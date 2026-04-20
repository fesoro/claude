# PSR-7 / PSR-15 Middleware — Deep Dive

## Mündəricat
1. [PSR nədir?](#psr-nədir)
2. [PSR-7 (HTTP Messages)](#psr-7-http-messages)
3. [PSR-15 (HTTP Middleware)](#psr-15-http-middleware)
4. [PSR-17 (HTTP Factory)](#psr-17-http-factory)
5. [Middleware pipeline](#middleware-pipeline)
6. [Slim framework nümunəsi](#slim-framework-nümunəsi)
7. [Mezzio (Laminas)](#mezzio-laminas)
8. [Laravel middleware və PSR fərqi](#laravel-middleware-və-psr-fərqi)
9. [Symfony HttpKernel](#symfony-httpkernel)
10. [PSR-7 Immutability](#psr-7-immutability)
11. [Real middleware nümunələri](#real-middleware-nümunələri)
12. [İntervyu Sualları](#intervyu-sualları)

---

## PSR nədir?

```
PSR (PHP Standards Recommendations) — PHP-FIG tərəfindən yazılır.
Framework-lər arası interoperability yaradır.

Əsas PSR-lər:
  PSR-1    Basic Coding Standard
  PSR-4    Autoloading (composer-də istifadə olunur)
  PSR-7    HTTP Message Interface (Request/Response)
  PSR-11   Container Interface (DI container)
  PSR-12   Extended Coding Style
  PSR-14   Event Dispatcher
  PSR-15   HTTP Server Middleware
  PSR-16   Simple Cache
  PSR-17   HTTP Factories
  PSR-18   HTTP Client
  PSR-20   Clock Interface
```

---

## PSR-7 (HTTP Messages)

```php
<?php
// PSR-7 immutable HTTP message interface-ləri

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;

// ServerRequestInterface — server tərəfdə gələn request
interface ServerRequestInterface extends RequestInterface
{
    public function getServerParams(): array;                // $_SERVER
    public function getCookieParams(): array;                // $_COOKIE
    public function getQueryParams(): array;                 // $_GET
    public function getUploadedFiles(): array;               // $_FILES
    public function getParsedBody();                         // $_POST (JSON parse)
    public function getAttributes(): array;                  // middleware-lərin əlavə etdikləri
    public function getAttribute(string $name, $default = null);
    
    // Immutable withers (clone qaytarır)
    public function withQueryParams(array $query);
    public function withParsedBody($data);
    public function withAttribute(string $name, $value);
}

// ResponseInterface
interface ResponseInterface extends MessageInterface
{
    public function getStatusCode(): int;
    public function getReasonPhrase(): string;
    public function withStatus(int $code, string $reasonPhrase = '');
}
```

```php
<?php
// Tipik implementasiya: nyholm/psr7 və ya guzzlehttp/psr7
composer require nyholm/psr7

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;

$factory = new Psr17Factory();
$requestCreator = new ServerRequestCreator(
    $factory, $factory, $factory, $factory
);

// $_SERVER, $_GET, $_POST, headers, body-dən PSR-7 Request yarat
$request = $requestCreator->fromGlobals();

$method   = $request->getMethod();         // "GET"
$uri      = $request->getUri()->getPath(); // "/users/42"
$headers  = $request->getHeaders();
$body     = (string) $request->getBody();  // raw body
$json     = $request->getParsedBody();     // parsed (əgər middleware varsa)

// Response yaratmaq
$response = $factory->createResponse(200)
    ->withHeader('Content-Type', 'application/json')
    ->withBody($factory->createStream(json_encode(['ok' => true])));

// Immutable — hər with...() YENİ obyekt qaytarır
$r1 = $response;
$r2 = $response->withStatus(404);
// $r1->getStatusCode() === 200, $r2->getStatusCode() === 404
```

---

## PSR-15 (HTTP Middleware)

```php
<?php
// PSR-15 iki interface təqdim edir:
//   RequestHandlerInterface — end handler (final controller)
//   MiddlewareInterface — pipeline link

use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

interface RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface;
}

interface MiddlewareInterface
{
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface;
}
```

```php
<?php
// Middleware nümunəsi — Authentication
class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(private AuthService $auth) {}
    
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $token = $request->getHeaderLine('Authorization');
        
        $user = $this->auth->validate($token);
        if (!$user) {
            return new Response(401);
        }
        
        // Request-ə user əlavə et, sonrakı middleware/handler istifadə edəcək
        $request = $request->withAttribute('user', $user);
        
        // Pipeline davam etdir
        return $handler->handle($request);
    }
}

// Logging middleware — before + after
class LoggingMiddleware implements MiddlewareInterface
{
    public function __construct(private LoggerInterface $log) {}
    
    public function process($request, $handler): ResponseInterface
    {
        $start = microtime(true);
        $this->log->info("→ {$request->getMethod()} {$request->getUri()}");
        
        $response = $handler->handle($request);  // pipeline-a ver
        
        $duration = (microtime(true) - $start) * 1000;
        $this->log->info("← {$response->getStatusCode()} ({$duration}ms)");
        
        return $response;
    }
}

// CORS middleware
class CorsMiddleware implements MiddlewareInterface
{
    public function process($request, $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        
        return $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization');
    }
}
```

---

## PSR-17 (HTTP Factory)

```php
<?php
// PSR-17 PSR-7 obyektlərini yaratmaq üçün factory-lər
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;

// Bu factory-lər DI container-də bind olunur,
// kod framework-independent qalır:

class MyController
{
    public function __construct(
        private ResponseFactoryInterface $responses,
        private StreamFactoryInterface $streams,
    ) {}
    
    public function handle(ServerRequestInterface $req): ResponseInterface
    {
        $body = $this->streams->createStream(json_encode(['ok' => true]));
        return $this->responses->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($body);
    }
}
```

---

## Middleware pipeline

```
Request                             Response
  │                                    ▲
  ▼                                    │
┌───────────────────────────────────────┐
│ Middleware 1: Logging                 │
│   process(req, next) {                │
│     before...                         │
│     response = next->handle(req) ─────┼──┐
│     after...                          │  │
│     return response                   │  │
│   }                                   │  │
└───────────────────────────────────────┘  │
┌───────────────────────────────────────┐  │
│ Middleware 2: Auth                    │  │
│   process(req, next) {                │  │
│     if (!auth) return 401             │  │
│     req = req.withAttribute("user")   │  │
│     return next->handle(req) ─────────┼──┼──┐
│   }                                   │  │  │
└───────────────────────────────────────┘  │  │
┌───────────────────────────────────────┐  │  │
│ Middleware 3: CORS                    │  │  │
│   ...                                 │  │  │
└───────────────────────────────────────┘  │  │
┌───────────────────────────────────────┐  │  │
│ FINAL HANDLER (Controller)            │  │  │
│   response = MyController::show()     │  │  │
└───────────────────────────────────────┘  │  │
         │                                 │  │
         └─────────────────────────────────┘  │
                                              │
                                              │
                                              ▼
```

```php
<?php
// Sadə pipeline runner
class Pipeline implements RequestHandlerInterface
{
    private array $middleware = [];
    
    public function __construct(
        private RequestHandlerInterface $finalHandler
    ) {}
    
    public function add(MiddlewareInterface $mw): self
    {
        $this->middleware[] = $mw;
        return $this;
    }
    
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (empty($this->middleware)) {
            return $this->finalHandler->handle($request);
        }
        
        $mw = array_shift($this->middleware);
        return $mw->process($request, $this);  // recursive
    }
}

// İstifadə
$pipeline = new Pipeline($controller);
$pipeline->add(new LoggingMiddleware($logger));
$pipeline->add(new AuthMiddleware($auth));
$pipeline->add(new CorsMiddleware());

$response = $pipeline->handle($request);
```

---

## Slim framework nümunəsi

```php
<?php
// Slim 4 — PSR-7/PSR-15 compliant micro-framework
require 'vendor/autoload.php';

use Slim\Factory\AppFactory;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

$app = AppFactory::create();

// Route
$app->get('/users/{id}', function (
    ServerRequestInterface $req,
    ResponseInterface $res,
    array $args
) {
    $user = User::find((int) $args['id']);
    $res->getBody()->write(json_encode($user));
    return $res->withHeader('Content-Type', 'application/json');
});

// Middleware — əks sırada əlavə olunur (LIFO)
$app->add(new CorsMiddleware());         // last added = first executed
$app->add(new AuthMiddleware($auth));
$app->add(new LoggingMiddleware($log));  // first added = last executed

$app->run();
```

---

## Mezzio (Laminas)

```php
<?php
// Mezzio (keçmiş Zend Expressive) — saf PSR-15 pipeline framework
$app = \Mezzio\AppFactory::create();

$app->pipe(new ErrorHandlerMiddleware());
$app->pipe(new RouterMiddleware($router));
$app->pipe(new AuthMiddleware($auth));
$app->pipe(new DispatchMiddleware());

// Routes
$app->get('/users/{id}', UserHandler::class);
$app->post('/users', CreateUserHandler::class);

// Handler (controller əvəzinə)
class UserHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $req): ResponseInterface
    {
        $id = $req->getAttribute('id');
        $user = User::find($id);
        return new JsonResponse($user);
    }
}
```

---

## Laravel middleware və PSR fərqi

```php
<?php
// Laravel öz middleware signature-ından istifadə edir (PSR-15 DEYİL)
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class LaravelAuthMiddleware
{
    // İlk 2 parametr: Request, Closure $next
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check()) {
            return response()->json(['error' => 'unauthorized'], 401);
        }
        return $next($request);  // pipeline davam
    }
    
    // Response-u dəyişmək üçün:
    public function terminate(Request $request, Response $response): void
    {
        // Log, cleanup
    }
}
```

```
Laravel vs PSR-15:
  Laravel Illuminate\Http\Request — Symfony Request əsaslıdır, PSR-7 ilə uyğun
  Laravel Closure-based middleware sadə, amma PSR-15 deyil
  
  Adapter paketlər:
    - symfony/psr-http-message-bridge → Symfony ↔ PSR-7
    - Laravel HTTP Foundation → PSR-7 conversion

Niyə Laravel PSR-15 istifadə etmir?
  Laravel öz ekosistemi qurulmuşdu PSR-15-dən əvvəl.
  Backward compatibility vacibdir.
  BridgE-lər mövcuddur amma overhead gətirir.
```

---

## Symfony HttpKernel

```php
<?php
// Symfony — PSR-7/15 DEYİL, amma çox oxşar event-based pipeline
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;

class AuthListener
{
    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$this->auth->check($request)) {
            $event->setResponse(new Response('Unauthorized', 401));
        }
    }
}

// services.yaml
// kernel.event_listener: { event: kernel.request, method: __invoke }
```

---

## PSR-7 Immutability

```
PSR-7 obyektləri IMMUTABLE:
  - Bütün "setter"-lər `with...()` — clone qaytarır, dəyişdirmir
  - Thread-safe (paralel dəyişdirmə yoxdur)
  - Debugging asan (state "donmuş")

Performance problem:
  $req = $request
      ->withHeader('X-1', 'a')
      ->withHeader('X-2', 'b')
      ->withHeader('X-3', 'c');
  → 3 clone! Hər with 1 clone yaradır.

Optimization (nyholm/psr7):
  Internal "copy-on-write" və şallow clone — amma hələ də weight var.

Laravel Illuminate\Http\Request — MUTABLE (sadəlik üçün).
  $request->headers->set('X-1', 'a');  // direct mutation
  Daha performant, amma state-i izləmək çətin.
```

---

## Real middleware nümunələri

```php
<?php
// 1. Rate limiting
class RateLimitMiddleware implements MiddlewareInterface
{
    public function __construct(
        private \Redis $redis,
        private int $limit = 100,
        private int $window = 60,
    ) {}
    
    public function process($request, $handler): ResponseInterface
    {
        $ip  = $request->getServerParams()['REMOTE_ADDR'];
        $key = "rate_limit:$ip";
        
        $count = $this->redis->incr($key);
        if ($count === 1) {
            $this->redis->expire($key, $this->window);
        }
        
        if ($count > $this->limit) {
            return new Response(429, ['X-RateLimit-Reset' => $this->window]);
        }
        
        $response = $handler->handle($request);
        return $response
            ->withHeader('X-RateLimit-Limit', $this->limit)
            ->withHeader('X-RateLimit-Remaining', max(0, $this->limit - $count));
    }
}

// 2. Request ID propagation
class RequestIdMiddleware implements MiddlewareInterface
{
    public function process($request, $handler): ResponseInterface
    {
        $id = $request->getHeaderLine('X-Request-ID') ?: bin2hex(random_bytes(8));
        
        // Logger context-ə əlavə et
        \Log::withContext(['request_id' => $id]);
        
        $request = $request->withAttribute('request_id', $id);
        $response = $handler->handle($request);
        
        return $response->withHeader('X-Request-ID', $id);
    }
}

// 3. Content negotiation
class JsonBodyMiddleware implements MiddlewareInterface
{
    public function process($request, $handler): ResponseInterface
    {
        if (str_contains($request->getHeaderLine('Content-Type'), 'application/json')) {
            $body = (string) $request->getBody();
            $parsed = json_decode($body, true) ?? [];
            $request = $request->withParsedBody($parsed);
        }
        return $handler->handle($request);
    }
}

// 4. Error handler (last line of defense)
class ErrorHandlerMiddleware implements MiddlewareInterface
{
    public function __construct(private LoggerInterface $log) {}
    
    public function process($request, $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (\HttpException $e) {
            return new Response($e->getStatusCode(), [], $e->getMessage());
        } catch (\Throwable $e) {
            $this->log->error($e->getMessage(), ['exception' => $e]);
            return new Response(500, [], 'Internal Server Error');
        }
    }
}
```

---

## İntervyu Sualları

- PSR-7 nədir və nə üçün lazımdır?
- PSR-7 niyə immutable dizayn edildi?
- PSR-15 middleware ilə Laravel middleware arasındakı fərq?
- `withAttribute()` niyə yeni obyekt qaytarır?
- Middleware-in LIFO icra sırası niyə vacibdir (outer scope)?
- PSR-17 Factory-lər niyə lazımdır (PSR-7 onsuz da kifayət etməz)?
- PSR-15 final `RequestHandlerInterface` ilə middleware-in fərqi nədir?
- Slim və Laravel middleware dizayn fərqi nədir?
- `before` və `after` middleware-ləri necə yazılır?
- Guzzle, Symfony, Laravel arasında PSR-7 compatibility necədir?
- Request-ə middleware-lərin əlavə etdiyi data necə ötürülür?
- PSR-7 immutability performance-a necə təsir edir?
