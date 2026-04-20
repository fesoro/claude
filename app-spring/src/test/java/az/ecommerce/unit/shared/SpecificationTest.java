package az.ecommerce.unit.shared;

import az.ecommerce.shared.domain.Specification;
import org.junit.jupiter.api.Test;

import static org.junit.jupiter.api.Assertions.*;

/**
 * Laravel: tests/Unit/Shared/SpecificationTest.php
 * and/or/not composition yoxlanılır.
 */
class SpecificationTest {

    private final Specification<Integer> isPositive = n -> n > 0;
    private final Specification<Integer> isEven = n -> n % 2 == 0;

    @Test
    void shouldComposeWithAnd() {
        Specification<Integer> positiveAndEven = isPositive.and(isEven);
        assertTrue(positiveAndEven.isSatisfiedBy(4));
        assertFalse(positiveAndEven.isSatisfiedBy(3));
        assertFalse(positiveAndEven.isSatisfiedBy(-2));
    }

    @Test
    void shouldComposeWithOr() {
        Specification<Integer> positiveOrEven = isPositive.or(isEven);
        assertTrue(positiveOrEven.isSatisfiedBy(3));
        assertTrue(positiveOrEven.isSatisfiedBy(-2));
        assertFalse(positiveOrEven.isSatisfiedBy(-1));
    }

    @Test
    void shouldNegateWithNot() {
        Specification<Integer> isNonPositive = isPositive.not();
        assertTrue(isNonPositive.isSatisfiedBy(0));
        assertTrue(isNonPositive.isSatisfiedBy(-5));
        assertFalse(isNonPositive.isSatisfiedBy(5));
    }
}
