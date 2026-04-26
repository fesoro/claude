# 30 — Spring MVC Filters və Interceptors — Geniş İzah

> **Seviyye:** Senior ⭐⭐⭐


## Mündəricat
1. [Filter nədir?](#filter-nədir)
2. [OncePerRequestFilter](#oncepperrequestfilter)
3. [HandlerInterceptor](#handlerinterceptor)
4. [Filter vs Interceptor fərqləri](#filter-vs-interceptor-fərqləri)
5. [Filter qeydiyyatı](#filter-qeydiyyatı)
6. [Praktik nümunələr](#praktik-nümunələr)
7. [İntervyu Sualları](#intervyu-sualları)

---

## Filter nədir?

**Servlet Filter** — HTTP request/response pipeline-ın ən aşağı səviyyəsindədir. Spring-dən əvvəl, Servlet container (Tomcat) səviyyəsindədir.

```java
// Sadə Filter
@Component
public class RequestLoggingFilter implements Filter {

    private static final Logger log = LoggerFactory.getLogger(RequestLoggingFilter.class);

    @Override
    public void doFilter(ServletRequest request,
                         ServletResponse response,
                         FilterChain chain) throws IOException, ServletException {

        HttpServletRequest httpRequest = (HttpServletRequest) request;
        HttpServletResponse httpResponse = (HttpServletResponse) response;

        String method = httpRequest.getMethod();
        String uri = httpRequest.getRequestURI();
        long startTime = System.currentTimeMillis();

        log.info("Gələn sorğu: {} {}", method, uri);

        // Növbəti filter-ə və ya controller-ə ötür
        chain.doFilter(request, response);

        // Response sonrası
        long duration = System.currentTimeMillis() - startTime;
        int status = httpResponse.getStatus();
        log.info("Cavab: {} {} → {} ({} ms)", method, uri, status, duration);
    }

    @Override
    public void init(FilterConfig filterConfig) {
        // Filter başlanğıcı (optional)
    }

    @Override
    public void destroy() {
        // Filter məhv edilməsi (optional)
    }
}
```

---

## OncePerRequestFilter

Bir request üçün yalnız bir dəfə işləməni təmin edən abstract sinif (redirect/forward hallarında da bir dəfə):

```java
@Component
public class JwtAuthenticationFilter extends OncePerRequestFilter {

    private final JwtTokenProvider jwtTokenProvider;
    private final UserDetailsService userDetailsService;

    public JwtAuthenticationFilter(JwtTokenProvider jwtTokenProvider,
                                   UserDetailsService userDetailsService) {
        this.jwtTokenProvider = jwtTokenProvider;
        this.userDetailsService = userDetailsService;
    }

    @Override
    protected void doFilterInternal(HttpServletRequest request,
                                    HttpServletResponse response,
                                    FilterChain filterChain)
            throws ServletException, IOException {

        // Authorization header-i al
        String authHeader = request.getHeader("Authorization");

        if (authHeader == null || !authHeader.startsWith("Bearer ")) {
            filterChain.doFilter(request, response); // Token yoxdur, davam et
            return;
        }

        String token = authHeader.substring(7); // "Bearer " sonrası

        try {
            // Token-i yoxla
            if (jwtTokenProvider.validateToken(token)) {
                String username = jwtTokenProvider.getUsernameFromToken(token);

                UserDetails userDetails = userDetailsService.loadUserByUsername(username);

                UsernamePasswordAuthenticationToken authentication =
                    new UsernamePasswordAuthenticationToken(
                        userDetails, null, userDetails.getAuthorities());

                authentication.setDetails(
                    new WebAuthenticationDetailsSource().buildDetails(request));

                // SecurityContext-ə əlavə et
                SecurityContextHolder.getContext().setAuthentication(authentication);
            }
        } catch (JwtException e) {
            log.error("Etibarsız JWT token: {}", e.getMessage());
            response.setStatus(HttpServletResponse.SC_UNAUTHORIZED);
            return;
        }

        filterChain.doFilter(request, response);
    }

    // Müəyyən path-ları keç
    @Override
    protected boolean shouldNotFilter(HttpServletRequest request) {
        String path = request.getServletPath();
        return path.startsWith("/auth/") || path.startsWith("/public/");
    }
}
```

---

## HandlerInterceptor

**Spring MVC səviyyəsindədir** — DispatcherServlet-dən sonra, Controller-dən əvvəl/sonra işləyir.

```java
@Component
public class AuditInterceptor implements HandlerInterceptor {

    private final AuditService auditService;

    public AuditInterceptor(AuditService auditService) {
        this.auditService = auditService;
    }

    // Controller-dən ƏVVƏL
    // false qaytarsa — controller çağırılmır
    @Override
    public boolean preHandle(HttpServletRequest request,
                             HttpServletResponse response,
                             Object handler) throws Exception {

        if (handler instanceof HandlerMethod handlerMethod) {
            String controllerName = handlerMethod.getBeanType().getSimpleName();
            String methodName = handlerMethod.getMethod().getName();
            log.info("Controller çağırılır: {}.{}", controllerName, methodName);

            // Rate limit yoxlaması
            String clientIp = request.getRemoteAddr();
            if (rateLimitService.isExceeded(clientIp)) {
                response.setStatus(HttpStatus.TOO_MANY_REQUESTS.value());
                response.getWriter().write("Rate limit aşıldı");
                return false; // Controller çağırılmır
            }
        }

        // Başlama vaxtını saxla
        request.setAttribute("startTime", System.currentTimeMillis());
        return true; // Davam et
    }

    // Controller-dən SONRA (exception olmadıqda)
    @Override
    public void postHandle(HttpServletRequest request,
                           HttpServletResponse response,
                           Object handler,
                           ModelAndView modelAndView) throws Exception {

        // ModelAndView-a məlumat əlavə etmək
        if (modelAndView != null) {
            modelAndView.addObject("serverTime", LocalDateTime.now());
        }
    }

    // View render olduqdan SONRA (həmişə)
    @Override
    public void afterCompletion(HttpServletRequest request,
                                HttpServletResponse response,
                                Object handler,
                                Exception ex) throws Exception {

        long startTime = (Long) request.getAttribute("startTime");
        long duration = System.currentTimeMillis() - startTime;

        // Audit log
        auditService.log(
            request.getMethod(),
            request.getRequestURI(),
            response.getStatus(),
            duration
        );

        if (ex != null) {
            log.error("Request exception ilə bitdi: {}", ex.getMessage());
        }
    }
}
```

**Interceptor-u qeydiyyatdan keçirmək:**

```java
@Configuration
public class WebMvcConfig implements WebMvcConfigurer {

    private final AuditInterceptor auditInterceptor;
    private final RateLimitInterceptor rateLimitInterceptor;

    public WebMvcConfig(AuditInterceptor auditInterceptor,
                        RateLimitInterceptor rateLimitInterceptor) {
        this.auditInterceptor = auditInterceptor;
        this.rateLimitInterceptor = rateLimitInterceptor;
    }

    @Override
    public void addInterceptors(InterceptorRegistry registry) {
        // Bütün path-lara
        registry.addInterceptor(auditInterceptor)
                .addPathPatterns("/**");

        // Yalnız API path-larına
        registry.addInterceptor(rateLimitInterceptor)
                .addPathPatterns("/api/**")
                .excludePathPatterns("/api/public/**", "/api/health");
    }
}
```

---

## Filter vs Interceptor fərqləri

| Xüsusiyyət | Filter (Servlet) | HandlerInterceptor (Spring) |
|------------|-----------------|---------------------------|
| **Səviyyə** | Servlet container | Spring MVC (DispatcherServlet) |
| **Standart** | Java EE / Jakarta EE | Spring-ə xasdır |
| **Spring bean-larına çıxış** | Limitli (servlet-dən əvvəl) | Tam çıxış |
| **Controller məlumatı** | Yoxdur | HandlerMethod-a çıxış var |
| **Tətbiq olunan sorğular** | Bütün HTTP sorğular (static file-lar da) | Yalnız DispatcherServlet-dən keçən |
| **İstifadə halları** | JWT auth, CORS, request logging | Audit, i18n, model manipulation |
| **Async dəstəyi** | Tam | Async dispatcher lazım ola bilər |

```
HTTP Request
    ↓
[Filter 1] → [Filter 2] → [Filter N]    ← Servlet container səviyyəsi
    ↓
DispatcherServlet
    ↓
[HandlerInterceptor.preHandle()]         ← Spring MVC səviyyəsi
    ↓
Controller Method
    ↓
[HandlerInterceptor.postHandle()]
    ↓
View Rendering
    ↓
[HandlerInterceptor.afterCompletion()]
```

---

## Filter qeydiyyatı

```java
// 1. @Component ilə (bütün sorğulara tətbiq olunur)
@Component
@Order(1) // Sıra
public class FirstFilter extends OncePerRequestFilter {
    @Override
    protected void doFilterInternal(HttpServletRequest req,
                                    HttpServletResponse res,
                                    FilterChain chain)
            throws ServletException, IOException {
        chain.doFilter(req, res);
    }
}

// 2. FilterRegistrationBean ilə (daha çox nəzarət)
@Configuration
public class FilterConfig {

    @Bean
    public FilterRegistrationBean<RequestLoggingFilter> loggingFilter() {
        FilterRegistrationBean<RequestLoggingFilter> bean =
            new FilterRegistrationBean<>();

        bean.setFilter(new RequestLoggingFilter());
        bean.addUrlPatterns("/api/*"); // Yalnız bu path-lara
        bean.setOrder(Ordered.HIGHEST_PRECEDENCE); // Sıra
        bean.setName("requestLoggingFilter");
        return bean;
    }

    @Bean
    public FilterRegistrationBean<SecurityFilter> securityFilter() {
        FilterRegistrationBean<SecurityFilter> bean =
            new FilterRegistrationBean<>();

        bean.setFilter(new SecurityFilter());
        bean.addUrlPatterns("/*");
        bean.setOrder(1);
        return bean;
    }
}

// 3. @WebFilter annotasiyası ilə
@WebFilter(urlPatterns = "/api/*")
public class ApiFilter implements Filter {
    // @ServletComponentScan lazımdır
}
```

---

## Praktik nümunələr

### CORS Filter

```java
@Component
@Order(Ordered.HIGHEST_PRECEDENCE)
public class CorsFilter extends OncePerRequestFilter {

    @Override
    protected void doFilterInternal(HttpServletRequest request,
                                    HttpServletResponse response,
                                    FilterChain chain)
            throws ServletException, IOException {

        response.setHeader("Access-Control-Allow-Origin", "*");
        response.setHeader("Access-Control-Allow-Methods",
            "GET, POST, PUT, DELETE, OPTIONS, PATCH");
        response.setHeader("Access-Control-Allow-Headers",
            "Authorization, Content-Type, X-Requested-With");
        response.setHeader("Access-Control-Max-Age", "3600");

        // OPTIONS (preflight) sorğusunu bitir
        if ("OPTIONS".equalsIgnoreCase(request.getMethod())) {
            response.setStatus(HttpServletResponse.SC_OK);
            return;
        }

        chain.doFilter(request, response);
    }
}
```

### i18n Interceptor

```java
@Component
public class LocaleInterceptor implements HandlerInterceptor {

    @Override
    public boolean preHandle(HttpServletRequest request,
                             HttpServletResponse response,
                             Object handler) {

        String lang = request.getHeader("Accept-Language");
        if (lang != null) {
            Locale locale = Locale.forLanguageTag(lang);
            LocaleContextHolder.setLocale(locale);
        }
        return true;
    }

    @Override
    public void afterCompletion(HttpServletRequest request,
                               HttpServletResponse response,
                               Object handler, Exception ex) {
        LocaleContextHolder.resetLocaleContext(); // Təmizlə
    }
}
```

---

## İntervyu Sualları

### 1. Filter ilə HandlerInterceptor arasındakı əsas fərq nədir?
**Cavab:** Filter Servlet container səviyyəsindədir (DispatcherServlet-dən əvvəl), bütün HTTP sorğulara tətbiq olunur. HandlerInterceptor Spring MVC səviyyəsindədir, yalnız DispatcherServlet-dən keçən sorğulara tətbiq olunur və Controller/Handler məlumatına çıxışı var.

### 2. OncePerRequestFilter niyə istifadə edilir?
**Cavab:** Normal Filter bir request üçün forward/redirect hallarında bir neçə dəfə çağırıla bilər. OncePerRequestFilter bir request üçün yalnız bir dəfə işləməni təmin edir.

### 3. preHandle() false qaytarsa nə baş verir?
**Cavab:** Controller metodu çağırılmır. Response birbaşa göndərilir. Sonrakı interceptor-ların preHandle-ı da çağırılmır.

### 4. JWT authentication üçün Filter mi, Interceptor mu daha uyğundur?
**Cavab:** Filter daha uyğundur. JWT yoxlaması DispatcherServlet-dən əvvəl edilməlidir ki, Spring Security-nin filter chain-i düzgün işləsin. OncePerRequestFilter-dan extend etmək lazımdır.

### 5. Bir neçə Filter-in sırası necə müəyyən edilir?
**Cavab:** @Order annotasiyası, FilterRegistrationBean.setOrder(), və ya Ordered interface-i ilə. Kiçik rəqəm = daha əvvəl işləyir. Ordered.HIGHEST_PRECEDENCE = ən əvvəl.

*Son yenilənmə: 2026-04-10*
