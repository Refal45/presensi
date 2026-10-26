<?php

/**
 * absensi.php — Manajemen Data Absensi
 * Presensi NFC | Sistem Kehadiran Mahasiswa
 * ------------------------------------------------------------
 * CRUD untuk tabel `attendances`.
 *
 * ATURAN OTOMATIS:
 *  - Status hadir/terlambat ditentukan dari jam masuk:
 *      jam_masuk ≤ late_after  →  hadir
 *      jam_masuk > late_after  →  terlambat
 *    (late_after = start_time + toleransi sesuai data jadwal)
 *  - Jam selesai (check_out_at) OTOMATIS = attendance_date + end_time jadwal.
 *    Nilai ini TIDAK dapat diubah manual.
 *  - Status izin/sakit/alpha diisi manual, jam masuk opsional.
 *
 * Aksi HAPUS diproses oleh: hapus-absensi.php
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
    error_log('[absensi.php] Koneksi database tidak tersedia.');
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
        error_log('[absensi.php] ' . $ex->getMessage());
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
        error_log('[absensi.php] ' . $ex->getMessage());
    }
}

function format_tanggal(?string $date): string
{
    if (!$date) return '-';
    $t = strtotime($date);
    if ($t === false) return '-';
    $bulan = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    return date('j', $t) . ' ' . $bulan[(int)date('n', $t) - 1] . ' ' . date('Y', $t);
}

function format_jam(?string $datetime): string
{
    if (!$datetime) return '—';
    $t = strtotime($datetime);
    if ($t === false) return '—';
    return date('H:i', $t);
}

/** Detik dari string HH:MM:SS / HH:MM. */
function time_to_seconds(?string $time): int
{
    if (!$time) return 0;
    $parts = explode(':', $time);
    return ((int)($parts[0] ?? 0)) * 3600
        + ((int)($parts[1] ?? 0)) * 60
        + ((int)($parts[2] ?? 0));
}

/** Ambil start_time, late_after, end_time dari jadwal. */
function get_schedule_info(mysqli $db, int $scheduleId): ?array
{
    $stmt = $db->prepare("SELECT start_time, late_after, end_time FROM schedules WHERE id=?");
    if (!$stmt) return null;
    $stmt->bind_param('i', $scheduleId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

$statusLabels = [
    'hadir'     => 'Hadir',
    'terlambat' => 'Terlambat',
    'izin'      => 'Izin',
    'sakit'     => 'Sakit',
    'alpha'     => 'Alpha',
];
$statusIcons = [
    'hadir'     => 'i-check',
    'terlambat' => 'i-history',
    'izin'      => 'i-mail',
    'sakit'     => 'i-alert',
    'alpha'     => 'i-x-circle',
];

// ============================================================
// 4. HANDLE CRUD (CREATE / UPDATE)
//    Aksi DELETE ditangani oleh hapus-absensi.php
// ============================================================
$today = date('Y-m-d');
$formData = [
    'id' => 0,
    'user_id' => 0,
    'schedule_id' => 0,
    'attendance_date' => $today,
    'check_in_time' => '',   // HH:MM (time only)
    'status' => 'auto',      // auto | izin | sakit | alpha
    'notes' => '',
];
$formErrors = [];
$formAction = 'create';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $id             = (int)($_POST['id'] ?? 0);
        $userId         = (int)($_POST['user_id'] ?? 0);
        $scheduleId     = (int)($_POST['schedule_id'] ?? 0);
        $attendanceDate = trim($_POST['attendance_date'] ?? '');
        if ($attendanceDate === '') {
            $attendanceDate = $today;
        }
        $checkInTime    = trim($_POST['check_in_time'] ?? ''); // HH:MM
        $statusInput    = $_POST['status'] ?? 'auto';
        $notes          = trim($_POST['notes'] ?? '');

        $formData = [
            'id' => $id,
            'user_id' => $userId,
            'schedule_id' => $scheduleId,
            'attendance_date' => $attendanceDate,
            'check_in_time' => $checkInTime,
            'status' => $statusInput,
            'notes' => $notes,
        ];
        $formAction = $action;

        // --- Validasi dasar ---
        if ($userId <= 0)       $formErrors[] = 'Mahasiswa wajib dipilih.';
        if ($scheduleId <= 0)   $formErrors[] = 'Jadwal wajib dipilih.';
        if ($attendanceDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $attendanceDate)) {
            $attendanceDate = $today;
        }
        if (!in_array($statusInput, ['auto', 'izin', 'sakit', 'alpha'], true)) {
            $formErrors[] = 'Status tidak valid.';
        }

        // --- Validasi user ---
        if (empty($formErrors) && $userId > 0) {
            $stmt = $db->prepare("SELECT id FROM users WHERE id=? AND role='mahasiswa' AND is_active=1");
            if ($stmt) {
                $stmt->bind_param('i', $userId);
                $stmt->execute();
                if (!$stmt->get_result()->fetch_assoc()) {
                    $formErrors[] = 'Mahasiswa tidak ditemukan atau tidak aktif.';
                }
                $stmt->close();
            }
        }

        // --- Ambil info jadwal (start, late_after, end) ---
        $schedule = null;
        if (empty($formErrors) && $scheduleId > 0) {
            $schedule = get_schedule_info($db, $scheduleId);
            if (!$schedule) {
                $formErrors[] = 'Jadwal tidak ditemukan.';
            }
        }

        // --- Bangun check_in_at (date + time) ---
        $checkIn = null;
        if ($checkInTime !== '') {
            if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $checkInTime)) {
                $formErrors[] = 'Format jam masuk tidak valid.';
            } else {
                $checkInTimeNormalized = strlen($checkInTime) === 5 ? $checkInTime . ':00' : $checkInTime;
                $checkIn = $attendanceDate . ' ' . $checkInTimeNormalized;
            }
        }

        // --- Tentukan status final & check_out ---
        $finalStatus = null;
        $checkOut    = null;

        if (empty($formErrors) && $schedule) {
            // check_out SELALU = date + end_time (auto, tidak bisa diubah)
            $checkOut = $attendanceDate . ' ' . $schedule['end_time'];

            if ($statusInput === 'auto') {
                if ($checkIn === null) {
                    $formErrors[] = 'Jam masuk wajib diisi untuk status Hadir/Terlambat otomatis.';
                } else {
                    $checkInTimePart = substr($checkIn, 11, 8); // HH:MM:SS
                    if (time_to_seconds($checkInTimePart) <= time_to_seconds($schedule['late_after'])) {
                        $finalStatus = 'hadir';
                    } else {
                        $finalStatus = 'terlambat';
                    }
                }
            } else {
                // izin / sakit / alpha → manual
                $finalStatus = $statusInput;
            }
        }

        // --- Cek duplikat (UNIQUE: user_id + schedule_id + attendance_date) ---
        if (empty($formErrors)) {
            $checkSql = "SELECT id FROM attendances WHERE user_id=? AND schedule_id=? AND attendance_date=?";
            if ($action === 'update') $checkSql .= " AND id != ?";
            $stmt = $db->prepare($checkSql);
            if ($stmt) {
                if ($action === 'update') $stmt->bind_param('iisi', $userId, $scheduleId, $attendanceDate, $id);
                else                      $stmt->bind_param('iis', $userId, $scheduleId, $attendanceDate);
                $stmt->execute();
                if ($stmt->get_result()->fetch_assoc()) {
                    $formErrors[] = 'Sudah ada data absensi untuk mahasiswa ini pada jadwal dan tanggal tersebut.';
                }
                $stmt->close();
            }
        }

        // --- Simpan ---
        if (empty($formErrors)) {
            try {
                if ($action === 'create') {
                    $stmt = $db->prepare("INSERT INTO attendances
                        (user_id, schedule_id, attendance_date, check_in_at, check_out_at, status, notes)
                        VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param('iisssss', $userId, $scheduleId, $attendanceDate, $checkIn, $checkOut, $finalStatus, $notes);
                    if ($stmt->execute()) {
                        $newId = (int)$db->insert_id;

                        $namaStmt = $db->prepare("SELECT name, nim FROM users WHERE id=?");
                        $namaStmt->bind_param('i', $userId);
                        $namaStmt->execute();
                        $u = $namaStmt->get_result()->fetch_assoc();
                        $namaStmt->close();

                        log_activity(
                            $db,
                            $adminId,
                            'Tambah Absensi',
                            "Menambah absensi #{$newId} untuk " . ($u['name'] ?? '-') . " ({$u['nim']}?) pada {$attendanceDate} — status {$finalStatus}"
                        );

                        $_SESSION['flash']       = 'Data absensi berhasil ditambahkan — status: ' . $statusLabels[$finalStatus];
                        $_SESSION['flash_type']  = 'success';
                        $_SESSION['flash_title'] = 'Data Ditambahkan';
                        redirect_to('absensi.php');
                    }
                    $formErrors[] = 'Gagal menyimpan: ' . $stmt->error;
                    $stmt->close();
                } else {
                    $stmt = $db->prepare("UPDATE attendances
                        SET user_id=?, schedule_id=?, attendance_date=?,
                            check_in_at=?, check_out_at=?, status=?, notes=?
                        WHERE id=?");
                    $stmt->bind_param('iisssssi', $userId, $scheduleId, $attendanceDate, $checkIn, $checkOut, $finalStatus, $notes, $id);
                    if ($stmt->execute()) {
                        log_activity(
                            $db,
                            $adminId,
                            'Edit Absensi',
                            "Memperbarui absensi #{$id} pada {$attendanceDate} — status {$finalStatus}"
                        );

                        $_SESSION['flash']       = 'Data absensi berhasil diperbarui — status: ' . $statusLabels[$finalStatus];
                        $_SESSION['flash_type']  = 'success';
                        $_SESSION['flash_title'] = 'Data Diperbarui';
                        redirect_to('absensi.php');
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
        redirect_to('absensi.php?modal=open');
    }
}

// ============================================================
// 5. FLASH & FORM STATE
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
// 6. FILTER & PAGINATION
// ============================================================
$search         = trim($_GET['q'] ?? '');
$filterDate     = trim($_GET['tanggal'] ?? '');
$filterClass    = trim($_GET['kelas'] ?? '');
$filterStatus   = $_GET['status'] ?? '';
$filterSchedule = (int)($_GET['jadwal'] ?? 0);
$page           = max(1, (int)($_GET['page'] ?? 1));
$perPage        = 20;

// Daftar kelas
$classList = [];
$res = $db->query("SELECT DISTINCT class_name FROM users WHERE role='mahasiswa' AND class_name IS NOT NULL AND class_name != '' ORDER BY class_name ASC");
if ($res) {
    while ($r = $res->fetch_assoc()) $classList[] = $r['class_name'];
    $res->free();
}

// Daftar jadwal
$scheduleList = [];
$res = $db->query("SELECT id, course_name, class_name, lecturer_name, day_of_week, start_time, late_after, end_time
                   FROM schedules WHERE is_active=1
                   ORDER BY class_name, day_of_week, start_time");
if ($res) {
    while ($r = $res->fetch_assoc()) $scheduleList[] = $r;
    $res->free();
}

// Peta jadwal untuk JS (start, late_after, end)
$scheduleMap = [];
foreach ($scheduleList as $sc) {
    $scheduleMap[(int)$sc['id']] = [
        'start'    => $sc['start_time'],
        'late'     => $sc['late_after'],
        'end'      => $sc['end_time'],
        'day'      => $sc['day_of_week'],
        'class'    => $sc['class_name'],
        'course'   => $sc['course_name'],
        'lecturer' => $sc['lecturer_name'] ?? '',
    ];
}

$where  = "WHERE 1=1";
$params = [];
$types  = '';

if ($search !== '') {
    $where .= " AND (u.name LIKE ? OR u.nim LIKE ?)";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $types .= 'ss';
}
if ($filterDate !== '') {
    $where .= " AND a.attendance_date = ?";
    $params[] = $filterDate;
    $types .= 's';
}
if ($filterClass !== '') {
    $where .= " AND u.class_name = ?";
    $params[] = $filterClass;
    $types .= 's';
}
if ($filterStatus !== '' && array_key_exists($filterStatus, $statusLabels)) {
    $where .= " AND a.status = ?";
    $params[] = $filterStatus;
    $types .= 's';
}
if ($filterSchedule > 0) {
    $where .= " AND a.schedule_id = ?";
    $params[] = $filterSchedule;
    $types .= 'i';
}

$totalRows = 0;
$stmt = $db->prepare("SELECT COUNT(*)
    FROM attendances a
    JOIN users u ON u.id = a.user_id
    $where");
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

$attendanceList = [];
$dataSql = "SELECT a.id, a.user_id, a.schedule_id, a.attendance_date,
                   a.check_in_at, a.check_out_at, a.status, a.notes,
                   u.name AS user_name, u.nim, u.class_name,
                   s.course_name, s.lecturer_name, s.class_name AS schedule_class,
                   s.day_of_week, s.start_time, s.late_after, s.end_time
            FROM attendances a
            JOIN users u ON u.id = a.user_id
            JOIN schedules s ON s.id = a.schedule_id
            $where
            ORDER BY a.attendance_date DESC, a.check_in_at DESC, a.id DESC
            LIMIT ? OFFSET ?";
$stmt = $db->prepare($dataSql);
if ($stmt) {
    $allTypes  = $types . 'ii';
    $allParams = array_merge($params, [$perPage, $offset]);
    $stmt->bind_param($allTypes, ...$allParams);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) $attendanceList[] = $row;
    $stmt->close();
}

// ============================================================
// 7. STATISTIK & DIAGRAM
// ============================================================
$statsToday = ['total' => 0, 'hadir' => 0, 'terlambat' => 0, 'alpha' => 0];
$stmt = $db->prepare("SELECT
        COUNT(*) AS total,
        COALESCE(SUM(status='hadir'), 0) AS hadir,
        COALESCE(SUM(status='terlambat'), 0) AS terlambat,
        COALESCE(SUM(status='alpha'), 0) AS alpha
    FROM attendances WHERE attendance_date = ?");
if ($stmt) {
    $stmt->bind_param('s', $today);
    $stmt->execute();
    $resRow = $stmt->get_result()->fetch_assoc();
    if ($resRow) {
        $statsToday = [
            'total'     => (int)$resRow['total'],
            'hadir'     => (int)$resRow['hadir'],
            'terlambat' => (int)$resRow['terlambat'],
            'alpha'     => (int)$resRow['alpha'],
        ];
    }
    $stmt->close();
}

$statCards = [
    ['value' => $statsToday['total'],     'label' => 'Absensi Hari Ini',   'icon' => 'i-clipboard', 'tone' => 'blue'],
    ['value' => $statsToday['hadir'],     'label' => 'Hadir Hari Ini',     'icon' => 'i-check',     'tone' => 'green'],
    ['value' => $statsToday['terlambat'], 'label' => 'Terlambat Hari Ini', 'icon' => 'i-history',   'tone' => 'amber'],
    ['value' => $statsToday['alpha'],     'label' => 'Alpha Hari Ini',     'icon' => 'i-x-circle',  'tone' => 'red'],
];

// Distribusi status (mengikuti filter aktif)
$statusDist = ['hadir' => 0, 'terlambat' => 0, 'izin' => 0, 'sakit' => 0, 'alpha' => 0];
$chartSql = "SELECT a.status, COUNT(*) AS cnt
             FROM attendances a
             JOIN users u ON u.id = a.user_id
             $where
             GROUP BY a.status";
$stmt = $db->prepare($chartSql);
if ($stmt) {
    if ($types !== '') $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $st = (string)$r['status'];
        if (isset($statusDist[$st])) $statusDist[$st] = (int)$r['cnt'];
    }
    $stmt->close();
}
$maxStatusCount = 1;
foreach ($statusDist as $c) if ($c > $maxStatusCount) $maxStatusCount = $c;

$presentCount    = $statusDist['hadir'] + $statusDist['terlambat'];
$nonPresentCount = $statusDist['izin'] + $statusDist['sakit'] + $statusDist['alpha'];
$totalFiltered   = $presentCount + $nonPresentCount;
$presentPercent  = $totalFiltered > 0 ? (int)round(($presentCount / $totalFiltered) * 100) : 0;

$radius        = 55;
$circumference = 2 * M_PI * $radius;
$presentDash   = $circumference * ($presentPercent / 100);

// ============================================================
// 8. DATA UNTUK FORM (dropdown mahasiswa)
// ============================================================
$mahasiswaOptions = [];
$res = $db->query("SELECT id, nim, name, class_name
                   FROM users
                   WHERE role='mahasiswa' AND is_active=1
                   ORDER BY name ASC");
if ($res) {
    while ($r = $res->fetch_assoc()) $mahasiswaOptions[] = $r;
    $res->free();
}

$dayLabels = [
    'senin' => 'Senin',
    'selasa' => 'Selasa',
    'rabu' => 'Rabu',
    'kamis' => 'Kamis',
    'jumat' => 'Jumat',
    'sabtu' => 'Sabtu',
    'minggu' => 'Minggu',
];

// ============================================================
// 9. MENU SIDEBAR
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
$currentPage = 'absensi.php';
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
    <title>Data Absensi — Presensi NFC</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
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
            --purple: #A78BFA;
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

        /* LAYOUT */
        .app {
            display: grid;
            grid-template-columns: var(--sidebar-w) minmax(0, 1fr);
            min-height: 100vh;
            min-height: 100dvh;
            background-color: var(--navy);
        }

        /* SIDEBAR */
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

        /* MAIN */
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

        /* TOAST */
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

        /* STATISTIK */
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

        /* ANALYSIS */
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
            border-radius: 6px;
            min-width: 4px;
            transition: width 0.6s ease;
        }

        .chart-fill--hadir {
            background: linear-gradient(90deg, #22C55E, #4ADE80);
        }

        .chart-fill--terlambat {
            background: linear-gradient(90deg, #FBBF24, #FCD34D);
        }

        .chart-fill--izin {
            background: linear-gradient(90deg, #38BDF8, #7DD3FC);
        }

        .chart-fill--sakit {
            background: linear-gradient(90deg, #A78BFA, #C4B5FD);
        }

        .chart-fill--alpha {
            background: linear-gradient(90deg, #F87171, #FCA5A5);
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

        /* TABEL & TOOLBAR */
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

        .form-control:disabled {
            opacity: 0.8;
            cursor: not-allowed;
        }

        select.form-control {
            cursor: pointer;
        }

        .toolbar-form .filter-select {
            flex: 0 1 160px;
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

        .btn-icon:hover:not(:disabled) {
            background: var(--nav-active);
            color: var(--text);
        }

        .btn-icon:disabled {
            opacity: 0.35;
            cursor: not-allowed;
        }

        .btn-icon--edit:hover:not(:disabled) {
            color: var(--cyan);
            border-color: var(--cyan);
        }

        .btn-icon--delete:hover:not(:disabled) {
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
            min-width: 880px;
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

        .cell-schedule {
            min-width: 0;
        }

        .schedule-course {
            font-weight: 600;
            overflow-wrap: anywhere;
        }

        .schedule-meta {
            margin-top: 2px;
            font-size: 12px;
            color: var(--text-2);
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
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
            white-space: nowrap;
        }

        .badge--green {
            background: rgba(34, 197, 94, 0.14);
            color: #6EE7A4;
            border-color: rgba(34, 197, 94, 0.3);
        }

        .badge--amber {
            background: rgba(251, 191, 36, 0.14);
            color: #FCD34D;
            border-color: rgba(251, 191, 36, 0.3);
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

        .badge--purple {
            background: rgba(167, 139, 250, 0.14);
            color: #C4B5FD;
            border-color: rgba(167, 139, 250, 0.3);
        }

        .badge--muted {
            background: rgba(168, 183, 204, 0.1);
            color: var(--text-2);
            border-color: rgba(168, 183, 204, 0.2);
        }

        .badge .icon {
            width: 12px;
            height: 12px;
        }

        .cell-date {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12.5px;
            color: var(--text-2);
            white-space: nowrap;
        }

        .cell-date .icon {
            width: 13px;
            height: 13px;
            opacity: 0.7;
        }

        .cell-time {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12.5px;
            color: var(--text-2);
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
        }

        .cell-time .icon {
            width: 13px;
            height: 13px;
            opacity: 0.7;
        }

        .time-arrow {
            opacity: 0.5;
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

        /* MODAL */
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
            max-width: 560px;
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

        .form-hint {
            font-size: 11.5px;
            color: var(--text-2);
            opacity: 0.85;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        textarea.form-control {
            height: auto;
            min-height: 72px;
            padding: 10px 14px;
            resize: vertical;
            line-height: 1.5;
        }

        /* Schedule info box */
        .schedule-info {
            margin-top: 4px;
            padding: 10px 14px;
            border-radius: var(--radius-sm);
            background: rgba(66, 232, 213, 0.06);
            border: 1px dashed rgba(66, 232, 213, 0.3);
            font-size: 12.5px;
            color: var(--text-2);
            display: none;
            line-height: 1.6;
        }

        .schedule-info.is-visible {
            display: block;
        }

        .schedule-info strong {
            color: var(--cyan);
            font-weight: 700;
        }

        /* Status preview */
        .status-preview-wrap {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 2px;
            font-size: 12.5px;
            color: var(--text-2);
            flex-wrap: wrap;
        }

        .status-preview {
            display: none;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            border: 1px solid transparent;
        }

        .status-preview.is-visible {
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        /* Read-only checkout */
        .checkout-display {
            background: rgba(66, 232, 213, 0.06) !important;
            border-style: dashed !important;
            color: var(--cyan) !important;
            font-weight: 700 !important;
            font-variant-numeric: tabular-nums;
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

        /* FOOTER & FAB */
        .footer {
            padding: 4px 4px 0;
            color: var(--text-2);
            font-size: 12px;
            text-align: center;
        }

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

        /* TAB BAR */
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

        /* RESPONSIVE */
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

            .hero {
                margin-inline: calc(var(--pad) * -1);
                border-radius: 0;
                border-left: 0;
                border-right: 0;
                border-top: 0;
                box-shadow: none;
            }

            .tabbar {
                display: grid;
            }

            .fab {
                display: grid;
                place-items: center;
            }

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

            .toolbar-form .filter-select:nth-child(3),
            .toolbar-form .filter-select:nth-child(4),
            .toolbar-form .filter-select:nth-child(5) {
                grid-column: span 1;
            }

            .toolbar-form .filter-select:nth-child(5) {
                grid-column: 1 / -1;
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

            /* Tabel → Kartu */
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

            .data-table tbody td:nth-child(3),
            .data-table tbody td:nth-child(4),
            .data-table tbody td:nth-child(5) {
                flex: 0 0 auto;
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

            .schedule-course {
                font-size: 14px;
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

            /* Modal bottom sheet */
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

            .form-row {
                grid-template-columns: 1fr;
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
        <symbol id="i-clock" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="10" />
            <polyline points="12 6 12 12 16 14" />
        </symbol>
        <symbol id="i-info" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="10" />
            <line x1="12" y1="16" x2="12" y2="12" />
            <line x1="12" y1="8" x2="12.01" y2="8" />
        </symbol>
        <symbol id="i-lock" viewBox="0 0 24 24">
            <rect x="3" y="11" width="18" height="11" rx="2" />
            <path d="M7 11V7a5 5 0 0 1 10 0v4" />
        </symbol>
    </svg>

    <!-- TOAST -->
    <div class="toast-container" id="toastContainer" aria-live="polite" aria-atomic="true">
        <?php if (!empty($flash)): ?>
            <div class="toast toast--<?= $flashType === 'success' ? 'success' : 'error' ?>"
                role="alert" data-auto-dismiss="5000">
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
        <!-- SIDEBAR -->
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

        <!-- MAIN -->
        <div class="main">

            <header class="topbar panel">
                <div class="topbar-left">
                    <button type="button" class="menu-toggle" id="menuToggle"
                        aria-label="Buka menu navigasi" aria-controls="sidebar" aria-expanded="false">
                        <svg class="icon" aria-hidden="true">
                            <use href="#i-menu" />
                        </svg>
                    </button>
                    <div class="topbar-title">
                        <h2>Data Absensi</h2>
                        <p>Status hadir/terlambat & jam selesai ditentukan otomatis dari jadwal.</p>
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

                <section class="hero" aria-hidden="true">
                    <img class="hero-img" src="background.jpg" alt="" width="1600" height="600"
                        loading="eager" decoding="async">
                </section>

                <!-- Statistik -->
                <section class="stats" aria-label="Ringkasan statistik hari ini">
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

                <!-- Diagram -->
                <section class="analysis-grid" aria-label="Diagram analisis absensi">
                    <div class="analysis-panel panel">
                        <div class="analysis-head">
                            <h3>
                                <svg class="icon" aria-hidden="true">
                                    <use href="#i-bar-chart" />
                                </svg>
                                Distribusi Status (Hasil Filter)
                            </h3>
                            <span class="analysis-hint"><?= number_format($totalFiltered, 0, ',', '.') ?> record</span>
                        </div>

                        <?php if ($totalFiltered > 0): ?>
                            <div class="chart-list">
                                <?php foreach ($statusDist as $statusKey => $count):
                                    $pct = ($count / $maxStatusCount) * 100;
                                ?>
                                    <div class="chart-row">
                                        <div class="chart-label" title="<?= e($statusLabels[$statusKey]) ?>"><?= e($statusLabels[$statusKey]) ?></div>
                                        <div class="chart-track">
                                            <div class="chart-fill chart-fill--<?= e($statusKey) ?>" style="width: <?= number_format($pct, 2, '.', '') ?>%"></div>
                                        </div>
                                        <div class="chart-value"><?= (int)$count ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-chart">Belum ada data absensi pada filter ini.</div>
                        <?php endif; ?>
                    </div>

                    <div class="analysis-panel panel">
                        <div class="analysis-head">
                            <h3>
                                <svg class="icon" aria-hidden="true">
                                    <use href="#i-chart" />
                                </svg>
                                Persentase Kehadiran
                            </h3>
                            <span class="analysis-hint"><?= $presentPercent ?>% hadir</span>
                        </div>

                        <div class="donut-wrap">
                            <div class="donut" role="img" aria-label="Diagram donat persentase kehadiran">
                                <svg class="donut-svg" viewBox="0 0 140 140">
                                    <circle class="donut-track" cx="70" cy="70" r="<?= $radius ?>" />
                                    <circle class="donut-fill" cx="70" cy="70" r="<?= $radius ?>"
                                        stroke-dasharray="<?= number_format($presentDash, 2, '.', '') ?> <?= number_format($circumference, 2, '.', '') ?>" />
                                </svg>
                                <div class="donut-center">
                                    <div class="donut-value"><?= $presentPercent ?>%</div>
                                    <div class="donut-label">Hadir</div>
                                </div>
                            </div>

                            <div class="donut-legend">
                                <div class="legend-item">
                                    <span class="legend-dot legend-dot--green"></span>
                                    <span class="legend-text">Hadir + Terlambat</span>
                                    <span class="legend-value"><?= number_format($presentCount, 0, ',', '.') ?></span>
                                </div>
                                <div class="legend-item">
                                    <span class="legend-dot legend-dot--red"></span>
                                    <span class="legend-text">Izin + Sakit + Alpha</span>
                                    <span class="legend-value"><?= number_format($nonPresentCount, 0, ',', '.') ?></span>
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
                                <use href="#i-clipboard" />
                            </svg>
                            Daftar Absensi
                        </div>

                        <form class="toolbar-form" method="get" action="absensi.php">
                            <div class="search-wrap">
                                <svg class="icon" aria-hidden="true">
                                    <use href="#i-search" />
                                </svg>
                                <input type="text" name="q" class="form-control"
                                    placeholder="Cari nama atau NIM…"
                                    value="<?= e($search) ?>">
                            </div>

                            <input type="date" name="tanggal" class="form-control filter-select"
                                value="<?= e($filterDate) ?>"
                                title="Filter tanggal">

                            <select name="kelas" class="form-control filter-select">
                                <option value="">Semua Kelas</option>
                                <?php foreach ($classList as $c): ?>
                                    <option value="<?= e($c) ?>" <?= $filterClass === $c ? 'selected' : '' ?>><?= e($c) ?></option>
                                <?php endforeach; ?>
                            </select>

                            <select name="status" class="form-control filter-select">
                                <option value="">Semua Status</option>
                                <?php foreach ($statusLabels as $key => $label): ?>
                                    <option value="<?= e($key) ?>" <?= $filterStatus === $key ? 'selected' : '' ?>>
                                        <?= e($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <select name="jadwal" class="form-control filter-select" title="Filter jadwal">
                                <option value="">Semua Jadwal</option>
                                <?php foreach ($scheduleList as $sc): ?>
                                    <option value="<?= (int)$sc['id'] ?>" <?= $filterSchedule === (int)$sc['id'] ? 'selected' : '' ?>>
                                        <?= e($sc['class_name']) ?> · <?= e($dayLabels[$sc['day_of_week']] ?? $sc['day_of_week']) ?> <?= e(substr($sc['start_time'], 0, 5)) ?> · <?= e($sc['course_name']) ?><?= !empty($sc['lecturer_name']) ? ' · ' . e($sc['lecturer_name']) : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <div class="form-actions">
                                <button type="submit" class="btn">
                                    <svg class="icon" aria-hidden="true">
                                        <use href="#i-search" />
                                    </svg>
                                    Filter
                                </button>

                                <?php if ($search !== '' || $filterDate !== '' || $filterClass !== '' || $filterStatus !== '' || $filterSchedule > 0): ?>
                                    <a class="btn" href="absensi.php">Reset</a>
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
                                    <th style="width: 26%;">Mahasiswa</th>
                                    <th style="width: 24%;">Jadwal</th>
                                    <th>Tanggal</th>
                                    <th>Waktu</th>
                                    <th>Status</th>
                                    <th style="width: 90px; text-align: right;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($attendanceList)): ?>
                                    <?php foreach ($attendanceList as $a):
                                        $initial = function_exists('mb_substr')
                                            ? mb_strtoupper(mb_substr($a['user_name'], 0, 1, 'UTF-8'), 'UTF-8')
                                            : strtoupper(substr($a['user_name'], 0, 1));
                                        $statusKey = $a['status'];
                                        $statusLabel = $statusLabels[$statusKey] ?? $statusKey;
                                        $statusIcon = $statusIcons[$statusKey] ?? 'i-info';
                                        $statusTone = match ($statusKey) {
                                            'hadir' => 'green',
                                            'terlambat' => 'amber',
                                            'izin' => 'blue',
                                            'sakit' => 'purple',
                                            'alpha' => 'red',
                                            default => 'muted',
                                        };
                                        $checkInTimePart = $a['check_in_at'] ? substr($a['check_in_at'], 11, 5) : '';
                                    ?>
                                        <tr>
                                            <td>
                                                <div class="cell-user">
                                                    <div class="user-avatar" aria-hidden="true"><?= e($initial) ?></div>
                                                    <div style="min-width:0;">
                                                        <div class="user-name"><?= e($a['user_name']) ?></div>
                                                        <div class="user-nim"><?= e($a['nim'] ?: '-') ?><?= $a['class_name'] ? ' · ' . e($a['class_name']) : '' ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="cell-schedule">
                                                    <div class="schedule-course"><?= e($a['course_name']) ?></div>
                                                    <div class="schedule-meta">
                                                        <span class="badge badge--muted"><?= e($a['schedule_class']) ?></span>
                                                        <span><?= e($a['lecturer_name'] ?: 'Dosen belum diisi') ?></span>
                                                    </div>
                                                    <div class="schedule-meta">
                                                        <span><?= e($dayLabels[$a['day_of_week']] ?? $a['day_of_week']) ?> · <?= e(substr($a['start_time'], 0, 5)) ?>–<?= e(substr($a['end_time'], 0, 5)) ?></span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="cell-date">
                                                    <svg class="icon" aria-hidden="true">
                                                        <use href="#i-calendar" />
                                                    </svg>
                                                    <?= e(format_tanggal($a['attendance_date'])) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="cell-time">
                                                    <svg class="icon" aria-hidden="true">
                                                        <use href="#i-clock" />
                                                    </svg>
                                                    <?= e(format_jam($a['check_in_at'])) ?>
                                                    <span class="time-arrow">→</span>
                                                    <?= e(format_jam($a['check_out_at'])) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge badge--<?= e($statusTone) ?>">
                                                    <svg class="icon" aria-hidden="true">
                                                        <use href="#<?= e($statusIcon) ?>" />
                                                    </svg>
                                                    <?= e($statusLabel) ?>
                                                </span>
                                            </td>
                                            <td style="text-align: right;">
                                                <div class="action-group">
                                                    <button type="button" class="btn-icon btn-icon--edit"
                                                        title="Edit"
                                                        data-id="<?= (int)$a['id'] ?>"
                                                        data-user-id="<?= (int)$a['user_id'] ?>"
                                                        data-schedule-id="<?= (int)$a['schedule_id'] ?>"
                                                        data-date="<?= e($a['attendance_date']) ?>"
                                                        data-checkin-time="<?= e($checkInTimePart) ?>"
                                                        data-status="<?= e($a['status']) ?>"
                                                        data-notes="<?= e((string)$a['notes']) ?>"
                                                        onclick="openModal('edit', this.dataset)">
                                                        <svg class="icon" aria-hidden="true">
                                                            <use href="#i-edit" />
                                                        </svg>
                                                    </button>

                                                    <button type="button" class="btn-icon btn-icon--delete"
                                                        title="Hapus"
                                                        data-attendance-id="<?= (int)$a['id'] ?>"
                                                        data-attendance-name="<?= e($a['user_name'] . ' — ' . format_tanggal($a['attendance_date']) . ' — ' . $a['course_name']) ?>">
                                                        <svg class="icon" aria-hidden="true">
                                                            <use href="#i-trash" />
                                                        </svg>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr class="empty-row">
                                        <td colspan="6">
                                            <div class="empty-table">
                                                <svg class="icon" aria-hidden="true">
                                                    <use href="#i-inbox" />
                                                </svg>
                                                <p>Belum ada data absensi yang cocok dengan filter.</p>
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
                            $buildQuery = function ($p) use ($search, $filterDate, $filterClass, $filterStatus, $filterSchedule) {
                                $q = array_filter([
                                    'q' => $search,
                                    'tanggal' => $filterDate,
                                    'kelas' => $filterClass,
                                    'status' => $filterStatus,
                                    'jadwal' => $filterSchedule > 0 ? $filterSchedule : '',
                                    'page' => $p,
                                ], fn($v) => $v !== '' && $v !== null && $v !== 0);
                                return 'absensi.php?' . http_build_query($q);
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

    <!-- FAB -->
    <button type="button" class="fab" onclick="openModal('create')" aria-label="Tambah absensi">
        <svg class="icon" aria-hidden="true">
            <use href="#i-plus" />
        </svg>
    </button>

    <!-- TAB BAR -->
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
        <a class="tabbar-item is-active" href="absensi.php" aria-current="page">
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

    <!-- MODAL FORM -->
    <div class="modal <?= $autoOpenModal ? 'is-open' : '' ?>" id="modalForm">
        <div class="modal-dialog" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">
                    <?= $formAction === 'update' ? 'Edit Data Absensi' : 'Tambah Data Absensi' ?>
                </h3>
                <button type="button" class="modal-close" onclick="closeModal()" aria-label="Tutup">
                    <svg class="icon" aria-hidden="true">
                        <use href="#i-x" />
                    </svg>
                </button>
            </div>

            <div class="schedule-summary" id="scheduleSummary" style="display:none; margin: 0 1.25rem 0.75rem; padding: 0.75rem 1rem; border-radius: 12px; background: rgba(96, 165, 250, 0.08); border: 1px solid rgba(96, 165, 250, 0.2); color: var(--text-1); font-size: 0.88rem; line-height: 1.5;">
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

            <form method="post" action="absensi.php" autocomplete="off">
                <div class="modal-body">
                    <input type="hidden" name="action" id="formAction" value="<?= e($formAction) ?>">
                    <input type="hidden" name="id" id="formId" value="<?= (int)$formData['id'] ?>">

                    <div class="form-group">
                        <label class="form-label" for="f-user">Mahasiswa</label>
                        <select class="form-control" name="user_id" id="f-user" required>
                            <option value="">— Pilih Mahasiswa —</option>
                            <?php foreach ($mahasiswaOptions as $m): ?>
                                <option value="<?= (int)$m['id'] ?>" <?= (int)$formData['user_id'] === (int)$m['id'] ? 'selected' : '' ?>>
                                    <?= e($m['nim'] ?: '-') ?> · <?= e($m['name']) ?><?= $m['class_name'] ? ' · ' . e($m['class_name']) : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="f-schedule">Jadwal Kuliah</label>
                        <select class="form-control" name="schedule_id" id="f-schedule" required>
                            <option value="">— Pilih Jadwal —</option>
                            <?php foreach ($scheduleList as $sc): ?>
                                <option value="<?= (int)$sc['id'] ?>" <?= (int)$formData['schedule_id'] === (int)$sc['id'] ? 'selected' : '' ?>>
                                    <?= e($sc['class_name']) ?> · <?= e($dayLabels[$sc['day_of_week']] ?? $sc['day_of_week']) ?> <?= e(substr($sc['start_time'], 0, 5)) ?> · <?= e($sc['course_name']) ?><?= !empty($sc['lecturer_name']) ? ' · ' . e($sc['lecturer_name']) : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="schedule-info" id="scheduleInfo"></div>
                    </div>

                    <input type="hidden" name="attendance_date" id="f-date"
                        value="<?= e($formData['attendance_date'] ?: $today) ?>">

                    <div class="form-row">
                        <div class="form-group" style="flex: 1 1 100%;">
                            <label class="form-label" for="f-checkin">Jam Masuk</label>
                            <input class="form-control" type="time" name="check_in_time" id="f-checkin"
                                value="<?= e($formData['check_in_time']) ?>">
                            <div class="form-hint" id="checkinHint">Wajib untuk status Hadir/Terlambat otomatis.</div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="f-status">Status</label>
                        <select class="form-control" name="status" id="f-status" required>
                            <option value="auto" <?= $formData['status'] === 'auto' ? 'selected' : '' ?>>
                                Otomatis (Hadir / Terlambat dari jam masuk)
                            </option>
                            <option value="izin" <?= $formData['status'] === 'izin'  ? 'selected' : '' ?>>Izin</option>
                            <option value="sakit" <?= $formData['status'] === 'sakit' ? 'selected' : '' ?>>Sakit</option>
                            <option value="alpha" <?= $formData['status'] === 'alpha' ? 'selected' : '' ?>>Alpha</option>
                        </select>
                        <div class="status-preview-wrap">
                            <span>Status akhir:</span>
                            <span class="status-preview" id="statusPreview"></span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            Jam Selesai
                            <svg class="icon" aria-hidden="true" style="width:12px;height:12px;display:inline;vertical-align:middle;color:var(--cyan);">
                                <use href="#i-lock" />
                            </svg>
                            Otomatis
                        </label>
                        <input class="form-control checkout-display" type="text" id="f-checkout-display"
                            readonly disabled value="—">
                        <div class="form-hint">
                            Otomatis mengikuti <strong>jam selesai jadwal kuliah</strong> — tidak dapat diubah.
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="f-notes">Catatan</label>
                        <textarea class="form-control" name="notes" id="f-notes"
                            placeholder="Opsional — mis. keterangan izin, sakit, dsb."><?= e((string)$formData['notes']) ?></textarea>
                    </div>
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

    <!-- MODAL DELETE -->
    <div class="modal" id="modalDelete">
        <div class="modal-dialog" role="dialog" aria-modal="true" aria-labelledby="delTitle" style="max-width: 460px;">
            <div class="modal-header">
                <h3 class="modal-title" id="delTitle">Hapus Data Absensi?</h3>
                <button type="button" class="modal-close" onclick="closeDelete()" aria-label="Tutup">
                    <svg class="icon" aria-hidden="true">
                        <use href="#i-x" />
                    </svg>
                </button>
            </div>

            <div class="modal-body">
                <div class="delete-warning">
                    <svg class="icon" aria-hidden="true">
                        <use href="#i-alert" />
                    </svg>
                    <div>
                        <p>Yakin ingin menghapus <strong id="deleteName">data absensi ini</strong>?</p>
                        <p>Tindakan ini tidak dapat dibatalkan. Poin terkait (jika ada) akan di-set NULL.</p>
                    </div>
                </div>
            </div>

            <form method="post" action="hapus-absensi.php" class="modal-footer" id="deleteForm">
                <input type="hidden" name="id" id="deleteId">
                <button type="button" class="btn" onclick="closeDelete()">Batal</button>
                <button type="submit" class="btn btn-danger" id="deleteConfirmBtn">
                    <svg class="icon" aria-hidden="true">
                        <use href="#i-trash" />
                    </svg>
                    <span>Ya, Hapus</span>
                </button>
            </form>
        </div>
    </div>

    <script>
        // Peta jadwal: start, late, end dalam HH:MM:SS
        var SCHEDULE_MAP = <?= json_encode($scheduleMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

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
                if (tabMenu) tabMenu.setAttribute('aria-expanded', open ? 'true' : 'false');
            }

            if (toggle) toggle.addEventListener('click', function() {
                setOpen(!isOpen());
            });
            if (tabMenu) tabMenu.addEventListener('click', function() {
                setOpen(!isOpen());
            });
            if (backdrop) backdrop.addEventListener('click', function() {
                setOpen(false);
            });

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

            // ---------- FORM HELPERS ----------
            var formModal = document.getElementById('modalForm');
            var userField = document.getElementById('f-user');
            var scheduleField = document.getElementById('f-schedule');
            var dateField = document.getElementById('f-date');
            var checkinField = document.getElementById('f-checkin');
            var statusField = document.getElementById('f-status');
            var notesField = document.getElementById('f-notes');
            var checkinHint = document.getElementById('checkinHint');
            var statusPreview = document.getElementById('statusPreview');
            var scheduleInfo = document.getElementById('scheduleInfo');
            var scheduleSummary = document.getElementById('scheduleSummary');
            var checkoutDisp = document.getElementById('f-checkout-display');

            function timeToSec(t) {
                if (!t) return 0;
                var p = t.split(':');
                return (parseInt(p[0] || 0, 10) * 3600) + (parseInt(p[1] || 0, 10) * 60) + (parseInt(p[2] || 0, 10));
            }

            function fmtHM(t) {
                if (!t) return '—';
                return t.substring(0, 5);
            }

            function getScheduleDisplayDate(dayKey) {
                var dayMap = {
                    'senin': 1,
                    'selasa': 2,
                    'rabu': 3,
                    'kamis': 4,
                    'jumat': 5,
                    'sabtu': 6,
                    'minggu': 0
                };
                var targetDay = dayMap[dayKey] !== undefined ? dayMap[dayKey] : 1;
                var now = new Date();
                var currentDay = now.getDay();
                var diff = (targetDay - currentDay + 7) % 7;
                if (diff === 0 && now.getHours() < 0) diff = 7;
                var date = new Date(now);
                date.setDate(now.getDate() + diff);
                var dd = String(date.getDate()).padStart(2, '0');
                var mm = String(date.getMonth() + 1).padStart(2, '0');
                var yyyy = date.getFullYear();
                return dd + '/' + mm + '/' + yyyy;
            }

            function refreshScheduleInfo() {
                var sid = scheduleField.value;
                var info = SCHEDULE_MAP[sid];
                if (!info) {
                    scheduleInfo.classList.remove('is-visible');
                    scheduleInfo.innerHTML = '';
                    if (scheduleSummary) {
                        scheduleSummary.style.display = 'none';
                        scheduleSummary.innerHTML = '';
                    }
                    return;
                }

                var dayLabel = (info.day && (window.dayLabels || {})[info.day]) || info.day || 'Hari';
                var dateText = getScheduleDisplayDate(info.day || 'senin');
                var summaryText = 'Jadwal Kuliah <strong>' + (info.class || '-') + '</strong> · ' + dayLabel + ' ' + dateText + ' ' + fmtHM(info.start) + ' · ' + (info.course || '-') + ' · ' + (info.lecturer || 'Dosen belum diisi');

                scheduleInfo.innerHTML =
                    'Mulai: <strong>' + fmtHM(info.start) + '</strong>' +
                    ' · Batas terlambat: <strong>' + fmtHM(info.late) + '</strong>' +
                    ' · Selesai: <strong>' + fmtHM(info.end) + '</strong>' +
                    (info.lecturer ? ' · Dosen: <strong>' + info.lecturer + '</strong>' : '');
                scheduleInfo.classList.add('is-visible');

                if (scheduleSummary) {
                    scheduleSummary.innerHTML = summaryText;
                    scheduleSummary.style.display = 'block';
                }
            }

            function refreshCheckout() {
                var sid = scheduleField.value;
                var info = SCHEDULE_MAP[sid];
                if (info) {
                    var today = new Date();
                    var yyyy = today.getFullYear();
                    var mm = String(today.getMonth() + 1).padStart(2, '0');
                    var dd = String(today.getDate()).padStart(2, '0');
                    var date = yyyy + '-' + mm + '-' + dd;
                    var timePart = info.end.substring(0, 5);
                    checkoutDisp.value = date + ' ' + timePart;
                } else {
                    checkoutDisp.value = '—';
                }
            }

            function refreshStatusPreview() {
                var st = statusField.value;
                var sid = scheduleField.value;
                var timeVal = checkinField.value; // HH:MM

                if (st !== 'auto') {
                    var label = st.charAt(0).toUpperCase() + st.slice(1);
                    var tone = st === 'izin' ? 'blue' : (st === 'sakit' ? 'purple' : 'red');
                    statusPreview.className = 'status-preview is-visible badge badge--' + tone;
                    statusPreview.textContent = label;
                    return;
                }

                if (!sid || !timeVal) {
                    statusPreview.className = 'status-preview';
                    statusPreview.textContent = '';
                    return;
                }

                var info = SCHEDULE_MAP[sid];
                if (!info) {
                    statusPreview.className = 'status-preview';
                    statusPreview.textContent = '';
                    return;
                }

                var t = timeVal + ':00';
                if (timeToSec(t) <= timeToSec(info.late)) {
                    statusPreview.className = 'status-preview is-visible badge badge--green';
                    statusPreview.textContent = 'Hadir';
                } else {
                    statusPreview.className = 'status-preview is-visible badge badge--amber';
                    statusPreview.textContent = 'Terlambat';
                }
            }

            function refreshCheckinRequirement() {
                var isAuto = (statusField.value === 'auto');
                checkinField.required = isAuto;
                if (isAuto) {
                    checkinHint.textContent = 'Wajib diisi — status hadir/terlambat ditentukan otomatis.';
                    checkinHint.style.color = '';
                } else {
                    checkinHint.textContent = 'Opsional untuk status ' + statusField.value + '.';
                    checkinHint.style.color = '';
                }
            }

            if (scheduleField) scheduleField.addEventListener('change', function() {
                refreshScheduleInfo();
                refreshCheckout();
                refreshStatusPreview();
            });
            if (dateField) dateField.addEventListener('change', function() {
                refreshCheckout();
            });
            if (checkinField) checkinField.addEventListener('input', refreshStatusPreview);
            if (statusField) statusField.addEventListener('change', function() {
                refreshStatusPreview();
                refreshCheckinRequirement();
            });

            // ---------- MODAL FORM ----------
            window.openModal = function(mode, data) {
                var title = document.getElementById('modalTitle');
                var actionField = document.getElementById('formAction');
                var idField = document.getElementById('formId');

                var oldErr = formModal.querySelector('.modal-errors');
                if (oldErr && oldErr.closest('.modal-body')) {
                    oldErr.closest('.modal-body').remove();
                }

                if (mode === 'edit' && data) {
                    title.textContent = 'Edit Data Absensi';
                    actionField.value = 'update';
                    idField.value = data.id || 0;
                    userField.value = data.userId || '';
                    scheduleField.value = data.scheduleId || '';
                    dateField.value = data.date || '';
                    checkinField.value = data.checkinTime || '';
                    notesField.value = data.notes || '';

                    var originalStatus = data.status || 'hadir';
                    if (originalStatus === 'hadir' || originalStatus === 'terlambat') {
                        statusField.value = 'auto';
                    } else {
                        statusField.value = originalStatus;
                    }
                } else {
                    title.textContent = 'Tambah Data Absensi';
                    actionField.value = 'create';
                    idField.value = 0;
                    userField.value = '';
                    scheduleField.value = '';
                    dateField.value = '<?= e($today) ?>';
                    checkinField.value = '';
                    statusField.value = 'auto';
                    notesField.value = '';
                }

                refreshScheduleInfo();
                refreshCheckout();
                refreshStatusPreview();
                refreshCheckinRequirement();

                formModal.classList.add('is-open');
                body.classList.add('modal-open');
                setTimeout(function() {
                    userField.focus();
                }, 60);
            };

            window.closeModal = function() {
                formModal.classList.remove('is-open');
                body.classList.remove('modal-open');
            };

            // ---------- MODAL DELETE ----------
            window.confirmDelete = function(id, name) {
                document.getElementById('deleteId').value = id;
                document.getElementById('deleteName').textContent = name || '';
                document.getElementById('modalDelete').classList.add('is-open');
                body.classList.add('modal-open');

                var btn = document.getElementById('deleteConfirmBtn');
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<svg class="icon" aria-hidden="true"><use href="#i-trash" /></svg> <span>Ya, Hapus</span>';
                }
            };

            window.closeDelete = function() {
                document.getElementById('modalDelete').classList.remove('is-open');
                body.classList.remove('modal-open');

                var btn = document.getElementById('deleteConfirmBtn');
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<svg class="icon" aria-hidden="true"><use href="#i-trash" /></svg> <span>Ya, Hapus</span>';
                }
            };

            document.addEventListener('click', function(e) {
                var delBtn = e.target.closest('.btn-icon--delete[data-attendance-id]');
                if (delBtn && !delBtn.disabled) {
                    var id = parseInt(delBtn.getAttribute('data-attendance-id'), 10) || 0;
                    var name = delBtn.getAttribute('data-attendance-name') || '';
                    window.confirmDelete(id, name);
                }
            });

            var deleteForm = document.getElementById('deleteForm');
            if (deleteForm) {
                deleteForm.addEventListener('submit', function() {
                    var btn = document.getElementById('deleteConfirmBtn');
                    if (btn && !btn.disabled) {
                        btn.disabled = true;
                        btn.innerHTML = '<svg class="icon" style="animation: spin 0.8s linear infinite;"><use href="#i-loader" /></svg> <span>Menghapus…</span>';
                    }
                    setTimeout(function() {
                        if (btn) {
                            btn.disabled = false;
                            btn.innerHTML = '<svg class="icon" aria-hidden="true"><use href="#i-trash" /></svg> <span>Ya, Hapus</span>';
                        }
                    }, 15000);
                });
            }

            var modalDelete = document.getElementById('modalDelete');

            if (formModal) {
                formModal.addEventListener('click', function(e) {
                    if (e.target === this) window.closeModal();
                });
            }
            if (modalDelete) {
                modalDelete.addEventListener('click', function(e) {
                    if (e.target === this) window.closeDelete();
                });
            }

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    if (isOpen()) {
                        setOpen(false);
                        if (toggle) toggle.focus();
                        return;
                    }
                    if (formModal && formModal.classList.contains('is-open')) window.closeModal();
                    if (modalDelete && modalDelete.classList.contains('is-open')) window.closeDelete();
                }
            });
        })();
    </script>
</body>

</html>