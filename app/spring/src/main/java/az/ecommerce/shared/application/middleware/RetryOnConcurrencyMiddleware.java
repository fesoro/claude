package az.ecommerce.shared.application.middleware;

import az.ecommerce.shared.application.bus.Command;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;
import org.springframework.orm.ObjectOptimisticLockingFailureException;
import org.springframework.stereotype.Component;

/**
 * Pipeline mövqeyi: 5-ci (ən daxili middleware, handler-dan əvvəl).
 * @Version optimistic locking conflict-i tutur və 3 dəfəyə qədər retry edir.
 * Laravel: RetryOnConcurrencyMiddleware.php
 */
@Component
public class RetryOnConcurrencyMiddleware implements CommandMiddleware {

    private static final Logger log = LoggerFactory.getLogger(RetryOnConcurrencyMiddleware.class);
    private static final int MAX_ATTEMPTS = 3;

    @Override
    public <R> R handle(Command<R> command, CommandPipeline<R> next) {
        ObjectOptimisticLockingFailureException last = null;
        for (int attempt = 1; attempt <= MAX_ATTEMPTS; attempt++) {
            try {
                return next.proceed(command);
            } catch (ObjectOptimisticLockingFailureException ex) {
                last = ex;
                log.warn("Optimistic lock conflict {}/{}: {}", attempt, MAX_ATTEMPTS, ex.getMessage());
                try {
                    Thread.sleep(50L * attempt);
                } catch (InterruptedException ie) {
                    Thread.currentThread().interrupt();
                    throw ex;
                }
            }
        }
        throw last;
    }

    @Override
    public int order() {
        return 50;
    }
}
