<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/Logic/Auth.php';

$auth = new AuthLogic($conn);
if ($auth->isLoggedIn()) {
    header('Location: /Admin/dashboard');
    exit();
}

$error   = '';
$success = '';

// ── Proses POST ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    $password   = $_POST['password'] ?? '';
    $csrfToken  = $_POST['csrf_token'] ?? '';

    $result = $auth->login($identifier, $password, $csrfToken);

    if ($result['success']) {
        header('Location: /Admin/dashboard');
        exit();
    }

    $error = AuthLogic::e($result['message']);
}

// Generate CSRF token baru untuk form
$csrfToken = $auth->generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login  |  Flavory.id</title>

    <link rel="icon" type="image/png" sizes="32x32" href="/assets/logo.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/logo.png">
    <link rel="apple-touch-icon" href="/assets/logo.png">

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">

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
                        cyber: {
                            bg:      '#0a0a0f',
                            card:    '#0f0f1a',
                            border:  '#1a1a2e',
                            panel:   '#12121f',
                            orange:  '#f97316',
                            orangeL: '#fb923c',
                            orangeD: '#ea580c',
                            glow:    '#f9731640',
                            text:    '#e2e8f0',
                            muted:   '#64748b',
                            dim:     '#334155',
                        },
                    },
                    boxShadow: {
                        'cyber':       '0 0 20px #f9731630, 0 0 60px #f9731610',
                        'cyber-sm':    '0 0 10px #f9731625',
                        'cyber-input': '0 0 0 2px #f9731650',
                    },
                    animation: {
                        'pulse-slow': 'pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite',
                        'flicker':    'flicker 4s linear infinite',
                        'scan':       'scan 6s linear infinite',
                    },
                    keyframes: {
                        flicker: {
                            '0%, 95%, 100%': { opacity: '1' },
                            '96%':           { opacity: '0.8' },
                            '97%':           { opacity: '1' },
                            '98%':           { opacity: '0.7' },
                            '99%':           { opacity: '1' },
                        },
                        scan: {
                            '0%':   { transform: 'translateY(-100%)' },
                            '100%': { transform: 'translateY(100vh)' },
                        },
                    },
                },
            },
        }
    </script>

    <style>
        /* Grid cyber background */
        .cyber-grid {
            background-image:
                linear-gradient(rgba(249, 115, 22, 0.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(249, 115, 22, 0.04) 1px, transparent 1px);
            background-size: 50px 50px;
        }

        /* Glow efek teks */
        .text-glow {
            text-shadow: 0 0 20px #f9731680, 0 0 40px #f9731640;
        }

        /* Scan line */
        .scan-line {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, #f97316, transparent);
            animation: scan 6s linear infinite;
            pointer-events: none;
            opacity: 0.3;
            z-index: 9999;
        }

        /* Corner bracket */
        .corner-tl::before,
        .corner-br::after {
            content: '';
            position: absolute;
            width: 16px;
            height: 16px;
            border-color: #f97316;
            border-style: solid;
        }
        .corner-tl::before {
            top: -1px; left: -1px;
            border-width: 2px 0 0 2px;
        }
        .corner-br::after {
            bottom: -1px; right: -1px;
            border-width: 0 2px 2px 0;
        }

        /* Input focus glow */
        .cyber-input:focus {
            box-shadow: 0 0 0 2px #f9731650, 0 0 12px #f9731630;
        }

        /* Button glow hover */
        .cyber-btn:hover {
            box-shadow: 0 0 20px #f9731650, 0 0 40px #f9731620;
        }

        /* Scrollbar custom */
        ::-webkit-scrollbar { width: 4px; }
        ::-webkit-scrollbar-track { background: #0a0a0f; }
        ::-webkit-scrollbar-thumb { background: #f97316; border-radius: 2px; }
    </style>
</head>

<body class="font-sans bg-cyber-bg text-cyber-text min-h-screen flex items-center justify-center overflow-hidden relative">

    <!-- Scan line animasi -->
    <div class="scan-line"></div>

    <!-- Background grid -->
    <div class="cyber-grid fixed inset-0 pointer-events-none"></div>

    <!-- Decorative glows -->
    <div class="fixed top-1/4 -left-32 w-72 h-72 bg-cyber-orange rounded-full opacity-5 blur-3xl pointer-events-none"></div>
    <div class="fixed bottom-1/4 -right-32 w-72 h-72 bg-cyber-orange rounded-full opacity-5 blur-3xl pointer-events-none"></div>
    <div class="fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-96 h-96 bg-cyber-orange rounded-full opacity-[0.03] blur-3xl pointer-events-none"></div>

    <!-- Main container -->
    <div class="relative z-10 w-full max-w-md mx-auto px-4 py-8">

        <!-- Logo & judul -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-xl bg-cyber-card border border-cyber-border mb-4 shadow-cyber relative">
                <div class="absolute inset-0 rounded-xl bg-cyber-orange opacity-10"></div>
                <span class="relative text-cyber-orange font-extrabold text-2xl text-glow animate-flicker">F</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight">
                <span class="text-cyber-text">Flavory</span><span class="text-cyber-orange text-glow">.id</span>
            </h1>
            <p class="text-cyber-muted text-sm mt-1 tracking-widest uppercase">Admin Control Panel</p>
        </div>

        <!-- Card form -->
        <div class="corner-tl corner-br relative bg-cyber-card border border-cyber-border rounded-2xl p-7 sm:p-8 shadow-cyber">

            <!-- Header card -->
            <div class="mb-7">
                <div class="flex items-center gap-2 mb-1">
                    <div class="w-2 h-2 rounded-full bg-cyber-orange animate-pulse-slow"></div>
                    <div class="w-1.5 h-1.5 rounded-full bg-cyber-orangeL opacity-60 animate-pulse-slow" style="animation-delay:.3s"></div>
                    <div class="w-1 h-1 rounded-full bg-cyber-orangeD opacity-40 animate-pulse-slow" style="animation-delay:.6s"></div>
                </div>
                <h2 class="text-lg font-bold text-cyber-text mt-3">Masuk ke Dashboard</h2>
                <p class="text-cyber-muted text-sm mt-1">Autentikasi diperlukan untuk melanjutkan</p>
            </div>

            <!-- Alert error -->
            <?php if ($error !== ''): ?>
                <div class="mb-5 flex items-start gap-3 bg-red-950/50 border border-red-800/60 rounded-xl px-4 py-3" role="alert">
                    <svg class="w-4 h-4 text-red-400 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                    </svg>
                    <p class="text-red-300 text-sm leading-relaxed"><?= $error ?></p>
                </div>
            <?php endif; ?>

            <!-- Form login -->
            <form method="POST" action="/login.php" novalidate class="space-y-5" autocomplete="off">

                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?= AuthLogic::e($csrfToken) ?>">

                <!-- Identifier (username / email) -->
                <div>
                    <label for="identifier" class="block text-xs font-semibold uppercase tracking-widest text-cyber-muted mb-2">
                        Username atau Email
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                            <i class="devicon-github-original text-cyber-orange text-base opacity-70"></i>
                        </div>
                        <input
                            type="text"
                            id="identifier"
                            name="identifier"
                            required
                            autocomplete="off"
                            spellcheck="false"
                            maxlength="191"
                            value="<?= AuthLogic::e($_POST['identifier'] ?? '') ?>"
                            placeholder="thooms atau email@domain.com"
                            class="cyber-input w-full bg-cyber-panel border border-cyber-border rounded-xl
                                   pl-10 pr-4 py-3 text-sm text-cyber-text placeholder-cyber-dim
                                   focus:outline-none focus:border-cyber-orange transition-all duration-200"
                        >
                    </div>
                </div>

                <!-- Password -->
                <div>
                    <label for="password" class="block text-xs font-semibold uppercase tracking-widest text-cyber-muted mb-2">
                        Password
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                            <i class="devicon-ssh-plain text-cyber-orange text-base opacity-70"></i>
                        </div>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            required
                            autocomplete="current-password"
                            maxlength="256"
                            placeholder="••••••••••••"
                            class="cyber-input w-full bg-cyber-panel border border-cyber-border rounded-xl
                                   pl-10 pr-12 py-3 text-sm text-cyber-text placeholder-cyber-dim
                                   focus:outline-none focus:border-cyber-orange transition-all duration-200"
                        >
                        <!-- Toggle show/hide password -->
                        <button
                            type="button"
                            id="togglePassword"
                            aria-label="Tampilkan / sembunyikan password"
                            class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-cyber-muted hover:text-cyber-orange transition-colors"
                        >
                            <svg id="eyeOpen" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7
                                         -1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            <svg id="eyeClosed" class="w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7
                                         a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878
                                         l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59
                                         m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0
                                         01-4.132 5.411m0 0L21 21"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Divider -->
                <div class="flex items-center gap-3 my-1">
                    <div class="flex-1 h-px bg-cyber-border"></div>
                    <span class="text-cyber-dim text-xs tracking-widest">AUTHENTICATE</span>
                    <div class="flex-1 h-px bg-cyber-border"></div>
                </div>

                <!-- Submit button -->
                <button
                    type="submit"
                    id="submitBtn"
                    class="cyber-btn w-full bg-cyber-orange hover:bg-cyber-orangeL active:bg-cyber-orangeD
                           text-white font-bold py-3.5 px-6 rounded-xl text-sm tracking-wider uppercase
                           transition-all duration-200 flex items-center justify-center gap-2
                           disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <i class="devicon-linux-plain text-base"></i>
                    <span id="btnLabel">Masuk ke Panel</span>
                    <svg id="btnSpinner" class="hidden w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                </button>

            </form>

            <!-- Footer info -->
            <div class="mt-6 pt-5 border-t border-cyber-border flex items-center justify-between text-xs text-cyber-dim">
                <span class="flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 text-cyber-orange" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                    </svg>
                    Sesi terenkripsi & aman
                </span>
                <span class="text-cyber-dim/60">v2.0 · <?= date('Y') ?></span>
            </div>
        </div>

        <!-- Back to site -->
        <div class="text-center mt-6">
            <a href="/" class="text-cyber-muted hover:text-cyber-orange text-xs tracking-wide transition-colors inline-flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Kembali ke Blog
            </a>
        </div>

    </div>

    <!-- ── JavaScript ──────────────────────────────────────────────────────── -->
    <script>
    (function () {
        // Toggle show/hide password
        const toggleBtn  = document.getElementById('togglePassword');
        const pwInput    = document.getElementById('password');
        const eyeOpen    = document.getElementById('eyeOpen');
        const eyeClosed  = document.getElementById('eyeClosed');

        toggleBtn.addEventListener('click', () => {
            const isText = pwInput.type === 'text';
            pwInput.type = isText ? 'password' : 'text';
            eyeOpen.classList.toggle('hidden', !isText);
            eyeClosed.classList.toggle('hidden', isText);
        });

        // Loading state saat submit
        const form      = document.querySelector('form');
        const submitBtn = document.getElementById('submitBtn');
        const btnLabel  = document.getElementById('btnLabel');
        const spinner   = document.getElementById('btnSpinner');

        form.addEventListener('submit', () => {
            submitBtn.disabled    = true;
            btnLabel.textContent  = 'Mengautentikasi...';
            spinner.classList.remove('hidden');
        });

        // Auto-focus identifier input
        document.getElementById('identifier').focus();
    })();
    </script>

    <!-- Loading overlay -->
    <script src="/loading.js"></script>

</body>
</html>
