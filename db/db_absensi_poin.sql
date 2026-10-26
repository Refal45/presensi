-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 26 Okt 2026 pada 05.05
-- Versi server: 10.4.32-MariaDB
-- Versi PHP: 8.3.23

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `db_absensi_poin`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `created_at`) VALUES
(1, 3, 'Tambah Mahasiswa', 'Menambahkan mahasiswa: Raifal Fadhal Zahran (10260006)', '::1', '2026-10-25 06:16:06'),
(2, 3, 'Edit Mahasiswa', 'Memperbarui data mahasiswa: Raifal Fadhal Zahran (10260006)', '::1', '2026-10-25 06:22:00'),
(3, 3, 'Hapus Mahasiswa', 'Menghapus mahasiswa: Raifal Fadhal Zahran (10260006)', '::1', '2026-10-25 08:15:03'),
(4, 3, 'Tambah Mahasiswa', 'Menambahkan mahasiswa: Refal (10260006)', '::1', '2026-10-25 08:16:06'),
(5, 3, 'Tambah Jadwal', 'Menambah jadwal: web (10.1A.01)', '::1', '2026-10-26 02:38:24'),
(6, 3, 'Edit Jadwal', 'Memperbarui jadwal: web (10.1A.01)', '::1', '2026-10-26 02:38:49'),
(7, 3, 'Edit Jadwal', 'Memperbarui jadwal: web (10.1A.01)', '::1', '2026-10-26 02:39:02'),
(8, 3, 'Tambah Admin', 'Menambahkan admin: jamal (jamal@gmail.com)', '::1', '2026-10-26 02:46:31'),
(9, 3, 'Tambah Absensi', 'Menambah absensi #1 untuk Refal (10260006?) pada 2026-10-26 — status hadir', '::1', '2026-10-26 02:59:58'),
(10, 3, 'Edit Absensi', 'Memperbarui absensi #1 pada 2026-10-26 — status hadir', '::1', '2026-10-26 03:00:32'),
(11, 3, 'Edit Absensi', 'Memperbarui absensi #1 pada 2026-10-26 — status hadir', '::1', '2026-10-26 03:00:50'),
(12, 3, 'Edit Absensi', 'Memperbarui absensi #1 pada 2026-10-26 — status hadir', '::1', '2026-10-26 03:11:22'),
(13, 3, 'Hapus Absensi', 'Menghapus absensi #1: Refal (10260006) — web — 2026-10-26 — status hadir', '::1', '2026-10-26 03:14:35'),
(14, 3, 'Tambah Absensi', 'Menambah absensi #2 untuk Refal (10260006?) pada 2026-10-26 — status hadir', '::1', '2026-10-26 03:16:10'),
(15, 3, 'Hapus Absensi', 'Menghapus absensi #2: Refal (10260006) — web — 2026-10-26 — status hadir', '::1', '2026-10-26 03:18:22'),
(16, 7, 'Login', 'Admin masuk ke dashboard', '::1', '2026-10-26 03:25:44'),
(17, 7, 'Tambah Absensi', 'Menambah absensi #3 untuk Refal (10260006?) pada 2026-10-26 — status hadir', '::1', '2026-10-26 03:27:53'),
(18, 7, 'Hapus Absensi', 'Menghapus absensi #3: Refal (10260006) — web — 2026-10-26 — status hadir', '::1', '2026-10-26 03:32:50'),
(19, 7, 'Tambah Absensi', 'Menambah absensi #4 untuk Refal (10260006?) pada 2026-10-26 — status hadir', '::1', '2026-10-26 03:33:10'),
(20, 7, 'Tambah Poin', 'Menambah transaksi poin #1 untuk Refal (10260006) sebesar 5 — Bonus kehadiran penuh', '::1', '2026-10-26 03:47:39'),
(21, 7, 'Hapus Poin', 'Menghapus transaksi poin #1 milik Refal (5 poin)', '::1', '2026-10-26 03:58:27'),
(22, 7, 'Tambah Poin', 'Menambah transaksi poin #2 untuk Refal (10260006) sebesar 2 — Bonus kehadiran penuh', '::1', '2026-10-26 03:59:09'),
(23, 7, 'Hapus Poin', 'Menghapus transaksi poin #2 milik Refal (2 poin)', '::1', '2026-10-26 04:09:58'),
(24, 7, 'Tambah Poin', 'Menambah transaksi poin #3 untuk Refal (10260006) sebesar -5 — Pelanggaran tata tertib', '::1', '2026-10-26 04:14:57'),
(25, 7, 'Hapus Poin', 'Menghapus transaksi poin #3 milik Refal (-5 poin)', '::1', '2026-10-26 04:15:04'),
(26, 7, 'Tambah Poin', 'Menambah transaksi poin #4 untuk Refal (10260006) sebesar 5 — Koreksi poin oleh admin', '::1', '2026-10-26 04:15:23'),
(27, 7, 'Tambah Poin', 'Menambah transaksi poin #5 untuk Refal (10260006) sebesar -3 — Tidak hadir tanpa keterangan (alpha)', '::1', '2026-10-26 04:21:57'),
(28, 7, 'Tambah Poin', 'Menambah transaksi poin #6 untuk Refal (10260006) sebesar -2 — Pelanggaran tata tertib', '::1', '2026-10-26 04:24:29'),
(29, 7, 'Hapus Poin', 'Menghapus transaksi poin #6 milik Refal (-2 poin)', '::1', '2026-10-26 04:31:32'),
(30, 7, 'Hapus Poin', 'Menghapus transaksi poin #5 milik Refal (-3 poin)', '::1', '2026-10-26 04:31:39'),
(31, 7, 'Hapus Poin', 'Menghapus transaksi poin #4 milik Refal (5 poin)', '::1', '2026-10-26 04:41:49'),
(32, 7, 'Tambah Poin', 'Menambah transaksi poin #7 untuk Refal (10260006) sebesar 5 — Koreksi poin oleh admin', '::1', '2026-10-26 04:43:17'),
(33, 7, 'Tambah Poin', 'Menambah transaksi poin #8 untuk Refal (10260006) sebesar -3 — Terlambat masuk kelas', '::1', '2026-10-26 04:48:21'),
(34, 7, 'Hapus Poin', 'Menghapus transaksi poin #8 milik Refal (-3 poin)', '::1', '2026-10-26 04:55:32'),
(35, 7, 'Hapus Poin', 'Menghapus transaksi poin #7 milik Refal (5 poin). Shield terpakai tidak dikembalikan.', '::1', '2026-10-26 05:01:12'),
(36, 7, 'Tambah Poin', 'Transaksi poin #9 untuk Refal (10260006) perubahan=5, credit_delta=0, shield_delta=5, shield_used=0, sisa_penalti=0 — Koreksi poin oleh admin', '::1', '2026-10-26 05:01:22'),
(37, 7, 'Tambah Poin', 'Transaksi poin #10 untuk Refal (10260006) perubahan=-5, credit_delta=0, shield_delta=-5, shield_used=5, sisa_penalti=0 — Tidak hadir tanpa keterangan (alpha)', '::1', '2026-10-26 05:01:40');

-- --------------------------------------------------------

--
-- Struktur dari tabel `attendances`
--

CREATE TABLE `attendances` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `schedule_id` bigint(20) UNSIGNED NOT NULL,
  `attendance_date` date NOT NULL,
  `check_in_at` datetime DEFAULT NULL,
  `check_out_at` datetime DEFAULT NULL,
  `status` enum('hadir','terlambat','izin','sakit','alpha') NOT NULL DEFAULT 'hadir',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `attendances`
--

INSERT INTO `attendances` (`id`, `user_id`, `schedule_id`, `attendance_date`, `check_in_at`, `check_out_at`, `status`, `notes`, `created_at`, `updated_at`) VALUES
(4, 6, 1, '2026-10-26', '2026-10-26 09:32:00', '2026-10-26 12:38:00', 'hadir', '', '2026-10-26 03:33:10', '2026-10-26 03:33:10');

-- --------------------------------------------------------

--
-- Struktur dari tabel `leave_requests`
--

CREATE TABLE `leave_requests` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `schedule_id` bigint(20) UNSIGNED NOT NULL,
  `request_date` date NOT NULL,
  `request_type` enum('izin','sakit') NOT NULL,
  `reason` text NOT NULL,
  `attachment_path` varchar(255) DEFAULT NULL,
  `status` enum('menunggu','disetujui','ditolak') NOT NULL DEFAULT 'menunggu',
  `reviewed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `review_notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `nfc_cards`
--

CREATE TABLE `nfc_cards` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `card_uid` varchar(100) NOT NULL,
  `label` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `registered_at` timestamp NULL DEFAULT current_timestamp(),
  `last_used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `email` varchar(150) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `point_transactions`
--

CREATE TABLE `point_transactions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `attendance_id` bigint(20) UNSIGNED DEFAULT NULL,
  `weekly_balance_id` bigint(20) UNSIGNED NOT NULL,
  `points_change` int(11) NOT NULL,
  `credit_delta` int(11) NOT NULL DEFAULT 0,
  `shield_delta` int(11) NOT NULL DEFAULT 0,
  `shield_used` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `remaining_penalty` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `reason` varchar(255) NOT NULL,
  `changed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `point_transactions`
--

INSERT INTO `point_transactions` (`id`, `user_id`, `attendance_id`, `weekly_balance_id`, `points_change`, `credit_delta`, `shield_delta`, `shield_used`, `remaining_penalty`, `reason`, `changed_by`, `created_at`) VALUES
(9, 6, NULL, 1, 5, 0, 5, 0, 0, '0', 7, '0000-00-00 00:00:00'),
(10, 6, NULL, 1, -5, 0, -5, 5, 0, '0', 7, '0000-00-00 00:00:00');

-- --------------------------------------------------------

--
-- Struktur dari tabel `schedules`
--

CREATE TABLE `schedules` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `course_name` varchar(100) NOT NULL,
  `class_name` varchar(50) NOT NULL,
  `lecturer_name` varchar(100) DEFAULT NULL,
  `day_of_week` enum('senin','selasa','rabu','kamis','jumat','sabtu','minggu') NOT NULL,
  `start_time` time NOT NULL,
  `late_after` time NOT NULL,
  `end_time` time NOT NULL,
  `attendance_code` varchar(20) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `schedules`
--

INSERT INTO `schedules` (`id`, `course_name`, `class_name`, `lecturer_name`, `day_of_week`, `start_time`, `late_after`, `end_time`, `attendance_code`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'web', '10.1A.01', 'wawan', 'senin', '09:37:00', '10:00:00', '12:38:00', 'R5DF', 1, '2026-10-26 02:38:24', '2026-10-26 02:39:02');

-- --------------------------------------------------------

--
-- Struktur dari tabel `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `system_settings`
--

CREATE TABLE `system_settings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` varchar(255) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `nim` varchar(30) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','mahasiswa') NOT NULL DEFAULT 'mahasiswa',
  `class_name` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `users`
--

INSERT INTO `users` (`id`, `nim`, `name`, `email`, `password`, `role`, `class_name`, `is_active`, `email_verified_at`, `remember_token`, `created_at`, `updated_at`) VALUES
(3, NULL, 'Administrator', 'admin.presensi@presensimahasiswa.com', '$2y$10$FM4GY13dZtlHyLvRx8usvOUyABxojbmaD707dWK6dRlM6CXL0weYG', 'admin', NULL, 1, NULL, NULL, '2026-10-25 05:17:44', '2026-10-25 05:43:22'),
(6, '10260006', 'Refal', 'raifalzahran@gmail.com', '$2y$10$TGUWTSp0b0FvrVmNWYYvKu50/TtpHKS.e7BLw551IjYXs6zv/av2a', 'mahasiswa', '10.1A.01', 1, NULL, NULL, '2026-10-25 08:16:06', '2026-10-25 08:16:06'),
(7, NULL, 'jamal', 'jamal@gmail.com', '$2y$10$cnlxtwR80GamLzFkD/UK0OrjtYTr3ozhybBXUQuMR3nqRFTtPbmZW', 'admin', NULL, 1, NULL, NULL, '2026-10-26 02:46:31', '2026-10-26 02:49:24');

-- --------------------------------------------------------

--
-- Struktur dari tabel `weekly_point_balances`
--

CREATE TABLE `weekly_point_balances` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `week_start` date NOT NULL,
  `week_end` date NOT NULL,
  `initial_points` int(10) UNSIGNED NOT NULL DEFAULT 100,
  `current_points` int(10) UNSIGNED NOT NULL DEFAULT 100,
  `shield_points` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `weekly_point_balances`
--

INSERT INTO `weekly_point_balances` (`id`, `user_id`, `week_start`, `week_end`, `initial_points`, `current_points`, `shield_points`, `created_at`, `updated_at`) VALUES
(1, 6, '2026-10-26', '2026-11-01', 100, 100, 0, '2026-10-26 03:47:39', '2026-10-26 05:01:40');

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_activity_date` (`created_at`);

--
-- Indeks untuk tabel `attendances`
--
ALTER TABLE `attendances`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_attendance` (`user_id`,`schedule_id`,`attendance_date`),
  ADD KEY `schedule_id` (`schedule_id`),
  ADD KEY `idx_attendance_date` (`attendance_date`),
  ADD KEY `idx_attendance_status` (`status`);

--
-- Indeks untuk tabel `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `schedule_id` (`schedule_id`),
  ADD KEY `reviewed_by` (`reviewed_by`),
  ADD KEY `idx_leave_status` (`status`,`request_date`);

--
-- Indeks untuk tabel `nfc_cards`
--
ALTER TABLE `nfc_cards`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_card_uid` (`card_uid`),
  ADD KEY `idx_nfc_user` (`user_id`);

--
-- Indeks untuk tabel `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`email`);

--
-- Indeks untuk tabel `point_transactions`
--
ALTER TABLE `point_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `attendance_id` (`attendance_id`),
  ADD KEY `weekly_balance_id` (`weekly_balance_id`),
  ADD KEY `changed_by` (`changed_by`),
  ADD KEY `idx_transaction_user_date` (`user_id`,`created_at`);

--
-- Indeks untuk tabel `schedules`
--
ALTER TABLE `schedules`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sessions_user` (`user_id`),
  ADD KEY `idx_sessions_activity` (`last_activity`);

--
-- Indeks untuk tabel `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `nim` (`nim`);

--
-- Indeks untuk tabel `weekly_point_balances`
--
ALTER TABLE `weekly_point_balances`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_week` (`user_id`,`week_start`),
  ADD KEY `idx_weekly_points` (`week_start`,`current_points`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT untuk tabel `attendances`
--
ALTER TABLE `attendances`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT untuk tabel `leave_requests`
--
ALTER TABLE `leave_requests`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `nfc_cards`
--
ALTER TABLE `nfc_cards`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `point_transactions`
--
ALTER TABLE `point_transactions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT untuk tabel `schedules`
--
ALTER TABLE `schedules`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT untuk tabel `weekly_point_balances`
--
ALTER TABLE `weekly_point_balances`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `attendances`
--
ALTER TABLE `attendances`
  ADD CONSTRAINT `attendances_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `attendances_ibfk_2` FOREIGN KEY (`schedule_id`) REFERENCES `schedules` (`id`);

--
-- Ketidakleluasaan untuk tabel `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD CONSTRAINT `leave_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `leave_requests_ibfk_2` FOREIGN KEY (`schedule_id`) REFERENCES `schedules` (`id`),
  ADD CONSTRAINT `leave_requests_ibfk_3` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `nfc_cards`
--
ALTER TABLE `nfc_cards`
  ADD CONSTRAINT `nfc_cards_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `point_transactions`
--
ALTER TABLE `point_transactions`
  ADD CONSTRAINT `point_transactions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `point_transactions_ibfk_2` FOREIGN KEY (`attendance_id`) REFERENCES `attendances` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `point_transactions_ibfk_3` FOREIGN KEY (`weekly_balance_id`) REFERENCES `weekly_point_balances` (`id`),
  ADD CONSTRAINT `point_transactions_ibfk_4` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `weekly_point_balances`
--
ALTER TABLE `weekly_point_balances`
  ADD CONSTRAINT `weekly_point_balances_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
