<?php
$host = 'localhost';
$dbname = 'crm_sekolah';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
session_start();

function checkLogin() {
    if(!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit;
    }
}
?>