# 53 — Spring Security Arxitekturası

> **Seviyye:** Advanced ⭐⭐⭐


## Mündəricat
1. [Ümumi Baxış](#ümumi-baxış)
2. [DelegatingFilterProxy](#delegatingfilterproxy)
3. [FilterChainProxy](#filterchainproxy)
4. [SecurityFilterChain](#securityfilterchain)
5. [Filter Sırası](#filter-sırası)
6. [SecurityContext və SecurityContextHolder](#securitycontext-və-securitycontextholder)
7. [Authentication Obyekti](#authentication-obyekti)
8. [AuthenticationManager və ProviderManager](#authenticationmanager-və-providermanager)
9. [AuthenticationProvider Zənciri](#authenticationprovider-zənciri)
10. [İntervyu Sualları](#i̇ntervyu-sualları)

---

## Ümumi Baxış

Spring Security, Java tətbiqlərini autentifikasiya (kim olduğunu yoxlamaq) və avtorizasiya (nəyə icazəsi olduğunu yoxlamaq) ilə qoruyan güclü bir framework-dür. Spring Security-nin əsas arxitekturası **Servlet Filter** mexanizminə əsaslanır.

HTTP sorğusu gəldikdə aşağıdakı axın baş verir:

```
HTTP Sorğusu
     ↓
DelegatingFilterProxy (Servlet Container)
     ↓
FilterChainProxy (Spring Bean)
     ↓
SecurityFilterChain (1, 2, 3... zəncir)
     ↓
UsernamePasswordAuthenticationFilter
     ↓
...digər filterlər...
     ↓
DispatcherServlet → Controller
```

---

## DelegatingFilterProxy

`DelegatingFilterProxy` — Servlet Container (Tomcat) ilə Spring Application Context arasında körpü rolunu oynayan bir Servlet Filter-dir. Servlet Container Spring Bean-ləri birbaşa tanımır, buna görə bu proxy vasitəsilə Spring-ə sorğu ötürülür.

```java
// DelegatingFilterProxy avtomatik olaraq Spring Boot tərəfindən qeydiyyata alınır.
// Əl ilə konfiqurasiya (köhnə üsul, Spring Boot-da lazım deyil):
public class SecurityWebApplicationInitializer
        extends AbstractSecurityWebApplicationInitializer {
    // Bu class DelegatingFilterProxy-ni "springSecurityFilterChain" adı ilə qeydiyyata alır
}
```

**Necə işləyir:**
- Servlet Container startup zamanı `DelegatingFilterProxy`-ni yaradır
- İlk sorğu gəldikdə Spring Application Context-dən `FilterChainProxy` bean-ini tapır
- Sonrakı bütün sorğuları `FilterChainProxy`-ə ötürür

---

## FilterChainProxy

`FilterChainProxy` — Spring Bean-i kimi qeydiyyata alınan, birdən çox `SecurityFilterChain`-i idarə edən əsas sinifdir. `springSecurityFilterChain` adı ilə Application Context-də mövcuddur.

```java
// FilterChainProxy-nin daxili məntiqi (sadələşdirilmiş):
// Hər sorğu üçün uyğun SecurityFilterChain tapılır
// İlk uyğun gələn zəncir işləyir, qalanları işləmir

@Configuration
@EnableWebSecurity
public class SecurityConfig {

    // Birdən çox SecurityFilterChain müəyyən etmək:
    @Bean
    @Order(1) // Prioritet - kiçik ədəd = yüksək prioritet
    public SecurityFilterChain apiSecurityFilterChain(HttpSecurity http) throws Exception {
        http
            // Yalnız /api/** sorğularına tətbiq olunur
            .securityMatcher("/api/**")
            .authorizeHttpRequests(auth -> auth
                .anyRequest().authenticated()
            )
            // API üçün JWT istifadə edirik, session yoxdur
            .sessionManagement(session -> session
                .sessionCreationPolicy(SessionCreationPolicy.STATELESS)
            );
        return http.build();
    }

    @Bean
    @Order(2) // İkinci prioritet
    public SecurityFilterChain webSecurityFilterChain(HttpSecurity http) throws Exception {
        http
            // /api/** xaricindəki bütün sorğulara tətbiq olunur
            .authorizeHttpRequests(auth -> auth
                .requestMatchers("/public/**").permitAll()
                .anyRequest().authenticated()
            )
            // Web üçün form login istifadə edirik
            .formLogin(Customizer.withDefaults());
        return http.build();
    }
}
```

---

## SecurityFilterChain

`SecurityFilterChain` — müəyyən URL pattern-lərinə uyğun gələn filterlər toplusudur. Hər zəncir müstəqil konfiqurasiya oluna bilər.

```java
@Configuration
@EnableWebSecurity
public class SecurityConfig {

    @Bean
    public SecurityFilterChain securityFilterChain(HttpSecurity http) throws Exception {
        http
            // CSRF qoruması konfiqurasiyası
            .csrf(csrf -> csrf.disable()) // REST API üçün deaktiv

            // CORS konfiqurasiyası
            .cors(cors -> cors.configurationSource(corsConfigurationSource()))

            // Sorğulara icazə qaydaları
            .authorizeHttpRequests(auth -> auth
                .requestMatchers("/api/public/**").permitAll()       // Hamıya açıq
                .requestMatchers("/api/admin/**").hasRole("ADMIN")   // Yalnız ADMIN
                .anyRequest().authenticated()                         // Qalanları - giriş tələb edir
            )

            // JWT filter əlavə etmək (standart filterdən əvvəl)
            .addFilterBefore(jwtAuthenticationFilter(),
                           UsernamePasswordAuthenticationFilter.class)

            // Session idarəetməsi
            .sessionManagement(session -> session
                .sessionCreationPolicy(SessionCreationPolicy.STATELESS)
            );

        return http.build();
    }
}
```

---

## Filter Sırası

Spring Security filterlərinin standart icra sırası (kiçik ədəd = əvvəl işləyir):

| Sıra | Filter Adı | Vəzifəsi |
|------|-----------|---------|
| -100 | DisableEncodeUrlFilter | URL-də session ID-ni gizlətmək |
| 100 | WebAsyncManagerIntegrationFilter | Async sorğular üçün SecurityContext |
| 200 | SecurityContextHolderFilter | SecurityContext-i yükləmək/saxlamaq |
| 300 | HeaderWriterFilter | Security header-ləri yazmaq (X-Frame-Options, vs) |
| 400 | CorsFilter | CORS sorğularını idarə etmək |
| 500 | CsrfFilter | CSRF token yoxlamaq |
| 600 | LogoutFilter | Logout sorğularını idarə etmək |
| 700 | UsernamePasswordAuthenticationFilter | Form login autentifikasiyası |
| 1000 | BasicAuthenticationFilter | HTTP Basic autentifikasiyası |
| 1300 | AnonymousAuthenticationFilter | Anonim istifadəçi yaratmaq |
| 1400 | SessionManagementFilter | Session idarəetməsi |
| 1500 | ExceptionTranslationFilter | Security exception-larını HTTP response-a çevirmək |
| 1600 | AuthorizationFilter | İcazə yoxlamaq |

```java
// Custom filter əlavə etmək:
@Component
public class JwtAuthenticationFilter extends OncePerRequestFilter {

    @Override
    protected void doFilterInternal(HttpServletRequest request,
                                    HttpServletResponse response,
                                    FilterChain filterChain) throws ServletException, IOException {
        // JWT token-i yoxla
        String token = extractToken(request);

        if (token != null && jwtService.isValid(token)) {
            // Authentication obyekti yarat və SecurityContext-ə qoy
            UsernamePasswordAuthenticationToken auth =
                new UsernamePasswordAuthenticationToken(
                    jwtService.getUsername(token),
                    null,
                    jwtService.getAuthorities(token)
                );
            SecurityContextHolder.getContext().setAuthentication(auth);
        }

        // Növbəti filterə ötür
        filterChain.doFilter(request, response);
    }

    private String extractToken(HttpServletRequest request) {
        String header = request.getHeader("Authorization");
        // "Bearer <token>" formatından token-i ayır
        if (header != null && header.startsWith("Bearer ")) {
            return header.substring(7);
        }
        return null;
    }
}
```

---

## SecurityContext və SecurityContextHolder

### SecurityContext

`SecurityContext` — cari autentifikasiya məlumatlarını saxlayan interfeysdır:

```java
public interface SecurityContext extends Serializable {
    Authentication getAuthentication();
    void setAuthentication(Authentication authentication);
}
```

### SecurityContextHolder

`SecurityContextHolder` — `SecurityContext`-i saxlayan statik sinifdir. Default olaraq **ThreadLocal** istifadə edir — yəni hər thread öz `SecurityContext`-ini saxlayır.

```java
// SecurityContext-ə müraciət:

// 1. Cari istifadəçini əldə etmək
Authentication authentication = SecurityContextHolder.getContext().getAuthentication();
String username = authentication.getName();

// 2. Cari istifadəçinin detallarını əldə etmək
Object principal = authentication.getPrincipal();
if (principal instanceof UserDetails userDetails) {
    String email = userDetails.getUsername();
    Collection<? extends GrantedAuthority> authorities = userDetails.getAuthorities();
}

// 3. Cari istifadəçinin rolunu yoxlamaq
boolean isAdmin = authentication.getAuthorities().stream()
    .anyMatch(a -> a.getAuthority().equals("ROLE_ADMIN"));

// 4. SecurityContext-i təmizləmək (logout zamanı)
SecurityContextHolder.clearContext();
```

### ThreadLocal Strategiyası

```java
// SecurityContextHolder 3 strategiya dəstəkləyir:

// 1. MODE_THREADLOCAL (default) - hər thread öz context-ini saxlayır
SecurityContextHolder.setStrategyName(SecurityContextHolder.MODE_THREADLOCAL);

// 2. MODE_INHERITABLETHREADLOCAL - uşaq thread-lər parent-in context-ini miras alır
SecurityContextHolder.setStrategyName(SecurityContextHolder.MODE_INHERITABLETHREADLOCAL);

// 3. MODE_GLOBAL - bütün thread-lər eyni context-i paylaşır (nadir hallarda)
SecurityContextHolder.setStrategyName(SecurityContextHolder.MODE_GLOBAL);
```

### YANLIŞ vs DOĞRU İstifadə

```java
// YANLIŞ: Yeni thread-də SecurityContext əlçatan deyil
@Service
public class ReportService {
    public void generateReport() {
        // Yeni thread - SecurityContext boş olacaq!
        new Thread(() -> {
            Authentication auth = SecurityContextHolder.getContext().getAuthentication();
            System.out.println(auth); // null olacaq!
        }).start();
    }
}

// DOGRU: DelegatingSecurityContextRunnable istifadə et
@Service
public class ReportService {
    public void generateReport() {
        // SecurityContext-i yeni thread-ə ötür
        SecurityContext context = SecurityContextHolder.getContext();
        Runnable task = new DelegatingSecurityContextRunnable(() -> {
            Authentication auth = SecurityContextHolder.getContext().getAuthentication();
            System.out.println(auth); // Düzgün işləyəcək
        }, context);
        new Thread(task).start();
    }
}
```

---

## Authentication Obyekti

`Authentication` — autentifikasiya məlumatlarını saxlayan interfeysdır:

```java
public interface Authentication extends Principal, Serializable {
    // İstifadəçinin rollları/icazələri
    Collection<? extends GrantedAuthority> getAuthorities();

    // Şifrə (autentifikasiyadan sonra null edilir - təhlükəsizlik üçün)
    Object getCredentials();

    // Əlavə məlumatlar (IP ünvanı, session, vs)
    Object getDetails();

    // İstifadəçi obyekti (UserDetails və ya username string)
    Object getPrincipal();

    // Autentifikasiya olunub-olmadığı
    boolean isAuthenticated();

    void setAuthenticated(boolean isAuthenticated) throws IllegalArgumentException;
}
```

### Authentication Növləri

```java
// 1. UsernamePasswordAuthenticationToken - ən çox istifadə edilən

// Autentifikasiyadan ƏVVƏL (credentials mövcuddur):
UsernamePasswordAuthenticationToken beforeAuth =
    new UsernamePasswordAuthenticationToken(
        "username",    // principal - istifadəçi adı
        "password"     // credentials - şifrə
        // authorities verilmir = isAuthenticated() false qaytarır
    );

// Autentifikasiyadan SONRA (authenticated = true):
UsernamePasswordAuthenticationToken afterAuth =
    new UsernamePasswordAuthenticationToken(
        userDetails,                    // principal - UserDetails obyekti
        null,                           // credentials - güvenlik üçün null
        userDetails.getAuthorities()    // authorities - ROLE_USER, ROLE_ADMIN, vs
    );

// 2. JwtAuthenticationToken - JWT üçün (Spring Security OAuth2 Resource Server)
JwtAuthenticationToken jwtAuth = new JwtAuthenticationToken(jwt, authorities);

// 3. AnonymousAuthenticationToken - anonim istifadəçilər üçün
List<GrantedAuthority> anonAuthorities = List.of(new SimpleGrantedAuthority("ROLE_ANONYMOUS"));
AnonymousAuthenticationToken anonymousAuth =
    new AnonymousAuthenticationToken("key", "anonymousUser", anonAuthorities);
```

---

## AuthenticationManager və ProviderManager

### AuthenticationManager

```java
// AuthenticationManager - autentifikasiyanın əsas giriş nöqtəsi
public interface AuthenticationManager {
    // Uğurlu isə - authenticated Authentication qaytarır
    // Uğursuz isə - AuthenticationException atır
    Authentication authenticate(Authentication authentication)
        throws AuthenticationException;
}
```

### ProviderManager

`ProviderManager` — `AuthenticationManager`-in standart implementasiyasıdır. Birdən çox `AuthenticationProvider` saxlayır:

```java
@Configuration
public class SecurityConfig {

    @Bean
    public AuthenticationManager authenticationManager(
            UserDetailsService userDetailsService,
            PasswordEncoder passwordEncoder) {

        // DaoAuthenticationProvider - veritabanından istifadəçi yoxlayan provider
        DaoAuthenticationProvider daoProvider = new DaoAuthenticationProvider();
        daoProvider.setUserDetailsService(userDetailsService);
        daoProvider.setPasswordEncoder(passwordEncoder);

        // ProviderManager hər provider-i sıra ilə yoxlayır
        // Biri uğurlu olarsa - nəticəni qaytarır
        // Hamısı uğursuz olarsa - ProviderNotFoundException atır
        return new ProviderManager(daoProvider);
    }
}
```

**ProviderManager-in iş prinsipi:**

```
ProviderManager.authenticate(authentication)
    ↓
  Provider 1 (DaoAuthenticationProvider)
    - Dəstəkləyirmi? (supports()) → Bəli
    - authenticate() çağır
    - Uğurlu? → Authentication qaytarır
    - Uğursuz? → AuthenticationException → növbəti provider
    ↓
  Provider 2 (LdapAuthenticationProvider)
    - Dəstəkləyirmi? → Bəli
    - authenticate() çağır
    ↓
  Bütün provider-lər uğursuz → ProviderNotFoundException atılır
```

---

## AuthenticationProvider Zənciri

### Custom AuthenticationProvider

```java
@Component
public class CustomAuthenticationProvider implements AuthenticationProvider {

    private final UserDetailsService userDetailsService;
    private final PasswordEncoder passwordEncoder;

    public CustomAuthenticationProvider(UserDetailsService userDetailsService,
                                        PasswordEncoder passwordEncoder) {
        this.userDetailsService = userDetailsService;
        this.passwordEncoder = passwordEncoder;
    }

    @Override
    public Authentication authenticate(Authentication authentication)
            throws AuthenticationException {

        String username = authentication.getName();
        String password = authentication.getCredentials().toString();

        // İstifadəçini veritabanından tap
        UserDetails userDetails;
        try {
            userDetails = userDetailsService.loadUserByUsername(username);
        } catch (UsernameNotFoundException e) {
            // Güvenlik üçün eyni xəta mesajı ver (username-in mövcudluğunu gizlət)
            throw new BadCredentialsException("Yanlış istifadəçi adı və ya şifrə");
        }

        // Şifrəni yoxla
        if (!passwordEncoder.matches(password, userDetails.getPassword())) {
            throw new BadCredentialsException("Yanlış istifadəçi adı və ya şifrə");
        }

        // Hesabın statusunu yoxla
        if (!userDetails.isEnabled()) {
            throw new DisabledException("Hesab deaktivdir");
        }

        if (!userDetails.isAccountNonLocked()) {
            throw new LockedException("Hesab bloklanıb");
        }

        // Uğurlu autentifikasiya - authenticated token qaytar
        return new UsernamePasswordAuthenticationToken(
            userDetails,
            null,                          // Şifrəni yaddaşdan sil
            userDetails.getAuthorities()   // İstifadəçinin icazələri
        );
    }

    @Override
    public boolean supports(Class<?> authentication) {
        // Bu provider yalnız UsernamePasswordAuthenticationToken üçün işləyir
        return UsernamePasswordAuthenticationToken.class.isAssignableFrom(authentication);
    }
}

// Konfiqurasiya:
@Configuration
@EnableWebSecurity
public class SecurityConfig {

    @Autowired
    private CustomAuthenticationProvider customAuthenticationProvider;

    @Bean
    public SecurityFilterChain filterChain(HttpSecurity http) throws Exception {
        // Custom provider-i əlavə et
        http.authenticationProvider(customAuthenticationProvider);
        http.authorizeHttpRequests(auth -> auth.anyRequest().authenticated());
        return http.build();
    }
}
```

### Tam İş Axını - Login

```java
@RestController
@RequestMapping("/api/auth")
public class AuthController {

    @Autowired
    private AuthenticationManager authenticationManager;

    @Autowired
    private JwtService jwtService;

    @PostMapping("/login")
    public ResponseEntity<LoginResponse> login(@RequestBody LoginRequest request) {
        // 1. Autentifikasiya etmək - ProviderManager çağırılır
        Authentication authentication = authenticationManager.authenticate(
            new UsernamePasswordAuthenticationToken(
                request.getUsername(),  // istifadəçi adı
                request.getPassword()   // şifrə
            )
        );

        // 2. Uğurlu isə - SecurityContext-ə qoy
        SecurityContextHolder.getContext().setAuthentication(authentication);

        // 3. JWT token yarat
        UserDetails userDetails = (UserDetails) authentication.getPrincipal();
        String token = jwtService.generateToken(userDetails);

        return ResponseEntity.ok(new LoginResponse(token));
    }
}
```

---

## İntervyu Sualları

**S: DelegatingFilterProxy ilə FilterChainProxy arasındakı fərq nədir?**

C: `DelegatingFilterProxy` Servlet Container tərəfindən tanınan Java EE Servlet Filter-dir. Onun vəzifəsi Servlet Container ilə Spring Application Context arasında körpü olmaq və sorğuları Spring Bean-i olan `FilterChainProxy`-ə ötürməkdir. `FilterChainProxy` isə Spring Bean-i kimi bütün Spring Security filterlərini idarə edir.

---

**S: SecurityContextHolder niyə ThreadLocal istifadə edir?**

C: HTTP Servlet tətbiqlərində hər sorğu ayrı bir thread-də icra olunur. ThreadLocal hər thread-ə öz müstəqil `SecurityContext`-ini saxlamağa imkan verir. Bu sayədə fərqli istifadəçilərin məlumatları bir-birinə qarışmır. Lakin async əməliyyatlarda bu kontekst uşaq thread-lərə ötürülmür, buna görə `DelegatingSecurityContextRunnable` istifadə etmək lazımdır.

---

**S: Authentication obyektindəki credentials niyə autentifikasiyadan sonra null edilir?**

C: Güvenlik məqsədilə. Şifrə artıq lazım olmadıqdan sonra onu yaddaşda saxlamaq risk yaradır. Şifrə yaddaşda dump alındığı halda açıq görünə bilər. Buna görə `DaoAuthenticationProvider` uğurlu autentifikasiyadan sonra credentials-i null edir.

---

**S: ProviderManager-in "parent" konsepti nədir?**

C: `ProviderManager`-in bir "parent" `AuthenticationManager`-i ola bilər. Əgər bütün provider-lər uğursuz olarsa, `ProviderManager` parent-inə müraciət edir. Bu xüsusiyyət birdən çox `SecurityFilterChain` olduğu hallarda faydalıdır — hər zəncirin öz `ProviderManager`-i var, lakin hamısı ortaq bir parent `AuthenticationManager` paylaşa bilər.

---

**S: `supports()` metodu nə üçün lazımdır?**

C: `AuthenticationProvider.supports(Class)` metodu, `ProviderManager`-ə bu provider-in müəyyən `Authentication` növünü işləyib-işləyə bilməyəcəyini bildirir. Bu sayədə `ProviderManager` hər provider-i gereksiz yerə çağırmır. Məsələn, `DaoAuthenticationProvider` yalnız `UsernamePasswordAuthenticationToken` üçün `true` qaytarır.

---

**S: Birdən çox SecurityFilterChain olduqda onların arasında seçim necə edilir?**

C: `FilterChainProxy` gələn hər sorğu üçün bütün `SecurityFilterChain`-ləri `@Order`-a görə sıra ilə yoxlayır. `securityMatcher()` ilə müəyyən edilmiş URL pattern-ə ilk uyğun gələn zəncir seçilir və icra edilir. Digər zəncirlər işləmir. Əgər heç bir zəncir uyğun gəlməsə, sorğu uğursuz olur.
