package az.ecommerce.shared.application.middleware;

import az.ecommerce.shared.application.bus.Command;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;
import org.springframework.stereotype.Component;

/**
 * Pipeline mövqeyi: 1-ci (ən xarici).
 * Hər command-ı və nəticəsini loga yazır.
 * Laravel: LoggingMiddleware.php
 */
@Component
public class LoggingMiddleware implements CommandMiddleware {

    private static final Logger log = LoggerFactory.getLogger(LoggingMiddleware.class);

    @Override
    public <R> R handle(Command<R> command, CommandPipeline<R> next) {
        long start = System.currentTimeMillis();
        log.info("[CMD] {} başladı", command.getClass().getSimpleName());
        try {
            R result = next.proceed(command);
            log.info("[CMD] {} bitdi ({}ms)",
                    command.getClass().getSimpleName(), System.currentTimeMillis() - start);
            return result;
        } catch (Exception ex) {
            log.error("[CMD] {} XƏTA: {}", command.getClass().getSimpleName(), ex.getMessage(), ex);
            throw ex;
        }
    }

    @Override
    public int order() {
        return 10;
    }
}
