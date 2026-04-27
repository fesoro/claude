# Strategy Pattern (Senior ⭐⭐⭐)

## İcmal
Strategy pattern — algorithm family-sini təyin edib hər birini ayrı class-da encapsulate edən, onları bir-birinin yerinə qoya bilən behavioral pattern-dir. "Define a family of algorithms, encapsulate each one, and make them interchangeable." Client algorithm-in daxili implementasiyasını bilmir, yalnız interface-ini istifadə edir. Payment processing, sorting, file export, authentication — hər hansı "variant algorithm" Strategy-nin ideal use-case-idir.

## Niyə Vacibdir
Strategy pattern — OCP-nin ən gözəl tətbiqlərindən biridir. Yeni variant əlavə etmək üçün mövcud kodu dəyişmədən yeni class yazmaq kifayətdir. Laravel-in driver-based sistem (cache, mail, queue, filesystem) tam Strategy pattern üzərindən qurulub. Bu pattern-i dərindən bilmək həm kod yazma keyfiyyətini artırır, həm də Laravel-in içini anlamağı asanlaşdırır.

---

## Əsas Anlayışlar

**Strategy Pattern Komponentləri:**
- **Strategy Interface:** Bütün variant algorithm-ların implement etməli olduğu contract — client yalnız bunu bilir
- **Concrete Strategies:** Hər variant algorithm-in implementasiyası — `CsvExporter`, `PdfExporter`, `ExcelExporter`
- **Context:** Strategy-ni saxlayır; client-in çağırdığı class; concrete strategy-ni bilmir — interface-ə depend edir

**UML Strukturu:**
- `Context` → `Strategy` interface (composition)
- `ConcreteStrategyA`, `ConcreteStrategyB` → `Strategy` implement edir
- Client `Context`-ə strategy inject edir; `Context.execute()` → `strategy.algorithm()`

**Strategy vs Inheritance:**
- Inheritance ilə algorithm dəyişmək: subclass yaratmaq lazımdır; behavioral + structural dəyişiklik
- Strategy ilə: yalnız behavior dəyişir, object type dəyişmir; runtime-da algorithm dəyişmək mümkündür

**Runtime Strategy Dəyişdirməsi:**
- Constructor injection: yaradılarkən strategy təyin edilir — immutable
- Setter: `$context->setStrategy(new FastSortStrategy())` — runtime dəyişim; A/B test üçün ideal
- Laravel: `Cache::driver('redis')` — runtime-da driver seçimi

**Strategy vs State Pattern:**
- **Strategy:** Client xaricdən algorithm-ı seçir; object-in internal state-i dəyişmir
- **State:** Object özü internal state-ə görə behavior-unu dəyişdirir; state transition auto-managed

**Strategy vs Template Method:**
- **Strategy:** Composition — algorithm ayrı class-dadır; runtime dəyişim mümkün
- **Template Method:** Inheritance — algorithm parent-dədir, addımlar override olunur; compile-time qərar

**Strategy + Factory:**
- Strategy seçimi məntiqi Factory-yə verilə bilər: `ExporterFactory::make('pdf')`
- Client yalnız format string bilir, factory-ni bilmir, strategy-ni bilmir

**Null Object Strategy:**
- `do nothing` implementation — null check-ləri aradan qaldırır
- `NoopLogger`, `NoopNotifier` — test-lərdə, optional feature-lar üçün

**Closure-based Strategy (PHP/JS):**
- Sadə hallarda ayrı class olmadan closure da strategy kimi keçirilə bilər
- Dezavantaj: type safety yoxdur, test etmək çətin, autocompletion işləmir

**Laravel-də Strategy Pattern-lər:**
- `Cache::driver('redis')` → `CacheManager::store()` → `RedisStore`
- `Storage::disk('s3')` → `FilesystemManager` → `S3Adapter`
- `Mail::mailer('smtp')` → `TransportManager` → `SmtpTransport`
- `Queue::connection('redis')` → `QueueManager` → `RedisQueue`
- Bütün bunlar: `Illuminate\Support\Manager` abstract class-dan extend edir

**SOLID ilə əlaqə:**
- **OCP:** Yeni strategy = yeni class; mövcud kod dəyişmir
- **SRP:** Hər strategy bir algorithm-ı implement edir
- **DIP:** Context strategy interface-ə depend edir, concrete class-a yox
- **LSP:** Hər concrete strategy interface kontraktını tam yerinə yetirməlidir

---

## Praktik Baxış

**Interview-da yanaşma:**
- Əvvəlcə problemi göstərin: "if-else chain yeni case-lə böyüyür, test etmək çətin, dəyişiklik riski var"
- Sonra Strategy həllini göstərin — interface + concrete class-lar
- Laravel driver sistemini nümunə kimi çəkin — real-world validation

**Follow-up suallar:**
1. "Strategy nə zaman if-else-dən üstündür?" — Hər variant ayrıca test lazımdırsa; runtime dəyişim lazımdırsa; yeni variant tez-tez əlavə olunarsa
2. "Strategy-nin dezavantajları?" — Sadə 2 variant üçün overkill; client bütün strategy-ləri bilməli ola bilər (Factory ilə həll)
3. "Laravel-in `config/filesystems.php` ilə Strategy əlaqəsi?" — Hər disk driver bir concrete strategy; `Storage::disk()` context
4. "Sorting strategy runtime-da necə seçilər?" — Request parameter, user preference, feature flag
5. "Strategy vs Policy (Laravel Authorization)?" — Policy authorization rule-dır; Strategy algorithm variant-dır; conseptual fərq
6. "Feature flag ilə strategy A/B test necə?" — User segment-ə görə `NewPricingStrategy` vs `OldPricingStrategy` inject et; rollout percentage-ə görə

**Real framework implementasiyaları:**
- **Laravel:** `CacheManager`, `FilesystemManager`, `MailManager`, `QueueManager` — hamısı `Manager` abstract class-dan
- **Spring:** `AuthenticationProvider` list — hər provider bir strategy; `PasswordEncoder` hierarchy
- **Django:** Authentication backends — `AUTHENTICATION_BACKENDS` siyahısı; hər backend bir strategy
- **Symfony:** `VoterInterface` implementations — authorization strategy-ləri

**Anti-patterns:**
- Strategy interface olmadan birbaşa concrete class inject etmək — type safety itirilir
- Closure-ları strategy kimi istifadə — IDE support yoxdur, test çətin
- Context-ə çox method əlavə etmək — context yalnız delegation etməlidir, logic saxlamamalıdır
- Bir strategy-yə "default fallback" məntiqi yazmaq — Null Object pattern istifadə et

---

## Nümunələr

### Tipik Interview Sualı
"You have a ReportGenerator that supports CSV, PDF, and Excel exports. New formats may be needed. How would you design this using Strategy Pattern?"

### Güclü Cavab
Report export Strategy pattern-in ideal use-case-idir. Hər format bir algorithm-dir, interface-ləri eyni: "Report al, fayl qaytarır."

`ExportStrategy` interface-i `export(Report $report): ExportedFile` metodunu müəyyən edir. `CsvExporter`, `PdfExporter`, `ExcelExporter` — hər biri bu interface-i implement edir.

`ReportGenerator` context class-ı strategy saxlayır, `generate()` metodu strategy-ni çağırır. Yeni format lazım olanda — `JsonExporter` class-ı yazılır, mövcud kod dəyişmir (OCP).

Factory ilə birləşdirmə: `ExporterFactory::make($format)` string-dən strategy seçir. Controller yalnız format string bilir, factory-nin ya strategy-nin daxilini bilmir.

### Kod Nümunəsi

```php
// ── Strategy Interface ────────────────────────────────────────────
interface ExportStrategy
{
    public function export(Report $report): ExportedFile;
    public function mimeType(): string;
    public function fileExtension(): string;
    public function supportsStreaming(): bool; // Böyük file-lar üçün
}

// ── Concrete Strategy: CSV ────────────────────────────────────────
class CsvExporter implements ExportStrategy
{
    public function export(Report $report): ExportedFile
    {
        $output = fopen('php://temp', 'r+');
        fputcsv($output, $report->headers());

        foreach ($report->rows() as $row) {
            fputcsv($output, $row);
        }

        rewind($output);
        $content = stream_get_contents($output);
        fclose($output);

        return new ExportedFile($content, $this->mimeType());
    }

    public function mimeType(): string      { return 'text/csv; charset=UTF-8'; }
    public function fileExtension(): string  { return 'csv'; }
    public function supportsStreaming(): bool { return true; }
}

// ── Concrete Strategy: PDF ────────────────────────────────────────
class PdfExporter implements ExportStrategy
{
    public function __construct(
        private readonly PdfRenderer $renderer,
        private readonly string      $template = 'reports.default',
    ) {}

    public function export(Report $report): ExportedFile
    {
        $pdf = $this->renderer->render($this->template, [
            'title'     => $report->title(),
            'headers'   => $report->headers(),
            'rows'      => $report->rows(),
            'summary'   => $report->summary(),
            'generated' => now()->toDateTimeString(),
        ]);

        return new ExportedFile($pdf, $this->mimeType());
    }

    public function mimeType(): string       { return 'application/pdf'; }
    public function fileExtension(): string  { return 'pdf'; }
    public function supportsStreaming(): bool { return false; }
}

// ── Concrete Strategy: Excel ──────────────────────────────────────
class ExcelExporter implements ExportStrategy
{
    public function __construct(private readonly SpreadsheetWriter $writer) {}

    public function export(Report $report): ExportedFile
    {
        $content = $this->writer
            ->addSheet($report->title())
            ->writeHeaders($report->headers())
            ->writeRows($report->rows())
            ->addFormulas($report->formulas())
            ->applyStyles()
            ->toXlsx();

        return new ExportedFile($content, $this->mimeType());
    }

    public function mimeType(): string      { return 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'; }
    public function fileExtension(): string { return 'xlsx'; }
    public function supportsStreaming(): bool { return false; }
}

// ── Context ───────────────────────────────────────────────────────
class ReportGenerator
{
    private ExportStrategy $strategy;

    public function __construct(ExportStrategy $strategy)
    {
        $this->strategy = $strategy;
    }

    // Runtime strategy dəyişdirməsi — A/B test, user preference
    public function setStrategy(ExportStrategy $strategy): self
    {
        $this->strategy = $strategy;
        return $this;
    }

    public function generate(Report $report): ExportedFile
    {
        // Context sadəcə delegate edir — öz məntiqi yoxdur
        return $this->strategy->export($report);
    }

    public function filename(Report $report): string
    {
        return Str::slug($report->title()) . '-' . date('Y-m-d') . '.' . $this->strategy->fileExtension();
    }
}

// ── Factory: Strategy seçim məntiqini gizlədən ────────────────────
class ExporterFactory
{
    /** @var array<string, \Closure> */
    private array $resolvers = [];

    public function __construct(private readonly Container $app) {}

    public function register(string $format, \Closure $resolver): void
    {
        $this->resolvers[$format] = $resolver;
    }

    public function make(string $format): ExportStrategy
    {
        if (!isset($this->resolvers[$format])) {
            throw new \InvalidArgumentException("Unsupported format: {$format}");
        }
        return ($this->resolvers[$format])();
    }

    public function supported(): array
    {
        return array_keys($this->resolvers);
    }
}

// ServiceProvider-da register:
public function register(): void
{
    $this->app->singleton(ExporterFactory::class, function ($app) {
        $factory = new ExporterFactory($app);
        $factory->register('csv',   fn() => new CsvExporter());
        $factory->register('pdf',   fn() => $app->make(PdfExporter::class));
        $factory->register('excel', fn() => $app->make(ExcelExporter::class));
        return $factory;
    });
}

// Yeni format əlavə etmək (plugin-dən):
$factory->register('json', fn() => new JsonExporter());
// Mövcud kod dəyişmdi — OCP!
```

```php
// ── Controller-da istifadə ────────────────────────────────────────
class ReportController extends Controller
{
    public function __construct(
        private readonly ReportGenerator $generator,
        private readonly ExporterFactory $factory,
        private readonly ReportRepository $reports,
    ) {}

    public function download(Request $request, int $reportId): StreamedResponse
    {
        $request->validate([
            'format' => 'required|in:csv,pdf,excel,json',
        ]);

        $report   = $this->reports->findOrFail($reportId);
        $strategy = $this->factory->make($request->format);

        $this->generator->setStrategy($strategy);
        $file     = $this->generator->generate($report);
        $filename = $this->generator->filename($report);

        return response()->streamDownload(
            fn() => print($file->content()),
            $filename,
            ['Content-Type' => $strategy->mimeType()]
        );
    }
}
```

```php
// ── Payment Processing — real-world Strategy ─────────────────────
interface PaymentGateway
{
    public function charge(int $cents, string $currency, PaymentToken $token): PaymentResult;
    public function refund(string $transactionId, int $cents): RefundResult;
    public function supports(string $currency): bool;
}

class StripeGateway implements PaymentGateway
{
    public function __construct(private readonly StripeClient $stripe) {}

    public function charge(int $cents, string $currency, PaymentToken $token): PaymentResult
    {
        try {
            $charge = $this->stripe->charges()->create([
                'amount'   => $cents,
                'currency' => strtolower($currency),
                'source'   => $token->value(),
            ]);
            return PaymentResult::success($charge->id, $cents);
        } catch (\Stripe\Exception\CardException $e) {
            return PaymentResult::failure($e->getMessage(), $e->getDeclineCode());
        }
    }

    public function refund(string $transactionId, int $cents): RefundResult
    {
        $refund = $this->stripe->refunds()->create([
            'charge' => $transactionId,
            'amount' => $cents,
        ]);
        return RefundResult::success($refund->id);
    }

    public function supports(string $currency): bool
    {
        return in_array(strtoupper($currency), ['USD', 'EUR', 'GBP', 'AZN']);
    }
}

class PayPalGateway implements PaymentGateway
{
    public function __construct(private readonly PayPalClient $paypal) {}

    public function charge(int $cents, string $currency, PaymentToken $token): PaymentResult
    {
        $order = $this->paypal->orders()->create([
            'intent'        => 'CAPTURE',
            'purchase_units' => [[
                'amount' => ['value' => $cents / 100, 'currency_code' => $currency],
            ]],
        ]);
        // ...
        return PaymentResult::success($order->id, $cents);
    }

    public function refund(string $transactionId, int $cents): RefundResult { /* ... */ }

    public function supports(string $currency): bool
    {
        return in_array(strtoupper($currency), ['USD', 'EUR', 'GBP']);
    }
}

// Feature flag ilə A/B testing
class PaymentGatewayResolver
{
    public function __construct(
        private readonly StripeGateway $stripe,
        private readonly PayPalGateway $paypal,
        private readonly FeatureFlags  $flags,
    ) {}

    public function forUser(User $user, string $currency): PaymentGateway
    {
        // Müəyyən user segmenti üçün yeni gateway test et
        if ($this->flags->enabled('new-payment-gateway', $user)) {
            return $this->paypal; // Yeni gateway test edilir
        }
        return $this->stripe; // Default
    }
}
```

```php
// ── Authentication Strategy ───────────────────────────────────────
interface AuthStrategy
{
    public function authenticate(Request $request): ?User;
    public function supports(Request $request): bool;
}

class JwtAuthStrategy implements AuthStrategy
{
    public function __construct(private readonly JwtParser $jwt) {}

    public function authenticate(Request $request): ?User
    {
        $token = $request->bearerToken();
        if (!$token) return null;

        $payload = $this->jwt->parse($token);
        return User::find($payload->sub);
    }

    public function supports(Request $request): bool
    {
        return !empty($request->bearerToken());
    }
}

class ApiKeyAuthStrategy implements AuthStrategy
{
    public function authenticate(Request $request): ?User
    {
        $key = $request->header('X-Api-Key');
        if (!$key) return null;

        return ApiKey::where('key', hash('sha256', $key))
            ->where('is_active', true)
            ->first()
            ?->user;
    }

    public function supports(Request $request): bool
    {
        return !empty($request->header('X-Api-Key'));
    }
}

class SessionAuthStrategy implements AuthStrategy
{
    public function authenticate(Request $request): ?User
    {
        return auth()->user();
    }

    public function supports(Request $request): bool
    {
        return $request->hasSession();
    }
}

// Chain of strategies — ilk uyğun olanı istifadə et
class AuthenticationChain
{
    /** @param AuthStrategy[] $strategies */
    public function __construct(private readonly array $strategies) {}

    public function authenticate(Request $request): ?User
    {
        foreach ($this->strategies as $strategy) {
            if ($strategy->supports($request)) {
                return $strategy->authenticate($request);
            }
        }
        return null;
    }
}
```

### Real-World Nümunə

```php
// Laravel Cache Driver — Strategy pattern-in real implementasiyası

// config/cache.php:
// 'default' => env('CACHE_DRIVER', 'redis')
// 'stores' => ['redis' => [...], 'memcached' => [...], 'database' => [...]]

// Hər driver bir Strategy (Store interface-i implement edir):
// RedisStore, MemcachedStore, DatabaseStore, ArrayStore, FileStore

// CacheManager = Context:
Cache::driver('redis')->put('key', 'value', 300);   // RedisStore
Cache::driver('memcached')->get('key');              // MemcachedStore
Cache::driver('array')->forget('key');              // ArrayStore (test üçün)

// Yeni driver — mövcud kod dəyişmir (OCP):
Cache::extend('dynamodb', function ($app) {
    return Cache::repository(new DynamoDbStore(
        $app->make(DynamoDbClient::class),
        config('cache.stores.dynamodb.table')
    ));
});

// Test-lərdə:
Cache::driver('array'); // In-memory — DB lazım deyil
// ya da:
app()->bind(Store::class, fn() => new ArrayStore());
```

### Anti-Pattern Nümunəsi

```php
// ❌ Anti-pattern: if-else chain — OCP pozulur
class ReportExporter
{
    public function export(Report $report, string $format): string
    {
        if ($format === 'csv') {
            // 30 sətir CSV logic
            $lines = [implode(',', $report->headers())];
            foreach ($report->rows() as $row) $lines[] = implode(',', $row);
            return implode("\n", $lines);

        } elseif ($format === 'pdf') {
            // 40 sətir PDF logic
            $pdf = new TCPDF();
            // ...
            return $pdf->Output('', 'S');

        } elseif ($format === 'excel') {
            // 50 sətir Excel logic
            // ...
        }
        // Yeni format lazım oldu → bu faylı aç, dəyiş, test et, review et
        // Hər dəyişiklik mövcud format-ları da poza bilər!

        throw new \InvalidArgumentException("Unknown format: {$format}");
    }
}

// ❌ Anti-pattern: Closure strategy — type safety yoxdur
class ExporterWithClosure
{
    private \Closure $exportFn;

    public function setExporter(\Closure $fn): void
    {
        $this->exportFn = $fn; // IDE hint yoxdur, interface yoxdur
    }

    public function export(Report $report): string
    {
        return ($this->exportFn)($report); // Return type bilinmir
    }
}

// ✅ Düzgün: Interface-based strategy — type safe, testable, extensible
// (yuxarıdakı ExportStrategy interface + concrete class-lar)
```

---

## Praktik Tapşırıqlar

1. Payment processor Strategy qurun: Stripe, PayPal, Braintree — `extend()` ilə yeni provider əlavə et
2. Sort strategy: `QuickSort`, `MergeSort` — array ölçüsünə görə runtime seçim; benchmark edin
3. Laravel custom cache driver əlavə edin: `Cache::extend()` ilə DynamoDB driver
4. Authentication strategy chain: Session, JWT, API Key — ilk uyğun olanı istifadə edir
5. Feature flag ilə A/B strategy: `NewPricingStrategy` vs `OldPricingStrategy` — user segmentinə görə seçim
6. Null Object Strategy implement edin: `NoopLogger`, `NoopNotifier` — optional feature-lar üçün
7. Report export-u `ExporterFactory` ilə tam implement edin: test-də `InMemoryExporter` inject edin
8. Closure-based vs class-based strategy test edin: type safety, mock etmə, IDE autocompletion trade-off-larını sənədləyin

## Əlaqəli Mövzular
- [SOLID Principles](01-solid-principles.md) — Strategy OCP tətbiqi
- [Factory Patterns](02-factory-patterns.md) — Strategy yaratmaq üçün factory
- [Observer/Event Pattern](04-observer-event.md) — Event handler strategy
- [Singleton Pattern](03-singleton-pattern.md) — Singleton context ilə strategy birlikdə
