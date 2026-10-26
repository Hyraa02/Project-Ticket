<?php
session_start();
require_once __DIR__ . "/koneksi.php";

if (!isset($_SESSION['id_pengguna'])) {
    header("Location: ../login.php");
    exit;
}

$pesan = "";
$nama = $_SESSION['nama'] ?? 'Siswa';

// Mengambil daftar kategori dari database
$kategori = mysqli_query(
    $conn,
    "SELECT id_kategori, nama_kategori FROM kategori ORDER BY nama_kategori"
);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $judul = trim($_POST['judul'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $id_kategori = (int) ($_POST['id_kategori'] ?? 0);
    $prioritas = $_POST['prioritas'] ?? 'sedang';
    $id_pengguna = (int) $_SESSION['id_pengguna'];

    $prioritasValid = ['rendah', 'sedang', 'tinggi'];

    // Memeriksa input wajib
    if ($judul === '' || $deskripsi === '' || $id_kategori < 1) {
        $pesan = "Judul, kategori, dan deskripsi wajib diisi!";
    } elseif (!in_array($prioritas, $prioritasValid, true)) {
        $pesan = "Pilihan prioritas tidak valid!";
    } else {
        // Memastikan kategori benar-benar tersedia
        $cekKategori = mysqli_prepare(
            $conn,
            "SELECT id_kategori FROM kategori WHERE id_kategori = ?"
        );

        mysqli_stmt_bind_param($cekKategori, "i", $id_kategori);
        mysqli_stmt_execute($cekKategori);
        $hasilKategori = mysqli_stmt_get_result($cekKategori);

        if (mysqli_num_rows($hasilKategori) === 0) {
            $pesan = "Kategori tidak ditemukan!";
        } else {
            $file = $_FILES['foto'] ?? null;
            $adaFoto = $file && $file['error'] !== UPLOAD_ERR_NO_FILE;
            $namaFile = null;
            $pathRelatif = null;
            $pathAbsolut = null;

          
            if ($adaFoto) {
                if ($file['error'] !== UPLOAD_ERR_OK) {
                    $pesan = "Upload foto gagal!";
                } elseif ($file['size'] > 5 * 1024 * 1024) {
                    $pesan = "Ukuran foto maksimal 5 MB!";
                } else {
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mime = $finfo->file($file['tmp_name']);

                    $jenisFoto = [
                        'image/jpeg' => 'jpg',
                        'image/png'  => 'png',
                        'image/webp' => 'webp'
                    ];

                    if (!isset($jenisFoto[$mime])) {
                        $pesan = "Foto harus berformat JPG, PNG, atau WEBP!";
                    } else {
                        $folderUpload = __DIR__ . "/uploads";

                        if (
                            !is_dir($folderUpload) &&
                            !mkdir($folderUpload, 0755, true)
                        ) {
                            $pesan = "Folder upload gagal dibuat!";
                        } else {
                            $namaFile = bin2hex(random_bytes(16))
                                . "." . $jenisFoto[$mime];

                            $pathAbsolut = $folderUpload . "/" . $namaFile;
                            $pathRelatif = "uploads/" . $namaFile;
                        }
                    }
                }
            }

            if ($pesan === "") {
                mysqli_begin_transaction($conn);

                try {
                    // Menyimpan data tiket
                    $stmt = mysqli_prepare(
                        $conn,
                        "INSERT INTO tiket
                        (id_pengguna, id_kategori, judul, deskripsi,
                         foto, status, prioritas)
                        VALUES (?, ?, ?, ?, ?, 'menunggu', ?)"
                    );

                    mysqli_stmt_bind_param(
                        $stmt,
                        "iissss",
                        $id_pengguna,
                        $id_kategori,
                        $judul,
                        $deskripsi,
                        $pathRelatif,
                        $prioritas
                    );

                    if (!mysqli_stmt_execute($stmt)) {
                        throw new Exception("Tiket gagal disimpan.");
                    }

                    $id_tiket = mysqli_insert_id($conn);

                    // Memindahkan foto dan menyimpan datanya
                    if ($adaFoto) {
                        if (!move_uploaded_file(
                            $file['tmp_name'],
                            $pathAbsolut
                        )) {
                            throw new Exception("Foto gagal diunggah.");
                        }

                        $stmtFoto = mysqli_prepare(
                            $conn,
                            "INSERT INTO lampiran
                            (id_tiket, nama_file, file_path, tipe_file)
                            VALUES (?, ?, ?, ?)"
                        );

                        mysqli_stmt_bind_param(
                            $stmtFoto,
                            "isss",
                            $id_tiket,
                            $namaFile,
                            $pathRelatif,
                            $mime
                        );

                        if (!mysqli_stmt_execute($stmtFoto)) {
                            throw new Exception(
                                "Data foto gagal disimpan."
                            );
                        }
                    }

                    mysqli_commit($conn);

                    header("Location: tiket_saya.php?berhasil=1");
                    exit;

                } catch (Throwable $e) {
                    mysqli_rollback($conn);

                    if ($pathAbsolut && is_file($pathAbsolut)) {
                        unlink($pathAbsolut);
                    }

                    $pesan = $e instanceof Exception
                        ? $e->getMessage()
                        : "Terjadi kesalahan saat menyimpan tiket.";
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buat Tiket - Helpdesk Sekolah</title>
    <link rel="stylesheet" href="buat_tiket.css">
</head>
<body>

<header class="header">
    <h2>Helpdesk Sekolah</h2>
    <a href="dashboard.php" class="btn btn-light">Kembali</a>
</header>

<main class="container">
    <section class="form-card">
        <h1>Buat Tiket Kerusakan</h1>
        <p class="subtitle">
            Halo, <?= htmlspecialchars($nama) ?>.
            Jelaskan kerusakan fasilitas yang ingin kamu laporkan.
        </p>

        <?php if ($pesan !== ""): ?>
            <div class="alert">
                <?= htmlspecialchars($pesan) ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">

            <label for="judul">Judul Kerusakan</label>
            <input
                type="text"
                id="judul"
                name="judul"
                maxlength="100"
                value="<?= htmlspecialchars($_POST['judul'] ?? '') ?>"
                placeholder="Contoh: AC tidak dingin"
                required
            >

            <label for="id_kategori">Kategori</label>
            <select id="id_kategori" name="id_kategori" required>
                <option value="">-- Pilih Kategori --</option>

                <?php while ($k = mysqli_fetch_assoc($kategori)): ?>
                    <option
                        value="<?= (int) $k['id_kategori'] ?>"
                        <?= (($_POST['id_kategori'] ?? '') == $k['id_kategori']) ? 'selected' : '' ?>
                    >
                        <?= htmlspecialchars($k['nama_kategori']) ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <label for="deskripsi">Deskripsi Kerusakan</label>
            <textarea
                id="deskripsi"
                name="deskripsi"
                rows="5"
                placeholder="Jelaskan kerusakan dan lokasi fasilitas..."
                required
            ><?= htmlspecialchars($_POST['deskripsi'] ?? '') ?></textarea>

            <label for="prioritas">Prioritas</label>
            <select id="prioritas" name="prioritas" required>
                <option value="rendah">Rendah</option>
                <option value="sedang" selected>Sedang</option>
                <option value="tinggi">Tinggi</option>
            </select>

            <label for="foto">Foto Bukti (Opsional)</label>
            <input
                type="file"
                id="foto"
                name="foto"
                accept=".jpg,.jpeg,.png,.webp"
            >
            <small>Format JPG, PNG, atau WEBP. Maksimal 5 MB.</small>

            <button type="submit" class="btn btn-submit">
                Kirim Tiket
            </button>
        </form>
    </section>
</main>

</body>
</html>
