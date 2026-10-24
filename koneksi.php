<?php
// koneksi.php
$host     = 'localhost';
$user     = 'root';
$password = '';
$database = 'db_absensi_poin';

$koneksi = mysqli_connect($host, $user, $password, $database);

if (!$koneksi) {
    die('Koneksi database gagal: ' . mysqli_connect_error());
}

mysqli_set_charset($koneksi, 'utf8mb4');
