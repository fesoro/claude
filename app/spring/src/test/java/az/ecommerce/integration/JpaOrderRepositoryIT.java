package az.ecommerce.integration;

import az.ecommerce.order.domain.Order;
import az.ecommerce.order.domain.enums.OrderStatusEnum;
import az.ecommerce.order.domain.repository.OrderRepository;
import az.ecommerce.order.domain.valueobject.Address;
import az.ecommerce.order.domain.valueobject.OrderId;
import az.ecommerce.order.domain.valueobject.OrderItem;
import az.ecommerce.product.domain.valueobject.Currency;
import az.ecommerce.product.domain.valueobject.Money;
import org.junit.jupiter.api.Test;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.boot.test.context.SpringBootTest;
import org.springframework.test.context.ActiveProfiles;
import org.springframework.test.context.DynamicPropertyRegistry;
import org.springframework.test.context.DynamicPropertySource;
import org.testcontainers.containers.MySQLContainer;
import org.testcontainers.junit.jupiter.Container;
import org.testcontainers.junit.jupiter.Testcontainers;

import java.util.List;
import java.util.UUID;

import static org.junit.jupiter.api.Assertions.*;

/**
 * Laravel: tests/Integration/EloquentOrderRepositoryTest.php
 *
 * Order aggregate-nin JPA map-ini real MySQL container-da yoxlayır.
 * Testcontainers — production parity (H2 MySQL fərqləri yaranmır).
 *
 * Yoxlananlar:
 * - Order save + findById round-trip
 * - Status dəyişikliyi persist olunur
 * - Ləğv olunmuş sifariş findById ilə gətirilir
 * - findByUserId siyahı qaytarır
 */
@SpringBootTest
@ActiveProfiles("test")
@Testcontainers
class JpaOrderRepositoryIT {

    @Container
    static MySQLContainer<?> mysql = new MySQLContainer<>("mysql:8.0")
            .withDatabaseName("order_db")
            .withUsername("test")
            .withPassword("test");

    @DynamicPropertySource
    static void overrideProps(DynamicPropertyRegistry registry) {
        registry.add("app.datasource.order.jdbc-url", mysql::getJdbcUrl);
        registry.add("app.datasource.order.username", mysql::getUsername);
        registry.add("app.datasource.order.password", mysql::getPassword);
    }

    @Autowired
    private OrderRepository repository;

    @Test
    void shouldSaveAndRetrieveOrder() {
        Order order = Order.create(
                UUID.randomUUID(),
                List.of(new OrderItem(UUID.randomUUID(), "Test Məhsul", Money.of(1500, Currency.AZN), 2)),
                new Address("İstiqlaliyyət 5", "Bakı", "AZ1000", "AZ"),
                Currency.AZN
        );

        repository.save(order);

        Order retrieved = repository.findById(order.id()).orElseThrow();
        assertEquals(order.id(), retrieved.id());
        assertEquals(OrderStatusEnum.PENDING, retrieved.status());
        assertEquals(1, retrieved.items().size());
        assertEquals(3000, retrieved.totalAmount().amount()); // 2 × 1500
    }

    @Test
    void shouldPersistStatusChange() {
        Order order = Order.create(
                UUID.randomUUID(),
                List.of(new OrderItem(UUID.randomUUID(), "Məhsul", Money.of(500, Currency.AZN), 1)),
                new Address("Nizami 10", "Bakı", "AZ1001", "AZ"),
                Currency.AZN
        );
        repository.save(order);

        order.confirm();
        repository.save(order);

        Order retrieved = repository.findById(order.id()).orElseThrow();
        assertEquals(OrderStatusEnum.CONFIRMED, retrieved.status());
    }

    @Test
    void shouldCancelOrder() {
        Order order = Order.create(
                UUID.randomUUID(),
                List.of(new OrderItem(UUID.randomUUID(), "Məhsul", Money.of(300, Currency.AZN), 1)),
                new Address("Hüseyn Cavid 1", "Bakı", "AZ1002", "AZ"),
                Currency.AZN
        );
        repository.save(order);

        order.cancel("Müştəri ləğv etdi");
        repository.save(order);

        Order retrieved = repository.findById(order.id()).orElseThrow();
        assertEquals(OrderStatusEnum.CANCELLED, retrieved.status());
    }

    @Test
    void shouldReturnEmptyForUnknownId() {
        assertTrue(repository.findById(new OrderId(UUID.randomUUID())).isEmpty());
    }

    @Test
    void shouldFindOrdersByUserId() {
        UUID userId = UUID.randomUUID();
        Order order1 = Order.create(userId,
                List.of(new OrderItem(UUID.randomUUID(), "Məhsul 1", Money.of(100, Currency.AZN), 1)),
                new Address("Test 1", "Bakı", "AZ1000", "AZ"), Currency.AZN);
        Order order2 = Order.create(userId,
                List.of(new OrderItem(UUID.randomUUID(), "Məhsul 2", Money.of(200, Currency.AZN), 2)),
                new Address("Test 2", "Bakı", "AZ1000", "AZ"), Currency.AZN);

        repository.save(order1);
        repository.save(order2);

        List<Order> orders = repository.findByUserId(userId);
        assertEquals(2, orders.size());
    }
}
