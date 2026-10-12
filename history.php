<?php
session_start();
require_once "config/database.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

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
    <div class="topbar-brand"><img src="assets/1logo.png" alt="NextStop - BGC"></div>
    <div class="topbar-user"><span><?php echo htmlspecialchars($_SESSION['username']); ?></span><a href="logout.php">Log out</a></div>
</header>
<div class="dashboard">
    <aside class="sidebar">
        <nav class="main-nav" aria-label="Main navigation">
            <a class="nav-item" href="dashboard.php">⌁ <span>Routes &amp; Map</span></a>
            <a class="nav-item" href="bus-stops.php">⌖ <span>Bus Stops</span></a>
            <a class="nav-item" href="feedback.php">◌ <span>Feedback</span></a>
            <a class="nav-item active" href="history.php">↶ <span>History</span></a>
            <?php if ($isAdmin): ?>
                <a class="nav-item admin-nav-item" href="admin/dashboard.php">⚙ <span>Admin Panel</span></a>
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
