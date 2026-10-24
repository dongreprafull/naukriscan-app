-- V21: Native/SMTP email provider + post-login email verification
-- Apply after v20-migration.sql

CREATE TABLE IF NOT EXISTS app_notification_settings (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY DEFAULT 1,
    email_provider ENUM('hostinger','smtp') NOT NULL DEFAULT 'hostinger',
    smtp_host VARCHAR(255) NULL,
    smtp_port SMALLINT UNSIGNED NULL DEFAULT 587,
    smtp_username VARCHAR(255) NULL,
    smtp_password VARCHAR(255) NULL,
    smtp_encryption ENUM('tls','ssl','none') NOT NULL DEFAULT 'tls',
    from_email VARCHAR(255) NOT NULL DEFAULT 'noreply@naukriscan.in',
    from_name VARCHAR(255) NOT NULL DEFAULT 'NaukriScan',
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_single_row CHECK (id = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO app_notification_settings (id, email_provider, from_email, from_name)
VALUES (1, 'hostinger', 'noreply@naukriscan.in', 'NaukriScan');

CREATE TABLE IF NOT EXISTS email_verifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    token VARCHAR(64) NOT NULL,
    code VARCHAR(6) NOT NULL,
    expires_at DATETIME NOT NULL,
    verified_at DATETIME NULL,
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_token (token),
    KEY idx_user (user_id),
    CONSTRAINT fk_ev_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
