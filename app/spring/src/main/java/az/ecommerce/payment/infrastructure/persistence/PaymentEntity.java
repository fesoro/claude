package az.ecommerce.payment.infrastructure.persistence;

import jakarta.persistence.*;

import java.time.Instant;
import java.util.UUID;

@Entity
@Table(name = "payments")
public class PaymentEntity {

    @Id
    @Column(columnDefinition = "CHAR(36)")
    private UUID id;

    @Column(name = "order_id", nullable = false, columnDefinition = "CHAR(36)")
    private UUID orderId;

    @Column(name = "user_id", nullable = false, columnDefinition = "CHAR(36)")
    private UUID userId;

    @Column(nullable = false)
    private long amount;

    @Column(nullable = false, length = 3)
    private String currency;

    @Column(name = "payment_method", nullable = false, length = 32)
    private String paymentMethod;

    @Column(nullable = false, length = 32)
    private String status;

    @Column(name = "transaction_id", length = 128)
    private String transactionId;

    @Column(name = "failure_reason", length = 512)
    private String failureReason;

    @Version
    private Long version;

    @Column(name = "created_at", nullable = false, updatable = false)
    private Instant createdAt = Instant.now();

    @Column(name = "updated_at")
    private Instant updatedAt = Instant.now();

    @Column(name = "completed_at")
    private Instant completedAt;

    @PreUpdate
    void onUpdate() { this.updatedAt = Instant.now(); }

    public UUID getId() { return id; }
    public void setId(UUID id) { this.id = id; }
    public UUID getOrderId() { return orderId; }
    public void setOrderId(UUID o) { this.orderId = o; }
    public UUID getUserId() { return userId; }
    public void setUserId(UUID u) { this.userId = u; }
    public long getAmount() { return amount; }
    public void setAmount(long a) { this.amount = a; }
    public String getCurrency() { return currency; }
    public void setCurrency(String c) { this.currency = c; }
    public String getPaymentMethod() { return paymentMethod; }
    public void setPaymentMethod(String m) { this.paymentMethod = m; }
    public String getStatus() { return status; }
    public void setStatus(String s) { this.status = s; }
    public String getTransactionId() { return transactionId; }
    public void setTransactionId(String t) { this.transactionId = t; }
    public String getFailureReason() { return failureReason; }
    public void setFailureReason(String r) { this.failureReason = r; }
}
