<?php
session_start();

// 1. CEK AUTENTIKASI USER
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

// 3. FITUR CETAK WORD (.DOC)
if (isset($_GET['export']) && $_GET['export'] === 'word') {
    $filename = "Data_Interaksi_CRM_" . date('Ymd_His') . ".doc";
    header("Content-Type: application/vnd.ms-word");
    header("Expires: 0");
    header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
    header("Content-Disposition: attachment; filename=\"$filename\"");

    $search = $_GET['search'] ?? '';
    $where_word = "";
    if (!empty($search)) {
        $search_clean = mysqli_real_escape_string($conn, $search);
        $where_word = "WHERE s.nama_calon LIKE '%$search_clean%' 
                         OR s.nama_ortu LIKE '%$search_clean%' 
                         OR s.asal_sekolah LIKE '%$search_clean%' 
                         OR s.status LIKE '%$search_clean%'";
    }

    $q_word = "SELECT s.*, COALESCE(mt.nama_tahap, s.status, 'Follow Up') AS status_tahap
               FROM crm_siswa s
               LEFT JOIN (
                   SELECT id_siswa, MAX(id_tahap) AS id_tahap FROM siswa_tahap GROUP BY id_siswa
               ) st ON s.id_calonsiswa = st.id_siswa
               LEFT JOIN master_tahap mt ON st.id_tahap = mt.id_tahap
               $where_word ORDER BY s.id_calonsiswa ASC";
    
    $result_word = @mysqli_query($conn, $q_word);
    if (!$result_word) {
        $result_word = mysqli_query($conn, "SELECT *, status AS status_tahap FROM crm_siswa $where_word ORDER BY id_calonsiswa ASC");
    }
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <title>Laporan Data Interaksi CRM</title>
        <style>
            body { font-family: 'Calibri', 'Segoe UI', Arial, sans-serif; font-size: 11pt; color: #333; }
            .header-title { text-align: center; font-size: 16pt; font-weight: bold; text-transform: uppercase; margin-bottom: 5px; }
            .header-subtitle { text-align: center; font-size: 11pt; margin-bottom: 20px; color: #555; }
            table { width: 100%; border-collapse: collapse; margin-top: 10px; }
            table, th, td { border: 1px solid #000000; }
            th { background-color: #0f2b5c; color: #ffffff; padding: 8px 5px; font-size: 10pt; text-align: center; }
            td { padding: 6px 5px; font-size: 9.5pt; vertical-align: top; }
            .text-center { text-align: center; }
            .text-right { text-align: right; }
        </style>
    </head>
    <body>
        <div class="header-title">LAPORAN DATA INTERAKSI CRM</div>
        <div class="header-subtitle">CRM Sekolah System - Tanggal Cetak: <?php echo date('d/m/Y H:i'); ?></div>

        <table>
            <thead>
                <tr>
                    <th width="4%">No</th>
                    <th width="10%">ID Siswa</th>
                    <th width="18%">Nama & Asal Sekolah</th>
                    <th width="15%">Orang Tua / Kota</th>
                    <th width="13%">No. HP / WA</th>
                    <th width="11%">Jalur & Sumber</th>
                    <th width="11%">Status Tahap</th>
                    <th width="11%">Tgl Follow Up</th>
                    <th width="7%">Biaya SPP</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $no = 1;
                if ($result_word && mysqli_num_rows($result_word) > 0) {
                    while ($row = mysqli_fetch_assoc($result_word)) {
                        $formatted_id = 'CRM' . sprintf('%03d', $row['id_calonsiswa']);
                        $biaya = !empty($row['biaya_spp']) ? 'Rp ' . number_format($row['biaya_spp'], 0, ',', '.') : '-';
                        $tgl_fu = !empty($row['tgl_followup']) ? date('d/m/Y', strtotime($row['tgl_followup'])) : '-';
                        ?>
                        <tr>
                            <td class="text-center"><?php echo $no++; ?></td>
                            <td class="text-center"><b><?php echo $formatted_id; ?></b></td>
                            <td>
                                <b><?php echo htmlspecialchars($row['nama_calon']); ?></b><br>
                                <small><?php echo htmlspecialchars($row['asal_sekolah'] ?? '-'); ?></small>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($row['nama_ortu'] ?? '-'); ?><br>
                                <small><?php echo htmlspecialchars($row['kota'] ?? ''); ?></small>
                            </td>
                            <td class="text-center"><?php echo htmlspecialchars($row['no_hp'] ?? '-'); ?></td>
                            <td>
                                <?php echo htmlspecialchars($row['jalur_daftar'] ?? '-'); ?><br>
                                <small><?php echo htmlspecialchars($row['sumber_info'] ?? ''); ?></small>
                            </td>
                            <td class="text-center"><?php echo htmlspecialchars($row['status_tahap']); ?></td>
                            <td class="text-center"><?php echo $tgl_fu; ?></td>
                            <td class="text-right"><?php echo $biaya; ?></td>
                        </tr>
                        <?php
                    }
                } else {
                    echo '<tr><td colspan="9" class="text-center">Tidak ada data interaksi.</td></tr>';
                }
                ?>
            </tbody>
        </table>
    </body>
    </html>
    <?php
    exit;
}

// 4. PROSES HAPUS DATA
if (isset($_GET['hapus']) && !empty($_GET['hapus'])) {
    $id_hapus = (int)$_GET['hapus'];
    @mysqli_query($conn, "DELETE FROM siswa_tahap WHERE id_siswa = $id_hapus");
    @mysqli_query($conn, "DELETE FROM kunjungan WHERE id_siswa = $id_hapus OR id_calonsiswa = $id_hapus OR id = $id_hapus");
    
    $stmt_del = mysqli_prepare($conn, "DELETE FROM crm_siswa WHERE id_calonsiswa = ?");
    mysqli_stmt_bind_param($stmt_del, "i", $id_hapus);
    mysqli_stmt_execute($stmt_del);
    mysqli_stmt_close($stmt_del);

    header("Location: index.php");
    exit;
}

// 5. QUERY UTAMA DENGAN RELASI TAHAP (DIURUTKAN ASC AGAR CRM001 DI ATAS)
$search = $_GET['search'] ?? '';
$where_clause = "";
if (!empty($search)) {
    $search_clean = mysqli_real_escape_string($conn, $search);
    $where_clause = "WHERE s.nama_calon LIKE '%$search_clean%' 
                        OR s.nama_ortu LIKE '%$search_clean%' 
                        OR s.asal_sekolah LIKE '%$search_clean%' 
                        OR s.status LIKE '%$search_clean%'";
}

$query_join = "SELECT s.*, 
                      COALESCE(mt.nama_tahap, s.status, 'Follow Up') AS status_tahap
               FROM crm_siswa s
               LEFT JOIN (
                   SELECT id_siswa, MAX(id_tahap) AS id_tahap 
                   FROM siswa_tahap 
                   GROUP BY id_siswa
               ) st ON s.id_calonsiswa = st.id_siswa
               LEFT JOIN master_tahap mt ON st.id_tahap = mt.id_tahap
               $where_clause 
               ORDER BY s.id_calonsiswa ASC";

$result = @mysqli_query($conn, $query_join);

if (!$result) {
    $query_fallback = "SELECT s.*, s.status AS status_tahap FROM crm_siswa s $where_clause ORDER BY s.id_calonsiswa ASC";
    $result = mysqli_query($conn, $query_fallback);
}

// Variabel Profil Dinamis
$nama_user    = $_SESSION['nama_lengkap'] ?? $_SESSION['username'] ?? 'dewi';
$jabatan_user = $_SESSION['role'] ?? 'Administrator';
if (strtolower($jabatan_user) === 'admin') {
    $jabatan_user = 'Administrator';
}

$words        = explode(' ', trim($nama_user));
$inisial      = strtoupper(substr($words[0], 0, 1));
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Data Interaksi CRM - CRM System</title>
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
        .card-container { background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 25px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); }
        .btn-navy { background-color: #0f2b5c; color: white; font-size: 12px; font-weight: 500; border-radius: 4px; padding: 6px 14px; border: none; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
        .btn-navy:hover { background-color: #1a3a75; color: white; }
        .search-box { position: relative; max-width: 260px; }
        .search-box input { padding-right: 35px; font-size: 12px; border-radius: 4px; border: 1px solid #cbd5e1; }
        .search-box button { position: absolute; right: 2px; top: 2px; bottom: 2px; border: none; background: transparent; color: #64748b; padding: 0 10px; }
        .table { font-size: 12px; margin-bottom: 0; }
        .table th { background-color: #f8fafc; color: #1e293b; font-weight: 700; border-bottom: 1px solid #e2e8f0; padding: 12px 10px; }
        .table td { vertical-align: middle; padding: 12px 10px; color: #334155; }
        .badge-status { font-size: 10px; font-weight: 600; padding: 4px 10px; border-radius: 4px; display: inline-block; }
        .badge-followup { background-color: #fef08a; color: #854d0e; }
        .badge-deal { background-color: #bbf7d0; color: #166534; }
        .badge-default { background-color: #e2e8f0; color: #334155; }
        .btn-action-edit { background-color: #eab308; color: white; font-size: 11px; padding: 3px 10px; border-radius: 4px; text-decoration: none; font-weight: 500; display: inline-block; }
        .btn-action-edit:hover { background-color: #ca8a04; color: white; }
        .btn-action-hapus { background-color: #ef4444; color: white; font-size: 11px; padding: 3px 10px; border-radius: 4px; text-decoration: none; font-weight: 500; display: inline-block; }
        .btn-action-hapus:hover { background-color: #dc2626; color: white; }
        .wa-link { color: #16a34a; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; }
        .wa-link:hover { color: #15803d; text-decoration: underline; }
    </style>
</head>
<body>

    <!-- SIDEBAR -->
    <div class="sidebar">
        <h4>📈 CRM System</h4>
        <a href="../dashboard.php">🏠 Dashboard</a>
        <a href="../kunjungan/index.php">👥 Kunjungan</a>
        <a href="javascript:void(0);" data-bs-toggle="collapse" data-bs-target="#menuCrm" style="justify-content: space-between; align-items: center;" class="active">
            <div style="display: flex; gap: 10px; align-items: center;"><span>👤</span><span>CRM</span></div>
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
                <h5 class="m-0 text-dark fw-bold" style="font-size: 16px;">Riwayat Data Interaksi CRM</h5>
                <span class="text-muted" style="font-size: 11px;">Dashboard / CRM</span>
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
            <?php if (isset($_SESSION['pesan'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert" style="font-size: 12px;">
                    <?php echo htmlspecialchars($_SESSION['pesan']); unset($_SESSION['pesan']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="card-container">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <form action="" method="GET" class="search-box">
                        <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari data..." value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit"><i class="fa fa-search"></i></button>
                    </form>

                    <div class="d-flex gap-2">
                        <a href="index.php?export=word<?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>" class="btn-navy" target="_blank">
                            <i class="fa-regular fa-file-word"></i> Cetak Word
                        </a>
                        <a href="tambah_interaksi.php" class="btn-navy">
                            <i class="fa fa-plus"></i> Tambah Interaksi Baru
                        </a>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table border">
                        <thead>
                            <tr>
                                <th>ID Siswa</th>
                                <th>Nama & Asal Sekolah</th>
                                <th>Jalur & Sumber</th>
                                <th>No. HP / WA</th>
                                <th>Biaya SPP</th>
                                <th>Status Tahap</th>
                                <th>Tgl Follow Up</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result && mysqli_num_rows($result) > 0): ?>
                                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                    <?php 
                                        $id_siswa = 'CRM' . sprintf('%03d', $row['id_calonsiswa']); 
                                        $spp_formatted = !empty($row['biaya_spp']) ? 'Rp ' . number_format($row['biaya_spp'], 0, ',', '.') : '-';
                                        $status_text  = $row['status_tahap'] ?? 'Follow Up';
                                        $status_clean = strtolower($status_text);
                                        $tgl_fu = !empty($row['tgl_followup']) ? date('Y-m-d', strtotime($row['tgl_followup'])) : '-';
                                        
                                        $badge_class = 'badge-default';
                                        if (strpos($status_clean, 'follow') !== false || strpos($status_clean, 'konsultasi') !== false) {
                                            $badge_class = 'badge-followup';
                                        } else if (strpos($status_clean, 'deal') !== false || strpos($status_clean, 'pendaftaran') !== false || strpos($status_clean, 'diterima') !== false) {
                                            $badge_class = 'badge-deal';
                                        }
                                    ?>
                                    <tr>
                                        <td><b><?php echo $id_siswa; ?></b></td>
                                        <td>
                                            <div class="fw-bold"><?php echo htmlspecialchars($row['nama_calon']); ?></div>
                                            <div class="text-muted" style="font-size: 11px;"><?php echo htmlspecialchars($row['asal_sekolah'] ?? '-'); ?></div>
                                        </td>
                                        <td>
                                            <div><?php echo htmlspecialchars($row['jalur_daftar'] ?? 'Reguler'); ?></div>
                                            <div class="text-muted" style="font-size: 11px;"><?php echo htmlspecialchars($row['sumber_info'] ?? '-'); ?></div>
                                        </td>
                                        <td>
                                            <?php if (!empty($row['no_hp'])): ?>
                                                <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $row['no_hp']); ?>" target="_blank" class="wa-link">
                                                    <i class="fa-brands fa-whatsapp"></i> <?php echo htmlspecialchars($row['no_hp']); ?>
                                                </a>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $spp_formatted; ?></td>
                                        <td>
                                            <span class="badge-status <?php echo $badge_class; ?>">
                                                <?php echo htmlspecialchars($status_text); ?>
                                            </span>
                                        </td>
                                        <td><div class="fw-semibold"><?php echo $tgl_fu; ?></div></td>
                                        <td class="text-center">
                                            <!-- Tombol Edit langsung mengarahkan ke halaman form tambah_interaksi.php?id=... -->
                                            <a href="tambah_interaksi.php?id=<?php echo $row['id_calonsiswa']; ?>" class="btn-action-edit">Edit</a>
                                            <a href="index.php?hapus=<?php echo $row['id_calonsiswa']; ?>" class="btn-action-hapus" onclick="return confirm('Apakah Anda yakin ingin menghapus data ini?');" title="Hapus">Hapus</a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-muted">Belum ada data interaksi CRM.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>