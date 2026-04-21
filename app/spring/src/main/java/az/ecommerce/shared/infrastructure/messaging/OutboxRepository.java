package az.ecommerce.shared.infrastructure.messaging;

import org.springframework.data.domain.Pageable;
import org.springframework.data.jpa.repository.JpaRepository;

import java.util.List;

public interface OutboxRepository extends JpaRepository<OutboxMessageEntity, Long> {

    List<OutboxMessageEntity> findByPublishedFalseOrderByCreatedAtAsc(Pageable pageable);

    long countByPublishedFalse();
}
