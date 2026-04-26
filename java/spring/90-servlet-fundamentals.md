# 90 — Servlet Fundamentals — Spring-in Əsası

> **Seviyye:** Middle ⭐⭐

## Mündəricat
1. [Servlet nədir?](#servlet-nədir)
2. [Servlet Lifecycle](#servlet-lifecycle)
3. [DispatcherServlet — Spring-in ürəyi](#dispatcherservlet--spring-in-ürəyi)
4. [HttpServletRequest / HttpServletResponse](#httpservletrequest--httpservletresponse)
5. [Filter vs HandlerInterceptor](#filter-vs-handlerinterceptor)
6. [Laravel ilə müqayisə](#laravel-ilə-müqayisə)
7. [İntervyu Sualları](#intervyu-sualları)

---

## Servlet nədir?

**Servlet** — Java-da HTTP sorğularını idarə edən class. Spring MVC-nin altında Servlet API dayanır — siz birbaşa yazmasanız da, Spring hər şeyi Servlet üzərindən edir.

```
Client → HTTP Request
         ↓
    [Servlet Container]   ← Tomcat, Jetty, Undertow
    (sorğunu Java objectə çevirir)
         ↓
    [DispatcherServlet]   ← Spring-in əsas Servlet-i
         ↓
    [Controller Method]
```

**Laravel ekvivalenti:**
```
PHP-da Servlet yoxdur — PHP hər request üçün yenidən başlayır.
Java-da Servlet container daima işləyir, thread pool-dan thread alır.

Laravel:          index.php → kernel → router → controller
Spring:           Tomcat → DispatcherServlet → handler → controller
```

---

## Servlet Lifecycle

```java
// Servlet lifecycle — Spring-in arxasında baş verir:
// 1. init() — container başlayanda bir dəfə
// 2. service() — hər request üçün
//    → doGet(), doPost(), doPut(), doDelete()
// 3. destroy() — container dayananda bir dəfə

// Siz bunu birbaşa yazmırsınız, amma bilmək lazımdır:
public class MyServlet extends HttpServlet {
    @Override
    public void init() {
        // Tomcat başlayanda bir dəfə çağrılır
        System.out.println("Servlet initialized");
    }

    @Override
    protected void doGet(HttpServletRequest req, HttpServletResponse resp)
            throws ServletException, IOException {
        resp.setContentType("text/html");
        resp.getWriter().write("<h1>Hello</h1>");
    }

    @Override
    public void destroy() {
        // Tomcat dayananda bir dəfə çağrılır
        System.out.println("Servlet destroyed");
    }
}
```

**Spring-də siz bunu görmürsünüz** — `@RestController` hər şeyi gizlədir. Amma spring-in içi belə işləyir.

---

## DispatcherServlet — Spring-in ürəyi

`DispatcherServlet` Spring MVC-nin mərkəzidir. **Tək bir Servlet** bütün HTTP sorğularını qəbul edir, sonra doğru Controller-ə yönləndirir.

```
Request: GET /users/42

DispatcherServlet:
  1. HandlerMapping-ə soruşur: bu URL hansı method üçündür?
     → @GetMapping("/users/{id}") → UserController#getUser()

  2. HandlerAdapter-ə verir: method-u çağır
     → UserController#getUser(42)

  3. MessageConverter: return dəyərini JSON-a çevir
     → {"id": 42, "name": "Ali"}

  4. Response-u göndər
```

```java
// DispatcherServlet-i manual konfiqurasiya etmək (nadir)
@Bean
public DispatcherServlet dispatcherServlet() {
    DispatcherServlet servlet = new DispatcherServlet();
    servlet.setThrowExceptionIfNoHandlerFound(true);
    return servlet;
}

// Adətən application.properties ilə:
spring.mvc.throw-exception-if-no-handler-found=true
spring.web.resources.add-mappings=false
```

---

## HttpServletRequest / HttpServletResponse

Spring Controller-lərinizdə bu objectlərə birbaşa çatmaq olar:

```java
@RestController
@RequestMapping("/api")
public class UserController {

    // Spring avtomatik inject edir:
    @GetMapping("/info")
    public Map<String, String> info(
            HttpServletRequest request,
            HttpServletResponse response) {

        // Request məlumatları:
        String method = request.getMethod();          // GET
        String uri = request.getRequestURI();         // /api/info
        String ip = request.getRemoteAddr();          // 127.0.0.1
        String userAgent = request.getHeader("User-Agent");
        String token = request.getHeader("Authorization");

        // Session:
        HttpSession session = request.getSession();
        session.setAttribute("userId", 42);

        // Response manipulyasiyası:
        response.setHeader("X-Custom-Header", "value");
        response.setStatus(HttpServletResponse.SC_OK); // 200

        return Map.of(
            "method", method,
            "uri", uri,
            "ip", ip
        );
    }

    // Cookie ilə işləmək:
    @GetMapping("/cookie")
    public String cookieExample(
            @CookieValue(name = "session_id", required = false) String sessionId,
            HttpServletResponse response) {

        // Cookie yaratmaq:
        Cookie cookie = new Cookie("session_id", "abc123");
        cookie.setHttpOnly(true);
        cookie.setMaxAge(3600);
        cookie.setPath("/");
        response.addCookie(cookie);

        return "Cookie set";
    }
}
```

**Nə zaman birbaşa HttpServletRequest istifadə etməli:**
- IP address, User-Agent almaq
- Custom header oxumaq
- Raw request body lazım olduqda
- Session ilə işləmək

---

## Filter vs HandlerInterceptor

İki mexanizm mövcuddur — hər birinin fərqli yeri var:

```
Request →→→ [Filter] →→→ [DispatcherServlet] →→→ [Interceptor] →→→ [Controller]
Response ←←←[Filter] ←←←                    ←←← [Interceptor] ←←← [Controller]
```

### Filter — Servlet səviyyəsində

```java
@Component
@Order(1)
public class LoggingFilter implements Filter {

    @Override
    public void doFilter(ServletRequest request, ServletResponse response,
                         FilterChain chain) throws IOException, ServletException {

        HttpServletRequest req = (HttpServletRequest) request;
        long start = System.currentTimeMillis();

        // Request öncəsi:
        System.out.println("→ " + req.getMethod() + " " + req.getRequestURI());

        // Növbəti filter/servlet-ə keç:
        chain.doFilter(request, response);

        // Response sonrası:
        long duration = System.currentTimeMillis() - start;
        System.out.println("← " + ((HttpServletResponse) response).getStatus()
                + " in " + duration + "ms");
    }
}
```

### HandlerInterceptor — Spring MVC səviyyəsində

```java
@Component
public class AuthInterceptor implements HandlerInterceptor {

    @Override
    public boolean preHandle(HttpServletRequest request,
                             HttpServletResponse response,
                             Object handler) throws Exception {
        // false qaytarırsa request dayanır
        String token = request.getHeader("Authorization");
        if (token == null) {
            response.setStatus(401);
            return false;
        }
        return true;
    }

    @Override
    public void postHandle(HttpServletRequest request,
                           HttpServletResponse response,
                           Object handler, ModelAndView modelAndView) {
        // Controller-dən sonra, view render-dən əvvəl
    }

    @Override
    public void afterCompletion(HttpServletRequest request,
                                HttpServletResponse response,
                                Object handler, Exception ex) {
        // Hər şeydən sonra (exception olsa belə)
        // Cleanup üçün ideal
    }
}

// Interceptor-u qeyd etmək:
@Configuration
public class WebConfig implements WebMvcConfigurer {
    @Autowired
    private AuthInterceptor authInterceptor;

    @Override
    public void addInterceptors(InterceptorRegistry registry) {
        registry.addInterceptor(authInterceptor)
                .addPathPatterns("/api/**")
                .excludePathPatterns("/api/auth/**");
    }
}
```

### Seçim meyarı:

| Xüsusiyyət | Filter | HandlerInterceptor |
|-----------|--------|-------------------|
| Yerləşmə | Servlet container | Spring MVC |
| Spring Bean-lərə çatmaq | Çətin | Asan |
| Handler (controller) məlumatı | Yoxdur | Var |
| Bütün request-lar üçün (static files) | Bəli | Xeyr |
| İstifadə | Auth token format, CORS, logging | Business logic auth, audit log |

---

## Laravel ilə müqayisə

```
Laravel Middleware      ←→  Java Filter/Interceptor

// Laravel:
public function handle(Request $request, Closure $next)
{
    // öncəsi
    $response = $next($request);
    // sonrası
    return $response;
}

// Java Filter (eyni fikir):
public void doFilter(ServletRequest req, ServletResponse res, FilterChain chain) {
    // öncəsi
    chain.doFilter(req, res);
    // sonrası
}
```

**Fərqlər:**
- Laravel-də middleware route-a bağlanır; Java-da Filter URL pattern-ə, Interceptor path pattern-ə
- Laravel-də hər request üçün yeni process; Java-da eyni JVM-də thread pool
- Laravel Kernel class filter sırasını idarə edir; Java-da `@Order` annotation

---

## İntervyu Sualları

**S: Filter ilə Interceptor arasındakı fərq nədir?**
C: Filter Servlet container səviyyəsindədir (Spring-dən əvvəl), static file-lara da tətbiq olunur, Spring Bean-lərə DI çətin. Interceptor Spring MVC səviyyəsindədir, Handler məlumatına çatmaq olar, DI tam işləyir.

**S: DispatcherServlet nədir?**
C: Spring MVC-nin front controller-i. Bütün HTTP request-ları bir Servlet qəbul edir, HandlerMapping ilə doğru Controller tapır, HandlerAdapter ilə çağırır, MessageConverter ilə response formatına çevirir.

**S: Nə zaman HttpServletRequest-ə birbaşa çatmaq lazım olur?**
C: IP address, raw header-lər, session manipulyasiyası, Cookie yaratmaq üçün. Adi hallarda `@RequestHeader`, `@RequestParam` daha yaxşıdır.

**S: Servlet thread-safe-dirmi?**
C: Servlet özü singleton-dur (bir instance), amma hər request ayrı thread-də işləyir. Instance variable istifadə etmək thread-safety problemi yaradır — həmişə method-local variable istifadə edin.
