package az.ecommerce.shared.application.middleware;

import az.ecommerce.shared.application.bus.Command;
import org.slf4j.MDC;
import org.springframework.data.redis.core.StringRedisTemplate;
import org.springframework.stereotype.Component;

import java.time.Duration;

/**
 * Pipeline mövqeyi: 2-ci.
 * Eyni idempotency_key ilə təkrar gələn command-i blok edir
 * (24 saat ərzində). Redis-də saxlayır.
 *
 * Laravel: IdempotencyMiddleware.php (DB-də saxlayır)
 *
 * Idempotency key request header-dən gəlir (X-Idempotency-Key) — IdempotencyInterceptor
 * onu MDC-yə yerləşdirir, biz buradan oxuyuruq.
 */
@Component
public class IdempotencyMiddleware implements CommandMiddleware {

    public static final String MDC_KEY = "idempotencyKey";
    private static final String REDIS_PREFIX = "cmd:idempotency:";
    private static final Duration TTL = Duration.ofHours(24);

    private final StringRedisTemplate redis;

    public IdempotencyMiddleware(StringRedisTemplate redis) {
        this.redis = redis;
    }

    @Override
    public <R> R handle(Command<R> command, CommandPipeline<R> next) {
        String key = MDC.get(MDC_KEY);
        if (key == null || key.isBlank()) {
            return next.proceed(command);
        }

        String redisKey = REDIS_PREFIX + key;
        Boolean firstTime = redis.opsForValue().setIfAbsent(redisKey, "processing", TTL);
        if (Boolean.FALSE.equals(firstTime)) {
            throw new az.ecommerce.shared.domain.exception.DomainException(
                    "Bu idempotency key artıq istifadə olunub: " + key);
        }

        try {
            R result = next.proceed(command);
            redis.opsForValue().set(redisKey, "completed", TTL);
            return result;
        } catch (Exception ex) {
            redis.delete(redisKey);
            throw ex;
        }
    }

    @Override
    public int order() {
        return 20;
    }
}
