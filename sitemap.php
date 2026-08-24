<?php
declare(strict_types=1);

/**
 * sitemap.php — XML Sitemap dinamis
 *
 * Diakses via: GET /sitemap.xml
 * .htaccess me-rewrite /sitemap.xml → sitemap.php
 *
 * Berisi:
 *  - Halaman statis (beranda, halaman kategori)
 *  - Semua artikel yang dipublikasikan (urut terbaru)
 */

require_once __DIR__ . '/config.php';

// ── Resolve APP_URL ───────────────────────────────────────────────────────
$appUrl = rtrim(getenv('APP_URL') ?: 'https://blog.flavory.id', '/');

// ── Ambil semua post dari DB ──────────────────────────────────────────────
$posts = [];
try {
    $stmt = $conn->prepare(
        "SELECT slug, updatedAt, createdAt
         FROM   Post
         ORDER  BY createdAt DESC"
    );
    $stmt->execute();
    $posts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Throwable $e) {
    error_log('Sitemap DB error: ' . $e->getMessage());
}

// ── Ambil semua kategori ──────────────────────────────────────────────────
$categories = [];
try {
    $stmt = $conn->prepare(
        "SELECT k.id
         FROM   Kategori k
         INNER  JOIN _KategoriToPost kp ON kp.A = k.id
         GROUP  BY k.id
         HAVING COUNT(kp.B) > 0"
    );
    $stmt->execute();
    $categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Throwable $e) {
    error_log('Sitemap categories DB error: ' . $e->getMessage());
}

// ── Helper: format tanggal W3C (untuk lastmod) ────────────────────────────
function w3cDate(?string $dateStr): string
{
    if (empty($dateStr)) return date('Y-m-d');
    $ts = strtotime($dateStr);
    return $ts !== false ? date('Y-m-d', $ts) : date('Y-m-d');
}

// ── Output XML ────────────────────────────────────────────────────────────
header('Content-Type: application/xml; charset=UTF-8');
header('X-Robots-Tag: noindex'); // sitemap itu sendiri tidak perlu diindeks

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";

// ── 1. Beranda ────────────────────────────────────────────────────────────
echo '  <url>' . "\n";
echo '    <loc>' . htmlspecialchars($appUrl . '/') . '</loc>' . "\n";
echo '    <lastmod>' . date('Y-m-d') . '</lastmod>' . "\n";
echo '    <changefreq>daily</changefreq>' . "\n";
echo '    <priority>1.0</priority>' . "\n";
echo '  </url>' . "\n";

// ── 2. Halaman tiap kategori ──────────────────────────────────────────────
foreach ($categories as $cat) {
    $catUrl = $appUrl . '/?' . http_build_query(['kategori' => (int) $cat['id']]);
    echo '  <url>' . "\n";
    echo '    <loc>' . htmlspecialchars($catUrl) . '</loc>' . "\n";
    echo '    <changefreq>weekly</changefreq>' . "\n";
    echo '    <priority>0.7</priority>' . "\n";
    echo '  </url>' . "\n";
}

// ── 3. Semua artikel ──────────────────────────────────────────────────────
foreach ($posts as $post) {
    // Sanitasi slug: hanya karakter aman yang diizinkan
    $slug = preg_replace('/[^a-zA-Z0-9_\-]/', '', $post['slug']);
    if ($slug === '') continue;

    $postUrl = $appUrl . '/post/' . rawurlencode($slug);
    $lastmod = w3cDate($post['updatedAt'] ?? $post['createdAt']);

    echo '  <url>' . "\n";
    echo '    <loc>' . htmlspecialchars($postUrl) . '</loc>' . "\n";
    echo '    <lastmod>' . htmlspecialchars($lastmod) . '</lastmod>' . "\n";
    echo '    <changefreq>monthly</changefreq>' . "\n";
    echo '    <priority>0.8</priority>' . "\n";
    echo '  </url>' . "\n";
}

echo '</urlset>' . "\n";
