<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/Logic/User/Blog.php';
require_once __DIR__ . '/Logic/User/Ulasan.php';

$blogLogic   = new BlogUserLogic($conn);
$ulasanLogic = new UlasanUserLogic($conn);

// ── Resolve APP_URL ───────────────────────────────────────────────────────
$appUrl = rtrim(getenv('APP_URL') ?: 'https://blog.libix.tech', '/');

// Ambil slug dari URL
$slug = trim($_GET['slug'] ?? '');

if ($slug === '') {
    header('Location: /');
    exit();
}

$post = $blogLogic->getBySlug($slug);

if ($post === null) {
    $errorCode    = 404;
    $errorTitle   = 'Blog Tidak Ditemukan';
    $errorMessage = 'Blog "' . htmlspecialchars($slug) . '" tidak ada, sudah dihapus, atau URL-nya salah. Coba cari di halaman beranda.';
    require __DIR__ . '/error.php';
    exit();
}

$blogLogic->incrementViews((int) $post['id']);

$reviews          = $ulasanLogic->getByPostId((int) $post['id']);
$ratingData       = $ulasanLogic->getAverageRating((int) $post['id']);
$ratingDist       = $ulasanLogic->getRatingDistribution((int) $post['id']);

$title       = htmlspecialchars($post['title']);
$createdDate = date('d F Y', strtotime($post['createdAt']));
$avgRating   = round($ratingData['avg_rating'], 1);
$totalReview = (int) $ratingData['total_reviews'];

// ── Variabel SEO untuk layout ─────────────────────────────────────────────
// Canonical: gunakan URL bersih /post/{slug}
$canonicalUrl = $appUrl . '/post/' . rawurlencode($post['slug']);

// Meta description: dari excerpt, fallback ke strip_tags(content) ~160 karakter
$_rawDesc = !empty($post['excerpt'])
    ? $post['excerpt']
    : mb_substr(strip_tags($post['content']), 0, 160);
$metaDesc = mb_strlen($_rawDesc) > 160
    ? mb_substr($_rawDesc, 0, 157) . '...'
    : $_rawDesc;

// Keywords: gabungan kategori + long-tail teknologi
$_katNames    = array_column($post['categories'] ?? [], 'nama');
$_baseKeywords = ['blog teknologi', 'software development', 'startup teknologi Indonesia',
                  'artificial intelligence', 'platform digital', 'solusi digital', 'Libix Technology'];
$metaKeywords = implode(', ', array_unique(array_merge($_katNames, $_baseKeywords)));

// OG / Twitter - gunakan libix-logo sebagai fallback (bukan og-default.jpg)
$ogType  = 'article';
$ogTitle = $post['title'] . ' - blog.libix.tech';
$ogDesc  = $metaDesc;
$ogImage = !empty($post['image'])
    ? (str_starts_with($post['image'], 'http') ? $post['image'] : $appUrl . $post['image'])
    : $appUrl . '/assets/libix-logo.png';

// Dimensi OG image (artikel punya thumbnail, logo pakai 512x512)
$ogImageWidth  = !empty($post['image']) ? 1200 : 512;
$ogImageHeight = !empty($post['image']) ? 630  : 512;

// Article meta
$articlePublishedTime = date('c', strtotime($post['createdAt']));
$articleModifiedTime  = date('c', strtotime($post['updatedAt'] ?? $post['createdAt']));
$articleAuthor        = $post['author_name'] ?? 'Tim Redaksi blog.libix.tech';
$articleSection       = !empty($_katNames) ? $_katNames[0] : 'Teknologi';

// ── Hitung reading time & word count (untuk AEO / GEO) ───────────────────
$_plainContent  = strip_tags($post['content'] ?? '');
$_wordCount     = str_word_count($_plainContent);
$_readingTimeMin = max(1, (int) round($_wordCount / 200)); // ~200 kata/menit

// ── Speakable CSS selectors (AEO: Google Assistant / AI Overview) ─────────
$_speakableCss = ['h1', '.prose h2', '.prose h3', '.prose p:first-of-type'];

// ── JSON-LD: Article + BreadcrumbList + Speakable + WebPage ──────────────
$jsonLd = [
    '@context' => 'https://schema.org',
    '@graph'   => [
        // 1. WebPage
        [
            '@type'           => 'WebPage',
            '@id'             => $canonicalUrl . '#webpage',
            'url'             => $canonicalUrl,
            'name'            => $post['title'] . ' - blog.libix.tech',
            'description'     => $metaDesc,
            'inLanguage'      => 'id-ID',
            'isPartOf'        => ['@id' => $appUrl . '/#website'],
            'datePublished'   => $articlePublishedTime,
            'dateModified'    => $articleModifiedTime,
            'primaryImageOfPage' => ['@id' => $ogImage],
            'speakable'       => [
                '@type'            => 'SpeakableSpecification',
                'cssSelector'      => $_speakableCss,
            ],
            'breadcrumb'      => ['@id' => $canonicalUrl . '#breadcrumb'],
        ],
        // 2. Article (NewsArticle lebih kuat untuk GEO / AI Overview)
        [
            '@type'            => 'Article',
            '@id'              => $canonicalUrl . '#article',
            'headline'         => $post['title'],
            'alternativeHeadline' => $post['title'],
            'description'      => $metaDesc,
            'image'            => [
                '@type'  => 'ImageObject',
                '@id'    => $ogImage,
                'url'    => $ogImage,
                'width'  => $ogImageWidth,
                'height' => $ogImageHeight,
            ],
            'datePublished'    => $articlePublishedTime,
            'dateModified'     => $articleModifiedTime,
            'author'           => [
                '@type' => 'Person',
                'name'  => $articleAuthor,
                'url'   => $appUrl . '/',
            ],
            'publisher'        => ['@id' => 'https://libix.tech/#organization'],
            'url'              => $canonicalUrl,
            'mainEntityOfPage' => ['@id' => $canonicalUrl . '#webpage'],
            'isPartOf'         => ['@id' => $appUrl . '/#website'],
            'articleSection'   => $articleSection,
            'keywords'         => implode(', ', array_unique(array_merge($_katNames, $_baseKeywords))),
            'wordCount'        => $_wordCount,
            'timeRequired'     => 'PT' . $_readingTimeMin . 'M',
            'inLanguage'       => 'id-ID',
            'copyrightYear'    => date('Y', strtotime($post['createdAt'])),
            'copyrightHolder'  => ['@id' => 'https://libix.tech/#organization'],
            'interactionStatistic' => [
                '@type'                => 'InteractionCounter',
                'interactionType'      => 'https://schema.org/ReadAction',
                'userInteractionCount' => (int) $post['views'],
            ],
            'speakable' => [
                '@type'       => 'SpeakableSpecification',
                'cssSelector' => $_speakableCss,
            ],
        ],
        // 3. BreadcrumbList
        [
            '@type' => 'BreadcrumbList',
            '@id'   => $canonicalUrl . '#breadcrumb',
            'itemListElement' => array_merge(
                [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Beranda', 'item' => $appUrl . '/'],
                ],
                !empty($_katNames) ? [[
                    '@type'    => 'ListItem',
                    'position' => 2,
                    'name'     => $_katNames[0],
                    'item'     => $appUrl . '/kategori/' . rawurlencode(
                                    class_exists('BlogUserLogic')
                                        ? BlogUserLogic::generateKategoriSlug($_katNames[0])
                                        : strtolower(preg_replace('/[^a-z0-9]+/i', '-', trim($_katNames[0])))
                                  ),
                ]] : [],
                [[
                    '@type'    => 'ListItem',
                    'position' => !empty($_katNames) ? 3 : 2,
                    'name'     => $post['title'],
                    'item'     => $canonicalUrl,
                ]]
            ),
        ],
    ],
];

// Tambah AggregateRating + Review ke artikel jika ada ulasan
if ($totalReview > 0) {
    $jsonLd['@graph'][1]['aggregateRating'] = [
        '@type'       => 'AggregateRating',
        'ratingValue' => (string) $avgRating,
        'reviewCount' => $totalReview,
        'bestRating'  => '5',
        'worstRating' => '1',
    ];
    // Sertakan ulasan terbaru (maks 3) untuk snippet ulasan di SERP
    $reviewSchemas = [];
    foreach (array_slice($reviews, 0, 3) as $rev) {
        if (empty($rev['comment'])) continue;
        $reviewSchemas[] = [
            '@type'         => 'Review',
            'reviewRating'  => [
                '@type'       => 'Rating',
                'ratingValue' => (string) (int) $rev['rating'],
                'bestRating'  => '5',
                'worstRating' => '1',
            ],
            'reviewBody'    => mb_substr(htmlspecialchars_decode($rev['comment']), 0, 200),
            'datePublished' => date('c', strtotime($rev['created_at'])),
            'author'        => ['@type' => 'Person', 'name' => 'Pembaca blog.libix.tech'],
        ];
    }
    if (!empty($reviewSchemas)) {
        $jsonLd['@graph'][1]['review'] = $reviewSchemas;
    }
}

/* ── helper: render N bintang ───────────────────────────────────────── */
function renderStars(float $rating, string $size = 'w-5 h-5'): string
{
    $html = '';
    for ($i = 1; $i <= 5; $i++) {
        $color = $i <= $rating ? 'text-yellow-400' : 'text-gray-300';
        $html .= '<svg class="' . $size . ' ' . $color . '" fill="currentColor" viewBox="0 0 20 20">
            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0
                00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0
                00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54
                1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1
                1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0
                00.951-.69l1.07-3.292z"/>
        </svg>';
    }
    return $html;
}

ob_start();
?>

<!-- ════════════════════════════════════════════════
     HERO / META ARTIKEL
═════════════════════════════════════════════════ -->
<div class="bg-gradient-to-b from-brand-50 to-white pt-8 pb-4">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">

        <!-- Breadcrumb -->
        <nav class="mb-5 text-sm" aria-label="Breadcrumb">
            <ol class="inline-flex items-center gap-1 text-brand-500 font-medium flex-wrap">
                <li>
                    <a href="/" class="hover:text-brand-600 transition-colors">Beranda</a>
                </li>
                <?php if (!empty($_katNames)): ?>
                    <li aria-hidden="true"><svg class="w-3.5 h-3.5 text-gray-400 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg></li>
                    <li>
                        <a href="/kategori/<?= htmlspecialchars(
                            class_exists('BlogUserLogic')
                                ? BlogUserLogic::generateKategoriSlug($_katNames[0])
                                : strtolower(preg_replace('/[^a-z0-9]+/i', '-', trim($_katNames[0])))
                        ) ?>" class="hover:text-brand-600 transition-colors"><?= htmlspecialchars($_katNames[0]) ?></a>
                    </li>
                <?php endif; ?>
                <li aria-hidden="true"><svg class="w-3.5 h-3.5 text-gray-400 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg></li>
                <li class="text-gray-500 line-clamp-1 max-w-xs" aria-current="page"><?= htmlspecialchars($post['title']) ?></li>
            </ol>
        </nav>

        <!-- Kategori badge -->
        <?php if (!empty($post['categories'])): ?>
            <div class="flex flex-wrap gap-2 mb-4">
                <?php foreach ($post['categories'] as $cat): ?>
                    <span class="px-3 py-1 bg-brand-100 text-brand-700 rounded-full text-xs font-semibold tracking-wide">
                        <?= htmlspecialchars($cat['nama']) ?>
                    </span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Judul -->
        <h1 class="text-3xl sm:text-4xl font-extrabold text-gray-900 leading-tight mb-4">
            <?= htmlspecialchars($post['title']) ?>
        </h1>

        <!-- Excerpt -->
        <?php if (!empty($post['excerpt'])): ?>
            <p class="text-lg text-gray-600 leading-relaxed mb-5">
                <?= htmlspecialchars($post['excerpt']) ?>
            </p>
        <?php endif; ?>

        <!-- Meta: penulis · tanggal · views · rating -->
        <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-sm text-gray-500 pb-5 border-b border-gray-200">

            <!-- Penulis -->
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-full bg-brand-500 flex items-center justify-center flex-shrink-0">
                    <span class="text-white text-xs font-bold">A</span>
                </div>
                <span class="font-medium text-gray-700">
                    <?= htmlspecialchars($post['author_name'] ?? 'Admin Libix Technology') ?>
                </span>
            </div>

            <span class="hidden sm:block text-gray-300">|</span>

            <!-- Tanggal -->
            <span class="flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <?= $createdDate ?>
            </span>

            <!-- Views -->
            <span class="flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
                <?= number_format($post['views']) ?> Views
            </span>

            <!-- Reading time -->
            <span class="flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <?= $_readingTimeMin ?> menit baca
            </span>

            <!-- Rating ringkasan -->
            <?php if ($totalReview > 0): ?>
                <span class="flex items-center gap-1">
                    <svg class="w-4 h-4 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0
                            00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0
                            00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54
                            1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1
                            1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0
                            00.951-.69l1.07-3.292z"/>
                    </svg>
                    <strong class="text-gray-700"><?= $avgRating ?></strong>
                    <span>(<?= $totalReview ?> ulasan)</span>
                </span>
            <?php endif; ?>
        </div>

        <!-- ── Tombol Share ──────────────────────────────────────────────── -->
        <div class="flex items-center gap-2 pt-4 pb-2">
            <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Bagikan:</span>

            <!-- Tombol Share Utama (Web Share API / fallback copy link) -->
            <button id="btnShare"
                    type="button"
                    data-title="<?= htmlspecialchars($post['title']) ?>"
                    data-text="<?= htmlspecialchars($metaDesc) ?>"
                    data-url="<?= htmlspecialchars($canonicalUrl) ?>"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-brand-500 hover:bg-brand-600
                           text-white text-sm font-semibold transition-all active:scale-95 shadow-sm shadow-brand-500/30">
                <!-- Share icon -->
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3
                             3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684
                             3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/>
                </svg>
                <span id="btnShareLabel">Bagikan Blog</span>
            </button>

            <!-- Indikator "Link disalin!" -->
            <span id="copyToast"
                  class="hidden items-center gap-1.5 px-3 py-2 rounded-xl bg-green-50 border border-green-200
                         text-green-700 text-xs font-semibold">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                Link disalin!
            </span>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════════════════
     KONTEN ARTIKEL
═════════════════════════════════════════════════ -->
<article class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    <!-- Thumbnail -->
    <figure class="mb-8">
        <?php if (!empty($post['image'])): ?>
            <img src="<?= htmlspecialchars($post['image']) ?>"
                 alt="<?= htmlspecialchars($post['title']) ?>"
                 class="w-full h-auto rounded-2xl shadow-md object-cover"
                 loading="lazy"
                 onerror="this.replaceWith(document.getElementById('no-img-tpl').content.cloneNode(true))">
        <?php else: ?>
            <?php /* image kosong dari DB, langsung tampilkan placeholder */ ?>
            <div class="w-full aspect-video rounded-2xl border-2 border-dashed border-gray-200 bg-gray-50
                        flex flex-col items-center justify-center gap-3">
                <svg class="w-14 h-14 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586
                             a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0
                             00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <span class="text-gray-400 font-medium text-sm tracking-wide">No Image</span>
            </div>
        <?php endif; ?>
    </figure>

    <!-- Template placeholder dipakai oleh onerror di atas -->
    <template id="no-img-tpl">
        <div class="w-full aspect-video rounded-2xl border-2 border-dashed border-gray-200 bg-gray-50
                    flex flex-col items-center justify-center gap-3">
            <svg class="w-14 h-14 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                      d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586
                         a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0
                         00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            <span class="text-gray-400 font-medium text-sm tracking-wide">No Image</span>
        </div>
    </template>

    <!-- Body konten -->
    <div class="
        prose prose-lg max-w-none
        prose-headings:font-bold prose-headings:text-gray-900
        prose-h2:text-2xl prose-h2:mt-10 prose-h2:mb-4
        prose-h3:text-xl prose-h3:mt-8 prose-h3:mb-3
        prose-p:text-gray-700 prose-p:leading-relaxed prose-p:mb-4
        prose-a:text-brand-500 prose-a:font-medium hover:prose-a:text-brand-600
        prose-ul:list-disc prose-ul:pl-6 prose-li:mb-1
        prose-ol:list-decimal prose-ol:pl-6
        prose-blockquote:border-l-4 prose-blockquote:border-brand-400
        prose-blockquote:pl-4 prose-blockquote:italic prose-blockquote:text-gray-600
        prose-strong:text-gray-900
        mb-10
    ">
        <?= $post['content'] ?>
    </div>

    <!-- ════════════════════════════════════════════════
         CTA – STICKY & INLINE
    ═════════════════════════════════════════════════ -->

    <!-- CTA Inline (di dalam konten) -->
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-brand-500 to-cyan-700 p-8 sm:p-10 text-center mb-12 shadow-xl shadow-brand-500/25">
        <!-- Dekorasi bulat -->
        <div class="absolute -top-10 -right-10 w-40 h-40 bg-white/10 rounded-full pointer-events-none"></div>
        <div class="absolute -bottom-8 -left-8 w-32 h-32 bg-white/10 rounded-full pointer-events-none"></div>

        <div class="relative z-10">
            <span class="inline-block bg-white/20 text-white text-xs font-bold uppercase tracking-widest px-3 py-1 rounded-full mb-4">
                Tentang Kami
            </span>
            <h3 class="text-2xl sm:text-3xl font-extrabold text-white mb-3">
                Wujudkan Ide Digital Anda Bersama Libix Technology
            </h3>
            <p class="text-cyan-100 text-base mb-6 max-w-xl mx-auto">
                Libix Technology adalah startup teknologi yang menghadirkan solusi inovatif di bidang software development, AI, dan platform digital. Kami melayani kebutuhan teknologi Anda dari konsep hingga produk jadi.
            </p>
            <a href="https://libix.tech"
               target="_blank"
               rel="noopener noreferrer"
               class="inline-flex items-center gap-2 bg-white text-brand-600 font-extrabold
                      px-8 py-4 rounded-xl text-base hover:bg-cyan-50 active:scale-95
                      transition-all duration-200 shadow-lg shadow-black/20">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                </svg>
                Kenali Libix Technology
            </a>
            <p class="text-cyan-200 text-xs mt-3">Software Development · AI · Platform Digital · Dan banyak lagi</p>
        </div>
    </div>

    <!-- ════════════════════════════════════════════════
         SECTION ULASAN
    ═════════════════════════════════════════════════ -->
    <section id="ulasan" class="border-t border-gray-100 pt-10">

        <h2 class="text-2xl font-extrabold text-gray-900 mb-6">Ulasan Pembaca</h2>

        <!-- Ringkasan rating (tampil hanya jika ada ulasan) -->
        <?php if ($totalReview > 0): ?>
            <div class="bg-gray-50 rounded-2xl p-6 mb-8 flex flex-col sm:flex-row gap-6 items-center">

                <!-- Angka & bintang rata-rata -->
                <div class="text-center flex-shrink-0">
                    <div class="text-5xl font-extrabold text-gray-900 leading-none mb-1">
                        <?= $avgRating ?>
                    </div>
                    <div class="flex justify-center gap-0.5 mb-1">
                        <?= renderStars($avgRating, 'w-5 h-5') ?>
                    </div>
                    <div class="text-sm text-gray-500"><?= $totalReview ?> ulasan</div>
                </div>

                <!-- Bar distribusi -->
                <div class="flex-1 w-full space-y-2">
                    <?php for ($s = 5; $s >= 1; $s--): ?>
                        <?php
                        $cnt  = $ratingDist[$s] ?? 0;
                        $pct  = $totalReview > 0 ? round(($cnt / $totalReview) * 100) : 0;
                        ?>
                        <div class="flex items-center gap-3 text-sm">
                            <span class="w-6 text-right font-medium text-gray-600"><?= $s ?></span>
                            <svg class="w-4 h-4 text-yellow-400 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0
                                    00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0
                                    00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54
                                    1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1
                                    1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0
                                    00.951-.69l1.07-3.292z"/>
                            </svg>
                            <div class="flex-1 bg-gray-200 rounded-full h-2.5 overflow-hidden">
                                <div class="bg-yellow-400 h-2.5 rounded-full transition-all duration-500"
                                     style="width:<?= $pct ?>%"></div>
                            </div>
                            <span class="w-6 text-gray-500"><?= $cnt ?></span>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- ── FORM ULASAN ───────────────────────────────────── -->
        <div class="bg-white border border-gray-200 rounded-2xl p-6 mb-8 shadow-sm">
            <h3 class="text-lg font-bold text-gray-900 mb-4">Tulis Ulasan</h3>

            <form id="reviewForm" novalidate class="space-y-5">
                <input type="hidden" id="postId" value="<?= (int) $post['id'] ?>">

                <!-- Pilih rating bintang -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Rating <span class="text-red-500">*</span>
                    </label>
                    <div id="starPicker" class="flex gap-2" role="group" aria-label="Pilih rating">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <button type="button"
                                    class="star-btn w-9 h-9 text-gray-300 hover:text-yellow-400
                                           focus:outline-none transition-colors duration-150"
                                    data-value="<?= $i ?>"
                                    aria-label="<?= $i ?> bintang">
                                <svg fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0
                                        00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0
                                        00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54
                                        1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1
                                        1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0
                                        00.951-.69l1.07-3.292z"/>
                                </svg>
                            </button>
                        <?php endfor; ?>
                    </div>
                    <input type="hidden" id="ratingValue" name="rating">
                    <p id="ratingError" class="mt-1 text-xs text-red-500 hidden">Pilih rating dulu ya.</p>
                </div>

                <!-- Komentar (opsional) -->
                <div>
                    <label for="commentInput" class="block text-sm font-semibold text-gray-700 mb-2">
                        Komentar
                        <span class="text-gray-400 font-normal">(opsional)</span>
                    </label>
                    <textarea id="commentInput"
                              name="comment"
                              rows="4"
                              maxlength="500"
                              placeholder="Bagikan kesan Anda tentang artikel ini..."
                              class="w-full px-4 py-3 text-sm border border-gray-300 rounded-xl
                                     focus:ring-2 focus:ring-brand-500 focus:border-brand-500
                                     resize-none outline-none transition placeholder-gray-400"></textarea>
                    <div class="flex justify-end mt-1.5">
                        <span id="charCounter" class="text-xs text-gray-400 tabular-nums">
                            <span id="charCurrent">0</span>/500
                        </span>
                    </div>
                </div>

                <!-- Tombol kirim -->
                <button type="submit"
                        id="submitBtn"
                        class="w-full bg-brand-500 hover:bg-brand-600 active:scale-[.98]
                               text-white font-bold py-3 px-6 rounded-xl transition-all
                               duration-200 flex items-center justify-center gap-2
                               disabled:opacity-60 disabled:cursor-not-allowed">
                    <svg id="submitIcon" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                    </svg>
                    <span id="submitLabel">Kirim Ulasan</span>
                </button>
            </form>

            <!-- Alert feedback AJAX -->
            <div id="reviewAlert" class="hidden mt-4 px-4 py-3 rounded-xl text-sm font-medium"></div>
        </div>

        <!-- ── DAFTAR ULASAN ─────────────────────────────────── -->
        <?php
        // Pisahkan 3 teratas dan sisanya
        $visibleReviews = array_slice($reviews, 0, 3);
        $hiddenReviews  = array_slice($reviews, 3);
        ?>

        <div id="reviewSection">
            <?php if (!empty($reviews)): ?>
                <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
                    <h3 class="text-lg font-bold text-gray-900">
                        Ulasan Pembaca
                        <span class="text-gray-400 font-normal text-base" id="reviewCountLabel">(<?= $totalReview ?>)</span>
                    </h3>
                </div>

                <!-- Ulasan yang selalu tampil (3 pertama) -->
                <div id="reviewsVisible" class="space-y-4">
                    <?php foreach ($visibleReviews as $rev): ?>
                        <div class="review-card border border-gray-100 rounded-xl p-5 bg-gray-50 hover:bg-white transition-colors duration-200">
                            <div class="flex items-center justify-between flex-wrap gap-2 mb-2">
                                <div class="flex gap-0.5">
                                    <?= renderStars((float) $rev['rating'], 'w-4 h-4') ?>
                                </div>
                                <time class="text-xs text-gray-400">
                                    <?= date('d M Y • H:i', strtotime($rev['created_at'])) ?>
                                </time>
                            </div>
                            <?php if (!empty($rev['comment'])): ?>
                                <p class="text-gray-700 text-sm leading-relaxed">
                                    <?= nl2br(htmlspecialchars($rev['comment'])) ?>
                                </p>
                            <?php else: ?>
                                <p class="text-gray-400 text-sm italic">Tidak ada komentar.</p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Ulasan tersembunyi (sisanya setelah 3) -->
                <?php if (!empty($hiddenReviews)): ?>
                    <div id="reviewsHidden" class="space-y-4 mt-4 hidden">
                        <?php foreach ($hiddenReviews as $rev): ?>
                            <div class="review-card border border-gray-100 rounded-xl p-5 bg-gray-50 hover:bg-white transition-colors duration-200">
                                <div class="flex items-center justify-between flex-wrap gap-2 mb-2">
                                    <div class="flex gap-0.5">
                                        <?= renderStars((float) $rev['rating'], 'w-4 h-4') ?>
                                    </div>
                                    <time class="text-xs text-gray-400">
                                        <?= date('d M Y • H:i', strtotime($rev['created_at'])) ?>
                                    </time>
                                </div>
                                <?php if (!empty($rev['comment'])): ?>
                                    <p class="text-gray-700 text-sm leading-relaxed">
                                        <?= nl2br(htmlspecialchars($rev['comment'])) ?>
                                    </p>
                                <?php else: ?>
                                    <p class="text-gray-400 text-sm italic">Tidak ada komentar.</p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Tombol lihat semua / sembunyikan -->
                    <div class="mt-5 text-center">
                        <button id="btnToggleReviews" type="button"
                                class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl border border-brand-500
                                       text-brand-500 hover:bg-brand-50 font-semibold text-sm transition-all">
                            <svg id="btnToggleIcon" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                            <span id="btnToggleLabel">Lihat Semua <?= $totalReview ?> Ulasan</span>
                        </button>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <!-- Empty state  |  akan diganti JS setelah user submit -->
                <div id="emptyReview" class="text-center py-10 text-gray-400">
                    <svg class="w-10 h-10 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                              d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0
                                 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                    </svg>
                    <p class="text-sm">Belum ada ulasan. Jadilah yang pertama!</p>
                </div>
            <?php endif; ?>
        </div>

    </section>
</article>

<!-- ════════════════════════════════════════════════
     CTA STICKY BOTTOM (mobile)
═════════════════════════════════════════════════ -->
<div class="fixed bottom-0 inset-x-0 z-40 sm:hidden bg-white border-t border-gray-200 shadow-2xl px-4 py-3">
    <a href="https://libix.tech"
       target="_blank"
       rel="noopener noreferrer"
       class="flex items-center justify-center gap-2 bg-brand-500 hover:bg-brand-600
              text-white font-bold py-3 rounded-xl w-full transition-colors active:scale-[.98]">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M13 7l5 5m0 0l-5 5m5-5H6"/>
        </svg>
        PELAJARI LIBIX TECHNOLOGY
    </a>
</div>

<!-- Spacer agar konten tidak tertutup sticky bar di mobile -->
<div class="h-20 sm:hidden"></div>

<!-- ════════════════════════════════════════════════
     JAVASCRIPT – RATING + REALTIME COUNTER + AJAX
═════════════════════════════════════════════════ -->
<script>
(function () {
    /* ── Elemen ───────────────────────────────────── */
    const starBtns     = document.querySelectorAll('.star-btn');
    const ratingInput  = document.getElementById('ratingValue');
    const ratingError  = document.getElementById('ratingError');
    const commentEl    = document.getElementById('commentInput');
    const charCurrent  = document.getElementById('charCurrent');
    const charCounter  = document.getElementById('charCounter');
    const reviewForm   = document.getElementById('reviewForm');
    const submitBtn    = document.getElementById('submitBtn');
    const submitLabel  = document.getElementById('submitLabel');
    const submitIcon   = document.getElementById('submitIcon');
    const reviewAlert  = document.getElementById('reviewAlert');

    let selectedRating = 0;

    /* ── Helpers bintang ──────────────────────────── */
    function paintStars(upTo) {
        starBtns.forEach(btn => {
            const val = parseInt(btn.dataset.value, 10);
            btn.classList.toggle('text-yellow-400', val <= upTo);
            btn.classList.toggle('text-gray-300',   val >  upTo);
        });
    }

    starBtns.forEach(btn => {
        btn.addEventListener('mouseenter', () => paintStars(parseInt(btn.dataset.value, 10)));
        btn.addEventListener('mouseleave', () => paintStars(selectedRating));
        btn.addEventListener('click', () => {
            selectedRating    = parseInt(btn.dataset.value, 10);
            ratingInput.value = selectedRating;
            ratingError.classList.add('hidden');
            paintStars(selectedRating);
        });
    });

    /* ── Counter realtime ─────────────────────────── */
    commentEl?.addEventListener('input', () => {
        const len = commentEl.value.length;
        charCurrent.textContent = len;
        if (len >= 480) {
            charCounter.classList.add('text-red-500', 'font-semibold');
            charCounter.classList.remove('text-gray-400');
        } else {
            charCounter.classList.remove('text-red-500', 'font-semibold');
            charCounter.classList.add('text-gray-400');
        }
    });

    /* ── Alert helper ────────────────────────────── */
    function showAlert(msg, type) {
        reviewAlert.textContent = msg;
        reviewAlert.className   = 'mt-4 px-4 py-3 rounded-xl text-sm font-medium ' +
            (type === 'success'
                ? 'bg-green-50 text-green-800 border border-green-200'
                : 'bg-red-50 text-red-800 border border-red-200');
        reviewAlert.classList.remove('hidden');
        reviewAlert.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        if (type === 'success') setTimeout(() => reviewAlert.classList.add('hidden'), 5000);
    }

    /* ── Loading state ───────────────────────────── */
    function setLoading(on) {
        submitBtn.disabled      = on;
        submitLabel.textContent = on ? 'Mengirim…' : 'Kirim Ulasan';
        submitIcon.innerHTML    = on
            ? '<circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" fill="none" stroke-dasharray="31.4" stroke-dashoffset="10" style="animation:spin .8s linear infinite;transform-origin:center"/>'
            : '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>';
    }

    /* ── Render bintang SVG (JS) ─────────────────── */
    function renderStarsJs(rating) {
        let html = '';
        for (let i = 1; i <= 5; i++) {
            const color = i <= rating ? '#facc15' : '#d1d5db';
            html += `<svg class="w-4 h-4" fill="${color}" viewBox="0 0 20 20">
                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0
                    00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0
                    00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1
                    1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1
                    1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0
                    00.951-.69l1.07-3.292z"/>
            </svg>`;
        }
        return html;
    }

    /* ── Inject ulasan baru ke DOM (no reload) ───── */
    function injectNewReview(rating, comment) {
        const now       = new Date();
        const dateStr   = now.toLocaleDateString('id-ID', { day:'2-digit', month:'short', year:'numeric' })
                        + ' • ' + now.toLocaleTimeString('id-ID', { hour:'2-digit', minute:'2-digit' });

        // Escape HTML untuk mencegah DOM XSS - komentar dari user tidak boleh dirender sebagai HTML
        function escHtml(str) {
            const d = document.createElement('div');
            d.appendChild(document.createTextNode(str));
            return d.innerHTML;
        }

        // Render komentar sebagai plain-text (baris baru → <br>)
        const safeComment = escHtml(comment).replace(/\n/g, '<br>');
        const commentHtml = comment
            ? `<p class="text-gray-700 text-sm leading-relaxed">${safeComment}</p>`
            : `<p class="text-gray-400 text-sm italic">Tidak ada komentar.</p>`;

        const card = document.createElement('div');
        card.className = 'review-card border border-gray-100 rounded-xl p-5 bg-gray-50 hover:bg-white transition-colors duration-200';
        card.innerHTML = `
            <div class="flex items-center justify-between flex-wrap gap-2 mb-2">
                <div class="flex gap-0.5">${renderStarsJs(rating)}</div>
                <time class="text-xs text-gray-400">${dateStr}</time>
            </div>
            ${commentHtml}`;

        // Cari kontainer ulasan yang sudah ada, atau buat jika belum ada
        const visibleContainer = document.getElementById('reviewsVisible');
        const emptyState       = document.getElementById('emptyReview');

        if (emptyState) {
            // Belum ada ulasan  |  buat struktur baru
            const section = document.getElementById('reviewSection');
            section.innerHTML = `
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-bold text-gray-900">
                        Ulasan Pembaca
                        <span class="text-gray-400 font-normal text-base" id="reviewCountLabel">(1)</span>
                    </h3>
                </div>
                <div id="reviewsVisible" class="space-y-4"></div>`;
            document.getElementById('reviewsVisible').appendChild(card);
        } else if (visibleContainer) {
            // Sudah ada ulasan  |  prepend ke kontainer visible
            visibleContainer.insertBefore(card, visibleContainer.firstChild);

            // Update counter label
            const label = document.getElementById('reviewCountLabel');
            if (label) {
                const current = parseInt(label.textContent.replace(/\D/g, ''), 10) || 0;
                label.textContent = `(${current + 1})`;
            }
        }
    }

    /* ── Show/hide semua ulasan ──────────────────── */
    const btnToggle    = document.getElementById('btnToggleReviews');
    const hiddenBlock  = document.getElementById('reviewsHidden');
    const btnToggleIcon  = document.getElementById('btnToggleIcon');
    const btnToggleLabel = document.getElementById('btnToggleLabel');

    if (btnToggle && hiddenBlock) {
        let expanded = false;

        btnToggle.addEventListener('click', () => {
            expanded = !expanded;
            hiddenBlock.classList.toggle('hidden', !expanded);

            if (expanded) {
                btnToggleLabel.textContent = 'Sembunyikan Ulasan';
                btnToggleIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>';
                hiddenBlock.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            } else {
                const total = document.getElementById('reviewCountLabel')?.textContent.replace(/\D/g,'') || '';
                btnToggleLabel.textContent = `Lihat Semua ${total} Ulasan`;
                btnToggleIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>';
            }
        });
    }

    /* ── Submit ──────────────────────────────────── */
    reviewForm?.addEventListener('submit', async (e) => {
        e.preventDefault();

        if (selectedRating === 0) {
            ratingError.classList.remove('hidden');
            ratingError.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            return;
        }

        setLoading(true);
        reviewAlert.classList.add('hidden');

        const commentVal = commentEl.value.trim();
        const fd = new FormData();
        fd.append('post_id', document.getElementById('postId').value);
        fd.append('rating',  selectedRating);
        fd.append('comment', commentVal);

        try {
            const res  = await fetch('/submit-review.php', { method: 'POST', body: fd });
            const data = await res.json();

            if (data.success) {
                showAlert(data.message, 'success');

                // Inject ulasan baru langsung ke DOM  |  tanpa reload
                injectNewReview(selectedRating, commentVal);

                // Reset form
                reviewForm.reset();
                selectedRating    = 0;
                ratingInput.value = '';
                charCurrent.textContent = '0';
                paintStars(0);
            } else {
                showAlert(data.message, 'error');
            }
        } catch (_) {
            showAlert('Terjadi kesalahan koneksi. Silakan coba lagi.', 'error');
        } finally {
            setLoading(false);
        }
    });

    /* ── Spin keyframe ───────────────────────────── */
    const style = document.createElement('style');
    style.textContent = '@keyframes spin { to { transform: rotate(360deg); } }';
    document.head.appendChild(style);

    /* ── Web Share API + fallback copy link ──────── */
    (function () {
        const btn       = document.getElementById('btnShare');
        const toast     = document.getElementById('copyToast');
        const btnLabel  = document.getElementById('btnShareLabel');
        if (!btn) return;

        const shareData = {
            title : btn.dataset.title || document.title,
            text  : btn.dataset.text  || '',
            url   : btn.dataset.url   || location.href,
        };

        let toastTimer = null;

        function showToast() {
            toast.classList.remove('hidden');
            toast.classList.add('inline-flex');
            clearTimeout(toastTimer);
            toastTimer = setTimeout(() => {
                toast.classList.add('hidden');
                toast.classList.remove('inline-flex');
                btnLabel.textContent = 'Bagikan Blog';
            }, 3000);
        }

        btn.addEventListener('click', async () => {
            // Coba Web Share API bawaan browser (mobile & desktop modern)
            if (navigator.share) {
                try {
                    await navigator.share(shareData);
                    return; // berhasil di-share via dialog OS
                } catch (err) {
                    // User membatalkan share (AbortError) - tidak perlu fallback
                    if (err.name === 'AbortError') return;
                    // Error lain → fallback ke copy link
                }
            }

            // Fallback: salin URL ke clipboard
            try {
                await navigator.clipboard.writeText(shareData.url);
                btnLabel.textContent = 'Link Disalin ';
                showToast();
            } catch (_) {
                // Fallback lama untuk browser yang tidak support Clipboard API
                const ta = document.createElement('textarea');
                ta.value = shareData.url;
                ta.style.cssText = 'position:fixed;opacity:0;pointer-events:none';
                document.body.appendChild(ta);
                ta.focus();
                ta.select();
                try {
                    document.execCommand('copy');
                    btnLabel.textContent = 'Link Disalin ';
                    showToast();
                } catch (_) { /* silent */ }
                document.body.removeChild(ta);
            }
        });
    })();

})();
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/layouts/User/app.blade.php';
?>
