package az.ecommerce.notification.infrastructure.preference;

import org.springframework.data.jpa.repository.JpaRepository;

import java.util.List;
import java.util.Optional;
import java.util.UUID;

public interface NotificationPreferenceRepository extends JpaRepository<NotificationPreferenceEntity, Long> {
    List<NotificationPreferenceEntity> findByUserId(UUID userId);
    Optional<NotificationPreferenceEntity> findByUserIdAndEventType(UUID userId, String eventType);
}
