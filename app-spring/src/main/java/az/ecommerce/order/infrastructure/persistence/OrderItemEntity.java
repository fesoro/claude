package az.ecommerce.order.infrastructure.persistence;

import jakarta.persistence.*;

import java.util.UUID;

@Entity
@Table(name = "order_items")
public class OrderItemEntity {

    @Id
    @Column(columnDefinition = "CHAR(36)")
    private UUID id;

    @ManyToOne(fetch = FetchType.LAZY)
    @JoinColumn(name = "order_id", nullable = false)
    private OrderEntity order;

    @Column(name = "product_id", nullable = false, columnDefinition = "CHAR(36)")
    private UUID productId;

    @Column(name = "product_name", nullable = false)
    private String productName;

    @Column(name = "unit_price_amount", nullable = false)
    private long unitPriceAmount;

    @Column(name = "unit_price_currency", nullable = false, length = 3)
    private String unitPriceCurrency;

    @Column(nullable = false)
    private int quantity;

    @Column(name = "line_total", nullable = false)
    private long lineTotal;

    public UUID getId() { return id; }
    public void setId(UUID id) { this.id = id; }
    public OrderEntity getOrder() { return order; }
    public void setOrder(OrderEntity o) { this.order = o; }
    public UUID getProductId() { return productId; }
    public void setProductId(UUID p) { this.productId = p; }
    public String getProductName() { return productName; }
    public void setProductName(String n) { this.productName = n; }
    public long getUnitPriceAmount() { return unitPriceAmount; }
    public void setUnitPriceAmount(long v) { this.unitPriceAmount = v; }
    public String getUnitPriceCurrency() { return unitPriceCurrency; }
    public void setUnitPriceCurrency(String c) { this.unitPriceCurrency = c; }
    public int getQuantity() { return quantity; }
    public void setQuantity(int q) { this.quantity = q; }
    public long getLineTotal() { return lineTotal; }
    public void setLineTotal(long v) { this.lineTotal = v; }
}
