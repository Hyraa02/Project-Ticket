
<?php
session_start();
require_once __DIR__ . "/koneksi.php";

if (!isset($_SESSION['id_pengguna'])) {
    header("Location: login.php");
    exit;
}

$id_pengguna = $_SESSION['id_pengguna'];


$id_pengguna = (int) $_SESSION['id_pengguna'];


$query = mysqli_prepare(
    $conn,
    "SELECT
        t.id_tiket,
        t.judul,
        t.deskripsi,
        t.status,
        t.prioritas,
        t.created_at,
        k.nama_kategori,
        (
            SELECT r.catatan
            FROM riwayat_tiket r
            WHERE r.id_tiket = t.id_tiket
              AND r.catatan IS NOT NULL
              AND r.catatan <> ''
            ORDER BY r.id_riwayat DESC
            LIMIT 1
        ) AS catatan
     FROM tiket t
     LEFT JOIN kategori k
        ON t.id_kategori = k.id_kategori
     WHERE t.id_pengguna = ?
     ORDER BY t.id_tiket DESC"
);

mysqli_stmt_bind_param($query, "i", $id_pengguna);
mysqli_stmt_execute($query);
$hasil = mysqli_stmt_get_result($query);

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tiket Saya - Helpdesk Sekolah</title>
</head>
<body>

<h1>Tiket Saya</h1>

<p><strong>Keterangan Pengerjaan Teknisi:</strong></p>

<?php if (!empty($tiket['catatan'])): ?>
    <p><?= nl2br(htmlspecialchars($tiket['catatan'])) ?></p>
<?php else: ?>
    <p>Belum ada keterangan pengerjaan dari teknisi.</p>
<?php endif; ?>

<?php if (isset($_GET['berhasil'])): ?>
    <p>Tiket berhasil dikirim!</p>
<?php endif; ?>

<a href="dashboard/siswa.php">Kembali ke Dashboard</a>
<hr>

<?php if (mysqli_num_rows($hasil) > 0): ?>
    <?php while ($tiket = mysqli_fetch_assoc($hasil)): ?>
        <article>
            <h3><?= htmlspecialchars($tiket['judul']) ?></h3>
            <p>Kategori: <?= htmlspecialchars($tiket['nama_kategori'] ?? 'Tidak ada') ?></p>
            <p>Deskripsi: <?= htmlspecialchars($tiket['deskripsi']) ?></p>
            <p>Status: <?= htmlspecialchars($tiket['status']) ?></p>
            
<p><strong>Keterangan Pengerjaan:</strong></p>

<?php if (!empty($tiket['catatan'])): ?>
    <p><?= nl2br(htmlspecialchars($tiket['catatan'])) ?></p>
<?php else: ?>
    <p>Belum ada keterangan pengerjaan.</p>
<?php endif; ?>

            <p>Prioritas: <?= htmlspecialchars($tiket['prioritas']) ?></p>
            
<p>Tanggal: <?= htmlspecialchars($tiket['created_at']) ?></p>

<a href="chat_tiket.php?id_tiket=<?= (int) $tiket['id_tiket'] ?>">
    <button type="button">Chat Tiket</button>
</a>


            <hr>
        </article>
    <?php endwhile; ?>
<?php else: ?>
    <p>Kamu belum memiliki tiket pengaduan.</p>
<?php endif; ?>

</body>
</html>
