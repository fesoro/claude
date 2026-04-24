# Agent Yaddaş Sistemləri

## Yaddaş Niyə Vacibdir

Yaddaşsız LLM — amneziyası olan parlaq analitik kimidir — an içərisində qabiliyyətli, amma keçmiş işlər üzərindən qura bilmir. İstehsalat agentlərinin müxtəlif məqsədlərə xidmət edən, fərqli zaman ölçülərindəki bir neçə yaddaş növünə ehtiyacı var.

Əsas gərginlik: **kontekst pəncərələri sonludur, amma faydalı məlumat hədsizdir**. Yaddaş sistemləri bu gərginliyə mühəndislik həllidir.

---

## Agent Yaddaşının Dörd Növü

```
┌─────────────────────────────────────────────────────────────────┐
│                     YADDAŞ TƏSNİFATI                            │
│                                                                 │
│  ┌──────────────────────┐  ┌──────────────────────┐            │
│  │   KONTEKSTDAXİLİ     │  │   XARİCİ             │            │
│  │   (İşçi Yaddaş)      │  │   (Uzunmüddətli Anbar)│           │
│  │                      │  │                      │            │
│  │  • Cari söhbət       │  │  • Verilənlər bazası  │            │
│  │  • Son alət çağırışı │  │  • Vektor anbarı      │            │
│  │  • Sistem promptu    │  │  • Fayl sistemi        │            │
│  │                      │  │                      │            │
│  │  Sürətli, müvəqqəti  │  │  Yavaş, qalıcı       │            │
│  └──────────────────────┘  └──────────────────────┘            │
│                                                                 │
│  ┌──────────────────────┐  ┌──────────────────────┐            │
│  │   EPİZODİK           │  │   SEMANTİK           │            │
│  │   (Keçmiş Təcrübə)   │  │   (Faktlar/Bilik)    │            │
│  │                      │  │                      │            │
│  │  • Əvvəlki işlər     │  │  • Dünya faktları    │            │
│  │  • Nə işə yaradı     │  │  • İstifadəçi seçimləri│          │
│  │  • Nəticələr         │  │  • Sahə bilici        │            │
│  │                      │  │                      │            │
│  │  "Bunu etmişəm"      │  │  "Bilirəm ki..."     │            │
│  └──────────────────────┘  └──────────────────────┘            │
└─────────────────────────────────────────────────────────────────┘
```

### Növ 1: Kontekstdaxili Yaddaş (İşçi Yaddaş)

Cari kontekst pəncərəsindəki hər şey. Bu agentin "işçi yaddaşı"dır — sürətli, sıfır gecikmə, amma:
- **Məhdud**: 200K token böyük görünür, amma uzun agentlik işlərində tez dolur
- **Müvəqqəti**: sessiya bitdikdə yox olur
- **Bahalı**: hər token hər çağırışda pul xərcləyir

**İdarəetmə strategiyası**: sürüşən pəncərə + xülasə. Son N mesajı olduğu kimi saxlayın; köhnə mesajları xülasəyə sıxışdırın.

### Növ 2: Xarici Yaddaş (Uzunmüddətli Anbar)

LLM-in xaricində saxlanılmış. Agent müvafiq məlumatı əldə etmək üçün alətlər istifadə edir.
- **Limitsiz ölçü**: yalnız saxlama sahəsi ilə məhdudlaşır
- **Geri alma gecikmə**: müvafiq məlumatı tapmaq üçün sorğu/axtarış lazımdır
- **Qalıcı**: sessiyanın yenidən başlamasından sağ qalır

**İmplementasiya**: verilənlər bazası, vektor anbarları, sənəd anbarları.

### Növ 3: Epizodik Yaddaş (Keçmiş Təcrübə)

Agentin əvvəlki işlərdə nə etdiyi və nəticənin nə olduğunun qeydləri. Agentlər bu sayədə "təcrübədən öyrənir" — çəkilərini yeniləməklə deyil, keçmiş nəticələri geri çağırıb tətbiq etməklə.

```
Keçmiş iş: "İstifadəçi Q3 satışlarını analiz etmək istədi. Sifarişlər cədvəlini
            tarix aralığına görə sorğuladım. Sorğu 45 saniyə çəkdi — created_at
            üzərində index lazım idi. Əlavə edildi: bütün tarix aralığı sorğularında
            created_at indeksindən istifadə et."
```

### Növ 4: Semantik Yaddaş (Faktlar və Bilik)

Sahə haqqında faktiki bilik — istifadəçi seçimləri, biznes qaydaları, tez-tez dəyişməyən sahəyə xas faktlar.

```
user_id 42 haqqında semantik faktlar:
  - Ətraflı texniki hesabatları üstün tutur (xülasə deyil)
  - PST saat qurşağında işləyir
  - Əsas verilənlər bazası PostgreSQL 15-dir
  - Gəlir məlumatı USD-dədir, EUR deyil
```

---

## Yaddaş Sıxışdırma Strategiyaları

### Sürüşən Pəncərə

Yalnız son N mesajı saxlayın. Sadə, proqnozlaşdırıla bilən, amma erkən konteksti itirir.

```
Mesajlar: [M1, M2, M3, M4, M5, M6, M7, M8]
Pəncərə=5:        [M4, M5, M6, M7, M8]
                   ↑ köhnə mesajlar atılır
```

### Xülasə

Köhnə mesajları xülasəyə sıxışdırın. Dəqiqliyi itirir, amma əsas məlumatı qoruyur.

```
M1..M10 → Xülasə: "İstifadəçi Q3 satışlarını analiz etmək istədi.
                    Agent sifarişlər cədvəlini sorğuladı, 15 min qeyd tapdı.
                    Gəlir $2.3M olub, Q2-yə nisbətən 12% artıb."

Cari kontekst: [Xülasə, M11, M12, M13]
```

### Token Büdcəsi İdarəetməsi

Hər LLM çağırışından əvvəl token sayını izləyin. Limitə yaxınlaşırsınızsa, proaktiv sıxışdırın.

```
if (token_count(messages) > max_context * 0.8) {
    compress_oldest_messages(messages, target_ratio: 0.5)
}
```

### Açar-Dəyər Yaddaşı

Xam mesaj tarixçəsi əvəzinə strukturlaşdırılmış "öyrənilmiş faktlar" anbarı saxlayın. Agent bu anbardan açıq şəkildə oxuyur və yazır.

```
{
    "user_goal": "Q3 2025 satışlarını bölgəyə görə analiz et",
    "schema_discovered": ["orders", "customers", "products"],
    "queries_run": 3,
    "key_findings": [
        "Şimal-Şərq bölgəsi: +18% İY",
        "Cənub-Qərb bölgəsi: -5% İY"
    ]
}
```

---

## Laravel İmplementasiyası

### 1. ConversationMemory — Sürüşən Pəncərə + Xülasə

```php
<?php

namespace App\AI\Memory;

use App\Models\AgentMessage;
use App\Models\AgentSession;
use Anthropic\Client;

class ConversationMemory
{
    private const MAX_TOKENS = 80_000;   // Cavab üçün yer saxlayın
    private const WINDOW_SIZE = 20;      // Sıxışdırmadan əvvəl maksimal mesaj sayı
    private const SUMMARY_TARGET = 10;  // Bu qədər mesaja sıxışdırın

    public function __construct(
        private readonly Client $claude,
    ) {}

    /**
     * LLM istehlakı üçün formatlanmış mesajları qaytarın.
     * Lazım olduqda sıxışdırma tətbiq edir.
     */
    public function getMessages(AgentSession $session): array
    {
        $messages = AgentMessage::where('session_id', $session->id)
            ->orderBy('created_at')
            ->get()
            ->map(fn($m) => ['role' => $m->role, 'content' => $m->content])
            ->toArray();

        // Çox mesaj varsa sürüşən pəncərə tətbiq edin
        if (count($messages) > self::WINDOW_SIZE) {
            return $this->compress($messages, $session);
        }

        return $messages;
    }

    /**
     * Köhnə mesajları xülasə edərək söhbəti sıxışdırın.
     */
    private function compress(array $messages, AgentSession $session): array
    {
        $splitPoint = count($messages) - self::SUMMARY_TARGET;

        $toSummarize = array_slice($messages, 0, $splitPoint);
        $toKeep = array_slice($messages, $splitPoint);

        $summary = $this->summarize($toSummarize, $session->goal);

        // Xülasəni başlanğıcda sistem səviyyəli mesaj kimi əlavə edin
        return array_merge(
            [
                [
                    'role' => 'user',
                    'content' => "[SÖHBƏT XÜLASƏSİ]\nAşağıdakılar əvvəlki söhbətin xülasəsidir:\n\n{$summary}\n\n[XÜLASƏNİN SONU]\nSöhbət aşağıda davam edir:",
                ],
                [
                    'role' => 'assistant',
                    'content' => 'Başa düşdüm. Əvvəlki söhbətin kontekstini əlimdə var, dayandığımız yerdən davam edəcəyəm.',
                ],
            ],
            $toKeep,
        );
    }

    private function summarize(array $messages, string $goal): string
    {
        $formattedMessages = collect($messages)->map(function ($m) {
            $role = strtoupper($m['role']);
            $content = is_string($m['content'])
                ? $m['content']
                : json_encode($m['content']);
            return "[{$role}]: " . substr($content, 0, 500);
        })->join("\n\n");

        $response = $this->claude->messages()->create([
            'model' => 'claude-haiku-4-5', // Sıxışdırma üçün ucuz model
            'max_tokens' => 1024,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => <<<PROMPT
                    Aşağıdakı agent söhbətini qısa xülasə edin.
                    Saxlayın: verilmiş əsas qərarlar, tapılan məlumatlar, qarşılaşılan xətalar və cari irəliləyiş.
                    Ümumi məqsəd belə idi: {$goal}

                    Söhbət:
                    {$formattedMessages}

                    200-400 sözdə sıx xülasə yazın.
                    PROMPT,
                ],
            ],
        ]);

        return $response->content[0]->text;
    }

    /**
     * Token sayını qiymətləndirin (təxmini: 1 token ≈ 4 simvol).
     */
    public function estimateTokens(array $messages): int
    {
        $text = collect($messages)->map(function ($m) {
            return is_string($m['content'])
                ? $m['content']
                : json_encode($m['content']);
        })->join('');

        return (int) (strlen($text) / 4);
    }
}
```

### 2. LongTermMemory — pgvector Vasitəsilə Semantik Axtarış

```php
<?php

namespace App\AI\Memory;

use Anthropic\Client;
use Illuminate\Support\Facades\DB;

class LongTermMemory
{
    private const EMBEDDING_MODEL = 'voyage-3'; // Anthropic-in embedding modeli
    private const MAX_RESULTS = 5;
    private const SIMILARITY_THRESHOLD = 0.7;

    public function __construct(
        private readonly Client $claude,
    ) {}

    /**
     * Vektor embeddingləri ilə yaddaşı saxlayın.
     */
    public function store(
        string $userId,
        string $content,
        string $type = 'general',
        array $metadata = [],
    ): int {
        $embedding = $this->embed($content);

        return DB::table('agent_memories')->insertGetId([
            'user_id'   => $userId,
            'type'      => $type,
            'content'   => $content,
            'embedding' => json_encode($embedding), // pgvector formatı: '[1,2,3,...]'
            'metadata'  => json_encode($metadata),
            'created_at' => now(),
        ]);
    }

    /**
     * Semantik cəhətdən bənzər yaddaşları geri alın.
     */
    public function retrieve(
        string $userId,
        string $query,
        int $limit = self::MAX_RESULTS,
    ): array {
        $queryEmbedding = $this->embed($query);
        $vectorString = '[' . implode(',', $queryEmbedding) . ']';

        // pgvector kosinus oxşarlıq axtarışı
        $results = DB::select(<<<SQL
            SELECT id, type, content, metadata,
                   1 - (embedding <=> ?::vector) AS similarity
            FROM agent_memories
            WHERE user_id = ?
              AND 1 - (embedding <=> ?::vector) > ?
            ORDER BY similarity DESC
            LIMIT ?
        SQL, [$vectorString, $userId, $vectorString, self::SIMILARITY_THRESHOLD, $limit]);

        return collect($results)
            ->map(fn($r) => [
                'id'         => $r->id,
                'type'       => $r->type,
                'content'    => $r->content,
                'metadata'   => json_decode($r->metadata, true),
                'similarity' => round($r->similarity, 3),
            ])
            ->toArray();
    }

    /**
     * Geri alınan yaddaşları kontekstə yerləşdirmək üçün formatlayın.
     */
    public function formatForContext(array $memories): string
    {
        if (empty($memories)) {
            return '';
        }

        $formatted = collect($memories)->map(function ($m) {
            $type = strtoupper($m['type']);
            return "[{$type}] {$m['content']} (uyğunluq: {$m['similarity']})";
        })->join("\n");

        return "Keçmiş qarşılıqlı əlaqələrdən uyğun yaddaşlar:\n{$formatted}";
    }

    public function forget(int $memoryId): void
    {
        DB::table('agent_memories')->where('id', $memoryId)->delete();
    }

    public function forgetAll(string $userId, string $type = null): int
    {
        $query = DB::table('agent_memories')->where('user_id', $userId);
        if ($type) $query->where('type', $type);
        return $query->delete();
    }

    private function embed(string $text): array
    {
        // Anthropic-in Voyage embeddingləri istifadə edilir
        $response = \Illuminate\Support\Facades\Http::withToken(config('services.anthropic.key'))
            ->post('https://api.voyageai.com/v1/embeddings', [
                'model' => self::EMBEDDING_MODEL,
                'input' => $text,
            ]);

        return $response->json('data.0.embedding');
    }
}
```

### agent_memories üçün Miqrasiya

```php
<?php
// database/migrations/xxxx_create_agent_memories_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // pgvector genişlənməsini aktivləşdirin
        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');

        Schema::create('agent_memories', function (Blueprint $table) {
            $table->id();
            $table->string('user_id');
            $table->string('type');
            $table->text('content');
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        // Vektor sütunu əlavə edin (voyage-3 üçün 1024 ölçü)
        DB::statement('ALTER TABLE agent_memories ADD COLUMN embedding vector(1024)');

        // Sürətli oxşarlıq axtarışı üçün HNSW indeksi yaradın
        DB::statement('CREATE INDEX agent_memories_embedding_idx ON agent_memories USING hnsw (embedding vector_cosine_ops)');

        // Filtrli axtarışlar üçün indeks
        DB::statement('CREATE INDEX agent_memories_user_type ON agent_memories(user_id, type)');
    }
};
```

### 3. EpisodicMemory — Keçmiş İşlərden Öyrənmək

```php
<?php

namespace App\AI\Memory;

use App\Models\AgentSession;
use Illuminate\Support\Facades\DB;

class EpisodicMemory
{
    /**
     * Gələcək istinad üçün epizodu (tamamlanmış agent işini) qeydə alın.
     */
    public function record(AgentSession $session): void
    {
        if ($session->status !== 'completed') {
            return;
        }

        $steps = $session->steps()->orderBy('iteration')->get();

        // Baş verənləri çıxarın
        $toolsUsed = $steps->where('type', 'tool_result')
            ->pluck('metadata')
            ->pluck('tool')
            ->unique()
            ->values()
            ->toArray();

        $errors = $steps->where('type', 'tool_result')
            ->filter(fn($s) => isset($s->metadata['success']) && !$s->metadata['success'])
            ->map(fn($s) => $s->content)
            ->take(3)
            ->toArray();

        DB::table('agent_episodes')->insert([
            'user_id'         => $session->user_id,
            'session_id'      => $session->id,
            'goal'            => $session->goal,
            'goal_type'       => $this->classifyGoal($session->goal),
            'tools_used'      => json_encode($toolsUsed),
            'iteration_count' => $session->iteration_count,
            'success'         => true,
            'errors'          => json_encode($errors),
            'outcome'         => json_encode($session->final_result),
            'duration_seconds'=> $session->started_at
                ? $session->started_at->diffInSeconds($session->completed_at)
                : null,
            'created_at'      => now(),
        ]);
    }

    /**
     * Yeni tapşırıq üçün müvafiq keçmiş epizodları xatırlayın.
     */
    public function recall(string $userId, string $goal, int $limit = 3): array
    {
        $goalType = $this->classifyGoal($goal);

        // Məqsəd növü və açar sözlərə görə bənzər keçmiş epizodları tapın
        $episodes = DB::table('agent_episodes')
            ->where('user_id', $userId)
            ->where('goal_type', $goalType)
            ->where('success', true)
            ->orderBy('created_at', 'desc')
            ->limit($limit * 3) // Daha çox götürün, ən müvafiqini süzün
            ->get();

        // Açar söz üst-üstə düşməsinə görə skor verin
        $goalWords = $this->tokenize($goal);

        return $episodes
            ->map(function ($ep) use ($goalWords) {
                $epWords = $this->tokenize($ep->goal);
                $overlap = count(array_intersect($goalWords, $epWords));
                $ep->relevance_score = $overlap / max(count($goalWords), 1);
                return $ep;
            })
            ->sortByDesc('relevance_score')
            ->take($limit)
            ->map(fn($ep) => [
                'goal'            => $ep->goal,
                'tools_used'      => json_decode($ep->tools_used),
                'iteration_count' => $ep->iteration_count,
                'errors'          => json_decode($ep->errors),
                'duration'        => $ep->duration_seconds,
            ])
            ->values()
            ->toArray();
    }

    public function formatForPrompt(array $episodes): string
    {
        if (empty($episodes)) return '';

        $items = collect($episodes)->map(function ($ep) {
            $tools = implode(', ', $ep['tools_used'] ?? []);
            $errors = !empty($ep['errors'])
                ? "\n  - Rastlaşılan xətalar: " . implode('; ', $ep['errors'])
                : '';
            return "- Məqsəd: \"{$ep['goal']}\"\n  Alətlər: {$tools}\n  Addımlar: {$ep['iteration_count']}{$errors}";
        })->join("\n");

        return "Əvvəllər tamamladığım bənzər tapşırıqlar:\n{$items}";
    }

    private function classifyGoal(string $goal): string
    {
        $goal = strtolower($goal);
        return match(true) {
            str_contains($goal, 'analy') || str_contains($goal, 'report') => 'analysis',
            str_contains($goal, 'search') || str_contains($goal, 'find')   => 'search',
            str_contains($goal, 'creat') || str_contains($goal, 'generat') => 'creation',
            str_contains($goal, 'summar')                                  => 'summarization',
            default                                                        => 'general',
        };
    }

    private function tokenize(string $text): array
    {
        return array_filter(
            explode(' ', strtolower(preg_replace('/[^a-z0-9 ]/i', '', $text))),
            fn($w) => strlen($w) > 3,
        );
    }
}
```

### 4. MemoryManager — Bütün Növləri Orkestrlaşdırmaq

```php
<?php

namespace App\AI\Memory;

use App\Models\AgentSession;

class MemoryManager
{
    public function __construct(
        private readonly ConversationMemory $conversation,
        private readonly LongTermMemory     $longTerm,
        private readonly EpisodicMemory     $episodic,
    ) {}

    /**
     * Agent çağırışı üçün tam yaddaş kontekstini qurun.
     * Həm mesajlar massivi, həm də zənginləşdirilmiş sistem promptu əlavələri qaytarır.
     */
    public function buildContext(AgentSession $session): MemoryContext
    {
        // 1. (Sıxışdırılmış) söhbət tarixçəsini alın
        $messages = $this->conversation->getMessages($session);

        // 2. Semantik cəhətdən müvafiq uzunmüddətli yaddaşları geri alın
        $ltmResults = $this->longTerm->retrieve(
            userId: (string) $session->user_id,
            query: $session->goal,
        );

        // 3. Müvafiq keçmiş epizodları xatırlayın
        $episodes = $this->episodic->recall(
            userId: (string) $session->user_id,
            goal: $session->goal,
        );

        // 4. Sistem promptu üçün kontekst əlavələri qurun
        $contextAdditions = array_filter([
            $this->longTerm->formatForContext($ltmResults),
            $this->episodic->formatForPrompt($episodes),
        ]);

        return new MemoryContext(
            messages: $messages,
            systemAdditions: implode("\n\n", $contextAdditions),
            tokenEstimate: $this->conversation->estimateTokens($messages),
        );
    }

    /**
     * Sessiya tamamlandıqdan sonra öyrənmələri çıxarın və saxlayın.
     */
    public function consolidate(AgentSession $session): void
    {
        // Bu işi epizod kimi qeydə alın
        $this->episodic->record($session);

        // Uzunmüddətli yaddaş kimi saxlamaq üçün yeni faktlar çıxarın
        $this->extractAndStoreFacts($session);
    }

    private function extractAndStoreFacts(AgentSession $session): void
    {
        if (empty($session->final_result)) {
            return;
        }

        $summary = $session->final_result['summary'] ?? '';
        if (empty($summary)) return;

        // Nəticəni uzunmüddətli yaddaş kimi saxlayın
        $this->longTerm->store(
            userId: (string) $session->user_id,
            content: "Tapşırıq: {$session->goal}\nNəticə: " . substr($summary, 0, 500),
            type: 'task_outcome',
            metadata: [
                'session_id'      => $session->id,
                'completed_at'    => $session->completed_at?->toISOString(),
                'iteration_count' => $session->iteration_count,
            ],
        );
    }

    /**
     * Agent işi zamanı tapılan istifadəçi seçimini saxlayın.
     */
    public function rememberPreference(string $userId, string $preference): void
    {
        $this->longTerm->store(
            userId: $userId,
            content: $preference,
            type: 'user_preference',
        );
    }
}

final class MemoryContext
{
    public function __construct(
        public readonly array  $messages,
        public readonly string $systemAdditions,
        public readonly int    $tokenEstimate,
    ) {}
}
```

### Service Provider

```php
<?php

namespace App\Providers;

use App\AI\Memory\ConversationMemory;
use App\AI\Memory\EpisodicMemory;
use App\AI\Memory\LongTermMemory;
use App\AI\Memory\MemoryManager;
use Illuminate\Support\ServiceProvider;

class MemoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ConversationMemory::class);
        $this->app->singleton(LongTermMemory::class);
        $this->app->singleton(EpisodicMemory::class);

        $this->app->singleton(MemoryManager::class, function ($app) {
            return new MemoryManager(
                conversation: $app->make(ConversationMemory::class),
                longTerm:     $app->make(LongTermMemory::class),
                episodic:     $app->make(EpisodicMemory::class),
            );
        });
    }
}
```

---

## Yaddaş Memarlığı Qərar Bələdçisi

| Yaddaş Növü | Nə zaman istifadə edilir | Saxlama | Geri Alma |
|---|---|---|---|
| Kontekstdaxili | Cari tapşırıq məlumatı, son tarixçə | Kontekst pəncərəsi | Avtomatik (oradır) |
| Uzunmüddətli (açar söz) | Strukturlaşdırılmış faktlar, seçimlər | SQL | WHERE sorğuları |
| Uzunmüddətli (semantik) | Struktursuz bilik | pgvector | Oxşarlıq axtarışı |
| Epizodik | Keçmiş iş patternləri, nə işə yaradı | SQL | Açar söz + növ filtri |

---

## Unutmaq və Yaddaş Gigiyenası

**Niyə unutmaq lazımdır?** Köhnəlmiş ya da yanlış yaddaşlar agent performansını pisləşdirir. 6 ay əvvəl doğru olan yaddaş bu gün çaşdıra bilər.

```php
class MemoryHygiene
{
    public function runMaintenance(string $userId): void
    {
        // 90 gündən köhnə yaddaşları silin (növə görə konfiqurasiya edilə bilər)
        DB::table('agent_memories')
            ->where('user_id', $userId)
            ->where('type', 'task_outcome')
            ->where('created_at', '<', now()->subDays(90))
            ->delete();

        // Seçimləri daha uzun müddət saxlayın
        // İstifadəçi seçimləri: 1 il
        DB::table('agent_memories')
            ->where('user_id', $userId)
            ->where('type', 'user_preference')
            ->where('created_at', '<', now()->subYear())
            ->delete();

        // Dublikat/çox bənzər epizodları silin
        $this->deduplicateEpisodes($userId);
    }

    private function deduplicateEpisodes(string $userId): void
    {
        // Hər 7 günlük pəncərədə məqsəd növü başına yalnız ən son epizodu saxlayın
        DB::statement(<<<SQL
            DELETE FROM agent_episodes
            WHERE user_id = ?
              AND id NOT IN (
                SELECT MAX(id)
                FROM agent_episodes
                WHERE user_id = ?
                GROUP BY goal_type, DATE(FLOOR(UNIX_TIMESTAMP(created_at) / 604800) * 604800)
              )
        SQL, [$userId, $userId]);
    }
}
```

---

## Nəyi Yadda Saxlamaq Lazımdır: Qərar Çərçivəsi

Hər şey yaddaşa layiq deyil. Çox şey saxlamaq geri alma keyfiyyətini aşağı salır.

**Saxlayın**:
- İstifadəçi tərəfindən açıqca ifadə edilmiş seçimlər ("Siyahı nöqtələrini üstün tuturam")
- Əvvəlki çıxışlara düzəlişlər ("Həmin analiz yanlış idi, çünki...")
- Tapşırıqlar zamanı aşkar edilmiş sahəyə xas faktlar
- Uğurlu strategiyalar ("Tarix bölmələndirilməsi sorğunu sürətləndirdi")
- Xətalar və onların həlləri

**Saxlamayın**:
- Aralıq hesablama nəticələri
- Xam alət çıxışları (çox böyük, az siqnal)
- Şablon söhbət mübadiləsi
- Məxfi ya da həssas məlumatlar (ŞHM, parollar)
- Hipotetik ssenarilər ("Nə olardı ki...")

Qayda: **insan mütəxəssisi gələcəkdə istinad üçün bunu qeyd edərmi?** Edərsə, saxlayın. Etməzsə, atın.
