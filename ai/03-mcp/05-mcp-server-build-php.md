# Laravel/PHP-da Tam MCP Server Qurmaq (Middle)

## PHP-da MCP Server Niyə Qurmaq Lazımdır?

2025-ci il etibarilə rəsmi Anthropic PHP MCP SDK yoxdur. Lakin MCP stdio üzərindən JSON-RPC 2.0-a əsaslanır — PHP-da həyata keçirilməsi o qədər sadə olan bir protokoldur. PHP, Laravel tətbiqinin datasını ifşa edən MCP serverləri üçün çox münasibdir: Eloquent modellər, verilənlər bazası sorğuları, tətbiq logları, route müayinəsi və biznes məntiqi.

Bu bələdçi **Laravel Artisan əmri kimi tam, istehsala hazır MCP server** qurur ki, Claude Desktop və Claude Code birbaşa istifadə edə bilsin.

---

## Protokol Baxışı: stdio üzərindən JSON-RPC 2.0

MCP stdio nəqli sadədir:
1. Host prosesini işə salır: `php artisan mcp:serve`
2. Host JSON-RPC mesajlarını **stdin**-ə yazır, hər biri bir sətirdə (yeni sətir ilə ayrılır)
3. Serveriniz JSON-RPC cavablarını **stdout**-a yazır, hər biri bir sətirdə
4. Log/debug çıxışı **stderr**-ə gedir (heç vaxt stdout-a — stdout protokol kanalıdır)

Bu bütün nəqldir. Mürəkkəblik MCP metodlarını düzgün həyata keçirməkdədir.

### Tələb Olunan MCP Metodları

| Metod | İstiqamət | Təsvir |
|---|---|---|
| `initialize` | istemci→server | Əl sıxma, imkanları mübadiləsi |
| `initialized` | istemci→server | Bildiriş, əl sıxma tamamlandı |
| `tools/list` | istemci→server | Mövcud toolları qaytarır |
| `tools/call` | istemci→server | Tool icra edir |
| `resources/list` | istemci→server | Mövcud resursları qaytarır |
| `resources/read` | istemci→server | URI ilə resurs oxuyur |
| `prompts/list` | istemci→server | Mövcud promptları qaytarır |
| `prompts/get` | istemci→server | Ada görə prompt alır |
| `ping` | istemci→server | Sağlamlıq yoxlaması |

---

## Tam Laravel İmplementasiyası

### 1. Artisan Əmri (Giriş Nöqtəsi)

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mcp\McpServer;
use Illuminate\Console\Command;

/**
 * MCP stdio server — bu Laravel tətbiqini MCP server kimi qeydiyyatdan keçirir.
 *
 * İstifadə: php artisan mcp:serve
 *
 * claude_desktop_config.json faylında qeydiyyatdan keçirin:
 * {
 *   "mcpServers": {
 *     "my-laravel-app": {
 *       "command": "php",
 *       "args": ["/yol/to/artisan", "mcp:serve"],
 *       "env": { "APP_ENV": "production" }
 *     }
 *   }
 * }
 */
final class McpServeCommand extends Command
{
    protected $signature = 'mcp:serve';
    protected $description = 'MCP stdio serveri başlat — Laravel datasını Claude-a ifşa edir';

    public function handle(McpServer $server): int
    {
        // Vacib: PHP output bufferingi deaktiv et
        // Hər hansı buffering yeni sətir ilə ayrılmış JSON-RPC protokolunu pozur
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $server->run();

        return self::SUCCESS;
    }
}
```

### 2. MCP Server Nüvəsi

```php
<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Mcp\Handlers\InitializeHandler;
use App\Mcp\Handlers\ToolsHandler;
use App\Mcp\Handlers\ResourcesHandler;
use App\Mcp\Handlers\PromptsHandler;
use Throwable;

/**
 * MCP stdio server nüvəsi.
 *
 * stdin-dən yeni sətir ilə ayrılmış JSON-RPC oxuyur,
 * handler-lərə göndərir, stdout-a cavablar yazır.
 */
final class McpServer
{
    private const PROTOCOL_VERSION = '2025-03-26';
    private const SERVER_NAME      = 'laravel-mcp';
    private const SERVER_VERSION   = '1.0.0';

    /** @var array<string, callable(array): array|null> */
    private array $handlers = [];

    public function __construct(
        private readonly InitializeHandler $initialize,
        private readonly ToolsHandler      $tools,
        private readonly ResourcesHandler  $resources,
        private readonly PromptsHandler    $prompts,
    ) {
        $this->registerHandlers();
    }

    private function registerHandlers(): void
    {
        $this->handlers = [
            'initialize'     => fn (array $p) => $this->initialize->handle($p),
            'initialized'    => fn (array $p) => null, // bildiriş, cavab yoxdur
            'ping'           => fn (array $p) => [],
            'tools/list'     => fn (array $p) => $this->tools->list($p),
            'tools/call'     => fn (array $p) => $this->tools->call($p),
            'resources/list' => fn (array $p) => $this->resources->list($p),
            'resources/read' => fn (array $p) => $this->resources->read($p),
            'prompts/list'   => fn (array $p) => $this->prompts->list($p),
            'prompts/get'    => fn (array $p) => $this->prompts->get($p),
        ];
    }

    public function run(): void
    {
        $this->log('MCP server başladı (protokol ' . self::PROTOCOL_VERSION . ')');

        $stdin = fopen('php://stdin', 'r');
        if ($stdin === false) {
            $this->log('XƏTA: stdin açıla bilmir');
            return;
        }

        while (true) {
            $line = fgets($stdin);

            if ($line === false) {
                // EOF — host bağlantını bağladı
                $this->log('stdin EOF, dayanılır');
                break;
            }

            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $this->processLine($line);
        }

        fclose($stdin);
    }

    private function processLine(string $line): void
    {
        try {
            $message = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $this->writeError(null, -32700, 'Parse xətası: ' . $e->getMessage());
            return;
        }

        if (! isset($message['jsonrpc']) || $message['jsonrpc'] !== '2.0') {
            $this->writeError($message['id'] ?? null, -32600, 'Yanlış JSON-RPC versiyası');
            return;
        }

        $method = $message['method'] ?? '';
        $id     = $message['id'] ?? null;
        $params = $message['params'] ?? [];

        $handler = $this->handlers[$method] ?? null;

        if ($handler === null) {
            // id yoxdursa, təhlükəsiz şəkildə nəzərə almaya biləcəyimiz bildirishdir
            if ($id !== null) {
                $this->writeError($id, -32601, "Metod tapılmadı: {$method}");
            }
            return;
        }

        try {
            $result = $handler($params);

            // Bildirişlər (id yoxdur) cavab almır
            if ($id === null) {
                return;
            }

            $this->writeResponse($id, $result ?? new \stdClass());
        } catch (McpException $e) {
            $this->writeError($id, $e->getCode(), $e->getMessage(), $e->getData());
        } catch (Throwable $e) {
            $this->log("{$method}-də idarəsiz xəta: " . $e->getMessage());
            $this->writeError($id, -32603, 'Daxili xəta: ' . $e->getMessage());
        }
    }

    private function writeResponse(mixed $id, mixed $result): void
    {
        $this->writeLine(json_encode([
            'jsonrpc' => '2.0',
            'id'      => $id,
            'result'  => $result,
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    private function writeError(mixed $id, int $code, string $message, mixed $data = null): void
    {
        $error = ['code' => $code, 'message' => $message];
        if ($data !== null) {
            $error['data'] = $data;
        }

        $this->writeLine(json_encode([
            'jsonrpc' => '2.0',
            'id'      => $id,
            'error'   => $error,
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    private function writeLine(string $json): void
    {
        // stdout-a getməlidir, hər sətirdə bir JSON obyekti
        fwrite(STDOUT, $json . "\n");
        fflush(STDOUT);
    }

    private function log(string $message): void
    {
        // Yalnız stderr — heç vaxt stdout
        fwrite(STDERR, '[MCP] ' . $message . "\n");
    }
}
```

### 3. İstisna Tipi

```php
<?php

declare(strict_types=1);

namespace App\Mcp;

use RuntimeException;

final class McpException extends RuntimeException
{
    public function __construct(
        string $message,
        int $code = -32603,
        private readonly mixed $data = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getData(): mixed
    {
        return $this->data;
    }
}
```

### 4. Initialize Handler

```php
<?php

declare(strict_types=1);

namespace App\Mcp\Handlers;

final class InitializeHandler
{
    public function handle(array $params): array
    {
        // İstemcinin dəstəklədiklərini log et
        fwrite(STDERR, '[MCP] İstemci: ' . json_encode($params['clientInfo'] ?? []) . "\n");

        return [
            'protocolVersion' => '2025-03-26',
            'capabilities'    => [
                'tools'     => ['listChanged' => false],
                'resources' => ['subscribe' => false, 'listChanged' => false],
                'prompts'   => ['listChanged' => false],
            ],
            'serverInfo' => [
                'name'    => 'laravel-mcp',
                'version' => '1.0.0',
            ],
        ];
    }
}
```

### 5. Tools Handler — Eloquent Modellər, DB Sorğuları, Loglar, Routelar

```php
<?php

declare(strict_types=1);

namespace App\Mcp\Handlers;

use App\Mcp\McpException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class ToolsHandler
{
    /** @return array{tools: array<int, array<string, mixed>>} */
    public function list(array $params): array
    {
        return [
            'tools' => [
                $this->defineListModels(),
                $this->defineRunQuery(),
                $this->defineReadLogs(),
                $this->defineListRoutes(),
                $this->defineTableSchema(),
            ],
        ];
    }

    public function call(array $params): array
    {
        $name      = $params['name']      ?? throw new McpException('Tool adı çatışmır', -32602);
        $arguments = $params['arguments'] ?? [];

        return match ($name) {
            'list_models'  => $this->listModels($arguments),
            'run_query'    => $this->runQuery($arguments),
            'read_logs'    => $this->readLogs($arguments),
            'list_routes'  => $this->listRoutes($arguments),
            'table_schema' => $this->tableSchema($arguments),
            default        => throw new McpException("Naməlum tool: {$name}", -32601),
        };
    }

    // ─── Tool Tərifləri ─────────────────────────────────────────────

    private function defineListModels(): array
    {
        return [
            'name'        => 'list_models',
            'description' => 'Tətbiqdəki bütün Eloquent modelləri onların cədvəl adları və doldurula bilən sahələri ilə siyahıla.',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => new \stdClass(),
            ],
        ];
    }

    private function defineRunQuery(): array
    {
        return [
            'name'        => 'run_query',
            'description' => 'Tətbiq verilənlər bazasına qarşı yalnız oxuma SELECT sorğusu işlət. '
                           . 'Yalnız SELECT ifadələrinə icazə verilir.',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'sql'   => ['type' => 'string', 'description' => 'SELECT SQL sorğusu'],
                    'limit' => ['type' => 'integer', 'description' => 'Maks sətir sayı (standart 50, maks 500)'],
                ],
                'required' => ['sql'],
            ],
        ];
    }

    private function defineReadLogs(): array
    {
        return [
            'name'        => 'read_logs',
            'description' => 'Laravel tətbiq logunu (laravel.log) oxu. Son N sətiri qaytarır.',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'lines' => [
                        'type'        => 'integer',
                        'description' => 'Sondan qaytarılacaq log sətri sayı (standart: 100)',
                    ],
                    'level' => [
                        'type'        => 'string',
                        'enum'        => ['error', 'warning', 'info', 'debug', 'all'],
                        'description' => 'Log səviyyəsinə görə filtrle (standart: all)',
                    ],
                ],
            ],
        ];
    }

    private function defineListRoutes(): array
    {
        return [
            'name'        => 'list_routes',
            'description' => 'Bütün qeydiyyatdan keçmiş Laravel routelarını metod, URI, middleware və controller ilə siyahıla.',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'filter' => ['type' => 'string', 'description' => 'Routeları URI nümunəsinə görə filtrle'],
                    'method' => [
                        'type'  => 'string',
                        'enum'  => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'any'],
                    ],
                ],
            ],
        ];
    }

    private function defineTableSchema(): array
    {
        return [
            'name'        => 'table_schema',
            'description' => 'Xüsusi verilənlər bazası cədvəli üçün sütun sxemini al.',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'table' => ['type' => 'string', 'description' => 'Cədvəl adı'],
                ],
                'required' => ['table'],
            ],
        ];
    }

    // ─── Tool İmplementasiyaları ─────────────────────────────────────

    private function listModels(array $args): array
    {
        $modelPath = app_path('Models');
        $models    = [];

        if (! is_dir($modelPath)) {
            return $this->textResult('Models qovluğu tapılmadı: ' . $modelPath);
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($modelPath)
        );

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relative  = str_replace([$modelPath . '/', '.php'], '', $file->getPathname());
            $className = 'App\\Models\\' . str_replace('/', '\\', $relative);

            if (! class_exists($className)) {
                continue;
            }

            try {
                $reflection = new \ReflectionClass($className);

                if (
                    ! $reflection->isInstantiable()
                    || ! $reflection->isSubclassOf(\Illuminate\Database\Eloquent\Model::class)
                ) {
                    continue;
                }

                /** @var \Illuminate\Database\Eloquent\Model $instance */
                $instance = $reflection->newInstanceWithoutConstructor();

                $models[] = [
                    'class'      => $className,
                    'table'      => $instance->getTable(),
                    'fillable'   => $instance->getFillable(),
                    'hidden'     => $instance->getHidden(),
                    'timestamps' => $instance->usesTimestamps(),
                ];
            } catch (\Throwable) {
                // Reflection edilə bilməyən modelləri keç
            }
        }

        return $this->textResult(json_encode($models, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function runQuery(array $args): array
    {
        $sql   = trim($args['sql'] ?? '');
        $limit = min((int) ($args['limit'] ?? 50), 500);

        if ($sql === '') {
            return $this->errorResult('sql parametri tələb olunur');
        }

        // Ciddi yalnız oxuma tətbiqi
        $normalized = strtoupper(preg_replace('/\s+/', ' ', $sql));
        if (! str_starts_with($normalized, 'SELECT')) {
            return $this->errorResult('Yalnız SELECT ifadələrinə icazə verilir');
        }

        foreach (['INSERT', 'UPDATE', 'DELETE', 'DROP', 'TRUNCATE', 'ALTER', 'GRANT', 'REVOKE'] as $kw) {
            if (str_contains($normalized, $kw)) {
                return $this->errorResult("Qadağan olunmuş açar söz: {$kw}");
            }
        }

        // LIMIT yoxdursa əlavə et
        if (! str_contains($normalized, 'LIMIT')) {
            $sql = rtrim($sql, '; ') . " LIMIT {$limit}";
        }

        try {
            $start   = microtime(true);
            $results = DB::select(DB::raw($sql));
            $ms      = round((microtime(true) - $start) * 1000, 2);

            $output = [
                'rows'        => $results,
                'count'       => count($results),
                'execution_ms' => $ms,
            ];

            return $this->textResult(json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $e) {
            return $this->errorResult('Sorğu xətası: ' . $e->getMessage());
        }
    }

    private function readLogs(array $args): array
    {
        $lines    = min((int) ($args['lines'] ?? 100), 1000);
        $level    = $args['level'] ?? 'all';
        $logPath  = storage_path('logs/laravel.log');

        if (! file_exists($logPath)) {
            return $this->textResult('Log faylı tapılmadı: ' . $logPath);
        }

        // Əks fayl oxuyucu ilə son N sətiri effektiv oxu
        $content = $this->tailFile($logPath, $lines * 10); // artıq götür, sonra filtrle

        if ($level !== 'all') {
            $upperLevel = strtoupper($level);
            $filtered   = array_filter(
                explode("\n", $content),
                fn (string $line) => str_contains($line, $upperLevel)
            );
            $content = implode("\n", array_slice(array_values($filtered), -$lines));
        } else {
            $allLines = explode("\n", $content);
            $content  = implode("\n", array_slice($allLines, -$lines));
        }

        return $this->textResult($content);
    }

    private function listRoutes(array $args): array
    {
        $filter = $args['filter'] ?? '';
        $method = strtoupper($args['method'] ?? 'any');

        $routes = collect(Route::getRoutes())
            ->map(fn (\Illuminate\Routing\Route $route) => [
                'method'     => implode('|', $route->methods()),
                'uri'        => $route->uri(),
                'name'       => $route->getName() ?? '',
                'action'     => $route->getActionName(),
                'middleware' => $route->middleware(),
            ])
            ->when($filter !== '', fn ($c) => $c->filter(
                fn (array $r) => str_contains($r['uri'], $filter)
            ))
            ->when($method !== 'ANY', fn ($c) => $c->filter(
                fn (array $r) => str_contains($r['method'], $method)
            ))
            ->values()
            ->all();

        return $this->textResult(json_encode($routes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function tableSchema(array $args): array
    {
        $table = $args['table'] ?? '';

        if ($table === '') {
            return $this->errorResult('table parametri tələb olunur');
        }

        if (! Schema::hasTable($table)) {
            return $this->errorResult("'{$table}' cədvəli mövcud deyil");
        }

        $columns = Schema::getColumns($table);
        $indexes = Schema::getIndexes($table);

        $output = [
            'table'   => $table,
            'columns' => $columns,
            'indexes' => $indexes,
        ];

        return $this->textResult(json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    // ─── Köməkçilər ─────────────────────────────────────────────────

    /** Faylı tamamilə yükləmədən son N baytını oxu */
    private function tailFile(string $path, int $lines): string
    {
        $fp   = fopen($path, 'r');
        if ($fp === false) {
            return 'Log faylı açıla bilmir';
        }

        fseek($fp, 0, SEEK_END);
        $fileSize  = ftell($fp);
        $chunkSize = min(65536, $fileSize); // 64 KB chunk-lar
        $output    = '';
        $lineCount = 0;

        while ($fileSize > 0 && $lineCount < $lines) {
            $chunkSize = min($chunkSize, $fileSize);
            fseek($fp, -$chunkSize, SEEK_CUR);
            $chunk     = fread($fp, $chunkSize);
            $output    = $chunk . $output;
            $lineCount = substr_count($output, "\n");
            fseek($fp, -$chunkSize, SEEK_CUR);
            $fileSize -= $chunkSize;
        }

        fclose($fp);
        return $output;
    }

    /** @return array{content: array<int, array{type: string, text: string}>, isError: false} */
    private function textResult(string $text): array
    {
        return [
            'content' => [['type' => 'text', 'text' => $text]],
            'isError' => false,
        ];
    }

    /** @return array{content: array<int, array{type: string, text: string}>, isError: true} */
    private function errorResult(string $message): array
    {
        return [
            'content' => [['type' => 'text', 'text' => $message]],
            'isError' => true,
        ];
    }
}
```

### 6. Resources Handler

```php
<?php

declare(strict_types=1);

namespace App\Mcp\Handlers;

use Illuminate\Support\Facades\DB;
use App\Mcp\McpException;

final class ResourcesHandler
{
    public function list(array $params): array
    {
        return [
            'resources' => [
                [
                    'uri'         => 'laravel://config/app',
                    'name'        => 'Tətbiq Konfiqurasiyası',
                    'description' => 'Həssas olmayan Laravel tətbiq konfiqurasiyası',
                    'mimeType'    => 'application/json',
                ],
                [
                    'uri'         => 'laravel://db/tables',
                    'name'        => 'Verilənlər Bazası Cədvəlləri',
                    'description' => 'Bütün verilənlər bazası cədvəllərinin siyahısı',
                    'mimeType'    => 'application/json',
                ],
                [
                    'uri'         => 'laravel://env/info',
                    'name'        => 'Mühit Məlumatı',
                    'description' => 'Laravel mühiti və versiya məlumatı',
                    'mimeType'    => 'application/json',
                ],
            ],
        ];
    }

    public function read(array $params): array
    {
        $uri = $params['uri'] ?? throw new McpException('uri çatışmır', -32602);

        $content = match ($uri) {
            'laravel://config/app' => $this->readAppConfig(),
            'laravel://db/tables'  => $this->readDbTables(),
            'laravel://env/info'   => $this->readEnvInfo(),
            default                => throw new McpException("Naməlum resurs: {$uri}", -32602),
        };

        return [
            'contents' => [
                [
                    'uri'      => $uri,
                    'mimeType' => 'application/json',
                    'text'     => json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                ],
            ],
        ];
    }

    private function readAppConfig(): array
    {
        // Yalnız təhlükəsiz, həssas olmayan konfiqurasiyanı qaytarın
        return [
            'name'       => config('app.name'),
            'env'        => config('app.env'),
            'debug'      => config('app.debug'),
            'url'        => config('app.url'),
            'timezone'   => config('app.timezone'),
            'locale'     => config('app.locale'),
        ];
    }

    private function readDbTables(): array
    {
        $tables = DB::select("SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE()");
        return array_column($tables, 'table_name');
    }

    private function readEnvInfo(): array
    {
        return [
            'laravel_version' => app()->version(),
            'php_version'     => PHP_VERSION,
            'environment'     => app()->environment(),
            'debug_mode'      => config('app.debug', false),
            'timezone'        => config('app.timezone'),
            'cache_driver'    => config('cache.default'),
            'queue_driver'    => config('queue.default'),
            'db_driver'       => config('database.default'),
        ];
    }
}
```

### 7. Prompts Handler

```php
<?php

declare(strict_types=1);

namespace App\Mcp\Handlers;

use App\Mcp\McpException;

final class PromptsHandler
{
    public function list(array $params): array
    {
        return [
            'prompts' => [
                [
                    'name'        => 'debug_query',
                    'description' => 'Yavaş və ya uğursuz verilənlər bazası sorğusunu sazlamağa kömək et',
                    'arguments'   => [
                        ['name' => 'query', 'description' => 'Sazlanacaq SQL sorğusu', 'required' => true],
                        ['name' => 'error',  'description' => 'Xəta mesajı (varsa)', 'required' => false],
                    ],
                ],
                [
                    'name'        => 'explain_route',
                    'description' => 'Laravel route-nun nə etdiyini izah et və onu necə test edəcəyini göstər',
                    'arguments'   => [
                        ['name' => 'uri', 'description' => 'Route URI-si', 'required' => true],
                    ],
                ],
            ],
        ];
    }

    public function get(array $params): array
    {
        $name      = $params['name']      ?? throw new McpException('Prompt adı çatışmır', -32602);
        $arguments = $params['arguments'] ?? [];

        return match ($name) {
            'debug_query'  => $this->debugQueryPrompt($arguments),
            'explain_route' => $this->explainRoutePrompt($arguments),
            default         => throw new McpException("Naməlum prompt: {$name}", -32601),
        };
    }

    private function debugQueryPrompt(array $args): array
    {
        $query = $args['query'] ?? '(sorğu verilməyib)';
        $error = $args['error'] ?? null;

        $text = "Zəhmət olmasa bu SQL sorğusunu sazlamağa kömək edin:\n\n```sql\n{$query}\n```\n";
        if ($error) {
            $text .= "\nAlınan xəta: {$error}\n";
        }
        $text .= "\nVariasiyaları test etmək üçün run_query toolunu, sütun adlarını yoxlamaq üçün table_schema toolunu istifadə edin.";

        return [
            'description' => 'Verilənlər bazası sorğusunu sazla',
            'messages'    => [
                ['role' => 'user', 'content' => ['type' => 'text', 'text' => $text]],
            ],
        ];
    }

    private function explainRoutePrompt(array $args): array
    {
        $uri = $args['uri'] ?? '/';

        return [
            'description' => 'Laravel route-nu izah et',
            'messages'    => [
                [
                    'role'    => 'user',
                    'content' => [
                        'type' => 'text',
                        'text' => "'{$uri}' ilə uyğun gələn route-u tapmaq üçün list_routes toolunu istifadə edin, "
                                . "sonra nə etdiyini, hansı middleware istifadə etdiyini izah edin "
                                . "və curl ilə necə test edəcəyinə dair nümunə verin.",
                    ],
                ],
            ],
        ];
    }
}
```

### 8. Service Provider Qeydiyyatı

```php
// app/Providers/AppServiceProvider.php
use App\Mcp\McpServer;
use App\Mcp\Handlers\InitializeHandler;
use App\Mcp\Handlers\ToolsHandler;
use App\Mcp\Handlers\ResourcesHandler;
use App\Mcp\Handlers\PromptsHandler;

$this->app->singleton(McpServer::class, fn ($app) => new McpServer(
    initialize: $app->make(InitializeHandler::class),
    tools:      $app->make(ToolsHandler::class),
    resources:  $app->make(ResourcesHandler::class),
    prompts:    $app->make(PromptsHandler::class),
));
```

---

## Claude Desktop-da Qeydiyyat

`~/Library/Application Support/Claude/claude_desktop_config.json` (macOS):

```json
{
  "mcpServers": {
    "my-laravel-app": {
      "command": "php",
      "args": ["/var/www/my-app/artisan", "mcp:serve"],
      "env": {
        "APP_ENV":  "local",
        "APP_KEY":  "base64:...",
        "DB_HOST":  "127.0.0.1",
        "DB_PORT":  "3306",
        "DB_DATABASE": "my_app",
        "DB_USERNAME": "root",
        "DB_PASSWORD": "secret"
      }
    }
  }
}
```

Windows yolu: `%APPDATA%\Claude\claude_desktop_config.json`
Linux yolu: `~/.config/Claude/claude_desktop_config.json`

---

## Sazlama

Server qoşulmursa, Claude Desktop-un MCP loglarını yoxlayın:
- macOS: `~/Library/Logs/Claude/mcp-server-my-laravel-app.log`
- Linux: `~/.config/Claude/logs/mcp-server-my-laravel-app.log`

Ümumi problemlər:
1. **`artisan` icra edilə bilmir** — artisan-ın başına `#!/usr/bin/env php` əlavə edin və ya `"command": "php"` ilə `"args": ["/yol/to/artisan", "mcp:serve"]` istifadə edin
2. **Output buffering** — PHP stdout-u bufferə ala bilər; əmrdəki `ob_end_clean()` bunu idarə edir
3. **Verilənlər bazası qoşulmur** — env dəyişənlərinin `claude_desktop_config.json`-un `env` blokunda təyin edildiyinə əmin olun
4. **Yanlış iş qovluğu** — hər yerdə mütləq yollardan istifadə edin; `php artisan` cwd-ni layihə köküne dəyişir

---

## Praktik Tapşırıqlar

### Tapşırıq 1: İlk PHP MCP Tool

`mcp:serve` Artisan command-ı yaz. `get_user(id: int)` tool-unu implement et — Eloquent `User` modelindən data çəkir, JSON qaytarır. `claude_desktop_config.json`-a əlavə et. Claude Desktop-da `@` ilə server-i çağır, tool-u test et.

### Tapşırıq 2: Stdout Pollution Fix

Mövcud Laravel project-ə `mcp:serve` əmri əlavə etdikdə `ob_end_clean()` olmadan çalışdır. Laravel-in output buffering-inin JSON-RPC cavabını necə korlaya biləcəyini müşahidə et. `ob_end_clean()` + `ob_implicit_flush(true)` əlavə et, problemi həll et.

### Tapşırıq 3: Multi-Tool Server

Ən azı 3 tool olan MCP server qur: `search_products`, `get_order_status`, `list_customers`. Hər tool üçün JSON schema tərif et. Claude-a mürəkkəb sual ver ki, birdən çox tool çağırılsın. Tool call sequence-i log et.

---

## Əlaqəli Mövzular

- `02-mcp-resources-tools-prompts.md` — Tool, Resource, Prompt primitiv ayrımı
- `03-mcp-transports-deep.md` — stdio transport-un texniki detalları
- `09-mcp-security-patterns.md` — Input sanitization, permission model
- `11-mcp-for-company-laravel.md` — Şirkətin üçün production-grade MCP server
