-- Shared with the existing Tuition and Fees / Online Payment Guide editors.
-- Additive and safe to repeat; existing content remains unchanged.
CREATE TABLE IF NOT EXISTS tuition_fee_content (
    field_key VARCHAR(80) PRIMARY KEY,
    content TEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
