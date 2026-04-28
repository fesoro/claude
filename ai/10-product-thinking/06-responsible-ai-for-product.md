# Product Team üçün Responsible AI (Lead)

> **Kim üçündür:** Senior developerlər, tech lead-lər, product engineer-lər ki, AI feature-larını etik, şəffaf və riskə uyğun şəkildə qurmaq istəyirlər.
>
> **Əhatə dairəsi:** AI risk kateqoriyaları, bias/fairness, şəffaflıq, istifadəçi razılığı, insan nəzarəti, həvəsləndirmə (incentive) uyğunluğu, incident response.

---

## 1. Niyə "Responsible AI" Texniki Mövzudur?

Responsible AI yalnız etika komandası üçün deyil. Developer-lar üçün:

```
Legal risk:
  EU AI Act (2025) → Yüksək risk AI sistemlər üçün sertifikasiya
  GDPR → AI qərarlarında izahat tələbi
  AZ qanunvericiliyi → sürətlə dəyişir

Biznes risk:
  AI-in xəta etməsi → PR krizi (viral xəbər)
  Bias aşkarlanması → müştəri itkisi, dava
  Model drift → pis qərarlar, hidden cost

Texniki debt:
  "Biz sonra düzəldərik" → production-da düzəltmək 10x baha
```

**Developer məsuliyyəti:** responsible AI-i "başqasının problemi" kimi görmək yanıldır. Siz kodu yazırsınız, siz seçirsiz.

---

## 2. AI Risk Kateqoriyaları

### 2.1 Risk Matrisası

```
                  Ehtimal
                Aşağı  ↔  Yüksək
         ┌─────────────────────────┐
Yüksək   │  Medium │    HIGH      │
Təsir    ├─────────┼─────────────┤
Aşağı    │   LOW   │   Medium    │
         └─────────────────────────┘
```

### 2.2 Real Nümunələr

| Feature | Risk Kateqoriyası | Niyə |
|---------|------------------|----|
| Email spam filter | Medium | Yanlış filter → legitimate email itirilir |
| CV analizi (hiring) | HIGH | Bias → ayrı-seçkilik iddiaları |
| Kredit qərarı | HIGH | Maliyyə zərəri, GDPR hüquqları |
| Müştəri dəstəyi chatbot | Low-Medium | Yanlış məlumat → frustrasiya |
| Kod reviewer | Low | Developer son qərarı verir |
| Tibbi diaqnostika | CRITICAL | İnsan həyatı |

### 2.3 Risk Assessment Checklist

```
AI feature üçün risk qiymətləndirməsi:

Avtonoma qərar verir? (insan review yoxdur)     → +2 risk
Maliyyə/hüquqi nəticəsi var?                    → +3 risk
Fiziki dünyaya təsir edir?                      → +4 risk
Həssas qrup haqqında qərar verir?               → +2 risk
  (yaş, cins, etnik mənşə, sağlamlıq)
Böyük miqyasda işləyir? (>10K user/gün)        → +1 risk
Dönüşü çətin qərar? (kredit rədd, işdən çıxarma)→ +2 risk
Model interpretable deyil?                      → +1 risk

Total 0-3: Low → Launch edin
Total 4-6: Medium → Human review + monitoring
Total 7+:  High → HITL mütləq, legal review, audit trail
```

---

## 3. Bias və Fairness

### 3.1 Bias Növləri

```
Training data bias:
  Nümunə: CV analizi modeli — əgər training data 80% kişilər isə
          model kişiləri üstün tutur
  Həll: Balanced dataset, fairness metrics

Measurement bias:
  Nümunə: "Yaxşı müştəri xidməti" metriği əsasən ingilis dilindən ölçülüb
          Azərbaycan dilindəki istifadəçilər əskik görünür
  Həll: Subgroup performance tracking

Feedback loop bias:
  Nümunə: Recommendation system A məhsulunu tövsiyə edir →
          A daha çox satılır → daha çox tövsiyə olunur
          Digər məhsullar görünməz olur
  Həll: Diversity injection, exploration vs exploitation
```

### 3.2 Fairness Metrics İmplementasiyası

```php
<?php
// app/Services/AI/FairnessAuditor.php

namespace App\Services\AI;

class FairnessAuditor
{
    /**
     * Müxtəlif qrup üzrə model performansını müqayisə et.
     * Hər qrup eyni keyfiyyətdə cavab almalıdır.
     */
    public function auditByGroup(
        string $featureName,
        array  $groupAttributes = ['language', 'region', 'user_type'],
    ): array {
        $results = [];

        foreach ($groupAttributes as $attr) {
            $groupStats = \DB::table('ai_feedback')
                ->join('users', 'ai_feedback.user_id', '=', 'users.id')
                ->where('ai_feedback.feature', $featureName)
                ->select(
                    "users.{$attr} AS group_value",
                    \DB::raw('AVG(CASE WHEN ai_feedback.rating = \'thumbs_up\' THEN 1 ELSE 0 END) AS satisfaction_rate'),
                    \DB::raw('COUNT(*) AS sample_size'),
                )
                ->groupBy("users.{$attr}")
                ->having('sample_size', '>=', 50) // Min sample sayı
                ->get();

            $overallAvg = $groupStats->avg('satisfaction_rate');

            $disparities = $groupStats->filter(function ($group) use ($overallAvg) {
                // 10%-dən çox fərq → fairness problemi
                return abs($group->satisfaction_rate - $overallAvg) > 0.10;
            });

            $results[$attr] = [
                'overall_avg'  => round($overallAvg, 3),
                'groups'       => $groupStats,
                'disparities'  => $disparities,
                'is_fair'      => $disparities->isEmpty(),
            ];
        }

        return $results;
    }
}
```

---

## 4. Şəffaflıq: İstifadəçiyə Nə Bildirməli

### 4.1 AI Disclosure

```
Minimum tələblər (EU AI Act + best practice):
  ✓ İstifadəçi AI ilə danışdığını bilməlidir
  ✓ AI-in generate etdiyi məzmun işarələnməlidir
  ✓ İstifadəçi insan alternativini tələb edə bilməlidir

Nümunə UI mətn:
  "Bu cavab AI tərəfindən hazırlanmışdır. Doğruluğunu yoxlayın."
  "AI köməkçisi (beta) — Bütün maliyyə qərarları üçün mütəxəssislə məsləhətləşin."
```

```php
// Blade template-də disclosure component
// resources/views/components/ai-disclosure.blade.php
@props(['feature', 'risk' => 'low'])

<div class="ai-disclosure {{ $risk }}">
    @if($risk === 'high')
        <span class="icon">⚠️</span>
        <span>Bu məlumat AI tərəfindən yaradılıb. İnsan mütəxəssisi ilə yoxlayın.</span>
    @else
        <span class="icon">✨</span>
        <span>AI köməyi ilə hazırlanmışdır.</span>
    @endif
    
    @if($canRequestHuman ?? false)
        <button wire:click="requestHumanReview">İnsan review istə</button>
    @endif
</div>
```

### 4.2 İzahat Vermək (Explainability)

```php
class ExplainableAIService
{
    public function classifyWithReason(string $text, string $category): array
    {
        $prompt = <<<PROMPT
        Bu mətni "{$category}" kateqoriyasına aid edib-etmədiyini müəyyən et.
        
        Mətn: {$text}
        
        JSON formatında cavab ver:
        {
          "is_category": true/false,
          "confidence": 0.0-1.0,
          "reason": "Niyə bu qərarı verdin? 1-2 cümlə.",
          "key_phrases": ["qərara təsir edən əsas ifadələr"]
        }
        PROMPT;

        $response = json_decode($this->claude->messages(
            messages: [['role' => 'user', 'content' => $prompt]],
            model: 'claude-haiku-4-5',
        ), true);

        return $response;
    }
}

// İstifadəçiyə göstər:
// "Bu email 'Texniki Dəstək' kateqoriyasına yerləşdirilib.
//  Səbəb: 'server error', 'database connection' ifadələri var.
//  Güvən: 94%"
```

---

## 5. İstifadəçi Razılığı (Consent)

### 5.1 Data İstifadəsi üçün Razılıq

```php
<?php
// database/migrations/create_ai_consents_table.php
Schema::create('ai_consents', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('user_id');
    $table->string('consent_type');        // 'ai_feature', 'data_training', 'analytics'
    $table->boolean('granted')->default(false);
    $table->string('ip_address', 45);
    $table->string('consent_version');     // Policy versiyası
    $table->timestamps();

    $table->unique(['user_id', 'consent_type', 'consent_version']);
});
```

```php
class ConsentService
{
    public function hasConsent(int $userId, string $consentType): bool
    {
        return AIConsent::where('user_id', $userId)
            ->where('consent_type', $consentType)
            ->where('consent_version', config('ai.consent_version'))
            ->where('granted', true)
            ->exists();
    }

    public function requireConsent(int $userId, string $consentType): void
    {
        if (!$this->hasConsent($userId, $consentType)) {
            throw new ConsentRequiredException($consentType);
        }
    }

    public function grantConsent(int $userId, string $consentType): void
    {
        AIConsent::updateOrCreate(
            [
                'user_id'          => $userId,
                'consent_type'     => $consentType,
                'consent_version'  => config('ai.consent_version'),
            ],
            [
                'granted'    => true,
                'ip_address' => request()->ip(),
            ],
        );
    }
}
```

### 5.2 Opt-Out Mexanizmi

```php
// İstifadəçi AI feature-dan çıxa bilməlidir
class AIFeatureController extends Controller
{
    public function optOut(Request $request): RedirectResponse
    {
        $user = $request->user();

        // Bütün AI feature-larından razılığı geri al
        AIConsent::where('user_id', $user->id)->update(['granted' => false]);

        // User preference-i yenilə
        $user->update(['ai_features_enabled' => false]);

        return back()->with('success', 'AI feature-lar deaktiv edildi.');
    }
}
```

---

## 6. İnsan Nəzarəti

### 6.1 Risk-based HITL

```
Risk həddi ilə avtomatik HITL trigger:

  Kredit qərarı:
    Approve <$500: AI avtonoma ✓
    Approve $500-5000: AI + insan review
    Approve >$5000: İnsan mütləq

  Email classifier:
    Confidence >0.9: AI avtonoma
    Confidence 0.7-0.9: İnsan review queue
    Confidence <0.7: Birbaşa insana yönləndir

  CV analizi:
    İlkin filtrasiya: AI (bias-minimized)
    Final qərar: HƏMİŞƏ insan
```

```php
class HumanReviewGateway
{
    public function shouldEscalate(float $aiConfidence, float $decisionImpact): bool
    {
        // Impact 0-1 (1 = çox yüksək təsir)
        // Confidence 0-1

        // Yüksək impact + aşağı confidence → insan review
        if ($aiConfidence < 0.7 && $decisionImpact > 0.5) {
            return true;
        }

        // Çox yüksək impact → həmişə insan
        if ($decisionImpact > 0.9) {
            return true;
        }

        return false;
    }

    public function createReviewTask(
        string $featureName,
        array  $context,
        string $aiDecision,
        float  $confidence,
    ): HumanReviewTask {
        return HumanReviewTask::create([
            'feature'     => $featureName,
            'context'     => $context,
            'ai_decision' => $aiDecision,
            'confidence'  => $confidence,
            'priority'    => $confidence < 0.5 ? 'high' : 'normal',
            'due_at'      => now()->addHours(24),
        ]);
    }
}
```

---

## 7. Incident Response

### 7.1 AI Incident Classification

```
Level 1 — Minor:
  Nümunə: Bir istifadəçi yanlış email kateqoriyası alır
  Response: Log, investigate, monitor
  
Level 2 — Moderate:
  Nümunə: Bir user cohort üçün sistematik bias aşkarlanır
  Response: Feature disable (əgər lazımdırsa), root cause, fix
  
Level 3 — Major:
  Nümunə: Kredit qərarı sistemi səhv model istifadə edib (1 həftə)
  Response: Emergency rollback, affected user notification, external audit

Level 4 — Critical:
  Nümunə: PII leak, harmful content, ayrı-seçkilik
  Response: Immediate feature shutdown, legal team, public statement
```

### 7.2 Incident Response Checklist

```php
class AIIncidentResponder
{
    public function respond(string $incidentId, int $level): void
    {
        match ($level) {
            1 => $this->minorResponse($incidentId),
            2 => $this->moderateResponse($incidentId),
            3 => $this->majorResponse($incidentId),
            4 => $this->criticalResponse($incidentId),
        };
    }

    private function criticalResponse(string $incidentId): void
    {
        // 1. Immediate feature shutdown
        \Cache::set('ai_feature_disabled', true, 86400);
        \Redis::set('ai_emergency_stop', '1');

        // 2. Affected users müəyyən et
        $affectedUsers = $this->findAffectedUsers($incidentId);

        // 3. Legal team alert
        \Mail::to(config('ai.legal_email'))
            ->send(new AIIncidentLegalAlert($incidentId, $affectedUsers));

        // 4. Incident log (immutable)
        AIIncident::create([
            'id'             => $incidentId,
            'level'          => 4,
            'discovered_at'  => now(),
            'affected_users' => $affectedUsers->count(),
            'status'         => 'investigating',
        ]);
    }
}
```

---

## 8. Practical Checklist: Responsible AI Launch

```
Pre-launch:
  ☐ Risk assessment aparılıb (risk score müəyyən edilib)
  ☐ Bias audit (training data, subgroup performance)
  ☐ Şəffaflıq: AI disclosure UI-da mövcuddur
  ☐ User consent mexanizmi aktiv
  ☐ Opt-out mövcuddur
  ☐ HITL threshold müəyyən edilib

Monitoring:
  ☐ Fairness metrics dashboard-da var
  ☐ Subgroup performance tracking aktiv
  ☐ Feedback loop monitoring (bias feedback var?)
  ☐ Model drift detection aktiv

Incident Response:
  ☐ Incident classification cədvəli mövcuddur
  ☐ Emergency shutdown mexanizmi var
  ☐ Legal/PR escalation path müəyyən edilib
  ☐ Affected user notification template hazır

Documentation:
  ☐ AI feature mövzusu, risk səviyyəsi sənədləşdirilib
  ☐ Model seçimi əsaslandırılıb
  ☐ Audit trail aktiv (qərar + input + output saxlanır)
```

---

## Praktik Tapşırıqlar

### 1. Responsible AI Checklist Tətbiqi
Layihənizdəki mövcud AI feature-ı üçün responsible AI audit keçirin: (1) bias test — fərqli user qrupları eyni cavabı alırmı? (2) transparency — istifadəçi AI-la danışdığını bilirsə? (3) opt-out — istifadəçi AI-dan imtina edə bilərmi? (4) data retention — AI log-ları nə qədər saxlanır? Hər maddə üçün mövcud vəziyyəti yazın, boşluqları müəyyən edin.

### 2. Bias Detection Test
Eyni sorğuyu fərqli user kontekstləri ilə göndərin: fərqli ad, cins, yer, dil. Cavabları müqayisə edin. Anlamlı fərqlər var mı? `bias_tests` cədvəlinə log edin. Tapılan bias-ları sistem prompt-da explicit instruction ilə azaldın: `"Bütün istifadəçilərə eyni keyfiyyətdə xidmət et."` 2 həftə sonra yenidən test edin.

### 3. Transparency Notice Tətbiqi
AI-powered feature-lar üçün user-a açıqlama əlavə edin. UI-da: `"Bu cavab AI tərəfindən yaradılmışdır"` label-i. Onboarding-da: AI-ın nə üçün istifadə edildiyi, məlumatların necə işləndiyi. GDPR tələbi: AI qərarlarına etiraz etmək üçün endpoint. `user_ai_disclosures` cədvəlindən hansı istifadəçinin neçə dəfə disclosure gördüyünü izləyin.

## Əlaqəli Mövzular

- [../08-production/16-ai-governance-compliance.md](../08-production/16-ai-governance-compliance.md) — EU AI Act, ISO 42001
- [../08-production/13-content-moderation.md](../08-production/13-content-moderation.md) — Harmful content filtering
- [../08-production/11-pii-data-redaction.md](../08-production/11-pii-data-redaction.md) — PII idarəsi
- [../05-agents/09-human-in-the-loop.md](../05-agents/09-human-in-the-loop.md) — HITL patterns
- [05-measuring-ai-success.md](05-measuring-ai-success.md) — Fairness metrics tracking
