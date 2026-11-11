<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// KONEKSI DATABASE
$host = "localhost";
$user = "root";
$pass = "";
$db   = "crm_sekolah"; 

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die("Koneksi Database Gagal: " . mysqli_connect_error());
}

// ==========================================
// PROFIL USER DINAMIS DARI SESSION/DB
// ==========================================
$username_login = $_SESSION['username'] ?? $_SESSION['nama_lengkap'] ?? 'dewi';

$q_user = mysqli_query($conn, "SELECT * FROM users WHERE username = '$username_login' OR nama_lengkap = '$username_login' OR nama = '$username_login' LIMIT 1");
if ($q_user && mysqli_num_rows($q_user) > 0) {
    $user_data = mysqli_fetch_assoc($q_user);
    $nama_user    = $user_data['nama'] ?? $user_data['nama_lengkap'] ?? $user_data['username'] ?? $username_login;
    $jabatan_user = $user_data['role'] ?? $user_data['jabatan'] ?? 'Administrator';
} else {
    $nama_user    = $username_login;
    $jabatan_user = "Administrator";
}

$words = explode(' ', trim($nama_user));
$inisial = strtoupper(substr($words[0], 0, 1));

// ==========================================
// STATISTIK KARTU ATAS (SINKRON DENGAN DATABASE)
// ==========================================
$q_total   = mysqli_query($conn, "SELECT COUNT(*) as total FROM kunjungan");
$r_total   = ($q_total) ? (int)mysqli_fetch_assoc($q_total)['total'] : 0;

$q_deal    = mysqli_query($conn, "SELECT COUNT(*) as total FROM kunjungan WHERE LOWER(status) LIKE '%deal%'");
$r_deal    = ($q_deal) ? (int)mysqli_fetch_assoc($q_deal)['total'] : 0;

$q_follow  = mysqli_query($conn, "SELECT COUNT(*) as total FROM kunjungan WHERE LOWER(status) LIKE '%follow%'");
$r_follow  = ($q_follow) ? (int)mysqli_fetch_assoc($q_follow)['total'] : 0;

$q_pend    = mysqli_query($conn, "SELECT COUNT(*) as total FROM kunjungan WHERE LOWER(status) LIKE '%pending%'");
$r_pending = ($q_pend) ? (int)mysqli_fetch_assoc($q_pend)['total'] : 0;

// ==========================================
// GRAFIK BULANAN DINAMIS (OTOMATIS DETEKSI TANGGAL)
// ==========================================
$chart_data = array_fill(0, 12, 0); 
$q_all_kunjungan = mysqli_query($conn, "SELECT * FROM kunjungan");
if ($q_all_kunjungan) {
    while ($row_k = mysqli_fetch_assoc($q_all_kunjungan)) {
        foreach ($row_k as $val_col) {
            if (!empty($val_col) && preg_match('/^\d{4}-\d{2}-\d{2}/', $val_col)) {
                $b_num = (int)date('m', strtotime($val_col));
                $b_idx = $b_num - 1;
                if ($b_idx >= 0 && $b_idx < 12) {
                    $chart_data[$b_idx]++;
                }
                break;
            }
        }
    }
}

$chart_labels = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

// ==========================================
// DATA TABEL KUNJUNGAN TERBARU (URUTAN ASC AGAR KJN001 DI ATAS)
// ==========================================
$query_tabel = "SELECT * FROM kunjungan ORDER BY 1 ASC LIMIT 5";
$res_tabel   = mysqli_query($conn, $query_tabel);

$data_kunjungan = [];
if ($res_tabel && mysqli_num_rows($res_tabel) > 0) {
    while ($row = mysqli_fetch_assoc($res_tabel)) {
        $vals = array_values($row);
        $angka_id = (int)($vals[0] ?? 1);
        
        // Membentuk format persis KJN001, KJN002, dst. menggunakan padding 3 digit
        $id_tampil = 'KJN' . str_pad($angka_id, 3, '0', STR_PAD_LEFT);

        $waktu_tampil = '-';
        foreach ($row as $k => $v) {
            if (!empty($v) && (preg_match('/^\d{4}-\d{2}-\d{2}/', $v) || stripos($k, 'waktu') !== false || stripos($k, 'tgl') !== false || stripos($k, 'tanggal') !== false)) {
                if ($v != $vals[0]) {
                    $waktu_tampil = $v;
                    break;
                }
            }
        }

        if ($waktu_tampil === '-' && count($vals) >= 2) {
            $waktu_tampil = $vals[1] ?? '-';
        }

        $row['id_tampil']    = $id_tampil;
        $row['waktu_tampil'] = $waktu_tampil;
        $data_kunjungan[]    = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - CRM System</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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
        .stat-card-row { display: flex; gap: 15px; margin-bottom: 25px; }
        .stat-card { flex: 1; background-color: white; border-radius: 6px; padding: 18px 20px; border: 1px solid #eef0f3; box-shadow: 0 1px 3px rgba(0,0,0,0.03); }
        .stat-title { font-size: 11px; color: #888888; margin-bottom: 8px; font-weight: 500; }
        .stat-val { font-size: 26px; font-weight: 700; line-height: 1; }
        .box-card { background-color: white; border-radius: 6px; border: 1px solid #eef0f3; padding: 20px; margin-bottom: 25px; box-shadow: 0 1px 3px rgba(0,0,0,0.03); }
        .box-title { font-size: 14px; font-weight: 700; color: #333333; margin-bottom: 20px; }
        .badge-deal { background-color: #198754; color: white; font-size: 11px; padding: 3px 8px; border-radius: 4px; font-weight: 600; display: inline-block; }
        .badge-follow { background-color: #ffc107; color: #212529; font-size: 11px; padding: 3px 8px; border-radius: 4px; font-weight: 600; display: inline-block; }
        .badge-pending { background-color: #6c757d; color: white; font-size: 11px; padding: 3px 8px; border-radius: 4px; font-weight: 600; display: inline-block; }
        .btn-lihat-semua { border: 1px solid #ced4da; background: white; color: #555; font-size: 12px; padding: 4px 12px; border-radius: 4px; text-decoration: none; }
    </style>
</head>
<body>

    <!-- SIDEBAR -->
    <div class="sidebar">
        <h4>📈 CRM System</h4>
        <a href="dashboard.php" class="active" style="justify-content: flex-start; gap: 10px;">🏠 Dashboard</a>
        <a href="kunjungan/index.php" style="justify-content: flex-start; gap: 10px;">👥 Kunjungan</a>
        <a href="javascript:void(0);" data-bs-toggle="collapse" data-bs-target="#menuCrm" style="justify-content: space-between; align-items: center;" aria-expanded="false">
            <div style="display: flex; gap: 10px; align-items: center;"><span>👤</span><span>CRM</span></div>
            <span>▼</span>
        </a>
        <div class="collapse" id="menuCrm">
            <div style="background-color: #3b5074; display: flex; flex-direction: column;">
                <a href="/crm_sekolah/crm/index.php" style="padding-left: 45px;">Riwayat Interaksi</a>
                <a href="crm/tahap.php" style="padding-left: 45px;">Tahap</a>
                <a href="crm/agent.php" style="padding-left: 45px;">Agent</a>
                <a href="crm/label_status.php" style="padding-left: 45px;">Label Status</a>
            </div>
        </div>
        <a href="laporan/index.php" style="justify-content: flex-start; gap: 10px;">📋 Laporan</a>
        <a href="users/index.php" style="justify-content: flex-start; gap: 10px;">🧑 Pengguna</a>
        <a href="pengaturan/index.php" style="justify-content: flex-start; gap: 10px;">⚙️ Pengaturan</a>
        <a href="logout.php" style="position: absolute; bottom: 20px; width: 100%; justify-content: flex-start; gap: 10px;">🚪 Logout</a>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-content">
        <div class="top-header">
            <div>
                <h5 class="m-0 text-dark fw-bold" style="font-size: 18px;">Dashboard</h5>
                <span class="text-muted" style="font-size: 12px;">Selamat datang di Dashboard, <?= htmlspecialchars($nama_user) ?></span>
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
            
            <!-- STATISTIK KARTU -->
            <div class="stat-card-row">
                <div class="stat-card">
                    <div class="stat-title">Total Kunjungan</div>
                    <div class="stat-val text-dark"><?= $r_total ?></div>
                </div>

                <div class="stat-card">
                    <div class="stat-title">Status Deal</div>
                    <div class="stat-val text-success"><?= $r_deal ?></div>
                </div>

                <div class="stat-card">
                    <div class="stat-title">Follow Up</div>
                    <div class="stat-val" style="color: #d97706;"><?= $r_follow ?></div>
                </div>

                <div class="stat-card">
                    <div class="stat-title">Pending</div>
                    <div class="stat-val text-secondary"><?= $r_pending ?></div>
                </div>
            </div>

            <!-- GRAFIK -->
            <div class="box-card">
                <div class="box-title">Statistik Kunjungan (Januari - Desember)</div>
                <div style="height: 280px; position: relative; width: 100%;">
                    <canvas id="chartKunjungan"></canvas>
                </div>
            </div>

            <!-- TABEL -->
            <div class="box-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="box-title m-0">Riwayat Data Kunjungan Terbaru</div>
                    <a href="kunjungan/index.php" class="btn-lihat-semua">Lihat Semua</a>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle border mb-0" style="font-size: 13px;">
                        <thead class="table-light">
                            <tr style="color: #333; font-weight: 600;">
                                <th>ID Kunjungan</th>
                                <th>Waktu</th>
                                <th>Jenis & PIC</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($data_kunjungan)): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-3">Belum ada data kunjungan.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($data_kunjungan as $row): 
                                    $id_tampil    = $row['id_tampil'] ?? '-';
                                    $jenis        = $row['jenis_kunjungan'] ?? $row['keperluan'] ?? 'Konsultasi Pendaftaran';
                                    $waktu_tampil = $row['waktu_tampil'] ?? '-';
                                    $status_db    = strtolower($row['status'] ?? '');
                                ?>
                                <tr>
                                    <td class="fw-bold text-dark"><?= htmlspecialchars($id_tampil) ?></td>
                                    <td><?= htmlspecialchars($waktu_tampil) ?></td>
                                    <td><?= htmlspecialchars($jenis) ?><div class="text-muted" style="font-size: 11px;">PIC: <?= htmlspecialchars($nama_user) ?></div></td>
                                    <td>
                                        <?php if (strpos($status_db, 'deal') !== false): ?>
                                            <span class="badge-deal">Deal</span>
                                        <?php elseif (strpos($status_db, 'pending') !== false): ?>
                                            <span class="badge-pending">Pending</span>
                                        <?php else: ?>
                                            <span class="badge-follow">Follow Up</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

    <!-- Bootstrap & Chart.js Script -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const ctx = document.getElementById('chartKunjungan');
            if (ctx) {
                new Chart(ctx.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: <?= json_encode($chart_labels) ?>,
                        datasets: [{
                            label: 'Jumlah Kunjungan',
                            data: <?= json_encode($chart_data) ?>,
                            backgroundColor: '#4a628a',
                            borderRadius: 4,
                            barThickness: 22
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            y: { beginAtZero: true, ticks: { precision: 0, stepSize: 1 } },
                            x: { grid: { display: false } }
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>