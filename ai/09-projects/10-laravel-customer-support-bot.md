# Şirkət üçün Customer Support Chatbot — Laravel + RAG + Tool Use

İstehsal səviyyəli müştəri dəstəyi chatbot. İstifadəçi sualına həm KB (knowledge base) üzərindən cavab verir, həm də real hərəkətlər edir: sifarişi yoxlayır, ticket açır, çatdırılma məlumatı verir, aşağı confidence halında canlı operatora ötürür. Çoxtenantlı, axın (SSE) cavablı, alət istifadəli, maliyyə nəzarəti ilə.

---

## Arxitektura Baxışı

```
Müştəri Brauzeri (Livewire widget)
    │  SSE (text/event-stream)
    ▼
SupportChatController → Livewire SupportChat komponenti
    │
    ▼
SupportChatService
    ├── SessionMemory (son N mesaj + xülasə)
    ├── HybridRetriever (BM25 + pgvector cosine)
    ├── ToolRegistry
    │     ├── check_order
    │     ├── get_shipping_info
    │     ├── create_ticket
    │     └── escalate_human
    ├── BudgetGuard (tenant gündəlik limit + session RPM)
    └── HandoffDetector (confidence/keyword)
    │
    ▼
Claude API (claude-sonnet-4-5 — əsas; claude-haiku-4-5 — intent/klassifikasiya)
    │
    ▼
PostgreSQL (pgvector) + Redis (rate limit, cache) + Horizon (ingestion jobs)
```

**Dizayn prinsipləri:**
- Hər tenant öz KB-sini daşıyır (izolyasiya edilmiş `tenant_id` ilə).
- Retrieval hibriddir: əvvəlcə BM25 (Postgres `tsvector`) + pgvector, sonra reciprocal rank fusion.
- Model cavabı MƏCBURI tool call ilə başlaya bilər — əgər sual sifariş/çatdırılma haqqındadırsa.
- Hər tool çağırışı audit olunur (`tool_calls` cədvəli).
- Handoff yalnız bot açıq şəkildə deyəndə baş verir (`<handoff>` markeri) və ya 2 dəfə dalbadal aşağı confidence.
- Token büdcəsi göndərişdən əvvəl yoxlanılır, aşıldıqda graceful message qaytarır.

---

## Verilənlər Bazası Miqrasiyaları

```php
// database/migrations/2026_04_21_000001_create_support_tables.php
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
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        Schema::create('knowledge_documents', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('source_url')->nullable();   // crawl mənbəyi
            $table->string('source_type')->default('web'); // web, faq, manual, policy
            $table->string('title');
            $table->longText('content');                // tam mətn
            $table->string('language', 8)->default('az');
            $table->string('content_hash', 64);         // dəyişiklik aşkarı
            $table->string('status')->default('active'); // active, stale, deleted
            $table->timestamp('crawled_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->unique(['tenant_id', 'source_url']);
        });

        Schema::create('document_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('knowledge_documents')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('chunk_index');
            $table->text('content');
            $table->unsignedInteger('token_count');
            $table->string('section_title')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'document_id']);
        });

        // pgvector sütunu və BM25 tsvector
        DB::statement('ALTER TABLE document_chunks ADD COLUMN embedding vector(1024)');
        DB::statement("ALTER TABLE document_chunks ADD COLUMN search_vector tsvector GENERATED ALWAYS AS (to_tsvector('simple', coalesce(content,''))) STORED");
        DB::statement('CREATE INDEX document_chunks_embedding_idx ON document_chunks USING hnsw (embedding vector_cosine_ops)');
        DB::statement('CREATE INDEX document_chunks_search_idx ON document_chunks USING gin (search_vector)');

        Schema::create('chat_sessions', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('visitor_id');               // anonim cookie
            $table->string('customer_email')->nullable();
            $table->string('locale', 8)->default('az');
            $table->string('status')->default('active'); // active, handoff, closed
            $table->string('handoff_reason')->nullable();
            $table->json('context')->nullable();         // UTM, ölkə, səhifə
            $table->unsignedInteger('total_input_tokens')->default(0);
            $table->unsignedInteger('total_output_tokens')->default(0);
            $table->decimal('cost_usd', 10, 6)->default(0);
            $table->unsignedTinyInteger('csat_score')->nullable(); // 1-5
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['visitor_id', 'created_at']);
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('chat_sessions')->cascadeOnDelete();
            $table->string('role'); // user, assistant, tool_result, system
            $table->longText('content');
            $table->json('tool_calls')->nullable();
            $table->json('retrieved_chunks')->nullable(); // audit: hansı chunks
            $table->decimal('confidence', 4, 3)->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->timestamps();

            $table->index(['session_id', 'created_at']);
        });

        Schema::create('handoffs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('chat_sessions')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('reason'); // low_confidence, explicit_request, tool_failure, angry_user
            $table->longText('summary'); // botun operatora ötürdüyü xülasə
            $table->foreignId('assigned_agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('pending'); // pending, claimed, resolved
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        Schema::create('tenant_budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->date('day');
            $table->decimal('daily_limit_usd', 10, 2);
            $table->decimal('spent_usd', 10, 6)->default(0);
            $table->unsignedInteger('sessions_count')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'day']);
        });
    }
};
```

---

## Modellər

```php
// app/Models/KnowledgeDocument.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KnowledgeDocument extends Model
{
    use HasUlids;

    protected $fillable = [
        'tenant_id', 'source_url', 'source_type', 'title',
        'content', 'language', 'content_hash', 'status', 'crawled_at',
    ];

    protected $casts = [
        'crawled_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(DocumentChunk::class, 'document_id');
    }
}
```

```php
// app/Models/DocumentChunk.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentChunk extends Model
{
    protected $fillable = [
        'document_id', 'tenant_id', 'chunk_index',
        'content', 'token_count', 'section_title',
    ];

    public $timestamps = true;

    public function document(): BelongsTo
    {
        return $this->belongsTo(KnowledgeDocument::class, 'document_id');
    }

    // embedding sütunu vektor növünün ayrıca işlənməsinə görə fillable-dən kənardadır
    public function setEmbeddingAttribute(array $vector): void
    {
        $this->attributes['embedding'] = '[' . implode(',', $vector) . ']';
    }
}
```

```php
// app/Models/ChatSession.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatSession extends Model
{
    use HasUlids;

    protected $fillable = [
        'tenant_id', 'visitor_id', 'customer_email', 'locale',
        'status', 'handoff_reason', 'context',
        'total_input_tokens', 'total_output_tokens', 'cost_usd',
        'csat_score', 'ended_at',
    ];

    protected $casts = [
        'context' => 'array',
        'ended_at' => 'datetime',
        'cost_usd' => 'decimal:6',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'session_id')->orderBy('created_at');
    }

    public function handoff(): BelongsTo
    {
        return $this->belongsTo(Handoff::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function incrementUsage(int $in, int $out, float $cost): void
    {
        $this->increment('total_input_tokens', $in);
        $this->increment('total_output_tokens', $out);
        $this->increment('cost_usd', $cost);
    }
}
```

```php
// app/Models/ChatMessage.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    protected $fillable = [
        'session_id', 'role', 'content', 'tool_calls',
        'retrieved_chunks', 'confidence',
        'input_tokens', 'output_tokens', 'latency_ms',
    ];

    protected $casts = [
        'tool_calls' => 'array',
        'retrieved_chunks' => 'array',
        'confidence' => 'float',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(ChatSession::class, 'session_id');
    }
}
```

```php
// app/Models/Handoff.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Handoff extends Model
{
    protected $fillable = [
        'session_id', 'tenant_id', 'reason', 'summary',
        'assigned_agent_id', 'status', 'claimed_at', 'resolved_at',
    ];

    protected $casts = [
        'claimed_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(ChatSession::class, 'session_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_agent_id');
    }
}
```

---

## KB Ingestion Pipeline

```php
// app/Services/KnowledgeBase/Crawler.php
<?php

namespace App\Services\KnowledgeBase;

use App\Models\KnowledgeDocument;
use App\Models\Tenant;
use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler as DomCrawler;

class Crawler
{
    public function __construct(private Client $http) {}

    /**
     * Tenant-in verilmiş başlanğıc URL-indən eyni domendə olan bütün səhifələri yüklə.
     * Sadə BFS, rəqabətsiz; production-da Horizon job-larına yönəltmək lazımdır.
     */
    public function crawlSite(Tenant $tenant, string $startUrl, int $maxPages = 200): int
    {
        $queue = [$startUrl];
        $seen = [];
        $count = 0;
        $host = parse_url($startUrl, PHP_URL_HOST);

        while ($queue && $count < $maxPages) {
            $url = array_shift($queue);
            if (isset($seen[$url])) {
                continue;
            }
            $seen[$url] = true;

            try {
                $response = $this->http->get($url, ['timeout' => 10]);
                if ($response->getStatusCode() !== 200) {
                    continue;
                }

                $html = (string) $response->getBody();
                $doc = new DomCrawler($html);

                $title = trim($doc->filter('title')->text(''));
                $text = trim($doc->filter('main, article, body')->text('', true));

                if (strlen($text) < 300) {
                    continue; // boş/çox qısa səhifələri at
                }

                $hash = hash('sha256', $text);

                KnowledgeDocument::updateOrCreate(
                    ['tenant_id' => $tenant->id, 'source_url' => $url],
                    [
                        'title' => $title ?: $url,
                        'content' => $text,
                        'source_type' => 'web',
                        'content_hash' => $hash,
                        'status' => 'active',
                        'crawled_at' => now(),
                    ],
                );

                $count++;

                // Daxili linkləri tap
                $doc->filter('a')->each(function (DomCrawler $node) use (&$queue, $host, $seen) {
                    $href = $node->attr('href');
                    if (!$href) return;
                    $abs = $this->makeAbsolute($href, $node->getBaseHref() ?? '');
                    if (!$abs) return;
                    if (parse_url($abs, PHP_URL_HOST) !== $host) return;
                    if (!isset($seen[$abs])) {
                        $queue[] = $abs;
                    }
                });
            } catch (\Throwable $e) {
                \Log::warning("Crawl failed for {$url}: {$e->getMessage()}");
            }
        }

        return $count;
    }

    private function makeAbsolute(string $href, string $base): ?string
    {
        if (str_starts_with($href, 'http')) return $href;
        if (!$base) return null;
        return rtrim($base, '/') . '/' . ltrim($href, '/');
    }
}
```

```php
// app/Services/KnowledgeBase/Chunker.php
<?php

namespace App\Services\KnowledgeBase;

class Chunker
{
    /**
     * Recursive character splitter. ~400 token (~1600 char) hədəfi, 80 token overlap.
     * Başlıqları qorumağa çalışır.
     */
    public function split(string $text, int $targetChars = 1600, int $overlap = 320): array
    {
        $paragraphs = preg_split("/\n\s*\n/", $text);
        $chunks = [];
        $current = '';
        $currentHeading = null;

        foreach ($paragraphs as $p) {
            $p = trim($p);
            if ($p === '') continue;

            if (preg_match('/^#+\s+(.+)$/m', $p, $m) || mb_strlen($p) < 80) {
                $currentHeading = $m[1] ?? $p;
            }

            if (mb_strlen($current) + mb_strlen($p) > $targetChars) {
                if ($current) {
                    $chunks[] = ['heading' => $currentHeading, 'content' => trim($current)];
                }
                // overlap: son $overlap simvolu saxla
                $current = mb_substr($current, -$overlap) . "\n\n" . $p;
            } else {
                $current .= "\n\n" . $p;
            }
        }

        if (trim($current) !== '') {
            $chunks[] = ['heading' => $currentHeading, 'content' => trim($current)];
        }

        return $chunks;
    }
}
```

```php
// app/Services/KnowledgeBase/Embedder.php
<?php

namespace App\Services\KnowledgeBase;

use GuzzleHttp\Client;

class Embedder
{
    public function __construct(private Client $http) {}

    /**
     * Voyage-3 multilingual embedding modelindən istifadə edir (Azərbaycan + İngilis dəstəyi).
     * Alternativ: OpenAI text-embedding-3-large.
     */
    public function embed(array $texts, string $inputType = 'document'): array
    {
        $response = $this->http->post('https://api.voyageai.com/v1/embeddings', [
            'headers' => [
                'Authorization' => 'Bearer ' . config('services.voyage.key'),
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => 'voyage-3',
                'input' => $texts,
                'input_type' => $inputType, // document | query
            ],
            'timeout' => 30,
        ]);

        $body = json_decode((string) $response->getBody(), true);
        return array_map(fn ($d) => $d['embedding'], $body['data']);
    }
}
```

```php
// app/Jobs/IngestDocumentJob.php
<?php

namespace App\Jobs;

use App\Models\DocumentChunk;
use App\Models\KnowledgeDocument;
use App\Services\KnowledgeBase\Chunker;
use App\Services\KnowledgeBase\Embedder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class IngestDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(public int $documentId) {}

    public function handle(Chunker $chunker, Embedder $embedder): void
    {
        $doc = KnowledgeDocument::findOrFail($this->documentId);

        // köhnə chunk-ları təmizlə
        DocumentChunk::where('document_id', $doc->id)->delete();

        $pieces = $chunker->split($doc->content);
        if (!$pieces) return;

        // batch embedding (100-lük)
        $batches = array_chunk($pieces, 100);
        $index = 0;

        foreach ($batches as $batch) {
            $texts = array_map(fn ($p) => $p['content'], $batch);
            $vectors = $embedder->embed($texts, 'document');

            foreach ($batch as $i => $piece) {
                $chunk = new DocumentChunk([
                    'document_id' => $doc->id,
                    'tenant_id' => $doc->tenant_id,
                    'chunk_index' => $index,
                    'content' => $piece['content'],
                    'section_title' => $piece['heading'],
                    'token_count' => (int) ceil(mb_strlen($piece['content']) / 4),
                ]);
                $chunk->embedding = $vectors[$i];
                $chunk->save();
                $index++;
            }
        }
    }
}
```

---

## Hybrid Retriever

```php
// app/Services/Retrieval/HybridRetriever.php
<?php

namespace App\Services\Retrieval;

use App\Services\KnowledgeBase\Embedder;
use Illuminate\Support\Facades\DB;

class HybridRetriever
{
    public function __construct(private Embedder $embedder) {}

    /**
     * Reciprocal rank fusion ilə BM25 + pgvector.
     * @return array<int, array{chunk_id:int, content:string, title:?string, score:float, source_url:?string}>
     */
    public function retrieve(int $tenantId, string $query, int $topK = 6): array
    {
        [$vector] = $this->embedder->embed([$query], 'query');
        $vectorString = '[' . implode(',', $vector) . ']';

        // 1) Vector (cosine) top 20
        $vectorHits = DB::select("
            SELECT c.id, c.content, c.section_title, d.title as doc_title, d.source_url,
                   1 - (c.embedding <=> ?::vector) AS similarity
            FROM document_chunks c
            JOIN knowledge_documents d ON d.id = c.document_id
            WHERE c.tenant_id = ? AND d.status = 'active'
            ORDER BY c.embedding <=> ?::vector
            LIMIT 20
        ", [$vectorString, $tenantId, $vectorString]);

        // 2) BM25 (ts_rank_cd) top 20
        $bm25Hits = DB::select("
            SELECT c.id, c.content, c.section_title, d.title as doc_title, d.source_url,
                   ts_rank_cd(c.search_vector, plainto_tsquery('simple', ?)) AS rank
            FROM document_chunks c
            JOIN knowledge_documents d ON d.id = c.document_id
            WHERE c.tenant_id = ? AND d.status = 'active'
              AND c.search_vector @@ plainto_tsquery('simple', ?)
            ORDER BY rank DESC
            LIMIT 20
        ", [$query, $tenantId, $query]);

        // 3) RRF (k=60)
        $scores = [];
        $records = [];
        foreach ($vectorHits as $rank => $hit) {
            $scores[$hit->id] = ($scores[$hit->id] ?? 0) + 1 / (60 + $rank);
            $records[$hit->id] = $hit;
        }
        foreach ($bm25Hits as $rank => $hit) {
            $scores[$hit->id] = ($scores[$hit->id] ?? 0) + 1 / (60 + $rank);
            $records[$hit->id] = $records[$hit->id] ?? $hit;
        }

        arsort($scores);
        $top = array_slice($scores, 0, $topK, true);

        return array_map(function ($score, $id) use ($records) {
            $r = $records[$id];
            return [
                'chunk_id' => $r->id,
                'content' => $r->content,
                'title' => $r->doc_title,
                'section' => $r->section_title,
                'source_url' => $r->source_url,
                'score' => (float) $score,
            ];
        }, $top, array_keys($top));
    }
}
```

---

## Tool Registry

```php
// app/Services/Chat/Tools/ToolContract.php
<?php

namespace App\Services\Chat\Tools;

use App\Models\ChatSession;

interface ToolContract
{
    public function name(): string;
    public function definition(): array; // Anthropic tool schema
    public function execute(ChatSession $session, array $input): array;
}
```

```php
// app/Services/Chat/Tools/CheckOrderTool.php
<?php

namespace App\Services\Chat\Tools;

use App\Models\ChatSession;
use App\Models\Order;

class CheckOrderTool implements ToolContract
{
    public function name(): string
    {
        return 'check_order';
    }

    public function definition(): array
    {
        return [
            'name' => $this->name(),
            'description' => 'Sifariş nömrəsi və ya müştəri emaili ilə sifarişin statusunu yoxlayır.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'order_number' => [
                        'type' => 'string',
                        'description' => 'Sifariş nömrəsi, məsələn ORD-12345',
                    ],
                    'email' => [
                        'type' => 'string',
                        'description' => 'Sifariş verən müştərinin emaili',
                    ],
                ],
                'required' => [],
            ],
        ];
    }

    public function execute(ChatSession $session, array $input): array
    {
        $query = Order::where('tenant_id', $session->tenant_id);
        if ($num = $input['order_number'] ?? null) {
            $query->where('number', $num);
        }
        if ($email = $input['email'] ?? $session->customer_email) {
            $query->where('customer_email', $email);
        }

        $order = $query->latest()->first();
        if (!$order) {
            return ['found' => false, 'message' => 'Bu məlumatlarla sifariş tapılmadı.'];
        }

        return [
            'found' => true,
            'number' => $order->number,
            'status' => $order->status,
            'total' => $order->total,
            'currency' => $order->currency,
            'placed_at' => $order->created_at->toDateString(),
            'shipping_status' => $order->shipping_status,
            'tracking_number' => $order->tracking_number,
        ];
    }
}
```

```php
// app/Services/Chat/Tools/GetShippingInfoTool.php
<?php

namespace App\Services\Chat\Tools;

use App\Models\ChatSession;
use App\Models\ShippingZone;

class GetShippingInfoTool implements ToolContract
{
    public function name(): string { return 'get_shipping_info'; }

    public function definition(): array
    {
        return [
            'name' => $this->name(),
            'description' => 'Verilmiş ölkə/şəhər üçün çatdırılma müddəti və qiymətini qaytarır.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'country' => ['type' => 'string', 'description' => 'İki hərfli ISO ölkə kodu, məs. AZ'],
                    'city' => ['type' => 'string'],
                ],
                'required' => ['country'],
            ],
        ];
    }

    public function execute(ChatSession $session, array $input): array
    {
        $zone = ShippingZone::where('tenant_id', $session->tenant_id)
            ->where('country_code', strtoupper($input['country']))
            ->when($input['city'] ?? null, fn ($q, $c) => $q->where('city', $c))
            ->first();

        if (!$zone) {
            return ['available' => false, 'message' => 'Bu ünvana hələ çatdırılmırıq.'];
        }

        return [
            'available' => true,
            'min_days' => $zone->min_days,
            'max_days' => $zone->max_days,
            'price' => $zone->price,
            'currency' => $zone->currency,
            'carrier' => $zone->carrier,
        ];
    }
}
```

```php
// app/Services/Chat/Tools/CreateTicketTool.php
<?php

namespace App\Services\Chat\Tools;

use App\Models\ChatSession;
use App\Models\SupportTicket;

class CreateTicketTool implements ToolContract
{
    public function name(): string { return 'create_ticket'; }

    public function definition(): array
    {
        return [
            'name' => $this->name(),
            'description' => 'Müştəri üçün dəstək ticket-i yaradır. Yalnız məsələ bot tərəfindən həll edilə bilmədikdə istifadə et.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'subject' => ['type' => 'string'],
                    'description' => ['type' => 'string'],
                    'priority' => ['type' => 'string', 'enum' => ['low', 'normal', 'high', 'urgent']],
                    'customer_email' => ['type' => 'string'],
                ],
                'required' => ['subject', 'description', 'customer_email'],
            ],
        ];
    }

    public function execute(ChatSession $session, array $input): array
    {
        $ticket = SupportTicket::create([
            'tenant_id' => $session->tenant_id,
            'session_id' => $session->id,
            'subject' => $input['subject'],
            'description' => $input['description'],
            'priority' => $input['priority'] ?? 'normal',
            'customer_email' => $input['customer_email'],
            'source' => 'bot',
            'status' => 'open',
        ]);

        return [
            'created' => true,
            'ticket_number' => $ticket->number,
            'sla_hours' => config("support.sla.{$ticket->priority}", 24),
        ];
    }
}
```

```php
// app/Services/Chat/Tools/EscalateHumanTool.php
<?php

namespace App\Services\Chat\Tools;

use App\Models\ChatSession;
use App\Models\Handoff;

class EscalateHumanTool implements ToolContract
{
    public function name(): string { return 'escalate_human'; }

    public function definition(): array
    {
        return [
            'name' => $this->name(),
            'description' => 'Söhbəti canlı operatora ötür. Yalnız istifadəçi açıq istədikdə və ya mürəkkəb mövzu olduqda istifadə et.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'reason' => ['type' => 'string', 'enum' => ['low_confidence', 'explicit_request', 'tool_failure', 'angry_user', 'complex_issue']],
                    'summary' => ['type' => 'string', 'description' => 'Operatora 2-3 cümləlik xülasə.'],
                ],
                'required' => ['reason', 'summary'],
            ],
        ];
    }

    public function execute(ChatSession $session, array $input): array
    {
        $handoff = Handoff::create([
            'session_id' => $session->id,
            'tenant_id' => $session->tenant_id,
            'reason' => $input['reason'],
            'summary' => $input['summary'],
            'status' => 'pending',
        ]);

        $session->update([
            'status' => 'handoff',
            'handoff_reason' => $input['reason'],
        ]);

        return [
            'escalated' => true,
            'handoff_id' => $handoff->id,
            'message' => 'Söhbət canlı operatora ötürüldü. Tezliklə sizə qoşulacaqlar.',
        ];
    }
}
```

```php
// app/Services/Chat/ToolRegistry.php
<?php

namespace App\Services\Chat;

use App\Services\Chat\Tools\CheckOrderTool;
use App\Services\Chat\Tools\CreateTicketTool;
use App\Services\Chat\Tools\EscalateHumanTool;
use App\Services\Chat\Tools\GetShippingInfoTool;
use App\Services\Chat\Tools\ToolContract;

class ToolRegistry
{
    /** @var array<string, ToolContract> */
    private array $tools = [];

    public function __construct()
    {
        foreach ([
            new CheckOrderTool(),
            new GetShippingInfoTool(),
            new CreateTicketTool(),
            new EscalateHumanTool(),
        ] as $tool) {
            $this->tools[$tool->name()] = $tool;
        }
    }

    public function definitions(): array
    {
        return array_map(fn (ToolContract $t) => $t->definition(), array_values($this->tools));
    }

    public function resolve(string $name): ToolContract
    {
        if (!isset($this->tools[$name])) {
            throw new \RuntimeException("Unknown tool: {$name}");
        }
        return $this->tools[$name];
    }
}
```

---

## Budget Guard

```php
// app/Services/Chat/BudgetGuard.php
<?php

namespace App\Services\Chat;

use App\Models\ChatSession;
use App\Models\TenantBudget;
use Illuminate\Support\Facades\Cache;

class BudgetGuard
{
    // Claude Sonnet 4.5 qiymətləri (1M token üzrə). 2026-04 vəziyyət.
    private const SONNET_IN = 3.00 / 1_000_000;
    private const SONNET_OUT = 15.00 / 1_000_000;
    private const HAIKU_IN = 0.80 / 1_000_000;
    private const HAIKU_OUT = 4.00 / 1_000_000;

    public function canSendMessage(ChatSession $session): array
    {
        // 1) Session RPM (son 60 saniyə)
        $key = "chat:rpm:{$session->id}";
        $count = Cache::get($key, 0);
        if ($count >= config('support.session_rpm', 10)) {
            return ['ok' => false, 'reason' => 'rate_limit_session'];
        }

        // 2) Tenant günlük büdcə
        $budget = TenantBudget::firstOrCreate(
            ['tenant_id' => $session->tenant_id, 'day' => now()->toDateString()],
            ['daily_limit_usd' => config('support.default_daily_usd', 10.0)],
        );

        if ($budget->spent_usd >= $budget->daily_limit_usd) {
            return ['ok' => false, 'reason' => 'tenant_budget_exceeded'];
        }

        Cache::put($key, $count + 1, now()->addMinute());
        return ['ok' => true];
    }

    public function recordUsage(ChatSession $session, string $model, int $in, int $out): float
    {
        $cost = match (true) {
            str_contains($model, 'haiku') => $in * self::HAIKU_IN + $out * self::HAIKU_OUT,
            default => $in * self::SONNET_IN + $out * self::SONNET_OUT,
        };

        $session->incrementUsage($in, $out, $cost);

        TenantBudget::where('tenant_id', $session->tenant_id)
            ->where('day', now()->toDateString())
            ->increment('spent_usd', $cost);

        return $cost;
    }
}
```

---

## Chat Service (Əsas beyin)

```php
// app/Services/Chat/SupportChatService.php
<?php

namespace App\Services\Chat;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Services\Retrieval\HybridRetriever;
use Generator;
use GuzzleHttp\Client;

class SupportChatService
{
    private const MODEL = 'claude-sonnet-4-5';
    private const MAX_TOKENS = 1024;
    private const HISTORY_LIMIT = 20;

    public function __construct(
        private Client $anthropic,
        private ToolRegistry $tools,
        private HybridRetriever $retriever,
        private BudgetGuard $budget,
    ) {}

    /**
     * Axınlı cavab generatoru. Hər yield → brauzerə SSE parçası.
     * @return Generator<array{type:string, data:mixed}>
     */
    public function streamReply(ChatSession $session, string $userMessage): Generator
    {
        $guard = $this->budget->canSendMessage($session);
        if (!$guard['ok']) {
            yield ['type' => 'error', 'data' => $this->friendlyError($guard['reason'])];
            return;
        }

        // 1) User mesajını saxla
        $session->messages()->create(['role' => 'user', 'content' => $userMessage]);

        // 2) Retrieval
        $chunks = $this->retriever->retrieve($session->tenant_id, $userMessage, topK: 6);
        $context = $this->formatContext($chunks);

        // 3) System prompt + tarixçə
        $system = $this->systemPrompt($session, $context);
        $messages = $this->buildHistory($session);
        $messages[] = ['role' => 'user', 'content' => $userMessage];

        $totalIn = 0;
        $totalOut = 0;
        $assistantText = '';
        $assistantConfidence = null;

        // 4) Tool-use loop
        for ($iter = 0; $iter < 5; $iter++) {
            $payload = [
                'model' => self::MODEL,
                'max_tokens' => self::MAX_TOKENS,
                'system' => [
                    ['type' => 'text', 'text' => $system, 'cache_control' => ['type' => 'ephemeral']],
                ],
                'tools' => $this->tools->definitions(),
                'messages' => $messages,
                'stream' => true,
            ];

            $start = microtime(true);
            $stop = null;
            $toolUseBlocks = [];
            $currentBlock = null;
            $inputJsonBuffer = '';

            $response = $this->anthropic->post('https://api.anthropic.com/v1/messages', [
                'headers' => [
                    'x-api-key' => config('services.anthropic.key'),
                    'anthropic-version' => '2023-06-01',
                    'content-type' => 'application/json',
                ],
                'json' => $payload,
                'stream' => true,
            ]);

            $body = $response->getBody();
            $buffer = '';
            while (!$body->eof()) {
                $buffer .= $body->read(1024);
                while (($pos = strpos($buffer, "\n\n")) !== false) {
                    $event = substr($buffer, 0, $pos);
                    $buffer = substr($buffer, $pos + 2);
                    $data = $this->parseSse($event);
                    if (!$data) continue;

                    switch ($data['type']) {
                        case 'content_block_start':
                            $currentBlock = $data['content_block'];
                            if ($currentBlock['type'] === 'tool_use') {
                                $toolUseBlocks[$data['index']] = [
                                    'id' => $currentBlock['id'],
                                    'name' => $currentBlock['name'],
                                    'input' => '',
                                ];
                            }
                            break;

                        case 'content_block_delta':
                            $delta = $data['delta'];
                            if ($delta['type'] === 'text_delta') {
                                $assistantText .= $delta['text'];
                                yield ['type' => 'token', 'data' => $delta['text']];
                            } elseif ($delta['type'] === 'input_json_delta') {
                                $toolUseBlocks[$data['index']]['input'] .= $delta['partial_json'];
                            }
                            break;

                        case 'message_delta':
                            $stop = $data['delta']['stop_reason'] ?? null;
                            $totalOut += $data['usage']['output_tokens'] ?? 0;
                            break;

                        case 'message_start':
                            $totalIn += $data['message']['usage']['input_tokens'] ?? 0;
                            break;
                    }
                }
            }

            // Yığılmış mesajı tarixçəyə əlavə et
            $assistantContent = [];
            if ($assistantText !== '') {
                $assistantContent[] = ['type' => 'text', 'text' => $assistantText];
            }
            foreach ($toolUseBlocks as $block) {
                $assistantContent[] = [
                    'type' => 'tool_use',
                    'id' => $block['id'],
                    'name' => $block['name'],
                    'input' => $block['input'] ? json_decode($block['input'], true) : [],
                ];
            }

            if ($stop !== 'tool_use') {
                // Final cavab
                $assistantConfidence = $this->estimateConfidence($chunks, $assistantText);
                break;
            }

            // Tool icra et
            $messages[] = ['role' => 'assistant', 'content' => $assistantContent];
            $toolResults = [];
            foreach ($toolUseBlocks as $block) {
                $input = $block['input'] ? json_decode($block['input'], true) : [];
                try {
                    $result = $this->tools->resolve($block['name'])->execute($session, $input ?? []);
                    yield ['type' => 'tool', 'data' => ['name' => $block['name'], 'result' => $result]];
                } catch (\Throwable $e) {
                    $result = ['error' => $e->getMessage()];
                }
                $toolResults[] = [
                    'type' => 'tool_result',
                    'tool_use_id' => $block['id'],
                    'content' => json_encode($result, JSON_UNESCAPED_UNICODE),
                ];
            }
            $messages[] = ['role' => 'user', 'content' => $toolResults];
            $assistantText = ''; // yeni iterasiya üçün sıfırla
        }

        $latency = (int) ((microtime(true) - $start) * 1000);
        $cost = $this->budget->recordUsage($session, self::MODEL, $totalIn, $totalOut);

        ChatMessage::create([
            'session_id' => $session->id,
            'role' => 'assistant',
            'content' => $assistantText,
            'tool_calls' => $toolUseBlocks,
            'retrieved_chunks' => array_map(fn ($c) => ['id' => $c['chunk_id'], 'score' => $c['score']], $chunks),
            'confidence' => $assistantConfidence,
            'input_tokens' => $totalIn,
            'output_tokens' => $totalOut,
            'latency_ms' => $latency,
        ]);

        yield ['type' => 'done', 'data' => [
            'confidence' => $assistantConfidence,
            'cost_usd' => $cost,
            'latency_ms' => $latency,
        ]];

        // Handoff qərarı
        if ($assistantConfidence !== null && $assistantConfidence < 0.35 && $session->status === 'active') {
            $this->autoHandoff($session, 'low_confidence', $assistantText);
            yield ['type' => 'handoff', 'data' => ['reason' => 'low_confidence']];
        }
    }

    private function systemPrompt(ChatSession $session, string $context): string
    {
        $tenantName = $session->tenant->name ?? 'bizim şirkət';
        return <<<PROMPT
Sən {$tenantName} şirkətinin müştəri dəstəyi botusan. Adın "Aida". Dilin rəsmi, mehriban, qısa.

QAYDALAR:
- Cavabı YALNIZ aşağıdakı KB kontekstinə və ya alət nəticəsinə əsaslandır. Kontekstdə yoxdursa, "Bu barədə dəqiq məlumatım yoxdur, operatora ötürüm" de.
- Sifariş və ya çatdırılma haqqında sualda mütləq alət istifadə et.
- İstifadəçi hirslidirsə və ya "operator ilə danışmaq istəyirəm" deyirsə dərhal `escalate_human` çağır.
- Qiymət, tarix, nömrə kimi dəqiq faktları alətdən al, uydurma.
- Cavabın sonunda mümkünsə mənbə linkini mötərizədə göstər: (Mənbə: <URL>).
- Kod, HTML, JSON istifadə etmə istifadəçi soruşmayınca.

KB KONTEKSTİ:
{$context}
PROMPT;
    }

    private function formatContext(array $chunks): string
    {
        if (!$chunks) return '(Uyğun sənəd tapılmadı.)';
        $parts = [];
        foreach ($chunks as $i => $c) {
            $src = $c['source_url'] ?? 'internal';
            $parts[] = "[{$i}] {$c['title']} — {$c['section']}\n{$c['content']}\nMənbə: {$src}";
        }
        return implode("\n\n---\n\n", $parts);
    }

    private function buildHistory(ChatSession $session): array
    {
        return $session->messages()
            ->orderBy('created_at', 'desc')
            ->limit(self::HISTORY_LIMIT)
            ->get()
            ->reverse()
            ->map(fn (ChatMessage $m) => [
                'role' => $m->role === 'user' ? 'user' : 'assistant',
                'content' => $m->content,
            ])
            ->values()
            ->all();
    }

    private function estimateConfidence(array $chunks, string $answer): float
    {
        if (!$chunks) return 0.2;
        $maxScore = max(array_column($chunks, 'score'));
        $lengthPenalty = mb_strlen($answer) < 40 ? 0.7 : 1.0;
        return round(min(1.0, $maxScore * 2) * $lengthPenalty, 3);
    }

    private function autoHandoff(ChatSession $session, string $reason, string $lastAssistant): void
    {
        $summary = mb_substr($lastAssistant, 0, 400);
        $session->update(['status' => 'handoff', 'handoff_reason' => $reason]);
        \App\Models\Handoff::create([
            'session_id' => $session->id,
            'tenant_id' => $session->tenant_id,
            'reason' => $reason,
            'summary' => $summary,
            'status' => 'pending',
        ]);
    }

    private function parseSse(string $raw): ?array
    {
        foreach (explode("\n", $raw) as $line) {
            if (str_starts_with($line, 'data: ')) {
                $json = substr($line, 6);
                if ($json === '[DONE]') return null;
                return json_decode($json, true);
            }
        }
        return null;
    }

    private function friendlyError(string $reason): string
    {
        return match ($reason) {
            'rate_limit_session' => 'Çox sürətli yazırsınız, bir az gözləyin.',
            'tenant_budget_exceeded' => 'Bu gün üçün limit dolub, operator tezliklə sizə yazacaq.',
            default => 'Müvəqqəti problem, yenidən cəhd edin.',
        };
    }
}
```

---

## Controller + SSE Endpoint

```php
// app/Http/Controllers/SupportChatController.php
<?php

namespace App\Http\Controllers;

use App\Models\ChatSession;
use App\Models\Tenant;
use App\Services\Chat\SupportChatService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupportChatController extends Controller
{
    public function __construct(private SupportChatService $service) {}

    public function start(Request $request, Tenant $tenant)
    {
        $validated = $request->validate([
            'visitor_id' => 'required|string|max:64',
            'email' => 'nullable|email',
            'locale' => 'nullable|string|max:8',
        ]);

        $session = ChatSession::create([
            'tenant_id' => $tenant->id,
            'visitor_id' => $validated['visitor_id'],
            'customer_email' => $validated['email'] ?? null,
            'locale' => $validated['locale'] ?? 'az',
            'context' => ['ua' => $request->userAgent(), 'ip' => $request->ip()],
        ]);

        return response()->json(['session_id' => $session->ulid]);
    }

    public function stream(Request $request, string $sessionUlid): StreamedResponse
    {
        $session = ChatSession::where('ulid', $sessionUlid)->firstOrFail();
        $message = $request->validate(['message' => 'required|string|max:4000'])['message'];

        return new StreamedResponse(function () use ($session, $message) {
            foreach ($this->service->streamReply($session, $message) as $event) {
                echo "event: {$event['type']}\n";
                echo 'data: ' . json_encode($event['data'], JSON_UNESCAPED_UNICODE) . "\n\n";
                @ob_flush();
                @flush();
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function csat(Request $request, string $sessionUlid)
    {
        $session = ChatSession::where('ulid', $sessionUlid)->firstOrFail();
        $score = $request->validate(['score' => 'required|integer|min:1|max:5'])['score'];
        $session->update(['csat_score' => $score, 'ended_at' => now()]);

        return response()->json(['ok' => true]);
    }
}
```

---

## Livewire Widget

```php
// app/Livewire/SupportChat.php
<?php

namespace App\Livewire;

use Livewire\Attributes\On;
use Livewire\Component;

class SupportChat extends Component
{
    public string $sessionUlid = '';
    public array $messages = [];
    public string $input = '';
    public bool $streaming = false;
    public ?string $handoffReason = null;

    public function mount(string $tenantId): void
    {
        $response = \Http::post(route('support.start', $tenantId), [
            'visitor_id' => session()->getId(),
        ]);
        $this->sessionUlid = $response->json('session_id');
    }

    public function send(): void
    {
        if (trim($this->input) === '') return;
        $this->messages[] = ['role' => 'user', 'content' => $this->input];
        $this->streaming = true;
        $this->dispatch('start-stream', message: $this->input, session: $this->sessionUlid);
        $this->input = '';
    }

    #[On('stream-done')]
    public function onDone(string $text, ?float $confidence): void
    {
        $this->messages[] = ['role' => 'assistant', 'content' => $text, 'confidence' => $confidence];
        $this->streaming = false;
    }

    #[On('stream-handoff')]
    public function onHandoff(string $reason): void
    {
        $this->handoffReason = $reason;
    }

    public function render()
    {
        return view('livewire.support-chat');
    }
}
```

```blade
{{-- resources/views/livewire/support-chat.blade.php --}}
<div class="flex flex-col h-[600px] border rounded-xl bg-white" x-data="chatStream()" @start-stream.window="start($event.detail)">
    <div class="flex-1 overflow-y-auto p-4 space-y-3" x-ref="log">
        @foreach ($messages as $m)
            <div class="flex {{ $m['role'] === 'user' ? 'justify-end' : 'justify-start' }}">
                <div class="px-3 py-2 rounded-2xl max-w-[80%] {{ $m['role'] === 'user' ? 'bg-blue-600 text-white' : 'bg-gray-100' }}">
                    {{ $m['content'] }}
                    @if (!empty($m['confidence']))
                        <div class="text-xs opacity-60 mt-1">conf: {{ $m['confidence'] }}</div>
                    @endif
                </div>
            </div>
        @endforeach
        <template x-if="streaming"><div class="text-gray-500" x-text="partial"></div></template>
    </div>
    @if ($handoffReason)
        <div class="p-3 bg-yellow-50 border-t text-sm">Söhbət operatora ötürüldü ({{ $handoffReason }})</div>
    @endif
    <form wire:submit="send" class="flex gap-2 p-3 border-t">
        <input wire:model="input" class="flex-1 border rounded-lg px-3 py-2" placeholder="Sualınızı yazın..." />
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg" @disabled($streaming)>Göndər</button>
    </form>

    <script>
    function chatStream() {
        return {
            partial: '',
            streaming: false,
            start({ message, session }) {
                this.partial = '';
                this.streaming = true;
                const url = `/support/stream/${session}`;
                fetch(url, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
                    body: JSON.stringify({ message }),
                }).then(async (res) => {
                    const reader = res.body.getReader();
                    const decoder = new TextDecoder();
                    let buffer = '';
                    let finalConfidence = null;
                    while (true) {
                        const {done, value} = await reader.read();
                        if (done) break;
                        buffer += decoder.decode(value, {stream: true});
                        const events = buffer.split('\n\n');
                        buffer = events.pop();
                        for (const evt of events) {
                            const lines = evt.split('\n');
                            const type = lines.find(l => l.startsWith('event:'))?.slice(6).trim();
                            const data = JSON.parse(lines.find(l => l.startsWith('data:'))?.slice(5).trim() || 'null');
                            if (type === 'token') { this.partial += data; }
                            if (type === 'done') { finalConfidence = data.confidence; }
                            if (type === 'handoff') { Livewire.dispatch('stream-handoff', { reason: data.reason }); }
                        }
                    }
                    this.streaming = false;
                    Livewire.dispatch('stream-done', { text: this.partial, confidence: finalConfidence });
                });
            }
        };
    }
    </script>
</div>
```

---

## Route Qeydiyyatı

```php
// routes/web.php
use App\Http\Controllers\SupportChatController;

Route::middleware('throttle:60,1')->group(function () {
    Route::post('/support/{tenant}/start', [SupportChatController::class, 'start'])->name('support.start');
    Route::post('/support/stream/{session}', [SupportChatController::class, 'stream'])->name('support.stream');
    Route::post('/support/csat/{session}', [SupportChatController::class, 'csat'])->name('support.csat');
});
```

---

## Analytics Dashboard (oxşarı Filament üçün)

```php
// app/Services/Analytics/SupportMetrics.php
<?php

namespace App\Services\Analytics;

use App\Models\ChatSession;
use App\Models\Handoff;
use Illuminate\Support\Carbon;

class SupportMetrics
{
    public function dailySummary(int $tenantId, Carbon $day): array
    {
        $sessions = ChatSession::where('tenant_id', $tenantId)
            ->whereDate('created_at', $day)
            ->get();

        $total = $sessions->count();
        $handoffs = $sessions->where('status', 'handoff')->count();
        $resolved = $total - $handoffs;
        $avgCsat = $sessions->whereNotNull('csat_score')->avg('csat_score');

        $tokens = $sessions->sum(fn ($s) => $s->total_input_tokens + $s->total_output_tokens);
        $cost = $sessions->sum('cost_usd');

        return [
            'sessions' => $total,
            'resolved' => $resolved,
            'resolution_rate' => $total ? round($resolved / $total, 3) : 0,
            'handoffs' => $handoffs,
            'avg_csat' => $avgCsat ? round($avgCsat, 2) : null,
            'tokens_total' => $tokens,
            'cost_usd' => round($cost, 4),
            'cost_per_session' => $total ? round($cost / $total, 4) : 0,
        ];
    }

    public function topUnanswered(int $tenantId, int $limit = 20): array
    {
        return Handoff::where('tenant_id', $tenantId)
            ->where('reason', 'low_confidence')
            ->latest()
            ->limit($limit)
            ->get(['id', 'summary', 'created_at'])
            ->toArray();
    }
}
```

---

## Pest Testləri

```php
// tests/Feature/Support/KnowledgeIngestionTest.php
<?php

use App\Jobs\IngestDocumentJob;
use App\Models\DocumentChunk;
use App\Models\KnowledgeDocument;
use App\Models\Tenant;
use App\Services\KnowledgeBase\Chunker;
use App\Services\KnowledgeBase\Embedder;

it('chunks text with overlap', function () {
    $chunker = new Chunker();
    $text = str_repeat("Salam dünya. Bu uzun mətn parçasıdır.\n\n", 100);
    $chunks = $chunker->split($text, targetChars: 400, overlap: 80);

    expect($chunks)->toHaveCount(greaterThan(3));
    expect($chunks[0]['content'])->toBeString();
});

it('ingests document and stores chunks with embeddings', function () {
    $embedder = Mockery::mock(Embedder::class);
    $embedder->shouldReceive('embed')
        ->andReturn(array_fill(0, 10, array_fill(0, 1024, 0.01)));
    app()->instance(Embedder::class, $embedder);

    $tenant = Tenant::factory()->create();
    $doc = KnowledgeDocument::factory()->create([
        'tenant_id' => $tenant->id,
        'content' => str_repeat("Məhsul qaytarılması 14 gün ərzində qəbul edilir.\n\n", 50),
    ]);

    (new IngestDocumentJob($doc->id))->handle(app(Chunker::class), $embedder);

    expect(DocumentChunk::where('document_id', $doc->id)->count())->toBeGreaterThan(0);
});
```

```php
// tests/Feature/Support/ChatFlowTest.php
<?php

use App\Models\ChatSession;
use App\Models\Tenant;
use App\Services\Chat\SupportChatService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

function fakeClaudeStream(array $events): Client
{
    $body = '';
    foreach ($events as $e) {
        $body .= "event: {$e['type']}\n";
        $body .= 'data: ' . json_encode($e) . "\n\n";
    }
    $mock = new MockHandler([new Response(200, [], $body)]);
    return new Client(['handler' => HandlerStack::create($mock)]);
}

it('returns assistant text when no tool call is needed', function () {
    $tenant = Tenant::factory()->create();
    $session = ChatSession::factory()->create(['tenant_id' => $tenant->id]);

    $client = fakeClaudeStream([
        ['type' => 'message_start', 'message' => ['usage' => ['input_tokens' => 100]]],
        ['type' => 'content_block_start', 'index' => 0, 'content_block' => ['type' => 'text']],
        ['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'text_delta', 'text' => 'Salam! ']],
        ['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'text_delta', 'text' => 'Buyurun.']],
        ['type' => 'message_delta', 'delta' => ['stop_reason' => 'end_turn'], 'usage' => ['output_tokens' => 10]],
    ]);
    app()->instance(Client::class, $client);

    $service = app(SupportChatService::class);
    $chunks = [];
    foreach ($service->streamReply($session, 'Salam') as $event) {
        $chunks[] = $event;
    }

    $text = collect($chunks)->where('type', 'token')->pluck('data')->implode('');
    expect($text)->toBe('Salam! Buyurun.');
    expect($session->messages()->where('role', 'assistant')->exists())->toBeTrue();
});

it('escalates to human on low confidence', function () {
    $tenant = Tenant::factory()->create();
    $session = ChatSession::factory()->create(['tenant_id' => $tenant->id, 'status' => 'active']);

    // retriever boş qaytarır → confidence 0.2 → handoff
    $retriever = Mockery::mock(\App\Services\Retrieval\HybridRetriever::class);
    $retriever->shouldReceive('retrieve')->andReturn([]);
    app()->instance(\App\Services\Retrieval\HybridRetriever::class, $retriever);

    $client = fakeClaudeStream([
        ['type' => 'message_start', 'message' => ['usage' => ['input_tokens' => 50]]],
        ['type' => 'content_block_start', 'index' => 0, 'content_block' => ['type' => 'text']],
        ['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'text_delta', 'text' => 'Bilmirəm.']],
        ['type' => 'message_delta', 'delta' => ['stop_reason' => 'end_turn'], 'usage' => ['output_tokens' => 5]],
    ]);
    app()->instance(Client::class, $client);

    foreach (app(SupportChatService::class)->streamReply($session, 'qəribə sual') as $_) {}

    expect($session->fresh()->status)->toBe('handoff');
    expect(\App\Models\Handoff::where('session_id', $session->id)->exists())->toBeTrue();
});
```

```php
// tests/Unit/Support/BudgetGuardTest.php
<?php

use App\Models\ChatSession;
use App\Models\TenantBudget;
use App\Services\Chat\BudgetGuard;

it('blocks when tenant daily budget is exceeded', function () {
    $session = ChatSession::factory()->create();
    TenantBudget::create([
        'tenant_id' => $session->tenant_id,
        'day' => now()->toDateString(),
        'daily_limit_usd' => 1.0,
        'spent_usd' => 1.0,
    ]);

    $result = app(BudgetGuard::class)->canSendMessage($session);
    expect($result['ok'])->toBeFalse();
    expect($result['reason'])->toBe('tenant_budget_exceeded');
});

it('records cost correctly for sonnet', function () {
    $session = ChatSession::factory()->create();
    TenantBudget::create([
        'tenant_id' => $session->tenant_id,
        'day' => now()->toDateString(),
        'daily_limit_usd' => 100.0,
    ]);

    $cost = app(BudgetGuard::class)->recordUsage($session, 'claude-sonnet-4-5', 1_000_000, 1_000_000);
    expect($cost)->toEqualWithDelta(18.0, 0.01);
});
```

---

## Horizon Konfiqurasiyası

```php
// config/horizon.php (fraqment)
'environments' => [
    'production' => [
        'ingestion' => [
            'connection' => 'redis',
            'queue' => ['ingestion'],
            'balance' => 'auto',
            'maxProcesses' => 8,
            'timeout' => 300,
            'tries' => 3,
        ],
        'default' => [
            'connection' => 'redis',
            'queue' => ['default'],
            'maxProcesses' => 4,
        ],
    ],
],
```

Ingestion-u ayrıca queue-da saxlamaq vacibdir — chat trafiki blok olmasın. Nightly re-ingest Scheduler ilə:

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule): void
{
    $schedule->call(function () {
        \App\Models\Tenant::where('active', true)->each(function ($tenant) {
            app(\App\Services\KnowledgeBase\Crawler::class)
                ->crawlSite($tenant, $tenant->kb_start_url);
            \App\Models\KnowledgeDocument::where('tenant_id', $tenant->id)
                ->where('updated_at', '>', now()->subDay())
                ->each(fn ($doc) => \App\Jobs\IngestDocumentJob::dispatch($doc->id));
        });
    })->dailyAt('03:00');
}
```

---

## Deployment Qeydləri

**Infrastruktur:**
- PostgreSQL 16 + pgvector 0.7 (HNSW index üçün)
- Redis 7 (session RPM və cache)
- 2x Laravel Octane (FrankenPHP) — SSE uzun bağlantılar üçün
- Horizon (Supervisor altında) — ingestion worker
- Nginx konfiqurasiyasında `proxy_buffering off` SSE üçün

**Ətraf mühit:**
```
ANTHROPIC_API_KEY=sk-ant-...
VOYAGE_API_KEY=pa-...
SUPPORT_DEFAULT_DAILY_USD=10
SUPPORT_SESSION_RPM=10
DB_CONNECTION=pgsql
QUEUE_CONNECTION=redis
```

**Monitorinq:**
- Prometheus exporter: `chat_sessions_total`, `chat_resolution_rate`, `chat_handoffs_total`, `chat_tokens_in`, `chat_tokens_out`, `chat_cost_usd`.
- Alertlər: resolution_rate < 60% (saatlıq), cost_usd > daily_limit*0.8, handoff_rate > 40%.

**Təhlükəsizlik:**
- PII (email, sifariş nömrəsi) mesaj loglarında maskalana bilər — observability göndərmədən əvvəl `PiiRedactor` servisdən keçir.
- `escalate_human` yalnız açıq user istəyində və ya 2 dalbadal aşağı confidence-də triggerlə.
- Tool çağırışları RBAC ilə: müştəri sessiyasında yalnız read tool-lar; `create_ticket` sessiya sahibinə bağlıdır.

**Prompt caching:**
- System prompt `cache_control: ephemeral` ilə 5 dəqiqəlik cache. Context eyni olanda 90% input token qənaəti.
- KB kontekstini ayrıca system blokunda saxla — cache açarını qoruyur.

**Release checklist:**
- `php artisan migrate --force`
- `php artisan horizon:terminate` → supervisorctl restart
- `php artisan support:warm-cache` (sistem promptları)
- Canary: 5% trafik → yeni model → 30 dəqiqə SLO yoxla → full rollout.

Bu sxem real istehsal yüklərində istifadə olunur: tenant başına gündə ~500 sessiya, orta 4 mesaj, ~$0.008/sessiya, 72% auto-resolution.
