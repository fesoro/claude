package com.example.orders.entity;

import jakarta.persistence.*;
import java.math.BigDecimal;
import java.time.Instant;
import java.util.ArrayList;
import java.util.List;

@Entity
@Table(name = "orders")
public class Order {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    @Column(nullable = false)
    private String customerEmail;

    @Enumerated(EnumType.STRING)
    @Column(nullable = false)
    private OrderStatus status = OrderStatus.PENDING;

    @OneToMany(mappedBy = "order", cascade = CascadeType.ALL, orphanRemoval = true)
    private List<OrderItem> items = new ArrayList<>();

    @Column(updatable = false)
    private Instant createdAt = Instant.now();

    private Instant updatedAt;

    public BigDecimal totalAmount() {
        return items.stream()
                .map(i -> i.getUnitPrice().multiply(BigDecimal.valueOf(i.getQuantity())))
                .reduce(BigDecimal.ZERO, BigDecimal::add);
    }

    public void transitionTo(OrderStatus next) {
        status.validateTransition(next);
        this.status    = next;
        this.updatedAt = Instant.now();
    }

    public Long getId()                  { return id; }
    public String getCustomerEmail()     { return customerEmail; }
    public void setCustomerEmail(String e) { this.customerEmail = e; }
    public OrderStatus getStatus()       { return status; }
    public List<OrderItem> getItems()    { return items; }
    public Instant getCreatedAt()        { return createdAt; }
    public Instant getUpdatedAt()        { return updatedAt; }
}
