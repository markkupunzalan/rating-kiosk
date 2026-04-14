CREATE DATABASE IF NOT EXISTS feedbackiq
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE feedbackiq;

-- ── Feedback submissions ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS feedback (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  q1_rating     TINYINT(1)  NOT NULL CHECK (q1_rating BETWEEN 1 AND 5),
  q2_rating     TINYINT(1)  NOT NULL CHECK (q2_rating BETWEEN 1 AND 5),
  q3_rating     TINYINT(1)  NOT NULL CHECK (q3_rating BETWEEN 1 AND 5),
  q4_rating     TINYINT(1)  NOT NULL CHECK (q4_rating BETWEEN 1 AND 5),
  q5_rating     TINYINT(1)  NOT NULL CHECK (q5_rating BETWEEN 1 AND 5),
  overall_rating DECIMAL(3,2) GENERATED ALWAYS AS (
    (q1_rating + q2_rating + q3_rating + q4_rating + q5_rating) / 5.0
  ) STORED,  
  sentiment     ENUM('positive','neutral','negative') NOT NULL,
  comment       TEXT         DEFAULT NULL,
  language      VARCHAR(10)  NOT NULL DEFAULT 'en',
  submitted_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_submitted_at (submitted_at),
  INDEX idx_sentiment    (sentiment),
  INDEX idx_overall      (overall_rating)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Settings (key-value pairs) ────────────────────────────────
DROP TABLE IF EXISTS settings;
CREATE TABLE settings (
  setting_key   VARCHAR(64)  NOT NULL,
  setting_value TEXT         DEFAULT NULL,
  updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default settings
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
  ('business_name',       'Feedback Kiosk'),
  ('default_language',    'en'),
  ('logo_url',            ''),
  ('primary_color',       ''),
  ('anonymous_feedback',  '1');

-- ── Admin Users ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `admins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(255) UNIQUE DEFAULT NULL,
  `reset_token` varchar(64) DEFAULT NULL,
  `reset_token_expires_at` datetime DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- The default password is 'password123'
INSERT IGNORE INTO `admins` (`username`, `password`, `email`) VALUES
('admin', '$2y$12$jLXRsUL2fBSlz7L3/bREUO75UkDtKc1r8xhob.3xX.MGJ.H92EsHi', 'admin@example.com');
