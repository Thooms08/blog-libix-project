<?php
$imagePath   = !empty($post['image']) ? $post['image'] : '';
$postUrl     = '/post/' . rawurlencode($post['slug']);
$createdDate = date('d M Y', strtotime($post['createdAt']));
$hasImage    = $imagePath !== '';
?>
<article class="bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-xl transition-all duration-300 border border-gray-100 flex flex-col h-full group">

    <!-- Thumbnail -->
    <a href="<?= $postUrl ?>" class="block relative overflow-hidden aspect-video bg-gray-50 flex-shrink-0">
        <?php if ($hasImage): ?>
            <img src="<?= htmlspecialchars($imagePath) ?>"
                 alt="<?= htmlspecialchars($post['title']) ?>"
                 class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                 loading="lazy"
                 onerror="this.closest('a').querySelector('.fl-no-img').classList.remove('hidden');this.remove()">
        <?php endif; ?>

        <!-- Placeholder "No Image"  |  tampil jika gambar kosong atau gagal load -->
        <div class="fl-no-img <?= $hasImage ? 'hidden' : '' ?> absolute inset-0 flex flex-col items-center justify-center gap-2 bg-gray-50">
            <svg class="w-10 h-10 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                      d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0
                         012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0
                         00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            <span class="text-xs font-medium text-gray-400 tracking-wide">No Image</span>
        </div>
    </a>

    <div class="p-6 flex flex-col flex-grow">

        <!-- Meta: tanggal & views -->
        <div class="flex items-center gap-4 text-xs text-gray-400 mb-3">
            <span class="flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <?= $createdDate ?>
            </span>
            <span>&bull;</span>
            <span class="flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7
                             -1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
                <?= number_format($post['views']) ?> Views
            </span>
        </div>

        <!-- Judul -->
        <h2 class="text-xl font-bold text-gray-900 group-hover:text-brand-500 transition-colors line-clamp-2 mb-3">
            <a href="<?= $postUrl ?>">
                <?= htmlspecialchars($post['title']) ?>
            </a>
        </h2>

        <!-- Excerpt -->
        <p class="text-gray-600 text-sm line-clamp-3 mb-6 flex-grow leading-relaxed">
            <?= htmlspecialchars($post['excerpt'] ?? strip_tags($post['content'])) ?>
        </p>

        <!-- CTA kecil -->
        <div class="pt-4 border-t border-gray-100 flex items-center justify-between">
            <a href="<?= $postUrl ?>"
               class="text-brand-500 font-bold text-sm inline-flex items-center gap-1 group-hover:gap-2 transition-all">
                Baca Selengkapnya
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </a>
        </div>

    </div>
</article>
