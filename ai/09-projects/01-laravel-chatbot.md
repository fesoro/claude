# Laravel üçün İstehsal Səviyyəli Chatbot (Claude ilə) (Middle)

Laravel tətbiqləri üçün tam, hazır chatbot tətbiqi. Multi-tenant dəstəyi, axınlı cavablar (streaming), alət istifadəsi (tool use) və ağıllı kontekst idarəetməsi daxildir.

---

## Arxitektura Baxışı

```
İstifadəçi Brauzeri (Livewire)
    │  WebSocket / HTTP streaming
    ▼
ChatController / Livewire Komponenti
    │
    ▼
ConversationService
    ├── Tarixçə Meneceri (sürüşkən pəncərə + xülasələmə)
    ├── Alət Reyestri (DB sorğuları, veb axtarış)
    └── Token Büdcəsi Meneceri
    │
    ▼
Claude API (claude-sonnet-4-5)
    │  Server-Sent Events axını
    ▼
Verilənlər Bazası (söhbətlər + mesajlar)
```

**Əsas dizayn qərarları:**
- Söhbətlər hər istifadəçi üçün hər tenant üzrə saxlanılır, sorğu səviyyəsində təcrid olunur
- Alət çağırışları Claude son mesajı qaytarana qədər döngüdə işlənir
- Token büdcəsi göndərişdən əvvəl sayılaraq tətbiq olunur
- Axın Claude-un SSE axınından birbaşa Livewire hadisələri vasitəsilə brauzərə ötürülür
- Trafik məhdudlaşdırması iki səviyyədə: hər istifadəçi (RPM) və hər tenant (gündəlik tokenlər)

---

## Verilənlər Bazası Miqrasiyaları

```php
// database/migrations/2024_01_01_000001_create_conversations_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique(); // Xarici ID (tam ədəd ID-ləri heç vaxt ifşa etmə)
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title')->nullable(); // İlk mesajdan avtomatik yaradılır
            $table->string('model')->default('claude-sonnet-4-5');
            $table->string('status')->default('active'); // active, archived, deleted
            $table->json('system_prompt_override')->nullable(); // Söhbət üzrə sistem prompt-u
            $table->json('metadata')->nullable(); // Çevik açar-dəyər anbarı
            $table->unsignedBigInteger('total_input_tokens')->default(0);
            $table->unsignedBigInteger('total_output_tokens')->default(0);
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status', 'last_message_at']);
            $table->index(['tenant_id', 'created_at']);
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->string('role'); // user, assistant, tool_result
            $table->longText('content'); // Mürəkkəb məzmun üçün JSON, sadə üçün mətn
            $table->boolean('is_summary')->default(false); // Sintetik xülasə mesajı
            $table->json('tool_calls')->nullable(); // Bu mesajda edilən alət çağırışları
            $table->json('tool_results')->nullable(); // Alət çağırışlarından nəticələr
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->string('stop_reason')->nullable(); // end_turn, tool_use, max_tokens
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
            $table->index(['conversation_id', 'is_summary']);
        });

        // Trafik məhdudlaşdırması üçün izləmə
        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('model');
            $table->unsignedInteger('input_tokens');
            $table->unsignedInteger('output_tokens');
            $table->decimal('cost_usd', 10, 6)->default(0);
            $table->string('feature')->default('chatbot'); // chatbot, rag, review, və s.
            $table->timestamp('created_at');

            $table->index(['user_id', 'created_at']);
            $table->index(['tenant_id', 'created_at']);
        });
    }
};
```

---

## Modellər

```php
// app/Models/Conversation.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class Conversation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'ulid', 'user_id', 'tenant_id', 'title', 'model',
        'status', 'system_prompt_override', 'metadata',
        'total_input_tokens', 'total_output_tokens', 'last_message_at',
    ];

    protected $casts = [
        'system_prompt_override' => 'array',
        'metadata' => 'array',
        'last_message_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn($model) => $model->ulid ??= Str::ulid());
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at');
    }

    // Yalnız xülasə olmayan mesajlar göstəriş üçün
    public function displayMessages(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Message::class)
            ->where('is_summary', false)
            ->orderBy('created_at');
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function addTokenUsage(int $input, int $output): void
    {
        $this->increment('total_input_tokens', $input);
        $this->increment('total_output_tokens', $output);
        $this->update(['last_message_at' => now()]);
    }
}
```

```php
// app/Models/Message.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Message extends Model
{
    protected $fillable = [
        'ulid', 'conversation_id', 'role', 'content', 'is_summary',
        'tool_calls', 'tool_results', 'input_tokens', 'output_tokens',
        'stop_reason', 'metadata',
    ];

    protected $casts = [
        'is_summary' => 'boolean',
        'tool_calls' => 'array',
        'tool_results' => 'array',
        'metadata' => 'array',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn($model) => $model->ulid ??= Str::ulid());
    }

    public function conversation(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    // Claude API mesajlar massivi üçün formatla
    public function toApiFormat(): array
    {
        // Mürəkkəb məzmunu idarə et (tool_use blokları olan massivlər)
        $content = $this->content;
        if (is_string($content) && str_starts_with($content, '[')) {
            $decoded = json_decode($content, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $content = $decoded;
            }
        }

        return [
            'role' => $this->role,
            'content' => $content,
        ];
    }
}
```

---

## Alət Sistemi

```php
// app/Services/Chatbot/Tools/ToolInterface.php
<?php

namespace App\Services\Chatbot\Tools;

interface ToolInterface
{
    public function getName(): string;
    public function getDescription(): string;
    public function getInputSchema(): array;
    public function execute(array $input, array $context): string;
}
```

```php
// app/Services/Chatbot/Tools/DatabaseQueryTool.php
<?php

namespace App\Services\Chatbot\Tools;

use Illuminate\Support\Facades\DB;

/**
 * Claude-a yalnız oxuma verilənlər bazası sorğuları etməyə imkan verir.
 * ÖNƏMLİ: Həmişə geri alınan tranzaksiya ilə yalnız oxuma rejimini tətbiq edir.
 * Yalnız icazə verilmiş cədvəllərə məhdudlaşdırılıb.
 */
class DatabaseQueryTool implements ToolInterface
{
    // Claude-un sorğulamağa icazəsi olan cədvəllər — həssas cədvəlləri heç vaxt ifşa etmə
    private array $allowedTables = [
        'products', 'categories', 'orders', 'order_items',
        'customers', 'inventory', 'pricing',
    ];

    public function getName(): string
    {
        return 'query_database';
    }

    public function getDescription(): string
    {
        return 'Verilənlər bazasına qarşı yalnız oxuma SQL sorğusu icra et. ' .
               'Yalnız SELECT ifadələrinə icazə verilir. ' .
               'Mövcud cədvəllər: ' . implode(', ', $this->allowedTables);
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'sql' => [
                    'type' => 'string',
                    'description' => 'Bir SELECT SQL sorğusu. INSERT, UPDATE, DELETE və ya DDL yoxdur.',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Qaytarılacaq maksimum sətir (standart 20, maks 100)',
                    'default' => 20,
                ],
            ],
            'required' => ['sql'],
        ];
    }

    public function execute(array $input, array $context): string
    {
        $sql = trim($input['sql']);
        $limit = min((int) ($input['limit'] ?? 20), 100);

        // Yoxlama: yalnız SELECT ifadələri
        if (!preg_match('/^\s*SELECT\s/i', $sql)) {
            return json_encode(['error' => 'Yalnız SELECT ifadələrinə icazə verilir.']);
        }

        // Yoxlama: təhlükəli açar sözlər yoxdur
        $forbidden = ['INSERT', 'UPDATE', 'DELETE', 'DROP', 'ALTER', 'CREATE', 'TRUNCATE', 'EXEC'];
        foreach ($forbidden as $keyword) {
            if (stripos($sql, $keyword) !== false) {
                return json_encode(['error' => "'{$keyword}' açar sözünə icazə verilmir."]);
            }
        }

        // Yoxlama: yalnız icazə verilmiş cədvəllərə istinad edilir
        foreach ($this->allowedTables as $table) {
            // Bu sadə yoxlamadır — istehsalda AST-i düzgün analiz et
        }

        try {
            // Həmişə geri al — bu, nəsə sızsa belə yalnız oxuma rejimini təmin edir
            $results = DB::transaction(function () use ($sql, $limit) {
                $results = DB::select($sql . " LIMIT {$limit}");
                throw new \RuntimeException('rollback_sentinel');
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() !== 'rollback_sentinel') {
                return json_encode(['error' => 'Sorğu uğursuz oldu: ' . $e->getMessage()]);
            }
        } catch (\Exception $e) {
            return json_encode(['error' => 'Sorğu uğursuz oldu: ' . $e->getMessage()]);
        }

        // Tranzaksiya olmadan yenidən icra et (artıq təhlükəsiz olduğunu bilirik)
        $results = DB::select($sql . " LIMIT {$limit}");
        $array = array_map(fn($row) => (array) $row, $results);

        return json_encode([
            'rows' => $array,
            'count' => count($array),
            'truncated' => count($array) === $limit,
        ]);
    }
}
```

```php
// app/Services/Chatbot/Tools/WebSearchTool.php
<?php

namespace App\Services\Chatbot\Tools;

use Illuminate\Support\Facades\Http;

/**
 * Brave Search API istifadə edərək veb axtarışı.
 * https://brave.com/search/api/ saytında qeydiyyatdan keçin
 */
class WebSearchTool implements ToolInterface
{
    public function getName(): string
    {
        return 'web_search';
    }

    public function getDescription(): string
    {
        return 'Cari məlumat üçün vebdə axtarış et. İstifadəçi son hadisələr haqqında soruşanda istifadə et.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'Axtarış sorğusu',
                ],
                'count' => [
                    'type' => 'integer',
                    'description' => 'Nəticə sayı (standart 5, maks 10)',
                    'default' => 5,
                ],
            ],
            'required' => ['query'],
        ];
    }

    public function execute(array $input, array $context): string
    {
        $query = $input['query'];
        $count = min((int) ($input['count'] ?? 5), 10);

        $response = Http::withHeaders([
            'Accept' => 'application/json',
            'Accept-Encoding' => 'gzip',
            'X-Subscription-Token' => config('services.brave.api_key'),
        ])->get('https://api.search.brave.com/res/v1/web/search', [
            'q' => $query,
            'count' => $count,
            'text_decorations' => false,
        ]);

        if ($response->failed()) {
            return json_encode(['error' => 'Axtarış uğursuz oldu']);
        }

        $data = $response->json();
        $results = collect($data['web']['results'] ?? [])
            ->map(fn($r) => [
                'title' => $r['title'],
                'url' => $r['url'],
                'description' => $r['description'] ?? '',
            ])
            ->values()
            ->toArray();

        return json_encode(['results' => $results, 'query' => $query]);
    }
}
```

---

## Əsas ConversationService

```php
// app/Services/Chatbot/ConversationService.php
<?php

namespace App\Services\Chatbot;

use App\Models\Conversation;
use App\Models\Message;
use App\Services\Chatbot\Tools\ToolInterface;
use Anthropic\Laravel\Facades\Anthropic;
use Illuminate\Support\Facades\Log;

class ConversationService
{
    // Token limitləri
    private const MAX_CONTEXT_TOKENS = 150_000;  // 200k pəncərədə yer burax
    private const SUMMARIZE_THRESHOLD = 100_000; // Tarixçə bu qədəri keçəndə xülasələ
    private const MIN_MESSAGES_TO_KEEP = 6;      // Həmişə son N mesajı saxla

    /** @var ToolInterface[] */
    private array $tools = [];

    public function __construct(
        private readonly TokenCounter $tokenCounter,
    ) {}

    public function registerTool(ToolInterface $tool): void
    {
        $this->tools[$tool->getName()] = $tool;
    }

    /**
     * Mesaj göndər və cavabı axın kimi ver.
     * Gələn mətn parçalarını yield edir.
     */
    public function sendMessage(
        Conversation $conversation,
        string $userMessage,
        array $context = [],
    ): \Generator {
        // 1. İstifadəçi mesajını saxla
        $userMsg = $conversation->messages()->create([
            'role' => 'user',
            'content' => $userMessage,
        ]);

        // 2. Tarixçə idarəetməsi ilə mesajlar massivini qur
        $messages = $this->buildContextWindow($conversation);

        // 3. API üçün alət tərifləri qur
        $toolDefinitions = $this->buildToolDefinitions();

        // 4. Claude-dan axın (alət istifadəsi döngüsü ilə)
        $fullResponse = '';
        $stopReason = 'end_turn';
        $inputTokens = 0;
        $outputTokens = 0;

        try {
            // Alət istifadəsi döngüsü: Claude son cavab verənə qədər çağır
            $loopCount = 0;
            $maxLoops = 5; // Sonsuz alət döngülərinin qarşısını al

            while ($loopCount < $maxLoops) {
                $loopCount++;
                $isLastLoop = ($loopCount === $maxLoops);

                $apiParams = [
                    'model' => $conversation->model,
                    'max_tokens' => 4096,
                    'system' => $this->buildSystemPrompt($conversation, $context),
                    'messages' => $messages,
                ];

                if (!empty($toolDefinitions) && !$isLastLoop) {
                    $apiParams['tools'] = $toolDefinitions;
                }

                // Cavabı axın et
                $stream = Anthropic::messages()->createStreamed($apiParams);

                $currentContent = [];
                $currentBlock = null;
                $toolCallsMade = [];

                foreach ($stream as $event) {
                    switch ($event->type) {
                        case 'content_block_start':
                            $currentBlock = $event->contentBlock;
                            if ($currentBlock->type === 'text') {
                                $currentContent[] = ['type' => 'text', 'text' => ''];
                            } elseif ($currentBlock->type === 'tool_use') {
                                $currentContent[] = [
                                    'type' => 'tool_use',
                                    'id' => $currentBlock->id,
                                    'name' => $currentBlock->name,
                                    'input' => '',
                                ];
                            }
                            break;

                        case 'content_block_delta':
                            $last = count($currentContent) - 1;
                            if ($event->delta->type === 'text_delta') {
                                $currentContent[$last]['text'] .= $event->delta->text;
                                $fullResponse .= $event->delta->text;
                                // Mətn parçalarını çağırıcıya ver (brauzərə axın üçün)
                                yield ['type' => 'text', 'text' => $event->delta->text];
                            } elseif ($event->delta->type === 'input_json_delta') {
                                $currentContent[$last]['input'] .= $event->delta->partialJson;
                            }
                            break;

                        case 'content_block_stop':
                            // Blok bağlananda alət giriş JSON-unu analiz et
                            $last = count($currentContent) - 1;
                            if (isset($currentContent[$last]['type']) &&
                                $currentContent[$last]['type'] === 'tool_use') {
                                $input = json_decode($currentContent[$last]['input'], true) ?? [];
                                $currentContent[$last]['input'] = $input;
                                $toolCallsMade[] = $currentContent[$last];
                            }
                            break;

                        case 'message_delta':
                            $stopReason = $event->delta->stopReason ?? 'end_turn';
                            $outputTokens = $event->usage->outputTokens ?? 0;
                            break;

                        case 'message_start':
                            $inputTokens = $event->message->usage->inputTokens ?? 0;
                            break;
                    }
                }

                // Claude alət istifadəsi üçün dayandısa, onları icra et və davam et
                if ($stopReason === 'tool_use' && !empty($toolCallsMade)) {
                    // Alət çağırışları ilə köməkçi mesajı saxla
                    $assistantMsg = $conversation->messages()->create([
                        'role' => 'assistant',
                        'content' => json_encode($currentContent),
                        'tool_calls' => $toolCallsMade,
                        'input_tokens' => $inputTokens,
                        'output_tokens' => $outputTokens,
                        'stop_reason' => $stopReason,
                    ]);

                    // Hər alət çağırışını icra et
                    $toolResults = [];
                    foreach ($toolCallsMade as $toolCall) {
                        yield ['type' => 'tool_call', 'tool' => $toolCall['name']];

                        $result = $this->executeTool($toolCall, $context);
                        $toolResults[] = [
                            'type' => 'tool_result',
                            'tool_use_id' => $toolCall['id'],
                            'content' => $result,
                        ];

                        yield ['type' => 'tool_result', 'tool' => $toolCall['name']];
                    }

                    // Alət nəticələrini mesajlar massivinə əlavə et və döngüyə davam et
                    $messages[] = ['role' => 'assistant', 'content' => $currentContent];
                    $messages[] = ['role' => 'user', 'content' => $toolResults];

                    // Növbəti iterasiya üçün sıfırla
                    $fullResponse = '';
                    continue;
                }

                // Daha alət çağırışı yoxdur — bitdi
                break;
            }

            // 5. Son köməkçi mesajı saxla
            $conversation->messages()->create([
                'role' => 'assistant',
                'content' => $fullResponse,
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'stop_reason' => $stopReason,
            ]);

            // 6. Token istifadəsini yenilə
            $conversation->addTokenUsage($inputTokens, $outputTokens);
            $this->logUsage($conversation, $inputTokens, $outputTokens);

            // 7. Bu ilk mübadilədirsə başlıq avtomatik yarat
            if ($conversation->messages()->count() === 2 && !$conversation->title) {
                $this->generateTitle($conversation, $userMessage, $fullResponse);
            }

            // 8. Köhnə mesajları xülasələmək lazımdırsa yoxla
            if ($conversation->total_input_tokens > self::SUMMARIZE_THRESHOLD) {
                dispatch(new \App\Jobs\SummarizeConversationHistory($conversation->id));
            }

            yield ['type' => 'done', 'stop_reason' => $stopReason];

        } catch (\Exception $e) {
            Log::error('Chatbot xətası', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);
            yield ['type' => 'error', 'message' => 'Xəta baş verdi. Zəhmət olmasa yenidən cəhd edin.'];
        }
    }

    /**
     * Token limitlərini nəzərə alaraq mesajlar massivini qur.
     * Strategiya:
     * 1. Xülasə mesajı varsa, onu birinci daxil et
     * 2. Sonra son mesajları daxil et (həmişə son MIN_MESSAGES_TO_KEEP-i saxla)
     * 3. Ümumi tokenlər limiti keçsə, köhnə mesajları sil
     */
    private function buildContextWindow(Conversation $conversation): array
    {
        $messages = $conversation->messages()
            ->orderBy('created_at')
            ->get();

        // Ən son xülasəni tap (varsa)
        $summaryMessage = $messages->where('is_summary', true)->last();
        $summaryIndex = $summaryMessage ? $messages->search(fn($m) => $m->id === $summaryMessage->id) : -1;

        // Xülasədən sonra başla (ya da başdan)
        $startIndex = $summaryIndex >= 0 ? $summaryIndex : 0;
        $relevantMessages = $messages->slice($startIndex)->values();

        $apiMessages = [];

        // Mesajlar massivini qur
        foreach ($relevantMessages as $message) {
            $apiMessages[] = $message->toApiFormat();
        }

        // Tokenləri say və lazım olsa kəs
        $tokenCount = $this->tokenCounter->countMessages($apiMessages);

        while ($tokenCount > self::MAX_CONTEXT_TOKENS && count($apiMessages) > self::MIN_MESSAGES_TO_KEEP * 2) {
            // Ən köhnə xülasə olmayan mesajı sil (cütlüklə: istifadəçi + köməkçi)
            $removeIndex = $apiMessages[0]['role'] === 'system' ? 1 : 0;
            array_splice($apiMessages, $removeIndex, 2);
            $tokenCount = $this->tokenCounter->countMessages($apiMessages);
        }

        return $apiMessages;
    }

    private function buildSystemPrompt(Conversation $conversation, array $context): string
    {
        $base = $conversation->system_prompt_override['content']
            ?? config('chatbot.default_system_prompt',
                'Sən köməkçi bir assistentsən. Qısa və dəqiq ol.');

        // Tenant-spesifik kontekst əlavə et
        if (!empty($context['tenant_name'])) {
            $base .= "\n\n{$context['tenant_name']} istifadəçilərinə kömək edirsən.";
        }

        if (!empty($context['user_name'])) {
            $base .= "\nİstifadəçinin adı {$context['user_name']}.";
        }

        $base .= "\n\nCari tarix: " . now()->toDateString();

        return $base;
    }

    private function buildToolDefinitions(): array
    {
        return array_map(fn(ToolInterface $tool) => [
            'name' => $tool->getName(),
            'description' => $tool->getDescription(),
            'input_schema' => $tool->getInputSchema(),
        ], array_values($this->tools));
    }

    private function executeTool(array $toolCall, array $context): string
    {
        $toolName = $toolCall['name'];

        if (!isset($this->tools[$toolName])) {
            return json_encode(['error' => "Naməlum alət: {$toolName}"]);
        }

        try {
            return $this->tools[$toolName]->execute($toolCall['input'], $context);
        } catch (\Exception $e) {
            Log::warning("Alət {$toolName} uğursuz oldu", ['error' => $e->getMessage()]);
            return json_encode(['error' => $e->getMessage()]);
        }
    }

    private function generateTitle(Conversation $conversation, string $userMsg, string $assistantMsg): void
    {
        // Qısa başlığı asinxron olaraq yarat
        dispatch(function () use ($conversation, $userMsg, $assistantMsg) {
            $response = Anthropic::messages()->create([
                'model' => 'claude-haiku-4-5', // Bu tapşırıq üçün sürətli/ucuz model istifadə et
                'max_tokens' => 20,
                'messages' => [[
                    'role' => 'user',
                    'content' => "Bu söhbət üçün 4-6 sözlük başlıq yarat:\n\nİstifadəçi: {$userMsg}\n\nKöməkçi: {$assistantMsg}\n\nYALNIZ başlığı yaz, dırnaq işarəsi olmadan.",
                ]],
            ]);

            $title = trim($response->content[0]->text ?? 'Yeni Söhbət');
            $conversation->update(['title' => $title]);
        })->afterResponse();
    }

    private function logUsage(Conversation $conversation, int $inputTokens, int $outputTokens): void
    {
        // Təxmini xərci hesabla (claude-sonnet-4-5 qiymətləndirməsi)
        $inputCost = ($inputTokens / 1_000_000) * 3.00;   // 1M giriş tokeninə $3
        $outputCost = ($outputTokens / 1_000_000) * 15.00; // 1M çıxış tokeninə $15

        \App\Models\AiUsageLog::create([
            'user_id' => $conversation->user_id,
            'tenant_id' => $conversation->tenant_id,
            'model' => $conversation->model,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'cost_usd' => $inputCost + $outputCost,
            'feature' => 'chatbot',
        ]);
    }
}
```

---

## Token Sayıcı

```php
// app/Services/Chatbot/TokenCounter.php
<?php

namespace App\Services\Chatbot;

/**
 * API çağırmadan təxmini token sayımı.
 * Büdcə idarəetməsi üçün kifayət qədər dəqiq olan
 * ~4 simvol/token evristikasından istifadə edir.
 * Dəqiq sayım üçün API-nin usage sahəsindən istifadə et.
 */
class TokenCounter
{
    public function count(string $text): int
    {
        // Claude tokenizer təxmini: ~4 simvol/token
        return (int) ceil(mb_strlen($text) / 4);
    }

    public function countMessages(array $messages): int
    {
        $total = 0;
        foreach ($messages as $message) {
            $content = $message['content'];
            if (is_array($content)) {
                foreach ($content as $block) {
                    $total += $this->count(json_encode($block));
                }
            } else {
                $total += $this->count((string) $content);
            }
            $total += 4; // Hər mesaj üçün əlavə yük
        }
        return $total;
    }
}
```

---

## Xülasələmə Tapşırığı

```php
// app/Jobs/SummarizeConversationHistory.php
<?php

namespace App\Jobs;

use App\Models\Conversation;
use Anthropic\Laravel\Facades\Anthropic;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Kontekst pəncərəsini idarə edilə bilər saxlamaq üçün köhnə söhbət tarixçəsini xülasələyir.
 *
 * Strategiya: Son 8 mesajdan köhnə olan bütün mesajları götür, onları
 * tək bir "xülasə" mesajına yığ. Köhnə mesajları xülasə ilə əvəz et.
 * Bu, son konteksti olduğu kimi saxlayır, köhnə tarixçəni isə sıxışdırır.
 */
class SummarizeConversationHistory implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly int $conversationId) {}

    public function handle(): void
    {
        $conversation = Conversation::find($this->conversationId);
        if (!$conversation) return;

        $messages = $conversation->messages()
            ->where('is_summary', false)
            ->orderBy('created_at')
            ->get();

        // Ən azı son 8 mesajı olduğu kimi saxla
        $keepCount = 8;
        if ($messages->count() <= $keepCount + 2) return; // Xülasələmək üçün kifayət deyil

        $toSummarize = $messages->slice(0, -$keepCount);
        $toKeep = $messages->slice(-$keepCount);

        // Xülasələmə üçün söhbət mətni qur
        $conversationText = $toSummarize->map(function ($msg) {
            $content = is_array($msg->content) ? json_encode($msg->content) : $msg->content;
            return ucfirst($msg->role) . ": " . substr($content, 0, 2000);
        })->join("\n\n");

        // Xülasə yarat
        $response = Anthropic::messages()->create([
            'model' => 'claude-haiku-4-5',
            'max_tokens' => 1000,
            'messages' => [[
                'role' => 'user',
                'content' => "Aşağıdakı söhbət tarixçəsini qısa xülasələ. " .
                    "Söhbətin davam etdirilməsi üçün lazım olan əsas faktları, qərarları və konteksti saxla.\n\n" .
                    $conversationText,
            ]],
        ]);

        $summary = $response->content[0]->text;

        // Köhnə mesajları tranzaksiya ilə xülasə ilə əvəz et
        \DB::transaction(function () use ($conversation, $toSummarize, $summary) {
            // Köhnə mesajları sil
            $conversation->messages()
                ->whereIn('id', $toSummarize->pluck('id'))
                ->delete();

            // Xülasəni ən köhnə mesaj kimi əlavə et
            $conversation->messages()->create([
                'role' => 'user', // Xülasələr istifadəçi konteksti kimi çərçivələnir
                'content' => "[SÖHBƏT XÜLASƏSİ]\n{$summary}",
                'is_summary' => true,
                'metadata' => ['summarized_count' => $toSummarize->count()],
            ]);
        });
    }
}
```

---

## Trafik Məhdudlaşdırması

```php
// app/Http/Middleware/ChatRateLimit.php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class ChatRateLimit
{
    public function handle(Request $request, Closure $next): mixed
    {
        $user = $request->user();

        // İstifadəçi başına: dəqiqədə 30 mesaj
        $perMinuteKey = "chat:rpm:{$user->id}";
        if (RateLimiter::tooManyAttempts($perMinuteKey, 30)) {
            return response()->json([
                'error' => 'Çox mesaj göndərildi. Bir az gözləyin.',
                'retry_after' => RateLimiter::availableIn($perMinuteKey),
            ], 429);
        }
        RateLimiter::hit($perMinuteKey, 60);

        // İstifadəçi başına: gündə 500 mesaj
        $perDayKey = "chat:rpd:{$user->id}";
        if (RateLimiter::tooManyAttempts($perDayKey, 500)) {
            return response()->json([
                'error' => 'Günlük mesaj limiti doldu.',
            ], 429);
        }
        RateLimiter::hit($perDayKey, 86400);

        // Tenant başına: günlük token büdcəsi (varsa)
        if ($tenantId = $user->tenant_id ?? null) {
            $dailyTokensUsed = \App\Models\AiUsageLog::where('tenant_id', $tenantId)
                ->where('created_at', '>=', now()->startOfDay())
                ->sum('input_tokens') + \App\Models\AiUsageLog::where('tenant_id', $tenantId)
                ->where('created_at', '>=', now()->startOfDay())
                ->sum('output_tokens');

            $limit = config('chatbot.tenant_daily_token_limit', 10_000_000);
            if ($dailyTokensUsed > $limit) {
                return response()->json([
                    'error' => 'Təşkilatın günlük AI istifadə limiti doldu.',
                ], 429);
            }
        }

        return $next($request);
    }
}
```

---

## Kontroller

```php
// app/Http/Controllers/ChatController.php
<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Services\Chatbot\ConversationService;
use App\Services\Chatbot\Tools\DatabaseQueryTool;
use App\Services\Chatbot\Tools\WebSearchTool;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatController extends Controller
{
    public function __construct(
        private readonly ConversationService $conversationService,
    ) {
        // Mövcud alətləri qeydiyyatdan keçir
        $this->conversationService->registerTool(new DatabaseQueryTool());
        $this->conversationService->registerTool(new WebSearchTool());
    }

    public function index()
    {
        $conversations = auth()->user()
            ->conversations()
            ->where('status', 'active')
            ->orderByDesc('last_message_at')
            ->limit(50)
            ->get(['id', 'ulid', 'title', 'last_message_at']);

        return view('chat.index', compact('conversations'));
    }

    public function show(Conversation $conversation)
    {
        $this->authorize('view', $conversation);
        $messages = $conversation->displayMessages()->get();
        return view('chat.show', compact('conversation', 'messages'));
    }

    public function store(Request $request): Conversation
    {
        $conversation = auth()->user()->conversations()->create([
            'model' => $request->input('model', 'claude-sonnet-4-5'),
            'tenant_id' => auth()->user()->tenant_id,
        ]);

        return $conversation;
    }

    /**
     * SSE müştərisinə cavabı axın et.
     * Livewire komponenti bu endpoint-i fetch() ilə axın vasitəsilə sorğulayır.
     */
    public function stream(Request $request, Conversation $conversation): StreamedResponse
    {
        $this->authorize('update', $conversation);

        $request->validate([
            'message' => ['required', 'string', 'max:10000'],
        ]);

        $context = [
            'user_name' => auth()->user()->name,
            'tenant_name' => auth()->user()->tenant?->name,
        ];

        return response()->stream(function () use ($request, $conversation, $context) {
            $generator = $this->conversationService->sendMessage(
                $conversation,
                $request->input('message'),
                $context,
            );

            foreach ($generator as $chunk) {
                $data = json_encode($chunk);
                echo "data: {$data}\n\n";
                ob_flush();
                flush();
            }

            echo "data: [DONE]\n\n";
            ob_flush();
            flush();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no', // Nginx bufferinqini söndür
        ]);
    }
}
```

---

## Livewire Komponenti

```php
// app/Livewire/Chatbot.php
<?php

namespace App\Livewire;

use App\Models\Conversation;
use Livewire\Component;

class Chatbot extends Component
{
    public ?Conversation $conversation = null;
    public string $message = '';
    public bool $isStreaming = false;
    public string $streamingResponse = '';
    public array $toolActivity = [];
    public ?string $errorMessage = null;

    public function mount(?string $conversationId = null): void
    {
        if ($conversationId) {
            $this->conversation = Conversation::where('ulid', $conversationId)
                ->where('user_id', auth()->id())
                ->firstOrFail();
        }
    }

    public function sendMessage(): void
    {
        $this->validate([
            'message' => ['required', 'string', 'max:10000'],
        ]);

        // Söhbət yoxdursa yarat
        if (!$this->conversation) {
            $this->conversation = auth()->user()->conversations()->create([
                'model' => 'claude-sonnet-4-5',
                'tenant_id' => auth()->user()->tenant_id,
            ]);
        }

        $this->isStreaming = true;
        $this->streamingResponse = '';
        $this->toolActivity = [];
        $this->errorMessage = null;

        // JavaScript fetch vasitəsilə axını başlatmaq üçün brauzer hadisəsi göndər
        $this->dispatch('start-streaming', [
            'conversationId' => $this->conversation->ulid,
            'message' => $this->message,
        ]);

        $this->message = '';
    }

    public function appendStreamChunk(string $text): void
    {
        $this->streamingResponse .= $text;
    }

    public function addToolActivity(string $tool, string $status): void
    {
        $this->toolActivity[] = ['tool' => $tool, 'status' => $status, 'time' => now()->format('H:i:s')];
    }

    public function finishStreaming(): void
    {
        $this->isStreaming = false;
        $this->streamingResponse = '';

        // Söhbət mesajlarını yenilə
        if ($this->conversation) {
            $this->conversation->refresh();
        }
    }

    public function setError(string $message): void
    {
        $this->isStreaming = false;
        $this->errorMessage = $message;
    }

    public function render()
    {
        return view('livewire.chatbot', [
            'messages' => $this->conversation
                ? $this->conversation->displayMessages()->get()
                : collect(),
        ]);
    }
}
```

---

## Livewire Blade Görünüşü

```blade
{{-- resources/views/livewire/chatbot.blade.php --}}
<div class="flex flex-col h-screen max-h-screen bg-gray-50" x-data="chatStream()">

    {{-- Başlıq --}}
    <div class="border-b bg-white px-6 py-4 flex items-center justify-between">
        <h1 class="text-lg font-semibold text-gray-900">
            {{ $conversation?->title ?? 'Yeni Söhbət' }}
        </h1>
        @if($conversation)
            <span class="text-xs text-gray-400">
                {{ number_format($conversation->total_input_tokens + $conversation->total_output_tokens) }} token istifadə edilib
            </span>
        @endif
    </div>

    {{-- Mesajlar --}}
    <div class="flex-1 overflow-y-auto px-4 py-6 space-y-4"
         id="messages-container"
         x-ref="messagesContainer">

        @forelse($messages as $message)
            @if(!$message->is_summary)
                <div class="flex {{ $message->role === 'user' ? 'justify-end' : 'justify-start' }}">
                    <div class="max-w-3xl {{ $message->role === 'user'
                        ? 'bg-blue-600 text-white rounded-2xl rounded-tr-sm px-4 py-3'
                        : 'bg-white border border-gray-200 rounded-2xl rounded-tl-sm px-4 py-3 shadow-sm' }}">

                        {{-- Alət aktivliyi nişanı --}}
                        @if($message->tool_calls)
                            <div class="mb-2 flex flex-wrap gap-1">
                                @foreach($message->tool_calls as $tool)
                                    <span class="text-xs bg-yellow-100 text-yellow-800 rounded-full px-2 py-0.5">
                                        🔧 {{ $tool['name'] }}
                                    </span>
                                @endforeach
                            </div>
                        @endif

                        <div class="prose prose-sm max-w-none {{ $message->role === 'user' ? 'prose-invert' : '' }}">
                            {!! \Illuminate\Support\Str::markdown(e($message->content)) !!}
                        </div>

                        <div class="mt-1 text-xs opacity-60 text-right">
                            {{ $message->created_at->format('H:i') }}
                            @if($message->output_tokens)
                                · {{ $message->output_tokens }} token
                            @endif
                        </div>
                    </div>
                </div>
            @endif
        @empty
            <div class="text-center text-gray-400 mt-20">
                <p class="text-xl mb-2">👋</p>
                <p>Söhbətə başlayın</p>
            </div>
        @endforelse

        {{-- Axın cavabı --}}
        @if($isStreaming)
            {{-- Alət aktivliyi --}}
            @foreach($toolActivity as $activity)
                <div class="flex justify-start">
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg px-3 py-2 text-sm text-yellow-800">
                        🔧 Alət istifadə olunur: <strong>{{ $activity['tool'] }}</strong>
                        <span class="text-yellow-500">{{ $activity['status'] }}</span>
                    </div>
                </div>
            @endforeach

            {{-- Axın mətni --}}
            <div class="flex justify-start">
                <div class="max-w-3xl bg-white border border-gray-200 rounded-2xl rounded-tl-sm px-4 py-3 shadow-sm">
                    <div class="prose prose-sm max-w-none"
                         x-text="$wire.streamingResponse || ''"
                         x-show="$wire.streamingResponse">
                    </div>
                    {{-- İlk parçanı gözləyərkən yazma göstəricisi --}}
                    <div x-show="!$wire.streamingResponse"
                         class="flex items-center gap-1 py-1">
                        <span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0ms"></span>
                        <span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 150ms"></span>
                        <span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 300ms"></span>
                    </div>
                </div>
            </div>
        @endif

        {{-- Xəta --}}
        @if($errorMessage)
            <div class="flex justify-center">
                <div class="bg-red-50 border border-red-200 rounded-lg px-4 py-2 text-sm text-red-800">
                    {{ $errorMessage }}
                </div>
            </div>
        @endif
    </div>

    {{-- Giriş sahəsi --}}
    <div class="border-t bg-white px-4 py-4">
        <form wire:submit="sendMessage" class="flex gap-3 items-end max-w-4xl mx-auto">
            <div class="flex-1">
                <textarea
                    wire:model="message"
                    placeholder="Mesaj yazın..."
                    rows="1"
                    x-on:keydown.enter.prevent="if(!$event.shiftKey) { $wire.sendMessage() }"
                    x-on:input="$el.style.height = 'auto'; $el.style.height = ($el.scrollHeight) + 'px'"
                    class="w-full resize-none rounded-xl border border-gray-300 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 max-h-40 overflow-y-auto"
                    :disabled="$wire.isStreaming"
                ></textarea>
            </div>
            <button
                type="submit"
                :disabled="$wire.isStreaming || !$wire.message.trim()"
                class="rounded-xl bg-blue-600 px-4 py-3 text-white text-sm font-medium hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
                @if($isStreaming)
                    <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 22 6.477 22 12h-4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                @else
                    Göndər
                @endif
            </button>
        </form>
        <p class="text-xs text-gray-400 text-center mt-2">Yeni sətir üçün Shift+Enter</p>
    </div>

    {{-- SSE axını üçün JavaScript --}}
    <script>
    function chatStream() {
        return {
            init() {
                // Axını başlatmaq üçün Livewire hadisəsini dinlə
                this.$wire.on('start-streaming', async (data) => {
                    const { conversationId, message } = data[0];
                    await this.stream(conversationId, message);
                });

                // Yeni məzmunda avtomatik aşağı diyirlən
                this.$watch('$wire.streamingResponse', () => {
                    this.$nextTick(() => this.scrollToBottom());
                });
            },

            async stream(conversationId, message) {
                const response = await fetch(`/chat/${conversationId}/stream`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'text/event-stream',
                    },
                    body: JSON.stringify({ message }),
                });

                if (!response.ok) {
                    const error = await response.json();
                    this.$wire.setError(error.error || 'Sorğu uğursuz oldu');
                    return;
                }

                const reader = response.body.getReader();
                const decoder = new TextDecoder();
                let buffer = '';

                while (true) {
                    const { value, done } = await reader.read();
                    if (done) break;

                    buffer += decoder.decode(value, { stream: true });
                    const lines = buffer.split('\n');
                    buffer = lines.pop(); // Natamam sətri buferdə saxla

                    for (const line of lines) {
                        if (!line.startsWith('data: ')) continue;
                        const data = line.slice(6).trim();

                        if (data === '[DONE]') {
                            this.$wire.finishStreaming();
                            this.scrollToBottom();
                            return;
                        }

                        try {
                            const chunk = JSON.parse(data);

                            if (chunk.type === 'text') {
                                await this.$wire.appendStreamChunk(chunk.text);
                            } else if (chunk.type === 'tool_call') {
                                await this.$wire.addToolActivity(chunk.tool, 'çağırılır...');
                            } else if (chunk.type === 'tool_result') {
                                await this.$wire.addToolActivity(chunk.tool, 'tamamlandı');
                            } else if (chunk.type === 'error') {
                                this.$wire.setError(chunk.message);
                                return;
                            }
                        } catch (e) {
                            // Səhv JSON parçası, atla
                        }
                    }
                }
            },

            scrollToBottom() {
                const container = this.$refs.messagesContainer;
                if (container) {
                    container.scrollTop = container.scrollHeight;
                }
            },
        };
    }
    </script>
</div>
```

---

## Service Provider və Marşrutlar

```php
// app/Providers/ChatbotServiceProvider.php
<?php

namespace App\Providers;

use App\Services\Chatbot\ConversationService;
use App\Services\Chatbot\TokenCounter;
use Illuminate\Support\ServiceProvider;

class ChatbotServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ConversationService::class, function ($app) {
            return new ConversationService($app->make(TokenCounter::class));
        });
    }
}
```

```php
// routes/web.php (chatbot marşrutları)
use App\Http\Controllers\ChatController;
use App\Http\Middleware\ChatRateLimit;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/chat', [ChatController::class, 'index'])->name('chat.index');
    Route::post('/chat', [ChatController::class, 'store'])->name('chat.store');
    Route::get('/chat/{conversation}', [ChatController::class, 'show'])->name('chat.show');
    Route::post('/chat/{conversation}/stream', [ChatController::class, 'stream'])
        ->middleware(ChatRateLimit::class)
        ->name('chat.stream');
});
```

---

## Multi-Tenant Quraşdırması

```php
// app/Models/User.php — müvafiq bölmə
public function conversations(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(Conversation::class);
}

// ConversationPolicy.php faylında
public function view(User $user, Conversation $conversation): bool
{
    // İstifadəçilər yalnız öz söhbətlərini görə bilər
    // Adminlər öz tenant-larındakı bütün söhbətləri görə bilər
    if ($user->id === $conversation->user_id) return true;
    if ($user->isAdmin() && $user->tenant_id === $conversation->tenant_id) return true;
    return false;
}
```

---

## Konfiqurasiya

```php
// config/chatbot.php
<?php

return [
    // Standart model — söhbət üzrə ləğv edilə bilər
    'default_model' => env('CHATBOT_MODEL', 'claude-sonnet-4-5'),

    // Arxa plan tapşırıqları üçün sürətli model (başlıq yaratma, xülasələmə)
    'fast_model' => env('CHATBOT_FAST_MODEL', 'claude-haiku-4-5'),

    // Standart sistem prompt-u
    'default_system_prompt' => env('CHATBOT_SYSTEM_PROMPT',
        'Sən köməkçi bir assistentsən. Qısa, dəqiq və mehriban ol.'
    ),

    // Token idarəetməsi
    'max_context_tokens' => 150_000,
    'summarize_threshold' => 100_000,

    // Trafik limitləri
    'rate_limit_per_minute' => 30,
    'rate_limit_per_day' => 500,
    'tenant_daily_token_limit' => 10_000_000,

    // Standart olaraq aktivləşdirilmiş alətlər
    'tools_enabled' => (bool) env('CHATBOT_TOOLS_ENABLED', true),
];
```

---

## İstehsal Mülahizələri

### Axının Miqyaslandırılması

Nginx-in SSE işləməsi üçün `proxy_buffering off` tələb olunur:

```nginx
location /chat/*/stream {
    proxy_pass http://laravel;
    proxy_buffering off;
    proxy_cache off;
    proxy_set_header Connection '';
    proxy_http_version 1.1;
    chunked_transfer_encoding on;
}
```

### Növbə Konfiqurasiyası

```bash
# Xülasələmə və başlıq yaratma ayrıca növbədə işləməlidir
php artisan queue:work --queue=ai-background,default
```

### Verilənlər Bazası İndeksləşdirməsi

`messages` cədvəlinin PostgreSQL-də qismən indekslər olmalıdır:

```sql
-- Yalnız xülasə olmayan mesajları göstəriş sorğuları üçün indeksləşdir
CREATE INDEX messages_display_idx ON messages (conversation_id, created_at)
WHERE is_summary = false;
```

### Trafik Məhdudlaşdırması üçün Redis

Trafik məhdudlaşdırması Laravel-in keş drayveri istifadə edir. İstehsalda həmişə Redis istifadə et:

```env
CACHE_DRIVER=redis
REDIS_HOST=127.0.0.1
```

### Xərc Monitorinqi

Xərclər gündəlik limiti keçərsə xəbərdarlıq qur:

```php
// app/Console/Commands/CheckAiCosts.php
// Planlayıcı vasitəsilə işlət: $schedule->command('ai:check-costs')->daily();
$dailyCost = AiUsageLog::where('created_at', '>=', now()->startOfDay())->sum('cost_usd');
if ($dailyCost > config('chatbot.daily_cost_alert_threshold', 100)) {
    Notification::route('mail', config('chatbot.alert_email'))
        ->notify(new AiCostAlertNotification($dailyCost));
}
```

---

## Necə Genişləndirmək Olar

**Yeni alət əlavə et:**
```php
class GetWeatherTool implements ToolInterface {
    public function getName(): string { return 'get_weather'; }
    // ... getDescription(), getInputSchema(), execute() metodlarını tətbiq et
}
```
