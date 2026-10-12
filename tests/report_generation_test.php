<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../admin/report.php';

$range = '30';
$data = buildAdminReportSummary($pdo, $range);

if (!is_array($data) || !isset($data['generated_at'])) {
    throw new RuntimeException('Report summary is not valid.');
}

if (!isset($data['totals']['rides'], $data['totals']['users'], $data['totals']['active_stops'], $data['totals']['average_rating'])) {
    throw new RuntimeException('Report totals missing required fields.');
}

if (!isset($data['trends']) || !is_array($data['trends'])) {
    throw new RuntimeException('Report trends missing.');
}

if (!isset($data['alerts']) || !is_array($data['alerts'])) {
    throw new RuntimeException('Report alerts missing.');
}

$notificationColumns = $pdo->query('SHOW COLUMNS FROM road_notifications')->fetchAll(PDO::FETCH_COLUMN);
foreach (['affected_area', 'latitude', 'longitude', 'starts_at', 'ends_at'] as $column) {
    if (!in_array($column, $notificationColumns, true)) {
        throw new RuntimeException('Event notification column missing: ' . $column);
    }
}

$routeIds = $pdo->query('SELECT route_id FROM route_operations')->fetchAll(PDO::FETCH_COLUMN);
foreach (['sm-north-bgc', 'ayala-bgc', 'guadalupe-bgc', 'bgc-loop', 'venice-bgc'] as $routeId) {
    if (!in_array($routeId, $routeIds, true)) {
        throw new RuntimeException('Route operation seed missing: ' . $routeId);
    }
}

echo 'report_generation_ok|' . $data['totals']['rides'] . '|' . $data['totals']['users'];
