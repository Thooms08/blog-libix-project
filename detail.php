<?php
declare(strict_types=1);

/**
 * detail.php — legacy redirect, semua traffic sudah di-handle blog.php.
 * File ini dipertahankan untuk backward-compat URL lama.
 */
require_once __DIR__ . '/config.php';

$slug = trim($_GET['slug'] ?? '');

if ($slug === '') {
    header('Location: /', true, 301);
    exit();
}

// Redirect permanen ke URL kanonik /post/{slug}
// Validasi format slug sebelum redirect (mencegah header injection)
if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $slug)) {
    header('Location: /', true, 301);
    exit();
}

header('Location: /post/' . rawurlencode($slug), true, 301);
exit();
