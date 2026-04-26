# 89 — Spring Security Testing

> **Seviyye:** Senior ⭐⭐⭐


## Mündəricat
1. [Security Testing niyə xüsusidir?](#security-testing-niyə-xüsusidir)
2. [@WithMockUser](#withmockuser)
3. [@WithAnonymousUser](#withanonymoususer)
4. [Custom SecurityContext](#custom-securitycontext)
5. [MockMvc ilə Security](#mockmvc-ilə-security)
6. [OAuth2 / JWT testing](#oauth2--jwt-testing)
7. [@WithUserDetails](#withuserdetails)
8. [Method Security testing](#method-security-testing)
9. [Testcontainers ilə inteqrasiya](#testcontainers-ilə-inteqrasiya)
10. [Praktik Tapşırıqlar](#praktik-tapşırıqlar)

---

## Security Testing niyə xüsusidir?

Standart `@WebMvcTest` Spring Security-ni default aktiv saxlayır. Bu o deməkdir ki:
- Authentication olmadan bütün endpointlər **401** qaytarır
- `@PreAuthorize` yoxlanır
- CSRF aktiv olur

Buna görə security testlərini xüsusi hazırlamaq lazımdır: ya mock user inject et, ya security-ni söndür.

```java
// Security olmadan — 401 gəlir
mockMvc.perform(get("/api/users"))
       .andExpect(status().isUnauthorized()); // ✓ beklenen
```

---

## @WithMockUser

`spring-security-test` dependensiyası lazımdır (Spring Boot Test Starter ilə gəlir):

```xml
<dependency>
  <groupId>org.springframework.security</groupId>
  <artifactId>spring-security-test</artifactId>
  <scope>test</scope>
</dependency>
```

### Sadə istifadə

```java
@WebMvcTest(UserController.class)
class UserControllerTest {

    @Autowired MockMvc mockMvc;

    @Test
    @WithMockUser                          // default: username="user", roles=["USER"]
    void getProfile_authenticated() throws Exception {
        mockMvc.perform(get("/api/profile"))
               .andExpect(status().isOk());
    }

    @Test
    @WithMockUser(roles = "ADMIN")
    void deleteUser_asAdmin() throws Exception {
        mockMvc.perform(delete("/api/users/1"))
               .andExpect(status().isOk());
    }

    @Test
    @WithMockUser(username = "john@example.com", roles = {"USER", "PREMIUM"})
    void getProfile_withCustomUsername() throws Exception {
        mockMvc.perform(get("/api/profile"))
               .andExpect(jsonPath("$.email").value("john@example.com"));
    }
}
```

### Parametrlər

| Parametr | Default | İzah |
|----------|---------|------|
| `username` | `"user"` | SecurityContext-dəki username |
| `password` | `"password"` | Mock şifrə |
| `roles` | `{"USER"}` | `ROLE_` prefix avtomatik əlavə olunur |
| `authorities` | `{}` | `ROLE_` prefix olmadan, daha fine-grained |

**Roles vs Authorities:**
```java
@WithMockUser(roles = "ADMIN")
// SecurityContext-də: ROLE_ADMIN

@WithMockUser(authorities = "ADMIN")
// SecurityContext-də: ADMIN (prefix yoxdur)
```

---

## @WithAnonymousUser

Anonymous user kimi test etmək (authentication yoxdur):

```java
@Test
@WithAnonymousUser
void publicEndpoint_accessible() throws Exception {
    mockMvc.perform(get("/api/public"))
           .andExpect(status().isOk());
}

@Test
@WithAnonymousUser
void protectedEndpoint_rejected() throws Exception {
    mockMvc.perform(get("/api/profile"))
           .andExpect(status().isUnauthorized());
}
```

---

## Custom SecurityContext

`@WithMockUser` sadə hallar üçün yetərlidir, lakin custom `UserDetails` implementation istifadə edirsənsə, öz annotasiyanı yaratmaq lazımdır.

### Custom WithSecurityContext Factory

```java
// 1. Custom UserDetails
public record AppUserDetails(Long id, String email, Collection<GrantedAuthority> authorities)
    implements UserDetails {

    @Override public String getUsername() { return email; }
    @Override public String getPassword() { return ""; }
    @Override public Collection<? extends GrantedAuthority> getAuthorities() { return authorities; }
    @Override public boolean isEnabled() { return true; }
    @Override public boolean isAccountNonExpired() { return true; }
    @Override public boolean isAccountNonLocked() { return true; }
    @Override public boolean isCredentialsNonExpired() { return true; }
}

// 2. Custom annotasiya
@Retention(RetentionPolicy.RUNTIME)
@WithSecurityContext(factory = WithAppUserSecurityContextFactory.class)
public @interface WithAppUser {
    long id() default 1L;
    String email() default "test@example.com";
    String[] roles() default {"USER"};
}

// 3. Factory
public class WithAppUserSecurityContextFactory
    implements WithSecurityContextFactory<WithAppUser> {

    @Override
    public SecurityContext createSecurityContext(WithAppUser annotation) {
        List<GrantedAuthority> authorities = Arrays.stream(annotation.roles())
            .map(r -> new SimpleGrantedAuthority("ROLE_" + r))
            .collect(Collectors.toList());

        AppUserDetails principal = new AppUserDetails(
            annotation.id(),
            annotation.email(),
            authorities
        );

        UsernamePasswordAuthenticationToken auth =
            new UsernamePasswordAuthenticationToken(principal, null, authorities);

        SecurityContext ctx = SecurityContextHolder.createEmptyContext();
        ctx.setAuthentication(auth);
        return ctx;
    }
}

// 4. Test-də istifadə
@Test
@WithAppUser(id = 42L, email = "admin@company.com", roles = "ADMIN")
void updateProfile_withCustomUser() throws Exception {
    mockMvc.perform(put("/api/profile")
               .contentType(MediaType.APPLICATION_JSON)
               .content("""{"name": "Admin"}"""))
           .andExpect(status().isOk());

    // Principal-ı controller içindən almaq
    // @AuthenticationPrincipal AppUserDetails user — id=42 gəlir
}
```

---

## MockMvc ilə Security

### SecurityMockMvcRequestPostProcessors

`MockMvcRequestBuilders` üzərindən request-based security inject etmək:

```java
import static org.springframework.security.test.web.servlet.request.SecurityMockMvcRequestPostProcessors.*;

@Test
void withUser_requestPostProcessor() throws Exception {
    mockMvc.perform(get("/api/profile")
               .with(user("john").roles("USER")))
           .andExpect(status().isOk());
}

@Test
void withUserDetails() throws Exception {
    AppUserDetails userDetails = new AppUserDetails(1L, "john@example.com", authorities);
    mockMvc.perform(get("/api/profile")
               .with(user(userDetails)))
           .andExpect(status().isOk());
}

// HTTP Basic authentication test
@Test
void basicAuth() throws Exception {
    mockMvc.perform(get("/api/profile")
               .with(httpBasic("user", "password")))
           .andExpect(status().isOk());
}
```

### CSRF Token

Default olaraq `@WebMvcTest` CSRF aktiv saxlayır. POST/PUT/DELETE testlərini CSRF token ilə etmək lazımdır:

```java
@Test
@WithMockUser
void createUser_withCsrf() throws Exception {
    mockMvc.perform(post("/api/users")
               .with(csrf())                   // CSRF token inject edir
               .contentType(MediaType.APPLICATION_JSON)
               .content("""{"name": "John"}"""))
           .andExpect(status().isCreated());
}

@Test
@WithMockUser
void createUser_withInvalidCsrf() throws Exception {
    mockMvc.perform(post("/api/users")
               .with(csrf().useInvalidToken())  // 403 gəlməlidir
               .contentType(MediaType.APPLICATION_JSON)
               .content("""{"name": "John"}"""))
           .andExpect(status().isForbidden());
}
```

REST API-lərdə adətən CSRF söndürülür:
```java
// SecurityConfig-də
http.csrf(AbstractHttpConfigurer::disable)
```
Bu halda `.with(csrf())` lazım deyil.

---

## OAuth2 / JWT Testing

JWT ilə qorunan endpoint test etmək:

### MockMvc ilə JWT Bearer token (mock)

```java
import static org.springframework.security.test.web.servlet.request.SecurityMockMvcRequestPostProcessors.jwt;

@Test
void withJwt_mockToken() throws Exception {
    mockMvc.perform(get("/api/profile")
               .with(jwt()))                   // default mock JWT
           .andExpect(status().isOk());
}

@Test
void withJwt_customClaims() throws Exception {
    mockMvc.perform(get("/api/orders")
               .with(jwt()
                   .jwt(j -> j
                       .subject("user-123")
                       .claim("email", "john@example.com")
                       .claim("scope", "read:orders"))
                   .authorities(new SimpleGrantedAuthority("SCOPE_read:orders"))))
           .andExpect(status().isOk());
}
```

### OpaqueToken testing

```java
import static org.springframework.security.test.web.servlet.request.SecurityMockMvcRequestPostProcessors.opaqueToken;

@Test
void withOpaqueToken() throws Exception {
    mockMvc.perform(get("/api/profile")
               .with(opaqueToken()
                   .attributes(attrs -> attrs.put("sub", "user-123"))
                   .authorities(new SimpleGrantedAuthority("SCOPE_read"))))
           .andExpect(status().isOk());
}
```

---

## @WithUserDetails

Əsl `UserDetailsService` istifadə edərək test etmək:

```java
// UserDetailsService bean-dən "testUser" username-ini yükləyir
@Test
@WithUserDetails("testUser")
void withRealUserDetails() throws Exception {
    mockMvc.perform(get("/api/profile"))
           .andExpect(status().isOk());
}

// Custom UserDetailsService
@Test
@WithUserDetails(value = "admin@example.com", userDetailsServiceBeanName = "appUserDetailsService")
void withCustomService() throws Exception {
    mockMvc.perform(delete("/api/users/5"))
           .andExpect(status().isOk());
}
```

**Qeyd:** `@WithUserDetails` real database/service çağırır. Integration test kontekstinin DataSource-u olmalıdır. `@WebMvcTest`-də istifadə edilirsə, `UserDetailsService` mock edilməlidir.

---

## Method Security testing

`@PreAuthorize`, `@PostAuthorize` metodlarını test etmək:

```java
@Service
public class OrderService {

    @PreAuthorize("hasRole('ADMIN') or #userId == authentication.principal.id")
    public Order getOrder(Long userId, Long orderId) {
        return orderRepository.findById(orderId).orElseThrow();
    }

    @PreAuthorize("hasAuthority('ORDER_DELETE')")
    public void deleteOrder(Long orderId) {
        orderRepository.deleteById(orderId);
    }
}
```

```java
@SpringBootTest
@EnableMethodSecurity                // test context-də aktiv etmək lazımdır
class OrderServiceTest {

    @Autowired OrderService orderService;

    @Test
    @WithMockUser(roles = "ADMIN")
    void getOrder_admin_succeeds() {
        assertDoesNotThrow(() -> orderService.getOrder(99L, 1L));
    }

    @Test
    @WithAppUser(id = 42L)           // custom annotasiya — id match
    void getOrder_ownUser_succeeds() {
        assertDoesNotThrow(() -> orderService.getOrder(42L, 1L));
    }

    @Test
    @WithMockUser(roles = "USER")    // admin deyil, id fərqli
    void getOrder_unauthorized_throws() {
        assertThrows(AccessDeniedException.class,
            () -> orderService.getOrder(99L, 1L));
    }

    @Test
    @WithMockUser(authorities = "ORDER_DELETE")
    void deleteOrder_withAuthority_succeeds() {
        assertDoesNotThrow(() -> orderService.deleteOrder(1L));
    }
}
```

---

## Testcontainers ilə inteqrasiya

`@SpringBootTest` + Testcontainers ilə real security flow test etmək:

```java
@SpringBootTest(webEnvironment = SpringBootTest.WebEnvironment.RANDOM_PORT)
@Testcontainers
class AuthIntegrationTest {

    @Container
    static PostgreSQLContainer<?> postgres = new PostgreSQLContainer<>("postgres:16")
        .withDatabaseName("testdb");

    @DynamicPropertySource
    static void configureProperties(DynamicPropertyRegistry registry) {
        registry.add("spring.datasource.url", postgres::getJdbcUrl);
        registry.add("spring.datasource.username", postgres::getUsername);
        registry.add("spring.datasource.password", postgres::getPassword);
    }

    @Autowired TestRestTemplate restTemplate;
    @Autowired UserRepository userRepository;

    @Test
    void login_withValidCredentials_returnsJwt() {
        // real DB-ə user saxla
        userRepository.save(new User("john@example.com", passwordEncoder.encode("secret")));

        LoginRequest req = new LoginRequest("john@example.com", "secret");
        ResponseEntity<TokenResponse> resp = restTemplate.postForEntity(
            "/auth/login", req, TokenResponse.class);

        assertThat(resp.getStatusCode()).isEqualTo(HttpStatus.OK);
        assertThat(resp.getBody().token()).isNotBlank();
    }

    @Test
    void protectedEndpoint_withJwt_succeeds() {
        String token = obtainJwt("john@example.com", "secret");

        HttpHeaders headers = new HttpHeaders();
        headers.setBearerAuth(token);

        ResponseEntity<ProfileResponse> resp = restTemplate.exchange(
            "/api/profile", HttpMethod.GET, new HttpEntity<>(headers), ProfileResponse.class);

        assertThat(resp.getStatusCode()).isEqualTo(HttpStatus.OK);
    }
}
```

---

## Praktik Tapşırıqlar

**1. CRUD API security test:**
- `GET /api/items` — hər kəs, amma authenticated
- `POST /api/items` — yalnız `ADMIN`
- `DELETE /api/items/{id}` — yalnız `ADMIN` və ya item sahibi
- Hər 3 endpoint üçün pozitiv + neqativ test yaz

**2. JWT claim-based authorization:**
- JWT içindəki `tenant_id` claim-dən tenant ayrımı et
- Fərqli `tenant_id`-li JWT ilə başqa tenantin datasına girişi blok et
- Custom `WithJwtUser(tenantId=…)` annotasiya yaz

**3. CSRF test:**
- CSRF aktiv olan bir HTML form endpoint-i yaz (login, settings)
- `.with(csrf())` olmadan POST-un 403 verməsini, ilə 200 verməsini test et

---

## Əlaqəli Mövzular
- [85 — @SpringBootTest](85-boot-test.md)
- [86 — @WebMvcTest](86-webmvctest.md)
- [88 — Testcontainers](88-testcontainers.md)
- [53 — Spring Security Arxitekturası](53-security-architecture.md)
- [60 — JWT](60-security-jwt.md)
- [61 — OAuth2](61-security-oauth2.md)
