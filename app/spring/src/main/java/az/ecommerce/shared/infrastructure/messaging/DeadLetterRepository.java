package az.ecommerce.shared.infrastructure.messaging;

import org.springframework.data.jpa.repository.JpaRepository;

import java.util.List;

public interface DeadLetterRepository extends JpaRepository<DeadLetterMessageEntity, Long> {
    List<DeadLetterMessageEntity> findByRetriedFalse();
    long countByRetriedFalse();
}
