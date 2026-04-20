package az.ecommerce.product.domain.specification;

import az.ecommerce.product.domain.Product;
import az.ecommerce.shared.domain.Specification;

public class ProductPriceIsValidSpec implements Specification<Product> {
    @Override
    public boolean isSatisfiedBy(Product product) {
        return !product.price().isZero();
    }
}
