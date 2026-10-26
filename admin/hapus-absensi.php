<?php

/**
 * hapus-absensi.php — Handler Hapus Data Absensi
 * Presensi NFC | Sistem Kehadiran Mahasiswa
 * ------------------------------------------------------------
 * Dipanggil dari absensi.php via form POST.
 * Mendukung juga akses langsung: hapus-absensi.php?id=123
 *
 * Menghapus satu baris attendances. Poin terkait di
 * point_transactions otomatis di-set NULL oleh FK.
 */

date_default_timezone_set('Asia/Jakarta');

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

$adminId = (int)$_SESSION['user_id'];

require_once __DIR__ . '/../koneksi.php';
$db = $conn ?? $koneksi ?? null;

if (!($db instanceof mysqli)) {
    http_response_code(500);
    error_log('[hapus-absensi.php] Koneksi database tidak tersedia.');
    exit('Layanan sementara tidak tersedia. Silakan hubungi administrator sistem.');
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
        error_log('[hapus-absensi.php] ' . $ex->getMessage());
    }
}

$id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);

if ($id <= 0) {
    $_SESSION['flash']       = 'ID absensi tidak valid.';
    $_SESSION['flash_type']  = 'error';
    $_SESSION['flash_title'] = 'Gagal Menghapus';
    redirect_to('absensi.php');
}

// Ambil identitas untuk log
$stmt = $db->prepare("
    SELECT a.id, a.attendance_date, a.status, a.user_id,
           u.name AS user_name, u.nim,
           s.course_name
    FROM attendances a
    LEFT JOIN users u ON u.id = a.user_id
    LEFT JOIN schedules s ON s.id = a.schedule_id
    WHERE a.id = ?
");
if (!$stmt) {
    $_SESSION['flash']       = 'Gagal menyiapkan query.';
    $_SESSION['flash_type']  = 'error';
    $_SESSION['flash_title'] = 'Gagal Menghapus';
    redirect_to('absensi.php');
}
$stmt->bind_param('i', $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    $_SESSION['flash']       = 'Data absensi tidak ditemukan.';
    $_SESSION['flash_type']  = 'error';
    $_SESSION['flash_title'] = 'Gagal Menghapus';
    redirect_to('absensi.php');
}

try {
    $stmt = $db->prepare("DELETE FROM attendances WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $deleted = $stmt->affected_rows > 0;
    $stmt->close();

    if ($deleted) {
        $desc = "Menghapus absensi #{$id}: "
            . ($row['user_name'] ?? '-')
            . " (" . ($row['nim'] ?? '-') . ") — "
            . ($row['course_name'] ?? '-')
            . " — {$row['attendance_date']} — status {$row['status']}";
        log_activity($db, $adminId, 'Hapus Absensi', $desc);

        $_SESSION['flash']       = 'Data absensi berhasil dihapus.';
        $_SESSION['flash_type']  = 'success';
        $_SESSION['flash_title'] = 'Data Dihapus';
    } else {
        $_SESSION['flash']       = 'Tidak ada baris yang terhapus. Data mungkin sudah tidak ada.';
        $_SESSION['flash_type']  = 'error';
        $_SESSION['flash_title'] = 'Gagal Menghapus';
    }
} catch (Throwable $ex) {
    error_log('[hapus-absensi.php] Delete failed: ' . $ex->getMessage());
    $_SESSION['flash']       = 'Gagal menghapus: ' . $ex->getMessage();
    $_SESSION['flash_type']  = 'error';
    $_SESSION['flash_title'] = 'Gagal Menghapus';
}

redirect_to('absensi.php');
