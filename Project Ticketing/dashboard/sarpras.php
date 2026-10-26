
<?php
session_start();
require_once __DIR__ . "/../koneksi.php";

// Hanya Sarpras yang boleh masuk
if (
    !isset($_SESSION['id_pengguna']) ||
    ($_SESSION['role'] ?? '') !== 'sarpras'
) {
    header("Location: ../login.php");
    exit;
}

// Rekap jumlah tiket berdasarkan status
$sql_rekap = mysqli_query($conn, "
    SELECT
        COUNT(*) AS total,
        SUM(status = 'Menunggu') AS menunggu,
        SUM(status = 'Diproses') AS diproses,
        SUM(status = 'Selesai') AS selesai
    FROM tiket
");

$rekap = mysqli_fetch_assoc($sql_rekap);

// Rekap kinerja teknisi berdasarkan penugasan terbaru
$sql_kinerja = mysqli_query($conn, "
    SELECT
        u.id_pengguna,
        u.nama_lengkap,
        COUNT(pt.id_tiket) AS jumlah_ditugaskan,
        COALESCE(SUM(t.status = 'Selesai'), 0) AS jumlah_selesai
    FROM pengguna u
    LEFT JOIN (
        SELECT pt1.id_tiket, pt1.id_teknisi
        FROM penugasan_tiket pt1
        WHERE pt1.id_penugasan = (
            SELECT MAX(pt2.id_penugasan)
            FROM penugasan_tiket pt2
            WHERE pt2.id_tiket = pt1.id_tiket
        )
    ) pt ON pt.id_teknisi = u.id_pengguna
    LEFT JOIN tiket t ON t.id_tiket = pt.id_tiket
    WHERE u.role = 'teknisi'
      AND u.status = 'aktif'
    GROUP BY u.id_pengguna, u.nama_lengkap
    ORDER BY u.nama_lengkap ASC
");

if (!$sql_kinerja) {
    die("Gagal mengambil rekap kinerja teknisi.");
}

// Ambil teknisi aktif
$daftar_teknisi = [];

$query_teknisi = mysqli_query(
    $conn,
    "SELECT id_pengguna, nama_lengkap
     FROM pengguna
     WHERE role = 'teknisi' AND status = 'aktif'
     ORDER BY nama_lengkap ASC"
);

if (!$query_teknisi) {
    die("Gagal mengambil data teknisi.");
}

while ($teknisi = mysqli_fetch_assoc($query_teknisi)) {
    $daftar_teknisi[] = $teknisi;
}

// Ambil input pencarian dan filter
$cari = trim($_GET['cari'] ?? '');
$status = $_GET['status'] ?? '';
$prioritas = $_GET['prioritas'] ?? '';

$status_valid = ['', 'Menunggu', 'Diproses', 'Selesai'];
$prioritas_valid = ['', 'rendah', 'sedang', 'tinggi'];

if (!in_array($status, $status_valid, true)) {
    $status = '';
}

if (!in_array($prioritas, $prioritas_valid, true)) {
    $prioritas = '';
}

// Ambil tiket, penugasan terbaru, dan keterangan pengerjaan
$sql = "SELECT
            t.id_tiket,
            t.judul,
            t.deskripsi,
            t.status,
            t.prioritas,
            t.created_at,
            t.foto,
            (
                SELECT r.catatan
                FROM riwayat_tiket r
                WHERE r.id_tiket = t.id_tiket
                  AND r.catatan IS NOT NULL
                  AND r.catatan <> ''
                ORDER BY r.id_riwayat DESC
                LIMIT 1
            ) AS catatan,
            k.nama_kategori,
            p.nama_lengkap,
            teknisi.nama_lengkap AS nama_teknisi,
            pt.id_teknisi AS id_teknisi_aktif
        FROM tiket t
        LEFT JOIN kategori k
            ON t.id_kategori = k.id_kategori
        LEFT JOIN pengguna p
            ON t.id_pengguna = p.id_pengguna
        LEFT JOIN penugasan_tiket pt
            ON pt.id_penugasan = (
                SELECT MAX(pt2.id_penugasan)
                FROM penugasan_tiket pt2
                WHERE pt2.id_tiket = t.id_tiket
            )
        LEFT JOIN pengguna teknisi
            ON teknisi.id_pengguna = pt.id_teknisi
        WHERE 1=1";

$tipe = '';
$parameter = [];

if ($cari !== '') {
    $sql .= " AND (t.judul LIKE ? OR p.nama_lengkap LIKE ?)";
    $kata = "%{$cari}%";
    $tipe .= 'ss';
    $parameter[] = $kata;
    $parameter[] = $kata;
}

if ($status !== '') {
    $sql .= " AND t.status = ?";
    $tipe .= 's';
    $parameter[] = $status;
}

if ($prioritas !== '') {
    $sql .= " AND t.prioritas = ?";
    $tipe .= 's';
    $parameter[] = $prioritas;
}

$sql .= " ORDER BY t.id_tiket DESC";

$query = mysqli_prepare($conn, $sql);

if (!$query) {
    die("Gagal menyiapkan pencarian tiket.");
}

if ($tipe !== '') {
    mysqli_stmt_bind_param($query, $tipe, ...$parameter);
}

mysqli_stmt_execute($query);
$hasil = mysqli_stmt_get_result($query);

if (!$hasil) {
    die("Gagal mengambil data tiket.");
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Kepala Sarpras</title>
</head>
<body>

<h1>Dashboard Kepala Sarpras</h1>

<p>
    Selamat datang,
    <?= htmlspecialchars($_SESSION['nama'] ?? 'Kepala Sarpras') ?>!
</p>

<p>Di sini Kepala Sarpras dapat memantau tiket dan teknisi.</p>

<hr>

<h2>Rekap Pengaduan</h2>

<p>Total Tiket: <strong><?= (int) $rekap['total'] ?></strong></p>
<p>Menunggu: <strong><?= (int) $rekap['menunggu'] ?></strong></p>
<p>Diproses: <strong><?= (int) $rekap['diproses'] ?></strong></p>
<p>Selesai: <strong><?= (int) $rekap['selesai'] ?></strong></p>

<hr>

<h2>Rekap Kinerja Teknisi</h2>

<?php if (mysqli_num_rows($sql_kinerja) > 0): ?>
    <table border="1" cellpadding="8" cellspacing="0">
        <tr>
            <th>Nama Teknisi</th>
            <th>Tiket Ditugaskan</th>
            <th>Tiket Selesai</th>
        </tr>

        <?php while ($kinerja = mysqli_fetch_assoc($sql_kinerja)): ?>
            <tr>
                <td><?= htmlspecialchars($kinerja['nama_lengkap']) ?></td>
                <td><?= (int) $kinerja['jumlah_ditugaskan'] ?></td>
                <td><?= (int) $kinerja['jumlah_selesai'] ?></td>
            </tr>
        <?php endwhile; ?>
    </table>
<?php else: ?>
    <p>Belum ada data teknisi aktif.</p>
<?php endif; ?>

<p><a href="../logout.php">Logout</a></p>

<hr>

<h2>Daftar Tiket Pengaduan</h2>

<form method="GET" action="sarpras.php">
    <input
        type="text"
        name="cari"
        placeholder="Cari judul atau nama pelapor..."
        value="<?= htmlspecialchars($cari) ?>"
    >

    <select name="status">
        <option value="">Semua Status</option>
        <option value="Menunggu" <?= $status === 'Menunggu' ? 'selected' : '' ?>>Menunggu</option>
        <option value="Diproses" <?= $status === 'Diproses' ? 'selected' : '' ?>>Diproses</option>
        <option value="Selesai" <?= $status === 'Selesai' ? 'selected' : '' ?>>Selesai</option>
    </select>

    <select name="prioritas">
        <option value="">Semua Prioritas</option>
        <option value="rendah" <?= $prioritas === 'rendah' ? 'selected' : '' ?>>Rendah</option>
        <option value="sedang" <?= $prioritas === 'sedang' ? 'selected' : '' ?>>Sedang</option>
        <option value="tinggi" <?= $prioritas === 'tinggi' ? 'selected' : '' ?>>Tinggi</option>
    </select>

    <button type="submit">Cari / Filter</button>
    <a href="sarpras.php">Reset</a>
</form>

<hr>

<?php if (mysqli_num_rows($hasil) > 0): ?>

    <?php while ($tiket = mysqli_fetch_assoc($hasil)): ?>
        <article>
            <h3>
                #<?= (int) $tiket['id_tiket'] ?> -
                <?= htmlspecialchars($tiket['judul']) ?>
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
                Status:
                <?= htmlspecialchars($tiket['status']) ?>
            </p>

            <p><strong>Keterangan Pengerjaan:</strong></p>

            <?php if (!empty($tiket['catatan'])): ?>
                <p><?= nl2br(htmlspecialchars($tiket['catatan'])) ?></p>
            <?php else: ?>
                <p>Belum ada keterangan pengerjaan.</p>
            <?php endif; ?>

            <p>
                Prioritas:
                <?= htmlspecialchars($tiket['prioritas']) ?>
            </p>

            <p>
                Tanggal:
                <?= htmlspecialchars($tiket['created_at']) ?>
            </p>

            <?php if (!empty($tiket['foto'])): ?>
                <p>Bukti foto kerusakan:</p>
                <img
                    src="../<?= htmlspecialchars($tiket['foto']) ?>"
                    alt="Foto kerusakan tiket"
                    width="200"
                >
            <?php endif; ?>

            <h4>Penugasan Teknisi</h4>

            <p>
                Teknisi saat ini:
                <strong>
                    <?= htmlspecialchars($tiket['nama_teknisi'] ?? 'Belum ditugaskan') ?>
                </strong>
            </p>

            <form action="../proses_penugasan.php" method="POST">
                <input
                    type="hidden"
                    name="id_tiket"
                    value="<?= (int) $tiket['id_tiket'] ?>"
                >

                <label for="teknisi-<?= (int) $tiket['id_tiket'] ?>">
                    Pilih teknisi:
                </label>

                <select
                    id="teknisi-<?= (int) $tiket['id_tiket'] ?>"
                    name="id_teknisi"
                    required
                >
                    <option value="">-- Pilih Teknisi --</option>

                    <?php foreach ($daftar_teknisi as $teknisi): ?>
                        <option
                            value="<?= (int) $teknisi['id_pengguna'] ?>"
                            <?= (int) $tiket['id_teknisi_aktif'] === (int) $teknisi['id_pengguna'] ? 'selected' : '' ?>
                        >
                            <?= htmlspecialchars($teknisi['nama_lengkap']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit">Tugaskan Teknisi</button>
            </form>

            <p>
                <a href="../chat_tiket.php?id_tiket=<?= (int) $tiket['id_tiket'] ?>">
                    Detail &amp; Chat
                </a>
            </p>

            <hr>
        </article>
    <?php endwhile; ?>

<?php else: ?>
    <p>Tidak ada tiket yang sesuai dengan pencarian atau filter.</p>
<?php endif; ?>

</body>
</html>
