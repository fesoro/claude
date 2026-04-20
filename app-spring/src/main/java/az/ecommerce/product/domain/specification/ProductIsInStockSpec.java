package az.ecommerce.product.domain.specification;

import az.ecommerce.product.domain.Product;
import az.ecommerce.shared.domain.Specification;

/**
 * Laravel: src/Product/Domain/Specifications/ProductIsInStockSpec.php
 * Spring: functional interface implementasiyası — hətta lambda yazıla bilər.
 */
public class ProductIsInStockSpec implements Specification<Product> {
    @Override
    public boolean isSatisfiedBy(Product product) {
        return !product.stock().isOutOfStock();
    }
}
