# Spring Session

## Nə üçün lazımdır?

Ənənəvi HTTP session (`HttpSession`) yalnız bir server instance-da işləyir. Horizontal scaling zamanı sticky session ya da paylaşılan session storage lazım olur. Spring Session — session məlumatlarını xarici store-da (Redis, JDBC, MongoDB) saxlayır, bütün instance-lar eyni session-u görür.

---

## Dependency

```xml
<!-- Redis store üçün -->
<dependency>
    <groupId>org.springframework.session</groupId>
    <artifactId>spring-session-data-redis</artifactId>
</dependency>
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-data-redis</artifactId>
</dependency>
```

---

## application.yml

```yaml
spring:
  session:
    store-type: redis          # redis | jdbc | mongodb | hazelcast
    timeout: 30m               # session müddəti
    redis:
      namespace: myapp:session  # Redis key prefix
      flush-mode: on-save       # on-save | immediate
  data:
    redis:
      host: localhost
      port: 6379
```

---

## Əsas konfiqurasiya

```java
@Configuration
@EnableRedisHttpSession(
    maxInactiveIntervalInSeconds = 1800,  // 30 dəq
    redisNamespace = "myapp:session",
    flushMode = FlushMode.ON_SAVE
)
public class SessionConfig {

    // Spring Boot auto-config varsa bu sinif lazım deyil
    // Manual konfiqurasiya üçün istifadə edilir
}
```

---

## Sadə istifadə

```java
@RestController
@RequestMapping("/api")
public class SessionController {

    @PostMapping("/login")
    public ResponseEntity<String> login(
            HttpSession session,
            @RequestBody LoginRequest req) {

        // autentifikasiya yoxlaması...
        User user = authenticate(req.username(), req.password());

        session.setAttribute("user", user);
        session.setAttribute("loginTime", Instant.now());
        session.setMaxInactiveInterval(3600); // 1 saat

        return ResponseEntity.ok("Logged in: " + session.getId());
    }

    @GetMapping("/profile")
    public ResponseEntity<User> profile(HttpSession session) {
        User user = (User) session.getAttribute("user");
        if (user == null) {
            return ResponseEntity.status(401).build();
        }
        return ResponseEntity.ok(user);
    }

    @PostMapping("/logout")
    public ResponseEntity<Void> logout(HttpSession session) {
        session.invalidate();
        return ResponseEntity.noContent().build();
    }
}
```

---

## Session-u tapıb idarə etmək

```java
@Service
@RequiredArgsConstructor
public class SessionManagementService {

    private final FindByIndexNameSessionRepository<? extends Session> sessionRepository;

    // İstifadəçinin bütün session-larını tap
    public Map<String, ? extends Session> getUserSessions(String username) {
        return sessionRepository.findByPrincipalName(username);
    }

    // İstifadəçinin bütün session-larını sonlandır (force logout)
    public void terminateAllSessions(String username) {
        Map<String, ? extends Session> sessions = getUserSessions(username);
        sessions.keySet().forEach(sessionRepository::deleteById);
    }

    // Konkret session məlumatları
    public SessionInfo getSessionInfo(String sessionId) {
        Session session = sessionRepository.findById(sessionId);
        if (session == null) return null;

        return new SessionInfo(
            sessionId,
            session.getCreationTime(),
            session.getLastAccessedTime(),
            session.getMaxInactiveInterval(),
            session.getAttributeNames()
        );
    }
}

record SessionInfo(
    String id,
    Instant createdAt,
    Instant lastAccessed,
    Duration maxInactive,
    Set<String> attributes
) {}
```

---

## Principal indexing (FindByIndexName)

Spring Session Redis — principal adına görə session axtarışı üçün xüsusi index saxlayır.

```java
@Configuration
@EnableRedisIndexedHttpSession
public class RedisSessionConfig {
    // @EnableRedisIndexedHttpSession — FindByIndexName support-u aktivləşdirir
}
```

```java
// Session yaradanda principal adını index-ə əlavə et
session.setAttribute(
    FindByIndexNameSessionRepository.PRINCIPAL_NAME_INDEX_NAME,
    username
);

// və ya Spring Security istifadə edirsən — avtomatik edilir
```

---

## Spring Security inteqrasiyası

```java
@Configuration
@EnableWebSecurity
@RequiredArgsConstructor
public class SecurityConfig {

    private final FindByIndexNameSessionRepository<? extends Session> sessionRepository;

    @Bean
    public SecurityFilterChain filterChain(HttpSecurity http) throws Exception {
        http
            .sessionManagement(session -> session
                .maximumSessions(3)                    // max 3 aktiv session
                .maxSessionsPreventsLogin(false)       // false = köhnəni sil, yeni gəlsin
                .sessionRegistry(sessionRegistry())
            )
            .authorizeHttpRequests(auth -> auth
                .requestMatchers("/api/public/**").permitAll()
                .anyRequest().authenticated()
            );

        return http.build();
    }

    @Bean
    public SpringSessionBackedSessionRegistry<? extends Session> sessionRegistry() {
        return new SpringSessionBackedSessionRegistry<>(sessionRepository);
    }
}
```

---

## JDBC Session Store

```xml
<dependency>
    <groupId>org.springframework.session</groupId>
    <artifactId>spring-session-jdbc</artifactId>
</dependency>
```

```yaml
spring:
  session:
    store-type: jdbc
    jdbc:
      initialize-schema: always   # DB sxemini avtomatik yarat
      table-name: SPRING_SESSION
  datasource:
    url: jdbc:postgresql://localhost:5432/mydb
```

```sql
-- Spring tərəfindən avtomatik yaradılan cədvəllər:
-- SPRING_SESSION — əsas session məlumatı
-- SPRING_SESSION_ATTRIBUTES — session attributeları

-- Əl ilə yaratmaq lazım gəlsə:
CREATE TABLE SPRING_SESSION (
    PRIMARY_ID            CHAR(36) NOT NULL,
    SESSION_ID            CHAR(36) NOT NULL,
    CREATION_TIME         BIGINT   NOT NULL,
    LAST_ACCESS_TIME      BIGINT   NOT NULL,
    MAX_INACTIVE_INTERVAL INT      NOT NULL,
    EXPIRY_TIME           BIGINT   NOT NULL,
    PRINCIPAL_NAME        VARCHAR(100),
    CONSTRAINT SPRING_SESSION_PK PRIMARY KEY (PRIMARY_ID)
);

CREATE UNIQUE INDEX SPRING_SESSION_IX1 ON SPRING_SESSION (SESSION_ID);
CREATE INDEX SPRING_SESSION_IX2 ON SPRING_SESSION (EXPIRY_TIME);
CREATE INDEX SPRING_SESSION_IX3 ON SPRING_SESSION (PRINCIPAL_NAME);
```

---

## Custom Session Event listener

```java
@Component
public class SessionEventListener {

    private static final Logger log = LoggerFactory.getLogger(SessionEventListener.class);

    @EventListener
    public void onSessionCreated(SessionCreatedEvent event) {
        log.info("Session yaradıldı: {}", event.getSessionId());
    }

    @EventListener
    public void onSessionDeleted(SessionDeletedEvent event) {
        log.info("Session silindi: {}", event.getSessionId());
        // cleanup işləri: token revoke, audit log...
    }

    @EventListener
    public void onSessionExpired(SessionExpiredEvent event) {
        String sessionId = event.getSessionId();
        Session session = event.getSession();

        String username = (String) session.getAttribute(
            FindByIndexNameSessionRepository.PRINCIPAL_NAME_INDEX_NAME
        );
        log.warn("Session vaxtı bitdi — user: {}, sessionId: {}", username, sessionId);

        // Notification göndər, metrics yenilə...
    }
}
```

---

## Cookie konfiqurasiyası

```java
@Configuration
public class CookieConfig {

    @Bean
    public CookieSerializer cookieSerializer() {
        DefaultCookieSerializer serializer = new DefaultCookieSerializer();
        serializer.setCookieName("MYSESSION");    // default: SESSION
        serializer.setCookiePath("/");
        serializer.setDomainNamePattern("^.+?\\.(\\w+\\.[a-z]+)$"); // subdomain sharing
        serializer.setCookieMaxAge(3600);
        serializer.setUseSecureCookie(true);      // HTTPS only
        serializer.setSameSite("Strict");          // CSRF qorunması
        serializer.setUseHttpOnlyCookie(true);     // JS-dən gizlə
        return serializer;
    }
}
```

---

## Header-based Session (REST API / mobile)

Cookie əvəzinə `X-Auth-Token` header istifadə etmək üçün:

```java
@Configuration
public class HeaderSessionConfig {

    @Bean
    public HttpSessionIdResolver httpSessionIdResolver() {
        return HeaderHttpSessionIdResolver.xAuthToken();
        // X-Auth-Token header-ında session ID gəlir/göndərilir
    }
}
```

```http
# Login cavabında:
X-Auth-Token: 3b2b5d12-9b6f-4c8a-a3e9-2d1f5e7c8a9b

# Növbəti sorğularda:
GET /api/profile
X-Auth-Token: 3b2b5d12-9b6f-4c8a-a3e9-2d1f5e7c8a9b
```

---

## Redis-dəki session strukturu

```
# Bir session üçün Redis key-ləri:
myapp:session:sessions:<sessionId>                    → Hash (session data)
myapp:session:sessions:expires:<sessionId>            → String (TTL tracking)
myapp:session:index:org.springframework...PRINCIPAL_NAME_INDEX_NAME:<username>  → Set (user sessions)
```

```bash
# Redis CLI ilə yoxlamaq:
redis-cli hgetall "myapp:session:sessions:abc123"
redis-cli smembers "myapp:session:index:...PRINCIPAL_NAME_INDEX_NAME:john"
```

---

## Test

```java
@SpringBootTest
@AutoConfigureMockMvc
class SessionIntegrationTest {

    @Autowired
    MockMvc mockMvc;

    @Test
    void session_saxlanır_və_oxunur() throws Exception {
        // Login — session yaranır
        MvcResult loginResult = mockMvc.perform(post("/api/login")
                .contentType(MediaType.APPLICATION_JSON)
                .content("""{"username":"user","password":"pass"}"""))
            .andExpect(status().isOk())
            .andReturn();

        MockHttpSession session = (MockHttpSession)
            loginResult.getRequest().getSession(false);

        assertThat(session).isNotNull();
        assertThat(session.getAttribute("user")).isNotNull();

        // Eyni session ilə profile sorğusu
        mockMvc.perform(get("/api/profile").session(session))
            .andExpect(status().isOk())
            .andExpect(jsonPath("$.username").value("user"));
    }

    @Test
    void logout_session_silir() throws Exception {
        MockHttpSession session = new MockHttpSession();
        session.setAttribute("user", new User("test"));

        mockMvc.perform(post("/api/logout").session(session))
            .andExpect(status().isNoContent());

        assertThat(session.isInvalid()).isTrue();
    }
}
```

---

## Müqayisə

| Xüsusiyyət | Redis Store | JDBC Store |
|------------|-------------|------------|
| Sürət | Çox sürətli | Orta |
| Persistence | Optional (RDB/AOF) | Həmişə |
| Clustering | Native | DB cluster |
| Axtarış | Index ilə | SQL sorğu |
| İstifadə | Yüksək yük | Sadə tətbiq |

---

## Anti-pattern-lər

```java
// ❌ Session-a böyük obyekt saxlamaq
session.setAttribute("allProducts", productRepository.findAll()); // MB-larla data

// ✅ Yalnız ID saxla, lazım olanda DB-dən çək
session.setAttribute("userId", user.getId());

// ❌ Session-u concurrent modify etmək
// Session thread-safe deyil — Spring Security lock edir

// ❌ Session olmayan endpoint-də session yaratmaq
// Her GET sorğusunda session.getAttribute() — yeni session yaranır
// @Bean SessionCreationPolicy.STATELESS istifadə et (JWT üçün)
```
