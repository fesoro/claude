# Claude Extended Thinking: API, Budget, Streaming və Production Patterns (Senior)

> Hədəf auditoriyası: Claude API-ni Laravel production-da istifadə edən senior developerlər. Bu sənəd extended thinking feature-unun API səviyyəsində necə işlədiyini, budget idarəsini, streaming davranışını və real production nümunələrini əhatə edir. Reasoning modellərin konseptual izahı üçün 08-reasoning-models.md-ə bax; agentic tool use ilə interleaved thinking detalları 05-agents folderində.

---

## Mündəricat

1. [Extended Thinking Nədir](#what-is-extended-thinking)
2. [API Mexanikası](#api-mechanics)
3. [Request Sxemi](#request-schema)
4. [Response Strukturu](#response-structure)
5. [Budget Tokens — Sizing Heuristics](#budget-sizing)
6. [Billing — Thinking Tokenlər Necə Hesablanır](#billing)
7. [Redacted Thinking Blokları](#redacted-thinking)
8. [Streaming SSE Events](#streaming)
9. [Interleaved Thinking + Tool Use](#interleaved-thinking)
10. [Signature və Context Saxlama](#signature)
11. [Laravel Wrapper Class](#laravel-wrapper)
12. [Retry və Fallback Strategiyaları](#retry-fallback)
13. [Summarization Thinking Uzun Agent-lər üçün](#summarization)
14. [Anti-Pattern-lər və Güvənlik](#anti-patterns)
15. [Observability və Debug](#observability)
16. [Migration Guide (Classic → Thinking)](#migration)
17. [Qərar Çərçivəsi](#decision-framework)

---

## Extended Thinking Nədir

Extended thinking — Claude Opus və Sonnet modellərində (4.x+) mövcud olan feature-dur. Model cavab vermədən əvvəl **ayrı thinking blokları** yaradır — bu bloklar daxili mühakimədir və response-də `type: "thinking"` olaraq qayıdır.

```
Sadə müqayisə:

KLASSIK CLAUDE:
 request → model → response (text)

EXTENDED THINKING:
 request → model → response [thinking block + text block]
                            ↑               ↑
                         daxili plan    final cavab
```

### Açar Xüsusiyyətlər

- **Visible thinking**: Claude-in thinking-i **görünür** (OpenAI-dən fərqli). Oxunaqlı İngilis mətni.
- **Budget controlled**: `budget_tokens` parametri ilə maksimum thinking uzunluğu təyin olunur
- **Billed as output**: Thinking tokenlər output rate-i ilə hesablanır
- **Cache-compatible**: Prompt caching thinking-lə birlikdə işləyir
- **Tool-compatible**: Tool use ilə birgə işləyir (interleaved thinking)
- **Signed**: Thinking blokları imza ilə gəlir — multi-turn konteksdə qorunmalıdır

### Niyə Bu Vacibdir

- Debug: modelin niyə bu cavabı verdiyini görmək
- Audit: regulated sahələrdə reasoning trace saxlamaq
- Quality: mürəkkəb tapşırıqlarda dəqiqlik 20-50% artır
- Trust: istifadəçi üçün transparency verə bilər

---

## API Mexanikası

Extended thinking Messages API-ın bir parametri-dir. Heç bir endpoint fərqi yoxdur.

### Dəstəklənən Modellər (2026 əvvəli)

```
claude-opus-4-7-20260115         ← extended thinking mövcud
claude-sonnet-4-6-20251215       ← extended thinking mövcud
claude-haiku-4-5-20251012        ← hələ mövcud deyil (model-dən asılı)
```

Rəsmi sənədə bax — versiyalar yenilənir.

### Minimum Config

```json
{
  "model": "claude-sonnet-4-6",
  "max_tokens": 16000,
  "thinking": {
    "type": "enabled",
    "budget_tokens": 8000
  },
  "messages": [
    {"role": "user", "content": "..."}
  ]
}
```

### Məcburi Qaydalar

1. **`max_tokens > budget_tokens`** olmalıdır (thinking + cavab üçün toplam yer)
2. **`budget_tokens ≥ 1024`** (minimum)
3. **`temperature`, `top_p`, `top_k`** məhduddur — thinking zamanı dəyişmir
4. **`system` mesajı dəstəklənir** (OpenAI o-series-dən fərqli)

---

## Request Sxemi

Tam sxem:

```json
{
  "model": "claude-opus-4-7",
  "max_tokens": 24000,
  "thinking": {
    "type": "enabled",
    "budget_tokens": 16000
  },
  "system": "Sən senior Laravel developersən.",
  "messages": [
    {
      "role": "user",
      "content": "Bu SQL query-ni analiz et və optimize et: SELECT..."
    }
  ],
  "temperature": 1.0,
  "tools": [...],
  "metadata": {
    "user_id": "user_123"
  }
}
```

### Parametr Detalları

| Parametr | Vacibdir? | Dəyər | Qeyd |
|---|---|---|---|
| `thinking.type` | bəli | `"enabled"` və ya `"disabled"` | Disabled = classic mode |
| `thinking.budget_tokens` | bəli | int (≥1024) | Maksimum thinking uzunluğu |
| `max_tokens` | bəli | int (> budget) | Thinking + cavab üçün toplam |
| `temperature` | xeyr | 0-1 | Yalnız cavab mərhələsinə təsir edir |

### Dinamik Budget

Sorğunun real complexity-ni əvvəlcədən bilmək çətindir. Adaptive strategy:

```php
$budget = $this->estimateBudget($prompt);

$response = $claude->messages()->create([
    'model' => 'claude-sonnet-4-6',
    'max_tokens' => $budget + 4000, // cavab üçün buffer
    'thinking' => [
        'type' => 'enabled',
        'budget_tokens' => $budget,
    ],
    // ...
]);
```

---

## Response Strukturu

Thinking aktiv olduqda, `content` massivi bir neçə blok növü ehtiva edə bilər:

```json
{
  "id": "msg_01ABCxyz",
  "type": "message",
  "role": "assistant",
  "model": "claude-opus-4-7",
  "content": [
    {
      "type": "thinking",
      "thinking": "Bu query-də JOIN sırası məhsuldarlığa təsir edir...\nYoxlayım: əgər users cədvəli 1M sətirdən azdırsa...\n\nNəticə: index əlavə edilməli, HAVING-i WHERE-ə köçürmək lazım.",
      "signature": "EuYBCkQYAiJAlAGgEm3xzSKw..."
    },
    {
      "type": "text",
      "text": "Query-ni aşağıdakı kimi optimize edin:\n\n```sql\nSELECT ..."
    }
  ],
  "stop_reason": "end_turn",
  "stop_sequence": null,
  "usage": {
    "input_tokens": 412,
    "cache_creation_input_tokens": 0,
    "cache_read_input_tokens": 0,
    "output_tokens": 2834,
    "thinking_tokens": 2103
  }
}
```

### Blok Növləri

- `thinking` — daxili mühakimə (İngilis mətni, `signature` ilə)
- `text` — final cavab
- `tool_use` — tool çağırışı (agent-də, thinking-dən sonra)
- `redacted_thinking` — müxtəlif content policy səbəbindən silinmiş thinking

### Usage Alanı

`usage` blokunda indi əlavə sahə: `thinking_tokens`. Bu, cavabın output_tokens-inə daxildir — ayrı deyil. Hesablama:

```
output_tokens = thinking_tokens + final_answer_tokens
```

Billing üçün `output_tokens`-dan istifadə et.

---

## Budget Tokens — Sizing Heuristics

### Tapşırığa Görə Təxminlər

| Tapşırıq Növü | Tövsiyə Budget |
|---|---|
| Sadə sual-cavab | **Thinking istifadə etmə** |
| 2-3 addımlı analiz | 2000-4000 |
| Kod review (orta həcm) | 6000-10000 |
| SQL optimization / root-cause | 8000-12000 |
| Mikroservis arxitektura planı | 16000-24000 |
| Olimpik riyaziyyat / LeetCode hard | 32000-64000 |
| Frontier research | 64000+ |

### Adaptive Strategy

Heuristic təxminlər üçün classifier istifadə et:

```php
namespace App\Services\LLM;

class ThinkingBudgetEstimator
{
    public function estimate(string $prompt, ?array $context = null): int
    {
        $signals = 0;

        // Uzun prompt
        if (str_word_count($prompt) > 100) $signals++;
        if (str_word_count($prompt) > 500) $signals++;

        // Kod blokları
        $codeBlocks = substr_count($prompt, '```');
        if ($codeBlocks >= 2) $signals++;
        if ($codeBlocks >= 4) $signals++;

        // Reasoning keywords
        $reasoningWords = [
            'analyze', 'compare', 'optimize', 'design', 'architect',
            'plan', 'debug', 'refactor', 'tradeoff',
            'analiz', 'müqayisə', 'optimize', 'dizayn', 'plan',
        ];
        foreach ($reasoningWords as $word) {
            if (stripos($prompt, $word) !== false) {
                $signals++;
                break;
            }
        }

        // Math/logic
        if (preg_match('/(prove|theorem|equation|integral|derivative)/i', $prompt)) {
            $signals += 2;
        }

        // Multi-question
        $signals += min(2, substr_count($prompt, '?') - 1);

        return match (true) {
            $signals <= 1 => 2000,
            $signals <= 3 => 6000,
            $signals <= 5 => 12000,
            $signals <= 7 => 24000,
            default       => 48000,
        };
    }
}
```

### Empirical Tuning

Production data ilə tune et:

```php
// Her request-dən sonra log et
LLMCall::create([
    'prompt_tokens' => $usage->input_tokens,
    'thinking_tokens' => $usage->thinking_tokens,
    'budget_used_ratio' => $usage->thinking_tokens / $budget,
    'quality_score' => null, // sonradan rated
]);

// Retrospective analysis:
// Tapşırıq növünə görə orta budget istifadəsini tap
// - Əgər həmişə <50% istifadə olunursa → budget-i azalt
// - Əgər tez-tez 95%+-a çatırsa → budget-i artır
```

---

## Billing — Thinking Tokenlər Necə Hesablanır

### Əsas Qayda

**Thinking tokenlər output rate ilə hesablanır.** Claude Sonnet 4.6 üçün (2026 təxmini):

```
Input:           $3 / M token
Output:          $15 / M token
Cache Write:     $3.75 / M token
Cache Read:      $0.30 / M token

Thinking = Output rate ($15 / M)
```

### Cost Hesablama

```
Request: 500 input + 6000 thinking + 800 cavab
Cost:
  Input:    500 × $3 / 1M = $0.0015
  Output:   (6000 + 800) × $15 / 1M = $0.102
  Total:    ~$0.104
```

Klassik ekvivalent (eyni input/output, thinking=0):

```
Request: 500 input + 800 cavab
Cost:
  Input:  $0.0015
  Output: $0.012
  Total:  $0.0135
```

Fərq: ~7-8x bahalı. Amma keyfiyyət fərqi bunu bəzi tapşırıqlarda məntiqli edir.

### Prompt Caching ilə Kombinasiya

Prompt caching thinking request-lərində tam işləyir:

```
System prompt 4000 tok (cache'd) + 500 tok input + 6000 thinking + 800 cavab
  Input (cache read): 4000 × $0.30/M = $0.0012
  Input (new):        500 × $3/M = $0.0015
  Output:             6800 × $15/M = $0.102
  Total:              ~$0.105
```

System prompt böyükdürsə (10000+ token), cache ilə qənaət çox böyükdür.

### Batch API ilə

Batch API (10-batch-api.md) extended thinking-i dəstəkləyir və 50% endirim verir:

```
Standard request: $0.104
Batch: $0.052 (50% endirim)
```

Real-time tələb olmayan tapşırıqlar üçün tövsiyə olunur.

---

## Redacted Thinking Blokları

Bəzən Anthropic thinking-in bir hissəsini avtomatik silir. Səbəblər:
- Content policy riskləri
- Security / safety tetikləyiciləri
- Məhrəmlik (model öz çəkisini açıqlamağa yaxınlaşırsa)

Response-də belə görünür:

```json
{
  "content": [
    {
      "type": "redacted_thinking",
      "data": "encrypted_opaque_string_xyz..."
    },
    {
      "type": "text",
      "text": "..."
    }
  ]
}
```

### Necə İdarə Et

```php
foreach ($response->content as $block) {
    match ($block->type) {
        'thinking' => $this->logThinking($block->thinking, $block->signature),
        'redacted_thinking' => $this->logRedacted($block->data),
        'text' => $this->returnToUser($block->text),
        default => null,
    };
}
```

Multi-turn konteksdə redacted bloku **geri göndər** — Anthropic-ə signaldır ki, context-i tanımır. Yoxsa response dəyişə bilər.

### Redacted-in Mənası

- Model zərərli məzmuna yaxınlaşıb və dayanmağı öyrənib
- Amma final cavab (text) hələ də adekvat ola bilər
- Developer-ə tam thinking göstərmir — təhlükəsizlik

Production-da redacted rate-ini izlə. Yüksək rate → prompt injection cəhdi ola bilər.

---

## Streaming SSE Events

Extended thinking ilə streaming response fərqli event-lər ehtiva edir:

### Event Ardıcıllığı

```
1. event: message_start
   data: {"type": "message_start", "message": {...}}

2. event: content_block_start
   data: {"type": "content_block_start", "index": 0,
          "content_block": {"type": "thinking", "thinking": "", "signature": ""}}

3. event: content_block_delta (çoxlu)
   data: {"type": "content_block_delta", "index": 0,
          "delta": {"type": "thinking_delta", "thinking": "Bu problemi..."}}

4. event: content_block_delta (signature üçün)
   data: {"type": "content_block_delta", "index": 0,
          "delta": {"type": "signature_delta", "signature": "abc..."}}

5. event: content_block_stop
   data: {"type": "content_block_stop", "index": 0}

6. event: content_block_start
   data: {"type": "content_block_start", "index": 1,
          "content_block": {"type": "text", "text": ""}}

7. event: content_block_delta (çoxlu)
   data: {"type": "content_block_delta", "index": 1,
          "delta": {"type": "text_delta", "text": "Cavab: ..."}}

8. event: content_block_stop
   data: {"type": "content_block_stop", "index": 1}

9. event: message_delta
   data: {"type": "message_delta", "delta": {"stop_reason": "end_turn"}}

10. event: message_stop
    data: {"type": "message_stop"}
```

### Delta Növləri

- `thinking_delta` — thinking text chunk
- `signature_delta` — signature byte-lar
- `text_delta` — cavab text chunk
- `input_json_delta` — tool input (agent mode)

### Parse Nümunəsi (PHP)

```php
foreach ($stream as $event) {
    $type = $event->type;

    if ($type === 'content_block_delta') {
        $delta = $event->delta;

        match ($delta->type) {
            'thinking_delta' => $this->onThinking($delta->thinking),
            'text_delta'     => $this->onText($delta->text),
            'signature_delta' => $this->onSignature($delta->signature),
            default => null,
        };
    }
}
```

---

## Interleaved Thinking + Tool Use

Claude 4.5+ modellərdə thinking **tool call-lar arasında** baş verə bilər:

```
User: "San Francisco-da bu gün hava necədir və şəkli göstər"
  ↓
Model thinks: "Hava API-dən götürməli, sonra şəkil aramalı"
  ↓
Tool call: get_weather(location="SF")
  ↓
Tool result: { temp: 65F, condition: "sunny" }
  ↓
Model thinks: "İndi şəkil tapım — 'sunny SF' axtarım"
  ↓
Tool call: search_image(query="sunny San Francisco")
  ↓
Tool result: { url: "..." }
  ↓
Model thinks: "İndi final cavabı yaradım"
  ↓
Final text: "San Francisco-da bu gün 65F və günəşlidir. [şəkil]"
```

Hər tool çağırışı arasında yeni thinking bloku. Bu, complex agent-lər üçün kritikdir — model hər addımdan sonra planını revise edə bilir.

### Interleaved Header

Bu davranış default-dur (Claude 4.5+-də). Açıq şəkildə aktivləşdirmək lazımdırsa beta header:

```
anthropic-beta: interleaved-thinking-2025-05-14
```

(Header dəqiq tarixini rəsmi sənəddən yoxla).

### Multi-turn Konteks

Interleaved mode-da hər thinking bloku "signature"-a sahibdir. Sonrakı turn-da:

```
assistant message #1:
  thinking_block_1 (signature: "sig_a")
  tool_use_1
  
user message (tool_result):
  tool_result_1
  
assistant message #2:
  thinking_block_2 (signature: "sig_b") ← yeni thinking
  text_block
```

**Signature-ları qoru** — növbəti sorğuda assistant mesajını təkrarladığında signature-lar olmalıdır. Əks halda context inkonsist olur.

---

## Signature və Context Saxlama

Hər thinking blokunun `signature` sahəsi var — kriptoqrafik imza. Məqsəd:

1. Anthropic thinking-in həqiqi olduğunu təsdiq edir (tampering qarşısını alır)
2. Multi-turn conversation-da context inteqrasiyası

### Saxlamaq Lazımdır

```php
// Turn 1 response
$assistantMessage = [
    'role' => 'assistant',
    'content' => [
        ['type' => 'thinking', 'thinking' => '...', 'signature' => 'sig_1'],
        ['type' => 'text', 'text' => '...'],
    ],
];

// Turn 2 request-də bu mesajı saxla
$response2 = $claude->messages()->create([
    'messages' => [
        $userMessage1,
        $assistantMessage,  // ← thinking blokları + imzaları ilə birlikdə
        $userMessage2,
    ],
    'thinking' => ['type' => 'enabled', 'budget_tokens' => 8000],
]);
```

Əgər thinking blokunu silmiş olsan, Anthropic API 400 xətası qaytarır (context integrity).

### Laravel-də Conversation Storage

```php
Schema::create('conversations', function (Blueprint $t) {
    $t->id();
    $t->foreignId('user_id');
    $t->jsonb('messages'); // full message array including thinking blocks
    $t->timestamps();
});

// Store
$conv->messages = $this->appendAssistantMessage($conv->messages, $response);
$conv->save();
```

---

## Laravel Wrapper Class

Production-ready extended thinking service:

```php
<?php

namespace App\Services\LLM;

use Anthropic\Anthropic;
use App\Exceptions\ThinkingBudgetExceededException;
use Illuminate\Support\Facades\Log;

class ExtendedThinkingService
{
    public function __construct(
        private Anthropic $claude,
        private ThinkingBudgetEstimator $estimator,
        private ThinkingLogger $logger,
    ) {}

    /**
     * Thinking ilə sorğu göndər.
     *
     * @param string $prompt İstifadəçi mesajı
     * @param string|null $systemPrompt Optional system
     * @param int|null $budgetTokens Override - əgər null, auto-estimate
     * @param string $model Modelin adı
     * @return ThinkingResponse
     */
    public function ask(
        string $prompt,
        ?string $systemPrompt = null,
        ?int $budgetTokens = null,
        string $model = 'claude-sonnet-4-6',
    ): ThinkingResponse {
        $budget = $budgetTokens ?? $this->estimator->estimate($prompt);
        $maxTokens = $budget + 4096;

        $request = [
            'model' => $model,
            'max_tokens' => $maxTokens,
            'thinking' => [
                'type' => 'enabled',
                'budget_tokens' => $budget,
            ],
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ];

        if ($systemPrompt !== null) {
            $request['system'] = $systemPrompt;
        }

        try {
            $response = $this->claude->messages()->create($request);
        } catch (\Throwable $e) {
            Log::error('Extended thinking failed', [
                'error' => $e->getMessage(),
                'budget' => $budget,
                'model' => $model,
            ]);
            throw $e;
        }

        $thinking = $this->extractThinking($response);
        $text = $this->extractText($response);

        $this->logger->log(
            prompt: $prompt,
            thinking: $thinking,
            answer: $text,
            budget: $budget,
            usage: $response->usage,
        );

        if ($response->stop_reason === 'max_tokens') {
            throw new ThinkingBudgetExceededException(
                "Budget {$budget} insufficient, model stopped before completing"
            );
        }

        return new ThinkingResponse(
            thinking: $thinking,
            redactedThinkingBlocks: $this->extractRedacted($response),
            text: $text,
            rawResponse: $response,
        );
    }

    private function extractThinking($response): string
    {
        $parts = [];
        foreach ($response->content as $block) {
            if ($block->type === 'thinking') {
                $parts[] = $block->thinking;
            }
        }
        return implode("\n---\n", $parts);
    }

    private function extractText($response): string
    {
        $parts = [];
        foreach ($response->content as $block) {
            if ($block->type === 'text') {
                $parts[] = $block->text;
            }
        }
        return implode('', $parts);
    }

    private function extractRedacted($response): array
    {
        $blocks = [];
        foreach ($response->content as $block) {
            if ($block->type === 'redacted_thinking') {
                $blocks[] = $block->data;
            }
        }
        return $blocks;
    }
}
```

### Response DTO

```php
<?php

namespace App\Services\LLM;

final class ThinkingResponse
{
    public function __construct(
        public readonly string $thinking,
        public readonly array $redactedThinkingBlocks,
        public readonly string $text,
        public readonly mixed $rawResponse,
    ) {}

    public function thinkingTokens(): int
    {
        return $this->rawResponse->usage->thinking_tokens ?? 0;
    }

    public function totalOutputTokens(): int
    {
        return $this->rawResponse->usage->output_tokens ?? 0;
    }
}
```

---

## Retry və Fallback Strategiyaları

Thinking request-ləri uzun və bahalıdır. Retry strategiyası klassikdən fərqli olmalıdır.

### Timeout Konfiqurasiyası

```php
// .env
ANTHROPIC_TIMEOUT_SECONDS=300  // 5 dəqiqə

// Guzzle client config
$client = new \GuzzleHttp\Client([
    'timeout' => env('ANTHROPIC_TIMEOUT_SECONDS', 300),
    'connect_timeout' => 10,
]);
```

### Retry Logic

```php
namespace App\Services\LLM;

use Anthropic\Exceptions\RateLimitException;
use Anthropic\Exceptions\APIException;

class ResilientThinkingService
{
    public function askWithRetry(
        string $prompt,
        int $maxAttempts = 3,
    ): ThinkingResponse {
        $attempt = 0;
        $lastException = null;
        $budget = $this->estimator->estimate($prompt);

        while ($attempt < $maxAttempts) {
            $attempt++;

            try {
                return $this->service->ask($prompt, budgetTokens: $budget);
            } catch (ThinkingBudgetExceededException $e) {
                // Budget artır və yenidən cəhd et
                $budget = (int) ($budget * 1.5);
                Log::warning("Budget exceeded, retrying with {$budget}");
            } catch (RateLimitException $e) {
                // Exponential backoff
                $delay = min(60, 2 ** $attempt);
                sleep($delay);
                $lastException = $e;
            } catch (APIException $e) {
                if ($e->getCode() >= 500) {
                    // Server error — retry
                    sleep(2 ** $attempt);
                    $lastException = $e;
                } else {
                    throw $e; // Client error — retry etmə
                }
            }
        }

        throw $lastException ?? new \RuntimeException('Thinking request failed after retries');
    }
}
```

### Fallback-lar

Thinking çox uzanarsa / fail edirsə:

```php
try {
    return $thinkingService->ask($prompt, budgetTokens: 16000);
} catch (ThinkingBudgetExceededException | TimeoutException $e) {
    // Fallback 1: daha aşağı budget
    try {
        return $thinkingService->ask($prompt, budgetTokens: 4000);
    } catch (\Throwable $e2) {
        // Fallback 2: classic model
        return $classicService->ask($prompt);
    }
}
```

### Queue-də İstifadə

Thinking uzun çəkdiyi üçün HTTP request-də icra etmək riskli ola bilər. Queue-yə keçir:

```php
namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;

class ExtendedThinkingJob implements ShouldQueue
{
    public int $timeout = 600; // 10 dəqiqə
    public int $tries = 2;
    public int $backoff = 30;

    public function __construct(
        public int $conversationId,
        public string $prompt,
        public int $budgetTokens,
    ) {}

    public function handle(ExtendedThinkingService $service): void
    {
        $response = $service->ask(
            prompt: $this->prompt,
            budgetTokens: $this->budgetTokens,
        );

        // Sonucu WebSocket/SSE ilə UI-a ötür
        ConversationReply::create([
            'conversation_id' => $this->conversationId,
            'thinking' => $response->thinking,
            'text' => $response->text,
        ]);

        broadcast(new ReplyReady($this->conversationId, $response->text));
    }
}
```

---

## Summarization Thinking Uzun Agent-lər üçün

Uzun agentic session-larda (çoxlu tool call, 20+ addım) thinking blokları kontekstdə toplanır və baha olur.

### Problem

```
Session turn 1-10: hər birində 3000 tok thinking
Cumulative thinking in context: 30.000 token
Turn 11-də bu kontekst re-process olunmalıdır → yüksək input cost
```

### Həll: Thinking Summarization

Periodic olaraq köhnə thinking blokları **xülasə edilərək** əvəz et:

```php
namespace App\Services\LLM;

class AgentContextCompactor
{
    private const COMPACT_THRESHOLD = 10; // Hər 10 turn-dan sonra

    public function compactIfNeeded(array $messages): array
    {
        $assistantTurns = $this->countAssistantTurns($messages);

        if ($assistantTurns < self::COMPACT_THRESHOLD) {
            return $messages;
        }

        // İlk N turn-u thinking-siz summarize et
        $oldTurns = array_slice($messages, 0, -4);  // son 4-ü saxla
        $recentTurns = array_slice($messages, -4);

        $summary = $this->summarizeTurns($oldTurns);

        return [
            ['role' => 'user', 'content' => "Previous context summary:\n{$summary}"],
            ...$recentTurns,
        ];
    }

    private function summarizeTurns(array $turns): string
    {
        // Thinking-siz ucuz model çağırışı
        $response = app(\Anthropic\Anthropic::class)->messages()->create([
            'model' => 'claude-haiku-4-5',
            'max_tokens' => 1000,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => 'Summarize the following conversation turns into a brief '
                                . 'context summary. Focus on decisions made, tools used, '
                                . 'and current state. Drop intermediate thinking.'
                                . "\n\n" . json_encode($turns),
                ],
            ],
        ]);

        return $response->content[0]->text;
    }

    private function countAssistantTurns(array $messages): int
    {
        return collect($messages)->where('role', 'assistant')->count();
    }
}
```

### Prompt Caching ilə Optimallaşdırma

Köhnə thinking blokları **cache-ə yaz**:

```php
// İlk 10 turn kontekstini cache et
$messages = [
    ...$oldTurns, // bu hissə cache-lənir
    ['role' => 'user', 'content' => [
        ['type' => 'text', 'text' => 'Summary checkpoint'],
        ['cache_control' => ['type' => 'ephemeral']],
    ]],
    ...$recentTurns,
];
```

Bu, input cost-u 90% azaldır.

---

## Anti-Pattern-lər və Güvənlik

### 1. Thinking-i İstifadəçiyə Göstərmək

Thinking daxili mühakimədir — bəzən private məlumat, axtarış nəticəsi, yaxud model öz limitlərini açıqlayan cümlə ehtiva edir. **İstifadəçiyə xam şəkildə göstərmə.** 

Debug UI-da göstər:
- Yalnız admin/dev mühitdə
- Logged-in engineer üçün
- Feature flag arxasında

### 2. Thinking-ə İstifadəçi İnput-u Enjeksiyon

Thinking tokenləri **istifadəçinin birbaşa nəzarəti altında olmamalıdır**. Əks halda prompt injection thinking-i kompromisə sala bilər. Sistem prompt-unda:

```
ANTİ-PATTERN:
 "Bu user data-nı analiz et və thinking-də göstər: <user_data>"

DÜZGÜN:
 "Bu user data-nı analiz et: <user_data_tagged_as_untrusted>"
```

Claude 4+ untrusted content-in içində olan instructions-ı daha yaxşı ignore edir, amma yenə ehtiyatlı ol. 10-prompt-injection-defenses.md-ə bax.

### 3. PII-ni Thinking-də Saxlamaq

Əgər prompt-da PII varsa (milli ID, maliyyə data), thinking də onu ehtiva edə bilər. GDPR / CCPA tələbləri üçün:
- Thinking-i log edəndə PII redact et
- Production-da thinking-i qısamüddətli saxla
- Database-də şifrə altında saxla

### 4. Thinking Budget-i Infinitə Çəkmək

`budget_tokens: 500000` qoymaq — model birdən-birə 500k token düşünməyəcək, amma həddən artıq yüksək budget model performansını azalda bilər (over-thinking). Max 128k ağıllı limitdir.

### 5. Thinking-i Trust Mənbəyi Kimi Qəbul Etmək

Model thinking-də "Mən 100% dəqiq hesabladım" desə də, final cavab səhv ola bilər. Thinking ≠ verification. Sensitive output-u yenə validate et.

### 6. Budget-i Statik Hard-code Etmək

Hər müxtəlif tapşırıqda fərqli budget lazımdır. Estimator istifadə et və ya user-ə "effort" slider ver.

### 7. Stream-də Thinking-i Block Etmək

Bəzi developerlər "thinking bitmədən response göstərmirəm" yazır. Amma thinking 30s çəkir — UX pis olur. **Thinking stream et** ("düşünür..." indicator ilə), sonra final cavabı stream et.

### 8. Thinking-siz Retry Etmək

Error zamanı thinking-i söndürmək hər zaman düzgün deyil. Bəzi tapşırıqlar thinking olmadan fail edir — classic-ə fallback-ı konservativ et.

### 9. Signature-ları Atmaq

Multi-turn-da thinking blokunu saxlayıb signature-ı silmək = API 400 xətası. Tam bloku saxla.

### 10. Redacted Thinking-i Ignore Etmək

`redacted_thinking` blokları response-də olarsa, sonrakı turn-də mütləq geri qayıtsın. Əks halda context inkonsist, cavab degradasiya ola bilər.

---

## Observability və Debug

### Metrikalar

Prometheus / DataDog-a çıxar:

```
anthropic_thinking_tokens_total{model}         (counter)
anthropic_thinking_budget_utilization{model}   (histogram, 0-1)
anthropic_redacted_thinking_total{model}       (counter)
anthropic_thinking_cost_usd_total{model}       (counter)
anthropic_stop_reason{model, reason}           (counter)
anthropic_thinking_latency_seconds{model}      (histogram)
```

### Logging

Hər request-də tam kontekst sakla:

```php
Log::channel('claude')->info('extended_thinking', [
    'trace_id' => $traceId,
    'user_id' => $userId,
    'model' => $model,
    'prompt_hash' => hash('xxh3', $prompt),
    'budget_tokens' => $budget,
    'used_thinking_tokens' => $response->usage->thinking_tokens,
    'used_output_tokens' => $response->usage->output_tokens,
    'budget_utilization' => $response->usage->thinking_tokens / $budget,
    'stop_reason' => $response->stop_reason,
    'duration_ms' => $durationMs,
    'thinking_length_chars' => strlen($thinking),
    'has_redacted' => count($redactedBlocks) > 0,
]);
```

### Debug UI

Developer üçün inner-dashboard:

```
┌─ Conversation #1234 ──────────────────────────┐
│ User: "Optimize this SQL..."                  │
│                                               │
│ [Thinking] (3248 tok, budget: 8000, 40%)      │
│  Bu query-də JOIN sırası məhsuldarlığa...     │
│                                               │
│ [Answer] (520 tok)                            │
│  Aşağıdakı kimi optimize edin...              │
│                                               │
│ Stop: end_turn | Duration: 12.4s              │
│ Cost: $0.058                                  │
└───────────────────────────────────────────────┘
```

---

## Migration Guide (Classic → Thinking)

### 1. Identify Candidate Tapşırıqlar

Bütün prod prompt-lara thinking əlavə etmə. Candidates:
- Complexity signals: code analysis, planning, math
- Error rate yüksək olan tapşırıqlar
- Hallucination risk olan tapşırıqlar

### 2. A/B Test

Feature flag ilə tapşırıq növündə 10% trafiki thinking-ə yönəlt:

```php
if (config('features.thinking_enabled_tasks')[$taskType] ?? false) {
    if (random_int(1, 100) <= 10) {
        return $this->withThinking($prompt);
    }
}

return $this->classic($prompt);
```

### 3. Quality Comparison

Evaluation set üzərində hər iki variantı run et:
- Score (gold standard vs generated)
- Latency p50/p95
- Cost per request
- User satisfaction (thumbs up/down)

### 4. Gradual Rollout

```
Week 1: 10% thinking
Week 2: 30%
Week 3: 70%
Week 4: 100% (əgər metrikalar yaxşıdırsa)
```

### 5. Rollback Mexanizmi

Feature flag-i 0%-ə endir, heç bir kod dəyişikliyi olmadan classic-ə qayıt:

```php
// Environment variable ilə açıl/söndür
THINKING_ENABLED=false  // kill switch
```

---

## Qərar Çərçivəsi

### Nə Zaman Thinking Açmaq?

```
Prompt yoxla:
  └── Kompleks analiz? (code, data, multi-step) → Aç
  └── Riyazi / logiki? → Aç
  └── Planning? → Aç
  └── Decision-making? → Aç
  └── Sadə lookup / retrieval? → Bağlı
  └── Format transformation? → Bağlı
  └── Template filling? → Bağlı
  └── Creative writing? → İstifadəçi upgrade istəsə aç

Latency tolerance:
  └── <2s → Bağlı
  └── <10s → Kiçik budget (2000)
  └── <60s → Orta budget (8000)
  └── >60s → Böyük budget (16000+)

Cost budget:
  └── High-volume (>100k/day) → Hybrid routing
  └── Low-volume, high-value → Hər yerdə aç
```

### Model Seçimi

| Use Case | Tövsiyə |
|---|---|
| Kod review, SQL optimization | Sonnet 4.6 + budget 8000 |
| Arxitektura planlama | Opus 4.7 + budget 16000 |
| Math / Olimpiada | Opus 4.7 + budget 32000+ |
| Customer support analiz | Sonnet 4.6 + budget 4000 |
| Agentic (tool chain) | Sonnet 4.6 + interleaved |
| Batch processing | Sonnet thinking + Batch API |

### Budget Sizing

```
Minimum effektiv: 1024 (API limit)
Praktiki minimum: 2000
Orta tapşırıq:    8000
Mürəkkəb:         16000
Frontier:         32000+
Israf:            100000+ (əksər hallarda)
```

---

## Xülasə

- Extended thinking — Claude 4+ modellərində, `thinking: { type: "enabled", budget_tokens: N }` parametri
- Thinking tokenlər **output rate** ilə hesablanır, amma prompt caching ilə combine olunur
- Response-də `thinking` bloku visible, `redacted_thinking` bəzən mövcud, hər ikisini multi-turn-da qoru
- Streaming: `thinking_delta` + `text_delta` ayrı event-lər, UI-da "düşünür..." indicator + final cavab
- Interleaved thinking + tool use (Claude 4.5+) agent-lər üçün ən güclü kombinasiyadır
- Budget estimation: static heuristic + empirical tuning; over-budget = israf, under-budget = truncation
- Production: Queue Job + 5-10 dəq timeout, retry with budget increase, classic fallback
- Anti-pattern: user-visible thinking, infinite budget, PII-i thinking-də saxlamaq, signature-ları atmaq
- Migration: feature flag + A/B test + gradual rollout
- Observability: budget utilization, cost, latency metrikalarını izləyin

---

*Növbəti: [16 — Vision və PDF Support](./06-vision-pdf-support.md)*
