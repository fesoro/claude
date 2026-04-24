# 39 — AI Xərclərinin Optimallaşdırılması: Praktiki Strategiyalar

> **Oxucu:** Baş tərtibatçılar və arxitektlər  
> **Məqsəd:** Keyfiyyətdən güzəştə getmədən AI API xərclərini 60–90% azaltmaq

---

## 1. Xərc Problemi

AI xərcləri super-xətti artır. İstifadəçiləri 10x artırdığınızda AI xərclərinizdən tez-tez 50x artır, çünki:
- Daha çox söhbət → daha çox kontekst → daha çox giriş tokeni
- Daha çox sənəd → daha çox embedding → embedding xərcləri
- Daha çox xüsusiyyət → istifadəçi əməliyyatı başına daha çox AI çağırışı
- Düşüncəsiz retry məntiqi → faktiki istifadənin 2–5x-i

**Real rəqəmlər (Aprel 2025 qiymətləndirməsi 1M token başına):**

| Model               | Giriş    | Çıxış    | Qeydlər                              |
|---------------------|----------|----------|--------------------------------------|
| claude-opus-4-5     | $15.00   | $75.00   | Ən yaxşı keyfiyyət; bahalı           |
| claude-sonnet-4-5   | $3.00    | $15.00   | Mükəmməl keyfiyyət; balanslaşdırılmış|
| claude-haiku-4-5    | $0.25    | $1.25    | Sürətli; Opus-dan 60x ucuz           |
| Keşlənmiş giriş     | $0.30    | —        | Prompt keşi ilə Sonnet               |
| Ollama (yerli)      | $0.00    | $0.00    | Yalnız avadanlıq xərci               |

**100k sorğu/gün üçün nümunə xərc:**

| Ssenariy                    | Gündəlik Xərc | Aylıq Xərc  |
|-----------------------------|---------------|-------------|
| Hamı Opus, 2k token ort.    | $4,500        | $135,000    |
| Hamı Sonnet, 2k token ort.  | $900          | $27,000     |
| Yönləndirilmiş (Haiku 70%, Sonnet 25%, Opus 5%) | $220 | $6,600 |
| Yönləndirilmiş + keşləmə (60% keş dəqiqliyi)   | $88  | $2,640 |

**Yönləndirmə + keşləmə = Opus-u naiv şəkildə istifadəyə nisbətən ~96% xərc azalması.**

---

## 2. Göndərməzdən Əvvəl Token Sayımı

Heç vaxt təxmini token sayını bilmədən sorğu göndərməyin. Anthropic-in tiktoken-uyğun sayıcısından istifadə edin:

```php
<?php
// app/Services/AI/TokenCounter.php

namespace App\Services\AI;

class TokenCounter
{
    /**
     * API çağırmadan token sayını təxmin edin.
     * Başlanğıc nöqtəsi: İngilis mətni üçün ~4 simvol/token.
     * Daha dəqiq: tokenizer kitabxanasından istifadə edin.
     */
    public function estimate(string $text): int
    {
        // Kobud təxmin: 1 token ≈ 4 simvol
        return (int) ceil(strlen($text) / 4);
    }

    public function estimateMessages(array $messages): int
    {
        $total = 0;

        foreach ($messages as $message) {
            $total += 4; // Mesaj başına overhead
            $total += $this->estimate($message['content'] ?? '');
        }

        return $total + 3; // Söhbət overhead-i
    }

    /**
     * Sorğunun model kontekst pəncərəsinə sığıb sığmayacağını yoxlayın.
     * API sorğusundan ƏVVƏL çağırın.
     */
    public function validateRequest(
        array  $messages,
        int    $requestedMaxTokens,
        string $model = 'claude-sonnet-4-5',
    ): ValidationResult {
        $contextWindows = [
            'claude-opus-4-5'   => 200_000,
            'claude-sonnet-4-5' => 200_000,
            'claude-haiku-4-5'  => 200_000,
        ];

        $window     = $contextWindows[$model] ?? 200_000;
        $inputTokens = $this->estimateMessages($messages);
        $totalTokens = $inputTokens + $requestedMaxTokens;

        if ($totalTokens > $window) {
            return new ValidationResult(
                valid: false,
                reason: "Təxmini {$totalTokens} token {$window} kontekst pəncərəsini aşır",
                inputTokens: $inputTokens,
                willExceedWindow: true,
            );
        }

        return new ValidationResult(
            valid: true,
            inputTokens: $inputTokens,
        );
    }
}
```

---

## 3. Prompt Sıxışdırması

Uzun promptlar bahalıdır. Göndərməzdən əvvəl onları sıxışdırın:

```php
<?php
// app/Services/AI/PromptCompressor.php

namespace App\Services\AI;

class PromptCompressor
{
    /**
     * Promptlardan lazımsız boşluq və formatlamağı çıxarın.
     * Geniş promptlar üçün 10–15% token qənaəti edir.
     */
    public function compress(string $prompt): string
    {
        // Lazımsız boş sətirləri çıxar
        $prompt = preg_replace('/\n{3,}/', "\n\n", $prompt);

        // Sətir başına sona gələn boşluqları çıxar
        $prompt = preg_replace('/[ \t]+$/m', '', $prompt);

        // Çoxlu boşluqları birləşdir
        $prompt = preg_replace('/ {2,}/', ' ', $prompt);

        return trim($prompt);
    }

    /**
     * Büdcəyə sığmaq üçün sənəd məzmununu kəsin.
     * Başlanğıc və sonu qoruyur (ən vacib hissələr).
     */
    public function truncateDocument(string $content, int $maxTokens, TokenCounter $counter): string
    {
        $currentTokens = $counter->estimate($content);

        if ($currentTokens <= $maxTokens) {
            return $content;
        }

        // Büdcənin ilk 60%-ni və son 40%-ni saxla
        $headTokens = (int) ($maxTokens * 0.6);
        $tailTokens = $maxTokens - $headTokens;

        $headChars = $headTokens * 4;
        $tailChars = $tailTokens * 4;

        $head = substr($content, 0, $headChars);
        $tail = substr($content, -$tailChars);

        // Söz sərhədlərinə uyğunlaş
        $head = substr($head, 0, strrpos($head, ' '));
        $tail = substr($tail, strpos($tail, ' ') + 1);

        return $head . "\n\n[... məzmun qısaldılıb ...]\n\n" . $tail;
    }

    /**
     * Uzun söhbətlərdə token azaltmaq üçün söhbət tarixini xülasə edin.
     * Köhnə mesajları xülasə ilə əvəz edin.
     */
    public function summarizeHistory(array $messages, ClaudeService $claude, int $keepRecentN = 6): array
    {
        if (count($messages) <= $keepRecentN) {
            return $messages;
        }

        $toSummarize = array_slice($messages, 0, -$keepRecentN);
        $toKeep      = array_slice($messages, -$keepRecentN);

        $historyText = collect($toSummarize)
            ->map(fn($m) => "{$m['role']}: {$m['content']}")
            ->implode("\n");

        $summary = $claude->complete(
            model: 'claude-haiku-4-5',
            prompt: "Bu söhbət tarixini əsas faktları qoruyaraq qısaca xülasə edin:\n\n{$historyText}",
            maxTokens: 500,
        );

        return [
            ['role' => 'user',      'content' => "[Söhbət tarixi xülasəsi: {$summary}]"],
            ['role' => 'assistant', 'content' => "Başa düşdüm. Bu konteksti yadda saxlayacağam."],
            ...$toKeep,
        ];
    }
}
```

---

## 4. Embedding-lərlə Semantik Keş

Cavab keşləmə əsas tələbdir. Semantik keşləmə növbəti səviyyədir — *semantik baxımdan oxşar* sorğular üçün cavabları keşdə saxlayın:

```php
<?php
// app/Services/AI/SemanticCache.php

namespace App\Services\AI;

use App\Models\SemanticCacheEntry;
use Illuminate\Support\Facades\Cache;

class SemanticCache
{
    public function __construct(
        private readonly EmbeddingService $embeddings,
        private readonly float $similarityThreshold = 0.95,
        private readonly int   $ttlSeconds = 3600,
    ) {}

    /**
     * Semantik baxımdan oxşar keşlənmiş cavabı axtarın.
     *
     * Alqoritm:
     * 1. Sorğunu embed edin
     * 2. Kosinus oxşarlığından istifadə edərək ən yaxın keşlənmiş sorğunu tapın
     * 3. Oxşarlıq > hədd olarsa keşlənmiş cavabı qaytarın
     */
    public function get(string $query, string $context = ''): ?string
    {
        $queryEmbedding = $this->embeddings->embed($query);

        // pgvector kosinus oxşarlığı axtarışından istifadə et
        $hit = SemanticCacheEntry::query()
            ->where('context_hash', md5($context))
            ->where('expires_at', '>', now())
            ->selectRaw('*, 1 - (embedding <=> ?) as similarity', [json_encode($queryEmbedding)])
            ->having('similarity', '>=', $this->similarityThreshold)
            ->orderByDesc('similarity')
            ->first();

        if ($hit) {
            $hit->increment('hit_count');
            $hit->update(['last_hit_at' => now()]);

            logger()->info('Semantik keş dəqiqliyi', [
                'similarity' => $hit->similarity,
                'query'      => substr($query, 0, 100),
            ]);

            return $hit->response;
        }

        return null;
    }

    /**
     * Semantik keşdə cavab saxlayın.
     */
    public function put(string $query, string $response, string $context = ''): void
    {
        $embedding = $this->embeddings->embed($query);

        SemanticCacheEntry::create([
            'query'        => $query,
            'response'     => $response,
            'embedding'    => $embedding,
            'context_hash' => md5($context),
            'query_hash'   => md5($query),
            'expires_at'   => now()->addSeconds($this->ttlSeconds),
        ]);
    }

    /**
     * AI çağırışını semantik keşləmə ilə sarın.
     */
    public function remember(string $query, string $context, \Closure $callback): string
    {
        if ($cached = $this->get($query, $context)) {
            return $cached;
        }

        $response = $callback();
        $this->put($query, $response, $context);

        return $response;
    }
}
```

```sql
-- Semantik keş üçün miqrasiya
CREATE TABLE semantic_cache_entries (
    id           BIGSERIAL PRIMARY KEY,
    query        TEXT NOT NULL,
    response     TEXT NOT NULL,
    embedding    vector(1536) NOT NULL,
    context_hash VARCHAR(32),
    query_hash   VARCHAR(32),
    hit_count    INT DEFAULT 0,
    last_hit_at  TIMESTAMPTZ,
    expires_at   TIMESTAMPTZ NOT NULL,
    created_at   TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX semantic_cache_embedding_idx 
ON semantic_cache_entries USING hnsw (embedding vector_cosine_ops);

CREATE INDEX semantic_cache_expires_idx ON semantic_cache_entries(expires_at);
```

---

## 5. Model Router: Tapşırıq Mürəkkəbliyinə Görə Ən Ucuz Modeli Seçin

```php
<?php
// app/Services/AI/ModelRouter.php

namespace App\Services\AI;

class ModelRouter
{
    /**
     * Modellərə uyğun mürəkkəblik siqnalları.
     * Tapşırığı idarə edə bilən ən ucuz model qalib gəlir.
     */
    private array $tiers = [
        'haiku'  => 'claude-haiku-4-5',
        'sonnet' => 'claude-sonnet-4-5',
        'opus'   => 'claude-opus-4-5',
    ];

    /**
     * Tapşırıq növü və giriş xüsusiyyətlərinə əsasən yönləndir.
     * Yalnız bu xərcləri 60–80% azalda bilər.
     */
    public function route(string $task, string $input, array $hints = []): string
    {
        $tier = $this->determineTier($task, $input, $hints);
        return $this->tiers[$tier];
    }

    private function determineTier(string $task, string $input, array $hints): string
    {
        // Açıq yenidən yazma
        if (isset($hints['model'])) {
            return $this->mapToTier($hints['model']);
        }

        // Tapşırıq tipi siqnalları
        $alwaysHaiku = [
            'classify', 'sentiment', 'extract-entities', 'yes-no',
            'route', 'keyword-extract', 'format-conversion',
        ];

        $alwaysSonnet = [
            'summarize', 'translate', 'qa', 'code-review',
            'structured-extraction', 'rewrite',
        ];

        $alwaysOpus = [
            'complex-reasoning', 'research', 'architecture-review',
            'legal-analysis', 'medical',
        ];

        if (in_array($task, $alwaysHaiku))  return 'haiku';
        if (in_array($task, $alwaysOpus))   return 'opus';
        if (in_array($task, $alwaysSonnet)) return 'sonnet';

        // Naməlum tapşırıqlar üçün mürəkkəblik balı
        $score = $this->complexityScore($input, $hints);

        return match (true) {
            $score < 3  => 'haiku',
            $score < 7  => 'sonnet',
            default     => 'opus',
        };
    }

    private function complexityScore(string $input, array $hints): int
    {
        $score = 0;

        // Giriş uzunluğu
        $words = str_word_count($input);
        $score += match (true) {
            $words > 5000 => 3,
            $words > 1000 => 2,
            $words > 200  => 1,
            default       => 0,
        };

        // Promptda mürəkkəblik göstəriciləri
        $complexKeywords = ['analyze', 'compare', 'evaluate', 'critique', 'synthesize', 'reason', 'explain why'];
        foreach ($complexKeywords as $kw) {
            if (str_contains(strtolower($input), $kw)) $score++;
        }

        // Tələb olunan çıxış format mürəkkəbliyi
        if ($hints['requires_json'] ?? false) $score++;
        if ($hints['requires_citations'] ?? false) $score += 2;
        if ($hints['multi_step'] ?? false) $score += 2;
        if ($hints['requires_code'] ?? false) $score++;

        return $score;
    }

    private function mapToTier(string $model): string
    {
        return match (true) {
            str_contains($model, 'haiku') => 'haiku',
            str_contains($model, 'opus')  => 'opus',
            default                       => 'sonnet',
        };
    }
}
```

---

## 6. TokenBudget Middleware-i

Runaway xərclərinin qarşısını almaq üçün istifadəçi və kiracı başına token limitlərini tətbiq edin:

```php
<?php
// app/Http/Middleware/TokenBudgetMiddleware.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TokenBudgetMiddleware
{
    /**
     * AI sorğularını emal etməzdən əvvəl token büdcələrini tətbiq edin.
     * Redis-də istifadəni izləyir, hər 5 dəqiqədə DB-yə sinxronlaşdırır.
     */
    public function handle(Request $request, Closure $next, string $scope = 'user'): mixed
    {
        $subject = $scope === 'tenant'
            ? auth()->user()?->tenant_id
            : auth()->id();

        if (! $subject) {
            return $next($request);
        }

        $limits = $this->getLimits($subject, $scope);
        $usage  = $this->getCurrentUsage($subject, $scope);

        // Gündəlik limiti yoxla
        if ($usage['daily'] >= $limits['daily']) {
            return response()->json([
                'error'       => 'daily_token_limit_exceeded',
                'message'     => 'Gündəlik AI istifadə limitinizə çatdınız.',
                'limit'       => $limits['daily'],
                'used'        => $usage['daily'],
                'resets_at'   => now()->endOfDay()->toIso8601String(),
            ], 429);
        }

        // Aylıq limiti yoxla
        if ($usage['monthly'] >= $limits['monthly']) {
            return response()->json([
                'error'     => 'monthly_token_limit_exceeded',
                'message'   => 'Aylıq AI istifadə limitinizə çatdınız.',
                'limit'     => $limits['monthly'],
                'used'      => $usage['monthly'],
                'resets_at' => now()->endOfMonth()->toIso8601String(),
            ], 429);
        }

        // AI xidmətlərinin istifadəsi üçün büdcə məlumatını sorğuya əlavə edin
        $request->attributes->set('token_budget', [
            'remaining_daily'   => $limits['daily'] - $usage['daily'],
            'remaining_monthly' => $limits['monthly'] - $usage['monthly'],
        ]);

        $response = $next($request);

        // Cavabdan sonra istifadəni yeniləyin (istifadə olunan tokenlər cavab başlıqlarında və ya gövdəsindədir)
        if ($tokensUsed = $request->attributes->get('tokens_used')) {
            $this->incrementUsage($subject, $scope, $tokensUsed);
        }

        return $response;
    }

    private function getLimits(int $subject, string $scope): array
    {
        return Cache::remember("token_limits:{$scope}:{$subject}", 3600, function () use ($subject, $scope) {
            if ($scope === 'tenant') {
                $config = DB::table('tenant_ai_configs')->where('tenant_id', $subject)->first();
                return [
                    'daily'   => $config->daily_token_limit   ?? 1_000_000,
                    'monthly' => $config->monthly_token_limit ?? 20_000_000,
                ];
            }

            $plan = DB::table('users')
                ->join('plans', 'users.plan_id', '=', 'plans.id')
                ->where('users.id', $subject)
                ->value('plans.daily_token_limit');

            return [
                'daily'   => $plan ?? 100_000,
                'monthly' => ($plan ?? 100_000) * 25,
            ];
        });
    }

    private function getCurrentUsage(int $subject, string $scope): array
    {
        return [
            'daily'   => (int) Cache::get("tokens:{$scope}:{$subject}:daily:" . now()->format('Ymd'), 0),
            'monthly' => (int) Cache::get("tokens:{$scope}:{$subject}:monthly:" . now()->format('Ym'), 0),
        ];
    }

    private function incrementUsage(int $subject, string $scope, int $tokens): void
    {
        $dailyKey   = "tokens:{$scope}:{$subject}:daily:" . now()->format('Ymd');
        $monthlyKey = "tokens:{$scope}:{$subject}:monthly:" . now()->format('Ym');

        Cache::increment($dailyKey, $tokens);
        Cache::increment($monthlyKey, $tokens);

        // İlk artımda müddət qurun
        if (Cache::get($dailyKey) === $tokens) {
            Cache::expire($dailyKey, 86400 * 2);    // 2 günlük müddət
            Cache::expire($monthlyKey, 86400 * 35); // 35 günlük müddət
        }
    }
}
```

---

## 7. Embedding Xərcləri üçün Toplu Emal

Embedding-lər bir-bir çağırıldığında bahalıdır. Həmişə toplu emal edin:

```php
<?php
// app/Services/AI/EmbeddingService.php

namespace App\Services\AI;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class EmbeddingService
{
    private const BATCH_SIZE = 100;
    private const MODEL = 'text-embedding-3-small'; // $0.02/1M token

    /**
     * Çoxlu mətnləri tək API çağırışında embed edin.
     * Overhead azalması sayəsində fərdi çağırışlardan 10x ucuz ola bilər.
     */
    public function embedBatch(array $texts): array
    {
        $results    = [];
        $toFetch    = [];

        // Əvvəlcə keşi yoxla
        foreach ($texts as $i => $text) {
            $cacheKey = 'embed:' . md5($text);
            $cached   = Cache::get($cacheKey);

            if ($cached !== null) {
                $results[$i] = $cached;
            } else {
                $toFetch[$i] = $text;
            }
        }

        // Keşlənməmiş embedding-ləri toplu olaraq al
        foreach (array_chunk($toFetch, self::BATCH_SIZE, true) as $batch) {
            $response = Http::withToken(config('services.openai.key'))
                ->post('https://api.openai.com/v1/embeddings', [
                    'model' => self::MODEL,
                    'input' => array_values($batch),
                ]);

            $embeddings = $response->json('data');

            foreach (array_keys($batch) as $idx => $originalIndex) {
                $embedding = $embeddings[$idx]['embedding'];
                $cacheKey  = 'embed:' . md5($batch[$originalIndex]);

                // 7 gün keşdə saxla — embedding-lər dəyişmir
                Cache::put($cacheKey, $embedding, now()->addDays(7));

                $results[$originalIndex] = $embedding;
            }
        }

        ksort($results);
        return $results;
    }

    public function embed(string $text): array
    {
        return $this->embedBatch([$text])[0];
    }
}
```

---

## 8. Xərc Dashboard API-si

```php
<?php
// app/Http/Controllers/Admin/AICostController.php

namespace App\Http\Controllers\Admin;

use Illuminate\Support\Facades\DB;

class AICostController extends Controller
{
    public function summary(): \Illuminate\Http\JsonResponse
    {
        $costs = DB::table('ai_usage_logs')
            ->selectRaw(
                'model,
                 COUNT(*) as requests,
                 SUM(input_tokens) as total_input_tokens,
                 SUM(output_tokens) as total_output_tokens,
                 SUM(cost_usd) as total_cost_usd,
                 AVG(cost_usd) as avg_cost_per_request,
                 DATE(created_at) as date'
            )
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('model', 'date')
            ->orderByDesc('date')
            ->get();

        $cacheStats = DB::table('semantic_cache_entries')
            ->selectRaw('COUNT(*) as total_entries, SUM(hit_count) as total_hits')
            ->first();

        return response()->json([
            'costs_by_model' => $costs,
            'cache_stats'    => $cacheStats,
            'total_30d'      => $costs->sum('total_cost_usd'),
        ]);
    }
}
```

---

## 9. Xərc Optimallaşdırma Yoxlama Siyahısı

| Strategiya                 | Tipik Qənaət  | Tətbiq Əziyyəti      |
|----------------------------|---------------|----------------------|
| Model yönləndirməsi        | 60–80%        | Orta                 |
| Prompt keşi (Anthropic)    | Girişdə 40–60%| Aşağı                |
| Semantik cavab keşi        | 30–60%        | Orta                 |
| Prompt sıxışdırması        | 10–20%        | Aşağı                |
| Embedding toplu emal       | Emb-də 50–80% | Aşağı                |
| Token büdcələri            | Runaway-ın qarşısını alır| Aşağı     |
| Söhbət xülasəsi            | 20–40%        | Orta                 |
| Yerli modellər (Ollama)    | Sadə tapş. üçün 95%+| Yüksək (infrastr.)|

**Prioritet sırası:** Model yönləndirməsi → Keşləmə → Prompt sıxışdırması → Toplu emal → Yerli modellər
