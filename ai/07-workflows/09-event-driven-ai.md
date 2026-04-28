# Event-Driven AI Workflows: Kafka + Pub/Sub Pattern-ləri (Lead)

> **Kim üçündür:** Senior/Lead developerlər ki, AI işlərini klassik queue-dan çıxarıb event-driven arxitekturaya keçirmək istəyir.
>
> **Əhatə dairəsi:** Kafka əsasları, AI enrichment consumer-lar, dead letter queue, Laravel + Kafka inteqrasiyası, real e-ticarət nümunəsi.

---

## 1. Queue vs Event-Driven — Fərq Nədir?

```
Ənənəvi Queue (Laravel Horizon):
  Producer → [Queue] → Consumer (tək consumer)
  
  ✓ Asan setup
  ✗ Fan-out deyil (bir mesajı bir consumer alır)
  ✗ Replay mümkün deyil
  ✗ Ordering zəmanəti yoxdur

Event-Driven (Kafka):
  Producer → [Topic] → Consumer A (AI enrichment)
                     → Consumer B (Analytics)
                     → Consumer C (Notification)
  
  ✓ Fan-out: bir event birdən çox consumer tərəfindən oxunur
  ✓ Replay: keçmiş eventləri yenidən emal etmək olar
  ✓ Ordering: partition daxilində zəmanətli
  ✓ Audit trail: hər AI qərarı event kimi saxlanır
```

---

## 2. AI üçün Kafka-nın Əsas Üstünlükləri

### 2.1 AI Qərarlarının Audit Trail-i

```
Sual: "AI müştəriyə bu ödəniş limitini niyə rədd etdi?"
Queue: → Cavab yoxdur. Job işlənib, bitib.

Kafka: → "payment.limit.check" topicini replay et
         → Tam input, output, model versiyasını gör
         → Nə vaxt baş verdiyini bil
```

### 2.2 Model Versiyası Dəyişdikdə Replay

```
Ssenari: Yeni model deploy edildi. Keçən həftəki bütün məhsul
         açıqlamalarını yeni model ilə yenidən analiz etmək lazımdır.

Queue: → Mümkün deyil. Köhnə mesajlar yoxdur.
Kafka: → products.created topic-i retention 7 gündür
         → Consumer offset-i 7 gün geri apar
         → Yeni model ilə hər mesajı yenidən emal et
```

### 2.3 AI Enrichment Failure İzolyasiyası

```
Ssenari: AI provider down oldu.

Queue (single consumer):
  → Bütün pipeline bloklanır
  → Sifarişlər emal olunmur

Kafka (separate consumer groups):
  → "orders.ai.enrichment" consumer: retry/DLQ-a gedir
  → "orders.payment" consumer: işlənməyə davam edir
  → "orders.notification" consumer: işlənməyə davam edir
  → AI failure yalnız AI-specific işə təsir edir
```

---

## 3. Laravel + Kafka Qurulumu

### 3.1 Package

```bash
# PHP üçün ən yaxşı Kafka client
composer require junges/laravel-kafka
# və ya
composer require mateusjunges/laravel-kafka
```

### 3.2 Config

```php
<?php
// config/kafka.php
return [
    'brokers'         => env('KAFKA_BROKERS', 'localhost:9092'),
    'security_protocol' => env('KAFKA_SECURITY_PROTOCOL', 'PLAINTEXT'),
    'sasl_mechanisms' => env('KAFKA_SASL_MECHANISMS', null),
    'sasl_username'   => env('KAFKA_SASL_USERNAME', null),
    'sasl_password'   => env('KAFKA_SASL_PASSWORD', null),
    
    'topics' => [
        'products.created'    => 'products.created',
        'orders.created'      => 'orders.created',
        'ai.enrichment.done'  => 'ai.enrichment.done',
        'ai.enrichment.dlq'   => 'ai.enrichment.dlq',
    ],
];
```

---

## 4. Real Nümunə: E-ticarət AI Enrichment Pipeline

### Ssenario:
- Yeni məhsul yaradıldı
- AI: qiymət kateqoriyası, sentiment analizi, önerə bilən rəqiblər, uğur ehtimalı
- Bütün bu enrichment-lər asinxron, müstəqil olmalıdır

### 4.1 Event Publish

```php
<?php
// app/Listeners/PublishProductCreatedEvent.php

namespace App\Listeners;

use App\Events\ProductCreated;
use Junges\Kafka\Facades\Kafka;

class PublishProductCreatedEvent
{
    public function handle(ProductCreated $event): void
    {
        $product = $event->product;

        Kafka::publishOn(config('kafka.topics.products.created'))
            ->withBodyKey('product_id', $product->id)
            ->withBodyKey('name', $product->name)
            ->withBodyKey('description', $product->description)
            ->withBodyKey('price', $product->price)
            ->withBodyKey('category', $product->category)
            ->withBodyKey('seller_id', $product->seller_id)
            ->withHeaders([
                'event_type'    => 'product.created',
                'event_id'      => (string) \Str::uuid(),
                'published_at'  => now()->toIso8601String(),
                'source_system' => 'marketplace-api',
            ])
            ->withPartitionKey((string) $product->seller_id) // Eyni seller → eyni partition → sıralama
            ->send();
    }
}
```

### 4.2 AI Enrichment Consumer

```php
<?php
// app/Kafka/Consumers/ProductAIEnrichmentConsumer.php

namespace App\Kafka\Consumers;

use App\Services\AI\ProductAnalysisService;
use App\Models\Product;
use App\Models\AiEnrichment;
use Junges\Kafka\Contracts\KafkaConsumerMessage;

class ProductAIEnrichmentConsumer
{
    public function __construct(
        private readonly ProductAnalysisService $analysisService,
    ) {}

    public function handle(KafkaConsumerMessage $message): void
    {
        $body      = $message->getBody();
        $productId = $body['product_id'] ?? null;

        if (!$productId) {
            // Malformed message — DLQ-a göndər
            throw new \InvalidArgumentException("product_id yoxdur");
        }

        $product = Product::find($productId);
        if (!$product) {
            // Əgər product artıq silinibsə — silent skip
            return;
        }

        // AI enrichment (bu çağırış 2-5s çəkə bilər)
        $enrichment = $this->analysisService->analyze($product);

        // Nəticəni saxla
        AiEnrichment::updateOrCreate(
            ['product_id' => $productId],
            [
                'price_category'    => $enrichment['price_category'],    // 'budget' | 'mid' | 'premium'
                'quality_score'     => $enrichment['quality_score'],     // 0-100
                'sentiment'         => $enrichment['sentiment'],         // 'positive' | 'neutral' | 'negative'
                'competitor_count'  => $enrichment['competitor_count'],
                'success_probability' => $enrichment['success_probability'], // 0.0-1.0
                'model_version'     => $enrichment['model'],
                'enriched_at'       => now(),
            ],
        );

        // Downstream event publish et
        Kafka::publishOn(config('kafka.topics.ai.enrichment.done'))
            ->withBodyKey('product_id', $productId)
            ->withBodyKey('enrichment', $enrichment)
            ->send();
    }
}
```

```php
<?php
// app/Services/AI/ProductAnalysisService.php

namespace App\Services\AI;

use App\Models\Product;

class ProductAnalysisService
{
    public function __construct(
        private readonly ClaudeService $claude,
    ) {}

    public function analyze(Product $product): array
    {
        $prompt = <<<PROMPT
        Məhsul məlumatları əsasında aşağıdakı analizi et:

        Ad: {$product->name}
        Açıqlama: {$product->description}
        Qiymət: {$product->price} AZN
        Kateqoriya: {$product->category}

        JSON formatında cavab ver:
        {
          "price_category": "budget|mid|premium",
          "quality_score": 0-100,
          "sentiment": "positive|neutral|negative",
          "competitor_count": 0-100,
          "success_probability": 0.0-1.0,
          "reasoning": "qısa izah"
        }
        PROMPT;

        $response = $this->claude->messages(
            messages: [['role' => 'user', 'content' => $prompt]],
            model: 'claude-haiku-4-5',
            temperature: 0.0,
        );

        $data  = json_decode($response, true);
        $data['model'] = 'claude-haiku-4-5';

        return $data;
    }
}
```

### 4.3 Consumer Registration

```php
<?php
// app/Console/Commands/ConsumeProductEnrichment.php

namespace App\Console\Commands;

use App\Kafka\Consumers\ProductAIEnrichmentConsumer;
use Illuminate\Console\Command;
use Junges\Kafka\Facades\Kafka;

class ConsumeProductEnrichment extends Command
{
    protected $signature   = 'kafka:consume:product-enrichment';
    protected $description = 'AI enrichment consumer for products';

    public function handle(): void
    {
        Kafka::createConsumer()
            ->subscribe(config('kafka.topics.products.created'))
            ->withOptions([
                'group.id'           => 'product-ai-enrichment',
                'auto.offset.reset'  => 'earliest',
                'max.poll.interval.ms' => 300000,  // 5 dəq — AI çağırışı uzun çəkə bilər
                'session.timeout.ms' => 30000,
                'enable.auto.commit' => false,     // Manual commit — at-most-once deyil
            ])
            ->withHandler(new ProductAIEnrichmentConsumer(
                app(ProductAnalysisService::class)
            ))
            ->withMaxRetries(retries: 3)
            ->withDeadLetterQueue(
                topicName: config('kafka.topics.ai.enrichment.dlq'),
                producer: Kafka::publishOn(config('kafka.topics.ai.enrichment.dlq'))
            )
            ->build()
            ->consume();
    }
}
```

---

## 5. Dead Letter Queue (DLQ) İdarəsi

```php
<?php
// app/Kafka/Consumers/AIEnrichmentDLQConsumer.php

namespace App\Kafka\Consumers;

use App\Models\FailedAiEnrichment;
use Junges\Kafka\Contracts\KafkaConsumerMessage;

class AIEnrichmentDLQConsumer
{
    public function handle(KafkaConsumerMessage $message): void
    {
        $body    = $message->getBody();
        $headers = $message->getHeaders();

        // DLQ mesajını log et + notify
        FailedAiEnrichment::create([
            'product_id'     => $body['product_id'] ?? null,
            'error_message'  => $headers['kafka_error'] ?? 'unknown',
            'retry_count'    => $headers['kafka_retry_count'] ?? 0,
            'original_event' => json_encode($body),
            'failed_at'      => now(),
        ]);

        // Slack/Email alert
        \Log::channel('slack')->error("AI Enrichment DLQ", [
            'product_id' => $body['product_id'] ?? 'unknown',
            'error'      => $headers['kafka_error'] ?? 'unknown',
        ]);
    }
}
```

### DLQ Replay (Manual)

```php
<?php
// app/Console/Commands/ReplayDLQ.php

class ReplayDLQ extends Command
{
    protected $signature = 'kafka:dlq:replay {--product_id=} {--limit=100}';

    public function handle(): void
    {
        $query = FailedAiEnrichment::query()
            ->where('resolved', false)
            ->limit($this->option('limit'));

        if ($productId = $this->option('product_id')) {
            $query->where('product_id', $productId);
        }

        $failed = $query->get();

        foreach ($failed as $item) {
            // Orijinal event-i yenidən products.created topic-ə göndər
            Kafka::publishOn(config('kafka.topics.products.created'))
                ->withBody(json_decode($item->original_event, true))
                ->withHeaders(['replay' => 'true', 'replay_at' => now()->toIso8601String()])
                ->send();

            $item->update(['resolved' => true, 'resolved_at' => now()]);
        }

        $this->info("Replayed {$failed->count()} messages");
    }
}
```

---

## 6. Consumer Group Strategiyası

```
Topic: products.created
         │
         ├── Consumer Group: "product-ai-enrichment"
         │     (AI əsas enrichment)
         │
         ├── Consumer Group: "product-search-indexer"
         │     (Elasticsearch indexing)
         │
         ├── Consumer Group: "product-analytics"
         │     (BigQuery pipeline)
         │
         └── Consumer Group: "product-recommendations"
               (Recommendation engine update)

Hər consumer group müstəqil offset saxlayır.
AI consumer down olsa, analytics consumer işlənməyə davam edir.
```

---

## 7. Sıralama Zəmanəti

```php
// Əgər bir seller-in məhsulları sıralı emal olunmalıdırsa:
Kafka::publishOn('products.created')
    ->withPartitionKey((string) $product->seller_id) // Eyni seller → eyni partition
    ->send();

// Əgər sıralama vacib deyilsə:
Kafka::publishOn('products.created')
    ->send(); // Random partition — yüksək parallelism
```

---

## 8. Kafka vs Laravel Horizon: Nə Vaxt Nə Seçmək

| | Laravel Horizon | Kafka |
|--|-----------------|-------|
| **Setup** | 5 dəqiqə | Saatlar (cluster setup) |
| **Fan-out** | Dəstəkləmir | Əsas xüsusiyyət |
| **Replay** | Yoxdur | 7-30 gün retention |
| **Ordering** | Yoxdur | Partition daxilində |
| **Audit trail** | Manual | Avtomatik |
| **Scale** | 10-100 job/s | Milyonlarla event/s |
| **Monitoring** | Horizon Dashboard | Kafka UI, Grafana |
| **Nə vaxt** | Çox task üçün | Event-driven, fan-out, replay lazım olduqda |

---

## 9. Anti-Pattern-lər

### Hər Şeyi Kafka-ya Köçürmək

```
Kafka overhead böyükdür: broker cluster, offset management, consumer group.
Əgər Laravel Horizon işinizdə işləyirsə, köçürməyin.
Kafka yalnız bunlar lazım olduqda: fan-out, replay, ordering, audit trail.
```

### Böyük Payload-lar

```
Kafka: küçük mesajlar üçün nəzərdə tutulub (KB, deyil MB)
Məhsul şəklini birbaşa Kafka mesajına qoyma:
  → Storage-a yaz → Kafka-ya yalnız URL göndər

// YANLIŞ
->withBodyKey('image_base64', base64_encode($imageBytes))  // 5MB mesaj

// DOĞRU
$imageUrl = Storage::url($path);
->withBodyKey('image_url', $imageUrl)
```

### AI Timeout-u Consumer-ı Bloklamasın

```php
// max.poll.interval.ms = 300000 (5 dəqiqə) set edin
// AI çağırışı 30s çəkirsə → consumer timeout → rebalance → mesaj yenidən emal olunur
// Uzun AI job-ları üçün ayrı worker pool istifadə edin
```

---

## Praktik Tapşırıqlar

### Tapşırıq 1: Kafka Consumer + AI

`new_order` Kafka topic-inə subscribe olan `OrderClassificationConsumer` implement et. Hər mesaj üçün Claude-a `classify_order_priority(order)` call et. Nəticəni `order_classifications` cədvəlinə yaz. Consumer `max.poll.interval.ms` timeout-u keçmədən AI call-ı tamamlamağı test et.

### Tapşırıq 2: Event-Driven Embedding

`DocumentUploadedEvent` Laravel event-ini dispatch et. `GenerateEmbeddingListener`-ı bu event-ə qoş. Listener: sənədi embed edib pgvector-ə yazar. Həmçinin `EmbeddingGeneratedEvent` dispatch edir. `SearchIndexListener` bu event-ə subscribe edib search index-i güncəlləyir. Zəncir test et.

### Tapşırıq 3: Dead Letter Queue

Consumer exception atdıqda (API error, invalid data) mesajı Dead Letter Queue-ya (DLQ) göndər. DLQ-da toplanan mesajları admin paneldən əl ilə retry et ya da discard et. 10 mesaj üçün DLQ flow-u test et. DLQ ölçüsü kritik həddə çatdıqda alert göndər.

---

## Əlaqəli Mövzular

- [03-laravel-queue-ai-patterns.md](03-laravel-queue-ai-patterns.md) — Horizon + AI job patterns
- [04-ai-idempotency-circuit-breaker.md](04-ai-idempotency-circuit-breaker.md) — Idempotency (Kafka ilə vacibdir)
- [05-webhook-async-ai.md](05-webhook-async-ai.md) — Webhook-driven AI patterns
- [../08-production/02-observability-logging.md](../08-production/02-observability-logging.md) — Consumer monitoring
