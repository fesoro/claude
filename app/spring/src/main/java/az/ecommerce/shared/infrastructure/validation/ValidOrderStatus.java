package az.ecommerce.shared.infrastructure.validation;

import az.ecommerce.order.domain.enums.OrderStatusEnum;
import jakarta.validation.Constraint;
import jakarta.validation.ConstraintValidator;
import jakarta.validation.ConstraintValidatorContext;
import jakarta.validation.Payload;

import java.lang.annotation.ElementType;
import java.lang.annotation.Retention;
import java.lang.annotation.RetentionPolicy;
import java.lang.annotation.Target;

/**
 * Laravel: app/Rules/ValidOrderStatusRule.php
 * String → OrderStatusEnum yoxlama.
 */
@Target({ElementType.FIELD, ElementType.PARAMETER, ElementType.RECORD_COMPONENT})
@Retention(RetentionPolicy.RUNTIME)
@Constraint(validatedBy = ValidOrderStatus.Validator.class)
public @interface ValidOrderStatus {
    String message() default "Yanlış sifariş statusu";
    Class<?>[] groups() default {};
    Class<? extends Payload>[] payload() default {};

    class Validator implements ConstraintValidator<ValidOrderStatus, String> {
        @Override
        public boolean isValid(String value, ConstraintValidatorContext context) {
            if (value == null) return true;
            try {
                OrderStatusEnum.valueOf(value.toUpperCase());
                return true;
            } catch (IllegalArgumentException ex) {
                return false;
            }
        }
    }
}
