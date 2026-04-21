package az.ecommerce.product.application.dto;

import az.ecommerce.product.domain.Product;

import java.util.UUID;

public record ProductDto(
        UUID id, String name, String description,
        long priceAmount, String priceCurrency,
        int stockQuantity, boolean inStock, boolean lowStock
) {
    public static ProductDto fromDomain(Product p) {
        return new ProductDto(
                p.id().value(), p.name().value(), p.description(),
                p.price().amount(), p.price().currency().name(),
                p.stock().quantity(), !p.stock().isOutOfStock(), p.stock().isLow());
    }
}
