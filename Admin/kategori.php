<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../Logic/Auth.php';
require_once __DIR__ . '/../Logic/Admin/Kategori.php';

$auth = new AuthLogic($conn);
$auth->requireLogin();

$adminUser  = $auth->getCurrentUser();
$logoutCsrf = $auth->generateCsrfToken();

$katLogic = new KategoriAdminLogic($conn);

/* ── AJAX handler (POST) ──────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');

    $action    = $_POST['action'];
    $csrfToken = $_POST['csrf_token'] ?? '';

    // Validasi CSRF (pakai token AJAX yang persist  |  tidak one-time-use)
    if (!$auth->validateAjaxCsrfToken($csrfToken)) {
        echo json_encode(['success' => false, 'message' => 'Token tidak valid. Muat ulang halaman.']);
        exit();
    }

    switch ($action) {

        case 'create':
            $nama   = trim($_POST['nama'] ?? '');
            $result = $katLogic->create($nama);
            if ($result['success']) {
                // Kembalikan data kategori baru untuk update DOM tanpa reload
                $result['kategori'] = [
                    'id'             => $result['id'],
                    'nama'           => htmlspecialchars($nama, ENT_QUOTES, 'UTF-8'),
                    'jumlah_artikel' => 0,
                    'created_at'     => date('Y-m-d H:i:s'),
                ];
            }
            echo json_encode($result);
            break;

        case 'update':
            $id     = (int) ($_POST['id'] ?? 0);
            $nama   = trim($_POST['nama'] ?? '');
            $result = $katLogic->update($id, $nama);
            if ($result['success']) {
                $result['nama'] = htmlspecialchars($nama, ENT_QUOTES, 'UTF-8');
                $result['id']   = $id;
            }
            echo json_encode($result);
            break;

        case 'delete':
            $id     = (int) ($_POST['id'] ?? 0);
            $result = $katLogic->delete($id);
            echo json_encode($result);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Aksi tidak dikenal.']);
    }
    exit();
}

/* ── Data ─────────────────────────────────────────────────────────────────── */
$kategoris  = $katLogic->getAll();
$formCsrf   = $auth->getAjaxCsrfToken();   // token persist untuk semua AJAX di halaman ini
$title      = 'Kelola Kategori';

ob_start();
?>

<!-- ══════════════════════════════════════════════════════════════
     HEADER ROW
═══════════════════════════════════════════════════════════════ -->
<div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
    <div>
        <p class="text-xs text-cyber-muted tracking-widest uppercase mb-1">Manajemen Konten</p>
        <h2 class="text-xl sm:text-2xl font-extrabold text-cyber-text">Kelola Kategori</h2>
        <p class="text-cyber-muted text-sm mt-1">
            <span id="katCount"><?= number_format(count($kategoris)) ?></span> kategori tersedia
        </p>
    </div>

    <!-- Tombol buka modal -->
    <button
        type="button"
        id="btnBuatKategori"
        class="inline-flex items-center gap-2 bg-cyber-orange hover:bg-cyber-orangeL active:bg-cyber-orangeD
               text-white text-sm font-bold px-5 py-3 rounded-xl transition-all self-start sm:self-auto flex-shrink-0"
        style="box-shadow:0 0 20px #f9731640"
    >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Buat Kategori Baru
    </button>
</div>

<!-- ══════════════════════════════════════════════════════════════
     SEARCH BAR
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
            id="searchKat"
            placeholder="Cari kategori..."
            class="w-full bg-cyber-card border border-cyber-border rounded-xl
                   pl-10 pr-4 py-2.5 text-sm text-cyber-text placeholder-cyber-dim
                   focus:outline-none focus:border-cyber-orange transition-all"
        >
    </div>
    <div class="text-xs text-cyber-muted self-center flex-shrink-0 hidden xs:block">
        <span id="visibleCount"><?= count($kategoris) ?></span> / <?= count($kategoris) ?> kategori
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════
     KATEGORI LIST
═══════════════════════════════════════════════════════════════ -->
<div id="katListWrapper">
    <?php include __DIR__ . '/partials/kategori-list.php'; ?>
</div>

<!-- Hidden CSRF untuk AJAX -->
<input type="hidden" id="csrfToken" value="<?= htmlspecialchars($formCsrf, ENT_QUOTES, 'UTF-8') ?>">

<!-- ══════════════════════════════════════════════════════════════
     MODAL BUAT / EDIT KATEGORI
═══════════════════════════════════════════════════════════════ -->
<div
    id="modalKategori"
    class="hidden fixed inset-0 z-50 flex items-center justify-center px-4"
    role="dialog"
    aria-modal="true"
    aria-labelledby="modalTitle"
>
    <!-- Backdrop -->
    <div id="modalBackdrop"
         class="absolute inset-0 bg-black/70 backdrop-blur-sm"
    ></div>

    <!-- Dialog card -->
    <div class="relative bg-cyber-card border border-cyber-border rounded-2xl w-full max-w-md shadow-cyber
                transform transition-all duration-200">

        <!-- Accent top -->
        <div class="absolute top-0 left-0 right-0 h-[2px] rounded-t-2xl
                    bg-gradient-to-r from-transparent via-cyber-orange to-transparent opacity-70"></div>

        <!-- Header modal -->
        <div class="flex items-center justify-between px-6 py-5 border-b border-cyber-border">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-cyber-orange/15 border border-cyber-orange/30
                            flex items-center justify-center">
                    <svg id="modalIcon" class="w-4 h-4 text-cyber-orange" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                </div>
                <h2 id="modalTitle" class="text-base font-bold text-cyber-text">Buat Kategori Baru</h2>
            </div>
            <button id="modalClose"
                    class="w-8 h-8 flex items-center justify-center rounded-lg border border-cyber-border
                           text-cyber-muted hover:text-cyber-orange hover:border-cyber-orange/50 transition-all"
                    aria-label="Tutup modal">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <!-- Body modal -->
        <div class="px-6 py-5">
            <!-- Error alert dalam modal -->
            <div id="modalError"
                 class="hidden mb-4 flex items-start gap-2.5 bg-red-950/50 border border-red-800/60
                        rounded-xl px-4 py-3">
                <svg class="w-4 h-4 text-red-400 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
                <p id="modalErrorText" class="text-red-300 text-sm"></p>
            </div>

            <form id="formKategori" novalidate>
                <input type="hidden" id="formAction" value="create">
                <input type="hidden" id="formEditId" value="">

                <!-- Input nama -->
                <div class="mb-5">
                    <div class="flex items-center justify-between mb-2">
                        <label for="inputNama"
                               class="text-xs font-bold uppercase tracking-widest text-cyber-muted">
                            Nama Kategori <span class="text-red-400">*</span>
                        </label>
                        <span class="text-[11px] font-mono">
                            <span id="namaCount" class="text-cyber-orange font-bold">0</span>
                            <span class="text-cyber-dim">/100</span>
                        </span>
                    </div>

                    <input
                        type="text"
                        id="inputNama"
                        name="nama"
                        maxlength="100"
                        required
                        autocomplete="off"
                        placeholder="contoh: Teknologi, Bisnis, UMKM..."
                        class="w-full bg-cyber-panel border border-cyber-border rounded-xl px-4 py-3
                               text-sm text-cyber-text placeholder-cyber-dim
                               focus:outline-none focus:border-cyber-orange transition-all
                               focus:shadow-[0_0_0_2px_#f9731630]"
                    >

                    <!-- Progress bar karakter -->
                    <div class="mt-2 h-1 bg-cyber-border rounded-full overflow-hidden">
                        <div id="namaBar"
                             class="h-full rounded-full transition-all duration-200 bg-cyber-orange"
                             style="width:0%"></div>
                    </div>
                </div>

                <!-- Tombol simpan -->
                <button
                    type="submit"
                    id="btnSimpan"
                    class="w-full flex items-center justify-center gap-2 bg-cyber-orange
                           hover:bg-cyber-orangeL active:bg-cyber-orangeD text-white font-bold
                           py-3 px-5 rounded-xl text-sm transition-all
                           disabled:opacity-50 disabled:cursor-not-allowed"
                    style="box-shadow:0 0 16px #f9731630"
                >
                    <svg id="btnIcon" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    <span id="btnLabel">Simpan Kategori</span>
                </button>
            </form>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════
     JAVASCRIPT
═══════════════════════════════════════════════════════════════ -->

<!-- SweetAlert2 CDN -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

<!-- SweetAlert2 tema cyberpunk -->
<style>
.swal-cyber-popup  { border:1px solid #1a1a2e !important; border-radius:1rem !important; }
.swal-cyber-confirm{ font-weight:700 !important; border-radius:.75rem !important; display:inline-flex !important; align-items:center !important; }
.swal-cyber-cancel { border:1px solid #1a1a2e !important; color:#64748b !important; border-radius:.75rem !important; }
.swal-cyber-cancel:hover{ border-color:#334155 !important; color:#e2e8f0 !important; }
.swal2-timer-progress-bar{ background:#f97316 !important; }
</style>

<script>
(function () {
    const csrfToken     = document.getElementById('csrfToken')?.value ?? '';
    const modal         = document.getElementById('modalKategori');
    const modalBackdrop = document.getElementById('modalBackdrop');
    const modalClose    = document.getElementById('modalClose');
    const modalTitle    = document.getElementById('modalTitle');
    const modalIcon     = document.getElementById('modalIcon');
    const modalError    = document.getElementById('modalError');
    const modalErrTxt   = document.getElementById('modalErrorText');
    const formKategori  = document.getElementById('formKategori');
    const formAction    = document.getElementById('formAction');
    const formEditId    = document.getElementById('formEditId');
    const inputNama     = document.getElementById('inputNama');
    const namaCount     = document.getElementById('namaCount');
    const namaBar       = document.getElementById('namaBar');
    const btnSimpan     = document.getElementById('btnSimpan');
    const btnLabel      = document.getElementById('btnLabel');
    const btnIcon       = document.getElementById('btnIcon');
    const katListWrap   = document.getElementById('katListWrapper');
    const visibleCount  = document.getElementById('visibleCount');
    const katCountEl    = document.getElementById('katCount');

    /* ── Karakter counter input nama ─────────────────────── */
    inputNama?.addEventListener('input', updateNamaCounter);

    function updateNamaCounter() {
        const len = inputNama.value.length;
        namaCount.textContent = len;
        const pct = (len / 100) * 100;
        namaBar.style.width = pct + '%';

        if (len >= 100) {
            namaBar.style.background = '#ef4444';
            namaCount.style.color    = '#ef4444';
        } else if (len >= 80) {
            namaBar.style.background = '#eab308';
            namaCount.style.color    = '#eab308';
        } else {
            namaBar.style.background = '#f97316';
            namaCount.style.color    = '';
        }
    }

    /* ── Buka/tutup modal ────────────────────────────────── */
    function openModal(mode = 'create', id = null, namaValue = '') {
        formAction.value  = mode;
        formEditId.value  = id ?? '';
        inputNama.value   = namaValue;
        modalError.classList.add('hidden');
        updateNamaCounter();

        if (mode === 'edit') {
            modalTitle.textContent = 'Edit Kategori';
            btnLabel.textContent   = 'Simpan Perubahan';
            modalIcon.innerHTML    = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>';
        } else {
            modalTitle.textContent = 'Buat Kategori Baru';
            btnLabel.textContent   = 'Simpan Kategori';
            modalIcon.innerHTML    = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>';
        }

        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        setTimeout(() => inputNama.focus(), 100);
    }

    function closeModal() {
        modal.classList.add('hidden');
        document.body.style.overflow = '';
        formKategori.reset();
        namaCount.textContent = '0';
        namaBar.style.width   = '0%';
        namaBar.style.background = '#f97316';
        namaCount.style.color    = '';
        modalError.classList.add('hidden');
    }

    document.getElementById('btnBuatKategori')?.addEventListener('click', () => openModal('create'));
    modalClose?.addEventListener('click', closeModal);
    modalBackdrop?.addEventListener('click', closeModal);
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

    /* ── Submit form (create / update) ──────────────────── */
    function setLoading(on) {
        btnSimpan.disabled = on;
        btnLabel.textContent = on ? 'Menyimpan...' : (formAction.value === 'edit' ? 'Simpan Perubahan' : 'Simpan Kategori');
        btnIcon.innerHTML = on
            ? '<circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" stroke-dasharray="31.4" stroke-dashoffset="10" style="animation:spin 1s linear infinite;transform-origin:center"/>'
            : '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>';
    }

    formKategori?.addEventListener('submit', async (e) => {
        e.preventDefault();

        const nama = inputNama.value.trim();
        if (!nama) {
            showModalError('Nama kategori tidak boleh kosong.');
            inputNama.focus();
            return;
        }
        if (nama.length > 100) {
            showModalError('Nama kategori maksimal 100 karakter.');
            return;
        }

        setLoading(true);
        modalError.classList.add('hidden');

        const fd = new FormData();
        fd.append('action',     formAction.value);
        fd.append('nama',       nama);
        fd.append('csrf_token', csrfToken);
        if (formAction.value === 'edit') fd.append('id', formEditId.value);

        try {
            const res  = await fetch('/Admin/kategori', { method: 'POST', body: fd });
            const data = await res.json();

            if (data.success) {
                closeModal();

                if (formAction.value === 'create') {
                    appendKategoriRow(data.kategori);
                    updateCounters(1);
                } else {
                    updateKategoriRow(data.id, data.nama);
                }

                showToast(data.message, 'success');
            } else {
                showModalError(data.message);
            }
        } catch {
            showModalError('Koneksi gagal. Silakan coba lagi.');
        } finally {
            setLoading(false);
        }
    });

    /* ── Konfirmasi hapus ────────────────────────────────── */
    window.confirmDeleteKat = function (id, nama) {
        Swal.fire({
            title : 'Hapus Kategori?',
            html  : `<span style="color:#94a3b8;font-size:.875rem">Kategori <strong style="color:#e2e8f0">"${nama}"</strong> akan dihapus. Artikel yang terkait tidak ikut terhapus, hanya relasinya yang dihapus.</span>`,
            icon  : 'warning',
            background   : '#0f0f1a',
            color        : '#e2e8f0',
            iconColor    : '#f97316',
            confirmButtonColor : '#dc2626',
            cancelButtonColor  : '#1a1a2e',
            confirmButtonText  : '<svg style="width:14px;height:14px;display:inline;margin-right:6px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>Ya, Hapus!',
            cancelButtonText   : 'Batal',
            showCancelButton   : true,
            reverseButtons     : true,
            focusCancel        : true,
            customClass: { popup:'swal-cyber-popup', confirmButton:'swal-cyber-confirm', cancelButton:'swal-cyber-cancel' },
        }).then(result => {
            if (!result.isConfirmed) return;

            Swal.fire({ title:'Menghapus...', allowOutsideClick:false, background:'#0f0f1a', color:'#e2e8f0', didOpen:()=>Swal.showLoading() });

            const fd = new FormData();
            fd.append('action',     'delete');
            fd.append('id',         id);
            fd.append('csrf_token', csrfToken);

            fetch('/Admin/kategori', { method:'POST', body:fd })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({ title:'Terhapus!', text:data.message, icon:'success', background:'#0f0f1a', color:'#e2e8f0', iconColor:'#22c55e', confirmButtonColor:'#f97316', timer:1800, timerProgressBar:true, showConfirmButton:false });
                        const row = document.querySelector(`.kat-row[data-id="${id}"]`);
                        row?.remove();
                        updateCounters(-1);
                        checkEmptyState();
                    } else {
                        Swal.fire({ title:'Gagal!', text:data.message, icon:'error', background:'#0f0f1a', color:'#e2e8f0', iconColor:'#ef4444', confirmButtonColor:'#f97316' });
                    }
                })
                .catch(() => Swal.fire({ title:'Koneksi Error', text:'Tidak dapat terhubung ke server.', icon:'error', background:'#0f0f1a', color:'#e2e8f0', confirmButtonColor:'#f97316' }));
        });
    };

    /* ── Edit: buka modal dengan data yang ada ───────────── */
    window.openEditKat = function (id, nama) {
        openModal('edit', id, nama);
    };

    /* ── DOM helper: tambah baris baru ──────────────────── */
    function appendKategoriRow(kat) {
        // Hapus empty-state jika ada
        const emptyState = katListWrap.querySelector('.kat-empty');
        if (emptyState) katListWrap.innerHTML = buildTableShell();

        const tbody = document.getElementById('katTableBody');
        if (!tbody) { location.reload(); return; }

        const date = new Date(kat.created_at).toLocaleDateString('id-ID', { day:'2-digit', month:'short', year:'numeric' });

        const row = document.createElement('div');
        row.className = 'kat-row flex items-center gap-4 px-5 py-3.5 hover:bg-cyber-orange/[0.04] transition-colors group border-b border-cyber-border last:border-b-0';
        row.dataset.id   = kat.id;
        row.dataset.nama = kat.nama;
        row.innerHTML = buildRowHTML(kat.id, kat.nama, kat.jumlah_artikel, date);
        tbody.appendChild(row);
    }

    /* ── DOM helper: update nama baris ──────────────────── */
    function updateKategoriRow(id, nama) {
        const row = document.querySelector(`.kat-row[data-id="${id}"]`);
        if (!row) { location.reload(); return; }
        row.dataset.nama = nama.toLowerCase();
        const namaEl = row.querySelector('.kat-nama');
        if (namaEl) namaEl.textContent = nama;
        const btnEdit = row.querySelector('.btn-edit-kat');
        if (btnEdit) btnEdit.setAttribute('onclick', `openEditKat(${id}, '${nama.replace(/'/g, "\\'")}')`);
        const btnDel = row.querySelector('.btn-del-kat');
        if (btnDel) btnDel.setAttribute('onclick', `confirmDeleteKat(${id}, '${nama.replace(/'/g, "\\'")}')`);
    }

    function buildRowHTML(id, nama, jumlah, date) {
        return `
            <div class="flex-1 min-w-0 flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-cyber-orange/10 border border-cyber-orange/20
                            flex items-center justify-center flex-shrink-0">
                    <svg class="w-3.5 h-3.5 text-cyber-orange" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                    </svg>
                </div>
                <div class="min-w-0">
                    <p class="kat-nama text-sm font-semibold text-cyber-text group-hover:text-cyber-orange transition-colors">${nama}</p>
                    <p class="text-[11px] text-cyber-muted mt-0.5">${date}</p>
                </div>
            </div>
            <div class="flex-shrink-0 hidden sm:block">
                <span class="inline-flex items-center gap-1 text-xs font-bold tabular-nums text-cyber-text
                             bg-cyber-panel border border-cyber-border rounded-lg px-2.5 py-1">
                    <svg class="w-3 h-3 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    ${jumlah} artikel
                </span>
            </div>
            <div class="flex-shrink-0 flex items-center gap-1.5">
                <button type="button"
                        class="btn-edit-kat w-8 h-8 flex items-center justify-center rounded-lg border border-cyber-border
                               text-cyber-muted hover:text-cyber-orange hover:border-cyber-orange/50 bg-cyber-panel transition-all"
                        onclick="openEditKat(${id}, '${nama.replace(/'/g, "\\'")}')"
                        title="Edit kategori">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                </button>
                <button type="button"
                        class="btn-del-kat w-8 h-8 flex items-center justify-center rounded-lg border border-cyber-border
                               text-cyber-muted hover:text-red-400 hover:border-red-700/50 bg-cyber-panel transition-all"
                        onclick="confirmDeleteKat(${id}, '${nama.replace(/'/g, "\\'")}')"
                        title="Hapus kategori">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                </button>
            </div>`;
    }

    function buildTableShell() {
        return `<div class="bg-cyber-card border border-cyber-border rounded-2xl overflow-hidden">
            <div class="flex items-center gap-4 px-5 py-3 border-b border-cyber-border bg-cyber-panel
                        text-[10px] font-bold uppercase tracking-widest text-cyber-dim">
                <span class="flex-1">Nama Kategori</span>
                <span class="hidden sm:block w-24 text-center">Artikel</span>
                <span class="w-20 text-center">Aksi</span>
            </div>
            <div id="katTableBody"></div>
        </div>`;
    }

    /* ── Update counter di heading dan search bar ────────── */
    function updateCounters(delta) {
        const rows = document.querySelectorAll('.kat-row').length;
        if (katCountEl)   katCountEl.textContent  = rows;
        if (visibleCount) visibleCount.textContent = rows;
    }

    function checkEmptyState() {
        const rows = document.querySelectorAll('.kat-row');
        if (rows.length === 0) {
            katListWrap.innerHTML = `
                <div class="kat-empty bg-cyber-card border border-cyber-border rounded-2xl px-5 py-16 text-center">
                    <i class="devicon-thealgorithms-plain text-5xl text-cyber-dim mb-4 block"></i>
                    <p class="text-cyber-muted font-semibold text-base mb-1">Belum ada kategori</p>
                    <p class="text-cyber-dim text-sm mb-5">Tambahkan kategori pertama kamu sekarang.</p>
                    <button onclick="document.getElementById('btnBuatKategori').click()"
                            class="inline-flex items-center gap-2 bg-cyber-orange text-white text-sm font-bold px-5 py-2.5 rounded-xl">
                        + Buat Kategori Baru
                    </button>
                </div>`;
        }
    }

    /* ── Search client-side ──────────────────────────────── */
    document.getElementById('searchKat')?.addEventListener('input', function () {
        const q    = this.value.trim().toLowerCase();
        const rows = document.querySelectorAll('.kat-row');
        let shown  = 0;
        rows.forEach(row => {
            const nama = row.dataset.nama?.toLowerCase() ?? '';
            const show = q === '' || nama.includes(q);
            row.style.display = show ? '' : 'none';
            if (show) shown++;
        });
        if (visibleCount) visibleCount.textContent = shown;
    });

    /* ── Error display helper ────────────────────────────── */
    function showModalError(msg) {
        modalErrTxt.textContent = msg;
        modalError.classList.remove('hidden');
    }

    /* ── Toast notification (tanpa overlay) ─────────────── */
    function showToast(msg, type = 'success') {
        Swal.fire({
            toast            : true,
            position         : 'top-end',
            icon             : type,
            title            : msg,
            showConfirmButton: false,
            timer            : 2500,
            timerProgressBar : true,
            background       : '#0f0f1a',
            color            : '#e2e8f0',
            iconColor        : type === 'success' ? '#22c55e' : '#ef4444',
        });
    }

})();
</script>

<!-- Spin keyframe untuk loading icon -->
<style>
@keyframes spin { to { transform: rotate(360deg); } }
</style>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/Admin/app.blade.php';
?>
