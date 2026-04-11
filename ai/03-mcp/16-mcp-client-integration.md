# MCP Client İnteqrasiyası

## İcmal

MCP client — MCP server-lərə qoşulan və onlarla AI host arasında vasitəçilik edən komponentdir. Bu fayl aşağıdakıları əhatə edir:
1. Claude Desktop və Claude Code-da MCP server-lərinin konfiqurasiyası
2. Proqramlı şəkildə MCP server-lərini çağıran xüsusi PHP/Laravel MCP client-i qurmaq
3. Laravel tətbiqindən çox server orkestrləməsi

---

## Claude Desktop Konfiqurasiyası

Claude Desktop işə başladıqda `claude_desktop_config.json` faylını oxuyur. Hər server girişi üçün bir MCP client yaradır və tətbiqin ömrü boyunca əlaqəni saxlayır.

### Konfiqurasiya Faylının Yeri

| Platforma | Yol |
|---|---|
| macOS | `~/Library/Application Support/Claude/claude_desktop_config.json` |
| Windows | `%APPDATA%\Claude\claude_desktop_config.json` |
| Linux | `~/.config/Claude/claude_desktop_config.json` |

### Tam Konfiqurasiya Schema-sı

```json
{
  "mcpServers": {
    "<server-adı>": {
      "command": "<icra olunan>",
      "args": ["<arg1>", "<arg2>"],
      "env": {
        "AÇAR": "dəyər"
      },
      "disabled": false,
      "autoApprove": ["tool_adı_1", "tool_adı_2"]
    }
  }
}
```

| Sahə | Məcburi | Təsvir |
|---|---|---|
| `command` | Bəli | İşə salınacaq icra olunan fayl (node, python, php, və s.) |
| `args` | Xeyr | Komanda sətiri arqumentlərinin massivi |
| `env` | Xeyr | Server prosesinə ötürülən mühit dəyişənləri |
| `disabled` | Xeyr | Silmədən müvəqqəti deaktiv etmək üçün `true` təyin edin |
| `autoApprove` | Xeyr | İstifadəçi sorğusu olmadan avtomatik təsdiq ediləcək tool adları |

### Nümunə: Çoxlu Server-lər

```json
{
  "mcpServers": {
    "filesystem": {
      "command": "npx",
      "args": ["-y", "@modelcontextprotocol/server-filesystem", "/home/user/projects"]
    },
    "github": {
      "command": "npx",
      "args": ["-y", "@modelcontextprotocol/server-github"],
      "env": {
        "GITHUB_PERSONAL_ACCESS_TOKEN": "ghp_xxxxxxxxxxxx"
      }
    },
    "postgres": {
      "command": "npx",
      "args": ["-y", "@modelcontextprotocol/server-postgres", "postgresql://localhost/mydb"]
    },
    "my-laravel-app": {
      "command": "php",
      "args": ["/var/www/myapp/artisan", "mcp:serve"],
      "env": {
        "APP_ENV":     "local",
        "DB_HOST":     "127.0.0.1",
        "DB_DATABASE": "myapp"
      }
    }
  }
}
```

### Konfiqurasiyada Sirlər

`claude_desktop_config.json`-dakı `env` bloku açıq mətn kimi saxlanılır. İstehsal sirləri üçün bu yanaşmalardan birini istifadə edin:

**Seçim 1: Sistem mühit dəyişənlərinə istinad edin**
```json
{
  "env": {
    "API_KEY": "${MY_API_KEY}"
  }
}
```
(Yalnız Claude Desktop mühit dəyişəni interpolyasiyasını dəstəkləyirsə işləyir — cari sənədlərə baxın.)

**Seçim 2: Server tərəfindən oxunan sirlər faylı istifadə edin**
Server-i mühit dəyişənlərini gözləmək əvəzinə `.env` faylından və ya sirlər menecerindən (AWS Secrets Manager, HashiCorp Vault) oxuyacaq şəkildə konfiqurasiya edin.

**Seçim 3: Sarıcı skript istifadə edin**
```bash
#!/bin/bash
# /usr/local/bin/launch-my-mcp.sh
export DB_PASSWORD=$(cat /run/secrets/db_password)
exec php /var/www/myapp/artisan mcp:serve
```

```json
{
  "mcpServers": {
    "my-app": {
      "command": "/usr/local/bin/launch-my-mcp.sh"
    }
  }
}
```

---

## Claude Code CLI Konfiqurasiyası

Claude Code iki əhatə dairəsində MCP server-lərini dəstəkləyir:

### Layihə Səviyyəsi: `.claude/settings.json`

Layihə kökünüzdə yerləşir. Bütün komandanın eyni MCP server-lərini alması üçün versiya nəzarətinə daxil edilir.

```json
{
  "mcpServers": {
    "project-db": {
      "command": "php",
      "args": ["artisan", "mcp:serve"],
      "env": {
        "DB_HOST": "localhost"
      }
    }
  },
  "permissions": {
    "allow": [
      "mcp:project-db:tools:*",
      "mcp:project-db:resources:*"
    ]
  }
}
```

### Qlobal İstifadəçi Səviyyəsi: `~/.claude/settings.json`

Bütün layihələrdə mövcud olan şəxsi server-lər:

```json
{
  "mcpServers": {
    "personal-notes": {
      "command": "node",
      "args": ["/home/user/notes-mcp/dist/index.js"]
    }
  }
}
```

### CLI Bayrakları

```bash
# Bir sessiya üçün müvəqqəti server əlavə edin
claude --mcp-server '{"name":"test","command":"node","args":["server.js"]}'

# Qoşulmuş server-ləri siyahıla
claude mcp list

# Server detallarını alın
claude mcp get my-server

# Server-i silin
claude mcp remove my-server
```

---

## Ümumi Problemlərin Aradan Qaldırılması

### Server Claude Desktop-da Görünmür

1. **JSON sintaksisini yoxlayın** — tək bir arxadan vergül bütün konfiqurasiyanı pozur. Doğrulamaq üçün `jq . claude_desktop_config.json` istifadə edin.
2. **Claude Desktop-u yenidən başladın** — dəyişikliklər tam yenidən başladılma tələb edir (Çıxış, yalnız pəncərəni bağlamaq deyil).
3. **Server loglarını yoxlayın**:
   - macOS: `~/Library/Logs/Claude/mcp-server-<ad>.log`
   - Log server prosesinizdən stdout/stderr göstərir.

### Server Qoşulur Lakin Tool-lar Görünmür

1. `initialize` əl sıxması uğursuz ola bilər — `capabilities` cavabınızın düzgün olduğunu yoxlayın.
2. `tools/list` deformasiyalı JSON qaytarıyor ola bilər — bu ilə sınayın:
   ```bash
   echo '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-03-26","capabilities":{},"clientInfo":{"name":"test","version":"1.0"}}}' | php artisan mcp:serve
   ```

### "Spawn ENOENT" Xətası

Komanda tapıla bilmir. Mütləq yolları istifadə edin:
```json
{ "command": "/usr/local/bin/node" }
```
Və ya yolu tapın: `which node`, `which php`, `which npx`.

### Tool-lar Hər Dəfə Təsdiq Tələb Edir

Tez-tez istifadə olunan tool-ları `autoApprove`-a əlavə edin:
```json
{ "autoApprove": ["run_query", "list_files", "read_file"] }
```

### Böyük Tool Çağırışlarında Timeout

Claude Desktop-un default tool çağırışı timeout-u var. Uzun müddət işləyən tool-lar üçün qismən nəticələr qaytarın və ya tərəqqi bildirişlərini tətbiq edin (MCP `notifications/progress`).

---

## Xüsusi PHP MCP Client Qurmaq

Xüsusi MCP client Laravel tətbiqinizin **proqramlı olaraq MCP server-lərini istifadə etməsinə** imkan verir — onların tool-larını kəşf edin, çağırın və nəticələri tətbiq məntiqlərinizdə istifadə edin. Bu aşağıdakılar üçün faydalıdır:

- Fərqli MCP server-lərindən bir neçə AI tool-unu orkestrləyən Laravel tətbiqləri
- Öz MCP server-lərinizi proqramlı olaraq sınaqdan keçirmək
- Fərqli server-lərin müxtəlif ixtisaslaşmalara sahib olduğu çox agentli sistemlər qurmaq

### Transport: stdio Proses İdarəetməsi

```php
<?php

declare(strict_types=1);

namespace App\Mcp\Client;

use RuntimeException;
use Throwable;

/**
 * stdio üzərindən MCP server işlədən alt prosesi idarə edir.
 *
 * Bu client MCP JSON-RPC protokolunu uşaq prosesinə danışır,
 * Laravel-ə istənilən MCP server-ini proqramlı olaraq istifadə etməyə imkan verir.
 */
final class StdioMcpTransport
{
    /** @var resource|null */
    private mixed $process = null;

    /** @var resource */
    private mixed $stdin;

    /** @var resource */
    private mixed $stdout;

    /** @var resource */
    private mixed $stderr;

    private int $nextId = 1;

    /**
     * @param  string[]  $args
     * @param  array<string, string>  $env
     */
    public function __construct(
        private readonly string $command,
        private readonly array  $args = [],
        private readonly array  $env  = [],
    ) {}

    public function connect(): void
    {
        $cmd  = escapeshellcmd($this->command);
        $env  = array_merge(getenv(), $this->env);

        $descriptors = [
            0 => ['pipe', 'r'], // stdin  (biz yazırıq)
            1 => ['pipe', 'w'], // stdout (biz oxuyuruq)
            2 => ['pipe', 'w'], // stderr (biz log edirik)
        ];

        $fullCommand = $cmd . ' ' . implode(' ', array_map('escapeshellarg', $this->args));
        $pipes       = [];

        $this->process = proc_open($fullCommand, $descriptors, $pipes, null, $env);

        if ($this->process === false) {
            throw new RuntimeException("MCP server-i işə salmaq uğursuz oldu: {$fullCommand}");
        }

        $this->stdin  = $pipes[0];
        $this->stdout = $pipes[1];
        $this->stderr = $pipes[2];

        // Timeout-ları tətbiq edə bilmək üçün stdout-u bloklanmayan edin
        stream_set_blocking($this->stdout, false);
        stream_set_blocking($this->stderr, false);
    }

    /**
     * Sorğu göndərin və uyğun cavabı gözləyin.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     *
     * @throws RuntimeException timeout və ya protokol xətasında
     */
    public function request(string $method, array $params = [], int $timeoutSeconds = 30): array
    {
        $id      = $this->nextId++;
        $message = json_encode([
            'jsonrpc' => '2.0',
            'id'      => $id,
            'method'  => $method,
            'params'  => $params,
        ], JSON_THROW_ON_ERROR);

        fwrite($this->stdin, $message . "\n");
        fflush($this->stdin);

        return $this->waitForResponse($id, $timeoutSeconds);
    }

    /**
     * Bildiriş göndərin (cavab gözlənilmir).
     *
     * @param  array<string, mixed>  $params
     */
    public function notify(string $method, array $params = []): void
    {
        $message = json_encode([
            'jsonrpc' => '2.0',
            'method'  => $method,
            'params'  => $params,
        ], JSON_THROW_ON_ERROR);

        fwrite($this->stdin, $message . "\n");
        fflush($this->stdin);
    }

    /**
     * @return array<string, mixed>
     */
    private function waitForResponse(int $expectedId, int $timeoutSeconds): array
    {
        $deadline = time() + $timeoutSeconds;
        $buffer   = '';

        while (time() < $deadline) {
            // Prosesin hələ işlədiyini yoxlayın
            $status = proc_get_status($this->process);
            if (! $status['running']) {
                $stderr = stream_get_contents($this->stderr);
                throw new RuntimeException(
                    "MCP server prosesi çıxdı (kod {$status['exitcode']}). Stderr: {$stderr}"
                );
            }

            $read   = [$this->stdout];
            $write  = null;
            $except = null;

            // Məlumat üçün 100 ms-ə qədər gözləyin
            $ready = stream_select($read, $write, $except, 0, 100_000);

            if ($ready === false) {
                throw new RuntimeException('stream_select uğursuz oldu');
            }

            if ($ready > 0) {
                $chunk = fread($this->stdout, 65536);
                if ($chunk !== false && $chunk !== '') {
                    $buffer .= $chunk;

                    // Tam sətirləri parse etməyə cəhd edin
                    while (($pos = strpos($buffer, "\n")) !== false) {
                        $line   = substr($buffer, 0, $pos);
                        $buffer = substr($buffer, $pos + 1);

                        if (trim($line) === '') {
                            continue;
                        }

                        $parsed = json_decode($line, true, flags: JSON_THROW_ON_ERROR);

                        if (($parsed['id'] ?? null) === $expectedId) {
                            if (isset($parsed['error'])) {
                                throw new RuntimeException(
                                    "MCP xətası {$parsed['error']['code']}: {$parsed['error']['message']}"
                                );
                            }
                            return $parsed['result'] ?? [];
                        }
                        // Digər mesajlar (bildirişlər) — log edin və davam edin
                    }
                }
            }
        }

        throw new RuntimeException("MCP sorğusu {$timeoutSeconds}s-dən sonra timeout etdi (metod: id {$expectedId}-ni gözləyir)");
    }

    public function disconnect(): void
    {
        if ($this->process !== null) {
            fclose($this->stdin);
            fclose($this->stdout);
            fclose($this->stderr);
            proc_terminate($this->process);
            proc_close($this->process);
            $this->process = null;
        }
    }

    public function __destruct()
    {
        $this->disconnect();
    }
}
```

### MCP Client — Yüksək Səviyyəli API

```php
<?php

declare(strict_types=1);

namespace App\Mcp\Client;

/**
 * Transport-u MCP protokolu üçün
 * tipli metodlarla saran yüksək səviyyəli MCP client.
 */
final class McpClient
{
    private bool $initialized = false;

    /** @var array<string, mixed> */
    private array $serverCapabilities = [];

    /** @var array<int, array<string, mixed>>|null */
    private ?array $cachedTools = null;

    public function __construct(
        private readonly StdioMcpTransport $transport,
    ) {}

    /**
     * Qoşulun və MCP əl sıxmasını həyata keçirin.
     */
    public function connect(): void
    {
        $this->transport->connect();

        $result = $this->transport->request('initialize', [
            'protocolVersion' => '2025-03-26',
            'capabilities'    => [
                'roots'    => ['listChanged' => false],
                'sampling' => new \stdClass(),
            ],
            'clientInfo' => [
                'name'    => 'laravel-mcp-client',
                'version' => '1.0.0',
            ],
        ]);

        $this->serverCapabilities = $result['capabilities'] ?? [];

        // İnisializasiya bildirişi göndər
        $this->transport->notify('initialized');

        $this->initialized = true;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listTools(): array
    {
        if ($this->cachedTools !== null) {
            return $this->cachedTools;
        }

        $result            = $this->transport->request('tools/list');
        $this->cachedTools = $result['tools'] ?? [];

        return $this->cachedTools;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array{content: array, isError: bool}
     */
    public function callTool(string $name, array $arguments = []): array
    {
        return $this->transport->request('tools/call', [
            'name'      => $name,
            'arguments' => $arguments,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listResources(): array
    {
        $result = $this->transport->request('resources/list');
        return $result['resources'] ?? [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function readResource(string $uri): array
    {
        $result = $this->transport->request('resources/read', ['uri' => $uri]);
        return $result['contents'] ?? [];
    }

    /**
     * Tool çağırışı nəticəsinin məzmunundan sadə mətni çıxarın.
     */
    public function extractText(array $toolResult): string
    {
        $parts = [];
        foreach ($toolResult['content'] ?? [] as $block) {
            if ($block['type'] === 'text') {
                $parts[] = $block['text'];
            }
        }
        return implode("\n", $parts);
    }

    public function disconnect(): void
    {
        $this->transport->disconnect();
    }
}
```

### McpClientFactory

```php
<?php

declare(strict_types=1);

namespace App\Mcp\Client;

/**
 * Konfiqurasiya massivindən MCP client-ləri yaradır və konfiqurasiya edir.
 *
 * Konfiqurasiya formatı claude_desktop_config.json-u əks etdirir ki,
 * server təriflərini Claude Desktop ilə Laravel tətbiqiniz arasında paylaşa biləsiniz.
 */
final class McpClientFactory
{
    /**
     * Verilmiş server konfiqurasiyası üçün client yarat və qoşul.
     *
     * @param  array{command: string, args?: string[], env?: array<string, string>}  $config
     */
    public function make(array $config): McpClient
    {
        $transport = new StdioMcpTransport(
            command: $config['command'],
            args:    $config['args'] ?? [],
            env:     $config['env']  ?? [],
        );

        $client = new McpClient($transport);
        $client->connect();

        return $client;
    }

    /**
     * Tətbiqin mcp.php konfiqurasiya faylından yarat.
     */
    public function makeFromConfig(string $serverName): McpClient
    {
        $servers = config('mcp.servers', []);

        if (! isset($servers[$serverName])) {
            throw new \InvalidArgumentException("MCP server '{$serverName}' config/mcp.php-də tapılmadı");
        }

        return $this->make($servers[$serverName]);
    }
}
```

```php
// config/mcp.php
return [
    'servers' => [
        'laravel-app' => [
            'command' => 'php',
            'args'    => [base_path('artisan'), 'mcp:serve'],
            'env'     => [],
        ],
        'filesystem' => [
            'command' => 'npx',
            'args'    => ['-y', '@modelcontextprotocol/server-filesystem', storage_path()],
            'env'     => [],
        ],
        'database' => [
            'command' => 'node',
            'args'    => [base_path('node_modules/.bin/mcp-server-postgres')],
            'env'     => [
                'DATABASE_URL' => env('DATABASE_URL', ''),
            ],
        ],
    ],
];
```

---

## Laravel-dən Çox Server Orkestrləməsi

```php
<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Mcp\Client\McpClientFactory;
use App\Mcp\Client\McpClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Bir neçə MCP server-ini orkestrləyir, vahid tool reyestri təmin edir
 * və tool çağırışlarını düzgün server-ə yönləndirir.
 *
 * İstifadə halı: Eyni anda fayl sistemi server-indən, verilənlər bazası
 * server-indən və xüsusi biznes məntiqi server-indən tool-ları istifadə
 * edə bilən Laravel AI agenti.
 */
final class MultiServerMcpOrchestrator
{
    /** @var array<string, McpClient> */
    private array $clients = [];

    /** @var array<string, string>  toolAdı => serverAdı */
    private array $toolToServer = [];

    public function __construct(
        private readonly McpClientFactory $factory,
    ) {}

    /**
     * Bir neçə server-ə qoşulun və birləşdirilmiş tool reyestri qurun.
     *
     * @param  string[]  $serverNames
     */
    public function connect(array $serverNames): void
    {
        foreach ($serverNames as $name) {
            try {
                $client = $this->factory->makeFromConfig($name);
                $tools  = $client->listTools();

                $this->clients[$name] = $client;

                foreach ($tools as $tool) {
                    $toolName = $tool['name'];

                    if (isset($this->toolToServer[$toolName])) {
                        Log::warning("'{$toolName}' tool-u bir neçə server tərəfindən təqdim edilir. '{$name}' istifadə edilir.");
                    }

                    $this->toolToServer[$toolName] = $name;
                }

                Log::info("'{$name}' MCP server-inə " . count($tools) . " tool ilə qoşuldu");
            } catch (\Throwable $e) {
                Log::error("'{$name}' MCP server-inə qoşulmaq uğursuz oldu: " . $e->getMessage());
            }
        }
    }

    /**
     * Bütün server-lərdə mövcud olan bütün tool-ları alın.
     * Anthropic-ə uyğun tool təriflərini qaytarır.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAllTools(): array
    {
        $all = [];

        foreach ($this->clients as $serverName => $client) {
            $tools = $client->listTools();

            foreach ($tools as $tool) {
                $all[] = [
                    'name'         => $tool['name'],
                    'description'  => ($tool['description'] ?? '') . " [vasitəsilə: {$serverName}]",
                    'input_schema' => $tool['inputSchema'] ?? ['type' => 'object', 'properties' => []],
                ];
            }
        }

        return $all;
    }

    /**
     * Uyğun server-ə yönləndirilmiş tool çağırışını icra edin.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>  Anthropic tool_result məzmunu
     */
    public function callTool(string $toolName, array $arguments): array
    {
        $serverName = $this->toolToServer[$toolName] ?? null;

        if ($serverName === null) {
            return [
                'type'      => 'tool_result',
                'is_error'  => true,
                'content'   => "Naməlum tool: {$toolName}",
            ];
        }

        $client = $this->clients[$serverName];

        try {
            $result = $client->callTool($toolName, $arguments);

            return [
                'type'     => 'tool_result',
                'is_error' => $result['isError'] ?? false,
                'content'  => $client->extractText($result),
            ];
        } catch (\Throwable $e) {
            Log::error("MCP tool çağırışı uğursuz oldu", [
                'tool'   => $toolName,
                'server' => $serverName,
                'error'  => $e->getMessage(),
            ]);

            return [
                'type'     => 'tool_result',
                'is_error' => true,
                'content'  => "Tool xətası: {$e->getMessage()}",
            ];
        }
    }

    public function disconnect(): void
    {
        foreach ($this->clients as $client) {
            $client->disconnect();
        }
        $this->clients     = [];
        $this->toolToServer = [];
    }

    public function __destruct()
    {
        $this->disconnect();
    }
}
```

### Orkestratoru AI Agentdə İstifadə Etmək

```php
<?php

declare(strict_types=1);

namespace App\Services\AI;

use GuzzleHttp\Client as GuzzleClient;

/**
 * Tapşırıqları yerinə yetirmək üçün Claude + MCP tool-larından istifadə edən AI agenti.
 */
final class McpAgent
{
    public function __construct(
        private readonly MultiServerMcpOrchestrator $mcp,
        private readonly GuzzleClient               $anthropic,
    ) {}

    /**
     * Agentik döngü işlət: Claude-a mesajlar göndər, MCP vasitəsilə tool çağırışlarını icra et,
     * Claude tool çağırışını dayandırana qədər davam et.
     */
    public function run(string $task, int $maxIterations = 10): string
    {
        $this->mcp->connect(['laravel-app', 'filesystem', 'database']);

        $tools    = $this->mcp->getAllTools();
        $messages = [['role' => 'user', 'content' => $task]];

        for ($i = 0; $i < $maxIterations; $i++) {
            $response = $this->callClaude($messages, $tools);
            $content  = $response['content'];

            // Claude-un tool istifadə etmək istəyib-istəmədiyini yoxlayın
            $toolUseBlocks = array_filter($content, fn ($b) => $b['type'] === 'tool_use');

            if (empty($toolUseBlocks)) {
                // Daha çox tool çağırışı yoxdur — son mətn cavabını çıxarın
                foreach ($content as $block) {
                    if ($block['type'] === 'text') {
                        return $block['text'];
                    }
                }
                return '';
            }

            // Claude-un cavabını tarixçəyə əlavə edin
            $messages[] = ['role' => 'assistant', 'content' => $content];

            // Bütün tool çağırışlarını icra edin və nəticələri əlavə edin
            $toolResults = [];
            foreach ($toolUseBlocks as $block) {
                $result        = $this->mcp->callTool($block['name'], $block['input']);
                $toolResults[] = [
                    'type'        => 'tool_result',
                    'tool_use_id' => $block['id'],
                    'content'     => $result['content'],
                    'is_error'    => $result['is_error'],
                ];
            }

            $messages[] = ['role' => 'user', 'content' => $toolResults];
        }

        return "Maksimum iterasiya ({$maxIterations}) son cavab olmadan əldə edildi.";
    }

    private function callClaude(array $messages, array $tools): array
    {
        $response = $this->anthropic->post('https://api.anthropic.com/v1/messages', [
            'json' => [
                'model'      => 'claude-opus-4-5',
                'max_tokens' => 4096,
                'tools'      => $tools,
                'messages'   => $messages,
            ],
        ]);

        return json_decode($response->getBody()->getContents(), true, flags: JSON_THROW_ON_ERROR);
    }
}
```
