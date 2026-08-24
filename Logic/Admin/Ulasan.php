<?php
declare(strict_types=1);

/**
 * UlasanAdminLogic  |  Manajemen ulasan untuk panel admin
 *
 * Operasi:
 *  - getAll()          : semua ulasan + judul post, urut terbaru
 *  - getById()         : satu ulasan by id
 *  - togglePublic()    : toggle is_public antara 1 ↔ 0
 *  - delete()          : hapus ulasan permanen
 *  - getStats()        : ringkasan statistik (total, publik, privat, avg rating)
 *
 * Keamanan:
 *  - Semua query pakai prepared statement (anti SQL Injection)
 */
class UlasanAdminLogic
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
     * Ambil semua ulasan beserta judul postnya, urut terbaru.
     */
    public function getAll(): array
    {
        $stmt = $this->conn->prepare(
            "SELECT u.id, u.post_id, u.rating, u.comment,
                    u.ip_address, u.is_public, u.created_at,
                    p.title AS post_title, p.slug AS post_slug
             FROM   Ulasan u
             LEFT   JOIN Post p ON p.id = u.post_id
             ORDER  BY u.created_at DESC"
        );
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Ambil satu ulasan berdasarkan ID.
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->conn->prepare(
            "SELECT u.id, u.post_id, u.rating, u.comment,
                    u.ip_address, u.is_public, u.created_at,
                    p.title AS post_title, p.slug AS post_slug
             FROM   Ulasan u
             LEFT   JOIN Post p ON p.id = u.post_id
             WHERE  u.id = ? LIMIT 1"
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    /* ─────────────────────────────────────────────────────────
       TOGGLE PUBLIC
    ───────────────────────────────────────────────────────── */

    /**
     * Toggle status is_public sebuah ulasan.
     *
     * @return array{success:bool, message:string, is_public:int|null}
     */
    public function togglePublic(int $id): array
    {
        $ulasan = $this->getById($id);
        if (!$ulasan) {
            return ['success' => false, 'message' => 'Ulasan tidak ditemukan.', 'is_public' => null];
        }

        $newStatus = $ulasan['is_public'] ? 0 : 1;

        $stmt = $this->conn->prepare(
            "UPDATE Ulasan SET is_public = ?, updated_at = NOW(3) WHERE id = ?"
        );
        $stmt->bind_param('ii', $newStatus, $id);
        $stmt->execute();
        $stmt->close();

        $label = $newStatus ? 'dipublikasikan' : 'disembunyikan';
        return [
            'success'   => true,
            'message'   => "Ulasan berhasil {$label}.",
            'is_public' => $newStatus,
        ];
    }

    /* ─────────────────────────────────────────────────────────
       DELETE
    ───────────────────────────────────────────────────────── */

    /**
     * Hapus ulasan permanen.
     *
     * @return array{success:bool, message:string}
     */
    public function delete(int $id): array
    {
        $ulasan = $this->getById($id);
        if (!$ulasan) {
            return ['success' => false, 'message' => 'Ulasan tidak ditemukan.'];
        }

        $stmt = $this->conn->prepare("DELETE FROM Ulasan WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();

        return ['success' => true, 'message' => 'Ulasan berhasil dihapus.'];
    }

    /* ─────────────────────────────────────────────────────────
       STATS
    ───────────────────────────────────────────────────────── */

    /**
     * Statistik ringkasan ulasan.
     */
    public function getStats(): array
    {
        $stmt = $this->conn->prepare(
            "SELECT
                COUNT(*)                          AS total,
                SUM(is_public = 1)                AS publik,
                SUM(is_public = 0)                AS privat,
                ROUND(AVG(rating), 1)             AS avg_rating,
                SUM(comment IS NOT NULL AND comment != '') AS with_comment
             FROM Ulasan"
        );
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return [
            'total'        => (int)   ($row['total']        ?? 0),
            'publik'       => (int)   ($row['publik']       ?? 0),
            'privat'       => (int)   ($row['privat']       ?? 0),
            'avg_rating'   => (float) ($row['avg_rating']   ?? 0),
            'with_comment' => (int)   ($row['with_comment'] ?? 0),
        ];
    }
}
