<?php
session_start();
$message = $_SESSION['password_reset_message'] ?? '';
$error = $_SESSION['password_reset_error'] ?? '';
unset($_SESSION['password_reset_message'], $_SESSION['password_reset_error']);
$resetEmail = $_SESSION['password_reset_email'] ?? '';
if (!isset($_SESSION['password_reset_csrf'])) {
    $_SESSION['password_reset_csrf'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reset password | NextStop - BGC</title>
<link rel="icon" href="assets/2logo.png" type="image/png">
<link rel="stylesheet" href="assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/assets/css/style.css'); ?>">
</head>
<body class="login-page">
<main class="auth-shell auth-redesign">
    <section class="auth-visual" aria-label="NextStop BGC">
        <div class="auth-visual-content">
            <h1>Back on track.</h1>
            <p>Verify your email and choose a new password for your NextStop account.</p>
        </div>
        <span class="visual-caption">BONIFACIO GLOBAL CITY <b>///</b> 2026</span>
    </section>
    <section class="auth-panel">
        <div class="auth-content">
            <div class="auth-panel-heading">
                <span class="auth-eyebrow">Account recovery</span>
                <span class="secure-note">Email verification</span>
            </div>
            <h2>Reset password.</h2>
            <p class="auth-description">We’ll email a one-time code to the address registered with your account.</p>

            <?php if ($message !== ''): ?><p class="form-success" role="status"><?php echo htmlspecialchars($message); ?></p><?php endif; ?>
            <?php if ($error !== ''): ?><p class="form-error" role="alert"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>

            <form action="auth/request-password-reset.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['password_reset_csrf']); ?>">
                <label for="reset-email">Email address</label>
                <input type="email" id="reset-email" name="email" value="<?php echo htmlspecialchars($resetEmail); ?>" autocomplete="email" maxlength="255" required>
                <button type="submit">Send verification code</button>
            </form>

            <?php if ($resetEmail !== ''): ?>
                <div class="auth-divider"><span>enter the code from your email</span></div>
                <form action="auth/verify-password-reset.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['password_reset_csrf']); ?>">
                    <label for="reset-code">6-digit code</label>
                    <input type="text" id="reset-code" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required>

                    <label for="new-password">New password</label>
                    <input type="password" id="new-password" name="password" minlength="6" autocomplete="new-password" required>

                    <label for="confirm-password">Confirm new password</label>
                    <input type="password" id="confirm-password" name="confirm_password" minlength="6" autocomplete="new-password" required>
                    <button type="submit">Set new password</button>
                </form>
            <?php endif; ?>

            <p class="auth-link"><a href="index.php">Back to login</a></p>
        </div>
    </section>
</main>
</body>
</html>
