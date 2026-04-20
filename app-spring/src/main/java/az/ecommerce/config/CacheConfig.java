package az.ecommerce.config;

import com.fasterxml.jackson.databind.ObjectMapper;
import org.springframework.boot.context.properties.ConfigurationProperties;
import org.springframework.cache.CacheManager;
import org.springframework.cache.annotation.EnableCaching;
import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;
import org.springframework.data.redis.cache.RedisCacheConfiguration;
import org.springframework.data.redis.cache.RedisCacheManager;
import org.springframework.data.redis.connection.RedisConnectionFactory;
import org.springframework.data.redis.serializer.GenericJackson2JsonRedisSerializer;
import org.springframework.data.redis.serializer.RedisSerializationContext;
import org.springframework.data.redis.serializer.StringRedisSerializer;

import java.time.Duration;
import java.util.HashMap;
import java.util.Map;

/**
 * Laravel: config/cache.php — TTL-lər və cache key-lər.
 * Spring: Redis-based @Cacheable manager.
 */
@Configuration
@EnableCaching
@ConfigurationProperties(prefix = "app.cache")
public class CacheConfig {

    private Duration productTtl = Duration.ofMinutes(10);
    private Duration orderTtl = Duration.ofMinutes(5);
    private Duration userTtl = Duration.ofMinutes(15);

    @Bean
    public CacheManager cacheManager(RedisConnectionFactory factory, ObjectMapper mapper) {
        Map<String, RedisCacheConfiguration> configs = new HashMap<>();
        configs.put("products", baseConfig(productTtl, mapper));
        configs.put("orders", baseConfig(orderTtl, mapper));
        configs.put("users", baseConfig(userTtl, mapper));

        return RedisCacheManager.builder(factory)
                .cacheDefaults(baseConfig(Duration.ofMinutes(5), mapper))
                .withInitialCacheConfigurations(configs)
                .build();
    }

    private RedisCacheConfiguration baseConfig(Duration ttl, ObjectMapper mapper) {
        return RedisCacheConfiguration.defaultCacheConfig()
                .entryTtl(ttl)
                .serializeKeysWith(RedisSerializationContext.SerializationPair.fromSerializer(new StringRedisSerializer()))
                .serializeValuesWith(RedisSerializationContext.SerializationPair.fromSerializer(
                        new GenericJackson2JsonRedisSerializer(mapper)));
    }

    public void setProductTtl(Duration v) { this.productTtl = v; }
    public void setOrderTtl(Duration v) { this.orderTtl = v; }
    public void setUserTtl(Duration v) { this.userTtl = v; }
}
