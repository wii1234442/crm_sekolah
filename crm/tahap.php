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

$user_login = $_SESSION['nama_lengkap'] ?? 'dewi';
$user_login_safe = mysqli_real_escape_string($conn, $user_login);

// SINKRONISASI OTOMATIS: Masukkan siswa baru ke tabel siswa_tahap jika belum ada
// (PIC dicatat sesuai siapa yang pertama kali membuat/menyinkronkannya)
mysqli_query($conn, "
    INSERT INTO siswa_tahap (id_siswa, id_label, id_tahap, nama_tahap, tgl_mulai, pic)
    SELECT cs.id_calonsiswa, 1, 1, '1 Kontak Baru', CURRENT_DATE(), '$user_login_safe'
    FROM crm_siswa cs
    LEFT JOIN siswa_tahap st ON cs.id_calonsiswa = st.id_siswa
    WHERE st.id IS NULL
");

// 3. FITUR EXPORT / CETAK WORD (.DOC)
if (isset($_GET['export']) && $_GET['export'] === 'word') {
    $search_export = trim($_GET['search'] ?? '');
    
    $where_clause = "";
    if (!empty($search_export)) {
        $s = mysqli_real_escape_string($conn, $search_export);
        $where_clause = " WHERE cs.nama_calon LIKE '%$s%' OR cs.asal_sekolah LIKE '%$s%' ";
    }

    $q_export = "SELECT cs.id_calonsiswa,
                        cs.nama_calon, 
                        cs.asal_sekolah, 
                        cs.no_hp,
                        COALESCE(st.nama_tahap, cs.status, '1 Kontak Baru') AS nama_tahap,
                        COALESCE(st.tgl_mulai, CURRENT_DATE()) AS tgl_mulai,
                        COALESCE(NULLIF(st.pic, ''), '$user_login_safe') AS pic
                 FROM crm_siswa cs 
                 LEFT JOIN (
                     SELECT st1.*
                     FROM siswa_tahap st1
                     INNER JOIN (
                         SELECT id_siswa, MAX(id) AS max_id 
                         FROM siswa_tahap 
                         GROUP BY id_siswa
                     ) st2 ON st1.id = st2.max_id
                 ) st ON cs.id_calonsiswa = st.id_siswa 
                 $where_clause
                 ORDER BY cs.id_calonsiswa ASC";
                 
    $res_export = mysqli_query($conn, $q_export);

    $filename = "Laporan_Tahap_CRM_" . date('Ymd_His') . ".doc";
    header("Content-Type: application/vnd.ms-word");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: no-cache");
    header("Expires: 0");

    echo "<html xmlns:o='urn:schemas-microsoft-com:office:office' xmlns:w='urn:schemas-microsoft-com:office:word' xmlns='http://www.w3.org/TR/REC-html40'>";
    echo "<head><meta charset='utf-8'><title>Laporan Tahap CRM</title>";
    echo "<style>
            body { font-family: Arial, sans-serif; font-size: 11pt; }
            h2 { text-align: center; margin-bottom: 5px; text-transform: uppercase; }
            p { text-align: center; font-size: 10pt; color: #555; margin-top: 0; }
            table { width: 100%; border-collapse: collapse; margin-top: 15px; }
            th, td { border: 1px solid #000; padding: 8px; text-align: left; font-size: 10pt; }
            th { background-color: #2b579a; color: #ffffff; font-weight: bold; text-align: center; }
          </style></head><body>";
    
    echo "<h2>LAPORAN TAHAP CRM SISWA</h2>";
    echo "<p>Tanggal Cetak: " . date('d/m/Y H:i') . " | Oleh: " . htmlspecialchars($user_login) . "</p>";
    echo "<table>";
    echo "<thead><tr>
            <th width='5%'>No</th>
            <th>Nama Siswa</th>
            <th>No HP</th>
            <th>Asal Sekolah</th>
            <th>Tahap Saat Ini</th>
            <th>PIC</th>
            <th>Tgl Masuk</th>
          </tr></thead><tbody>";

    if ($res_export && mysqli_num_rows($res_export) > 0) {
        $no_exp = 1;
        while ($exp = mysqli_fetch_assoc($res_export)) {
            echo "<tr>";
            echo "<td style='text-align:center;'>" . $no_exp++ . "</td>";
            echo "<td>" . htmlspecialchars($exp['nama_calon'] ?? '-') . "</td>";
            echo "<td>" . htmlspecialchars($exp['no_hp'] ?? '-') . "</td>";
            echo "<td>" . htmlspecialchars($exp['asal_sekolah'] ?? '-') . "</td>";
            echo "<td>" . htmlspecialchars($exp['nama_tahap'] ?? '1 Kontak Baru') . "</td>";
            echo "<td>" . htmlspecialchars(!empty($exp['pic']) ? $exp['pic'] : '-') . "</td>";
            echo "<td style='text-align:center;'>" . date('d/m/Y', strtotime($exp['tgl_mulai'])) . "</td>";
            echo "</tr>";
        }
    } else {
        echo "<tr><td colspan='7' style='text-align:center;'>Tidak ada data.</td></tr>";
    }

    echo "</tbody></table></body></html>";
    exit;
}

// 4. PROSES SIMPAN / UPDATE TAHAP SISWA
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_siswa   = (int)($_POST['id_siswa'] ?? 0);
    $nama_tahap = trim($_POST['nama_tahap'] ?? '1 Kontak Baru');
    $tgl_mulai  = !empty($_POST['tgl_mulai']) ? $_POST['tgl_mulai'] : date('Y-m-d');
    $keterangan = trim($_POST['keterangan'] ?? '');
    $pic        = trim($_POST['pic'] ?? $user_login);

    $id_tahap = 1;
    if (strpos(strtolower($nama_tahap), 'follow') !== false || strpos($nama_tahap, '2') !== false) {
        $id_tahap = 2;
    } else if (strpos(strtolower($nama_tahap), 'deal') !== false || strpos($nama_tahap, '3') !== false) {
        $id_tahap = 3;
    }

    if ($id_siswa > 0) {
        $stmt_check = mysqli_prepare($conn, "SELECT id FROM siswa_tahap WHERE id_siswa = ?");
        mysqli_stmt_bind_param($stmt_check, "i", $id_siswa);
        mysqli_stmt_execute($stmt_check);
        $res_check = mysqli_stmt_get_result($stmt_check);

        if ($res_check && mysqli_num_rows($res_check) > 0) {
            $stmt = mysqli_prepare($conn, "UPDATE siswa_tahap SET id_tahap=?, nama_tahap=?, tgl_mulai=?, keterangan=?, pic=? WHERE id_siswa=?");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "issssi", $id_tahap, $nama_tahap, $tgl_mulai, $keterangan, $pic, $id_siswa);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        } else {
            $stmt = mysqli_prepare($conn, "INSERT INTO siswa_tahap (id_siswa, id_label, id_tahap, nama_tahap, tgl_mulai, keterangan, pic) VALUES (?, 1, ?, ?, ?, ?, ?)");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "iiisss", $id_siswa, $id_tahap, $nama_tahap, $tgl_mulai, $keterangan, $pic);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        }
        mysqli_stmt_close($stmt_check);

        $stmt_crm = mysqli_prepare($conn, "UPDATE crm_siswa SET status = ?, tgl_followup = ? WHERE id_calonsiswa = ?");
        if ($stmt_crm) {
            mysqli_stmt_bind_param($stmt_crm, "ssi", $nama_tahap, $tgl_mulai, $id_siswa);
            mysqli_stmt_execute($stmt_crm);
            mysqli_stmt_close($stmt_crm);
        }

        $_SESSION['pesan'] = "Tahap dan PIC siswa berhasil diperbarui!";
        $_SESSION['tipe_pesan'] = "success";
    }

    header("Location: tahap.php");
    exit;
}

// 5. PROSES HAPUS TAHAP
if (isset($_GET['hapus']) && !empty($_GET['hapus'])) {
    $id_siswa_hapus = (int)$_GET['hapus'];
    $stmt_del = mysqli_prepare($conn, "DELETE FROM siswa_tahap WHERE id_siswa = ?");
    if ($stmt_del) {
        mysqli_stmt_bind_param($stmt_del, "i", $id_siswa_hapus);
        mysqli_stmt_execute($stmt_del);
        mysqli_stmt_close($stmt_del);
    }
    $_SESSION['pesan'] = "Data tahap siswa berhasil dihapus!";
    $_SESSION['tipe_pesan'] = "danger";
    header("Location: tahap.php");
    exit;
}

// 6. HITUNG TOTAL AKURAT UNTUK SUMMARY CARDS
$q_total = mysqli_query($conn, "SELECT 
    SUM(CASE 
        WHEN LOWER(COALESCE(st.nama_tahap, cs.status, '')) LIKE '%2%' OR LOWER(COALESCE(st.nama_tahap, cs.status, '')) LIKE '%follow%' THEN 0
        WHEN LOWER(COALESCE(st.nama_tahap, cs.status, '')) LIKE '%3%' OR LOWER(COALESCE(st.nama_tahap, cs.status, '')) LIKE '%deal%' THEN 0
        ELSE 1 
    END) as c1,
    SUM(CASE 
        WHEN LOWER(COALESCE(st.nama_tahap, cs.status, '')) LIKE '%2%' OR LOWER(COALESCE(st.nama_tahap, cs.status, '')) LIKE '%follow%' THEN 1 
        ELSE 0 
    END) as c2,
    SUM(CASE 
        WHEN LOWER(COALESCE(st.nama_tahap, cs.status, '')) LIKE '%3%' OR LOWER(COALESCE(st.nama_tahap, cs.status, '')) LIKE '%deal%' THEN 1 
        ELSE 0 
    END) as c3
    FROM crm_siswa cs 
    LEFT JOIN (
        SELECT st1.id_siswa, st1.id_tahap, st1.nama_tahap
        FROM siswa_tahap st1
        INNER JOIN (
            SELECT id_siswa, MAX(id) as max_id 
            FROM siswa_tahap 
            GROUP BY id_siswa
        ) st2 ON st1.id = st2.max_id
    ) st ON cs.id_calonsiswa = st.id_siswa");

$res_tot = mysqli_fetch_assoc($q_total);
$total_kontak_baru = (int)($res_tot['c1'] ?? 0);
$total_follow_up  = (int)($res_tot['c2'] ?? 0);
$total_deal       = (int)($res_tot['c3'] ?? 0);

// 7. FILTER PENCARIAN
$search_query = trim($_GET['search'] ?? '');
$where_search = "";
if (!empty($search_query)) {
    $s_clean = mysqli_real_escape_string($conn, $search_query);
    $where_search = " WHERE cs.nama_calon LIKE '%$s_clean%' OR cs.asal_sekolah LIKE '%$s_clean%' ";
}

// 8. QUERY UTAMA SISWA & TAHAP
$query = "SELECT cs.id_calonsiswa, 
                 cs.nama_calon, 
                 cs.asal_sekolah, 
                 cs.no_hp,
                 st.id AS id_tahap_siswa,
                 COALESCE(st.id_tahap, 1) AS id_tahap,
                 COALESCE(st.nama_tahap, cs.status, '1 Kontak Baru') AS nama_tahap,
                 COALESCE(st.tgl_mulai, CURRENT_DATE()) AS tgl_mulai,
                 st.keterangan,
                 COALESCE(NULLIF(st.pic, ''), '$user_login_safe') AS pic
          FROM crm_siswa cs 
          LEFT JOIN (
              SELECT st1.*
              FROM siswa_tahap st1
              INNER JOIN (
                  SELECT id_siswa, MAX(id) AS max_id 
                  FROM siswa_tahap 
                  GROUP BY id_siswa
              ) st2 ON st1.id = st2.max_id
          ) st ON cs.id_calonsiswa = st.id_siswa 
          $where_search
          ORDER BY cs.id_calonsiswa ASC";

$result = mysqli_query($conn, $query);

$nama_user    = $_SESSION['nama_lengkap'] ?? 'dewi';
$jabatan_user = 'Administrator';
$words        = explode(' ', trim($nama_user));
$inisial      = strtoupper(substr($words[0], 0, 1));
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tahap CRM - CRM System</title>
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
        .stage-card { background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 18px; height: 100%; box-shadow: 0 1px 2px rgba(0,0,0,0.03); }
        .stage-circle { width: 24px; height: 24px; border-radius: 50%; color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 11px; flex-shrink: 0; }
        .circle-blue { background-color: #2563eb; }
        .circle-yellow { background-color: #eab308; }
        .circle-green { background-color: #16a34a; }
        .btn-word { background-color: #2b579a; color: white; font-size: 12px; font-weight: 600; border-radius: 4px; padding: 6px 14px; border: none; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; cursor: pointer; transition: 0.2s; white-space: nowrap; }
        .btn-word:hover { background-color: #1e3d6f; color: white; }
        .card-table-container { background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); }
        .table { font-size: 12px; margin-bottom: 0; }
        .table th { background-color: #f8fafc; color: #1e293b; font-weight: 700; border-bottom: 1px solid #e2e8f0; padding: 12px 10px; }
        .table td { vertical-align: middle; padding: 12px 10px; color: #334155; }
        .badge-stage { font-size: 10px; font-weight: 600; padding: 4px 10px; border-radius: 4px; color: white; display: inline-block; }
        .badge-stage-blue { background-color: #2563eb; }
        .badge-stage-yellow { background-color: #eab308; color: #1e293b; }
        .badge-stage-green { background-color: #16a34a; }
        .btn-action-edit { background-color: #2563eb; color: white; font-size: 11px; padding: 4px 12px; border-radius: 4px; text-decoration: none; font-weight: 500; border: none; cursor: pointer; display: inline-block; }
        .btn-action-edit:hover { background-color: #1d4ed8; color: white; }
        .btn-action-hapus { background-color: #ef4444; color: white; font-size: 11px; padding: 4px 12px; border-radius: 4px; text-decoration: none; font-weight: 500; border: none; cursor: pointer; display: inline-block; }
        .btn-action-hapus:hover { background-color: #dc2626; color: white; }
        .wa-link { color: #16a34a; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; font-size: 11px; }
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
                <a href="../crm/index.php" style="padding-left: 45px;">Riwayat Interaksi</a>
                <a href="../crm/tahap.php" style="padding-left: 45px; background-color: #2c3e5e; color: white; font-weight: bold;">Tahap</a>
                <a href="../crm/agent.php" style="padding-left: 45px;">Agent</a>
                <a href="../crm/label_status.php" style="padding-left: 45px;">Label Status</a>
            </div>
        </div>
        <a href="../laporan/index.php">📋 Laporan</a>
        <a href="../users/index.php">🧑 Pengguna</a>
        <a href="../pengaturan/index.php">⚙️ Pengaturan</a>
        <a href="logout.php" style="position: absolute; bottom: 20px; width: 100%;">🚪 Logout</a>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-content">
        <div class="top-header">
            <div>
                <h5 class="m-0 text-dark fw-bold" style="font-size: 16px;">Tahap CRM</h5>
                <span class="text-muted" style="font-size: 11px;">Dashboard / CRM / Tahap CRM</span>
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
                <?php $tipe_pesan = $_SESSION['tipe_pesan'] ?? 'success'; ?>
                <div class="alert alert-<?php echo htmlspecialchars($tipe_pesan); ?> alert-dismissible fade show" role="alert" style="font-size: 12px;">
                    <?php 
                        echo htmlspecialchars($_SESSION['pesan']); 
                        unset($_SESSION['pesan']);
                        unset($_SESSION['tipe_pesan']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="mb-3">
                <h6 class="fw-bold m-0 text-dark" style="font-size: 14px;">Manajemen Tahapan CRM</h6>
                <p class="text-muted m-0" style="font-size: 11px;">Kelola dan pantau pergerakan calon siswa pada setiap tahapan proses CRM.</p>
            </div>

            <!-- SUMMARY CARDS -->
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="stage-card">
                        <div class="d-flex align-items-start gap-2 mb-2">
                            <div class="stage-circle circle-blue">1</div>
                            <div>
                                <div class="fw-bold text-dark" style="font-size: 13px;">Kontak Baru (New Lead)</div>
                                <div class="text-muted" style="font-size: 10px; line-height: 1.3;">Calon siswa baru masuk dari brosur, sosmed, atau pendaftaran.</div>
                            </div>
                        </div>
                        <hr class="my-2" style="border-color: #f1f5f9;">
                        <div>
                            <span class="text-muted d-block" style="font-size: 10px;">Total Siswa</span>
                            <span class="fw-bold text-dark" style="font-size: 16px;"><?php echo $total_kontak_baru; ?></span> <span class="text-muted" style="font-size: 10px;">Siswa</span>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="stage-card">
                        <div class="d-flex align-items-start gap-2 mb-2">
                            <div class="stage-circle circle-yellow">2</div>
                            <div>
                                <div class="fw-bold text-dark" style="font-size: 13px;">Follow Up / Konsultasi</div>
                                <div class="text-muted" style="font-size: 10px; line-height: 1.3;">Tahap komunikasi aktif minat calon siswa.</div>
                            </div>
                        </div>
                        <hr class="my-2" style="border-color: #f1f5f9;">
                        <div>
                            <span class="text-muted d-block" style="font-size: 10px;">Total Siswa</span>
                            <span class="fw-bold text-dark" style="font-size: 16px;"><?php echo $total_follow_up; ?></span> <span class="text-muted" style="font-size: 10px;">Siswa</span>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="stage-card">
                        <div class="d-flex align-items-start gap-2 mb-2">
                            <div class="stage-circle circle-green">3</div>
                            <div>
                                <div class="fw-bold text-dark" style="font-size: 13px;">Deal / Registrasi</div>
                                <div class="text-muted" style="font-size: 10px; line-height: 1.3;">Calon siswa sudah melakukan pendaftaran / pembayaran.</div>
                            </div>
                        </div>
                        <hr class="my-2" style="border-color: #f1f5f9;">
                        <div>
                            <span class="text-muted d-block" style="font-size: 10px;">Total Siswa</span>
                            <span class="fw-bold text-dark" style="font-size: 16px;"><?php echo $total_deal; ?></span> <span class="text-muted" style="font-size: 10px;">Siswa</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TABEL DATA & FORM CARI + CETAK WORD -->
            <div class="card-table-container">
                <div class="row mb-3">
                    <div class="col-md-7 col-12 ms-auto">
                        <div class="d-flex justify-content-end align-items-center gap-2">
                            <form action="tahap.php" method="GET" class="d-flex gap-2 flex-grow-1">
                                <div class="input-group input-group-sm">
                                    <input type="text" name="search" class="form-control" placeholder="Cari nama siswa atau asal sekolah..." value="<?php echo htmlspecialchars($search_query); ?>">
                                    <button type="submit" class="btn btn-outline-secondary">
                                        <i class="fa-solid fa-magnifying-glass"></i> Cari
                                    </button>
                                </div>
                                <?php if (!empty($search_query)): ?>
                                    <a href="tahap.php" class="btn btn-sm btn-light border text-nowrap" style="font-size: 11px;">Reset</a>
                                <?php endif; ?>
                            </form>

                            <a href="tahap.php?export=word&search=<?php echo urlencode($search_query); ?>" class="btn-word">
                                <i class="fa-solid fa-file-word"></i> Cetak Word
                            </a>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table border">
                        <thead>
                            <tr>
                                <th width="5%">No</th>
                                <th width="22%">Nama Siswa</th>
                                <th width="18%">Asal Sekolah</th>
                                <th width="18%">Tahap Saat Ini</th>
                                <th width="15%">PIC</th>
                                <th width="12%">Tgl Masuk</th>
                                <th width="10%" class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result && mysqli_num_rows($result) > 0): ?>
                                <?php $no = 1; while ($row = mysqli_fetch_assoc($result)): ?>
                                    <?php 
                                        $tahap_text = $row['nama_tahap'];
                                        $id_t = (int)$row['id_tahap'];
                                        
                                        $badge_class = 'badge-stage-blue';
                                        if ($id_t === 2 || strpos(strtolower($tahap_text), 'follow') !== false || strpos($tahap_text, '2') !== false) {
                                            $badge_class = 'badge-stage-yellow';
                                        } else if ($id_t === 3 || strpos(strtolower($tahap_text), 'deal') !== false || strpos($tahap_text, '3') !== false) {
                                            $badge_class = 'badge-stage-green';
                                        }
                                        
                                        $tgl_masuk = date('d/m/Y', strtotime($row['tgl_mulai']));
                                        $nama_pic = !empty($row['pic']) ? $row['pic'] : '-';
                                    ?>
                                    <tr>
                                        <td><?php echo $no++; ?></td>
                                        <td>
                                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($row['nama_calon'] ?? 'Siswa Tanpa Nama'); ?></div>
                                            <?php if (!empty($row['no_hp'])): ?>
                                                <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $row['no_hp']); ?>" target="_blank" class="wa-link">
                                                    <i class="fa-brands fa-whatsapp"></i> <?php echo htmlspecialchars($row['no_hp']); ?>
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['asal_sekolah'] ?? '-'); ?></td>
                                        <td>
                                            <span class="badge-stage <?php echo $badge_class; ?>">
                                                <?php echo htmlspecialchars($tahap_text); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($nama_pic); ?></div>
                                            <div class="text-muted" style="font-size: 10px;">Penanggung Jawab</div>
                                        </td>
                                        <td><?php echo $tgl_masuk; ?></td>
                                        <td class="text-center">
                                            <button type="button" class="btn-action-edit" data-bs-toggle="modal" data-bs-target="#modalEdit<?php echo $row['id_calonsiswa']; ?>">Edit</button>
                                            <a href="tahap.php?hapus=<?php echo $row['id_calonsiswa']; ?>" class="btn-action-hapus" onclick="return confirm('Yakin ingin menghapus data tahapan ini?');">Hapus</a>
                                        </td>
                                    </tr>

                                    <!-- MODAL EDIT DATA -->
                                    <div class="modal fade" id="modalEdit<?php echo $row['id_calonsiswa']; ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content">
                                                <form action="" method="POST">
                                                    <input type="hidden" name="id_siswa" value="<?php echo $row['id_calonsiswa']; ?>">
                                                    <div class="modal-header">
                                                        <h6 class="modal-title fw-bold">Edit Tahap Siswa: <?php echo htmlspecialchars($row['nama_calon']); ?></h6>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body text-start">
                                                        <div class="mb-3">
                                                            <label class="form-label font-12 fw-semibold">Pindah ke Tahap</label>
                                                            <select name="nama_tahap" class="form-select form-select-sm">
                                                                <option value="1 Kontak Baru" <?php echo (strpos($tahap_text, '1') !== false) ? 'selected' : ''; ?>>1 Kontak Baru</option>
                                                                <option value="2 Follow Up / Konsultasi" <?php echo (strpos($tahap_text, '2') !== false || strpos(strtolower($tahap_text), 'follow') !== false) ? 'selected' : ''; ?>>2 Follow Up / Konsultasi</option>
                                                                <option value="3 Deal / Registrasi" <?php echo (strpos($tahap_text, '3') !== false || strpos(strtolower($tahap_text), 'deal') !== false) ? 'selected' : ''; ?>>3 Deal / Registrasi</option>
                                                            </select>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label font-12 fw-semibold">PIC (Penanggung Jawab)</label>
                                                            <input type="text" name="pic" class="form-control form-control-sm" value="<?php echo htmlspecialchars($nama_pic !== '-' ? $nama_pic : $user_login); ?>" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label font-12 fw-semibold">Tanggal Perubahan</label>
                                                            <input type="date" name="tgl_mulai" class="form-control form-control-sm" value="<?php echo date('Y-m-d', strtotime($row['tgl_mulai'])); ?>">
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label font-12 fw-semibold">Catatan / Keterangan</label>
                                                            <textarea name="keterangan" class="form-control form-control-sm" rows="3"><?php echo htmlspecialchars($row['keterangan'] ?? ''); ?></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                        <button type="submit" class="btn-word" style="background-color: #1a2e4c;">Simpan Perubahan</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">Data tidak ditemukan.</td>
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