<?php
declare(strict_types=1);

/**
 * router.php - Router untuk PHP Built-in Development Server
 *
 * Mengemulasi perilaku .htaccess (RewriteRule + FilesMatch) untuk dev lokal.
 * PENTING: security check berjalan PERTAMA, sebelum apapun, termasuk sebelum
 *          serve file statis - ini mencegah kebocoran .env, config.php, dll.
 *
 * Jalankan dengan:
 *   php -S localhost:8000 router.php
 */

// ════════════════════════════════════════════════════════════════════════════
//  KONSTANTA & HELPERS
// ════════════════════════════════════════════════════════════════════════════

define('BASE_DIR', __DIR__);

/** Kirim halaman error dinamis dan hentikan eksekusi */
function sendError(int $code, string $title = '', string $message = ''): never
{
    $errorCode    = $code;
    $errorTitle   = $title;
    $errorMessage = $message;

    // Coba load config agar DB tersedia untuk saran kategori di halaman 404
    // (dibungkus try/catch - boleh gagal tanpa mematikan proses)
    if (!isset($conn)) {
        try {
            @require_once BASE_DIR . '/config.php';
            @require_once BASE_DIR . '/Logic/User/Blog.php';
        } catch (Throwable $e) { /* diabaikan */ }
    }

    require BASE_DIR . '/error.php';
    exit();
}

/**
 * Apakah URI ini mengarah ke file/direktori yang DILARANG diakses publik?
 *
 * Aturan (diurutkan dari yang paling krusial):
 *  1. Dotfiles (.env, .htaccess, .gitignore, .git/*, dll.)
 *  2. Ekstensi file sensitif (.env, .sql, .log, .key, .pem, dll.)
 *  3. File JS/JSON di luar /assets/ (loading.js, package.json, dll.)
 *  4. Direktori internal (Logic/, layouts/, partials/, Admin/partials/)
 *  5. File PHP inti yang tidak boleh diakses langsung
 *  6. Direktori tersembunyi di segmen manapun (mis. /foo/.git/config)
 */
function isBlocked(string $uri): bool
{
    // Normalisasi: hilangkan query string, decode %2F dll., lowercase untuk perbandingan
    $path    = strtolower(ltrim(urldecode(parse_url($uri, PHP_URL_PATH) ?? $uri), '/'));

    // ── 1. Dotfile di segmen manapun ──────────────────────────────────────
    // Contoh: /.env  /sub/.env  /.git/config  /dir/.hidden
    if (preg_match('/(^|\/)\./', $path)) {
        return true;
    }

    // ── 2. Ekstensi file sensitif ─────────────────────────────────────────
    $ext = pathinfo($path, PATHINFO_EXTENSION);
    $blockedExts = [
        'env','sql','log','bak','backup','sh','bash',
        'conf','ini','lock','pem','key','cert','crt','p12','pfx',
        'md','txt','xml','yaml','yml',  // kecuali robots.txt & sitemap.xml (ditangani sebelum ini)
    ];
    if ($ext !== '' && in_array($ext, $blockedExts, true)) {
        return true;
    }

    // ── 3. File JS/JSON di luar /assets/ ──────────────────────────────────
    $jsExts = ['js', 'json', 'ts', 'jsx', 'tsx', 'vue'];
    if (in_array($ext, $jsExts, true) && !str_starts_with($path, 'assets/')) {
        return true;
    }

    // ── 4. Direktori internal ─────────────────────────────────────────────
    $blockedDirs = [
        'logic/',
        'layouts/',
        'partials/',
        'admin/partials/',
        '.git/',
    ];
    foreach ($blockedDirs as $dir) {
        if (str_starts_with($path, $dir)) {
            return true;
        }
    }

    // ── 5. File PHP inti yang tidak boleh diakses langsung ────────────────
    $blockedFiles = [
        'config.php',
        'router.php',
        'sitemap.php',   // hanya boleh via /sitemap.xml
        'submit-review.php', // diakses via AJAX POST, bukan langsung GET
    ];
    if (in_array($path, $blockedFiles, true)) {
        return true;
    }

    return false;
}

// ════════════════════════════════════════════════════════════════════════════
//  PARSE URI - dilakukan sekali di awal
// ════════════════════════════════════════════════════════════════════════════

$rawUri  = $_SERVER['REQUEST_URI'] ?? '/';
$uri     = parse_url($rawUri, PHP_URL_PATH) ?? '/';
$uri     = '/' . ltrim(urldecode($uri), '/');

// Normalisasi trailing slash (kecuali root)
if ($uri !== '/' && str_ends_with($uri, '/')) {
    header('Location: ' . rtrim($uri, '/'), true, 301);
    exit();
}

// ════════════════════════════════════════════════════════════════════════════
//  STEP 1 - SECURITY CHECK (SELALU PERTAMA, TANPA PENGECUALIAN)
//  Tidak ada file_exists() sebelum ini.
// ════════════════════════════════════════════════════════════════════════════

// Whitelist eksplisit: path ini SELALU aman dilewatkan tanpa cek ekstensi
$whitelist = [
    '/robots.txt',
    '/sitemap.xml',
    '/site.webmanifest',
    '/browserconfig.xml',
    '/favicon.ico',
];

if (!in_array($uri, $whitelist, true) && isBlocked($uri)) {
    sendError(403, 'Akses Ditolak', 'Kamu tidak punya izin untuk mengakses halaman atau file ini.');
}

// ════════════════════════════════════════════════════════════════════════════
//  STEP 2 - ROUTE KHUSUS (sebelum serve file statis)
// ════════════════════════════════════════════════════════════════════════════

// ── /robots.txt ───────────────────────────────────────────────────────────
if ($uri === '/robots.txt') {
    $f = BASE_DIR . '/robots.txt';
    if (file_exists($f)) {
        header('Content-Type: text/plain; charset=UTF-8');
        readfile($f);
    } else {
        sendError(404);
    }
    exit();
}

// ── /sitemap.xml ──────────────────────────────────────────────────────────
if ($uri === '/sitemap.xml') {
    require BASE_DIR . '/sitemap.php';
    exit();
}

// ── /site.webmanifest ─────────────────────────────────────────────────────
if ($uri === '/site.webmanifest') {
    $f = BASE_DIR . '/site.webmanifest';
    if (file_exists($f)) {
        header('Content-Type: application/manifest+json; charset=UTF-8');
        readfile($f);
    } else {
        sendError(404);
    }
    exit();
}

// ── /browserconfig.xml ────────────────────────────────────────────────────
if ($uri === '/browserconfig.xml') {
    $f = BASE_DIR . '/browserconfig.xml';
    if (file_exists($f)) {
        header('Content-Type: application/xml; charset=UTF-8');
        readfile($f);
    } else {
        sendError(404);
    }
    exit();
}

// ── /post/{slug} → blog.php ───────────────────────────────────────────────
if (preg_match('#^/post/([a-zA-Z0-9_-]+)$#', $uri, $m)) {
    $_GET['slug'] = $m[1];
    require BASE_DIR . '/blog.php';
    exit();
}

// ── /kategori/{slug} → index.php ──────────────────────────────────────────
if (preg_match('#^/kategori/([a-zA-Z0-9_-]+)$#', $uri, $m)) {
    $_GET['kat'] = $m[1];
    require BASE_DIR . '/index.php';
    exit();
}

// ── /api/{endpoint} → api/{endpoint}.php ──────────────────────────────────
if (preg_match('#^/api/([a-zA-Z0-9_\-]+)$#', $uri, $m)) {
    $apiFile = BASE_DIR . '/api/' . $m[1] . '.php';
    if (file_exists($apiFile)) {
        require $apiFile;
    } else {
        http_response_code(404);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['success' => false, 'error' => 'Endpoint tidak ditemukan.']);
    }
    exit();
}

// ── /Admin/{halaman} → Admin/{halaman}.php ────────────────────────────────
if (preg_match('#^/Admin/([a-zA-Z0-9_\-]+)$#', $uri, $m)) {
    $adminFile = BASE_DIR . '/Admin/' . $m[1] . '.php';
    if (file_exists($adminFile)) {
        require $adminFile;
    } else {
        sendError(404);
    }
    exit();
}

// ── / → index.php ─────────────────────────────────────────────────────────
if ($uri === '/') {
    require BASE_DIR . '/index.php';
    exit();
}

// ════════════════════════════════════════════════════════════════════════════
//  STEP 3 - SERVE FILE STATIS YANG DIIZINKAN
//  (hanya setelah security check lulus di STEP 1)
// ════════════════════════════════════════════════════════════════════════════

$physicalPath = BASE_DIR . $uri;

if (file_exists($physicalPath) && !is_dir($physicalPath)) {
    // File ada di disk dan sudah lolos security check → biarkan PHP built-in server tangani
    return false;
}

// ════════════════════════════════════════════════════════════════════════════
//  STEP 4 - URL TANPA EKSTENSI → coba {uri}.php
// ════════════════════════════════════════════════════════════════════════════

if (preg_match('#^/([a-zA-Z0-9_-]+)$#', $uri, $m)) {
    $phpFile = BASE_DIR . '/' . $m[1] . '.php';
    if (file_exists($phpFile) && !isBlocked('/' . $m[1] . '.php')) {
        require $phpFile;
        exit();
    }
}

// ════════════════════════════════════════════════════════════════════════════
//  FALLBACK - 404
// ════════════════════════════════════════════════════════════════════════════
sendError(404);
