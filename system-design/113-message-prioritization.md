# Message Prioritization (Lead)

## Problem necə yaranır?

FIFO queue: mesajlar gəliş sırasına görə işlənir. Eyni queue-da həm fraud alert həm newsletter varsa — newsletter əvvəl gəlibsə fraud alert gec işlənir. Kritik mesajlar minlərlə analytics job-dan arxada qalır.

```
Queue: [analytics, analytics, analytics, ...(10,000 mesaj)..., FRAUD_ALERT]
Worker: analytics → analytics → ... → FRAUD_ALERT  ← saatlarla gec!
```

---

## Prioritization Strategiyaları

### 1. Multiple Queues (Ən geniş yayılmış)

Hər priority üçün ayrı queue, worker ardıcıllığa görə baxır:

```
php artisan queue:work --queue=critical,high,normal,low
```

Worker `critical` boşalana qədər `high`-a baxmaz. Sadə, etibarlı, Laravel-də out-of-the-box dəstəklənir.

**Problem:** `critical` daim doluysa `low` heç işlənmir — **starvation**.

### 2. RabbitMQ Priority Queue

Broker özü priority-yə görə sıralayır. Queue yaradılarkən `x-max-priority` set edilir (0-10).

*Broker özü priority-yə görə sıralayır. Queue yaradılarkən `x-max-prior üçün kod nümunəsi:*
```php
// Queue x-max-priority: 10 ilə yaradılmalıdır — sonradan dəyişdirilə bilməz!
$this->channel->queue_declare(
    'tasks',
    false, true, false, false,
    new AMQPTable(['x-max-priority' => 10])
);

// Mesaj göndərilərkən priority verilir
$msg = new AMQPMessage(json_encode($payload), [
    'priority'      => 9,  // 0-10, yüksək = vacib
    'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
]);
```

**Məhdudiyyət:** Consumer mesajı aldıqda broker yenidən sort etmir — in-flight mesajlar var olduqda yeni yüksək-priority mesaj gözləyə bilər.

### 3. Weighted Fair Queuing

Her round-da hər queue-dan nisbətli sayda mesaj işlənir. Starvation yoxdur:

```
Critical: 8 job, High: 4 job, Normal: 2 job, Low: 1 job — hər round
Low priority da ən azı işlənir
```

---

## PHP İmplementasiyası

*PHP İmplementasiyası üçün kod nümunəsi:*
```php
// Laravel: Job-lar öz queue-larını bilir
class FraudAlertJob implements ShouldQueue
{
    public string $queue = 'critical';
    // Worker --queue=critical,high,normal,low ilə critical-ı əvvəl oxuyur
}

class NewsletterJob implements ShouldQueue
{
    public string $queue = 'low';
}

// Weighted Fair Queuing — starvation önlənir
class WeightedQueueWorker
{
    private array $weights = [
        'critical' => 8,
        'high'     => 4,
        'normal'   => 2,
        'low'      => 1,
    ];

    public function run(): void
    {
        while (true) {
            $queue = $this->selectQueue();
            $job   = Queue::connection()->pop($queue);

            if ($job) {
                $job->fire();
            } else {
                usleep(100000);
            }
        }
    }

    // Weighted random: critical 8/15 ehtimalla, low 1/15 ehtimalla seçilir
    private function selectQueue(): string
    {
        $total      = array_sum($this->weights);
        $rand       = random_int(1, $total);
        $cumulative = 0;

        foreach ($this->weights as $queue => $weight) {
            $cumulative += $weight;
            if ($rand <= $cumulative) return $queue;
        }

        return 'normal';
    }
}

// Aging — gözləyən mesajların prioriteti artır
class MessageAgingJob implements ShouldQueue
{
    public function handle(): void
    {
        // 10 dəqiqədən çox gözləyən low mesajları normal-a çək
        DB::table('jobs')
            ->where('queue', 'low')
            ->where('created_at', '<', now()->subMinutes(10))
            ->update(['queue' => 'normal']);

        DB::table('jobs')
            ->where('queue', 'normal')
            ->where('created_at', '<', now()->subMinutes(20))
            ->update(['queue' => 'high']);
    }
}
```

---

## Starvation problemi

Multiple queue + strict sequence (`--queue=critical,high,normal,low`) istifadə edildikdə `critical` daim doluysa `low` mesajları heç işlənmir. Həll yolları:

1. **Aging:** Gözləmə müddəti artdıqca priority artır
2. **Weighted fair queuing:** Hər queue-ya faiz verilir
3. **Dedicated workers:** Low priority üçün ayrı worker prosesi ayrılır — critical-dan asılısız işləyir

---

## Anti-patterns

- **Hər şeyi critical queue-ya atmaq:** Priority sistemi mənasını itirir, yenidən FIFO olur.
- **`x-max-priority` çox yüksək qoymaq (100+):** RabbitMQ daha çox RAM istifadə edir, performance düşür. 10 praktik maksimum.
- **Starvation-a etina etməmək:** Low priority job-lar (report generation, cleanup) heç işlənməzsə sistem korlanır.
- **Priority queue-nu `prefetch` olmadan işlətmək:** prefetch yüksəkdirsə broker çoxlu mesajı consumer-a push edir, priority sıralaması pozulur. prefetch=1 priority accuracy-ni artırır.

---

## İntervyu Sualları

**1. Message prioritization niyə lazımdır?**
FIFO queue-da fraud alert minlərlə analytics job-dan sonra işlənir. Kritik mesajların gecikməsi biznes zərəri yaradır. Priority queue resursları vacib işlərə yönləndirir.

**2. Multiple queue vs RabbitMQ priority queue — hansı seçilsin?**
Multiple queue: sadə, Laravel dəstəkli, explicit kontrol. Lakin strict sequence starvation yaradır. RabbitMQ priority queue: broker-level sort, daha dəqiq. Lakin in-flight mesajlar varsa yeni priority mesaj gözləyə bilər. Böyük sistemlər üçün weighted fair queuing daha ədalətli.

**3. Starvation nədir, necə önlənir?**
Yüksək priority mesajlar daim gəlsə aşağı priority mesajlar heç işlənmir. Həll: Aging (gözlədikcə priority artır), weighted fair queuing (hər queue-ya faiz), dedicated workers (low priority-nin öz worker-ı var).

**4. prefetch və priority queue əlaqəsi nədir?**
prefetch yüksəkdirsə broker N mesajı consumer-a push edir — aralarında yüksək priority mesaj gəlsə consumer artıq aldıqlarını işlədər. prefetch=1 ilə consumer hər dəfə bir mesaj alır — broker daima ən yüksək priority-ni verir.

**5. Kafka-da message prioritization necə edilir?**
Kafka built-in priority dəstəkləmir. Alternativlər: 1) Ayrı topic-lər (fraud-events, analytics-events) + consumer hər topic-ə ayrılmış; 2) Yüksək priority topic-ə daha çox consumer assign; 3) Kafka Streams ilə routing. Kafka-nın güclü yeri ordering + throughput — priority bunlarla tradeoff-dadır.

**6. SLA-based prioritization nə deməkdir?**
Müştərinin SLA-sına görə mesajların prioriteti müəyyən edilir. Premium müştərinin sifarişi `high`, free tier müştərinin sifarişi `low`. SLA contract-da "kritik mesajlar 5 saniyə ərzində işlənir" yazıbsa priority queue bunu enforce edir.

---

## Anti-patternlər

**1. Bütün mesajları "critical" priority ilə göndərmək**
Hər iş növünü yüksək priority ilə işarələmək — priority diferensiasiyası itir, sistem faktiki olaraq FIFO-ya qayıdır, kritik mesajlar arasında üstünlük qalmır. Priority-ləri ciddi şəkildə müəyyən edin: yalnız həqiqətən kritik olanlar (fraud alert, ödəniş) yüksək priority alsın.

**2. `x-max-priority` dəyərini çox yüksək (100+) qoymaq**
Priority range genişdir — RabbitMQ hər priority üçün ayrı daxili data strukturu saxlayır, memory istifadəsi artır, throughput aşağı düşür. Praktik maksimum 10 priority səviyyəsidir; əksər sistemlər üçün 3-5 səviyyə (low, normal, high, critical) kifayət edir.

**3. Starvation problemini göz ardı etmək**
Yüksək priority mesajlar daim gəlir, low priority mesajlar (report generation, cleanup) sonsuz gözləyir — sistem korlanır, vacib background iş heç edilmir. Aging mexanizmi tətbiq edin: uzun gözləyən mesajların priority-si tədricən artır; ya da low priority worker-lara minimum faiz ayrılsın.

**4. Priority queue-nu prefetch=0 ilə işlətmək**
Broker bütün mesajları consumer-a push edir — consumer queue-da yüksək priority mesaj gəlsə artıq bufferdəkiləri işlədər, yeni kritik mesaj gec çatdırılır. `prefetch=1` ilə consumer hər dəfə bir mesaj alır; broker həmişə ən yüksək priority-ni öndən verər.

**5. Priority queue-nu monitoring etməmək**
Starvation, priority distribution, queue depth izlənilmir — low priority mesajlar neçə saatdır gözlədiyini bilmirsiniz, kritik mesajların faktiki gecikməsi ölçülmür. Hər priority səviyyəsi üçün ayrı metric qoşun: queue depth, message age, processing rate; anomaliya dərhal alert versin.

**6. Multiple queue strategiyasında worker-ları sabit bölüşdürmək**
High priority queue-ya 8 worker, low priority-yə 2 worker sabit ayrılıb — yüksək trafik zamanı low priority işlər heç işlənmir, high priority az olduqda worker-lar idle dayanır. Dinamik worker skallaması qurun: KEDA ya da supervisor ilə queue dərinliyinə görə worker sayı tənzimlənsin.
