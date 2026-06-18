<?php
// Simpan dengan nama: setup.php
$host = "db"; $user = "root"; $pass = "rootsecurepwd123";

$conn = new mysqli($host, $user, $pass);
if ($conn->connect_error) die("Koneksi gagal: " . $conn->connect_error);

$conn->query("CREATE DATABASE IF NOT EXISTS cinema_dw");
$conn->select_db("cinema_dw");

// Buat Tabel
$conn->query("CREATE TABLE IF NOT EXISTS dim_film (id_film INT AUTO_INCREMENT PRIMARY KEY, judul_film VARCHAR(100), genre VARCHAR(50))");
$conn->query("CREATE TABLE IF NOT EXISTS dim_cabang (id_cabang INT AUTO_INCREMENT PRIMARY KEY, nama_cabang VARCHAR(100), kota_cabang VARCHAR(50))");
$conn->query("CREATE TABLE IF NOT EXISTS dim_tipe_tiket (id_tipe INT AUTO_INCREMENT PRIMARY KEY, nama_tipe VARCHAR(50), harga INT)");
$conn->query("CREATE TABLE IF NOT EXISTS fakta_penjualan (id_fakta INT AUTO_INCREMENT PRIMARY KEY, id_film INT, id_cabang INT, id_tipe INT, jumlah_tiket INT, total_pendapatan DECIMAL(15,2), waktu_transaksi DATETIME)");

// Bersihkan data lama
$conn->query("TRUNCATE TABLE dim_film");
$conn->query("TRUNCATE TABLE dim_cabang");
$conn->query("TRUNCATE TABLE dim_tipe_tiket");
$conn->query("TRUNCATE TABLE fakta_penjualan");

// Insert Cabang
$conn->query("INSERT INTO dim_cabang (nama_cabang, kota_cabang) VALUES ('Cineplex Tunjungan Plaza', 'Surabaya'), ('Grand Cinema Indo', 'Jakarta'), ('Empire Premiere', 'Bandung')");

// Insert Studio 1 - 7 dan VVIP
$studios = [
    ['Studio 1', 40000], ['Studio 2', 40000], ['Studio 3', 45000], 
    ['Studio 4', 45000], ['Studio 5', 50000], ['Studio 6', 50000], 
    ['Studio 7', 55000], ['VVIP Premiere', 120000]
];
foreach ($studios as $s) {
    $conn->query("INSERT INTO dim_tipe_tiket (nama_tipe, harga) VALUES ('{$s[0]}', {$s[1]})");
}

// Insert Beberapa Film
$films = [
    ['Scream 6', 'Horror'], ['Keluarga Cemara', 'Drama'], ['Fast X', 'Action'], 
    ['John Wick 4', 'Action'], ['Pengabdi Setan 2', 'Horror'], ['Oppenheimer', 'Drama']
];
foreach ($films as $f) {
    $conn->query("INSERT INTO dim_film (judul_film, genre) VALUES ('{$f[0]}', '{$f[1]}')");
}

// Generate 2000 Data Dummy dalam 5 Tahun Terakhir (1825 hari)
for ($i = 0; $i < 2000; $i++) {
    $id_film = rand(1, 6);
    $id_cabang = rand(1, 3);
    $id_tipe = rand(1, 8);
    $qty = rand(1, 4);
    $harga = $studios[$id_tipe - 1][1];
    $total = $qty * $harga;
    
    // Acak hari mundur dari 0 (hari ini) sampai 1825 hari yang lalu
    $hari_mundur = rand(0, 1825);
    $waktu = date('Y-m-d H:i:s', strtotime("-$hari_mundur days"));
    
    $conn->query("INSERT INTO fakta_penjualan (id_film, id_cabang, id_tipe, jumlah_tiket, total_pendapatan, waktu_transaksi) 
                  VALUES ($id_film, $id_cabang, $id_tipe, $qty, $total, '$waktu')");
}

echo "<h1>Setup Database Bioskop Berhasil!</h1>";
echo "<p>Studio 1-7, VVIP, dan riwayat transaksi 5 TAHUN telah di-generate.</p>";
echo "<a href='login.php'>Lanjut ke Halaman Login</a>";
?>