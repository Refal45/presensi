<?php

/**
 * faq.php
 * Halaman FAQ (Frequently Asked Questions) — Presensi NFC
 * ------------------------------------------------------------
 * VERSI PUBLIK — Layout konsisten dengan halaman lainnya.
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
   DAFTAR FAQ
   ============================================================ */
$faqList = [
    [
        'category' => 'Umum',
        'question' => 'Apa itu Presensi NFC?',
        'answer'   => 'Presensi NFC adalah sistem pencatatan kehadiran mahasiswa berbasis web yang menggunakan kartu NFC (Near Field Communication) untuk autentikasi. Cukup menempelkan kartu pada mesin pembaca, kehadiran Anda akan langsung tercatat secara real-time.'
    ],
    [
        'category' => 'Umum',
        'question' => 'Apakah saya harus membawa kartu setiap hari?',
        'answer'   => 'Ya, kartu NFC wajib dibawa setiap kali Anda memasuki area kampus atau kelas. Kartu ini berfungsi sebagai identitas digital Anda untuk keperluan presensi (tap masuk dan tap keluar).'
    ],
    [
        'category' => 'Presensi',
        'question' => 'Bagaimana cara melakukan presensi?',
        'answer'   => 'Ada dua tahap presensi: 1) Tap Masuk saat Anda tiba di kelas. 2) Tap Keluar saat Anda meninggalkan kampus. Pastikan mesin menampilkan pesan sukses setelah kartu ditempelkan.'
    ],
    [
        'category' => 'Presensi',
        'question' => 'Apa yang terjadi jika saya lupa melakukan tap keluar?',
        'answer'   => 'Jika Anda hadir tepat waktu namun lupa tap keluar, maka tidak ada pengurangan poin (0 poin). Namun, jika Anda terlambat masuk DAN lupa tap keluar, Anda akan dikenakan pengurangan total 4 poin (-2 untuk keterlambatan, -2 untuk lupa tap keluar).'
    ],
    [
        'category' => 'Presensi',
        'question' => 'Kartu NFC saya tidak terbaca oleh mesin, apa yang harus dilakukan?',
        'answer'   => 'Coba bersihkan kartu atau tempelkan kembali dengan jarak yang lebih dekat. Jika masih gagal, segera lapor ke petugas/pengelola akademik di ruang administrasi untuk mendapatkan bantuan atau penggantian kartu.'
    ],
    [
        'category' => 'Sistem Poin',
        'question' => 'Bagaimana sistem poin kedisiplinan bekerja?',
        'answer'   => 'Setiap mahasiswa memulai minggu dengan 100 poin. Poin akan berkurang secara otomatis jika Anda terlambat, lupa tap keluar, atau tidak melakukan absen masuk sama sekali. Poin di-reset kembali menjadi 100 di awal minggu berikutnya.'
    ],
    [
        'category' => 'Sistem Poin',
        'question' => 'Apakah poin bisa bertambah jika saya hadir tepat waktu terus?',
        'answer'   => 'Tidak. Poin maksimal dalam seminggu adalah 100. Tujuan sistem poin ini adalah untuk mengukur kedisiplinan, sehingga hadir tepat waktu artinya Anda mempertahankan poin Anda (tidak berkurang).'
    ],
    [
        'category' => 'Izin & Sakit',
        'question' => 'Bagaimana cara mengajukan izin jika saya sakit atau ada keperluan?',
        'answer'   => 'Anda dapat mengajukan izin melalui menu "Izin/Sakit" di dalam sistem (setelah login). Unggah bukti pendukung seperti surat dokter atau surat izin resmi maksimal 1x24 jam setelah jadwal kelas berakhir.'
    ],
    [
        'category' => 'Izin & Sakit',
        'question' => 'Apakah pengajuan izin otomatis disetujui?',
        'answer'   => 'Tidak. Pengajuan Anda akan berstatus "Menunggu" dan akan ditinjau oleh pengelola/dosen. Jika disetujui, status kehadiran Anda akan berubah dan poin Anda tidak akan berkurang.'
    ],
    [
        'category' => 'Integritas',
        'question' => 'Apakah saya boleh menitipkan kartu NFC saya ke teman untuk diabsenkan?',
        'answer'   => 'Sangat tidak boleh. Sistem memiliki validasi berlapis. Menitipkan kartu merupakan bentuk kecurangan dan pelanggaran berat yang akan ditindaklanjuti sesuai kebijakan akademik.'
    ],
];
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FAQ — Presensi NFC | Kehadiran Mahasiswa</title>
    <meta name="description" content="Pertanyaan yang sering diajukan seputar sistem Presensi NFC.">

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

        /* ACCORDION CUSTOMIZATION */
        .faq-accordion .accordion-item {
            background-color: var(--bg-card);
            border: 1px solid transparent;
            border-radius: 16px !important;
            margin-bottom: 1rem;
            box-shadow: var(--shadow-card);
            transition: all 0.3s ease;
            overflow: hidden;
        }

        .faq-accordion .accordion-item:hover {
            box-shadow: var(--shadow-card-hover);
            border-color: rgba(255, 217, 102, 0.3);
        }

        .faq-accordion .accordion-button {
            background-color: var(--bg-card);
            color: var(--text-primary);
            font-weight: 700;
            font-size: 1.05rem;
            padding: 1.25rem 1.5rem;
            box-shadow: none;
            transition: all 0.3s ease;
        }

        .faq-accordion .accordion-button:not(.collapsed) {
            background-color: rgba(34, 211, 238, 0.05);
            color: #0891b2;
        }

        body.dark-mode .faq-accordion .accordion-button:not(.collapsed) {
            color: #67e8f9;
        }

        .faq-accordion .accordion-button::after {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%234a5a6a'%3e%3cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3e%3c/svg%3e");
            transition: transform 0.3s ease;
        }

        body.dark-mode .faq-accordion .accordion-button::after {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23b0c0d0'%3e%3cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3e%3c/svg%3e");
        }

        .faq-accordion .accordion-body {
            color: var(--text-secondary);
            font-size: 0.95rem;
            line-height: 1.7;
            padding: 0 1.5rem 1.5rem 1.5rem;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
            margin-top: 0.5rem;
            padding-top: 1rem;
        }

        body.dark-mode .faq-accordion .accordion-body {
            border-top-color: rgba(255, 255, 255, 0.05);
        }

        .faq-category-badge {
            display: inline-block;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--accent);
            margin-bottom: 0.4rem;
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

            .faq-accordion .accordion-button {
                font-size: 0.95rem;
                padding: 1rem 1.2rem;
            }

            .faq-accordion .accordion-body {
                padding: 0 1.2rem 1.2rem 1.2rem;
                font-size: 0.9rem;
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
                    <span>FAQ</span>
                </div>
                <span class="hero-badge mt-3">
                    <span class="pulse-dot"></span>
                    Pusat Bantuan
                </span>
                <h1 class="hero-title">
                    Pertanyaan yang<br>
                    <span>Sering Diajukan</span>
                </h1>
                <p class="hero-sub">
                    Temukan jawaban cepat seputar penggunaan sistem presensi, kartu NFC, dan aturan poin kedisiplinan.
                </p>
            </div>
        </div>
    </section>

    <!-- ============================================================
         SECTION — FAQ ACCORDION
         ============================================================ -->
    <section class="section-padding">
        <div class="container">
            <div class="row">
                <div class="col-lg-10 mx-auto fade-up">
                    <div class="accordion faq-accordion" id="faqAccordion">
                        <?php foreach ($faqList as $index => $faq): ?>
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="heading<?= $index ?>">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                        data-bs-target="#collapse<?= $index ?>" aria-expanded="false"
                                        aria-controls="collapse<?= $index ?>">
                                        <div>
                                            <span class="faq-category-badge"><?= htmlspecialchars($faq['category']) ?></span><br>
                                            <?= htmlspecialchars($faq['question']) ?>
                                        </div>
                                    </button>
                                </h2>
                                <div id="collapse<?= $index ?>" class="accordion-collapse collapse"
                                    aria-labelledby="heading<?= $index ?>" data-bs-parent="#faqAccordion">
                                    <div class="accordion-body">
                                        <?= htmlspecialchars($faq['answer']) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
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
                <h2>Masih Ada Pertanyaan?</h2>
                <p>Jika pertanyaan Anda tidak terjawab di atas, silakan hubungi pengelola akademik atau masuk ke sistem untuk info lebih lanjut.</p>
                <a href="<?= $loginUrl ?>" class="btn-cta">
                    <i class="fas fa-sign-in-alt"></i> Masuk ke Sistem
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