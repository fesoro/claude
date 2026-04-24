# Build vs Buy — AI Component-lərini Özün Quraşdır, yoxsa Əldə Al?

> **Qərar**: hər AI komponent üçün — LLM, embeddings, vector DB, observability, fine-tuning, agent framework, moderation — **öz quraşdırmalı, yoxsa provayder-dən almalısan?** Senior PHP dev kimi bu qərarı həftədə bir dəfə verirsən. Bu sənəd həmişə istifadə etdiyin framework-dür.

---

## Mündəricat

1. [Qərar Çərçivəsi](#framework)
2. [LLM: API vs Self-hosted Llama](#llm)
3. [Embeddings: API vs Self-hosted BGE](#embeddings)
4. [Vector DB: Pinecone vs pgvector vs Qdrant](#vector-db)
5. [Observability: LangSmith vs DIY](#observability)
6. [Fine-tuning: Provider vs LoRA Self-hosted](#fine-tuning)
7. [Agent Framework: LangChain vs DIY](#agent)
8. [Moderation: Provider vs Self](#moderation)
9. [TCO Modelləşdirmə](#tco)
10. [Hybrid Strategiya](#hybrid)
11. [Migrasiya Strategiyası (Gateway Pattern)](#migration)
12. [Case Study-lər](#cases)
13. [10-sualık Checklist](#checklist)

---

## Qərar Çərçivəsi <a name="framework"></a>

### Ölçülər

Hər komponent üçün bu ölçüləri qiymətləndir:

| Ölçü | Sual |
|------|------|
| **Cost** | Aylıq volume × provider qiyməti vs self-host hardware + ops vaxtı |
| **Control** | Model versiyasını, davranışı, upgrade-ləri kim nəzarət edir? |
| **Lock-in** | Provayderi dəyişmək üçün neçə həftə lazımdır? |
| **Time-to-market** | İndiyədək nə qədər? |
| **Data residency** | Məlumat EU-da, Azərbaycan-da qalmalıdır? |
| **Compliance** | HIPAA, SOC2, GDPR tələbləri? |
| **Scale** | Peak RPS, concurrent users? |
| **Expertise** | Komandan bunu idarə edə bilirmi? |
| **Support** | SLA, incident response lazımdırmı? |

### Default qərar məntiqi

```
Start-up (<$5k/ay AI spend) → API-dan al
Mid-size ($5k-$50k/ay) → Hybrid (API yüksək, batch self-host)
Enterprise (>$50k/ay) → Gateway + selective self-host
Compliance-heavy → Self-host (data residency)
```

---

## LLM: API vs Self-hosted Llama <a name="llm"></a>

### API (Claude, GPT, Gemini)

**Üstünlüklər**:
- Day 1-də istifadə et
- Ən yüksək keyfiyyət (frontier modellər)
- Prompt caching, batch API, structured output
- SLA və support provayderindən
- Heç bir infra investisiyası

**Mənfi cəhətlər**:
- Token-based pricing — scale-də baha
- Rate limits
- Data provayderə gedir (GDPR/data residency concern)
- Vendor lock-in (kiçik)

### Self-hosted (Llama 4, Mistral, Qwen)

**Üstünlüklər**:
- Fixed cost (inference cost əvəzinə hardware amortization)
- Data heç vaxt çıxmır
- Tam custom fine-tune imkanı
- Rate limit öz əlində

**Mənfi cəhətlər**:
- Hardware lazımdır (H100, A100) — aylıq $5k-$50k+
- ML ops komandası lazımdır (vLLM, TGI, Ray Serve)
- Keyfiyyət frontier-dən 1-2 nəsil geri
- Upgrade, monitoring, scaling — sənin problemindir

### Qərar cədvəli

| Senaryo | Tövsiyə |
|---------|---------|
| MVP, <$5k/ay spend | **API** (Claude/GPT) |
| Prod, $5-30k/ay spend | **API** + aggressive caching |
| Prod, >$30k/ay batch workload | **Hybrid**: API interactive, Llama batch |
| Data residency tələbi (EU gov, healthcare) | **Self-host** (BGE, Llama, Azure EU region) |
| Offline/air-gapped deployment | **Self-host** məcburidir |
| Spesifik domain (legal, medical) fine-tune lazım | **Self-host LoRA** + API fallback |

### Laravel kodda nə dəyişir?

Gateway pattern istifadə edirsənsə heç nə. `LlmDriver` interface-i iki implementasiya götürür:

```php
interface LlmDriver {
    public function chat(array $messages, array $options = []): array;
}

class ClaudeDriver implements LlmDriver { /* ... */ }
class SelfHostedLlamaDriver implements LlmDriver { /* ... */ }
```

Bax: `/home/orkhan/Projects/claude/ai/08-production/15-multi-provider-failover.md`.

---

## Embeddings: API vs Self-hosted BGE <a name="embeddings"></a>

### API (Voyage, OpenAI, Cohere)

- **Voyage voyage-3**: Retrieval üçün SOTA, $0.12/M tokens
- **OpenAI text-embedding-3-large**: 3072 dim, $0.13/M tokens
- **Cohere embed-v3**: multilingual

**Üstünlük**: Zero ops. API çağır, vector al.

**Mənfi**: Scale-də xərc yüksəlir. 1B tokens → $120. Plus hər sorğuda network round-trip.

### Self-hosted (BGE, E5, Qwen-embed)

- **BGE-large-en**: BAAI, open-source, HF-də yüklə
- **E5-large-v2**: Microsoft, strong baseline
- **multilingual-e5-large**: çoxdilli

**Üstünlük**:
- Fixed cost (bir GPU serveri)
- Low latency (lokal)
- Tam offline
- Batch processing at scale

**Mənfi**: GPU lazımdır. Embed SDK yazmaq lazımdır.

### Qərar qısa

```
<100M tokens/ay → API (Voyage)
100M-1B → hybrid (critical API, batch self-host)
>1B tokens/ay → self-host (RTX 4090/A100)
```

### Laravel-da istifadə

```php
interface EmbeddingDriver {
    public function embed(string $text): array;      // float[1536]
    public function embedBatch(array $texts): array; // float[][]
}

class VoyageDriver implements EmbeddingDriver { /* ... */ }
class SelfHostedBgeDriver implements EmbeddingDriver {
    // HTTP call to internal BGE server (Text Embeddings Inference container)
}
```

---

## Vector DB: Pinecone vs pgvector vs Qdrant <a name="vector-db"></a>

### Qısa müqayisə

| Həll | Ops complexity | Cost (1M vectors) | Perf | Tövsiyə |
|------|---------------|-------------------|------|---------|
| **pgvector** (PostgreSQL extension) | 0 (DB-də) | $0 (mövcud DB-də) | Orta — 10k vectors-a qədər əla | MVP, <5M vectors |
| **Qdrant** self-host | Aşağı (Docker container) | ~$50/ay (hardware) | Yüksək | 5M-100M vectors |
| **Milvus/Weaviate** self-host | Orta (distributed cluster) | ~$200/ay | Çox yüksək | >100M vectors |
| **Pinecone** managed | 0 (SaaS) | ~$70/ay start, scale olur | Yüksək, zero ops | $50k/ay spend-ə qədər |
| **Supabase vector** (pgvector SaaS) | 0 | ~$25/ay | Yaxşı | MVP Supabase-də |

### Qərar qısa

```
Senior PHP dev, Laravel monolith, <5M vectors → pgvector
Laravel + ayrı ML service, 5M-100M vectors → Qdrant self-host  
SaaS MVP, zero ops → Pinecone və ya Supabase
Enterprise, 100M+ vectors → Weaviate self-host + K8s
```

### pgvector niyə default seçim PHP dev üçün

1. DB onsuz da var
2. SQL-in hamısını istifadə et — hybrid search (BM25 + vector) bir JOIN-də
3. Eloquent relationship-lər işləyir
4. Backup/restore mövcud prosesin içindədir
5. HNSW index 10M-ə qədər scale olur

Bax `/home/orkhan/Projects/claude/ai/04-rag-embeddings/02-vector-databases.md`.

---

## Observability: LangSmith vs Helicone vs DIY <a name="observability"></a>

### Managed (LangSmith, Helicone, Phoenix Cloud)

- **LangSmith**: LangChain-dən, tracing + evals + datasets. $39/user/ay
- **Helicone**: LLM proxy + analytics. Free tier → $20/ay
- **Phoenix Cloud**: Arize-dən, open-source + hosted

**Üstünlük**: 10 dəqiqəyə set up. Prompt versioning, eval runs, dashboard.

**Mənfi**: Request-lər onların proxy-sindən keçir (latency, cost, data concern).

### DIY (Prometheus + Grafana + Postgres)

**Əsas komponentlər**:
- Postgres table: `llm_requests (timestamp, model, prompt_hash, input_tokens, output_tokens, latency_ms, cost_usd, user_id, feature, feedback_score)`
- Prometheus metrics via `league/climate-prometheus` və ya `promphp/prometheus_client_php`
- Grafana dashboard
- Manual eval suite (pytest-like)

**Üstünlük**:
- Data öz DB-ndə qalır
- Custom metriklər
- Mövcud observability stack-ə inteqrasiya

**Mənfi**:
- Custom dashboard build-i 2-3 həftə
- Evals manual

### Qərar

```
MVP + <10 developer → Helicone proxy (easy setup)
Mid-size Laravel mono → DIY (Postgres log + Grafana)
Enterprise → DIY + Arize self-host
```

Bax: `/home/orkhan/Projects/claude/ai/08-production/03-llm-observability.md`.

---

## Fine-tuning: Provider vs LoRA Self-hosted <a name="fine-tuning"></a>

### Provider (OpenAI fine-tune, Anthropic fine-tune)

- Upload JSONL → wait 1-6 hours → custom model
- Model provayderin cloud-unda qalır
- $25/M training tokens + inference overhead

### Self-hosted LoRA

- Base model (Llama 4 8B) + adapter training
- 1 A100 GPU 6-12 saat
- Adapter file (~50MB) version-la, store
- vLLM-də LoRA adapter swap at runtime

### Qərar

```
Behavior dəyişməsi (tone, format) lazım + <10M training tokens → Provider fine-tune
Domain-specific knowledge + 100M+ tokens → Self-hosted LoRA
Multi-tenant (hər müştəri üçün adapter) → Self-hosted (provider cloud-da iqtisadi deyil)
```

**Çox vaxt**: prompt + few-shot examples kifayət edir, fine-tune lazım deyil. Day 30 problemi.

---

## Agent Framework: LangChain vs DIY <a name="agent"></a>

### LangChain / LlamaIndex / Griptape

**Üstünlüklər**:
- 100+ integrations out-of-box (Google Drive, Notion, etc.)
- Pre-built agent patterns (ReAct, Plan-Execute)
- Community, docs

**Mənfi**:
- Abstraction tax — sadə case-lər ekstra code
- Debugging çətin — 5 qat abstraction
- Version churn — API aylıq dəyişir
- TS/Python-dur — PHP-də yoxdur

### DIY (Laravel-da)

PHP dev üçün LangChain yoxdur. Ya:
- **Raw Claude API + tool loop** (bax 05-agents/06-claude-agent-sdk-deep.md)
- **php-mcp/laravel** ilə MCP-based agent
- **Saloon SDK** + Anthropic PHP SDK

**Üstünlük**: 200-500 sətir kod. Tam başa düşürsən. Debugging asandır.

**Mənfi**: 50 integration-u öz yazmalısan. Common patterns (ReAct) öz implement.

### Qərar

```
Python/TS team, 5+ external tool integration → LangChain
PHP/Laravel team, 2-3 tool integration → DIY
Complex multi-agent orchestration → Claude Agent SDK (TS) + Laravel backend
```

---

## Moderation: Provider vs Self <a name="moderation"></a>

### OpenAI Moderation API (free)

- Toxicity, sexual, violence, self-harm kateqoriyaları
- Free, aşağı latency
- İngilis əsaslıdır, azərbaycanca performance aşağıdır

### Azure Content Safety

- Microsoft, 200+ dil dəstəyi
- Pricing: $1/1000 text records
- EU data residency option

### Self-host (Detoxify, Llama Guard)

- Detoxify HuggingFace model — lokal
- Llama Guard — Meta, strong
- Custom fine-tune üçün

### Qərar

```
Aşağı volume (free tier sufficient) → OpenAI Moderation
Avropa data residency → Azure Content Safety
Azərbaycanca ciddi moderation lazım → Llama Guard + custom fine-tune
```

---

## TCO Modelləşdirmə <a name="tco"></a>

Self-host qərar verərkən yalnız hardware deyil — **total cost of ownership** hesabla:

### Nümunə: Llama 70B self-host

| Maddə | Aylıq Cost |
|-------|-----------|
| GPU server (H100 × 1, cloud) | $3,500 |
| Storage (model weights, logs) | $100 |
| DevOps vaxt (1 engineer × 10%) | $1,500 |
| Monitoring (Grafana, Prometheus infra) | $50 |
| On-call compensation | $200 |
| Upgrade + testing vaxt (3 ay/1) | $500 (amortized) |
| **Cəmi** | **~$5,850/ay** |

Eyni inference volume API-da: 500M tokens/ay × $3/M = **$1,500**.

**Conclusion**: Llama 70B self-host **yalnız** data residency / compliance tələbinə görə mənalıdır. Pure cost üçün API qalib.

### Nümunə: 5B tokens/ay (böyük workload)

| Option | Aylıq Cost |
|--------|-----------|
| Claude API (Sonnet) | $15,000 input + $45,000 output = **$60k** |
| Claude API + 80% prompt cache | $3k + $45k = **$48k** |
| Claude API + batch 50% | $30k |
| Llama 70B self-host (2 H100) | **$11k** |

**Conclusion**: 5B tokens/ay self-host ciddi qənaət. Setup 4-8 həftə.

---

## Hybrid Strategiya <a name="hybrid"></a>

Ən çox kullanılan pattern:

```
┌─────────────────────────────────┐
│   Interactive (latency sensitive)│ → Claude API (Sonnet)
│   Support chat, copilot           │
└─────────────────────────────────┘

┌─────────────────────────────────┐
│   Batch (cost sensitive)         │ → Self-hosted Llama 70B
│   Overnight doc summarization    │
│   Email classification batch     │
└─────────────────────────────────┘

┌─────────────────────────────────┐
│   Sensitive (data residency)     │ → Self-hosted (EU)
│   PII processing                 │
│   Legal documents                │
└─────────────────────────────────┘

┌─────────────────────────────────┐
│   Fallback (when API down)       │ → Self-hosted
│   Bypass rate limits             │
└─────────────────────────────────┘
```

### Laravel Gateway

```php
// config/ai.php
'routing' => [
    'default' => 'claude',
    'batch_processing' => 'llama',
    'pii_data' => 'llama_eu',
    'fallback' => 'llama',
],
```

```php
class AiGateway {
    public function chat(string $useCase, array $messages, array $options = []): array
    {
        $driver = config("ai.routing.{$useCase}")
            ?? config('ai.routing.default');

        return app("ai.drivers.{$driver}")->chat($messages, $options);
    }
}
```

---

## Migrasiya Strategiyası (Gateway Pattern) <a name="migration"></a>

Never direct-to-provider coupling. Həmişə gateway:

### Gün 1 (Claude API)

```php
// app/Services/Ai/ChatService.php
class ChatService {
    public function __construct(private ClaudeDriver $claude) {}

    public function reply(string $msg): string {
        return $this->claude->chat([...])->text;
    }
}
```

**Problem**: `ClaudeDriver` hard-coded. 6 ay sonra Gemini-yə keçmək lazım olsa — bütün codebase dəyişdirmək gərəkdir.

### Gün 1 (Düzgün)

```php
interface LlmDriver {
    public function chat(array $messages): ChatResponse;
}

class ChatService {
    public function __construct(private LlmDriver $llm) {}  // interface!

    public function reply(string $msg): string {
        return $this->llm->chat([...])->text;
    }
}

// bindings.php
$this->app->bind(LlmDriver::class, ClaudeDriver::class);
```

6 ay sonra: `->bind(LlmDriver::class, GeminiDriver::class)` bir sətir dəyişir.

### A/B testing support

```php
class RoutingLlmDriver implements LlmDriver {
    public function chat(array $messages): ChatResponse {
        $variant = $this->rolloutService->variantFor(auth()->user());
        return match ($variant) {
            'claude' => app(ClaudeDriver::class)->chat($messages),
            'gemini' => app(GeminiDriver::class)->chat($messages),
            'llama'  => app(LlamaDriver::class)->chat($messages),
        };
    }
}
```

Bax: `/home/orkhan/Projects/claude/ai/07-workflows/07-ai-ab-testing.md`.

---

## Case Study-lər <a name="cases"></a>

### Cursor (IDE coding assistant)

**Build**: Tab autocomplete custom model (specialized on code)
**Buy**: Main chat = Claude/GPT API
**Niyə**: Autocomplete latency kritik → custom distilled model. Main chat keyfiyyət > cost → API.

### Notion AI

**Build**: Search ranking, embeddings indexing pipeline
**Buy**: Generation = GPT-4, Claude
**Niyə**: Embedding 100B+ blocks üçün costs astronomiq → self-host. Text gen = frontier API.

### Zapier

**Build**: Internal classifier models, routing
**Buy**: Generation, reasoning = OpenAI, Anthropic APIs
**Niyə**: 1M+ workflows/day, classifier self-host 100x ucuz. Generation quality API-da daha yüksək.

### Intercom Fin (support AI)

**Build**: Custom trained models (Fin brain), answer generation
**Buy**: Base LLM = Anthropic Claude
**Niyə**: Support domain-specific fine-tune rəqabət üstünlüyüdür. Base LLM quality — Anthropic + fine-tune.

### Perplexity

**Build**: Custom ranking, retrieval
**Buy**: Generation = mix (Claude, GPT, Llama, öz Sonar)
**Niyə**: Retrieval differentiator. Generation multi-provider flexibility üçün.

**Pattern**: Hamısı **hybrid**. "Everything build" və ya "Everything buy" yoxdur.

---

## 10-sualık Checklist <a name="checklist"></a>

Yeni komponent əlavə edərkən hər birinə cavab ver:

1. **Aylıq volume nə qədər olacaq?** (API cost məhsulu çıxarın)
2. **Frontier quality lazımdır, yoxsa 80% kifayətdir?** (Frontier → API)
3. **Data sensitiv/residency-mi?** (Bəli → self-host)
4. **Komandada ML ops varmı?** (Xeyr → API qəti)
5. **Time-to-market nə qədər kritikdir?** (2 həftə → API)
6. **Rate limit-ə düşmüşükmü?** (Bəli → fallback self-host)
7. **Multi-tenant fine-tune lazımmı?** (Bəli → self-host LoRA)
8. **Offline/air-gapped tələbimi?** (Bəli → self-host məcburi)
9. **Vendor lock-in-dən qaçmaq vacibdirmi?** (Gateway pattern yetər)
10. **Hybrid strategiya mənalıdırmı?** (Çox vaxt bəli)

### Flowchart

```
┌─ Data sensitiv/EU residency? ──── bəli ──► SELF-HOST
│                                    xeyr
│                                     │
├─ <$5k/ay spend? ──── bəli ──► API
│                      xeyr
│                       │
├─ ML ops komanda var? ── xeyr ──► API
│                          bəli
│                           │
├─ >$30k/ay spend? ──── bəli ──► HYBRID (API hot, self-host batch)
│                       xeyr
│                        │
└─ Frontier quality lazım? ── bəli ──► API
                              xeyr
                               │
                           HYBRID və ya SELF-HOST
```

---

## Xülasə

| Prinsip | Mənası |
|---------|--------|
| **Gateway pattern day 1** | Interface ilə abstrakt et, sonra migration asandır |
| **API ilə başla** | <$5k/ay spend-ə qədər self-host vaxt itkisidir |
| **Hybrid normal sonucdır** | "Tam build" və ya "tam buy" real şirkətlərdə yoxdur |
| **TCO hesabla, təkcə inference deyil** | Hardware + ops + on-call + upgrade vaxtı |
| **Data residency = self-host** | Tipik tək güclü reason self-host-a |
| **Compliance sınaq et** | SOC2, GDPR, HIPAA audit-ə çıxmağa hazırsanmı? |
| **Migration üçün exit plan** | Vendor-dan asılılıq olmasın, hətta API istifadə edəndə belə |

**Yekun**: Senior PHP dev üçün çox vaxt **API-dan başla + gateway pattern**. Scale-ə çatanda selective self-host. Data residency olarsa məcburi self-host. Hybrid normal final arxitekturadır.

Növbəti: `/home/orkhan/Projects/claude/ai/01-fundamentals/11-llm-pricing-economics.md` — rəqəmləri dərindən anla.
