<?php
// setup_admin.php — jalankan sekali, lalu HAPUS file ini!
require_once __DIR__ . '/../koneksi.php';
$db = $koneksi ?? $conn ?? null;

if (!$db) {
    die('Koneksi database gagal.');
}

$email    = 'admin.presensi@presensimahasiswa.com';
$password = 'AdminPresensiMahasiswa'; // ganti sesuai kebutuhan
$hash     = password_hash($password, PASSWORD_DEFAULT);

$stmt = $db->prepare(
    "INSERT INTO users (name, email, password, role, is_active, created_at, updated_at)
     VALUES (?, ?, ?, 'admin', 1, NOW(), NOW())
     ON DUPLICATE KEY UPDATE
       name = VALUES(name),
       password = VALUES(password),
       role = VALUES(role),
       is_active = VALUES(is_active),
       updated_at = NOW()"
);

if (!$stmt) {
    die('Gagal menyiapkan query admin: ' . $db->error);
}

$name = 'Administrator';
$stmt->bind_param('sss', $name, $email, $hash);

if (!$stmt->execute()) {
    die('Gagal menyimpan password admin: ' . $stmt->error);
}

$stmt->close();

echo "Admin berhasil dibuat/diperbarui.<br>";
echo "Email: <b>$email</b><br>";
echo "Password: <b>$password</b><br>";
echo "Hash tersimpan: <code>$hash</code><br>";
echo "<br><b>HAPUS file setup_admin.php sekarang!</b>";
