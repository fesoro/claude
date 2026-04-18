<?php

/**
 * ╔══════════════════════════════════════════════════════════════════════════════════╗
 * ║                                                                                ║
 * ║           LARAVEL JOB SİSTEMİ — TAM HƏYAT DÖVRÜ İZAHI                        ║
 * ║           ═══════════════════════════════════════════════                       ║
 * ║                                                                                ║
 * ║  Bu fayl real PHP class deyil — yalnız öyrənmə üçün şərhlər faylıdır.        ║
 * ║  Bütün Job sistemi haqqında bilməli olduğun hər şey burada izah olunub.       ║
 * ║                                                                                ║
 * ╚══════════════════════════════════════════════════════════════════════════════════╝
 *
 *
 * ════════════════════════════════════════════════════════════════════
 * 1. HANDLER-DƏN JOB-LARIN DİSPATCH EDİLMƏSİ
 * ════════════════════════════════════════════════════════════════════
 *
 * DDD arxitekturada Handler birbaşa Job dispatch ETMİR.
 * Nəyə? Çünki Handler Application Layer-dədir, Job isə Infrastructure-dır.
 * Handler yalnız domain əməliyyatlarını koordinasiya edir.
 *
 * DÜZGÜN AXIN:
 * ────────────
 *
 * Variant 1: Controller dispatch edir (ən sadə)
 * ═══════════════════════════════════════════════
 *
 *   class OrderController
 *   {
 *       public function store(Request $request, CommandBus $bus)
 *       {
 *           // 1. Handler-i çağır — sifariş yarat
 *           $orderDTO = $bus->dispatch(new CreateOrderCommand($dto));
 *
 *           // 2. Sifariş yaradıldıqdan SONRA job-ları dispatch et
 *           ProcessPaymentJob::dispatch(
 *               orderId: $orderDTO->id,
 *               amount: $orderDTO->totalAmount,
 *               currency: 'AZN',
 *               paymentMethod: $request->payment_method,
 *           );
 *
 *           return response()->json($orderDTO, 201);
 *       }
 *   }
 *
 *
 * Variant 2: Event Listener dispatch edir (daha yaxşı)
 * ═══════════════════════════════════════════════════════
 *
 *   // Handler domain event atır (OrderCreated)
 *   // Listener bu event-ə reaksiya verir və Job dispatch edir
 *
 *   class OrderCreatedListener
 *   {
 *       public function handle(OrderCreated $event): void
 *       {
 *           Bus::chain([
 *               new ProcessPaymentJob($event->orderId, ...),
 *               new SendOrderConfirmationJob($event->orderId, ...),
 *           ])->dispatch();
 *       }
 *   }
 *
 *   // Bu yanaşma daha yaxşıdır çünki:
 *   // - Handler job-lardan xəbərsizdir (loose coupling)
 *   // - Yeni listener əlavə etmək asandır (Open/Closed Principle)
 *   // - Test yazmaq asandır (event-ləri mock edə bilərsən)
 *
 *
 * Variant 3: Job Chaining ilə ardıcıl icra
 * ═════════════════════════════════════════
 *
 *   Bus::chain([
 *       new ProcessPaymentJob($orderId, $amount, $currency, $method),
 *       new SendOrderConfirmationJob($orderId, $email, $amount, $items),
 *       new SendPaymentNotificationJob($orderId, $email, true, $amount),
 *   ])->catch(function (Throwable $e) use ($orderId, $email, $amount) {
 *       SendPaymentNotificationJob::dispatch($orderId, $email, false, $amount, $e->getMessage());
 *   })->dispatch();
 *
 *
 *
 * ════════════════════════════════════════════════════════════════════
 * 2. QUEUE CONNECTION KONFİQURASİYASI
 * ════════════════════════════════════════════════════════════════════
 *
 * config/queue.php faylında queue driver-lər təyin olunur.
 * .env faylında hansı driver-in istifadə olunacağı seçilir.
 *
 * .env:
 *   QUEUE_CONNECTION=redis          ← hansı driver istifadə olunsun
 *   REDIS_HOST=127.0.0.1            ← Redis server ünvanı
 *   REDIS_PORT=6379                  ← Redis portu
 *
 * config/queue.php:
 *   'connections' => [
 *       'sync' => [                  ← sinxron (queue yoxdur, dərhal icra)
 *           'driver' => 'sync',
 *       ],
 *       'database' => [              ← DB-də jobs cədvəlində saxlanılır
 *           'driver' => 'database',
 *           'table' => 'jobs',
 *           'queue' => 'default',
 *           'retry_after' => 90,     ← 90 saniyə sonra retry et
 *       ],
 *       'redis' => [                 ← Redis-də saxlanılır (ən populyar)
 *           'driver' => 'redis',
 *           'connection' => 'default',
 *           'queue' => 'default',
 *           'retry_after' => 90,
 *           'block_for' => 5,        ← yeni job gözləmə müddəti (saniyə)
 *       ],
 *   ],
 *
 * DRIVER SEÇİMİ QAYDASI:
 * - Development/test: sync (dərhal icra olunur, debug asandır)
 * - Kiçik proyekt: database (əlavə alət lazım deyil)
 * - Production: redis (sürətli, etibarlı)
 * - Enterprise: rabbitmq (routing, DLQ, federation dəstəyi)
 *
 *
 *
 * ════════════════════════════════════════════════════════════════════
 * 3. QUEUE:WORK NECƏ JOB-LARI GÖTÜRÜR?
 * ════════════════════════════════════════════════════════════════════
 *
 * `php artisan queue:work` — queue-dən job-ları oxuyub icra edən prosesdir.
 *
 * İŞLƏMƏ DÖVRÜ:
 * ─────────────
 *
 *   ┌──────────────────────────────────────────────────┐
 *   │  1. Worker başlayır                              │
 *   │     php artisan queue:work --queue=payments      │
 *   └──────────────────┬───────────────────────────────┘
 *                      │
 *                      ▼
 *   ┌──────────────────────────────────────────────────┐
 *   │  2. Queue-yə baxır: "Yeni job var?"             │
 *   │     Redis: BRPOP queue:payments 5               │
 *   │     DB: SELECT * FROM jobs WHERE queue='payments'│
 *   └──────────────────┬───────────────────────────────┘
 *                      │
 *              ┌───────┴───────┐
 *              │               │
 *           Job VAR         Job YOX
 *              │               │
 *              ▼               ▼
 *   ┌──────────────────┐  ┌──────────────────┐
 *   │ 3. Job-u götür   │  │ 3. Gözlə        │
 *   │    Deserialize et │  │    (block_for=5) │
 *   │    handle() çağır │  │    Sonra 2-yə qayıt│
 *   └────────┬─────────┘  └──────────────────┘
 *            │
 *     ┌──────┴──────┐
 *     │             │
 *   UĞURLU      UĞURSUZ
 *     │             │
 *     ▼             ▼
 *  ┌─────────┐  ┌─────────────────┐
 *  │ Job-u   │  │ Retry lazımdır? │
 *  │ sil     │  │ ($tries > cəhd) │
 *  │ 2-yə   │  └───┬─────────┬───┘
 *  │ qayıt  │      │         │
 *  └─────────┘    BƏLİ      XEYR
 *                   │         │
 *                   ▼         ▼
 *            ┌──────────┐ ┌──────────────┐
 *            │ $backoff  │ │ failed()     │
 *            │ qədər    │ │ çağır        │
 *            │ gözlə,  │ │ failed_jobs-a│
 *            │ queue-yə │ │ yaz          │
 *            │ qaytar   │ │ 2-yə qayıt  │
 *            │ 2-yə    │ └──────────────┘
 *            │ qayıt   │
 *            └──────────┘
 *
 *
 * WORKER ƏMRLƏRI:
 * ───────────────
 *
 *   # Sadə başlatma — "default" queue-dan oxu
 *   php artisan queue:work
 *
 *   # Konkret queue-dan oxu
 *   php artisan queue:work --queue=payments,emails,default
 *   # DİQQƏT: queue sırası = PRİORİTET! payments birinci emal olunur.
 *
 *   # Konkret connection-dan oxu
 *   php artisan queue:work redis --queue=payments
 *
 *   # Max 3 cəhd, 60 saniyə timeout
 *   php artisan queue:work --tries=3 --timeout=60
 *
 *   # Worker-i yenidən başlat (deploy sonrası)
 *   php artisan queue:restart
 *   # DİQQƏT: Bu bütün worker-ləri yavaşca bağlayır (graceful shutdown).
 *   # Hazırkı job-u bitirir, sonra çıxır. Supervisor yenidən başladır.
 *
 *   # queue:work vs queue:listen fərqi:
 *   # queue:work — framework-u bir dəfə yükləyir, sonra job-ları emal edir (sürətli)
 *   # queue:listen — hər job üçün framework-u yenidən yükləyir (yavaş, inkişaf üçün)
 *
 *
 *
 * ════════════════════════════════════════════════════════════════════
 * 4. RETRY, BACKOFF, DEAD LETTER QUEUE
 * ════════════════════════════════════════════════════════════════════
 *
 * RETRY MEXANİZMİ:
 * ────────────────
 * Job uğursuz olduqda, Laravel onu yenidən queue-yə qoyur.
 *
 *   public int $tries = 3;               // Max 3 cəhd
 *   public array $backoff = [10, 60];     // 10san, 60san gözlə
 *
 *   Alternativ: $retryUntil ilə vaxt limiti
 *   public function retryUntil(): DateTime
 *   {
 *       return now()->addHours(1);  // 1 saat ərzində cəhd et
 *   }
 *   // Bu halda $tries əvəzinə vaxt limiti istifadə olunur.
 *   // 1 saat ərzində neçə dəfə lazımdırsa, o qədər retry edir.
 *
 *
 * BACKOFF STRATEGİYALARI:
 * ──────────────────────
 *
 *   1. Sabit backoff:
 *      public int $backoff = 10;  // Hər dəfə 10 saniyə gözlə
 *
 *   2. Eksponensial backoff (massiv):
 *      public array $backoff = [10, 30, 60, 120, 300];
 *      // 10san → 30san → 1dəq → 2dəq → 5dəq
 *
 *   3. Dinamik backoff (metod):
 *      public function backoff(): array
 *      {
 *          return [
 *              $this->attempts() * 10,   // Cəhd sayı * 10 saniyə
 *          ];
 *      }
 *
 *
 * DEAD LETTER QUEUE (DLQ):
 * ───────────────────────
 * DLQ — bütün cəhdlər bitdikdən sonra uğursuz job-ların göndərildiyi
 * "ölü məktublar queue-si"-dir.
 *
 * Laravel-də DLQ built-in deyil, amma:
 * 1. `failed_jobs` cədvəli DLQ rolunu oynayır
 *    php artisan queue:failed          — uğursuz job-ları gör
 *    php artisan queue:retry all       — hamısını yenidən cəhd et
 *    php artisan queue:retry <uuid>    — konkret job-u yenidən cəhd et
 *    php artisan queue:forget <uuid>   — job-u sil
 *    php artisan queue:flush           — bütün failed job-ları sil
 *
 * 2. RabbitMQ istifadə edirsənsə, native DLQ dəstəyi var:
 *    - Job max retry-dan sonra avtomatik DLQ exchange-ə yönləndirilir
 *    - DLQ-dan manual və ya avtomatik retry etmək olur
 *    - Bu pattern "poison message handling" adlanır
 *
 *
 *
 * ════════════════════════════════════════════════════════════════════
 * 5. JOB BATCHING (Toplu İcra)
 * ════════════════════════════════════════════════════════════════════
 *
 * Bus::batch() — çox sayda job-u PARALEL icra etmək üçündür.
 * Chain-dən fərqi: batch-də job-lar bir-birini gözləmir, EYNI ANDA icra olunur.
 *
 * NÜMUNƏ — 1000 istifadəçiyə email göndərmək:
 *
 *   $users = User::all();
 *
 *   $jobs = $users->map(function ($user) {
 *       return new SendPromotionalEmailJob($user->id, $user->email);
 *   })->toArray();
 *
 *   Bus::batch($jobs)
 *       ->name('promotional-emails-2024')       // Batch adı (monitoring üçün)
 *       ->then(function (Batch $batch) {         // Hamısı bitdikdə
 *           Log::info("Batch tamamlandı: {$batch->totalJobs} email göndərildi");
 *       })
 *       ->catch(function (Batch $batch, Throwable $e) {  // Xəta olduqda
 *           Log::error("Batch-də xəta: {$e->getMessage()}");
 *       })
 *       ->finally(function (Batch $batch) {      // Hər halda (uğurlu və ya uğursuz)
 *           Log::info("Batch bitdi. Uğurlu: {$batch->processedJobs()}, Uğursuz: {$batch->failedJobs}");
 *       })
 *       ->allowFailures()                        // Bir job uğursuz olsa da davam et
 *       ->onQueue('emails')                      // Hansı queue-dən icra olunsun
 *       ->dispatch();
 *
 *
 * BATCH DATABASE CƏDVƏLİ:
 * ───────────────────────
 * Batch istifadə etmək üçün migration lazımdır:
 * php artisan queue:batches-table
 * php artisan migrate
 *
 * Bu `job_batches` cədvəlini yaradır — batch progress izlənilir.
 *
 *
 * BATCH MONİTORİNG:
 * ────────────────
 *   $batch = Bus::findBatch($batchId);
 *   $batch->progress();       // 0-100 (faiz)
 *   $batch->totalJobs;        // Ümumi job sayı
 *   $batch->processedJobs();  // Tamamlanmış job sayı
 *   $batch->failedJobs;       // Uğursuz job sayı
 *   $batch->finished();       // Batch bitibmi?
 *   $batch->cancel();         // Batch-i dayandır
 *
 *
 * CHAIN vs BATCH FƏRQ:
 * ───────────────────
 *   Chain: A → B → C (ardıcıl, biri uğursuz = dayan)
 *   Batch: A, B, C eyni anda (paralel, hər biri müstəqil)
 *
 *   Chain istifadə et: ödəniş → email → bildiriş (ardıcıllıq vacibdir)
 *   Batch istifadə et: 1000 email göndər (sıra əhəmiyyətsizdir)
 *
 *
 *
 * ════════════════════════════════════════════════════════════════════
 * 6. JOB MIDDLEWARE (Ara qat)
 * ════════════════════════════════════════════════════════════════════
 *
 * Job Middleware — job icra olunmadan əvvəl/sonra əlavə logika əlavə edir.
 * HTTP Middleware-ə bənzəyir, amma Job-lar üçündür.
 *
 * BUILT-IN MIDDLEWARE-LƏR:
 * ───────────────────────
 *
 *   1. RateLimited — saniyədə neçə job icra olunsun, məhdudlaşdır
 *      use Illuminate\Queue\Middleware\RateLimited;
 *
 *      public function middleware(): array
 *      {
 *          return [new RateLimited('payments')];
 *      }
 *
 *      // AppServiceProvider-da:
 *      RateLimiter::for('payments', function () {
 *          return Limit::perMinute(30);  // Dəqiqədə max 30 ödəniş
 *      });
 *
 *      // NƏYƏ LAZIM? Stripe API-nin rate limit-i var.
 *      // Çox sorğu göndərsək, 429 (Too Many Requests) alırıq.
 *
 *
 *   2. WithoutOverlapping — eyni ID ilə eyni anda iki job icra olunmasın
 *      use Illuminate\Queue\Middleware\WithoutOverlapping;
 *
 *      public function middleware(): array
 *      {
 *          return [new WithoutOverlapping($this->orderId)];
 *      }
 *
 *      // NƏYƏ LAZIM? Eyni sifariş üçün iki ödəniş eyni anda olmasın.
 *      // Lock qoyur: order_123 üçün yalnız bir ProcessPaymentJob işləyir.
 *
 *
 *   3. ThrottlesExceptions — xəta olduqda gözləmə müddəti tətbiq et
 *      use Illuminate\Queue\Middleware\ThrottlesExceptions;
 *
 *      public function middleware(): array
 *      {
 *          return [
 *              (new ThrottlesExceptions(maxAttempts: 3, decayMinutes: 5))
 *                  ->by('stripe-api'),  // Lock açarı
 *          ];
 *      }
 *
 *      // 3 xəta olsa, 5 dəqiqə gözlə, sonra yenidən cəhd et.
 *      // Bu, Circuit Breaker pattern-inə bənzəyir!
 *
 *
 *   4. SkipIfBatchCancelled — batch cancel olubsa, job-u keç
 *      use Illuminate\Queue\Middleware\SkipIfBatchCancelled;
 *
 *      public function middleware(): array
 *      {
 *          return [new SkipIfBatchCancelled()];
 *      }
 *
 *
 * CUSTOM MIDDLEWARE YAZMAQ:
 * ───────────────────────
 *
 *   class LogJobMiddleware
 *   {
 *       public function handle(object $job, callable $next): void
 *       {
 *           $start = microtime(true);
 *           Log::info("Job başladı: " . get_class($job));
 *
 *           $next($job);  // Job-u icra et
 *
 *           $duration = round(microtime(true) - $start, 2);
 *           Log::info("Job bitdi: " . get_class($job) . " ({$duration}s)");
 *       }
 *   }
 *
 *   // Job-da:
 *   public function middleware(): array
 *   {
 *       return [new LogJobMiddleware()];
 *   }
 *
 *
 *
 * ════════════════════════════════════════════════════════════════════
 * 7. LARAVEL HORIZON (Queue Monitoring Dashboard)
 * ════════════════════════════════════════════════════════════════════
 *
 * Horizon — Laravel-in Redis queue-ları üçün monitor dashboard-udur.
 * Yalnız Redis driver ilə işləyir!
 *
 * QURAŞDIRMA:
 * ───────────
 *   composer require laravel/horizon
 *   php artisan horizon:install
 *   php artisan horizon           ← Horizon worker başladır
 *
 *   Dashboard: https://yourapp.com/horizon
 *
 *
 * HORIZON NƏ TƏMİN EDİR?
 * ──────────────────────
 *
 *   1. Real-time Dashboard:
 *      - Aktiv job sayı
 *      - Queue uzunluğu (neçə job gözləyir)
 *      - İcra müddəti (orta, min, max)
 *      - Uğursuz job-lar
 *      - Son icra olunan job-lar
 *
 *   2. Metriklər və Qrafiklər:
 *      - Job throughput (saniyədə neçə job)
 *      - Queue wait time (job neçə saniyə gözlədi)
 *      - İcra müddəti trendi
 *
 *   3. Uğursuz Job İdarəsi:
 *      - Uğursuz job-ları görmək
 *      - Exception və stack trace görmək
 *      - Bir kliklə retry etmək
 *
 *   4. Worker İdarəsi:
 *      - Worker sayını avtomatik tənzimləmək (auto-scaling)
 *      - Queue prioritetlərini təyin etmək
 *      - Worker-ləri yenidən başlatmaq
 *
 *
 * HORİZON KONFİQURASİYASI (config/horizon.php):
 * ──────────────────────────────────────────────
 *
 *   'environments' => [
 *       'production' => [
 *           'supervisor-payments' => [    // Ödəniş worker-i
 *               'connection' => 'redis',
 *               'queue' => ['payments'],
 *               'maxProcesses' => 10,     // Max 10 worker
 *               'minProcesses' => 2,      // Min 2 worker
 *               'balanceMaxShift' => 1,   // Auto-scale sürəti
 *               'tries' => 5,
 *               'timeout' => 120,
 *           ],
 *           'supervisor-emails' => [      // Email worker-i
 *               'connection' => 'redis',
 *               'queue' => ['emails', 'notifications'],
 *               'maxProcesses' => 5,
 *               'minProcesses' => 1,
 *               'tries' => 3,
 *               'timeout' => 60,
 *           ],
 *           'supervisor-outbox' => [      // Outbox worker-i
 *               'connection' => 'redis',
 *               'queue' => ['outbox'],
 *               'maxProcesses' => 2,
 *               'minProcesses' => 1,
 *               'tries' => 3,
 *               'timeout' => 120,
 *           ],
 *       ],
 *   ],
 *
 *   // HƏR QUEUE ÜÇÜN AYRI SUPERVISOR:
 *   // - payments: 2-10 worker (çox trafik olsa auto-scale edir)
 *   // - emails: 1-5 worker
 *   // - outbox: 1-2 worker (ShouldBeUnique olduğu üçün çox lazım deyil)
 *
 *
 * HORİZON ALTERNATİVLƏRİ:
 * ──────────────────────
 * - Horizon: Yalnız Redis, ən yaxşı Laravel inteqrasiyası
 * - Supervisor: İstənilən driver, amma dashboard yoxdur
 * - Docker: Container-lərdə worker işlətmək
 * - AWS SQS + CloudWatch: AWS cloud monitoring
 *
 *
 *
 * ════════════════════════════════════════════════════════════════════
 * XÜLASƏ — BU PROYEKTDƏKİ JOB-LAR
 * ════════════════════════════════════════════════════════════════════
 *
 * ┌────────────────────────────────┬──────────┬────────┬──────────────┐
 * │ Job                            │ Queue    │ Tries  │ Məqsəd       │
 * ├────────────────────────────────┼──────────┼────────┼──────────────┤
 * │ ProcessPaymentJob              │ payments │ 5      │ Ödəniş emalı │
 * │ SendOrderConfirmationJob       │ emails   │ 3      │ Sifariş email│
 * │ SendPaymentNotificationJob     │ notifs   │ 3      │ Ödəniş nətic.│
 * │ PublishOutboxMessagesJob       │ outbox   │ 3      │ Outbox → MQ  │
 * │ CheckCircuitBreakerJob         │ default  │ 2      │ CB yoxlaması │
 * └────────────────────────────────┴──────────┴────────┴──────────────┘
 *
 * TIPIK AXIN:
 * ──────────
 * 1. İstifadəçi sifariş verir
 * 2. Controller → CreateOrderHandler → DB-yə yaz + Outbox-a event yaz
 * 3. Controller → ProcessPaymentJob::dispatch() → queue-yə göndər
 * 4. Worker → ProcessPaymentJob → CommandBus → ProcessPaymentHandler → Stripe
 * 5. Uğurlu → SendOrderConfirmationJob → email göndər
 * 6. PublishOutboxMessagesJob (scheduled) → Outbox-dan RabbitMQ-ya
 * 7. CheckCircuitBreakerJob (scheduled) → Circuit Breaker-ləri yoxla
 */
