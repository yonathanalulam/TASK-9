-- Create the test database and grant permissions (runs on first MySQL init only)
CREATE DATABASE IF NOT EXISTS meridian_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON meridian_test.* TO 'meridian_app'@'%';
FLUSH PRIVILEGES;
