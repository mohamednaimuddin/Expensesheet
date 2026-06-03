-- Activity Log Table Setup
-- Run this on your VPS database

CREATE TABLE IF NOT EXISTS activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    username VARCHAR(100),
    company_id INT,
    company_name VARCHAR(100),
    action VARCHAR(50),
    description TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_company (company_id),
    INDEX idx_action (action),
    INDEX idx_created (created_at)
);

-- Optional: If you need to add company_id to users table (if not already done)
-- ALTER TABLE users ADD COLUMN company_id INT DEFAULT NULL;
-- ALTER TABLE users ADD FOREIGN KEY (company_id) REFERENCES companies(id);
