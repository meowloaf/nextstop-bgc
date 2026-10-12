<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

$routeId = trim($_POST['route_id'] ?? '');
$action = $_POST['action'] ?? '';
$validRoutes = ['east-express', 'north-route', 'west-route', 'arca-south-express', 'central-route', 'night-route', 'lrt-ayala-route'];

if (!in_array($routeId, $validRoutes, true) || !in_array($action, ['deploy', 'offline'], true)) {
    $_SESSION['admin_message'] = 'That route action was not valid.';
    header('Location: dashboard.php');
    exit;
}

try {
    $stmt = $pdo->prepare(
        'INSERT INTO route_operations (route_id, is_online) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE is_online = VALUES(is_online)'
    );
    $stmt->execute([$routeId, $action === 'deploy' ? 1 : 0]);
    $_SESSION['admin_message'] = $action === 'deploy' ? 'Route deployed and visible to riders.' : 'Route taken offline.';
} catch (PDOException $exception) {
    $_SESSION['admin_message'] = 'Route status could not be updated right now.';
}

header('Location: dashboard.php');
exit;