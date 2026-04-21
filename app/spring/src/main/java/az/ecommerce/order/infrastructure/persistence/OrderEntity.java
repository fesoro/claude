package az.ecommerce.order.infrastructure.persistence;

import jakarta.persistence.*;

import java.time.Instant;
import java.util.ArrayList;
import java.util.List;
import java.util.UUID;

@Entity
@Table(name = "orders")
public class OrderEntity {

    @Id
    @Column(columnDefinition = "CHAR(36)")
    private UUID id;

    @Column(name = "user_id", nullable = false, columnDefinition = "CHAR(36)")
    private UUID userId;

    @Column(nullable = false, length = 32)
    private String status;

    @Column(name = "total_amount", nullable = false)
    private long totalAmount;

    @Column(name = "total_currency", nullable = false, length = 3)
    private String totalCurrency;

    // Embedded Address (Laravel-də ayrı sütunlar)
    @Column(name = "address_street", nullable = false)
    private String addressStreet;
    @Column(name = "address_city", nullable = false)
    private String addressCity;
    @Column(name = "address_zip", nullable = false)
    private String addressZip;
    @Column(name = "address_country", nullable = false)
    private String addressCountry;

    @Version
    private Long version;

    @Column(name = "tenant_id", columnDefinition = "CHAR(36)")
    private UUID tenantId;

    @Column(name = "created_at", nullable = false, updatable = false)
    private Instant createdAt = Instant.now();

    @Column(name = "updated_at")
    private Instant updatedAt = Instant.now();

    @OneToMany(mappedBy = "order", cascade = CascadeType.ALL, orphanRemoval = true, fetch = FetchType.EAGER)
    private List<OrderItemEntity> items = new ArrayList<>();

    @PreUpdate
    void onUpdate() { this.updatedAt = Instant.now(); }

    public UUID getId() { return id; }
    public void setId(UUID id) { this.id = id; }
    public UUID getUserId() { return userId; }
    public void setUserId(UUID id) { this.userId = id; }
    public String getStatus() { return status; }
    public void setStatus(String s) { this.status = s; }
    public long getTotalAmount() { return totalAmount; }
    public void setTotalAmount(long a) { this.totalAmount = a; }
    public String getTotalCurrency() { return totalCurrency; }
    public void setTotalCurrency(String c) { this.totalCurrency = c; }
    public String getAddressStreet() { return addressStreet; }
    public void setAddressStreet(String s) { this.addressStreet = s; }
    public String getAddressCity() { return addressCity; }
    public void setAddressCity(String s) { this.addressCity = s; }
    public String getAddressZip() { return addressZip; }
    public void setAddressZip(String s) { this.addressZip = s; }
    public String getAddressCountry() { return addressCountry; }
    public void setAddressCountry(String s) { this.addressCountry = s; }
    public List<OrderItemEntity> getItems() { return items; }
    public void setItems(List<OrderItemEntity> items) { this.items = items; }
}
