-- Additive migration. Back up the intended database before applying.
ALTER TABLE events ADD COLUMN description TEXT NULL;

CREATE TABLE calendar_import_series (
    series_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    start_year SMALLINT NOT NULL,
    revision INT NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    user_id INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE calendar_import_batches (
    token CHAR(64) NOT NULL PRIMARY KEY,
    series_id INT NOT NULL,
    user_id INT NOT NULL,
    base_revision INT NOT NULL,
    state VARCHAR(20) NOT NULL DEFAULT 'preview',
    filename VARCHAR(255) NOT NULL,
    preview_json LONGTEXT NOT NULL,
    approved_json LONGTEXT NULL,
    calendar_action VARCHAR(20) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (series_id), INDEX (user_id, state)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE calendar_import_sources (
    event_id INT NOT NULL PRIMARY KEY,
    series_id INT NOT NULL,
    INDEX (series_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
