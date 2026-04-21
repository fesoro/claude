package az.ecommerce.product.domain;

import az.ecommerce.product.domain.event.LowStockIntegrationEvent;
import az.ecommerce.product.domain.event.ProductCreatedEvent;
import az.ecommerce.product.domain.event.StockDecreasedEvent;
import az.ecommerce.product.domain.valueobject.*;
import az.ecommerce.shared.domain.AggregateRoot;

/**
 * === PRODUCT AGGREGATE ROOT ===
 * Laravel: src/Product/Domain/Entities/Product.php
 */
public class Product extends AggregateRoot {

    private final ProductId id;
    private ProductName name;
    private String description;
    private Money price;
    private Stock stock;

    private Product(ProductId id, ProductName name, String description, Money price, Stock stock) {
        this.id = id;
        this.name = name;
        this.description = description;
        this.price = price;
        this.stock = stock;
    }

    public static Product create(ProductName name, String description, Money price, Stock stock) {
        ProductId id = ProductId.generate();
        Product p = new Product(id, name, description, price, stock);
        p.recordEvent(ProductCreatedEvent.of(id, name, price));
        return p;
    }

    public static Product reconstitute(ProductId id, ProductName name, String desc, Money price, Stock stock) {
        return new Product(id, name, desc, price, stock);
    }

    public void decreaseStock(int amount) {
        Stock newStock = this.stock.decrease(amount);
        this.stock = newStock;
        recordEvent(StockDecreasedEvent.of(id, amount, newStock.quantity()));
        if (newStock.isLow()) {
            recordEvent(LowStockIntegrationEvent.of(id.value(), name.value(), newStock.quantity()));
        }
    }

    public void increaseStock(int amount) {
        this.stock = this.stock.increase(amount);
    }

    public ProductId id() { return id; }
    public ProductName name() { return name; }
    public String description() { return description; }
    public Money price() { return price; }
    public Stock stock() { return stock; }
}
