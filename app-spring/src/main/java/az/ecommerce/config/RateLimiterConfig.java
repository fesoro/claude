package az.ecommerce.config;

import io.github.bucket4j.Bandwidth;
import io.github.bucket4j.Bucket;
import org.springframework.boot.context.properties.ConfigurationProperties;
import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;

import java.time.Duration;
import java.util.HashMap;
import java.util.Map;

/**
 * Laravel: AppServiceProvider.php → RateLimiter::for('register', ...)
 * Spring: Bucket4j — token bucket algoritması.
 */
@Configuration
@ConfigurationProperties(prefix = "app.rate-limit")
public class RateLimiterConfig {

    private int register = 3;
    private int login = 5;
    private int products = 60;
    private int orders = 30;
    private int payment = 10;
    private int apiDefault = 60;

    @Bean
    public Map<String, Bucket> rateLimitBuckets() {
        Map<String, Bucket> buckets = new HashMap<>();
        buckets.put("register", bucket(register));
        buckets.put("login", bucket(login));
        buckets.put("products", bucket(products));
        buckets.put("orders", bucket(orders));
        buckets.put("payment", bucket(payment));
        buckets.put("api-default", bucket(apiDefault));
        return buckets;
    }

    private Bucket bucket(int requestsPerMinute) {
        return Bucket.builder()
                .addLimit(Bandwidth.builder().capacity(requestsPerMinute)
                        .refillIntervally(requestsPerMinute, Duration.ofMinutes(1)).build())
                .build();
    }

    public void setRegister(int v) { this.register = v; }
    public void setLogin(int v) { this.login = v; }
    public void setProducts(int v) { this.products = v; }
    public void setOrders(int v) { this.orders = v; }
    public void setPayment(int v) { this.payment = v; }
    public void setApiDefault(int v) { this.apiDefault = v; }
}
