package az.ecommerce.user.infrastructure.web;

import az.ecommerce.shared.domain.exception.DomainException;
import az.ecommerce.shared.domain.exception.EntityNotFoundException;
import az.ecommerce.shared.infrastructure.api.ApiResponse;
import az.ecommerce.shared.infrastructure.auth.TwoFactorService;
import az.ecommerce.user.domain.User;
import az.ecommerce.user.domain.repository.UserRepository;
import az.ecommerce.user.domain.valueobject.UserId;
import org.springframework.security.core.annotation.AuthenticationPrincipal;
import org.springframework.web.bind.annotation.*;

import java.util.List;
import java.util.Map;
import java.util.UUID;

/**
 * Laravel: app/Http/Controllers/TwoFactorController.php
 *
 * POST /api/auth/2fa/enable, /confirm, /disable, /verify
 */
@RestController
@RequestMapping("/api/auth/2fa")
public class TwoFactorController {

    private final UserRepository repository;
    private final TwoFactorService twoFactorService;
    private final JwtService jwtService;

    public TwoFactorController(UserRepository repository, TwoFactorService twoFactorService,
                               JwtService jwtService) {
        this.repository = repository;
        this.twoFactorService = twoFactorService;
        this.jwtService = jwtService;
    }

    @PostMapping("/enable")
    public ApiResponse<Map<String, Object>> enable(@AuthenticationPrincipal UUID userId) {
        User user = repository.findById(new UserId(userId))
                .orElseThrow(() -> new EntityNotFoundException("User", userId.toString()));
        var setup = twoFactorService.generateSecret(user.email().value());
        return ApiResponse.success(Map.of(
                "secret", setup.secret(),
                "qr_code_url", setup.otpAuthUrl()
        ), "Bu QR-i scan edin və code ilə təsdiq edin");
    }

    @PostMapping("/confirm")
    public ApiResponse<List<String>> confirm(@AuthenticationPrincipal UUID userId,
                                              @RequestBody Map<String, Object> body) {
        String secret = (String) body.get("secret");
        Object rawCode = body.get("code");
        if (rawCode == null) throw new DomainException("'code' sahəsi tələb olunur");
        int code = Integer.parseInt(rawCode.toString());

        if (!twoFactorService.verifyCode(secret, code)) {
            throw new DomainException("2FA kodu yanlışdır");
        }

        User user = repository.findById(new UserId(userId))
                .orElseThrow(() -> new EntityNotFoundException("User", userId.toString()));
        List<String> backupCodes = twoFactorService.generateBackupCodes(8);
        user.enableTwoFactor(secret, backupCodes);
        repository.save(user);

        return ApiResponse.success(backupCodes, "2FA aktiv edildi. Backup kodları saxlayın!");
    }

    @PostMapping("/disable")
    public ApiResponse<Void> disable(@AuthenticationPrincipal UUID userId) {
        User user = repository.findById(new UserId(userId))
                .orElseThrow(() -> new EntityNotFoundException("User", userId.toString()));
        user.disableTwoFactor();
        repository.save(user);
        return ApiResponse.success(null, "2FA deaktiv edildi");
    }

    @PostMapping("/verify")
    public ApiResponse<Map<String, String>> verify(@RequestBody Map<String, Object> body) {
        UUID userId = UUID.fromString((String) body.get("user_id"));
        Object rawCode = body.get("code");
        if (rawCode == null) throw new DomainException("'code' sahəsi tələb olunur");
        int code = Integer.parseInt(rawCode.toString());

        User user = repository.findById(new UserId(userId))
                .orElseThrow(() -> new EntityNotFoundException("User", userId.toString()));

        if (!twoFactorService.verifyCode(user.twoFactorSecret(), code)) {
            throw new DomainException("2FA kodu yanlışdır");
        }

        String token = jwtService.issue(user.id().value(), user.email().value());
        return ApiResponse.success(Map.of("token", token));
    }
}
