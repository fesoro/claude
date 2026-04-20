package az.ecommerce.shared.application.bus;

/**
 * Laravel: src/Shared/Application/Bus/Command.php (marker interface)
 * CQRS-də write əməliyyatlarını təmsil edir.
 *
 * NÜMUNƏ:
 *   public record CreateOrderCommand(UUID userId, List<OrderItemDto> items, AddressDto address)
 *       implements Command<UUID> {}
 */
public interface Command<R> {
}
