<?php
session_start();
require_once __DIR__ . '/../config/database.php';

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

$email = $_SESSION['password_reset_email'] ?? '';
$code = trim(is_string($_POST['code'] ?? null) ? $_POST['code'] : '');
$password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
$confirmPassword = is_string($_POST['confirm_password'] ?? null) ? $_POST['confirm_password'] : '';
$validationError = '';

if (!is_string($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['password_reset_error'] = 'Request a verification code first.';
    header('Location: ../forgot-password.php');
    exit;
}

if (!preg_match('/^[0-9]{6}$/', $code)) {
    $validationError = 'Enter the 6-digit verification code.';
} elseif (strlen($password) < 6) {
    $validationError = 'Password must be at least 6 characters.';
} elseif ($password !== $confirmPassword) {
    $validationError = 'Passwords do not match.';
}

if ($validationError !== '') {
    $_SESSION['password_reset_error'] = $validationError;
    header('Location: ../forgot-password.php');
    exit;
}

try {
    $pdo->beginTransaction();
    $resetStmt = $pdo->prepare(
        'SELECT reset.id, reset.user_id, reset.code_hash, reset.expires_at, reset.attempts
         FROM password_reset_codes AS reset
         JOIN users ON users.id = reset.user_id
         WHERE users.email = ?
         LIMIT 1
         FOR UPDATE'
    );
    $resetStmt->execute([$email]);
    $reset = $resetStmt->fetch(PDO::FETCH_ASSOC);

    if (!$reset) {
        $pdo->commit();
        $_SESSION['password_reset_error'] = 'That code is invalid or expired. Request a new code and try again.';
    } elseif ((int) $reset['attempts'] >= 5) {
        $pdo->commit();
        $_SESSION['password_reset_error'] = 'Too many code attempts. Request a new verification code.';
    } elseif (strtotime($reset['expires_at']) <= time()) {
        $deleteExpired = $pdo->prepare('DELETE FROM password_reset_codes WHERE id = ?');
        $deleteExpired->execute([(int) $reset['id']]);
        $pdo->commit();
        $_SESSION['password_reset_error'] = 'That code has expired. Request a new verification code.';
    } elseif (!password_verify($code, $reset['code_hash'])) {
        $updateAttempts = $pdo->prepare('UPDATE password_reset_codes SET attempts = attempts + 1 WHERE id = ?');
        $updateAttempts->execute([(int) $reset['id']]);
        $pdo->commit();
        $_SESSION['password_reset_error'] = 'That code is invalid or expired. Check it and try again.';
    } else {
        $updatePassword = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
        $updatePassword->execute([password_hash($password, PASSWORD_DEFAULT), (int) $reset['user_id']]);
        $deleteCode = $pdo->prepare('DELETE FROM password_reset_codes WHERE id = ?');
        $deleteCode->execute([(int) $reset['id']]);
        $pdo->commit();

        unset($_SESSION['password_reset_email'], $_SESSION['password_reset_csrf']);
        $_SESSION['login_success'] = 'Password updated. You can now sign in.';
        header('Location: ../index.php');
        exit;
    }
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Password reset verification failed: ' . $exception->getMessage());
    $_SESSION['password_reset_error'] = 'Your password could not be updated right now. Please try again.';
}

header('Location: ../forgot-password.php');
exit;
