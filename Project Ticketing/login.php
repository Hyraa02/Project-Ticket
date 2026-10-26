<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Login Helpdesk Sekolah</title>
</head>
<body>
    <h2>Login Helpdesk Sekolah</h2>

    <form action="proses_login.php" method="POST">
        <label for="username">Username</label><br>
        <input type="text" name="username" id="username" required>
        <br><br>

        <label for="password">Password</label><br>
        <input type="password" name="password" id="password" required>
        <br><br>

        <label for="role">Login sebagai:</label><br>
        <select name="role" id="role" required>
            <option value="">-- Pilih Role --</option>
            <option value="siswa">Siswa</option>
            <option value="teknisi">Teknisi</option>
            <option value="sarpras">Sarpras</option>
        </select>
        <br><br>

        <button type="submit">Login</button>
    </form>

    <p>Daftar Sebagai?
        <a href="register.php">Siswa</a>
    </p>
</body>
</html>
