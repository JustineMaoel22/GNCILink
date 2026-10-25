-- Import once after backing up users. Existing accounts are not forced to change passwords.
ALTER TABLE users
 ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0,
 ADD COLUMN password_changed_at DATETIME NULL,
 ADD COLUMN credential_version INT UNSIGNED NOT NULL DEFAULT 1;
