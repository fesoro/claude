# Claude Agent SDK — Production Agent Qurmaq (Lead)

> Hədəf auditoriyası: Production-da AI agent-lər qurmaq istəyən senior developerlər. Bu sənəd Claude Agent SDK-nın əsas anlayışlarını izah edir və PHP rəsmi SDK olmadığından, Laravel-də eyni pattern-ın necə qurulacağını göstərir.

---

## Mündəricat

1. [Agent SDK Nədir](#sdk-nədir)
2. [Raw API vs SDK — Nə Vaxt Hansını](#raw-vs-sdk)
3. [Quraşdırma və Başlanğıc](#quraşdırma)
4. [Əsas Anlayışlar](#əsas-anlayışlar)
5. [System Prompt](#system-prompt)
6. [Tools və Tool Use](#tools)
7. [Subagents — Çoxlu Agent Koordinasiyası](#subagents)
8. [MCP Integration](#mcp-integration)
9. [Hooks — Davranışın Dəyişdirilməsi](#hooks)
10. [Permissions Model](#permissions)
11. [Cost Tracking](#cost-tracking)
12. [Full TypeScript Nümunə](#ts-nümunə)
13. [Laravel-də Eyni Pattern](#laravel-pattern)
14. [ClaudeAgent Servis Class-ı](#claude-agent-class)
15. [Production Considerations](#production)

---

## SDK Nədir

Claude Agent SDK, Anthropic-in agent-əsaslı tətbiqlər yaratmaq üçün rəsmi dəstəklədiyi paketdir. Node (TypeScript) və Python dillərində mövcuddur:

```
@anthropic-ai/claude-agent-sdk   (npm)
claude-agent-sdk                  (pypi)
```

### SDK Nə Verir

Raw Messages API ilə agent yaratmaq mümkündür, amma həmin pattern-ları dəfələrlə yenidən yazmaq lazım gəlir. SDK bunu abstraktlaşdırır:

```
  Raw API                    Claude Agent SDK
  ───────                    ────────────────
  Messages göndər            Agent loop (avtomatik)
  Tool definition-lar yaz    Auto-discover və register
  Tool use cavabla           Avtomatik icra
  Tool result qaytar         Avtomatik continue
  Context idarə et           Auto-compaction
  Xətaları tut               Built-in retry
  Cost izlə                  Built-in tracking
  Permission soruş           Hook system
```

### Agent Loop

SDK-nın mərkəzindəki konsept — **agent loop**-dur:

```
   ┌──────────────┐
   │  User mesajı │
   └──────┬───────┘
          │
          ▼
   ┌──────────────┐
   │  Claude-ə    │
   │  göndər      │
   └──────┬───────┘
          │
          ▼
   ┌──────────────┐
   │  Cavab?      │
   └──────┬───────┘
          │
     ┌────┴────┐
     │         │
     ▼         ▼
  [text]   [tool_use]
     │         │
     │         ▼
     │   ┌─────────┐
     │   │ Tool    │
     │   │ icra et │
     │   └────┬────┘
     │        │
     │        ▼
     │   ┌─────────┐
     │   │ Result- │
     │   │ u əlavə │
     │   │ et      │
     │   └────┬────┘
     │        │
     │        └─────> Claude-ə təkrar
     ▼
  [final]
     │
     ▼
   User
```

Bu loop, Claude tool çağırdıqca davam edir, `end_turn` stop_reason gələnə qədər.

---

## Raw vs SDK

### Raw API Seçimi

Raw Messages API seçimi doğrudur əgər:
- **Sadə single-turn istifadə** — Q&A, sinifləndirmə
- **Custom protocol** var (məs., WebSocket-based agent)
- **Yüksək controlled environment** — hər token-ə görə sərbəst olmaq
- **PHP**, Go, Java — SDK yoxdur, raw API məcburidir
- **Minimal dependencies** — SDK 50+ dependency gətirə bilər

### SDK Seçimi

SDK seçimi doğrudur əgər:
- **Node.js və ya Python** istifadə edirsən
- **Çoxaddımlı agent-lər** qurursan
- **Tool use dominantdır** — əsas iş tool orkestrasiyasıdır
- **MCP server-ləri** inteqrasiya etmək istəyirsən
- **Production-ready observability** lazımdır
- **Subagent pattern** istifadə etmək istəyirsən

---

## Quraşdırma

### Node.js

```bash
npm install @anthropic-ai/claude-agent-sdk
```

### Python

```bash
pip install claude-agent-sdk
```

### Konfiqurasiya

```bash
# .env
ANTHROPIC_API_KEY=sk-ant-...
```

### Basic Setup

```typescript
// TypeScript
import { ClaudeAgent } from '@anthropic-ai/claude-agent-sdk';

const agent = new ClaudeAgent({
  model: 'claude-sonnet-4-5',
  systemPrompt: 'You are a helpful DevOps assistant.',
});

const result = await agent.run('Deploy the staging environment');
console.log(result.finalMessage);
```

```python
# Python
from claude_agent_sdk import ClaudeAgent

agent = ClaudeAgent(
    model="claude-sonnet-4-5",
    system_prompt="You are a helpful DevOps assistant.",
)

result = agent.run("Deploy the staging environment")
print(result.final_message)
```

---

## Əsas Anlayışlar

SDK bu konsept ətrafında qurulub:

```
Agent
├── System Prompt (identity & instructions)
├── Tools[]
│   ├── name
│   ├── description
│   ├── input_schema
│   └── handler function
├── Subagents[] (iç-içə agent-lər)
├── MCP Servers[] (xarici tool provider-lər)
├── Hooks
│   ├── onBeforeToolUse
│   ├── onAfterToolUse
│   ├── onError
│   └── onComplete
├── Permissions
│   ├── allow: string[]
│   ├── deny: string[]
│   └── askUser: boolean
├── Context
│   ├── messages[]
│   ├── totalTokens
│   └── cost
└── run(userInput) → AgentResult
```

### Agent Result

```typescript
interface AgentResult {
  finalMessage: string;
  messages: Message[];
  toolCalls: ToolCall[];
  totalInputTokens: number;
  totalOutputTokens: number;
  cachedTokens: number;
  totalCostUsd: number;
  elapsedMs: number;
  iterationCount: number;
  stopReason: 'end_turn' | 'max_iterations' | 'permission_denied' | 'error';
}
```

---

## System Prompt

System prompt — agent-in "kimliyi" və "təlimatları"dır:

```typescript
const agent = new ClaudeAgent({
  model: 'claude-sonnet-4-5',
  systemPrompt: `
    You are a senior Laravel developer assistant.
    
    Responsibilities:
    - Review pull requests for security issues
    - Suggest performance improvements
    - Enforce PSR-12 code style
    
    Constraints:
    - Never execute commands on production
    - Always require human approval for destructive actions
    - Cite the specific line numbers in your reviews
    
    Tone: direct, technical, no fluff.
  `,
});
```

### System Prompt Best Practices

1. **Role-u açıq göstər** — "You are a X"
2. **Constraints-i siyahıla** — "Never do Y"
3. **Expected output format-ı təsvir et** — structured output
4. **Edge case-ləri qeyd et** — "If unclear, ask clarifying question"
5. **Uzunluğu optimallaşdır** — cache-lənmək üçün stabilliyi vacibdir

---

## Tools

Tool-lar agent-in xarici dünya ilə qarşılıqlı əlaqə qurmasına imkan verir:

```typescript
import { tool } from '@anthropic-ai/claude-agent-sdk';

const readFileTool = tool({
  name: 'read_file',
  description: 'Read contents of a file from the repository',
  inputSchema: {
    type: 'object',
    properties: {
      path: { type: 'string', description: 'Relative path to file' },
    },
    required: ['path'],
  },
  handler: async ({ path }) => {
    const content = await fs.readFile(path, 'utf-8');
    return { content };
  },
});

const agent = new ClaudeAgent({
  model: 'claude-sonnet-4-5',
  systemPrompt: '...',
  tools: [readFileTool, writeFileTool, runTestsTool],
});
```

### Tool Design Prinsipləri

```
1. Tək məsuliyyət: bir tool bir iş görsün
2. Açıq description: Claude nə vaxt istifadə etməli olduğunu bilməlidir
3. Güclü schema: type və enum ilə məhdudlaşdır
4. Idempotency: eyni input → eyni output
5. Error messages: qaytarılan error Claude üçün faydalı olsun
```

### Tool Example Collection

```typescript
const tools = [
  tool({
    name: 'search_code',
    description: 'Search codebase with a regex pattern',
    inputSchema: {
      type: 'object',
      properties: {
        pattern: { type: 'string' },
        file_glob: { type: 'string', default: '**/*' },
      },
      required: ['pattern'],
    },
    handler: async ({ pattern, file_glob }) => {
      // ripgrep wrapper
      return await searchWithRipgrep(pattern, file_glob);
    },
  }),
  
  tool({
    name: 'run_tests',
    description: 'Run PHPUnit tests and return results',
    inputSchema: {
      type: 'object',
      properties: {
        filter: { type: 'string', description: 'Test filter pattern' },
      },
    },
    handler: async ({ filter }) => {
      return await exec(`./vendor/bin/phpunit --filter "${filter}"`);
    },
  }),
];
```

---

## Subagents

Subagent pattern mürəkkəb tapşırıqları kiçik specializədə agent-lərə bölür:

```
   Main Agent (Orchestrator)
       │
       ├── "This is a code review task"
       ├── delegate to ReviewerAgent
       │
       └── ReviewerAgent
              │
              ├── Read files
              ├── Run static analyzer
              ├── Check security
              └── Return structured review
```

### Subagent Kodu

```typescript
const securityReviewer = new ClaudeAgent({
  name: 'security_reviewer',
  model: 'claude-opus-4-5',
  systemPrompt: 'You are a security expert. Find vulnerabilities.',
  tools: [readFileTool, runSemgrepTool],
});

const performanceReviewer = new ClaudeAgent({
  name: 'performance_reviewer',
  model: 'claude-sonnet-4-5',
  systemPrompt: 'You are a performance engineer. Find bottlenecks.',
  tools: [readFileTool, profilerTool],
});

const mainAgent = new ClaudeAgent({
  model: 'claude-sonnet-4-5',
  systemPrompt: 'Orchestrate code review by delegating to specialists.',
  subagents: [securityReviewer, performanceReviewer],
});

const review = await mainAgent.run('Review PR #1234');
```

### Nə Vaxt Subagent Lazımdır

```
Tək agent kifayətdir əgər:
  - Tapşırıq <10 addımdır
  - Bütün addımlar eyni context-i tələb edir
  - Kontekst 100k token-dən azdır

Subagent lazımdır əgər:
  - Tapşırıq paralel addımlara parçalana bilir
  - Hər tərəfin fərqli model-ə ehtiyacı var (Opus security, Haiku extraction)
  - Kontekst böyüyür — subagent tamamlandıqda context qaytarır
  - Specializasiya → keyfiyyət artırır
```

---

## MCP Integration

Model Context Protocol (MCP) — tool-ları agent-dən ayrı server-lər kimi təqdim edir. SDK MCP server-ləri avtomatik discover edir:

```typescript
const agent = new ClaudeAgent({
  model: 'claude-sonnet-4-5',
  mcpServers: [
    { name: 'github', command: 'npx', args: ['-y', '@github/mcp-server'] },
    { name: 'postgres', command: 'mcp-postgres', env: { DB_URL: '...' } },
  ],
});
```

### Niyə MCP

```
SDK tools:
  - Bir process-də yaşayır
  - Tətbiq dəyişikliyi = deploy lazımdır
  - Language-specific

MCP servers:
  - Ayrı process-lər
  - Agent-dan müstəqil yenilənir
  - Language-agnostic (Claude Desktop, IDE-lər də istifadə edir)
  - Reusable across agents
```

---

## Hooks

Hook-lar agent-in həyat dövriyyəsinə daxil olmağa imkan verir:

```typescript
const agent = new ClaudeAgent({
  model: 'claude-sonnet-4-5',
  tools: [...],
  hooks: {
    onBeforeToolUse: async (toolName, input, context) => {
      logger.info(`Calling tool: ${toolName}`, input);
      
      // Rate limiting
      if (await rateLimiter.isLimited(context.userId)) {
        return { block: true, reason: 'rate_limit_exceeded' };
      }
      
      return { continue: true };
    },
    
    onAfterToolUse: async (toolName, input, output, context) => {
      await auditLog.write({ tool: toolName, input, output });
    },
    
    onError: async (error, context) => {
      sentry.captureException(error);
    },
    
    onComplete: async (result, context) => {
      await saveConversation(context.userId, result);
      await trackCost(context.userId, result.totalCostUsd);
    },
  },
});
```

### Hook İstifadə Nümunələri

1. **Audit Logging** — hər tool çağırışını yazmaq
2. **Rate Limiting** — istifadəçi başına məhdudiyyət
3. **Permission Check** — dinamik icazə sistemi
4. **Cost Guardrails** — müəyyən sümmədən sonra dayandırma
5. **PII Redaction** — input/output-da həssas data filter
6. **Metric Emission** — Prometheus/Datadog-a

---

## Permissions

Permission model agent-in nə edə biləcəyinə nəzarət edir:

```typescript
const agent = new ClaudeAgent({
  model: 'claude-sonnet-4-5',
  tools: [readFileTool, writeFileTool, runShellTool],
  permissions: {
    allow: [
      'read_file:./src/**',
      'read_file:./tests/**',
    ],
    deny: [
      'read_file:./secrets/**',
      'run_shell:rm *',
      'run_shell:git push *',
    ],
    askUser: {
      'write_file:**': true,
      'run_shell:**': true,
    },
  },
});
```

### Permission Matching

```
Pattern syntax:
  tool_name:glob_pattern
  
  read_file:./src/**     → bütün src faylları
  run_shell:git status   → yalnız `git status`
  run_shell:git *        → bütün git əmrləri
  write_file:**          → hər hansı write
```

### Interactive Permission Flow

```
Claude wants to use tool: write_file
Arguments:
  path: "./config/database.php"
  content: "<?php return [...];"

Options:
  (a) Allow once
  (A) Allow always for this pattern
  (d) Deny once
  (D) Deny always
  (s) Show diff first

> _
```

---

## Cost Tracking

SDK avtomatik olaraq istifadəni izləyir:

```typescript
const result = await agent.run('Refactor this function');

console.log({
  input_tokens: result.totalInputTokens,
  output_tokens: result.totalOutputTokens,
  cached_tokens: result.cachedTokens,
  cost_usd: result.totalCostUsd,
  iterations: result.iterationCount,
});
```

### Per-Iteration Breakdown

```typescript
agent.on('iteration', (data) => {
  console.log(`Iteration ${data.index}:`, {
    model: data.model,
    tool_calls: data.toolCalls.length,
    tokens: data.tokens,
    cost: data.cost,
  });
});
```

### Budget Limits

```typescript
const agent = new ClaudeAgent({
  model: 'claude-sonnet-4-5',
  maxCostUsd: 1.00,  // agent $1-dan artıq xərcləməz
  maxIterations: 20,
});

try {
  await agent.run(task);
} catch (err) {
  if (err.code === 'BUDGET_EXCEEDED') {
    // handle
  }
}
```

---

## TS Nümunə

Tam işlək production agent misalı:

```typescript
import { ClaudeAgent, tool } from '@anthropic-ai/claude-agent-sdk';
import { execSync } from 'child_process';
import * as fs from 'fs/promises';

// Tools
const readFile = tool({
  name: 'read_file',
  description: 'Read a file from the codebase',
  inputSchema: {
    type: 'object',
    properties: { path: { type: 'string' } },
    required: ['path'],
  },
  handler: async ({ path }) => ({
    content: await fs.readFile(path, 'utf-8'),
  }),
});

const searchCode = tool({
  name: 'search_code',
  description: 'Grep codebase',
  inputSchema: {
    type: 'object',
    properties: { pattern: { type: 'string' } },
    required: ['pattern'],
  },
  handler: async ({ pattern }) => {
    const result = execSync(`rg "${pattern}" --json`).toString();
    return { matches: result.split('\n').filter(Boolean) };
  },
});

const runTests = tool({
  name: 'run_tests',
  description: 'Run PHPUnit',
  inputSchema: {
    type: 'object',
    properties: { filter: { type: 'string' } },
  },
  handler: async ({ filter }) => {
    try {
      const cmd = filter 
        ? `./vendor/bin/phpunit --filter "${filter}"`
        : './vendor/bin/phpunit';
      const output = execSync(cmd).toString();
      return { success: true, output };
    } catch (err) {
      return { success: false, output: err.stdout?.toString() };
    }
  },
});

// Agent
const agent = new ClaudeAgent({
  model: 'claude-sonnet-4-5',
  systemPrompt: `
    You are a Laravel debugging assistant.
    When a test fails:
    1. Read the failing test file
    2. Read the implementation
    3. Identify the bug
    4. Suggest a fix (don't apply yet)
  `,
  tools: [readFile, searchCode, runTests],
  maxIterations: 15,
  maxCostUsd: 2.0,
  hooks: {
    onBeforeToolUse: async (name, input) => {
      console.log(`[tool] ${name}`, input);
    },
    onComplete: async (result) => {
      console.log(`Done. Cost: $${result.totalCostUsd.toFixed(4)}`);
    },
  },
});

// Run
const result = await agent.run(
  'Run all tests, then fix the first failing one'
);

console.log(result.finalMessage);
```

---

## Laravel Pattern

Laravel-də rəsmi SDK yoxdur. Amma eyni arxitekturanı öz servis-class-ımızda qura bilərik.

### Arxitektur

```
┌────────────────────────────────────┐
│  Controller / Command / Job         │
└────────────┬───────────────────────┘
             │
             ▼
┌────────────────────────────────────┐
│  ClaudeAgent (orchestrator)         │
│  ├── systemPrompt                   │
│  ├── tools[]                        │
│  ├── hooks                          │
│  └── run(input)                     │
└────────────┬───────────────────────┘
             │
             ▼
┌────────────────────────────────────┐
│  ClaudeClient (HTTP)                │
│  └── messages()->create()            │
└────────────┬───────────────────────┘
             │
             ▼
      Anthropic API
```

---

## Claude Agent Class

### Tool Interface

```php
<?php

namespace App\Services\AI\Agent;

interface AgentTool
{
    public function name(): string;
    public function description(): string;
    public function inputSchema(): array;
    public function handle(array $input): array;
}
```

### Tool Nümunələri

```php
<?php

namespace App\Services\AI\Agent\Tools;

use App\Services\AI\Agent\AgentTool;

class ReadFileTool implements AgentTool
{
    public function name(): string
    {
        return 'read_file';
    }

    public function description(): string
    {
        return 'Read a file from the project root';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'path' => ['type' => 'string', 'description' => 'Relative path'],
            ],
            'required' => ['path'],
        ];
    }

    public function handle(array $input): array
    {
        $path = base_path($input['path']);
        
        if (!file_exists($path) || !is_readable($path)) {
            return ['error' => "File not found or unreadable: {$input['path']}"];
        }

        return [
            'content' => file_get_contents($path),
            'size' => filesize($path),
        ];
    }
}
```

```php
<?php

namespace App\Services\AI\Agent\Tools;

use App\Services\AI\Agent\AgentTool;
use Symfony\Component\Process\Process;

class RunPhpunitTool implements AgentTool
{
    public function name(): string
    {
        return 'run_phpunit';
    }

    public function description(): string
    {
        return 'Run PHPUnit tests, optionally filtered';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'filter' => ['type' => 'string'],
            ],
        ];
    }

    public function handle(array $input): array
    {
        $filter = $input['filter'] ?? null;
        $cmd = ['./vendor/bin/phpunit'];
        
        if ($filter) {
            $cmd[] = '--filter';
            $cmd[] = $filter;
        }

        $process = new Process($cmd, base_path());
        $process->setTimeout(120);
        $process->run();

        return [
            'success' => $process->isSuccessful(),
            'output' => $process->getOutput(),
            'exit_code' => $process->getExitCode(),
        ];
    }
}
```

### ClaudeAgent Servis

```php
<?php

namespace App\Services\AI\Agent;

use App\Services\AI\ClaudeClient;
use App\Services\AI\PricingCalculator;
use Illuminate\Support\Facades\Log;

class ClaudeAgent
{
    private array $tools = [];
    private array $messages = [];
    private array $hooks = [];
    private int $totalInputTokens = 0;
    private int $totalOutputTokens = 0;
    private float $totalCost = 0.0;
    private int $iterations = 0;

    public function __construct(
        private readonly ClaudeClient $client,
        private readonly PricingCalculator $pricing,
        private readonly string $model = 'claude-sonnet-4-5',
        private readonly string $systemPrompt = '',
        private readonly int $maxIterations = 20,
        private readonly float $maxCostUsd = 5.0,
    ) {}

    public function addTool(AgentTool $tool): self
    {
        $this->tools[$tool->name()] = $tool;
        return $this;
    }

    public function onBeforeToolUse(callable $hook): self
    {
        $this->hooks['before_tool'] = $hook;
        return $this;
    }

    public function onAfterToolUse(callable $hook): self
    {
        $this->hooks['after_tool'] = $hook;
        return $this;
    }

    public function run(string $userInput): AgentResult
    {
        $this->messages = [
            ['role' => 'user', 'content' => $userInput],
        ];

        while ($this->iterations < $this->maxIterations) {
            if ($this->totalCost >= $this->maxCostUsd) {
                return $this->buildResult('budget_exceeded');
            }

            $this->iterations++;
            $response = $this->callClaude();
            $this->trackUsage($response);

            $stopReason = $response['stop_reason'];

            if ($stopReason === 'end_turn') {
                $this->messages[] = ['role' => 'assistant', 'content' => $response['content']];
                return $this->buildResult('end_turn');
            }

            if ($stopReason === 'tool_use') {
                $this->messages[] = ['role' => 'assistant', 'content' => $response['content']];
                $toolResults = $this->executeTools($response['content']);
                $this->messages[] = ['role' => 'user', 'content' => $toolResults];
                continue;
            }

            return $this->buildResult('stop_' . $stopReason);
        }

        return $this->buildResult('max_iterations');
    }

    private function callClaude(): array
    {
        return $this->client->messages()->create([
            'model' => $this->model,
            'max_tokens' => 4096,
            'system' => $this->systemPrompt,
            'tools' => $this->toolDefinitions(),
            'messages' => $this->messages,
        ]);
    }

    private function toolDefinitions(): array
    {
        return array_values(array_map(
            fn (AgentTool $t) => [
                'name' => $t->name(),
                'description' => $t->description(),
                'input_schema' => $t->inputSchema(),
            ],
            $this->tools,
        ));
    }

    private function executeTools(array $content): array
    {
        $results = [];

        foreach ($content as $block) {
            if ($block['type'] !== 'tool_use') {
                continue;
            }

            $toolName = $block['name'];
            $input = $block['input'];
            $useId = $block['id'];

            if (isset($this->hooks['before_tool'])) {
                $decision = ($this->hooks['before_tool'])($toolName, $input);
                if (($decision['block'] ?? false) === true) {
                    $results[] = [
                        'type' => 'tool_result',
                        'tool_use_id' => $useId,
                        'content' => 'Blocked: ' . ($decision['reason'] ?? 'policy'),
                        'is_error' => true,
                    ];
                    continue;
                }
            }

            try {
                $output = $this->tools[$toolName]->handle($input);
                
                if (isset($this->hooks['after_tool'])) {
                    ($this->hooks['after_tool'])($toolName, $input, $output);
                }

                $results[] = [
                    'type' => 'tool_result',
                    'tool_use_id' => $useId,
                    'content' => json_encode($output),
                ];
            } catch (\Throwable $e) {
                Log::error("Tool {$toolName} failed", ['error' => $e->getMessage()]);
                $results[] = [
                    'type' => 'tool_result',
                    'tool_use_id' => $useId,
                    'content' => 'Tool error: ' . $e->getMessage(),
                    'is_error' => true,
                ];
            }
        }

        return $results;
    }

    private function trackUsage(array $response): void
    {
        $usage = $response['usage'];
        $this->totalInputTokens += $usage['input_tokens'];
        $this->totalOutputTokens += $usage['output_tokens'];

        $this->totalCost += $this->pricing->calculate(
            model: $this->model,
            inputTokens: $usage['input_tokens'],
            outputTokens: $usage['output_tokens'],
            cacheReadTokens: $usage['cache_read_input_tokens'] ?? 0,
            cacheWriteTokens: $usage['cache_creation_input_tokens'] ?? 0,
        );
    }

    private function buildResult(string $stopReason): AgentResult
    {
        return new AgentResult(
            messages: $this->messages,
            finalMessage: $this->extractFinalText(),
            iterations: $this->iterations,
            inputTokens: $this->totalInputTokens,
            outputTokens: $this->totalOutputTokens,
            costUsd: $this->totalCost,
            stopReason: $stopReason,
        );
    }

    private function extractFinalText(): string
    {
        $last = end($this->messages);
        if ($last['role'] !== 'assistant') return '';

        $text = '';
        foreach ($last['content'] as $block) {
            if ($block['type'] === 'text') {
                $text .= $block['text'];
            }
        }

        return $text;
    }
}
```

### AgentResult DTO

```php
<?php

namespace App\Services\AI\Agent;

readonly class AgentResult
{
    public function __construct(
        public array $messages,
        public string $finalMessage,
        public int $iterations,
        public int $inputTokens,
        public int $outputTokens,
        public float $costUsd,
        public string $stopReason,
    ) {}
}
```

### İstifadə Nümunəsi

```php
use App\Services\AI\Agent\ClaudeAgent;
use App\Services\AI\Agent\Tools\ReadFileTool;
use App\Services\AI\Agent\Tools\SearchCodeTool;
use App\Services\AI\Agent\Tools\RunPhpunitTool;

$agent = app(ClaudeAgent::class, [
    'model' => 'claude-sonnet-4-5',
    'systemPrompt' => <<<'PROMPT'
        You are a Laravel debugging assistant.
        When asked to fix a failing test:
        1. Run tests to identify the failure
        2. Read the test file
        3. Read the implementation under test
        4. Identify the bug
        5. Propose a fix as a diff
        PROMPT,
    'maxIterations' => 15,
    'maxCostUsd' => 1.0,
]);

$agent->addTool(app(ReadFileTool::class));
$agent->addTool(app(SearchCodeTool::class));
$agent->addTool(app(RunPhpunitTool::class));

$agent->onBeforeToolUse(function ($name, $input) {
    Log::info("Agent calling: {$name}", $input);
    
    if ($name === 'run_phpunit' && str_contains($input['filter'] ?? '', 'Production')) {
        return ['block' => true, 'reason' => 'production tests not allowed'];
    }
    
    return ['continue' => true];
});

$result = $agent->run('Fix the failing test in OrderServiceTest');

echo $result->finalMessage;
echo "Cost: $" . number_format($result->costUsd, 4);
echo "Iterations: " . $result->iterations;
```

---

## Production

### 1. Queue-based Execution

Agent uzun işləyə bilər (dəqiqələrlə). HTTP request-in arxasında saxlama, queue istifadə et:

```php
namespace App\Jobs;

use App\Services\AI\Agent\ClaudeAgent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunAgentJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;
    public int $tries = 2;

    public function __construct(
        public string $userId,
        public string $taskId,
        public string $prompt,
    ) {}

    public function handle(ClaudeAgent $agent): void
    {
        // tools və hooks konfiqurasiya et
        $result = $agent->run($this->prompt);

        AgentRun::where('id', $this->taskId)->update([
            'status' => 'completed',
            'result' => $result->finalMessage,
            'cost_usd' => $result->costUsd,
            'completed_at' => now(),
        ]);
    }
}
```

### 2. Streaming Progress Updates

Agent işlədikcə frontend-ə progress göndər (Laravel Reverb/Pusher):

```php
$agent->onAfterToolUse(function ($name, $input, $output) use ($taskId) {
    broadcast(new AgentProgressEvent($taskId, [
        'tool' => $name,
        'status' => 'completed',
    ]));
});
```

### 3. Multi-tenant Isolation

Hər tenant üçün ayrı tool set və permission:

```php
class AgentFactory
{
    public function forTenant(Tenant $tenant): ClaudeAgent
    {
        $agent = app(ClaudeAgent::class, [
            'model' => $tenant->llm_tier,
            'maxCostUsd' => $tenant->llm_monthly_cap,
        ]);

        foreach ($tenant->enabled_tools as $toolClass) {
            $agent->addTool(app($toolClass));
        }

        return $agent;
    }
}
```

### 4. Observability

Hər agent run-ı db-də saxla:

```sql
CREATE TABLE agent_runs (
    id UUID PRIMARY KEY,
    user_id BIGINT,
    tenant_id VARCHAR(64),
    model VARCHAR(64),
    prompt TEXT,
    result TEXT,
    status VARCHAR(32),
    iterations INT,
    input_tokens INT,
    output_tokens INT,
    cost_usd NUMERIC(10,6),
    stop_reason VARCHAR(32),
    duration_ms INT,
    created_at TIMESTAMP,
    completed_at TIMESTAMP
);
```

### 5. Subagent Pattern Laravel-də

```php
class ReviewOrchestratorAgent extends ClaudeAgent
{
    public function run(string $prompt): AgentResult
    {
        // Security review (Opus)
        $securityAgent = app(SecurityReviewerAgent::class);
        $secResult = $securityAgent->run("Security review: {$prompt}");

        // Performance review (Sonnet)
        $perfAgent = app(PerformanceReviewerAgent::class);
        $perfResult = $perfAgent->run("Perf review: {$prompt}");

        // Aggregate (main agent)
        return parent::run(
            "Combine these reviews:\n\n" .
            "SECURITY:\n{$secResult->finalMessage}\n\n" .
            "PERFORMANCE:\n{$perfResult->finalMessage}"
        );
    }
}
```

### 6. Cost Guardrails

```php
$agent->onBeforeToolUse(function ($name, $input) use ($tenantId) {
    $guard = app(QuotaGuard::class);
    
    if (!$guard->canSpend($tenantId, expectedCost: 0.01)) {
        return ['block' => true, 'reason' => 'quota_exceeded'];
    }
    
    return ['continue' => true];
});
```

### 7. Test Strategy

Agent-ləri necə test edirik:

```php
// Mock ClaudeClient
$this->app->bind(ClaudeClient::class, function () {
    $mock = \Mockery::mock(ClaudeClient::class);
    $mock->shouldReceive('messages->create')
        ->andReturn([
            'content' => [['type' => 'tool_use', 'name' => 'read_file', ...]],
            'stop_reason' => 'tool_use',
            'usage' => ['input_tokens' => 100, 'output_tokens' => 50],
        ]);
    return $mock;
});

// Agent integration test
$agent = app(ClaudeAgent::class);
$agent->addTool(new FakeReadFileTool());

$result = $agent->run('Read composer.json');

$this->assertEquals('end_turn', $result->stopReason);
$this->assertGreaterThan(0, $result->iterations);
```

---

## Yekun

Claude Agent SDK, agent pattern-in "enterprise-ready" implementasiyasını verir. Əsas dəyəri:

1. **Agent loop avtomatizasiyası** — manual tool orkestrasiyasından qurtarır
2. **Subagent pattern** — mürəkkəb tapşırıqları specializədə agent-lərə bölür
3. **MCP inteqrasiyası** — externally managed tool-lara çıxış
4. **Hook-based extensibility** — audit, permission, cost
5. **Built-in cost tracking** — production üçün əvəzolunmaz

PHP developer kimi rəsmi SDK olmadığına görə bu pattern-ları özün tətbiq edirsən. `ClaudeAgent` servis-class-ı Laravel-də eyni dəyəri verir — yalnız daha çox boilerplate ilə. Uzunmüddətli baxanda bu kod bazasının hissəsi olur, update-lərdən asılı deyilsən, Laravel ekosisteminə natural inteqrasiya edir.

Agent-lər gələcəyin əsas LLM istifadə modelidir. Senior developer kimi həm SDK-nın nə verdiyini, həm də bu primitivləri PHP-də necə qurmağı başa düşmək — uzunmüddətli fayda verəcək.

---

## Praktik Tapşırıqlar

### Tapşırıq 1: PHP Agent Loop

`ClaudeAgent` sinifi yaz. `run(string $task): string` metodu: (1) LLM-ə tapşırığı göndər, (2) `stop_reason === 'tool_use'`-dan tool call-ları icra et, (3) tool result-larını messages-ə əlavə et, (4) loop davam et. 3 fərqli tool ilə (search, calculate, format) test et.

### Tapşırıq 2: Max Iteration Guard

Agent loop-una `$iteration` counter əlavə et. `MAX_ITERATIONS = 10`-dan keçdikdə loop-u dayandır, `['status' => 'max_iterations_reached', 'partial' => $lastResponse]` qaytar. 20 sorğu üzərindən ortalama iteration sayını ölç. Çox iteration tələb edən tapşırıqlar hansılardır?

### Tapşırıq 3: Agent Trace Logging

`agent_traces` cədvəlinə hər agent session-ını log et: `session_id`, `task`, `iterations`, `tool_calls` (JSON), `final_response`, `total_tokens`, `duration_ms`. Filament-də agent trace viewer qur. Ən bahalı agent session-larını müəyyənləşdir.

---

## Əlaqəli Mövzular

- `04-tool-use.md` — Tool use mexanizmi — agent loop-unun əsası
- `../05-agents/05-build-custom-agent-laravel.md` — PHP-də tam agent implementasiyası
- `../05-agents/06-claude-agent-sdk-deep.md` — Agent SDK dərin araşdırma
- `../05-agents/11-agent-evaluation-evals.md` — Agent-i eval ilə qiymətləndir
