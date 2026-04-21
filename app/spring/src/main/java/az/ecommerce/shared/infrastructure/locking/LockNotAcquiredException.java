package az.ecommerce.shared.infrastructure.locking;

import az.ecommerce.shared.domain.exception.DomainException;

public class LockNotAcquiredException extends DomainException {
    public LockNotAcquiredException(String message) {
        super(message);
    }
}
