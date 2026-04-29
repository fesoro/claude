# Anthropic Message Batches API (Senior)

## Batches API Nədir?

Message Batches API, Anthropic-in asinxron toplu emal endpointidir. Bir sorğu göndərib bir cavab gözləmək əvəzinə, **tək bir batch-da 100.000-ə qədər sorğu** təqdim edirsiniz, Anthropic GPU tutumu mövcud olanda onları emal edir (adətən 24 saat ərzində) və batch tamamlandıqda bütün nəticələri alırsınız.

Əsas kompromis:

| | Standart API | Batches API |
|---|---|---|
| Gecikmə | Saniyələr | Dəqiqələrdən saatlara |
| Xərc | Tam qiymət | **50% ucuz** |
| Sürət limitləri | Dəqiqəlik token limitləri | Daha yüksək ötürücülük |
| İstifadə halı | Real vaxtlı istifadəçi qarşılıqlı əlaqəsi | Oflayn toplu emal |

50% endirim, Batches API-ni dərhal nəticə tələb etməyən hər hansı iş yükü üçün düzgün seçim edir.

---

## Arxitektura və Protokol

### Batch Yaratmaq

`POST /v1/messages/batches`

```json
{
  "requests": [
    {
      "custom_id": "product-123",
      "params": {
        "model": "claude-opus-4-7",
        "max_tokens": 1024,
        "messages": [
          {
            "role": "user",
            "content": "SEO meta təsviri yazın: Blue Widget Pro 3000"
          }
        ]
      }
    },
    {
      "custom_id": "product-124",
      "params": { ... }
    }
  ]
}
```

Hər sorğunun:
- `custom_id` — sizin identifikatorunuz (maks 64 simvol), nəticələri girişlərə uyğunlaşdırmaq üçün istifadə olunur. Batch daxilinde unikal olmalıdır.
- `params` — standart Messages API sorğu gövdəsi (`stream` istisna olmaqla).

### Batch Həyat Dövrü

```
created → in_progress → ended
                     ↘ errored (batch səviyyəsindəki xəta)
```

Vəziyyətlər:
- `created` — qəbul edildi, hələ başlanmayıb.
- `in_progress` — aktiv şəkildə emal edilir.
- `ended` — bütün sorğular ya uğurla tamamlandı, ya da uğursuz oldu. Nəticələri alın.
- `errored` — bütün batch uğursuz oldu (nadir hal, infrastruktur xətası).

### Tamamlanmanı Sorğulamaq

`GET /v1/messages/batches/{batch_id}`

Qaytarır:
```json
{
  "id": "msgbatch_01HkcTjaV5uDC8jWR4ZsDV8d",
  "type": "message_batch",
  "processing_status": "in_progress",
  "request_counts": {
    "processing": 8432,
    "succeeded": 1500,
    "errored": 12,
    "canceled": 0,
    "expired": 0
  },
  "ended_at": null,
  "created_at": "2025-04-11T10:00:00Z",
  "expires_at": "2025-04-12T10:00:00Z",
  "cancel_initiated_at": null,
  "results_url": null
}
```

`processing_status` `ended` olduqda, `results_url` nəticələri yükləmək üçün URL ehtiva edir.

### Nəticələri Almaq

`GET /v1/messages/batches/{batch_id}/results`

**Yeni sətir ilə ayrılmış JSON** (JSONL) qaytarır — hər sətirdə bir nəticə:

```json
{"custom_id":"product-123","result":{"type":"succeeded","message":{"id":"msg_...","content":[{"type":"text","text":"Blue Widget Pro 3000-i kəşf edin..."}],"usage":{...}}}}
{"custom_id":"product-124","result":{"type":"errored","error":{"type":"invalid_request_error","message":"max_tokens çox yüksəkdir"}}}
```

Nəticə tipləri:
- `succeeded` — normal cavab, `result.message` standart Messages cavabıdır.
- `errored` — sorğu səviyyəsindəki xəta, `result.error` problemi təsvir edir.
- `expired` — bu sorğu emal edilməzdən əvvəl batch vaxtı bitdi (29 günlük pəncərə).
- `canceled` — `POST /v1/messages/batches/{id}/cancel` çağırdınız.

---

## Limitlər və Məhdudiyyətlər

| Məhdudiyyət | Dəyər |
|---|---|
| Batch başına maks sorğu | 100.000 |
| Maks batch ölçüsü (payload) | 256 MB |
| Batch vaxtının bitmesi (tamamlanmasa) | 29 gün |
| Tamamlanmadan sonra nəticələrin saxlanması | 29 gün |
| Eyni vaxtda batch sayı | Sənədlənmiş limit yoxdur |
| `custom_id` maks uzunluğu | 64 simvol |
| `custom_id` icazə verilən simvollar | Rəqəm-hərf, `-`, `_` |

---

## Laravel İmplementasiyası

### 1. BatchProcessor Xidməti

```php
<?php

declare(strict_types=1);

namespace App\Services\AI;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\LazyCollection;
use RuntimeException;

/**
 * Anthropic Message Batches API-ni bürüyür.
 *
 * Batch yaratma, vəziyyət sorğulama, nəticə yayımlama
 * və sorğu başına xəta izolyasiyasını idarə edir.
 */
final class BatchProcessor
{
    private readonly Client $http;

    public function __construct(
        private readonly string $model = 'claude-opus-4-7',
    ) {
        $this->http = new Client([
            'base_uri' => 'https://api.anthropic.com/v1/',
            'headers'  => [
                'x-api-key'         => config('services.anthropic.key'),
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ],
            'timeout'  => 30,
        ]);
    }

    /**
     * Sorğular batch-i göndər.
     *
     * @param  array<int, BatchRequest>  $requests
     * @return string  Batch ID
     *
     * @throws RuntimeException
     */
    public function createBatch(array $requests): string
    {
        if (count($requests) > 100_000) {
            throw new \InvalidArgumentException('Batch 100.000 sorğunu aşa bilməz');
        }

        $payload = [
            'requests' => array_map(
                fn (BatchRequest $r) => $r->toArray($this->model),
                $requests,
            ),
        ];

        try {
            $response = $this->http->post('messages/batches', ['json' => $payload]);
        } catch (RequestException $e) {
            $body = $e->getResponse()?->getBody()->getContents() ?? '';
            throw new RuntimeException("Batch yaratma uğursuz oldu: {$body}", previous: $e);
        }

        $data = json_decode($response->getBody()->getContents(), true, flags: JSON_THROW_ON_ERROR);

        return $data['id'];
    }

    /**
     * Batch vəziyyətini sorğula.
     *
     * @return array{status: string, counts: array<string, int>, results_url: string|null}
     */
    public function getStatus(string $batchId): array
    {
        $response = $this->http->get("messages/batches/{$batchId}");
        $data     = json_decode($response->getBody()->getContents(), true, flags: JSON_THROW_ON_ERROR);

        return [
            'status'      => $data['processing_status'],
            'counts'      => $data['request_counts'],
            'results_url' => $data['results_url'],
            'ended_at'    => $data['ended_at'],
            'expires_at'  => $data['expires_at'],
        ];
    }

    /**
     * Sorğulayaraq batch tamamlanmasını gözlə.
     * Vəziyyət 'ended' olduqda qaytarır.
     *
     * @throws RuntimeException batch xəta verdikdə və ya timeout aşıldıqda
     */
    public function waitForCompletion(
        string $batchId,
        int $pollIntervalSeconds = 30,
        int $timeoutSeconds = 86400,
    ): array {
        $startTime = time();

        while (true) {
            $status = $this->getStatus($batchId);

            if ($status['status'] === 'ended') {
                return $status;
            }

            if ($status['status'] === 'errored') {
                throw new RuntimeException("Batch {$batchId} infrastruktur səviyyəsindəki xəta verdi");
            }

            if ((time() - $startTime) > $timeoutSeconds) {
                throw new RuntimeException("Batch {$batchId} sorğulaması {$timeoutSeconds}s-dan sonra vaxtı bitdi");
            }

            sleep($pollIntervalSeconds);
        }
    }

    /**
     * 100k nəticəni yaddaşa yükləməmək üçün LazyCollection kimi nəticələri yayımla.
     *
     * @return LazyCollection<int, BatchResult>
     */
    public function streamResults(string $batchId): LazyCollection
    {
        return LazyCollection::make(function () use ($batchId) {
            $response = $this->http->get(
                "messages/batches/{$batchId}/results",
                ['stream' => true],
            );

            $body   = $response->getBody();
            $buffer = '';

            while (! $body->eof()) {
                $buffer .= $body->read(4096);

                while (($pos = strpos($buffer, "\n")) !== false) {
                    $line   = substr($buffer, 0, $pos);
                    $buffer = substr($buffer, $pos + 1);

                    if (trim($line) === '') {
                        continue;
                    }

                    $data = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
                    yield BatchResult::fromArray($data);
                }
            }

            // Arxada yeni sətir olmayan məzmunu idarə et
            if (trim($buffer) !== '') {
                $data = json_decode($buffer, true, flags: JSON_THROW_ON_ERROR);
                yield BatchResult::fromArray($data);
            }
        });
    }

    /**
     * İşləyən batch-i ləğv et.
     */
    public function cancel(string $batchId): void
    {
        $this->http->post("messages/batches/{$batchId}/cancel");
    }
}
```

### Köməkçi Dəyər Obyektləri

```php
<?php

declare(strict_types=1);

namespace App\Services\AI;

/**
 * Batch daxilindəki tək sorğunu təmsil edir.
 */
final readonly class BatchRequest
{
    public function __construct(
        public readonly string $customId,
        public readonly string $userMessage,
        public readonly int    $maxTokens   = 1024,
        public readonly string $systemPrompt = '',
        public readonly array  $extraParams  = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(string $model): array
    {
        $params = array_merge([
            'model'      => $model,
            'max_tokens' => $this->maxTokens,
            'messages'   => [
                ['role' => 'user', 'content' => $this->userMessage],
            ],
        ], $this->extraParams);

        if ($this->systemPrompt !== '') {
            $params['system'] = $this->systemPrompt;
        }

        return [
            'custom_id' => $this->customId,
            'params'    => $params,
        ];
    }
}

/**
 * Tamamlanmış batch-dən tək nəticəni təmsil edir.
 */
final readonly class BatchResult
{
    public function __construct(
        public readonly string  $customId,
        public readonly string  $resultType, // succeeded | errored | expired | canceled
        public readonly ?string $text,
        public readonly ?array  $error,
        public readonly ?array  $usage,
    ) {}

    public static function fromArray(array $data): self
    {
        $result = $data['result'];
        $type   = $result['type'];

        return new self(
            customId:   $data['custom_id'],
            resultType: $type,
            text:       $type === 'succeeded'
                ? self::extractText($result['message'])
                : null,
            error:      $result['error'] ?? null,
            usage:      $type === 'succeeded'
                ? ($result['message']['usage'] ?? null)
                : null,
        );
    }

    public function succeeded(): bool
    {
        return $this->resultType === 'succeeded';
    }

    public function failed(): bool
    {
        return in_array($this->resultType, ['errored', 'expired', 'canceled'], true);
    }

    private static function extractText(array $message): string
    {
        foreach ($message['content'] ?? [] as $block) {
            if ($block['type'] === 'text') {
                return $block['text'];
            }
        }
        return '';
    }
}
```

### 2. Laravel Queue Job — Batch Orkestrası

```php
<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\AI\BatchProcessor;
use App\Services\AI\BatchRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Anthropic batch işini orkestr edir.
 *
 * Faza 1: Batch yaradır və batch ID-sini saxlayır.
 * Faza 2: Ayrı sorğulama işi vəziyyəti yoxlayır və nəticələri emal edir.
 *
 * Niyə iki iş? Birinci iş sürətli işləyir (batch yaratma = bir API çağırışı).
 * İkincisi dəqiqələr/saatlar ərzindən sorğulayır — sleep() ilə işçini
 * bloklamaq əvəzinə özünü gecikmə ilə yenidən növbəyə qoyur.
 */
final class ProcessAiBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 60;
    public int $tries   = 3;

    /**
     * @param  array<int, array{id: int, prompt: string}>  $items
     * @param  string  $batchableType  məs. 'product_descriptions'
     */
    public function __construct(
        private readonly array  $items,
        private readonly string $batchableType,
        private readonly string $callbackJob,
    ) {}

    public function handle(BatchProcessor $processor): void
    {
        $requests = array_map(
            fn (array $item) => new BatchRequest(
                customId:     "item-{$item['id']}",
                userMessage:  $item['prompt'],
                maxTokens:    512,
                systemPrompt: $this->getSystemPrompt(),
            ),
            $this->items,
        );

        $batchId = $processor->createBatch($requests);

        Log::info("AI batch yaradıldı", [
            'batch_id'  => $batchId,
            'type'      => $this->batchableType,
            'count'     => count($requests),
        ]);

        // İlkin gecikmə ilə sorğulama işinə ötür
        PollAiBatchJob::dispatch(
            batchId:      $batchId,
            batchableType: $this->batchableType,
            callbackJob:   $this->callbackJob,
            itemIds:       array_column($this->items, 'id'),
        )->delay(now()->addMinutes(5));
    }

    private function getSystemPrompt(): string
    {
        return match ($this->batchableType) {
            'product_descriptions' => 'Siz SEO kopirayterisiniz. Cəlbedici, açar söz zəngin meta təsvirlər yazın.',
            'content_moderation'   => 'Siz məzmun moderatorsunuz. Məzmunu təhlükəsiz/təhlükəsiz kimi təsnif edin.',
            default                => '',
        };
    }
}
```

```php
<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\AI\BatchProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Aktiv Anthropic batch-i tamamlanana qədər sorğulayır, sonra
 * callback işi nəticələrlə dispatch edir.
 *
 * Özünü-yenidən-növbəyə-qoyma nümunəsi: dövrədə yatmaq əvəzinə, iş
 * bir dəfə yoxlayır və hazır deyilsə gecikmə ilə özünü yenidən növbəyə qoyur.
 * Bu, sorğular arasında işçini azad edir.
 */
final class PollAiBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 30;
    public int $tries   = 500; // ~24 saatlıq sorğulamanı əhatə edir

    public function __construct(
        private readonly string $batchId,
        private readonly string $batchableType,
        private readonly string $callbackJob,
        private readonly array  $itemIds,
        private readonly int    $pollCount = 0,
    ) {}

    public function handle(BatchProcessor $processor): void
    {
        $status = $processor->getStatus($this->batchId);

        Log::info("Batch {$this->batchId} sorğulanır", [
            'status'     => $status['status'],
            'counts'     => $status['counts'],
            'poll_count' => $this->pollCount,
        ]);

        if ($status['status'] === 'errored') {
            Log::error("Batch {$this->batchId} infrastruktur səviyyəsindəki xəta verdi");
            return;
        }

        if ($status['status'] !== 'ended') {
            // Adaptiv geri çəkilmə: 30s-dan başlayır, 5 dəqiqəyə qədər böyüyür
            $delaySeconds = min(300, 30 * (1 + intdiv($this->pollCount, 10)));

            self::dispatch(
                batchId:       $this->batchId,
                batchableType: $this->batchableType,
                callbackJob:   $this->callbackJob,
                itemIds:       $this->itemIds,
                pollCount:     $this->pollCount + 1,
            )->delay(now()->addSeconds($delaySeconds));

            return;
        }

        // Batch tamamlandı — nəticə emal işini dispatch et
        ($this->callbackJob)::dispatch(
            batchId: $this->batchId,
            itemIds: $this->itemIds,
        );
    }
}
```

### 3. Real İstifadə Halı — 10.000 Məhsul SEO Təsviri

```php
<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Jobs\ProcessAiBatchJob;
use App\Jobs\StoreSeoResultsJob;
use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * Batches API istifadə edərək bütün məhsullar üçün SEO meta təsvirlər yaradır.
 *
 * 10.000 məhsul üçün xərc hesablaması:
 * - Ortalama prompt: ~100 giriş tokeni, ~80 çıxış tokeni
 * - Batch olmadan: 10.000 × ($3/MTok × 0.0001) = məhsul başına $0,003 = $30 ümumi giriş
 * - Batch ilə (50% endirim): $15 ümumi giriş
 * - Çıxış xərci: 10.000 × 80 token × $15/MTok = $12
 * - Batch ilə ümumi: ~$27 vs ~$42 (bu işdə 35% qənaət)
 */
final class ProductSeoBatchService
{
    private const BATCH_SIZE = 10_000; // Batch göndərmə başına maks element

    public function __construct(
        private readonly BatchProcessor $processor,
    ) {}

    /**
     * SEO təsviri olmayan bütün məhsullar üçün batch emal növbəyə qoy.
     */
    public function queueMissingDescriptions(): int
    {
        $products = Product::query()
            ->whereNull('seo_meta_description')
            ->orWhere('seo_meta_description', '')
            ->select(['id', 'name', 'category', 'short_description', 'price'])
            ->cursor();

        $count  = 0;
        $chunk  = [];

        foreach ($products as $product) {
            $chunk[] = [
                'id'     => $product->id,
                'prompt' => $this->buildPrompt($product),
            ];
            $count++;

            if (count($chunk) >= self::BATCH_SIZE) {
                $this->dispatchChunk($chunk);
                $chunk = [];
            }
        }

        if ($chunk !== []) {
            $this->dispatchChunk($chunk);
        }

        return $count;
    }

    /**
     * Artisan əmri və ya controllerdən çağırılır.
     * İzləmə üçün batch ID qaytarır.
     */
    public function createBatchNow(Collection $products): string
    {
        $requests = $products->map(fn (Product $p) => new BatchRequest(
            customId:     "product-{$p->id}",
            userMessage:  $this->buildPrompt($p),
            maxTokens:    160, // Meta təsvirlər qısadır
            systemPrompt: $this->systemPrompt(),
        ))->all();

        return $this->processor->createBatch($requests);
    }

    private function buildPrompt(mixed $product): string
    {
        return <<<PROMPT
        Məhsul: {$product->name}
        Kateqoriya: {$product->category}
        Təsvir: {$product->short_description}
        Qiymət: \${$product->price}

        Məhsul adını, əsas bir faydanı və incə çağırış-hərəkəti ehtiva edən cəlbedici SEO meta təsviri (150-160 simvol) yazın.
        "yoxlayın" və ya "əla məhsul" kimi ümumi ifadələrdən istifadə etməyin.
        Yalnız meta təsvir mətnini qaytarın, dırnaq işarəsi və ya etiket olmadan.
        PROMPT;
    }

    private function systemPrompt(): string
    {
        return 'Siz ekspert e-ticarət SEO kopirayterisiniz. '
             . 'Spesifik, fayda yönümlü və klik nisbəti üçün optimallaşdırılmış meta təsvirlər yazın. '
             . 'Həmişə 155-160 simvol daxilinde qalın.';
    }

    private function dispatchChunk(array $chunk): void
    {
        ProcessAiBatchJob::dispatch(
            items:         $chunk,
            batchableType: 'product_descriptions',
            callbackJob:   StoreSeoResultsJob::class,
        )->onQueue('ai-batch');
    }
}
```

### 4. Nəticə Saxlama və Sorğu Başına Xəta İdarəetməsi

```php
<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Product;
use App\Models\AiBatchLog;
use App\Services\AI\BatchProcessor;
use App\Services\AI\BatchResult;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Tamamlanmış batch nəticələrini emal edir və verilənlər bazasına saxlayır.
 *
 * Effektivlik üçün chunk-laşdırılmış upsert istifadə edir — N+1 sorğu yoxdur.
 * Bütün işi uğursuz etmədən hər sorğu xətasını ayrı-ayrılıqda idarə edir.
 */
final class StoreSeoResultsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 3600; // Böyük batch-lər stream etmək üçün vaxt tələb edə bilər
    public int $tries   = 3;

    public function __construct(
        private readonly string $batchId,
        private readonly array  $itemIds,
    ) {}

    public function handle(BatchProcessor $processor): void
    {
        $stats = [
            'total'    => 0,
            'success'  => 0,
            'failed'   => 0,
            'skipped'  => 0,
        ];

        $updateBuffer = [];
        $failureLog   = [];

        foreach ($processor->streamResults($this->batchId) as $result) {
            /** @var BatchResult $result */
            $stats['total']++;

            if ($result->succeeded()) {
                $productId = $this->extractProductId($result->customId);

                if ($productId !== null) {
                    $updateBuffer[$productId] = $result->text;
                    $stats['success']++;
                }

                // Yaddaş yığımının qarşısını almaq üçün 500-lük batch-lərlə flush et
                if (count($updateBuffer) >= 500) {
                    $this->flushUpdates($updateBuffer);
                    $updateBuffer = [];
                }
            } else {
                $stats['failed']++;
                $failureLog[] = [
                    'custom_id'   => $result->customId,
                    'result_type' => $result->resultType,
                    'error'       => $result->error,
                ];

                Log::warning("Batch nəticəsi uğursuz oldu", [
                    'batch_id'  => $this->batchId,
                    'custom_id' => $result->customId,
                    'type'      => $result->resultType,
                    'error'     => $result->error,
                ]);
            }
        }

        // Son flush
        if ($updateBuffer !== []) {
            $this->flushUpdates($updateBuffer);
        }

        // Batch audit logu saxla
        AiBatchLog::create([
            'batch_id'       => $this->batchId,
            'batch_type'     => 'product_seo',
            'total_requests' => $stats['total'],
            'succeeded'      => $stats['success'],
            'failed'         => $stats['failed'],
            'failures'       => json_encode($failureLog),
            'completed_at'   => now(),
        ]);

        Log::info("Batch {$this->batchId} saxlandı", $stats);

        // Xəta varsa yeni batch vasitəsilə yenidən cəhd et
        if ($stats['failed'] > 0 && $stats['failed'] < 100) {
            $this->scheduleRetryForFailures($failureLog);
        }
    }

    /**
     * Effektivlik üçün upsert istifadə edərək məhsulları toplu yenilə.
     *
     * @param  array<int, string>  $updates  productId => metaDescription
     */
    private function flushUpdates(array $updates): void
    {
        $rows = array_map(
            fn (int $id, string $desc) => ['id' => $id, 'seo_meta_description' => $desc, 'updated_at' => now()],
            array_keys($updates),
            array_values($updates),
        );

        DB::table('products')->upsert(
            $rows,
            uniqueBy: ['id'],
            update:   ['seo_meta_description', 'updated_at'],
        );
    }

    private function extractProductId(string $customId): ?int
    {
        // custom_id formatı: "product-{id}"
        if (preg_match('/^product-(\d+)$/', $customId, $m)) {
            return (int) $m[1];
        }
        return null;
    }

    private function scheduleRetryForFailures(array $failures): void
    {
        $productIds = array_filter(array_map(
            fn (array $f) => $this->extractProductId($f['custom_id']),
            $failures,
        ));

        if ($productIds === []) {
            return;
        }

        $products = Product::whereIn('id', $productIds)
            ->select(['id', 'name', 'category', 'short_description', 'price'])
            ->get();

        // Gecikmə ilə daha kiçik batch kimi yenidən növbəyə qoy
        ProcessAiBatchJob::dispatch(
            items:         $products->map(fn ($p) => ['id' => $p->id, 'prompt' => "Yenidən cəhd: {$p->name}"])->all(),
            batchableType: 'product_descriptions',
            callbackJob:   self::class,
        )->delay(now()->addHour());
    }
}
```

### Manual Tetiklemek üçün Artisan Əmri

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\AI\ProductSeoBatchService;
use Illuminate\Console\Command;

final class GenerateSeoDescriptionsCommand extends Command
{
    protected $signature = 'ai:generate-seo
        {--limit=1000 : Emal ediləcək maks məhsul}
        {--force : Mövcud təsvirləri belə yenidən yarat}';

    protected $description = 'Claude Batches API istifadə edərək məhsullar üçün SEO meta təsvirlər yarat';

    public function handle(ProductSeoBatchService $service): int
    {
        $limit = (int) $this->option('limit');
        $force = (bool) $this->option('force');

        $query = Product::query()->select(['id', 'name', 'category', 'short_description', 'price']);

        if (! $force) {
            $query->where(fn ($q) => $q->whereNull('seo_meta_description')
                ->orWhere('seo_meta_description', ''));
        }

        $products = $query->limit($limit)->get();

        if ($products->isEmpty()) {
            $this->info('Heç bir məhsulun SEO təsvirinə ehtiyacı yoxdur.');
            return self::SUCCESS;
        }

        $this->info("{$products->count()} məhsul üçün batch yaradılır…");
        $batchId = $service->createBatchNow($products);

        $this->info("Batch yaradıldı: {$batchId}");
        $this->line('Batch tamamlandıqda nəticələr avtomatik saxlanacaq (adətən 1 saat ərzindədir).');
        $this->line("Vəziyyəti izlə: php artisan ai:batch-status {$batchId}");

        return self::SUCCESS;
    }
}
```

---

## Xərc Optimallaşdırma Strategiyaları

### 1. Böyük Batch-ləri Parçalamaq

500.000 məhsulunuz varsa, hər biri 100.000-dən ibarət 5 batch-ə bölün. Hamısını eyni anda göndərin — Anthropic paralel emal edir.

```php
collect($allItems)
    ->chunk(100_000)
    ->each(fn ($chunk) => ProcessAiBatchJob::dispatch(
        items: $chunk->values()->all(),
        ...
    ));
```

### 2. Batch-i Prompt Caching ilə Birləşdirmək

Batch sorğularında sistem promptunda `cache_control` istifadə edin — cache yazma və oxumalar batch-lərdə belə sorğu başına istifadə məlumatlarına əks olunur:

```php
$params = [
    'model'      => 'claude-opus-4-7',
    'max_tokens' => 512,
    'system'     => [
        [
            'type'          => 'text',
            'text'          => $longSystemPrompt,
            'cache_control' => ['type' => 'ephemeral'],
        ],
    ],
    'messages' => [['role' => 'user', 'content' => $userPrompt]],
];
```

10.000 sorğunun hamısı eyni sistem promptunu paylaşdığından, ilk sorğu cache yazır, qalan 9.999 isə 90% endirim alır — batch endirimi üzərinə qatlanır.

### 3. `max_tokens`-i Düzgün Seçmək

Batches API istifadə edilmiş faktiki çıxış tokenləri üçün hesab edir, lakin `max_tokens`-i artıq təyin etmək əlavə xərc yaratmır. Lakin çox az təyin etmək `max_tokens` dayandırma səbəbi və kəsilmiş çıxış yaradır. İstifadə halınızı profilləyin və `max_tokens`-i gözlənilən çıxış uzunluğunun 95-ci persentilinə uyğun təyin edin.

---

## İzləmə və Müşahidə

```php
// app/Console/Commands/BatchStatusCommand.php
public function handle(BatchProcessor $processor): int
{
    $batchId = $this->argument('batch_id');
    $status  = $processor->getStatus($batchId);

    $this->table(
        ['Sahə', 'Dəyər'],
        [
            ['Vəziyyət',     $status['status']],
            ['Emal edilir', $status['counts']['processing']],
            ['Uğurlu',  $status['counts']['succeeded']],
            ['Xətalı',    $status['counts']['errored']],
            ['Vaxtı bitmiş',    $status['counts']['expired']],
            ['Bitmə vaxtı',    $status['ended_at'] ?? 'gözlənilir'],
            ['Vaxt bitmə tarixi', $status['expires_at']],
        ],
    );

    return self::SUCCESS;
}
```

---

## Praktik Tapşırıqlar

### Tapşırıq 1: Batch Email Classifier

1000 müştəri emailini Batch API ilə eyni anda classify et. Hər email üçün ayrı request yarat: `{custom_id: "email_{id}", model: "haiku", messages: [...]}`. Batch-i göndər, webhook ya da polling ilə nəticəni al. Real-time API ilə cost + latency müqayisəsi apar (50% endirim gözlənilir).

### Tapşırıq 2: Batch Status Polling Job

`BatchStatusCheckJob` Laravel scheduled command-ı yarat: hər 5 dəqiqədə `batch_status` endpoint-ini sorğula. `in_progress` → gözlə, `ended` → nəticəni S3-dan yüklə, parse et, `batch_results` cədvəlinə yaz. Xətalı request-ləri (`errored`) flag et, yenidən göndər.

### Tapşırıq 3: Overnight Processing Pipeline

Gündüzlük yığılan bütün sənədləri (invoice, contract) gün sonunda bir batch-ə yığ. Gecə yarısı `ProcessDailyBatchJob` çalışdır: batch göndər. Səhər `RetrieveBatchResultsJob` nəticəni çəkib database-ə yaz. Real-time işləmə cost-unun 50%-ini qənaət etdiyini yoxla.

---

## Əlaqəli Mövzular

- `01-claude-api-guide.md` — API auth və temel istifadə
- `11-rate-limits-retry-php.md` — Batch request-lərdə xəta idarəetməsi
- `../07-workflows/03-laravel-queue-ai-patterns.md` — Queue ilə batch emalın orkestrası
- `../01-fundamentals/11-llm-pricing-economics.md` — Batch API-nin unit economics üzərindəki 50% təsiri
