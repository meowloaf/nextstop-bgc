<?php
session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$success = $_SESSION['register_success'] ?? '';
$error = $_SESSION['login_error'] ?? '';
unset($_SESSION['register_success'], $_SESSION['login_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>NextStop - BGC</title>
<link rel="icon" href="assets/2logo.png" type="image/png">
<link rel="stylesheet" href="assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/assets/css/style.css'); ?>">
</head>
<body class="login-page">
<main class="auth-shell auth-redesign">
    <section class="auth-visual" aria-label="NextStop BGC">
        <div class="auth-visual-content">
            <h1>Every stop is closer than it looks.</h1>
            <p>Plan the ride ahead and move through BGC with less waiting and more certainty.</p>
        </div>
        <span class="visual-caption">BONIFACIO GLOBAL CITY <b>///</b> 2026</span>
    </section>

    <section class="auth-panel">
        <div class="auth-content">
            <div class="auth-panel-heading">
                <span class="auth-eyebrow">Welcome back</span>
                <span class="secure-note">Secure access</span>
            </div>
            <h2>Good to see you.</h2>
            <p class="auth-description">Sign in to see your routes, stops, and ride history.</p>

            <?php if ($success !== ''): ?>
                <p class="form-success"><?php echo htmlspecialchars($success); ?></p>
            <?php endif; ?>
            <?php if ($error !== ''): ?>
                <p class="form-error"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>

            <a class="social-button" href="auth/google-login.php">
                <span class="google-mark" aria-hidden="true">G</span>
                Continue with Google
            </a>

            <button class="otp-button" type="button">
                <span aria-hidden="true">&#9742;</span>
                Use SMS OTP
            </button>

            <div class="auth-divider"><span>or use your username</span></div>

            <form action="auth/login.php" method="POST">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" placeholder="Username" required>

                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Password" required>

                <div class="auth-options">
                    <label><input type="checkbox"> Keep me signed in</label>
                    <a href="#">Forgot password?</a>
                </div>

                <button type="submit">Sign in</button>
            </form>

            <p class="auth-link">
                No account yet?
                <a href="register.php">Register here</a>
            </p>
        </div>
    </section>
</main>
</body>
</html>