<?php

/**
 * aturan.php
 * Halaman Aturan Poin — Presensi NFC | Sistem Kehadiran Mahasiswa
 * ------------------------------------------------------------
 * VERSI PUBLIK — Layout konsisten dengan beranda.php & tentang.php
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/koneksi.php';

/* ============================================================
   URL NAVIGASI
   ============================================================ */
$homeUrl  = 'beranda.php';
$loginUrl = 'auth/login.php';

/* ============================================================
   MENU UTAMA
   ============================================================ */
$menu = [
    ['href' => 'beranda.php',    'label' => 'Beranda',        'desc' => 'Halaman utama',      'icon' => 'fa-home'],
    ['href' => 'tentang.php',    'label' => 'Tentang Sistem', 'desc' => 'Informasi aplikasi', 'icon' => 'fa-info-circle'],
    ['href' => 'cara-kerja.php', 'label' => 'Cara Kerja',     'desc' => 'Alur presensi NFC',  'icon' => 'fa-cogs'],
    ['href' => 'aturan.php',     'label' => 'Aturan Poin',    'desc' => 'Kebijakan disiplin', 'icon' => 'fa-clipboard-list'],
    ['href' => 'faq.php',        'label' => 'FAQ',            'desc' => 'Pertanyaan umum',    'icon' => 'fa-question-circle'],
];

/* ============================================================
   PRINSIP DASAR
   ============================================================ */
$prinsipPoin = [
    [
        'icon'  => 'fa-seedling',
        'title' => 'Poin Awal',
        'desc'  => 'Setiap mahasiswa memulai minggu baru dengan saldo 100 poin penuh.',
    ],
    [
        'icon'  => 'fa-rotate',
        'title' => 'Reset Mingguan',
        'desc'  => 'Poin di-reset setiap awal minggu. Kedisiplinan minggu lalu tidak permanen.',
    ],
    [
        'icon'  => 'fa-receipt',
        'title' => 'Tercatat Transparan',
        'desc'  => 'Setiap perubahan poin dicatat sebagai transaksi yang bisa dilihat mahasiswa.',
    ],
    [
        'icon'  => 'fa-calendar-check',
        'title' => 'Deteksi Jadwal Otomatis',
        'desc'  => 'Mesin mengenali jadwal kelas aktif agar tap tidak bentrok dengan kelas lain.',
    ],
];

/* ============================================================
   MEKANISME DUA TAP
   ============================================================ */
$mekanismeTap = [
    [
        'icon'  => 'fa-right-to-bracket',
        'color' => '#22d3ee',
        'title' => 'Tap Masuk',
        'time'  => 'Saat datang ke kelas',
        'desc'  => 'Tempelkan kartu NFC saat tiba di kelas. Sistem mencatat jam kedatangan Anda dan menandai status kehadiran.',
    ],
    [
        'icon'  => 'fa-right-from-bracket',
        'color' => '#ffd966',
        'title' => 'Tap Keluar',
        'time'  => 'Saat pulang dari kampus',
        'desc'  => 'Tempelkan kartu NFC saat meninggalkan kampus. Jika lupa tap keluar, sistem akan mencatat penalti khusus sesuai aturan.',
    ],
];

/* ============================================================
   ATURAN PER STATUS KEHADIRAN (SESUAI GAMBAR)
   ============================================================ */
$aturanStatus = [
    [
        'icon'      => 'fa-check-circle',
        'color'     => '#28a745',
        'title'     => 'Hadir Tepat Waktu & Absen Keluar',
        'poin'      => '0',
        'tipe'      => 'Tetap',
        'keterangan' => 'Tap masuk sebelum batas waktu terlambat dan melakukan tap keluar.',
    ],
    [
        'icon'      => 'fa-clock',
        'color'     => '#ffc107',
        'title'     => 'Terlambat Masuk 1–30 Menit',
        'poin'      => '-2',
        'tipe'      => 'Berkurang',
        'keterangan' => 'Tap masuk melewati batas waktu perkuliahan (1 s/d 30 menit).',
    ],
    [
        'icon'      => 'fa-person-walking-arrow-right',
        'color'     => '#fd7e14',
        'title'     => 'Hadir Tepat Waktu, Lupa Absen Keluar',
        'poin'      => '0',
        'tipe'      => 'Tetap',
        'keterangan' => 'Hadir tepat waktu tetapi tidak melakukan tap keluar. Tidak ada pengurangan poin.',
    ],
    [
        'icon'      => 'fa-triangle-exclamation',
        'color'     => '#e83e8c',
        'title'     => 'Terlambat Masuk & Lupa Absen Keluar',
        'poin'      => '-4',
        'tipe'      => 'Berkurang',
        'keterangan' => 'Terlambat masuk (-2) dan juga lupa absen keluar (-2). Total pengurangan 4 poin.',
    ],
    [
        'icon'      => 'fa-times-circle',
        'color'     => '#6c757d',
        'title'     => 'Tidak Melakukan Absen Masuk',
        'poin'      => '0',
        'tipe'      => 'Tidak Otomatis',
        'keterangan' => 'Tidak ada pengurangan poin otomatis dari sistem. (Dapat ditindaklanjuti manual oleh pengelola).',
    ],
];

/* ============================================================
   ATURAN TAMBAHAN
   ============================================================ */
$aturanTambahan = [
    [
        'icon'  => 'fa-id-card-clip',
        'title' => 'Kartu Wajib Dibawa',
        'desc'  => 'Mahasiswa wajib membawa kartu NFC setiap mengikuti kelas. Kartu hilang harus segera dilaporkan.',
    ],
    [
        'icon'  => 'fa-user-lock',
        'title' => 'Kartu Tidak Boleh Dipinjamkan',
        'desc'  => 'Meminjamkan kartu kepada mahasiswa lain untuk presensi merupakan pelanggaran serius.',
    ],
    [
        'icon'  => 'fa-hourglass-half',
        'title' => 'Tap Hanya di Jam Kelas',
        'desc'  => 'Tap masuk & keluar hanya diterima saat jadwal kelas sedang aktif, sesuai deteksi mesin.',
    ],
    [
        'icon'  => 'fa-calendar-check',
        'title' => 'Izin Maksimal 1x24 Jam',
        'desc'  => 'Pengajuan izin/sakit wajib dilakukan maksimal 24 jam setelah jadwal kelas berakhir.',
    ],
    [
        'icon'  => 'fa-paperclip',
        'title' => 'Bukti Pendukung Wajib',
        'desc'  => 'Izin/sakit harus disertai surat keterangan dokter atau surat izin resmi yang sah.',
    ],
    [
        'icon'  => 'fa-shield-halved',
        'title' => 'Tidak Dapat Dimanipulasi',
        'desc'  => 'Setiap tap kartu tervalidasi sistem. Manipulasi data tercatat di log aktivitas.',
    ],
];

/* ============================================================
   CONTOH PERHITUNGAN MINGGUAN (SESUAI GAMBAR)
   ============================================================ */
$contohHitung = [
    ['hari' => 'Senin',  'status' => 'Hadir tepat waktu', 'poin' => 0,  'saldo' => 100, 'color' => '#28a745', 'icon' => 'fa-check-circle'],
    ['hari' => 'Selasa', 'status' => 'Terlambat 15 menit', 'poin' => -2, 'saldo' => 98,  'color' => '#ffc107', 'icon' => 'fa-clock'],
    ['hari' => 'Rabu',   'status' => 'Hadir tepat waktu, lupa absen keluar', 'poin' => 0,  'saldo' => 98,  'color' => '#fd7e14', 'icon' => 'fa-person-walking-arrow-right'],
    ['hari' => 'Kamis',  'status' => 'Terlambat 20 menit dan lupa absen keluar', 'poin' => -4, 'saldo' => 94,  'color' => '#e83e8c', 'icon' => 'fa-triangle-exclamation'],
];
$saldoAwal = 100;
$saldoAkhir = 94;

/* ============================================================
   TINGKAT KONSEKUENSI BERDASARKAN SALDO POIN
   ============================================================ */
$konsekuensi = [
    [
        'range' => '80 - 100',
        'label' => 'Aman',
        'color' => '#28a745',
        'icon'  => 'fa-shield-heart',
        'desc'  => 'Kedisiplinan baik. Tidak ada tindakan khusus.',
    ],
    [
        'range' => '60 - 79',
        'label' => 'Peringatan',
        'color' => '#ffc107',
        'icon'  => 'fa-triangle-exclamation',
        'desc'  => 'Peringatan tertulis dari pengelola akademik.',
    ],
    [
        'range' => '40 - 59',
        'label' => 'Pembinaan',
        'color' => '#fd7e14',
        'icon'  => 'fa-user-clock',
        'desc'  => 'Pemanggilan pembinaan oleh dosen wali atau pengelola.',
    ],
    [
        'range' => '0 - 39',
        'label' => 'Sanksi Akademik',
        'color' => '#dc3545',
        'icon'  => 'fa-gavel',
        'desc'  => 'Sanksi akademik sesuai kebijakan institusi masing-masing.',
    ],
];
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aturan Poin — Presensi NFC | Kehadiran Mahasiswa</title>
    <meta name="description" content="Aturan dan kebijakan sistem poin kedisiplinan Presensi NFC.">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <style>
        :root {
            --bg-body: #f4f7fc;
            --bg-card: #ffffff;
            --bg-navbar: transparent;
            --bg-navbar-scroll: rgba(13, 27, 42, 0.88);
            --bg-footer: #0d1b2a;
            --bg-hero-overlay: rgba(0, 0, 0, 0.50);
            --bg-stat: #ffffff;
            --text-primary: #1a2634;
            --text-secondary: #4a5a6a;
            --text-white: #ffffff;
            --text-muted: rgba(255, 255, 255, 0.75);
            --border-color: rgba(255, 255, 255, 0.18);
            --shadow-card: 0 8px 30px rgba(0, 0, 0, 0.06);
            --shadow-card-hover: 0 16px 48px rgba(0, 0, 0, 0.10);
            --btn-primary-bg: #ffd966;
            --btn-primary-color: #1a2634;
            --btn-primary-hover: #ffed4a;
            --accent: #ffd966;
            --stat-number: #0d6efd;
            --link-color: rgba(255, 255, 255, 0.7);
            --link-hover: #ffffff;
        }

        body.dark-mode {
            --bg-body: #121926;
            --bg-card: #1e2a3a;
            --bg-navbar: transparent;
            --bg-navbar-scroll: rgba(18, 25, 38, 0.94);
            --bg-footer: #0a1118;
            --bg-hero-overlay: rgba(0, 0, 0, 0.68);
            --bg-stat: #1e2a3a;
            --text-primary: #e8edf3;
            --text-secondary: #b0c0d0;
            --text-white: #ffffff;
            --text-muted: rgba(200, 200, 200, 0.75);
            --border-color: rgba(255, 255, 255, 0.10);
            --shadow-card: 0 8px 30px rgba(0, 0, 0, 0.3);
            --shadow-card-hover: 0 16px 48px rgba(0, 0, 0, 0.5);
            --btn-primary-bg: #f0c850;
            --btn-primary-color: #1a2634;
            --btn-primary-hover: #ffd966;
            --accent: #f0c850;
            --stat-number: #6ea8fe;
            --link-color: rgba(200, 200, 200, 0.7);
            --link-hover: #ffffff;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
            overflow-x: hidden;
            transition: background 0.3s ease, color 0.3s ease;
            line-height: 1.6;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        img {
            max-width: 100%;
            display: block;
        }

        button {
            font-family: inherit;
        }

        /* ===== NAVBAR ===== */
        .navbar-transparent {
            position: fixed !important;
            top: 0;
            left: 0;
            right: 0;
            width: 100%;
            z-index: 1050;
            padding: 0.8rem 0;
            background: var(--bg-navbar) !important;
            transition: background 0.4s ease, box-shadow 0.4s ease, backdrop-filter 0.4s ease;
        }

        .navbar-transparent.scrolled {
            background: var(--bg-navbar-scroll) !important;
            backdrop-filter: blur(14px) saturate(180%);
            -webkit-backdrop-filter: blur(14px) saturate(180%);
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.12);
            border-bottom: 1px solid var(--border-color);
        }

        .navbar-transparent .container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
        }

        .navbar-transparent .navbar-brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0;
            flex-shrink: 0;
        }

        .brand-logo-box {
            width: 46px;
            height: 46px;
            border-radius: 12px;
            display: grid;
            place-items: center;
            background: linear-gradient(135deg, #22d3ee, #0891b2);
            color: #03111f;
            font-weight: 900;
            font-size: 15px;
            letter-spacing: 0.5px;
            box-shadow: 0 6px 18px rgba(34, 211, 238, 0.4);
        }

        .logo-text {
            font-weight: 800;
            font-size: 1.4rem;
            letter-spacing: 1px;
            color: #ffffff;
            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.25);
            line-height: 1.1;
        }

        .logo-text small {
            display: block;
            font-weight: 300;
            font-size: 0.55rem;
            letter-spacing: 4px;
            color: rgba(255, 255, 255, 0.85);
            text-transform: uppercase;
        }

        .navbar-transparent .navbar-toggler {
            border: 1px solid rgba(255, 255, 255, 0.35);
            padding: 0.4rem 0.6rem;
            border-radius: 8px;
            flex-shrink: 0;
            margin-left: auto;
        }

        .navbar-transparent .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba(255,255,255,0.9)' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
        }

        #navbarDesktop {
            flex-grow: 1;
            display: flex;
            justify-content: center;
        }

        #navbarDesktop .navbar-nav {
            align-items: center;
            gap: 0.25rem;
            flex-wrap: wrap;
        }

        #navbarDesktop .navbar-nav .nav-link {
            color: rgba(255, 255, 255, 0.92) !important;
            font-weight: 500;
            font-size: 0.95rem;
            padding: 0.5rem 1rem !important;
            border-radius: 8px;
            transition: color 0.2s, background 0.2s;
            letter-spacing: 0.3px;
            white-space: nowrap;
        }

        #navbarDesktop .navbar-nav .nav-link:hover,
        #navbarDesktop .navbar-nav .nav-link.active {
            color: #ffffff !important;
            background: rgba(255, 255, 255, 0.15);
        }

        #navbarDesktop .navbar-nav .nav-link i {
            margin-right: 6px;
            font-size: 0.9rem;
        }

        .theme-toggle {
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: #ffffff;
            border-radius: 30px;
            padding: 0.4rem 1rem;
            cursor: pointer;
            transition: background 0.2s, border-color 0.2s;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-left: 0.5rem;
        }

        .theme-toggle:hover {
            background: rgba(255, 255, 255, 0.25);
        }

        .theme-toggle i {
            font-size: 1.1rem;
        }

        .nav-btn-login {
            background: var(--btn-primary-bg) !important;
            border: 1px solid var(--btn-primary-bg) !important;
            color: var(--btn-primary-color) !important;
            padding: 0.45rem 1.4rem !important;
            border-radius: 30px !important;
            font-weight: 700 !important;
            font-size: 0.9rem !important;
            transition: all 0.25s;
        }

        .nav-btn-login:hover {
            background: var(--btn-primary-hover) !important;
            border-color: var(--btn-primary-hover) !important;
            transform: translateY(-1px);
        }

        /* Offcanvas */
        .offcanvas-end {
            background: #0d1b2a;
            color: #ffffff;
            border-left: 1px solid rgba(255, 255, 255, 0.1);
            width: 300px !important;
            max-width: 85vw !important;
            z-index: 1060 !important;
        }

        .offcanvas-end .offcanvas-header {
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            padding: 1rem 1.5rem;
            background: #0d1b2a;
        }

        .offcanvas-end .offcanvas-header .btn-close {
            filter: invert(1) brightness(2);
            opacity: 0.8;
            margin-left: auto;
            padding: 0.5rem;
        }

        .offcanvas-end .offcanvas-body {
            padding: 1.5rem 1rem;
            background: #0d1b2a;
        }

        .offcanvas-end .offcanvas-body .navbar-nav {
            gap: 0.2rem;
        }

        .offcanvas-end .offcanvas-body .nav-link {
            color: rgba(255, 255, 255, 0.85) !important;
            font-weight: 500;
            padding: 0.7rem 0.8rem !important;
            border-radius: 8px;
            transition: background 0.2s, color 0.2s;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 1rem;
        }

        .offcanvas-end .offcanvas-body .nav-link i {
            width: 24px;
            text-align: center;
            font-size: 1.1rem;
        }

        .offcanvas-end .offcanvas-body .nav-link:hover {
            background: rgba(255, 255, 255, 0.08);
            color: #ffffff !important;
        }

        .offcanvas-end .offcanvas-body .nav-link.active {
            background: rgba(255, 217, 102, 0.15);
            color: #ffd966 !important;
        }

        .offcanvas-end .offcanvas-body .nav-btn-login {
            background: var(--btn-primary-bg) !important;
            border: 1px solid var(--btn-primary-bg) !important;
            color: var(--btn-primary-color) !important;
            border-radius: 30px !important;
            justify-content: center;
            margin-top: 0.5rem;
            padding: 0.65rem 0.8rem !important;
            font-weight: 700;
            width: 100%;
        }

        .theme-toggle-mobile {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: #ffffff;
            border-radius: 30px;
            padding: 0.5rem 1rem;
            cursor: pointer;
            transition: background 0.2s;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 10px;
            width: 100%;
            margin-top: 0.5rem;
            justify-content: center;
        }

        .theme-toggle-mobile:hover {
            background: rgba(255, 255, 255, 0.15);
        }

        /* HERO */
        .hero-section {
            min-height: 62vh;
            width: 100%;
            background: url('background.jpg') center center / cover no-repeat;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 140px 0 90px;
        }

        .hero-section::before {
            content: '';
            position: absolute;
            inset: 0;
            background: var(--bg-hero-overlay);
            z-index: 1;
            transition: background 0.3s ease;
        }

        .hero-section>* {
            position: relative;
            z-index: 2;
        }

        .hero-content {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 24px;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 16px;
            border-radius: 999px;
            background: rgba(34, 211, 238, 0.15);
            border: 1px solid rgba(34, 211, 238, 0.45);
            color: #67e8f9;
            font-size: 0.78rem;
            font-weight: 600;
            letter-spacing: 1.2px;
            text-transform: uppercase;
            margin-bottom: 22px;
        }

        .hero-badge .pulse-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #22d3ee;
            animation: pulseDot 2s infinite;
        }

        @keyframes pulseDot {
            0% {
                box-shadow: 0 0 0 0 rgba(34, 211, 238, 0.5);
            }

            70% {
                box-shadow: 0 0 0 12px rgba(34, 211, 238, 0);
            }

            100% {
                box-shadow: 0 0 0 0 rgba(34, 211, 238, 0);
            }
        }

        .hero-title {
            font-weight: 800;
            font-size: 3rem;
            color: #ffffff;
            text-shadow: 0 4px 20px rgba(0, 0, 0, 0.30);
            letter-spacing: -0.5px;
            line-height: 1.15;
            margin-bottom: 1rem;
        }

        .hero-title span {
            background: linear-gradient(90deg, #22d3ee, #ffd966);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero-sub {
            font-size: 1.15rem;
            font-weight: 400;
            color: rgba(255, 255, 255, 0.85);
            max-width: 700px;
            margin-bottom: 2rem;
            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.20);
        }

        .breadcrumb-mini {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }

        .breadcrumb-mini a {
            color: rgba(255, 255, 255, 0.7);
            transition: color 0.2s;
        }

        .breadcrumb-mini a:hover {
            color: var(--accent);
        }

        .breadcrumb-mini i {
            font-size: 0.7rem;
        }

        /* SECTION */
        .section-padding {
            padding: 4rem 0;
        }

        .section-title {
            font-weight: 800;
            font-size: 2.2rem;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            transition: color 0.3s ease;
        }

        .section-subtitle {
            color: var(--text-secondary);
            font-size: 1.1rem;
            margin-bottom: 2.5rem;
            transition: color 0.3s ease;
        }

        /* PRINSIP CARD */
        .prinsip-card {
            background: var(--bg-card);
            border-radius: 18px;
            padding: 2rem 1.5rem;
            text-align: center;
            box-shadow: var(--shadow-card);
            transition: all 0.3s ease;
            height: 100%;
            border: 1px solid transparent;
        }

        .prinsip-card:hover {
            transform: translateY(-6px);
            box-shadow: var(--shadow-card-hover);
            border-color: rgba(255, 217, 102, 0.4);
        }

        .prinsip-card .prinsip-icon {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: linear-gradient(135deg, rgba(255, 217, 102, 0.20), rgba(34, 211, 238, 0.15));
            color: var(--accent);
            display: grid;
            place-items: center;
            font-size: 1.8rem;
            margin: 0 auto 1.2rem;
            transition: all 0.35s;
        }

        .prinsip-card:hover .prinsip-icon {
            transform: rotate(-8deg) scale(1.08);
            background: linear-gradient(135deg, #ffd966, #f0c850);
            color: #1a2634;
        }

        .prinsip-card h6 {
            font-weight: 700;
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }

        .prinsip-card p {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin: 0;
            line-height: 1.55;
        }

        /* MEKANISME DUA TAP */
        .tap-card {
            background: var(--bg-card);
            border-radius: 20px;
            padding: 2.2rem 1.8rem;
            box-shadow: var(--shadow-card);
            transition: all 0.3s ease;
            height: 100%;
            position: relative;
            overflow: hidden;
            border-top: 4px solid transparent;
        }

        .tap-card:hover {
            transform: translateY(-6px);
            box-shadow: var(--shadow-card-hover);
        }

        .tap-card .tap-icon {
            width: 72px;
            height: 72px;
            border-radius: 18px;
            display: grid;
            place-items: center;
            font-size: 1.9rem;
            margin-bottom: 1.2rem;
            color: #ffffff;
            transition: all 0.35s;
        }

        .tap-card:hover .tap-icon {
            transform: scale(1.08);
        }

        .tap-card .tap-time-badge {
            display: inline-block;
            font-size: 0.72rem;
            font-weight: 700;
            padding: 4px 12px;
            border-radius: 20px;
            background: rgba(34, 211, 238, 0.12);
            color: #0891b2;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.8rem;
        }

        body.dark-mode .tap-card .tap-time-badge {
            color: #67e8f9;
        }

        .tap-card h5 {
            font-weight: 800;
            font-size: 1.3rem;
            margin-bottom: 0.4rem;
            color: var(--text-primary);
        }

        .tap-card p {
            font-size: 0.92rem;
            color: var(--text-secondary);
            margin: 0;
            line-height: 1.6;
        }

        /* ATURAN STATUS CARD */
        .aturan-card {
            background: var(--bg-card);
            border-radius: 18px;
            padding: 1.8rem 1.4rem;
            box-shadow: var(--shadow-card);
            transition: all 0.3s ease;
            height: 100%;
            border-left: 5px solid transparent;
            display: flex;
            gap: 1rem;
            align-items: flex-start;
        }

        .aturan-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-card-hover);
        }

        .aturan-card .aturan-icon {
            width: 56px;
            height: 56px;
            border-radius: 16px;
            display: grid;
            place-items: center;
            font-size: 1.5rem;
            flex-shrink: 0;
            color: #ffffff;
        }

        .aturan-card h6 {
            font-weight: 700;
            font-size: 1.1rem;
            color: var(--text-primary);
            margin-bottom: 0.3rem;
        }

        .aturan-card p {
            font-size: 0.88rem;
            color: var(--text-secondary);
            margin: 0 0 0.7rem;
            line-height: 1.55;
        }

        .aturan-card .aturan-poin {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.72rem;
            font-weight: 700;
            padding: 4px 12px;
            border-radius: 20px;
            background: rgba(34, 211, 238, 0.12);
            color: #0891b2;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        body.dark-mode .aturan-card .aturan-poin {
            color: #67e8f9;
        }

        /* ATURAN TAMBAHAN */
        .aturan-tambahan {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 1.6rem 1.3rem;
            box-shadow: var(--shadow-card);
            transition: all 0.3s ease;
            height: 100%;
            border-top: 3px solid transparent;
            display: flex;
            gap: 1rem;
            align-items: flex-start;
        }

        .aturan-tambahan:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-card-hover);
            border-top-color: var(--accent);
        }

        .aturan-tambahan .tambahan-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: rgba(255, 217, 102, 0.18);
            color: var(--accent);
            display: grid;
            place-items: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .aturan-tambahan h6 {
            font-weight: 700;
            font-size: 1rem;
            color: var(--text-primary);
            margin-bottom: 0.35rem;
        }

        .aturan-tambahan p {
            font-size: 0.86rem;
            color: var(--text-secondary);
            margin: 0;
            line-height: 1.55;
        }

        /* CONTOH HITUNG TABLE */
        .contoh-table-wrap {
            background: var(--bg-card);
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: var(--shadow-card);
            overflow-x: auto;
        }

        .contoh-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 500px;
        }

        .contoh-table th {
            padding: 1rem 1.2rem;
            text-align: left;
            font-weight: 700;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-secondary);
            border-bottom: 2px solid rgba(0, 0, 0, 0.08);
            background: transparent;
        }

        body.dark-mode .contoh-table th {
            border-bottom-color: rgba(255, 255, 255, 0.10);
        }

        .contoh-table td {
            padding: 0.9rem 1.2rem;
            font-size: 0.94rem;
            color: var(--text-primary);
            border-bottom: 1px solid rgba(0, 0, 0, 0.06);
            vertical-align: middle;
        }

        body.dark-mode .contoh-table td {
            border-bottom-color: rgba(255, 255, 255, 0.06);
        }

        .contoh-table tr:last-child td {
            border-bottom: none;
        }

        .contoh-table tr.total-row {
            background: rgba(255, 217, 102, 0.08);
        }

        .contoh-table tr.total-row td {
            font-weight: 700;
            font-size: 1rem;
            border-bottom: none;
        }

        .contoh-table .status-label {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .contoh-table .status-label i {
            font-size: 1rem;
        }

        .contoh-table .poin-cell {
            font-weight: 700;
            font-family: 'Courier New', monospace;
        }

        .contoh-table .saldo-cell {
            font-weight: 800;
            font-size: 1rem;
            font-family: 'Courier New', monospace;
            color: var(--stat-number);
        }

        /* KONSEKUENSI CARD */
        .konsekuensi-card {
            background: var(--bg-card);
            border-radius: 18px;
            padding: 2rem 1.5rem;
            box-shadow: var(--shadow-card);
            transition: all 0.3s ease;
            height: 100%;
            position: relative;
            overflow: hidden;
            border-top: 4px solid transparent;
            text-align: center;
        }

        .konsekuensi-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-card-hover);
        }

        .konsekuensi-card .konsekuensi-range {
            font-weight: 900;
            font-size: 1.9rem;
            line-height: 1;
            margin-bottom: 0.4rem;
            letter-spacing: -1px;
        }

        .konsekuensi-card .konsekuensi-range small {
            font-weight: 500;
            font-size: 0.85rem;
            opacity: 0.75;
            display: block;
            margin-top: 4px;
            letter-spacing: 0;
        }

        .konsekuensi-card .konsekuensi-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            font-size: 1.5rem;
            margin: 1.2rem auto 1rem;
            color: #ffffff;
            transition: all 0.3s;
        }

        .konsekuensi-card:hover .konsekuensi-icon {
            transform: scale(1.08);
        }

        .konsekuensi-card .konsekuensi-label {
            display: inline-block;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 4px 12px;
            border-radius: 20px;
            margin-bottom: 0.7rem;
        }

        .konsekuensi-card p {
            font-size: 0.88rem;
            color: var(--text-secondary);
            margin: 0;
            line-height: 1.55;
        }

        /* CTA */
        .cta-box {
            max-width: 900px;
            margin: 0 auto;
            text-align: center;
            padding: 60px 30px;
            border-radius: 28px;
            background:
                radial-gradient(circle at 20% 20%, rgba(34, 211, 238, 0.18), transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(255, 217, 102, 0.18), transparent 50%),
                var(--bg-card);
            border: 1px solid rgba(255, 217, 102, 0.25);
            box-shadow: var(--shadow-card);
        }

        .cta-box h2 {
            font-size: clamp(1.5rem, 3vw, 2.2rem);
            font-weight: 800;
            margin-bottom: 14px;
            color: var(--text-primary);
        }

        .cta-box p {
            color: var(--text-secondary);
            margin-bottom: 28px;
            font-size: 1.02rem;
            max-width: 560px;
            margin-left: auto;
            margin-right: auto;
        }

        .btn-cta {
            background: var(--btn-primary-bg);
            color: var(--btn-primary-color);
            font-weight: 700;
            border: none;
            padding: 0.9rem 2.2rem;
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.25s;
            box-shadow: 0 6px 20px rgba(255, 217, 102, 0.35);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-cta:hover {
            background: var(--btn-primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(255, 217, 102, 0.45);
            color: var(--btn-primary-color);
        }

        .btn-cta-ghost {
            background: rgba(34, 211, 238, 0.12);
            color: #0891b2;
            border: 1px solid rgba(34, 211, 238, 0.35);
            box-shadow: none;
        }

        .btn-cta-ghost:hover {
            background: rgba(34, 211, 238, 0.20);
            color: #0891b2;
            border-color: rgba(34, 211, 238, 0.55);
        }

        /* FOOTER */
        .footer {
            background: var(--bg-footer);
            color: var(--text-muted);
            padding: 3rem 0 1.5rem;
            transition: background 0.3s ease, color 0.3s ease;
        }

        .footer .footer-brand {
            font-weight: 800;
            font-size: 1.6rem;
            color: #ffffff;
        }

        .footer .footer-brand small {
            display: block;
            font-weight: 300;
            font-size: 0.7rem;
            letter-spacing: 3px;
            color: rgba(255, 255, 255, 0.6);
            text-transform: uppercase;
        }

        .footer h6 {
            color: #ffffff;
            font-weight: 700;
            font-size: 1rem;
            margin-bottom: 1rem;
        }

        .footer ul {
            list-style: none;
            padding: 0;
        }

        .footer ul li {
            margin-bottom: 0.5rem;
        }

        .footer ul li a {
            color: var(--link-color);
            transition: color 0.2s;
            font-size: 0.92rem;
        }

        .footer ul li a:hover {
            color: var(--link-hover);
            text-decoration: underline;
        }

        .footer .social-icons a {
            display: inline-block;
            width: 38px;
            height: 38px;
            background: rgba(255, 255, 255, 0.08);
            border-radius: 50%;
            text-align: center;
            line-height: 38px;
            color: rgba(255, 255, 255, 0.8);
            margin-right: 0.5rem;
            transition: background 0.2s, color 0.2s;
        }

        .footer .social-icons a:hover {
            background: #ffd966;
            color: #0d1b2a;
        }

        .footer .copyright {
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            padding-top: 1.5rem;
            margin-top: 2rem;
            font-size: 0.9rem;
            text-align: center;
            color: rgba(255, 255, 255, 0.5);
        }

        /* FADE-UP */
        .fade-up {
            opacity: 0;
            transform: translateY(30px);
            transition: opacity 0.8s ease, transform 0.8s ease;
        }

        .fade-up.visible {
            opacity: 1;
            transform: translateY(0);
        }

        /* RESPONSIVE */
        @media (max-width: 992px) {
            #navbarDesktop {
                display: none !important;
            }

            .navbar-transparent .navbar-toggler {
                display: flex !important;
            }

            .hero-title {
                font-size: 2.3rem;
            }

            .hero-sub {
                font-size: 1.05rem;
            }

            .section-title {
                font-size: 1.8rem;
            }
        }

        @media (max-width: 768px) {
            .navbar-transparent {
                padding: 0.5rem 0;
            }

            .brand-logo-box {
                width: 38px;
                height: 38px;
                font-size: 13px;
            }

            .logo-text {
                font-size: 1.15rem;
            }

            .logo-text small {
                font-size: 0.45rem;
            }

            .hero-section {
                min-height: auto;
                padding: 110px 0 60px;
            }

            .hero-title {
                font-size: 1.9rem;
                text-align: center;
            }

            .hero-sub {
                font-size: 1rem;
                text-align: center;
                margin-left: auto;
                margin-right: auto;
            }

            .hero-badge {
                font-size: 0.68rem;
                padding: 5px 12px;
                display: flex;
                width: fit-content;
                margin-left: auto;
                margin-right: auto;
            }

            .breadcrumb-mini {
                display: flex;
                justify-content: center;
                width: 100%;
                margin-bottom: 1rem;
            }

            .section-padding {
                padding: 2.5rem 0;
            }

            .prinsip-card {
                padding: 1.6rem 1.2rem;
            }

            .prinsip-card .prinsip-icon {
                width: 60px;
                height: 60px;
                font-size: 1.5rem;
            }

            .tap-card {
                padding: 1.8rem 1.3rem;
            }

            .tap-card .tap-icon {
                width: 60px;
                height: 60px;
                font-size: 1.5rem;
            }

            .aturan-card {
                padding: 1.5rem 1.1rem;
                flex-direction: column;
                text-align: center;
                align-items: center;
            }

            .aturan-tambahan {
                padding: 1.3rem 1rem;
                flex-direction: column;
                align-items: center;
                text-align: center;
            }

            .contoh-table-wrap {
                padding: 1rem;
            }

            .contoh-table th,
            .contoh-table td {
                padding: 0.7rem 0.8rem;
                font-size: 0.85rem;
            }

            .konsekuensi-card {
                padding: 1.5rem 1.1rem;
            }

            .konsekuensi-card .konsekuensi-range {
                font-size: 1.5rem;
            }

            .cta-box {
                padding: 40px 22px;
            }

            .footer {
                padding: 2rem 0 1rem;
            }

            .offcanvas-end {
                width: 85vw !important;
                max-width: 85vw !important;
            }
        }

        @media (max-width: 480px) {
            .hero-title {
                font-size: 1.6rem;
            }

            .offcanvas-end {
                width: 92vw !important;
                max-width: 92vw !important;
            }

            .contoh-table th,
            .contoh-table td {
                padding: 0.6rem 0.6rem;
                font-size: 0.8rem;
            }
        }

        @media (min-width: 993px) {
            .navbar-transparent .navbar-toggler {
                display: none !important;
            }
        }

        @media (prefers-reduced-motion: reduce) {

            *,
            *::before,
            *::after {
                animation-duration: 0.01ms !important;
                transition-duration: 0.01ms !important;
            }
        }
    </style>
</head>

<body>

    <!-- ============================================================
         NAVIGASI
         ============================================================ -->
    <nav class="navbar navbar-expand-lg navbar-transparent" id="mainNav">
        <div class="container">
            <a class="navbar-brand" href="<?= $homeUrl ?>">
                <div class="brand-logo-box">NFC</div>
                <span class="logo-text d-none d-sm-inline">
                    Presensi NFC
                    <small>Kehadiran Mahasiswa</small>
                </span>
            </a>

            <button class="navbar-toggler d-lg-none" type="button" data-bs-toggle="offcanvas"
                data-bs-target="#offcanvasRight" aria-controls="offcanvasRight" aria-expanded="false"
                aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse d-none d-lg-flex" id="navbarDesktop">
                <ul class="navbar-nav mx-auto align-items-lg-center gap-lg-1">
                    <?php foreach ($menu as $item): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === $item['href'] ? 'active' : '' ?>"
                                href="<?= htmlspecialchars($item['href']) ?>">
                                <i class="fas <?= htmlspecialchars($item['icon']) ?>"></i>
                                <?= htmlspecialchars($item['label']) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                    <li class="nav-item ms-lg-2">
                        <a class="nav-link nav-btn-login" href="<?= $loginUrl ?>">
                            <i class="fas fa-sign-in-alt"></i> Masuk
                        </a>
                    </li>
                    <li class="nav-item">
                        <button class="theme-toggle" id="themeToggleDesktop" aria-label="Toggle tema">
                            <i class="fas fa-moon"></i> <span id="themeLabelDesktop">Mode Gelap</span>
                        </button>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- ============================================================
         OFFCANVAS (MOBILE)
         ============================================================ -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasRight" aria-labelledby="offcanvasRightLabel">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="offcanvasRightLabel" style="display:flex;align-items:center;gap:8px;">
                <div class="brand-logo-box" style="width:36px;height:36px;font-size:12px;">NFC</div>
                <span style="font-weight: 700; font-size: 1.05rem; color:#ffffff;">Presensi NFC</span>
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <ul class="navbar-nav flex-column gap-1">
                <?php foreach ($menu as $item): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === $item['href'] ? 'active' : '' ?>"
                            href="<?= htmlspecialchars($item['href']) ?>">
                            <i class="fas <?= htmlspecialchars($item['icon']) ?>"></i>
                            <?= htmlspecialchars($item['label']) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
                <li class="nav-item mt-2">
                    <a class="nav-link nav-btn-login" href="<?= $loginUrl ?>">
                        <i class="fas fa-sign-in-alt"></i> Masuk ke Sistem
                    </a>
                </li>
                <li class="nav-item">
                    <button class="theme-toggle-mobile" id="themeToggleMobile">
                        <i class="fas fa-moon"></i> <span id="themeLabelMobile">Mode Gelap</span>
                    </button>
                </li>
            </ul>
        </div>
    </div>

    <!-- ============================================================
         HERO
         ============================================================ -->
    <section class="hero-section">
        <div class="hero-content">
            <div class="text-center text-lg-start">
                <div class="breadcrumb-mini">
                    <a href="<?= $homeUrl ?>">Beranda</a>
                    <i class="fas fa-chevron-right"></i>
                    <span>Aturan Poin</span>
                </div>
                <span class="hero-badge mt-3">
                    <span class="pulse-dot"></span>
                    Kebijakan Disiplin
                </span>
                <h1 class="hero-title">
                    Aturan Poin<br>
                    <span>Kedisiplinan</span>
                </h1>
                <p class="hero-sub">
                    Sistem dua-tap yang adil & terukur: tap masuk + tap keluar. Setiap status punya bobot poin
                    yang jelas — mulai dari hadir tepat waktu (0 poin) hingga penalti lupa absen keluar.
                </p>
            </div>
        </div>
    </section>

    <!-- ============================================================
         SECTION — PRINSIP DASAR
         ============================================================ -->
    <section class="section-padding">
        <div class="container">
            <div class="text-center fade-up">
                <h2 class="section-title">Prinsip Dasar Sistem Poin</h2>
                <p class="section-subtitle">Empat prinsip yang menjadi fondasi perhitungan poin</p>
            </div>
            <div class="row g-4">
                <?php foreach ($prinsipPoin as $p): ?>
                    <div class="col-md-6 col-lg-3 fade-up">
                        <div class="prinsip-card">
                            <div class="prinsip-icon"><i class="fas <?= htmlspecialchars($p['icon']) ?>"></i></div>
                            <h6><?= htmlspecialchars($p['title']) ?></h6>
                            <p><?= htmlspecialchars($p['desc']) ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ============================================================
         SECTION — MEKANISME DUA TAP
         ============================================================ -->
    <section class="section-padding" style="background: var(--bg-body);">
        <div class="container">
            <div class="text-center fade-up">
                <h2 class="section-title">Mekanisme Dua Tap</h2>
                <p class="section-subtitle">Setiap kehadiran dicatat lewat dua tap: saat masuk dan saat keluar</p>
            </div>
            <div class="row g-4">
                <?php foreach ($mekanismeTap as $t): ?>
                    <div class="col-md-6 fade-up">
                        <div class="tap-card" style="border-top-color: <?= htmlspecialchars($t['color']) ?>;">
                            <div class="tap-icon" style="background: <?= htmlspecialchars($t['color']) ?>;">
                                <i class="fas <?= htmlspecialchars($t['icon']) ?>"></i>
                            </div>
                            <span class="tap-time-badge">
                                <i class="fas fa-clock me-1"></i><?= htmlspecialchars($t['time']) ?>
                            </span>
                            <h5><?= htmlspecialchars($t['title']) ?></h5>
                            <p><?= htmlspecialchars($t['desc']) ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ============================================================
         SECTION — ATURAN PER STATUS
         ============================================================ -->
    <section class="section-padding" style="background: var(--bg-body);">
        <div class="container">
            <div class="text-center fade-up">
                <h2 class="section-title">Pengurangan Poin per Status</h2>
                <p class="section-subtitle">Kondisi kehadiran dengan bobot poin yang berlaku</p>
            </div>
            <div class="row g-4">
                <?php foreach ($aturanStatus as $s): ?>
                    <div class="col-md-6 fade-up">
                        <div class="aturan-card" style="border-left-color: <?= htmlspecialchars($s['color']) ?>;">
                            <div class="aturan-icon" style="background: <?= htmlspecialchars($s['color']) ?>;">
                                <i class="fas <?= htmlspecialchars($s['icon']) ?>"></i>
                            </div>
                            <div>
                                <h6><?= htmlspecialchars($s['title']) ?></h6>
                                <p><?= htmlspecialchars($s['keterangan']) ?></p>
                                <span class="aturan-poin">
                                    <i class="fas fa-star"></i>
                                    <?= htmlspecialchars($s['poin']) ?> poin · <?= htmlspecialchars($s['tipe']) ?>
                                </span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ============================================================
         SECTION — ATURAN TAMBAHAN
         ============================================================ -->
    <section class="section-padding" style="background: var(--bg-body);">
        <div class="container">
            <div class="text-center fade-up">
                <h2 class="section-title">Aturan Tambahan</h2>
                <p class="section-subtitle">Kewajiban dan larangan terkait penggunaan kartu NFC</p>
            </div>
            <div class="row g-4">
                <?php foreach ($aturanTambahan as $a): ?>
                    <div class="col-md-6 col-lg-4 fade-up">
                        <div class="aturan-tambahan">
                            <div class="tambahan-icon"><i class="fas <?= htmlspecialchars($a['icon']) ?>"></i></div>
                            <div>
                                <h6><?= htmlspecialchars($a['title']) ?></h6>
                                <p><?= htmlspecialchars($a['desc']) ?></p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ============================================================
         SECTION — CONTOH PERHITUNGAN
         ============================================================ -->
    <section class="section-padding" style="background: var(--bg-body);">
        <div class="container">
            <div class="text-center fade-up">
                <h2 class="section-title">Contoh Perhitungan Mingguan</h2>
                <p class="section-subtitle">Simulasi mingguan sesuai dengan kebijakan baru</p>
            </div>
            <div class="row g-4">
                <div class="col-lg-10 mx-auto fade-up">
                    <div class="contoh-table-wrap">
                        <table class="contoh-table">
                            <thead>
                                <tr>
                                    <th>Hari</th>
                                    <th>Status</th>
                                    <th>Perubahan</th>
                                    <th>Saldo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="3" style="font-weight:600; color: var(--text-secondary);">
                                        <i class="fas fa-seedling me-2" style="color: var(--accent);"></i>
                                        Saldo Awal Minggu
                                    </td>
                                    <td class="saldo-cell"><?= $saldoAwal ?></td>
                                </tr>
                                <?php foreach ($contohHitung as $c): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($c['hari']) ?></td>
                                        <td>
                                            <span class="status-label" style="color: <?= htmlspecialchars($c['color']) ?>;">
                                                <i class="fas <?= htmlspecialchars($c['icon']) ?>"></i>
                                                <?= htmlspecialchars($c['status']) ?>
                                            </span>
                                        </td>
                                        <td class="poin-cell" style="color: <?= htmlspecialchars($c['color']) ?>;">
                                            <?= $c['poin'] > 0 ? '+' : '' ?><?= htmlspecialchars((string) $c['poin']) ?>
                                        </td>
                                        <td class="saldo-cell"><?= htmlspecialchars((string) $c['saldo']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr class="total-row">
                                    <td colspan="3">
                                        <i class="fas fa-flag-checkered me-2" style="color: var(--accent);"></i>
                                        Saldo Akhir Minggu
                                    </td>
                                    <td class="saldo-cell" style="color: var(--accent);"><?= $saldoAkhir ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p style="text-align: center; margin-top: 1.2rem; font-size: 0.9rem; color: var(--text-secondary);">
                        <i class="fas fa-info-circle me-1" style="color: var(--accent);"></i>
                        Minggu berikutnya, saldo di-reset kembali ke <strong>100 poin</strong>.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- ============================================================
         SECTION — KONSEKUENSI AKUMULASI
         ============================================================ -->
    <section class="section-padding" style="background: var(--bg-body);">
        <div class="container">
            <div class="text-center fade-up">
                <h2 class="section-title">Konsekuensi Akumulasi Poin</h2>
                <p class="section-subtitle">Tindakan yang diambil berdasarkan saldo poin akhir minggu</p>
            </div>
            <div class="row g-4">
                <?php foreach ($konsekuensi as $k): ?>
                    <div class="col-md-6 col-lg-3 fade-up">
                        <div class="konsekuensi-card" style="border-top-color: <?= htmlspecialchars($k['color']) ?>;">
                            <div class="konsekuensi-range" style="color: <?= htmlspecialchars($k['color']) ?>;">
                                <?= htmlspecialchars($k['range']) ?>
                                <small>poin</small>
                            </div>
                            <div class="konsekuensi-icon" style="background: <?= htmlspecialchars($k['color']) ?>;">
                                <i class="fas <?= htmlspecialchars($k['icon']) ?>"></i>
                            </div>
                            <span class="konsekuensi-label" style="background: <?= htmlspecialchars($k['color']) ?>; color: #ffffff;">
                                <?= htmlspecialchars($k['label']) ?>
                            </span>
                            <p><?= htmlspecialchars($k['desc']) ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ============================================================
         SECTION — CTA
         ============================================================ -->
    <section class="section-padding">
        <div class="container">
            <div class="cta-box fade-up">
                <h2>Punya Pertanyaan Soal Poin?</h2>
                <p>Cek halaman FAQ untuk pertanyaan yang sering ditanyakan, atau langsung masuk ke sistem untuk melihat riwayat poin Anda.</p>
                <div class="d-flex flex-wrap justify-content-center gap-2">
                    <a href="faq.php" class="btn-cta btn-cta-ghost">
                        <i class="fas fa-question-circle"></i> Buka FAQ
                    </a>
                    <a href="<?= $loginUrl ?>" class="btn-cta">
                        <i class="fas fa-sign-in-alt"></i> Masuk Sekarang
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- ============================================================
         FOOTER
         ============================================================ -->
    <footer class="footer">
        <div class="container">
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="footer-brand">
                        Presensi NFC
                        <small>Kehadiran Mahasiswa</small>
                    </div>
                    <p class="mt-3" style="font-size:0.9rem; opacity:0.7;">
                        Sistem kehadiran mahasiswa berbasis teknologi NFC yang cepat, akurat, dan transparan
                        dengan sistem poin kedisiplinan terintegrasi.
                    </p>
                </div>
                <div class="col-6 col-md-2">
                    <h6>Menu</h6>
                    <ul>
                        <?php foreach ($menu as $item): ?>
                            <li>
                                <a href="<?= htmlspecialchars($item['href']) ?>">
                                    <i class="fas <?= htmlspecialchars($item['icon']) ?>" style="margin-right:6px;"></i>
                                    <?= htmlspecialchars($item['label']) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="col-6 col-md-2">
                    <h6>Bantuan</h6>
                    <ul>
                        <li><a href="faq.php">Pertanyaan Umum</a></li>
                        <li><a href="aturan.php">Kebijakan Poin</a></li>
                        <li><a href="cara-kerja.php">Panduan Presensi</a></li>
                        <li><a href="tentang.php">Tentang Aplikasi</a></li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h6>Ikuti Kami</h6>
                    <div class="social-icons">
                        <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                        <a href="#" aria-label="YouTube"><i class="fab fa-youtube"></i></a>
                        <a href="#" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                    </div>
                    <p class="mt-3" style="font-size:0.85rem; opacity:0.6;">
                        <i class="fas fa-envelope me-1"></i> admin@presensinfc.id<br>
                        <i class="fas fa-phone me-1"></i> +62 812 3456 7890
                    </p>
                </div>
            </div>
            <div class="copyright">
                &copy; <?= date('Y') ?> Presensi NFC · Sistem Kehadiran Mahasiswa. All rights reserved.
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            'use strict';

            /* FADE-UP */
            const fadeElements = document.querySelectorAll('.fade-up');
            if ('IntersectionObserver' in window) {
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            entry.target.classList.add('visible');
                            observer.unobserve(entry.target);
                        }
                    });
                }, {
                    threshold: 0.1,
                    rootMargin: '0px 0px -20px 0px'
                });
                fadeElements.forEach(el => observer.observe(el));
            } else {
                fadeElements.forEach(el => el.classList.add('visible'));
            }

            /* STICKY NAVBAR */
            const navbar = document.getElementById('mainNav');
            let ticking = false;

            function updateNavbar() {
                if (window.scrollY > 30) navbar.classList.add('scrolled');
                else navbar.classList.remove('scrolled');
                ticking = false;
            }
            window.addEventListener('scroll', function() {
                if (!ticking) {
                    window.requestAnimationFrame(updateNavbar);
                    ticking = true;
                }
            });
            updateNavbar();

            /* DARK MODE */
            const themeToggleDesktop = document.getElementById('themeToggleDesktop');
            const themeToggleMobile = document.getElementById('themeToggleMobile');
            const themeLabelDesktop = document.getElementById('themeLabelDesktop');
            const themeLabelMobile = document.getElementById('themeLabelMobile');

            const storedTheme = localStorage.getItem('presensi_theme');
            if (storedTheme === 'dark') {
                document.body.classList.add('dark-mode');
                updateUI('dark');
            } else if (storedTheme === 'light') {
                document.body.classList.remove('dark-mode');
                updateUI('light');
            } else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                document.body.classList.add('dark-mode');
                updateUI('dark');
                localStorage.setItem('presensi_theme', 'dark');
            } else {
                document.body.classList.remove('dark-mode');
                updateUI('light');
                localStorage.setItem('presensi_theme', 'light');
            }

            function updateUI(mode) {
                const iconDesktop = themeToggleDesktop.querySelector('i');
                const iconMobile = themeToggleMobile.querySelector('i');
                if (mode === 'dark') {
                    iconDesktop.className = 'fas fa-sun';
                    iconMobile.className = 'fas fa-sun';
                    themeLabelDesktop.textContent = 'Mode Terang';
                    themeLabelMobile.textContent = 'Mode Terang';
                } else {
                    iconDesktop.className = 'fas fa-moon';
                    iconMobile.className = 'fas fa-moon';
                    themeLabelDesktop.textContent = 'Mode Gelap';
                    themeLabelMobile.textContent = 'Mode Gelap';
                }
            }

            function toggleTheme() {
                const isDark = document.body.classList.toggle('dark-mode');
                const mode = isDark ? 'dark' : 'light';
                localStorage.setItem('presensi_theme', mode);
                updateUI(mode);
            }

            themeToggleDesktop.addEventListener('click', toggleTheme);
            themeToggleMobile.addEventListener('click', toggleTheme);
        });
    </script>

</body>

</html>
<?php
if (isset($koneksi) && $koneksi) {
    mysqli_close($koneksi);
}
?>