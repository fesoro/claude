package az.ecommerce.seed;

import az.ecommerce.user.domain.User;
import az.ecommerce.user.domain.repository.UserRepository;
import az.ecommerce.user.domain.valueobject.Email;
import az.ecommerce.user.domain.valueobject.Password;
import org.springframework.boot.CommandLineRunner;
import org.springframework.context.annotation.Profile;
import org.springframework.core.annotation.Order;
import org.springframework.stereotype.Component;

/**
 * Laravel: database/seeders/UserSeeder.php (1 admin + 10 user)
 * Spring: CommandLineRunner @Profile("seed") — `mvn -Dspring-boot.run.profiles=seed`
 */
@Component
@Profile("seed")
@Order(1)
public class UserSeeder implements CommandLineRunner {

    private final UserRepository repository;

    public UserSeeder(UserRepository repository) { this.repository = repository; }

    @Override
    public void run(String... args) {
        if (repository.existsByEmail(new Email("admin@ecommerce.az"))) return;

        repository.save(User.register("Admin", new Email("admin@ecommerce.az"),
                Password.fromPlaintext("admin12345")));

        for (int i = 1; i <= 10; i++) {
            repository.save(User.register("User " + i, new Email("user" + i + "@example.com"),
                    Password.fromPlaintext("password123")));
        }
        System.out.println("UserSeeder: 1 admin + 10 user yaradıldı");
    }
}
