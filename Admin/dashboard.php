<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../Logic/Auth.php';

$auth = new AuthLogic($conn);
$auth->requireLogin();

$adminUser   = $auth->getCurrentUser();
$logoutCsrf  = $auth->generateCsrfToken();

/* ─────────────────────────────────────────────────────────────
   AMBIL STATISTIK  |  semua pakai prepared statement
───────────────────────────────────────────────────────────── */

// Total views semua blog
$stmtViews = $conn->prepare('SELECT COALESCE(SUM(views), 0) AS total FROM `Post`');
$stmtViews->execute();
$totalViews = (int) $stmtViews->get_result()->fetch_assoc()['total'];
$stmtViews->close();

// Total blog (post)
$stmtBlog = $conn->prepare('SELECT COUNT(*) AS total FROM `Post`');
$stmtBlog->execute();
$totalBlog = (int) $stmtBlog->get_result()->fetch_assoc()['total'];
$stmtBlog->close();

// Total ulasan
$stmtUlasan = $conn->prepare('SELECT COUNT(*) AS total FROM `Ulasan`');
$stmtUlasan->execute();
$totalUlasan = (int) $stmtUlasan->get_result()->fetch_assoc()['total'];
$stmtUlasan->close();

// Total kategori
$stmtKat = $conn->prepare('SELECT COUNT(*) AS total FROM `Kategori`');
$stmtKat->execute();
$totalKategori = (int) $stmtKat->get_result()->fetch_assoc()['total'];
$stmtKat->close();

// 5 blog terbaru
$stmtRecent = $conn->prepare(
    'SELECT id, title, slug, views, createdAt FROM `Post` ORDER BY createdAt DESC LIMIT 5'
);
$stmtRecent->execute();
$recentPosts = $stmtRecent->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtRecent->close();

// 5 blog paling banyak dilihat
$stmtTop = $conn->prepare(
    'SELECT id, title, slug, views FROM `Post` ORDER BY views DESC LIMIT 5'
);
$stmtTop->execute();
$topPosts = $stmtTop->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtTop->close();

/* ─────────────────────────────────────────────────────────────
   KATA MOTIVASI  |  pool rotasi berdasarkan hari
───────────────────────────────────────────────────────────── */
$motivations = [
    [
        'quote'  => 'Kode yang baik bukan yang paling pintar, tapi yang paling mudah dipahami orang lain.',
        'author' => 'Clean Code Principle',
        'icon'   => 'pencil',
    ],
    [
        'quote'  => 'Setiap artikel yang kamu tulis hari ini adalah nilai yang kamu tinggalkan untuk ribuan pembaca masa depan.',
        'author' => 'Content Creator Wisdom',
        'icon'   => 'document',
    ],
    [
        'quote'  => 'Data tanpa konteks hanyalah angka. Konteks tanpa data hanyalah opini. Gabungkan keduanya.',
        'author' => 'Data Analytics Maxim',
        'icon'   => 'chart',
    ],
    [
        'quote'  => 'Bisnis terbaik bukan yang paling besar, tapi yang paling konsisten memberikan nilai kepada pelanggannya.',
        'author' => 'Business Strategy',
        'icon'   => 'briefcase',
    ],
    [
        'quote'  => 'Digitalisasi bukan tujuan akhir, tapi jalan menuju efisiensi yang memberdayakan semua orang.',
        'author' => 'Digital Transformation',
        'icon'   => 'sparkles',
    ],
    [
        'quote'  => 'Satu langkah kecil setiap hari lebih baik dari satu lompatan besar yang hanya dilakukan sekali.',
        'author' => 'Agile Philosophy',
        'icon'   => 'refresh',
    ],
    [
        'quote'  => 'UMKM yang bertransformasi digital hari ini, adalah pemimpin industri di masa depan.',
        'author' => 'Libix Technology Mission',
        'icon'   => 'mobile',
    ],
];

// Rotasi berdasarkan hari dalam setahun agar berbeda tiap hari
$todayMotivation = $motivations[(int) date('z') % count($motivations)];

/* ─────────────────────────────────────────────────────────────
   HELPER: format angka besar
───────────────────────────────────────────────────────────── */
function formatNumber(int $n): string
{
    if ($n >= 1_000_000) return number_format($n / 1_000_000, 1) . 'M';
    if ($n >= 1_000)     return number_format($n / 1_000, 1) . 'K';
    return (string) $n;
}

$title = 'Dashboard';

/* ─────────────────────────────────────────────────────────────
   VIEW
───────────────────────────────────────────────────────────── */
ob_start();
?>

<!-- ═══════════════════════════════════════════════════════════
     GREETING
════════════════════════════════════════════════════════════ -->
<?php
$hour    = (int) date('G');
$greeting = match(true) {
    $hour >= 5  && $hour < 12 => 'Selamat Pagi',
    $hour >= 12 && $hour < 15 => 'Selamat Siang',
    $hour >= 15 && $hour < 18 => 'Selamat Sore',
    default                   => 'Selamat Malam',
};
?>
<div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
    <div>
        <p class="text-xs text-cyber-muted tracking-widest uppercase mb-1">
            <?= date('l, d F Y') ?>
        </p>
        <h2 class="text-xl sm:text-2xl font-extrabold text-cyber-text">
            <?= $greeting ?>,
            <span class="text-cyber-orange" style="text-shadow:0 0 20px #06b6d460">
                <?= htmlspecialchars($adminUser['name'], ENT_QUOTES, 'UTF-8') ?>
            </span> 
        </h2>
        <p class="text-cyber-muted text-sm mt-1">Berikut ringkasan performa blog Libix Technology hari ini.</p>
    </div>

    <!-- Quick action -->
    <a href="/Admin/blog?action=create"
       class="inline-flex items-center gap-2 bg-cyber-orange hover:bg-cyber-orangeL active:bg-cyber-orangeD
              text-white text-sm font-bold px-4 py-2.5 rounded-xl transition-all
              self-start sm:self-auto flex-shrink-0"
    style="box-shadow: 0 0 16px #06b6d440">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Tulis Blog Baru
    </a>
</div>

<!-- ═══════════════════════════════════════════════════════════
     STATS CARDS  |  4 kolom
════════════════════════════════════════════════════════════ -->
<div class="grid grid-cols-1 xs:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">

    <!-- Card: Total Views -->
    <div class="relative overflow-hidden bg-cyber-card border border-cyber-border rounded-2xl p-5 group hover:border-cyber-orange/50 transition-all duration-200"
         style="box-shadow: 0 0 0 1px transparent">
        <!-- Glow accent -->
        <div class="absolute top-0 left-0 right-0 h-[2px] bg-gradient-to-r from-transparent via-cyber-orange to-transparent opacity-60 group-hover:opacity-100 transition-opacity"></div>
        <!-- BG decoration -->
        <div class="absolute -right-4 -bottom-4 w-24 h-24 rounded-full bg-cyber-orange opacity-[0.06] blur-2xl group-hover:opacity-[0.12] transition-opacity"></div>

        <div class="flex items-start justify-between mb-4">
            <div class="w-11 h-11 rounded-xl bg-cyber-orange/10 border border-cyber-orange/20 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-cyber-orange" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7
                             -1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
            </div>
            <span class="text-[10px] font-bold tracking-widest uppercase text-cyber-orange/70 bg-cyber-orange/10 px-2 py-1 rounded-lg">VIEWS</span>
        </div>

        <div class="relative z-10">
            <p class="text-3xl font-extrabold text-cyber-text tabular-nums"><?= formatNumber($totalViews) ?></p>
            <p class="text-xs text-cyber-muted mt-1">Total views semua blog</p>
        </div>
    </div>

    <!-- Card: Total Blog -->
    <div class="relative overflow-hidden bg-cyber-card border border-cyber-border rounded-2xl p-5 group hover:border-blue-500/40 transition-all duration-200">
        <div class="absolute top-0 left-0 right-0 h-[2px] bg-gradient-to-r from-transparent via-blue-500 to-transparent opacity-40 group-hover:opacity-80 transition-opacity"></div>
        <div class="absolute -right-4 -bottom-4 w-24 h-24 rounded-full bg-blue-500 opacity-[0.05] blur-2xl group-hover:opacity-[0.10] transition-opacity"></div>

        <div class="flex items-start justify-between mb-4">
            <div class="w-11 h-11 rounded-xl bg-blue-500/10 border border-blue-500/20 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586
                             a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </div>
            <span class="text-[10px] font-bold tracking-widest uppercase text-blue-400/70 bg-blue-500/10 px-2 py-1 rounded-lg">BLOG</span>
        </div>

        <div class="relative z-10">
            <p class="text-3xl font-extrabold text-cyber-text tabular-nums"><?= number_format($totalBlog) ?></p>
            <p class="text-xs text-cyber-muted mt-1">Total blog dipublikasikan</p>
        </div>
    </div>

    <!-- Card: Total Ulasan -->
    <div class="relative overflow-hidden bg-cyber-card border border-cyber-border rounded-2xl p-5 group hover:border-yellow-500/40 transition-all duration-200">
        <div class="absolute top-0 left-0 right-0 h-[2px] bg-gradient-to-r from-transparent via-yellow-500 to-transparent opacity-40 group-hover:opacity-80 transition-opacity"></div>
        <div class="absolute -right-4 -bottom-4 w-24 h-24 rounded-full bg-yellow-500 opacity-[0.05] blur-2xl group-hover:opacity-[0.10] transition-opacity"></div>

        <div class="flex items-start justify-between mb-4">
            <div class="w-11 h-11 rounded-xl bg-yellow-500/10 border border-yellow-500/20 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0
                        00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0
                        00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1
                        1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1
                        1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0
                        00.951-.69l1.07-3.292z"/>
                </svg>
            </div>
            <span class="text-[10px] font-bold tracking-widest uppercase text-yellow-400/70 bg-yellow-500/10 px-2 py-1 rounded-lg">ULASAN</span>
        </div>

        <div class="relative z-10">
            <p class="text-3xl font-extrabold text-cyber-text tabular-nums"><?= number_format($totalUlasan) ?></p>
            <p class="text-xs text-cyber-muted mt-1">Total ulasan pembaca</p>
        </div>
    </div>

    <!-- Card: Total Kategori -->
    <div class="relative overflow-hidden bg-cyber-card border border-cyber-border rounded-2xl p-5 group hover:border-purple-500/40 transition-all duration-200">
        <div class="absolute top-0 left-0 right-0 h-[2px] bg-gradient-to-r from-transparent via-purple-500 to-transparent opacity-40 group-hover:opacity-80 transition-opacity"></div>
        <div class="absolute -right-4 -bottom-4 w-24 h-24 rounded-full bg-purple-500 opacity-[0.05] blur-2xl group-hover:opacity-[0.10] transition-opacity"></div>

        <div class="flex items-start justify-between mb-4">
            <div class="w-11 h-11 rounded-xl bg-purple-500/10 border border-purple-500/20 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7
                             7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                </svg>
            </div>
            <span class="text-[10px] font-bold tracking-widest uppercase text-purple-400/70 bg-purple-500/10 px-2 py-1 rounded-lg">KATEGORI</span>
        </div>

        <div class="relative z-10">
            <p class="text-3xl font-extrabold text-cyber-text tabular-nums"><?= number_format($totalKategori) ?></p>
            <p class="text-xs text-cyber-muted mt-1">Total kategori aktif</p>
        </div>
    </div>

</div>

<!-- ═══════════════════════════════════════════════════════════
     BARIS BAWAH: Blog Terbaru + Top Views + Motivasi
════════════════════════════════════════════════════════════ -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

    <!-- ── Blog Terbaru (span 2 kolom) ────────────────── -->
    <div class="lg:col-span-2 bg-cyber-card border border-cyber-border rounded-2xl overflow-hidden">
        <!-- Header -->
        <div class="flex items-center justify-between px-5 py-4 border-b border-cyber-border">
            <div class="flex items-center gap-2.5">
                <div class="w-1.5 h-5 rounded-full bg-cyber-orange" style="box-shadow: 0 0 8px #06b6d4"></div>
                <h3 class="text-sm font-bold text-cyber-text">Blog Terbaru</h3>
            </div>
            <a href="/Admin/blog"
               class="text-[11px] text-cyber-muted hover:text-cyber-orange transition-colors flex items-center gap-1">
                Lihat semua
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </a>
        </div>

        <!-- List -->
        <div class="divide-y divide-cyber-border">
            <?php if (empty($recentPosts)): ?>
                <div class="px-5 py-10 text-center text-cyber-muted text-sm">
                    Belum ada artikel.
                </div>
            <?php else: ?>
                <?php foreach ($recentPosts as $i => $post): ?>
                    <div class="flex items-center gap-4 px-5 py-3.5 hover:bg-cyber-orange/[0.04] transition-colors group">
                        <!-- Nomor urut -->
                        <span class="flex-shrink-0 w-6 text-center text-xs font-bold tabular-nums text-cyber-dim">
                            <?= $i + 1 ?>
                        </span>

                        <!-- Info -->
                        <div class="flex-1 min-w-0">
                            <a href="/post/<?= htmlspecialchars($post['slug'], ENT_QUOTES, 'UTF-8') ?>"
                               target="_blank"
                               class="text-sm font-semibold text-cyber-text group-hover:text-cyber-orange transition-colors line-clamp-1 block">
                                <?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?>
                            </a>
                            <p class="text-[11px] text-cyber-muted mt-0.5">
                                <?= date('d M Y', strtotime($post['createdAt'])) ?>
                            </p>
                        </div>

                        <!-- Views badge -->
                        <div class="flex-shrink-0 flex items-center gap-1 text-[11px] text-cyber-muted bg-cyber-panel border border-cyber-border rounded-lg px-2 py-1">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7
                                         -1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            <span class="tabular-nums"><?= number_format((int)$post['views']) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Kolom kanan: Top Views + Motivasi ─────────────── -->
    <div class="flex flex-col gap-4">

        <!-- Top Views -->
        <div class="bg-cyber-card border border-cyber-border rounded-2xl overflow-hidden">
            <div class="flex items-center gap-2.5 px-5 py-4 border-b border-cyber-border">
                <div class="w-1.5 h-5 rounded-full bg-yellow-400" style="box-shadow: 0 0 8px #facc15"></div>
                <h3 class="text-sm font-bold text-cyber-text">Paling Banyak Dibaca</h3>
            </div>
            <div class="divide-y divide-cyber-border">
                <?php if (empty($topPosts)): ?>
                    <div class="px-5 py-8 text-center text-cyber-muted text-sm">Belum ada data.</div>
                <?php else: ?>
                    <?php foreach ($topPosts as $i => $post): ?>
                        <div class="flex items-center gap-3 px-4 py-3 hover:bg-yellow-400/[0.04] transition-colors group">
                            <!-- Rank medal -->
                            <div class="flex-shrink-0 w-6 h-6 rounded-md flex items-center justify-center text-xs font-extrabold
                                <?= match($i) {
                                    0 => 'bg-yellow-500/20 text-yellow-400 border border-yellow-500/30',
                                    1 => 'bg-slate-500/20 text-slate-400 border border-slate-500/30',
                                    2 => 'bg-orange-700/20 text-orange-500 border border-orange-700/30',
                                    default => 'bg-cyber-panel text-cyber-dim border border-cyber-border',
                                } ?>">
                                <?= $i + 1 ?>
                            </div>

                            <!-- Judul -->
                            <a href="/post/<?= htmlspecialchars($post['slug'], ENT_QUOTES, 'UTF-8') ?>"
                               target="_blank"
                               class="flex-1 min-w-0 text-xs font-semibold text-cyber-text group-hover:text-yellow-400 transition-colors line-clamp-2">
                                <?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?>
                            </a>

                            <!-- Views -->
                            <span class="flex-shrink-0 text-[11px] font-bold text-yellow-400 tabular-nums">
                                <?= formatNumber((int) $post['views']) ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Motivasi hari ini -->
        <div class="relative overflow-hidden bg-cyber-card border border-cyber-border rounded-2xl p-5 flex-shrink-0">
            <!-- Accent top -->
            <div class="absolute top-0 left-0 right-0 h-[2px] bg-gradient-to-r from-cyber-orange via-cyber-orangeL to-transparent opacity-80"></div>
            <!-- BG glow -->
            <div class="absolute -top-8 -right-8 w-40 h-40 rounded-full bg-cyber-orange opacity-[0.06] blur-3xl pointer-events-none"></div>

            <!-- Label -->
            <div class="flex items-center gap-2 mb-4 relative z-10">
                <div class="w-6 h-6 rounded-lg bg-cyber-orange/15 border border-cyber-orange/30 flex items-center justify-center flex-shrink-0">
                    <svg class="w-3.5 h-3.5 text-cyber-orange" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd"
                              d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z"
                              clip-rule="evenodd"/>
                    </svg>
                </div>
                <span class="text-[10px] font-bold tracking-widest uppercase text-cyber-orange/80">Motivasi Hari Ini</span>
            </div>

            <!-- Icon motivasi -->
            <div class="mb-3 relative z-10">
                <?php
                $motivIcons = [
                    'pencil'    => '<svg class="w-10 h-10 text-cyber-orange/30" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>',
                    'document'  => '<svg class="w-10 h-10 text-cyber-orange/30" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>',
                    'chart'     => '<svg class="w-10 h-10 text-cyber-orange/30" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>',
                    'briefcase' => '<svg class="w-10 h-10 text-cyber-orange/30" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>',
                    'sparkles'  => '<svg class="w-10 h-10 text-cyber-orange/30" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>',
                    'refresh'   => '<svg class="w-10 h-10 text-cyber-orange/30" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>',
                    'mobile'    => '<svg class="w-10 h-10 text-cyber-orange/30" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>',
                ];
                echo $motivIcons[$todayMotivation['icon']] ?? $motivIcons['document'];
                ?>
            </div>

            <!-- Quote -->
            <blockquote class="relative z-10 mb-3">
                <p class="text-sm text-cyber-text leading-relaxed font-medium italic">
                    "<?= htmlspecialchars($todayMotivation['quote'], ENT_QUOTES, 'UTF-8') ?>"
                </p>
            </blockquote>

            <!-- Author -->
            <div class="flex items-center gap-2 relative z-10">
                <div class="h-px flex-1 bg-cyber-border"></div>
                <p class="text-[11px] text-cyber-muted font-semibold tracking-wide flex-shrink-0">
                     |  <?= htmlspecialchars($todayMotivation['author'], ENT_QUOTES, 'UTF-8') ?>
                </p>
            </div>

            <!-- Tanggal -->
            <p class="text-[10px] text-cyber-dim mt-2 relative z-10 text-right">
                <?= date('d M Y') ?>
            </p>
        </div>

    </div><!-- /kolom kanan -->

</div><!-- /baris bawah -->

<!-- ═══════════════════════════════════════════════════════════
     QUICK LINKS
════════════════════════════════════════════════════════════ -->
<div class="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-3">
    <?php
    $quickLinks = [
        [
            'href'  => '/Admin/blog',
            'icon'  => 'document',
            'label' => 'Kelola Blog',
            'color' => 'text-blue-400 bg-blue-500/10 border-blue-500/20',
        ],
        [
            'href'  => '/Admin/kategori',
            'icon'  => 'tag',
            'label' => 'Kelola Kategori',
            'color' => 'text-purple-400 bg-purple-500/10 border-purple-500/20',
        ],
        [
            'href'  => '/Admin/ulasan',
            'icon'  => 'chat',
            'label' => 'Kelola Ulasan',
            'color' => 'text-yellow-400 bg-yellow-500/10 border-yellow-500/20',
        ],
        [
            'href'  => '/Admin/profile',
            'icon'  => 'user',
            'label' => 'Edit Profil',
            'color' => 'text-cyber-orange bg-cyber-orange/10 border-cyber-orange/20',
        ],
    ];
    ?>
    <?php foreach ($quickLinks as $link): ?>
        <a href="<?= $link['href'] ?>"
           class="flex items-center gap-3 bg-cyber-card border border-cyber-border hover:border-cyber-orange/40
                  rounded-xl px-4 py-3 transition-all duration-150 group">
            <div class="w-9 h-9 rounded-xl border flex items-center justify-center flex-shrink-0 <?= $link['color'] ?>">
                <?php
                $qlIcons = [
                    'document' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>',
                    'tag'      => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>',
                    'chat'     => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>',
                    'user'     => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>',
                ];
                echo $qlIcons[$link['icon']] ?? $qlIcons['document'];
                ?>
            </div>
            <span class="text-xs font-semibold text-cyber-muted group-hover:text-cyber-text transition-colors">
                <?= $link['label'] ?>
            </span>
        </a>
    <?php endforeach; ?>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/Admin/app.blade.php';
?>
