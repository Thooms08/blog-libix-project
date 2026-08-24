<?php
declare(strict_types=1);

/**
 * ProfileAdminLogic  |  Ubah username dan/atau password admin
 *
 * Aturan bisnis:
 *  - Admin bisa ubah username saja, password saja, atau keduanya sekaligus.
 *  - Untuk mengubah password, wajib memasukkan password lama yang benar.
 *  - Username baru harus unik (tidak boleh sama dengan user lain).
 *  - Password baru min 8 karakter.
 *
 * Keamanan:
 *  - Semua query pakai prepared statement (anti SQL Injection).
 *  - Password di-hash dengan PHP password_hash (bcrypt).
 *  - Kompatibel dengan hash $2b dari Node.js bcryptjs.
 */
class ProfileAdminLogic
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
     * Ambil data profil admin berdasarkan ID.
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->conn->prepare(
            "SELECT id, name, username, email, createdAt FROM `User` WHERE id = ? LIMIT 1"
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    /* ─────────────────────────────────────────────────────────
       UPDATE NAME
    ───────────────────────────────────────────────────────── */

    /**
     * Ubah nama tampilan admin.
     *
     * @return array{success:bool, message:string, name:string|null}
     */
    public function updateName(int $id, string $newName): array
    {
        $newName = trim($newName);

        if ($newName === '') {
            return ['success' => false, 'message' => 'Nama tidak boleh kosong.', 'name' => null];
        }

        if (mb_strlen($newName) < 2) {
            return ['success' => false, 'message' => 'Nama minimal 2 karakter.', 'name' => null];
        }

        if (mb_strlen($newName) > 100) {
            return ['success' => false, 'message' => 'Nama maksimal 100 karakter.', 'name' => null];
        }

        $stmt = $this->conn->prepare(
            "UPDATE `User` SET name = ?, updatedAt = NOW(3) WHERE id = ?"
        );
        $stmt->bind_param('si', $newName, $id);
        $stmt->execute();
        $stmt->close();

        return [
            'success' => true,
            'message' => 'Nama berhasil diperbarui.',
            'name'    => $newName,
        ];
    }

    /* ─────────────────────────────────────────────────────────
       UPDATE USERNAME
    ───────────────────────────────────────────────────────── */

    /**
     * Ubah username admin.
     *
     * @return array{success:bool, message:string, username:string|null}
     */
    public function updateUsername(int $id, string $newUsername): array
    {
        $newUsername = trim($newUsername);

        if ($newUsername === '') {
            return ['success' => false, 'message' => 'Username tidak boleh kosong.', 'username' => null];
        }

        // Validasi format: hanya huruf, angka, underscore, dash; panjang 3–50
        if (!preg_match('/^[a-zA-Z0-9_\-]{3,50}$/', $newUsername)) {
            return [
                'success'  => false,
                'message'  => 'Username hanya boleh mengandung huruf, angka, underscore (_) dan dash (-), panjang 3–50 karakter.',
                'username' => null,
            ];
        }

        // Cek duplikat (case-insensitive), kecuali milik sendiri
        $stmt = $this->conn->prepare(
            "SELECT id FROM `User` WHERE LOWER(username) = LOWER(?) AND id != ? LIMIT 1"
        );
        $stmt->bind_param('si', $newUsername, $id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $stmt->close();
            return ['success' => false, 'message' => "Username \"{$newUsername}\" sudah digunakan.", 'username' => null];
        }
        $stmt->close();

        // Update
        $stmt = $this->conn->prepare(
            "UPDATE `User` SET username = ?, updatedAt = NOW(3) WHERE id = ?"
        );
        $stmt->bind_param('si', $newUsername, $id);
        $stmt->execute();
        $stmt->close();

        return [
            'success'  => true,
            'message'  => 'Username berhasil diperbarui.',
            'username' => $newUsername,
        ];
    }

    /* ─────────────────────────────────────────────────────────
       UPDATE PASSWORD
    ───────────────────────────────────────────────────────── */

    /**
     * Ubah password admin.
     * Wajib verifikasi password lama sebelum menyimpan yang baru.
     *
     * @return array{success:bool, message:string}
     */
    public function updatePassword(int $id, string $oldPassword, string $newPassword, string $confirmPassword): array
    {
        if ($oldPassword === '') {
            return ['success' => false, 'message' => 'Password lama tidak boleh kosong.'];
        }
        if ($newPassword === '') {
            return ['success' => false, 'message' => 'Password baru tidak boleh kosong.'];
        }
        if (strlen($newPassword) < 8) {
            return ['success' => false, 'message' => 'Password baru minimal 8 karakter.'];
        }
        if ($newPassword !== $confirmPassword) {
            return ['success' => false, 'message' => 'Konfirmasi password tidak cocok.'];
        }
        if ($oldPassword === $newPassword) {
            return ['success' => false, 'message' => 'Password baru tidak boleh sama dengan password lama.'];
        }

        // Ambil hash password saat ini
        $stmt = $this->conn->prepare("SELECT password FROM `User` WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            return ['success' => false, 'message' => 'Akun tidak ditemukan.'];
        }

        // Kompatibilitas bcrypt Node.js ($2b → $2y)
        $hash = $row['password'];
        if (str_starts_with($hash, '$2b$')) {
            $hash = '$2y$' . substr($hash, 4);
        }

        if (!password_verify($oldPassword, $hash)) {
            return ['success' => false, 'message' => 'Password lama tidak benar.'];
        }

        // Hash password baru dengan bcrypt
        $newHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);

        $stmt = $this->conn->prepare(
            "UPDATE `User` SET password = ?, updatedAt = NOW(3) WHERE id = ?"
        );
        $stmt->bind_param('si', $newHash, $id);
        $stmt->execute();
        $stmt->close();

        return ['success' => true, 'message' => 'Password berhasil diperbarui.'];
    }
}
