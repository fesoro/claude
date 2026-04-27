package com.example.ecommerce.domain.order;

import jakarta.persistence.*;
import java.math.BigDecimal;
import java.time.Instant;
import java.util.ArrayList;
import java.util.Collections;
import java.util.List;

// Aggregate Root — Order aggregate
@Entity
@Table(name = "orders")
public class Order {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    @Column(nullable = false)
    private String customerEmail;

    @Enumerated(EnumType.STRING)
    private OrderStatus status = OrderStatus.PENDING;

    @OneToMany(mappedBy = "order", cascade = CascadeType.ALL, orphanRemoval = true)
    private List<OrderItem> items = new ArrayList<>();

    @Column(updatable = false)
    private Instant createdAt = Instant.now();

    protected Order() {}

    public static Order create(String customerEmail) {
        if (customerEmail == null || customerEmail.isBlank()) {
            throw new IllegalArgumentException("Müştəri email-i boş ola bilməz");
        }
        Order o = new Order();
        o.customerEmail = customerEmail;
        return o;
    }

    // Item əlavə etmək — Aggregate daxilindən
    public void addItem(OrderItem item) {
        item.setOrder(this);
        items.add(item);
    }

    // Ödənilməli məbləği hesabla — aggregate-in məsuliyyətidir
    public BigDecimal totalAmount() {
        return items.stream()
                .map(OrderItem::subtotal)
                .reduce(BigDecimal.ZERO, BigDecimal::add);
    }

    public void confirm() {
        if (status != OrderStatus.PENDING) throw new IllegalStateException("Yalnız PENDING sifariş təsdiqlənə bilər");
        if (items.isEmpty()) throw new IllegalStateException("Sifarişdə məhsul yoxdur");
        this.status = OrderStatus.CONFIRMED;
    }

    public void cancel() {
        if (status == OrderStatus.CONFIRMED) throw new IllegalStateException("Təsdiqlənmiş sifariş ləğv edilə bilməz");
        this.status = OrderStatus.CANCELLED;
    }

    public Long getId()              { return id; }
    public String getCustomerEmail() { return customerEmail; }
    public OrderStatus getStatus()   { return status; }
    public List<OrderItem> getItems() { return Collections.unmodifiableList(items); }
    public Instant getCreatedAt()    { return createdAt; }
}
