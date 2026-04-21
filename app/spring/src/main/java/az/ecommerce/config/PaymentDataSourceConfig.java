package az.ecommerce.config;

import com.zaxxer.hikari.HikariDataSource;
import jakarta.persistence.EntityManagerFactory;
import org.springframework.beans.factory.annotation.Qualifier;
import org.springframework.boot.context.properties.ConfigurationProperties;
import org.springframework.boot.jdbc.DataSourceBuilder;
import org.springframework.boot.orm.jpa.EntityManagerFactoryBuilder;
import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;
import org.springframework.data.jpa.repository.config.EnableJpaRepositories;
import org.springframework.orm.jpa.JpaTransactionManager;
import org.springframework.orm.jpa.LocalContainerEntityManagerFactoryBean;
import org.springframework.transaction.PlatformTransactionManager;

import javax.sql.DataSource;
import java.util.HashMap;
import java.util.Map;

/**
 * === PAYMENT DATABASE ===
 * Laravel: PaymentModel-də protected $connection = 'payment_db';
 */
@Configuration
@EnableJpaRepositories(
    basePackages = "az.ecommerce.payment.infrastructure.persistence",
    entityManagerFactoryRef = "paymentEntityManagerFactory",
    transactionManagerRef = "paymentTransactionManager"
)
public class PaymentDataSourceConfig {

    @Bean(name = "paymentDataSource")
    @ConfigurationProperties(prefix = "app.datasource.payment")
    public DataSource paymentDataSource() {
        return DataSourceBuilder.create().type(HikariDataSource.class).build();
    }

    @Bean(name = "paymentEntityManagerFactory")
    public LocalContainerEntityManagerFactoryBean paymentEntityManagerFactory(
            EntityManagerFactoryBuilder builder,
            @Qualifier("paymentDataSource") DataSource dataSource) {

        Map<String, Object> properties = new HashMap<>();
        properties.put("hibernate.hbm2ddl.auto", "validate");
        properties.put("hibernate.dialect", "org.hibernate.dialect.MySQLDialect");

        return builder
                .dataSource(dataSource)
                .packages("az.ecommerce.payment.infrastructure.persistence",
                          "az.ecommerce.payment.infrastructure.circuitbreaker")
                .persistenceUnit("payment")
                .properties(properties)
                .build();
    }

    @Bean(name = "paymentTransactionManager")
    public PlatformTransactionManager paymentTransactionManager(
            @Qualifier("paymentEntityManagerFactory") EntityManagerFactory emf) {
        return new JpaTransactionManager(emf);
    }
}
