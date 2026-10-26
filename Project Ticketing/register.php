<?php
require_once __DIR__ . "/koneksi.php";

$pesan = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nama = trim($_POST["nama_lengkap"] ?? "");
    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";
    $konfirmasi = $_POST["konfirmasi_password"] ?? "";

    if ($nama === "" || $username === "" || $password === "") {
        $pesan = "Semua kolom wajib diisi!";
    } elseif ($password !== $konfirmasi) {
        $pesan = "Konfirmasi password tidak cocok!";
    } else {
        $cek = mysqli_prepare(
            $conn,
            "SELECT id_pengguna FROM pengguna WHERE username = ?"
        );

        mysqli_stmt_bind_param($cek, "s", $username);
        mysqli_stmt_execute($cek);
        $hasil = mysqli_stmt_get_result($cek);

        if (mysqli_num_rows($hasil) > 0) {
            $pesan = "Username sudah digunakan!";
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $role = "siswa";

            $stmt = mysqli_prepare(
                $conn,
                "INSERT INTO pengguna (nama_lengkap, username, password, role)
                 VALUES (?, ?, ?, ?)"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "ssss",
                $nama,
                $username,
                $password_hash,
                $role
            );

            if (mysqli_stmt_execute($stmt)) {
                header("Location: login.php");
                exit;
            } else {
                $pesan = "Registrasi gagal. Periksa struktur tabel database.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Register Siswa</title>
</head>
<body>
    <h2>Register Siswa Helpdesk Sekolah</h2>

    <?php if ($pesan !== ""): ?>
        <p><?= htmlspecialchars($pesan) ?></p>
    <?php endif; ?>

    <form action="register.php" method="POST">
        <label>Nama Lengkap</label><br>
        <input type="text" name="nama_lengkap" required>
        <br><br>

        <label>Username</label><br>
        <input type="text" name="username" required>
        <br><br>

        <label>Password</label><br>
        <input type="password" name="password" required>
        <br><br>

        <label>Konfirmasi Password</label><br>
        <input type="password" name="konfirmasi_password" required>
        <br><br>

        <button type="submit">Daftar</button>
    </form>

    <p>Sudah punya akun? <a href="login.php">Login di sini</a></p>
</body>
</html>