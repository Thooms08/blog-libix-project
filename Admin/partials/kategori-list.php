<?php
/**
 * Partial: daftar kategori untuk halaman Admin/kategori.php
 * Variabel: $kategoris (array dari KategoriAdminLogic::getAll())
 */
if (empty($kategoris)): ?>

    <div class="kat-empty bg-cyber-card border border-cyber-border rounded-2xl px-5 py-16 text-center">
        <svg class="w-14 h-14 text-cyber-dim mb-4 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
        <p class="text-cyber-muted font-semibold text-base mb-1">Belum ada kategori</p>
        <p class="text-cyber-dim text-sm mb-5">Tambahkan kategori pertama kamu sekarang.</p>
        <button
            onclick="document.getElementById('btnBuatKategori').click()"
            class="inline-flex items-center gap-2 bg-cyber-orange hover:bg-cyber-orangeL
                   text-white text-sm font-bold px-5 py-2.5 rounded-xl transition-all"
            style="box-shadow:0 0 16px #06b6d430">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Buat Kategori Baru
        </button>
    </div>

<?php else:
    $totalKat = count($kategoris);
?>

    <div class="bg-cyber-card border border-cyber-border rounded-2xl overflow-hidden">

        <!-- Header kolom -->
        <div class="flex items-center gap-4 px-5 py-3 border-b border-cyber-border
                    bg-cyber-panel text-[10px] font-bold uppercase tracking-widest text-cyber-dim">
            <span class="flex-1">Nama Kategori</span>
            <span class="hidden sm:block w-28 text-center">Jumlah Artikel</span>
            <span class="hidden xs:block w-32 text-right">Dibuat</span>
            <span class="w-20 text-center">Aksi</span>
        </div>

        <div id="katTableBody" class="divide-y divide-cyber-border">
            <?php foreach ($kategoris as $i => $kat):
                $id     = (int) $kat['id'];
                $nama   = htmlspecialchars($kat['nama'], ENT_QUOTES, 'UTF-8');
                $jumlah = (int) $kat['jumlah_artikel'];
                $date   = date('d M Y', strtotime($kat['created_at']));
            ?>
                <div class="kat-row flex items-center gap-4 px-5 py-3.5
                            hover:bg-cyber-orange/[0.04] transition-colors group
                            <?= $i >= 30 ? 'hidden extra-kat-row' : '' ?>"
                     data-id="<?= $id ?>"
                     data-nama="<?= strtolower($kat['nama']) ?>">

                    <!-- Icon + nama -->
                    <div class="flex-1 min-w-0 flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-cyber-orange/10 border border-cyber-orange/20
                                    flex items-center justify-center flex-shrink-0">
                            <svg class="w-3.5 h-3.5 text-cyber-orange" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7
                                         7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <p class="kat-nama text-sm font-semibold text-cyber-text
                                      group-hover:text-cyber-orange transition-colors truncate">
                                <?= $nama ?>
                            </p>
                            <p class="text-[11px] text-cyber-muted mt-0.5 xs:hidden"><?= $date ?></p>
                        </div>
                    </div>

                    <!-- Jumlah artikel -->
                    <div class="hidden sm:flex flex-shrink-0 w-28 justify-center">
                        <span class="inline-flex items-center gap-1.5 text-xs font-bold tabular-nums
                                     text-cyber-text bg-cyber-panel border border-cyber-border rounded-lg px-2.5 py-1">
                            <svg class="w-3 h-3 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586
                                         a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            <?= $jumlah ?> artikel
                        </span>
                    </div>

                    <!-- Tanggal -->
                    <div class="hidden xs:block flex-shrink-0 w-32 text-right">
                        <p class="text-[11px] text-cyber-muted"><?= $date ?></p>
                    </div>

                    <!-- Aksi -->
                    <div class="flex-shrink-0 w-20 flex items-center justify-center gap-1.5">
                        <button type="button" title="Edit kategori"
                                class="btn-edit-kat w-8 h-8 flex items-center justify-center rounded-lg
                                       border border-cyber-border text-cyber-muted
                                       hover:text-cyber-orange hover:border-cyber-orange/50
                                       bg-cyber-panel transition-all"
                                onclick="openEditKat(<?= $id ?>, '<?= addslashes($kat['nama']) ?>')">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5
                                         m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                        </button>
                        <button type="button" title="Hapus kategori"
                                class="btn-del-kat w-8 h-8 flex items-center justify-center rounded-lg
                                       border border-cyber-border text-cyber-muted
                                       hover:text-red-400 hover:border-red-700/50
                                       bg-cyber-panel transition-all"
                                onclick="confirmDeleteKat(<?= $id ?>, '<?= addslashes($kat['nama']) ?>')">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0
                                         01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1
                                         1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Tombol Lihat Semua Data -->
    <?php if ($totalKat > 30): ?>
    <div class="mt-5 text-center" id="katShowMoreWrap">
        <button
            type="button"
            onclick="document.querySelectorAll('.extra-kat-row').forEach(el=>el.classList.remove('hidden'));document.getElementById('katShowMoreWrap').remove();"
            class="inline-flex items-center gap-2 border border-cyber-border text-cyber-muted
                   hover:text-cyber-orange hover:border-cyber-orange/50 bg-cyber-panel
                   text-sm font-semibold px-6 py-2.5 rounded-xl transition-all">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
            Lihat Semua Data
            <span class="text-[11px] text-cyber-dim ml-1">(<?= $totalKat ?> total)</span>
        </button>
    </div>
    <?php endif; ?>

<?php endif; ?>
