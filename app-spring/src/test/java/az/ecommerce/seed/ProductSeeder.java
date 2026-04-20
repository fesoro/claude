package az.ecommerce.seed;

import az.ecommerce.product.domain.Product;
import az.ecommerce.product.domain.repository.ProductRepository;
import az.ecommerce.product.domain.valueobject.*;
import org.springframework.boot.CommandLineRunner;
import org.springframework.context.annotation.Profile;
import org.springframework.core.annotation.Order;
import org.springframework.stereotype.Component;

/**
 * Laravel: ProductSeeder (20: 14 normal + 3 low stock + 3 expensive)
 */
@Component
@Profile("seed")
@Order(2)
public class ProductSeeder implements CommandLineRunner {

    private final ProductRepository repository;

    public ProductSeeder(ProductRepository repository) { this.repository = repository; }

    @Override
    public void run(String... args) {
        for (int i = 1; i <= 14; i++) {
            repository.save(Product.create(
                    new ProductName("Məhsul " + i), "Açıqlama " + i,
                    Money.of(1000L * i, Currency.AZN), Stock.of(100)));
        }
        for (int i = 15; i <= 17; i++) {
            repository.save(Product.create(
                    new ProductName("Az qalıq " + i), "Az qalıq",
                    Money.of(5000, Currency.AZN), Stock.of(3)));
        }
        for (int i = 18; i <= 20; i++) {
            repository.save(Product.create(
                    new ProductName("Premium " + i), "Bahalı məhsul",
                    Money.of(500_000, Currency.AZN), Stock.of(5)));
        }
        System.out.println("ProductSeeder: 20 məhsul yaradıldı");
    }
}
