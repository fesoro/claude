package az.ecommerce.admin;

import az.ecommerce.shared.infrastructure.api.ApiResponse;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.web.bind.annotation.*;

import java.util.List;
import java.util.Map;
import java.util.UUID;

/**
 * Laravel: app/Http/Controllers/FailedJobController.php
 *   - Failed job-ları idarə edir (DLQ)
 *
 * Spring: RabbitMQ DLQ (dead-letter exchange) → dead_letter_messages cədvəli
 * Real implementasiya RabbitAdmin və DLQ-dən oxuma tələb edir.
 */
@RestController
@RequestMapping("/api/admin/failed-jobs")
@PreAuthorize("hasRole('ADMIN')")
public class FailedJobController {

    @GetMapping
    public ApiResponse<List<Map<String, Object>>> index() {
        // dead_letter_messages cədvəlindən oxuma
        return ApiResponse.success(List.of());
    }

    @GetMapping("/{id}")
    public ApiResponse<Map<String, Object>> show(@PathVariable UUID id) {
        return ApiResponse.success(Map.of("id", id, "status", "failed"));
    }

    @PostMapping("/{id}/retry")
    public ApiResponse<Void> retry(@PathVariable UUID id) {
        // Republish to original queue
        return ApiResponse.success(null, "Yenidən cəhd edildi");
    }

    @PostMapping("/retry-all")
    public ApiResponse<Void> retryAll() {
        return ApiResponse.success(null, "Bütün failed job-lar yenidən cəhd edildi");
    }

    @DeleteMapping("/{id}")
    public ApiResponse<Void> destroy(@PathVariable UUID id) {
        return ApiResponse.success(null, "Silindi");
    }

    @DeleteMapping
    public ApiResponse<Void> flush() {
        return ApiResponse.success(null, "Bütün failed job-lar silindi");
    }
}
