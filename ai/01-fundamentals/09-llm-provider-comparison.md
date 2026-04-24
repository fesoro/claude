# LLM Provayder Müqayisəsi — Claude vs GPT vs Gemini vs Open-Source

> Hədəf auditoriyası: PHP/Laravel arxa tərəfi olan məhsullar üçün hansı LLM provayderini seçməli — sadəcə "Claude ən yaxşısıdır" və ya "GPT məşhurdur" demədən, arxitektor kimi düşünərək real texniki və kommersiya meyarları əsasında qərar verməli olan senior developerlər.

---

## Mündəricat

1. [Niyə Provayder Seçimi Arxitektura Məsələsidir](#niyə-provayder-seçimi-arxitektura-məsələsidir)
2. [Bazar Mənzərəsi — 2026-04 Vəziyyəti](#bazar-mənzərəsi)
3. [Anthropic Claude Ailəsi](#anthropic-claude-ailəsi)
4. [OpenAI GPT / o-seriyası](#openai-gpt--o-seriyası)
5. [Google Gemini](#google-gemini)
6. [Open-Source — Llama, DeepSeek, Mistral, Qwen](#open-source-modellər)
7. [Kontekst Pəncərəsi Müqayisəsi](#kontekst-pəncərəsi-müqayisəsi)
8. [Qiymət Müqayisəsi](#qiymət-müqayisəsi)
9. [API Erqonomikası və SDK Dəstəyi](#api-erqonomikası-və-sdk-dəstəyi)
10. [Tool Use / Function Calling Keyfiyyəti](#tool-use-keyfiyyəti)
11. [Multimodal Qabiliyyətlər](#multimodal-qabiliyyətlər)
12. [Rate Limits və Ölçəklənmə](#rate-limits-və-ölçəklənmə)
13. [EU / GDPR / Uyğunluq](#eu--gdpr--uyğunluq)
14. [Fine-Tuning Mövcudluğu](#fine-tuning-mövcudluğu)
15. [Ekosistem — MCP, Agent SDK, Vasitələr](#ekosistem)
16. [Qərar Matrisi](#qərar-matrisi)
17. [Use-Case-ə Görə Tövsiyələr](#use-case-ə-görə-tövsiyələr)

---

## Niyə Provayder Seçimi Arxitektura Məsələsidir

"Hansı model ən yaxşıdır?" sualı səhvdir. Düzgün sual budur:

> "Mənim use-case-im, qiymət həssaslığım, latency büdcəm, uyğunluq tələblərim və komandamın ekosistem bilikləri daxilində *mənim məhsulum üçün* hansı model ən yaxşı seçimdir?"

Provayder seçimi aşağıdakı oxlarda trade-off-lar deməkdir:

```
               Keyfiyyət
                   ^
                   |
                 Opus
                   |        o1 / o3
                   |
                Sonnet •  • GPT-4o
                   |
                   |    • Gemini Flash
                  Haiku • Llama-70B
                   |
                   +----------------------> Qiymət
           ucuz                         bahalı

Ölçülər: Latency, Throughput, Context, Multimodal, Tool-use, Compliance
```

Bir model bir ox üzrə qalib gəldikdə, adətən başqasında uduzur. Senior developerin işi:
1. Oxları tapşırıq üçün qiymətləndirmək
2. "Yetərincə yaxşı" keyfiyyət astanasını tapmaq
3. O astananı qarşılayan ən ucuz / ən sürətli modeli seçmək

---

## Bazar Mənzərəsi

2026-04 tarixi etibarilə əsas provayderlər:

| Provayder | Əsas Modellər | Güclü Tərəf | Yerləşmə |
|-----------|---------------|-------------|----------|
| **Anthropic** | Claude Opus 4.5, Sonnet 4.5, Haiku 4.5 | Reasoning, code, tool use, agents, uzun kontekst | Enterprise / developer-first |
| **OpenAI** | GPT-4o, o1, o3, o3-mini | Ekosistem, multimodal, ChatGPT brend | General-purpose, istehlakçı + enterprise |
| **Google** | Gemini 2.5 Pro, Gemini 2.5 Flash | 2M token kontekst, ucuz, Workspace | Workspace/GCP istifadəçiləri |
| **Meta** | Llama 3.3, Llama 4 | Open weights, edit edilə bilər, self-host | Open-source, on-prem |
| **DeepSeek** | DeepSeek-V3, DeepSeek-R1 | Çox ucuz, güclü reasoning, MIT license | Qiymət həssas, reasoning |
| **Mistral** | Mistral Large 2, Codestral | Avropa, kodlaşdırma, funksiya çağırışı | EU compliance, Azure |
| **Alibaba** | Qwen 2.5, Qwen Coder | Çox dilli (xüsusilə Çin dili), kod | Asiya bazarı, open-source |

---

## Anthropic Claude Ailəsi

### Modellər (2026-04)

```
claude-opus-4-5      → Flaqman. Kompleks reasoning, agents, uzun multi-step
                       tapşırıqlar. Bahalı, yavaş, amma ağıllı.
claude-sonnet-4-5    → İş ati. Production-un 80%-i. Keyfiyyət/qiymət
                       arasında ideal balans.
claude-haiku-4-5     → Sürətli/ucuz. Sadə tapşırıqlar, klassifikasiya,
                       extraction, yüksək həcm.
```

### Güclü Tərəflər

- **Tool use keyfiyyəti**: Sənaye rəyinə görə ən yaxşı. Parallel tool calls, səhv halında self-correction, çoxlu iterasiya üzərində sabitlik.
- **Kod yazma**: SWE-bench verified 70%+ (Sonnet), 80%+ (Opus). Claude Code rəsmi CLI.
- **Uzun kontekst davranışı**: 200K token-də "needle in haystack" performansı real qalır (GPT-4 uzun konteksləti unudur).
- **Agent SDK**: TypeScript/Python SDK tool loop və subagent-ləri idarə edir.
- **MCP (Model Context Protocol)**: Açıq standart — Claude Code, Claude Desktop, bir çox IDE onu dəstəkləyir.
- **System prompt uyğunluğu**: İnstruksiyalara dəqiq əməl edir, "helpful-ness" ilə over-ride etmir.
- **Prompt caching**: 5 dəqiqəlik cache 90% endirim (cached input).

### Zəif Tərəflər

- Yerli bazar istifadəçiləri üçün ChatGPT brendi yoxdur.
- Image generation yoxdur (yalnız image understanding).
- Audio/video input hələ limit (Sonnet 4.5 vision OK, native audio OpenAI qədər güclü deyil).
- Fine-tuning: yalnız Opus/Sonnet üçün enterprise, açıq deyil.
- EU data residency: yalnız AWS Bedrock / GCP Vertex ilə.

### PHP Use-Case Üçün Uyğunluq

```php
// Claude Sonnet 4.5 — PHP backend üçün ideal default
$response = Http::withHeaders([
    'x-api-key' => config('services.anthropic.key'),
    'anthropic-version' => '2023-06-01',
])->post('https://api.anthropic.com/v1/messages', [
    'model' => 'claude-sonnet-4-5',
    'max_tokens' => 1024,
    'system' => 'You are a Laravel support assistant.',
    'messages' => [
        ['role' => 'user', 'content' => $userMessage],
    ],
]);
```

---

## OpenAI GPT / o-Seriyası

### Modellər (2026-04)

```
gpt-4o          → Multimodal flagman (text + vision + audio).
                  Real-time API. ChatGPT-nin əsas modeli.
gpt-4o-mini     → Ucuz, sürətli. Haiku-ya alternativ.
o1              → "Thinking" model. Math, olympiad, elmi mühakimə.
o3              → o1-nin varisi. Daha yaxşı reasoning, daha az
                  hallucination.
o3-mini         → Ucuz reasoning model.
```

### Güclü Tərəflər

- **Ekosistem**: ən böyük SDK, community, dokumentasiya, 3rd-party alət dəstəyi.
- **Multimodal**: real-time audio (Realtime API), image generation (DALL-E 3), image understanding, TTS, STT (Whisper).
- **Reasoning (o-serial)**: olympiad math, elmi sual-cavab, mürəkkəb planlama.
- **Structured output**: `response_format: json_schema` strict type guarantee verir.
- **Assistants API**: built-in file search, code interpreter (kod sandbox icra).
- **Fine-tuning**: GPT-4o-mini fine-tune mümkündür, self-serve.

### Zəif Tərəflər

- **Kontekst**: 128K (GPT-4o), o3 üçün də 200K-a qədər, Claude/Gemini-dən aşağı.
- **Pricing volatility**: tez-tez qiymət dəyişikliyi, model deprecation.
- **Tool use keyfiyyəti**: Claude-dan aşağı hesab olunur (xüsusən parallel calls, çoxlu iterasiya).
- **API sabitliyi**: o1/o3 davranışı digər modellərdən fərqlidir (system prompt dəstəyi məhdud və s.).
- **Uzun kontekst zəifliyi**: 128K-da "middle of context" forget problemi mövcuddur.

### PHP Ekosistem

```php
// OpenAI PHP SDK
use OpenAI;
$client = OpenAI::client(env('OPENAI_API_KEY'));
$result = $client->chat()->create([
    'model' => 'gpt-4o',
    'messages' => [['role' => 'user', 'content' => 'Hello']],
]);
```

`openai-php/client` paketi — PHP ekosisteminin ən yetkin LLM SDK-larından biri.

---

## Google Gemini

### Modellər

```
gemini-2.5-pro      → Flaqman. 2M token kontekst. Multimodal (video daxil).
gemini-2.5-flash    → Ucuz, sürətli default. Çox istifadə olunan.
gemini-2.5-flash-lite → Ən ucuz. Sadə tapşırıqlar.
```

### Güclü Tərəflər

- **Kontekst pəncərəsi**: 2M token — sənayedə ən böyük. Bütöv kod repo-su, saatlıq video, 1000+ səhifəlik PDF tək sorğuda.
- **Multimodal (video)**: native video anlama — digərlərində bu qədər güclü deyil.
- **Qiymət**: Flash modelləri çox ucuzdur.
- **Google Workspace inteqrasiyası**: Docs, Gmail, Drive üzərindən data.
- **Grounding with Google Search**: modelin cavabı real-time web ilə əlaqələndirmək.

### Zəif Tərəflər

- **API qeyri-sabitliyi**: tez-tez breaking change, versiya dəyişiklikləri.
- **Tool use keyfiyyəti**: Claude-dan aşağı.
- **Code reasoning**: Opus/o3-dən aşağı.
- **Over-refusal**: safety filters bəzən lazımsız yerdə işə düşür.
- **EU**: Vertex AI EU region mövcuddur, amma data processing şərtləri mürəkkəbdir.

---

## Open-Source Modellər

### Meta Llama

```
llama-3.3-70b       → Self-host, Mistral Large ilə rəqabət.
llama-4              → Daha yeni, MoE arxitektura.
```

Güclü: open weights, on-prem deployment, fine-tune (LoRA), data göndərmək yoxdur.
Zəif: self-host ağrısı, GPU xərci, ən yüksək keyfiyyətli deyil.

### DeepSeek

```
deepseek-v3         → Ümumi chat, kod, tool use. 20x ucuz API.
deepseek-r1         → o1-ə bənzər reasoning, MIT lisenziyası.
```

Güclü: çox ucuz, open weights, güclü reasoning (R1).
Zəif: Çin-əsaslı provayder (data residency sualları), API stabillik.

### Mistral

```
mistral-large-2     → GPT-4 səviyyəsinə yaxın.
codestral           → Kod modeli.
mistral-nemo        → Kiçik, ucuz.
```

Güclü: **Avropa şirkəti** — GDPR / EU AI Act üçün sadə, Azure-da mövcuddur.
Zəif: ekosistem Claude/GPT qədər geniş deyil.

### Qwen (Alibaba)

```
qwen-2.5-max        → Ümumi use.
qwen-2.5-coder      → Kod.
```

Güclü: çox dilli (Çin, Koreya, Yapon), open-source.
Zəif: Qərb məhsulları üçün compliance sualları.

---

## Kontekst Pəncərəsi Müqayisəsi

| Model | Kontekst | "Effective" Kontekst* |
|-------|----------|-----------------------|
| Claude Opus 4.5 | 200K | ~180K (yüksək recall) |
| Claude Sonnet 4.5 | 200K | ~180K |
| Claude Sonnet 4.5 (1M beta) | 1M | ~700K |
| GPT-4o | 128K | ~80K (middle-lost) |
| o3 | 200K | ~150K |
| Gemini 2.5 Pro | 2M | ~1M (video/audio üçün güclü) |
| Llama 3.3 70B | 128K | ~64K |
| DeepSeek V3 | 128K | ~80K |
| Mistral Large 2 | 128K | ~80K |

*"Effective" — "needle in haystack" testlərində retrieval accuracy 90%+ qaldığı uzunluq.

> **Dərs**: 1M token reklamı edilmək 1M-i düzgün istifadə etmək demək deyil. Real dünya use-case-lərində Claude və Gemini ilk 200K-da çox rəqabətlidir; Gemini-nin 2M üstünlüyü yalnız bütöv kod-base / saatlıq video kimi xüsusi ssenarilərdə təsirlidir.

---

## Qiymət Müqayisəsi

2026-04 etibarilə, $ / 1M token (input / output):

| Model | Input | Output | Cached Input | Batch API |
|-------|-------|--------|--------------|-----------|
| Claude Opus 4.5 | $15 | $75 | $1.50 | -50% |
| Claude Sonnet 4.5 | $3 | $15 | $0.30 | -50% |
| Claude Haiku 4.5 | $0.80 | $4 | $0.08 | -50% |
| GPT-4o | $2.50 | $10 | $1.25 | -50% |
| GPT-4o-mini | $0.15 | $0.60 | $0.075 | -50% |
| o3 | $10 | $40 | - | -50% |
| o3-mini | $1.10 | $4.40 | - | -50% |
| Gemini 2.5 Pro | $1.25 | $5 | $0.31 | -50% |
| Gemini 2.5 Flash | $0.075 | $0.30 | - | -50% |
| DeepSeek V3 | $0.27 | $1.10 | $0.07 | - |
| Mistral Large 2 | $2 | $6 | - | - |
| Llama 3.3 70B (Together.ai) | $0.88 | $0.88 | - | - |

### Müşahidələr

- **Claude Haiku vs GPT-4o-mini**: Haiku 5x bahalıdır, amma keyfiyyət fərqi tool-use üçün əhəmiyyətli.
- **Gemini Flash**: ən ucuz hyperscaler seçim, sadə klassifikasiya üçün ideal.
- **DeepSeek**: Sonnet keyfiyyətinə yaxın, 10x ucuz — amma data residency riski.
- **Prompt caching**: Claude 90%, OpenAI 50% — RAG / agent-də Claude iqtisadi şəkildə daha sərfəli.
- **Batch API**: 24 saatda işləmək olarsa bütün provayderlərdə 50% endirim.

---

## API Erqonomikası və SDK Dəstəyi

### PHP Developer Nöqteyi-Nəzərindən

| Provayder | Rəsmi PHP SDK | Community SDK | REST API | Streaming |
|-----------|---------------|---------------|----------|-----------|
| Anthropic | Yox (TS/Python) | `anthropic-sdk-php` | Sadə | SSE |
| OpenAI | Yox | `openai-php/client` (yetkin) | Sadə | SSE |
| Google | Yox | `google/cloud-aiplatform` | Mürəkkəb | SSE |
| Mistral | Yox | `mistral-ai-php` | Sadə | SSE |
| DeepSeek | Yox | OpenAI-uyğun | OpenAI API kimi | SSE |

**Praktik qayda**: PHP-də `Http` klienti ilə REST istifadə etmək hamısı üçün OK-dir. SDK-lar sadəcə convenience layer-dir.

### Bunu PHP-də Necə Edirsən

```php
// Bütün provayderlər üçün vahid abstraction
interface LlmProvider {
    public function complete(string $prompt, array $options = []): LlmResponse;
    public function stream(string $prompt, array $options = []): Generator;
}

class ClaudeProvider implements LlmProvider { /* ... */ }
class OpenAIProvider implements LlmProvider { /* ... */ }
class GeminiProvider implements LlmProvider { /* ... */ }

// Environment-dən provayder seçimi
$llm = app(LlmProvider::class); // binding .env-dən oxuyur
$response = $llm->complete($prompt);
```

Bu pattern Strategy / Adapter pattern-dir. Provayder dəyişmək production-da çox önəmlidir (fallback, A/B test, qiymət optimizasiyası).

---

## Tool Use Keyfiyyəti

Tool use (function calling) — agent sistemlərinin özəyi. Sənaye rəyi:

| Provayder | Parallel Tool Calls | Tool Selection Accuracy | Multi-step Consistency |
|-----------|---------------------|--------------------------|------------------------|
| Claude Sonnet 4.5 | Əla | Əla | Əla |
| Claude Opus 4.5 | Əla | Əla | Əla |
| GPT-4o | Yaxşı | Yaxşı | Orta |
| o3 | Yaxşı | Əla (reasoning) | Yaxşı |
| Gemini 2.5 Pro | Orta | Yaxşı | Orta |
| Llama 3.3 70B | Orta | Orta | Zəif |
| DeepSeek V3 | Yaxşı | Yaxşı | Orta |
| Mistral Large 2 | Yaxşı | Yaxşı | Orta |

> **Niyə Claude tool use-da liderdir?** Anthropic xüsusi olaraq agent use-case-lərinə investisiya edir — SWE-bench, Claude Code, Agent SDK, MCP. Bu, real world evaluation-lara əsaslanır.

---

## Multimodal Qabiliyyətlər

| Qabiliyyət | Claude | GPT-4o | Gemini 2.5 |
|------------|--------|--------|------------|
| Image understanding | Bəli | Bəli | Bəli |
| Image generation | Yox | Bəli (DALL-E) | Bəli (Imagen) |
| Audio input (native) | Sayman | Bəli (Realtime) | Bəli |
| Audio output (native) | Yox | Bəli (Realtime) | Bəli |
| Video understanding | Yox (frames) | Frames | Native video |
| PDF understanding | Native | Via vision | Native |
| OCR keyfiyyəti | Əla | Əla | Əla |

> **Use-case**: chatbot UI-ə səsli söhbət əlavə etmək lazımdırsa — GPT-4o Realtime API en yaxşıdır. Video analizi — Gemini. Sənəd emalı — Claude (PDF native + ən yaxşı reasoning).

---

## Rate Limits və Ölçəklənmə

### Anthropic (Tier-1 yeni hesab, 2026-04)

```
claude-sonnet-4-5:
  Requests per minute (RPM):       50
  Input tokens per minute (ITPM):  50,000
  Output tokens per minute (OTPM): 10,000

Tier-4 (enterprise, aylıq $5k+):
  RPM:   4,000
  ITPM:  2,000,000
  OTPM:  400,000
```

### OpenAI

```
GPT-4o Tier-1:
  RPM:  500
  TPM:  30,000

Tier-5:
  RPM:  10,000
  TPM:  30,000,000
```

### Gemini

```
gemini-2.5-pro (Free tier):
  RPM: 2
  
Paid:
  RPM: 1,000
  TPM: 2,000,000
```

> **PHP Praktik Qeyd**: Laravel `RateLimited` middleware + `ThrottlesExceptions` + queue-based retry pattern qurmadan production-a çıxma. Fəsil 14 (Rate Limits, Retries və Backoff) bu mövzunu detallı əhatə edir.

---

## EU / GDPR / Uyğunluq

| Provayder | EU Data Residency | DPA | GDPR-friendly | HIPAA | SOC 2 |
|-----------|--------------------|-----|---------------|-------|-------|
| Anthropic direct | Yox (US) | Bəli | Orta | Yox | Bəli |
| Claude via AWS Bedrock EU | Bəli (eu-central-1) | Bəli | Əla | BAA | Bəli |
| Claude via GCP Vertex EU | Bəli | Bəli | Əla | Bəli | Bəli |
| OpenAI | EU Data Residency (Enterprise) | Bəli | Yaxşı | BAA | Bəli |
| Azure OpenAI | Bəli | Bəli | Əla | Bəli | Bəli |
| Gemini via Vertex | Bəli | Bəli | Yaxşı | Bəli | Bəli |
| Mistral (FR əsaslı) | Bəli (native) | Bəli | Əla | - | Bəli |
| DeepSeek | Yox (CN) | - | Zəif | Yox | Yox |

**Praktik qayda**: EU şirkətində GDPR kritikdirsə:
- **Azure OpenAI** (GPT modeli, EU region) — ən sadə corporate choice.
- **AWS Bedrock + Claude** — Anthropic modeli EU-da.
- **Mistral la Plateforme** — Avropa əsaslı, native uyğunluq.

---

## Fine-Tuning Mövcudluğu

| Model | Self-Serve FT | Enterprise FT | LoRA / Adapter |
|-------|---------------|---------------|----------------|
| Claude Haiku 4.5 | Yox | Bəli (AWS Bedrock) | - |
| GPT-4o-mini | Bəli | Bəli | - |
| Gemini 2.5 Flash | Bəli (Vertex) | Bəli | - |
| Llama 3.3 | - | - | Bəli (self-host) |
| Mistral | Bəli | Bəli | Bəli |

> **Real dünya dərs**: 2026-da fine-tuning çox vaxt yanlış cavabdır. RAG + good prompting + few-shot examples 90% use-case-lərdə fine-tuning-dən üstündür. Fine-tuning üçün: spesifik format (JSON schema reliability), çox dar domain dili (tibbi jarqon), və ya latency optimization.

---

## Ekosistem

### Model Context Protocol (MCP)

Anthropic tərəfindən yaradılmış açıq standart. Claude Code, Claude Desktop, bəzi IDE-lər, third-party tool-lar MCP serverlərinə qoşulur.

```
MCP Server
    ↓
Claude (Anthropic)
Claude Desktop
Cursor IDE
Zed
... hər şey eyni standart vasitəsilə qoşulur
```

OpenAI-də rəqibi yoxdur. Gemini özünün proprietary `tools` API-sini istifadə edir.

### Agent SDK

- **Anthropic**: Claude Agent SDK (TypeScript/Python) — rəsmi, production-ready.
- **OpenAI**: Swarm (eksperimental), Assistants API (yarı-deprecated).
- **Gemini**: Agent Development Kit (yeni, hələ yetişməyib).
- **Open**: LangChain, LlamaIndex — multi-provider abstraction.

### IDE / Developer Tools

```
Claude Code       — rəsmi Anthropic CLI
Cursor            — bütün provayderlərə pulu dəstəkləyir
Windsurf          — multi-provider
GitHub Copilot    — OpenAI əsaslı (+ Claude opsiyası)
Cody (Sourcegraph)— multi-provider
```

---

## Qərar Matrisi

Tapşırığınızın əsas kriteriyasını tapın, sütunu izləyin:

| Kriter | Qalib | İkinci yer |
|--------|-------|-----------|
| Kod yazma / SWE | Claude Sonnet 4.5 | o3 |
| Reasoning / math | o3 | Claude Opus 4.5 |
| Uzun kontekst (video) | Gemini 2.5 Pro | Claude Sonnet (1M) |
| Uzun kontekst (mətn) | Claude Sonnet | Gemini 2.5 |
| Ucuz klassifikasiya | Gemini 2.5 Flash | Claude Haiku |
| Realtime audio | GPT-4o Realtime | Gemini |
| Tool use / agent | Claude Sonnet 4.5 | o3 |
| EU compliance | Mistral / Azure OpenAI | Bedrock Claude |
| On-prem | Llama 3.3 | Mistral |
| Ən ucuz | DeepSeek | Gemini Flash |
| Multimodal (image) | GPT-4o | Claude Sonnet |
| Structured JSON | GPT-4o (strict) | Claude Sonnet |
| Enterprise SLA | Azure OpenAI | AWS Bedrock |

---

## Use-Case-ə Görə Tövsiyələr

### 1. Müştəri Dəstəyi Chatbot (PHP/Laravel)

```
Birinci seçim:  Claude Sonnet 4.5
İkinci seçim:   GPT-4o-mini (qiymət kritikdirsə)
Niyə:           Tool use (ticket creation, FAQ lookup) yüksək keyfiyyət,
                system prompt-a dəqiq əməl edir, uzun müştəri tarixçəsi
                üçün kontekst pəncərəsi.
Aylıq qiymət (~10k ticket): ~$200-400
```

### 2. RAG Sistemi (bilik bazası, sənədlər)

```
Birinci seçim:  Claude Sonnet 4.5 + prompt caching
İkinci seçim:   Gemini 2.5 Flash (çox böyük kontekst üçün)
Niyə:           Prompt caching 90% input endirimi (sistem prompt +
                kontekst sənədləri dəfələrlə istifadə olunur).
                Uzun kontekstdə accuracy yüksəkdir.
```

### 3. Kod Assistant (IDE integration)

```
Birinci seçim:  Claude Sonnet 4.5
İkinci seçim:   o3 (mürəkkəb refactoring üçün)
Niyə:           SWE-bench 70%+, tool use əla (file edit, run tests),
                Claude Code ecosystem, MCP dəstəyi.
```

### 4. Summarization (məqalələr, emails, meetings)

```
Birinci seçim:  Claude Haiku 4.5
İkinci seçim:   Gemini 2.5 Flash
Niyə:           Sadə tapşırıq, ucuz model uyğun. Yüksək həcm üçün
                batch API ilə əlavə 50% endirim.
Qiymət: ~$0.001-0.01 / document.
```

### 5. Klassifikasiya (sentiment, spam, category)

```
Birinci seçim:  Gemini 2.5 Flash
İkinci seçim:   Claude Haiku / GPT-4o-mini
Niyə:           Ən ucuz, sürətli. Structured output (JSON schema)
                ilə deterministik parsing. Fine-tuning variantı da
                mövcuddur.
```

### 6. Extraction (PDF → structured JSON)

```
Birinci seçim:  Claude Sonnet 4.5 (native PDF + reasoning)
İkinci seçim:   GPT-4o (vision-əsaslı)
Niyə:           Claude native PDF parsing edir, vision + text birlikdə
                işləyir. Mürəkkəb tablar, form-lar üçün accuracy yüksək.
```

### 7. Agent (multi-step, tool use, planning)

```
Birinci seçim:  Claude Sonnet 4.5 + Agent SDK
İkinci seçim:   o3 (reasoning-heavy agent-lər)
Niyə:           Sənayədə ən yaxşı tool use, parallel calls, subagent-lər.
                MCP protokolu integrasiyanı asanlaşdırır. Production
                agent-lərin əksəriyyəti Claude üzərində qurulur.
```

---

## Finalda: Portfel Yanaşması

Production PHP sistemində tək provayderə **sadiq qalmayın**. Portfel:

```
Default:              Claude Sonnet 4.5 (yüksək keyfiyyət, tool use)
Ucuz tapşırıqlar:     Claude Haiku / Gemini Flash
Müxtəlifləşdirmə:     OpenAI fallback (Anthropic outage üçün)
Batch işlər:          Claude batch API (50% endirim)
On-prem həssas:       Llama 3.3 (self-host)
```

Laravel-də bu şəkildə:

```php
// config/services.php
'llm' => [
    'primary' => env('LLM_PRIMARY', 'claude-sonnet-4-5'),
    'fallback' => env('LLM_FALLBACK', 'gpt-4o'),
    'cheap' => env('LLM_CHEAP', 'claude-haiku-4-5'),
],

// Service
$llm->primary()->complete($prompt);
// Primary fail olsa:
$llm->fallback()->complete($prompt);
// Yüksək həcmli klassifikasiya üçün:
$llm->cheap()->complete($classificationPrompt);
```

Arxitektura trade-off-ları yaxşı anladıqda, provayder seçimi statik deyil dinamik qərar olur. Use-case başına doğru model. Business metric-lərinizə görə tənzimlənir.

---

## Yekun Fikirlər

- **Claude Sonnet 4.5** — PHP backend-li məhsul üçün default. 80% use-case-i örtür.
- **GPT-4o** — multimodal (audio/image generation) və ChatGPT ekosistemi üçün.
- **Gemini 2.5** — Google Workspace / GCP istifadəçiləri + ultra-long context.
- **Open-source** — on-prem, data residency, ya da ultra-ucuz use-case üçün.
- **Portfel yanaşması** — bir provayderə bağlı olmayın, fallback və cost-optimization üçün multi-provider.

Növbəti fəsillər: **10-model-selection-decision** (Opus vs Sonnet vs Haiku seçimi) və **11-llm-pricing-economics** (AI feature-ın unit economics-i).
