<?php
session_start();
require_once "config/database.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

require_once __DIR__ . '/includes/profile-editor.php';
[$profile, $profileMessage, $profileError, $profileFormValues] = handleProfileUpdate($pdo, (int) $_SESSION['user_id']);
$isAdmin = $_SESSION['role'] === 'admin';

$stopRows = [];
$alerts = [];
$routeOperations = [];
$routeBuses = [];

try {
    $stopStmt = $pdo->query("SELECT name, is_active FROM bus_stops ORDER BY name ASC");
    $stopRows = $stopStmt->fetchAll(PDO::FETCH_ASSOC);

    $alertStmt = $pdo->query("SELECT title, message, severity, affected_area, latitude, longitude, starts_at, ends_at FROM road_notifications WHERE is_active = 1 AND (starts_at IS NULL OR starts_at <= NOW()) AND (ends_at IS NULL OR ends_at >= NOW()) ORDER BY created_at DESC LIMIT 3");
    $alerts = $alertStmt->fetchAll(PDO::FETCH_ASSOC);

    $routeOperations = $pdo->query("SELECT route_id, is_online FROM route_operations")->fetchAll(PDO::FETCH_KEY_PAIR);
    $routeBuses = $pdo->query("SELECT id, route_id, plate_number, is_online, is_default FROM route_buses ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $exception) {
    $stopRows = [];
    $alerts = [];
    $routeOperations = [];
    $routeBuses = [];
}

$activeStops = array_values(array_filter(array_map(static fn($stop) => ((int) $stop['is_active'] === 1) ? $stop['name'] : null, $stopRows)));
$busNumbersByRoute = [];
foreach ($routeBuses as &$bus) {
    $routeId = $bus['route_id'];
    $busNumbersByRoute[$routeId] = ($busNumbersByRoute[$routeId] ?? 0) + 1;
    $bus['bus_number'] = $busNumbersByRoute[$routeId];
}
unset($bus);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>NextStop - BGC</title>
<link rel="icon" href="assets/2logo.png" type="image/png">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<link rel="stylesheet" href="assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/assets/css/style.css'); ?>">
</head>
<body data-role="<?php echo $isAdmin ? 'admin' : 'user'; ?>">
<header class="topbar">
    <div class="topbar-brand topbar-brand--wordmark" aria-label="Next Stop - BGC">
        <span class="topbar-wordmark"><span>NEXT</span><span>STOP</span><small><span>-</span> <span class="topbar-wordmark-bgc">BGC</span></small></span>
    </div>

    <div class="topbar-user">
        <?php renderProfileEditor($profile, '', $profileMessage, $profileError, $profileFormValues); ?>
        <a href="logout.php">Log out</a>
    </div>
</header>

<div class="dashboard">
    <aside class="sidebar">
        <nav class="main-nav" aria-label="Main navigation">
            <a class="nav-item active" href="dashboard.php"><span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10.5 12 3l9 7.5"/><path d="M5.5 9v11h13V9M9 20v-6h6v6"/></svg></span><span>Routes &amp; Map</span></a>
            <a class="nav-item" href="bus-stops.php"><span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="3" width="16" height="16" rx="3"/><path d="M4 12h16M7 7h.01M17 7h.01M8 22l2-3m6 3-2-3"/></svg></span><span>Bus Stops</span></a>
            <a class="nav-item" href="feedback.php"><span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 11.5a7.5 7.5 0 0 1-7.5 7.5H6l-3 2v-6.5A7.5 7.5 0 1 1 20 11.5Z"/><path d="M8 11h8m-8 3h5"/></svg></span><span>Feedback</span></a>
            <a class="nav-item" href="history.php"><span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2M4 5v4h4"/></svg></span><span>History</span></a>
            <?php if ($isAdmin): ?>
                <a class="nav-item admin-nav-item" href="admin/dashboard.php"><span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 8.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7Z"/><path d="m19.4 15 .1.1 1.4 1.1-1.5 2.6-1.7-.7a8 8 0 0 1-1.6.9l-.3 1.8h-3l-.3-1.8a8 8 0 0 1-1.6-.9l-1.7.7-1.5-2.6 1.4-1.1a7 7 0 0 1 0-1.9l-1.4-1.1 1.5-2.6 1.7.7a8 8 0 0 1 1.6-.9l.3-1.8h3l.3 1.8a8 8 0 0 1 1.6.9l1.7-.7 1.5 2.6-1.4 1.1a7 7 0 0 1-.1 1.8Z"/></svg></span><span>Admin Panel</span></a>
            <?php endif; ?>
        </nav>

        <section class="live-status" aria-label="Live bus status">
            <p>LIVE BUS STATUS</p>
            <div id="live-status-list"></div>
        </section>

    </aside>

    <main class="content">
        <div class="page-heading">
            <div>
                <h1>Route &amp; Map</h1>
                <p>Find a route and track buses in real time.</p>
            </div>

        </div>

        <div class="route-map-layout">
            <section class="route-panel" aria-label="Find a bus route">
                <div class="route-search">
                    <label class="location-field from-field">
                        <span>●</span>
                        <input id="from-location" type="text" placeholder="Your location" list="stop-suggestions" aria-label="Starting location">
                    </label>
                    <button class="location-button" type="button" onclick="useMyLocation()">Use my location</button>
                    <label class="location-field to-field">
                        <span>●</span>
                        <input id="to-location" type="text" placeholder="Where do you want to go?" list="stop-suggestions" aria-label="Destination">
                    </label>
                    <datalist id="stop-suggestions"></datalist>
                    <button class="find-button" id="find-routes-button" type="button" onclick="findRoutes()">Find Routes</button>
                </div>

                <?php if ($alerts): ?>
                    <section class="system-alerts" aria-label="Active road notices" aria-live="polite">
                        <h2 class="system-alerts-heading">ROAD NOTICES</h2>
                        <?php foreach ($alerts as $alert): ?>
                            <div class="system-alert system-alert--<?php echo htmlspecialchars($alert['severity'] ?? 'warning'); ?>" role="alert">
                                <strong><?php echo htmlspecialchars($alert['title']); ?></strong>
                                <span><?php echo htmlspecialchars($alert['message']); ?></span>
                                <?php if (!empty($alert['affected_area'])): ?>
                                    <span class="system-alert-area">Affected area: <?php echo htmlspecialchars($alert['affected_area']); ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </section>
                <?php endif; ?>

                <p id="trip-status" class="trip-status" role="status">Choose a starting point and destination.</p>

                <section id="fare-summary" class="fare-summary" aria-live="polite" hidden>
                    <div class="fare-summary-heading">
                        <span>ESTIMATED FARE</span>
                        <strong id="fare-total"></strong>
                    </div>
                    <p id="fare-breakdown"></p>
                    <small id="fare-note"></small>
                </section>

                <div id="trip-details" class="trip-details" hidden>
                    <div class="trip-detail-item"><span>BUS ETA</span><strong id="bus-eta">--</strong></div>
                    <div class="trip-detail-item"><span>NEAREST STOP</span><strong id="nearest-stop">--</strong></div>
                    <section id="transfer-guidance" class="transfer-guidance" aria-live="polite" hidden></section>
                    <section id="transfer-alternatives" class="transfer-alternatives" aria-label="Other nearby transfer options" hidden></section>
                    <div class="passing-stops"><span>STOPS ALONG THE WAY</span><ol id="passing-stop-list"></ol></div>
                    <button id="ride-action-button" class="ride-button" type="button" onclick="handleRideAction()">Bus is here!</button>
                </div>

                <div class="route-list-heading">
                    <span>ALL ROUTES</span>
                    <span id="route-count"></span>
                </div>
                <div id="route-list" class="route-list"></div>
            </section>

            <section class="map-panel" aria-label="Bus route map">
                <div id="map"></div>
            </section>
        </div>
    </main>
</div>

<script>
window.nextStopState = {
    activeStops: <?php echo json_encode($activeStops, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>,
    alerts: <?php echo json_encode($alerts, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>,
    routeOperations: <?php echo json_encode($routeOperations, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>,
    routeBuses: <?php echo json_encode($routeBuses, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>,
    fareType: <?php echo json_encode($profile['fare_type'] ?? '', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>
};
</script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="assets/js/bus-simulator.js?v=<?php echo filemtime(__DIR__ . '/assets/js/bus-simulator.js'); ?>"></script>
</body>
</html>