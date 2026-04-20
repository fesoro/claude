package az.ecommerce.product.domain.repository;

import az.ecommerce.product.domain.Product;
import az.ecommerce.product.domain.valueobject.ProductId;

import java.util.List;
import java.util.Optional;

public interface ProductRepository {
    Product save(Product product);
    Optional<Product> findById(ProductId id);
    List<Product> findAll(int page, int size);
    long count();
}
