# Spring Security OAuth2 — Geniş İzah

## Mündəricat
1. [OAuth2 nədir?](#oauth2-nədir)
2. [OAuth2 Resource Server (JWT)](#oauth2-resource-server-jwt)
3. [OAuth2 Client (Social Login)](#oauth2-client-social-login)
4. [Authorization Server (Keycloak)](#authorization-server-keycloak)
5. [Custom JWT claims](#custom-jwt-claims)
6. [İntervyu Sualları](#intervyu-sualları)

---

## OAuth2 nədir?

**OAuth2** — üçüncü tərəf tətbiqlərin resource-lara məhdud çıxış imkanı verən açıq avtorizasiya protokoludur. OpenID Connect (OIDC) — OAuth2 üzərindəki autentifikasiya qatıdır.

```
Resource Owner (İstifadəçi)
       ↓
Client Application (bizim app)
       ↓
Authorization Server (Google, Keycloak, Okta)
       ↓ Access Token verir
Resource Server (bizim API)
```

**Əsas grant type-lar:**
- **Authorization Code** — web app-lar üçün (ən güvənli)
- **Client Credentials** — microservice-to-microservice (istifadəçi yoxdur)
- **Password** — köhnə, tövsiyə olunmaz
- **Implicit** — deprecated

---

## OAuth2 Resource Server (JWT)

Bizim API-nin JWT token-ları qəbul etməsi:

```xml
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-oauth2-resource-server</artifactId>
</dependency>
```

```yaml
spring:
  security:
    oauth2:
      resourceserver:
        jwt:
          # JWT-ni verify etmək üçün JWKS endpoint
          jwk-set-uri: https://keycloak.example.com/realms/myrealm/protocol/openid-connect/certs
          # Ya da issuer URI
          issuer-uri: https://keycloak.example.com/realms/myrealm
```

```java
@Configuration
@EnableWebSecurity
@EnableMethodSecurity
public class SecurityConfig {

    @Bean
    public SecurityFilterChain securityFilterChain(HttpSecurity http) throws Exception {
        http
            .csrf(csrf -> csrf.disable()) // REST API üçün
            .sessionManagement(session ->
                session.sessionCreationPolicy(SessionCreationPolicy.STATELESS))
            .authorizeHttpRequests(auth -> auth
                .requestMatchers("/api/public/**").permitAll()
                .requestMatchers("/api/admin/**").hasRole("ADMIN")
                .anyRequest().authenticated()
            )
            .oauth2ResourceServer(oauth2 ->
                oauth2.jwt(jwt -> jwt.jwtAuthenticationConverter(jwtConverter()))
            );

        return http.build();
    }

    // JWT-dən roller çıxarmaq
    @Bean
    public JwtAuthenticationConverter jwtAuthenticationConverter() {
        JwtGrantedAuthoritiesConverter authoritiesConverter =
            new JwtGrantedAuthoritiesConverter();

        // Keycloak realm roles-u üçün
        authoritiesConverter.setAuthoritiesClaimName("realm_access.roles");
        authoritiesConverter.setAuthorityPrefix("ROLE_");

        JwtAuthenticationConverter converter = new JwtAuthenticationConverter();
        converter.setJwtGrantedAuthoritiesConverter(authoritiesConverter);
        return converter;
    }
}
```

**Custom JWT converter (Keycloak üçün):**
```java
@Component
public class KeycloakJwtConverter implements Converter<Jwt, AbstractAuthenticationToken> {

    private final JwtGrantedAuthoritiesConverter defaultConverter =
        new JwtGrantedAuthoritiesConverter();

    @Override
    public AbstractAuthenticationToken convert(Jwt jwt) {
        Collection<GrantedAuthority> defaultAuthorities = defaultConverter.convert(jwt);
        Collection<GrantedAuthority> keycloakAuthorities = extractKeycloakRoles(jwt);

        List<GrantedAuthority> allAuthorities = new ArrayList<>();
        allAuthorities.addAll(defaultAuthorities);
        allAuthorities.addAll(keycloakAuthorities);

        return new JwtAuthenticationToken(jwt, allAuthorities, getPrincipalName(jwt));
    }

    private Collection<GrantedAuthority> extractKeycloakRoles(Jwt jwt) {
        // realm_access.roles — realm-level roles
        Map<String, Object> realmAccess = jwt.getClaim("realm_access");
        if (realmAccess == null) return Collections.emptyList();

        List<String> roles = (List<String>) realmAccess.get("roles");
        return roles.stream()
            .map(role -> new SimpleGrantedAuthority("ROLE_" + role.toUpperCase()))
            .collect(Collectors.toList());
    }

    private String getPrincipalName(Jwt jwt) {
        String preferredUsername = jwt.getClaim("preferred_username");
        return preferredUsername != null ? preferredUsername : jwt.getSubject();
    }
}
```

---

## OAuth2 Client (Social Login)

Google/GitHub ilə giriş:

```xml
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-oauth2-client</artifactId>
</dependency>
```

```yaml
spring:
  security:
    oauth2:
      client:
        registration:
          google:
            client-id: ${GOOGLE_CLIENT_ID}
            client-secret: ${GOOGLE_CLIENT_SECRET}
            scope: openid, profile, email
          github:
            client-id: ${GITHUB_CLIENT_ID}
            client-secret: ${GITHUB_CLIENT_SECRET}
            scope: user:email, read:user
```

```java
@Configuration
@EnableWebSecurity
public class OAuth2ClientConfig {

    @Bean
    public SecurityFilterChain securityFilterChain(HttpSecurity http) throws Exception {
        http
            .authorizeHttpRequests(auth -> auth
                .requestMatchers("/", "/login").permitAll()
                .anyRequest().authenticated()
            )
            .oauth2Login(oauth2 -> oauth2
                .loginPage("/login")
                .defaultSuccessUrl("/dashboard")
                .failureUrl("/login?error")
                .userInfoEndpoint(userInfo ->
                    userInfo.userService(customOAuth2UserService())
                )
            );

        return http.build();
    }

    @Bean
    public OAuth2UserService<OAuth2UserRequest, OAuth2User> customOAuth2UserService() {
        return new CustomOAuth2UserService();
    }
}

// Custom user service — DB-yə kaydet
@Service
public class CustomOAuth2UserService implements OAuth2UserService<OAuth2UserRequest, OAuth2User> {

    private final UserRepository userRepository;
    private final DefaultOAuth2UserService delegate = new DefaultOAuth2UserService();

    @Override
    public OAuth2User loadUser(OAuth2UserRequest request) throws OAuth2AuthenticationException {
        OAuth2User oAuth2User = delegate.loadUser(request);

        String provider = request.getClientRegistration().getRegistrationId(); // "google"
        String providerId = oAuth2User.getAttribute("sub"); // Google user ID
        String email = oAuth2User.getAttribute("email");
        String name = oAuth2User.getAttribute("name");

        // DB-də yoxla/yarat
        User user = userRepository.findByProviderAndProviderId(provider, providerId)
            .orElseGet(() -> {
                User newUser = new User();
                newUser.setEmail(email);
                newUser.setName(name);
                newUser.setProvider(provider);
                newUser.setProviderId(providerId);
                return userRepository.save(newUser);
            });

        return new DefaultOAuth2User(
            Collections.singleton(new SimpleGrantedAuthority("ROLE_USER")),
            oAuth2User.getAttributes(),
            "email"
        );
    }
}
```

---

## Authorization Server (Keycloak)

Keycloak ilə integration — `application.yml`:

```yaml
spring:
  security:
    oauth2:
      resourceserver:
        jwt:
          issuer-uri: http://localhost:8080/realms/myrealm

# Keycloak admin client üçün (əgər lazımdırsa)
keycloak:
  server-url: http://localhost:8080
  realm: myrealm
  client-id: my-backend-client
  client-secret: ${KEYCLOAK_CLIENT_SECRET}
```

```java
// Token introspection endpoint ilə (opaque token)
@Configuration
public class OpaqueTokenConfig {

    @Bean
    public SecurityFilterChain securityFilterChain(HttpSecurity http) throws Exception {
        http
            .oauth2ResourceServer(oauth2 ->
                oauth2.opaqueToken(opaque -> opaque
                    .introspectionUri("http://keycloak/realms/myrealm/protocol/openid-connect/token/introspect")
                    .introspectionClientCredentials("my-client", "my-secret")
                )
            );
        return http.build();
    }
}
```

---

## Custom JWT claims

```java
// Controller-də JWT məlumatlarına çıxış
@RestController
@RequestMapping("/api/me")
public class ProfileController {

    @GetMapping
    public ResponseEntity<ProfileDto> getProfile(
            @AuthenticationPrincipal Jwt jwt) {

        String userId = jwt.getSubject();
        String email = jwt.getClaim("email");
        String name = jwt.getClaim("preferred_username");
        List<String> roles = jwt.getClaim("realm_access.roles");
        Instant issuedAt = jwt.getIssuedAt();
        Instant expiresAt = jwt.getExpiresAt();

        return ResponseEntity.ok(new ProfileDto(userId, email, name, roles));
    }

    // SecurityContextHolder ilə
    @GetMapping("/info")
    public String getInfo() {
        JwtAuthenticationToken auth = (JwtAuthenticationToken)
            SecurityContextHolder.getContext().getAuthentication();

        Jwt jwt = auth.getToken();
        return "User: " + jwt.getClaim("preferred_username");
    }
}
```

---

## İntervyu Sualları

### 1. OAuth2 ilə JWT fərqi nədir?
**Cavab:** OAuth2 — avtorizasiya çərçivəsi/protokoludur (kim nəyə çıxış edə bilər). JWT — token formatıdır (Base64 encoded JSON). OAuth2 müxtəlif token formatlarını istifadə edə bilər, JWT ən populyar olanıdır. OIDC — OAuth2 üzərindəki autentifikasiya layeridir.

### 2. Resource Server vs Authorization Server fərqi?
**Cavab:** Authorization Server — token verir (Keycloak, Okta, Google). Resource Server — token-i qəbul edib API-ni qoruyur (bizim Spring Boot app). Client — token alır və Resource Server-ə göndərir.

### 3. JWKS endpoint nədir?
**Cavab:** JSON Web Key Set — Authorization Server-in public key-lərini paylaşdığı endpoint. Resource Server bu endpoint-dən public key alır və JWT imzasını yoxlayır. Key rotation zamanı server avtomatik yeni key-i yükləyir.

### 4. JwtAuthenticationConverter nə üçündür?
**Cavab:** JWT claim-lərini Spring Security `GrantedAuthority`-lərinə çevirir. Fərqli Authorization Server-lər (Keycloak, Auth0, Okta) rol məlumatını fərqli claim-lərdə saxlayır. Converter bu fərqliliyi normalize edir.

### 5. Client Credentials grant nə zaman istifadə edilir?
**Cavab:** Service-to-service kommunikasiyada, istifadəçi olmayan (machine-to-machine) ssenarilərdə. Microservice A, Microservice B-yə müraciət edərkən öz client_id/client_secret ilə token alır. İstifadəçi session-u olmur.

*Son yenilənmə: 2026-04-10*
