package az.ecommerce.shared.domain;

import jakarta.persistence.MappedSuperclass;
import jakarta.persistence.Version;

/**
 * Laravel: VersionedAggregateRoot.php (optimistic locking)
 * Spring/JPA: @Version annotation Hibernate-ə optimistic locking-i avtomatik dəstəkləyir.
 *
 * 2 user eyni vaxtda update etsə, ikinci save() OptimisticLockException atacaq.
 * RetryOnConcurrencyMiddleware bu exception-u tutub retry edir.
 */
@MappedSuperclass
public abstract class VersionedAggregateRoot extends AggregateRoot {

    @Version
    private Long version;

    public Long getVersion() {
        return version;
    }
}
