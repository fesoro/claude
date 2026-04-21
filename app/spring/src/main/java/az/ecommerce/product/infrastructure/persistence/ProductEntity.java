package az.ecommerce.product.infrastructure.persistence;

import jakarta.persistence.*;

import java.time.Instant;
import java.util.UUID;

@Entity
@Table(name = "products")
public class ProductEntity {

    @Id
    @Column(columnDefinition = "CHAR(36)")
    private UUID id;

    @Column(nullable = false)
    private String name;

    @Column(columnDefinition = "TEXT")
    private String description;

    @Column(name = "price_amount", nullable = false)
    private long priceAmount;

    @Column(name = "price_currency", nullable = false, length = 3)
    private String priceCurrency;

    @Column(name = "stock_quantity", nullable = false)
    private int stockQuantity;

    @Version
    private Long version;

    @Column(name = "tenant_id", columnDefinition = "CHAR(36)")
    private UUID tenantId;

    @Column(name = "created_at", nullable = false, updatable = false)
    private Instant createdAt = Instant.now();

    @Column(name = "updated_at")
    private Instant updatedAt = Instant.now();

    @PreUpdate
    void onUpdate() { this.updatedAt = Instant.now(); }

    public UUID getId() { return id; }
    public void setId(UUID id) { this.id = id; }
    public String getName() { return name; }
    public void setName(String name) { this.name = name; }
    public String getDescription() { return description; }
    public void setDescription(String d) { this.description = d; }
    public long getPriceAmount() { return priceAmount; }
    public void setPriceAmount(long v) { this.priceAmount = v; }
    public String getPriceCurrency() { return priceCurrency; }
    public void setPriceCurrency(String c) { this.priceCurrency = c; }
    public int getStockQuantity() { return stockQuantity; }
    public void setStockQuantity(int v) { this.stockQuantity = v; }
    public Long getVersion() { return version; }
}
