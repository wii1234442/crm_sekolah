<?php
// ==========================================
// 1. KONEKSI DATABASE & SESSION
// ==========================================
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

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
$username_login = $_SESSION['username'] ?? '';
$nama_session   = $_SESSION['nama_lengkap'] ?? $_SESSION['nama'] ?? 'wi';
$role_session   = $_SESSION['role'] ?? 'Administrator';

if (!empty($username_login)) {
    $q_user = @mysqli_query($conn, "SELECT * FROM users WHERE username = '$username_login' LIMIT 1");
    if ($q_user && mysqli_num_rows($q_user) > 0) {
        $user_data = mysqli_fetch_assoc($q_user);
        $nama_user    = $user_data['nama_lengkap'] ?? $user_data['nama'] ?? $user_data['username'];
        $jabatan_user = $user_data['role'] ?? 'Administrator';
    } else {
        $nama_user    = $nama_session;
        $jabatan_user = $role_session;
    }
} else {
    $nama_user    = "wi";
    $jabatan_user = "Administrator";
}

$words = explode(' ', trim($nama_user));
$inisial = strtoupper(substr($words[0], 0, 1) . (isset($words[1]) ? substr($words[1], 0, 1) : ''));

// ==========================================
// 1.2 DETEKSI NAMA KOLOM TANGGAL PADA TABEL KUNJUNGAN
// ==========================================
$kolom_tgl_kunjungan = 'waktu'; // Default
$cek_kolom = @mysqli_query($conn, "SHOW COLUMNS FROM kunjungan");
if ($cek_kolom) {
    while ($col = mysqli_fetch_assoc($cek_kolom)) {
        $nama_field = strtolower($col['Field']);
        if (in_array($nama_field, ['waktu', 'tanggal', 'tgl_kunjungan', 'created_at', 'date', 'tgl'])) {
            $kolom_tgl_kunjungan = $col['Field'];
            break;
        }
    }
}

// ==========================================
// 2. HITUNG KARTU RINGKASAN KESELURUHAN (REAL-TIME)
// ==========================================
$q_kunjungan = @mysqli_query($conn, "SELECT COUNT(*) as total FROM kunjungan");
$r_kunjungan = $q_kunjungan ? (int)mysqli_fetch_assoc($q_kunjungan)['total'] : 0;

$q_leads = @mysqli_query($conn, "SELECT COUNT(*) as total FROM crm_siswa");
$r_leads = $q_leads ? (int)mysqli_fetch_assoc($q_leads)['total'] : 0;

$q_deal = @mysqli_query($conn, "SELECT COUNT(DISTINCT s.id_calonsiswa) as total 
                                FROM crm_siswa s 
                                LEFT JOIN siswa_tahap st ON s.id_calonsiswa = st.id_siswa 
                                WHERE LOWER(COALESCE(st.nama_tahap, s.status, '')) LIKE '%deal%' 
                                   OR LOWER(COALESCE(st.nama_tahap, s.status, '')) LIKE '%registrasi%'
                                   OR LOWER(COALESCE(st.nama_tahap, s.status, '')) LIKE '%3%'");
$r_deal = $q_deal ? (int)mysqli_fetch_assoc($q_deal)['total'] : 0;

$total_gabung = max($r_kunjungan + $r_leads, 1);
$closing_rate = round(($r_deal / $total_gabung) * 100);

// ==========================================
// 3. AMBIL DAFTAR BULAN SECARA OTOMATIS
// ==========================================
$daftar_bulan = [];

$q_b1 = @mysqli_query($conn, "SELECT DISTINCT LEFT($kolom_tgl_kunjungan, 7) AS thn_bln FROM kunjungan WHERE $kolom_tgl_kunjungan IS NOT NULL AND $kolom_tgl_kunjungan != ''");
if ($q_b1) {
    while ($b1 = mysqli_fetch_assoc($q_b1)) {
        if (!empty($b1['thn_bln']) && strlen($b1['thn_bln']) >= 7) {
            $daftar_bulan[$b1['thn_bln']] = $b1['thn_bln'];
        }
    }
}

$q_b2 = @mysqli_query($conn, "SELECT DISTINCT LEFT(tgl_followup, 7) AS thn_bln FROM crm_siswa WHERE tgl_followup IS NOT NULL AND tgl_followup != ''");
if ($q_b2) {
    while ($b2 = mysqli_fetch_assoc($q_b2)) {
        if (!empty($b2['thn_bln']) && strlen($b2['thn_bln']) >= 7) {
            $daftar_bulan[$b2['thn_bln']] = $b2['thn_bln'];
        }
    }
}

if (empty($daftar_bulan)) {
    $daftar_bulan[date('Y-m')] = date('Y-m');
}

ksort($daftar_bulan);

$rekap_bulanan = [];
$en_months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
$id_months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

foreach ($daftar_bulan as $thn_bln) {
    $nama_bln_en = date('F Y', strtotime($thn_bln . '-01'));
    $nama_bln_indo = str_replace($en_months, $id_months, $nama_bln_en);

    // Hitung kunjungan menggunakan kolom dinamis yang terdeteksi
    $q_k_bln = @mysqli_query($conn, "SELECT COUNT(*) as total FROM kunjungan WHERE LEFT($kolom_tgl_kunjungan, 7) = '$thn_bln'");
    $tot_k_bln = $q_k_bln ? (int)mysqli_fetch_assoc($q_k_bln)['total'] : 0;

    // Hitung leads dan pendapatan SPP
    $q_l_bln = @mysqli_query($conn, "SELECT COUNT(*) as total, SUM(biaya_spp) as spp FROM crm_siswa WHERE LEFT(tgl_followup, 7) = '$thn_bln'");
    $res_l_bln = $q_l_bln ? mysqli_fetch_assoc($q_l_bln) : [];
    $tot_l_bln = (int)($res_l_bln['total'] ?? 0);
    $tot_spp_bln = (float)($res_l_bln['spp'] ?? 0);

    // Hitung deal
    $q_d_bln = @mysqli_query($conn, "SELECT COUNT(DISTINCT s.id_calonsiswa) as total FROM crm_siswa s LEFT JOIN siswa_tahap st ON s.id_calonsiswa = st.id_siswa WHERE LEFT(s.tgl_followup, 7) = '$thn_bln' AND (LOWER(COALESCE(st.nama_tahap, s.status, '')) LIKE '%deal%' OR LOWER(COALESCE(st.nama_tahap, s.status, '')) LIKE '%registrasi%' OR LOWER(COALESCE(st.nama_tahap, s.status, '')) LIKE '%3%')");
    $res_d_bln = $q_d_bln ? mysqli_fetch_assoc($q_d_bln) : [];
    $tot_d_bln = (int)($res_d_bln['total'] ?? 0);

    $rekap_bulanan[] = [
        'bulan' => $nama_bln_indo,
        'total_kunjungan' => $tot_k_bln,
        'total_leads' => $tot_l_bln,
        'total_deal' => $tot_d_bln,
        'estimasi_pendapatan' => $tot_spp_bln
    ];
}

// ==========================================
// 4. FITUR EXPORT / CETAK WORD (.DOC)
// ==========================================
if (isset($_GET['export']) && $_GET['export'] === 'word') {
    $filename = "Laporan_Performa_CRM_" . date('Ymd_His') . ".doc";
    header("Content-Type: application/vnd.ms-word");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: no-cache");
    header("Expires: 0");

    echo "<html xmlns:o='urn:schemas-microsoft-com:office:office' xmlns:w='urn:schemas-microsoft-com:office:word' xmlns='http://www.w3.org/TR/REC-html40'>";
    echo "<head><meta charset='utf-8'><title>Laporan Performa Sistem</title>";
    echo "<style>
            body { font-family: Arial, sans-serif; font-size: 11pt; }
            h2 { text-align: center; margin-bottom: 5px; text-transform: uppercase; }
            p { text-align: center; font-size: 10pt; color: #555; margin-top: 0; }
            table { width: 100%; border-collapse: collapse; margin-top: 15px; }
            th, td { border: 1px solid #000; padding: 8px; text-align: left; font-size: 10pt; }
            th { background-color: #4a628a; color: #ffffff; font-weight: bold; text-align: center; }
            .summary-box { margin-bottom: 15px; font-size: 11pt; }
          </style></head><body>";
    
    echo "<h2>LAPORAN REKAPITULASI PERFORMA SISTEM CRM</h2>";
    echo "<p>Tanggal Cetak: " . date('d/m/Y H:i') . " | Oleh: " . htmlspecialchars($nama_user) . "</p>";
    
    // Ringkasan Eksekutif dalam format teks/tabel kecil untuk dokumen Word
    echo "<div class='summary-box'>";
    echo "<b>Ringkasan Eksekutif:</b><br/>";
    echo "- Total Buku Tamu (Kunjungan): " . number_format($r_kunjungan, 0, ',', '.') . " Kunjungan<br/>";
    echo "- Total Leads CRM Masuk: " . number_format($r_leads, 0, ',', '.') . " Siswa<br/>";
    echo "- Total Deal / Daftar Ulang: " . number_format($r_deal, 0, ',', '.') . " Siswa<br/>";
    echo "- Persentase Deal (Closing Rate): " . $closing_rate . "%<br/>";
    echo "</div>";

    echo "<table>";
    echo "<thead><tr>
            <th width='25%'>Bulan</th>
            <th width='20%'>Total Kunjungan Tamu</th>
            <th width='20%'>Leads Masuk (CRM)</th>
            <th width='15%'>Closing Deal</th>
            <th width='20%'>Estimasi Pendapatan</th>
          </tr></thead><tbody>";

    foreach ($rekap_bulanan as $row) {
        echo "<tr>";
        echo "<td><b>" . htmlspecialchars($row['bulan']) . "</b></td>";
        echo "<td style='text-align:center;'>" . $row['total_kunjungan'] . " Tamu</td>";
        echo "<td style='text-align:center;'>" . $row['total_leads'] . " Siswa</td>";
        echo "<td style='text-align:center;'>" . $row['total_deal'] . " Siswa</td>";
        echo "<td>Rp " . number_format($row['estimasi_pendapatan'], 0, ',', '.') . "</td>";
        echo "</tr>";
    }

    echo "</tbody></table></body></html>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan - Sistem Kunjungan & CRM</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body { background-color: #F8F9FA; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; overflow-x: hidden; margin: 0; }
        .sidebar { width: 250px; height: 100vh; background-color: #4a628a; color: white; position: fixed; top: 0; left: 0; padding-top: 20px; z-index: 1000; }
        .sidebar h4 { padding: 0 20px; margin-bottom: 30px; font-weight: bold; font-size: 18px; }
        .sidebar a { color: #e0e6ed; text-decoration: none; display: flex; align-items: center; justify-content: space-between; padding: 12px 20px; font-size: 14px; transition: 0.2s; }
        .sidebar a:hover { background-color: #3b5074; color: white; }
        .sidebar a.active { background-color: #3b5074; border-left: 4px solid white; color: white; font-weight: bold; }
        .main-content { margin-left: 250px; padding: 0; min-height: 100vh; background-color: #F8F9FA; display: flex; flex-direction: column; }
        .top-header { background-color: white; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eaeaea; }
        .content-area { padding: 25px 30px; flex-grow: 1; }
        .stat-card-row { display: flex; gap: 15px; margin-bottom: 30px; }
        .stat-card { flex: 1; border-radius: 6px; padding: 20px; color: white; box-shadow: 0 2px 4px rgba(0,0,0,0.04); }
        .card-darkblue { background-color: #4a628a; }
        .card-cyan { background-color: #00bcd4; }
        .card-green { background-color: #28a745; }
        .card-yellow { background-color: #ffc107; color: #333 !important; }
        .card-yellow .card-subtext { color: #555 !important; }
        .card-title-text { font-size: 13px; font-weight: 500; margin-bottom: 12px; opacity: 0.9; }
        .card-value-num { font-size: 32px; font-weight: 700; line-height: 1; margin-bottom: 12px; }
        .card-subtext { font-size: 11px; opacity: 0.85; }
        .table-custom { border-collapse: collapse; margin-bottom: 0; }
        .table-custom thead th { background-color: #f1f4f8; color: #444444; font-weight: 600; font-size: 12px; border-bottom: 1px solid #e2e8f0; padding: 12px 20px; text-align: center; }
        .table-custom tbody td { font-size: 13px; vertical-align: middle; padding: 14px 20px; color: #333333; border-bottom: 1px solid #edf2f7; text-align: center; }
        .badge-deal { background-color: #28a745; color: #ffffff; font-size: 11px; font-weight: 600; padding: 4px 12px; border-radius: 12px; display: inline-block; }
        .text-price { color: #2b7fff; font-weight: 700; font-size: 13px; }
        .btn-word { background-color: #2b579a; color: white; font-size: 12px; font-weight: 600; border-radius: 4px; padding: 6px 14px; border: none; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; cursor: pointer; transition: 0.2s; white-space: nowrap; }
        .btn-word:hover { background-color: #1e3d6f; color: white; }
    </style>
</head>
<body>

    <!-- SIDEBAR -->
    <div class="sidebar">
        <h4>📈 CRM System</h4>
        <a href="../dashboard.php" style="justify-content: flex-start; gap: 10px;">🏠 Dashboard</a>
        <a href="../kunjungan/index.php" style="justify-content: flex-start; gap: 10px;">👥 Kunjungan</a>
        <a href="javascript:void(0);" data-bs-toggle="collapse" data-bs-target="#menuCrm" style="justify-content: space-between; align-items: center;" aria-expanded="false">
            <div style="display: flex; gap: 10px; align-items: center;"><span>👤</span><span>CRM</span></div>
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
        <a href="index.php" class="active" style="justify-content: flex-start; gap: 10px;">📋 Laporan</a>
        <a href="../users/index.php" style="justify-content: flex-start; gap: 10px;">🧑 Pengguna</a>
        <a href="../pengaturan/index.php" style="justify-content: flex-start; gap: 10px;">⚙️ Pengaturan</a>
        <a href="../logout.php" style="position: absolute; bottom: 20px; width: 100%; justify-content: flex-start; gap: 10px;">🚪 Logout</a>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-content">
        <div class="top-header">
            <div>
                <h5 class="m-0 text-dark fw-bold" style="font-size: 18px;">Laporan Sistem</h5>
                <span class="text-muted" style="font-size: 12px;">Dashboard / Laporan Utama</span>
            </div>
            <div class="d-flex align-items-center text-end">
                <div class="me-3">
                    <span class="d-block fw-bold text-dark" style="font-size: 13px;"><?= htmlspecialchars($nama_user) ?></span>
                    <span class="text-muted" style="font-size: 11px;"><?= htmlspecialchars($jabatan_user) ?></span>
                </div>
                <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 38px; height: 38px; background-color: #dbeafe; color: #1e40af; font-size: 13px;">
                    <?= htmlspecialchars($inisial) ?>
                </div>
            </div>
        </div>

        <div class="content-area">
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h6 class="fw-bold text-dark mb-1" style="font-size: 16px;">Ringkasan Laporan Keseluruhan</h6>
                    <p class="text-muted mb-0" style="font-size: 12px;">Menampilkan data akumulasi kunjungan dan CRM secara real-time</p>
                </div>
                <div>
                    <a href="index.php?export=word" class="btn-word">
                        <i class="fa-solid fa-file-word"></i> Cetak Word
                    </a>
                </div>
            </div>

            <!-- 4 KARTU RINGKASAN PERFORMA -->
            <div class="stat-card-row">
                <div class="stat-card card-darkblue">
                    <div class="card-title-text">Total Buku Tamu (Kunjungan)</div>
                    <div class="card-value-num"><?= number_format($r_kunjungan, 0, ',', '.') ?></div>
                    <div class="card-subtext">Kunjungan Tercatat</div>
                </div>
                
                <div class="stat-card card-cyan">
                    <div class="card-title-text">Total Leads CRM Masuk</div>
                    <div class="card-value-num"><?= number_format($r_leads, 0, ',', '.') ?></div>
                    <div class="card-subtext">Calon Siswa Tertarik</div>
                </div>

                <div class="stat-card card-green">
                    <div class="card-title-text">Total Deal / Daftar Ulang</div>
                    <div class="card-value-num"><?= number_format($r_deal, 0, ',', '.') ?></div>
                    <div class="card-subtext">Siswa Resmi Bergabung</div>
                </div>

                <div class="stat-card card-yellow">
                    <div class="card-title-text">Persentase Deal (Closing Rate)</div>
                    <div class="card-value-num"><?= $closing_rate ?>%</div>
                    <div class="card-subtext">Dari total Leads & Kunjungan</div>
                </div>
            </div>

            <!-- REKAPITULASI PERFORMA BULANAN -->
            <div class="card border-0 shadow-sm" style="border-radius: 6px; overflow: hidden;">
                <div class="card-body p-0">
                    <div class="px-4 py-3 border-bottom" style="background-color: #fafafa;">
                        <h6 class="fw-bold text-dark m-0" style="font-size: 14px;">Rekapitulasi Performa Bulanan</h6>
                    </div>
                    <div class="table-responsive">
                        <table id="tabelLaporanBulanan" class="table table-custom align-middle mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 20%; text-align: left; padding-left: 30px;">Bulan</th>
                                    <th style="width: 20%;">Total Kunjungan Tamu</th>
                                    <th style="width: 20%;">Leads Masuk (CRM)</th>
                                    <th style="width: 20%;">Closing Deal (Daftar)</th>
                                    <th style="width: 20%;">Estimasi Pendapatan (SPP/Pendaftaran)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rekap_bulanan as $row): ?>
                                <tr>
                                    <td style="text-align: left; padding-left: 30px;" class="fw-semibold text-dark"><?= htmlspecialchars($row['bulan']) ?></td>
                                    <td><?= htmlspecialchars($row['total_kunjungan']) ?> Tamu</td>
                                    <td><?= htmlspecialchars($row['total_leads']) ?> Siswa</td>
                                    <td>
                                        <span class="badge-deal"><?= htmlspecialchars($row['total_deal']) ?> Siswa</span>
                                    </td>
                                    <td>
                                        <span class="text-price">Rp <?= number_format($row['estimasi_pendapatan'], 0, ',', '.') ?></span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>