# Content Moderation Pipeline (Lead)

## Problem Təsviri

Sosial platforma, marketplace və ya forum saxlayan istənilən backend sistem **user-generated content (UGC)** ilə qarşılaşır:

- User şərhləri, postları, məhsul rəyləri
- Profile şəkilləri, məhsul şəkilləri
- Direkt mesajlar, rəylər
- Video upload-ları (TikTok-style platformalar)

Bu content **abusive, illegal və ya spam** ola bilər:

- Hate speech, harassment
- Sexual content (xüsusilə uşaq istismarı materialı — CSAM, qanunla məcburi report olunur)
- Spam, scam (phishing link, fake invoice)
- Şiddət, terrorla bağlı content
- Brand-a zərər verən content (rəqib reklamı, false claim)

Real ssenari: 10k yeni post/gün gələn platforma. **Yalnız user report-larına güvənmək** çox gecdir — content artıq saatlarla görünüb. **Yalnız insan moderator** scale etmir (1 moderator gündə 1000 post check edə bilər → 10 moderator lazım, $300k/il). **Yalnız AI** false positive yaradır (legitimate user-lər banlanır).

### Problem niyə yaranır?

Əksər startup-lar moderasiyanı **reactive** qurur — user "Report" düyməsinə basır, sonra moderator yoxlayır. Bu yanaşma 3 səbəbdən uğursuz olur:

1. **Latency:** Content artıq saatlarla feed-də görünür, user-lər görür və emosional zərər çəkir. CSAM kontekstində bu hətta hüquqi məsuliyyətdir (EU DSA, US SESTA-FOSTA).
2. **Volume:** 10k post/gün × 0.5% report rate = 50 report/gün. Reallıqda zərərli content faizi daha yüksəkdir (3-5%) — yəni 300-500 toxic post hər gün görünməz qalır.
3. **Adversarial users:** Spammers və trolls tanış pattern-ləri bilir — birinci hesabı banlandıqdan sonra yenisini yaradır, eyni content-i posting davam etdirir. Reactive sistem hər dəfə sıfırdan başlayır.

Düzgün moderation **layered** olmalıdır: hard rules (sürətli, deterministic) + AI (scalable) + insan reviewer (nuance üçün) + appeals (false positive-lər üçün).

---

## Arxitektura

```
User content yaradır (post/comment/image)
              ↓
┌────────────────────────────────────┐
│  Layer 1: Hard Rules Engine        │
│  Inline, < 10ms                    │
│  - Blocked words list              │
│  - URL blocklist (phishing, scam)  │
│  - Regex pattern (telefon, email)  │
│  - Rate limit per user             │
│  → REJECT immediately              │
└────────────────────────────────────┘
              ↓ (passed)
        Content saved with status=PENDING
              ↓
        Async job dispatched
              ↓
┌────────────────────────────────────┐
│  Layer 2: AI Moderation            │
│  Async, < 2s                       │
│  - OpenAI Moderation API (text)    │
│  - AWS Rekognition (image)         │
│  - Score per category (0-1)        │
└────────────────────────────────────┘
              ↓
        Decision Matrix:
        max_score < 0.3  → AUTO APPROVE → status=APPROVED
        0.3 ≤ score ≤ 0.7 → MANUAL REVIEW QUEUE → status=UNDER_REVIEW
        score > 0.7      → AUTO REJECT (hide) → status=REJECTED
              ↓
        Optional: Human reviewer dashboard
              ↓
        Appeals process (user can appeal rejected content)
```

---

## Content Status Enum

*Bu kod content-in moderasiya lifecycle-ını idarə edən status enum-unu və icazə verilən keçidləri göstərir:*

```php
// app/Enums/ContentStatus.php
namespace App\Enums;

enum ContentStatus: string
{
    case PENDING = 'pending';           // Layer 1 keçib, Layer 2 gözləyir
    case APPROVED = 'approved';         // Görünür
    case REJECTED = 'rejected';         // Hidden
    case UNDER_REVIEW = 'under_review'; // İnsan reviewer queue-da
    case APPEALING = 'appealing';       // User appeal etdi, yenidən review-da
    case AUTO_APPROVED = 'auto_approved'; // AI low score, görünür

    public function isVisible(): bool
    {
        return in_array($this, [
            self::APPROVED,
            self::AUTO_APPROVED,
        ]);
    }

    public function canTransitionTo(self $newStatus): bool
    {
        return match ($this) {
            self::PENDING => in_array($newStatus, [
                self::AUTO_APPROVED,
                self::REJECTED,
                self::UNDER_REVIEW,
            ]),
            self::UNDER_REVIEW => in_array($newStatus, [
                self::APPROVED,
                self::REJECTED,
            ]),
            self::REJECTED => $newStatus === self::APPEALING,
            self::APPEALING => in_array($newStatus, [
                self::APPROVED,
                self::REJECTED,
            ]),
            default => false,
        };
    }
}
```

---

## Migration

*Bu kod content moderasiya nəticələrini saxlayan cədvəlləri yaradır:*

```php
// database/migrations/2024_01_create_content_moderation_tables.php
return new class extends Migration {
    public function up(): void
    {
        Schema::create('contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('type'); // post, comment, profile_image
            $table->text('body')->nullable();
            $table->string('image_url')->nullable();
            $table->string('status')->default('pending');
            $table->json('ai_scores')->nullable();
            $table->float('max_risk_score')->nullable();
            $table->timestamp('moderated_at')->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users');
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['status', 'max_risk_score']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('moderation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_id')->constrained();
            $table->string('layer'); // hard_rules, ai, human, appeal
            $table->string('decision'); // approve, reject, escalate
            $table->string('reason')->nullable();
            $table->json('details')->nullable();
            $table->foreignId('reviewer_id')->nullable()->constrained('users');
            $table->timestamp('decided_at');
        });

        Schema::create('content_appeals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->text('user_explanation');
            $table->string('status')->default('pending'); // pending, upheld, overturned
            $table->foreignId('reviewer_id')->nullable()->constrained('users');
            $table->text('reviewer_notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }
};
```

---

## Layer 1: Hard Rules Engine

*Bu kod content-in DB-yə düşməmişdən əvvəl sürətli (< 10ms) qiymətləndirilməsini həyata keçirən rules engine-i göstərir:*

```php
// app/Services/Moderation/HardRulesEngine.php
namespace App\Services\Moderation;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class HardRulesEngine
{
    /**
     * Content-i deterministic qaydalara qarşı yoxlayır.
     * Match olarsa, dərhal ModerationDecision qaytarır (REJECT).
     */
    public function check(string $userId, string $content): ?ModerationDecision
    {
        // 1. Rate limit per user (spam prevention)
        $key = "content_rate:{$userId}";
        $count = Redis::incr($key);
        if ($count === 1) {
            Redis::expire($key, 60);
        }
        if ($count > 10) {
            return ModerationDecision::reject(
                reason: 'RATE_LIMIT_EXCEEDED',
                details: ['count' => $count, 'window' => '60s']
            );
        }

        // 2. Blocked words (cache-də saxlanır, DB-də idarə olunur)
        $blockedWords = Cache::remember(
            'moderation:blocked_words',
            3600,
            fn () => BlockedWord::pluck('word')->toArray()
        );

        $contentLower = mb_strtolower($content);
        foreach ($blockedWords as $word) {
            if (str_contains($contentLower, mb_strtolower($word))) {
                return ModerationDecision::reject(
                    reason: 'BLOCKED_WORD',
                    details: ['word' => $word]
                );
            }
        }

        // 3. URL blocklist (phishing, scam, malware)
        preg_match_all('/https?:\/\/[^\s<>"]+/i', $content, $urls);
        foreach ($urls[0] as $url) {
            $host = parse_url($url, PHP_URL_HOST);
            if (!$host) continue;

            if ($this->isBlockedDomain($host)) {
                return ModerationDecision::reject(
                    reason: 'BLOCKED_URL',
                    details: ['url' => $url]
                );
            }
        }

        // 4. Suspicious patterns
        if (preg_match('/(\+?\d[\d\s\-()]{8,})/', $content)) {
            // Telefon nömrəsi — minimum suspicious, manual review-a göndər
            return ModerationDecision::escalate(
                reason: 'PHONE_NUMBER_DETECTED'
            );
        }

        if (str_word_count($content) > 0) {
            $upperRatio = $this->uppercaseRatio($content);
            if ($upperRatio > 0.7) {
                // ÇOX BÖYÜK HƏRFLƏ YAZILMIŞ POSTLAR adətən spam-dır
                return ModerationDecision::escalate(
                    reason: 'EXCESSIVE_CAPS',
                    details: ['ratio' => $upperRatio]
                );
            }
        }

        return null; // Layer 1 keçildi
    }

    private function isBlockedDomain(string $host): bool
    {
        $blocklist = Cache::remember(
            'moderation:blocked_domains',
            3600,
            fn () => BlockedDomain::pluck('domain')->toArray()
        );

        foreach ($blocklist as $blocked) {
            if ($host === $blocked || str_ends_with($host, '.' . $blocked)) {
                return true;
            }
        }

        return false;
    }

    private function uppercaseRatio(string $content): float
    {
        $letters = preg_replace('/[^a-zA-Z]/', '', $content);
        if (strlen($letters) < 10) {
            return 0.0;
        }

        $upperCount = strlen(preg_replace('/[^A-Z]/', '', $letters));
        return $upperCount / strlen($letters);
    }
}
```

*Bu kod ModerationDecision dəyər obyektini göstərir:*

```php
// app/Services/Moderation/ModerationDecision.php
namespace App\Services\Moderation;

class ModerationDecision
{
    public function __construct(
        public readonly string $action, // approve, reject, escalate
        public readonly string $reason,
        public readonly array $details = [],
    ) {}

    public static function approve(string $reason = 'NO_ISSUES'): self
    {
        return new self('approve', $reason);
    }

    public static function reject(string $reason, array $details = []): self
    {
        return new self('reject', $reason, $details);
    }

    public static function escalate(string $reason, array $details = []): self
    {
        return new self('escalate', $reason, $details);
    }
}
```

---

## Layer 2: AI Moderation Job

*Bu kod content-i async olaraq AI moderation API-yə göndərib qərar qəbul edən job-u göstərir:*

```php
// app/Jobs/ModerateContentJob.php
namespace App\Jobs;

use App\Enums\ContentStatus;
use App\Models\Content;
use App\Services\Moderation\AiModerationService;
use App\Services\Moderation\ContentModerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ModerateContentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [5, 30, 60];
    public int $timeout = 30;

    public function __construct(
        public int $contentId
    ) {}

    public function handle(
        AiModerationService $ai,
        ContentModerationService $moderation
    ): void {
        $content = Content::find($this->contentId);

        if (!$content || $content->status !== ContentStatus::PENDING->value) {
            return; // Already processed
        }

        try {
            // AI scoring
            $scores = $content->image_url
                ? $ai->analyzeImage($content->image_url)
                : $ai->analyzeText($content->body);

            $moderation->applyAiDecision($content, $scores);
        } catch (\Throwable $e) {
            Log::error('AI moderation failed', [
                'content_id' => $content->id,
                'error' => $e->getMessage(),
            ]);

            // Fallback: AI fail oldu — manual review-a göndər
            $moderation->escalateToManualReview(
                $content,
                'AI_UNAVAILABLE'
            );
        }
    }

    public function failed(\Throwable $exception): void
    {
        // Bütün retry-lar uğursuz oldu — manual review-a göndər
        $content = Content::find($this->contentId);
        if ($content) {
            app(ContentModerationService::class)
                ->escalateToManualReview($content, 'JOB_FAILED');
        }
    }
}
```

*Bu kod OpenAI Moderation və AWS Rekognition API-lərini istifadə edən servisi göstərir:*

```php
// app/Services/Moderation/AiModerationService.php
namespace App\Services\Moderation;

use Aws\Rekognition\RekognitionClient;
use Illuminate\Support\Facades\Http;

class AiModerationService
{
    public function analyzeText(string $text): array
    {
        $response = Http::timeout(10)
            ->withToken(config('services.openai.key'))
            ->post('https://api.openai.com/v1/moderations', [
                'input' => $text,
                'model' => 'text-moderation-latest',
            ])
            ->throw();

        $result = $response->json('results.0');

        return [
            'sexual' => $result['category_scores']['sexual'] ?? 0,
            'hate' => $result['category_scores']['hate'] ?? 0,
            'harassment' => $result['category_scores']['harassment'] ?? 0,
            'self_harm' => $result['category_scores']['self-harm'] ?? 0,
            'sexual_minors' => $result['category_scores']['sexual/minors'] ?? 0,
            'violence' => $result['category_scores']['violence'] ?? 0,
            'flagged' => $result['flagged'] ?? false,
        ];
    }

    public function analyzeImage(string $imageUrl): array
    {
        $client = new RekognitionClient([
            'region' => config('services.aws.region'),
            'version' => 'latest',
        ]);

        $imageBytes = file_get_contents($imageUrl);

        $result = $client->detectModerationLabels([
            'Image' => ['Bytes' => $imageBytes],
            'MinConfidence' => 50,
        ]);

        $scores = [
            'sexual' => 0,
            'violence' => 0,
            'drugs' => 0,
            'hate_symbols' => 0,
        ];

        foreach ($result->get('ModerationLabels') as $label) {
            $confidence = $label['Confidence'] / 100;

            match ($label['ParentName'] ?? $label['Name']) {
                'Explicit Nudity', 'Suggestive' => $scores['sexual'] = max($scores['sexual'], $confidence),
                'Violence', 'Visually Disturbing' => $scores['violence'] = max($scores['violence'], $confidence),
                'Drugs' => $scores['drugs'] = max($scores['drugs'], $confidence),
                'Hate Symbols' => $scores['hate_symbols'] = max($scores['hate_symbols'], $confidence),
                default => null,
            };
        }

        return $scores;
    }
}
```

*Bu kod AI score-larına əsasən qərar verən və content statusunu yeniləyən servisi göstərir:*

```php
// app/Services/Moderation/ContentModerationService.php
namespace App\Services\Moderation;

use App\Enums\ContentStatus;
use App\Models\Content;
use App\Models\ModerationLog;
use Illuminate\Support\Facades\DB;

class ContentModerationService
{
    private const AUTO_APPROVE_THRESHOLD = 0.3;
    private const AUTO_REJECT_THRESHOLD = 0.7;

    public function applyAiDecision(Content $content, array $scores): void
    {
        $maxScore = max($scores);
        $topCategory = array_search($maxScore, $scores);

        DB::transaction(function () use ($content, $scores, $maxScore, $topCategory) {
            if ($maxScore >= self::AUTO_REJECT_THRESHOLD) {
                $content->update([
                    'status' => ContentStatus::REJECTED->value,
                    'ai_scores' => $scores,
                    'max_risk_score' => $maxScore,
                    'moderated_at' => now(),
                    'rejection_reason' => "AI flagged: {$topCategory}",
                ]);

                $this->log($content, 'ai', 'reject', $topCategory, $scores);
            } elseif ($maxScore <= self::AUTO_APPROVE_THRESHOLD) {
                $content->update([
                    'status' => ContentStatus::AUTO_APPROVED->value,
                    'ai_scores' => $scores,
                    'max_risk_score' => $maxScore,
                    'moderated_at' => now(),
                ]);

                $this->log($content, 'ai', 'approve', 'low_score', $scores);
            } else {
                // Borderline — manual review
                $content->update([
                    'status' => ContentStatus::UNDER_REVIEW->value,
                    'ai_scores' => $scores,
                    'max_risk_score' => $maxScore,
                ]);

                $this->log($content, 'ai', 'escalate', 'borderline_score', $scores);
            }
        });
    }

    public function escalateToManualReview(Content $content, string $reason): void
    {
        $content->update([
            'status' => ContentStatus::UNDER_REVIEW->value,
        ]);

        $this->log($content, 'ai', 'escalate', $reason);
    }

    public function humanDecide(
        Content $content,
        int $reviewerId,
        string $decision, // approve | reject
        ?string $reason = null
    ): void {
        DB::transaction(function () use ($content, $reviewerId, $decision, $reason) {
            $newStatus = $decision === 'approve'
                ? ContentStatus::APPROVED->value
                : ContentStatus::REJECTED->value;

            $content->update([
                'status' => $newStatus,
                'reviewed_by_user_id' => $reviewerId,
                'moderated_at' => now(),
                'rejection_reason' => $reason,
            ]);

            $this->log($content, 'human', $decision, $reason, ['reviewer_id' => $reviewerId]);
        });
    }

    private function log(
        Content $content,
        string $layer,
        string $decision,
        ?string $reason,
        array $details = []
    ): void {
        ModerationLog::create([
            'content_id' => $content->id,
            'layer' => $layer,
            'decision' => $decision,
            'reason' => $reason,
            'details' => $details,
            'decided_at' => now(),
        ]);
    }
}
```

---

## Manual Review Dashboard

*Bu kod human reviewer üçün queue və qərar endpoint-lərini göstərir:*

```php
// app/Http/Controllers/Admin/ContentReviewController.php
namespace App\Http\Controllers\Admin;

use App\Enums\ContentStatus;
use App\Http\Controllers\Controller;
use App\Models\Content;
use App\Services\Moderation\ContentModerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContentReviewController extends Controller
{
    public function __construct(
        private ContentModerationService $moderation
    ) {
        $this->middleware('can:moderate-content');
    }

    /**
     * Review queue — high-risk content əvvəl göstərilir.
     */
    public function queue(Request $request): JsonResponse
    {
        $items = Content::where('status', ContentStatus::UNDER_REVIEW->value)
            ->with(['user:id,name,email'])
            ->orderByDesc('max_risk_score')
            ->orderBy('created_at') // Eyni risk-də köhnələr əvvəl
            ->paginate(20);

        return response()->json($items);
    }

    public function approve(Request $request, Content $content): JsonResponse
    {
        $this->moderation->humanDecide(
            content: $content,
            reviewerId: $request->user()->id,
            decision: 'approve',
            reason: $request->input('notes')
        );

        return response()->json(['message' => 'Approved']);
    }

    public function reject(Request $request, Content $content): JsonResponse
    {
        $request->validate(['reason' => 'required|string|max:500']);

        $this->moderation->humanDecide(
            content: $content,
            reviewerId: $request->user()->id,
            decision: 'reject',
            reason: $request->input('reason')
        );

        return response()->json(['message' => 'Rejected']);
    }

    public function stats(): JsonResponse
    {
        return response()->json([
            'pending_review' => Content::where('status', ContentStatus::UNDER_REVIEW->value)->count(),
            'avg_review_time_minutes' => DB::table('contents')
                ->whereIn('status', [ContentStatus::APPROVED->value, ContentStatus::REJECTED->value])
                ->whereNotNull('reviewed_by_user_id')
                ->whereDate('moderated_at', today())
                ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, created_at, moderated_at)) as avg_min')
                ->value('avg_min'),
            'today_decisions' => [
                'approved' => Content::where('status', ContentStatus::APPROVED->value)
                    ->whereDate('moderated_at', today())->count(),
                'rejected' => Content::where('status', ContentStatus::REJECTED->value)
                    ->whereDate('moderated_at', today())->count(),
            ],
        ]);
    }
}
```

---

## Appeals System

*Bu kod user-ə öz rejected content-ini appeal etmək imkanı verən sistemi göstərir:*

```php
// app/Http/Controllers/ContentAppealController.php
namespace App\Http\Controllers;

use App\Enums\ContentStatus;
use App\Models\Content;
use App\Models\ContentAppeal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContentAppealController extends Controller
{
    public function submit(Request $request, Content $content): JsonResponse
    {
        // Yalnız öz rejected content-ini appeal edə bilər
        if ($content->user_id !== $request->user()->id) {
            abort(403);
        }

        if ($content->status !== ContentStatus::REJECTED->value) {
            return response()->json([
                'error' => 'Yalnız rejected content appeal edilə bilər',
            ], 422);
        }

        // Eyni content üçün ikinci appeal qadağandır
        if ($content->appeals()->exists()) {
            return response()->json([
                'error' => 'Bu content üçün artıq appeal mövcuddur',
            ], 422);
        }

        $request->validate([
            'explanation' => 'required|string|min:20|max:1000',
        ]);

        $appeal = ContentAppeal::create([
            'content_id' => $content->id,
            'user_id' => $request->user()->id,
            'user_explanation' => $request->input('explanation'),
            'status' => 'pending',
        ]);

        // Content-i appealing status-una qaytar — yenidən review-a düşür
        $content->update([
            'status' => ContentStatus::APPEALING->value,
        ]);

        return response()->json([
            'message' => 'Appeal qəbul edildi. 48 saat ərzində baxılacaq.',
            'appeal_id' => $appeal->id,
        ]);
    }
}
```

---

## Pre-publish vs Post-publish Decision

İki yanaşma var və hansını seçəcəyiniz product strategiyasından asılıdır:

| Yanaşma | Üstünlük | Risk | Nə zaman istifadə et |
|---------|---------|------|---------------------|
| **Pre-publish** (görünməyə qədər moderasiya) | Pis content heç vaxt görünmür | UX gec — user 5-10 saniyə gözləyir | CSAM, child safety platformları, finansial reklamlar |
| **Post-publish** (dərhal göstər, sonra moderasiya) | UX sürətli — instant feedback | Bir neçə saniyə pis content görünə bilər | Sosial media (Twitter, Instagram), forumlar |
| **Hybrid** (trusted user post-publish, new user pre-publish) | Ən optimal | Mürəkkəb logic | Mature platformalar (LinkedIn, Reddit) |

---

## Trade-offs

| Yanaşma | Sürət | Accuracy | Cost | Nə zaman |
|---------|-------|----------|------|----------|
| **Yalnız Hard Rules** | < 10ms | Çox aşağı (sophisticated content keçir) | Çox aşağı | MVP, low-volume |
| **Yalnız AI** | 1-2s | 85-92% | $0.01-0.05 per content | Mid-volume, no nuance |
| **Yalnız Human** | 5-30 dəqiqə | 95%+ | $300k+/il (scale-də) | Premium platformalar |
| **Hybrid (3-layer)** | < 2s + async | 95%+ | Optimal | Production scale |

---

## Anti-patternlər

**1. Yalnız user report-larına güvənmək**
Reactive moderation — content artıq saatlarla görünüb. CSAM kontekstində hüquqi məsuliyyət yaranır. Proactive scanning + report-lar birgə olmalıdır.

**2. Bütün content-i pre-publish bloklamaq**
User "Post" basır → 10 saniyə gözləyir → görünür. UX dağılır, engagement düşür. Solution: trusted user-lər (verified email, 30+ gün old account) post-publish, new user-lər pre-publish.

**3. Appeals prosesi olmamaq**
False positive 2-5% olur (AI səhv flag edir). User content-i rejected olub, izah yoxdur, appeal yoxdur — user platforma tərk edir. Hər rejected content-ə appeal imkanı verin.

**4. AI false positive rate-ini tracking etməmək**
"AI işləyir" deyə kifayət deyil. Hər həftə random sample götürün, human reviewer-lə yoxlayın. Threshold-u datamətrikə görə tune edin (məs: rejection-ların 5%-i appeal upheld olursa, threshold çox aggressive-dir).

**5. Moderasiya qərarlarını log etməmək**
Compliance audit (DSA, GDPR) zamanı "Niyə bu content rejected oldu?" sualına cavabınız olmalıdır. Hər qərarı (layer, reason, reviewer, timestamp) `moderation_logs` cədvəlində saxlayın.

**6. Blocked words list-ini statik saxlamaq**
Bu gün spammer-lər dünyaya `c1al1s` yazır, sabah `cıal1s`. Blocklist DB-də olmalı, admin UI-dan idarə edilməli, real-time yenilənməlidir. Bypass attempts-ları monitor edin.

**7. Image moderation-ı unutmaq**
Yalnız text moderation = profile photo-da explicit content görünür. AWS Rekognition / Google Vision SafeSearch / OpenAI Vision API ilə hər image-ı analyze edin. Image upload üçün dedicated job dispatch edin.

---

## Interview Sualları və Cavablar

**S: Pre-publish vs post-publish moderation trade-off-u necə qərar verirsiniz?**
C: Content riskinə əsasən. CSAM, terror, child safety üçün **mütləq pre-publish** — hətta saniyə qədər görünmək hüquqi məsuliyyətdir (NCMEC report tələb olunur). Standart sosial media (Twitter, Instagram) post-publish — UX vacibdir, AI 95%+ false negative tutur. Hybrid yanaşma optimal: yeni user (< 30 gün, < 10 post) → pre-publish, trusted user (verified email, history clean) → post-publish + async scan.

**S: AI false positive-ləri necə idarə edirsiniz?**
C: 4 mexanizm: (1) **Appeals system** — user explain edir, ikinci human review olur. (2) **Threshold tuning** — rejection-ların appeal upheld rate-i monitor edilir; > 10%-sə threshold çox aggressive. (3) **Human-in-the-loop** — borderline (0.3-0.7) score-lar avtomatik manual review-a gedir. (4) **Feedback loop** — human reviewer qərarları AI training data-ya qaytarılır (RLHF benzəri).

**S: 100k post/gün scale-də human reviewer-ları necə idarə edərsiniz?**
C: Birinci, AI hər şeyi triage edir — yalnız 5-10% manual review-a gəlir (5-10k/gün). Bu 50 reviewer × 200 case/gün = idarə oluna bilər. Reviewer-lər **specialized** olur (text, image, video, language). **Priority queue** — high-risk score əvvəl. **SLA tracking** — hər case 2 saat ərzində review olmalıdır. **Quality assurance** — random 5%-də senior reviewer second-review.

**S: Compliance/audit tələbləri necə həll edirsiniz?**
C: EU Digital Services Act (DSA), GDPR, US Section 230 hər biri tələblər qoyur. (1) **Audit log** — hər qərar üçün timestamp, layer, reason, reviewer ID. (2) **Transparency report** — illik public report rejection saysayını göstərir. (3) **CSAM hashing** — PhotoDNA / NCMEC API ilə uşaq istismarı materialını detect et və avtomatik report et. (4) **Right to explanation** — user "Niyə rejected oldum?" deyə bilər, log-dan cavab verə bilməlisiniz.

**S: Image moderation-ı text-dən nə fərqlidir?**
C: 3 fərq: (1) **API cost** — image moderation 5-10x bahalıdır ($0.001 vs $0.0001 per item). (2) **Latency** — image bytes upload + API call 1-3s, text 100-500ms. Bu səbəbdən async olmalıdır. (3) **Accuracy** — image moderation context-i tutmur (medical photo vs explicit photo). AWS Rekognition + Google SafeSearch eyni image-a göndərmək (ensemble) accuracy-ni artırır. Text-də OpenAI Moderation kifayətdir.

---

## Əlaqəli Mövzular

- [13-background-job-orchestration.md](13-background-job-orchestration.md) — Async moderation pipeline
- [10-audit-logging.md](10-audit-logging.md) — Moderation log audit
- [16-audit-and-gdpr-compliance.md](16-audit-and-gdpr-compliance.md) — DSA, GDPR compliance
- [42-async-image-processing.md](42-async-image-processing.md) — Image moderation pipeline
