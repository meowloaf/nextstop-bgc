<?php
session_start();
require_once "../config/database.php";

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'You must be logged in.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST requests only.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$startStop = trim($data['start_stop'] ?? '');
$destinationStop = trim($data['destination_stop'] ?? '');

if ($startStop === '' || $destinationStop === '' || $startStop === $destinationStop) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'A valid start and destination are required.']);
    exit;
}

try {
    $stmt = $pdo->prepare(
        "INSERT INTO ride_history (user_id, start_stop, destination_stop, status, started_at, completed_at)
         VALUES (?, ?, ?, 'completed', NOW(), NOW())"
    );
    $stmt->execute([$_SESSION['user_id'], $startStop, $destinationStop]);

    echo json_encode(['success' => true]);
} catch (PDOException $exception) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Ride history table is not available.']);
}
