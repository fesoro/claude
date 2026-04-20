package az.ecommerce.config;

import org.flywaydb.core.Flyway;
import org.springframework.beans.factory.annotation.Qualifier;
import org.springframework.boot.autoconfigure.flyway.FlywayMigrationStrategy;
import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;

import javax.sql.DataSource;

/**
 * Laravel-də: php artisan migrate --database=user_db
 *             php artisan migrate --database=product_db ...
 *
 * Spring-də: hər DB üçün ayrı Flyway instance, ayrı migration qovluğu.
 * Default Flyway-i deaktiv edirik və 4 ayrıca config edirik.
 */
@Configuration
public class FlywayMultiTenantConfig {

    @Bean(initMethod = "migrate")
    public Flyway userFlyway(@Qualifier("userDataSource") DataSource dataSource) {
        return Flyway.configure()
                .dataSource(dataSource)
                .locations("classpath:db/migration/user")
                .baselineOnMigrate(true)
                .load();
    }

    @Bean(initMethod = "migrate")
    public Flyway productFlyway(@Qualifier("productDataSource") DataSource dataSource) {
        return Flyway.configure()
                .dataSource(dataSource)
                .locations("classpath:db/migration/product")
                .baselineOnMigrate(true)
                .load();
    }

    @Bean(initMethod = "migrate")
    public Flyway orderFlyway(@Qualifier("orderDataSource") DataSource dataSource) {
        return Flyway.configure()
                .dataSource(dataSource)
                .locations("classpath:db/migration/order")
                .baselineOnMigrate(true)
                .load();
    }

    @Bean(initMethod = "migrate")
    public Flyway paymentFlyway(@Qualifier("paymentDataSource") DataSource dataSource) {
        return Flyway.configure()
                .dataSource(dataSource)
                .locations("classpath:db/migration/payment")
                .baselineOnMigrate(true)
                .load();
    }

    /**
     * Spring Boot-un default Flyway auto-config-ini söndürürük
     * (yuxarıdakı 4 instance manual idarə edir)
     */
    @Bean
    public FlywayMigrationStrategy noopStrategy() {
        return flyway -> { /* heç nə etmə — bizim 4 ayrıca instance var */ };
    }
}
