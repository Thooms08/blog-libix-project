<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../Logic/Auth.php';

$auth = new AuthLogic($conn);

// Pastikan sudah login sebelum bisa logout
$auth->requireLogin();

// Hanya proses POST untuk mencegah logout via GET (CSRF via URL)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /Admin/dashboard');
    exit();
}

// Validasi CSRF token sebelum logout
$csrfToken = $_POST['csrf_token'] ?? '';

if (empty($csrfToken) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrfToken)) {
    // Token tidak valid  |  kembalikan ke dashboard dengan pesan error
    // Tetap logout untuk keamanan jika ada indikasi serangan
    $auth->logout();
    exit();
}

// Logout  |  hancurkan session & redirect ke login
$auth->logout();
