<?php
declare(strict_types=1);

/**
 * error.php - Halaman error dinamis & user-friendly
 *
 * Dipanggil oleh:
 *  - router.php  (404, 403, 503)
 *  - blog.php    (404 artikel tidak ditemukan)
 *  - .htaccess   ErrorDocument (403, 404, 500, 503) via Apache
 *
 * Variabel yang bisa diset sebelum include:
 *  $errorCode    int    - kode HTTP (default: 404)
 *  $errorTitle   string - judul singkat (opsional, ada default per kode)
 *  $errorMessage string - pesan panjang (opsional, ada default per kode)
 */

// ── Tentukan kode & pesan ──────────────────────────────────────────────────
$errorCode = isset($errorCode) ? (int) $errorCode : (int) ($_SERVER['REDIRECT_STATUS'] ?? 404);

// Default per kode
$defaults = [
    400 => [
        'title'   => 'Permintaan Tidak Valid',
        'message' => 'Server tidak dapat memproses permintaan karena format yang dikirim tidak valid.',
        'icon'    => '400',
        'color'   => 'orange',
    ],
    403 => [
        'title'   => 'Akses Ditolak',
        'message' => 'Kamu tidak punya izin untuk mengakses halaman atau file ini.',
        'icon'    => '403',
        'color'   => 'red',
    ],
    404 => [
        'title'   => 'Halaman Tidak Ditemukan',
        'message' => 'Halaman yang kamu cari tidak ada, sudah dipindahkan, atau URL-nya salah ketik.',
        'icon'    => '404',
        'color'   => 'brand',
    ],
    429 => [
        'title'   => 'Terlalu Banyak Permintaan',
        'message' => 'Kamu mengirim terlalu banyak permintaan dalam waktu singkat. Tunggu sebentar lalu coba lagi.',
        'icon'    => '429',
        'color'   => 'orange',
    ],
    500 => [
        'title'   => 'Terjadi Kesalahan Server',
        'message' => 'Ada sesuatu yang tidak beres di sisi kami. Tim teknis sudah diberitahu. Coba lagi dalam beberapa menit.',
        'icon'    => '500',
        'color'   => 'red',
    ],
    503 => [
        'title'   => 'Sedang Maintenance',
        'message' => 'Website sedang dalam proses pemeliharaan untuk pengalaman yang lebih baik. Kembali lagi sebentar ya.',
        'icon'    => '503',
        'color'   => 'orange',
    ],
];

$def          = $defaults[$errorCode] ?? $defaults[404];
$errorTitle   = isset($errorTitle)   ? (string) $errorTitle   : $def['title'];
$errorMessage = isset($errorMessage) ? (string) $errorMessage : $def['message'];
$errorIcon    = $def['icon'];
$errorColor   = $def['color'];

// Set HTTP response code
http_response_code($errorCode);

// Warna per tipe error
$colorMap = [
    'brand'  => ['bg' => '#ecfeff', 'text' => '#06b6d4', 'border' => '#cffafe'],
    'red'    => ['bg' => '#fef2f2', 'text' => '#ef4444', 'border' => '#fecaca'],
    'orange' => ['bg' => '#fff7ed', 'text' => '#f97316', 'border' => '#fed7aa'],
];
$c = $colorMap[$errorColor] ?? $colorMap['brand'];

// Cek apakah APP_URL sudah tersedia (belum tentu config.php ter-load)
if (!function_exists('getenv') || !getenv('APP_URL')) {
    $appUrl = 'https://blog.libix.tech';
} else {
    $appUrl = rtrim(getenv('APP_URL') ?: 'https://blog.libix.tech', '/');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $errorCode ?> - <?= htmlspecialchars($errorTitle) ?> | blog.libix.tech</title>
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#06b6d4">
    <link rel="icon" type="image/png" href="/assets/libix-logo.png">
    <link rel="shortcut icon" href="/assets/libix-logo.png">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">

    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
            background: #f8fafc;
            color: #1e293b;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* ── Navbar minimal ── */
        .nav {
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            padding: 0 1.5rem;
            height: 4.5rem;
            display: flex;
            align-items: center;
        }
        .nav a {
            display: flex;
            align-items: center;
            gap: .625rem;
            text-decoration: none;
        }
        .nav img { height: 2.25rem; width: auto; object-fit: contain; }
        .nav-brand {
            font-size: 1.15rem;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -.02em;
        }
        .nav-brand span { color: #06b6d4; }

        /* ── Hero error ── */
        main {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 3rem 1.5rem;
        }
        .card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 1.5rem;
            padding: 3rem 2.5rem;
            max-width: 520px;
            width: 100%;
            text-align: center;
            box-shadow: 0 4px 24px rgba(0,0,0,.06);
        }

        .code-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 5rem;
            height: 5rem;
            border-radius: 1.25rem;
            background: <?= $c['bg'] ?>;
            border: 2px solid <?= $c['border'] ?>;
            margin: 0 auto 1.5rem;
        }
        .code-badge span.icon { font-size: 1.5rem; font-weight: 800; line-height: 1; color: inherit; }

        .error-code {
            font-size: 4rem;
            font-weight: 800;
            color: <?= $c['text'] ?>;
            line-height: 1;
            margin-bottom: .75rem;
            letter-spacing: -.04em;
        }

        h1 {
            font-size: 1.35rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: .75rem;
        }

        .desc {
            font-size: .95rem;
            color: #64748b;
            line-height: 1.65;
            margin-bottom: 2rem;
        }

        .actions {
            display: flex;
            gap: .75rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn-primary {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            background: #06b6d4;
            color: #fff;
            font-weight: 700;
            font-size: .875rem;
            padding: .75rem 1.5rem;
            border-radius: .75rem;
            text-decoration: none;
            transition: background .2s, transform .15s;
        }
        .btn-primary:hover { background: #0891b2; }
        .btn-primary:active { transform: scale(.97); }

        .btn-secondary {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            background: transparent;
            color: #475569;
            font-weight: 600;
            font-size: .875rem;
            padding: .75rem 1.5rem;
            border-radius: .75rem;
            border: 1.5px solid #e2e8f0;
            text-decoration: none;
            transition: border-color .2s, color .2s;
        }
        .btn-secondary:hover { border-color: #06b6d4; color: #06b6d4; }

        /* ── Saran artikel (hanya 404) ── */
        .suggestions {
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid #f1f5f9;
        }
        .suggestions p {
            font-size: .8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: #94a3b8;
            margin-bottom: .875rem;
        }
        .sug-links {
            display: flex;
            flex-direction: column;
            gap: .5rem;
            text-align: left;
        }
        .sug-links a {
            display: flex;
            align-items: center;
            gap: .5rem;
            padding: .625rem .875rem;
            border-radius: .625rem;
            background: #f8fafc;
            color: #334155;
            font-size: .875rem;
            font-weight: 500;
            text-decoration: none;
            transition: background .15s, color .15s;
        }
        .sug-links a:hover { background: #ecfeff; color: #06b6d4; }
        .sug-links a svg { flex-shrink: 0; opacity: .5; }

        /* ── Footer minimal ── */
        footer {
            text-align: center;
            padding: 1.5rem;
            font-size: .8rem;
            color: #94a3b8;
            border-top: 1px solid #f1f5f9;
        }
        footer a { color: #06b6d4; text-decoration: none; }

        @media (max-width: 480px) {
            .card { padding: 2rem 1.25rem; border-radius: 1.25rem; }
            .error-code { font-size: 3rem; }
        }
    </style>
</head>
<body>

    <!-- Navbar minimal -->
    <nav class="nav">
        <a href="/">
            <img src="/assets/libix-logo.png" alt="Libix Technology">
            <span class="nav-brand">blog.<span>libix.tech</span></span>
        </a>
    </nav>

    <main>
        <div class="card">
            <!-- Ikon & kode -->
            <p class="error-code"><?= $errorCode ?></p>
            <h1><?= htmlspecialchars($errorTitle) ?></h1>
            <p class="desc"><?= htmlspecialchars($errorMessage) ?></p>

            <!-- Tombol aksi -->
            <div class="actions">
                <a href="/" class="btn-primary">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                    Ke Beranda
                </a>
                <a href="javascript:history.back()" class="btn-secondary">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Kembali
                </a>
            </div>

            <?php if ($errorCode === 404): ?>
            <!-- Saran halaman populer -->
            <div class="suggestions">
                <p>Atau coba halaman ini</p>
                <div class="sug-links">
                    <a href="/">
                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/>
                        </svg>
                        Semua Artikel Terbaru
                    </a>
                    <?php
                    // Coba tampilkan kategori jika DB tersedia
                    if (isset($conn)) {
                        try {
                            $sugStmt = $conn->prepare(
                                "SELECT k.nama, COUNT(kp.B) AS jml
                                 FROM Kategori k
                                 INNER JOIN _KategoriToPost kp ON kp.A = k.id
                                 GROUP BY k.id, k.nama
                                 ORDER BY jml DESC LIMIT 3"
                            );
                            $sugStmt->execute();
                            $sugKats = $sugStmt->get_result()->fetch_all(MYSQLI_ASSOC);
                            $sugStmt->close();
                            foreach ($sugKats as $sk):
                                $skSlug = class_exists('BlogUserLogic')
                                    ? BlogUserLogic::generateKategoriSlug($sk['nama'])
                                    : strtolower(preg_replace('/[^a-z0-9]+/i', '-', trim($sk['nama'])));
                    ?>
                    <a href="/kategori/<?= htmlspecialchars($skSlug) ?>">
                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                        </svg>
                        <?= htmlspecialchars($sk['nama']) ?>
                    </a>
                    <?php
                            endforeach;
                        } catch (Throwable $e) { /* DB tidak tersedia, skip */ }
                    }
                    ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($errorCode === 503): ?>
            <p style="margin-top:1.5rem;font-size:.8rem;color:#94a3b8;">
                Butuh bantuan segera? Hubungi kami di
                <a href="https://wa.me/6285797574754" style="color:#06b6d4;text-decoration:none;">WhatsApp</a>
            </p>
            <?php endif; ?>
        </div>
    </main>

    <footer>
        <p>&copy; <?= date('Y') ?> <a href="/">blog.libix.tech</a> &mdash; Libix Technology</p>
    </footer>

</body>
</html>
