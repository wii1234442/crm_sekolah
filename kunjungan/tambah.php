<?php
session_start();

$host = "localhost";
$user = "root";
$pass = "";
$db   = "crm_sekolah";

$conn = mysqli_connect($host, $user, $pass, $db);
if (!$conn) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

// 1. CEK MODE EDIT ATAU TAMBAH
$id_kunjungan = $_GET['id'] ?? '';
$is_edit = !empty($id_kunjungan);

// Logika untuk mendeteksi celah ID kosong terendah
$q_id = mysqli_query($conn, "SELECT id_kunjungan FROM kunjungan ORDER BY id_kunjungan ASC");
$next_id_num = 1;
$existing_ids = [];
while ($row_id = mysqli_fetch_assoc($q_id)) {
    $existing_ids[] = (int)$row_id['id_kunjungan'];
}

while (in_array($next_id_num, $existing_ids)) {
    $next_id_num++;
}

// Form default jika mode tambah baru
$d = [
    'id_kunjungan'    => $is_edit ? '' : $next_id_num,
    'nama_siswa_wali' => '',
    'asal_sekolah'    => '',
    'nama_pic_staf'   => '',
    'no_hp_wa'        => '',
    'alamat'          => '',
    'status'          => 'Deal',
    'tgl_follow_up'   => '',
    'tgl_kunjungan'   => date('Y-m-d'),
    'jam'             => date('H:i'),
    'hasil_kunjungan' => '',
    'jenis_kunjungan' => 'Konsultasi Pendaftaran',
    'link_ig'         => '',
    'link_tiktok'     => '',
    'upload_foto'     => ''
];

// AMBIL DATA LAMA DARI DATABASE JIKA DALAM MODE EDIT
if ($is_edit) {
    $id_clean = mysqli_real_escape_string($conn, $id_kunjungan);
    $q_data = mysqli_query($conn, "SELECT * FROM kunjungan WHERE id_kunjungan = '$id_clean'");
    if ($data_db = mysqli_fetch_assoc($q_data)) {
        $d = array_merge($d, $data_db);
    }
}

// 2. PROSES SIMPAN (INSERT / UPDATE) DENGAN SINKRONISASI PIC LENGKAP
if (isset($_POST['simpan'])) {
    $nama_siswa_wali = mysqli_real_escape_string($conn, $_POST['nama_siswa_wali'] ?? '');
    $asal_sekolah    = mysqli_real_escape_string($conn, $_POST['asal_sekolah'] ?? '');
    $nama_pic_staf   = mysqli_real_escape_string($conn, $_POST['nama_pic_staf'] ?? '');
    $no_hp_wa        = mysqli_real_escape_string($conn, $_POST['no_hp_wa'] ?? '');
    $alamat          = mysqli_real_escape_string($conn, $_POST['alamat'] ?? '');
    $status          = mysqli_real_escape_string($conn, $_POST['status'] ?? 'Pending');
    $tgl_follow_up   = !empty($_POST['tgl_follow_up']) ? "'" . mysqli_real_escape_string($conn, $_POST['tgl_follow_up']) . "'" : "NULL";
    $tgl_kunjungan   = mysqli_real_escape_string($conn, $_POST['tgl_kunjungan'] ?? date('Y-m-d'));
    $jam             = mysqli_real_escape_string($conn, $_POST['jam'] ?? date('H:i:s'));
    $hasil_kunjungan = mysqli_real_escape_string($conn, $_POST['hasil_kunjungan'] ?? '');
    $jenis_kunjungan = mysqli_real_escape_string($conn, $_POST['jenis_kunjungan'] ?? '');
    $link_ig         = mysqli_real_escape_string($conn, $_POST['link_ig'] ?? '');
    $link_tiktok     = mysqli_real_escape_string($conn, $_POST['link_tiktok'] ?? '');

    // Olah file Foto Dokumentasi
    $foto_name = $d['upload_foto'];
    if (isset($_FILES['upload_foto']) && $_FILES['upload_foto']['error'] === 0) {
        $ext = strtolower(pathinfo($_FILES['upload_foto']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        if (in_array($ext, $allowed)) {
            $foto_name = time() . '_' . rand(100, 999) . '.' . $ext;
            if (!is_dir("../uploads")) { mkdir("../uploads", 0777, true); }
            move_uploaded_file($_FILES['upload_foto']['tmp_name'], "../uploads/" . $foto_name);
        }
    }

    if ($is_edit) {
        $old_hp   = mysqli_real_escape_string($conn, $d['no_hp_wa']);
        $old_nama = mysqli_real_escape_string($conn, $d['nama_siswa_wali']);

        // A. Update tabel kunjungan
        $query = "UPDATE kunjungan SET 
                    nama_siswa_wali = '$nama_siswa_wali',
                    asal_sekolah    = '$asal_sekolah',
                    nama_pic_staf   = '$nama_pic_staf',
                    no_hp_wa        = '$no_hp_wa',
                    alamat          = '$alamat',
                    status          = '$status',
                    tgl_follow_up   = $tgl_follow_up,
                    tgl_kunjungan   = '$tgl_kunjungan',
                    jam             = '$jam',
                    hasil_kunjungan = '$hasil_kunjungan',
                    jenis_kunjungan = '$jenis_kunjungan',
                    upload_foto     = '$foto_name'
                  WHERE id_kunjungan = '$id_clean'";
        mysqli_query($conn, $query);

        // B. Update tabel crm_siswa
        $tgl_fu_val = !empty($_POST['tgl_follow_up']) ? mysqli_real_escape_string($conn, $_POST['tgl_follow_up']) : date('Y-m-d');
        mysqli_query($conn, "UPDATE crm_siswa SET 
                                nama_calon = '$nama_siswa_wali', 
                                asal_sekolah = '$asal_sekolah', 
                                no_hp = '$no_hp_wa', 
                                status = '$status',
                                tgl_followup = '$tgl_fu_val'
                              WHERE id_calonsiswa = '$id_clean' 
                                 OR no_hp = '$old_hp' 
                                 OR no_hp = '$no_hp_wa' 
                                 OR nama_calon = '$old_nama'");

        // C. Update PIC di tabel siswa_tahap agar sinkron ke halaman Tahap
        mysqli_query($conn, "UPDATE siswa_tahap SET pic = '$nama_pic_staf' WHERE id_siswa = '$id_clean'");

        header("Location: index.php");
        exit;
    } else {
        // A. Insert data baru ke tabel kunjungan
        $query = "INSERT INTO kunjungan 
                    (id_kunjungan, nama_siswa_wali, asal_sekolah, nama_pic_staf, no_hp_wa, alamat, status, tgl_follow_up, tgl_kunjungan, jam, hasil_kunjungan, jenis_kunjungan, upload_foto) 
                  VALUES 
                    ($next_id_num, '$nama_siswa_wali', '$asal_sekolah', '$nama_pic_staf', '$no_hp_wa', '$alamat', '$status', $tgl_follow_up, '$tgl_kunjungan', '$jam', '$hasil_kunjungan', '$jenis_kunjungan', '$foto_name')";
        mysqli_query($conn, $query);

        // B. Insert/Update ke crm_siswa
        $tgl_fu_val = !empty($_POST['tgl_follow_up']) ? mysqli_real_escape_string($conn, $_POST['tgl_follow_up']) : date('Y-m-d');
        mysqli_query($conn, "INSERT INTO crm_siswa 
                    (id_calonsiswa, nama_calon, asal_sekolah, no_hp, status, tgl_followup) 
                  VALUES 
                    ($next_id_num, '$nama_siswa_wali', '$asal_sekolah', '$no_hp_wa', '$status', '$tgl_fu_val')
                  ON DUPLICATE KEY UPDATE 
                    nama_calon = '$nama_siswa_wali', asal_sekolah = '$asal_sekolah', no_hp = '$no_hp_wa', status = '$status', tgl_followup = '$tgl_fu_val'");

        // C. Insert ke siswa_tahap dengan PIC yang diinputkan
        mysqli_query($conn, "INSERT INTO siswa_tahap (id_siswa, id_label, id_tahap, nama_tahap, tgl_mulai, pic) 
                             VALUES ($next_id_num, 1, 1, '1 Kontak Baru', CURRENT_DATE(), '$nama_pic_staf')
                             ON DUPLICATE KEY UPDATE pic = '$nama_pic_staf'");

        header("Location: index.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_edit ? 'Edit Kunjungan' : 'Form Kunjungan Baru'; ?> - CRM System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body { background-color: #F8F9FA; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; }
        .sidebar { width: 250px; height: 100vh; background-color: #4a628a; color: white; position: fixed; top: 0; left: 0; padding-top: 20px; z-index: 1000; }
        .sidebar h4 { padding: 0 20px; margin-bottom: 30px; font-weight: bold; font-size: 18px; display: flex; align-items: center; gap: 10px; }
        .sidebar a { color: #e0e6ed; text-decoration: none; display: flex; align-items: center; gap: 10px; padding: 12px 20px; font-size: 14px; transition: 0.2s; }
        .sidebar a:hover, .sidebar a.active { background-color: #3b5074; color: white; }
        
        .main-content { margin-left: 250px; padding: 25px 30px; min-height: 100vh; }
        .card-container { background: white; border: 1px solid #e0e0e0; border-radius: 8px; padding: 30px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); }
        .form-label { font-size: 12px; font-weight: 600; color: #495057; margin-bottom: 4px; }
        .form-control, .form-select { font-size: 13px; padding: 8px 12px; border-radius: 6px; }
        .btn-navy { background-color: #0b2545; color: white; border: none; }
        .btn-navy:hover { background-color: #13315c; color: white; }
    </style>
</head>
<body>

    <!-- SIDEBAR -->
    <div class="sidebar">
        <h4>📈 CRM System</h4>
        <a href="../dashboard.php">🏠 Dashboard</a>
        <a href="../index.php" class="active">👥 Kunjungan</a>
        <a href="javascript:void(0);" data-bs-toggle="collapse" data-bs-target="#menuCrm" style="justify-content: space-between; align-items: center;">
            <div style="display: flex; gap: 10px; align-items: center;">
                <span>👤</span>
                <span>CRM</span>
            </div>
            <span>▼</span>
        </a>
        <div class="collapse" id="menuCrm">
            <div style="background-color: #3b5074; display: flex; flex-direction: column;">
                <a href="../crm/index.php" style="padding-left: 45px;">Riwayat Interaksi</a>
                <a href="../crm/tahap.php" style="padding-left: 45px;">Tahap</a>
                <a href="../crm/agent.php" style="padding-left: 45px;">Agent</a>
                <a href="../crm/label_status.php" style="padding-left: 45px;">Label Status</a>
            </div>
        </div>
        <a href="../laporan/index.php">📋 Laporan</a>
        <a href="../users/index.php">🧑 Pengguna</a>
        <a href="../pengaturan/index.php">⚙️ Pengaturan</a>
        <a href="../logout.php" style="position: absolute; bottom: 20px; width: 100%;">🚪 Logout</a>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-content">
        <div class="card-container">
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="fw-bold m-0 text-dark"><?php echo $is_edit ? 'Edit Data Kunjungan' : 'Form Input Kunjungan'; ?></h5>
                <a href="index.php" class="btn btn-outline-secondary btn-sm" style="font-size: 12px;">
                    <i class="fa fa-arrow-left me-1"></i> Kembali ke Riwayat Kunjungan
                </a>
            </div>

            <form action="" method="POST" enctype="multipart/form-data">
                <div class="row g-3">
                    
                    <!-- Kiri -->
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">ID Kunjungan</label>
                            <input type="text" class="form-control bg-light" value="<?php echo htmlspecialchars('KJN' . sprintf('%03d', $is_edit ? $d['id_kunjungan'] : $next_id_num)); ?>" readonly>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Nama Siswa/Wali</label>
                            <input type="text" name="nama_siswa_wali" class="form-control" placeholder="Nama lengkap" required value="<?php echo htmlspecialchars($d['nama_siswa_wali']); ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Nama Pic (Staf)</label>
                            <input type="text" name="nama_pic_staf" class="form-control" placeholder="Masukkan nama PIC / Petugas" required value="<?php echo htmlspecialchars($d['nama_pic_staf']); ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Asal Sekolah</label>
                            <input type="text" name="asal_sekolah" class="form-control" placeholder="Contoh: SMPN 1 Bandung" required value="<?php echo htmlspecialchars($d['asal_sekolah']); ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">No.HP / WA</label>
                            <input type="text" name="no_hp_wa" class="form-control" placeholder="08123456789" required value="<?php echo htmlspecialchars($d['no_hp_wa']); ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Alamat</label>
                            <textarea name="alamat" class="form-control" rows="3" placeholder="Alamat Lengkap"><?php echo htmlspecialchars($d['alamat']); ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Dokumentasi</label>
                            <input type="file" name="upload_foto" class="form-control" accept="image/*">
                            <?php if (!empty($d['upload_foto'])): ?>
                                <small class="text-muted d-block mt-1">File saat ini: <strong><?php echo htmlspecialchars($d['upload_foto']); ?></strong></small>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Kanan -->
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Tanggal Kunjungan</label>
                            <input type="date" name="tgl_kunjungan" class="form-control" required value="<?php echo htmlspecialchars($d['tgl_kunjungan']); ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Jam</label>
                            <input type="time" name="jam" class="form-control" required value="<?php echo htmlspecialchars($d['jam']); ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Jenis Kunjungan</label>
                            <input type="text" name="jenis_kunjungan" class="form-control" placeholder="Konsultasi Pendaftaran" value="<?php echo htmlspecialchars($d['jenis_kunjungan']); ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Hasil Kunjungan</label>
                            <input type="text" name="hasil_kunjungan" class="form-control" placeholder="Catatan Hasil Diskusi" value="<?php echo htmlspecialchars($d['hasil_kunjungan']); ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label d-block">Status</label>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="status" id="stDeal" value="Deal" <?php echo ($d['status'] == 'Deal') ? 'checked' : ''; ?>>
                                <label class="form-check-label" style="font-size: 13px;" for="stDeal">Deal</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="status" id="stFollowUp" value="Follow Up" <?php echo ($d['status'] == 'Follow Up') ? 'checked' : ''; ?>>
                                <label class="form-check-label" style="font-size: 13px;" for="stFollowUp">Follow Up</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="status" id="stPending" value="Pending" <?php echo ($d['status'] == 'Pending') ? 'checked' : ''; ?>>
                                <label class="form-check-label" style="font-size: 13px;" for="stPending">Pending</label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Tgl Follow Up</label>
                            <input type="date" name="tgl_follow_up" class="form-control" value="<?php echo htmlspecialchars($d['tgl_follow_up'] ?? ''); ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Link Instagram</label>
                            <input type="url" name="link_ig" class="form-control" placeholder="https://instagram.com" value="<?php echo htmlspecialchars($d['link_ig'] ?? ''); ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Link Tik Tok</label>
                            <input type="url" name="link_tiktok" class="form-control" placeholder="https://tiktok.com" value="<?php echo htmlspecialchars($d['link_tiktok'] ?? ''); ?>">
                        </div>
                    </div>

                    <!-- Tombol Aksi -->
                    <div class="col-12 text-end mt-4">
                        <a href="index.php" class="btn btn-secondary btn-sm px-4 me-2">Batal</a>
                        <button type="submit" name="simpan" class="btn btn-navy btn-sm px-4">
                            <i class="fa fa-save me-1"></i> <?php echo $is_edit ? 'Update Data' : 'Simpan Data'; ?>
                        </button>
                    </div>

                </div>
            </form>

        </div>
    </div>

</body>
</html>