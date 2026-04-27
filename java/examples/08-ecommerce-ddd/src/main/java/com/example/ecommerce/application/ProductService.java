package com.example.ecommerce.application;

import com.example.ecommerce.domain.product.Money;
import com.example.ecommerce.domain.product.Product;
import com.example.ecommerce.domain.product.ProductRepository;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Transactional;

import java.math.BigDecimal;
import java.util.List;
import java.util.NoSuchElementException;

// Application Service: use case-ləri orkestrasiya edir, domain logic-i özündə saxlamır
@Service
@Transactional(readOnly = true)
public class ProductService {

    private final ProductRepository repo;

    public ProductService(ProductRepository repo) { this.repo = repo; }

    public List<Product> findAll() { return repo.findAll(); }

    public Product findById(Long id) {
        return repo.findById(id).orElseThrow(() -> new NoSuchElementException("Məhsul tapılmadı: " + id));
    }

    @Transactional
    public Product create(String name, BigDecimal price, int stock) {
        Product product = Product.create(name, Money.of(price), stock);
        return repo.save(product);
    }
}
