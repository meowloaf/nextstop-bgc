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

if (!in_array($routeId, $validRoutes, true) || !in_array($action, ['add', 'remove', 'deploy', 'offline'], true)) {
    $_SESSION['admin_message'] = 'That bus action was not valid.';
    header('Location: dashboard.php');
    exit;
}

try {
    if ($action === 'add') {
        $plateNumber = strtoupper(trim($_POST['plate_number'] ?? ''));
        $plateNumber = preg_replace('/\s+/', ' ', $plateNumber);

        if (strlen($plateNumber) < 2 || strlen($plateNumber) > 20 || !preg_match('/^[A-Z0-9 -]+$/', $plateNumber)) {
            $_SESSION['admin_message'] = 'Enter a valid bus plate number.';
        } else {
            $stmt = $pdo->prepare('INSERT INTO route_buses (route_id, plate_number) VALUES (?, ?)');
            $stmt->execute([$routeId, $plateNumber]);
            $_SESSION['admin_message'] = 'Bus added to the route.';
        }
    } elseif ($action === 'remove') {
        $busId = filter_input(INPUT_POST, 'bus_id', FILTER_VALIDATE_INT);
        if (!$busId) {
            $_SESSION['admin_message'] = 'That bus could not be removed.';
        } else {
            $busCheck = $pdo->prepare('SELECT is_default FROM route_buses WHERE id = ? AND route_id = ?');
            $busCheck->execute([$busId, $routeId]);
            $isDefaultBus = $busCheck->fetchColumn();
            if ($isDefaultBus === false) {
                $_SESSION['admin_message'] = 'That bus could not be found on this route.';
            } elseif ((int) $isDefaultBus === 1) {
                $_SESSION['admin_message'] = 'The default bus for each route cannot be removed.';
            } else {
                $stmt = $pdo->prepare('DELETE FROM route_buses WHERE id = ? AND route_id = ? AND is_default = 0');
                $stmt->execute([$busId, $routeId]);
                $_SESSION['admin_message'] = $stmt->rowCount()
                    ? 'Bus removed from the route.'
                    : 'That bus could not be removed.';
            }
        }
    } else {
        $busId = filter_input(INPUT_POST, 'bus_id', FILTER_VALIDATE_INT);
        if (!$busId) {
            $_SESSION['admin_message'] = 'That bus status action was not valid.';
        } else {
            $isOnline = $action === 'deploy' ? 1 : 0;
            $stmt = $pdo->prepare('UPDATE route_buses SET is_online = ? WHERE id = ? AND route_id = ?');
            $stmt->execute([$isOnline, $busId, $routeId]);
            $busExists = $stmt->rowCount() > 0;
            if (!$busExists) {
                $check = $pdo->prepare('SELECT id FROM route_buses WHERE id = ? AND route_id = ?');
                $check->execute([$busId, $routeId]);
                $busExists = $check->fetchColumn() !== false;
            }

            if ($busExists) {
                $_SESSION['admin_message'] = $isOnline ? 'Bus deployed.' : 'Bus taken offline.';
            } else {
                $_SESSION['admin_message'] = 'That bus could not be found on this route.';
            }
        }
    }
} catch (PDOException $exception) {
    $_SESSION['admin_message'] = $action === 'add'
        ? 'Bus could not be added. Check that its plate number is not already assigned.'
        : 'Bus status could not be updated right now.';
}

header('Location: dashboard.php');
exit;
