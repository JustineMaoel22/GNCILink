-- Run manually after backing up the database. Additive; no announcements are republished.
CREATE TABLE IF NOT EXISTS facebook_publishing_connection (
    connection_id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    page_id VARCHAR(40) NOT NULL,
    page_name VARCHAR(255) NOT NULL,
    environment VARCHAR(10) NOT NULL DEFAULT 'test',
    enabled TINYINT NOT NULL DEFAULT 0,
    connected_by INT NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Deliberately retained if an announcement is deleted, for delivery auditing.
CREATE TABLE IF NOT EXISTS announcement_facebook_deliveries (
    announcement_id INT NOT NULL PRIMARY KEY,
    page_id VARCHAR(40) NOT NULL,
    state VARCHAR(20) NOT NULL DEFAULT 'ready',
    post_id VARCHAR(100) NULL,
    post_url VARCHAR(255) NULL,
    last_error VARCHAR(500) NULL,
    attempted_by INT NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS facebook_publishing_requests (
    request_key CHAR(64) NOT NULL PRIMARY KEY,
    user_id INT NOT NULL,
    announcement_id INT NOT NULL,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- OAuth credentials stay encrypted; existing manual connections remain usable.
CREATE TABLE IF NOT EXISTS facebook_publishing_tokens (
    connection_id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    page_id VARCHAR(40) NOT NULL,
    encrypted_token TEXT NOT NULL,
    environment VARCHAR(10) NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
