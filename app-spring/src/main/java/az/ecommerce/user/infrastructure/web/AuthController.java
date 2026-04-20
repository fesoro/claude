package az.ecommerce.user.infrastructure.web;

import az.ecommerce.shared.application.bus.CommandBus;
import az.ecommerce.shared.application.bus.QueryBus;
import az.ecommerce.shared.domain.exception.DomainException;
import az.ecommerce.shared.infrastructure.api.ApiResponse;
import az.ecommerce.user.application.command.RegisterUserCommand;
import az.ecommerce.user.application.dto.UserDto;
import az.ecommerce.user.application.query.GetUserQuery;
import az.ecommerce.user.application.service.PasswordResetService;
import az.ecommerce.user.domain.repository.UserRepository;
import az.ecommerce.user.domain.valueobject.Email;
import az.ecommerce.user.infrastructure.web.dto.LoginRequest;
import az.ecommerce.user.infrastructure.web.dto.RegisterRequest;
import jakarta.validation.Valid;
import org.springframework.http.HttpStatus;
import org.springframework.http.ResponseEntity;
import org.springframework.security.core.annotation.AuthenticationPrincipal;
import org.springframework.web.bind.annotation.*;

import java.util.Map;
import java.util.UUID;

/**
 * Laravel: app/Http/Controllers/AuthController.php
 *
 * Endpoint mapping (Laravel routes/api.php):
 *   POST /api/auth/register       — public
 *   POST /api/auth/login          — public
 *   POST /api/auth/logout         — auth
 *   GET  /api/auth/me             — auth
 */
@RestController
@RequestMapping("/api/auth")
public class AuthController {

    private final CommandBus commandBus;
    private final QueryBus queryBus;
    private final UserRepository userRepository;
    private final JwtService jwtService;
    private final PasswordResetService passwordResetService;

    public AuthController(CommandBus commandBus, QueryBus queryBus,
                          UserRepository userRepository, JwtService jwtService,
                          PasswordResetService passwordResetService) {
        this.commandBus = commandBus;
        this.queryBus = queryBus;
        this.userRepository = userRepository;
        this.jwtService = jwtService;
        this.passwordResetService = passwordResetService;
    }

    @PostMapping("/forgot-password")
    public ApiResponse<Void> forgotPassword(@RequestBody Map<String, String> body) {
        passwordResetService.requestReset(body.get("email"));
        return ApiResponse.success(null, "Şifrə bərpa linki email ünvanına göndərildi");
    }

    @PostMapping("/reset-password")
    public ApiResponse<Void> resetPassword(@RequestBody Map<String, String> body) {
        passwordResetService.resetPassword(body.get("email"), body.get("token"), body.get("password"));
        return ApiResponse.success(null, "Şifrə yeniləndi");
    }

    @PostMapping("/register")
    public ResponseEntity<ApiResponse<Map<String, Object>>> register(@RequestBody @Valid RegisterRequest request) {
        UUID userId = commandBus.dispatch(
                new RegisterUserCommand(request.name(), request.email(), request.password()));
        String token = jwtService.issue(userId, request.email());
        return ResponseEntity.status(HttpStatus.CREATED).body(
                ApiResponse.success(Map.of("user_id", userId, "token", token), "Qeydiyyat uğurla tamamlandı"));
    }

    @PostMapping("/login")
    public ApiResponse<Map<String, String>> login(@RequestBody @Valid LoginRequest request) {
        var user = userRepository.findByEmail(new Email(request.email()))
                .orElseThrow(() -> new DomainException("Email və ya şifrə yanlışdır"));

        if (!user.verifyPassword(request.password())) {
            throw new DomainException("Email və ya şifrə yanlışdır");
        }

        // 2FA enable olunubsa, token vermirik — verify endpoint-ə yönəldirik
        if (user.twoFactorEnabled()) {
            return ApiResponse.success(Map.of("require_2fa", "true", "user_id", user.id().toString()));
        }

        String token = jwtService.issue(user.id().value(), user.email().value());
        return ApiResponse.success(Map.of("token", token));
    }

    @PostMapping("/logout")
    public ApiResponse<Void> logout() {
        // JWT stateless olduğu üçün server-side iş yoxdur.
        // Production-da token blacklist (Redis) əlavə oluna bilər.
        return ApiResponse.success(null, "Çıxış edildi");
    }

    @GetMapping("/me")
    public ApiResponse<UserDto> me(@AuthenticationPrincipal UUID userId) {
        UserDto dto = queryBus.ask(new GetUserQuery(userId));
        return ApiResponse.success(dto);
    }
}
