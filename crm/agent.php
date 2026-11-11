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
    $nama_user    = $user_data_login['nama'] ?? $user_data_login['nama_lengkap'] ?? $user_data_login['username'] ?? 'dewi';
    $jabatan_user = 'Administrator';
} else {
    $nama_user    = "dewi";
    $jabatan_user = "Administrator";
}

$words = explode(' ', trim($nama_user));
$inisial = strtoupper(substr($words[0], 0, 1));

// ==========================================
// 2. PROSES EDIT AGENT
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $id_agent   = (int)($_POST['id_agent'] ?? 0);
    $nama_agent = mysqli_real_escape_string($conn, trim($_POST['nama_agent'] ?? ''));
    $email      = mysqli_real_escape_string($conn, trim($_POST['email'] ?? ''));
    $no_hp      = mysqli_real_escape_string($conn, trim($_POST['no_hp'] ?? ''));
    $status     = mysqli_real_escape_string($conn, trim($_POST['status'] ?? 'Aktif'));

    if (!empty($nama_agent)) {
        @mysqli_query($conn, "UPDATE agent SET nama_agent = '$nama_agent', email = '$email', no_hp = '$no_hp', status = '$status' WHERE id_agent = '$id_agent'");
        @mysqli_query($conn, "UPDATE users SET nama_lengkap = '$nama_agent', email = '$email', no_hp = '$no_hp' WHERE id_user = '$id_agent' OR id = '$id_agent'");

        header("Location: agent.php?status=success_update");
        exit();
    }
}

// ==========================================
// 3. PROSES HAPUS AGENT
// ==========================================
if (isset($_GET['action']) && $_GET['action'] === 'hapus' && isset($_GET['id'])) {
    $id_agent = (int)$_GET['id'];
    if ($id_agent > 0) {
        @mysqli_query($conn, "DELETE FROM agent WHERE id_agent = '$id_agent'");
        @mysqli_query($conn, "DELETE FROM users WHERE id_user = '$id_agent' OR id = '$id_agent'");

        header("Location: agent.php?status=success_delete");
        exit();
    }
}

// ==========================================
// 4. MENGAMBIL DAFTAR AGENT & HITUNG JUMLAH SISWA
// ==========================================
$data_agent = [];

// Ambil semua nama unik dari tabel users, kunjungan, crm_siswa, dan siswa_tahap
$q_names = mysqli_query($conn, "
    SELECT DISTINCT nama_agent FROM (
        SELECT nama_agent FROM agent
        UNION
        SELECT username AS nama_agent FROM users
        UNION
        SELECT nama_lengkap AS nama_agent FROM users
        UNION
        SELECT nama_pic_staf AS nama_agent FROM kunjungan WHERE nama_pic_staf IS NOT NULL AND nama_pic_staf != ''
        UNION
        SELECT pic AS nama_agent FROM siswa_tahap WHERE pic IS NOT NULL AND pic != ''
    ) AS gabungan WHERE nama_agent IS NOT NULL AND nama_agent != ''
");

if ($q_names) {
    $id_counter = 1;
    while ($n_row = mysqli_fetch_assoc($q_names)) {
        $nama_agt = $n_row['nama_agent'];
        $nama_esc = mysqli_real_escape_string($conn, $nama_agt);

        // Hitung total data siswa yang ditangani oleh agent ini
        $q_count = mysqli_query($conn, "
            SELECT 
                (SELECT COUNT(*) FROM kunjungan WHERE LOWER(nama_pic_staf) = LOWER('$nama_esc')) +
                (SELECT COUNT(*) FROM siswa_tahap WHERE LOWER(pic) = LOWER('$nama_esc')) AS total
        ");
        $c_row = mysqli_fetch_assoc($q_count);
        $jumlah_siswa = (int)($c_row['total'] ?? 0);

        // Ambil info tambahan email/no_hp jika ada di tabel users atau agent
        $q_info = mysqli_query($conn, "SELECT email, no_hp FROM users WHERE username = '$nama_esc' OR nama_lengkap = '$nama_esc' LIMIT 1");
        $info = mysqli_fetch_assoc($q_info);
        
        $email_val = $info['email'] ?? ($nama_agt . '@gmail.com');
        $hp_val    = $info['no_hp'] ?? '08123456789';

        $data_agent[] = [
            'id_agent'   => $id_counter++,
            'nama_agent' => $nama_agt,
            'email'      => $email_val,
            'no_hp'      => $hp_val,
            'status'     => 'Aktif',
            'jumlah'     => $jumlah_siswa
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Agent - CRM System</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome Icon -->
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
        .btn-action-edit { border: 1px solid #2b7fff; color: #2b7fff; background-color: #ffffff; font-size: 12px; padding: 2px 12px; border-radius: 4px; text-decoration: none; transition: 0.2s; }
        .btn-action-edit:hover { background-color: #2b7fff; color: #ffffff; }
        .btn-action-delete { border: 1px solid #ff4d4f; color: #ff4d4f; background-color: #ffffff; font-size: 12px; padding: 2px 12px; border-radius: 4px; text-decoration: none; transition: 0.2s; }
        .btn-action-delete:hover { background-color: #ff4d4f; color: #ffffff; }
        .wa-link { color: #0f5132; background-color: #d1e7dd; padding: 3px 8px; border-radius: 4px; text-decoration: none; font-weight: 500; display: inline-flex; align-items: center; gap: 5px; }
        .wa-link:hover { background-color: #badbcc; color: #0f5132; }
    </style>
</head>
<body>

    <!-- SIDEBAR -->
    <div class="sidebar">
        <h4>📈 CRM System</h4>
        <a href="../dashboard.php" style="justify-content: flex-start; gap: 10px;">🏠 Dashboard</a>
        <a href="../kunjungan/index.php" style="justify-content: flex-start; gap: 10px;">👥 Kunjungan</a>
        <a href="javascript:void(0);" data-bs-toggle="collapse" data-bs-target="#menuCrm" style="justify-content: space-between; align-items: center;" aria-expanded="true">
            <div style="display: flex; gap: 10px; align-items: center;"><span>👤</span><span>CRM</span></div>
            <span>▼</span>
        </a>
        <div class="collapse show" id="menuCrm">
            <div style="background-color: #3b5074; display: flex; flex-direction: column;">
                <a href="../crm/index.php" style="padding-left: 45px;">Riwayat Interaksi</a>
                <a href="../crm/tahap.php" style="padding-left: 45px;">Tahap</a>
                <a href="../crm/agent.php" class="active" style="padding-left: 45px; border-left: none;">Agent</a>
                <a href="../crm/label_status.php" style="padding-left: 45px;">Label Status</a>
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
                <h5 class="m-0 text-dark fw-bold" style="font-size: 20px;">Agent</h5>
                <span class="text-muted" style="font-size: 12px;">Dashboard / CRM / Agent</span>
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
                        Data agent berhasil diperbarui!
                        <button type="button" class="btn-close py-2" data-bs-dismiss="alert"></button>
                    </div>
                <?php elseif ($_GET['status'] === 'success_delete'): ?>
                    <div class="alert alert-warning alert-dismissible fade show py-2" style="font-size: 12px;" role="alert">
                        Data agent telah dihapus!
                        <button type="button" class="btn-close py-2" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <div class="mb-4">
                <h5 class="fw-bold text-dark mb-1" style="font-size: 18px;">Manajemen Agent / Petugas</h5>
                <p class="text-muted mb-0" style="font-size: 13px;">Daftar agent penanggung jawab interaksi & kunjungan siswa</p>
            </div>

            <div class="card border-0 shadow-sm" style="border-radius: 4px; overflow: hidden;">
                <div class="table-responsive">
                    <table class="table table-custom align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width: 80px;">No</th>
                                <th>Nama Agent / Petugas</th>
                                <th>Email</th>
                                <th>No HP / Whatsapp</th>
                                <th style="width: 110px;">Status</th>
                                <th class="text-center" style="width: 140px;">Jumlah Siswa</th>
                                <th class="text-center" style="width: 120px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($data_agent)): ?>
                                <?php $no_agt = 1; foreach ($data_agent as $row): ?>
                                <tr>
                                    <td class="fw-semibold text-dark"><?= $no_agt++ ?></td>
                                    <td class="fw-semibold text-dark"><?= htmlspecialchars($row['nama_agent']) ?></td>
                                    <td class="text-dark"><?= htmlspecialchars(!empty($row['email']) ? $row['email'] : '-') ?></td>
                                    <td>
                                        <?php 
                                            $hp = trim($row['no_hp'] ?? '');
                                            if (!empty($hp) && $hp !== '-') {
                                                $wa_number = preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $hp));
                                                echo '<a href="https://wa.me/' . $wa_number . '" target="_blank" class="wa-link"><i class="fa-brands fa-whatsapp"></i> ' . htmlspecialchars($hp) . '</a>';
                                            } else {
                                                echo '-';
                                            }
                                        ?>
                                    </td>
                                    <td>
                                        <span class="badge-status-aktif">Aktif</span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge-leads"><?= (int)($row['jumlah'] ?? 0) ?> Siswa</span>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex justify-content-center gap-1">
                                            <button type="button" class="btn-action-edit"
                                                data-bs-toggle="modal" 
                                                data-bs-target="#modalEditAgent"
                                                data-id="<?= htmlspecialchars($row['id_agent']) ?>"
                                                data-nama="<?= htmlspecialchars($row['nama_agent']) ?>"
                                                data-email="<?= htmlspecialchars($row['email'] ?? '') ?>"
                                                data-hp="<?= htmlspecialchars($row['no_hp'] ?? '') ?>"
                                                data-status="Aktif">
                                                Edit
                                            </button>
                                            <a href="agent.php?action=hapus&id=<?= htmlspecialchars($row['id_agent']) ?>" 
                                               class="btn-action-delete" 
                                               onclick="return confirm('Apakah Anda yakin ingin menghapus agent ini?');">
                                                Hapus
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">Belum ada agent/petugas terdaftar.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL EDIT AGENT -->
    <div class="modal fade" id="modalEditAgent" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 420px;">
            <div class="modal-content" style="border-radius: 8px;">
                <div class="modal-header border-bottom-0 pb-0">
                    <h6 class="modal-title fw-bold" style="font-size: 14px;">Edit Data Agent</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="agent.php" method="POST">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id_agent" id="edit_id_agent">

                    <div class="modal-body pt-3 pb-2" style="font-size: 13px;">
                        <div class="mb-2">
                            <label class="form-label text-secondary mb-1">Nama Agent <span class="text-danger">*</span></label>
                            <input type="text" name="nama_agent" id="edit_nama_agent" class="form-control form-control-sm" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label text-secondary mb-1">Email</label>
                            <input type="email" name="email" id="edit_email" class="form-control form-control-sm">
                        </div>
                        <div class="mb-2">
                            <label class="form-label text-secondary mb-1">No HP / WhatsApp</label>
                            <input type="text" name="no_hp" id="edit_no_hp" class="form-control form-control-sm" placeholder="Contoh: 08123456789">
                        </div>
                        <div class="mb-2">
                            <label class="form-label text-secondary mb-1">Status</label>
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
            var modalEdit = document.getElementById('modalEditAgent');
            if (modalEdit) {
                modalEdit.addEventListener('show.bs.modal', function (event) {
                    var button = event.relatedTarget;
                    modalEdit.querySelector('#edit_id_agent').value = button.getAttribute('data-id') || '';
                    modalEdit.querySelector('#edit_nama_agent').value = button.getAttribute('data-nama') || '';
                    modalEdit.querySelector('#edit_email').value = button.getAttribute('data-email') || '';
                    modalEdit.querySelector('#edit_no_hp').value = button.getAttribute('data-hp') || '';
                    modalEdit.querySelector('#edit_status').value = button.getAttribute('data-status') || 'Aktif';
                });
            }
        });
    </script>
</body>
</html>