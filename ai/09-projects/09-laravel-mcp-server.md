# Laravel MCP Server Qurulması (Lead)

Laravel tətbiqinizi Model Context Protocol serveri kimi ifşa edin ki, Claude Code və Claude Desktop birbaşa verilənlər bazanızı, marşrutlarınızı, loglarınızı və modellərini yoxlaya bilsin.

---

## Bu Sizə Nə Verir

Claude Desktop ilə qeydiyyatdan keçdikdən sonra Claude-dan soruşa bilərsiniz:
- "Middleware-i olmayan bütün marşrutları göstər"
- "Bu gün yaradılmış hesabları tapmaq üçün users cədvəlini sorğula"
- "Son bir saatda hansı xətalar baş verdi?"
- "orders cədvəlinin sütunları hansılardır?"
- "Tinker-də `User::where('active', false)->count()` icra et"

Claude tətbiqinizin vəziyyətinə real vaxt girişi əldə edir — statik təsvir deyil, canlı məlumatlar.

---

## Arxitektura

```
Claude Desktop / Claude Code
        │  stdin/stdout
        │  JSON-RPC 2.0
        ▼
php artisan mcp:serve
        │
        ▼
McpServer (stdin oxuyur, stdout yazır)
    ├── initialize idarəedicisi
    ├── tools/list idarəedicisi
    ├── tools/call idarəedicisi
    │     ├── list_tables
    │     ├── query_database (yalnız oxuma)
    │     ├── get_logs
    │     ├── list_routes
    │     ├── run_tinker_expression
    │     └── get_model_schema
    └── resources/list + resources/read idarəedicilər
          ├── config://app (həssas olmayan konfiqurasiya)
          └── logs://recent (son 100 xəta sətri)
```

MCP, stdin/stdout üzərindən JSON-RPC 2.0 istifadə edir. Hər mesaj yeni sətrlə bitən tək JSON sətridir. Server bir sorğu oxuyur, bir cavab yazır və döngüyə davam edir.

---

## Quraşdırma

```bash
# Artisan əmrini tətbiqinizə əlavə etməklə quraşdırın
# Xarici MCP kitabxanası lazım deyil — protokolu birbaşa tətbiq edirik

# İsteğe bağlı: log analizi üçün smalot/pdfparser quraşdırın
composer require smalot/pdfparser --dev
```

---

## Artisan Əmri

```php
// app/Console/Commands/McpServe.php
<?php

namespace App\Console\Commands;

use App\Services\Mcp\McpServer;
use Illuminate\Console\Command;

class McpServe extends Command
{
    protected $signature = 'mcp:serve {--debug : Bütün mesajları stderr-ə qeyd et}';
    protected $description = 'Model Context Protocol stdio serveri kimi işlət';

    public function handle(McpServer $server): int
    {
        // MCP serverlər stdin/stdout üzərindən əlaqə qurur.
        // stderr sazlama qeydi üçün təhlükəsizdir (Claude Desktop onu nəzərə almır).
        if ($this->option('debug')) {
            $server->enableDebug();
        }

        $this->info('MCP server stdin/stdout üzərindən başlayır', 'vv');
        $server->run();

        return Command::SUCCESS;
    }
}
```

---

## MCP Server Əsası

```php
// app/Services/Mcp/McpServer.php
<?php

namespace App\Services\Mcp;

use App\Services\Mcp\Tools\ToolRegistry;
use App\Services\Mcp\Resources\ResourceRegistry;

/**
 * stdin/stdout üzərindən JSON-RPC 2.0 serveri.
 *
 * Protokol istinadı: https://modelcontextprotocol.io/specification
 *
 * Mesaj çərçivələmə: Hər mesaj tək sətirdə JSON obyektidir,
 * \n ilə bitir. Content-Length başlıqları yoxdur (bu HTTP nəqlidir).
 */
class McpServer
{
    private bool $debug = false;
    private string $serverName = 'laravel-mcp-server';
    private string $serverVersion = '1.0.0';

    public function __construct(
        private readonly ToolRegistry $toolRegistry,
        private readonly ResourceRegistry $resourceRegistry,
    ) {}

    public function enableDebug(): void
    {
        $this->debug = true;
    }

    public function run(): void
    {
        // Sətir-sətir oxumaq üçün stdin-i bloklanmayan rejimdə qur
        $stdin = fopen('php://stdin', 'r');
        $stdout = fopen('php://stdout', 'w');

        // Çıxış bufferini söndür — cavabların dərhal görünməsi lazımdır
        stream_set_blocking($stdin, true);

        while (($line = fgets($stdin)) !== false) {
            $line = trim($line);
            if (empty($line)) continue;

            $this->debugLog("← " . $line);

            $request = json_decode($line, true);
            if ($request === null) {
                $this->writeError($stdout, null, -32700, 'Analiz xətası');
                continue;
            }

            $response = $this->handleRequest($request);

            if ($response !== null) {
                $responseJson = json_encode($response);
                $this->debugLog("→ " . $responseJson);
                fwrite($stdout, $responseJson . "\n");
                fflush($stdout);
            }
        }

        fclose($stdin);
        fclose($stdout);
    }

    private function handleRequest(array $request): ?array
    {
        $id = $request['id'] ?? null;
        $method = $request['method'] ?? null;
        $params = $request['params'] ?? [];

        // Bildirişlər (id yoxdur) cavab almır
        $isNotification = !array_key_exists('id', $request);

        try {
            $result = match ($method) {
                'initialize' => $this->handleInitialize($params),
                'notifications/initialized' => null, // Müştəri hazır olduğunu bildirir
                'ping' => ['pong' => true],

                // Alətlər
                'tools/list' => $this->handleToolsList(),
                'tools/call' => $this->handleToolsCall($params),

                // Resurslar
                'resources/list' => $this->handleResourcesList(),
                'resources/read' => $this->handleResourcesRead($params),

                // Prompt-lar (isteğe bağlı)
                'prompts/list' => ['prompts' => []],

                default => throw new McpException("Metod tapılmadı: {$method}", -32601),
            };

            if ($isNotification || $result === null) {
                return null;
            }

            return [
                'jsonrpc' => '2.0',
                'id' => $id,
                'result' => $result,
            ];

        } catch (McpException $e) {
            if ($isNotification) return null;
            return [
                'jsonrpc' => '2.0',
                'id' => $id,
                'error' => [
                    'code' => $e->getCode(),
                    'message' => $e->getMessage(),
                ],
            ];
        } catch (\Exception $e) {
            $this->debugLog("Xəta: " . $e->getMessage());
            if ($isNotification) return null;
            return [
                'jsonrpc' => '2.0',
                'id' => $id,
                'error' => [
                    'code' => -32603,
                    'message' => 'Daxili xəta: ' . $e->getMessage(),
                ],
            ];
        }
    }

    private function handleInitialize(array $params): array
    {
        return [
            'protocolVersion' => '2024-11-05',
            'capabilities' => [
                'tools' => ['listChanged' => false],
                'resources' => ['listChanged' => false, 'subscribe' => false],
                'prompts' => [],
            ],
            'serverInfo' => [
                'name' => $this->serverName,
                'version' => $this->serverVersion,
            ],
        ];
    }

    private function handleToolsList(): array
    {
        return ['tools' => $this->toolRegistry->list()];
    }

    private function handleToolsCall(array $params): array
    {
        $name = $params['name'] ?? throw new McpException('Alət adı çatışmır', -32602);
        $arguments = $params['arguments'] ?? [];

        $result = $this->toolRegistry->call($name, $arguments);

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => $result,
                ],
            ],
        ];
    }

    private function handleResourcesList(): array
    {
        return ['resources' => $this->resourceRegistry->list()];
    }

    private function handleResourcesRead(array $params): array
    {
        $uri = $params['uri'] ?? throw new McpException('URI çatışmır', -32602);
        $content = $this->resourceRegistry->read($uri);

        return [
            'contents' => [
                [
                    'uri' => $uri,
                    'mimeType' => 'text/plain',
                    'text' => $content,
                ],
            ],
        ];
    }

    private function writeError($stdout, $id, int $code, string $message): void
    {
        $response = json_encode([
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => ['code' => $code, 'message' => $message],
        ]);
        fwrite($stdout, $response . "\n");
        fflush($stdout);
    }

    private function debugLog(string $message): void
    {
        if ($this->debug) {
            fwrite(STDERR, "[MCP] {$message}\n");
        }
    }
}
```

```php
// app/Services/Mcp/McpException.php
<?php

namespace App\Services\Mcp;

class McpException extends \RuntimeException
{
    public function __construct(string $message, int $code = -32603)
    {
        parent::__construct($message, $code);
    }
}
```

---

## Alət Reyestri

```php
// app/Services/Mcp/Tools/ToolRegistry.php
<?php

namespace App\Services\Mcp\Tools;

use App\Services\Mcp\McpException;

class ToolRegistry
{
    /** @var McpToolInterface[] */
    private array $tools = [];

    public function register(McpToolInterface $tool): void
    {
        $this->tools[$tool->getName()] = $tool;
    }

    public function list(): array
    {
        return array_map(fn($t) => [
            'name' => $t->getName(),
            'description' => $t->getDescription(),
            'inputSchema' => $t->getInputSchema(),
        ], array_values($this->tools));
    }

    public function call(string $name, array $arguments): string
    {
        if (!isset($this->tools[$name])) {
            throw new McpException("Naməlum alət: {$name}", -32602);
        }

        return $this->tools[$name]->execute($arguments);
    }
}
```

```php
// app/Services/Mcp/Tools/McpToolInterface.php
<?php

namespace App\Services\Mcp\Tools;

interface McpToolInterface
{
    public function getName(): string;
    public function getDescription(): string;
    public function getInputSchema(): array;
    public function execute(array $arguments): string; // Claude üçün mətn qaytarır
}
```

---

## Alət Tətbiqləri

```php
// app/Services/Mcp/Tools/ListTablesTool.php
<?php

namespace App\Services\Mcp\Tools;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ListTablesTool implements McpToolInterface
{
    public function getName(): string { return 'list_tables'; }

    public function getDescription(): string
    {
        return 'Bütün verilənlər bazası cədvəllərini sətir sayları və sütun adları ilə siyahıla.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'include_system' => [
                    'type' => 'boolean',
                    'description' => 'Sistem/miqrasiya cədvəllərini daxil et (standart false)',
                    'default' => false,
                ],
            ],
        ];
    }

    public function execute(array $arguments): string
    {
        $includeSystem = $arguments['include_system'] ?? false;

        $systemTables = ['migrations', 'failed_jobs', 'password_reset_tokens', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches'];

        // Bütün cədvəlləri al
        $tables = DB::select('SELECT tablename FROM pg_tables WHERE schemaname = \'public\' ORDER BY tablename');

        $output = [];
        foreach ($tables as $table) {
            $tableName = $table->tablename;

            if (!$includeSystem && in_array($tableName, $systemTables)) continue;

            // Sətir sayını al
            try {
                $count = DB::table($tableName)->count();
            } catch (\Exception $e) {
                $count = '?';
            }

            // Sütunları al
            $columns = Schema::getColumnListing($tableName);

            $output[] = "## {$tableName} ({$count} sətir)";
            $output[] = "Sütunlar: " . implode(', ', $columns);
            $output[] = '';
        }

        return implode("\n", $output) ?: 'Cədvəl tapılmadı.';
    }
}
```

```php
// app/Services/Mcp/Tools/QueryDatabaseTool.php
<?php

namespace App\Services\Mcp\Tools;

use Illuminate\Support\Facades\DB;

/**
 * TƏHLÜKƏSİZLİK: Bu alət verilənlər bazanıza qarşı SQL icra edir.
 * Yalnız oxuma rejimini aşağıdakılarla tətbiq edir:
 * 1. Yalnız SELECT ifadələrinə icazə verir (reqex yoxlaması)
 * 2. Təhlükəli açar sözləri bloklayır
 * 3. Nəticələri 50 sətirə məhdudlaşdırır
 *
 * Bu aləti MCP serverdə autentifikasiya olmadan heç vaxt ifşa etməyin.
 */
class QueryDatabaseTool implements McpToolInterface
{
    // Heç vaxt sorğulana bilməyən cədvəllər (həssas məlumatlar)
    private array $blockedTables = [
        'users',           // Şəxsi məlumat — lazım olarsa müəyyən sütunları istifadə et
        'password_resets', 'personal_access_tokens',
        'oauth_access_tokens', 'oauth_refresh_tokens',
    ];

    public function getName(): string { return 'query_database'; }

    public function getDescription(): string
    {
        return 'Verilənlər bazasına qarşı yalnız oxuma SELECT sorğusu icra et. ' .
               'Maksimum 50 sətir qaytarır. ' .
               'Bloklanan cədvəllər: ' . implode(', ', $this->blockedTables) . '. ' .
               'Mövcud cədvəlləri görmək üçün list_tables istifadə et.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'sql' => [
                    'type' => 'string',
                    'description' => 'Bir SELECT SQL sorğusu',
                ],
            ],
            'required' => ['sql'],
        ];
    }

    public function execute(array $arguments): string
    {
        $sql = trim($arguments['sql'] ?? '');

        if (empty($sql)) {
            return 'Xəta: SQL sorğusu tələb olunur';
        }

        // Yalnız SELECT-i yoxla
        if (!preg_match('/^\s*SELECT\s/i', $sql)) {
            return 'Xəta: Yalnız SELECT ifadələrinə icazə verilir.';
        }

        // Təhlükəli açar sözləri blokla
        $forbidden = ['INSERT', 'UPDATE', 'DELETE', 'DROP', 'ALTER', 'CREATE', 'TRUNCATE', 'EXEC', 'EXECUTE', 'CALL'];
        foreach ($forbidden as $kw) {
            if (preg_match('/\b' . $kw . '\b/i', $sql)) {
                return "Xəta: '{$kw}' açar sözünə icazə verilmir.";
            }
        }

        // Həssas cədvəlləri blokla
        foreach ($this->blockedTables as $table) {
            if (preg_match('/\b' . preg_quote($table, '/') . '\b/i', $sql)) {
                return "Xəta: '{$table}' cədvəli təhlükəsizlik səbəbilə bloklanıb.";
            }
        }

        // LIMIT yoxdursa əlavə et
        if (!preg_match('/\bLIMIT\s+\d+/i', $sql)) {
            $sql .= ' LIMIT 50';
        }

        try {
            $results = DB::select($sql);

            if (empty($results)) {
                return 'Sorğu 0 sətir qaytardı.';
            }

            // Markdown cədvəli kimi formatla
            $rows = array_map(fn($r) => (array) $r, $results);
            $headers = array_keys($rows[0]);

            $output = '| ' . implode(' | ', $headers) . " |\n";
            $output .= '| ' . implode(' | ', array_fill(0, count($headers), '---')) . " |\n";

            foreach ($rows as $row) {
                $cells = array_map(fn($v) => is_null($v) ? 'NULL' : str_replace('|', '\\|', (string) $v), $row);
                $output .= '| ' . implode(' | ', $cells) . " |\n";
            }

            $count = count($rows);
            $output .= "\n{$count} sətir qaytarıldı.";
            if ($count === 50) {
                $output .= " (Nəticələr 50 sətirə məhdudlaşdırıldı)";
            }

            return $output;

        } catch (\Exception $e) {
            return 'Sorğu xətası: ' . $e->getMessage();
        }
    }
}
```

```php
// app/Services/Mcp/Tools/GetLogsTool.php
<?php

namespace App\Services\Mcp\Tools;

use Illuminate\Support\Facades\Storage;

class GetLogsTool implements McpToolInterface
{
    public function getName(): string { return 'get_logs'; }

    public function getDescription(): string
    {
        return 'Son tətbiq log qeydlərini əldə et. Səviyyəyə (error, warning, info) görə filtrələ və ya sətir axtarışı et.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'level' => [
                    'type' => 'string',
                    'enum' => ['error', 'warning', 'info', 'debug', 'all'],
                    'description' => 'Filtrələnəcək log səviyyəsi (standart: error)',
                    'default' => 'error',
                ],
                'lines' => [
                    'type' => 'integer',
                    'description' => 'Qaytarılacaq sətir sayı (standart 50, maks 200)',
                    'default' => 50,
                ],
                'search' => [
                    'type' => 'string',
                    'description' => 'Log qeydlərində axtarılacaq sətir (isteğe bağlı)',
                ],
            ],
        ];
    }

    public function execute(array $arguments): string
    {
        $level = strtolower($arguments['level'] ?? 'error');
        $lines = min((int) ($arguments['lines'] ?? 50), 200);
        $search = $arguments['search'] ?? null;

        $logPath = storage_path('logs/laravel.log');

        if (!file_exists($logPath)) {
            return 'Log faylı tapılmadı: ' . $logPath;
        }

        // Çox sətirli qeydləri nəzərə almaq üçün son N*10 sətri oxu, sonra filtrələ
        $allLines = $this->tailFile($logPath, $lines * 10);

        // Log qeydlərini analiz et (Laravel istifadə edir: [tarix] kanal.SƏVİYYƏ: mesaj)
        $pattern = '/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] \w+\.(\w+): (.+)/';
        $entries = [];
        $currentEntry = '';

        foreach ($allLines as $line) {
            if (preg_match($pattern, $line)) {
                if ($currentEntry) {
                    $entries[] = $currentEntry;
                }
                $currentEntry = $line;
            } else {
                $currentEntry .= "\n" . $line;
            }
        }
        if ($currentEntry) $entries[] = $currentEntry;

        // Səviyyəyə görə filtrələ
        if ($level !== 'all') {
            $entries = array_filter($entries, function ($entry) use ($level, $pattern) {
                preg_match($pattern, $entry, $matches);
                return strtolower($matches[2] ?? '') === $level;
            });
        }

        // Axtarış sətrinə görə filtrələ
        if ($search) {
            $entries = array_filter($entries, fn($entry) => stripos($entry, $search) !== false);
        }

        // Son N qeydi götür
        $entries = array_values(array_slice($entries, -$lines));

        if (empty($entries)) {
            return "{$level} log qeydi tapılmadı" . ($search ? " '{$search}' üçün" : '');
        }

        return implode("\n---\n", $entries);
    }

    private function tailFile(string $path, int $lines): array
    {
        $file = new \SplFileObject($path, 'r');
        $file->seek(PHP_INT_MAX);
        $totalLines = $file->key();

        $startLine = max(0, $totalLines - $lines);
        $file->seek($startLine);

        $result = [];
        while (!$file->eof()) {
            $line = rtrim($file->current(), "\r\n");
            if ($line !== '') $result[] = $line;
            $file->next();
        }

        return $result;
    }
}
```

```php
// app/Services/Mcp/Tools/ListRoutesTool.php
<?php

namespace App\Services\Mcp\Tools;

use Illuminate\Support\Facades\Route;

class ListRoutesTool implements McpToolInterface
{
    public function getName(): string { return 'list_routes'; }

    public function getDescription(): string
    {
        return 'Bütün qeydiyyatdan keçmiş marşrutları metodları, URI-ləri, middleware-ləri və kontrollerlər ilə siyahıla.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'filter' => [
                    'type' => 'string',
                    'description' => 'URI nümunəsinə görə filtrələ (məs. "api/", "admin")',
                ],
                'method' => [
                    'type' => 'string',
                    'description' => 'HTTP metoduna görə filtrələ (GET, POST, və s.)',
                ],
                'no_middleware' => [
                    'type' => 'boolean',
                    'description' => 'Yalnız auth middleware-i olmayan marşrutları göstər',
                    'default' => false,
                ],
            ],
        ];
    }

    public function execute(array $arguments): string
    {
        $filter = $arguments['filter'] ?? null;
        $method = strtoupper($arguments['method'] ?? '');
        $noMiddleware = $arguments['no_middleware'] ?? false;

        $routes = Route::getRoutes();
        $output = [];

        foreach ($routes as $route) {
            $uri = $route->uri();
            $methods = $route->methods();
            $action = $route->getActionName();
            $middleware = $route->gatherMiddleware();

            // Filtrləri tətbiq et
            if ($filter && stripos($uri, $filter) === false) continue;
            if ($method && !in_array($method, $methods)) continue;
            if ($noMiddleware) {
                $hasAuth = collect($middleware)->some(fn($m) => str_contains($m, 'auth'));
                if ($hasAuth) continue;
            }

            // Əməliyyat adını sadələşdir
            if (str_contains($action, 'Closure')) {
                $action = 'Closure';
            }

            $middlewareStr = implode(', ', $middleware) ?: 'yoxdur';

            $output[] = sprintf(
                "%-8s %-50s %-60s %s",
                implode('|', $methods),
                $uri,
                $action,
                $middlewareStr
            );
        }

        if (empty($output)) {
            return 'Meyarlara uyğun marşrut tapılmadı.';
        }

        $header = sprintf("%-8s %-50s %-60s %s", 'METOD', 'URI', 'ƏMƏLİYYAT', 'MIDDLEWARE');
        $separator = str_repeat('-', 180);

        return $header . "\n" . $separator . "\n" . implode("\n", $output);
    }
}
```

```php
// app/Services/Mcp/Tools/RunTinkerTool.php
<?php

namespace App\Services\Mcp\Tools;

use Illuminate\Support\Facades\Artisan;

/**
 * TƏHLÜKƏLİ ZONA: Bu alət ixtiyari PHP kodu icra edə bilər.
 *
 * Yalnız aşağıdakı hallarda aktivləşdirin:
 * 1. MCP server yalnız sizə əlçatandır (yerli inkişaf)
 * 2. Hansı ifadələri icra etdiyinizi başa düşürsünüz
 * 3. İfadə ağ siyahısını nəzərdən keçirmisiniz
 *
 * Reyestrdən çıxararaq istehsalda söndürün.
 */
class RunTinkerTool implements McpToolInterface
{
    // Kontekstdən asılı olmayaraq bloklanan nümunələr
    private array $blockedPatterns = [
        '/\bexec\b/', '/\bshell_exec\b/', '/\bsystem\b/',
        '/\bpassthru\b/', '/\bpopen\b/', '/\bproc_open\b/',
        '/\beval\b/', '/\bfile_put_contents\b/', '/\bunlink\b/',
        '/\brm\b/', '/`/', // geri dırnaq operatoru
        '/\benv\(\s*[\'"].*SECRET/i', // sirlər üçün env() çağırışları
        '/\benv\(\s*[\'"].*PASSWORD/i',
        '/\benv\(\s*[\'"].*KEY/i',
    ];

    public function getName(): string { return 'run_tinker_expression'; }

    public function getDescription(): string
    {
        return 'Laravel tətbiqinin kontekstindən istifadə edərək PHP ifadəsini qiymətləndir. ' .
               'Bunlar üçün yaxşıdır: qeydlər saymaq, model atributlarını yoxlamaq, konfiqurasiya dəyərlərini yoxlamaq. ' .
               'Nəticənin var_export-unu qaytarır. ' .
               'MƏHDUDLAŞDIRILMIŞ: Fayl əməliyyatları, exec, shell_exec və sirr girişi bloklanıb.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'expression' => [
                    'type' => 'string',
                    'description' => 'Qiymətləndirilecək PHP ifadəsi (<?php olmadan). Məs.: "User::count()"',
                ],
            ],
            'required' => ['expression'],
        ];
    }

    public function execute(array $arguments): string
    {
        $expression = trim($arguments['expression'] ?? '');

        if (empty($expression)) {
            return 'Xəta: ifadə tələb olunur';
        }

        // Təhlükəsizlik yoxlaması
        foreach ($this->blockedPatterns as $pattern) {
            if (preg_match($pattern, $expression)) {
                return 'Xəta: Bu ifadə bloklanan nümunə ehtiva edir.';
            }
        }

        // Sandboxed eval-da icra et
        try {
            // Çıxışı tut
            ob_start();
            $result = eval("return ({$expression});");
            $output = ob_get_clean();

            $formatted = var_export($result, true);

            $response = "Nəticə: {$formatted}";
            if ($output) {
                $response .= "\n\nÇıxış:\n{$output}";
            }

            return $response;

        } catch (\ParseError $e) {
            return 'Analiz xətası: ' . $e->getMessage();
        } catch (\Exception $e) {
            return 'Xəta: ' . $e->getMessage();
        }
    }
}
```

```php
// app/Services/Mcp/Tools/GetModelSchemaTool.php
<?php

namespace App\Services\Mcp\Tools;

use Illuminate\Support\Facades\Schema;

class GetModelSchemaTool implements McpToolInterface
{
    public function getName(): string { return 'get_model_schema'; }

    public function getDescription(): string
    {
        return 'Sütun növləri, null ola bilmə, standartlar və indekslər daxil olmaqla verilənlər bazası cədvəli üçün ətraflı sxem məlumatı əldə et.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'table' => [
                    'type' => 'string',
                    'description' => 'Cədvəl adı (məs. "users", "orders")',
                ],
            ],
            'required' => ['table'],
        ];
    }

    public function execute(array $arguments): string
    {
        $table = trim($arguments['table'] ?? '');

        if (empty($table)) {
            return 'Xəta: cədvəl adı tələb olunur';
        }

        if (!Schema::hasTable($table)) {
            return "'{$table}' cədvəli mövcud deyil. Mövcud cədvəlləri görmək üçün list_tables istifadə et.";
        }

        $columns = Schema::getColumns($table);
        $indexes = Schema::getIndexes($table);
        $foreignKeys = Schema::getForeignKeys($table);

        $output = "## Cədvəl: {$table}\n\n";

        // Sütunlar
        $output .= "### Sütunlar\n\n";
        $output .= "| Sütun | Növ | Null ola bilər | Standart |\n";
        $output .= "|-------|-----|----------------|----------|\n";

        foreach ($columns as $col) {
            $nullable = $col['nullable'] ? 'BƏLİ' : 'XEYİR';
            $default = $col['default'] ?? 'NULL';
            $output .= "| {$col['name']} | {$col['type_name']} | {$nullable} | {$default} |\n";
        }

        // İndekslər
        if (!empty($indexes)) {
            $output .= "\n### İndekslər\n\n";
            foreach ($indexes as $index) {
                $type = $index['primary'] ? 'PRİMARY' : ($index['unique'] ? 'UNIQUE' : 'INDEX');
                $cols = implode(', ', $index['columns']);
                $output .= "- [{$type}] {$index['name']}: ({$cols})\n";
            }
        }

        // Xarici açarlar
        if (!empty($foreignKeys)) {
            $output .= "\n### Xarici Açarlar\n\n";
            foreach ($foreignKeys as $fk) {
                $localCols = implode(', ', $fk['columns']);
                $foreignCols = implode(', ', $fk['foreign_columns']);
                $output .= "- {$localCols} → {$fk['foreign_table']}.{$foreignCols}";
                $output .= " (ON DELETE {$fk['on_delete']}, ON UPDATE {$fk['on_update']})\n";
            }
        }

        return $output;
    }
}
```

---

## Resurs Reyestri

```php
// app/Services/Mcp/Resources/ResourceRegistry.php
<?php

namespace App\Services\Mcp\Resources;

use App\Services\Mcp\McpException;

class ResourceRegistry
{
    private array $resources = [];

    public function register(string $uri, string $name, string $description, callable $reader): void
    {
        $this->resources[$uri] = compact('uri', 'name', 'description', 'reader');
    }

    public function list(): array
    {
        return array_values(array_map(fn($r) => [
            'uri' => $r['uri'],
            'name' => $r['name'],
            'description' => $r['description'],
            'mimeType' => 'text/plain',
        ], $this->resources));
    }

    public function read(string $uri): string
    {
        if (!isset($this->resources[$uri])) {
            throw new McpException("Resurs tapılmadı: {$uri}", -32602);
        }

        return ($this->resources[$uri]['reader'])();
    }
}
```

---

## Service Provider

```php
// app/Providers/McpServiceProvider.php
<?php

namespace App\Providers;

use App\Services\Mcp\McpServer;
use App\Services\Mcp\Tools\ToolRegistry;
use App\Services\Mcp\Tools\ListTablesTool;
use App\Services\Mcp\Tools\QueryDatabaseTool;
use App\Services\Mcp\Tools\GetLogsTool;
use App\Services\Mcp\Tools\ListRoutesTool;
use App\Services\Mcp\Tools\RunTinkerTool;
use App\Services\Mcp\Tools\GetModelSchemaTool;
use App\Services\Mcp\Resources\ResourceRegistry;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;

class McpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ToolRegistry::class, function () {
            $registry = new ToolRegistry();

            $registry->register(new ListTablesTool());
            $registry->register(new QueryDatabaseTool());
            $registry->register(new GetLogsTool());
            $registry->register(new ListRoutesTool());
            $registry->register(new GetModelSchemaTool());

            // Tinker-i yalnız istehsal olmayan mühitlərdə qeydiyyatdan keçir
            if (!app()->isProduction()) {
                $registry->register(new RunTinkerTool());
            }

            return $registry;
        });

        $this->app->singleton(ResourceRegistry::class, function () {
            $registry = new ResourceRegistry();

            // Həssas olmayan tətbiq konfiqurasiyası
            $registry->register(
                uri: 'config://app',
                name: 'Tətbiq Konfiqurasiyası',
                description: 'Əsas tətbiq konfiqurasiya dəyərləri (həssas deyil)',
                reader: function () {
                    return json_encode([
                        'app_name' => config('app.name'),
                        'app_env' => config('app.env'),
                        'app_debug' => config('app.debug'),
                        'php_version' => PHP_VERSION,
                        'laravel_version' => app()->version(),
                        'timezone' => config('app.timezone'),
                        'locale' => config('app.locale'),
                        'database_driver' => config('database.default'),
                        'cache_driver' => config('cache.default'),
                        'queue_driver' => config('queue.default'),
                    ], JSON_PRETTY_PRINT);
                },
            );

            // Son xəta logları (xülasə)
            $registry->register(
                uri: 'logs://recent',
                name: 'Son Xəta Logları',
                description: 'Son 100 xəta səviyyəli log qeydi',
                reader: function () {
                    $logPath = storage_path('logs/laravel.log');
                    if (!file_exists($logPath)) return 'Log faylı tapılmadı.';

                    $lines = file($logPath);
                    $errors = array_filter($lines, fn($l) => stripos($l, '.ERROR:') !== false);
                    $recent = array_slice($errors, -100);

                    return implode('', $recent) ?: 'Son xəta yoxdur.';
                },
            );

            // Verilənlər bazası statistikası
            $registry->register(
                uri: 'database://stats',
                name: 'Verilənlər Bazası Statistikası',
                description: 'Cədvəl ölçüləri və sətir sayları',
                reader: function () {
                    try {
                        $tables = DB::select("
                            SELECT
                                relname as table_name,
                                n_live_tup as row_count,
                                pg_size_pretty(pg_total_relation_size(relid)) as total_size
                            FROM pg_stat_user_tables
                            ORDER BY n_live_tup DESC
                        ");

                        $output = "Cədvəl Statistikası:\n\n";
                        foreach ($tables as $t) {
                            $output .= sprintf("%-40s %10s sətir  %s\n",
                                $t->table_name, number_format($t->row_count), $t->total_size);
                        }
                        return $output;
                    } catch (\Exception $e) {
                        return 'Statistika əldə edilə bilmədi: ' . $e->getMessage();
                    }
                },
            );

            return $registry;
        });

        $this->app->singleton(McpServer::class, function ($app) {
            return new McpServer(
                $app->make(ToolRegistry::class),
                $app->make(ResourceRegistry::class),
            );
        });
    }
}
```

---

## Claude Desktop ilə Qeydiyyat

`~/.config/claude/claude_desktop_config.json` faylını yaradın və ya redaktə edin (macOS: `~/Library/Application Support/Claude/claude_desktop_config.json`):

```json
{
  "mcpServers": {
    "my-laravel-app": {
      "command": "php",
      "args": ["/path/to/your/laravel/app/artisan", "mcp:serve"],
      "env": {
        "APP_ENV": "local"
      }
    }
  }
}
```

Claude Desktop-u yenidən başladın. Alətlər interfeysdə görünməlidir.

### Claude Code üçün

```bash
# Layihənizin .mcp.json faylına əlavə edin
cat > .mcp.json << 'EOF'
{
  "mcpServers": {
    "laravel": {
      "command": "php",
      "args": ["artisan", "mcp:serve"]
    }
  }
}
EOF
```

---

## MCP Serverini Əl ilə Test Etmə

JSON-RPC mesajlarını birbaşa pipe edərək serveri test edə bilərsiniz:

```bash
# initialize-i test et
echo '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2024-11-05","capabilities":{},"clientInfo":{"name":"test","version":"1.0"}}}' \
  | php artisan mcp:serve

# tools/list-i test et
echo '{"jsonrpc":"2.0","id":1,"method":"tools/list","params":{}}' \
  | php artisan mcp:serve

# Alət çağırışını test et
echo '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"list_tables","arguments":{}}}' \
  | php artisan mcp:serve
```

---

## Təhlükəsizlik Mülahizələri

### Heç vaxt ifşa etmə

```php
// BUNLARI heç vaxt ifşa etməyin:
// - Etimadnamələr, API açarları, .env dəyərləri
// - İstifadəçi şifrələri, tokenlər, sessiyalar
// - Ödəniş məlumatları (PCI uyğunluğu)
// - Sağlamlıq məlumatları (HIPAA uyğunluğu)
// - Verilənlər bazasına yazma imkanı
// - Fayl sisteminə yazma girişi
// - Şəbəkə girişi (alətlərdə HTTP müştərisi yoxdur)
```

### Müəyyən istifadəçilərə məhdudlaşdırma

MCP server yerli proses kimi işlədiyindən onu işlədən istifadəçinin icazələri ilə işləyir. Girişi məhdudlaşdırmaq istədiyinizdə:

```php
// app/Console/Commands/McpServe.php — auth yoxlaması əlavə et
public function handle(McpServer $server): int
{
    // Yalnız müəyyən mühitə icazə ver
    if (app()->isProduction()) {
        $this->error('MCP server istehsalda işlədilə bilməz.');
        return Command::FAILURE;
    }

    // İsteğe bağlı: env vasitəsilə gizli token tələb et
    $expectedToken = config('mcp.access_token');
    if ($expectedToken) {
        $providedToken = $this->option('token') ?? env('MCP_ACCESS_TOKEN');
        if ($providedToken !== $expectedToken) {
            $this->error('Yanlış MCP giriş tokeni.');
            return Command::FAILURE;
        }
    }

    $server->run();
    return Command::SUCCESS;
}
```

### Tinker Alətinin Riski

`RunTinkerTool` ixtiyari PHP icra edir. Onu yalnız aşağıdakılarla daxil edin:
- Yerli inkişaf (`!app()->isProduction()` yoxlaması tətbiq edilir)
- Claude Desktop-a yalnız sizin girişiniz olan şəxsi quraşdırmalar

Kimsə Claude Desktop girişi ilə Claude-a zərərli prompt göndərərsə, Claude Tinker-i zərərli kodla çağıra bilər. Bloklanan nümunələr kömək edir, lakin mükəmməl deyil.

---

## Necə Genişləndirmək Olar

**Xüsusi alət əlavə et:**

```php
class GetPendingJobsTool implements McpToolInterface
{
    public function getName(): string { return 'get_pending_jobs'; }
    public function getDescription(): string { return 'Növbəyə alınmış tapşırıqların sayını və təfərrüatlarını əldə et'; }
    public function getInputSchema(): array { return ['type' => 'object', 'properties' => []]; }

    public function execute(array $arguments): string
    {
        $count = DB::table('jobs')->count();
        $failed = DB::table('failed_jobs')->count();
        $byQueue = DB::table('jobs')->groupBy('queue')
            ->select('queue', DB::raw('count(*) as count'))
            ->get();

        return "Gözləyən: {$count}, Uğursuz: {$failed}\n" .
               "Növbəyə görə:\n" . $byQueue->map(fn($r) => "  {$r->queue}: {$r->count}")->join("\n");
    }
}

// McpServiceProvider-da qeydiyyatdan keçir:
$registry->register(new GetPendingJobsTool());
```
