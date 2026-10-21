<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/config.php';
getDB()->exec('CREATE TABLE IF NOT EXISTS department_page_content (
    dept_id INT NOT NULL,
    field_key VARCHAR(80) NOT NULL,
    content TEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (dept_id, field_key),
    CONSTRAINT department_page_content_department FOREIGN KEY (dept_id) REFERENCES departments(dept_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
echo "Department page content schema ready. Existing content preserved.\n";
