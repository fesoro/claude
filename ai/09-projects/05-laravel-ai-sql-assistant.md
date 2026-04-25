# Natural Language → SQL — Business User DB Asistanı (Laravel) (Senior)

> **Use-case**: marketinq, ops, maliyyə komandası SQL bilmir, amma "Bakı-dan son həftə neçə sifariş gəldi?" kimi sualı cavablandırmaq istəyir. BA-lar hər soruşduqlarında backend dev vaxt itirir. Bu proyekt **Laravel + Claude + read-replica** ilə güvənli natural-language-to-SQL assistant qurur.

---

## Mündəricat

1. [Təhlükələr və Dizayn Prinsipləri](#safety)
2. [Arxitektura](#architecture)
3. [Migrations](#migrations)
4. [Schema Introspection](#schema)
5. [Two-pass Generation](#two-pass)
6. [SQL Validation Layer](#validation)
7. [Execution (Read Replica)](#execution)
8. [Result Summarization + Chart](#summarize)
9. [Filament UI](#ui)
10. [Refinement və Conversation](#refinement)
11. [Observability + Audit](#audit)
12. [Cost Optimization](#cost)
13. [Tests](#tests)

---

## Təhlükələr və Dizayn Prinsipləri <a name="safety"></a>

Natural-language-to-SQL pattern-i **DEFAULT təhlükəlidir**. Xətalar:

| Risk | Qarşı tədbir |
|------|--------------|
| LLM `DROP TABLE users` yaradır | READ-ONLY DB user + SQL parser whitelist |
| SQL injection (user input prompt-a enjekte olunur) | Parameterized query məcbur yoxdur (LLM özü SQL yazır) → **validation layer** |
| Həssas məlumat leak (salary, PII) | Table-level ACL + column-level redaction |
| `SELECT * FROM users` → 10M sətir → DB crash | `LIMIT` inject + timeout 5s |
| Hallucinated column/table | Schema validation → retry with error feedback |
| Prompt injection via data | Həssas cədvəlləri hidden tag ilə |

**Qızıl qaydalar**:
1. **Read-only istifadəçi**. DDL yoxdur. UPDATE/DELETE/INSERT yoxdur.
2. **Read replica**. Primary DB-yə heç nə toxunmur.
3. **SQL parser**. LLM yazdığı SQL execute olunmadan əvvəl parse + validate.
4. **Row limit**. 10,000 max. Excel export-a keçsələr — background job.
5. **Timeout**. 5 saniyə. Yavaş query → iptal.
6. **Audit**. Hər generated SQL + kim + nə vaxt + nəticə rowcount.

---

## Arxitektura <a name="architecture"></a>

```
┌─────────────────────┐
│ Business User       │
│ (Filament UI)       │
└──────┬──────────────┘
       │ "Bakı-dan son həftə neçə sifariş?"
       ▼
┌─────────────────────────────────────────┐
│ SqlAssistantController                  │
│  1. Schema retrieve (relevant tables)   │
│  2. Plan generation (Claude Haiku)      │
│  3. SQL generation (Claude Sonnet)      │
│  4. Validation (pg_query parser)        │
│  5. Execute (pgsql.read replica)        │
│  6. Summarize + chart (Claude Sonnet)   │
└──────┬──────────────────────────────────┘
       │
       ▼
┌─────────────────────────────────────────┐
│ Audit log + conversation history        │
└─────────────────────────────────────────┘
```

---

## Migrations <a name="migrations"></a>

```php
<?php
// database/migrations/2026_04_21_000001_create_sql_conversations.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sql_conversations', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('sql_queries', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('conversation_id')->constrained('sql_conversations')->cascadeOnDelete();
            $table->text('user_question');
            $table->text('selected_tables')->nullable();     // JSON list (plan output)
            $table->text('generated_sql')->nullable();
            $table->text('validation_error')->nullable();
            $table->integer('row_count')->nullable();
            $table->decimal('execution_ms', 10, 2)->nullable();
            $table->text('summary_text')->nullable();        // Claude-un plain English cavabı
            $table->json('chart_spec')->nullable();          // Chart.js config
            $table->string('status');                        // pending | success | failed | refined
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->timestamps();
            $table->index(['conversation_id', 'created_at']);
        });

        Schema::create('sql_audit_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->text('question');
            $table->text('sql');
            $table->integer('row_count')->nullable();
            $table->string('status');
            $table->text('error')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'created_at']);
            $table->index('status');
        });
    }
};
```

---

## Schema Introspection <a name="schema"></a>

### Schema export command

```php
<?php
// app/Console/Commands/ExportSchemaForAI.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExportSchemaForAI extends Command
{
    protected $signature = 'ai:export-schema {--driver=pgsql}';
    protected $description = 'Export DB schema to JSON for AI SQL assistant';

    public function handle(): int
    {
        $tables = DB::select("
            SELECT table_name, obj_description(format('public.%I', table_name)::regclass, 'pg_class') AS comment
            FROM information_schema.tables
            WHERE table_schema = 'public'
              AND table_name NOT IN ('migrations', 'password_reset_tokens', 'personal_access_tokens', 'failed_jobs', 'sql_conversations', 'sql_queries', 'sql_audit_log')
        ");

        $schema = [];
        foreach ($tables as $t) {
            $columns = DB::select("
                SELECT column_name, data_type, is_nullable,
                       col_description(format('public.%I', table_name)::regclass,
                                       ordinal_position) AS comment
                FROM information_schema.columns
                WHERE table_schema = 'public' AND table_name = ?
                ORDER BY ordinal_position
            ", [$t->table_name]);

            $fks = DB::select("
                SELECT kcu.column_name, ccu.table_name AS foreign_table, ccu.column_name AS foreign_column
                FROM information_schema.table_constraints tc
                JOIN information_schema.key_column_usage kcu ON tc.constraint_name = kcu.constraint_name
                JOIN information_schema.constraint_column_usage ccu ON tc.constraint_name = ccu.constraint_name
                WHERE tc.constraint_type = 'FOREIGN KEY' AND tc.table_name = ?
            ", [$t->table_name]);

            $schema[] = [
                'table' => $t->table_name,
                'comment' => $t->comment,
                'columns' => collect($columns)->map(fn($c) => [
                    'name' => $c->column_name,
                    'type' => $c->data_type,
                    'nullable' => $c->is_nullable === 'YES',
                    'comment' => $c->comment,
                ])->all(),
                'foreign_keys' => $fks,
            ];
        }

        file_put_contents(
            storage_path('app/ai-schema.json'),
            json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        $this->info('Schema exported: ' . storage_path('app/ai-schema.json'));
        return 0;
    }
}
```

### Schema Retrieval Service (Relevant Tables Only)

Full schema 50+ cədvəllə 200k+ token-ə çatır. Hər sorğuda 5-10 relevant cədvəli seç.

```php
<?php
// app/Services/Ai/SchemaRetriever.php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Cache;
use App\Services\Ai\EmbeddingClient;

class SchemaRetriever
{
    public function __construct(private EmbeddingClient $embeddings) {}

    public function relevantTables(string $question, int $topK = 8): array
    {
        $schema = json_decode(file_get_contents(storage_path('app/ai-schema.json')), true);

        // Bir dəfə — hər cədvəl üçün embedding cache-lə
        $tableEmbeddings = Cache::remember('ai.schema.embeddings.v1', 86400, fn() =>
            collect($schema)->map(fn($t) => [
                'table' => $t['table'],
                'embedding' => $this->embeddings->embed(
                    "Table {$t['table']}: {$t['comment']}. Columns: " .
                    collect($t['columns'])->pluck('name')->implode(', ')
                ),
            ])->all()
        );

        $questionEmb = $this->embeddings->embed($question);

        return collect($tableEmbeddings)
            ->map(fn($t) => [
                'table' => $t['table'],
                'score' => $this->cosine($t['embedding'], $questionEmb),
            ])
            ->sortByDesc('score')
            ->take($topK)
            ->map(fn($t) => collect($schema)->firstWhere('table', $t['table']))
            ->values()
            ->all();
    }

    private function cosine(array $a, array $b): float
    {
        $dot = 0.0; $normA = 0.0; $normB = 0.0;
        foreach ($a as $i => $v) {
            $dot += $v * $b[$i];
            $normA += $v * $v;
            $normB += $b[$i] * $b[$i];
        }
        return $dot / (sqrt($normA) * sqrt($normB));
    }
}
```

---

## Two-pass Generation <a name="two-pass"></a>

### Pass 1: Plan (Haiku, ucuz)

```php
<?php
// app/Services/Ai/SqlPlanner.php

namespace App\Services\Ai;

use App\Services\Ai\ClaudeClient;

class SqlPlanner
{
    public function __construct(
        private ClaudeClient $claude,
        private SchemaRetriever $schemas,
    ) {}

    public function plan(string $question): array
    {
        $tables = $this->schemas->relevantTables($question, 8);
        $tablesText = collect($tables)->map(fn($t) => $this->formatTable($t))->implode("\n\n");

        $response = $this->claude->messages([
            'model' => 'claude-haiku-4-5',
            'max_tokens' => 500,
            'system' => <<<SYS
                You are a SQL query planner. Given a business question and a database schema,
                identify which tables are needed and what the query structure should be.
                Output JSON only, no prose.
                SYS,
            'messages' => [[
                'role' => 'user',
                'content' => <<<MSG
                Schema:
                {$tablesText}

                Question: {$question}

                Output JSON:
                {
                  "tables_needed": ["table1", "table2"],
                  "joins": "brief description of joins if any",
                  "aggregations": ["count", "sum", ...],
                  "filters": ["date >= now() - 7 days", "country = 'Azerbaijan'"],
                  "group_by": [...],
                  "feasible": true|false,
                  "reason_if_not_feasible": "..."
                }
                MSG,
            ]],
        ]);

        return json_decode($response['content'][0]['text'], true);
    }

    private function formatTable(array $t): string
    {
        $cols = collect($t['columns'])
            ->map(fn($c) => "  - {$c['name']} ({$c['type']})" . ($c['comment'] ? " — {$c['comment']}" : ''))
            ->implode("\n");

        $fks = collect($t['foreign_keys'])
            ->map(fn($fk) => "  FK: {$fk->column_name} → {$fk->foreign_table}.{$fk->foreign_column}")
            ->implode("\n");

        return "Table: {$t['table']}" . ($t['comment'] ? " — {$t['comment']}" : '')
            . "\n{$cols}" . ($fks ? "\n{$fks}" : '');
    }
}
```

### Pass 2: SQL Generation (Sonnet)

```php
<?php
// app/Services/Ai/SqlGenerator.php

namespace App\Services\Ai;

class SqlGenerator
{
    public function __construct(
        private ClaudeClient $claude,
        private SchemaRetriever $schemas,
    ) {}

    public function generate(string $question, array $plan, ?string $previousError = null): array
    {
        $tables = collect($plan['tables_needed'])
            ->map(fn($name) => $this->schemas->getTable($name))
            ->filter()
            ->all();

        $tablesText = collect($tables)->map(fn($t) => $this->formatTable($t))->implode("\n\n");

        $errorContext = $previousError
            ? "\n\nPrevious attempt failed with error: {$previousError}\nFix the error in the new query."
            : '';

        $response = $this->claude->messages([
            'model' => 'claude-sonnet-4-5',
            'max_tokens' => 1000,
            'system' => [[
                'type' => 'text',
                'text' => $this->systemPrompt(),
                'cache_control' => ['type' => 'ephemeral'],  // prompt cache — sistem prompt statikdir
            ]],
            'messages' => [[
                'role' => 'user',
                'content' => <<<MSG
                Schema for relevant tables:
                {$tablesText}

                User question: {$question}

                Plan: {$plan['joins']} | Aggregations: {$this->joinList($plan['aggregations'])} | Filters: {$this->joinList($plan['filters'])}
                {$errorContext}

                Generate a single valid PostgreSQL SELECT query. Return JSON:
                {"sql": "SELECT ...", "explanation": "brief explanation"}
                MSG,
            ]],
        ]);

        $json = json_decode($response['content'][0]['text'], true);
        if (!isset($json['sql'])) {
            throw new \RuntimeException('LLM did not return valid SQL JSON');
        }

        return [
            'sql' => $json['sql'],
            'explanation' => $json['explanation'] ?? '',
            'usage' => $response['usage'],
        ];
    }

    private function systemPrompt(): string
    {
        return <<<PROMPT
            You are a PostgreSQL expert. You generate **read-only SELECT queries** for business users.

            **Strict rules**:
            1. ONLY SELECT queries. No INSERT, UPDATE, DELETE, DROP, ALTER, CREATE, TRUNCATE, GRANT.
            2. No dynamic SQL (no EXECUTE, no DO blocks).
            3. Always use LIMIT. If user didn't specify, default to LIMIT 1000.
            4. Use explicit JOIN syntax, not implicit comma joins.
            5. Use table aliases for readability.
            6. For date filters, use PostgreSQL interval syntax: `NOW() - INTERVAL '7 days'`.
            7. For case-insensitive string match, use ILIKE.
            8. Use `COUNT(DISTINCT ...)` when business asks "unique/different".
            9. Round decimals: `ROUND(SUM(amount), 2)`.
            10. Only reference tables and columns that exist in the provided schema. Never invent.

            Output JSON only:
            {"sql": "...", "explanation": "..."}
            PROMPT;
    }

    private function joinList(array $items): string { return implode(', ', $items); }
}
```

---

## SQL Validation Layer <a name="validation"></a>

LLM mükəmməl deyil — SQL parse + validate et.

```php
<?php
// app/Services/Ai/SqlValidator.php

namespace App\Services\Ai;

class SqlValidator
{
    private array $forbiddenKeywords = [
        'INSERT', 'UPDATE', 'DELETE', 'DROP', 'ALTER', 'CREATE',
        'TRUNCATE', 'GRANT', 'REVOKE', 'EXECUTE', 'CALL',
        'LOCK', 'BEGIN', 'COMMIT', 'ROLLBACK',
    ];

    public function validate(string $sql): array
    {
        $errors = [];

        // 1. Forbidden keywords (surface-level check)
        $upper = strtoupper(preg_replace('/\s+/', ' ', $sql));
        foreach ($this->forbiddenKeywords as $kw) {
            if (preg_match("/\\b{$kw}\\b/", $upper)) {
                $errors[] = "Forbidden keyword: {$kw}";
            }
        }

        // 2. Must start with SELECT or WITH (CTE)
        if (!preg_match('/^\s*(SELECT|WITH)\b/i', $sql)) {
            $errors[] = 'Query must start with SELECT or WITH';
        }

        // 3. Must contain LIMIT
        if (!preg_match('/\bLIMIT\s+\d+/i', $sql)) {
            $errors[] = 'Query must contain LIMIT';
        }

        // 4. LIMIT value reasonable
        if (preg_match('/\bLIMIT\s+(\d+)/i', $sql, $m) && (int)$m[1] > 10000) {
            $errors[] = 'LIMIT exceeds 10000 (use export for larger results)';
        }

        // 5. Parse with pg_query_parser (via pg-query-parser PHP extension or ext via exec)
        // Production-da libpg_query istifadə et — burada simple approach
        try {
            $this->dryRunExplain($sql);
        } catch (\Throwable $e) {
            $errors[] = 'SQL parse error: ' . $e->getMessage();
        }

        return $errors;
    }

    private function dryRunExplain(string $sql): void
    {
        // Read-only replica üzərində EXPLAIN — data toxunulmur
        \DB::connection('pgsql_readonly')->select("EXPLAIN {$sql}");
    }
}
```

---

## Execution (Read Replica) <a name="execution"></a>

### `config/database.php` Fragment

```php
'pgsql_readonly' => [
    'driver' => 'pgsql',
    'host' => env('DB_READ_HOST'),
    'port' => env('DB_READ_PORT', 5432),
    'database' => env('DB_DATABASE'),
    'username' => env('DB_READ_USER'),   // user: ai_readonly
    'password' => env('DB_READ_PASSWORD'),
    'charset' => 'utf8',
    'prefix' => '',
    'schema' => 'public',
    'sslmode' => 'require',
    'options' => [
        \PDO::ATTR_TIMEOUT => 10,
        // statement_timeout PostgreSQL-də → 5 saniyə
    ],
],
```

### DB user yaratma

```sql
CREATE USER ai_readonly WITH PASSWORD '...';
GRANT CONNECT ON DATABASE acme_prod TO ai_readonly;
GRANT USAGE ON SCHEMA public TO ai_readonly;
GRANT SELECT ON ALL TABLES IN SCHEMA public TO ai_readonly;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT ON TABLES TO ai_readonly;

-- Həssas cədvəlləri açma
REVOKE SELECT ON employee_salaries FROM ai_readonly;
REVOKE SELECT ON password_reset_tokens FROM ai_readonly;
REVOKE SELECT ON personal_access_tokens FROM ai_readonly;

-- Statement timeout 5s
ALTER USER ai_readonly SET statement_timeout = '5s';
ALTER USER ai_readonly SET lock_timeout = '1s';
```

### Executor

```php
<?php
// app/Services/Ai/SqlExecutor.php

namespace App\Services\Ai;

use Illuminate\Support\Facades\DB;

class SqlExecutor
{
    public function execute(string $sql): array
    {
        $start = microtime(true);

        try {
            $rows = DB::connection('pgsql_readonly')->select($sql);
            $durationMs = (microtime(true) - $start) * 1000;

            return [
                'success' => true,
                'rows' => $rows,
                'row_count' => count($rows),
                'duration_ms' => round($durationMs, 2),
            ];
        } catch (\Throwable $e) {
            $durationMs = (microtime(true) - $start) * 1000;

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'duration_ms' => round($durationMs, 2),
            ];
        }
    }
}
```

---

## Result Summarization + Chart <a name="summarize"></a>

Raw row-lar business user üçün oxunaqlı deyil. Claude onları plain English-ə çevirib Chart.js spec üretə bilər.

```php
<?php
// app/Services/Ai/ResultSummarizer.php

namespace App\Services\Ai;

class ResultSummarizer
{
    public function __construct(private ClaudeClient $claude) {}

    public function summarize(string $question, array $rows, int $rowCount): array
    {
        $sample = array_slice($rows, 0, 20);  // ilk 20 sətir — kontekst üçün
        $sampleJson = json_encode($sample, JSON_UNESCAPED_UNICODE);

        $response = $this->claude->messages([
            'model' => 'claude-sonnet-4-5',
            'max_tokens' => 800,
            'messages' => [[
                'role' => 'user',
                'content' => <<<MSG
                User asked: {$question}

                Query returned {$rowCount} rows. Sample:
                {$sampleJson}

                Write a 2-3 sentence plain-English answer for the user.
                If the data makes sense as a chart, also provide a Chart.js config.

                Output JSON:
                {
                  "summary": "...",
                  "chart": null | {"type": "bar|line|pie", "data": {...}, "options": {...}}
                }
                MSG,
            ]],
        ]);

        return json_decode($response['content'][0]['text'], true);
    }
}
```

---

## Filament UI <a name="ui"></a>

```php
<?php
// app/Filament/Pages/SqlAssistant.php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use App\Jobs\RunSqlAssistantQueryJob;

class SqlAssistant extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $view = 'filament.pages.sql-assistant';
    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    public ?array $data = [];
    public ?int $conversationId = null;
    public array $messages = [];

    protected function getFormSchema(): array
    {
        return [
            Textarea::make('question')
                ->label('Sualınızı təbii dildə yazın')
                ->placeholder('Məs: Son 7 gündə Bakı-dan neçə sifariş gəldi?')
                ->required(),
        ];
    }

    public function ask(): void
    {
        $this->form->validate();

        $conversation = $this->conversationId
            ? \App\Models\SqlConversation::find($this->conversationId)
            : \App\Models\SqlConversation::create([
                'ulid' => \Str::ulid(),
                'user_id' => auth()->id(),
                'title' => \Str::limit($this->data['question'], 60),
            ]);

        $this->conversationId = $conversation->id;

        $query = \App\Models\SqlQuery::create([
            'ulid' => \Str::ulid(),
            'conversation_id' => $conversation->id,
            'user_question' => $this->data['question'],
            'status' => 'pending',
        ]);

        RunSqlAssistantQueryJob::dispatch($query->id);

        $this->messages[] = ['role' => 'user', 'text' => $this->data['question']];
        $this->messages[] = ['role' => 'assistant', 'loading' => true, 'query_id' => $query->id];
        $this->data['question'] = '';
    }

    public function loadResult(int $queryId): ?array
    {
        $q = \App\Models\SqlQuery::find($queryId);
        if (!$q || $q->status === 'pending') return null;

        return [
            'sql' => $q->generated_sql,
            'summary' => $q->summary_text,
            'chart' => $q->chart_spec,
            'row_count' => $q->row_count,
            'duration_ms' => $q->execution_ms,
        ];
    }
}
```

### Queue Job

```php
<?php
// app/Jobs/RunSqlAssistantQueryJob.php

namespace App\Jobs;

use App\Models\SqlQuery;
use App\Services\Ai\{SqlPlanner, SqlGenerator, SqlValidator, SqlExecutor, ResultSummarizer};
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class RunSqlAssistantQueryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;
    public int $timeout = 60;

    public function __construct(public int $queryId) {}

    public function handle(
        SqlPlanner $planner,
        SqlGenerator $generator,
        SqlValidator $validator,
        SqlExecutor $executor,
        ResultSummarizer $summarizer,
    ): void {
        $query = SqlQuery::find($this->queryId);
        if (!$query) return;

        try {
            // 1. Plan
            $plan = $planner->plan($query->user_question);
            if (!$plan['feasible']) {
                $this->fail($query, $plan['reason_if_not_feasible']);
                return;
            }
            $query->selected_tables = json_encode($plan['tables_needed']);

            // 2. Generate SQL (with retry on error)
            $attempts = 0;
            $previousError = null;
            do {
                $generated = $generator->generate($query->user_question, $plan, $previousError);
                $errors = $validator->validate($generated['sql']);

                if (empty($errors)) break;
                $previousError = implode('; ', $errors);
                $attempts++;
            } while ($attempts < 2);

            if (!empty($errors)) {
                $this->fail($query, 'SQL validation failed: ' . implode('; ', $errors));
                return;
            }

            $query->generated_sql = $generated['sql'];
            $query->input_tokens += $generated['usage']['input_tokens'];
            $query->output_tokens += $generated['usage']['output_tokens'];

            // 3. Execute
            $result = $executor->execute($generated['sql']);
            if (!$result['success']) {
                $this->fail($query, 'Execution failed: ' . $result['error']);
                return;
            }

            $query->row_count = $result['row_count'];
            $query->execution_ms = $result['duration_ms'];

            // 4. Summarize
            $summary = $summarizer->summarize(
                $query->user_question,
                $result['rows'],
                $result['row_count']
            );

            $query->summary_text = $summary['summary'];
            $query->chart_spec = $summary['chart'];
            $query->status = 'success';
            $query->save();

            // Audit
            \DB::table('sql_audit_log')->insert([
                'user_id' => $query->conversation->user_id,
                'question' => $query->user_question,
                'sql' => $query->generated_sql,
                'row_count' => $query->row_count,
                'status' => 'success',
            ]);

        } catch (\Throwable $e) {
            $this->fail($query, $e->getMessage());
        }
    }

    private function fail(SqlQuery $query, string $error): void
    {
        $query->status = 'failed';
        $query->validation_error = $error;
        $query->save();

        \DB::table('sql_audit_log')->insert([
            'user_id' => $query->conversation->user_id,
            'question' => $query->user_question,
            'sql' => $query->generated_sql,
            'status' => 'failed',
            'error' => $error,
        ]);
    }
}
```

---

## Refinement və Conversation <a name="refinement"></a>

User "yalnız Baku-dan olanlar" deyə bilər — əvvəlki query-ni modify et.

```php
// SqlAssistant page-də ikinci sual gələndə
// Conversation history-ni planner-ə kontekst kimi ver

$messages = $conversation->queries()
    ->orderBy('created_at')
    ->limit(5)
    ->get()
    ->map(fn($q) => [
        ['role' => 'user', 'content' => $q->user_question],
        ['role' => 'assistant', 'content' => "I generated: {$q->generated_sql}\nResult: {$q->row_count} rows. {$q->summary_text}"],
    ])
    ->flatten(1)
    ->all();

$messages[] = ['role' => 'user', 'content' => $newRefinementQuestion];
// planner-ə bu məlumat ilə plan qur
```

---

## Observability + Audit <a name="audit"></a>

### Filament Resource for Audit Log

```php
// app/Filament/Resources/SqlAuditLogResource.php
// — user, question, SQL, row_count, duration, status, created_at
```

### Metrics

```
sql_assistant.queries_total{status="success|failed"}
sql_assistant.execution_ms (histogram)
sql_assistant.tokens_used{model, type}
sql_assistant.retry_count (validation retries)
sql_assistant.forbidden_keyword_triggered
```

### Alert-lər

- `forbidden_keyword_triggered` > 0 → potential prompt injection → SOC-a bildiriş
- `execution_ms p95 > 4s` → DB performance degradation → analyst-ə xəbərdarlıq
- `daily_tokens > tenant_budget` → pauza

---

## Cost Optimization <a name="cost"></a>

### Prompt Caching (schema)

Sistem prompt + schema kontenti **statikdir**. Prompt caching ilə 90% endirim:

```php
'system' => [
    ['type' => 'text', 'text' => $systemRules, 'cache_control' => ['type' => 'ephemeral']],
    ['type' => 'text', 'text' => $tablesText, 'cache_control' => ['type' => 'ephemeral']],
],
```

### Cost Model

| Addım | Model | Input | Output | Qiymət/sorğu (təxmini) |
|-------|-------|-------|--------|------------------------|
| Plan | Haiku 4.5 | 2k | 200 | $0.002 |
| SQL gen | Sonnet 4.5 | 3k (cached: 300) | 400 | $0.012 |
| Summary | Sonnet 4.5 | 2k | 300 | $0.010 |
| **Toplam** | — | — | — | **~$0.024/sorğu** |

Gündəlik 500 sorğu → ~$12/gün → $360/ay. 1000 user × orta 10 sorğu → $720/ay.

### Embedding Cache

Schema embeddings-i 24 saat cache-lə. Yenidən hesablama maliyyətsiz.

---

## Tests <a name="tests"></a>

```php
<?php
// tests/Feature/SqlAssistantTest.php

use App\Jobs\RunSqlAssistantQueryJob;
use App\Models\{User, SqlConversation, SqlQuery};

it('rejects forbidden keywords', function () {
    $validator = app(\App\Services\Ai\SqlValidator::class);
    expect($validator->validate("DROP TABLE users"))->not->toBeEmpty();
    expect($validator->validate("SELECT * FROM orders LIMIT 10"))->toBeEmpty();
});

it('enforces LIMIT clause', function () {
    $validator = app(\App\Services\Ai\SqlValidator::class);
    expect($validator->validate("SELECT * FROM orders"))->toContain('LIMIT');
});

it('generates valid SQL for simple question', function () {
    $user = User::factory()->create();
    $conv = SqlConversation::create(['ulid' => Str::ulid(), 'user_id' => $user->id]);
    $query = SqlQuery::create([
        'ulid' => Str::ulid(),
        'conversation_id' => $conv->id,
        'user_question' => 'How many orders last week?',
        'status' => 'pending',
    ]);

    RunSqlAssistantQueryJob::dispatchSync($query->id);
    $query->refresh();

    expect($query->status)->toBe('success');
    expect($query->generated_sql)->toContain('SELECT');
    expect($query->generated_sql)->toContain('LIMIT');
});

it('retries on validation failure with error context', function () {
    // Mock generator to return bad SQL first, then good
    // Expect retry logic to kick in
});
```

---

## Xülasə

| Təhlükəsizlik | Həll |
|---------------|------|
| DDL / DML | Read-only DB user + forbidden keyword filter |
| Large query | LIMIT enforcement + timeout 5s |
| Hallucinated schema | Parse via EXPLAIN, retry with error feedback |
| Sensitive tables | REVOKE SELECT-dən |
| Cost | Prompt caching, Haiku planner, Sonnet generator |
| Audit | Every query logged, Filament UI |

**Yadda saxla**:
- Schema introspection avtomatik — migration əlavə edəndə `php artisan ai:export-schema` işlət
- Production-da read replica istifadə et, primary-ə qoruma ver
- User-ə **SQL-i göstər**, onunla birlikdə summary — trust-i artırır

Növbəti addım — [01-ai-feature-economics.md](../10-product-thinking/01-ai-feature-economics.md): bu feature-in iqtisadiyyatını hesabla.
