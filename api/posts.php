<?php
declare(strict_types=1);

/**
 * GET /api/posts
 *
 * Endpoint publik untuk mengambil daftar post terbaru dari blog.flavory.id.
 * Digunakan oleh flavory.id untuk menampilkan preview artikel di section blog.
 *
 * Query params (opsional):
 *   ?limit=3   — jumlah post yang dikembalikan (default: 3, max: 12)
 *
 * Response JSON:
 * [
 *   {
 *     "title"        : "Judul Artikel",
 *     "slug"         : "judul-artikel",
 *     "excerpt"      : "Ringkasan singkat artikel...",
 *     "cover_image"  : "https://blog.flavory.id/assets/upload/...",
 *     "category"     : "Bisnis",
 *     "published_at" : "25 Jun 2026",
 *     "url"          : "https://blog.flavory.id/post/judul-artikel"
 *   },
 *   ...
 * ]
 */

require_once __DIR__ . '/../config.php';

// ── CORS: izinkan request dari flavory.id dan subdomain-nya ──────────────────
$allowedOrigins = [
    'https://flavory.id',
    'https://www.flavory.id',
    rtrim(getenv('FLAVORYID_URL') ?: '', '/'),
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, array_filter($allowedOrigins), true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
} else {
    // Fallback: izinkan semua di local / dev (lebih mudah debugging)
    $appEnv = getenv('APP_ENV') ?: 'production';
    if ($appEnv === 'local' || $appEnv === 'development') {
        header('Access-Control-Allow-Origin: *');
    }
}
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

// Hanya izinkan GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// ── Resolve APP_URL (sumber kebenaran URL blog) ───────────────────────────────
$appUrl = rtrim(getenv('APP_URL') ?: 'https://blog.flavory.id', '/');

// ── Parameter limit (1–12) ────────────────────────────────────────────────────
$limit = max(1, min(12, (int) ($_GET['limit'] ?? 3)));

// ── Query: ambil N post terbaru beserta kategori pertamanya ──────────────────
try {
    $stmt = $conn->prepare(
        "SELECT
             p.id,
             p.title,
             p.slug,
             p.excerpt,
             p.image,
             p.createdAt,
             (
                 SELECT k.nama
                 FROM   Kategori k
                 INNER  JOIN _KategoriToPost kp ON kp.A = k.id
                 WHERE  kp.B = p.id
                 ORDER  BY k.id ASC
                 LIMIT  1
             ) AS category
         FROM   Post p
         ORDER  BY p.createdAt DESC
         LIMIT  ?"
    );
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
    exit();
}

// ── Format response ───────────────────────────────────────────────────────────
$posts = [];
foreach ($rows as $row) {
    // cover_image: jika sudah berupa URL penuh biarkan, jika path lokal tambah base URL
    $image = (string) ($row['image'] ?? '');
    if ($image !== '' && !str_starts_with($image, 'http')) {
        $image = $appUrl . '/' . ltrim($image, '/');
    }

    // excerpt: fallback ke kosong agar flavory.id bisa handle
    $excerpt = (string) ($row['excerpt'] ?? '');

    // Tanggal: format "25 Jun 2026" (bahasa Indonesia)
    $months = [
        1  => 'Jan', 2  => 'Feb', 3  => 'Mar', 4  => 'Apr',
        5  => 'Mei', 6  => 'Jun', 7  => 'Jul', 8  => 'Agu',
        9  => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des',
    ];
    $ts          = strtotime((string) $row['createdAt']);
    $publishedAt = date('d', $ts) . ' ' . $months[(int) date('n', $ts)] . ' ' . date('Y', $ts);

    $posts[] = [
        'title'        => (string) $row['title'],
        'slug'         => (string) $row['slug'],
        'excerpt'      => $excerpt,
        'cover_image'  => $image,
        'category'     => (string) ($row['category'] ?? 'Blog'),
        'published_at' => $publishedAt,
        'url'          => $appUrl . '/post/' . rawurlencode((string) $row['slug']),
    ];
}

echo json_encode($posts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
