# Problem Details (RFC 7807 / RFC 9457) — Standart Xəta Cavabı

## Giriş

REST API-lərdə xəta qaytaranda hər layihə öz formasını uydurur: biri `{"error": "..."}`, biri `{"message": "...", "code": 42}`, biri `{"errors": [{"field": "..."}]}`. Bu, client tərəfi çətinləşdirir — hər servis üçün xəta parsing yazmaq lazımdır.

**RFC 7807** (2016) və onun yenilənmiş variantı **RFC 9457** (2023) bu problemi həll edir. Problem Details — HTTP xəta cavabları üçün standart JSON formasıdır. Content-Type `application/problem+json` və ya `application/problem+xml` olur.

Məcburi sahələr:
- **`type`** — xətanın URI identifikatoru (məs. `https://api.example.com/problems/validation-error`). Client bu URI-yə baxıb xətanın tipini anlaya bilər.
- **`title`** — qısa insan-oxuyan başlıq (dəyişməz olmalı).
- **`status`** — HTTP status code (integer).
- **`detail`** — konkret vəziyyəti izah edən mətn.
- **`instance`** — xətanın konkret hadisə URI-si (məs. `/orders/42/errors/abc-123`).

Əlavə olaraq **extensions** dəstəklənir — istədiyin sahəni əlavə et (məs. `errors`, `traceId`, `timestamp`).

**Spring 6+** `ProblemDetail` sinfi ilə bunu birbaşa dəstəkləyir. **Laravel**-də built-in Problem Details yoxdur — `App\Exceptions\Handler` və ya Laravel 11-in `bootstrap/app.php` `withExceptions` ilə əl ilə qurmaq lazımdır.

---

## Spring-də istifadəsi

### 1) `ProblemDetail` — əsas sinif

```java
import org.springframework.http.ProblemDetail;
import org.springframework.http.HttpStatus;
import java.net.URI;

ProblemDetail problem = ProblemDetail.forStatusAndDetail(
    HttpStatus.NOT_FOUND,
    "Order with id 42 was not found"
);
problem.setType(URI.create("https://api.example.com/problems/order-not-found"));
problem.setTitle("Order Not Found");
problem.setInstance(URI.create("/orders/42"));
problem.setProperty("orderId", 42);           // extension
problem.setProperty("timestamp", Instant.now());
```

JSON çıxışı:

```json
{
  "type": "https://api.example.com/problems/order-not-found",
  "title": "Order Not Found",
  "status": 404,
  "detail": "Order with id 42 was not found",
  "instance": "/orders/42",
  "orderId": 42,
  "timestamp": "2026-04-20T10:15:30Z"
}
```

Content-Type avtomatik `application/problem+json` olur.

### 2) Built-in aktivasiya — `application.yml`

```yaml
spring:
  mvc:
    problemdetails:
      enabled: true                  # Spring MVC üçün
  webflux:
    problemdetails:
      enabled: true                  # WebFlux üçün
```

Bu flag aktivləşsə, Spring özü bu exception-ları avtomatik Problem Details formasına çevirir:

- `ResponseStatusException`
- `BindException`
- `MethodArgumentNotValidException` (validation xətaları)
- `HttpMessageNotReadableException`
- `HttpMediaTypeNotSupportedException`
- `HttpRequestMethodNotSupportedException`
- `NoHandlerFoundException`
- `ErrorResponseException`

Nümunə — `ResponseStatusException`:

```java
@GetMapping("/orders/{id}")
public Order getOrder(@PathVariable Long id) {
    return orderRepo.findById(id)
        .orElseThrow(() -> new ResponseStatusException(
            HttpStatus.NOT_FOUND,
            "Order " + id + " not found"
        ));
}
```

Cavab:

```json
{
  "type": "about:blank",
  "title": "Not Found",
  "status": 404,
  "detail": "Order 42 not found",
  "instance": "/orders/42"
}
```

### 3) `ErrorResponseException` — özəl exception

```java
public class OrderNotFoundException extends ErrorResponseException {

    public OrderNotFoundException(Long orderId) {
        super(HttpStatus.NOT_FOUND, asProblemDetail(orderId), null);
    }

    private static ProblemDetail asProblemDetail(Long orderId) {
        ProblemDetail pd = ProblemDetail.forStatusAndDetail(
            HttpStatus.NOT_FOUND,
            "Order with id " + orderId + " does not exist"
        );
        pd.setType(URI.create("https://api.example.com/problems/order-not-found"));
        pd.setTitle("Order Not Found");
        pd.setProperty("orderId", orderId);
        return pd;
    }
}

@Service
public class OrderService {
    public Order getById(Long id) {
        return repo.findById(id).orElseThrow(() -> new OrderNotFoundException(id));
    }
}
```

Controller-də `throw new OrderNotFoundException(42L)` edəndə Spring avtomatik Problem Details cavabı göndərir — əlavə `@ExceptionHandler` lazım deyil.

### 4) `@ControllerAdvice` ilə custom exception mapping

```java
@RestControllerAdvice
public class GlobalExceptionHandler extends ResponseEntityExceptionHandler {

    @ExceptionHandler(InsufficientFundsException.class)
    public ProblemDetail handleInsufficientFunds(
            InsufficientFundsException ex,
            HttpServletRequest request) {

        ProblemDetail pd = ProblemDetail.forStatusAndDetail(
            HttpStatus.CONFLICT,
            "Balance is not enough to complete the transfer"
        );
        pd.setType(URI.create("https://api.example.com/problems/insufficient-funds"));
        pd.setTitle("Insufficient Funds");
        pd.setInstance(URI.create(request.getRequestURI()));
        pd.setProperty("accountId", ex.getAccountId());
        pd.setProperty("required", ex.getRequired());
        pd.setProperty("available", ex.getAvailable());
        pd.setProperty("currency", ex.getCurrency());
        pd.setProperty("traceId", MDC.get("traceId"));
        return pd;
    }

    @ExceptionHandler(AccessDeniedException.class)
    public ProblemDetail handleAccessDenied(AccessDeniedException ex) {
        ProblemDetail pd = ProblemDetail.forStatusAndDetail(
            HttpStatus.FORBIDDEN,
            "You do not have permission to access this resource"
        );
        pd.setType(URI.create("https://api.example.com/problems/forbidden"));
        pd.setTitle("Forbidden");
        return pd;
    }
}
```

### 5) Validation xətaları — `MethodArgumentNotValidException`

```java
public record CreateOrderRequest(
    @NotBlank String customerEmail,
    @Size(min = 1, max = 100) List<@Valid OrderLine> lines,
    @Positive BigDecimal totalAmount
) {}

public record OrderLine(
    @NotNull Long productId,
    @Positive int quantity
) {}

@PostMapping("/orders")
public Order create(@Valid @RequestBody CreateOrderRequest req) {
    return orderService.create(req);
}
```

Default cavab (Spring 6 `problemdetails.enabled=true` olduqda):

```json
{
  "type": "about:blank",
  "title": "Bad Request",
  "status": 400,
  "detail": "Invalid request content.",
  "instance": "/orders"
}
```

Validation sahələri detallı göstərmək üçün handler override edilir:

```java
@RestControllerAdvice
public class ValidationExceptionHandler extends ResponseEntityExceptionHandler {

    @Override
    protected ResponseEntity<Object> handleMethodArgumentNotValid(
            MethodArgumentNotValidException ex,
            HttpHeaders headers,
            HttpStatusCode status,
            WebRequest request) {

        List<Map<String, Object>> fieldErrors = ex.getBindingResult()
            .getFieldErrors().stream()
            .map(fe -> Map.<String, Object>of(
                "field", fe.getField(),
                "message", fe.getDefaultMessage() == null ? "invalid" : fe.getDefaultMessage(),
                "rejectedValue", fe.getRejectedValue() == null ? "" : fe.getRejectedValue()
            ))
            .toList();

        ProblemDetail pd = ProblemDetail.forStatusAndDetail(
            HttpStatus.BAD_REQUEST,
            "Request validation failed"
        );
        pd.setType(URI.create("https://api.example.com/problems/validation-error"));
        pd.setTitle("Validation Error");
        pd.setInstance(URI.create(((ServletWebRequest) request).getRequest().getRequestURI()));
        pd.setProperty("errors", fieldErrors);
        pd.setProperty("traceId", MDC.get("traceId"));

        return ResponseEntity.status(status)
            .contentType(MediaType.APPLICATION_PROBLEM_JSON)
            .body(pd);
    }
}
```

Cavab:

```json
{
  "type": "https://api.example.com/problems/validation-error",
  "title": "Validation Error",
  "status": 400,
  "detail": "Request validation failed",
  "instance": "/orders",
  "errors": [
    { "field": "customerEmail", "message": "must not be blank", "rejectedValue": "" },
    { "field": "totalAmount", "message": "must be positive", "rejectedValue": -5.0 },
    { "field": "lines[0].quantity", "message": "must be positive", "rejectedValue": 0 }
  ],
  "traceId": "abc-123-def"
}
```

### 6) Business xətaları — custom `type` URI

Hər biznes xətası üçün özəl URI — bu URI-də sənədlərin olacaq (`https://api.example.com/problems/insufficient-funds` → həll yolu, niyə baş verir, retryable mi).

```java
public enum ProblemType {
    INSUFFICIENT_FUNDS("https://api.example.com/problems/insufficient-funds", "Insufficient Funds"),
    ORDER_NOT_FOUND("https://api.example.com/problems/order-not-found", "Order Not Found"),
    VALIDATION_ERROR("https://api.example.com/problems/validation-error", "Validation Error"),
    RATE_LIMITED("https://api.example.com/problems/rate-limited", "Rate Limit Exceeded"),
    ACCOUNT_LOCKED("https://api.example.com/problems/account-locked", "Account Locked");

    public final String type;
    public final String title;

    ProblemType(String type, String title) { this.type = type; this.title = title; }

    public ProblemDetail asDetail(HttpStatus status, String detail) {
        ProblemDetail pd = ProblemDetail.forStatusAndDetail(status, detail);
        pd.setType(URI.create(type));
        pd.setTitle(title);
        return pd;
    }
}
```

### 7) `ProblemDetail` WebFlux-da

Reactive stack-də eyni sinif işləyir:

```java
@RestController
public class OrderController {

    @GetMapping("/orders/{id}")
    public Mono<Order> get(@PathVariable Long id) {
        return orderRepo.findById(id)
            .switchIfEmpty(Mono.error(new OrderNotFoundException(id)));
    }
}

@RestControllerAdvice
public class ReactiveAdvice {

    @ExceptionHandler(OrderNotFoundException.class)
    public Mono<ResponseEntity<ProblemDetail>> handle(OrderNotFoundException ex) {
        ProblemDetail pd = ProblemType.ORDER_NOT_FOUND.asDetail(HttpStatus.NOT_FOUND, ex.getMessage());
        return Mono.just(ResponseEntity.status(404)
            .contentType(MediaType.APPLICATION_PROBLEM_JSON)
            .body(pd));
    }
}
```

### 8) Köhnə stil — custom error DTO (Spring 5 və əvvəli)

Spring 6-a qədər insanlar belə yazırdı:

```java
public record ApiError(
    int status,
    String error,
    String message,
    String path,
    Instant timestamp
) {}

@RestControllerAdvice
public class OldStyleHandler {
    @ExceptionHandler(RuntimeException.class)
    public ResponseEntity<ApiError> handle(RuntimeException ex, HttpServletRequest req) {
        ApiError err = new ApiError(
            500,
            "Internal Server Error",
            ex.getMessage(),
            req.getRequestURI(),
            Instant.now()
        );
        return ResponseEntity.status(500).body(err);
    }
}
```

Bu variant standart deyil — hər layihədə struktur başqadır. `ProblemDetail` bu sonra gəlmiş standart yanaşmadır.

---

## Laravel-də istifadəsi

### 1) Default exception rendering — Laravel 11+

Laravel 11-də `App\Exceptions\Handler` silinib. Hər şey `bootstrap/app.php`-də:

```php
// bootstrap/app.php
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function ($middleware) {
        // middleware config
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // exception rendering burada qurulur
    })
    ->create();
```

### 2) ValidationException — avtomatik 422

Laravel `FormRequest` istifadə etsən, validation xətası avtomatik 422 cavabını qaytarır:

```php
class StoreOrderRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'customer_email' => ['required', 'email'],
            'total_amount'   => ['required', 'numeric', 'gt:0'],
            'lines'          => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'lines.*.quantity'   => ['required', 'integer', 'min:1'],
        ];
    }
}

Route::post('/orders', function (StoreOrderRequest $req) {
    return Order::create($req->validated());
});
```

Default cavab (Accept: application/json göndəriləndə):

```json
{
  "message": "The customer email field is required. (and 1 more error)",
  "errors": {
    "customer_email": ["The customer email field is required."],
    "total_amount": ["The total amount field must be greater than 0."]
  }
}
```

Bu — Laravel-in öz "de facto" formasıdır. RFC 7807 deyil.

### 3) RFC 7807 əl ilə — `withExceptions`

Laravel 11+ üçün:

```php
// bootstrap/app.php
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Illuminate\Http\Request;
use Throwable;

->withExceptions(function (Exceptions $exceptions) {
    $exceptions->render(function (ValidationException $e, Request $req) {
        if (! $req->expectsJson()) {
            return null; // default HTML render
        }
        return response()->json([
            'type'     => url('/problems/validation-error'),
            'title'    => 'Validation Error',
            'status'   => 422,
            'detail'   => 'The submitted data is not valid',
            'instance' => $req->getRequestUri(),
            'errors'   => collect($e->errors())->map(fn ($messages, $field) => [
                'field'   => $field,
                'messages' => $messages,
            ])->values(),
            'traceId' => request()->header('X-Request-Id'),
        ], 422, ['Content-Type' => 'application/problem+json']);
    });

    $exceptions->render(function (ModelNotFoundException $e, Request $req) {
        if (! $req->expectsJson()) return null;
        $model = class_basename($e->getModel());
        return response()->json([
            'type'     => url('/problems/not-found'),
            'title'    => "{$model} Not Found",
            'status'   => 404,
            'detail'   => "No {$model} found with the given identifier",
            'instance' => $req->getRequestUri(),
            'resource' => $model,
            'ids'      => $e->getIds(),
        ], 404, ['Content-Type' => 'application/problem+json']);
    });

    $exceptions->render(function (AuthenticationException $e, Request $req) {
        if (! $req->expectsJson()) return null;
        return response()->json([
            'type'     => url('/problems/unauthorized'),
            'title'    => 'Unauthorized',
            'status'   => 401,
            'detail'   => 'Authentication is required to access this resource',
            'instance' => $req->getRequestUri(),
        ], 401, ['Content-Type' => 'application/problem+json']);
    });

    $exceptions->render(function (AuthorizationException $e, Request $req) {
        if (! $req->expectsJson()) return null;
        return response()->json([
            'type'     => url('/problems/forbidden'),
            'title'    => 'Forbidden',
            'status'   => 403,
            'detail'   => $e->getMessage() ?: 'You do not have permission to perform this action',
            'instance' => $req->getRequestUri(),
        ], 403, ['Content-Type' => 'application/problem+json']);
    });

    $exceptions->render(function (Throwable $e, Request $req) {
        if (! $req->expectsJson()) return null;
        if ($e instanceof HttpExceptionInterface) {
            return response()->json([
                'type'     => url('/problems/http-' . $e->getStatusCode()),
                'title'    => 'HTTP ' . $e->getStatusCode(),
                'status'   => $e->getStatusCode(),
                'detail'   => $e->getMessage(),
                'instance' => $req->getRequestUri(),
            ], $e->getStatusCode(), ['Content-Type' => 'application/problem+json']);
        }
        return response()->json([
            'type'     => url('/problems/internal'),
            'title'    => 'Internal Server Error',
            'status'   => 500,
            'detail'   => app()->hasDebugModeEnabled() ? $e->getMessage() : 'An unexpected error occurred',
            'instance' => $req->getRequestUri(),
            'traceId'  => request()->header('X-Request-Id'),
        ], 500, ['Content-Type' => 'application/problem+json']);
    });
});
```

### 4) Helper — `ProblemDetail` DTO

Təkrarı azaltmaq üçün kiçik sinif:

```php
// app/Http/Problems/ProblemDetail.php
namespace App\Http\Problems;

use Illuminate\Http\JsonResponse;

class ProblemDetail
{
    public function __construct(
        public readonly string $type,
        public readonly string $title,
        public readonly int $status,
        public readonly string $detail,
        public readonly ?string $instance = null,
        public array $extensions = [],
    ) {}

    public function toResponse(): JsonResponse
    {
        $body = array_merge([
            'type'     => $this->type,
            'title'    => $this->title,
            'status'   => $this->status,
            'detail'   => $this->detail,
            'instance' => $this->instance,
        ], $this->extensions);
        return response()->json(
            array_filter($body, fn ($v) => $v !== null),
            $this->status,
            ['Content-Type' => 'application/problem+json']
        );
    }
}
```

Business exception:

```php
// app/Exceptions/InsufficientFundsException.php
namespace App\Exceptions;

use App\Http\Problems\ProblemDetail;
use Exception;

class InsufficientFundsException extends Exception
{
    public function __construct(
        public readonly int $accountId,
        public readonly float $required,
        public readonly float $available,
        public readonly string $currency = 'USD',
    ) {
        parent::__construct("Account {$accountId} has only {$available} {$currency}, but {$required} is required");
    }

    public function render()
    {
        return (new ProblemDetail(
            type: url('/problems/insufficient-funds'),
            title: 'Insufficient Funds',
            status: 409,
            detail: $this->getMessage(),
            instance: request()->getRequestUri(),
            extensions: [
                'accountId' => $this->accountId,
                'required'  => $this->required,
                'available' => $this->available,
                'currency'  => $this->currency,
            ],
        ))->toResponse();
    }
}
```

Laravel exception-lar `render()` metoduna malik olsa, framework avtomatik onu çağırır — `withExceptions`-da ayrıca qeyd etməyə ehtiyac yoxdur.

İstifadə:

```php
public function transfer(TransferRequest $req)
{
    $from = Account::findOrFail($req->from_id);
    if ($from->balance < $req->amount) {
        throw new InsufficientFundsException(
            accountId: $from->id,
            required: $req->amount,
            available: $from->balance,
            currency: $from->currency,
        );
    }
    // ...
}
```

Cavab:

```json
{
  "type": "https://api.example.com/problems/insufficient-funds",
  "title": "Insufficient Funds",
  "status": 409,
  "detail": "Account 42 has only 100 USD, but 500 is required",
  "instance": "/api/transfers",
  "accountId": 42,
  "required": 500,
  "available": 100,
  "currency": "USD"
}
```

### 5) Laravel 10 və əvvəli — `App\Exceptions\Handler`

```php
// app/Exceptions/Handler.php (Laravel 10)
namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    public function register(): void
    {
        $this->renderable(function (ValidationException $e, $request) {
            if (! $request->expectsJson()) return null;
            return response()->json([
                'type'   => url('/problems/validation-error'),
                'title'  => 'Validation Error',
                'status' => 422,
                'detail' => 'Request body validation failed',
                'errors' => $e->errors(),
            ], 422, ['Content-Type' => 'application/problem+json']);
        });
    }
}
```

### 6) Community paketi — `laravel/problem-details`

Rəsmi paket yoxdur. Community paketləri var (məsələn `cerbero/laravel-json-api`) amma ən asan yol öz helper-ini yazmaqdır — çünki Laravel-də exception rendering artıq çox sadədir.

### 7) Middleware ilə Accept header control

```php
// app/Http/Middleware/EnforceJsonProblem.php
class EnforceJsonProblem
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);
        if ($response->isClientError() || $response->isServerError()) {
            $response->headers->set('Content-Type', 'application/problem+json');
        }
        return $response;
    }
}
```

---

## Əsas fərqlər

| Xüsusiyyət | Spring | Laravel |
|---|---|---|
| RFC 7807 built-in | Bəli — `ProblemDetail` sinfi | Xeyr — əl ilə qurulur |
| Auto-enable flag | `spring.mvc.problemdetails.enabled=true` | Yox — hər exception əl ilə |
| Default handler | `ResponseEntityExceptionHandler` | `bootstrap/app.php withExceptions` |
| Validation errors | `MethodArgumentNotValidException` handler override | `ValidationException::errors()` |
| Custom exception | `extends ErrorResponseException` | `render()` metodu class-da |
| Content-Type | Avtomatik `application/problem+json` | Əl ilə qurulmalı |
| Extensions | `pd.setProperty("key", value)` | Associative array |
| Reactive dəstək | WebFlux-da eyni `ProblemDetail` | Laravel Octane-da eyni kod |
| `type` URI-lər | Enum ilə mərkəzləşdirmək asan | Helper sinif lazımdır |
| Paket lazımdır | Yox — Spring 6 daxilində | Yox — amma əl ilə yazılır |

---

## Niyə belə fərqlər var?

**Spring-in standartlara yönəlməsi.** Spring ekosistemi enterprise mühitə tuşlanıb — çoxlu servis arasında eyni forma vacibdir. RFC 7807 2016-da çıxdı, Spring 6 (2022) onu birinci-dərəcəli dəstəyə çevirdi. Artıq `ResponseStatusException` atsan belə, standart Problem Details formasında gəlir.

**Laravel-in sadəlik yönü.** Laravel komandası öz formasını seçib (`{"message": "...", "errors": {...}}`) — bu forma çox layihədə istifadə olunur, Inertia.js və Laravel Blade ilə inteqrasiya var. RFC 7807-yə keçmək istəyən əl ilə yazmalıdır. Laravel "convention over configuration" fəlsəfəsi üçün öz formasını saxlayır.

**`ProblemDetail` nə üçün asan gəlir?** Spring `ErrorResponseException` interfeysi ilə exception-lar artıq `ProblemDetail` qaytara bilir. Yəni sən öz exception-ını `ErrorResponseException`-dan törətsən, Spring avtomatik uyğun cavabı generate edir. Laravel-də `render()` metodu eyni rol oynayır, amma format seçimi sənindir.

**`type` URI-lərin əhəmiyyəti.** RFC 7807-nin əsas ideyası — `type` URI-nin sənədlərə aparmasıdır. Client xətanı aldıqda URI-ni açıb "nədir bu, necə həll edim" oxuya bilsin. Bu, client-ə `switch (errorCode)` yazma ehtiyacını aradan qaldırır — sadəcə `type` URI-yə bax.

**Validation error strukturu.** Laravel `{"errors": {"field": ["msg1", "msg2"]}}` formasını seçib. Spring Problem Details `{"errors": [{"field": "...", "message": "..."}]}` (array of objects) seçir. İkincisi client üçün daha asan iterate olunur, amma birincisi sahə-əsaslı göstərmək üçün daha rahatdır.

**Geri uyğunluq.** Spring-də köhnə custom DTO üslubu hələ də işləyir — `ProblemDetail` opt-in. Laravel-də isə Problem Details qəbul edəndə köhnə client-ləri qıra bilərsən (`errors` key yerdəyişə bilər). Buna görə Laravel-də çox vaxt Problem Details yalnız V2 API üçün istifadə olunur.

---

## Hansı framework-də var, hansında yoxdur?

**Yalnız Spring-də:**
- Built-in `ProblemDetail` sinfi (Spring 6+)
- Auto-enable flag `spring.mvc.problemdetails.enabled`
- `ErrorResponseException` interfeysi — exception direct Problem Details çevrilir
- `ResponseEntityExceptionHandler`-in standart xəta handler-ləri
- Avtomatik `application/problem+json` content-type
- Jackson-a inteqrasiya (ProblemDetail serialization out-of-box)
- WebFlux-da eyni sinif (reactive dəstək)

**Yalnız Laravel-də:**
- Exception-da `render()` metodu — render məsuliyyəti exception-a verilir (Spring daha ControllerAdvice-əsaslı)
- `report()` metodu — loglamaq üçün ayrıca hook
- `$dontReport`, `$dontFlash` arrays — Laravel-ə xas konfiqurasiya
- Accept header-ə avtomatik reaksiya (`$request->expectsJson()`)
- Inertia.js ilə xüsusi inteqrasiya (redirect-back-with-errors)

**Hər ikisində var:**
- Global exception handler (`@ControllerAdvice` vs `withExceptions`)
- Per-exception handler
- Custom exception sinifləri
- Status code və body-ni idarə etmək

---

## Best Practices

**Hər xəta tipi üçün sabit `type` URI istifadə et.** `type` URI dəyişsə, client yazılmış mantiqdan ayrılır. URI çap ediləndən sonra dəyişməməlidir — `title` da eyni.

**`detail` ilə `title` fərqini anla.** `title` human-readable və dəyişməzdir ("Order Not Found"). `detail` — konkret hadisə üçün ("Order 42 not found"). Logda `type` və `status` axtar.

**`traceId` extension əlavə et.** Distributed tracing ID-ni cavaba əlavə edərsən, client dəstək komandasına ID göndərər, sən log-larda tapa biləsən.

**Security: internal detail-i client-ə göndərmə.** Stack trace, SQL query, internal ID-lər production-da gizlət. Yalnız `traceId` qaytar ki, log-da axtarasan.

**Field errors — array format seç.** `[{"field": "x", "message": "..."}]` — client üçün iterate etmək asan. Laravel default `{"x": ["..."]}` formasını dəyişmək istəsən, client tərəfini də dəyişməlisən.

**Instance URI — xəta üçün unique.** Mümkündürsə, `instance` sahəyə hadisəyə aid ID qoy (məs. `/orders/42/errors/uuid`) — beləcə dəstək komandası spesifik hadisəni tapa bilər.

**Hər `type` URI-yə aid sənəd hazırla.** URI açılan kimi nə baş verdiyini, necə həll olunmasını izah edən səhifə olsun. Məsələn: `/problems/insufficient-funds` → "Retry-adable: no. Solution: add funds to account."

**Content-Type-a diqqət.** Proxy və gateway-lər `application/json` ilə fərqli davranır. `application/problem+json` istifadə edəndə monitoring tool-lar xətanı ayıra bilir.

**Spring-də `ErrorResponseException` törət.** Hər custom exception `ErrorResponseException` törəsə, handler override lazım deyil — Spring avtomatik Problem Details qaytarır.

**Laravel-də `render()` metodu yaz.** Exception-a `render()` əlavə edərsən, `bootstrap/app.php`-ə əl ilə qeyd etməyə ehtiyac qalmır.

**Versioning.** Problem Details yeni API versiyasında əlavə et (V2). Köhnə client-ləri qırmamaq üçün V1-də köhnə formanı saxla.

---

## Yekun

Problem Details (RFC 7807/9457) — REST API xətalarının standart formasıdır. `type`, `title`, `status`, `detail`, `instance` və extensions verir. Client bu sənədi bilməyə ehtiyac duymur — hər servis eyni formada cavab verir.

**Spring 6+** bu standartı birinci-dərəcəli dəstəkləyir: `ProblemDetail` sinfi, auto-enable flag, `ErrorResponseException` interfeysi. `spring.mvc.problemdetails.enabled=true` yaz, hər exception avtomatik standart formada gələcək. Custom exception üçün `ErrorResponseException` törət — artıq `@ExceptionHandler` yazmağa ehtiyac qalmır.

**Laravel 11+** Problem Details üçün daxili dəstək vermir. Laravel öz "convention" formasını saxlayır (`{"message": "...", "errors": {...}}`). RFC 7807 lazımsa, `bootstrap/app.php` `withExceptions` içində hər exception üçün əl ilə qur, yaxud exception-larda `render()` metodu ilə ProblemDetail qaytar. Helper DTO + enum ilə təkrarı azaltmaq olar.

Seçim: Spring-lə mikroservis ekosistemi qursan, Problem Details hər yerdə eyni olacaq — default seç. Laravel-də isə ilk layihədə qərar ver: standart Laravel formatı (daha sadə, Inertia-ya uyğun) yoxsa RFC 7807 (çoxlu client üçün daha strukturlu). Birini seç, son client-ə qədər dəyişmə.
