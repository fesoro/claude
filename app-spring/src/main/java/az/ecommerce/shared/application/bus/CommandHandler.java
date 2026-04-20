package az.ecommerce.shared.application.bus;

/**
 * Laravel: src/Shared/Application/Bus/CommandHandler.php
 * Spring: hər Handler @Component (yaxud @Service) işarələnir.
 * CommandBus reflection ilə command tipinə görə uyğun handler-i tapır.
 *
 * NÜMUNƏ:
 *   @Service
 *   public class CreateOrderHandler implements CommandHandler<CreateOrderCommand, UUID> {
 *       public UUID handle(CreateOrderCommand command) { ... }
 *   }
 */
public interface CommandHandler<C extends Command<R>, R> {
    R handle(C command);
}
