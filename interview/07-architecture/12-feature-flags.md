# Feature Flags (Senior ⭐⭐⭐)

## İcmal
Feature Flags (Feature Toggles) — kod deployment-i ilə feature activation-ı ayıran texnikadır. Martin Fowler-in məşhur "trunk-based development" konsepsiyasının əsas alətidir. Deployment-dan asılı olmadan feature-ı istənilən vaxt aktiv/passiv etmək, müəyyən istifadəçilərə göstərmək, A/B test aparmaq mümkün olur. Interview-da continuous delivery, risk management, experimentation anlayışını ölçür.

## Niyə Vacibdir
Feature branch-lər uzun yaşasa merge conflict artar. Feature flags trunk-based development imkan verir — hər commit main branch-ə gedir, yeni feature flag arxasında gizlənir. Production-a cəsarətlə release etmək mümkün olur — problem yarananda flag söndürülür, rollback deployment lazım deyil. A/B testing, canary release, kill switch kimi use case-lər feature flags ilə asanlıqla implement olunur.

## Əsas Anlayışlar

- **Release Toggle**: Hazır olmayan feature-ı production-da gizlətmək — dark launch
- **Experiment Toggle**: A/B testing üçün — istifadəçilərin %50-si A variant, %50-si B görür
- **Ops Toggle**: Operational switch — yüksək yük zamanı bəzi feature-ları söndürmək (circuit breaker kimi)
- **Permission Toggle**: Müəyyən user qrupuna (beta users, premium, internal) feature göstərmək
- **Trunk-based Development**: Hər developer main branch-ə gündəlik commit edir — uzun feature branch yoxdur
- **Flag lifespan**: Flags permanent olmamalıdır — feature stable olduqdan sonra flag silinməlidir (flag debt)
- **Targeting rules**: User ID, country, plan, percentage-based rollout, custom attribute
- **Flag evaluation**: Client-side vs Server-side — server-side daha güvənlidir (business logic gizli qalır)
- **LaunchDarkly, Unleash, Flagsmith**: Populyar feature flag platformları
- **Homegrown vs Platform**: Kiçik komanda üçün sadə DB-based flag, böyük komanda üçün dedicated platform
- **Flag explosion**: Çox flag toplanması — testing complexity artır, "dark code" yaranır
- **Kill switch**: Production-da kritik feature-ı dərhal söndürmək imkanı — incident response
- **Gradual rollout**: 1% → 5% → 25% → 50% → 100% — hər mərhələdə metrikalar izlənir
- **Stickiness**: Eyni istifadəçi hər dəfə eyni variantı görür — consistent experience

## Praktik Baxış

**Interview-da necə yanaşmaq:**
Feature Flags mövzusunda əvvəlcə "deployment ≠ release" ideyasını izah edin. Sonra müxtəlif toggle tip-lərini verin, flag debt-i (köhnə flagların silinməsi) qeyd edin. Platformlar haqqında (LaunchDarkly vs Unleash) müqayisə artı hesab edilir.

**Follow-up suallar:**
- "Flag debt-i necə idarə edirsiniz?"
- "Client-side vs server-side flag evaluation fərqi nədir?"
- "Flags-ı test etmək üçün strategiyalar nələrdir?"
- "Feature flag olmadan canary release necə edilir?"

**Ümumi səhvlər:**
- Flags-ı heç vaxt silməmək — "dark code" yığılır, testing mürəkkəbləşir
- Flag adlandırmasında ardıcıllıq olmaması — `new_checkout` vs `enable-checkout-v2` vs `checkoutFeature`
- Çox nested flag logic — if flag A && flag B && !flag C — test cases eksponensial artır
- Database-i hər request-də flagları yükləmək — cache lazımdır

**Yaxşı cavabı əla cavabdan fərqləndirən:**
Əla cavab flag lifecycle-ı bilir: yaradılma → testing → gradual rollout → full release → flag silinmə. Monitoring ilə flag-ı əlaqələndirə bilir: "25%-ə keçdikdə conversion rate düşdü, flag söndürüldü."

## Nümunələr

### Tipik Interview Sualı
"Feature Flags nədir? Production-da yeni checkout flow-nu risk minimuma endirərək necə release edərdiniz?"

### Güclü Cavab
"Feature Flag deployment-i release-dən ayırır. Yeni checkout kodu flag arxasında production-a deploy edilir — heç kim görür. Sonra beta istifadəçilərə (10 nəfər) görüntülənir, problem yoxsa 1%-ə genişləndirilir. Conversion rate, error rate, session duration izlənir. Hər mərhələdə metrikalar stabil olsa percent artırılır. Problem yarananda deployment olmadan flag söndürülür — dərhal rollback. Checkout stable olduqda flag silinir, kod cleanup edilir. Bu process deployment confidence-i artırır, release riskini azaldır."

### Kod / Konfiqurasiya Nümunəsi

```php
// Feature Flag Service — Sadə implementasiya
interface FeatureFlagService
{
    public function isEnabled(string $flag, ?User $user = null): bool;
    public function getVariant(string $flag, User $user): string;
}

class DatabaseFeatureFlagService implements FeatureFlagService
{
    private array $cache = [];

    public function isEnabled(string $flag, ?User $user = null): bool
    {
        $config = $this->getFlag($flag);

        if (!$config || !$config['enabled']) {
            return false;
        }

        if ($user === null) {
            return true; // global flag
        }

        return $this->evaluate($config, $user);
    }

    private function evaluate(array $config, User $user): bool
    {
        // 1. User ID whitelist
        if (in_array($user->id, $config['allowed_user_ids'] ?? [])) {
            return true;
        }

        // 2. User plan check
        if ($config['allowed_plans'] ?? null) {
            if (!in_array($user->plan, $config['allowed_plans'])) {
                return false;
            }
        }

        // 3. Country targeting
        if ($config['allowed_countries'] ?? null) {
            if (!in_array($user->country, $config['allowed_countries'])) {
                return false;
            }
        }

        // 4. Percentage rollout — consistent (sticky)
        $percentage = $config['rollout_percentage'] ?? 100;
        if ($percentage < 100) {
            // User ID hash-i faizə çevir — hər user həmişə eyni bucket-dədir
            $bucket = crc32($user->id . $config['flag_name']) % 100;
            return $bucket < $percentage;
        }

        return true;
    }

    public function getVariant(string $flag, User $user): string
    {
        $config = $this->getFlag($flag);
        if (!$config || empty($config['variants'])) {
            return 'control';
        }

        $bucket = crc32($user->id . $flag) % 100;
        $cumulative = 0;

        foreach ($config['variants'] as $variant => $weight) {
            $cumulative += $weight;
            if ($bucket < $cumulative) {
                return $variant;
            }
        }

        return 'control';
    }

    private function getFlag(string $flag): ?array
    {
        if (isset($this->cache[$flag])) {
            return $this->cache[$flag];
        }

        $config = Cache::remember("feature_flag_{$flag}", 60, function () use ($flag) {
            return FeatureFlag::where('name', $flag)->first()?->toArray();
        });

        return $this->cache[$flag] = $config;
    }
}

// Laravel Middleware — Request-level flag check
class FeatureFlagMiddleware
{
    public function handle(Request $request, Closure $next, string $flag): mixed
    {
        if (!app(FeatureFlagService::class)->isEnabled($flag, $request->user())) {
            abort(404);
        }
        return $next($request);
    }
}

// Controller-də istifadə
class CheckoutController extends Controller
{
    public function __construct(private FeatureFlagService $flags) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        // A/B test: yeni checkout variantı
        if ($this->flags->isEnabled('new-checkout', $user)) {
            $variant = $this->flags->getVariant('new-checkout', $user);
            return match ($variant) {
                'one-page'   => view('checkout.one-page'),
                'multi-step' => view('checkout.multi-step'),
                default      => view('checkout.classic'),
            };
        }

        return view('checkout.classic');
    }
}

// Database schema
// feature_flags tablosu
// id | name              | enabled | rollout_percentage | allowed_user_ids | allowed_plans | variants   | created_at
// 1  | new-checkout      | true    | 25                 | [1,2,3]          | ['premium']   | {...}      | ...
// 2  | new-payment-flow  | false   | 0                  | []               | []            | null       | ...
// 3  | dark-mode         | true    | 100                | []               | []            | null       | ...
```

```php
// Unleash Client (Open-source platform) — Laravel inteqrasiyası
use Unleash\Client\UnleashBuilder;

$unleash = UnleashBuilder::create()
    ->withAppUrl(config('unleash.url'))
    ->withInstanceId(config('unleash.instance_id'))
    ->withAppName(config('app.name'))
    ->build();

// Context ilə zəngin targeting
$context = (new UnleashContext())
    ->setCurrentUserId((string) auth()->id())
    ->setProperties([
        'plan'    => auth()->user()?->plan,
        'country' => auth()->user()?->country,
    ]);

if ($unleash->isEnabled('new-dashboard', $context)) {
    // yeni dashboard
}
```

```yaml
# LaunchDarkly flag konfiqurasiyası (JSON)
{
  "key": "new-checkout",
  "on": true,
  "variations": [
    {"value": false},
    {"value": true}
  ],
  "fallthrough": {"variation": 0},
  "offVariation": 0,
  "rules": [
    {
      "variation": 1,
      "clauses": [
        {
          "attribute": "plan",
          "op": "in",
          "values": ["premium", "enterprise"]
        }
      ]
    }
  ],
  "targets": [
    {
      "variation": 1,
      "values": ["user-123", "user-456"]
    }
  ]
}
```

## Praktik Tapşırıqlar

- Laravel-də sadə DB-based feature flag service implement edin
- Percentage rollout-da "stickiness" (eyni user həmişə eyni cavab) olmasa nə problem yaranır?
- Flag lifecycle: yaradılma → cleanup cədvəl hazırlayın (məsələn: 30 gündən sonra avtomatik deaktiv)
- Ops toggle (kill switch) üçün specific monitoring alarm qurun
- 3 flag tipini (Release, Experiment, Permission) öz layihənizdə müəyyən edin

## Əlaqəli Mövzular

- `13-blue-green-canary.md` — Canary + Feature Flags birlikdə
- `10-strangler-fig.md` — Migration-da Feature Flags
- `14-zero-downtime-deployments.md` — Deployment risk azaltma
- `15-technical-debt-management.md` — Flag debt idarəsi
