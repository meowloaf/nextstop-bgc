<?php
$host = "localhost";
$dbname = "bgc_bus_system";
$username = "root";
$password = "";

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            email VARCHAR(255) NULL UNIQUE,
            google_id VARCHAR(255) NULL UNIQUE,
            role VARCHAR(20) NOT NULL DEFAULT 'user',
            fare_type VARCHAR(20) NULL,
            eligibility_proof VARCHAR(255) NULL,
            emergency_contact_name VARCHAR(100) NULL,
            emergency_contact VARCHAR(100) NULL,
            emergency_contact_address VARCHAR(255) NULL,
            profile_photo VARCHAR(255) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )"
    );

    $userColumns = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
    foreach (['email', 'google_id', 'fare_type', 'eligibility_proof', 'emergency_contact_name', 'emergency_contact', 'emergency_contact_address', 'profile_photo'] as $column) {
        if (!in_array($column, $userColumns, true)) {
            $after = match ($column) {
                'email' => 'password',
                'google_id' => 'email',
                'fare_type' => 'role',
                'eligibility_proof' => 'fare_type',
                'emergency_contact_name' => 'eligibility_proof',
                'emergency_contact' => 'emergency_contact_name',
                'emergency_contact_address' => 'emergency_contact',
                'profile_photo' => 'emergency_contact_address',
                default => 'role'
            };

            if ($column === 'email') {
                $pdo->exec("ALTER TABLE users ADD COLUMN email VARCHAR(255) NULL UNIQUE AFTER password");
            } elseif ($column === 'google_id') {
                $pdo->exec("ALTER TABLE users ADD COLUMN google_id VARCHAR(255) NULL UNIQUE AFTER email");
            } elseif ($column === 'fare_type') {
                $pdo->exec("ALTER TABLE users ADD COLUMN fare_type VARCHAR(20) NULL AFTER role");
            } elseif ($column === 'eligibility_proof') {
                $pdo->exec("ALTER TABLE users ADD COLUMN eligibility_proof VARCHAR(255) NULL AFTER fare_type");
            } elseif ($column === 'emergency_contact_name') {
                $pdo->exec("ALTER TABLE users ADD COLUMN emergency_contact_name VARCHAR(100) NULL AFTER eligibility_proof");
            } elseif ($column === 'emergency_contact') {
                $pdo->exec("ALTER TABLE users ADD COLUMN emergency_contact VARCHAR(100) NULL AFTER emergency_contact_name");
            } elseif ($column === 'emergency_contact_address') {
                $pdo->exec("ALTER TABLE users ADD COLUMN emergency_contact_address VARCHAR(255) NULL AFTER emergency_contact");
            } elseif ($column === 'profile_photo') {
                $pdo->exec("ALTER TABLE users ADD COLUMN profile_photo VARCHAR(255) NULL AFTER emergency_contact_address");
            }
        }
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS bus_stops (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL UNIQUE,
            area VARCHAR(100) NOT NULL,
            latitude DECIMAL(10,7) NOT NULL,
            longitude DECIMAL(10,7) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1
        )"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS road_notifications (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(120) NOT NULL,
            message TEXT NOT NULL,
            severity VARCHAR(20) NOT NULL DEFAULT 'warning',
            affected_area VARCHAR(120) NULL,
            latitude DECIMAL(10,7) NULL,
            longitude DECIMAL(10,7) NULL,
            starts_at DATETIME NULL,
            ends_at DATETIME NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )"
    );

    $notificationColumns = $pdo->query("SHOW COLUMNS FROM road_notifications")->fetchAll(PDO::FETCH_COLUMN);
    foreach (['affected_area', 'latitude', 'longitude', 'starts_at', 'ends_at'] as $column) {
        if (!in_array($column, $notificationColumns, true)) {
            if ($column === 'affected_area') {
                $pdo->exec("ALTER TABLE road_notifications ADD affected_area VARCHAR(120) NULL AFTER severity");
            } elseif ($column === 'latitude') {
                $pdo->exec("ALTER TABLE road_notifications ADD latitude DECIMAL(10,7) NULL AFTER affected_area");
            } elseif ($column === 'longitude') {
                $pdo->exec("ALTER TABLE road_notifications ADD longitude DECIMAL(10,7) NULL AFTER latitude");
            } elseif ($column === 'starts_at') {
                $pdo->exec("ALTER TABLE road_notifications ADD starts_at DATETIME NULL AFTER longitude");
            } elseif ($column === 'ends_at') {
                $pdo->exec("ALTER TABLE road_notifications ADD ends_at DATETIME NULL AFTER starts_at");
            }
        }
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS feedbacks (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            rating TINYINT UNSIGNED NULL,
            comment TEXT NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_feedback_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS ride_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            start_stop VARCHAR(100) NOT NULL,
            destination_stop VARCHAR(100) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'completed',
            started_at DATETIME NULL,
            completed_at DATETIME NULL,
            INDEX idx_ride_history_user (user_id)
        )"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS route_operations (
            route_id VARCHAR(40) PRIMARY KEY,
            is_online TINYINT(1) NOT NULL DEFAULT 1,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )"
    );

    $routeIds = ['sm-north-bgc', 'ayala-bgc', 'guadalupe-bgc', 'bgc-loop', 'venice-bgc'];
    $routeSeed = $pdo->prepare('INSERT IGNORE INTO route_operations (route_id, is_online) VALUES (?, 1)');
    foreach ($routeIds as $routeId) {
        $routeSeed->execute([$routeId]);
    }

    $adminHash = '$2y$10$2T4HLkWLX/9q9Rq4EMOlUePow9Vh8.V0CgYmS1ut/Xoo.BFvQyUr2';
    $seedAdmin = $pdo->prepare(
        "INSERT IGNORE INTO users (username, password, role) VALUES ('admin', ?, 'admin')"
    );
    $seedAdmin->execute([$adminHash]);

    $busStops = $pdo->query('SELECT COUNT(*) FROM bus_stops')->fetchColumn();
    if ((int) $busStops === 0) {
        $pdo->exec(
            "INSERT IGNORE INTO bus_stops (name, area, latitude, longitude) VALUES
            ('SM North EDSA', 'Quezon City', 14.657093, 121.031318),
            ('North Avenue / EDSA', 'Quezon City', 14.653200, 121.032100),
            ('Ayala MRT / EDSA', 'Makati', 14.549198, 121.027902),
            ('Guadalupe', 'Makati', 14.566600, 121.046200),
            ('Market! Market!', 'BGC', 14.548873, 121.056361),
            ('High Street', 'BGC', 14.550800, 121.054200),
            ('The Fort', 'BGC', 14.552000, 121.050500),
            ('Globe Tower', 'BGC', 14.554500, 121.051500),
            ('32nd Street', 'BGC', 14.553500, 121.056000),
            ('Venice Grand Canal', 'McKinley Hill', 14.533742, 121.051685)"
        );
    }
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>