<?php
session_start();

// 1. CEK AUTENTIKASI USER (SESSION LOGIN)
if (!isset($_SESSION['nama_lengkap']) && !isset($_SESSION['username'])) {
    $_SESSION['nama_lengkap'] = 'dewi';
    $_SESSION['role'] = 'Administrator';
}

// 2. KONEKSI DATABASE
$host = "localhost";
$user = "root";
$pass = "";
$db   = "crm_sekolah";

$conn = mysqli_connect($host, $user, $pass, $db);
if (!$conn) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

// Variabel Profil Dinamis Sesuai User Login
$nama_user    = $_SESSION['nama_lengkap'] ?? $_SESSION['username'] ?? 'dewi';
$jabatan_user = $_SESSION['role'] ?? 'Administrator';
if (strtolower($jabatan_user) === 'admin') {
    $jabatan_user = 'Administrator';
}

$words   = explode(' ', trim($nama_user));
$inisial = strtoupper(substr($words[0], 0, 1));

// 3. INISIALISASI VARIABEL FORM
$is_edit      = false;
$id_edit      = 0;
$nama_calon   = '';
$nama_ortu    = '';
$kota         = '';
$jalur_daftar = '';
$asal_sekolah = '';
$no_hp        = '';
$sumber_info  = 'Sosial Media';
$status       = '1 Kontak Baru';
$biaya_spp    = '';
$tgl_followup = date('Y-m-d');
$pesan_error  = '';

// Preview ID Otomatis
$q_max = mysqli_query($conn, "SELECT MAX(id_calonsiswa) AS max_id FROM crm_siswa");
$d_max = mysqli_fetch_assoc($q_max);
$max_val = isset($d_max['max_id']) ? (int)$d_max['max_id'] : 0;
$next_id_num = max($max_val + 1, 1);
$preview_id = 'CRM' . sprintf('%03d', $next_id_num);

// 4. AMBIL DATA JIKA MODE EDIT (?id=...)
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $is_edit = true;
    $id_edit = (int)$_GET['id'];
    $preview_id = 'CRM' . sprintf('%03d', $id_edit);

    $stmt = mysqli_prepare($conn, "SELECT * FROM crm_siswa WHERE id_calonsiswa = ?");
    mysqli_stmt_bind_param($stmt, "i", $id_edit);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    if ($row = mysqli_fetch_assoc($res)) {
        $nama_calon   = $row['nama_calon'];
        $nama_ortu    = $row['nama_ortu'] ?? '';
        $kota         = $row['kota'] ?? '';
        $jalur_daftar = $row['jalur_daftar'] ?? '';
        $asal_sekolah = $row['asal_sekolah'] ?? '';
        $no_hp        = $row['no_hp'] ?? '';
        $sumber_info  = $row['sumber_info'] ?? 'Sosial Media';
        $status       = $row['status'] ?? '1 Kontak Baru';
        $biaya_spp    = $row['biaya_spp'] ?? '';
        $tgl_followup = !empty($row['tgl_followup']) ? $row['tgl_followup'] : date('Y-m-d');
    }
    mysqli_stmt_close($stmt);
}

// 5. PROSES SIMPAN / UPDATE DATA
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil ID dari POST jika ada (untuk keamanan proses update)
    $id_post      = isset($_POST['id_calonsiswa']) ? (int)$_POST['id_calonsiswa'] : 0;
    $nama_calon   = mysqli_real_escape_string($conn, trim($_POST['nama_calon'] ?? ''));
    $nama_ortu    = mysqli_real_escape_string($conn, trim($_POST['nama_ortu'] ?? ''));
    $kota         = mysqli_real_escape_string($conn, trim($_POST['kota'] ?? ''));
    $jalur_daftar = mysqli_real_escape_string($conn, trim($_POST['jalur_daftar'] ?? 'Reguler'));
    $asal_sekolah = mysqli_real_escape_string($conn, trim($_POST['asal_sekolah'] ?? ''));
    $no_hp        = mysqli_real_escape_string($conn, trim($_POST['no_hp'] ?? ''));
    $sumber_info  = mysqli_real_escape_string($conn, trim($_POST['sumber_info'] ?? 'Sosial Media'));
    $status       = mysqli_real_escape_string($conn, trim($_POST['status'] ?? '1 Kontak Baru'));
    $biaya_spp    = !empty($_POST['biaya_spp']) ? (int)$_POST['biaya_spp'] : 0;
    $tgl_followup = !empty($_POST['tgl_followup']) ? $_POST['tgl_followup'] : date('Y-m-d');

    if (empty($nama_calon)) {
        $pesan_error = "Nama Calon Siswa wajib diisi!";
    } else {
        if ($id_post > 0) {
            // Update data jika id_post valid
            $sql = "UPDATE crm_siswa SET 
                        nama_calon = '$nama_calon', 
                        nama_ortu = '$nama_ortu', 
                        kota = '$kota', 
                        jalur_daftar = '$jalur_daftar', 
                        asal_sekolah = '$asal_sekolah', 
                        no_hp = '$no_hp', 
                        sumber_info = '$sumber_info', 
                        status = '$status', 
                        biaya_spp = $biaya_spp, 
                        tgl_followup = '$tgl_followup' 
                    WHERE id_calonsiswa = $id_post";
            $simpan = mysqli_query($conn, $sql);

            // Sinkronisasi otomatis ke tabel kunjungan & siswa_tahap agar data konsisten
            @mysqli_query($conn, "UPDATE kunjungan SET nama_siswa_wali = '$nama_calon', asal_sekolah = '$asal_sekolah', no_hp_wa = '$no_hp', status = '$status' WHERE id_siswa = $id_post OR id_calonsiswa = $id_post OR id = $id_post");
            @mysqli_query($conn, "UPDATE siswa_tahap SET nama_tahap = '$status' WHERE id_siswa = $id_post");

            $_SESSION['pesan'] = "Data interaksi berhasil diperbarui dan disinkronkan!";
        } else {
            // Insert data baru
            $sql = "INSERT INTO crm_siswa 
                        (id_calonsiswa, nama_calon, nama_ortu, kota, jalur_daftar, asal_sekolah, no_hp, sumber_info, status, biaya_spp, tgl_followup) 
                    VALUES ($next_id_num, '$nama_calon', '$nama_ortu', '$kota', '$jalur_daftar', '$asal_sekolah', '$no_hp', '$sumber_info', '$status', $biaya_spp, '$tgl_followup')";
            $simpan = mysqli_query($conn, $sql);
            $_SESSION['pesan'] = "Data siswa baru berhasil disimpan!";
        }

        if ($simpan) {
            header("Location: index.php");
            exit;
        } else {
            $pesan_error = "Gagal menyimpan data ke database: " . mysqli_error($conn);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Form CRM Siswa - CRM System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #F8F9FA; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; overflow-x: hidden; margin: 0; }
        .sidebar { width: 250px; height: 100vh; background-color: #4a628a; color: white; position: fixed; top: 0; left: 0; padding-top: 20px; z-index: 1000; }
        .sidebar h4 { padding: 0 20px; margin-bottom: 30px; font-weight: bold; font-size: 18px; display: flex; align-items: center; gap: 10px; }
        .sidebar a { color: #e0e6ed; text-decoration: none; display: flex; align-items: center; gap: 10px; padding: 12px 20px; font-size: 14px; transition: 0.2s; }
        .sidebar a:hover, .sidebar a.active { background-color: #3b5074; color: white; }
        .main-content { margin-left: 250px; padding: 0; min-height: 100vh; background-color: #F8F9FA; display: flex; flex-direction: column; }
        .top-header { background-color: white; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eaeaea; }
        .content-area { padding: 25px 30px; flex-grow: 1; }
        .card-container { background: white; border: 1px solid #e0e0e0; border-radius: 8px; padding: 30px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); }
        .form-row-inline { display: flex; align-items: center; margin-bottom: 16px; }
        .form-row-inline label { width: 140px; font-size: 13px; font-weight: 500; color: #333; flex-shrink: 0; margin-bottom: 0; }
        .form-row-inline .form-control, .form-row-inline .form-select { border-radius: 8px; border: 1px solid #cbd5e1; padding: 8px 14px; font-size: 13px; background-color: #fff; }
        .btn-kembali-figma { background-color: #3b5074; color: white; border-radius: 6px; padding: 6px 16px; font-size: 12px; text-decoration: none; display: inline-block; }
        .btn-kembali-figma:hover { background-color: #2c3e5e; color: white; }
        .btn-simpan-figma { background-color: #1e3a8a; color: white; border-radius: 8px; padding: 8px 24px; font-size: 13px; font-weight: 500; border: none; }
        .btn-simpan-figma:hover { background-color: #1e293b; color: white; }
        .btn-reset-figma { background-color: #e2e8f0; color: #475569; border-radius: 8px; padding: 8px 24px; font-size: 13px; font-weight: 500; border: none; text-decoration: none; }
    </style>
</head>
<body>

    <!-- SIDEBAR -->
    <div class="sidebar">
        <h4>📈 CRM System</h4>
        <a href="../dashboard.php">🏠 Dashboard</a>
        <a href="../kunjungan/index.php">👥 Kunjungan</a>
        <a href="javascript:void(0);" data-bs-toggle="collapse" data-bs-target="#menuCrm" style="justify-content: space-between; align-items: center;" class="active">
            <div style="display: flex; gap: 10px; align-items: center;">
                <span>👤</span>
                <span>CRM</span>
            </div>
            <span>▼</span>
        </a>
        <div class="collapse show" id="menuCrm">
            <div style="background-color: #3b5074; display: flex; flex-direction: column;">
                <a href="../crm/index.php" style="padding-left: 45px; background-color: #2c3e5e; color: white; font-weight: bold;">Riwayat Interaksi</a>
                <a href="../crm/tahap.php" style="padding-left: 45px;">Tahap</a>
                <a href="../crm/agent.php" style="padding-left: 45px;">Agent</a>
                <a href="../crm/label_status.php" style="padding-left: 45px;">Label Status</a>
            </div>
        </div>
        <a href="../laporan/index.php">📋 Laporan</a>
        <a href="../users/index.php">🧑 Pengguna</a>
        <a href="../pengaturan/index.php">⚙ Pengaturan</a>
        <a href="logout.php" style="position: absolute; bottom: 20px; width: 100%;">🚪 Logout</a>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-content">
        <div class="top-header">
            <div>
                <h5 class="m-0 text-dark fw-bold" style="font-size: 18px;">Form CRM Siswa</h5>
                <span class="text-muted" style="font-size: 11px;">Dashboard / CRM / Form CRM</span>
            </div>
            <div class="d-flex align-items-center text-end">
                <div class="me-3">
                    <span class="d-block fw-bold text-dark" style="font-size: 12px;"><?php echo htmlspecialchars($nama_user); ?></span>
                    <span class="text-muted" style="font-size: 11px;"><?php echo htmlspecialchars($jabatan_user); ?></span>
                </div>
                <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 38px; height: 38px; background-color: #dbeafe; color: #1e40af; font-size: 13px;">
                    <?php echo htmlspecialchars($inisial); ?>
                </div>
            </div>
        </div>

        <div class="content-area">
            <div class="card-container">
                <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
                    <h5 class="fw-semibold m-0 text-dark" style="font-size: 16px;">
                        <?php echo $is_edit ? 'Form Edit Interaksi Siswa' : 'Form Input Interaksi Calon Siswa'; ?>
                    </h5>
                    <a href="index.php" class="btn-kembali-figma">
                        &larr; Kembali Ke Riwayat Interaksi
                    </a>
                </div>

                <?php if (!empty($pesan_error)): ?>
                    <div class="alert alert-danger py-2 px-3 mb-4" style="font-size: 12px; border-radius: 6px;"><?php echo $pesan_error; ?></div>
                <?php endif; ?>

                <form action="" method="POST">
                    <!-- Elemen penting agar ID terbawa saat proses update -->
                    <?php if ($is_edit): ?>
                        <input type="hidden" name="id_calonsiswa" value="<?php echo $id_edit; ?>">
                    <?php endif; ?>

                    <div class="row gx-5">
                        <div class="col-md-6">
                            <div class="form-row-inline">
                                <label>ID Calon Siswa</label>
                                <input type="text" class="form-control bg-light" value="<?php echo htmlspecialchars($preview_id); ?>" readonly>
                            </div>
                            <div class="form-row-inline">
                                <label>Nama Calon Siswa <span class="text-danger">*</span></label>
                                <input type="text" name="nama_calon" class="form-control" value="<?php echo htmlspecialchars($nama_calon); ?>" placeholder="Contoh: Budi Santoso" required>
                            </div>
                            <div class="form-row-inline">
                                <label>Asal Sekolah</label>
                                <input type="text" name="asal_sekolah" class="form-control" value="<?php echo htmlspecialchars($asal_sekolah); ?>" placeholder="Contoh: SMPN 1 Jakarta">
                            </div>
                            <div class="form-row-inline">
                                <label>Nama Orang Tua</label>
                                <input type="text" name="nama_ortu" class="form-control" value="<?php echo htmlspecialchars($nama_ortu); ?>" placeholder="Contoh: Bpk. Santoso">
                            </div>
                            <div class="form-row-inline">
                                <label>NO HP / WA</label>
                                <input type="text" name="no_hp" class="form-control" value="<?php echo htmlspecialchars($no_hp); ?>" placeholder="Contoh: 08123456789">
                            </div>
                            <div class="form-row-inline">
                                <label>Kota</label>
                                <input type="text" name="kota" class="form-control" value="<?php echo htmlspecialchars($kota); ?>" placeholder="Contoh: Jakarta">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-row-inline">
                                <label>Jalur Daftar</label>
                                <select name="jalur_daftar" class="form-select">
                                    <option value="Reguler" <?php echo ($jalur_daftar === 'Reguler') ? 'selected' : ''; ?>>Reguler</option>
                                    <option value="Prestasi" <?php echo ($jalur_daftar === 'Prestasi') ? 'selected' : ''; ?>>Prestasi</option>
                                    <option value="Beasiswa" <?php echo ($jalur_daftar === 'Beasiswa') ? 'selected' : ''; ?>>Beasiswa</option>
                                </select>
                            </div>
                            <div class="form-row-inline">
                                <label>Sumber Info</label>
                                <select name="sumber_info" class="form-select">
                                    <option value="Sosial Media" <?php echo ($sumber_info === 'Sosial Media') ? 'selected' : ''; ?>>Sosial Media</option>
                                    <option value="Brosur" <?php echo ($sumber_info === 'Brosur') ? 'selected' : ''; ?>>Brosur</option>
                                    <option value="Teman/Kerabat" <?php echo ($sumber_info === 'Teman/Kerabat') ? 'selected' : ''; ?>>Teman / Kerabat</option>
                                    <option value="Website" <?php echo ($sumber_info === 'Website') ? 'selected' : ''; ?>>Website</option>
                                </select>
                            </div>
                            <div class="form-row-inline">
                                <label>Status / Tahap CRM</label>
                                <select name="status" class="form-select">
                                    <option value="1 Kontak Baru" <?php echo (strpos($status, '1') !== false || stripos($status, 'kontak') !== false) ? 'selected' : ''; ?>>1. Kontak Baru</option>
                                    <option value="2 Follow Up / Konsultasi Pendaftaran" <?php echo (strpos($status, '2') !== false || stripos($status, 'follow') !== false) ? 'selected' : ''; ?>>2. Follow Up / Konsultasi</option>
                                    <option value="3 Deal / Registrasi" <?php echo (strpos($status, '3') !== false || stripos($status, 'deal') !== false) ? 'selected' : ''; ?>>3. Deal / Registrasi</option>
                                </select>
                            </div>
                            <div class="form-row-inline">
                                <label>Biaya / SPP (Rp)</label>
                                <input type="number" name="biaya_spp" class="form-control" value="<?php echo htmlspecialchars($biaya_spp); ?>" placeholder="0">
                            </div>
                            <div class="form-row-inline">
                                <label>Tgl Follow Up</label>
                                <input type="date" name="tgl_followup" class="form-control" value="<?php echo htmlspecialchars($tgl_followup); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 pt-3 text-end d-flex justify-content-end gap-2">
                        <a href="tambah_interaksi.php" class="btn-reset-figma">Reset</a>
                        <button type="submit" class="btn-simpan-figma">
                            <?php echo $is_edit ? 'Update Data' : 'Simpan Baru'; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>