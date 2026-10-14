<?php
session_start();
require_once "../config/database.php";

$identifier = trim($_POST['identifier'] ?? $_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if ($identifier === '' || $password === '') {
    $_SESSION['login_error'] = 'Email or username and password are required.';
    header('Location: ../index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1");
$stmt->execute([$identifier, $identifier]);
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