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
// 1.1 PROFIL USER DINAMIS & UPDATE STATUS AKTIF
// ==========================================
$username_login = $_SESSION['username'] ?? $_SESSION['nama_lengkap'] ?? $_SESSION['nama'] ?? 'wi';

$q_user = mysqli_query($conn, "SELECT * FROM users WHERE username = '$username_login' OR nama_lengkap = '$username_login' OR nama = '$username_login' LIMIT 1");
if ($q_user && mysqli_num_rows($q_user) > 0) {
    $user_data_login = mysqli_fetch_assoc($q_user);
    $nama_user    = $user_data_login['nama'] ?? $user_data_login['nama_lengkap'] ?? $user_data_login['username'] ?? 'wi';
    $jabatan_user = $user_data_login['jabatan'] ?? $user_data_login['role'] ?? 'Administrator';
    
    // OTOMATIS: Update status user yang sedang login menjadi 'Aktif' dan yang lain 'Tidak Aktif'
    $nama_login_db = mysqli_real_escape_string($conn, $user_data_login['username']);
    mysqli_query($conn, "UPDATE users SET status = 'Tidak Aktif'");
    mysqli_query($conn, "UPDATE users SET status = 'Aktif' WHERE username = '$nama_login_db' OR nama_lengkap = '$nama_login_db'");
} else {
    $nama_user    = "wi";
    $jabatan_user = "Administrator";
}

// Inisial Huruf untuk Avatar Profile
$words = explode(' ', trim($nama_user));
$inisial = strtoupper(substr($words[0], 0, 1));

// ==========================================
// 2. PROSES EDIT PENGGUNA
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $id_user  = trim($_POST['id_user'] ?? '');
    $nama     = trim($_POST['nama_lengkap'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $role     = trim($_POST['role'] ?? 'Agent CRM');
    $status   = trim($_POST['status'] ?? 'Aktif');

    if (!empty($id_user) && !empty($nama)) {
        $stmt = mysqli_prepare($conn, "UPDATE users SET nama_lengkap = ?, username = ?, email = ?, role = ?, status = ? WHERE id_user = ?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "ssssss", $nama, $username, $email, $role, $status, $id_user);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
        header("Location: index.php?status=success_update");
        exit();
    }
}

// ==========================================
// 3. PROSES HAPUS PENGGUNA
// ==========================================
if (isset($_GET['action']) && $_GET['action'] === 'hapus' && isset($_GET['id'])) {
    $id_user = trim($_GET['id']);
    if (!empty($id_user)) {
        $stmt = mysqli_prepare($conn, "DELETE FROM users WHERE id_user = ?");
        mysqli_stmt_bind_param($stmt, "s", $id_user);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        header("Location: index.php?status=success_delete");
        exit();
    }
}

// ==========================================
// 4. AMBIL DATA PENGGUNA DARI DATABASE
// ==========================================
$query = "SELECT * FROM users ORDER BY id_user ASC";
$result = mysqli_query($conn, $query);

$data_user = [];
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $data_user[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Pengguna - CRM System</title>
    
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
        .table-custom { margin-bottom: 0; border-collapse: separate; border-spacing: 0; background-color: white; }
        .table-custom thead th { background-color: #f1f4f8; color: #555555; font-weight: 600; font-size: 12px; border-bottom: 1px solid #e2e8f0; padding: 12px 15px; vertical-align: middle; }
        .table-custom tbody td { font-size: 13px; vertical-align: middle; padding: 12px 15px; color: #333333; border-bottom: 1px solid #edf2f7; }
        .badge-role-admin { background-color: #d9383a; color: #ffffff; font-size: 11px; font-weight: 500; padding: 4px 12px; border-radius: 12px; display: inline-block; }
        .badge-role-agent { background-color: #5b92e5; color: #ffffff; font-size: 11px; font-weight: 500; padding: 4px 12px; border-radius: 12px; display: inline-block; }
        .badge-status-aktif { background-color: #198754; color: #ffffff; padding: 4px 14px; border-radius: 12px; font-weight: 500; font-size: 11px; display: inline-block; }
        .badge-status-non { background-color: #dc3545; color: #ffffff; padding: 4px 14px; border-radius: 12px; font-weight: 500; font-size: 11px; display: inline-block; }
        .btn-action-edit { border: 1px solid #2b7fff; color: #2b7fff; background-color: #ffffff; font-size: 12px; padding: 2px 10px; border-radius: 4px; text-decoration: none; transition: 0.2s; }
        .btn-action-edit:hover { background-color: #2b7fff; color: #ffffff; }
        .btn-action-delete { border: 1px solid #ff4d4f; color: #ff4d4f; background-color: #ffffff; font-size: 12px; padding: 2px 10px; border-radius: 4px; text-decoration: none; transition: 0.2s; }
        .btn-action-delete:hover { background-color: #ff4d4f; color: #ffffff; }
    </style>
</head>
<body>

    <!-- SIDEBAR -->
    <div class="sidebar">
        <h4>📈 CRM System</h4>
        <a href="/crm_sekolah/dashboard.php"> 🏠 Dashboard </a>
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
        <a href="index.php" class="active" style="justify-content: flex-start; gap: 10px;">🧑 Pengguna</a>
        <a href="../pengaturan/index.php" style="justify-content: flex-start; gap: 10px;">⚙️ Pengaturan</a>
        <a href="logout.php" style="position: absolute; bottom: 20px; width: 100%; justify-content: flex-start; gap: 10px;">🚪 Logout</a>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-content">
        <div class="top-header">
            <div>
                <h5 class="m-0 text-dark fw-bold" style="font-size: 18px;">Manajemen Pengguna</h5>
                <span class="text-muted" style="font-size: 12px;">Dashboard / Pengguna Sistem</span>
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
                        Data pengguna berhasil diperbarui!
                        <button type="button" class="btn-close py-2" data-bs-dismiss="alert"></button>
                    </div>
                <?php elseif ($_GET['status'] === 'success_delete'): ?>
                    <div class="alert alert-warning alert-dismissible fade show py-2" style="font-size: 12px;" role="alert">
                        Pengguna berhasil dihapus!
                        <button type="button" class="btn-close py-2" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h6 class="fw-bold text-dark mb-1" style="font-size: 16px;">Daftar Akun Pengguna Sistem</h6>
                    <p class="text-muted mb-0" style="font-size: 12px;">Pengaturan hak akses login admin, petugas, dan agent</p>
                </div>
            </div>

            <div class="card border-0 shadow-sm" style="border-radius: 4px; overflow: hidden;">
                <div class="table-responsive">
                    <table class="table table-custom align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width: 100px; text-align: center;">ID User</th>
                                <th style="width: 200px;">Nama Lengkap</th>
                                <th style="width: 150px;">Username</th>
                                <th>Email</th>
                                <th style="width: 160px; text-align: center;">Hak Akses (Role)</th>
                                <th style="width: 120px; text-align: center;">Status Akun</th>
                                <th style="width: 120px; text-align: center;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($data_user as $row): ?>
                            <tr>
                                <td style="text-align: center;" class="text-dark fw-semibold"><?= htmlspecialchars($row['id_user']) ?></td>
                                <td class="fw-semibold text-dark"><?= htmlspecialchars($row['nama_lengkap']) ?></td>
                                <td class="text-muted"><?= htmlspecialchars($row['username']) ?></td>
                                <td class="text-muted"><?= htmlspecialchars($row['email']) ?></td>
                                <td style="text-align: center;">
                                    <?php 
                                        $role = $row['role'];
                                        if (strtolower($role) === 'administrator' || strtolower($role) === 'admin') {
                                            echo '<span class="badge-role-admin">Administrator</span>';
                                        } else {
                                            echo '<span class="badge-role-agent">Agent CRM</span>';
                                        }
                                    ?>
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
                                <td style="text-align: center;">
                                    <div class="d-flex justify-content-center gap-1">
                                        <button type="button" class="btn-action-edit" 
                                           data-bs-toggle="modal" data-bs-target="#modalEditPengguna" 
                                           data-id="<?= htmlspecialchars($row['id_user']) ?>"
                                           data-nama="<?= htmlspecialchars($row['nama_lengkap']) ?>"
                                           data-username="<?= htmlspecialchars($row['username']) ?>"
                                           data-email="<?= htmlspecialchars($row['email']) ?>"
                                           data-role="<?= htmlspecialchars($row['role']) ?>"
                                           data-status="<?= htmlspecialchars($row['status'] ?? 'Aktif') ?>">Edit</button>
                                        
                                        <a href="index.php?action=hapus&id=<?= htmlspecialchars($row['id_user']) ?>" 
                                           class="btn-action-delete" onclick="return confirm('Apakah Anda yakin ingin menghapus pengguna ini?');">Hapus</a>
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

    <!-- MODAL EDIT PENGGUNA -->
    <div class="modal fade" id="modalEditPengguna" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 450px;">
            <div class="modal-content" style="border-radius: 8px;">
                <div class="modal-header border-bottom-0 pb-0">
                    <h6 class="modal-title fw-bold" style="font-size: 15px;">Edit Data Pengguna</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="index.php" method="POST">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id_user" id="edit_id_user">
                    <div class="modal-body pt-3 pb-2" style="font-size: 13px;">
                        <div class="mb-2">
                            <label class="form-label text-secondary mb-1">Nama Lengkap <span class="text-danger">*</span></label>
                            <input type="text" name="nama_lengkap" id="edit_nama_lengkap" class="form-control form-control-sm" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label text-secondary mb-1">Username <span class="text-danger">*</span></label>
                            <input type="text" name="username" id="edit_username" class="form-control form-control-sm" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label text-secondary mb-1">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" id="edit_email" class="form-control form-control-sm" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label text-secondary mb-1">Hak Akses (Role)</label>
                            <select name="role" id="edit_role" class="form-select form-select-sm">
                                <option value="Administrator">Administrator</option>
                                <option value="Agent CRM">Agent CRM</option>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label text-secondary mb-1">Status Akun</label>
                            <select name="status" id="edit_status" class="form-select form-select-sm">
                                <option value="Aktif">Aktif</option>
                                <option value="Tidak Aktif">Tidak Aktif</option>
                            </select>
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
            var modalEdit = document.getElementById('modalEditPengguna');
            if (modalEdit) {
                modalEdit.addEventListener('show.bs.modal', function (event) {
                    var button = event.relatedTarget;
                    modalEdit.querySelector('#edit_id_user').value = button.getAttribute('data-id') || '';
                    modalEdit.querySelector('#edit_nama_lengkap').value = button.getAttribute('data-nama') || '';
                    modalEdit.querySelector('#edit_username').value = button.getAttribute('data-username') || '';
                    modalEdit.querySelector('#edit_email').value = button.getAttribute('data-email') || '';
                    modalEdit.querySelector('#edit_role').value = button.getAttribute('data-role') || 'Agent CRM';
                    modalEdit.querySelector('#edit_status').value = button.getAttribute('data-status') || 'Aktif';
                });
            }
        });
    </script>
</body>
</html>