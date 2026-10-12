<?php
session_start();
require_once '../config/google.php';

try {
    $google = googleConfig();
} catch (RuntimeException $exception) {
    die($exception->getMessage());
}

$state = bin2hex(random_bytes(32));
$_SESSION['google_oauth_state'] = $state;

$query = http_build_query([
    'client_id' => $google['client_id'],
    'redirect_uri' => $google['redirect_uri'],
    'response_type' => 'code',
    'scope' => 'openid email profile',
    'access_type' => 'online',
    'state' => $state,
    'prompt' => 'select_account',
]);

header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . $query);
exit;
