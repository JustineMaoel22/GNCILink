-- Back up before importing. Existing accounts and content are retained.
-- Legacy role values remain stored; runtime treats Content Admin as Content Editor,
-- and Office Staff as The Mirror. New assignments offer only the three current roles.
ALTER TABLE users MODIFY role ENUM('Super Admin','Content Admin','Content Editor','Office Staff','The Mirror') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Content Editor';
CREATE TABLE IF NOT EXISTS rbac_submissions (
 submission_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
 user_id INT NOT NULL,
 kind VARCHAR(30) NOT NULL,
 route VARCHAR(150) NOT NULL,
 target_id INT NULL,
 title VARCHAR(500) NOT NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'pending',
 revision INT NOT NULL DEFAULT 1,
 reason TEXT NULL,
 reviewed_by INT NULL,
 reviewed_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX rbac_owner_status (user_id,status),
 INDEX rbac_review_status (status,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS rbac_submission_chunks (
 submission_id INT NOT NULL,
 chunk_no INT NOT NULL,
 payload MEDIUMBLOB NOT NULL,
 PRIMARY KEY(submission_id,chunk_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
