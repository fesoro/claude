# 55 — Spring Security Avtorizasiya

> **Seviyye:** Senior ⭐⭐⭐


## Mündəricat
1. [Autentifikasiya vs Avtorizasiya](#autentifikasiya-vs-avtorizasiya)
2. [hasRole() vs hasAuthority()](#hasrole-vs-hasauthority)
3. [Request Matcher Patterns](#request-matcher-patterns)
4. [Method Security Annotasiyaları](#method-security-annotasiyaları)
5. [@PreAuthorize və @PostAuthorize](#preauthorize-və-postauthorize)
6. [@Secured və @RolesAllowed](#secured-və-rolesallowed)
7. [@EnableMethodSecurity](#enablemethodsecurity)
8. [Authorization Hierarchy](#authorization-hierarchy)
9. [İntervyu Sualları](#i̇ntervyu-sualları)

---

## Autentifikasiya vs Avtorizasiya

| Xüsusiyyət | Autentifikasiya | Avtorizasiya |
|-----------|----------------|-------------|
| Sual | Kim olduğunu yoxlamaq | Nə edə bilərini yoxlamaq |
| Nə vaxt | Əvvəl | Sonra |
| Xəta kodu | 401 Unauthorized | 403 Forbidden |
| Məlumat | Username + Password | Rollar, İcazələr |
| Spring exception | `AuthenticationException` | `AccessDeniedException` |

```java
// Autentifikasiya xətası - 401
// Hər hansı qorunan resursa anonim giriş:
// GET /api/profile → 401 Unauthorized

// Avtorizasiya xətası - 403
// Admin resursa normal user girişi:
// GET /api/admin/users → 403 Forbidden (giriş edilib, amma icazə yoxdur)

@RestControllerAdvice
public class GlobalExceptionHandler {

    // 401 - Autentifikasiya xətası
    @ExceptionHandler(AuthenticationException.class)
    public ResponseEntity<ErrorResponse> handleAuthenticationException(
            AuthenticationException ex) {
        return ResponseEntity.status(HttpStatus.UNAUTHORIZED)
            .body(new ErrorResponse("Giriş tələb olunur"));
    }

    // 403 - Avtorizasiya xətası
    @ExceptionHandler(AccessDeniedException.class)
    public ResponseEntity<ErrorResponse> handleAccessDeniedException(
            AccessDeniedException ex) {
        return ResponseEntity.status(HttpStatus.FORBIDDEN)
            .body(new ErrorResponse("Bu əməliyyat üçün icazəniz yoxdur"));
    }
}
```

---

## hasRole() vs hasAuthority()

Bu ikisi arasındakı **əsas fərq** `ROLE_` prefiksidir:

```java
// hasRole("ADMIN")     →  "ROLE_ADMIN" authority-ni axtarır (ROLE_ əlavə edilir)
// hasAuthority("ROLE_ADMIN") →  "ROLE_ADMIN" authority-ni axtarır (əlavə edilmir)

// Bunlar EKVİVALENTDİR:
.hasRole("ADMIN")
.hasAuthority("ROLE_ADMIN")

// Bunlar da ekvivalentdir:
.hasAnyRole("ADMIN", "MODERATOR")
.hasAnyAuthority("ROLE_ADMIN", "ROLE_MODERATOR")
```

### YANLIŞ vs DOĞRU

```java
// YANLIŞ: hasRole-a ROLE_ prefiksi əlavə etmək
http.authorizeHttpRequests(auth -> auth
    .requestMatchers("/admin/**").hasRole("ROLE_ADMIN") // "ROLE_ROLE_ADMIN" axtarır!
);

// DOGRU: hasRole-da prefix olmadan
http.authorizeHttpRequests(auth -> auth
    .requestMatchers("/admin/**").hasRole("ADMIN") // "ROLE_ADMIN" axtarır - düzgün
);

// YANLIŞ: hasAuthority-dən ROLE_ prefiksi atmaq
http.authorizeHttpRequests(auth -> auth
    .requestMatchers("/admin/**").hasAuthority("ADMIN") // "ADMIN" axtarır, "ROLE_ADMIN" deyil!
);

// DOGRU: hasAuthority-də tam ad
http.authorizeHttpRequests(auth -> auth
    .requestMatchers("/admin/**").hasAuthority("ROLE_ADMIN") // Düzgün
);

// İcazə (permission) üçün hasAuthority istifadə et:
http.authorizeHttpRequests(auth -> auth
    .requestMatchers(HttpMethod.DELETE, "/posts/**").hasAuthority("DELETE_POST")
    .requestMatchers(HttpMethod.GET, "/reports/**").hasAuthority("READ_REPORT")
);
```

### GrantedAuthority Qurma

```java
@Override
public Collection<? extends GrantedAuthority> getAuthorities() {
    List<GrantedAuthority> authorities = new ArrayList<>();

    user.getRoles().forEach(role -> {
        // Rol əlavə et (ROLE_ prefiksi ilə)
        authorities.add(new SimpleGrantedAuthority("ROLE_" + role.getName()));

        // Rolun icazələrini əlavə et (prefikssiz)
        role.getPermissions().forEach(perm ->
            authorities.add(new SimpleGrantedAuthority(perm.getName()))
        );
    });

    return authorities;
    // Nəticə: ["ROLE_ADMIN", "READ_USER", "DELETE_USER", "WRITE_USER"]
}
```

---

## Request Matcher Patterns

```java
@Bean
public SecurityFilterChain securityFilterChain(HttpSecurity http) throws Exception {
    http.authorizeHttpRequests(auth -> auth

        // 1. Tam URL
        .requestMatchers("/api/login").permitAll()

        // 2. Wildcard - * bir path segment
        .requestMatchers("/api/public/*").permitAll()  // /api/public/news, amma /api/public/news/1 deyil

        // 3. Double wildcard - ** bütün alt path-lar
        .requestMatchers("/api/public/**").permitAll() // Bütün alt path-lar daxil

        // 4. HTTP metoduna görə
        .requestMatchers(HttpMethod.GET, "/api/posts/**").permitAll()
        .requestMatchers(HttpMethod.POST, "/api/posts/**").hasRole("USER")
        .requestMatchers(HttpMethod.DELETE, "/api/posts/**").hasRole("ADMIN")

        // 5. Regex
        .requestMatchers(new RegexRequestMatcher("/api/v[0-9]+/.*", null)).authenticated()

        // 6. Rol tələbləri
        .requestMatchers("/api/admin/**").hasRole("ADMIN")
        .requestMatchers("/api/moderator/**").hasAnyRole("ADMIN", "MODERATOR")

        // 7. İfadə ilə
        .requestMatchers("/api/reports/**").access(
            new WebExpressionAuthorizationManager("hasRole('ADMIN') and isFullyAuthenticated()")
        )

        // 8. Qalanları - autentifikasiya tələb edir
        .anyRequest().authenticated()
    );

    return http.build();
}
```

### Sıra Önəmlidir!

```java
// YANLIŞ: Ümumi qayda əvvəl gəlir - spesifik qaydalar heç vaxt çatmır
http.authorizeHttpRequests(auth -> auth
    .anyRequest().authenticated()      // Bu birinci olsa...
    .requestMatchers("/public/**").permitAll() // ...bura heç vaxt gəlinmir!
);

// DOGRU: Spesifik qaydalar əvvəl, ümumi qayda sonda
http.authorizeHttpRequests(auth -> auth
    .requestMatchers("/api/auth/**").permitAll()  // Əvvəl spesifik
    .requestMatchers("/api/admin/**").hasRole("ADMIN")
    .anyRequest().authenticated()                 // Sonda ümumi
);
```

---

## Method Security Annotasiyaları

Method Security — URL səviyyəsindən əlavə olaraq, service/metod səviyyəsində icazə nəzarəti əlavə edir.

```java
// Aktiv etmək üçün:
@Configuration
@EnableMethodSecurity // Spring Security 6.x
public class SecurityConfig {
    // ...
}
```

---

## @PreAuthorize və @PostAuthorize

### @PreAuthorize

Metod **çağırılmazdan əvvəl** yoxlayır. SpEL (Spring Expression Language) ifadəsi dəstəkləyir.

```java
@RestController
@RequestMapping("/api/users")
public class UserController {

    // 1. Rol yoxlama
    @GetMapping
    @PreAuthorize("hasRole('ADMIN')")
    public List<UserResponse> getAllUsers() {
        return userService.findAll();
    }

    // 2. Çoxlu rol
    @GetMapping("/reports")
    @PreAuthorize("hasAnyRole('ADMIN', 'MODERATOR')")
    public List<ReportResponse> getReports() {
        return reportService.findAll();
    }

    // 3. Method parametrinə istinad (#param)
    @GetMapping("/{id}")
    @PreAuthorize("hasRole('ADMIN') or #id == authentication.principal.id")
    public UserResponse getUser(@PathVariable Long id) {
        // Admin hər kəsi görə bilər, user yalnız özünü
        return userService.findById(id);
    }

    // 4. İstifadəçi adına istinad
    @PutMapping("/{username}/profile")
    @PreAuthorize("#username == authentication.name")
    public void updateProfile(@PathVariable String username,
                               @RequestBody ProfileRequest request) {
        // Yalnız öz profilini yeniləyə bilər
        userService.updateProfile(username, request);
    }

    // 5. Bean metoduna istinad (@beanName.method(args))
    @DeleteMapping("/{id}")
    @PreAuthorize("@userSecurityService.canDelete(authentication, #id)")
    public void deleteUser(@PathVariable Long id) {
        userService.delete(id);
    }

    // 6. Mürəkkəb ifadə
    @PostMapping("/{id}/ban")
    @PreAuthorize("hasRole('ADMIN') and !@userSecurityService.isSuperAdmin(#id)")
    public void banUser(@PathVariable Long id) {
        // Super admin-i ban etmək olmaz
        userService.ban(id);
    }
}

// Bean istinadı üçün custom servis:
@Service("userSecurityService")
public class UserSecurityService {

    private final UserRepository userRepository;

    public UserSecurityService(UserRepository userRepository) {
        this.userRepository = userRepository;
    }

    public boolean canDelete(Authentication authentication, Long userId) {
        // Admin hər kəsi, user yalnız özünü silə bilər
        if (authentication.getAuthorities().stream()
                .anyMatch(a -> a.getAuthority().equals("ROLE_ADMIN"))) {
            return true;
        }
        UserDetails userDetails = (UserDetails) authentication.getPrincipal();
        User currentUser = ((CustomUserDetails) userDetails).getUser();
        return currentUser.getId().equals(userId);
    }

    public boolean isSuperAdmin(Long userId) {
        return userRepository.findById(userId)
            .map(User::isSuperAdmin)
            .orElse(false);
    }
}
```

### @PostAuthorize

Metod **çağırıldıqdan sonra** nəticəni yoxlayır. `returnObject` ilə nəticəyə müraciət edilir.

```java
@Service
public class DocumentService {

    // Sənədi yükləyir, amma ancaq sahibinə qaytarır
    @PostAuthorize("returnObject.owner == authentication.name")
    public Document findById(Long id) {
        // Metod işləyir, amma nəticə sahibdən başqasına qaytarılmır
        return documentRepository.findById(id)
            .orElseThrow(() -> new ResourceNotFoundException("Sənəd tapılmadı"));
    }

    // Admin hər şeyi görə bilər, user yalnız öz sənədlərini
    @PostAuthorize("hasRole('ADMIN') or returnObject.createdBy == authentication.name")
    public Report getReport(Long id) {
        return reportRepository.findById(id)
            .orElseThrow();
    }
}
```

### SpEL İfadələri Cədvəli

| İfadə | Məna |
|-------|------|
| `hasRole('ADMIN')` | ROLE_ADMIN authority-si var |
| `hasAnyRole('ADMIN','USER')` | Bu rollardan biri var |
| `hasAuthority('READ_DATA')` | Bu authority var |
| `isAuthenticated()` | Giriş edilib |
| `isAnonymous()` | Anonim istifadəçi |
| `isFullyAuthenticated()` | Remember-Me deyil, tam giriş |
| `principal` | Authentication.getPrincipal() |
| `authentication` | Tam Authentication obyekti |
| `#paramName` | Metod parametri |
| `returnObject` | Metodun qaytardığı dəyər (@PostAuthorize) |
| `@beanName.method()` | Spring bean-inin metodunu çağır |

---

## @Secured və @RolesAllowed

Bunlar daha sadə annotasiyalardır, SpEL dəstəkləmirlər.

```java
// @Secured - Spring Security öz annotasiyası
@Service
public class AdminService {

    @Secured("ROLE_ADMIN") // "ROLE_" prefiksi LAZIMDIR
    public void deleteUser(Long id) {
        userRepository.deleteById(id);
    }

    @Secured({"ROLE_ADMIN", "ROLE_MODERATOR"}) // Çoxlu rol
    public List<User> getAllUsers() {
        return userRepository.findAll();
    }
}

// @RolesAllowed - JSR-250 standart annotasiyası (Jakarta EE)
@Service
public class PostService {

    @RolesAllowed("ROLE_USER") // "ROLE_" prefiksi lazımdır
    public Post createPost(PostRequest request) {
        return postRepository.save(new Post(request));
    }

    @RolesAllowed({"ROLE_ADMIN", "ROLE_MODERATOR"})
    public void approvePost(Long id) {
        postRepository.findById(id).ifPresent(post -> {
            post.setApproved(true);
            postRepository.save(post);
        });
    }
}
```

### Müqayisə

| Xüsusiyyət | @PreAuthorize | @Secured | @RolesAllowed |
|-----------|--------------|---------|--------------|
| SpEL dəstəyi | Bəli | Xeyr | Xeyr |
| Parametr yoxlama | Bəli (#param) | Xeyr | Xeyr |
| Return dəyər | @PostAuthorize | Xeyr | Xeyr |
| ROLE_ prefiksi | Lazım deyil | Lazımdır | Lazımdır |
| Standard | Spring | Spring | JSR-250 |
| Tövsiyə | Ən güclü | Sadə hallarda | Portativlik üçün |

---

## @EnableMethodSecurity

Spring Security 6.x-də köhnə `@EnableGlobalMethodSecurity` əvəzinə `@EnableMethodSecurity` istifadə edilir.

```java
// YANLIŞ: Köhnə üsul (deprecated)
@Configuration
@EnableGlobalMethodSecurity(
    prePostEnabled = true,
    securedEnabled = true,
    jsr250Enabled = true
)
public class OldSecurityConfig { }

// DOGRU: Spring Security 6.x üsulu
@Configuration
@EnableWebSecurity
@EnableMethodSecurity(
    prePostEnabled = true,   // @PreAuthorize, @PostAuthorize, @PreFilter, @PostFilter (default: true)
    securedEnabled = true,   // @Secured (default: false)
    jsr250Enabled = true     // @RolesAllowed, @PermitAll, @DenyAll (default: false)
)
public class SecurityConfig {

    @Bean
    public SecurityFilterChain securityFilterChain(HttpSecurity http) throws Exception {
        http.authorizeHttpRequests(auth -> auth
            .anyRequest().authenticated()
        );
        return http.build();
    }
}
```

### Method Security Necə İşləyir (Proxy)

```java
// Spring AOP Proxy vasitəsilə işləyir:
// UserService -> Proxy -> @PreAuthorize yoxlanır -> Metod çağırılır

@Service
public class UserService {

    @PreAuthorize("hasRole('ADMIN')")
    public List<User> findAll() {
        // Bu metod yalnız ADMIN rollu istifadəçilər çağıra bilər
        return userRepository.findAll();
    }
}

// VACIB: Method Security yalnız Spring Proxy üzərindən keçən çağırışlarda işləyir!
// Eyni sinifdən çağırış (self-invocation) işləmir:
@Service
public class PostService {

    @PreAuthorize("hasRole('ADMIN')")
    public void deletePost(Long id) {
        postRepository.deleteById(id);
    }

    public void processExpiredPosts() {
        // YANLIŞ: Bu daxili çağırış @PreAuthorize-ı bypass edir!
        this.deletePost(1L); // Security yoxlanmır!
    }
}

// DOGRU: Başqa servis vasitəsilə çağır
@Service
public class PostCleanupService {

    @Autowired
    private PostService postService; // Spring proxy-si

    public void processExpiredPosts() {
        // DOGRU: Proxy üzərindən keçir, @PreAuthorize işləyir
        postService.deletePost(1L);
    }
}
```

---

## Authorization Hierarchy

Rol iyerarxiyası — yüksək rol aşağı rolların icazələrini miras alır.

```java
// ADMIN → MODERATOR → USER iyerarxiyası:
// ADMIN bütün MODERATOR və USER resurslara da daxil ola bilər
// MODERATOR bütün USER resurslara da daxil ola bilər

@Bean
public RoleHierarchy roleHierarchy() {
    RoleHierarchyImpl hierarchy = new RoleHierarchyImpl();
    hierarchy.setHierarchy("""
        ROLE_ADMIN > ROLE_MODERATOR
        ROLE_MODERATOR > ROLE_USER
        ROLE_USER > ROLE_GUEST
        """);
    return hierarchy;
}

// Method Security üçün RoleHierarchy-ı register et:
@Bean
public MethodSecurityExpressionHandler methodSecurityExpressionHandler(
        RoleHierarchy roleHierarchy) {
    DefaultMethodSecurityExpressionHandler handler =
        new DefaultMethodSecurityExpressionHandler();
    handler.setRoleHierarchy(roleHierarchy);
    return handler;
}

// Web Security üçün:
@Bean
public SecurityExpressionHandler<FilterInvocation> webSecurityExpressionHandler(
        RoleHierarchy roleHierarchy) {
    DefaultWebSecurityExpressionHandler handler =
        new DefaultWebSecurityExpressionHandler();
    handler.setRoleHierarchy(roleHierarchy);
    return handler;
}
```

### İyerarxiya ilə nümunə

```java
// ROLE_ADMIN > ROLE_MODERATOR > ROLE_USER qurulubsa:

@GetMapping("/user-content")
@PreAuthorize("hasRole('USER')") // ADMIN, MODERATOR, USER hamısı daxil ola bilər
public String userContent() { return "User content"; }

@GetMapping("/moderator-content")
@PreAuthorize("hasRole('MODERATOR')") // ADMIN, MODERATOR daxil ola bilər
public String moderatorContent() { return "Moderator content"; }

@GetMapping("/admin-content")
@PreAuthorize("hasRole('ADMIN')") // Yalnız ADMIN daxil ola bilər
public String adminContent() { return "Admin content"; }
```

---

## İntervyu Sualları

**S: `hasRole('ADMIN')` ilə `hasAuthority('ROLE_ADMIN')` arasında nə fərq var?**

C: Funksional olaraq eynidir. `hasRole('ADMIN')` avtomatik `ROLE_` prefiksi əlavə edir və `ROLE_ADMIN` authority-ni axtarır. `hasAuthority('ROLE_ADMIN')` isə məhz yazdığınız authority-ni axtarır, heç bir dəyişiklik etmir. Buna görə `hasRole` ilə `ROLE_` prefiksi YAZILMAZ, `hasAuthority` ilə isə lazım olduqda tam yazılmalıdır.

---

**S: @PreAuthorize ilə URL-based authorization arasındakı fərq nədir?**

C: URL-based authorization (`authorizeHttpRequests`) yalnız HTTP endpoint-ləri qoruyur. `@PreAuthorize` isə servis metod səviyyəsində qoruyur. Bu o deməkdir ki, `@PreAuthorize` olan servis metodu həm HTTP, həm də daxili Java çağırışından çağırılsa belə qoruma aktiv olur (proxy üzərindən keçdikdə). İkisini birlikdə istifadə etmək "defense in depth" (çox qatlı müdafiə) prinsipini həyata keçirir.

---

**S: Method Security niyə self-invocation-da işləmir?**

C: Spring Method Security AOP Proxy mexanizminə əsaslanır. `@Autowired` Bean aldığınızda əslində Proxy alırsınız. Proxy giriş zamanı security yoxlayır. Lakin bir metoddan eyni sinifin başqa metodunu `this.method()` ilə çağırdığınızda — proxy bypass edilir, birbaşa obyektə gedir, security yoxlanmır. Həll yolu: Başqa bir Spring Bean vasitəsilə çağırmaq, ya da `ApplicationContext`-dən öz proxy-nizi almaq.

---

**S: `@EnableMethodSecurity` ilə köhnə `@EnableGlobalMethodSecurity` arasındakı fərq nədir?**

C: Spring Security 6.x-dən `@EnableGlobalMethodSecurity` deprecated oldu. `@EnableMethodSecurity` daha sadədir: `prePostEnabled=true` default gəlir, əvvəllər false idi. Bundan əlavə yeni annotasiya daha yaxşı performance-a malikdir, çünki AOP proxy əvəzinə daha effektiv Authorization Manager istifadə edir.

---

**S: Authorization Hierarchy nə üçün lazımdır?**

C: Rol iyerarxiyası olmadan hər resursu ayrıca bütün rollar üçün konfiqurasiya etmək lazımdır. Məsələn ADMIN resursunu yalnız ADMIN üçün yox, həm də məntiqi olaraq onu include edən üst rollar üçün əlavə etmək lazımdır. Hierarchy ilə bir dəfə `ROLE_ADMIN > ROLE_MODERATOR > ROLE_USER` yazdıqda, ADMIN artıq User və Moderator resurslarına da avtomatik daxil ola bilər. Bu konfiqurasiya duplikasiyasını azaldır.
