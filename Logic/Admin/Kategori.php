<?php
declare(strict_types=1);

/**
 * KategoriAdminLogic  |  CRUD kategori untuk panel admin
 *
 * Operasi:
 *  - getAll()    : semua kategori + jumlah artikel per kategori
 *  - getById()   : satu kategori berdasarkan id
 *  - create()    : buat kategori baru (nama maks 100 karakter, unique)
 *  - update()    : ubah nama kategori
 *  - delete()    : hapus kategori + relasi _KategoriToPost
 *
 * Keamanan:
 *  - Semua query pakai prepared statement (anti SQL Injection)
 *  - Output di-escape di view (anti XSS)
 */
class KategoriAdminLogic
{
    private mysqli $conn;

    public function __construct(mysqli $conn)
    {
        $this->conn = $conn;
    }

    /* ─────────────────────────────────────────────────────────
       READ
    ───────────────────────────────────────────────────────── */

    /**
     * Ambil semua kategori beserta jumlah artikel yang menggunakannya.
     */
    public function getAll(): array
    {
        $stmt = $this->conn->prepare(
            "SELECT k.id, k.nama, k.created_at,
                    COUNT(kp.B) AS jumlah_artikel
             FROM   Kategori k
             LEFT   JOIN _KategoriToPost kp ON kp.A = k.id
             GROUP  BY k.id
             ORDER  BY k.nama ASC"
        );
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Ambil satu kategori berdasarkan ID.
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->conn->prepare(
            "SELECT id, nama, created_at FROM Kategori WHERE id = ? LIMIT 1"
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    /* ─────────────────────────────────────────────────────────
       CREATE
    ───────────────────────────────────────────────────────── */

    /**
     * Buat kategori baru.
     *
     * @return array{success:bool, message:string, id:int|null}
     */
    public function create(string $nama): array
    {
        $nama = trim($nama);

        if ($nama === '') {
            return ['success' => false, 'message' => 'Nama kategori tidak boleh kosong.', 'id' => null];
        }
        if (mb_strlen($nama) > 100) {
            return ['success' => false, 'message' => 'Nama kategori maksimal 100 karakter.', 'id' => null];
        }

        // Cek duplikat (case-insensitive)
        if ($this->namaExists($nama)) {
            return ['success' => false, 'message' => "Kategori \"{$nama}\" sudah ada.", 'id' => null];
        }

        $stmt = $this->conn->prepare(
            "INSERT INTO Kategori (nama, created_at, updated_at)
             VALUES (?, NOW(3), NOW(3))"
        );
        $stmt->bind_param('s', $nama);
        $stmt->execute();
        $newId = (int) $this->conn->insert_id;
        $stmt->close();

        return ['success' => true, 'message' => "Kategori \"{$nama}\" berhasil ditambahkan.", 'id' => $newId];
    }

    /* ─────────────────────────────────────────────────────────
       UPDATE
    ───────────────────────────────────────────────────────── */

    /**
     * Update nama kategori.
     *
     * @return array{success:bool, message:string}
     */
    public function update(int $id, string $nama): array
    {
        $nama = trim($nama);

        if ($nama === '') {
            return ['success' => false, 'message' => 'Nama kategori tidak boleh kosong.'];
        }
        if (mb_strlen($nama) > 100) {
            return ['success' => false, 'message' => 'Nama kategori maksimal 100 karakter.'];
        }
        if (!$this->getById($id)) {
            return ['success' => false, 'message' => 'Kategori tidak ditemukan.'];
        }

        // Cek duplikat, kecuali ID ini sendiri
        if ($this->namaExists($nama, $id)) {
            return ['success' => false, 'message' => "Kategori \"{$nama}\" sudah ada."];
        }

        $stmt = $this->conn->prepare(
            "UPDATE Kategori SET nama = ?, updated_at = NOW(3) WHERE id = ?"
        );
        $stmt->bind_param('si', $nama, $id);
        $stmt->execute();
        $stmt->close();

        return ['success' => true, 'message' => "Kategori berhasil diperbarui menjadi \"{$nama}\"."];
    }

    /* ─────────────────────────────────────────────────────────
       DELETE
    ───────────────────────────────────────────────────────── */

    /**
     * Hapus kategori beserta relasinya ke Post.
     *
     * @return array{success:bool, message:string}
     */
    public function delete(int $id): array
    {
        $kat = $this->getById($id);
        if (!$kat) {
            return ['success' => false, 'message' => 'Kategori tidak ditemukan.'];
        }

        // Hapus relasi (ON DELETE CASCADE sudah ada di DB, tapi eksplisit lebih aman)
        $relStmt = $this->conn->prepare("DELETE FROM _KategoriToPost WHERE A = ?");
        $relStmt->bind_param('i', $id);
        $relStmt->execute();
        $relStmt->close();

        // Hapus kategori
        $stmt = $this->conn->prepare("DELETE FROM Kategori WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();

        return ['success' => true, 'message' => "Kategori \"{$kat['nama']}\" berhasil dihapus."];
    }

    /* ─────────────────────────────────────────────────────────
       HELPER PRIVATE
    ───────────────────────────────────────────────────────── */

    /**
     * Cek apakah nama sudah dipakai (case-insensitive).
     * $excludeId dipakai saat update agar tidak flagging dirinya sendiri.
     */
    private function namaExists(string $nama, int $excludeId = 0): bool
    {
        if ($excludeId > 0) {
            $stmt = $this->conn->prepare(
                "SELECT id FROM Kategori WHERE LOWER(nama) = LOWER(?) AND id != ? LIMIT 1"
            );
            $stmt->bind_param('si', $nama, $excludeId);
        } else {
            $stmt = $this->conn->prepare(
                "SELECT id FROM Kategori WHERE LOWER(nama) = LOWER(?) LIMIT 1"
            );
            $stmt->bind_param('s', $nama);
        }
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        return $exists;
    }
}
