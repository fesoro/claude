# 58 — Spring Security CORS — Geniş İzah

> **Seviyye:** Middle ⭐⭐


## Mündəricat
1. [CORS nədir?](#cors-nədir)
2. [Spring-də CORS konfiqurasiyası](#spring-də-cors-konfiqurasiyası)
3. [@CrossOrigin annotasiyası](#crossorigin-annotasiyası)
4. [WebMvcConfigurer ilə global CORS](#webmvcconfigurer-ilə-global-cors)
5. [Spring Security ilə CORS](#spring-security-ilə-cors)
6. [İntervyu Sualları](#intervyu-sualları)

---

## CORS nədir?

**CORS (Cross-Origin Resource Sharing)** — browserın başqa domain/port/protocol-dan gələn API sorğularını bloklaması. Təhlükəsizlik mexanizmidir.

```
Origin: https://frontend.example.com

Browser: GET https://api.example.com/users
         Origin: https://frontend.example.com

Server cavabında lazımdır:
  Access-Control-Allow-Origin: https://frontend.example.com
  (yoxdursa → browser bloklar)
```

**Preflight (OPTIONS) sorğu:**
```
Browser → OPTIONS /api/users
          Origin: https://frontend.example.com
          Access-Control-Request-Method: POST
          Access-Control-Request-Headers: Authorization, Content-Type

Server → Access-Control-Allow-Origin: https://frontend.example.com
         Access-Control-Allow-Methods: GET, POST, PUT, DELETE
         Access-Control-Allow-Headers: Authorization, Content-Type
         Access-Control-Max-Age: 3600
```

---

## @CrossOrigin annotasiyası

```java
// Bir controller-ə tətbiq etmək
@RestController
@RequestMapping("/api/users")
@CrossOrigin(origins = "https://frontend.example.com")
public class UserController {

    @GetMapping
    public List<User> getUsers() {
        return userService.findAll();
    }
}

// Metod səviyyəsində
@RestController
public class ProductController {

    // Bütün origin-lərə icazə (development üçün)
    @CrossOrigin(origins = "*")
    @GetMapping("/api/public/products")
    public List<Product> getPublicProducts() {
        return productService.findPublic();
    }

    // Detallı konfiqurasiya
    @CrossOrigin(
        origins = {"https://app.example.com", "https://admin.example.com"},
        methods = {RequestMethod.GET, RequestMethod.POST},
        allowedHeaders = {"Authorization", "Content-Type"},
        exposedHeaders = {"X-Total-Count"},
        maxAge = 3600,
        allowCredentials = "true"
    )
    @GetMapping("/api/products")
    public ResponseEntity<List<Product>> getProducts() {
        List<Product> products = productService.findAll();
        return ResponseEntity.ok()
            .header("X-Total-Count", String.valueOf(products.size()))
            .body(products);
    }
}
```

---

## WebMvcConfigurer ilə global CORS

```java
@Configuration
public class CorsConfig implements WebMvcConfigurer {

    @Value("${app.cors.allowed-origins}")
    private List<String> allowedOrigins;

    @Override
    public void addCorsMappings(CorsRegistry registry) {
        registry.addMapping("/api/**")
            .allowedOrigins(allowedOrigins.toArray(String[]::new))
            .allowedMethods("GET", "POST", "PUT", "DELETE", "PATCH", "OPTIONS")
            .allowedHeaders("*")
            .exposedHeaders("Authorization", "X-Total-Count")
            .allowCredentials(true)
            .maxAge(3600); // Preflight cache müddəti (saniyə)

        // Public endpoint-lər üçün ayrı qayda
        registry.addMapping("/api/public/**")
            .allowedOrigins("*")
            .allowedMethods("GET")
            .maxAge(86400);
    }
}

# application.yml
app:
  cors:
    allowed-origins:
      - https://app.example.com
      - https://admin.example.com
      - http://localhost:3000  # Development
```

---

## Spring Security ilə CORS

Spring Security varsa, CORS konfiqurasiyası Security filter chain-də edilməlidir:

```java
@Configuration
@EnableWebSecurity
public class SecurityConfig {

    @Bean
    public SecurityFilterChain securityFilterChain(HttpSecurity http) throws Exception {
        http
            .cors(cors -> cors.configurationSource(corsConfigurationSource()))
            // YANLIŞ: .cors(cors -> cors.disable()) — bunu etmə!
            .csrf(csrf -> csrf.disable())
            .authorizeHttpRequests(auth -> auth
                .requestMatchers("/api/public/**").permitAll()
                .anyRequest().authenticated()
            );

        return http.build();
    }

    @Bean
    public CorsConfigurationSource corsConfigurationSource() {
        CorsConfiguration configuration = new CorsConfiguration();

        // Allowed origins
        configuration.setAllowedOrigins(List.of(
            "https://app.example.com",
            "https://admin.example.com",
            "http://localhost:3000"
        ));

        // Allowed methods
        configuration.setAllowedMethods(List.of(
            "GET", "POST", "PUT", "DELETE", "PATCH", "OPTIONS"
        ));

        // Allowed headers
        configuration.setAllowedHeaders(List.of(
            "Authorization",
            "Content-Type",
            "X-Requested-With",
            "Accept",
            "Origin"
        ));

        // Exposed headers (frontend-in görə biləcəyi)
        configuration.setExposedHeaders(List.of(
            "Authorization",
            "X-Total-Count",
            "X-Page-Number"
        ));

        // Credentials (cookie, Authorization header)
        configuration.setAllowCredentials(true);

        // Preflight cache müddəti
        configuration.setMaxAge(3600L);

        UrlBasedCorsConfigurationSource source = new UrlBasedCorsConfigurationSource();
        source.registerCorsConfiguration("/**", configuration);
        return source;
    }
}
```

**Pattern-based origin validation:**
```java
@Bean
public CorsConfigurationSource corsConfigurationSource() {
    CorsConfiguration configuration = new CorsConfiguration();

    // Pattern — subdomain-lərə icazə
    configuration.setAllowedOriginPatterns(List.of(
        "https://*.example.com",
        "http://localhost:[*]" // Hər hansı localhost port
    ));

    configuration.setAllowedMethods(List.of("*"));
    configuration.setAllowedHeaders(List.of("*"));
    configuration.setAllowCredentials(true);

    UrlBasedCorsConfigurationSource source = new UrlBasedCorsConfigurationSource();
    source.registerCorsConfiguration("/**", configuration);
    return source;
}
```

**Environment-ə görə CORS:**
```java
@Configuration
public class CorsConfig {

    @Value("${spring.profiles.active:default}")
    private String activeProfile;

    @Bean
    public CorsConfigurationSource corsConfigurationSource() {
        CorsConfiguration config = new CorsConfiguration();

        if ("development".equals(activeProfile) || "local".equals(activeProfile)) {
            // Development: bütün origin-lərə icazə
            config.setAllowedOriginPatterns(List.of("*"));
            config.setAllowCredentials(false);
        } else {
            // Production: yalnız müəyyən origin-lərə
            config.setAllowedOrigins(List.of(
                "https://app.example.com",
                "https://admin.example.com"
            ));
            config.setAllowCredentials(true);
        }

        config.setAllowedMethods(List.of("GET", "POST", "PUT", "DELETE", "OPTIONS"));
        config.setAllowedHeaders(List.of("Authorization", "Content-Type"));
        config.setMaxAge(3600L);

        UrlBasedCorsConfigurationSource source = new UrlBasedCorsConfigurationSource();
        source.registerCorsConfiguration("/**", config);
        return source;
    }
}
```

---

## İntervyu Sualları

### 1. CORS server-tərəfli yoxsa client-tərəfli mexanizmdir?
**Cavab:** Hər ikisidir. Browser preflight sorğu göndərir (client-tərəf), server CORS header-ları ilə cavab verir (server-tərəf). Server həm header-ları göndərməlidir, həm də browser bu header-lara əsasən qərar verir. Postman CORS yoxlamır — yalnız browser-lər yoxlayır.

### 2. Niyə Spring Security varsa ayrıca CORS konfiqurasiyası lazımdır?
**Cavab:** Spring Security bütün sorğuları, hətta OPTIONS (preflight) sorğularını da bloklay bilər. `http.cors()` konfiqurasiyası CORS filter-ini Spring Security filter chain-ə düzgün inteqrasiya edir. Əgər `WebMvcConfigurer.addCorsMappings` istifadə edilsə, Security filter-dən əvvəl işləmə zəmanəti olmur.

### 3. allowedOrigins("*") + allowCredentials(true) niyə işləmir?
**Cavab:** Browser spesifikasiyasına görə `Access-Control-Allow-Origin: *` ilə `Access-Control-Allow-Credentials: true` birlikdə işləmir. Credentials (cookie, Authorization header) göndərmək üçün konkret origin göstərilməlidir. `allowedOriginPatterns("*")` + `allowCredentials(true)` isə işləyir — Spring wildcard-ı konkret origin ilə əvəz edir.

### 4. Preflight sorğu nə zaman göndərilir?
**Cavab:** "Simple request" olmayan hallarda: (1) GET/POST/HEAD xaricindəki metodlar; (2) `Content-Type` `application/json` olduqda; (3) Authorization kimi custom header-lər olduqda. Browser əvvəlcə OPTIONS sorğusu göndərir, server "ok" cavab verərsə əsl sorğu göndərilir.

### 5. maxAge nə üçündür?
**Cavab:** Browser-in preflight cavabını cache-lədiyi müddət (saniyə). Bu müddət ərzində eyni endpoint üçün yenidən preflight göndərilmir. Varsayılan 0 — hər sorğuda preflight. 3600 (1 saat) tövsiyə olunur — daha az OPTIONS sorğusu = daha yaxşı performans.

*Son yenilənmə: 2026-04-10*
