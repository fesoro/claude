# 056 — Password Hashing & BCrypt — Geniş İzah
**Səviyyə:** Orta


## Mündəricat
1. [Password Hashing nədir?](#password-hashing-nədir)
2. [BCrypt alqoritmi](#bcrypt-alqoritmi)
3. [Spring Security ilə BCrypt](#spring-security-ilə-bcrypt)
4. [Argon2 və SCrypt — müasir alternativlər](#argon2-və-scrypt--müasir-alternativlər)
5. [Password policy və validation](#password-policy-və-validation)
6. [Password reset flow](#password-reset-flow)
7. [İntervyu Sualları](#intervyu-sualları)

---

## Password Hashing nədir?

```
Şifrə saxlama — yanlış vs doğru yanaşmalar:

  ❌ Plain text: password = "mypassword123"
     → DB sızıntısı = bütün şifrələr açıq!

  ❌ Encryption (AES, RSA):
     → Şifrələnmiş data decrypt edilə bilər
     → Açar sızarsa → hamısı açılır

  ❌ Sadə hash (MD5, SHA-1, SHA-256):
     → Deterministic: eyni input → eyni hash
     → Rainbow table attack: milyonlarla hash əvvəlcədən hesablanıb
     → GPU ilə md5: milyard hash/saniyə

  ✅ Adaptive hashing (BCrypt, Argon2, SCrypt):
     → Salt: hər şifrəyə random data əlavə edilir
     → Yavaş: cost factor ilə compute time artırılır
     → GPU-ya davamlı (Argon2 memory-hard)

BCrypt hash strukturu:
  $2b$12$SALT_22_CHARS_HERE.HASH_31_CHARS_HERE

  $2b$  → BCrypt version
  $12$  → cost factor (work factor) — 2^12 = 4096 iteration
  SALT  → 22 Base64 char = 128 bit salt
  HASH  → 31 Base64 char = 184 bit hash

  Eyni şifrə, eyni cost:
  "password123" → $2b$12$abc...xyz  (hər dəfə fərqli salt!)
  "password123" → $2b$12$def...uvw  (fərqli hash, yenə doğru!)
```

---

## BCrypt alqoritmi

```
BCrypt necə işləyir:
  1. Random 128-bit salt yarat
  2. Cost factor (rounds): 2^cost iterasiya
  3. Blowfish cipher ilə şifrəni kriptir
  4. Salt + hash birləşdir

Cost factor seçimi:
  cost=10: ~100ms  → Web login (default Spring Security)
  cost=12: ~400ms  → Bank login
  cost=14: ~1.6s   → Çox kritik sistemlər

  Qayda: serverdə ~100-300ms olmalı
  → İstifadəçini narahat etmir
  → Attacker üçün çox yavaş (1 GPU = ~1000 cəhd/san cost=10-da)

Rainbow Table attack niyə işləmir:
  "password123" + salt="xyz" → hash_1
  "password123" + salt="abc" → hash_2 (tamamilə fərqli!)

  Rainbow table-da "password123"-ün hash-i saxlanılıb amma
  salt əlavəsi ilə tam fərqli hash alınır → table işə yaramır
```

```xml
<!-- pom.xml — Spring Security BCrypt üçün -->
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-security</artifactId>
</dependency>
<!-- Ya da yalnız BCrypt üçün -->
<dependency>
    <groupId>org.springframework.security</groupId>
    <artifactId>spring-security-crypto</artifactId>
</dependency>
```

---

## Spring Security ilə BCrypt

```java
// ─── BCryptPasswordEncoder konfiqurasiyası ────────────────
@Configuration
public class SecurityConfig {

    // Strength (cost factor): default 10, dəyişdirmək mümkün
    @Bean
    public PasswordEncoder passwordEncoder() {
        return new BCryptPasswordEncoder(12); // cost=12 → ~400ms
    }

    // Ya da random strength (test vaxtı üçün)
    @Bean
    @Profile("test")
    public PasswordEncoder testPasswordEncoder() {
        return new BCryptPasswordEncoder(4); // Test-lərdə sürətli
    }
}

// ─── User Registration ────────────────────────────────────
@Service
@Transactional
public class UserService {

    private final UserRepository userRepository;
    private final PasswordEncoder passwordEncoder;

    public UserResponse register(RegisterRequest request) {
        // Email artıq varsa
        if (userRepository.existsByEmail(request.email())) {
            throw new EmailAlreadyExistsException("Email artıq qeydiyyatdadır");
        }

        // Şifrəni hash et — heç vaxt plain text saxlama!
        String hashedPassword = passwordEncoder.encode(request.password());

        User user = User.builder()
            .email(request.email())
            .passwordHash(hashedPassword)  // Field adı: passwordHash, password yox!
            .name(request.name())
            .role(Role.USER)
            .createdAt(Instant.now())
            .build();

        user = userRepository.save(user);

        log.info("Yeni istifadəçi qeydiyyatdan keçdi: {}", user.getEmail());
        return UserResponse.from(user);
    }

    public void changePassword(String userId, ChangePasswordRequest request) {
        User user = userRepository.findById(userId)
            .orElseThrow(() -> new UserNotFoundException(userId));

        // Köhnə şifrəni yoxla
        if (!passwordEncoder.matches(request.currentPassword(), user.getPasswordHash())) {
            throw new InvalidPasswordException("Mövcud şifrə yanlışdır");
        }

        // Yeni şifrə köhnə ilə eyni olmamalıdır
        if (passwordEncoder.matches(request.newPassword(), user.getPasswordHash())) {
            throw new SamePasswordException("Yeni şifrə köhnə ilə eyni ola bilməz");
        }

        user.setPasswordHash(passwordEncoder.encode(request.newPassword()));
        userRepository.save(user);

        log.info("İstifadəçi {} şifrəsini dəyişdi", userId);
    }

    // ─── Login verification ──────────────────────────────
    public boolean verifyPassword(String rawPassword, String storedHash) {
        // BCrypt.matches() özü daxilən salt-ı hash-dən çıxarır
        return passwordEncoder.matches(rawPassword, storedHash);
        // HEÇ VAXT: storedHash.equals(passwordEncoder.encode(rawPassword))
        // Hər encode() fərqli salt → fərqli hash!
    }
}

// ─── Spring Security Authentication Provider ─────────────
@Component
public class CustomAuthenticationProvider implements AuthenticationProvider {

    private final UserDetailsService userDetailsService;
    private final PasswordEncoder passwordEncoder;

    @Override
    public Authentication authenticate(Authentication authentication)
            throws AuthenticationException {

        String email = authentication.getName();
        String rawPassword = authentication.getCredentials().toString();

        UserDetails user = userDetailsService.loadUserByUsername(email);

        // matches() — BCrypt-i otomatik handle edir
        if (!passwordEncoder.matches(rawPassword, user.getPassword())) {
            throw new BadCredentialsException("Email ya da şifrə yanlışdır");
        }

        return new UsernamePasswordAuthenticationToken(
            user, null, user.getAuthorities());
    }

    @Override
    public boolean supports(Class<?> authentication) {
        return UsernamePasswordAuthenticationToken.class.isAssignableFrom(authentication);
    }
}

// ─── Password Rehashing — cost factor artırıldıqda ───────
// Cost factor 10 → 12 yüksəldildikdə köhnə hash-lər
// hələ işləyir (backwards compatible), amma yeni hash lazım.

@Service
public class PasswordRehashService {

    private final UserRepository userRepository;
    private final PasswordEncoder newEncoder;

    // Login zamanı avtomatik rehash
    public void rehashIfNeeded(User user, String rawPassword) {
        BCryptPasswordEncoder encoder = (BCryptPasswordEncoder) newEncoder;

        // upgradeEncoding() → hash-in cost factor-i kifayətli deyilsə true
        if (encoder.upgradeEncoding(user.getPasswordHash())) {
            String newHash = encoder.encode(rawPassword);
            user.setPasswordHash(newHash);
            userRepository.save(user);
            log.info("İstifadəçi {} şifrəsi yenidən hash edildi", user.getId());
        }
    }
}
```

---

## Argon2 və SCrypt — müasir alternativlər

```java
// ─── Argon2 — OWASP tövsiyəsi (2023+) ───────────────────
// BCrypt-dən üstün: memory-hard → GPU/ASIC-ə daha davamlı

@Configuration
public class Argon2Config {

    @Bean
    public PasswordEncoder argon2PasswordEncoder() {
        // Argon2id (ən güvənli variant):
        // saltLength=16, hashLength=32, parallelism=1,
        // memory=65536 (64MB), iterations=3
        return Argon2PasswordEncoder.defaultsForSpringSecurity_v5_8();
    }
}

// Argon2 hash formatı:
// $argon2id$v=19$m=65536,t=3,p=1$SALT$HASH

// ─── SCrypt ───────────────────────────────────────────────
@Bean
public PasswordEncoder sCryptPasswordEncoder() {
    // CPU ve memory intensive
    return SCryptPasswordEncoder.defaultsForSpringSecurity_v5_8();
}

// ─── DelegatingPasswordEncoder — migration üçün ──────────
// Köhnə MD5/SHA hash-ləri BCrypt-ə migrate etmək

@Bean
public PasswordEncoder delegatingPasswordEncoder() {
    Map<String, PasswordEncoder> encoders = new HashMap<>();
    encoders.put("bcrypt", new BCryptPasswordEncoder());
    encoders.put("argon2", Argon2PasswordEncoder.defaultsForSpringSecurity_v5_8());
    encoders.put("noop", NoOpPasswordEncoder.getInstance()); // {noop} prefix üçün

    // Default encoder: yeni şifrələr üçün
    DelegatingPasswordEncoder delegating =
        new DelegatingPasswordEncoder("bcrypt", encoders);

    // Default nəticə: {bcrypt}$2b$10$...
    return delegating;
}

// DelegatingPasswordEncoder hash formatı:
// {bcrypt}$2b$10$...  → BCryptPasswordEncoder istifadə edir
// {argon2}$argon2id$... → Argon2 istifadə edir
// {noop}plaintext → NoOp (test)

// ─── Müqayisə cədvəli ─────────────────────────────────────
/*
Alqoritm     GPU-ya davamlı   Memory-hard   OWASP tövsiyəsi
BCrypt       Orta             ❌            Köhnə sistemlər
SCrypt       Yüksək           ✅            Qəbul edilir
Argon2id     Çox yüksək       ✅            ✅ 2023 tövsiyəsi
PBKDF2       Aşağı            ❌            FIPS tələb olunanda
*/
```

---

## Password policy və validation

```java
// ─── Password strength validator ─────────────────────────
@Component
public class PasswordPolicyValidator {

    private static final int MIN_LENGTH = 8;
    private static final int MAX_LENGTH = 128;

    // OWASP tövsiyəsinə əsasən
    public PasswordValidationResult validate(String password) {
        List<String> violations = new ArrayList<>();

        if (password.length() < MIN_LENGTH) {
            violations.add("Minimum " + MIN_LENGTH + " simvol olmalıdır");
        }
        if (password.length() > MAX_LENGTH) {
            violations.add("Maksimum " + MAX_LENGTH + " simvol ola bilər");
        }
        if (!password.matches(".*[A-Z].*")) {
            violations.add("Ən az bir böyük hərf (A-Z) olmalıdır");
        }
        if (!password.matches(".*[a-z].*")) {
            violations.add("Ən az bir kiçik hərf (a-z) olmalıdır");
        }
        if (!password.matches(".*\\d.*")) {
            violations.add("Ən az bir rəqəm (0-9) olmalıdır");
        }
        if (!password.matches(".*[!@#$%^&*()_+\\-=\\[\\]{};':\"\\\\|,.<>\\/?].*")) {
            violations.add("Ən az bir xüsusi simvol olmalıdır");
        }

        // Çox asan şifrə siyahısı
        if (isCommonPassword(password)) {
            violations.add("Bu şifrə çox yaygındır, başqa şifrə seçin");
        }

        return violations.isEmpty()
            ? PasswordValidationResult.valid()
            : PasswordValidationResult.invalid(violations);
    }

    private boolean isCommonPassword(String password) {
        Set<String> common = Set.of(
            "Password1!", "Passw0rd!", "Admin123!", "Welcome1!",
            "Test1234!", "Spring123!"
        );
        return common.contains(password);
    }
}

// ─── Custom constraint annotation ────────────────────────
@Constraint(validatedBy = PasswordConstraintValidator.class)
@Target(ElementType.FIELD)
@Retention(RetentionPolicy.RUNTIME)
public @interface ValidPassword {
    String message() default "Şifrə tələblərə uyğun deyil";
    Class<?>[] groups() default {};
    Class<? extends Payload>[] payload() default {};
}

@Component
public class PasswordConstraintValidator
        implements ConstraintValidator<ValidPassword, String> {

    private final PasswordPolicyValidator policyValidator;

    @Override
    public boolean isValid(String password, ConstraintValidatorContext context) {
        if (password == null) return false;

        PasswordValidationResult result = policyValidator.validate(password);

        if (!result.isValid()) {
            context.disableDefaultConstraintViolation();
            result.violations().forEach(violation ->
                context.buildConstraintViolationWithTemplate(violation)
                    .addConstraintViolation()
            );
            return false;
        }
        return true;
    }
}

// ─── Register Request ─────────────────────────────────────
public record RegisterRequest(
    @Email(message = "Düzgün email ünvanı daxil edin")
    @NotBlank
    String email,

    @ValidPassword
    @NotBlank
    String password,

    @NotBlank
    @Size(min = 2, max = 100)
    String name
) {}
```

---

## Password reset flow

```java
// ─── Secure Password Reset ────────────────────────────────
@Service
@Transactional
public class PasswordResetService {

    private final UserRepository userRepository;
    private final PasswordResetTokenRepository tokenRepository;
    private final PasswordEncoder passwordEncoder;
    private final EmailService emailService;

    private static final Duration TOKEN_VALIDITY = Duration.ofHours(1);

    // Step 1: Reset sorğusu
    public void initiateReset(String email) {
        // Email tapılmasa da eyni cavab qaytar (user enumeration-ı önlə)
        Optional<User> userOpt = userRepository.findByEmail(email);

        if (userOpt.isPresent()) {
            User user = userOpt.get();

            // Əvvəlki token-ları ləğv et
            tokenRepository.deleteByUserId(user.getId());

            // Kriptografik random token
            String rawToken = generateSecureToken();
            String tokenHash = hashToken(rawToken); // Token-ı da hash et!

            PasswordResetToken resetToken = PasswordResetToken.builder()
                .userId(user.getId())
                .tokenHash(tokenHash)
                .expiresAt(Instant.now().plus(TOKEN_VALIDITY))
                .used(false)
                .build();
            tokenRepository.save(resetToken);

            // Email-ə raw token göndər (hash yox!)
            emailService.sendPasswordResetEmail(email, rawToken);
        }

        // Həm tapıldı, həm tapılmadı — eyni cavab (timing attack)
        log.info("Password reset initiated for: {}", email);
    }

    // Step 2: Token yoxlama + şifrə dəyişmə
    public void resetPassword(String rawToken, String newPassword) {
        String tokenHash = hashToken(rawToken);

        PasswordResetToken resetToken = tokenRepository.findByTokenHash(tokenHash)
            .orElseThrow(() -> new InvalidTokenException("Token tapılmadı ya da istifadə edilib"));

        if (resetToken.isUsed()) {
            throw new InvalidTokenException("Token artıq istifadə edilib");
        }

        if (Instant.now().isAfter(resetToken.getExpiresAt())) {
            throw new TokenExpiredException("Token vaxtı keçib");
        }

        User user = userRepository.findById(resetToken.getUserId())
            .orElseThrow(() -> new UserNotFoundException("İstifadəçi tapılmadı"));

        // Şifrəni güncəllə
        user.setPasswordHash(passwordEncoder.encode(newPassword));
        userRepository.save(user);

        // Token-ı işlənmiş kimi qeyd et (yenidən istifadəni önlə)
        resetToken.setUsed(true);
        tokenRepository.save(resetToken);

        // Bütün aktiv session-ları sonlandır
        sessionService.invalidateAllSessions(user.getId());

        log.info("Şifrə uğurla sıfırlandı: userId={}", user.getId());
    }

    private String generateSecureToken() {
        byte[] tokenBytes = new byte[32];
        new SecureRandom().nextBytes(tokenBytes);
        return Base64.getUrlEncoder().withoutPadding().encodeToString(tokenBytes);
    }

    private String hashToken(String rawToken) {
        // Token-ı SHA-256 ilə hash et (DB-yə salt-sız saxla)
        // BCrypt lazım deyil — random token artıq güvənlidir
        MessageDigest digest;
        try {
            digest = MessageDigest.getInstance("SHA-256");
        } catch (NoSuchAlgorithmException e) {
            throw new RuntimeException(e);
        }
        byte[] hashBytes = digest.digest(rawToken.getBytes(StandardCharsets.UTF_8));
        return HexFormat.of().formatHex(hashBytes);
    }
}

// ─── Password Reset Token Entity ─────────────────────────
@Entity
@Table(name = "password_reset_tokens")
public class PasswordResetToken {

    @Id
    @GeneratedValue(strategy = GenerationType.UUID)
    private String id;

    @Column(name = "user_id", nullable = false)
    private String userId;

    @Column(name = "token_hash", nullable = false, unique = true)
    private String tokenHash; // SHA-256 hash, plain text deyil

    @Column(name = "expires_at", nullable = false)
    private Instant expiresAt;

    @Column(nullable = false)
    private boolean used;
}
```

---

## İntervyu Sualları

### 1. Şifrə niyə hash edilməlidir, niyə şifrələnməməlidir?
**Cavab:** **Şifrələmə** (encryption) reverse edilə bilər — açar (key) sızarsa bütün şifrələr açılır. **Hash** — one-way function, geri çevrilmir. Atacan DB-yə çatsa belə hash-dən şifrəni ala bilməz. Digər tərəfdən, sadə hash (MD5/SHA-256) rainbow table attack-ə açıqdır. BCrypt — salt əlavə edir (hər şifrə üçün random) və yavaş alqoritm (cost factor) istifadə edir, bu rainbow table-ı işə yaramaz edir.

### 2. BCrypt-in cost factor-i niyə vacibdir?
**Cavab:** Cost factor = 2^N iterasiya. Cost=10 → ~100ms, cost=12 → ~400ms. İstifadəçi login-i üçün 100-300ms qəbul ediləndir. Amma attacker üçün: 1 GPU ilə cost=10-da saniyədə ~1000 cəhd — parol sındırmaq praktiki olaraq qeyri-mümkün olur. Zamanla CPU gücləndikcə cost factor artırılmalıdır (upgradeEncoding() metodu ilə login zamanı avtomatik rehash).

### 3. passwordEncoder.matches() vs encode() müqayisəsi necə işləyir?
**Cavab:** `encode(rawPassword)` — hər dəfə yeni random salt ilə yeni hash yaradır. Buna görə eyni şifrənin iki encode-u fərqli hash verir. `matches(rawPassword, storedHash)` — stored hash-dən salt-ı çıxarır → raw password + salt → hash hesabla → stored hash ilə müqayisə et. Yanlış yanaşma: `encode(raw).equals(stored)` — bu HEÇ VAXT düzgün deyil, çünkü hər encode yeni salt istifadə edir.

### 4. Argon2 BCrypt-dən niyə üstündür?
**Cavab:** BCrypt GPU-ya qarşı nisbətən davamlı (Blowfish cache-sensitive), amma ASIC/FPGA ilə sürətləndirilə bilər. **Argon2id** (OWASP 2023 tövsiyəsi) — **memory-hard**: GB-larla RAM tələb edir, parallel GPU cəhdlərini çox bahalı edir. Parametrləri: memory (64MB+), iterations, parallelism. Modern sistemlər üçün Argon2id tövsiyə edilir. Köhnə sistemlərə BCrypt saxlanılır (backwards compatible).

### 5. Password reset token-ı necə güvənli saxlamaq lazımdır?
**Cavab:** Email-ə göndərilən raw token DB-də **plain text saxlanmamalıdır** — email server log-ları, DB sızıntısı riski var. Həll: raw token-ı SHA-256 ilə hash et, yalnız hash-i DB-ə yaz. Doğrulamada: gələn token-ı hash et → DB-dəki hash ilə müqayisə et. BCrypt lazım deyil — random 32-byte token artıq yüksək entropiyalidir, rainbow table işləmir. Əlavə tədbirlər: (1) Token 1 saat etibarlıdır; (2) İstifadə edildikdən sonra `used=true`; (3) Reset sonrası bütün session-lar ləğv edilir; (4) Email tapılmadıqda da eyni response (user enumeration-ı önlər).

*Son yenilənmə: 2026-04-10*
