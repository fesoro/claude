package az.ecommerce.shared.domain.exception;

/**
 * Laravel: src/Shared/Domain/Exceptions/DomainException.php
 * Spring: RuntimeException-dən törəyir.
 * GlobalExceptionHandler bunu 422 Unprocessable Entity-yə çevirir.
 */
public class DomainException extends RuntimeException {
    public DomainException(String message) {
        super(message);
    }

    public DomainException(String message, Throwable cause) {
        super(message, cause);
    }
}
