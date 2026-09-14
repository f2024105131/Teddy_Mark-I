-- ============================================================
-- LAUNDRY MANAGEMENT SYSTEM — APPLICATION DB USER
-- Author: Rohan
-- Purpose: Least-privilege MySQL user for the PHP app.
-- Run as: mysql -u root -p < create_app_user.sql
-- ============================================================

DROP USER IF EXISTS 'laundry_app'@'localhost';

CREATE USER 'laundry_app'@'localhost'
    IDENTIFIED BY 'Laundry@2025#App';

GRANT SELECT, INSERT, UPDATE, DELETE
    ON laundry_management.*
    TO 'laundry_app'@'localhost';

FLUSH PRIVILEGES;

SHOW GRANTS FOR 'laundry_app'@'localhost';

-- ============================================================
-- Update app/Config/database.php to use:
--   'username' => 'laundry_app',
--   'password' => 'Laundry@2025#App',
-- ============================================================