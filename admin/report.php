<?php

function buildAdminReportSummary(PDO $pdo, string $range = '30'): array
{
    $safeRange = in_array($range, ['7', '30', '90'], true) ? $range : '30';
    $days = (int) $safeRange;
    $startDate = date('Y-m-d 00:00:00', strtotime("-$days days"));

    $feedbackStmt = $pdo->prepare(
        "SELECT COUNT(*) AS total_feedback, COALESCE(AVG(rating), 0) AS avg_rating
         FROM feedbacks
         WHERE created_at >= ?"
    );
    $feedbackStmt->execute([$startDate]);
    $feedback = $feedbackStmt->fetch(PDO::FETCH_ASSOC) ?: ['total_feedback' => 0, 'avg_rating' => 0];

    $rideStmt = $pdo->prepare(
        "SELECT COUNT(*) AS total_rides,
                COALESCE(SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END), 0) AS completed_rides,
                COALESCE(SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END), 0) AS cancelled_rides
         FROM ride_history
         WHERE started_at >= ? OR completed_at >= ?"
    );
    $rideStmt->execute([$startDate, $startDate]);
    $rideSummary = $rideStmt->fetch(PDO::FETCH_ASSOC) ?: ['total_rides' => 0, 'completed_rides' => 0, 'cancelled_rides' => 0];

    $userStmt = $pdo->prepare(
        "SELECT COUNT(*) AS total_users FROM users WHERE created_at >= ?"
    );
    $userStmt->execute([$startDate]);
    $userSummary = $userStmt->fetch(PDO::FETCH_ASSOC) ?: ['total_users' => 0];

    $activeStopStmt = $pdo->prepare(
        "SELECT COUNT(*) AS active_stops FROM bus_stops WHERE is_active = 1"
    );
    $activeStopStmt->execute();
    $activeStops = $activeStopStmt->fetch(PDO::FETCH_ASSOC) ?: ['active_stops' => 0];

    $alertStmt = $pdo->prepare(
        "SELECT title, severity, message, created_at
                 FROM road_notifications
                 WHERE is_active = 1 AND created_at >= ?
                     AND (starts_at IS NULL OR starts_at <= NOW())
                     AND (ends_at IS NULL OR ends_at >= NOW())
         ORDER BY created_at DESC LIMIT 5"
    );
    $alertStmt->execute([$startDate]);
    $alerts = $alertStmt->fetchAll(PDO::FETCH_ASSOC);

    $trendStmt = $pdo->prepare(
        "SELECT DATE(COALESCE(completed_at, started_at)) AS day, COUNT(*) AS rides
         FROM ride_history
         WHERE completed_at >= ? OR started_at >= ?
         GROUP BY DATE(COALESCE(completed_at, started_at))
         ORDER BY day ASC"
    );
    $trendStmt->execute([$startDate, $startDate]);
    $trends = $trendStmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'generated_at' => date('Y-m-d H:i:s'),
        'range_days' => $days,
        'totals' => [
            'rides' => (int) ($rideSummary['total_rides'] ?? 0),
            'completed_rides' => (int) ($rideSummary['completed_rides'] ?? 0),
            'cancelled_rides' => (int) ($rideSummary['cancelled_rides'] ?? 0),
            'users' => (int) ($userSummary['total_users'] ?? 0),
            'active_stops' => (int) ($activeStops['active_stops'] ?? 0),
            'feedback_count' => (int) ($feedback['total_feedback'] ?? 0),
            'average_rating' => round((float) ($feedback['avg_rating'] ?? 0), 1),
        ],
        'trends' => $trends,
        'alerts' => $alerts,
    ];
}

function exportAdminReportCsv(PDO $pdo, string $range = '30'): string
{
    $summary = buildAdminReportSummary($pdo, $range);

    $lines = [];
    $lines[] = 'Generated At,' . $summary['generated_at'];
    $lines[] = 'Range Days,' . $summary['range_days'];
    $lines[] = '';
    $lines[] = 'Metric,Value';
    $lines[] = 'Total Rides,' . $summary['totals']['rides'];
    $lines[] = 'Completed Rides,' . $summary['totals']['completed_rides'];
    $lines[] = 'Cancelled Rides,' . $summary['totals']['cancelled_rides'];
    $lines[] = 'New Users,' . $summary['totals']['users'];
    $lines[] = 'Active Stops,' . $summary['totals']['active_stops'];
    $lines[] = 'Feedback Count,' . $summary['totals']['feedback_count'];
    $lines[] = 'Average Rating,' . $summary['totals']['average_rating'];
    $lines[] = '';
    $lines[] = 'Date,Rides';

    foreach ($summary['trends'] as $trend) {
        $lines[] = $trend['day'] . ',' . (int) $trend['rides'];
    }

    return implode(PHP_EOL, $lines);
}