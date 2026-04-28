# Agent Reasoning Pattern-ləri: ReAct, Reflexion, Tree-of-Thoughts və Plan-and-Execute (Senior)

> **Oxucu:** Senior PHP/Laravel tərtibatçılar, production agent quran arxitektlər
> **Ön şərtlər:** Agent dövrü (24), multi-agent (25), agent memory (27), Claude Agent SDK (31), tool dizaynı (32)
> **Diff vs 24-32:** 24-cü fayl "agent nədir", 25 multi-agent topologiyası, 27 yaddaş, 31-32 SDK/tool mexanikası haqqındadır. Bu fayl isə **agent-in başının içində hansı alqoritm dönür** — nə "reason" edir, nə əsasda növbəti addımı seçir, necə öz səhvlərini düzəldir. Başqa sözlə: **cognitive pattern-lər**, infrastruktur deyil.
> **Tarix:** 2026-04-24

---

## Mündəricat

1. Niyə reasoning pattern-i lazımdır — tək LLM çağırışı agent deyil
2. Chain-of-Thought vs ReAct — tarixi kontekst
3. ReAct (Reason + Act) — ətraflı
4. Reflexion — özünü tənqid loop-u
5. Tree of Thoughts (ToT) — fikir ağacı üzrə axtarış
6. Plan-and-Execute — DAG yaratmaq, sonra icra etmək
7. Plan-and-Solve — iki addımlı sadə variant
8. Self-Refine — iterativ özünütəkmilləşdirmə
9. LATS (Language Agent Tree Search) — ToT + Reflexion hibridi
10. Pattern seçim matrisi — hansı halda nə
11. Cost və latency analizi
12. Laravel PHP implementasiya — ReAct loop
13. Laravel PHP implementasiya — Reflexion loop (SQL query assistant)
14. Production pitfalls — sonsuz loop, degrading reflection, reward hacking
15. 2026 reasoning model-ləri (Claude extended thinking, o3) bu pattern-ləri necə dəyişdirir

---

## 1. Niyə reasoning pattern-i lazımdır

Bir LLM çağırışı agent deyil. Model bir `messages` array-i alır, bir `assistant_message` qaytarır — bu qədər. Bu stateless prosesdir:

```
İstifadəçi: "Bakıda sabah hava necə olacaq?"
 LLM: "Bilmirəm, internet girişim yoxdur."
```

Agent olmaq üçün modelin:

1. **Düşünməsi** lazımdır — "bu suala cavab üçün mən weather_api alətini çağırmalıyam".
2. **Hərəkət etməsi** lazımdır — tool call göndərməli.
3. **Müşahidə etməsi** lazımdır — tool nəticəsini oxumalı.
4. **Növbəti addımı seçməsi** lazımdır — "indi cavab verə bilərəm, yoxsa başqa tool lazımdır?".

Bu 4-ü **reasoning pattern** təşkil edir. Pattern — modelin hansı ardıcıllıqla düşünüb hərəkət etməsini strukturlaşdıran şablondur. Pattern olmadan agent ya çox erkən dayanır (tool çağırmır), ya sonsuz loop-a düşür (hey tool çağırır), ya da təsadüfi nəticələr verir.

### Pattern-lər spektri

```
Sadəlik ◄─────────────────────────────────────────► Mürəkkəblik
  │                                                       │
  │  CoT     ReAct     Self-Refine    Plan-and-Execute    ToT    LATS
  │                    Reflexion
  │  (1 call) (N call) (N iter)       (plan + exec)      (ağac)  (hibrid)
  │                                                       │
```

Sol tərəf: ucuz, sürətli, sadə tasklar üçün. Sağ tərəf: bahalı, yavaş, amma çətin problemi həll edə bilir (coding challenge, riyazi sübut, strateji planlaşdırma).

---

## 2. Chain-of-Thought vs ReAct — tarixi kontekst

### Chain-of-Thought (CoT)

Wei et al., 2022 (Google Brain). Əsas kəşf: LLM-ə "Let's think step by step" yazsan, performans çətin tasklarda 15-40% artır. Model düşüncə addımlarını çıxışda yazır, bu onu daha dəqiq edir.

```
Prompt: "John 3 alma aldı, 2-ni yedi. Sonra 5 alma daha aldı. Neçə alma var?"

CoT cavab:
"Addım 1: John 3 alma aldı.
 Addım 2: 2 yedi → 1 qaldı.
 Addım 3: 5 daha aldı → 1+5 = 6.
 Cavab: 6 alma."
```

Problem: CoT yalnız **təsəvvürdə** düşünür. Real dünyada məlumat olmasa, uydurur (hallucination).

### ReAct (Yao et al., 2022, Princeton + Google)

Əsas insight: CoT-u tool use ilə birləşdir. Model düşünəndə "hm, burada dəqiq rəqəm lazımdır" düşünür, sonra `calculator` tool-unu çağırır. Müşahidəni oxuyur, yenidən düşünür.

```
Thought: Alma sayını bilmək üçün hesablamalıyam.
Action: calculator(expression="3 - 2 + 5")
Observation: 6
Thought: İndi cavabı bildim.
Answer: 6
```

ReAct CoT-dan fərqli olaraq:

| Ölçü | CoT | ReAct |
|------|-----|-------|
| Tool istifadəsi | Yox | Var |
| Halüsinasiya | Yüksək | Aşağı (tool-ları gerçək məlumat verir) |
| Latency | 1 LLM çağırışı | N LLM çağırışı |
| Cost | Aşağı | N × aşağı |
| HotpotQA F1 (original paper) | 29.4 | 35.1 |

---

## 3. ReAct (Reason + Act) — ətraflı

ReAct dövrünün anatomiyası:

```
┌─────────────────────────────────────────────────────────┐
│                    REACT LOOP                           │
│                                                         │
│   ┌──────────┐                                          │
│   │ Thought  │ "Mən hansı məlumata ehtiyac duyuram?"    │
│   └────┬─────┘                                          │
│        │                                                │
│        ▼                                                │
│   ┌──────────┐                                          │
│   │ Action   │ tool_use: search_orders(email="x@y.az")  │
│   └────┬─────┘                                          │
│        │                                                │
│        ▼                                                │
│   ┌──────────────┐                                      │
│   │ Observation  │ [{"id": 123, "status": "pending"}]   │
│   └────┬─────────┘                                      │
│        │                                                │
│        ▼                                                │
│   ┌──────────┐                                          │
│   │ Thought  │ "Sifarişi tapdım. Başqa tool lazımdırmı?"│
│   └────┬─────┘                                          │
│        │                                                │
│        ▼                                                │
│   ┌──────────────────────────┐                          │
│   │ Action (final) / Answer  │                          │
│   └──────────────────────────┘                          │
└─────────────────────────────────────────────────────────┘
```

Claude tool-use API-də ReAct implicit olaraq dəstəklənir. Model `assistant_message` daxilində həm düşüncə (extended thinking block və ya adi mətn), həm də `tool_use` blokunu qaytara bilir. Developer loop yazır: model → tool exec → model → tool exec → ... → final cavab.

### ReAct-ın faktiki istifadə halları

| İş | ReAct faydası |
|----|---------------|
| Customer support bot | Ticket axtar → oxu → cavabla |
| Research agent | Google → Wikipedia → sintez |
| Code repair | Error oxu → kod oxu → dəyişiklik yaz |
| SQL assistant | Schema oxu → query yaz → icra et |
| DevOps triage | Metric oxu → log oxu → hipotez |

### ReAct nə vaxt **işləmir**

- **Qeyri-verifikə edilə bilən tasklar** — "Poetry yaz" (reflexion lazımdır).
- **Çox uzun planlama** — 20+ addım (plan-and-execute daha yaxşıdır).
- **Bir doğru cavab olmayan explorativ tasklar** — (ToT daha yaxşıdır).
- **Latency-sensitive tasklar** — hər iteration ~2-5 saniyədir; 10 iteration = 30 saniyə.

---

## 4. Reflexion — özünü tənqid loop-u

Shinn et al., 2023 (Northeastern, MIT). Əsas insight: agent bir dəfə sınayır, nəticəni qiymətləndirir, özünə yazılı reflection verir (yaddaşa), yenidən sınayır. Bu — klassik "trial-and-error + self-critique" döngəsidir.

```
┌──────────────────────────────────────────────────────────┐
│                    REFLEXION LOOP                        │
│                                                          │
│   ┌──────────────┐                                       │
│   │ Actor agent  │  Task-ı icra et (ReAct və ya CoT)     │
│   └──────┬───────┘                                       │
│          │                                               │
│          ▼                                               │
│   ┌──────────────┐                                       │
│   │ Evaluator    │  Nəticəni qiymətləndir                │
│   │              │  (test suite, LLM-as-judge, oracle)   │
│   └──────┬───────┘                                       │
│          │                                               │
│          ▼                                               │
│      uğurlu?                                             │
│       │     │                                            │
│   bəli│     │xeyr                                        │
│       ▼     ▼                                            │
│   Qaytar  ┌──────────────┐                               │
│           │ Self-Reflect │  "Niyə uğursuz oldum?"        │
│           │              │  Mətn reflection yarat.       │
│           └──────┬───────┘                               │
│                  │                                       │
│                  ▼                                       │
│           ┌────────────────┐                             │
│           │ Episodic memory│  Reflection-u saxla         │
│           │                │  (növbəti trial-a verəcək)  │
│           └──────┬─────────┘                             │
│                  │                                       │
│                  └──────► (Actor-a qayıt, max N trial)   │
└──────────────────────────────────────────────────────────┘
```

### Reflexion-un 3 komponenti

1. **Actor** — əsl taskı icra edən agent. ReAct ola bilər.
2. **Evaluator** — nəticəni uğur/uğursuzluq kimi qiymətləndirən. Aşağıdakılardan biri:
   - Deterministik oracle (test suite keçdi/keçmədi)
   - External API (SQL query xəta verdi?)
   - LLM-as-judge (başqa LLM rubrika ilə qiymətləndirir)
3. **Self-Reflector** — uğursuzluq halında mətn reflection yaradır: "Mən X etdim, amma Y səhv idi, gələn dəfə Z-ni sınayıb".

### Nə vaxt işləyir

Reflexion **yalnız verifiable task**-larda işləyir. "Uğur" siqnalı aydın olmalıdır:

| Task | Verifiable? | Reflexion işləyirmi |
|------|-------------|---------------------|
| LeetCode problem | Bəli (test suite) | Bəli, çox yaxşı |
| SQL query | Bəli (icra et, xətaya bax) | Bəli |
| Mətn xülasəsi | Qismən (LLM judge) | Orta |
| Poeziya yazmaq | Yox (subyektiv) | Xeyr |
| Çoxmərhələli research | Qismən | Orta |

Original paper: HumanEval-da 91% pass@1, GPT-4 baseline 80%. Coding agent-lərdə bu +10-15% artıma gətirir.

### Degrading reflection problemi

Real dünyada 5+ trial-dan sonra reflection **pisləşir**: model "o saat da eyni şey etmişəm, yenə eyni şey olacaq" kimi boş mətnlər yazır. Praktikada:

- **Max trial**: 3-4.
- **Reflection-ları summarize et** — N trial-dan sonra köhnələri xülasələş.
- **Diversity injection** — reflection-lara "fərqli strategiya sınayın" imperativi əlavə et.

---

## 5. Tree of Thoughts (ToT) — fikir ağacı üzrə axtarış

Yao et al., 2023 (Princeton + DeepMind). CoT-un genişləndirilməsi: **birdən çox fikir yolu** yarat, onları qiymətləndir, ən yaxşılarını genişləndir.

```
              [İlkin problem]
                     │
           ┌─────────┼─────────┐
           ▼         ▼         ▼
        Fikir A  Fikir B  Fikir C     ← 3 alternativ
         (0.7)    (0.4)    (0.8)      ← value function skoru
           │                 │
     ┌─────┼──┐          ┌───┴────┐
     ▼     ▼             ▼        ▼
   A1 (.9) A2(.6)       C1(.3)  C2(.85)   ← dərinləşdir
     │                           │
     ▼                           ▼
  [Cavab A1]                 [Cavab C2]
```

### ToT-un 4 elementi

1. **Thought decomposition** — problemi fikir addımlarına bölmək.
2. **Thought generator** — hər node-da K alternativ fikir doğurmaq (sampling və ya propose prompt).
3. **State evaluator** — hər fikrin faydalı olma ehtimalını qiymətləndirmək (value function). Ya LLM-as-judge, ya da vote.
4. **Search algorithm** — BFS və ya DFS ağac üzrə.

### BFS vs DFS

| Ölçü | BFS | DFS |
|------|-----|-----|
| Yaddaş | O(branching^depth) | O(depth) |
| Ən yaxşı cavab zəmanəti | Yüksək | Aşağı (lokal max-a ilişə bilər) |
| İstifadə halı | Geniş, dayaz problemlər | Dar, dərin problemlər |

### ToT-un real cost-u

Paper Game of 24-də 4% → 74% artırdı (GPT-4 ilə). Amma cost:

- CoT: 1 LLM çağırışı = ~$0.01
- ToT (branching=3, depth=3): 3 + 9 + 27 = 39 LLM çağırışı = ~$0.40

**40x cost artımı**. 2026-cı ildə Claude Opus 4.5-lə saat başına $20-30 cost çox tez yığılır.

### Nə vaxt ToT istifadə olunur

- **Game-like problem** (24 oyunu, Sudoku, chess).
- **Creative writing** (novel plotting, outline generasiyası).
- **Strategic planning** (business case simulyasiyası).
- **Riyazi sübut** (proof search).

Tipik production agent üçün **ToT overkill-dir**. Əgər Claude Opus 4.5 extended thinking istifadə edirsinizsə, oxşar nəticəni 1 çağırışla alırsınız (aşağıda baxın).

---

## 6. Plan-and-Execute — DAG yaratmaq, sonra icra etmək

Wang et al., 2023 (LangChain populyarlaşdırdı). ReAct-ın zəif tərəfi: hər addımda tam planı yenidən düşünür, uzun tasklarda bu 20+ iterasiyaya gedir. Plan-and-Execute bunu ikiyə bölür:

```
┌────────────────────────────────────────────┐
│             PLAN PHASE                     │
│  İri LLM (Opus) tam planı tərtib edir      │
│                                            │
│  Output: DAG of steps                      │
│  Step 1: search_orders(customer_id)        │
│  Step 2: for each order → get_refund_info  │
│  Step 3: aggregate → write_report          │
└────────────────────────────────────────────┘
                  │
                  ▼
┌────────────────────────────────────────────┐
│           EXECUTE PHASE                    │
│  Kiçik LLM (Haiku) və ya deterministic     │
│  runner addımları icra edir                │
│                                            │
│  Hər addım üçün: tool çağır, nəticə saxla  │
│  Xəta olsa → replan (və ya halt)           │
└────────────────────────────────────────────┘
```

### Faydaları

- **Model bifurkasiyası** — plan üçün güclü model (expensive), execution üçün ucuz model.
- **Parallel execution** — plan DAG-dirsə, bir-birindən asılı olmayan addımlar paralel icra olunur.
- **Debuggability** — plan göründüyü üçün "nə tərs gedir" izləmək asandır.
- **Caching** — eyni plan şablonu gələcəkdə yenidən istifadə edilə bilər.

### LangGraph PlanAndExecute (2026 variant)

LangGraph bu pattern-i first-class dəstəkləyir. Bu fayl PHP-yə yönəlib, amma konsept:

```
class PlanAndExecuteAgent {
  planner: LLM  // Opus
  executor: LLM // Haiku
  
  run(task):
    plan = planner.generate_plan(task)  // returns List[Step]
    state = {}
    for step in topological_sort(plan):
      result = executor.execute(step, state)
      state[step.id] = result
      if step.needs_replan(result):
        plan = planner.replan(plan, state)
    return synthesize(state)
}
```

### Pitfalls

- **Plan rigidity** — plan icra zamanı yanlış olsa, model geri dönmür. Replan hook-u lazımdır.
- **Over-planning** — sadə tasklar üçün 10 addımlı plan yaradır. Task classifier əlavə et.
- **Step dependency modelling** — DAG çox sadəlikə approximasiya olunur, real dünyada dynamic dependencies var.

---

## 7. Plan-and-Solve — iki addımlı sadə variant

Wang et al., 2023 (ICLR 2023). Plan-and-Execute-un sadələşdirilmiş variantı. Tool yoxdur, yalnız reasoning:

```
Prompt: "İlk növbədə, problemi anlayaq və planı tərtib edək.
         Sonra planı addım-addım icra edək."
```

Bu, bir LLM çağırışında işləyir və CoT-dan 5-10% daha yaxşı nəticə verir arifmetik tasklarda. Plan-and-Execute-dan fərqi: tool yoxdur, multi-turn yoxdur.

Praktik istifadə: sistem prompt-da "Cavabdan əvvəl planı qur" təlimatını əlavə et. Bu **$0** cost artımı ilə accuracy-ni yüksəldir.

---

## 8. Self-Refine — iterativ özünütəkmilləşdirmə

Madaan et al., 2023 (CMU). Reflexion-dan fərq: evaluator yoxdur, aktor özü öz çıxışını tənqid edir.

```
v0 = model.generate(task)
for i in 1..N:
  feedback = model.critique(v0)
  if feedback.is_satisfied: break
  v0 = model.refine(v0, feedback)
return v0
```

Bu, yazı-based tasklarda (xülasə, essay, code cleanup) yaxşı işləyir. Kod-based tasklarda evaluator olmadan pis halüsinasiya edir ("mən yaxşı yazdım" deyir, amma kod xətalıdır).

### Self-Refine vs Reflexion

| Ölçü | Self-Refine | Reflexion |
|------|-------------|-----------|
| External evaluator | Yox (model özü) | Bəli (test, oracle) |
| Tool | İstəyə görə | İstəyə görə |
| Ən yaxşı istifadə | Yazı təkmilləşdirilməsi | Kod, SQL, verifiable task |
| Convergence risk | Yüksək (model öz səhvini görmür) | Aşağı (external signal) |

---

## 9. LATS (Language Agent Tree Search) — hibrid

Zhou et al., 2023. ToT + Reflexion + Monte Carlo Tree Search (MCTS). Çox mürəkkəbdir, real dünyada nadir istifadə olunur (akademik).

Konsept: ToT kimi ağac yarat, amma hər node-da Reflexion et. MCTS UCB1 formulu ilə ən gözəl trajectory-ni seç.

Coding benchmark-larda state-of-the-art, amma cost 100x ReAct-dən. Yalnız research və ya çox yüksək-dəyərli tasklar üçün (məsələn, avtomatik elmi kəşf).

---

## 10. Pattern seçim matrisi

```
┌─────────────────────┬─────────────────────────────────────────┐
│ Taskın xüsusiyyəti  │ Tövsiyə olunan pattern                  │
├─────────────────────┼─────────────────────────────────────────┤
│ Sadə, 1-3 tool call │ ReAct                                   │
│ Verifiable, retry   │ Reflexion                               │
│ Uzun plan, paralel  │ Plan-and-Execute                        │
│ Creative explorasiya│ Tree of Thoughts                        │
│ Yazı təkmilləşdirmə │ Self-Refine                             │
│ Arifmetik/riyazi    │ CoT və ya Plan-and-Solve                │
│ Qarışıq, produksiya │ ReAct + optional Reflexion fallback     │
│ Zoning/game         │ ToT / LATS                              │
└─────────────────────┴─────────────────────────────────────────┘
```

### Quick decision tree

```
Task verifiable-dirmi? (test/oracle var?)
│
├─ BƏLi → ilk cəhd ReAct et
│          uğursuz olsa → Reflexion (max 3 trial)
│
└─ XEYR → Yazılı çıxış?
           ├─ BƏLi → Self-Refine (2-3 iter)
           └─ XEYR → Yalnız ReAct
```

---

## 11. Cost və latency analizi

Real rəqəmlər (2026-04, Claude Sonnet 4.5 baseline, ortalama 2k input / 500 output token per call):

| Pattern | LLM çağırışı sayı | Cost | Latency (p50) |
|---------|-------------------|------|---------------|
| CoT (1 call) | 1 | $0.009 | 2s |
| ReAct (5 iter) | 5 | $0.045 | 10-15s |
| Self-Refine (3 iter) | 6 (3 critique + 3 refine) | $0.054 | 15-20s |
| Reflexion (3 trial × 5 iter) | 18 | $0.162 | 45-60s |
| Plan-and-Execute | 1 plan + 5 exec | $0.05 | 20s (paralel olsa 10s) |
| Tree of Thoughts (3×3×3) | 39 | $0.35 | 90s+ |
| LATS | 50-200 | $0.50-$2.00 | 3-10 dəq |

**Müşahidə**: Latency-lər paralel icra edilə bilən yerdə ciddi azalır. Plan-and-Execute-də addımlar async ola bilər.

---

## 12. Laravel PHP implementasiya — ReAct loop

PHP-də Claude Agent SDK yoxdur, amma pattern-i əl ilə qurmaq asandır. Aşağıdakı kod Anthropic Messages API-nı birbaşa istifadə edir (Guzzle ilə).

### app/Services/Agents/ReActAgent.php

```php
<?php

namespace App\Services\Agents;

use App\Services\Anthropic\AnthropicClient;
use App\Services\Agents\Tools\ToolRegistry;
use Illuminate\Support\Facades\Log;

class ReActAgent
{
    public function __construct(
        protected AnthropicClient $client,
        protected ToolRegistry $tools,
        protected int $maxIterations = 10,
    ) {}

    public function run(string $userPrompt, array $context = []): AgentResult
    {
        $messages = [
            ['role' => 'user', 'content' => $userPrompt],
        ];

        $iteration = 0;
        $totalUsage = ['input' => 0, 'output' => 0];

        while ($iteration++ < $this->maxIterations) {
            $response = $this->client->messages([
                'model' => 'claude-sonnet-4-5',
                'max_tokens' => 4096,
                'tools' => $this->tools->toSchema(),
                'messages' => $messages,
                'system' => $this->systemPrompt($context),
            ]);

            $totalUsage['input']  += $response['usage']['input_tokens'];
            $totalUsage['output'] += $response['usage']['output_tokens'];

            $messages[] = ['role' => 'assistant', 'content' => $response['content']];

            if ($response['stop_reason'] === 'end_turn') {
                return new AgentResult(
                    finalMessage: $this->extractText($response['content']),
                    iterations: $iteration,
                    usage: $totalUsage,
                );
            }

            if ($response['stop_reason'] !== 'tool_use') {
                throw new \RuntimeException("Gözlənilməz stop_reason: {$response['stop_reason']}");
            }

            // ReAct "Act" addımı — bütün tool_use blok-larını icra et
            $toolResults = [];
            foreach ($response['content'] as $block) {
                if ($block['type'] !== 'tool_use') continue;

                try {
                    $result = $this->tools->execute($block['name'], $block['input']);
                    $toolResults[] = [
                        'type' => 'tool_result',
                        'tool_use_id' => $block['id'],
                        'content' => json_encode($result),
                    ];
                } catch (\Throwable $e) {
                    Log::error('Tool execution failed', [
                        'tool' => $block['name'],
                        'error' => $e->getMessage(),
                    ]);
                    $toolResults[] = [
                        'type' => 'tool_result',
                        'tool_use_id' => $block['id'],
                        'is_error' => true,
                        'content' => "Xəta: {$e->getMessage()}",
                    ];
                }
            }

            $messages[] = ['role' => 'user', 'content' => $toolResults];
        }

        throw new \RuntimeException("Max iteration ({$this->maxIterations}) çatdı");
    }

    protected function systemPrompt(array $context): string
    {
        return <<<SYS
        Sən ReAct pattern-i ilə işləyən köməkçi agent-sən.
        Cavabdan əvvəl alət çağırışları lazımdırmı düşün.
        Alətlərin nəticəsini oxuyub gələcək qərarına tətbiq et.
        Kontekst: {$this->serializeContext($context)}
        SYS;
    }

    protected function extractText(array $content): string
    {
        $texts = [];
        foreach ($content as $block) {
            if ($block['type'] === 'text') $texts[] = $block['text'];
        }
        return implode("\n", $texts);
    }

    protected function serializeContext(array $c): string
    {
        return $c ? json_encode($c, JSON_UNESCAPED_UNICODE) : 'yoxdur';
    }
}
```

### İstifadə

```php
$agent = app(ReActAgent::class);
$result = $agent->run("Bakıdakı sabah hava necədir?");
echo $result->finalMessage;
echo "İterasiya: {$result->iterations}, Cost: ...\n";
```

### Observability hook

`AgentIteration` event-i yaradıb hər loop iterasiyasında dispatch et. Laravel listener OpenTelemetry span-ı qeyd edir:

```php
event(new AgentIterationStarted($sessionId, $iteration));
// ... loop ...
event(new AgentIterationCompleted($sessionId, $iteration, $toolsUsed, $duration));
```

---

## 13. Laravel PHP implementasiya — Reflexion loop (SQL query assistant)

Use-case: analyst natural language-də sual verir, agent SQL yazır, icra edir. Əgər xəta olsa, reflection yazıb yenidən sınayır.

### Şəma

```
┌───────────────────────────────────────────────────────┐
│  İstifadəçi: "Ötən həftənin ən aktiv 10 müştərisi"    │
└───────────────┬───────────────────────────────────────┘
                │
                ▼
         ┌──────────────┐
         │ Actor (LLM)  │── SQL yarat
         └──────┬───────┘
                │
                ▼
         ┌──────────────┐
         │ DB executor  │── EXPLAIN + LIMIT-li test
         └──────┬───────┘
                │
         ┌──────┴──────┐
         │             │
      uğurlu?       xəta?
         │             │
         ▼             ▼
      Cavab     ┌──────────────────┐
                │ Self-Reflector   │
                │ (niyə səhv oldu?)│
                └──────┬───────────┘
                       ▼
                 reflections[]  ── Actor-ə növbəti cəhddə
```

### app/Services/Agents/ReflexionSqlAgent.php

```php
<?php

namespace App\Services\Agents;

use App\Services\Anthropic\AnthropicClient;
use Illuminate\Support\Facades\DB;

class ReflexionSqlAgent
{
    public function __construct(
        protected AnthropicClient $client,
        protected int $maxTrials = 3,
    ) {}

    public function answer(string $question, string $schema): SqlAnswer
    {
        $reflections = [];
        $lastAttempt = null;

        for ($trial = 1; $trial <= $this->maxTrials; $trial++) {
            // Actor — SQL yarat
            $sql = $this->generateSql($question, $schema, $reflections, $lastAttempt);

            // Evaluator — icra et və xətaları tut
            $eval = $this->evaluate($sql);

            if ($eval->isSuccess()) {
                return new SqlAnswer(
                    sql: $sql,
                    rows: $eval->rows,
                    trial: $trial,
                    reflections: $reflections,
                );
            }

            // Self-Reflector — niyə uğursuz oldu?
            $reflections[] = $this->reflect($sql, $eval->error, $question);
            $lastAttempt = $sql;
        }

        throw new \RuntimeException(
            "Reflexion {$this->maxTrials} trial-dən sonra uğursuz."
        );
    }

    protected function generateSql(
        string $question,
        string $schema,
        array $reflections,
        ?string $lastAttempt,
    ): string {
        $reflectionBlock = empty($reflections)
            ? ''
            : "Əvvəlki səhvlər və reflection-lar:\n" . implode("\n---\n", $reflections);

        $lastBlock = $lastAttempt ? "Son sınaq:\n```sql\n{$lastAttempt}\n```" : '';

        $prompt = <<<PROMPT
        Sxem:
        {$schema}

        Sual: {$question}

        {$reflectionBlock}

        {$lastBlock}

        Yalnız PostgreSQL SQL qaytar, açıqlama yoxdur. LIMIT əlavə et.
        PROMPT;

        $resp = $this->client->messages([
            'model' => 'claude-sonnet-4-5',
            'max_tokens' => 1024,
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ]);

        return $this->stripMarkdown($resp['content'][0]['text']);
    }

    protected function evaluate(string $sql): SqlEvaluation
    {
        try {
            // Təhlükəsiz read-only connection
            $rows = DB::connection('analytics_readonly')->select($sql);
            return SqlEvaluation::success($rows);
        } catch (\Throwable $e) {
            return SqlEvaluation::error($e->getMessage());
        }
    }

    protected function reflect(string $sql, string $error, string $question): string
    {
        $prompt = <<<PROMPT
        Aşağıdakı SQL icra zamanı xəta verdi.
        Sual: {$question}
        SQL: {$sql}
        Xəta: {$error}

        1-2 cümlədə, niyə bu xətanın baş verdiyini və
        növbəti sınaqda nəyi fərqli etməli olduğunu yaz.
        Dəqiq ol: "`users` cədvəlində `created_at` sütunu yoxdur, `registered_at`-dir" kimi.
        PROMPT;

        $resp = $this->client->messages([
            'model' => 'claude-haiku-4-5',  // ucuz model
            'max_tokens' => 256,
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ]);

        return $resp['content'][0]['text'];
    }

    protected function stripMarkdown(string $text): string
    {
        return trim(preg_replace('/^```sql\s*|\s*```$/m', '', $text));
    }
}
```

### reflections cədvəli — episodic memory

Production-da reflection-ları DB-də saxla ki, oxşar suallarda hot-start edə biləsən:

```sql
CREATE TABLE agent_reflections (
    id BIGSERIAL PRIMARY KEY,
    agent_name VARCHAR(64) NOT NULL,
    task_hash CHAR(64) NOT NULL,    -- question hash
    trial_number INT NOT NULL,
    reflection TEXT NOT NULL,
    was_successful BOOLEAN NOT NULL,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    INDEX (agent_name, task_hash)
);
```

Oxşar task gələndə son 5 reflection-u prompt-a inject et.

---

## 14. Production pitfalls

### 14.1 Infinite loop

Ən çox rastlanan xəta. Agent hey eyni tool-u çağırır, heç dayanmır. Səbəblər:

- **Poor tool response** — tool "yoxdur" deyil, uğurla boş array qaytarır. Model "axtarmağa davam etməliyəm" düşünür.
- **System prompt qeyri-dəqiq** — "task bitdikdə dayan" yazmadın.
- **Max iteration guardrail yoxdur**.

Çarə:

```php
if ($iteration >= $this->maxIterations) {
    $this->forceStop($messages);  // "Dayanmağa məcbursan" mesajı
    break;
}
```

### 14.2 Tool result çox böyükdür

Agent `list_users` çağırır, 10k nəticə gəlir, context 200k-ə çatır, növbəti çağırış $15-ə başa gəlir. Çarə:

- Tool-lara **default LIMIT** qoy.
- Agent-ə `summarize_result` tool-u ver.
- Pagination inject et.

### 14.3 Reward hacking / reflection shortcut

Reflexion-da agent "sistem söylədi test keçdi, amma mən yalnız `assert True` yazdım" kimi shortcut tapır. Code-gen benchmark-larında bu klassik problemdir. Çarə:

- Oracle-ı stricter et — coverage tələb et.
- Reflection prompt-da "shortcut-dan qaçın" yaz.
- Multi-criteria evaluation (test + code review + style check).

### 14.4 Degrading reflection

5+ trial-dan sonra reflections pis olmağa başlayır. Çarə: `maxTrials = 3` + `diversity temperature` artırılması.

### 14.5 Plan obsolescence

Plan-and-Execute-də plan 5 dəqiqə əvvəl yaradılıb, amma icra vaxtı məlumatlar dəyişib. Replan trigger-i lazımdır:

```php
if ($step->result->has('replan_signal')) {
    $plan = $this->planner->replan($plan, $state);
}
```

### 14.6 ToT cost bomb

Dev-də 3x3x3 ağac işlədi, production-da traffic artanda $10k aylıq API fakturası gəldi. Çarə:

- Per-user rate limit + per-session cost cap.
- Branching-i task difficulty-ə görə adaptiv et.
- Haiku ilə prune et, yalnız promising node-lar Opus-a gedir.

---

## 15. 2026 reasoning model-ləri bu pattern-ləri necə dəyişdirir

Claude 3.7 Sonnet (extended thinking, 2025) və Claude 4.5 Opus (genişləndirilmiş thinking, 2026), habelə OpenAI o3 — bunlar built-in chain-of-thought reasoning-i olan modellərdir. Model özü daxilində ağacı araşdırır, çoxlu hypothesis qurur, sonra cavab verir.

### Nə dəyişir

| Pattern | 2024 (GPT-4 era) | 2026 (reasoning models) |
|---------|------------------|-------------------------|
| CoT | Manual prompt lazım | Built-in |
| ToT | Manual LLM ağacı | Model özü daxilində edir (thinking block) |
| Plan-and-Solve | Manual "plan sonra həll" | Thinking block avtomatik edir |
| ReAct | Hələ lazımdır (tool call) | Hələ lazımdır (tool call) |
| Reflexion | Hələ lazımdır (retry) | Hələ lazımdır (retry) |
| Plan-and-Execute | Hələ lazımdır | Daha səmərəli (plan faza 1 çağırışda) |

### Praktik tövsiyə

- **Tool-less reasoning** (math, logic puzzle) — extended thinking istifadə et, ToT-a ehtiyac yoxdur.
- **Tool-based agent** — hələ də ReAct/Reflexion lazımdır. Extended thinking tool call-ı əvəz etmir.
- **Cost baxımından** — thinking token-lər 5x bahalıdır, amma N çağırışdan ucuzdur. Əvvəl 10 çağırışlıq ToT edirdin, indi 1 extended thinking çağırışı.

### Nümunə: əvvəl və sonra

**Əvvəl (ToT ilə Math Olympiad):**

```
39 çağırış × 2k input × 500 output = ~$0.35
Latency: 90s
```

**İndi (extended thinking Opus 4.5):**

```
1 çağırış × 2k input × 15k thinking + 500 output = ~$0.15
Latency: 25s
```

Həm ucuz, həm tez. ToT artıq yalnız explorativ tasklar üçün qalır.

---

## Xülasə

- **ReAct** — production agent-lərin standart pattern-i. Tool calling ilə təbii uyğun gəlir.
- **Reflexion** — verifiable task-larda accuracy-ni artırır, amma latency bahasına.
- **Plan-and-Execute** — uzun, paralel işlər üçün. Model bifurkasiyası ilə cost azaldır.
- **Tree of Thoughts** — creative explorativ tasklarda güclü, cost baxımından bahalı. 2026-da extended thinking onu bir çox halda əvəz edir.
- **Self-Refine** — yazılı content üçün uyğundur, lakin external evaluator olmadan konvergensiya təhlükəsi var.
- **LATS** — akademik, real production-da nadir.

Senior həyatda 80% hal ReAct-lı bir agent-dir. Verifiable uğursuzluq halında Reflexion fallback əlavə edirsiniz. Uzun workflow-lar üçün Plan-and-Execute seçirsiniz. Qalanı adətən over-engineering-dir.

---

**Növbəti fayl:** `08-agent-orchestration-patterns.md` — tək agent yerinə bir neçə agent-i necə qurmaq (supervisor, hierarchical, swarm, blackboard).

---

## Praktik Tapşırıqlar

### Tapşırıq 1: ReAct Agent

`ReActAgent` sinifi implement et. Tool-lar: `search_docs(query)`, `calculate(expr)`. 10 multi-step sual üçün (məs. "Şirkətin Q3 gəliri Q2-dən neçə faiz artıb?") agent-i çalışdır. Hər Thought/Action/Observation addımını log et. Kaç iteration average olduğunu ölç.

### Tapşırıq 2: Reflexion İmplementasiyası

`ReActAgent`-ə `ReflexionLayer` əlavə et: agent cavab qaytardıqdan sonra özünü eval et ("Bu cavab sualı tam cavablandırdı?"). Self-eval "xeyr" olduqda, reflection-ı messages-ə əlavə edib yenidən cəhd et. Max 2 reflection. Reflexion olmadan vs olduqda quality score müqayisə et.

### Tapşırıq 3: Plan-and-Execute vs ReAct

Mürəkkəb research tapşırığı (məs. "5 competitor-ı araşdır, hər biri üçün pricing, features, target market analiz et") üçün iki pattern-ı müqayisə et. Plan-and-Execute: əvvəlcə plan yaz, sonra icra et. ReAct: addım-addım. Hansı pattern daha az hallucination edir? Hansı daha az token istifadə edir?

---

## Əlaqəli Mövzular

- `05-build-custom-agent-laravel.md` — ReAct pattern-in Laravel implementasiyası
- `03-agent-tool-design-principles.md` — Reasoning üçün optimal tool dizaynı
- `08-agent-orchestration-patterns.md` — Multi-agent reasoning: supervisor, swarm
- `../02-claude-api/07-extended-thinking.md` — Extended thinking vs ReAct tradeoffs
