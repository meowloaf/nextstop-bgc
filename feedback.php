<?php
session_start();
require_once "config/database.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$message = $_SESSION['feedback_message'] ?? '';
$error = $_SESSION['feedback_error'] ?? '';
unset($_SESSION['feedback_message'], $_SESSION['feedback_error']);
$communityFeedback = [];
$availableRides = [];
$userId = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit-feedback') {
    $rating = (int) ($_POST['rating'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');
    $rideId = filter_input(INPUT_POST, 'ride_id', FILTER_VALIDATE_INT);

    if (!$rideId || $rideId < 1) {
        $error = 'Please select a completed ride to review.';
    } elseif ($comment === '') {
        $error = 'Please add a comment before sending feedback.';
    } elseif ($rating < 1 || $rating > 5) {
        $error = 'Please choose a rating from 1 to 5.';
    }

    if ($error === '') {
        try {
            $rideStmt = $pdo->prepare(
                "SELECT id
                 FROM ride_history
                 WHERE id = ? AND user_id = ? AND status = 'completed'
                   AND NOT EXISTS (SELECT 1 FROM feedbacks WHERE ride_id = ride_history.id)"
            );
            $rideStmt->execute([$rideId, $userId]);
            if (!$rideStmt->fetchColumn()) {
                $error = 'That completed ride is unavailable or already has feedback. Please select another ride.';
            }
        } catch (PDOException $exception) {
            $error = 'Could not verify your completed ride. Please try again.';
        }
    }

    if ($error === '') {
        try {
            $stmt = $pdo->prepare("INSERT INTO feedbacks (user_id, ride_id, rating, comment) VALUES (?, ?, ?, ?)");
            $stmt->execute([$userId, $rideId, $rating, $comment]);
            $_SESSION['feedback_message'] = 'Thank you. Your feedback has been saved.';
            header('Location: feedback.php');
            exit;
        } catch (PDOException $exception) {
            $_SESSION['feedback_error'] = 'Could not save feedback for that ride. It may already have a review.';
            header('Location: feedback.php');
            exit;
        }
    }

    if ($error !== '') {
        $_SESSION['feedback_error'] = $error;
        header('Location: feedback.php');
        exit;
    }
}

$isAdmin = $_SESSION['role'] === 'admin';
require_once __DIR__ . '/includes/profile-editor.php';
[$profile, $profileMessage, $profileError, $profileFormValues] = handleProfileUpdate($pdo, (int) $_SESSION['user_id']);

try {
    $rideStmt = $pdo->prepare(
        "SELECT ride_history.id, ride_history.start_stop, ride_history.destination_stop, ride_history.completed_at, ride_history.started_at
         FROM ride_history
         WHERE ride_history.user_id = ? AND ride_history.status = 'completed'
           AND NOT EXISTS (SELECT 1 FROM feedbacks WHERE feedbacks.ride_id = ride_history.id)
         ORDER BY COALESCE(ride_history.completed_at, ride_history.started_at) DESC"
    );
    $rideStmt->execute([$userId]);
    $availableRides = $rideStmt->fetchAll(PDO::FETCH_ASSOC);

    $feedbackStmt = $pdo->query(
        "SELECT feedbacks.rating, feedbacks.comment, feedbacks.created_at, users.username,
                ride_history.start_stop, ride_history.destination_stop
         FROM feedbacks
         LEFT JOIN users ON users.id = feedbacks.user_id
         LEFT JOIN ride_history ON ride_history.id = feedbacks.ride_id
         ORDER BY feedbacks.created_at DESC LIMIT 12"
    );
    $communityFeedback = $feedbackStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $exception) {
    $communityFeedback = [];
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
            <a class="nav-item" href="dashboard.php"><span class="nav-icon" aria-hidden="true">▦</span><span>Routes &amp; Map</span></a>
            <a class="nav-item" href="bus-stops.php"><span class="nav-icon" aria-hidden="true">●</span><span>Bus Stops</span></a>
            <a class="nav-item active" href="feedback.php"><span class="nav-icon" aria-hidden="true">✉</span><span>Feedback</span></a>
            <a class="nav-item" href="history.php"><span class="nav-icon" aria-hidden="true">↺</span><span>History</span></a>
            <?php if ($isAdmin): ?>
                <a class="nav-item admin-nav-item" href="admin/dashboard.php"><span class="nav-icon" aria-hidden="true">⚙</span><span>Admin Panel</span></a>
            <?php endif; ?>
        </nav>
    </aside>
    <main class="content feedback-page">
        <div class="page-heading">
            <div><h1>Feedback</h1><p>Help us improve your bus experience.</p></div>
        </div>
        <div class="feedback-layout">
            <section class="simple-card feedback-card">
                <?php if ($message !== ''): ?><p class="form-success"><?php echo htmlspecialchars($message); ?></p><?php endif; ?>
                <?php if ($error !== ''): ?><p class="form-error"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>
                <form method="POST">
                    <input type="hidden" name="action" value="submit-feedback">
                    <label for="feedback-ride">Completed ride <b>*</b></label>
                    <select id="feedback-ride" name="ride_id" required <?php echo $availableRides ? '' : 'disabled'; ?>>
                        <option value="">Select a completed ride</option>
                        <?php foreach ($availableRides as $ride): ?>
                            <option value="<?php echo (int) $ride['id']; ?>">
                                <?php echo htmlspecialchars($ride['start_stop'] . ' → ' . $ride['destination_stop'] . ' · ' . ($ride['completed_at'] ?: $ride['started_at'])); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!$availableRides): ?>
                        <p class="feedback-ride-empty">No completed rides are available for feedback yet.</p>
                    <?php endif; ?>
                    <fieldset class="star-rating-field">
                        <legend>How was your trip? <b>*</b></legend>
                        <div class="star-rating" aria-label="Choose a rating from 1 to 5 stars">
                            <?php for ($star = 5; $star >= 1; $star--): ?>
                                <input id="rating-<?php echo $star; ?>" type="radio" name="rating" value="<?php echo $star; ?>" required>
                                <label for="rating-<?php echo $star; ?>" title="<?php echo $star; ?> stars">★</label>
                            <?php endfor; ?>
                        </div>
                    </fieldset>
                    <label for="comment">Comments</label>
                    <textarea id="comment" name="comment" rows="6" placeholder="Tell us about your experience..." required></textarea>
                    <button class="find-button" type="submit" <?php echo $availableRides ? '' : 'disabled'; ?>>Submit Feedback</button>
                </form>
            </section>
            <section class="simple-card community-card">
                <div class="community-heading">
                    <div><h2>Community Feedbacks</h2><p>See what other riders are saying.</p></div>
                    <button class="refresh-button" type="button" onclick="window.location.reload()">↻ Refresh</button>
                </div>
                <div class="feedback-grid">
                    <?php if (!$communityFeedback): ?>
                        <div class="empty-state"><strong>No feedback yet</strong><span>Be the first rider to share your experience.</span></div>
                    <?php else: ?>
                        <?php foreach ($communityFeedback as $feedback): ?>
                            <article class="feedback-item" data-rating="<?php echo (int) $feedback['rating']; ?>">
                                <div class="feedback-item-heading"><span class="feedback-avatar">▰</span><div><strong>Recent Feedback:</strong><small>User “<?php echo htmlspecialchars($feedback['username'] ?? 'Rider'); ?>” · <?php echo htmlspecialchars($feedback['created_at'] ?? ''); ?></small></div></div>
                                <?php if (!empty($feedback['start_stop']) && !empty($feedback['destination_stop'])): ?>
                                    <small class="feedback-ride"><?php echo htmlspecialchars($feedback['start_stop'] . ' → ' . $feedback['destination_stop']); ?></small>
                                <?php endif; ?>
                                <div class="feedback-rating" aria-label="<?php echo (int) $feedback['rating']; ?> out of 5 stars"><?php echo str_repeat('★', (int) $feedback['rating']); ?><span><?php echo str_repeat('★', 5 - (int) $feedback['rating']); ?></span></div>
                                <p><?php echo htmlspecialchars($feedback['comment']); ?></p>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </main>
</div>
</body>
</html>
