package az.ecommerce.shared.infrastructure.auth;

import com.warrenstrange.googleauth.GoogleAuthenticator;
import com.warrenstrange.googleauth.GoogleAuthenticatorKey;
import org.springframework.stereotype.Service;

import java.security.SecureRandom;
import java.util.ArrayList;
import java.util.List;

/**
 * Laravel: src/Shared/Infrastructure/Auth/TwoFactorService.php (TOTP)
 * Spring: GoogleAuth library — RFC 6238 TOTP standardı.
 *
 * Flow:
 *   1. enable() → secret yaradır, QR URL qaytarır
 *   2. confirm(code) → verify, backup codes generate
 *   3. verify(code) → login zamanı
 */
@Service
public class TwoFactorService {

    private final GoogleAuthenticator gAuth = new GoogleAuthenticator();
    private final SecureRandom secureRandom = new SecureRandom();

    public TwoFactorSetup generateSecret(String userEmail) {
        GoogleAuthenticatorKey key = gAuth.createCredentials();
        String otpAuthUrl = String.format(
                "otpauth://totp/Ecommerce:%s?secret=%s&issuer=Ecommerce",
                userEmail, key.getKey());
        return new TwoFactorSetup(key.getKey(), otpAuthUrl);
    }

    public boolean verifyCode(String secret, int code) {
        return gAuth.authorize(secret, code);
    }

    public List<String> generateBackupCodes(int count) {
        List<String> codes = new ArrayList<>(count);
        for (int i = 0; i < count; i++) {
            codes.add(String.format("%08d", secureRandom.nextInt(100_000_000)));
        }
        return codes;
    }

    public record TwoFactorSetup(String secret, String otpAuthUrl) {}
}
