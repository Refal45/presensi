<?php

/**
 * cara-kerja.php
 * Halaman Cara Kerja — Presensi NFC | Sistem Kehadiran Mahasiswa
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
   ALUR PRESENSI (4 LANGKAH UTAMA)
   ============================================================ */
$alurUtama = [
    [
        'step'  => '01',
        'icon'  => 'fa-id-card',
        'title' => 'Tap Kartu NFC',
        'desc'  => 'Tempelkan kartu mahasiswa pada reader NFC yang tersedia di pintu kelas atau lokasi presensi.',
    ],
    [
        'step'  => '02',
        'icon'  => 'fa-shield-alt',
        'title' => 'Validasi Otomatis',
        'desc'  => 'Sistem memverifikasi identitas mahasiswa, jadwal kuliah aktif, serta kode presensi saat itu.',
    ],
    [
        'step'  => '03',
        'icon'  => 'fa-database',
        'title'  => 'Pencatatan Real-time',
        'desc'  => 'Kehadiran langsung tersimpan di sistem dengan status sesuai waktu tap (hadir atau terlambat).',
    ],
    [
        'step'  => '04',
        'icon'  => 'fa-chart-line',
        'title' => 'Poin Diperbarui',
        'desc'  => 'Poin kedisiplinan otomatis bertambah atau berkurang sesuai status kehadiran yang tercatat.',
    ],
];

/* ============================================================
   STATUS KEHADIRAN
   ============================================================ */
$statusKehadiran = [
    [
        'icon'  => 'fa-check-circle',
        'color' => '#28a745',
        'title' => 'Hadir',
        'desc'  => 'Tap kartu dilakukan sebelum batas waktu terlambat. Poin kedisiplinan tetap utuh.',
        'poin'  => 'Poin tetap',
    ],
    [
        'icon'  => 'fa-clock',
        'color' => '#ffc107',
        'title' => 'Terlambat',
        'desc'  => 'Tap kartu dilakukan setelah batas waktu. Tetap tercatat hadir, namun poin berkurang.',
        'poin'  => 'Poin berkurang',
    ],
    [
        'icon'  => 'fa-file-medical',
        'color' => '#0d6efd',
        'title' => 'Izin / Sakit',
        'desc'  => 'Diajukan sebelum/setelah kelas dengan bukti pendukung. Status menunggu persetujuan pengelola.',
        'poin'  => 'Poin disesuaikan',
    ],
    [
        'icon'  => 'fa-times-circle',
        'color' => '#dc3545',
        'title' => 'Alpha',
        'desc'  => 'Tidak tap kartu dan tidak mengajukan izin. Poin berkurang paling besar.',
        'poin'  => 'Poin berkurang besar',
    ],
];

/* ============================================================
   ALUR PENGAJUAN IZIN / SAKIT
   ============================================================ */
$alurIzin = [
    ['icon' => 'fa-edit',            'title' => 'Isi Formulir',      'desc' => 'Mahasiswa membuka menu pengajuan izin dan mengisi jenis (izin/sakit), tanggal, serta alasan.'],
    ['icon' => 'fa-paperclip',       'title' => 'Unggah Bukti',      'desc' => 'Lampirkan surat keterangan dokter atau surat izin resmi sebagai bukti pendukung.'],
    ['icon' => 'fa-user-check',      'title' => 'Review Pengelola',  'desc' => 'Admin atau dosen memeriksa pengajuan dan memutuskan untuk menyetujui atau menolak.'],
    ['icon' => 'fa-check-double',    'title' => 'Status Diperbarui', 'desc' => 'Jika disetujui, status kehadiran otomatis berubah dan poin disesuaikan sesuai kebijakan.'],
];

/* ============================================================
   PERAN MAHASISWA & PENGELOLA
   ============================================================ */
$peranMahasiswa = [
    'Tap kartu NFC setiap mengikuti kelas sesuai jadwal.',
    'Memantau riwayat kehadiran dan poin kedisiplinan pribadi.',
    'Mengajukan izin/sakit lewat sistem bila tidak bisa hadir.',
    'Menjaga kartu NFC agar tidak hilang atau dipinjamkan.',
];

$peranPengelola = [
    'Mengelola data mahasiswa, jadwal kuliah, dan kode presensi.',
    'Memantau kehadiran real-time dari dashboard admin.',
    'Mereview pengajuan izin/sakit yang masuk dari mahasiswa.',
    'Menetapkan kebijakan poin dan meninjau log aktivitas sistem.',
];
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cara Kerja — Presensi NFC | Kehadiran Mahasiswa</title>
    <meta name="description" content="Pelajari alur kerja Presensi NFC: tap kartu, validasi, pencatatan real-time, dan sistem poin kedisiplinan.">

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

        /* STEP CARD (Alur Utama) */
        .step-card {
            background: var(--bg-card);
            border-radius: 20px;
            padding: 2.2rem 1.5rem 1.8rem;
            box-shadow: var(--shadow-card);
            transition: all 0.3s ease;
            height: 100%;
            position: relative;
            overflow: hidden;
            border: 1px solid transparent;
        }

        .step-card:hover {
            transform: translateY(-6px);
            box-shadow: var(--shadow-card-hover);
            border-color: rgba(255, 217, 102, 0.4);
        }

        .step-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #22d3ee, #ffd966);
            opacity: 0;
            transition: opacity 0.3s;
        }

        .step-card:hover::before {
            opacity: 1;
        }

        .step-card .step-badge {
            position: absolute;
            top: 16px;
            right: 18px;
            font-weight: 900;
            font-size: 2.6rem;
            line-height: 1;
            color: var(--accent);
            opacity: 0.18;
            letter-spacing: -2px;
        }

        .step-card .step-icon {
            width: 64px;
            height: 64px;
            border-radius: 16px;
            background: linear-gradient(135deg, rgba(34, 211, 238, 0.18), rgba(255, 217, 102, 0.15));
            color: #22d3ee;
            display: grid;
            place-items: center;
            font-size: 1.6rem;
            margin-bottom: 1.1rem;
            transition: all 0.3s;
        }

        .step-card:hover .step-icon {
            background: linear-gradient(135deg, #22d3ee, #0891b2);
            color: #03111f;
            transform: scale(1.05);
        }

        .step-card h6 {
            font-weight: 700;
            font-size: 1.15rem;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .step-card p {
            font-size: 0.92rem;
            color: var(--text-secondary);
            line-height: 1.6;
            margin: 0;
        }

        /* STATUS CARD */
        .status-card {
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

        .status-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-card-hover);
        }

        .status-card .status-icon {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            display: grid;
            place-items: center;
            font-size: 1.4rem;
            flex-shrink: 0;
            color: #ffffff;
        }

        .status-card h6 {
            font-weight: 700;
            font-size: 1.05rem;
            color: var(--text-primary);
            margin-bottom: 0.4rem;
        }

        .status-card p {
            font-size: 0.88rem;
            color: var(--text-secondary);
            margin: 0 0 0.6rem;
            line-height: 1.55;
        }

        .status-card .poin-badge {
            display: inline-block;
            font-size: 0.72rem;
            font-weight: 600;
            padding: 3px 10px;
            border-radius: 20px;
            background: rgba(34, 211, 238, 0.12);
            color: #0891b2;
            letter-spacing: 0.3px;
            text-transform: uppercase;
        }

        body.dark-mode .status-card .poin-badge {
            color: #67e8f9;
        }

        /* IZIN STEP CARD */
        .izin-card {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 1.8rem 1.4rem;
            box-shadow: var(--shadow-card);
            transition: all 0.3s ease;
            height: 100%;
            text-align: center;
            border-top: 3px solid transparent;
        }

        .izin-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-card-hover);
            border-top-color: var(--accent);
        }

        .izin-card .izin-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: rgba(255, 217, 102, 0.18);
            color: var(--accent);
            display: grid;
            place-items: center;
            font-size: 1.4rem;
            margin: 0 auto 1rem;
            transition: all 0.3s;
        }

        .izin-card:hover .izin-icon {
            background: var(--accent);
            color: #1a2634;
            transform: scale(1.05);
        }

        .izin-card h6 {
            font-weight: 700;
            font-size: 1rem;
            color: var(--text-primary);
            margin-bottom: 0.4rem;
        }

        .izin-card p {
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin: 0;
            line-height: 1.55;
        }

        /* PERAN CARD */
        .peran-card {
            background: var(--bg-card);
            border-radius: 20px;
            padding: 2.5rem 2rem;
            box-shadow: var(--shadow-card);
            transition: all 0.3s ease;
            height: 100%;
            position: relative;
            overflow: hidden;
        }

        .peran-card::after {
            content: '';
            position: absolute;
            top: -60px;
            right: -60px;
            width: 180px;
            height: 180px;
            border-radius: 50%;
            opacity: 0.10;
        }

        .peran-card.mahasiswa::after {
            background: #22d3ee;
        }

        .peran-card.pengelola::after {
            background: var(--accent);
        }

        .peran-card .peran-header {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 1.5rem;
            position: relative;
            z-index: 1;
        }

        .peran-card .peran-icon {
            width: 54px;
            height: 54px;
            border-radius: 14px;
            display: grid;
            place-items: center;
            font-size: 1.4rem;
            flex-shrink: 0;
        }

        .peran-card.mahasiswa .peran-icon {
            background: linear-gradient(135deg, #22d3ee, #0891b2);
            color: #03111f;
        }

        .peran-card.pengelola .peran-icon {
            background: linear-gradient(135deg, #ffd966, #f0c850);
            color: #1a2634;
        }

        .peran-card .peran-header h5 {
            font-weight: 800;
            font-size: 1.3rem;
            margin: 0;
            color: var(--text-primary);
        }

        .peran-card .peran-header small {
            display: block;
            font-size: 0.85rem;
            color: var(--text-secondary);
        }

        .peran-card ul {
            list-style: none;
            padding: 0;
            margin: 0;
            position: relative;
            z-index: 1;
        }

        .peran-card ul li {
            display: flex;
            gap: 10px;
            align-items: flex-start;
            padding: 0.55rem 0;
            font-size: 0.94rem;
            color: var(--text-secondary);
            border-bottom: 1px dashed rgba(0, 0, 0, 0.06);
            line-height: 1.55;
        }

        body.dark-mode .peran-card ul li {
            border-bottom-color: rgba(255, 255, 255, 0.06);
        }

        .peran-card ul li:last-child {
            border-bottom: none;
        }

        .peran-card ul li i {
            color: var(--accent);
            margin-top: 4px;
            flex-shrink: 0;
            font-size: 0.85rem;
        }

        .peran-card.mahasiswa ul li i {
            color: #22d3ee;
        }

        /* POIN INFO CARD */
        .poin-info {
            background:
                radial-gradient(circle at 20% 20%, rgba(34, 211, 238, 0.15), transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(255, 217, 102, 0.15), transparent 50%),
                var(--bg-card);
            border-radius: 20px;
            padding: 2.5rem 2rem;
            box-shadow: var(--shadow-card);
            border-left: 5px solid var(--accent);
            position: relative;
            overflow: hidden;
        }

        .poin-info h4 {
            font-weight: 800;
            font-size: 1.4rem;
            color: var(--text-primary);
            margin-bottom: 1rem;
        }

        .poin-info h4 i {
            color: var(--accent);
            margin-right: 10px;
        }

        .poin-info p {
            color: var(--text-secondary);
            font-size: 1rem;
            line-height: 1.75;
            margin-bottom: 0.8rem;
        }

        .poin-info p:last-child {
            margin-bottom: 0;
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

            .step-card {
                padding: 1.8rem 1.3rem 1.4rem;
            }

            .step-card .step-badge {
                font-size: 2rem;
                top: 12px;
                right: 14px;
            }

            .status-card {
                padding: 1.5rem 1.1rem;
                flex-direction: column;
                text-align: center;
                align-items: center;
            }

            .izin-card {
                padding: 1.5rem 1.1rem;
            }

            .peran-card {
                padding: 2rem 1.5rem;
            }

            .peran-card .peran-header h5 {
                font-size: 1.15rem;
            }

            .poin-info {
                padding: 2rem 1.5rem;
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

            .step-card {
                padding: 1.6rem 1.1rem 1.3rem;
            }

            .status-card {
                padding: 1.3rem 1rem;
            }

            .peran-card {
                padding: 1.6rem 1.2rem;
            }

            .poin-info {
                padding: 1.8rem 1.2rem;
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
                    <span>Cara Kerja</span>
                </div>
                <span class="hero-badge mt-3">
                    <span class="pulse-dot"></span>
                    Panduan Sistem
                </span>
                <h1 class="hero-title">
                    Bagaimana Presensi NFC<br>
                    <span>Bekerja?</span>
                </h1>
                <p class="hero-sub">
                    Empat langkah sederhana — dari tap kartu hingga poin kedisiplinan diperbarui.
                    Semua tercatat otomatis, cepat, dan transparan.
                </p>
            </div>
        </div>
    </section>

    <!-- ============================================================
         SECTION — ALUR PRESENSI UTAMA (4 LANGKAH)
         ============================================================ -->
    <section class="section-padding">
        <div class="container">
            <div class="text-center fade-up">
                <h2 class="section-title">Alur Presensi</h2>
                <p class="section-subtitle">Proses yang terjadi setiap kali mahasiswa melakukan presensi</p>
            </div>
            <div class="row g-4">
                <?php foreach ($alurUtama as $a): ?>
                    <div class="col-md-6 col-lg-3 fade-up">
                        <div class="step-card">
                            <span class="step-badge"><?= htmlspecialchars($a['step']) ?></span>
                            <div class="step-icon"><i class="fas <?= htmlspecialchars($a['icon']) ?>"></i></div>
                            <h6><?= htmlspecialchars($a['title']) ?></h6>
                            <p><?= htmlspecialchars($a['desc']) ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ============================================================
         SECTION — STATUS KEHADIRAN
         ============================================================ -->
    <section class="section-padding" style="background: var(--bg-body);">
        <div class="container">
            <div class="text-center fade-up">
                <h2 class="section-title">Status Kehadiran</h2>
                <p class="section-subtitle">Empat status yang mungkin tercatat pada setiap sesi presensi</p>
            </div>
            <div class="row g-4">
                <?php foreach ($statusKehadiran as $s): ?>
                    <div class="col-md-6 fade-up">
                        <div class="status-card" style="border-left-color: <?= htmlspecialchars($s['color']) ?>;">
                            <div class="status-icon" style="background: <?= htmlspecialchars($s['color']) ?>;">
                                <i class="fas <?= htmlspecialchars($s['icon']) ?>"></i>
                            </div>
                            <div>
                                <h6><?= htmlspecialchars($s['title']) ?></h6>
                                <p><?= htmlspecialchars($s['desc']) ?></p>
                                <span class="poin-badge"><?= htmlspecialchars($s['poin']) ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ============================================================
         SECTION — ALUR PENGAJUAN IZIN / SAKIT
         ============================================================ -->
    <section class="section-padding" style="background: var(--bg-body);">
        <div class="container">
            <div class="text-center fade-up">
                <h2 class="section-title">Cara Mengajukan Izin / Sakit</h2>
                <p class="section-subtitle">Bila tidak bisa hadir, ajukan izin melalui sistem dengan bukti pendukung</p>
            </div>
            <div class="row g-4">
                <?php foreach ($alurIzin as $i => $izin): ?>
                    <div class="col-md-6 col-lg-3 fade-up">
                        <div class="izin-card">
                            <div class="izin-icon"><i class="fas <?= htmlspecialchars($izin['icon']) ?>"></i></div>
                            <h6><?= ($i + 1) . '. ' ?><?= htmlspecialchars($izin['title']) ?></h6>
                            <p><?= htmlspecialchars($izin['desc']) ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ============================================================
         SECTION — PERAN MAHASISWA & PENGELOLA
         ============================================================ -->
    <section class="section-padding" style="background: var(--bg-body);">
        <div class="container">
            <div class="text-center fade-up">
                <h2 class="section-title">Peran Pengguna</h2>
                <p class="section-subtitle">Dua peran utama dengan tanggung jawab masing-masing</p>
            </div>
            <div class="row g-4">
                <div class="col-lg-6 fade-up">
                    <div class="peran-card mahasiswa h-100">
                        <div class="peran-header">
                            <div class="peran-icon"><i class="fas fa-user-graduate"></i></div>
                            <div>
                                <h5>Mahasiswa</h5>
                                <small>Pengguna utama sistem</small>
                            </div>
                        </div>
                        <ul>
                            <?php foreach ($peranMahasiswa as $p): ?>
                                <li>
                                    <i class="fas fa-check"></i>
                                    <span><?= htmlspecialchars($p) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <div class="col-lg-6 fade-up">
                    <div class="peran-card pengelola h-100">
                        <div class="peran-header">
                            <div class="peran-icon"><i class="fas fa-user-shield"></i></div>
                            <div>
                                <h5>Pengelola / Admin</h5>
                                <small>Pengatur kebijakan & data</small>
                            </div>
                        </div>
                        <ul>
                            <?php foreach ($peranPengelola as $p): ?>
                                <li>
                                    <i class="fas fa-check"></i>
                                    <span><?= htmlspecialchars($p) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ============================================================
         SECTION — SISTEM POIN
         ============================================================ -->
    <section class="section-padding" style="background: var(--bg-body);">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-8 mx-auto fade-up">
                    <div class="poin-info">
                        <h4><i class="fas fa-star-half-stroke"></i> Bagaimana Sistem Poin Bekerja?</h4>
                        <p>
                            Setiap mahasiswa memulai setiap minggu dengan <strong>saldo poin penuh</strong>.
                            Setiap kejadian presensi — hadir, terlambat, izin/sakit, atau alpha — akan
                            menyesuaikan saldo poin tersebut secara otomatis.
                        </p>
                        <p>
                            Semua perubahan poin dicatat dalam riwayat transaksi, sehingga mahasiswa dapat
                            melihat <strong>jejak audit lengkap</strong> dari setiap pengurangan atau
                            penambahan poin. Poin di-reset kembali setiap awal minggu baru, sehingga
                            kedisiplinan di minggu sebelumnya tidak permanen memengaruhi minggu berikutnya.
                        </p>
                        <p>
                            Detail kebijakan poin — termasuk besaran pengurangan untuk tiap status dan
                            konsekuensi akumulasi — dijelaskan di halaman
                            <a href="aturan.php" style="color: var(--accent); font-weight: 600;">Aturan Poin</a>.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ============================================================
         SECTION — CTA
         ============================================================ -->
    <section class="section-padding">
        <div class="container">
            <div class="cta-box fade-up">
                <h2>Siap Mencoba Presensi NFC?</h2>
                <p>Masuk ke sistem untuk melihat jadwal kuliah, memantau kehadiran, dan mengelola poin kedisiplinan Anda.</p>
                <a href="<?= $loginUrl ?>" class="btn-cta">
                    <i class="fas fa-sign-in-alt"></i> Masuk Sekarang
                </a>
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