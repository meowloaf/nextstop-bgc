<?php
session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = $_SESSION['register_error'] ?? '';
unset($_SESSION['register_error']);
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
                <span class="auth-eyebrow">New to NextStop?</span>
                <span class="secure-note">Free to join</span>
            </div>
            <h2>Start your journey.</h2>
            <p class="auth-description">Create one account for every BGC commute.</p>

            <?php if ($error !== ''): ?>
                <p class="form-error"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>

            <a class="social-button" href="auth/google-login.php">
                <span class="google-mark" aria-hidden="true">G</span>
                Sign up with Google
            </a>

            <button class="otp-button" type="button">
                <span aria-hidden="true">&#9742;</span>
                Sign up with SMS OTP
            </button>

            <div class="auth-divider"><span>or create with username</span></div>

            <form action="auth/register.php" method="POST" enctype="multipart/form-data">
                <label for="register-email">Email</label>
                <input type="email" id="register-email" name="email" placeholder="you@example.com">

                <label for="register-username">Username</label>
                <input type="text" id="register-username" name="username" placeholder="Username" minlength="3" maxlength="50" required>

                <label for="register-password">Password</label>
                <input type="password" id="register-password" name="password" placeholder="Password" minlength="6" required>

                <label for="confirm-password">Confirm password</label>
                <input type="password" id="confirm-password" name="confirm_password" placeholder="Confirm password" minlength="6" required>

                <label for="fare-type">Fare type</label>
                <select id="fare-type" name="fare_type">
                    <option value="">Select fare type (optional)</option>
                    <option value="student">Student</option>
                    <option value="senior">Senior Citizen</option>
                    <option value="pwd">PWD</option>
                </select>

                <label for="eligibility-proof">Eligibility proof</label>
                <input type="file" id="eligibility-proof" name="eligibility_proof" accept="image/jpeg,image/png,application/pdf">

                <button type="submit">Create account</button>
            </form>

            <p class="auth-link">
                Already have an account?
                <a href="index.php">Back to login</a>
            </p>
        </div>
    </section>
</main>
</body>
</html>
