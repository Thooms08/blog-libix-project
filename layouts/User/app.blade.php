<?php
/**
 * Layout User  |  blog.libix.tech
 * Dipakai oleh: index.php, blog.php, detail.php
 *
 * Variabel yang diharapkan dari halaman pemanggil:
 *  - $title        : judul halaman
 *  - $content      : konten HTML yang sudah di-ob_get_clean()
 *  - $conn         : koneksi MySQLi (sudah tersedia via config.php)
 *
 * Variabel SEO opsional (diisi penuh oleh blog.php, sebagian oleh index.php):
 *  - $metaDesc     : meta description (maks ~160 karakter)
 *  - $metaKeywords : kata kunci (opsional)
 *  - $canonicalUrl : URL kanonik absolut halaman ini
 *  - $ogTitle      : Open Graph title
 *  - $ogDesc       : Open Graph description
 *  - $ogImage      : URL absolut gambar OG (1200×630 ideal)
 *  - $ogType       : 'article' atau 'website' (default: 'website')
 *  - $articlePublishedTime : ISO 8601 (hanya artikel)
 *  - $articleModifiedTime  : ISO 8601 (hanya artikel)
 *  - $articleAuthor        : nama penulis (hanya artikel)
 *  - $articleSection       : nama kategori (hanya artikel)
 *  - $jsonLd       : array/object yang akan di-encode sebagai JSON-LD
 */

// ── Query 5 kategori terbanyak dipakai (untuk dropdown navbar) ────────────
$navKategoriList = [];
if (isset($conn)) {
    $navKatStmt = $conn->prepare(
        "SELECT k.id, k.nama, COUNT(kp.B) AS jumlah
         FROM   Kategori k
         INNER  JOIN _KategoriToPost kp ON kp.A = k.id
         GROUP  BY k.id, k.nama
         ORDER  BY jumlah DESC
         LIMIT  5"
    );
    if ($navKatStmt) {
        $navKatStmt->execute();
        $rows = $navKatStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $navKatStmt->close();

        // Tambahkan slug ke setiap kategori
        // Pastikan class BlogUserLogic sudah di-load (tersedia via index.php / blog.php)
        foreach ($rows as &$r) {
            $r['slug'] = class_exists('BlogUserLogic')
                ? BlogUserLogic::generateKategoriSlug($r['nama'])
                : strtolower(preg_replace('/[^a-z0-9]+/i', '-', trim($r['nama'])));
        }
        unset($r);
        $navKategoriList = $rows;
    }
}

// Slug kategori aktif (diset oleh index.php via $_GET['kat'], atau oleh blog.php via artikel)
$activeKatSlug = trim($_GET['kat'] ?? '');

// ── Resolve APP_URL (tanpa trailing slash) ────────────────────────────────
$appUrl = rtrim(getenv('APP_URL') ?: 'https://blog.libix.tech', '/');

// ── Defaults SEO ──────────────────────────────────────────────────────────
$_pageTitle    = isset($title) ? htmlspecialchars($title) : 'blog.libix.tech';
$_fullTitle    = isset($title) ? htmlspecialchars($title) . ' - blog.libix.tech' : 'blog.libix.tech - Wawasan & Insight Teknologi';
$_metaDesc     = isset($metaDesc)     ? htmlspecialchars($metaDesc)     : 'Wawasan, inovasi teknologi, dan insight digital dari Libix Technology. Software development, AI, platform digital, dan solusi teknologi terkini.';
$_metaKeywords = isset($metaKeywords) ? htmlspecialchars($metaKeywords) : 'blog teknologi, software development, AI, startup Indonesia, platform digital, solusi digital, Libix Technology';
$_canonical    = isset($canonicalUrl) ? htmlspecialchars($canonicalUrl) : $appUrl . htmlspecialchars(strtok($_SERVER['REQUEST_URI'], '?'));
$_ogTitle      = isset($ogTitle)      ? htmlspecialchars($ogTitle)      : $_pageTitle;
$_ogDesc       = isset($ogDesc)       ? htmlspecialchars($ogDesc)       : $_metaDesc;
// Gunakan libix-logo sebagai OG image default (thumbnail saat website di-share)
$_ogImage      = isset($ogImage)      ? htmlspecialchars($ogImage)      : $appUrl . '/assets/libix-logo.png';
$_ogType       = isset($ogType)       ? htmlspecialchars($ogType)       : 'website';
$_ogImageW     = isset($ogImageWidth)  ? (int) $ogImageWidth  : 1200;
$_ogImageH     = isset($ogImageHeight) ? (int) $ogImageHeight : 630;

// ── Organization (dipakai oleh JSON-LD di semua halaman) ─────────────────
$_orgSchema = [
    '@type'       => 'Organization',
    '@id'         => 'https://libix.tech/#organization',
    'name'        => 'Libix Technology',
    'url'         => 'https://libix.tech',
    'logo'        => [
        '@type'  => 'ImageObject',
        '@id'    => $appUrl . '/assets/libix-logo.png',
        'url'    => $appUrl . '/assets/libix-logo.png',
        'width'  => 512,
        'height' => 512,
    ],
    'sameAs' => [
        'https://www.instagram.com/libix.tech',
        'https://wa.me/6285797574754',
    ],
    'contactPoint' => [
        '@type'             => 'ContactPoint',
        'telephone'         => '+62-857-9757-4754',
        'email'             => 'admin@libix.tech',
        'contactType'       => 'customer service',
        'availableLanguage' => 'Indonesian',
    ],
];

// ── JSON-LD default (WebSite + Organization - dipakai jika halaman tidak set $jsonLd) ──
if (!isset($jsonLd)) {
    $jsonLd = [
        '@context' => 'https://schema.org',
        '@graph'   => [
            [
                '@type'       => 'WebSite',
                '@id'         => $appUrl . '/#website',
                'url'         => $appUrl . '/',
                'name'        => 'blog.libix.tech',
                'description' => 'Blog wawasan, inovasi teknologi, dan solusi digital dari Libix Technology.',
                'inLanguage'  => 'id-ID',
                'publisher'   => ['@id' => 'https://libix.tech/#organization'],
                'potentialAction' => [
                    '@type'       => 'SearchAction',
                    'target'      => [
                        '@type'       => 'EntryPoint',
                        'urlTemplate' => $appUrl . '/?q={search_term_string}',
                    ],
                    'query-input' => 'required name=search_term_string',
                ],
            ],
            $_orgSchema,
        ],
    ];
} else {
    // Sisipkan Organization ke dalam @graph yang sudah ada dari halaman
    if (isset($jsonLd['@graph']) && is_array($jsonLd['@graph'])) {
        $jsonLd['@graph'][] = $_orgSchema;
    }
}
?>
<!DOCTYPE html>
<html lang="id" prefix="og: https://ogp.me/ns#">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- ── Identitas & Performa ──────────────────────────────────────── -->
    <title><?= $_fullTitle ?></title>
    <meta name="theme-color" content="#06b6d4">
    <meta name="color-scheme" content="light">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="blog.libix.tech">
    <meta name="application-name" content="blog.libix.tech">
    <meta name="generator" content="Libix Technology">

    <!-- ── DNS Prefetch & Preconnect (performa) ──────────────────────── -->
    <link rel="dns-prefetch" href="//fonts.googleapis.com">
    <link rel="dns-prefetch" href="//fonts.gstatic.com">
    <link rel="dns-prefetch" href="//cdn.tailwindcss.com">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <!-- ── SEO Dasar ──────────────────────────────────────────────────── -->
    <meta name="description" content="<?= $_metaDesc ?>">
    <meta name="keywords"    content="<?= $_metaKeywords ?>">
    <meta name="robots"      content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1">
    <meta name="author"      content="<?= isset($articleAuthor) ? htmlspecialchars($articleAuthor) : 'Tim Redaksi blog.libix.tech' ?>">
    <meta name="publisher"   content="Libix Technology">
    <meta name="copyright"   content="© <?= date('Y') ?> blog.libix.tech">
    <meta name="language"    content="Indonesian">
    <meta name="revisit-after" content="3 days">
    <meta name="rating"      content="General">
    <link rel="canonical"    href="<?= $_canonical ?>">

    <!-- ── Paginasi (prev/next untuk Google) ────────────────────────── -->
    <?php if (isset($prevUrl)): ?><link rel="prev" href="<?= htmlspecialchars($prevUrl) ?>"><?php endif; ?>
    <?php if (isset($nextUrl)): ?><link rel="next" href="<?= htmlspecialchars($nextUrl) ?>"><?php endif; ?>

    <!-- ── Open Graph (WhatsApp, Facebook, LINE, Telegram, dsb.) ─────── -->
    <meta property="og:type"        content="<?= $_ogType ?>">
    <meta property="og:url"         content="<?= $_canonical ?>">
    <meta property="og:title"       content="<?= $_ogTitle ?>">
    <meta property="og:description" content="<?= $_ogDesc ?>">
    <meta property="og:image"       content="<?= $_ogImage ?>">
    <meta property="og:image:secure_url" content="<?= $_ogImage ?>">
    <meta property="og:image:width"  content="<?= $_ogImageW ?>">
    <meta property="og:image:height" content="<?= $_ogImageH ?>">
    <meta property="og:image:type"  content="image/<?= str_ends_with(strtolower(parse_url($_ogImage, PHP_URL_PATH) ?? ''), '.png') ? 'png' : 'jpeg' ?>">
    <meta property="og:image:alt"   content="<?= $_ogTitle ?>">
    <meta property="og:site_name"   content="blog.libix.tech">
    <meta property="og:locale"      content="id_ID">
    <meta property="og:locale:alternate" content="en_US">
    <?php if ($_ogType === 'article'): ?>
    <meta property="article:published_time" content="<?= isset($articlePublishedTime) ? htmlspecialchars($articlePublishedTime) : '' ?>">
    <meta property="article:modified_time"  content="<?= isset($articleModifiedTime)  ? htmlspecialchars($articleModifiedTime)  : '' ?>">
    <meta property="article:author"         content="<?= isset($articleAuthor) ? htmlspecialchars($articleAuthor) : 'Tim Redaksi blog.libix.tech' ?>">
    <meta property="article:publisher"      content="https://libix.tech">
    <?php if (isset($articleSection)): ?>
    <meta property="article:section"        content="<?= htmlspecialchars($articleSection) ?>">
    <?php endif;
    if (isset($metaKeywords)): ?>
    <meta property="article:tag"            content="<?= htmlspecialchars($metaKeywords) ?>">
    <?php endif; ?>
    <?php endif; ?>

    <!-- ── Twitter / X Card ──────────────────────────────────────────── -->
    <meta name="twitter:card"        content="summary_large_image">
    <meta name="twitter:site"        content="@libixtech">
    <meta name="twitter:creator"     content="@libixtech">
    <meta name="twitter:title"       content="<?= $_ogTitle ?>">
    <meta name="twitter:description" content="<?= $_ogDesc ?>">
    <meta name="twitter:image"       content="<?= $_ogImage ?>">
    <meta name="twitter:image:alt"   content="<?= $_ogTitle ?>">
    <meta name="twitter:domain"      content="blog.libix.tech">

    <!-- ── WhatsApp / Telegram link preview (reinforced) ────────────── -->
    <meta property="og:rich_attachment" content="true">

    <!-- ── JSON-LD Structured Data ───────────────────────────────────── -->
    <script type="application/ld+json">
    <?= json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?>
    </script>

    <!-- ── Favicon & App Icons (coverage penuh semua platform) ──────── -->
    <link rel="icon"            type="image/png" sizes="512x512" href="/assets/libix-logo.png">
    <link rel="icon"            type="image/png" sizes="192x192" href="/assets/libix-logo.png">
    <link rel="icon"            type="image/png" sizes="96x96"   href="/assets/libix-logo.png">
    <link rel="icon"            type="image/png" sizes="32x32"   href="/assets/libix-logo.png">
    <link rel="icon"            type="image/png" sizes="16x16"   href="/assets/libix-logo.png">
    <link rel="shortcut icon"   href="/assets/libix-logo.png">
    <link rel="apple-touch-icon"                 href="/assets/libix-logo.png">
    <link rel="apple-touch-icon" sizes="152x152" href="/assets/libix-logo.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/libix-logo.png">
    <meta name="msapplication-TileImage" content="/assets/libix-logo.png">
    <meta name="msapplication-TileColor" content="#06b6d4">
    <meta name="msapplication-config"    content="/browserconfig.xml">
    <link rel="manifest" href="/site.webmanifest">

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Google Fonts: Plus Jakarta Sans (preload untuk performa) -->
    <link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Devicon CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/devicons/devicon@latest/devicon.min.css">

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            50: '#ecfeff',
                            100: '#cffafe',
                            500: '#06b6d4',
                            600: '#0891b2',
                            700: '#0e7490',
                        }
                    }
                }
            }
        }
    </script>

    <style>
        /* Dropdown kategori */
        .nav-dropdown { display: none; }
        .nav-dropdown-wrap:hover .nav-dropdown,
        .nav-dropdown-wrap:focus-within .nav-dropdown { display: block; }
    </style>
</head>
<body class="font-sans bg-gray-50 text-gray-800 flex flex-col min-h-screen">

    <!-- ══════════════════════════════════════════
         HEADER / NAVBAR
    ═══════════════════════════════════════════ -->
    <header class="bg-white border-b border-gray-100 sticky top-0 z-50 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-20">

                <!-- Logo -->
                <a href="/" class="flex items-center gap-3 flex-shrink-0">
                    <img src="/assets/libix-logo.png" alt="Libix Technology" class="h-10 w-auto object-contain">
                    <span class="font-extrabold text-xl text-gray-900 tracking-tight">
                        blog.<span class="text-brand-500">libix.tech</span>
                    </span>
                </a>

                <!-- Nav desktop -->
                <nav class="hidden md:flex items-center gap-8 font-semibold text-gray-600">
                    <a href="/" class="hover:text-brand-500 transition-colors <?= $activeKatId === 0 && basename($_SERVER['PHP_SELF']) === 'index.php' ? 'text-brand-500' : '' ?>">
                        Beranda
                    </a>

                    <!-- Dropdown Kategori -->
                    <div class="nav-dropdown-wrap relative" id="katMenuWrap">
                        <button
                            id="katMenuBtn"
                            type="button"
                            aria-haspopup="true"
                            aria-expanded="false"
                            class="flex items-center gap-1.5 hover:text-brand-500 transition-colors focus:outline-none <?= $activeKatSlug !== '' ? 'text-brand-500' : '' ?>"
                        >
                            Kategori
                            <svg id="katChevron" class="w-4 h-4 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>

                        <!-- Dropdown panel -->
                        <div
                            id="katDropdown"
                            class="nav-dropdown absolute left-0 top-full mt-2 w-56 bg-white border border-gray-100
                                   rounded-xl shadow-xl overflow-hidden z-50 py-1"
                            role="menu"
                        >
                            <?php if (!empty($navKategoriList)): ?>
                                <?php foreach ($navKategoriList as $navKat): ?>
                                    <a href="/kategori/<?= htmlspecialchars($navKat['slug'], ENT_QUOTES, 'UTF-8') ?>"
                                       role="menuitem"
                                       class="flex items-center justify-between px-4 py-2.5 text-sm text-gray-700
                                              hover:bg-brand-50 hover:text-brand-600 transition-colors
                                              <?= $activeKatSlug === $navKat['slug'] ? 'bg-brand-50 text-brand-600 font-semibold' : '' ?>">
                                        <span><?= htmlspecialchars($navKat['nama']) ?></span>
                                        <span class="text-xs text-gray-400 bg-gray-100 rounded-full px-2 py-0.5">
                                            <?= (int) $navKat['jumlah'] ?>
                                        </span>
                                    </a>
                                <?php endforeach; ?>
                                <div class="border-t border-gray-100 mt-1 pt-1">
                                    <a href="/"
                                       role="menuitem"
                                       class="flex items-center gap-2 px-4 py-2.5 text-xs text-gray-500 hover:text-brand-500 transition-colors">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/>
                                        </svg>
                                        Semua Blog
                                    </a>
                                </div>
                            <?php else: ?>
                                <p class="px-4 py-3 text-sm text-gray-400">Belum ada kategori.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </nav>

                <!-- Hamburger mobile -->
                <button
                    id="mobileMenuBtn"
                    type="button"
                    class="md:hidden p-2 rounded-lg text-gray-600 hover:text-brand-500 hover:bg-brand-50 transition-colors"
                    aria-label="Buka menu"
                    aria-expanded="false"
                >
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>
            </div>

            <!-- Nav mobile (tersembunyi default) -->
            <div id="mobileMenu" class="hidden md:hidden pb-4 border-t border-gray-100 pt-3 space-y-1">
                <a href="/" class="block px-3 py-2.5 rounded-xl text-sm font-semibold text-gray-700 hover:bg-brand-50 hover:text-brand-600 transition-colors">
                    Beranda
                </a>

                <!-- Accordion Kategori mobile -->
                <div>
                    <button
                        id="mobileKatBtn"
                        type="button"
                        class="w-full flex items-center justify-between px-3 py-2.5 rounded-xl text-sm font-semibold text-gray-700 hover:bg-brand-50 hover:text-brand-600 transition-colors"
                    >
                        <span>Kategori</span>
                        <svg id="mobileKatChevron" class="w-4 h-4 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div id="mobileKatList" class="hidden pl-4 mt-1 space-y-0.5">
                        <?php foreach ($navKategoriList as $navKat): ?>
                            <a href="/kategori/<?= htmlspecialchars($navKat['slug'], ENT_QUOTES, 'UTF-8') ?>"
                               class="flex items-center justify-between px-3 py-2 rounded-xl text-sm text-gray-600 hover:bg-brand-50 hover:text-brand-600 transition-colors
                                      <?= $activeKatSlug === $navKat['slug'] ? 'bg-brand-50 text-brand-600 font-semibold' : '' ?>">
                                <span><?= htmlspecialchars($navKat['nama']) ?></span>
                                <span class="text-xs text-gray-400"><?= (int) $navKat['jumlah'] ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div>
        </div>
    </header>

    <!-- ══════════════════════════════════════════
         KONTEN HALAMAN
    ═══════════════════════════════════════════ -->
    <main class="flex-grow">
        <?php if (isset($content)) echo $content; ?>
    </main>

    <!-- ══════════════════════════════════════════
         FOOTER
    ═══════════════════════════════════════════ -->
    <footer class="bg-gray-900 text-gray-300 pt-16 pb-12 border-t border-gray-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-12 mb-12">

                <!-- Brand -->
                <div>
                    <div class="flex items-center gap-3 mb-4">
                        <img src="/assets/libix-logo.png" alt="Libix Technology" class="h-9 w-9 rounded-lg object-contain bg-white p-0.5">
                        <span class="font-extrabold text-xl text-white tracking-tight">
                            blog.<span class="text-brand-500">libix.tech</span>
                        </span>
                    </div>
                    <p class="text-gray-400 text-sm leading-relaxed">
                        blog.libix.tech adalah blog resmi Libix Technology - startup teknologi yang menghadirkan solusi digital inovatif.
                        Temukan wawasan, cerita inovasi, dan insight teknologi dari tim kami.
                    </p>
                </div>

                <!-- Navigasi -->
                <div>
                    <h3 class="text-white font-bold text-lg mb-4">Navigasi</h3>
                    <ul class="space-y-2.5 text-sm">
                        <li><a href="/" class="hover:text-brand-500 transition-colors">Beranda</a></li>
                        <?php foreach ($navKategoriList as $navKat): ?>
                            <li>
                                <a href="/kategori/<?= htmlspecialchars($navKat['slug'], ENT_QUOTES, 'UTF-8') ?>"
                                   class="hover:text-brand-500 transition-colors">
                                    <?= htmlspecialchars($navKat['nama']) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <!-- Kontak -->
                <div>
                    <h3 class="text-white font-bold text-lg mb-4">Kontak Kami</h3>
                    <ul class="space-y-3 text-sm">
                        <li class="flex items-center gap-3">
                            <!-- WhatsApp icon -->
                            <svg class="w-5 h-5 text-brand-500 flex-shrink-0" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                            </svg>
                            <span>WhatsApp: 085797574754</span>
                        </li>
                        <li class="flex items-center gap-3">
                            <!-- Envelope / Email icon -->
                            <svg class="w-5 h-5 text-brand-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                            <span>Email: admin@libix.tech</span>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="border-t border-gray-800 pt-8 text-center text-sm text-gray-500">
                <p>&copy; <?= date('Y') ?> blog.libix.tech. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Loading overlay -->
    <script src="/loading.js"></script>

    <!-- ══════════════════════════════════════════
         NAVBAR JAVASCRIPT
    ═══════════════════════════════════════════ -->
    <script>
    (function () {
        /* ── Dropdown Kategori (desktop) ─────── */
        const katBtn      = document.getElementById('katMenuBtn');
        const katDropdown = document.getElementById('katDropdown');
        const katChevron  = document.getElementById('katChevron');

        function openKat() {
            katDropdown.style.display = 'block';
            katBtn.setAttribute('aria-expanded', 'true');
            katChevron.style.transform = 'rotate(180deg)';
        }
        function closeKat() {
            katDropdown.style.display = 'none';
            katBtn.setAttribute('aria-expanded', 'false');
            katChevron.style.transform = 'rotate(0deg)';
        }

        katBtn?.addEventListener('click', (e) => {
            e.stopPropagation();
            katDropdown.style.display === 'block' ? closeKat() : openKat();
        });

        document.addEventListener('click', (e) => {
            if (!document.getElementById('katMenuWrap')?.contains(e.target)) closeKat();
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeKat();
        });

        /* ── Hamburger mobile ────────────────── */
        const mobileMenuBtn  = document.getElementById('mobileMenuBtn');
        const mobileMenu     = document.getElementById('mobileMenu');
        const mobileKatBtn   = document.getElementById('mobileKatBtn');
        const mobileKatList  = document.getElementById('mobileKatList');
        const mobileKatChev  = document.getElementById('mobileKatChevron');

        mobileMenuBtn?.addEventListener('click', () => {
            const open = !mobileMenu.classList.contains('hidden');
            mobileMenu.classList.toggle('hidden', open);
            mobileMenuBtn.setAttribute('aria-expanded', String(!open));
        });

        mobileKatBtn?.addEventListener('click', () => {
            const open = !mobileKatList.classList.contains('hidden');
            mobileKatList.classList.toggle('hidden', open);
            mobileKatChev.style.transform = open ? 'rotate(0deg)' : 'rotate(180deg)';
        });
    })();
    </script>

</body>
</html>
