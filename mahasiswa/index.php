<?php

/**
 * index.php — Dashboard Mahasiswa
 * Presensi NFC | Sistem Kehadiran Mahasiswa
 * ------------------------------------------------------------
 * Navbar desktop: brand di kiri (container-fluid).
 * Hero mobile: mengisi penuh layar Android (100dvh).
 */

/* ============================================================
   KONEKSI & SESSION
   ============================================================ */
$koneksiFile = __DIR__ . '/../koneksi.php';
if (!file_exists($koneksiFile)) {
    $koneksiFile = __DIR__ . '/koneksi.php';
}
require_once $koneksiFile;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$loginUrl  = '../auth/login.php';
$logoutUrl = 'logout.php';

/* ============================================================
   OTORISASI
   ============================================================ */
if (empty($_SESSION['user_id'])) {
    header('Location: ' . $loginUrl);
    exit;
}

$role = $_SESSION['role'] ?? '';
if ($role === 'admin') {
    header('Location: ../admin/index2.php');
    exit;
}
if ($role !== 'mahasiswa') {
    header('Location: ' . $loginUrl);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$db     = $conn ?? $koneksi ?? null;

if (!$db instanceof mysqli) {
    http_response_code(500);
    die('Koneksi database tidak tersedia.');
}

/* ============================================================
   AMBIL DATA USER
   ============================================================ */
$stmt = $db->prepare("SELECT id, nim, name, email, class_name FROM users WHERE id = ? AND role = 'mahasiswa' AND is_active = 1");
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    session_destroy();
    header('Location: ' . $loginUrl);
    exit;
}

$userName    = $user['name'];
$userClass   = $user['class_name'] ?: 'Tanpa Kelas';
$userNim     = $user['nim'] ?: '—';
$userInitial = function_exists('mb_substr')
    ? mb_strtoupper(mb_substr($userName, 0, 1, 'UTF-8'), 'UTF-8')
    : strtoupper(substr($userName, 0, 1));

/* ============================================================
   HELPER QUERY
   ============================================================ */
function q1(mysqli $db, string $sql, string $types = '', array $params = []): int
{
    $stmt = $db->prepare($sql);
    if (!$stmt) return 0;
    if ($types !== '') $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $val = 0;
    $stmt->bind_result($val);
    $stmt->fetch();
    $stmt->close();
    return (int)$val;
}

/* ============================================================
   STATISTIK BULAN INI
   ============================================================ */
$monthStart = date('Y-m-01');
$monthEnd   = date('Y-m-t');

$stats = [
    'hadir'      => q1($db, "SELECT COUNT(*) FROM attendances WHERE user_id=? AND attendance_date BETWEEN ? AND ? AND status='hadir'", 'iss', [$userId, $monthStart, $monthEnd]),
    'terlambat'  => q1($db, "SELECT COUNT(*) FROM attendances WHERE user_id=? AND attendance_date BETWEEN ? AND ? AND status='terlambat'", 'iss', [$userId, $monthStart, $monthEnd]),
    'izin_sakit' => q1($db, "SELECT COUNT(*) FROM attendances WHERE user_id=? AND attendance_date BETWEEN ? AND ? AND status IN ('izin','sakit')", 'iss', [$userId, $monthStart, $monthEnd]),
    'alpha'      => q1($db, "SELECT COUNT(*) FROM attendances WHERE user_id=? AND attendance_date BETWEEN ? AND ? AND status='alpha'", 'iss', [$userId, $monthStart, $monthEnd]),
];

/* ============================================================
   POIN & PERINGKAT MINGGU INI
   ============================================================ */
$weekStart = date('Y-m-d', strtotime('monday this week'));

$currentPoints = 100;
$stmt = $db->prepare("SELECT current_points FROM weekly_point_balances WHERE user_id = ? AND week_start = ? LIMIT 1");
$stmt->bind_param('is', $userId, $weekStart);
$stmt->execute();
if ($row = $stmt->get_result()->fetch_assoc()) {
    $currentPoints = (int)$row['current_points'];
}
$stmt->close();

$rank = 1;
$stmt = $db->prepare("SELECT COUNT(*) + 1 AS r FROM weekly_point_balances WHERE week_start = ? AND current_points > ?");
$stmt->bind_param('si', $weekStart, $currentPoints);
$stmt->execute();
if ($row = $stmt->get_result()->fetch_assoc()) {
    $rank = (int)$row['r'];
}
$stmt->close();

/* ============================================================
   JADWAL HARI INI
   ============================================================ */
$dayMap = [
    'Sunday'    => 'minggu',
    'Monday'    => 'senin',
    'Tuesday'   => 'selasa',
    'Wednesday' => 'rabu',
    'Thursday'  => 'kamis',
    'Friday'    => 'jumat',
    'Saturday'  => 'sabtu',
];
$todayDay = $dayMap[date('l')] ?? 'senin';

$todaySchedule = [];
if (!empty($user['class_name'])) {
    $stmt = $db->prepare("SELECT id, course_name, class_name, lecturer_name, start_time, end_time, attendance_code
                          FROM schedules
                          WHERE day_of_week = ? AND class_name = ? AND is_active = 1
                          ORDER BY start_time ASC");
    $stmt->bind_param('ss', $todayDay, $user['class_name']);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $todaySchedule[] = $r;
    $stmt->close();
}

/* ============================================================
   AKTIVITAS ABSENSI TERBARU
   ============================================================ */
$recent = [];
$stmt = $db->prepare("SELECT a.attendance_date, a.status, a.check_in_at, s.course_name
                      FROM attendances a
                      LEFT JOIN schedules s ON s.id = a.schedule_id
                      WHERE a.user_id = ?
                      ORDER BY a.attendance_date DESC, a.check_in_at DESC
                      LIMIT 5");
$stmt->bind_param('i', $userId);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) $recent[] = $r;
$stmt->close();

/* ============================================================
   MENU UTAMA
   ============================================================ */
$menus = [
    ['href' => 'index.php',      'icon' => 'fa-home',        'label' => 'Dashboard',       'desc' => 'Ringkasan aktivitas Anda'],
    ['href' => 'absensi.php',    'icon' => 'fa-id-card',     'label' => 'Absensi',         'desc' => 'Tap kartu NFC untuk hadir'],
    ['href' => 'riwayat.php',    'icon' => 'fa-history',     'label' => 'Riwayat Absensi', 'desc' => 'Rekap kehadiran Anda'],
    ['href' => 'poin.php',       'icon' => 'fa-star',        'label' => 'Poin Disiplin',   'desc' => 'Poin kedisiplinan mingguan'],
    ['href' => 'leaderboard.php', 'icon' => 'fa-trophy',      'label' => 'Leaderboard',     'desc' => 'Peringkat poin mahasiswa'],
    ['href' => 'izin.php',       'icon' => 'fa-envelope',    'label' => 'Izin & Sakit',    'desc' => 'Ajukan izin atau sakit'],
    ['href' => 'profil.php',     'icon' => 'fa-user',        'label' => 'Profil Saya',     'desc' => 'Kelola data pribadi'],
    ['href' => 'keamanan.php',   'icon' => 'fa-lock',        'label' => 'Keamanan Akun',   'desc' => 'Password & keamanan'],
];

$currentPage = basename($_SERVER['PHP_SELF']);

$statusLabel = [
    'hadir'     => ['label' => 'Hadir',     'class' => 'status-hadir'],
    'terlambat' => ['label' => 'Terlambat', 'class' => 'status-terlambat'],
    'izin'      => ['label' => 'Izin',      'class' => 'status-izin'],
    'sakit'     => ['label' => 'Sakit',     'class' => 'status-sakit'],
    'alpha'     => ['label' => 'Alpha',     'class' => 'status-alpha'],
];
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#0d1b2a">
    <title>Dashboard — Presensi NFC | Mahasiswa</title>
    <meta name="description" content="Dashboard mahasiswa Presensi NFC — pantau kehadiran, poin disiplin, dan jadwal kuliah.">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <style>
        /* ============================================================
           CSS VARIABLES — LIGHT MODE (default)
           ============================================================ */
        :root {
            --bg-body: #f4f7fc;
            --bg-card: #ffffff;
            --bg-navbar: transparent;
            --bg-navbar-scroll: rgba(13, 27, 42, 0.88);
            --bg-footer: #0d1b2a;
            --bg-hero-overlay: rgba(0, 0, 0, 0.55);
            --bg-stat: #ffffff;
            --bg-mobile-nav: #ffffff;
            --text-primary: #1a2634;
            --text-secondary: #4a5a6a;
            --text-white: #ffffff;
            --text-muted: rgba(255, 255, 255, 0.75);
            --border-color: rgba(255, 255, 255, 0.18);
            --border-soft: rgba(0, 0, 0, 0.06);
            --shadow-card: 0 8px 30px rgba(0, 0, 0, 0.06);
            --shadow-card-hover: 0 16px 48px rgba(0, 0, 0, 0.10);
            --btn-primary-bg: #ffd966;
            --btn-primary-color: #1a2634;
            --btn-primary-hover: #ffed4a;
            --accent: #ffd966;
            --accent-strong: #f0c850;
            --stat-number: #0d6efd;
            --link-color: rgba(255, 255, 255, 0.7);
            --link-hover: #ffffff;
            --cyan: #22d3ee;
            --green: #22c55e;
            --amber: #f59e0b;
            --red: #ef4444;
            --blue: #3b82f6;
        }

        /* DARK MODE */
        body.dark-mode {
            --bg-body: #121926;
            --bg-card: #1e2a3a;
            --bg-navbar: transparent;
            --bg-navbar-scroll: rgba(18, 25, 38, 0.94);
            --bg-footer: #0a1118;
            --bg-hero-overlay: rgba(0, 0, 0, 0.68);
            --bg-stat: #1e2a3a;
            --bg-mobile-nav: #1e2a3a;
            --text-primary: #e8edf3;
            --text-secondary: #b0c0d0;
            --text-white: #ffffff;
            --text-muted: rgba(200, 200, 200, 0.75);
            --border-color: rgba(255, 255, 255, 0.10);
            --border-soft: rgba(255, 255, 255, 0.08);
            --shadow-card: 0 8px 30px rgba(0, 0, 0, 0.3);
            --shadow-card-hover: 0 16px 48px rgba(0, 0, 0, 0.5);
            --btn-primary-bg: #f0c850;
            --btn-primary-color: #1a2634;
            --btn-primary-hover: #ffd966;
            --accent: #f0c850;
            --accent-strong: #ffd966;
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
            padding-bottom: 0;
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

        /* ============================================================
           NAVBAR DESKTOP — full width, brand di kiri
           ============================================================ */
        .navbar-transparent {
            position: fixed !important;
            top: 0;
            left: 0;
            right: 0;
            width: 100%;
            z-index: 1050;
            padding: 0.8rem 0;
            background: var(--bg-navbar) !important;
            transition: background 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94),
                box-shadow 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94),
                backdrop-filter 0.4s ease;
        }

        .navbar-transparent.scrolled {
            background: var(--bg-navbar-scroll) !important;
            backdrop-filter: blur(14px) saturate(180%);
            -webkit-backdrop-filter: blur(14px) saturate(180%);
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.12);
            border-bottom: 1px solid var(--border-color);
        }

        /* Full-width container — brand flush left */
        .navbar-transparent .navbar-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            width: 100%;
            padding-left: clamp(16px, 4vw, 48px);
            padding-right: clamp(16px, 4vw, 48px);
            gap: 12px;
        }

        .navbar-transparent .navbar-brand {
            display: flex;
            align-items: center;
            gap: 0.85rem;
            padding: 0;
            flex-shrink: 0;
            margin-right: 0;
        }

        .brand-logo-box {
            width: 48px;
            height: 48px;
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
            font-size: 1.35rem;
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
            margin-top: 2px;
        }

        #navbarDesktop {
            flex-grow: 1;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        #navbarDesktop .navbar-nav {
            align-items: center;
            gap: 0.2rem;
            flex-wrap: wrap;
            margin: 0 auto;
        }

        #navbarDesktop .navbar-nav .nav-link {
            color: rgba(255, 255, 255, 0.92) !important;
            font-weight: 500;
            font-size: 0.9rem;
            padding: 0.5rem 0.8rem !important;
            border-radius: 8px;
            transition: color 0.2s, background 0.2s;
            letter-spacing: 0.2px;
            white-space: nowrap;
        }

        #navbarDesktop .navbar-nav .nav-link:hover,
        #navbarDesktop .navbar-nav .nav-link.active {
            color: #ffffff !important;
            background: rgba(255, 255, 255, 0.15);
        }

        #navbarDesktop .navbar-nav .nav-link i {
            margin-right: 5px;
            font-size: 0.85rem;
        }

        .navbar-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
        }

        .theme-toggle {
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: #ffffff;
            border-radius: 30px;
            padding: 0.4rem 0.85rem;
            cursor: pointer;
            transition: background 0.2s, border-color 0.2s;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .theme-toggle:hover {
            background: rgba(255, 255, 255, 0.25);
        }

        .theme-toggle i {
            font-size: 1rem;
        }

        .user-dropdown {
            position: relative;
        }

        .user-trigger {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 0.3rem 0.7rem 0.3rem 0.3rem;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 40px;
            color: #ffffff;
            cursor: pointer;
            transition: background 0.2s;
        }

        .user-trigger:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, #ffd966, #f0c850);
            color: #1a2634;
            display: grid;
            place-items: center;
            font-weight: 800;
            font-size: 0.85rem;
        }

        .user-name-nav {
            font-size: 0.85rem;
            font-weight: 600;
            max-width: 110px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .user-menu {
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            min-width: 220px;
            background: var(--bg-card);
            border: 1px solid var(--border-soft);
            border-radius: 12px;
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.25);
            padding: 6px;
            display: none;
            z-index: 1060;
        }

        .user-menu.show {
            display: block;
        }

        .user-menu a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            border-radius: 8px;
            color: var(--text-primary);
            font-size: 0.9rem;
            font-weight: 500;
            transition: background 0.15s;
        }

        .user-menu a:hover {
            background: rgba(255, 217, 102, 0.15);
        }

        .user-menu a i {
            width: 18px;
            text-align: center;
            color: var(--accent-strong);
        }

        .user-menu a.logout-link {
            color: #ef4444;
        }

        .user-menu a.logout-link i {
            color: #ef4444;
        }

        .user-menu hr {
            margin: 6px 4px;
            border-color: var(--border-soft);
            opacity: 1;
        }

        /* ============================================================
           MOBILE TOP BAR
           ============================================================ */
        .mobile-top-bar {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1050;
            padding: 0.6rem 1rem;
            padding-top: calc(0.6rem + env(safe-area-inset-top, 0));
            background: transparent;
            transition: background 0.3s, backdrop-filter 0.3s;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }

        .mobile-top-bar.scrolled {
            background: var(--bg-navbar-scroll);
            backdrop-filter: blur(14px) saturate(180%);
            -webkit-backdrop-filter: blur(14px) saturate(180%);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
        }

        .mobile-top-bar .brand-mini {
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: 0;
        }

        .mobile-top-bar .brand-mini .brand-logo-box {
            width: 38px;
            height: 38px;
            font-size: 12px;
            border-radius: 10px;
            flex-shrink: 0;
        }

        .mobile-top-bar .brand-mini .brand-title {
            color: #ffffff;
            font-weight: 700;
            font-size: 1rem;
            letter-spacing: 0.3px;
            text-shadow: 0 2px 6px rgba(0, 0, 0, 0.3);
            white-space: nowrap;
        }

        .mobile-top-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
        }

        .mobile-icon-btn {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #ffffff;
            display: grid;
            place-items: center;
            font-size: 1rem;
            cursor: pointer;
            transition: background 0.2s;
            padding: 0;
        }

        .mobile-icon-btn:hover {
            background: rgba(255, 255, 255, 0.25);
        }

        .mobile-icon-btn.user-avatar-mobile {
            background: linear-gradient(135deg, #ffd966, #f0c850);
            color: #1a2634;
            font-weight: 800;
            font-size: 0.9rem;
        }

        /* ============================================================
           HERO — full screen on mobile
           ============================================================ */
        .hero-section {
            min-height: 100vh;
            width: 100%;
            background: url('background.jpg') center center / cover no-repeat;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 100px 0 60px;
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
            font-size: 1.1rem;
            font-weight: 400;
            color: rgba(255, 255, 255, 0.88);
            max-width: 640px;
            margin-bottom: 2rem;
            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.25);
        }

        .hero-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 1.6rem;
        }

        .hero-meta span {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.18);
            border-radius: 20px;
            font-size: 0.82rem;
            color: rgba(255, 255, 255, 0.9);
            font-weight: 500;
            backdrop-filter: blur(4px);
        }

        .hero-meta span i {
            color: var(--accent);
        }

        .hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.8rem;
        }

        .btn-hero-primary {
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

        .btn-hero-primary:hover {
            background: var(--btn-primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(255, 217, 102, 0.45);
            color: var(--btn-primary-color);
        }

        .btn-hero-ghost {
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.28);
            color: #ffffff;
            font-weight: 600;
            padding: 0.9rem 2.2rem;
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.25s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            backdrop-filter: blur(4px);
        }

        .btn-hero-ghost:hover {
            background: rgba(255, 255, 255, 0.22);
            color: #ffffff;
            transform: translateY(-2px);
        }

        .scroll-hint {
            position: absolute;
            left: 0;
            right: 0;
            bottom: 26px;
            z-index: 3;
            text-align: center;
            color: rgba(255, 255, 255, 0.65);
            font-size: 0.78rem;
            letter-spacing: 3px;
            text-transform: uppercase;
            font-weight: 500;
            cursor: pointer;
            user-select: none;
            animation: floatDown 2s infinite ease-in-out;
            transition: color 0.3s;
        }

        .scroll-hint:hover {
            color: var(--accent);
        }

        .scroll-hint i {
            display: block;
            font-size: 1.3rem;
            margin-top: 4px;
        }

        @keyframes floatDown {

            0%,
            100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(8px);
            }
        }

        /* ============================================================
           SECTIONS
           ============================================================ */
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
            font-size: 1.05rem;
            margin-bottom: 2.5rem;
            transition: color 0.3s ease;
        }

        /* ============================================================
           STAT CARD
           ============================================================ */
        .stat-card {
            background: var(--bg-stat);
            border-radius: 16px;
            padding: 1.8rem 1rem;
            text-align: center;
            box-shadow: var(--shadow-card);
            transition: transform 0.3s, background 0.3s ease, box-shadow 0.3s ease;
            height: 100%;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-card-hover);
        }

        .stat-card .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: rgba(255, 217, 102, 0.18);
            color: var(--accent);
            display: grid;
            place-items: center;
            font-size: 1.6rem;
            margin: 0 auto 1rem;
            transition: all 0.3s;
        }

        .stat-card:hover .stat-icon {
            background: var(--accent);
            color: #1a2634;
            transform: scale(1.05);
        }

        .stat-card .stat-number {
            font-weight: 800;
            font-size: 2.4rem;
            color: var(--stat-number);
            line-height: 1.1;
            transition: color 0.3s ease;
        }

        .stat-card .stat-label {
            color: var(--text-secondary);
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 0.4rem;
            transition: color 0.3s ease;
        }

        .stat-card.stat-hadir .stat-icon {
            background: rgba(34, 197, 94, 0.15);
            color: #22c55e;
        }

        .stat-card.stat-hadir .stat-number {
            color: #22c55e;
        }

        .stat-card.stat-hadir:hover .stat-icon {
            background: #22c55e;
            color: #ffffff;
        }

        .stat-card.stat-terlambat .stat-icon {
            background: rgba(245, 158, 11, 0.15);
            color: #f59e0b;
        }

        .stat-card.stat-terlambat .stat-number {
            color: #f59e0b;
        }

        .stat-card.stat-terlambat:hover .stat-icon {
            background: #f59e0b;
            color: #ffffff;
        }

        .stat-card.stat-izin .stat-icon {
            background: rgba(59, 130, 246, 0.15);
            color: #3b82f6;
        }

        .stat-card.stat-izin .stat-number {
            color: #3b82f6;
        }

        .stat-card.stat-izin:hover .stat-icon {
            background: #3b82f6;
            color: #ffffff;
        }

        .stat-card.stat-poin .stat-icon {
            background: rgba(168, 85, 247, 0.15);
            color: #a855f7;
        }

        .stat-card.stat-poin .stat-number {
            color: #a855f7;
        }

        .stat-card.stat-poin:hover .stat-icon {
            background: #a855f7;
            color: #ffffff;
        }

        /* ============================================================
           MENU CARD
           ============================================================ */
        .menu-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            background: var(--bg-card);
            border-radius: 18px;
            padding: 1.8rem 1.3rem;
            box-shadow: var(--shadow-card);
            transition: all 0.3s ease;
            height: 100%;
            border: 1px solid transparent;
            color: inherit;
        }

        .menu-card:hover {
            transform: translateY(-6px);
            box-shadow: var(--shadow-card-hover);
            border-color: rgba(255, 217, 102, 0.4);
            color: inherit;
        }

        .menu-card .menu-icon {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            background: rgba(255, 217, 102, 0.16);
            color: var(--accent-strong);
            display: grid;
            place-items: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s;
        }

        .menu-card:hover .menu-icon {
            background: var(--accent);
            color: #1a2634;
            transform: scale(1.08) rotate(-4deg);
        }

        .menu-card h6 {
            font-weight: 700;
            font-size: 1.05rem;
            color: var(--text-primary);
            margin-bottom: 0.4rem;
            transition: color 0.3s ease;
        }

        .menu-card p {
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin-bottom: 0.9rem;
            flex: 1;
            line-height: 1.5;
            transition: color 0.3s ease;
        }

        .menu-card .menu-arrow {
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--accent-strong);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: gap 0.3s;
        }

        .menu-card:hover .menu-arrow {
            gap: 12px;
        }

        /* ============================================================
           JADWAL HARI INI
           ============================================================ */
        .schedule-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .schedule-item {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 16px 20px;
            background: var(--bg-card);
            border-radius: 14px;
            box-shadow: var(--shadow-card);
            border-left: 4px solid var(--accent);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .schedule-item:hover {
            transform: translateX(4px);
            box-shadow: var(--shadow-card-hover);
        }

        .schedule-time {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-width: 80px;
            padding: 8px 10px;
            background: rgba(255, 217, 102, 0.14);
            border-radius: 10px;
            color: var(--accent-strong);
            font-weight: 700;
            font-size: 0.95rem;
            line-height: 1.2;
        }

        .schedule-time small {
            font-size: 0.68rem;
            font-weight: 500;
            color: var(--text-secondary);
            margin-top: 2px;
            letter-spacing: 0.3px;
        }

        .schedule-body {
            flex: 1;
            min-width: 0;
        }

        .schedule-body h6 {
            font-size: 1rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 3px;
            overflow-wrap: anywhere;
        }

        .schedule-body .meta {
            font-size: 0.82rem;
            color: var(--text-secondary);
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .schedule-body .meta span i {
            color: var(--accent-strong);
            margin-right: 4px;
        }

        .empty-schedule {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-secondary);
            background: var(--bg-card);
            border-radius: 14px;
            box-shadow: var(--shadow-card);
        }

        .empty-schedule i {
            font-size: 2.5rem;
            color: var(--accent);
            opacity: 0.6;
            margin-bottom: 12px;
        }

        /* ============================================================
           RECENT ACTIVITY
           ============================================================ */
        .activity-list {
            background: var(--bg-card);
            border-radius: 14px;
            overflow: hidden;
            box-shadow: var(--shadow-card);
        }

        .activity-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px 20px;
            border-bottom: 1px solid var(--border-soft);
            transition: background 0.15s;
        }

        .activity-item:last-child {
            border-bottom: 0;
        }

        .activity-item:hover {
            background: rgba(255, 217, 102, 0.05);
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: rgba(34, 211, 238, 0.14);
            color: var(--cyan);
            display: grid;
            place-items: center;
            font-size: 1rem;
            flex-shrink: 0;
        }

        .activity-body {
            flex: 1;
            min-width: 0;
        }

        .activity-body h6 {
            font-size: 0.92rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
            overflow-wrap: anywhere;
        }

        .activity-body small {
            color: var(--text-secondary);
            font-size: 0.78rem;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            flex-shrink: 0;
        }

        .status-hadir {
            background: rgba(34, 197, 94, 0.15);
            color: #16a34a;
        }

        .status-terlambat {
            background: rgba(245, 158, 11, 0.15);
            color: #d97706;
        }

        .status-izin {
            background: rgba(59, 130, 246, 0.15);
            color: #2563eb;
        }

        .status-sakit {
            background: rgba(168, 85, 247, 0.15);
            color: #9333ea;
        }

        .status-alpha {
            background: rgba(239, 68, 68, 0.15);
            color: #dc2626;
        }

        .empty-activity {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-secondary);
        }

        .empty-activity i {
            font-size: 2.5rem;
            color: var(--accent);
            opacity: 0.6;
            margin-bottom: 12px;
        }

        /* ============================================================
           FOOTER
           ============================================================ */
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

        .footer .copyright {
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            padding-top: 1.5rem;
            margin-top: 2rem;
            font-size: 0.9rem;
            text-align: center;
            color: rgba(255, 255, 255, 0.5);
        }

        /* ============================================================
           MOBILE BOTTOM NAV — APP STYLE
           ============================================================ */
        .mobile-bottom-nav {
            display: none;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: var(--bg-mobile-nav);
            border-top: 1px solid var(--border-soft);
            box-shadow: 0 -8px 32px rgba(0, 0, 0, 0.12);
            z-index: 1040;
            padding: 6px 4px calc(6px + env(safe-area-inset-bottom, 0));
            transition: background 0.3s;
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
        }

        body.dark-mode .mobile-bottom-nav {
            background: rgba(30, 42, 58, 0.94);
        }

        .mobile-nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 3px;
            padding: 6px 4px;
            flex: 1;
            color: var(--text-secondary);
            font-size: 0.68rem;
            font-weight: 500;
            text-decoration: none;
            transition: color 0.2s;
            position: relative;
            border: 0;
            background: transparent;
            font-family: inherit;
            cursor: pointer;
            -webkit-tap-highlight-color: transparent;
        }

        .mobile-nav-item i {
            font-size: 1.15rem;
            transition: transform 0.2s;
        }

        .mobile-nav-item.active {
            color: var(--accent-strong);
        }

        .mobile-nav-item.active i {
            transform: scale(1.1);
        }

        .mobile-nav-item.active::before {
            content: '';
            position: absolute;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 28px;
            height: 3px;
            background: var(--accent-strong);
            border-radius: 0 0 3px 3px;
        }

        /* Offcanvas bottom sheet */
        .offcanvas-bottom-nav {
            background: var(--bg-card);
            color: var(--text-primary);
            border-top-left-radius: 20px;
            border-top-right-radius: 20px;
        }

        .offcanvas-bottom-nav .offcanvas-header {
            border-bottom: 1px solid var(--border-soft);
            padding: 1rem 1.2rem;
        }

        .offcanvas-bottom-nav .offcanvas-title {
            font-weight: 800;
            font-size: 1.1rem;
        }

        .offcanvas-bottom-nav .offcanvas-body {
            padding: 1rem 1.2rem;
        }

        .nav-list-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px 14px;
            border-radius: 12px;
            color: var(--text-primary);
            font-weight: 500;
            font-size: 0.95rem;
            transition: background 0.15s;
            margin-bottom: 4px;
        }

        .nav-list-item:hover,
        .nav-list-item.active {
            background: rgba(255, 217, 102, 0.12);
            color: var(--accent-strong);
        }

        .nav-list-item .nav-list-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: rgba(255, 217, 102, 0.14);
            color: var(--accent-strong);
            display: grid;
            place-items: center;
            font-size: 1.05rem;
            flex-shrink: 0;
        }

        .nav-list-item .nav-list-body {
            flex: 1;
            min-width: 0;
        }

        .nav-list-item .nav-list-body small {
            display: block;
            color: var(--text-secondary);
            font-size: 0.75rem;
            font-weight: 400;
            margin-top: 2px;
        }

        .nav-list-item.logout-list {
            color: #ef4444;
        }

        .nav-list-item.logout-list .nav-list-icon {
            background: rgba(239, 68, 68, 0.14);
            color: #ef4444;
        }

        /* ============================================================
           FADE-UP ANIMATION
           ============================================================ */
        .fade-up {
            opacity: 0;
            transform: translateY(30px);
            transition: opacity 0.8s ease, transform 0.8s ease;
        }

        .fade-up.visible {
            opacity: 1;
            transform: translateY(0);
        }

        /* ============================================================
           RESPONSIVE — TABLET & MOBILE
           ============================================================ */
        @media (max-width: 1200px) {
            #navbarDesktop .navbar-nav .nav-link {
                padding: 0.5rem 0.55rem !important;
                font-size: 0.85rem;
            }

            .user-name-nav {
                display: none;
            }
        }

        @media (max-width: 992px) {
            #navbarDesktop {
                display: none !important;
            }

            .navbar-transparent {
                display: none !important;
            }

            .mobile-top-bar {
                display: flex;
            }

            .mobile-bottom-nav {
                display: flex;
            }

            body {
                padding-bottom: 80px;
            }

            /* HERO FULL SCREEN MOBILE */
            .hero-section {
                min-height: 100vh;
                min-height: 100svh;
                min-height: 100dvh;
                padding: calc(90px + env(safe-area-inset-top, 0)) 0 calc(90px + env(safe-area-inset-bottom, 0));
            }

            .hero-content {
                padding: 0 20px;
            }

            .hero-title {
                font-size: 2rem;
            }

            .hero-sub {
                font-size: 0.95rem;
                margin-bottom: 1.6rem;
            }

            .hero-badge {
                margin-bottom: 18px;
            }

            .hero-meta {
                margin-bottom: 1.3rem;
            }

            .section-title {
                font-size: 1.8rem;
            }

            .section-padding {
                padding: 2.5rem 0;
            }

            .stat-card {
                padding: 1.4rem 0.8rem;
            }

            .stat-card .stat-icon {
                width: 46px;
                height: 46px;
                font-size: 1.25rem;
            }

            .stat-card .stat-number {
                font-size: 1.7rem;
            }

            .stat-card .stat-label {
                font-size: 0.72rem;
            }

            .menu-card {
                padding: 1.5rem 1rem;
            }

            .menu-card .menu-icon {
                width: 52px;
                height: 52px;
                font-size: 1.3rem;
            }

            .menu-card h6 {
                font-size: 0.98rem;
            }

            .menu-card p {
                font-size: 0.8rem;
            }

            .schedule-item {
                padding: 12px 14px;
                gap: 12px;
            }

            .schedule-time {
                min-width: 68px;
                padding: 6px 8px;
                font-size: 0.85rem;
            }

            .schedule-body h6 {
                font-size: 0.92rem;
            }

            .schedule-body .meta {
                font-size: 0.76rem;
            }

            .activity-item {
                padding: 12px 16px;
                gap: 12px;
            }

            .activity-icon {
                width: 36px;
                height: 36px;
                font-size: 0.9rem;
            }

            .activity-body h6 {
                font-size: 0.85rem;
            }

            .activity-body small {
                font-size: 0.72rem;
            }

            .status-badge {
                font-size: 0.65rem;
                padding: 2px 8px;
            }

            .footer {
                padding: 2.5rem 0 1.5rem;
            }

            .scroll-hint {
                display: none;
            }

            .btn-hero-primary,
            .btn-hero-ghost {
                padding: 0.8rem 1.4rem;
                font-size: 0.9rem;
                flex: 1;
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .hero-title {
                font-size: 1.7rem;
            }

            .hero-badge {
                font-size: 0.68rem;
                padding: 5px 12px;
            }

            .stat-card .stat-number {
                font-size: 1.5rem;
            }

            .section-title {
                font-size: 1.5rem;
            }

            .section-subtitle {
                font-size: 0.92rem;
            }
        }

        /* Layar sangat pendek — jaga agar konten hero tidak overflow */
        @media (max-width: 992px) and (max-height: 720px) {
            .hero-section {
                padding: calc(75px + env(safe-area-inset-top, 0)) 0 calc(75px + env(safe-area-inset-bottom, 0));
            }

            .hero-title {
                font-size: 1.65rem;
            }

            .hero-sub {
                font-size: 0.88rem;
                margin-bottom: 1.2rem;
            }

            .hero-meta {
                margin-bottom: 1rem;
            }

            .hero-badge {
                font-size: 0.65rem;
                padding: 4px 10px;
                margin-bottom: 14px;
            }
        }

        /* Reduced motion */
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
         NAVBAR DESKTOP — full width, brand di kiri
         ============================================================ -->
    <nav class="navbar navbar-expand-lg navbar-transparent" id="mainNav">
        <div class="navbar-inner">
            <a class="navbar-brand" href="index.php">
                <div class="brand-logo-box">NFC</div>
                <span class="logo-text">
                    Presensi NFC
                    <small>Mahasiswa</small>
                </span>
            </a>

            <div class="collapse navbar-collapse d-none d-lg-flex" id="navbarDesktop">
                <ul class="navbar-nav">
                    <?php foreach ($menus as $item):
                        if (in_array($item['href'], ['profil.php', 'keamanan.php'])) continue;
                    ?>
                        <li class="nav-item">
                            <a class="nav-link <?= $currentPage === $item['href'] ? 'active' : '' ?>"
                                href="<?= htmlspecialchars($item['href']) ?>">
                                <i class="fas <?= htmlspecialchars($item['icon']) ?>"></i>
                                <?= htmlspecialchars($item['label']) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="navbar-actions d-none d-lg-flex">
                <button class="theme-toggle" id="themeToggleDesktop" aria-label="Toggle tema">
                    <i class="fas fa-moon"></i> <span id="themeLabelDesktop">Gelap</span>
                </button>

                <div class="user-dropdown" id="userDropdown">
                    <button class="user-trigger" id="userTrigger" aria-label="Menu pengguna">
                        <div class="user-avatar"><?= htmlspecialchars($userInitial) ?></div>
                        <span class="user-name-nav"><?= htmlspecialchars($userName) ?></span>
                        <i class="fas fa-chevron-down" style="font-size: 0.7rem; opacity: 0.8;"></i>
                    </button>
                    <div class="user-menu" id="userMenu">
                        <a href="profil.php">
                            <i class="fas fa-user"></i> Profil Saya
                        </a>
                        <a href="keamanan.php">
                            <i class="fas fa-lock"></i> Keamanan Akun
                        </a>
                        <hr>
                        <a href="<?= htmlspecialchars($logoutUrl) ?>" class="logout-link">
                            <i class="fas fa-sign-out-alt"></i> Keluar
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- ============================================================
         MOBILE TOP BAR
         ============================================================ -->
    <div class="mobile-top-bar" id="mobileTopBar">
        <div class="brand-mini">
            <div class="brand-logo-box">NFC</div>
            <span class="brand-title">Presensi NFC</span>
        </div>
        <div class="mobile-top-actions">
            <button class="mobile-icon-btn" id="themeToggleMobile" aria-label="Toggle tema">
                <i class="fas fa-moon"></i>
            </button>
            <a href="profil.php" class="mobile-icon-btn user-avatar-mobile" aria-label="Profil">
                <?= htmlspecialchars($userInitial) ?>
            </a>
        </div>
    </div>

    <!-- ============================================================
         HERO — FULL SCREEN
         ============================================================ -->
    <section class="hero-section">
        <div class="hero-content">
            <div class="text-center text-lg-start">
                <span class="hero-badge">
                    <span class="pulse-dot"></span>
                    Selamat Datang · <?= date('d M Y') ?>
                </span>
                <h1 class="hero-title">
                    Halo, <span><?= htmlspecialchars($userName) ?></span>!
                </h1>
                <p class="hero-sub">
                    Pantau kehadiran, poin kedisiplinan, dan jadwal kuliah Anda hari ini
                    langsung dari dashboard mahasiswa Presensi NFC.
                </p>

                <div class="hero-meta justify-content-center justify-content-lg-start">
                    <span><i class="fas fa-id-badge"></i> NIM: <?= htmlspecialchars($userNim) ?></span>
                    <span><i class="fas fa-graduation-cap"></i> Kelas: <?= htmlspecialchars($userClass) ?></span>
                    <span><i class="fas fa-star"></i> Poin: <?= $currentPoints ?></span>
                </div>

                <div class="hero-actions justify-content-center justify-content-lg-start">
                    <a href="absensi.php" class="btn-hero-primary">
                        <i class="fas fa-id-card"></i> Absensi Sekarang
                    </a>
                    <a href="riwayat.php" class="btn-hero-ghost">
                        <i class="fas fa-history"></i> Lihat Riwayat
                    </a>
                </div>
            </div>
        </div>

        <div class="scroll-hint" id="scrollHint">
            Scroll untuk jelajahi
            <i class="fas fa-chevron-down"></i>
        </div>
    </section>

    <!-- ============================================================
         SECTION STATISTIK BULAN INI
         ============================================================ -->
    <section class="section-padding" id="statsSection">
        <div class="container">
            <div class="text-center fade-up">
                <h2 class="section-title">Statistik Bulan Ini</h2>
                <p class="section-subtitle">Ringkasan kehadiran dan poin Anda di bulan <?= date('F Y') ?></p>
            </div>
            <div class="row g-3 g-md-4">
                <div class="col-6 col-md-3 fade-up">
                    <div class="stat-card stat-hadir">
                        <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                        <div class="stat-number"><?= number_format($stats['hadir'], 0, ',', '.') ?></div>
                        <div class="stat-label">Hadir</div>
                    </div>
                </div>
                <div class="col-6 col-md-3 fade-up">
                    <div class="stat-card stat-terlambat">
                        <div class="stat-icon"><i class="fas fa-clock"></i></div>
                        <div class="stat-number"><?= number_format($stats['terlambat'], 0, ',', '.') ?></div>
                        <div class="stat-label">Terlambat</div>
                    </div>
                </div>
                <div class="col-6 col-md-3 fade-up">
                    <div class="stat-card stat-izin">
                        <div class="stat-icon"><i class="fas fa-envelope-open-text"></i></div>
                        <div class="stat-number"><?= number_format($stats['izin_sakit'], 0, ',', '.') ?></div>
                        <div class="stat-label">Izin / Sakit</div>
                    </div>
                </div>
                <div class="col-6 col-md-3 fade-up">
                    <div class="stat-card stat-poin">
                        <div class="stat-icon"><i class="fas fa-star"></i></div>
                        <div class="stat-number"><?= number_format($currentPoints, 0, ',', '.') ?></div>
                        <div class="stat-label">Poin Minggu Ini</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ============================================================
         SECTION JADWAL HARI INI
         ============================================================ -->
    <section class="section-padding" style="background: var(--bg-body);">
        <div class="container">
            <div class="d-flex justify-content-between align-items-end flex-wrap gap-2 mb-4 fade-up">
                <div>
                    <h2 class="section-title mb-1">Jadwal Hari Ini</h2>
                    <p class="section-subtitle mb-0">
                        <?= date('l, d F Y') ?> · <?= count($todaySchedule) ?> mata kuliah
                    </p>
                </div>
                <a href="absensi.php" class="btn-hero-primary" style="padding: 0.6rem 1.4rem; font-size: 0.88rem;">
                    <i class="fas fa-id-card"></i> Absensi
                </a>
            </div>

            <?php if (!empty($todaySchedule)): ?>
                <div class="schedule-list fade-up">
                    <?php foreach ($todaySchedule as $sch): ?>
                        <div class="schedule-item">
                            <div class="schedule-time">
                                <?= htmlspecialchars(substr($sch['start_time'], 0, 5)) ?>
                                <small>s.d. <?= htmlspecialchars(substr($sch['end_time'], 0, 5)) ?></small>
                            </div>
                            <div class="schedule-body">
                                <h6><?= htmlspecialchars($sch['course_name']) ?></h6>
                                <div class="meta">
                                    <span><i class="fas fa-user-tie"></i><?= htmlspecialchars($sch['lecturer_name'] ?: 'Dosen') ?></span>
                                    <span><i class="fas fa-users"></i><?= htmlspecialchars($sch['class_name']) ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-schedule fade-up">
                    <i class="fas fa-coffee"></i>
                    <p style="margin: 0; font-weight: 500;">Tidak ada jadwal kuliah hari ini.</p>
                    <small style="color: var(--text-secondary);">Selamat beristirahat!</small>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- ============================================================
         SECTION MENU
         ============================================================ -->
    <section class="section-padding" style="background: var(--bg-body);">
        <div class="container">
            <div class="text-center fade-up">
                <h2 class="section-title">Jelajahi Sistem</h2>
                <p class="section-subtitle">Akses cepat ke semua fitur mahasiswa</p>
            </div>
            <div class="row g-3 g-md-4">
                <?php foreach ($menus as $item):
                    if ($item['href'] === 'index.php') continue;
                ?>
                    <div class="col-6 col-md-4 col-lg-3 fade-up">
                        <a href="<?= htmlspecialchars($item['href']) ?>" class="menu-card">
                            <div class="menu-icon"><i class="fas <?= htmlspecialchars($item['icon']) ?>"></i></div>
                            <h6><?= htmlspecialchars($item['label']) ?></h6>
                            <p><?= htmlspecialchars($item['desc']) ?></p>
                            <span class="menu-arrow">Buka <i class="fas fa-arrow-right"></i></span>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ============================================================
         SECTION AKTIVITAS TERBARU
         ============================================================ -->
    <section class="section-padding" style="background: var(--bg-body);">
        <div class="container">
            <div class="d-flex justify-content-between align-items-end flex-wrap gap-2 mb-4 fade-up">
                <div>
                    <h2 class="section-title mb-1">Aktivitas Terbaru</h2>
                    <p class="section-subtitle mb-0">5 kehadiran terakhir Anda</p>
                </div>
                <a href="riwayat.php" class="menu-arrow" style="font-size: 0.9rem; font-weight: 600; color: var(--accent-strong); display: inline-flex; align-items: center; gap: 6px;">
                    Lihat semua <i class="fas fa-arrow-right"></i>
                </a>
            </div>

            <?php if (!empty($recent)): ?>
                <div class="activity-list fade-up">
                    <?php foreach ($recent as $r):
                        $label = $statusLabel[$r['status']] ?? ['label' => ucfirst($r['status']), 'class' => ''];
                    ?>
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                            <div class="activity-body">
                                <h6><?= htmlspecialchars($r['course_name'] ?: 'Mata Kuliah') ?></h6>
                                <small>
                                    <i class="far fa-calendar"></i>
                                    <?= date('d M Y', strtotime($r['attendance_date'])) ?>
                                    <?php if (!empty($r['check_in_at'])): ?>
                                        · <i class="far fa-clock"></i> <?= date('H:i', strtotime($r['check_in_at'])) ?>
                                    <?php endif; ?>
                                </small>
                            </div>
                            <span class="status-badge <?= $label['class'] ?>"><?= $label['label'] ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-activity fade-up">
                    <i class="fas fa-inbox"></i>
                    <p style="margin: 0; font-weight: 500;">Belum ada aktivitas absensi.</p>
                    <small style="color: var(--text-secondary);">Mulai absensi dengan kartu NFC Anda.</small>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- ============================================================
         FOOTER
         ============================================================ -->
    <footer class="footer">
        <div class="container">
            <div class="row g-4">
                <div class="col-md-5">
                    <div class="footer-brand">
                        Presensi NFC
                        <small>Kehadiran Mahasiswa</small>
                    </div>
                    <p class="mt-3" style="font-size:0.9rem; opacity:0.7;">
                        Sistem kehadiran mahasiswa berbasis teknologi NFC yang cepat, akurat, dan transparan
                        dengan sistem poin kedisiplinan terintegrasi.
                    </p>
                </div>
                <div class="col-6 col-md-3">
                    <h6>Menu Cepat</h6>
                    <ul>
                        <li><a href="index.php"><i class="fas fa-home me-2"></i>Dashboard</a></li>
                        <li><a href="absensi.php"><i class="fas fa-id-card me-2"></i>Absensi</a></li>
                        <li><a href="riwayat.php"><i class="fas fa-history me-2"></i>Riwayat</a></li>
                        <li><a href="poin.php"><i class="fas fa-star me-2"></i>Poin</a></li>
                    </ul>
                </div>
                <div class="col-6 col-md-4">
                    <h6>Akun</h6>
                    <ul>
                        <li><a href="profil.php"><i class="fas fa-user me-2"></i>Profil Saya</a></li>
                        <li><a href="keamanan.php"><i class="fas fa-lock me-2"></i>Keamanan Akun</a></li>
                        <li><a href="leaderboard.php"><i class="fas fa-trophy me-2"></i>Leaderboard</a></li>
                        <li><a href="<?= htmlspecialchars($logoutUrl) ?>"><i class="fas fa-sign-out-alt me-2"></i>Keluar</a></li>
                    </ul>
                </div>
            </div>
            <div class="copyright">
                &copy; <?= date('Y') ?> Presensi NFC · Sistem Kehadiran Mahasiswa. All rights reserved.
            </div>
        </div>
    </footer>

    <!-- ============================================================
         MOBILE BOTTOM NAVIGATION
         ============================================================ -->
    <nav class="mobile-bottom-nav" aria-label="Navigasi aplikasi">
        <?php
        $bottomNav = [
            ['href' => 'index.php',   'icon' => 'fa-home',    'label' => 'Beranda'],
            ['href' => 'absensi.php', 'icon' => 'fa-id-card', 'label' => 'Absensi'],
            ['href' => 'riwayat.php', 'icon' => 'fa-history', 'label' => 'Riwayat'],
            ['href' => 'poin.php',    'icon' => 'fa-star',    'label' => 'Poin'],
        ];
        foreach ($bottomNav as $nav):
            $isActive = $currentPage === $nav['href'];
        ?>
            <a href="<?= htmlspecialchars($nav['href']) ?>"
                class="mobile-nav-item <?= $isActive ? 'active' : '' ?>"
                aria-label="<?= htmlspecialchars($nav['label']) ?>">
                <i class="fas <?= htmlspecialchars($nav['icon']) ?>"></i>
                <span><?= htmlspecialchars($nav['label']) ?></span>
            </a>
        <?php endforeach; ?>

        <button type="button" class="mobile-nav-item" id="moreMenuBtn"
            aria-label="Menu lainnya" data-bs-toggle="offcanvas" data-bs-target="#offcanvasMore">
            <i class="fas fa-ellipsis-h"></i>
            <span>Lainnya</span>
        </button>
    </nav>

    <!-- ============================================================
         OFFCANVAS — MENU LAINNYA
         ============================================================ -->
    <div class="offcanvas offcanvas-bottom offcanvas-bottom-nav" tabindex="-1" id="offcanvasMore"
        aria-labelledby="offcanvasMoreLabel" style="height: auto; max-height: 80vh;">
        <div class="offcanvas-header">
            <div class="d-flex align-items-center gap-2">
                <div class="user-avatar" style="width: 40px; height: 40px; font-size: 1rem;">
                    <?= htmlspecialchars($userInitial) ?>
                </div>
                <div>
                    <h5 class="offcanvas-title mb-0" id="offcanvasMoreLabel">
                        <?= htmlspecialchars($userName) ?>
                    </h5>
                    <small style="color: var(--text-secondary); font-size: 0.78rem;">
                        <?= htmlspecialchars($userClass) ?> · <?= htmlspecialchars($userNim) ?>
                    </small>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Tutup"></button>
        </div>
        <div class="offcanvas-body">
            <?php foreach ($menus as $item):
                if (in_array($item['href'], ['index.php', 'absensi.php', 'riwayat.php', 'poin.php'])) continue;
                $isActive = $currentPage === $item['href'];
            ?>
                <a href="<?= htmlspecialchars($item['href']) ?>"
                    class="nav-list-item <?= $isActive ? 'active' : '' ?>">
                    <div class="nav-list-icon">
                        <i class="fas <?= htmlspecialchars($item['icon']) ?>"></i>
                    </div>
                    <div class="nav-list-body">
                        <?= htmlspecialchars($item['label']) ?>
                        <small><?= htmlspecialchars($item['desc']) ?></small>
                    </div>
                    <i class="fas fa-chevron-right" style="opacity: 0.4; font-size: 0.8rem;"></i>
                </a>
            <?php endforeach; ?>

            <hr style="border-color: var(--border-soft); margin: 12px 0;">

            <a href="<?= htmlspecialchars($logoutUrl) ?>" class="nav-list-item logout-list">
                <div class="nav-list-icon">
                    <i class="fas fa-sign-out-alt"></i>
                </div>
                <div class="nav-list-body">
                    Keluar Sistem
                    <small>Logout dari akun Anda</small>
                </div>
            </a>
        </div>
    </div>

    <!-- ============================================================
         BOOTSTRAP JS
         ============================================================ -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            'use strict';

            // --- FADE UP ---
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

            // --- SCROLL HINT ---
            const scrollHint = document.getElementById('scrollHint');
            if (scrollHint) {
                scrollHint.addEventListener('click', function(e) {
                    e.preventDefault();
                    const target = document.getElementById('statsSection');
                    if (target) target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                });
            }

            // --- STICKY NAVBAR ---
            const navbar = document.getElementById('mainNav');
            const mobileTopBar = document.getElementById('mobileTopBar');

            let ticking = false;

            function updateNavbar() {
                const scrollY = window.scrollY;
                if (navbar) navbar.classList.toggle('scrolled', scrollY > 30);
                if (mobileTopBar) mobileTopBar.classList.toggle('scrolled', scrollY > 30);
                ticking = false;
            }
            window.addEventListener('scroll', function() {
                if (!ticking) {
                    window.requestAnimationFrame(updateNavbar);
                    ticking = true;
                }
            });
            updateNavbar();

            // --- USER DROPDOWN ---
            const userTrigger = document.getElementById('userTrigger');
            const userMenu = document.getElementById('userMenu');
            if (userTrigger && userMenu) {
                userTrigger.addEventListener('click', function(e) {
                    e.stopPropagation();
                    userMenu.classList.toggle('show');
                });
                document.addEventListener('click', function(e) {
                    if (!userMenu.contains(e.target) && e.target !== userTrigger) {
                        userMenu.classList.remove('show');
                    }
                });
            }

            // --- DARK MODE ---
            const themeBtnDesktop = document.getElementById('themeToggleDesktop');
            const themeBtnMobile = document.getElementById('themeToggleMobile');
            const themeLabelDesk = document.getElementById('themeLabelDesktop');

            function updateThemeUI(mode) {
                const iconClass = mode === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
                const labelText = mode === 'dark' ? 'Terang' : 'Gelap';
                if (themeBtnDesktop) {
                    themeBtnDesktop.querySelector('i').className = iconClass;
                    if (themeLabelDesk) themeLabelDesk.textContent = labelText;
                }
                if (themeBtnMobile) {
                    themeBtnMobile.querySelector('i').className = iconClass;
                }
            }

            const storedTheme = localStorage.getItem('presensi_theme');
            if (storedTheme === 'dark') {
                document.body.classList.add('dark-mode');
                updateThemeUI('dark');
            } else if (storedTheme === 'light') {
                document.body.classList.remove('dark-mode');
                updateThemeUI('light');
            } else {
                if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                    document.body.classList.add('dark-mode');
                    updateThemeUI('dark');
                    localStorage.setItem('presensi_theme', 'dark');
                } else {
                    document.body.classList.remove('dark-mode');
                    updateThemeUI('light');
                    localStorage.setItem('presensi_theme', 'light');
                }
            }

            function toggleTheme() {
                const isDark = document.body.classList.toggle('dark-mode');
                const mode = isDark ? 'dark' : 'light';
                localStorage.setItem('presensi_theme', mode);
                updateThemeUI(mode);
            }

            if (themeBtnDesktop) themeBtnDesktop.addEventListener('click', toggleTheme);
            if (themeBtnMobile) themeBtnMobile.addEventListener('click', toggleTheme);
        });
    </script>

</body>

</html>
<?php
if (isset($db) && $db instanceof mysqli) {
    mysqli_close($db);
}
?>