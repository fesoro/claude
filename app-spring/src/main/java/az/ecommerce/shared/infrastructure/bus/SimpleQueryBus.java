package az.ecommerce.shared.infrastructure.bus;

import az.ecommerce.shared.application.bus.Query;
import az.ecommerce.shared.application.bus.QueryBus;
import az.ecommerce.shared.application.bus.QueryHandler;
import org.springframework.context.ApplicationContext;
import org.springframework.core.GenericTypeResolver;
import org.springframework.stereotype.Component;

import java.util.HashMap;
import java.util.Map;

/**
 * Laravel: SimpleQueryBus.php
 *
 * Query-lərdə middleware yoxdur (sadə, idempotent, side-effect-siz).
 * Cache-ı handler özü idarə edir (CachedProductRepository decorator-ı vasitəsilə).
 */
@Component
public class SimpleQueryBus implements QueryBus {

    private final ApplicationContext context;
    private final Map<Class<?>, QueryHandler<?, ?>> handlersByQuery = new HashMap<>();
    private volatile boolean initialized = false;

    public SimpleQueryBus(ApplicationContext context) {
        this.context = context;
    }

    @Override
    @SuppressWarnings({"unchecked", "rawtypes"})
    public <R> R ask(Query<R> query) {
        ensureInitialized();
        QueryHandler handler = handlersByQuery.get(query.getClass());
        if (handler == null) {
            throw new IllegalStateException("Query üçün handler tapılmadı: " + query.getClass().getName());
        }
        return (R) handler.handle(query);
    }

    private void ensureInitialized() {
        if (initialized) return;
        synchronized (this) {
            if (initialized) return;
            for (String name : context.getBeanNamesForType(QueryHandler.class)) {
                QueryHandler<?, ?> bean = context.getBean(name, QueryHandler.class);
                Class<?>[] generics = GenericTypeResolver.resolveTypeArguments(bean.getClass(), QueryHandler.class);
                if (generics != null && generics.length >= 1 && generics[0] != null) {
                    handlersByQuery.put(generics[0], bean);
                }
            }
            initialized = true;
        }
    }
}
