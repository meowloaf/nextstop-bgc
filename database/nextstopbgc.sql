CREATE DATABASE IF NOT EXISTS bgc_bus_system;
USE bgc_bus_system;

CREATE TABLE IF NOT EXISTS users (
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
);

CREATE TABLE IF NOT EXISTS password_reset_codes (
	id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	user_id INT NOT NULL UNIQUE,
	code_hash VARCHAR(255) NOT NULL,
	expires_at DATETIME NOT NULL,
	attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	CONSTRAINT fk_password_reset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS bus_stops (
	id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	name VARCHAR(100) NOT NULL UNIQUE,
	area VARCHAR(100) NOT NULL,
	latitude DECIMAL(10,7) NOT NULL,
	longitude DECIMAL(10,7) NOT NULL,
	is_active TINYINT(1) NOT NULL DEFAULT 1
);

CREATE TABLE IF NOT EXISTS road_notifications (
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
);

INSERT IGNORE INTO bus_stops (name, area, latitude, longitude) VALUES
('SM North EDSA', 'Quezon City', 14.657093, 121.031318),
('North Avenue / EDSA', 'Quezon City', 14.653200, 121.032100),
('Ayala MRT / EDSA', 'Makati', 14.549198, 121.027902),
('Guadalupe', 'Makati', 14.566600, 121.046200),
('Market! Market!', 'BGC', 14.548873, 121.056361),
('High Street', 'BGC', 14.550800, 121.054200),
('The Fort', 'BGC', 14.552000, 121.050500),
('Globe Tower', 'BGC', 14.554500, 121.051500),
('32nd Street', 'BGC', 14.553500, 121.056000),
('Venice Grand Canal', 'McKinley Hill', 14.533742, 121.051685);

CREATE TABLE IF NOT EXISTS ride_history (
	id INT AUTO_INCREMENT PRIMARY KEY,
	user_id INT NOT NULL,
	start_stop VARCHAR(100) NOT NULL,
	destination_stop VARCHAR(100) NOT NULL,
	status VARCHAR(20) NOT NULL DEFAULT 'completed',
	started_at DATETIME NULL,
	completed_at DATETIME NULL,
	INDEX idx_ride_history_user (user_id)
);

CREATE TABLE IF NOT EXISTS feedbacks (
	id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	user_id INT NOT NULL,
	ride_id INT NULL,
	rating TINYINT UNSIGNED NULL,
	comment TEXT NOT NULL,
	photo_path VARCHAR(255) NULL,
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	UNIQUE KEY uq_feedback_ride (ride_id),
	CONSTRAINT fk_feedback_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
	CONSTRAINT fk_feedback_ride FOREIGN KEY (ride_id) REFERENCES ride_history(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS route_operations (
	route_id VARCHAR(40) PRIMARY KEY,
	is_online TINYINT(1) NOT NULL DEFAULT 1,
	updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS route_buses (
	id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	route_id VARCHAR(40) NOT NULL,
	plate_number VARCHAR(20) NOT NULL UNIQUE,
	is_online TINYINT(1) NOT NULL DEFAULT 1,
	is_default TINYINT(1) NOT NULL DEFAULT 0,
	INDEX idx_route_buses_route (route_id)
);

INSERT IGNORE INTO route_operations (route_id, is_online) VALUES
('east-express', 1),
('north-route', 1),
('west-route', 1),
('arca-south-express', 1),
('central-route', 1),
('night-route', 1),
('lrt-ayala-route', 1);

INSERT IGNORE INTO route_buses (route_id, plate_number, is_online, is_default) VALUES
('east-express', 'EX-DEFAULT', 1, 1),
('north-route', 'NR-DEFAULT', 1, 1),
('west-route', 'WR-DEFAULT', 1, 1),
('arca-south-express', 'AS-DEFAULT', 1, 1),
('central-route', 'CR-DEFAULT', 1, 1),
('night-route', 'NT-DEFAULT', 1, 1),
('lrt-ayala-route', 'LRT-DEFAULT', 1, 1);
