package az.ecommerce.shared.infrastructure.validation;

import jakarta.validation.Constraint;
import jakarta.validation.ConstraintValidator;
import jakarta.validation.ConstraintValidatorContext;
import jakarta.validation.Payload;

import java.lang.annotation.ElementType;
import java.lang.annotation.Retention;
import java.lang.annotation.RetentionPolicy;
import java.lang.annotation.Target;

/**
 * Laravel: app/Rules/ValidMoneyRule.php
 * Pul məbləğini yoxlayır (qəpiklə, müsbət).
 */
@Target({ElementType.FIELD, ElementType.PARAMETER, ElementType.RECORD_COMPONENT})
@Retention(RetentionPolicy.RUNTIME)
@Constraint(validatedBy = ValidMoney.Validator.class)
public @interface ValidMoney {
    String message() default "Pul məbləği müsbət olmalıdır";
    Class<?>[] groups() default {};
    Class<? extends Payload>[] payload() default {};

    class Validator implements ConstraintValidator<ValidMoney, Long> {
        @Override
        public boolean isValid(Long value, ConstraintValidatorContext context) {
            return value == null || value >= 0;
        }
    }
}
