
<?php
session_start();

if (!isset($_SESSION['id_pengguna'])) {
    header("Location: ../login.php");
    exit;
}

$nama = $_SESSION['nama'] ?? 'Siswa';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard Siswa</title>

    <!-- Menghubungkan file CSS -->
    <link rel="stylesheet" href="siswa.css">
</head>
<body>

<header>
    <h2>Helpdesk Sekolah</h2>
    <a href="../logout.php" class="btn">Logout</a>
</header>

<main>
    <section class="welcome">
        <h1>Dashboard Siswa</h1>
        <p>
            Selamat datang,
            <strong><?= htmlspecialchars($nama) ?></strong>!
        </p>
        <p>Laporkan kerusakan fasilitas sekolah di sini.</p>
    </section>

    
<section class="menu">
    <div class="card">
        <h3>Buat Tiket</h3>
        <p>Buat laporan kerusakan fasilitas sekolah.</p>
        <a href="../buat_tiket.php" class="btn">Buat Tiket</a>
    </div>

    <div class="card">
        <h3>Tiket Saya</h3>
        <p>Lihat laporan dan status tiketmu.</p>
        <a href="../tiket_saya.php" class="btn">Lihat Tiket</a>
    </div>

    <div class="card">
        <h3>Chat Tiket</h3>
        <p>Komunikasi dengan teknisi.</p>
        <a href="../tiket_saya.php" class="btn">Buka Chat</a>
    </div>
</section>

</main>

<footer>
    Helpdesk Sekolah &copy; <?= date("Y") ?>
</footer>

</body>
</html>
