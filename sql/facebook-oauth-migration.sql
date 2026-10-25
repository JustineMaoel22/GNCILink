-- Additive upgrade for databases with facebook-publishing-migration.sql installed.
-- Credentials are encrypted using the backend FB_PUBLISH_TOKEN_KEY, never plaintext.
CREATE TABLE IF NOT EXISTS facebook_publishing_tokens (
    connection_id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    page_id VARCHAR(40) NOT NULL,
    encrypted_token TEXT NOT NULL,
    environment VARCHAR(10) NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
