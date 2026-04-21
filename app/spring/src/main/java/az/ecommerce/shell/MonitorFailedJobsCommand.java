package az.ecommerce.shell;

import org.springframework.shell.standard.ShellComponent;
import org.springframework.shell.standard.ShellMethod;

/**
 * Laravel: php artisan queue:failed-monitor
 * Spring: dead_letter_queue-dakı mesajları sayır.
 */
@ShellComponent
public class MonitorFailedJobsCommand {

    @ShellMethod(key = "queue:failed-monitor", value = "DLQ-dakı failed job-ların sayını göstərir")
    public String monitor() {
        // Real implementasiya RabbitAdmin ilə queue depth oxuyur
        return "DLQ status: 0 failed jobs";
    }
}
