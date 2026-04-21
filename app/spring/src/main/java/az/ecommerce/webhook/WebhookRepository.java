package az.ecommerce.webhook;

import org.springframework.data.jpa.repository.JpaRepository;

import java.util.List;
import java.util.UUID;

public interface WebhookRepository extends JpaRepository<WebhookEntity, UUID> {
    List<WebhookEntity> findByUserId(UUID userId);
}
