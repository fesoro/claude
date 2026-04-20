package az.ecommerce.shared.application.middleware;

import az.ecommerce.shared.application.bus.Command;
import az.ecommerce.shared.domain.exception.ValidationException;
import jakarta.validation.ConstraintViolation;
import jakarta.validation.Validator;
import org.springframework.stereotype.Component;

import java.util.HashMap;
import java.util.Map;
import java.util.Set;
import java.util.stream.Collectors;

/**
 * Pipeline mövqeyi: 3-cü.
 * Command-ın Bean Validation annotation-larını yoxlayır.
 * Laravel: ValidationMiddleware.php
 */
@Component
public class ValidationMiddleware implements CommandMiddleware {

    private final Validator validator;

    public ValidationMiddleware(Validator validator) {
        this.validator = validator;
    }

    @Override
    public <R> R handle(Command<R> command, CommandPipeline<R> next) {
        Set<ConstraintViolation<Command<R>>> violations = validator.validate(command);
        if (!violations.isEmpty()) {
            Map<String, String> errors = violations.stream()
                    .collect(Collectors.toMap(
                            v -> v.getPropertyPath().toString(),
                            ConstraintViolation::getMessage,
                            (a, b) -> a,
                            HashMap::new));
            throw new ValidationException(errors);
        }
        return next.proceed(command);
    }

    @Override
    public int order() {
        return 30;
    }
}
