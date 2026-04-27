package com.example.ecommerce.domain.product;

import jakarta.persistence.*;

// Aggregate Root — Product domain-ının invariantlarını qoruyur
@Entity
@Table(name = "products")
public class Product {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    @Column(nullable = false)
    private String name;

    @Embedded
    @AttributeOverride(name = "amount",   column = @Column(name = "price_amount"))
    @AttributeOverride(name = "currency", column = @Column(name = "price_currency"))
    private Money price;

    @Column(nullable = false)
    private int stock;

    protected Product() {} // JPA üçün

    public static Product create(String name, Money price, int initialStock) {
        if (name == null || name.isBlank()) throw new IllegalArgumentException("Ad boş ola bilməz");
        if (initialStock < 0) throw new IllegalArgumentException("Stok mənfi ola bilməz");
        Product p = new Product();
        p.name  = name;
        p.price = price;
        p.stock = initialStock;
        return p;
    }

    // Domain method — business qaydası burada
    public void decreaseStock(int qty) {
        if (qty <= 0) throw new IllegalArgumentException("Miqdar müsbət olmalıdır");
        if (stock < qty) throw new IllegalStateException("Kifayət qədər stok yoxdur: " + name);
        this.stock -= qty;
    }

    public void increaseStock(int qty) {
        if (qty <= 0) throw new IllegalArgumentException("Miqdar müsbət olmalıdır");
        this.stock += qty;
    }

    public boolean hasStock(int qty) { return stock >= qty; }

    public Long getId()    { return id; }
    public String getName() { return name; }
    public Money getPrice() { return price; }
    public int getStock()  { return stock; }
}
