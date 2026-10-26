<?php

/**
 * hapus-admin.php — Handler Hapus Data Admin
 * Presensi NFC | Sistem Kehadiran Mahasiswa
 * ------------------------------------------------------------
 * Dipanggil dari admin.php melalui form POST:
 *     <form action="hapus-admin.php" method="post">
 *         <input type="hidden" name="id" value="123">
 *     </form>
 *
 * Juga mendukung akses langsung (link):
 *     hapus-admin.php?id=123
 *
 * Proteksi:
 *  - Tidak dapat menghapus akun sendiri.
 *  - Tidak dapat menghapus admin terakhir.
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

$adminId = (int)$_SESSION['user_id'];

// ============================================================
// 2. KONEKSI DATABASE
// ============================================================
require_once __DIR__ . '/../koneksi.php';
$db = $conn ?? $koneksi ?? null;

if (!($db instanceof mysqli)) {
    http_response_code(500);
    error_log('[hapus-admin.php] Koneksi database tidak tersedia.');
    exit('Layanan sementara tidak tersedia. Silakan hubungi administrator sistem.');
}

// ============================================================
// 3. HELPER
// ============================================================
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
        error_log('[hapus-admin.php] ' . $ex->getMessage());
    }
}

/**
 * Hapus admin beserta data terkait dalam satu transaksi.
 * Admin umumnya tidak memiliki data sebagai user_id di
 * attendances/leave_requests/point_transactions/weekly_point_balances,
 * namun pembersihan tetap dilakukan agar aman jika ada data
 * inkonsisten. Foreign key reviewed_by, changed_by, dan
 * activity_logs.user_id menggunakan ON DELETE SET NULL.
 */
function hapus_admin(mysqli $db, int $userId, int $currentAdminId): array
{
    if ($userId === $currentAdminId) {
        return [false, 'Anda tidak dapat menghapus akun Anda sendiri.', null];
    }

    // Ambil identitas
    $stmt = $db->prepare("SELECT name, email FROM users WHERE id=? AND role='admin'");
    if (!$stmt) return [false, 'Gagal menyiapkan query.', null];
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return [false, 'Data admin tidak ditemukan.', null];
    }

    // Cegah menghapus admin terakhir
    $adminCount = 0;
    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE role='admin'");
    if ($stmt) {
        $stmt->execute();
        $stmt->bind_result($adminCount);
        $stmt->fetch();
        $stmt->close();
    }
    if ($adminCount <= 1) {
        return [false, 'Tidak dapat menghapus satu-satunya akun admin.', null];
    }

    try {
        $db->begin_transaction();

        // Defensive cleanup untuk data terkait (jika ada)
        $stmt = $db->prepare("DELETE FROM point_transactions WHERE user_id = ?");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();

        $stmt = $db->prepare("DELETE FROM attendances WHERE user_id = ?");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();

        $stmt = $db->prepare("DELETE FROM leave_requests WHERE user_id = ?");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();

        $stmt = $db->prepare("DELETE FROM weekly_point_balances WHERE user_id = ?");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();

        // Hapus admin (FK reviewed_by/changed_by/activity_logs.user_id → SET NULL, nfc_cards → CASCADE)
        $stmt = $db->prepare("DELETE FROM users WHERE id=? AND role='admin'");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $deleted = $stmt->affected_rows > 0;
        $stmt->close();

        if (!$deleted) {
            $db->rollback();
            return [false, 'Tidak ada baris yang terhapus. Data mungkin sudah tidak ada.', null];
        }

        $db->commit();
        return [true, '', $row];
    } catch (Throwable $ex) {
        try {
            $db->rollback();
        } catch (Throwable $ignore) {
        }
        error_log('[hapus-admin.php] Delete failed: ' . $ex->getMessage());
        return [false, 'Gagal menghapus: ' . $ex->getMessage(), null];
    }
}

// ============================================================
// 4. PROSES HAPUS
// ============================================================
$id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);

if ($id <= 0) {
    $_SESSION['flash']       = 'ID admin tidak valid.';
    $_SESSION['flash_type']  = 'error';
    $_SESSION['flash_title'] = 'Gagal Menghapus';
    redirect_to('admin.php');
}

list($ok, $msg, $row) = hapus_admin($db, $id, $adminId);

if ($ok && $row) {
    log_activity($db, $adminId, 'Hapus Admin', "Menghapus admin: {$row['name']} ({$row['email']})");
    $_SESSION['flash']       = 'Admin "' . $row['name'] . '" berhasil dihapus.';
    $_SESSION['flash_type']  = 'success';
    $_SESSION['flash_title'] = 'Data Dihapus';
} else {
    $_SESSION['flash']       = $msg;
    $_SESSION['flash_type']  = 'error';
    $_SESSION['flash_title'] = 'Gagal Menghapus';
}

redirect_to('admin.php');
