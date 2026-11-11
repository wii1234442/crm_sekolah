<?php
session_start();

// ==========================================
// 1. KONEKSI DATABASE MYSQL (crm_sekolah)
// ==========================================
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
$username_login = $_SESSION['username'] ?? $_SESSION['nama_lengkap'] ?? 'dewi';

$q_user = mysqli_query($conn, "SELECT * FROM users WHERE username = '$username_login' OR nama_lengkap = '$username_login' OR nama = '$username_login' LIMIT 1");
if ($q_user && mysqli_num_rows($q_user) > 0) {
    $user_data_login = mysqli_fetch_assoc($q_user);
    $nama_user    = $user_data_login['nama'] ?? $user_data_login['nama_lengkap'] ?? 'dewi';
    $jabatan_user = $user_data_login['jabatan'] ?? $user_data_login['role'] ?? 'admin';
} else {
    $nama_user    = "dewi";
    $jabatan_user = "admin";
}

$words = explode(' ', trim($nama_user));
$inisial = strtoupper(substr($words[0], 0, 1));

// ==========================================
// 2. PROSES EDIT LABEL
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $id_label   = trim($_POST['id_label'] ?? ''); 
    $nama_label = trim($_POST['nama_label'] ?? '');
    $warna      = trim($_POST['warna'] ?? '#cccccc');
    $status     = trim($_POST['status'] ?? 'Aktif');
    $deskripsi  = trim($_POST['deskripsi'] ?? '');

    if (!empty($id_label) && !empty($nama_label)) {
        $stmt = mysqli_prepare($conn, "UPDATE label_status SET nama_label = ?, warna = ?, status = ?, deskripsi = ? WHERE id_label = ?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "sssss", $nama_label, $warna, $status, $deskripsi, $id_label);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
        header("Location: label_status.php?status=success_update");
        exit();
    }
}

// ==========================================
// 3. PROSES HAPUS LABEL
// ==========================================
if (isset($_GET['action']) && $_GET['action'] === 'hapus' && isset($_GET['id'])) {
    $id_label = trim($_GET['id']);
    if (!empty($id_label)) {
        $stmt = mysqli_prepare($conn, "DELETE FROM label_status WHERE id_label = ?");
        mysqli_stmt_bind_param($stmt, "s", $id_label);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        header("Location: label_status.php?status=success_delete");
        exit();
    }
}

// ==========================================
// 4. HITUNG JUMLAH SISWA RIIL (DISAMAKAN DENGAN DASHBOARD & TAHAP)
// ==========================================
$q_deal = mysqli_query($conn, "SELECT COUNT(*) as total FROM crm_siswa WHERE LOWER(status) LIKE '%3%' OR LOWER(status) LIKE '%deal%'");
$c_deal = ($q_deal && mysqli_num_rows($q_deal) > 0) ? (int)mysqli_fetch_assoc($q_deal)['total'] : 0;

$q_minat = mysqli_query($conn, "SELECT COUNT(*) as total FROM crm_siswa WHERE LOWER(status) LIKE '%2%' OR LOWER(status) LIKE '%follow%'");
$c_minat = ($q_minat && mysqli_num_rows($q_minat) > 0) ? (int)mysqli_fetch_assoc($q_minat)['total'] : 0;

$q_ragu = mysqli_query($conn, "SELECT COUNT(*) as total FROM crm_siswa WHERE LOWER(status) NOT LIKE '%2%' AND LOWER(status) NOT LIKE '%follow%' AND LOWER(status) NOT LIKE '%3%' AND LOWER(status) NOT LIKE '%deal%'");
$c_ragu = ($q_ragu && mysqli_num_rows($q_ragu) > 0) ? (int)mysqli_fetch_assoc($q_ragu)['total'] : 0;

$data_label = [
    [
        'id_label' => 'LBL001', 
        'nama_label' => 'Deal / Closing', 
        'warna' => '#28a745', 
        'deskripsi' => 'Calon siswa sudah yakin dan melakukan pendaftaran', 
        'status' => 'Aktif', 
        'jumlah_siswa' => $c_deal
    ],
    [
        'id_label' => 'LBL002', 
        'nama_label' => 'Follow Up / Minat', 
        'warna' => '#ffc107', 
        'deskripsi' => 'Calon siswa tertarik dan masih dalam proses follow up', 
        'status' => 'Aktif', 
        'jumlah_siswa' => $c_minat
    ],
    [
        'id_label' => 'LBL003', 
        'nama_label' => 'Pending / Ragu', 
        'warna' => '#dc3545', 
        'deskripsi' => 'Calon siswa masih mempertimbangkan atau belum yakin', 
        'status' => 'Aktif', 
        'jumlah_siswa' => $c_ragu
    ]
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Label Status CRM - CRM System</title>
    
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
        .content-area { padding: 30px; flex-grow: 1; }
        .table-custom { margin-bottom: 0; border-collapse: separate; border-spacing: 0; }
        .table-custom thead th { background-color: #dcdcdc; color: #333333; font-weight: 600; font-size: 13px; border-bottom: 1px solid #cccccc; padding: 10px 15px; vertical-align: middle; }
        .table-custom tbody td { font-size: 13px; vertical-align: middle; padding: 12px 15px; color: #222222; border-bottom: 1px solid #eeeeee; }
        .badge-leads { background-color: #2b7fff; color: #ffffff; font-size: 12px; font-weight: 500; padding: 5px 14px; border-radius: 6px; display: inline-block; }
        .badge-status-aktif { background-color: #d1e7dd; color: #0f5132; padding: 4px 10px; border-radius: 4px; font-weight: 500; font-size: 12px; }
        .badge-status-non { background-color: #f8d7da; color: #842029; padding: 4px 10px; border-radius: 4px; font-weight: 500; font-size: 12px; }
        .btn-action-edit { border: 1px solid #2b7fff; color: #2b7fff; background-color: #ffffff; font-size: 12px; padding: 2px 12px; border-radius: 4px; text-decoration: none; transition: 0.2s; }
        .btn-action-edit:hover { background-color: #2b7fff; color: #ffffff; }
        .btn-action-delete { border: 1px solid #ff4d4f; color: #ff4d4f; background-color: #ffffff; font-size: 12px; padding: 2px 12px; border-radius: 4px; text-decoration: none; transition: 0.2s; }
        .btn-action-delete:hover { background-color: #ff4d4f; color: #ffffff; }
        .stats-container { display: flex; gap: 15px; margin-bottom: 25px; flex-wrap: wrap; }
        .stat-card { background-color: #ffffff; border: 1px solid #eaeaea; border-radius: 6px; padding: 15px; width: calc(33.333% - 10px); min-width: 220px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .dot-color { font-size: 11px; margin-right: 5px; }
    </style>
</head>
<body>

    <!-- SIDEBAR -->
    <div class="sidebar">
        <h4>📈 CRM System</h4>
        <a href="/crm_sekolah/dashboard.php"> 🏠 Dashboard</a>
        <a href="../kunjungan/index.php" style="justify-content: flex-start; gap: 10px;">👥 Kunjungan</a>
        <a href="javascript:void(0);" data-bs-toggle="collapse" data-bs-target="#menuCrm" style="justify-content: space-between; align-items: center;" aria-expanded="true">
            <div style="display: flex; gap: 10px; align-items: center;"><span>👤</span><span>CRM</span></div>
            <span>▼</span>
        </a>
        <div class="collapse show" id="menuCrm">
            <div style="background-color: #3b5074; display: flex; flex-direction: column;">
                <a href="/crm_sekolah/crm/index.php" style="padding-left: 45px;">Riwayat Interaksi</a>
                <a href="../crm/tahap.php" style="padding-left: 45px;">Tahap</a>
                <a href="../crm/agent.php" style="padding-left: 45px;">Agent</a>
                <a href="../crm/label_status.php" class="active" style="padding-left: 45px; border-left: none;">Label Status</a>
            </div>
        </div>
        <a href="../laporan/index.php" style="justify-content: flex-start; gap: 10px;">📋 Laporan</a>
        <a href="../users/index.php" style="justify-content: flex-start; gap: 10px;">🧑 Pengguna</a>
        <a href="../pengaturan/index.php" style="justify-content: flex-start; gap: 10px;">⚙️ Pengaturan</a>
        <a href="logout.php" style="position: absolute; bottom: 20px; width: 100%; justify-content: flex-start; gap: 10px;">🚪 Logout</a>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-content">
        <div class="top-header">
            <div>
                <h5 class="m-0 text-dark fw-bold" style="font-size: 20px;">Label Status</h5>
                <span class="text-muted" style="font-size: 12px;">Dashboard / CRM / Label Status</span>
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
            <?php if (isset($_GET['status'])): ?>
                <?php if ($_GET['status'] === 'success_update'): ?>
                    <div class="alert alert-info alert-dismissible fade show py-2" style="font-size: 12px;" role="alert">
                        Label berhasil diperbarui!
                        <button type="button" class="btn-close py-2" data-bs-dismiss="alert"></button>
                    </div>
                <?php elseif ($_GET['status'] === 'success_delete'): ?>
                    <div class="alert alert-warning alert-dismissible fade show py-2" style="font-size: 12px;" role="alert">
                        Label berhasil dihapus!
                        <button type="button" class="btn-close py-2" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h5 class="fw-bold text-dark mb-1" style="font-size: 18px;">Kelola Kategori Label Status</h5>
                    <p class="text-muted mb-0" style="font-size: 13px;">Kelola kategori tingkat ketertarikan calon siswa terhadap sekolah</p>
                </div>
            </div>

            <div class="stats-container">
                <?php foreach ($data_label as $card): ?>
                <div class="stat-card">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="fw-bold text-dark" style="font-size: 13px;">
                            <i class="fa-solid fa-square dot-color" style="color: <?= htmlspecialchars($card['warna']) ?>;"></i> 
                            <?= htmlspecialchars($card['nama_label']) ?>
                        </span>
                        <span class="text-muted" style="font-size: 11px;"><?= htmlspecialchars($card['id_label']) ?></span>
                    </div>
                    <p class="text-muted mb-3" style="font-size: 12px; height: 32px; overflow: hidden;">
                        <?= htmlspecialchars($card['deskripsi'] ?? '-') ?>
                    </p>
                    <div class="text-muted" style="font-size: 11px;">Jumlah Siswa</div>
                    <div class="fw-bold fs-5" style="color: <?= htmlspecialchars($card['warna']) ?>;">
                        <?= htmlspecialchars($card['jumlah_siswa']) ?> <span style="font-size: 12px; font-weight: normal; color: #555;">siswa</span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="card border-0 shadow-sm" style="border-radius: 4px; overflow: hidden;">
                <div class="table-responsive">
                    <table class="table table-custom align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width: 100px; text-align: center;">ID Label</th>
                                <th style="width: 160px;">Nama Label</th>
                                <th>Deskripsi</th>
                                <th style="width: 140px; text-align: center;">Jumlah Siswa</th>
                                <th style="width: 110px; text-align: center;">Status</th>
                                <th class="text-center" style="width: 130px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($data_label as $row): ?>
                            <tr>
                                <td style="text-align: center;" class="fw-semibold text-dark"><?= htmlspecialchars($row['id_label']) ?></td>
                                <td>
                                    <span class="fw-semibold" style="color: <?= htmlspecialchars($row['warna']) ?>;">
                                        <i class="fa-solid fa-square dot-color"></i> <?= htmlspecialchars($row['nama_label']) ?>
                                    </span>
                                </td>
                                <td class="text-dark"><?= htmlspecialchars($row['deskripsi'] ?? '-') ?></td>
                                <td style="text-align: center;">
                                    <span class="badge-leads"><?= htmlspecialchars($row['jumlah_siswa'] ?? '0') ?> Siswa</span>
                                </td>
                                <td style="text-align: center;">
                                    <?php 
                                        $st = trim($row['status'] ?? '');
                                        if (strtolower($st) === 'aktif' || $st === '1') {
                                            echo '<span class="badge-status-aktif">Aktif</span>';
                                        } else {
                                            echo '<span class="badge-status-non">Tidak Aktif</span>';
                                        }
                                    ?>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center gap-1">
                                        <button type="button" class="btn-action-edit" 
                                           data-bs-toggle="modal" data-bs-target="#modalEditLabel" 
                                           data-id="<?= htmlspecialchars($row['id_label']) ?>"
                                           data-nama="<?= htmlspecialchars($row['nama_label']) ?>"
                                           data-warna="<?= htmlspecialchars($row['warna']) ?>"
                                           data-status="<?= htmlspecialchars($row['status'] ?? 'Aktif') ?>"
                                           data-deskripsi="<?= htmlspecialchars($row['deskripsi'] ?? '') ?>">Edit</button>
                                        
                                        <a href="label_status.php?action=hapus&id=<?= htmlspecialchars($row['id_label']) ?>" 
                                           class="btn-action-delete" onclick="return confirm('Apakah Anda yakin ingin menghapus label ini?');">Hapus</a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalEditLabel" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 420px;">
            <div class="modal-content" style="border-radius: 8px;">
                <div class="modal-header border-bottom-0 pb-0">
                    <h6 class="modal-title fw-bold" style="font-size: 14px;">Edit Data Label</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="label_status.php" method="POST">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id_label" id="edit_id_label">
                    <div class="modal-body pt-3 pb-2" style="font-size: 13px;">
                        <div class="mb-2">
                            <label class="form-label text-secondary mb-1">Nama Label <span class="text-danger">*</span></label>
                            <input type="text" name="nama_label" id="edit_nama_label" class="form-control form-control-sm" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label text-secondary mb-1">Warna Label</label>
                            <input type="color" name="warna" id="edit_warna" class="form-control form-control-sm form-control-color" style="width: 40px;">
                        </div>
                        <div class="mb-2">
                            <label class="form-label text-secondary mb-1">Status</label>
                            <select name="status" id="edit_status" class="form-select form-select-sm">
                                <option value="Aktif">Aktif</option>
                                <option value="Tidak Aktif">Tidak Aktif</option>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label text-secondary mb-1">Deskripsi</label>
                            <textarea name="deskripsi" id="edit_deskripsi" class="form-control form-control-sm" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-top-0 pt-0">
                        <button type="button" class="btn btn-secondary btn-sm px-3" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary btn-sm px-3" style="background-color: #2b7fff; border: none;">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var modalEdit = document.getElementById('modalEditLabel');
            if (modalEdit) {
                modalEdit.addEventListener('show.bs.modal', function (event) {
                    var button = event.relatedTarget;
                    modalEdit.querySelector('#edit_id_label').value = button.getAttribute('data-id') || '';
                    modalEdit.querySelector('#edit_nama_label').value = button.getAttribute('data-nama') || '';
                    var defaultColor = button.getAttribute('data-warna');
                    if (!/^#[0-9A-F]{6}$/i.test(defaultColor)) defaultColor = '#cccccc'; 
                    modalEdit.querySelector('#edit_warna').value = defaultColor;
                    modalEdit.querySelector('#edit_status').value = button.getAttribute('data-status') || 'Aktif';
                    modalEdit.querySelector('#edit_deskripsi').value = button.getAttribute('data-deskripsi') || '';
                });
            }
        });
    </script>
</body>
</html>