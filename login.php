<?php
session_start();

// Koneksi Database
$host = "localhost";
$user = "root";
$pass = "";
$db   = "crm_sekolah"; 

$conn = mysqli_connect($host, $user, $pass, $db);
if (!$conn) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

$pesan = "";
$tipe_pesan = "";

// Tangkap aksi POST (apakah register atau login)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';

    // ================= PROSES REGISTER =================
    if ($action == "register") {
        $nama_lengkap = mysqli_real_escape_string($conn, $_POST['nama_lengkap']);
        $email        = mysqli_real_escape_string($conn, $_POST['email']);
        $role         = mysqli_real_escape_string($conn, $_POST['role']);
        $username     = mysqli_real_escape_string($conn, $_POST['username']);
        $password     = $_POST['password'];

        // Enkripsi password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Cek apakah username atau email sudah ada
        $cek_user = mysqli_query($conn, "SELECT * FROM users WHERE username = '$username' OR email = '$email'");
        if (mysqli_num_rows($cek_user) > 0) {
            $pesan = "Username atau email sudah digunakan, silakan pilih yang lain!";
            $tipe_pesan = "danger";
        } else {
            // Query INSERT (menyimpan email)
            $query = "INSERT INTO users (nama_lengkap, email, role, username, password) 
                      VALUES ('$nama_lengkap', '$email', '$role', '$username', '$hashed_password')";
            
            if (mysqli_query($conn, $query)) {
                $pesan = "Pendaftaran berhasil! Silakan pindah ke tab Masuk.";
                $tipe_pesan = "success";
            } else {
                $pesan = "Gagal mendaftar: " . mysqli_error($conn);
                $tipe_pesan = "danger";
            }
        }
    }

    // ================= PROSES LOGIN =================
    if ($action == "login") {
        $role_input = mysqli_real_escape_string($conn, $_POST['role']);
        $username   = mysqli_real_escape_string($conn, $_POST['username']);
        $password   = $_POST['password'];

        $query = "SELECT * FROM users WHERE username = '$username' OR email = '$username'";
        $result = mysqli_query($conn, $query);

        if ($result && mysqli_num_rows($result) == 1) {
            $row = mysqli_fetch_assoc($result);
            
            // Cek password (mendukung teks biasa di database ataupun yang sudah di-hash)
            $db_password = $row['password'];
            $is_password_valid = false;

            if (password_verify($password, $db_password) || $password === $db_password) {
                $is_password_valid = true;
            }

            if ($is_password_valid) {
                $_SESSION['user_id'] = $row['id_user'] ?? $row['id'] ?? 1;
                $_SESSION['nama_lengkap'] = $row['nama_lengkap'] ?? $row['nama'] ?? $row['username'];
                $_SESSION['nama'] = $row['nama'] ?? $row['nama_lengkap'] ?? $row['username'];
                $_SESSION['email'] = $row['email'] ?? '';
                $_SESSION['role'] = $row['role'] ?? 'Administrator';
                $_SESSION['username'] = $row['username'];

                header("Location: dashboard.php");
                exit;
            } else {
                $pesan = "Kata sandi salah!";
                $tipe_pesan = "danger";
            }
        } else {
            $pesan = "Username atau email tidak ditemukan di database!";
            $tipe_pesan = "danger";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CRM & Buku Tamu - Login</title>
    
    <!-- Menggunakan Bootstrap 5 untuk layout dasar yang rapi -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        body {
            background-color: #f8f9fa;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
        }
        .auth-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 25px rgba(0,0,0,0.05);
            padding: 40px;
            width: 100%;
            max-width: 420px;
        }
        .logo-box {
            width: 48px;
            height: 48px;
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 22px;
        }
        .auth-title {
            text-align: center;
            font-weight: 700;
            color: #212529;
            margin-bottom: 5px;
            font-size: 1.25rem;
        }
        .auth-subtitle {
            text-align: center;
            font-size: 13px;
            color: #6c757d;
            margin-bottom: 25px;
        }
        /* Toggle Tabs */
        .toggle-container {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 30px;
        }
        .toggle-btn {
            border: none;
            background: none;
            font-weight: 600;
            font-size: 14px;
            color: #6c757d;
            padding: 8px 24px;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .toggle-btn.active {
            background-color: #4a6282;
            color: white;
        }
        /* Form Styles */
        .form-label {
            font-size: 13px;
            color: #495057;
            font-weight: 500;
            margin-bottom: 6px;
        }
        .form-control, .form-select {
            font-size: 14px;
            padding: 10px 15px;
            border-radius: 6px;
            border: 1px solid #ced4da;
            margin-bottom: 15px;
        }
        .form-control:focus, .form-select:focus {
            border-color: #4a6282;
            box-shadow: 0 0 0 0.2rem rgba(74, 98, 130, 0.25);
        }
        /* Buttons */
        .btn-login {
            background-color: #4a6282;
            color: white;
            border: none;
            padding: 10px;
            font-weight: 600;
            border-radius: 6px;
            width: 100%;
            margin-top: 10px;
        }
        .btn-login:hover {
            background-color: #3b4e68;
            color: white;
        }
        .btn-register {
            background-color: #198754;
            color: white;
            border: none;
            padding: 10px;
            font-weight: 600;
            border-radius: 6px;
            width: 100%;
            margin-top: 10px;
        }
        .btn-register:hover {
            background-color: #157347;
            color: white;
        }
        .auth-footer {
            text-align: center;
            font-size: 11px;
            color: #adb5bd;
            margin-top: 30px;
        }
        /* Form Sections */
        .form-section {
            display: none;
            animation: fadeIn 0.4s ease;
        }
        .form-section.active {
            display: block;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(5px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>

    <div class="auth-card">
        <!-- Logo & Title -->
        <div class="logo-box">
            📈
        </div>
        <div class="auth-title">CRM & Buku Tamu</div>
        <div class="auth-subtitle">Sistem Informasi Terpadu Sekolah</div>

        <!-- Tampilkan Notifikasi Pesan (jika ada) -->
        <?php if (!empty($pesan)): ?>
            <div class="alert alert-<?php echo $tipe_pesan; ?> alert-dismissible fade show py-2" role="alert" style="font-size: 13px;">
                <?php echo $pesan; ?>
                <button type="button" class="btn-close py-2" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Toggle Tabs -->
        <div class="toggle-container">
            <button class="toggle-btn active" id="btn-tab-login" onclick="switchTab('login')">Masuk</button>
            <button class="toggle-btn" id="btn-tab-register" onclick="switchTab('register')">Daftar Akun</button>
        </div>

        <!-- FORM LOGIN -->
        <div id="form-login" class="form-section active">
            <form action="login.php" method="POST">
                <input type="hidden" name="action" value="login">
                
                <label class="form-label">Pilih Peran Pengguna (Role)</label>
                <select name="role" class="form-select" required>
                    <option value="" selected disabled>-- Pilih Hak Akses --</option>
                    <option value="Administrator">🧑 Administrator / Tata Usaha</option>
                    <option value="agent">🎧 Agent CRM / Tim Follow-up</option>
                    <option value="resepsionis">📋 Petugas Resepsionis / Kunjungan</option>
                </select>

                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-control" placeholder="Masukkan username" required>

                <label class="form-label">Kata Sandi (Password)</label>
                <input type="password" name="password" class="form-control" placeholder="Masukkan password" required>

                <button type="submit" class="btn-login">Masuk ke Sistem</button>
            </form>
        </div>

        <!-- FORM DAFTAR AKUN -->
        <div id="form-register" class="form-section">
            <form action="login.php" method="POST">
                <input type="hidden" name="action" value="register">

                <label class="form-label">Nama Lengkap</label>
                <input type="text" name="nama_lengkap" class="form-control" placeholder="Contoh: Budi Santoso" required>

                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" placeholder="Contoh: budi@sekolah.sch.id" required>

                <label class="form-label">Pilih Peran Pengguna (Role)</label>
                <select name="role" class="form-select" required>
                    <option value="" selected disabled>-- Pilih Hak Akses --</option>
                    <option value="Administrator">🧑 Administrator / Tata Usaha</option>
                    <option value="agent">🎧 Agent CRM / Tim Follow-up</option>
                    <option value="resepsionis">📋 Petugas Resepsionis / Kunjungan</option>
                </select>

                <label class="form-label">Username Akun</label>
                <input type="text" name="username" class="form-control" placeholder="Buat username" required>

                <label class="form-label">Kata Sandi (Password)</label>
                <input type="password" name="password" class="form-control" placeholder="Buat password" required>

                <button type="submit" class="btn-register">Daftar Sekarang</button>
            </form>
        </div>

        <!-- Footer -->
        <div class="auth-footer">
            © 2026 Sistem CRM & Kunjungan Sekolah
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- JavaScript untuk toggle tab -->
    <script>
        function switchTab(tabName) {
            document.getElementById('form-login').classList.remove('active');
            document.getElementById('form-register').classList.remove('active');
            
            document.getElementById('btn-tab-login').classList.remove('active');
            document.getElementById('btn-tab-register').classList.remove('active');

            if (tabName === 'login') {
                document.getElementById('form-login').classList.add('active');
                document.getElementById('btn-tab-login').classList.add('active');
            } else if (tabName === 'register') {
                document.getElementById('form-register').classList.add('active');
                document.getElementById('btn-tab-register').classList.add('active');
            }
        }
    </script>
</body>
</html>