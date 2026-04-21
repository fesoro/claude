package az.ecommerce.config;

import org.springframework.context.annotation.Configuration;

/**
 * Laravel: config/database.php — redis client.
 *
 * Spring:
 *   - spring-data-redis Spring Boot tərəfindən avtomatik konfiqurasiya olunur
 *     (RedisConnectionFactory, StringRedisTemplate, RedisTemplate)
 *   - redisson-spring-boot-starter avtomatik RedissonClient yaradır
 *   - application.yml-də spring.data.redis.host/port kifayətdir
 *
 * Bu class struktur üçün boş saxlanır.
 */
@Configuration
public class RedisConfig {
}
