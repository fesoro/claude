package com.example.ecommerce.domain.product;

import jakarta.persistence.Embeddable;
import java.math.BigDecimal;
import java.util.Objects;

// Value Object: immutable, identity yoxdur — yalnız dəyəri var
@Embeddable
public final class Money {

    private final BigDecimal amount;
    private final String currency;

    protected Money() { this.amount = null; this.currency = null; } // JPA üçün

    public Money(BigDecimal amount, String currency) {
        if (amount.compareTo(BigDecimal.ZERO) < 0) {
            throw new IllegalArgumentException("Qiymət mənfi ola bilməz");
        }
        this.amount   = amount;
        this.currency = currency;
    }

    public static Money of(BigDecimal amount) {
        return new Money(amount, "USD");
    }

    public Money multiply(int qty) {
        return new Money(amount.multiply(BigDecimal.valueOf(qty)), currency);
    }

    public Money add(Money other) {
        if (!currency.equals(other.currency)) throw new IllegalArgumentException("Valyuta fərqlidir");
        return new Money(amount.add(other.amount), currency);
    }

    public BigDecimal getAmount()  { return amount; }
    public String getCurrency()    { return currency; }

    @Override
    public boolean equals(Object o) {
        if (!(o instanceof Money m)) return false;
        return Objects.equals(amount, m.amount) && Objects.equals(currency, m.currency);
    }

    @Override public int hashCode() { return Objects.hash(amount, currency); }
    @Override public String toString() { return amount + " " + currency; }
}
