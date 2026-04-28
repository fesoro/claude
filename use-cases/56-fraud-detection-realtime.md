# Real-time Fraud Detection (Payment) (Lead)

## Problem
- E-commerce: 10k payment/saat
- Fraudulent transaction-ları (stolen card, account takeover) detect et
- < 200ms qərar (UX)
- False positive minimal (legitimate user-i blok etmə)
- Audit trail (compliance)

---

## Həll: Multi-layer rule engine + ML score + manual review

```
Payment Request
      ↓
┌─────────────────────┐
│ Layer 1: Hard Rules │  ← Velocity (5+ payment / 1 dəq), blacklist
│ < 5ms               │  → BLOCK if matched
└─────────────────────┘
      ↓
┌─────────────────────┐
│ Layer 2: Risk Score │  ← Sign-up age, IP risk, device fingerprint
│ < 50ms              │  → 0-100 score
└─────────────────────┘
      ↓
┌─────────────────────┐
│ Layer 3: ML Model   │  ← Behavioral anomaly
│ < 100ms             │  → Confidence 0-1
└─────────────────────┘
      ↓
  Decision Matrix
      ↓
  ALLOW / CHALLENGE / BLOCK / MANUAL_REVIEW
```

---

## 1. Hard rules (Layer 1)

```php
<?php
class HardRuleEngine
{
    public function check(PaymentAttempt $attempt): ?FraudDecision
    {
        // 1. Blacklist (card, IP, email, device)
        if ($this->isBlacklisted($attempt)) {
            return FraudDecision::block('BLACKLIST_MATCH');
        }
        
        // 2. Velocity (rapid attempts)
        $recentAttempts = Redis::zCount(
            "payments:user:{$attempt->user_id}",
            time() - 60,
            time()
        );
        if ($recentAttempts >= 5) {
            return FraudDecision::block('VELOCITY_USER_1MIN');
        }
        
        $cardAttempts = Redis::zCount(
            "payments:card:" . hash('sha256', $attempt->card_last4),
            time() - 3600,
            time()
        );
        if ($cardAttempts >= 10) {
            return FraudDecision::block('VELOCITY_CARD_1HOUR');
        }
        
        // 3. Geo mismatch (card BIN vs IP country)
        $cardCountry = $this->cardBinCountry($attempt->card_bin);
        $ipCountry = $this->geoIp($attempt->ip);
        if ($cardCountry !== $ipCountry && !$this->isVpn($attempt->ip)) {
            // Soft signal — block etmə, score artır
        }
        
        // 4. Suspicious amount
        if ($attempt->amount > 5000 && $attempt->user->avg_order < 100) {
            return FraudDecision::challenge('AMOUNT_ANOMALY');
        }
        
        return null;   // hard rule keçdi, sonrakı layer-ə
    }
    
    private function isBlacklisted(PaymentAttempt $a): bool
    {
        return Blacklist::where(function ($q) use ($a) {
            $q->where('value', $a->ip)
              ->orWhere('value', $a->email)
              ->orWhere('value', hash('sha256', $a->card_number))
              ->orWhere('value', $a->device_id);
        })->where('expires_at', '>', now())->exists();
    }
}
```

---

## 2. Risk score (Layer 2)

```php
<?php
class RiskScoreEngine
{
    public function score(PaymentAttempt $attempt): int
    {
        $score = 0;
        
        // Account age
        $accountAgeDays = $attempt->user->created_at->diffInDays(now());
        if ($accountAgeDays < 1)   $score += 30;
        if ($accountAgeDays < 7)   $score += 15;
        
        // Email reputation
        if ($this->isDisposableEmail($attempt->user->email)) $score += 25;
        if (!$attempt->user->email_verified_at)              $score += 10;
        
        // IP reputation
        $ipScore = $this->ipReputationScore($attempt->ip);
        $score += $ipScore;     // 0-30 typically
        
        // Device fingerprint
        if ($this->isNewDevice($attempt->device_id, $attempt->user_id)) {
            $score += 10;
        }
        
        $devicePaymentCount = Redis::sCard("device:cards:{$attempt->device_id}");
        if ($devicePaymentCount > 5) $score += 20;   // 1 device, 5+ card → şübhəli
        
        // Time of day anomaly (3-6 AM yüksək risk)
        $hour = (int) date('G');
        if ($hour >= 3 && $hour <= 6) $score += 5;
        
        // VPN / Tor
        if ($this->isVpn($attempt->ip))  $score += 15;
        if ($this->isTor($attempt->ip))  $score += 30;
        
        // BIN country vs IP country
        if ($this->cardBinCountry($attempt->card_bin) !== $this->geoIp($attempt->ip)) {
            $score += 20;
        }
        
        // User behavior anomaly
        if ($attempt->amount > $attempt->user->avg_order * 5) {
            $score += 15;
        }
        
        return min($score, 100);
    }
    
    private function ipReputationScore(string $ip): int
    {
        // External service (MaxMind, IPQualityScore)
        return Cache::remember("ip:reputation:$ip", 86400, function () use ($ip) {
            $response = Http::get("https://api.ipqualityscore.com/json/ip/{$ip}", [
                'key' => config('services.ipqs.key'),
            ]);
            
            $data = $response->json();
            return (int) ($data['fraud_score'] ?? 0);   // 0-100
        });
    }
}
```

---

## 3. ML model integration (Layer 3)

```php
<?php
class FraudMLClient
{
    public function __construct(private HttpClient $http) {}
    
    public function predict(PaymentAttempt $attempt): float
    {
        // Feature engineering
        $features = [
            'amount'              => $attempt->amount,
            'account_age_days'    => $attempt->user->created_at->diffInDays(now()),
            'past_orders_30d'     => $attempt->user->orders()->where('created_at', '>=', now()->subDays(30))->count(),
            'avg_order_amount'    => $attempt->user->avg_order,
            'distinct_cards_30d'  => $attempt->user->payments()->distinct('card_last4')->count(),
            'distinct_ips_30d'    => $attempt->user->logins()->distinct('ip')->count(),
            'time_of_day'         => (int) date('G'),
            'day_of_week'         => (int) date('w'),
            'is_weekend'          => (int) date('w') >= 5,
            'item_count'          => $attempt->cart->items->count(),
            'category_diversity'  => $attempt->cart->items->pluck('category_id')->unique()->count(),
            'shipping_billing_same' => (int) ($attempt->shipping_address === $attempt->billing_address),
        ];
        
        // Inference API (TensorFlow Serving, custom)
        try {
            $response = $this->http->post('http://ml-fraud:8501/v1/models/fraud:predict', [
                'json' => ['instances' => [$features]],
                'timeout' => 0.1,    // 100ms
            ]);
            
            return (float) $response->json('predictions.0.0');   // 0-1
        } catch (\Throwable $e) {
            // ML service down → score-əsaslı qərar (degradation)
            Log::warning('ML service down, fallback', ['exception' => $e]);
            return 0.5;   // neutral
        }
    }
}
```

---

## 4. Decision orchestrator

```php
<?php
class FraudDecisionService
{
    public function __construct(
        private HardRuleEngine $rules,
        private RiskScoreEngine $score,
        private FraudMLClient $ml,
    ) {}
    
    public function decide(PaymentAttempt $attempt): FraudDecision
    {
        $start = microtime(true);
        
        // Layer 1
        if ($block = $this->rules->check($attempt)) {
            return $this->record($attempt, $block, microtime(true) - $start);
        }
        
        // Layer 2
        $score = $this->score->score($attempt);
        
        // Layer 3 (yalnız score 30+ olanlarda — cost saving)
        $mlScore = $score >= 30 ? $this->ml->predict($attempt) : null;
        
        // Decision matrix
        $decision = $this->matrix($score, $mlScore);
        
        return $this->record($attempt, $decision, microtime(true) - $start);
    }
    
    private function matrix(int $ruleScore, ?float $mlScore): FraudDecision
    {
        // Composite
        $combined = $mlScore !== null
            ? $ruleScore * 0.6 + $mlScore * 100 * 0.4
            : $ruleScore;
        
        return match (true) {
            $combined < 30  => FraudDecision::allow('LOW_RISK', $combined),
            $combined < 60  => FraudDecision::challenge('MEDIUM_RISK', $combined),
            $combined < 85  => FraudDecision::manualReview('HIGH_RISK', $combined),
            default         => FraudDecision::block('VERY_HIGH_RISK', $combined),
        };
    }
    
    private function record(PaymentAttempt $a, FraudDecision $d, float $duration): FraudDecision
    {
        FraudCheckLog::create([
            'attempt_id'    => $a->id,
            'user_id'       => $a->user_id,
            'decision'      => $d->action,
            'reason'        => $d->reason,
            'score'         => $d->score,
            'duration_ms'   => $duration * 1000,
            'features'      => $a->toArray(),
        ]);
        
        Metrics::increment('fraud.decisions', ['action' => $d->action]);
        
        return $d;
    }
}
```

---

## 5. Challenge (3DS, OTP)

```php
<?php
class CheckoutController
{
    public function pay(Request $req, FraudDecisionService $fraud): JsonResponse
    {
        $attempt = $this->buildAttempt($req);
        $decision = $fraud->decide($attempt);
        
        return match ($decision->action) {
            FraudAction::Allow => $this->processPayment($attempt),
            FraudAction::Challenge => $this->triggerChallenge($attempt),
            FraudAction::ManualReview => $this->queueForReview($attempt),
            FraudAction::Block => $this->refuse($attempt),
        };
    }
    
    private function triggerChallenge(PaymentAttempt $a): JsonResponse
    {
        // 3D Secure 2.0
        $threedsUrl = $this->paymentGateway->initiate3DS($a);
        return response()->json([
            'status' => 'challenge_required',
            'redirect_url' => $threedsUrl,
        ]);
    }
}
```

---

## 6. Feedback loop (model retraining)

```php
<?php
// Chargeback gəldikdə → label "fraud"
class ChargebackHandler
{
    public function handle(Chargeback $cb): void
    {
        $payment = Payment::find($cb->payment_id);
        
        // ML training data-ya əlavə
        FraudTrainingData::create([
            'payment_id' => $payment->id,
            'label'      => 'fraud',
            'features'   => $payment->fraud_check_features,
        ]);
        
        // Auto-blacklist (severity-ə görə)
        if ($cb->reason_code === '4837') {   // Fraudulent transaction
            Blacklist::create([
                'type'       => 'card',
                'value'      => hash('sha256', $payment->card_number),
                'reason'     => "Chargeback {$cb->reason_code}",
                'expires_at' => now()->addYear(),
            ]);
        }
    }
}

// Daily/weekly model retraining (Airflow / cron)
// Pipeline: training_data → feature engineering → train → validate → deploy
```

---

## 7. Manual review queue

```php
<?php
// Risk team-i üçün dashboard
Route::get('/admin/fraud-review', function () {
    $cases = FraudReviewQueue::with(['user', 'attempt'])
        ->where('status', 'pending')
        ->orderByDesc('score')
        ->paginate(20);
    
    return view('admin.fraud.queue', compact('cases'));
});

class ReviewerActionController
{
    public function approve(int $caseId, Request $req): RedirectResponse
    {
        $case = FraudReviewQueue::findOrFail($caseId);
        $case->update([
            'status'      => 'approved',
            'reviewer_id' => auth()->id(),
            'reviewed_at' => now(),
            'note'        => $req->note,
        ]);
        
        // Resume payment
        $this->paymentGateway->charge($case->attempt);
        
        // Training data — false positive
        FraudTrainingData::create([
            'attempt_id' => $case->attempt_id,
            'label'      => 'legitimate',
            'features'   => $case->attempt->features,
        ]);
        
        return back();
    }
    
    public function reject(int $caseId, Request $req): RedirectResponse
    {
        $case = FraudReviewQueue::findOrFail($caseId);
        $case->update(['status' => 'rejected', 'reviewer_id' => auth()->id()]);
        
        // True positive — training
        FraudTrainingData::create([
            'attempt_id' => $case->attempt_id,
            'label'      => 'fraud',
            'features'   => $case->attempt->features,
        ]);
        
        return back();
    }
}
```

---

## 8. Performance & metrics

```
Hard rules:           2-5ms
Risk score:           20-50ms (DB queries, IP API cached)
ML inference:         50-100ms (TensorFlow Serving)
Total SLO:            < 200ms p99

Throughput:
  10k payment/saat = 2.8 req/sec → tək worker yeterli
  Peak (Black Friday): 50/sec → 5 worker

Metrics (Grafana):
  - decisions / sec (by action)
  - latency p50/p95/p99
  - challenge acceptance rate
  - manual review backlog
  - false positive rate (post-review)
  - chargeback rate (post-payment)
```

---

## 9. Pitfalls

```
❌ Latency budget aşmaq → checkout abandon
   ✓ Async ML if degradation, hard timeout 100ms

❌ False positive yüksək → revenue loss + user frustration
   ✓ Tune threshold, A/B test, manual review safety valve

❌ Single algorithm — adversaries adapt
   ✓ Multi-layer (rules + ML + manual)

❌ Privacy (PII in features)
   ✓ Hash card, salt PII, GDPR audit

❌ Model drift — accuracy düşür
   ✓ Daily/weekly retraining + monitoring

❌ Adversarial attack — fraud-er ML feature-larını öyrənir
   ✓ Hidden features, threshold randomization
```

---

## Problem niyə yaranır?

Ödəniş sistemi qurarkən ən sadə yanaşma "kart məlumatlarını al, bank-a göndər, nəticəni qaytar" kimi görünür. Bu yanaşma texniki baxımdan işləyir, lakin fraud-a qarşı heç bir müqavimət göstərmir. Fraud yalnız ödəniş tamamlandıqdan sonra aşkar olunarsa — chargeback gəlir, bank pulu geri alır, merchant komissiya itirir. Problem burada deyil. Problem ondadır ki, çoğunlukla ödəniş uğurla keçdikdən sonra real malı artıq göndərmisən, xidməti aktivləşdirmisən, ya da mükafat balansını artırmısan. Pulu geri almaq üçün 30-60 gün gözləyirsən, bu müddətdə iş proseslərin pozulur.

10,000 payment/saat dedikdə bu saniyədə təxminən 2.8 request deməkdir, lakin peak saatlar (axşam 19-23, kampaniya anları) bu rəqəm 10-20x arta bilər. Bu yükdə hər yoxlama üçün əlavə 200ms sərfiyyat demək olar ki, checkout abandon rate-ni artırır — tədqiqatlar göstərir ki, 1 saniyəlik əlavə gecikmə e-commerce-də 7% konversiya düşkünlüyünə səbəb olur. Buna görə fraud qərarı asynchronous edə bilməzsən: ödəniş başlamazdan əvvəl qərar verilməli, bu qərar isə < 200ms daxilində hazır olmalıdır. Gecikmiş yoxlama ya checkout-u blok edir, ya da ödəniş artıq keçdikdən sonra gəlir — bu da post-payment reversalı deməkdir ki, praktiki olaraq daha bahalıdır.

Yalnız rule-based sistem (velocity check, blacklist, country mismatch) 2019-cu illərdə yetərli idi. Müasir fraud-çılar bu qaydaları öyrənirlər: əgər 1 dəqiqədə 5+ ödəniş blok edirsə, 4 ödəniş edir, 2 dəqiqə gözləyib 4 daha edir. Stolen card-lardan kiçik məbləğ "test charge" edirlər (məs: $1.00) — keçirsə böyük məbləğ cəhdi edirlər. VPN-dən istifadə edir, Tor exit node-larından keçirlər, geoblock-u keçirlər. Bu adaptasiya qabiliyyəti rule-only sistemi çox tez devalvasiya edir. ML modeli isə bu pattern-ları statistik olaraq öyrənir — fraud-çı davranışı yeniləndikcə, model yeni training data ilə yenilənir. Rule-ların əvvəlcədən yazılması lazımdır; ML model isə "görünməmiş" kombinasiyaları da risk kimi qiymətləndirə bilir.

---

## Kompensasiya və Manual Review Flow (PHP)

Avtomatik qərar sistemi hər zaman 100% dəqiq deyil. Yüksək risk score-lu, lakin legitimik ola bilən əməliyyatlar üçün insan qərarı tələb olunur. Bu flow fraud case-in yaradılmasından reviewer-in qərarına qədər tam lifecycle-ı əhatə edir.

### Migration: fraud_cases cədvəli

```php
// database/migrations/2024_xx_xx_create_fraud_cases_table.php
Schema::create('fraud_cases', function (Blueprint $table) {
    $table->id();
    $table->ulid('case_ref')->unique();           // HR-2024-00123 kimi external ref
    $table->foreignId('payment_attempt_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->constrained();
    $table->foreignId('reviewer_id')->nullable()->constrained('users');
    $table->float('risk_score');                   // 0-100
    $table->string('reason');                      // VELOCITY_USER_1MIN, HIGH_RISK, ...
    $table->string('status')->default('pending_review');
    // pending_review | under_review | approved | rejected | escalated | appealed
    $table->string('priority')->default('normal'); // normal | high | critical
    $table->text('reviewer_note')->nullable();
    $table->text('appeal_reason')->nullable();
    $table->timestamp('assigned_at')->nullable();
    $table->timestamp('resolved_at')->nullable();
    $table->timestamp('sla_deadline')->nullable();  // high priority: 1h, normal: 24h
    $table->json('evidence')->nullable();           // screenshot, device info, ...
    $table->timestamps();

    $table->index(['status', 'priority', 'created_at']); // review queue sorğusu
    $table->index(['user_id', 'status']);
});
```

### FraudCaseStatus Enum

```php
// app/Enums/FraudCaseStatus.php
enum FraudCaseStatus: string
{
    case PENDING_REVIEW  = 'pending_review';
    case UNDER_REVIEW    = 'under_review';
    case APPROVED        = 'approved';    // legitimate — ödənişi davam et
    case REJECTED        = 'rejected';   // fraud — blokla
    case ESCALATED       = 'escalated';  // senior reviewer-ə göndər
    case APPEALED        = 'appealed';   // user etiraz etdi

    public function isTerminal(): bool
    {
        return in_array($this, [self::APPROVED, self::REJECTED]);
    }

    public function allowedTransitions(): array
    {
        return match ($this) {
            self::PENDING_REVIEW => [self::UNDER_REVIEW],
            self::UNDER_REVIEW   => [self::APPROVED, self::REJECTED, self::ESCALATED],
            self::ESCALATED      => [self::APPROVED, self::REJECTED],
            self::REJECTED       => [self::APPEALED],
            self::APPEALED       => [self::APPROVED, self::REJECTED],
            self::APPROVED       => [],
        };
    }
}
```

### FraudCaseService

```php
// app/Services/FraudCaseService.php
class FraudCaseService
{
    public function __construct(
        private FraudTrainingDataRepository $trainingRepo,
        private NotificationService $notifications,
    ) {}

    /**
     * Yüksək risk score-lu payment attempt-i manual review queue-ya göndər.
     */
    public function queueForReview(
        PaymentAttempt $attempt,
        string $reason,
        float $riskScore
    ): FraudCase {
        $priority   = $riskScore > 80 ? 'high' : 'normal';
        $slaDeadline = $priority === 'high'
            ? now()->addHour()
            : now()->addHours(24);

        $case = FraudCase::create([
            'case_ref'           => (string) Str::ulid(),
            'payment_attempt_id' => $attempt->id,
            'user_id'            => $attempt->user_id,
            'risk_score'         => $riskScore,
            'reason'             => $reason,
            'status'             => FraudCaseStatus::PENDING_REVIEW,
            'priority'           => $priority,
            'sla_deadline'       => $slaDeadline,
            'evidence'           => $this->collectEvidence($attempt),
        ]);

        // Reviewer-lərə bildiriş — yüksək prioritet anlıq xəbərdar edilir
        if ($priority === 'high') {
            $this->notifications->notifyFraudTeam($case, urgent: true);
        }

        // Avtomatik assign — ən az iş yükü olan reviewer-ə
        $this->autoAssign($case);

        return $case;
    }

    /**
     * Reviewer case-i öz üzərinə götürür (claim).
     */
    public function claimCase(FraudCase $case, User $reviewer): FraudCase
    {
        $this->assertTransition($case, FraudCaseStatus::UNDER_REVIEW);

        $case->update([
            'status'      => FraudCaseStatus::UNDER_REVIEW,
            'reviewer_id' => $reviewer->id,
            'assigned_at' => now(),
        ]);

        return $case->fresh();
    }

    /**
     * Reviewer ödənişi legitimate olaraq təsdiqləyir.
     * Ödəniş davam edir, training data-ya "legitimate" kimi əlavə olunur.
     */
    public function approve(FraudCase $case, User $reviewer, string $note): FraudCase
    {
        $this->assertTransition($case, FraudCaseStatus::APPROVED);
        $this->assertReviewer($case, $reviewer);

        DB::transaction(function () use ($case, $reviewer, $note) {
            $case->update([
                'status'        => FraudCaseStatus::APPROVED,
                'reviewer_id'   => $reviewer->id,
                'reviewer_note' => $note,
                'resolved_at'   => now(),
            ]);

            // Ödənişi davam etdir
            ProcessApprovedPaymentJob::dispatch($case->paymentAttempt);

            // ML feedback — false positive olaraq işarələ
            $this->trainingRepo->addLabel(
                attempt: $case->paymentAttempt,
                label: 'legitimate',
                source: 'manual_review',
            );

            // User-a bildiriş — uzun gözlədilmişsə üzr bildir
            if ($case->created_at->diffInHours(now()) > 2) {
                $this->notifications->notifyUserPaymentApproved($case->user, $case);
            }
        });

        return $case->fresh();
    }

    /**
     * Reviewer fraud kimi rədd edir.
     * Ödəniş bloklanır, kart/IP/email blacklist-ə düşür (severity-ə görə).
     */
    public function reject(FraudCase $case, User $reviewer, string $note, bool $blacklist = false): FraudCase
    {
        $this->assertTransition($case, FraudCaseStatus::REJECTED);
        $this->assertReviewer($case, $reviewer);

        DB::transaction(function () use ($case, $reviewer, $note, $blacklist) {
            $case->update([
                'status'        => FraudCaseStatus::REJECTED,
                'reviewer_id'   => $reviewer->id,
                'reviewer_note' => $note,
                'resolved_at'   => now(),
            ]);

            // ML feedback — true positive
            $this->trainingRepo->addLabel(
                attempt: $case->paymentAttempt,
                label: 'fraud',
                source: 'manual_review',
            );

            if ($blacklist) {
                $this->blacklistEntities($case->paymentAttempt);
            }

            // User-a bildiriş — fraud şübhəsi olduğunu açıqlamadan rədd
            $this->notifications->notifyUserPaymentRejected($case->user, $case);
        });

        return $case->fresh();
    }

    /**
     * Senior reviewer-ə eskalasiya (şübhəli, lakin qərar vermək çətin olduqda).
     */
    public function escalate(FraudCase $case, User $reviewer, string $reason): FraudCase
    {
        $this->assertTransition($case, FraudCaseStatus::ESCALATED);

        $case->update([
            'status'        => FraudCaseStatus::ESCALATED,
            'reviewer_note' => $reason,
            'sla_deadline'  => now()->addHours(4),  // Eskalasiyada SLA sıxılır
        ]);

        $this->notifications->notifyFraudTeamLead($case, urgent: true);

        return $case->fresh();
    }

    /**
     * User rədd qərarına etiraz edir (appeal).
     * Otomatik olaraq yenidən review queue-ya düşür.
     */
    public function appeal(FraudCase $case, string $userReason): FraudCase
    {
        $this->assertTransition($case, FraudCaseStatus::APPEALED);

        $case->update([
            'status'        => FraudCaseStatus::APPEALED,
            'appeal_reason' => $userReason,
            'reviewer_id'   => null,        // Öncəki reviewer-i sıfırla
            'sla_deadline'  => now()->addHours(48),
        ]);

        // Fərqli reviewer-ə assign et (bias önləmək üçün)
        $this->autoAssign($case, excludeReviewer: $case->reviewer_id);

        $this->notifications->notifyFraudTeam($case, urgent: false);

        return $case->fresh();
    }

    // ─── Private helpers ──────────────────────────────────────

    private function collectEvidence(PaymentAttempt $attempt): array
    {
        return [
            'ip'              => $attempt->ip,
            'device_id'       => $attempt->device_id,
            'user_agent'      => $attempt->user_agent,
            'card_bin'        => $attempt->card_bin,
            'card_last4'      => $attempt->card_last4,
            'billing_country' => $attempt->billing_country,
            'ip_country'      => $attempt->ip_country,
            'account_age_days'=> $attempt->user->created_at->diffInDays(now()),
            'past_fraud_count'=> FraudCase::where('user_id', $attempt->user_id)
                                    ->where('status', FraudCaseStatus::REJECTED)
                                    ->count(),
        ];
    }

    private function autoAssign(FraudCase $case, ?int $excludeReviewer = null): void
    {
        // Fraud team role-una sahib, ən az açıq case-i olan user
        $reviewer = User::role('fraud_reviewer')
            ->when($excludeReviewer, fn($q) => $q->where('id', '!=', $excludeReviewer))
            ->withCount(['fraudCases' => fn($q) => $q->whereIn('status', [
                FraudCaseStatus::PENDING_REVIEW,
                FraudCaseStatus::UNDER_REVIEW,
            ])])
            ->orderBy('fraud_cases_count')
            ->first();

        if ($reviewer) {
            $case->update([
                'reviewer_id' => $reviewer->id,
                'assigned_at' => now(),
            ]);
        }
    }

    private function assertTransition(FraudCase $case, FraudCaseStatus $target): void
    {
        if (!in_array($target, $case->status->allowedTransitions())) {
            throw new InvalidStateTransitionException(
                "Keçid mümkün deyil: {$case->status->value} → {$target->value}"
            );
        }
    }

    private function assertReviewer(FraudCase $case, User $reviewer): void
    {
        if ($case->reviewer_id && $case->reviewer_id !== $reviewer->id) {
            throw new UnauthorizedReviewerException(
                "Bu case başqa reviewer-ə assign edilib."
            );
        }
    }

    private function blacklistEntities(PaymentAttempt $attempt): void
    {
        $entries = [
            ['type' => 'card',   'value' => hash('sha256', $attempt->card_number)],
            ['type' => 'email',  'value' => $attempt->user->email],
            ['type' => 'device', 'value' => $attempt->device_id],
        ];

        foreach ($entries as $entry) {
            Blacklist::firstOrCreate(
                ['type' => $entry['type'], 'value' => $entry['value']],
                ['reason' => "Manual review rejection: {$attempt->id}", 'expires_at' => now()->addYears(2)]
            );
        }
    }
}
```

### Manual Review Admin Panel Route-ları

```php
// routes/web.php — fraud review dashboard
Route::middleware(['auth', 'role:fraud_reviewer'])->prefix('admin/fraud')->group(function () {

    // Queue — pending və under review case-lər
    Route::get('/queue', function () {
        $cases = FraudCase::with(['user', 'paymentAttempt', 'reviewer'])
            ->whereNotIn('status', [FraudCaseStatus::APPROVED, FraudCaseStatus::REJECTED])
            ->orderByRaw("FIELD(priority, 'critical', 'high', 'normal')")
            ->orderBy('sla_deadline')
            ->paginate(20);

        return view('admin.fraud.queue', compact('cases'));
    })->name('fraud.queue');

    // Case detail
    Route::get('/{case}', [FraudReviewController::class, 'show'])->name('fraud.show');

    // Actions
    Route::post('/{case}/claim',    [FraudReviewController::class, 'claim'])->name('fraud.claim');
    Route::post('/{case}/approve',  [FraudReviewController::class, 'approve'])->name('fraud.approve');
    Route::post('/{case}/reject',   [FraudReviewController::class, 'reject'])->name('fraud.reject');
    Route::post('/{case}/escalate', [FraudReviewController::class, 'escalate'])->name('fraud.escalate');
});

// User appeal — autentifikasiya olunmuş user öz rədd edilmiş ödənişinə etiraz edə bilər
Route::middleware('auth')->post('/payments/{case}/appeal', [PaymentAppealController::class, 'store'])
    ->name('payment.appeal');
```

### SLA Monitoring Job

```php
// app/Jobs/FraudCaseSlaMonitorJob.php — hər 15 dəqiqədən bir işlər
class FraudCaseSlaMonitorJob implements ShouldQueue
{
    public function handle(NotificationService $notifications): void
    {
        // SLA-sı keçmiş, hələ həll olunmamış case-lər
        $overdueCases = FraudCase::whereNotIn('status', [
                FraudCaseStatus::APPROVED,
                FraudCaseStatus::REJECTED,
            ])
            ->where('sla_deadline', '<', now())
            ->get();

        foreach ($overdueCases as $case) {
            Log::warning("Fraud case SLA breached", [
                'case_ref'    => $case->case_ref,
                'priority'    => $case->priority,
                'overdue_min' => $case->sla_deadline->diffInMinutes(now()),
            ]);

            $notifications->notifyFraudTeamLead($case, urgent: true);

            // Eskalasiya etməmişsə, avtomatik eskalasiya
            if ($case->status !== FraudCaseStatus::ESCALATED) {
                $case->update(['status' => FraudCaseStatus::ESCALATED]);
            }
        }
    }
}
```

---

## Trade-offs

### False Positive vs False Negative

| Yanaşma | Üstünlük | Risk | Nə zaman |
|---------|----------|------|-----------|
| **Threshold aşağı (risk < 40 → allow)** | False positive az, user experience yaxşı | False negative çox — fraud keçir, chargeback artır | Yeni bazara girişdə, brand reputasiyası ön plandadırsa |
| **Threshold yüksək (risk > 60 → block)** | False negative az — fraud çox tutulur | False positive artır — legitimate user-lər blok edilir, revenue itirilir | High-value digital goods, irreversible əməliyyatlar |
| **Manual review zone geniş (40-80)** | Balans — qeyri-müəyyən case-lər insana gedir | Ops xərci artır, SLA-ya uyğunluq çətin olur | Regulated sektorlar (banking, crypto) |
| **Challenge (3DS) istifadəsi** | False positive əvəzinə friction əlavə edir — fraud blok, real user keçir | Conversion rate düşür (~5-15% abandon) | E-commerce-də orta risk zone üçün ideal |

### Threshold Tənzimlənməsi

Threshold çox aşağı təyin edilərsə (məs: risk > 30 → block): legitimate user-lərin 15-20%-i bloklanır, müştəri şikayəti artır, manual review queue daşır, fraud team yanır. Threshold çox yüksək təyin edilərsə (məs: risk > 90 → block): yalnız ən kobud fraud tutulur, sophisticated fraud keçir, chargeback rate artır, bank penalty gəlir.

Optimal threshold müəyyən etmək üçün A/B test tətbiq edilir: trafiki split et, fərqli threshold-lar ilə test et, 2-4 həftə sonra chargeback rate + false positive rate-i müqayisə et. Tipik hədəf: **chargeback rate < 0.5%, false positive rate < 1-2%**.

### Speed vs Accuracy

| Yanaşma | Üstünlük | Risk | Nə zaman |
|---------|----------|------|-----------|
| **Yalnız hard rules (< 10ms)** | Ən sürətli, deterministik | Adaptiv fraud keçir | Layer 1 olaraq mütləq lazımdır |
| **Rule + risk score (< 60ms)** | Balanslaşdırılmış | ML olmadan edge case-ləri çatır | Çox ödəniş olmayan sistem |
| **Rule + score + ML (< 200ms)** | Ən dəqiq | Latency yüksək, ML service dependency | Yüksək həcmli production sistem |
| **Async ML (post-payment check)** | Sıfır latency əlavəsi | Real-time blok etmək mümkün deyil, yalnız refund | Düşük risk kategoriyalar (subscription renewal) |

### Rules vs ML

| Yanaşma | Üstünlük | Risk | Nə zaman |
|---------|----------|------|-----------|
| **Yalnız rules** | Şəffaf, debug asan, GDPR-compliant | Adaptasiya yavaş, edge case-ləri tutmur | Startup mərhələsi, az data |
| **Yalnız ML** | Pattern recognition güclü | Black box, drift, training data lazım | Milyonlarla transaction olan sistem |
| **Hibrid (rule + ML)** | Hər ikisinin üstünlüyü | Mürəkkəblik artır, iki sistemi maintain etmək lazım | Production fraud sistemi üçün standart |

---

## Anti-patternlər

**1. Yalnız rule-based sistem**
Hard-coded qaydalar (velocity, blacklist, geo) başlanğıc üçün faydalıdır, lakin sophisticated fraud-çılar bu qaydaları öyrənib uyğunlaşırlar. Velocity 5/dəq-dirsə, 4 ödəniş edib gözləyirlər. Blacklist varsa, yeni kart/IP istifadə edirlər. Rule-lar statik qalarkən fraud dinamik inkişaf edir. **Həll:** Multi-layer yanaşma — rule-ların üstünə ML əlavə et, hər ikisi biri-birini tamamlasın.

**2. ML modelini feedback loop olmadan saxlamaq**
Model bir dəfə train edilib production-a deploy edilir, lakin artıq yenilənmir. Fraud pattern-lar zamanla dəyişir (yeni attack vector-lar, yeni cihaz tipləri, yeni geo), model isə köhnə data ilə işləyir — accuracy tədricən aşağı düşür (model drift). **Həll:** Chargeback-ları, manual review nəticələrini training data-ya əlavə et; həftəlik/aylıq model retraining pipeline qur; accuracy metric-lərini Grafana-da izlə.

**3. False positive-ləri tracking etməmək**
Sistem neçə legitimate user-i bloklandığını bilmirsən. Manual review-dan "approved" olaraq qayıdan case-lər xüsusi qeyd edilmir, training data-ya əlavə olunmur. Nəticə: threshold tənzimləmək üçün data yoxdur, model eyni false positive pattern-larını təkrar edir. **Həll:** Hər approved case-i "legitimate" label-ı ilə training repo-ya yaz, aylıq false positive rate hesabla, threshold review-u calendar-a əlavə et.

**4. Bütün yüksək risk əməliyyatları birbaşa block etmək**
Risk score 70+ olan hər ödənişi block etmək sadədir, lakin bu score-ların 40-60%-i legitimate user-lərə aid ola bilər (yeni cihaz, VPN, gecə vaxtı). Legitimate user-in ödənişi uğursuz olduqda şirkəti tərk edir, rəqibə keçir. **Həll:** Block əvəzinə challenge (3DS) + manual review zone tətbiq et; yalnız çox yüksək score-larda (85+) avtomatik block et.

**5. Audit trail olmadan qərar qəbul etmək**
Fraud qərarları — xüsusilə block — log edilmir, ya da yalnız minimal məlumatla (sadəcə "BLOCKED") saxlanılır. PCI DSS compliance, bank audit, user dispute zamanı "nə əsasla bloklandı?" sualına cavab vermək mümkün olmur. **Həll:** Hər qərarla birlikdə risk score, hansi qaydanın işlədiyini, feature dəyərlərini, reviewer qeydlərini audit log-a yaz; bu log-lar silkinə bilməz (append-only).

**6. Fraud detection-ı synchronous kritik path-a daxil etmək**
ML service down olduqda (deploy, OOM kill, network partition) bütün ödənişlər fail olur. 100ms timeout ML inference üçün kifayət etmir, ödəniş service-i timeout alır, transaction rollback olur. **Həll:** Circuit breaker tətbiq et; ML service down olduqda rule + score əsaslı fallback qərarı ver (neutral 0.5 return); latency budget-i hər layer üçün ayrıca təyin et.

**7. Yalnız real-time signal-lara etibar etmək**
Hazırkı ödəniş üçün yalnız anlıq məlumatlar (bu IP, bu kart, bu məbləğ) yoxlanılır. Lakin fraud pattern-lar tez-tez historical context tələb edir: bu user son 30 gündə neçə fərqli kart istifadə etdi? Bu device əvvəllər başqa hesablarla əlaqəli olubmu? Bu email bir neçə hesabda istifadə edilibmi? **Həll:** Feature engineering-ə 7, 30, 90 günlük aggregation-ları daxil et; Redis-də precomputed rolling counters saxla (hər ödənişdə DB-dən yenidən saymaq əvəzinə).

---

## Interview Sualları və Cavablar

**S: False positive vs false negative trade-off-u necə balanslaşdırırsınız?**

False positive — legitimate user-i fraud kimi işarələmək — birbaşa revenue itkisi, user churn, müştəri şikayəti deməkdir. False negative — real fraud-u buraxmaq — chargeback, bank penalty, brand zərəri deməkdir. İkisi arasında optimal nöqtəni tapmaq üçün threshold A/B test-i aparıram: eyni anda fərqli segment-lərə fərqli threshold tətbiq edib 2-4 həftəlik chargeback rate + manual review rate-i müqayisə edirəm. Hər biznesin risk iştahası fərqlidir — digital goods üçün false negative daha bahalıdır (irreversible), fiziki mal üçün false positive daha bahalı ola bilər (return cost çoxdur). Mən əlavə olaraq challenge zone (3DS) istifadə edirəm ki, block əvəzinə legitimate user keçə bilsin.

**S: Fraud detection sisteminizi necə test edərdiniz?**

Üç səviyyədə test strategiyam var. Unit test: hər layer ayrıca test edilir — HardRuleEngine için müxtəlif velocity scenario-ları, RiskScoreEngine üçün kombinasiya test-ləri, qərar matriksinin bütün branch-ları. Integration test: test environment-də bütün layer-ların birgə işini test edirəm, ML service mock-lanır. End-to-end: production-a shadow mode deploy — real traffic üçün qərar qəbul edirik amma tətbiq etmirik, nəticəni post-hoc əsl fraud/legitimate label-larla müqayisə edib accuracy ölçürük. Bundan əlavə, replay test qururam: keçmiş chargeback case-lərini sisteme yenidən veririk — əgər sistemi düzgün işləsəydi bu fraud-ları tutmalı idi? Tutubmu?

**S: Real-time ML model inference necə işləyir?**

Fraud detection üçün iki seçim var: gRPC/HTTP üzərindən ayrıca ML microservice-ə sorğu (TensorFlow Serving, Triton) və ya ONNX model-i PHP process-inin özündə (FFI vasitəsilə). Production-da xarici ML service daha çox görünür — model-i ayrıca deploy etmək, versioning, rollback daha asandır. Latency budget-i 100ms təyin edirəm: bu aşıldıqda circuit breaker açılır, fallback olaraq yalnız rule + score qərarı verilir. Feature engineering PHP tərəfindən edilir, normalization/scaling ML service-də baş verir. Feature drift-i monitor etmək vacibdir: training zamanla feature dəyərlərinin distribution-u production-dakından fərqlənərsə model accuracy düşür.

**S: Fraud pattern-ları zamanla dəyişir — model-i necə yeniləyərsiniz?**

Feedback loop qururam: chargeback gəldikdə həmin ödənişin feature-ları "fraud" label-ı ilə training data-ya əlavə olunur. Manual review nəticələri — approve (legitimate) və reject (fraud) — da training data-ya yazılır. Bu data ilə həftəlik model retraining pipeline işlədirəm (Airflow cron job): yeni data + köhnə data → feature engineering → train → evaluate → if accuracy > threshold → deploy. Champion/challenger strategiyasını tətbiq edirəm: yeni model 10% traffic-ə shadow mode-da deploy edilir, performans müqayisəsi müsbətdirsə traffic 100%-ə çatdırılır. Model performance metric-ləri (precision, recall, AUC) Grafana-da izlənilir; threshold aşıldıqda alert gəlir.

**S: Velocity check nədir, necə implement edilir?**

Velocity check — qısa müddət ərzindən gələn request sayını limitləmək. Fraud pattern-ların çoxu tez ardıcıl cəhdlərə əsaslanır: stolen card ilə 1 dəqiqədə onlarla test charge, hesab ele keçirildikdən sonra dərhal çoxlu ödəniş. Redis Sorted Set istifadə edirəm — score olaraq Unix timestamp, member olaraq unikal event ID saxlayıram. Yoxlama zamanı `ZRANGEBYSCORE` ilə son N saniyənin event-lərini sayıram, limit aşıldıqda block edirəm, sonra `ZADD` ilə yeni event-i əlavə edib `ZREMRANGEBYSCORE` ilə köhnə event-ləri təmizləyirəm. Bu sliding window approach-dur — fixed window-dan fərqli olaraq window keçidlərindəki burst-ları da tutur. Velocity check-i user ID, card hash, IP, device ID üzrə müstəqil olaraq tətbiq edirəm — biri keçsə digərləri tuta bilər.
