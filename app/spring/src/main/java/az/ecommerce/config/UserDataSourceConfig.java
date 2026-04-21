package az.ecommerce.config;

import com.zaxxer.hikari.HikariDataSource;
import jakarta.persistence.EntityManagerFactory;
import org.springframework.beans.factory.annotation.Qualifier;
import org.springframework.boot.context.properties.ConfigurationProperties;
import org.springframework.boot.jdbc.DataSourceBuilder;
import org.springframework.boot.orm.jpa.EntityManagerFactoryBuilder;
import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;
import org.springframework.context.annotation.Primary;
import org.springframework.data.jpa.repository.config.EnableJpaRepositories;
import org.springframework.orm.jpa.JpaTransactionManager;
import org.springframework.orm.jpa.LocalContainerEntityManagerFactoryBean;
import org.springframework.transaction.PlatformTransactionManager;

import javax.sql.DataSource;
import java.util.HashMap;
import java.util.Map;

/**
 * === USER DATABASE Konfiqurasiyası ===
 *
 * Laravel-də: config/database.php-də 'connections' altında 'user_db' connection.
 * Models-də: protected $connection = 'user_db';
 *
 * Spring-də: hər DB-yə öz @Configuration class lazımdır:
 *   - DataSource bean (HikariCP connection pool)
 *   - EntityManagerFactory bean (JPA üçün)
 *   - TransactionManager bean
 *
 * @EnableJpaRepositories — bu DB-nin repository-ləri hansı paketdədir
 */
@Configuration
@EnableJpaRepositories(
    basePackages = {
        "az.ecommerce.user.infrastructure.persistence",
        "az.ecommerce.shared.infrastructure.audit",          // AuditLogRepository
        "az.ecommerce.notification.infrastructure.preference", // NotificationPreferenceRepository
        "az.ecommerce.webhook"                                // WebhookRepository, WebhookLogRepository
    },
    entityManagerFactoryRef = "userEntityManagerFactory",
    transactionManagerRef = "userTransactionManager"
)
public class UserDataSourceConfig {

    @Primary
    @Bean(name = "userDataSource")
    @ConfigurationProperties(prefix = "app.datasource.user")
    public DataSource userDataSource() {
        return DataSourceBuilder.create().type(HikariDataSource.class).build();
    }

    @Primary
    @Bean(name = "userEntityManagerFactory")
    public LocalContainerEntityManagerFactoryBean userEntityManagerFactory(
            EntityManagerFactoryBuilder builder,
            @Qualifier("userDataSource") DataSource dataSource) {

        Map<String, Object> properties = new HashMap<>();
        properties.put("hibernate.hbm2ddl.auto", "validate");
        properties.put("hibernate.dialect", "org.hibernate.dialect.MySQLDialect");

        return builder
                .dataSource(dataSource)
                .packages(
                    "az.ecommerce.user.infrastructure.persistence",
                    "az.ecommerce.shared.infrastructure.audit",
                    "az.ecommerce.notification.infrastructure.preference",
                    "az.ecommerce.webhook"
                )
                .persistenceUnit("user")
                .properties(properties)
                .build();
    }

    @Primary
    @Bean(name = "userTransactionManager")
    public PlatformTransactionManager userTransactionManager(
            @Qualifier("userEntityManagerFactory") EntityManagerFactory emf) {
        return new JpaTransactionManager(emf);
    }
}
