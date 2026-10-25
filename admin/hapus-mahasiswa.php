<?php

/**
 * hapus-mahasiswa.php — Handler Hapus Data Mahasiswa
 * Presensi NFC | Sistem Kehadiran Mahasiswa
 * ------------------------------------------------------------
 * Dipanggil dari mahasiswa.php melalui form POST:
 *     <form action="hapus-mahasiswa.php" method="post">
 *         <input type="hidden" name="id" value="123">
 *     </form>
 *
 * Juga mendukung akses langsung (link):
 *     hapus-mahasiswa.php?id=123
 *
 * Proses:
 *  1. Verifikasi session admin.
 *  2. Hapus data terkait dalam satu transaksi (absensi, izin,
 *     poin, saldo poin mingguan, kartu NFC) lalu user.
 *  3. Catat aktivitas ke activity_logs.
 *  4. Redirect kembali ke mahasiswa.php dengan flash message.
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
    error_log('[hapus-mahasiswa.php] Koneksi database tidak tersedia.');
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
        error_log('[hapus-mahasiswa.php] ' . $ex->getMessage());
    }
}

/**
 * Hapus mahasiswa beserta data terkait dalam satu transaksi.
 * Urutan penting karena FK di attendances/leave_requests/point_transactions/
 * weekly_point_balances bersifat RESTRICT (bukan CASCADE).
 */
function hapus_mahasiswa(mysqli $db, int $userId): array
{
    // Ambil identitas dulu
    $stmt = $db->prepare("SELECT name, nim FROM users WHERE id=? AND role='mahasiswa'");
    if (!$stmt) return [false, 'Gagal menyiapkan query.', null];
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return [false, 'Data mahasiswa tidak ditemukan.', null];
    }

    try {
        $db->begin_transaction();

        // 1) point_transactions (referensi attendances & weekly_point_balances)
        $stmt = $db->prepare("DELETE FROM point_transactions WHERE user_id = ?");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();

        // 2) attendances
        $stmt = $db->prepare("DELETE FROM attendances WHERE user_id = ?");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();

        // 3) leave_requests (sebagai pemohon)
        $stmt = $db->prepare("DELETE FROM leave_requests WHERE user_id = ?");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();

        // 3b) leave_requests yang pernah dia review → set NULL
        $stmt = $db->prepare("UPDATE leave_requests SET reviewed_by = NULL WHERE reviewed_by = ?");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();

        // 4) weekly_point_balances
        $stmt = $db->prepare("DELETE FROM weekly_point_balances WHERE user_id = ?");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();

        // 5) user (nfc_cards auto-cascade; activity_logs auto SET NULL)
        $stmt = $db->prepare("DELETE FROM users WHERE id=? AND role='mahasiswa'");
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
        error_log('[hapus-mahasiswa.php] Delete failed: ' . $ex->getMessage());
        return [false, 'Gagal menghapus: ' . $ex->getMessage(), null];
    }
}

// ============================================================
// 4. PROSES HAPUS
// ============================================================
$id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);

if ($id <= 0) {
    $_SESSION['flash']       = 'ID mahasiswa tidak valid.';
    $_SESSION['flash_type']  = 'error';
    $_SESSION['flash_title'] = 'Gagal Menghapus';
    redirect_to('mahasiswa.php');
}

list($ok, $msg, $row) = hapus_mahasiswa($db, $id);

if ($ok && $row) {
    log_activity($db, $adminId, 'Hapus Mahasiswa', "Menghapus mahasiswa: {$row['name']} ({$row['nim']})");
    $_SESSION['flash']       = 'Mahasiswa "' . $row['name'] . '" berhasil dihapus.';
    $_SESSION['flash_type']  = 'success';
    $_SESSION['flash_title'] = 'Data Dihapus';
} else {
    $_SESSION['flash']       = $msg;
    $_SESSION['flash_type']  = 'error';
    $_SESSION['flash_title'] = 'Gagal Menghapus';
}

redirect_to('mahasiswa.php');
