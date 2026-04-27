# Error Handling və Exceptions - Laravel/PHP (Junior)

## Mündəricat
1. PHP Throwable Hierarchy
2. try/catch/finally - Tam nümunələr
3. Custom Exception Classes
4. Exception Chaining
5. Global Error Handlers
6. Laravel Handler (App\Exceptions\Handler)
7. HTTP Exceptions
8. Sentry/Bugsnag Integration
9. Exception Enrichment
10. Railway-Oriented Programming / Result Pattern
11. Problem Details RFC 7807
12. Graceful Degradation
13. Real-world: Payment Exception Hierarchy
14. İntervyu Sualları

---

## 1. PHP Throwable Hierarchy

PHP 7+ versiyasından etibarən bütün error və exception-lar `Throwable` interface-ini implement edir. Bu, `catch (Throwable $e)` ilə hər şeyi tutmağa imkan verir.

```
Throwable (interface)
│
├── Error (class) — PHP engine-dən gələn fatal errors
│   ├── ArithmeticError
│   │   └── DivisionByZeroError
│   ├── AssertionError
│   ├── ParseError          — syntax xətaları (eval() zamanı)
│   ├── TypeError           — yanlış tip ötürülməsi
│   ├── ValueError          — düzgün tipdə, amma yanlış dəyər
│   ├── UnhandledMatchError — match expression exhaustive deyil
│   └── FiberError          — Fiber əməliyyatları zamanı
│
└── Exception (class) — application-level exceptions
    ├── BadFunctionCallException
    │   └── BadMethodCallException
    ├── DomainException     — biznes qaydalarının pozulması
    ├── InvalidArgumentException
    ├── LengthException
    ├── LogicException
    ├── OutOfRangeException
    ├── OverflowException
    ├── RangeException
    ├── RuntimeException    — runtime zamanı baş verən xətalar
    │   ├── OutOfBoundsException
    │   ├── OverflowException
    │   ├── UnderflowException
    │   └── UnexpectedValueException
    └── InvalidArgumentException
```

### Error vs Exception fərqi

*Error vs Exception fərqi üçün kod nümunəsi:*
```php
<?php

// Exception — proqramçı tərəfindən throw edilir
try {
    throw new InvalidArgumentException("Yanlış arqument");
} catch (InvalidArgumentException $e) {
    echo $e->getMessage(); // "Yanlış arqument"
}

// Error — PHP engine tərəfindən yaranır
try {
    $result = 10 / 0; // Bu DivisionByZeroError deyil (0.0 qaytarır)
    intdiv(10, 0);     // Bu DivisionByZeroError throw edir
} catch (DivisionByZeroError $e) {
    echo "Sıfıra bölmə: " . $e->getMessage();
}

// TypeError — tip uyğunsuzluğu
function multiply(int $a, int $b): int
{
    return $a * $b;
}

try {
    multiply("salam", 5); // strict_types=1 olduqda TypeError
} catch (TypeError $e) {
    echo "Tip xətası: " . $e->getMessage();
}

// Hər şeyi tutmaq üçün Throwable
try {
    // istənilən xəta
    throw new Error("Engine xətası");
} catch (Throwable $e) {
    echo get_class($e) . ": " . $e->getMessage();
}
```

---

## 2. try/catch/finally — Tam Nümunələr

### Əsas struktur və execution order

*Əsas struktur və execution order üçün kod nümunəsi:*
```php
<?php

function processOrder(int $orderId): string
{
    echo "1. processOrder başladı\n";

    try {
        echo "2. try bloku başladı\n";

        if ($orderId <= 0) {
            throw new InvalidArgumentException("Order ID müsbət olmalıdır");
        }

        echo "3. İşlənir...\n";
        return "4. try-dan return (finally-dən əvvəl çalışır)\n";

    } catch (InvalidArgumentException $e) {
        echo "5. catch bloku: " . $e->getMessage() . "\n";
        return "6. catch-dan return\n";

    } finally {
        // finally HƏMİŞƏ çalışır: return, exception, hər şeydən sonra
        echo "7. finally bloku (həmişə çalışır)\n";
    }
}

// Nəticə orderId=5 üçün:
// 1. processOrder başladı
// 2. try bloku başladı
// 3. İşlənir...
// 7. finally bloku (həmişə çalışır)
// 4. try-dan return (finally-dən əvvəl çalışır)

// Nəticə orderId=-1 üçün:
// 1. processOrder başladı
// 2. try bloku başladı
// 5. catch bloku: Order ID müsbət olmalıdır
// 7. finally bloku (həmişə çalışır)
// 6. catch-dan return
```

### Çoxlu catch blokları

*Çoxlu catch blokları üçün kod nümunəsi:*
```php
<?php

function fetchUserData(int $userId): array
{
    try {
        $user = findUser($userId);        // NotFoundException ata bilər
        $perms = checkPermissions($user); // AuthorizationException ata bilər
        $data = loadProfile($user);       // RuntimeException ata bilər

        return $data;

    } catch (NotFoundException $e) {
        // Spesifik exception — daha yuxarıda olmalıdır
        Log::warning("İstifadəçi tapılmadı", ['user_id' => $userId]);
        return [];

    } catch (AuthorizationException $e) {
        Log::info("İcazə yoxdur", ['user_id' => $userId]);
        throw $e; // yenidən throw et

    } catch (RuntimeException | DatabaseException $e) {
        // PHP 8+ — çoxlu tip bir catch-də (union catch)
        Log::error("Infrastruktur xətası", [
            'exception' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        throw new ServiceUnavailableException("Xidmət müvəqqəti əlçatmazdır", 0, $e);

    } finally {
        // Resource-ları buraxmaq üçün
        releaseConnection();
    }
}
```

### finally-nin return üzərindəki təsiri

*finally-nin return üzərindəki təsiri üçün kod nümunəsi:*
```php
<?php

// DİQQƏT: finally-də return try/catch-in return-ünü əzir!
function dangerousReturn(): string
{
    try {
        return "try-dan dəyər";
    } finally {
        return "finally-dən dəyər"; // BU qaytarılır!
    }
}

echo dangerousReturn(); // "finally-dən dəyər"

// finally exception-u da əzə bilər
function dangerousException(): void
{
    try {
        throw new RuntimeException("Orijinal xəta");
    } finally {
        throw new LogicException("Finally xətası"); // orijinal itir!
    }
}
```

---

## 3. Custom Exception Classes

### Exception Hierarchy dizaynı

Yaxşı layihədə exception-lar domain-ə görə qruplaşdırılır. Bu, `catch` bloklarını daha mənalı edir.

*Yaxşı layihədə exception-lar domain-ə görə qruplaşdırılır. Bu, `catch` üçün kod nümunəsi:*
```php
<?php

namespace App\Exceptions;

// ── Base application exception ──────────────────────────────────────────────
abstract class AppException extends \RuntimeException
{
    protected string $errorCode = 'APP_ERROR';
    protected array $context = [];

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function withContext(array $context): static
    {
        $clone = clone $this;
        $clone->context = array_merge($this->context, $context);
        return $clone;
    }
}

// ── Domain layer exceptions ──────────────────────────────────────────────────
class DomainException extends AppException
{
    protected string $errorCode = 'DOMAIN_ERROR';
}

class BusinessRuleViolationException extends DomainException
{
    protected string $errorCode = 'BUSINESS_RULE_VIOLATION';

    public static function because(string $reason, array $context = []): static
    {
        $e = new static($reason);
        $e->context = $context;
        return $e;
    }
}

// ── Validation exceptions ─────────────────────────────────────────────────────
class ValidationException extends AppException
{
    protected string $errorCode = 'VALIDATION_ERROR';
    private array $errors;

    public function __construct(array $errors, string $message = "Validasiya uğursuz oldu")
    {
        parent::__construct($message);
        $this->errors = $errors;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public static function withErrors(array $errors): static
    {
        return new static($errors);
    }
}

// ── Not found exceptions ──────────────────────────────────────────────────────
class NotFoundException extends AppException
{
    protected string $errorCode = 'NOT_FOUND';

    public static function forModel(string $model, int|string $id): static
    {
        return new static("{$model} tapılmadı: {$id}");
    }

    public static function forResource(string $resource): static
    {
        return new static("{$resource} mövcud deyil");
    }
}

class UserNotFoundException extends NotFoundException
{
    protected string $errorCode = 'USER_NOT_FOUND';
}

class OrderNotFoundException extends NotFoundException
{
    protected string $errorCode = 'ORDER_NOT_FOUND';
}

// ── Authorization exceptions ──────────────────────────────────────────────────
class AuthorizationException extends AppException
{
    protected string $errorCode = 'AUTHORIZATION_ERROR';
    private ?string $requiredPermission;

    public function __construct(
        string $message = "Bu əməliyyat üçün icazəniz yoxdur",
        ?string $requiredPermission = null
    ) {
        parent::__construct($message);
        $this->requiredPermission = $requiredPermission;
    }

    public function getRequiredPermission(): ?string
    {
        return $this->requiredPermission;
    }
}

// ── Infrastructure exceptions ─────────────────────────────────────────────────
class InfrastructureException extends AppException
{
    protected string $errorCode = 'INFRASTRUCTURE_ERROR';
}

class DatabaseException extends InfrastructureException
{
    protected string $errorCode = 'DATABASE_ERROR';
}

class CacheException extends InfrastructureException
{
    protected string $errorCode = 'CACHE_ERROR';
}

class ExternalServiceException extends InfrastructureException
{
    protected string $errorCode = 'EXTERNAL_SERVICE_ERROR';
    private string $service;

    public function __construct(string $service, string $message, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->service = $service;
    }

    public function getService(): string
    {
        return $this->service;
    }
}
```

---

## 4. Exception Chaining ($previous) — Stack Trace-i Qorumaq

Exception chaining — aşağı səviyyəli xətanı yuxarı səviyyəyə ötürərkən orijinal stack trace-i itirməmək üçün istifadə edilir.

*Exception chaining — aşağı səviyyəli xətanı yuxarı səviyyəyə ötürərkən üçün kod nümunəsi:*
```php
<?php

class UserRepository
{
    public function findById(int $id): User
    {
        try {
            // PDO xətası throw edə bilər
            $row = $this->db->query("SELECT * FROM users WHERE id = ?", [$id]);

            if (!$row) {
                throw new UserNotFoundException("İstifadəçi tapılmadı: {$id}");
            }

            return User::fromRow($row);

        } catch (\PDOException $e) {
            // PDOException-ı birbaşa expose etmirik (leaking)
            // Əvəzinə domain exception-a wrap edirik, amma $previous ilə orijinalı qoruyuruq
            throw new DatabaseException(
                "İstifadəçi bazadan oxuna bilmədi",
                0,
                $e  // ← $previous: orijinal xəta qorunur
            );
        }
    }
}

class UserService
{
    public function getUser(int $id): User
    {
        try {
            return $this->repository->findById($id);
        } catch (DatabaseException $e) {
            // Stack trace-i debug üçün qoruyuruq
            throw new ServiceUnavailableException(
                "İstifadəçi xidməti müvəqqəti əlçatmazdır",
                503,
                $e  // ← DatabaseException saxlanılır
            );
        }
    }
}

// Log-da bütün chain görünür:
// ServiceUnavailableException: İstifadəçi xidməti müvəqqəti əlçatmazdır
//   Previous: DatabaseException: İstifadəçi bazadan oxuna bilmədi
//     Previous: PDOException: SQLSTATE[42S02]: Base table not found

// Chain-i iterate etmək
function getAllExceptionMessages(\Throwable $e): array
{
    $messages = [];
    $current = $e;

    while ($current !== null) {
        $messages[] = [
            'class'   => get_class($current),
            'message' => $current->getMessage(),
            'file'    => $current->getFile(),
            'line'    => $current->getLine(),
        ];
        $current = $current->getPrevious();
    }

    return $messages;
}
```

---

## 5. Global Error Handlers

### set_exception_handler

*set_exception_handler üçün kod nümunəsi:*
```php
<?php

// Laravel-dən kənar PHP proyektləri üçün
set_exception_handler(function (\Throwable $exception) {
    $statusCode = match (true) {
        $exception instanceof NotFoundException        => 404,
        $exception instanceof AuthorizationException  => 403,
        $exception instanceof ValidationException     => 422,
        $exception instanceof InfrastructureException => 503,
        default                                        => 500,
    };

    http_response_code($statusCode);

    if (isApiRequest()) {
        header('Content-Type: application/json');
        echo json_encode([
            'error' => [
                'code'    => $exception instanceof AppException
                    ? $exception->getErrorCode()
                    : 'INTERNAL_ERROR',
                'message' => $exception->getMessage(),
            ],
        ]);
    } else {
        include "views/error.php";
    }

    // Log et
    error_log($exception->__toString());
    exit(1);
});
```

### set_error_handler — köhnə PHP error-larını tutmaq

*set_error_handler — köhnə PHP error-larını tutmaq üçün kod nümunəsi:*
```php
<?php

// PHP notice, warning, deprecated — bunlar default olaraq exception deyil
set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline): bool {
    // E_NOTICE, E_WARNING, E_DEPRECATED, etc.
    if (!(error_reporting() & $errno)) {
        // Bu xəta error_reporting ilə deaktivdir, ignore et
        return false;
    }

    throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
});

// İndi PHP warning-lər də exception kimi tutulur
try {
    $arr = [];
    $val = $arr['mövcud_olmayan_açar']; // PHP Notice
} catch (\ErrorException $e) {
    echo "Tutuldu: " . $e->getMessage();
}
```

### register_shutdown_function — fatal error-ları tutmaq

*register_shutdown_function — fatal error-ları tutmaq üçün kod nümunəsi:*
```php
<?php

// Fatal error-lar (memory limit, etc.) set_error_handler ilə tutulmur
register_shutdown_function(function () {
    $error = error_get_last();

    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        // Fatal xəta baş verdi
        $message = sprintf(
            "Fatal Error: %s in %s on line %d",
            $error['message'],
            $error['file'],
            $error['line']
        );

        error_log($message);

        // Lazım olarsa HTTP response göndər
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Server daxili xətası']);
        }
    }
});
```

---

## 6. Laravel Handler: App\Exceptions\Handler

Laravel-in exception handling mərkəzi `app/Exceptions/Handler.php` faylıdır. Laravel 10+ versiyasında `register()` metodunda bütün konfiqurasiya edilir.

*Laravel-in exception handling mərkəzi `app/Exceptions/Handler.php` fay üçün kod nümunəsi:*
```php
<?php

namespace App\Exceptions;

use App\Exceptions\{
    AuthorizationException,
    NotFoundException,
    ValidationException as AppValidationException,
    ExternalServiceException,
};
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * Bu exception-lar log-a yazılmır.
     * Məsələn: 404-lər log-u çirkləndirər.
     */
    protected $dontReport = [
        AuthorizationException::class,
        NotFoundException::class,
        \Illuminate\Auth\AuthenticationException::class,
        \Illuminate\Validation\ValidationException::class,
        \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
        \Symfony\Component\HttpKernel\Exception\HttpException::class,
    ];

    /**
     * Bu field adları log-a yazılmadan əvvəl məlumatdan çıxarılır.
     * Sensitive data-nı protect edirik.
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
        'credit_card',
        'cvv',
        'card_number',
    ];

    public function register(): void
    {
        // ── reportable: exception-u necə log edəcəyimizi təyin edirik ──────────
        $this->reportable(function (ExternalServiceException $e) {
            // Xarici servis xətaları üçün əlavə context
            Log::error("Xarici servis xətası", [
                'service'   => $e->getService(),
                'message'   => $e->getMessage(),
                'exception' => $e,
            ]);

            // false qaytararaq default report-u dayandırırıq
            return false;
        });

        $this->reportable(function (AppValidationException $e) {
            // Validation xətaları report edilməsin (warning deyil, normal axış)
            return false;
        });

        // ── renderable: exception-u HTTP response-a çevirməyi təyin edirik ────
        $this->renderable(function (NotFoundException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => [
                        'type'    => 'not_found',
                        'message' => $e->getMessage(),
                        'code'    => $e->getErrorCode(),
                    ],
                ], 404);
            }
            // web üçün view
            return response()->view('errors.404', ['message' => $e->getMessage()], 404);
        });

        $this->renderable(function (AppValidationException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => [
                        'type'   => 'validation_error',
                        'errors' => $e->getErrors(),
                    ],
                ], 422);
            }
        });

        $this->renderable(function (AuthorizationException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => [
                        'type'    => 'forbidden',
                        'message' => $e->getMessage(),
                    ],
                ], 403);
            }
        });

        // Laravel-in ModelNotFoundException-ını bizim NotFoundException-a çeviririk
        $this->renderable(function (ModelNotFoundException $e, Request $request) {
            $model = class_basename($e->getModel());
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => [
                        'type'    => 'not_found',
                        'message' => "{$model} tapılmadı",
                    ],
                ], 404);
            }
        });

        // Laravel Validation Exception
        $this->renderable(function (ValidationException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => [
                        'type'    => 'validation_error',
                        'message' => $e->getMessage(),
                        'errors'  => $e->errors(),
                    ],
                ], 422);
            }
        });
    }
}
```

### API vs Web Response strategiyası

*API vs Web Response strategiyası üçün kod nümunəsi:*
```php
<?php

// Handler-ə əlavə metod: bütün unhandled exception-lar üçün
// (render metodu override)
public function render($request, Throwable $e)
{
    // İlk öncə parent-in renderable-larını yoxla
    if ($response = $this->renderExceptionResponse($request, $e)) {
        return $response;
    }

    if ($request->expectsJson() || $request->is('api/*')) {
        return $this->renderApiException($request, $e);
    }

    return parent::render($request, $e);
}

private function renderApiException(Request $request, Throwable $e): JsonResponse
{
    $statusCode = $this->getStatusCode($e);

    $body = [
        'error' => [
            'type'      => $this->getErrorType($e),
            'message'   => $this->isProduction()
                ? $this->getPublicMessage($e, $statusCode)
                : $e->getMessage(),
        ],
    ];

    // Development mühitdə debug məlumatı əlavə edirik
    if (config('app.debug')) {
        $body['debug'] = [
            'exception' => get_class($e),
            'file'      => $e->getFile(),
            'line'      => $e->getLine(),
            'trace'     => collect($e->getTrace())->take(10)->toArray(),
        ];
    }

    return response()->json($body, $statusCode);
}

private function getStatusCode(Throwable $e): int
{
    return match (true) {
        $e instanceof HttpException              => $e->getStatusCode(),
        $e instanceof NotFoundException          => 404,
        $e instanceof AuthorizationException     => 403,
        $e instanceof ValidationException        => 422,
        $e instanceof AuthenticationException    => 401,
        $e instanceof InfrastructureException    => 503,
        default                                  => 500,
    };
}
```

---

## 7. HTTP Exceptions: abort(), abort_if(), abort_unless()

*7. HTTP Exceptions: abort(), abort_if(), abort_unless() üçün kod nümunəsi:*
```php
<?php

// abort() — birbaşa HTTP exception throw edir
Route::get('/admin', function () {
    if (!auth()->user()->isAdmin()) {
        abort(403, 'Admin panelə girişiniz yoxdur');
    }
    // ...
});

// abort_if() — şərt doğrudursa abort et
Route::get('/users/{id}', function (int $id) {
    $user = User::find($id);

    abort_if($user === null, 404, 'İstifadəçi tapılmadı');
    abort_if(!auth()->user()->can('view', $user), 403);

    return response()->json($user);
});

// abort_unless() — şərt yanlışdırsa abort et (abort_if-in tərsi)
Route::delete('/posts/{id}', function (int $id) {
    $post = Post::findOrFail($id);

    abort_unless(auth()->user()->can('delete', $post), 403, 'Silmə icazəniz yoxdur');
    abort_unless($post->isDeletable(), 422, 'Bu post silinə bilməz');

    $post->delete();
    return response()->noContent();
});

// Custom HTTP Exception class
use Symfony\Component\HttpKernel\Exception\HttpException;

class TooManyRequestsException extends HttpException
{
    public function __construct(private int $retryAfter)
    {
        parent::__construct(429, 'Çox sayda sorğu göndərdiniz');
    }

    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }
}

// Handler-də render
$this->renderable(function (TooManyRequestsException $e) {
    return response()->json([
        'error' => [
            'type'        => 'rate_limit_exceeded',
            'message'     => $e->getMessage(),
            'retry_after' => $e->getRetryAfter(),
        ],
    ], 429)->header('Retry-After', $e->getRetryAfter());
});
```

---

## 8. Sentry/Bugsnag Integration

*8. Sentry/Bugsnag Integration üçün kod nümunəsi:*
```php
<?php
// config/logging.php — Sentry channel əlavə etmək

return [
    'channels' => [
        'stack' => [
            'driver'   => 'stack',
            'channels' => ['daily', 'sentry'],
        ],

        'sentry' => [
            'driver' => 'sentry',
            'level'  => 'error', // yalnız error+ səviyyəsindəkilər
        ],
    ],
];

// app/Exceptions/Handler.php — Sentry context enrichment
public function register(): void
{
    $this->reportable(function (Throwable $e) {
        if (app()->bound('sentry')) {
            \Sentry\configureScope(function (\Sentry\State\Scope $scope) {
                // İstifadəçi məlumatı əlavə et
                if ($user = auth()->user()) {
                    $scope->setUser([
                        'id'    => $user->id,
                        'email' => $user->email,
                        'role'  => $user->role,
                    ]);
                }

                // Request context
                $scope->setTag('route', request()->route()?->getName() ?? 'unknown');
                $scope->setTag('tenant', tenant()?->id ?? 'global');
                $scope->setExtra('request_id', request()->header('X-Request-ID'));
            });

            app('sentry')->captureException($e);
        }
    });
}

// .env
// SENTRY_LARAVEL_DSN=https://xxx@sentry.io/yyy
// SENTRY_TRACES_SAMPLE_RATE=0.1  // 10% of requests üçün performance tracking
```

---

## 9. Exception Enrichment: withContext(), shareContext()

*9. Exception Enrichment: withContext(), shareContext() üçün kod nümunəsi:*
```php
<?php

// Handler-də bütün exception-lara context əlavə etmək
public function register(): void
{
    // shareContext — bütün report edilən exception-lara əlavə olunur
    $this->withContext([
        'app_version' => config('app.version'),
        'environment' => config('app.env'),
        'server'      => gethostname(),
    ]);

    // Request-ə əsasən context əlavə etmək
    if ($user = auth()->user()) {
        $this->withContext([
            'user_id'   => $user->id,
            'user_role' => $user->role,
            'tenant_id' => $user->tenant_id,
        ]);
    }
}

// Middleware-də request ID və correlation ID əlavə etmək
class RequestContextMiddleware
{
    public function handle(Request $request, \Closure $next): mixed
    {
        $requestId = $request->header('X-Request-ID') ?? (string) Str::uuid();

        // Response-a da əlavə edirik
        $response = $next($request);

        // Exception handler-ə context ver
        app(\App\Exceptions\Handler::class)->withContext([
            'request_id'     => $requestId,
            'correlation_id' => $request->header('X-Correlation-ID'),
            'user_agent'     => $request->userAgent(),
            'ip'             => $request->ip(),
            'url'            => $request->fullUrl(),
            'method'         => $request->method(),
        ]);

        return $response->header('X-Request-ID', $requestId);
    }
}

// Exception-un öz context-i
class OrderProcessingException extends DomainException
{
    public static function paymentFailed(Order $order, string $reason): static
    {
        $e = new static("Ödəniş uğursuz oldu: {$reason}");
        $e->context = [
            'order_id'     => $order->id,
            'order_total'  => $order->total,
            'currency'     => $order->currency,
            'payment_type' => $order->payment_type,
            'reason'       => $reason,
        ];
        return $e;
    }
}
```

---

## 10. Railway-Oriented Programming / Result Pattern

Bu pattern exception-ları tamamilə başqa cür idarə edir: xəta throw etmək əvəzinə `Result` obyekti qaytarır. Bu, functional programming-dən gəlir (Either monad).

*Bu pattern exception-ları tamamilə başqa cür idarə edir: xəta throw et üçün kod nümunəsi:*
```php
<?php

// Result type implementation
/**
 * @template T
 */
final class Result
{
    private function __construct(
        private readonly bool $success,
        private readonly mixed $value,
        private readonly ?string $error,
        private readonly ?string $errorCode,
    ) {}

    /**
     * @template U
     * @return self<U>
     */
    public static function ok(mixed $value): self
    {
        return new self(true, $value, null, null);
    }

    public static function fail(string $error, string $errorCode = 'ERROR'): self
    {
        return new self(false, null, $error, $errorCode);
    }

    public function isOk(): bool
    {
        return $this->success;
    }

    public function isFail(): bool
    {
        return !$this->success;
    }

    public function getValue(): mixed
    {
        if (!$this->success) {
            throw new \LogicException("Uğursuz Result-dan dəyər oxumaq olmaz");
        }
        return $this->value;
    }

    public function getError(): string
    {
        if ($this->success) {
            throw new \LogicException("Uğurlu Result-dan xəta oxumaq olmaz");
        }
        return $this->error;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode ?? 'ERROR';
    }

    /**
     * Uğurlu halda transform et, xəta halında keç
     * @template U
     */
    public function map(callable $fn): self
    {
        if ($this->isFail()) {
            return $this;
        }
        return self::ok($fn($this->value));
    }

    /**
     * Chain etmək: Result qaytaran funksiyanı bir-birinə bağla
     */
    public function flatMap(callable $fn): self
    {
        if ($this->isFail()) {
            return $this;
        }
        return $fn($this->value);
    }

    /**
     * Xəta halında alternativ dəyər ver
     */
    public function getOrElse(mixed $default): mixed
    {
        return $this->success ? $this->value : $default;
    }
}

// İstifadə nümunəsi
class UserRegistrationService
{
    public function register(array $data): Result
    {
        // Validation
        $validationResult = $this->validate($data);
        if ($validationResult->isFail()) {
            return $validationResult;
        }

        // Email mövcudluğunu yoxla
        $emailResult = $this->checkEmailUnique($data['email']);
        if ($emailResult->isFail()) {
            return $emailResult;
        }

        // İstifadəçi yarat
        return $this->createUser($data);
    }

    private function validate(array $data): Result
    {
        if (empty($data['email'])) {
            return Result::fail("Email tələb olunur", "VALIDATION_ERROR");
        }
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return Result::fail("Email formatı yanlışdır", "INVALID_EMAIL");
        }
        return Result::ok($data);
    }

    private function checkEmailUnique(string $email): Result
    {
        if (User::where('email', $email)->exists()) {
            return Result::fail("Bu email artıq qeydiyyatdan keçib", "EMAIL_TAKEN");
        }
        return Result::ok(true);
    }

    private function createUser(array $data): Result
    {
        try {
            $user = User::create($data);
            return Result::ok($user);
        } catch (\Exception $e) {
            return Result::fail("İstifadəçi yaradıla bilmədi", "DB_ERROR");
        }
    }
}

// Controller-də istifadə
class UserController
{
    public function register(Request $request): JsonResponse
    {
        $result = $this->service->register($request->all());

        if ($result->isFail()) {
            return response()->json([
                'error' => [
                    'code'    => $result->getErrorCode(),
                    'message' => $result->getError(),
                ],
            ], $this->statusForCode($result->getErrorCode()));
        }

        $user = $result->getValue();
        return response()->json(['data' => $user], 201);
    }
}

// Chaining nümunəsi (Railway pattern)
$result = Result::ok($request->all())
    ->flatMap(fn($data) => $this->validate($data))
    ->flatMap(fn($data) => $this->checkEmailUnique($data['email']))
    ->flatMap(fn($data) => $this->createUser($data))
    ->map(fn($user) => UserResource::make($user));

if ($result->isFail()) {
    return response()->json(['error' => $result->getError()], 422);
}

return response()->json(['data' => $result->getValue()], 201);
```

---

## 11. Problem Details RFC 7807 — Standart API Error Format

RFC 7807 API error response-larını standartlaşdırır. Content-Type: `application/problem+json`

*RFC 7807 API error response-larını standartlaşdırır. Content-Type: `ap üçün kod nümunəsi:*
```php
<?php

// Problem Details format:
// {
//   "type":     "https://api.example.com/errors/validation-failed",
//   "title":    "Validasiya uğursuz oldu",
//   "status":   422,
//   "detail":   "Email formatı düzgün deyil",
//   "instance": "/api/users/register",
//   "errors": { ... }   // extension fields
// }

class ProblemDetails
{
    public function __construct(
        private readonly string $type,
        private readonly string $title,
        private readonly int $status,
        private readonly ?string $detail = null,
        private readonly ?string $instance = null,
        private readonly array $extensions = [],
    ) {}

    public function toArray(): array
    {
        $problem = [
            'type'   => $this->type,
            'title'  => $this->title,
            'status' => $this->status,
        ];

        if ($this->detail !== null) {
            $problem['detail'] = $this->detail;
        }

        if ($this->instance !== null) {
            $problem['instance'] = $this->instance;
        }

        return array_merge($problem, $this->extensions);
    }

    public static function fromException(\Throwable $e, Request $request): self
    {
        [$type, $title, $status] = match (true) {
            $e instanceof ValidationException    => [
                'https://api.example.com/errors/validation-failed',
                'Validasiya Xətası',
                422,
            ],
            $e instanceof NotFoundException       => [
                'https://api.example.com/errors/not-found',
                'Resurs Tapılmadı',
                404,
            ],
            $e instanceof AuthorizationException  => [
                'https://api.example.com/errors/forbidden',
                'Giriş Qadağandır',
                403,
            ],
            default                               => [
                'https://api.example.com/errors/internal-error',
                'Server Daxili Xətası',
                500,
            ],
        };

        $extensions = [];
        if ($e instanceof ValidationException) {
            $extensions['errors'] = $e->getErrors();
        }

        return new self(
            type: $type,
            title: $title,
            status: $status,
            detail: $e->getMessage(),
            instance: $request->path(),
            extensions: $extensions,
        );
    }
}

// Handler-də istifadə
$this->renderable(function (AppException $e, Request $request) {
    if ($request->expectsJson()) {
        $problem = ProblemDetails::fromException($e, $request);
        return response()
            ->json($problem->toArray(), $problem->toArray()['status'])
            ->header('Content-Type', 'application/problem+json');
    }
});
```

---

## 12. Graceful Degradation Strategiyası

Xarici servis xətası baş verdikdə sistemi tamamilə dayandırmaq əvəzinə, daha az funksionallıqla işləməyə davam etmək.

*Xarici servis xətası baş verdikdə sistemi tamamilə dayandırmaq əvəzinə üçün kod nümunəsi:*
```php
<?php

class ProductRecommendationService
{
    public function getRecommendations(User $user): array
    {
        try {
            // ML servisi — əgər işləmirsə, biz işləməyə davam edirik
            return $this->mlService->recommend($user->id);

        } catch (ExternalServiceException $e) {
            // ML servisi əlçatmazdır — fallback: populyar məhsullar
            Log::warning("ML recommendation servisi uğursuz oldu, fallback istifadə edilir", [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);

            return $this->getPopularProducts();

        } catch (\Throwable $e) {
            // Gözlənilməz xəta — boş array qaytarırıq, crash etmirik
            Log::error("Recommendation service kritik xəta", ['exception' => $e]);
            return [];
        }
    }
}

// Circuit Breaker pattern ilə graceful degradation
class CircuitBreaker
{
    private int $failureCount = 0;
    private ?Carbon $lastFailureTime = null;
    private string $state = 'closed'; // closed, open, half-open

    public function __construct(
        private readonly int $threshold = 5,
        private readonly int $timeout = 60, // seconds
    ) {}

    public function call(callable $fn, callable $fallback): mixed
    {
        if ($this->isOpen()) {
            Log::info("Circuit breaker açıqdır, fallback istifadə edilir");
            return $fallback();
        }

        try {
            $result = $fn();
            $this->onSuccess();
            return $result;
        } catch (\Throwable $e) {
            $this->onFailure();
            if ($this->isOpen()) {
                return $fallback();
            }
            throw $e;
        }
    }

    private function isOpen(): bool
    {
        if ($this->state === 'open') {
            // Timeout keçibsə half-open et
            if ($this->lastFailureTime && now()->diffInSeconds($this->lastFailureTime) > $this->timeout) {
                $this->state = 'half-open';
                return false;
            }
            return true;
        }
        return false;
    }

    private function onSuccess(): void
    {
        $this->failureCount = 0;
        $this->state = 'closed';
    }

    private function onFailure(): void
    {
        $this->failureCount++;
        $this->lastFailureTime = now();

        if ($this->failureCount >= $this->threshold) {
            $this->state = 'open';
            Log::warning("Circuit breaker açıldı", ['failures' => $this->failureCount]);
        }
    }
}
```

---

## 13. Real-world: Payment Exception Hierarchy

*13. Real-world: Payment Exception Hierarchy üçün kod nümunəsi:*
```php
<?php

namespace App\Exceptions\Payment;

// Base
abstract class PaymentException extends \App\Exceptions\DomainException {}

// ── Kart xətaları ─────────────────────────────────────────────────────────────
class CardException extends PaymentException
{
    protected string $errorCode = 'CARD_ERROR';
}

class CardDeclinedException extends CardException
{
    protected string $errorCode = 'CARD_DECLINED';

    public static function insufficientFunds(): static
    {
        return new static("Kartda kifayət qədər vəsait yoxdur");
    }

    public static function cardExpired(): static
    {
        return new static("Kartın istifadə müddəti bitib");
    }
}

class InvalidCardException extends CardException
{
    protected string $errorCode = 'INVALID_CARD';
}

// ── Gateway xətaları ──────────────────────────────────────────────────────────
class PaymentGatewayException extends PaymentException
{
    protected string $errorCode = 'GATEWAY_ERROR';

    public function __construct(
        private readonly string $gateway,
        string $message,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getGateway(): string
    {
        return $this->gateway;
    }

    public function isRetryable(): bool
    {
        return true; // Gateway xətaları adətən retry edilə bilər
    }
}

class PaymentGatewayTimeoutException extends PaymentGatewayException
{
    protected string $errorCode = 'GATEWAY_TIMEOUT';
}

class PaymentGatewayUnavailableException extends PaymentGatewayException
{
    protected string $errorCode = 'GATEWAY_UNAVAILABLE';
}

// ── Fraud xətaları ────────────────────────────────────────────────────────────
class FraudDetectedException extends PaymentException
{
    protected string $errorCode = 'FRAUD_DETECTED';

    public function __construct(
        private readonly string $reason,
        private readonly float $riskScore,
    ) {
        parent::__construct("Şübhəli əməliyyat aşkarlandı");
        $this->context = [
            'reason'     => $reason,
            'risk_score' => $riskScore,
        ];
    }

    public function getRiskScore(): float
    {
        return $this->riskScore;
    }
}

// ── İstifadə ──────────────────────────────────────────────────────────────────
class PaymentService
{
    public function processPayment(Order $order, PaymentMethod $method): PaymentResult
    {
        try {
            $this->validatePaymentMethod($method);

            $result = $this->gateway->charge(
                amount: $order->total,
                method: $method,
            );

            return PaymentResult::success($result->transactionId);

        } catch (CardDeclinedException $e) {
            // İstifadəçiyə xəbər ver, retry mümkün
            Log::info("Kart rədd edildi", ['order' => $order->id, 'reason' => $e->getMessage()]);
            throw $e;

        } catch (FraudDetectedException $e) {
            // Fraud team-ə xəbər ver, order-i block et
            Log::warning("Fraud aşkarlandı", $e->getContext());
            $this->fraudTeam->alert($order, $e);
            $order->markAsSuspicious();
            throw $e;

        } catch (PaymentGatewayTimeoutException $e) {
            // Retry queue-ya əlavə et
            Log::error("Gateway timeout", ['gateway' => $e->getGateway()]);
            ProcessPaymentJob::dispatch($order)->delay(now()->addMinutes(5));
            throw $e;

        } catch (PaymentGatewayUnavailableException $e) {
            // Backup gateway-ə keç
            return $this->processWithBackupGateway($order, $method, $e);
        }
    }
}

// Handler-də Payment exception-ları
$this->renderable(function (PaymentException $e, Request $request) {
    if ($request->expectsJson()) {
        $statusCode = match (true) {
            $e instanceof CardDeclinedException          => 402,
            $e instanceof FraudDetectedException         => 403,
            $e instanceof PaymentGatewayException        => 503,
            default                                      => 400,
        };

        return response()->json([
            'error' => [
                'type'       => 'payment_error',
                'code'       => $e->getErrorCode(),
                'message'    => $e->getMessage(),
                'retryable'  => $e instanceof PaymentGatewayException && $e->isRetryable(),
            ],
        ], $statusCode);
    }
});
```

---

## 14. İntervyu Sualları və Cavabları

**S1: PHP-də `Error` və `Exception` arasındakı fərq nədir?**

C: `Exception` — proqramçının öz tərəfindən `throw` etdiyi, application-level xətalardır. `Error` isə PHP engine-in özündən gəlir: `TypeError`, `ParseError`, `OutOfMemoryError` kimi. Hər ikisi PHP 7+ versiyasında `Throwable` interface-ini implement edir, ona görə `catch (Throwable $e)` hər ikisinə tətbiq olunur.

---

**S2: `finally` bloku nə zaman çalışmır?**

C: Demək olar ki, həmişə çalışır. Lakin `exit()` / `die()` çağrılsa, ya da PHP prosesi `SIGKILL` ilə öldürülsə, `finally` çalışmaz. `finally`-də özü exception throw edilsə, orijinal exception itirir.

---

**S3: Exception chaining niyə vacibdir?**

C: Low-level exception-u (məsələn `PDOException`) birbaşa expose etmək güvənsizdir (database strukturunu leak edir). Lakin onu silsək, debug üçün stack trace itir. `$previous` parametri ilə yüksək səviyyəli exception throw edib, aşağı səviyyəli xətanı da saxlayırıq. Log-da bütün chain görünür.

---

**S4: Laravel Handler-də `dontReport` nə üçün istifadə edilir?**

C: `dontReport` — bu exception tipləri log-a yazılmasın deyə. Məsələn, `404 NotFoundHttpException` çox tez-tez baş verir (botlar, yanlış URL-lər) və log-u çirkləndirir. Validation xətaları da normal axışın bir hissəsidir, log-a yazılmamalıdır.

---

**S5: `renderable()` vs `reportable()` fərqi nədir?**

C: `reportable()` — exception-un necə log/report ediləcəyini müəyyən edir (Sentry, log driver, etc.). `renderable()` — exception-un HTTP response-a necə çevriləcəyini müəyyən edir (JSON, view, redirect). Bunlar müstəqildir: bir exception həm öz şəkildə report edilə, həm öz şəkildə render edilə bilər.

---

**S6: Result pattern nə zaman exception-dan yaxşıdır?**

C: Exception-lar "istisna" hallara görədir — gözlənilməz, nadir vəziyyətlər. Əgər validasiya uğursuzluğu, tapılmama kimi **gözlənilən** nəticələrdirsə, Result pattern daha münasibdir. Çünki: 1) caller-i xətanı handle etməyə məcbur edir (compile-time), 2) kod axışı daha aydındır, 3) performans baxımından try/catch-dən sürətlidir.

---

**S7: `set_error_handler` ilə `register_shutdown_function` fərqi?**

C: `set_error_handler` — PHP `E_WARNING`, `E_NOTICE`, `E_DEPRECATED` kimi soft error-ları tutur (fatal deyil). `register_shutdown_function` — script bitdikdə (hətta fatal error-dan sonra da) çağrılır. Fatal error-ları ancaq `register_shutdown_function` + `error_get_last()` ilə tuta bilirik.

---

**S8: API-də HTTP status kodları seçiminin əhəmiyyəti nədir?**

C: Düzgün status kodları: 400 (client xətası), 401 (autentifikasiya yoxdur), 403 (icazə yoxdur), 404 (tapılmadı), 422 (validasiya xətası), 429 (rate limit), 500 (server xətası), 503 (servis əlçatmazdır). Yanlış kod (məs. hər şey üçün 200) client-i çaşdırır və monitoring-i çətinləşdirir.

---

**S9: RFC 7807 Problem Details nədir, niyə istifadə edilməlidir?**

C: Standart API error format-ıdır: `type` (URI), `title`, `status`, `detail`, `instance` sahələri var. Üstünlüyü: bütün API-lər eyni struktur istifadə edir, client-lər generic olaraq handle edə bilər. Content-Type `application/problem+json` olur ki, normal response-dan fərqlənsin.

---

**S10: Graceful degradation nə deməkdir?**

C: Bir komponentin uğursuzluğu bütün sistemi dayandırmamalıdır. Məsələn: recommendation servisi düşüb → popular məhsullar göstər. Payment gateway timeout → backup gateway istifadə et, ya da queue-ya at. Circuit Breaker pattern bu strategiyanın texniki implementasiyasıdır: müəyyən sayda uğursuzluqdan sonra servisi müvəqqəti olaraq bypass edir.

---

**S11: Laravel-də unhandled exception baş verdikdə nə olur?**

C: Laravel-in `Handler::render()` metodu çağrılır. Əgər `renderable()` callback tapılmasa, default behavior: debug mode-da stack trace göstərir, production-da isə generic 500 error page-i. `APP_DEBUG=false` olduqda exception məlumatı client-ə göstərilmir (security).

---

**S12: Exception-u log-a yazarkən sensitive data-nı necə qorumaq olar?**

C: `$dontFlash` array-i session data-nı flash-dan çıxarır. Lakin log-da exception context-ini özümüz yazırıqsa, sensitive sahələri mask etməliyik. `Handler::withContext()` ilə context əlavə edərkən şifrə, kart nömrəsi kimi məlumatları daxil etməmək lazımdır. Sentry kimi toollar da PII scrubbing funksionallığı təklif edir.

---

## Anti-patternlər

**1. Boş `catch` bloku ilə xətanı udmaq**
`catch (Exception $e) {}` yazmaq — xəta baş verib, amma heç kim bilmir; sistem qüsurlu vəziyyətdə davam edir, debug etmək qeyri-mümkün olur. Minimum `Log::error()` ilə xətanı log-la, ya da yenidən at.

**2. Ən ümumi `Exception`-ı tutub bütün xətaları eyni cür idarə etmək**
`catch (Exception $e)` ilə hər şeyi eyni handler-a yönləndirmək — `ValidationException`, `AuthorizationException`, `DatabaseException` hamısı eyni şəkildə işlənir, müştəriyə yanlış response göndərilir. Spesifik exception class-ları tut, hər birini uyğun şəkildə idarə et.

**3. Exception message-ında sensitive data göndərmək**
`throw new Exception("User {$user->password} login failed")` — stack trace-lərdə, log-larda, hətta API response-larda parol, kart nömrəsi kimi məlumatlar görünür. Exception message-larına yalnız debug üçün lazım olan texniki detalları yaz, PII-ni heç vaxt daxil etmə.

**4. Business logic üçün exception flow-u işlətmək**
Normal iş axışlarını (məs: "user tapılmadı") exception ilə idarə etmək — exception-lar performans baxımından baha başa gəlir, kod axışı anlaşılmazdır. Normal hallar üçün `null`, `Result` object, ya da `false` qaytar; exception-ları həqiqi gözlənilməz vəziyyətlər üçün saxla.

**5. `APP_DEBUG=true`-ni production-da buraxmaq**
Debug rejimini production-da aktiv saxlamaq — stack trace, konfiqurasiya dəyərləri, DB sorğuları birbaşa brauzerə göndərilir, ciddi təhlükəsizlik boşluğu yaranır. `.env` faylında `APP_DEBUG=false` et, xətaları log-a yaz.

**6. Graceful degradation olmadan xarici servis xətalarını yaymaq**
Ödəniş gateway-i, SMS xidməti uğursuz olduqda xətanı birbaşa istifadəçiyə göndərmək — bütün əməliyyat dayanır. Circuit Breaker pattern tətbiq et, fallback strategiyaları (queue, backup service) hazırla, istifadəçiyə mənalı xəta mesajı göstər.
