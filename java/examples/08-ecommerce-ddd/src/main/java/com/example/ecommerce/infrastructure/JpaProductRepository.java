package com.example.ecommerce.infrastructure;

import com.example.ecommerce.domain.product.Product;
import com.example.ecommerce.domain.product.ProductRepository;
import org.springframework.data.jpa.repository.JpaRepository;
import org.springframework.stereotype.Repository;

// Infrastructure layer: domain interface-ni Spring Data JPA ilə implement edir
@Repository
public interface JpaProductRepository extends ProductRepository, JpaRepository<Product, Long> {
    // JpaRepository findById, save, findAll implement edir
    // ProductRepository-nin bütün metodları örtülmüş olur
}
