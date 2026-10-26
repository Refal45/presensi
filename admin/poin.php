<?php

/**
 * poin.php — Manajemen Poin Disiplin + Shield Protection
 * Presensi NFC | PHP Native + MySQLi + Bootstrap 5
 *
 * ATURAN:
 *  - Credit Point 0–100.
 *  - Shield Protection disimpan di weekly_point_balances.shield_points.
 *  - Penalti memakai Shield terlebih dahulu.
 *  - Bonus mengisi Credit Point sampai 100, sisanya menjadi Shield sampai batas.
 *  - Kelebihan bonus di atas batas Shield tidak dikonversi.
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

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf'];

// ============================================================
// 2. KONEKSI DATABASE
// ============================================================
require_once __DIR__ . '/../koneksi.php';
$db = $conn ?? $koneksi ?? null;

if (!($db instanceof mysqli)) {
    http_response_code(500);
    error_log('[poin.php] Koneksi database tidak tersedia.');
    exit('Layanan sementara tidak tersedia. Silakan hubungi administrator sistem.');
}

// ============================================================
// 3. KONFIGURASI & HELPER
// ============================================================
const INITIAL_POINTS     = 100;
const MAX_CREDIT_POINTS  = 100;
const SHIELD_MAX         = 10;

/** Daftar rank, urutan tertinggi ke terendah. */
$RANKS = [
    'legendary' => [
        'name' => 'Legendary',
        'min' => 95,
        'max' => 100,
        'status' => 'Sangat disiplin',
        'headline' => 'Sangat disiplin · Terbaik',
        'desc' => 'Mahasiswa dengan skor tertinggi dan catatan kedisiplinan yang sangat baik.',
        'color' => 'success',
        'icon' => 'bi-shield-fill-check',
        'grad' => 'linear-gradient(135deg,#b8860b,#f5c542)',
    ],
    'epic' => [
        'name' => 'Epic',
        'min' => 85,
        'max' => 94,
        'status' => 'Disiplin',
        'headline' => 'Disiplin',
        'desc' => 'Konsisten mengikuti aturan presensi dengan baik.',
        'color' => 'primary',
        'icon' => 'bi-gem',
        'grad' => 'linear-gradient(135deg,#0b3d6b,#2b8fd8)',
    ],
    'grandmaster' => [
        'name' => 'Grandmaster',
        'min' => 70,
        'max' => 84,
        'status' => 'Cukup baik',
        'headline' => 'Cukup baik',
        'desc' => 'Masih perlu menjaga konsistensi waktu masuk dan keluar.',
        'color' => 'secondary',
        'icon' => 'bi-award-fill',
        'grad' => 'linear-gradient(135deg,#4b5563,#9ca3af)',
    ],
    'master' => [
        'name' => 'Master',
        'min' => 50,
        'max' => 69,
        'status' => 'Perlu ditingkatkan',
        'headline' => 'Perlu ditingkatkan',
        'desc' => 'Mahasiswa perlu meningkatkan kedisiplinan presensi.',
        'color' => 'warning',
        'icon' => 'bi-trophy-fill',
        'grad' => 'linear-gradient(135deg,#9a5b13,#e0a03a)',
    ],
    'warrior' => [
        'name' => 'Warrior',
        'min' => 0,
        'max' => 49,
        'status' => 'Perlu pembinaan',
        'headline' => 'Perlu pembinaan',
        'desc' => 'Perlu evaluasi dan pendampingan untuk memperbaiki kedisiplinan.',
        'color' => 'danger',
        'icon' => 'bi-shield-exclamation',
        'grad' => 'linear-gradient(135deg,#7c1d1d,#d04545)',
    ],
];

function rank_key_for(int $points, array $ranks): string
{
    foreach ($ranks as $key => $r) {
        if ($points >= $r['min']) return $key;
    }
    return array_key_last($ranks);
}

function rank_image(string $key): ?string
{
    $rel = 'assets/rank/' . $key . '.png';
    return is_file(__DIR__ . '/' . $rel) ? $rel : null;
}

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function run_rows(mysqli $db, string $sql, string $types = '', array $params = []): array
{
    $rows = [];
    try {
        $stmt = $db->prepare($sql);
        if (!$stmt) return [];
        if ($types !== '') $stmt->bind_param($types, ...$params);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) $rows[] = $row;
        }
        $stmt->close();
    } catch (Throwable $ex) {
        error_log('[poin.php] ' . $ex->getMessage());
    }
    return $rows;
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
        error_log('[poin.php] ' . $ex->getMessage());
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

function format_minggu(?string $start, ?string $end): string
{
    if (!$start || !$end) return '-';
    $bulan = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $s = strtotime($start);
    $en = strtotime($end);
    if ($s === false || $en === false) return '-';
    return date('j', $s) . ' ' . $bulan[(int)date('n', $s) - 1] . ' – '
        . date('j', $en) . ' ' . $bulan[(int)date('n', $en) - 1] . ' ' . date('Y', $en);
}

function week_start_of(string $date): string
{
    $t = strtotime($date);
    return date('Y-m-d', strtotime('monday this week', $t));
}

/** Ambil/buat saldo mingguan. Harus dipanggil dalam transaksi. */
function ensure_balance(mysqli $db, int $userId, string $weekStart): array
{
    $stmt = $db->prepare("SELECT id, current_points, shield_points
                          FROM weekly_point_balances
                          WHERE user_id=? AND week_start=? FOR UPDATE");
    $stmt->bind_param('is', $userId, $weekStart);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) {
        return [
            'id' => (int)$row['id'],
            'current_points' => (int)$row['current_points'],
            'shield_points' => (int)$row['shield_points'],
        ];
    }

    $weekEnd = date('Y-m-d', strtotime($weekStart . ' +6 days'));
    $init = INITIAL_POINTS;
    $zero = 0;

    $stmt = $db->prepare("INSERT INTO weekly_point_balances
        (user_id, week_start, week_end, initial_points, current_points, shield_points)
        VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('issiii', $userId, $weekStart, $weekEnd, $init, $init, $zero);
    $stmt->execute();
    $newId = (int)$db->insert_id;
    $stmt->close();

    return ['id' => $newId, 'current_points' => $init, 'shield_points' => 0];
}

function set_balance(mysqli $db, int $balanceId, int $points, int $shield): void
{
    $points = max(0, min(MAX_CREDIT_POINTS, $points));
    $shield = max(0, min(SHIELD_MAX, $shield));

    $stmt = $db->prepare("UPDATE weekly_point_balances
                          SET current_points=?, shield_points=?
                          WHERE id=?");
    $stmt->bind_param('iii', $points, $shield, $balanceId);
    $stmt->execute();
    $stmt->close();
}

/**
 * Terapkan perubahan poin ke Credit + Shield.
 * $change negatif = penalti, positif = bonus.
 */
function apply_points_change(int $credit, int $shield, int $change, int $shieldMax): array
{
    $credit = max(0, min(MAX_CREDIT_POINTS, $credit));
    $shield = max(0, min($shieldMax, $shield));

    $out = [
        'credit_before' => $credit,
        'shield_before' => $shield,
        'credit_after' => $credit,
        'shield_after' => $shield,
        'credit_delta' => 0,
        'shield_delta' => 0,
        'shield_used' => 0,
        'remaining_penalty' => 0,
        'unconverted_bonus' => 0,
    ];

    if ($change < 0) {
        $penalty = abs($change);
        $shieldUsed = min($shield, $penalty);
        $shieldAfter = $shield - $shieldUsed;
        $remaining = $penalty - $shieldUsed;
        $creditAfter = max(0, $credit - $remaining);

        $out['credit_after'] = $creditAfter;
        $out['shield_after'] = $shieldAfter;
        $out['credit_delta'] = $creditAfter - $credit;
        $out['shield_delta'] = $shieldAfter - $shield;
        $out['shield_used'] = $shieldUsed;
        $out['remaining_penalty'] = $remaining;
    } elseif ($change > 0) {
        $creditAdd = min($change, max(0, MAX_CREDIT_POINTS - $credit));
        $creditAfter = $credit + $creditAdd;
        $remainingBonus = $change - $creditAdd;
        $shieldAdd = min($remainingBonus, max(0, $shieldMax - $shield));
        $shieldAfter = $shield + $shieldAdd;
        $unconverted = $remainingBonus - $shieldAdd;

        $out['credit_after'] = $creditAfter;
        $out['shield_after'] = $shieldAfter;
        $out['credit_delta'] = $creditAfter - $credit;
        $out['shield_delta'] = $shieldAfter - $shield;
        $out['unconverted_bonus'] = $unconverted;
    }

    return $out;
}

/**
 * Balikkan efek transaksi lama.
 * - Bonus: Credit dan Shield dikembalikan.
 * - Penalti: hanya Credit yang dikoreksi; Shield yang sudah dipakai TIDAK dikembalikan.
 */
function reverse_transaction_effect(
    int $credit,
    int $shield,
    int $pointsChange,
    int $creditDelta,
    int $shieldDelta
): array {
    // Fallback transaksi lama sebelum migrasi
    if ($creditDelta === 0 && $shieldDelta === 0 && $pointsChange !== 0) {
        $creditDelta = $pointsChange;
        $shieldDelta = 0;
    }

    $creditAfter = $credit - $creditDelta;
    $shieldAfter = $shield;

    if ($pointsChange > 0) {
        $shieldAfter = $shield - $shieldDelta;
    }

    return [
        max(0, min(MAX_CREDIT_POINTS, $creditAfter)),
        max(0, min(SHIELD_MAX, $shieldAfter)),
    ];
}

function flash_set(string $type, string $title, string $msg): void
{
    $_SESSION['flash']       = $msg;
    $_SESSION['flash_type']  = $type;
    $_SESSION['flash_title'] = $title;
}

// ============================================================
// 4. HANDLE CRUD
// ============================================================
$today = date('Y-m-d');
$currentWeekStart = week_start_of($today);
$formData = [
    'id' => 0,
    'user_id' => 0,
    'tx_date' => $today,
    'type' => 'kurang',
    'amount' => 5,
    'reason' => '',
];
$formErrors = [];
$formAction = 'create';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
        flash_set('error', 'Gagal', 'Token keamanan tidak valid. Silakan muat ulang halaman.');
        redirect_to('poin.php');
    }

    // ---------- DELETE ----------
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        try {
            $db->begin_transaction();
            $stmt = $db->prepare("SELECT pt.id, pt.user_id, pt.points_change, pt.credit_delta, pt.shield_delta,
                                         pt.shield_used, pt.weekly_balance_id,
                                         wpb.current_points, wpb.shield_points, u.name
                                  FROM point_transactions pt
                                  JOIN weekly_point_balances wpb ON wpb.id = pt.weekly_balance_id
                                  JOIN users u ON u.id = pt.user_id
                                  WHERE pt.id=? FOR UPDATE");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $tx = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$tx) {
                $db->rollback();
                flash_set('error', 'Gagal', 'Transaksi poin tidak ditemukan.');
                redirect_to('poin.php');
            }

            [$creditAfter, $shieldAfter] = reverse_transaction_effect(
                (int)$tx['current_points'],
                (int)$tx['shield_points'],
                (int)$tx['points_change'],
                (int)$tx['credit_delta'],
                (int)$tx['shield_delta']
            );

            set_balance($db, (int)$tx['weekly_balance_id'], $creditAfter, $shieldAfter);

            $stmt = $db->prepare("DELETE FROM point_transactions WHERE id=?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();

            $db->commit();

            log_activity(
                $db,
                $adminId,
                'Hapus Poin',
                "Menghapus transaksi poin #{$id} milik {$tx['name']} ({$tx['points_change']} poin). Shield terpakai tidak dikembalikan."
            );
            flash_set('success', 'Data Dihapus', 'Transaksi poin dihapus. Credit Point dikoreksi; Shield yang sudah terpakai tidak dikembalikan.');
        } catch (Throwable $ex) {
            $db->rollback();
            error_log('[poin.php] ' . $ex->getMessage());
            flash_set('error', 'Gagal', 'Transaksi poin gagal dihapus.');
        }
        redirect_to('poin.php');
    }

    // ---------- CREATE / UPDATE ----------
    if ($action === 'create' || $action === 'update') {
        $id      = (int)($_POST['id'] ?? 0);
        $userId  = (int)($_POST['user_id'] ?? 0);
        $txDate  = trim($_POST['tx_date'] ?? '');
        $type    = $_POST['type'] ?? 'kurang';
        $amount  = (int)($_POST['amount'] ?? 0);
        $reason  = trim($_POST['reason'] ?? '');

        if ($txDate === '') $txDate = $today;

        // Saat edit, field mahasiswa & tanggal disabled di UI; ambil nilai asli dari DB.
        if ($action === 'update' && $id > 0) {
            $meta = run_rows(
                $db,
                "SELECT user_id, DATE(created_at) AS tx_date FROM point_transactions WHERE id=?",
                'i',
                [$id]
            )[0] ?? null;
            if ($meta) {
                $userId = (int)$meta['user_id'];
                $txDate = (string)$meta['tx_date'];
            }
        }

        $formData = [
            'id' => $id,
            'user_id' => $userId,
            'tx_date' => $txDate,
            'type' => $type,
            'amount' => $amount,
            'reason' => $reason,
        ];
        $formAction = $action;

        if (!in_array($type, ['kurang', 'tambah'], true)) $formErrors[] = 'Jenis perubahan tidak valid.';
        if ($amount < 1 || $amount > 100)                 $formErrors[] = 'Jumlah poin harus antara 1 sampai 100.';
        if ($reason === '')                               $formErrors[] = 'Alasan wajib diisi.';
        if (function_exists('mb_strlen') ? mb_strlen($reason, 'UTF-8') > 255 : strlen($reason) > 255) {
            $formErrors[] = 'Alasan maksimal 255 karakter.';
        }

        if ($action === 'create') {
            if ($userId <= 0) $formErrors[] = 'Mahasiswa wajib dipilih.';
            $d = DateTime::createFromFormat('Y-m-d', $txDate);
            if (!$d || $d->format('Y-m-d') !== $txDate) $formErrors[] = 'Format tanggal tidak valid.';

            if (empty($formErrors)) {
                $rows = run_rows($db, "SELECT id FROM users WHERE id=? AND role='mahasiswa' AND is_active=1", 'i', [$userId]);
                if (!$rows) $formErrors[] = 'Mahasiswa tidak ditemukan atau tidak aktif.';
            }
        } else {
            if ($id <= 0) $formErrors[] = 'Transaksi tidak valid.';
        }

        if (empty($formErrors)) {
            $change = $type === 'kurang' ? -$amount : $amount;

            try {
                $db->begin_transaction();

                if ($action === 'create') {
                    $ws  = week_start_of($txDate);
                    $bal = ensure_balance($db, $userId, $ws);

                    $res = apply_points_change(
                        (int)$bal['current_points'],
                        (int)$bal['shield_points'],
                        $change,
                        SHIELD_MAX
                    );

                    $createdAt = $txDate . ' ' . date('H:i:s');

                    $stmt = $db->prepare("INSERT INTO point_transactions
                        (user_id, attendance_id, weekly_balance_id, points_change,
                         credit_delta, shield_delta, shield_used, remaining_penalty,
                         reason, changed_by, created_at)
                        VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param(
                        'iiiiiiiisi',
                        $userId,
                        $bal['id'],
                        $change,
                        $res['credit_delta'],
                        $res['shield_delta'],
                        $res['shield_used'],
                        $res['remaining_penalty'],
                        $reason,
                        $adminId,
                        $createdAt
                    );
                    $stmt->execute();
                    $newId = (int)$db->insert_id;
                    $stmt->close();

                    set_balance($db, (int)$bal['id'], (int)$res['credit_after'], (int)$res['shield_after']);
                    $db->commit();

                    $u = run_rows($db, "SELECT name, nim FROM users WHERE id=?", 'i', [$userId])[0] ?? [];
                    log_activity(
                        $db,
                        $adminId,
                        'Tambah Poin',
                        "Transaksi poin #{$newId} untuk " . ($u['name'] ?? '-') . ' (' . ($u['nim'] ?? '-') . ") "
                            . "perubahan={$change}, credit_delta={$res['credit_delta']}, shield_delta={$res['shield_delta']}, "
                            . "shield_used={$res['shield_used']}, sisa_penalti={$res['remaining_penalty']} — {$reason}"
                    );

                    $note = '';
                    if ($type === 'tambah' && (int)$res['unconverted_bonus'] > 0) {
                        $note = ' ' . (int)$res['unconverted_bonus'] . ' poin bonus tidak dikonversi karena batas Shield.';
                    }

                    flash_set(
                        'success',
                        'Data Ditambahkan',
                        'Transaksi poin berhasil dicatat (' . ($change > 0 ? '+' : '') . $change . ' poin).'
                            . ' Credit: ' . (int)$res['credit_after'] . '/100, Shield: ' . (int)$res['shield_after'] . '/' . SHIELD_MAX . '.'
                            . $note
                    );
                    redirect_to('poin.php');
                }

                // UPDATE
                $stmt = $db->prepare("SELECT pt.points_change, pt.credit_delta, pt.shield_delta,
                                             pt.weekly_balance_id, wpb.current_points, wpb.shield_points
                                      FROM point_transactions pt
                                      JOIN weekly_point_balances wpb ON wpb.id = pt.weekly_balance_id
                                      WHERE pt.id=? FOR UPDATE");
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $old = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$old) {
                    $db->rollback();
                    $formErrors[] = 'Transaksi poin tidak ditemukan.';
                } else {
                    [$baseCredit, $baseShield] = reverse_transaction_effect(
                        (int)$old['current_points'],
                        (int)$old['shield_points'],
                        (int)$old['points_change'],
                        (int)$old['credit_delta'],
                        (int)$old['shield_delta']
                    );

                    $res = apply_points_change($baseCredit, $baseShield, $change, SHIELD_MAX);

                    $stmt = $db->prepare("UPDATE point_transactions
                                          SET points_change=?, credit_delta=?, shield_delta=?,
                                              shield_used=?, remaining_penalty=?,
                                              reason=?, changed_by=?
                                          WHERE id=?");
                    $stmt->bind_param(
                        'iiiiiisi',
                        $change,
                        $res['credit_delta'],
                        $res['shield_delta'],
                        $res['shield_used'],
                        $res['remaining_penalty'],
                        $reason,
                        $adminId,
                        $id
                    );
                    $stmt->execute();
                    $stmt->close();

                    set_balance($db, (int)$old['weekly_balance_id'], (int)$res['credit_after'], (int)$res['shield_after']);
                    $db->commit();

                    log_activity(
                        $db,
                        $adminId,
                        'Edit Poin',
                        "Memperbarui transaksi poin #{$id}: perubahan={$change}, credit_delta={$res['credit_delta']}, "
                            . "shield_delta={$res['shield_delta']}, shield_used={$res['shield_used']} — {$reason}"
                    );

                    flash_set(
                        'success',
                        'Data Diperbarui',
                        'Transaksi poin diperbarui. Credit: ' . (int)$res['credit_after'] . '/100, '
                            . 'Shield: ' . (int)$res['shield_after'] . '/' . SHIELD_MAX . '.'
                    );
                    redirect_to('poin.php');
                }
            } catch (Throwable $ex) {
                $db->rollback();
                error_log('[poin.php] ' . $ex->getMessage());
                $formErrors[] = 'Terjadi kesalahan saat menyimpan data.';
            }
        }

        $_SESSION['form_errors'] = $formErrors;
        $_SESSION['form_data']   = $formData;
        $_SESSION['form_action'] = $formAction;
        redirect_to('poin.php?modal=open');
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
$search       = trim($_GET['q'] ?? '');
$filterWeek   = trim($_GET['minggu'] ?? '');
$filterClass  = trim($_GET['kelas'] ?? '');
$filterType   = $_GET['jenis'] ?? '';
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 20;

if ($filterWeek !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterWeek)) $filterWeek = '';
if (!in_array($filterType, ['tambah', 'kurang'], true)) $filterType = '';

$classList = [];
foreach (run_rows($db, "SELECT DISTINCT class_name FROM users WHERE role='mahasiswa' AND class_name IS NOT NULL AND class_name != '' ORDER BY class_name ASC") as $r) {
    $classList[] = $r['class_name'];
}

$weekList = [];
foreach (run_rows($db, "SELECT DISTINCT week_start, week_end FROM weekly_point_balances ORDER BY week_start DESC LIMIT 52") as $r) {
    $weekList[$r['week_start']] = $r['week_end'];
}
if (!isset($weekList[$currentWeekStart])) {
    $weekList[$currentWeekStart] = date('Y-m-d', strtotime($currentWeekStart . ' +6 days'));
    krsort($weekList);
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
if ($filterWeek !== '') {
    $where .= " AND wpb.week_start = ?";
    $params[] = $filterWeek;
    $types .= 's';
}
if ($filterClass !== '') {
    $where .= " AND u.class_name = ?";
    $params[] = $filterClass;
    $types .= 's';
}
if ($filterType === 'tambah') $where .= " AND pt.points_change > 0";
if ($filterType === 'kurang') $where .= " AND pt.points_change < 0";

$fromSql = "FROM point_transactions pt
            JOIN users u ON u.id = pt.user_id
            JOIN weekly_point_balances wpb ON wpb.id = pt.weekly_balance_id";

$totalRows = (int)(run_rows($db, "SELECT COUNT(*) AS c $fromSql $where", $types, $params)[0]['c'] ?? 0);
$totalPages = max(1, (int)ceil($totalRows / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;

$txList = run_rows(
    $db,
    "SELECT pt.id, pt.user_id, pt.attendance_id, pt.points_change,
            pt.credit_delta, pt.shield_delta, pt.shield_used, pt.remaining_penalty,
            pt.reason, pt.created_at,
            u.name AS user_name, u.nim, u.class_name,
            wpb.week_start, wpb.week_end, wpb.initial_points, wpb.current_points,
            wpb.shield_points AS balance_shield,
            a.status AS att_status, a.attendance_date, s.course_name,
            cb.name AS changer_name
     $fromSql
     LEFT JOIN attendances a ON a.id = pt.attendance_id
     LEFT JOIN schedules s ON s.id = a.schedule_id
     LEFT JOIN users cb ON cb.id = pt.changed_by
     $where
     ORDER BY pt.created_at DESC, pt.id DESC
     LIMIT ? OFFSET ?",
    $types . 'ii',
    array_merge($params, [$perPage, $offset])
);

// ============================================================
// 7. STATISTIK, RANK & DIAGRAM
// ============================================================
$sw = run_rows(
    $db,
    "SELECT COUNT(*) AS n,
            COALESCE(ROUND(AVG(current_points)), 0) AS avg_pts,
            COALESCE(SUM(current_points < 50), 0) AS warrior,
            COALESCE(SUM(shield_points), 0) AS shield_total
     FROM weekly_point_balances WHERE week_start = ?",
    's',
    [$currentWeekStart]
)[0] ?? ['n' => 0, 'avg_pts' => 0, 'warrior' => 0, 'shield_total' => 0];

$cutThisWeek = (int)(run_rows(
    $db,
    "SELECT COALESCE(SUM(-pt.points_change), 0) AS c
     FROM point_transactions pt
     JOIN weekly_point_balances w ON w.id = pt.weekly_balance_id
     WHERE w.week_start = ? AND pt.points_change < 0",
    's',
    [$currentWeekStart]
)[0]['c'] ?? 0);

$statCards = [
    ['value' => (int)$sw['n'],       'label' => 'Mahasiswa Minggu Ini', 'icon' => 'bi-people-fill',          'tone' => 'info'],
    ['value' => (int)$sw['avg_pts'], 'label' => 'Rata-rata Poin',       'icon' => 'bi-star-fill',            'tone' => 'success'],
    ['value' => $cutThisWeek,        'label' => 'Poin Terpotong',       'icon' => 'bi-exclamation-triangle-fill', 'tone' => 'warning'],
    ['value' => (int)$sw['warrior'], 'label' => 'Rank Warrior (<50)',   'icon' => 'bi-shield-exclamation',   'tone' => 'danger'],
];

$chartWeek = $filterWeek !== '' ? $filterWeek : $currentWeekStart;
$bWhere  = "WHERE wpb.week_start = ?";
$bParams = [$chartWeek];
$bTypes  = 's';
if ($search !== '') {
    $bWhere .= " AND (u.name LIKE ? OR u.nim LIKE ?)";
    $bParams[] = "%$search%";
    $bParams[] = "%$search%";
    $bTypes .= 'ss';
}
if ($filterClass !== '') {
    $bWhere .= " AND u.class_name = ?";
    $bParams[] = $filterClass;
    $bTypes .= 's';
}
$agg = run_rows(
    $db,
    "SELECT COUNT(*) AS n,
            COALESCE(SUM(wpb.current_points), 0) AS sc,
            COALESCE(SUM(wpb.initial_points), 0) AS si,
            COALESCE(SUM(wpb.shield_points), 0) AS sh,
            COALESCE(SUM(wpb.current_points >= 95), 0) AS legendary,
            COALESCE(SUM(wpb.current_points >= 85 AND wpb.current_points < 95), 0) AS epic,
            COALESCE(SUM(wpb.current_points >= 70 AND wpb.current_points < 85), 0) AS grandmaster,
            COALESCE(SUM(wpb.current_points >= 50 AND wpb.current_points < 70), 0) AS master,
            COALESCE(SUM(wpb.current_points < 50), 0) AS warrior
     FROM weekly_point_balances wpb
     JOIN users u ON u.id = wpb.user_id
     $bWhere",
    $bTypes,
    $bParams
)[0] ?? [];

$rankDist = [];
$maxBucket = 1;
foreach ($RANKS as $k => $_) {
    $rankDist[$k] = (int)($agg[$k] ?? 0);
    if ($rankDist[$k] > $maxBucket) $maxBucket = $rankDist[$k];
}
$totalFiltered = (int)($agg['n'] ?? 0);
$sumCurrent    = (int)($agg['sc'] ?? 0);
$sumInitial    = (int)($agg['si'] ?? 0);
$sumShield     = (int)($agg['sh'] ?? 0);
$lostPoints    = max(0, $sumInitial - $sumCurrent);
$keptPercent   = $sumInitial > 0 ? (int)round(($sumCurrent / $sumInitial) * 100) : 0;

$avgPoints = $totalFiltered > 0 ? (int)round($sumCurrent / $totalFiltered) : INITIAL_POINTS;
$avgPoints = max(0, min(100, $avgPoints));
$currentRankKey = rank_key_for($avgPoints, $RANKS);
$currentRank    = $RANKS[$currentRankKey];

$radius        = 55;
$circumference = 2 * M_PI * $radius;
$keptDash      = $circumference * ($keptPercent / 100);
$chartWeekEnd  = $weekList[$chartWeek] ?? date('Y-m-d', strtotime($chartWeek . ' +6 days'));

// ============================================================
// 8. DATA UNTUK FORM
// ============================================================
$mahasiswaOptions = run_rows($db, "SELECT id, nim, name, class_name FROM users WHERE role='mahasiswa' AND is_active=1 ORDER BY name ASC");

// ============================================================
// 9. MENU SIDEBAR
// ============================================================
$menus = [
    ['link' => 'index2.php',      'icon' => 'bi-grid-1x2-fill',   'label' => 'Dashboard'],
    ['link' => 'mahasiswa.php',   'icon' => 'bi-people-fill',     'label' => 'Data Mahasiswa'],
    ['link' => 'admin.php',       'icon' => 'bi-shield-lock-fill', 'label' => 'Data Admin'],
    ['link' => 'jadwal.php',      'icon' => 'bi-calendar3',       'label' => 'Jadwal Kuliah'],
    ['link' => 'absensi.php',     'icon' => 'bi-clipboard-check-fill', 'label' => 'Data Absensi'],
    ['link' => 'poin.php',        'icon' => 'bi-star-fill',       'label' => 'Poin Disiplin'],
    ['link' => 'izin.php',        'icon' => 'bi-envelope-fill',   'label' => 'Izin & Sakit'],
    ['link' => 'laporan.php',     'icon' => 'bi-pie-chart-fill',  'label' => 'Laporan'],
    ['link' => 'leaderboard.php', 'icon' => 'bi-trophy-fill',     'label' => 'Leaderboard'],
    ['link' => 'pengaturan.php',  'icon' => 'bi-sliders',         'label' => 'Pengaturan'],
    ['link' => 'log.php',         'icon' => 'bi-clock-history',   'label' => 'Log Aktivitas'],
];
$currentPage = 'poin.php';

function rank_tile(string $key, array $rank, int $size = 72): string
{
    $img = rank_image($key);
    $style = 'width:' . $size . 'px;height:' . $size . 'px;';
    if ($img) {
        return '<div class="rank-tile rank-tile--img" style="' . $style . '"><img src="' . e($img) . '" alt="' . e($rank['name']) . '"></div>';
    }
    return '<div class="rank-tile" style="' . $style . 'background:' . $rank['grad'] . ';"><i class="bi ' . e($rank['icon']) . '" style="font-size:' . (int)($size * 0.46) . 'px"></i></div>';
}
?>
<!DOCTYPE html>
<html lang="id" data-bs-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0B1220">
    <meta name="robots" content="noindex, nofollow">
    <title>Poin Disiplin — Presensi NFC</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <style>
        :root {
            --navy: #0B1220;
            --sidebar: #0F1B2D;
            --panel: #12233B;
            --panel-2: #172B45;
            --nav-active: #1D3A5F;
            --cyan: #42E8D5;
            --line: rgba(148, 163, 184, .18);
            --sidebar-w: 260px;
            --bs-body-bg: var(--navy);
            --bs-body-font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
        }

        body {
            background: var(--navy);
            -webkit-font-smoothing: antialiased;
        }

        .card {
            --bs-card-bg: var(--panel);
            --bs-card-border-color: var(--line);
            --bs-card-border-radius: 16px;
            --bs-card-inner-border-radius: 15px;
            box-shadow: 0 8px 24px rgba(2, 8, 20, .35);
        }

        .form-control,
        .form-select {
            background-color: var(--navy);
            border-color: var(--line);
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--cyan);
            box-shadow: 0 0 0 .2rem rgba(66, 232, 213, .15);
            background-color: var(--navy);
        }

        .modal-content {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 16px;
        }

        .modal-header,
        .modal-footer {
            border-color: var(--line);
        }

        .sidebar {
            --bs-offcanvas-bg: var(--sidebar);
            --bs-offcanvas-width: var(--sidebar-w);
            --bs-offcanvas-border-color: var(--line);
        }

        @media (min-width: 992px) {
            .sidebar {
                position: fixed;
                top: 0;
                bottom: 0;
                left: 0;
                width: var(--sidebar-w);
                background: var(--sidebar);
                border-right: 1px solid var(--line);
                display: flex;
                flex-direction: column;
                z-index: 1030;
            }

            .content {
                margin-left: var(--sidebar-w);
            }
        }

        .brand-mark {
            width: 44px;
            height: 44px;
            border: 1.5px solid var(--cyan);
            border-radius: 12px;
            color: var(--cyan);
            background: rgba(66, 232, 213, .08);
            display: grid;
            place-items: center;
            font-size: 22px;
        }

        .brand-tag {
            font-size: 11px;
            letter-spacing: .1em;
            color: var(--cyan);
            font-weight: 600;
        }

        .side-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 14px;
            margin-bottom: 4px;
            border-radius: 10px;
            color: #A8B7CC;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
        }

        .side-link:hover {
            background: var(--panel-2);
            color: #fff;
        }

        .side-link.active {
            background: var(--nav-active);
            color: #fff;
            box-shadow: inset 3px 0 0 var(--cyan);
        }

        .side-link.active i,
        .side-link:hover i {
            color: var(--cyan);
        }

        .topbar {
            position: sticky;
            top: 0;
            z-index: 1020;
            background: rgba(11, 18, 32, .88);
            backdrop-filter: blur(14px);
            border-bottom: 1px solid var(--line);
        }

        .avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            background: linear-gradient(135deg, var(--cyan), #38BDF8);
            color: var(--navy);
            font-weight: 800;
        }

        .rank-tile {
            border-radius: 16px;
            display: grid;
            place-items: center;
            color: #fff;
            flex-shrink: 0;
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, .15), 0 6px 16px rgba(0, 0, 0, .3);
        }

        .rank-tile--img {
            background: rgba(255, 255, 255, .04);
            box-shadow: inset 0 0 0 1px var(--line);
            overflow: hidden;
        }

        .rank-tile--img img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .rank-item {
            padding: 16px 0;
            border-bottom: 1px solid var(--line);
        }

        .rank-item:last-child {
            border-bottom: 0;
            padding-bottom: 0;
        }

        .rank-item:first-child {
            padding-top: 0;
        }

        .rank-item.is-current {
            position: relative;
        }

        .rank-item.is-current .rank-name::after {
            content: "Rank saat ini";
            margin-left: 8px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: uppercase;
            padding: 3px 8px;
            border-radius: 999px;
            background: rgba(66, 232, 213, .15);
            color: var(--cyan);
            vertical-align: middle;
        }

        .rank-points {
            font-size: 3rem;
            line-height: 1;
            font-weight: 800;
        }

        .stat-icon {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            display: grid;
            place-items: center;
            font-size: 24px;
            flex-shrink: 0;
        }

        .table-dark {
            --bs-table-bg: transparent;
            --bs-table-border-color: var(--line);
        }

        .table thead th {
            font-size: 11px;
            letter-spacing: .06em;
            text-transform: uppercase;
            color: #A8B7CC;
            white-space: nowrap;
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            background: linear-gradient(135deg, var(--cyan), #38BDF8);
            color: var(--navy);
            font-weight: 800;
            font-size: 13px;
            flex-shrink: 0;
        }

        .btn-cyan {
            background: linear-gradient(135deg, var(--cyan), #38BDF8);
            border: 0;
            color: var(--navy);
            font-weight: 600;
        }

        .btn-cyan:hover {
            background: linear-gradient(135deg, #38BDF8, var(--cyan));
            color: var(--navy);
        }

        .dist-bar {
            height: 24px;
            background: rgba(15, 27, 45, .7);
        }

        .donut {
            position: relative;
            width: 190px;
            height: 190px;
            margin-inline: auto;
        }

        .donut svg {
            transform: rotate(-90deg);
            width: 100%;
            height: 100%;
        }

        .donut-center {
            position: absolute;
            inset: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .fab {
            position: fixed;
            right: 16px;
            bottom: 20px;
            width: 56px;
            height: 56px;
            border-radius: 50%;
            z-index: 1025;
            font-size: 24px;
            box-shadow: 0 12px 28px rgba(66, 232, 213, .32);
        }

        @media (max-width: 575.98px) {
            .rank-points {
                font-size: 2.5rem;
            }
        }
    </style>
</head>

<body>

    <!-- TOAST -->
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index:2000">
        <?php if (!empty($flash)): ?>
            <div class="toast align-items-center text-bg-<?= $flashType === 'success' ? 'success' : 'danger' ?> border-0"
                role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="6000" id="flashToast">
                <div class="d-flex">
                    <div class="toast-body">
                        <div class="fw-bold"><i class="bi <?= $flashType === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill' ?> me-1"></i><?= e($flashTitle) ?></div>
                        <div class="small"><?= e($flash) ?></div>
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Tutup"></button>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- SIDEBAR -->
    <aside class="sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="sidebar" aria-label="Navigasi utama">
        <div class="d-flex align-items-center gap-3 p-3 border-bottom" style="border-color:var(--line)!important">
            <div class="brand-mark"><i class="bi bi-broadcast"></i></div>
            <div class="flex-grow-1">
                <div class="fw-bold" style="letter-spacing:.04em">NFC - ADMIN</div>
                <div class="brand-tag">HALAMAN ADMIN</div>
            </div>
            <button type="button" class="btn-close d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#sidebar" aria-label="Tutup"></button>
        </div>

        <nav class="flex-grow-1 overflow-auto p-3">
            <?php foreach ($menus as $menu): ?>
                <?php $isActive = ($menu['link'] === $currentPage); ?>
                <a class="side-link<?= $isActive ? ' active' : '' ?>" href="<?= e($menu['link']) ?>" <?= $isActive ? 'aria-current="page"' : '' ?>>
                    <i class="bi <?= e($menu['icon']) ?>"></i>
                    <span><?= e($menu['label']) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="p-3 border-top" style="border-color:var(--line)!important">
            <a class="btn btn-outline-danger w-100" href="logout.php">
                <i class="bi bi-box-arrow-right me-1"></i> Keluar Sistem
            </a>
        </div>
    </aside>

    <div class="content">

        <!-- TOPBAR -->
        <header class="topbar px-3 px-lg-4 py-3 d-flex align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-3 min-w-0">
                <button class="btn btn-outline-secondary d-lg-none" type="button"
                    data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-controls="sidebar" aria-label="Buka menu">
                    <i class="bi bi-list fs-5"></i>
                </button>
                <div>
                    <h1 class="h5 fw-bold mb-0">Poin Disiplin</h1>
                    <div class="small text-secondary d-none d-md-block">Saldo poin mingguan + Shield Protection — perubahan langsung dari database.</div>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <div class="text-end d-none d-sm-block">
                    <div class="fw-semibold small text-truncate" style="max-width:200px" title="<?= e($adminName) ?>"><?= e($adminName) ?></div>
                    <div class="small" style="color:var(--cyan)">Administrator</div>
                </div>
                <div class="avatar" aria-hidden="true"><?= e($adminInitial) ?></div>
            </div>
        </header>

        <main class="container-fluid px-3 px-lg-4 py-4" style="max-width:1440px">

            <!-- STATISTIK -->
            <section class="row g-3 mb-4" aria-label="Ringkasan statistik minggu ini">
                <?php foreach ($statCards as $card): ?>
                    <div class="col-6 col-xl-3">
                        <div class="card h-100">
                            <div class="card-body d-flex align-items-center gap-3">
                                <div class="stat-icon bg-<?= e($card['tone']) ?>-subtle text-<?= e($card['tone']) ?>">
                                    <i class="bi <?= e($card['icon']) ?>"></i>
                                </div>
                                <div class="min-w-0">
                                    <div class="fs-3 fw-bold lh-1"><?= number_format((int)$card['value'], 0, ',', '.') ?></div>
                                    <div class="small text-secondary text-uppercase fw-semibold mt-1" style="font-size:.7rem;letter-spacing:.05em"><?= e($card['label']) ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </section>

            <!-- RANK + SHIELD -->
            <section class="row g-3 mb-4" aria-label="Rank disiplin">
                <div class="col-lg-5">
                    <div class="card h-100">
                        <div class="card-body p-4 d-flex flex-column">
                            <div class="d-flex gap-3 align-items-center">
                                <?= rank_tile($currentRankKey, $currentRank, 96) ?>
                                <div>
                                    <div class="small text-secondary text-uppercase">Rank saat ini</div>
                                    <div class="h4 fw-bold mb-1"><?= e($currentRank['name']) ?></div>
                                    <div class="rank-points"><?= $avgPoints ?></div>
                                    <div class="small fw-semibold text-secondary">/ <?= INITIAL_POINTS ?> poin</div>
                                </div>
                            </div>

                            <div class="progress mt-4" style="height:10px" role="progressbar"
                                aria-valuenow="<?= $avgPoints ?>" aria-valuemin="0" aria-valuemax="100">
                                <div class="progress-bar bg-<?= e($currentRank['color']) ?>" style="width: <?= $avgPoints ?>%"></div>
                            </div>
                            <div class="small text-secondary mt-2">
                                Rata-rata <?= number_format($totalFiltered, 0, ',', '.') ?> mahasiswa · <?= e(format_minggu($chartWeek, $chartWeekEnd)) ?>
                            </div>

                            <hr style="border-color:var(--line)" class="my-4">

                            <div class="d-flex align-items-start justify-content-between gap-3">
                                <div class="d-flex gap-3">
                                    <i class="bi bi-shield-check fs-4 text-success"></i>
                                    <div>
                                        <div class="fw-semibold">Shield Protection</div>
                                        <div class="small text-secondary">Total Shield aktual minggu ini. Maksimum <?= SHIELD_MAX ?> per mahasiswa.</div>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <div class="fs-4 fw-bold"><?= (int)($sw['shield_total'] ?? 0) ?></div>
                                    <div class="small text-secondary">total</div>
                                </div>
                            </div>

                            <hr style="border-color:var(--line)" class="my-4">

                            <div class="d-flex align-items-center justify-content-between mt-auto">
                                <span class="small text-secondary">Status kedisiplinan</span>
                                <span class="badge rounded-pill text-bg-<?= e($currentRank['color']) ?>"><?= e($currentRank['status']) ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-7">
                    <div class="card h-100">
                        <div class="card-header bg-transparent py-3" style="border-color:var(--line)">
                            <h2 class="h6 fw-bold mb-0"><i class="bi bi-star-fill me-2" style="color:var(--cyan)"></i>Tingkatan Rank Disiplin</h2>
                        </div>
                        <div class="card-body">
                            <?php foreach ($RANKS as $key => $r): ?>
                                <div class="rank-item d-flex gap-3 align-items-center <?= $key === $currentRankKey ? 'is-current' : '' ?>">
                                    <?= rank_tile($key, $r, 72) ?>
                                    <div class="min-w-0">
                                        <div class="fw-bold fs-6 rank-name"><?= e($r['name']) ?></div>
                                        <span class="badge rounded-pill text-bg-<?= e($r['color']) ?> mb-1"><?= $r['min'] ?>–<?= $r['max'] ?> poin</span>
                                        <div class="fw-semibold small"><?= e($r['headline']) ?></div>
                                        <div class="small text-secondary"><?= e($r['desc']) ?></div>
                                    </div>
                                    <?php if ($key === 'legendary'): ?>
                                        <span class="badge rounded-pill bg-success-subtle text-success-emphasis ms-auto d-none d-sm-inline-flex align-items-center gap-1">
                                            <i class="bi bi-shield-check"></i> Proteksi
                                        </span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </section>

            <!-- DIAGRAM -->
            <section class="row g-3 mb-4" aria-label="Diagram analisis poin">
                <div class="col-xl-7">
                    <div class="card h-100">
                        <div class="card-header bg-transparent py-3 d-flex flex-wrap justify-content-between gap-2" style="border-color:var(--line)">
                            <h2 class="h6 fw-bold mb-0"><i class="bi bi-bar-chart-fill me-2" style="color:var(--cyan)"></i>Distribusi Rank</h2>
                            <span class="small text-secondary"><?= e(format_minggu($chartWeek, $chartWeekEnd)) ?> · <?= number_format($totalFiltered, 0, ',', '.') ?> mahasiswa</span>
                        </div>
                        <div class="card-body">
                            <?php if ($totalFiltered > 0): ?>
                                <?php foreach ($rankDist as $key => $count):
                                    $r = $RANKS[$key];
                                    $pct = ($count / $maxBucket) * 100;
                                ?>
                                    <div class="d-flex align-items-center gap-3 mb-3">
                                        <div class="small fw-semibold text-secondary text-nowrap" style="width:150px">
                                            <i class="bi <?= e($r['icon']) ?> text-<?= e($r['color']) ?> me-1"></i><?= e($r['name']) ?>
                                            <span class="d-block" style="font-size:.7rem"><?= $r['min'] ?>–<?= $r['max'] ?></span>
                                        </div>
                                        <div class="progress dist-bar flex-grow-1" role="progressbar" aria-valuenow="<?= (int)$count ?>" aria-valuemin="0" aria-valuemax="<?= (int)$maxBucket ?>">
                                            <div class="progress-bar bg-<?= e($r['color']) ?>" style="width: <?= number_format($pct, 2, '.', '') ?>%; min-width:4px"></div>
                                        </div>
                                        <div class="fw-bold text-end" style="width:36px"><?= (int)$count ?></div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center text-secondary py-4">Belum ada saldo poin pada minggu / filter ini.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-xl-5">
                    <div class="card h-100">
                        <div class="card-header bg-transparent py-3 d-flex justify-content-between" style="border-color:var(--line)">
                            <h2 class="h6 fw-bold mb-0"><i class="bi bi-pie-chart-fill me-2" style="color:var(--cyan)"></i>Persentase Poin Tersisa</h2>
                            <span class="small text-secondary"><?= $keptPercent ?>% tersisa</span>
                        </div>
                        <div class="card-body">
                            <div class="donut mb-3" role="img" aria-label="Diagram donat persentase poin tersisa">
                                <svg viewBox="0 0 140 140">
                                    <circle cx="70" cy="70" r="<?= $radius ?>" fill="none" stroke="rgba(248,113,113,.45)" stroke-width="16" />
                                    <circle cx="70" cy="70" r="<?= $radius ?>" fill="none" stroke="#22C55E" stroke-width="16" stroke-linecap="round"
                                        stroke-dasharray="<?= number_format($keptDash, 2, '.', '') ?> <?= number_format($circumference, 2, '.', '') ?>" />
                                </svg>
                                <div class="donut-center">
                                    <div class="fs-2 fw-bold lh-1"><?= $keptPercent ?>%</div>
                                    <div class="small text-secondary text-uppercase">Tersisa</div>
                                </div>
                            </div>
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item bg-transparent d-flex justify-content-between px-0" style="border-color:var(--line)">
                                    <span><i class="bi bi-circle-fill text-success me-2" style="font-size:.6rem"></i>Poin Tersisa</span>
                                    <strong><?= number_format($sumCurrent, 0, ',', '.') ?></strong>
                                </li>
                                <li class="list-group-item bg-transparent d-flex justify-content-between px-0" style="border-color:var(--line)">
                                    <span><i class="bi bi-circle-fill text-danger me-2" style="font-size:.6rem"></i>Poin Terpotong</span>
                                    <strong><?= number_format($lostPoints, 0, ',', '.') ?></strong>
                                </li>
                                <li class="list-group-item bg-transparent d-flex justify-content-between px-0" style="border-color:var(--line)">
                                    <span><i class="bi bi-shield-check text-success me-2"></i>Total Shield</span>
                                    <strong><?= number_format($sumShield, 0, ',', '.') ?></strong>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </section>

            <!-- TABEL -->
            <section class="card mb-4">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <h2 class="h6 fw-bold mb-0"><i class="bi bi-star-fill me-2" style="color:var(--cyan)"></i>Riwayat Transaksi Poin</h2>
                        <button type="button" class="btn btn-cyan d-none d-md-inline-flex align-items-center gap-1" id="btnAdd">
                            <i class="bi bi-plus-lg"></i> Tambah
                        </button>
                    </div>

                    <form class="row g-2 mb-3" method="get" action="poin.php">
                        <div class="col-12 col-lg-4">
                            <div class="input-group">
                                <span class="input-group-text bg-transparent" style="border-color:var(--line)"><i class="bi bi-search"></i></span>
                                <input type="text" name="q" class="form-control" placeholder="Cari nama atau NIM…" value="<?= e($search) ?>">
                            </div>
                        </div>
                        <div class="col-12 col-sm-6 col-lg-3">
                            <select name="minggu" class="form-select" title="Filter minggu">
                                <option value="">Semua Minggu</option>
                                <?php foreach ($weekList as $ws => $we): ?>
                                    <option value="<?= e($ws) ?>" <?= $filterWeek === $ws ? 'selected' : '' ?>>
                                        <?= e(format_minggu($ws, $we)) ?><?= $ws === $currentWeekStart ? ' (Minggu ini)' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6 col-sm-3 col-lg-2">
                            <select name="kelas" class="form-select">
                                <option value="">Semua Kelas</option>
                                <?php foreach ($classList as $c): ?>
                                    <option value="<?= e($c) ?>" <?= $filterClass === $c ? 'selected' : '' ?>><?= e($c) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6 col-sm-3 col-lg-2">
                            <select name="jenis" class="form-select">
                                <option value="">Semua Jenis</option>
                                <option value="kurang" <?= $filterType === 'kurang' ? 'selected' : '' ?>>Pengurangan</option>
                                <option value="tambah" <?= $filterType === 'tambah' ? 'selected' : '' ?>>Penambahan</option>
                            </select>
                        </div>
                        <div class="col-12 col-lg-1 d-flex gap-2">
                            <button type="submit" class="btn btn-secondary flex-fill" title="Terapkan filter"><i class="bi bi-funnel-fill"></i></button>
                            <?php if ($search !== '' || $filterWeek !== '' || $filterClass !== '' || $filterType !== ''): ?>
                                <a class="btn btn-outline-secondary" href="poin.php" title="Reset"><i class="bi bi-x-lg"></i></a>
                            <?php endif; ?>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-dark table-hover align-middle mb-0" style="min-width:980px">
                            <thead>
                                <tr>
                                    <th style="width:22%">Mahasiswa</th>
                                    <th style="width:28%">Alasan</th>
                                    <th>Minggu / Saldo</th>
                                    <th>Waktu</th>
                                    <th>Poin</th>
                                    <th class="text-end">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($txList)): ?>
                                    <?php foreach ($txList as $t):
                                        $initial = function_exists('mb_substr')
                                            ? mb_strtoupper(mb_substr($t['user_name'], 0, 1, 'UTF-8'), 'UTF-8')
                                            : strtoupper(substr($t['user_name'], 0, 1));
                                        $change = (int)$t['points_change'];
                                        $isPlus = $change > 0;
                                        $attTone = match ($t['att_status']) {
                                            'hadir' => 'success',
                                            'terlambat' => 'warning',
                                            'izin' => 'info',
                                            'sakit' => 'primary',
                                            'alpha' => 'danger',
                                            default => 'secondary',
                                        };
                                    ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="user-avatar" aria-hidden="true"><?= e($initial) ?></div>
                                                    <div class="min-w-0">
                                                        <div class="fw-semibold"><?= e($t['user_name']) ?></div>
                                                        <div class="small text-secondary"><?= e($t['nim'] ?: '-') ?><?= $t['class_name'] ? ' · ' . e($t['class_name']) : '' ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="fw-semibold"><?= e($t['reason']) ?></div>
                                                <div class="small text-secondary d-flex flex-wrap gap-2 mt-1">
                                                    <?php if (!empty($t['attendance_id'])): ?>
                                                        <span class="badge text-bg-<?= e($attTone) ?>"><?= e(ucfirst((string)$t['att_status'])) ?></span>
                                                        <span><?= e($t['course_name'] ?: 'Absensi') ?> · <?= e(format_tanggal($t['attendance_date'])) ?></span>
                                                    <?php else: ?>
                                                        <span class="badge text-bg-secondary">Manual</span>
                                                        <span>oleh <?= e($t['changer_name'] ?: 'Sistem') ?></span>
                                                    <?php endif; ?>

                                                    <?php if ((int)$t['shield_used'] > 0): ?>
                                                        <span class="badge text-bg-success"><i class="bi bi-shield-check me-1"></i>Shield −<?= (int)$t['shield_used'] ?></span>
                                                    <?php endif; ?>
                                                    <?php if ((int)$t['remaining_penalty'] > 0): ?>
                                                        <span class="badge text-bg-danger">Credit −<?= (int)$t['remaining_penalty'] ?></span>
                                                    <?php endif; ?>
                                                    <?php if ($isPlus && (int)$t['shield_delta'] > 0): ?>
                                                        <span class="badge text-bg-success"><i class="bi bi-shield-plus me-1"></i>Shield +<?= (int)$t['shield_delta'] ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="small"><i class="bi bi-calendar3 me-1 text-secondary"></i><?= e(format_minggu($t['week_start'], $t['week_end'])) ?></div>
                                                <div class="small text-secondary">
                                                    Saldo: <?= (int)$t['current_points'] ?>/<?= (int)$t['initial_points'] ?>
                                                    · Shield: <?= (int)$t['balance_shield'] ?>/<?= SHIELD_MAX ?>
                                                </div>
                                            </td>
                                            <td class="small text-secondary text-nowrap">
                                                <i class="bi bi-clock me-1"></i><?= e(format_tanggal($t['created_at'])) ?>, <?= e(format_jam($t['created_at'])) ?>
                                            </td>
                                            <td>
                                                <span class="badge fs-6 text-bg-<?= $isPlus ? 'success' : 'danger' ?>">
                                                    <?= $isPlus ? '+' : '−' ?><?= abs($change) ?>
                                                </span>
                                            </td>
                                            <td class="text-end text-nowrap">
                                                <button type="button" class="btn btn-sm btn-outline-info btn-edit" title="Edit"
                                                    data-id="<?= (int)$t['id'] ?>"
                                                    data-user-id="<?= (int)$t['user_id'] ?>"
                                                    data-date="<?= e(substr((string)$t['created_at'], 0, 10)) ?>"
                                                    data-type="<?= $isPlus ? 'tambah' : 'kurang' ?>"
                                                    data-amount="<?= abs($change) ?>"
                                                    data-reason="<?= e($t['reason']) ?>">
                                                    <i class="bi bi-pencil-square"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger btn-delete" title="Hapus"
                                                    data-tx-id="<?= (int)$t['id'] ?>"
                                                    data-tx-name="<?= e($t['user_name'] . ' — ' . ($isPlus ? '+' : '−') . abs($change) . ' poin — ' . $t['reason']) ?>">
                                                    <i class="bi bi-trash3"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-secondary py-5">
                                            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                            Belum ada transaksi poin yang cocok dengan filter.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($totalPages > 1): ?>
                        <?php
                        $buildQuery = function ($p) use ($search, $filterWeek, $filterClass, $filterType) {
                            $q = array_filter([
                                'q' => $search,
                                'minggu' => $filterWeek,
                                'kelas' => $filterClass,
                                'jenis' => $filterType,
                                'page' => $p,
                            ], fn($v) => $v !== '' && $v !== null && $v !== 0);
                            return 'poin.php?' . http_build_query($q);
                        };
                        $startPage = max(1, $page - 2);
                        $endPage   = min($totalPages, $page + 2);
                        ?>
                        <nav class="d-flex flex-column flex-md-row align-items-center justify-content-between gap-2 mt-3" aria-label="Navigasi halaman">
                            <span class="small text-secondary">Halaman <?= $page ?> dari <?= $totalPages ?> (<?= $totalRows ?> data)</span>
                            <ul class="pagination pagination-sm mb-0 flex-wrap justify-content-center">
                                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                    <a class="page-link" href="<?= e($buildQuery(max(1, $page - 1))) ?>" aria-label="Sebelumnya">«</a>
                                </li>
                                <?php if ($startPage > 1): ?>
                                    <li class="page-item"><a class="page-link" href="<?= e($buildQuery(1)) ?>">1</a></li>
                                    <?php if ($startPage > 2): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                                <?php endif; ?>
                                <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                        <a class="page-link" href="<?= e($buildQuery($i)) ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>
                                <?php if ($endPage < $totalPages): ?>
                                    <?php if ($endPage < $totalPages - 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                                    <li class="page-item"><a class="page-link" href="<?= e($buildQuery($totalPages)) ?>"><?= $totalPages ?></a></li>
                                <?php endif; ?>
                                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                    <a class="page-link" href="<?= e($buildQuery(min($totalPages, $page + 1))) ?>" aria-label="Berikutnya">»</a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
            </section>

            <footer class="text-center small text-secondary pb-4">
                &copy; <?= date('Y') ?> Presensi NFC — Sistem Kehadiran Mahasiswa
            </footer>
        </main>
    </div>

    <!-- FAB -->
    <button type="button" class="btn btn-cyan fab d-md-none" id="fabAdd" aria-label="Tambah transaksi poin">
        <i class="bi bi-plus-lg"></i>
    </button>

    <!-- MODAL FORM -->
    <div class="modal fade" id="modalForm" tabindex="-1" aria-labelledby="modalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title fs-5 fw-bold" id="modalTitle">
                        <?= $formAction === 'update' ? 'Edit Transaksi Poin' : 'Tambah Transaksi Poin' ?>
                    </h3>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>

                <form method="post" action="poin.php" autocomplete="off">
                    <div class="modal-body">
                        <?php if (!empty($formErrors)): ?>
                            <div class="alert alert-danger" id="formErrors">
                                <ul class="mb-0 ps-3">
                                    <?php foreach ($formErrors as $err): ?>
                                        <li><?= e($err) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="action" id="formAction" value="<?= e($formAction) ?>">
                        <input type="hidden" name="id" id="formId" value="<?= (int)$formData['id'] ?>">

                        <div class="mb-3">
                            <label class="form-label small text-uppercase fw-semibold text-secondary" for="f-user">Mahasiswa</label>
                            <select class="form-select" name="user_id" id="f-user" required>
                                <option value="">— Pilih Mahasiswa —</option>
                                <?php foreach ($mahasiswaOptions as $m): ?>
                                    <option value="<?= (int)$m['id'] ?>" <?= (int)$formData['user_id'] === (int)$m['id'] ? 'selected' : '' ?>>
                                        <?= e($m['nim'] ?: '-') ?> · <?= e($m['name']) ?><?= $m['class_name'] ? ' · ' . e($m['class_name']) : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-sm-6">
                                <label class="form-label small text-uppercase fw-semibold text-secondary" for="f-date">Tanggal</label>
                                <input class="form-control" type="date" name="tx_date" id="f-date" required value="<?= e($formData['tx_date'] ?: $today) ?>">
                                <div class="form-text" id="weekHint">Menentukan minggu saldo yang terpengaruh.</div>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small text-uppercase fw-semibold text-secondary" for="f-type">Jenis</label>
                                <select class="form-select" name="type" id="f-type" required>
                                    <option value="kurang" <?= $formData['type'] === 'kurang' ? 'selected' : '' ?>>Pengurangan (−)</option>
                                    <option value="tambah" <?= $formData['type'] === 'tambah' ? 'selected' : '' ?>>Penambahan (+)</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small text-uppercase fw-semibold text-secondary" for="f-amount">Jumlah Poin</label>
                            <input class="form-control" type="number" name="amount" id="f-amount" min="1" max="100" step="1" required
                                value="<?= (int)$formData['amount'] ?: '' ?>">
                            <div class="form-text">Perubahan: <span class="badge" id="pointPreview"></span></div>
                        </div>

                        <div class="mb-1">
                            <label class="form-label small text-uppercase fw-semibold text-secondary" for="f-reason">Alasan</label>
                            <input class="form-control" type="text" name="reason" id="f-reason" required maxlength="255" list="reasonList"
                                placeholder="mis. Terlambat masuk kelas" value="<?= e($formData['reason']) ?>">
                            <datalist id="reasonList">
                                <option value="Terlambat masuk kelas">
                                <option value="Tidak hadir tanpa keterangan (alpha)">
                                <option value="Pelanggaran tata tertib">
                                <option value="Bonus kehadiran penuh">
                                <option value="Koreksi poin oleh admin">
                            </datalist>
                            <div class="form-text">
                                Penalti memakai Shield terlebih dahulu. Credit Point 0–100, Shield maksimum <?= SHIELD_MAX ?>.
                                Bonus di atas batas Shield tidak dikonversi.
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-cyan"><i class="bi bi-check-lg me-1"></i>Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL DELETE -->
    <div class="modal fade" id="modalDelete" tabindex="-1" aria-labelledby="delTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title fs-5 fw-bold" id="delTitle">Hapus Transaksi Poin?</h3>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <form method="post" action="poin.php" id="deleteForm">
                    <div class="modal-body">
                        <div class="alert alert-danger d-flex gap-2 mb-0">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            <div>
                                <p class="mb-1">Yakin ingin menghapus <strong id="deleteName">transaksi poin ini</strong>?</p>
                                <p class="small mb-0">Credit Point akan dikoreksi. Shield yang sudah terpakai untuk penalti tidak dikembalikan.</p>
                            </div>
                        </div>
                        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" id="deleteId">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-danger" id="deleteConfirmBtn"><i class="bi bi-trash3 me-1"></i>Ya, Hapus</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        (function() {
            'use strict';

            var BULAN = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
            var TODAY = '<?= e($today) ?>';
            var SHIELD_MAX = <?= (int)SHIELD_MAX ?>;

            var formEl = document.getElementById('modalForm');
            var formModal = bootstrap.Modal.getOrCreateInstance(formEl);
            var delEl = document.getElementById('modalDelete');
            var delModal = bootstrap.Modal.getOrCreateInstance(delEl);

            var titleEl = document.getElementById('modalTitle');
            var actionField = document.getElementById('formAction');
            var idField = document.getElementById('formId');
            var userField = document.getElementById('f-user');
            var dateField = document.getElementById('f-date');
            var typeField = document.getElementById('f-type');
            var amountField = document.getElementById('f-amount');
            var reasonField = document.getElementById('f-reason');
            var pointPreview = document.getElementById('pointPreview');
            var weekHint = document.getElementById('weekHint');

            var flash = document.getElementById('flashToast');
            if (flash) bootstrap.Toast.getOrCreateInstance(flash).show();

            function refreshPointPreview() {
                var amt = parseInt(amountField.value, 10);
                if (!amt || amt < 1) {
                    pointPreview.className = 'badge';
                    pointPreview.textContent = '';
                    return;
                }
                var minus = typeField.value === 'kurang';
                pointPreview.className = 'badge text-bg-' + (minus ? 'danger' : 'success');
                pointPreview.textContent = (minus ? '−' : '+') + amt + ' poin';
            }

            function refreshWeekHint() {
                if (!dateField.value) {
                    weekHint.textContent = 'Menentukan minggu saldo yang terpengaruh.';
                    return;
                }
                var p = dateField.value.split('-');
                var d = new Date(parseInt(p[0], 10), parseInt(p[1], 10) - 1, parseInt(p[2], 10));
                var dow = (d.getDay() + 6) % 7;
                var s = new Date(d);
                s.setDate(d.getDate() - dow);
                var en = new Date(s);
                en.setDate(s.getDate() + 6);
                weekHint.textContent = 'Minggu saldo: ' + s.getDate() + ' ' + BULAN[s.getMonth()] + ' – ' +
                    en.getDate() + ' ' + BULAN[en.getMonth()] + ' ' + en.getFullYear();
            }

            function setEditLock(locked) {
                userField.disabled = locked;
                dateField.disabled = locked;
                userField.required = !locked;
                dateField.required = !locked;
            }

            typeField.addEventListener('change', refreshPointPreview);
            amountField.addEventListener('input', refreshPointPreview);
            dateField.addEventListener('change', refreshWeekHint);

            function clearErrors() {
                var err = document.getElementById('formErrors');
                if (err) err.remove();
            }

            function openCreate() {
                clearErrors();
                titleEl.textContent = 'Tambah Transaksi Poin';
                actionField.value = 'create';
                idField.value = 0;
                userField.value = '';
                dateField.value = TODAY;
                typeField.value = 'kurang';
                amountField.value = 5;
                reasonField.value = '';
                setEditLock(false);
                refreshPointPreview();
                refreshWeekHint();
                formModal.show();
            }

            function openEdit(d) {
                clearErrors();
                titleEl.textContent = 'Edit Transaksi Poin';
                actionField.value = 'update';
                idField.value = d.id || 0;
                userField.value = d.userId || '';
                dateField.value = d.date || '';
                typeField.value = d.type || 'kurang';
                amountField.value = d.amount || '';
                reasonField.value = d.reason || '';
                setEditLock(true);
                refreshPointPreview();
                refreshWeekHint();
                formModal.show();
            }

            formEl.addEventListener('shown.bs.modal', function() {
                (actionField.value === 'update' ? amountField : userField).focus();
            });

            var btnAdd = document.getElementById('btnAdd');
            var fabAdd = document.getElementById('fabAdd');
            if (btnAdd) btnAdd.addEventListener('click', openCreate);
            if (fabAdd) fabAdd.addEventListener('click', openCreate);

            document.addEventListener('click', function(e) {
                var editBtn = e.target.closest('.btn-edit');
                if (editBtn) {
                    openEdit(editBtn.dataset);
                    return;
                }
                var delBtn = e.target.closest('.btn-delete');
                if (delBtn) {
                    document.getElementById('deleteId').value = parseInt(delBtn.getAttribute('data-tx-id'), 10) || 0;
                    document.getElementById('deleteName').textContent = delBtn.getAttribute('data-tx-name') || '';
                    var b = document.getElementById('deleteConfirmBtn');
                    b.disabled = false;
                    b.innerHTML = '<i class="bi bi-trash3 me-1"></i>Ya, Hapus';
                    delModal.show();
                }
            });

            document.getElementById('deleteForm').addEventListener('submit', function() {
                var b = document.getElementById('deleteConfirmBtn');
                b.disabled = true;
                b.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Menghapus…';
            });

            refreshPointPreview();
            refreshWeekHint();
            <?php if ($autoOpenModal): ?>
                if (actionField.value === 'update') setEditLock(true);
                formModal.show();
            <?php endif; ?>
        })();
    </script>
</body>

</html>