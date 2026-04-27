# Chain of Responsibility (Lead ⭐⭐⭐⭐)

## İcmal

Chain of Responsibility (CoR) — sorğunu bir neçə handler-ın zənciri boyunca ötürən, hər handler-ın ya işləmə ya da növbəti handler-a ötürmə qərarı verdiyi behavioral pattern-dir. "Avoid coupling the sender of a request to its receiver by giving more than one object a chance to handle the request." Klassik CoR: Hər handler-ın növbəti handler-a referansı var. Modern variant: Middleware pipeline — sorğu bütün handler-lardan keçir. Laravel HTTP middleware stack, Pipeline class, validation rule pipeline, event listener-lar — bunların hamısı CoR pattern üzərindədir. Lead səviyyəsindəki interview-larda "Middleware pipeline-ı sıfırdan necə qurarsınız?", "Validation rule zənciri", "Request processing pipeline" suallarında çıxır.

## Niyə Vacibdir

CoR pattern — cross-cutting concern-ləri decoupling ilə həll edir. Authentication, rate limiting, logging, validation, compression — bunların hər biri ayrı handler-dır, bir-birindən xəbərsizdir. Handler-ları birləşdirən orchestrator (Pipeline) yalnız sıranı bilir. Yeni concern əlavə etmək: Yeni handler class yazılır, pipeline-a qatılır — mövcud kod dəyişmir. Laravel-in `Illuminate\Pipeline\Pipeline` class-ı — CoR-un həm klasik, həm middleware variantını dəstəkləyir. Bu pattern-i Lead səviyyəsindən anlayan developer: Pipeline-ın short-circuit, priority, condition-based execution mexanizmlərini, performans trade-off-larını bilir.

## Əsas Anlayışlar

**CoR iki əsas variantı:**

**1. Klassik CoR:**
- Hər handler-ın `next` referansı var
- Handler ya işləyir (zənciri durdurur) ya da `next.handle()` çağırır
- Use-case: Escalation chains (support ticket → agent → manager → director), validation (əgər əvvəlki keçsə)

**2. Middleware Pipeline (Modern variant):**
- Hər middleware `$next($request)` çağırır — sonrakıya ötürür
- Bütün middleware-lər iştirak edir (short-circuit mümkündür — exception atıb)
- Request: Xaricdən içəriyə. Response: İçəridən xariyə
- Laravel, Express.js, ASP.NET Core — hamısı bu variantdır

**Handler interface:**
- `handle(Request $request, Closure $next): Response` — Laravel middleware imzası
- Klassik: `handle(Request $request): void` + `setNext(Handler $next): Handler`

**Short-circuit:**
- Middleware exception atarsa ya da response qaytararsa — zəncir dayan
- `Auth::class` — unauthenticated user-ı durdurur, `$next()` çağrılmır
- Rate limiter — limit aşılıbsa `429 Too Many Requests` qaytarır

**Order matters:**
- Middleware sırası vacibdir: Auth → Throttle → CSRF → Route
- Auth əvvəl olmalıdır — throttling-dən qabaq authenticated user-ı bilmək lazımdır
- Logging ən xaricdə — hər şeyi görür

**Onion model / Inverted Execution:**
- Request: `MW1 → MW2 → MW3 → Handler`
- Response: `Handler → MW3 → MW2 → MW1`
- Response manipulyasiyası: `$response = $next($request); $response->header('X-Custom', '1'); return $response;`

**Pipeline class:**
- `app(Pipeline::class)->send($payload)->through([$handler1, $handler2])->then(fn($p) => $p)`
- Laravel-in öz Pipeline class-ı — CoR-u programmatic istifadə üçün

**Priority-based CoR:**
- Handler-ların sırası priority-yə görə dinamik qurulur
- Event system-lərdə: Listener priority-si yüksəkdirsə əvvəl çağrılır

**Conditional handler:**
- Handler-ın işləyib işləməyəcəyi runtime şərtə bağlıdır
- `if (!$this->appliesToRequest($request)) return $next($request);`

**Memory leak risk:**
- Uzun handler zəncirləri closure capture ilə memory sızdıra bilər
- Xüsusən long-running process-lərdə (Octane, RoadRunner): Stateful middleware-lər arasında request state qalmamalıdır

**Testing CoR:**
- Hər middleware-i izolasiyada test edin: `$middleware->handle($request, fn($r) => new Response('ok'))`
- Integration test: Full pipeline-ı test edin

## Praktik Baxış

**Interview-da yanaşma:**
CoR-u iki variant üzərindən izah edin — klassik (escalation) və middleware (pipeline). Laravel middleware sırasının niyə vacib olduğunu izah edin. Pipeline class-ını nümunə göstərin. Short-circuit semantics-ini (auth middleware kim keçirmir?) aydınlaşdırın.

**"Nə vaxt CoR seçərdiniz?" sualına cavab:**
- Request/response processing pipeline lazım olduqda
- Birden çox handler-ın sequentially işləməsi lazım olduqda, lakin bir-birindən asılı olmamalı olduqda
- Handler-ların runtime-da konfiqurasiya edilməsi lazım olduqda
- Cross-cutting concern-ləri (auth, logging, validation) decoupled saxlamaq lazım olduqda

**Anti-pattern-lər:**
- Handler-ların bir-birinə birbaşa bağlı olması — zəncir qırılınca bütün sistem pozulur
- Çox uzun zəncir — performance overhead, debug çətin
- Stateful middleware-lər — concurrent request-lər arasında state sızması
- Handler-ın öz məsuliyyətindən kənar işlər görməsi — SRP pozulması
- CoR-u yalnız bir handler işləyəcəksə istifadə etmək — overkill

**Follow-up suallar:**
- "Laravel middleware-in `before` vs `after` davranışı nədir?" → `$next($request)` dan əvvəl kod = before. Sonra kod = after. Response `$next`-dən gəlir
- "Pipeline-da middleware sırası necə müəyyən olunur?" → `$kernel->middleware()` array, `$this->middleware()` route middleware, priority sistemi
- "Handler-lar bir-birinə coupling olmadan necə kommunikasiya edir?" → Shared carrier object-ə data yazır (Request bag, Payload DTO), sonrakı handler oxuyur

## Nümunələr

### Tipik Interview Sualı

"Design a request processing pipeline for an API gateway. The pipeline must: authenticate the token, check rate limits, validate the request body, log the request, and route to the handler. Each step can reject the request. How would you implement this using Chain of Responsibility?"

### Güclü Cavab

Bu middleware pipeline-ın klassik use-case-idir.

Hər concern ayrı middleware: `AuthenticationMiddleware`, `RateLimitMiddleware`, `ValidationMiddleware`, `LoggingMiddleware`.

Sıra vacibdir: Auth əvvəl — kim olduğunu bilmədən rate limit tətbiq edə bilmirik. Rate limit sonra — authenticated user-ın limitini yoxlayırıq. Validation — authenticated, limit keçməyib, indi data-nı yoxla. Logging ən xaricdə — uğurlu və uğursuz istəklərin hər ikisini log edir.

`$next($request)` çağrısı zənciri irəlilədır. Auth middleware unauthenticated görürsə `$next()` çağırmır, `401` qaytarır — zəncir burada dayanır.

`Pipeline::send($request)->through([Auth, RateLimit, Validation, Logging])->then($handler)` — bütün pipeline bir xətdə konfiqurasiya olunur.

### Kod Nümunəsi

```php
// Handler interface — middleware contract
interface MiddlewareInterface
{
    public function handle(ServerRequest $request, callable $next): Response;
}

// Middleware 1: Authentication
class AuthenticationMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly TokenValidator $tokenValidator,
    ) {}

    public function handle(ServerRequest $request, callable $next): Response
    {
        $token = $request->getHeaderLine('Authorization');

        if (empty($token) || !str_starts_with($token, 'Bearer ')) {
            return new JsonResponse(['error' => 'Missing token'], 401);
        }

        try {
            $user = $this->tokenValidator->validate(substr($token, 7));
            // Sonrakı middleware-lər üçün user-i request-ə qoy
            $request = $request->withAttribute('authenticated_user', $user);
        } catch (InvalidTokenException $e) {
            return new JsonResponse(['error' => 'Invalid token'], 401);
        }

        return $next($request);  // Zənciri davam et
    }
}

// Middleware 2: Rate Limiting
class RateLimitMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly RateLimiter $limiter,
        private readonly int $requestsPerMinute = 60,
    ) {}

    public function handle(ServerRequest $request, callable $next): Response
    {
        $user = $request->getAttribute('authenticated_user');
        $key  = "rate_limit:user:{$user->id}:" . date('Y-m-d-H-i');

        if ($this->limiter->tooManyAttempts($key, $this->requestsPerMinute)) {
            $retryAfter = $this->limiter->availableIn($key);
            return new JsonResponse(
                ['error' => 'Too many requests'],
                429,
                ['Retry-After' => $retryAfter]
            );
        }

        $this->limiter->hit($key, 60);
        return $next($request);
    }
}

// Middleware 3: Request Validation
class RequestValidationMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly ValidatorFactory $validatorFactory,
        private readonly array $rules,
    ) {}

    public function handle(ServerRequest $request, callable $next): Response
    {
        $data      = json_decode($request->getBody()->getContents(), true) ?? [];
        $validator = $this->validatorFactory->make($data, $this->rules);

        if ($validator->fails()) {
            return new JsonResponse([
                'error'   => 'Validation failed',
                'details' => $validator->errors()->toArray(),
            ], 422);
        }

        // Validated data-nı request-ə qoy
        $request = $request->withAttribute('validated_data', $validator->validated());

        return $next($request);
    }
}

// Middleware 4: Logging (ən xaricdə — request + response görür)
class RequestLoggingMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    public function handle(ServerRequest $request, callable $next): Response
    {
        $startTime = microtime(true);
        $user      = $request->getAttribute('authenticated_user');

        // Before: request log
        $this->logger->info('API request', [
            'method'  => $request->getMethod(),
            'path'    => $request->getUri()->getPath(),
            'user_id' => $user?->id,
        ]);

        // Zənciri davam et
        $response = $next($request);

        // After: response log
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        $this->logger->info('API response', [
            'status'      => $response->getStatusCode(),
            'duration_ms' => $duration,
        ]);

        // Response-a timing header əlavə et
        return $response->withHeader('X-Response-Time', "{$duration}ms");
    }
}

// Pipeline — middleware-ləri zəncirə yığır
class Pipeline
{
    private array $middleware = [];

    public function pipe(MiddlewareInterface $middleware): self
    {
        $clone = clone $this;
        $clone->middleware[] = $middleware;
        return $clone;
    }

    public function process(ServerRequest $request, callable $final): Response
    {
        $pipeline = array_reduce(
            array_reverse($this->middleware),
            fn($carry, $mw) => fn($req) => $mw->handle($req, $carry),
            $final
        );

        return $pipeline($request);
    }
}

// Quruluş
$pipeline = (new Pipeline())
    ->pipe(new RequestLoggingMiddleware($logger))       // Ən xaricdə
    ->pipe(new AuthenticationMiddleware($tokenValidator))
    ->pipe(new RateLimitMiddleware($limiter, 100))
    ->pipe(new RequestValidationMiddleware($validator, [
        'email' => 'required|email',
        'name'  => 'required|min:2',
    ]));

// Execution
$response = $pipeline->process($request, function (ServerRequest $req): Response {
    $data = $req->getAttribute('validated_data');
    $user = $req->getAttribute('authenticated_user');
    // Business logic
    return new JsonResponse(['success' => true], 201);
});
```

```php
// Laravel Pipeline class — programmatic CoR
use Illuminate\Pipeline\Pipeline;

class OrderProcessingPipeline
{
    private array $pipes = [
        ValidateOrderInventory::class,
        ApplyCouponDiscount::class,
        CalculateTax::class,
        ChargePayment::class,
        CreateOrder::class,
        SendConfirmationEmail::class,
    ];

    public function process(OrderData $data): Order
    {
        return app(Pipeline::class)
            ->send($data)
            ->through($this->pipes)
            ->then(fn(OrderData $data) => $data->getCreatedOrder());
    }
}

// Pipeline stage — hər stage bir handler
class ApplyCouponDiscount
{
    public function handle(OrderData $data, Closure $next): mixed
    {
        if ($data->couponCode) {
            $discount = $this->coupons->find($data->couponCode);

            if (!$discount || $discount->isExpired()) {
                throw new InvalidCouponException($data->couponCode);
            }

            $data->applyDiscount($discount->amount());
        }

        return $next($data);  // Sonrakı stage-ə ötür
    }
}

class ChargePayment
{
    public function handle(OrderData $data, Closure $next): mixed
    {
        $result = $this->gateway->charge($data->total(), $data->paymentMethod());

        if ($result->isFailed()) {
            throw new PaymentFailedException($result->message());
            // Zəncir burada dayanır — sonrakı stage-lər çalışmır
        }

        $data->setTransactionId($result->transactionId());

        return $next($data);
    }
}
```

## Praktik Tapşırıqlar

- Custom Pipeline yazın: `send()`, `through()`, `then()` metodları — array_reduce istifadə edin
- Laravel-ə custom global middleware əlavə edin: `X-Request-ID` header inject edən, response-da eyni header qaytaran
- Klassik CoR: Support ticket escalation — `AgentHandler → TeamLeadHandler → ManagerHandler` — hər biri ya həll edir ya escalate edir
- Order processing pipeline: 5 stage (validate, discount, tax, payment, create) — hər stage-i izolasiyada test edin
- Middleware priority test edin: Auth, RateLimit, CSRF — sıranı dəyişdirib davranışı müqayisə edin

## Əlaqəli Mövzular

- [Command Pattern](06-command-pattern.md) — Command Bus middleware pipeline CoR-dur
- [Decorator Pattern](08-decorator-pattern.md) — Oxşar "wrap" strukturu, lakin davranış əlavə edir
- [Proxy Pattern](13-proxy-pattern.md) — Proxy zənciri CoR-la qarışdırıla bilər: hər ikisi intercept edir
- [Observer / Event](04-observer-event.md) — Event listener priority — CoR-un event-driven variantı
- [Template Method](12-template-method.md) — Pipeline stage-ları Template Method hook-ları kimi düşünülə bilər
