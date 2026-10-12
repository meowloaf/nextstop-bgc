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

$title = trim($_POST['title'] ?? '');
$message = trim($_POST['message'] ?? '');
$severity = $_POST['severity'] ?? 'warning';
$affectedArea = trim($_POST['affected_area'] ?? '');
$latitude = is_numeric($_POST['latitude'] ?? null) ? (float) $_POST['latitude'] : null;
$longitude = is_numeric($_POST['longitude'] ?? null) ? (float) $_POST['longitude'] : null;
$startsAt = trim($_POST['starts_at'] ?? '');
$endsAt = trim($_POST['ends_at'] ?? '');

if ($title === '' || $message === '') {
    $_SESSION['admin_message'] = 'Title and notification details are required.';
    header('Location: dashboard.php');
    exit;
}

if (!in_array($severity, ['info', 'warning', 'critical'], true)) {
    $severity = 'warning';
}

if ($latitude !== null && ($latitude < -90 || $latitude > 90)) {
    $latitude = null;
}
if ($longitude !== null && ($longitude < -180 || $longitude > 180)) {
    $longitude = null;
}

$startDateTime = $startsAt !== '' ? date_create($startsAt) : null;
$endDateTime = $endsAt !== '' ? date_create($endsAt) : null;
if ($startDateTime && $endDateTime && $endDateTime <= $startDateTime) {
    $_SESSION['admin_message'] = 'The event end time must be after the start time.';
    header('Location: dashboard.php');
    exit;
}
$startsAt = $startDateTime ? $startDateTime->format('Y-m-d H:i:s') : null;
$endsAt = $endDateTime ? $endDateTime->format('Y-m-d H:i:s') : null;

try {
    $stmt = $pdo->prepare(
           "INSERT INTO road_notifications (title, message, severity, affected_area, latitude, longitude, starts_at, ends_at, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)"
    );
        $stmt->execute([$title, $message, $severity, $affectedArea !== '' ? $affectedArea : null, $latitude, $longitude, $startsAt, $endsAt]);
    $_SESSION['admin_message'] = 'Alert published to riders.';
} catch (PDOException $exception) {
    $_SESSION['admin_message'] = 'Alert could not be saved right now.';
}

header('Location: dashboard.php');
exit;
