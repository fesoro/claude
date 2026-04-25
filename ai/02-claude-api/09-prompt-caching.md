# Prompt Caching (Senior)

## Problem: Eyni Tokenləri Dəfələrlə Ödəmək

Claude-a edilən hər API çağırışı göndərdiyiniz hər tokeni — sistem prompt, tool tərifləri, söhbət tarixi — sıfırdan yenidən emal edir. 4000 tokenlik sistem promptunuz varsa və gündə 10.000 API çağırışı edirsinizsə, heç vaxt dəyişməyən 40 milyon giriş tokenini emal etmək üçün pul ödəyirsiniz. Claude Sonnet üçün milyon token başına $3 hesabı ilə bu, israf edilən hesablama üçün gündə $120 deməkdir.

Prompt caching bu problemi həll edir: Anthropic infrastrukturuna prompt prefiksinin işlənmiş təsvirini cache-ə almasına və sorğular arasında yenidən istifadə etməsinə imkan verir. Cache oxuma adi giriş qiymətinin 10%-nə başa gəlir — 90% endirim. Cache yazma adi giriş qiymətinin 125%-nə başa gəlir (bir dəfəlik), sonra hər oxuma ucuz olur.

---

## Daxili İş Prinsipi

Claude bir prompt emal edəndə hər tokeni transformerin diqqət qatlarından keçirir və generasiya zamanı həmin tokenlərə diqqət etmək üçün lazım olan ara aktivləşmələr olan **KV cache** (açar-dəyər cache) yaradır. Adətən bu KV cache sorğudan sonra silinir.

Prompt caching aktiv olduqda, Anthropic bu KV cache vəziyyətini serverlərinde (GPU-nun yanındakı sürətli SRAM-da) **5 dəqiqə** saxlayır. Eyni cache prefiksi ilə növbəti sorğuda, həmin tokenlərin üzərindən diqqəti yenidən hesablamaq əvəzinə, sistem saxlanılmış KV cache-i geri götürür və cache-in bitdiyi yerdən davam edir.

Tələb: **cache edilə bilən prefiks sorğular arasında bayt-bayta eyni olmalıdır**. Cache edilmiş hissədə tək simvol belə fərqlilик cache-i etibarsız edir.

### Nə Cache Edilə Bilər

- Sistem promptları (ən çox yayılmış istifadə halı)
- Tool tərifləri (böyük sxemlər)
- Messages massivindəki statik az-nümunəli misallar
- İstifadəçinin sualından əvvəl yerləşdirilmiş uzun sənəd konteksti

### Nə Cache Edilə Bilməz

- Ən son istifadəçi mesajı (hər sorğuda dəyişir)
- `cache_control` nöqtəsindən sonrakı hər hansı məzmun
- Modelin generasiya etdiyi cavablar

---

## `cache_control` Nöqtə Sintaksisi

Cache ediləcəyin harada bitəcəyini, cache etmək istədiyiniz son bloğa `cache_control` obyekti əlavə etməklə işarələyirsiniz:

```json
{
  "type": "text",
  "text": "...uzun sistem promptunuz...",
  "cache_control": { "type": "ephemeral" }
}
```

`"ephemeral"` hazırda yeganə `type`-dır — bu, "5 dəqiqə cache et, hər oxuma zamanı TTL-i sıfırla" deməkdir. Ad bir qədər yanıldıcıdır; Anthropic gələcəkdə daha uzun TTL-lər əlavə edəcək.

### Sistem Promptlarında Cache Nöqtələri

```json
{
  "system": [
    {
      "type": "text",
      "text": "Siz ekspert Laravel developersiniiz...\n[4000 tokenlik təlimatlar]",
      "cache_control": { "type": "ephemeral" }
    }
  ],
  "messages": [
    { "role": "user", "content": "Service provider necə yazılır?" }
  ]
}
```

### Çoxlu Nöqtələr

Sorğu başına **4-ə qədər** `cache_control` nöqtəsi ola bilər. Bu, qatmarlı caching imkan verir:

```json
{
  "system": [
    {
      "type": "text",
      "text": "[Statik şirkət konteksti — 3000 token]",
      "cache_control": { "type": "ephemeral" }   // nöqtə 1
    }
  ],
  "messages": [
    {
      "role": "user",
      "content": [
        {
          "type": "text",
          "text": "[Analiz edilən 10.000 tokenlik sənəd]",
          "cache_control": { "type": "ephemeral" }   // nöqtə 2
        },
        {
          "type": "text",
          "text": "Bu sənəddəki əsas riskləri ümumiləşdirin."
        }
      ]
    }
  ]
}
```

Nöqtə 1 sistem promptunu cache edir. Nöqtə 2 sənədi cache edir. Son sual heç vaxt cache edilmir.

### Minimum Cache Edilə Bilən Ölçü

Caching-in minimum token həddi var:

| Model ailəsi | Cache üçün minimum token |
|---|---|
| Claude 3.5, Claude 3 Opus | 1.024 token |
| Claude 3 Haiku | 2.048 token |

Daha az token üçün cache etməyə cəhd etmək sadəcə heç bir effekt vermir — xəta yox, yalnız caching yoxdur.

---

## Qiymət Bölgüsü

| Token tipi | Claude Sonnet 3.5 qiyməti | Nisbi xərc |
|---|---|---|
| Normal giriş | $3,00 / MTok | 100% |
| Cache yazma | $3,75 / MTok | 125% |
| Cache oxuma | $0,30 / MTok | 10% |
| Çıxış | $15,00 / MTok | — |

### Zərər-Mənfəət Analizi

Cache yazma adi girişdən 25% bahalıdır. Zərərsizkəsişmə nöqtəsinə çatmaq üçün ən azı **2 cache oxuma** lazımdır, sonra əhəmiyyətli qənaət başlayır:

| Yazma başına cache oxuma | Ümumi xərc (caching olmadan müqayisədə) | Qənaət |
|---|---|---|
| 1 | $4,05 vs $3,00 | -35% (daha pis!) |
| 2 | $4,35 vs $6,00 | 27% ucuz |
| 10 | $6,75 vs $30,00 | 77% ucuz |
| 100 | $33,75 vs $300,00 | 88,75% ucuz |
| 1000 | $303,75 vs $3000,00 | 89,875% ucuz |

**5 dəqiqəlik TTL o deməkdir ki, faydalanmaq üçün 5 dəqiqə ərzində ən azı 2 sorğu lazımdır.** Yüksək axınlı istehsal sistemləri (sistem promptunu paylaşan çox istifadəçi) böyük fayda əldə edir.

---

## Cache Etibarsızlaşdırma

Cache aşağıdakı hallarda avtomatik olaraq etibarsızlaşır:

1. **5 dəqiqə** həmin cache prefiksinə toxunan sorğu olmadan keçir.
2. **Prefiks məzmunu dəyişir** — hətta bir bayt fərqliliyi yeni cache girişi yaradır.
3. **Model dəyişir** — hər model versiyasının öz cache ad sahəsi var.

Manual cache etibarsızlaşdırma API-si yoxdur. Cache girişini erkən silmək mümkün deyil. Yeganə mexanizm onun vaxtının bitmesini gözləmək və ya məzmunu dəyişdirməkdir.

### Dolaylı Etibarsızlaşdırma: Söhbət Tarixi Problemi

Çox dönüşlü söhbətdə, hər sorğuya bütün tarixi sadəcə əlavə etmək, assistanın cavabı əlavə edilmiş mesajlar birinci `cache_control` nöqtəsindən sonrakı prefiksin dəyişdiyini bildirir, çünki söhbət tarixinin artması prefiksi dəyişdirir.

Həll yolu: yalnız stabil məzmunu cache edin (sistem promptu, toollar, böyük statik sənədlər) və böyüyən söhbət tarixinə heç vaxt `cache_control` qoymayin.

---

## Laravel İmplementasiyası

### 1. CachedPromptBuilder

```php
<?php

declare(strict_types=1);

namespace App\Services\AI\Caching;

/**
 * Düzgün cache_control nöqtələri ilə Anthropic API sorğu massivləri qurur.
 *
 * İstifadə:
 *   $builder = new CachedPromptBuilder();
 *   $builder->setSystemPrompt($largeSystemPrompt);
 *   $builder->addTools($toolDefinitions);
 *   $builder->addStaticContext($documentText);
 *   $payload = $builder->buildPayload($conversationMessages);
 */
final class CachedPromptBuilder
{
    private string $systemPrompt = '';

    /** @var array<int, array<string, mixed>> */
    private array $tools = [];

    /** @var array<int, string> */
    private array $staticContextBlocks = [];

    private bool $cacheSystemPrompt    = true;
    private bool $cacheTools           = true;
    private bool $cacheStaticContext   = true;

    public function setSystemPrompt(string $prompt, bool $cache = true): self
    {
        $this->systemPrompt      = $prompt;
        $this->cacheSystemPrompt = $cache;
        return $this;
    }

    /**
     * @param  array<int, array<string, mixed>>  $tools  Anthropic tool tərif obyektləri
     */
    public function setTools(array $tools, bool $cache = true): self
    {
        $this->tools       = $tools;
        $this->cacheTools  = $cache;
        return $this;
    }

    /**
     * Cache edilməli böyük statik sənəd və ya kontekst bloku əlavə et.
     */
    public function addStaticContext(string $text): self
    {
        $this->staticContextBlocks[] = $text;
        return $this;
    }

    /**
     * Anthropic-ə göndərilməyə hazır tam API payload massivi qur.
     *
     * @param  array<int, array{role: string, content: string|array}>  $messages
     * @param  array<string, mixed>  $extraOptions  məs. ['max_tokens' => 4096]
     * @return array<string, mixed>
     */
    public function buildPayload(array $messages, array $extraOptions = []): array
    {
        $payload = array_merge([
            'model'      => config('services.anthropic.model', 'claude-opus-4-5'),
            'max_tokens' => 4096,
        ], $extraOptions);

        // cache_control üçün tələb olunan məzmun blokları massivi kimi sistem promptu
        if ($this->systemPrompt !== '') {
            $systemBlock = ['type' => 'text', 'text' => $this->systemPrompt];

            if ($this->cacheSystemPrompt) {
                $systemBlock['cache_control'] = ['type' => 'ephemeral'];
            }

            $payload['system'] = [$systemBlock];
        }

        // Son tool-da cache işarəsi olan toollar
        if ($this->tools !== []) {
            $tools = $this->tools;

            if ($this->cacheTools) {
                // cache_control son tool tərifi üzərinə qoyulur
                $lastIndex = array_key_last($tools);
                $tools[$lastIndex]['cache_control'] = ['type' => 'ephemeral'];
            }

            $payload['tools'] = $tools;
        }

        // Statik konteksti ilk istifadəçi mesaj məzmun blokları kimi inject et
        if ($this->staticContextBlocks !== []) {
            $contextBlocks = [];

            foreach ($this->staticContextBlocks as $i => $text) {
                $block = ['type' => 'text', 'text' => $text];

                // Son statik bloku cache et (bütün kontekst daxil olduqdan sonra)
                if ($this->cacheStaticContext && $i === array_key_last($this->staticContextBlocks)) {
                    $block['cache_control'] = ['type' => 'ephemeral'];
                }

                $contextBlocks[] = $block;
            }

            // Kontekst bloklarını ilk istifadəçi mesajının önünə əlavə et
            if (! empty($messages)) {
                $firstMessage = $messages[0];

                if (is_string($firstMessage['content'])) {
                    $firstMessage['content'] = array_merge($contextBlocks, [
                        ['type' => 'text', 'text' => $firstMessage['content']],
                    ]);
                } elseif (is_array($firstMessage['content'])) {
                    $firstMessage['content'] = array_merge($contextBlocks, $firstMessage['content']);
                }

                $messages[0] = $firstMessage;
            }
        }

        $payload['messages'] = $messages;

        return $payload;
    }
}
```

### 2. CachedConversation — Sistem Promptu + Toollar Cache Edilir, Tarix Cache Edilmir

```php
<?php

declare(strict_types=1);

namespace App\Services\AI\Caching;

use App\Services\AI\AnthropicClient;
use Illuminate\Support\Facades\Log;

/**
 * Sistem promptunu və toolları cache edən, lakin böyüyən mesaj tarixini
 * heç vaxt cache etməyən çox dönüşlü söhbət.
 *
 * Arxitektura qərarı: Yalnız statik "giriş" cache üçün işarələnir.
 * Mesaj tarixi dinamikdir və hər dönüşdə dəyişir, ona görə cache_control
 * qoymaq hər sorğuda yeni cache yazması yaradır — bahalı və israf.
 */
final class CachedConversation
{
    /** @var array<int, array{role: string, content: string}> */
    private array $messages = [];

    private readonly CachedPromptBuilder $builder;

    public function __construct(
        private readonly AnthropicClient $client,
        string $systemPrompt,
        array $tools = [],
    ) {
        $this->builder = (new CachedPromptBuilder())
            ->setSystemPrompt($systemPrompt, cache: true)
            ->setTools($tools, cache: $tools !== []);
    }

    /**
     * İstifadəçi mesajı göndər və assistant cavabını al.
     * Hər ikisini söhbət tarixinə əlavə edir.
     */
    public function chat(string $userMessage): string
    {
        $this->messages[] = ['role' => 'user', 'content' => $userMessage];

        $payload  = $this->builder->buildPayload($this->messages);
        $response = $this->client->messages($payload);

        $this->logCacheStats($response);

        $assistantText = $this->extractText($response);
        $this->messages[] = ['role' => 'assistant', 'content' => $assistantText];

        return $assistantText;
    }

    /**
     * Söhbət tarixini sıfırla (lakin cache edilmiş sistem promptu/toolları saxla).
     */
    public function reset(): void
    {
        $this->messages = [];
    }

    /** @return array<int, array{role: string, content: string}> */
    public function getHistory(): array
    {
        return $this->messages;
    }

    private function extractText(array $response): string
    {
        foreach ($response['content'] ?? [] as $block) {
            if ($block['type'] === 'text') {
                return $block['text'];
            }
        }
        return '';
    }

    private function logCacheStats(array $response): void
    {
        $usage = $response['usage'] ?? [];

        Log::info('Anthropic cache statistikası', [
            'input_tokens'          => $usage['input_tokens']               ?? 0,
            'cache_creation_tokens' => $usage['cache_creation_input_tokens'] ?? 0,
            'cache_read_tokens'     => $usage['cache_read_input_tokens']     ?? 0,
            'output_tokens'         => $usage['output_tokens']               ?? 0,
        ]);

        // Cache hit gözlənilən, lakin alınmadıqda xəbərdarlıq et
        if (
            ($usage['cache_read_input_tokens'] ?? 0) === 0
            && ($usage['cache_creation_input_tokens'] ?? 0) === 0
            && count($this->messages) > 2
        ) {
            Log::warning('Prompt cache hit gözlənildi, lakin nə oxuma nə yazma alındı — TTL və ya prefiks uyğunsuzluğunu yoxlayın');
        }
    }
}
```

### 3. Cache Hit Dərəcəsinin İzlənməsi

```php
<?php

declare(strict_types=1);

namespace App\Services\AI\Caching;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Cache hit/miss dərəcələrini izləyir və prompt caching-dən xərc qənaətini hesablayır.
 */
final class CacheMonitor
{
    // Milyon token başına qiymətlər (Claude Sonnet 3.5, 2025 etibarilə)
    private const NORMAL_INPUT_PRICE   = 3.00;
    private const CACHE_WRITE_PRICE    = 3.75;
    private const CACHE_READ_PRICE     = 0.30;

    private const STATS_KEY            = 'anthropic_cache_stats';
    private const STATS_TTL            = 86400 * 30; // 30 gün

    public function record(array $usage, string $context = 'default'): void
    {
        $stats = Cache::get(self::STATS_KEY, $this->emptyStats());

        $stats[$context]['requests']++;
        $stats[$context]['input_tokens']            += $usage['input_tokens']                ?? 0;
        $stats[$context]['cache_creation_tokens']   += $usage['cache_creation_input_tokens'] ?? 0;
        $stats[$context]['cache_read_tokens']        += $usage['cache_read_input_tokens']     ?? 0;
        $stats[$context]['output_tokens']           += $usage['output_tokens']               ?? 0;

        if (($usage['cache_read_input_tokens'] ?? 0) > 0) {
            $stats[$context]['cache_hits']++;
        } elseif (($usage['cache_creation_input_tokens'] ?? 0) > 0) {
            $stats[$context]['cache_writes']++;
        } else {
            $stats[$context]['cache_misses']++;
        }

        Cache::put(self::STATS_KEY, $stats, self::STATS_TTL);
    }

    /**
     * @return array<string, mixed>
     */
    public function getReport(string $context = 'default'): array
    {
        $stats = Cache::get(self::STATS_KEY, [])[$context] ?? $this->emptyStats()[$context];

        $totalRequests = $stats['requests'];
        if ($totalRequests === 0) {
            return ['error' => 'Heç bir data qeydə alınmayıb'];
        }

        // Caching olmadan xərc (bütün tokenlər adi qiymətlə hesablanır)
        $totalInputTokens = $stats['input_tokens']
            + $stats['cache_creation_tokens']
            + $stats['cache_read_tokens'];

        $costWithoutCache = ($totalInputTokens / 1_000_000) * self::NORMAL_INPUT_PRICE;

        // Caching ilə faktiki xərc
        $actualCost = (($stats['input_tokens']          / 1_000_000) * self::NORMAL_INPUT_PRICE)
                    + (($stats['cache_creation_tokens']  / 1_000_000) * self::CACHE_WRITE_PRICE)
                    + (($stats['cache_read_tokens']       / 1_000_000) * self::CACHE_READ_PRICE);

        $hitRate = $totalRequests > 0
            ? round(($stats['cache_hits'] / $totalRequests) * 100, 2)
            : 0;

        return [
            'context'                => $context,
            'total_requests'         => $totalRequests,
            'cache_hit_rate_pct'     => $hitRate,
            'cache_hits'             => $stats['cache_hits'],
            'cache_writes'           => $stats['cache_writes'],
            'cache_misses'           => $stats['cache_misses'],
            'tokens' => [
                'normal_input'   => $stats['input_tokens'],
                'cache_writes'   => $stats['cache_creation_tokens'],
                'cache_reads'    => $stats['cache_read_tokens'],
                'output'         => $stats['output_tokens'],
            ],
            'cost' => [
                'actual_usd'          => round($actualCost, 4),
                'without_cache_usd'   => round($costWithoutCache, 4),
                'saved_usd'           => round($costWithoutCache - $actualCost, 4),
                'savings_pct'         => $costWithoutCache > 0
                    ? round((($costWithoutCache - $actualCost) / $costWithoutCache) * 100, 2)
                    : 0,
            ],
        ];
    }

    /** @return array<string, array<string, int>> */
    private function emptyStats(): array
    {
        return [
            'default' => [
                'requests'               => 0,
                'cache_hits'             => 0,
                'cache_writes'           => 0,
                'cache_misses'           => 0,
                'input_tokens'           => 0,
                'cache_creation_tokens'  => 0,
                'cache_read_tokens'      => 0,
                'output_tokens'          => 0,
            ],
        ];
    }
}
```

### 4. AnthropicClient (minimal, istinad üçün)

```php
<?php

declare(strict_types=1);

namespace App\Services\AI;

use GuzzleHttp\Client;
use RuntimeException;

final class AnthropicClient
{
    private readonly Client $http;

    public function __construct()
    {
        $this->http = new Client([
            'base_uri' => 'https://api.anthropic.com/v1/',
            'headers'  => [
                'x-api-key'         => config('services.anthropic.key'),
                'anthropic-version' => '2023-06-01',
                'anthropic-beta'    => 'prompt-caching-2024-07-31',
                'content-type'      => 'application/json',
            ],
            'timeout'  => 120,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function messages(array $payload): array
    {
        $response = $this->http->post('messages', ['json' => $payload]);
        $body     = json_decode($response->getBody()->getContents(), true, flags: JSON_THROW_ON_ERROR);

        if (isset($body['error'])) {
            throw new RuntimeException("Anthropic xətası: {$body['error']['message']}");
        }

        return $body;
    }
}
```

`anthropic-beta: prompt-caching-2024-07-31` başlığına diqqət edin — prompt caching-i aktiv etmək üçün **bu vacibdir**. Onsuz `cache_control` sahələri səssizcə nəzərə alınmır.

---

## Qabaqcıl Nümunələr

### Nümunə 1: Cache Edilmiş Kontekstlə Sənəd Sual-Cavabı

```php
<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Services\AI\Caching\CachedPromptBuilder;
use App\Services\AI\AnthropicClient;

/**
 * Çoxlu suallarla böyük sənədi analiz edir.
 * Sənəd ilk sualdan sonra cache edilir — sonrakı suallar
 *50.000 token yenidən göndərmək əvəzinə cache-dən istifadə edir.
 */
final class DocumentAnalyzer
{
    public function __construct(
        private readonly AnthropicClient $client,
    ) {}

    /**
     * @param  string[]  $questions
     * @return array<string, string>  sual => cavab xəritəsi
     */
    public function analyzeWithQuestions(string $documentText, array $questions): array
    {
        $answers = [];

        $systemPrompt = <<<'PROMPT'
        Siz dəqiq sənəd analitikissiniz. Sualları yalnız təqdim olunan sənəd əsasında cavablandırın.
        Cavab sənəddə yoxdursa, "Sənəddə tapılmadı" deyin.
        PROMPT;

        foreach ($questions as $question) {
            $builder = (new CachedPromptBuilder())
                ->setSystemPrompt($systemPrompt, cache: true)
                ->addStaticContext($documentText);

            $payload = $builder->buildPayload([
                ['role' => 'user', 'content' => $question],
            ]);

            $response      = $this->client->messages($payload);
            $answers[$question] = $this->extractText($response);

            // Cache performansını log et — ilk çağırış = yazma, sonrakılar = oxuma
            $usage = $response['usage'] ?? [];
            logger()->info('Sənəd Q&A cache', [
                'question_preview'   => substr($question, 0, 50),
                'cache_read_tokens'  => $usage['cache_read_input_tokens'] ?? 0,
                'cache_write_tokens' => $usage['cache_creation_input_tokens'] ?? 0,
            ]);
        }

        return $answers;
    }

    private function extractText(array $response): string
    {
        foreach ($response['content'] ?? [] as $block) {
            if ($block['type'] === 'text') {
                return $block['text'];
            }
        }
        return '';
    }
}
```

### Nümunə 2: Agentlərdə Tool Təriflərini Cache Etmək

Tool tərifləri çox vaxt 1.000–5.000 token tutур. Agentiniz dövrə vuranda (tapşırığı tamamlamaq üçün çoxlu toolları çağıranda), hər iterasiyada bu tərifləri yenidən göndərirsiniz. Onları cache edin:

```php
$tools = [
    [
        'name'        => 'search_database',
        'description' => 'SQL-ə bənzər filtrlər ilə məhsul verilənlər bazasını sorğulayın...',
        'input_schema' => [
            'type'       => 'object',
            'properties' => [
                'query'  => ['type' => 'string', 'description' => 'Axtarış sorğusu'],
                'limit'  => ['type' => 'integer', 'description' => 'Maks nəticə', 'default' => 10],
                'filters'=> ['type' => 'object', 'description' => 'Sahə filtrləri'],
            ],
            'required' => ['query'],
        ],
    ],
    [
        'name'         => 'send_email',
        'description'  => 'Müştəriyə email göndərin...',
        'input_schema' => [ /* ... */ ],
        // Son tool-da cache_control ondan əvvəl təyin olunan bütün toolları da cache edir
        'cache_control' => ['type' => 'ephemeral'],
    ],
];
```

### Nümunə 3: Versiyalı Sistem Promptları

Sistem promptunu yeniləyəndə köhnə cache girişi tərk edilir (5 dəqiqə sonra vaxtı bitəcək). Yeniləmənin heç bir xərci yoxdur — sadəcə növbəti sorğuda cache yazması ödəyirsiniz.

Hansı versiyasının cache edildiyini izləmək üçün versiya şərhi daxil edin:

```php
$systemPrompt = "<!-- v{$this->promptVersion} -->\n" . $this->baseSystemPrompt;
```

Bu, loglarda cache hit-in cari prompt versiyasına uyğun olub-olmadığını asanlıqla müəyyən etməyə imkan verir.

---

## Prompt Caching-i Nə Vaxt İşlətməmək Lazımdır

1. **Az həcmli tətbiqlər** — hər 5 dəqiqəlik pəncərədə 2-dən az sorğu edərsinizsə, qazandığınızdan çox ödəyirsiniz.
2. **Yüksək fərdiləşdirilmiş promptlar** — hər istifadəçinin unikal sistem promptu varsa, cache ediləcək heç bir təkrarlanan prefiks yoxdur.
3. **Tez-tez dəyişən kontekst** — hər dəqiqə dəyişən sistem promptu heç vaxt cache oxuma hit-i almayacaq.
4. **Çox qısa promptlar** — 1.024 tokendən az olan promptlar minimum həddin altındadır.

---

## Cache Davranışının Sazlanması

Cavab `usage` obyekti sizə tam olaraq nə baş verdiyini bildirir:

```json
{
  "usage": {
    "input_tokens": 12,
    "cache_creation_input_tokens": 4523,
    "cache_read_input_tokens": 0,
    "output_tokens": 215
  }
}
```

- `cache_creation_input_tokens > 0` → cache miss, yeni giriş yazıldı. 125% ödəyirsiniz.
- `cache_read_input_tokens > 0` → cache hit. 10% ödəyirsiniz.
- İkisi də 0-dır → caching aktiv deyil (beta başlığı yoxdur və ya məzmun minimum ölçüdən azdır).

```php
// Diaqnostik köməkçi
public function diagnoseCache(array $usage): string
{
    $write = $usage['cache_creation_input_tokens'] ?? 0;
    $read  = $usage['cache_read_input_tokens']      ?? 0;
    $normal = $usage['input_tokens']                ?? 0;

    if ($write > 0) {
        return "CACHE YAZMA: {$write} token cache edildi. Növbəti sorğu oxuma olacaq.";
    }

    if ($read > 0) {
        $savings = round(($read * 0.0000027), 4); // Sonnet qiymətlərinde təxmini qənaət
        return "CACHE HIT: {$read} token cache-dən oxundu. ~\${$savings} qənaət edildi.";
    }

    return "CACHE YOX: {$normal} adi giriş tokeni. Beta başlığını və minimum ölçünü yoxlayın.";
}
```
