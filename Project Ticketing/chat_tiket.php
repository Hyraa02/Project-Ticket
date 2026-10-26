
<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

session_start();
require_once __DIR__ . "/koneksi.php";

// Pastikan siswa sudah login
if (!isset($_SESSION['id_pengguna'])) {
    header("Location: login.php");
    exit;
}

$id_pengguna = (int) $_SESSION['id_pengguna'];
$role = $_SESSION['role'] ?? '';
$pesan_error = "";


if (!in_array($role, ['siswa', 'teknisi', 'sarpras'], true)) {
    exit("Akses ditolak.");
}



$id_tiket = (int) ($_GET['id_tiket'] ?? 0);

// Jika belum memilih tiket:
// siswa membuka tiket terbarunya,
// teknisi diarahkan memilih tiket dari dashboard.
if ($id_tiket <= 0 && $role === 'teknisi') {
    header("Location: dashboard/teknisi.php");
    exit;
}

if ($id_tiket > 0) {
    if ($role === 'teknisi' || $role === 'sarpras')  {
        // Teknisi boleh membuka tiket siswa mana pun
        $query_tiket = mysqli_prepare(
            $conn,
            "SELECT t.*, k.nama_kategori
             FROM tiket t
             LEFT JOIN kategori k ON t.id_kategori = k.id_kategori
             WHERE t.id_tiket = ?"
        );

        mysqli_stmt_bind_param($query_tiket, "i", $id_tiket);
    } else {
        // Siswa hanya boleh membuka tiket miliknya sendiri
        $query_tiket = mysqli_prepare(
            $conn,
            "SELECT t.*, k.nama_kategori
             FROM tiket t
             LEFT JOIN kategori k ON t.id_kategori = k.id_kategori
             WHERE t.id_tiket = ? AND t.id_pengguna = ?"
        );

        mysqli_stmt_bind_param(
            $query_tiket,
            "ii",
            $id_tiket,
            $id_pengguna
        );
    }
} else {
    // Ambil tiket terbaru milik siswa yang sedang login
    $query_tiket = mysqli_prepare(
        $conn,
        "SELECT t.*, k.nama_kategori
         FROM tiket t
         LEFT JOIN kategori k ON t.id_kategori = k.id_kategori
         WHERE t.id_pengguna = ?
         ORDER BY t.created_at DESC, t.id_tiket DESC
         LIMIT 1"
    );

    mysqli_stmt_bind_param($query_tiket, "i", $id_pengguna);
}

mysqli_stmt_execute($query_tiket);
$hasil_tiket = mysqli_stmt_get_result($query_tiket);
$tiket = mysqli_fetch_assoc($hasil_tiket);


// Jika siswa belum mempunyai tiket
if (!$tiket) {
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Chat Tiket</title>
    </head>
    <body>
        <h1>Chat Tiket</h1>
        <p>Kamu belum memiliki tiket untuk dibicarakan.</p>
        <a href="buat_tiket.php">Buat Tiket</a>
        <a href="dashboard/siswa.php">Kembali ke Dashboard</a>
    </body>
    </html>
    <?php
    exit;
}

$id_tiket = (int) $tiket['id_tiket'];

// Proses pengiriman pesan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pesan = trim($_POST['pesan'] ?? '');

    if ($pesan === '') {
        $pesan_error = "Pesan tidak boleh kosong.";
    } else {
        $simpan = mysqli_prepare(
            $conn,
            "INSERT INTO chat_tiket
             (id_tiket, id_pengirim, pesan)
             VALUES (?, ?, ?)"
        );

        mysqli_stmt_bind_param(
            $simpan,
            "iis",
            $id_tiket,
            $id_pengguna,
            $pesan
        );

        mysqli_stmt_execute($simpan);

        header("Location: chat_tiket.php?id_tiket=" . $id_tiket);
        exit;
    }
}

// Ambil percakapan untuk tiket yang sedang dibuka
$chat = mysqli_prepare(
    $conn,
    "SELECT c.id_pengirim, c.pesan, c.waktu_kirim,
            p.nama_lengkap
     FROM chat_tiket c
     LEFT JOIN pengguna p ON c.id_pengirim = p.id_pengguna
     WHERE c.id_tiket = ?
     ORDER BY c.waktu_kirim ASC, c.id_chat ASC"
);

mysqli_stmt_bind_param($chat, "i", $id_tiket);
mysqli_stmt_execute($chat);
$hasil_chat = mysqli_stmt_get_result($chat);

// Ambil lampiran jika ada
$lampiran = mysqli_prepare(
    $conn,
    "SELECT nama_file, file_path
     FROM lampiran
     WHERE id_tiket = ?"
);

mysqli_stmt_bind_param($lampiran, "i", $id_tiket);
mysqli_stmt_execute($lampiran);
$hasil_lampiran = mysqli_stmt_get_result($lampiran);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail dan Chat Tiket</title>
</head>
<body>

<h1>Detail Tiket dan Chat</h1>

<?php if ($role === 'sarpras'): ?>
    <a href="dashboard/sarpras.php">Kembali ke Dashboard Sarpras</a>
<?php elseif ($role === 'teknisi'): ?>
    <a href="dashboard/teknisi.php">Kembali ke Dashboard Teknisi</a>
<?php else: ?>
    <a href="tiket_saya.php">Kembali ke Tiket Saya</a>
<?php endif; ?>


<hr>

<div style="display: flex; gap: 24px; align-items: flex-start; flex-wrap: wrap;">

    <!-- BAGIAN KIRI: DETAIL TIKET -->
    <section style="flex: 1; min-width: 280px;">
        <h2>Detail Tiket</h2>

        <p>
            <strong>Nomor Tiket:</strong>
            #<?= (int) $tiket['id_tiket'] ?>
        </p>

        <p>
            <strong>Judul:</strong>
            <?= htmlspecialchars($tiket['judul'] ?? '') ?>
        </p>

        <p>
            <strong>Kategori:</strong>
            <?= htmlspecialchars($tiket['nama_kategori'] ?? '-') ?>
        </p>

        <p>
            <strong>Status:</strong>
            <?= htmlspecialchars($tiket['status'] ?? '-') ?>
        </p>

        <p>
            <strong>Prioritas:</strong>
            <?= htmlspecialchars($tiket['prioritas'] ?? '-') ?>
        </p>

        <p><strong>Deskripsi:</strong></p>
        <p>
            <?= nl2br(htmlspecialchars($tiket['deskripsi'] ?? '')) ?>
        </p>

        <h3>Lampiran</h3>

        <?php if (mysqli_num_rows($hasil_lampiran) > 0): ?>
            <?php while ($file = mysqli_fetch_assoc($hasil_lampiran)): ?>
                <p>
                    <a href="<?= htmlspecialchars($file['file_path']) ?>"
                       target="_blank" rel="noopener">
                        <?= htmlspecialchars($file['nama_file']) ?>
                    </a>
                </p>
            <?php endwhile; ?>
        <?php else: ?>
            <p>Tidak ada lampiran.</p>
        <?php endif; ?>
    </section>

    <!-- BAGIAN KANAN: CHAT TIKET -->
    <section style="flex: 1; min-width: 280px;">
        <h2>Chat Tiket</h2>

        <?php if ($pesan_error !== ''): ?>
            <p><?= htmlspecialchars($pesan_error) ?></p>
        <?php endif; ?>

        <div style="border: 1px solid #ccc; padding: 12px; min-height: 250px;">
            <?php if (mysqli_num_rows($hasil_chat) > 0): ?>
                <?php while ($baris = mysqli_fetch_assoc($hasil_chat)): ?>
                    <div>
                        <strong>
                            <?= htmlspecialchars($baris['nama_lengkap'] ?? 'Pengguna') ?>
                        </strong>

                        <p>
                            <?= nl2br(htmlspecialchars($baris['pesan'])) ?>
                        </p>

                        <small>
                            <?= htmlspecialchars($baris['waktu_kirim']) ?>
                        </small>
                        <hr>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p>Belum ada pesan. Mulai percakapan di sini!</p>
            <?php endif; ?>
        </div>

        <form method="POST" action="chat_tiket.php?id_tiket=<?= $id_tiket ?>">
            <label for="pesan">Tulis pesan</label><br>

            <textarea
                id="pesan"
                name="pesan"
                rows="4"
                required
                style="width: 100%; box-sizing: border-box;"
            ></textarea>

            <br>
            <button type="submit">Kirim Pesan</button>
        </form>
    </section>

</div>

</body>
</html>
