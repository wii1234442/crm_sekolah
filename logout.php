<?php
session_start();

// Jika pengguna mengonfirmasi untuk keluar (klik 'Ya, Logout')
if (isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
    session_unset();
    session_destroy();

    // Hapus cookie sesi jika ada
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    // Arahkan ke halaman login
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konfirmasi Logout - CRM System</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome Icon -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
        }
        .logout-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            padding: 30px;
            max-width: 400px;
            width: 100%;
            text-align: center;
        }
        .icon-box {
            width: 60px;
            height: 60px;
            background-color: #fff3cd;
            color: #856404;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin: 0 auto 20px;
        }
        .btn-yes {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 9px 20px;
            font-weight: 600;
            border-radius: 6px;
            text-decoration: none;
            transition: 0.2s;
        }
        .btn-yes:hover {
            background-color: #bb2d3b;
            color: white;
        }
        .btn-no {
            background-color: #6c757d;
            color: white;
            border: none;
            padding: 9px 20px;
            font-weight: 600;
            border-radius: 6px;
            text-decoration: none;
            transition: 0.2s;
        }
        .btn-no:hover {
            background-color: #5c636a;
            color: white;
        }
    </style>
</head>
<body>

    <div class="logout-card">
        <div class="icon-box">
            <i class="fa-solid fa-right-from-bracket"></i>
        </div>
        <h5 class="fw-bold text-dark mb-2">Konfirmasi Keluar</h5>
        <p class="text-secondary mb-4" style="font-size: 14px;">Apakah Anda yakin ingin keluar dari aplikasi CRM Sekolah?</p>
        
        <div class="d-flex justify-content-center gap-2">
            <a href="dashboard.php" class="btn-no">Tidak / Batal</a>
            <a href="logout.php?confirm=yes" class="btn-yes">Ya, Logout</a>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>