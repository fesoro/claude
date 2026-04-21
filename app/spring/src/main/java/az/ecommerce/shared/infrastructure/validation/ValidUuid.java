package az.ecommerce.shared.infrastructure.validation;

import jakarta.validation.Constraint;
import jakarta.validation.ConstraintValidator;
import jakarta.validation.ConstraintValidatorContext;
import jakarta.validation.Payload;

import java.lang.annotation.ElementType;
import java.lang.annotation.Retention;
import java.lang.annotation.RetentionPolicy;
import java.lang.annotation.Target;
import java.util.regex.Pattern;

/**
 * Laravel: app/Rules/ValidUuidRule.php
 *
 * İstifadə:
 *   public record CreateOrderCommand(@ValidUuid String userId, ...) {}
 */
@Target({ElementType.FIELD, ElementType.PARAMETER, ElementType.RECORD_COMPONENT})
@Retention(RetentionPolicy.RUNTIME)
@Constraint(validatedBy = ValidUuid.Validator.class)
public @interface ValidUuid {
    String message() default "düzgün UUID formatında olmalıdır";
    Class<?>[] groups() default {};
    Class<? extends Payload>[] payload() default {};

    class Validator implements ConstraintValidator<ValidUuid, String> {
        private static final Pattern UUID_PATTERN = Pattern.compile(
                "^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$",
                Pattern.CASE_INSENSITIVE);

        @Override
        public boolean isValid(String value, ConstraintValidatorContext context) {
            if (value == null) return true;   // @NotNull ayrıca yoxlayır
            return UUID_PATTERN.matcher(value).matches();
        }
    }
}
