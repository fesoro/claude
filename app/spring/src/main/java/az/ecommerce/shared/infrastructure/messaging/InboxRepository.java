package az.ecommerce.shared.infrastructure.messaging;

import org.springframework.data.jpa.repository.JpaRepository;

import java.util.UUID;

public interface InboxRepository extends JpaRepository<InboxMessageEntity, Long> {
    boolean existsByMessageId(UUID messageId);
}
