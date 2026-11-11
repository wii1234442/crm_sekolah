<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>CRM Sekolah Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
  <div class="container">
    <a class="navbar-brand" href="index.php">CRM System</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav me-auto">
        <li class="nav-item"><a class="nav-link" href="index.php?page=dashboard">Dashboard</a></li>
        <li class="nav-item"><a class="nav-link" href="index.php?page=label_status">Label Status</a></li>
        <!-- Tambahkan menu tabel lain di sini -->
      </ul>
      <span class="navbar-text me-3">Halo, <?= htmlspecialchars($_SESSION['nama']) ?></span>
      <a href="logout.php" class="btn btn-sm btn-danger">Logout</a>
    </div>
  </div>
</nav>
<div class="container">