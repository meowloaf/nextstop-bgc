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
$proofPath = null;

if ($username === '' || $password === '' || $confirmPassword === '') {
    $_SESSION['register_error'] = 'All required fields are required.';
    header("Location: ../register.php");
    exit;
}

if (!preg_match('/^[A-Za-z0-9_]{3,50}$/', $username)) {
    $_SESSION['register_error'] = 'Username must be 3-50 characters using only letters, numbers, and underscores.';
    header("Location: ../register.php");
    exit;
}

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
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

if (isset($_FILES['eligibility_proof']) && $_FILES['eligibility_proof']['error'] !== UPLOAD_ERR_NO_FILE) {
    $proof = $_FILES['eligibility_proof'];
    $allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'application/pdf' => 'pdf'];
    $fileType = $proof['type'] ?? '';
    if ($proof['error'] !== UPLOAD_ERR_OK || !isset($allowedTypes[$fileType]) || $proof['size'] > 5 * 1024 * 1024) {
        $_SESSION['register_error'] = 'Proof must be a JPG, PNG, or PDF file smaller than 5 MB.';
        header("Location: ../register.php");
        exit;
    }

    $proofDirectory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'eligibility';
    if (!is_dir($proofDirectory) && !mkdir($proofDirectory, 0755, true)) {
        $_SESSION['register_error'] = 'The proof upload folder could not be created.';
        header("Location: ../register.php");
        exit;
    }
    $proofFileName = bin2hex(random_bytes(16)) . '.' . $allowedTypes[$fileType];
    if (!move_uploaded_file($proof['tmp_name'], $proofDirectory . DIRECTORY_SEPARATOR . $proofFileName)) {
        $_SESSION['register_error'] = 'The proof file could not be saved.';
        header("Location: ../register.php");
        exit;
    }
    $proofPath = 'assets/uploads/eligibility/' . $proofFileName;
}

$stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
$stmt->execute([$username, $email ?: '']);
if ($stmt->fetch()) {
    $_SESSION['register_error'] = 'That username or email is already in use.';
    header("Location: ../register.php");
    exit;
}

$stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, fare_type, eligibility_proof) VALUES (?, ?, ?, 'user', ?, ?)");
$stmt->execute([
    $username,
    $email ?: null,
    password_hash($password, PASSWORD_DEFAULT),
    $fareType,
    $proofPath
]);

$_SESSION['register_success'] = 'Account created. You can now log in.';
header("Location: ../index.php");
exit;
