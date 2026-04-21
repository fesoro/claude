package az.ecommerce.product.domain.valueobject;

import az.ecommerce.shared.domain.ValueObject;
import az.ecommerce.shared.domain.exception.DomainException;

/**
 * === MONEY VALUE OBJECT (ən vacib VO) ===
 *
 * Laravel: src/Product/Domain/ValueObjects/Money.php
 *   - amount qəpiklə (cent) saxlanır — float xətalarından qorunmaq üçün
 *
 * Spring: Java record + compact constructor + immutable arithmetic.
 *
 * NÜMUNƏ:
 *   var price = Money.of(2599, Currency.AZN);  // 25.99 AZN
 *   var withTax = price.add(Money.of(519, Currency.AZN));  // 5.19 AZN tax
 *   var total = price.multiply(3);  // 3 ədəd
 */
public record Money(long amount, Currency currency) implements ValueObject {

    public Money {
        if (amount < 0) {
            throw new DomainException("Money mənfi ola bilməz: " + amount);
        }
        if (currency == null) {
            throw new DomainException("Currency null ola bilməz");
        }
    }

    public static Money of(long amount, Currency currency) {
        return new Money(amount, currency);
    }

    public static Money zero(Currency currency) {
        return new Money(0, currency);
    }

    public Money add(Money other) {
        requireSameCurrency(other);
        return new Money(this.amount + other.amount, this.currency);
    }

    public Money subtract(Money other) {
        requireSameCurrency(other);
        return new Money(this.amount - other.amount, this.currency);
    }

    public Money multiply(int factor) {
        if (factor < 0) throw new DomainException("Factor mənfi ola bilməz");
        return new Money(this.amount * factor, this.currency);
    }

    public boolean isZero() {
        return amount == 0;
    }

    public boolean isGreaterThan(Money other) {
        requireSameCurrency(other);
        return this.amount > other.amount;
    }

    private void requireSameCurrency(Money other) {
        if (this.currency != other.currency) {
            throw new DomainException(String.format(
                    "Müxtəlif valyutalar üzərində əməliyyat: %s vs %s", this.currency, other.currency));
        }
    }

    /** Display: "25.99 AZN" */
    @Override
    public String toString() {
        return String.format("%d.%02d %s", amount / 100, amount % 100, currency);
    }
}
