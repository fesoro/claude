# 59 — Spring Security CSRF — Geniş İzah

> **Seviyye:** Middle ⭐⭐


## Mündəricat
1. [CSRF nədir?](#csrf-nədir)
2. [CSRF token mexanizmi](#csrf-token-mexanizmi)
3. [REST API-də CSRF](#rest-apidə-csrf)
4. [CSRF konfiqurasiyası](#csrf-konfiqurasiyası)
5. [SameSite cookie](#samesite-cookie)
6. [İntervyu Sualları](#intervyu-sualları)

---

## CSRF nədir?

**CSRF (Cross-Site Request Forgery)** — istifadəçinin autentifikasiya olunmuş session-undan istifadə edərək, istifadəçinin bilmədiyi əməliyyatlar etdirmək hücumu.

```
1. İstifadəçi bank.com-a login olur (session cookie-si yaranır)
2. İstifadəçi evil.com-a keçir
3. evil.com içərisindəki gizli form POST bank.com/transfer edir
4. Browser avtomatik bank.com cookie-sini əlavə edir
5. Bank server session-u görür, sorğunu qəbul edir → Pul köçürüldü!
```

**Niyə baş verir:** Browser eyni domain-ə avtomatik cookie göndərir.

---

## CSRF token mexanizmi

```
Server → Login zamanı CSRF token yaradır
Server → Token-i cookie-yə (HttpOnly=false) ya da response-a əlavə edir
Frontend → Hər state-changing sorğuda bu token-i header-ə əlavə edir
Server → Header-dəki token ilə cookie-dəki token-i müqayisə edir
evil.com → Cookie-ni göndərə bilər, amma başqa domain-dəki token-i oxuya bilmir → Bloklayır!
```

```java
// Thymeleaf ilə form-da CSRF (avtomatik)
/*
<form method="post" th:action="@{/transfer}">
    <input type="hidden" th:name="${_csrf.parameterName}" th:value="${_csrf.token}"/>
    ...
</form>
*/

// JavaScript (Angular, React) ilə:
// Cookie-dən token-i oxu
const token = document.cookie
    .split('; ')
    .find(row => row.startsWith('XSRF-TOKEN='))
    ?.split('=')[1];

// Header-ə əlavə et
fetch('/api/transfer', {
    method: 'POST',
    headers: {
        'X-XSRF-TOKEN': token,
        'Content-Type': 'application/json'
    },
    body: JSON.stringify({amount: 100})
});
```

---

## REST API-də CSRF

```java
// REST API + JWT istifadə edildikdə:
// Cookie-based session YOX, JWT token istifadə olunur
// CSRF hücumu üçün cookie lazımdır → CSRF riski aşağıdır
// Buna görə REST API-lərdə CSRF söndürülür

@Configuration
@EnableWebSecurity
public class RestApiSecurityConfig {

    @Bean
    public SecurityFilterChain securityFilterChain(HttpSecurity http) throws Exception {
        http
            .csrf(csrf -> csrf.disable()) // REST + JWT üçün
            .sessionManagement(session ->
                session.sessionCreationPolicy(SessionCreationPolicy.STATELESS))
            .authorizeHttpRequests(auth -> auth
                .anyRequest().authenticated()
            )
            .oauth2ResourceServer(oauth2 ->
                oauth2.jwt(Customizer.withDefaults())
            );

        return http.build();
    }
}
```

---

## CSRF konfiqurasiyası

**Traditional web app (cookie-based session):**
```java
@Configuration
@EnableWebSecurity
public class WebAppSecurityConfig {

    @Bean
    public SecurityFilterChain securityFilterChain(HttpSecurity http) throws Exception {
        http
            // Default CSRF aktiv — formlarda çalışır
            .csrf(csrf -> csrf
                // Yalnız müəyyən endpoint-ləri exempt et
                .ignoringRequestMatchers("/api/webhook/**")
                .csrfTokenRepository(CookieCsrfTokenRepository.withHttpOnlyFalse())
            )
            .authorizeHttpRequests(auth -> auth
                .anyRequest().authenticated()
            )
            .formLogin(Customizer.withDefaults());

        return http.build();
    }
}
```

**SPAs (React, Angular) ilə CSRF:**
```java
@Bean
public SecurityFilterChain securityFilterChain(HttpSecurity http) throws Exception {
    http
        .csrf(csrf -> csrf
            // Token cookie-yə yazılır (JavaScript oxuya bilər)
            .csrfTokenRepository(CookieCsrfTokenRepository.withHttpOnlyFalse())
            // Token header-dən qəbul edilir
            .csrfTokenRequestHandler(new XorCsrfTokenRequestAttributeHandler())
        )
        .authorizeHttpRequests(auth -> auth
            .requestMatchers("/api/public/**").permitAll()
            .anyRequest().authenticated()
        );

    return http.build();
}
```

**Custom CSRF token repository:**
```java
@Component
public class CustomCsrfTokenRepository implements CsrfTokenRepository {

    private static final String CSRF_HEADER = "X-CSRF-TOKEN";
    private static final String CSRF_COOKIE = "CSRF-TOKEN";

    @Override
    public CsrfToken generateToken(HttpServletRequest request) {
        String tokenValue = UUID.randomUUID().toString();
        return new DefaultCsrfToken(CSRF_HEADER, "_csrf", tokenValue);
    }

    @Override
    public void saveToken(CsrfToken token, HttpServletRequest request,
                          HttpServletResponse response) {
        if (token == null) {
            // Token-i sil
            Cookie cookie = new Cookie(CSRF_COOKIE, "");
            cookie.setMaxAge(0);
            response.addCookie(cookie);
        } else {
            Cookie cookie = new Cookie(CSRF_COOKIE, token.getToken());
            cookie.setHttpOnly(false); // JavaScript oxumalıdır
            cookie.setSecure(true); // Yalnız HTTPS
            cookie.setPath("/");
            cookie.setMaxAge(3600);
            response.addCookie(cookie);
        }
    }

    @Override
    public CsrfToken loadToken(HttpServletRequest request) {
        Cookie[] cookies = request.getCookies();
        if (cookies != null) {
            for (Cookie cookie : cookies) {
                if (CSRF_COOKIE.equals(cookie.getName())) {
                    return new DefaultCsrfToken(CSRF_HEADER, "_csrf", cookie.getValue());
                }
            }
        }
        return null;
    }
}
```

---

## SameSite cookie

CSRF-ə qarşı müasir həll:

```java
// SameSite=Strict — yalnız eyni site-dan gələn sorğularda cookie göndər
// SameSite=Lax — GET sorğularında üçüncü tərəf gəlişə icazə
// SameSite=None — bütün sorğularda göndər (Secure lazımdır)

@Configuration
public class CookieConfig {

    @Bean
    public TomcatContextCustomizer sameSiteCookieCustomizer() {
        return context -> {
            Rfc6265CookieProcessor cookieProcessor = new Rfc6265CookieProcessor();
            cookieProcessor.setSameSiteCookies(SameSiteCookies.STRICT.getValue());
            context.setCookieProcessor(cookieProcessor);
        };
    }
}

// application.yml ilə
server:
  servlet:
    session:
      cookie:
        same-site: strict
        secure: true
        http-only: true
```

**CSRF qorunma strategiyaları:**
```
1. CSRF Token — state-changing sorğularda token yoxlanır (klassik)
2. SameSite Cookie — üçüncü tərəf site-lardan cookie göndərilmər
3. Custom header yoxlaması — API key, Origin header yoxlanması
4. Double Submit Cookie — Cookie + Request parametri müqayisəsi
```

---

## İntervyu Sualları

### 1. CSRF hücumu necə işləyir?
**Cavab:** İstifadəçi hədəf saytda autentifikasiya olub. Zərərli sayt gizli form/sorğu vasitəsilə hədəf sayta sorğu göndərir. Browser avtomatik cookie-ni əlavə edir. Server cookie-ni görür və sorğunu qanuni hesab edir. Həll: CSRF token — zərərli sayt token-i oxuya bilməz (SOP maneəsi).

### 2. REST API-lərdə niyə CSRF söndürülür?
**Cavab:** CSRF hücumu yalnız cookie-based authentication olduqda işləyir. REST API-lər adətən JWT token istifadə edir (Authorization header). Browser zərərli cross-site sorğulara custom header əlavə etmir — SOP bunu maneə törədir. StateLESS + JWT = CSRF riski minimal.

### 3. CookieCsrfTokenRepository.withHttpOnlyFalse() nədir?
**Cavab:** CSRF token-i HttpOnly=false olan cookie-yə yazır. Bu, JavaScript-in cookie-ni oxuya bilməsi üçün lazımdır. Angular kimi framework-lər `XSRF-TOKEN` cookie-ni oxuyub `X-XSRF-TOKEN` header-inə əlavə edir. HttpOnly=true olsaydı JavaScript oxuya bilməzdi.

### 4. SameSite=Strict vs SameSite=Lax fərqi?
**Cavab:** `Strict` — yalnız eyni site-dan gələn sorğularda cookie göndərilir. Başqa saytdan link klikləndikcə belə cookie göndərilmir. `Lax` (browser default) — üst-level navigation (link klik) üçün GET sorğularında cookie göndərilir, amma POST kimi digər metodlarda göndərilmir. `Lax` daha az qorunma, amma daha yaxşı UX.

### 5. CSRF token state-changing olmayan GET sorğulara niyə tətbiq edilmir?
**Cavab:** GET sorğuları data dəyişdirməməlidir (idempotent). CSRF hücumunun məqsədi server-da dəyişiklik etməkdir. GET sorğularında token tələb etmək həm UX-i pisləşdirir, həm də URL-lərdə token sızdırma riski yaradır.

*Son yenilənmə: 2026-04-10*
