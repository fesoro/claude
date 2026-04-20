package az.ecommerce.shared.infrastructure.cache;

import org.springframework.data.redis.core.StringRedisTemplate;
import org.springframework.stereotype.Service;

import java.time.Duration;
import java.util.Set;

/**
 * Laravel: src/Shared/Infrastructure/Cache/TaggedCacheService.php
 *   - Cache::tags(['products', 'product:'.$id])->put(...)
 *   - Cache::tags(['products'])->flush()  ← invalidation
 *
 * Spring: Native Spring Cache tag dəstəkləmir, ona görə Redis SET-lər ilə
 * tag-key map qururuq.
 *
 *   Redis structure:
 *     tag:products → SET[key1, key2, key3, ...]
 *     <key>        → cache value
 */
@Service
public class TaggedCacheService {

    private final StringRedisTemplate redis;

    public TaggedCacheService(StringRedisTemplate redis) {
        this.redis = redis;
    }

    public void put(String key, String value, Duration ttl, String... tags) {
        redis.opsForValue().set(key, value, ttl);
        for (String tag : tags) {
            redis.opsForSet().add("tag:" + tag, key);
        }
    }

    public String get(String key) {
        return redis.opsForValue().get(key);
    }

    /**
     * Bütün tag-altındakı cache-ləri invalidate edir.
     * Laravel: Cache::tags(['products'])->flush()
     */
    public void invalidateTag(String tag) {
        Set<String> keys = redis.opsForSet().members("tag:" + tag);
        if (keys != null && !keys.isEmpty()) {
            redis.delete(keys);
        }
        redis.delete("tag:" + tag);
    }
}
