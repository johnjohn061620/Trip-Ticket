-- =====================================================
-- Trip Ticket System - MySQL / MariaDB schema
-- Import via phpMyAdmin or: mysql -u root < database.sql
-- =====================================================
CREATE DATABASE IF NOT EXISTS trip_ticket
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE trip_ticket;

-- ---------------------------------------------------
-- Settings: single row with logo, names and signatories
-- ---------------------------------------------------
CREATE TABLE IF NOT EXISTS settings (
  id              TINYINT UNSIGNED NOT NULL DEFAULT 1,
  admin_hash      VARCHAR(255) NOT NULL DEFAULT '',
  logo_path       VARCHAR(255) DEFAULT NULL,
  country         VARCHAR(120) NOT NULL DEFAULT 'Republic of the Philippines',
  agency_name     VARCHAR(200) NOT NULL DEFAULT '',
  office_name     VARCHAR(200) NOT NULL DEFAULT '',
  address         VARCHAR(255) NOT NULL DEFAULT '',
  doc_code        VARCHAR(160) NOT NULL DEFAULT '',
  ticket_prefix   VARCHAR(20)  NOT NULL DEFAULT 'AP-',
  driver_name     VARCHAR(160) NOT NULL DEFAULT '',
  plate_no        VARCHAR(60)  NOT NULL DEFAULT '',
  default_vehicle_id INT UNSIGNED DEFAULT NULL,
  approver1_name  VARCHAR(160) NOT NULL DEFAULT '',
  approver1_sub   VARCHAR(255) NOT NULL DEFAULT '',
  approver2_name  VARCHAR(160) NOT NULL DEFAULT '',
  approver2_sub   VARCHAR(255) NOT NULL DEFAULT '',
  attest_name     VARCHAR(160) NOT NULL DEFAULT '',
  attest_sub      VARCHAR(255) NOT NULL DEFAULT '',
  cert_name       VARCHAR(160) NOT NULL DEFAULT '',
  cert_sub        VARCHAR(255) NOT NULL DEFAULT '',
  updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB;

-- ---------------------------------------------------
-- Saved trip tickets
-- ---------------------------------------------------
CREATE TABLE IF NOT EXISTS trip_tickets (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ticket_no     VARCHAR(60)  NOT NULL DEFAULT '',
  ticket_date   VARCHAR(60)  NOT NULL DEFAULT '',
  date_iso      DATE         DEFAULT NULL,
  driver_name   VARCHAR(160) NOT NULL DEFAULT '',
  plate_no      VARCHAR(60)  NOT NULL DEFAULT '',
  vehicle_name  VARCHAR(60)  NOT NULL DEFAULT '',
  fuel_rate     DECIMAL(6,2) NOT NULL DEFAULT 30,
  passengers    TEXT,
  places        TEXT,
  purpose       TEXT,
  dt_needed     VARCHAR(120) NOT NULL DEFAULT '',
  dt_return     VARCHAR(120) NOT NULL DEFAULT '',
  time_dep      VARCHAR(40)  NOT NULL DEFAULT '',
  time_ret      VARCHAR(40)  NOT NULL DEFAULT '',
  distance_km   VARCHAR(40)  NOT NULL DEFAULT '',
  gas_a         DECIMAL(10,2) DEFAULT NULL,
  gas_b         DECIMAL(10,2) DEFAULT NULL,
  gas_c         DECIMAL(10,2) DEFAULT NULL,
  gas_d         DECIMAL(10,2) DEFAULT NULL,
  gas_e         DECIMAL(10,2) DEFAULT NULL,
  speed_start   DECIMAL(10,2) DEFAULT NULL,
  speed_end     DECIMAL(10,2) DEFAULT NULL,
  speed_dist    DECIMAL(10,2) DEFAULT NULL,
  remarks       TEXT,
  log_rows      TEXT,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ticket_no (ticket_no),
  KEY idx_date_iso (date_iso)
) ENGINE=InnoDB;

-- ---------------------------------------------------
-- Vehicles and their average fuel consumption (km per liter)
-- ---------------------------------------------------
CREATE TABLE IF NOT EXISTS vehicles (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name          VARCHAR(60)  NOT NULL,
  plate_no      VARCHAR(60)  NOT NULL DEFAULT '',
  km_per_liter  DECIMAL(6,2) NOT NULL DEFAULT 30,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_vehicle_name (name)
) ENGINE=InnoDB;

INSERT INTO vehicles (name, plate_no, km_per_liter) VALUES
('Aerox', '140106', 30.00),
('Ford Pick up', '', 8.00)
ON DUPLICATE KEY UPDATE id = id;

-- ---------------------------------------------------
-- Driver accounts (username + password feed the driver dropdown)
-- Seed account: username jandres / password admin123
-- ---------------------------------------------------
CREATE TABLE IF NOT EXISTS drivers (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username      VARCHAR(60)  NOT NULL,
  password_hash VARCHAR(255) NOT NULL DEFAULT '',
  full_name     VARCHAR(160) NOT NULL DEFAULT '',
  active        TINYINT(1)   NOT NULL DEFAULT 1,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_username (username)
) ENGINE=InnoDB;

INSERT INTO drivers (username, password_hash, full_name, active) VALUES
('jandres', '$2y$10$UrTsd4/5Ys2DXvTKQgsQ.O7WLjJN5yRRxaDvSkdC0ann9Fv5iDz5y', 'JOHN BENEDICT Q. ANDRES', 1)
ON DUPLICATE KEY UPDATE id = id;

-- ---------------------------------------------------
-- Default settings (change the admin password!)
-- Login password for this seed: admin123
-- ---------------------------------------------------
INSERT INTO settings (
  id, admin_hash, country, agency_name, office_name, address, doc_code, ticket_prefix,
  driver_name, plate_no,
  approver1_name, approver1_sub, approver2_name, approver2_sub,
  attest_name, attest_sub, cert_name, cert_sub
) VALUES (
  1,
  '$2y$10$UrTsd4/5Ys2DXvTKQgsQ.O7WLjJN5yRRxaDvSkdC0ann9Fv5iDz5y',
  'Republic of the Philippines',
  'DEPARTMENT OF THE INTERIOR AND LOCAL GOVERNMENT',
  'CAR - APAYAO PROVINCIAL OFFICE',
  'Payanan, San Gregorio, Luna, Apayao',
  'FM-QP-DILG-AS-RO-12-04 | Rev. No.: 00 | Eff. Date: 06.15.21',
  'AP-',
  'JOHN BENEDICT Q. ANDRES',
  '140106',
  'MARLEEN M. GUERZON', 'Chief, AS-General Service Division /<br>Finance and Administrative Division',
  'EDWARD ERROL B. UCOL', 'Chief, Motorpool Section /<br>Designated Authority',
  'MARLEEN M. GUERZON', 'Provincial Director',
  'JOHN BENEDICT Q. ANDRES', 'Driver'
) ON DUPLICATE KEY UPDATE id = id;
