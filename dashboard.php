<?php
session_start();
require_once "config/database.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$profileMessage = '';
$profileError = '';
$profileDirectory = __DIR__ . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'profiles';
$profileUrl = 'assets/uploads/profiles/';

try {
    $pdo->exec("ALTER TABLE users ADD COLUMN emergency_contact VARCHAR(100) NULL AFTER role");
} catch (PDOException $exception) {
}
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN emergency_contact_name VARCHAR(100) NULL AFTER role");
} catch (PDOException $exception) {
}
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN emergency_contact_address VARCHAR(255) NULL AFTER emergency_contact");
} catch (PDOException $exception) {
}
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN profile_photo VARCHAR(255) NULL AFTER emergency_contact");
} catch (PDOException $exception) {
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update-profile') {
    $profileName = trim($_POST['profile_name'] ?? '');
    $emergencyContactName = trim($_POST['emergency_contact_name'] ?? '');
    $emergencyContact = trim($_POST['emergency_contact'] ?? '');
    $emergencyContactAddress = trim($_POST['emergency_contact_address'] ?? '');
    $profilePhoto = null;

    if ($profileName === '' || !preg_match('/^[A-Za-z0-9 _.-]{3,50}$/', $profileName)) {
        $profileError = 'Use a name between 3 and 50 characters.';
    } elseif ($emergencyContactName !== '' && !preg_match('/^[A-Za-z .\'-]{2,100}$/', $emergencyContactName)) {
        $profileError = 'Enter a valid emergency contact name.';
    } elseif ($emergencyContact !== '' && !preg_match('/^[0-9+() .-]{7,30}$/', $emergencyContact)) {
        $profileError = 'Enter a valid emergency contact number.';
    } elseif (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] !== UPLOAD_ERR_NO_FILE) {
        $photo = $_FILES['profile_photo'];
        $allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $imageInfo = $photo['error'] === UPLOAD_ERR_OK ? @getimagesize($photo['tmp_name']) : false;
        $extension = $imageInfo ? ($allowedTypes[$imageInfo['mime']] ?? null) : null;

        if ($photo['error'] !== UPLOAD_ERR_OK || !$extension || $photo['size'] > 3 * 1024 * 1024) {
            $profileError = 'Upload a JPG, PNG, or WEBP image smaller than 3 MB.';
        } elseif (!is_dir($profileDirectory) && !mkdir($profileDirectory, 0755, true)) {
            $profileError = 'The profile photo folder could not be created.';
        } else {
            $fileName = bin2hex(random_bytes(16)) . '.' . $extension;
            if (move_uploaded_file($photo['tmp_name'], $profileDirectory . $fileName)) {
                $profilePhoto = $profileUrl . $fileName;
            } else {
                $profileError = 'The profile photo could not be saved.';
            }
        }
    }

    if ($profileError === '') {
        try {
            $update = $pdo->prepare("UPDATE users SET username = ?, emergency_contact_name = ?, emergency_contact = ?, emergency_contact_address = ?, profile_photo = COALESCE(?, profile_photo) WHERE id = ?");
            $update->execute([$profileName, $emergencyContactName ?: null, $emergencyContact ?: null, $emergencyContactAddress ?: null, $profilePhoto, $_SESSION['user_id']]);
            $_SESSION['username'] = $profileName;
            $profileMessage = 'Profile updated successfully.';
        } catch (PDOException $exception) {
            $profileError = 'That name may already be in use.';
        }
    }
}

$profileStmt = $pdo->prepare("SELECT username, role, fare_type, emergency_contact_name, emergency_contact, emergency_contact_address, profile_photo FROM users WHERE id = ?");
$profileStmt->execute([$_SESSION['user_id']]);
$profile = $profileStmt->fetch(PDO::FETCH_ASSOC) ?: [
    'username' => $_SESSION['username'], 'role' => $_SESSION['role'], 'fare_type' => '', 'emergency_contact_name' => '', 'emergency_contact' => '', 'emergency_contact_address' => '', 'profile_photo' => ''
];
$accountLabel = !empty($profile['fare_type']) ? ucfirst(str_replace('_', ' ', $profile['fare_type'])) : ucfirst($profile['role']);
$isAdmin = $_SESSION['role'] === 'admin';

$stopRows = [];
$alerts = [];
$routeOperations = [];

try {
    $stopStmt = $pdo->query("SELECT name, is_active FROM bus_stops ORDER BY name ASC");
    $stopRows = $stopStmt->fetchAll(PDO::FETCH_ASSOC);

    $alertStmt = $pdo->query("SELECT title, message, severity, affected_area, latitude, longitude, starts_at, ends_at FROM road_notifications WHERE is_active = 1 AND (starts_at IS NULL OR starts_at <= NOW()) AND (ends_at IS NULL OR ends_at >= NOW()) ORDER BY created_at DESC LIMIT 3");
    $alerts = $alertStmt->fetchAll(PDO::FETCH_ASSOC);

    $routeOperations = $pdo->query("SELECT route_id, is_online FROM route_operations")->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (PDOException $exception) {
    $stopRows = [];
    $alerts = [];
    $routeOperations = [];
}

$activeStops = array_values(array_filter(array_map(static fn($stop) => ((int) $stop['is_active'] === 1) ? $stop['name'] : null, $stopRows)));
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
    <div class="topbar-brand">
        <img src="assets/1logo.png" alt="NextStop - BGC">
    </div>

    <div class="topbar-user">
        <span><?php echo htmlspecialchars($profile['username']); ?></span>
        <a href="logout.php">Log out</a>
    </div>
</header>

<?php if ($alerts): ?>
    <div class="system-alerts" aria-live="polite">
        <?php foreach ($alerts as $alert): ?>
            <div class="system-alert system-alert--<?php echo htmlspecialchars($alert['severity'] ?? 'warning'); ?>" role="alert">
                <strong><?php echo htmlspecialchars($alert['title']); ?></strong>
                <span><?php echo htmlspecialchars($alert['message']); ?></span>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="dashboard">
    <aside class="sidebar">
        <nav class="main-nav" aria-label="Main navigation">
            <a class="nav-item active" href="dashboard.php"><span class="nav-icon" aria-hidden="true">▦</span><span>Routes &amp; Map</span></a>
            <a class="nav-item" href="bus-stops.php"><span class="nav-icon" aria-hidden="true">●</span><span>Bus Stops</span></a>
            <a class="nav-item" href="feedback.php"><span class="nav-icon" aria-hidden="true">✉</span><span>Feedback</span></a>
            <a class="nav-item" href="history.php"><span class="nav-icon" aria-hidden="true">↺</span><span>History</span></a>
            <?php if ($isAdmin): ?>
                <a class="nav-item admin-nav-item" href="admin/dashboard.php"><span class="nav-icon" aria-hidden="true">⚙</span><span>Admin Panel</span></a>
            <?php endif; ?>
        </nav>

        <section class="live-status" aria-label="Live bus status">
            <p>LIVE BUS STATUS</p>
            <div id="live-status-list"></div>
        </section>

        <button class="sidebar-user" id="profile-trigger" type="button" aria-haspopup="dialog" aria-controls="profile-dialog">
            <?php if (!empty($profile['profile_photo'])): ?><img class="user-avatar profile-avatar-image" src="<?php echo htmlspecialchars($profile['profile_photo']); ?>" alt="Profile photo">
            <?php else: ?><span class="user-avatar">●</span><?php endif; ?>
            <span class="sidebar-user-text"><?php echo htmlspecialchars($profile['username']); ?><small><?php echo htmlspecialchars($accountLabel); ?></small></span><span class="profile-chevron">›</span>
        </button>
        <div class="profile-dialog" id="profile-dialog" role="dialog" aria-modal="true" aria-labelledby="profile-title" hidden>
            <div class="profile-dialog-panel">
                <button class="profile-close" id="profile-close" type="button" aria-label="Close profile">×</button>
                <h2 id="profile-title">Your Profile</h2>
                <p>Keep your account and emergency contact details up to date.</p>
                <?php if ($profileMessage !== ''): ?><p class="form-success"><?php echo htmlspecialchars($profileMessage); ?></p><?php endif; ?>
                <?php if ($profileError !== ''): ?><p class="form-error"><?php echo htmlspecialchars($profileError); ?></p><?php endif; ?>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="update-profile">
                    <label for="profile-name">Name</label>
                    <input id="profile-name" name="profile_name" value="<?php echo htmlspecialchars($profile['username']); ?>" maxlength="50" required>
                    <label for="profile-photo">Profile picture</label>
                    <input id="profile-photo" name="profile_photo" type="file" accept="image/jpeg,image/png,image/webp">
                    <h3 class="profile-section-title">EMERGENCY CONTACT</h3>
                    <label for="emergency-contact-name">Contact name</label>
                    <input id="emergency-contact-name" name="emergency_contact_name" value="<?php echo htmlspecialchars($profile['emergency_contact_name'] ?? ''); ?>" placeholder="Full name">
                    <label for="emergency-contact">Contact number</label>
                    <input id="emergency-contact" name="emergency_contact" type="tel" value="<?php echo htmlspecialchars($profile['emergency_contact'] ?? ''); ?>" placeholder="09XX XXX XXXX">
                    <label for="emergency-contact-address">Address</label>
                    <input id="emergency-contact-address" name="emergency_contact_address" value="<?php echo htmlspecialchars($profile['emergency_contact_address'] ?? ''); ?>" placeholder="Home address">
                    <button class="find-button" type="submit">Save Profile</button>
                </form>
            </div>
        </div>
    </aside>

    <main class="content">
        <div class="page-heading">
            <div>
                <h1>Route &amp; Map</h1>
                <p>Find a route and track buses in real time.</p>
            </div>

            <?php if ($isAdmin): ?>
            <div class="admin-controls">
                <button type="button" onclick="startSimulation()">Start</button>
                <button type="button" onclick="pauseSimulation()">Pause</button>
                <button type="button" onclick="resetSimulation()">Reset</button>
                <select id="speed" onchange="changeSpeed()" aria-label="Simulation speed">
                    <option value="2000">0.5x</option>
                    <option value="1000" selected>1x</option>
                    <option value="500">2x</option>
                    <option value="200">5x</option>
                </select>
            </div>
            <?php endif; ?>
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
                    <button class="find-button" type="button" onclick="findRoutes()">Find Routes</button>
                </div>

                <p id="trip-status" class="trip-status" role="status">Choose a starting point and destination.</p>

                <div id="trip-details" class="trip-details" hidden>
                    <div class="trip-detail-item"><span>BUS ETA</span><strong id="bus-eta">--</strong></div>
                    <div class="trip-detail-item"><span>NEAREST STOP</span><strong id="nearest-stop">--</strong></div>
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
    routeOperations: <?php echo json_encode($routeOperations, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>
};
</script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="assets/js/bus-simulator.js"></script>
</body>
</html>