<?php
declare(strict_types=1);

/**
 * AuthLogic  |  Menangani login, logout, session, dan CSRF token
 *
 * Keamanan yang diterapkan:
 *  - CSRF Token : setiap form login memiliki token satu-kali-pakai
 *  - SQL Injection : semua query menggunakan prepared statement (MySQLi)
 *  - XSS          : output selalu di-escape dengan htmlspecialchars()
 *  - Brute-force  : rate-limit sederhana berbasis session (max 5 percobaan / 15 menit)
 *  - Session      : session_regenerate_id() setelah login, cookie HttpOnly + SameSite=Strict
 *  - Password     : PHP password_verify()  |  kompatibel dengan hash bcrypt Node.js ($2b → $2y)
 */
class AuthLogic
{
    private mysqli $conn;

    /** Jumlah maksimum percobaan login sebelum dikunci sementara */
    private const MAX_ATTEMPTS = 5;

    /** Durasi kunci dalam detik (15 menit) */
    private const LOCKOUT_TIME = 900;

    public function __construct(mysqli $conn)
    {
        $this->conn = $conn;
        $this->startSecureSession();
    }

    /* ─────────────────────────────────────────────────────────
       SESSION
    ───────────────────────────────────────────────────────── */

    /**
     * Mulai sesi dengan konfigurasi cookie yang aman.
     */
    private function startSecureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            // Deteksi HTTPS: cek langsung ATAU via reverse-proxy header
            $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'
                    || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
                    || ($_SERVER['HTTP_X_FORWARDED_SSL']   ?? '') === 'on'
                    || (int) ($_SERVER['SERVER_PORT'] ?? 80) === 443;

            session_set_cookie_params([
                'lifetime' => 0,
                'path'     => '/',
                'domain'   => '',
                'secure'   => $isHttps,
                'httponly' => true,
                'samesite' => 'Strict',
            ]);
            session_start();
        }
    }

    /* ─────────────────────────────────────────────────────────
       CSRF TOKEN
    ───────────────────────────────────────────────────────── */

    /**
     * Hasilkan CSRF token baru dan simpan di session.
     * Panggil ini saat merender form login.
     */
    public function generateCsrfToken(): string
    {
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token']      = $token;
        $_SESSION['csrf_token_time'] = time();
        return $token;
    }

    /**
     * Hasilkan CSRF token untuk operasi AJAX yang tidak one-time-use.
     * Token ini persist selama sesi (tidak dihapus setelah validasi),
     * sehingga aman dipakai berulang di halaman yang sama (multi-AJAX).
     * Token di-refresh tiap 30 menit.
     */
    public function getAjaxCsrfToken(): string
    {
        // Refresh jika belum ada atau sudah kadaluarsa (30 menit)
        if (
            empty($_SESSION['ajax_csrf_token']) ||
            empty($_SESSION['ajax_csrf_time']) ||
            (time() - $_SESSION['ajax_csrf_time']) > 1800
        ) {
            $_SESSION['ajax_csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['ajax_csrf_time']  = time();
        }
        return $_SESSION['ajax_csrf_token'];
    }

    /**
     * Validasi AJAX CSRF token (tidak one-time-use, tidak dihapus setelah cek).
     */
    public function validateAjaxCsrfToken(string $token): bool
    {
        if (empty($_SESSION['ajax_csrf_token']) || empty($_SESSION['ajax_csrf_time'])) {
            return false;
        }
        if ((time() - $_SESSION['ajax_csrf_time']) > 1800) {
            unset($_SESSION['ajax_csrf_token'], $_SESSION['ajax_csrf_time']);
            return false;
        }
        return hash_equals($_SESSION['ajax_csrf_token'], $token);
    }

    /**
     * Validasi CSRF token dari POST.
     * Token kedaluwarsa setelah 30 menit.
     */
    private function validateCsrfToken(string $token): bool
    {
        if (empty($_SESSION['csrf_token']) || empty($_SESSION['csrf_token_time'])) {
            return false;
        }

        // Token kadaluarsa setelah 30 menit
        if ((time() - $_SESSION['csrf_token_time']) > 1800) {
            unset($_SESSION['csrf_token'], $_SESSION['csrf_token_time']);
            return false;
        }

        // Perbandingan aman (timing-safe)
        $valid = hash_equals($_SESSION['csrf_token'], $token);

        // Hapus token setelah divalidasi (one-time-use)
        unset($_SESSION['csrf_token'], $_SESSION['csrf_token_time']);

        return $valid;
    }

    /* ─────────────────────────────────────────────────────────
       RATE LIMITING (Brute-force Protection)
    ───────────────────────────────────────────────────────── */

    /**
     * Catat percobaan login gagal.
     */
    private function recordFailedAttempt(): void
    {
        if (!isset($_SESSION['login_attempts'])) {
            $_SESSION['login_attempts'] = 0;
        }
        if (!isset($_SESSION['first_attempt_time'])) {
            $_SESSION['first_attempt_time'] = time();
        }

        $_SESSION['login_attempts']++;
    }

    /**
     * Cek apakah akun sedang dikunci.
     */
    private function isLockedOut(): bool
    {
        if (!isset($_SESSION['login_attempts'], $_SESSION['first_attempt_time'])) {
            return false;
        }

        // Reset jika sudah lewat masa lockout
        if ((time() - $_SESSION['first_attempt_time']) > self::LOCKOUT_TIME) {
            unset($_SESSION['login_attempts'], $_SESSION['first_attempt_time']);
            return false;
        }

        return $_SESSION['login_attempts'] >= self::MAX_ATTEMPTS;
    }

    /**
     * Sisa waktu lockout dalam detik.
     */
    public function getLockoutRemaining(): int
    {
        if (!isset($_SESSION['first_attempt_time'])) {
            return 0;
        }

        $elapsed   = time() - $_SESSION['first_attempt_time'];
        $remaining = self::LOCKOUT_TIME - $elapsed;
        return max(0, $remaining);
    }

    /**
     * Reset counter percobaan gagal.
     */
    private function resetAttempts(): void
    {
        unset($_SESSION['login_attempts'], $_SESSION['first_attempt_time']);
    }

    /* ─────────────────────────────────────────────────────────
       LOGIN
    ───────────────────────────────────────────────────────── */

    /**
     * Proses login admin.
     *
     * @return array{success: bool, message: string}
     */
    public function login(string $identifier, string $password, string $csrfToken): array
    {
        // 1. Validasi CSRF
        if (!$this->validateCsrfToken($csrfToken)) {
            return ['success' => false, 'message' => 'Permintaan tidak valid. Muat ulang halaman dan coba lagi.'];
        }

        // 2. Cek lockout
        if ($this->isLockedOut()) {
            $remaining = $this->getLockoutRemaining();
            $minutes   = (int) ceil($remaining / 60);
            return [
                'success' => false,
                'message' => "Terlalu banyak percobaan. Coba lagi dalam {$minutes} menit.",
            ];
        }

        // 3. Sanitasi input
        $identifier = trim($identifier);
        $password   = trim($password);

        if ($identifier === '' || $password === '') {
            return ['success' => false, 'message' => 'Username/email dan password tidak boleh kosong.'];
        }

        // 4. Query dengan prepared statement (mencegah SQL Injection)
        //    Cari berdasarkan username ATAU email
        $stmt = $this->conn->prepare(
            'SELECT id, name, username, email, password FROM `User`
             WHERE username = ? OR email = ?
             LIMIT 1'
        );
        $stmt->bind_param('ss', $identifier, $identifier);
        $stmt->execute();
        $result = $stmt->get_result();
        $user   = $result->fetch_assoc();
        $stmt->close();

        // 5. Verifikasi password
        //    Hash dari Node.js bcryptjs menggunakan prefix $2b, PHP password_verify
        //    memerlukan $2y. Kita ganti prefix secara aman sebelum verify.
        if ($user === null) {
            $this->recordFailedAttempt();
            // Pesan generik agar tidak membocorkan informasi (username vs password salah)
            return ['success' => false, 'message' => 'Username atau password salah.'];
        }

        $hash = $user['password'];
        // Kompatibilitas bcrypt Node ($2b) → PHP ($2y)
        if (str_starts_with($hash, '$2b$')) {
            $hash = '$2y$' . substr($hash, 4);
        }

        if (!password_verify($password, $hash)) {
            $this->recordFailedAttempt();
            return ['success' => false, 'message' => 'Username atau password salah.'];
        }

        // 6. Login berhasil  |  buat session baru (mencegah session fixation)
        $this->resetAttempts();
        session_regenerate_id(true);

        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_id']        = (int) $user['id'];
        $_SESSION['admin_name']      = $user['name'];
        $_SESSION['admin_username']  = $user['username'];
        $_SESSION['admin_email']     = $user['email'];
        $_SESSION['login_time']      = time();

        return ['success' => true, 'message' => 'Login berhasil.'];
    }

    /* ─────────────────────────────────────────────────────────
       LOGOUT
    ───────────────────────────────────────────────────────── */

    /**
     * Hancurkan session dan redirect ke halaman login.
     */
    public function logout(): void
    {
        // Hapus semua variabel session
        $_SESSION = [];

        // Hapus cookie session
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();

        header('Location: /login');
        exit();
    }

    /* ─────────────────────────────────────────────────────────
       MIDDLEWARE / GUARD
    ───────────────────────────────────────────────────────── */

    /**
     * Pastikan user sudah login sebagai admin.
     * Panggil di awal setiap halaman admin yang terproteksi.
     */
    public function requireLogin(): void
    {
        if (empty($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
            header('Location: /login');
            exit();
        }
    }

    /**
     * Cek apakah user sedang login.
     */
    public function isLoggedIn(): bool
    {
        return !empty($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
    }

    /**
     * Ambil data user yang sedang login.
     */
    public function getCurrentUser(): array
    {
        return [
            'id'       => $_SESSION['admin_id']       ?? null,
            'name'     => $_SESSION['admin_name']      ?? '',
            'username' => $_SESSION['admin_username']  ?? '',
            'email'    => $_SESSION['admin_email']     ?? '',
        ];
    }

    /* ─────────────────────────────────────────────────────────
       HELPER: ESCAPE OUTPUT (XSS Protection)
    ───────────────────────────────────────────────────────── */

    /**
     * Escape string untuk output HTML yang aman.
     */
    public static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
