# 94 — ProblemDetail — RFC 7807/9457 Error Response

> **Seviyye:** Middle ⭐⭐

## Mündəricat
1. [Problem nədir?](#problem-nədir)
2. [RFC 7807 strukturu](#rfc-7807-strukturu)
3. [Spring 6 — ProblemDetail class](#spring-6--problemdetail-class)
4. [ErrorResponse interface](#errorresponse-interface)
5. [Custom exception ilə inteqrasiya](#custom-exception-ilə-inteqrasiya)
6. [Global Exception Handler](#global-exception-handler)
7. [Laravel ilə müqayisə](#laravel-ilə-müqayisə)
8. [İntervyu Sualları](#intervyu-sualları)

---

## Problem nədir?

REST API-lərdə error response standartlaşdırılmayıb. Hər layihə özündə fərqli format istifadə edir:

```json
// Birinci API:
{"error": "User not found"}

// İkinci API:
{"status": 404, "message": "Not found", "code": "USER_001"}

// Üçüncü API:
{"success": false, "errors": ["User does not exist"]}
```

**RFC 7807 (Problem Details for HTTP APIs)** bu problemi həll edir — standart bir format müəyyən edir. Spring 6+ bu standartı built-in dəstəkləyir.

---

## RFC 7807 strukturu

```json
{
  "type": "https://api.example.com/errors/not-found",
  "title": "Resource Not Found",
  "status": 404,
  "detail": "User with id 42 was not found",
  "instance": "/api/users/42",
  "traceId": "a1b2c3d4",
  "timestamp": "2024-01-15T10:30:00Z"
}
```

| Field | Məcburi? | Açıqlama |
|-------|----------|---------|
| type | Xeyr | Error növünün URI-i (həmişə eyni növ üçün eyni) |
| title | Xeyr | Qısa başlıq (type üçün bir dəfə, dəyişmir) |
| status | Xeyr | HTTP status code |
| detail | Xeyr | Bu konkret hadisə haqqında detallar |
| instance | Xeyr | Bu konkret xətanın URI-i |

---

## Spring 6 — ProblemDetail class

Spring 6 (Spring Boot 3) `ProblemDetail` class-ı təqdim etdi:

```java
// application.properties-də aktiv etmək:
spring.mvc.problemdetails.enabled=true

// Spring avtomatik olaraq standart exception-ları ProblemDetail-ə çevirir:
// - HttpRequestMethodNotSupportedException → 405
// - HttpMediaTypeNotSupportedException → 415
// - MethodArgumentNotValidException → 400
// - ConstraintViolationException → 400
```

### Manual ProblemDetail yaratmaq:

```java
@RestController
@RequestMapping("/api/users")
public class UserController {

    @GetMapping("/{id}")
    public ResponseEntity<UserDto> getUser(@PathVariable Long id) {
        return userRepo.findById(id)
            .map(UserDto::fromEntity)
            .map(ResponseEntity::ok)
            .orElseGet(() -> {
                ProblemDetail problem = ProblemDetail
                    .forStatusAndDetail(HttpStatus.NOT_FOUND,
                        "User with id " + id + " was not found");
                problem.setTitle("User Not Found");
                problem.setType(URI.create("https://api.example.com/errors/not-found"));
                problem.setProperty("userId", id);
                return ResponseEntity.of(problem).build(); // ← Spring 6+
            });
    }
}
```

---

## ErrorResponse interface

Spring 6-nın `ErrorResponse` interface-i custom exception-ların ProblemDetail qaytarmasına imkan verir:

```java
public class UserNotFoundException extends RuntimeException
        implements ErrorResponse {

    private final Long userId;
    private final ProblemDetail problemDetail;

    public UserNotFoundException(Long userId) {
        super("User not found: " + userId);
        this.userId = userId;
        this.problemDetail = ProblemDetail.forStatus(HttpStatus.NOT_FOUND);
        this.problemDetail.setTitle("User Not Found");
        this.problemDetail.setDetail("User with id " + userId + " does not exist");
        this.problemDetail.setType(URI.create("https://api.example.com/errors/user-not-found"));
        this.problemDetail.setProperty("userId", userId);
    }

    @Override
    public HttpStatusCode getStatusCode() {
        return HttpStatus.NOT_FOUND;
    }

    @Override
    public ProblemDetail getBody() {
        return problemDetail;
    }
}

// İstifadə — exception atdıqda Spring avtomatik format edir:
throw new UserNotFoundException(42L);

// Response:
// HTTP/1.1 404 Not Found
// Content-Type: application/problem+json
// {
//   "type": "https://api.example.com/errors/user-not-found",
//   "title": "User Not Found",
//   "status": 404,
//   "detail": "User with id 42 does not exist",
//   "instance": "/api/users/42",
//   "userId": 42
// }
```

---

## Custom exception ilə inteqrasiya

### Exception hierarchy yaratmaq:

```java
// Base exception:
public abstract class ApiException extends RuntimeException implements ErrorResponse {

    private final HttpStatus status;
    private final ProblemDetail problemDetail;

    protected ApiException(HttpStatus status, String title, String detail) {
        super(detail);
        this.status = status;
        this.problemDetail = ProblemDetail.forStatus(status);
        this.problemDetail.setTitle(title);
        this.problemDetail.setDetail(detail);
        this.problemDetail.setType(URI.create("https://api.example.com/errors/" +
            title.toLowerCase().replace(" ", "-")));
    }

    protected void addProperty(String key, Object value) {
        problemDetail.setProperty(key, value);
    }

    @Override
    public HttpStatusCode getStatusCode() { return status; }

    @Override
    public ProblemDetail getBody() { return problemDetail; }
}

// Konkret exception-lar:
public class UserNotFoundException extends ApiException {
    public UserNotFoundException(Long id) {
        super(HttpStatus.NOT_FOUND, "User Not Found",
              "User with id " + id + " does not exist");
        addProperty("userId", id);
    }
}

public class EmailAlreadyExistsException extends ApiException {
    public EmailAlreadyExistsException(String email) {
        super(HttpStatus.CONFLICT, "Email Already Exists",
              "Email " + email + " is already registered");
        addProperty("email", email);
    }
}

public class OrderProcessingException extends ApiException {
    public OrderProcessingException(Long orderId, String reason) {
        super(HttpStatus.UNPROCESSABLE_ENTITY, "Order Processing Failed",
              "Cannot process order " + orderId + ": " + reason);
        addProperty("orderId", orderId);
        addProperty("reason", reason);
    }
}
```

---

## Global Exception Handler

`@ControllerAdvice` ilə bütün exception-ları mərkəzdən idarə etmək:

```java
@RestControllerAdvice
@Slf4j
public class GlobalExceptionHandler extends ResponseEntityExceptionHandler {

    // Validation xətaları (MethodArgumentNotValidException):
    @Override
    protected ResponseEntity<Object> handleMethodArgumentNotValid(
            MethodArgumentNotValidException ex,
            HttpHeaders headers, HttpStatusCode status,
            WebRequest request) {

        ProblemDetail problem = ProblemDetail.forStatus(status);
        problem.setTitle("Validation Failed");
        problem.setDetail("Request body validation failed");

        Map<String, List<String>> fieldErrors = ex.getBindingResult()
            .getFieldErrors()
            .stream()
            .collect(Collectors.groupingBy(
                FieldError::getField,
                Collectors.mapping(FieldError::getDefaultMessage, Collectors.toList())
            ));

        problem.setProperty("fieldErrors", fieldErrors);
        return ResponseEntity.of(problem).build();
    }

    // Custom exception-lar — ErrorResponse implement edirsə:
    @ExceptionHandler(ApiException.class)
    public ResponseEntity<ProblemDetail> handleApiException(
            ApiException ex, HttpServletRequest request) {

        log.warn("API exception at {}: {}", request.getRequestURI(), ex.getMessage());
        ProblemDetail body = ex.getBody();
        body.setInstance(URI.create(request.getRequestURI()));
        return ResponseEntity.status(ex.getStatusCode()).body(body);
    }

    // Gözlənilməyən exception-lar:
    @ExceptionHandler(Exception.class)
    public ResponseEntity<ProblemDetail> handleUnexpected(
            Exception ex, HttpServletRequest request) {

        log.error("Unexpected error at {}", request.getRequestURI(), ex);
        ProblemDetail problem = ProblemDetail.forStatus(HttpStatus.INTERNAL_SERVER_ERROR);
        problem.setTitle("Internal Server Error");
        problem.setDetail("An unexpected error occurred");
        problem.setInstance(URI.create(request.getRequestURI()));
        return ResponseEntity.internalServerError().body(problem);
    }
}
```

### Nümunə response-lar:

```bash
# 404 - User not found:
curl GET /api/users/999
{
  "type": "https://api.example.com/errors/user-not-found",
  "title": "User Not Found",
  "status": 404,
  "detail": "User with id 999 does not exist",
  "instance": "/api/users/999",
  "userId": 999
}

# 400 - Validation failed:
curl POST /api/users -d '{"name": "", "email": "invalid"}'
{
  "type": "about:blank",
  "title": "Validation Failed",
  "status": 400,
  "detail": "Request body validation failed",
  "instance": "/api/users",
  "fieldErrors": {
    "name": ["must not be blank"],
    "email": ["must be a valid email"]
  }
}
```

---

## Laravel ilə müqayisə

```php
// Laravel — default JSON error:
// {"message": "No query results for model [User] 42"}

// Laravel custom exception:
class UserNotFoundException extends \Exception
{
    public function render(): JsonResponse
    {
        return response()->json([
            'error' => 'user_not_found',
            'message' => $this->getMessage(),
        ], 404);
    }
}

// Laravel Handler.php:
public function register(): void
{
    $this->renderable(function (UserNotFoundException $e) {
        return response()->json(['error' => $e->getMessage()], 404);
    });
}
```

```java
// Spring 6 — RFC 7807 standart ilə:
// Spring avtomatik application/problem+json Content-Type əlavə edir
// Type, title, status, detail, instance standardlaşdırılıb
// Client-lər standart error format gözləyə bilər
```

**Əsas fərq:** Spring 6 RFC 7807-i built-in dəstəkləyir. Laravel-də özünüz format müəyyən etmək lazımdır.

---

## İntervyu Sualları

**S: ProblemDetail niyə standart deyildi öncə?**
C: Spring 5.x-də hər layihə özünün error format-ını yazırdı. RFC 7807 2016-da standartı müəyyən etdi, Spring 6 / Boot 3 bunu built-in implementasiya etdi.

**S: ErrorResponse interface-i nə üçün var?**
C: Custom exception-lar bu interface-i implement edərsə, Spring `@ControllerAdvice` olmadan da avtomatik olaraq ProblemDetail format-ında error qaytarır. Code less, standard more.

**S: `application/problem+json` vs `application/json` fərqi?**
C: MIME type fərqidir. `problem+json` RFC 7807 formatında olduğunu göstərir. Spring avtomatik bu Content-Type-ı əlavə edir. Client-lər error response-larını bu header ilə distinguishing edə bilər.

**S: instance field nə üçündür?**
C: Bu konkret request-in URI-i. Eyni `type` (eyni növ error) üçün fərqli `instance`-lar ola bilər. Log-larda trace etmək üçün faydalıdır — "bu 404 hansı endpoint-dən gəldi?"
