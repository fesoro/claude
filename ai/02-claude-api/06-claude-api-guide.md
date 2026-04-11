# Claude / Anthropic API — Tam Developer İstinadı

> Autentifikasiya, modellər, sürət limitləri, xəta emalı və produksiyaya hazır Laravel müştərisini əhatə edən Anthropic Messages API üçün tam istinad.

---

## Mündəricat

1. [API İcmalı](#api-icmalı)
2. [Autentifikasiya](#autentifikasiya)
3. [Cari Modellər İstinadı](#cari-modellər-istinadı)
4. [Messages API](#messages-api)
5. [Sorğu Strukturu](#sorğu-strukturu)
6. [Cavab Strukturu](#cavab-strukturu)
7. [Sürət Limitləri](#sürət-limitləri)
8. [Xəta Kodları və Emal](#xəta-kodları-və-emal)
9. [Axın (Streaming)](#axın-streaming)
10. [Prompt Keşləmə](#prompt-keşləmə)
11. [Laravel: Möhkəm API Müştərisi](#laravel-möhkəm-api-müştərisi)
12. [Laravel: Servis Provider Quraşdırması](#laravel-servis-provider-quraşdırması)
13. [Monitorinq və Müşahidə](#monitorinq-və-müşahidə)

---

## API İcmalı

Anthropic API JSON sorğu/cavab gövdələri ilə REST konvensiyalarına əməl edir. Bütün sorğular bu ünvana göndərilir:

```
Əsas URL: https://api.anthropic.com

Cari API versiyası: 2023-06-01
Versiya başlığı: anthropic-version: 2023-06-01

Əsas endpoint-lər:
  POST /v1/messages          — Mesaj yarat (əsas endpoint)
  POST /v1/messages/count_tokens — Generasiya etmədən tokenləri say
  GET  /v1/models            — Mövcud modelləri siyahıla
```

### Əsas Anlayışlar

```
MESSAGES API:
  Əsas interfeys. Mesajlar siyahısı alır və modelin cavabını qaytarır.
  Mesajlar massivi vasitəsilə tək-dövrəli və çox-dövrəli
  söhbətləri idarə edir.

MƏZMUN BLOKLARI:
  Həm giriş, həm də çıxış müxtəlif növlərə malik birdən çox "məzmun bloku"
  ehtiva edə bilər: text, image, tool_use, tool_result.
  Çoxmodallı və alət istifadəsi bu şəkildə işləyir.

SİSTEM PROMPT:
  Mesajlardan ayrı üst səviyyəli sahə. Kontekst, şəxsiyyət
  və təlimatları müəyyən edir. Messages massivinin bir hissəsi DEYİL.

AXIN (STREAMING):
  Tamamlanmasını gözləmək əvəzinə generasiya edildikcə tokenləri
  Server-Sent Events kimi almaq üçün "stream": true əlavə edin.
```

---

## Autentifikasiya

```bash
# Bütün sorğular başlıqda API açarını tələb edir:
curl https://api.anthropic.com/v1/messages \
  -H "x-api-key: $ANTHROPIC_API_KEY" \
  -H "anthropic-version: 2023-06-01" \
  -H "content-type: application/json" \
  --data '{
    "model": "claude-sonnet-4-6",
    "max_tokens": 1024,
    "messages": [{"role": "user", "content": "Salam"}]
  }'
```

### Açar İdarəetmə Ən Yaxşı Praktikalar

```
1. API açarlarını heç vaxt versiya nəzarətinə əlavə etməyin
   Saxlayın: .env fayl (gitignored), AWS Secrets Manager,
             HashiCorp Vault, Kubernetes secrets

2. İnkişaf və produksiya üçün ayrı açarlar istifadə edin
   İzlənilə bilənlik üçün Anthropic konsolunda etiketləyin

3. Açarları müntəzəm yeniləyin (hər 90 gündə tövsiyə edilir)

4. Anthropic konsolunda istifadə xəbərdarlıqları qurun
   Aylıq büdcənin 50%, 80%, 100%-ində xəbərdarlıq

5. Produksiyada: mühit səviyyəsindəki açar injeksiyası istifadə edin
   (Docker secrets, ECS tapşırıq tərifləri, K8s secrets)
```

### Laravel Mühit Quraşdırması

```ini
# .env
ANTHROPIC_API_KEY=sk-ant-api03-...
ANTHROPIC_DEFAULT_MODEL=claude-sonnet-4-6
ANTHROPIC_MAX_TOKENS=4096
ANTHROPIC_TIMEOUT=120

# Test mühiti üçün
ANTHROPIC_API_KEY_TEST=sk-ant-api03-test-...
```

---

## Cari Modellər İstinadı

### Produksiya Modelləri (2026-cı il etibarilə)

| Model ID | Kontekst Pəncərəsi | Maks Çıxış | Ən Uyğun |
|----------|---------------|------------|----------|
| `claude-haiku-4-5-20251001` | 200,000 | 8,192 | Yüksək həcmli, sürətli tapşırıqlar |
| `claude-sonnet-4-6` | 200,000 | 8,192 | Ümumi produksiya istifadəsi |
| `claude-opus-4-6` | 200,000 | 4,096 | Mürəkkəb, yüksək əhəmiyyətli tapşırıqlar |

### Qiymətləndirmə (təxmini, console.anthropic.com-da yoxlayın)

| Model | Giriş (1M başına) | Çıxış (1M başına) | Keş Yazma | Keş Oxuma |
|-------|---------------|-----------------|-------------|------------|
| Haiku 4.5 | $0.80 | $4.00 | $1.00 | $0.08 |
| Sonnet 4.6 | $3.00 | $15.00 | $3.75 | $0.30 |
| Opus 4.6 | $15.00 | $75.00 | $18.75 | $1.50 |

### Model ID Versiyalaşdırması

```
claude-sonnet-4-6          ← Son alias (avtomatik yenilənir)
claude-sonnet-4-6-20261001 ← Xüsusi versiyaya bağlı (sabit)

TÖVSİYƏ:
  Ardıcıllıq üçün produksiyada bağlı versiyalar istifadə edin.
  Son yeniliklər üçün inkişafda aliaslar istifadə edin.
  
  Bağlı versiyaların yüksəldilməsini qiymətləndirmək üçün aylıq tapşırıq qeyd edin.
```

---

## Messages API

### Sorğunun Anatomiyası

```json
{
  "model": "claude-sonnet-4-6",
  "max_tokens": 2048,
  "system": "Siz baş PHP developersınız...",
  "messages": [
    {
      "role": "user",
      "content": "Bu kodu nəzərdən keçirin..."
    },
    {
      "role": "assistant",
      "content": "İndi nəzərdən keçirəcəyəm..."
    },
    {
      "role": "user",
      "content": "Təhlükəsizlik məsələlərinə fokuslanın"
    }
  ],
  "temperature": 0.7,
  "top_p": 0.95,
  "top_k": 50,
  "stop_sequences": ["BAXIŞ_SONU"],
  "stream": false,
  "metadata": {
    "user_id": "user_12345"
  }
}
```

### Mesaj Rolları

```
"user":      İnsan/tətbiqdən məzmun
"assistant": Claude-dan məzmun (tarixi təqdim etmək üçün istifadə edilir)

Qaydalar:
  - Mesajlar növbə ilə olmalıdır: user, assistant, user, assistant...
  - İlk mesaj "user" olmalıdır
  - Son mesaj "user" olmalıdır (buna cavab verilir)
  - Köməkçinin cavabını assistant mesajı ilə bitirərək "öncədən doldurmaq" olar
    — Claude dayandığı yerdən davam edir
```

### Məzmun Blok Növləri

```json
// Mətn məzmunu (sadə)
{"role": "user", "content": "Salam"}

// Mətn məzmunu (açıq blok)
{"role": "user", "content": [{"type": "text", "text": "Salam"}]}

// Şəkil məzmunu
{
  "role": "user",
  "content": [
    {
      "type": "image",
      "source": {
        "type": "base64",
        "media_type": "image/jpeg",
        "data": "/9j/4AAQ..."
      }
    },
    {"type": "text", "text": "Bu şəkildə nə var?"}
  ]
}

// URL-dən şəkil (bəzi modellər dəstəkləyir)
{
  "type": "image",
  "source": {
    "type": "url",
    "url": "https://example.com/image.jpg"
  }
}

// Alət nəticəsi (alət çağırışından cavab)
{
  "role": "user",
  "content": [
    {
      "type": "tool_result",
      "tool_use_id": "toolu_01abc",
      "content": "Parisdəki hava 18°C və günəşlidir."
    }
  ]
}
```

---

## Cavab Strukturu

```json
{
  "id": "msg_01XFDUDYJgAACzvnptvVoYEL",
  "type": "message",
  "role": "assistant",
  "content": [
    {
      "type": "text",
      "text": "Budur mənim analizim..."
    }
  ],
  "model": "claude-sonnet-4-6",
  "stop_reason": "end_turn",
  "stop_sequence": null,
  "usage": {
    "input_tokens": 2095,
    "output_tokens": 503,
    "cache_creation_input_tokens": 0,
    "cache_read_input_tokens": 0
  }
}
```

### Dayandırma Səbəbləri

```
"end_turn":        Model təbii şəkildə bitirdi (EOS tokeni yaratdı)
"max_tokens":      max_tokens limitinə çatdı — cavab kəsildi!
"stop_sequence":   stop_sequences-dən birini yaratdı
"tool_use":        Model alət çağırmaq istəyir (alət istifadəsi bələdçisinə baxın)

VACİB: Produksiya kodunda stop_reason yoxlayın.
stop_reason === "max_tokens" olarsa, cavab TAMAMLANMAYIB.
Aşağıdakılardan birini etməlisiniz:
  a) max_tokens-i artırın
  b) Kəsilmiş cavabı kontekst kimi istifadə edərək generasiyanı davam etdirin
  c) Natamam cavabı xəbərdar edin və idarə edin
```

---

## Sürət Limitləri

### Standart Sürət Limitləri (Dərəcə 1 — Yeni API Açarları)

```
Dəqiqə Başına Sorğu (RPM):       50
Dəqiqə Başına Giriş Tokeni (ITPM): 50,000
Dəqiqə Başına Çıxış Tokeni (OTPM): 10,000

Bunlar AŞAĞIDIR. Yeni tətbiqlər bu limitlərə tez çatır.
Daha yüksək limitlər üçün müraciət edin: console.anthropic.com → Rate Limits
```

### Produksiya Sürət Limitləri (Dərəcə 4+)

```
Model: Claude Sonnet 4.6 (təxmini)
RPM:   4,000
ITPM:  400,000
OTPM:  80,000

Model: Claude Haiku 4.5 (təxmini)
RPM:   4,000
ITPM:  1,000,000
OTPM:  100,000
```

### Sürət Limiti Başlıqları

```
Hər cavab sürət limiti məlumatı ehtiva edir:
  anthropic-ratelimit-requests-limit:      4000
  anthropic-ratelimit-requests-remaining:  3999
  anthropic-ratelimit-requests-reset:      2024-01-15T10:00:01Z
  anthropic-ratelimit-tokens-limit:        400000
  anthropic-ratelimit-tokens-remaining:    399000
  anthropic-ratelimit-tokens-reset:        2024-01-15T10:00:01Z
  retry-after:                             30  (yalnız 429 cavablarında)
```

---

## Xəta Kodları və Emal

### HTTP Status Kodları

```
200 OK:              Sorğu uğurlu oldu
400 Bad Request:     Etibarsız sorğu (tələb olunan sahələr yoxdur, və s.)
401 Unauthorized:    Etibarsız API açarı
403 Forbidden:       API açarının icazəsi yoxdur
404 Not Found:       Etibarsız endpoint və ya model
422 Unprocessable:   Sorğu doğrulaması uğursuz oldu
429 Too Many Req:    Sürət limiti aşıldı
500 Internal Error:  Anthropic server xətası
529 Overloaded:      Anthropic API müvəqqəti olaraq həddindən artıq yüklüdür
```

### Xəta Cavabı Formatı

```json
{
  "type": "error",
  "error": {
    "type": "rate_limit_error",
    "message": "Sorğu tokenlerinin sayı dəqiqəlik sürət limitini aşıb..."
  }
}
```

### Xəta Növləri

```
authentication_error:  Etibarsız API açarı
invalid_request_error: Deformasiya olmuş sorğu
not_found_error:       Model və ya resurs tapılmadı
permission_error:      Qeyri-kafi icazələr
rate_limit_error:      Sürət limiti aşıldı — geri çəkilmə ilə yenidən cəhd edin
api_error:             Anthropic server xətası — yenidən cəhd edin
overloaded_error:      Müvəqqəti həddindən artıq yüklənmə — yenidən cəhd edin
```

### Yenidən Cəhd Strategiyası

```
YENİDƏN CƏHD EDİLƏ BİLƏN XƏTALAR:
  429 rate_limit_error: retry-after başlığına hörmət edin
  500 api_error:        Eksponensial geri çəkilmə
  529 overloaded_error: Eksponensial geri çəkilmə

YENİDƏN CƏHD EDİLƏ BİLMƏYƏN XƏTALAR:
  400 invalid_request_error: Sorğunu düzəldin
  401 authentication_error:  API açarını düzəldin
  403 permission_error:      İcazələri yoxlayın
  422 validation error:      Sorğu parametrlərini düzəldin

EKSPONENSİAL GERİ ÇƏKİLMƏ DÜSTURu:
  gecikmə = min(əsas_gecikmə * 2^cəhd + jitter, maks_gecikmə)
  
  cəhd=0: 1s + təsadüfi(0-1s) ≈ 1-2s
  cəhd=1: 2s + təsadüfi(0-1s) ≈ 2-3s
  cəhd=2: 4s + təsadüfi(0-1s) ≈ 4-5s
  cəhd=3: 8s + təsadüfi(0-1s) ≈ 8-9s
  maks: 60s
```

---

## Axın (Streaming)

Axın generasiya edildikcə tokenləri Server-Sent Events (SSE) kimi qaytarır.

```json
// Sorğu: "stream": true ilə normal kimi

// Cavab hadisələr axınıdır:
event: message_start
data: {"type":"message_start","message":{"id":"msg_01...","type":"message",...}}

event: content_block_start
data: {"type":"content_block_start","index":0,"content_block":{"type":"text","text":""}}

event: ping
data: {"type":"ping"}

event: content_block_delta
data: {"type":"content_block_delta","index":0,"delta":{"type":"text_delta","text":"Salam"}}

event: content_block_delta
data: {"type":"content_block_delta","index":0,"delta":{"type":"text_delta","text":", necə"}}

event: content_block_delta
data: {"type":"content_block_delta","index":0,"delta":{"type":"text_delta","text":" kömək edə bilərəm?"}}

event: content_block_stop
data: {"type":"content_block_stop","index":0}

event: message_delta
data: {"type":"message_delta","delta":{"stop_reason":"end_turn","stop_sequence":null},"usage":{"output_tokens":12}}

event: message_stop
data: {"type":"message_stop"}
```

### Laravel Axın Cavabı

```php
<?php

namespace App\Http\Controllers;

use Anthropic\Laravel\Facades\Anthropic;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StreamingChatController extends Controller
{
    public function stream(Request $request): StreamedResponse
    {
        $request->validate([
            'message' => 'required|string|max:10000',
        ]);

        return response()->stream(function () use ($request) {
            $stream = Anthropic::messages()->createStreamed([
                'model'      => 'claude-sonnet-4-6',
                'max_tokens' => 2048,
                'system'     => 'Siz faydalı bir köməkçisiniz.',
                'messages'   => [
                    ['role' => 'user', 'content' => $request->input('message')],
                ],
            ]);

            foreach ($stream as $response) {
                $text = match ($response->type) {
                    'content_block_delta' => $response->delta->text ?? '',
                    default               => '',
                };

                if ($text !== '') {
                    // SSE formatı
                    echo "data: " . json_encode(['text' => $text]) . "\n\n";
                    ob_flush();
                    flush();
                }
            }

            echo "data: [DONE]\n\n";
            ob_flush();
            flush();
        }, 200, [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache',
            'X-Accel-Buffering' => 'no', // nginx buferizasiyasını deaktiv edin
        ]);
    }
}
```

---

## Prompt Keşləmə

Prompt keşləmə tez-tez təkrarlanan məzmunu (sistem prompt-ları və böyük sənədlər kimi) keşləməyə və keşlənmiş məzmundan istifadə edən sonrakı sorğular üçün 90% daha az ödəməyə imkan verir.

```json
// cache_control ilə keşlənəcək məzmunu işarələyin
{
  "model": "claude-sonnet-4-6",
  "system": [
    {
      "type": "text",
      "text": "Siz Laravel haqqında dərin biliyə malik ekspert PHP developersınız...",
      "cache_control": {"type": "ephemeral"}
    }
  ],
  "messages": [...]
}

// Keş ömrü: 5 dəqiqə (ephemeral)
// Xərc: yazma normal qiymətdən 1.25x; oxuma normal qiymətdən 0.1x
// Keşlənə bilən minimum ölçü: 1024 token

// Keş VURULDUQDA:
// usage.cache_read_input_tokens > 0
// Bu tokenlər üçün 0.1x ödəyirsiniz

// Keş İTİRİLDİKDƏ (ilk dəfə və ya müddəti bitdikdə):
// usage.cache_creation_input_tokens > 0
// Bu tokenlər üçün 1.25x ödəyirsiniz (keşi doldurmaq üçün bir dəfəlik xərc)
```

---

## Laravel: Möhkəm API Müştərisi

```php
<?php

declare(strict_types=1);

namespace App\AI\Client;

use Anthropic\Exceptions\ApiException;
use Anthropic\Laravel\Facades\Anthropic;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Aşağıdakıları ehtiva edən produksiyaya hazır Claude API müştərisi:
 * - Eksponensial geri çəkilmə ilə avtomatik yenidən cəhd
 * - Sürət limitinin idarə edilməsi və hörmət edilməsi
 * - Sorğu/cavab qeydiyyatı
 * - Xərc izlənməsi
 * - Circuit breaker nümunəsi
 * - Timeout idarəetməsi
 */
class ClaudeClient
{
    private const MAX_RETRIES = 3;
    private const BASE_DELAY_MS = 1000;
    private const MAX_DELAY_MS = 60_000;
    private const CIRCUIT_BREAKER_THRESHOLD = 5; // açılmadan əvvəl uğursuzluqlar
    private const CIRCUIT_BREAKER_TIMEOUT = 60;  // yarı-açıqdan əvvəl saniyələr

    public function __construct(
        private readonly string $defaultModel = 'claude-sonnet-4-6',
        private readonly int $defaultMaxTokens = 4096,
    ) {}

    /**
     * Tam xəta emalı və yenidən cəhd məntiqi ilə Claude-a mesaj göndərin.
     *
     * @param  array  $messages  Mesaj obyektlərinin massivi
     * @param  array  $options   Əlavə API seçimləri
     * @return ApiResponse
     */
    public function messages(array $messages, array $options = []): ApiResponse
    {
        $requestId = Str::uuid()->toString();
        $startTime = microtime(true);

        // Circuit breaker-i yoxlayın
        if ($this->isCircuitOpen()) {
            throw new CircuitOpenException(
                'Təkrarlanan uğursuzluqlar səbəbindən Claude API circuit breaker açıqdır. ' .
                $this->getCircuitResetSeconds() . ' saniyədən sonra yenidən cəhd ediləcək.'
            );
        }

        $payload = $this->buildPayload($messages, $options);

        Log::debug('Claude API sorğusu', [
            'request_id'  => $requestId,
            'model'       => $payload['model'],
            'message_count' => count($messages),
            'max_tokens'  => $payload['max_tokens'],
            'has_system'  => isset($options['system']),
            'has_tools'   => isset($options['tools']),
        ]);

        $lastException = null;

        for ($attempt = 0; $attempt <= self::MAX_RETRIES; $attempt++) {
            try {
                if ($attempt > 0) {
                    $this->sleepWithJitter($attempt);
                }

                $response = Anthropic::messages()->create($payload);

                $duration = microtime(true) - $startTime;

                // Uğuru qeyd edin (circuit breaker-i sıfırlayır)
                $this->recordSuccess();

                $result = new ApiResponse(
                    id: $response->id,
                    content: $response->content[0]->text ?? '',
                    contentBlocks: $response->content,
                    stopReason: $response->stopReason,
                    model: $response->model,
                    inputTokens: $response->usage->inputTokens,
                    outputTokens: $response->usage->outputTokens,
                    cacheReadTokens: $response->usage->cacheReadInputTokens ?? 0,
                    cacheWriteTokens: $response->usage->cacheCreationInputTokens ?? 0,
                    durationMs: (int) ($duration * 1000),
                    requestId: $requestId,
                );

                Log::info('Claude API cavabı', [
                    'request_id'    => $requestId,
                    'model'         => $result->model,
                    'input_tokens'  => $result->inputTokens,
                    'output_tokens' => $result->outputTokens,
                    'cache_hit'     => $result->cacheReadTokens > 0,
                    'stop_reason'   => $result->stopReason,
                    'duration_ms'   => $result->durationMs,
                    'estimated_cost_usd' => $result->estimatedCostUsd(),
                ]);

                // Cavab kəsilibsə xəbərdarlıq verin
                if ($result->stopReason === 'max_tokens') {
                    Log::warning('Claude cavabı max_tokens tərəfindən kəsildi', [
                        'request_id'  => $requestId,
                        'max_tokens'  => $payload['max_tokens'],
                        'output_tokens' => $result->outputTokens,
                    ]);
                }

                return $result;

            } catch (ApiException $e) {
                $statusCode = $e->getCode();
                $errorType = $this->extractErrorType($e);

                Log::warning('Claude API xətası', [
                    'request_id' => $requestId,
                    'attempt'    => $attempt + 1,
                    'status'     => $statusCode,
                    'error_type' => $errorType,
                    'message'    => $e->getMessage(),
                ]);

                // Yenidən cəhd edilə bilməyən xətalar
                if (in_array($statusCode, [400, 401, 403, 404, 422])) {
                    $this->recordFailure();
                    throw new ApiClientException(
                        "Yenidən cəhd edilə bilməyən Claude API xətası ({$statusCode}): {$e->getMessage()}",
                        $statusCode,
                        $e
                    );
                }

                // Sürət limiti — mövcudsa retry-after başlığından istifadə edin
                if ($statusCode === 429) {
                    $retryAfter = $this->getRetryAfterSeconds($e);
                    if ($retryAfter && $attempt < self::MAX_RETRIES) {
                        Log::info("Sürət limiti. {$retryAfter}s gözlənilir", [
                            'request_id' => $requestId,
                        ]);
                        sleep($retryAfter);
                        $lastException = $e;
                        continue;
                    }
                }

                $lastException = $e;

                // Server xətaları və həddindən artıq yüklənmə — yenidən cəhd edilə bilər
                if ($statusCode >= 500 || $statusCode === 529) {
                    if ($attempt < self::MAX_RETRIES) {
                        continue; // Döngünün başında yatacaq
                    }
                }

                break;
            }
        }

        $this->recordFailure();

        throw new ApiClientException(
            "Claude API " . (self::MAX_RETRIES + 1) . " cəhddən sonra uğursuz oldu: " .
            $lastException?->getMessage(),
            $lastException?->getCode() ?? 0,
            $lastException
        );
    }

    /**
     * Mesajlar və seçimlər əsasında tam API payload-ı yaradın.
     */
    private function buildPayload(array $messages, array $options): array
    {
        return array_filter([
            'model'          => $options['model'] ?? $this->defaultModel,
            'max_tokens'     => $options['max_tokens'] ?? $this->defaultMaxTokens,
            'system'         => $options['system'] ?? null,
            'messages'       => $messages,
            'temperature'    => $options['temperature'] ?? null,
            'top_p'          => $options['top_p'] ?? null,
            'top_k'          => $options['top_k'] ?? null,
            'stop_sequences' => $options['stop_sequences'] ?? null,
            'tools'          => $options['tools'] ?? null,
            'tool_choice'    => $options['tool_choice'] ?? null,
            'stream'         => false,
            'metadata'       => isset($options['user_id'])
                ? ['user_id' => $options['user_id']]
                : null,
        ], fn ($v) => $v !== null);
    }

    /**
     * Eksponensial geri çəkilmə və təsadüfi jitter ilə yatın.
     */
    private function sleepWithJitter(int $attempt): void
    {
        $baseDelay = self::BASE_DELAY_MS * pow(2, $attempt - 1);
        $jitter = random_int(0, (int) ($baseDelay * 0.3));
        $delay = min($baseDelay + $jitter, self::MAX_DELAY_MS);

        usleep($delay * 1000);
    }

    /**
     * Sürət limiti istisnanasından retry-after dəyərini çıxarın.
     */
    private function getRetryAfterSeconds(ApiException $e): ?int
    {
        // Anthropic SDK istisna üzərindəki başlıqları açıqlaya bilər
        // Bu tətbiq asılıdır
        if (method_exists($e, 'getHeaders')) {
            $headers = $e->getHeaders();
            $retryAfter = $headers['retry-after'][0] ?? null;
            if ($retryAfter) {
                return (int) $retryAfter;
            }
        }

        return null;
    }

    private function extractErrorType(ApiException $e): string
    {
        $body = $e->getMessage();
        if (preg_match('/"type"\s*:\s*"([^"]+)"/', $body, $m)) {
            return $m[1];
        }
        return 'naməlum';
    }

    // Keş istifadə edərək circuit breaker tətbiqi
    private function isCircuitOpen(): bool
    {
        $state = Cache::get('claude_circuit_state', 'closed');

        if ($state === 'open') {
            $openedAt = Cache::get('claude_circuit_opened_at', 0);
            if (time() - $openedAt > self::CIRCUIT_BREAKER_TIMEOUT) {
                Cache::put('claude_circuit_state', 'half-open', 300);
                return false; // Bir test sorğusunu keçirtin
            }
            return true;
        }

        return false;
    }

    private function recordSuccess(): void
    {
        Cache::put('claude_circuit_failures', 0, 300);
        Cache::put('claude_circuit_state', 'closed', 300);
    }

    private function recordFailure(): void
    {
        $failures = Cache::increment('claude_circuit_failures');
        Cache::put('claude_circuit_failures', $failures, 300);

        if ($failures >= self::CIRCUIT_BREAKER_THRESHOLD) {
            Cache::put('claude_circuit_state', 'open', 300);
            Cache::put('claude_circuit_opened_at', time(), 300);
            Log::critical('Claude API circuit breaker AÇILDI', [
                'failures' => $failures,
            ]);
        }
    }

    private function getCircuitResetSeconds(): int
    {
        $openedAt = Cache::get('claude_circuit_opened_at', 0);
        return max(0, self::CIRCUIT_BREAKER_TIMEOUT - (time() - $openedAt));
    }
}

/**
 * Strukturlu API cavabı.
 */
readonly class ApiResponse
{
    public function __construct(
        public string $id,
        public string $content,
        public array $contentBlocks,
        public string $stopReason,
        public string $model,
        public int $inputTokens,
        public int $outputTokens,
        public int $cacheReadTokens,
        public int $cacheWriteTokens,
        public int $durationMs,
        public string $requestId,
    ) {}

    public function isTruncated(): bool
    {
        return $this->stopReason === 'max_tokens';
    }

    public function hasToolUse(): bool
    {
        return $this->stopReason === 'tool_use';
    }

    /**
     * Model və token istifadəsinə əsasən USD-də xərci qiymətləndirin.
     */
    public function estimatedCostUsd(): float
    {
        $pricing = [
            'claude-haiku-4-5'   => ['input' => 0.0008, 'output' => 0.004, 'cache_write' => 0.001, 'cache_read' => 0.00008],
            'claude-sonnet-4-6'  => ['input' => 0.003,  'output' => 0.015, 'cache_write' => 0.00375, 'cache_read' => 0.0003],
            'claude-opus-4-6'    => ['input' => 0.015,  'output' => 0.075, 'cache_write' => 0.01875, 'cache_read' => 0.0015],
        ];

        // Model prefiksini uyğunlaşdırın
        $rates = null;
        foreach ($pricing as $prefix => $price) {
            if (str_starts_with($this->model, $prefix)) {
                $rates = $price;
                break;
            }
        }

        if (!$rates) {
            return 0.0;
        }

        $billableInput = $this->inputTokens - $this->cacheReadTokens;

        return round(
            ($billableInput        / 1_000_000 * $rates['input']) +
            ($this->outputTokens   / 1_000_000 * $rates['output']) +
            ($this->cacheWriteTokens / 1_000_000 * $rates['cache_write']) +
            ($this->cacheReadTokens  / 1_000_000 * $rates['cache_read']),
            8
        );
    }
}

class ApiClientException extends \RuntimeException {}
class CircuitOpenException extends \RuntimeException {}
```

---

## Laravel: Servis Provider Quraşdırması

```php
<?php

// config/ai.php
return [
    'default_model'       => env('ANTHROPIC_DEFAULT_MODEL', 'claude-sonnet-4-6'),
    'default_max_tokens'  => env('ANTHROPIC_MAX_TOKENS', 4096),
    'timeout'             => env('ANTHROPIC_TIMEOUT', 120),

    'models' => [
        'fast'     => 'claude-haiku-4-5-20251001',
        'balanced' => 'claude-sonnet-4-6',
        'powerful' => 'claude-opus-4-6',
    ],

    'rate_limits' => [
        'requests_per_minute' => env('ANTHROPIC_RPM', 50),
        'tokens_per_minute'   => env('ANTHROPIC_TPM', 50000),
    ],
];
```

```php
<?php

namespace App\Providers;

use App\AI\Client\ClaudeClient;
use Illuminate\Support\ServiceProvider;

class AIServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ClaudeClient::class, function () {
            return new ClaudeClient(
                defaultModel: config('ai.default_model'),
                defaultMaxTokens: config('ai.default_max_tokens'),
            );
        });
    }
}
```

---

## Monitorinq və Müşahidə

### İzlənəcək Metriklər

```php
<?php

namespace App\AI\Monitoring;

use Illuminate\Support\Facades\Log;

trait TracksAIMetrics
{
    protected function recordApiCall(
        string $feature,
        string $model,
        int $inputTokens,
        int $outputTokens,
        float $durationMs,
        bool $success,
        float $costUsd,
    ): void {
        // DataDog, CloudWatch, və s. tərəfindən qəbul üçün strukturlu loq
        Log::channel('ai_metrics')->info('api_call', [
            'feature'       => $feature,
            'model'         => $model,
            'input_tokens'  => $inputTokens,
            'output_tokens' => $outputTokens,
            'total_tokens'  => $inputTokens + $outputTokens,
            'duration_ms'   => $durationMs,
            'success'       => $success,
            'cost_usd'      => $costUsd,
            'timestamp'     => now()->toIso8601String(),
        ]);
    }
}
```

### Əsas Metriklər Paneli

```
İzlənəcək əməliyyat metriklər:
  - API xəta dərəcəsi (hədəf: < 0.1%)
  - P50/P95/P99 gecikməsi
  - Sürət limiti vurulma dərəcəsi (hədəf: < 1%)
  - Circuit breaker vəziyyəti

Xərc metriklər:
  - Gün/həftə/ay üzrə ümumi xərc
  - Xüsusiyyət başına xərc (çat, kod baxışı, çıxarım, və s.)
  - Sorğu başına orta xərc
  - Keş vurulma dərəcəsi (statik məzmun üçün hədəf: > 60%)

Keyfiyyət metriklər:
  - Kəsilmə dərəcəsi (max_tokens vurulur) — max_tokens-in çox aşağı olduğunu göstərir
  - Alət çağırışı uğursuzluq dərəcəsi
  - Yenidən cəhd dərəcəsi — API qeyri-sabitliyini göstərir
```

---

*Əvvəlki: [05 — Multimodal AI](../01-fundamentals/05-multimodal-ai.md) | Növbəti: [07 — Prompt Engineering](./07-prompt-engineering.md)*
