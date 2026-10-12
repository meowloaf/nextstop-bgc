<?php
session_start();
require_once __DIR__ . '/config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$isAdmin = $_SESSION['role'] === 'admin';
$stops = [];

try {
    $stmt = $pdo->query("SELECT id, name, area, is_active FROM bus_stops ORDER BY id ASC");
    $stops = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $exception) {
    $stops = [
        ['name' => 'SM North EDSA', 'area' => 'Quezon City', 'number' => 1, 'is_active' => 1],
        ['name' => 'North Avenue / EDSA', 'area' => 'Quezon City', 'number' => 2, 'is_active' => 1],
        ['name' => 'Ayala MRT / EDSA', 'area' => 'Makati', 'number' => 3, 'is_active' => 1],
        ['name' => 'Guadalupe', 'area' => 'Makati', 'number' => 4, 'is_active' => 1],
        ['name' => 'Market! Market!', 'area' => 'BGC', 'number' => 5, 'is_active' => 1],
        ['name' => 'High Street', 'area' => 'BGC', 'number' => 6, 'is_active' => 1],
        ['name' => 'The Fort', 'area' => 'BGC', 'number' => 7, 'is_active' => 1],
        ['name' => 'Globe Tower', 'area' => 'BGC', 'number' => 8, 'is_active' => 1],
        ['name' => '32nd Street', 'area' => 'BGC', 'number' => 9, 'is_active' => 1],
        ['name' => 'Venice Grand Canal', 'area' => 'McKinley Hill', 'number' => 10, 'is_active' => 1]
    ];
}
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
            <a class="nav-item active" href="bus-stops.php">⌖ <span>Bus Stops</span></a>
            <a class="nav-item" href="feedback.php">◌ <span>Feedback</span></a>
            <a class="nav-item" href="history.php">↶ <span>History</span></a>
            <?php if ($isAdmin): ?>
                <a class="nav-item admin-nav-item" href="admin/dashboard.php">⚙ <span>Admin Panel</span></a>
            <?php endif; ?>
        </nav>
    </aside>
    <main class="content simple-page">
        <div class="page-heading">
            <div><h1>Bus Stops</h1><p>Stops currently available on the BGC route.</p></div>
        </div>
        <section class="simple-card stop-list" aria-label="Bus stop list">
            <?php foreach ($stops as $stop): ?>
                <div class="stop-row <?php echo ((int) ($stop['is_active'] ?? 1) === 1) ? '' : 'stop-row--inactive'; ?>">
                    <span class="stop-number"><?php echo (int) ($stop['number'] ?? $stop['id']); ?></span>
                    <div><strong><?php echo htmlspecialchars($stop['name']); ?></strong><small><?php echo htmlspecialchars($stop['area']); ?></small></div>
                    <span class="stop-state"><?php echo ((int) ($stop['is_active'] ?? 1) === 1) ? 'ACTIVE' : 'UNAVAILABLE'; ?></span>
                </div>
            <?php endforeach; ?>
        </section>
    </main>
</div>
</body>
</html>
