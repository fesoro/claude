package az.ecommerce.payment.infrastructure.circuitbreaker;

import jakarta.persistence.*;

import java.time.Instant;

/**
 * Laravel: CircuitBreaker.php → circuit_breaker_states cədvəli
 * Migration: payment/V2__create_circuit_breaker_states.sql
 *
 * Spring: Resilience4j default in-memory işləyir, amma multi-instance app
 * üçün state-i DB-də paylaşmaq lazımdır. Bu entity cluster-dağıtık
 * scenarios üçündür.
 */
@Entity
@Table(name = "circuit_breaker_states")
public class CircuitBreakerStateEntity {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    @Column(name = "service_name", nullable = false, unique = true, length = 128)
    private String serviceName;

    @Column(nullable = false, length = 16)
    private String state = "CLOSED";   // CLOSED | OPEN | HALF_OPEN

    @Column(name = "failure_count", nullable = false)
    private int failureCount = 0;

    @Column(name = "last_failure_at")
    private Instant lastFailureAt;

    @Column(name = "next_attempt_at")
    private Instant nextAttemptAt;

    @Column(name = "last_state_change_at")
    private Instant lastStateChangeAt = Instant.now();

    public Long getId() { return id; }
    public String getServiceName() { return serviceName; }
    public void setServiceName(String s) { this.serviceName = s; }
    public String getState() { return state; }
    public void setState(String s) { this.state = s; this.lastStateChangeAt = Instant.now(); }
    public int getFailureCount() { return failureCount; }
    public void incrementFailure() { this.failureCount++; this.lastFailureAt = Instant.now(); }
    public void resetFailures() { this.failureCount = 0; }
    public Instant getNextAttemptAt() { return nextAttemptAt; }
    public void setNextAttemptAt(Instant t) { this.nextAttemptAt = t; }
}
