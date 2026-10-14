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
                <span class="auth-eyebrow">Welcome!</span>
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
                <svg class="google-mark" aria-hidden="true" viewBox="0 0 48 48">
                    <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
                    <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.9c-.58 2.96-2.26 5.48-4.76 7.18l7.73 6C44.38 37.98 46.98 31.85 46.98 24.55z"/>
                    <path fill="#FBBC05" d="M10.53 28.59A14.4 14.4 0 0 1 9.75 24c0-1.59.27-3.13.76-4.59l-7.98-6.19A23.9 23.9 0 0 0 0 24c0 3.87.93 7.53 2.56 10.78l7.97-6.19z"/>
                    <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.9-5.8l-7.73-6c-2.15 1.45-4.9 2.3-8.17 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
                </svg>
                Continue with Google
            </a>

            <div class="auth-divider"><span>or sign in with your account</span></div>

            <form action="auth/login.php" method="POST">
                <label for="login-identifier">Email or username</label>
                <input type="text" id="login-identifier" name="identifier" placeholder="Email or username" autocomplete="username" required>

                <label for="password">Password</label>
                <div class="password-field">
                    <input type="password" id="password" name="password" placeholder="Password" autocomplete="current-password" required>
                    <button class="password-toggle" type="button" aria-label="Show password" aria-pressed="false" aria-controls="password">
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/>
                            <circle cx="12" cy="12" r="3"/>
                            <path class="eye-slash" d="m4 4 16 16"/>
                        </svg>
                    </button>
                </div>

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
<script src="assets/js/password-toggle.js"></script>
</body>
</html>