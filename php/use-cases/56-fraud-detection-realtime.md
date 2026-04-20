# Use Case: Real-time Fraud Detection (Payment)

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
