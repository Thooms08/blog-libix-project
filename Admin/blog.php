<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../Logic/Auth.php';
require_once __DIR__ . '/../Logic/Admin/Blog.php';

$auth = new AuthLogic($conn);
$auth->requireLogin();

$adminUser  = $auth->getCurrentUser();
$logoutCsrf = $auth->generateCsrfToken();

$blogLogic = new BlogAdminLogic($conn);

/* ── Handle AJAX DELETE ───────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    header('Content-Type: application/json; charset=utf-8');

    $postId    = (int) ($_POST['id'] ?? 0);
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!$auth->validateAjaxCsrfToken($csrfToken)) {
        echo json_encode(['success' => false, 'message' => 'Token tidak valid.']);
        exit();
    }

    if ($postId <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID artikel tidak valid.']);
        exit();
    }

    $result = $blogLogic->delete($postId);
    echo json_encode($result);
    exit();
}

/* ── Data ─────────────────────────────────────────────────────────────────── */
$posts = $blogLogic->getAll();

/* ── Flash message dari redirect ─────────────────────────────────────────── */
$flash = '';
if (!empty($_SESSION['flash_success'])) {
    $flash = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}

$title      = 'Kelola Blog';
$deleteCsrf = $auth->getAjaxCsrfToken();   // token persist untuk AJAX delete

ob_start();
?>

<!-- ══════════════════════════════════════════════════════════════
     HEADER ROW
═══════════════════════════════════════════════════════════════ -->
<div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
    <div>
        <p class="text-xs text-cyber-muted tracking-widest uppercase mb-1">Manajemen Konten</p>
        <h2 class="text-xl sm:text-2xl font-extrabold text-cyber-text">Kelola Blog</h2>
        <p class="text-cyber-muted text-sm mt-1">
            <?= number_format(count($posts)) ?> artikel tersedia
        </p>
    </div>

    <a href="/Admin/blog-detail"
       class="inline-flex items-center gap-2 bg-cyber-orange hover:bg-cyber-orangeL active:bg-cyber-orangeD
              text-white text-sm font-bold px-5 py-3 rounded-xl transition-all self-start sm:self-auto flex-shrink-0"
    style="box-shadow:0 0 20px #06b6d440">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Buat Blog Baru
    </a>
</div>

<!-- Flash message -->
<?php if ($flash !== ''): ?>
    <div id="flashMsg"
         class="mb-5 flex items-center gap-3 bg-green-950/50 border border-green-700/50 rounded-xl px-4 py-3"
         role="alert">
        <svg class="w-4 h-4 text-green-400 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd"
                  d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9
                     10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                  clip-rule="evenodd"/>
        </svg>
        <p class="text-green-300 text-sm font-medium"><?= htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') ?></p>
    </div>
    <script>setTimeout(()=>{const el=document.getElementById('flashMsg');if(el)el.remove();},4000);</script>
<?php endif; ?>

<!-- ══════════════════════════════════════════════════════════════
     SEARCH & FILTER BAR
═══════════════════════════════════════════════════════════════ -->
<div class="mb-4 flex flex-col xs:flex-row gap-3">
    <div class="relative flex-1">
        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-cyber-muted pointer-events-none"
             fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
        <input
            type="text"
            id="searchInput"
            placeholder="Cari artikel..."
            class="w-full bg-cyber-card border border-cyber-border rounded-xl
                   pl-10 pr-4 py-2.5 text-sm text-cyber-text placeholder-cyber-dim
                   focus:outline-none focus:border-cyber-orange transition-all"
        >
    </div>
    <div class="text-xs text-cyber-muted self-center flex-shrink-0 hidden xs:block">
        <span id="visibleCount"><?= count($posts) ?></span> / <?= count($posts) ?> artikel
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════
     BLOG LIST
═══════════════════════════════════════════════════════════════ -->
<div id="blogListWrapper">
    <?php include __DIR__ . '/partials/blog-list.php'; ?>
</div>

<!-- ── Hidden CSRF for delete AJAX ──────────────────────────── -->
<input type="hidden" id="deleteCsrfToken" value="<?= htmlspecialchars($deleteCsrf, ENT_QUOTES, 'UTF-8') ?>">

<!-- ══════════════════════════════════════════════════════════════
     JAVASCRIPT  |  Search + Delete AJAX
═══════════════════════════════════════════════════════════════ -->
<script>
(function () {

    /* ── Client-side search ───────────────────────────────── */
    const searchInput  = document.getElementById('searchInput');
    const visibleCount = document.getElementById('visibleCount');
    const rows         = document.querySelectorAll('.blog-row');

    searchInput?.addEventListener('input', () => {
        const q   = searchInput.value.trim().toLowerCase();
        let shown = 0;

        rows.forEach(row => {
            const title = row.dataset.title?.toLowerCase() ?? '';
            const show  = q === '' || title.includes(q);
            row.style.display = show ? '' : 'none';
            if (show) shown++;
        });

        if (visibleCount) visibleCount.textContent = shown;
    });

    /* ── Delete via AJAX + SweetAlert2 ───────────────────── */
    const csrfToken = document.getElementById('deleteCsrfToken')?.value ?? '';

    window.confirmDelete = function (id, title) {
        Swal.fire({
            title: 'Hapus Blog?',
            html : `<span style="color:#94a3b8;font-size:.875rem">Artikel <strong style="color:#e2e8f0">"${title}"</strong> akan dihapus permanen dan tidak bisa dikembalikan.</span>`,
            icon : 'warning',

            /* Tema cyberpunk */
            background    : '#0f0f1a',
            color         : '#e2e8f0',
            iconColor     : '#06b6d4',
            confirmButtonColor  : '#dc2626',
            cancelButtonColor   : '#1a1a2e',
            confirmButtonText   : '<svg style="width:14px;height:14px;display:inline;margin-right:6px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>Ya, Hapus!',
            cancelButtonText    : 'Batal',
            showCancelButton    : true,
            reverseButtons      : true,
            focusCancel         : true,
            customClass: {
                popup          : 'swal-cyber-popup',
                confirmButton  : 'swal-cyber-confirm',
                cancelButton   : 'swal-cyber-cancel',
            },
        }).then(result => {
            if (!result.isConfirmed) return;

            /* Loading state */
            Swal.fire({
                title             : 'Menghapus...',
                allowOutsideClick : false,
                background        : '#0f0f1a',
                color             : '#e2e8f0',
                didOpen           : () => Swal.showLoading(),
            });

            const fd = new FormData();
            fd.append('action',     'delete');
            fd.append('id',         id);
            fd.append('csrf_token', csrfToken);

            fetch('/Admin/blog', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            title    : 'Terhapus!',
                            text     : data.message,
                            icon     : 'success',
                            background     : '#0f0f1a',
                            color          : '#e2e8f0',
                            iconColor      : '#22c55e',
                            confirmButtonColor : '#06b6d4',
                            timer    : 1800,
                            timerProgressBar : true,
                            showConfirmButton : false,
                        });
                        /* Hapus baris dari DOM */
                        const row = document.querySelector(`.blog-row[data-id="${id}"]`);
                        row?.remove();
                        /* Update counter */
                        const remaining = document.querySelectorAll('.blog-row').length;
                        if (visibleCount) visibleCount.textContent = remaining;
                        /* Tampilkan empty state jika tidak ada lagi */
                        if (remaining === 0) {
                            document.getElementById('blogListWrapper').innerHTML = `
                                <div class="bg-cyber-card border border-cyber-border rounded-2xl px-5 py-16 text-center">
                                    <svg class="w-14 h-14 text-cyber-dim mb-4 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    <p class="text-cyber-muted font-medium">Belum ada artikel.</p>
                                    <a href="/Admin/blog-detail"
                                       class="mt-4 inline-flex items-center gap-2 bg-cyber-orange text-white text-sm font-bold px-4 py-2 rounded-xl">
                                       + Buat Blog Baru
                                    </a>
                                </div>`;
                        }
                    } else {
                        Swal.fire({
                            title    : 'Gagal!',
                            text     : data.message,
                            icon     : 'error',
                            background     : '#0f0f1a',
                            color          : '#e2e8f0',
                            iconColor      : '#ef4444',
                            confirmButtonColor : '#06b6d4',
                        });
                    }
                })
                .catch(() => {
                    Swal.fire({
                        title    : 'Koneksi Error',
                        text     : 'Tidak dapat terhubung ke server.',
                        icon     : 'error',
                        background     : '#0f0f1a',
                        color          : '#e2e8f0',
                        confirmButtonColor : '#06b6d4',
                    });
                });
        });
    };
})();
</script>

<!-- SweetAlert2 CDN -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

<!-- SweetAlert2 overrides tema cyberpunk -->
<style>
.swal-cyber-popup  { border: 1px solid #1a1a2e !important; border-radius: 1rem !important; }
.swal-cyber-confirm { font-weight: 700 !important; border-radius: .75rem !important; display: inline-flex !important; align-items: center !important; }
.swal-cyber-cancel  { border: 1px solid #1a1a2e !important; color: #64748b !important; border-radius: .75rem !important; }
.swal-cyber-cancel:hover { border-color: #334155 !important; color: #e2e8f0 !important; }
.swal2-timer-progress-bar { background: #06b6d4 !important; }
</style>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/Admin/app.blade.php';
?>
