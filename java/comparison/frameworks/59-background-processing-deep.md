# Background Processing — Dərin Müqayisə

> **Seviyye:** Advanced ⭐⭐⭐

## Giriş

Background processing — "bu HTTP sorğuda dərhal icra etməyə ehtiyac yoxdur" deyilən işlər: email göndərmək, PDF generate etmək, video encode etmək, hesabat hazırlamaq. Əsas prinsiplər hər iki ekosistemdə eynidir (queue, worker, retry, backoff, scheduling), amma texniki həllər çox fərqlidir.

Spring dünyasında **`@Async`** (sadə tapşırıqlar), **Spring Batch** (milyonlarla qeyd emal etmək), **Quartz** (kron əvəzi), **Spring Scheduler** (sadə cron) var. Laravel-də isə **Queues** (Redis/SQS/DB), **Horizon** (Redis dashboard), **Scheduler** (`app/Console/Kernel.php`) və Octane ilə long-running modellər var.

---

## Spring-də istifadəsi

### 1) `@Async` — sadə fon tapşırıqları

```java
@Configuration
@EnableAsync
public class AsyncConfig implements AsyncConfigurer {

    @Override
    @Bean(name = "taskExecutor")
    public Executor getAsyncExecutor() {
        ThreadPoolTaskExecutor executor = new ThreadPoolTaskExecutor();
        executor.setCorePoolSize(10);
        executor.setMaxPoolSize(50);
        executor.setQueueCapacity(500);
        executor.setThreadNamePrefix("async-");
        executor.setRejectedExecutionHandler(new ThreadPoolExecutor.CallerRunsPolicy());
        executor.initialize();
        return executor;
    }

    @Bean(name = "emailExecutor")
    public Executor emailExecutor() {
        // Ayrıca pool — email üçün
        ThreadPoolTaskExecutor executor = new ThreadPoolTaskExecutor();
        executor.setCorePoolSize(5);
        executor.setMaxPoolSize(20);
        executor.setThreadNamePrefix("email-");
        executor.initialize();
        return executor;
    }
}

@Service
public class EmailService {

    @Async("emailExecutor")
    public CompletableFuture<Void> sendWelcomeEmail(User user) {
        // Uzun çəkən əməliyyat
        mailSender.send(createMessage(user));
        return CompletableFuture.completedFuture(null);
    }

    @Async
    public CompletableFuture<Report> generateReport(Long reportId) {
        Report report = reportGenerator.generate(reportId);
        return CompletableFuture.completedFuture(report);
    }
}

// İstifadə
emailService.sendWelcomeEmail(user)
    .exceptionally(ex -> {
        log.error("Email göndərilmədi", ex);
        return null;
    });
```

**Məhdudiyyət:** `@Async` yaddaşda işləyir — tətbiq restart olunsa, işlər itir. Mühüm job-lar üçün Spring Batch, Quartz və ya xarici message broker lazımdır.

### 2) Spring Scheduler — cron-style tapşırıqlar

```java
@Configuration
@EnableScheduling
public class SchedulerConfig {
    // Scheduler thread pool
    @Bean
    public TaskScheduler taskScheduler() {
        ThreadPoolTaskScheduler scheduler = new ThreadPoolTaskScheduler();
        scheduler.setPoolSize(10);
        scheduler.setThreadNamePrefix("scheduler-");
        return scheduler;
    }
}

@Component
public class ScheduledJobs {

    @Scheduled(fixedRate = 60000)              // Hər 60 saniyədə
    public void healthCheck() { ... }

    @Scheduled(fixedDelay = 30000, initialDelay = 10000)
    public void cleanupCache() { ... }

    @Scheduled(cron = "0 0 2 * * *")           // Hər gecə saat 02:00
    public void nightlyReport() { ... }

    @Scheduled(cron = "0 */15 * * * *")        // Hər 15 dəqiqədə
    @SchedulerLock(name = "syncInventory", lockAtLeastFor = "PT1M", lockAtMostFor = "PT14M")
    public void syncInventory() {
        // ShedLock — distributed lock (çoxlu node olsa belə yalnız bir instansiya işləsin)
    }
}
```

### 3) Quartz Scheduler — enterprise-level

```xml
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-quartz</artifactId>
</dependency>
```

```java
@Configuration
public class QuartzConfig {

    @Bean
    public JobDetail reportJob() {
        return JobBuilder.newJob(ReportJob.class)
            .withIdentity("nightlyReport")
            .storeDurably()
            .build();
    }

    @Bean
    public Trigger reportTrigger(JobDetail reportJob) {
        return TriggerBuilder.newTrigger()
            .forJob(reportJob)
            .withIdentity("nightlyReportTrigger")
            .withSchedule(CronScheduleBuilder.cronSchedule("0 0 2 * * ?"))
            .build();
    }
}

@Component
public class ReportJob extends QuartzJobBean {
    @Override
    protected void executeInternal(JobExecutionContext context) {
        JobDataMap data = context.getMergedJobDataMap();
        // Job-a parametr ötürülə bilər
    }
}
```

Quartz üstünlükləri:
- DB-də persistent (JobStore)
- Misfire handling (server durdu və qaçırıldı → necə davransın)
- Cluster mode — çoxlu server arasında yük bölüşdürülür
- Job chaining, triggers

### 4) Spring Batch — ETL və batch processing

Spring Batch milyonlarla qeyd emal etmək üçün yaradılıb — import, export, ETL, hesabat.

```xml
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-batch</artifactId>
</dependency>
```

```java
@Configuration
public class ImportCustomersBatch {

    @Bean
    public Job importCustomersJob(JobRepository repo, Step importStep) {
        return new JobBuilder("importCustomers", repo)
            .start(importStep)
            .incrementer(new RunIdIncrementer())
            .listener(new ImportJobListener())
            .build();
    }

    @Bean
    public Step importStep(JobRepository repo, PlatformTransactionManager tx,
                           ItemReader<CustomerCsv> reader,
                           ItemProcessor<CustomerCsv, Customer> processor,
                           ItemWriter<Customer> writer) {
        return new StepBuilder("importStep", repo)
            .<CustomerCsv, Customer>chunk(1000, tx)       // 1000-lik chunk
            .reader(reader)
            .processor(processor)
            .writer(writer)
            .faultTolerant()
            .retry(DeadlockLoserDataAccessException.class)
            .retryLimit(3)
            .skip(ValidationException.class)
            .skipLimit(100)                               // 100 xəta icazə
            .listener(new StepLoggingListener())
            .taskExecutor(new SimpleAsyncTaskExecutor())  // paralel chunk
            .build();
    }

    @Bean
    public FlatFileItemReader<CustomerCsv> reader() {
        return new FlatFileItemReaderBuilder<CustomerCsv>()
            .name("customerReader")
            .resource(new ClassPathResource("customers.csv"))
            .delimited()
            .names("firstName", "lastName", "email")
            .targetType(CustomerCsv.class)
            .build();
    }

    @Bean
    public ItemProcessor<CustomerCsv, Customer> processor() {
        return csv -> {
            if (! isValidEmail(csv.email())) {
                throw new ValidationException("Yanlış email");
            }
            return new Customer(csv.firstName(), csv.lastName(), csv.email().toLowerCase());
        };
    }

    @Bean
    public JdbcBatchItemWriter<Customer> writer(DataSource ds) {
        return new JdbcBatchItemWriterBuilder<Customer>()
            .dataSource(ds)
            .sql("INSERT INTO customers(first_name, last_name, email) VALUES (?, ?, ?)")
            .itemPreparedStatementSetter((item, ps) -> {
                ps.setString(1, item.getFirstName());
                ps.setString(2, item.getLastName());
                ps.setString(3, item.getEmail());
            })
            .build();
    }
}
```

Spring Batch xüsusiyyətləri:
- **Chunk-oriented** — bir neçə item oxu, emal et, bir transaction-da yaz
- **Restart** — yarıda dayanan job dəqiq orda davam edir
- **Retry + Skip** — problemli item-ləri atla, qalanlarını emal et
- **Partitioning** — bir step-i bir neçə node arasında bölmək
- **JobRepository** — job run-ları DB-də saxlanır, tarix görünür

### 5) Message broker — Kafka, RabbitMQ, SQS

Enterprise səviyyə asenxron işlər üçün:

```java
@Component
public class OrderEventListener {

    @KafkaListener(topics = "orders.created", groupId = "order-service")
    public void onOrderCreated(OrderEvent event, Acknowledgment ack) {
        try {
            processOrder(event);
            ack.acknowledge();
        } catch (Exception e) {
            // DLQ-a göndər
            kafkaTemplate.send("orders.dlq", event);
            ack.acknowledge();
        }
    }
}
```

---

## Laravel-də istifadəsi

### 1) Queue-lar — əsas iş

```bash
# Driver: database, redis, sqs, beanstalkd, rabbitmq
php artisan queue:table && php artisan migrate
```

```php
// .env
QUEUE_CONNECTION=redis

// config/queue.php
'redis' => [
    'driver' => 'redis',
    'connection' => 'default',
    'queue' => env('REDIS_QUEUE', 'default'),
    'retry_after' => 90,
    'block_for' => 5,
],
```

```php
// app/Jobs/SendWelcomeEmail.php
class SendWelcomeEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 5;
    public $backoff = [10, 30, 60, 120, 300];    // exponential
    public $timeout = 60;
    public $maxExceptions = 3;
    public $failOnTimeout = true;

    public function __construct(public User $user) {}

    public function handle(Mailer $mailer): void
    {
        $mailer->to($this->user->email)->send(new WelcomeMail($this->user));
    }

    public function failed(\Throwable $e): void
    {
        Log::error('Welcome email failed', ['user' => $this->user->id, 'e' => $e]);
        Notification::route('slack', config('services.slack.webhook'))
            ->notify(new JobFailedNotification(self::class, $e));
    }

    // Middleware ilə davranış
    public function middleware(): array
    {
        return [
            new RateLimited('emails'),                  // rate limit
            new WithoutOverlapping($this->user->id),    // eyni user üçün üst-üstə düşməsin
            (new ThrottlesExceptions(10, 5))->backoff(5),
        ];
    }

    // Tags (Horizon-da görmək üçün)
    public function tags(): array
    {
        return ['email', 'user:' . $this->user->id];
    }

    // Unique job — eyni key olan job-lar bir dəfədən çox queue-da olmasın
    public function uniqueId(): string
    {
        return 'welcome-email:' . $this->user->id;
    }

    public function uniqueFor(): int { return 3600; }
}

// İstifadə
dispatch(new SendWelcomeEmail($user));
dispatch(new SendWelcomeEmail($user))->delay(now()->addMinutes(5));
dispatch(new SendWelcomeEmail($user))->onQueue('high');
dispatch(new SendWelcomeEmail($user))->onConnection('sqs');
```

### 2) Horizon — Redis queue dashboard

```bash
composer require laravel/horizon
php artisan horizon:install
```

```php
// config/horizon.php
'environments' => [
    'production' => [
        'supervisor-1' => [
            'maxProcesses' => 10,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 128,
            'tries' => 1,
            'timeout' => 60,
            'nice' => 0,
            'balance' => 'auto',                  // auto scaling
            'minProcesses' => 1,
            'balanceMaxShift' => 1,
            'balanceCooldown' => 3,
            'autoScalingStrategy' => 'time',      // 'size' və ya 'time'
            'queue' => ['high', 'default', 'low'],
        ],
        'emails-supervisor' => [
            'maxProcesses' => 5,
            'queue' => ['emails'],
            'memory' => 256,
        ],
    ],
],

// Failed job retention
'trim' => [
    'recent' => 60,          // dəqiqə
    'pending' => 60,
    'completed' => 60,
    'recent_failed' => 10080,
    'failed' => 10080,       // həftə
],
```

Horizon-un verdiyi:
- Auto-scaling (yük çoxalanda daha çox process)
- Throughput, wait time, runtime metrikləri
- Per-queue və per-job statistikası
- Failed job-ları yenidən göndərmək
- Tags ilə job axtarışı

```bash
php artisan horizon                    # işə sal
php artisan horizon:pause              # dayandır
php artisan horizon:continue           # davam et
php artisan horizon:terminate          # graceful stop
```

### 3) Job batching

```php
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;

$batch = Bus::batch([
    new ProcessPodcast($podcast1),
    new ProcessPodcast($podcast2),
    new ProcessPodcast($podcast3),
])
->name('Import Podcasts')
->allowFailures()
->onQueue('media')
->progress(function (Batch $batch) {
    broadcast(new BatchProgress($batch->id, $batch->progress()));
})
->then(function (Batch $batch) {
    // Hamısı uğurla
    Mail::to('admin@example.com')->send(new ImportDone($batch->id));
})
->catch(function (Batch $batch, \Throwable $e) {
    Log::error('Batch failed', ['id' => $batch->id, 'e' => $e]);
})
->finally(function (Batch $batch) {
    // Uğurlu olsa da, olmasa da
})
->dispatch();

return response()->json(['batch_id' => $batch->id]);
```

### 4) Job chaining

```php
use Illuminate\Support\Facades\Bus;

Bus::chain([
    new GeneratePdf($order),
    new AttachPdfToEmail($order),
    new SendInvoiceEmail($order),
])
->catch(function (\Throwable $e) {
    // Hansısa zəncir üzvü uğursuz olsa
})
->dispatch();
```

### 5) Scheduler

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule): void
{
    $schedule->command('cache:prune-stale-tags')->hourly();

    $schedule->call(fn () => DB::table('audits')->where('created_at', '<', now()->subMonths(3))->delete())
        ->daily()
        ->name('prune-audits')
        ->onOneServer()                  // Çoxlu serverdə yalnız bir dəfə
        ->withoutOverlapping(10);

    $schedule->job(new GenerateDailyReport(), 'reports')
        ->cron('0 2 * * *')              // Hər gecə saat 02:00
        ->timezone('Asia/Baku')
        ->environments(['production']);

    $schedule->command('horizon:snapshot')->everyFiveMinutes();

    $schedule->call(fn () => logger('heartbeat'))
        ->everyMinute()
        ->pingBefore('https://uptime.com/ping/xxx/start')
        ->thenPing('https://uptime.com/ping/xxx/complete');
}
```

```bash
# Cron-da yalnız tək giriş
* * * * * cd /app && php artisan schedule:run >> /dev/null 2>&1
```

### 6) Unique jobs, rate limited, throttled

```php
class ProcessPayment implements ShouldQueue, ShouldBeUnique
{
    public $uniqueFor = 3600;
    public int $uniqueViaLock = true;

    public function uniqueId(): string
    {
        return "payment:{$this->order->id}";
    }
}
```

### 7) Long-running jobs + memory leaks

PHP-də worker proses uzun müddət işləyəndə yaddaş sızıntısı ola bilər (ORM cache, Guzzle, DB pool). Laravel bunu belə həll edir:

```php
// Horizon supervisor-da
'memory' => 128,    // MB — keçsə worker restart
'maxTime' => 3600,  // saniyə — sonra restart
'maxJobs' => 1000,  // job sayı — sonra restart
```

```bash
# Manual
php artisan queue:work --memory=128 --timeout=60 --tries=3 --max-jobs=1000 --max-time=3600
```

Octane ilə (RoadRunner/Swoole) worker-lər long-running-dir — Laravel framework-u hər sorğu üçün boot etmir. Amma bu da yaddaş sızıntısı riski deməkdir:

```php
// Octane event listener — yaddaşı təmizlə
Octane::tick('cleanup', function () {
    gc_collect_cycles();
    EntityManager::getInstance()->clear();
})->seconds(60);
```

### 8) Database-driven queues — sadə start

```bash
QUEUE_CONNECTION=database
php artisan queue:table
php artisan queue:failed-table
php artisan migrate
```

Redis olmadan işləmək istəyirsənsə — DB-də jobs cədvəli yaradılır. Prod-da Redis və ya SQS tövsiyə olunur.

---

## Əsas fərqlər

| Xüsusiyyət | Spring | Laravel |
|---|---|---|
| Sadə async | `@Async` + thread pool | `dispatch()` + queue worker |
| Kron scheduler | `@Scheduled(cron=...)` | `schedule:run` in `Kernel.php` |
| Distributed lock | ShedLock (3rd party) | `onOneServer()` + `withoutOverlapping()` |
| Enterprise scheduler | Quartz (DB-persistent, misfire) | Queue + `delay()` |
| Batch processing | Spring Batch (chunk, restart, skip) | `Bus::batch()` |
| Job chaining | Spring Batch `next()` | `Bus::chain()` |
| Retry | Manual və ya `@Retryable` | `$tries`, `$backoff` array |
| Unique jobs | Manual (distributed lock ilə) | `ShouldBeUnique` interface |
| Rate limited | `@RateLimiter` | `RateLimited` middleware |
| Dashboard | Actuator + Spring Batch Admin | Horizon (daha zəngin) |
| Workers | Thread pool daxilində (eyni JVM) | Ayrıca proses (PHP-FPM xaricində) |
| Memory leaks | JVM GC idarə edir | Worker restart strategy lazımdır |
| Message broker | Kafka/RabbitMQ/JMS first-class | Queue driver kimi (rabbitmq paketi) |
| Dead letter queue | Broker səviyyəsində | `failed_jobs` cədvəli |

---

## Niyə belə fərqlər var?

**JVM-in uzun-ömürlü prosesi.** Spring tətbiqi bir JVM proses kimi işləyir — thread pool saxlaya, `@Async` ilə metodu asinxron etmək kifayətdir. Job state yaddaşdadır, restart olunanda itir. Davamlılıq lazımdırsa, message broker və ya DB lazımdır.

**PHP-nin queue-first yanaşması.** PHP-də hər HTTP sorğu ayrı prosesdir — background thread yaratmaq yoxdur. Buna görə Laravel "queue-first" fəlsəfə seçib: hər uzun iş queue-a göndərilir, ayrıca worker (php artisan queue:work) icra edir. Bu, PHP limitini üstünlüyə çevirir.

**Spring Batch nə üçün var?** Java enterprise mühitində milyon-record import/export hallarında (finans, insurance) çox önəmlidir. Chunk-oriented processing, restart, partitioning kimi konseptlər bu mühitdən gəlir. Laravel-də `Bus::batch()` var, amma Spring Batch kimi "restart dəqiq qaldığı yerdən" imkanı yoxdur — əvəzinə idempotent job design təklif olunur.

**Horizon-un Spring-də ekvivalenti yoxdur.** Horizon auto-scaling, throughput izləmə, per-queue supervisor, failed retry web UI verir. Spring-də bunun üçün Spring Batch Admin (deprecated), custom actuator endpoint-lər və ya Grafana dashboard-lar yaradılır. Horizon daha "opinionated" və cari.

**Memory model.** JVM-də GC avtomatik işləyir — uzun-ömürlü worker problemsizdir. PHP-də isə OPCache və request-per-process modelinə görə long-running worker sızıntı riski yaradır. Buna görə Laravel worker-ləri `maxJobs`/`maxTime`/`memory` limitlərinə çatanda restart olunur.

**Distributed lock.** Çoxlu node scheduler işlədəndə eyni cron tapşırığı birdən çox işlənməsin. Spring-də ShedLock (3rd party paket) lazımdır. Laravel `onOneServer()` daxili — cache lock istifadə edir (Redis/DB). Laravel daha sadə gəlir, amma ShedLock daha çevikdir (fərqli backend-lər dəstəkləyir).

---

## Hansı framework-də var, hansında yoxdur?

**Yalnız Spring-də:**
- Spring Batch — milyonlarla qeyd üçün ETL framework
- Chunk-oriented processing (read-process-write pattern)
- Job restart — yarıda dayanan job dəqiq orda davam edir
- Quartz cluster mode — DB-persistent distributed scheduler
- Misfire handling (server söndü, qaçırılan tapşırıq nə etsin)
- Thread-pool bulkhead ilə paralelizm (tək JVM daxilində)
- Partitioning — step-i çoxlu node arasında bölmək
- `@Async` ilə davamlı olmayan sadə async (yaddaşda)
- JobRepository ilə run tarixi audit

**Yalnız Laravel-də:**
- Horizon dashboard — zengin, auto-scaling, supervisor-lər
- `Bus::batch()` ilə asan job batching + progress callback
- `Bus::chain()` ilə ardıcıl job zənciri
- `ShouldBeUnique` interface + `uniqueId()` sadə API
- Middleware-əsaslı job davranışı (`RateLimited`, `WithoutOverlapping`, `ThrottlesExceptions`)
- `onOneServer()` daxili distributed lock
- Scheduler-də `pingBefore`/`thenPing` — uptime monitor inteqrasiyası
- Failed job-ları web UI-dan yenidən göndərmək
- Job tags ilə Horizon-da axtarış
- Octane ilə long-running worker (Swoole/RoadRunner fiber)
