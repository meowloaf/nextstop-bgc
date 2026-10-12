<?php
session_start();
require_once '../config/database.php';
require_once '../config/google.php';

if (!isset($_GET['code'], $_GET['state']) || !hash_equals($_SESSION['google_oauth_state'] ?? '', $_GET['state'])) {
    die('Invalid Google login request.');
}
unset($_SESSION['google_oauth_state']);

try {
    $google = googleConfig();
} catch (RuntimeException $exception) {
    die($exception->getMessage());
}

$tokenRequest = curl_init('https://oauth2.googleapis.com/token');
curl_setopt_array($tokenRequest, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        'code' => $_GET['code'],
        'client_id' => $google['client_id'],
        'client_secret' => $google['client_secret'],
        'redirect_uri' => $google['redirect_uri'],
        'grant_type' => 'authorization_code',
    ]),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
]);
$tokenResponse = curl_exec($tokenRequest);
$tokenCurlError = curl_error($tokenRequest);
curl_close($tokenRequest);
$token = json_decode($tokenResponse ?: '', true);

if (empty($token['access_token'])) {
    $details = $token['error_description'] ?? $token['error'] ?? $tokenCurlError;
    die('Google login could not be completed.' . ($details ? ' ' . htmlspecialchars($details, ENT_QUOTES, 'UTF-8') : ''));
}

$profileRequest = curl_init('https://www.googleapis.com/oauth2/v3/userinfo');
curl_setopt_array($profileRequest, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token['access_token']],
]);
$profileResponse = curl_exec($profileRequest);
curl_close($profileRequest);
$profile = json_decode($profileResponse ?: '', true);

if (empty($profile['sub']) || empty($profile['email']) || empty($profile['email_verified'])) {
    die('Google did not return a verified email address.');
}

$stmt = $pdo->prepare('SELECT * FROM users WHERE google_id = ? OR email = ? LIMIT 1');
$stmt->execute([$profile['sub'], $profile['email']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    $baseUsername = preg_replace('/[^A-Za-z0-9_]/', '_', strstr($profile['email'], '@', true));
    $baseUsername = trim(substr($baseUsername ?: 'google_user', 0, 42), '_') ?: 'google_user';
    $username = $baseUsername;
    $suffix = 1;

    while (true) {
        $usernameCheck = $pdo->prepare('SELECT id FROM users WHERE username = ?');
        $usernameCheck->execute([$username]);
        if (!$usernameCheck->fetch()) {
            break;
        }
        $username = substr($baseUsername, 0, 42) . '_' . $suffix++;
    }

    $stmt = $pdo->prepare('INSERT INTO users (username, password, email, google_id, role) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$username, password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT), $profile['email'], $profile['sub'], 'user']);
    $user = [
        'id' => $pdo->lastInsertId(),
        'username' => $username,
        'role' => 'user',
    ];
} else {
    $stmt = $pdo->prepare('UPDATE users SET email = ?, google_id = ? WHERE id = ?');
    $stmt->execute([$profile['email'], $profile['sub'], $user['id']]);
}

session_regenerate_id(true);
$_SESSION['user_id'] = $user['id'];
$_SESSION['username'] = $user['username'];
$_SESSION['role'] = $user['role'];
header('Location: ../dashboard.php');
exit;
