# Agentic RAG: Retrieval-i Tool Kimi — ReAct, Self-Query və Adaptive Retrieval

> **Oxucu kütləsi:** Senior backend developerlər və arxitektorlar — Claude Agent SDK və ya oxşar agentic framework-lərlə RAG sistemi qurur.
> **Bu faylın qonşu fayllarla fərqi:**
> - `03-rag-architecture.md` — klassik "retrieve → generate" pipeline. Bu fayl **retrieve-i tool çevirən** paradiqmanı təsvir edir.
> - `05-query-transformation-hyde.md` — sorğu transformasiyaları (bir dəfə). Bu fayl **agent-in iterativ** şəkildə sorğu dəyişdirdiyi pattern-ları göstərir.
> - `09-long-context-vs-rag.md` — "bir dəfəlik" retrieval alternativləri. Bu fayl **çoxaddımlı** retrieval strategiyaları haqqındadır.
> - `11-rag-evaluation-rerank.md` — klassik RAG eval. Burada **trajectory eval** — agent-in qərarlarını qiymətləndirmək.
> - `/home/orkhan/Projects/claude/ai/05-agents/06-claude-agent-sdk-deep.md` — Agent SDK-nın dərin mexanikası. Bu fayl spesifik olaraq RAG use case-i üçün Agent SDK istifadəsini təsvir edir.

---

## Mündəricat

1. Klassik RAG vs Agentic RAG
2. Retrieval-as-Tool pattern-ı
3. Self-Query Retriever — filter generation-dan NL sorğu
4. Multi-Hop Retrieval — iterative refinement
5. Adaptive Retrieval — "lazımdır ya yox" qərarı
6. Corrective RAG (CRAG) — retrieval keyfiyyətinin qiymətləndirilməsi
7. Self-RAG — cavab özü-özünü critique edir
8. Nə vaxt agentic klassik-dən üstündür
9. Nə vaxt klassik RAG kifayətdir
10. Laravel + Claude Agent SDK implementation
11. Latency büdcəsi və cost explosion riskləri
12. Observability: agentic trace-lər
13. Trajectory eval — agent-in doğru qərar verdiyini ölçmək
14. Anti-pattern-lar və qərar cədvəli

---

## 1. Klassik RAG vs Agentic RAG

### 1.1 Klassik RAG — retrieval sabitdir

```
query ──► retrieve (top-5) ──► augment ──► generate ──► answer
```

Bu pipeline-da:
- **Nə vaxt**: Həmişə retrieval edilir
- **Nəyi**: Sorğunun embedding-i ilə ən yaxın 5 chunk
- **Necə**: Tək axtarış, tək index
- **Neçə dəfə**: Bir dəfə

LLM-in rolu: **passiv**. Retrieval-ın qarşısında və ya onu istiqamətləndirməkdə iştirak etmir.

### 1.2 Agentic RAG — retrieval dinamikdir

```
query ──► agent ──► should I retrieve? ──┐
            ▲                             │ yes
            │                             ▼
            │                        search(q1)
            │                             │
            │                             ▼
            │                        results_1
            │                             │
            │                             ▼
            └─◄── enough info? ◄──── reason
                     │
                     │ no
                     ▼
                 search(q2 derived from results_1)
                     │
                     ▼
                 results_2
                     │
                     ▼
                  reason
                     │
                     ▼
                 final answer
```

Bu arxitekturada:
- **Nə vaxt**: Agent qərar verir — retrieval lazımdır ya yox
- **Nəyi**: Hər iterasiyada fərqli sorğu, fərqli filter, fərqli index
- **Necə**: Tool call kimi — LLM `search_docs(query)` funksiyasını çağırır
- **Neçə dəfə**: 0-10+ dəfə, budget-ə görə

LLM-in rolu: **aktiv**. Retrieval strategiyasını dinamik seçir.

### 1.3 Niyə bu dəyişiklik vacibdir

Real istifadəçi sualları klassik RAG-ın pipeline-na yaxşı uyğun gəlmir:

| Sual tipi | Klassik RAG | Agentic RAG |
|-----------|-------------|-------------|
| "Salam!" | Lazımsız retrieval, 5 noisy chunk | Agent retrieval skip edir |
| "Apple Q3 revenue?" | Tək retrieval kifayətdir | Agent tək retrieval edir (overhead yox) |
| "Apple vs Google Q3 revenue comparison" | Tək retrieval 2 şirkətin məlumatını tapmaz | Agent 2 ayrı retrieval + compare |
| "Son dəyişiklik nə oldu?" | Retrieval etməli ola bilər, amma hansı sənəd? | Agent metadata filter (date) istifadə edir |
| "Bu error-u necə həll edim?" (sənəddə yoxdur) | 5 irrelevant chunk qaytarır | Agent "cavab yoxdur" qərar verir və web search fallback |

---

## 2. Retrieval-as-Tool Pattern

### 2.1 Əsas ideya

Retrieval-ı pipeline-ın sabit addımı etmək əvəzinə, LLM-ə "search" tool-u ver. LLM tool-u lazım gəldiyində çağırır.

```python
# Tool definition
{
  "name": "search_docs",
  "description": "Search the company knowledge base. Use when you need specific information from internal docs.",
  "input_schema": {
    "type": "object",
    "properties": {
      "query": {"type": "string", "description": "Search query"},
      "filter": {
        "type": "object",
        "properties": {
          "document_type": {"type": "string", "enum": ["policy", "manual", "faq"]},
          "date_after": {"type": "string", "format": "date"}
        }
      }
    },
    "required": ["query"]
  }
}
```

Agent loop:
```
system: "You have access to search_docs tool. Use it when needed."
user: "What's our refund policy?"
  │
  ▼
assistant: [thinking: need to search internal docs]
           tool_call: search_docs({"query": "refund policy"})
  │
  ▼
tool_result: [3 chunks from refund policy doc]
  │
  ▼
assistant: "Based on the policy, you can request a full refund within 30 days..."
```

### 2.2 Multi-tool agent

Tək search yetməyəndə — bir neçə ayrı tool expose et:

```
Tools:
- search_docs(query, filter)          # Confluence/wiki
- query_database(sql)                 # Analytical DB
- lookup_customer(customer_id)        # CRM
- search_code(query, repo)            # GitHub
- get_ticket(ticket_id)               # JIRA
- web_search(query)                   # Public web fallback
```

Agent hər sorğuya görə **hansı tool-u istifadə edəcəyini** özü seçir:

```
user: "Customer X barədə açıq ticket-lər nədir və son aldıqları məhsul hansıdır?"
  │
  ▼
agent: [plan: 2 tool call lazımdır]
  │
  ├─► lookup_customer("X") ──► customer info
  ├─► get_ticket filter by customer="X", status="open" ──► tickets
  └─► query_database("SELECT product FROM orders WHERE customer='X' ORDER BY date DESC LIMIT 1")
  │
  ▼
agent: synthesizes all three results
```

### 2.3 Tool design prinsipləri

1. **Tək məsuliyyət**: Hər tool bir işi edir. `search_docs` search edir, filter etmir — filter parametri var.
2. **Sərt schema**: JSON schema ilə input/output dəqiq müəyyən et. Parsing error-lar azalır.
3. **Error response-ları**: Tool uğursuz olduqda strukturlaşdırılmış error qaytar. Agent buna görə planı dəyişə bilir.
4. **Token-efficient output**: 100K token chunk qaytarma — agent bunu emal etmək üçün çox token sərf edir. Tool-da daxili pagination, summarization.
5. **İdempotent**: Eyni input eyni output qaytarmalıdır (cache üçün).

---

## 3. Self-Query Retriever — Filter Generation

### 3.1 Problem

İstifadəçi: "2024-cü ilin ikinci yarısında yazılmış backend arxitektura sənədləri".

Klassik RAG bunu tək embedding kimi axtarır və filter-siz heç nə tapmır. Filter əl ilə qurulmalıdır — amma istifadəçi SQL yazmır.

### 3.2 Self-Query pattern

LLM-dən natural language sorğudan **iki komponent** çıxartmasını istə:
1. Semantic search query
2. Structured metadata filter

```
user: "2024-cü ilin ikinci yarısında yazılmış backend arxitektura sənədləri"
  │
  ▼
LLM extracts:
  query: "backend architecture documents"
  filter:
    document_type: "architecture"
    date_after: "2024-07-01"
    date_before: "2024-12-31"
  │
  ▼
retrieval: vector search on query, WHERE filter applied
  │
  ▼
results with both semantic relevance AND metadata match
```

### 3.3 Schema description prompt

LLM-ə metadata schema-sını izah et:

```
You have access to a knowledge base with the following metadata fields:

Fields:
- document_type: string, one of [policy, manual, faq, architecture, meeting-notes]
- author: string, person's name
- created_at: date, YYYY-MM-DD format
- tags: array of strings
- team: string, one of [backend, frontend, infra, product]

When given a user query, extract:
1. "query": semantic search terms (natural phrasing)
2. "filter": structured filter using only the fields above

If no filter is inferable, omit it.

Return as JSON.
```

### 3.4 Implementation

```php
<?php
// app/Services/RAG/Agentic/SelfQueryRetriever.php

namespace App\Services\RAG\Agentic;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class SelfQueryRetriever
{
    private const SCHEMA_PROMPT = <<<'PROMPT'
You parse user queries into structured search parameters.

Knowledge base metadata fields:
- document_type: [policy, manual, faq, architecture, meeting-notes]
- author: string
- created_at: date YYYY-MM-DD
- tags: array of strings
- team: [backend, frontend, infra, product]

Extract a JSON object:
{
  "query": "semantic search query",
  "filter": { "document_type": "...", "date_after": "...", ... }
}

Omit "filter" if no constraints are inferable. Return ONLY JSON.
PROMPT;

    public function retrieve(string $userQuery, int $topK = 10): array
    {
        // 1. LLM ilə query + filter çıxart
        $response = Http::withHeaders([
            'x-api-key' => config('services.anthropic.key'),
            'anthropic-version' => '2023-06-01',
        ])->post('https://api.anthropic.com/v1/messages', [
            'model' => 'claude-haiku-4-5',
            'max_tokens' => 512,
            'system' => self::SCHEMA_PROMPT,
            'messages' => [['role' => 'user', 'content' => $userQuery]],
        ]);

        $parsed = json_decode($response->json('content.0.text'), true);

        if (!$parsed) {
            // Fallback: raw query, no filter
            return $this->rawVectorSearch($userQuery, $topK);
        }

        $semanticQuery = $parsed['query'] ?? $userQuery;
        $filter = $parsed['filter'] ?? [];

        // 2. Filter ilə vector search
        return $this->filteredVectorSearch($semanticQuery, $filter, $topK);
    }

    private function filteredVectorSearch(string $query, array $filter, int $topK): array
    {
        $embedding = app(\App\Services\AI\EmbeddingService::class)->embed($query);
        $vecStr = '[' . implode(',', $embedding) . ']';

        $sql = "SELECT id, content, metadata, 1 - (embedding <=> ?) as score
                FROM knowledge_chunks
                WHERE embedding IS NOT NULL";
        $params = [$vecStr];

        if (isset($filter['document_type'])) {
            $sql .= " AND metadata->>'document_type' = ?";
            $params[] = $filter['document_type'];
        }
        if (isset($filter['team'])) {
            $sql .= " AND metadata->>'team' = ?";
            $params[] = $filter['team'];
        }
        if (isset($filter['author'])) {
            $sql .= " AND metadata->>'author' = ?";
            $params[] = $filter['author'];
        }
        if (isset($filter['date_after'])) {
            $sql .= " AND (metadata->>'created_at')::date >= ?";
            $params[] = $filter['date_after'];
        }
        if (isset($filter['date_before'])) {
            $sql .= " AND (metadata->>'created_at')::date <= ?";
            $params[] = $filter['date_before'];
        }

        $sql .= " ORDER BY embedding <=> ? LIMIT ?";
        $params[] = $vecStr;
        $params[] = $topK;

        return DB::select($sql, $params);
    }

    private function rawVectorSearch(string $query, int $topK): array
    {
        // Standart vector search (filter yox)
        return [];
    }
}
```

### 3.5 Validation və injection prevention

LLM bəzən schema-da olmayan field generasiya edir və ya SQL injection-a bənzəyən value çıxarır. Filter-i **whitelist etməlisən**:

```php
private function validateFilter(array $filter): array
{
    $allowed = ['document_type', 'author', 'date_after', 'date_before', 'team', 'tags'];
    $allowedTypes = ['policy', 'manual', 'faq', 'architecture', 'meeting-notes'];
    $allowedTeams = ['backend', 'frontend', 'infra', 'product'];

    $clean = array_intersect_key($filter, array_flip($allowed));

    if (isset($clean['document_type']) && !in_array($clean['document_type'], $allowedTypes)) {
        unset($clean['document_type']);
    }
    if (isset($clean['team']) && !in_array($clean['team'], $allowedTeams)) {
        unset($clean['team']);
    }
    // Date validation
    foreach (['date_after', 'date_before'] as $key) {
        if (isset($clean[$key]) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $clean[$key])) {
            unset($clean[$key]);
        }
    }

    return $clean;
}
```

---

## 4. Multi-Hop Retrieval — Iterative Refinement

### 4.1 Problem

Sual: "CTO-muzun yazdığı axırıncı arxitektura qeydini tap."

Tək retrieval:
- Embedding "CTO arxitektura qeydi" ilə axtarır
- Amma sənədlərdə CTO-nun adı yoxdur — onun kim olduğu ayrı faktdır
- Retrieval pis nəticələr qaytarır

Multi-hop agent:
1. search("kim CTO-dur") → HR sənədindən "John Doe, CTO"
2. search("John Doe architecture notes") → tapılır
3. ən son tarixə görə filter et

### 4.2 Agent-ın iterative pattern-ı

```
system: "You can search docs multiple times. Use each search result to inform the next query."

user: "CTO-muzun yazdığı axırıncı arxitektura qeydini tap."

turn 1:
  assistant: [thinking: need to identify who CTO is first]
  tool_call: search_docs("who is CTO")

turn 2:
  tool_result: "John Doe is the Chief Technology Officer..."
  assistant: [thinking: now search for John Doe's architecture notes]
  tool_call: search_docs("John Doe architecture", filter={document_type: architecture})

turn 3:
  tool_result: [5 architecture docs by John Doe]
  assistant: [thinking: need the latest, sort by date]
  tool_call: search_docs("John Doe architecture", filter={document_type: architecture, order: date_desc, limit: 1})

turn 4:
  tool_result: [1 doc]
  assistant: "The latest architecture note by our CTO, John Doe, is..."
```

### 4.3 Multi-hop control parameters

- **Max iterations**: Agent sonsuz dövr yaratmasın (default: 5-10)
- **Budget (token / $$)**: Tool call sayı + prompt ölçüsü büdcəyə sığsın
- **Timeout**: Ümumi iş vaxtı limit (15-30 sec typical)
- **Failure recovery**: Tool uğursuz olduqda agent graceful degrade olmalıdır

### 4.4 Multi-hop-un fayda/zərər

**Fayda**: Complex sorğular avtomatik resolve olunur.
**Zərər**:
- Latency 5-10× artır
- Xərc 5-20× artır
- Error compounding (bir səhv iteration hamını pozur)

---

## 5. Adaptive Retrieval — "Lazımdır ya Yox" Qərarı

### 5.1 Motivasiya

Klassik RAG hər sorğuda retrieval edir — hətta "Salam" deyəndə də. Bu:
- Latency artırır (300 ms boş yerə)
- Xərc artırır (embedding + LLM context)
- Keyfiyyəti pisləşdirir (noisy chunks LLM-i çaşdırır)

### 5.2 Adaptive pattern

Agent system prompt-unda retrieval-ın **opsional** olduğunu bildir:

```
You have access to the search_docs tool. Use it ONLY when:
- The user asks for specific internal information
- The question cannot be answered from general knowledge
- You need to verify a fact from company docs

DO NOT use it for:
- Greetings ("hi", "thanks")
- Generic questions you can answer from training
- Clarifications about the user's own request
- Simple computations
```

### 5.3 Classifier-based adaptive

Alternativ: Tool çağırmaq üçün LLM-ə etibar etmir, ayrı Haiku classifier istifadə et:

```php
public function shouldRetrieve(string $query): bool
{
    $response = Http::withHeaders([...])->post('/v1/messages', [
        'model' => 'claude-haiku-4-5',
        'max_tokens' => 10,
        'messages' => [[
            'role' => 'user',
            'content' => <<<PROMPT
Does this user query require searching company internal documents?
Answer only YES or NO.

Query: "{$query}"
PROMPT,
        ]],
    ]);

    return str_starts_with(trim($response->json('content.0.text')), 'YES');
}
```

Bu ucuz (Haiku call ~$0.0005) və sürətli (~150 ms), amma ikiqat LLM çağırışı yaradır. Built-in adaptive (LLM özü qərar verir) bəzən daha səmərəli.

### 5.4 Adaptive nəticələri

Production data (customer support bot):
- Sorğuların 35%-i retrieval tələb etmir (greeting, clarification, generic)
- Adaptive retrieval ilə ümumi xərc 30% azaldı
- Keyfiyyət dəyişmədi (və ya marginal yaxşılaşdı)

---

## 6. Corrective RAG (CRAG)

### 6.1 Əsas ideya

Yao et al., 2024 ("Corrective Retrieval Augmented Generation"). Retrieval nəticələrini **qiymətləndir**, keyfiyyətə görə dəyişik mənbələrə müraciət et.

Pipeline:
```
query
  │
  ▼
retrieve (top-5)
  │
  ▼
evaluator: relevance assessment
  │
  ├─ "correct"    ──► use as-is
  ├─ "ambiguous"  ──► refine and retrieve again
  └─ "incorrect"  ──► web search fallback
  │
  ▼
final answer
```

### 6.2 Relevance evaluator

Haiku ilə retrieval nəticələrini yoxla:

```
For each retrieved chunk, rate its relevance to the query on a scale:
- correct (3): directly answers the query
- partial (2): contains related information
- incorrect (1): not relevant

If ALL chunks are <2, mark "fallback_needed".
If mixed, mark "refine_needed".
If ≥2 chunks are 3, mark "good".

Return JSON: { "label": "good|refine|fallback", "reasoning": "..." }
```

### 6.3 Web search fallback

Retrieval keyfiyyəti pisdirsə, agent **dərhal halüsinasiya etmir** — external source-a müraciət edir:

```php
if ($evaluation['label'] === 'fallback') {
    $webResults = $this->webSearchTool->search($query);
    return $this->generateWithContext($query, $webResults, source: 'web');
} elseif ($evaluation['label'] === 'refine') {
    // Re-query with HyDE or different strategy
    $newResults = $this->hydeRetriever->retrieve($query);
    return $this->generateWithContext($query, $newResults, source: 'internal-refined');
}
```

### 6.4 Transparency

İstifadəçiyə mənbəni göstər:
```
"Bu cavab internal sənədlərdən alınıb."
"Bu cavab web axtarışdan gəlir (internal docs-da tapılmadı)."
```

---

## 7. Self-RAG — Özünü Critique

### 7.1 Asai et al., 2023

Self-RAG paradigm-ı: LLM cavabı yaradır, sonra onu "critique" edir (faithfulness, completeness), lazım gəldiyində yenidən retrieve edir.

```
query ──► retrieve ──► draft answer ──► critique
                                          │
                           ┌──────────────┼──────────────┐
                           │              │              │
                           ▼              ▼              ▼
                      "supported"    "needs more"    "insufficient"
                           │              │              │
                           ▼              ▼              ▼
                     finalize       retrieve more    retrieve
                                    + rewrite        different
```

### 7.2 Critique prompt

```
Review the draft answer and the retrieved context. For each claim in the 
answer, indicate:
- SUPPORTED: claim is clearly backed by context
- PARTIAL: claim is partially backed
- UNSUPPORTED: claim is not in context (potential hallucination)

If any claim is UNSUPPORTED, identify what additional information is needed 
and propose a new retrieval query.

Return JSON:
{
  "claims": [
    {"text": "...", "support": "SUPPORTED|PARTIAL|UNSUPPORTED"},
    ...
  ],
  "verdict": "finalize|refine|retrieve_more",
  "additional_query": "..."  // only if verdict != finalize
}
```

### 7.3 Self-RAG-ın dəyəri

- Halüsinasiya dramatik azalır (50%+ reduction tipik)
- Amma 2-3× LLM call (generate + critique + opt. regenerate)
- Latency 2× artır
- Use case: yüksək-stake domain-lər (hüquqi, tibbi, maliyyə)

---

## 8. Nə Vaxt Agentic Klassik-dən Üstündür

| Ssenari | Klassik RAG | Agentic RAG | Qalib |
|---------|-------------|-------------|-------|
| Müəyyən FAQ (10 sual pattern-ı) | **Optimal** | Overkill | Klassik |
| Kompleks analiz sualları | Orta | **Güclü** | Agentic |
| Çox mənbəli sorğular (docs + DB + API) | Qeyri-mümkün | **Təbii** | Agentic |
| Multi-hop (A → B → C) | Uğursuz | **Düzgün** | Agentic |
| Cavab yoxdursa graceful fallback | Halüsinasiya | **Fallback** | Agentic |
| Metadata filter NL-dən | Əl ilə | **Avtomatik** | Agentic |
| Real-time chat | Sürətli | Yavaş | Klassik |
| Research assistant | Məhdud | **İdeal** | Agentic |
| Tight cost (100K+ q/day) | **Ucuz** | Bahalı | Klassik |
| Tight latency (<500 ms) | **Optimal** | Çətin | Klassik |
| Müxtəlif sorğu tipləri | Single strategy | **Adaptive** | Agentic |

---

## 9. Nə Vaxt Klassik RAG Kifayətdir

Agentic RAG yeni və cəlbedici olsa da, over-engineering riski çoxdur:

1. **Sual distributsiyası homogen-dirsə** — istifadəçilərin 95%-i oxşar FAQ sorur
2. **Budget sıxdırsa** — hər sorğuda 3-5 LLM call iqtisadi deyil
3. **Latency kritikdirsə** — chat UX, voice assistant
4. **Monitoring qurulmayıbsa** — agent-ın qərarları mürəkkəbdir, debugging çətindir
5. **Single-source data** — tək wiki varsa, multi-tool lazım deyil

**Praktik yanaşma**: Klassik RAG-la başla, eval et, harada uğursuzdursa agentic features əlavə et (incremental).

---

## 10. Laravel + Claude Agent SDK Implementation

### 10.1 Agent SDK setup

```php
<?php
// app/Services/RAG/Agentic/ResearchAgent.php

namespace App\Services\RAG\Agentic;

use Anthropic\Client as AnthropicClient;
use App\Services\RAG\HybridSearchService;
use App\Services\Analytics\MetricsQueryService;

class ResearchAgent
{
    private const MODEL = 'claude-sonnet-4-7';
    private const MAX_ITERATIONS = 8;
    private const TIMEOUT_SECONDS = 30;

    public function __construct(
        private AnthropicClient $anthropic,
        private HybridSearchService $search,
        private MetricsQueryService $metrics,
    ) {}

    public function run(string $userQuery, string $sessionId): array
    {
        $messages = [[
            'role' => 'user',
            'content' => $userQuery,
        ]];

        $iteration = 0;
        $startTime = microtime(true);
        $toolCalls = [];

        while ($iteration < self::MAX_ITERATIONS) {
            if ((microtime(true) - $startTime) > self::TIMEOUT_SECONDS) {
                throw new \RuntimeException('Agent timeout');
            }

            $response = $this->anthropic->messages()->create([
                'model' => self::MODEL,
                'max_tokens' => 2048,
                'system' => $this->systemPrompt(),
                'tools' => $this->toolDefinitions(),
                'messages' => $messages,
            ]);

            // Append assistant response to history
            $messages[] = [
                'role' => 'assistant',
                'content' => $response->content,
            ];

            // Check for tool use
            $toolUses = array_filter(
                $response->content,
                fn($block) => $block->type === 'tool_use'
            );

            if (empty($toolUses)) {
                // Agent finished
                $finalText = collect($response->content)
                    ->filter(fn($b) => $b->type === 'text')
                    ->pluck('text')
                    ->implode('');

                return [
                    'answer' => $finalText,
                    'iterations' => $iteration + 1,
                    'tool_calls' => $toolCalls,
                    'latency_ms' => (int)((microtime(true) - $startTime) * 1000),
                ];
            }

            // Execute tool calls
            $toolResults = [];
            foreach ($toolUses as $toolUse) {
                $result = $this->executeTool($toolUse->name, $toolUse->input);
                $toolCalls[] = [
                    'name' => $toolUse->name,
                    'input' => $toolUse->input,
                    'result_preview' => substr(json_encode($result), 0, 200),
                ];

                $toolResults[] = [
                    'type' => 'tool_result',
                    'tool_use_id' => $toolUse->id,
                    'content' => json_encode($result),
                ];
            }

            // Append tool results to continue loop
            $messages[] = [
                'role' => 'user',
                'content' => $toolResults,
            ];

            $iteration++;
        }

        throw new \RuntimeException('Max iterations exceeded');
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
You are a research assistant with access to the company's knowledge base 
and analytics database.

Guidelines:
- Use search_docs for qualitative questions (policies, procedures, docs).
- Use query_metrics for quantitative questions (counts, aggregations, trends).
- You may call tools multiple times to gather complete information.
- When the user asks about something you cannot verify, explicitly say so.
- Cite sources with [doc:ID] or [metric:name] notation.
- Do NOT use tools for greetings or generic questions.

Stop calling tools and provide a final answer when you have enough information.
PROMPT;
    }

    private function toolDefinitions(): array
    {
        return [
            [
                'name' => 'search_docs',
                'description' => 'Search internal documentation and knowledge base using semantic + keyword search. Returns top-5 most relevant chunks.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'Search query in natural language',
                        ],
                        'document_type' => [
                            'type' => 'string',
                            'enum' => ['policy', 'manual', 'faq', 'architecture'],
                            'description' => 'Optional filter by document type',
                        ],
                    ],
                    'required' => ['query'],
                ],
            ],
            [
                'name' => 'query_metrics',
                'description' => 'Query pre-defined business metrics (revenue, users, conversions, etc). Returns aggregated numbers.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'metric_name' => [
                            'type' => 'string',
                            'enum' => ['revenue', 'active_users', 'signups', 'churn_rate'],
                        ],
                        'period' => [
                            'type' => 'string',
                            'description' => 'Time period, e.g. "2024-Q3", "last_7_days"',
                        ],
                        'breakdown' => [
                            'type' => 'string',
                            'description' => 'Optional group-by dimension',
                        ],
                    ],
                    'required' => ['metric_name', 'period'],
                ],
            ],
        ];
    }

    private function executeTool(string $name, array $input): array
    {
        return match ($name) {
            'search_docs' => $this->executeSearchDocs($input),
            'query_metrics' => $this->executeQueryMetrics($input),
            default => ['error' => "Unknown tool: {$name}"],
        };
    }

    private function executeSearchDocs(array $input): array
    {
        $query = $input['query'] ?? '';
        $docType = $input['document_type'] ?? null;

        $candidates = $this->search->search(
            query: $query,
            topK: 5,
            candidateK: 30,
        );

        if ($docType) {
            $candidates = $candidates->filter(
                fn($c) => ($c['metadata']['document_type'] ?? null) === $docType
            )->values();
        }

        return [
            'results' => $candidates->map(fn($c) => [
                'doc_id' => $c['document_id'],
                'snippet' => substr($c['content'], 0, 800),
                'source' => $c['metadata']['document_title'] ?? 'unknown',
                'score' => $c['rrf_score'] ?? null,
            ])->all(),
        ];
    }

    private function executeQueryMetrics(array $input): array
    {
        try {
            $value = $this->metrics->get(
                metricName: $input['metric_name'],
                period: $input['period'],
                breakdown: $input['breakdown'] ?? null,
            );

            return [
                'metric_name' => $input['metric_name'],
                'period' => $input['period'],
                'value' => $value,
            ];
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }
}
```

### 10.2 HTTP controller

```php
<?php
// app/Http/Controllers/API/AgentController.php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\RAG\Agentic\ResearchAgent;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class AgentController extends Controller
{
    public function __construct(private ResearchAgent $agent) {}

    public function query(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => 'required|string|min:3|max:1000',
            'session_id' => 'nullable|string',
        ]);

        $sessionId = $validated['session_id'] ?? Str::uuid()->toString();

        try {
            $result = $this->agent->run($validated['query'], $sessionId);
        } catch (\RuntimeException $e) {
            return response()->json([
                'error' => 'agent_error',
                'message' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'answer' => $result['answer'],
            'session_id' => $sessionId,
            'iterations' => $result['iterations'],
            'tool_calls_count' => count($result['tool_calls']),
            'latency_ms' => $result['latency_ms'],
        ]);
    }
}
```

### 10.3 Queue for long-running agents

Agent loop 15-30 saniyə çəkə bilir. Sinxron HTTP request uyğun deyil (gateway timeout). İki pattern:

**Streaming**: Server-Sent Events ilə hər tool call-ı axınla göndər.
**Async + polling**: Agent-i queue-ya at, job_id qaytar, frontend polling et.

```php
// Async pattern
class RunAgentJob implements ShouldQueue
{
    public function __construct(
        public string $jobId,
        public string $query,
        public string $sessionId,
    ) {}

    public function handle(ResearchAgent $agent): void
    {
        $result = $agent->run($this->query, $this->sessionId);

        cache()->put("agent:{$this->jobId}", $result, now()->addMinutes(10));
    }
}
```

---

## 11. Latency Büdcəsi və Cost Explosion Riskləri

### 11.1 Per-iteration xərc

Hər agent iteration-da:
- LLM call: ~$0.01-0.05 (Sonnet) with input context
- Tool call: ~$0.001 (vector search)
- Cumulative context grows (prior messages + tool results)

### 11.2 Cost explosion misal

Agent 5 iteration-dan sonra:
- Context: 20K token (5 × 4K average)
- LLM input: 20K × $3/M = $0.06
- Output: 1K × $15/M = $0.015
- Per-iteration: ~$0.075
- 5 iteration total: ~$0.25 per query

Klassik RAG-la müqayisə ($0.02): **12×** bahalı.

### 11.3 Mitigations

1. **Haiku substitution**: Sonnet yerinə Haiku istifadə et (rahat task-lar). $0.25 → $0.05.
2. **Max iteration limit**: 8 iteration ciddi limitdir, 3-5 çox use case üçün kifayətdir.
3. **Budget circuit breaker**: Cost budget aşıldıqda fallback to simple RAG.
4. **Compact previous turns**: İlk turn-ların summary-sini saxla, tam tekst yox.
5. **Parallel tool calls**: Agent paralel tool call edə bilirsə (Claude dəstəkləyir), 1 turn-da 3 tool execute olur.

```php
// Circuit breaker
if ($estimatedCost > $maxBudget) {
    Log::warning('Agent budget exceeded, falling back to simple RAG');
    return $this->simpleRag->answer($userQuery);
}
```

### 11.4 Latency büdcəsi

| Iteration count | Tipik latency |
|-----------------|---------------|
| 1 | 1.5-3 s |
| 3 | 5-9 s |
| 5 | 10-15 s |
| 8 | 20-30 s |

Chat UX-də 5+ saniyə acceptable deyil — streaming və "still searching..." indicator göstər.

---

## 12. Observability: Agentic Trace-lər

### 12.1 Trace schema

Hər agent run-da bütün qərarları log et:

```php
Schema::create('agent_traces', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('session_id');
    $table->text('user_query');
    $table->text('final_answer')->nullable();
    $table->integer('iterations');
    $table->integer('total_latency_ms');
    $table->decimal('total_cost_usd', 10, 6);
    $table->string('status'); // completed, timeout, budget_exceeded, error
    $table->timestamps();
});

Schema::create('agent_tool_calls', function (Blueprint $table) {
    $table->id();
    $table->uuid('trace_id');
    $table->integer('iteration');
    $table->string('tool_name');
    $table->jsonb('input');
    $table->jsonb('output_preview');
    $table->integer('latency_ms');
    $table->foreign('trace_id')->references('id')->on('agent_traces')->cascadeOnDelete();
});
```

### 12.2 Tracing in code

```php
private function logTrace(string $userQuery, array $result): void
{
    $traceId = Str::uuid()->toString();

    DB::transaction(function () use ($traceId, $userQuery, $result) {
        AgentTrace::create([
            'id' => $traceId,
            'session_id' => $result['session_id'],
            'user_query' => $userQuery,
            'final_answer' => $result['answer'],
            'iterations' => $result['iterations'],
            'total_latency_ms' => $result['latency_ms'],
            'total_cost_usd' => $result['cost_usd'] ?? 0,
            'status' => 'completed',
        ]);

        foreach ($result['tool_calls'] as $idx => $call) {
            AgentToolCall::create([
                'trace_id' => $traceId,
                'iteration' => $idx,
                'tool_name' => $call['name'],
                'input' => $call['input'],
                'output_preview' => ['text' => $call['result_preview']],
                'latency_ms' => $call['latency_ms'] ?? 0,
            ]);
        }
    });
}
```

### 12.3 Trace viewer

Admin UI-da trace-i göstər — debugging və human review üçün kritik:

```
Trace #abc-123
User query: "CTO-muzun son arxitektura qeydi?"
Status: completed  |  Iterations: 3  |  Latency: 7.2s  |  Cost: $0.14

Iteration 1:
  Tool: search_docs({"query": "who is CTO"})
  Result preview: "John Doe, CTO since 2022..."

Iteration 2:
  Tool: search_docs({"query": "John Doe architecture", "document_type": "architecture"})
  Result preview: [5 docs found]

Iteration 3:
  (no tool call)
  Answer: "The latest architecture note by our CTO, John Doe, is..."
```

---

## 13. Trajectory Eval — Agent-in Düzgün Qərar Verdiyini Ölçmək

### 13.1 Klassik RAG eval çatışmır

Klassik eval (fayl 11): answer faithfulness, retrieval MRR. Amma agent-də suallar:
- Agent doğru tool-ları seçdi?
- İteration count adekvatdır?
- Redundant tool call yox idi?
- Graceful stop edir?

Bu metriklər tələb olunur: **trajectory eval**.

### 13.2 Trajectory metrikaleri

| Metric | Məna |
|--------|------|
| Tool accuracy | Doğru tool seçimi (vs ground truth) |
| Redundancy | Eyni tool çağırışları təkrar |
| Stopping | Erkən stop vs lazımsız davam |
| Iteration efficiency | Min iteration-larda düzgün cavab |
| Fallback correctness | Cavab olmayanda "bilmirəm" deyir |

### 13.3 Gold trajectories

Gold set-də hər sorğu üçün **expected trajectory**-ni define et:

```php
// Sorğu: "Refund policy nədir?"
$expectedTrajectory = [
    ['tool' => 'search_docs', 'query_matches' => 'refund'],
    ['final_answer', 'contains' => ['30 days', 'refund']],
];

// Sorğu: "Salam"
$expectedTrajectory = [
    ['final_answer', 'no_tool_calls' => true],
];
```

Eval command gold trajectories ilə real trajectory-ni müqayisə edir, mismatch-ları işarələyir.

### 13.4 LLM-as-judge for trajectory

Daha mürəkkəb halda LLM trajectory-ni qiymətləndirir:

```
Review the agent's trajectory for this query. Rate:
- efficiency (1-5): minimal tool calls
- correctness (1-5): used right tools
- stopping (1-5): stopped at right moment
- overall (1-5)

Query: {query}
Trajectory: {tool_calls_json}
Final answer: {answer}

Return JSON with scores and brief reasoning.
```

---

## 14. Anti-Pattern-lar və Qərar Cədvəli

### 14.1 Anti-pattern-lar

1. **Hər use case-də agentic**
   - 90% sorğu single retrieval ilə həll olunur. Over-engineering.
   - Həll: Klassik RAG default, complex case-lər üçün agent fallback.

2. **Tool çoxluğu**
   - 20+ tool-lu agent — LLM confused olur, səhv tool seçir.
   - Həll: 3-5 tool-la başla, lazımdırsa əlavə et.

3. **Vague tool descriptions**
   - "search the database" — LLM hansı DB-də? nə zaman? bilmir.
   - Həll: Dəqiq, nümunə ilə description.

4. **No iteration limit**
   - Agent sonsuz dövrə girə bilər.
   - Həll: Max 5-8 iteration + timeout.

5. **No cost budget**
   - Bir ağır sorğu $10 xərcləyə bilir.
   - Həll: Per-query budget + circuit breaker.

6. **No observability**
   - Agent niyə pis cavab verdiyini anlayamazsan.
   - Həll: Trace logging + admin viewer.

7. **Ignoring parallel tool calls**
   - Agent 3 tool-u sequential çağırır, paralel edə bilərdi.
   - Həll: System prompt-da "use parallel tool calls when possible".

8. **No fallback plan**
   - Tool fail olursa agent halüsinasiya edir.
   - Həll: Tool error response-unda agent-ə plan B təklif et.

### 14.2 Qərar cədvəli

| Use case | Yanaşma | Səbəb |
|----------|---------|-------|
| FAQ bot, 100+ q/s | Klassik RAG | Latency, cost |
| Research assistant | Agentic | Multi-hop, synthesis |
| Customer support chat | Adaptive RAG | Mixed simple/complex |
| SQL copilot | Agentic (self-query) | Query + filter generation |
| Code review | Long context (bax 27) | Single repo |
| Multi-source Q&A | Agentic (multi-tool) | DB + docs + API |
| High-stakes (legal, medical) | Self-RAG | Halüsinasiya tolerance aşağı |
| Real-time voice | Klassik RAG | <500 ms budget |

### 14.3 Müsahibə xülasəsi

> Agentic RAG klassik "retrieve → generate" pipeline-ını **tool-calling loop**-a çevirir. LLM retrieval-ın nə vaxt, nə ilə, neçə dəfə ediləcəyini özü qərar verir. Əsas pattern-lar: retrieval-as-tool (search_docs, query_db), self-query (NL-dən filter extract), multi-hop retrieval (iterative refinement), adaptive retrieval (skip when not needed), Corrective RAG (retrieval quality eval + web fallback), Self-RAG (output critique + re-retrieve). Agent loop hər turn-da LLM call, tool call, tool result → LLM call. Max iteration limit (5-8), timeout (15-30s) və cost budget (circuit breaker) kritikdir. Cost 5-20× artır klassikə görə, amma multi-hop və multi-source sorğularda keyfiyyət dramatik yaxşıdır. Laravel-də Claude Agent SDK ilə implement olunur — tools JSON schema ilə, execute-tool switch, trace logging. Trajectory eval yeni paradiqma tələb edir — tool accuracy, iteration efficiency, stopping correctness. Anti-pattern-lar: hər use case-də agent (over-engineering), tool çoxluğu, no observability, no budget. Default klassik RAG, agentic yalnız multi-hop və mixed-source halları üçün.

---

## 15. Əsas Çıxarışlar

- Agentic RAG retrieval-ı tool-callable funksiya edir — LLM strategiyanı dinamik seçir
- Əsas pattern-lar: retrieval-as-tool, self-query, multi-hop, adaptive, Corrective RAG, Self-RAG
- Klassikdən 5-20× bahalı və yavaş, amma multi-hop, multi-source, filter-generation use case-lərində keyfiyyət fərqi dramatik
- Max iteration, timeout, budget circuit breaker — production-da kritikdir
- Laravel + Claude Agent SDK ilə tool loop sade implement olunur
- Observability: agent_traces + tool_calls table, admin viewer
- Eval yeni dimensiya — trajectory metrics (tool accuracy, iteration efficiency)
- Anti-pattern: hər sorğuya agentic — əksər case-lər klassik RAG ilə yaxşı həll olunur
- Pragmatic yanaşma: klassik RAG default, agent complex sorğular üçün (router əsaslı seçim)
- Multi-source və multi-hop sorğular agentic paradiqmanı həqiqətən qazandırır
