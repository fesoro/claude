<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Src\Payment\Application\Commands\ProcessPayment\ProcessPaymentCommand;
use Src\Shared\Application\Bus\CommandBus;

/**
 * PROCESS PAYMENT JOB (Laravel Queue sistemi)
 * =============================================
 * Sifariş yaradıldıqda ödənişi arxa planda (background) emal edən Job.
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  JOB NƏDİR VƏ NƏYƏ LAZIMDIR?                                         ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                        ║
 * ║  Job — "arxa planda icra olunan tapşırıq" deməkdir.                   ║
 * ║                                                                        ║
 * ║  PROBLEM: İstifadəçi "Sifariş ver" düyməsinə basır. Biz bu an:       ║
 * ║  1. Sifarişi DB-yə yazmalıyıq (tez — 50ms)                           ║
 * ║  2. Ödənişi emal etməliyik (yavaş — 2-5 saniyə, Stripe API çağırışı) ║
 * ║  3. Email göndərməliyik (yavaş — 1-3 saniyə)                         ║
 * ║                                                                        ║
 * ║  Əgər hamısını sinxron etsək: istifadəçi 8 saniyə gözləyir!          ║
 * ║  Bu pis UX-dir (User Experience).                                      ║
 * ║                                                                        ║
 * ║  HƏLLİ: Ödəniş və email-i QUEUE-yə (növbəyə) göndəririk.           ║
 * ║  İstifadəçi dərhal cavab alır: "Sifarişiniz qəbul olundu!"           ║
 * ║  Arxa planda Queue Worker ödənişi və email-i emal edir.               ║
 * ║                                                                        ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  SİNXRON vs ASİNXRON (dispatch vs dispatchSync)                       ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                        ║
 * ║  1) ASİNXRON (default) — dispatch():                                  ║
 * ║     ProcessPaymentJob::dispatch($orderId, $amount, $currency, $method);║
 * ║     → Job queue-yə (Redis/DB) yazılır                                 ║
 * ║     → Kod DAVAM EDİR, gözləmir                                        ║
 * ║     → Arxa planda `queue:work` worker onu emal edir                   ║
 * ║     → İstifadəçi gözləmir!                                            ║
 * ║                                                                        ║
 * ║  2) SİNXRON — dispatchSync():                                         ║
 * ║     ProcessPaymentJob::dispatchSync($orderId, $amount, ...);          ║
 * ║     → Job ELƏ İNDİ, EYNİ PROSESDƏ icra olunur                        ║
 * ║     → Queue istifadə OLUNMUR                                          ║
 * ║     → Test yazarkən və ya debug edərkən faydalıdır                    ║
 * ║     → İstifadəçi GÖZLƏYİR!                                           ║
 * ║                                                                        ║
 * ║  QAYDA: Production-da dispatch() istifadə et.                         ║
 * ║         Test-lərdə dispatchSync() istifadə edə bilərsən.              ║
 * ║                                                                        ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ShouldQueue İNTERFEYSİ                                               ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                        ║
 * ║  `implements ShouldQueue` — bu interfeys Job-un QUEUE-dən keçməsini    ║
 * ║  bildirən İŞARƏdir (marker interface).                                ║
 * ║                                                                        ║
 * ║  ShouldQueue OLMADAN:                                                  ║
 * ║    dispatch() çağırsan belə, Job sinxron icra olunur.                 ║
 * ║                                                                        ║
 * ║  ShouldQueue İLƏ:                                                      ║
 * ║    dispatch() çağırdıqda Job aşağıdakı queue driver-lərdən birinə     ║
 * ║    yazılır:                                                            ║
 * ║    - Redis (ən populyar, sürətli, real-time)                          ║
 * ║    - Database (sadə, əlavə alət lazım deyil)                          ║
 * ║    - RabbitMQ (enterprise, routing, DLQ dəstəyi)                      ║
 * ║    - Amazon SQS (AWS cloud)                                            ║
 * ║    - Beanstalkd (köhnə, az istifadə olunur)                          ║
 * ║                                                                        ║
 * ║  .env-də seçilir: QUEUE_CONNECTION=redis                              ║
 * ║                                                                        ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  $tries, $backoff, $timeout XÜSUSİYYƏTLƏRİ                          ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                        ║
 * ║  $tries — Job neçə dəfə cəhd olunsun?                                ║
 * ║    public int $tries = 3;                                              ║
 * ║    → 1-ci cəhd uğursuz → 2-ci cəhd → 3-cü cəhd → FAILED            ║
 * ║    → 3-dən sonra failed() metodu çağırılır                             ║
 * ║                                                                        ║
 * ║  $backoff — Cəhdlər arası neçə saniyə gözləsin?                      ║
 * ║    public int $backoff = 10;  → Hər dəfə 10 saniyə gözlə            ║
 * ║    public array $backoff = [10, 60, 300];  → Eksponensial:            ║
 * ║      1-ci uğursuzluqdan sonra 10 san, 2-ci-dən sonra 60 san,         ║
 * ║      3-cü-dən sonra 300 san (5 dəqiqə) gözlə                        ║
 * ║    → Bu "exponential backoff" adlanır — hər dəfə daha çox gözləyir   ║
 * ║    → Xarici API-ni "bombalamaq" əvəzinə, ona bərpa vaxtı verir       ║
 * ║                                                                        ║
 * ║  $timeout — Job ən çox neçə saniyə işləyə bilər?                     ║
 * ║    public int $timeout = 120;  → 120 saniyə sonra timeout olur       ║
 * ║    → Stripe API cavab vermirsə, sonsuza qədər gözləməsin             ║
 * ║    → Bu resurslara qənaət edir                                        ║
 * ║                                                                        ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  failed() METODU — Bütün cəhdlər bitdikdə nə olur?                   ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                        ║
 * ║  $tries qədər cəhd edildikdən sonra Job "failed" (uğursuz) sayılır.  ║
 * ║  Bu zaman:                                                             ║
 * ║  1. failed() metodu çağırılır (əgər Job-da təyin olunubsa)            ║
 * ║  2. Job `failed_jobs` cədvəlinə yazılır                               ║
 * ║  3. İstəsən JobFailed event ilə digər action-lar edə bilərsən        ║
 * ║                                                                        ║
 * ║  failed() metodunda nə etmək olar:                                     ║
 * ║  - Admin-ə xəbərdarlıq göndərmək (email, Slack, SMS)                ║
 * ║  - Sifarişin statusunu "payment_failed" etmək                         ║
 * ║  - Log yazmaq                                                          ║
 * ║  - Kompensasiya əməliyyatı (compensating transaction) icra etmək      ║
 * ║                                                                        ║
 * ║  Uğursuz job-ları yenidən cəhd etmək:                                ║
 * ║  php artisan queue:retry all       — bütün uğursuzları yenidən cəhd et║
 * ║  php artisan queue:retry <uuid>    — konkret job-u yenidən cəhd et   ║
 * ║                                                                        ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  JOB CHAINING — Bus::chain()                                          ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                        ║
 * ║  Job Chaining — job-ları ardıcıl (bir-birinin ardınca) icra etmək.    ║
 * ║  Əgər birinci uğursuz olsa, qalanlar icra OLUNMUR.                    ║
 * ║                                                                        ║
 * ║  Bus::chain([                                                          ║
 * ║      new ProcessPaymentJob($orderId, $amount, $currency, $method),    ║
 * ║      new SendOrderConfirmationJob($orderId, $email, $amount, $items), ║
 * ║      new SendPaymentNotificationJob($orderId, $email, true, $amount), ║
 * ║  ])->dispatch();                                                       ║
 * ║                                                                        ║
 * ║  AXIN:                                                                 ║
 * ║  ProcessPaymentJob → uğurlu? → SendOrderConfirmationJob → uğurlu? →  ║
 * ║    → SendPaymentNotificationJob                                        ║
 * ║                                                                        ║
 * ║  Əgər ProcessPaymentJob uğursuz olsa:                                 ║
 * ║  → SendOrderConfirmationJob İCRA OLUNMUR                              ║
 * ║  → SendPaymentNotificationJob İCRA OLUNMUR                            ║
 * ║  → catch callback çağırılır (əgər təyin olunubsa)                     ║
 * ║                                                                        ║
 * ║  Bus::chain([...])->catch(function (Throwable $e) {                   ║
 * ║      Log::error('Job zənciri uğursuz oldu: ' . $e->getMessage());    ║
 * ║  })->dispatch();                                                       ║
 * ║                                                                        ║
 * ║  FƏRQ: chain() vs batch():                                            ║
 * ║  - chain() — ardıcıl: A → B → C (biri uğursuz = dayan)              ║
 * ║  - batch() — paralel: A, B, C eyni anda (hər biri müstəqil)         ║
 * ║                                                                        ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  JOB vs EVENT/LISTENER — FƏRQ NƏDİR?                                 ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                        ║
 * ║  JOB = AÇIQ TAPŞIRIQ (Explicit Task)                                  ║
 * ║  - "BU İŞİ ET!" deyirsən                                              ║
 * ║  - Kim edəcəyini bilirsən (konkret Job class)                        ║
 * ║  - ProcessPaymentJob::dispatch(...) — "Ödənişi emal et!"             ║
 * ║  - Birbaşa dispatch edirsən                                            ║
 * ║                                                                        ║
 * ║  EVENT/LISTENER = REAKSİYA (Reaction)                                 ║
 * ║  - "BU OLDU" deyirsən, kim nə edəcəyini BİLMİRSƏN                   ║
 * ║  - OrderCreated event atılır → Listener-lər özləri reagent edir       ║
 * ║  - OrderCreated → SendWelcomeEmail, UpdateInventory, NotifyWarehouse  ║
 * ║  - Loose coupling (aşağı asılılıq)                                    ║
 * ║                                                                        ║
 * ║  QAYDA:                                                                ║
 * ║  - Konkret bir iş etmək lazımdırsa → JOB istifadə et                 ║
 * ║  - "Nəsə baş verdi, kimə maraqlıdırsa bilsin" → EVENT istifadə et   ║
 * ║                                                                        ║
 * ║  NÜMUNƏ:                                                               ║
 * ║  - Ödənişi emal et → Job (konkret, birbaşa)                          ║
 * ║  - Sifariş yaradıldı, kim istəyir reagent versin → Event             ║
 * ║                                                                        ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  QUEUE WORKER NECƏ İŞLƏYİR? (php artisan queue:work)                 ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                        ║
 * ║  Queue Worker — queue-dən job-ları oxuyub icra edən prosesdir.        ║
 * ║                                                                        ║
 * ║  İŞLƏMƏ PRİNSİPİ:                                                     ║
 * ║  1. Worker başladılır: php artisan queue:work                          ║
 * ║  2. Queue-yə baxır: "Yeni job var?"                                   ║
 * ║  3. Job var → götürür, handle() metodunu çağırır                      ║
 * ║  4. Uğurlu → Job silir, növbəti job-a keçir                          ║
 * ║  5. Uğursuz → retry edir və ya failed_jobs-a yazır                    ║
 * ║  6. Job yoxdur → qısa gözləyir, sonra yenə yoxlayır                 ║
 * ║                                                                        ║
 * ║  ƏMRLƏR:                                                               ║
 * ║  php artisan queue:work              — Worker başlat                   ║
 * ║  php artisan queue:work --tries=3    — Max 3 cəhd                     ║
 * ║  php artisan queue:work --timeout=60 — Max 60 saniyə                  ║
 * ║  php artisan queue:work --queue=high — "high" adlı queue-dan oxu     ║
 * ║  php artisan queue:restart           — Bütün worker-ləri yenidən başlat║
 * ║                                                                        ║
 * ║  PRODUCTION-DA:                                                        ║
 * ║  Supervisor ilə işlədilir ki, worker çöksə avtomatik yenidən başlasın║
 * ║  Və ya Laravel Horizon istifadə olunur (Redis üçün dashboard).        ║
 * ║                                                                        ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 *
 * AXIN (Bu Job-un):
 * ════════════════
 * 1. Sifariş yaradılır → Controller və ya Handler bu Job-u dispatch edir
 * 2. Job queue-yə düşür (Redis/DB/RabbitMQ)
 * 3. Queue Worker job-u götürür
 * 4. handle() metodu çağırılır
 * 5. ProcessPaymentCommand yaradılır və CommandBus ilə göndərilir
 * 6. Uğurlu olsa → bitir
 * 7. Uğursuz olsa → $tries qədər yenidən cəhd edir
 * 8. Bütün cəhdlər uğursuz → failed() çağırılır
 */
class ProcessPaymentJob implements ShouldQueue
{
    /**
     * Laravel Queue trait-ləri — hər birinin öz rolu var:
     *
     * Dispatchable — dispatch(), dispatchSync(), dispatchAfterResponse() kimi
     *   statik metodları əlavə edir. Bu trait olmadan Job-u dispatch edə bilməzsən.
     *
     * InteractsWithQueue — Job-un queue ilə əlaqə qurmasını təmin edir:
     *   $this->release(30) — Job-u 30 saniyə sonra yenidən queue-yə qoy
     *   $this->delete() — Job-u queue-dən sil (artıq lazım deyilsə)
     *   $this->attempts() — Neçənci cəhd olduğunu öyrən
     *   $this->fail() — Job-u manual olaraq uğursuz say
     *
     * Queueable — queue, connection, delay kimi xüsusiyyətləri əlavə edir:
     *   ->onQueue('payments') — hansı queue-yə göndərilsin
     *   ->onConnection('redis') — hansı driver istifadə olunsun
     *   ->delay(now()->addMinutes(5)) — 5 dəqiqə sonra icra olunsun
     *
     * SerializesModels — Eloquent model-lərini serialize/deserialize edir:
     *   Əgər Job-a User model göndərsən, queue-yə yazılarkən yalnız ID saxlanır.
     *   Job icra olunanda ID ilə DB-dən yenidən yüklənir (fresh data).
     */
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Maksimum cəhd sayı.
     * Ödəniş kritik əməliyyatdır, 5 dəfə cəhd edirik.
     * Hər uğursuz cəhddən sonra $backoff qədər gözləyirik.
     */
    public int $tries = 5;

    /**
     * Eksponensial backoff — hər uğursuz cəhddən sonra daha çox gözlə.
     *
     * 1-ci uğursuzluq → 10 saniyə gözlə
     * 2-ci uğursuzluq → 30 saniyə gözlə
     * 3-cü uğursuzluq → 60 saniyə gözlə (1 dəqiqə)
     * 4-cü uğursuzluq → 120 saniyə gözlə (2 dəqiqə)
     *
     * NƏYƏ EKSPONENSİAL?
     * Stripe çöküb. Əgər hər 5 saniyədən bir sorğu göndərsək,
     * minlərlə server eyni anda Stripe-ı "bombalar" — bu Stripe-ın bərpasını
     * daha da çətinləşdirir! Eksponensial backoff buna mane olur.
     */
    public array $backoff = [10, 30, 60, 120];

    /**
     * Job ən çox neçə saniyə işləyə bilər.
     * Stripe API cavab vermirsə, 120 saniyə sonra timeout olur.
     * Bu, worker-in "sonsuz gözləmə"yə düşməsinin qarşısını alır.
     */
    public int $timeout = 120;

    /**
     * Konstruktor — Job yaradılarkən lazım olan dataları qəbul edir.
     *
     * DİQQƏT: Konstruktorda ağır işlər (DB sorğusu, API çağırışı) etmə!
     * Konstruktor dispatch() zamanı çağırılır — yəni controller-dədir.
     * Ağır işlər handle()-da olmalıdır — yəni worker tərəfindədir.
     *
     * @param string $orderId Sifariş ID-si
     * @param float $amount Ödəniş məbləği
     * @param string $currency Valyuta kodu (USD, AZN, EUR)
     * @param string $paymentMethod Ödəniş üsulu (credit_card, paypal, bank_transfer)
     */
    public function __construct(
        private readonly string $orderId,
        private readonly float $amount,
        private readonly string $currency,
        private readonly string $paymentMethod,
    ) {
    }

    /**
     * Job-un əsas icra metodu — Queue Worker tərəfindən çağırılır.
     *
     * handle() metodu dependency injection dəstəkləyir:
     * Laravel Service Container avtomatik olaraq CommandBus-u inject edir.
     * Bunu konstruktorda deyil, burada edirik çünki:
     * - Konstruktor: dispatch zamanı (controller prosesində) icra olunur
     * - handle(): worker prosesində icra olunur
     * - Worker-in öz Service Container-i var
     *
     * @param CommandBus $commandBus CQRS Command Bus — command-ı handler-ə yönləndirir
     */
    public function handle(CommandBus $commandBus): void
    {
        Log::info('Ödəniş emalı başladı', [
            'order_id' => $this->orderId,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'payment_method' => $this->paymentMethod,
            'attempt' => $this->attempts(), // Neçənci cəhd olduğunu göstərir
        ]);

        /**
         * ProcessPaymentCommand yaradılır və CommandBus ilə göndərilir.
         *
         * NƏYƏ BİRBAŞA HANDLER ÇAĞIRMIRUQ?
         * - CommandBus middleware pipeline-ı var (logging, transaction, validation)
         * - Loose coupling — Job handler-i birbaşa tanımır
         * - Command-ı göndərir, Bus doğru handler-i tapır
         *
         * AXIN:
         * Job::handle()
         *   → CommandBus::dispatch(ProcessPaymentCommand)
         *     → [Middleware Pipeline]
         *       → ProcessPaymentHandler::handle()
         *         → PaymentGateway (Stripe/PayPal) çağırılır
         */
        $command = new ProcessPaymentCommand(
            orderId: $this->orderId,
            amount: $this->amount,
            currency: $this->currency,
            paymentMethod: $this->paymentMethod,
        );

        $commandBus->dispatch($command);

        Log::info('Ödəniş uğurla emal olundu', [
            'order_id' => $this->orderId,
            'amount' => $this->amount,
        ]);
    }

    /**
     * Bütün cəhdlər uğursuz olduqda çağırılır (failed callback).
     *
     * Bu metod $tries (5) dəfə cəhd edildikdən sonra çağırılır.
     * Yəni ödəniş 5 dəfə uğursuz oldu — artıq yenidən cəhd olunmayacaq.
     *
     * BURADA NƏ EDİRİK:
     * 1. Xətanı log-a yazırıq (debug üçün)
     * 2. Admin-ə bildiriş göndərə bilərik (Slack, email)
     * 3. Sifarişin statusunu "payment_failed" edə bilərik
     *
     * DİQQƏT: Bu metod artıq try/catch-ə ehtiyac duymur.
     * Əgər failed() özü xəta atsa, o xəta sadəcə log olunur.
     *
     * @param \Throwable $exception Son uğursuz cəhdin exception-ı
     */
    public function failed(\Throwable $exception): void
    {
        Log::critical('Ödəniş emalı tamamilə uğursuz oldu! Bütün cəhdlər bitdi.', [
            'order_id' => $this->orderId,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'payment_method' => $this->paymentMethod,
            'exception' => $exception->getMessage(),
            'tries_exhausted' => $this->tries,
        ]);

        /**
         * REAL PROYEKTDƏ BURADA:
         *
         * 1. Sifarişin statusunu yeniləmək:
         *    Order::where('id', $this->orderId)->update(['status' => 'payment_failed']);
         *
         * 2. İstifadəçiyə bildiriş göndərmək:
         *    SendPaymentNotificationJob::dispatch(
         *        $this->orderId, $userEmail, false, $this->amount,
         *        $exception->getMessage()
         *    );
         *
         * 3. Admin-ə Slack bildirişi:
         *    Notification::route('slack', config('services.slack.webhook'))
         *        ->notify(new PaymentFailedNotification($this->orderId, $exception));
         *
         * 4. Kompensasiya əməliyyatı (compensating transaction):
         *    Əgər inventory ayrılmışdısa, onu geri qaytarmaq
         *    ReleaseInventoryJob::dispatch($this->orderId);
         */
    }

    /**
     * Job-un hansı queue-yə göndəriləcəyini təyin edir.
     *
     * Ödəniş kritik olduğu üçün "payments" adlı ayrı queue istifadə edirik.
     * Bu, ödəniş job-larının email job-larından ayrı emal olunmasını təmin edir.
     *
     * Worker-i belə başladırıq:
     * php artisan queue:work --queue=payments
     *
     * Beləliklə ödəniş worker-i yalnız ödəniş job-ları ilə məşğul olur.
     */
    public function queue(): string
    {
        return 'payments';
    }

    /**
     * NÜMUNƏ: Job Chaining — Controller-dən və ya Handler-dən necə istifadə olunur
     *
     * Bu statik helper metod real production kodda olmaz — sadəcə nümunədir.
     * Real istifadə: Controller və ya Handler-dən birbaşa Bus::chain() çağırılır.
     *
     * AXIN:
     * 1. ProcessPaymentJob — ödənişi emal et
     * 2. SendOrderConfirmationJob — uğurlu olsa, email göndər
     * 3. SendPaymentNotificationJob — ödəniş nəticəsini bildir
     *
     * Əgər ödəniş uğursuz olsa → email və notification GÖNDƏRİLMİR.
     * catch() callback-i ilə xəta halını idarə edə bilərsən.
     */
    public static function chainExample(
        string $orderId,
        float $amount,
        string $currency,
        string $paymentMethod,
        string $userEmail,
        array $items,
    ): void {
        Bus::chain([
            new self($orderId, $amount, $currency, $paymentMethod),
            new SendOrderConfirmationJob($orderId, $userEmail, $amount, $items),
            new SendPaymentNotificationJob($orderId, $userEmail, true, $amount),
        ])->catch(function (\Throwable $e) use ($orderId, $userEmail, $amount) {
            // Zəncirdə xəta olsa, uğursuzluq bildirişi göndər
            Log::error("Job zənciri uğursuz: sifariş {$orderId}", [
                'exception' => $e->getMessage(),
            ]);
            SendPaymentNotificationJob::dispatch(
                $orderId, $userEmail, false, $amount, $e->getMessage()
            );
        })->dispatch();
    }
}
