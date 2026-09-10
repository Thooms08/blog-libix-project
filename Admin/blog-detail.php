<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../Logic/Auth.php';
require_once __DIR__ . '/../Logic/Admin/Blog.php';

$auth = new AuthLogic($conn);
$auth->requireLogin();

$adminUser  = $auth->getCurrentUser();
$logoutCsrf = $auth->generateCsrfToken();
$blogLogic  = new BlogAdminLogic($conn);

/* ── Mode: create atau edit ───────────────────────────────────────────────── */
$editSlug = isset($_GET['slug']) ? trim($_GET['slug']) : '';
$isEdit   = $editSlug !== '';
$post     = null;

if ($isEdit) {
    $post = $blogLogic->getBySlug($editSlug);
    if (!$post) {
        $_SESSION['flash_success'] = 'Artikel tidak ditemukan.';
        header('Location: /Admin/blog');
        exit();
    }
}
$editId = $isEdit ? (int) $post['id'] : 0;

$allKategori      = $blogLogic->getAllKategori();
$selectedKategori = $post['kategori_ids'] ?? [];

/* ── Handle POST (save) ───────────────────────────────────────────────────── */
$errors  = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    /* CSRF */
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (empty($csrfToken) || !$auth->validateAjaxCsrfToken($csrfToken)) {
        $errors[] = 'Token tidak valid. Muat ulang halaman dan coba lagi.';
    }

    if (empty($errors)) {
        $title    = trim($_POST['title']   ?? '');
        $excerpt  = trim($_POST['excerpt'] ?? '');
        $content  = trim($_POST['content'] ?? '');
        $katIds   = array_map('intval', (array) ($_POST['kategori'] ?? []));
        $imagePath = null;

        /* Validasi dasar */
        if ($title === '')   $errors[] = 'Judul tidak boleh kosong.';
        if ($content === '') $errors[] = 'Konten artikel tidak boleh kosong.';
        if (mb_strlen($excerpt) > 150) $errors[] = 'Excerpt maksimal 150 karakter.';

        /* Upload gambar (opsional) */
        if (!empty($_FILES['thumbnail']['name'])) {
            $upload = $blogLogic->uploadImage($_FILES['thumbnail'], $blogLogic->generateSlug($title, $isEdit ? $editId : 0));
            if (!$upload['success']) {
                $errors[] = $upload['message'];
            } else {
                $imagePath = $upload['path'];
            }
        }

        if (empty($errors)) {
            $data = [
                'title'   => $title,
                'excerpt' => $excerpt,
                'content' => $content,
                'image'   => $imagePath,
            ];

            if ($isEdit) {
                $result = $blogLogic->update($editId, $data, $katIds);
            } else {
                $result = $blogLogic->create($data, $katIds);
            }

            if ($result['success']) {
                $_SESSION['flash_success'] = $result['message'];
                header('Location: /Admin/blog');
                exit();
            } else {
                $errors[] = $result['message'];
            }
        }
    }
}

$formCsrf = $auth->getAjaxCsrfToken();
$title    = $isEdit ? 'Edit Artikel' : 'Buat Blog Baru';

ob_start();
?>

<!-- ══════════════════════════════════════════════════════════════
     HEADER
═══════════════════════════════════════════════════════════════ -->
<div class="mb-6 flex items-center gap-4">
    <a href="/Admin/blog"
       class="w-9 h-9 flex items-center justify-center rounded-xl border border-cyber-border
              text-cyber-muted hover:text-cyber-orange hover:border-cyber-orange/50
              bg-cyber-card transition-all flex-shrink-0">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
    </a>
    <div>
        <p class="text-xs text-cyber-muted tracking-widest uppercase mb-0.5">
            <?= $isEdit ? 'Manajemen Konten · Edit' : 'Manajemen Konten · Baru' ?>
        </p>
        <h2 class="text-xl font-extrabold text-cyber-text">
            <?= $isEdit ? 'Edit Artikel' : 'Buat Blog Baru' ?>
        </h2>
    </div>
</div>

<!-- Error list -->
<?php if (!empty($errors)): ?>
    <div class="mb-5 bg-red-950/50 border border-red-800/60 rounded-xl px-4 py-3 space-y-1" role="alert">
        <?php foreach ($errors as $err): ?>
            <p class="text-red-300 text-sm flex items-start gap-2">
                <svg class="w-4 h-4 flex-shrink-0 mt-0.5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
                <?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?>
            </p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- ══════════════════════════════════════════════════════════════
     FORM
═══════════════════════════════════════════════════════════════ -->
<form method="POST"
      action="/Admin/blog-detail<?= $isEdit ? '?slug=' . urlencode($post['slug']) : '' ?>"
      enctype="multipart/form-data"
      id="blogForm"
      novalidate>

    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($formCsrf, ENT_QUOTES, 'UTF-8') ?>">

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">

        <!-- ── Kolom kiri (konten utama) ─────────────────── -->
        <div class="xl:col-span-2 space-y-5">

            <!-- Judul -->
            <div class="bg-cyber-card border border-cyber-border rounded-2xl p-5">
                <label for="titleInput" class="block text-xs font-bold uppercase tracking-widest text-cyber-muted mb-2">
                    Judul Artikel <span class="text-red-400">*</span>
                </label>
                <input
                    type="text"
                    id="titleInput"
                    name="title"
                    required
                    maxlength="191"
                    placeholder="Masukkan judul artikel yang menarik..."
                    value="<?= htmlspecialchars($post['title'] ?? ($_POST['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                    class="w-full bg-cyber-panel border border-cyber-border rounded-xl px-4 py-3
                           text-sm text-cyber-text placeholder-cyber-dim
                           focus:outline-none focus:border-cyber-orange transition-all"
                >
            </div>

            <!-- Excerpt -->
            <div class="bg-cyber-card border border-cyber-border rounded-2xl p-5">
                <div class="flex items-center justify-between mb-2">
                    <label for="excerptInput" class="text-xs font-bold uppercase tracking-widest text-cyber-muted">
                        Excerpt / Ringkasan
                    </label>
                    <span class="text-[11px] font-mono">
                        <span id="excerptCount" class="text-cyber-orange font-bold">0</span>
                        <span class="text-cyber-dim">/150</span>
                    </span>
                </div>
                <textarea
                    id="excerptInput"
                    name="excerpt"
                    rows="3"
                    maxlength="150"
                    placeholder="Ringkasan singkat artikel (maks 150 karakter)..."
                    class="w-full bg-cyber-panel border border-cyber-border rounded-xl px-4 py-3
                           text-sm text-cyber-text placeholder-cyber-dim resize-none
                           focus:outline-none focus:border-cyber-orange transition-all"
                ><?= htmlspecialchars($post['excerpt'] ?? ($_POST['excerpt'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                <!-- Progress bar excerpt -->
                <div class="mt-2 h-1 bg-cyber-border rounded-full overflow-hidden">
                    <div id="excerptBar" class="h-full rounded-full transition-all duration-200 bg-cyber-orange" style="width:0%"></div>
                </div>
                <p id="excerptHint" class="text-[10px] text-cyber-dim mt-1">Excerpt digunakan sebagai deskripsi singkat di halaman daftar artikel.</p>
            </div>

            <!-- Konten (CKEditor) -->
            <div class="bg-cyber-card border border-cyber-border rounded-2xl p-5">
                <label class="block text-xs font-bold uppercase tracking-widest text-cyber-muted mb-3">
                    Konten Artikel <span class="text-red-400">*</span>
                </label>

                <textarea id="contentEditor" name="content"><?= $post['content'] ?? ($_POST['content'] ?? '') ?></textarea>

                <!-- CKEditor 5 via CDN -->
                <script src="https://cdn.ckeditor.com/ckeditor5/41.4.2/classic/ckeditor.js"></script>
                <script>
                ClassicEditor.create(document.getElementById('contentEditor'), {
                    toolbar: {
                        items: [
                            'heading', '|',
                            'bold', 'italic', 'underline', 'strikethrough', '|',
                            'link', 'blockQuote', '|',
                            'bulletedList', 'numberedList', '|',
                            'outdent', 'indent', '|',
                            'insertTable', 'horizontalLine', '|',
                            'undo', 'redo',
                        ],
                        shouldNotGroupWhenFull: false,
                    },
                    heading: {
                        options: [
                            { model: 'paragraph',  title: 'Paragraph', class: 'ck-heading_paragraph' },
                            { model: 'heading2',   view: 'h2', title: 'Heading 2', class: 'ck-heading_heading2' },
                            { model: 'heading3',   view: 'h3', title: 'Heading 3', class: 'ck-heading_heading3' },
                            { model: 'heading4',   view: 'h4', title: 'Heading 4', class: 'ck-heading_heading4' },
                        ],
                    },
                    placeholder: 'Mulai menulis konten artikel di sini...',
                    language: 'id',
                }).then(editor => {
                    window._ckEditor = editor;

                    /* Sync isi ke textarea sebelum form submit */
                    document.getElementById('blogForm').addEventListener('submit', () => {
                        document.getElementById('contentEditor').value = editor.getData();
                    });
                }).catch(console.error);
                </script>

                <!-- Override CKEditor style agar cocok dengan tema -->
                <style>
                :root { --ck-color-base-background:#12121f; --ck-color-base-border:#1a1a2e;
                        --ck-color-toolbar-background:#0d0d18; --ck-color-toolbar-border:#1a1a2e;
                        --ck-color-text:#e2e8f0; --ck-color-button-default-hover-background:#1a1a2e;
                        --ck-color-button-on-background:#06b6d415; --ck-color-button-on-color:#06b6d4;
                        --ck-color-focus-border:#06b6d4; --ck-color-focus-outer-shadow:#06b6d425;
                        --ck-color-base-foreground:#0f0f1a; }
                .ck.ck-editor__main>.ck-editor__editable { min-height:320px; background:#12121f !important;
                    border-color:#1a1a2e !important; color:#e2e8f0 !important; }
                .ck.ck-editor__main>.ck-editor__editable.ck-focused { border-color:#06b6d4 !important;
                    box-shadow:0 0 0 2px #06b6d430 !important; }
                .ck.ck-toolbar { border-color:#1a1a2e !important; }
                .ck.ck-button:not(.ck-disabled):hover { background:#1a1a2e !important; }
                .ck-rounded-corners .ck.ck-editor__top .ck-sticky-panel .ck-toolbar,
                .ck.ck-editor__top .ck-sticky-panel .ck-toolbar.ck-rounded-corners {
                    border-radius:.75rem .75rem 0 0 !important; }
                .ck-rounded-corners .ck.ck-editor__main>.ck-editor__editable,
                .ck.ck-editor__main>.ck-editor__editable.ck-rounded-corners {
                    border-radius: 0 0 .75rem .75rem !important; }
                </style>
            </div>
        </div>

        <!-- ── Kolom kanan (meta) ─────────────────────────── -->
        <div class="space-y-5">

            <!-- Thumbnail Upload -->
            <div class="bg-cyber-card border border-cyber-border rounded-2xl p-5">
                <label class="block text-xs font-bold uppercase tracking-widest text-cyber-muted mb-3">
                    Thumbnail
                </label>

                <!-- Preview gambar lama (edit mode) -->
                <?php if ($isEdit && !empty($post['image'])): ?>
                    <div class="mb-3 rounded-xl overflow-hidden border border-cyber-border bg-cyber-panel">
                        <img id="currentThumb"
                             src="<?= htmlspecialchars($post['image'], ENT_QUOTES, 'UTF-8') ?>"
                             alt="Thumbnail saat ini"
                             class="w-full h-36 object-cover">
                    </div>
                    <p class="text-[11px] text-cyber-muted mb-3">Upload gambar baru untuk mengganti thumbnail di atas.</p>
                <?php endif; ?>

                <!-- Drop zone -->
                <div id="dropZone"
                     class="relative border-2 border-dashed border-cyber-border rounded-xl
                            cursor-pointer hover:border-cyber-orange/50 transition-all
                            flex flex-col items-center justify-center gap-2 p-6 min-h-[120px]
                            bg-cyber-panel overflow-hidden">

                    <!-- Preview baru -->
                    <img id="thumbPreview" src="" alt=""
                         class="hidden absolute inset-0 w-full h-full object-cover rounded-xl">
                    <div class="absolute inset-0 bg-black/40 rounded-xl hidden" id="thumbOverlay"></div>

                    <div id="dropContent" class="relative z-10 text-center pointer-events-none">
                        <svg class="w-8 h-8 text-cyber-dim mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                  d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586
                                     a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0
                                     00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        <p class="text-xs text-cyber-muted font-semibold">Klik atau seret gambar ke sini</p>
                        <p class="text-[11px] text-cyber-dim mt-0.5">JPG, PNG, WebP  |  Maks <strong class="text-cyber-orange">2 MB</strong></p>
                        <p class="text-[10px] text-cyber-dim/70 mt-0.5">Gambar akan dikompres otomatis ≤ 300 KB</p>
                    </div>

                    <input type="file" name="thumbnail" id="thumbnailInput"
                           accept="image/jpeg,image/png,image/webp"
                           class="absolute inset-0 opacity-0 cursor-pointer w-full h-full">
                </div>

                <!-- Info file terpilih -->
                <div id="fileInfo" class="hidden mt-2 flex items-center justify-between gap-2">
                    <p class="text-[11px] text-cyber-muted truncate" id="fileName"></p>
                    <button type="button" id="clearThumb"
                            class="text-[11px] text-red-400 hover:text-red-300 flex-shrink-0">
                        Hapus
                    </button>
                </div>

                <!-- Validasi ukuran client-side -->
                <p id="thumbError" class="hidden mt-2 text-[11px] text-red-400"></p>
            </div>

            <!-- Kategori -->
            <div class="bg-cyber-card border border-cyber-border rounded-2xl p-5">
                <label class="block text-xs font-bold uppercase tracking-widest text-cyber-muted mb-3">
                    Kategori
                </label>

                <?php if (empty($allKategori)): ?>
                    <p class="text-xs text-cyber-dim">
                        Belum ada kategori.
                        <a href="/Admin/kategori" class="text-cyber-orange hover:underline">Buat kategori</a>
                    </p>
                <?php else: ?>
                    <div class="space-y-1.5 max-h-52 overflow-y-auto pr-1" id="kategoriList">
                        <?php foreach ($allKategori as $kat):
                            $checked = in_array((int) $kat['id'], $selectedKategori, true) ? 'checked' : '';
                        ?>
                            <label class="flex items-center gap-2.5 px-3 py-2 rounded-xl cursor-pointer
                                          hover:bg-cyber-orange/[0.06] transition-colors group">
                                <input type="checkbox"
                                       name="kategori[]"
                                       value="<?= (int) $kat['id'] ?>"
                                       <?= $checked ?>
                                       class="w-4 h-4 accent-orange-500 flex-shrink-0">
                                <span class="text-sm text-cyber-muted group-hover:text-cyber-text transition-colors">
                                    <?= htmlspecialchars($kat['nama'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Tombol Simpan -->
            <div class="bg-cyber-card border border-cyber-border rounded-2xl p-5 space-y-3">

                <button type="submit" id="submitBtn"
                        class="w-full flex items-center justify-center gap-2 bg-cyber-orange
                               hover:bg-cyber-orangeL active:bg-cyber-orangeD text-white font-bold
                               py-3 px-5 rounded-xl text-sm transition-all
                               disabled:opacity-50 disabled:cursor-not-allowed"
                        style="box-shadow:0 0 20px #06b6d440">
                    <svg id="submitIcon" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M5 13l4 4L19 7"/>
                    </svg>
                    <span id="submitLabel">
                        <?= $isEdit ? 'Simpan Perubahan' : 'Publikasikan Artikel' ?>
                    </span>
                </button>

                <a href="/Admin/blog"
                   class="w-full flex items-center justify-center gap-2 border border-cyber-border
                          text-cyber-muted hover:text-cyber-text hover:border-cyber-dim
                          font-semibold py-3 px-5 rounded-xl text-sm transition-all bg-cyber-panel">
                    Batal
                </a>

                <?php if ($isEdit): ?>
                    <!-- Preview di tab baru -->
                    <a href="/post/<?= htmlspecialchars($post['slug'], ENT_QUOTES, 'UTF-8') ?>" target="_blank"
                       class="w-full flex items-center justify-center gap-2 border border-cyber-border
                              text-cyber-muted hover:text-cyber-orange hover:border-cyber-orange/50
                              font-semibold py-2.5 px-5 rounded-xl text-xs transition-all bg-cyber-panel">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4
                                     M14 4h6m0 0v6m0-6L10 14"/>
                        </svg>
                        Preview Artikel
                    </a>
                <?php endif; ?>
            </div>

        </div><!-- /kolom kanan -->
    </div><!-- /grid -->
</form>

<!-- ══════════════════════════════════════════════════════════════
     JAVASCRIPT
═══════════════════════════════════════════════════════════════ -->
<script>
(function () {

    /* ── Excerpt counter (real-time via input event) ──────── */
    const excerptInput = document.getElementById('excerptInput');
    const excerptCount = document.getElementById('excerptCount');
    const excerptBar   = document.getElementById('excerptBar');
    const excerptHint  = document.getElementById('excerptHint');
    const MAX_EXCERPT  = 150;

    function updateExcerptCounter() {
        const len = excerptInput.value.length;
        excerptCount.textContent = len;

        const pct = (len / MAX_EXCERPT) * 100;
        excerptBar.style.width = pct + '%';

        if (len >= MAX_EXCERPT) {
            excerptBar.classList.replace('bg-cyber-orange', 'bg-red-500');
            excerptCount.style.color = '#ef4444';
            excerptHint.textContent = 'Batas karakter tercapai!';
        } else if (len >= 120) {
            excerptBar.classList.replace('bg-cyber-orange', 'bg-yellow-500');
            excerptCount.style.color = '#eab308';
            excerptHint.textContent = MAX_EXCERPT - len + ' karakter tersisa.';
        } else {
            excerptBar.className = excerptBar.className.replace(/bg-\w+-\d+/, 'bg-cyber-orange');
            excerptCount.style.color = '';
            excerptHint.textContent = 'Excerpt digunakan sebagai deskripsi singkat di halaman daftar artikel.';
        }
    }

    excerptInput?.addEventListener('input', updateExcerptCounter);
    // Inisialisasi untuk mode edit
    updateExcerptCounter();

    /* ── Thumbnail drop zone + preview ───────────────────── */
    const dropZone      = document.getElementById('dropZone');
    const thumbInput    = document.getElementById('thumbnailInput');
    const thumbPreview  = document.getElementById('thumbPreview');
    const thumbOverlay  = document.getElementById('thumbOverlay');
    const dropContent   = document.getElementById('dropContent');
    const fileInfo      = document.getElementById('fileInfo');
    const fileName      = document.getElementById('fileName');
    const clearThumb    = document.getElementById('clearThumb');
    const thumbError    = document.getElementById('thumbError');
    const MAX_FILE_SIZE = 2 * 1024 * 1024; // 2 MB

    function showPreview(file) {
        thumbError.classList.add('hidden');

        if (file.size > MAX_FILE_SIZE) {
            thumbError.textContent = 'Ukuran file melebihi 2 MB. Pilih file yang lebih kecil.';
            thumbError.classList.remove('hidden');
            clearPreview();
            return;
        }

        const reader = new FileReader();
        reader.onload = e => {
            thumbPreview.src = e.target.result;
            thumbPreview.classList.remove('hidden');
            thumbOverlay.classList.remove('hidden');
            dropContent.style.opacity = '0';
            fileInfo.classList.remove('hidden');
            fileName.textContent = file.name + ' (' + (file.size / 1024).toFixed(0) + ' KB)';
        };
        reader.readAsDataURL(file);
    }

    function clearPreview() {
        thumbInput.value = '';
        thumbPreview.src = '';
        thumbPreview.classList.add('hidden');
        thumbOverlay.classList.add('hidden');
        dropContent.style.opacity = '1';
        fileInfo.classList.add('hidden');
        fileName.textContent = '';
    }

    thumbInput?.addEventListener('change', () => {
        if (thumbInput.files[0]) showPreview(thumbInput.files[0]);
    });

    clearThumb?.addEventListener('click', clearPreview);

    // Drag & drop
    dropZone?.addEventListener('dragover', e => {
        e.preventDefault();
        dropZone.classList.add('border-cyber-orange', 'bg-cyber-orange/[0.04]');
    });
    dropZone?.addEventListener('dragleave', () => {
        dropZone.classList.remove('border-cyber-orange', 'bg-cyber-orange/[0.04]');
    });
    dropZone?.addEventListener('drop', e => {
        e.preventDefault();
        dropZone.classList.remove('border-cyber-orange', 'bg-cyber-orange/[0.04]');
        const file = e.dataTransfer.files[0];
        if (file && file.type.startsWith('image/')) {
            // Set ke input agar ikut form submit
            const dt = new DataTransfer();
            dt.items.add(file);
            thumbInput.files = dt.files;
            showPreview(file);
        }
    });

    /* ── Submit loading state ─────────────────────────────── */
    const blogForm  = document.getElementById('blogForm');
    const submitBtn = document.getElementById('submitBtn');
    const submitLbl = document.getElementById('submitLabel');
    const submitIco = document.getElementById('submitIcon');

    blogForm?.addEventListener('submit', () => {
        // Pastikan konten CKEditor sudah di-sync
        if (window._ckEditor) {
            document.getElementById('contentEditor').value = window._ckEditor.getData();
        }

        submitBtn.disabled = true;
        submitLbl.textContent = 'Menyimpan...';
        submitIco.innerHTML = '<circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" stroke-dasharray="31.4" stroke-dashoffset="10" class="animate-spin origin-center"/>';
    });

})();
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/Admin/app.blade.php';
?>
