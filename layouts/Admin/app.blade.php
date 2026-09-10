<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($title) ? htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '  |  Admin Libix Technology' : 'Admin Panel  |  Libix Technology' ?></title>

    <link rel="icon" type="image/png" sizes="32x32" href="/assets/libix-logo.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/libix-logo.png">
    <link rel="apple-touch-icon" href="/assets/libix-logo.png">

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">


    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                    },
                    colors: {
                        cyber: {
                            bg:      '#0a0a0f',
                            card:    '#0f0f1a',
                            panel:   '#12121f',
                            sidebar: '#0d0d18',
                            border:  '#1a1a2e',
                            orange:  '#06b6d4',
                            orangeL: '#22d3ee',
                            orangeD: '#0891b2',
                            text:    '#e2e8f0',
                            muted:   '#64748b',
                            dim:     '#334155',
                            active:  '#1a1a2e',
                        },
                    },
                    boxShadow: {
                        'cyber':    '0 0 20px #06b6d430, 0 0 60px #06b6d410',
                        'cyber-sm': '0 0 10px #06b6d425',
                        'sidebar':  '4px 0 24px rgba(0,0,0,0.4)',
                    },
                    animation: {
                        'pulse-slow': 'pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite',
                        'flicker':    'flicker 5s linear infinite',
                    },
                    keyframes: {
                        flicker: {
                            '0%, 95%, 100%': { opacity: '1' },
                            '96%':           { opacity: '0.7' },
                            '98%':           { opacity: '1' },
                            '99%':           { opacity: '0.8' },
                        },
                    },
                    screens: {
                        'xs': '480px',
                    },
                },
            },
        }
    </script>

    <style>
        /* Cyber grid background */
        .cyber-grid {
            background-image:
                linear-gradient(rgba(249, 115, 22, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(249, 115, 22, 0.03) 1px, transparent 1px);
            background-size: 40px 40px;
        }

        /* Sidebar nav item active glow */
        .nav-item-active {
            background: linear-gradient(90deg, rgba(249,115,22,0.15) 0%, rgba(249,115,22,0.05) 100%);
            border-left: 3px solid #06b6d4;
        }
        .nav-item-active .nav-icon { color: #06b6d4; }
        .nav-item-active .nav-text { color: #06b6d4; font-weight: 700; }

        /* Hover nav item */
        .nav-item:hover:not(.nav-item-active) {
            background: rgba(249,115,22,0.06);
            border-left-color: rgba(249,115,22,0.3);
        }

        /* Sidebar scrollbar */
        #sidebar::-webkit-scrollbar { width: 3px; }
        #sidebar::-webkit-scrollbar-track { background: transparent; }
        #sidebar::-webkit-scrollbar-thumb { background: #06b6d440; border-radius: 2px; }

        /* Main scrollbar */
        ::-webkit-scrollbar { width: 4px; }
        ::-webkit-scrollbar-track { background: #0a0a0f; }
        ::-webkit-scrollbar-thumb { background: #06b6d450; border-radius: 2px; }

        /* Glow text */
        .text-glow { text-shadow: 0 0 20px #06b6d480, 0 0 40px #06b6d440; }

        /* Divider gradient */
        .sidebar-divider {
            height: 1px;
            background: linear-gradient(90deg, transparent, #06b6d430, transparent);
        }

        /* Topbar blur */
        .topbar-blur {
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
        }

        /* Sidebar overlay backdrop */
        #sidebarOverlay {
            backdrop-filter: blur(2px);
            -webkit-backdrop-filter: blur(2px);
        }

        /* Content fade-in */
        .page-content {
            animation: fadeInUp 0.3s ease forwards;
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(8px); }
            to   { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>

<?php
/**
 * Guard & fallback variabel layout.
 * Setiap halaman admin SEHARUSNYA sudah set $adminUser dan $logoutCsrf
 * sebelum require layout ini. Blok ini hanya safety-net.
 */
if (!isset($auth) || !($auth instanceof AuthLogic)) {
    // Jika halaman lupa include Auth, buat instance baru dari session yang ada
    if (!isset($conn)) {
        require_once dirname(__DIR__, 2) . '/config.php';
    }
    if (!class_exists('AuthLogic')) {
        require_once dirname(__DIR__, 2) . '/Logic/Auth.php';
    }
    $auth = new AuthLogic($conn);
    $auth->requireLogin();
}

if (empty($adminUser) || !is_array($adminUser)) {
    $adminUser = $auth->getCurrentUser();
}

// Logout CSRF: pakai generateCsrfToken hanya jika belum ada dari halaman pemanggil.
// Jangan regenerate jika sudah di-set, karena itu akan menimpa token AJAX yang
// sudah dikirim ke browser (menyebabkan mismatch).
if (empty($logoutCsrf)) {
    $logoutCsrf = $auth->generateCsrfToken();
}
?>
<body class="font-sans bg-cyber-bg text-cyber-text min-h-screen">

    <!-- Cyber grid background -->
    <div class="cyber-grid fixed inset-0 pointer-events-none z-0"></div>

    <!-- Decorative glow blobs -->
    <div class="fixed top-0 left-64 w-96 h-48 bg-cyber-orange rounded-full opacity-[0.03] blur-3xl pointer-events-none z-0"></div>
    <div class="fixed bottom-0 right-0 w-80 h-80 bg-cyber-orange rounded-full opacity-[0.03] blur-3xl pointer-events-none z-0"></div>

    <!-- ═══════════════════════════════════════════════════════════
         SIDEBAR OVERLAY (mobile)
    ════════════════════════════════════════════════════════════ -->
    <div
        id="sidebarOverlay"
        class="fixed inset-0 bg-black/60 z-30 hidden lg:hidden"
        aria-hidden="true"
    ></div>

    <!-- ═══════════════════════════════════════════════════════════
         SIDEBAR
    ════════════════════════════════════════════════════════════ -->
    <aside
        id="sidebar"
        class="fixed top-0 left-0 h-full w-64 bg-cyber-sidebar border-r border-cyber-border
               shadow-sidebar z-40 flex flex-col overflow-y-auto
               -translate-x-full lg:translate-x-0 transition-transform duration-300 ease-in-out"
        aria-label="Navigasi Admin"
    >
        <!-- Brand / Logo -->
        <div class="flex items-center gap-3 px-5 py-5 border-b border-cyber-border flex-shrink-0">
            <div class="relative w-9 h-9 rounded-lg bg-cyber-card border border-cyber-border flex items-center justify-center flex-shrink-0">
                <div class="absolute inset-0 rounded-lg bg-cyber-orange opacity-10"></div>
                <img src="/assets/libix-logo.png" alt="Libix Technology" class="relative h-7 w-7 object-contain">
            </div>
            <div class="min-w-0">
                <div class="font-extrabold text-sm tracking-tight leading-none">
                    <span class="text-cyber-text">Libix</span><span class="text-cyber-orange text-glow"> Technology</span>
                </div>
                <div class="text-[10px] text-cyber-muted tracking-widest uppercase mt-0.5">Admin Panel</div>
            </div>
            <!-- Close button (mobile) -->
            <button
                id="sidebarClose"
                class="ml-auto text-cyber-muted hover:text-cyber-orange transition-colors lg:hidden"
                aria-label="Tutup sidebar"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <!-- User info -->
        <div class="px-4 py-4 border-b border-cyber-border flex-shrink-0">
            <div class="flex items-center gap-3 bg-cyber-card border border-cyber-border rounded-xl px-3 py-2.5">
                <div class="relative flex-shrink-0">
                    <div class="w-8 h-8 rounded-lg bg-cyber-orange/20 border border-cyber-orange/30 flex items-center justify-center">
                        <span class="text-cyber-orange font-bold text-sm">
                            <?= strtoupper(substr($adminUser['name'] ?? 'A', 0, 1)) ?>
                        </span>
                    </div>
                    <div class="absolute -bottom-0.5 -right-0.5 w-2.5 h-2.5 rounded-full bg-green-500 border-2 border-cyber-sidebar"></div>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-xs font-semibold text-cyber-text truncate">
                        <?= htmlspecialchars($adminUser['name'] ?? 'Admin', ENT_QUOTES, 'UTF-8') ?>
                    </p>
                    <p class="text-[10px] text-cyber-muted truncate">
                        @<?= htmlspecialchars($adminUser['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                    </p>
                </div>
                <div class="flex-shrink-0">
                    <div class="w-1.5 h-1.5 rounded-full bg-cyber-orange animate-pulse-slow"></div>
                </div>
            </div>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 px-3 py-4 space-y-1" aria-label="Menu utama">

            <!-- Label section -->
            <p class="px-3 mb-2 text-[10px] font-bold tracking-widest uppercase text-cyber-dim">Utama</p>

            <!-- Dashboard -->
            <a href="/Admin/dashboard"
               class="nav-item border-l-3 border-transparent flex items-center gap-3 px-3 py-2.5 rounded-r-xl rounded-l-none transition-all duration-150
                      <?= (basename($_SERVER['PHP_SELF']) === 'dashboard.php') ? 'nav-item-active' : 'border-l-[3px]' ?>">
                <svg class="nav-icon text-cyber-muted w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                <span class="nav-text text-sm text-cyber-muted">Dashboard</span>
            </a>

            <!-- Blog -->
            <a href="/Admin/blog"
               class="nav-item border-l-[3px] border-transparent flex items-center gap-3 px-3 py-2.5 rounded-r-xl rounded-l-none transition-all duration-150
                      <?= (basename($_SERVER['PHP_SELF']) === 'blog.php') ? 'nav-item-active' : '' ?>">
                <svg class="nav-icon text-cyber-muted w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <span class="nav-text text-sm text-cyber-muted">Blog</span>
            </a>

            <!-- Kategori -->
            <a href="/Admin/kategori"
               class="nav-item border-l-[3px] border-transparent flex items-center gap-3 px-3 py-2.5 rounded-r-xl rounded-l-none transition-all duration-150
                      <?= (basename($_SERVER['PHP_SELF']) === 'kategori.php') ? 'nav-item-active' : '' ?>">
                <svg class="nav-icon text-cyber-muted w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                <span class="nav-text text-sm text-cyber-muted">Kategori</span>
            </a>

            <!-- Ulasan -->
            <a href="/Admin/ulasan"
               class="nav-item border-l-[3px] border-transparent flex items-center gap-3 px-3 py-2.5 rounded-r-xl rounded-l-none transition-all duration-150
                      <?= (basename($_SERVER['PHP_SELF']) === 'ulasan.php') ? 'nav-item-active' : '' ?>">
                <svg class="nav-icon text-cyber-muted w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                <span class="nav-text text-sm text-cyber-muted">Ulasan</span>
            </a>

            <div class="sidebar-divider my-3 mx-3"></div>

            <p class="px-3 mb-2 text-[10px] font-bold tracking-widest uppercase text-cyber-dim">Akun</p>

            <!-- Profil -->
            <a href="/Admin/profile"
               class="nav-item border-l-[3px] border-transparent flex items-center gap-3 px-3 py-2.5 rounded-r-xl rounded-l-none transition-all duration-150
                      <?= (basename($_SERVER['PHP_SELF']) === 'profile.php') ? 'nav-item-active' : '' ?>">
                <svg class="nav-icon text-cyber-muted w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span class="nav-text text-sm text-cyber-muted">Profil</span>
            </a>

            <div class="sidebar-divider my-3 mx-3"></div>

            <!-- Logout -->
            <form method="POST" action="/Admin/logout" id="logoutForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($logoutCsrf ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <button
                    type="button"
                    onclick="confirmLogout()"
                    class="nav-item border-l-[3px] border-transparent w-full flex items-center gap-3 px-3 py-2.5 rounded-r-xl rounded-l-none transition-all duration-150
                           hover:bg-red-950/30 hover:border-red-800/50 group text-left"
                >
                    <svg class="nav-icon text-cyber-muted group-hover:text-red-400 w-5 h-5 flex-shrink-0 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                    <span class="nav-text text-sm text-cyber-muted group-hover:text-red-400 transition-colors">Logout</span>
                </button>
            </form>

        </nav>

        <!-- Sidebar footer -->
        <div class="px-4 py-3 border-t border-cyber-border flex-shrink-0">
            <div class="flex items-center gap-2 text-[10px] text-cyber-dim">
                <div class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse-slow"></div>
                <span>System online · <?= date('H:i') ?></span>
            </div>
        </div>
    </aside>

    <!-- ═══════════════════════════════════════════════════════════
         MAIN WRAPPER
    ════════════════════════════════════════════════════════════ -->
    <div class="lg:pl-64 flex flex-col min-h-screen relative z-10">

        <!-- ── TOPBAR ───────────────────────────────────────────── -->
        <header class="sticky top-0 z-20 topbar-blur bg-cyber-sidebar/80 border-b border-cyber-border flex-shrink-0">
            <div class="flex items-center justify-between h-14 px-4 sm:px-6">

                <!-- Kiri: hamburger (mobile) + breadcrumb -->
                <div class="flex items-center gap-3 min-w-0">
                    <!-- Hamburger (mobile only) -->
                    <button
                        id="sidebarToggle"
                        class="lg:hidden flex-shrink-0 w-9 h-9 flex items-center justify-center rounded-lg border border-cyber-border hover:border-cyber-orange text-cyber-muted hover:text-cyber-orange transition-all"
                        aria-label="Buka sidebar"
                        aria-expanded="false"
                        aria-controls="sidebar"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                    </button>

                    <!-- Breadcrumb / page title -->
                    <div class="min-w-0">
                        <h1 class="text-sm font-bold text-cyber-text truncate">
                            <?= htmlspecialchars($title ?? 'Dashboard', ENT_QUOTES, 'UTF-8') ?>
                        </h1>
                        <p class="text-[10px] text-cyber-muted truncate hidden sm:block">
                            Admin / <?= htmlspecialchars($title ?? 'Dashboard', ENT_QUOTES, 'UTF-8') ?>
                        </p>
                    </div>
                </div>

                <!-- Kanan: info + avatar -->
                <div class="flex items-center gap-2 sm:gap-3 flex-shrink-0">

                    <!-- Clock -->
                    <div class="hidden sm:flex items-center gap-1.5 text-[11px] text-cyber-muted font-mono
                                border border-cyber-border rounded-lg px-2.5 py-1.5 bg-cyber-card">
                        <svg class="w-3 h-3 text-cyber-orange" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span id="liveTime"></span>
                    </div>

                    <!-- Visit site link -->
                    <a href="/" target="_blank"
                       class="hidden sm:flex items-center gap-1.5 text-[11px] text-cyber-muted hover:text-cyber-orange
                              border border-cyber-border hover:border-cyber-orange rounded-lg px-2.5 py-1.5 bg-cyber-card transition-all"
                       title="Lihat Blog">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span>Lihat Blog</span>
                    </a>

                    <!-- Avatar / user menu trigger -->
                    <div class="relative" id="userMenuWrapper">
                        <button
                            id="userMenuBtn"
                            class="flex items-center gap-2 bg-cyber-card border border-cyber-border hover:border-cyber-orange
                                   rounded-xl px-2.5 py-1.5 transition-all group"
                            aria-label="Menu akun"
                            aria-expanded="false"
                            aria-haspopup="true"
                        >
                            <div class="w-6 h-6 rounded-lg bg-cyber-orange/20 border border-cyber-orange/30 flex items-center justify-center flex-shrink-0">
                                <span class="text-cyber-orange font-bold text-[10px]">
                                    <?= strtoupper(substr($adminUser['name'] ?? 'A', 0, 1)) ?>
                                </span>
                            </div>
                            <span class="text-xs text-cyber-text hidden sm:block max-w-[80px] truncate">
                                <?= htmlspecialchars($adminUser['name'] ?? 'Admin', ENT_QUOTES, 'UTF-8') ?>
                            </span>
                            <svg class="w-3 h-3 text-cyber-muted group-hover:text-cyber-orange transition-colors flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>

                        <!-- Dropdown menu -->
                        <div
                            id="userDropdown"
                            class="hidden absolute right-0 top-full mt-2 w-52 bg-cyber-card border border-cyber-border rounded-xl shadow-cyber overflow-hidden z-50"
                        >
                            <div class="px-4 py-3 border-b border-cyber-border">
                                <p class="text-xs font-semibold text-cyber-text truncate">
                                    <?= htmlspecialchars($adminUser['name'] ?? 'Admin', ENT_QUOTES, 'UTF-8') ?>
                                </p>
                                <p class="text-[11px] text-cyber-muted truncate">
                                    <?= htmlspecialchars($adminUser['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                </p>
                            </div>
                            <div class="py-1">
                                <a href="/Admin/profile"
                                   class="flex items-center gap-2.5 px-4 py-2 text-xs text-cyber-muted hover:text-cyber-orange hover:bg-cyber-orange/5 transition-all">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                    Profil Saya
                                </a>
                                <a href="/" target="_blank"
                                   class="flex items-center gap-2.5 px-4 py-2 text-xs text-cyber-muted hover:text-cyber-orange hover:bg-cyber-orange/5 transition-all">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    Lihat Blog
                                </a>
                            </div>
                            <div class="border-t border-cyber-border py-1">
                                <button
                                    onclick="confirmLogout()"
                                    class="w-full flex items-center gap-2.5 px-4 py-2 text-xs text-red-400 hover:bg-red-950/30 transition-all"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                    </svg>
                                    Logout
                                </button>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Progress bar accent -->
            <div class="h-[2px] bg-gradient-to-r from-transparent via-cyber-orange to-transparent opacity-40"></div>
        </header>

        <!-- ── PAGE CONTENT ─────────────────────────────────────── -->
        <main class="flex-1 p-4 sm:p-6 page-content" id="mainContent">
            <?php if (isset($content)) echo $content; ?>
        </main>

        <!-- ── FOOTER ───────────────────────────────────────────── -->
        <footer class="flex-shrink-0 border-t border-cyber-border px-4 sm:px-6 py-3">
            <div class="flex flex-col sm:flex-row items-center justify-between gap-2 text-[11px] text-cyber-dim">
                <span>
                    &copy; <?= date('Y') ?>
                    <span class="text-cyber-orange">Libix Technology</span>
                    · Admin Panel
                </span>
                <span class="flex items-center gap-1.5">
                    <div class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse-slow"></div>
                    All systems operational
                </span>
            </div>
        </footer>
    </div>

    <!-- ═══════════════════════════════════════════════════════════
         LOGOUT CONFIRM MODAL
    ════════════════════════════════════════════════════════════ -->
    <div
        id="logoutModal"
        class="hidden fixed inset-0 z-50 flex items-center justify-center px-4"
        role="dialog"
        aria-modal="true"
        aria-labelledby="logoutModalTitle"
    >
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" onclick="closeLogoutModal()"></div>

        <!-- Dialog -->
        <div class="relative bg-cyber-card border border-cyber-border rounded-2xl p-7 w-full max-w-sm shadow-cyber">
            <!-- Icon -->
            <div class="flex items-center justify-center w-14 h-14 rounded-xl bg-red-950/50 border border-red-800/40 mx-auto mb-5">
                <svg class="w-7 h-7 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                </svg>
            </div>

            <h2 id="logoutModalTitle" class="text-center text-base font-bold text-cyber-text mb-1">Konfirmasi Logout</h2>
            <p class="text-center text-sm text-cyber-muted mb-6">Kamu akan keluar dari panel admin. Yakin?</p>

            <div class="flex gap-3">
                <button
                    onclick="closeLogoutModal()"
                    class="flex-1 py-2.5 px-4 rounded-xl border border-cyber-border text-cyber-muted hover:text-cyber-text hover:border-cyber-dim text-sm font-semibold transition-all"
                >
                    Batal
                </button>
                <button
                    onclick="document.getElementById('logoutForm').submit()"
                    class="flex-1 py-2.5 px-4 rounded-xl bg-red-700 hover:bg-red-600 text-white text-sm font-bold transition-all"
                >
                    Ya, Logout
                </button>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════
         JAVASCRIPT
    ════════════════════════════════════════════════════════════ -->
    <script>
    (function () {

        /* ── Sidebar toggle (mobile) ──────────────────────── */
        const sidebar        = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const sidebarToggle  = document.getElementById('sidebarToggle');
        const sidebarClose   = document.getElementById('sidebarClose');

        function openSidebar() {
            sidebar.classList.remove('-translate-x-full');
            sidebarOverlay.classList.remove('hidden');
            sidebarToggle.setAttribute('aria-expanded', 'true');
            document.body.style.overflow = 'hidden';
        }

        function closeSidebar() {
            sidebar.classList.add('-translate-x-full');
            sidebarOverlay.classList.add('hidden');
            sidebarToggle.setAttribute('aria-expanded', 'false');
            document.body.style.overflow = '';
        }

        sidebarToggle?.addEventListener('click', openSidebar);
        sidebarClose?.addEventListener('click', closeSidebar);
        sidebarOverlay?.addEventListener('click', closeSidebar);

        // Keyboard: ESC menutup sidebar di mobile
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeSidebar();
                closeLogoutModal();
                closeUserDropdown();
            }
        });

        /* ── Live clock ───────────────────────────────────── */
        const liveTime = document.getElementById('liveTime');
        if (liveTime) {
            function updateClock() {
                const now = new Date();
                liveTime.textContent = now.toLocaleTimeString('id-ID', {
                    hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false
                });
            }
            updateClock();
            setInterval(updateClock, 1000);
        }

        /* ── User dropdown ────────────────────────────────── */
        const userMenuBtn  = document.getElementById('userMenuBtn');
        const userDropdown = document.getElementById('userDropdown');

        function closeUserDropdown() {
            userDropdown?.classList.add('hidden');
            userMenuBtn?.setAttribute('aria-expanded', 'false');
        }

        userMenuBtn?.addEventListener('click', (e) => {
            e.stopPropagation();
            const isHidden = userDropdown.classList.contains('hidden');
            userDropdown.classList.toggle('hidden', !isHidden);
            userMenuBtn.setAttribute('aria-expanded', String(isHidden));
        });

        document.addEventListener('click', (e) => {
            if (!document.getElementById('userMenuWrapper')?.contains(e.target)) {
                closeUserDropdown();
            }
        });

        /* ── Logout modal ─────────────────────────────────── */
        const logoutModal = document.getElementById('logoutModal');

        window.confirmLogout = function () {
            logoutModal.classList.remove('hidden');
            closeUserDropdown();
        };

        window.closeLogoutModal = function () {
            logoutModal.classList.add('hidden');
        };

    })();
    </script>

    <!-- Loading overlay -->
    <script src="/loading.js"></script>

</body>
</html>
