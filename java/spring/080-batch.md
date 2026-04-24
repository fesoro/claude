# 080 — Spring Batch — Geniş İzah
**Səviyyə:** İrəli


## Mündəricat
1. [Spring Batch nədir?](#spring-batch-nədir)
2. [Job, Step, Chunk](#job-step-chunk)
3. [ItemReader, ItemProcessor, ItemWriter](#itemreader-itemprocessor-itemwriter)
4. [Partitioning (parallel processing)](#partitioning-parallel-processing)
5. [Job monitoring](#job-monitoring)
6. [İntervyu Sualları](#intervyu-sualları)

---

## Spring Batch nədir?

**Spring Batch** — böyük həcmli məlumat emalı üçün lightweight batch processing framework-ü. CSV import, hesabat generasiyası, data migration, ETL əməliyyatları üçün istifadə olunur.

```xml
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-batch</artifactId>
</dependency>
```

---

## Job, Step, Chunk

```
Job (bütün batch proses)
  └── Step 1 (mərhələ)
        ├── ItemReader (məlumat oxu)
        ├── ItemProcessor (emal)
        └── ItemWriter (yaz)
  └── Step 2
  └── Step 3 (tasklet — sadə tapşırıq)
```

```java
@Configuration
@EnableBatchProcessing
public class BatchConfig {

    private final JobRepository jobRepository;
    private final PlatformTransactionManager transactionManager;
    private final DataSource dataSource;

    // ===== Job =====
    @Bean
    public Job importUserJob(Step importStep, Step notifyStep) {
        return new JobBuilder("importUserJob", jobRepository)
            .start(importStep)
            .next(notifyStep)
            .listener(jobExecutionListener())
            .build();
    }

    // ===== Chunk Step =====
    @Bean
    public Step importStep(ItemReader<UserCsv> reader,
                            ItemProcessor<UserCsv, User> processor,
                            ItemWriter<User> writer) {

        return new StepBuilder("importStep", jobRepository)
            .<UserCsv, User>chunk(100, transactionManager) // 100-lük batch
            .reader(reader)
            .processor(processor)
            .writer(writer)
            .faultTolerant()
            .skipLimit(10)                    // Maksimum 10 xəta keç
            .skip(ValidationException.class)  // Bu exception-u keç
            .retry(DeadlockLoserDataAccessException.class) // Retry et
            .retryLimit(3)
            .listener(stepExecutionListener())
            .build();
    }

    // ===== Tasklet Step =====
    @Bean
    public Step notifyStep() {
        return new StepBuilder("notifyStep", jobRepository)
            .tasklet((contribution, chunkContext) -> {
                log.info("İmport tamamlandı, bildiriş göndərilir");
                notificationService.sendImportComplete();
                return RepeatStatus.FINISHED;
            }, transactionManager)
            .build();
    }

    // Job Listener
    @Bean
    public JobExecutionListener jobExecutionListener() {
        return new JobExecutionListenerSupport() {
            @Override
            public void beforeJob(JobExecution jobExecution) {
                log.info("Job başladı: {}", jobExecution.getJobInstance().getJobName());
            }

            @Override
            public void afterJob(JobExecution jobExecution) {
                if (jobExecution.getStatus() == BatchStatus.COMPLETED) {
                    log.info("Job tamamlandı. Read={}, Write={}, Skip={}",
                        jobExecution.getStepExecutions().iterator().next().getReadCount(),
                        jobExecution.getStepExecutions().iterator().next().getWriteCount(),
                        jobExecution.getStepExecutions().iterator().next().getSkipCount());
                } else {
                    log.error("Job uğursuz: {}", jobExecution.getStatus());
                }
            }
        };
    }
}
```

---

## ItemReader, ItemProcessor, ItemWriter

```java
// ===== FlatFileItemReader — CSV oxuma =====
@Bean
public FlatFileItemReader<UserCsv> csvReader() {
    return new FlatFileItemReaderBuilder<UserCsv>()
        .name("userItemReader")
        .resource(new ClassPathResource("users.csv"))
        .delimited()
        .delimiter(",")
        .names("name", "email", "age", "department")
        .targetType(UserCsv.class)
        .linesToSkip(1) // Header sətrini keç
        .build();
}

// ===== JpaPagingItemReader — DB-dən oxuma =====
@Bean
public JpaPagingItemReader<OldUser> dbReader(EntityManagerFactory emf) {
    return new JpaPagingItemReaderBuilder<OldUser>()
        .name("dbUserReader")
        .entityManagerFactory(emf)
        .queryString("SELECT u FROM OldUser u WHERE u.migrated = false ORDER BY u.id")
        .pageSize(100)
        .build();
}

// ===== ItemProcessor =====
@Component
public class UserItemProcessor implements ItemProcessor<UserCsv, User> {

    private final DepartmentRepository departmentRepository;

    @Override
    public User process(UserCsv item) throws Exception {
        // Validation
        if (item.getEmail() == null || !item.getEmail().contains("@")) {
            log.warn("Yanlış email, keç: {}", item.getName());
            return null; // null qaytarsa — bu item writer-a getmir
        }

        // Transform
        User user = new User();
        user.setName(item.getName().trim());
        user.setEmail(item.getEmail().toLowerCase());
        user.setAge(Integer.parseInt(item.getAge()));

        Department dept = departmentRepository
            .findByName(item.getDepartment())
            .orElseGet(() -> departmentRepository.save(
                new Department(item.getDepartment())));
        user.setDepartment(dept);

        return user;
    }
}

// ===== JpaItemWriter — DB-yə yazma =====
@Bean
public JpaItemWriter<User> jpaWriter(EntityManagerFactory emf) {
    JpaItemWriter<User> writer = new JpaItemWriter<>();
    writer.setEntityManagerFactory(emf);
    return writer;
}

// ===== FlatFileItemWriter — CSV yaratma =====
@Bean
public FlatFileItemWriter<UserDto> csvWriter() {
    return new FlatFileItemWriterBuilder<UserDto>()
        .name("userExportWriter")
        .resource(new FileSystemResource("/output/users-export.csv"))
        .delimited()
        .delimiter(",")
        .names("id", "name", "email", "department")
        .headerCallback(writer -> writer.write("ID,Ad,Email,Şöbə"))
        .build();
}

// ===== Composite Writer — bir neçə writer =====
@Bean
public CompositeItemWriter<User> compositeWriter(JpaItemWriter<User> dbWriter,
                                                   FlatFileItemWriter<User> fileWriter) {
    CompositeItemWriter<User> composite = new CompositeItemWriter<>();
    composite.setDelegates(List.of(dbWriter, fileWriter));
    return composite;
}
```

---

## Partitioning (parallel processing)

```java
@Configuration
public class PartitionedBatchConfig {

    // Partitioner — data-nı bölür
    @Bean
    public Partitioner partitioner() {
        return gridSize -> {
            Map<String, ExecutionContext> partitions = new HashMap<>();
            long totalRecords = userRepository.count();
            long chunkSize = (totalRecords / gridSize) + 1;

            for (int i = 0; i < gridSize; i++) {
                ExecutionContext context = new ExecutionContext();
                context.putLong("minId", i * chunkSize + 1);
                context.putLong("maxId", (i + 1) * chunkSize);
                partitions.put("partition" + i, context);
            }

            return partitions;
        };
    }

    // Slave step — hər partition üçün
    @Bean
    public Step slaveStep() {
        return new StepBuilder("slaveStep", jobRepository)
            .<User, UserDto>chunk(100, transactionManager)
            .reader(partitionedReader(null, null)) // Context-dən doldurulur
            .processor(userProcessor())
            .writer(userWriter())
            .build();
    }

    // Master step — partition-ları koordinasiya edir
    @Bean
    public Step masterStep(Step slaveStep, Partitioner partitioner) {
        return new StepBuilder("masterStep", jobRepository)
            .partitioner("slaveStep", partitioner)
            .step(slaveStep)
            .gridSize(4)                          // 4 paralel partition
            .taskExecutor(partitionTaskExecutor()) // Thread pool
            .build();
    }

    @Bean
    @StepScope
    public JpaPagingItemReader<User> partitionedReader(
            @Value("#{stepExecutionContext['minId']}") Long minId,
            @Value("#{stepExecutionContext['maxId']}") Long maxId) {

        return new JpaPagingItemReaderBuilder<User>()
            .name("partitionedUserReader")
            .entityManagerFactory(emf)
            .queryString("SELECT u FROM User u WHERE u.id >= :minId AND u.id <= :maxId")
            .parameterValues(Map.of("minId", minId, "maxId", maxId))
            .pageSize(100)
            .build();
    }

    @Bean
    public TaskExecutor partitionTaskExecutor() {
        ThreadPoolTaskExecutor executor = new ThreadPoolTaskExecutor();
        executor.setCorePoolSize(4);
        executor.setMaxPoolSize(8);
        executor.setThreadNamePrefix("batch-partition-");
        executor.initialize();
        return executor;
    }
}
```

---

## Job monitoring

```java
// Job-u manual başlatmaq
@Service
public class BatchJobLauncher {

    private final JobLauncher jobLauncher;
    private final Job importUserJob;

    @PostMapping("/api/batch/import")
    public ResponseEntity<String> startImport() {
        try {
            JobParameters params = new JobParametersBuilder()
                .addLocalDateTime("startTime", LocalDateTime.now())
                .addString("fileLocation", "/imports/users.csv")
                .toJobParameters();

            JobExecution execution = jobLauncher.run(importUserJob, params);

            return ResponseEntity.ok("Job başladı: " + execution.getId());
        } catch (Exception e) {
            return ResponseEntity.internalServerError()
                .body("Job başlaya bilmədi: " + e.getMessage());
        }
    }

    public JobExecution getJobStatus(Long jobExecutionId) {
        return jobExplorer.getJobExecution(jobExecutionId);
    }
}

// JobExplorer ilə monitoring
@Service
public class BatchMonitoringService {

    private final JobExplorer jobExplorer;
    private final JobOperator jobOperator;

    public List<JobInstance> getRecentJobs(String jobName, int count) {
        return jobExplorer.getJobInstances(jobName, 0, count);
    }

    public JobExecution getLatestExecution(String jobName) {
        return jobExplorer.getLastJobExecution(
            jobExplorer.getLastJobInstance(jobName));
    }

    public void stopJob(Long jobExecutionId) {
        jobOperator.stop(jobExecutionId);
    }

    public Long restartJob(Long jobExecutionId) {
        return jobOperator.restart(jobExecutionId);
    }
}
```

---

## İntervyu Sualları

### 1. Chunk-oriented processing nədir?
**Cavab:** Read → Process → Write əməliyyatlarını `chunkSize` ölçüsündə batch-lərlə icra edir. Hər chunk ayrı transaction-da işləyir. Xəta olarsa yalnız o chunk rollback olur, əvvəlkilər qalır. `chunk(100)` — hər dəfə 100 record oxu, emal et, yaz; transaction commit et.

### 2. ItemProcessor null qaytarsa nə olur?
**Cavab:** Həmin item writer-a göndərilmir — filterlənir. Invalid, duplicate, yaxud emal olunmamalı record-ları null qaytararaq keçmək mümkündür. Skip sayına daxil edilmir.

### 3. Skip vs Retry fərqi nədir?
**Cavab:** `skip` — xətalı record-u keç, batch davam etsin. `retry` — eyni record-u yenidən cəhd et (məsələn deadlock). `skipLimit(10)` — maksimum 10 skip; artıq olsa job fail olur. Hər ikisi `faultTolerant()` ilə istifadə edilir.

### 4. @StepScope nə üçündür?
**Cavab:** Step-ə bağlı bean scope-u. Hər step execution üçün yeni instance yaradılır. Partition step-lərində hər partition-un öz reader instance-ı olur. `@Value("#{stepExecutionContext['key']}")` ilə step-ə xas parametrləri (minId, maxId) inject etmək üçün lazımdır.

### 5. Job Parameters niyə vacibdir?
**Cavab:** Spring Batch eyni parametrlərlə job-u yenidən işlətmir (idempotency). `addLocalDateTime("startTime")` ilə hər run unique parametr alır. Bu, eyni job-un bir neçə dəfə qəzasız başlatılmasına imkan verir. Parametrsiz job yalnız bir dəfə uğurla icra olunur.

*Son yenilənmə: 2026-04-10*
