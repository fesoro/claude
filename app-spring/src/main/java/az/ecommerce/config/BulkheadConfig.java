package az.ecommerce.config;

import io.github.resilience4j.bulkhead.Bulkhead;
import io.github.resilience4j.bulkhead.BulkheadConfig.Builder;
import io.github.resilience4j.bulkhead.BulkheadRegistry;
import org.springframework.beans.factory.annotation.Value;
import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;

/**
 * Laravel: BulkheadPattern.php (DomainServiceProvider-də konfiqurasiya)
 *   - payment: 20 concurrent
 *   - notification: 50 concurrent
 *
 * Spring: Resilience4j Bulkhead — semaphore-based concurrent call limit.
 */
@Configuration
public class BulkheadConfig {

    @Bean
    public Bulkhead paymentBulkhead(@Value("${app.bulkhead.payment-concurrent:20}") int max) {
        return Bulkhead.of("paymentBulkhead",
                io.github.resilience4j.bulkhead.BulkheadConfig.custom()
                        .maxConcurrentCalls(max).build());
    }

    @Bean
    public Bulkhead notificationBulkhead(@Value("${app.bulkhead.notification-concurrent:50}") int max) {
        return Bulkhead.of("notificationBulkhead",
                io.github.resilience4j.bulkhead.BulkheadConfig.custom()
                        .maxConcurrentCalls(max).build());
    }
}
