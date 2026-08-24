<?php
declare(strict_types=1);

/**
 * BlogAdminLogic  |  CRUD blog untuk panel admin
 *
 * Fitur:
 *  - getAll()         : ambil semua post dengan kategori
 *  - getById()        : ambil satu post lengkap + kategori
 *  - create()         : buat post baru + relasi kategori
 *  - update()         : edit post + relasi kategori
 *  - delete()         : hapus post + file thumbnail + relasi
 *  - uploadImage()    : validasi + simpan gambar (max 2MB input → compress 100-300KB output)
 *  - generateSlug()   : buat slug unik dari judul
 *  - getAllKategori()  : ambil semua kategori untuk form checkbox
 */
class BlogAdminLogic
{
    private mysqli $conn;

    /** Direktori upload relatif terhadap root proyek */
    private const UPLOAD_DIR = __DIR__ . '/../../assets/post/';

    /** URL path publik thumbnail */
    private const UPLOAD_URL = '/assets/post/';

    /** Batas upload file mentah: 2 MB */
    private const MAX_UPLOAD_BYTES = 2 * 1024 * 1024;

    /** Target ukuran output setelah kompresi: 150 KB */
    private const TARGET_SIZE_KB = 150;

    public function __construct(mysqli $conn)
    {
        $this->conn = $conn;
    }

    /* ─────────────────────────────────────────────────────────
       READ
    ───────────────────────────────────────────────────────── */

    /**
     * Ambil semua post, urut terbaru, dengan jumlah kategori.
     */
    public function getAll(): array
    {
        $stmt = $this->conn->prepare(
            "SELECT p.id, p.title, p.slug, p.excerpt, p.image,
                    p.views, p.createdAt, p.updatedAt,
                    GROUP_CONCAT(k.nama ORDER BY k.nama SEPARATOR ', ') AS kategori_names
             FROM   Post p
             LEFT   JOIN _KategoriToPost kp ON kp.B = p.id
             LEFT   JOIN Kategori k          ON k.id  = kp.A
             GROUP  BY p.id
             ORDER  BY p.createdAt DESC"
        );
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Ambil satu post lengkap beserta array id kategori terpilih, berdasarkan slug.
     */
    public function getBySlug(string $slug): ?array
    {
        $stmt = $this->conn->prepare(
            "SELECT id, title, slug, excerpt, content, image, views, createdAt, updatedAt
             FROM   Post WHERE slug = ? LIMIT 1"
        );
        $stmt->bind_param('s', $slug);
        $stmt->execute();
        $post = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$post) return null;

        // Ambil kategori yang sudah dipilih
        $stmt2 = $this->conn->prepare(
            "SELECT A FROM _KategoriToPost WHERE B = ?"
        );
        $stmt2->bind_param('i', $post['id']);
        $stmt2->execute();
        $rows  = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt2->close();

        $post['kategori_ids'] = array_column($rows, 'A');
        return $post;
    }

    /**
     * Ambil satu post lengkap beserta array id kategori terpilih.
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->conn->prepare(
            "SELECT id, title, slug, excerpt, content, image, views, createdAt, updatedAt
             FROM   Post WHERE id = ? LIMIT 1"
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $post = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$post) return null;

        // Ambil kategori yang sudah dipilih
        $stmt2 = $this->conn->prepare(
            "SELECT A FROM _KategoriToPost WHERE B = ?"
        );
        $stmt2->bind_param('i', $id);
        $stmt2->execute();
        $rows  = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt2->close();

        $post['kategori_ids'] = array_column($rows, 'A');
        return $post;
    }

    /* ─────────────────────────────────────────────────────────
       CREATE
    ───────────────────────────────────────────────────────── */

    /**
     * Buat post baru.
     *
     * @param  array  $data      ['title', 'excerpt', 'content', 'image']
     * @param  array  $kategoriIds  array of int
     * @return array{success:bool, message:string, id:int|null}
     */
    public function create(array $data, array $kategoriIds): array
    {
        $title   = trim($data['title']   ?? '');
        $excerpt = trim($data['excerpt'] ?? '');
        $content = trim($data['content'] ?? '');
        $image   = $data['image'] ?? null;

        if ($title === '') {
            return ['success' => false, 'message' => 'Judul tidak boleh kosong.', 'id' => null];
        }
        if ($content === '') {
            return ['success' => false, 'message' => 'Konten tidak boleh kosong.', 'id' => null];
        }
        if (mb_strlen($excerpt) > 150) {
            return ['success' => false, 'message' => 'Excerpt maksimal 150 karakter.', 'id' => null];
        }

        $slug = $this->generateSlug($title);
        $now  = date('Y-m-d H:i:s.000');

        $stmt = $this->conn->prepare(
            "INSERT INTO Post (title, slug, excerpt, content, image, views, createdAt, updatedAt)
             VALUES (?, ?, ?, ?, ?, 0, NOW(3), NOW(3))"
        );
        $stmt->bind_param('sssss', $title, $slug, $excerpt, $content, $image);
        $stmt->execute();
        $newId = (int) $this->conn->insert_id;
        $stmt->close();

        // Relasi kategori
        if (!empty($kategoriIds)) {
            $this->syncKategori($newId, $kategoriIds);
        }

        return ['success' => true, 'message' => 'Artikel berhasil dibuat.', 'id' => $newId];
    }

    /* ─────────────────────────────────────────────────────────
       UPDATE
    ───────────────────────────────────────────────────────── */

    /**
     * Edit post yang sudah ada.
     *
     * @return array{success:bool, message:string}
     */
    public function update(int $id, array $data, array $kategoriIds): array
    {
        $title   = trim($data['title']   ?? '');
        $excerpt = trim($data['excerpt'] ?? '');
        $content = trim($data['content'] ?? '');
        $image   = $data['image'] ?? null; // null berarti tidak ganti gambar

        if ($title === '') {
            return ['success' => false, 'message' => 'Judul tidak boleh kosong.'];
        }
        if ($content === '') {
            return ['success' => false, 'message' => 'Konten tidak boleh kosong.'];
        }
        if (mb_strlen($excerpt) > 150) {
            return ['success' => false, 'message' => 'Excerpt maksimal 150 karakter.'];
        }

        // Regenerate slug hanya jika judul berubah
        $existing = $this->getById($id);
        if (!$existing) {
            return ['success' => false, 'message' => 'Artikel tidak ditemukan.'];
        }

        $slug = ($existing['title'] !== $title)
            ? $this->generateSlug($title, $id)
            : $existing['slug'];

        if ($image !== null) {
            // Ada gambar baru  |  hapus gambar lama
            $this->deleteImageFile($existing['image']);
            $stmt = $this->conn->prepare(
                "UPDATE Post SET title=?, slug=?, excerpt=?, content=?, image=?, updatedAt=NOW(3)
                 WHERE id=?"
            );
            $stmt->bind_param('sssssi', $title, $slug, $excerpt, $content, $image, $id);
        } else {
            // Pertahankan gambar lama
            $stmt = $this->conn->prepare(
                "UPDATE Post SET title=?, slug=?, excerpt=?, content=?, updatedAt=NOW(3)
                 WHERE id=?"
            );
            $stmt->bind_param('ssssi', $title, $slug, $excerpt, $content, $id);
        }

        $stmt->execute();
        $stmt->close();

        // Sync ulang kategori
        $this->syncKategori($id, $kategoriIds);

        return ['success' => true, 'message' => 'Artikel berhasil diperbarui.'];
    }

    /* ─────────────────────────────────────────────────────────
       DELETE
    ───────────────────────────────────────────────────────── */

    /**
     * Hapus post beserta thumbnail dan relasi kategori-nya.
     *
     * @return array{success:bool, message:string}
     */
    public function delete(int $id): array
    {
        $post = $this->getById($id);
        if (!$post) {
            return ['success' => false, 'message' => 'Artikel tidak ditemukan.'];
        }

        // Hapus relasi kategori dulu (CASCADE sebenarnya sudah ada di DB,
        // tapi kita hapus eksplisit untuk kepastian)
        $stmtKat = $this->conn->prepare("DELETE FROM _KategoriToPost WHERE B = ?");
        $stmtKat->bind_param('i', $id);
        $stmtKat->execute();
        $stmtKat->close();

        // Hapus ulasan terkait
        $stmtUlasan = $this->conn->prepare("DELETE FROM Ulasan WHERE post_id = ?");
        $stmtUlasan->bind_param('i', $id);
        $stmtUlasan->execute();
        $stmtUlasan->close();

        // Hapus post
        $stmt = $this->conn->prepare("DELETE FROM Post WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();

        // Hapus file gambar
        $this->deleteImageFile($post['image']);

        return ['success' => true, 'message' => 'Artikel berhasil dihapus.'];
    }

    /* ─────────────────────────────────────────────────────────
       UPLOAD & COMPRESS GAMBAR
    ───────────────────────────────────────────────────────── */

    /**
     * Validasi, simpan, dan kompres gambar thumbnail.
     *
     * - Menerima: JPEG/PNG/WebP/GIF, maks 2 MB
     * - Output  : JPEG, ~150 KB (rentang 100–300 KB)
     *
     * @param  array  $file   $_FILES['thumbnail']
     * @param  string $slug   dipakai sebagai nama file
     * @return array{success:bool, message:string, path:string|null}
     */
    public function uploadImage(array $file, string $slug): array
    {
        // Pastikan direktori ada
        if (!is_dir(self::UPLOAD_DIR)) {
            mkdir(self::UPLOAD_DIR, 0755, true);
        }

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $errMap = [
                UPLOAD_ERR_INI_SIZE   => 'File melebihi batas server.',
                UPLOAD_ERR_FORM_SIZE  => 'File terlalu besar.',
                UPLOAD_ERR_PARTIAL    => 'Upload tidak lengkap.',
                UPLOAD_ERR_NO_FILE    => 'Tidak ada file yang dipilih.',
                UPLOAD_ERR_NO_TMP_DIR => 'Direktori sementara tidak tersedia.',
                UPLOAD_ERR_CANT_WRITE => 'Gagal menulis ke disk.',
            ];
            $msg = $errMap[$file['error']] ?? 'Terjadi kesalahan saat upload.';
            return ['success' => false, 'message' => $msg, 'path' => null];
        }

        // Validasi ukuran mentah
        if ($file['size'] > self::MAX_UPLOAD_BYTES) {
            return ['success' => false, 'message' => 'Ukuran gambar maksimal 2 MB.', 'path' => null];
        }

        // Validasi MIME type dari konten file (bukan extension)
        $finfo    = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        $allowed  = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

        if (!in_array($mimeType, $allowed, true)) {
            return ['success' => false, 'message' => 'Format gambar tidak didukung. Gunakan JPG, PNG, atau WebP.', 'path' => null];
        }

        // Pastikan GD tersedia
        if (!extension_loaded('gd')) {
            return ['success' => false, 'message' => 'Ekstensi GD tidak aktif di server.', 'path' => null];
        }

        // Buat resource GD dari file
        $srcImage = match ($mimeType) {
            'image/jpeg' => imagecreatefromjpeg($file['tmp_name']),
            'image/png'  => imagecreatefrompng($file['tmp_name']),
            'image/webp' => imagecreatefromwebp($file['tmp_name']),
            'image/gif'  => imagecreatefromgif($file['tmp_name']),
            default      => false,
        };

        if ($srcImage === false) {
            return ['success' => false, 'message' => 'Gagal memproses gambar.', 'path' => null];
        }

        // Resize proporsional: max lebar 1200px
        [$origW, $origH] = getimagesize($file['tmp_name']);
        $maxW = 1200;
        if ($origW > $maxW) {
            $ratio  = $maxW / $origW;
            $newW   = $maxW;
            $newH   = (int) round($origH * $ratio);
            $canvas = imagecreatetruecolor($newW, $newH);

            // Preserve alpha untuk PNG/WebP
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            imagecopyresampled($canvas, $srcImage, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
            imagedestroy($srcImage);
            $srcImage = $canvas;
        }

        // Nama file output: {slug}-{timestamp}-{random6}.jpg — mencegah collision dan enumeration
        $filename  = preg_replace('/[^a-z0-9\-]/', '', $slug) . '-' . time() . '-' . bin2hex(random_bytes(3)) . '.jpg';
        $destPath  = self::UPLOAD_DIR . $filename;
        $publicUrl = self::UPLOAD_URL . $filename;

        // Kompresi iteratif: turunkan quality sampai ukuran ≤ TARGET_SIZE_KB
        $quality  = 82;
        $minQuality = 40;

        do {
            imagejpeg($srcImage, $destPath, $quality);
            $sizeKb = filesize($destPath) / 1024;
            if ($sizeKb <= self::TARGET_SIZE_KB) break;
            $quality -= 5;
        } while ($quality >= $minQuality);

        imagedestroy($srcImage);

        return ['success' => true, 'message' => 'Gambar berhasil diupload.', 'path' => $publicUrl];
    }

    /* ─────────────────────────────────────────────────────────
       HELPER: SLUG
    ───────────────────────────────────────────────────────── */

    /**
     * Generate slug unik dari judul.
     * Jika ada slug duplikat, tambahkan suffix angka.
     */
    public function generateSlug(string $title, int $excludeId = 0): string
    {
        // Transliterasi dasar karakter Indonesia
        $title = str_replace(
            ['á','à','ä','â','é','è','ë','ê','í','ì','ï','î','ó','ò','ö','ô','ú','ù','ü','û','ñ','ç'],
            ['a','a','a','a','e','e','e','e','i','i','i','i','o','o','o','o','u','u','u','u','n','c'],
            mb_strtolower($title)
        );

        $slug = preg_replace('/[^a-z0-9\s\-]/', '', $title);
        $slug = preg_replace('/[\s\-]+/', '-', trim($slug ?? ''));
        $slug = trim($slug, '-');
        $slug = substr($slug, 0, 191);

        // Cek keunikan
        $base    = $slug;
        $counter = 1;

        while (true) {
            if ($excludeId > 0) {
                $stmt = $this->conn->prepare(
                    "SELECT id FROM Post WHERE slug = ? AND id != ? LIMIT 1"
                );
                $stmt->bind_param('si', $slug, $excludeId);
            } else {
                $stmt = $this->conn->prepare(
                    "SELECT id FROM Post WHERE slug = ? LIMIT 1"
                );
                $stmt->bind_param('s', $slug);
            }
            $stmt->execute();
            $exists = $stmt->get_result()->num_rows > 0;
            $stmt->close();

            if (!$exists) break;
            $slug = $base . '-' . $counter++;
        }

        return $slug;
    }

    /* ─────────────────────────────────────────────────────────
       HELPER: KATEGORI
    ───────────────────────────────────────────────────────── */

    /**
     * Ambil semua kategori untuk form checkbox.
     */
    public function getAllKategori(): array
    {
        $stmt = $this->conn->prepare(
            "SELECT id, nama FROM Kategori ORDER BY nama ASC"
        );
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Sync relasi kategori ↔ post (hapus lama, insert baru).
     */
    private function syncKategori(int $postId, array $kategoriIds): void
    {
        // Hapus relasi lama
        $del = $this->conn->prepare("DELETE FROM _KategoriToPost WHERE B = ?");
        $del->bind_param('i', $postId);
        $del->execute();
        $del->close();

        if (empty($kategoriIds)) return;

        // Insert satu per satu — aman, mudah dibaca, tidak ada bug bind-by-ref
        $ins = $this->conn->prepare(
            "INSERT IGNORE INTO _KategoriToPost (A, B) VALUES (?, ?)"
        );
        foreach ($kategoriIds as $kid) {
            $kid = (int) $kid;
            $ins->bind_param('ii', $kid, $postId);
            $ins->execute();
        }
        $ins->close();
    }

    /* ─────────────────────────────────────────────────────────
       HELPER: DELETE IMAGE FILE
    ───────────────────────────────────────────────────────── */

    /**
     * Hapus file gambar dari disk jika ada.
     * Hanya hapus file yang berada di dalam UPLOAD_DIR (keamanan path traversal).
     */
    private function deleteImageFile(?string $imagePath): void
    {
        if (empty($imagePath)) return;

        // Hanya hapus file yang ada di /assets/post/
        if (!str_starts_with($imagePath, self::UPLOAD_URL)) return;

        $filename = basename($imagePath);
        // Validasi: hanya karakter aman
        if (!preg_match('/^[a-zA-Z0-9_\-]+\.jpe?g$/i', $filename)) return;

        $fullPath = self::UPLOAD_DIR . $filename;
        if (file_exists($fullPath)) {
            @unlink($fullPath);
        }
    }
}
