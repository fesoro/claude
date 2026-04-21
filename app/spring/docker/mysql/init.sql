-- Laravel-də DDD bounded context-lər üçün 4 ayrı DB
-- (Database-per-Bounded-Context pattern)
CREATE DATABASE IF NOT EXISTS user_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS product_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS order_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS payment_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

GRANT ALL PRIVILEGES ON user_db.* TO 'laravel'@'%';
GRANT ALL PRIVILEGES ON product_db.* TO 'laravel'@'%';
GRANT ALL PRIVILEGES ON order_db.* TO 'laravel'@'%';
GRANT ALL PRIVILEGES ON payment_db.* TO 'laravel'@'%';
FLUSH PRIVILEGES;
