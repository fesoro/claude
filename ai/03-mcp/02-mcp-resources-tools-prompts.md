# MCP Primitives — Resources vs Tools vs Prompts

## Mündəricat

1. [Üç Primitive-in Konseptual Fərqi](#üç-primitive-in-konseptual-fərqi)
2. [Tools — Model-invoked Actions](#tools--model-invoked-actions)
3. [Resources — Host/User-selected Data](#resources--hostuser-selected-data)
4. [Prompts — User-invoked Templates](#prompts--user-invoked-templates)
5. [Hansını Nə Vaxt İstifadə Etmək](#hansını-nə-vaxt-i̇stifadə-etmək)
6. [Resource Templates və Completions](#resource-templates-və-completions)
7. [Subscriptions və Roots](#subscriptions-və-roots)
8. [Tipik Səhvlər və Anti-patternlər](#tipik-səhvlər-və-anti-patternlər)

---

## Üç Primitive-in Konseptual Fərqi

MCP spesifikasiyası serverlərin host-a ifşa edə biləcəyi **üç əsas primitive** müəyyən edir. Bunların hər biri fərqli **control plane** üzərindən idarə olunur — kim çağırır, kim qərar verir, nə vaxt icra olunur — və bu fərqi bilməmək senior səviyyədə dizayn xətalarının əsas mənbəyidir.

```
┌─────────────────────────────────────────────────────────────┐
│                     KİM QƏRAR VERİR?                        │
├──────────────┬──────────────┬───────────────────────────────┤
│   TOOLS      │  RESOURCES   │         PROMPTS               │
│              │              │                               │
│  Model       │  Host/User   │  User (explicit)              │
│  (LLM seçir) │  (kontekst)  │  (slash command, menu)        │
│              │              │                               │
│  Autonomous  │  Curated     │  Invoked                      │
└──────────────┴──────────────┴───────────────────────────────┘
```

**Tools** model tərəfindən çağırılır. LLM söhbət əsasında qərar verir: "İstifadəçi sifarişin statusunu soruşdu, `get_order_status` toolunu çağırmalıyam." Bu **model-invoked** semantikasıdır — agent nə vaxt istifadə edəcəyini seçir.

**Resources** host və ya istifadəçi tərəfindən seçilir. Claude Desktop istifadəçisi faylı UI-dan söhbətə əlavə edir. Claude Code `@` ilə fayl qeyd edir. Resource LLM-in kontekst pəncərəsinə **əvvəlcədən** yüklənir — model onu axtarmır, artıq orada olur. Bu **application-controlled** semantikadır.

**Prompts** istifadəçi tərəfindən açıq-aşkar çağırılır. `/summarize-pr` yazırsan və şablon aktivləşir. Prompt əvvəlcədən hazırlanmış söhbət skeletini, müəyyən tool-lar və resurs istinadları ilə birlikdə yaradır. Bu **user-invoked** semantikadır.

### Semantik Fərq: İsim vs Fel vs Makro

Dərin səviyyədə bu fərq dil nəzəriyyəsinə bənzəyir:

| Primitive | Qrammatik analog | Məsuliyyət |
|---|---|---|
| Tool | **Fel** (verb) — hərəkət | "Sifariş yarat", "bilet aç", "faylı yaz" |
| Resource | **İsim** (noun) — varlıq | "Müştəri #123", "Auth.php faylı", "dashboard metric" |
| Prompt | **Makro** (macro) — hazır iş axını | "PR-ı xülasə et", "incident-i təhlil et" |

Bu fərqi model səviyyəsində qorumaq çox vacibdir. Misal üçün, "SELECT sorğusu icra et" **tooldur** (fel: icra et), lakin "müştəri #42-nin sətri" **resursdur** (isim: sətir, data). "Verilənlər bazasını necə optimallaşdırmalı?" isə **prompta** uyğundur — hazır təhlil iş axını.

---

## Tools — Model-invoked Actions

Tools Claude-un imkanlarını genişləndirməyin əsas mexanizmidir. LLM söhbət zamanı hansı toolu çağıracağına özü qərar verir.

### Tool Definition Strukturu

```json
{
  "name": "create_ticket",
  "description": "Support sistemində yeni bilet yaradır. İstifadəçinin problemi haqqında konkret sualı olduqda çağırın.",
  "inputSchema": {
    "type": "object",
    "properties": {
      "title": { "type": "string", "description": "Biletin qısa başlığı (maks 100 simvol)" },
      "description": { "type": "string", "description": "Problemin ətraflı izahı" },
      "priority": { "type": "string", "enum": ["low", "medium", "high", "urgent"], "default": "medium" },
      "customer_id": { "type": "integer", "description": "Müştəri ID-si" }
    },
    "required": ["title", "description", "customer_id"]
  }
}
```

`description` sahəsi **LLM-in toolu çağıracağı anları müəyyən edir** — bu prompt engineering-dir. "Support sistemində yeni bilet yaradır" kifayət deyil. "İstifadəçinin problemi haqqında konkret sualı olduqda çağırın" da əlavə etmək modeli nə vaxt istifadə edəcəyini öyrədir.

### Laravel-də Tool Implementation

```php
<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Models\SupportTicket;
use App\Models\Customer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

final class CreateTicketTool
{
    public function definition(): array
    {
        return [
            'name' => 'create_ticket',
            'description' => 'Support sistemində yeni bilet yaradır. İstifadəçinin problemi haqqında konkret sualı olduqda çağırın.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'title' => [
                        'type' => 'string',
                        'description' => 'Biletin qısa başlığı (maks 100 simvol)',
                        'maxLength' => 100,
                    ],
                    'description' => [
                        'type' => 'string',
                        'description' => 'Problemin ətraflı izahı',
                    ],
                    'priority' => [
                        'type' => 'string',
                        'enum' => ['low', 'medium', 'high', 'urgent'],
                        'default' => 'medium',
                    ],
                    'customer_id' => [
                        'type' => 'integer',
                        'description' => 'Müştəri ID-si',
                    ],
                ],
                'required' => ['title', 'description', 'customer_id'],
            ],
        ];
    }

    public function call(array $arguments): array
    {
        $customer = Customer::findOrFail($arguments['customer_id']);

        // Təhlükəsizlik: mövcud istifadəçi bu müştəriyə çıxış əldə edə bilərmi?
        if (!$this->canAccessCustomer($customer)) {
            return $this->errorResponse('Bu müştəriyə çıxış rədd edildi');
        }

        $ticket = SupportTicket::create([
            'title'       => $arguments['title'],
            'description' => $arguments['description'],
            'priority'    => $arguments['priority'] ?? 'medium',
            'customer_id' => $customer->id,
            'created_by'  => Auth::id(),
            'status'      => 'open',
        ]);

        Log::info('MCP: ticket created', ['ticket_id' => $ticket->id, 'user_id' => Auth::id()]);

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => sprintf(
                        "Bilet yaradıldı: TKT-%06d\nBaşlıq: %s\nPriority: %s\nURL: %s",
                        $ticket->id,
                        $ticket->title,
                        $ticket->priority,
                        url("/tickets/{$ticket->id}")
                    ),
                ],
            ],
            'isError' => false,
        ];
    }

    private function canAccessCustomer(Customer $customer): bool
    {
        return Auth::user()?->can('view', $customer) ?? false;
    }

    private function errorResponse(string $message): array
    {
        return [
            'content' => [['type' => 'text', 'text' => $message]],
            'isError' => true,
        ];
    }
}
```

### Tool Çağırışı Wire Formatı

```json
// İstemci → Server
{
  "jsonrpc": "2.0",
  "id": "42",
  "method": "tools/call",
  "params": {
    "name": "create_ticket",
    "arguments": {
      "title": "Ödəniş səhifəsi yüklənmir",
      "description": "Checkout-a keçəndə 500 xətası alıram",
      "priority": "high",
      "customer_id": 12847
    }
  }
}

// Server → İstemci
{
  "jsonrpc": "2.0",
  "id": "42",
  "result": {
    "content": [
      {
        "type": "text",
        "text": "Bilet yaradıldı: TKT-009142\nBaşlıq: Ödəniş səhifəsi yüklənmir\nPriority: high\nURL: https://app.example.com/tickets/9142"
      }
    ],
    "isError": false
  }
}
```

### Tool Description — Prompt Engineering olaraq

Senior MCP server müəllifləri description sahəsini ciddi qəbul edir. Pis və yaxşı description arasında real fərq:

**Pis:**
```json
{
  "name": "query_orders",
  "description": "Sifarişləri axtar"
}
```

**Yaxşı:**
```json
{
  "name": "query_orders",
  "description": "Müştərinin sifariş tarixçəsini axtarır. Status, tarix aralığı və məbləğ üzrə filter dəstəklənir. Yalnız müştəri ID məlum olduqda istifadə edin — müştəri adı varsa əvvəlcə search_customer toolunu çağırın. Maks 50 nəticə qaytarır; daha çoxu üçün pagination istifadə edin."
}
```

İkinci description modelə üç şey öyrədir:
1. **Nə üçün** istifadə etmək (sifariş tarixçəsi üçün)
2. **Nə vaxt** istifadə etmək (yalnız customer_id varsa)
3. **Ardıcıllıq** (əvvəlcə search_customer çağır)

---

## Resources — Host/User-selected Data

Resources oxuna bilən URI-ünvanlı datadır. Tool-lardan fərqli olaraq, resources **model tərəfindən çağırılmır** — host və ya istifadəçi onları söhbətə yerləşdirir.

### Resource-lerin Control Plane-i

```
İstifadəçi Claude Desktop-da:
    [Attach file] düyməsini basır
    ↓
İstemci resources/list çağırır
    ↓
Server bütün resursları qaytarır:
    file://project/src/Auth.php
    file://project/src/UserController.php
    db://customer/12847
    ↓
İstifadəçi birini seçir → resources/read çağırılır
    ↓
Məzmun kontekst pəncərəsinə əlavə edilir
    ↓
Model onu görür amma çağırmağa qərar vermir
```

Bu fərqi başa düşmək vacibdir: **resource oxumaq model-invoked deyil**. Bəzi serverlər resource-ləri tool kimi də ifşa edir (`read_file` tool), çünki bəzi istemcilər resources UI-nu dəstəkləmir. Bu hibrid pattern ekosistemdə çox yaygındır.

### Resource Definition

```json
{
  "uri": "file://project/src/Auth.php",
  "name": "Auth.php",
  "description": "Laravel authentication service — login, logout, token issuance",
  "mimeType": "text/x-php"
}
```

### Laravel-də Resource Handler

```php
<?php

declare(strict_types=1);

namespace App\Mcp\Resources;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class FileSystemResourceProvider
{
    /** @var array<string> Açıq qovluqlar */
    private array $allowedRoots;

    public function __construct()
    {
        $this->allowedRoots = [
            base_path('app'),
            base_path('routes'),
            base_path('config'),
        ];
    }

    public function list(): array
    {
        $resources = [];

        foreach ($this->allowedRoots as $root) {
            foreach ($this->scanDirectory($root) as $file) {
                $relative = Str::after($file, base_path() . '/');
                $resources[] = [
                    'uri'         => "file://project/{$relative}",
                    'name'        => basename($file),
                    'description' => $this->describeFile($relative),
                    'mimeType'    => $this->mimeTypeFor($file),
                ];
            }
        }

        return ['resources' => $resources];
    }

    public function read(string $uri): array
    {
        // URI: file://project/app/Http/Controllers/AuthController.php
        $path = $this->resolveUri($uri);

        if (!$this->isPathAllowed($path)) {
            throw new \RuntimeException("URI icazəli root-dan kənardadır: {$uri}");
        }

        if (!file_exists($path)) {
            throw new \RuntimeException("Resource tapılmadı: {$uri}");
        }

        $contents = file_get_contents($path);

        return [
            'contents' => [
                [
                    'uri'      => $uri,
                    'mimeType' => $this->mimeTypeFor($path),
                    'text'     => $contents,
                ],
            ],
        ];
    }

    private function resolveUri(string $uri): string
    {
        // file://project/app/... → /full/path/app/...
        $relative = Str::after($uri, 'file://project/');
        return base_path($relative);
    }

    private function isPathAllowed(string $absolutePath): bool
    {
        $real = realpath($absolutePath);
        if ($real === false) return false;

        foreach ($this->allowedRoots as $root) {
            if (str_starts_with($real, realpath($root))) {
                return true;
            }
        }

        return false;
    }

    private function mimeTypeFor(string $path): string
    {
        return match (pathinfo($path, PATHINFO_EXTENSION)) {
            'php'  => 'text/x-php',
            'js'   => 'text/javascript',
            'json' => 'application/json',
            'md'   => 'text/markdown',
            'yml', 'yaml' => 'text/yaml',
            default => 'text/plain',
        };
    }

    private function describeFile(string $relative): string
    {
        return match (true) {
            str_contains($relative, 'Controllers') => 'HTTP controller',
            str_contains($relative, 'Models')      => 'Eloquent model',
            str_contains($relative, 'Services')    => 'Application service',
            str_contains($relative, 'routes/')     => 'Route definition',
            str_contains($relative, 'config/')     => 'Configuration file',
            default => 'Source file',
        };
    }

    /** @return \Generator<string> */
    private function scanDirectory(string $dir): \Generator
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $this->isIncludable($file->getPathname())) {
                yield $file->getPathname();
            }
        }
    }

    private function isIncludable(string $path): bool
    {
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        return in_array($ext, ['php', 'js', 'json', 'md', 'yml', 'yaml'], true);
    }
}
```

### Wire Format — resources/read

```json
// İstemci → Server
{
  "jsonrpc": "2.0",
  "id": "7",
  "method": "resources/read",
  "params": {
    "uri": "file://project/src/Auth.php"
  }
}

// Server → İstemci
{
  "jsonrpc": "2.0",
  "id": "7",
  "result": {
    "contents": [
      {
        "uri": "file://project/src/Auth.php",
        "mimeType": "text/x-php",
        "text": "<?php\n\nnamespace App\\Services;\n\nclass AuthService { ... }\n"
      }
    ]
  }
}
```

### Binary Resource

PDF, şəkil və digər binary data üçün `blob` sahəsi istifadə olunur (base64):

```json
{
  "contents": [
    {
      "uri": "invoice://INV-2026-001",
      "mimeType": "application/pdf",
      "blob": "JVBERi0xLjQKJeLjz9MKNCAwIG9iaiA8PC..."
    }
  ]
}
```

---

## Prompts — User-invoked Templates

Promptlar istifadəçinin açıq-aşkar çağırdığı **slash command** şablonlarıdır. Claude Code-da `/summarize-pr`, Claude Desktop-da prompt menyusu bunlara misaldır.

### Niyə Prompts Lazımdır?

Tools və resources model və istifadəçi arasında paylaşılan data/fel ifadə edir. Prompts isə **hazır iş axını**dır — server müəllifi ekspertizasını encoded şəkildə istifadəçilərə çatdırır.

Misal: support komandası incident-i həll etmək üçün 15 addımlı analiz protokoluna sahibdir. Bunu hər dəfə əl ilə yazmaq əvəzinə, server `/incident-analysis` promptu ifşa edir — istifadəçi incident ID-ni verir, prompt bütün lazımi resource-lara istinadla tam söhbət skeleti yaradır.

### Prompt Definition

```json
{
  "name": "summarize_pr",
  "description": "Pull request-in xülasəsini yaradır — dəyişikliklər, risk, test coverage",
  "arguments": [
    { "name": "pr_number", "description": "PR nömrəsi", "required": true },
    { "name": "focus", "description": "Spesifik fokus (security, performance)", "required": false }
  ]
}
```

### Laravel-də Prompt Implementation

```php
<?php

declare(strict_types=1);

namespace App\Mcp\Prompts;

use App\Services\GitHubService;

final class SummarizePrPrompt
{
    public function __construct(
        private readonly GitHubService $github,
    ) {}

    public function definition(): array
    {
        return [
            'name' => 'summarize-pr',
            'description' => 'Pull request-in xülasəsini yaradır — dəyişikliklər, risk, test coverage',
            'arguments' => [
                [
                    'name' => 'pr_number',
                    'description' => 'PR nömrəsi',
                    'required' => true,
                ],
                [
                    'name' => 'focus',
                    'description' => 'Spesifik fokus sahəsi: security, performance, testing',
                    'required' => false,
                ],
            ],
        ];
    }

    public function get(array $arguments): array
    {
        $prNumber = (int) $arguments['pr_number'];
        $focus = $arguments['focus'] ?? 'general';

        $pr = $this->github->fetchPullRequest($prNumber);
        $diff = $this->github->fetchDiff($prNumber);

        $focusInstructions = match ($focus) {
            'security' => "Təhlükəsizlik risklərinə fokus et: auth, input validation, SQL injection, XSS, secrets.",
            'performance' => "Performance-a fokus et: N+1 sorğular, index istifadəsi, memory leak, yavaş algoritmlər.",
            'testing' => "Test coverage-ə fokus et: yeni testlər, flaky testlər, coverage azalması.",
            default => "Ümumi review et: funksionallıq, kod keyfiyyəti, risk.",
        };

        return [
            'description' => "PR #{$prNumber} xülasəsi ({$focus} fokusu ilə)",
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        'type' => 'text',
                        'text' => <<<PROMPT
                        Aşağıdakı pull request üçün texniki xülasə hazırla.

                        {$focusInstructions}

                        Struktur:
                        1. TL;DR (1-2 cümlə)
                        2. Nə dəyişdi (fayl-fayl)
                        3. Riskli nöqtələr
                        4. Test coverage qiymətləndirməsi
                        5. Tövsiyələr

                        ---
                        PR #{$prNumber}: {$pr['title']}
                        Müəllif: {$pr['user']['login']}
                        Dəyişdirilmiş fayllar: {$pr['changed_files']}
                        +{$pr['additions']} / -{$pr['deletions']} sətir

                        Təsvir:
                        {$pr['body']}

                        ---
                        DIFF:
                        ```diff
                        {$diff}
                        ```
                        PROMPT,
                    ],
                ],
            ],
        ];
    }
}
```

### Wire Format — prompts/get

```json
// İstemci → Server
{
  "jsonrpc": "2.0",
  "id": "11",
  "method": "prompts/get",
  "params": {
    "name": "summarize-pr",
    "arguments": {
      "pr_number": "4281",
      "focus": "security"
    }
  }
}

// Server → İstemci
{
  "jsonrpc": "2.0",
  "id": "11",
  "result": {
    "description": "PR #4281 xülasəsi (security fokusu ilə)",
    "messages": [
      {
        "role": "user",
        "content": {
          "type": "text",
          "text": "Aşağıdakı pull request üçün texniki xülasə hazırla..."
        }
      }
    ]
  }
}
```

### Multi-message Prompt Ardıcıllığı

Prompt bir neçə mesaj qaytara bilər, hər biri user/assistant/tool roluna sahib ola bilər — bu, yarım-tamamlanmış söhbət kontekstinə imkan verir:

```php
return [
    'description' => 'Incident analysis protokolu',
    'messages' => [
        [
            'role' => 'user',
            'content' => ['type' => 'text', 'text' => 'Incident #12 üçün RCA hazırla'],
        ],
        [
            'role' => 'assistant',
            'content' => ['type' => 'text', 'text' => 'Əvvəlcə incident metadata-sını yoxlayıram...'],
        ],
        [
            'role' => 'user',
            'content' => [
                'type' => 'resource',
                'resource' => [
                    'uri' => 'incident://12/logs',
                    'mimeType' => 'text/plain',
                    'text' => '... log data ...',
                ],
            ],
        ],
    ],
];
```

---

## Hansını Nə Vaxt İstifadə Etmək

| Kriteriya | Tool | Resource | Prompt |
|---|---|---|---|
| Kim çağırır? | Model (LLM) | Host/İstifadəçi (UI) | İstifadəçi (slash cmd) |
| Yan təsir? | Bəli (action) | Xeyr (read-only) | Xeyr (template) |
| Seçim üsulu | Model reasoning | URI ilə | Ad ilə |
| Qaytarır | content blocks | contents (data) | messages (prompt) |
| Nümunə | `create_ticket` | `file://...` | `/summarize-pr` |
| Dəyişiklik yaradır | Mümkündür | Yox | Yox |
| Pagination | Adətən daxildir | `cursor` ilə | Tətbiq olunmur |
| Schema tələbi | Sərt (inputSchema) | Sərbəst (URI pattern) | Arguments array |

### Qərar Ağacı

```
Primitive seçərkən:

1. Model özü qərar verməlidirmi nə vaxt istifadə etmək?
   BƏLİ → Tool
   XEYR → davam et

2. İstifadəçi açıq-aşkar çağırırmı (slash command)?
   BƏLİ → Prompt
   XEYR → davam et

3. Oxunan data və ya UI-də attach edilən məzmundur?
   BƏLİ → Resource
   XEYR → yenidən düşün — bəlkə Tool və ya Prompt lazımdır

4. Hibrid hal: eyni imkan həm autonomous, həm də curated istifadə olunsun?
   → İkisini də ifşa et (tool + resource)
```

### Real Nümunələr

**GitHub serveri:**
- Tools: `create_issue`, `merge_pr`, `add_comment` (fellər, yan təsir)
- Resources: `github://repo/owner/name/issues/42` (isim, oxunan data)
- Prompts: `/review-pr`, `/triage-issues` (hazır iş axınları)

**Verilənlər bazası serveri:**
- Tools: `execute_query`, `explain_query` (fellər)
- Resources: `db://schema/users`, `db://row/users/42` (isimlər)
- Prompts: `/optimize-slow-query`, `/design-schema` (analizlər)

**Slack serveri:**
- Tools: `send_message`, `create_channel` (fellər)
- Resources: `slack://channel/C123/recent` (oxunan data)
- Prompts: `/daily-summary`, `/find-thread-on` (analizlər)

---

## Resource Templates və Completions

MCP 2025-03-26 versiyası **resource templates**-i dəstəkləyir — RFC 6570 URI Template formatında parametrli resource URL-ləri.

### Template Definition

```json
{
  "uriTemplate": "customer://{customer_id}/orders/{order_id}",
  "name": "Customer Order",
  "description": "Müştərinin konkret sifarişi",
  "mimeType": "application/json"
}
```

### Laravel-də Template Handler

```php
<?php

declare(strict_types=1);

namespace App\Mcp\Resources;

use App\Models\Order;

final class OrderResourceTemplate
{
    public function listTemplates(): array
    {
        return [
            'resourceTemplates' => [
                [
                    'uriTemplate' => 'customer://{customer_id}/orders/{order_id}',
                    'name'        => 'Customer Order',
                    'description' => 'Müştərinin konkret sifarişi',
                    'mimeType'    => 'application/json',
                ],
            ],
        ];
    }

    public function read(string $uri): array
    {
        // customer://12/orders/4281
        if (!preg_match('#^customer://(\d+)/orders/(\d+)$#', $uri, $matches)) {
            throw new \InvalidArgumentException("URI uyğun gəlmir: {$uri}");
        }

        [$_, $customerId, $orderId] = $matches;

        $order = Order::where('customer_id', $customerId)
            ->where('id', $orderId)
            ->firstOrFail();

        return [
            'contents' => [
                [
                    'uri'      => $uri,
                    'mimeType' => 'application/json',
                    'text'     => json_encode($order->toArray(), JSON_PRETTY_PRINT),
                ],
            ],
        ];
    }
}
```

### Completions — Auto-complete UI üçün

İstifadəçi template URI-i yazarkən, istemci `completion/complete` metodunu çağırır ki, mümkün dəyərləri təklif etsin:

```json
// İstemci → Server
{
  "jsonrpc": "2.0",
  "id": "15",
  "method": "completion/complete",
  "params": {
    "ref": {
      "type": "ref/resource",
      "uri": "customer://{customer_id}/orders/{order_id}"
    },
    "argument": {
      "name": "customer_id",
      "value": "128"
    }
  }
}

// Server → İstemci
{
  "jsonrpc": "2.0",
  "id": "15",
  "result": {
    "completion": {
      "values": ["12801", "12847", "12891"],
      "total": 3,
      "hasMore": false
    }
  }
}
```

### Laravel-də Completion Handler

```php
<?php

declare(strict_types=1);

namespace App\Mcp\Completions;

use App\Models\Customer;

final class CompletionHandler
{
    public function complete(array $params): array
    {
        $ref = $params['ref'];
        $argument = $params['argument'];

        if ($ref['type'] === 'ref/resource' && $argument['name'] === 'customer_id') {
            $prefix = $argument['value'];

            $ids = Customer::where('id', 'LIKE', $prefix . '%')
                ->limit(100)
                ->pluck('id')
                ->map(fn ($id) => (string) $id)
                ->toArray();

            return [
                'completion' => [
                    'values'  => array_slice($ids, 0, 100),
                    'total'   => count($ids),
                    'hasMore' => count($ids) >= 100,
                ],
            ];
        }

        return ['completion' => ['values' => []]];
    }
}
```

---

## Subscriptions və Roots

### Resource Subscriptions

Server resource məzmununun dəyişdiyi haqqında istemciyə bildirə bilər. Bu, canlı-yenilənən dashboard və ya log stream-lər üçün faydalıdır.

```json
// İstemci abunə olur
{
  "jsonrpc": "2.0",
  "id": "20",
  "method": "resources/subscribe",
  "params": {
    "uri": "dashboard://metrics/cpu"
  }
}

// Server dəyişiklik bildirişi göndərir (notification — id yoxdur)
{
  "jsonrpc": "2.0",
  "method": "notifications/resources/updated",
  "params": {
    "uri": "dashboard://metrics/cpu"
  }
}
```

Laravel-də subscriptions adətən Redis pub/sub və ya broadcasting ilə həyata keçirilir:

```php
final class MetricsSubscriptionHandler
{
    private array $subscribers = [];

    public function subscribe(string $uri, string $clientId): void
    {
        $this->subscribers[$uri][] = $clientId;

        // Redis-ə qulaq as
        Redis::subscribe(['metrics:' . md5($uri)], function ($message) use ($uri, $clientId) {
            $this->notifyClient($clientId, [
                'jsonrpc' => '2.0',
                'method'  => 'notifications/resources/updated',
                'params'  => ['uri' => $uri],
            ]);
        });
    }
}
```

### Roots — Serverin Çıxış Sərhədi

Host `roots` elan edir — serverin çıxış əldə edə biləcəyi URI sərhədləri. Bu, filesystem serveri üçün hansı qovluqlara baxa biləcəyini müəyyən edir.

```json
// Host → Server (initialize zamanı)
{
  "capabilities": {
    "roots": {
      "listChanged": true
    }
  }
}

// Host roots elan edir
{
  "jsonrpc": "2.0",
  "method": "notifications/roots/list_changed"
}

// Server roots soruşur
{
  "jsonrpc": "2.0",
  "id": "5",
  "method": "roots/list"
}

// Host cavab verir
{
  "jsonrpc": "2.0",
  "id": "5",
  "result": {
    "roots": [
      { "uri": "file:///home/user/project", "name": "project" },
      { "uri": "file:///home/user/docs",    "name": "docs" }
    ]
  }
}
```

Serverin vəzifəsi bu sərhədlərə hörmət etməkdir — texniki olaraq ondan kənar fayl oxuya bilər, amma protokol pozulması sayılır.

---

## Tipik Səhvlər və Anti-patternlər

### 1. Tool description-da "what" yazıb "when" yazmamaq

**Pis:**
```json
{ "description": "Sifariş siyahısını qaytarır" }
```

**Yaxşı:**
```json
{ "description": "Müştərinin sifariş tarixçəsini qaytarır. İstifadəçi keçmiş sifarişlər haqqında sual verdikdə istifadə edin. Tək bir sifariş üçün get_order istifadə edin." }
```

### 2. Resource əvəzinə tool istifadə etmək (və əksi)

Yeni başlayanlar çox vaxt hər şeyi tool edir, çünki bu "daha güclüdür". Lakin `get_file_content(path)` tool-u əvəzinə `file://...` resource daha yaxşıdır — istifadəçi UI-dan seçə bilər və model onu yükləmək üçün reasoning sərf etmir.

Əksinə, "Slack kanalına mesaj göndər" resource olmamalıdır — bu yan təsirli hərəkətdir, tool olmalıdır.

### 3. Sonsuz pagination olmayan siyahılar

`tools/list` 500 tool qaytarsa, model hansını çağıracağını seçməkdə çətinlik çəkəcək — context-də çox yer tutur. Maksimum 30-50 tool məsləhətdir. Daha çoxu varsa, categorization edin və ya ayrı MCP serverlərinə bölün.

### 4. Sensitive data-nı resource-da ifşa etmək

Resource məzmunu **tam kontekst pəncərəsinə yüklənir**. Əgər PII, secret və ya auth token var, redact edin:

```php
public function read(string $uri): array
{
    $content = $this->loadResource($uri);
    $content = $this->redactSecrets($content);
    return ['contents' => [['uri' => $uri, 'text' => $content]]];
}

private function redactSecrets(string $text): string
{
    $patterns = [
        '/sk-[a-zA-Z0-9]{32,}/'           => '[REDACTED_API_KEY]',
        '/ghp_[a-zA-Z0-9]{36}/'           => '[REDACTED_GH_TOKEN]',
        '/"password"\s*:\s*"[^"]+"/'       => '"password": "[REDACTED]"',
    ];
    return preg_replace(array_keys($patterns), array_values($patterns), $text);
}
```

### 5. Prompt-ları ad şəklində tool ilə qarışdırmaq

Developer tez-tez `/summarize` adlı prompt yaradır, amma eyni zamanda `summarize` tool yaradır. İstemci UI-da bu iki primitive fərqli yerlərdə görünür, istifadəçi hansını istifadə edəcəyini bilmir. Qayda: **eyni ad istifadə etməyin**.

### 6. Resource URI-də local state-ə güvənmək

`file://tmp/session_42` kimi URI ephemeral-dir — server restart olanda itir. URI-lər **stabil və reproducible** olmalıdır. Ephemeral data tool response-da qaytarılsın, resource kimi yox.

### 7. Binary data-nı text kimi qaytarmaq

PDF və ya şəkli `text` sahəsində base64 olaraq qaytarmaq səhvdir. `blob` sahəsi və düzgün `mimeType` istifadə edin — istemci necə göstərəcəyini bu əsasda qərar verir.

### 8. Tools arasında implicit ordering

Əgər `create_order` tool-u `validate_cart` tool-unu əvvəlcə çağırmağı tələb edirsə, bunu description-da açıq yazın:

```json
{
  "name": "create_order",
  "description": "Sifariş yaradır. ƏN SON `validate_cart` uğurlu olduqda çağırın — validasiya olmasa 400 qaytaracaq."
}
```

### 9. Tool schema-da over-engineering

100 sahəli schema model üçün çətin oxunur. Əsas sahələri tələb edin, digərlərini optional edin və description ilə izah edin:

```json
{
  "inputSchema": {
    "properties": {
      "query": { "type": "string" },
      "options": {
        "type": "object",
        "description": "Əlavə seçimlər. Adi istifadə üçün lazım deyil.",
        "properties": { "limit": {"type":"integer"}, "offset": {"type":"integer"} }
      }
    },
    "required": ["query"]
  }
}
```

### 10. Error response-da stack trace qaytarmaq

```php
// Pis — LLM-ə içəri detal sızdırır
return ['isError' => true, 'content' => [['type' => 'text', 'text' => $exception->getTraceAsString()]]];

// Yaxşı — sanitize edilmiş xəta
Log::error('MCP tool error', ['exception' => $exception]);
return ['isError' => true, 'content' => [['type' => 'text', 'text' => 'Əməliyyat uğursuz oldu: ' . $exception->getMessage()]]];
```

---

## Yekun: Senior Qayda Dəsti

1. **Tool = fel + model qərar verir**. Description-da "when to use" yaz.
2. **Resource = isim + istifadəçi seçir**. URI stabil olsun, binary üçün blob istifadə et.
3. **Prompt = slash command + istifadəçi çağırır**. Multi-message skeleti qurmaq üçün güclüdür.
4. **Eyni imkanı iki primitive kimi ifşa edə bilərsən** (tool + resource hibrid) — müxtəlif istemcilərə dəstək üçün.
5. **Resource templates + completions** istifadəçi təcrübəsini çox artırır — adi list-lərdən qabağa keç.
6. **Roots hörmətini protokol səviyyəsində saxla** — kənara çıxmaq təhlükəsizlik pozuntusudur.
7. **Subscriptions** canlı data üçün, lakin resource-ləri ephemeral etməyə bəhanə deyil.
8. **Sensitive data-nı redact et** — resource məzmunu tam context-ə yüklənir.
9. **Schema-nı sadə saxla** — model 10 sahəli tool-u 100 sahəli tool-dan daha yaxşı çağırır.
10. **Error-ları sanitize et** — stack trace LLM-ə getməsin.

Növbəti addım: testing və debugging nümunələri üçün `10-mcp-testing-debugging.md` faylına bax.
