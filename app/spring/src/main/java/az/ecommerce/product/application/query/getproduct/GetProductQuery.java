package az.ecommerce.product.application.query.getproduct;

import az.ecommerce.product.application.dto.ProductDto;
import az.ecommerce.shared.application.bus.Query;

import java.util.UUID;

public record GetProductQuery(UUID productId) implements Query<ProductDto> {}
