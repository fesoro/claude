package com.example.orders.entity;

import com.fasterxml.jackson.annotation.JsonIgnore;
import jakarta.persistence.*;
import java.math.BigDecimal;

@Entity
@Table(name = "order_items")
public class OrderItem {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    @ManyToOne(fetch = FetchType.LAZY)
    @JoinColumn(name = "order_id", nullable = false)
    @JsonIgnore
    private Order order;

    @Column(nullable = false)
    private String productName;

    private int quantity;

    @Column(precision = 10, scale = 2)
    private BigDecimal unitPrice;

    public Long getId()              { return id; }
    public Order getOrder()          { return order; }
    public void setOrder(Order o)    { this.order = o; }
    public String getProductName()   { return productName; }
    public void setProductName(String n) { this.productName = n; }
    public int getQuantity()         { return quantity; }
    public void setQuantity(int q)   { this.quantity = q; }
    public BigDecimal getUnitPrice() { return unitPrice; }
    public void setUnitPrice(BigDecimal p) { this.unitPrice = p; }
}
