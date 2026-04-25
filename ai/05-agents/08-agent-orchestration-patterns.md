# Agent Orchestration Pattern-ləri: Supervisor, Hierarchical, Swarm və Blackboard (Lead)

> **Oxucu:** Senior PHP/Laravel tərtibatçılar, multi-agent sistem quran arxitektlər
> **Ön şərtlər:** Agent dövrü (24), multi-agent sistemlər (25), agent memory (27), Claude Agent SDK (31), tool dizaynı (32), reasoning pattern-lər (33)
> **Diff vs 24-33:** 25 multi-agent sistemlərin yüksək səviyyəli baxışıdır — "niyə multi-agent, nə vaxt multi-agent". Bu fayl isə **konkret coordination pattern-ləri**: agent-lər bir-birini necə çağırır, state-i necə paylaşır, message-lər necə axır, observability necə qurulur. 33-cü fayl tək agent-in başındakı reasoning-dir; bu fayl agent-lər arasındakı dance-dir.
> **Tarix:** 2026-04-24

---

## Mündəricat

1. Niyə orchestration — tək agent nə vaxt çatmır
2. Orchestration pattern-lərinin təsnifatı
3. Supervisor pattern (router agent)
4. Hierarchical pattern (manager + worker)
5. Swarm pattern (peer handoff)
6. Blackboard pattern (shared workspace)
7. Pipeline pattern (sequential specialists)
8. Competition pattern (N agent + judge)
9. Collaboration vs Competition vs Consensus
10. State management across agents
11. Handoff mechanics — context köçürməsi
12. Error propagation — bir agent düşdükdə
13. Observability — per-agent span-lar, trace correlation
14. Claude Agent SDK subagents
15. Laravel implementasiya — Supervisor + 3 specialist
16. Cost math — N agent çağırışı
17. Tək agent nə vaxt multi-agent-dən yaxşıdır
18. Anti-pattern-lər — agent sprawl, coordination overhead
19. 2026 landscape — LangGraph, OpenAI Agents SDK, Claude Agent SDK, CrewAI, AutoGen

---

## 1. Niyə orchestration

Tək agent geniş domain-də yaxşı işləmir. Senior engineer bilir ki, bir sinifdə 1000+ sətir kod yazsan, hər şey qarışır; eyni şey agent-də də baş verir:

- **Context bloat** — 30 tool-luq agent-ə model hamısını hər çağırışda "oxuyur"; tool-lara diqqət dağılır.
- **System prompt overloading** — "Sən həm support, həm billing, həm sales, həm kod review..." şəklindəki prompt model-i çaşdırır.
- **Domain expertise** — hər sahənin öz ton-u, öz alətləri, öz guardrail-ləri var.
- **Debuggability** — tək agent 50 tool call edəndə səhvi izləmək cəhənnəmdir.

Orchestration həlli: **böl və idarə et**. Hər biri dar domain-də mütəxəssis olan kiçik agent-lər, və bir coordinator onları idarə edir.

### Sadə analogiya

Bir şirkət yönətiminə bax:

- CEO (supervisor) — "bu məsələ kimə aiddir?" qərarı verir.
- Departament meneceri (hierarchical manager) — öz komandasını istiqamətləndirir.
- Komanda üzvləri (worker agents) — konkret işi görür.
- Meeting notes (blackboard) — hamının oxuyub-yaza biləcəyi paylaşılan vəziyyət.
- Mehmanxana konfransı (pipeline) — hər kəs öz mərhələsini icra edir.

---

## 2. Orchestration pattern-lərinin təsnifatı

```
┌────────────────────────────────────────────────────────────┐
│               COORDINATION DIRECTION                       │
│                                                            │
│  Mərkəzləşdirilmiş ◄──────────────► Decentralised          │
│                                                            │
│  Supervisor       Hierarchical      Swarm       Blackboard │
│   (router)         (manager tree)    (peer)      (shared)  │
│                                                            │
│  Pipeline          Competition       Consensus             │
│   (chain)          (N + judge)       (vote/merge)          │
└────────────────────────────────────────────────────────────┘
```

### Klassifikasiya ölçüləri

| Ölçü | Seçimlər |
|------|----------|
| Mərkəzləşdirmə | Central (supervisor) vs peer |
| State paylaşımı | Shared (blackboard) vs message-passing |
| Qərar qəbulu | Hierarchical vs consensus vs election |
| Axın | Sequential (pipeline) vs parallel |
| Semantika | Collaboration vs competition |

Hər real sistem adətən **hibrid**-dir: supervisor + pipeline daxilində, blackboard ilə shared state.

---

## 3. Supervisor pattern

Ən populyar pattern (LangGraph Supervisor, OpenAI Swarm, Claude Agent SDK subagent router).

```
                      ┌────────────────┐
                      │   USER         │
                      └────────┬───────┘
                               │
                               ▼
                      ┌────────────────┐
                      │  SUPERVISOR    │
                      │  (routing LLM) │
                      └─┬───┬────┬─────┘
                        │   │    │
             ┌──────────┘   │    └──────────┐
             ▼              ▼               ▼
       ┌───────────┐  ┌───────────┐  ┌───────────┐
       │  Support  │  │  Billing  │  │  Sales    │
       │  Agent    │  │  Agent    │  │  Agent    │
       └───────────┘  └───────────┘  └───────────┘
```

### İşləmə prinsipi

1. User mesajı gəlir.
2. Supervisor LLM router prompt-u ilə qərar verir: hansı specialist?
3. Supervisor mesajı specialist-ə ötürür.
4. Specialist cavab verir.
5. Supervisor ya user-ə qaytarır, ya da başqa specialist-ə ötürür.

### Supervisor prompt nümunəsi

```
Sən router agent-isən. Aşağıdakı 3 specialist var:
- support: texniki problem, login, error
- billing: ödəniş, fakura, refund
- sales: yeni plan, upgrade, demo

İstifadəçinin mesajına əsasən JSON qaytar:
{"target": "support"|"billing"|"sales"|"none", "reason": "..."}

Əgər cavab verə bilmirsənsə, "none" qaytar.
```

### Faydaları

- **Dar specialist-lər** — hər biri yalnız öz tool-larını və öz system prompt-unu görür.
- **Audit-friendly** — kimin hara yönləndirildiyi aydındır.
- **Asanlıqla ölçülür** — yeni specialist əlavə etmək = yeni agent + router-in update-i.

### Zəif tərəflər

- **Router latency** — hər mesaj əvvəlcə supervisor-dan keçir (+2s).
- **Cross-domain sual-lar** — "Mən ödəniş problemi + texniki xəta istəyirəm" — bir specialist çatmır.
- **Router accuracy** — səhv routing = pis UX. Log-ları izlə.

---

## 4. Hierarchical pattern

Supervisor-un rekursiv variantı: supervisor altında başqa supervisor-lar, onların altında worker-lər.

```
              ┌─────────────────┐
              │   CEO Agent     │  (top coordinator)
              └─┬─────────────┬─┘
                │             │
       ┌────────▼──────┐  ┌───▼────────────┐
       │  Research Mgr │  │ Engineering Mgr│
       └─┬──────────┬──┘  └─┬──────────┬───┘
         │          │       │          │
    ┌────▼──┐  ┌────▼──┐ ┌──▼─────┐ ┌──▼─────┐
    │WebScrp│  │DBQuery│ │CodeGen │ │Debugger│
    │agent  │  │agent  │ │agent   │ │agent   │
    └───────┘  └───────┘ └────────┘ └────────┘
```

### İstifadə halları

- **Research systems** — top-level "topic choose", sub-level "gather", "synthesize".
- **Code generation** — architect → implementer → tester.
- **Financial analysis** — portfolio planner → sector analysts → instrument specialists.

### Context isolation faydası

Hər manager yalnız öz worker-lərinin nəticələrini görür, bütün alt-dialogları görmür. Bu, context window-u qoruyur. 3-səviyyəli iyerarxiya 90%+ context-i kənara at bilər.

### Risklər

- **Məlumat itkisi** — manager worker-dən gələn detalı "özünə görə" xülasələyir; kritik detal itə bilər.
- **Cascading failure** — alt-səviyyədə xəta yuxarıya sürüklənə bilər.
- **Cost multiplikasiyası** — hər səviyyə LLM çağırışıdır, 3-səviyyəli sistem tək-agent-dən 5-10x bahalıdır.

---

## 5. Swarm pattern

OpenAI Swarm (2024, indi OpenAI Agents SDK), Anthropic-in "handoff" termini. Mərkəzi coordinator yoxdur, agent-lər bir-birinə birbaşa ötürürlər.

```
       ┌──────────┐      handoff      ┌──────────┐
       │ Triage   │──────────────────▶│ Billing  │
       │ agent    │                   │ agent    │
       └────┬─────┘                   └─────┬────┘
            │                               │
            │ handoff                       │ handoff
            ▼                               ▼
       ┌──────────┐                   ┌──────────┐
       │ Support  │◄──────────────────│ Refund   │
       │ agent    │     handoff       │ agent    │
       └──────────┘                   └──────────┘
```

### Handoff mexanikası

Hər agent `handoff_to(agent_name)` tool-una malikdir. Tool çağırılanda konversasiya tamamilə qarşı agent-ə ötürülür. Qarşı agent öz system prompt-u və tool-ları ilə davam edir, amma əvvəlki mesaj tarixçəsini görür.

### Faydaları

- **Decentralized** — supervisor lazım deyil.
- **Təbii UX** — insan contact center-də də belədir: "Sizi billing-ə bağlayıram".
- **Aşağı latency** — router addımı yoxdur.

### Risklər

- **Handoff loop** — A → B → A → B → A loop-u. Circuit breaker əlavə et.
- **Context pollution** — bütün tarixçə hər agent-ə ötürülür; 10 handoff-dan sonra context 100k+ olur.
- **Distributed decision-making** — kim son cavabı verir qeyri-aydındır.

### Loop protection

```php
$handoffChain = $session->handoffChain;  // ['triage', 'billing', 'triage']

if (count($handoffChain) >= 5) {
    throw new HandoffLoopDetected($handoffChain);
}

if ($this->hasCycle($handoffChain, $target)) {
    // triage → billing → triage kimi dövrü tap
    $this->escalateToHuman();
}
```

---

## 6. Blackboard pattern

AI tarixindən gəlir (1970-lər, Hearsay-II speech recognition). Müasir variant: multiple agents bir **shared state object**-a oxuyub yaza bilir, birbaşa message-passing yoxdur.

```
     ┌─────────────────────────────────────────┐
     │           BLACKBOARD (Redis/DB)         │
     │                                         │
     │  {                                      │
     │    "user_query": "...",                 │
     │    "research_results": [...],           │
     │    "draft_answer": "...",               │
     │    "review_status": "pending",          │
     │    "final_answer": null                 │
     │  }                                      │
     └──┬──────┬──────────┬─────────┬──────────┘
        │      │          │         │
   oxu/yaz│  oxu/yaz│   oxu/yaz│   oxu/yaz
        │      │          │         │
    ┌───▼──┐ ┌─▼────┐ ┌───▼──┐ ┌───▼───┐
    │Resrch│ │Draft │ │Review│ │Publish│
    └──────┘ └──────┘ └──────┘ └───────┘
```

### İşləmə prinsipi

1. Blackboard başlanğıcda user query-lə initialize olunur.
2. Agent-lər trigger rule-u ilə "oyadılır" — "əgər `research_results` varsa və `draft_answer` yoxdursa, Draft agent işə düş".
3. Hər agent blackboard-dan lazım olanı oxuyur, işini görür, nəticəni yazır.
4. `final_answer` yazılanda sistem dayanır.

### Faydaları

- **Loose coupling** — agent-lər bir-birini tanımır.
- **Easy extension** — yeni agent = yeni trigger rule + yeni field.
- **Replay-debuggability** — blackboard state-i log-layıb istənilən anda nə olduğunu görürsən.
- **Parallel execution** — bir-birindən asılı olmayan agent-lər paralel işləyir.

### Risklər

- **Race condition** — iki agent eyni anda eyni field-i yaz. Optimistic lock (version field) lazımdır.
- **Thrashing** — trigger rule-lar pis yazılıb, agent-lər hey bir-birini oyadır.
- **State bloat** — blackboard vaxtla böyüyür, hər field lazım deyil.

### Laravel-da blackboard

```php
Schema::create('agent_blackboards', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('session_id');
    $table->jsonb('state');
    $table->integer('version')->default(1);  // optimistic lock
    $table->timestamps();
    $table->index('session_id');
});
```

Hər agent optimistic update edir:

```php
DB::transaction(function () use ($sessionId, $updates) {
    $bb = AgentBlackboard::where('session_id', $sessionId)->lockForUpdate()->first();
    $bb->state = array_merge($bb->state, $updates);
    $bb->version++;
    $bb->save();
});
```

---

## 7. Pipeline pattern

Ən sadə orchestration: agent-lər düz zəncirdə.

```
[Input] ──▶ [Extract] ──▶ [Classify] ──▶ [Enrich] ──▶ [Summarize] ──▶ [Output]
```

### İstifadə halları

- **Document processing** — OCR → structure extract → classify → index.
- **Data enrichment** — raw → cleanup → enrich → validate.
- **Content moderation** — input → toxicity check → PII redact → publish.

### Pipeline orchestration vs Laravel pipe

Laravel `Pipeline` class-ı pattern-i təmiz dəstəkləyir:

```php
use Illuminate\Pipeline\Pipeline;

$result = app(Pipeline::class)
    ->send($document)
    ->through([
        ExtractStage::class,
        ClassifyStage::class,
        EnrichStage::class,
        SummarizeStage::class,
    ])
    ->thenReturn();
```

Hər stage öz agent-ini daxilində çağırır. Pipeline sadə, deterministikdir, amma **dinamik routing** olmadığı üçün complex decision-lər üçün deyil.

---

## 8. Competition pattern

N agent eyni tapşırığı müxtəlif yollarla həll edir, judge ən yaxşısını seçir.

```
             ┌──────────┐
     ┌──────▶│ Agent A  │──▶ Answer A ┐
     │       └──────────┘              │
     │                                 ▼
  [Task]     ┌──────────┐        ┌─────────┐
     ├──────▶│ Agent B  │──▶ Ans B──▶│ Judge  │──▶ Best
     │       └──────────┘        │   LLM   │
     │                           └─────────┘
     │       ┌──────────┐              ▲
     └──────▶│ Agent C  │──▶ Answer C ─┘
             └──────────┘
```

### İstifadə halları

- **Creative writing** — 3 agent, 3 draft, editor seçir.
- **Code generation** — 3 yanaşma (functional, OOP, imperative), reviewer seçir.
- **RAG-da query rewrite** — 3 rewrite, search relevance judge seçir.

### Cost

3 agent × 1 task + 1 judge = 4x cost tək-agent-dən. Yalnız keyfiyyət kritik olanda dəyərinə dəyir.

### Consensus variantı

Judge olmadan, majority voting:

```
if Agent A answers X and Agent B answers X and Agent C answers Y:
  final = X (2-1 majority)
```

Self-consistency paper-ləri (Wang et al., 2022) bunun arifmetik reasoning-də +10-15% accuracy verdiyini göstərdi.

---

## 9. Collaboration vs Competition vs Consensus

Əksər mühəndislər bu üçü qarışdırır. Fərqi:

| Rejim | Necə qərar verilir | Nümunə |
|-------|---------------------|--------|
| Collaboration | Agent-lər bir-birinin outputlarını input kimi istifadə edir | Pipeline, hierarchical |
| Competition | Agent-lər müstəqil cəhd edir, kənar judge seçir | Code gen, creative |
| Consensus | Agent-lər müstəqil cəhd edir, vote/merge | Self-consistency, ensemble |

Seçim qaydaları:

- **Collaboration** → workflow aydındır, addımlar paralel deyil.
- **Competition** → "ən yaxşı" subyektivdir, judge lazımdır.
- **Consensus** → "ən yaxşı" obyektiv amma noisy-dir, majority doğrusu artırır.

---

## 10. State management across agents

Multi-agent sistemdə state 3 yerdə ola bilər:

### 10.1 In-context (mesaj tarixçəsi)

Bütün tarixçə hər çağırışda ötürülür. Sadə amma token xərcli.

```
Messages: [user, assistant, tool_result, assistant, user, ...]
```

### 10.2 Blackboard (shared DB/Redis)

Paylaşılan state. Agent-lər id ilə oxuyur. Daha scalable.

### 10.3 Event-sourced

Hər action domain event-i doğurur, state-i replay ilə reconstruct edirsən.

```php
events = [
  AgentStarted(id=x, agent=supervisor),
  RoutedTo(from=supervisor, to=billing),
  ToolCalled(agent=billing, tool=lookup_invoice, input={...}),
  ToolReturned(tool_call_id=1, output={...}),
  AgentFinished(agent=billing, result=...),
]
```

Production-da tövsiyə: **hybrid** — recent tarixçə in-context, long-term state blackboard-da, audit trail event store-da.

### Laravel `agent_sessions` cədvəli

```sql
CREATE TABLE agent_sessions (
    id UUID PRIMARY KEY,
    user_id BIGINT,
    current_agent VARCHAR(64),
    message_history JSONB,           -- son N mesaj
    blackboard JSONB,                -- shared state
    handoff_chain TEXT[],            -- agent adı massivi
    started_at TIMESTAMPTZ,
    last_activity_at TIMESTAMPTZ,
    status VARCHAR(32),              -- active, paused, completed
    total_cost_cents INT,
    INDEX (user_id, status),
    INDEX (last_activity_at)
);
```

---

## 11. Handoff mechanics — context köçürməsi

Swarm-da ən böyük sual: Agent A-dan Agent B-yə nə ötürülür?

### 3 strategiya

**Full history:**

```
B alır: [bütün mesajlar, bütün tool call-lar]
+ lazımlı detallar var
- context blowup, bahalı
```

**Summary:**

```
A əvvəl "handoff summary" yaradır:
"User refund istəyir, invoice #123, səbəb: duplicate charge"
B yalnız summary + yeni user mesajını alır
+ ucuz, təmiz
- detal itkisi
```

**Selective:**

```
A handoff tool-unda mühüm field-ləri göstərir:
handoff(agent="billing", context={
  invoice_id: 123,
  user_intent: "refund",
  verified: true
})
B structured input alır
+ tam kontrol
- implementasiya mürəkkəb
```

Praktikada **summary + selective** ən yaxşısıdır. Full history yalnız debug rejimi.

### Handoff token economics

Ortalama Laravel support session: 15 mesaj × 200 token = 3k token. 3 handoff olsa, full history-də hər handoff 3k token input → 9k token. Summary strategiyası: hər handoff 500 token → 1.5k toplam. **6x saving**.

---

## 12. Error propagation

Multi-agent-də failure-lar bir-birinə keçir. Sadə supervisor-da:

```
User → Supervisor → Billing agent → tool_call FAILED → ???
```

### 3 strategiya

1. **Fail-fast** — xəta yuxarıya bubble olunur, session dayanır.
2. **Retry with same agent** — Reflexion kimi.
3. **Fallback to different agent** — "Billing cavab verə bilmədi, supervisor-a qayıt, başqa agent-ə yönəlt".

### Circuit breaker

Hər agent üçün son N çağırışın uğur statistikasını saxla:

```php
if ($agent->failureRate(lastN: 10) > 0.5) {
    $this->circuit->open($agent->name, cooldownSeconds: 60);
    // supervisor bu agent-i routing-dən çıxarır
}
```

### Partial failure handling

Hierarchical-də 5 worker-dən 1-i düşdüsə:

- Mission-critical olsa → hamını dayandır.
- Optional olsa → result-ı `partial` flag-i ilə qaytar.

---

## 13. Observability — per-agent span-lar, trace correlation

Multi-agent observability tək agent-dən çətindir. Trace ağacı belə görünməlidir:

```
session: abc-123
└─ supervisor (500ms)
   ├─ route_decision (150ms)
   └─ handoff_to_billing
      └─ billing_agent (2.4s)
         ├─ tool: lookup_invoice (300ms)
         ├─ tool: check_refund_eligibility (400ms)
         └─ llm_response (1.2s)
```

### OpenTelemetry span attributes

Hər span-a ən azı:

- `agent.name`
- `agent.session_id`
- `agent.iteration`
- `agent.model`
- `agent.cost_cents`
- `agent.parent_agent` (nested üçün)
- `agent.handoff_source` (əvvəlki agent)

### Laravel implementasiyası

```php
class AgentTracer
{
    public function trace(string $agentName, callable $fn, array $context = []): mixed
    {
        $tracer = Globals::tracerProvider()->getTracer('agents');
        $span = $tracer->spanBuilder("agent.{$agentName}")->startSpan();

        $span->setAttributes([
            'agent.name' => $agentName,
            'agent.session_id' => $context['session_id'] ?? null,
            'agent.model' => $context['model'] ?? null,
        ]);

        try {
            $scope = $span->activate();
            return $fn();
        } catch (\Throwable $e) {
            $span->recordException($e);
            throw $e;
        } finally {
            $scope->detach();
            $span->end();
        }
    }
}
```

---

## 14. Claude Agent SDK subagents

31-ci faylda SDK-nı təfsilatla açmışdıq. Burada orchestration kontekstində:

```ts
const researchAgent = subagent({
  name: "researcher",
  description: "Dərin web araşdırma aparır",
  model: "claude-sonnet-4-5",
  tools: [webSearch, webFetch],
});

const writerAgent = subagent({
  name: "writer",
  description: "Araşdırma nəticələrindən məqalə yazır",
  model: "claude-opus-4-5",
  tools: [markdownFormatter],
});

const mainAgent = await query({
  prompt: "React Server Components haqqında məqalə yaz",
  model: "claude-opus-4-5",
  subagents: [researchAgent, writerAgent],
});
```

SDK daxilində main agent hierarchical pattern-lə subagent-ləri çağırır. Hər subagent öz context window-u ilə izolasiyadır. Main agent yalnız final nəticəni görür — bu **hierarchical + blackboard** hibrididir.

---

## 15. Laravel implementasiya — Supervisor + 3 specialist

Use-case: customer support bot.

- **Triage agent** — user intent təyin edir və yönləndirir.
- **Knowledge agent** — texniki sualları KB-dən cavablandırır.
- **Billing agent** — ödəniş və refund məsələləri.

### Struktur

```
app/Services/Agents/
├── Orchestrator.php            (supervisor)
├── Specialists/
│   ├── TriageAgent.php
│   ├── KnowledgeAgent.php
│   └── BillingAgent.php
├── AgentSession.php            (state holder)
└── Tools/ ... (KB search, invoice lookup, refund)
```

### AgentSession model

```php
class AgentSession extends Model
{
    protected $casts = [
        'message_history' => 'array',
        'handoff_chain' => 'array',
        'blackboard' => 'array',
    ];

    public function appendMessage(string $role, array $content): void
    {
        $history = $this->message_history ?? [];
        $history[] = ['role' => $role, 'content' => $content, 'ts' => now()->toIso8601String()];
        $this->message_history = $history;
        $this->save();
    }

    public function handoff(string $from, string $to, string $summary): void
    {
        $this->handoff_chain = [...($this->handoff_chain ?? []), ['from' => $from, 'to' => $to, 'summary' => $summary]];
        $this->current_agent = $to;
        $this->save();
    }
}
```

### Orchestrator

```php
<?php

namespace App\Services\Agents;

use App\Services\Agents\Specialists\{TriageAgent, KnowledgeAgent, BillingAgent};

class Orchestrator
{
    public function __construct(
        protected TriageAgent $triage,
        protected KnowledgeAgent $knowledge,
        protected BillingAgent $billing,
    ) {}

    public function handle(AgentSession $session, string $userMessage): string
    {
        $session->appendMessage('user', [['type' => 'text', 'text' => $userMessage]]);

        $maxHops = 4;  // loop protection
        $current = $session->current_agent ?? 'triage';
        $hops = 0;

        while ($hops++ < $maxHops) {
            $response = match ($current) {
                'triage'    => $this->triage->handle($session),
                'knowledge' => $this->knowledge->handle($session),
                'billing'   => $this->billing->handle($session),
                default     => throw new \RuntimeException("Naməlum agent: {$current}"),
            };

            if ($response->isFinal()) {
                $session->appendMessage('assistant', [['type' => 'text', 'text' => $response->text]]);
                return $response->text;
            }

            if ($response->isHandoff()) {
                $session->handoff($current, $response->target, $response->summary);
                $current = $response->target;
                continue;
            }

            throw new \RuntimeException("Gözlənilməz response tipi");
        }

        // hops exceeded — escalate
        $session->status = 'escalated';
        $session->save();
        return "Bu sualı tam cavablandırmaq mümkün olmadı, human operator-a yönəldim.";
    }
}
```

### TriageAgent

```php
<?php

namespace App\Services\Agents\Specialists;

use App\Services\Agents\AgentSession;
use App\Services\Anthropic\AnthropicClient;

class TriageAgent
{
    protected string $systemPrompt = <<<SYS
    Sən router agent-sən. İstifadəçi intent-ini təyin et və qərar ver:
    - "knowledge" — texniki, login, error, how-to sualları
    - "billing" — ödəniş, invoice, refund, plan dəyişiklik
    - "answer" — əgər sadə salam/suallar (dövr bitir)

    Cavab formatı JSON: {"route": "knowledge|billing|answer", "summary": "...", "answer"?: "..."}
    SYS;

    public function __construct(protected AnthropicClient $client) {}

    public function handle(AgentSession $session): AgentResponse
    {
        $resp = $this->client->messages([
            'model' => 'claude-haiku-4-5',  // ucuz router
            'max_tokens' => 512,
            'system' => $this->systemPrompt,
            'messages' => $session->message_history,
        ]);

        $json = $this->parseJson($resp['content'][0]['text']);

        return match ($json['route']) {
            'answer' => AgentResponse::final($json['answer']),
            default  => AgentResponse::handoff($json['route'], $json['summary']),
        };
    }

    protected function parseJson(string $raw): array
    {
        return json_decode(
            preg_replace('/^```json\s*|\s*```$/m', '', trim($raw)),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
    }
}
```

### KnowledgeAgent

```php
<?php

namespace App\Services\Agents\Specialists;

use App\Services\Agents\AgentSession;
use App\Services\Agents\Tools\KbSearchTool;
use App\Services\Agents\ReActAgent;

class KnowledgeAgent
{
    public function __construct(
        protected ReActAgent $react,
        protected KbSearchTool $kbSearch,
    ) {}

    public function handle(AgentSession $session): AgentResponse
    {
        $this->react->setTools([$this->kbSearch]);
        $this->react->setSystemPrompt(
            "Sən texniki support agent-sən. Knowledge base-də axtar və cavab ver."
        );

        $result = $this->react->runOnHistory($session->message_history);
        return AgentResponse::final($result->finalMessage);
    }
}
```

### BillingAgent — analogi

Eyni nümunə, amma `InvoiceLookupTool`, `RefundTool` ilə.

### Controller üçün giriş nöqtəsi

```php
Route::post('/api/chat', function (Request $request, Orchestrator $orch) {
    $session = AgentSession::firstOrCreate(
        ['id' => $request->session_id],
        ['user_id' => $request->user()->id, 'current_agent' => 'triage']
    );

    $response = $orch->handle($session, $request->message);

    return response()->json([
        'reply' => $response,
        'session_id' => $session->id,
        'current_agent' => $session->current_agent,
    ]);
});
```

---

## 16. Cost math

Tipik single-agent vs multi-agent (orta 2k input / 500 output):

**Single agent (Sonnet 4.5):**
- 5 tool iter × $0.009 = $0.045 per session.

**Supervisor + 3 specialist:**
- Triage (Haiku): 1 × $0.002 = $0.002
- Specialist (Sonnet): 4 iter × $0.009 = $0.036
- **Ümumi: $0.038 per session**.

Gözləntilərin əksinə, **multi-agent ucuz ola bilər** çünki:

1. Router Haiku-dur (Sonnet deyil).
2. Specialist context-i daha kiçikdir (yalnız öz tool-ları).

**Amma**: Hierarchical 3-səviyyəli + 5 worker → 8 çağırış × $0.01 = $0.08. **2x artış**.

Qayda: 2 səviyyə OK-dir, 3+ yalnız yüksək dəyərli task-lar üçün.

---

## 17. Tək agent nə vaxt multi-agent-dən yaxşıdır

**Demək olar ki həmişə, kiçik-orta scale-də.**

Amazon, Anthropic və LangChain engineering blog-larında ortaq message: "Multi-agent sizin düşündüyünüzdən daha pis işləyir". Səbəblər:

- **Coordination overhead** — hər handoff latency + cost.
- **Context loss** — handoff-da detal itir.
- **Debugging difficulty** — 5 agent failure-u izləmək 5x çətindir.
- **Guardrail fragmentation** — hər agent-də təkrar.

Qayda: **Tək agent + geniş tool set + yaxşı system prompt** 80% hallar üçün yetərlidir. Multi-agent-ə keçməlisən yalnız:

- Domain-lər tamamilə ayrıdır (billing-də medical info görmək olmaz).
- Tool sayı 25+ olur və router overhead-i görünür.
- Regulyar compliance tələb edir ("billing agent yalnız billing data-sına çıxış").
- Domain-specific model fərqi var (kod üçün Opus, klassifikasiya üçün Haiku).

---

## 18. Anti-pattern-lər

### 18.1 Agent sprawl

"Mikroservis" fəlsəfəsindən ilhamlanan takım hər kiçik task üçün agent yaradır. 20 agent, 40% zamanı handoff-a gedir.

**Düzəliş**: Agent == microservice deyil. Agent == domain. 3-5 agent təcrübədə maksimum.

### 18.2 Endless coordination

Supervisor hər mesajı 3 specialist-ə göndərir (competition), judge seçir, amma judge də LLM-dir və səhv edir.

**Düzəliş**: Competition yalnız high-stakes creative task-lar üçün. Rest istifadəsi — overkill.

### 18.3 Hidden dependencies

BillingAgent daxilində KnowledgeAgent-i çağırır, Blackboard-u oxuyur, Supervisor-u yoxlayır. Heç bir orchestration diaqramı bunu göstərmir.

**Düzəliş**: Tek bir orchestration layer. Agent-lər bir-birinə birbaşa müraciət etməməli — supervisor və ya blackboard vasitəsilə.

### 18.4 Shared mutable state without versioning

İki agent eyni blackboard field-ini eyni anda yeniləyir. Biri yox olur.

**Düzəliş**: Optimistic lock (version field) və ya Redis LUA script.

### 18.5 No circuit breaker

Bir specialist düşür, supervisor hey ona yönəldir, hər dəfə timeout, user 2 dəqiqə gözləyir.

**Düzəliş**: Son N çağırışın failure rate-ini izlə, açıq dairə — routing-dən çıxar.

### 18.6 Router agent over-specialized

Router üçün Opus istifadə olunur, həmişə "düzgün" qərar verir, amma $0.30/çağırış.

**Düzəliş**: Router Haiku olmalıdır. Əgər Haiku routing səhv edirsə, prompt-u təkmilləşdir, yoxsa specialist sayını azalt.

### 18.7 Synchronous multi-agent in HTTP request

User HTTP POST göndərir, 5 agent pipeline-ı sinxron işləyir, 30 saniyə lazımdır, browser timeout.

**Düzəliş**: Long-running orchestration queue-da (Horizon), SSE və ya WebSocket ilə progress ötür.

---

## 19. 2026 landscape — framework-lər

### LangGraph (LangChain)

Python-yönəlik. Graph-based orchestration. Supervisor pattern birinci dərəcəli. State machine-dir ki, agent-lər node-dur, edge-lər qərar axınıdır. Çox güclü debugger (LangSmith). Production-da ən populyar.

### OpenAI Agents SDK (2024 Swarm evolution)

Python + TypeScript. Swarm pattern, handoff-lar first-class. Sadə API, daha az feature LangGraph-dən.

### Claude Agent SDK

TypeScript + Python. Subagent-lər (hierarchical). MCP-yə təbii dəstək. Permission sistemi güclüdür. Anthropic öz Claude Code-da istifadə edir.

### CrewAI

Python. Role-based (sən bu agent-sən, sən o agent-sən). Pipeline + collaboration. Sadə başlamaq, lakin production-da scale problemləri.

### AutoGen (Microsoft)

Python. Multi-agent conversation. Blackboard + swarm hibridi. Research oriented, production-da az.

### PHP dünyası

2026-da rəsmi PHP Agent SDK hələ yoxdur. İki yol:

1. **Native qur** — bu faylda göstərdiyimiz kimi, Anthropic REST API üzərində öz Orchestrator.
2. **Bridge** — TypeScript SDK-nı Node sidecar kimi aç, Laravel HTTP ilə çağır. Amma latency, complexity, ops burden.

### Seçim meyarları

| Tələb | Ən yaxşı seçim |
|-------|-----------------|
| Prod-ready, böyük | LangGraph (Python) |
| Sadə handoff | OpenAI Agents SDK |
| Claude-first + MCP | Claude Agent SDK |
| Role-based prototyping | CrewAI |
| PHP native | Öz Orchestrator (bu fayl) |

---

## Xülasə

- **Supervisor** — 3-5 specialist ilə başlanğıc. Ən yaygın pattern.
- **Hierarchical** — dərin domain (research, code gen), 2-3 səviyyə max.
- **Swarm** — təbii handoff UX, loop protection vacibdir.
- **Blackboard** — loose coupling, paralel agent-lər, event-driven.
- **Pipeline** — sadə, sequential, deterministik.
- **Competition/Consensus** — yalnız kritik tasklar üçün, cost bahalı.

Senior həyatda **tək agent + geniş tool ilə başla**, ölçü böyüyəndə **supervisor + specialist-lərə keç**. Multi-agent həlli peşəkar tutmaq üçün deyil — problemi həll etmək üçündür. Agent sayını minimum saxla.

---

**Növbəti fayl:** `09-human-in-the-loop.md` — agent-lərin kritik aksiyaları üçün insan approval pattern-ləri.
