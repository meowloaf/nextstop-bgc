<?php
session_start();
require_once "../config/database.php";

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

if ($username === '' || $password === '') {
    $_SESSION['login_error'] = 'Username and password are required.';
    header('Location: ../index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user && password_verify($password, $user['password'])) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];

    $redirect = $user['role'] === 'admin' ? '../admin/dashboard.php' : '../dashboard.php';
    header("Location: $redirect");
    exit;
}

$_SESSION['login_error'] = 'Invalid username or password.';
header('Location: ../index.php');
exit;
?>