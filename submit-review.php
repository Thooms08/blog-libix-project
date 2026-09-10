<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

// ── 1. Hanya terima POST ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method tidak diizinkan.']);
    exit();
}

// ── 2. Validasi Origin / Referer (mencegah CSRF dari domain lain) ────────────
//    Endpoint ini sengaja tidak pakai CSRF token berbasis session karena
//    dipanggil oleh pengunjung publik yang tidak punya session admin.
//    Sebagai gantinya kita validasi Origin header agar request hanya bisa
//    datang dari domain kita sendiri (double-submit mitigation).
$allowedHost = parse_url(getenv('APP_URL') ?: 'http://localhost', PHP_URL_HOST);
$origin      = $_SERVER['HTTP_ORIGIN'] ?? '';
$referer     = $_SERVER['HTTP_REFERER'] ?? '';

$originHost  = $origin  ? parse_url($origin,  PHP_URL_HOST) : null;
$refererHost = $referer ? parse_url($referer, PHP_URL_HOST) : null;

// Tolak jika Origin ada tapi tidak cocok (browser selalu kirim Origin untuk cross-origin XHR)
if ($origin !== '' && $originHost !== $allowedHost) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Permintaan tidak diizinkan.']);
    exit();
}

// Tolak jika tidak ada Origin dan tidak ada Referer (kemungkinan non-browser tool)
// - beri sedikit toleransi untuk curl/Postman saat development
// CATATAN: comment-out baris di bawah ini saat production jika diperlukan
// if ($origin === '' && $refererHost !== $allowedHost) {
//     http_response_code(403);
//     echo json_encode(['success' => false, 'message' => 'Referer tidak valid.']);
//     exit();
// }

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/Logic/User/Ulasan.php';

$ulasanLogic = new UlasanUserLogic($conn);

// ── 3. Ambil & sanitasi input ─────────────────────────────────────────────────
$postId    = isset($_POST['post_id']) ? (int) $_POST['post_id']          : 0;
$rating    = isset($_POST['rating'])  ? (int) $_POST['rating']           : 0;
$comment   = isset($_POST['comment']) ? trim((string) $_POST['comment']) : '';

// IP address: ambil yang paling reliable, strip proxy chain jika ada
$ipAddress = $_SERVER['HTTP_CF_CONNECTING_IP']   // Cloudflare
          ?? $_SERVER['HTTP_X_REAL_IP']           // Nginx proxy
          ?? (explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '')[0] ?: null)
          ?? $_SERVER['REMOTE_ADDR']
          ?? '';
$ipAddress = trim($ipAddress);

// Validasi format IP agar tidak menyimpan nilai palsu
if ($ipAddress !== '' && !filter_var($ipAddress, FILTER_VALIDATE_IP)) {
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
}

$userAgent = mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 191);

// ── 4. Validasi nilai input ───────────────────────────────────────────────────
if ($postId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Post ID tidak valid.']);
    exit();
}

if ($rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Rating harus antara 1 sampai 5.']);
    exit();
}

if (mb_strlen($comment) > 500) {
    echo json_encode(['success' => false, 'message' => 'Komentar maksimal 500 karakter.']);
    exit();
}

// Sanitasi komentar: strip tag HTML untuk mencegah stored XSS
// Konten disimpan plain-text; saat output gunakan htmlspecialchars()
$comment = strip_tags($comment);

// ── 5. Pastikan post ada di database ─────────────────────────────────────────
$chk = $conn->prepare("SELECT id FROM Post WHERE id = ? LIMIT 1");
$chk->bind_param('i', $postId);
$chk->execute();

if ($chk->get_result()->num_rows === 0) {
    $chk->close();
    echo json_encode(['success' => false, 'message' => 'Artikel tidak ditemukan.']);
    exit();
}
$chk->close();

// ── 6. Proses simpan ulasan ───────────────────────────────────────────────────
$result = $ulasanLogic->addReview($postId, $rating, $comment, $ipAddress, $userAgent);

echo json_encode($result);
