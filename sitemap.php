<?php
declare(strict_types=1);

/**
 * sitemap.php - XML Sitemap dinamis
 *
 * Diakses via: GET /sitemap.xml
 * .htaccess me-rewrite /sitemap.xml → sitemap.php
 *
 * Berisi:
 *  - Halaman statis (beranda)
 *  - Semua halaman kategori (URL slug-based: /kategori/{slug})
 *  - Semua artikel yang dipublikasikan + image sitemap
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/Logic/User/Blog.php';

// ── Resolve APP_URL ───────────────────────────────────────────────────────
$appUrl = rtrim(getenv('APP_URL') ?: 'https://blog.libix.tech', '/');

// ── Helper: slug kategori (konsisten dengan BlogUserLogic) ────────────────
function makeKatSlug(string $nama): string
{
    if (class_exists('BlogUserLogic')) {
        return BlogUserLogic::generateKategoriSlug($nama);
    }
    $slug = mb_strtolower(trim($nama), 'UTF-8');
    $slug = preg_replace('/[\s\-]+/', '-', $slug);
    $slug = preg_replace('/[^a-z0-9\-]/', '', $slug);
    return trim($slug, '-');
}

// ── Ambil semua kategori yang punya artikel ───────────────────────────────
$categories = [];
try {
    $stmt = $conn->prepare(
        "SELECT k.id, k.nama, MAX(p.updatedAt) AS lastUpdated
         FROM   Kategori k
         INNER  JOIN _KategoriToPost kp ON kp.A = k.id
         INNER  JOIN Post p             ON p.id  = kp.B
         GROUP  BY k.id, k.nama
         HAVING COUNT(kp.B) > 0
         ORDER  BY lastUpdated DESC"
    );
    $stmt->execute();
    $categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Throwable $e) {
    error_log('Sitemap categories DB error: ' . $e->getMessage());
}

// ── Ambil semua artikel yang dipublikasikan ───────────────────────────────
$posts = [];
try {
    $stmt = $conn->prepare(
        "SELECT p.slug, p.title, p.image, p.updatedAt, p.createdAt
         FROM   Post p
         ORDER  BY p.createdAt DESC"
    );
    $stmt->execute();
    $posts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Throwable $e) {
    error_log('Sitemap posts DB error: ' . $e->getMessage());
}

// ── Helper: format tanggal W3C ────────────────────────────────────────────
function w3cDate(?string $dateStr): string
{
    if (empty($dateStr)) return date('Y-m-d');
    $ts = strtotime($dateStr);
    return $ts !== false ? date('Y-m-d', $ts) : date('Y-m-d');
}

// ── Output XML ────────────────────────────────────────────────────────────
header('Content-Type: application/xml; charset=UTF-8');
header('X-Robots-Tag: noindex'); // sitemap itu sendiri tidak perlu diindeks
header('Cache-Control: public, max-age=3600'); // cache 1 jam di CDN/proxy

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
echo '        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";

// ── 1. Beranda ────────────────────────────────────────────────────────────
echo '  <url>' . "\n";
echo '    <loc>' . htmlspecialchars($appUrl . '/') . '</loc>' . "\n";
echo '    <lastmod>' . date('Y-m-d') . '</lastmod>' . "\n";
echo '    <changefreq>daily</changefreq>' . "\n";
echo '    <priority>1.0</priority>' . "\n";
echo '  </url>' . "\n";

// ── 2. Halaman tiap kategori (/kategori/{slug}) ───────────────────────────
foreach ($categories as $cat) {
    $slug = makeKatSlug($cat['nama']);
    if ($slug === '') continue;

    $catUrl  = $appUrl . '/kategori/' . rawurlencode($slug);
    $lastmod = w3cDate($cat['lastUpdated']);

    echo '  <url>' . "\n";
    echo '    <loc>' . htmlspecialchars($catUrl) . '</loc>' . "\n";
    echo '    <lastmod>' . htmlspecialchars($lastmod) . '</lastmod>' . "\n";
    echo '    <changefreq>weekly</changefreq>' . "\n";
    echo '    <priority>0.7</priority>' . "\n";
    echo '  </url>' . "\n";
}

// ── 3. Semua artikel + image sitemap ─────────────────────────────────────
foreach ($posts as $post) {
    // Sanitasi slug
    $slug = preg_replace('/[^a-zA-Z0-9_\-]/', '', $post['slug']);
    if ($slug === '') continue;

    $postUrl = $appUrl . '/post/' . rawurlencode($slug);
    $lastmod = w3cDate($post['updatedAt'] ?? $post['createdAt']);

    // Resolve URL gambar
    $imgUrl = '';
    if (!empty($post['image'])) {
        $imgRaw = trim($post['image']);
        $imgUrl = str_starts_with($imgRaw, 'http')
            ? $imgRaw
            : $appUrl . '/' . ltrim($imgRaw, '/');
    }

    echo '  <url>' . "\n";
    echo '    <loc>' . htmlspecialchars($postUrl) . '</loc>' . "\n";
    echo '    <lastmod>' . htmlspecialchars($lastmod) . '</lastmod>' . "\n";
    echo '    <changefreq>monthly</changefreq>' . "\n";
    echo '    <priority>0.8</priority>' . "\n";

    // Sisipkan image:image jika artikel punya thumbnail
    if ($imgUrl !== '') {
        $imgTitle = htmlspecialchars($post['title'] ?? $slug);
        echo '    <image:image>' . "\n";
        echo '      <image:loc>' . htmlspecialchars($imgUrl) . '</image:loc>' . "\n";
        echo '      <image:title>' . $imgTitle . '</image:title>' . "\n";
        echo '      <image:caption>' . $imgTitle . ' - blog.libix.tech</image:caption>' . "\n";
        echo '    </image:image>' . "\n";
    }

    echo '  </url>' . "\n";
}

echo '</urlset>' . "\n";
