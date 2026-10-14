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

require_once __DIR__ . '/../includes/profile-editor.php';
[$adminProfile, $profileMessage, $profileError, $profileFormValues] = handleProfileUpdate($pdo, (int) $_SESSION['user_id']);

$feedbackRows = [];
$stopRows = [];
$rideCount = 0;
$userCount = 0;
$notifications = [];
$routeBuses = [];
$routeOperations = [];
$adminMessage = $_SESSION['admin_message'] ?? '';
unset($_SESSION['admin_message']);
$reportRange = $_GET['report_range'] ?? '30';
$reportSummary = buildAdminReportSummary($pdo, $reportRange);

try {
    $feedbackStmt = $pdo->query(
        "SELECT f.id, f.rating, f.comment, f.created_at, u.username,
                ride_history.start_stop, ride_history.destination_stop
         FROM feedbacks f
         JOIN users u ON u.id = f.user_id
         LEFT JOIN ride_history ON ride_history.id = f.ride_id
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

    $routeBuses = $pdo->query(
        "SELECT id, route_id, plate_number, is_online, is_default FROM route_buses ORDER BY id ASC"
    )->fetchAll(PDO::FETCH_ASSOC);
    $routeOperations = $pdo->query(
        "SELECT route_id, is_online FROM route_operations"
    )->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (PDOException $exception) {
    $feedbackRows = [];
    $stopRows = [];
    $rideCount = 0;
    $userCount = 0;
    $notifications = [];
    $routeBuses = [];
    $routeOperations = [];
}

$busesByRoute = [];
foreach ($routeBuses as $bus) {
    $busesByRoute[$bus['route_id']][] = $bus;
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
    <div class="topbar-brand topbar-brand--wordmark" aria-label="Next Stop - BGC">
        <span class="topbar-wordmark"><span>NEXT</span><span>STOP</span><small><span>-</span> <span class="topbar-wordmark-bgc">BGC</span></small></span>
    </div>
    <div class="topbar-user">
        <?php renderProfileEditor($adminProfile, '../', $profileMessage, $profileError, $profileFormValues); ?>
        <a href="../logout.php">Log out</a>
    </div>
</header>
<div class="dashboard">
    <aside class="sidebar">
        <nav class="main-nav" aria-label="Main navigation">
            <a class="nav-item" href="../dashboard.php"><span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10.5 12 3l9 7.5"/><path d="M5.5 9v11h13V9M9 20v-6h6v6"/></svg></span><span>Routes &amp; Map</span></a>
            <a class="nav-item" href="../bus-stops.php"><span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="3" width="16" height="16" rx="3"/><path d="M4 12h16M7 7h.01M17 7h.01M8 22l2-3m6 3-2-3"/></svg></span><span>Bus Stops</span></a>
            <a class="nav-item" href="../feedback.php"><span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 11.5a7.5 7.5 0 0 1-7.5 7.5H6l-3 2v-6.5A7.5 7.5 0 1 1 20 11.5Z"/><path d="M8 11h8m-8 3h5"/></svg></span><span>Feedback</span></a>
            <a class="nav-item" href="../history.php"><span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2M4 5v4h4"/></svg></span><span>History</span></a>
            <a class="nav-item admin-nav-item" href="dashboard.php"><span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 8.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7Z"/><path d="m19.4 15 .1.1 1.4 1.1-1.5 2.6-1.7-.7a8 8 0 0 1-1.6.9l-.3 1.8h-3l-.3-1.8a8 8 0 0 1-1.6-.9l-1.7.7-1.5-2.6 1.4-1.1a7 7 0 0 1 0-1.9l-1.4-1.1 1.5-2.6 1.7.7a8 8 0 0 1 1.6-.9l.3-1.8h3l.3 1.8a8 8 0 0 1 1.6.9l1.7-.7 1.5 2.6-1.4 1.1a7 7 0 0 1-.1 1.8Z"/></svg></span><span>Admin Panel</span></a>
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
                                    <th>Ride</th>
                                    <th>Rating</th>
                                    <th>Comment</th>
                                    <th>Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($feedbackRows as $row): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['username']); ?></td>
                                        <td><?php echo htmlspecialchars(!empty($row['start_stop']) && !empty($row['destination_stop']) ? $row['start_stop'] . ' → ' . $row['destination_stop'] : 'Ride unavailable'); ?></td>
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
                    <?php $routeBusList = $busesByRoute[$routeId] ?? []; ?>
                    <?php $routeIsOnline = (int) ($routeOperations[$routeId] ?? 1) === 1; ?>
                    <div class="admin-toggle">
                        <div>
                            <strong><?php echo htmlspecialchars($route['name']); ?></strong>
                            <small><?php echo htmlspecialchars($route['code']); ?> · <?php echo count($routeBusList); ?> buses · <?php echo $routeIsOnline ? 'Route online' : 'Route offline'; ?></small>
                        </div>
                        <div class="route-bus-list">
                            <?php foreach ($routeBusList as $busIndex => $bus): ?>
                                <?php $busIsOnline = (int) $bus['is_online'] === 1; ?>
                                <?php $busIsAvailable = $busIsOnline && $routeIsOnline; ?>
                                <div class="route-bus">
                                    <span><strong>Bus #<?php echo $busIndex + 1; ?></strong><?php echo (int) $bus['is_default'] === 1 ? ' · Default' : ''; ?> · <?php echo htmlspecialchars($bus['plate_number']); ?></span>
                                    <div class="route-bus-actions">
                                        <span class="admin-status <?php echo $busIsAvailable ? 'active' : 'inactive'; ?>"><?php echo !$routeIsOnline ? 'ROUTE OFFLINE' : ($busIsOnline ? 'ONLINE' : 'OFFLINE'); ?></span>
                                        <form method="POST" action="manage-bus.php">
                                            <input type="hidden" name="action" value="<?php echo $busIsOnline ? 'offline' : 'deploy'; ?>">
                                            <input type="hidden" name="bus_id" value="<?php echo (int) $bus['id']; ?>">
                                            <input type="hidden" name="route_id" value="<?php echo htmlspecialchars($routeId); ?>">
                                            <button class="route-bus-status-button <?php echo $busIsOnline ? 'admin-toggle-button--inactive' : ''; ?>" type="submit"><?php echo $busIsOnline ? 'Take offline' : 'Deploy'; ?></button>
                                        </form>
                                        <?php if ((int) $bus['is_default'] !== 1): ?>
                                            <form method="POST" action="manage-bus.php">
                                                <input type="hidden" name="action" value="remove">
                                                <input type="hidden" name="bus_id" value="<?php echo (int) $bus['id']; ?>">
                                                <input type="hidden" name="route_id" value="<?php echo htmlspecialchars($routeId); ?>">
                                                <button class="route-bus-remove-button" type="submit" aria-label="Remove bus <?php echo htmlspecialchars($bus['plate_number']); ?> from <?php echo htmlspecialchars($route['name']); ?>">Remove</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <form class="route-bus-add" method="POST" action="manage-bus.php">
                                <input type="hidden" name="action" value="add">
                                <input type="hidden" name="route_id" value="<?php echo htmlspecialchars($routeId); ?>">
                                <label class="sr-only" for="plate-<?php echo htmlspecialchars($routeId); ?>">Bus plate number for <?php echo htmlspecialchars($route['name']); ?></label>
                                <input id="plate-<?php echo htmlspecialchars($routeId); ?>" name="plate_number" type="text" maxlength="20" pattern="[A-Za-z0-9 -]{2,20}" placeholder="Bus plate number" required>
                                <button type="submit">Add bus</button>
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
<script>
const adminScrollStorageKey = "admin-dashboard-scroll-position";

document.querySelectorAll('form[method="POST"]').forEach(form => {
    form.addEventListener("submit", () => {
        sessionStorage.setItem(adminScrollStorageKey, JSON.stringify({
            x: window.scrollX,
            y: window.scrollY
        }));
    });
});

window.addEventListener("pageshow", () => {
    const savedPosition = sessionStorage.getItem(adminScrollStorageKey);
    if (!savedPosition) {
        return;
    }

    sessionStorage.removeItem(adminScrollStorageKey);
    const position = JSON.parse(savedPosition);
    if (Number.isFinite(position.x) && Number.isFinite(position.y)) {
        requestAnimationFrame(() => window.scrollTo(position.x, position.y));
    }
});
</script>
</body>
</html>
