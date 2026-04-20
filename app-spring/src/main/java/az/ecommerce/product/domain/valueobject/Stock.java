package az.ecommerce.product.domain.valueobject;

import az.ecommerce.shared.domain.ValueObject;
import az.ecommerce.shared.domain.exception.DomainException;

/**
 * Laravel: src/Product/Domain/ValueObjects/Stock.php
 * Spring: immutable record. decrease/increase yeni instance qaytarır.
 */
public record Stock(int quantity) implements ValueObject {

    public static final int LOW_STOCK_THRESHOLD = 5;

    public Stock {
        if (quantity < 0) {
            throw new DomainException("Stok mənfi ola bilməz: " + quantity);
        }
    }

    public static Stock of(int quantity) {
        return new Stock(quantity);
    }

    public Stock decrease(int amount) {
        if (amount < 0) throw new DomainException("Azalma müsbət olmalıdır");
        if (this.quantity < amount) {
            throw new DomainException(String.format(
                    "Yetərli stok yoxdur: mövcud %d, tələb %d", quantity, amount));
        }
        return new Stock(this.quantity - amount);
    }

    public Stock increase(int amount) {
        if (amount < 0) throw new DomainException("Artma müsbət olmalıdır");
        return new Stock(this.quantity + amount);
    }

    public boolean isOutOfStock() {
        return quantity == 0;
    }

    public boolean isLow() {
        return quantity > 0 && quantity <= LOW_STOCK_THRESHOLD;
    }
}
