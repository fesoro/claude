package az.ecommerce.health;

import az.ecommerce.shared.infrastructure.api.ApiResponse;
import org.springframework.amqp.rabbit.core.RabbitTemplate;
import org.springframework.data.redis.core.StringRedisTemplate;
import org.springframework.http.HttpStatus;
import org.springframework.http.ResponseEntity;
import org.springframework.jdbc.core.JdbcTemplate;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.RestController;

import javax.sql.DataSource;
import java.util.HashMap;
import java.util.Map;

/**
 * Laravel: app/Http/Controllers/HealthCheckController.php
 *
 * Spring-də Actuator (/actuator/health) artıq bunu edir, amma Laravel-ə
 * uyğun /api/health endpoint-i də əlavə edirik.
 */
@RestController
@RequestMapping("/api/health")
public class HealthCheckController {

    private final DataSource userDs, productDs, orderDs, paymentDs;
    private final StringRedisTemplate redis;
    private final RabbitTemplate rabbit;

    public HealthCheckController(
            @org.springframework.beans.factory.annotation.Qualifier("userDataSource") DataSource userDs,
            @org.springframework.beans.factory.annotation.Qualifier("productDataSource") DataSource productDs,
            @org.springframework.beans.factory.annotation.Qualifier("orderDataSource") DataSource orderDs,
            @org.springframework.beans.factory.annotation.Qualifier("paymentDataSource") DataSource paymentDs,
            StringRedisTemplate redis, RabbitTemplate rabbit) {
        this.userDs = userDs; this.productDs = productDs;
        this.orderDs = orderDs; this.paymentDs = paymentDs;
        this.redis = redis; this.rabbit = rabbit;
    }

    @GetMapping
    public ResponseEntity<ApiResponse<Map<String, Object>>> full() {
        Map<String, Object> checks = new HashMap<>();
        checks.put("user_db", checkDb(userDs));
        checks.put("product_db", checkDb(productDs));
        checks.put("order_db", checkDb(orderDs));
        checks.put("payment_db", checkDb(paymentDs));
        checks.put("redis", checkRedis());
        checks.put("rabbitmq", checkRabbit());

        boolean allOk = checks.values().stream().allMatch(v -> "UP".equals(v));
        var status = allOk ? HttpStatus.OK : HttpStatus.SERVICE_UNAVAILABLE;
        return ResponseEntity.status(status).body(ApiResponse.success(checks));
    }

    @GetMapping("/live")
    public ApiResponse<String> liveness() { return ApiResponse.success("UP"); }

    @GetMapping("/ready")
    public ResponseEntity<ApiResponse<String>> readiness() {
        boolean ok = "UP".equals(checkDb(userDs)) && "UP".equals(checkRedis());
        var status = ok ? HttpStatus.OK : HttpStatus.SERVICE_UNAVAILABLE;
        return ResponseEntity.status(status).body(ApiResponse.success(ok ? "READY" : "NOT_READY"));
    }

    private String checkDb(DataSource ds) {
        try { new JdbcTemplate(ds).queryForObject("SELECT 1", Integer.class); return "UP"; }
        catch (Exception e) { return "DOWN: " + e.getMessage(); }
    }

    private String checkRedis() {
        try {
            redis.execute(conn -> conn.ping());
            return "UP";
        } catch (Exception e) { return "DOWN: " + e.getMessage(); }
    }

    private String checkRabbit() {
        try { rabbit.getConnectionFactory().createConnection().close(); return "UP"; }
        catch (Exception e) { return "DOWN: " + e.getMessage(); }
    }
}
