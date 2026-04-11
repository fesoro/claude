# Competing Consumers Pattern

## Pattern nədir?

Eyni queue-dan bir neçə consumer paralel oxuyur. Məqsəd: throughput artırmaq, fault tolerance təmin etmək.

```
// Bu kod competing consumers pattern-inin əsas axınını göstərir
Producer → Queue → [Consumer1, Consumer2, Consumer3]
```

Competing consumers olmadan: 1 worker saniyədə 10 mesaj işləyir, queue-da 10,000 mesaj birikir → backlog böyüyür, latency artır. 10 worker ilə: saniyədə 100 mesaj → backlog idarə olunur.

---

## Necə işləyir?

**RabbitMQ:** Hər consumer `basic_get` və ya `basic_consume` ilə mesaj alır. Broker atomic olaraq bir mesajı yalnız bir consumer-a verir. `prefetch=1` ilə fair dispatch: consumer yalnız bir mesajı işlədikdən sonra növbəti mesajı alır.

```
// Bu kod prefetch dəyərinin fair dispatch-ə təsirini RabbitMQ nümunəsi ilə göstərir
prefetch=100 (default):
  Consumer1: 100 mesaj alır → yavaş işləyir
  Consumer2: 100 mesaj alır → sürətli işləyir, sonra boş dayanır
  → Load imbalance!

prefetch=1:
  Consumer1: 1 mesaj alır → işləyir
  Consumer2: 1 mesaj alır → işləyir
  Birincisi bitən növbəti mesajı alır
  → Fair distribution, sürətli consumer daha çox işləyir ✅
```

**Kafka:** Partition-based — bir partition-u eyni consumer group-dan yalnız 1 consumer oxuyur. Competing consumers üçün: partition sayı ≥ consumer sayı olmalıdır. 3 partition, 5 consumer: 2 consumer boş dayanır.

---

## Əsas Problemlər

**1. Message ordering pozulması**

5 mesaj ardıcıl gəlir: msg1 → msg2 → msg3. Consumer1 msg1-i, Consumer2 msg2-ni alır. Consumer2 msg2-ni əvvəl bitirir → sıra pozulur.

Həll: Ordering lazımdırsa ya 1 consumer, ya da Kafka partition key (eyni müştərinin mesajları eyni partition-a → eyni consumer-a gedir).

**2. Duplicate processing (idempotency)**

Consumer mesajı alır, işləyir, ACK göndərməzdən əvvəl crash olur. Broker mesajı requeue edir, başqa consumer eyni mesajı işləyir → double processing.

Həll: Hər job idempotent olmalıdır. DB-də idempotency key saxla, işlənmiş job-ları skip et.

**3. Poison message (zəhərli mesaj)**

Bir mesaj həmişə exception atır (məs. malformed JSON, mövcud olmayan entity). Consumer işləyir → fail → requeue → başqa consumer işləyir → fail → requeue → sonsuz loop. Queue-u bloklamağa başlayır.

Həll: `max_tries` + Dead Letter Queue (DLQ). 3 cəhddən sonra mesaj DLQ-ya keçir, manual müdaxilə mümkün olur.

**4. Slow consumer problemi**

Consumer yavaş işləyirsə (məs. external API call), mesajlar birikir. prefetch yüksəkdirsə o consumer-da yüzlərlə mesaj "locked" vəziyyətdə dayanır, digər sürətli consumer-lara getmir.

---

## PHP İmplementasiyası

*PHP İmplementasiyası üçün kod nümunəsi:*
```php
// Bu kod supervisor konfiqurasiyasını, idempotent job-ı və Kafka partition routing-i göstərir
// Supervisor ilə 5 parallel worker
// /etc/supervisor/conf.d/laravel-worker.conf
// [program:laravel-worker]
// command=php /var/www/artisan queue:work redis --queue=default --sleep=3 --tries=3
// numprocs=5
// autostart=true
// autorestart=true

// İdempotent job — duplicate processing önlənir
class ProcessOrderJob implements ShouldQueue
{
    public int $tries = 3;

    public function __construct(
        private string $orderId,
        private string $idempotencyKey
    ) {}

    public function handle(): void
    {
        // DB-də yoxla — cache eviction riski olduğundan DB daha etibarlı
        if (ProcessedJob::where('key', $this->idempotencyKey)->exists()) {
            return; // Artıq işlənib, skip et
        }

        DB::transaction(function () {
            $this->processOrder($this->orderId);

            ProcessedJob::create([
                'key'          => $this->idempotencyKey,
                'processed_at' => now(),
            ]);
        });
    }

    // Max retry keçdikdə çağırılır
    public function failed(\Throwable $e): void
    {
        Log::critical('Order job permanently failed', [
            'order_id' => $this->orderId,
            'error'    => $e->getMessage(),
        ]);

        // Ops-a alert + DLQ
        Notification::route('slack', '#alerts')
            ->notify(new JobFailedNotification($this->orderId));
    }
}

// Kafka — consumer group, partition-based ordering
// config/queue.php → kafka driver
// 'queue' => 'orders-topic'
// Eyni user-in mesajları: partition key = user_id
// → eyni partition → eyni consumer → ordering qorunur
```

---

## Anti-patterns

- **prefetch-i çox yüksək qoymaq:** Consumer-lar arasında load imbalance, yavaş consumer digərlərini "starve" edir.
- **Job-ları idempotent etməmək:** Requeue zamanı side effect-lər təkrarlanır (double charge, double email).
- **Poison message-ə qarşı DLQ qoymamaq:** Sonsuz retry loop queue-u tıxayır.
- **Consumer sayını partition sayından çox etmək (Kafka):** Əlavə consumer-lar idle dayanır, resurs israfı.

---

## İntervyu Sualları

**1. Competing consumers nədir, niyə lazımdır?**
Tək consumer queue-u tuta bilmir — throughput kifayət etmir, backlog birikir. Çox consumer paralel işləyərək throughput artırır. RabbitMQ broker-level atomic dispatch təmin edir — eyni mesaj 2 consumer-a getmir.

**2. prefetch=1 nədir, niyə vacibdir?**
prefetch: consumer neçə mesajı eyni anda "reserved" edə bilər. prefetch=100 ilə yavaş consumer 100 mesajı blokda saxlayır. prefetch=1 ilə fair dispatch — hər consumer işini bitirəndə növbəti mesajı alır. Latency-throughput tradeoff: yüksək throughput üçün prefetch artır, lakin fairness azalır.

**3. Kafka-da competing consumers necə işləyir?**
Consumer group: eyni group-dakı consumer-lar partition-ları paylaşır. 6 partition, 3 consumer: hər consumer 2 partition. Consumer əlavə edilsə rebalance — partition-lar yenidən bölünür. Consumer sayı partition sayını keçsə artıq consumer-lar idle dayanır. Ordering: partition daxilində qorunur, partition-lar arasında yoxdur.

**4. Idempotency niyə lazımdır, cache kifayət etmirmi?**
Cache eviction, Redis restart, TTL bitməsi — cache-based idempotency etibarsızdır. DB-dəki `processed_jobs` cədvəli persistent — restart-dan sonra da qorunur. Unique constraint ilə race condition önlənir.

**5. Laravel Horizon nədir, necə kömək edir?**
Redis queue-lar üçün dashboard + supervision. Worker sayını queue yüküne görə auto-scale edir (`balanced` strategy). Failed job-ları izləyir, retry edir. Metrics: throughput, wait time, runtime. `php artisan horizon` ilə işlədir, Supervisor-la yox.

**6. Supervisor-la worker idarəsi necə edilir?**
`numprocs=5` — 5 worker prosesi paralel. `autostart=true`, `autorestart=true` — crash-da yenidən başlar. `stopwaitsecs=60` — graceful shutdown üçün gözləmə vaxtı. Hər queue üçün ayrı `[program:...]` bloku — priority-ə görə worker sayı fərqləndirin.

---

## Anti-patternlər

**1. Consumer sayını Kafka partition sayından çox etmək**
6 partition, 10 consumer — 4 consumer idle dayanır, resurs israfı yaranır, rebalance zamanı latency artır. Consumer sayını partition sayına bərabər ya da az saxlayın; throughput artırmaq lazımdırsa əvvəlcə partition sayını artırın.

**2. Prefetch-i çox yüksək qoymaq (RabbitMQ)**
`prefetch=500` — yavaş consumer 500 mesajı özündə saxlayır, digər consumer-lar boş dayanır, fair dispatch pozulur. `prefetch=1` ilə başlayın; yük testindən sonra optimal dəyəri tapın; throughput ilə fairness arasındakı balansı ölçün.

**3. Poison message üçün DLQ qoymamaq**
Consumer bir mesajı heç vaxt işləyə bilmir (malformed data, bug) — NACK + requeue=true ilə sonsuz retry loop, queue-u tıxayır, digər mesajlar işlənmir. Hər queue üçün DLQ konfiqurasiya edin; max retry keçdikdə mesaj DLQ-ya getsin; DLQ-da biriken mesajlar üçün alert qurun.

**4. Consumer-ları idempotent yazmamaq**
At-least-once delivery semantikasında eyni mesaj iki dəfə çatdırıla bilər — consumer idempotent deyilsə double charge, double email baş verir. Hər consumer-a idempotency tətbiq edin: `processed_jobs` cədvəlinə unikal constraint ilə `job_id` yazın, artıq işlənibsə skip edin.

**5. Consumer-ı uzun sürən iş üçün single thread-də bloklamaq**
Consumer mesajı alır, 5 dəqiqəlik hesablama edir, bu müddət ərzində heç bir mesaj işlənmir — throughput aşağı düşür. Uzun işləri ayrı worker pool-a ya da child process-ə köçürün; consumer mesajı alıb növbəti işə keçsin, ağır iş asenkron icra olunsun.

**6. Rebalance zamanı işlənməkdə olan mesajı itirmək (Kafka)**
Consumer rebalance başlayır, consumer group yenidən qurulur, işlənməkdə olan mesajın offseti commit edilməmişdir — yeni consumer eyni mesajı yenidən işləyir. `enable.auto.commit=false` ilə manual commit istifadə edin; iş tamamlandıqdan sonra offset commit edin; consumer-ı idempotent yazın.
