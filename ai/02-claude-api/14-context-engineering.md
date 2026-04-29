# Context Engineering: Kontekst Pəncərəsini Sistemli İdarəetmə (Senior)

## İcmal

Context engineering — LLM sorğularının məzmununu, strukturunu və sırasını sistemli şəkildə optimallaşdırma fənnidir. Prompt engineering "nə demək"i öyrədirsə, context engineering "necə qablaşdırmaq"ı öyrədir: hansı məlumat, hansı formatda, hansı yerdə kontekstdə olmalıdır.

---

## Niyə Vacibdir

```
Pis prompt engineering:
  "Mənə kömək et. Sual: {user_question}"
  
Yaxşı prompt engineering:
  "Sən senior PHP developer-ləri üçün texniki köməkçisən..."

Context engineering problemi:
  200K token kontekstə sahib modeldə performans xətti düşür
  Yanlış yerləşdirilmiş məlumat görünmür
  Sistem promptu vs istifadəçi mesajı — model hər birinə fərqli etibar edir
```

Real sistemdə kontekst pəncərəsi həmişə limitlidir. Necə doldurduğunuz:
- Model cavab keyfiyyətini birbaşa təsir edir
- Prompt caching xərclərini müəyyən edir
- Hallucination riskini artırır və ya azaldır

---

## Əsas Anlayışlar

### Kontekst Növləri

```
┌────────────────────────────────────────────────────┐
│ System Prompt (Model üçün "sabit təlimat")          │
│  - Rol, persona, qaydalar                           │
│  - Sabit qalır → cache-lənir (90% xərc azalır)    │
├────────────────────────────────────────────────────┤
│ Conversation History (Əvvəlki söhbət)              │
│  - İstifadəçi + köməkçi növbəşi                    │
│  - Böyüyür → trim lazımdır                         │
├────────────────────────────────────────────────────┤
│ Retrieved Context (RAG nəticəsi)                   │
│  - Sorğuya uyğun sənəd parçaları                   │
│  - Hər sorğuda dəyişir                             │
├────────────────────────────────────────────────────┤
│ User Message (Cari istifadəçi girişi)              │
│  - Ən yeni — modelin diqqəti buradadadır           │
└────────────────────────────────────────────────────┘
```

### "Lost in the Middle" Effekti

Stanford tədqiqatı (2023) göstərir ki, LLM-lər kontekstdə orta yerdəki məlumatı daha az yaxşı xatırlayır:

```
Kontekst pəncərəsi:
  [BAŞLANĞIC] ← Model yaxşı xatırlayır     ✓✓✓
  [ORTA]      ← Model tez-tez keçir        ✗
  [SON]       ← Model yaxşı xatırlayır     ✓✓✓

Nəticə: Vacib məlumatı başa ya da sonuna qoyun.
```

### XML Teqləri ilə Struktur

Claude XML teqləri ilə işləmək üçün xüsusi olaraq öyrədilmişdir. Bu teqlər Claude-a "bu məlumat bu kateqoriyaya aiddir" mesajını verir:

```xml
<system>
Sən senior PHP/Laravel developerlərinə kömək edən texniki köməkçisən.
Azərbaycan dilindədir, texniki terminlər ingilis dilindədir.
</system>

<context>
<document id="1" source="laravel-docs">
Laravel queue worker hər 60 saniyədən bir health check edir...
</document>
<document id="2" source="stackoverflow">
Redis connection pool size-i artırmaq üçün...
</document>
</context>

<user_question>
Queue worker-in asinxron restart edilməsini necə idarə etmək olar?
</user_question>
```

**XML teqsiz vs teqli müqayisə:**
- Teqsiz: Model mətn bloklarını necə ayıracağını bilmir
- Teqli: Açıq struktur → daha dəqiq cavab, sitat göstərmə daha kolay

---

## Praktik Baxış

### 1. Sistem Promptunun Strukturu

**Yaxşı sistem promptu:**
```
[Rol] + [Kontext] + [Məhdudiyyətlər] + [Format]
```

```php
$systemPrompt = <<<SYSTEM
Sən {$company->name} şirkəti üçün daxili AI köməkçisən.

ROLUUN:
- Senior PHP/Laravel developer-lərinə texniki kömək göstərmək
- Şirkətin daxili kodlama standartlarına uyğun cavab vermək
- Azərbaycan dilindədir, texniki terminlər ingilis dilindədir

MƏHDUDİYYƏTLƏR:
- Şirkətin gizli məlumatlarını (müştəri məlumatları, API açarları) heç vaxt paylaşma
- Yalnız daxili sənədlərdən öyrəndiklərini cavab ver — əmin olmadıqda bildir
- Kod nümunəsi verərkən həmişə işlək, test edilə bilən kod ver

FORMAT:
- Qısa kod nümunəsi: inline `kod` istifadə et
- Uzun kod: ```php bloku
- Addım-addım izahat: nömrəli siyahı
SYSTEM;
```

**Anti-pattern — çox uzun, struktursuz sistem promptu:**
```
// Pis: 5000 tokenlik sıx mətn bloku
"Sən AI köməkçisisən. Laravel, PHP, Redis, MySQL, Docker, Kubernetes,
AWS, GCP, Azure, microservices, DDD, CQRS, event sourcing, Kafka,
RabbitMQ, WebSocket, GraphQL, REST API, OAuth, JWT, SAML, OpenID
haqqında məlumat verə bilərsən. Həmişə Azərbaycanca cavab ver amma
texniki terminlər ingilis olsun. Kod nümunəsi ver. Test yaz.
Sənədləşdirmə et..."
```

### 2. Kontekst Sıxışdırma (Compression)

Uzun söhbətlərdə köhnə mesajları xülasəyə sıxışdırın:

```php
<?php
// app/Services/AI/ConversationCompressor.php

namespace App\Services\AI;

class ConversationCompressor
{
    private const TOKEN_THRESHOLD = 150_000; // 200K kontekstdən 150K-da sıxışdır

    public function __construct(
        private readonly ClaudeService $claude,
        private readonly TokenCounter  $tokenCounter,
    ) {}

    public function compressIfNeeded(array $messages): array
    {
        $totalTokens = $this->tokenCounter->countMessages($messages);

        if ($totalTokens <= self::TOKEN_THRESHOLD) {
            return $messages;
        }

        // Son 10 mesajı saxla, qalanını xülasə et
        $recentMessages = array_slice($messages, -10);
        $oldMessages    = array_slice($messages, 0, count($messages) - 10);

        if (empty($oldMessages)) {
            return $messages;
        }

        $summary = $this->summarizeOldMessages($oldMessages);

        return array_merge(
            [['role' => 'user',      'content' => "Söhbət xülasəsi:\n{$summary}"]],
            [['role' => 'assistant', 'content' => "Anladım, davam edək."]],
            $recentMessages,
        );
    }

    private function summarizeOldMessages(array $messages): string
    {
        $formatted = collect($messages)
            ->map(fn($m) => "{$m['role']}: {$m['content']}")
            ->implode("\n\n");

        return $this->claude->messages(
            messages: [[
                'role'    => 'user',
                'content' => "Bu söhbətin əsas məqamlarını 200 sözlə xülasə et:\n\n{$formatted}",
            ]],
            model: 'claude-haiku-4-5', // Ucuz model xülasə üçün
        );
    }
}
```

### 3. RAG Kontekstinin Yerləşdirilməsi

Retrieved context-i **sistem promptundan sonra, istifadəçi mesajından əvvəl** yerləşdirin:

```php
<?php
// Yanlış — retrieved context son istifadəçi mesajının içindədir
$messages = [
    ['role' => 'user', 'content' => "Sual: {$question}\n\nBuraya əlaqəli sənədlər: {$bigContext}"],
];

// Düzgün — ayrı "context" bloku kimi
private function buildMessages(string $question, array $retrievedDocs): array
{
    $contextBlock = $this->formatContext($retrievedDocs);

    return [
        [
            'role'    => 'user',
            'content' => [
                [
                    'type' => 'text',
                    'text' => $contextBlock,
                ],
                [
                    'type' => 'text',
                    'text' => $question,
                ],
            ],
        ],
    ];
}

private function formatContext(array $docs): string
{
    if (empty($docs)) {
        return '';
    }

    $formatted = collect($docs)
        ->map(fn($doc, $i) => <<<XML
        <document index="{$i}" source="{$doc['source']}" relevance="{$doc['score']}">
        {$doc['content']}
        </document>
        XML)
        ->implode("\n");

    return "<context>\n{$formatted}\n</context>";
}
```

### 4. Prefilling ilə Cavab Yönəltmə

Claude-un cavabına başlamadan əvvəl assistant mesajını dolduraraq istiqaməti müəyyən edə bilərsiniz:

```php
<?php
// Prefilling — cavabı JSON formatına məcbur etmək
$messages = [
    ['role' => 'user',      'content' => "Bu review-u analiz et: {$reviewText}"],
    ['role' => 'assistant', 'content' => '{"sentiment": "'],  // ← Prefill: JSON başlamaq üçün
];

$response = $claude->messages(
    messages: $messages,
    // Model '{"sentiment": "' ilə davam edəcək
);
// Nəticə: '{"sentiment": "positive", "score": 0.87, ...}'
```

**Prefilling istifadə halları:**
- JSON formatının zəmanəti (structured output ilə müqayisədə daha ucuz)
- Cavabın `##` ilə başlamasını təmin etmək (markdown format)
- Çoxdilli sistemdə dil seçimi

### 5. Token Budget İdarəsi

```php
<?php
// app/Services/AI/TokenBudgetManager.php

namespace App\Services\AI;

class TokenBudgetManager
{
    private const MODEL_LIMITS = [
        'claude-opus-4-7'   => 200_000,
        'claude-sonnet-4-6' => 200_000,
        'claude-haiku-4-5'  => 200_000,
    ];

    private const OUTPUT_RESERVE = 4_096; // Cavab üçün ayrılmış token

    public function __construct(
        private readonly TokenCounter $counter,
    ) {}

    /**
     * Verilmiş komponentlər üçün token büdcəsini bölüşdür.
     * Sistematik olaraq en az vacib komponentleri kəsir.
     */
    public function allocate(
        string $model,
        string $systemPrompt,
        array  $history,
        string $userMessage,
        array  $retrievedDocs,
    ): array {
        $limit = self::MODEL_LIMITS[$model] ?? 200_000;
        $budget = $limit - self::OUTPUT_RESERVE;

        // Sabit komponentlər (kəsilə bilməz)
        $systemTokens = $this->counter->count($systemPrompt);
        $userTokens   = $this->counter->count($userMessage);
        $fixedTotal   = $systemTokens + $userTokens;

        if ($fixedTotal > $budget) {
            throw new \RuntimeException("Sistem prompt + istifadəçi mesajı limiti aşır");
        }

        $remaining = $budget - $fixedTotal;

        // Retrieved docs-u əldə olunan yerə uyğunlaşdır
        $docsTokens = $this->counter->countDocs($retrievedDocs);
        $historyTokens = $this->counter->countMessages($history);

        // Əvvəlcə docs, sonra tarix
        $docsAllocation    = min($docsTokens, (int)($remaining * 0.6));
        $historyAllocation = min($historyTokens, $remaining - $docsAllocation);

        return [
            'system'  => $systemPrompt,
            'history' => $this->trimHistory($history, $historyAllocation),
            'docs'    => $this->trimDocs($retrievedDocs, $docsAllocation),
            'user'    => $userMessage,
        ];
    }

    private function trimHistory(array $history, int $budget): array
    {
        // Ən köhnə cütləri sil, limitə çatana qədər
        while ($history && $this->counter->countMessages($history) > $budget) {
            array_splice($history, 0, 2); // İstifadəçi + köməkçi cütü
        }
        return $history;
    }

    private function trimDocs(array $docs, int $budget): array
    {
        // Ən az uyğun sənədləri sil (docs relevance score-a görə sıralanıb)
        $trimmed = [];
        $used    = 0;

        foreach ($docs as $doc) {
            $docTokens = $this->counter->count($doc['content']);
            if ($used + $docTokens > $budget) {
                break;
            }
            $trimmed[] = $doc;
            $used += $docTokens;
        }

        return $trimmed;
    }
}
```

### 6. Prompt Caching üçün Kontekst Dizaynı

Prompt caching maksimum faydası üçün konteksti belə strukturlaşdırın:

```
[Sabit kontekst — cache-lənir]
├── Sistem promptu (rol, qaydalar)
├── Şirkətin siyasət sənədləri (nadir dəyişir)
└── Statik bilik bazası
↑ CACHE BOUNDARY ↑
[Dəyişən kontekst — cache-lənmir]
├── İstifadəçiyə xas tarix
├── Bu sorğu üçün retrieved docs
└── Cari istifadəçi mesajı
```

```php
<?php
private function buildCacheableMessages(
    string $systemPrompt,    // Cache-lənir
    string $policyDocs,      // Cache-lənir (nadir dəyişir)
    array  $history,         // Cache-lənmir
    array  $retrievedDocs,   // Cache-lənmir
    string $userMessage,     // Cache-lənmir
): array {
    // Cache breakpoint: sabit+dəyişən arasında
    return [
        [
            'role'    => 'user',
            'content' => [
                // Cache-lənəcək bloklar (əvvəldə)
                [
                    'type'          => 'text',
                    'text'          => $policyDocs,
                    'cache_control' => ['type' => 'ephemeral'], // 5 dəq cache
                ],
                // Cache-lənməyəcək bloklar (sonda)
                [
                    'type' => 'text',
                    'text' => $this->formatContext($retrievedDocs),
                ],
                [
                    'type' => 'text',
                    'text' => $userMessage,
                ],
            ],
        ],
    ];
}
```

---

## Trade-off-lar

| Qərar | Üstünlük | Çatışmazlıq |
|---|---|---|
| Uzun sistem promptu | Daha dəqiq rol, daha az hallucination | Daha çox token, caching zəruri olur |
| XML struktur | Aydın sınırlar, model daha yaxşı anlar | Biraz daha çox token |
| Söhbət sıxışdırma | Token limitinə uyğun | Köhnə kontekst itirilə bilər |
| Prefilling | Format zəmanəti, daha ucuz | JSON möhkəmlənmiş deyil — hələ yoxlayın |
| Əvvəlcə retrieved docs | Model vacib məlumatı əvvəlcə görür | "Lost in middle" effekti |

---

## Common Mistakes

**1. Sistem promptunda hər şeyi sıralamaq:**
```
// Pis — 15 müxtəlif tapşırıq üçün bir sistem promptu
"Sen email yaz, kod debug et, sözlük ol, mühasibat yoxla, ..."

// Yaxşı — hər tapşırıq üçün ayrı sistem promptu
$systemPrompt = $task === 'email' ? $emailPrompt : $codePrompt;
```

**2. Retrieved context-i trim etməmək:**
```php
// Pis — 50 sənəd ayırd etmədən göndərmək
$docs = $retriever->retrieve($query, limit: 50);

// Yaxşı — token büdcəsinə uyğun sınırla
$docs = $retriever->retrieve($query, limit: 5);
```

**3. Vacib məlumatı ortaya qoymaq:**
```
// Pis — kritik məhdudiyyəti ortada gizlətmək
"... uzun bir sistem promptu ..."
"Heç vaxt müştəri məlumatını paylaşma"  ← Ortada itirir
"... daha çox məzmun ..."

// Yaxşı — başa və ya sona
"KRITIK: Heç vaxt müştəri məlumatını paylaşma.
... sistem promptunun qalan hissəsi ..."
```

**4. Prefilling ilə JSON-u güvənmək:**
```php
// Risikli — JSON həmişə düzgün olmaya bilər
$messages[] = ['role' => 'assistant', 'content' => '{"result":'];
$raw = $claude->messages($messages);
$data = json_decode('{"result":' . $raw); // Parse xətası riski

// Güvənli — əvvəl emal et, yoxla
try {
    $data = json_decode('{"result":' . $raw, associative: true, flags: JSON_THROW_ON_ERROR);
} catch (\JsonException $e) {
    // Retry və ya fallback
}
```

---

## Nümunələr

### Tam Laravel Context Engineering Pipeline

```php
<?php
// app/Services/AI/ContextEngineeringService.php

namespace App\Services\AI;

class ContextEngineeringService
{
    public function __construct(
        private readonly ClaudeService          $claude,
        private readonly TokenBudgetManager     $budgetManager,
        private readonly ConversationCompressor $compressor,
        private readonly PromptCacheService     $cacheService,
    ) {}

    public function chat(
        string $userMessage,
        int    $tenantId,
        int    $conversationId,
        array  $retrievedDocs = [],
    ): string {
        // 1. Sistem promptunu yarat (cache-lənəcək)
        $systemPrompt = $this->buildSystemPrompt($tenantId);

        // 2. Söhbət tarixini yüklə
        $history = $this->loadHistory($conversationId);

        // 3. Token büdcəsini bölüşdür
        $allocated = $this->budgetManager->allocate(
            model: 'claude-sonnet-4-6',
            systemPrompt: $systemPrompt,
            history: $history,
            userMessage: $userMessage,
            retrievedDocs: $retrievedDocs,
        );

        // 4. Mesaj massivini qur
        $messages = $this->buildMessages(
            history:      $allocated['history'],
            docs:         $allocated['docs'],
            userMessage:  $allocated['user'],
        );

        // 5. Claude-a göndər
        $response = $this->claude->messages(
            messages:     $messages,
            systemPrompt: $allocated['system'],
            model:        'claude-sonnet-4-6',
        );

        // 6. Tarixə əlavə et
        $this->saveToHistory($conversationId, $userMessage, $response);

        return $response;
    }

    private function buildSystemPrompt(int $tenantId): string
    {
        $tenant = \Cache::remember("tenant:{$tenantId}", 300, fn() =>
            \App\Models\Tenant::with('aiConfig')->find($tenantId)
        );

        return <<<PROMPT
        <role>
        Sən {$tenant->name} şirkəti üçün AI köməkçisən.
        {$tenant->aiConfig->system_instructions}
        </role>

        <constraints>
        - Yalnız müştəri icazəsi olan məlumatlara əsaslan
        - Əmin olmadığında "bilmirəm" de
        - Həmişə Azərbaycanca cavab ver
        </constraints>
        PROMPT;
    }

    private function buildMessages(array $history, array $docs, string $userMessage): array
    {
        // Tarixi emal et
        $result = $history;

        // Son istifadəçi mesajını sənəd + sual kimi əlavə et
        $userContent = [];

        if (!empty($docs)) {
            $formatted = collect($docs)
                ->map(fn($d, $i) => "<source id=\"{$i}\">{$d['content']}</source>")
                ->implode("\n");

            $userContent[] = ['type' => 'text', 'text' => "<context>\n{$formatted}\n</context>"];
        }

        $userContent[] = ['type' => 'text', 'text' => $userMessage];

        $result[] = ['role' => 'user', 'content' => $userContent];

        return $result;
    }
}
```

---

## Praktik Tapşırıqlar

### Tapşırıq 1: Token Sayacı Quraşdırmaq

```php
// Aktual token sayacı (Anthropic SDK-dan)
composer require anthropics/anthropic-sdk-php

// Token sayımı
$response = $client->messages->create([
    'model'      => 'claude-sonnet-4-6',
    'max_tokens' => 1,
    'messages'   => [['role' => 'user', 'content' => 'Hello']],
]);

// usage.input_tokens dəqiq sayı verir
echo $response->usage->input_tokens; // Məs: 12
```

**Tapşırıq:** Öz sisteminizdəki ortalama token istifadəsini ölçün:
- Sistem promptu: ___ token
- Ortalama söhbət tarixçəsi: ___ token
- Ortalama retrieved docs: ___ token
- İstifadəçi mesajı: ___ token
- Cəm: ___ token (200K limitin ___%-i)

### Tapşırıq 2: "Lost in the Middle" Effektini Test Etmək

```php
// 3 fərqli yerləşdirmə üçün faktiki nəticəni müqayisə edin
function testPlacement(ClaudeService $claude, string $keyFact, string $position): string
{
    $filler = str_repeat("Bu söz doldurma mətnidir. ", 1000); // ~2000 token

    $context = match ($position) {
        'beginning' => "{$keyFact}\n\n{$filler}",
        'middle'    => "{$filler}\n\n{$keyFact}\n\n{$filler}",
        'end'       => "{$filler}\n\n{$keyFact}",
    };

    $response = $claude->messages(
        messages: [['role' => 'user', 'content' => "{$context}\n\nYuxarıdakı mövzuya aid əsas faktı söylə."]],
    );

    return $response;
}

$keyFact = "Şirkətin aylıq gəliri 47,382 manata çatıb.";

echo testPlacement($claude, $keyFact, 'beginning');  // Doğru cavab çox güman
echo testPlacement($claude, $keyFact, 'middle');     // Əks-küçük ehtimal
echo testPlacement($claude, $keyFact, 'end');        // Doğru cavab çox güman
```

### Tapşırıq 3: Caching Effektivliyini Ölçmək

```php
// İlk sorğu — cache yoxdur
$start    = microtime(true);
$response = $claude->messages(messages: $messages, systemPrompt: $longSystemPrompt);
$firstLatency = (microtime(true) - $start) * 1000;

// İkinci sorğu — eyni sistem promptu, cache var
$start    = microtime(true);
$response = $claude->messages(messages: $messages2, systemPrompt: $longSystemPrompt);
$cachedLatency = (microtime(true) - $start) * 1000;

echo "İlk sorğu: {$firstLatency}ms\n";
echo "Cache-li sorğu: {$cachedLatency}ms\n";
echo "Xərc fərqi: " . ($response->usage->cache_read_input_tokens > 0 ? "Cache işlədi" : "Cache işləmədi");
// Gözlənilən: cache-li sorğu 30-50% daha sürətli
```

---

## Əlaqəli Mövzular

- [02-prompt-engineering.md](02-prompt-engineering.md) — Prompt mühəndisliyi əsasları
- [09-prompt-caching.md](09-prompt-caching.md) — Prompt caching dərindən
- [03-structured-output.md](03-structured-output.md) — Strukturlaşdırılmış çıxış
- [11-rate-limits-retry-php.md](11-rate-limits-retry-php.md) — Rate limit idarəsi
- [../07-workflows/04-ai-idempotency-circuit-breaker.md](../07-workflows/04-ai-idempotency-circuit-breaker.md) — Circuit breaker pattern
