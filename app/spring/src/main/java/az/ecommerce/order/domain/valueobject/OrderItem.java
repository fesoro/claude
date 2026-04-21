package az.ecommerce.order.domain.valueobject;

import az.ecommerce.product.domain.valueobject.Money;
import az.ecommerce.shared.domain.ValueObject;
import az.ecommerce.shared.domain.exception.DomainException;

import java.util.UUID;

public record OrderItem(UUID productId, String productName, Money unitPrice, int quantity) implements ValueObject {

    public OrderItem {
        if (productId == null) throw new DomainException("ProductId null");
        if (productName == null || productName.isBlank()) throw new DomainException("Məhsul adı boş");
        if (quantity <= 0) throw new DomainException("Miqdar müsbət olmalıdır");
    }

    public Money lineTotal() {
        return unitPrice.multiply(quantity);
    }
}
