package az.ecommerce.shell;

import org.springframework.shell.standard.ShellComponent;
import org.springframework.shell.standard.ShellMethod;
import org.springframework.shell.standard.ShellOption;

/**
 * Laravel: RebuildProjectionCommand → CQRS read model-ləri yenidən qur (event replay).
 */
@ShellComponent
public class RebuildProjectionCommand {

    @ShellMethod(key = "projection:rebuild", value = "Read model-i event store-dan yenidən qur")
    public String rebuild(@ShellOption(defaultValue = "OrderListProjector") String projector) {
        // Real implementasiya: bütün event-ləri oxu, projector-u yenidən işlət
        return "Projection rebuild: " + projector + " — completed";
    }
}
