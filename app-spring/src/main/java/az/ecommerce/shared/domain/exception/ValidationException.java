package az.ecommerce.shared.domain.exception;

import java.util.Collections;
import java.util.Map;

/**
 * Laravel: ValidationException → 400/422 Bad Request
 */
public class ValidationException extends DomainException {

    private final Map<String, String> errors;

    public ValidationException(String message) {
        super(message);
        this.errors = Collections.emptyMap();
    }

    public ValidationException(Map<String, String> errors) {
        super("Validasiya xətası: " + errors);
        this.errors = errors;
    }

    public Map<String, String> getErrors() {
        return errors;
    }
}
