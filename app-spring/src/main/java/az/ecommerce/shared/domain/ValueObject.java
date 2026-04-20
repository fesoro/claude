package az.ecommerce.shared.domain;

/**
 * Laravel: src/Shared/Domain/ValueObject.php (marker abstract)
 *
 * Spring: marker interface. Java record-lər ən təbii Value Object-dir:
 *   - Immutable (final fields)
 *   - equals/hashCode auto-generated
 *   - Compact constructor-da validation
 *
 * NÜMUNƏ:
 *   public record Money(long amount, Currency currency) implements ValueObject {
 *       public Money {  // compact constructor — validation
 *           if (amount < 0) throw new IllegalArgumentException("Money negativ ola bilməz");
 *       }
 *   }
 */
public interface ValueObject {
}
