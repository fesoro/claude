package az.ecommerce.notification.infrastructure.web;

import az.ecommerce.notification.infrastructure.preference.NotificationPreferenceEntity;
import az.ecommerce.notification.infrastructure.preference.NotificationPreferenceRepository;
import az.ecommerce.shared.infrastructure.api.ApiResponse;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.security.core.annotation.AuthenticationPrincipal;
import org.springframework.web.bind.annotation.*;

import java.util.List;
import java.util.UUID;

/**
 * Laravel: app/Http/Controllers/NotificationPreferenceController.php
 *   GET /api/notifications/preferences
 *   PUT /api/notifications/preferences/{eventType}
 */
@RestController
@RequestMapping("/api/notifications/preferences")
@PreAuthorize("isAuthenticated()")
public class NotificationPreferenceController {

    private final NotificationPreferenceRepository repository;

    public NotificationPreferenceController(NotificationPreferenceRepository repository) {
        this.repository = repository;
    }

    @GetMapping
    public ApiResponse<List<NotificationPreferenceEntity>> index(@AuthenticationPrincipal UUID userId) {
        return ApiResponse.success(repository.findByUserId(userId));
    }

    @PutMapping("/{eventType}")
    public ApiResponse<NotificationPreferenceEntity> update(@AuthenticationPrincipal UUID userId,
                                                            @PathVariable String eventType,
                                                            @RequestBody PreferenceBody body) {
        NotificationPreferenceEntity entity = repository.findByUserIdAndEventType(userId, eventType)
                .orElseGet(() -> {
                    NotificationPreferenceEntity e = new NotificationPreferenceEntity();
                    e.setUserId(userId);
                    e.setEventType(eventType);
                    return e;
                });
        entity.setEmailEnabled(body.email());
        entity.setSmsEnabled(body.sms());
        entity.setPushEnabled(body.push());
        return ApiResponse.success(repository.save(entity), "Üstünlüklər yeniləndi");
    }

    public record PreferenceBody(boolean email, boolean sms, boolean push) {}
}
