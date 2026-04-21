package az.ecommerce.order.infrastructure.readmodel;

import jakarta.persistence.*;

import java.time.Instant;
import java.util.UUID;

/**
 * === CQRS READ MODEL ===
 * Laravel: OrderReadModel — denormalized projection.
 *
 * Sifariş və user adı eyni cədvəldə (JOIN olmadan sürətli oxu).
 * OrderListProjector domain event-ləri tutub bu cədvəli yeniləyir.
 */
@Entity
@Table(name = "order_read_model")
public class OrderReadModelEntity {

    @Id
    @Column(name = "order_id", columnDefinition = "CHAR(36)")
    private UUID orderId;

    @Column(name = "user_id", nullable = false, columnDefinition = "CHAR(36)")
    private UUID userId;

    @Column(name = "user_name")
    private String userName;

    @Column(name = "user_email")
    private String userEmail;

    @Column(nullable = false, length = 32)
    private String status;

    @Column(name = "total_amount", nullable = false)
    private long totalAmount;

    @Column(name = "total_currency", nullable = false, length = 3)
    private String totalCurrency;

    @Column(name = "item_count", nullable = false)
    private int itemCount;

    @Column(name = "items_summary", columnDefinition = "JSON")
    private String itemsSummary;

    @Column(name = "address_summary", length = 512)
    private String addressSummary;

    @Column(name = "last_updated_at", nullable = false)
    private Instant lastUpdatedAt = Instant.now();

    public UUID getOrderId() { return orderId; }
    public void setOrderId(UUID id) { this.orderId = id; }
    public UUID getUserId() { return userId; }
    public void setUserId(UUID id) { this.userId = id; }
    public String getUserName() { return userName; }
    public void setUserName(String n) { this.userName = n; }
    public String getStatus() { return status; }
    public void setStatus(String s) { this.status = s; }
    public long getTotalAmount() { return totalAmount; }
    public void setTotalAmount(long a) { this.totalAmount = a; }
    public String getTotalCurrency() { return totalCurrency; }
    public void setTotalCurrency(String c) { this.totalCurrency = c; }
    public int getItemCount() { return itemCount; }
    public void setItemCount(int c) { this.itemCount = c; }
    public void setLastUpdatedAt(Instant t) { this.lastUpdatedAt = t; }
}
