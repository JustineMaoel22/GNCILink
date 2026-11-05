-- Additive topic tags. Preserve legacy category_id and all existing assignments.
CREATE TABLE IF NOT EXISTS announcement_categories (
    announcement_id INT NOT NULL,
    category_id INT NOT NULL,
    PRIMARY KEY (announcement_id, category_id),
    KEY idx_announcement_category (category_id, announcement_id),
    CONSTRAINT fk_announcement_category_post FOREIGN KEY (announcement_id) REFERENCES announcements (announcement_id) ON DELETE CASCADE,
    CONSTRAINT fk_announcement_category_tag FOREIGN KEY (category_id) REFERENCES categories (category_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO announcement_categories (announcement_id, category_id)
SELECT a.announcement_id, a.category_id FROM announcements a JOIN categories c ON c.category_id=a.category_id;
