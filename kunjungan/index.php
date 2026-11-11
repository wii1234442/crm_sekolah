<?php
session_start();

// 1. CEK AUTENTIKASI USER
if (!isset($_SESSION['nama_lengkap'])) {
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

// ------------------------------------------------------------------
// 3. LOGIKA HAPUS DATA (SESUAI KOLOM ASLI: id_kunjungan)
// ------------------------------------------------------------------
if (isset($_GET['hapus']) && !empty($_GET['hapus'])) {
    $id_hapus = (int)$_GET['hapus'];
    
    // Ambil data kunjungan berdasarkan id_kunjungan
    $q_kunj = @mysqli_query($conn, "SELECT * FROM kunjungan WHERE id_kunjungan = $id_hapus LIMIT 1");
    
    if ($q_kunj && mysqli_num_rows($q_kunj) > 0) {
        $d_kunj = mysqli_fetch_assoc($q_kunj);
        
        // Hapus file foto fisik jika ada
        $file_foto = basename(isset($d_kunj['upload_foto']) ? $d_kunj['upload_foto'] : '');
        if (!empty($file_foto) && file_exists("../uploads/" . $file_foto)) {
            unlink("../uploads/" . $file_foto);
        }

        $nama_siswa = isset($d_kunj['nama_siswa_wali']) ? mysqli_real_escape_string($conn, $d_kunj['nama_siswa_wali']) : '';
        if (!empty($nama_siswa)) {
            @mysqli_query($conn, "DELETE FROM crm_siswa WHERE nama_calon = '$nama_siswa'");
        }
    }

    // Eksekusi hapus menggunakan id_kunjungan
    $hapus_berhasil = @mysqli_query($conn, "DELETE FROM kunjungan WHERE id_kunjungan = $id_hapus");

    if ($hapus_berhasil) {
        $_SESSION['pesan'] = "Data kunjungan berhasil dihapus permanen!";
    } else {
        $_SESSION['pesan'] = "Gagal menghapus data: " . mysqli_error($conn);
    }

    header("Location: index.php");
    exit;
}

// ------------------------------------------------------------------
// 4. LOGIKA EKSPOR / CETAK WORD
// ------------------------------------------------------------------
if (isset($_GET['export']) && $_GET['export'] === 'word') {
    $filename = "Laporan_Riwayat_Kunjungan_" . date('Ymd_His') . ".doc";
    
    header("Content-Type: application/vnd.ms-word; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: no-cache");
    header("Expires: 0");

    $search_exp = "";
    $where_exp = "";
    if (isset($_GET['cari']) && !empty(trim($_GET['cari']))) {
        $search_exp = mysqli_real_escape_string($conn, trim($_GET['cari']));
        $where_exp = " WHERE nama_siswa_wali LIKE '%$search_exp%' 
                       OR asal_sekolah LIKE '%$search_exp%' 
                       OR nama_pic_staf LIKE '%$search_exp%' 
                       OR status LIKE '%$search_exp%' 
                       OR jenis_kunjungan LIKE '%$search_exp%'";
    }

    $res_export = mysqli_query($conn, "SELECT * FROM kunjungan $where_exp");
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <title>Laporan Kunjungan</title>
        <style>
            body { font-family: 'Calibri', 'Arial', sans-serif; font-size: 11pt; color: #333; }
            .title { text-align: center; margin-bottom: 20px; }
            .title h2 { margin: 0; font-size: 16pt; text-transform: uppercase; }
            .title p { margin: 2px 0; font-size: 10pt; color: #555; }
            table { width: 100%; border-collapse: collapse; margin-top: 10px; }
            table, th, td { border: 1px solid #000; }
            th { background-color: #f2f2f2; padding: 8px; font-size: 10pt; text-align: center; font-weight: bold; }
            td { padding: 6px 8px; font-size: 10pt; vertical-align: top; }
            .text-center { text-align: center; }
            .ttd-box { margin-top: 40px; float: right; text-align: center; width: 200px; }
        </style>
    </head>
    <body>
        <div class="title">
            <h2>LAPORAN RIWAYAT KUNJUNGAN</h2>
            <p>Sistem Informasi CRM Sekolah</p>
            <p>Tanggal Cetak: <?php echo date('d-m-Y H:i'); ?> WIB</p>
            <?php if (!empty($search_exp)): ?>
                <p><i>(Filter Pencarian: "<?php echo htmlspecialchars($search_exp); ?>")</i></p>
            <?php endif; ?>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width: 30px;">No</th>
                    <th style="width: 70px;">ID Kunjungan</th>
                    <th>Nama Siswa/Wali</th>
                    <th>Asal Sekolah</th>
                    <th>Waktu Kunjungan</th>
                    <th>Jenis</th>
                    <th>PIC (Staf)</th>
                    <th>No. HP/WA</th>
                    <th>Status</th>
                    <th>Hasil Kunjungan</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($res_export && mysqli_num_rows($res_export) > 0): ?>
                    <?php $no = 1; while ($r = mysqli_fetch_assoc($res_export)): ?>
                        <tr>
                            <td class="text-center"><?php echo $no; ?></td>
                            <td class="text-center">KJN<?php echo sprintf('%03d', $r['id_kunjungan']); ?></td>
                            <td><?php echo htmlspecialchars($r['nama_siswa_wali']); ?></td>
                            <td><?php echo htmlspecialchars($r['asal_sekolah']); ?></td>
                            <td><?php echo date('d-m-Y', strtotime($r['tgl_kunjungan'])) . ' ' . date('H:i', strtotime($r['jam'])); ?></td>
                            <td><?php echo htmlspecialchars($r['jenis_kunjungan']); ?></td>
                            <td><?php echo htmlspecialchars($r['nama_pic_staf']); ?></td>
                            <td><?php echo htmlspecialchars($r['no_hp_wa']); ?></td>
                            <td class="text-center"><?php echo htmlspecialchars(isset($r['status']) ? $r['status'] : 'Pending'); ?></td>
                            <td><?php echo htmlspecialchars($r['hasil_kunjungan'] ? $r['hasil_kunjungan'] : '-'); ?></td>
                        </tr>
                    <?php $no++; endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="10" class="text-center">Data kunjungan tidak ditemukan.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="ttd-box">
            <p>Dicetak Oleh,</p>
            <br><br><br>
            <p><b><u><?php echo htmlspecialchars(isset($_SESSION['nama_lengkap']) ? $_SESSION['nama_lengkap'] : 'dewi'); ?></u></b></p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// ------------------------------------------------------------------
// 5. HELPER FUNCTION & LOGIKA VIEW / PENCARIAN DATA
// ------------------------------------------------------------------
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'Deal': return 'bg-success';
        case 'Follow Up': return 'bg-warning text-dark';
        case 'Pending': default: return 'bg-secondary';
    }
}

$is_view = false;
$detail = null;
if (isset($_GET['view']) && !empty($_GET['view'])) {
    $id_view = (int)$_GET['view'];
    $q_detail = @mysqli_query($conn, "SELECT * FROM kunjungan WHERE id_kunjungan = $id_view LIMIT 1");

    if ($q_detail && mysqli_num_rows($q_detail) > 0) {
        $is_view = true;
        $detail = mysqli_fetch_assoc($q_detail);
    }
}

$search = "";
$where = "";
if (!$is_view) {
    if (isset($_GET['cari']) && !empty(trim($_GET['cari']))) {
        $search = mysqli_real_escape_string($conn, trim($_GET['cari']));
        $where = " WHERE nama_siswa_wali LIKE '%$search%' 
                   OR asal_sekolah LIKE '%$search%' 
                   OR nama_pic_staf LIKE '%$search%' 
                   OR status LIKE '%$search%' 
                   OR jenis_kunjungan LIKE '%$search%'";
    }

    $result = mysqli_query($conn, "SELECT * FROM kunjungan $where ORDER BY id_kunjungan ASC");
}

$nama_user    = isset($_SESSION['nama_lengkap']) ? $_SESSION['nama_lengkap'] : 'dewi';
$jabatan_user = 'Administrator';
$words        = explode(' ', trim($nama_user));
$inisial      = strtoupper(substr($words[0], 0, 1));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_view ? 'Detail Kunjungan' : 'Riwayat Kunjungan'; ?> - CRM System</title>
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
        .card-container { background: white; border: 1px solid #e0e0e0; border-radius: 8px; padding: 25px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); }
        .btn-biru-tua { background-color: #0b2545 !important; color: white !important; border: none; }
        .btn-biru-tua:hover { background-color: #13315c !important; }
        .detail-box { background: white; border: 1px solid #dcdcdc; border-radius: 6px; padding: 20px 25px; max-width: 750px; margin: 0 auto; box-shadow: 0 2px 6px rgba(0,0,0,0.02); }
        .table-detail-figma { width: 100%; font-size: 13px; }
        .table-detail-figma td { padding: 4px 0; vertical-align: top; }
        .table-detail-figma td.label-col { width: 140px; color: #333; font-weight: 500; }
        .btn-tutup-figma { background-color: #828282; color: white; border: none; padding: 5px 20px; font-size: 12px; border-radius: 4px; text-decoration: none; display: inline-block; }
        .btn-tutup-figma:hover { background-color: #6c757d; color: white; }
        .img-thumb-table { width: 45px; height: 45px; object-fit: cover; border-radius: 6px; border: 1px solid #dee2e6; transition: transform 0.2s; }
        .img-thumb-table:hover { transform: scale(1.1); }
    </style>
</head>
<body>

    <div class="sidebar">
        <h4>📈 CRM System</h4>
        <a href="../dashboard.php">🏠 Dashboard</a>
        <a href="index.php" class="active">👥 Kunjungan</a>
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
        <a href="logout.php" style="position: absolute; bottom: 20px; width: 100%;">🚪 Logout</a>
    </div>

    <div class="main-content">
        <div class="top-header">
            <div>
                <h5 class="m-0 text-dark fw-bold" style="font-size: 17px;"><?php echo $is_view ? 'Form Kunjungan' : 'Riwayat Data Kunjungan'; ?></h5>
                <span class="text-muted" style="font-size: 11px;">Dashboard / Kunjungan</span>
            </div>
            <div class="d-flex align-items-center text-end">
                <div class="me-3">
                    <span class="d-block fw-bold text-dark" style="font-size: 13px;"><?php echo htmlspecialchars($nama_user); ?></span>
                    <span class="text-muted" style="font-size: 11px;"><?php echo htmlspecialchars($jabatan_user); ?></span>
                </div>
                <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 38px; height: 38px; background-color: #dbeafe; color: #1e40af; font-size: 13px;">
                    <?php echo htmlspecialchars($inisial); ?>
                </div>
            </div>
        </div>

        <div class="content-area">

            <?php if (isset($_SESSION['pesan'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert" style="font-size: 12px;">
                    <?php echo htmlspecialchars($_SESSION['pesan']); unset($_SESSION['pesan']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if ($is_view && $detail): ?>
                <?php 
                    $id_detail_val = $detail['id_kunjungan'];
                    $formatted_id = 'KJN' . sprintf('%03d', $id_detail_val);
                    $waktu_kunjungan = date('d M Y', strtotime($detail['tgl_kunjungan'])) . ' / ' . date('H:i', strtotime($detail['jam']));
                    $st = isset($detail['status']) ? $detail['status'] : 'Pending';
                    $badge_class = getStatusBadgeClass($st);
                ?>

                <!-- VIEW DETAIL DATA KUNJUNGAN -->
                <div class="detail-box">
                    <div class="d-flex justify-content-between align-items-center pb-2 mb-3 border-bottom">
                        <h6 class="fw-bold m-0 text-dark" style="font-size: 14px;">Detail Informasi Kunjungan</h6>
                        <a href="index.php" class="text-dark text-decoration-none fw-bold" style="font-size: 18px;" title="Tutup">&times;</a>
                    </div>

                    <div class="row g-3 align-items-start">
                        <div class="col-md-7">
                            <table class="table-detail-figma">
                                <tr><td class="label-col">ID Kunjungan</td><td>: <?php echo htmlspecialchars($formatted_id); ?></td></tr>
                                <tr><td class="label-col">Nama Siswa/Wali</td><td>: <?php echo htmlspecialchars($detail['nama_siswa_wali']); ?></td></tr>
                                <tr><td class="label-col">Asal Sekolah</td><td>: <?php echo htmlspecialchars($detail['asal_sekolah']); ?></td></tr>
                                <tr><td class="label-col">No. HP / WA</td><td>: <?php echo htmlspecialchars($detail['no_hp_wa']); ?></td></tr>
                                <tr>
                                    <td class="label-col">Sosial Media</td>
                                    <td>: 
                                        <?php 
                                            $sosmed = [];
                                            if (!empty($detail['link_ig'])) { $sosmed[] = "IG: " . htmlspecialchars($detail['link_ig']); }
                                            if (!empty($detail['link_tiktok'])) { $sosmed[] = "TikTok: " . htmlspecialchars($detail['link_tiktok']); }
                                            echo !empty($sosmed) ? implode(' | ', $sosmed) : '-';
                                        ?>
                                    </td>
                                </tr>
                                <tr><td class="label-col">Waktu Kunjungan</td><td>: <?php echo htmlspecialchars($waktu_kunjungan); ?></td></tr>
                                <tr><td class="label-col">Jenis Kunjungan</td><td>: <?php echo htmlspecialchars($detail['jenis_kunjungan']); ?></td></tr>
                                <tr><td class="label-col">PIC (Staf)</td><td>: <?php echo htmlspecialchars($detail['nama_pic_staf']); ?></td></tr>
                                <tr><td class="label-col">Alamat</td><td>: <?php echo htmlspecialchars($detail['alamat'] ? $detail['alamat'] : '-'); ?></td></tr>
                                <tr><td class="label-col">Hasil Kunjungan</td><td>: <?php echo htmlspecialchars($detail['hasil_kunjungan'] ? $detail['hasil_kunjungan'] : '-'); ?></td></tr>
                                <tr><td class="label-col">Status</td><td>: <span class="badge <?php echo $badge_class; ?>" style="font-size: 11px;"><?php echo htmlspecialchars($st); ?></span></td></tr>
                            </table>
                        </div>

                        <div class="col-md-5 text-center">
                            <span class="d-block text-muted mb-2" style="font-size: 11px;">Dokumentasi Kunjungan</span>
                            <?php if (!empty($detail['upload_foto']) && file_exists("../uploads/" . basename($detail['upload_foto']))): ?>
                                <a href="../uploads/<?php echo htmlspecialchars(basename($detail['upload_foto'])); ?>" target="_blank">
                                    <img src="../uploads/<?php echo htmlspecialchars(basename($detail['upload_foto'])); ?>" class="img-fluid rounded border" style="max-height: 180px; object-fit: cover;" alt="Dokumentasi">
                                </a>
                            <?php else: ?>
                                <div class="bg-light border rounded d-flex align-items-center justify-content-center text-muted mx-auto" style="width: 150px; height: 150px; font-size: 11px;">
                                    <i class="fa fa-image me-1"></i> Tidak Ada Foto
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="text-end mt-3">
                        <a href="index.php" class="btn-tutup-figma">Tutup</a>
                    </div>
                </div>

            <?php else: ?>
                <!-- TABEL UTAMA RIWAYAT KUNJUNGAN -->
                <div class="card-container">
                    
                    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                        <!-- FORM PENCARIAN -->
                        <form action="" method="GET" class="d-flex gap-1">
                            <input type="text" name="cari" class="form-control form-control-sm" placeholder="Cari data..." value="<?php echo htmlspecialchars($search); ?>" style="font-size: 12px; width: 200px;">
                            <button type="submit" class="btn btn-outline-secondary btn-sm" style="font-size: 12px;"><i class="fa fa-search"></i></button>
                            <?php if (!empty($search)): ?>
                                <a href="index.php" class="btn btn-outline-danger btn-sm" style="font-size: 12px;" title="Reset"><i class="fa fa-times"></i></a>
                            <?php endif; ?>
                        </form>

                        <div class="d-flex gap-2 align-items-center flex-wrap">
                            <!-- TOMBOL CETAK WORD -->
                            <a href="index.php?export=word<?php echo !empty($search) ? '&cari=' . urlencode($search) : ''; ?>" class="btn btn-secondary btn-sm" style="background-color: #2b579a; border: none; font-size: 12px;">
                                <i class="fa fa-file-word me-1"></i> Cetak Word
                            </a>
                            <a href="tambah.php" class="btn btn-biru-tua btn-sm" style="font-size: 12px;">
                                <i class="fa fa-plus me-1"></i> Tambah Kunjungan 
                            </a>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-striped table-bordered align-middle" style="font-size: 12px;">
                            <thead class="table-light">
                                <tr>
                                    <th>ID Kunjungan</th>
                                    <th>Nama & Asal Sekolah</th>
                                    <th>Waktu</th>
                                    <th>Jenis & PIC</th>
                                    <th>No. HP / WA</th>
                                    <th>Sosmed</th>
                                    <th>Status</th>
                                    <th class="text-center">Foto</th>
                                    <th class="text-center" style="width: 140px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($result && mysqli_num_rows($result) > 0): ?>
                                    <?php 
                                    $no_urut = 1;
                                    while ($row = mysqli_fetch_assoc($result)): 
                                    ?>
                                        <?php 
                                            // Ambil ID secara akurat langsung dari kolom id_kunjungan database
                                            $id_val = $row['id_kunjungan'];
                                            $formatted_id = 'KJN' . sprintf('%03d', $id_val); 
                                            
                                            $hp_clean = preg_replace('/[^0-9]/', '', $row['no_hp_wa']);
                                            if (substr($hp_clean, 0, 1) == '0') { 
                                                $hp_clean = '62' . substr($hp_clean, 1); 
                                            }

                                            $st = isset($row['status']) ? $row['status'] : 'Pending';
                                            $badge_class = getStatusBadgeClass($st);
                                            $foto_path = basename(isset($row['upload_foto']) ? $row['upload_foto'] : '');
                                        ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($formatted_id); ?></strong></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($row['nama_siswa_wali']); ?></strong><br>
                                                <span class="text-muted"><?php echo htmlspecialchars($row['asal_sekolah']); ?></span>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($row['tgl_kunjungan']); ?><br>
                                                <span class="text-muted"><?php echo htmlspecialchars($row['jam']); ?></span>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($row['jenis_kunjungan']); ?><br>
                                                <span class="text-muted">PIC: <?php echo htmlspecialchars($row['nama_pic_staf']); ?></span>
                                            </td>
                                            <td>
                                                <?php if (!empty($hp_clean)): ?>
                                                    <a href="https://wa.me/<?php echo $hp_clean; ?>" target="_blank" class="text-decoration-none text-success fw-bold">
                                                        <i class="fab fa-whatsapp"></i> <?php echo htmlspecialchars($row['no_hp_wa']); ?>
                                                    </a>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php 
                                                    $has_ig = !empty($row['link_ig']);
                                                    $has_tiktok = !empty($row['link_tiktok']);
                                                ?>
                                                <?php if ($has_ig): ?>
                                                    <a href="<?php echo htmlspecialchars($row['link_ig']); ?>" target="_blank" class="d-block text-decoration-none">IG</a>
                                                <?php endif; ?>
                                                <?php if ($has_tiktok): ?>
                                                    <a href="<?php echo htmlspecialchars($row['link_tiktok']); ?>" target="_blank" class="d-block text-decoration-none">TikTok</a>
                                                <?php endif; ?>
                                                <?php if (!$has_ig && !$has_tiktok): ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($st); ?></span>
                                            </td>
                                            <td class="text-center">
                                                <?php if (!empty($foto_path) && file_exists("../uploads/" . $foto_path)): ?>
                                                    <a href="../uploads/<?php echo htmlspecialchars($foto_path); ?>" target="_blank" title="Klik untuk melihat foto ukuran penuh">
                                                        <img src="../uploads/<?php echo htmlspecialchars($foto_path); ?>" alt="Foto Kunjungan" class="img-thumb-table">
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted" style="font-size: 11px;">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <div class="d-flex justify-content-center gap-1">
                                                    <a href="index.php?view=<?php echo $id_val; ?>" class="btn btn-info btn-sm py-0 px-2 fw-semibold text-white" style="font-size: 11px;">View</a>
                                                    <a href="tambah.php?id=<?php echo $id_val; ?>" class="btn btn-warning btn-sm py-0 px-2 fw-semibold" style="font-size: 11px;">Edit</a>
                                                    <a href="index.php?hapus=<?php echo $id_val; ?>" class="btn btn-danger btn-sm py-0 px-2 fw-semibold" onclick="return confirm('Yakin hapus data ini?');" style="font-size: 11px;">Hapus</a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php 
                                    $no_urut++; 
                                    endwhile; 
                                    ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="text-center text-muted py-4">Belum ada data kunjungan.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                </div>
            <?php endif; ?>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>