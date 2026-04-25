# MCP Server Testing və Debugging (Senior)

## Mündəricat

1. [Niyə MCP Testing Xüsusi Çətindir](#niyə-mcp-testing-xüsusi-çətindir)
2. [MCP Inspector — Rəsmi Alət](#mcp-inspector--rəsmi-alət)
3. [Unit Testing Strategiyası](#unit-testing-strategiyası)
4. [Integration Testing — Mock İstemci](#integration-testing--mock-i̇stemci)
5. [Pest/PHPUnit Nümunələri](#pestphpunit-nümunələri)
6. [Debug Patternləri](#debug-patternləri)
7. [Tipik Bug-lar və Həlli](#tipik-bug-lar-və-həlli)
8. [Production Observability](#production-observability)
9. [OpenTelemetry Integration](#opentelemetry-integration)
10. [Client-side Trace və Dashboardlar](#client-side-trace-və-dashboardlar)

---

## Niyə MCP Testing Xüsusi Çətindir

MCP server testing adi API server testing-dən iki səbəbə görə fərqlənir:

**Birincisi, nəql qatı.** stdio serverləri stdin/stdout üzərindən işləyir — bu o deməkdir ki, ənənəvi HTTP testing alətləri işləmir. Postman, curl, Laravel-in `$this->get()` helper-ləri — heç biri mənbə kodu-nda stdio server-ə yaxınlaşa bilmir. Test mühitinin stdin/stdout ilə JSON-RPC mesajları mübadilə etməsinə ehtiyac var.

**İkincisi, LLM-driven behavior.** MCP server-in son istifadəçisi insan deyil — LLM-dir. "Bu tool yaxşı işləyir" dedikdə iki ayrı sual var: (a) tool texniki olaraq düzgün cavab verirmi (unit test), və (b) LLM onu düzgün situasiyalarda çağırırmı (behavior test). İkinci sual yalnız real LLM ilə və ya ona çox yaxın mock ilə cavablandırıla bilər.

Bu iki qatı bir-birindən ayırmaq senior test strategiyasının əsasıdır:

```
┌─────────────────────────────────────────────────┐
│              TEST PYRAMİDASI                    │
├─────────────────────────────────────────────────┤
│                                                 │
│        ┌───────────────────────┐                │
│        │  E2E (Claude Desktop) │  ← manual      │
│        └───────────────────────┘                │
│     ┌─────────────────────────────┐             │
│     │  Behavior (LLM + mock MCP)  │  ← eval     │
│     └─────────────────────────────┘             │
│  ┌──────────────────────────────────┐           │
│  │  Integration (JSON-RPC round-trip)│  ← CI    │
│  └──────────────────────────────────┘           │
│┌────────────────────────────────────────┐       │
││  Unit (handler məntiqi, schema, auth)  │ ← CI  │
│└────────────────────────────────────────┘       │
└─────────────────────────────────────────────────┘
```

---

## MCP Inspector — Rəsmi Alət

Anthropic `@modelcontextprotocol/inspector` paketini MCP serverlərini interaktiv yoxlamaq üçün yayımlayıb. Bu, server qurarkən **ilk istifadə etməli olduğun alətdir** — LLM olmadan bütün primitive-ləri görə və çağıra bilirsən.

### İşə Salma

```bash
# Stdio server üçün
npx @modelcontextprotocol/inspector php /path/to/artisan mcp:serve

# HTTP server üçün
npx @modelcontextprotocol/inspector http://localhost:8080/mcp

# Environment variables ilə
npx @modelcontextprotocol/inspector \
  -e APP_ENV=local \
  -e DB_DATABASE=testing \
  php /path/to/artisan mcp:serve
```

Inspector brauzerdə açılır (adətən `http://localhost:5173`) və üç panel göstərir:

```
┌──────────────────────────────────────────────────────────┐
│  MCP Inspector                                           │
├──────────────────────────────────────────────────────────┤
│ Server: php artisan mcp:serve  [Connect]  [Disconnect]   │
│ Status: ● Connected (protokol 2025-03-26)                │
├──────────────────────────────────────────────────────────┤
│  Tools        │    Resources      │    Prompts           │
│  ─────────    │    ────────────   │    ──────────        │
│  create_ticket│  file://project/..│  /summarize-pr       │
│  search_cust  │  customer://12847 │  /escalate-ticket    │
│  get_order    │  dashboard://cpu  │                      │
├──────────────────────────────────────────────────────────┤
│  [Selected: create_ticket]                               │
│                                                          │
│  title:       [_________________]                        │
│  description: [_________________]                        │
│  priority:    [medium ▼]                                 │
│  customer_id: [12847]                                    │
│                                                          │
│  [Call Tool]                                             │
│                                                          │
│  Response:                                               │
│  {                                                       │
│    "content": [{                                         │
│      "type": "text",                                     │
│      "text": "Bilet yaradıldı: TKT-009142"               │
│    }]                                                    │
│  }                                                       │
└──────────────────────────────────────────────────────────┘
```

### Inspector-də Workflow

1. **Connect** — server başladığını və initialize uğurlu olduğunu təsdiqlə.
2. **Tools panel** — hər toolun schema-sını gözdən keçir. description əlverişlidirmi? Required sahələr düzgündürmü?
3. **Test çağırış** — hər tool üçün minimum və maksimum input ilə çağır. Error halları da yoxla (invalid ID, yanlış type).
4. **Resources** — list() response-u kontekst pəncərəsinə sığacaq qədər qısadırmı? read() düzgün mimeType qaytarır?
5. **Prompts** — message ardıcıllığı LLM üçün konkret və qısadırmı?

### Inspector Log Panel

Sağ tərəfdə bütün JSON-RPC trafiki real-time göstərilir:

```
14:32:11  → initialize {"protocolVersion":"2025-03-26",...}
14:32:11  ← initialize result
14:32:11  → tools/list
14:32:11  ← tools/list {"tools":[...]}
14:32:15  → tools/call {"name":"create_ticket","arguments":{...}}
14:32:16  ← tools/call {"content":[...],"isError":false}
```

Bu log-u `.har` faylına export edib sonra regress test üçün istifadə etmək mümkündür.

---

## Unit Testing Strategiyası

MCP server unit testing-in əsas prinsipi: **handler-ləri protokol qatından ayır**. McpServer-in stdin/stdout loop-u test edilə bilməz asanlıqla — amma handler class-larını izolyasiya edib PHP unit-test ilə test etmək asandır.

### Handler Structure

```php
<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

// Handler sırf input → output transform edir
// IO, stdin/stdout, JSON-RPC parsing bu class-dan xaricdədir
final class SearchCustomerTool
{
    public function __construct(
        private readonly CustomerRepository $repository,
    ) {}

    public function call(array $arguments): array
    {
        $query = $arguments['query'] ?? '';
        $limit = min(50, (int) ($arguments['limit'] ?? 10));

        $results = $this->repository->search($query, $limit);

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => $this->formatResults($results),
                ],
            ],
            'isError' => false,
        ];
    }

    private function formatResults(array $customers): string
    {
        if (empty($customers)) {
            return 'Müştəri tapılmadı.';
        }

        return collect($customers)
            ->map(fn ($c) => "#{$c['id']}: {$c['name']} ({$c['email']})")
            ->join("\n");
    }
}
```

### Unit Test

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Mcp;

use App\Mcp\Tools\SearchCustomerTool;
use App\Repositories\CustomerRepository;
use Mockery;
use Tests\TestCase;

final class SearchCustomerToolTest extends TestCase
{
    public function test_returns_formatted_results(): void
    {
        $repo = Mockery::mock(CustomerRepository::class);
        $repo->shouldReceive('search')
            ->with('john', 10)
            ->once()
            ->andReturn([
                ['id' => 1, 'name' => 'John Doe',   'email' => 'john@example.com'],
                ['id' => 2, 'name' => 'Jane Smith', 'email' => 'jane@example.com'],
            ]);

        $tool = new SearchCustomerTool($repo);
        $result = $tool->call(['query' => 'john']);

        $this->assertFalse($result['isError']);
        $this->assertStringContainsString('#1: John Doe', $result['content'][0]['text']);
        $this->assertStringContainsString('#2: Jane Smith', $result['content'][0]['text']);
    }

    public function test_returns_empty_message_when_no_results(): void
    {
        $repo = Mockery::mock(CustomerRepository::class);
        $repo->shouldReceive('search')->once()->andReturn([]);

        $tool = new SearchCustomerTool($repo);
        $result = $tool->call(['query' => 'nonexistent']);

        $this->assertFalse($result['isError']);
        $this->assertStringContainsString('tapılmadı', $result['content'][0]['text']);
    }

    public function test_limit_is_capped_at_50(): void
    {
        $repo = Mockery::mock(CustomerRepository::class);
        $repo->shouldReceive('search')
            ->with('a', 50)  // İstifadəçi 1000 verdi, 50-yə cap edildi
            ->once()
            ->andReturn([]);

        $tool = new SearchCustomerTool($repo);
        $tool->call(['query' => 'a', 'limit' => 1000]);

        // Assertion mockdan gəlir
        $this->assertTrue(true);
    }
}
```

### Schema Validation Testi

Tool schema-nın JSON Schema valid olub olmamasını test edin:

```php
public function test_tool_definition_is_valid_json_schema(): void
{
    $tool = new SearchCustomerTool(Mockery::mock(CustomerRepository::class));
    $def = $tool->definition();

    // Required sahələr mövcuddur
    $this->assertArrayHasKey('name', $def);
    $this->assertArrayHasKey('description', $def);
    $this->assertArrayHasKey('inputSchema', $def);

    // inputSchema JSON Schema draft-7 ilə validdir
    $validator = new \Opis\JsonSchema\Validator();
    $this->assertTrue(
        $validator->validate($def['inputSchema'], 'http://json-schema.org/draft-07/schema#')->isValid()
    );

    // Description kifayət qədər aydındır (heuristic)
    $this->assertGreaterThan(30, strlen($def['description']),
        'Tool description çox qısa — LLM nə vaxt istifadə edəcəyini bilməyəcək');
}
```

---

## Integration Testing — Mock İstemci

Integration test-lər JSON-RPC round-trip-i yoxlayır. Mock istemci yazıb serverə məsajlar göndərmək və cavabları təsdiqləmək lazımdır.

### Mock Stdio Client

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Mcp;

final class McpTestClient
{
    private $process;
    private $stdin;
    private $stdout;
    private int $idCounter = 0;

    public function __construct(string $command, array $args = [])
    {
        $descriptors = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w'],  // stderr
        ];

        $fullCommand = escapeshellcmd($command) . ' ' .
                       implode(' ', array_map('escapeshellarg', $args));

        $this->process = proc_open($fullCommand, $descriptors, $pipes);
        [$this->stdin, $this->stdout] = $pipes;

        stream_set_blocking($this->stdout, false);
    }

    public function request(string $method, array $params = []): array
    {
        $id = (string) (++$this->idCounter);
        $message = json_encode([
            'jsonrpc' => '2.0',
            'id'      => $id,
            'method'  => $method,
            'params'  => $params,
        ]) . "\n";

        fwrite($this->stdin, $message);
        fflush($this->stdin);

        return $this->readResponse($id);
    }

    public function notify(string $method, array $params = []): void
    {
        $message = json_encode([
            'jsonrpc' => '2.0',
            'method'  => $method,
            'params'  => $params,
        ]) . "\n";

        fwrite($this->stdin, $message);
        fflush($this->stdin);
    }

    private function readResponse(string $expectedId, int $timeoutMs = 5000): array
    {
        $start = microtime(true) * 1000;
        $buffer = '';

        while ((microtime(true) * 1000 - $start) < $timeoutMs) {
            $chunk = fread($this->stdout, 4096);
            if ($chunk !== false) {
                $buffer .= $chunk;
            }

            // Yeni sətir ilə ayrılmış mesajları parse et
            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 1);

                $msg = json_decode($line, true);
                if (isset($msg['id']) && (string) $msg['id'] === $expectedId) {
                    return $msg;
                }
            }

            usleep(10_000);
        }

        throw new \RuntimeException("Server {$timeoutMs}ms ərzində cavab vermədi");
    }

    public function __destruct()
    {
        if (is_resource($this->stdin))  fclose($this->stdin);
        if (is_resource($this->stdout)) fclose($this->stdout);
        if (is_resource($this->process)) proc_close($this->process);
    }
}
```

### Integration Test İstifadəsi

```php
<?php

namespace Tests\Integration\Mcp;

use Tests\TestCase;

final class McpServerIntegrationTest extends TestCase
{
    private McpTestClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = new McpTestClient('php', [
            base_path('artisan'),
            'mcp:serve',
        ]);

        // Handshake
        $result = $this->client->request('initialize', [
            'protocolVersion' => '2025-03-26',
            'capabilities'    => [],
            'clientInfo'      => ['name' => 'test-client', 'version' => '1.0'],
        ]);

        $this->assertArrayHasKey('result', $result);
        $this->assertEquals('2025-03-26', $result['result']['protocolVersion']);

        $this->client->notify('initialized');
    }

    public function test_tools_list_returns_expected_tools(): void
    {
        $response = $this->client->request('tools/list');

        $toolNames = array_column($response['result']['tools'], 'name');

        $this->assertContains('create_ticket', $toolNames);
        $this->assertContains('search_customer', $toolNames);
        $this->assertContains('get_order_status', $toolNames);
    }

    public function test_create_ticket_tool_call_end_to_end(): void
    {
        $response = $this->client->request('tools/call', [
            'name' => 'create_ticket',
            'arguments' => [
                'title'       => 'Test issue',
                'description' => 'Test description',
                'customer_id' => 1,
                'priority'    => 'medium',
            ],
        ]);

        $this->assertFalse($response['result']['isError']);
        $this->assertStringContainsString('TKT-', $response['result']['content'][0]['text']);

        // DB-də yaradıldığını təsdiqlə
        $this->assertDatabaseHas('support_tickets', [
            'title'       => 'Test issue',
            'customer_id' => 1,
        ]);
    }

    public function test_invalid_tool_returns_error(): void
    {
        $response = $this->client->request('tools/call', [
            'name' => 'nonexistent_tool',
            'arguments' => [],
        ]);

        $this->assertArrayHasKey('error', $response);
        $this->assertEquals(-32601, $response['error']['code']); // Method not found
    }
}
```

---

## Pest/PHPUnit Nümunələri

Pest istifadə edən komandalar üçün integration test-lər daha yığcam yazılır:

```php
<?php

use Tests\Integration\Mcp\McpTestClient;

beforeEach(function () {
    $this->mcp = new McpTestClient('php', [base_path('artisan'), 'mcp:serve']);
    $this->mcp->request('initialize', [
        'protocolVersion' => '2025-03-26',
        'capabilities'    => [],
        'clientInfo'      => ['name' => 'pest', 'version' => '1.0'],
    ]);
    $this->mcp->notify('initialized');
});

it('lists expected tools', function () {
    $response = $this->mcp->request('tools/list');
    $names = array_column($response['result']['tools'], 'name');

    expect($names)
        ->toContain('create_ticket')
        ->toContain('search_customer');
});

it('creates a ticket successfully', function () {
    $response = $this->mcp->request('tools/call', [
        'name' => 'create_ticket',
        'arguments' => [
            'title'       => 'Pest test',
            'description' => 'From Pest',
            'customer_id' => 1,
        ],
    ]);

    expect($response['result']['isError'])->toBeFalse()
        ->and($response['result']['content'][0]['text'])->toContain('TKT-');
});

it('returns error for invalid customer_id', function () {
    $response = $this->mcp->request('tools/call', [
        'name' => 'create_ticket',
        'arguments' => [
            'title'       => 'X',
            'description' => 'Y',
            'customer_id' => 999999,
        ],
    ]);

    expect($response['result']['isError'])->toBeTrue();
});
```

### Dataset ilə Edge Case Testing

```php
it('handles various input sizes', function (int $length) {
    $longTitle = str_repeat('a', $length);
    $response = $this->mcp->request('tools/call', [
        'name' => 'create_ticket',
        'arguments' => [
            'title'       => $longTitle,
            'description' => 'desc',
            'customer_id' => 1,
        ],
    ]);

    // 100-dən çoxu schema ilə rədd edilməlidir
    if ($length > 100) {
        expect($response['result']['isError'])->toBeTrue();
    } else {
        expect($response['result']['isError'])->toBeFalse();
    }
})->with([50, 99, 100, 101, 500, 10000]);
```

---

## Debug Patternləri

### Stderr Logging — ƏN VACİB QAYDA

MCP stdio server-lərinin **ən sıx səhvi**: `echo`, `var_dump`, `print_r`, `dump()`, `dd()` — hamısı stdout-a yazır. Stdout protokol kanalıdır. İstemci orada valid JSON-RPC gözləyir. Debug output protokolu dərhal pozur:

```
İstemci gözləyir:
  {"jsonrpc":"2.0","id":"1","result":{...}}

Amma alır:
  array(2) {
    ["title"]=> "test"
    ["id"]=> 1
  }
  {"jsonrpc":"2.0","id":"1","result":{...}}

Parse xətası → bağlantı düşür → server "mysterious failure"
```

**Qayda: MCP server içində heç vaxt stdout-a yazma.** Bütün debug stderr-ə:

```php
<?php

namespace App\Mcp;

final class StderrLogger
{
    private static $stream = null;

    public static function log(string $level, string $message, array $context = []): void
    {
        if (self::$stream === null) {
            self::$stream = fopen('php://stderr', 'w');
        }

        $timestamp = date('Y-m-d H:i:s');
        $contextJson = empty($context) ? '' : ' ' . json_encode($context);

        fwrite(self::$stream, "[{$timestamp}] {$level}: {$message}{$contextJson}\n");
    }

    public static function debug(string $msg, array $ctx = []): void { self::log('DEBUG', $msg, $ctx); }
    public static function info(string $msg, array $ctx = []): void  { self::log('INFO',  $msg, $ctx); }
    public static function warn(string $msg, array $ctx = []): void  { self::log('WARN',  $msg, $ctx); }
    public static function error(string $msg, array $ctx = []): void { self::log('ERROR', $msg, $ctx); }
}

// İstifadə
StderrLogger::info('Tool çağırıldı', ['tool' => 'create_ticket', 'args' => $args]);
```

Laravel Log facade də istifadə edilə bilər, əgər log channel stderr-ə yönləndirilib:

```php
// config/logging.php
'channels' => [
    'mcp' => [
        'driver' => 'errorlog',
        'level'  => 'debug',
    ],
],

// İstifadə
Log::channel('mcp')->info('Tool called', ['name' => 'create_ticket']);
```

### Laravel-də dd()/dump() Qadağası

Əgər Laravel tətbiqin kodunda `dd()` və ya `dump()` çağırışı varsa və o kod MCP server-in bir hissəsi kimi işləyirsə, stdout pozulur. MCP mode-da bu funksiyaları override edin:

```php
// app/Mcp/McpServer.php — run() başlanğıcında
public function run(): void
{
    if (env('APP_MCP_MODE') === 'true') {
        // dd() və dump()-u stderr-ə yönləndir
        \Illuminate\Support\Env::getRepository()->set('VAR_DUMPER_FORMAT', 'cli');

        \Symfony\Component\VarDumper\VarDumper::setHandler(function ($var) {
            $dumper = new \Symfony\Component\VarDumper\Dumper\CliDumper('php://stderr');
            $cloner = new \Symfony\Component\VarDumper\Cloner\VarCloner();
            $dumper->dump($cloner->cloneVar($var));
        });
    }

    // ... əsas loop
}
```

### Protokol Trafikini Log Et

Debug zamanı hər gələn/gedən mesajı stderr-ə yazmaq faydalıdır:

```php
private function handleMessage(string $line): void
{
    StderrLogger::debug('→ IN ', ['msg' => $line]);

    $message = json_decode($line, true);
    $response = $this->dispatch($message);

    if ($response !== null) {
        $responseJson = json_encode($response);
        StderrLogger::debug('← OUT', ['msg' => $responseJson]);
        echo $responseJson . "\n";
    }
}
```

Bu log-u Claude Desktop-da server stderr-i-ni göstərməsi ilə görə bilərsiniz (`~/Library/Logs/Claude/mcp*.log` macOS-da).

---

## Tipik Bug-lar və Həlli

### Bug 1: Schema Mismatch — Model Tool-u Çağıra Bilmir

**Simptom:** Claude tool-u göstərir amma heç vaxt çağırmır, və ya səhv arqumentlər verir.

**Səbəb:** Schema və description arasında uyğunsuzluq. Description deyir "customer_id və ya customer_name", amma schema yalnız `customer_id` required edir.

**Həll:**
```php
// Pis
[
    'name' => 'search_orders',
    'description' => 'Müştəri ID və ya email ilə sifarişləri tap',
    'inputSchema' => [
        'properties' => ['customer_id' => ['type' => 'integer']],
        'required'   => ['customer_id'],  // email-i dəstəkləmir!
    ],
]

// Yaxşı — oneOf ilə
[
    'name' => 'search_orders',
    'description' => 'Müştəri ID və ya email ilə sifarişləri tap',
    'inputSchema' => [
        'properties' => [
            'customer_id' => ['type' => 'integer'],
            'email'       => ['type' => 'string', 'format' => 'email'],
        ],
        'oneOf' => [
            ['required' => ['customer_id']],
            ['required' => ['email']],
        ],
    ],
]
```

### Bug 2: Vague Tool Descriptions — LLM Səhv Tool Seçir

**Simptom:** Claude `search_customer` əvəzinə `list_customers` çağırır.

**Səbəb:** Description-lar bir-birinə çox bənzəyir.

**Həll:** Disambiguating signalları əlavə et:

```php
// Pis
'search_customer' => 'Müştərilər üzərində axtarış'
'list_customers'  => 'Müştəri siyahısı'

// Yaxşı
'search_customer' => 'Konkret müştərini ad, email və ya telefon ilə tap. Məqsəd tək müştəri və ya kiçik dəst tapmaqdır.'
'list_customers'  => 'Paginated müştəri siyahısı — browsing və overview üçün. Spesifik müştəri axtarılırsa search_customer istifadə edin.'
```

### Bug 3: Unbounded Loops — Context Overflow

**Simptom:** Agent `list_files` → `read_file` → `list_files` → ... sonsuz dövrə daxil olur və context-i partladır.

**Səbəb:** Tool state-siz qaytarır, amma agent yaddaşı olmayan reasoning-dən qaçınmaq üçün yenidən çağırır.

**Həll:** Pagination state-i server-də saxla:

```php
public function listFiles(array $args): array
{
    $cursor = $args['cursor'] ?? null;
    $pageSize = 20;

    $files = $this->fileService->list(afterCursor: $cursor, limit: $pageSize + 1);

    $hasMore = count($files) > $pageSize;
    $files = array_slice($files, 0, $pageSize);
    $nextCursor = $hasMore ? end($files)['id'] : null;

    return [
        'content' => [[
            'type' => 'text',
            'text' => json_encode([
                'files'       => $files,
                'next_cursor' => $nextCursor,
                'has_more'    => $hasMore,
            ]),
        ]],
    ];
}
```

Description-da explicit göstər:

```
"list_files": "Faylları pagination ilə qaytarır. next_cursor boş deyilsə, daha çox fayl var — onları almaq üçün cursor arqumentini keçirin."
```

### Bug 4: UTF-8 Breakage — JSON Parse Error

**Simptom:** Ərəb, rus və ya emoji simvolları olduqda server crash olur.

**Səbəb:** `json_encode` varsayılan olaraq non-ASCII simvolları escape edir, amma bəzi PHP konfiqurasiyalarında binary-safe olmur.

**Həll:**
```php
$json = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
```

### Bug 5: Blocking I/O — Server Donur

**Simptom:** Uzun çəkən bir tool çağırışı bütün serveri bloklayır.

**Səbəb:** Single-threaded stdio server-də bir tool icra edilərkən yeni məsajlar gözləyir.

**Həll:** Uzun işləri queue-ə ötür və progress bildirişləri göndər:

```php
public function generateReport(array $args): array
{
    $job = GenerateReportJob::dispatch($args);

    // Progress notifications
    $this->sendProgress(0, 'Başladı');

    // ... polling və ya event subscription
    while (!$job->isFinished()) {
        $progress = $job->getProgress();
        $this->sendProgress($progress, "İcra olunur: {$progress}%");
        sleep(1);
    }

    return ['content' => [['type' => 'text', 'text' => $job->getResult()]]];
}

private function sendProgress(int $percent, string $message): void
{
    $notification = [
        'jsonrpc' => '2.0',
        'method'  => 'notifications/progress',
        'params'  => [
            'progressToken' => $this->currentRequestId,
            'progress'      => $percent,
            'total'         => 100,
            'message'       => $message,
        ],
    ];
    echo json_encode($notification) . "\n";
}
```

### Bug 6: Hangling stdin

**Simptom:** İstemci bağlantını bağlayır, amma server hələ də process olaraq qalır.

**Səbəb:** `fgets` EOF halında `false` qaytarır — əgər yoxlamırsansa, sonsuz loop olursan.

**Həll:**
```php
while (($line = fgets(STDIN)) !== false) {
    $this->handleMessage(trim($line));
}

StderrLogger::info('stdin EOF — server qapanır');
```

---

## Production Observability

Production-da MCP server monitor etmək üç qat gözətləmə tələb edir: protokol-level metrika, tool-level metrika və client-side behavior.

### Metrika Seti

| Metrika | Niyə Vacibdir |
|---|---|
| `mcp.request.count` | Protokol aktivliyi |
| `mcp.request.duration` | Gecikməyə nəzarət |
| `mcp.request.errors` | JSON-RPC error-ları |
| `mcp.tool.calls{tool=...}` | Hansı tool-lar istifadə olunur |
| `mcp.tool.duration{tool=...}` | Yavaş tool-ları tapmaq |
| `mcp.tool.errors{tool=...}` | Uğursuz icra |
| `mcp.resource.reads{uri_template=...}` | Hansı resource-lar oxunur |
| `mcp.connections.active` | Aktiv sessiyalar |
| `mcp.handshake.duration` | Başlatma yavaş olsa |

### Laravel-də Prometheus Export

```php
<?php

namespace App\Mcp\Metrics;

use Prometheus\CollectorRegistry;
use Prometheus\Storage\Redis;

final class McpMetrics
{
    private CollectorRegistry $registry;

    public function __construct()
    {
        $this->registry = new CollectorRegistry(new Redis([
            'host' => env('REDIS_HOST'),
            'port' => 6379,
        ]));
    }

    public function recordToolCall(string $tool, float $durationMs, bool $error): void
    {
        $counter = $this->registry->getOrRegisterCounter(
            'mcp', 'tool_calls_total', 'MCP tool call count', ['tool', 'status']
        );
        $counter->inc(['tool' => $tool, 'status' => $error ? 'error' : 'ok']);

        $histogram = $this->registry->getOrRegisterHistogram(
            'mcp', 'tool_duration_ms', 'MCP tool duration',
            ['tool'],
            [10, 50, 100, 250, 500, 1000, 2500, 5000, 10000]
        );
        $histogram->observe($durationMs, ['tool' => $tool]);
    }

    public function recordProtocolError(string $method, int $errorCode): void
    {
        $counter = $this->registry->getOrRegisterCounter(
            'mcp', 'protocol_errors_total', 'JSON-RPC errors', ['method', 'code']
        );
        $counter->inc(['method' => $method, 'code' => (string) $errorCode]);
    }
}
```

Tool handler-də middleware:

```php
public function dispatch(string $toolName, array $args): array
{
    $start = microtime(true);
    $error = false;

    try {
        return $this->tools[$toolName]->call($args);
    } catch (\Throwable $e) {
        $error = true;
        throw $e;
    } finally {
        $duration = (microtime(true) - $start) * 1000;
        $this->metrics->recordToolCall($toolName, $duration, $error);
    }
}
```

---

## OpenTelemetry Integration

OpenTelemetry distributed tracing üçün sənaye standartıdır. MCP server-də hər tool çağırışı üçün span yaratmaq lazımdır.

### Setup

```bash
composer require open-telemetry/sdk \
                open-telemetry/exporter-otlp \
                open-telemetry/instrumentation-laravel
```

```php
<?php

namespace App\Mcp\Observability;

use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;

final class TracedToolDispatcher
{
    public function call(string $toolName, array $args): array
    {
        $tracer = Globals::tracerProvider()->getTracer('mcp-server');

        $span = $tracer->spanBuilder("mcp.tool.{$toolName}")
            ->setSpanKind(SpanKind::KIND_SERVER)
            ->setAttribute('mcp.tool.name', $toolName)
            ->setAttribute('mcp.tool.args_count', count($args))
            ->setAttribute('mcp.protocol.version', '2025-03-26')
            ->startSpan();

        $scope = $span->activate();

        try {
            $result = $this->tools[$toolName]->call($args);
            $span->setAttribute('mcp.tool.is_error', $result['isError'] ?? false);
            $span->setStatus(StatusCode::STATUS_OK);
            return $result;
        } catch (\Throwable $e) {
            $span->recordException($e);
            $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
            throw $e;
        } finally {
            $scope->detach();
            $span->end();
        }
    }
}
```

### Trace Context Propagation

HTTP transport-da istemci `traceparent` header-i göndərərsə, onu span context-inə mapping etmək lazımdır:

```php
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;

$propagator = TraceContextPropagator::getInstance();
$parentContext = $propagator->extract($request->headers->all());

$span = $tracer->spanBuilder('mcp.request')
    ->setParent($parentContext)
    ->startSpan();
```

Stdio transport-da "trace context" host-un proses environment-i ilə ötürülür və ya JSON-RPC `params._meta.traceparent` ilə:

```json
{
  "method": "tools/call",
  "params": {
    "name": "create_ticket",
    "arguments": {...},
    "_meta": {
      "traceparent": "00-0af7651916cd43dd8448eb211c80319c-b7ad6b7169203331-01"
    }
  }
}
```

---

## Client-side Trace və Dashboardlar

Production mühitində MCP trafikin tam mənzərəsi həm host, həm də server tərəfindən tələb olunur.

### Host-side Logging

Claude Desktop log-larını oxumaq:

```bash
# macOS
tail -f ~/Library/Logs/Claude/mcp-server-my-laravel-app.log

# Linux
tail -f ~/.config/Claude/logs/mcp-server-my-laravel-app.log

# Windows
type "%APPDATA%\Claude\logs\mcp-server-my-laravel-app.log"
```

Log-da görə biləcəyiniz şeylər:
- Server başlatma statusu
- Protokol handshake detalları
- Hər gələn/gedən mesaj
- Server stderr output
- Əgər server crash edib, exit code

### Grafana Dashboard Nümunəsi

```
┌─────────────────────────────────────────────────────────────┐
│  MCP Server Health — Laravel App                            │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Requests/min          │  Error Rate %     │  P99 Latency   │
│  [sparkline: 847 rpm]  │  [0.3%]           │  [234ms]       │
│                                                             │
├─────────────────────────────────────────────────────────────┤
│  Top Tools (by calls)                                       │
│  ━━━━━━━━━━━━━━━━ search_customer     1,234/min             │
│  ━━━━━━━━━━━━      get_order_status     721/min             │
│  ━━━━━━━          create_ticket         203/min             │
│  ━━                list_invoices         47/min             │
│                                                             │
├─────────────────────────────────────────────────────────────┤
│  Tool Duration (p50/p95/p99)                                │
│  search_customer   45ms / 120ms / 340ms                    │
│  get_order_status  12ms / 45ms  / 180ms                    │
│  create_ticket     67ms / 230ms / 890ms  ← attention        │
│                                                             │
├─────────────────────────────────────────────────────────────┤
│  Error Breakdown (last hour)                                │
│  -32602 (invalid params)    12                              │
│  -32603 (internal)           3                              │
│  custom  (validation)       18                              │
└─────────────────────────────────────────────────────────────┘
```

### Behavior Dashboard — LLM-i Server ilə

Protokol metriki məlumatın yarısıdır. Digər yarısı **LLM-in server ilə qarşılıqlı təsiri**:

| Metrika | Nə Göstərir |
|---|---|
| Tool calls per conversation | Agent bir sessiyada nə qədər tool istifadə edir |
| Avg args per call | Arguments complexity |
| Tool selection entropy | Model seçimdə dəqiqdirmi yoxsa confuse olur |
| Retry rate per tool | Model eyni tool-u dəfələrlə çağırırsa, description-ı pisdir |
| Context token spend per call | Tool response-ları nə qədər yer tutur |

Bu metrika-lar yalnız host log-larından toplana bilər — server onları özü bilmir.

### CI-də MCP Regression Testing

```yaml
# .github/workflows/mcp-tests.yml
name: MCP Server Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'

      - name: Install dependencies
        run: composer install --no-dev

      - name: Run unit tests
        run: ./vendor/bin/pest --group=unit

      - name: Run integration tests
        run: ./vendor/bin/pest --group=integration

      - name: MCP Inspector smoke test
        run: |
          npx -y @modelcontextprotocol/inspector \
            --cli \
            --method tools/list \
            php artisan mcp:serve
```

`inspector --cli` headless inspector CLI-dir, CI-də regression check üçün idealdır.

---

## Yekun: Testing Checklist

Senior MCP server production-a çıxmamışdan əvvəl:

- [ ] **Unit testlər** — hər handler izolyasiyada test edilir (mock repo, mock auth)
- [ ] **Schema validation testi** — hər tool-un inputSchema valid JSON Schema-dır
- [ ] **Integration testlər** — real JSON-RPC round-trip Pest/PHPUnit-da
- [ ] **Inspector manual test** — hər tool və resource manually sınanıb
- [ ] **Stdout contamination yoxlanılıb** — heç bir `echo`/`dd` yoxdur server path-də
- [ ] **UTF-8 edge case-lər** — ərəb/rus/emoji testləri keçir
- [ ] **Large payload testləri** — 1MB resource oxumaq timeout etmir
- [ ] **Error response-lar sanitize edilib** — stack trace LLM-ə getmir
- [ ] **Stderr logging** — bütün debug stderr-dədir
- [ ] **Metrika export** — Prometheus və ya OTel collector qoşuludur
- [ ] **Dashboard qurulub** — P99 latency, error rate, top tools
- [ ] **Host log lokasyası sənədləşib** — debug zamanı harada baxmaq məlumdur
- [ ] **Load test edilib** — 50+ concurrent tool çağırışı pozulmur
- [ ] **Protokol version handshake testləri** — köhnə versiyalı istemci ilə graceful fail
- [ ] **CI-də MCP smoke testlər** — pull request-lər üçün blocker

Növbəti addım: təhlükəsizlik pattern-ləri üçün `09-mcp-security-patterns.md` faylına bax.
