<?php
// ==========================================
// 1. KONEKSI DATABASE MYSQL & SESSION
// ==========================================
session_start();
$host = "localhost";
$user = "root";
$pass = "";
$db   = "crm_sekolah"; 

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

// ==========================================
// 1.1 PROFIL USER DINAMIS DARI SESSION/DB
// ==========================================
$username_login = $_SESSION['username'] ?? $_SESSION['nama'] ?? 'dewi';

$q_user = mysqli_query($conn, "SELECT * FROM users WHERE username = '$username_login' OR nama_lengkap = '$username_login' OR nama = '$username_login' LIMIT 1");
if ($q_user && mysqli_num_rows($q_user) > 0) {
    $user_data_login = mysqli_fetch_assoc($q_user);
    $nama_user    = $user_data_login['nama'] ?? $user_data_login['nama_lengkap'] ?? 'dewi';
    $jabatan_user = $user_data_login['jabatan'] ?? $user_data_login['role'] ?? 'admin';
} else {
    $nama_user    = "dewi";
    $jabatan_user = "admin";
}

// Inisial Huruf untuk Avatar Profile
$words = explode(' ', trim($nama_user));
$inisial = strtoupper(substr($words[0], 0, 1));

// Buat tabel 'pengaturan' otomatis sesuai struktur phpMyAdmin jika belum ada
$create_table = "CREATE TABLE IF NOT EXISTS pengaturan (
    id_pengaturan INT PRIMARY KEY AUTO_INCREMENT,
    nama_instansi VARCHAR(255) NOT NULL,
    alamat TEXT NOT NULL,
    no_telepon VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL,
    tahun_ajaran VARCHAR(20) NOT NULL
)";
mysqli_query($conn, $create_table);

// ==========================================
// 2. CEK & ISI DATA AWAL (DEFAULT)
// ==========================================
$check_data = mysqli_query($conn, "SELECT * FROM pengaturan LIMIT 1");
if (mysqli_num_rows($check_data) == 0) {
    $insert_default = "INSERT INTO pengaturan (id_pengaturan, nama_instansi, alamat, no_telepon, email, tahun_ajaran) 
                       VALUES (1, 'SMA / SMK Bina Nusantara', 'Jl. Pendidikan No. 45, Kota Bandung, Jawa Barat', '022-87654321', 'info@binanusantara.sch.id', '2026/2027')";
    mysqli_query($conn, $insert_default);
}

// ==========================================
// 3. PROSES SIMPAN / UPDATE PENGATURAN
// ==========================================
$alert_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_instansi = trim($_POST['nama_instansi'] ?? '');
    $tahun_ajaran  = trim($_POST['tahun_ajaran'] ?? '');
    $alamat        = trim($_POST['alamat'] ?? '');
    $no_telepon    = trim($_POST['no_telepon'] ?? '');
    $email         = trim($_POST['email'] ?? '');

    // Update data baris pertama (id_pengaturan = 1)
    $stmt = mysqli_prepare($conn, "UPDATE pengaturan SET nama_instansi=?, alamat=?, no_telepon=?, email=?, tahun_ajaran=? WHERE id_pengaturan=1");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "sssss", $nama_instansi, $alamat, $no_telepon, $email, $tahun_ajaran);
        if (mysqli_stmt_execute($stmt)) {
            $alert_msg = '<div class="alert alert-success alert-dismissible fade show py-2" style="font-size: 12px;" role="alert">
                Pengaturan berhasil disimpan ke database!
                <button type="button" class="btn-close py-2" data-bs-dismiss="alert"></button>
            </div>';
        } else {
            $alert_msg = '<div class="alert alert-danger alert-dismissible fade show py-2" style="font-size: 12px;" role="alert">
                Gagal menyimpan data ke database.
                <button type="button" class="btn-close py-2" data-bs-dismiss="alert"></button>
            </div>';
        }
        mysqli_stmt_close($stmt);
    }
}

// ==========================================
// 4. AMBIL DATA TERBARU DARI DATABASE
// ==========================================
$q_get = mysqli_query($conn, "SELECT * FROM pengaturan ORDER BY id_pengaturan ASC LIMIT 1");
$data  = mysqli_fetch_assoc($q_get);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan Sistem - Sistem CRM Sekolah</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome Icon -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body { background-color: #F8F9FA; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; overflow-x: hidden; margin: 0; }
        
        /* SIDEBAR STYLES */
        .sidebar { width: 250px; height: 100vh; background-color: #4a628a; color: white; position: fixed; top: 0; left: 0; padding-top: 20px; z-index: 1000; }
        .sidebar h4 { padding: 0 20px; margin-bottom: 30px; font-weight: bold; font-size: 18px; }
        .sidebar a { color: #e0e6ed; text-decoration: none; display: flex; align-items: center; justify-content: space-between; padding: 12px 20px; font-size: 14px; transition: 0.2s; }
        .sidebar a:hover { background-color: #3b5074; color: white; }
        .sidebar a.active { background-color: #3b5074; border-left: 4px solid white; color: white; font-weight: bold; }
        
        /* MAIN CONTENT & HEADER */
        .main-content { margin-left: 250px; padding: 0; min-height: 100vh; background-color: #F8F9FA; display: flex; flex-direction: column; }
        .top-header { background-color: white; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eaeaea; }
        .content-area { padding: 25px 30px; flex-grow: 1; }
        
        /* FORM CARD STYLES */
        .card-custom { background-color: #ffffff; border: none; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); padding: 25px 30px; }
        .form-label-custom { font-size: 12px; font-weight: 600; color: #333333; margin-bottom: 6px; }
        .form-control-custom { font-size: 13px; border: 1px solid #ced4da; border-radius: 4px; padding: 8px 12px; color: #333; }
        .form-control-custom:focus { border-color: #4a628a; box-shadow: none; }
        
        .btn-simpan { background-color: #4a628a; color: white; font-size: 13px; font-weight: 600; padding: 8px 24px; border-radius: 4px; border: none; transition: 0.2s; }
        .btn-simpan:hover { background-color: #3b5074; color: white; }
    </style>
</head>
<body>

    <!-- SIDEBAR -->
    <div class="sidebar">
        <h4>📈 CRM System</h4>
        <a href="/crm_sekolah/dashboard.php"> 🏠 Dashboard</a>
        <a href="../kunjungan/index.php" style="justify-content: flex-start; gap: 10px;">👥 Kunjungan</a>
        <a href="javascript:void(0);" data-bs-toggle="collapse" data-bs-target="#menuCrm" style="justify-content: space-between; align-items: center;" aria-expanded="false">
            <div style="display: flex; gap: 10px; align-items: center;"><span>👤</span><span>CRM</span></div>
            <span>▼</span>
        </a>
        <div class="collapse" id="menuCrm">
            <div style="background-color: #3b5074; display: flex; flex-direction: column;">
                <a href="/crm_sekolah/crm/index.php" style="padding-left: 45px;">Riwayat Interaksi</a>
                <a href="../crm/tahap.php" style="padding-left: 45px;">Tahap</a>
                <a href="../crm/agent.php" style="padding-left: 45px;">Agent</a>
                <a href="../crm/label_status.php" style="padding-left: 45px;">Label Status</a>
            </div>
        </div>
        <a href="../laporan/index.php" style="justify-content: flex-start; gap: 10px;">📋 Laporan</a>
        <a href="../users/index.php" style="justify-content: flex-start; gap: 10px;">🧑 Pengguna</a>
        <a href="index.php" class="active" style="justify-content: flex-start; gap: 10px;">⚙️ Pengaturan</a>
        <a href="../logout.php" style="position: absolute; bottom: 20px; width: 100%; justify-content: flex-start; gap: 10px;">🚪 Logout</a>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-content">
        <!-- TOP HEADER DINAMIS -->
        <div class="top-header">
            <div>
                <h5 class="m-0 text-dark fw-bold" style="font-size: 18px;">Pengaturan Sistem</h5>
                <span class="text-muted" style="font-size: 12px;">Dashboard / Pengaturan Aplikasi</span>
            </div>
            <div class="d-flex align-items-center text-end">
                <div class="me-3">
                    <span class="d-block fw-bold text-dark" style="font-size: 13px;"><?= htmlspecialchars($nama_user) ?></span>
                    <span class="text-muted" style="font-size: 11px;"><?= htmlspecialchars($jabatan_user) ?></span>
                </div>
                <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 38px; height: 38px; background-color: #dbeafe; color: #1e40af; font-size: 13px;"><?= htmlspecialchars($inisial) ?></div>
            </div>
        </div>

        <div class="content-area">
            <?= $alert_msg ?>

            <div class="card-custom">
                <div class="mb-4">
                    <h6 class="fw-bold text-dark mb-1" style="font-size: 15px;">Konfigurasi Umum Aplikasi</h6>
                    <p class="text-muted mb-0" style="font-size: 12px;">Sesuaikan informasi profil sekolah dan parameter sistem CRM</p>
                </div>

                <!-- FORM PENGATURAN -->
                <form action="index.php" method="POST">
                    <div class="row g-3 mb-3">
                        <!-- Nama Instansi / Sekolah -->
                        <div class="col-md-6">
                            <label class="form-label form-label-custom">Nama Instansi / Sekolah</label>
                            <input type="text" name="nama_instansi" class="form-control form-control-custom" 
                                   value="<?= htmlspecialchars($data['nama_instansi'] ?? '') ?>" required>
                        </div>
                        
                        <!-- Tahun Ajaran Aktif -->
                        <div class="col-md-6">
                            <label class="form-label form-label-custom">Tahun Ajaran Aktif</label>
                            <input type="text" name="tahun_ajaran" class="form-control form-control-custom" 
                                   value="<?= htmlspecialchars($data['tahun_ajaran'] ?? '') ?>" required>
                        </div>
                    </div>

                    <!-- Alamat Lengkap Sekolah -->
                    <div class="mb-3">
                        <label class="form-label form-label-custom">Alamat Lengkap Sekolah</label>
                        <textarea name="alamat" class="form-control form-control-custom" rows="3" required><?= htmlspecialchars($data['alamat'] ?? '') ?></textarea>
                    </div>

                    <div class="row g-3 mb-4">
                        <!-- No Telepon / WhatsApp Resmi -->
                        <div class="col-md-6">
                            <label class="form-label form-label-custom">No Telepon / WhatsApp Resmi</label>
                            <input type="text" name="no_telepon" class="form-control form-control-custom" 
                                   value="<?= htmlspecialchars($data['no_telepon'] ?? '') ?>" required>
                        </div>

                        <!-- Email Resmi Sekolah -->
                        <div class="col-md-6">
                            <label class="form-label form-label-custom">Email Resmi Sekolah</label>
                            <input type="email" name="email" class="form-control form-control-custom" 
                                   value="<?= htmlspecialchars($data['email'] ?? '') ?>" required>
                        </div>
                    </div>

                    <!-- TOMBOL SIMPAN -->
                    <div class="text-end">
                        <button type="submit" class="btn btn-simpan">Simpan Perubahan</button>
                    </div>
                </form>
            </div>

        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>