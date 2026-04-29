package az.ecommerce.integration;

import az.ecommerce.user.domain.User;
import az.ecommerce.user.domain.repository.UserRepository;
import az.ecommerce.user.domain.valueobject.Email;
import az.ecommerce.user.domain.valueobject.Password;
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
import java.util.Optional;

import static org.junit.jupiter.api.Assertions.*;

/**
 * Laravel: tests/Integration/EloquentUserRepositoryTest.php
 *
 * User aggregate-nin JPA map-ini real MySQL container-da yoxlayır.
 * BCrypt hash-lı şifrənin persist olunmasını və doğrulanmasını test edir.
 *
 * Yoxlananlar:
 * - User register → save → findByEmail round-trip
 * - Password hash persist olunur (plaintext saxlanmır)
 * - verifyPassword düzgün işləyir
 * - 2FA flag-ı persist olunur
 */
@SpringBootTest
@ActiveProfiles("test")
@Testcontainers
class JpaUserRepositoryIT {

    @Container
    static MySQLContainer<?> mysql = new MySQLContainer<>("mysql:8.0")
            .withDatabaseName("user_db")
            .withUsername("test")
            .withPassword("test");

    @DynamicPropertySource
    static void overrideProps(DynamicPropertyRegistry registry) {
        registry.add("app.datasource.user.jdbc-url", mysql::getJdbcUrl);
        registry.add("app.datasource.user.username", mysql::getUsername);
        registry.add("app.datasource.user.password", mysql::getPassword);
    }

    @Autowired
    private UserRepository repository;

    @Test
    void shouldSaveAndFindByEmail() {
        User user = User.register(
                "Test İstifadəçi",
                new Email("testuser@example.com"),
                Password.fromPlaintext("password123")
        );

        repository.save(user);

        Optional<User> found = repository.findByEmail(new Email("testuser@example.com"));
        assertTrue(found.isPresent());
        assertEquals("testuser@example.com", found.get().email().value());
        assertEquals("Test İstifadəçi", found.get().name());
    }

    @Test
    void shouldHashPasswordOnSave() {
        User user = User.register(
                "Hash Test",
                new Email("hashtest@example.com"),
                Password.fromPlaintext("mySecretPassword")
        );

        repository.save(user);

        User retrieved = repository.findByEmail(new Email("hashtest@example.com")).orElseThrow();
        // Şifrə plaintext olaraq saxlanmamalıdır
        assertNotEquals("mySecretPassword", retrieved.password().hash());
        // Amma doğrulama işləməlidir
        assertTrue(retrieved.verifyPassword("mySecretPassword"));
        assertFalse(retrieved.verifyPassword("wrongPassword"));
    }

    @Test
    void shouldReturnEmptyForUnknownEmail() {
        Optional<User> found = repository.findByEmail(new Email("nobody@example.com"));
        assertFalse(found.isPresent());
    }

    @Test
    void shouldPersist2FaFlag() {
        User user = User.register(
                "2FA Test",
                new Email("twofa@example.com"),
                Password.fromPlaintext("password123")
        );
        repository.save(user);
        assertFalse(repository.findByEmail(new Email("twofa@example.com"))
                .orElseThrow().twoFactorEnabled());

        // 2FA enable et — secret + backup codes ilə
        user.enableTwoFactor("JBSWY3DPEHPK3PXP", List.of("BACKUP1", "BACKUP2"));
        repository.save(user);

        assertTrue(repository.findByEmail(new Email("twofa@example.com"))
                .orElseThrow().twoFactorEnabled());
    }
}
