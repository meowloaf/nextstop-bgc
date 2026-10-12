<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/report.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('Access denied');
}

$range = $_GET['range'] ?? '30';
$csv = exportAdminReportCsv($pdo, $range);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="nextstop-bgc-report-' . date('Y-m-d') . '.csv"');
header('Pragma: no-cache');
header('Expires: 0');

echo $csv;
exit;