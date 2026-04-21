package az.ecommerce.shared.application.bus;

/**
 * Laravel: src/Shared/Application/Bus/Query.php (marker)
 * CQRS-də read əməliyyatları (side-effect-siz, idempotent).
 *
 * NÜMUNƏ:
 *   public record GetOrderQuery(UUID orderId) implements Query<OrderDto> {}
 */
public interface Query<R> {
}
