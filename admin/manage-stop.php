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

$stopId = (int) ($_POST['stop_id'] ?? 0);
$action = $_POST['action'] ?? '';

if ($stopId <= 0 || !in_array($action, ['activate', 'deactivate'], true)) {
    header('Location: dashboard.php');
    exit;
}

$status = $action === 'activate' ? 1 : 0;

try {
    $stmt = $pdo->prepare('UPDATE bus_stops SET is_active = ? WHERE id = ?');
    $stmt->execute([$status, $stopId]);
} catch (PDOException $exception) {
    $_SESSION['admin_message'] = 'Unable to update the stop status.';
    header('Location: dashboard.php');
    exit;
}

$_SESSION['admin_message'] = $action === 'activate' ? 'Stop activated.' : 'Stop deactivated.';
header('Location: dashboard.php');
exit;
