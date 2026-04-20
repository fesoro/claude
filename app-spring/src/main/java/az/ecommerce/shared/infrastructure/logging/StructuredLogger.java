package az.ecommerce.shared.infrastructure.logging;

import org.slf4j.MDC;
import org.springframework.stereotype.Component;

import java.util.Map;

/**
 * Laravel: src/Shared/Infrastructure/Logging/StructuredLogger.php (JSON format)
 * Spring: SLF4J + logstash-logback-encoder (logback-spring.xml-də konfiqurasiya).
 * MDC (Mapped Diagnostic Context) ilə context dataları log-a düşür.
 *
 * NÜMUNƏ:
 *   structuredLogger.withContext(Map.of("orderId", id), () -> {
 *       log.info("Sifariş işlənir");  // log JSON: { "orderId": "...", "msg": "Sifariş işlənir" }
 *   });
 */
@Component
public class StructuredLogger {

    public void withContext(Map<String, String> context, Runnable action) {
        Map<String, String> backup = MDC.getCopyOfContextMap();
        try {
            context.forEach(MDC::put);
            action.run();
        } finally {
            MDC.clear();
            if (backup != null) MDC.setContextMap(backup);
        }
    }

    public void put(String key, String value) {
        MDC.put(key, value);
    }

    public void clear() {
        MDC.clear();
    }
}
