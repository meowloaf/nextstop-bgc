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

$alertId = filter_input(INPUT_POST, 'alert_id', FILTER_VALIDATE_INT);
$action = $_POST['action'] ?? '';

if (!$alertId || !in_array($action, ['activate', 'deactivate'], true)) {
    $_SESSION['admin_message'] = 'That alert action was not valid.';
    header('Location: dashboard.php');
    exit;
}

try {
    $stmt = $pdo->prepare('UPDATE road_notifications SET is_active = ? WHERE id = ?');
    $stmt->execute([$action === 'activate' ? 1 : 0, $alertId]);
    $_SESSION['admin_message'] = $action === 'activate' ? 'Alert activated for riders.' : 'Alert marked as completed and hidden from riders.';
} catch (PDOException $exception) {
    $_SESSION['admin_message'] = 'Alert status could not be updated right now.';
}

header('Location: dashboard.php');
exit;