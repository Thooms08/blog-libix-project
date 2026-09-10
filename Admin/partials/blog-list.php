<?php
/**
 * Partial: daftar blog untuk halaman Admin/blog.php
 * Variabel: $posts (array dari BlogAdminLogic::getAll())
 */
if (empty($posts)): ?>

    <div class="bg-cyber-card border border-cyber-border rounded-2xl px-5 py-16 text-center">
        <svg class="w-14 h-14 text-cyber-dim mb-4 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        <p class="text-cyber-muted font-semibold text-base mb-1">Belum ada blog</p>
        <p class="text-cyber-dim text-sm mb-5">Mulai tulis artikel pertama kamu sekarang.</p>
        <a href="/Admin/blog-detail"
           class="inline-flex items-center gap-2 bg-cyber-orange hover:bg-cyber-orangeL
                  text-white text-sm font-bold px-5 py-2.5 rounded-xl transition-all"
           style="box-shadow:0 0 16px #06b6d430">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Buat Blog Baru
        </a>
    </div>

<?php else:
    $totalBlog = count($posts);
?>

    <!-- Desktop table view -->
    <div class="hidden md:block bg-cyber-card border border-cyber-border rounded-2xl overflow-hidden">
        <div class="grid grid-cols-[2fr_1fr_120px_96px] gap-4 px-5 py-3 border-b border-cyber-border
                    bg-cyber-panel text-[10px] font-bold uppercase tracking-widest text-cyber-dim">
            <span>Judul Blog</span>
            <span>Kategori</span>
            <span class="text-center">Views</span>
            <span class="text-center">Aksi</span>
        </div>

        <div class="divide-y divide-cyber-border">
            <?php foreach ($posts as $i => $post):
                $title   = htmlspecialchars($post['title']          ?? '', ENT_QUOTES, 'UTF-8');
                $katName = htmlspecialchars($post['kategori_names'] ?? '-', ENT_QUOTES, 'UTF-8');
                $views   = number_format((int) ($post['views'] ?? 0));
                $id      = (int) $post['id'];
                $date    = date('d M Y', strtotime($post['createdAt']));
                $imgSrc  = !empty($post['image']) ? htmlspecialchars($post['image'], ENT_QUOTES, 'UTF-8') : '';
            ?>
                <div class="blog-row grid grid-cols-[2fr_1fr_120px_96px] gap-4 px-5 py-3
                            items-center hover:bg-cyber-orange/[0.04] transition-colors group
                            <?= $i >= 30 ? 'hidden extra-blog-row' : '' ?>"
                     data-id="<?= $id ?>"
                     data-title="<?= $title ?>">

                    <!-- Judul + thumbnail -->
                    <div class="flex items-center gap-2 min-w-0">
                        <div class="w-10 h-10 rounded-lg overflow-hidden flex-shrink-0 bg-cyber-panel border border-cyber-border"
                             style="min-width:2.5rem;min-height:2.5rem;max-width:2.5rem;max-height:2.5rem;">
                            <?php if ($imgSrc): ?>
                                <img src="<?= $imgSrc ?>" alt="" loading="lazy"
                                     style="width:100%;height:100%;object-fit:cover;display:block;"
                                     onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                                <div style="display:none;width:100%;height:100%;align-items:center;justify-content:center;">
                                    <svg class="w-4 h-4 text-cyber-dim" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                </div>
                            <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center">
                                    <svg class="w-4 h-4 text-cyber-dim" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-cyber-text group-hover:text-cyber-orange transition-colors line-clamp-1">
                                <?= $title ?>
                            </p>
                            <p class="text-[11px] text-cyber-muted mt-0.5"><?= $date ?></p>
                        </div>
                    </div>

                    <!-- Kategori -->
                    <div class="min-w-0 self-center">
                        <p class="text-xs text-cyber-muted line-clamp-1"><?= $katName ?></p>
                    </div>

                    <!-- Views -->
                    <div class="text-center self-center">
                        <span class="inline-flex items-center gap-1 text-xs font-bold tabular-nums text-cyber-text
                                     bg-cyber-panel border border-cyber-border rounded-lg px-2.5 py-1">
                            <svg class="w-3 h-3 text-cyber-orange" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7
                                         -1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            <?= $views ?>
                        </span>
                    </div>

                    <!-- Aksi -->
                    <div class="flex items-center justify-center gap-1.5 self-center">
                        <a href="/Admin/blog-detail?slug=<?= htmlspecialchars($post['slug'], ENT_QUOTES, 'UTF-8') ?>"
                           title="Edit artikel"
                           class="w-8 h-8 flex items-center justify-center rounded-lg border border-cyber-border
                                  text-cyber-muted hover:text-cyber-orange hover:border-cyber-orange/50
                                  bg-cyber-panel transition-all">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5
                                         m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                        </a>
                        <button type="button" title="Hapus artikel"
                                onclick="confirmDelete(<?= $id ?>, '<?= addslashes($post['title']) ?>')"
                                class="w-8 h-8 flex items-center justify-center rounded-lg border border-cyber-border
                                       text-cyber-muted hover:text-red-400 hover:border-red-700/50
                                       bg-cyber-panel transition-all">
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

    <!-- Mobile card view -->
    <div class="md:hidden space-y-3">
        <?php foreach ($posts as $i => $post):
            $title   = htmlspecialchars($post['title']          ?? '', ENT_QUOTES, 'UTF-8');
            $katName = htmlspecialchars($post['kategori_names'] ?? '-', ENT_QUOTES, 'UTF-8');
            $views   = number_format((int) ($post['views'] ?? 0));
            $id      = (int) $post['id'];
            $date    = date('d M Y', strtotime($post['createdAt']));
            $imgSrc  = !empty($post['image']) ? htmlspecialchars($post['image'], ENT_QUOTES, 'UTF-8') : '';
        ?>
            <div class="blog-row bg-cyber-card border border-cyber-border rounded-2xl overflow-hidden
                        <?= $i >= 30 ? 'hidden extra-blog-row' : '' ?>"
                 data-id="<?= $id ?>"
                 data-title="<?= $title ?>">

                <?php if ($imgSrc): ?>
                    <div class="w-full aspect-video bg-cyber-panel overflow-hidden flex-shrink-0">
                        <img src="<?= $imgSrc ?>" alt="<?= $title ?>"
                             class="w-full h-full object-cover" loading="lazy"
                             onerror="this.parentElement.style.display='none'">
                    </div>
                <?php endif; ?>

                <div class="p-4">
                    <p class="text-sm font-bold text-cyber-text line-clamp-2 mb-1"><?= $title ?></p>
                    <p class="text-[11px] text-cyber-muted mb-3"><?= $date ?> &bull; <?= $views ?> views</p>
                    <?php if ($katName !== '-'): ?>
                        <p class="text-[11px] text-cyber-orange/70 mb-3 line-clamp-1"><?= $katName ?></p>
                    <?php endif; ?>
                    <div class="flex gap-2 pt-3 border-t border-cyber-border">
                        <a href="/Admin/blog-detail?slug=<?= htmlspecialchars($post['slug'], ENT_QUOTES, 'UTF-8') ?>"
                           class="flex-1 flex items-center justify-center gap-1.5 py-2 rounded-xl border
                                  border-cyber-border text-cyber-muted hover:text-cyber-orange
                                  hover:border-cyber-orange/50 text-xs font-semibold transition-all bg-cyber-panel">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5
                                         m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                            Edit
                        </a>
                        <button type="button"
                                onclick="confirmDelete(<?= $id ?>, '<?= addslashes($post['title']) ?>')"
                                class="flex-1 flex items-center justify-center gap-1.5 py-2 rounded-xl border
                                       border-cyber-border text-cyber-muted hover:text-red-400
                                       hover:border-red-700/50 text-xs font-semibold transition-all bg-cyber-panel">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0
                                         01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1
                                         1 0 00-1 1v3M4 7h16"/>
                            </svg>
                            Hapus
                        </button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Tombol Lihat Semua Data -->
    <?php if ($totalBlog > 30): ?>
    <div class="mt-5 text-center" id="blogShowMoreWrap">
        <button
            type="button"
            onclick="document.querySelectorAll('.extra-blog-row').forEach(el=>el.classList.remove('hidden'));document.getElementById('blogShowMoreWrap').remove();"
            class="inline-flex items-center gap-2 border border-cyber-border text-cyber-muted
                   hover:text-cyber-orange hover:border-cyber-orange/50 bg-cyber-panel
                   text-sm font-semibold px-6 py-2.5 rounded-xl transition-all">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
            Lihat Semua Data
            <span class="text-[11px] text-cyber-dim ml-1">(<?= $totalBlog ?> total)</span>
        </button>
    </div>
    <?php endif; ?>

<?php endif; ?>
