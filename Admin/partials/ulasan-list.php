<?php
/**
 * Partial: daftar ulasan untuk halaman Admin/ulasan.php
 * Variabel: $ulasans (array dari UlasanAdminLogic::getAll())
 */
if (empty($ulasans)): ?>

    <div class="bg-cyber-card border border-cyber-border rounded-2xl px-5 py-16 text-center">
        <svg class="w-12 h-12 mx-auto mb-3 text-cyber-dim" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                  d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0
                     01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
        </svg>
        <p class="text-cyber-muted font-semibold text-base mb-1">Belum ada ulasan</p>
        <p class="text-cyber-dim text-sm">Ulasan dari pembaca akan muncul di sini.</p>
    </div>

<?php else:
    $totalUlasan = count($ulasans);
?>

    <!-- Desktop view -->
    <div class="hidden md:block bg-cyber-card border border-cyber-border rounded-2xl overflow-hidden">
        <div class="grid grid-cols-[2fr_1fr_80px_100px_88px] gap-4 px-5 py-3 border-b border-cyber-border
                    bg-cyber-panel text-[10px] font-bold uppercase tracking-widest text-cyber-dim">
            <span>Ulasan</span>
            <span>Blog</span>
            <span class="text-center">Rating</span>
            <span class="text-center">Status</span>
            <span class="text-center">Aksi</span>
        </div>

        <div class="divide-y divide-cyber-border">
            <?php foreach ($ulasans as $i => $u):
                $id        = (int)  $u['id'];
                $isPublic  = (int)  $u['is_public'];
                $rating    = (int)  $u['rating'];
                $comment   = $u['comment'] ?? '';
                $postTitle = htmlspecialchars($u['post_title'] ?? '-', ENT_QUOTES, 'UTF-8');
                $postSlug  = htmlspecialchars($u['post_slug']  ?? '', ENT_QUOTES, 'UTF-8');
                $date      = date('d M Y, H:i', strtotime($u['created_at']));
                $ip        = htmlspecialchars($u['ip_address'] ?? '', ENT_QUOTES, 'UTF-8');
            ?>
                <div class="ulasan-row grid grid-cols-[2fr_1fr_80px_100px_88px] gap-4 px-5 py-4
                            items-start hover:bg-cyber-orange/[0.03] transition-colors group
                            <?= $i >= 30 ? 'hidden extra-ulasan-row' : '' ?>"
                     data-id="<?= $id ?>"
                     data-is-public="<?= $isPublic ?>"
                     data-comment="<?= htmlspecialchars(strtolower($comment), ENT_QUOTES, 'UTF-8') ?>"
                     data-post-title="<?= htmlspecialchars(strtolower($u['post_title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">

                    <!-- Komentar + meta -->
                    <div class="min-w-0">
                        <?php if ($comment !== ''): ?>
                            <p class="text-sm text-cyber-text leading-relaxed line-clamp-3">
                                "<?= htmlspecialchars($comment, ENT_QUOTES, 'UTF-8') ?>"
                            </p>
                        <?php else: ?>
                            <p class="text-sm text-cyber-dim italic">Tidak ada komentar</p>
                        <?php endif; ?>
                        <div class="flex items-center gap-2 mt-1.5 flex-wrap">
                            <span class="text-[11px] text-cyber-muted"><?= $date ?></span>
                            <?php if ($ip): ?>
                                <span class="text-[10px] text-cyber-dim font-mono bg-cyber-panel border border-cyber-border px-1.5 py-0.5 rounded">
                                    <?= $ip ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Judul artikel -->
                    <div class="min-w-0">
                        <?php if ($postSlug): ?>
                            <a href="/post/<?= $postSlug ?>" target="_blank"
                               class="text-xs text-cyber-muted hover:text-cyber-orange transition-colors line-clamp-2">
                                <?= $postTitle ?>
                            </a>
                        <?php else: ?>
                            <span class="text-xs text-cyber-dim">-</span>
                        <?php endif; ?>
                    </div>

                    <!-- Rating -->
                    <div class="flex items-center justify-center gap-0.5 pt-0.5">
                        <?php for ($s = 1; $s <= 5; $s++): ?>
                            <svg class="w-3.5 h-3.5 <?= $s <= $rating ? 'text-yellow-400' : 'text-cyber-dim' ?>"
                                 fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0
                                         1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688
                                         -1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539
                                         -1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461
                                         a1 1 0 00.951-.69l1.07-3.292z"/>
                            </svg>
                        <?php endfor; ?>
                    </div>

                    <!-- Status -->
                    <div class="flex justify-center pt-0.5">
                        <span class="status-badge inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full
                            <?= $isPublic
                                ? 'bg-green-500/15 text-green-400 border border-green-500/30'
                                : 'bg-slate-500/15 text-slate-400 border border-slate-500/30' ?>">
                            <span class="w-1.5 h-1.5 rounded-full <?= $isPublic ? 'bg-green-400' : 'bg-slate-400' ?>"></span>
                            <?= $isPublic ? 'Publik' : 'Privat' ?>
                        </span>
                    </div>

                    <!-- Aksi -->
                    <div class="flex items-center justify-center gap-1.5 pt-0.5">
                        <button type="button"
                                title="<?= $isPublic ? 'Sembunyikan ulasan' : 'Publikasikan ulasan' ?>"
                                onclick="togglePublic(<?= $id ?>)"
                                class="btn-toggle w-8 h-8 flex items-center justify-center rounded-lg border bg-cyber-panel transition-all
                                       <?= $isPublic
                                           ? 'text-green-400 border-green-700/50 hover:text-slate-400 hover:border-slate-700/50'
                                           : 'text-slate-400 border-slate-700/50 hover:text-green-400 hover:border-green-700/50' ?>">
                            <?php if ($isPublic): ?>
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7
                                             -1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                            <?php else: ?>
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7
                                             a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878
                                             l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59
                                             m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7
                                             a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                                </svg>
                            <?php endif; ?>
                        </button>

                        <button type="button" title="Hapus ulasan"
                                onclick="confirmDeleteUlasan(<?= $id ?>)"
                                class="w-8 h-8 flex items-center justify-center rounded-lg border border-cyber-border
                                       text-cyber-muted hover:text-red-400 hover:border-red-700/50 bg-cyber-panel transition-all">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7
                                         m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Mobile card view -->
    <div class="md:hidden space-y-3">
        <?php foreach ($ulasans as $i => $u):
            $id        = (int)  $u['id'];
            $isPublic  = (int)  $u['is_public'];
            $rating    = (int)  $u['rating'];
            $comment   = $u['comment'] ?? '';
            $postTitle = htmlspecialchars($u['post_title'] ?? '-', ENT_QUOTES, 'UTF-8');
            $postSlug  = htmlspecialchars($u['post_slug']  ?? '', ENT_QUOTES, 'UTF-8');
            $date      = date('d M Y, H:i', strtotime($u['created_at']));
        ?>
            <div class="ulasan-row bg-cyber-card border border-cyber-border rounded-2xl p-4
                        <?= $i >= 30 ? 'hidden extra-ulasan-row' : '' ?>"
                 data-id="<?= $id ?>"
                 data-is-public="<?= $isPublic ?>"
                 data-comment="<?= htmlspecialchars(strtolower($comment), ENT_QUOTES, 'UTF-8') ?>"
                 data-post-title="<?= htmlspecialchars(strtolower($u['post_title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">

                <div class="flex items-center justify-between gap-2 mb-3 flex-wrap">
                    <div class="flex gap-0.5">
                        <?php for ($s = 1; $s <= 5; $s++): ?>
                            <svg class="w-4 h-4 <?= $s <= $rating ? 'text-yellow-400' : 'text-cyber-dim' ?>"
                                 fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0
                                         1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688
                                         -1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539
                                         -1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461
                                         a1 1 0 00.951-.69l1.07-3.292z"/>
                            </svg>
                        <?php endfor; ?>
                    </div>
                    <span class="status-badge inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full
                        <?= $isPublic ? 'bg-green-500/15 text-green-400 border border-green-500/30' : 'bg-slate-500/15 text-slate-400 border border-slate-500/30' ?>">
                        <span class="w-1.5 h-1.5 rounded-full <?= $isPublic ? 'bg-green-400' : 'bg-slate-400' ?>"></span>
                        <?= $isPublic ? 'Publik' : 'Privat' ?>
                    </span>
                </div>

                <?php if ($comment !== ''): ?>
                    <p class="text-sm text-cyber-text leading-relaxed mb-2">
                        "<?= htmlspecialchars($comment, ENT_QUOTES, 'UTF-8') ?>"
                    </p>
                <?php else: ?>
                    <p class="text-sm text-cyber-dim italic mb-2">Tidak ada komentar</p>
                <?php endif; ?>

                <p class="text-[11px] text-cyber-muted mb-3">
                    <?= $date ?>
                    <?php if ($postSlug): ?>
                        &bull;
                        <a href="/post/<?= $postSlug ?>" target="_blank"
                           class="text-cyber-orange hover:underline"><?= $postTitle ?></a>
                    <?php endif; ?>
                </p>

                <div class="flex gap-2 pt-3 border-t border-cyber-border">
                    <button type="button" onclick="togglePublic(<?= $id ?>)"
                            class="btn-toggle flex-1 flex items-center justify-center gap-1.5 py-2 rounded-xl border
                                   text-xs font-semibold transition-all bg-cyber-panel
                                   <?= $isPublic
                                       ? 'border-green-700/50 text-green-400 hover:text-slate-400 hover:border-slate-700/50'
                                       : 'border-slate-700/50 text-slate-400 hover:text-green-400 hover:border-green-700/50' ?>">
                        <?= $isPublic ? 'Sembunyikan' : 'Publikasikan' ?>
                    </button>
                    <button type="button" onclick="confirmDeleteUlasan(<?= $id ?>)"
                            class="flex-1 flex items-center justify-center gap-1.5 py-2 rounded-xl border
                                   border-cyber-border text-cyber-muted hover:text-red-400
                                   hover:border-red-700/50 text-xs font-semibold transition-all bg-cyber-panel">
                        Hapus
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Tombol Lihat Semua Data -->
    <?php if ($totalUlasan > 30): ?>
    <div class="mt-5 text-center" id="ulasanShowMoreWrap">
        <button
            type="button"
            onclick="document.querySelectorAll('.extra-ulasan-row').forEach(el=>el.classList.remove('hidden'));document.getElementById('ulasanShowMoreWrap').remove();"
            class="inline-flex items-center gap-2 border border-cyber-border text-cyber-muted
                   hover:text-cyber-orange hover:border-cyber-orange/50 bg-cyber-panel
                   text-sm font-semibold px-6 py-2.5 rounded-xl transition-all">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
            Lihat Semua Data
            <span class="text-[11px] text-cyber-dim ml-1">(<?= $totalUlasan ?> total)</span>
        </button>
    </div>
    <?php endif; ?>

<?php endif; ?>
