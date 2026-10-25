-- Additive only. Import into the GNCI database using the hosting control panel.
CREATE TABLE IF NOT EXISTS content_editor_drafts (
    draft_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    page_key CHAR(64) NOT NULL,
    revision INT UNSIGNED NOT NULL DEFAULT 1,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY owner_page (user_id, page_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS content_editor_draft_chunks (
    draft_id BIGINT UNSIGNED NOT NULL,
    chunk_no INT UNSIGNED NOT NULL,
    payload MEDIUMBLOB NOT NULL,
    PRIMARY KEY (draft_id, chunk_no),
    CONSTRAINT editor_draft_chunks_parent FOREIGN KEY (draft_id)
        REFERENCES content_editor_drafts(draft_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
