<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../Logic/Auth.php';
require_once __DIR__ . '/../Logic/Admin/Profile.php';

$auth = new AuthLogic($conn);
$auth->requireLogin();

$adminUser      = $auth->getCurrentUser();
$logoutCsrf     = $auth->generateCsrfToken();
$profileLogic   = new ProfileAdminLogic($conn);

$adminId = (int) ($adminUser['id'] ?? 0);
$profile = $profileLogic->getById($adminId);

/* ── Helper: ikon mata ───────────────────────────────────────────────────── */
function eyeIcon(): string {
    return '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7
                 -1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
    </svg>';
}

/* ── Pesan flash dari POST redirect ──────────────────────────────────────── */
$flashSuccess = '';
$flashError   = '';
if (!empty($_SESSION['profile_success'])) {
    $flashSuccess = $_SESSION['profile_success'];
    unset($_SESSION['profile_success']);
}
if (!empty($_SESSION['profile_error'])) {
    $flashError = $_SESSION['profile_error'];
    unset($_SESSION['profile_error']);
}

/* ── Handle POST (form submit biasa, bukan AJAX) ─────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action    = $_POST['action'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';

    // Validasi CSRF  |  pakai ajax token (persist) agar kedua form bisa submit
    if (!$auth->validateAjaxCsrfToken($csrfToken)) {
        $_SESSION['profile_error'] = 'Token tidak valid. Muat ulang halaman dan coba lagi.';
        header('Location: /Admin/profile');
        exit();
    }

    if ($action === 'update_name') {
        $newName = trim($_POST['name'] ?? '');
        $result  = $profileLogic->updateName($adminId, $newName);

        if ($result['success']) {
            // Update session agar nama di navbar langsung berubah
            $_SESSION['admin_name'] = $result['name'];
            $_SESSION['profile_success'] = $result['message'];
        } else {
            $_SESSION['profile_error'] = $result['message'];
        }

    } elseif ($action === 'update_username') {
        $newUsername = trim($_POST['username'] ?? '');
        $result      = $profileLogic->updateUsername($adminId, $newUsername);

        if ($result['success']) {
            // Update session agar navbar langsung berubah
            $_SESSION['admin_username'] = $result['username'];
            $_SESSION['profile_success'] = $result['message'];
        } else {
            $_SESSION['profile_error'] = $result['message'];
        }

    } elseif ($action === 'update_password') {
        $oldPw  = $_POST['old_password']     ?? '';
        $newPw  = $_POST['new_password']     ?? '';
        $confPw = $_POST['confirm_password'] ?? '';
        $result = $profileLogic->updatePassword($adminId, $oldPw, $newPw, $confPw);

        if ($result['success']) {
            $_SESSION['profile_success'] = $result['message'];
        } else {
            $_SESSION['profile_error'] = $result['message'];
        }
    }

    // PRG  |  redirect agar tidak double-submit saat refresh
    header('Location: /Admin/profile');
    exit();
}

/* ── Generate fresh CSRF token untuk form ────────────────────────────────── */
// Pakai token persist (getAjaxCsrfToken) agar kedua form di halaman ini
// bisa pakai token yang sama tanpa saling menimpa.
$formCsrf = $auth->getAjaxCsrfToken();
$title    = 'Profil';

ob_start();
?>

<!-- ══════════════════════════════════════════════════════════════
     HEADER
═══════════════════════════════════════════════════════════════ -->
<div class="mb-6">
    <p class="text-xs text-cyber-muted tracking-widest uppercase mb-1">Pengaturan Akun</p>
    <h2 class="text-xl sm:text-2xl font-extrabold text-cyber-text">Profil Admin</h2>
    <p class="text-cyber-muted text-sm mt-1">Kelola nama, username, dan password akun kamu.</p>
</div>

<!-- Flash messages -->
<?php if ($flashSuccess): ?>
    <div id="flashOk" class="mb-5 flex items-center gap-3 bg-green-950/50 border border-green-700/50 rounded-xl px-4 py-3" role="alert">
        <svg class="w-4 h-4 text-green-400 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
        </svg>
        <p class="text-green-300 text-sm font-medium"><?= htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8') ?></p>
    </div>
    <script>setTimeout(()=>document.getElementById('flashOk')?.remove(), 4000);</script>
<?php endif; ?>

<?php if ($flashError): ?>
    <div id="flashErr" class="mb-5 flex items-center gap-3 bg-red-950/50 border border-red-800/60 rounded-xl px-4 py-3" role="alert">
        <svg class="w-4 h-4 text-red-400 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
        </svg>
        <p class="text-red-300 text-sm font-medium"><?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?></p>
    </div>
    <script>setTimeout(()=>document.getElementById('flashErr')?.remove(), 6000);</script>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

    <!-- ── Kolom kiri: Info akun (read-only) ──────────────── -->
    <div class="space-y-5">

        <!-- Avatar card -->
        <div class="bg-cyber-card border border-cyber-border rounded-2xl p-6 relative overflow-hidden">
            <div class="absolute top-0 left-0 right-0 h-[2px] bg-gradient-to-r from-transparent via-cyber-orange to-transparent opacity-60"></div>

            <!-- Avatar -->
            <div class="flex flex-col items-center text-center">
                <div class="w-20 h-20 rounded-2xl bg-cyber-orange/15 border-2 border-cyber-orange/30
                            flex items-center justify-center mb-4 relative">
                    <span class="text-cyber-orange font-extrabold text-3xl">
                        <?= strtoupper(substr($profile['name'] ?? 'A', 0, 1)) ?>
                    </span>
                    <div class="absolute -bottom-1 -right-1 w-4 h-4 rounded-full bg-green-500
                                border-2 border-cyber-card"></div>
                </div>

                <h3 class="text-base font-bold text-cyber-text">
                    <?= htmlspecialchars($profile['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                </h3>
                <p class="text-xs text-cyber-muted mt-0.5">
                    @<?= htmlspecialchars($profile['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                </p>
                <p class="text-[11px] text-cyber-dim mt-1">
                    <?= htmlspecialchars($profile['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                </p>
            </div>

            <!-- Divider -->
            <div class="my-5 h-px bg-cyber-border"></div>

            <!-- Detail info -->
            <div class="space-y-3 text-sm">
                <div class="flex items-center justify-between">
                    <span class="text-cyber-muted text-xs">Nama</span>
                    <span class="text-cyber-text font-medium text-xs">
                        <?= htmlspecialchars($profile['name'] ?? ' | ', ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-cyber-muted text-xs">Username</span>
                    <span class="text-cyber-orange font-mono text-xs">
                        @<?= htmlspecialchars($profile['username'] ?? ' | ', ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-cyber-muted text-xs">Email</span>
                    <span class="text-cyber-text font-medium text-xs truncate max-w-[140px]" title="<?= htmlspecialchars($profile['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($profile['email'] ?? ' | ', ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-cyber-muted text-xs">Bergabung</span>
                    <span class="text-cyber-text text-xs">
                        <?= !empty($profile['createdAt']) ? date('d M Y', strtotime($profile['createdAt'])) : ' | ' ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Info tips -->
        <div class="bg-cyber-card border border-cyber-border rounded-2xl p-4">
            <div class="flex items-start gap-3">
                <div class="w-7 h-7 rounded-lg bg-cyber-orange/10 border border-cyber-orange/20
                            flex items-center justify-center flex-shrink-0 mt-0.5">
                    <svg class="w-3.5 h-3.5 text-cyber-orange" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <div>
                    <p class="text-xs font-semibold text-cyber-text mb-1">Info</p>
                    <p class="text-[11px] text-cyber-muted leading-relaxed">
                        Kamu bisa ubah nama, username, password, atau ketiganya secara terpisah. Email tidak dapat diubah dari panel ini.
                    </p>
                </div>
            </div>
        </div>

    </div>

    <!-- ── Kolom kanan: Form edit ──────────────────────────── -->
    <div class="lg:col-span-2 space-y-5">

        <!-- ════════════════════════════════════════
             FORM: UBAH NAMA
        ════════════════════════════════════════ -->
        <div class="bg-cyber-card border border-cyber-border rounded-2xl overflow-hidden">

            <!-- Card header -->
            <div class="flex items-center gap-3 px-6 py-4 border-b border-cyber-border bg-cyber-panel">
                <div class="w-8 h-8 rounded-lg bg-green-500/10 border border-green-500/20
                            flex items-center justify-center">
                    <svg class="w-4 h-4 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-cyber-text">Ubah Nama</h3>
                    <p class="text-[11px] text-cyber-muted">Nama tampilan · 2–100 karakter</p>
                </div>
            </div>

            <form method="POST" action="/Admin/profile" class="px-6 py-5" novalidate>
                <input type="hidden" name="action"     value="update_name">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($formCsrf, ENT_QUOTES, 'UTF-8') ?>">

                <div class="mb-5">
                    <div class="flex items-center justify-between mb-2">
                        <label for="inputName"
                               class="text-xs font-bold uppercase tracking-widest text-cyber-muted">
                            Nama Baru <span class="text-red-400">*</span>
                        </label>
                        <span class="text-[11px] font-mono">
                            <span id="nameCount" class="text-cyber-orange font-bold">0</span>
                            <span class="text-cyber-dim">/100</span>
                        </span>
                    </div>
                    <input
                        type="text"
                        id="inputName"
                        name="name"
                        required
                        autocomplete="name"
                        maxlength="100"
                        value="<?= htmlspecialchars($profile['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        placeholder="Nama lengkap kamu"
                        class="w-full bg-cyber-panel border border-cyber-border rounded-xl
                               px-4 py-3 text-sm text-cyber-text placeholder-cyber-dim
                               focus:outline-none focus:border-cyber-orange transition-all
                               focus:shadow-[0_0_0_2px_#f9731630]"
                    >
                    <!-- Progress bar karakter -->
                    <div class="mt-2 h-1 bg-cyber-border rounded-full overflow-hidden">
                        <div id="nameBar" class="h-full rounded-full transition-all duration-200 bg-green-500" style="width:0%"></div>
                    </div>
                </div>

                <button type="submit"
                        class="inline-flex items-center gap-2 bg-cyber-orange hover:bg-cyber-orangeL
                               active:bg-cyber-orangeD text-white font-bold py-2.5 px-5 rounded-xl
                               text-sm transition-all"
                        style="box-shadow:0 0 16px #f9731630">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Simpan Nama
                </button>
            </form>
        </div>

        <!-- ════════════════════════════════════════
             FORM: UBAH USERNAME
        ════════════════════════════════════════ -->
        <div class="bg-cyber-card border border-cyber-border rounded-2xl overflow-hidden">
            <div class="absolute"></div>

            <!-- Card header -->
            <div class="flex items-center gap-3 px-6 py-4 border-b border-cyber-border bg-cyber-panel">
                <div class="w-8 h-8 rounded-lg bg-blue-500/10 border border-blue-500/20
                            flex items-center justify-center">
                    <i class="devicon-github-original text-blue-400 text-base"></i>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-cyber-text">Ubah Username</h3>
                    <p class="text-[11px] text-cyber-muted">Huruf, angka, underscore, dash · 3–50 karakter</p>
                </div>
            </div>

            <form method="POST" action="/Admin/profile" class="px-6 py-5" novalidate>
                <input type="hidden" name="action"     value="update_username">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($formCsrf, ENT_QUOTES, 'UTF-8') ?>">

                <div class="mb-5">
                    <label for="inputUsername"
                           class="block text-xs font-bold uppercase tracking-widest text-cyber-muted mb-2">
                        Username Baru <span class="text-red-400">*</span>
                    </label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none
                                     text-cyber-muted font-mono text-sm">@</span>
                        <input
                            type="text"
                            id="inputUsername"
                            name="username"
                            required
                            autocomplete="username"
                            maxlength="50"
                            value="<?= htmlspecialchars($profile['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                            placeholder="username_baru"
                            class="w-full bg-cyber-panel border border-cyber-border rounded-xl
                                   pl-9 pr-4 py-3 text-sm text-cyber-text placeholder-cyber-dim
                                   focus:outline-none focus:border-cyber-orange transition-all
                                   focus:shadow-[0_0_0_2px_#f9731630]"
                        >
                    </div>
                </div>

                <button type="submit"
                        class="inline-flex items-center gap-2 bg-cyber-orange hover:bg-cyber-orangeL
                               active:bg-cyber-orangeD text-white font-bold py-2.5 px-5 rounded-xl
                               text-sm transition-all"
                        style="box-shadow:0 0 16px #f9731630">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Simpan Username
                </button>
            </form>
        </div>

        <!-- ════════════════════════════════════════
             FORM: UBAH PASSWORD
        ════════════════════════════════════════ -->
        <div class="bg-cyber-card border border-cyber-border rounded-2xl overflow-hidden">

            <!-- Card header -->
            <div class="flex items-center gap-3 px-6 py-4 border-b border-cyber-border bg-cyber-panel">
                <div class="w-8 h-8 rounded-lg bg-cyber-orange/10 border border-cyber-orange/20
                            flex items-center justify-center">
                    <svg class="w-4 h-4 text-cyber-orange" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-cyber-text">Ubah Password</h3>
                    <p class="text-[11px] text-cyber-muted">Minimal 8 karakter · wajib verifikasi password lama</p>
                </div>
            </div>

            <form method="POST" action="/Admin/profile" class="px-6 py-5 space-y-4" novalidate
                  id="formPassword">
                <input type="hidden" name="action"     value="update_password">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($formCsrf, ENT_QUOTES, 'UTF-8') ?>">

                <!-- Password lama -->
                <div>
                    <label for="oldPassword"
                           class="block text-xs font-bold uppercase tracking-widest text-cyber-muted mb-2">
                        Password Lama <span class="text-red-400">*</span>
                    </label>
                    <div class="relative">
                        <input
                            type="password"
                            id="oldPassword"
                            name="old_password"
                            required
                            autocomplete="current-password"
                            maxlength="256"
                            placeholder="••••••••"
                            class="pw-input w-full bg-cyber-panel border border-cyber-border rounded-xl
                                   px-4 pr-12 py-3 text-sm text-cyber-text placeholder-cyber-dim
                                   focus:outline-none focus:border-cyber-orange transition-all
                                   focus:shadow-[0_0_0_2px_#f9731630]"
                        >
                        <button type="button"
                                class="pw-toggle absolute inset-y-0 right-0 pr-3.5 flex items-center
                                       text-cyber-muted hover:text-cyber-orange transition-colors"
                                aria-label="Tampilkan / sembunyikan password lama">
                            <?= eyeIcon() ?>
                        </button>
                    </div>
                </div>

                <!-- Password baru -->
                <div>
                    <label for="newPassword"
                           class="block text-xs font-bold uppercase tracking-widest text-cyber-muted mb-2">
                        Password Baru <span class="text-red-400">*</span>
                    </label>
                    <div class="relative">
                        <input
                            type="password"
                            id="newPassword"
                            name="new_password"
                            required
                            autocomplete="new-password"
                            minlength="8"
                            maxlength="256"
                            placeholder="••••••••"
                            class="pw-input w-full bg-cyber-panel border border-cyber-border rounded-xl
                                   px-4 pr-12 py-3 text-sm text-cyber-text placeholder-cyber-dim
                                   focus:outline-none focus:border-cyber-orange transition-all
                                   focus:shadow-[0_0_0_2px_#f9731630]"
                        >
                        <button type="button"
                                class="pw-toggle absolute inset-y-0 right-0 pr-3.5 flex items-center
                                       text-cyber-muted hover:text-cyber-orange transition-colors"
                                aria-label="Tampilkan / sembunyikan password baru">
                            <?= eyeIcon() ?>
                        </button>
                    </div>
                    <!-- Strength meter -->
                    <div class="mt-2 space-y-1">
                        <div class="h-1 bg-cyber-border rounded-full overflow-hidden">
                            <div id="strengthBar" class="h-full rounded-full transition-all duration-300 w-0"></div>
                        </div>
                        <p id="strengthLabel" class="text-[10px] text-cyber-dim"></p>
                    </div>
                </div>

                <!-- Konfirmasi password baru -->
                <div>
                    <label for="confirmPassword"
                           class="block text-xs font-bold uppercase tracking-widest text-cyber-muted mb-2">
                        Konfirmasi Password Baru <span class="text-red-400">*</span>
                    </label>
                    <div class="relative">
                        <input
                            type="password"
                            id="confirmPassword"
                            name="confirm_password"
                            required
                            autocomplete="new-password"
                            maxlength="256"
                            placeholder="••••••••"
                            class="pw-input w-full bg-cyber-panel border border-cyber-border rounded-xl
                                   px-4 pr-12 py-3 text-sm text-cyber-text placeholder-cyber-dim
                                   focus:outline-none focus:border-cyber-orange transition-all
                                   focus:shadow-[0_0_0_2px_#f9731630]"
                        >
                        <button type="button"
                                class="pw-toggle absolute inset-y-0 right-0 pr-3.5 flex items-center
                                       text-cyber-muted hover:text-cyber-orange transition-colors"
                                aria-label="Tampilkan / sembunyikan konfirmasi password">
                            <?= eyeIcon() ?>
                        </button>
                    </div>
                    <p id="matchLabel" class="text-[11px] mt-1 hidden"></p>
                </div>

                <div class="pt-1">
                    <button type="submit"
                            id="btnSavePassword"
                            class="inline-flex items-center gap-2 bg-cyber-orange hover:bg-cyber-orangeL
                                   active:bg-cyber-orangeD text-white font-bold py-2.5 px-5 rounded-xl
                                   text-sm transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                            style="box-shadow:0 0 16px #f9731630">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Simpan Password
                    </button>
                </div>
            </form>
        </div>

    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════
     JAVASCRIPT
═══════════════════════════════════════════════════════════════ -->
<script>
(function () {

    /* ── Counter karakter nama ────────────────────────────── */
    const nameInput = document.getElementById('inputName');
    const nameCount = document.getElementById('nameCount');
    const nameBar   = document.getElementById('nameBar');

    function updateNameCounter() {
        if (!nameInput) return;
        const len = nameInput.value.length;
        nameCount.textContent    = len;
        nameBar.style.width      = Math.min((len / 100) * 100, 100) + '%';
        nameBar.style.background = len >= 100 ? '#ef4444' : len >= 80 ? '#eab308' : '#22c55e';
        nameCount.style.color    = len >= 100 ? '#ef4444' : len >= 80 ? '#eab308' : '';
    }

    nameInput?.addEventListener('input', updateNameCounter);
    updateNameCounter(); // inisialisasi nilai awal

    /* ── Toggle show/hide password (semua field) ──────────── */
    document.querySelectorAll('.pw-toggle').forEach(btn => {
        btn.addEventListener('click', () => {
            const input = btn.previousElementSibling;
            const isText = input.type === 'text';
            input.type = isText ? 'password' : 'text';

            // Tukar ikon
            btn.innerHTML = isText ? eyeOpenHtml() : eyeClosedHtml();
        });
    });

    function eyeOpenHtml() {
        return `<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7
                     -1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
        </svg>`;
    }

    function eyeClosedHtml() {
        return `<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7
                     a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243
                     M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29
                     M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7
                     a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
        </svg>`;
    }

    /* ── Password strength meter ──────────────────────────── */
    const newPwInput   = document.getElementById('newPassword');
    const strengthBar  = document.getElementById('strengthBar');
    const strengthLbl  = document.getElementById('strengthLabel');

    newPwInput?.addEventListener('input', () => {
        const val    = newPwInput.value;
        const score  = calcStrength(val);
        const pct    = score * 25;

        const colors = ['', '#ef4444', '#f97316', '#eab308', '#22c55e'];
        const labels = ['', 'Sangat Lemah', 'Lemah', 'Sedang', 'Kuat'];

        strengthBar.style.width      = pct + '%';
        strengthBar.style.background = colors[score] ?? '';
        strengthLbl.textContent      = val.length > 0 ? labels[score] : '';
        strengthLbl.style.color      = colors[score] ?? '';
    });

    function calcStrength(pw) {
        if (pw.length === 0) return 0;
        let s = 0;
        if (pw.length >= 8)             s++;
        if (/[A-Z]/.test(pw))           s++;
        if (/[0-9]/.test(pw))           s++;
        if (/[^A-Za-z0-9]/.test(pw))    s++;
        return Math.min(s, 4);
    }

    /* ── Konfirmasi password match check ─────────────────── */
    const confirmInput = document.getElementById('confirmPassword');
    const matchLabel   = document.getElementById('matchLabel');

    function checkMatch() {
        const match = newPwInput.value === confirmInput.value;
        if (confirmInput.value.length === 0) {
            matchLabel.classList.add('hidden');
            return;
        }
        matchLabel.classList.remove('hidden');
        if (match) {
            matchLabel.textContent  = '✓ Password cocok';
            matchLabel.style.color  = '#22c55e';
        } else {
            matchLabel.textContent  = '✗ Password tidak cocok';
            matchLabel.style.color  = '#ef4444';
        }
    }

    newPwInput?.addEventListener('input', checkMatch);
    confirmInput?.addEventListener('input', checkMatch);

    /* ── Scroll ke flash message jika ada ────────────────── */
    const flash = document.getElementById('flashOk') || document.getElementById('flashErr');
    if (flash) flash.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

})();
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/Admin/app.blade.php';
?>
