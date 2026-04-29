# Laravel üçün Avtomatlaşdırılmış AI Kod İcmal Sistemi (Senior)

Pull request-ləri Claude ilə analiz edən və strukturlaşdırılmış şərhlər yerləşdirən tam GitHub inteqrasiyalı kod icmal botu.

---

## Arxitektura Baxışı

```
GitHub PR Açıldı/Yeniləndi
        │  webhook POST
        ▼
WebhookController
  ├── İmzanı yoxla (HMAC-SHA256)
  └── ReviewPullRequestJob-u növbəyə al
              │  növbəyə alındı
              ▼
    ReviewPullRequestJob
      ├── GitHub API-dən diff-i əldə et
      ├── Filtrələ: yalnız PHP/Blade/konfiqurasiya faylları
      ├── DiffParser: fayl səviyyəli parçalara böl
      ├── Hər fayl parçası üçün:
      │     └── ReviewFileJob (paralel)
      │           └── Claude: xəta/təhlükəsizlik/performans/stil icmalı
      └── Toplayan:
            ├── GitHub Review API vasitəsilə inline şərhləri yerləşdir
            └── PR xülasə şərhini yerləşdir
```

---

## Verilənlər Bazası Miqrasiyası

```php
// database/migrations/2024_01_01_create_code_reviews_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('code_reviews', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('repository');          // "owner/repo"
            $table->unsignedInteger('pr_number');
            $table->string('pr_title');
            $table->string('head_sha');            // İcmal edilən commit SHA
            $table->string('status')->default('pending'); // pending, processing, completed, failed
            $table->unsignedSmallInteger('files_reviewed')->default(0);
            $table->unsignedSmallInteger('comments_posted')->default(0);
            $table->unsignedSmallInteger('issues_found')->default(0); // xətalar + təhlükəsizlik
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->string('github_review_id')->nullable(); // Xülasə üçün GitHub icmal ID-si
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['repository', 'pr_number']);
        });

        Schema::create('code_review_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('code_review_id')->constrained()->cascadeOnDelete();
            $table->string('file_path');
            $table->unsignedInteger('line_number')->nullable(); // null = fayl səviyyəli şərh
            $table->string('severity'); // critical, high, medium, low, info
            $table->string('category'); // bug, security, performance, style, best_practice
            $table->text('comment');
            $table->string('suggestion')->nullable(); // Xüsusi kod düzəliş təklifi
            $table->string('github_comment_id')->nullable();
            $table->timestamps();

            $table->index(['code_review_id', 'severity']);
        });
    }
};
```

---

## Modellər

```php
// app/Models/CodeReview.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CodeReview extends Model
{
    protected $fillable = [
        'ulid', 'repository', 'pr_number', 'pr_title', 'head_sha',
        'status', 'files_reviewed', 'comments_posted', 'issues_found',
        'input_tokens', 'output_tokens', 'github_review_id', 'metadata',
    ];

    protected $casts = ['metadata' => 'array'];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn($m) => $m->ulid ??= Str::ulid());
    }

    public function comments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CodeReviewComment::class);
    }
}
```

```php
// app/Models/CodeReviewComment.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CodeReviewComment extends Model
{
    protected $fillable = [
        'code_review_id', 'file_path', 'line_number', 'severity',
        'category', 'comment', 'suggestion', 'github_comment_id',
    ];

    public function review(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(CodeReview::class);
    }

    public function isCritical(): bool
    {
        return in_array($this->severity, ['critical', 'high']);
    }
}
```

---

## GitHub Xidməti

```php
// app/Services/CodeReview/GitHubService.php
<?php

namespace App\Services\CodeReview;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class GitHubService
{
    private PendingRequest $client;

    public function __construct()
    {
        $this->client = Http::withToken(config('services.github.token'))
            ->baseUrl('https://api.github.com')
            ->withHeaders([
                'Accept' => 'application/vnd.github.v3+json',
                'X-GitHub-Api-Version' => '2022-11-28',
            ])
            ->timeout(30);
    }

    /**
     * Pull request üçün tam diff əldə et.
     * Xam unified diff formatı qaytarır.
     */
    public function getPullRequestDiff(string $repo, int $prNumber): string
    {
        return Http::withToken(config('services.github.token'))
            ->withHeaders(['Accept' => 'application/vnd.github.v3.diff'])
            ->get("https://api.github.com/repos/{$repo}/pulls/{$prNumber}")
            ->throw()
            ->body();
    }

    /**
     * PR metadata-sını əldə et (başlıq, müəllif, əsas branch, və s.)
     */
    public function getPullRequest(string $repo, int $prNumber): array
    {
        return $this->client
            ->get("/repos/{$repo}/pulls/{$prNumber}")
            ->throw()
            ->json();
    }

    /**
     * Inline şərhlərlə GitHub icmalı yarat.
     * Bu üstünlük verilən üsuldur — bütün şərhləri bir icmalda toplayır.
     *
     * @param array $comments Hər biri: ['path' => string, 'line' => int, 'body' => string, 'side' => 'RIGHT']
     */
    public function createReview(
        string $repo,
        int $prNumber,
        string $commitSha,
        string $body,
        array $comments = [],
        string $event = 'COMMENT', // APPROVE, REQUEST_CHANGES, COMMENT
    ): array {
        $payload = [
            'commit_id' => $commitSha,
            'body' => $body,
            'event' => $event,
            'comments' => $comments,
        ];

        return $this->client
            ->post("/repos/{$repo}/pulls/{$prNumber}/reviews", $payload)
            ->throw()
            ->json();
    }

    /**
     * PR-da tək şərh yerləşdir (inline deyil — sadəcə söhbətdə).
     */
    public function createPrComment(string $repo, int $prNumber, string $body): array
    {
        return $this->client
            ->post("/repos/{$repo}/issues/{$prNumber}/comments", ['body' => $body])
            ->throw()
            ->json();
    }

    /**
     * PR-da dəyişdirilmiş faylları əldə et.
     */
    public function getPullRequestFiles(string $repo, int $prNumber): array
    {
        return $this->client
            ->get("/repos/{$repo}/pulls/{$prNumber}/files", ['per_page' => 100])
            ->throw()
            ->json();
    }
}
```

---

## Diff Analiz Edici

```php
// app/Services/CodeReview/DiffParser.php
<?php

namespace App\Services\CodeReview;

/**
 * Unified diff formatını fayl səviyyəli parçalara analiz edir.
 *
 * Unified diff belə görünür:
 *   diff --git a/app/Foo.php b/app/Foo.php
 *   index abc123..def456 100644
 *   --- a/app/Foo.php
 *   +++ b/app/Foo.php
 *   @@ -10,7 +10,9 @@
 *    kontekst sətri
 *   -silinen sətir
 *   +əlavə edilmiş sətir
 */
class DiffParser
{
    // İcmal ediləcək fayllar (digərləri nəzərə alınmır)
    private array $includedExtensions = ['php', 'blade.php', 'json'];

    // Həmişə keçilən fayllar
    private array $excludedPatterns = [
        '/vendor/',
        '/node_modules/',
        '/_ide_helper',
        '/\.min\.php/',
        'composer.lock',
        'package-lock.json',
    ];

    /**
     * Unified diff-i fayl parçalarına analiz et.
     *
     * @return array<array{
     *   file_path: string,
     *   old_path: string,
     *   status: string,     // added, modified, deleted, renamed
     *   diff: string,       // Bu fayl üçün xam diff
     *   additions: int,
     *   deletions: int,
     *   hunks: array,       // Sətir nömrəli analiz edilmiş parçalar
     * }>
     */
    public function parse(string $rawDiff): array
    {
        $files = [];
        $currentFile = null;
        $currentDiff = '';

        $lines = explode("\n", $rawDiff);
        $i = 0;

        while ($i < count($lines)) {
            $line = $lines[$i];

            if (str_starts_with($line, 'diff --git ')) {
                // Əvvəlki faylı saxla
                if ($currentFile !== null) {
                    $currentFile['diff'] = $currentDiff;
                    $currentFile['hunks'] = $this->parseHunks($currentDiff);
                    $files[] = $currentFile;
                }

                // Yeni fayl başlat
                preg_match('/diff --git a\/(.+) b\/(.+)/', $line, $matches);
                $currentFile = [
                    'file_path' => $matches[2] ?? '',
                    'old_path' => $matches[1] ?? '',
                    'status' => 'modified',
                    'diff' => '',
                    'additions' => 0,
                    'deletions' => 0,
                    'hunks' => [],
                ];
                $currentDiff = $line . "\n";

            } elseif ($currentFile !== null) {
                $currentDiff .= $line . "\n";

                if (str_starts_with($line, 'new file mode')) {
                    $currentFile['status'] = 'added';
                } elseif (str_starts_with($line, 'deleted file mode')) {
                    $currentFile['status'] = 'deleted';
                } elseif (str_starts_with($line, 'rename from')) {
                    $currentFile['status'] = 'renamed';
                } elseif (str_starts_with($line, '+') && !str_starts_with($line, '+++')) {
                    $currentFile['additions']++;
                } elseif (str_starts_with($line, '-') && !str_starts_with($line, '---')) {
                    $currentFile['deletions']++;
                }
            }

            $i++;
        }

        // Son faylı saxla
        if ($currentFile !== null) {
            $currentFile['diff'] = $currentDiff;
            $currentFile['hunks'] = $this->parseHunks($currentDiff);
            $files[] = $currentFile;
        }

        // Yalnız icmal edilə bilən faylları filtrələ
        return array_values(array_filter($files, fn($f) => $this->shouldReview($f)));
    }

    /**
     * Sətir nömrəsi xəritələrini çıxarmaq üçün @@ parça başlıqlarını analiz et.
     * Hər parça üçün ['old_start', 'new_start', 'lines'] massivi qaytarır.
     */
    private function parseHunks(string $diff): array
    {
        $hunks = [];
        $lines = explode("\n", $diff);
        $currentHunk = null;
        $currentNewLine = 0;

        foreach ($lines as $line) {
            if (preg_match('/@@ -(\d+)(?:,\d+)? \+(\d+)(?:,\d+)? @@/', $line, $matches)) {
                if ($currentHunk) $hunks[] = $currentHunk;
                $currentHunk = [
                    'old_start' => (int) $matches[1],
                    'new_start' => (int) $matches[2],
                    'lines' => [],
                ];
                $currentNewLine = (int) $matches[2];
            } elseif ($currentHunk !== null) {
                if (str_starts_with($line, '+')) {
                    $currentHunk['lines'][$currentNewLine] = ['type' => 'added', 'content' => substr($line, 1)];
                    $currentNewLine++;
                } elseif (str_starts_with($line, '-')) {
                    $currentHunk['lines'][] = ['type' => 'removed', 'content' => substr($line, 1)];
                } elseif (!str_starts_with($line, '\\')) {
                    $currentHunk['lines'][$currentNewLine] = ['type' => 'context', 'content' => substr($line, 1)];
                    $currentNewLine++;
                }
            }
        }

        if ($currentHunk) $hunks[] = $currentHunk;
        return $hunks;
    }

    private function shouldReview(array $file): bool
    {
        $path = $file['file_path'];

        // Silinmiş faylları keç
        if ($file['status'] === 'deleted') return false;

        // Xaric edilmiş nümunələri keç
        foreach ($this->excludedPatterns as $pattern) {
            if (str_contains($path, ltrim($pattern, '/'))) return false;
        }

        // Genişləməni yoxla
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        if (!in_array($ext, $this->includedExtensions)) {
            // Xüsusi hal: blade.php iki genişlənməyə malikdir
            if (!str_ends_with($path, '.blade.php')) return false;
        }

        // Kiçik dəyişiklikləri keç (< 5 dəyişdirilmiş sətir — ehtimal ki, yalnız formatlaşdırma)
        if ($file['additions'] + $file['deletions'] < 5) return false;

        return true;
    }

    /**
     * Böyük diff-i token limitinə sığan parçalara böl.
     * Diff sətirləri massivi qaytarır.
     */
    public function splitIntoChunks(string $diff, int $maxCharsPerChunk = 40_000): array
    {
        if (strlen($diff) <= $maxCharsPerChunk) {
            return [$diff];
        }

        $chunks = [];
        $lines = explode("\n", $diff);
        $currentChunk = '';

        foreach ($lines as $line) {
            if (strlen($currentChunk) + strlen($line) > $maxCharsPerChunk) {
                if ($currentChunk) $chunks[] = $currentChunk;
                $currentChunk = $line . "\n";
            } else {
                $currentChunk .= $line . "\n";
            }
        }

        if ($currentChunk) $chunks[] = $currentChunk;
        return $chunks;
    }
}
```

---

## Kod İcmal Xidməti (Claude İnteqrasiyası)

```php
// app/Services/CodeReview/ClaudeReviewService.php
<?php

namespace App\Services\CodeReview;

use Anthropic\Laravel\Facades\Anthropic;
use Illuminate\Support\Facades\Log;

class ClaudeReviewService
{
    // İcmal prompt-u ən vacib hissədir.
    // Claude-un nəyi axtarmasını istədiyiniz barədə konkret olun.
    private string $systemPrompt = <<<'PROMPT'
    Sən ekspert Laravel/PHP kod icmalçısısın. Təqdim edilən git diff-i analiz et və problemləri müəyyən et.

    Bu kateqoriyalara diqqət et (prioritet sırasına görə):
    1. **TƏHLÜKƏSİZLİK** — SQL injection, XSS, CSRF bypass, kütləvi təyinat zəiflikləri, ifşa edilmiş sirlər, təhlükəsiz olmayan birbaşa obyekt istinadları, çatışmayan avtorizasiya yoxlamaları
    2. **XƏTA** — Məntiq xətaları, null pointer istisnaları, off-by-one xətaları, yarış vəziyyətləri, çatışmayan xəta idarəetməsi, yanlış dəyişən istifadəsi
    3. **PERFORMANS** — N+1 sorğular (çatışmayan eager loading), çatışmayan verilənlər bazası indeksləri, səmərəsiz döngülər, lazımsız API çağırışları, yaddaş sızmaları
    4. **LARAVEL_BEST_PRACTICE** — Eloquent əlaqələrindən istifadə etməmək, ORM işləyəcəkdə xam sorğular, çatışmayan doğrulama, facade-ların yanlış istifadəsi, Laravel konvensiyalarına uymamaq
    5. **STİL** — Kiçik problemlər: adlandırma, formatlaşdırma, həddən artıq mürəkkəb kod, public metodlar üçün çatışmayan docblock-lar

    Cavab formatı — JSON massivi kimi problemlər qaytarın:
    [
      {
        "file": "app/Http/Controllers/UserController.php",
        "line": 42,
        "severity": "critical|high|medium|low|info",
        "category": "security|bug|performance|laravel_best_practice|style",
        "comment": "Problemin aydın izahı və niyə əhəmiyyət daşıdığı",
        "suggestion": "İsteğe bağlı: konkret düzəldilmiş kod və ya yanaşma"
      }
    ]

    Qaydalar:
    - Yalnız ƏLAVƏ EDİLMİŞ sətirlərdəki (+ ilə başlayan) problemləri bildirin, silinmiş sətirləri deyil
    - Konkret olun — faktiki koda istinad edin, ümumi məsləhət deyil
    - severity=critical: təhlükəsizlik zəiflikləri, məlumat itkisi xətaları
    - severity=high: istehsalda mütləq xətalara səbəb olacaq xətalar
    - severity=medium: performans problemləri, potensial xətalar
    - severity=low: best practice pozuntuları
    - severity=info: stil/təmizlik təklifləri
    - Problem yoxdursa, boş massiv qaytarın: []
    - Problem uydurmayın. Yalnız real problemləri bildirin.
    - Fayl başına maksimum 10 problem (severity-yə görə prioritetləşdirin)
    PROMPT;

    public function reviewFileDiff(
        string $filePath,
        string $diff,
        array $context = [],
    ): array {
        $contextStr = '';
        if (!empty($context['pr_title'])) {
            $contextStr = "PR: {$context['pr_title']}\n";
        }
        if (!empty($context['pr_description'])) {
            $contextStr .= "Açıqlama: " . substr($context['pr_description'], 0, 500) . "\n";
        }

        $prompt = <<<PROMPT
        {$contextStr}
        Fayl: {$filePath}

        Diff:
        ```diff
        {$diff}
        ```

        Bu diff-i icmal et və problemləri JSON massivi kimi qaytarın. Problem yoxdursa [] qaytarın.
        PROMPT;

        try {
            $response = Anthropic::messages()->create([
                'model' => 'claude-sonnet-4-6',
                'max_tokens' => 2048,
                'system' => $this->systemPrompt,
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ]);

            $text = $response->content[0]->text ?? '[]';

            // Cavabdan JSON çıxar (Claude bəzən əvvəl/sonra izahat əlavə edir)
            if (preg_match('/\[.*\]/s', $text, $matches)) {
                $issues = json_decode($matches[0], true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($issues)) {
                    return [
                        'issues' => $issues,
                        'input_tokens' => $response->usage->inputTokens,
                        'output_tokens' => $response->usage->outputTokens,
                    ];
                }
            }

            Log::warning("{$filePath} üçün Claude JSON olmayan icmal qaytardı", ['text' => $text]);
            return ['issues' => [], 'input_tokens' => 0, 'output_tokens' => 0];

        } catch (\Exception $e) {
            Log::error("{$filePath} üçün Claude icmalı uğursuz oldu", ['error' => $e->getMessage()]);
            return ['issues' => [], 'input_tokens' => 0, 'output_tokens' => 0];
        }
    }

    /**
     * PR səviyyəli xülasə şərhi yarat.
     */
    public function generateSummary(
        array $allIssues,
        string $prTitle,
        array $reviewedFiles,
    ): string {
        if (empty($allIssues)) {
            return $this->buildSummaryMarkdown([], $prTitle, $reviewedFiles);
        }

        // Xülasə üçün problemləri severity-yə görə qruplaşdır
        $bySeverity = collect($allIssues)->groupBy('severity');
        $criticalCount = count($bySeverity['critical'] ?? []);
        $highCount = count($bySeverity['high'] ?? []);
        $mediumCount = count($bySeverity['medium'] ?? []);

        return $this->buildSummaryMarkdown($allIssues, $prTitle, $reviewedFiles);
    }

    private function buildSummaryMarkdown(array $issues, string $prTitle, array $files): string
    {
        $bySeverity = collect($issues)->groupBy('severity');
        $total = count($issues);

        $critical = count($bySeverity['critical'] ?? []);
        $high = count($bySeverity['high'] ?? []);
        $medium = count($bySeverity['medium'] ?? []);
        $low = count($bySeverity['low'] ?? []);

        $emoji = match(true) {
            $critical > 0 => '🔴',
            $high > 0 => '🟠',
            $medium > 0 => '🟡',
            $total > 0 => '🔵',
            default => '✅',
        };

        $md = "{$emoji} **AI Kod İcmalı Xülasəsi**\n\n";
        $md .= "> " . count($files) . " fayl icmal edildi\n\n";

        if ($total === 0) {
            $md .= "Problem tapılmadı. Yaxşı görünür! 🎉\n";
            return $md;
        }

        $md .= "| Ciddilik | Say |\n|----------|-----|\n";
        if ($critical) $md .= "| 🔴 Kritik | {$critical} |\n";
        if ($high)     $md .= "| 🟠 Yüksək | {$high} |\n";
        if ($medium)   $md .= "| 🟡 Orta   | {$medium} |\n";
        if ($low)      $md .= "| 🔵 Aşağı  | {$low} |\n";

        // Ən vacib problemlər
        $topIssues = collect($issues)
            ->sortBy(fn($i) => match($i['severity']) {
                'critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3, default => 4,
            })
            ->take(5);

        if ($topIssues->isNotEmpty()) {
            $md .= "\n**Əsas Problemlər:**\n\n";
            foreach ($topIssues as $issue) {
                $severityEmoji = match($issue['severity']) {
                    'critical' => '🔴', 'high' => '🟠', 'medium' => '🟡', 'low' => '🔵', default => 'ℹ️',
                };
                $md .= "- {$severityEmoji} `{$issue['file']}:{$issue['line']}` — {$issue['comment']}\n";
            }
        }

        $md .= "\n---\n*AI Kod İcmal Botu tərəfindən yaradıldı*";

        return $md;
    }
}
```

---

## Webhook Kontroller

```php
// app/Http/Controllers/GitHubWebhookController.php
<?php

namespace App\Http\Controllers;

use App\Jobs\ReviewPullRequest;
use App\Models\CodeReview;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class GitHubWebhookController extends Controller
{
    public function handle(Request $request): Response
    {
        // Webhook imzasını yoxla (HMAC-SHA256)
        if (!$this->verifySignature($request)) {
            return response('İmza uyğunsuzluğu', 403);
        }

        $event = $request->header('X-GitHub-Event');
        $payload = $request->json()->all();

        // Yalnız müəyyən əməliyyatlarla pull_request hadisələrini idarə et
        if ($event !== 'pull_request') {
            return response('Nəzərə alınmadı', 200);
        }

        $action = $payload['action'] ?? '';
        if (!in_array($action, ['opened', 'synchronize', 'reopened'])) {
            return response('Nəzərə alınmadı', 200);
        }

        $pr = $payload['pull_request'];
        $repo = $payload['repository']['full_name'];
        $prNumber = $pr['number'];
        $headSha = $pr['head']['sha'];

        // Bu dəqiq commit-i artıq icmal edib-etmədiyimizi yoxla
        $existingReview = CodeReview::where('repository', $repo)
            ->where('pr_number', $prNumber)
            ->where('head_sha', $headSha)
            ->first();

        if ($existingReview) {
            return response('Bu commit artıq icmal edilir', 200);
        }

        // İcmal qeydi yarat
        $review = CodeReview::create([
            'repository' => $repo,
            'pr_number' => $prNumber,
            'pr_title' => $pr['title'],
            'head_sha' => $headSha,
            'status' => 'pending',
            'metadata' => [
                'pr_url' => $pr['html_url'],
                'author' => $pr['user']['login'],
                'base_branch' => $pr['base']['ref'],
                'head_branch' => $pr['head']['ref'],
            ],
        ]);

        // İcmal tapşırığını növbəyə al
        ReviewPullRequest::dispatch($review->id)
            ->onQueue('code-review');

        return response('İcmal növbəyə alındı', 202);
    }

    private function verifySignature(Request $request): bool
    {
        $secret = config('services.github.webhook_secret');
        if (empty($secret)) return true; // Gizli konfiqurasiya edilməyib — dev-də keç

        $signature = $request->header('X-Hub-Signature-256', '');
        $expected = 'sha256=' . hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }
}
```

---

## Əsas İcmal Tapşırığı

```php
// app/Jobs/ReviewPullRequest.php
<?php

namespace App\Jobs;

use App\Models\CodeReview;
use App\Services\CodeReview\GitHubService;
use App\Services\CodeReview\DiffParser;
use App\Services\CodeReview\ClaudeReviewService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ReviewPullRequest implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries = 2;

    public function __construct(private readonly int $reviewId) {}

    public function handle(
        GitHubService $github,
        DiffParser $parser,
        ClaudeReviewService $claude,
    ): void {
        $review = CodeReview::find($this->reviewId);
        if (!$review || $review->status === 'completed') return;

        $review->update(['status' => 'processing']);

        try {
            $repo = $review->repository;
            $prNumber = $review->pr_number;

            // 1. Diff-i əldə et
            Log::info("{$repo}#{$prNumber} üçün diff əldə edilir");
            $rawDiff = $github->getPullRequestDiff($repo, $prNumber);

            // 2. Faylları analiz et və filtrələ
            $files = $parser->parse($rawDiff);
            Log::info(count($files) . " icmal edilə bilən fayl tapıldı");

            if (empty($files)) {
                $review->update(['status' => 'completed']);
                $github->createPrComment($repo, $prNumber,
                    "✅ **AI Kod İcmalı**: Dəyişdirilmiş PHP/Blade faylı yoxdur.");
                return;
            }

            // 3. Hər faylı icmal et
            $allIssues = [];
            $totalInputTokens = 0;
            $totalOutputTokens = 0;
            $context = [
                'pr_title' => $review->pr_title,
                'pr_description' => $review->metadata['description'] ?? '',
            ];

            foreach ($files as $file) {
                // Böyük diff-ləri parçalara böl
                $chunks = $parser->splitIntoChunks($file['diff']);

                foreach ($chunks as $chunk) {
                    $result = $claude->reviewFileDiff($file['file_path'], $chunk, $context);

                    foreach ($result['issues'] as $issue) {
                        $issue['file'] = $file['file_path'];
                        $allIssues[] = $issue;

                        // DB-yə saxla
                        $review->comments()->create([
                            'file_path' => $issue['file'],
                            'line_number' => $issue['line'] ?? null,
                            'severity' => $issue['severity'] ?? 'low',
                            'category' => $issue['category'] ?? 'style',
                            'comment' => $issue['comment'],
                            'suggestion' => $issue['suggestion'] ?? null,
                        ]);
                    }

                    $totalInputTokens += $result['input_tokens'];
                    $totalOutputTokens += $result['output_tokens'];
                }
            }

            // 4. GitHub Review API üçün inline şərhlər qur
            $githubComments = $this->buildGitHubComments($allIssues, $files);

            // 5. PR xülasəsi yarat
            $summaryBody = $claude->generateSummary($allIssues, $review->pr_title, $files);

            // 6. GitHub-a icmal yerləşdir (inline şərhlər + xülasə bir API çağırışında)
            $event = $this->determineReviewEvent($allIssues);
            $githubReview = $github->createReview(
                $repo,
                $prNumber,
                $review->head_sha,
                $summaryBody,
                $githubComments,
                $event,
            );

            // 7. İcmal qeydini yenilə
            $criticalAndHigh = collect($allIssues)
                ->filter(fn($i) => in_array($i['severity'], ['critical', 'high']))
                ->count();

            $review->update([
                'status' => 'completed',
                'files_reviewed' => count($files),
                'comments_posted' => count($githubComments),
                'issues_found' => $criticalAndHigh,
                'input_tokens' => $totalInputTokens,
                'output_tokens' => $totalOutputTokens,
                'github_review_id' => $githubReview['id'] ?? null,
            ]);

            Log::info("{$repo}#{$prNumber} üçün icmal tamamlandı: " . count($allIssues) . " problem");

        } catch (\Exception $e) {
            Log::error("{$this->reviewId} icmalı üçün xəta", ['error' => $e->getMessage()]);
            $review->update(['status' => 'failed']);
            throw $e;
        }
    }

    /**
     * Problemlərimizi GitHub-ın inline şərh formatına çevir.
     * GitHub sətir nömrəsinin bir parçanın aralığında olmasını tələb edir.
     */
    private function buildGitHubComments(array $issues, array $files): array
    {
        $comments = [];
        $fileHunks = collect($files)->keyBy('file_path');

        foreach ($issues as $issue) {
            if (empty($issue['line'])) continue;

            $file = $fileHunks->get($issue['file']);
            if (!$file) continue;

            // Sətirin parçada olduğunu yoxla (GitHub aralıq xaricindəki sətirləri rədd edəcək)
            $lineIsValid = collect($file['hunks'])->some(function ($hunk) use ($issue) {
                return isset($hunk['lines'][$issue['line']]);
            });

            if (!$lineIsValid) continue;

            $body = $this->formatCommentBody($issue);
            $comments[] = [
                'path' => $issue['file'],
                'line' => $issue['line'],
                'side' => 'RIGHT',
                'body' => $body,
            ];
        }

        return $comments;
    }

    private function formatCommentBody(array $issue): string
    {
        $severityEmoji = match($issue['severity']) {
            'critical' => '🔴 **KRİTİK**',
            'high' => '🟠 **Yüksək**',
            'medium' => '🟡 **Orta**',
            'low' => '🔵 Aşağı',
            default => 'ℹ️ Məlumat',
        };

        $categoryLabel = match($issue['category']) {
            'security' => '🔒 Təhlükəsizlik',
            'bug' => '🐛 Xəta',
            'performance' => '⚡ Performans',
            'laravel_best_practice' => '⚙️ Laravel Best Practice',
            'style' => '✨ Stil',
            default => $issue['category'],
        };

        $body = "{$severityEmoji} · {$categoryLabel}\n\n{$issue['comment']}";

        if (!empty($issue['suggestion'])) {
            $body .= "\n\n**Təklif:**\n```php\n{$issue['suggestion']}\n```";
        }

        return $body;
    }

    /**
     * REQUEST_CHANGES-mi, yoxsa COMMENT-mi etmək qərara gəl.
     * Yalnız kritik/yüksək ciddilikli problemlər üçün dəyişiklik tələb et.
     */
    private function determineReviewEvent(array $issues): string
    {
        $hasBlockingIssues = collect($issues)->some(
            fn($i) => in_array($i['severity'], ['critical', 'high'])
        );

        // Yalnız konfiqurasiya varsa və bloklayan problemlər varsa REQUEST_CHANGES
        if ($hasBlockingIssues && config('code_review.request_changes_on_high', false)) {
            return 'REQUEST_CHANGES';
        }

        return 'COMMENT';
    }
}
```

---

## Marşrutlar və Konfiqurasiya

```php
// routes/web.php
Route::post('/webhooks/github', [\App\Http\Controllers\GitHubWebhookController::class, 'handle'])
    ->middleware('throttle:60,1')
    ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);

// İcmalları görmək üçün admin marşrutları
Route::middleware(['auth', 'admin'])->prefix('admin/reviews')->name('reviews.')->group(function () {
    Route::get('/', fn() => view('admin.reviews.index', [
        'reviews' => \App\Models\CodeReview::latest()->paginate(20),
    ]))->name('index');

    Route::get('/{review}', fn(\App\Models\CodeReview $review) => view('admin.reviews.show', [
        'review' => $review->load('comments'),
    ]))->name('show');
});
```

```php
// config/code_review.php
<?php

return [
    // Yüksək/kritik problemlər üçün REQUEST_CHANGES (birləşməni blokla) edib-etməmək
    // Komanda buna hazır olduqda yalnız true edin
    'request_changes_on_high' => env('CODE_REVIEW_REQUEST_CHANGES', false),

    // Standartların xaricindəki fayl nümunələrini keç
    'excluded_patterns' => [
        'database/migrations/',
        'tests/',
        'config/',
    ],

    // Xüsusi icmal diqqət sahələri (sistem prompt-una əlavə edilir)
    'custom_rules' => env('CODE_REVIEW_CUSTOM_RULES', null),

    // PR başına icmal ediləcək maksimum fayl sayı (böyük PR-larda xərcləri idarə etmək üçün)
    'max_files_per_pr' => env('CODE_REVIEW_MAX_FILES', 20),

    // Xərc xəbərdarlığı: tək icmal bu qədərdən çox xərc tutursa bildiriş göndər (USD)
    'cost_alert_threshold' => 1.00,
];
```

---

## GitHub App Quraşdırması

İstehsal üçün GitHub App yarat (OAuth App deyil):

1. **Settings → Developer Settings → GitHub Apps → New** bölməsinə keçin
2. Webhook URL-i təyin edin: `https://yourapp.com/webhooks/github`
3. Abunə olun: `Pull request` hadisələri
4. İcazələr: `Pull requests: Read & write`, `Contents: Read`
5. Şəxsi açar yaradın və App ID-ni qeyd edin
6. Tətbiqi repozitoriyanzda quraşdırın

```env
GITHUB_APP_ID=12345
GITHUB_PRIVATE_KEY_PATH=/path/to/private-key.pem
GITHUB_WEBHOOK_SECRET=your-webhook-secret
```

Şəxsi layihələr üçün Personal Access Token (PAT) daha sadədir:

```env
GITHUB_TOKEN=ghp_your_personal_access_token
GITHUB_WEBHOOK_SECRET=your-webhook-secret
```

---

## İstehsal Mülahizələri

### Xərc Nəzarəti

10 faylı olan tipik PR Claude API çağırışlarında ~$0.10-0.50 xərc tutur. Böyük PR-lar (100+ fayl) üçün fayl limiti əlavə edin:

```php
// ReviewPullRequest::handle() içində:
$files = array_slice($files, 0, config('code_review.max_files_per_pr', 20));
if (count($parser->parse($rawDiff)) > config('code_review.max_files_per_pr')) {
    // Yalnız ilk N faylı icmal etdiyimizi izah edən şərh yerləşdir
}
```

### Yanlış Müsbətləri Azaltmaq

Komandanızın xüsusi konvensiyaları ilə sistem prompt-unu incə tənzimləyin:

```php
// config/code_review.php custom_rules:
'custom_rules' => "
    Bu layihə Repository pattern istifadə edir — bu kod bazasında kontrollerlerdə birbaşa Eloquent normaldir.
    Biz public resurslar üçün tam ədəd ID-ləri deyil, ULID istifadə edirik.
    FormRequest sinifləri bütün doğrulamanı idarə edir — kontrollerlerdə çatışmayan doğrulamanı işarələməyin.
",
```

### Paralel Fayl İcmalları

Böyük PR-lar üçün hər fayl icmalını ayrı tapşırıq kimi göndərin:

```php
// Bir tapşırıqda ardıcıl fayl icmalı əvəzinə:
$batch = Bus::batch(
    collect($files)->map(fn($file) => new ReviewSingleFile($review->id, $file))
)->then(function (Batch $batch) use ($review) {
    // Bütün fayllar bitdikdə xülasə yerləşdir
    PostReviewSummary::dispatch($review->id);
})->dispatch();
```

---

## Layihə Kontekst Şüuru (Codebase-Aware Review)

Generic prompt "N+1 yoxla, SQL injection yoxla" — amma hər layihənin öz konvensiyaları var. Layihənin arxitektura qaydalarını Claude-a verin:

```php
// app/Services/CodeReview/ProjectContextService.php
<?php

namespace App\Services\CodeReview;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ProjectContextService
{
    /**
     * Layihənin arxitektura qaydalarını oxuyur.
     * .claude-review.yml faylından konfiqurasiya alır.
     */
    public function getProjectContext(string $repo): string
    {
        return Cache::remember("project_context:{$repo}", 3600, function () use ($repo) {
            $parts = [];

            // 1. Repo-dan .claude-review.yml oxu (repository qaydaları)
            $configContent = $this->fetchFileFromRepo($repo, '.claude-review.yml');
            if ($configContent) {
                $config = yaml_parse($configContent) ?? [];
                if (!empty($config['coding_standards'])) {
                    $parts[] = "## Layihənin Kodlama Standartları\n" .
                        implode("\n", array_map(fn($r) => "- {$r}", $config['coding_standards']));
                }
                if (!empty($config['architecture_patterns'])) {
                    $parts[] = "## Arxitektura Pattern-ləri\n" .
                        implode("\n", array_map(fn($p) => "- {$p}", $config['architecture_patterns']));
                }
                if (!empty($config['false_positive_rules'])) {
                    $parts[] = "## Keçilməli Pattern-lər (False Positive)\n" .
                        implode("\n", array_map(fn($r) => "- {$r}", $config['false_positive_rules']));
                }
            }

            // 2. composer.json-dan framework versiyasını çıxar
            $composerJson = $this->fetchFileFromRepo($repo, 'composer.json');
            if ($composerJson) {
                $composer = json_decode($composerJson, true) ?? [];
                $laravelVersion = $composer['require']['laravel/framework'] ?? null;
                if ($laravelVersion) {
                    $parts[] = "## Framework\nLaravel {$laravelVersion} istifadə olunur.";
                }
            }

            return implode("\n\n", $parts);
        });
    }

    private function fetchFileFromRepo(string $repo, string $path): ?string
    {
        try {
            $response = Http::withToken(config('services.github.token'))
                ->withHeaders(['Accept' => 'application/vnd.github.v3.raw'])
                ->timeout(10)
                ->get("https://api.github.com/repos/{$repo}/contents/{$path}");

            return $response->successful() ? $response->body() : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
```

**`.claude-review.yml`** nümunəsi (repo root-da):

```yaml
# .claude-review.yml — Bu layihənin AI kod icmal qaydaları
coding_standards:
  - "Repository pattern istifadə edirik, kontrollerlerdə birbaşa Eloquent normaldir"
  - "FormRequest sinifləri bütün validasiyanı idarə edir — kontrollerdə missing validation flag etmə"
  - "UUID v4 əvəzinə ULID istifadə edirik public resource-lar üçün"
  - "Service class-lar readonly constructor property promotion istifadə edir (PHP 8.1+)"

architecture_patterns:
  - "CQRS pattern: Commands (write) və Queries (read) ayrıdır — bu normal dizayndır"
  - "Event Sourcing istifadə edirik, birbaşa DB update əvəzinə event dispatch normaldir"
  - "Outbox pattern: events events_outbox cədvəlindən oxunur"

false_positive_rules:
  - "'Missing eager loading' — bu layihədə lazy loading qəsdən istifadə olunur (benchmark edilib)"
  - "config() çağırışları kontrollerdə — dependency injection əvəzinə qəsdən, bəzi yerlər"
```

Claude review prompt-unu layihə konteksti ilə zənginləşdirin:

```php
// ClaudeReviewService::reviewFileDiff() metodunda:

public function reviewFileDiff(
    string $filePath,
    string $diff,
    array $context = [],
    string $projectContext = '',   // ← yeni parametr
): array {
    $contextBlock = $projectContext
        ? "\n\n{$projectContext}\n\nYuxarıdakı layihə qaydalarına əsaslanaraq icmal et."
        : '';

    $prompt = <<<PROMPT
    {$contextBlock}

    Fayl: {$filePath}

    Diff:
    ```diff
    {$diff}
    ```

    Bu diff-i icmal et. Problem yoxdursa [] qaytarın.
    PROMPT;

    // ...
}
```

---

## Keçmiş İcmallardan Öyrənmə

Reviewer "Dismiss" etdiyi şərhləri gələcək icmallardan çıxarın:

```php
// database/migrations/2024_01_02_create_review_feedback_table.php
Schema::create('review_feedback', function (Blueprint $table) {
    $table->id();
    $table->foreignId('code_review_comment_id')->constrained()->cascadeOnDelete();
    $table->string('repository');
    $table->string('feedback'); // 'accepted', 'dismissed', 'false_positive'
    $table->string('dismissed_reason')->nullable();
    $table->string('reviewer')->nullable();
    $table->timestamps();
    $table->index(['repository', 'feedback']);
});
```

```php
// FeedbackLearningService.php — dismissal pattern-lərini öyrən
class FeedbackLearningService
{
    /**
     * Repo üçün tez-tez dismissed pattern-ları çıxarır.
     * Bu pattern-lar sistem prompt-a "false positive rules" kimi əlavə olunur.
     */
    public function getLearnedRules(string $repo, int $minOccurrences = 3): array
    {
        // Son 90 gündəki dismissed şərhlər
        return CodeReviewComment::query()
            ->join('review_feedback', 'review_feedback.code_review_comment_id', '=', 'code_review_comments.id')
            ->join('code_reviews', 'code_reviews.id', '=', 'code_review_comments.code_review_id')
            ->where('code_reviews.repository', $repo)
            ->where('review_feedback.feedback', 'false_positive')
            ->where('code_reviews.created_at', '>=', now()->subDays(90))
            ->select('code_review_comments.category', 'review_feedback.dismissed_reason')
            ->get()
            ->groupBy('dismissed_reason')
            ->filter(fn($group) => $group->count() >= $minOccurrences)
            ->keys()
            ->map(fn($reason) => "- {$reason} (reviewer tərəfindən dəfələrlə dismissed edilib)")
            ->values()
            ->all();
    }
}
```

---

## Praktik Tapşırıqlar

### Tapşırıq 1: Webhook + Lokal Test

1. `ngrok http 8000` ilə lokal tunnel açın
2. Test reposunda GitHub App webhook-u ngrok URL-ə yönləndirin
3. Kiçik bir PR açın (3-5 sətir dəyişiklik)
4. Webhook gəldikdə `code_reviews` cədvəlini izləyin
5. GitHub PR-da inline şərh göründüyünü doğrulayın

### Tapşırıq 2: `.claude-review.yml` Konfiqurasiyası

Mövcud bir layihədə aşağıdakıları müəyyən edin:
- 3 "false positive" — Claude tez-tez flag etdiyi, amma qəbul edilən pattern (məs., `config()` çağırışı)
- 2 xüsusi layihə qaydası (məs., "bizim authentication Sanctum ilə, manual JWT yoxdur")
- 1 arxitektura qeydiyyatı

`.claude-review.yml` yazın, review prompt-una inteqrasiya edin, eyni PR-da qabaq/sonra müqayisə edin.

### Tapşırıq 3: Cost Monitoring

10 PR-dan sonra:
- Fayl başına ortalama token istifadəsini hesablayın
- Ən bahalı fayl tipini müəyyən edin (PHP vs Blade vs JSON)
- `max_files_per_pr` limitini optimallaşdırın: keyfiyyəti qoruyaraq aylıq $X limiti altında saxlayın

---

## Əlaqəli Mövzular

- `01-support-bot.md` — FAQ cavablama botu (eyni queue + Claude pattern-i)
- `07-sql-assistant.md` — SQL agent (agentic review üçün ilham)
- `../02-claude-api/02-prompt-engineering.md` — System prompt keyfiyyəti (review prompt-unu yaxşılaşdırmaq üçün)
- `../02-claude-api/04-tool-use.md` — Review agentini tool-calling ilə daha ağıllı etmək
- `../05-agents/08-agent-orchestration-patterns.md` — Çoxlu reviewer agent-lər (paralel ixtisaslaşmış review-lər)
