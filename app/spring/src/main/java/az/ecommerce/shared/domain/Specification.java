package az.ecommerce.shared.domain;

/**
 * Laravel: src/Shared/Domain/Specification.php
 *   - isSatisfiedBy($candidate): bool
 *   - and(), or(), not() composition
 *
 * Spring: generic functional interface — Java lambda-larla composition mümkündür.
 *
 * NÜMUNƏ:
 *   var spec = new ProductIsInStockSpec().and(new ProductPriceIsValidSpec());
 *   if (spec.isSatisfiedBy(product)) { ... }
 */
@FunctionalInterface
public interface Specification<T> {

    boolean isSatisfiedBy(T candidate);

    default Specification<T> and(Specification<T> other) {
        return c -> this.isSatisfiedBy(c) && other.isSatisfiedBy(c);
    }

    default Specification<T> or(Specification<T> other) {
        return c -> this.isSatisfiedBy(c) || other.isSatisfiedBy(c);
    }

    default Specification<T> not() {
        return c -> !this.isSatisfiedBy(c);
    }
}
