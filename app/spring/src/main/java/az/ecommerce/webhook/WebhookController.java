package az.ecommerce.webhook;

import az.ecommerce.shared.infrastructure.api.ApiResponse;
import jakarta.validation.constraints.NotBlank;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.security.core.annotation.AuthenticationPrincipal;
import org.springframework.web.bind.annotation.*;

import java.util.List;
import java.util.UUID;

/**
 * Laravel: app/Http/Controllers/WebhookController.php
 *   POST /api/webhooks
 *   GET /api/webhooks
 *   PATCH /api/webhooks/{id}
 *   DELETE /api/webhooks/{id}
 */
@RestController
@RequestMapping("/api/webhooks")
@PreAuthorize("isAuthenticated()")
public class WebhookController {

    private final WebhookRepository repository;

    public WebhookController(WebhookRepository repository) { this.repository = repository; }

    @PostMapping
    public ApiResponse<WebhookEntity> store(@AuthenticationPrincipal UUID userId, @RequestBody CreateBody body) {
        WebhookEntity w = new WebhookEntity();
        w.setId(UUID.randomUUID());
        w.setUserId(userId);
        w.setUrl(body.url());
        w.setSecret(UUID.randomUUID().toString());
        w.setEvents(body.events());
        w.setActive(true);
        return ApiResponse.success(repository.save(w), "Webhook yaradıldı");
    }

    @GetMapping
    public ApiResponse<List<WebhookEntity>> index(@AuthenticationPrincipal UUID userId) {
        return ApiResponse.success(repository.findByUserId(userId));
    }

    @PatchMapping("/{id}")
    public ApiResponse<WebhookEntity> toggle(@PathVariable UUID id) {
        WebhookEntity w = repository.findById(id).orElseThrow();
        w.setActive(!w.isActive());
        return ApiResponse.success(repository.save(w));
    }

    @DeleteMapping("/{id}")
    public ApiResponse<Void> destroy(@PathVariable UUID id) {
        repository.deleteById(id);
        return ApiResponse.success(null, "Silindi");
    }

    public record CreateBody(@NotBlank String url, @NotBlank String events) {}
}
