package az.ecommerce.product.domain.valueobject;

/**
 * Laravel: src/Product/Domain/Enums/CurrencyEnum.php
 * Spring: standart Java enum.
 */
public enum Currency {
    USD, EUR, AZN;

    public static Currency of(String code) {
        return Currency.valueOf(code.toUpperCase());
    }
}
