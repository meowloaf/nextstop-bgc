<?php
session_start();
require_once "config/database.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

require_once __DIR__ . '/includes/profile-editor.php';
[$profile, $profileMessage, $profileError, $profileFormValues] = handleProfileUpdate($pdo, (int) $_SESSION['user_id']);

$rides = [];
try {
    $stmt = $pdo->prepare("SELECT start_stop, destination_stop, status, started_at FROM ride_history WHERE user_id = ? ORDER BY started_at DESC");
    $stmt->execute([$_SESSION['user_id']]);
    $rides = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $exception) {
    $rides = [];
}

$isAdmin = $_SESSION['role'] === 'admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>NextStop - BGC</title>
<link rel="icon" href="assets/2logo.png" type="image/png">
<link rel="stylesheet" href="assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/assets/css/style.css'); ?>">
</head>
<body>
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
            <a class="nav-item" href="dashboard.php"><span class="nav-icon" aria-hidden="true">▦</span><span>Routes &amp; Map</span></a>
            <a class="nav-item" href="bus-stops.php"><span class="nav-icon" aria-hidden="true">●</span><span>Bus Stops</span></a>
            <a class="nav-item" href="feedback.php"><span class="nav-icon" aria-hidden="true">✉</span><span>Feedback</span></a>
            <a class="nav-item active" href="history.php"><span class="nav-icon" aria-hidden="true">↺</span><span>History</span></a>
            <?php if ($isAdmin): ?>
                <a class="nav-item admin-nav-item" href="admin/dashboard.php"><span class="nav-icon" aria-hidden="true">⚙</span><span>Admin Panel</span></a>
            <?php endif; ?>
        </nav>
    </aside>
    <main class="content simple-page">
        <div class="page-heading">
            <div><h1>Ride History</h1><p>Your completed trips will appear here.</p></div>
        </div>
        <section class="simple-card history-list">
            <?php if (!$rides): ?>
                <div class="empty-state"><strong>No rides yet</strong><span>Your future trips will be recorded here.</span></div>
            <?php else: ?>
                <?php foreach ($rides as $ride): ?>
                    <div class="history-row">
                        <div><strong><?php echo htmlspecialchars($ride['start_stop']); ?> → <?php echo htmlspecialchars($ride['destination_stop']); ?></strong><small><?php echo htmlspecialchars($ride['started_at'] ?? ''); ?></small></div>
                        <span class="route-badge"><?php echo htmlspecialchars(strtoupper($ride['status'])); ?></span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    </main>
</div>
</body>
</html>
