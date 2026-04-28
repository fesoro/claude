# AI Feature Uğurunu Ölçmək: Metric Framework (Senior)

> **Kim üçündür:** Senior developerlər, tech lead-lər, product engineer-lər ki, "AI feature işləyirmi?" sualına məlumata əsaslanan cavab vermək istəyirlər.
>
> **Əhatə dairəsi:** Leading vs lagging indicators, texniki/keyfiyyət/biznes metriklər, dashboard qurma, regression monitoring, Laravel implementasiyası.

---

## 1. "AI Feature İşləyir" Nə Deməkdir?

```
Ənənəvi feature:
  ✓ Test keçdi
  ✓ Exception atmır
  ✓ Latency SLA-ya uyğundur

AI feature üçün bu kifayət deyil:
  ✓ Test keçdi → amma model yanlış cavab verir (non-deterministic)
  ✓ Exception atmır → amma hallucination var
  ✓ Latency yaxşıdır → amma user task-ı tamamlaya bilmir
```

AI feature-ın uğuru **3 dimension-da** ölçülməlidir:
1. **Texniki metriklər** — sistem işləyirmi?
2. **Keyfiyyət metriklər** — model yaxşı cavab verir?
3. **Biznes metriklər** — istifadəçi dəyər alır?

---

## 2. Metric Piramidası

```
         ┌──────────────────┐
         │   Biznes Metriklər│  ← Ən vacib, amma ölçmək çətin
         │  (conversion, NPS)│
         ├──────────────────┤
         │ Keyfiyyət Metriklər│  ← Orta mürəkkəblik
         │  (accuracy, CSAT)  │
         ├──────────────────┤
         │ Texniki Metriklər  │  ← Ən asan, amma yetərli deyil
         │ (latency, uptime)  │
         └──────────────────┘

Yalnız texniki metriklər ölçmək:
  "Sistem 99.9% uptime ilə işləyir!"
  ← Amma model yanlış cavablar verə bilər

Tam sistem:
  Texniki + Keyfiyyət + Biznes → tam mənzərə
```

---

## 3. Texniki Metriklər

### 3.1 Latency

```php
// Laravel Middleware: AI request latency tracking
class AILatencyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);

        $response = $next($request);

        $duration = (microtime(true) - $start) * 1000; // ms

        // Prometheus/Datadog metric
        app('metrics')->histogram('ai_request_duration_ms', $duration, [
            'route'   => $request->route()?->getName() ?? 'unknown',
            'model'   => $response->headers->get('X-AI-Model', 'unknown'),
            'cached'  => $response->headers->get('X-AI-Cached', 'false'),
        ]);

        // P99 alert: 10s-dən çox → xəbərdarlıq
        if ($duration > 10_000) {
            \Log::warning("AI request slow", [
                'duration_ms' => $duration,
                'route'       => $request->path(),
            ]);
        }

        return $response;
    }
}
```

### 3.2 Error Rate

```php
class AIErrorTracker
{
    public function track(string $featureName, bool $success, ?string $errorType = null): void
    {
        app('metrics')->increment('ai_requests_total', [
            'feature' => $featureName,
            'status'  => $success ? 'success' : 'error',
            'error'   => $errorType ?? 'none',
        ]);
    }
}

// Error type-lar:
// - 'rate_limit'    → API 429
// - 'timeout'       → AI çox gec cavab verdi
// - 'parse_error'   → JSON format yanlış
// - 'empty_response'→ Model cavab vermək istəmədi
// - 'provider_down' → API unavailable
```

### 3.3 Token Usage

```php
// Token xərci izlə
class TokenUsageTracker
{
    public function record(
        string $featureName,
        int    $inputTokens,
        int    $outputTokens,
        string $model,
        int    $userId,
    ): void {
        $cost = $this->calculateCost($model, $inputTokens, $outputTokens);

        \DB::table('ai_token_usage')->insert([
            'feature_name'  => $featureName,
            'user_id'       => $userId,
            'input_tokens'  => $inputTokens,
            'output_tokens' => $outputTokens,
            'model'         => $model,
            'cost_usd'      => $cost,
            'created_at'    => now(),
        ]);

        // Budget alert
        $monthlySpend = \DB::table('ai_token_usage')
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->sum('cost_usd');

        if ($monthlySpend > config('ai.monthly_budget_alert_usd')) {
            \Notification::send(User::admins()->get(), new AIBudgetAlertNotification($monthlySpend));
        }
    }

    private function calculateCost(string $model, int $input, int $output): float
    {
        $pricing = [
            'claude-sonnet-4-5' => ['input' => 3.0, 'output' => 15.0],
            'claude-haiku-4-5'  => ['input' => 0.80, 'output' => 4.0],
        ];

        $p = $pricing[$model] ?? ['input' => 3.0, 'output' => 15.0];
        return ($input * $p['input'] + $output * $p['output']) / 1_000_000;
    }
}
```

---

## 4. Keyfiyyət Metriklər

### 4.1 Task Completion Rate

İstifadəçi AI köməyi ilə tapşırığı tamamladımı?

```php
class TaskCompletionTracker
{
    public function startTask(string $userId, string $taskType, string $sessionId): void
    {
        session()->put("ai_task_{$sessionId}", [
            'user_id'   => $userId,
            'task_type' => $taskType,
            'started_at' => now(),
        ]);
    }

    public function completeTask(string $sessionId, bool $success, string $reason = ''): void
    {
        $task = session()->get("ai_task_{$sessionId}");
        if (!$task) return;

        AITaskCompletion::create([
            'user_id'     => $task['user_id'],
            'task_type'   => $task['task_type'],
            'completed'   => $success,
            'reason'      => $reason,
            'duration_s'  => now()->diffInSeconds($task['started_at']),
            'session_id'  => $sessionId,
        ]);

        session()->forget("ai_task_{$sessionId}");
    }
}
```

**Nümunə metrik:**
```
Email classifier feature:
  Task: Email-i doğru kateqoriyaya yönləndir
  Uğur: İstifadəçi "Doğru kategorizasiya" deyir (thumbs up)
  Uğursuzluq: İstifadəçi kategoriya dəyişdirir (manual override)
  
  Task completion rate: 87% → Hədəf: >90%
```

### 4.2 LLM-as-Judge Keyfiyyət Skoru

```php
class LLMQualityJudge
{
    public function __construct(
        private readonly ClaudeService $claude,
    ) {}

    /**
     * AI cavabını başqa modellə qiymətləndir.
     * Sampling: hər 100 request-dən birini evaluate et.
     */
    public function evaluate(
        string $prompt,
        string $response,
        string $featureName,
    ): float {
        $judgePrompt = <<<PROMPT
        Aşağıdakı AI cavabını 1-5 arası qiymətləndir:

        Sual: {$prompt}
        Cavab: {$response}

        Kriteriyalar:
        - Dəqiqlik (1-5): Cavab faktiki olaraq doğrudur?
        - Faydalılıq (1-5): Cavab suala tam cavab verir?
        - Aydınlıq (1-5): Cavab aydın və anlaşılırdır?

        JSON formatında cavab ver:
        {"accuracy": 1-5, "helpfulness": 1-5, "clarity": 1-5, "overall": 1-5}
        PROMPT;

        $judgment = $this->claude->messages(
            messages: [['role' => 'user', 'content' => $judgePrompt]],
            model: 'claude-sonnet-4-5',
            temperature: 0.0,
        );

        $scores = json_decode($judgment, true);
        $overall = $scores['overall'] / 5.0;

        AIQualityLog::create([
            'feature'    => $featureName,
            'prompt'     => substr($prompt, 0, 500),
            'response'   => substr($response, 0, 500),
            'accuracy'   => $scores['accuracy'],
            'helpfulness'=> $scores['helpfulness'],
            'clarity'    => $scores['clarity'],
            'overall'    => $overall,
        ]);

        return $overall;
    }
}
```

### 4.3 User Feedback Collection

```php
// app/Http/Controllers/AIFeedbackController.php
class AIFeedbackController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'response_id' => 'required|string',
            'rating'      => 'required|in:thumbs_up,thumbs_down',
            'comment'     => 'nullable|string|max:500',
            'feature'     => 'required|string',
        ]);

        AIFeedback::create([
            'response_id' => $request->response_id,
            'user_id'     => auth()->id(),
            'rating'      => $request->rating,
            'comment'     => $request->comment,
            'feature'     => $request->feature,
        ]);

        // Thumbs down → queue'ya analiz üçün
        if ($request->rating === 'thumbs_down') {
            AnalyzeNegativeFeedback::dispatch($request->response_id);
        }

        return response()->json(['status' => 'ok']);
    }
}
```

---

## 5. Biznes Metriklər

### 5.1 Feature-Specific KPIs

| AI Feature | Primary KPI | Secondary KPI | Target |
|-----------|-------------|--------------|--------|
| Email classifier | Auto-classification rate | Manual override rate | >85% auto, <15% manual |
| Code reviewer | Review comment acceptance | Time-to-merge reduction | >60% accept, -30% time |
| Customer support bot | Self-service resolution | Escalation rate | >70% resolved, <30% escalate |
| SQL assistant | Query success rate | Time-to-query reduction | >80% success, -40% time |
| Document Q&A | Relevant answer rate | Follow-up question rate | >85% relevant, <20% follow-up |

### 5.2 North Star Metric

Bir ai feature üçün hər şeyi əhatə edən tək metrik:

```
Email classifier North Star:
  "İstifadəçi-başına gündəlik email emal vaxtı (dəqiqə)"
  
  AI feature əvvəl: 45 dəq/gün
  AI feature sonra: 12 dəq/gün
  Improvement: -73%
  
  Bu rəqəm:
  - Texniki metrikləri əhatə edir (latency yaxşı olduqda vaxt düşür)
  - Keyfiyyət metrikləri əhatə edir (yanlış classify → manual override → vaxt artır)
  - Biznes metrikaları əhatə edir (istifadəçi reallığını əks etdirir)
```

### 5.3 Cohort Analysis

```sql
-- AI feature-ı istifadə edən vs etməyən istifadəçiləri müqayisə et
SELECT
    has_ai_feature,
    AVG(emails_processed_per_day)     AS avg_emails_day,
    AVG(manual_overrides_per_day)     AS avg_overrides,
    AVG(time_in_email_minutes_per_day) AS avg_time_min,
    COUNT(*)                          AS user_count
FROM user_daily_stats
WHERE date >= CURRENT_DATE - INTERVAL '30 days'
GROUP BY has_ai_feature;
```

---

## 6. Dashboard Qurma

### 6.1 Minimum Viable Dashboard

```
AI Feature Dashboard:
┌─────────────────────────────────────────────────────────┐
│  Email Classifier — Son 7 gün                           │
├──────────────┬──────────────┬──────────────┬────────────┤
│ Requests/day │ Error rate   │ Avg latency  │ Thumbs up% │
│ 12,450       │ 0.8%         │ 2.3s         │ 84%        │
│ ↑ 12%        │ ↓ 0.2%       │ ↓ 0.4s       │ ↑ 3%      │
├──────────────┴──────────────┴──────────────┴────────────┤
│ Task completion rate: 87% ████████████████░░░ Target: 90% │
│ Daily cost: $45.20 (budget: $100/day ✓)                  │
├─────────────────────────────────────────────────────────┤
│ Quality trend (LLM judge, avg score 0-5)                 │
│ 4.2 ──────────────────────────────── 4.3 → ↑ Good      │
├─────────────────────────────────────────────────────────┤
│ Alerts: No active alerts                                 │
└─────────────────────────────────────────────────────────┘
```

### 6.2 Laravel + Grafana Metrics Export

```php
// routes/api.php — Prometheus metrics endpoint
Route::get('/metrics/ai', function () {
    $stats = Cache::remember('ai_metrics', 60, function () {
        return [
            'requests_total'  => DB::table('ai_requests')->count(),
            'errors_total'    => DB::table('ai_requests')->where('status', 'error')->count(),
            'avg_latency_ms'  => DB::table('ai_requests')->avg('duration_ms'),
            'thumbs_up_pct'   => DB::table('ai_feedback')
                ->where('rating', 'thumbs_up')
                ->count() / max(DB::table('ai_feedback')->count(), 1) * 100,
            'daily_cost_usd'  => DB::table('ai_token_usage')
                ->whereDate('created_at', today())
                ->sum('cost_usd'),
        ];
    });

    // Prometheus format
    $output = "";
    foreach ($stats as $key => $value) {
        $output .= "ai_{$key} {$value}\n";
    }

    return response($output, 200, ['Content-Type' => 'text/plain']);
});
```

---

## 7. Regression Detection

Model versiyası yeniləndikdə keyfiyyətin düşməsini avtomatik aşkar et.

```php
<?php
// app/Console/Commands/DetectQualityRegression.php

class DetectQualityRegression extends Command
{
    protected $signature   = 'ai:check-regression {--feature=all}';
    protected $description = 'Detect AI quality regression in last 24 hours';

    public function handle(): void
    {
        $features = $this->option('feature') === 'all'
            ? AIQualityLog::distinct()->pluck('feature')->toArray()
            : [$this->option('feature')];

        foreach ($features as $feature) {
            $currentAvg  = AIQualityLog::where('feature', $feature)
                ->where('created_at', '>', now()->subDay())
                ->avg('overall');

            $baselineAvg = AIQualityLog::where('feature', $feature)
                ->whereBetween('created_at', [now()->subDays(8), now()->subDays(2)])
                ->avg('overall');

            if ($baselineAvg && $currentAvg) {
                $drop = ($baselineAvg - $currentAvg) / $baselineAvg;

                if ($drop > 0.05) { // 5%+ düşüş → regression
                    $this->error("REGRESSION DETECTED: {$feature}");
                    $this->line("  Baseline: " . round($baselineAvg, 3));
                    $this->line("  Current:  " . round($currentAvg, 3));
                    $this->line("  Drop: "     . round($drop * 100, 1) . "%");

                    \Notification::send(
                        User::admins()->get(),
                        new AIQualityRegressionNotification($feature, $baselineAvg, $currentAvg),
                    );
                }
            }
        }
    }
}
```

---

## 8. Ölçmə Checklist

Yeni AI feature launch etmədən əvvəl:

```
Texniki:
  ☐ Latency tracked (P50, P95, P99)
  ☐ Error rate tracked (by type)
  ☐ Token usage tracked (cost per request)
  ☐ Cache hit rate tracked (if applicable)

Keyfiyyət:
  ☐ User feedback (thumbs up/down) UI mövcuddur
  ☐ LLM-judge sampling qurulub (100-dən birini)
  ☐ Task completion tracking mövcuddur
  ☐ Manual override tracking (nə qədər düzəldirlər)

Biznes:
  ☐ North Star metric müəyyən edilib
  ☐ Baseline (AI-siz) data alınıb
  ☐ Target metrics yazılıb (realistik)
  ☐ Cohort tracking (AI user vs control)

Monitoring:
  ☐ Dashboard qurulub
  ☐ Alertlər qurulub (latency, error rate, cost)
  ☐ Regression detection cron aktivdir
  ☐ Weekly review prozessi var
```

---

## Praktik Tapşırıqlar

### 1. AI Metrics Dashboard Qurulması
Layihənizdəki bir AI feature üçün 5 əsas metric-i müəyyən edin. Laravel-də `ai_metrics` cədvəli qurun: `feature`, `metric_name`, `metric_value`, `recorded_at`. Hər metric üçün baseline müəyyən edin (ilk 2 həftə). Grafana və ya Laravel Nova dashboard-da real-time göstərin. Hər metrik üçün target və threshold (alert həddini) müəyyən edin.

### 2. North Star Metric Seçimi
Komanda ilə workshop: AI feature-ınız üçün **bir** north star metric seçin. Bu metric business outcome-u əks etdirməlidir (task completion, not API calls). Metric-i necə ölçdüyünüzü sənədləşdirin. 30 günlük trend üçün baseline qurun. Hər sprint review-ında bu metric-i report edin. İkinci ay: baseline-a nisbətən dəyişimi ölçün.

### 3. Regression Alert Sistemi
Nightly cron job: 20 benchmark sorğusunu işlədin, ortalama keyfiyyət score-u hesablayın. Son 7 günün ortalama score-u ilə müqayisə edin. `>5%` azalma varsa Slack alert göndərin, automatik `regression_incidents` cədvəlinə yazın. False positive azaltmaq üçün minimum 3 ardıcıl gecə regression olduqda eskalasiya edin.

## Əlaqəli Mövzular

- [03-ai-feature-economics.md](03-ai-feature-economics.md) — Unit economics
- [../08-production/03-llm-observability.md](../08-production/03-llm-observability.md) — LLM observability tools
- [../08-production/07-model-drift-quality-monitoring.md](../08-production/07-model-drift-quality-monitoring.md) — Drift detection
- [../07-workflows/07-ai-ab-testing.md](../07-workflows/07-ai-ab-testing.md) — A/B testing AI features
