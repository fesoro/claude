# 059 — Spring Security JWT
**Səviyyə:** İrəli


## Mündəricat
1. [JWT Strukturu](#jwt-strukturu)
2. [JWT Kitabxanaları](#jwt-kitabxanaları)
3. [JWT Yaratma və Yoxlama](#jwt-yaratma-və-yoxlama)
4. [JwtAuthenticationFilter](#jwtauthenticationfilter)
5. [Token Validation](#token-validation)
6. [JWT SecurityContext-ə Yerləşdirmək](#jwt-securitycontext-ə-yerləşdirmək)
7. [Stateless Session](#stateless-session)
8. [Refresh Token Strategiyası](#refresh-token-strategiyası)
9. [İntervyu Sualları](#i̇ntervyu-sualları)

---

## JWT Strukturu

JWT (JSON Web Token) — üç hissədən ibarət Base64URL ilə kodlanmış token formatıdır:

```
eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiJ1c2VyQGV4YW1wbGUuY29tIiwiaWF0IjoxNzE0NTAwMDAwLCJleHAiOjE3MTQ1ODY0MDB9.SflKxwRJSMeKKF2QT4fwpMeJf36POk6yJV_adQssw5c

    HEADER          .        PAYLOAD          .      SIGNATURE
```

### Header (Başlıq)

```json
{
  "alg": "HS256",   // İmzalama alqoritmi: HS256, RS256, ES256
  "typ": "JWT"      // Token növü
}
```

### Payload (Yük) — Claims

```json
{
  // Standart (registered) claims:
  "sub": "user@example.com",   // Subject - istifadəçi identifikatoru
  "iat": 1714500000,           // Issued At - yaradılma vaxtı (Unix timestamp)
  "exp": 1714586400,           // Expiration - bitmə vaxtı
  "iss": "myapp.com",          // Issuer - kim yaratdı
  "aud": "myapp-client",       // Audience - kim üçün

  // Custom claims:
  "roles": ["ROLE_USER", "ROLE_ADMIN"],
  "userId": 123,
  "email": "user@example.com"
}
```

### Signature (İmza)

```
HMACSHA256(
    base64UrlEncode(header) + "." + base64UrlEncode(payload),
    secretKey
)
```

**Vacib xatırlatma:** JWT payload **şifrələnmir**, yalnız imzalanır! Base64 ilə kodlanmış məlumat hər kəs tərəfindən oxuna bilər. Həssas məlumatları (şifrə, kredit kartı) payload-a yazma!

---

## JWT Kitabxanaları

### JJWT (Java JWT)

```xml
<!-- pom.xml -->
<dependency>
    <groupId>io.jsonwebtoken</groupId>
    <artifactId>jjwt-api</artifactId>
    <version>0.12.3</version>
</dependency>
<dependency>
    <groupId>io.jsonwebtoken</groupId>
    <artifactId>jjwt-impl</artifactId>
    <version>0.12.3</version>
    <scope>runtime</scope>
</dependency>
<dependency>
    <groupId>io.jsonwebtoken</groupId>
    <artifactId>jjwt-jackson</artifactId>
    <version>0.12.3</version>
    <scope>runtime</scope>
</dependency>
```

### application.yml konfiqurasiyası

```yaml
application:
  security:
    jwt:
      # Ən azı 256-bit (32 simvol) gizli açar
      secret-key: "404E635266556A586E3272357538782F413F4428472B4B6250645367566B5970"
      # Access token müddəti: 15 dəqiqə (millisaniyə ilə)
      expiration: 900000
      # Refresh token müddəti: 7 gün
      refresh-token:
        expiration: 604800000
```

---

## JWT Yaratma və Yoxlama

```java
@Service
public class JwtService {

    @Value("${application.security.jwt.secret-key}")
    private String secretKey;

    @Value("${application.security.jwt.expiration}")
    private long jwtExpiration;

    @Value("${application.security.jwt.refresh-token.expiration}")
    private long refreshExpiration;

    // Gizli açarı SecretKey obyektinə çevir
    private SecretKey getSigningKey() {
        byte[] keyBytes = Decoders.BASE64.decode(secretKey);
        return Keys.hmacShaKeyFor(keyBytes);
    }

    // Access token yarat
    public String generateToken(UserDetails userDetails) {
        return generateToken(new HashMap<>(), userDetails);
    }

    // Əlavə claims ilə token yarat
    public String generateToken(Map<String, Object> extraClaims, UserDetails userDetails) {
        return buildToken(extraClaims, userDetails, jwtExpiration);
    }

    // Refresh token yarat
    public String generateRefreshToken(UserDetails userDetails) {
        return buildToken(new HashMap<>(), userDetails, refreshExpiration);
    }

    private String buildToken(Map<String, Object> extraClaims,
                               UserDetails userDetails,
                               long expiration) {
        // İstifadəçinin rollarını claim kimi əlavə et
        List<String> roles = userDetails.getAuthorities().stream()
            .map(GrantedAuthority::getAuthority)
            .collect(Collectors.toList());

        return Jwts.builder()
            .claims(extraClaims)
            .subject(userDetails.getUsername())          // sub claim
            .issuedAt(new Date(System.currentTimeMillis())) // iat claim
            .expiration(new Date(System.currentTimeMillis() + expiration)) // exp claim
            .claim("roles", roles)                       // custom claim
            .signWith(getSigningKey(), Jwts.SIG.HS256)   // imzala
            .compact();                                   // string-ə çevir
    }

    // Token-dən istifadəçi adını çıxar
    public String extractUsername(String token) {
        return extractClaim(token, Claims::getSubject);
    }

    // Token-dən bitmə vaxtını çıxar
    public Date extractExpiration(String token) {
        return extractClaim(token, Claims::getExpiration);
    }

    // Token-dən istənilən claim-i çıxar
    public <T> T extractClaim(String token, Function<Claims, T> claimsResolver) {
        final Claims claims = extractAllClaims(token);
        return claimsResolver.apply(claims);
    }

    // Bütün claims-ləri çıxar (token yoxlanılır)
    private Claims extractAllClaims(String token) {
        return Jwts.parser()
            .verifyWith(getSigningKey())        // İmzanı yoxla
            .build()
            .parseSignedClaims(token)           // Parse et
            .getPayload();                      // Claims qaytarır
    }

    // Token etibarlıdırmı?
    public boolean isTokenValid(String token, UserDetails userDetails) {
        final String username = extractUsername(token);
        // İstifadəçi uyğundur və token expire olmayıb?
        return (username.equals(userDetails.getUsername())) && !isTokenExpired(token);
    }

    // Token expire olubmu?
    private boolean isTokenExpired(String token) {
        return extractExpiration(token).before(new Date());
    }

    // Token-dən rolları çıxar
    @SuppressWarnings("unchecked")
    public List<String> extractRoles(String token) {
        return extractClaim(token, claims -> claims.get("roles", List.class));
    }
}
```

---

## JwtAuthenticationFilter

`OncePerRequestFilter` — hər sorğuda yalnız BİR DƏFƏ işləyən filter bazası sinfidir.

```java
@Component
public class JwtAuthenticationFilter extends OncePerRequestFilter {

    private final JwtService jwtService;
    private final UserDetailsService userDetailsService;

    public JwtAuthenticationFilter(JwtService jwtService,
                                    UserDetailsService userDetailsService) {
        this.jwtService = jwtService;
        this.userDetailsService = userDetailsService;
    }

    @Override
    protected void doFilterInternal(HttpServletRequest request,
                                    HttpServletResponse response,
                                    FilterChain filterChain)
            throws ServletException, IOException {

        // 1. Authorization header-ini al
        final String authHeader = request.getHeader("Authorization");

        // 2. Header yoxdursa və ya "Bearer " ilə başlamırsa - keç
        if (authHeader == null || !authHeader.startsWith("Bearer ")) {
            filterChain.doFilter(request, response);
            return;
        }

        // 3. Token-i header-dən ayır ("Bearer " - 7 simvol)
        final String jwt = authHeader.substring(7);

        String username;
        try {
            // 4. Username-i token-dən çıxar (eyni zamanda token imzasını yoxlayır)
            username = jwtService.extractUsername(jwt);
        } catch (JwtException e) {
            // Token yanlışdır - keç (AnonymousAuthenticationFilter anonim qoyacaq)
            filterChain.doFilter(request, response);
            return;
        }

        // 5. Username var və hələ autentifikasiya edilməyibsə
        if (username != null &&
                SecurityContextHolder.getContext().getAuthentication() == null) {

            // 6. İstifadəçini veritabanından yüklə
            UserDetails userDetails = userDetailsService.loadUserByUsername(username);

            // 7. Token etibarlıdırsa - SecurityContext-ə qoy
            if (jwtService.isTokenValid(jwt, userDetails)) {
                UsernamePasswordAuthenticationToken authToken =
                    new UsernamePasswordAuthenticationToken(
                        userDetails,
                        null,                           // Credentials - null (artıq lazım deyil)
                        userDetails.getAuthorities()    // İstifadəçinin icazələri
                    );

                // Əlavə detallar (IP, session, vs)
                authToken.setDetails(
                    new WebAuthenticationDetailsSource().buildDetails(request)
                );

                // SecurityContext-ə yaz
                SecurityContextHolder.getContext().setAuthentication(authToken);
            }
        }

        // 8. Növbəti filterə ötür
        filterChain.doFilter(request, response);
    }

    // Bu URL-ləri filter etmə (auth endpoint-ləri)
    @Override
    protected boolean shouldNotFilter(HttpServletRequest request)
            throws ServletException {
        String path = request.getServletPath();
        // Login və register endpoint-lərini atla
        return path.startsWith("/api/auth/");
    }
}
```

---

## Token Validation

### Geniş Yoxlama

```java
@Service
public class TokenValidationService {

    private final JwtService jwtService;
    private final TokenBlacklistRepository blacklistRepository;

    public TokenValidationService(JwtService jwtService,
                                   TokenBlacklistRepository blacklistRepository) {
        this.jwtService = jwtService;
        this.blacklistRepository = blacklistRepository;
    }

    public ValidationResult validateToken(String token) {
        try {
            // 1. İmza yoxlaması (JJWT özü edir)
            String username = jwtService.extractUsername(token);

            // 2. Expire yoxlaması
            if (jwtService.isTokenExpired(token)) {
                return ValidationResult.expired("Token müddəti bitib");
            }

            // 3. Blacklist yoxlaması (logout edilmiş token-lər)
            if (blacklistRepository.existsByToken(token)) {
                return ValidationResult.invalid("Token ləğv edilib");
            }

            return ValidationResult.valid(username);

        } catch (ExpiredJwtException e) {
            return ValidationResult.expired("Token müddəti bitib");
        } catch (SignatureException e) {
            return ValidationResult.invalid("Token imzası yanlışdır");
        } catch (MalformedJwtException e) {
            return ValidationResult.invalid("Token formatı yanlışdır");
        } catch (UnsupportedJwtException e) {
            return ValidationResult.invalid("Token dəstəklənmir");
        } catch (IllegalArgumentException e) {
            return ValidationResult.invalid("Token boşdur");
        }
    }
}

// Token blacklist (logout üçün):
@Entity
@Table(name = "token_blacklist")
public class BlacklistedToken {
    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    @Column(unique = true, length = 1000)
    private String token;

    private LocalDateTime blacklistedAt;
    private LocalDateTime expiresAt; // Lazımsız qeydləri silmək üçün
}
```

---

## JWT SecurityContext-ə Yerləşdirmək

```java
// Tam konfiqurasiya:
@Configuration
@EnableWebSecurity
public class SecurityConfig {

    private final JwtAuthenticationFilter jwtAuthFilter;
    private final UserDetailsService userDetailsService;

    public SecurityConfig(JwtAuthenticationFilter jwtAuthFilter,
                           UserDetailsService userDetailsService) {
        this.jwtAuthFilter = jwtAuthFilter;
        this.userDetailsService = userDetailsService;
    }

    @Bean
    public SecurityFilterChain securityFilterChain(HttpSecurity http) throws Exception {
        http
            // CSRF REST API üçün lazım deyil (stateless)
            .csrf(csrf -> csrf.disable())

            // URL icazə qaydaları
            .authorizeHttpRequests(auth -> auth
                .requestMatchers("/api/auth/**").permitAll()  // Login/register açıqdır
                .requestMatchers("/api/public/**").permitAll() // Public endpoint-lər
                .requestMatchers("/api/admin/**").hasRole("ADMIN")
                .anyRequest().authenticated()
            )

            // Session yoxdur - JWT istifadə edirik
            .sessionManagement(session -> session
                .sessionCreationPolicy(SessionCreationPolicy.STATELESS)
            )

            // JWT filter-i UsernamePasswordAuthenticationFilter-dən ƏVVƏL qoy
            .addFilterBefore(jwtAuthFilter, UsernamePasswordAuthenticationFilter.class)

            // Exception handling
            .exceptionHandling(ex -> ex
                // 401 - Autentifikasiya yoxdur
                .authenticationEntryPoint((request, response, authException) -> {
                    response.setStatus(HttpServletResponse.SC_UNAUTHORIZED);
                    response.setContentType("application/json");
                    response.getWriter().write("{\"error\": \"Giriş tələb olunur\"}");
                })
                // 403 - İcazə yoxdur
                .accessDeniedHandler((request, response, accessDeniedException) -> {
                    response.setStatus(HttpServletResponse.SC_FORBIDDEN);
                    response.setContentType("application/json");
                    response.getWriter().write("{\"error\": \"Bu əməliyyat üçün icazəniz yoxdur\"}");
                })
            );

        return http.build();
    }

    @Bean
    public AuthenticationProvider authenticationProvider() {
        DaoAuthenticationProvider provider = new DaoAuthenticationProvider();
        provider.setUserDetailsService(userDetailsService);
        provider.setPasswordEncoder(passwordEncoder());
        return provider;
    }

    @Bean
    public AuthenticationManager authenticationManager(
            AuthenticationConfiguration config) throws Exception {
        return config.getAuthenticationManager();
    }

    @Bean
    public PasswordEncoder passwordEncoder() {
        return new BCryptPasswordEncoder(12);
    }
}
```

### Auth Controller

```java
@RestController
@RequestMapping("/api/auth")
public class AuthController {

    private final AuthenticationManager authenticationManager;
    private final UserDetailsService userDetailsService;
    private final JwtService jwtService;
    private final RefreshTokenService refreshTokenService;

    // Constructor injection...

    @PostMapping("/login")
    public ResponseEntity<AuthResponse> login(@RequestBody @Valid LoginRequest request) {
        // 1. Autentifikasiya
        Authentication authentication = authenticationManager.authenticate(
            new UsernamePasswordAuthenticationToken(
                request.getEmail(),
                request.getPassword()
            )
        );

        // 2. İstifadəçini al
        UserDetails userDetails = (UserDetails) authentication.getPrincipal();

        // 3. Access token yarat
        String accessToken = jwtService.generateToken(userDetails);

        // 4. Refresh token yarat (DB-də saxla)
        String refreshToken = refreshTokenService.createRefreshToken(userDetails.getUsername());

        return ResponseEntity.ok(AuthResponse.builder()
            .accessToken(accessToken)
            .refreshToken(refreshToken)
            .tokenType("Bearer")
            .expiresIn(900) // 15 dəqiqə
            .build());
    }

    @PostMapping("/logout")
    public ResponseEntity<Void> logout(
            @RequestHeader("Authorization") String authHeader) {
        // Access token-i blacklist-ə əlavə et
        String token = authHeader.substring(7);
        tokenBlacklistService.blacklist(token);

        // Refresh token-i sil
        String username = jwtService.extractUsername(token);
        refreshTokenService.deleteByUsername(username);

        // SecurityContext-i təmizlə
        SecurityContextHolder.clearContext();

        return ResponseEntity.ok().build();
    }
}
```

---

## Stateless Session

```java
// Stateless nə deməkdir?
// Server heç bir session məlumatı saxlamır.
// Hər sorğu müstəqildir - JWT token-da bütün lazımi məlumatlar var.

// YANLIŞ: JWT istifadə edib session açıq saxlamaq
@Bean
public SecurityFilterChain filterChain(HttpSecurity http) throws Exception {
    http
        .addFilterBefore(jwtFilter, UsernamePasswordAuthenticationFilter.class)
        // Session siyasəti qoyulmayıb! Server hər istifadəçi üçün session açır
        .authorizeHttpRequests(auth -> auth.anyRequest().authenticated());
    return http.build();
}

// DOGRU: STATELESS siyasəti
@Bean
public SecurityFilterChain filterChain(HttpSecurity http) throws Exception {
    http
        .sessionManagement(session -> session
            .sessionCreationPolicy(SessionCreationPolicy.STATELESS) // Session yaratma!
        )
        .addFilterBefore(jwtFilter, UsernamePasswordAuthenticationFilter.class)
        .authorizeHttpRequests(auth -> auth.anyRequest().authenticated());
    return http.build();
}

// Stateless-in üstünlükləri:
// 1. Scalability - Hər server sorğunu emal edə bilər (session replication lazım deyil)
// 2. Performance - Veritabanında session axtarmaq lazım deyil
// 3. Microservices - Fərqli servisler arası autentifikasiya asandır

// Stateless-in çatışmazlıqları:
// 1. Token ləğvi çətin (expire olmadan "logout" etmək üçün blacklist lazımdır)
// 2. Token oğurlandıqda expire olana kimi istifadə edilə bilər
// 3. Böyük payload = böyük token = hər sorğuda böyük header
```

---

## Refresh Token Strategiyası

```java
// Access Token: Qısamüddətli (15 dəqiqə - 1 saat)
// Refresh Token: Uzunmüddətli (7-30 gün), veritabanında saxlanılır

@Entity
@Table(name = "refresh_tokens")
public class RefreshToken {
    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    @Column(unique = true, nullable = false)
    private String token; // UUID

    @ManyToOne
    @JoinColumn(name = "user_id", nullable = false)
    private User user;

    @Column(nullable = false)
    private Instant expiresAt;

    private boolean revoked = false;
}

@Service
public class RefreshTokenService {

    private final RefreshTokenRepository refreshTokenRepository;
    private final UserRepository userRepository;
    private final JwtService jwtService;

    @Value("${application.security.jwt.refresh-token.expiration}")
    private Long refreshTokenExpiration;

    // Yeni refresh token yarat
    public String createRefreshToken(String username) {
        User user = userRepository.findByEmail(username)
            .orElseThrow(() -> new UsernameNotFoundException("İstifadəçi tapılmadı"));

        // Köhnə refresh token-ları sil
        refreshTokenRepository.deleteByUser(user);

        // Yeni token yarat
        RefreshToken refreshToken = new RefreshToken();
        refreshToken.setUser(user);
        refreshToken.setToken(UUID.randomUUID().toString()); // UUID - təxmin edilməz
        refreshToken.setExpiresAt(Instant.now().plusMillis(refreshTokenExpiration));
        refreshTokenRepository.save(refreshToken);

        return refreshToken.getToken();
    }

    // Refresh token ilə yeni access token al
    @Transactional
    public TokenRefreshResponse refreshToken(String refreshTokenStr) {
        // 1. Refresh token-i tap
        RefreshToken refreshToken = refreshTokenRepository.findByToken(refreshTokenStr)
            .orElseThrow(() -> new TokenRefreshException("Refresh token tapılmadı"));

        // 2. Revoke edilmişmi?
        if (refreshToken.isRevoked()) {
            throw new TokenRefreshException("Refresh token ləğv edilib");
        }

        // 3. Expire olubmu?
        if (refreshToken.getExpiresAt().isBefore(Instant.now())) {
            refreshTokenRepository.delete(refreshToken);
            throw new TokenRefreshException("Refresh token müddəti bitib, yenidən giriş edin");
        }

        // 4. Yeni access token yarat
        UserDetails userDetails = new CustomUserDetails(refreshToken.getUser());
        String newAccessToken = jwtService.generateToken(userDetails);

        // 5. Refresh token-i yenilə (Rotation strategy - təhlükəsizlik üçün)
        refreshToken.setToken(UUID.randomUUID().toString());
        refreshToken.setExpiresAt(Instant.now().plusMillis(refreshTokenExpiration));
        refreshTokenRepository.save(refreshToken);

        return new TokenRefreshResponse(newAccessToken, refreshToken.getToken());
    }
}

// Refresh endpoint:
@PostMapping("/refresh-token")
public ResponseEntity<TokenRefreshResponse> refreshToken(
        @RequestBody TokenRefreshRequest request) {
    return ResponseEntity.ok(
        refreshTokenService.refreshToken(request.getRefreshToken())
    );
}
```

### Token Rotation

```java
// Refresh Token Rotation - Hər istifadədə refresh token yenilənir:
// 1. Client access token expire olduqda refresh token göndərir
// 2. Server yeni access token VƏ yeni refresh token qaytarır
// 3. Köhnə refresh token artıq etibarsızdır
// 4. Token oğurlandıqda: oğru token istifadə etsə, sahibinin növbəti sorğusu uğursuz olur
//    Sistem bunu aşkarlayır və bütün sessiyaları bağlayır

// YANLIŞ: Refresh token heç vaxt dəyişmirsə
public TokenRefreshResponse refreshToken(String token) {
    RefreshToken rt = findByToken(token);
    String newAccess = jwtService.generateToken(rt.getUser());
    return new TokenRefreshResponse(newAccess, token); // Köhnə refresh token qaytarılır!
}

// DOGRU: Rotation ilə
public TokenRefreshResponse refreshToken(String token) {
    RefreshToken rt = findByToken(token);
    String newAccess = jwtService.generateToken(rt.getUser());
    // Yeni refresh token yarat
    rt.setToken(UUID.randomUUID().toString());
    rt.setExpiresAt(Instant.now().plusMillis(refreshExpiration));
    refreshTokenRepository.save(rt);
    return new TokenRefreshResponse(newAccess, rt.getToken()); // Yeni refresh token
}
```

---

## İntervyu Sualları

**S: JWT payload-u şifrələnirmi?**

C: Xeyr! JWT payload yalnız Base64URL ilə kodlanır, şifrələnmir. Bu o deməkdir ki, token-i olan istənilən şəxs payload-dakı məlumatları oxuya bilər. Yalnız imzalama vasitəsilə məlumatın dəyişdirilmədiyini yoxlamaq mümkündür. Buna görə şifrə, kredit kartı nömrəsi kimi həssas məlumatları JWT payload-a yazmamalısınız.

---

**S: JWT-nin dezavantajları nələrdir?**

C: 1) **Token ləğvi çətin** — server tərəfindən token expire olmadan geçərsiz etmək üçün blacklist lazımdır. 2) **Böyük ölçü** — payload çox claim saxladıqda token böyüyür, hər sorğuda bu overhead var. 3) **Şifrə dəyişdikdə köhnə token-lər etibarlı qalır** — istifadəçi şifrəni dəyişdikdə bütün köhnə token-lər expire olana kimi keçərli olur. 4) **Secret key idarəetməsi** — gizli açar oğurlanıbsa bütün token-lər saxtalaşdırıla bilər.

---

**S: `OncePerRequestFilter` nə üçün lazımdır?**

C: Bəzi Servlet ortamlarında (xüsusilə forward/include zamanı) eyni filter bir sorğu üçün birdən çox çağırıla bilər. `OncePerRequestFilter` bu problemi həll edir — hətta forward/include olsa belə, filterimiz sorğu başına yalnız bir dəfə işləyir. JWT filterini `OncePerRequestFilter`-dən extend etmək yanlış təkrar token yoxlanmasının qarşısını alır.

---

**S: Access token qısa müddətli, refresh token uzun müddətli olmağının səbəbi nədir?**

C: Balans məsələsidir. Access token tez-tez istifadə edilir, oğurlanma riski yüksəkdir — qısa müddət (15 dəq) zarar minimaldır. Refresh token isə veritabanında saxlanılır, serverə az göndərilir, revoke edilə bilər — uzun müddət (7 gün) user experience-i yaxşılaşdırır. Bu kombinasiya həm təhlükəsizlik, həm də rahatlıq təmin edir.

---

**S: JWT ilə session-based auth arasındakı fərq nədir?**

C: Session-based auth serverdə session saxlayır, client-ə yalnız session ID verir. Bu centralized control verir amma scalability problemi yaradır (bütün serverlər session-a çıxış lazımdır). JWT isə bütün məlumatları özündə saxlayır, server stateless olur. JWT microservices, horizontal scaling üçün daha uyğundur. Session-based instant revocation dəstəkləyir, JWT isə buna blacklist tələb edir.
