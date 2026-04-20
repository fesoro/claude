package az.ecommerce.shared.application.middleware;

import az.ecommerce.shared.application.bus.Command;

/**
 * Pipeline-ın "next()" funksiyası. Middleware bunu çağırır ki, növbəti
 * middleware (yaxud son nəticədə Handler) işləsin.
 */
@FunctionalInterface
public interface CommandPipeline<R> {
    R proceed(Command<R> command);
}
