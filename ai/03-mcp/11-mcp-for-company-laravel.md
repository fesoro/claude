# Şirkətin üçün MCP Server — Laravel Model/DB/Service-lərini Aç (Lead)

> **Senaryo**: Şirkətinin Laravel monolitidir. İçində `customers`, `orders`, `support_tickets`, `invoices` cədvəlləri, business logic service-ləri, S3-də PDF-lər var. İstəyin: Claude Desktop, Cursor və ya öz daxili AI agent-in bu məlumatları sorğulaya və üzərində hərəkət edə bilsin — SQL yazmadan, REST endpoint-lər qurmadan. **MCP bunun üçündür**.

---

## Mündəricat

1. [Arxitektura Qərarları](#architecture)
2. [Paket Seçmək — php-mcp/laravel](#package)
3. [Tool-ları Eloquent Model-lərdən Üretmək](#tools-from-models)
4. [Resource-ları URI-lərlə Aç](#resources)
5. [Prompt-ları Slash Command Kimi](#prompts)
6. [Autentifikasiya və Tenant Scoping](#auth)
7. [Production Deployment](#deployment)
8. [Claude Desktop/Cursor-dan Qoşul](#connect)
9. [Canlı İstifadə Transkripti](#transcript)
10. [Observability və Audit](#observability)

---

## Arxitektura <a name="architecture"></a>

```
┌──────────────────────┐     ┌─────────────────────────────────┐
│  Claude Desktop /    │     │     Laravel Monolit (mövcud)    │
│  Cursor / Custom     │◄───►│                                 │
│  AI Agent            │MCP  │  ┌──────────────────────────┐   │
│  (istemci)           │HTTP │  │   Mövcud kodun:          │   │
└──────────────────────┘     │  │   - Models (Customer,    │   │
                             │  │     Order, Ticket, ...)  │   │
                             │  │   - Services             │   │
                             │  │   - Policies             │   │
                             │  └──────────────────────────┘   │
                             │  ┌──────────────────────────┐   │
                             │  │   YENİ: MCP Qatı         │   │
                             │  │   - Tools wrapper        │   │
                             │  │   - Resources registry   │   │
                             │  │   - Prompts              │   │
                             │  │   - Auth middleware      │   │
                             │  └──────────────────────────┘   │
                             │                                 │
                             │  Database │ Redis │ S3          │
                             └─────────────────────────────────┘
```

**Əsas qərarlar**:
- **Mövcud koduna toxunma** — MCP qatı mövcud model/service-lərin üstündə nazik adapter-dir
- **Streamable HTTP transport** (stdio deyil) — cloud-dan Claude Desktop/Cursor istemciləri qoşula bilsin
- **Bearer token auth** — hər istifadəçinin öz token-i, token → tenant + role mapping
- **Tool-lar Policy-ə tabe olur** — mövcud `CustomerPolicy::view()` MCP tool çağırışı zamanı yenidən işə düşür
- **Read-heavy, write-cautious** — oxuma tool-ları sərbəst; yazma tool-ları explicit confirmation tələb edir

---

## Paket Seçmək <a name="package"></a>

PHP ekosistemində 2026-04 tarixi ilə əsas paket: **`php-mcp/laravel`** (php-mcp/server üzərində qurulub).

```bash
composer require php-mcp/laravel
php artisan vendor:publish --tag=mcp-config
php artisan vendor:publish --tag=mcp-routes
```

`config/mcp.php`:

```php
return [
    'server' => [
        'name' => 'acme-company-mcp',
        'version' => '1.0.0',
        'instructions' => <<<MD
            Acme şirkətinin daxili MCP server-i. Mövcud tool-lar ilə
            müştəri, sifariş, ticket və invoice məlumatlarını sorğulaya,
            yeni ticket yarada, sifariş statusunu yoxlaya bilərsən.
            **Bütün yazma əməliyyatları auditə tabedir.**
            MD,
    ],
    'transport' => env('MCP_TRANSPORT', 'http'), // 'http' | 'stdio'
    'http' => [
        'path' => '/mcp',
        'middleware' => ['auth:mcp-token', 'mcp.tenant'],
    ],
    'discovery' => [
        'paths' => [
            app_path('Mcp/Tools'),
            app_path('Mcp/Resources'),
            app_path('Mcp/Prompts'),
        ],
    ],
    'capabilities' => [
        'tools' => ['listChanged' => true],
        'resources' => ['subscribe' => true, 'listChanged' => true],
        'prompts' => ['listChanged' => true],
    ],
];
```

---

## Tool-ları Eloquent Model-lərdən Üretmək <a name="tools-from-models"></a>

### Prinsip: bir Tool = bir use-case, bir model deyil

`Customer` model-i üçün CRUD endpoint-ləri açmaq ANTİ-PATTERN-dir. Bunun əvəzinə LLM-in faktiki yerinə yetirəcəyi vəzifələrə fokuslan:

| Bad (CRUD-lu) | Good (use-case) |
|---------------|-----------------|
| `create_customer` | `register_new_customer` |
| `read_customer` | `search_customer_by_email_or_phone` |
| `update_customer` | `change_customer_billing_address` |
| `delete_customer` | — (dəstəklənmir; support-a handoff) |

### Nümunə 1: `search_customer` Tool

```php
<?php
// app/Mcp/Tools/SearchCustomerTool.php

namespace App\Mcp\Tools;

use App\Models\Customer;
use PhpMcp\Server\Attributes\McpTool;
use Illuminate\Validation\ValidationException;

class SearchCustomerTool
{
    #[McpTool(
        name: 'search_customer',
        description: 'Müştərini email, telefon, və ya ad ilə axtar.
            Nəticə maksimum 10 müştəri qaytarır. Müştəri tapılmasa,
            boş siyahı və "suggest_create_ticket" təklifi qaytarır.
            Həssas sahələr (password_hash, national_id) gizlədilir.'
    )]
    public function __invoke(
        string $query,                    // e.g. "farid@acme.az" or "+994501234567" or "Fərid"
        ?string $field = null,            // 'email' | 'phone' | 'name' | null (auto-detect)
        int $limit = 10,
    ): array {
        $user = auth()->user();
        $this->authorize($user, 'search', Customer::class);

        $q = Customer::query()
            ->where('tenant_id', $user->tenant_id)  // multi-tenant scope
            ->limit(min($limit, 10));

        match ($field ?? $this->autoDetect($query)) {
            'email' => $q->where('email', 'ilike', "%{$query}%"),
            'phone' => $q->where('phone', 'like', "%{$query}%"),
            'name'  => $q->where(fn($q2) =>
                $q2->where('first_name', 'ilike', "%{$query}%")
                   ->orWhere('last_name', 'ilike', "%{$query}%")),
            default => throw ValidationException::withMessages([
                'field' => 'Unknown search field',
            ]),
        };

        $customers = $q->get()->map(fn($c) => [
            'id' => $c->ulid,                     // heç vaxt internal int ID açma
            'email' => $c->email,
            'phone' => $this->redactPhone($c->phone),
            'name' => $c->full_name,
            'created_at' => $c->created_at->toIso8601String(),
            'uri' => "customer://{$c->ulid}",     // Resource URI LLM-in oxuya biləcəyi
        ])->toArray();

        if (empty($customers)) {
            return [
                'customers' => [],
                'suggestion' => 'Müştəri tapılmadı. '
                    . 'İstifadəçiyə support ticket yaratmağı təklif et.',
            ];
        }

        return ['customers' => $customers];
    }

    private function autoDetect(string $q): string
    {
        if (filter_var($q, FILTER_VALIDATE_EMAIL)) return 'email';
        if (preg_match('/^\+?\d{7,}$/', $q)) return 'phone';
        return 'name';
    }

    private function redactPhone(string $phone): string
    {
        // "+994501234567" → "+9945012***567"
        return substr($phone, 0, -6) . '***' . substr($phone, -3);
    }

    private function authorize($user, $ability, $subject): void
    {
        if (!$user->can($ability, $subject)) {
            throw new \RuntimeException("Sizin {$ability} icazəniz yoxdur.");
        }
    }
}
```

**Niyə belə yazılıb**:
- **Tenant scope** — hər sorğu avtomatik tenant ID ilə filter olunur (prod-da global scope kimi)
- **ULID açıq, int ID qapalı** — enumeration hücumlarının qarşısını alır
- **Redaction inline** — LLM həssas məlumat görmür; audit loglara full phone yazılmır
- **Boş nəticə üçün "suggestion"** — LLM nə edəcəyini bilsin (halusinasiya etməsin)
- **Description vəzifəyönümlü** — LLM model-in nə etdiyini və məhdudiyyətlərini bilir

### Nümunə 2: `create_support_ticket` Tool (Yazma Əməliyyatı)

```php
#[McpTool(
    name: 'create_support_ticket',
    description: 'Müştəri üçün yeni support ticket yarat.
        Tələb: customer_uri, subject, body, priority (low|normal|high|urgent).
        Qayıdır: ticket_id və ticket URI. Urgent prioritet yalnız müştəri
        özü tələb etdikdə və ya ödəniş/kritik sistem problemi olduqda təyin et.
        Ticket yaradıldıqdan sonra ilgili komandaya avtomatik notifikasiya gedir.'
)]
public function __invoke(
    string $customer_uri,       // "customer://01HM..."
    string $subject,            // max 200 char
    string $body,               // max 5000 char
    string $priority = 'normal', // enum
    array $tags = [],           // ['billing', 'login-issue']
): array {
    $user = auth()->user();
    $customer = $this->resolveCustomerFromUri($customer_uri, $user->tenant_id);

    $this->authorize($user, 'create', SupportTicket::class);

    // Yazma əməliyyatı → audit
    $ticket = DB::transaction(function () use ($customer, $subject, $body, $priority, $tags, $user) {
        $t = SupportTicket::create([
            'ulid' => Str::ulid(),
            'tenant_id' => $user->tenant_id,
            'customer_id' => $customer->id,
            'subject' => Str::limit($subject, 200, ''),
            'body' => Str::limit($body, 5000, ''),
            'priority' => $priority,
            'status' => 'open',
            'created_by_user_id' => $user->id,
            'created_by_channel' => 'mcp',
        ]);

        foreach ($tags as $tag) {
            $t->tags()->attach(Tag::firstOrCreate(['slug' => Str::slug($tag)]));
        }

        AuditLog::create([
            'actor_type' => 'mcp_user',
            'actor_id' => $user->id,
            'action' => 'ticket.created',
            'subject_type' => 'ticket',
            'subject_id' => $t->ulid,
            'meta' => compact('priority', 'tags'),
        ]);

        return $t;
    });

    TicketCreated::dispatch($ticket);  // Laravel event → notify team

    return [
        'ticket_id' => $ticket->ulid,
        'uri' => "ticket://{$ticket->ulid}",
        'status' => 'open',
        'priority' => $ticket->priority,
        'message' => "Ticket yaradıldı. Support komandası 15 dəqiqə içində baxacaq.",
    ];
}
```

### Nümunə 3: `get_order_status` — Sadə Lookup

```php
#[McpTool(
    name: 'get_order_status',
    description: 'Sifariş ULID-i ilə statusu və izləmə məlumatını qaytarır.
        Status dəyərləri: pending, paid, preparing, shipped, delivered, cancelled.
        Sifariş tapılmasa, "not_found" qaytarır (müştəriyə "sifariş №-ni yoxlayın" de).'
)]
public function __invoke(string $order_ulid): array
{
    $user = auth()->user();
    $order = Order::with('tracking')
        ->where('tenant_id', $user->tenant_id)
        ->where('ulid', $order_ulid)
        ->first();

    if (!$order) {
        return ['status' => 'not_found'];
    }

    $this->authorize($user, 'view', $order);

    return [
        'order_id' => $order->ulid,
        'status' => $order->status,
        'total' => $order->total_azn,
        'currency' => 'AZN',
        'placed_at' => $order->created_at->toIso8601String(),
        'tracking' => $order->tracking?->only(['carrier', 'tracking_number', 'url']),
        'uri' => "order://{$order->ulid}",
    ];
}
```

---

## Resource-ları URI-lərlə Aç <a name="resources"></a>

Tool qaytardığı `uri` dəyərləri əslində **Resource**-lara istinaddır. İstifadəçi və ya LLM bunlara abunə ola, oxuya bilər.

### Customer Resource

```php
<?php
// app/Mcp/Resources/CustomerResource.php

namespace App\Mcp\Resources;

use App\Models\Customer;
use PhpMcp\Server\Attributes\McpResource;
use PhpMcp\Server\Attributes\McpResourceTemplate;

class CustomerResource
{
    #[McpResourceTemplate(
        uriTemplate: 'customer://{ulid}',
        name: 'Customer Detail',
        description: 'Tam müştəri profili: əlaqə, ödəniş plan, son sifarişlər.',
        mimeType: 'application/json',
    )]
    public function read(string $ulid): array
    {
        $user = auth()->user();
        $c = Customer::with(['orders' => fn($q) => $q->latest()->limit(10)])
            ->where('tenant_id', $user->tenant_id)
            ->where('ulid', $ulid)
            ->firstOrFail();

        $this->authorize($user, 'view', $c);

        return [
            'contents' => [[
                'uri' => "customer://{$c->ulid}",
                'mimeType' => 'application/json',
                'text' => json_encode([
                    'id' => $c->ulid,
                    'name' => $c->full_name,
                    'email' => $c->email,
                    'phone' => $this->redactPhone($c->phone),
                    'plan' => $c->subscription_plan,
                    'country' => $c->country,
                    'lifetime_value_azn' => $c->ltv_azn,
                    'recent_orders' => $c->orders->map->only(['ulid', 'status', 'total_azn', 'created_at']),
                ], JSON_PRETTY_PRINT),
            ]],
        ];
    }

    #[McpResource(
        uri: 'customer://list/top-spenders',
        name: 'Top 100 Spender Customers',
        description: 'Bu ayın ən çox xərcləyən 100 müştərisi. Hər saat yenilənir.',
        mimeType: 'application/json',
    )]
    public function topSpenders(): array
    {
        $user = auth()->user();
        $customers = Cache::remember("mcp:top-spenders:{$user->tenant_id}", 3600, fn() =>
            Customer::where('tenant_id', $user->tenant_id)
                ->whereHas('orders', fn($q) => $q->whereMonth('created_at', now()->month))
                ->withSum(['orders as month_total' => fn($q) => $q->whereMonth('created_at', now()->month)], 'total_azn')
                ->orderByDesc('month_total')
                ->limit(100)
                ->get(['ulid', 'first_name', 'last_name', 'email'])
        );

        return [
            'contents' => [[
                'uri' => 'customer://list/top-spenders',
                'mimeType' => 'application/json',
                'text' => json_encode($customers, JSON_PRETTY_PRINT),
            ]],
        ];
    }
}
```

### Invoice PDF Resource (Binary)

```php
#[McpResourceTemplate(
    uriTemplate: 'invoice://{ulid}',
    name: 'Invoice PDF',
    mimeType: 'application/pdf',
)]
public function readInvoice(string $ulid): array
{
    $user = auth()->user();
    $invoice = Invoice::where('tenant_id', $user->tenant_id)
        ->where('ulid', $ulid)
        ->firstOrFail();

    $this->authorize($user, 'view', $invoice);

    $pdf = Storage::disk('s3')->get($invoice->pdf_path);

    return [
        'contents' => [[
            'uri' => "invoice://{$ulid}",
            'mimeType' => 'application/pdf',
            'blob' => base64_encode($pdf),   // MCP binary kontenti base64-ə kodlaşdırır
        ]],
    ];
}
```

---

## Prompt-ları Slash Command Kimi <a name="prompts"></a>

Prompt-lar LLM-in avtomatik çağırdığı tool-lar DEYİL — istifadəçi `/escalate-ticket` kimi explicit seçir. Claude Desktop-da slash menyuda görünür.

```php
<?php
// app/Mcp/Prompts/EscalateTicketPrompt.php

namespace App\Mcp\Prompts;

use PhpMcp\Server\Attributes\McpPrompt;

class EscalateTicketPrompt
{
    #[McpPrompt(
        name: 'escalate_ticket',
        description: 'Ticket-i L2 dəstəyə eskalasiya etmək üçün context hazırla.',
    )]
    public function __invoke(string $ticket_ulid): array
    {
        $user = auth()->user();
        $ticket = SupportTicket::with(['customer', 'messages', 'tags'])
            ->where('tenant_id', $user->tenant_id)
            ->where('ulid', $ticket_ulid)
            ->firstOrFail();

        return [
            'description' => "Ticket {$ticket_ulid} eskalasiya context-i",
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        'type' => 'text',
                        'text' => <<<PROMPT
                        Aşağıdakı support ticket-i L2 komandasına eskalasiya et.
                        Xülasə yaz, təkrarlanan problemləri tap, müştərinin
                        lifetime value-sini qeyd et, və tövsiyə olunan növbəti addımı göstər.

                        Ticket: {$ticket->ulid}
                        Mövzu: {$ticket->subject}
                        Prioritet: {$ticket->priority}
                        Müştəri: {$ticket->customer->full_name} (LTV: {$ticket->customer->ltv_azn} AZN)
                        Yaradılıb: {$ticket->created_at->diffForHumans()}

                        Mesajlar:
                        {$this->formatMessages($ticket->messages)}

                        Taglar: {$ticket->tags->pluck('slug')->implode(', ')}

                        Format:
                        ## Xülasə (2-3 cümlə)
                        ## Təkrarlanan Problemlər
                        ## Tövsiyə Olunan Addım
                        ## L2 üçün Əlavə Context
                        PROMPT,
                    ],
                ],
            ],
        ];
    }

    private function formatMessages($messages): string
    {
        return $messages->map(fn($m) => "[{$m->role}]: {$m->body}")->implode("\n");
    }
}
```

**Digər faydalı prompt-lar**:
- `/customer_summary {customer_ulid}` — müştəri haqqında 1 paragraph-lıq xülasə
- `/refund_analysis {order_ulid}` — refund tələbinin qəbul edilib-edilməməsi üçün qərar kömək
- `/onboarding_email {customer_ulid}` — yeni müştəri üçün xüsusiləşdirilmiş welcome email-i draft

---

## Autentifikasiya və Tenant Scoping <a name="auth"></a>

### Bearer Token Auth (Laravel Sanctum)

```php
// routes/mcp.php (published by php-mcp/laravel)

use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'mcp.tenant'])
    ->post('/mcp', [\PhpMcp\Laravel\HttpController::class, 'handle']);
```

### Custom `mcp.tenant` Middleware

```php
<?php
// app/Http/Middleware/ResolveMcpTenant.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ResolveMcpTenant
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Tenant scope Eloquent-ə — bütün sorğular avtomatik filter olunacaq
        app()->instance('current_tenant_id', $user->tenant_id);

        // Rate limit per user
        if (app('mcp.rate_limiter')->tooManyAttempts("mcp:{$user->id}", 600)) {
            return response()->json(['error' => 'Rate limit exceeded'], 429);
        }

        return $next($request);
    }
}
```

### Token Yaratmaq (İstifadəçiyə)

```php
// Artisan command: php artisan mcp:token:create {user_email}

$user = User::where('email', $email)->firstOrFail();
$token = $user->createToken('mcp-claude-desktop', [
    'mcp:read:customers',
    'mcp:read:orders',
    'mcp:write:tickets',
    // — lakin "mcp:write:invoices" verilmir (yüksək risk)
])->plainTextToken;

$this->info("Token: {$token}");
$this->info("Bu token-i Claude Desktop config-ə əlavə et.");
```

### Policy-lər MCP Tool-ları üçün

Artıq `CustomerPolicy` varsa, MCP tool onu yenidən istifadə edir. Ayrıca MCP Policy yazmaq lazım deyil.

```php
// app/Policies/CustomerPolicy.php (mövcud)

public function view(User $user, Customer $customer): bool
{
    return $user->tenant_id === $customer->tenant_id
        && ($user->hasRole('admin')
            || $user->hasRole('support')
            || $customer->assigned_user_id === $user->id);
}
```

MCP tool içində `$user->can('view', $customer)` çağırışı bunu yoxlayır.

---

## Production Deployment <a name="deployment"></a>

### Docker Compose

```yaml
# docker-compose.prod.yml (fragment)

services:
  php-fpm:
    # ... mövcud konfig

  nginx:
    volumes:
      - ./nginx/mcp.conf:/etc/nginx/conf.d/mcp.conf
    # MCP endpoint-i eyni domen-də açılır: https://api.acme.az/mcp
```

### Nginx Config (HTTPS, Long Keep-alive)

```nginx
# nginx/mcp.conf

server {
    listen 443 ssl http2;
    server_name api.acme.az;

    ssl_certificate /etc/letsencrypt/live/api.acme.az/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/api.acme.az/privkey.pem;

    location /mcp {
        proxy_pass http://php-fpm:9000;
        include fastcgi_params;

        # Streamable HTTP üçün uzun timeout
        proxy_read_timeout 3600s;
        proxy_send_timeout 3600s;

        # Chunked transfer
        proxy_buffering off;
        proxy_cache off;

        # Keep-alive
        proxy_set_header Connection "";
        proxy_http_version 1.1;
    }
}
```

### Horizon + Queue

MCP tool-lar çox vaxt queue-ya işı ötürür (məs. `TicketCreated` event → `NotifyTeamJob`). Horizon supervisor konfiqurasiyası:

```php
// config/horizon.php (fragment)

'mcp-tools' => [
    'connection' => 'redis',
    'queue' => ['mcp-default', 'mcp-urgent'],
    'balance' => 'auto',
    'minProcesses' => 2,
    'maxProcesses' => 10,
],
```

---

## Claude Desktop/Cursor-dan Qoşul <a name="connect"></a>

### Claude Desktop

`~/Library/Application Support/Claude/claude_desktop_config.json` (macOS) və ya `%APPDATA%\Claude\claude_desktop_config.json` (Windows):

```json
{
  "mcpServers": {
    "acme-company": {
      "transport": "streamable-http",
      "url": "https://api.acme.az/mcp",
      "headers": {
        "Authorization": "Bearer ${ACME_MCP_TOKEN}"
      }
    }
  }
}
```

Token environment variable ilə — config file-də plaintext saxlama:
```bash
export ACME_MCP_TOKEN="1|abc123...xyz"
```

Claude Desktop restart → toolbar-da 🔌 ikona → `acme-company` görünür → tool sayı göstərilir.

### Cursor

`~/.cursor/mcp.json` (user-level) və ya `.cursor/mcp.json` (project-level):

```json
{
  "mcpServers": {
    "acme-company": {
      "transport": "http",
      "url": "https://api.acme.az/mcp",
      "headers": {
        "Authorization": "Bearer ${env:ACME_MCP_TOKEN}"
      }
    }
  }
}
```

### Claude Code

```bash
claude mcp add acme-company \
  --transport http \
  --url https://api.acme.az/mcp \
  --header "Authorization: Bearer $ACME_MCP_TOKEN"
```

---

## Canlı İstifadə Transkripti <a name="transcript"></a>

Claude Desktop-da adminin (support manageri) dialoqu:

```
👤 User: farid@acme.az email-i ilə müştəri tap, son sifarişinin statusu nədir?

🤖 Claude:
  [Tool: search_customer(query="farid@acme.az", field="email")]
  → {customers: [{id: "01HMXYZ...", name: "Fərid Məmmədov", uri: "customer://01HMXYZ..."}]}

  [Resource read: customer://01HMXYZ...]
  → {recent_orders: [{ulid: "01HMORD...", status: "shipped", total: 249.90, ...}]}

  Müştəri tapıldı: Fərid Məmmədov (farid@acme.az).
  Son sifarişi (01HMORD...): **shipped** statusunda, 249.90 AZN.

👤 User: Bu müştəri üçün yüksək prioritetli ticket yarat —
         "sifariş 3 gün gecikir, izləmə linki işləmir" mövzusunda.

🤖 Claude:
  [Tool: create_support_ticket(
      customer_uri="customer://01HMXYZ...",
      subject="Sifariş 3 gün gecikir, izləmə linki işləmir",
      body="Müştəri 01HMORD... sifarişinin 3 gün gecikdiyini bildirib...",
      priority="high",
      tags=["shipping-delay", "tracking-broken"])]
  → {ticket_id: "01HMT1K...", status: "open", message: "Ticket yaradıldı..."}

  Ticket yaradıldı: **01HMT1K...** (high priority).
  Shipping komandasına notifikasiya göndərildi.
  15 dəqiqə içində cavab gözlənilir.

👤 User: /escalate_ticket 01HMT1K...

🤖 Claude:
  [Prompt: escalate_ticket]
  ## Xülasə
  Fərid Məmmədov (LTV: 1,450 AZN, gold-tier customer) 249.90 AZN-lik sifarişinin...

  ## Təkrarlanan Problemlər
  Eyni carrier (AzerExpress) ilə son həftə 12 ticket açılıb shipping delay-lə.

  ## Tövsiyə Olunan Addım
  1. Carrier-dən SLA status al
  2. Müştəriyə 20% endirim kuponu təklif et
  3. Gold-tier customer olduğu üçün manager L2-yə yönləndir

  ## L2 üçün Əlavə Context
  ...
```

---

## Observability və Audit <a name="observability"></a>

### MCP Middleware Trace

```php
// app/Http/Middleware/LogMcpCalls.php

public function handle($request, Closure $next)
{
    $start = microtime(true);
    $response = $next($request);
    $duration = (microtime(true) - $start) * 1000;

    $payload = json_decode($request->getContent(), true);
    $method = $payload['method'] ?? 'unknown';
    $toolName = $payload['params']['name'] ?? null;

    Log::channel('mcp')->info('mcp.call', [
        'user_id' => $request->user()?->id,
        'tenant_id' => $request->user()?->tenant_id,
        'method' => $method,
        'tool' => $toolName,
        'duration_ms' => round($duration, 2),
        'status' => $response->status(),
    ]);

    // Prometheus metric
    app('metrics')->histogram('mcp_call_duration_ms', $duration, [
        'method' => $method,
        'tool' => $toolName ?? 'n/a',
    ]);

    return $response;
}
```

### Audit Log

Hər yazma tool-u `AuditLog::create(...)` çağırmalıdır (yuxarıda `create_support_ticket`-də nümunə). Filament-də oxuma ekranı:

```php
// app/Filament/Resources/AuditLogResource.php
// — actor (user), tool, subject, result, timestamp, IP
```

### Alert-lər

```
- MCP error rate > 5% → PagerDuty
- Tool latency p95 > 3s → Slack warning
- create_support_ticket volume 10x baseline → anomaly (promptinjection ola bilər)
```

---

## Xülasə

| Addım | Status |
|-------|--------|
| Composer paket quraşdır | `composer require php-mcp/laravel` |
| Tool-ları yaz (use-case yönümlü) | `app/Mcp/Tools/*.php` attribute-lərlə |
| Resource-ları URI template ilə | `customer://{ulid}`, `invoice://{ulid}` |
| Prompt-ları slash commands | `/escalate_ticket`, `/customer_summary` |
| Auth + tenant scoping | Sanctum + `mcp.tenant` middleware + Policy-lər |
| Production deploy | HTTPS, nginx streamable HTTP, Horizon |
| Klient config | Claude Desktop / Cursor / Code — bearer token |
| Audit + observability | Log middleware, AuditLog model, Prometheus |

**Əsas prinsip**: MCP server mövcud Laravel koduna toxunmadan üstündə nazik adapter qatıdır. Mövcud Policy-lərin, Service-lərin, Event-lərin hamısı dəyişməz qalır.

Növbəti addım — [07-mcp-clients-compared.md](./07-mcp-clients-compared.md): hansı istemcini hansı senarium üçün seçməli.

---

## Praktik Tapşırıqlar

### Tapşırıq 1: İlk Company Tool

Mövcud Laravel layihəsindəki `OrderService::getOrdersByCustomer(int $customerId)` metodunu MCP tool kimi aç. `get_customer_orders(customer_id: int)` adlı tool yaz. Laravel Policy ilə `auth user can view orders` yoxla. Claude Desktop-dan test et: "124 nömrəli müştərinin sifarişlərini göstər."

### Tapşırıq 2: Multi-Model Tool

`search_products(query, category?, price_max?)` tool-u implement et. Elasticsearch ya da Scout ilə axtarış et. Nəticəni paginasiya ilə qaytarır: `{data: [...], total, page}`. Sorğu boş qalanda, kateqoriya yanlış olanda, database bağlantısı uğursuz olanda necə davranır?

### Tapşırıq 3: Audit + Observability

Bütün MCP tool call-larını `AuditLog` modeli ilə qeyd et. Prometheus metrics endpoint-i əlavə et: `mcp_tool_calls_total{tool="get_orders"}`. Grafana dashboard-da real-time tool usage grafiki qur. Anomaliya aşkar ediləndə Slack webhook göndər.

---

## Əlaqəli Mövzular

- `05-mcp-server-build-php.md` — PHP MCP server qurmağın əsasları
- `08-mcp-oauth-auth.md` — Laravel Passport ilə MCP auth
- `09-mcp-security-patterns.md` — Company MCP-nin security layihəsi
- `07-mcp-clients-compared.md` — Komandada hansı client-i paylaşmaq
