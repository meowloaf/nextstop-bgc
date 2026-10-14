<?php
session_start();
require_once "../config/database.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../register.php");
    exit;
}

$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';
$fareType = $_POST['fare_type'] ?? 'regular';

if ($username === '' || $email === '' || $password === '' || $confirmPassword === '') {
    $_SESSION['register_error'] = 'All required fields are required.';
    header("Location: ../register.php");
    exit;
}

if (!preg_match('/^[A-Za-z0-9_]{3,50}$/', $username)) {
    $_SESSION['register_error'] = 'Username must be 3-50 characters using only letters, numbers, and underscores.';
    header("Location: ../register.php");
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
    $_SESSION['register_error'] = 'Please enter a valid email address.';
    header("Location: ../register.php");
    exit;
}

if (strlen($password) < 6) {
    $_SESSION['register_error'] = 'Password must be at least 6 characters.';
    header("Location: ../register.php");
    exit;
}

if ($password !== $confirmPassword) {
    $_SESSION['register_error'] = 'Passwords do not match.';
    header("Location: ../register.php");
    exit;
}

if (!in_array($fareType, ['regular', 'student', 'senior', 'pwd'], true)) {
    $_SESSION['register_error'] = 'Please choose a valid fare type.';
    header("Location: ../register.php");
    exit;
}

$stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1");
$stmt->execute([$username, $email]);
if ($stmt->fetch()) {
    $_SESSION['register_error'] = 'That username or email is already in use.';
    header("Location: ../register.php");
    exit;
}

$stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, fare_type) VALUES (?, ?, ?, 'user', ?)");
$stmt->execute([
    $username,
    $email,
    password_hash($password, PASSWORD_DEFAULT),
    $fareType
]);

$_SESSION['register_success'] = 'Account created. You can now log in.';
header("Location: ../index.php");
exit;
