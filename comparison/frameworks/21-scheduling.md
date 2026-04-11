# Scheduling (Planlasdirma / Zamanlanmis tapsiriglar)

## Giris

Bir cox tetbiqde mueyyen tapshiriqlarin avtomatik, dovrevi islemesi lazim olur - melumat bazasinin temizlenmesi, hesabatlarin yaradilmasi, e-poct bildirislerin gonderilmesi ve s. Spring `@Scheduled` annotasiyasi ile Java sinfi daxilinde planlasdirma teklif edir, Laravel ise `schedule()` metodu ve artisan emrleri ile daha yukek seviyyeli planlasdirma sistemi qurur.

## Spring-de istifadesi

### @EnableScheduling ile aktivlesdirme

```java
@SpringBootApplication
@EnableScheduling
public class Application {
    public static void main(String[] args) {
        SpringApplication.run(Application.class, args);
    }
}
```

### @Scheduled annotasiyasi

```java
@Component
public class ScheduledTasks {

    private static final Logger log = LoggerFactory.getLogger(ScheduledTasks.class);

    private final ReportService reportService;
    private final UserRepository userRepository;

    public ScheduledTasks(ReportService reportService,
                          UserRepository userRepository) {
        this.reportService = reportService;
        this.userRepository = userRepository;
    }

    // Her 5 saniyede bir (evvelki bitmeden sonra 5 saniye gozle)
    @Scheduled(fixedDelay = 5000)
    public void cleanExpiredTokens() {
        log.info("Mudeti kecmis tokenler temizlenir...");
        userRepository.deleteExpiredTokens();
    }

    // Her 10 saniyede bir (evvelkinin bitib-bitmemesinden asili olmayaraq)
    @Scheduled(fixedRate = 10000)
    public void checkSystemHealth() {
        log.info("Sistem sagligi yoxlanilir...");
        // Monitoring metiqi
    }

    // Baslangicda 5 saniye gozle, sonra her 30 saniyede bir
    @Scheduled(initialDelay = 5000, fixedRate = 30000)
    public void syncExternalData() {
        log.info("Xarici melumatlar sinxronlasdirilir...");
    }

    // Cron ifadesi ile - her gun saat 02:00-da
    @Scheduled(cron = "0 0 2 * * *")
    public void generateDailyReport() {
        log.info("Gunluk hesabat hazirlayir...");
        reportService.generateDailyReport(LocalDate.now().minusDays(1));
    }

    // Her bazar ertesi saat 09:00-da
    @Scheduled(cron = "0 0 9 * * MON")
    public void sendWeeklyNewsletter() {
        log.info("Heftelik buleten gonderilir...");
    }

    // Her ayin 1-i saat 00:00-da
    @Scheduled(cron = "0 0 0 1 * *")
    public void monthlyCleanup() {
        log.info("Ayliq temizlik aparilir...");
    }
}
```

### Cron ifadeleri

Spring-de cron ifadesi 6 saheden ibaretdir:

```
┌───────── saniye (0-59)
│ ┌───────── deqiqe (0-59)
│ │ ┌───────── saat (0-23)
│ │ │ ┌───────── ayin gunu (1-31)
│ │ │ │ ┌───────── ay (1-12)
│ │ │ │ │ ┌───────── heftenin gunu (0-7, MON-SUN)
│ │ │ │ │ │
* * * * * *
```

```java
@Component
public class CronExamples {

    @Scheduled(cron = "0 */15 * * * *")   // Her 15 deqiqede
    public void every15Minutes() {}

    @Scheduled(cron = "0 0 */2 * * *")    // Her 2 saatda
    public void every2Hours() {}

    @Scheduled(cron = "0 30 8 * * MON-FRI") // Ish gunleri 08:30-da
    public void weekdayMorning() {}

    // Konfiqurasiyadan cron ifadesi oxumaq
    @Scheduled(cron = "${app.scheduling.report-cron}")
    public void configurableCron() {}
}
```

### FixedRate vs FixedDelay

```java
@Component
public class RateVsDelay {

    // fixedRate: Evvelki tapshiriq bitmese bele, novbeti baslayir
    // Tapshiriq 3 saniye surerse, her 5 saniyede bir baslayacaq
    @Scheduled(fixedRate = 5000)
    public void fixedRateTask() {
        // 3 saniye surer
    }

    // fixedDelay: Evvelki tapshiriq bitdikden sonra 5 saniye gozleyir
    // Tapshiriq 3 saniye surerse, novbeti 8-ci saniyede baslayacaq
    @Scheduled(fixedDelay = 5000)
    public void fixedDelayTask() {
        // 3 saniye surer
    }
}
```

### Task Executor konfiqurasiyasi

```java
@Configuration
@EnableScheduling
public class SchedulingConfig implements SchedulingConfigurer {

    @Override
    public void configureTasks(ScheduledTaskRegistrar taskRegistrar) {
        ThreadPoolTaskScheduler scheduler = new ThreadPoolTaskScheduler();
        scheduler.setPoolSize(5); // Eyni anda 5 tapshiriq isle
        scheduler.setThreadNamePrefix("scheduler-");
        scheduler.setErrorHandler(throwable ->
            log.error("Planli tapshiriqda xeta: {}", throwable.getMessage()));
        scheduler.initialize();

        taskRegistrar.setTaskScheduler(scheduler);
    }
}
```

### Sertli planlasdirma

```java
@Component
@ConditionalOnProperty(name = "app.scheduling.enabled", havingValue = "true")
public class ConditionalScheduledTasks {

    @Scheduled(fixedRate = 60000)
    public void conditionalTask() {
        // Yalniz app.scheduling.enabled=true olduqda isleyir
    }
}
```

## Laravel-de istifadesi

### Schedule metodu

```php
// app/Console/Kernel.php (Laravel 10 ve evvel)
// ve ya routes/console.php (Laravel 11+)

// Laravel 11+ - routes/console.php
use Illuminate\Support\Facades\Schedule;

Schedule::command('reports:daily')->dailyAt('02:00');
Schedule::command('tokens:cleanup')->everyFiveMinutes();
Schedule::command('newsletter:send')->weeklyOn(1, '9:00'); // Bazar ertesi 09:00
Schedule::command('backup:run')->monthlyOn(1, '00:00');
```

### Artisan emrleri yaratmaq

```php
// php artisan make:command DailyReportCommand

namespace App\Console\Commands;

use Illuminate\Console\Command;

class DailyReportCommand extends Command
{
    protected $signature = 'reports:daily {--date= : Hesabat tarixi}';
    protected $description = 'Gunluk hesabat yaradir';

    public function handle(ReportService $reportService): int
    {
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))
            : now()->subDay();

        $this->info("Hesabat hazirlayir: {$date->toDateString()}");

        $report = $reportService->generateDailyReport($date);

        $this->info("Hesabat hazirdir. Cemi: {$report->total_orders} sifaris");

        return Command::SUCCESS;
    }
}
```

```php
// Digrer emr numunesi
class CleanupTokensCommand extends Command
{
    protected $signature = 'tokens:cleanup';
    protected $description = 'Mudeti kecmis tokenleri temizleyir';

    public function handle(): int
    {
        $count = PersonalAccessToken::where('expires_at', '<', now())->delete();

        $this->info("{$count} token silindi.");

        return Command::SUCCESS;
    }
}
```

### Tezlik metodlari

```php
use Illuminate\Support\Facades\Schedule;

// Her deqiqe
Schedule::command('monitor:check')->everyMinute();

// Her 5/10/15/30 deqiqede
Schedule::command('cache:warm')->everyFiveMinutes();
Schedule::command('stats:update')->everyTenMinutes();
Schedule::command('queue:monitor')->everyFifteenMinutes();
Schedule::command('feeds:refresh')->everyThirtyMinutes();

// Saatlig
Schedule::command('logs:rotate')->hourly();
Schedule::command('reports:hourly')->hourlyAt(15); // Her saatin 15-ci deqiqesinde

// Gunluk
Schedule::command('reports:daily')->daily();
Schedule::command('reports:daily')->dailyAt('02:00');
Schedule::command('backup:clean')->twiceDaily(1, 13); // 01:00 ve 13:00

// Heftelik
Schedule::command('newsletter:send')->weekly();
Schedule::command('analytics:weekly')->weeklyOn(5, '18:00'); // Cume 18:00

// Ayliq
Schedule::command('invoices:generate')->monthly();
Schedule::command('invoices:generate')->monthlyOn(1, '00:00');

// Cron ifadesi ile
Schedule::command('custom:task')->cron('0 */6 * * *'); // Her 6 saatda
```

### Sertli planlasdirma

```php
// Yalniz production-da isle
Schedule::command('backup:run')
    ->daily()
    ->environments(['production']);

// Sertli isletme
Schedule::command('reports:daily')
    ->dailyAt('02:00')
    ->when(function () {
        return Cache::get('reporting_enabled', true);
    });

// Bitmemisden ikincisini baslatma (overlap qorunmasi)
Schedule::command('sync:products')
    ->everyFiveMinutes()
    ->withoutOverlapping();

// Bir serverde isle (birden cox server olduqda)
Schedule::command('reports:daily')
    ->dailyAt('02:00')
    ->onOneServer();

// Maintenance modunda bele isle
Schedule::command('monitor:health')
    ->everyMinute()
    ->evenInMaintenanceMode();
```

### Tapshiriq neticesini izlemek

```php
// Cixisi fayla yazmaq
Schedule::command('reports:daily')
    ->dailyAt('02:00')
    ->sendOutputTo('/var/log/daily-report.log');

// Cixisi e-pocta gonderme
Schedule::command('reports:daily')
    ->dailyAt('02:00')
    ->emailOutputTo('admin@example.com');

// Ugurlu/ugursuz halda webhook cagiris
Schedule::command('backup:run')
    ->daily()
    ->onSuccess(function () {
        // Bildiris gonder
    })
    ->onFailure(function () {
        // Xeberdarliq gonder
    });

// Before/After hook-lar
Schedule::command('sync:data')
    ->hourly()
    ->before(function () {
        Log::info('Sinxronlasma baslayir...');
    })
    ->after(function () {
        Log::info('Sinxronlasma bitdi.');
    });
```

### Closure ile planlasdirma

```php
// Artisan emri olmadan birbaşa closure
Schedule::call(function () {
    $count = DB::table('sessions')
        ->where('last_activity', '<', now()->subHours(2))
        ->delete();

    Log::info("{$count} kohne sessiya silindi");
})->hourly();

// Job sinfi planlasdirma
Schedule::job(new ProcessPendingOrders)->everyFiveMinutes();
Schedule::job(new ProcessPendingOrders, 'orders')->everyFiveMinutes(); // Xususi queue
```

### Cron qurulumu

Laravel schedule sisteminin islesmesi ucun serverde bir tek cron qeydi lazimdir:

```bash
# crontab -e
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

Bu tek setir her deqiqe Laravel-in schedule sistemini cagirir, ve Laravel ozue qalan isleri idarae edir.

## Esas ferqler

| Xususiyyet | Spring | Laravel |
|---|---|---|
| **Esas mexanizm** | `@Scheduled` annotasiyasi | `Schedule` facade / `schedule()` metodu |
| **Tapshiriq tanimlamasi** | Java metodu | Artisan emri, Closure, Job |
| **Tezlik ifadesi** | `fixedRate`, `fixedDelay`, `cron` | `everyFiveMinutes()`, `daily()`, `cron()` |
| **Cron formati** | 6 sahe (saniye daxil) | 5 sahe (standart Unix cron) |
| **Overlap qorunmasi** | Manual (lock ile) | `withoutOverlapping()` |
| **Bir server** | Manual (distributed lock) | `onOneServer()` |
| **Cixis izleme** | Manual logging | `sendOutputTo()`, `emailOutputTo()` |
| **Sertli isletme** | `@ConditionalOnProperty` | `when()`, `environments()` |
| **Thread pool** | `SchedulingConfigurer` ile | Lazim deyil (ayri proses) |
| **Server qurulumu** | Her hangi bir seyae ehtiyac yoxdur (tetbiq daxilinde isleyir) | Bir tek crontab girisi lazimdir |

## Niye bele ferqler var?

**Spring-in yanasmasi:** Spring tetbiqi daimi isleyen bir JVM prosesidir. `@Scheduled` metodlari tetbiq daxilinde ayri thread-lerde isleyir. Bu o demekdir ki, xarici cron sisteme ehtiyac yoxdur - her sey tetbiqin ozunde bas verir. `fixedRate` ve `fixedDelay` ferqi Java-nin thread mexanizmi ile elaqedardir ve coxlu esneklik verir.

**Laravel-in yanasmasi:** PHP her sorgu ucun ayri proses yaradan bir dildir - daimi isleyen proses yoxdur. Buna gore de planlasdirma ucun xarici tetikleyici lazimdir - bu Unix cron-dur. Laravel bu meseleya dahiyane yanasir: serverde yalniz bir cron girisi lazimdir (`* * * * *`), qalanini Laravel ozue idarae edir. `everyFiveMinutes()`, `daily()` kimi oxunaqli metodlar cron ifadelerinin abstrasksiyasidir.

**Developer Experience:** Laravel-in tezlik metodlari (`everyFiveMinutes()`, `weeklyOn()`) cron ifadelerini ezberlemek ehtiyacini aradan qaldirir. Spring-de ise cron ifadelerini bilmek lazimdir. Diger terefden, Spring-de `fixedRate` ve `fixedDelay` ferqi millisaniyye deqiqliyi ile nezaret imkani verir ki, bu Laravel-de yoxdur.

## Hansi framework-de var, hansinda yoxdur?

- **`withoutOverlapping()`** - Laravel-de built-in. Spring-de manual lock mexanizmi yazmaq lazimdir.
- **`onOneServer()`** - Laravel-de distributed lock ile bir serverde isletme built-in-dir. Spring-de ShedLock kimi xarici kutubxane lazimdir.
- **`sendOutputTo()` / `emailOutputTo()`** - Yalniz Laravel-de. Spring-de manual logging lazimdir.
- **`fixedRate` / `fixedDelay`** - Yalniz Spring-de. Laravel-de bu cur millisaniye seviyyesinde nezaret yoxdur.
- **Thread pool konfiqurasiyasi** - Yalniz Spring-de. Laravel-de her schedule run ayri proses oldugundan thread pool anlayisi yoxdur.
- **Saniyeli cron** - Spring 6 saheli cron destekleyir (saniye daxil). Laravel standart 5 saheli Unix cron istifade edir.
- **`onSuccess()` / `onFailure()` hook-lar** - Laravel-de built-in. Spring-de manual error handling lazimdir.
- **Artisan emrleri** - Laravel planlasdirilan tapshiriqlari artisan emrleri olaraq yaradildigi ucun emr setirinden de isletmek asandir. Spring-de bu cur ayri CLI interfeys yoxdur.
