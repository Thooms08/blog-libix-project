<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/Logic/User/Blog.php';

$blogLogic = new BlogUserLogic($conn);

// ── Resolve APP_URL ───────────────────────────────────────────────────────
$appUrl = rtrim(getenv('APP_URL') ?: 'https://blog.libix.tech', '/');

// ── Filter kategori ──────────────────────────────────────────────────────
$activeKatSlug = trim($_GET['kat'] ?? '');
$activeKat     = null;
$topPosts      = [];

if ($activeKatSlug !== '') {
    $activeKat = $blogLogic->getKategoriBySlug($activeKatSlug);
    if ($activeKat === null) {
        // Slug tidak dikenal → redirect ke beranda (301)
        header('Location: /', true, 301);
        exit();
    }
    $topPosts = $blogLogic->getTopByKategoriId((int) $activeKat['id'], 5);
}

// Untuk kompatibilitas kode di bawah yang masih pakai $activeKatId
$activeKatId = $activeKat ? (int) $activeKat['id'] : 0;

// ── Paginasi ─────────────────────────────────────────────────────────────
const PER_PAGE = 20;

$totalPosts = $blogLogic->countPosts($activeKatId);
$totalPages = max(1, (int) ceil($totalPosts / PER_PAGE));
$page       = max(1, min($totalPages, (int) ($_GET['page'] ?? 1)));
$offset     = ($page - 1) * PER_PAGE;

$posts = $blogLogic->getPaginated(PER_PAGE, $offset, $activeKatId);

// ── Title halaman ─────────────────────────────────────────────────────────
$title = $activeKat
    ? 'Kategori: ' . htmlspecialchars($activeKat['nama'])
    : 'Beranda Blog & Informasi Kuliner';

// ── Variabel SEO ──────────────────────────────────────────────────────────
if ($activeKat) {
    // Halaman kategori
    $metaDesc     = 'Temukan ' . $totalPosts . ' blog terbaru tentang ' . $activeKat['nama']
                  . ' di blog.libix.tech - wawasan, inovasi teknologi, dan insight digital dari Libix Technology.';
    $metaKeywords = htmlspecialchars($activeKat['nama'])
                  . ', blog teknologi, software development, AI, startup Indonesia, solusi digital, Libix Technology';
    $ogTitle      = 'Blog ' . $activeKat['nama'] . ' - blog.libix.tech';
    $ogDesc       = $metaDesc;
    // OG image fallback: libix-logo (bukan og-default.jpg)
    $ogImage      = $appUrl . '/assets/libix-logo.png';
    $ogImageWidth  = 512;
    $ogImageHeight = 512;

    // Canonical: /kategori/{slug} untuk page 1, tambah ?page=N untuk halaman berikutnya
    $canonicalUrl = $appUrl . '/kategori/' . rawurlencode($activeKatSlug);
    if ($page > 1) $canonicalUrl .= '?page=' . $page;

    // Prev / Next untuk paginasi SEO
    $prevUrl = $page > 1          ? $appUrl . '/kategori/' . rawurlencode($activeKatSlug) . ($page > 2  ? '?page=' . ($page - 1) : '') : null;
    $nextUrl = $page < $totalPages ? $appUrl . '/kategori/' . rawurlencode($activeKatSlug) . '?page=' . ($page + 1) : null;

    // JSON-LD: CollectionPage + BreadcrumbList + WebSite
    $jsonLd = [
        '@context' => 'https://schema.org',
        '@graph'   => [
            [
                '@type'       => 'CollectionPage',
                '@id'         => $canonicalUrl . '#webpage',
                'url'         => $canonicalUrl,
                'name'        => 'Blog ' . $activeKat['nama'] . ' - blog.libix.tech',
                'description' => $metaDesc,
                'inLanguage'  => 'id-ID',
                'isPartOf'    => ['@id' => $appUrl . '/#website'],
                'breadcrumb'  => ['@id' => $canonicalUrl . '#breadcrumb'],
                'speakable'   => [
                    '@type'       => 'SpeakableSpecification',
                    'cssSelector' => ['h1', '.text-gray-600'],
                ],
            ],
            [
                '@type'           => 'BreadcrumbList',
                '@id'             => $canonicalUrl . '#breadcrumb',
                'itemListElement' => [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Beranda', 'item' => $appUrl . '/'],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => 'Kategori ' . $activeKat['nama'], 'item' => $canonicalUrl],
                ],
            ],
            [
                '@type'       => 'WebSite',
                '@id'         => $appUrl . '/#website',
                'url'         => $appUrl . '/',
                'name'        => 'blog.libix.tech',
                'description' => 'Blog wawasan, inovasi teknologi, dan solusi digital dari Libix Technology.',
                'inLanguage'  => 'id-ID',
                'publisher'   => ['@id' => 'https://libix.tech/#organization'],
            ],
        ],
    ];
} else {
    // Halaman beranda
    $metaDesc     = 'blog.libix.tech - wawasan, insight teknologi, dan cerita inovasi digital '
                  . 'dari Libix Technology. Baca ' . $totalPosts . ' blog terpilih tentang '
                  . 'software development, AI, platform digital, dan solusi teknologi terkini.';
    $metaKeywords = 'blog teknologi, software development, artificial intelligence, startup Indonesia, '
                  . 'platform digital, solusi digital, inovasi teknologi, Libix Technology';
    $ogTitle      = 'blog.libix.tech - Wawasan & Insight Teknologi dari Libix Technology';
    $ogDesc       = $metaDesc;
    $ogImage      = $appUrl . '/assets/libix-logo.png';
    $ogImageWidth  = 512;
    $ogImageHeight = 512;

    // Canonical: root untuk page 1, tambahkan ?page=N untuk halaman berikutnya
    $canonicalUrl = $page > 1
        ? $appUrl . '/?page=' . $page
        : $appUrl . '/';

    // Prev / Next untuk paginasi SEO
    $prevUrl = $page > 1          ? ($page === 2 ? $appUrl . '/' : $appUrl . '/?page=' . ($page - 1)) : null;
    $nextUrl = $page < $totalPages ? $appUrl . '/?page=' . ($page + 1) : null;

    // JSON-LD: WebSite + WebPage + Speakable + SearchAction
    $jsonLd = [
        '@context' => 'https://schema.org',
        '@graph'   => [
            [
                '@type'       => 'WebSite',
                '@id'         => $appUrl . '/#website',
                'url'         => $appUrl . '/',
                'name'        => 'blog.libix.tech',
                'alternateName' => 'Blog Libix Technology',
                'description' => 'Blog wawasan, inovasi teknologi, dan solusi digital dari Libix Technology.',
                'inLanguage'  => 'id-ID',
                'publisher'   => ['@id' => 'https://libix.tech/#organization'],
                'potentialAction' => [
                    '@type'       => 'SearchAction',
                    'target'      => ['@type' => 'EntryPoint', 'urlTemplate' => $appUrl . '/?q={search_term_string}'],
                    'query-input' => 'required name=search_term_string',
                ],
            ],
            [
                '@type'       => 'WebPage',
                '@id'         => $canonicalUrl . '#webpage',
                'url'         => $canonicalUrl,
                'name'        => 'blog.libix.tech - Wawasan & Insight Teknologi dari Libix Technology',
                'description' => $metaDesc,
                'inLanguage'  => 'id-ID',
                'isPartOf'    => ['@id' => $appUrl . '/#website'],
                'about'       => [
                    '@type' => 'Thing',
                    'name'  => 'Teknologi, Software Development & Inovasi Digital Indonesia',
                ],
                'speakable'   => [
                    '@type'       => 'SpeakableSpecification',
                    'cssSelector' => ['h1', '.text-gray-600'],
                ],
            ],
        ],
    ];
}

// ── Helper: bangun URL paginasi ───────────────────────────────────────────
function pageUrl(int $p, string $katSlug = ''): string {
    $base = $katSlug !== '' ? '/kategori/' . rawurlencode($katSlug) : '/';
    if ($p > 1) {
        return $base . ($katSlug !== '' ? '?' : '?') . 'page=' . $p;
    }
    return $base;
}

ob_start();
?>

<!-- ══════════════════════════════════════════════
     HERO SECTION
═══════════════════════════════════════════════ -->
<section class="py-12 bg-gradient-to-b from-brand-50 to-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <?php if ($activeKat): ?>
            <a href="/" class="inline-flex items-center gap-1.5 text-brand-500 text-sm font-semibold mb-3 hover:underline">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Semua Blog
            </a>
            <span class="block text-brand-500 font-bold tracking-wider uppercase text-sm mb-2">Kategori</span>
            <h1 class="text-4xl sm:text-5xl font-extrabold text-gray-900 mt-1 mb-4">
                <?= htmlspecialchars($activeKat['nama']) ?>
            </h1>
            <p class="text-gray-600 max-w-2xl mx-auto text-base sm:text-lg">
                <?= $totalPosts ?> blog dalam kategori <strong><?= htmlspecialchars($activeKat['nama']) ?></strong>.
            </p>
        <?php else: ?>
            <span class="text-brand-500 font-bold tracking-wider uppercase text-sm">blog.libix.tech</span>
            <h1 class="text-4xl sm:text-5xl font-extrabold text-gray-900 mt-2 mb-4">
                Wawasan &amp; Insight Teknologi
            </h1>
            <p class="text-gray-600 max-w-2xl mx-auto text-base sm:text-lg">
                Cerita inovasi, solusi digital, dan insight dari tim Libix Technology untuk Anda.
            </p>
        <?php endif; ?>
    </div>
</section>

<!-- ══════════════════════════════════════════════
     5 BLOG TERPOPULER (hanya saat filter kategori aktif & halaman 1)
═══════════════════════════════════════════════ -->
<?php if ($activeKat && !empty($topPosts) && $page === 1): ?>
<section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-10 pb-2">
    <div class="flex items-center gap-3 mb-6">
        <div class="w-1 h-6 bg-brand-500 rounded-full flex-shrink-0"></div>
        <h2 class="text-xl font-extrabold text-gray-900">
            5 Blog Terpopuler di
            <span class="text-brand-500"><?= htmlspecialchars($activeKat['nama']) ?></span>
        </h2>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
        <?php foreach ($topPosts as $rank => $top):
            $topUrl    = '/post/' . rawurlencode($top['slug']);
            $topHasImg = !empty($top['image']);
        ?>
            <a href="<?= $topUrl ?>"
               class="group relative bg-white rounded-2xl overflow-hidden border border-gray-100
                      hover:border-brand-300 hover:shadow-lg transition-all duration-300 flex flex-col">
                <div class="relative aspect-video bg-gray-50 overflow-hidden flex-shrink-0">
                    <?php if ($topHasImg): ?>
                        <img src="<?= htmlspecialchars($top['image']) ?>"
                             alt="<?= htmlspecialchars($top['title']) ?>"
                             class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                             loading="lazy"
                             onerror="this.closest('div').querySelector('.fl-no-img').classList.remove('hidden');this.remove()">
                    <?php endif; ?>
                    <div class="fl-no-img <?= $topHasImg ? 'hidden' : '' ?> absolute inset-0 flex flex-col items-center justify-center gap-1 bg-gray-50">
                        <svg class="w-7 h-7 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                  d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        <span class="text-xs text-gray-400">No Image</span>
                    </div>
                    <div class="absolute top-2 left-2 w-7 h-7 rounded-full bg-brand-500 text-white text-xs font-extrabold
                                flex items-center justify-center shadow-md shadow-brand-500/40">
                        <?= $rank + 1 ?>
                    </div>
                </div>
                <div class="p-3 flex flex-col flex-grow">
                    <h3 class="text-sm font-bold text-gray-900 group-hover:text-brand-500 transition-colors line-clamp-2 mb-2 flex-grow">
                        <?= htmlspecialchars($top['title']) ?>
                    </h3>
                    <span class="flex items-center gap-1 text-xs text-gray-400">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        <?= number_format((int) $top['views']) ?> Views
                    </span>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- ══════════════════════════════════════════════
     GRID BLOG
═══════════════════════════════════════════════ -->
<section class="py-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

    <?php if ($activeKat): ?>
        <div class="flex items-center gap-3 mb-6">
            <div class="w-1 h-6 bg-gray-300 rounded-full flex-shrink-0"></div>
            <h2 class="text-xl font-extrabold text-gray-900">
                Semua Blog | <?= htmlspecialchars($activeKat['nama']) ?>
            </h2>
        </div>
    <?php endif; ?>

    <?php if (!empty($posts)): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <?php foreach ($posts as $post): ?>
                <?php include __DIR__ . '/partials/blog-card.php'; ?>
            <?php endforeach; ?>
        </div>

        <!-- ═══════════════════════════════════════
             PAGINASI
        ════════════════════════════════════════ -->
        <?php if ($totalPages > 1): ?>
        <nav class="mt-12 flex items-center justify-center gap-1.5 flex-wrap" aria-label="Paginasi blog">

            <?php
            // Tombol Prev
            if ($page > 1): ?>
                <a href="<?= htmlspecialchars(pageUrl($page - 1, $activeKatSlug)) ?>"
                   class="inline-flex items-center justify-center w-10 h-10 rounded-xl border border-gray-200
                          bg-white text-gray-500 hover:border-brand-500 hover:text-brand-500 transition-all text-sm font-semibold"
                   aria-label="Halaman sebelumnya">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </a>
            <?php endif;

            // Tampilkan nomor halaman dengan window ±2 dari halaman aktif
            $windowStart = max(1, $page - 2);
            $windowEnd   = min($totalPages, $page + 2);

            // Halaman pertama + ellipsis
            if ($windowStart > 1): ?>
                <a href="<?= htmlspecialchars(pageUrl(1, $activeKatSlug)) ?>"
                   class="inline-flex items-center justify-center w-10 h-10 rounded-xl border border-gray-200
                          bg-white text-gray-600 hover:border-brand-500 hover:text-brand-500 transition-all text-sm font-semibold">
                    1
                </a>
                <?php if ($windowStart > 2): ?>
                    <span class="px-1 text-gray-400 text-sm">...</span>
                <?php endif;
            endif;

            // Nomor halaman dalam window
            for ($p = $windowStart; $p <= $windowEnd; $p++): ?>
                <a href="<?= htmlspecialchars(pageUrl($p, $activeKatSlug)) ?>"
                   class="inline-flex items-center justify-center w-10 h-10 rounded-xl border text-sm font-semibold transition-all
                          <?= $p === $page
                              ? 'border-brand-500 bg-brand-500 text-white shadow-md shadow-brand-500/30 pointer-events-none'
                              : 'border-gray-200 bg-white text-gray-600 hover:border-brand-500 hover:text-brand-500' ?>"
                   <?= $p === $page ? 'aria-current="page"' : '' ?>>
                    <?= $p ?>
                </a>
            <?php endfor;

            // Ellipsis + halaman terakhir
            if ($windowEnd < $totalPages):
                if ($windowEnd < $totalPages - 1): ?>
                    <span class="px-1 text-gray-400 text-sm">...</span>
                <?php endif; ?>
                <a href="<?= htmlspecialchars(pageUrl($totalPages, $activeKatSlug)) ?>"
                   class="inline-flex items-center justify-center w-10 h-10 rounded-xl border border-gray-200
                          bg-white text-gray-600 hover:border-brand-500 hover:text-brand-500 transition-all text-sm font-semibold">
                    <?= $totalPages ?>
                </a>
            <?php endif;

            // Tombol Next
            if ($page < $totalPages): ?>
                <a href="<?= htmlspecialchars(pageUrl($page + 1, $activeKatSlug)) ?>"
                   class="inline-flex items-center justify-center w-10 h-10 rounded-xl border border-gray-200
                          bg-white text-gray-500 hover:border-brand-500 hover:text-brand-500 transition-all text-sm font-semibold"
                   aria-label="Halaman berikutnya">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
            <?php endif; ?>

        </nav>

        <!-- Info halaman -->
        <p class="mt-4 text-center text-xs text-gray-400">
            Halaman <?= $page ?> dari <?= $totalPages ?>
            (<?= $totalPosts ?> blog)
        </p>
        <?php endif; ?>

    <?php else: ?>
        <div class="text-center py-20 bg-white rounded-2xl border border-gray-100">
            <svg class="w-12 h-12 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                      d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414A1 1 0 0119 9.414V19a2 2 0 01-2 2z"/>
            </svg>
            <p class="text-gray-500 font-medium">Belum ada artikel di kategori ini.</p>
            <a href="/" class="inline-flex items-center gap-1.5 mt-4 text-brand-500 font-semibold text-sm hover:underline">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Lihat Semua Blog
            </a>
        </div>
    <?php endif; ?>
</section>

<?php
$content = ob_get_clean();
include __DIR__ . '/layouts/User/app.blade.php';
?>
