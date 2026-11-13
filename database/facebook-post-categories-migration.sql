-- Additive manual tags; retain all primary categories and automatic/manual modes.
CREATE TABLE IF NOT EXISTS fb_post_categories (
    fb_post_id VARCHAR(64) NOT NULL,
    category_id INT NOT NULL,
    PRIMARY KEY (fb_post_id, category_id),
    KEY idx_fb_post_category (category_id, fb_post_id),
    CONSTRAINT fk_fb_category_post FOREIGN KEY (fb_post_id) REFERENCES fb_posts_cache (fb_post_id) ON DELETE CASCADE,
    CONSTRAINT fk_fb_category_tag FOREIGN KEY (category_id) REFERENCES categories (category_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO fb_post_categories(fb_post_id,category_id)
SELECT f.fb_post_id,f.category_id FROM fb_posts_cache f JOIN categories c ON c.category_id=f.category_id WHERE f.category_locked=1;
