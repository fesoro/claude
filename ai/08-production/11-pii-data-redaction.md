# PII və Həssas Məlumat Redaction — LLM-ə Göndərməzdən Əvvəl (Lead)

> **Problem**: istifadəçi support ticket-ini Claude-a göndərirsən, ticket-də email, telefon, credit card, VOEN, passport number var. Bu data provayderin serverinə keçir, logs-da görünür, potensial olaraq sonrakı training-ə daxil olur. Redaction **məcburi qat**dır — option yox.

---

## Mündəricat

1. [Nə PII sayılır?](#what)
2. [Niyə Mütləq Redact Etməlisən](#why)
3. [3 Redaction Strategiyası](#strategies)
4. [Azərbaycan-specific Patterns](#az-patterns)
5. [Laravel `RedactionService` Implementasiya](#laravel)
6. [Reversible Tokenization (Placeholder-lər)](#reversible)
7. [Middleware: Avtomatik Redaction](#middleware)
8. [Log-lardan Redaction](#logging)
9. [Prompt Cache ilə Uzlaşdırma](#cache-safe)
10. [Tests: Vektor Korpusu](#tests)
11. [Compliance: GDPR / EU AI Act](#compliance)

---

## Nə PII sayılır? <a name="what"></a>

| Kateqoriya | Nümunələr | Risk |
|-----------|-----------|------|
| **Direct identifiers** | Email, phone, passport №, VOEN, social security | Yüksək |
| **Financial** | Credit card, IBAN, bank account | Kritik |
| **Health** | Diagnosis, medication, patient ID | GDPR special category |
| **Biometric** | Fingerprint hash, face embedding | Special category |
| **Location** | Full address, GPS, IP (in EU context) | Orta |
| **Indirect** | Name + birth date + city (reidentifikasiya) | Orta |
| **Credentials** | Password, API key, session token | Kritik |

### Azərbaycan spesifik

- **Şəxsiyyət vəsiqəsi (FIN)**: 7 rəqəm (məs: `AA1234567`)
- **VÖEN**: 10 rəqəm (məs: `1234567890`)
- **Telefon**: `+994 50 123 45 67` və variantları
- **Kartlar**: AzeriCard, Bolkart, ümumi PAN formatları
- **Bank**: İBAN `AZ21NABZ00000000137010001944`

---

## Niyə Mütləq Redact Etməlisən <a name="why"></a>

### 1. Compliance

- **GDPR**: processing PII third-party-yə (Anthropic US, OpenAI US) transferi risky — DPA lazımdır, user consent
- **EU AI Act**: high-risk sistem kateqoriyası — data minimization məcburi
- **Azərbaycan kibermühasirə qanunvericiliyi**: fərdi məlumat export edilərkən şərtlər

### 2. Log leakage

```php
Log::info('User message', ['content' => $msg]);  // YANLIŞ!
// ... 3 ay sonra log aggregator ElasticSearch-də credit card-ları axtarırsan ...
```

### 3. Prompt injection data exfiltration

Redact edilməmiş sensitive data + compromised tool → hack data çıxarda bilər.

### 4. Model feedback loops

Bəzi provayderlər default "retain for abuse monitoring" edir. Inference-də göndərilən data sonradan onların sistemlərində qalır.

### 5. Support/debugging loglarında leak

Engineering team staging logs-da real customer data görməməlidir.

---

## 3 Redaction Strategiyası <a name="strategies"></a>

### Strategy A: Regex-based (quick, imperfect)

- Regex pattern → `[REDACTED_EMAIL]`, `[REDACTED_PHONE]`
- Sürətli, sadə
- False negatives (Azerbaijani name regex ilə tutulmur)

### Strategy B: NER-based (ML model)

- Presidio (Microsoft), spaCy NER, OpenAI moderation
- Named entities tanıyır — names, locations, organizations
- False positive-lər var ("Baku" yer olaraq tanınır — lakin company-də Baku street adı var)
- Latency yüksək (50-200ms)

### Strategy C: Reversible tokenization (production choice)

- PII → placeholder `<EMAIL_1>`
- Placeholder + real value mapping-i server tərəfində saxla
- LLM cavab qaytarır, placeholder-ləri real value-yə restore et
- Ən təhlükəsiz, amma complexity yüksək

### Qərar matris

| Scenario | Tövsiyə |
|----------|---------|
| MVP, non-sensitive use-case | Regex + allow list |
| Production B2C support | Regex + NER + reversible tokens |
| Compliance-heavy (health, finance) | Reversible tokens + server-side redaction log |
| High-volume batch (log processing) | NER self-hosted (Presidio) |

---

## Azərbaycan-specific Patterns <a name="az-patterns"></a>

```php
// app/Services/Redaction/AzerbaijaniPatterns.php

class AzerbaijaniPatterns
{
    public const PATTERNS = [
        // Email
        'EMAIL' => '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}\b/',

        // Azerbaijani phone: +994 50 123 45 67, 0501234567, +994501234567, 050-123-45-67
        'PHONE_AZ' => '/(?:\+994|0)[\s\-]?(50|51|55|70|77|99|10|12)[\s\-]?\d{3}[\s\-]?\d{2}[\s\-]?\d{2}/',

        // International phone
        'PHONE_INTL' => '/\+?\d{1,3}[\s\-]?\(?\d{3}\)?[\s\-]?\d{3}[\s\-]?\d{4}/',

        // Credit card (Luhn-check real implementation)
        'CREDIT_CARD' => '/\b(?:4\d{3}|5[1-5]\d{2}|6(?:011|5\d{2}))(?:[\s\-]?\d{4}){3}\b/',

        // IBAN AZ
        'IBAN_AZ' => '/\bAZ\d{2}[A-Z]{4}\d{20}\b/',

        // Azerbaijan FIN (ID card): 7 digits
        'FIN_AZ' => '/\b[A-Z]{1,2}\d{7}\b/',

        // VOEN (company tax ID): 10 digits
        'VOEN' => '/\b\d{10}\b/',

        // Passport: mostly letters + digits, 9 chars
        'PASSPORT' => '/\b[A-Z]{1,2}\d{7,8}\b/',

        // IP address
        'IP_V4' => '/\b(?:(?:25[0-5]|2[0-4]\d|[01]?\d{1,2})\.){3}(?:25[0-5]|2[0-4]\d|[01]?\d{1,2})\b/',

        // URL (may contain session token)
        'URL' => '/https?:\/\/[^\s]+/',

        // API keys / tokens
        'API_KEY' => '/\b(?:sk|pk|Bearer)[_\-][A-Za-z0-9]{20,}\b/',
    ];
}
```

### Azerbaijani Name Detection (harder)

Pure regex ilə ad tanımaq mümkün deyil. İki seçim:

**A) Allow list** — şirkət data-sında olan adlarla match et (tenant database-dən export):

```php
public function maskKnownNames(string $text): string
{
    $names = Cache::remember('pii.customer_names', 3600, fn() =>
        Customer::where('tenant_id', tenant_id())->pluck('first_name', 'last_name')->all()
    );

    foreach ($names as $name) {
        $text = preg_replace("/\b{$name}\b/u", '<NAME>', $text);
    }

    return $text;
}
```

**B) NER** (ağır): Python microservice ilə spaCy `xx_ent_wiki_sm` modelini çağır.

---

## Laravel `RedactionService` Implementasiya <a name="laravel"></a>

```php
<?php
// app/Services/Redaction/RedactionService.php

namespace App\Services\Redaction;

class RedactionService
{
    private array $strategies = [];

    public function __construct()
    {
        $this->strategies = [
            new RegexStrategy(AzerbaijaniPatterns::PATTERNS),
            new NameListStrategy(),
            // new NerStrategy(), // opsional
        ];
    }

    public function redact(string $text, ?TokenVault $vault = null): RedactionResult
    {
        $original = $text;
        $findings = [];

        foreach ($this->strategies as $strategy) {
            $result = $strategy->apply($text, $vault);
            $text = $result->text;
            $findings = array_merge($findings, $result->findings);
        }

        return new RedactionResult($text, $findings, $original);
    }

    public function restore(string $text, TokenVault $vault): string
    {
        return $vault->restoreAll($text);
    }
}
```

### Regex Strategy

```php
<?php
// app/Services/Redaction/RegexStrategy.php

class RegexStrategy
{
    public function __construct(private array $patterns) {}

    public function apply(string $text, ?TokenVault $vault = null): StrategyResult
    {
        $findings = [];

        foreach ($this->patterns as $type => $pattern) {
            $text = preg_replace_callback($pattern, function ($match) use ($type, $vault, &$findings) {
                $token = $vault
                    ? $vault->store($type, $match[0])
                    : "<{$type}>";

                $findings[] = [
                    'type' => $type,
                    'original_length' => strlen($match[0]),
                    'token' => $token,
                ];

                return $token;
            }, $text);
        }

        return new StrategyResult($text, $findings);
    }
}
```

---

## Reversible Tokenization <a name="reversible"></a>

### TokenVault (Redis-backed)

```php
<?php
// app/Services/Redaction/TokenVault.php

namespace App\Services\Redaction;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class TokenVault
{
    private string $sessionId;
    private int $counter = 0;
    private array $tokens = []; // local cache for current request
    private int $ttl = 3600;

    public function __construct(?string $sessionId = null)
    {
        $this->sessionId = $sessionId ?? (string) Str::uuid();
    }

    public function store(string $type, string $original): string
    {
        $this->counter++;
        $token = "<{$type}_{$this->counter}>";

        $this->tokens[$token] = $original;

        // Redis-də saxla — tokens session boyu yaşayır
        Redis::setex(
            "vault:{$this->sessionId}:{$token}",
            $this->ttl,
            encrypt($original)  // Laravel Crypt
        );

        return $token;
    }

    public function restoreAll(string $text): string
    {
        return preg_replace_callback('/<([A-Z_]+)_\d+>/', function ($match) {
            $token = $match[0];
            $original = $this->tokens[$token] ?? null;

            if ($original === null) {
                $cached = Redis::get("vault:{$this->sessionId}:{$token}");
                $original = $cached ? decrypt($cached) : $token;
            }

            return $original;
        }, $text);
    }

    public function sessionId(): string { return $this->sessionId; }

    public function purge(): void
    {
        $keys = Redis::keys("vault:{$this->sessionId}:*");
        if (!empty($keys)) Redis::del($keys);
    }
}
```

### İstifadə

```php
<?php
// app/Services/SupportChatService.php

class SupportChatService
{
    public function __construct(
        private RedactionService $redactor,
        private ClaudeClient $claude,
    ) {}

    public function reply(string $userMessage): string
    {
        $vault = new TokenVault();

        // 1. Redact
        $result = $this->redactor->redact($userMessage, $vault);

        Log::channel('ai')->info('chat.redacted', [
            'session' => $vault->sessionId(),
            'findings' => $result->findings,
            // NOT: logda original yoxdur, yalnız finding types və count
        ]);

        // 2. LLM-ə göndər (placeholder-li mətn)
        $response = $this->claude->messages([
            'model' => 'claude-sonnet-4-6',
            'messages' => [
                ['role' => 'user', 'content' => $result->text],
            ],
        ]);

        $llmReply = $response['content'][0]['text'];

        // 3. Restore
        $finalReply = $this->redactor->restore($llmReply, $vault);

        // 4. Purge vault (if session is done)
        if ($this->isSessionEnding()) $vault->purge();

        return $finalReply;
    }
}
```

### Nümunə axını

**User**: "Mənim sifarişim 01HM... tracking-i +994 50 123 45 67-ə SMS göndərilməlidir, hanim email ferid@acme.az"

**Redact edildi**: "Mənim sifarişim 01HM... tracking-i <PHONE_AZ_1>-ə SMS göndərilməlidir, hanim email <EMAIL_1>"

**LLM cavab**: "Sifarişiniz izlənir. Tracking SMS <PHONE_AZ_1> nömrəsinə göndəriləcək və email <EMAIL_1> ünvanına təkrar bildiriş gələcək."

**Restore**: "Sifarişiniz izlənir. Tracking SMS +994 50 123 45 67 nömrəsinə göndəriləcək və email ferid@acme.az ünvanına təkrar bildiriş gələcək."

LLM heç vaxt real phone/email görmədi.

---

## Middleware: Avtomatik Redaction <a name="middleware"></a>

API route-larında hər incoming request-də avtomatik redact:

```php
<?php
// app/Http/Middleware/AutoRedactPii.php

class AutoRedactPii
{
    public function __construct(private RedactionService $redactor) {}

    public function handle($request, Closure $next)
    {
        $vault = new TokenVault();

        foreach ($request->all() as $key => $value) {
            if (is_string($value)) {
                $result = $this->redactor->redact($value, $vault);
                $request->merge([$key => $result->text]);
            }
        }

        $request->attributes->set('pii_vault', $vault);

        return $next($request);
    }
}
```

Route:

```php
Route::middleware(['auth', 'redact.pii'])
    ->post('/ai/chat', [ChatController::class, 'store']);
```

---

## Log-lardan Redaction <a name="logging"></a>

### Monolog processor

```php
<?php
// app/Logging/RedactingProcessor.php

class RedactingProcessor
{
    public function __construct(private RedactionService $redactor) {}

    public function __invoke(array $record): array
    {
        // Message
        $record['message'] = $this->redactor->redact($record['message'])->text;

        // Context (recursive)
        $record['context'] = $this->redactRecursive($record['context']);

        return $record;
    }

    private function redactRecursive(array $arr): array
    {
        foreach ($arr as $k => $v) {
            if (is_string($v)) {
                $arr[$k] = $this->redactor->redact($v)->text;
            } elseif (is_array($v)) {
                $arr[$k] = $this->redactRecursive($v);
            }
        }
        return $arr;
    }
}
```

`bootstrap/app.php`:

```php
Log::channel('single')->pushProcessor(app(RedactingProcessor::class));
```

---

## Prompt Cache ilə Uzlaşdırma <a name="cache-safe"></a>

Anthropic prompt caching ilə sistem prompt-u hash-lə cache olunur. Həssas data sistem prompt-a düşməməlidir — yalnız user message-da.

```php
// BAD: cache-ə hits etməz, bir də hər sessiya üçün user data cache-lənir
$messages = [[
    'role' => 'system',
    'content' => "You are helping user {$user->email}...",  // email cache-lənir!
]];

// GOOD
$messages = [[
    'role' => 'system',
    'content' => [[
        'type' => 'text',
        'text' => 'You are a support agent. Help users with their orders.',
        'cache_control' => ['type' => 'ephemeral'],
    ]],
]];

// User identity user message-ın içində (redact edildikdən sonra)
$messages[] = ['role' => 'user', 'content' => $result->text];
```

Bax: `/home/orkhan/Projects/claude/ai/02-claude-api/09-prompt-caching.md`.

---

## Tests: Vektor Korpusu <a name="tests"></a>

```php
<?php
// tests/Unit/Redaction/RedactionServiceTest.php

use App\Services\Redaction\{RedactionService, TokenVault};

beforeEach(fn() => $this->redactor = app(RedactionService::class));

it('redacts Azerbaijani phone numbers', function () {
    $cases = [
        '+994 50 123 45 67' => '<PHONE_AZ',
        '0501234567' => '<PHONE_AZ',
        '050-123-45-67' => '<PHONE_AZ',
        '+994501234567' => '<PHONE_AZ',
    ];

    foreach ($cases as $input => $expectedPrefix) {
        $result = $this->redactor->redact("Əlaqə: {$input}");
        expect($result->text)->toContain($expectedPrefix);
        expect($result->text)->not->toContain($input);
    }
});

it('redacts FIN (Azerbaijan ID card)', function () {
    $result = $this->redactor->redact('FIN: AA1234567');
    expect($result->text)->toContain('<FIN_AZ');
    expect($result->text)->not->toContain('AA1234567');
});

it('redacts credit cards', function () {
    $result = $this->redactor->redact('Card: 4532-1234-5678-9010');
    expect($result->text)->toContain('<CREDIT_CARD');
});

it('redacts IBAN AZ', function () {
    $result = $this->redactor->redact('IBAN: AZ21NABZ00000000137010001944');
    expect($result->text)->toContain('<IBAN_AZ');
});

it('restores placeholders from vault', function () {
    $vault = new TokenVault();
    $result = $this->redactor->redact('Call +994 50 123 45 67', $vault);

    expect($result->text)->not->toContain('+994 50 123 45 67');

    $restored = $this->redactor->restore($result->text, $vault);
    expect($restored)->toContain('+994 50 123 45 67');
});

it('does not leak PII in logs', function () {
    $logOutput = '';
    Log::listen(fn($level, $message) => $logOutput .= $message);

    Log::channel('ai')->info('User sent', ['msg' => 'My email is ferid@acme.az']);

    expect($logOutput)->not->toContain('ferid@acme.az');
});

it('handles common false positives', function () {
    // VOEN pattern (10 digits) false positive: phone without country code
    $result = $this->redactor->redact('Sifariş nömrəsi: 1234567890');
    // Real implementation: context-aware (şirkət ID-si sözlə gəlir)
    expect($result->findings)->toHaveCount(1);
});
```

---

## Compliance: GDPR / EU AI Act <a name="compliance"></a>

### DPA (Data Processing Agreement)

Anthropic-lə və OpenAI-lə DPA imzala. [Anthropic DPA](https://www.anthropic.com/dpa), [OpenAI DPA](https://openai.com/policies/data-processing-addendum/). Redaction DPA-nı əvəz etmir — complementary.

### Zero-retention policy

Anthropic-dan **Zero Data Retention** (ZDR) tələb et. Enterprise plan-da default. Bu request-lər hər hansı logging-ə getmir.

### Right to erasure

GDPR Article 17 — istifadəçi məlumat silmə tələbi edirsə:
- TokenVault-dakı mapping-ləri sil (sənin control-də)
- Anthropic ilə audit trail: ZDR varsa orada heç nə yoxdur
- Support ticket-dən data-nı çıxar

### Audit logging (compliance-ready)

```php
DB::table('pii_redaction_log')->insert([
    'user_id' => auth()->id(),
    'session_id' => $vault->sessionId(),
    'findings_count' => count($result->findings),
    'findings_types' => json_encode(array_column($result->findings, 'type')),
    // NOT: original values saxlama
    'model' => 'claude-sonnet-4-6',
    'created_at' => now(),
]);
```

---

## Xülasə

| Addım | Detalı |
|-------|--------|
| 1. Redact before LLM | Middleware ilə avtomatik |
| 2. Reversible tokens | TokenVault + Redis + encrypt |
| 3. Restore LLM cavabında | Session vault-dən |
| 4. Log-lardan redact | Monolog processor |
| 5. Cache-safe | System prompt-a PII qoyma |
| 6. Test corpus | AZ-specific: phone, FIN, VOEN, IBAN |
| 7. DPA + ZDR | Compliance layer |
| 8. Audit log | Finding types + count, not values |

**Yadda saxla**: redaction **nice-to-have deyil, məcburi**. Security review hər AI feature launch-dan əvvəl lazımdır. PII leak-ı reputational və legal risk-dir.

## Praktik Tapşırıqlar

### 1. Regex + Presidio İkiqat Süzgəc
Laravel middleware yaradın. Birinci qat: AZN şəxsiyyət nömrəsi (`\d{7}`), email (`[\w.]+@[\w.]+`), bank kartı (`\d{4}[- ]?\d{4}[- ]?\d{4}[- ]?\d{4}`) üçün regex redaction. İkinci qat: Microsoft Presidio API-ni çağırın (docker ilə local deploy edin). İki qatı keçən PII-ı `[NAME]`, `[EMAIL]`, `[CARD]` kimi mask edin. Redaction audit log-a yazın.

### 2. PII Leak Regression Test
100 test case hazırlayın: 50 normal + 50 PII-lı input. Hər ikisi üçün hem input, hem output PII scan edin. Output-da PII tapılarsa TEST FAIL edin. CI pipeline-a daxil edin. `php artisan test --group=pii` ilə işlətmə. Xüsusi diqqət: LLM-in PII-ı paraphrase edib qaytarma hallarını da test edin.

### 3. Consent-based Retention Policy
`user_data_consents` cədvəli qurun: `user_id`, `feature`, `consent_given_at`, `retention_days`. Hər AI call üçün active consent yoxlayın. `retention_days` keçdikdən sonra `ai_call_logs`-dan həmin user-in məlumatlarını avtomatik sil (`DeleteExpiredAiLogs` scheduled job). GDPR "right to erasure" endpoint-ini tətbiq edin: `DELETE /api/user/ai-data`.

## Əlaqəli Mövzular

- [Safety Guardrails](./08-safety-guardrails.md)
- [AI Security](./09-ai-security.md)
- [Prompt Injection Defenses](./10-prompt-injection-defenses.md)
- [Content Moderation](./13-content-moderation.md)
- [AI Governance Compliance](./16-ai-governance-compliance.md)
