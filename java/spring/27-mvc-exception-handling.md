# 27 — Spring MVC — Exception Handling

> **Seviyye:** Advanced ⭐⭐⭐


## Mündəricat
1. [@ExceptionHandler — Controller Səviyyəsində](#exceptionhandler)
2. [@ControllerAdvice / @RestControllerAdvice — Global](#controlleradvice)
3. [ResponseEntityExceptionHandler](#responseentityexceptionhandler)
4. [ProblemDetail — RFC 7807 (Spring 6+)](#problemdetail)
5. [Xüsusi Xəta Cavabı](#custom-error-response)
6. [İstisna Hierarchy və Emal Sırası](#exception-hierarchy)
7. [Tam Nümunə](#tam-numune)
8. [İntervyu Sualları](#intervyu-sualları)

---

## @ExceptionHandler — Controller Səviyyəsində

`@ExceptionHandler` — yalnız müəyyən Controller-dəki istisnalar üçün işləyir.

```java
@RestController
@RequestMapping("/api/users")
public class UserController {

    private final UserService userService;

    @GetMapping("/{id}")
    public User getUser(@PathVariable Long id) {
        return userService.findById(id)
            .orElseThrow(() -> new UserNotFoundException(id));
    }

    @PostMapping
    public User createUser(@RequestBody @Valid CreateUserRequest request) {
        if (userService.existsByEmail(request.email())) {
            throw new EmailAlreadyExistsException(request.email());
        }
        return userService.create(request);
    }

    // Yalnız BU controller-dəki UserNotFoundException-ları tutur
    @ExceptionHandler(UserNotFoundException.class)
    @ResponseStatus(HttpStatus.NOT_FOUND)
    public Map<String, String> handleUserNotFound(UserNotFoundException ex) {
        return Map.of(
            "error", "İstifadəçi tapılmadı",
            "message", ex.getMessage()
        );
    }

    // Bir neçə istisna növü üçün
    @ExceptionHandler({
        EmailAlreadyExistsException.class,
        UsernameAlreadyExistsException.class
    })
    @ResponseStatus(HttpStatus.CONFLICT)
    public Map<String, String> handleConflict(RuntimeException ex) {
        return Map.of(
            "error", "Unikallıq xətası",
            "message", ex.getMessage()
        );
    }

    // Request və Response-a daxil olmaq
    @ExceptionHandler(IllegalArgumentException.class)
    public ResponseEntity<Map<String, Object>> handleIllegalArg(
            IllegalArgumentException ex,
            HttpServletRequest request) {

        Map<String, Object> body = new LinkedHashMap<>();
        body.put("timestamp", LocalDateTime.now());
        body.put("status", 400);
        body.put("error", "Yanlış arqument");
        body.put("message", ex.getMessage());
        body.put("path", request.getRequestURI());

        return ResponseEntity.badRequest().body(body);
    }
}
```

---

## @ControllerAdvice / @RestControllerAdvice — Global

`@ControllerAdvice` — bütün Controller-lərə tətbiq edilən global istisna işləyicisidir.

`@RestControllerAdvice` = `@ControllerAdvice` + `@ResponseBody`

```java
// YANLIŞ: Hər controller-ə ayrıca @ExceptionHandler yazmaq
@RestController
public class ProductController {
    @ExceptionHandler(NotFoundException.class) // Təkrarlanan kod!
    public ErrorResponse handle(NotFoundException ex) { ... }
}

@RestController
public class OrderController {
    @ExceptionHandler(NotFoundException.class) // Yenidən təkrar!
    public ErrorResponse handle(NotFoundException ex) { ... }
}

// DOĞRU: Global @RestControllerAdvice
@RestControllerAdvice
public class GlobalExceptionHandler {

    // Bütün controller-lər üçün NotFoundException tutur
    @ExceptionHandler(NotFoundException.class)
    @ResponseStatus(HttpStatus.NOT_FOUND)
    public ErrorResponse handleNotFound(NotFoundException ex) {
        return new ErrorResponse("NOT_FOUND", ex.getMessage());
    }
}
```

**Ətraflı Global Handler:**

```java
@RestControllerAdvice
@Slf4j
public class GlobalExceptionHandler {

    // Biznes xətaları
    @ExceptionHandler(UserNotFoundException.class)
    @ResponseStatus(HttpStatus.NOT_FOUND)
    public ErrorResponse handleUserNotFound(UserNotFoundException ex) {
        log.warn("İstifadəçi tapılmadı: {}", ex.getMessage());
        return ErrorResponse.of("USER_NOT_FOUND", ex.getMessage());
    }

    @ExceptionHandler(OrderNotFoundException.class)
    @ResponseStatus(HttpStatus.NOT_FOUND)
    public ErrorResponse handleOrderNotFound(OrderNotFoundException ex) {
        log.warn("Sifariş tapılmadı: {}", ex.getMessage());
        return ErrorResponse.of("ORDER_NOT_FOUND", ex.getMessage());
    }

    // Validasiya xətaları
    @ExceptionHandler(MethodArgumentNotValidException.class)
    @ResponseStatus(HttpStatus.BAD_REQUEST)
    public ValidationErrorResponse handleValidationErrors(
            MethodArgumentNotValidException ex) {

        List<FieldError> fieldErrors = ex.getBindingResult()
            .getFieldErrors()
            .stream()
            .map(error -> new FieldError(
                error.getField(),
                error.getDefaultMessage(),
                error.getRejectedValue()
            ))
            .collect(Collectors.toList());

        return new ValidationErrorResponse(
            "VALIDATION_FAILED",
            "Daxil edilən məlumatlar yanlışdır",
            fieldErrors
        );
    }

    // Unikallıq xətaları
    @ExceptionHandler(DuplicateResourceException.class)
    @ResponseStatus(HttpStatus.CONFLICT)
    public ErrorResponse handleDuplicate(DuplicateResourceException ex) {
        log.warn("Dublikat resurs: {}", ex.getMessage());
        return ErrorResponse.of("DUPLICATE_RESOURCE", ex.getMessage());
    }

    // İcazə xətaları
    @ExceptionHandler(AccessDeniedException.class)
    @ResponseStatus(HttpStatus.FORBIDDEN)
    public ErrorResponse handleAccessDenied(AccessDeniedException ex) {
        log.warn("Giriş qadağandır: {}", ex.getMessage());
        return ErrorResponse.of("ACCESS_DENIED", "Bu əməliyyat üçün icazəniz yoxdur");
    }

    // Bütün qalanları — ümumi server xətası
    @ExceptionHandler(Exception.class)
    @ResponseStatus(HttpStatus.INTERNAL_SERVER_ERROR)
    public ErrorResponse handleAllUnexpected(Exception ex, HttpServletRequest req) {
        // Tam stack trace-i log-la, istifadəçiyə göstərmə!
        log.error("Gözlənilməz xəta: {} {}", req.getMethod(), req.getRequestURI(), ex);
        return ErrorResponse.of("INTERNAL_ERROR", "Daxili server xətası baş verdi");
    }
}
```

**@ControllerAdvice-i məhdudlaşdırmaq:**

```java
// Yalnız müəyyən package üçün
@RestControllerAdvice(basePackages = "com.example.api")
public class ApiExceptionHandler { ... }

// Yalnız müəyyən Controller-lər üçün
@RestControllerAdvice(assignableTypes = {
    UserController.class,
    ProductController.class
})
public class SpecificExceptionHandler { ... }

// Yalnız @RestController annotasiyalı Controller-lər üçün
@RestControllerAdvice(annotations = RestController.class)
public class RestExceptionHandler { ... }
```

---

## ResponseEntityExceptionHandler

Spring-in daxili standart MVC istisnalarını idarə etmək üçün base class.

```java
@RestControllerAdvice
public class CustomExceptionHandler extends ResponseEntityExceptionHandler {

    // Spring-in daxili metodunu override et
    @Override
    protected ResponseEntity<Object> handleMethodArgumentNotValid(
            MethodArgumentNotValidException ex,
            HttpHeaders headers,
            HttpStatusCode status,
            WebRequest request) {

        // Standart davranışı öz formatımızla əvəz et
        List<String> errors = ex.getBindingResult()
            .getFieldErrors()
            .stream()
            .map(e -> e.getField() + ": " + e.getDefaultMessage())
            .collect(Collectors.toList());

        Map<String, Object> body = new LinkedHashMap<>();
        body.put("timestamp", LocalDateTime.now());
        body.put("status", status.value());
        body.put("errors", errors);

        return ResponseEntity.status(status).body(body);
    }

    // Tapılmayan URL-ləri idarə et
    @Override
    protected ResponseEntity<Object> handleNoHandlerFoundException(
            NoHandlerFoundException ex,
            HttpHeaders headers,
            HttpStatusCode status,
            WebRequest request) {

        Map<String, Object> body = Map.of(
            "status", 404,
            "error", "Səhifə tapılmadı",
            "path", ex.getRequestURL()
        );

        return ResponseEntity.notFound().build();
    }

    // HTTP metod dəstəklənmir (405)
    @Override
    protected ResponseEntity<Object> handleHttpRequestMethodNotSupported(
            HttpRequestMethodNotSupportedException ex,
            HttpHeaders headers,
            HttpStatusCode status,
            WebRequest request) {

        String supported = String.join(", ",
            ex.getSupportedMethods() != null
                ? ex.getSupportedMethods()
                : new String[0]);

        Map<String, String> body = Map.of(
            "error", "HTTP metodu dəstəklənmir",
            "used", ex.getMethod(),
            "supported", supported
        );

        return ResponseEntity.status(HttpStatus.METHOD_NOT_ALLOWED)
            .allow(ex.getSupportedHttpMethods())
            .body(body);
    }

    // Content type dəstəklənmir (415)
    @Override
    protected ResponseEntity<Object> handleHttpMediaTypeNotSupported(
            HttpMediaTypeNotSupportedException ex,
            HttpHeaders headers,
            HttpStatusCode status,
            WebRequest request) {

        Map<String, String> body = Map.of(
            "error", "Media tipi dəstəklənmir",
            "contentType", ex.getContentType() != null
                ? ex.getContentType().toString() : "naməlum",
            "supported", ex.getSupportedMediaTypes().toString()
        );

        return ResponseEntity.status(HttpStatus.UNSUPPORTED_MEDIA_TYPE).body(body);
    }

    // Öz istisnalarımızı əlavə et
    @ExceptionHandler(BusinessException.class)
    public ResponseEntity<Object> handleBusinessException(BusinessException ex) {
        Map<String, Object> body = Map.of(
            "code", ex.getErrorCode(),
            "message", ex.getMessage(),
            "timestamp", LocalDateTime.now()
        );
        return ResponseEntity.status(ex.getHttpStatus()).body(body);
    }
}
```

---

## ProblemDetail — RFC 7807 (Spring 6+)

RFC 7807 (`application/problem+json`) — API xəta cavabları üçün standart format. Spring 6 / Spring Boot 3-dən etibarən dəstəklənir.

**RFC 7807 standart formatı:**
```json
{
  "type": "https://example.com/errors/user-not-found",
  "title": "İstifadəçi Tapılmadı",
  "status": 404,
  "detail": "123 ID-li istifadəçi mövcud deyil",
  "instance": "/api/users/123"
}
```

```java
// application.properties-də aktiv et
// spring.mvc.problemdetails.enabled=true

@RestControllerAdvice
public class ProblemDetailExceptionHandler extends ResponseEntityExceptionHandler {

    // Sadə ProblemDetail
    @ExceptionHandler(UserNotFoundException.class)
    public ProblemDetail handleUserNotFound(
            UserNotFoundException ex, HttpServletRequest request) {

        ProblemDetail problem = ProblemDetail.forStatus(HttpStatus.NOT_FOUND);
        problem.setTitle("İstifadəçi Tapılmadı");
        problem.setDetail(ex.getMessage());
        problem.setInstance(URI.create(request.getRequestURI()));
        problem.setType(URI.create("https://api.example.com/errors/user-not-found"));

        return problem;
    }

    // Genişləndirilmiş ProblemDetail (əlavə sahələr)
    @ExceptionHandler(ValidationException.class)
    public ProblemDetail handleValidation(
            ValidationException ex, HttpServletRequest request) {

        ProblemDetail problem = ProblemDetail.forStatus(HttpStatus.UNPROCESSABLE_ENTITY);
        problem.setTitle("Validasiya Xətası");
        problem.setDetail("Daxil edilən məlumatlar yanlışdır");
        problem.setInstance(URI.create(request.getRequestURI()));

        // Əlavə sahələr — "properties" kimi əlavə olunur
        problem.setProperty("errors", ex.getErrors());
        problem.setProperty("timestamp", LocalDateTime.now());
        problem.setProperty("errorCode", "VALIDATION_FAILED");

        return problem;
    }

    // ResponseEntity<ProblemDetail>
    @ExceptionHandler(RateLimitExceededException.class)
    public ResponseEntity<ProblemDetail> handleRateLimit(
            RateLimitExceededException ex, HttpServletRequest request) {

        ProblemDetail problem = ProblemDetail.forStatus(HttpStatus.TOO_MANY_REQUESTS);
        problem.setTitle("Limit Aşıldı");
        problem.setDetail("Çox sayda sorğu göndərdiniz. Gözləyin.");
        problem.setProperty("retryAfterSeconds", ex.getRetryAfterSeconds());

        return ResponseEntity
            .status(HttpStatus.TOO_MANY_REQUESTS)
            .header("Retry-After", String.valueOf(ex.getRetryAfterSeconds()))
            .body(problem);
    }
}
```

---

## Xüsusi Xəta Cavabı

```java
// Xüsusi xəta cavab DTO-ları
public record ErrorResponse(
    String code,
    String message,
    LocalDateTime timestamp,
    String path
) {
    // Factory metodu
    public static ErrorResponse of(String code, String message) {
        return new ErrorResponse(code, message, LocalDateTime.now(), null);
    }

    public static ErrorResponse of(String code, String message, String path) {
        return new ErrorResponse(code, message, LocalDateTime.now(), path);
    }
}

public record ValidationErrorResponse(
    String code,
    String message,
    List<FieldError> errors,
    LocalDateTime timestamp
) {
    public record FieldError(
        String field,
        String message,
        Object rejectedValue
    ) {}
}

// Xüsusi istisna sinifləri
public class NotFoundException extends RuntimeException {
    private final String resourceType;
    private final Object resourceId;

    public NotFoundException(String resourceType, Object resourceId) {
        super(String.format("%s tapılmadı: %s", resourceType, resourceId));
        this.resourceType = resourceType;
        this.resourceId = resourceId;
    }
    // Getters...
}

public class UserNotFoundException extends NotFoundException {
    public UserNotFoundException(Long id) {
        super("İstifadəçi", id);
    }
}

public class BusinessException extends RuntimeException {
    private final String errorCode;
    private final HttpStatus httpStatus;

    public BusinessException(String errorCode, String message, HttpStatus status) {
        super(message);
        this.errorCode = errorCode;
        this.httpStatus = status;
    }
    // Getters...
}
```

---

## İstisna Hierarchy və Emal Sırası

Spring `@ExceptionHandler`-i axtararkən bu sıranı izləyir:

```
İstisna atıldı
      |
      v
1. Həmin Controller-dəki @ExceptionHandler yoxla
   (ən spesifik uyğunluq qalib gəlir)
      |
      v (tapılmadısa)
2. @ControllerAdvice-dəki @ExceptionHandler yoxla
   (ən spesifik istisna tipi qalib gəlir)
      |
      v (tapılmadısa)
3. Super class istisnaları yoxla
      |
      v (tapılmadısa)
4. HandlerExceptionResolver zənciri
      |
      v (tapılmadısa)
5. Default → 500 Internal Server Error
```

**Spesifiklik sırası nümunəsi:**

```java
@RestControllerAdvice
public class HierarchyExampleHandler {

    // UserNotFoundException, ProductNotFoundException hər ikisini tutur
    // (NotFoundException-ın alt sinifləridir)
    @ExceptionHandler(NotFoundException.class)
    @ResponseStatus(HttpStatus.NOT_FOUND)
    public ErrorResponse handleNotFound(NotFoundException ex) {
        return ErrorResponse.of("NOT_FOUND", ex.getMessage());
    }

    // Yalnız UserNotFoundException üçün — daha spesifik → prioritet alır
    @ExceptionHandler(UserNotFoundException.class)
    @ResponseStatus(HttpStatus.NOT_FOUND)
    public ErrorResponse handleUserNotFound(UserNotFoundException ex) {
        // Bu metod NotFoundException-dakındən əvvəl çağırılır
        return ErrorResponse.of("USER_NOT_FOUND", ex.getMessage());
    }

    // RuntimeException — bütün runtime istisnalar (az spesifik)
    @ExceptionHandler(RuntimeException.class)
    @ResponseStatus(HttpStatus.INTERNAL_SERVER_ERROR)
    public ErrorResponse handleRuntime(RuntimeException ex) {
        return ErrorResponse.of("RUNTIME_ERROR", "Gözlənilməz xəta");
    }

    // Exception — hər şeyi tutur (ən az spesifik)
    @ExceptionHandler(Exception.class)
    @ResponseStatus(HttpStatus.INTERNAL_SERVER_ERROR)
    public ErrorResponse handleAll(Exception ex) {
        log.error("Tutulmayan istisna", ex);
        return ErrorResponse.of("INTERNAL_ERROR", "Daxili xəta");
    }
}
```

**Bir neçə @ControllerAdvice-in prioriteti:**

```java
// Prioritet: @Order ilə müəyyən edilir
@RestControllerAdvice
@Order(1) // Daha yüksək prioritet
public class HighPriorityHandler {
    @ExceptionHandler(SpecificException.class)
    public ErrorResponse handle(SpecificException ex) { ... }
}

@RestControllerAdvice
@Order(2) // Daha aşağı prioritet
public class LowPriorityHandler {
    @ExceptionHandler(Exception.class) // Hər şeyi tutur
    public ErrorResponse handle(Exception ex) { ... }
}
```

---

## Tam Nümunə

```java
// 1. Xüsusi istisnalar
@Getter
public class ResourceNotFoundException extends RuntimeException {
    private final String resource;
    private final Object id;

    public ResourceNotFoundException(String resource, Object id) {
        super(String.format("%s ID=%s tapılmadı", resource, id));
        this.resource = resource;
        this.id = id;
    }
}

@Getter
public class ConflictException extends RuntimeException {
    private final String field;
    private final Object value;

    public ConflictException(String field, Object value) {
        super(String.format("%s artıq mövcuddur: %s", field, value));
        this.field = field;
        this.value = value;
    }
}

// 2. Xəta cavab formatı
public record ApiError(
    int status,
    String code,
    String message,
    @JsonInclude(JsonInclude.Include.NON_NULL)
    List<FieldViolation> violations,
    Instant timestamp,
    String path
) {
    public record FieldViolation(String field, String message) {}

    public static ApiError of(int status, String code, String message, String path) {
        return new ApiError(status, code, message, null, Instant.now(), path);
    }
}

// 3. Global exception handler
@RestControllerAdvice
@Slf4j
public class ApiExceptionHandler extends ResponseEntityExceptionHandler {

    @ExceptionHandler(ResourceNotFoundException.class)
    public ResponseEntity<ApiError> handleNotFound(
            ResourceNotFoundException ex, HttpServletRequest req) {

        log.warn("Resurs tapılmadı: {} id={}", ex.getResource(), ex.getId());
        ApiError error = ApiError.of(404, "RESOURCE_NOT_FOUND",
            ex.getMessage(), req.getRequestURI());

        return ResponseEntity.status(HttpStatus.NOT_FOUND).body(error);
    }

    @ExceptionHandler(ConflictException.class)
    public ResponseEntity<ApiError> handleConflict(
            ConflictException ex, HttpServletRequest req) {

        log.warn("Konflikt: {} = {}", ex.getField(), ex.getValue());
        ApiError error = ApiError.of(409, "CONFLICT",
            ex.getMessage(), req.getRequestURI());

        return ResponseEntity.status(HttpStatus.CONFLICT).body(error);
    }

    @Override
    protected ResponseEntity<Object> handleMethodArgumentNotValid(
            MethodArgumentNotValidException ex,
            HttpHeaders headers, HttpStatusCode status, WebRequest request) {

        // Field xətalarını topla
        List<ApiError.FieldViolation> violations = ex.getBindingResult()
            .getFieldErrors()
            .stream()
            .map(e -> new ApiError.FieldViolation(e.getField(), e.getDefaultMessage()))
            .toList();

        String path = ((ServletWebRequest) request).getRequest().getRequestURI();
        ApiError error = new ApiError(400, "VALIDATION_FAILED",
            "Daxil edilən məlumatlar düzgün deyil", violations, Instant.now(), path);

        return ResponseEntity.badRequest().body(error);
    }

    @ExceptionHandler(Exception.class)
    public ResponseEntity<ApiError> handleUnexpected(
            Exception ex, HttpServletRequest req) {

        log.error("Gözlənilməz xəta: {} {}",
            req.getMethod(), req.getRequestURI(), ex);

        ApiError error = ApiError.of(500, "INTERNAL_ERROR",
            "Daxili server xətası", req.getRequestURI());

        return ResponseEntity.internalServerError().body(error);
    }
}

// 4. Controller
@RestController
@RequestMapping("/api/users")
@RequiredArgsConstructor
public class UserController {

    private final UserService userService;

    @GetMapping("/{id}")
    public UserDto getUser(@PathVariable Long id) {
        return userService.findById(id)
            .orElseThrow(() -> new ResourceNotFoundException("İstifadəçi", id));
    }

    @PostMapping
    @ResponseStatus(HttpStatus.CREATED)
    public UserDto createUser(@RequestBody @Valid CreateUserRequest request) {
        if (userService.existsByEmail(request.email())) {
            throw new ConflictException("email", request.email());
        }
        return userService.create(request);
    }
}
```

---

## İntervyu Sualları

**S: @ExceptionHandler ilə @ControllerAdvice arasındakı fərq nədir?**

C: `@ExceptionHandler` yalnız yerləşdirildiyi Controller sinifi üçün işləyir — lokal scope-dur. `@ControllerAdvice` (və ya `@RestControllerAdvice`) bütün Controller-lərə tətbiq olunan qlobal istisna işləyicisidir. Adətən `@ExceptionHandler`-i yalnız həmin Controller-ə xas istisnalar üçün, qlobal olanları isə `@ControllerAdvice`-də yazırıq.

---

**S: ResponseEntityExceptionHandler-dən niyə miras alırıq?**

C: `ResponseEntityExceptionHandler` Spring MVC-nin daxili standart istisnalarını (`MethodArgumentNotValidException`, `NoHandlerFoundException`, `HttpRequestMethodNotSupportedException` və s.) idarə edən metodları ehtiva edir. Bu sinifi extend edərək həmin metodları override etməklə standart Spring istisnalarının formatını da öz formatımıza uyğunlaşdıra bilirik.

---

**S: ProblemDetail (RFC 7807) nədir?**

C: RFC 7807 — API xəta cavabları üçün standartlaşdırılmış JSON formatıdır. `type`, `title`, `status`, `detail`, `instance` sahələrini ehtiva edir. Spring Boot 3-dən (Spring 6+) dəstəklənir. `application.properties`-də `spring.mvc.problemdetails.enabled=true` ilə avtomatik aktiv olur. Məziyyəti — API istehlakçılarının xəta cavablarını müxtəlif API-lardan asılı olmayaraq işləyə bilməsidir.

---

**S: İstisna işləmə sırası necədir?**

C: 1) Həmin Controller-dəki `@ExceptionHandler` (spesifikdən ümumiyə), 2) `@ControllerAdvice`-dəki `@ExceptionHandler` (spesifikdən ümumiyə, `@Order`-a görə), 3) `HandlerExceptionResolver` zənciri, 4) Default → 500. Eyni tipli istisna üçün daha spesifik (alt sinif) handler qalib gəlir.

---

**S: @ExceptionHandler metodunda hansı parametrləri qəbul edə bilərik?**

C: İstisna özü, `HttpServletRequest`, `HttpServletResponse`, `WebRequest`, `Model`, `Principal`, `Locale`, `InputStream`/`OutputStream`, `HttpSession`, `HttpMethod`, `@SessionAttribute`/`@RequestAttribute` işarəli parametrlər qəbul edilə bilər. Bu çeviklik sayəsində request-ə aid məlumatları (URL, metod, başlıqlar) xəta cavabına daxil etmək mümkündür.
