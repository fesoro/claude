package az.ecommerce.shell;

import org.springframework.shell.standard.ShellComponent;
import org.springframework.shell.standard.ShellMethod;
import org.springframework.shell.standard.ShellOption;

/**
 * Laravel: php artisan rabbitmq:consume — manual consumer.
 * Spring-də @RabbitListener avtomatikdir, amma debug üçün manual consumer faydalıdır.
 */
@ShellComponent
public class ConsumeRabbitMQCommand {

    @ShellMethod(key = "rabbitmq:consume", value = "RabbitMQ-dan manual mesaj consume et")
    public String consume(@ShellOption(defaultValue = "notifications.order.created") String queue,
                          @ShellOption(defaultValue = "10") int count) {
        return "Consuming " + count + " messages from " + queue;
    }
}
