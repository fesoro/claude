# Claude Agent SDK Dərindən: Production Agent Qurmaq (TS + Laravel Eşdəyər) (Lead)

> **Oxucu:** Senior PHP/Laravel tərtibatçılar
> **Ön şərtlər:** Agent dövrü (24-ci fayl), tool calling, Laravel queues, əsas TypeScript
> **Tarix:** 2026-04-21

---

## Mündəricat

1. Claude Agent SDK nədir və nə üçün var
2. API Surface — query(), tools, subagents, MCP, permissions, hooks
3. TypeScript ilə tam nümunə — Support Ticket Triage Agent
4. Cost tracking və session management
5. PHP-nin faktiki vəziyyəti — rəsmi SDK yoxdur
6. Laravel-da eyni pattern-i qurmaq — arxitektura
7. ClaudeAgent xidməti: loop control, tool registry, hook events
8. Subagent spawn — child process vs HTTP
9. Tam işlək nümunə — Research Agent (daxili docs + DB + xarici API)
10. Test, observability və production qayğıları

---

## 1. Claude Agent SDK nədir və nə üçün var

Claude Agent SDK (Anthropic tərəfindən rəsmi) agent qurmaq üçün "batteries-included" kitabxanadır. Əsas dil dəstəyi: **TypeScript** və **Python**. PHP üçün rəsmi dəstək **yoxdur** — bu fayl sizə həm SDK-nı (TS ilə) öyrədir, həm də Laravel-da eyni dizaynı PHP ilə necə qurmağı göstərir.

SDK sizə nə verir:

- **`query()`** — tək çağırışla tam agent dövrü (perceive → think → act → repeat).
- **Tool registry** — alətləri (functions) elan edib modelin avtomatik çağırmasına icazə verirsən.
- **Subagents** — alt-agentlər spawn etmək (əsas agent bir "plan agent"-dir, işin müəyyən hissələrini "kod agenti"-nə ötürür).
- **MCP (Model Context Protocol) inteqrasiyası** — xarici MCP server-ləri tool-lar kimi qoşmaq.
- **Permissions** — hansı alətə icazə var, hansına yox (read-only mode, confirm-before-write və s.).
- **Hooks** — `beforeToolUse`, `afterToolUse`, `onMessage`, `onError` kimi lifecycle event-lər.
- **Cost tracking** — token sayımı və $ hesablaması avtomatik.
- **Session management** — multi-turn söhbətlər, transcript persistence, resume.

Niyə SDK istifadə etməli? — çünki agent dövrünü özün yazmaq 200+ sətir boilerplate-dir: tool execution loop, retry, context trimming, error recovery, observability. SDK bunları bir yerdə verir.

---

## 2. API Surface

### 2.1 `query()` — Giriş nöqtəsi

```
query({
  prompt,
  model,
  tools,
  subagents,
  permissions,
  hooks,
  maxTurns,
  session,
})
  → AsyncIterable<Event>
```

Dönüş dəyəri — hadisələr axını (async iterator). Hər element: `assistant_message`, `tool_use`, `tool_result`, `usage`, `error` və s. Developer bu axını consume edir və UI-da göstərir (streaming UX).

### 2.2 Tool elan etmək

```ts
import { tool } from "@anthropic-ai/claude-agent-sdk";
import { z } from "zod";

const searchTickets = tool({
  name: "search_tickets",
  description: "Müştəri ticket-lərini axtar. Status və müəllif filtrinə görə.",
  inputSchema: z.object({
    query: z.string().describe("Açar sözlər (məsələn: 'ödəniş xətası')"),
    status: z.enum(["open", "closed", "pending"]).optional(),
    limit: z.number().min(1).max(50).default(10),
  }),
  async execute({ query, status, limit }) {
    // DB çağırışı və ya HTTP
    return await db.tickets.search({ query, status, limit });
  },
});
```

Vacib qayda: **`description` adı əvəzlədir**. Model tool-u adından daha çox description-dan oxuyur. (Bunu 32-ci faylda detallı açacağıq.)

### 2.3 Subagents

```ts
const codeReviewer = subagent({
  name: "code_reviewer",
  description: "Kod diff-ləri nəzərdən keçirir, security risklərini tapır.",
  model: "claude-sonnet-4-5",
  tools: [readFile, runLinter],
  systemPrompt: "Sən təcrübəli code reviewer-sən...",
});

const mainAgent = await query({
  prompt: "Bu PR-i nəzərdən keçir: #1234",
  model: "claude-opus-4-5",
  subagents: [codeReviewer],
  // ...
});
```

Subagent öz context window-una malikdir — əsas agent yalnız subagent-in nəticəsini görür, bütün dialog deyil. Bu **context isolation** prinsipidir: böyük işləri kiçik agent-lərə bölmək.

### 2.4 MCP inteqrasiyası

```ts
import { mcpServer } from "@anthropic-ai/claude-agent-sdk";

const github = mcpServer({
  command: "npx",
  args: ["-y", "@modelcontextprotocol/server-github"],
  env: { GITHUB_TOKEN: process.env.GITHUB_TOKEN! },
});

await query({
  prompt: "Son 5 issue-nu göstər",
  mcpServers: [github],
  // ...
});
```

MCP — Anthropic-in açıq standartıdır: xarici tool-ları bir dəfə MCP server kimi yazırsan, istənilən agent (Claude Code, öz agent-in, başqası) onu istifadə edə bilir.

### 2.5 Permissions

```ts
permissions: {
  mode: "auto",          // "auto" | "ask" | "read-only"
  allowedTools: ["search_tickets", "read_*"],
  deniedTools: ["delete_*"],
  onDangerousAction: async ({ tool, input }) => {
    return await confirmViaSlack(tool, input);
  },
}
```

Production-da `mode: "ask"` risklidir (insan olmur). Əvəzində: whitelist (`allowedTools`) + audit log.

### 2.6 Hooks

```ts
hooks: {
  beforeToolUse: async ({ tool, input, context }) => {
    logger.info({ tool: tool.name, input }, "Tool çağırılır");
    if (tool.name === "delete_user" && context.env === "prod") {
      throw new Error("Prod-da delete_user bloklandı");
    }
  },
  afterToolUse: async ({ tool, output, duration }) => {
    metrics.histogram("tool.duration", duration, { tool: tool.name });
  },
  onMessage: async ({ message, usage }) => {
    await saveToTranscript(message, usage);
  },
  onError: async ({ error, turn }) => {
    await alertOps(error, turn);
  },
}
```

Hook-lar production-da critical-dir: audit, metrics, cost budget enforcement, PII redaction bütün bu layer-də olur.

### 2.7 Cost tracking

```ts
const result = await query({...});
console.log(result.usage);
// { input_tokens: 1234, output_tokens: 567,
//   cache_read_input_tokens: 800, cost_usd: 0.0234 }
```

SDK hər turn üçün usage saxlayır. Session bitəndə cəmi çıxarır.

### 2.8 Session management

```ts
const session = createSession({ id: "user_42_chat_99" });
await query({ prompt: "Salam", session });
await query({ prompt: "Əvvəl nə danışdıq?", session });
// Session transcript-i avtomatik saxlayır
```

---

## 3. TypeScript ilə tam nümunə — Support Ticket Triage Agent

Real use case: customer support-da yeni ticket gəlir, agent avtomatik:
1. Ticket-i DB-dən çəkir
2. Oxşar həll olunmuş ticket-ləri tapır
3. Müştəri tarixçəsinə baxır
4. Priority və category təyin edir
5. Əgər bilirsə — avtomatik cavab yazır; bilmirsə — insan agent-ə escalation edir

```ts
// src/agents/ticketTriageAgent.ts
import { query, tool, subagent } from "@anthropic-ai/claude-agent-sdk";
import { z } from "zod";
import { db } from "../db";
import { vectorStore } from "../vectorStore";
import { slack } from "../slack";
import { logger } from "../logger";

// ---------- Tools ----------

const getTicket = tool({
  name: "get_ticket",
  description: "Ticket ID-yə görə ticket detallarını (mövzu, mətn, müştəri) qaytarır.",
  inputSchema: z.object({ id: z.string() }),
  async execute({ id }) {
    const t = await db.tickets.findUnique({ where: { id } });
    if (!t) return { error: "Ticket tapılmadı" };
    return {
      id: t.id,
      subject: t.subject,
      body: t.body.slice(0, 4000), // size budget
      customer_id: t.customerId,
      created_at: t.createdAt.toISOString(),
    };
  },
});

const findSimilarSolved = tool({
  name: "find_similar_solved_tickets",
  description:
    "Vektor axtarış — mövcud ticket-ə semantik oxşar, artıq həll olunmuş ticket-ləri qaytarır.",
  inputSchema: z.object({
    query_text: z.string().describe("Axtarış mətni — ticket mövzusu + qısa body"),
    limit: z.number().min(1).max(10).default(5),
  }),
  async execute({ query_text, limit }) {
    const hits = await vectorStore.search(query_text, {
      filter: { status: "solved" },
      limit,
    });
    return hits.map((h) => ({
      ticket_id: h.id,
      score: h.score,
      resolution: h.metadata.resolution?.slice(0, 500),
    }));
  },
});

const getCustomerHistory = tool({
  name: "get_customer_history",
  description:
    "Müştərinin son 90 gündəki ticket tarixçəsini qaytarır (status və qısa mövzularla).",
  inputSchema: z.object({
    customer_id: z.string(),
    days: z.number().default(90),
  }),
  async execute({ customer_id, days }) {
    const since = new Date(Date.now() - days * 86400_000);
    const rows = await db.tickets.findMany({
      where: { customerId: customer_id, createdAt: { gte: since } },
      select: { id: true, subject: true, status: true, createdAt: true },
      take: 20,
      orderBy: { createdAt: "desc" },
    });
    return rows;
  },
});

const classifyTicket = tool({
  name: "classify_ticket",
  description:
    "Ticket üçün category və priority təyin et. Kateqoriyalar: billing, technical, account, other. Priority: low/medium/high/urgent.",
  inputSchema: z.object({
    ticket_id: z.string(),
    category: z.enum(["billing", "technical", "account", "other"]),
    priority: z.enum(["low", "medium", "high", "urgent"]),
    reasoning: z.string().max(300),
  }),
  async execute({ ticket_id, category, priority, reasoning }) {
    await db.tickets.update({
      where: { id: ticket_id },
      data: { category, priority, classifierReasoning: reasoning },
    });
    return { ok: true };
  },
});

const draftReply = tool({
  name: "draft_reply",
  description:
    "Ticket üçün cavab hazırla. Cavab müştəriyə göndərilməmişdən əvvəl human review-dan keçəcək.",
  inputSchema: z.object({
    ticket_id: z.string(),
    reply_markdown: z.string().min(50).max(2000),
    confidence: z.number().min(0).max(1),
  }),
  async execute({ ticket_id, reply_markdown, confidence }) {
    await db.ticketDrafts.create({
      data: { ticketId: ticket_id, body: reply_markdown, confidence },
    });
    return { ok: true, requires_human_review: confidence < 0.85 };
  },
});

const escalateToHuman = tool({
  name: "escalate_to_human",
  description: "Ticket-i insan agent-ə ötür. Bot həll edə bilməyəndə istifadə et.",
  inputSchema: z.object({
    ticket_id: z.string(),
    reason: z.string().max(500),
    suggested_team: z.enum(["l1", "l2", "billing", "security"]),
  }),
  async execute({ ticket_id, reason, suggested_team }) {
    await slack.sendToTeam(suggested_team, { ticketId: ticket_id, reason });
    await db.tickets.update({
      where: { id: ticket_id },
      data: { status: "escalated", escalationReason: reason },
    });
    return { ok: true };
  },
});

// ---------- Subagent: hesab üçün security yoxlaması ----------

const securityReviewer = subagent({
  name: "security_reviewer",
  description:
    "Hesab ilə bağlı şübhəli aktivlik varmı? Login-lər, IP-lər, ödəniş anomaliyaları.",
  model: "claude-sonnet-4-5",
  tools: [getCustomerHistory, /* read-only audit log tool-ları */],
  systemPrompt: `Sən security analyst-sən. Müştəri tarixçəsində şübhəli
naxış axtar. Cavabın qısa olsun: {risk_level, findings[]}.`,
});

// ---------- Main Agent ----------

export async function triageTicket(ticketId: string) {
  const result = await query({
    model: "claude-sonnet-4-5",
    systemPrompt: `Sən customer support triage agent-isən. Yeni ticket-ə baxırsan.
Məqsədin: ticket-i təsnif etmək, tarixçəsini anlamaq və ya avtomatik cavab
hazırlamaq, ya da insan agent-ə escalate etmək. Heç vaxt müştəri məlumatlarını
uydurma. Əmin deyilsənsə — escalate_to_human istifadə et.`,
    prompt: `Aşağıdakı ticket üçün triage et: ${ticketId}`,
    tools: [
      getTicket,
      findSimilarSolved,
      getCustomerHistory,
      classifyTicket,
      draftReply,
      escalateToHuman,
    ],
    subagents: [securityReviewer],
    permissions: {
      mode: "auto",
      allowedTools: ["*"],
      deniedTools: ["delete_*"],
    },
    hooks: {
      beforeToolUse: async ({ tool, input }) => {
        logger.info({ agent: "triage", tool: tool.name, input }, "tool_call");
      },
      afterToolUse: async ({ tool, duration }) => {
        logger.info({ tool: tool.name, duration_ms: duration }, "tool_done");
      },
      onMessage: async ({ usage }) => {
        logger.info({ usage }, "turn_usage");
      },
    },
    maxTurns: 12,
  });

  // Stream-i consume et (və ya cəmi nəticəni gözlə)
  let lastMessage = "";
  for await (const event of result) {
    if (event.type === "assistant_message") lastMessage = event.text;
  }

  return { text: lastMessage, usage: result.usage };
}
```

Diqqət: `maxTurns: 12` — agent loop-dan çıxmasa büdcə dayandırır. Production-da **həmişə** max turns qoyun.

---

## 4. Cost tracking və session management

```ts
// Daily cost budget enforcement
const session = createSession({ id: `tenant_${tenantId}_daily` });

await query({
  session,
  prompt: "...",
  hooks: {
    beforeToolUse: async () => {
      const spent = await redis.get(`cost:tenant:${tenantId}:today`);
      if (parseFloat(spent ?? "0") > tenantBudget) {
        throw new BudgetExceededError();
      }
    },
    onMessage: async ({ usage }) => {
      await redis.incrbyfloat(
        `cost:tenant:${tenantId}:today`,
        usage.cost_usd,
      );
    },
  },
});
```

Session-lar `{session_id}.jsonl` fayllarında və ya DB-də saxlanıla bilər. Resume: session ID-yə görə SDK əvvəlki mesajları yükləyir.

---

## 5. PHP-nin faktiki vəziyyəti

Anthropic-in rəsmi PHP Agent SDK-sı **yoxdur** (Aprel 2026 tarixinə). Community tərəfindən bir neçə paket var, amma production-ready sayılmır. Dolayısıyla, senior PHP dev-ə iki yol var:

1. **TypeScript micro-service yaz** — agent Node.js-də, PHP yalnız HTTP ilə çağırır. Plus: rəsmi SDK-dan istifadə. Minus: əlavə servis, əlavə deploy.
2. **Laravel-da eyni pattern-i özün qur** — aşağıda göstəririk. Plus: bir dil, bir deploy. Minus: SDK feature-larını (MCP, advanced session) özün yazmalısan.

Biz 2-ni seçirik, çünki əksər komandaların artıq böyük Laravel codebase-i var.

---

## 6. Laravel-da eyni pattern-i qurmaq — arxitektura

```
┌────────────────────────────────────────────────────────────────┐
│                       Laravel App                              │
│                                                                │
│   Controller ──▶ ClaudeAgent::for($user)->run($prompt)         │
│                           │                                    │
│                           ▼                                    │
│                  ┌────────────────┐                            │
│                  │  AgentRunner   │  ◀──── ToolRegistry        │
│                  │  (loop control)│  ◀──── HookDispatcher      │
│                  │                │  ◀──── Permissions         │
│                  └────────┬───────┘                            │
│                           │                                    │
│            ┌──────────────┼──────────────┐                     │
│            ▼              ▼              ▼                     │
│     AnthropicClient   ToolExecutor   SubagentSpawner           │
│            │              │              │                     │
│            ▼              ▼              ▼                     │
│     api.anthropic      Tools[]       (queue job                │
│                        (read DB,     + HTTP to                 │
│                         call API,    child agent)              │
│                         ...)                                   │
└────────────────────────────────────────────────────────────────┘
```

Komponentlər:

- **`ClaudeAgent`** — fluent fasad (Laravel facade pattern).
- **`AgentRunner`** — loop-un özü (perceive → think → act → repeat).
- **`ToolRegistry`** — tool-ları qeydiyyatdan keçirir (DI ilə).
- **`HookDispatcher`** — Laravel event-lərinə `AgentToolCalled`, `AgentMessageReceived` göndərir.
- **`Permissions`** — middleware stil: icazəsi olmayan tool-u bloklayır.
- **`AnthropicClient`** — Guzzle-based API client (streaming dəstəkli).
- **`SubagentSpawner`** — child agent-i queue job və ya HTTP ilə spawn edir.

---

## 7. `ClaudeAgent` xidməti — tam PHP kodu

### 7.1 Tool interface və attribute

```php
<?php
// app/AI/Tools/Contracts/Tool.php

namespace App\AI\Tools\Contracts;

interface Tool
{
    public function name(): string;
    public function description(): string;
    /** JSON Schema array */
    public function inputSchema(): array;
    public function execute(array $input): array;
}
```

### 7.2 ToolRegistry

```php
<?php
// app/AI/ToolRegistry.php

namespace App\AI;

use App\AI\Tools\Contracts\Tool;
use InvalidArgumentException;

class ToolRegistry
{
    /** @var array<string, Tool> */
    private array $tools = [];

    public function register(Tool $tool): self
    {
        $this->tools[$tool->name()] = $tool;
        return $this;
    }

    public function get(string $name): Tool
    {
        return $this->tools[$name]
            ?? throw new InvalidArgumentException("Tool '{$name}' qeydiyyatdan keçməyib");
    }

    /** @return list<array> — Anthropic API formatında */
    public function toApiFormat(array $allowed = null): array
    {
        $out = [];
        foreach ($this->tools as $name => $tool) {
            if ($allowed !== null && !$this->matches($name, $allowed)) {
                continue;
            }
            $out[] = [
                'name' => $tool->name(),
                'description' => $tool->description(),
                'input_schema' => $tool->inputSchema(),
            ];
        }
        return $out;
    }

    private function matches(string $name, array $patterns): bool
    {
        foreach ($patterns as $p) {
            if ($p === '*' || $name === $p) return true;
            if (str_ends_with($p, '*') && str_starts_with($name, substr($p, 0, -1))) {
                return true;
            }
        }
        return false;
    }
}
```

### 7.3 Hook event-lər (Laravel event pattern)

```php
<?php
// app/AI/Events/AgentToolCalled.php

namespace App\AI\Events;

class AgentToolCalled
{
    public function __construct(
        public readonly string $agentId,
        public readonly string $toolName,
        public readonly array $input,
    ) {}
}

// app/AI/Events/AgentToolCompleted.php
class AgentToolCompleted
{
    public function __construct(
        public readonly string $agentId,
        public readonly string $toolName,
        public readonly array $output,
        public readonly float $durationMs,
    ) {}
}

// app/AI/Events/AgentTurnUsage.php
class AgentTurnUsage
{
    public function __construct(
        public readonly string $agentId,
        public readonly int $inputTokens,
        public readonly int $outputTokens,
        public readonly int $cacheReadTokens,
        public readonly float $costUsd,
    ) {}
}
```

### 7.4 AgentRunner — loop-un özü

```php
<?php
// app/AI/AgentRunner.php

namespace App\AI;

use App\AI\Events\{AgentToolCalled, AgentToolCompleted, AgentTurnUsage};
use App\AI\Exceptions\{MaxTurnsExceeded, BudgetExceeded, PermissionDenied};
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

class AgentRunner
{
    public function __construct(
        private AnthropicClient $client,
        private ToolRegistry $registry,
        private CostTracker $cost,
    ) {}

    /**
     * @param array $config = [
     *   'model' => 'claude-sonnet-4-5',
     *   'system' => '...',
     *   'prompt' => '...',
     *   'max_turns' => 12,
     *   'allowed_tools' => ['*'],
     *   'denied_tools' => ['delete_*'],
     *   'tenant_id' => 42,
     *   'budget_usd' => 1.00,
     * ]
     */
    public function run(array $config): array
    {
        $agentId = 'agt_' . Str::ulid();
        $messages = [
            ['role' => 'user', 'content' => $config['prompt']],
        ];
        $totalUsage = ['input' => 0, 'output' => 0, 'cache_read' => 0, 'cost' => 0.0];

        for ($turn = 1; $turn <= ($config['max_turns'] ?? 12); $turn++) {
            $this->enforceBudget($config, $totalUsage);

            $response = $this->client->messages([
                'model' => $config['model'] ?? 'claude-sonnet-4-5',
                'system' => $config['system'] ?? null,
                'messages' => $messages,
                'tools' => $this->registry->toApiFormat($config['allowed_tools'] ?? null),
                'max_tokens' => 4096,
            ]);

            // Usage accumulate
            $u = $response['usage'];
            $totalUsage['input'] += $u['input_tokens'];
            $totalUsage['output'] += $u['output_tokens'];
            $totalUsage['cache_read'] += $u['cache_read_input_tokens'] ?? 0;
            $turnCost = $this->cost->calculate($config['model'], $u);
            $totalUsage['cost'] += $turnCost;
            Event::dispatch(new AgentTurnUsage(
                $agentId,
                $u['input_tokens'], $u['output_tokens'],
                $u['cache_read_input_tokens'] ?? 0, $turnCost,
            ));

            $messages[] = ['role' => 'assistant', 'content' => $response['content']];

            // stop_reason 'end_turn' — agent tamamladı
            if ($response['stop_reason'] === 'end_turn') {
                return [
                    'agent_id' => $agentId,
                    'final_text' => $this->extractText($response['content']),
                    'usage' => $totalUsage,
                    'turns' => $turn,
                ];
            }

            // 'tool_use' — tool-ları icra et
            if ($response['stop_reason'] === 'tool_use') {
                $toolResults = $this->executeToolBlocks(
                    $response['content'], $config, $agentId,
                );
                $messages[] = ['role' => 'user', 'content' => $toolResults];
                continue;
            }

            throw new \RuntimeException(
                "Gözlənilməz stop_reason: " . $response['stop_reason']
            );
        }

        throw new MaxTurnsExceeded("Agent {$agentId} maksimal turn-a çatdı");
    }

    private function executeToolBlocks(array $content, array $config, string $agentId): array
    {
        $results = [];
        foreach ($content as $block) {
            if ($block['type'] !== 'tool_use') continue;

            $name = $block['name'];
            $input = $block['input'];

            // Permission yoxlaması
            if ($this->isDenied($name, $config['denied_tools'] ?? [])) {
                throw new PermissionDenied("Tool '{$name}' qadağandır");
            }

            Event::dispatch(new AgentToolCalled($agentId, $name, $input));
            $start = microtime(true);

            try {
                $output = $this->registry->get($name)->execute($input);
                $isError = false;
            } catch (\Throwable $e) {
                $output = [
                    'error' => $e->getMessage(),
                    'hint' => 'Input-u düzəldib yenidən cəhd et',
                ];
                $isError = true;
            }

            $duration = (microtime(true) - $start) * 1000;
            Event::dispatch(new AgentToolCompleted($agentId, $name, $output, $duration));

            $results[] = [
                'type' => 'tool_result',
                'tool_use_id' => $block['id'],
                'content' => json_encode($output, JSON_UNESCAPED_UNICODE),
                'is_error' => $isError,
            ];
        }
        return $results;
    }

    private function enforceBudget(array $config, array $usage): void
    {
        if (isset($config['budget_usd']) && $usage['cost'] >= $config['budget_usd']) {
            throw new BudgetExceeded(sprintf(
                'Agent büdcəsi aşıldı: $%.4f / $%.4f',
                $usage['cost'], $config['budget_usd'],
            ));
        }
    }

    private function isDenied(string $name, array $patterns): bool
    {
        foreach ($patterns as $p) {
            if ($p === $name) return true;
            if (str_ends_with($p, '*') && str_starts_with($name, substr($p, 0, -1))) {
                return true;
            }
        }
        return false;
    }

    private function extractText(array $content): string
    {
        $out = '';
        foreach ($content as $b) if ($b['type'] === 'text') $out .= $b['text'];
        return $out;
    }
}
```

### 7.5 ClaudeAgent fluent fasad

```php
<?php
// app/AI/ClaudeAgent.php

namespace App\AI;

class ClaudeAgent
{
    private array $config = [
        'model' => 'claude-sonnet-4-5',
        'max_turns' => 12,
        'allowed_tools' => ['*'],
        'denied_tools' => [],
    ];

    public function __construct(private AgentRunner $runner) {}

    public function model(string $m): self { $this->config['model'] = $m; return $this; }
    public function system(string $s): self { $this->config['system'] = $s; return $this; }
    public function maxTurns(int $n): self { $this->config['max_turns'] = $n; return $this; }
    public function allowTools(array $t): self { $this->config['allowed_tools'] = $t; return $this; }
    public function denyTools(array $t): self { $this->config['denied_tools'] = $t; return $this; }
    public function budget(float $usd): self { $this->config['budget_usd'] = $usd; return $this; }
    public function forTenant(int $id): self { $this->config['tenant_id'] = $id; return $this; }

    public function run(string $prompt): array
    {
        $this->config['prompt'] = $prompt;
        return $this->runner->run($this->config);
    }
}
```

İstifadə:

```php
$result = app(ClaudeAgent::class)
    ->model('claude-sonnet-4-5')
    ->system('Sən research agent-sən...')
    ->maxTurns(15)
    ->denyTools(['delete_*'])
    ->budget(0.50)
    ->forTenant($user->tenant_id)
    ->run('ABC şirkətinin maliyyə göstəricilərini tap');
```

---

## 8. Subagent spawn — child process vs HTTP

### 8.1 Queue job pattern (sadə, production-safe)

```php
<?php
// app/AI/Subagents/SubagentJob.php

namespace App\AI\Subagents;

use App\AI\ClaudeAgent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class SubagentJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public $timeout = 300;
    public $tries = 2;

    public function __construct(
        public string $subagentName,
        public string $prompt,
        public string $resultKey,
    ) {}

    public function handle(ClaudeAgent $agent): void
    {
        $cfg = config("ai.subagents.{$this->subagentName}");
        $result = $agent
            ->model($cfg['model'])
            ->system($cfg['system'])
            ->allowTools($cfg['tools'])
            ->maxTurns($cfg['max_turns'] ?? 8)
            ->budget($cfg['budget_usd'] ?? 0.25)
            ->run($this->prompt);

        cache()->put("subagent:{$this->resultKey}", $result, now()->addMinutes(30));
    }
}
```

Əsas agent-in içində:

```php
// Subagent "tool"-u
class SpawnSubagentTool implements Tool
{
    public function name(): string { return 'spawn_subagent'; }
    public function description(): string {
        return 'Alt-agent işə sal. name: code_reviewer | security_reviewer | research_assistant. '
             . 'Nəticə async qaytarılır — wait_for_subagent ilə gözlə.';
    }
    public function inputSchema(): array { /* ... */ }

    public function execute(array $input): array
    {
        $key = (string) Str::ulid();
        SubagentJob::dispatch($input['name'], $input['prompt'], $key);
        return ['subagent_key' => $key, 'status' => 'spawned'];
    }
}
```

### 8.2 HTTP pattern (separate service)

Agent öz Node.js service-də işləyir, Laravel HTTP ilə çağırır. 54-cü faylda detallı.

---

## 9. Research Agent — tam nümunə

Məqsəd: istifadəçi suala cavab üçün həm daxili docs (Markdown fayllar), həm DB (tickets, customers), həm xarici API (web search) istifadə edir.

### 9.1 Tool-lar

```php
<?php
// app/AI/Tools/Research/SearchDocsTool.php

namespace App\AI\Tools\Research;

use App\AI\Tools\Contracts\Tool;
use App\Services\VectorStore;

class SearchDocsTool implements Tool
{
    public function __construct(private VectorStore $store) {}

    public function name(): string { return 'search_internal_docs'; }

    public function description(): string
    {
        return 'Daxili şirkət dokumentlərində semantik axtarış. '
             . 'SSS, onboarding, məhsul təsvirləri burada. '
             . 'Birinci yoxla — xarici axtarışdan ucuzdur.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'Axtarış mətni (təbii dildə)',
                ],
                'limit' => [
                    'type' => 'integer', 'minimum' => 1, 'maximum' => 10, 'default' => 5,
                ],
            ],
            'required' => ['query'],
        ];
    }

    public function execute(array $input): array
    {
        $hits = $this->store->search(
            $input['query'],
            limit: $input['limit'] ?? 5,
            filter: ['namespace' => 'docs'],
        );
        return ['hits' => array_map(fn($h) => [
            'doc_path' => $h->metadata['path'],
            'score' => round($h->score, 3),
            'excerpt' => \Str::limit($h->text, 400),
        ], $hits)];
    }
}
```

```php
// app/AI/Tools/Research/QueryDatabaseTool.php

namespace App\AI\Tools\Research;

use App\AI\Tools\Contracts\Tool;
use App\Models\Ticket;
use App\Models\Customer;

class QueryDatabaseTool implements Tool
{
    public function name(): string { return 'query_database'; }

    public function description(): string
    {
        return 'DB sorğuları: tickets və customers. '
             . 'entity-ə görə filter seç. Raw SQL YOX — strukturlu filter.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'entity' => ['type' => 'string', 'enum' => ['tickets', 'customers']],
                'filters' => [
                    'type' => 'object',
                    'description' => 'key=value filter (status, customer_id, email)',
                ],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 10],
            ],
            'required' => ['entity'],
        ];
    }

    public function execute(array $input): array
    {
        return match ($input['entity']) {
            'tickets' => $this->queryTickets($input['filters'] ?? [], $input['limit'] ?? 10),
            'customers' => $this->queryCustomers($input['filters'] ?? [], $input['limit'] ?? 10),
        };
    }

    private function queryTickets(array $f, int $limit): array
    {
        $q = Ticket::query();
        if (isset($f['status']))      $q->where('status', $f['status']);
        if (isset($f['customer_id'])) $q->where('customer_id', $f['customer_id']);
        if (isset($f['category']))    $q->where('category', $f['category']);
        return ['rows' => $q->limit($limit)->get([
            'id', 'subject', 'status', 'category', 'priority', 'created_at',
        ])->toArray()];
    }

    private function queryCustomers(array $f, int $limit): array
    {
        $q = Customer::query();
        if (isset($f['email'])) $q->where('email', $f['email']);
        if (isset($f['plan']))  $q->where('plan', $f['plan']);
        return ['rows' => $q->limit($limit)->get([
            'id', 'email', 'plan', 'created_at',
        ])->toArray()];
    }
}
```

```php
// app/AI/Tools/Research/WebSearchTool.php

namespace App\AI\Tools\Research;

use App\AI\Tools\Contracts\Tool;
use Illuminate\Support\Facades\Http;

class WebSearchTool implements Tool
{
    public function name(): string { return 'web_search'; }

    public function description(): string
    {
        return 'Xarici web axtarış (Brave API). Yalnız daxili docs kömək etmədikdə istifadə et. '
             . 'Rate-limited və bahalıdır.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string', 'minLength' => 3],
                'count' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 10, 'default' => 5],
            ],
            'required' => ['query'],
        ];
    }

    public function execute(array $input): array
    {
        $resp = Http::withHeaders([
            'X-Subscription-Token' => config('services.brave.key'),
        ])->get('https://api.search.brave.com/res/v1/web/search', [
            'q' => $input['query'],
            'count' => $input['count'] ?? 5,
        ]);

        if ($resp->failed()) {
            return ['error' => 'Web search uğursuz — başqa yol cəhd et'];
        }

        return ['results' => collect($resp['web']['results'] ?? [])->map(fn($r) => [
            'title' => $r['title'],
            'url' => $r['url'],
            'snippet' => $r['description'] ?? '',
        ])->all()];
    }
}
```

### 9.2 Service Provider ilə qeydiyyat

```php
<?php
// app/Providers/AIServiceProvider.php

namespace App\Providers;

use App\AI\ToolRegistry;
use App\AI\Tools\Research\{SearchDocsTool, QueryDatabaseTool, WebSearchTool};
use Illuminate\Support\ServiceProvider;

class AIServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ToolRegistry::class, function ($app) {
            return (new ToolRegistry())
                ->register($app->make(SearchDocsTool::class))
                ->register($app->make(QueryDatabaseTool::class))
                ->register($app->make(WebSearchTool::class));
        });
    }
}
```

### 9.3 Controller

```php
<?php
// app/Http/Controllers/ResearchController.php

namespace App\Http\Controllers;

use App\AI\ClaudeAgent;
use Illuminate\Http\Request;

class ResearchController extends Controller
{
    public function ask(Request $request, ClaudeAgent $agent)
    {
        $validated = $request->validate([
            'question' => 'required|string|min:10|max:500',
        ]);

        $result = $agent
            ->model('claude-sonnet-4-5')
            ->system(<<<SYS
            Sən research assistant-sən. İstifadəçi sualı cavablandırmaq üçün:
            1. Əvvəl search_internal_docs-dan yoxla (ucuz və dəqiq).
            2. Konkret data lazımdırsa query_database istifadə et.
            3. Yalnız daxili mənbə kömək etmədikdə web_search-ə keç.
            Heç vaxt məlumat uydurma. Mənbələri citation kimi göstər.
            Cavabı strukturlu ver: TL;DR + Details + Sources.
            SYS)
            ->maxTurns(10)
            ->budget(0.30)
            ->forTenant($request->user()->tenant_id)
            ->run($validated['question']);

        return response()->json([
            'answer' => $result['final_text'],
            'cost_usd' => round($result['usage']['cost'], 4),
            'turns' => $result['turns'],
        ]);
    }
}
```

---

## 10. Test, observability və production qayğıları

### 10.1 Test (Pest)

```php
<?php
// tests/Feature/ResearchAgentTest.php

use App\AI\ClaudeAgent;
use App\AI\AnthropicClient;

it('research agent daxili docs-dan cavab qaytarır', function () {
    // Fake Anthropic response
    $this->mock(AnthropicClient::class, function ($m) {
        $m->shouldReceive('messages')->twice()
          ->andReturn(
              // 1-ci turn: tool call
              [
                  'content' => [[
                      'type' => 'tool_use', 'id' => 'tu_1',
                      'name' => 'search_internal_docs',
                      'input' => ['query' => 'onboarding'],
                  ]],
                  'stop_reason' => 'tool_use',
                  'usage' => ['input_tokens' => 100, 'output_tokens' => 20],
              ],
              // 2-ci turn: final
              [
                  'content' => [['type' => 'text', 'text' => 'Onboarding sənədi: ...']],
                  'stop_reason' => 'end_turn',
                  'usage' => ['input_tokens' => 150, 'output_tokens' => 50],
              ],
          );
    });

    $result = app(ClaudeAgent::class)
        ->maxTurns(5)
        ->run('Onboarding necədir?');

    expect($result['final_text'])->toContain('Onboarding');
    expect($result['turns'])->toBe(2);
});
```

### 10.2 Observability

Laravel event listener-lər hər tool çağırışını `events` cədvəlinə yazır. Grafana dashboard:

- `agent_turns_total{agent, tenant}`
- `agent_tool_calls_total{tool, status}`
- `agent_cost_usd_sum{tenant, model}`
- `agent_duration_ms_histogram`

### 10.3 Production qayğıları

| Mövzu | Tövsiyə |
|-------|---------|
| Max turns | Həmişə qoy. Default: 12. |
| Budget | Tenant başına gündəlik limit. Redis counter. |
| Timeout | Hər tool execute 30s, bütün agent 5 dəq. |
| Retry | Yalnız API-nin 5xx/timeout-ları retry. Tool xətaları retry etmə. |
| PII | Request və log redaction (53-cü faylda). |
| Multi-provider | Anthropic outage olanda fallback. (54-cü faylda.) |
| Streaming | SSE ilə browser-ə real-time göndər. |
| Session | DB-də JSON blob, `session_id` ilə resume. |

---

## Xülasə

- Claude Agent SDK TS/Python üçün rəsmi — PHP üçün yox.
- SDK-nın verdikləri: query(), tools, subagents, hooks, permissions, cost tracking.
- Laravel-da eyni pattern qurmaq mümkündür: `ToolRegistry` + `AgentRunner` + Laravel event-lər.
- Subagent-lər queue job-larla spawn et (və ya ayrı HTTP service).
- Production-da **budget enforcement**, **max turns** və **observability** olmadan agent yazma.
- PHP codebase-ində senior dev üçün bu fasad bütün agent work-ları üçün vahid giriş nöqtəsi olur.
