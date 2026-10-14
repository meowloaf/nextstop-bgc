<?php
session_start();
require_once __DIR__ . '/config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

require_once __DIR__ . '/includes/profile-editor.php';
[$profile, $profileMessage, $profileError, $profileFormValues] = handleProfileUpdate($pdo, (int) $_SESSION['user_id']);

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
            <a class="nav-item" href="dashboard.php"><span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10.5 12 3l9 7.5"/><path d="M5.5 9v11h13V9M9 20v-6h6v6"/></svg></span><span>Routes &amp; Map</span></a>
            <a class="nav-item active" href="bus-stops.php"><span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="3" width="16" height="16" rx="3"/><path d="M4 12h16M7 7h.01M17 7h.01M8 22l2-3m6 3-2-3"/></svg></span><span>Bus Stops</span></a>
            <a class="nav-item" href="feedback.php"><span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 11.5a7.5 7.5 0 0 1-7.5 7.5H6l-3 2v-6.5A7.5 7.5 0 1 1 20 11.5Z"/><path d="M8 11h8m-8 3h5"/></svg></span><span>Feedback</span></a>
            <a class="nav-item" href="history.php"><span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2M4 5v4h4"/></svg></span><span>History</span></a>
            <?php if ($isAdmin): ?>
                <a class="nav-item admin-nav-item" href="admin/dashboard.php"><span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 8.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7Z"/><path d="m19.4 15 .1.1 1.4 1.1-1.5 2.6-1.7-.7a8 8 0 0 1-1.6.9l-.3 1.8h-3l-.3-1.8a8 8 0 0 1-1.6-.9l-1.7.7-1.5-2.6 1.4-1.1a7 7 0 0 1 0-1.9l-1.4-1.1 1.5-2.6 1.7.7a8 8 0 0 1 1.6-.9l.3-1.8h3l.3 1.8a8 8 0 0 1 1.6.9l1.7-.7 1.5 2.6-1.4 1.1a7 7 0 0 1-.1 1.8Z"/></svg></span><span>Admin Panel</span></a>
            <?php endif; ?>
        </nav>
    </aside>
    <main class="content simple-page">
        <div class="page-heading">
            <div><h1>Bus Stops</h1><p>Stops across the BGC, Makati, and Arca South routes.</p></div>
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
