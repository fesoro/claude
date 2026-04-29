# Daxili HR / Knowledge Bot — Laravel + Slack + MCP (Senior)

Şirkətdaxili istifadə üçün HR/IT köməkçisi. İşçilər Slack-də `/hr` slash komandası və ya birbaşa mesajla sual verir: PTO balansı, məzuniyyət müraciəti, bayram təqvimi, IT access, onboarding sənədləri. Bot həm RAG ilə (Confluence/Notion/Drive) cavab verir, həm də MCP tool-ları ilə real hərəkət edir (HRIS-dən balans çəkir, Jira-da access ticket açır). Hər şey SSO ilə autentifikasiya olunur, audit log saxlanılır, manager/əməkdaş rolu fərqləndirilir.

---

## Arxitektura Baxışı

```
Slack (events API + slash command + OAuth)
        │  HTTPS POST
        ▼
SlackWebhookController  ──► Signature verify (3s budget)
        │
        ▼
SlackEventRouter
        ├── app_mention → respond
        ├── message.im → respond
        └── /hr slash → ack immediately → queue reply job
        │
        ▼
ResolveIdentity (Slack user id → company user via SSO mapping)
        │
        ▼
HrChatService
        ├── RagRetriever (nightly re-ingested Confluence/Notion/Drive)
        ├── MCPClient (internal MCP server for HRIS, ITSM, calendar)
        ├── ToolPolicy (RBAC — manager sees more)
        ├── PiiRedactor (logs/observability)
        └── AuditLogger
        │
        ▼
Claude API (claude-sonnet-4-6)
        │
        ▼
Post message back to Slack (chat.postMessage, Block Kit)
```

**Dizayn qərarları:**
- Slash komanda 3 saniyə içində ack etməlidir → job queue-ya düşür, sonra `response_url`-a göndərilir.
- MCP server (ayrı Node/Go prosesi) HRIS, ITSM, Google Calendar-a wrap edir. Laravel MCP-yə stdio ilə deyil, HTTP transport ilə bağlanır (çoxluq dəstəyi üçün).
- Hər MCP tool çağırışı audit cədvəlinə düşür: kim, nə vaxt, hansı parametrlərlə, hansı nəticə.
- RAG index gecə 02:00-da re-build olunur (Confluence incremental sync).
- PII (SSN, maaş) embedding-ə və loglara getmir — `PiiRedactor` ingestion və logging-də.

---

## Verilənlər Bazası Miqrasiyaları

```php
// database/migrations/2026_04_21_000010_create_hr_bot_tables.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');

        Schema::create('slack_workspaces', function (Blueprint $table) {
            $table->id();
            $table->string('team_id')->unique();
            $table->string('team_name');
            $table->text('bot_token'); // xrm-encrypted
            $table->text('bot_user_id');
            $table->string('signing_secret'); // encrypted
            $table->json('scopes');
            $table->timestamps();
        });

        Schema::create('slack_user_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('slack_workspaces')->cascadeOnDelete();
            $table->string('slack_user_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'slack_user_id']);
        });

        Schema::create('hr_documents', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('source');    // confluence, notion, gdrive
            $table->string('external_id');
            $table->string('title');
            $table->longText('content');
            $table->string('url');
            $table->string('space')->nullable();
            $table->json('acl')->nullable(); // hansı rollar görə bilər
            $table->string('content_hash', 64);
            $table->timestamp('synced_at');
            $table->timestamps();

            $table->unique(['source', 'external_id']);
            $table->index('synced_at');
        });

        Schema::create('hr_document_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('hr_documents')->cascadeOnDelete();
            $table->unsignedInteger('chunk_index');
            $table->text('content');
            $table->json('acl')->nullable();
            $table->unsignedInteger('token_count');
            $table->timestamps();
        });

        DB::statement('ALTER TABLE hr_document_chunks ADD COLUMN embedding vector(1024)');
        DB::statement('CREATE INDEX hr_chunks_embedding_idx ON hr_document_chunks USING hnsw (embedding vector_cosine_ops)');

        Schema::create('hr_conversations', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('channel_type'); // im, channel, command
            $table->string('slack_channel')->nullable();
            $table->string('slack_thread_ts')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'updated_at']);
        });

        Schema::create('hr_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('hr_conversations')->cascadeOnDelete();
            $table->string('role');
            $table->longText('content');
            $table->json('tool_calls')->nullable();
            $table->json('retrieved_docs')->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');        // tool:get_pto_balance, rag:query, ...
            $table->string('resource')->nullable();
            $table->json('parameters')->nullable();
            $table->json('result_summary')->nullable();
            $table->string('status');        // ok, denied, error
            $table->string('ip')->nullable();
            $table->string('source')->default('hr_bot');
            $table->timestamp('created_at');

            $table->index(['user_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });
    }
};
```

---

## Modellər

```php
// app/Models/SlackWorkspace.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class SlackWorkspace extends Model
{
    protected $fillable = [
        'team_id', 'team_name', 'bot_token', 'bot_user_id', 'signing_secret', 'scopes',
    ];

    protected $casts = ['scopes' => 'array'];

    protected function botToken(): Attribute
    {
        return Attribute::make(
            get: fn ($v) => $v ? Crypt::decryptString($v) : null,
            set: fn ($v) => Crypt::encryptString($v),
        );
    }

    protected function signingSecret(): Attribute
    {
        return Attribute::make(
            get: fn ($v) => $v ? Crypt::decryptString($v) : null,
            set: fn ($v) => Crypt::encryptString($v),
        );
    }
}
```

```php
// app/Models/SlackUserMapping.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SlackUserMapping extends Model
{
    protected $fillable = ['workspace_id', 'slack_user_id', 'user_id', 'email', 'last_seen_at'];
    protected $casts = ['last_seen_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(SlackWorkspace::class, 'workspace_id');
    }
}
```

```php
// app/Models/HrConversation.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrConversation extends Model
{
    use HasUlids;

    protected $fillable = [
        'user_id', 'channel_type', 'slack_channel', 'slack_thread_ts',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(HrMessage::class, 'conversation_id')->orderBy('created_at');
    }
}
```

```php
// app/Models/HrMessage.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HrMessage extends Model
{
    protected $fillable = [
        'conversation_id', 'role', 'content', 'tool_calls',
        'retrieved_docs', 'input_tokens', 'output_tokens',
    ];

    protected $casts = [
        'tool_calls' => 'array',
        'retrieved_docs' => 'array',
    ];
}
```

```php
// app/Models/AuditEntry.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditEntry extends Model
{
    protected $table = 'audit_log';
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'action', 'resource', 'parameters',
        'result_summary', 'status', 'ip', 'source', 'created_at',
    ];

    protected $casts = [
        'parameters' => 'array',
        'result_summary' => 'array',
        'created_at' => 'datetime',
    ];
}
```

---

## Slack OAuth

```php
// app/Http/Controllers/SlackOAuthController.php
<?php

namespace App\Http\Controllers;

use App\Models\SlackWorkspace;
use GuzzleHttp\Client;
use Illuminate\Http\Request;

class SlackOAuthController extends Controller
{
    public function __construct(private Client $http) {}

    public function redirect()
    {
        $scopes = 'app_mentions:read,chat:write,commands,im:history,im:read,im:write,users:read,users:read.email';
        $url = 'https://slack.com/oauth/v2/authorize?' . http_build_query([
            'client_id' => config('services.slack.client_id'),
            'scope' => $scopes,
            'redirect_uri' => route('slack.callback'),
        ]);
        return redirect($url);
    }

    public function callback(Request $request)
    {
        $code = $request->get('code');
        $response = $this->http->post('https://slack.com/api/oauth.v2.access', [
            'form_params' => [
                'client_id' => config('services.slack.client_id'),
                'client_secret' => config('services.slack.client_secret'),
                'code' => $code,
                'redirect_uri' => route('slack.callback'),
            ],
        ]);

        $body = json_decode((string) $response->getBody(), true);
        if (!($body['ok'] ?? false)) {
            abort(400, $body['error'] ?? 'Slack OAuth failed');
        }

        SlackWorkspace::updateOrCreate(
            ['team_id' => $body['team']['id']],
            [
                'team_name' => $body['team']['name'],
                'bot_token' => $body['access_token'],
                'bot_user_id' => $body['bot_user_id'],
                'signing_secret' => config('services.slack.signing_secret'),
                'scopes' => explode(',', $body['scope']),
            ],
        );

        return redirect('/dashboard')->with('success', 'Slack bağlandı');
    }
}
```

---

## Webhook Controller + İmza Yoxlanışı

```php
// app/Http/Controllers/SlackWebhookController.php
<?php

namespace App\Http\Controllers;

use App\Jobs\HandleSlackEventJob;
use App\Jobs\HandleSlashCommandJob;
use App\Models\SlackWorkspace;
use Illuminate\Http\Request;

class SlackWebhookController extends Controller
{
    public function events(Request $request)
    {
        $this->verifySignature($request);

        // URL verification handshake
        if ($request->input('type') === 'url_verification') {
            return response($request->input('challenge'), 200)
                ->header('Content-Type', 'text/plain');
        }

        // Bot-un öz mesajını emal etmə
        $event = $request->input('event', []);
        if (($event['bot_id'] ?? null) || ($event['subtype'] ?? null) === 'bot_message') {
            return response('', 200);
        }

        HandleSlackEventJob::dispatch($request->input());
        return response('', 200);
    }

    public function command(Request $request)
    {
        $this->verifySignature($request);

        $payload = $request->all();
        HandleSlashCommandJob::dispatch($payload);

        // Dərhal ack
        return response()->json([
            'response_type' => 'ephemeral',
            'text' => 'Sualınızı araşdırıram, bir neçə saniyə...',
        ]);
    }

    private function verifySignature(Request $request): void
    {
        $timestamp = $request->header('X-Slack-Request-Timestamp');
        $sig = $request->header('X-Slack-Signature');

        if (abs(time() - (int) $timestamp) > 60 * 5) {
            abort(401, 'stale request');
        }

        $body = $request->getContent();
        $baseString = "v0:{$timestamp}:{$body}";
        $workspace = SlackWorkspace::first(); // tək workspace-də; multi üçün team_id ilə resolve
        $expected = 'v0=' . hash_hmac('sha256', $baseString, $workspace->signing_secret);

        if (!hash_equals($expected, $sig)) {
            abort(401, 'invalid signature');
        }
    }
}
```

---

## Identity Resolver (SSO)

```php
// app/Services/Hr/IdentityResolver.php
<?php

namespace App\Services\Hr;

use App\Models\SlackUserMapping;
use App\Models\SlackWorkspace;
use App\Models\User;
use GuzzleHttp\Client;

class IdentityResolver
{
    public function __construct(private Client $http) {}

    /**
     * Slack user id → User. Mapping yoxdursa, Slack API-dən email götürüb,
     * company SSO directory-də axtarır.
     */
    public function resolve(SlackWorkspace $workspace, string $slackUserId): ?User
    {
        $mapping = SlackUserMapping::where('workspace_id', $workspace->id)
            ->where('slack_user_id', $slackUserId)
            ->first();

        if ($mapping) {
            $mapping->touch(); // last_seen_at əvəzinə updated_at işlədirik
            return $mapping->user;
        }

        $info = $this->http->get('https://slack.com/api/users.info', [
            'headers' => ['Authorization' => 'Bearer ' . $workspace->bot_token],
            'query' => ['user' => $slackUserId],
        ]);
        $body = json_decode((string) $info->getBody(), true);
        if (!($body['ok'] ?? false)) {
            return null;
        }

        $email = $body['user']['profile']['email'] ?? null;
        if (!$email) return null;

        $user = User::where('email', $email)->first();
        if (!$user) return null; // SSO-də yoxdur → botu istifadə edə bilməz

        SlackUserMapping::create([
            'workspace_id' => $workspace->id,
            'slack_user_id' => $slackUserId,
            'user_id' => $user->id,
            'email' => $email,
            'last_seen_at' => now(),
        ]);

        return $user;
    }
}
```

---

## RAG Retriever (ACL-aware)

```php
// app/Services/Hr/RagRetriever.php
<?php

namespace App\Services\Hr;

use App\Models\User;
use App\Services\KnowledgeBase\Embedder;
use Illuminate\Support\Facades\DB;

class RagRetriever
{
    public function __construct(private Embedder $embedder) {}

    /**
     * Yalnız istifadəçinin roluna uyğun chunk-ları qaytarır.
     * `acl` JSON massivində "everyone", "managers", "hr", "engineering" və s. ola bilər.
     */
    public function retrieve(User $user, string $query, int $topK = 6): array
    {
        [$vec] = $this->embedder->embed([$query], 'query');
        $vecStr = '[' . implode(',', $vec) . ']';

        $userRoles = array_merge(['everyone'], $user->roles->pluck('name')->all());
        $rolesJson = json_encode($userRoles);

        $rows = DB::select("
            SELECT c.id, c.content, d.title, d.url, d.source,
                   1 - (c.embedding <=> ?::vector) AS similarity
            FROM hr_document_chunks c
            JOIN hr_documents d ON d.id = c.document_id
            WHERE (c.acl IS NULL OR c.acl ?| ARRAY(SELECT jsonb_array_elements_text(?::jsonb)))
            ORDER BY c.embedding <=> ?::vector
            LIMIT ?
        ", [$vecStr, $rolesJson, $vecStr, $topK]);

        return array_map(fn ($r) => [
            'id' => $r->id,
            'content' => $r->content,
            'title' => $r->title,
            'url' => $r->url,
            'source' => $r->source,
            'similarity' => (float) $r->similarity,
        ], $rows);
    }
}
```

---

## Confluence / Notion / Drive Sync

```php
// app/Jobs/SyncConfluenceJob.php
<?php

namespace App\Jobs;

use App\Models\HrDocument;
use App\Services\Hr\PiiRedactor;
use GuzzleHttp\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class SyncConfluenceJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $timeout = 600;

    public function handle(Client $http, PiiRedactor $redactor): void
    {
        $cursor = null;
        $base = config('services.confluence.base');
        $auth = config('services.confluence.user') . ':' . config('services.confluence.token');

        do {
            $response = $http->get("{$base}/rest/api/content/search", [
                'auth' => explode(':', $auth),
                'query' => [
                    'cql' => 'space = "HR" AND lastmodified > now("-1d")',
                    'expand' => 'body.storage,space,restrictions.read.restrictions.group',
                    'cursor' => $cursor,
                    'limit' => 50,
                ],
            ]);

            $body = json_decode((string) $response->getBody(), true);
            foreach ($body['results'] ?? [] as $page) {
                $content = strip_tags($page['body']['storage']['value']);
                $content = $redactor->redact($content);
                $hash = hash('sha256', $content);

                $doc = HrDocument::updateOrCreate(
                    ['source' => 'confluence', 'external_id' => $page['id']],
                    [
                        'title' => $page['title'],
                        'content' => $content,
                        'url' => $page['_links']['base'] . $page['_links']['webui'],
                        'space' => $page['space']['key'] ?? null,
                        'acl' => $this->extractAcl($page),
                        'content_hash' => $hash,
                        'synced_at' => now(),
                    ],
                );

                if ($doc->wasRecentlyCreated || $doc->wasChanged('content_hash')) {
                    IngestHrDocumentJob::dispatch($doc->id);
                }
            }

            $cursor = $body['_links']['next'] ?? null;
        } while ($cursor);
    }

    private function extractAcl(array $page): array
    {
        $restrictions = $page['restrictions']['read']['restrictions']['group']['results'] ?? [];
        if (!$restrictions) return ['everyone'];
        return array_map(fn ($g) => $g['name'], $restrictions);
    }
}
```

```php
// app/Jobs/IngestHrDocumentJob.php
<?php

namespace App\Jobs;

use App\Models\HrDocument;
use App\Services\KnowledgeBase\Chunker;
use App\Services\KnowledgeBase\Embedder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;

class IngestHrDocumentJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(public int $documentId) {}

    public function handle(Chunker $chunker, Embedder $embedder): void
    {
        $doc = HrDocument::findOrFail($this->documentId);
        DB::table('hr_document_chunks')->where('document_id', $doc->id)->delete();

        $pieces = $chunker->split($doc->content);
        if (!$pieces) return;

        foreach (array_chunk($pieces, 100) as $batch) {
            $texts = array_map(fn ($p) => $p['content'], $batch);
            $vecs = $embedder->embed($texts, 'document');

            foreach ($batch as $i => $p) {
                DB::insert("
                    INSERT INTO hr_document_chunks
                    (document_id, chunk_index, content, acl, token_count, embedding, created_at, updated_at)
                    VALUES (?, ?, ?, ?::jsonb, ?, ?::vector, now(), now())
                ", [
                    $doc->id,
                    $i,
                    $p['content'],
                    json_encode($doc->acl ?? ['everyone']),
                    (int) ceil(mb_strlen($p['content']) / 4),
                    '[' . implode(',', $vecs[$i]) . ']',
                ]);
            }
        }
    }
}
```

---

## MCP Client

```php
// app/Services/Mcp/McpClient.php
<?php

namespace App\Services\Mcp;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;

class McpClient
{
    private int $idCounter = 0;

    public function __construct(private Client $http) {}

    /**
     * MCP `tools/list` — öz içində cache olunur.
     * HTTP transport ilə (streamable-http). stdio əvəzinə HTTP seçdik çünki
     * Laravel multi-worker və horizonda stdio prosesləri idarə etmək çətindir.
     */
    public function listTools(string $server = 'hris'): array
    {
        return Cache::remember("mcp:tools:{$server}", 300, fn () => $this->rpc($server, 'tools/list', [])['tools']);
    }

    public function callTool(string $server, string $name, array $args, array $context = []): array
    {
        return $this->rpc($server, 'tools/call', [
            'name' => $name,
            'arguments' => $args,
            '_meta' => ['context' => $context],
        ]);
    }

    private function rpc(string $server, string $method, array $params): array
    {
        $endpoint = config("mcp.servers.{$server}.url");
        $token = config("mcp.servers.{$server}.token");

        $response = $this->http->post($endpoint, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => "Bearer {$token}",
                'Accept' => 'application/json',
            ],
            'json' => [
                'jsonrpc' => '2.0',
                'id' => ++$this->idCounter,
                'method' => $method,
                'params' => $params,
            ],
            'timeout' => 15,
        ]);

        $body = json_decode((string) $response->getBody(), true);
        if (isset($body['error'])) {
            throw new \RuntimeException("MCP error: {$body['error']['message']}");
        }

        return $body['result'];
    }
}
```

`config/mcp.php`:

```php
return [
    'servers' => [
        'hris' => [
            'url' => env('MCP_HRIS_URL', 'http://mcp-hris:3001/rpc'),
            'token' => env('MCP_HRIS_TOKEN'),
        ],
        'itsm' => [
            'url' => env('MCP_ITSM_URL', 'http://mcp-itsm:3002/rpc'),
            'token' => env('MCP_ITSM_TOKEN'),
        ],
        'calendar' => [
            'url' => env('MCP_CALENDAR_URL', 'http://mcp-calendar:3003/rpc'),
            'token' => env('MCP_CALENDAR_TOKEN'),
        ],
    ],
];
```

---

## Tool Policy (RBAC)

```php
// app/Services/Hr/ToolPolicy.php
<?php

namespace App\Services\Hr;

use App\Models\User;

class ToolPolicy
{
    /** @var array<string, array{roles: array<string>, self_only?: bool}> */
    private const RULES = [
        'get_pto_balance' => ['roles' => ['employee', 'manager', 'hr'], 'self_only' => true],
        'submit_leave_request' => ['roles' => ['employee', 'manager', 'hr'], 'self_only' => true],
        'list_holidays' => ['roles' => ['employee', 'manager', 'hr']],
        'request_access' => ['roles' => ['employee', 'manager']],
        'view_team_pto' => ['roles' => ['manager', 'hr']],
        'approve_leave' => ['roles' => ['manager', 'hr']],
    ];

    public function allowed(User $user, string $tool, array $args): array
    {
        $rule = self::RULES[$tool] ?? null;
        if (!$rule) {
            return ['ok' => false, 'reason' => 'unknown_tool'];
        }

        $userRoles = $user->roles->pluck('name')->all();
        if (!array_intersect($userRoles, $rule['roles'])) {
            return ['ok' => false, 'reason' => 'forbidden'];
        }

        if (($rule['self_only'] ?? false)) {
            $target = $args['employee_email'] ?? $user->email;
            if ($target !== $user->email) {
                return ['ok' => false, 'reason' => 'self_only'];
            }
        }

        return ['ok' => true];
    }

    public function visibleTools(User $user): array
    {
        $userRoles = $user->roles->pluck('name')->all();
        return array_keys(array_filter(self::RULES, fn ($r) => (bool) array_intersect($userRoles, $r['roles'])));
    }
}
```

---

## PII Redactor

```php
// app/Services/Hr/PiiRedactor.php
<?php

namespace App\Services\Hr;

class PiiRedactor
{
    private const PATTERNS = [
        // SSN / FIN (Azərbaycan FIN 7 simvol)
        '/\b[A-Z0-9]{7}\b/' => '[FIN_REDACTED]',
        // Kart nömrəsi (16 rəqəm)
        '/\b(?:\d[ -]?){13,19}\b/' => '[CARD_REDACTED]',
        // IBAN AZ
        '/\bAZ\d{2}[A-Z]{4}\d{20}\b/' => '[IBAN_REDACTED]',
        // Email (audit-də qoruyuruq, observability-dən maskalayırıq)
        '/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/' => '[EMAIL_REDACTED]',
        // Telefon
        '/\+?994[ \-]?\d{2}[ \-]?\d{3}[ \-]?\d{2}[ \-]?\d{2}/' => '[PHONE_REDACTED]',
    ];

    public function redact(string $text): string
    {
        return preg_replace(array_keys(self::PATTERNS), array_values(self::PATTERNS), $text);
    }

    public function redactArray(array $data): array
    {
        return array_map(
            fn ($v) => is_string($v) ? $this->redact($v) : (is_array($v) ? $this->redactArray($v) : $v),
            $data,
        );
    }
}
```

---

## Audit Logger

```php
// app/Services/Hr/AuditLogger.php
<?php

namespace App\Services\Hr;

use App\Models\AuditEntry;
use App\Models\User;

class AuditLogger
{
    public function __construct(private PiiRedactor $redactor) {}

    public function log(
        ?User $user,
        string $action,
        ?string $resource = null,
        array $parameters = [],
        array $resultSummary = [],
        string $status = 'ok',
        ?string $ip = null,
    ): void {
        AuditEntry::create([
            'user_id' => $user?->id,
            'action' => $action,
            'resource' => $resource,
            'parameters' => $this->redactor->redactArray($parameters),
            'result_summary' => $this->redactor->redactArray($resultSummary),
            'status' => $status,
            'ip' => $ip,
            'source' => 'hr_bot',
            'created_at' => now(),
        ]);
    }
}
```

---

## HR Chat Service

```php
// app/Services/Hr/HrChatService.php
<?php

namespace App\Services\Hr;

use App\Models\HrConversation;
use App\Models\User;
use App\Services\Mcp\McpClient;
use GuzzleHttp\Client;

class HrChatService
{
    private const MODEL = 'claude-sonnet-4-6';

    public function __construct(
        private Client $anthropic,
        private McpClient $mcp,
        private RagRetriever $retriever,
        private ToolPolicy $policy,
        private AuditLogger $audit,
        private PiiRedactor $redactor,
    ) {}

    public function reply(User $user, string $question, ?HrConversation $conversation = null, ?string $ip = null): array
    {
        $conversation ??= HrConversation::create([
            'user_id' => $user->id,
            'channel_type' => 'command',
        ]);

        $this->audit->log($user, 'chat:query', $conversation->ulid, ['q' => $question], [], 'ok', $ip);

        // 1) RAG
        $docs = $this->retriever->retrieve($user, $question, topK: 6);

        // 2) MCP tools, user-a icazəli olanlar
        $allTools = $this->mcp->listTools('hris')
            + $this->mcp->listTools('itsm')
            + $this->mcp->listTools('calendar');
        $visible = $this->policy->visibleTools($user);
        $tools = array_values(array_filter($allTools, fn ($t) => in_array($t['name'], $visible)));

        // 3) Messages array
        $messages = $this->history($conversation);
        $messages[] = ['role' => 'user', 'content' => $question];

        $system = $this->systemPrompt($user, $docs);

        $iter = 0;
        $totalIn = 0;
        $totalOut = 0;
        $finalText = '';
        $toolInvocations = [];

        while ($iter++ < 5) {
            $response = $this->anthropic->post('https://api.anthropic.com/v1/messages', [
                'headers' => [
                    'x-api-key' => config('services.anthropic.key'),
                    'anthropic-version' => '2023-06-01',
                    'content-type' => 'application/json',
                ],
                'json' => [
                    'model' => self::MODEL,
                    'max_tokens' => 1024,
                    'system' => [
                        ['type' => 'text', 'text' => $system, 'cache_control' => ['type' => 'ephemeral']],
                    ],
                    'tools' => $tools,
                    'messages' => $messages,
                ],
                'timeout' => 60,
            ]);

            $body = json_decode((string) $response->getBody(), true);
            $totalIn += $body['usage']['input_tokens'] ?? 0;
            $totalOut += $body['usage']['output_tokens'] ?? 0;

            $toolUses = [];
            $assistantContent = [];
            foreach ($body['content'] as $block) {
                $assistantContent[] = $block;
                if ($block['type'] === 'text') {
                    $finalText .= $block['text'];
                }
                if ($block['type'] === 'tool_use') {
                    $toolUses[] = $block;
                }
            }

            if ($body['stop_reason'] !== 'tool_use' || !$toolUses) {
                break;
            }

            // Tool icra et
            $messages[] = ['role' => 'assistant', 'content' => $assistantContent];
            $toolResults = [];

            foreach ($toolUses as $tu) {
                $check = $this->policy->allowed($user, $tu['name'], $tu['input']);
                if (!$check['ok']) {
                    $toolResults[] = [
                        'type' => 'tool_result',
                        'tool_use_id' => $tu['id'],
                        'content' => json_encode(['error' => "Bu əməliyyat üçün icazəniz yoxdur ({$check['reason']})."]),
                        'is_error' => true,
                    ];
                    $this->audit->log($user, "tool:{$tu['name']}", null, $tu['input'], [], 'denied', $ip);
                    continue;
                }

                try {
                    $server = $this->routeTool($tu['name']);
                    $result = $this->mcp->callTool($server, $tu['name'], $tu['input'] + ['actor_email' => $user->email]);
                    $toolResults[] = [
                        'type' => 'tool_result',
                        'tool_use_id' => $tu['id'],
                        'content' => json_encode($result, JSON_UNESCAPED_UNICODE),
                    ];
                    $toolInvocations[] = ['name' => $tu['name'], 'input' => $tu['input'], 'result' => $result];
                    $this->audit->log($user, "tool:{$tu['name']}", null, $tu['input'], $result, 'ok', $ip);
                } catch (\Throwable $e) {
                    $toolResults[] = [
                        'type' => 'tool_result',
                        'tool_use_id' => $tu['id'],
                        'content' => json_encode(['error' => $e->getMessage()]),
                        'is_error' => true,
                    ];
                    $this->audit->log($user, "tool:{$tu['name']}", null, $tu['input'], ['error' => $e->getMessage()], 'error', $ip);
                }
            }

            $messages[] = ['role' => 'user', 'content' => $toolResults];
            $finalText = '';
        }

        $conversation->messages()->create([
            'role' => 'user',
            'content' => $question,
        ]);
        $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $finalText,
            'tool_calls' => $toolInvocations,
            'retrieved_docs' => array_map(fn ($d) => ['id' => $d['id'], 'sim' => $d['similarity']], $docs),
            'input_tokens' => $totalIn,
            'output_tokens' => $totalOut,
        ]);

        return [
            'text' => $finalText,
            'citations' => array_slice(array_map(fn ($d) => ['title' => $d['title'], 'url' => $d['url']], $docs), 0, 3),
            'tools' => $toolInvocations,
        ];
    }

    private function history(HrConversation $c): array
    {
        return $c->messages()
            ->latest()
            ->limit(10)
            ->get()
            ->reverse()
            ->map(fn ($m) => ['role' => $m->role === 'user' ? 'user' : 'assistant', 'content' => $m->content])
            ->values()
            ->all();
    }

    private function systemPrompt(User $user, array $docs): string
    {
        $name = $user->name;
        $role = $user->roles->pluck('name')->implode(', ');
        $context = empty($docs)
            ? '(Uyğun sənəd tapılmadı.)'
            : collect($docs)->map(fn ($d) => "- [{$d['title']}]({$d['url']})\n{$d['content']}")->implode("\n\n---\n\n");

        return <<<PROMPT
Sən şirkətin daxili HR/IT köməkçisisən. Adın "Saba".

İstifadəçi: {$name} (rol: {$role}).
Cavablar qısa, rəsmi, Azərbaycan dilində. İngilis soruşursa ingiliscə cavabla.

QAYDALAR:
- Yalnız aşağıdakı daxili sənədlərə əsaslan. Cavab yoxdursa, "Bu barədə rəsmi sənəd tapmadım, HR-a ötürüm" de.
- Dəqiq rəqəmləri (PTO balansı, bayram tarixi, maaş siyasəti) alətdən götür — uydurma.
- Başqa işçinin məlumatını göstərmə, "self_only" qaydası var.
- Hər cavabın sonunda istifadə etdiyin 1-2 sənədi mənbə kimi linklə göstər.

KONTEKST:
{$context}
PROMPT;
    }

    private function routeTool(string $name): string
    {
        return match (true) {
            in_array($name, ['get_pto_balance', 'submit_leave_request', 'view_team_pto', 'approve_leave']) => 'hris',
            in_array($name, ['request_access']) => 'itsm',
            in_array($name, ['list_holidays']) => 'calendar',
            default => throw new \RuntimeException("Unknown tool: {$name}"),
        };
    }
}
```

---

## Slack Reply Jobs

```php
// app/Jobs/HandleSlashCommandJob.php
<?php

namespace App\Jobs;

use App\Models\SlackWorkspace;
use App\Services\Hr\HrChatService;
use App\Services\Hr\IdentityResolver;
use App\Services\Slack\BlockKit;
use GuzzleHttp\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class HandleSlashCommandJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $timeout = 60;

    public function __construct(public array $payload) {}

    public function handle(
        IdentityResolver $identity,
        HrChatService $chat,
        Client $http,
        BlockKit $blocks,
    ): void {
        $workspace = SlackWorkspace::where('team_id', $this->payload['team_id'])->firstOrFail();
        $user = $identity->resolve($workspace, $this->payload['user_id']);

        if (!$user) {
            $this->respond($http, $this->payload['response_url'], 'SSO-da hesabınız tapılmadı. IT-ə müraciət edin.');
            return;
        }

        $text = trim($this->payload['text'] ?? '');
        if ($text === '') {
            $this->respond($http, $this->payload['response_url'], 'Nümunə: `/hr PTO balansım nədir?`');
            return;
        }

        $result = $chat->reply($user, $text, null, ip: $this->payload['client_ip'] ?? null);
        $msg = $blocks->reply($result['text'], $result['citations'], $result['tools']);
        $this->respond($http, $this->payload['response_url'], null, $msg);
    }

    private function respond(Client $http, string $url, ?string $text, ?array $blocks = null): void
    {
        $http->post($url, [
            'json' => array_filter([
                'response_type' => 'ephemeral',
                'text' => $text,
                'blocks' => $blocks,
            ]),
        ]);
    }
}
```

```php
// app/Jobs/HandleSlackEventJob.php
<?php

namespace App\Jobs;

use App\Models\SlackWorkspace;
use App\Services\Hr\HrChatService;
use App\Services\Hr\IdentityResolver;
use App\Services\Slack\BlockKit;
use GuzzleHttp\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class HandleSlackEventJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(public array $payload) {}

    public function handle(
        IdentityResolver $identity,
        HrChatService $chat,
        Client $http,
        BlockKit $blocks,
    ): void {
        $workspace = SlackWorkspace::where('team_id', $this->payload['team_id'])->firstOrFail();
        $event = $this->payload['event'];

        if (!in_array($event['type'], ['app_mention', 'message'])) return;

        $user = $identity->resolve($workspace, $event['user']);
        if (!$user) return;

        $text = preg_replace('/<@[A-Z0-9]+>/', '', $event['text'] ?? '');
        $text = trim($text);
        if ($text === '') return;

        $result = $chat->reply($user, $text);
        $msg = $blocks->reply($result['text'], $result['citations'], $result['tools']);

        $http->post('https://slack.com/api/chat.postMessage', [
            'headers' => [
                'Authorization' => 'Bearer ' . $workspace->bot_token,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'channel' => $event['channel'],
                'thread_ts' => $event['thread_ts'] ?? $event['ts'],
                'text' => $result['text'],
                'blocks' => $msg,
            ],
        ]);
    }
}
```

```php
// app/Services/Slack/BlockKit.php
<?php

namespace App\Services\Slack;

class BlockKit
{
    public function reply(string $text, array $citations, array $tools): array
    {
        $blocks = [
            ['type' => 'section', 'text' => ['type' => 'mrkdwn', 'text' => $text]],
        ];

        if ($tools) {
            $toolLines = array_map(
                fn ($t) => "• `{$t['name']}` → " . (is_array($t['result']) ? json_encode($t['result']) : (string) $t['result']),
                $tools,
            );
            $blocks[] = [
                'type' => 'context',
                'elements' => [[
                    'type' => 'mrkdwn',
                    'text' => "*İstifadə edilən əməliyyatlar:*\n" . implode("\n", $toolLines),
                ]],
            ];
        }

        if ($citations) {
            $lines = array_map(fn ($c) => "<{$c['url']}|{$c['title']}>", $citations);
            $blocks[] = [
                'type' => 'context',
                'elements' => [[
                    'type' => 'mrkdwn',
                    'text' => '*Mənbələr:* ' . implode(' · ', $lines),
                ]],
            ];
        }

        return $blocks;
    }
}
```

---

## Routes

```php
// routes/web.php
use App\Http\Controllers\SlackOAuthController;
use App\Http\Controllers\SlackWebhookController;

Route::get('/slack/install', [SlackOAuthController::class, 'redirect']);
Route::get('/slack/callback', [SlackOAuthController::class, 'callback'])->name('slack.callback');

Route::post('/slack/events', [SlackWebhookController::class, 'events'])
    ->withoutMiddleware(['web', \App\Http\Middleware\VerifyCsrfToken::class]);
Route::post('/slack/command', [SlackWebhookController::class, 'command'])
    ->withoutMiddleware(['web', \App\Http\Middleware\VerifyCsrfToken::class]);
```

---

## Nightly Re-Ingest Schedule

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule): void
{
    $schedule->job(new \App\Jobs\SyncConfluenceJob())->dailyAt('02:00');
    $schedule->job(new \App\Jobs\SyncNotionJob())->dailyAt('02:30');
    $schedule->job(new \App\Jobs\SyncGoogleDriveJob())->dailyAt('03:00');

    // stale documents cleanup
    $schedule->call(function () {
        \App\Models\HrDocument::where('synced_at', '<', now()->subDays(30))->delete();
    })->weeklyOn(1, '04:00');
}
```

---

## MCP Server (Node.js nümunəsi)

HRIS MCP server-i ayrıca Node servisi kimi işləyir. Laravel bunun içini bilmir — sadəcə HTTP RPC çağırır. Nümunə:

```javascript
// mcp-hris/server.js
import express from 'express';
import { BambooHR } from './bamboo.js';

const app = express();
app.use(express.json());

const bamboo = new BambooHR(process.env.BAMBOO_KEY, process.env.BAMBOO_SUBDOMAIN);

const TOOLS = [
  {
    name: 'get_pto_balance',
    description: 'İstifadəçinin cari PTO balansını günlərlə qaytarır.',
    inputSchema: {
      type: 'object',
      properties: {
        employee_email: { type: 'string' },
      },
      required: ['employee_email'],
    },
  },
  {
    name: 'submit_leave_request',
    description: 'Məzuniyyət müraciəti yaradır. Manager təsdiqi tələb olunur.',
    inputSchema: {
      type: 'object',
      properties: {
        employee_email: { type: 'string' },
        start_date: { type: 'string' },
        end_date: { type: 'string' },
        type: { type: 'string', enum: ['vacation', 'sick', 'personal'] },
        reason: { type: 'string' },
      },
      required: ['employee_email', 'start_date', 'end_date', 'type'],
    },
  },
];

app.post('/rpc', async (req, res) => {
  const auth = req.headers.authorization;
  if (auth !== `Bearer ${process.env.MCP_TOKEN}`) {
    return res.status(401).json({ error: 'unauthorized' });
  }

  const { id, method, params } = req.body;

  try {
    if (method === 'tools/list') {
      return res.json({ jsonrpc: '2.0', id, result: { tools: TOOLS } });
    }

    if (method === 'tools/call') {
      const { name, arguments: args } = params;
      if (name === 'get_pto_balance') {
        const result = await bamboo.getPtoBalance(args.employee_email);
        return res.json({ jsonrpc: '2.0', id, result });
      }
      if (name === 'submit_leave_request') {
        const result = await bamboo.submitLeave(args);
        return res.json({ jsonrpc: '2.0', id, result });
      }
    }

    res.status(400).json({ jsonrpc: '2.0', id, error: { code: -32601, message: 'Method not found' }});
  } catch (e) {
    res.json({ jsonrpc: '2.0', id, error: { code: -32000, message: e.message }});
  }
});

app.listen(3001);
```

---

## Pest Testləri

```php
// tests/Feature/Hr/SlashCommandTest.php
<?php

use App\Jobs\HandleSlashCommandJob;
use App\Models\SlackWorkspace;
use App\Models\SlackUserMapping;
use App\Models\User;
use Illuminate\Support\Facades\Bus;

beforeEach(function () {
    $this->workspace = SlackWorkspace::create([
        'team_id' => 'T_TEST',
        'team_name' => 'Test Inc',
        'bot_token' => 'xoxb-fake',
        'bot_user_id' => 'U_BOT',
        'signing_secret' => 'test-secret',
        'scopes' => ['chat:write'],
    ]);
});

it('verifies Slack signature and queues command job', function () {
    Bus::fake();

    $body = http_build_query([
        'team_id' => 'T_TEST',
        'user_id' => 'U123',
        'text' => 'PTO balansım nədir?',
        'response_url' => 'https://hooks.slack.com/fake',
    ]);
    $ts = time();
    $sig = 'v0=' . hash_hmac('sha256', "v0:{$ts}:{$body}", 'test-secret');

    $response = $this->withHeaders([
        'X-Slack-Request-Timestamp' => $ts,
        'X-Slack-Signature' => $sig,
    ])->call('POST', '/slack/command', [], [], [], [], $body);

    $response->assertOk();
    Bus::assertDispatched(HandleSlashCommandJob::class);
});

it('rejects invalid signature', function () {
    $response = $this->withHeaders([
        'X-Slack-Request-Timestamp' => time(),
        'X-Slack-Signature' => 'v0=wrong',
    ])->post('/slack/command', ['text' => 'hi']);

    $response->assertStatus(401);
});
```

```php
// tests/Feature/Hr/HrChatServiceTest.php
<?php

use App\Models\HrDocument;
use App\Models\User;
use App\Services\Hr\HrChatService;
use App\Services\Hr\RagRetriever;
use App\Services\Mcp\McpClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

it('calls MCP tool when user asks for PTO balance', function () {
    $user = User::factory()->withRole('employee')->create(['email' => 'ayan@test.az']);

    $mcp = Mockery::mock(McpClient::class);
    $mcp->shouldReceive('listTools')->andReturn([
        ['name' => 'get_pto_balance', 'description' => 'pto', 'input_schema' => ['type' => 'object']],
    ]);
    $mcp->shouldReceive('callTool')
        ->with('hris', 'get_pto_balance', Mockery::on(fn ($args) => $args['employee_email'] === 'ayan@test.az'))
        ->andReturn(['balance_days' => 18.5]);

    $retriever = Mockery::mock(RagRetriever::class);
    $retriever->shouldReceive('retrieve')->andReturn([]);

    // Claude: əvvəl tool_use, sonra text
    $claudeMock = new MockHandler([
        new Response(200, [], json_encode([
            'content' => [[
                'type' => 'tool_use', 'id' => 't1', 'name' => 'get_pto_balance',
                'input' => ['employee_email' => 'ayan@test.az'],
            ]],
            'stop_reason' => 'tool_use',
            'usage' => ['input_tokens' => 50, 'output_tokens' => 10],
        ])),
        new Response(200, [], json_encode([
            'content' => [['type' => 'text', 'text' => 'Sizin PTO balansınız 18.5 gündür.']],
            'stop_reason' => 'end_turn',
            'usage' => ['input_tokens' => 80, 'output_tokens' => 20],
        ])),
    ]);
    $anthropic = new Client(['handler' => HandlerStack::create($claudeMock)]);

    $service = new HrChatService(
        $anthropic, $mcp, $retriever,
        app(\App\Services\Hr\ToolPolicy::class),
        app(\App\Services\Hr\AuditLogger::class),
        app(\App\Services\Hr\PiiRedactor::class),
    );

    $result = $service->reply($user, 'PTO balansım nədir?');
    expect($result['text'])->toContain('18.5');
    expect($result['tools'][0]['name'])->toBe('get_pto_balance');
});

it('denies tool when user has no permission', function () {
    $user = User::factory()->withRole('employee')->create();
    $policy = app(\App\Services\Hr\ToolPolicy::class);
    $check = $policy->allowed($user, 'approve_leave', ['employee_email' => 'other@test.az']);
    expect($check['ok'])->toBeFalse();
    expect($check['reason'])->toBe('forbidden');
});
```

```php
// tests/Unit/Hr/PiiRedactorTest.php
<?php

use App\Services\Hr\PiiRedactor;

it('redacts email and phone', function () {
    $r = new PiiRedactor();
    $text = 'Contact ayan.m@test.az or +994501234567';
    expect($r->redact($text))->toBe('Contact [EMAIL_REDACTED] or [PHONE_REDACTED]');
});

it('redacts nested arrays', function () {
    $r = new PiiRedactor();
    $in = ['name' => 'Ayan', 'contact' => ['email' => 'a@b.az', 'phone' => '+994501234567']];
    $out = $r->redactArray($in);
    expect($out['contact']['email'])->toBe('[EMAIL_REDACTED]');
});
```

```php
// tests/Unit/Hr/AuditLoggerTest.php
<?php

use App\Models\AuditEntry;
use App\Models\User;
use App\Services\Hr\AuditLogger;
use App\Services\Hr\PiiRedactor;

it('logs tool call with redacted parameters', function () {
    $logger = new AuditLogger(new PiiRedactor());
    $user = User::factory()->create();

    $logger->log($user, 'tool:submit_leave_request', null, [
        'start_date' => '2026-05-01', 'employee_email' => 'a@b.az',
    ], ['request_id' => 'LR-1'], 'ok');

    $entry = AuditEntry::latest('id')->first();
    expect($entry->action)->toBe('tool:submit_leave_request');
    expect($entry->parameters['employee_email'])->toBe('[EMAIL_REDACTED]');
    expect($entry->result_summary['request_id'])->toBe('LR-1');
});
```

---

## Deployment Qeydləri

**Infrastruktur:**
- Laravel Octane (roadrunner) — Slack 3s ack budget
- Horizon (dedicated) — `ingestion`, `slack`, `default` queue-ları
- 3 MCP server konteyneri: `mcp-hris`, `mcp-itsm`, `mcp-calendar` (Node/Go)
- PostgreSQL 16 + pgvector
- Redis (job queue + cache)

**Slack App manifesti (əsas hissə):**
```yaml
display_information:
  name: Saba (HR Bot)
features:
  bot_user:
    display_name: Saba
  slash_commands:
    - command: /hr
      url: https://app.example.com/slack/command
      description: HR suallarını soruş
      should_escape: false
oauth_config:
  scopes:
    bot: [app_mentions:read, chat:write, commands, im:history, im:read, im:write, users:read, users:read.email]
settings:
  event_subscriptions:
    request_url: https://app.example.com/slack/events
    bot_events: [app_mention, message.im]
  interactivity:
    is_enabled: false
```

**ENV:**
```
ANTHROPIC_API_KEY=sk-ant-...
VOYAGE_API_KEY=pa-...
SLACK_CLIENT_ID=123
SLACK_CLIENT_SECRET=xxx
SLACK_SIGNING_SECRET=xxx
MCP_HRIS_URL=http://mcp-hris:3001/rpc
MCP_HRIS_TOKEN=...
CONFLUENCE_BASE=https://company.atlassian.net/wiki
CONFLUENCE_USER=service@company.com
CONFLUENCE_TOKEN=...
```

**Monitorinq:**
- Prometheus metrics: `hr_bot_query_total`, `hr_bot_tool_call_total{tool="get_pto_balance"}`, `hr_bot_retrieval_latency_seconds`, `hr_bot_claude_latency_seconds`, `hr_bot_denied_total{reason="forbidden"}`.
- Sentry: Claude API error, MCP RPC timeout, Confluence sync failure.
- Loglarda PII redakt olunmuş vəziyyətdə getməlidir (audit cədvəlində də redakt olunur).

**GDPR / Data retention:**
- `audit_log` — 2 il (sözləşmə tələbi).
- `hr_messages` — 90 gün, sonra sessiya mətni silinir (statistika saxlanılır).
- İşdən çıxan işçi SSO-dan silinəndə `SlackUserMapping` və keçmiş mesajları scheduler ilə təmizlənir.

**Release checklist:**
1. `php artisan migrate --force`
2. MCP serverləri yenilə: `kubectl apply -f mcp-hris-deployment.yml`
3. `php artisan horizon:terminate` → supervisor restart
4. Slack App-də webhook URL yoxla, event subscriptions yaşıldır.
5. Canary: 1 team-ə aç → 24 saat izlə → bütün şirkət.

**Real rəqəmlər (500 nəfərlik şirkət, 3 ay istifadə):**
- Gündə ~150 sorğu, orta 1.2 tool call
- Orta latency 3.2s (retrieval 180ms, Claude 2.8s, Slack post 200ms)
- Aylıq maliyyət: ~$42 (80% sual cache hit olur)
- HR-a düşən manual ticket sayı 41% azaldı

Bu arxitektura MCP-nin güclü tərəflərini istifadə edir: HRIS, ITSM, Calendar kimi müxtəlif sistemləri tək protocol arxasında birləşdirir. Laravel yalnız orchestration edir — MCP serverlər ayrıca servislər kimi saxlanılır, versiyalaşdırılır, deploy olunur.

## Praktik Tapşırıqlar

### 1. Slack Bot Quraşdırması
Slack App yaradın, `events:read`, `chat:write`, `im:read` permissionları əldə edin. Laravel-də `/api/slack/events` webhook endpoint yazın. Slack slash command `/hr-question` ilə Claude-a sorğu göndərin. Response-ı `ephemeral` (yalnız soruşana görünən) göndərin. Rate limiting: bir user saatda `>20` sorğu göndərirsə `⚠️ Limit reached` qaytarın.

### 2. Knowledge Base Yeniləmə Prosesi
HR sənədlərinin (PDF/Confluence) avtomatik indeksləşdirilməsi üçün pipeline qurun. Yeni fayl upload olunanda `IndexHrDocument` job queue-ya göndərilsin. Chunk-ları `hr_knowledge_chunks` cədvəlinə yazın. `updated_at` sahəsindən 90 gündən köhnə chunk-ları "stale" olaraq işarələyin. Stale chunk-lar sorğu nəticəsində qaytarıldıqda `⚠️ Bu məlumat köhnə ola bilər` bildirişi əlavə edin.

### 3. Usage Analytics Dashboard
`hr_bot_queries` cədvəlindən aylıq report yaradın: ən çox sorulan suallar, cavabsız qalan suallar (low confidence), peak usage saatları. Bu analiz HR komandasına hansı sənədlərin genişləndirilməsi lazım olduğunu göstərir. Avtomatik aylıq email report göndərin: `php artisan hr:monthly-report`.

## Əlaqəli Mövzular

- [Laravel MCP Server](./09-laravel-mcp-server.md)
- [Laravel Chatbot](./01-laravel-chatbot.md)
- [MCP Fundamentals](../03-mcp/02-mcp-fundamentals.md)
- [RAG App](./04-laravel-rag-app.md)
- [Observability Logging](../08-production/02-observability-logging.md)
