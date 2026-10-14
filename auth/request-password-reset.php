<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../forgot-password.php');
    exit;
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!is_string($csrfToken) || !hash_equals($_SESSION['password_reset_csrf'] ?? '', $csrfToken)) {
    $_SESSION['password_reset_error'] = 'Your request expired. Please try again.';
    header('Location: ../forgot-password.php');
    exit;
}

$email = trim(is_string($_POST['email'] ?? null) ? $_POST['email'] : '');
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
    $_SESSION['password_reset_error'] = 'Enter a valid email address.';
    header('Location: ../forgot-password.php');
    exit;
}

try {
    $mailConfig = gmailSmtpConfig();
} catch (RuntimeException $exception) {
    $_SESSION['password_reset_error'] = $exception->getMessage();
    header('Location: ../forgot-password.php');
    exit;
}

$userStmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
$userStmt->execute([$email]);
$userId = $userStmt->fetchColumn();

$_SESSION['password_reset_email'] = $email;
if ($userId !== false) {
    $resetStmt = $pdo->prepare('SELECT created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE) FROM password_reset_codes WHERE user_id = ? LIMIT 1');
    $resetStmt->execute([(int) $userId]);
    $isRateLimited = (bool) $resetStmt->fetchColumn();

    if ($isRateLimited) {
        $_SESSION['password_reset_message'] = 'If an account uses this email, a verification code has been sent. Wait a minute before requesting another code.';
        header('Location: ../forgot-password.php');
        exit;
    }

    $code = (string) random_int(100000, 999999);
    $codeHash = password_hash($code, PASSWORD_DEFAULT);
    $saveCode = $pdo->prepare(
        'INSERT INTO password_reset_codes (user_id, code_hash, expires_at, attempts, created_at)
         VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE), 0, NOW())
         ON DUPLICATE KEY UPDATE code_hash = VALUES(code_hash), expires_at = VALUES(expires_at), attempts = 0, created_at = NOW()'
    );
    $saveCode->execute([(int) $userId, $codeHash]);

    try {
        sendGmailOtp($mailConfig, $email, $code);
    } catch (RuntimeException $exception) {
        error_log('Password reset email delivery failed: ' . $exception->getMessage());
        $discardCode = $pdo->prepare('DELETE FROM password_reset_codes WHERE user_id = ?');
        $discardCode->execute([(int) $userId]);
        unset($_SESSION['password_reset_email']);
        $_SESSION['password_reset_error'] = 'The verification email could not be sent right now. Please try again later.';
        header('Location: ../forgot-password.php');
        exit;
    }
}

$_SESSION['password_reset_message'] = 'If an account uses this email, a verification code has been sent. Check your inbox and spam folder.';
header('Location: ../forgot-password.php');
exit;
