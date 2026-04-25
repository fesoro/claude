# Laravel-da İstehsalata Hazır AI Agent Qurmaq (Senior)

## Memarlığa Ümumi Baxış

İstehsalat agenti while dövrü içərisindəki LLM çağırışından daha çoxdur. Bunlara ehtiyac var:
- Validasiya və xəta idarəsi olan alət qeydiyyatı
- Qalıcılıq ilə söhbət yaddaşı
- Təhlükəsizlik limitləri olan agentik dövr
- Frontend-ə canlı axın
- Davam etdirmə/təkrar oynatma üçün vəziyyət qalıcılığı
- Müşahidə imkanı (hər qərarı qeydə almaq)

**Data Analiz Agenti** quracağıq — verilənlər bazanıza sorğu verə bilən, anlayışlar çıxara bilən və hesabatlar yaza bilən — tam muxtariyyatla.

```
┌──────────────────────────────────────────────────────────────┐
│                    DATA ANALİZ AGENTİ                        │
│                                                              │
│  İstifadəçi Sorğusu                                          │
│       │                                                      │
│       ▼                                                      │
│  ┌─────────────┐   ┌──────────────┐   ┌────────────────┐   │
│  │   Agent     │   │    Alət      │   │   Vəziyyət     │   │
│  │   Dövrü     │──▶│   Qeydiyyatı │──▶│   Qalıcılığı   │   │
│  │  (ReAct)    │   │              │   │   (DB)         │   │
│  └─────┬───────┘   └──────────────┘   └────────────────┘   │
│        │                                                     │
│        ▼                                                     │
│  ┌─────────────┐   ┌──────────────┐                         │
│  │  Axın       │   │   Yaddaş     │                         │
│  │  (SSE)      │   │   İdarəçisi  │                         │
│  └─────────────┘   └──────────────┘                         │
└──────────────────────────────────────────────────────────────┘
```

---

## Verilənlər Bazası Sxemi

```php
<?php

// database/migrations/xxxx_create_agent_tables.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_sessions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('data_analysis');
            $table->text('goal');
            $table->string('status')->default('pending'); // pending|running|paused|completed|failed
            $table->json('config')->nullable();
            $table->json('final_result')->nullable();
            $table->unsignedInteger('iteration_count')->default(0);
            $table->unsignedInteger('max_iterations')->default(20);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('agent_steps', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('session_id');
            $table->unsignedInteger('iteration');
            $table->string('type'); // thought|tool_call|tool_result|final_answer
            $table->text('content');
            $table->json('metadata')->nullable();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('session_id')
                  ->references('id')
                  ->on('agent_sessions')
                  ->cascadeOnDelete();

            $table->index(['session_id', 'iteration']);
        });

        Schema::create('agent_messages', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('session_id');
            $table->string('role'); // user|assistant|tool_result
            $table->json('content'); // tam mesaj məzmun massivini saxlayır
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('session_id')
                  ->references('id')
                  ->on('agent_sessions')
                  ->cascadeOnDelete();
        });
    }
};
```

---

## Alət Qeydiyyatı

```php
<?php

namespace App\AI\Tools;

use Illuminate\Support\Collection;

class ToolRegistry
{
    private array $tools = [];

    public function register(AgentTool $tool): static
    {
        $this->tools[$tool->name()] = $tool;
        return $this;
    }

    public function get(string $name): ?AgentTool
    {
        return $this->tools[$name] ?? null;
    }

    public function schemas(): array
    {
        return array_map(fn(AgentTool $t) => $t->schema(), array_values($this->tools));
    }

    public function execute(string $name, array $input): ToolResult
    {
        $tool = $this->get($name);

        if (!$tool) {
            return ToolResult::error("Unknown tool: {$name}");
        }

        try {
            // Girişi sxemə qarşı yoxlayın
            $tool->validate($input);
            return $tool->execute($input);
        } catch (\Throwable $e) {
            return ToolResult::error("Tool {$name} failed: {$e->getMessage()}");
        }
    }
}

abstract class AgentTool
{
    abstract public function name(): string;
    abstract public function description(): string;
    abstract public function inputSchema(): array;
    abstract public function execute(array $input): ToolResult;

    public function schema(): array
    {
        return [
            'name' => $this->name(),
            'description' => $this->description(),
            'input_schema' => [
                'type' => 'object',
                ...$this->inputSchema(),
            ],
        ];
    }

    public function validate(array $input): void
    {
        $required = $this->inputSchema()['required'] ?? [];
        foreach ($required as $field) {
            if (!isset($input[$field])) {
                throw new \InvalidArgumentException("Missing required field: {$field}");
            }
        }
    }
}

final class ToolResult
{
    private function __construct(
        public readonly bool $success,
        public readonly mixed $data,
        public readonly ?string $error = null,
    ) {}

    public static function success(mixed $data): static
    {
        return new static(true, $data);
    }

    public static function error(string $message): static
    {
        return new static(false, null, $message);
    }

    public function toText(): string
    {
        if (!$this->success) {
            return "ERROR: {$this->error}";
        }

        return is_string($this->data)
            ? $this->data
            : json_encode($this->data, JSON_PRETTY_PRINT);
    }
}
```

---

## Data Analizi Üçün Konkret Alətlər

```php
<?php

namespace App\AI\Tools\DataAnalysis;

use App\AI\Tools\AgentTool;
use App\AI\Tools\ToolResult;
use Illuminate\Support\Facades\DB;

class QueryDatabaseTool extends AgentTool
{
    // İcazə verilən cədvəllər — təhlükəsizlik: agentin ixtiyari cədvəlləri sorğulamasına heç vaxt icazə verməyin
    private array $allowedTables = ['orders', 'users', 'products', 'revenue_snapshots'];

    public function name(): string { return 'query_database'; }

    public function description(): string
    {
        return 'Execute a read-only SQL query against the analytics database. Only SELECT statements are allowed.';
    }

    public function inputSchema(): array
    {
        return [
            'properties' => [
                'sql' => [
                    'type' => 'string',
                    'description' => 'A SELECT SQL query. Must be read-only.',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Human-readable description of what this query does.',
                ],
            ],
            'required' => ['sql', 'description'],
        ];
    }

    public function execute(array $input): ToolResult
    {
        $sql = $input['sql'];

        // Təhlükəsizlik: yalnız SELECT-ə icazə verin
        if (!preg_match('/^\s*SELECT\s/i', $sql)) {
            return ToolResult::error('Only SELECT queries are allowed.');
        }

        // Təhlükəsizlik: təhlükəli açar sözlər üçün yoxlayın
        $dangerous = ['DROP', 'DELETE', 'UPDATE', 'INSERT', 'TRUNCATE', 'ALTER', 'CREATE'];
        foreach ($dangerous as $keyword) {
            if (stripos($sql, $keyword) !== false) {
                return ToolResult::error("Query contains forbidden keyword: {$keyword}");
            }
        }

        try {
            $results = DB::select($sql);

            if (empty($results)) {
                return ToolResult::success('Query returned no results.');
            }

            // Kontekst dolmasının qarşısını almaq üçün çıxışı məhdudlaşdırın
            $limited = array_slice($results, 0, 100);
            $count = count($results);
            $note = $count > 100 ? " (showing 100 of {$count} rows)" : '';

            return ToolResult::success([
                'rows' => $limited,
                'row_count' => $count,
                'note' => $note,
                'columns' => array_keys((array) $limited[0]),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error("Query failed: {$e->getMessage()}");
        }
    }
}

class GetDatabaseSchemaTool extends AgentTool
{
    public function name(): string { return 'get_schema'; }

    public function description(): string
    {
        return 'Get the database schema — table names, column names, and types. Use this first to understand available data.';
    }

    public function inputSchema(): array
    {
        return [
            'properties' => [
                'table' => [
                    'type' => 'string',
                    'description' => 'Optional: specific table name. If omitted, returns all tables.',
                ],
            ],
            'required' => [],
        ];
    }

    public function execute(array $input): ToolResult
    {
        $table = $input['table'] ?? null;

        $query = "SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE, IS_NULLABLE
                  FROM INFORMATION_SCHEMA.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE()";

        if ($table) {
            $query .= " AND TABLE_NAME = ?";
            $rows = DB::select($query, [$table]);
        } else {
            $rows = DB::select($query);
        }

        $schema = collect($rows)->groupBy('TABLE_NAME')->map(function ($columns) {
            return $columns->map(fn($col) => [
                'column' => $col->COLUMN_NAME,
                'type' => $col->DATA_TYPE,
                'nullable' => $col->IS_NULLABLE === 'YES',
            ])->values();
        });

        return ToolResult::success($schema);
    }
}

class CreateChartTool extends AgentTool
{
    public function name(): string { return 'create_chart'; }

    public function description(): string
    {
        return 'Create a chart specification from data. Returns a Chart.js configuration that will be rendered.';
    }

    public function inputSchema(): array
    {
        return [
            'properties' => [
                'type' => [
                    'type' => 'string',
                    'enum' => ['bar', 'line', 'pie', 'doughnut', 'scatter'],
                    'description' => 'Chart type',
                ],
                'title' => ['type' => 'string'],
                'labels' => ['type' => 'array', 'items' => ['type' => 'string']],
                'datasets' => [
                    'type' => 'array',
                    'description' => 'Array of dataset objects with label and data fields',
                ],
            ],
            'required' => ['type', 'title', 'labels', 'datasets'],
        ];
    }

    public function execute(array $input): ToolResult
    {
        // Frontend render etmək üçün chart konfiqurasiyasını sessiya/cache-də saxlayın
        $chartId = uniqid('chart_');

        $config = [
            'id' => $chartId,
            'type' => $input['type'],
            'data' => [
                'labels' => $input['labels'],
                'datasets' => $input['datasets'],
            ],
            'options' => [
                'responsive' => true,
                'plugins' => [
                    'title' => [
                        'display' => true,
                        'text' => $input['title'],
                    ],
                ],
            ],
        ];

        cache()->put("chart:{$chartId}", $config, now()->addHour());

        return ToolResult::success("Chart created with ID: {$chartId}. The chart '{$input['title']}' will be displayed to the user.");
    }
}

class WriteReportSectionTool extends AgentTool
{
    public function name(): string { return 'write_report_section'; }

    public function description(): string
    {
        return 'Add a section to the analysis report. Call multiple times to build the full report.';
    }

    public function inputSchema(): array
    {
        return [
            'properties' => [
                'section_title' => ['type' => 'string'],
                'content' => ['type' => 'string', 'description' => 'Markdown content for this section'],
                'order' => ['type' => 'integer', 'description' => 'Section order (1, 2, 3...)'],
            ],
            'required' => ['section_title', 'content', 'order'],
        ];
    }

    public function execute(array $input): ToolResult
    {
        // Real implementasiyada sessiyaya saxlayın
        return ToolResult::success("Section '{$input['section_title']}' added to report at position {$input['order']}.");
    }
}
```

---

## Agent Dövrü

```php
<?php

namespace App\AI\Agents;

use App\AI\Tools\ToolRegistry;
use App\Models\AgentSession;
use App\Models\AgentStep;
use App\Models\AgentMessage;
use Anthropic\Client;
use Illuminate\Support\Str;

class DataAnalysisAgent
{
    private const SYSTEM_PROMPT = <<<PROMPT
    You are an expert data analyst with access to a business database. Your job is to:
    1. Understand what analysis the user wants
    2. Explore the database schema to understand available data
    3. Write SQL queries to gather relevant data
    4. Create visualizations (charts) for key metrics
    5. Write a comprehensive analysis report

    Always start by checking the schema. Write clear, efficient SQL. Explain your findings.
    When you have completed the full analysis, call finish_analysis with your final summary.

    Be thorough but efficient — avoid redundant queries. If a query returns an error, fix it.
    PROMPT;

    public function __construct(
        private readonly Client $claude,
        private readonly ToolRegistry $tools,
    ) {}

    public function run(AgentSession $session): \Generator
    {
        $session->update([
            'status' => 'running',
            'started_at' => now(),
        ]);

        yield $this->event('status', ['status' => 'running', 'message' => 'Agent started']);

        // Söhbəti yükləyin ya da ilk dəfə başladın
        $messages = $this->loadMessages($session);

        if ($messages->isEmpty()) {
            $userMessage = [
                'role' => 'user',
                'content' => $session->goal,
            ];
            $this->saveMessage($session, $userMessage);
            $messages->push($userMessage);
        }

        $iteration = $session->iteration_count;

        while ($iteration < $session->max_iterations) {
            $iteration++;
            $session->update(['iteration_count' => $iteration]);

            yield $this->event('iteration', [
                'iteration' => $iteration,
                'max' => $session->max_iterations,
            ]);

            // LLM-i çağırın
            $response = $this->callLLM($messages->toArray(), $session);

            $usage = $response->usage;
            $stopReason = $response->stopReason;

            // Cavab məzmununu emal edin
            $assistantContent = [];
            $hasToolUse = false;

            foreach ($response->content as $block) {
                $assistantContent[] = $block->toArray();

                if ($block->type === 'text' && !empty($block->text)) {
                    // Düşüncə/mühakiməni saxlayın
                    $this->saveStep($session, $iteration, 'thought', $block->text, [
                        'input_tokens' => $usage->inputTokens,
                        'output_tokens' => $usage->outputTokens,
                    ]);

                    yield $this->event('thought', ['text' => $block->text]);
                }

                if ($block->type === 'tool_use') {
                    $hasToolUse = true;

                    yield $this->event('tool_call', [
                        'tool' => $block->name,
                        'input' => $block->input,
                    ]);
                }
            }

            // Köməkçi mesajını saxlayın
            $assistantMessage = ['role' => 'assistant', 'content' => $assistantContent];
            $this->saveMessage($session, $assistantMessage);
            $messages->push($assistantMessage);

            // Alət istifadəsi yoxdursa, agent tamamlandı
            if ($stopReason === 'end_turn' && !$hasToolUse) {
                $finalText = collect($response->content)
                    ->where('type', 'text')
                    ->pluck('text')
                    ->join("\n\n");

                $session->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                    'final_result' => ['summary' => $finalText],
                ]);

                yield $this->event('complete', ['summary' => $finalText]);
                return;
            }

            // Alət çağırışlarını icra edin və nəticələri toplayın
            if ($hasToolUse) {
                $toolResultContent = [];

                foreach ($response->content as $block) {
                    if ($block->type !== 'tool_use') continue;

                    $result = $this->tools->execute($block->name, $block->input);

                    $this->saveStep($session, $iteration, 'tool_result', $result->toText(), [
                        'tool' => $block->name,
                        'success' => $result->success,
                    ]);

                    yield $this->event('tool_result', [
                        'tool' => $block->name,
                        'success' => $result->success,
                        'result_preview' => Str::limit($result->toText(), 200),
                    ]);

                    $toolResultContent[] = [
                        'type' => 'tool_result',
                        'tool_use_id' => $block->id,
                        'content' => $result->toText(),
                    ];
                }

                // Alət nəticələrini mesajlara əlavə edin
                $toolResultMessage = ['role' => 'user', 'content' => $toolResultContent];
                $this->saveMessage($session, $toolResultMessage);
                $messages->push($toolResultMessage);
            }
        }

        // Maksimal iterasiyaya çatıldı
        $session->update([
            'status' => 'failed',
            'final_result' => ['error' => 'Max iterations reached without completing analysis'],
        ]);

        yield $this->event('error', ['message' => 'Agent reached maximum iterations']);
    }

    private function callLLM(array $messages, AgentSession $session): mixed
    {
        return $this->claude->messages()->create([
            'model' => 'claude-opus-4-5',
            'max_tokens' => 4096,
            'system' => self::SYSTEM_PROMPT,
            'tools' => $this->tools->schemas(),
            'messages' => $messages,
        ]);
    }

    private function loadMessages(AgentSession $session): \Illuminate\Support\Collection
    {
        return AgentMessage::where('session_id', $session->id)
            ->orderBy('created_at')
            ->get()
            ->map(fn($m) => ['role' => $m->role, 'content' => $m->content]);
    }

    private function saveMessage(AgentSession $session, array $message): void
    {
        AgentMessage::create([
            'id' => Str::ulid(),
            'session_id' => $session->id,
            'role' => $message['role'],
            'content' => $message['content'],
        ]);
    }

    private function saveStep(AgentSession $session, int $iteration, string $type, string $content, array $metadata = []): void
    {
        AgentStep::create([
            'id' => Str::ulid(),
            'session_id' => $session->id,
            'iteration' => $iteration,
            'type' => $type,
            'content' => $content,
            'metadata' => $metadata,
        ]);
    }

    private function event(string $type, array $data): array
    {
        return ['event' => $type, 'data' => $data, 'timestamp' => now()->toISOString()];
    }
}
```

---

## Frontend-ə Axın (SSE)

```php
<?php

namespace App\Http\Controllers;

use App\AI\Agents\DataAnalysisAgent;
use App\AI\Tools\ToolRegistry;
use App\AI\Tools\DataAnalysis\QueryDatabaseTool;
use App\AI\Tools\DataAnalysis\GetDatabaseSchemaTool;
use App\AI\Tools\DataAnalysis\CreateChartTool;
use App\AI\Tools\DataAnalysis\WriteReportSectionTool;
use App\Models\AgentSession;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataAnalysisController extends Controller
{
    public function create(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'goal' => ['required', 'string', 'min:10', 'max:1000'],
            'max_iterations' => ['integer', 'min:5', 'max:30'],
        ]);

        $session = AgentSession::create([
            'id' => Str::ulid(),
            'user_id' => $request->user()->id,
            'type' => 'data_analysis',
            'goal' => $request->string('goal'),
            'max_iterations' => $request->integer('max_iterations', 20),
            'status' => 'pending',
        ]);

        return response()->json([
            'session_id' => $session->id,
            'stream_url' => route('agent.stream', $session->id),
        ], 201);
    }

    public function stream(Request $request, string $sessionId): StreamedResponse
    {
        $session = AgentSession::where('id', $sessionId)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        if ($session->status === 'completed') {
            abort(410, 'Session already completed');
        }

        return response()->stream(function () use ($session) {
            // Çıxış bufferinq deaktiv edin
            while (ob_get_level() > 0) ob_end_flush();

            $agent = $this->buildAgent();

            foreach ($agent->run($session) as $event) {
                $this->sendSseEvent($event['event'], $event['data']);

                if (in_array($event['event'], ['complete', 'error'])) {
                    break;
                }
            }

            $this->sendSseEvent('done', []);
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no', // nginx bufferinqi deaktiv edin
        ]);
    }

    private function sendSseEvent(string $event, array $data): void
    {
        echo "event: {$event}\n";
        echo "data: " . json_encode($data) . "\n\n";
        flush();
    }

    private function buildAgent(): DataAnalysisAgent
    {
        $registry = new ToolRegistry();
        $registry->register(new GetDatabaseSchemaTool())
                 ->register(new QueryDatabaseTool())
                 ->register(new CreateChartTool())
                 ->register(new WriteReportSectionTool());

        return new DataAnalysisAgent(
            claude: app(\Anthropic\Client::class),
            tools: $registry,
        );
    }
}
```

### JavaScript Frontend (SSE Client)

```javascript
// resources/js/agent-stream.js

class AgentStreamClient {
    constructor(streamUrl, handlers = {}) {
        this.streamUrl = streamUrl;
        this.handlers = handlers;
        this.eventSource = null;
    }

    connect() {
        this.eventSource = new EventSource(this.streamUrl);

        const events = ['status', 'iteration', 'thought', 'tool_call',
                        'tool_result', 'complete', 'error', 'done'];

        events.forEach(event => {
            this.eventSource.addEventListener(event, (e) => {
                const data = JSON.parse(e.data);
                this.handlers[event]?.(data);
            });
        });

        this.eventSource.onerror = () => {
            this.handlers.connection_error?.();
            this.eventSource.close();
        };
    }

    disconnect() {
        this.eventSource?.close();
    }
}

// İstifadə
const client = new AgentStreamClient('/api/agent/stream/sess_123', {
    thought: (data) => console.log('Agent düşünür:', data.text),
    tool_call: (data) => console.log(`Alət çağırılır: ${data.tool}`),
    tool_result: (data) => updateToolResultUI(data),
    complete: (data) => showFinalReport(data.summary),
    error: (data) => showError(data.message),
});

client.connect();
```

---

## Marşrutlar

```php
// routes/api.php

use App\Http\Controllers\DataAnalysisController;

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/agent/data-analysis', [DataAnalysisController::class, 'create'])
         ->name('agent.create');

    Route::get('/agent/stream/{sessionId}', [DataAnalysisController::class, 'stream'])
         ->name('agent.stream');

    Route::get('/agent/sessions', [DataAnalysisController::class, 'index'])
         ->name('agent.index');

    Route::get('/agent/sessions/{sessionId}', [DataAnalysisController::class, 'show'])
         ->name('agent.show');
});
```

---

## Agent Modelləri

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgentSession extends Model
{
    protected $fillable = [
        'id', 'user_id', 'type', 'goal', 'status',
        'config', 'final_result', 'iteration_count',
        'max_iterations', 'started_at', 'completed_at',
    ];

    protected $casts = [
        'config' => 'array',
        'final_result' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public $incrementing = false;
    protected $keyType = 'string';

    public function steps(): HasMany
    {
        return $this->hasMany(AgentStep::class, 'session_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AgentMessage::class, 'session_id');
    }

    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    public function tokenCount(): int
    {
        return $this->steps()->sum(
            \DB::raw("COALESCE(JSON_EXTRACT(metadata, '$.input_tokens'), 0) + COALESCE(JSON_EXTRACT(metadata, '$.output_tokens'), 0)")
        );
    }
}
```

---

## İstehsalat Mülahizələri

### Vaxt Aşımı İdarəetməsi

SSE bağlantıları kəsilə bilər. Davam etdirmə imkanı həyata keçirin:

```php
// Yenidən qoşulduqda, sessiyanın hələ çalışıb-çalışmadığını yoxlayın
// Son saxlanılmış mesajdan davam edin (mesajlar DB-də saxlanılır)
// Davam edən agent işinə SSE axınını yenidən əlavə edin

public function resume(AgentSession $session): StreamedResponse
{
    // Artıq baş verənləri göstərmək üçün son N addımı yükləyin
    $recentSteps = $session->steps()
        ->latest()
        ->take(10)
        ->get()
        ->reverse();

    return response()->stream(function () use ($session, $recentSteps) {
        // Son hadisələri təkrar oynayın
        foreach ($recentSteps as $step) {
            $this->sendSseEvent('replay', [
                'type' => $step->type,
                'content' => $step->content,
                'iteration' => $step->iteration,
            ]);
        }

        // Tamamlanmadıysa çalışmağa davam edin
        if (!in_array($session->status, ['completed', 'failed'])) {
            $agent = $this->buildAgent();
            foreach ($agent->run($session) as $event) {
                $this->sendSseEvent($event['event'], $event['data']);
            }
        }
    });
}
```

### Xərc İzləmə

```php
// Hər LLM çağırışından sonra xərci qeydə alın
$inputCost  = ($usage->inputTokens  / 1_000_000) * 15.00;  // Claude Opus qiymətləndirməsi
$outputCost = ($usage->outputTokens / 1_000_000) * 75.00;

DB::table('agent_costs')->insert([
    'session_id'    => $session->id,
    'input_tokens'  => $usage->inputTokens,
    'output_tokens' => $usage->outputTokens,
    'cost_usd'      => $inputCost + $outputCost,
    'model'         => 'claude-opus-4-5',
    'created_at'    => now(),
]);
```

### Alət İcrasını Sandboxlamaq

Kod icra edən ya da sorğu çalıştıran agentlər üçün həmişə sandbox istifadə edin:

```php
class QueryDatabaseTool extends AgentTool
{
    public function execute(array $input): ToolResult
    {
        // Yalnız oxuma replica-sında çalışdırın
        return DB::connection('analytics_readonly')
            ->transaction(function () use ($input) {
                // Sorğu vaxt aşımı təyin edin
                DB::statement('SET statement_timeout = 5000'); // 5 saniyə (PostgreSQL)
                return DB::select($input['sql']);
            });
    }
}
```
