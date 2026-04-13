-- =============================================================================
-- MySQL İlkin Quraşdırma Skripti
-- =============================================================================
--
-- Bu skript MySQL konteyneri İLK DƏFƏ yarananda avtomatik icra olunur.
-- /docker-entrypoint-initdb.d/ qovluğuna qoyulan SQL faylları MySQL tərəfindən
-- avtomatik oxunur və icra edilir.
--
-- Niyə ayrı-ayrı verilənlər bazaları?
-- ------------------------------------
-- DDD (Domain-Driven Design) yanaşmasında hər "bounded context" (məhdud kontekst)
-- öz verilənlər bazasına malik olmalıdır. Bunun səbəbləri:
--
-- 1. Ayrılıq (Separation of Concerns):
--    Hər kontekst yalnız öz verilənlərini bilir. Məhsul konteksti istifadəçi
--    cədvəllərinə birbaşa müraciət edə bilməz — yalnız API vasitəsilə.
--
-- 2. Müstəqil inkişaf:
--    Hər komanda öz verilənlər bazası sxemini müstəqil dəyişə bilər.
--    Bir kontekstdəki dəyişiklik digərini pozmur.
--
-- 3. Müstəqil miqyaslama (Scaling):
--    Sifariş bazası çox yüklənibsə, yalnız onu ayrı serverə köçürmək olar.
--    Digər bazalar eyni serverdə qala bilər.
--
-- 4. Mikro servis hazırlığı:
--    Gələcəkdə hər konteksti ayrı mikro servisə çevirmək asanlaşır,
--    çünki verilənlər bazası artıq ayrıdır.
--
-- Kontekstlər:
--   user_db    — İstifadəçi idarəetməsi (Authentication, Authorization, Profile)
--   product_db — Məhsul kataloqu (Product, Category, Inventory, Pricing)
--   order_db   — Sifariş idarəetməsi (Cart, Order, Shipping, OrderHistory)
--   payment_db — Ödəniş əməliyyatları (Transaction, Invoice, Refund)
-- =============================================================================

-- İstifadəçi konteksti üçün verilənlər bazası
-- Qeydiyyat, giriş, profil, rol və icazə məlumatları burada saxlanılır
CREATE DATABASE IF NOT EXISTS user_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Məhsul konteksti üçün verilənlər bazası
-- Məhsul, kateqoriya, inventar və qiymət məlumatları burada saxlanılır
CREATE DATABASE IF NOT EXISTS product_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Sifariş konteksti üçün verilənlər bazası
-- Səbət, sifariş, çatdırılma və sifariş tarixçəsi burada saxlanılır
CREATE DATABASE IF NOT EXISTS order_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Ödəniş konteksti üçün verilənlər bazası
-- Tranzaksiya, faktura və geri qaytarma məlumatları burada saxlanılır
CREATE DATABASE IF NOT EXISTS payment_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- İcazələrin verilməsi (Permissions)
-- -------------------------------------------------------
-- "laravel" istifadəçisinə bütün bazalara tam giriş veririk.
-- İstehsal (production) mühitində hər kontekst üçün ayrı istifadəçi yaradılmalıdır.
-- Məsələn: user_service yalnız user_db-yə, order_service yalnız order_db-yə daxil ola bilsin.
-- Bu, təhlükəsizliyi artırır — bir servisin sızması digər verilənlərə təsir etmir.
GRANT ALL PRIVILEGES ON user_db.* TO 'laravel'@'%';
GRANT ALL PRIVILEGES ON product_db.* TO 'laravel'@'%';
GRANT ALL PRIVILEGES ON order_db.* TO 'laravel'@'%';
GRANT ALL PRIVILEGES ON payment_db.* TO 'laravel'@'%';

-- Dəyişiklikləri tətbiq et
FLUSH PRIVILEGES;
