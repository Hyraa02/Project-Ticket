
<?php
session_start();
require_once __DIR__ . "/koneksi.php";

// Hanya teknisi yang boleh mengubah status
if (
    !isset($_SESSION['id_pengguna']) ||
    ($_SESSION['role'] ?? '') !== 'teknisi'
) {
    header("Location: login.php");
    exit;
}

// Pastikan request menggunakan POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard/teknisi.php");
    exit;
}

$id_tiket = (int) ($_POST['id_tiket'] ?? 0);
$status_baru = $_POST['status'] ?? '';
$catatan = trim($_POST['catatan'] ?? '');
$id_teknisi = (int) $_SESSION['id_pengguna'];

$status_valid = ['Menunggu', 'Diproses', 'Selesai'];

if (
    $id_tiket <= 0 ||
    !in_array($status_baru, $status_valid, true) ||
    $catatan === ''
) {
    exit("ID tiket, status, dan keterangan pengerjaan wajib diisi.");
}

// Pastikan teknisi merupakan pemegang penugasan terbaru
$stmt = mysqli_prepare(
    $conn,
    "SELECT t.status
     FROM tiket t
     INNER JOIN penugasan_tiket pt
        ON pt.id_penugasan = (
            SELECT MAX(pt2.id_penugasan)
            FROM penugasan_tiket pt2
            WHERE pt2.id_tiket = t.id_tiket
        )
     WHERE t.id_tiket = ?
       AND pt.id_teknisi = ?"
);

mysqli_stmt_bind_param($stmt, "ii", $id_tiket, $id_teknisi);
mysqli_stmt_execute($stmt);
$hasil = mysqli_stmt_get_result($stmt);
$tiket = mysqli_fetch_assoc($hasil);

if (!$tiket) {
    exit("Kamu tidak memiliki penugasan aktif untuk tiket ini.");
}

$status_lama = $tiket['status'];

// Simpan perubahan status dan keterangan dalam satu transaksi
mysqli_begin_transaction($conn);

try {
    $stmt_update = mysqli_prepare(
        $conn,
        "UPDATE tiket SET status = ? WHERE id_tiket = ?"
    );

    mysqli_stmt_bind_param(
        $stmt_update,
        "si",
        $status_baru,
        $id_tiket
    );

    if (!mysqli_stmt_execute($stmt_update)) {
        throw new Exception("Gagal memperbarui status tiket.");
    }

    $stmt_riwayat = mysqli_prepare(
        $conn,
        "INSERT INTO riwayat_tiket
         (id_tiket, id_pengguna, status_lama, status_baru, catatan)
         VALUES (?, ?, ?, ?, ?)"
    );

    mysqli_stmt_bind_param(
        $stmt_riwayat,
        "iisss",
        $id_tiket,
        $id_teknisi,
        $status_lama,
        $status_baru,
        $catatan
    );

    if (!mysqli_stmt_execute($stmt_riwayat)) {
        throw new Exception("Gagal menyimpan riwayat pengerjaan.");
    }

    mysqli_commit($conn);
} catch (Throwable $e) {
    mysqli_rollback($conn);
    exit("Perubahan status atau keterangan gagal disimpan. Silakan coba lagi.");
}

header("Location: dashboard/teknisi.php");
exit;
?>
