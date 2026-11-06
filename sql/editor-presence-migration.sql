-- Additive Content Editor presence and optimistic concurrency metadata.
CREATE TABLE IF NOT EXISTS content_editor_versions (
    scope_key VARCHAR(120) NOT NULL PRIMARY KEY,
    revision BIGINT UNSIGNED NOT NULL DEFAULT 0,
    lease_owner CHAR(64) NULL,
    lease_until BIGINT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS content_editor_presence (
    peer_key CHAR(64) NOT NULL PRIMARY KEY,
    session_key CHAR(64) NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    credential_version INT UNSIGNED NOT NULL,
    page_key CHAR(64) NOT NULL,
    scope_key VARCHAR(120) NOT NULL,
    base_revision BIGINT UNSIGNED NOT NULL,
    external_revision BIGINT UNSIGNED NOT NULL,
    activity VARCHAR(8) NOT NULL DEFAULT 'viewing',
    seen_at BIGINT UNSIGNED NOT NULL,
    INDEX page_seen (page_key, seen_at),
    INDEX expiry (seen_at),
    INDEX session_peers (session_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
