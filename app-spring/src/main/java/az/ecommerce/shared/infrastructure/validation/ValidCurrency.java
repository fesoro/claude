package az.ecommerce.shared.infrastructure.validation;

import jakarta.validation.Constraint;
import jakarta.validation.ConstraintValidator;
import jakarta.validation.ConstraintValidatorContext;
import jakarta.validation.Payload;

import java.lang.annotation.ElementType;
import java.lang.annotation.Retention;
import java.lang.annotation.RetentionPolicy;
import java.lang.annotation.Target;
import java.util.Set;

/**
 * Laravel: app/Rules/ValidMoneyRule.php-də valyuta hissəsi.
 * Yalnız USD/EUR/AZN qəbul edir.
 */
@Target({ElementType.FIELD, ElementType.PARAMETER, ElementType.RECORD_COMPONENT})
@Retention(RetentionPolicy.RUNTIME)
@Constraint(validatedBy = ValidCurrency.Validator.class)
public @interface ValidCurrency {
    String message() default "valyuta dəstəklənmir (yalnız USD, EUR, AZN)";
    Class<?>[] groups() default {};
    Class<? extends Payload>[] payload() default {};

    class Validator implements ConstraintValidator<ValidCurrency, String> {
        private static final Set<String> ALLOWED = Set.of("USD", "EUR", "AZN");

        @Override
        public boolean isValid(String value, ConstraintValidatorContext context) {
            return value == null || ALLOWED.contains(value.toUpperCase());
        }
    }
}
