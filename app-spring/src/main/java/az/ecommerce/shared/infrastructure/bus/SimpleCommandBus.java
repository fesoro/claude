package az.ecommerce.shared.infrastructure.bus;

import az.ecommerce.shared.application.bus.Command;
import az.ecommerce.shared.application.bus.CommandBus;
import az.ecommerce.shared.application.bus.CommandHandler;
import az.ecommerce.shared.application.middleware.CommandMiddleware;
import az.ecommerce.shared.application.middleware.CommandPipeline;
import org.springframework.context.ApplicationContext;
import org.springframework.core.GenericTypeResolver;
import org.springframework.stereotype.Component;

import java.util.Comparator;
import java.util.HashMap;
import java.util.List;
import java.util.Map;

/**
 * Laravel: src/Shared/Infrastructure/Bus/SimpleCommandBus.php
 *
 * Spring: bütün CommandHandler-ləri ApplicationContext-dən tapır,
 * command tipi → handler map-i qurur (lazy initialization).
 *
 * Middleware-ləri @Order-ə görə sıralayır və pipeline yaradır:
 *   Logging → Idempotency → Validation → Transaction → RetryOnConcurrency → Handler
 */
@Component
public class SimpleCommandBus implements CommandBus {

    private final ApplicationContext context;
    private final List<CommandMiddleware> middlewares;
    private final Map<Class<?>, CommandHandler<?, ?>> handlersByCommand = new HashMap<>();
    private volatile boolean initialized = false;

    public SimpleCommandBus(ApplicationContext context, List<CommandMiddleware> middlewares) {
        this.context = context;
        this.middlewares = middlewares.stream()
                .sorted(Comparator.comparingInt(CommandMiddleware::order))
                .toList();
    }

    @Override
    @SuppressWarnings({"unchecked", "rawtypes"})
    public <R> R dispatch(Command<R> command) {
        ensureInitialized();

        CommandPipeline<R> finalPipeline = cmd -> {
            CommandHandler handler = handlersByCommand.get(cmd.getClass());
            if (handler == null) {
                throw new IllegalStateException("Command üçün handler tapılmadı: " + cmd.getClass().getName());
            }
            return (R) handler.handle(cmd);
        };

        // Middleware-ləri əks sıra ilə bürüyürük (decorator pattern)
        CommandPipeline<R> pipeline = finalPipeline;
        for (int i = middlewares.size() - 1; i >= 0; i--) {
            CommandMiddleware mw = middlewares.get(i);
            CommandPipeline<R> next = pipeline;
            pipeline = cmd -> mw.handle(cmd, next);
        }

        return pipeline.proceed(command);
    }

    private void ensureInitialized() {
        if (initialized) return;
        synchronized (this) {
            if (initialized) return;
            for (String name : context.getBeanNamesForType(CommandHandler.class)) {
                CommandHandler<?, ?> bean = context.getBean(name, CommandHandler.class);
                Class<?>[] generics = GenericTypeResolver.resolveTypeArguments(bean.getClass(), CommandHandler.class);
                if (generics != null && generics.length >= 1 && generics[0] != null) {
                    handlersByCommand.put(generics[0], bean);
                }
            }
            initialized = true;
        }
    }
}
