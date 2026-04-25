# İstehsal AI Sistem Dizaynı: Arxitekt Perspektivi (Senior)

> **Oxucu:** Baş arxitektlər  
> **Məqsəd:** Miqyasda istehsal AI sistemləri üçün dizayn qərarlarını, mübadilələri və komponent seçimlərini başa düşmək.

---

## 1. Üç Əsas Arxitektura Rejimi

Hər AI xüsusiyyəti əsaslı olaraq fərqli tələblərə malik üç rejimdən birinə uyğundur:

### Rejim 1: Sinxron AI (Real Vaxt Cavab)

```
Müştəri → [Yük Balanslaşdırıcı] → [API Serveri] → [AI Provayder] → Cavab
                                       ↑
                                P99 < 10 saniyə
```

**Məhdudiyyətlər:** HTTP vaxt aşımı (~30s) daxilində tamamlanmalıdır. İstifadəçi gözləyir.  
**İstifadə yeri:** Chat tamamlamaları, qısa formatlı generasiya, klassifikasiya, yönləndirmə qərarları  
**Əsas narahatlıq:** Gecikmə SLA. Hər gözləmə saniyəsi məşğulluğu azaldır.

### Rejim 2: Asinxron AI (Arxa Planda Emal)

```
Müştəri → [API Serveri] → [İş Növbəsi] → [İşçi] → [AI Provayder]
                ↓                                         ↓
          iş_id (202)                            DB → Webhook/Poll
```

**Məhdudiyyətlər:** Dəqiqələr və ya saatlar çəkə bilər. Müştəri dərhal iş ID alır.  
**İstifadə yeri:** Sənəd analizi, toplu zənginləşdirmə, hesabat generasiyası  
**Əsas narahatlıq:** İş etibarlılığı, tərəqqi görünürlüyü, uğursuzluqdan bərpa

### Rejim 3: Axın AI (Canlı UX)

```
Müştəri ←───── SSE/WebSocket ─────── [Axın Proksi] ──→ [AI Provayder]
         token-token                                    (axın API-si)
```

**Məhdudiyyətlər:** Uzun müddətli bağlantı. İlk token gecikməsi ümumi gecikmədən daha vacibdir.  
**İstifadə yeri:** Chat UI, canlı sənəd redaktəsi, interaktiv kod generasiyası  
**Əsas narahatlıq:** Bağlantı idarəsi, qismən uğursuzluq idarəsi, backpressure

---

## 2. Tam Sistem Diaqramı: İstehsal RAG Chatbot-u

```
┌─────────────────────────────────────────────────────────────────────┐
│                          Müştəri Qatı                                │
│  Brauzer / Mobil Tətbiq / API İstehlakçısı                          │
└──────────────────┬────────────────────────────────────────────────--┘
                   │ HTTPS
┌──────────────────▼───────────────────────────────────────────────────┐
│                        Kənar / CDN Qatı                               │
│  Cloudflare / AWS CloudFront                                          │
│  - TLS sonlandırması                                                  │
│  - DDoS qoruması                                                      │
│  - Rate limiting (IP əsaslı)                                         │
│  - Statik resurs keşləmə                                             │
└──────────────────┬───────────────────────────────────────────────────┘
                   │
┌──────────────────▼───────────────────────────────────────────────────┐
│                      Yük Balanslaşdırıcı Qatı                         │
│  AWS ALB / Nginx                                                      │
│  - SSE bağlantıları üçün sessiya yaxınlığı                           │
│  - Sağlamlıq yoxlamaları                                             │
│  - SSL yüksüzləndirməsi                                              │
└──────┬──────────────────────────┬────────────────────────────────────┘
       │                          │
┌──────▼──────┐          ┌────────▼────────┐
│  API Serverləri│        │  Axın          │
│  (Vəziyyətsiz)│        │  Serverləri    │
│  Laravel    │          │  (SSE idarəçiləri)│
│  PHP-FPM    │          │  Laravel Octane  │
└──────┬──────┘          └────────┬────────┘
       │                          │
┌──────▼──────────────────────────▼────────────────────────────────────┐
│                         Xidmət Qatı                                   │
├──────────────────┬──────────────────┬────────────────────────────────┤
│   Chat Xidməti   │   RAG Xidməti    │   Auth Xidməti                  │
│   - Tarixçə İd. │   - Geri Alma    │   - JWT doğrulama               │
│   - Kontekst İd. │   - Yenidən sır.│   - Tenant ayrımı               │
└──────────────────┴────────┬─────────┴────────────────────────────────┘
                            │
┌───────────────────────────▼──────────────────────────────────────────┐
│                        AI Gateway Qatı                                │
│  (Rate limiting, fallback, loqlama, circuit breaker)                  │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐               │
│  │   Əsas       │  │   Fallback   │  │   Yerli      │               │
│  │   Claude     │  │   GPT-4o     │  │   Ollama     │               │
│  └──────────────┘  └──────────────┘  └──────────────┘               │
└──────────────────────────────────────────────────────────────────────┘
       │                      │                    │
┌──────▼──────┐      ┌────────▼────────┐  ┌───────▼───────┐
│  PostgreSQL  │      │  Redis Klasteri  │  │  Vektor DB    │
│  - Chat tar. │      │  - Sessiya keşi │  │  (pgvector/   │
│  - Sənədlər  │      │  - Rate limitlər│  │   Pinecone)   │
│  - İstifadəçi│      │  - İş növbəl.  │  │  - Embedding-lər│
└─────────────┘      └─────────────────┘  └───────────────┘
                             │
                    ┌────────▼────────┐
                    │  Növbə İşçiləri │
                    │  (Horizon)      │
                    │  - AI toplu işl.│
                    │  - Webhook-lar  │
                    │  - Embedding-lər│
                    └─────────────────┘
```

---

## 3. Vəziyyətsiz AI Xidmət Dizaynı

AI xidmətləri üfüqi miqyaslama üçün vəziyyətsiz olmalıdır. Vəziyyət saxlanır:
- Verilənlər bazalarında (söhbət tarixi, sənədlər)
- Keşdə (sessiyalar, rate limitlər)
- Mesaj növbələrində (iş vəziyyəti)

```php
<?php
// app/Services/AI/ChatService.php

namespace App\Services\AI;

use App\Models\Conversation;
use App\Models\Message;
use App\Services\RAG\RetrievalService;

/**
 * Vəziyyətsiz chat xidməti.
 * Bütün vəziyyət DB-dən yüklənir; sorğular arasında sinif xüsusiyyətlərində heç nə saxlanılmır.
 */
class ChatService
{
    public function __construct(
        private readonly ClaudeService     $claude,
        private readonly RetrievalService  $retrieval,
        private readonly TokenCounter      $tokenCounter,
    ) {}

    /**
     * Chat növbəsini emal et.
     * DB-dən bütün konteksti yükləyir — paylaşılan vəziyyət yoxdur.
     */
    public function chat(
        Conversation $conversation,
        string       $userMessage,
        int          $tenantId,
    ): string {
        // Tarixi yüklə (vəziyyətsiz — həmişə DB-dən)
        $history = $this->buildHistory($conversation);

        // RAG: əlaqəli konteksti geri al
        $context = $this->retrieval->retrieve(
            query: $userMessage,
            tenantId: $tenantId,
            limit: 5,
        );

        // Mesajlar massivini qur
        $messages = [
            ...$history,
            ['role' => 'user', 'content' => $this->formatUserMessage($userMessage, $context)],
        ];

        // Kontekst pəncərəsinə uyğunlaşdır (uzun söhbətlər üçün kritikdir)
        $messages = $this->trimToContextWindow($messages, maxTokens: 180_000);

        // AI çağırışı (vəziyyətsiz — DB yazısına qədər yan effekt yoxdur)
        $response = $this->claude->messages(
            messages: $messages,
            systemPrompt: $this->buildSystemPrompt($conversation->tenant, $context),
            model: $conversation->tenant->preferred_model ?? 'claude-sonnet-4-5',
        );

        // Saxla (yan effektlər burada, sonda baş verir)
        $this->persistTurn($conversation, $userMessage, $response);

        return $response;
    }

    private function buildHistory(Conversation $conversation): array
    {
        return Message::where('conversation_id', $conversation->id)
            ->orderBy('created_at')
            ->get()
            ->map(fn($m) => ['role' => $m->role, 'content' => $m->content])
            ->toArray();
    }

    private function trimToContextWindow(array $messages, int $maxTokens): array
    {
        // Sistem promptunu + son mesajları saxla
        // Limitdən çıxdıqda ən köhnə istifadəçi/köməkçi cütlərini sil
        while ($this->tokenCounter->countMessages($messages) > $maxTokens && count($messages) > 2) {
            // Ən köhnə qeyri-sistem mesaj cütlərini sil
            array_splice($messages, 0, 2);
        }

        return $messages;
    }

    private function formatUserMessage(string $message, array $context): string
    {
        if (empty($context)) {
            return $message;
        }

        $contextText = collect($context)
            ->map(fn($c, $i) => "<source id=\"{$i}\">{$c['content']}</source>")
            ->implode("\n");

        return "<context>\n{$contextText}\n</context>\n\n{$message}";
    }

    private function buildSystemPrompt(mixed $tenant, array $context): string
    {
        $base = $tenant->system_prompt ?? "Siz köməkçi AI assistentsiniz.";

        if (! empty($context)) {
            $base .= "\n\nTəqdim olunan kontekstdən istifadə edərək cavab verin. Mənbələri [mənbə N] notasiyası ilə istinad edin.";
        }

        return $base;
    }

    private function persistTurn(Conversation $conv, string $user, string $assistant): void
    {
        Message::insert([
            ['conversation_id' => $conv->id, 'role' => 'user',      'content' => $user,      'created_at' => now()],
            ['conversation_id' => $conv->id, 'role' => 'assistant', 'content' => $assistant, 'created_at' => now()],
        ]);

        $conv->touch('last_message_at');
    }
}
```

---

## 4. Çox Kiracılı Arxitektura

Çox kiracılı AI sistemləri aşağıdakıları təmin etməlidir:
1. **Məlumat izolyasiyası** — A kiracısının sənədləri heç vaxt B kiracısının RAG nəticələrində görünmür
2. **Kiracı başına konfiqurasiya** — fərqli modellər, promptlar, rate limitlər
3. **Xərc atributu** — hesablama üçün kiracı başına xərci izlə
4. **Rate limiting** — bir kiracının digərlərini aclığa salmasının qarşısını al

```php
<?php
// app/Services/AI/TenantAIContext.php

namespace App\Services\AI;

use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;

/**
 * Bütün kiracıya xas AI konfiqurasiyasını əhatə edir.
 * Həmişə xam kiracı ID-si əvəzinə bu obyekti AI xidmətlərinə ötürün.
 */
readonly class TenantAIContext
{
    public function __construct(
        public readonly int    $tenantId,
        public readonly string $model,
        public readonly string $systemPrompt,
        public readonly int    $maxTokensPerRequest,
        public readonly int    $maxRequestsPerMinute,
        public readonly float  $temperature,
        public readonly array  $allowedTools,
        public readonly string $vectorNamespace,  // Kiracı başına vektor axtarışını izolyasiya edir
    ) {}

    public static function forTenant(int $tenantId): static
    {
        return Cache::remember("tenant_ai_ctx:{$tenantId}", 300, function () use ($tenantId) {
            $tenant = Tenant::with('aiConfig')->findOrFail($tenantId);
            $config = $tenant->aiConfig;

            return new static(
                tenantId: $tenantId,
                model: $config->model ?? 'claude-haiku-4-5',
                systemPrompt: $config->system_prompt ?? '',
                maxTokensPerRequest: $config->max_tokens ?? 4096,
                maxRequestsPerMinute: $config->rate_limit ?? 60,
                temperature: $config->temperature ?? 0.7,
                allowedTools: $config->allowed_tools ?? [],
                vectorNamespace: "tenant_{$tenantId}",  // Fiziki olaraq ayrı vektor məkanı
            );
        });
    }

    public function validate(): void
    {
        // Kiracı limitlərini tətbiq etmək üçün hər AI sorğusundan əvvəl çağırılır
        $this->checkRateLimit();
        $this->checkMonthlyBudget();
    }

    private function checkRateLimit(): void
    {
        $key   = "rate:{$this->tenantId}:" . now()->format('YmdHi');
        $count = Cache::increment($key, 1, now()->addMinutes(2));

        if ($count > $this->maxRequestsPerMinute) {
            throw new \App\Exceptions\AI\TenantRateLimitException(
                "Kiracı {$this->tenantId} rate limitini aşdı"
            );
        }
    }

    private function checkMonthlyBudget(): void
    {
        $spent = Cache::get("budget:{$this->tenantId}:" . now()->format('Ym'), 0);
        $limit = Tenant::find($this->tenantId)?->monthly_ai_budget ?? PHP_INT_MAX;

        if ($spent >= $limit) {
            throw new \App\Exceptions\AI\BudgetExceededException(
                "Kiracı {$this->tenantId} aylıq AI büdcəsini aşdı"
            );
        }
    }
}
```

---

## 5. Üfüqi Miqyaslama Strategiyası

```php
// config/octane.php — axın/uzun-bağlantı serverləri üçün
return [
    'server'     => 'swoole',
    'https'      => false,
    'listeners'  => [],
    'warm'       => [
        \App\Services\AI\ClaudeService::class,
        \App\Services\AI\EmbeddingService::class,
    ],
    'flush'      => [
        // Sorğu başına vəziyyəti sıfırla
        \App\Services\AI\RequestContext::class,
    ],
    'swoole'     => [
        'options' => [
            'max_request'        => 500,
            'dispatch_mode'      => 2,  // SSE üçün bağlantı yaxınlığı rejimi
            'package_max_length' => 20 * 1024 * 1024,  // Böyük sənədlər üçün 20MB
        ],
    ],
];
```

**Miqyaslama qərarları:**

| Komponent          | Miqyaslama Strategiyası   | Əsaslandırma                              |
|--------------------|---------------------------|-------------------------------------------|
| API serverləri     | Üfüqi (vəziyyətsiz)       | Paylaşılan vəziyyət yoxdur; nümunə əlavə et|
| Növbə işçiləri     | Üfüqi                     | Horizon növbə dərinliyinə əsasən avtomiqyaslar|
| Vektor DB          | Şaquli + sharding         | Sharding mürəkkəbdir; əvvəlcə yuxarı miqyasla|
| PostgreSQL         | Oxuma replikaları          | Tarixi oxumalar yüksək həcmlidir          |
| Redis              | Klaster rejimi             | Keş + növbə HA tələb edir                |
| Axın serverləri    | Üfüqi + yapışqan          | SSE bağlantı yaxınlığı tələb edir        |

---

## 6. İstehsal RAG Chatbot-u üçün Verilənlər Bazası Sxemi

```sql
-- Əsas söhbət cədvəlləri
CREATE TABLE conversations (
    id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id   BIGINT NOT NULL REFERENCES tenants(id),
    user_id     BIGINT NOT NULL REFERENCES users(id),
    title       VARCHAR(500),
    model       VARCHAR(100) NOT NULL,
    metadata    JSONB DEFAULT '{}',
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    last_message_at TIMESTAMPTZ,
    INDEX (tenant_id, user_id, last_message_at DESC)
);

CREATE TABLE messages (
    id              BIGSERIAL PRIMARY KEY,
    conversation_id UUID NOT NULL REFERENCES conversations(id) ON DELETE CASCADE,
    role            VARCHAR(20) NOT NULL,  -- user|assistant|system
    content         TEXT NOT NULL,
    input_tokens    INT,
    output_tokens   INT,
    model           VARCHAR(100),
    latency_ms      INT,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    INDEX (conversation_id, created_at)
);

-- Vektor embedding-ləri ilə sənəd anbarı
CREATE TABLE documents (
    id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id   BIGINT NOT NULL REFERENCES tenants(id),
    title       VARCHAR(500),
    content     TEXT NOT NULL,
    metadata    JSONB DEFAULT '{}',
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE document_chunks (
    id          BIGSERIAL PRIMARY KEY,
    document_id UUID NOT NULL REFERENCES documents(id) ON DELETE CASCADE,
    tenant_id   BIGINT NOT NULL,  -- Sürətli filtr üçün denormalize edilmiş
    chunk_index INT NOT NULL,
    content     TEXT NOT NULL,
    embedding   vector(1536),  -- pgvector
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- Sürətli oxşarlıq axtarışı üçün HNSW indeksi (pgvector)
CREATE INDEX document_chunks_embedding_idx 
ON document_chunks USING hnsw (embedding vector_cosine_ops)
WITH (m = 16, ef_construction = 64);

-- Qismən indeks: yalnız izolyasiya üçün kiracı başına indeks
CREATE INDEX document_chunks_tenant_idx ON document_chunks(tenant_id);
```

---

## 7. Əsas Arxitektura Qərarları və Mübadilələr

| Qərar                     | Seçim A                  | Seçim B               | Tövsiyə                                       |
|---------------------------|--------------------------|-----------------------|-----------------------------------------------|
| Vektor DB                 | pgvector (PostgreSQL)    | Xüsusi (Pinecone)     | pgvector 10M vektora qədər; Pinecone sonra    |
| Axın serveri              | Laravel Octane (Swoole)  | Node.js proksi        | Tam-PHP stack üçün Octane; SSE-ağır üçün Node |
| Kontekst idarəsi          | DB-dəstəkli hər növbə   | Yaddaş + Redis        | Etibarlılıq üçün DB; keş üçün Redis           |
| Çox kiracılı izolyasiya   | Kiracı başına sxem       | Sıra-səviyyəli təhlükəsizlik | Sıra-səviyyəli daha yaxşı miqyaslanır    |
| Model fallback            | Eyni provayder (pillar)  | Çarpaz-provayder      | Həqiqi dayanıqlılıq üçün çarpaz-provayder    |
| Prompt saxlama            | Kod                      | DB + xüsusiyyət flag-ları| DB deploy etmədən isti dəyişiklik imkanı verir|
