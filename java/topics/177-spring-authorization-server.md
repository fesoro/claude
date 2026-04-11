# Spring Authorization Server — Geniş İzah

## Mündəricat
1. [OAuth2 Authorization Server nədir?](#oauth2-authorization-server-nədir)
2. [Spring Authorization Server quraşdırma](#spring-authorization-server-quraşdırma)
3. [Client registration](#client-registration)
4. [Authorization Code Flow](#authorization-code-flow)
5. [Token customization](#token-customization)
6. [Resource Server inteqrasiyası](#resource-server-inteqrasiyası)
7. [İntervyu Sualları](#intervyu-sualları)

---

## OAuth2 Authorization Server nədir?

```
OAuth2 / OIDC əsas anlayışlar:

  Authorization Server (AS):
    → Token-ları verir (Access Token, Refresh Token, ID Token)
    → Kimlik doğrulama (authentication) həyata keçirir
    → Nümunələr: Keycloak, Auth0, Okta, Google, Spring AS

  Resource Server (RS):
    → Qorunan API (order-service, payment-service...)
    → AS-dən token-ı yoxlayır
    → Spring Security oauth2-resource-server

  Client:
    → Token-ı istəyən tətbiq (SPA, mobile, backend)

OAuth2 Grant Types:
  Authorization Code (PKCE):
    → İstifadəçi login → AS → code → token exchange
    → SPA, mobile üçün ən güvənli

  Client Credentials:
    → Server-to-server (microservice-ler arası)
    → İstifadəçi yoxdur

  Refresh Token:
    → Access token bitdikdə yenisi almaq

OIDC (OpenID Connect):
    → OAuth2 üzərindəki kimlik qatı
    → ID Token (JWT) — istifadəçi məlumatı
    → /userinfo endpoint

Spring Authorization Server:
  → Spring Security ekosistemi üçün özəl OAuth2/OIDC AS
  → Keycloak alternativ, amma Spring-native
  → 1.0 GA — 2023
```

---

## Spring Authorization Server quraşdırma

```xml
<!-- pom.xml — Authorization Server -->
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-security</artifactId>
</dependency>
<dependency>
    <groupId>org.springframework.security</groupId>
    <artifactId>spring-security-oauth2-authorization-server</artifactId>
</dependency>
```

```java
// ─── Authorization Server konfigurasyonu ─────────────────
@Configuration
@EnableWebSecurity
public class AuthorizationServerConfig {

    // ─── Authorization Server endpoint-lər ───────────────
    @Bean
    @Order(1)
    public SecurityFilterChain authorizationServerSecurityFilterChain(
            HttpSecurity http) throws Exception {

        OAuth2AuthorizationServerConfiguration
            .applyDefaultSecurity(http);

        http.getConfigurer(OAuth2AuthorizationServerConfigurer.class)
            .oidc(Customizer.withDefaults());  // OIDC aktiv

        http
            .exceptionHandling(ex -> ex
                .defaultAuthenticationEntryPointFor(
                    new LoginUrlAuthenticationEntryPoint("/login"),
                    new MediaTypeRequestMatcher(MediaType.TEXT_HTML)
                )
            )
            .oauth2ResourceServer(rs -> rs.jwt(Customizer.withDefaults()));

        return http.build();
    }

    // ─── Default Security (Login page) ───────────────────
    @Bean
    @Order(2)
    public SecurityFilterChain defaultSecurityFilterChain(
            HttpSecurity http) throws Exception {
        http
            .authorizeHttpRequests(auth -> auth
                .requestMatchers("/login", "/error").permitAll()
                .anyRequest().authenticated()
            )
            .formLogin(form -> form
                .loginPage("/login")
                .permitAll()
            );
        return http.build();
    }

    // ─── JWK Source — JWT imzalamaq üçün açar ────────────
    @Bean
    public JWKSource<SecurityContext> jwkSource() {
        KeyPair keyPair = generateRsaKey();
        RSAPublicKey publicKey = (RSAPublicKey) keyPair.getPublic();
        RSAPrivateKey privateKey = (RSAPrivateKey) keyPair.getPrivate();

        RSAKey rsaKey = new RSAKey.Builder(publicKey)
            .privateKey(privateKey)
            .keyID(UUID.randomUUID().toString())
            .build();

        JWKSet jwkSet = new JWKSet(rsaKey);
        return new ImmutableJWKSet<>(jwkSet);
    }

    private static KeyPair generateRsaKey() {
        try {
            KeyPairGenerator generator = KeyPairGenerator.getInstance("RSA");
            generator.initialize(2048);
            return generator.generateKeyPair();
        } catch (Exception ex) {
            throw new IllegalStateException(ex);
        }
    }

    // ─── JWT Decoder (token yoxlaması üçün) ──────────────
    @Bean
    public JwtDecoder jwtDecoder(JWKSource<SecurityContext> jwkSource) {
        return OAuth2AuthorizationServerConfiguration.jwtDecoder(jwkSource);
    }

    // ─── Authorization Server metadata ───────────────────
    @Bean
    public AuthorizationServerSettings authorizationServerSettings() {
        return AuthorizationServerSettings.builder()
            .issuer("http://localhost:9000")
            .authorizationEndpoint("/oauth2/authorize")
            .tokenEndpoint("/oauth2/token")
            .jwkSetEndpoint("/oauth2/jwks")
            .tokenRevocationEndpoint("/oauth2/revoke")
            .tokenIntrospectionEndpoint("/oauth2/introspect")
            .oidcUserInfoEndpoint("/userinfo")
            .build();
    }

    // ─── UserDetailsService ───────────────────────────────
    @Bean
    public UserDetailsService userDetailsService() {
        UserDetails user = User.builder()
            .username("user@example.com")
            .password("{bcrypt}" + new BCryptPasswordEncoder().encode("password"))
            .roles("USER")
            .build();

        UserDetails admin = User.builder()
            .username("admin@example.com")
            .password("{bcrypt}" + new BCryptPasswordEncoder().encode("admin"))
            .roles("USER", "ADMIN")
            .build();

        return new InMemoryUserDetailsManager(user, admin);
    }
}
```

---

## Client registration

```java
// ─── RegisteredClient — OAuth2 client tərifi ─────────────
@Configuration
public class ClientRegistrationConfig {

    @Bean
    public RegisteredClientRepository registeredClientRepository() {
        // ─── SPA Client (Authorization Code + PKCE) ───────
        RegisteredClient spaClient = RegisteredClient
            .withId(UUID.randomUUID().toString())
            .clientId("spa-client")
            .clientSecret("{noop}secret")  // Public client üçün lazım deyil
            .clientAuthenticationMethod(ClientAuthenticationMethod.NONE)  // PKCE
            .authorizationGrantType(AuthorizationGrantType.AUTHORIZATION_CODE)
            .authorizationGrantType(AuthorizationGrantType.REFRESH_TOKEN)
            .redirectUri("http://localhost:3000/callback")
            .postLogoutRedirectUri("http://localhost:3000")
            .scope(OidcScopes.OPENID)
            .scope(OidcScopes.PROFILE)
            .scope(OidcScopes.EMAIL)
            .scope("orders:read")
            .scope("orders:write")
            .clientSettings(ClientSettings.builder()
                .requireAuthorizationConsent(true)  // Consent page
                .requireProofKey(true)              // PKCE məcburi
                .build())
            .tokenSettings(TokenSettings.builder()
                .accessTokenTimeToLive(Duration.ofMinutes(30))
                .refreshTokenTimeToLive(Duration.ofDays(7))
                .reuseRefreshTokens(false)          // Hər refresh-də yeni token
                .build())
            .build();

        // ─── Backend Service (Client Credentials) ─────────
        RegisteredClient serviceClient = RegisteredClient
            .withId(UUID.randomUUID().toString())
            .clientId("order-service")
            .clientSecret("{bcrypt}" + new BCryptPasswordEncoder().encode("service-secret"))
            .clientAuthenticationMethod(ClientAuthenticationMethod.CLIENT_SECRET_BASIC)
            .authorizationGrantType(AuthorizationGrantType.CLIENT_CREDENTIALS)
            .scope("payments:read")
            .scope("inventory:write")
            .tokenSettings(TokenSettings.builder()
                .accessTokenTimeToLive(Duration.ofMinutes(5))
                .build())
            .build();

        // ─── Mobile Client ─────────────────────────────────
        RegisteredClient mobileClient = RegisteredClient
            .withId(UUID.randomUUID().toString())
            .clientId("mobile-app")
            .clientAuthenticationMethod(ClientAuthenticationMethod.NONE)
            .authorizationGrantType(AuthorizationGrantType.AUTHORIZATION_CODE)
            .authorizationGrantType(AuthorizationGrantType.REFRESH_TOKEN)
            .redirectUri("myapp://callback")          // Custom scheme
            .scope(OidcScopes.OPENID)
            .scope("orders:read")
            .clientSettings(ClientSettings.builder()
                .requireProofKey(true)
                .build())
            .build();

        return new InMemoryRegisteredClientRepository(spaClient, serviceClient, mobileClient);
    }
}

// Production — DB-dən client yüklə:
@Bean
public RegisteredClientRepository registeredClientRepository(
        JdbcTemplate jdbcTemplate) {
    return new JdbcRegisteredClientRepository(jdbcTemplate);
}
```

---

## Authorization Code Flow

```java
// ─── Authorization Code + PKCE Flow ──────────────────────
/*
1. Client → PKCE code_verifier + code_challenge yarat
   code_verifier = random 43-128 char string
   code_challenge = BASE64URL(SHA256(code_verifier))

2. Client → AS /oauth2/authorize:
   GET /oauth2/authorize?
     response_type=code
     &client_id=spa-client
     &redirect_uri=http://localhost:3000/callback
     &scope=openid profile email orders:read
     &code_challenge=<challenge>
     &code_challenge_method=S256
     &state=<random-state>

3. AS → Login page göstər
4. İstifadəçi login olur
5. AS → /callback?code=<auth_code>&state=<state>

6. Client → AS /oauth2/token:
   POST /oauth2/token
   Content-Type: application/x-www-form-urlencoded

   grant_type=authorization_code
   &code=<auth_code>
   &redirect_uri=http://localhost:3000/callback
   &client_id=spa-client
   &code_verifier=<original_verifier>

7. AS → {access_token, refresh_token, id_token}
*/

// ─── Token endpoint test ──────────────────────────────────
// curl -X POST http://localhost:9000/oauth2/token \
//   -H "Content-Type: application/x-www-form-urlencoded" \
//   -d "grant_type=client_credentials" \
//   -d "client_id=order-service" \
//   -d "client_secret=service-secret" \
//   -d "scope=payments:read"

// Response:
// {
//   "access_token": "eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9...",
//   "token_type": "Bearer",
//   "expires_in": 300,
//   "scope": "payments:read"
// }
```

---

## Token customization

```java
// ─── JWT token-ə əlavə claim-lər ────────────────────────
@Bean
public OAuth2TokenCustomizer<JwtEncodingContext> jwtTokenCustomizer(
        UserService userService) {
    return context -> {
        // Access token-ə claim əlavə et
        if (OAuth2TokenType.ACCESS_TOKEN.equals(context.getTokenType())) {
            Authentication principal = context.getPrincipal();

            if (principal instanceof UsernamePasswordAuthenticationToken auth) {
                String username = auth.getName();
                User user = userService.findByEmail(username).orElseThrow();

                // Custom claim-lər
                context.getClaims()
                    .claim("user_id", user.getId())
                    .claim("roles", user.getRoles())
                    .claim("tenant_id", user.getTenantId())
                    .claim("plan", user.getSubscriptionPlan());
            }
        }

        // ID Token-ə əlavə məlumat (OIDC)
        if (OidcParameterNames.ID_TOKEN.equals(context.getTokenType().getValue())) {
            context.getClaims()
                .claim("preferred_username", context.getPrincipal().getName());
        }
    };
}

// ─── Opaque token (reference token) ──────────────────────
// JWT əvəzinə opaque token — DB-dən yoxlanılır

@Bean
public OAuth2AuthorizationService authorizationService(
        JdbcTemplate jdbcTemplate,
        RegisteredClientRepository repo) {
    return new JdbcOAuth2AuthorizationService(jdbcTemplate, repo);
}

// Introspection endpoint aktiv edilir
// Resource server introspection ilə yoxlayır:
// POST /oauth2/introspect
// → {active: true, scope: "...", username: "...", exp: ...}
```

---

## Resource Server inteqrasiyası

```java
// ─── Resource Server — Order Service ─────────────────────
// pom.xml: spring-boot-starter-oauth2-resource-server

@Configuration
@EnableWebSecurity
@EnableMethodSecurity
public class ResourceServerConfig {

    @Bean
    public SecurityFilterChain securityFilterChain(HttpSecurity http) throws Exception {
        http
            .authorizeHttpRequests(auth -> auth
                .requestMatchers("/actuator/health").permitAll()
                .requestMatchers(HttpMethod.GET, "/api/orders/**")
                    .hasAuthority("SCOPE_orders:read")
                .requestMatchers(HttpMethod.POST, "/api/orders/**")
                    .hasAuthority("SCOPE_orders:write")
                .anyRequest().authenticated()
            )
            .oauth2ResourceServer(rs -> rs
                .jwt(jwt -> jwt
                    .jwtAuthenticationConverter(jwtAuthConverter())
                )
            );
        return http.build();
    }

    @Bean
    public JwtAuthenticationConverter jwtAuthConverter() {
        JwtGrantedAuthoritiesConverter grantedAuthoritiesConverter =
            new JwtGrantedAuthoritiesConverter();
        grantedAuthoritiesConverter.setAuthoritiesClaimName("roles");
        grantedAuthoritiesConverter.setAuthorityPrefix("ROLE_");

        JwtAuthenticationConverter authConverter = new JwtAuthenticationConverter();
        authConverter.setJwtGrantedAuthoritiesConverter(jwt -> {
            // Scope-lar
            Collection<GrantedAuthority> scopeAuthorities =
                new JwtGrantedAuthoritiesConverter().convert(jwt);

            // Roles claim-dən
            List<String> roles = jwt.getClaimAsStringList("roles");
            List<GrantedAuthority> roleAuthorities = roles == null ? List.of() :
                roles.stream()
                    .map(r -> new SimpleGrantedAuthority("ROLE_" + r))
                    .collect(Collectors.toList());

            Set<GrantedAuthority> all = new HashSet<>();
            if (scopeAuthorities != null) all.addAll(scopeAuthorities);
            all.addAll(roleAuthorities);
            return all;
        });

        return authConverter;
    }
}

// ─── application.yml (Resource Server) ───────────────────
/*
spring:
  security:
    oauth2:
      resourceserver:
        jwt:
          # Authorization Server-in JWK endpoint-i
          jwk-set-uri: http://localhost:9000/oauth2/jwks
          # Ya da issuer-uri (discovery edilir):
          issuer-uri: http://localhost:9000
*/

// ─── Controller-də token məlumatı ────────────────────────
@RestController
@RequestMapping("/api/orders")
public class OrderController {

    @GetMapping
    @PreAuthorize("hasAuthority('SCOPE_orders:read')")
    public List<Order> getOrders(
            @AuthenticationPrincipal Jwt jwt) {

        String userId = jwt.getClaimAsString("user_id");
        String plan   = jwt.getClaimAsString("plan");

        return orderService.findByUserId(userId);
    }

    @PostMapping
    @PreAuthorize("hasAuthority('SCOPE_orders:write') and hasRole('USER')")
    public Order createOrder(@RequestBody CreateOrderRequest request,
                              @AuthenticationPrincipal Jwt jwt) {
        String userId = jwt.getClaimAsString("user_id");
        return orderService.create(request, userId);
    }
}

// ─── Service-to-Service (Client Credentials) ─────────────
@Configuration
public class ServiceClientConfig {

    @Bean
    public OAuth2AuthorizedClientManager authorizedClientManager(
            ClientRegistrationRepository clientRegistrationRepository,
            OAuth2AuthorizedClientRepository authorizedClientRepository) {

        OAuth2AuthorizedClientProvider provider =
            OAuth2AuthorizedClientProviderBuilder.builder()
                .clientCredentials()
                .build();

        DefaultOAuth2AuthorizedClientManager manager =
            new DefaultOAuth2AuthorizedClientManager(
                clientRegistrationRepository, authorizedClientRepository);
        manager.setAuthorizedClientProvider(provider);
        return manager;
    }

    @Bean
    public WebClient paymentServiceClient(
            OAuth2AuthorizedClientManager manager) {
        ServletOAuth2AuthorizedClientExchangeFilterFunction oauth2 =
            new ServletOAuth2AuthorizedClientExchangeFilterFunction(manager);
        oauth2.setDefaultClientRegistrationId("payment-service");

        return WebClient.builder()
            .baseUrl("http://payment-service")
            .apply(oauth2.oauth2Configuration())
            .build();
    }
}
```

---

## İntervyu Sualları

### 1. Spring Authorization Server nədir?
**Cavab:** Spring Security ekosistemi üçün OAuth2 / OpenID Connect Authorization Server. Keycloak, Auth0 kimi xarici AS-lərin alternatividir — amma Spring-native, Spring Boot ilə tam inteqrasiya. Token verir (Access Token JWT, Refresh Token, ID Token OIDC). Grant type-lar: Authorization Code + PKCE (SPA, mobile), Client Credentials (service-to-service), Refresh Token. 1.0 GA 2023-ci ildə çıxdı.

### 2. Authorization Code vs Client Credentials fərqi?
**Cavab:** **Authorization Code** — istifadəçi iştirak edir. User login → AS redirect → callback code → token exchange. SPA, mobile, web app üçün. PKCE (Proof Key for Code Exchange) — code_challenge/verifier ilə man-in-the-middle-a qarşı qoruma. **Client Credentials** — server-to-server, istifadəçi yoxdur. Service öz client_id + secret ilə token alır. Microservice-lər arası API çağırışları üçün. İkisi arasında seçim: insan var mı? Authorization Code. Service-to-service? Client Credentials.

### 3. PKCE nədir?
**Cavab:** Proof Key for Code Exchange — Authorization Code flow-u üçün güvənlik əlavəsi. Problem: code interception attack — authorization code ələ keçirilsə token alına bilər. PKCE: client random `code_verifier` yaradır → `code_challenge = SHA256(code_verifier)` → AS-ə challenge ilə sorğu → token exchange-də original verifier göndərilir → AS yoxlayır. Client secret olmayan public client-lər (SPA, mobile) üçün mütləq lazımdır. Spring AS-da `requireProofKey(true)` ilə məcburi edilir.

### 4. JWT token-ə custom claim necə əlavə edilir?
**Cavab:** `OAuth2TokenCustomizer<JwtEncodingContext>` Bean yaratmaqla. `context.getTokenType()` yoxlanılır (ACCESS_TOKEN, ID_TOKEN). `context.getPrincipal()` ilə authenticated user alınır, DB-dən əlavə məlumat yüklənir. `context.getClaims().claim("user_id", userId)` ilə claim əlavə edilir. Resource Server-də `Jwt.getClaimAsString("user_id")` ilə oxunur. Bu şəkildə DB sorğusu olmadan token içindəki məlumat istifadə edilir.

### 5. Resource Server Authorization Server-i necə yoxlayır?
**Cavab:** JWT token imzası yoxlanılır: **JWK endpoint** (`/oauth2/jwks`) — AS-in public açarları. Resource Server bu endpoint-dən açarları yükləyir, JWT-nin imzasını yoxlayır. `jwk-set-uri` ya da `issuer-uri` (auto-discovery) konfiqurasyonda verilir. Opaque token üçün **Introspection endpoint** (`/oauth2/introspect`) — RS AS-ə soruşur "bu token aktivdirmi?". JWT daha performanslıdır (local yoxlama), opaque daha güvənlidir (revoke anında işləyir).

*Son yenilənmə: 2026-04-10*
