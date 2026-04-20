package az.ecommerce.config;

import org.springframework.context.annotation.Configuration;

/**
 * Laravel: config/mail.php — SMTP konfiqurasiyası.
 *
 * Spring: spring-boot-starter-mail + spring-boot-starter-thymeleaf
 * artıq auto-configure edir. application.yml-də `spring.mail.*` və
 * `spring.thymeleaf.*` açarları kifayətdir.
 *
 * Bu class struktur saxlamaq üçündür — manual override lazım gəlsə buraya əlavə edilir.
 */
@Configuration
public class MailConfig {
    // Default Spring Boot auto-config: JavaMailSender + SpringTemplateEngine
    // application.yml-də konfiqurasiya:
    //   spring.mail.host, port, username, password
    //   spring.thymeleaf.prefix, suffix
}
