<?php
require_once __DIR__ . '/../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        header('Location: login.php');
        exit;
    }
    return $user;
}

function require_admin(): array
{
    $user = require_login();
    if ($user['role'] !== 'ADMIN') {
        http_response_code(403);
        die('Access denied. This page is for admins only.');
    }
    return $user;
}

function is_admin(): bool
{
    $user = current_user();
    return $user && $user['role'] === 'ADMIN';
}

function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function ensure_station_pricing_schema(mysqli $conn): void
{
    $ensureColumn = function (string $table, string $column, string $sql) use ($conn): void {
        $res = mysqli_query($conn, 'SHOW COLUMNS FROM ' . $table . ' LIKE \'' . mysqli_real_escape_string($conn, $column) . '\'');
        if ($res && mysqli_num_rows($res) === 0) {
            mysqli_query($conn, $sql);
        }
    };

    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS reservations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        station_id INT NOT NULL,
        customer_name VARCHAR(100) NOT NULL,
        start_at DATETIME NOT NULL,
        end_at DATETIME NOT NULL,
        notes TEXT NULL,
        status ENUM('PENDING','CONFIRMED','CANCELLED') NOT NULL DEFAULT 'PENDING',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (station_id) REFERENCES stations(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS audit_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        action VARCHAR(100) NOT NULL,
        details TEXT NULL,
        entity_type VARCHAR(50) NULL,
        entity_id INT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS site_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        site_name VARCHAR(100) NOT NULL DEFAULT 'PS Gaming Center',
        currency_symbol VARCHAR(10) NOT NULL DEFAULT '$',
        low_stock_threshold INT NOT NULL DEFAULT 3,
        default_start_price_per_hour DECIMAL(10,2) NOT NULL DEFAULT 0,
        session_budget_warning DECIMAL(10,2) NOT NULL DEFAULT 0,
        session_duration_warning_minutes INT NOT NULL DEFAULT 0,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $ensureColumn('stations', 'price_per_hour_2', "ALTER TABLE stations ADD COLUMN price_per_hour_2 DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER price_per_hour");
    $ensureColumn('stations', 'price_per_hour_4', "ALTER TABLE stations ADD COLUMN price_per_hour_4 DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER price_per_hour_2");
    $ensureColumn('stations', 'default_start_price_per_hour', "ALTER TABLE stations ADD COLUMN default_start_price_per_hour DECIMAL(10,2) NULL AFTER price_per_hour_4");
    $ensureColumn('stations', 'current_player_count', "ALTER TABLE stations ADD COLUMN current_player_count INT NULL AFTER pos_y");
    $ensureColumn('stations', 'current_rate_per_hour', "ALTER TABLE stations ADD COLUMN current_rate_per_hour DECIMAL(10,2) NULL AFTER current_player_count");
    $statusColumn = mysqli_query($conn, "SHOW COLUMNS FROM stations LIKE 'status'");
    if ($statusColumn && mysqli_num_rows($statusColumn) > 0) {
        $statusRow = mysqli_fetch_assoc($statusColumn);
        if (stripos($statusRow['Type'] ?? '', "PAUSED") === false) {
            mysqli_query($conn, "ALTER TABLE stations MODIFY COLUMN status ENUM('FREE','BUSY','PAUSED') NOT NULL DEFAULT 'FREE'");
        }
    }
    $ensureColumn('stations', 'pause_started_at', "ALTER TABLE stations ADD COLUMN pause_started_at BIGINT NULL AFTER current_rate_per_hour");
    $ensureColumn('stations', 'paused_duration_ms', "ALTER TABLE stations ADD COLUMN paused_duration_ms BIGINT NOT NULL DEFAULT 0 AFTER pause_started_at");
    $ensureColumn('stations', 'current_session_notes', "ALTER TABLE stations ADD COLUMN current_session_notes TEXT NULL AFTER paused_duration_ms");
    $ensureColumn('stations', 'current_session_fee', "ALTER TABLE stations ADD COLUMN current_session_fee DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER current_session_notes");
    $ensureColumn('sessions', 'player_count', "ALTER TABLE sessions ADD COLUMN player_count INT NULL AFTER duration_minutes");
    $ensureColumn('sessions', 'pricing_rate_per_hour', "ALTER TABLE sessions ADD COLUMN pricing_rate_per_hour DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER session_price");
    $ensureColumn('sessions', 'session_fee', "ALTER TABLE sessions ADD COLUMN session_fee DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER pricing_rate_per_hour");
    $ensureColumn('sessions', 'notes', "ALTER TABLE sessions ADD COLUMN notes TEXT NULL AFTER total_price");
    $ensureColumn('sessions', 'receipt_number', "ALTER TABLE sessions ADD COLUMN receipt_number VARCHAR(50) NULL AFTER notes");
    $ensureColumn('sessions', 'snacks_snapshot', "ALTER TABLE sessions ADD COLUMN snacks_snapshot TEXT NULL AFTER receipt_number");
    $ensureColumn('site_settings', 'session_start_fee', "ALTER TABLE site_settings ADD COLUMN session_start_fee DECIMAL(10,2) NOT NULL DEFAULT 1 AFTER default_start_price_per_hour");
}

function session_start_fee(array $settings): float
{
    return isset($settings['session_start_fee']) ? (float) $settings['session_start_fee'] : 1.0;
}

function station_price_for_player_count(array $station, int $playerCount): float
{
    if ($playerCount === 4) {
        $value = isset($station['price_per_hour_4']) ? (float) $station['price_per_hour_4'] : 0;
        if ($value <= 0) {
            $value = isset($station['price_per_hour_2']) ? (float) $station['price_per_hour_2'] : 0;
        }
        return $value;
    }

    return isset($station['price_per_hour_2']) ? (float) $station['price_per_hour_2'] : 0;
}

function log_audit_event(mysqli $conn, ?int $userId, string $action, string $details, ?string $entityType = null, ?int $entityId = null): void
{
    $stmt = mysqli_prepare($conn, 'INSERT INTO audit_log (user_id, action, details, entity_type, entity_id) VALUES (?, ?, ?, ?, ?)');
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'isssi', $userId, $action, $details, $entityType, $entityId);
        mysqli_stmt_execute($stmt);
    }
}

function get_site_settings(mysqli $conn): array
{
    $res = mysqli_query($conn, 'SELECT * FROM site_settings ORDER BY id ASC LIMIT 1');
    if ($res && mysqli_num_rows($res) > 0) {
        $row = mysqli_fetch_assoc($res);
        return $row ?: [];
    }

    mysqli_query($conn, "INSERT INTO site_settings (site_name, currency_symbol, low_stock_threshold, default_start_price_per_hour, session_start_fee, session_budget_warning, session_duration_warning_minutes) VALUES ('PS Gaming Center', '$', 3, 0, 1, 0, 0)");
    return [
        'site_name' => 'PS Gaming Center',
        'currency_symbol' => '$',
        'low_stock_threshold' => 3,
        'default_start_price_per_hour' => 0,
        'session_start_fee' => 1,
        'session_budget_warning' => 0,
        'session_duration_warning_minutes' => 0,
    ];
}

function get_low_stock_snacks(mysqli $conn, int $threshold = 3): array
{
    $snacks = [];
    $stmt = mysqli_prepare($conn, 'SELECT * FROM snacks WHERE stock <= ? ORDER BY stock ASC, name ASC');
    mysqli_stmt_bind_param($stmt, 'i', $threshold);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $snacks[] = $row;
    }
    return $snacks;
}
