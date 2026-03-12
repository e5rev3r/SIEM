-- CentralLog Database Schema
-- Run: mysql -u root -p < schema.sql

CREATE DATABASE IF NOT EXISTS centrallog CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE centrallog;

-- Dashboard operator accounts
CREATE TABLE users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(64)  NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login    DATETIME
) ENGINE=InnoDB;

-- Parsed log events from target machine
CREATE TABLE events (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ingested_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    event_time    DATETIME     NOT NULL,
    hostname      VARCHAR(255) NOT NULL DEFAULT '',
    log_source    VARCHAR(64)  NOT NULL,
    service       VARCHAR(128) DEFAULT NULL,
    source_ip     VARCHAR(45)  DEFAULT NULL,
    severity      ENUM('INFO','WARNING','ERROR','CRITICAL') NOT NULL DEFAULT 'INFO',
    message       TEXT         NOT NULL,
    raw_line      TEXT         NOT NULL,
    INDEX idx_event_time  (event_time),
    INDEX idx_source_ip   (source_ip),
    INDEX idx_severity    (severity),
    INDEX idx_log_source  (log_source)
) ENGINE=InnoDB;

-- User-defined alert rules
CREATE TABLE alert_rules (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(255) NOT NULL,
    log_source    VARCHAR(64)  DEFAULT NULL,
    condition_key VARCHAR(64)  NOT NULL,
    threshold     INT          NOT NULL,
    window_mins   INT          NOT NULL,
    severity      ENUM('WARNING','CRITICAL') NOT NULL DEFAULT 'WARNING',
    enabled       TINYINT(1)   NOT NULL DEFAULT 1,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Triggered alert instances
CREATE TABLE alerts (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    triggered_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    rule_id       INT UNSIGNED DEFAULT NULL,
    rule_name     VARCHAR(255) NOT NULL,
    severity      ENUM('WARNING','CRITICAL') NOT NULL,
    detail        TEXT,
    source_ip     VARCHAR(45)  DEFAULT NULL,
    acknowledged  TINYINT(1)   NOT NULL DEFAULT 0,
    ack_by        VARCHAR(64)  DEFAULT NULL,
    ack_at        DATETIME     DEFAULT NULL,
    INDEX idx_triggered_at (triggered_at),
    INDEX idx_acknowledged (acknowledged),
    FOREIGN KEY (rule_id) REFERENCES alert_rules(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Currently blocked IPs on the target machine
CREATE TABLE blocked_ips (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip_address  VARCHAR(45)  NOT NULL UNIQUE,
    blocked_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reason      VARCHAR(255) DEFAULT NULL,
    blocked_by  VARCHAR(64)  NOT NULL DEFAULT 'admin'
) ENGINE=InnoDB;

-- Audit trail of all response actions
CREATE TABLE action_log (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    action_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    action_type VARCHAR(32)  NOT NULL,
    actor       VARCHAR(64)  NOT NULL,
    target      VARCHAR(255) DEFAULT NULL,
    detail      TEXT         DEFAULT NULL,
    result      ENUM('SUCCESS','FAILURE') NOT NULL
) ENGINE=InnoDB;

-- Seed default admin user (password: admin — change on first use)
INSERT INTO users (username, password_hash) VALUES
('admin', '$2y$12$LJ3m4ys3Gzf0GQ9DKxPHHeY/0RFbFMwEb0G2dGYVxF5.qTlZr.W6e');

-- Seed default alert rules
INSERT INTO alert_rules (name, log_source, condition_key, threshold, window_mins, severity) VALUES
('SSH Brute Force',        'auth',          'failed_ssh',      5,  5,  'CRITICAL'),
('HTTP 404 Scanning',      'apache_access', 'http_404',        20, 2,  'WARNING'),
('Auth Failure Spike',     'auth',          'auth_failure',    10, 10, 'WARNING'),
('Fail2ban Ban Detected',  'fail2ban',      'fail2ban_ban',    1,  1,  'CRITICAL');
