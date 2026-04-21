package az.ecommerce.shared.infrastructure.api;

import com.fasterxml.jackson.annotation.JsonInclude;

import java.util.Map;

/**
 * Laravel: app/Http/Resources/ApiResponse.php
 *   Standart cavab format: {success, message, data, meta, errors}
 *
 * Spring: Java record — immutable, JSON serialization auto.
 */
@JsonInclude(JsonInclude.Include.NON_NULL)
public record ApiResponse<T>(
        boolean success,
        String message,
        T data,
        Map<String, Object> meta,
        Map<String, String> errors
) {
    public static <T> ApiResponse<T> success(T data) {
        return new ApiResponse<>(true, null, data, null, null);
    }

    public static <T> ApiResponse<T> success(T data, String message) {
        return new ApiResponse<>(true, message, data, null, null);
    }

    public static <T> ApiResponse<T> paginated(T data, Map<String, Object> meta) {
        return new ApiResponse<>(true, null, data, meta, null);
    }

    public static <T> ApiResponse<T> error(String message) {
        return new ApiResponse<>(false, message, null, null, null);
    }

    public static <T> ApiResponse<T> validationError(Map<String, String> errors) {
        return new ApiResponse<>(false, "Validasiya xətası", null, null, errors);
    }
}
