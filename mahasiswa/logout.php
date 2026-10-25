<?php

/**
 * logout.php — Konfirmasi & Proses Logout
 * Presensi NFC | Sistem Kehadiran Mahasiswa
 * ------------------------------------------------------------
 * Letak ideal: auth/logout.php
 *
 * AUTO-DETECT LOKASI:
 *   - Jika logout.php ada di folder yang sama dengan login.php
 *     (auth/) → redirect ke ./login.php
 *   - Jika logout.php ada di folder lain (mis. mahasiswa/)
 *     → redirect ke ../auth/login.php
 *
 * Dua mode:
 *   1. GET biasa       → tampilkan halaman konfirmasi (SweetAlert)
 *   2. GET ?confirm=1  → proses logout & balas JSON
 */

// ============================================================
// SESSION BOOTSTRAP
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

require_once __DIR__ . '/../koneksi.php';
$db = $conn ?? $koneksi ?? null;

// Cache-control untuk semua respons
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Content-Type-Options: nosniff');

// ============================================================
// AUTO-DETECT LOKASI FILE & HITUNG URL REDIRECT
// ============================================================

$selfDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$baseDir = preg_replace('#/(?:auth|admin|mahasiswa)$#', '', $selfDir) ?: '';

if ($baseDir === '') {
    $baseDir = '/presensi';
}

$loginUrl           = $baseDir . '/auth/login.php';
$dashboardMahasiswa = $selfDir . '/index.php';
$dashboardAdmin     = $baseDir . '/admin/index2.php';

if ($selfDir === '') {
    $loginUrl = '/auth/login.php';
}

// ============================================================
// MODE 1 — ENDPOINT LOGOUT (?confirm=1)
// ============================================================
if (isset($_GET['confirm']) && $_GET['confirm'] === '1') {
    header('Content-Type: application/json; charset=utf-8');

    $wasAdmin = ($_SESSION['role'] ?? '') === 'admin';
    $userId   = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;

    if ($userId > 0 && $db instanceof mysqli) {
        $stmt = $db->prepare('UPDATE users SET remember_token = NULL WHERE id = ?');
        if ($stmt) {
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $stmt->close();
        }
    }

    if (isset($_COOKIE['remember_me_token'])) {
        setcookie('remember_me_token', '', time() - 3600, '/');
        unset($_COOKIE['remember_me_token']);
    }

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            [
                'expires'  => time() - 42000,
                'path'     => $params['path'] ?: '/',
                'domain'   => $params['domain'] ?? '',
                'secure'   => $params['secure'] ?? false,
                'httponly' => $params['httponly'] ?? true,
                'samesite' => $params['samesite'] ?? 'Lax',
            ]
        );
    }

    session_destroy();

    echo json_encode([
        'status'   => 'success',
        'message'  => 'Logout berhasil.',
        'redirect' => $loginUrl,
        'role'     => $wasAdmin ? 'admin' : 'mahasiswa',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ============================================================
// MODE 2 — HALAMAN KONFIRMASI
// ============================================================

// Jika tidak ada session aktif → langsung ke login
if (empty($_SESSION['user_id'])) {
    header('Location: ' . $loginUrl);
    exit;
}

$userName = trim((string)($_SESSION['name'] ?? 'Pengguna'));
if ($userName === '') $userName = 'Pengguna';

$userRole  = $_SESSION['role'] ?? 'mahasiswa';
$isAdmin   = $userRole === 'admin';
$roleLabel = $isAdmin ? 'Administrator' : 'Mahasiswa';

$userInitial = function_exists('mb_substr')
    ? mb_strtoupper(mb_substr($userName, 0, 1, 'UTF-8'), 'UTF-8')
    : strtoupper(substr($userName, 0, 1));

// URL "Batal" kembali ke dashboard yang sesuai
$backUrl = $isAdmin ? $dashboardAdmin : $dashboardMahasiswa;
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0d1b2a">
    <title>Keluar Sistem — Presensi NFC</title>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --accent: #ffd966;
            --accent-strong: #f0c850;
            --navy: #0d1b2a;
            --text-primary: #0f172a;
            --text-secondary: #475569;
            --red: #ef4444;
            --green: #22c55e;
            --cyan: #22d3ee;
            --bg-1: #f4f7fc;
            --bg-2: #e6edf7;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            -webkit-text-size-adjust: 100%;
        }

        body {
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', sans-serif;
            background: linear-gradient(145deg, var(--bg-1) 0%, var(--bg-2) 100%);
            padding: 1rem;
            animation: fadeIn 0.5s ease;
            overflow-x: hidden;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: scale(0.97);
            }

            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .bg-circle {
            position: fixed;
            border-radius: 50%;
            pointer-events: none;
            z-index: 0;
        }

        .bg-circle:nth-child(1) {
            width: 320px;
            height: 320px;
            top: -100px;
            right: -100px;
            background: radial-gradient(circle, rgba(255, 217, 102, 0.28), transparent 70%);
        }

        .bg-circle:nth-child(2) {
            width: 440px;
            height: 440px;
            bottom: -140px;
            left: -140px;
            background: radial-gradient(circle, rgba(34, 211, 238, 0.18), transparent 70%);
        }

        .bg-circle:nth-child(3) {
            width: 200px;
            height: 200px;
            top: 45%;
            left: 8%;
            background: radial-gradient(circle, rgba(255, 217, 102, 0.14), transparent 70%);
        }

        .welcome-msg {
            position: relative;
            z-index: 1;
            text-align: center;
            background: rgba(255, 255, 255, 0.72);
            backdrop-filter: blur(16px) saturate(180%);
            -webkit-backdrop-filter: blur(16px) saturate(180%);
            border-radius: 28px;
            padding: 2.5rem 2.8rem;
            box-shadow: 0 20px 60px rgba(15, 23, 42, 0.10);
            border: 1px solid rgba(255, 255, 255, 0.7);
            max-width: 420px;
            width: 100%;
            transition: opacity 0.4s ease, transform 0.4s ease;
        }

        .brand-box {
            width: 64px;
            height: 64px;
            border-radius: 18px;
            background: linear-gradient(135deg, var(--cyan), #0891b2);
            color: #03111f;
            font-weight: 900;
            font-size: 1.15rem;
            letter-spacing: 0.5px;
            display: grid;
            place-items: center;
            margin: 0 auto 1.2rem;
            box-shadow: 0 10px 28px rgba(34, 211, 238, 0.4);
        }

        .welcome-msg h2 {
            font-weight: 800;
            font-size: 1.5rem;
            color: var(--text-primary);
            letter-spacing: -0.02em;
            margin-bottom: 0.3rem;
        }

        .welcome-msg .sub {
            color: var(--text-secondary);
            font-size: 0.9rem;
            opacity: 0.85;
        }

        .user-mini {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-top: 1.4rem;
            padding: 6px 14px 6px 6px;
            background: rgba(255, 217, 102, 0.16);
            border: 1px solid rgba(240, 200, 80, 0.38);
            border-radius: 40px;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--navy);
            max-width: 100%;
        }

        .user-mini .avatar {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent), var(--accent-strong));
            color: var(--navy);
            display: grid;
            place-items: center;
            font-weight: 800;
            font-size: 0.8rem;
            flex-shrink: 0;
        }

        .user-mini .name {
            max-width: 160px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .user-mini .role {
            color: var(--text-secondary);
            font-weight: 500;
            font-size: 0.75rem;
            white-space: nowrap;
        }

        .loader-dots {
            display: inline-block;
            margin-top: 1.4rem;
        }

        .loader-dots span {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--accent-strong);
            margin: 0 4px;
            animation: bounce 1.4s infinite both;
        }

        .loader-dots span:nth-child(1) {
            animation-delay: -0.32s;
        }

        .loader-dots span:nth-child(2) {
            animation-delay: -0.16s;
        }

        .loader-dots span:nth-child(3) {
            animation-delay: 0s;
        }

        @keyframes bounce {

            0%,
            80%,
            100% {
                transform: scale(0.6);
                opacity: 0.4;
            }

            40% {
                transform: scale(1);
                opacity: 1;
            }
        }

        .swal2-popup {
            border-radius: 22px !important;
            font-family: 'Inter', system-ui, sans-serif !important;
            padding: 2rem 1.6rem !important;
        }

        .swal2-title {
            font-weight: 700 !important;
            color: var(--text-primary) !important;
            font-size: 1.35rem !important;
        }

        .swal2-html-container {
            color: var(--text-secondary) !important;
            font-size: 0.93rem !important;
            line-height: 1.55 !important;
        }

        .swal2-confirm,
        .swal2-cancel {
            font-weight: 700 !important;
            border-radius: 10px !important;
            padding: 0.65rem 1.5rem !important;
            font-family: 'Inter', system-ui, sans-serif !important;
        }

        .swal2-confirm:focus,
        .swal2-cancel:focus {
            box-shadow: 0 0 0 3px rgba(255, 217, 102, 0.35) !important;
        }

        @media (max-width: 480px) {
            .welcome-msg {
                padding: 2rem 1.5rem;
            }

            .welcome-msg h2 {
                font-size: 1.3rem;
            }

            .brand-box {
                width: 56px;
                height: 56px;
                font-size: 1rem;
            }

            .user-mini .name {
                max-width: 110px;
            }
        }
    </style>
</head>

<body>

    <div class="bg-circle"></div>
    <div class="bg-circle"></div>
    <div class="bg-circle"></div>

    <div class="welcome-msg" id="welcome">
        <div class="brand-box">NFC</div>
        <h2>Presensi NFC</h2>
        <p class="sub">Sistem Kehadiran Mahasiswa</p>

        <div class="user-mini">
            <div class="avatar"><?= htmlspecialchars($userInitial) ?></div>
            <span class="name"><?= htmlspecialchars($userName) ?></span>
            <span class="role">· <?= htmlspecialchars($roleLabel) ?></span>
        </div>

        <div class="loader-dots" aria-hidden="true">
            <span></span><span></span><span></span>
        </div>
    </div>

    <noscript>
        <div style="position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%); max-width: 480px; width: calc(100% - 40px); padding: 14px 18px; background: #fef3c7; border: 1px solid #fcd34d; border-radius: 12px; color: #78350f; text-align: center; font-size: 0.9rem; z-index: 9999; font-family: system-ui, sans-serif;">
            JavaScript dinonaktifkan.
            <a href="logout.php?confirm=1" style="color: #b45309; font-weight: 700; text-decoration: underline;">Klik di sini untuk logout</a>.
        </div>
    </noscript>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const welcome = document.getElementById('welcome');

            // URL dinamis dari PHP — dijamin benar, tidak tergantung folder
            const loginUrl = <?= json_encode($loginUrl, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            const backUrl = <?= json_encode($backUrl,  JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            const roleLabelJs = <?= json_encode($roleLabel, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

            // 1) Fade-out kartu sambutan
            welcome.style.opacity = '0';
            welcome.style.transform = 'scale(0.96)';

            setTimeout(function() {
                welcome.style.display = 'none';

                // 2) Konfirmasi logout
                Swal.fire({
                    title: 'Keluar dari sistem?',
                    html: '<div>Anda akan keluar dari akun <strong style="color:#0f172a;">Presensi NFC</strong> sebagai <strong style="color:#0f172a;">' + roleLabelJs + '</strong>.<br>Pastikan tidak ada pekerjaan yang belum disimpan.</div>',
                    icon: 'question',
                    iconColor: '#f0c850',
                    showCancelButton: true,
                    confirmButtonColor: '#ef4444',
                    cancelButtonColor: '#64748b',
                    confirmButtonText: 'Ya, Logout',
                    cancelButtonText: 'Batal',
                    reverseButtons: true,
                    focusCancel: true,
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showLoaderOnConfirm: true,
                    backdrop: 'rgba(15, 23, 42, 0.35)',
                    preConfirm: function() {
                        return fetch('logout.php?confirm=1', {
                                method: 'GET',
                                credentials: 'same-origin',
                                cache: 'no-store',
                                headers: {
                                    'Accept': 'application/json'
                                }
                            })
                            .then(function(res) {
                                if (!res.ok) throw new Error('Server error: ' + res.status);
                                return res.json();
                            })
                            .then(function(data) {
                                if (!data || data.status !== 'success') {
                                    throw new Error('Respons tidak valid');
                                }
                                return data;
                            })
                            .catch(function(err) {
                                Swal.showValidationMessage('Gagal logout: ' + err.message);
                                return false;
                            });
                    }
                }).then(function(result) {
                    if (result.isConfirmed && result.value && result.value.status === 'success') {
                        // 3) Sukses → redirect ke LOGIN URL yang sudah pasti benar dari PHP
                        var targetLogin = result.value.redirect || loginUrl;
                        Swal.fire({
                            icon: 'success',
                            title: 'Logout Berhasil',
                            text: 'Anda telah keluar. Sampai jumpa kembali!',
                            iconColor: '#22c55e',
                            timer: 1700,
                            timerProgressBar: true,
                            showConfirmButton: false,
                            allowOutsideClick: false,
                            allowEscapeKey: false,
                            backdrop: 'rgba(15, 23, 42, 0.25)'
                        }).then(function() {
                            window.location.replace(targetLogin);
                        });
                    } else if (result.dismiss === Swal.DismissReason.cancel) {
                        // 4) Batal → kembali ke dashboard (URL dari PHP)
                        window.location.replace(backUrl);
                    }
                });
            }, 350);
        });
    </script>

</body>

</html>