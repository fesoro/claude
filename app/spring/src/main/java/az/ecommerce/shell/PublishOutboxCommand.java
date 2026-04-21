package az.ecommerce.shell;

import az.ecommerce.shared.infrastructure.messaging.OutboxPublisherJob;
import org.springframework.shell.standard.ShellComponent;
import org.springframework.shell.standard.ShellMethod;

/**
 * Laravel: php artisan outbox:publish [--sync]
 * Spring Shell: outbox:publish
 */
@ShellComponent
public class PublishOutboxCommand {

    private final OutboxPublisherJob job;

    public PublishOutboxCommand(OutboxPublisherJob job) { this.job = job; }

    @ShellMethod(key = "outbox:publish", value = "Outbox-dakı bütün publish olunmamış mesajları RabbitMQ-yə göndər")
    public String publish() {
        job.publish();
        return "Outbox publish bitdi";
    }
}
