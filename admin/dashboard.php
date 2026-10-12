<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/report.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../dashboard.php');
    exit;
}

$feedbackRows = [];
$stopRows = [];
$rideCount = 0;
$userCount = 0;
$notifications = [];
$routeOperations = [];
$adminMessage = $_SESSION['admin_message'] ?? '';
unset($_SESSION['admin_message']);
$reportRange = $_GET['report_range'] ?? '30';
$reportSummary = buildAdminReportSummary($pdo, $reportRange);

try {
    $feedbackStmt = $pdo->query(
        "SELECT f.id, f.rating, f.comment, f.created_at, u.username
         FROM feedbacks f
         JOIN users u ON u.id = f.user_id
         ORDER BY f.created_at DESC LIMIT 8"
    );
    $feedbackRows = $feedbackStmt->fetchAll(PDO::FETCH_ASSOC);

    $stopStmt = $pdo->query(
        "SELECT id, name, area, is_active
         FROM bus_stops
         ORDER BY name ASC"
    );
    $stopRows = $stopStmt->fetchAll(PDO::FETCH_ASSOC);

    $rideCount = (int) $pdo->query("SELECT COUNT(*) FROM ride_history")->fetchColumn();
    $userCount = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

    $notificationStmt = $pdo->query(
        "SELECT id, title, message, severity, affected_area, starts_at, ends_at, is_active
         FROM road_notifications
         ORDER BY created_at DESC LIMIT 8"
    );
    $notifications = $notificationStmt->fetchAll(PDO::FETCH_ASSOC);

    $routeOperations = $pdo->query(
        "SELECT route_id, is_online FROM route_operations ORDER BY route_id ASC"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $exception) {
    $feedbackRows = [];
    $stopRows = [];
    $rideCount = 0;
    $userCount = 0;
    $notifications = [];
    $routeOperations = [];
}

$routeLabels = [
    'east-express' => ['name' => 'East Express', 'code' => 'EX'],
    'north-route' => ['name' => 'North Route', 'code' => 'NR'],
    'west-route' => ['name' => 'West Route', 'code' => 'WR'],
    'arca-south-express' => ['name' => 'Arca South Express', 'code' => 'AS'],
    'central-route' => ['name' => 'Central Route', 'code' => 'CR'],
    'night-route' => ['name' => 'Night Route', 'code' => 'NT'],
    'lrt-ayala-route' => ['name' => 'LRT - Ayala Route', 'code' => 'LRT'],
];

$averageRating = 0;
if ($feedbackRows) {
    $averageRating = array_sum(array_map(fn($row) => (int) $row['rating'], $feedbackRows)) / count($feedbackRows);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard | NextStop - BGC</title>
<link rel="icon" href="../assets/2logo.png" type="image/png">
<link rel="stylesheet" href="../assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/style.css'); ?>">
</head>
<body>
<header class="topbar">
    <div class="topbar-brand">
        <img src="../assets/1logo.png" alt="NextStop - BGC">
    </div>
    <div class="topbar-user">
        <span><?php echo htmlspecialchars($_SESSION['username']); ?></span>
        <a href="../logout.php">Log out</a>
    </div>
</header>
<div class="dashboard">
    <aside class="sidebar">
        <nav class="main-nav" aria-label="Main navigation">
            <a class="nav-item" href="../dashboard.php">⌁ <span>Routes &amp; Map</span></a>
            <a class="nav-item" href="../bus-stops.php">⌖ <span>Bus Stops</span></a>
            <a class="nav-item" href="../feedback.php">◌ <span>Feedback</span></a>
            <a class="nav-item" href="../history.php">↶ <span>History</span></a>
            <a class="nav-item" href="dashboard.php">⚙ <span>Admin Panel</span></a>
        </nav>
    </aside>

    <main class="content simple-page admin-shell">
        

        <section class="admin-hero" aria-label="Operations overview">
            <div>
                <span class="admin-eyebrow">Operate the BGC transport network and monitor rider experience.</span>
                <h2>Admin Dashboard</h2>
            </div>
        </section>

        <?php if ($adminMessage !== ''): ?>
            <p class="form-success admin-message" aria-live="polite"><?php echo htmlspecialchars($adminMessage); ?></p>
        <?php endif; ?>

        <section class="admin-grid" aria-label="Admin summary">
        </section>

        <section class="admin-panel admin-panel--wide">
                <div class="admin-panel-header">
                    <h3>Recent feedback</h3>
                    <span class="admin-chip">Last 8</span>
                </div>
                <?php if (!$feedbackRows): ?>
                    <div class="empty-state"><strong>No feedback yet</strong><span>Rider comments will appear here.</span></div>
                <?php else: ?>
                    <div class="admin-table-wrap">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Rating</th>
                                    <th>Comment</th>
                                    <th>Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($feedbackRows as $row): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['username']); ?></td>
                                        <td><?php echo htmlspecialchars((int) $row['rating']); ?>/5</td>
                                        <td><?php echo htmlspecialchars($row['comment']); ?></td>
                                        <td><?php echo htmlspecialchars(date('M d, Y', strtotime($row['created_at']))); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
        </section>

        <section class="admin-panel admin-panel--wide">
            <div class="admin-panel-header">
                <h3>Road warning command</h3>
                <span class="admin-chip admin-chip--alert">Alerts</span>
            </div>
            <form class="alert-form" method="POST" action="save-alert.php">
                <label for="alert-title">Title</label>
                <input id="alert-title" name="title" type="text" maxlength="120" placeholder="Road closure / event / hazard" required>

                <label for="alert-message">Notification</label>
                <textarea id="alert-message" name="message" rows="4" placeholder="Share the impact, detour, and expected recovery time." required></textarea>

                <label for="alert-area">Affected area</label>
                <input id="alert-area" name="affected_area" type="text" maxlength="120" placeholder="Example: BGC or Makati">

                <div class="alert-coordinate-grid">
                    <div>
                        <label for="alert-latitude">Event latitude</label>
                        <input id="alert-latitude" name="latitude" type="number" step="any" min="-90" max="90" placeholder="14.5508">
                    </div>
                    <div>
                        <label for="alert-longitude">Event longitude</label>
                        <input id="alert-longitude" name="longitude" type="number" step="any" min="-180" max="180" placeholder="121.0542">
                    </div>
                </div>

                <small class="form-hint">Add coordinates to pin the event on the rider map. The area name marks related routes.</small>

                <div class="alert-coordinate-grid">
                    <div>
                        <label for="alert-starts-at">Starts</label>
                        <input id="alert-starts-at" name="starts_at" type="datetime-local">
                    </div>
                    <div>
                        <label for="alert-ends-at">Ends</label>
                        <input id="alert-ends-at" name="ends_at" type="datetime-local">
                    </div>
                </div>
                <small class="form-hint">Leave either time empty for an open-ended alert. Events disappear automatically after the end time.</small>

                <label for="alert-severity">Severity</label>
                <select id="alert-severity" name="severity">
                    <option value="info">Informational</option>
                    <option value="warning" selected>Warning</option>
                    <option value="critical">Critical</option>
                </select>

                <button class="find-button" type="submit">Publish alert</button>
            </form>
        </section>

        <section class="admin-panel admin-panel--wide">
            <div class="admin-panel-header">
                <h3>Route management</h3>
                <span class="admin-chip">Service</span>
            </div>
            <?php if (!$stopRows): ?>
                <div class="empty-state"><strong>No stops configured</strong><span>Load the route map database to add stops.</span></div>
            <?php else: ?>
                <div class="admin-forms">
                    <?php foreach ($stopRows as $stop): ?>
                        <div class="admin-toggle">
                            <div>
                                <strong><?php echo htmlspecialchars($stop['name']); ?></strong>
                                <small><?php echo htmlspecialchars($stop['area']); ?></small>
                            </div>
                            <form method="POST" action="manage-stop.php">
                                <input type="hidden" name="stop_id" value="<?php echo (int) $stop['id']; ?>">
                                <input type="hidden" name="action" value="<?php echo (int) $stop['is_active'] ? 'deactivate' : 'activate'; ?>">
                                <button class="<?php echo (int) $stop['is_active'] ? '' : 'admin-toggle-button--inactive'; ?>" type="submit"><?php echo (int) $stop['is_active'] ? 'Deactivate' : 'Activate'; ?></button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="admin-panel admin-panel--wide">
            <div class="admin-panel-header">
                <h3>Bus deployment</h3>
                <span class="admin-chip admin-chip--success">Fleet</span>
            </div>
            <div class="admin-forms">
                <?php foreach ($routeLabels as $routeId => $route): ?>
                    <?php
                    $isOnline = true;
                    foreach ($routeOperations as $operation) {
                        if ($operation['route_id'] === $routeId) {
                            $isOnline = (int) $operation['is_online'] === 1;
                            break;
                        }
                    }
                    ?>
                    <div class="admin-toggle">
                        <div>
                            <strong><?php echo htmlspecialchars($route['name']); ?></strong>
                            <small><?php echo htmlspecialchars($route['code']); ?> · <?php echo $isOnline ? 'Buses deployed' : 'Route offline'; ?></small>
                        </div>
                        <div class="admin-alert-actions">
                            <span class="admin-status <?php echo $isOnline ? 'active' : 'inactive'; ?>"><?php echo $isOnline ? 'ONLINE' : 'OFFLINE'; ?></span>
                            <form method="POST" action="manage-route.php">
                                <input type="hidden" name="route_id" value="<?php echo htmlspecialchars($routeId); ?>">
                                <input type="hidden" name="action" value="<?php echo $isOnline ? 'offline' : 'deploy'; ?>">
                                <button class="<?php echo $isOnline ? 'admin-toggle-button--inactive' : ''; ?>" type="submit"><?php echo $isOnline ? 'Take offline' : 'Deploy buses'; ?></button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="admin-panel admin-panel--wide">
            <div class="admin-panel-header">
                <h3>Latest alerts</h3>
                <span class="admin-chip admin-chip--alert">Live</span>
            </div>
            <?php if (!$notifications): ?>
                <div class="empty-state"><strong>No active alerts</strong><span>Broadcast a route message to riders here.</span></div>
            <?php else: ?>
                <div class="admin-forms">
                    <?php foreach ($notifications as $notification): ?>
                        <div class="admin-toggle">
                            <div>
                                <strong><?php echo htmlspecialchars($notification['title']); ?></strong>
                                <small>
                                    <?php echo htmlspecialchars($notification['message']); ?>
                                    <?php if ($notification['starts_at'] || $notification['ends_at']): ?>
                                        <br>Schedule: <?php echo $notification['starts_at'] ? htmlspecialchars(date('M d, Y g:i A', strtotime($notification['starts_at']))) : 'Now'; ?>
                                        to <?php echo $notification['ends_at'] ? htmlspecialchars(date('M d, Y g:i A', strtotime($notification['ends_at']))) : 'Until completed'; ?>
                                    <?php endif; ?>
                                </small>
                            </div>
                            <div class="admin-alert-actions">
                                <span class="admin-status <?php echo (int) $notification['is_active'] === 1 ? 'active' : 'inactive'; ?>">
                                    <?php echo (int) $notification['is_active'] === 1 ? 'LIVE' : 'DONE'; ?>
                                </span>
                                <form method="POST" action="manage-alert.php">
                                    <input type="hidden" name="alert_id" value="<?php echo (int) $notification['id']; ?>">
                                    <input type="hidden" name="action" value="<?php echo (int) $notification['is_active'] === 1 ? 'deactivate' : 'activate'; ?>">
                                    <button type="submit"><?php echo (int) $notification['is_active'] === 1 ? 'Mark done' : 'Reactivate'; ?></button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="admin-panel admin-panel--wide">
            <div class="admin-panel-header">
                <h3>Operations report</h3>
                <div class="admin-panel-actions">
                    <form method="GET" action="dashboard.php" class="report-range-form">
                        <label class="sr-only" for="report-range">Report range</label>
                        <select id="report-range" name="report_range" onchange="this.form.submit()">
                            <option value="7" <?php echo $reportRange === '7' ? 'selected' : ''; ?>>Last 7 days</option>
                            <option value="30" <?php echo $reportRange === '30' ? 'selected' : ''; ?>>Last 30 days</option>
                            <option value="90" <?php echo $reportRange === '90' ? 'selected' : ''; ?>>Last 90 days</option>
                        </select>
                    </form>
                    <a class="admin-export" href="report-export.php?range=<?php echo urlencode($reportRange); ?>" target="_blank" rel="noopener">Download CSV</a>
                </div>
            </div>

            <div class="report-summary-grid" aria-label="Operational summary metrics">
                <div class="report-summary-card">
                    <span>Total rides</span>
                    <strong><?php echo (int) $reportSummary['totals']['rides']; ?></strong>
                </div>
                <div class="report-summary-card">
                    <span>Completed</span>
                    <strong><?php echo (int) $reportSummary['totals']['completed_rides']; ?></strong>
                </div>
                <div class="report-summary-card">
                    <span>New users</span>
                    <strong><?php echo (int) $reportSummary['totals']['users']; ?></strong>
                </div>
                <div class="report-summary-card">
                    <span>Active stops</span>
                    <strong><?php echo (int) $reportSummary['totals']['active_stops']; ?></strong>
                </div>
                <div class="report-summary-card">
                    <span>Avg. rating</span>
                    <strong><?php echo number_format((float) $reportSummary['totals']['average_rating'], 1); ?></strong>
                </div>
            </div>

            <div class="report-chart" aria-label="Ride trends chart">
                <?php if (empty($reportSummary['trends'])): ?>
                    <div class="empty-state"><strong>No ride data yet</strong><span>Trips will appear in this report automatically.</span></div>
                <?php else: ?>
                    <?php
                    $maxRideCount = 1;
                    foreach ($reportSummary['trends'] as $trend) {
                        $maxRideCount = max($maxRideCount, (int) $trend['rides']);
                    }
                    ?>
                    <div class="report-bars" role="img" aria-label="Ride volume trend chart">
                        <?php foreach ($reportSummary['trends'] as $trend): ?>
                            <?php $barHeight = $maxRideCount > 0 ? max(8, ((int) $trend['rides'] / $maxRideCount) * 100) : 8; ?>
                            <div class="report-bar-group" title="<?php echo htmlspecialchars($trend['day']); ?>: <?php echo (int) $trend['rides']; ?> rides">
                                <span class="report-bar" style="height: <?php echo $barHeight; ?>%"></span>
                                <small><?php echo htmlspecialchars(date('M d', strtotime($trend['day']))); ?></small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="report-alert-list">
                <h4>Recent alerts in this period</h4>
                <?php if (empty($reportSummary['alerts'])): ?>
                    <div class="empty-state compact-empty"><strong>No alerts published</strong><span>New warnings will appear here.</span></div>
                <?php else: ?>
                    <ul>
                        <?php foreach ($reportSummary['alerts'] as $alert): ?>
                            <li>
                                <strong><?php echo htmlspecialchars($alert['title']); ?></strong>
                                <span class="report-alert-severity <?php echo htmlspecialchars($alert['severity']); ?>"><?php echo htmlspecialchars(strtoupper($alert['severity'])); ?></span>
                                <p><?php echo htmlspecialchars($alert['message']); ?></p>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </section>
    </main>
</div>
</body>
</html>
