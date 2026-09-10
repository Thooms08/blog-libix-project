<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../Logic/Auth.php';
require_once __DIR__ . '/../Logic/Admin/Ulasan.php';

$auth = new AuthLogic($conn);
$auth->requireLogin();

$adminUser  = $auth->getCurrentUser();
$logoutCsrf = $auth->generateCsrfToken();

$ulasanLogic = new UlasanAdminLogic($conn);

/* ── AJAX handler ─────────────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');

    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!$auth->validateAjaxCsrfToken($csrfToken)) {
        echo json_encode(['success' => false, 'message' => 'Token tidak valid. Muat ulang halaman.']);
        exit();
    }

    $action = $_POST['action'];
    $id     = (int) ($_POST['id'] ?? 0);

    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID tidak valid.']);
        exit();
    }

    $result = match ($action) {
        'toggle' => $ulasanLogic->togglePublic($id),
        'delete' => $ulasanLogic->delete($id),
        default  => ['success' => false, 'message' => 'Aksi tidak dikenal.'],
    };

    echo json_encode($result);
    exit();
}

/* ── Data ─────────────────────────────────────────────────────────────────── */
$ulasans   = $ulasanLogic->getAll();
$stats     = $ulasanLogic->getStats();
$ajaxCsrf  = $auth->getAjaxCsrfToken();
$title     = 'Kelola Ulasan';

ob_start();
?>

<!-- ══════════════════════════════════════════════════════════════
     HEADER
═══════════════════════════════════════════════════════════════ -->
<div class="mb-6">
    <p class="text-xs text-cyber-muted tracking-widest uppercase mb-1">Manajemen Konten</p>
    <h2 class="text-xl sm:text-2xl font-extrabold text-cyber-text">Kelola Ulasan</h2>
    <p class="text-cyber-muted text-sm mt-1"><?= number_format($stats['total']) ?> ulasan dari pembaca</p>
</div>

<!-- ══════════════════════════════════════════════════════════════
     STAT CARDS
═══════════════════════════════════════════════════════════════ -->
<div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">

    <!-- Total -->
    <div class="bg-cyber-card border border-cyber-border rounded-2xl p-4 relative overflow-hidden">
        <div class="absolute top-0 left-0 right-0 h-[2px] bg-gradient-to-r from-transparent via-cyber-orange to-transparent opacity-60"></div>
        <p class="text-2xl font-extrabold text-cyber-text tabular-nums"><?= number_format($stats['total']) ?></p>
        <p class="text-xs text-cyber-muted mt-1">Total Ulasan</p>
    </div>

    <!-- Publik -->
    <div class="bg-cyber-card border border-cyber-border rounded-2xl p-4 relative overflow-hidden">
        <div class="absolute top-0 left-0 right-0 h-[2px] bg-gradient-to-r from-transparent via-green-500 to-transparent opacity-60"></div>
        <p class="text-2xl font-extrabold text-green-400 tabular-nums"><?= number_format($stats['publik']) ?></p>
        <p class="text-xs text-cyber-muted mt-1">Publik</p>
    </div>

    <!-- Privat -->
    <div class="bg-cyber-card border border-cyber-border rounded-2xl p-4 relative overflow-hidden">
        <div class="absolute top-0 left-0 right-0 h-[2px] bg-gradient-to-r from-transparent via-slate-500 to-transparent opacity-60"></div>
        <p class="text-2xl font-extrabold text-slate-400 tabular-nums"><?= number_format($stats['privat']) ?></p>
        <p class="text-xs text-cyber-muted mt-1">Privat / Disembunyikan</p>
    </div>

    <!-- Avg Rating -->
    <div class="bg-cyber-card border border-cyber-border rounded-2xl p-4 relative overflow-hidden">
        <div class="absolute top-0 left-0 right-0 h-[2px] bg-gradient-to-r from-transparent via-yellow-500 to-transparent opacity-60"></div>
        <div class="flex items-end gap-1.5">
            <p class="text-2xl font-extrabold text-yellow-400 tabular-nums"><?= number_format($stats['avg_rating'], 1) ?></p>
            <svg class="w-5 h-5 text-yellow-400 mb-0.5" fill="currentColor" viewBox="0 0 20 20">
                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
            </svg>
        </div>
        <p class="text-xs text-cyber-muted mt-1">Rata-rata Rating</p>
    </div>

</div>

<!-- ══════════════════════════════════════════════════════════════
     SEARCH + FILTER
═══════════════════════════════════════════════════════════════ -->
<div class="mb-4 flex flex-col xs:flex-row gap-3">
    <div class="relative flex-1">
        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-cyber-muted pointer-events-none"
             fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
        <input type="text" id="searchUlasan" placeholder="Cari komentar atau judul artikel..."
               class="w-full bg-cyber-card border border-cyber-border rounded-xl
                      pl-10 pr-4 py-2.5 text-sm text-cyber-text placeholder-cyber-dim
                      focus:outline-none focus:border-cyber-orange transition-all">
    </div>

    <!-- Filter status -->
    <select id="filterStatus"
            class="bg-cyber-card border border-cyber-border rounded-xl px-4 py-2.5
                   text-sm text-cyber-text focus:outline-none focus:border-cyber-orange transition-all flex-shrink-0">
        <option value="all">Semua Status</option>
        <option value="1">Publik</option>
        <option value="0">Privat</option>
    </select>

    <div class="text-xs text-cyber-muted self-center flex-shrink-0 hidden xs:block">
        <span id="visibleCount"><?= count($ulasans) ?></span> ulasan
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════
     LIST ULASAN
═══════════════════════════════════════════════════════════════ -->
<div id="ulasanListWrapper">
    <?php include __DIR__ . '/partials/ulasan-list.php'; ?>
</div>

<input type="hidden" id="ajaxCsrfToken" value="<?= htmlspecialchars($ajaxCsrf, ENT_QUOTES, 'UTF-8') ?>">

<!-- SweetAlert2 CDN -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<style>
.swal-cyber-popup   { border:1px solid #1a1a2e !important; border-radius:1rem !important; }
.swal-cyber-confirm { font-weight:700 !important; border-radius:.75rem !important; display:inline-flex !important; align-items:center !important; }
.swal-cyber-cancel  { border:1px solid #1a1a2e !important; color:#64748b !important; border-radius:.75rem !important; }
.swal-cyber-cancel:hover { border-color:#334155 !important; color:#e2e8f0 !important; }
.swal2-timer-progress-bar { background:#06b6d4 !important; }
</style>

<!-- ══════════════════════════════════════════════════════════════
     JAVASCRIPT
═══════════════════════════════════════════════════════════════ -->
<script>
(function () {
    const csrfToken    = document.getElementById('ajaxCsrfToken')?.value ?? '';
    const visibleCount = document.getElementById('visibleCount');

    /* ── Search + Filter ──────────────────────────────────── */
    const searchInput  = document.getElementById('searchUlasan');
    const filterStatus = document.getElementById('filterStatus');

    function applyFilter() {
        const q      = searchInput.value.trim().toLowerCase();
        const status = filterStatus.value; // 'all', '1', '0'
        const rows   = document.querySelectorAll('.ulasan-row');
        let shown    = 0;

        rows.forEach(row => {
            const comment   = (row.dataset.comment   ?? '').toLowerCase();
            const postTitle = (row.dataset.postTitle ?? '').toLowerCase();
            const rowStatus = row.dataset.isPublic;

            const matchQ      = q === '' || comment.includes(q) || postTitle.includes(q);
            const matchStatus = status === 'all' || rowStatus === status;

            const show = matchQ && matchStatus;
            row.style.display = show ? '' : 'none';
            if (show) shown++;
        });

        if (visibleCount) visibleCount.textContent = shown;
    }

    searchInput?.addEventListener('input', applyFilter);
    filterStatus?.addEventListener('change', applyFilter);

    /* ── Toggle Public / Private ──────────────────────────── */
    window.togglePublic = function (id) {
        const fd = new FormData();
        fd.append('action',     'toggle');
        fd.append('id',         id);
        fd.append('csrf_token', csrfToken);

        fetch('/Admin/ulasan', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    showToast(data.message, 'error');
                    return;
                }

                const row = document.querySelector(`.ulasan-row[data-id="${id}"]`);
                if (!row) return;

                const isPublic  = data.is_public;
                row.dataset.isPublic = isPublic;

                // Update badge status
                const badge = row.querySelector('.status-badge');
                if (badge) {
                    badge.textContent  = isPublic ? 'Publik' : 'Privat';
                    badge.className    = badge.className.replace(/bg-\w+-\d+\/\d+|text-\w+-\d+|border-\w+-\d+\/\d+/g, '');
                    if (isPublic) {
                        badge.className = 'status-badge inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-green-500/15 text-green-400 border border-green-500/30';
                    } else {
                        badge.className = 'status-badge inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-slate-500/15 text-slate-400 border border-slate-500/30';
                    }
                }

                // Update tombol toggle
                const btn = row.querySelector('.btn-toggle');
                if (btn) {
                    if (isPublic) {
                        btn.title = 'Sembunyikan ulasan';
                        btn.className = btn.className.replace('text-slate-400 border-slate-700/50 hover:text-green-400 hover:border-green-700/50',
                                                               'text-green-400 border-green-700/50 hover:text-slate-400 hover:border-slate-700/50');
                        btn.innerHTML = iconEye();
                    } else {
                        btn.title = 'Publikasikan ulasan';
                        btn.className = btn.className.replace('text-green-400 border-green-700/50 hover:text-slate-400 hover:border-slate-700/50',
                                                               'text-slate-400 border-slate-700/50 hover:text-green-400 hover:border-green-700/50');
                        btn.innerHTML = iconEyeOff();
                    }
                }

                // Re-apply filter agar sesuai filter status aktif
                applyFilter();
                showToast(data.message, 'success');
            })
            .catch(() => showToast('Koneksi gagal.', 'error'));
    };

    /* ── Delete ───────────────────────────────────────────── */
    window.confirmDeleteUlasan = function (id) {
        Swal.fire({
            title : 'Hapus Ulasan?',
            text  : 'Ulasan ini akan dihapus permanen dan tidak bisa dikembalikan.',
            icon  : 'warning',
            background : '#0f0f1a', color : '#e2e8f0', iconColor : '#06b6d4',
            confirmButtonColor  : '#dc2626',
            cancelButtonColor   : '#1a1a2e',
            confirmButtonText   : 'Ya, Hapus!',
            cancelButtonText    : 'Batal',
            showCancelButton    : true,
            reverseButtons      : true,
            focusCancel         : true,
            customClass : { popup:'swal-cyber-popup', confirmButton:'swal-cyber-confirm', cancelButton:'swal-cyber-cancel' },
        }).then(result => {
            if (!result.isConfirmed) return;

            Swal.fire({ title:'Menghapus...', allowOutsideClick:false, background:'#0f0f1a', color:'#e2e8f0', didOpen:()=>Swal.showLoading() });

            const fd = new FormData();
            fd.append('action',     'delete');
            fd.append('id',         id);
            fd.append('csrf_token', csrfToken);

            fetch('/Admin/ulasan', { method:'POST', body:fd })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({ title:'Terhapus!', text:data.message, icon:'success', background:'#0f0f1a', color:'#e2e8f0', iconColor:'#22c55e', confirmButtonColor:'#06b6d4', timer:1800, timerProgressBar:true, showConfirmButton:false });
                        document.querySelector(`.ulasan-row[data-id="${id}"]`)?.remove();
                        applyFilter();
                        checkEmpty();
                    } else {
                        Swal.fire({ title:'Gagal!', text:data.message, icon:'error', background:'#0f0f1a', color:'#e2e8f0', iconColor:'#ef4444', confirmButtonColor:'#06b6d4' });
                    }
                })
                .catch(() => Swal.fire({ title:'Koneksi Error', icon:'error', background:'#0f0f1a', color:'#e2e8f0', confirmButtonColor:'#06b6d4' }));
        });
    };

    /* ── Empty state check ────────────────────────────────── */
    function checkEmpty() {
        if (document.querySelectorAll('.ulasan-row').length === 0) {
            document.getElementById('ulasanListWrapper').innerHTML =
                `<div class="bg-cyber-card border border-cyber-border rounded-2xl px-5 py-16 text-center">
                    <svg class="w-12 h-12 mx-auto mb-3 text-cyber-dim" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                              d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0
                                 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                    </svg>
                    <p class="text-cyber-muted font-semibold">Belum ada ulasan</p>
                </div>`;
        }
    }

    /* ── SVG icon helpers ─────────────────────────────────── */
    function iconEye() {
        return `<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
        </svg>`;
    }
    function iconEyeOff() {
        return `<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0
                     011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29
                     m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7
                     a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
        </svg>`;
    }

    /* ── Toast ────────────────────────────────────────────── */
    function showToast(msg, type = 'success') {
        Swal.fire({
            toast:true, position:'top-end', icon:type, title:msg,
            showConfirmButton:false, timer:2500, timerProgressBar:true,
            background:'#0f0f1a', color:'#e2e8f0',
            iconColor: type === 'success' ? '#22c55e' : '#ef4444',
        });
    }

})();
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/Admin/app.blade.php';
?>
