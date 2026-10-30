-- Additive: existing content and media remain the fallback for every device.
CREATE TABLE IF NOT EXISTS responsive_background_settings (
    page_key CHAR(64) NOT NULL,
    element_key CHAR(64) NOT NULL,
    device VARCHAR(10) NOT NULL,
    selector VARCHAR(1000) NOT NULL,
    settings TEXT NOT NULL,
    media_id INT NULL,
    updated_by INT NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (page_key, element_key, device),
    INDEX (media_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
