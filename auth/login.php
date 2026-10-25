<?php

/**
 * auth/login.php
 * Halaman Login — Presensi NFC
 * - Login via Email/NIM
 * - Verifikasi password_hash (bcrypt)
 * - Auto-upgrade password plaintext → hash (backward compatible)
 * - Remember Me (cookie 30 hari, token disimpan dalam bentuk hash SHA-256)
 * - CAPTCHA (3 karakter)
 * - Pembatasan percobaan login (5x gagal → tunggu 60 detik)
 * - Dark Mode
 * - Redirect:
 *    admin     → ../admin/index2.php
 *    mahasiswa → ../mahasiswa/index.php
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../koneksi.php';
$db = $koneksi ?? $conn ?? null;

const MAX_ATTEMPTS = 5;
const LOCK_SECONDS = 60;

/* ============================================================
   HELPER
   ============================================================ */
function generateCaptcha()
{
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code  = '';
    for ($i = 0; $i < 3; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $code;
}

function redirectByRole($role)
{
    header("Location: " . ($role === 'admin' ? "../admin/index2.php" : "../mahasiswa/index.php"));
    exit;
}

function startUserSession($id, $name, $email, $role)
{
    session_regenerate_id(true); // cegah session fixation
    $_SESSION['user_id'] = $id;
    $_SESSION['name']    = $name;
    $_SESSION['role']    = $role;
    $_SESSION['email']   = $email;
}

/* ============================================================
   CAPTCHA
   ============================================================ */
if (isset($_GET['refresh_captcha'])) {
    $_SESSION['captcha_code'] = generateCaptcha();
    echo $_SESSION['captcha_code'];
    exit;
}

if (empty($_SESSION['captcha_code'])) {
    $_SESSION['captcha_code'] = generateCaptcha();
}

/* ============================================================
   AUTO LOGIN (REMEMBER ME)
   ============================================================ */
if (!isset($_SESSION['user_id']) && !empty($_COOKIE['remember_me_token']) && $db) {
    $token_hash = hash('sha256', $_COOKIE['remember_me_token']);

    $stmt = $db->prepare("SELECT id, name, email, role FROM users WHERE remember_token = ? AND is_active = 1 LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("s", $token_hash);
        $stmt->execute();
        $stmt->bind_result($id, $name, $email, $role);
        $found = $stmt->fetch();
        $stmt->close();

        if ($found) {
            startUserSession($id, $name, $email, $role);
            redirectByRole($role);
        }
    }
}

/* Jika sudah login, langsung alihkan */
if (isset($_SESSION['user_id'])) {
    redirectByRole($_SESSION['role'] ?? 'mahasiswa');
}

/* ============================================================
   PROSES LOGIN
   ============================================================ */
$error            = '';
$identifier_val   = '';
$remember_checked = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier    = trim($_POST['identifier'] ?? '');
    $password      = $_POST['password'] ?? '';
    $captcha_input = strtoupper(trim($_POST['captcha_input'] ?? ''));
    $remember_me   = isset($_POST['remember_me']);

    $identifier_val   = htmlspecialchars($identifier);
    $remember_checked = $remember_me ? 'checked' : '';

    $lock_until = $_SESSION['lock_until'] ?? 0;

    if ($lock_until > time()) {
        $error = 'Terlalu banyak percobaan gagal. Coba lagi dalam ' . ($lock_until - time()) . ' detik.';
    } elseif ($identifier === '' || $password === '' || $captcha_input === '') {
        $error = 'Email/NIM, Password, dan Kode Keamanan wajib diisi.';
    } elseif (!hash_equals((string)($_SESSION['captcha_code'] ?? ''), $captcha_input)) {
        $error = 'Kode keamanan salah. Silakan coba lagi.';
        $_SESSION['captcha_code'] = generateCaptcha();
    } elseif (!$db) {
        $error = 'Koneksi database gagal.';
    } else {
        $stmt = $db->prepare("SELECT id, name, email, password, role FROM users WHERE (email = ? OR nim = ?) AND is_active = 1 LIMIT 1");

        if ($stmt) {
            $stmt->bind_param("ss", $identifier, $identifier);
            $stmt->execute();
            $stmt->bind_result($id, $name, $email, $hashed_password, $role);
            $found = $stmt->fetch();
            $stmt->close(); // WAJIB ditutup sebelum menjalankan query lain

            $login_ok = false;

            if ($found) {
                $info = password_get_info($hashed_password);

                if ($hashed_password !== '' && $info['algo'] !== null) {
                    // 1) Password sudah berupa hash (bcrypt/argon)
                    if (password_verify($password, $hashed_password)) {
                        $login_ok = true;

                        // Upgrade otomatis jika algoritma/cost sudah usang
                        if (password_needs_rehash($hashed_password, PASSWORD_DEFAULT)) {
                            $new_hash = password_hash($password, PASSWORD_DEFAULT);
                            $up = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                            if ($up) {
                                $up->bind_param("si", $new_hash, $id);
                                $up->execute();
                                $up->close();
                            }
                        }
                    }
                } elseif ($hashed_password !== '' && hash_equals($hashed_password, $password)) {
                    // 2) Fallback: kolom masih plaintext → cocokkan lalu upgrade ke hash
                    $new_hash = password_hash($password, PASSWORD_DEFAULT);
                    $up = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                    if ($up) {
                        $up->bind_param("si", $new_hash, $id);
                        $up->execute();
                        $up->close();
                    }
                    $login_ok = true;
                }
                // Catatan: password kosong ('') di database SELALU ditolak.
                // Jalankan setup_admin.php untuk mengisi password admin.
            }

            if ($login_ok) {
                startUserSession($id, $name, $email, $role);
                unset($_SESSION['captcha_code'], $_SESSION['login_fail'], $_SESSION['lock_until']);

                // Remember Me
                if ($remember_me) {
                    $token      = bin2hex(random_bytes(32));
                    $token_hash = hash('sha256', $token);

                    $upd = $db->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
                    if ($upd) {
                        $upd->bind_param("si", $token_hash, $id);
                        $upd->execute();
                        $upd->close();
                    }
                    setcookie('remember_me_token', $token, [
                        'expires'  => time() + 86400 * 30,
                        'path'     => '/',
                        'secure'   => !empty($_SERVER['HTTPS']),
                        'httponly' => true,
                        'samesite' => 'Lax',
                    ]);
                } else {
                    $clr = $db->prepare("UPDATE users SET remember_token = NULL WHERE id = ?");
                    if ($clr) {
                        $clr->bind_param("i", $id);
                        $clr->execute();
                        $clr->close();
                    }
                    if (isset($_COOKIE['remember_me_token'])) {
                        setcookie('remember_me_token', '', time() - 3600, '/');
                    }
                }

                redirectByRole($role);
            } else {
                // Hitung percobaan gagal
                $_SESSION['login_fail'] = ($_SESSION['login_fail'] ?? 0) + 1;
                if ($_SESSION['login_fail'] >= MAX_ATTEMPTS) {
                    $_SESSION['lock_until'] = time() + LOCK_SECONDS;
                    $_SESSION['login_fail'] = 0;
                    $error = 'Terlalu banyak percobaan gagal. Coba lagi dalam ' . LOCK_SECONDS . ' detik.';
                } else {
                    $error = $found
                        ? 'Password yang Anda masukkan salah.'
                        : 'Akun tidak ditemukan atau sedang dinonaktifkan.';
                }
                $_SESSION['captcha_code'] = generateCaptcha();
            }
        } else {
            $error = 'Terjadi kesalahan pada sistem database.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk — Presensi NFC | Kehadiran Mahasiswa</title>
    <meta name="description" content="Halaman login sistem Presensi NFC.">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <style>
        :root {
            --bg-body: #f4f7fc;
            --bg-card: #ffffff;
            --text-primary: #1a2634;
            --text-secondary: #4a5a6a;
            --border-color: rgba(0, 0, 0, .08);
            --input-bg: #f8fafc;
            --btn-primary-bg: #ffd966;
            --btn-primary-color: #1a2634;
            --btn-primary-hover: #ffed4a;
            --accent: #ffd966;
            --link-color: #0891b2;
            --shadow-card: 0 10px 40px rgba(0, 0, 0, .08);
            --captcha-border: #d1d5db;
            --captcha-bg: #f9fafb;
            --captcha-text: #4c1d95;
            --captcha-line: rgba(124, 58, 237, .4);
        }

        body.dark-mode {
            --bg-body: #121926;
            --bg-card: #1e2a3a;
            --text-primary: #e8edf3;
            --text-secondary: #b0c0d0;
            --border-color: rgba(255, 255, 255, .1);
            --input-bg: #15202b;
            --btn-primary-bg: #f0c850;
            --btn-primary-color: #1a2634;
            --btn-primary-hover: #ffd966;
            --accent: #f0c850;
            --link-color: #67e8f9;
            --shadow-card: 0 10px 40px rgba(0, 0, 0, .3);
            --captcha-border: #374151;
            --captcha-bg: #111827;
            --captcha-text: #a78bfa;
            --captcha-line: rgba(167, 139, 250, .3);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            transition: background .3s, color .3s
        }

        a {
            text-decoration: none;
            color: inherit
        }

        button {
            font-family: inherit
        }

        .login-wrapper {
            display: flex;
            width: 100%;
            min-height: 100vh
        }

        .login-left {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 3rem 5%;
            position: relative;
            background: var(--bg-card);
            z-index: 2
        }

        .login-right {
            flex: 1;
            background: url('../background.jpg') center center/cover no-repeat;
            position: relative;
            display: none
        }

        .login-right::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(13, 27, 42, .85) 0%, rgba(13, 27, 42, .4) 100%);
            z-index: 1
        }

        .login-right-content {
            position: relative;
            z-index: 2;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 3rem;
            color: #fff
        }

        .login-right-content h2 {
            font-size: 2.5rem;
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: 1rem;
            text-shadow: 0 4px 20px rgba(0, 0, 0, .4)
        }

        .login-right-content p {
            font-size: 1.1rem;
            color: rgba(255, 255, 255, .85);
            max-width: 400px
        }

        .brand-area {
            display: flex;
            align-items: center;
            gap: .75rem;
            margin-bottom: 2rem;
            text-decoration: none
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
            font-size: 16px;
            letter-spacing: .5px;
            box-shadow: 0 6px 18px rgba(34, 211, 238, .4)
        }

        .brand-text {
            font-weight: 800;
            font-size: 1.4rem;
            color: var(--text-primary);
            letter-spacing: .5px;
            line-height: 1.1
        }

        .brand-text small {
            display: block;
            font-weight: 500;
            font-size: .6rem;
            letter-spacing: 3px;
            color: var(--text-secondary);
            text-transform: uppercase
        }

        .form-title {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: .5rem;
            color: var(--text-primary)
        }

        .form-subtitle {
            color: var(--text-secondary);
            margin-bottom: 1.5rem;
            font-size: .95rem
        }

        .form-label {
            font-size: .8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .5px;
            color: var(--text-secondary);
            margin-bottom: .4rem
        }

        .form-control {
            background: var(--input-bg);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            padding: .7rem 1rem;
            border-radius: 10px;
            font-size: .95rem;
            transition: all .25s
        }

        .form-control:focus {
            background: var(--input-bg);
            color: var(--text-primary);
            border-color: var(--accent);
            box-shadow: 0 0 0 4px rgba(255, 217, 102, .15)
        }

        .input-group-text {
            background: var(--input-bg);
            border: 1px solid var(--border-color);
            border-right: none;
            color: var(--text-secondary);
            border-radius: 10px 0 0 10px
        }

        .input-group .form-control {
            border-left: none;
            border-radius: 0 10px 10px 0
        }

        .captcha-row {
            display: flex;
            gap: 10px;
            margin-bottom: 1rem
        }

        .captcha-box {
            flex: 1;
            border: 1px solid var(--captcha-border);
            border-radius: 8px;
            background: var(--captcha-bg);
            height: 50px;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden
        }

        .captcha-text {
            font-family: 'Courier New', monospace;
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--captcha-text);
            letter-spacing: 10px;
            z-index: 2;
            user-select: none;
            text-shadow: 1px 1px 0 rgba(255, 255, 255, .5)
        }

        body.dark-mode .captcha-text {
            text-shadow: 1px 1px 0 rgba(0, 0, 0, .5)
        }

        .captcha-line {
            position: absolute;
            width: 150%;
            height: 2px;
            background: var(--captcha-line);
            z-index: 1
        }

        .captcha-line.line-1 {
            transform: rotate(-15deg)
        }

        .captcha-line.line-2 {
            transform: rotate(20deg)
        }

        .btn-refresh {
            width: 50px;
            height: 50px;
            background: transparent;
            border: 1px solid var(--captcha-border);
            border-radius: 8px;
            color: var(--captcha-text);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all .2s
        }

        .btn-refresh:hover {
            background: var(--input-bg)
        }

        .btn-refresh i {
            transition: transform .4s ease
        }

        .btn-refresh.spinning i {
            transform: rotate(360deg)
        }

        .captcha-input {
            border: 1px solid #c4b5fd;
            background: transparent;
            color: var(--text-primary);
            padding: .8rem 1rem;
            border-radius: 8px;
            width: 100%;
            font-size: .95rem;
            text-align: center;
            letter-spacing: 2px;
            transition: all .2s
        }

        body.dark-mode .captcha-input {
            border-color: #6d28d9
        }

        .captcha-input:focus {
            outline: none;
            border-color: var(--captcha-text);
            box-shadow: 0 0 0 3px rgba(124, 58, 237, .1)
        }

        .btn-login {
            background: var(--btn-primary-bg);
            color: var(--btn-primary-color);
            font-weight: 700;
            padding: .8rem;
            border-radius: 10px;
            border: none;
            width: 100%;
            font-size: 1rem;
            transition: all .25s;
            box-shadow: 0 4px 15px rgba(255, 217, 102, .35);
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px
        }

        .btn-login:hover {
            background: var(--btn-primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(255, 217, 102, .45);
            color: var(--btn-primary-color)
        }

        .btn-login:disabled {
            opacity: .7;
            cursor: not-allowed;
            transform: none
        }

        .auth-links {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1.2rem;
            font-size: .9rem
        }

        .auth-links a {
            color: var(--link-color);
            font-weight: 600;
            transition: color .2s
        }

        .auth-links a:hover {
            color: var(--text-primary);
            text-decoration: underline
        }

        .form-check-input {
            cursor: pointer
        }

        .form-check-input:checked {
            background-color: #0891b2;
            border-color: #0891b2
        }

        .back-home {
            margin-top: 2.5rem;
            text-align: center
        }

        .back-home a {
            color: var(--text-secondary);
            font-size: .9rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: color .2s
        }

        .back-home a:hover {
            color: var(--text-primary)
        }

        .theme-toggle {
            position: absolute;
            top: 2rem;
            right: 2rem;
            width: 42px;
            height: 42px;
            border-radius: 50%;
            border: 1px solid var(--border-color);
            background: var(--input-bg);
            color: var(--text-primary);
            cursor: pointer;
            display: grid;
            place-items: center;
            transition: all .3s;
            font-size: 16px;
            z-index: 10
        }

        .theme-toggle:hover {
            background: var(--border-color);
            transform: rotate(20deg)
        }

        @media (min-width:992px) {
            .login-left {
                flex: 0 0 45%;
                max-width: 45%;
                padding: 3rem 4rem
            }

            .login-right {
                display: block;
                flex: 0 0 55%;
                max-width: 55%
            }
        }
    </style>
</head>

<body>

    <div class="login-wrapper">
        <div class="login-left">
            <button class="theme-toggle" id="themeToggle" aria-label="Toggle tema">
                <i class="fas fa-moon"></i>
            </button>

            <a href="../beranda.php" class="brand-area">
                <div class="brand-logo-box">NFC</div>
                <div class="brand-text">
                    Presensi NFC
                    <small>Kehadiran Mahasiswa</small>
                </div>
            </a>

            <div>
                <h1 class="form-title">Selamat Datang</h1>
                <p class="form-subtitle">Silakan masuk menggunakan akun Email atau NIM Anda.</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger d-flex align-items-center gap-2"
                    style="border-radius:10px;font-size:.85rem;background-color:rgba(220,53,69,.1);border-color:rgba(220,53,69,.2);color:#dc3545;">
                    <i class="fas fa-exclamation-circle fs-5"></i>
                    <div><?= htmlspecialchars($error) ?></div>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="loginForm">
                <div class="mb-3">
                    <label class="form-label">Email atau NIM</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                        <input type="text" name="identifier" class="form-control"
                            placeholder="Masukkan Email atau NIM"
                            value="<?= $identifier_val ?>" required autofocus>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" name="password" class="form-control" id="passwordInput"
                            placeholder="Masukkan Password" required
                            style="border-right:none;border-radius:0;">
                        <button class="btn btn-outline-secondary" type="button" id="togglePassword"
                            style="border:1px solid var(--border-color);border-left:none;border-radius:0 10px 10px 0;background:var(--input-bg);color:var(--text-secondary);">
                            <i class="fas fa-eye" id="eyeIcon"></i>
                        </button>
                    </div>
                </div>

                <div class="mb-2">
                    <label class="form-label">Kode Keamanan</label>
                    <div class="captcha-row">
                        <div class="captcha-box">
                            <div class="captcha-line line-1"></div>
                            <div class="captcha-line line-2"></div>
                            <span class="captcha-text" id="captchaText"><?= htmlspecialchars($_SESSION['captcha_code']) ?></span>
                        </div>
                        <button type="button" class="btn-refresh" id="btnRefreshCaptcha" title="Ganti Kode">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>
                    <input type="text" name="captcha_input" class="captcha-input"
                        placeholder="Masukkan 3 karakter di atas"
                        required maxlength="3" autocomplete="off">
                </div>

                <div class="auth-links mb-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="remember_me" id="rememberMe" <?= $remember_checked ?>>
                        <label class="form-check-label text-secondary" for="rememberMe"
                            style="font-size:.85rem;user-select:none;">Ingat Saya</label>
                    </div>
                    <a href="forgot-password.php">Lupa Password?</a>
                </div>

                <button type="submit" class="btn-login" id="submitBtn">
                    <span>Masuk</span> <i class="fas fa-arrow-right"></i>
                </button>
            </form>

            <div class="back-home">
                <a href="../beranda.php"><i class="fas fa-arrow-left"></i> Kembali ke Beranda</a>
            </div>
        </div>

        <div class="login-right">
            <div class="login-right-content">
                <div style="display:flex;justify-content:flex-end;">
                    <span style="background:rgba(255,255,255,.1);padding:6px 16px;border-radius:20px;font-size:.8rem;font-weight:600;letter-spacing:1px;border:1px solid rgba(255,255,255,.2);backdrop-filter:blur(4px);">
                        <i class="fas fa-shield-alt me-2" style="color:#22d3ee;"></i> SISTEM AMAN
                    </span>
                </div>
                <div>
                    <h2>Tap Kartu,<br>Hadir Tercatat.</h2>
                    <p>Presensi modern berbasis kartu NFC untuk mencatat kehadiran mahasiswa secara otomatis, real-time, dan transparan dengan sistem poin kedisiplinan.</p>
                </div>
                <div style="font-size:.85rem;color:rgba(255,255,255,.6);">
                    &copy; <?= date('Y') ?> Presensi NFC. All rights reserved.
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            'use strict';

            /* DARK MODE */
            const themeBtn = document.getElementById('themeToggle');
            const icon = themeBtn.querySelector('i');
            const storedTheme = localStorage.getItem('presensi_theme');
            if (storedTheme === 'dark' || (!storedTheme && matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.body.classList.add('dark-mode');
                icon.className = 'fas fa-sun';
                localStorage.setItem('presensi_theme', 'dark');
            }
            themeBtn.addEventListener('click', () => {
                const isDark = document.body.classList.toggle('dark-mode');
                localStorage.setItem('presensi_theme', isDark ? 'dark' : 'light');
                icon.className = isDark ? 'fas fa-sun' : 'fas fa-moon';
            });

            /* SHOW/HIDE PASSWORD */
            const togglePassword = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('passwordInput');
            const eyeIcon = document.getElementById('eyeIcon');
            togglePassword.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                eyeIcon.className = type === 'text' ? 'fas fa-eye-slash' : 'fas fa-eye';
            });

            /* REFRESH CAPTCHA */
            const btnRefresh = document.getElementById('btnRefreshCaptcha');
            const captchaText = document.getElementById('captchaText');
            btnRefresh.addEventListener('click', function() {
                btnRefresh.classList.add('spinning');
                fetch('login.php?refresh_captcha=1')
                    .then(r => r.text())
                    .then(data => {
                        captchaText.textContent = data;
                        document.querySelector('input[name="captcha_input"]').value = '';
                    })
                    .catch(err => console.error('Error fetching captcha:', err))
                    .finally(() => setTimeout(() => btnRefresh.classList.remove('spinning'), 500));
            });

            /* FORM SUBMIT LOADING */
            const loginForm = document.getElementById('loginForm');
            const submitBtn = document.getElementById('submitBtn');
            loginForm.addEventListener('submit', function() {
                if (loginForm.checkValidity()) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Memproses...';
                }
            });
        });
    </script>
</body>

</html>