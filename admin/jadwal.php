<?php

/**
 * jadwal.php — Manajemen Jadwal Kuliah
 * Presensi NFC | Sistem Kehadiran Mahasiswa
 * ------------------------------------------------------------
 * CRUD untuk tabel `schedules`.
 * Aksi: Tambah, Edit (aktif/nonaktif lewat checkbox), Lihat detail.
 *
 * Layout mengikuti pola halaman Data Mahasiswa:
 *  - Desktop  : sidebar tetap (sticky) + konten di kanan.
 *  - Mobile   : pola aplikasi native — app bar sticky di atas,
 *               bottom tab bar (fixed), hero full-bleed, kartu
 *               statistik 2 kolom, modal menjadi bottom sheet,
 *               safe-area aware (notch / home indicator).
 */

date_default_timezone_set('Asia/Jakarta');

// ============================================================
// 1. SESSION & OTORISASI
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
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    header('Location: ' . $url);
    exit;
}

if (empty($_SESSION['user_id'])) {
    redirect_to('login.php');
}

$sessionRole = $_SESSION['role'] ?? '';
if ($sessionRole === 'mahasiswa') redirect_to('index.php');
if ($sessionRole !== 'admin')     redirect_to('login.php');

$adminId   = (int)$_SESSION['user_id'];
$adminName = trim((string)($_SESSION['name'] ?? ''));
if ($adminName === '') $adminName = 'Administrator';
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
    error_log('[jadwal.php] Koneksi database tidak tersedia.');
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
        if (!$stmt) return 0;
        if ($types !== '') $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) {
            $stmt->close();
            return 0;
        }
        $total = 0;
        $stmt->bind_result($total);
        $stmt->fetch();
        $stmt->close();
        return (int)$total;
    } catch (Throwable $ex) {
        error_log('[jadwal.php] ' . $ex->getMessage());
        return 0;
    }
}

function log_activity(mysqli $db, ?int $userId, string $action, string $desc): void
{
    try {
        $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)");
        if (!$stmt) return;
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $stmt->bind_param('isss', $userId, $action, $desc, $ip);
        $stmt->execute();
        $stmt->close();
    } catch (Throwable $ex) {
        error_log('[jadwal.php] ' . $ex->getMessage());
    }
}

// Kode presensi acak 6 karakter (tanpa huruf/angka rancu), bisa diedit manual.
function generate_attendance_code(): string
{
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code  = '';
    for ($i = 0; $i < 6; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $code;
}

function time_short(?string $t): string
{
    return $t ? substr($t, 0, 5) : '';
}

// ============================================================
// 4. KONSTANTA HARI
// ============================================================
$DAYS = [
    'senin'  => 'Senin',
    'selasa' => 'Selasa',
    'rabu'   => 'Rabu',
    'kamis'  => 'Kamis',
    'jumat'  => 'Jumat',
    'sabtu'  => 'Sabtu',
    'minggu' => 'Minggu',
];

// ============================================================
// 5. HANDLE CRUD (CREATE / UPDATE)
// ============================================================
$formData   = [
    'id' => 0, 'course_name' => '', 'class_name' => '', 'lecturer_name' => '',
    'day_of_week' => 'senin', 'start_time' => '', 'late_after' => '',
    'end_time' => '', 'attendance_code' => '', 'is_active' => 1,
];
$formErrors = [];
$formAction = 'create';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $id         = (int)($_POST['id'] ?? 0);
        $course     = trim($_POST['course_name'] ?? '');
        $className  = trim($_POST['class_name'] ?? '');
        $lecturer   = trim($_POST['lecturer_name'] ?? '');
        $day        = (string)($_POST['day_of_week'] ?? 'senin');
        $start      = trim($_POST['start_time'] ?? '');
        $late       = trim($_POST['late_after'] ?? '');
        $end        = trim($_POST['end_time'] ?? '');
        $code       = strtoupper(trim($_POST['attendance_code'] ?? ''));
        $isActive   = isset($_POST['is_active']) ? 1 : 0;

        $formData = [
            'id' => $id, 'course_name' => $course, 'class_name' => $className,
            'lecturer_name' => $lecturer, 'day_of_week' => $day, 'start_time' => $start,
            'late_after' => $late, 'end_time' => $end, 'attendance_code' => $code,
            'is_active' => $isActive,
        ];
        $formAction = $action;

        $isTime = function (string $t): bool {
            return (bool)preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $t);
        };

        if ($course === '')    $formErrors[] = 'Mata kuliah wajib diisi.';
        if ($className === '') $formErrors[] = 'Kelas wajib diisi.';
        if (!isset($DAYS[$day])) $formErrors[] = 'Hari tidak valid.';
        if ($start === '' || !$isTime($start)) $formErrors[] = 'Jam mulai tidak valid.';
        if ($end === '' || !$isTime($end))     $formErrors[] = 'Jam selesai tidak valid.';
        if ($late !== '' && !$isTime($late))   $formErrors[] = 'Batas telat tidak valid.';
        if (empty($formErrors)) {
            if ($late === '') $late = $start; // tanpa batas telat → dianggap sama dengan jam mulai
            if ($end <= $start) $formErrors[] = 'Jam selesai harus lebih besar dari jam mulai.';
        }

        if (empty($formErrors)) {
            $code = $code !== '' ? $code : generate_attendance_code();

            try {
                if ($action === 'create') {
                    $stmt = $db->prepare(
                        "INSERT INTO schedules
                         (course_name, class_name, lecturer_name, day_of_week, start_time, late_after, end_time, attendance_code, is_active)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
                    );
                    $stmt->bind_param('ssssssssi', $course, $className, $lecturer, $day, $start, $late, $end, $code, $isActive);
                    if ($stmt->execute()) {
                        log_activity($db, $adminId, 'Tambah Jadwal', "Menambah jadwal: $course ($className)");
                        $_SESSION['flash']       = 'Jadwal "' . $course . '" berhasil ditambahkan.';
                        $_SESSION['flash_type']  = 'success';
                        $_SESSION['flash_title'] = 'Data Ditambahkan';
                        redirect_to('jadwal.php');
                    }
                    $formErrors[] = 'Gagal menyimpan: ' . $stmt->error;
                    $stmt->close();
                } else {
                    $stmt = $db->prepare(
                        "UPDATE schedules
                         SET course_name=?, class_name=?, lecturer_name=?, day_of_week=?, start_time=?, late_after=?, end_time=?, attendance_code=?, is_active=?
                         WHERE id=?"
                    );
                    $stmt->bind_param('ssssssssii', $course, $className, $lecturer, $day, $start, $late, $end, $code, $isActive, $id);
                    if ($stmt->execute()) {
                        log_activity($db, $adminId, 'Edit Jadwal', "Memperbarui jadwal: $course ($className)");
                        $_SESSION['flash']       = 'Jadwal "' . $course . '" berhasil diperbarui.';
                        $_SESSION['flash_type']  = 'success';
                        $_SESSION['flash_title'] = 'Data Diperbarui';
                        redirect_to('jadwal.php');
                    }
                    $formErrors[] = 'Gagal menyimpan: ' . $stmt->error;
                    $stmt->close();
                }
            } catch (Throwable $ex) {
                $formErrors[] = 'Terjadi kesalahan: ' . $ex->getMessage();
            }
        }

        $_SESSION['form_errors'] = $formErrors;
        $_SESSION['form_data']   = $formData;
        $_SESSION['form_action'] = $formAction;
        redirect_to('jadwal.php?modal=open');
    }
}

// ============================================================
// 6. FLASH & FORM STATE
// ============================================================
$flash      = $_SESSION['flash'] ?? null;
$flashType  = $_SESSION['flash_type'] ?? 'success';
$flashTitle = $_SESSION['flash_title'] ?? ($flashType === 'success' ? 'Berhasil' : 'Gagal');
unset($_SESSION['flash'], $_SESSION['flash_type'], $_SESSION['flash_title']);

$formErrors = $_SESSION['form_errors'] ?? [];
$formData   = $_SESSION['form_data'] ?? $formData;
$formAction = $_SESSION['form_action'] ?? 'create';
$autoOpenModal = !empty($formErrors) || (($_GET['modal'] ?? '') === 'open');
unset($_SESSION['form_errors'], $_SESSION['form_data'], $_SESSION['form_action']);

// ============================================================
// 7. FILTER & PAGINATION
// ============================================================
$search       = trim($_GET['q'] ?? '');
$filterDay    = trim($_GET['hari'] ?? '');
$filterClass  = trim($_GET['kelas'] ?? '');
$filterStatus = $_GET['status'] ?? '';
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 15;

$classList = [];
$res = $db->query("SELECT DISTINCT class_name FROM schedules WHERE class_name IS NOT NULL AND class_name != '' ORDER BY class_name ASC");
if ($res) {
    while ($r = $res->fetch_assoc()) $classList[] = $r['class_name'];
    $res->free();
}

$where  = "WHERE 1=1";
$params = [];
$types  = '';

if ($search !== '') {
    $where .= " AND (course_name LIKE ? OR lecturer_name LIKE ? OR class_name LIKE ?)";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= 'sss';
}
if ($filterDay !== '' && isset($DAYS[$filterDay])) {
    $where .= " AND day_of_week = ?";
    $params[] = $filterDay;
    $types .= 's';
}
if ($filterClass !== '') {
    $where .= " AND class_name = ?";
    $params[] = $filterClass;
    $types .= 's';
}
if ($filterStatus === 'aktif')    $where .= " AND is_active = 1";
if ($filterStatus === 'nonaktif') $where .= " AND is_active = 0";

$totalRows = 0;
$stmt = $db->prepare("SELECT COUNT(*) FROM schedules $where");
if ($stmt) {
    if ($types !== '') $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $stmt->bind_result($totalRows);
    $stmt->fetch();
    $stmt->close();
}
$totalPages = max(1, (int)ceil($totalRows / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;

$jadwalList = [];
$dataSql = "SELECT id, course_name, class_name, lecturer_name, day_of_week, start_time, late_after, end_time, attendance_code, is_active
            FROM schedules $where
            ORDER BY FIELD(day_of_week, 'senin','selasa','rabu','kamis','jumat','sabtu','minggu'), start_time ASC
            LIMIT ? OFFSET ?";
$stmt = $db->prepare($dataSql);
if ($stmt) {
    $allTypes  = $types . 'ii';
    $allParams = array_merge($params, [$perPage, $offset]);
    $stmt->bind_param($allTypes, ...$allParams);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) $jadwalList[] = $row;
    $stmt->close();
}

// ============================================================
// 8. STATISTIK
// ============================================================
$stats = [
    'total'    => fetch_total($db, "SELECT COUNT(*) FROM schedules"),
    'aktif'    => fetch_total($db, "SELECT COUNT(*) FROM schedules WHERE is_active = 1"),
    'nonaktif' => fetch_total($db, "SELECT COUNT(*) FROM schedules WHERE is_active = 0"),
    'kelas'    => fetch_total($db, "SELECT COUNT(DISTINCT class_name) FROM schedules WHERE class_name IS NOT NULL AND class_name != ''"),
];

$statCards = [
    ['value' => $stats['total'],    'label' => 'Total Jadwal',     'icon' => 'i-calendar',  'tone' => 'blue'],
    ['value' => $stats['aktif'],    'label' => 'Jadwal Aktif',     'icon' => 'i-check',     'tone' => 'green'],
    ['value' => $stats['nonaktif'], 'label' => 'Nonaktif',         'icon' => 'i-x-circle',  'tone' => 'red'],
    ['value' => $stats['kelas'],    'label' => 'Kelas Terjadwal',  'icon' => 'i-users',     'tone' => 'amber'],
];

// ============================================================
// 9. DIAGRAM
// ============================================================
$dayCount = array_fill_keys(array_keys($DAYS), 0);
$res = $db->query("SELECT day_of_week, COUNT(*) AS cnt FROM schedules GROUP BY day_of_week");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        if (isset($dayCount[$r['day_of_week']])) {
            $dayCount[$r['day_of_week']] = (int)$r['cnt'];
        }
    }
    $res->free();
}
$dayDist = [];
foreach ($DAYS as $key => $label) {
    $dayDist[] = ['label' => $label, 'cnt' => $dayCount[$key]];
}
$maxDayCount = 1;
foreach ($dayDist as $d) if ($d['cnt'] > $maxDayCount) $maxDayCount = $d['cnt'];

$totalSafe     = max(1, $stats['total']);
$activePercent = (int)round(($stats['aktif'] / $totalSafe) * 100);
$radius        = 55;
$circumference = 2 * M_PI * $radius;
$activeDash    = $circumference * ($activePercent / 100);

// ============================================================
// 10. MENU SIDEBAR
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
$currentPage = 'jadwal.php';
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
    <title>Jadwal Kuliah — Presensi NFC</title>

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

        body.nav-open,
        body.modal-open {
            overflow: hidden;
        }

        h1,
        h2,
        h3,
        h4,
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

        @keyframes spin {
            from {
                transform: rotate(0deg);
            }

            to {
                transform: rotate(360deg);
            }
        }

        /* ============================================================
           LAYOUT
           ============================================================ */
        .app {
            display: grid;
            grid-template-columns: var(--sidebar-w) minmax(0, 1fr);
            min-height: 100vh;
            min-height: 100dvh;
            background-color: var(--navy);
        }

        /* ============================================================
           SIDEBAR / DRAWER
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

        .hero {
            width: 100%;
            border-radius: var(--radius);
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            background-color: var(--navy);
            overflow: hidden;
            line-height: 0;
        }

        .hero-img {
            display: block;
            width: 100%;
            height: auto;
            object-fit: contain;
            max-width: 100%;
        }

        .panel {
            background: var(--panel-bg);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
        }

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

        /* ============================================================
           TOAST
           ============================================================ */
        .toast-container {
            position: fixed;
            top: 22px;
            right: 22px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 12px;
            pointer-events: none;
            max-width: calc(100vw - 44px);
        }

        .toast {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            width: 380px;
            max-width: 100%;
            padding: 16px 18px;
            border-radius: var(--radius-sm);
            background: var(--panel);
            border: 1px solid var(--border);
            box-shadow: 0 14px 40px rgba(0, 0, 0, 0.5);
            pointer-events: auto;
            position: relative;
            overflow: hidden;
            animation: toastIn 0.45s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        .toast.is-hiding {
            animation: toastOut 0.3s ease forwards;
        }

        @keyframes toastIn {
            from {
                opacity: 0;
                transform: translateY(-14px) scale(0.98);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        @keyframes toastOut {
            from {
                opacity: 1;
            }

            to {
                opacity: 0;
                transform: translateY(-10px) scale(0.98);
            }
        }

        .toast--success {
            border-color: rgba(34, 197, 94, 0.55);
            background: linear-gradient(135deg, rgba(34, 197, 94, 0.18), var(--panel));
        }

        .toast--error {
            border-color: rgba(248, 113, 113, 0.55);
            background: linear-gradient(135deg, rgba(248, 113, 113, 0.18), var(--panel));
        }

        .toast-icon {
            display: grid;
            place-items: center;
            width: 42px;
            height: 42px;
            flex-shrink: 0;
            border-radius: 50%;
            animation: iconPop 0.5s cubic-bezier(0.68, -0.55, 0.27, 1.55) 0.15s both;
        }

        .toast--success .toast-icon {
            background: rgba(34, 197, 94, 0.28);
            color: var(--green);
        }

        .toast--error .toast-icon {
            background: rgba(248, 113, 113, 0.28);
            color: var(--red);
        }

        .toast-icon .icon {
            width: 22px;
            height: 22px;
        }

        @keyframes iconPop {
            0% {
                transform: scale(0);
            }

            60% {
                transform: scale(1.18);
            }

            100% {
                transform: scale(1);
            }
        }

        .toast-body {
            flex: 1;
            min-width: 0;
            padding-top: 2px;
        }

        .toast-title {
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 3px;
        }

        .toast-message {
            font-size: 13px;
            color: var(--text-2);
            line-height: 1.45;
            overflow-wrap: anywhere;
        }

        .toast-close {
            display: grid;
            place-items: center;
            width: 24px;
            height: 24px;
            padding: 0;
            border: none;
            border-radius: 4px;
            background: transparent;
            color: var(--text-2);
            cursor: pointer;
            flex-shrink: 0;
            transition: background var(--speed), color var(--speed);
        }

        .toast-close:hover {
            background: rgba(148, 163, 184, 0.2);
            color: var(--text);
        }

        .toast-close .icon {
            width: 14px;
            height: 14px;
        }

        .toast-progress {
            position: absolute;
            bottom: 0;
            left: 0;
            height: 3px;
            width: 100%;
            animation: toastProgress 5s linear forwards;
        }

        .toast--success .toast-progress {
            background: var(--green);
        }

        .toast--error .toast-progress {
            background: var(--red);
        }

        @keyframes toastProgress {
            from {
                width: 100%;
            }

            to {
                width: 0%;
            }
        }

        /* ============================================================
           STATISTIK
           ============================================================ */
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

        /* ============================================================
           DIAGRAM ANALISIS
           ============================================================ */
        .analysis-grid {
            display: grid;
            grid-template-columns: 3fr 2fr;
            gap: var(--gap);
        }

        .analysis-panel {
            padding: var(--card-pad) 24px;
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .analysis-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 8px 16px;
            padding-bottom: 14px;
            border-bottom: 1px solid var(--border);
        }

        .analysis-head h3 {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 16px;
            font-weight: 700;
        }

        .analysis-head h3 .icon {
            color: var(--cyan);
        }

        .analysis-hint {
            font-size: 12px;
            color: var(--text-2);
        }

        .chart-list {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .chart-row {
            display: grid;
            grid-template-columns: 130px 1fr 48px;
            gap: 12px;
            align-items: center;
            font-size: 13px;
        }

        .chart-label {
            color: var(--text-2);
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .chart-track {
            height: 26px;
            background: rgba(15, 27, 45, 0.65);
            border-radius: 6px;
            overflow: hidden;
            position: relative;
        }

        .chart-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--blue), var(--cyan));
            border-radius: 6px;
            min-width: 4px;
            transition: width 0.6s ease;
        }

        .chart-value {
            text-align: right;
            font-weight: 700;
            color: var(--text);
        }

        .empty-chart {
            padding: 30px 12px;
            text-align: center;
            color: var(--text-2);
            font-size: 13px;
        }

        .donut-wrap {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 22px;
        }

        .donut {
            position: relative;
            width: 200px;
            height: 200px;
        }

        .donut-svg {
            width: 100%;
            height: 100%;
            transform: rotate(-90deg);
        }

        .donut-track {
            fill: none;
            stroke: rgba(248, 113, 113, 0.45);
            stroke-width: 16;
        }

        .donut-fill {
            fill: none;
            stroke: var(--green);
            stroke-width: 16;
            stroke-linecap: round;
            transition: stroke-dasharray 0.6s ease;
        }

        .donut-center {
            position: absolute;
            inset: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            pointer-events: none;
        }

        .donut-value {
            font-size: 32px;
            font-weight: 800;
            line-height: 1;
        }

        .donut-label {
            margin-top: 4px;
            font-size: 12px;
            color: var(--text-2);
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .donut-legend {
            display: flex;
            flex-direction: column;
            gap: 10px;
            width: 100%;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            border-radius: 8px;
            background: rgba(15, 27, 45, 0.55);
            font-size: 13px;
        }

        .legend-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .legend-dot--green {
            background: var(--green);
        }

        .legend-dot--red {
            background: var(--red);
        }

        .legend-text {
            flex: 1;
            color: var(--text-2);
        }

        .legend-value {
            font-weight: 700;
            color: var(--text);
        }

        /* ============================================================
           TABEL & TOOLBAR
           ============================================================ */
        .table-panel {
            padding: var(--card-pad);
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
            justify-content: space-between;
        }

        .toolbar-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 16px;
            font-weight: 700;
        }

        .toolbar-title .icon {
            color: var(--cyan);
        }

        .toolbar-form {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            flex: 1 1 auto;
        }

        .form-actions {
            display: contents;
        }

        .search-wrap {
            position: relative;
            flex: 1 1 240px;
            min-width: 180px;
        }

        .search-wrap .icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-2);
            width: 18px;
            height: 18px;
            pointer-events: none;
        }

        .search-wrap .form-control {
            padding-left: 38px;
        }

        .form-control {
            width: 100%;
            height: 42px;
            padding: 0 14px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            background: var(--navy);
            color: var(--text);
            font-family: inherit;
            font-size: 14px;
            transition: border-color var(--speed), box-shadow var(--speed);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--cyan);
            box-shadow: 0 0 0 3px rgba(66, 232, 213, 0.15);
        }

        .form-control::placeholder {
            color: rgba(168, 183, 204, 0.6);
        }

        select.form-control {
            cursor: pointer;
        }

        .toolbar-form .filter-select {
            flex: 0 1 170px;
            min-width: 140px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            height: 42px;
            padding: 0 18px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            background: var(--panel-2);
            color: var(--text);
            font-family: inherit;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background var(--speed), border-color var(--speed), color var(--speed);
            text-decoration: none;
            white-space: nowrap;
        }

        .btn:hover {
            background: var(--nav-active);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--cyan), var(--blue));
            color: var(--navy);
            border-color: transparent;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--blue), var(--cyan));
            color: var(--navy);
        }

        .btn-danger {
            background: rgba(248, 113, 113, 0.15);
            color: var(--red);
            border-color: rgba(248, 113, 113, 0.4);
        }

        .btn-danger:hover {
            background: var(--red);
            color: var(--navy);
        }

        .btn:disabled {
            opacity: 0.65;
            cursor: not-allowed;
        }

        .btn-icon {
            display: inline-grid;
            place-items: center;
            width: 34px;
            height: 34px;
            padding: 0;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--panel-2);
            color: var(--text-2);
            cursor: pointer;
            transition: background var(--speed), color var(--speed), border-color var(--speed), transform var(--speed);
        }

        .btn-icon:hover {
            background: var(--nav-active);
            color: var(--text);
        }

        .btn-icon--edit:hover {
            color: var(--cyan);
            border-color: var(--cyan);
        }

        .btn-icon--toggle:hover {
            color: var(--amber);
            border-color: var(--amber);
        }

        .btn-icon--delete:hover {
            color: var(--red);
            border-color: var(--red);
        }

        .btn-icon .icon {
            width: 16px;
            height: 16px;
        }

        .action-group {
            display: inline-flex;
            gap: 6px;
        }

        .table-wrap {
            overflow-x: auto;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border);
            -webkit-overflow-scrolling: touch;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
            min-width: 760px;
        }

        .data-table thead th {
            padding: 13px 16px;
            text-align: left;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: var(--text-2);
            background: rgba(15, 27, 45, 0.85);
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
        }

        .data-table tbody td {
            padding: 14px 16px;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
        }

        .data-table tbody tr:last-child td {
            border-bottom: 0;
        }

        .data-table tbody tr:hover {
            background: rgba(23, 43, 69, 0.45);
        }

        .cell-user {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
        }

        .user-avatar {
            display: grid;
            place-items: center;
            width: 34px;
            height: 34px;
            flex-shrink: 0;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--cyan), var(--blue));
            color: var(--navy);
            font-weight: 800;
            font-size: 13px;
        }

        .user-name {
            font-weight: 600;
            overflow-wrap: anywhere;
        }

        .user-nim {
            font-size: 12px;
            color: var(--text-2);
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            border: 1px solid transparent;
        }

        .badge--green {
            background: rgba(34, 197, 94, 0.14);
            color: #6EE7A4;
            border-color: rgba(34, 197, 94, 0.3);
        }

        .badge--red {
            background: rgba(248, 113, 113, 0.14);
            color: #FFB3B3;
            border-color: rgba(248, 113, 113, 0.3);
        }

        .badge--blue {
            background: rgba(56, 189, 248, 0.14);
            color: #8ED6FF;
            border-color: rgba(56, 189, 248, 0.3);
        }

        .badge--muted {
            background: rgba(168, 183, 204, 0.1);
            color: var(--text-2);
            border-color: rgba(168, 183, 204, 0.2);
        }

        .empty-table {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            padding: 48px 20px;
            color: var(--text-2);
            font-size: 14px;
            text-align: center;
        }

        .empty-table .icon {
            width: 40px;
            height: 40px;
            opacity: 0.7;
        }

        .pagination {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 6px;
            justify-content: flex-end;
            padding-top: 4px;
        }

        .page-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 36px;
            height: 36px;
            padding: 0 10px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--panel-2);
            color: var(--text);
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            transition: background var(--speed), color var(--speed), border-color var(--speed);
        }

        .page-link:hover {
            background: var(--nav-active);
        }

        .page-link.is-active {
            background: linear-gradient(135deg, var(--cyan), var(--blue));
            color: var(--navy);
            border-color: transparent;
        }

        .page-link.is-disabled {
            opacity: 0.4;
            pointer-events: none;
        }

        .page-info {
            font-size: 12px;
            color: var(--text-2);
            margin-right: 8px;
        }

        /* ============================================================
           MODAL
           ============================================================ */
        .modal {
            position: fixed;
            inset: 0;
            z-index: 100;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: rgba(2, 8, 20, 0.75);
            -webkit-backdrop-filter: blur(4px);
            backdrop-filter: blur(4px);
        }

        .modal.is-open {
            display: flex;
        }

        .modal-dialog {
            width: 100%;
            max-width: 520px;
            max-height: 90vh;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: 0 24px 80px rgba(0, 0, 0, 0.55);
            animation: modalIn 0.25s ease forwards;
        }

        @keyframes modalIn {
            from {
                opacity: 0;
                transform: scale(0.95) translateY(10px);
            }

            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 18px 22px;
            border-bottom: 1px solid var(--border);
        }

        .modal-title {
            font-size: 17px;
            font-weight: 700;
        }

        .modal-close {
            display: grid;
            place-items: center;
            width: 34px;
            height: 34px;
            padding: 0;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: transparent;
            color: var(--text-2);
            cursor: pointer;
            transition: background var(--speed), color var(--speed);
        }

        .modal-close:hover {
            background: var(--panel-2);
            color: var(--text);
        }

        .modal-close .icon {
            width: 16px;
            height: 16px;
        }

        .modal-body {
            padding: 20px 22px;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .modal-footer {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-end;
            gap: 10px;
            padding: 16px 22px;
            border-top: 1px solid var(--border);
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .form-label {
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: var(--text-2);
        }

        .form-check {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            background: rgba(15, 27, 45, 0.55);
            cursor: pointer;
        }

        .form-check input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: var(--cyan);
            cursor: pointer;
        }

        .form-check-text {
            font-size: 14px;
        }

        .modal-errors {
            padding: 12px 14px;
            border-radius: var(--radius-sm);
            background: rgba(248, 113, 113, 0.1);
            border: 1px solid rgba(248, 113, 113, 0.35);
            color: #FFC7C7;
            font-size: 13px;
        }

        .modal-errors ul {
            padding-left: 18px;
            list-style: disc;
        }

        .delete-warning {
            display: flex;
            gap: 12px;
            padding: 14px 16px;
            border-radius: var(--radius-sm);
            background: rgba(248, 113, 113, 0.08);
            border: 1px solid rgba(248, 113, 113, 0.3);
        }

        .delete-warning .icon {
            color: var(--red);
            flex-shrink: 0;
            margin-top: 2px;
        }

        .delete-warning p {
            font-size: 13px;
            color: var(--text);
        }

        .delete-warning p+p {
            margin-top: 6px;
            font-size: 12px;
            color: var(--text-2);
        }

        /* ============================================================
           FOOTER
           ============================================================ */
        .footer {
            padding: 4px 4px 0;
            color: var(--text-2);
            font-size: 12px;
            text-align: center;
        }

        /* ============================================================
           FAB (mobile)
           ============================================================ */
        .fab {
            display: none;
            position: fixed;
            right: 16px;
            bottom: calc(var(--tabbar-h) + var(--safe-b) + 18px);
            z-index: 29;
            width: 56px;
            height: 56px;
            padding: 0;
            border: 0;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--cyan), var(--blue));
            color: var(--navy);
            box-shadow: 0 12px 28px rgba(66, 232, 213, 0.32);
            cursor: pointer;
            transition: transform var(--speed), box-shadow var(--speed);
        }

        .fab:active {
            transform: scale(0.92);
        }

        .fab .icon {
            width: 26px;
            height: 26px;
            stroke-width: 2.4;
        }

        /* ============================================================
           BOTTOM TAB BAR (mobile)
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

            .analysis-grid {
                grid-template-columns: 1fr;
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

            /* Sidebar → drawer */
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

            .main {
                padding: 0;
                gap: 0;
                max-width: none;
            }

            .page {
                padding: 14px var(--pad) calc(var(--tabbar-h) + var(--safe-b) + 26px);
                gap: 14px;
            }

            /* App bar sticky */
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

            /* Hero full-bleed */
            .hero {
                margin-inline: calc(var(--pad) * -1);
                border-radius: 0;
                border-left: 0;
                border-right: 0;
                border-top: 0;
                box-shadow: none;
            }

            /* Tab bar & FAB tampil */
            .tabbar {
                display: grid;
            }

            .fab {
                display: grid;
                place-items: center;
            }

            /* Toolbar jadi susunan vertikal */
            .toolbar {
                flex-direction: column;
                align-items: stretch;
                gap: 14px;
            }

            .toolbar-form {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 10px;
            }

            .search-wrap {
                grid-column: 1 / -1;
                min-width: 0;
                flex-basis: auto;
            }

            .toolbar-form .filter-select {
                width: 100%;
                min-width: 0;
                flex: initial;
            }

            .form-actions {
                display: flex;
                grid-column: 1 / -1;
                gap: 10px;
            }

            .form-actions .btn {
                flex: 1 1 0;
                padding: 0 10px;
            }

            .form-actions .btn-add {
                display: none;
                /* digantikan FAB */
            }

            .analysis-panel,
            .table-panel {
                border-radius: 14px;
            }

            .pagination {
                justify-content: center;
            }

            .page-info {
                width: 100%;
                text-align: center;
                margin: 0 0 4px;
            }

            .toast-container {
                top: calc(var(--safe-t) + 12px);
                left: 12px;
                right: 12px;
                max-width: none;
            }

            .toast {
                width: auto;
            }

            .data-table tbody tr:active {
                background: rgba(23, 43, 69, 0.65);
            }
        }

        @media (max-width: 640px) {
            :root {
                --pad: 14px;
                --gap: 12px;
                --card-pad: 14px;
            }

            /* Statistik ringkas 2 kolom */
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

            .analysis-panel,
            .table-panel {
                padding: var(--card-pad) 14px;
            }

            .chart-row {
                grid-template-columns: 96px 1fr 38px;
                gap: 10px;
                font-size: 12.5px;
            }

            .donut {
                width: 168px;
                height: 168px;
            }

            .donut-value {
                font-size: 26px;
            }

            /* ---------- Tabel → Kartu ---------- */
            .table-wrap {
                border: 0;
                overflow: visible;
                border-radius: 0;
            }

            .data-table {
                min-width: 0;
                display: block;
            }

            .data-table thead {
                display: none;
            }

            .data-table tbody {
                display: flex;
                flex-direction: column;
                gap: 10px;
            }

            .data-table tbody tr {
                display: flex;
                flex-wrap: wrap;
                gap: 8px 10px;
                padding: 14px;
                border: 1px solid var(--border);
                border-radius: 14px;
                background: rgba(15, 27, 45, 0.55);
            }

            .data-table tbody tr:hover {
                background: rgba(23, 43, 69, 0.55);
            }

            .data-table tbody td {
                width: auto;
                padding: 0;
                border-bottom: 0;
            }

            .data-table tbody td:nth-child(1),
            .data-table tbody td:nth-child(2),
            .data-table tbody td:nth-child(6) {
                flex: 1 1 100%;
            }

            .data-table tbody td:nth-child(2) {
                font-size: 12.5px;
                color: var(--text-2);
                overflow-wrap: anywhere;
            }

            .data-table tbody td:nth-child(6) {
                margin-top: 4px;
            }

            .data-table tbody tr.empty-row {
                display: block;
                padding: 0;
                border: 0;
                background: transparent;
            }

            .data-table tbody tr.empty-row td {
                padding: 0;
            }

            .cell-user {
                gap: 12px;
            }

            .user-avatar {
                width: 40px;
                height: 40px;
                font-size: 15px;
            }

            .user-name {
                font-size: 15px;
            }

            .action-group {
                display: flex;
                width: 100%;
                gap: 8px;
            }

            .action-group>form {
                display: flex;
                flex: 1 1 0;
            }

            .action-group .btn-icon {
                width: 100%;
                height: 42px;
                border-radius: 10px;
            }

            .action-group .btn-icon .icon {
                width: 18px;
                height: 18px;
            }

            /* ---------- Modal → Bottom sheet ---------- */
            .modal {
                padding: 0;
                align-items: flex-end;
            }

            .modal-dialog {
                max-width: none;
                max-height: 92vh;
                border-radius: 20px 20px 0 0;
                border-bottom: 0;
                animation: sheetUp 0.3s cubic-bezier(0.32, 0.72, 0, 1) forwards;
                padding-bottom: var(--safe-b);
            }

            @keyframes sheetUp {
                from {
                    opacity: 0.6;
                    transform: translateY(100%);
                }

                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            .modal-dialog::before {
                content: "";
                display: block;
                width: 44px;
                height: 4px;
                margin: 8px auto 0;
                border-radius: 4px;
                background: rgba(148, 163, 184, 0.35);
            }

            .modal-header {
                padding: 12px 18px 14px;
            }

            .modal-body {
                padding: 16px 18px;
            }

            .modal-footer {
                padding: 14px 18px calc(14px + var(--safe-b));
            }

            .modal-footer .btn {
                flex: 1 1 0;
            }

            .footer {
                font-size: 11px;
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

            .data-table tbody td:nth-child(3),
            .data-table tbody td:nth-child(4),
            .data-table tbody td:nth-child(5) {
                font-size: 11px;
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
        <symbol id="i-bar-chart" viewBox="0 0 24 24">
            <line x1="12" y1="20" x2="12" y2="10" />
            <line x1="18" y1="20" x2="18" y2="4" />
            <line x1="6" y1="20" x2="6" y2="16" />
            <line x1="3" y1="20" x2="21" y2="20" />
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
        <symbol id="i-x-circle" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="10" />
            <line x1="15" y1="9" x2="9" y2="15" />
            <line x1="9" y1="9" x2="15" y2="15" />
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
        <symbol id="i-plus" viewBox="0 0 24 24">
            <line x1="12" y1="5" x2="12" y2="19" />
            <line x1="5" y1="12" x2="19" y2="12" />
        </symbol>
        <symbol id="i-edit" viewBox="0 0 24 24">
            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
        </symbol>
        <symbol id="i-trash" viewBox="0 0 24 24">
            <polyline points="3 6 5 6 21 6" />
            <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" />
            <path d="M10 11v6M14 11v6" />
            <path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2" />
        </symbol>
        <symbol id="i-search" viewBox="0 0 24 24">
            <circle cx="11" cy="11" r="8" />
            <line x1="21" y1="21" x2="16.65" y2="16.65" />
        </symbol>
        <symbol id="i-x" viewBox="0 0 24 24">
            <line x1="18" y1="6" x2="6" y2="18" />
            <line x1="6" y1="6" x2="18" y2="18" />
        </symbol>
        <symbol id="i-toggle" viewBox="0 0 24 24">
            <rect x="1" y="5" width="22" height="14" rx="7" />
            <circle cx="16" cy="12" r="3" />
        </symbol>
        <symbol id="i-inbox" viewBox="0 0 24 24">
            <polyline points="22 12 16 12 14 15 10 15 8 12 2 12" />
            <path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z" />
        </symbol>
        <symbol id="i-alert" viewBox="0 0 24 24">
            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
            <line x1="12" y1="9" x2="12" y2="13" />
            <line x1="12" y1="17" x2="12.01" y2="17" />
        </symbol>
        <symbol id="i-loader" viewBox="0 0 24 24">
            <path d="M21 12a9 9 0 1 1-6.219-8.56" />
        </symbol>
    </svg>

    <!-- Ikon tambahan khusus halaman ini -->
    <svg xmlns="http://www.w3.org/2000/svg" width="0" height="0" aria-hidden="true" focusable="false" style="display:none">
        <symbol id="i-eye" viewBox="0 0 24 24">
            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
            <circle cx="12" cy="12" r="3" />
        </symbol>
    </svg>

    <!-- ======================= TOAST CONTAINER ======================= -->
    <div class="toast-container" id="toastContainer" aria-live="polite" aria-atomic="true">
        <?php if (!empty($flash)): ?>
            <div class="toast toast--<?= $flashType === 'success' ? 'success' : 'error' ?>"
                role="alert"
                data-auto-dismiss="5000">
                <div class="toast-icon">
                    <svg class="icon" aria-hidden="true">
                        <use href="#<?= $flashType === 'success' ? 'i-check' : 'i-alert' ?>" />
                    </svg>
                </div>
                <div class="toast-body">
                    <div class="toast-title"><?= e($flashTitle) ?></div>
                    <div class="toast-message"><?= e($flash) ?></div>
                </div>
                <button type="button" class="toast-close" aria-label="Tutup notifikasi">
                    <svg class="icon" aria-hidden="true">
                        <use href="#i-x" />
                    </svg>
                </button>
                <div class="toast-progress"></div>
            </div>
        <?php endif; ?>
    </div>

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

            <!-- App bar -->
            <header class="topbar panel">
                <div class="topbar-left">
                    <button type="button" class="menu-toggle" id="menuToggle"
                        aria-label="Buka menu navigasi" aria-controls="sidebar" aria-expanded="false">
                        <svg class="icon" aria-hidden="true">
                            <use href="#i-menu" />
                        </svg>
                    </button>
                    <div class="topbar-title">
                        <h2>Jadwal Kuliah</h2>
                        <p>Kelola jadwal kuliah, dosen pengampu, kelas, dan kode presensi.</p>
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

                <!-- Hero banner -->
                <section class="hero" aria-hidden="true">
                    <img class="hero-img" src="background.jpg" alt="" width="1600" height="600"
                        loading="eager" decoding="async">
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

                <!-- Diagram analisis -->
                <section class="analysis-grid" aria-label="Diagram analisis jadwal">
                    <div class="analysis-panel panel">
                        <div class="analysis-head">
                            <h3>
                                <svg class="icon" aria-hidden="true">
                                    <use href="#i-bar-chart" />
                                </svg>
                                Distribusi Jadwal per Hari
                            </h3>
                            <span class="analysis-hint"><?= number_format($stats['total'], 0, ',', '.') ?> jadwal terdaftar</span>
                        </div>

                        <div class="chart-list">
                            <?php foreach ($dayDist as $d):
                                $pct = $maxDayCount > 0 ? ($d['cnt'] / $maxDayCount) * 100 : 0;
                            ?>
                                <div class="chart-row">
                                    <div class="chart-label"><?= e($d['label']) ?></div>
                                    <div class="chart-track">
                                        <div class="chart-fill" style="width: <?= number_format($pct, 2, '.', '') ?>%"></div>
                                    </div>
                                    <div class="chart-value"><?= (int)$d['cnt'] ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="analysis-panel panel">
                        <div class="analysis-head">
                            <h3>
                                <svg class="icon" aria-hidden="true">
                                    <use href="#i-chart" />
                                </svg>
                                Status Jadwal
                            </h3>
                            <span class="analysis-hint"><?= $activePercent ?>% aktif</span>
                        </div>

                        <div class="donut-wrap">
                            <div class="donut" role="img" aria-label="Diagram donat status jadwal">
                                <svg class="donut-svg" viewBox="0 0 140 140">
                                    <circle class="donut-track" cx="70" cy="70" r="<?= $radius ?>" />
                                    <circle class="donut-fill" cx="70" cy="70" r="<?= $radius ?>"
                                        stroke-dasharray="<?= number_format($activeDash, 2, '.', '') ?> <?= number_format($circumference, 2, '.', '') ?>" />
                                </svg>
                                <div class="donut-center">
                                    <div class="donut-value"><?= number_format($stats['total'], 0, ',', '.') ?></div>
                                    <div class="donut-label">Total</div>
                                </div>
                            </div>

                            <div class="donut-legend">
                                <div class="legend-item">
                                    <span class="legend-dot legend-dot--green"></span>
                                    <span class="legend-text">Aktif</span>
                                    <span class="legend-value"><?= number_format($stats['aktif'], 0, ',', '.') ?></span>
                                </div>
                                <div class="legend-item">
                                    <span class="legend-dot legend-dot--red"></span>
                                    <span class="legend-text">Nonaktif</span>
                                    <span class="legend-value"><?= number_format($stats['nonaktif'], 0, ',', '.') ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Tabel -->
                <section class="table-panel panel">
                    <div class="toolbar">
                        <div class="toolbar-title">
                            <svg class="icon" aria-hidden="true">
                                <use href="#i-calendar" />
                            </svg>
                            Daftar Jadwal Kuliah
                        </div>

                        <form class="toolbar-form" method="get" action="jadwal.php">
                            <div class="search-wrap">
                                <svg class="icon" aria-hidden="true">
                                    <use href="#i-search" />
                                </svg>
                                <input type="text" name="q" class="form-control"
                                    placeholder="Cari mata kuliah atau dosen…"
                                    value="<?= e($search) ?>">
                            </div>

                            <select name="hari" class="form-control filter-select">
                                <option value="">Semua Hari</option>
                                <?php foreach ($DAYS as $key => $label): ?>
                                    <option value="<?= e($key) ?>" <?= $filterDay === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>

                            <select name="kelas" class="form-control filter-select">
                                <option value="">Semua Kelas</option>
                                <?php foreach ($classList as $c): ?>
                                    <option value="<?= e($c) ?>" <?= $filterClass === $c ? 'selected' : '' ?>><?= e($c) ?></option>
                                <?php endforeach; ?>
                            </select>

                            <select name="status" class="form-control filter-select">
                                <option value="">Semua Status</option>
                                <option value="aktif" <?= $filterStatus === 'aktif'    ? 'selected' : '' ?>>Aktif</option>
                                <option value="nonaktif" <?= $filterStatus === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
                            </select>

                            <div class="form-actions">
                                <button type="submit" class="btn">
                                    <svg class="icon" aria-hidden="true">
                                        <use href="#i-search" />
                                    </svg>
                                    Filter
                                </button>

                                <?php if ($search !== '' || $filterDay !== '' || $filterClass !== '' || $filterStatus !== ''): ?>
                                    <a class="btn" href="jadwal.php">Reset</a>
                                <?php endif; ?>

                                <button type="button" class="btn btn-primary btn-add" onclick="openModal('create')">
                                    <svg class="icon" aria-hidden="true">
                                        <use href="#i-plus" />
                                    </svg>
                                    Tambah
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th style="width: 30%;">Mata Kuliah</th>
                                    <th>Kelas</th>
                                    <th>Hari</th>
                                    <th>Jam</th>
                                    <th>Batas Telat</th>
                                    <th>Kode</th>
                                    <th>Status</th>
                                    <th style="width: 110px; text-align: right;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($jadwalList)): ?>
                                    <?php foreach ($jadwalList as $j):
                                        $dayLabel = $DAYS[$j['day_of_week']] ?? ucfirst((string)$j['day_of_week']);
                                    ?>
                                        <tr>
                                            <td>
                                                <div class="user-name"><?= e($j['course_name']) ?></div>
                                                <div class="user-nim"><?= e($j['lecturer_name'] ?: 'Dosen belum diisi') ?></div>
                                            </td>
                                            <td>
                                                <span class="badge badge--muted"><?= e($j['class_name'] ?: '—') ?></span>
                                            </td>
                                            <td><?= e($dayLabel) ?></td>
                                            <td><?= e(time_short($j['start_time'])) ?> – <?= e(time_short($j['end_time'])) ?></td>
                                            <td><?= e(time_short($j['late_after']) ?: '—') ?></td>
                                            <td>
                                                <?php if (!empty($j['attendance_code'])): ?>
                                                    <span class="badge badge--blue"><?= e($j['attendance_code']) ?></span>
                                                <?php else: ?>
                                                    <span style="color: var(--text-2);">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ((int)$j['is_active'] === 1): ?>
                                                    <span class="badge badge--green">Aktif</span>
                                                <?php else: ?>
                                                    <span class="badge badge--red">Nonaktif</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="text-align: right;">
                                                <div class="action-group">
                                                    <button type="button" class="btn-icon btn-icon--edit"
                                                        title="Edit"
                                                        data-id="<?= (int)$j['id'] ?>"
                                                        data-course="<?= e($j['course_name']) ?>"
                                                        data-kelas="<?= e($j['class_name']) ?>"
                                                        data-lecturer="<?= e($j['lecturer_name']) ?>"
                                                        data-day="<?= e($j['day_of_week']) ?>"
                                                        data-start="<?= e(time_short($j['start_time'])) ?>"
                                                        data-late="<?= e(time_short($j['late_after'])) ?>"
                                                        data-end="<?= e(time_short($j['end_time'])) ?>"
                                                        data-code="<?= e($j['attendance_code']) ?>"
                                                        data-active="<?= (int)$j['is_active'] ?>"
                                                        onclick="openModal('edit', this.dataset)">
                                                        <svg class="icon" aria-hidden="true">
                                                            <use href="#i-edit" />
                                                        </svg>
                                                    </button>

                                                    <button type="button" class="btn-icon"
                                                        title="Lihat detail"
                                                        data-course="<?= e($j['course_name']) ?>"
                                                        data-kelas="<?= e($j['class_name']) ?>"
                                                        data-lecturer="<?= e($j['lecturer_name']) ?>"
                                                        data-hari="<?= e($dayLabel) ?>"
                                                        data-jam="<?= e(time_short($j['start_time'])) ?> – <?= e(time_short($j['end_time'])) ?>"
                                                        data-late="<?= e(time_short($j['late_after']) ?: '—') ?>"
                                                        data-code="<?= e($j['attendance_code'] ?: '—') ?>"
                                                        data-active="<?= (int)$j['is_active'] ?>"
                                                        onclick="openDetail(this.dataset)">
                                                        <svg class="icon" aria-hidden="true">
                                                            <use href="#i-eye" />
                                                        </svg>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr class="empty-row">
                                        <td colspan="8">
                                            <div class="empty-table">
                                                <svg class="icon" aria-hidden="true">
                                                    <use href="#i-inbox" />
                                                </svg>
                                                <p>Belum ada jadwal kuliah yang cocok dengan filter.</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($totalPages > 1): ?>
                        <nav class="pagination" aria-label="Navigasi halaman">
                            <span class="page-info">Halaman <?= $page ?> dari <?= $totalPages ?> (<?= $totalRows ?> data)</span>

                            <?php
                            $buildQuery = function ($p) use ($search, $filterDay, $filterClass, $filterStatus) {
                                $q = array_filter([
                                    'q' => $search,
                                    'hari' => $filterDay,
                                    'kelas' => $filterClass,
                                    'status' => $filterStatus,
                                    'page' => $p,
                                ], fn($v) => $v !== '' && $v !== null);
                                return 'jadwal.php?' . http_build_query($q);
                            };
                            ?>

                            <a href="<?= e($buildQuery(max(1, $page - 1))) ?>"
                                class="page-link <?= $page <= 1 ? 'is-disabled' : '' ?>">«</a>

                            <?php
                            $startPage = max(1, $page - 2);
                            $endPage   = min($totalPages, $page + 2);

                            if ($startPage > 1): ?>
                                <a href="<?= e($buildQuery(1)) ?>" class="page-link">1</a>
                                <?php if ($startPage > 2): ?><span class="page-link is-disabled">…</span><?php endif; ?>
                            <?php endif;

                            for ($i = $startPage; $i <= $endPage; $i++): ?>
                                <?php if ($i === $page): ?>
                                    <span class="page-link is-active"><?= $i ?></span>
                                <?php else: ?>
                                    <a href="<?= e($buildQuery($i)) ?>" class="page-link"><?= $i ?></a>
                                <?php endif; ?>
                            <?php endfor;

                            if ($endPage < $totalPages): ?>
                                <?php if ($endPage < $totalPages - 1): ?><span class="page-link is-disabled">…</span><?php endif; ?>
                                <a href="<?= e($buildQuery($totalPages)) ?>" class="page-link"><?= $totalPages ?></a>
                            <?php endif; ?>

                            <a href="<?= e($buildQuery(min($totalPages, $page + 1))) ?>"
                                class="page-link <?= $page >= $totalPages ? 'is-disabled' : '' ?>">»</a>
                        </nav>
                    <?php endif; ?>
                </section>

                <footer class="footer">
                    &copy; <?= date('Y') ?> Presensi NFC — Sistem Kehadiran Mahasiswa
                </footer>

            </div>
        </div>
    </div>

    <!-- ======================= FAB (mobile) ======================= -->
    <button type="button" class="fab" onclick="openModal('create')" aria-label="Tambah jadwal kuliah">
        <svg class="icon" aria-hidden="true">
            <use href="#i-plus" />
        </svg>
    </button>

    <!-- ======================= BOTTOM TAB BAR (mobile) ======================= -->
    <nav class="tabbar" aria-label="Navigasi bawah">
        <a class="tabbar-item" href="index2.php">
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

    <!-- ======================= MODAL: TAMBAH / EDIT ======================= -->
    <div class="modal <?= $autoOpenModal ? 'is-open' : '' ?>" id="modalForm">
        <div class="modal-dialog" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">
                    <?= $formAction === 'update' ? 'Edit Jadwal Kuliah' : 'Tambah Jadwal Kuliah' ?>
                </h3>
                <button type="button" class="modal-close" onclick="closeModal()" aria-label="Tutup">
                    <svg class="icon" aria-hidden="true">
                        <use href="#i-x" />
                    </svg>
                </button>
            </div>

            <?php if (!empty($formErrors)): ?>
                <div class="modal-body" style="padding-bottom: 0;">
                    <div class="modal-errors">
                        <ul>
                            <?php foreach ($formErrors as $err): ?>
                                <li><?= e($err) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>

            <form method="post" action="jadwal.php" autocomplete="off">
                <div class="modal-body">
                    <input type="hidden" name="action" id="formAction" value="<?= e($formAction) ?>">
                    <input type="hidden" name="id" id="formId" value="<?= (int)$formData['id'] ?>">

                    <div class="form-group">
                        <label class="form-label" for="f-course">Mata Kuliah</label>
                        <input class="form-control" type="text" name="course_name" id="f-course"
                            required maxlength="100" placeholder="Contoh: Pemrograman Web"
                            value="<?= e($formData['course_name']) ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="f-lecturer">Dosen Pengampu</label>
                        <input class="form-control" type="text" name="lecturer_name" id="f-lecturer"
                            maxlength="100" placeholder="Nama dosen (opsional)"
                            value="<?= e($formData['lecturer_name']) ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="f-kelas">Kelas</label>
                        <input class="form-control" type="text" name="class_name" id="f-kelas" required
                            maxlength="50" list="classOptions" placeholder="Contoh: 10 A1 01"
                            value="<?= e($formData['class_name']) ?>">
                        <datalist id="classOptions">
                            <?php foreach ($classList as $c): ?>
                                <option value="<?= e($c) ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="f-day">Hari</label>
                        <select class="form-control" name="day_of_week" id="f-day" required>
                            <?php foreach ($DAYS as $key => $label): ?>
                                <option value="<?= e($key) ?>" <?= $formData['day_of_week'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="f-start">Jam Mulai</label>
                        <input class="form-control" type="time" name="start_time" id="f-start"
                            required value="<?= e($formData['start_time']) ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="f-late">Batas Telat</label>
                        <input class="form-control" type="time" name="late_after" id="f-late"
                            value="<?= e($formData['late_after']) ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="f-end">Jam Selesai</label>
                        <input class="form-control" type="time" name="end_time" id="f-end"
                            required value="<?= e($formData['end_time']) ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="f-code">
                            Kode Presensi
                            <span style="color: var(--text-2); font-weight: 400; text-transform: none; letter-spacing: 0;">
                                (kosongkan untuk dibuat otomatis)
                            </span>
                        </label>
                        <input class="form-control" type="text" name="attendance_code" id="f-code"
                            maxlength="20" placeholder="Contoh: RB3MTD" style="text-transform: uppercase;"
                            value="<?= e($formData['attendance_code']) ?>">
                    </div>

                    <label class="form-check">
                        <input type="checkbox" name="is_active" id="f-active" value="1"
                            <?= (int)$formData['is_active'] === 1 ? 'checked' : '' ?>>
                        <span class="form-check-text">Jadwal aktif</span>
                    </label>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn" onclick="closeModal()">Batal</button>
                    <button type="submit" class="btn btn-primary">
                        <svg class="icon" aria-hidden="true">
                            <use href="#i-check" />
                        </svg>
                        Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ======================= MODAL: DETAIL JADWAL ======================= -->
    <div class="modal" id="modalDetail">
        <div class="modal-dialog" role="dialog" aria-modal="true" aria-labelledby="detailTitle">
            <div class="modal-header">
                <h3 class="modal-title" id="detailTitle">Detail Jadwal</h3>
                <button type="button" class="modal-close" onclick="closeDetail()" aria-label="Tutup">
                    <svg class="icon" aria-hidden="true">
                        <use href="#i-x" />
                    </svg>
                </button>
            </div>

            <div class="modal-body">
                <div class="form-group">
                    <div class="form-label">Mata Kuliah</div>
                    <div class="user-name" id="d-course">—</div>
                </div>
                <div class="form-group">
                    <div class="form-label">Dosen Pengampu</div>
                    <div class="user-nim" id="d-lecturer" style="font-size: 0.95rem;">—</div>
                </div>
                <div class="form-group">
                    <div class="form-label">Kelas</div>
                    <div class="user-nim" id="d-kelas" style="font-size: 0.95rem;">—</div>
                </div>
                <div class="form-group">
                    <div class="form-label">Hari</div>
                    <div class="user-nim" id="d-hari" style="font-size: 0.95rem;">—</div>
                </div>
                <div class="form-group">
                    <div class="form-label">Jam</div>
                    <div class="user-nim" id="d-jam" style="font-size: 0.95rem;">—</div>
                </div>
                <div class="form-group">
                    <div class="form-label">Batas Telat</div>
                    <div class="user-nim" id="d-late" style="font-size: 0.95rem;">—</div>
                </div>
                <div class="form-group">
                    <div class="form-label">Kode Presensi</div>
                    <div class="user-nim" id="d-code" style="font-size: 0.95rem;">—</div>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <div class="form-label">Status</div>
                    <div id="d-status">—</div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeDetail()">Tutup</button>
            </div>
        </div>
    </div>

    <script>
        (function() {
            'use strict';

            var body = document.body;
            var toggle = document.getElementById('menuToggle');
            var tabMenu = document.getElementById('tabMenu');
            var sidebar = document.getElementById('sidebar');
            var backdrop = document.getElementById('backdrop');
            var BREAKPOINT = 1024;

            // ---------- SIDEBAR / DRAWER ----------
            function isOpen() {
                return body.classList.contains('nav-open');
            }

            function setOpen(open) {
                body.classList.toggle('nav-open', open);
                if (toggle) {
                    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                    toggle.setAttribute('aria-label', open ? 'Tutup menu navigasi' : 'Buka menu navigasi');
                }
                if (tabMenu) {
                    tabMenu.setAttribute('aria-expanded', open ? 'true' : 'false');
                }
            }

            if (toggle) {
                toggle.addEventListener('click', function() {
                    setOpen(!isOpen());
                });
            }

            if (tabMenu) {
                tabMenu.addEventListener('click', function() {
                    setOpen(!isOpen());
                });
            }

            if (backdrop) {
                backdrop.addEventListener('click', function() {
                    setOpen(false);
                });
            }

            if (sidebar) {
                sidebar.addEventListener('click', function(e) {
                    if (e.target.closest('a')) setOpen(false);
                });

                sidebar.addEventListener('touchmove', function(e) {
                    var nav = sidebar.querySelector('.nav');
                    if (nav && nav.contains(e.target)) return;
                    e.preventDefault();
                }, {
                    passive: false
                });
            }

            var resizeTimer = null;
            window.addEventListener('resize', function() {
                if (resizeTimer) clearTimeout(resizeTimer);
                resizeTimer = setTimeout(function() {
                    if (window.innerWidth > BREAKPOINT && isOpen()) setOpen(false);
                }, 120);
            });

            // ---------- TOAST ----------
            window.dismissToast = function(toast) {
                if (!toast || toast.classList.contains('is-hiding')) return;
                toast.classList.add('is-hiding');
                setTimeout(function() {
                    if (toast.parentNode) toast.parentNode.removeChild(toast);
                }, 320);
            };

            document.querySelectorAll('.toast[data-auto-dismiss]').forEach(function(toast) {
                var delay = parseInt(toast.getAttribute('data-auto-dismiss'), 10) || 5000;
                setTimeout(function() {
                    window.dismissToast(toast);
                }, delay);
            });

            document.querySelectorAll('.toast-close').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    window.dismissToast(btn.closest('.toast'));
                });
            });

            // ---------- MODAL FORM ----------
            var fCourse = document.getElementById('f-course');
            var fLecturer = document.getElementById('f-lecturer');
            var fKelas = document.getElementById('f-kelas');
            var fDay = document.getElementById('f-day');
            var fStart = document.getElementById('f-start');
            var fLate = document.getElementById('f-late');
            var fEnd = document.getElementById('f-end');
            var fCode = document.getElementById('f-code');
            var fActive = document.getElementById('f-active');

            window.openModal = function(mode, data) {
                var modal = document.getElementById('modalForm');
                var title = document.getElementById('modalTitle');
                var actionField = document.getElementById('formAction');
                var idField = document.getElementById('formId');

                var oldErr = modal.querySelector('.modal-errors');
                if (oldErr && oldErr.closest('.modal-body')) {
                    oldErr.closest('.modal-body').remove();
                }

                if (mode === 'edit' && data) {
                    title.textContent = 'Edit Jadwal Kuliah';
                    actionField.value = 'update';
                    idField.value = data.id || 0;
                    fCourse.value = data.course || '';
                    fLecturer.value = data.lecturer || '';
                    fKelas.value = data.kelas || '';
                    fDay.value = data.day || 'senin';
                    fStart.value = data.start || '';
                    fLate.value = data.late || '';
                    fEnd.value = data.end || '';
                    fCode.value = data.code || '';
                    fActive.checked = String(data.active) === '1';
                } else {
                    title.textContent = 'Tambah Jadwal Kuliah';
                    actionField.value = 'create';
                    idField.value = 0;
                    fCourse.value = '';
                    fLecturer.value = '';
                    fKelas.value = '';
                    fDay.value = 'senin';
                    fStart.value = '';
                    fLate.value = '';
                    fEnd.value = '';
                    fCode.value = '';
                    fActive.checked = true;
                }

                modal.classList.add('is-open');
                body.classList.add('modal-open');
                setTimeout(function() {
                    fCourse.focus();
                }, 60);
            };

            window.closeModal = function() {
                document.getElementById('modalForm').classList.remove('is-open');
                body.classList.remove('modal-open');
            };

            // Isi otomatis "Batas Telat" = jam mulai + 20 menit (masih bisa diubah).
            fStart.addEventListener('change', function() {
                if (fLate.value !== '' || !/^\d{2}:\d{2}$/.test(fStart.value)) return;
                var parts = fStart.value.split(':');
                var mins = Math.min(parseInt(parts[0], 10) * 60 + parseInt(parts[1], 10) + 20, 23 * 60 + 59);
                fLate.value = String(Math.floor(mins / 60)).padStart(2, '0') + ':' + String(mins % 60).padStart(2, '0');
            });

            // ---------- MODAL DETAIL ----------
            window.openDetail = function(data) {
                if (!data) return;
                document.getElementById('d-course').textContent = data.course || '—';
                document.getElementById('d-lecturer').textContent = data.lecturer || 'Belum diisi';
                document.getElementById('d-kelas').textContent = data.kelas || '—';
                document.getElementById('d-hari').textContent = data.hari || '—';
                document.getElementById('d-jam').textContent = data.jam || '—';
                document.getElementById('d-late').textContent = data.late || '—';
                document.getElementById('d-code').textContent = data.code || '—';

                var isActive = String(data.active) === '1';
                document.getElementById('d-status').innerHTML = isActive ?
                    '<span class="badge badge--green">Aktif</span>' :
                    '<span class="badge badge--red">Nonaktif</span>';

                document.getElementById('modalDetail').classList.add('is-open');
                body.classList.add('modal-open');
            };

            window.closeDetail = function() {
                document.getElementById('modalDetail').classList.remove('is-open');
                body.classList.remove('modal-open');
            };

            // Backdrop & Escape menutup modal
            var modalForm = document.getElementById('modalForm');
            var modalDetail = document.getElementById('modalDetail');

            if (modalForm) {
                modalForm.addEventListener('click', function(e) {
                    if (e.target === this) window.closeModal();
                });
            }
            if (modalDetail) {
                modalDetail.addEventListener('click', function(e) {
                    if (e.target === this) window.closeDetail();
                });
            }

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    if (isOpen()) {
                        setOpen(false);
                        if (toggle) toggle.focus();
                        return;
                    }
                    if (modalForm && modalForm.classList.contains('is-open')) window.closeModal();
                    if (modalDetail && modalDetail.classList.contains('is-open')) window.closeDetail();
                }
            });
        })();
    </script>
</body>

</html>
