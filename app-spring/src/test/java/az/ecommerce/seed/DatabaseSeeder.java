package az.ecommerce.seed;

import org.springframework.boot.CommandLineRunner;
import org.springframework.context.annotation.Profile;
import org.springframework.core.annotation.Order;
import org.springframework.stereotype.Component;

/**
 * Laravel: database/seeders/DatabaseSeeder.php (orchestrator)
 *
 * Spring-də CommandLineRunner-lər artıq @Order ilə sıralı işləyir.
 * Bu seeder yekun mesajı çap edir.
 */
@Component
@Profile("seed")
@Order(99)
public class DatabaseSeeder implements CommandLineRunner {

    @Override
    public void run(String... args) {
        System.out.println("==========================================");
        System.out.println("  Bütün seeder-lər uğurla işlədi");
        System.out.println("==========================================");
    }
}
