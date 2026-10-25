-- Back up first. Requires first-login-password-migration.sql. No existing records are removed.
CREATE TABLE IF NOT EXISTS auth_password_resets (
 user_id INT NOT NULL PRIMARY KEY,
 token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
 expires_at DATETIME NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS auth_rate_limits (
 bucket_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
 attempts INT UNSIGNED NOT NULL DEFAULT 1,
 expires_at DATETIME NOT NULL,
 INDEX auth_rate_expiry(expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS auth_security_logs (
 log_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 user_id INT NULL,
 action VARCHAR(50) NOT NULL,
 ip_address VARCHAR(45) NOT NULL,
 description VARCHAR(255) NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX auth_log_time(created_at), INDEX auth_log_user(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
