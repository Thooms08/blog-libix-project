-- phpMyAdmin SQL Dump
-- version 5.2.3deb1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Waktu pembuatan: 10 Sep 2026 pada 04.44
-- Versi server: 8.4.10-0ubuntu0.26.04.1
-- Versi PHP: 8.5.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Basis data: `flavoryi_blog`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `Kategori`
--

CREATE TABLE `Kategori` (
  `id` int NOT NULL,
  `nama` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updated_at` datetime(3) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- (data dihapus)


-- --------------------------------------------------------

--
-- Struktur dari tabel `Post`
--

CREATE TABLE `Post` (
  `id` int NOT NULL,
  `title` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `excerpt` text COLLATE utf8mb4_unicode_ci,
  `content` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `image` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `views` int NOT NULL DEFAULT '0',
  `createdAt` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updatedAt` datetime(3) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- (data dihapus)


-- --------------------------------------------------------

--
-- Struktur dari tabel `Ulasan`
--

CREATE TABLE `Ulasan` (
  `id` int NOT NULL,
  `post_id` int NOT NULL,
  `rating` int NOT NULL,
  `comment` text COLLATE utf8mb4_unicode_ci,
  `ip_address` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_public` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updated_at` datetime(3) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- (data dihapus)


-- --------------------------------------------------------

--
-- Struktur dari tabel `User`
--

CREATE TABLE `User` (
  `id` int NOT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `username` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `createdAt` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updatedAt` datetime(3) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- (data dihapus)


-- --------------------------------------------------------

--
-- Struktur dari tabel `_KategoriToPost`
--

CREATE TABLE `_KategoriToPost` (
  `A` int NOT NULL,
  `B` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- (data dihapus)


--
-- Indeks untuk tabel yang dibuang
--

--
-- Indeks untuk tabel `Kategori`
--
ALTER TABLE `Kategori`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `Kategori_nama_key` (`nama`);

--
-- Indeks untuk tabel `Post`
--
ALTER TABLE `Post`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `Post_slug_key` (`slug`);

--
-- Indeks untuk tabel `Ulasan`
--
ALTER TABLE `Ulasan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `Ulasan_post_id_fkey` (`post_id`);

--
-- Indeks untuk tabel `User`
--
ALTER TABLE `User`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `User_username_key` (`username`),
  ADD UNIQUE KEY `User_email_key` (`email`);

--
-- Indeks untuk tabel `_KategoriToPost`
--
ALTER TABLE `_KategoriToPost`
  ADD UNIQUE KEY `_KategoriToPost_AB_unique` (`A`,`B`),
  ADD KEY `_KategoriToPost_B_index` (`B`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `Kategori`
--
ALTER TABLE `Kategori`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT untuk tabel `Post`
--
ALTER TABLE `Post`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=57;

--
-- AUTO_INCREMENT untuk tabel `Ulasan`
--
ALTER TABLE `Ulasan`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `User`
--
ALTER TABLE `User`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `Ulasan`
--
ALTER TABLE `Ulasan`
  ADD CONSTRAINT `Ulasan_post_id_fkey` FOREIGN KEY (`post_id`) REFERENCES `Post` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `_KategoriToPost`
--
ALTER TABLE `_KategoriToPost`
  ADD CONSTRAINT `_KategoriToPost_A_fkey` FOREIGN KEY (`A`) REFERENCES `Kategori` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `_KategoriToPost_B_fkey` FOREIGN KEY (`B`) REFERENCES `Post` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
