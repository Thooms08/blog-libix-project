<?php

class BlogUserLogic
{
    private mysqli $conn;

    public function __construct(mysqli $connection)
    {
        $this->conn = $connection;
    }

    /**
     * Ambil satu post berdasarkan slug, beserta nama penulis & kategori.
     * Semua data diambil dalam 2 query (tidak ada N+1):
     *  1. POST + nama author (JOIN User sekali)
     *  2. Kategori yang melekat pada post
     */
    public function getBySlug(string $slug): ?array
    {
        // Query 1: post + author dalam satu query (JOIN User)
        $stmt = $this->conn->prepare(
            "SELECT p.id, p.title, p.slug, p.excerpt, p.content,
                    p.image, p.views, p.createdAt, p.updatedAt,
                    COALESCE(u.name, 'Admin blog.libix.tech') AS author_name
             FROM   Post p
             LEFT   JOIN User u ON u.id = (SELECT MIN(id) FROM User)
             WHERE  p.slug = ?
             LIMIT  1"
        );
        $stmt->bind_param('s', $slug);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            return null;
        }

        $post = $result->fetch_assoc();
        $stmt->close();

        // Query 2: kategori post
        $post['categories'] = $this->getCategoriesByPostId((int) $post['id']);

        return $post;
    }

    /**
     * Ambil semua kategori yang melekat pada sebuah post.
     */
    public function getCategoriesByPostId(int $postId): array
    {
        $stmt = $this->conn->prepare(
            "SELECT k.id, k.nama
             FROM   Kategori k
             INNER  JOIN _KategoriToPost kp ON k.id = kp.A
             WHERE  kp.B = ?"
        );
        $stmt->bind_param('i', $postId);
        $stmt->execute();
        $result = $stmt->get_result();

        $categories = [];
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }

        return $categories;
    }

    /**
     * Tambah satu view pada post.
     */
    public function incrementViews(int $postId): void
    {
        $stmt = $this->conn->prepare(
            "UPDATE Post SET views = views + 1 WHERE id = ?"
        );
        $stmt->bind_param('i', $postId);
        $stmt->execute();
    }

    /**
     * Ambil N post terbanyak views dalam sebuah kategori (by kategori ID).
     *
     * @return array<int,array>
     */
    public function getTopByKategoriId(int $kategoriId, int $limit = 5): array
    {
        $stmt = $this->conn->prepare(
            "SELECT p.id, p.title, p.slug, p.excerpt, p.image, p.views, p.createdAt
             FROM   Post p
             INNER  JOIN _KategoriToPost kp ON kp.B = p.id
             WHERE  kp.A = ?
             ORDER  BY p.views DESC
             LIMIT  ?"
        );
        $stmt->bind_param('ii', $kategoriId, $limit);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Ambil 5 kategori yang paling banyak dipakai (jumlah post terbanyak).
     * Hasil sudah menyertakan field 'slug' yang digenerate dari nama.
     *
     * @return array<int,array{id:int,nama:string,jumlah:int,slug:string}>
     */
    public function getTopKategori(int $limit = 5): array
    {
        $stmt = $this->conn->prepare(
            "SELECT k.id, k.nama, COUNT(kp.B) AS jumlah
             FROM   Kategori k
             INNER  JOIN _KategoriToPost kp ON kp.A = k.id
             GROUP  BY k.id, k.nama
             ORDER  BY jumlah DESC
             LIMIT  ?"
        );
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        // Tambahkan slug ke setiap baris
        foreach ($rows as &$row) {
            $row['slug'] = self::generateKategoriSlug($row['nama']);
        }
        unset($row);

        return $rows;
    }

    /**
     * Ambil semua post dalam sebuah kategori, urut terbaru.
     *
     * @return array<int,array>
     */
    public function getAllByKategoriId(int $kategoriId): array
    {
        $stmt = $this->conn->prepare(
            "SELECT p.id, p.title, p.slug, p.excerpt, p.image, p.views, p.createdAt, p.content
             FROM   Post p
             INNER  JOIN _KategoriToPost kp ON kp.B = p.id
             WHERE  kp.A = ?
             ORDER  BY p.createdAt DESC"
        );
        $stmt->bind_param('i', $kategoriId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Cari kategori berdasarkan ID.
     *
     * @return array{id:int,nama:string}|null
     */
    public function getKategoriById(int $id): ?array
    {
        $stmt = $this->conn->prepare(
            "SELECT id, nama FROM Kategori WHERE id = ? LIMIT 1"
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return $row ?: null;
    }

    /**
     * Cari kategori berdasarkan slug (digenerate dari nama).
     * Karena nama kategori unik, slug-nya pun unik.
     *
     * @return array{id:int,nama:string}|null
     */
    public function getKategoriBySlug(string $slug): ?array
    {
        // Ambil semua kategori lalu bandingkan slug-nya.
        // Jumlah kategori kecil (< 100), jadi tidak ada masalah performa.
        $stmt = $this->conn->prepare(
            "SELECT id, nama FROM Kategori ORDER BY id ASC"
        );
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        foreach ($rows as $row) {
            if (self::generateKategoriSlug($row['nama']) === $slug) {
                return $row;
            }
        }
        return null;
    }

    /**
     * Generate slug URL-friendly dari nama kategori.
     * Konsisten: nama yang sama selalu menghasilkan slug yang sama.
     *
     * Contoh:
     *   "Teknologi"      → "teknologi"
     *   "Aplikasi Kasir" → "aplikasi-kasir"
     *   "Bisnis kuliner" → "bisnis-kuliner"
     *   "strategi bisnis"→ "strategi-bisnis"
     */
    public static function generateKategoriSlug(string $nama): string
    {
        // Transliterasi karakter beraksara
        $nama = str_replace(
            ['á','à','ä','â','é','è','ë','ê','í','ì','ï','î',
             'ó','ò','ö','ô','ú','ù','ü','û','ñ','ç'],
            ['a','a','a','a','e','e','e','e','i','i','i','i',
             'o','o','o','o','u','u','u','u','n','c'],
            mb_strtolower(trim($nama))
        );

        // Hapus karakter selain huruf, angka, spasi, dan dash
        $slug = preg_replace('/[^a-z0-9\s\-]/', '', $nama);
        // Ganti spasi/dash berurutan jadi satu dash
        $slug = preg_replace('/[\s\-]+/', '-', $slug ?? '');
        return trim($slug, '-');
    }

    /**
     * Hitung total post (semua atau per kategori).
     */
    public function countPosts(int $kategoriId = 0): int
    {
        if ($kategoriId > 0) {
            $stmt = $this->conn->prepare(
                "SELECT COUNT(DISTINCT p.id)
                 FROM Post p
                 INNER JOIN _KategoriToPost kp ON kp.B = p.id
                 WHERE kp.A = ?"
            );
            $stmt->bind_param('i', $kategoriId);
        } else {
            $stmt = $this->conn->prepare("SELECT COUNT(*) FROM Post");
        }
        $stmt->execute();
        return (int) $stmt->get_result()->fetch_row()[0];
    }

    /**
     * Ambil post dengan paginasi (LIMIT + OFFSET), urut terbaru.
     */
    public function getPaginated(int $limit, int $offset, int $kategoriId = 0): array
    {
        if ($kategoriId > 0) {
            $stmt = $this->conn->prepare(
                "SELECT p.id, p.title, p.slug, p.excerpt, p.content,
                        p.image, p.views, p.createdAt
                 FROM   Post p
                 INNER  JOIN _KategoriToPost kp ON kp.B = p.id
                 WHERE  kp.A = ?
                 ORDER  BY p.createdAt DESC
                 LIMIT  ? OFFSET ?"
            );
            $stmt->bind_param('iii', $kategoriId, $limit, $offset);
        } else {
            $stmt = $this->conn->prepare(
                "SELECT id, title, slug, excerpt, content, image, views, createdAt
                 FROM   Post
                 ORDER  BY createdAt DESC
                 LIMIT  ? OFFSET ?"
            );
            $stmt->bind_param('ii', $limit, $offset);
        }
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}
