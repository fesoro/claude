# Spring Batch vs Laravel Batch Processing — Dərin Müqayisə

## Giriş

Batch processing — "milyonlarla qeyd oxu, emal et, yaz" tipli uzun işlərdir. Məsələn: hər gecə bank 50 milyon əməliyyatı agregasiya edir, insurance şirkəti 10 milyon müştərinin faylını import edir, e-commerce platforması 1 milyon sifarişi hesabata çevirir. Bu işlər online request cycle-a sığmır — ayrıca "job" kimi icra olunur.

**Spring Batch** — JVM ekosistemində enterprise batch framework standartıdır. Chunk-oriented processing, restart, skip, retry, partitioning — hər biri ayrıca konsept kimi düşünülüb. Batch job yarıda dayansa, dəqiq dayandığı yerdən davam edir (restart semantics). Finans, insurance, telekomun əsas aləti.

**Laravel**-də isə "Spring Batch kimi" framework yoxdur. Amma Laravel bir neçə yanaşma təklif edir: `Bus::batch([...])` (paralel job-lar + progress), `LazyCollection::chunkById()` (yaddaş-səmərəli oxuma), `artisan` command-lar + scheduler (ETL üçün), `Bus::chain()` (ardıcıl job-lar). Laravel fəlsəfəsi "idempotent job design" — hər job təkrar işləyə bilər, buna görə "restart from where it stopped" əvəzinə "re-run whole job, skip already-processed" yanaşması var.

Bu sənəddə hər iki framework-də 10 milyon sətirli CSV-ni DB-yə import edən ETL quracayıq.

---

## Spring-də istifadəsi

### 1) Dependency və konfigurasiya

```xml
<!-- pom.xml -->
<dependencies>
    <dependency>
        <groupId>org.springframework.boot</groupId>
        <artifactId>spring-boot-starter-batch</artifactId>
    </dependency>
    <dependency>
        <groupId>org.springframework.boot</groupId>
        <artifactId>spring-boot-starter-data-jpa</artifactId>
    </dependency>
    <dependency>
        <groupId>org.postgresql</groupId>
        <artifactId>postgresql</artifactId>
        <scope>runtime</scope>
    </dependency>
    <dependency>
        <groupId>org.springframework.boot</groupId>
        <artifactId>spring-boot-starter-quartz</artifactId>
    </dependency>
</dependencies>
```

```yaml
# application.yml
spring:
  datasource:
    url: jdbc:postgresql://localhost:5432/batch_db
    username: batch
    password: batch
  batch:
    jdbc:
      initialize-schema: always    # JobRepository cədvəlləri avtomatik
    job:
      enabled: false               # Auto-run kapalı, biz trigger edirik
  jpa:
    hibernate:
      ddl-auto: validate

logging:
  level:
    org.springframework.batch: INFO
```

### 2) Domain və CSV model

```java
// Domain entity
@Entity
@Table(name = "customers")
public class Customer {
    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    @Column(name = "first_name", nullable = false)
    private String firstName;

    @Column(name = "last_name", nullable = false)
    private String lastName;

    @Column(nullable = false, unique = true)
    private String email;

    @Column(name = "created_at")
    private LocalDateTime createdAt;

    // getters, setters, constructors
}

// CSV sətir üçün record
public record CustomerCsv(
    String firstName,
    String lastName,
    String email,
    String birthDate
) {}
```

### 3) Job, Step, Chunk konfiqurasiyası

```java
@Configuration
public class ImportCustomersJobConfig {

    @Bean
    public Job importCustomersJob(JobRepository jobRepository, Step importStep,
                                  JobCompletionListener listener) {
        return new JobBuilder("importCustomersJob", jobRepository)
            .incrementer(new RunIdIncrementer())   // Hər run üçün yeni id
            .listener(listener)
            .start(importStep)
            .build();
    }

    @Bean
    public Step importStep(JobRepository jobRepository,
                           PlatformTransactionManager txManager,
                           FlatFileItemReader<CustomerCsv> reader,
                           CustomerItemProcessor processor,
                           JdbcBatchItemWriter<Customer> writer,
                           SkipListener<CustomerCsv, Customer> skipListener) {
        return new StepBuilder("importStep", jobRepository)
            .<CustomerCsv, Customer>chunk(1000, txManager)   // 1000-lik transaction
            .reader(reader)
            .processor(processor)
            .writer(writer)
            .faultTolerant()
            .retry(DeadlockLoserDataAccessException.class)
            .retry(TransientDataAccessException.class)
            .retryLimit(3)
            .skip(ValidationException.class)
            .skip(DuplicateKeyException.class)
            .skipLimit(1000)                       // 1000 xətaya qədər icazə
            .listener(skipListener)
            .taskExecutor(taskExecutor())          // paralel chunk icra
            .throttleLimit(4)                      // 4 thread paralel
            .build();
    }

    @Bean
    public TaskExecutor taskExecutor() {
        ThreadPoolTaskExecutor executor = new ThreadPoolTaskExecutor();
        executor.setCorePoolSize(4);
        executor.setMaxPoolSize(8);
        executor.setQueueCapacity(100);
        executor.setThreadNamePrefix("batch-");
        executor.initialize();
        return executor;
    }
}
```

### 4) ItemReader — CSV faylı oxumaq

```java
@Configuration
public class ReaderConfig {

    @Bean
    @StepScope   // Job parametrini götürmək üçün
    public FlatFileItemReader<CustomerCsv> reader(
            @Value("#{jobParameters['inputFile']}") String inputFile) {
        return new FlatFileItemReaderBuilder<CustomerCsv>()
            .name("customerReader")
            .resource(new FileSystemResource(inputFile))
            .linesToSkip(1)                    // header skip
            .delimited()
            .delimiter(",")
            .quoteCharacter('"')
            .names("firstName", "lastName", "email", "birthDate")
            .targetType(CustomerCsv.class)
            .strict(true)
            .build();
    }
}
```

Digər built-in reader-lər:

```java
// JDBC cursor reader — DB-dən oxu
@Bean
public JdbcCursorItemReader<Order> ordersReader(DataSource ds) {
    return new JdbcCursorItemReaderBuilder<Order>()
        .name("ordersReader")
        .dataSource(ds)
        .sql("SELECT id, amount, status FROM orders WHERE status = 'PENDING'")
        .rowMapper((rs, rowNum) -> new Order(rs.getLong("id"),
                                             rs.getBigDecimal("amount"),
                                             rs.getString("status")))
        .fetchSize(1000)
        .build();
}

// JSON reader
@Bean
public JsonItemReader<Product> productReader() {
    return new JsonItemReaderBuilder<Product>()
        .name("productReader")
        .resource(new ClassPathResource("products.json"))
        .jsonObjectReader(new JacksonJsonObjectReader<>(Product.class))
        .build();
}

// Mongo reader
@Bean
public MongoItemReader<Event> mongoReader(MongoTemplate mongo) {
    return new MongoItemReaderBuilder<Event>()
        .name("eventsReader")
        .template(mongo)
        .targetType(Event.class)
        .query("{status: 'new'}")
        .sorts(Map.of("createdAt", Sort.Direction.ASC))
        .pageSize(500)
        .build();
}
```

### 5) ItemProcessor — transformation və validation

```java
@Component
@StepScope
public class CustomerItemProcessor implements ItemProcessor<CustomerCsv, Customer> {

    private static final Logger log = LoggerFactory.getLogger(CustomerItemProcessor.class);
    private final Validator validator;

    public CustomerItemProcessor(Validator validator) {
        this.validator = validator;
    }

    @Override
    public Customer process(CustomerCsv csv) throws Exception {
        // 1. Validation
        if (! isValidEmail(csv.email())) {
            throw new ValidationException("Yanlış email: " + csv.email());
        }

        // 2. Transform
        Customer customer = new Customer();
        customer.setFirstName(capitalize(csv.firstName()));
        customer.setLastName(capitalize(csv.lastName()));
        customer.setEmail(csv.email().toLowerCase().trim());
        customer.setCreatedAt(LocalDateTime.now());

        // 3. Filter — null qaytarırıqsa, bu item skip olunur
        if (customer.getEmail().endsWith("@test.com")) {
            log.debug("Test email skip: {}", customer.getEmail());
            return null;
        }

        return customer;
    }

    private boolean isValidEmail(String email) {
        return email != null && email.matches("^[\\w.+-]+@[\\w.-]+\\.\\w{2,}$");
    }

    private String capitalize(String s) {
        if (s == null || s.isBlank()) return s;
        return Character.toUpperCase(s.charAt(0)) + s.substring(1).toLowerCase();
    }
}
```

Composite processor (bir neçə processor ardıcıl):

```java
@Bean
public CompositeItemProcessor<CustomerCsv, Customer> compositeProcessor(
        CustomerValidationProcessor validator,
        CustomerEnrichmentProcessor enricher,
        CustomerTransformProcessor transformer) {
    CompositeItemProcessor<CustomerCsv, Customer> composite = new CompositeItemProcessor<>();
    composite.setDelegates(List.of(validator, enricher, transformer));
    return composite;
}
```

### 6) ItemWriter — DB-yə yazmaq

```java
@Configuration
public class WriterConfig {

    @Bean
    public JdbcBatchItemWriter<Customer> writer(DataSource dataSource) {
        return new JdbcBatchItemWriterBuilder<Customer>()
            .dataSource(dataSource)
            .sql("""
                INSERT INTO customers(first_name, last_name, email, created_at)
                VALUES (?, ?, ?, ?)
                ON CONFLICT (email) DO UPDATE SET
                    first_name = EXCLUDED.first_name,
                    last_name = EXCLUDED.last_name
                """)
            .itemPreparedStatementSetter((item, ps) -> {
                ps.setString(1, item.getFirstName());
                ps.setString(2, item.getLastName());
                ps.setString(3, item.getEmail());
                ps.setTimestamp(4, Timestamp.valueOf(item.getCreatedAt()));
            })
            .assertUpdates(false)
            .build();
    }
}
```

Composite writer — bir item-i həm DB, həm Kafka-ya:

```java
@Bean
public CompositeItemWriter<Customer> compositeWriter(
        JdbcBatchItemWriter<Customer> dbWriter,
        KafkaItemWriter<String, Customer> kafkaWriter) {
    CompositeItemWriter<Customer> composite = new CompositeItemWriter<>();
    composite.setDelegates(List.of(dbWriter, kafkaWriter));
    return composite;
}
```

### 7) Skip/Retry listener

```java
@Component
public class CustomerSkipListener implements SkipListener<CustomerCsv, Customer> {

    private static final Logger log = LoggerFactory.getLogger(CustomerSkipListener.class);

    @Override
    public void onSkipInRead(Throwable t) {
        log.warn("Oxuma xətası skip", t);
    }

    @Override
    public void onSkipInProcess(CustomerCsv item, Throwable t) {
        log.warn("Process skip email={}, xəta={}", item.email(), t.getMessage());
        // DLQ-a yazmaq olar
    }

    @Override
    public void onSkipInWrite(Customer item, Throwable t) {
        log.warn("Write skip email={}, xəta={}", item.getEmail(), t.getMessage());
    }
}
```

### 8) Job completion listener

```java
@Component
public class JobCompletionListener implements JobExecutionListener {

    private static final Logger log = LoggerFactory.getLogger(JobCompletionListener.class);

    @Override
    public void beforeJob(JobExecution jobExecution) {
        log.info("Job başladı: {} parametr={}",
            jobExecution.getJobInstance().getJobName(),
            jobExecution.getJobParameters());
    }

    @Override
    public void afterJob(JobExecution jobExecution) {
        var stepExecution = jobExecution.getStepExecutions().iterator().next();
        log.info("""
            Job bitdi: status={}
            Read: {}, Write: {}, Skip: {}
            Müddət: {} ms
            """,
            jobExecution.getStatus(),
            stepExecution.getReadCount(),
            stepExecution.getWriteCount(),
            stepExecution.getSkipCount(),
            Duration.between(jobExecution.getStartTime(), jobExecution.getEndTime()).toMillis()
        );

        if (jobExecution.getStatus() == BatchStatus.FAILED) {
            // Alert göndər
            alertService.notifyJobFailure(jobExecution);
        }
    }
}
```

### 9) JobLauncher — proqramatik trigger

```java
@RestController
@RequestMapping("/batch")
public class BatchController {

    private final JobLauncher jobLauncher;
    private final Job importCustomersJob;

    public BatchController(JobLauncher jobLauncher, Job importCustomersJob) {
        this.jobLauncher = jobLauncher;
        this.importCustomersJob = importCustomersJob;
    }

    @PostMapping("/import")
    public ResponseEntity<Map<String, Object>> trigger(@RequestParam String file) throws Exception {
        JobParameters params = new JobParametersBuilder()
            .addString("inputFile", file)
            .addLong("startAt", System.currentTimeMillis())   // unique
            .toJobParameters();

        JobExecution execution = jobLauncher.run(importCustomersJob, params);

        return ResponseEntity.ok(Map.of(
            "jobId", execution.getId(),
            "status", execution.getStatus().name()
        ));
    }
}
```

### 10) Partitioning — paralel chunk processing

Bir step-i bir neçə "slave" step arasında bölmək — məsələn CSV-ni 10 hissəyə böl, hər biri ayrı thread-də işləsin:

```java
@Bean
public Step masterStep(JobRepository repo, PartitionHandler partitionHandler,
                       Step slaveStep, Partitioner partitioner) {
    return new StepBuilder("masterStep", repo)
        .partitioner(slaveStep.getName(), partitioner)
        .partitionHandler(partitionHandler)
        .build();
}

@Bean
public Partitioner partitioner() {
    return gridSize -> {
        Map<String, ExecutionContext> partitions = new HashMap<>();
        // Hər partition üçün file range
        long totalRows = countRows();
        long rowsPerPartition = totalRows / gridSize;
        for (int i = 0; i < gridSize; i++) {
            ExecutionContext ctx = new ExecutionContext();
            ctx.putLong("startRow", i * rowsPerPartition);
            ctx.putLong("endRow", (i + 1) * rowsPerPartition);
            partitions.put("partition-" + i, ctx);
        }
        return partitions;
    };
}

@Bean
public TaskExecutorPartitionHandler partitionHandler(Step slaveStep, TaskExecutor executor) {
    TaskExecutorPartitionHandler handler = new TaskExecutorPartitionHandler();
    handler.setStep(slaveStep);
    handler.setTaskExecutor(executor);
    handler.setGridSize(10);
    return handler;
}
```

### 11) Restart semantikası

Job yarıda dayansa (OOM, crash), yenidən eyni parametrlərlə işə salınanda **dəqiq dayandığı yerdən** davam edir. Bunu JobRepository idarə edir (BATCH_JOB_EXECUTION_CONTEXT, BATCH_STEP_EXECUTION cədvəllərində offset saxlanır).

```java
// Eyni parametrlə yenidən çağır — BATCH_JOB_EXECUTION-dan STARTED tapır,
// step-in getReadCount() dəyərindən davam edir.
jobLauncher.run(importCustomersJob, sameParams);
```

Restart-ın işləməsi üçün:
- Reader `ItemStream` interface-ini implement etməlidir (built-in reader-lər edirlər)
- Job parametrləri eyni olmalıdır
- Job status `STARTED`, `FAILED` və ya `STOPPED` olmalıdır (COMPLETED olmaz)

### 12) Scheduling

```java
@Component
public class BatchScheduler {

    private final JobLauncher jobLauncher;
    private final Job importCustomersJob;

    @Scheduled(cron = "0 0 2 * * *")   // hər gecə 02:00
    public void nightlyImport() throws Exception {
        JobParameters params = new JobParametersBuilder()
            .addString("inputFile", "/data/customers-" + LocalDate.now() + ".csv")
            .addLong("timestamp", System.currentTimeMillis())
            .toJobParameters();

        jobLauncher.run(importCustomersJob, params);
    }
}
```

Və ya Quartz ilə DB-persistent schedule. Və ya Kubernetes CronJob ilə:

```yaml
apiVersion: batch/v1
kind: CronJob
metadata:
  name: nightly-import
spec:
  schedule: "0 2 * * *"
  jobTemplate:
    spec:
      template:
        spec:
          containers:
            - name: batch
              image: myapp:latest
              args: ["--spring.batch.job.name=importCustomersJob",
                     "inputFile=/data/customers.csv"]
          restartPolicy: Never
```

### 13) Monitoring

```yaml
management:
  endpoints:
    web:
      exposure:
        include: health,metrics,batch
  metrics:
    tags:
      application: batch-app
```

`JobExplorer` ilə proqramatik:

```java
@Autowired
private JobExplorer jobExplorer;

public void showRecent() {
    List<JobInstance> instances = jobExplorer.getJobInstances("importCustomersJob", 0, 10);
    for (JobInstance inst : instances) {
        List<JobExecution> execs = jobExplorer.getJobExecutions(inst);
        execs.forEach(e -> log.info("Run {} status={}", e.getId(), e.getStatus()));
    }
}
```

---

## Laravel-də istifadəsi

### 1) composer və konfigurasiya

```json
// composer.json
{
    "require": {
        "php": "^8.3",
        "laravel/framework": "^12.0",
        "laravel/horizon": "^5.24",
        "league/csv": "^9.16"
    }
}
```

```php
// config/queue.php
'connections' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => env('REDIS_QUEUE', 'default'),
        'retry_after' => 600,
        'block_for' => 5,
    ],
],
```

### 2) Artisan command — main ETL entry

```php
// app/Console/Commands/ImportCustomers.php
namespace App\Console\Commands;

use App\Jobs\ImportCustomerChunk;
use Illuminate\Bus\Batch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use League\Csv\Reader;

class ImportCustomers extends Command
{
    protected $signature = 'customers:import {file} {--chunk=1000}';
    protected $description = '10 milyon CSV sətir import et';

    public function handle(): int
    {
        $file = $this->argument('file');
        $chunkSize = (int) $this->option('chunk');

        if (! file_exists($file)) {
            $this->error("Fayl tapılmadı: {$file}");
            return self::FAILURE;
        }

        $this->info("Import başladı: {$file}");

        $csv = Reader::createFromPath($file, 'r');
        $csv->setHeaderOffset(0);

        $jobs = [];
        $chunk = [];
        $count = 0;

        foreach ($csv->getRecords() as $row) {
            $chunk[] = $row;
            $count++;

            if (count($chunk) >= $chunkSize) {
                $jobs[] = new ImportCustomerChunk($chunk);
                $chunk = [];
            }

            // Hər 100k job yığılanda batch dispatch et
            if (count($jobs) >= 100) {
                $this->dispatchBatch($jobs, $file);
                $jobs = [];
            }
        }

        if (! empty($chunk)) {
            $jobs[] = new ImportCustomerChunk($chunk);
        }

        if (! empty($jobs)) {
            $this->dispatchBatch($jobs, $file);
        }

        $this->info("Ümumi sətir: {$count}");
        return self::SUCCESS;
    }

    private function dispatchBatch(array $jobs, string $file): void
    {
        Bus::batch($jobs)
            ->name("import:{$file}")
            ->allowFailures()
            ->onQueue('imports')
            ->then(function (Batch $batch) {
                logger()->info("Batch bitdi", [
                    'id' => $batch->id,
                    'total' => $batch->totalJobs,
                    'failed' => $batch->failedJobs,
                ]);
            })
            ->catch(function (Batch $batch, \Throwable $e) {
                logger()->error("Batch xətası", ['id' => $batch->id, 'error' => $e->getMessage()]);
            })
            ->finally(function (Batch $batch) {
                // Cleanup
            })
            ->dispatch();
    }
}
```

### 3) Chunk job — "ItemProcessor + Writer" kimi

```php
// app/Jobs/ImportCustomerChunk.php
namespace App\Jobs;

use App\Models\Customer;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ImportCustomerChunk implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [10, 30, 60];
    public int $timeout = 300;

    public function __construct(public array $rows) {}

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $valid = [];
        $skipped = 0;

        foreach ($this->rows as $row) {
            try {
                $valid[] = $this->process($row);
            } catch (\Throwable $e) {
                $skipped++;
                Log::warning('Row skip', ['email' => $row['email'] ?? null, 'error' => $e->getMessage()]);
            }
        }

        // Transaction + upsert = "writer"
        DB::transaction(function () use ($valid) {
            Customer::upsert(
                array_filter($valid),
                ['email'],                                           // unique key
                ['first_name', 'last_name', 'updated_at']            // yenilənən sahələr
            );
        });

        if ($skipped > 0) {
            Log::info("Chunk: skipped={$skipped}, written=" . count($valid));
        }
    }

    private function process(array $row): ?array
    {
        // Validation
        if (! filter_var($row['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("Yanlış email");
        }

        // Filter
        if (str_ends_with($row['email'], '@test.com')) {
            return null;
        }

        // Transform
        return [
            'first_name' => ucfirst(strtolower(trim($row['firstName'] ?? ''))),
            'last_name' => ucfirst(strtolower(trim($row['lastName'] ?? ''))),
            'email' => strtolower(trim($row['email'])),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    public function failed(\Throwable $e): void
    {
        Log::error('Chunk job failed', [
            'rows' => count($this->rows),
            'error' => $e->getMessage(),
        ]);
    }

    public function tags(): array
    {
        return ['import', 'customers'];
    }
}
```

### 4) LazyCollection — yaddaş-səmərəli oxuma

10 milyon sətirli CSV-ni bütöv yaddaşa yükləmək olmaz. `LazyCollection` hər sətri bir-bir verir:

```php
use Illuminate\Support\LazyCollection;

LazyCollection::make(function () use ($file) {
    $handle = fopen($file, 'r');
    $header = fgetcsv($handle);
    while (($row = fgetcsv($handle)) !== false) {
        yield array_combine($header, $row);
    }
    fclose($handle);
})
->chunk(1000)
->each(function ($chunk) {
    ImportCustomerChunk::dispatch($chunk->all())->onQueue('imports');
});
```

### 5) DB-dən oxumaq — `chunkById`

Yaddaş problemi olmadan milyonlarla qeyd oxumaq:

```php
use App\Models\Order;

Order::where('status', 'pending')
    ->chunkById(1000, function ($orders) {
        foreach ($orders as $order) {
            ProcessOrder::dispatch($order);
        }
    });

// Və ya cursor() — bir-bir gətirir
foreach (Order::where('status', 'pending')->cursor() as $order) {
    ProcessOrder::dispatch($order);
}

// Və ya lazyById
Order::where('status', 'pending')
    ->lazyById(1000)
    ->each(fn ($o) => ProcessOrder::dispatch($o));
```

### 6) Bus::batch — parallelism + progress

```php
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;

$batch = Bus::batch([
    new ImportCustomerChunk($chunk1),
    new ImportCustomerChunk($chunk2),
    new ImportCustomerChunk($chunk3),
])
->name('Import Customers')
->allowFailures()
->onQueue('imports')
->progress(function (Batch $batch) {
    broadcast(new BatchProgress($batch->id, $batch->progress()));
})
->then(fn (Batch $b) => event(new BatchCompleted($b)))
->catch(fn (Batch $b, \Throwable $e) => Log::error('Batch failed', ['id' => $b->id, 'e' => $e]))
->finally(fn (Batch $b) => Cache::forget("batch:{$b->id}:progress"))
->dispatch();

// Batch status API
Route::get('/batches/{id}', function (string $id) {
    $batch = Bus::findBatch($id);
    return response()->json([
        'id' => $batch->id,
        'name' => $batch->name,
        'totalJobs' => $batch->totalJobs,
        'pendingJobs' => $batch->pendingJobs,
        'failedJobs' => $batch->failedJobs,
        'processedJobs' => $batch->processedJobs(),
        'progress' => $batch->progress(),
        'finished' => $batch->finished(),
    ]);
});
```

### 7) Job chain — ardıcıl step-lər

Spring Batch-də bir neçə step-i `next()` ilə zəncir kimi qururuq; Laravel-də `Bus::chain()`:

```php
Bus::chain([
    new ValidateCsvFile($file),
    new ImportCustomersJob($file),
    new SendImportReport($file),
    new CleanupTempFiles($file),
])
->onQueue('imports')
->catch(function (\Throwable $e) use ($file) {
    Log::error("Chain xətası: {$file}", ['e' => $e]);
})
->dispatch();
```

### 8) Scheduling

```php
// routes/console.php (Laravel 11+)
use Illuminate\Support\Facades\Schedule;

Schedule::command('customers:import /data/customers-' . date('Y-m-d') . '.csv')
    ->cron('0 2 * * *')
    ->timezone('Asia/Baku')
    ->name('nightly-import')
    ->onOneServer()
    ->withoutOverlapping(60)
    ->runInBackground()
    ->environments(['production']);
```

### 9) Restart — idempotent design

Laravel-də "exact restart from offset" yoxdur. Əvəzinə idempotent job:

```php
// Upsert — eyni email varsa update, yoxdursa insert
Customer::upsert($valid, ['email'], ['first_name', 'last_name']);

// Və ya checkpoint table
DB::table('import_checkpoints')->updateOrInsert(
    ['file' => $file],
    ['last_row' => $currentRow, 'updated_at' => now()]
);

// Növbəti run-da oxu
$checkpoint = DB::table('import_checkpoints')->where('file', $file)->value('last_row') ?? 0;
// $checkpoint-dan başla
```

### 10) Horizon konfiqurasiyası

```php
// config/horizon.php
'environments' => [
    'production' => [
        'import-supervisor' => [
            'maxProcesses' => 20,
            'memory' => 512,
            'tries' => 3,
            'timeout' => 600,
            'balance' => 'auto',
            'minProcesses' => 2,
            'queue' => ['imports'],
        ],
    ],
],
```

### 11) Monitoring — batch status

```php
Route::get('/admin/batches', function () {
    $batches = DB::table('job_batches')
        ->orderByDesc('created_at')
        ->limit(20)
        ->get();

    return view('admin.batches', ['batches' => $batches]);
});
```

---

## Əsas fərqlər

| Xüsusiyyət | Spring Batch | Laravel |
|---|---|---|
| Framework | Dedicated `spring-batch` | Yox — Bus::batch + artisan + LazyCollection |
| Read-Process-Write pattern | Built-in (ItemReader/Processor/Writer) | Manual (custom Job class) |
| Chunk transaction | Avtomatik (chunk-oriented) | Manual (`DB::transaction()`) |
| Restart dəqiq offset-dən | Hə (JobRepository) | Yox — idempotent + checkpoint |
| Skip/Retry policy | Declarative (`faultTolerant().skip(...)`) | Manual try/catch |
| Partitioning | Native (`.partitioner(...)`) | `Bus::batch()` ilə paralel |
| Reader variantları | FlatFile, Jdbc, JSON, XML, Mongo, Kafka | LazyCollection, Eloquent cursor, manual |
| Metadata persistence | JobRepository (BATCH_* cədvəllər) | `job_batches`, `jobs`, `failed_jobs` |
| Progress | StepExecution counters | `$batch->progress()` |
| Parametr passing | JobParameters + `@StepScope` | Constructor arguments |
| Scheduling | `@Scheduled`, Quartz, K8s CronJob | `Schedule::command()` + cron |
| Dashboard | Spring Batch Admin (deprecated), Actuator | Horizon |
| Fault tolerance | Declarative retry/skip limits | Job `$tries`, `$backoff` |

---

## Niyə belə fərqlər var?

**JVM və stateful JobRepository.** Spring uzun-ömürlü JVM prosesində işləyir. JobRepository (DB) hər chunk-dan sonra offset saxlayır — restart etsə, eyni transaction boundary-dən davam edir. Bu "exactly-where-it-stopped" semantikası yalnız persistent metadata ilə mümkündür.

**PHP-nin request-per-process modeli.** Laravel queue worker-i hər job üçün (demək olar ki) yeni state alır. "Yarıda qalan job restart-da davam etsin" üçün framework-un yaddaş saxlaması çətindir. Əvəzinə Laravel "idempotent job" fəlsəfəsini seçib — job təkrar işləsə də nəticə eyni olsun (`upsert`, external checkpoint table).

**Enterprise Java heritage.** Spring Batch bank, insurance, telekomun "night batch window" mədəniyyətindən gəlir — 02:00-06:00 arası milyonlarla record emal olunur, restart zamanı vacibdir. Laravel veb-first mühitdən gəlir — "job queue" modelinə görə iş ayrı-ayrı kiçik parçalara bölünür.

**Declarative vs imperative.** Spring Batch declarative: "chunk 1000, skip 100 error, retry 3 times". Laravel imperative: try/catch yazırsan. Spring-də daha az kod, amma daha çox konsept öyrənmək lazımdır. Laravel-də konsept az, amma boilerplate çox.

**Dashboard fəlsəfəsi.** Horizon Laravel üçün zəngin web UI verir — throughput, failed job-ları retry, auto-scaling. Spring Batch Admin vaxtilə var idi, indi deprecated. Enterprise Java daha çox Grafana/Prometheus-a üz tutur.

---

## Hansı framework-də var, hansında yoxdur?

**Yalnız Spring Batch-də:**
- Chunk-oriented reader-process-writer pattern
- `faultTolerant().skip(...).retry(...)` declarative fault policy
- JobRepository-də exact offset restart
- Partitioning API (master-slave step)
- Built-in reader/writer (JSON, XML, FlatFile, JDBC cursor, Mongo, Kafka)
- `@StepScope` ilə job parametrlərindən bean inject
- `CompositeItemProcessor`, `CompositeItemWriter`
- SkipListener, RetryListener, ChunkListener
- Remote partitioning (JMS/Kafka ilə slave-lər)
- ExitStatus, BatchStatus ilə dəqiq status model

**Yalnız Laravel-də:**
- `Bus::batch()` ilə progress + then/catch/finally callback
- `Bus::chain()` sadə API
- `Horizon` dashboard (auto-scaling, retry UI)
- `LazyCollection` ilə memory-safe streaming
- Eloquent `chunkById()`, `lazyById()`, `cursor()`
- Job `tags()` ilə Horizon axtarışı
- Artisan command + scheduler çox sadə inteqrasiya
- `ShouldBeUnique` interface
- Job middleware (`RateLimited`, `WithoutOverlapping`)

---

## Best Practices

**Spring Batch üçün:**
- Chunk size: 100-1000 arası başla; profil et, yüksəlt
- Hər chunk ayrı transaction — böyük chunk DB lock-a gətirir
- `@StepScope` lazy bean-lər üçün — job parametri inject olur
- Reader-də `fetchSize` böyüklüyü ilə chunk uyğunlaşsın
- Partitioning-dən əvvəl sadə step-i profilə at
- `RunIdIncrementer` unique parametrlər üçün lazımdır
- Failed job-ları `JobExplorer` ilə izlə

**Laravel üçün:**
- `chunkById` istifadə et (`chunk` yox — offset problem yaradır)
- Job-lar idempotent olsun (upsert, unique constraint)
- Böyük job-ları kiçik chunk job-lara parçala
- Horizon supervisor-da `memory` və `maxTime` qoy — sızıntıya qarşı
- Batch progress-i Redis-də saxla, polling yerinə WebSocket broadcast et
- `failed_jobs` cədvəlini vaxtaşırı təmizlə
- `allowFailures()` ilə batch bütün job-u dayandırmasın

---

## Yekun

Spring Batch enterprise-grade ETL framework-dur — chunk processing, restart, partitioning onun DNA-sındadır. Milyonlarla qeyd emal edəndə, "yarıda dayansa dəqiq orada davam etsin" tələb varsa, Spring Batch-in alternativi yoxdur. Java ekosistemində bu bir standartdır.

Laravel-də isə ayrıca batch framework yoxdur. Lakin `Bus::batch()`, `LazyCollection`, Eloquent `chunkById`, artisan command və Horizon birlikdə praktiki ETL həlli verir. Restart semantikası yerinə **idempotent job design** və **checkpoint table** ilə eyni nəticə alınır. Çoxu Laravel layihəsi üçün bu kifayət edir — milyonlarla sətir PHP-də də emal olunur, sadəcə fəlsəfə fərqlidir.

Qısa qayda: **finans, insurance, telekom kimi night-batch vacib olan yerdə Spring Batch; web-first SaaS-də Laravel-in batch-chunk həlli daha sadə və sürətli dəyişdirilə bilir.**
