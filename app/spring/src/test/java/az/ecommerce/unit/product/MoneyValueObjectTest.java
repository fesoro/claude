package az.ecommerce.unit.product;

import az.ecommerce.product.domain.valueobject.Currency;
import az.ecommerce.product.domain.valueobject.Money;
import az.ecommerce.shared.domain.exception.DomainException;
import org.junit.jupiter.api.Test;

import static org.junit.jupiter.api.Assertions.*;

/**
 * Laravel: tests/Unit/Product/MoneyValueObjectTest.php
 */
class MoneyValueObjectTest {

    @Test
    void shouldCreateMoneyWithValidValues() {
        Money money = Money.of(2599, Currency.AZN);
        assertEquals(2599, money.amount());
        assertEquals(Currency.AZN, money.currency());
    }

    @Test
    void shouldRejectNegativeAmount() {
        assertThrows(DomainException.class, () -> Money.of(-100, Currency.AZN));
    }

    @Test
    void shouldAddSameCurrency() {
        Money a = Money.of(1000, Currency.AZN);
        Money b = Money.of(500, Currency.AZN);
        assertEquals(1500, a.add(b).amount());
    }

    @Test
    void shouldRejectAdditionOfDifferentCurrencies() {
        Money usd = Money.of(100, Currency.USD);
        Money azn = Money.of(100, Currency.AZN);
        assertThrows(DomainException.class, () -> usd.add(azn));
    }

    @Test
    void shouldMultiplyByPositiveFactor() {
        Money price = Money.of(2599, Currency.AZN);
        assertEquals(7797, price.multiply(3).amount());
    }

    @Test
    void shouldFormatToString() {
        assertEquals("25.99 AZN", Money.of(2599, Currency.AZN).toString());
    }
}
