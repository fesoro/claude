# Feature Flags & Progressive Rollout (Middle)

## Mündəricat
1. [Feature Flag nədir?](#feature-flag-nədir)
2. [Flag Növləri](#flag-növləri)
3. [Trunk-Based Development](#trunk-based-development)
4. [Progressive Rollout Strategiyaları](#progressive-rollout-strategiyaları)
5. [Flag Lifecycle](#flag-lifecycle)
6. [PHP İmplementasiyası](#php-implementasiyası)
7. [İntervyu Sualları](#intervyu-sualları)

---

## Feature Flag nədir?

```
Feature flag (feature toggle) — kod deploy etmədən
feature-ı açıb-bağlamağa imkan verir.

if ($featureFlags->isEnabled('new_checkout')) {
    return $this->newCheckout($cart);
} else {
    return $this->legacyCheckout($cart);
}

Niyə lazımdır?
  Deploy ≠ Release
  Kod production-a göndərilir amma feature bağlıdır.
  Hazır olduqda flag açılır — deploy olmadan release.

  ┌──────────────────────────────────────────────┐
  │                                              │
  │  Monday:  Deploy new_checkout feature (OFF) │
  │  Tuesday: QA test edir                      │
  │  Wednesday: Flag ON → %5 user               │
  │  Thursday: Flag ON → %25 user               │
  │  Friday:  Flag ON → %100 user               │
  │                                              │
  │  Problem?  Flag OFF → dərhal rollback!       │
  └──────────────────────────────────────────────┘
```

---

## Flag Növləri

```
1. Release Flags (Canary):
   Yeni feature-ı tədricən açmaq.
   Müvəqqəti — feature stabil olduqda silinir.
   
   isEnabled('new_payment_flow')

2. Experiment Flags (A/B Test):
   Hansı variant daha yaxşı performans göstərir?
   User-ları iki qrupa böl, hər birini ölç.
   
   variant = getVariant('checkout_button_color')
   // 'blue' → qrup A, 'green' → qrup B

3. Ops Flags (Kill Switch):
   Xarici servis çökdükdə feature söndür.
   Uzun müddət yaşaya bilər.
   
   isEnabled('stripe_payments')  // Stripe down? → false

4. Permission Flags:
   Yalnız müəyyən user-lara açıq.
   Premium features, beta testers, internal users.
   
   isEnabled('export_csv', user: $user)
   // Yalnız enterprise plan user-lar üçün
```

---

## Trunk-Based Development

```
Feature flags trunk-based development-i mümkün edir.
Uzun yaşayan branch-lər yoxdur.

Ənənəvi (feature branch):             Trunk-based + Flags:
main ────────────────────             main ──────────────────────►
        │           ↑                  Hər commit → main
feature ▼───────────┘                  Feature flag ilə gizlənir
(2 həftə ayrı)

Trunk-based faydaları:
  + Merge conflict yoxdur
  + CI/CD hər commit-dən işləyir
  + Production koda həmişə yaxınsınız
  + Deploy tez-tez, kiçik dəyişikliklər

Feature flag mütləq şərtlər:
  - Tam tamamlanmamış feature kodu main-dədir
  - Flag OFF isə kod icra edilmir
  - Flag-ın özü ayrıca test edilir (true/false hər ikisi)
```

---

## Progressive Rollout Strategiyaları

```
Percentage Rollout (Faiz):
  %1 → %5 → %10 → %25 → %50 → %100
  Hər mərhələdə metrikləri izlə.
  Problem?  → Faizi azalt/sıfırla.

  Deterministic: eyni user həmişə eyni variant görsün!
  hash(user_id + flag_name) % 100 < percentage

User Segment:
  "Beta testers" → ON
  "Premium plan"  → ON
  "Country: AZ"   → ON
  Hamı            → OFF

Canary:
  Spesifik server/instance-da ON.
  Əksər traffic köhnə versiyaya.
  "Canary" instance-ı izlə.

Geolocation:
  Əvvəlcə Azərbaycanda test et,
  sonra digər ölkələrə yay.
```

---

## Flag Lifecycle

```
Problem: "Flag borcu" — artıq lazım olmayan flag-lar yığılır.

Lifecycle:
  1. CREATE  → flag yaradılır, default OFF
  2. TESTING → QA/staging-də test
  3. ROLLOUT → tədricən istifadəçilərə açılır
  4. STABLE  → %100-ə çatdı, problem yoxdur
  5. CLEANUP → flag kodu çıxarılır (!) ← ən çox unudulan addım

Cleanup niyə vacibdir:
  if ($flags->isEnabled('old_feature')) { // 2 il əvvəl yazılıb
      // Artıq həmişə true, amma flag kodu kod-da qalır
      // Dead code, anlaşılmazlıq, test complexity
  }

Qaydalar:
  - Flag-ın expiry date-i olsun
  - Linter/CI flag adlarını yoxlasın (registry-dəmi?)
  - Her sprint: "köhnə flagları sil" task-ı
  - Flag registry-si (mərkəzləşdirilmiş idarəetmə)
```

---

## PHP İmplementasiyası

```php
<?php
// Sadə Feature Flag Service
class FeatureFlagService
{
    private array $flags;

    public function __construct(
        private FlagRepository $repository,
        private ?int $userId = null,
    ) {}

    public function isEnabled(string $flagName, ?User $user = null): bool
    {
        $flag = $this->repository->find($flagName);

        if (!$flag || !$flag->isActive()) {
            return false;
        }

        return match($flag->getType()) {
            'boolean'    => $flag->getValue(),
            'percentage' => $this->checkPercentage($flag, $user),
            'user_list'  => $this->checkUserList($flag, $user),
            'segment'    => $this->checkSegment($flag, $user),
            default      => false,
        };
    }

    private function checkPercentage(Flag $flag, ?User $user): bool
    {
        if (!$user) return false;

        // Deterministic: eyni user həmişə eyni nəticə
        $hash = crc32($flag->getName() . ':' . $user->getId());
        $bucket = abs($hash) % 100;

        return $bucket < $flag->getPercentage();
    }

    private function checkUserList(Flag $flag, ?User $user): bool
    {
        return $user && in_array($user->getId(), $flag->getAllowedUserIds());
    }

    private function checkSegment(Flag $flag, ?User $user): bool
    {
        if (!$user) return false;

        return match($flag->getSegment()) {
            'premium'     => $user->isPremium(),
            'beta_tester' => $user->isBetaTester(),
            'employee'    => $user->isEmployee(),
            default       => false,
        };
    }
}
```

```php
<?php
// Controller-da istifadə
class CheckoutController
{
    public function process(Request $request): Response
    {
        $user = auth()->user();

        if ($this->flags->isEnabled('new_checkout_v2', $user)) {
            return $this->newCheckoutService->process($request);
        }

        return $this->legacyCheckoutService->process($request);
    }
}

// Test-də flag override
class CheckoutControllerTest extends TestCase
{
    public function test_new_checkout_when_flag_enabled(): void
    {
        $this->flags->enable('new_checkout_v2'); // Test-də force enable

        $response = $this->post('/checkout', [...]);

        $this->assertNewCheckoutUsed($response);
    }

    public function test_legacy_checkout_when_flag_disabled(): void
    {
        $this->flags->disable('new_checkout_v2');

        $response = $this->post('/checkout', [...]);

        $this->assertLegacyCheckoutUsed($response);
    }
}
```

---

## İntervyu Sualları

- Feature flag niyə "deploy ≠ release" konsepsiyasını mümkün edir?
- Percentage rollout-da "deterministic" olmaq niyə vacibdir?
- Flag borcu (flag debt) nədir? Necə idarə olunur?
- Kill switch flag-ı release flag-dan nəylə fərqlənir?
- A/B test flag-ı ilə canary release fərqi nədir?
- Feature flag-ı test edərkən hər iki hal (ON/OFF) niyə test edilməlidir?
- `hash(user_id + flag_name) % 100` formulası niyə yalnız `hash(user_id)` deyil?
