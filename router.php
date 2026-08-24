<?php
declare(strict_types=1);

/**
 * Router untuk PHP Built-in Development Server.
 * Mengemulasi perilaku RewriteRule + blokir file sensitif dari .htaccess.
 *
 * Jalankan dengan:
 *   php -S localhost:8888 router.php
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Hilangkan trailing slash kecuali root
if ($uri !== '/' && str_ends_with($uri, '/')) {
    $uri = rtrim($uri, '/');
}

/* ════════════════════════════════════════════════════════════════════════════
   BLOKIR FILE SENSITIF — sama persis dengan logika .htaccess
   Harus dicek SEBELUM rule "sajikan file yang ada".
════════════════════════════════════════════════════════════════════════════ */

/**
 * Daftar pola URI yang harus selalu diblokir.
 * Mengemulasi FilesMatch dan RewriteRule [F] di .htaccess.
 */
function isSensitivePath(string $uri): bool
{
    $path = ltrim($uri, '/');

    // Dotfile: .env, .htaccess, .gitignore, dll.
    if (str_starts_with($path, '.') || str_contains($path, '/.')) {
        return true;
    }

    // Ekstensi file sensitif
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $blockedExts = ['env','sql','log','bak','backup','sh','bash','conf','ini','lock','pem','key','cert','crt','p12','pfx'];
    if (in_array($ext, $blockedExts, true)) {
        return true;
    }

    // File JS / JSON di luar /assets/ (loading.js, package.json, dll.)
    $jsExts = ['js','json','ts','jsx','tsx','vue'];
    if (in_array($ext, $jsExts, true) && !str_starts_with($path, 'assets/')) {
        return true;
    }

    // Folder Logic, layouts, partials, Admin/partials — tidak boleh diakses langsung
    // Folder api/ TIDAK diblokir — dibutuhkan untuk endpoint publik
    $blockedDirs = ['logic/', 'layouts/', 'partials/', 'admin/partials/'];
    foreach ($blockedDirs as $dir) {
        if (str_starts_with(strtolower($path), $dir)) {
            return true;
        }
    }

    // config.php dan router.php tidak boleh diakses lewat browser
    if (in_array(strtolower($path), ['config.php', 'router.php'], true)) {
        return true;
    }

    return false;
}

// Jalankan pengecekan sebelum apapun
if (isSensitivePath($uri)) {
    http_response_code(403);
    echo '<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><title>403</title></head>
<body style="font-family:sans-serif;background:#0a0a0f;color:#e2e8f0;display:flex;
             align-items:center;justify-content:center;min-height:100vh;margin:0">
  <div style="text-align:center">
    <p style="font-size:4rem;font-weight:800;color:#ef4444;margin:0">403</p>
    <h1 style="margin:.5rem 0 1rem">Akses Ditolak</h1>
    <a href="/" style="color:#f97316">← Kembali ke Beranda</a>
  </div>
</body></html>';
    return true;
}

/* ════════════════════════════════════════════════════════════════════════════
   ROUTING NORMAL
════════════════════════════════════════════════════════════════════════════ */

// ── 1. Sajikan file statis yang BOLEH diakses (gambar di /assets, css, dll.) ─
if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    return false; // built-in server menangani langsung
}

// ── 2. Route: /post/{slug}  →  blog.php ──────────────────────────────────────
if (preg_match('#^/post/([a-zA-Z0-9_-]+)$#', $uri, $m)) {
    $_GET['slug'] = $m[1];
    require __DIR__ . '/blog.php';
    return true;
}

// ── 2b. Route: /kategori/{slug}  →  index.php ────────────────────────────────
if (preg_match('#^/kategori/([a-zA-Z0-9_-]+)$#', $uri, $m)) {
    $_GET['kat'] = $m[1];
    require __DIR__ . '/index.php';
    return true;
}

// ── 3. Route: /sitemap.xml  →  sitemap.php ───────────────────────────────────
if ($uri === '/sitemap.xml') {
    require __DIR__ . '/sitemap.php';
    return true;
}

// ── 4. Route: /robots.txt  →  robots.txt (file statis) ──────────────────────
if ($uri === '/robots.txt') {
    header('Content-Type: text/plain; charset=UTF-8');
    readfile(__DIR__ . '/robots.txt');
    return true;
}

// ── 5. Route: /Admin/{halaman}  →  Admin/{halaman}.php ───────────────────────
if (preg_match('#^/Admin/([a-zA-Z0-9_\-]+)$#', $uri, $m)) {
    $file = __DIR__ . '/Admin/' . $m[1] . '.php';
    if (file_exists($file)) {
        require $file;
        return true;
    }
}

// ── 6. Route: halaman root .php tanpa ekstensi  →  {halaman}.php ─────────────
if (preg_match('#^/([a-zA-Z0-9_-]+)$#', $uri, $m)) {
    $file = __DIR__ . '/' . $m[1] . '.php';
    if (file_exists($file)) {
        require $file;
        return true;
    }
}

// ── 6b. Route: /api/{endpoint} → api/{endpoint}.php ─────────────────────────
if (preg_match('#^/api/([a-zA-Z0-9_\-]+)$#', $uri, $m)) {
    $file = __DIR__ . '/api/' . $m[1] . '.php';
    if (file_exists($file)) {
        require $file;
        return true;
    }
    // File tidak ditemukan → 404 JSON
    http_response_code(404);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['error' => 'API endpoint not found']);
    return true;
}

// ── 7. Root → index.php ──────────────────────────────────────────────────────
if ($uri === '/') {
    require __DIR__ . '/index.php';
    return true;
}

// ── 8. Fallback 404 ───────────────────────────────────────────────────────────
http_response_code(404);
echo '<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><title>404</title></head>
<body style="font-family:sans-serif;background:#0a0a0f;color:#e2e8f0;display:flex;
             align-items:center;justify-content:center;min-height:100vh;margin:0">
  <div style="text-align:center">
    <p style="font-size:4rem;font-weight:800;color:#f97316;margin:0">404</p>
    <h1 style="margin:.5rem 0 1rem">Halaman Tidak Ditemukan</h1>
    <a href="/" style="color:#f97316">← Kembali ke Beranda</a>
  </div>
</body></html>';
return true;
