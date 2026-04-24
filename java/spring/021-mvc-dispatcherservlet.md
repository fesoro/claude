# 021 — Spring MVC — DispatcherServlet
**Səviyyə:** İrəli


## Mündəricat
1. [Front Controller Pattern nədir](#front-controller-pattern)
2. [DispatcherServlet nədir](#dispatcherservlet-nedir)
3. [HTTP Request Lifecycle](#http-request-lifecycle)
4. [HandlerMapping](#handlermapping)
5. [HandlerAdapter](#handleradapter)
6. [ViewResolver](#viewresolver)
7. [WebApplicationContext](#webapplicationcontext)
8. [DispatcherServlet İnisializasiyası](#dispatcherservlet-inisializasiyasi)
9. [MessageConverter Seçimi](#messageconverter-secimi)
10. [İntervyu Sualları](#intervyu-sualları)

---

## Front Controller Pattern

**Front Controller** — bütün gələn HTTP sorğularını tək bir nöqtədən qəbul edib, müvafiq işləyiciyə yönləndirən dizayn nümunəsidir.

```
Müştəri (Browser/API Client)
         |
         v
  [Front Controller]  ← Bütün sorğular buradan keçir
         |
    +----+----+
    |         |
[Handler A] [Handler B]
```

**Üstünlükləri:**
- Mərkəzləşdirilmiş sorğu emalı
- Autentifikasiya/avtorizasiya tək yerdə
- Logging, monitoring tək nöqtədən
- URL rewriting, locale dəyişimi asanlıqla

---

## DispatcherServlet nədir

`DispatcherServlet` — Spring MVC-nin əsas komponentidir. `javax.servlet.http.HttpServlet`-dən miras alır və **Front Controller** rolunu oynayır.

```java
// Spring Boot-da avtomatik konfiqasiya olunur
// Əl ilə konfiqurasiya etmək lazım deyil, amma belə görünür:

@Bean
public DispatcherServlet dispatcherServlet() {
    DispatcherServlet servlet = new DispatcherServlet();
    servlet.setThrowExceptionIfNoHandlerFound(true); // Handler tapılmasa istisna at
    servlet.setDetectAllHandlerMappings(true);        // Bütün HandlerMapping-ləri tap
    return servlet;
}

@Bean
public ServletRegistrationBean<DispatcherServlet> dispatcherServletRegistration(
        DispatcherServlet dispatcherServlet) {
    ServletRegistrationBean<DispatcherServlet> registration =
        new ServletRegistrationBean<>(dispatcherServlet, "/*");
    registration.setName("dispatcherServlet");
    registration.setLoadOnStartup(1); // Server başladıqda yüklə
    return registration;
}
```

Spring Boot-da `DispatcherServletAutoConfiguration` avtomatik olaraq bu işi görür.

---

## HTTP Request Lifecycle

```
HTTP Request gəldi
      |
      v
[DispatcherServlet.doDispatch()]
      |
      v
[HandlerMapping] → Handler (Controller metodu) tapır
      |
      v
[HandlerAdapter] → Handler-i çağırmağı bilir (reflection)
      |
      v
[Handler Interceptors - preHandle()] → sorğu emalından ƏVVƏL
      |
      v
[Controller Method] → biznes məntiqi
      |
      v
[Handler Interceptors - postHandle()] → sorğu emalından SONRA
      |
      v
[ViewResolver] → View adını real View-a çevirir (REST-də yoxdur)
      |
      v
[View Rendering] → HTML/JSON/XML render edilir
      |
      v
[Handler Interceptors - afterCompletion()] → hər şey bitdikdən sonra
      |
      v
HTTP Response göndərildi
```

**Kod nümunəsi — DispatcherServlet daxilindəki axın:**

```java
// Bu Spring-in daxili kodu kimidir — sadəcə anlamaq üçün
// DispatcherServlet.doDispatch() metodunun sadələşdirilmiş versiyası

protected void doDispatch(HttpServletRequest request, HttpServletResponse response)
        throws Exception {

    // 1. HandlerMapping vasitəsilə handler tap
    HandlerExecutionChain mappedHandler = getHandler(request);

    if (mappedHandler == null) {
        // Handler tapılmadı → 404
        noHandlerFound(request, response);
        return;
    }

    // 2. Handler üçün müvafiq HandlerAdapter tap
    HandlerAdapter ha = getHandlerAdapter(mappedHandler.getHandler());

    // 3. preHandle interceptor-larını çağır
    if (!mappedHandler.applyPreHandle(request, response)) {
        return; // Interceptor sorğunu dayandırdı
    }

    // 4. Handler-i icra et (Controller metodunu çağır)
    ModelAndView mv = ha.handle(request, response, mappedHandler.getHandler());

    // 5. postHandle interceptor-larını çağır
    mappedHandler.applyPostHandle(request, response, mv);

    // 6. View-u render et (REST-də HttpMessageConverter işləyir)
    processDispatchResult(request, response, mappedHandler, mv, null);
}
```

---

## HandlerMapping

`HandlerMapping` — gələn URL-ə uyğun `Handler` (Controller metodu) tapır.

Spring-də bir neçə `HandlerMapping` implementasiyası var:

| Implementasiya | Təyinat |
|---|---|
| `RequestMappingHandlerMapping` | `@RequestMapping` annotasiyalı metodları tapır |
| `BeanNameUrlHandlerMapping` | Bean adına görə URL uyğunlaşdırır |
| `RouterFunctionMapping` | Functional endpoint-lər üçün |

```java
// RequestMappingHandlerMapping avtomatik @RequestMapping-ləri skan edir
// Prioritet sırası: daha spesifik URL qalib gəlir

@RestController
@RequestMapping("/api/users")
public class UserController {

    // Bu metod: GET /api/users
    @GetMapping
    public List<User> getAllUsers() {
        return userService.findAll();
    }

    // Bu metod: GET /api/users/123
    @GetMapping("/{id}")
    public User getUserById(@PathVariable Long id) {
        return userService.findById(id);
    }

    // Bu metod: GET /api/users/profile (daha spesifik → qalib gəlir)
    @GetMapping("/profile")
    public User getCurrentUserProfile() {
        return userService.getCurrentUser();
    }
}
```

---

## HandlerAdapter

`HandlerAdapter` — tapılmış handler-i necə çağıracağını bilir. Müxtəlif handler növləri üçün müxtəlif adapter-lər var.

| Adapter | Handler Növü |
|---|---|
| `RequestMappingHandlerAdapter` | `@RequestMapping` metodları |
| `HttpRequestHandlerAdapter` | `HttpRequestHandler` interface |
| `SimpleControllerHandlerAdapter` | Köhnə `Controller` interface |

```java
// RequestMappingHandlerAdapter-i fərdiləşdirmək
@Configuration
public class WebConfig implements WebMvcConfigurer {

    @Override
    public void extendMessageConverters(List<HttpMessageConverter<?>> converters) {
        // DOĞRU: Mövcud converter-lərə əlavə et, silmə
        converters.stream()
            .filter(c -> c instanceof MappingJackson2HttpMessageConverter)
            .map(c -> (MappingJackson2HttpMessageConverter) c)
            .findFirst()
            .ifPresent(converter -> {
                ObjectMapper mapper = converter.getObjectMapper();
                // null dəyərləri JSON-a yazma
                mapper.setSerializationInclusion(JsonInclude.Include.NON_NULL);
                // Tarixləri timestamp kimi deyil, string kimi yaz
                mapper.configure(SerializationFeature.WRITE_DATES_AS_TIMESTAMPS, false);
                mapper.registerModule(new JavaTimeModule());
            });
    }
}
```

---

## ViewResolver

`ViewResolver` — Controller-dən qaytarılan view adını (`String`) real `View` obyektinə çevirir.

**REST API-lərdə ViewResolver istifadə edilmir** — bunun əvəzinə `HttpMessageConverter` işləyir.

```java
// Köhnə üsul — Thymeleaf ilə ViewResolver
@Controller // @RestController deyil!
public class HomeController {

    @GetMapping("/home")
    public String home(Model model) {
        model.addAttribute("userName", "Əli");
        return "home"; // ViewResolver "home" → "templates/home.html" tapır
    }
}

// REST API-də ViewResolver yoxdur — birbaşa JSON qaytarılır
@RestController // = @Controller + @ResponseBody
public class ApiController {

    @GetMapping("/api/data")
    public Map<String, String> getData() {
        // HttpMessageConverter (Jackson) bu obyekti JSON-a çevirir
        return Map.of("key", "value");
    }
}
```

```properties
# Thymeleaf ViewResolver konfiqurasiyası (application.properties)
spring.thymeleaf.prefix=classpath:/templates/
spring.thymeleaf.suffix=.html
spring.thymeleaf.cache=false
```

---

## WebApplicationContext

`WebApplicationContext` — standart `ApplicationContext`-in genişləndirilmiş versiyasıdır. Web-spesifik bean-ları ehtiva edir.

```
[Root WebApplicationContext]
  └── Service, Repository bean-ları
  └── Bütün DispatcherServlet-lər arasında paylaşılır
         |
    +---------+
    |         |
[Dispatcher   [Dispatcher
 WebAppCtx1]   WebAppCtx2]
 Controller,   Controller,
 MVC bean-lar  MVC bean-lar
```

```java
// WebApplicationContext-ə daxil olmaq
@RestController
public class ContextAwareController {

    @Autowired
    private WebApplicationContext webApplicationContext;

    @GetMapping("/context-info")
    public Map<String, Object> getContextInfo() {
        Map<String, Object> info = new HashMap<>();
        info.put("displayName", webApplicationContext.getDisplayName());
        info.put("beanCount", webApplicationContext.getBeanDefinitionCount());

        // Servlet context-ə çatmaq
        ServletContext servletContext = webApplicationContext.getServletContext();
        info.put("serverInfo", servletContext.getServerInfo());

        return info;
    }
}
```

---

## DispatcherServlet İnisializasiyası

`DispatcherServlet` ilk sorğu gəldikdə (və ya `loadOnStartup=1` olduqda) inisializasiya olunur.

```java
// Spring-in daxili inisializasiyasının sadələşdirilmiş versiyası
protected void initStrategies(ApplicationContext context) {
    initMultipartResolver(context);          // Fayl yükləmə üçün
    initLocaleResolver(context);             // Dil/locale üçün
    initThemeResolver(context);              // Tema üçün (köhnəlmiş)
    initHandlerMappings(context);            // URL → Handler xəritəsi
    initHandlerAdapters(context);            // Handler-i çağırma mexanizmi
    initHandlerExceptionResolvers(context);  // İstisna idarəetmə
    initRequestToViewNameTranslator(context);// View adı tərcüməsi
    initViewResolvers(context);              // View həlli
    initFlashMapManager(context);            // Redirect üçün Flash atribut
}
```

```properties
# application.properties — DispatcherServlet fərdiləşdirməsi
spring.mvc.servlet.path=/api
spring.mvc.servlet.load-on-startup=1
spring.mvc.throw-exception-if-no-handler-found=true
spring.web.resources.add-mappings=false
```

---

## MessageConverter Seçimi

`HttpMessageConverter` — Java obyektlərini HTTP request/response body-ə çevirir.

**Seçim prosesi (Content Negotiation):**

```
Client sorğusu: Accept: application/json
                                |
                                v
              Mövcud Converter-ləri yoxla:
              ✓ MappingJackson2HttpMessageConverter → application/json  UYĞUN
              ✗ StringHttpMessageConverter          → text/plain
              ✗ ByteArrayHttpMessageConverter       → application/octet-stream
                                |
                                v
              Uyğun Converter tapıldı → JSON serializasiya
```

| Converter | Media Type | Təyinat |
|---|---|---|
| `MappingJackson2HttpMessageConverter` | `application/json` | JSON serializasiya |
| `StringHttpMessageConverter` | `text/plain`, `text/*` | String cavab |
| `ByteArrayHttpMessageConverter` | `application/octet-stream` | Binary data |
| `FormHttpMessageConverter` | `application/x-www-form-urlencoded` | Form data |
| `MappingJackson2XmlHttpMessageConverter` | `application/xml` | XML (əlavə dep lazım) |

```java
@RestController
@RequestMapping("/api/demo")
public class MessageConverterDemoController {

    // Accept: application/json → Jackson converter işləyir
    @GetMapping(produces = MediaType.APPLICATION_JSON_VALUE)
    public UserDto getJson() {
        return new UserDto("Əli", "ali@example.com");
    }

    // Accept: text/plain → String converter işləyir
    @GetMapping(produces = MediaType.TEXT_PLAIN_VALUE)
    public String getText() {
        return "Sadə mətn cavabı";
    }

    // Accept: application/xml → Jackson XML converter işləyir
    // pom.xml-də jackson-dataformat-xml lazımdır
    @GetMapping(produces = MediaType.APPLICATION_XML_VALUE)
    public UserDto getXml() {
        return new UserDto("Əli", "ali@example.com");
    }
}
```

```java
// YANLIŞ: configureMessageConverters bütün default-ları silir
@Override
public void configureMessageConverters(List<HttpMessageConverter<?>> converters) {
    converters.add(new MappingJackson2HttpMessageConverter()); // Digərləri itdi!
}

// DOĞRU: extendMessageConverters mövcudları saxlayır
@Override
public void extendMessageConverters(List<HttpMessageConverter<?>> converters) {
    // Burada yalnız əlavə et və ya mövcudları dəyiş
}
```

---

## İntervyu Sualları

**S: DispatcherServlet nədir və nə üçün istifadə olunur?**

C: `DispatcherServlet` Spring MVC-nin əsas komponentidir və **Front Controller** dizayn nümunəsini implementasiya edir. Bütün HTTP sorğuları əvvəlcə `DispatcherServlet`-ə gəlir, o da `HandlerMapping` vasitəsilə müvafiq Controller-i tapır, `HandlerAdapter` ilə çağırır, nəticəni `HttpMessageConverter` vasitəsilə JSON/XML-ə çevirib cavab göndərir.

---

**S: HTTP sorğusunun Spring MVC-dəki tam həyat dövrü necədir?**

C:
1. HTTP sorğu `DispatcherServlet`-ə gəlir
2. `HandlerMapping` müvafiq Controller metodunu tapır
3. `HandlerInterceptor.preHandle()` çağırılır
4. `HandlerAdapter` Controller metodunu icra edir
5. `HandlerInterceptor.postHandle()` çağırılır
6. REST-də `HttpMessageConverter` JSON/XML-ə çevirir
7. `HandlerInterceptor.afterCompletion()` çağırılır
8. HTTP cavab göndərilir

---

**S: WebApplicationContext ilə ApplicationContext arasındakı fərq nədir?**

C: `WebApplicationContext`, `ApplicationContext`-in genişləndirilmiş versiyasıdır. `ServletContext`-ə çıxış imkanı verir və web-spesifik scope-ları (`request`, `session`, `application`) dəstəkləyir. `Root WebApplicationContext` `Service`/`Repository` bean-larını, `Dispatcher WebApplicationContext` isə `Controller`, `HandlerMapping`, `ViewResolver` kimi MVC bean-larını ehtiva edir.

---

**S: `configureMessageConverters` vs `extendMessageConverters` fərqi nədir?**

C: `configureMessageConverters()` bütün default converter-ləri əvəz edir — yalnız özünüzün əlavə etdikləriniz qalır. `extendMessageConverters()` isə default converter-ləri saxlayır, siz sadəcə əlavə edirsiniz və ya mövcudları dəyişirsiniz. Adətən `extendMessageConverters()` tövsiyə olunur.

---

**S: HandlerMapping ilə HandlerAdapter arasındakı fərq nədir?**

C: `HandlerMapping` — **tapır** (hansı handler bu sorğuya cavab verəcək?). `HandlerAdapter` — **çağırır** (tapılmış handler-i necə icra edəcək?). Bu ayrılıq müxtəlif handler növlərini eyni `DispatcherServlet` vasitəsilə dəstəkləməyə imkan verir.

---

**S: Spring Boot-da DispatcherServlet neçə ədəd olur?**

C: Default olaraq **bir ədəd** `DispatcherServlet` olur və `"/"` path-inə qeydiyyat olunur. Lazım gələrsə, bir neçə `DispatcherServlet` yaratmaq mümkündür — hər biri öz `WebApplicationContext`-inə sahib olar, amma `Root WebApplicationContext`-i paylaşar.
