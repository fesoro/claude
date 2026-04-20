package az.ecommerce.product.application.query.listproducts;

import az.ecommerce.product.application.dto.ProductDto;
import az.ecommerce.shared.application.bus.Query;

import java.util.List;

/** Laravel: ListProductsQuery + ProductFilter */
public record ListProductsQuery(int page, int size) implements Query<List<ProductDto>> {}
