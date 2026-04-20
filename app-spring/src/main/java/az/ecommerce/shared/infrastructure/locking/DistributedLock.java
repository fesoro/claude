package az.ecommerce.shared.infrastructure.locking;

import org.redisson.api.RLock;
import org.redisson.api.RedissonClient;
import org.springframework.stereotype.Component;

import java.time.Duration;
import java.util.concurrent.Callable;
import java.util.concurrent.TimeUnit;

/**
 * Laravel: src/Shared/Infrastructure/Locking/DistributedLock.php (Redis SETNX)
 * Spring: Redisson kitabxanası — daha güclü distributed lock (lease, watchdog, fairness).
 *
 * NÜMUNƏ:
 *   distributedLock.executeLocked("payment:" + orderId, Duration.ofSeconds(30), () -> {
 *       processPayment(orderId);
 *       return null;
 *   });
 */
@Component
public class DistributedLock {

    private final RedissonClient redisson;

    public DistributedLock(RedissonClient redisson) {
        this.redisson = redisson;
    }

    public <T> T executeLocked(String lockKey, Duration leaseTime, Callable<T> action) {
        RLock lock = redisson.getLock("lock:" + lockKey);
        boolean acquired = false;
        try {
            acquired = lock.tryLock(5, leaseTime.getSeconds(), TimeUnit.SECONDS);
            if (!acquired) {
                throw new LockNotAcquiredException("Lock alına bilmədi: " + lockKey);
            }
            return action.call();
        } catch (InterruptedException e) {
            Thread.currentThread().interrupt();
            throw new LockNotAcquiredException("Lock interrupted: " + lockKey);
        } catch (Exception e) {
            throw new RuntimeException(e);
        } finally {
            if (acquired && lock.isHeldByCurrentThread()) {
                lock.unlock();
            }
        }
    }
}
