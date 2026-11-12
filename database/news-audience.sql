-- Additive: existing news and publication states are unchanged.
CREATE TABLE IF NOT EXISTS news_departments (
    news_id INT NOT NULL,
    dept_id INT NOT NULL,
    PRIMARY KEY (news_id, dept_id),
    KEY idx_news_audience_department (dept_id),
    CONSTRAINT fk_news_audience_department_news FOREIGN KEY (news_id) REFERENCES news(news_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS news_programs (
    news_id INT NOT NULL,
    program_code VARCHAR(20) NOT NULL,
    PRIMARY KEY (news_id, program_code),
    KEY idx_news_audience_program (program_code),
    CONSTRAINT fk_news_audience_program_news FOREIGN KEY (news_id) REFERENCES news(news_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
