<?php
session_start();
require_once __DIR__ . "/koneksi.php";

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';
$role_dipilih = $_POST['role'] ?? '';

$stmt = mysqli_prepare(
    $conn,
    "SELECT id_pengguna, username, password,
            nama_lengkap, role
     FROM pengguna
     WHERE username = ?"
);

mysqli_stmt_bind_param($stmt, "s", $username);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

if (
    $user &&
    (
        password_verify($password, $user['password']) ||
        $password === $user['password']
    ) &&
    $role_dipilih === $user['role']
) {
    session_regenerate_id(true);

    $_SESSION['id_pengguna'] = $user['id_pengguna'];
    $_SESSION['nama'] = $user['nama_lengkap'];
    $_SESSION['role'] = $user['role'];

    if ($user['role'] === 'siswa') {
        header("Location: dashboard/siswa.php");
    } elseif ($user['role'] === 'teknisi') {
        header("Location: dashboard/teknisi.php");
    } elseif ($user['role'] === 'sarpras') {
        header("Location: dashboard/sarpras.php");
    } else {
        session_unset();
        session_destroy();
        exit("Role pengguna tidak valid.");
    }

    exit;
}

echo "Login gagal. Periksa username, password, dan role yang dipilih.";
?>
