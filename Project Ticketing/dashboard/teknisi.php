
<?php
session_start();
require_once __DIR__ . "/../koneksi.php";

// Pastikan pengguna sudah login sebagai teknisi
if (
    !isset($_SESSION['id_pengguna']) ||
    ($_SESSION['role'] ?? '') !== 'teknisi'
) {
    header("Location: ../login.php");
    exit;
}

$nama = $_SESSION['nama'] ?? 'Teknisi';

// Ambil daftar tiket pengaduan dari database

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
        p.nama_lengkap
     FROM tiket t
     INNER JOIN penugasan_tiket pt
        ON pt.id_penugasan = (
            SELECT MAX(pt2.id_penugasan)
            FROM penugasan_tiket pt2
            WHERE pt2.id_tiket = t.id_tiket
        )
        AND pt.id_tiket = t.id_tiket
     LEFT JOIN kategori k
        ON t.id_kategori = k.id_kategori
     LEFT JOIN pengguna p
        ON t.id_pengguna = p.id_pengguna
     WHERE pt.id_teknisi = ?
     ORDER BY t.id_tiket DESC"
);

$id_teknisi = (int) $_SESSION['id_pengguna'];

mysqli_stmt_bind_param($query, "i", $id_teknisi);
mysqli_stmt_execute($query);
$query = mysqli_stmt_get_result($query);

if (!$query) {
    die("Gagal mengambil data tiket.");
}


if (!$query) {
    die("Gagal mengambil data tiket: " . mysqli_error($conn));
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Teknisi</title>
</head>
<body>

<header>
    <h2>Helpdesk Sekolah</h2>
    <a href="../logout.php">Logout</a>
</header>

<main>
    <h1>Dashboard Teknisi</h1>

    <p>
        Selamat datang,
        <strong><?= htmlspecialchars($nama) ?></strong>!
    </p>

    <p>Berikut daftar tiket pengaduan yang masuk.</p>

    <h2>Daftar Tiket</h2>

    <?php if (mysqli_num_rows($query) > 0): ?>
        <?php while ($tiket = mysqli_fetch_assoc($query)): ?>
            <section>
                <h3>
                    #<?= (int) $tiket['id_tiket'] ?>
                    - <?= htmlspecialchars($tiket['judul']) ?>
                </h3>

                <p>
                    Pelapor:
                    <?= htmlspecialchars($tiket['nama_lengkap'] ?? 'Pengguna') ?>
                </p>

                <p>
                    Kategori:
                    <?= htmlspecialchars($tiket['nama_kategori'] ?? 'Lainnya') ?>
                </p>

                <p>
                    Deskripsi:
                    <?= nl2br(htmlspecialchars($tiket['deskripsi'])) ?>
                </p>

              
<p>
    Status saat ini:
    <strong><?= htmlspecialchars($tiket['status']) ?></strong>
</p>

<form action="../ubah_status.php" method="POST">
    <input
        type="hidden"
        name="id_tiket"
        value="<?= (int) $tiket['id_tiket'] ?>"
    >

    <label for="status-<?= (int) $tiket['id_tiket'] ?>">
        Ubah status:
    </label>

    <select
        id="status-<?= (int) $tiket['id_tiket'] ?>"
        name="status"
        required
    >
        <option value="Menunggu"
            <?= $tiket['status'] === 'Menunggu' ? 'selected' : '' ?>>
            Menunggu
        </option>

        <option value="Diproses"
            <?= $tiket['status'] === 'Diproses' ? 'selected' : '' ?>>
            Diproses
        </option>

        <option value="Selesai"
            <?= $tiket['status'] === 'Selesai' ? 'selected' : '' ?>>
            Selesai
        </option>
    </select>

    <label for="catatan-<?= (int) $tiket['id_tiket'] ?>">
        Keterangan Pengerjaan:
    </label>

    <textarea
        id="catatan-<?= (int) $tiket['id_tiket'] ?>"
        name="catatan"
        rows="3"
        placeholder="Tuliskan keterangan pengerjaan tiket..."
        required
    ></textarea>

    <button type="submit">Simpan Status</button>
</form>

                <p>
                    Prioritas:
                    <?= htmlspecialchars($tiket['prioritas']) ?>
                </p>

                <p>
                     Tanggal:
                     <?= htmlspecialchars($tiket['created_at']) ?>
                </p>

                <a href="../chat_tiket.php?id_tiket=<?= (int) $tiket['id_tiket'] ?>">
                     <button type="button">Detail & Chat</button>
                </a>

                <hr>


                <hr>
            </section>
        <?php endwhile; ?>
    <?php else: ?>
        <p>Belum ada tiket pengaduan.</p>
    <?php endif; ?>
</main>

</body>
</html>
