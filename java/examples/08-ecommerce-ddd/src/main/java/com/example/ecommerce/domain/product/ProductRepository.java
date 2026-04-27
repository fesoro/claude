package com.example.ecommerce.domain.product;

import java.util.List;
import java.util.Optional;

// Domain layer-da interfeys — framework dependency yoxdur
public interface ProductRepository {
    Product save(Product product);
    Optional<Product> findById(Long id);
    List<Product> findAll();
}
