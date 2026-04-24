# Error Handling (Xeta idare etme)

> **Seviyye:** Beginner ⭐

## Giris

Xetalarin duzgun idare olunmasi tetbiqin etibarliligi ve istifadeci tecrubesi ucun son derece vacibdir. API istifadecileri anlasilir xeta mesajlari almali, inkisaf etdiriciler ise debug ucun kifayet qeder melumat gormelidir. Spring `@ExceptionHandler` ve `@ControllerAdvice` ile merkezlesmis xeta idare etme teklif edir, Laravel ise Exception Handler sinfi ve `abort()`, `render()`, `report()` metodlari ile isleyir.

## Spring-de istifadesi

### Xususi Exception sinifleri

```java
// Base exception
public class AppException extends RuntimeException {
    private final String errorCode;

    public AppException(String message, String errorCode) {
        super(message);
        this.errorCode = errorCode;
    }

    public String getErrorCode() {
        return errorCode;
    }
}

// 404 - tapilmadi
public class ResourceNotFoundException extends AppException {
    public ResourceNotFoundException(String resource, Object id) {
        super(resource + " tapilmadi: " + id, "NOT_FOUND");
    }
}

// 409 - konflikt
public class DuplicateResourceException extends AppException {
    public DuplicateResourceException(String message) {
        super(message, "DUPLICATE");
    }
}

// 422 - business rule violation
public class BusinessRuleException extends AppException {
    public BusinessRuleException(String message) {
        super(message, "BUSINESS_RULE_VIOLATION");
    }
}

// 403 - icaze yoxdur
public class ForbiddenException extends AppException {
    public ForbiddenException(String message) {
        super(message, "FORBIDDEN");
    }
}
```

### @ExceptionHandler - Controller seviyyesinde

```java
@RestController
@RequestMapping("/api/users")
public class UserController {

    private final UserService userService;

    @GetMapping("/{id}")
    public UserResponse getUser(@PathVariable Long id) {
        return UserResponse.from(userService.getUserById(id));
    }

    // Yalniz bu controller ucun isleyir
    @ExceptionHandler(ResourceNotFoundException.class)
    @ResponseStatus(HttpStatus.NOT_FOUND)
    public ErrorResponse handleNotFound(ResourceNotFoundException ex) {
        return new ErrorResponse(ex.getErrorCode(), ex.getMessage());
    }
}
```

### @ControllerAdvice - Qlobal xeta idaresi

```java
@RestControllerAdvice
public class GlobalExceptionHandler {

    private static final Logger log = LoggerFactory.getLogger(GlobalExceptionHandler.class);

    // 404 - Resurs tapilmadi
    @ExceptionHandler(ResourceNotFoundException.class)
    @ResponseStatus(HttpStatus.NOT_FOUND)
    public ErrorResponse handleNotFound(ResourceNotFoundException ex) {
        return new ErrorResponse(
            ex.getErrorCode(),
            ex.getMessage(),
            LocalDateTime.now()
        );
    }

    // 409 - Dublikat
    @ExceptionHandler(DuplicateResourceException.class)
    @ResponseStatus(HttpStatus.CONFLICT)
    public ErrorResponse handleDuplicate(DuplicateResourceException ex) {
        return new ErrorResponse(ex.getErrorCode(), ex.getMessage());
    }

    // 422 - Business qaydasi pozuldu
    @ExceptionHandler(BusinessRuleException.class)
    @ResponseStatus(HttpStatus.UNPROCESSABLE_ENTITY)
    public ErrorResponse handleBusinessRule(BusinessRuleException ex) {
        return new ErrorResponse(ex.getErrorCode(), ex.getMessage());
    }

    // 400 - Validasiya xetasi
    @ExceptionHandler(MethodArgumentNotValidException.class)
    @ResponseStatus(HttpStatus.BAD_REQUEST)
    public ValidationErrorResponse handleValidation(
            MethodArgumentNotValidException ex) {

        Map<String, String> errors = new HashMap<>();
        ex.getBindingResult().getFieldErrors().forEach(error ->
            errors.put(error.getField(), error.getDefaultMessage()));

        return new ValidationErrorResponse(
            "VALIDATION_ERROR",
            "Validasiya ugursuz oldu",
            errors
        );
    }

    // 400 - Tip uygunsuzlugu (meselen, String gonderildi, Long gozlenirdi)
    @ExceptionHandler(MethodArgumentTypeMismatchException.class)
    @ResponseStatus(HttpStatus.BAD_REQUEST)
    public ErrorResponse handleTypeMismatch(
            MethodArgumentTypeMismatchException ex) {
        String message = String.format(
            "'%s' parametri ucun '%s' deyeri uygun deyil",
            ex.getName(), ex.getValue());
        return new ErrorResponse("TYPE_MISMATCH", message);
    }

    // 405 - Method Not Allowed
    @ExceptionHandler(HttpRequestMethodNotSupportedException.class)
    @ResponseStatus(HttpStatus.METHOD_NOT_ALLOWED)
    public ErrorResponse handleMethodNotAllowed(
            HttpRequestMethodNotSupportedException ex) {
        return new ErrorResponse("METHOD_NOT_ALLOWED",
            ex.getMethod() + " metodu desteklenmir");
    }

    // 500 - Gozlenilmeyen xeta
    @ExceptionHandler(Exception.class)
    @ResponseStatus(HttpStatus.INTERNAL_SERVER_ERROR)
    public ErrorResponse handleGeneral(Exception ex) {
        log.error("Gozlenilmeyen xeta", ex);

        // Production-da detalli mesaj gosterme
        return new ErrorResponse(
            "INTERNAL_ERROR",
            "Daxili server xetasi bas verdi"
        );
    }
}
```

### Xeta cavab modelleri

```java
public record ErrorResponse(
    String errorCode,
    String message,
    LocalDateTime timestamp
) {
    public ErrorResponse(String errorCode, String message) {
        this(errorCode, message, LocalDateTime.now());
    }
}

public record ValidationErrorResponse(
    String errorCode,
    String message,
    Map<String, String> fieldErrors
) {}
```

### ResponseStatusException istifadesi

```java
@RestController
@RequestMapping("/api/products")
public class ProductController {

    // Xususi exception sinfi yaratmadan
    @GetMapping("/{id}")
    public Product getProduct(@PathVariable Long id) {
        return productRepository.findById(id)
            .orElseThrow(() -> new ResponseStatusException(
                HttpStatus.NOT_FOUND,
                "Mehsul tapilmadi: " + id
            ));
    }

    @PostMapping
    public Product createProduct(@RequestBody ProductDto dto) {
        if (productRepository.existsBySku(dto.getSku())) {
            throw new ResponseStatusException(
                HttpStatus.CONFLICT,
                "Bu SKU artiq movcuddur: " + dto.getSku()
            );
        }
        return productRepository.save(mapToProduct(dto));
    }
}
```

### ProblemDetail (RFC 7807) - Spring 6+

```java
@RestControllerAdvice
public class ProblemDetailExceptionHandler {

    @ExceptionHandler(ResourceNotFoundException.class)
    public ProblemDetail handleNotFound(ResourceNotFoundException ex) {
        ProblemDetail problem = ProblemDetail.forStatusAndDetail(
            HttpStatus.NOT_FOUND, ex.getMessage());
        problem.setTitle("Resurs tapilmadi");
        problem.setType(URI.create("https://api.example.com/errors/not-found"));
        problem.setProperty("errorCode", ex.getErrorCode());
        problem.setProperty("timestamp", Instant.now());
        return problem;
    }
}

// Cavab formati (RFC 7807):
// {
//   "type": "https://api.example.com/errors/not-found",
//   "title": "Resurs tapilmadi",
//   "status": 404,
//   "detail": "Istifadeci tapilmadi: 42",
//   "errorCode": "NOT_FOUND",
//   "timestamp": "2026-04-11T10:30:00Z"
// }
```

## Laravel-de istifadesi

### Xususi Exception sinifleri

```php
// php artisan make:exception ResourceNotFoundException

class ResourceNotFoundException extends \Exception
{
    public function __construct(
        string $resource,
        int|string $id,
        public string $errorCode = 'NOT_FOUND'
    ) {
        parent::__construct("{$resource} tapilmadi: {$id}");
    }

    // Xetanin HTTP cavabini mueyyen etmek
    public function render(Request $request)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'error_code' => $this->errorCode,
                'message' => $this->getMessage(),
            ], 404);
        }

        return response()->view('errors.404', [
            'message' => $this->getMessage(),
        ], 404);
    }

    // Xetanin loqlara yazilma qaydasi
    public function report(): bool
    {
        // false qaytarsa, default logging istifade olunur
        // true qaytarsa, ozumuz idare edirik ve default logging dayandrilir
        Log::warning('Resurs tapilmadi', [
            'message' => $this->getMessage(),
        ]);

        return true; // Default reporting dayandrilir
    }
}
```

```php
// Diger xususilesmis exception-lar
class BusinessRuleException extends \Exception
{
    public function __construct(
        string $message,
        public string $errorCode = 'BUSINESS_RULE_VIOLATION'
    ) {
        parent::__construct($message);
    }

    public function render(Request $request)
    {
        return response()->json([
            'error_code' => $this->errorCode,
            'message' => $this->getMessage(),
        ], 422);
    }
}

class DuplicateResourceException extends \Exception
{
    public function render(Request $request)
    {
        return response()->json([
            'error_code' => 'DUPLICATE',
            'message' => $this->getMessage(),
        ], 409);
    }

    public function report(): bool
    {
        return false; // Loqlara yazmaga ehtiyac yoxdur
    }
}
```

### Exception Handler (bootstrap/app.php - Laravel 11+)

```php
// bootstrap/app.php
use Illuminate\Foundation\Configuration\Exceptions;

return Application::configure(basePath: dirname(__DIR__))
    ->withExceptions(function (Exceptions $exceptions) {

        // Render - xetanin HTTP cavabini tanimlamaq
        $exceptions->render(function (ResourceNotFoundException $e, Request $request) {
            return response()->json([
                'error_code' => $e->errorCode,
                'message' => $e->getMessage(),
                'timestamp' => now()->toISOString(),
            ], 404);
        });

        // Validasiya xetalarinin formatini deyismek
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error_code' => 'VALIDATION_ERROR',
                    'message' => 'Validasiya ugursuz oldu',
                    'errors' => $e->errors(),
                ], 422);
            }
        });

        // Model tapilmadiqda
        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if ($request->expectsJson()) {
                $model = class_basename($e->getModel());
                return response()->json([
                    'error_code' => 'NOT_FOUND',
                    'message' => "{$model} tapilmadi",
                ], 404);
            }
        });

        // Report - xetanin loqlara/xidmetlere bildirilmesi
        $exceptions->report(function (PaymentException $e) {
            // Xususi bildiris xidmetine gonder
            Sentry::captureException($e);

            return false; // Default reporting dayandirma
        });

        // Bezi exception-lari loqlara YAZMA
        $exceptions->dontReport([
            ResourceNotFoundException::class,
            AuthorizationException::class,
        ]);
    })
    ->create();
```

### abort() helper funksiyasi

```php
class ProductController extends Controller
{
    public function show(int $id)
    {
        $product = Product::find($id);

        // Sade abort
        abort_if(!$product, 404, 'Mehsul tapilmadi');

        // abort_unless - eksine
        abort_unless($product->is_published, 403, 'Bu mehsul derc olunmayib');

        return new ProductResource($product);
    }

    public function update(Request $request, int $id)
    {
        $product = Product::findOrFail($id); // Avtomatik 404

        // Icaze yoxlamasi
        abort_if(
            $product->user_id !== auth()->id(),
            403,
            'Bu mehsulu redakte etmeye icazeniz yoxdur'
        );

        $product->update($request->validated());
        return new ProductResource($product);
    }

    public function delete(int $id)
    {
        $product = Product::findOrFail($id);

        // abort() ile xususi cavab
        if ($product->orders()->exists()) {
            abort(response()->json([
                'error_code' => 'HAS_ORDERS',
                'message' => 'Sifarisleri olan mehsulu silmek olmaz',
            ], 422));
        }

        $product->delete();
        return response()->noContent();
    }
}
```

### Reportable ve Renderable exception-lar

```php
// Reportable - xetani xarici xidmetlere bildir
class PaymentFailedException extends \Exception
{
    public function __construct(
        public Order $order,
        public string $gatewayError,
        string $message = 'Odenish ugursuz oldu'
    ) {
        parent::__construct($message);
    }

    public function report(): void
    {
        Log::error('Odenish ugursuz oldu', [
            'order_id' => $this->order->id,
            'gateway_error' => $this->gatewayError,
            'user_id' => $this->order->user_id,
        ]);

        // Slack-a bildiris gonder
        Notification::route('slack', config('services.slack.webhook'))
            ->notify(new PaymentFailedNotification($this->order));
    }

    public function render(Request $request)
    {
        return response()->json([
            'error_code' => 'PAYMENT_FAILED',
            'message' => $this->getMessage(),
            'order_id' => $this->order->id,
        ], 402);
    }
}
```

### Xeta sehifeleri (Web ucun)

```php
// resources/views/errors/404.blade.php
@extends('layouts.app')

@section('content')
<div class="error-page">
    <h1>404</h1>
    <p>{{ $exception->getMessage() ?: 'Sehife tapilmadi' }}</p>
    <a href="{{ url('/') }}">Ana sehifeye qayit</a>
</div>
@endsection

// resources/views/errors/500.blade.php
@extends('layouts.app')

@section('content')
<div class="error-page">
    <h1>500</h1>
    <p>Daxili server xetasi bas verdi. Zehmet olmasa sonra yeniden ceahd edin.</p>
</div>
@endsection
```

### Context elave etmek

```php
class OrderService
{
    public function processOrder(Order $order): void
    {
        try {
            $this->chargePayment($order);
        } catch (PaymentException $e) {
            // Xetaya elave kontekst elave etmek
            throw new PaymentFailedException(
                $order,
                $e->getMessage()
            );
        }
    }
}

// Laravel 11+ ile kontekst elave etmek
class InsufficientStockException extends \Exception
{
    public function context(): array
    {
        return [
            'product_id' => $this->productId,
            'requested' => $this->requested,
            'available' => $this->available,
        ];
    }
}
```

## Esas ferqler

| Xususiyyet | Spring | Laravel |
|---|---|---|
| **Qlobal handler** | `@ControllerAdvice` | `withExceptions()` (bootstrap/app.php) |
| **Controller-de handler** | `@ExceptionHandler` metod | Exception sinifinde `render()` |
| **HTTP status** | `@ResponseStatus` annotasiya | `abort()`, `response()->json(..., 404)` |
| **Validasiya xetasi** | `MethodArgumentNotValidException` | `ValidationException` |
| **Logging/Reporting** | Manual (handler-de) | `report()` metodu exception-da |
| **Sade abort** | `ResponseStatusException` | `abort(404)`, `abort_if()` |
| **Xeta formati** | ProblemDetail (RFC 7807) | Xususi JSON format |
| **Xeta sehifeleri** | Thymeleaf error templates | Blade error views |
| **Xarici bildiris** | Manual | `report()` + Notification |

## Niye bele ferqler var?

**Spring-in yanasmasi:** Spring `@ControllerAdvice` ile butun tetbiqdeki xetalari bir yerde toplamaq imkanini verir. Bu Java-nin checked/unchecked exception sistemi ile uygunlasdiriliib. `@ExceptionHandler` AOP esasli isleyir - proxy metod cagrisini intercept edir. ProblemDetail (RFC 7807) standarti API xetalarinin vahid formatda qaytarilmasini temin edir.

**Laravel-in yanasmasi:** Laravel her Exception sinifine `render()` ve `report()` metodlari elave etmeye imkan verir - xeta ozue nece gosterileceyini ve nece loqlanacagini bilir. Bu Object-Oriented yanasmadiar. Bundan elave, `abort()` helper funksiyasi ile isteniley yerde kodu dayandirmaq ve HTTP xeta qaytarmaq mumkundur - bu, controller kodunu coxlu sadeledsdirir.

**render() vs @ExceptionHandler:** Spring-de xeta idare etme metiqi handler sinifinde merkezlesmis olur. Laravel-de ise her exception sinifi ozue nece render olunacagini bileae biler. Her iki yanasman artiq ceheti var - Spring-de her sey bir yerdedir ve asanliqla gorunur, Laravel-de ise exception sinfi tam ozunu-idarae edici (self-contained) olur.

## Hansi framework-de var, hansinda yoxdur?

- **ProblemDetail (RFC 7807)** - Yalniz Spring-de (Spring 6+). Standart xeta formati.
- **`abort()` / `abort_if()` / `abort_unless()`** - Yalniz Laravel-de. Isteniley yerde kodu dayandirmaq ucun helper-ler.
- **`report()` metodu exception-da** - Yalniz Laravel-de. Xetanin ozue nece loqlanacagini bilmesi.
- **`render()` metodu exception-da** - Yalniz Laravel-de. Xetanin ozue HTTP cavabini mueyyen etmesi.
- **`dontReport()`** - Laravel-de mueyyen exception-larin loqlara yazilmasinin qarshsini almaq.
- **`@ControllerAdvice`** - Yalniz Spring-de. Bir sinifde butun exception handler-leri toplamaq.
- **`@ResponseStatus` annotasiya** - Yalniz Spring-de. Exception sinifine HTTP status kodu vermek.
- **`findOrFail()`** - Laravel Eloquent-de avtomatik 404 atma. Spring-de manual `orElseThrow()` yazmaq lazimdir.
- **Xeta sehifeleri konvensiyasi** - Laravel-de `resources/views/errors/404.blade.php` fayli avtomatik 404 sehifesi olur. Spring-de manual konfiqurasiya lazimdir.
- **`context()` metodu** - Laravel 11-de exception-a elave melumat elave etmek ucun.
