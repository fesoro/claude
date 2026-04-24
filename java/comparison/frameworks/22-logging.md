# Logging (Loglama)

> **Seviyye:** Intermediate ⭐⭐

## Giris

Logging tetbiqin isleme prosesini izlemek, xetalari tapmaq ve sistemin veziyyetini anlamaq ucun en vacib vasitelerden biridir. Production muhitinde tetbiqin ne etdiyini gormek ucun yeganam etibarlı vasitedir.

Spring ekosisteminde SLF4J (Simple Logging Facade for Java) ve Logback istifade olunur. Laravel-de ise Monolog kitabxanasi uzerinde qurulmus `Log` facade-i istifade olunur. Her iki framework bir nece log seviyyesini (level) desdekleyir ve muxtelif log hedeflerine (fayl, konsol, xarici servisler) yazmaga imkan verir.

## Spring-de istifadesi

### Esas istifade

Spring Boot default olaraq SLF4J + Logback istifade edir:

```java
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;

@Service
public class OrderService {

    private static final Logger log =
        LoggerFactory.getLogger(OrderService.class);

    public Order createOrder(OrderDto dto) {
        log.debug("Sifaris yaradilir: {}", dto);

        try {
            Order order = new Order();
            order.setUserId(dto.getUserId());
            order.setTotal(dto.getTotal());
            Order saved = orderRepository.save(order);

            log.info("Sifaris ugurla yaradildi: id={}, total={}",
                     saved.getId(), saved.getTotal());

            return saved;
        } catch (Exception e) {
            log.error("Sifaris yaradila bilmedi: userId={}",
                      dto.getUserId(), e);
            throw e;
        }
    }
}

// Lombok ile daha qisa:
@Slf4j
@Service
public class ProductService {

    public Product findById(Long id) {
        log.info("Mehsul axtarilir: id={}", id);
        // log degisheni avtomatik yaradilir
        return productRepository.findById(id)
            .orElseThrow(() -> {
                log.warn("Mehsul tapilmadi: id={}", id);
                return new ProductNotFoundException(id);
            });
    }
}
```

### Log seviyeleri

```java
@Service
public class PaymentService {

    private static final Logger log =
        LoggerFactory.getLogger(PaymentService.class);

    public PaymentResult processPayment(PaymentRequest request) {
        // TRACE -- en tefesilatlı, development ucun
        log.trace("Odeme metodu yoxlanilir: {}",
                  request.getPaymentMethod());

        // DEBUG -- debugging ucun tefsirli melumat
        log.debug("Odeme sorgusu hazirlanir: amount={}, currency={}",
                  request.getAmount(), request.getCurrency());

        // INFO -- normal is axisi
        log.info("Odeme emali bashladi: orderId={}",
                 request.getOrderId());

        try {
            PaymentResult result = gateway.charge(request);

            if (result.isSuccess()) {
                // INFO -- ugurlu emeliyyat
                log.info("Odeme ugurlu: txnId={}", result.getTxnId());
            } else {
                // WARN -- ugursuz amma gozlenilen veziyyet
                log.warn("Odeme imtina edildi: reason={}",
                         result.getDeclineReason());
            }
            return result;

        } catch (PaymentGatewayException e) {
            // ERROR -- xeta, muxakkiq lazimdir
            log.error("Odeme gateway xetasi: orderId={}, error={}",
                      request.getOrderId(), e.getMessage(), e);
            throw e;
        }
    }
}
```

### Logback konfiqurasiyasi

**logback-spring.xml:**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<configuration>

    <!-- Konsol appender -->
    <appender name="CONSOLE"
              class="ch.qos.logback.core.ConsoleAppender">
        <encoder>
            <pattern>
                %d{yyyy-MM-dd HH:mm:ss.SSS} [%thread] %-5level
                %logger{36} - %msg%n
            </pattern>
        </encoder>
    </appender>

    <!-- Fayl appender (gunluk rotation ile) -->
    <appender name="FILE"
              class="ch.qos.logback.core.rolling.RollingFileAppender">
        <file>logs/application.log</file>
        <rollingPolicy
            class="ch.qos.logback.core.rolling.TimeBasedRollingPolicy">
            <fileNamePattern>
                logs/application.%d{yyyy-MM-dd}.log
            </fileNamePattern>
            <maxHistory>30</maxHistory>
            <totalSizeCap>3GB</totalSizeCap>
        </rollingPolicy>
        <encoder>
            <pattern>
                %d{yyyy-MM-dd HH:mm:ss.SSS} [%thread] %-5level
                %logger{36} - %msg%n
            </pattern>
        </encoder>
    </appender>

    <!-- JSON format (ELK stack ucun) -->
    <appender name="JSON_FILE"
              class="ch.qos.logback.core.rolling.RollingFileAppender">
        <file>logs/application.json</file>
        <rollingPolicy
            class="ch.qos.logback.core.rolling.SizeAndTimeBasedRollingPolicy">
            <fileNamePattern>
                logs/application.%d{yyyy-MM-dd}.%i.json
            </fileNamePattern>
            <maxFileSize>100MB</maxFileSize>
            <maxHistory>7</maxHistory>
        </rollingPolicy>
        <encoder
            class="net.logstash.logback.encoder.LogstashEncoder"/>
    </appender>

    <!-- Paket seviyyesinde log -->
    <logger name="com.example.myapp" level="DEBUG"/>
    <logger name="org.springframework" level="WARN"/>
    <logger name="org.hibernate.SQL" level="DEBUG"/>

    <!-- Profile-e gore konfiqurasiya -->
    <springProfile name="dev">
        <root level="DEBUG">
            <appender-ref ref="CONSOLE"/>
        </root>
    </springProfile>

    <springProfile name="prod">
        <root level="INFO">
            <appender-ref ref="FILE"/>
            <appender-ref ref="JSON_FILE"/>
        </root>
    </springProfile>

</configuration>
```

**application.yml ile sade konfiqurasiya:**

```yaml
logging:
  level:
    root: INFO
    com.example.myapp: DEBUG
    org.springframework.web: WARN
    org.hibernate.SQL: DEBUG
  file:
    name: logs/application.log
    max-size: 100MB
    max-history: 30
  pattern:
    console: "%d{HH:mm:ss.SSS} [%thread] %-5level %logger{36} - %msg%n"
    file: "%d{yyyy-MM-dd HH:mm:ss.SSS} [%thread] %-5level %logger{36} - %msg%n"
```

### MDC (Mapped Diagnostic Context)

MDC her thread ucun kontekstual melumat saxlamaga imkan verir -- meselen, request ID:

```java
// Filter -- her request ucun MDC set etmek
@Component
public class MdcFilter extends OncePerRequestFilter {

    @Override
    protected void doFilterInternal(
            HttpServletRequest request,
            HttpServletResponse response,
            FilterChain chain) throws ServletException, IOException {

        String requestId = request.getHeader("X-Request-ID");
        if (requestId == null) {
            requestId = UUID.randomUUID().toString();
        }

        MDC.put("requestId", requestId);
        MDC.put("userId", getCurrentUserId());
        MDC.put("clientIp", request.getRemoteAddr());

        try {
            response.setHeader("X-Request-ID", requestId);
            chain.doFilter(request, response);
        } finally {
            MDC.clear(); // Mutleq temizle!
        }
    }
}

// Logback pattern-de MDC istifade etmek:
// %d [%thread] [requestId=%X{requestId}] [userId=%X{userId}]
//     %-5level %logger{36} - %msg%n

// Indi her log mesajinda requestId ve userId gorsenecek:
// 2024-01-15 10:30:00 [http-nio-8080-1]
//     [requestId=abc-123] [userId=42]
//     INFO OrderService - Sifaris yaradildi: id=100
```

```java
// Service-lerde MDC-den oxumaq
@Service
public class AuditService {

    public void logAction(String action) {
        String requestId = MDC.get("requestId");
        String userId = MDC.get("userId");
        log.info("Audit: action={}, requestId={}, userId={}",
                 action, requestId, userId);
    }
}
```

### Custom Appender

```java
// Xarici servise log gondermek ucun custom appender
public class SlackAppender extends AppenderBase<ILoggingEvent> {

    private String webhookUrl;
    private String channel;

    @Override
    protected void append(ILoggingEvent event) {
        if (event.getLevel().isGreaterOrEqual(Level.ERROR)) {
            String message = String.format(
                "[%s] %s: %s",
                event.getLevel(),
                event.getLoggerName(),
                event.getFormattedMessage()
            );
            sendToSlack(message);
        }
    }

    // setter-ler Logback XML-den konfiqurasiya ucun lazimdir
    public void setWebhookUrl(String url) { this.webhookUrl = url; }
    public void setChannel(String ch) { this.channel = ch; }
}
```

## Laravel-de istifadesi

### Esas istifade

```php
use Illuminate\Support\Facades\Log;

class OrderService
{
    public function createOrder(array $data): Order
    {
        Log::debug('Sifaris yaradilir', ['data' => $data]);

        try {
            $order = Order::create($data);

            Log::info('Sifaris ugurla yaradildi', [
                'order_id' => $order->id,
                'total' => $order->total,
                'user_id' => $order->user_id,
            ]);

            return $order;
        } catch (\Exception $e) {
            Log::error('Sifaris yaradila bilmedi', [
                'data' => $data,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
```

### Log seviyeleri

```php
class PaymentService
{
    public function processPayment(PaymentRequest $request): PaymentResult
    {
        // Seviyelere gore log
        Log::emergency('Sistem ishlemir!');    // 0 - en ciddi
        Log::alert('Dehal mudaxile lazimdir'); // 1
        Log::critical('Kritik xeta');           // 2
        Log::error('Xeta bash verdi');          // 3
        Log::warning('Diqqet edilecesk');       // 4
        Log::notice('Normal amma muhum');       // 5
        Log::info('Melumat mesaji');             // 6
        Log::debug('Debug mesaji');              // 7

        // Kontekst ile
        Log::info('Odeme emali bashladi', [
            'order_id' => $request->order_id,
            'amount' => $request->amount,
            'method' => $request->payment_method,
        ]);
    }
}
```

### Log Channels (Kanallar)

**config/logging.php:**

```php
return [
    'default' => env('LOG_CHANNEL', 'stack'),

    'channels' => [
        // Stack -- birden cox kanali birleshdir
        'stack' => [
            'driver' => 'stack',
            'channels' => ['daily', 'slack'],
            'ignore_exceptions' => false,
        ],

        // Gunluk fayl (rotation ile)
        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 14, // 14 gunluk saxla
        ],

        // Tek fayl
        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => 'debug',
        ],

        // Slack
        'slack' => [
            'driver' => 'slack',
            'url' => env('LOG_SLACK_WEBHOOK_URL'),
            'username' => 'Laravel Log',
            'emoji' => ':boom:',
            'level' => 'error', // Yalniz error ve yuxari
        ],

        // Stderr (Docker/container ucun)
        'stderr' => [
            'driver' => 'monolog',
            'level' => 'debug',
            'handler' => StreamHandler::class,
            'with' => [
                'stream' => 'php://stderr',
            ],
        ],

        // Syslog
        'syslog' => [
            'driver' => 'syslog',
            'level' => 'debug',
        ],

        // Custom kanal -- ayri fayla yaz
        'payments' => [
            'driver' => 'daily',
            'path' => storage_path('logs/payments.log'),
            'level' => 'info',
            'days' => 30,
        ],

        'audit' => [
            'driver' => 'daily',
            'path' => storage_path('logs/audit.log'),
            'level' => 'info',
            'days' => 90,
        ],
    ],
];
```

### Museyyen kanala yazmaq

```php
class PaymentService
{
    public function charge(Order $order): PaymentResult
    {
        // Default kanala yaz
        Log::info('Odeme bashladi', ['order_id' => $order->id]);

        // Museyyen kanala yaz
        Log::channel('payments')->info('Odeme emali', [
            'order_id' => $order->id,
            'amount' => $order->total,
            'method' => $order->payment_method,
        ]);

        // Birden cox kanala eyni anda yaz
        Log::stack(['daily', 'slack'])->error('Odeme ugursuz', [
            'order_id' => $order->id,
            'error' => 'Insufficient funds',
        ]);
    }
}
```

### Custom Monolog Channel

```php
// config/logging.php
'custom_json' => [
    'driver' => 'monolog',
    'handler' => StreamHandler::class,
    'with' => [
        'stream' => storage_path('logs/json.log'),
    ],
    'formatter' => JsonFormatter::class,
    'formatter_with' => [
        'includeStacktraces' => true,
    ],
],

// Custom channel factory
'custom' => [
    'driver' => 'custom',
    'via' => App\Logging\CreateCustomLogger::class,
    'level' => 'info',
],
```

```php
// app/Logging/CreateCustomLogger.php
namespace App\Logging;

use Monolog\Logger;
use Monolog\Handler\TelegramBotHandler;

class CreateCustomLogger
{
    public function __invoke(array $config): Logger
    {
        $logger = new Logger('telegram');

        $logger->pushHandler(new TelegramBotHandler(
            apiKey: config('services.telegram.bot_token'),
            channel: config('services.telegram.log_channel'),
            level: $config['level'] ?? 'error'
        ));

        return $logger;
    }
}
```

### Contextual Logging

```php
// Laravel 10+ ile kontekstual loglama
class OrderController extends Controller
{
    public function store(OrderRequest $request): JsonResponse
    {
        // Bu kontekst butun sonraki log mesajlarina elave olunacaq
        Log::withContext([
            'request_id' => $request->header('X-Request-ID'),
            'user_id' => auth()->id(),
            'ip' => $request->ip(),
        ]);

        // Indi her log mesajinda bu kontekst olacaq
        Log::info('Sifaris yaradilir');
        // Output: [2024-01-15] INFO: Sifaris yaradilir
        //   {"request_id":"abc-123","user_id":42,"ip":"192.168.1.1"}

        $order = $this->orderService->create($request->validated());

        Log::info('Sifaris yaradildi', ['order_id' => $order->id]);
        // Kontekst avtomatik elave olunur

        return response()->json($order, 201);
    }
}

// Middleware ile qlobal kontekst
class AddRequestContext
{
    public function handle(Request $request, Closure $next)
    {
        Log::withContext([
            'request_id' => (string) Str::uuid(),
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'user_id' => $request->user()?->id,
        ]);

        return $next($request);
    }
}
```

### Sharing Context between Channels

```php
// Shared context butun kanallarda gorsenecek
Log::shareContext([
    'app_version' => config('app.version'),
    'environment' => config('app.env'),
    'hostname' => gethostname(),
]);
```

## Esas ferqler

| Xususiyyet | Spring | Laravel |
|---|---|---|
| **Logging facade** | SLF4J | `Log` facade |
| **Backend** | Logback (default) | Monolog |
| **Konfiqurasiya** | XML / application.yml | PHP array (config/logging.php) |
| **Log seviyeleri** | TRACE, DEBUG, INFO, WARN, ERROR | emergency, alert, critical, error, warning, notice, info, debug |
| **Kontekst** | MDC (Mapped Diagnostic Context) | `Log::withContext()` |
| **Kanallar** | Appender-ler (XML ile) | Channel-ler (PHP array ile) |
| **JSON format** | Logstash encoder | JsonFormatter |
| **Log rotation** | Logback rolling policy | `daily` driver |
| **Custom handler** | Custom Appender sinifi | `driver => 'custom'` + factory |
| **Paket filter** | Logger adi ile | Yoxdur (manual) |

## Niye bele ferqler var?

**SLF4J vs Log Facade:**
Java dunyasinda bir nece logging framework var (Log4j, Logback, JUL). SLF4J bunlarin uzerinde abstraction layer-dir -- hansi implementation istifade olunmasina baxmayaraq eyni API istifade olunur. Laravel-de ise Monolog de-facto standartdir ve `Log` facade-i onun uzerine qurulub. Java-nin coxlu secim problemi PHP-de movcud deyil.

**MDC vs withContext:**
Spring-in MDC-si thread-based-dir -- her thread oz kontekstini daşıyır. Bu, multi-threaded server muhitinde (Tomcat, Jetty) her request-in oz kontekstini saxlamaga imkan verir. Laravel-de PHP request-per-process modeli oldugu ucun `withContext()` sade array kimi ishleyir -- threading problemi yoxdur.

**XML vs PHP konfiqurasiya:**
Logback konfiqurasiyasi XML-dir -- bu daha verboz-dur amma guclu imkanlar verir (shertli konfiqurasiya, profiller, degishkenler). Laravel PHP array istifade edir -- daha oxunaqli ve sade, amma Logback-in bezi guclu xususiyyetleri (meselen, shertli appender secimi) burada yoxdur.

**Log seviyeleri:**
Spring 5 seviyye istifade edir (TRACE, DEBUG, INFO, WARN, ERROR). Laravel PSR-3 standartina uygun 8 seviyye istifade edir (emergency, alert, critical, error, warning, notice, info, debug). Laravel-in daha cox seviyyesi RFC 5424 (Syslog) standartindan gelir.

## Hansi framework-de var, hansinda yoxdur?

**Yalniz Spring-de:**
- MDC (Mapped Diagnostic Context) -- thread-based kontekst
- TRACE log seviyyesi -- DEBUG-dan daha tefsirli
- Logback XML ile guclu konfiqurasiya (shertli logika, profiller)
- Paket/sinif seviyyesinde log level teyin etmek (meselan, `com.example.dao` ucun DEBUG, `org.springframework` ucun WARN)
- `@Slf4j` (Lombok) ile avtomatik logger yaratma
- Marker-based logging -- log mesajlarini kategoriyalashdirmaq

**Yalniz Laravel-de:**
- `Log::channel()` ile runtime-da kanal secmek
- `Log::stack()` ile bir nece kanala eyni anda yazmaq
- `Log::shareContext()` -- butun kanallarda paylasilam kontekst
- `emergency`, `alert`, `critical`, `notice` kimi elave log seviyeleri
- Slack, Telegram kimi servislere inteqrasiya konfiqurasiya ile
- `driver => 'custom'` ile factory pattern-le custom channel yaratmaq
- `LOG_CHANNEL` environment variable ile kanal deyishdirmek
