<?php

/**
 * index2.php — Dashboard Admin
 * Presensi NFC | Sistem Kehadiran Mahasiswa
 * ------------------------------------------------------------
 * Hanya pengguna dengan session role 'admin' yang boleh masuk.
 * Bergantung pada: koneksi.php (variabel $conn MySQLi), login.php,
 * index.php, logout.php, background.jpg, dan halaman menu lainnya.
 *
 * Layout:
 *  - Desktop  : sidebar tetap (sticky) + konten di kanan.
 *  - Mobile   : pola aplikasi native — app bar sticky di atas,
 *               bottom tab bar (fixed) + bottom sheet menu,
 *               hero full-bleed, kartu statistik 2 kolom,
 *               safe-area aware (notch / home indicator).
 */

date_default_timezone_set('Asia/Jakarta');

// ============================================================
// 1. SESSION & OTORISASI (dijalankan sebelum output HTML apa pun)
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

function redirect_to(string $url): void
{
    header('Location: ' . $url);
    exit;
}

if (empty($_SESSION['user_id'])) {
    redirect_to('login.php');
}

$sessionRole = $_SESSION['role'] ?? '';

if ($sessionRole === 'mahasiswa') {
    redirect_to('index.php');
}

if ($sessionRole !== 'admin') {
    redirect_to('login.php');
}

$adminName = trim((string)($_SESSION['name'] ?? ''));
if ($adminName === '') {
    $adminName = 'Administrator';
}
$adminInitial = function_exists('mb_substr')
    ? mb_strtoupper(mb_substr($adminName, 0, 1, 'UTF-8'), 'UTF-8')
    : strtoupper(substr($adminName, 0, 1));

// ============================================================
// 2. KONEKSI DATABASE
// ============================================================
require_once __DIR__ . '/../koneksi.php';

$db = $conn ?? $koneksi ?? null;

if (!($db instanceof mysqli)) {
    http_response_code(500);
    error_log('[index2.php] Koneksi database tidak tersedia.');
    exit('Layanan sementara tidak tersedia. Silakan hubungi administrator sistem.');
}

// ============================================================
// 3. HELPER
// ============================================================
function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function fetch_total(mysqli $db, string $sql, string $types = '', array $params = []): int
{
    try {
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            error_log('[index2.php] Prepare gagal: ' . $db->error);
            return 0;
        }
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        if (!$stmt->execute()) {
            error_log('[index2.php] Execute gagal: ' . $stmt->error);
            $stmt->close();
            return 0;
        }
        $total = 0;
        $stmt->bind_result($total);
        $stmt->fetch();
        $stmt->close();
        return (int)$total;
    } catch (Throwable $ex) {
        error_log('[index2.php] ' . $ex->getMessage());
        return 0;
    }
}

function format_waktu(?string $timestamp): string
{
    if (!$timestamp) {
        return '-';
    }
    $t = strtotime($timestamp);
    if ($t === false) {
        return '-';
    }
    $bulan = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    return date('j', $t) . ' ' . $bulan[(int)date('n', $t) - 1] . ' ' . date('Y, H:i', $t);
}

// ============================================================
// 4. STATISTIK DASHBOARD
// ============================================================
$today = date('Y-m-d');

$stats = [
    'mahasiswa' => fetch_total(
        $db,
        "SELECT COUNT(id) FROM users WHERE role = 'mahasiswa' AND is_active = 1"
    ),
    'hadir' => fetch_total(
        $db,
        "SELECT COUNT(DISTINCT user_id) FROM attendances
         WHERE attendance_date = ? AND status IN ('hadir', 'terlambat')",
        's',
        [$today]
    ),
    'jadwal' => fetch_total(
        $db,
        "SELECT COUNT(id) FROM schedules WHERE is_active = 1"
    ),
    'izin' => fetch_total(
        $db,
        "SELECT COUNT(id) FROM leave_requests WHERE status = 'menunggu'"
    ),
];

$statCards = [
    ['value' => $stats['mahasiswa'], 'label' => 'Mahasiswa Aktif', 'icon' => 'i-users',    'tone' => 'blue'],
    ['value' => $stats['hadir'],     'label' => 'Hadir Hari Ini',  'icon' => 'i-check',    'tone' => 'green'],
    ['value' => $stats['jadwal'],    'label' => 'Jadwal Aktif',    'icon' => 'i-calendar', 'tone' => 'amber'],
    ['value' => $stats['izin'],      'label' => 'Izin Menunggu',   'icon' => 'i-mail',     'tone' => 'red'],
];

// ============================================================
// 5. AKTIVITAS TERBARU (maks. 5)
// ============================================================
$activities = [];
try {
    $sqlLog = "SELECT al.action, al.description, al.created_at, u.name AS user_name
               FROM activity_logs al
               LEFT JOIN users u ON u.id = al.user_id
               ORDER BY al.created_at DESC, al.id DESC
               LIMIT 5";
    if ($result = $db->query($sqlLog)) {
        while ($row = $result->fetch_assoc()) {
            $activities[] = $row;
        }
        $result->free();
    } else {
        error_log('[index2.php] Query log gagal: ' . $db->error);
    }
} catch (Throwable $ex) {
    error_log('[index2.php] ' . $ex->getMessage());
}

// ============================================================
// 6. MENU SIDEBAR
// ============================================================
$menus = [
    ['link' => 'index2.php',      'icon' => 'i-grid',      'label' => 'Dashboard'],
    ['link' => 'mahasiswa.php',   'icon' => 'i-users',     'label' => 'Data Mahasiswa'],
    ['link' => 'admin.php',       'icon' => 'i-shield',    'label' => 'Data Admin'],
    ['link' => 'jadwal.php',      'icon' => 'i-calendar',  'label' => 'Jadwal Kuliah'],
    ['link' => 'absensi.php',     'icon' => 'i-clipboard', 'label' => 'Data Absensi'],
    ['link' => 'poin.php',        'icon' => 'i-star',      'label' => 'Poin Disiplin'],
    ['link' => 'izin.php',        'icon' => 'i-mail',      'label' => 'Izin & Sakit'],
    ['link' => 'laporan.php',     'icon' => 'i-chart',     'label' => 'Laporan'],
    ['link' => 'leaderboard.php', 'icon' => 'i-trophy',    'label' => 'Leaderboard'],
    ['link' => 'pengaturan.php',  'icon' => 'i-sliders',   'label' => 'Pengaturan'],
    ['link' => 'log.php',         'icon' => 'i-history',   'label' => 'Log Aktivitas'],
];
$currentPage = 'index2.php';
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="color-scheme" content="dark">
    <meta name="theme-color" content="#0B1220">
    <meta name="robots" content="noindex, nofollow">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="NFC Admin">
    <title>Dashboard Admin — Presensi NFC</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        /* ============================================================
           DESIGN TOKENS
           ============================================================ */
        :root {
            --navy: #0B1220;
            --sidebar: #0F1B2D;
            --panel: #12233B;
            --panel-2: #172B45;
            --nav-active: #1D3A5F;
            --cyan: #42E8D5;
            --blue: #38BDF8;
            --text: #F8FAFC;
            --text-2: #A8B7CC;
            --green: #22C55E;
            --amber: #FBBF24;
            --red: #F87171;
            --border: rgba(148, 163, 184, 0.18);

            --panel-bg: rgba(18, 35, 59, 0.9);

            --sidebar-w: 260px;
            --gap: 24px;
            --pad: 28px;
            --card-pad: 22px;
            --radius: 16px;
            --radius-sm: 10px;
            --shadow: 0 8px 24px rgba(2, 8, 20, 0.35);
            --speed: 0.2s;

            --tabbar-h: 62px;
            --safe-t: env(safe-area-inset-top, 0px);
            --safe-b: env(safe-area-inset-bottom, 0px);
            --safe-l: env(safe-area-inset-left, 0px);
            --safe-r: env(safe-area-inset-right, 0px);

            --font: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, Arial, sans-serif;
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        html {
            -webkit-text-size-adjust: 100%;
            text-size-adjust: 100%;
        }

        body {
            margin: 0;
            min-height: 100vh;
            min-height: 100dvh;
            background-color: var(--navy);
            color: var(--text);
            font-family: var(--font);
            font-size: 15px;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
            -webkit-tap-highlight-color: transparent;
        }

        body.nav-open {
            overflow: hidden;
        }

        h1,
        h2,
        h3,
        p {
            margin: 0;
        }

        ul {
            margin: 0;
            padding: 0;
            list-style: none;
        }

        a {
            color: inherit;
            touch-action: manipulation;
        }

        button {
            font-family: inherit;
            touch-action: manipulation;
        }

        :focus-visible {
            outline: 2px solid var(--cyan);
            outline-offset: 2px;
        }

        .icon {
            width: 1.25em;
            height: 1.25em;
            flex-shrink: 0;
            fill: none;
            stroke: currentColor;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        /* ============================================================
           LAYOUT UTAMA
           ============================================================ */
        .app {
            display: grid;
            grid-template-columns: var(--sidebar-w) minmax(0, 1fr);
            min-height: 100vh;
            min-height: 100dvh;
            background-color: var(--navy);
        }

        /* ============================================================
           SIDEBAR (desktop)
           ============================================================ */
        .sidebar {
            position: sticky;
            top: 0;
            align-self: start;
            height: 100vh;
            height: 100dvh;
            display: flex;
            flex-direction: column;
            background: var(--sidebar);
            border-right: 1px solid var(--border);
            z-index: 40;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 22px 20px;
            border-bottom: 1px solid var(--border);
        }

        .brand-mark {
            display: grid;
            place-items: center;
            width: 44px;
            height: 44px;
            flex-shrink: 0;
            border: 1.5px solid var(--cyan);
            border-radius: 12px;
            color: var(--cyan);
            background: rgba(66, 232, 213, 0.08);
        }

        .brand-mark .icon {
            width: 24px;
            height: 24px;
        }

        .brand-text {
            min-width: 0;
        }

        .brand-name {
            font-size: 17px;
            font-weight: 800;
            letter-spacing: 0.04em;
            line-height: 1.2;
        }

        .brand-tag {
            margin-top: 2px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.1em;
            color: var(--cyan);
        }

        .nav {
            flex: 1;
            min-height: 0;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
            padding: 16px 12px;
        }

        .nav::-webkit-scrollbar {
            width: 4px;
        }

        .nav::-webkit-scrollbar-thumb {
            background: rgba(148, 163, 184, 0.25);
            border-radius: 4px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            min-height: 44px;
            padding: 10px 14px;
            margin-bottom: 4px;
            border-radius: var(--radius-sm);
            color: var(--text-2);
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            transition: background-color var(--speed), color var(--speed);
        }

        .nav-link:hover {
            background: var(--panel-2);
            color: var(--text);
        }

        .nav-link.is-active {
            background: var(--nav-active);
            color: var(--text);
            box-shadow: inset 3px 0 0 var(--cyan);
        }

        .nav-link.is-active .icon,
        .nav-link:hover .icon {
            color: var(--cyan);
        }

        .sidebar-foot {
            padding: 16px 12px 20px;
            border-top: 1px solid var(--border);
        }

        .btn-logout {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            min-height: 44px;
            padding: 10px 14px;
            border: 1px solid rgba(248, 113, 113, 0.35);
            border-radius: var(--radius-sm);
            background: rgba(248, 113, 113, 0.1);
            color: var(--red);
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: background-color var(--speed), color var(--speed);
        }

        .btn-logout:hover {
            background: var(--red);
            color: var(--navy);
        }

        .backdrop {
            position: fixed;
            inset: 0;
            z-index: 35;
            background: rgba(2, 8, 20, 0.65);
            -webkit-backdrop-filter: blur(2px);
            backdrop-filter: blur(2px);
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            transition: opacity var(--speed), visibility var(--speed);
        }

        /* ============================================================
           AREA UTAMA
           ============================================================ */
        .main {
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: var(--gap);
            padding: var(--pad);
            max-width: 1440px;
            width: 100%;
            margin-inline: auto;
        }

        .page {
            display: flex;
            flex-direction: column;
            gap: var(--gap);
            min-width: 0;
        }

        /* ============================================================
           HERO BANNER
           Tinggi mengikuti rasio asli foto — gambar tampil utuh.
           ============================================================ */
        .hero {
            width: 100%;
            border-radius: var(--radius);
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            background-color: var(--navy);
            overflow: hidden;
            line-height: 0;
            /* hilangkan gap inline di bawah <img> */
        }

        .hero-img {
            display: block;
            width: 100%;
            height: auto;
            /* tinggi mengikuti rasio asli gambar */
            object-fit: contain;
            max-width: 100%;
        }

        .panel {
            background: var(--panel-bg);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
        }

        /* --- Header / App bar --- */
        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
            padding: 16px 24px;
        }

        .topbar-left {
            display: flex;
            align-items: center;
            gap: 14px;
            min-width: 0;
            flex: 1 1 280px;
        }

        .menu-toggle {
            display: none;
            place-items: center;
            width: 44px;
            height: 44px;
            flex-shrink: 0;
            padding: 0;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            background: var(--panel-2);
            color: var(--text);
            cursor: pointer;
            transition: background-color var(--speed), transform var(--speed);
        }

        .menu-toggle:active {
            transform: scale(0.94);
            background: var(--nav-active);
        }

        .menu-toggle .icon {
            width: 22px;
            height: 22px;
        }

        .topbar-title {
            min-width: 0;
        }

        .topbar-title h2 {
            font-size: 20px;
            font-weight: 700;
            line-height: 1.3;
        }

        .topbar-title p {
            margin-top: 2px;
            font-size: 13px;
            color: var(--text-2);
        }

        .profile {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
            max-width: 100%;
        }

        .profile-info {
            min-width: 0;
            text-align: right;
        }

        .profile-name {
            display: block;
            max-width: 220px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-size: 14px;
            font-weight: 600;
        }

        .profile-role {
            font-size: 12px;
            color: var(--cyan);
        }

        .avatar {
            display: grid;
            place-items: center;
            width: 44px;
            height: 44px;
            flex-shrink: 0;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--cyan), var(--blue));
            color: var(--navy);
            font-size: 18px;
            font-weight: 800;
        }

        /* --- Welcome --- */
        .welcome {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            align-items: center;
            gap: 24px;
            padding: 26px 28px;
            background:
                linear-gradient(135deg, rgba(66, 232, 213, 0.1), rgba(56, 189, 248, 0.04)),
                var(--panel-bg);
        }

        .welcome h1 {
            font-size: 24px;
            font-weight: 700;
            line-height: 1.3;
            overflow-wrap: anywhere;
        }

        .welcome p {
            margin-top: 8px;
            max-width: 62ch;
            color: var(--text-2);
            font-size: 14px;
        }

        .welcome-deco {
            display: grid;
            place-items: center;
            width: 72px;
            height: 72px;
            border: 1px solid rgba(66, 232, 213, 0.3);
            border-radius: 20px;
            background: rgba(66, 232, 213, 0.08);
            color: var(--cyan);
        }

        .welcome-deco .icon {
            width: 34px;
            height: 34px;
        }

        /* --- Statistik --- */
        .stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: var(--gap);
        }

        .stat {
            --tone: var(--blue);
            --tone-bg: rgba(56, 189, 248, 0.14);
            display: flex;
            align-items: center;
            gap: 16px;
            min-width: 0;
            padding: var(--card-pad);
            transition: border-color var(--speed), transform var(--speed);
        }

        .stat:hover {
            border-color: var(--tone);
        }

        .stat--green {
            --tone: var(--green);
            --tone-bg: rgba(34, 197, 94, 0.14);
        }

        .stat--amber {
            --tone: var(--amber);
            --tone-bg: rgba(251, 191, 36, 0.14);
        }

        .stat--red {
            --tone: var(--red);
            --tone-bg: rgba(248, 113, 113, 0.14);
        }

        .stat-icon {
            display: grid;
            place-items: center;
            width: 52px;
            height: 52px;
            flex-shrink: 0;
            border-radius: 14px;
            background: var(--tone-bg);
            color: var(--tone);
        }

        .stat-icon .icon {
            width: 24px;
            height: 24px;
        }

        .stat-body {
            min-width: 0;
        }

        .stat-value {
            font-size: clamp(1.5rem, 1.2rem + 1vw, 2rem);
            font-weight: 800;
            line-height: 1.1;
            overflow-wrap: anywhere;
            font-variant-numeric: tabular-nums;
        }

        .stat-label {
            margin-top: 6px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: var(--text-2);
        }

        /* --- Aksi cepat (khusus mobile) --- */
        .quick {
            display: none;
            padding: var(--card-pad);
        }

        .quick-head {
            margin-bottom: 14px;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: var(--text-2);
        }

        .quick-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 10px;
        }

        .quick-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 84px;
            padding: 12px 6px;
            border: 1px solid var(--border);
            border-radius: 14px;
            background: var(--panel-2);
            color: var(--text);
            font-size: 11.5px;
            font-weight: 600;
            text-align: center;
            text-decoration: none;
            transition: transform var(--speed), border-color var(--speed), background-color var(--speed);
        }

        .quick-item:active {
            transform: scale(0.96);
            background: var(--nav-active);
            border-color: var(--cyan);
        }

        .quick-item .icon {
            width: 22px;
            height: 22px;
            color: var(--cyan);
        }

        /* --- Aktivitas --- */
        .activity {
            padding: var(--card-pad) 24px;
        }

        .activity-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 8px 16px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--border);
        }

        .activity-head h3 {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 16px;
            font-weight: 700;
        }

        .activity-head h3 .icon {
            color: var(--cyan);
        }

        .link-all {
            display: inline-flex;
            align-items: center;
            min-height: 36px;
            font-size: 13px;
            font-weight: 600;
            color: var(--blue);
            text-decoration: none;
        }

        .link-all:hover {
            text-decoration: underline;
        }

        .activity-item {
            display: flex;
            gap: 14px;
            padding: 16px 0;
            border-bottom: 1px solid var(--border);
        }

        .activity-item:last-child {
            border-bottom: 0;
            padding-bottom: 0;
        }

        .activity-icon {
            display: grid;
            place-items: center;
            width: 36px;
            height: 36px;
            flex-shrink: 0;
            border-radius: 50%;
            background: rgba(56, 189, 248, 0.14);
            color: var(--blue);
        }

        .activity-icon .icon {
            width: 18px;
            height: 18px;
        }

        .activity-body {
            min-width: 0;
            flex: 1;
        }

        .activity-title {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 4px 10px;
            font-size: 14px;
        }

        .activity-title strong {
            font-weight: 600;
            overflow-wrap: anywhere;
        }

        .badge {
            padding: 2px 10px;
            border: 1px solid rgba(66, 232, 213, 0.3);
            border-radius: 999px;
            background: rgba(66, 232, 213, 0.1);
            color: var(--cyan);
            font-size: 12px;
            font-weight: 600;
            overflow-wrap: anywhere;
        }

        .activity-desc {
            margin-top: 4px;
            color: var(--text-2);
            font-size: 14px;
            overflow-wrap: anywhere;
        }

        .activity-time {
            margin-top: 6px;
            font-size: 12px;
            color: var(--text-2);
        }

        .empty {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            padding: 36px 16px 20px;
            color: var(--text-2);
            font-size: 14px;
            text-align: center;
        }

        .empty .icon {
            width: 36px;
            height: 36px;
            opacity: 0.7;
        }

        /* --- Footer --- */
        .footer {
            padding: 4px 4px 0;
            color: var(--text-2);
            font-size: 12px;
            text-align: center;
        }

        /* ============================================================
           BOTTOM TAB BAR (khusus mobile)
           ============================================================ */
        .tabbar {
            display: none;
            position: fixed;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 30;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            padding: 0 max(4px, var(--safe-l)) var(--safe-b) max(4px, var(--safe-r));
            background: rgba(15, 27, 45, 0.96);
            -webkit-backdrop-filter: blur(14px);
            backdrop-filter: blur(14px);
            border-top: 1px solid var(--border);
            box-shadow: 0 -6px 24px rgba(2, 8, 20, 0.45);
        }

        .tabbar-item {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 3px;
            min-height: var(--tabbar-h);
            padding: 9px 2px 8px;
            border: 0;
            background: transparent;
            color: var(--text-2);
            font-size: 10.5px;
            font-weight: 600;
            letter-spacing: 0.01em;
            text-align: center;
            text-decoration: none;
            cursor: pointer;
            transition: color var(--speed), transform var(--speed);
        }

        .tabbar-item .icon {
            width: 22px;
            height: 22px;
        }

        .tabbar-item:active {
            transform: scale(0.92);
        }

        .tabbar-item.is-active {
            color: var(--cyan);
        }

        .tabbar-item::before {
            content: "";
            position: absolute;
            top: 0;
            left: 50%;
            width: 26px;
            height: 3px;
            border-radius: 0 0 3px 3px;
            background: var(--cyan);
            transform: translateX(-50%) scaleX(0);
            transform-origin: center;
            transition: transform var(--speed) ease;
        }

        .tabbar-item.is-active::before {
            transform: translateX(-50%) scaleX(1);
        }

        /* ============================================================
           RESPONSIVE — TABLET & MOBILE
           ============================================================ */
        @media (max-width: 1280px) {
            .stats {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 1024px) {
            :root {
                --pad: 18px;
                --gap: 16px;
                --card-pad: 18px;
            }

            .app {
                grid-template-columns: minmax(0, 1fr);
            }

            /* --- Sidebar jadi bottom-sheet drawer --- */
            .sidebar {
                position: fixed;
                top: 0;
                bottom: 0;
                left: 0;
                width: min(var(--sidebar-w), 84vw);
                height: 100vh;
                height: 100dvh;
                padding-top: var(--safe-t);
                padding-bottom: var(--safe-b);
                border-right: 1px solid var(--border);
                border-radius: 0 var(--radius) var(--radius) 0;
                box-shadow: 24px 0 60px rgba(2, 8, 20, 0.55);
                transform: translateX(-102%);
                visibility: hidden;
                transition: transform 0.28s cubic-bezier(0.32, 0.72, 0, 1),
                    visibility 0s linear 0.28s;
            }

            body.nav-open .sidebar {
                transform: translateX(0);
                visibility: visible;
                transition: transform 0.28s cubic-bezier(0.32, 0.72, 0, 1), visibility 0s;
            }

            body.nav-open .backdrop {
                opacity: 1;
                visibility: visible;
                pointer-events: auto;
            }

            .menu-toggle {
                display: grid;
            }

            /* --- Layout konten full-bleed --- */
            .main {
                padding: 0;
                gap: 0;
                max-width: none;
            }

            .page {
                padding: 14px var(--pad) calc(var(--tabbar-h) + var(--safe-b) + 26px);
                gap: 14px;
            }

            /* --- App bar sticky --- */
            .topbar {
                position: sticky;
                top: 0;
                z-index: 25;
                flex-wrap: nowrap;
                gap: 12px;
                padding: calc(10px + var(--safe-t)) 14px 10px;
                border-radius: 0;
                border-left: 0;
                border-right: 0;
                border-top: 0;
                background: rgba(11, 18, 32, 0.88);
                -webkit-backdrop-filter: blur(16px) saturate(140%);
                backdrop-filter: blur(16px) saturate(140%);
                box-shadow: 0 6px 20px rgba(2, 8, 20, 0.4);
            }

            .topbar-left {
                flex: 1 1 auto;
                gap: 10px;
            }

            .topbar-title h2 {
                font-size: 16px;
                line-height: 1.2;
            }

            .topbar-title p {
                display: none;
            }

            .profile {
                gap: 8px;
                flex-shrink: 0;
            }

            .profile-info {
                display: none;
            }

            .avatar {
                width: 38px;
                height: 38px;
                font-size: 16px;
            }

            .menu-toggle {
                width: 38px;
                height: 38px;
                border-radius: 12px;
            }

            .menu-toggle .icon {
                width: 20px;
                height: 20px;
            }

            /* --- Hero full-bleed --- */
            .hero {
                margin-inline: calc(var(--pad) * -1);
                border-radius: 0;
                border-left: 0;
                border-right: 0;
                border-top: 0;
                box-shadow: none;
            }

            /* --- Tab bar tampil --- */
            .tabbar {
                display: grid;
            }

            /* --- Welcome ringkas --- */
            .welcome {
                grid-template-columns: minmax(0, 1fr);
                gap: 0;
                padding: 18px;
            }

            .welcome h1 {
                font-size: 19px;
            }

            .welcome p {
                margin-top: 6px;
                font-size: 13px;
            }

            .welcome-deco {
                display: none;
            }

            /* --- Aksi cepat tampil --- */
            .quick {
                display: block;
            }

            .activity {
                padding: var(--card-pad) 16px;
            }

            /* --- Efek tekan untuk kartu --- */
            .stat:active,
            .activity-item:active {
                transform: scale(0.99);
            }
        }

        @media (max-width: 640px) {
            :root {
                --pad: 14px;
                --gap: 12px;
                --card-pad: 14px;
            }

            .stats {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 10px;
            }

            .stat {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
                padding: 14px;
                border-radius: 14px;
            }

            .stat-icon {
                width: 40px;
                height: 40px;
                border-radius: 12px;
            }

            .stat-icon .icon {
                width: 19px;
                height: 19px;
            }

            .stat-value {
                font-size: 22px;
                line-height: 1.05;
            }

            .stat-label {
                margin-top: 4px;
                font-size: 10.5px;
                letter-spacing: 0.04em;
            }

            .quick-grid {
                gap: 8px;
            }

            .quick-item {
                min-height: 76px;
                font-size: 10.5px;
                border-radius: 12px;
            }

            .quick-item .icon {
                width: 20px;
                height: 20px;
            }

            .activity {
                padding: var(--card-pad) 14px;
                border-radius: 14px;
            }

            .activity-item {
                padding: 13px 0;
                gap: 11px;
            }

            .activity-icon {
                width: 32px;
                height: 32px;
            }

            .activity-icon .icon {
                width: 16px;
                height: 16px;
            }

            .activity-title {
                font-size: 13.5px;
            }

            .activity-desc {
                font-size: 13px;
            }

            .footer {
                font-size: 11px;
                padding-bottom: 4px;
            }

            .tabbar {
                --tabbar-h: 58px;
            }

            .tabbar-item {
                font-size: 10px;
            }

            .tabbar-item .icon {
                width: 21px;
                height: 21px;
            }
        }

        @media (max-width: 380px) {
            .stat-value {
                font-size: 19px;
            }

            .stat-label {
                font-size: 9.5px;
            }

            .topbar-title h2 {
                font-size: 15px;
            }

            .quick-item {
                font-size: 9.5px;
                min-height: 70px;
            }
        }

        @media (prefers-reduced-motion: reduce) {

            *,
            *::before,
            *::after {
                transition: none !important;
                animation: none !important;
            }
        }
    </style>
</head>

<body>

    <!-- Sprite ikon SVG -->
    <svg xmlns="http://www.w3.org/2000/svg" width="0" height="0" aria-hidden="true" focusable="false" style="display:none">
        <symbol id="i-nfc" viewBox="0 0 24 24">
            <path d="M8 8.5a5 5 0 0 1 0 7" />
            <path d="M12 6a8.5 8.5 0 0 1 0 12" />
            <path d="M16 3.5a12 12 0 0 1 0 17" />
            <circle cx="4.5" cy="12" r="0.8" />
        </symbol>
        <symbol id="i-grid" viewBox="0 0 24 24">
            <rect x="3" y="3" width="7" height="7" rx="1" />
            <rect x="14" y="3" width="7" height="7" rx="1" />
            <rect x="14" y="14" width="7" height="7" rx="1" />
            <rect x="3" y="14" width="7" height="7" rx="1" />
        </symbol>
        <symbol id="i-users" viewBox="0 0 24 24">
            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
            <circle cx="9" cy="7" r="4" />
            <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
            <path d="M16 3.13a4 4 0 0 1 0 7.75" />
        </symbol>
        <symbol id="i-shield" viewBox="0 0 24 24">
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
        </symbol>
        <symbol id="i-calendar" viewBox="0 0 24 24">
            <rect x="3" y="4" width="18" height="18" rx="2" />
            <path d="M16 2v4M8 2v4M3 10h18" />
        </symbol>
        <symbol id="i-clipboard" viewBox="0 0 24 24">
            <path d="M9 11l3 3L22 4" />
            <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11" />
        </symbol>
        <symbol id="i-star" viewBox="0 0 24 24">
            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />
        </symbol>
        <symbol id="i-mail" viewBox="0 0 24 24">
            <path d="M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z" />
            <polyline points="22,6 12,13 2,6" />
        </symbol>
        <symbol id="i-chart" viewBox="0 0 24 24">
            <path d="M21.21 15.89A10 10 0 1 1 8 2.83" />
            <path d="M22 12A10 10 0 0 0 12 2v10z" />
        </symbol>
        <symbol id="i-trophy" viewBox="0 0 24 24">
            <path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6" />
            <path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18" />
            <path d="M4 22h16" />
            <path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22" />
            <path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22" />
            <path d="M18 2H6v7a6 6 0 0 0 12 0V2z" />
        </symbol>
        <symbol id="i-sliders" viewBox="0 0 24 24">
            <path d="M4 21v-7M4 10V3M12 21v-9M12 8V3M20 21v-5M20 12V3M1 14h6M9 8h6M17 16h6" />
        </symbol>
        <symbol id="i-history" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="10" />
            <polyline points="12 6 12 12 16 14" />
        </symbol>
        <symbol id="i-check" viewBox="0 0 24 24">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
            <polyline points="22 4 12 14.01 9 11.01" />
        </symbol>
        <symbol id="i-logout" viewBox="0 0 24 24">
            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
            <polyline points="16 17 21 12 16 7" />
            <line x1="21" y1="12" x2="9" y2="12" />
        </symbol>
        <symbol id="i-menu" viewBox="0 0 24 24">
            <line x1="3" y1="6" x2="21" y2="6" />
            <line x1="3" y1="12" x2="21" y2="12" />
            <line x1="3" y1="18" x2="21" y2="18" />
        </symbol>
        <symbol id="i-info" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="10" />
            <line x1="12" y1="16" x2="12" y2="12" />
            <line x1="12" y1="8" x2="12.01" y2="8" />
        </symbol>
        <symbol id="i-inbox" viewBox="0 0 24 24">
            <polyline points="22 12 16 12 14 15 10 15 8 12 2 12" />
            <path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z" />
        </symbol>
        <symbol id="i-server" viewBox="0 0 24 24">
            <rect x="2" y="2" width="20" height="8" rx="2" />
            <rect x="2" y="14" width="20" height="8" rx="2" />
            <line x1="6" y1="6" x2="6.01" y2="6" />
            <line x1="6" y1="18" x2="6.01" y2="18" />
        </symbol>
    </svg>

    <div class="app">
        <!-- ======================= SIDEBAR / DRAWER ======================= -->
        <aside class="sidebar" id="sidebar" aria-label="Navigasi utama">
            <div class="brand">
                <div class="brand-mark" aria-hidden="true">
                    <svg class="icon">
                        <use href="#i-nfc" />
                    </svg>
                </div>
                <div class="brand-text">
                    <div class="brand-name">NFC - ADMIN</div>
                    <div class="brand-tag">HALAMAN ADMIN</div>
                </div>
            </div>

            <nav class="nav">
                <ul>
                    <?php foreach ($menus as $menu): ?>
                        <?php $isActive = ($menu['link'] === $currentPage); ?>
                        <li>
                            <a class="nav-link<?= $isActive ? ' is-active' : '' ?>"
                                href="<?= e($menu['link']) ?>"
                                <?= $isActive ? 'aria-current="page"' : '' ?>>
                                <svg class="icon" aria-hidden="true">
                                    <use href="#<?= e($menu['icon']) ?>" />
                                </svg>
                                <span><?= e($menu['label']) ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </nav>

            <div class="sidebar-foot">
                <a class="btn-logout" href="logout.php">
                    <svg class="icon" aria-hidden="true">
                        <use href="#i-logout" />
                    </svg>
                    <span>Keluar Sistem</span>
                </a>
            </div>
        </aside>

        <div class="backdrop" id="backdrop"></div>

        <!-- ======================= KONTEN UTAMA ======================= -->
        <div class="main">

            <!-- App bar (sticky di mobile, panel biasa di desktop) -->
            <header class="topbar panel">
                <div class="topbar-left">
                    <button type="button" class="menu-toggle" id="menuToggle"
                        aria-label="Buka menu navigasi" aria-controls="sidebar" aria-expanded="false">
                        <svg class="icon" aria-hidden="true">
                            <use href="#i-menu" />
                        </svg>
                    </button>
                    <div class="topbar-title">
                        <h2>Dashboard Admin</h2>
                        <p>Selamat datang kembali di pusat kendali Presensi NFC.</p>
                    </div>
                </div>

                <div class="profile">
                    <div class="profile-info">
                        <span class="profile-name" title="<?= e($adminName) ?>"><?= e($adminName) ?></span>
                        <span class="profile-role">Administrator</span>
                    </div>
                    <div class="avatar" aria-hidden="true"><?= e($adminInitial) ?></div>
                </div>
            </header>

            <div class="page">

                <!-- Hero banner: tinggi mengikuti rasio asli background.jpg -->
                <section class="hero" aria-hidden="true">
                    <img class="hero-img" src="background.jpg" alt="" width="1600" height="600"
                        loading="eager" decoding="async">
                </section>

                <!-- Welcome -->
                <section class="welcome panel" aria-labelledby="welcomeTitle">
                    <div>
                        <h1 id="welcomeTitle">Halo, <?= e($adminName) ?>! 👋</h1>
                        <p>Sistem Presensi NFC membantu pengelolaan kehadiran dan aktivitas akademik mahasiswa secara terstruktur.</p>
                    </div>
                    <div class="welcome-deco" aria-hidden="true">
                        <svg class="icon">
                            <use href="#i-server" />
                        </svg>
                    </div>
                </section>

                <!-- Statistik -->
                <section class="stats" aria-label="Ringkasan statistik">
                    <?php foreach ($statCards as $card): ?>
                        <article class="stat stat--<?= e($card['tone']) ?> panel">
                            <div class="stat-icon" aria-hidden="true">
                                <svg class="icon">
                                    <use href="#<?= e($card['icon']) ?>" />
                                </svg>
                            </div>
                            <div class="stat-body">
                                <div class="stat-value"><?= number_format((int)$card['value'], 0, ',', '.') ?></div>
                                <div class="stat-label"><?= e($card['label']) ?></div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </section>

                <!-- Aksi cepat (mobile only) -->
                <section class="quick panel" aria-label="Aksi cepat">
                    <div class="quick-head">Aksi Cepat</div>
                    <div class="quick-grid">
                        <a class="quick-item" href="absensi.php">
                            <svg class="icon" aria-hidden="true">
                                <use href="#i-clipboard" />
                            </svg>
                            <span>Absensi</span>
                        </a>
                        <a class="quick-item" href="jadwal.php">
                            <svg class="icon" aria-hidden="true">
                                <use href="#i-calendar" />
                            </svg>
                            <span>Jadwal</span>
                        </a>
                        <a class="quick-item" href="izin.php">
                            <svg class="icon" aria-hidden="true">
                                <use href="#i-mail" />
                            </svg>
                            <span>Izin</span>
                        </a>
                        <a class="quick-item" href="laporan.php">
                            <svg class="icon" aria-hidden="true">
                                <use href="#i-chart" />
                            </svg>
                            <span>Laporan</span>
                        </a>
                    </div>
                </section>

                <!-- Aktivitas -->
                <section class="activity panel" aria-labelledby="activityTitle">
                    <div class="activity-head">
                        <h3 id="activityTitle">
                            <svg class="icon" aria-hidden="true">
                                <use href="#i-history" />
                            </svg>
                            Aktivitas Terbaru
                        </h3>
                        <a class="link-all" href="log.php">Lihat semua</a>
                    </div>

                    <?php if (!empty($activities)): ?>
                        <ul>
                            <?php foreach ($activities as $act): ?>
                                <li class="activity-item">
                                    <div class="activity-icon" aria-hidden="true">
                                        <svg class="icon">
                                            <use href="#i-info" />
                                        </svg>
                                    </div>
                                    <div class="activity-body">
                                        <div class="activity-title">
                                            <strong><?= e($act['user_name'] ?? 'Sistem') ?></strong>
                                            <span class="badge"><?= e($act['action']) ?></span>
                                        </div>
                                        <?php if (trim((string)$act['description']) !== ''): ?>
                                            <p class="activity-desc"><?= e($act['description']) ?></p>
                                        <?php endif; ?>
                                        <p class="activity-time"><?= e(format_waktu($act['created_at'])) ?></p>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <div class="empty">
                            <svg class="icon" aria-hidden="true">
                                <use href="#i-inbox" />
                            </svg>
                            <p>Belum ada aktivitas terbaru.</p>
                        </div>
                    <?php endif; ?>
                </section>

                <footer class="footer">
                    &copy; <?= date('Y') ?> Presensi NFC — Sistem Kehadiran Mahasiswa
                </footer>

            </div>
        </div>
    </div>

    <!-- ======================= BOTTOM TAB BAR (mobile) ======================= -->
    <nav class="tabbar" aria-label="Navigasi bawah">
        <a class="tabbar-item is-active" href="index2.php" aria-current="page">
            <svg class="icon" aria-hidden="true">
                <use href="#i-grid" />
            </svg>
            <span>Dashboard</span>
        </a>
        <a class="tabbar-item" href="mahasiswa.php">
            <svg class="icon" aria-hidden="true">
                <use href="#i-users" />
            </svg>
            <span>Mahasiswa</span>
        </a>
        <a class="tabbar-item" href="absensi.php">
            <svg class="icon" aria-hidden="true">
                <use href="#i-clipboard" />
            </svg>
            <span>Absensi</span>
        </a>
        <a class="tabbar-item" href="izin.php">
            <svg class="icon" aria-hidden="true">
                <use href="#i-mail" />
            </svg>
            <span>Izin</span>
        </a>
        <button type="button" class="tabbar-item" id="tabMenu"
            aria-controls="sidebar" aria-expanded="false" aria-label="Buka menu lainnya">
            <svg class="icon" aria-hidden="true">
                <use href="#i-menu" />
            </svg>
            <span>Menu</span>
        </button>
    </nav>

    <script>
        (function() {
            'use strict';

            var body = document.body;
            var toggle = document.getElementById('menuToggle');
            var tabMenu = document.getElementById('tabMenu');
            var sidebar = document.getElementById('sidebar');
            var backdrop = document.getElementById('backdrop');
            var BREAKPOINT = 1024;

            if (!toggle || !sidebar || !backdrop) {
                return;
            }

            function isOpen() {
                return body.classList.contains('nav-open');
            }

            function setOpen(open) {
                body.classList.toggle('nav-open', open);

                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                toggle.setAttribute('aria-label', open ? 'Tutup menu navigasi' : 'Buka menu navigasi');

                if (tabMenu) {
                    tabMenu.setAttribute('aria-expanded', open ? 'true' : 'false');
                }
            }

            toggle.addEventListener('click', function() {
                setOpen(!isOpen());
            });

            if (tabMenu) {
                tabMenu.addEventListener('click', function() {
                    setOpen(!isOpen());
                });
            }

            backdrop.addEventListener('click', function() {
                setOpen(false);
            });

            // Tutup drawer saat salah satu tautan menu diklik
            sidebar.addEventListener('click', function(e) {
                if (e.target.closest('a')) {
                    setOpen(false);
                }
            });

            // Tutup dengan tombol Escape
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && isOpen()) {
                    setOpen(false);
                    toggle.focus();
                }
            });

            // Tutup otomatis ketika layar melebar ke ukuran desktop
            var resizeTimer = null;
            window.addEventListener('resize', function() {
                if (resizeTimer) {
                    clearTimeout(resizeTimer);
                }
                resizeTimer = setTimeout(function() {
                    if (window.innerWidth > BREAKPOINT && isOpen()) {
                        setOpen(false);
                    }
                }, 120);
            });

            // Cegah scroll body saat drawer terbuka di iOS
            sidebar.addEventListener('touchmove', function(e) {
                var nav = sidebar.querySelector('.nav');
                if (nav && nav.contains(e.target)) {
                    return;
                }
                e.preventDefault();
            }, {
                passive: false
            });
        })();
    </script>
</body>

</html>