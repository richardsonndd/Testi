-- PlayStation Gaming Center Management — schema

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('ADMIN','STAFF') NOT NULL DEFAULT 'STAFF',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS zones (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL UNIQUE,
  width INT NOT NULL DEFAULT 900,
  height INT NOT NULL DEFAULT 600,
  sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS stations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  console_type VARCHAR(50) NOT NULL DEFAULT 'PS5',
  price_per_hour DECIMAL(10,2) NOT NULL DEFAULT 0,
  price_per_hour_2 DECIMAL(10,2) NOT NULL DEFAULT 2.50,
  price_per_hour_4 DECIMAL(10,2) NOT NULL DEFAULT 4.00,
  default_start_price_per_hour DECIMAL(10,2) NULL,
  status ENUM('FREE','BUSY','PAUSED') NOT NULL DEFAULT 'FREE',
  session_start BIGINT NULL,
  zone_id INT NOT NULL,
  pos_x INT NOT NULL DEFAULT 20,
  pos_y INT NOT NULL DEFAULT 20,
  current_player_count INT NULL,
  current_rate_per_hour DECIMAL(10,2) NULL,
  pause_started_at BIGINT NULL,
  paused_duration_ms BIGINT NOT NULL DEFAULT 0,
  current_session_notes TEXT NULL,
  current_session_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (zone_id) REFERENCES zones(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS snacks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  price DECIMAL(10,2) NOT NULL DEFAULT 0,
  stock INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS site_settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  site_name VARCHAR(100) NOT NULL DEFAULT 'PS Gaming Center',
  currency_symbol VARCHAR(10) NOT NULL DEFAULT '$',
  low_stock_threshold INT NOT NULL DEFAULT 3,
  default_start_price_per_hour DECIMAL(10,2) NOT NULL DEFAULT 0,
  session_start_fee DECIMAL(10,2) NOT NULL DEFAULT 1,
  session_budget_warning DECIMAL(10,2) NOT NULL DEFAULT 0,
  session_duration_warning_minutes INT NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One row per finished station session (for history + revenue reporting)
CREATE TABLE IF NOT EXISTS sessions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  station_id INT NULL,
  station_name VARCHAR(100) NOT NULL,
  started_at DATETIME NOT NULL,
  ended_at DATETIME NOT NULL,
  duration_minutes INT NOT NULL,
  session_price DECIMAL(10,2) NOT NULL DEFAULT 0,
  pricing_rate_per_hour DECIMAL(10,2) NOT NULL DEFAULT 0,
  session_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
  player_count INT NULL,
  snacks_total DECIMAL(10,2) NOT NULL DEFAULT 0,
  total_price DECIMAL(10,2) NOT NULL DEFAULT 0,
  notes TEXT NULL,
  receipt_number VARCHAR(50) NULL,
  snacks_snapshot TEXT NULL,
  closed_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (station_id) REFERENCES stations(id) ON DELETE SET NULL,
  FOREIGN KEY (closed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Snacks ordered during an ACTIVE (not yet closed) station session.
-- Cleared into session history text once the session ends (kept simple: just summed).
CREATE TABLE IF NOT EXISTS active_orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  station_id INT NOT NULL,
  snack_id INT NOT NULL,
  snack_name VARCHAR(100) NOT NULL,
  price DECIMAL(10,2) NOT NULL,
  quantity INT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (station_id) REFERENCES stations(id) ON DELETE CASCADE,
  FOREIGN KEY (snack_id) REFERENCES snacks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO zones (name, width, height, sort_order)
SELECT * FROM (SELECT 'Main Floor' AS name, 900 AS width, 600 AS height, 0 AS sort_order) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM zones WHERE name = 'Main Floor');
