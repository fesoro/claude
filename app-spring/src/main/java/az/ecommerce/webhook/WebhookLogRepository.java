package az.ecommerce.webhook;

import org.springframework.data.jpa.repository.JpaRepository;

public interface WebhookLogRepository extends JpaRepository<WebhookLogEntity, Long> {
}
