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
            fare_type VARCHAR(20) NOT NULL DEFAULT 'regular',
            eligibility_proof VARCHAR(255) NULL,
            emergency_contact_name VARCHAR(100) NULL,
            emergency_contact VARCHAR(100) NULL,
            emergency_contact_address VARCHAR(255) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )"
    );

    $userColumns = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
    foreach (['email', 'google_id', 'fare_type', 'eligibility_proof', 'emergency_contact_name', 'emergency_contact', 'emergency_contact_address'] as $column) {
        if (!in_array($column, $userColumns, true)) {
            $after = match ($column) {
                'email' => 'password',
                'google_id' => 'email',
                'fare_type' => 'role',
                'eligibility_proof' => 'fare_type',
                'emergency_contact_name' => 'eligibility_proof',
                'emergency_contact' => 'emergency_contact_name',
                'emergency_contact_address' => 'emergency_contact',
                default => 'role'
            };

            if ($column === 'email') {
                $pdo->exec("ALTER TABLE users ADD COLUMN email VARCHAR(255) NULL UNIQUE AFTER password");
            } elseif ($column === 'google_id') {
                $pdo->exec("ALTER TABLE users ADD COLUMN google_id VARCHAR(255) NULL UNIQUE AFTER email");
            } elseif ($column === 'fare_type') {
                $pdo->exec("ALTER TABLE users ADD COLUMN fare_type VARCHAR(20) NOT NULL DEFAULT 'regular' AFTER role");
            } elseif ($column === 'eligibility_proof') {
                $pdo->exec("ALTER TABLE users ADD COLUMN eligibility_proof VARCHAR(255) NULL AFTER fare_type");
            } elseif ($column === 'emergency_contact_name') {
                $pdo->exec("ALTER TABLE users ADD COLUMN emergency_contact_name VARCHAR(100) NULL AFTER eligibility_proof");
            } elseif ($column === 'emergency_contact') {
                $pdo->exec("ALTER TABLE users ADD COLUMN emergency_contact VARCHAR(100) NULL AFTER emergency_contact_name");
            } elseif ($column === 'emergency_contact_address') {
                $pdo->exec("ALTER TABLE users ADD COLUMN emergency_contact_address VARCHAR(255) NULL AFTER emergency_contact");
            }
        }
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS password_reset_codes (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL UNIQUE,
            code_hash VARCHAR(255) NOT NULL,
            expires_at DATETIME NOT NULL,
            attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_password_reset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )"
    );

    $fareTypeColumn = $pdo->query("SHOW COLUMNS FROM users LIKE 'fare_type'")->fetch(PDO::FETCH_ASSOC);
    if ($fareTypeColumn && ($fareTypeColumn['Null'] === 'YES' || $fareTypeColumn['Default'] !== 'regular')) {
        $pdo->exec("UPDATE users SET fare_type = 'regular' WHERE fare_type IS NULL OR fare_type = ''");
        $pdo->exec("ALTER TABLE users MODIFY fare_type VARCHAR(20) NOT NULL DEFAULT 'regular'");
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

    $feedbackColumns = $pdo->query("SHOW COLUMNS FROM feedbacks")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('ride_id', $feedbackColumns, true)) {
        $pdo->exec("ALTER TABLE feedbacks ADD ride_id INT NULL AFTER user_id");
    }

    $feedbackIndexes = $pdo->query("SHOW INDEX FROM feedbacks")->fetchAll(PDO::FETCH_ASSOC);
    $hasRideFeedbackIndex = false;
    foreach ($feedbackIndexes as $index) {
        if ($index['Key_name'] === 'uq_feedback_ride') {
            $hasRideFeedbackIndex = true;
            break;
        }
    }
    if (!$hasRideFeedbackIndex) {
        $pdo->exec("ALTER TABLE feedbacks ADD UNIQUE INDEX uq_feedback_ride (ride_id)");
    }

    $feedbackCreateSql = $pdo->query("SHOW CREATE TABLE feedbacks")->fetch(PDO::FETCH_ASSOC);
    if (!str_contains($feedbackCreateSql['Create Table'] ?? '', 'fk_feedback_ride')) {
        $pdo->exec(
            "ALTER TABLE feedbacks
             ADD CONSTRAINT fk_feedback_ride
             FOREIGN KEY (ride_id) REFERENCES ride_history(id) ON DELETE SET NULL"
        );
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS route_operations (
            route_id VARCHAR(40) PRIMARY KEY,
            is_online TINYINT(1) NOT NULL DEFAULT 1,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS route_buses (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            route_id VARCHAR(40) NOT NULL,
            plate_number VARCHAR(20) NOT NULL UNIQUE,
            is_online TINYINT(1) NOT NULL DEFAULT 1,
            is_default TINYINT(1) NOT NULL DEFAULT 0,
            INDEX idx_route_buses_route (route_id)
        )"
    );

    $routeBusColumns = $pdo->query("SHOW COLUMNS FROM route_buses")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('is_online', $routeBusColumns, true)) {
        $pdo->exec("ALTER TABLE route_buses ADD is_online TINYINT(1) NOT NULL DEFAULT 1");
    }
    if (!in_array('is_default', $routeBusColumns, true)) {
        $pdo->exec("ALTER TABLE route_buses ADD is_default TINYINT(1) NOT NULL DEFAULT 0");
    }

    $routeIds = [
        'east-express' => 'EX',
        'north-route' => 'NR',
        'west-route' => 'WR',
        'arca-south-express' => 'AS',
        'central-route' => 'CR',
        'night-route' => 'NT',
        'lrt-ayala-route' => 'LRT',
    ];
    $routeSeed = $pdo->prepare('INSERT IGNORE INTO route_operations (route_id, is_online) VALUES (?, 1)');
    $defaultBusCheck = $pdo->prepare('SELECT id FROM route_buses WHERE route_id = ? AND is_default = 1 LIMIT 1');
    $defaultBusInsert = $pdo->prepare('INSERT INTO route_buses (route_id, plate_number, is_online, is_default) VALUES (?, ?, 1, 1)');
    foreach ($routeIds as $routeId => $routeCode) {
        $routeSeed->execute([$routeId]);

        $defaultBusCheck->execute([$routeId]);
        if (!$defaultBusCheck->fetchColumn()) {
            $plateNumber = $routeCode . '-DEFAULT';
            $suffix = 2;
            while (true) {
                $plateCheck = $pdo->prepare('SELECT id FROM route_buses WHERE plate_number = ? LIMIT 1');
                $plateCheck->execute([$plateNumber]);
                if (!$plateCheck->fetchColumn()) {
                    break;
                }
                $plateNumber = $routeCode . '-DEF-' . $suffix++;
            }
            $defaultBusInsert->execute([$routeId, $plateNumber]);
        }
    }

    $adminHash = '$2y$10$2T4HLkWLX/9q9Rq4EMOlUePow9Vh8.V0CgYmS1ut/Xoo.BFvQyUr2';
    $seedAdmin = $pdo->prepare(
        "INSERT IGNORE INTO users (username, password, role) VALUES ('admin', ?, 'admin')"
    );
    $seedAdmin->execute([$adminHash]);

    $pdo->exec(
        "INSERT IGNORE INTO bus_stops (name, area, latitude, longitude) VALUES
        ('EDSA Ayala', 'Makati', 14.54894167, 121.02767222),
        ('Market! Market!', 'BGC', 14.5492886, 121.0559966),
        ('HSBC', 'BGC', 14.5535091, 121.0485311),
        ('Turf BGC', 'BGC', 14.5540884, 121.0519522),
        ('Avida 34th', 'BGC', 14.5547163, 121.0540338),
        ('Uptown Mall', 'BGC', 14.5563154, 121.0544068),
        ('Uptown Parade', 'BGC', 14.5580384, 121.0543999),
        ('The Globe Tower', 'BGC', 14.5528868, 121.0501066),
        ('Arca South', 'Taguig', 14.50555556, 121.04411111),
        ('NutriAsia', 'BGC', 14.5517828, 121.0512573),
        ('The Fort', 'BGC', 14.5518, 121.0505),
        ('One/NEO', 'BGC', 14.5497492, 121.0455238),
        ('Bonifacio Stopover', 'BGC', 14.5546751, 121.0458416),
        ('Crescent Park West', 'BGC', 14.5544405, 121.0438408),
        ('Fort Victoria', 'BGC', 14.5471058, 121.0456866),
        ('McKinley Parkway', 'BGC', 14.55158, 121.0558313),
        ('Arya Residences', 'BGC', 14.5462322, 121.0482351),
        ('The Finance Centre', 'BGC', 14.546628, 121.0500938),
        ('One Parkade', 'BGC', 14.550067, 121.0495318),
        ('University Parkway', 'BGC', 14.5541658, 121.0572816),
        ('MECC', 'Makati (approximate)', 14.5538, 121.0245),
        ('Makati Stock Exchange', 'Makati', 14.5557166, 121.0230533),
        ('PBCOM', 'Makati', 14.5585501, 121.0193853),
        ('RCBC', 'Makati', 14.5607615, 121.0163798),
        ('The Columns', 'Makati', 14.5618494, 121.0162319),
        ('Chino Roces', 'Makati', 14.5616346, 121.0109937),
        ('Mayapis', 'Makati', 14.5635114, 121.005145),
        ('LRT Buendia', 'Pasay', 14.5541275, 120.9971759),
        ('Leveriza', 'Pasay', 14.558357, 120.993362),
        ('Makati Mati', 'Makati (approximate)', 14.5593307, 121.0147968),
        ('City Gate', 'Makati (approximate)', 14.5596055, 121.0154958),
        ('Security Bank', 'Makati', 14.558617, 121.0187768),
        ('SGV', 'Makati', 14.5555299, 121.0224672),
        ('Glorietta', 'Makati', 14.55124, 121.02537),
        ('One Ayala', 'Makati', 14.55047, 121.02793)"
    );
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>