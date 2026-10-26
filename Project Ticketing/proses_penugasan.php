
<?php
session_start();
require_once __DIR__ . "/koneksi.php";

// Hanya Sarpras yang boleh menugaskan teknisi
if (
    !isset($_SESSION['id_pengguna']) ||
    ($_SESSION['role'] ?? '') !== 'sarpras'
) {
    header("Location: login.php");
    exit;
}

// Pastikan data dikirim melalui POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard/sarpras.php");
    exit;
}

$id_tiket = (int) ($_POST['id_tiket'] ?? 0);
$id_teknisi = (int) ($_POST['id_teknisi'] ?? 0);

// Validasi ID
if ($id_tiket <= 0 || $id_teknisi <= 0) {
    exit("Tiket atau teknisi tidak valid.");
}

// Pastikan tiket tersedia
$stmt = mysqli_prepare(
    $conn,
    "SELECT id_tiket FROM tiket WHERE id_tiket = ?"
);
mysqli_stmt_bind_param($stmt, "i", $id_tiket);
mysqli_stmt_execute($stmt);
$tiket = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($tiket) === 0) {
    exit("Tiket tidak ditemukan.");
}

// Pastikan akun teknisi tersedia dan aktif
$stmt = mysqli_prepare(
    $conn,
    "SELECT id_pengguna
     FROM pengguna
     WHERE id_pengguna = ?
       AND role = 'teknisi'
       AND status = 'aktif'"
);
mysqli_stmt_bind_param($stmt, "i", $id_teknisi);
mysqli_stmt_execute($stmt);
$teknisi = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($teknisi) === 0) {
    exit("Akun teknisi tidak ditemukan atau tidak aktif.");
}

// Simpan penugasan baru
$stmt = mysqli_prepare(
    $conn,
    "INSERT INTO penugasan_tiket (id_tiket, id_teknisi)
     VALUES (?, ?)"
);
mysqli_stmt_bind_param($stmt, "ii", $id_tiket, $id_teknisi);

if (!mysqli_stmt_execute($stmt)) {
    exit("Penugasan gagal disimpan.");
}

// Kembali ke dashboard Sarpras
header("Location: dashboard/sarpras.php");
exit;
?>
