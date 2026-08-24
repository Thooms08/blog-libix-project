<?php

class UlasanUserLogic
{
    private mysqli $conn;

    const MAX_COMMENT_LENGTH = 500;
    const SPAM_WINDOW_HOURS  = 1;

    public function __construct(mysqli $connection)
    {
        $this->conn = $connection;
    }

    /**
     * Ambil semua ulasan publik untuk satu post, urut terbaru.
     */
    public function getByPostId(int $postId): array
    {
        $stmt = $this->conn->prepare(
            "SELECT id, rating, comment, created_at
             FROM   Ulasan
             WHERE  post_id   = ?
             AND    is_public  = 1
             ORDER  BY created_at DESC"
        );
        $stmt->bind_param('i', $postId);
        $stmt->execute();
        $result = $stmt->get_result();

        $reviews = [];
        while ($row = $result->fetch_assoc()) {
            $reviews[] = $row;
        }

        return $reviews;
    }

    /**
     * Rata-rata rating & jumlah total ulasan publik untuk satu post.
     */
    public function getAverageRating(int $postId): array
    {
        $stmt = $this->conn->prepare(
            "SELECT COALESCE(AVG(rating), 0) AS avg_rating,
                    COUNT(*)                  AS total_reviews
             FROM   Ulasan
             WHERE  post_id  = ?
             AND    is_public = 1"
        );
        $stmt->bind_param('i', $postId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        return [
            'avg_rating'    => (float) $row['avg_rating'],
            'total_reviews' => (int)   $row['total_reviews'],
        ];
    }

    /**
     * Distribusi jumlah per bintang untuk ulasan publik (5 → 1).
     */
    public function getRatingDistribution(int $postId): array
    {
        $stmt = $this->conn->prepare(
            "SELECT rating, COUNT(*) AS count
             FROM   Ulasan
             WHERE  post_id  = ?
             AND    is_public = 1
             GROUP  BY rating"
        );
        $stmt->bind_param('i', $postId);
        $stmt->execute();
        $result = $stmt->get_result();

        $dist = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        while ($row = $result->fetch_assoc()) {
            $dist[(int) $row['rating']] = (int) $row['count'];
        }

        return $dist;
    }

    /**
     * Simpan ulasan baru. Mengembalikan array ['success', 'message'].
     */
    public function addReview(
        int    $postId,
        int    $rating,
        string $comment   = '',
        string $ipAddress = '',
        string $userAgent = ''
    ): array {
        // Validasi rating
        if ($rating < 1 || $rating > 5) {
            return ['success' => false, 'message' => 'Rating harus antara 1 sampai 5.'];
        }

        // Validasi panjang komentar
        $comment = trim($comment);
        if (mb_strlen($comment) > self::MAX_COMMENT_LENGTH) {
            return [
                'success' => false,
                'message' => 'Komentar maksimal ' . self::MAX_COMMENT_LENGTH . ' karakter.',
            ];
        }

        // Anti-spam: satu IP per post per jam
        if ($this->hasRecentReview($postId, $ipAddress)) {
            return [
                'success' => false,
                'message' => 'Anda sudah memberikan ulasan untuk artikel ini. Silakan coba lagi nanti.',
            ];
        }

        $commentVal   = $comment   !== '' ? $comment   : null;
        $ipVal        = $ipAddress !== '' ? $ipAddress : null;
        $agentVal     = $userAgent !== '' ? $userAgent : null;

        $stmt = $this->conn->prepare(
            "INSERT INTO Ulasan
                (post_id, rating, comment, ip_address, user_agent, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, NOW(3), NOW(3))"
        );
        $stmt->bind_param('iisss', $postId, $rating, $commentVal, $ipVal, $agentVal);

        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Ulasan berhasil dikirim. Terima kasih!'];
        }

        return ['success' => false, 'message' => 'Gagal menyimpan ulasan. Silakan coba lagi.'];
    }

    /**
     * Cek apakah IP sudah review post ini dalam rentang waktu tertentu.
     */
    public function hasRecentReview(int $postId, string $ipAddress): bool
    {
        if ($ipAddress === '') {
            return false;
        }

        $hours = self::SPAM_WINDOW_HOURS;
        $stmt  = $this->conn->prepare(
            "SELECT id FROM Ulasan
             WHERE  post_id    = ?
             AND    ip_address = ?
             AND    created_at > DATE_SUB(NOW(), INTERVAL ? HOUR)
             LIMIT  1"
        );
        $stmt->bind_param('isi', $postId, $ipAddress, $hours);
        $stmt->execute();

        return $stmt->get_result()->num_rows > 0;
    }
}
