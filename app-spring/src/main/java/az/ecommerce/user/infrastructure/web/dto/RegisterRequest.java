package az.ecommerce.user.infrastructure.web.dto;

import jakarta.validation.constraints.Email;
import jakarta.validation.constraints.NotBlank;
import jakarta.validation.constraints.Size;

/**
 * Laravel: app/Http/Requests/RegisterRequest.php
 * Spring: @Valid annotation ilə avtomatik validate olunur (@RequestBody-də).
 */
public record RegisterRequest(
        @NotBlank @Size(min = 2, max = 100) String name,
        @NotBlank @Email String email,
        @NotBlank @Size(min = 8) String password
) {}
