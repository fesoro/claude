package az.ecommerce;

import org.springframework.boot.SpringApplication;
import org.springframework.boot.autoconfigure.SpringBootApplication;
import org.springframework.modulith.Modulithic;
import org.springframework.scheduling.annotation.EnableAsync;
import org.springframework.scheduling.annotation.EnableScheduling;

/**
 * Laravel-də: bootstrap/app.php → Application::configure()
 * Spring-də: @SpringBootApplication aktivasiyanı edir.
 *
 * @Modulithic — Spring Modulith bounded context-ləri compile-time-da yoxlayır:
 *   user, product, order, payment, notification → bir-birinin internal class-larını
 *   import edə bilməz.
 *
 * @EnableScheduling — Laravel-də Console/Kernel.php $schedule->job(...)
 * @EnableAsync — Laravel ShouldQueue trait-i + Job-lar
 */
@SpringBootApplication
@Modulithic(systemName = "ecommerce")
@EnableScheduling
@EnableAsync
public class EcommerceApplication {

    public static void main(String[] args) {
        SpringApplication.run(EcommerceApplication.class, args);
    }
}
