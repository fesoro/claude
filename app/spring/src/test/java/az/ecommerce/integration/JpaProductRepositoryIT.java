package az.ecommerce.integration;

import az.ecommerce.product.domain.Product;
import az.ecommerce.product.domain.repository.ProductRepository;
import az.ecommerce.product.domain.valueobject.*;
import org.junit.jupiter.api.Test;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.boot.test.context.SpringBootTest;
import org.springframework.test.context.ActiveProfiles;
import org.springframework.test.context.DynamicPropertyRegistry;
import org.springframework.test.context.DynamicPropertySource;
import org.testcontainers.containers.MySQLContainer;
import org.testcontainers.junit.jupiter.Container;
import org.testcontainers.junit.jupiter.Testcontainers;

import java.util.UUID;

import static org.junit.jupiter.api.Assertions.*;

/**
 * Laravel: tests/Integration/EloquentProductRepositoryTest.php
 *
 * Spring: Real MySQL container (Testcontainers) — production parity.
 * Bu test JpaProductRepository-nin Hibernate map-ini doğru saxladığını yoxlayır.
 */
@SpringBootTest
@ActiveProfiles("test")
@Testcontainers
class JpaProductRepositoryIT {

    @Container
    static MySQLContainer<?> mysql = new MySQLContainer<>("mysql:8.0")
            .withDatabaseName("product_db")
            .withUsername("test")
            .withPassword("test");

    @DynamicPropertySource
    static void overrideProps(DynamicPropertyRegistry registry) {
        registry.add("app.datasource.product.jdbc-url", mysql::getJdbcUrl);
        registry.add("app.datasource.product.username", mysql::getUsername);
        registry.add("app.datasource.product.password", mysql::getPassword);
    }

    @Autowired
    private ProductRepository repository;

    @Test
    void shouldSaveAndRetrieveProduct() {
        Product product = Product.create(
                new ProductName("Test Məhsul"), "Açıqlama",
                Money.of(2599, Currency.AZN), Stock.of(50));

        repository.save(product);

        Product retrieved = repository.findById(product.id()).orElseThrow();
        assertEquals("Test Məhsul", retrieved.name().value());
        assertEquals(2599, retrieved.price().amount());
        assertEquals(Currency.AZN, retrieved.price().currency());
        assertEquals(50, retrieved.stock().quantity());
    }

    @Test
    void shouldReturnEmptyForUnknownId() {
        assertTrue(repository.findById(new ProductId(UUID.randomUUID())).isEmpty());
    }
}
