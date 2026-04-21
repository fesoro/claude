package az.ecommerce.shell;

import org.springframework.amqp.rabbit.listener.RabbitListenerEndpointRegistry;
import org.springframework.shell.standard.ShellComponent;
import org.springframework.shell.standard.ShellMethod;

/**
 * Laravel: php artisan worker:graceful — SIGTERM tutub son job bitdikdən sonra exit
 * Spring: RabbitListener-lərin gracefully dayandırılması.
 */
@ShellComponent
public class GracefulWorkerCommand {

    private final RabbitListenerEndpointRegistry registry;

    public GracefulWorkerCommand(RabbitListenerEndpointRegistry registry) {
        this.registry = registry;
    }

    @ShellMethod(key = "worker:graceful", value = "RabbitMQ listener-ləri gracefully dayandır")
    public String stop() {
        registry.stop();
        return "Bütün listener-lər dayandırıldı";
    }
}
