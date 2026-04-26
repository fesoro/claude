# 69 — Spring Scheduling — Geniş İzah

> **Seviyye:** Middle ⭐⭐


## Mündəricat
1. [@Scheduled nədir?](#scheduled-nədir)
2. [Cron ifadələri](#cron-ifadələri)
3. [fixedRate vs fixedDelay](#fixedrate-vs-fixeddelay)
4. [Dynamic scheduling](#dynamic-scheduling)
5. [Distributed locking](#distributed-locking)
6. [İntervyu Sualları](#intervyu-sualları)

---

## @Scheduled nədir?

**@Scheduled** — metodları müəyyən vaxt intervallarında avtomatik işlədən Spring annotasiyası. Cron job-lar, periyodik görevlər üçün istifadə olunur.

```java
// Aktivləşdirmək
@SpringBootApplication
@EnableScheduling
public class App { }

// Sadə nümunə
@Component
public class ReportScheduler {

    private static final Logger log = LoggerFactory.getLogger(ReportScheduler.class);

    @Scheduled(cron = "0 0 8 * * MON-FRI") // Hər iş günü saat 08:00
    public void generateDailyReport() {
        log.info("Günlük hesabat hazırlanır...");
        reportService.generateDaily();
    }
}
```

---

## Cron ifadələri

```
Format: second minute hour day-of-month month day-of-week

* — hər dəyər
? — əhəmiyyətsiz (day-of-month/day-of-week üçün)
/ — interval (*/5 = hər 5 vahid)
, — siyahı (MON,WED,FRI)
- — aralıq (MON-FRI)
L — son (6L = ayın son Cümə)
# — n-ci (2#1 = ayın ilk Çərşənbə)
```

```java
@Component
public class ScheduledTasks {

    // Hər 30 saniyədə bir
    @Scheduled(cron = "*/30 * * * * *")
    public void every30Seconds() { }

    // Hər dəqiqə
    @Scheduled(cron = "0 * * * * *")
    public void everyMinute() { }

    // Hər saat 15-ci dəqiqəsi
    @Scheduled(cron = "0 15 * * * *")
    public void everyHourAt15() { }

    // Hər gün saat 00:00
    @Scheduled(cron = "0 0 0 * * *")
    public void midnight() { }

    // Hər gün saat 08:30
    @Scheduled(cron = "0 30 8 * * *")
    public void at0830() { }

    // Hər Bazar ertəsi saat 09:00
    @Scheduled(cron = "0 0 9 * * MON")
    public void mondayMorning() { }

    // Hər iş günü (Bazar ertəsi - Cümə) saat 18:00
    @Scheduled(cron = "0 0 18 * * MON-FRI")
    public void weekdayEvening() { }

    // Hər ayın 1-i, saat 00:01
    @Scheduled(cron = "0 1 0 1 * *")
    public void firstOfMonth() { }

    // 3 ayda bir — Yanvar, Aprel, İyul, Oktyabr — 1-i
    @Scheduled(cron = "0 0 0 1 1,4,7,10 *")
    public void quarterly() { }

    // Timezone ilə
    @Scheduled(cron = "0 0 9 * * MON-FRI",
               zone = "Asia/Baku")
    public void bakuTime() { }

    // application.yml-dən cron oxuma
    @Scheduled(cron = "${app.scheduler.report-cron:0 0 8 * * *}")
    public void configurableCron() { }
}
```

**application.yml:**
```yaml
app:
  scheduler:
    report-cron: "0 0 8 * * MON-FRI"
    cleanup-cron: "0 30 2 * * *"
```

---

## fixedRate vs fixedDelay

```java
@Component
public class PollingScheduler {

    // fixedRate — əvvəlki başlamadan N ms sonra başla (paralel risk var)
    // Hər 5 saniyədə bir BAŞLAYIR (əvvəlki bitməyibsə paralel işləyir)
    @Scheduled(fixedRate = 5000)
    public void fixedRateTask() {
        // 3 saniyə işləyir → 5 saniyəyə başlayır
        processMessages();
    }

    // fixedDelay — əvvəlki bitdikdən N ms sonra başla
    // Əvvəlki bitdikdən 5 saniyə SONRA başlayır
    @Scheduled(fixedDelay = 5000)
    public void fixedDelayTask() {
        // 3 saniyə işləyir → 3+5=8 saniyədə növbəti başlayır
        processQueue();
    }

    // İlk icranı gecikdirmək
    @Scheduled(fixedRate = 60000, initialDelay = 10000)
    public void withInitialDelay() {
        // App başlayandan 10 saniyə sonra ilk dəfə işləyir
        // Sonra hər 60 saniyədə bir
        warmUpCache();
    }

    // application.yml-dən oxumaq
    @Scheduled(fixedRateString = "${app.scheduler.rate:60000}",
               initialDelayString = "${app.scheduler.initial-delay:5000}")
    public void configurableRate() {
        checkHealth();
    }
}
```

**fixedRate vs fixedDelay fərqi:**
```
Task: ████ (4 saniyə işləyir)
fixedRate = 5000ms:
  t=0: ████
  t=5: ████
  t=10: ████
  (başlanğıca görə)

fixedDelay = 5000ms:
  t=0: ████
  t=9: ████  (4+5=9)
  t=18: ████
  (bitiş + delay)
```

---

## Dynamic scheduling

Runtime-da schedule dəyişdirmək:

```java
@Configuration
@EnableScheduling
public class DynamicSchedulingConfig implements SchedulingConfigurer {

    private final TaskRepository taskRepository;

    @Override
    public void configureTasks(ScheduledTaskRegistrar registrar) {
        // Trigger ilə dinamik scheduling
        registrar.addTriggerTask(
            () -> runDynamicTask(), // Runnable
            context -> {
                // DB-dən növbəti icra vaxtını al
                Optional<ScheduledTask> task =
                    taskRepository.findByName("dynamic-task");

                if (task.isEmpty()) return null; // Task yoxdursa dayandır

                String cronExpression = task.get().getCronExpression();
                CronTrigger trigger = new CronTrigger(cronExpression);
                return trigger.nextExecution(context);
            }
        );
    }

    private void runDynamicTask() {
        System.out.println("Dinamik task işlədi: " + LocalDateTime.now());
    }
}

// Runtime-da task əlavə/silmə
@Service
public class SchedulerManagerService {

    private final ScheduledTaskRegistrar registrar;
    private final Map<String, ScheduledTask> tasks = new ConcurrentHashMap<>();

    public void addTask(String taskName, String cronExpression, Runnable task) {
        ScheduledTask scheduledTask = registrar.scheduleCronTask(
            new CronTask(task, cronExpression));
        tasks.put(taskName, scheduledTask);
    }

    public void removeTask(String taskName) {
        ScheduledTask task = tasks.remove(taskName);
        if (task != null) {
            task.cancel();
        }
    }
}
```

---

## Distributed locking

Cluster-da yalnız bir instance-ın task-ı icra etməsi:

```java
// ShedLock ilə distributed locking
@Scheduled(cron = "0 0 8 * * *")
@SchedulerLock(name = "dailyReport",
               lockAtLeastFor = "PT5M",  // Minimum 5 dəqiqə lock
               lockAtMostFor = "PT30M")  // Maksimum 30 dəqiqə lock
public void generateDailyReport() {
    // Yalnız bir pod/instance icra edir
    reportService.generateDaily();
}

// pom.xml
// <dependency>
//     <groupId>net.javacrumbs.shedlock</groupId>
//     <artifactId>shedlock-spring</artifactId>
// </dependency>
// <dependency>
//     <groupId>net.javacrumbs.shedlock</groupId>
//     <artifactId>shedlock-provider-jdbc-template</artifactId>
// </dependency>

@Configuration
@EnableSchedulerLock(defaultLockAtMostFor = "PT10M")
public class ShedLockConfig {

    @Bean
    public LockProvider lockProvider(DataSource dataSource) {
        return new JdbcTemplateLockProvider(
            JdbcTemplateLockProvider.Configuration.builder()
                .withJdbcTemplate(new JdbcTemplate(dataSource))
                .usingDbTime()
                .build()
        );
    }
}
```

---

## İntervyu Sualları

### 1. @Scheduled metodunun şərtləri nədir?
**Cavab:** (1) Metod `void` qaytarmalıdır. (2) Metod heç bir parametr qəbul etməməlidir. (3) Sinif Spring bean olmalıdır (`@Component`, `@Service`). (4) `@EnableScheduling` konfiqurasiyaya əlavə edilməlidir.

### 2. fixedRate vs fixedDelay fərqi nədir?
**Cavab:** `fixedRate` — metodun başlama vaxtından N ms sonra növbəti icra başlayır (paralel risk var). `fixedDelay` — metodun bitməsindən N ms sonra növbəti icra başlayır (ardıcıl icraya zəmanət). Database polling, queue processing üçün `fixedDelay` daha uyğundur.

### 3. Cron ifadəsinin 6 sahəsi nədir?
**Cavab:** `second minute hour day-of-month month day-of-week`. Nümunə: `0 30 8 * * MON-FRI` = hər iş günü saat 08:30. Spring `0/5 * * * * *` (5 saniyədə bir) formatını dəstəkləyir. Standard Unix cron-dan fərqli olaraq saniyə sahəsi əlavədir.

### 4. Cluster mühitdə scheduler duplicate execution necə qarşısı alınır?
**Cavab:** ShedLock library-si istifadə edilir. `@SchedulerLock` annotasiyası distributed lock yaradır (DB-də yaxud Redis-də). Yalnız lock alan instance task-ı icra edir. `lockAtMostFor` — instance çöksə lock avtomatik açılır, başqa instance davam edə bilər.

### 5. Scheduling thread pool konfiqurasyonu necədir?
**Cavab:** Default olaraq Spring single thread executor istifadə edir — bütün `@Scheduled` metodlar ardıcıl icra olunur. Paralel icra üçün: `@EnableAsync` + `@Async` + `@Scheduled` kombinasiyası, yaxud `SchedulingConfigurer`-da `setScheduler(Executors.newScheduledThreadPool(N))`.

*Son yenilənmə: 2026-04-10*
