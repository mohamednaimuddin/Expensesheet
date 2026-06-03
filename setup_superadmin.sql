-- =====================================================
-- Super Admin Database Setup for Expense Management
-- =====================================================
-- Run these queries in phpMyAdmin or MySQL command line

-- 1. Create companies table (if not exists)
CREATE TABLE IF NOT EXISTS `companies` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `company_name` VARCHAR(255) NOT NULL,
    `company_code` VARCHAR(50) NOT NULL UNIQUE,
    `address` TEXT DEFAULT NULL,
    `phone` VARCHAR(50) DEFAULT NULL,
    `email` VARCHAR(100) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Update users table to add company_id if not exists
-- Check if column exists first, if not add it
ALTER TABLE `users` 
ADD COLUMN IF NOT EXISTS `company_id` INT(11) DEFAULT NULL;

-- 3. Add foreign key constraint (optional but recommended)
-- ALTER TABLE `users` ADD CONSTRAINT `fk_user_company` 
-- FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE SET NULL;

-- 4. Insert sample companies
INSERT INTO `companies` (`company_name`, `company_code`, `address`, `phone`, `email`) VALUES
('Vision Angles Security EST - Riyadh', 'VA-RYD', 'Riyadh, Saudi Arabia', '+966-11-XXXXXXX', 'riyadh@visionangles.com'),
('Vision Angles Security EST - Jeddah', 'VA-JED', 'Jeddah, Saudi Arabia', '+966-12-XXXXXXX', 'jeddah@visionangles.com');

-- 5. Create super admin user (password: superadmin123)
-- The password hash below is for 'superadmin123' - change this after first login!
INSERT INTO `users` (`full_name`, `username`, `password`, `email`, `number`, `role`, `company_id`) VALUES
('Super Administrator', 'superadmin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'superadmin@visionangles.com', '+966-XXXXXXXXX', 'superadmin', NULL);

-- Note: The default password is 'password' (Laravel default hash)
-- You should change this immediately after setup!

-- =====================================================
-- To change super admin password, run this query:
-- UPDATE users SET password = '$2y$10$YOUR_NEW_HASH' WHERE username = 'superadmin';
--
-- To generate a new hash, use PHP:
-- echo password_hash('your_new_password', PASSWORD_DEFAULT);
-- =====================================================

-- 6. Update existing users to assign them to companies (example)
-- UPDATE users SET company_id = 1 WHERE company_id IS NULL AND role != 'superadmin';

-- =====================================================
-- VERIFICATION QUERIES
-- =====================================================

-- Check companies table
-- SELECT * FROM companies;

-- Check users with company info
-- SELECT u.*, c.company_name FROM users u LEFT JOIN companies c ON u.company_id = c.id;

-- Check super admin exists
-- SELECT * FROM users WHERE role = 'superadmin';
