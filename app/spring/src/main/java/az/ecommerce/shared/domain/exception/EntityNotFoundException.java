package az.ecommerce.shared.domain.exception;

/**
 * Laravel: EntityNotFoundException → 404
 */
public class EntityNotFoundException extends DomainException {
    public EntityNotFoundException(String entityType, String id) {
        super(String.format("%s tapılmadı: id=%s", entityType, id));
    }
}
