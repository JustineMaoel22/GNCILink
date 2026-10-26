-- Additive migration. Run once before deploying the onboarding feature.
-- Account records and authentication credentials are unchanged.
CREATE TABLE IF NOT EXISTS admin_onboarding (
    user_id INT NOT NULL PRIMARY KEY,
    status VARCHAR(16) NOT NULL,
    finished_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
