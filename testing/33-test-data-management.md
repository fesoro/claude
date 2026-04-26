# Test Data Management (Senior)
## İcmal

**Test Data Management (TDM)** — testlər üçün lazım olan məlumatların yaradılması,
saxlanması, idarə edilməsi və təmizlənməsi prosesidir. Yaxşı TDM strategiyası testləri
**etibarlı**, **təkrarlanabilir** və **sürətli** edir.

**Əsas problemlər:**
- Testlər bir-birinə təsir edir (test pollution)
- Real məlumatlarda PII (şəxsi məlumatlar) olur
- Testləri lokal və CI-də eyni data ilə işlətmək çətin olur
- Production snapshot-larından istifadə GDPR/məxfilik pozuntusudur

**TDM həll edir:**
- Test izolasiyasını təmin edir
- Məlumat anonimizasiyası ilə təhlükəsizliyi qoruyur
- Realistik, lakin təhlükəsiz sintetik məlumat yaradır
- Test sürətini artırır (minimal data)

## Niyə Vacibdir

- **GDPR və məxfilik uyumu:** Real production data-sını test mühitinə kopyalamaq qanuni risk daşıyır. Düzgün TDM strategiyası — Faker ilə sintetik data və ya anonimizasiya edilmiş dump — hüquqi problemlərin qarşısını alır.
- **Test etibarlılığı (reliability):** Testlər bir-birinin data-sına görə uğursuz olarsa (test pollution), CI pipeline-da flaky test-lər yaranır. RefreshDatabase və factory-lərlə hər test öz izole mühitini alır.
- **Development sürəti:** Yaxşı qurulmuş factory-lər ilə developer tək sətirdə kompleks scenario yarada bilər (`Order::factory()->withItems(5)->paid()->create()`). Bu, test yazmağı asanlaşdırır və coverage-i artırır.
- **Realistic scenario-lar:** Factory state-ləri (admin, banned, premium) real production hallarını simulyasiya edir. Yalnız "normal" user ilə test etmək edge case-ləri buraxır — bunlar production-da bug kimi ortaya çıxır.
- **CI/CD pipeline sabitliyi:** Testlər hardcoded ID-lər (`User::find(1)`) istifadə edərsə, data-nın mövcud olub-olmamasından asılı olur. Factory-lər hər mühitdə müstəqil data yaradır — pipeline hər yerdə eyni işləyir.

## Əsas Anlayışlar

### 1. Test Data Strategiyaları

| Strategiya | Nə vaxt? | Üstünlük | Çatışmazlıq |
|------------|----------|----------|-------------|
| **Factories** | Dinamik data | Çevik, parametrləşdirilə bilər | Setup mürəkkəbdir |
| **Fixtures** | Statik data | Sadə, təkrarlanabilir | Sərt (rigid), dəyişməsi çətin |
| **Seeders** | Referans data | Production-oxşar | Bütün testlər üçün yavaş |
| **Builders** | Complex objects | Readable, oxunabilir | Daha çox kod |
| **Anonymized dumps** | Realistik data | Production-bənzər | Təhlükəsizlik riski |

### 2. Factories vs Fixtures vs Seeders

```
Factories   → Dynamic, programmatic (User::factory()->create())
Fixtures    → Static JSON/YAML files (users.json)
Seeders     → Database population scripts (DatabaseSeeder)
```

### 3. Data Builder Pattern

Object Mother-un təkmilləşdirilmiş versiyası. **Fluent interface** ilə mürəkkəb obyektlər qurur.

### 4. PII Anonymization

**PII (Personally Identifiable Information)** — şəxs müəyyən edə bilən məlumatlar:
- Ad, soyad, email, telefon
- Kredit kartı, SSN
- IP adres, coğrafi məlumat

**Texnikalar:**
- **Masking**: `john@example.com` → `j***@example.com`
- **Tokenization**: Real data → random token
- **Shuffling**: Sütundakı dəyərləri qarışdırmaq
- **Faker**: Tam sintetik data

### 5. Per-Test vs Shared Data

- **Per-test isolation**: Hər test öz datasını yaradır (DatabaseTransactions, RefreshDatabase)
- **Shared data**: Read-only reference data (countries, currencies) seeder-lə qurulur

## Praktik Baxış

### Best Practices
1. **Factory-lərdən istifadə edin** — fixture yalnız statik data üçün
2. **State-lərlə composable edin** — `->admin()->verified()`
3. **Faker-dən istifadə edin** — realistik, lakin sintetik data
4. **Per-test isolation** — RefreshDatabase və ya transactions
5. **Seed yalnız reference data** — ölkələr, rollar
6. **Minimal data yaradın** — lazım olmayan obyektləri yaratmayın
7. **Builder pattern mürəkkəb obyektlər üçün** — 5+ əlaqə olduqda
8. **Anonymize edin** — production clone-dan istifadə edərkən
9. **Deterministic Faker seed** — flaky testlər üçün
10. **Test namespace-lərindən istifadə edin** — `@example.com` domain

### Anti-Patterns
- **Hardcoded IDs** — `User::find(1)` → factory id-si istifadə et
- **Test pollution** — testlər bir-birinə təsir edir
- **Production dump** — PII data testdə
- **Mega-factory** — bir factory hər şeyi yaradır
- **Shared mutable data** — testlər arasında dəyişən data
- **Duplicate setup** — hər testdə eyni 20 sətir setup
- **Database-independent unit testlər DB-dən istifadə edir** — yavaşlatır
- **Seeders hər testdə** — yalnız reference data üçün

### Test Data Checklist
- [ ] Factory-lər var və state-lərlə composable
- [ ] Faker ilə realistik data
- [ ] RefreshDatabase və ya DatabaseTransactions
- [ ] Reference data seeder-lə yüklənir (setUp-da)
- [ ] PII anonimizasiya edilib (staging/dev)
- [ ] Builder pattern mürəkkəb obyektlər üçün
- [ ] Hardcoded ID-lər yoxdur
- [ ] Test namespace email (`@example.com`)
- [ ] Minimal data yaradılır
- [ ] Deterministic (Faker seed)

## Nümunələr

### Nümunə 1: Test pollution problemi
```
Test A: User yaradır, id=1
Test B: id=1 user-i gözləyir → başqa test qalıqlarına görə uğursuz olur
```

### Nümunə 2: PII in tests
```
Testdə: email = "real.customer@gmail.com" → GDPR pozuntusu
Həll: Faker → email = "quentin.smith.42@example.org"
```

### Nümunə 3: Factory state-ləri
```
User::factory()->admin()->verified()->create()
User::factory()->unverified()->count(5)->create()
```

## Praktik Tapşırıqlar

### 1. Əsas Factory

```php
// database/factories/UserFactory.php
namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => 'user',
            'phone' => $this->faker->e164PhoneNumber(),
            'birth_date' => $this->faker->date('Y-m-d', '-18 years'),
        ];
    }

    // State: Admin istifadəçi
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'admin',
            'email' => 'admin+' . Str::random(5) . '@example.com',
        ]);
    }

    // State: Verify edilməmiş
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    // State: Banlanmış
    public function banned(): static
    {
        return $this->state(fn (array $attributes) => [
            'banned_at' => now(),
            'ban_reason' => $this->faker->sentence(),
        ]);
    }

    // State: Premium istifadəçi
    public function premium(): static
    {
        return $this->state(fn (array $attributes) => [
            'subscription_tier' => 'premium',
            'subscribed_at' => now()->subMonths(3),
        ]);
    }
}
```

### 2. Relationship Factories

```php
// database/factories/OrderFactory.php
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'total' => $this->faker->randomFloat(2, 10, 1000),
            'status' => 'pending',
            'shipping_address' => $this->faker->address(),
        ];
    }

    public function withItems(int $count = 3): static
    {
        return $this->has(
            OrderItem::factory()->count($count),
            'items'
        );
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => 'paid',
            'paid_at' => now(),
        ])->has(Payment::factory()->successful(), 'payment');
    }
}

// İstifadə
$order = Order::factory()
    ->for(User::factory()->premium(), 'user')
    ->withItems(5)
    ->paid()
    ->create();
```

### 3. Data Builder Pattern

```php
namespace Tests\Builders;

use App\Models\Order;
use App\Models\User;
use App\Models\OrderItem;

class OrderBuilder
{
    private array $attributes = [];
    private ?User $user = null;
    private array $items = [];

    public static function new(): self
    {
        return new self();
    }

    public function forUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function withStatus(string $status): self
    {
        $this->attributes['status'] = $status;
        return $this;
    }

    public function withItem(string $name, float $price, int $qty = 1): self
    {
        $this->items[] = compact('name', 'price', 'qty');
        return $this;
    }

    public function paid(): self
    {
        $this->attributes['status'] = 'paid';
        $this->attributes['paid_at'] = now();
        return $this;
    }

    public function build(): Order
    {
        $user = $this->user ?? User::factory()->create();

        $order = Order::factory()->create(array_merge(
            ['user_id' => $user->id],
            $this->attributes
        ));

        foreach ($this->items as $item) {
            $order->items()->create([
                'name' => $item['name'],
                'price' => $item['price'],
                'quantity' => $item['qty'],
            ]);
        }

        return $order->fresh(['items', 'user']);
    }
}

// İstifadə - çox oxunaqlıdır
public function test_paid_order_with_items(): void
{
    $order = OrderBuilder::new()
        ->forUser(User::factory()->premium()->create())
        ->withItem('iPhone', 999.99)
        ->withItem('Case', 29.99, 2)
        ->paid()
        ->build();

    $this->assertEquals('paid', $order->status);
    $this->assertCount(2, $order->items);
}
```

### 4. PII Anonymization Seeder

```php
// database/seeders/AnonymizeProductionSeeder.php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;

class AnonymizeProductionSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new \Exception('PRODUCTION-də işlədilə bilməz!');
        }

        $faker = Faker::create();

        DB::table('users')->orderBy('id')->chunk(1000, function ($users) use ($faker) {
            foreach ($users as $user) {
                DB::table('users')
                    ->where('id', $user->id)
                    ->update([
                        'name' => $faker->name(),
                        'email' => "user{$user->id}@example.test",
                        'phone' => $faker->e164PhoneNumber(),
                        'ssn' => null,
                        'credit_card_last4' => null,
                        'address' => $faker->address(),
                    ]);
            }
        });

        $this->command->info('PII anonymization tamamlandı.');
    }
}
```

### 5. Shared Reference Data (Seeder)

```php
// database/seeders/CountrySeeder.php
class CountrySeeder extends Seeder
{
    public function run(): void
    {
        $countries = [
            ['code' => 'AZ', 'name' => 'Azərbaycan'],
            ['code' => 'TR', 'name' => 'Türkiyə'],
            ['code' => 'US', 'name' => 'United States'],
        ];

        foreach ($countries as $country) {
            Country::updateOrCreate(['code' => $country['code']], $country);
        }
    }
}

// TestCase.php - setUp zamanı
abstract class TestCase extends BaseTestCase
{
    use CreatesApplication, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Shared reference data (hər test üçün)
        $this->seed(CountrySeeder::class);
        $this->seed(CurrencySeeder::class);
    }
}
```

### 6. Fixtures (JSON faylları)

```php
// tests/Fixtures/users.json
[
    {"id": 1, "name": "John", "email": "john@test.com", "role": "admin"},
    {"id": 2, "name": "Jane", "email": "jane@test.com", "role": "user"}
]

// tests/TestCase.php
protected function loadFixture(string $name): array
{
    $path = base_path("tests/Fixtures/{$name}.json");
    return json_decode(file_get_contents($path), true);
}

// İstifadə
public function test_with_fixture(): void
{
    $users = $this->loadFixture('users');

    foreach ($users as $userData) {
        User::create($userData);
    }

    $this->assertDatabaseCount('users', 2);
}
```

### 7. Faker ilə Realistik Data

```php
class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        $faker = $this->faker;

        return [
            'invoice_number' => 'INV-' . $faker->unique()->numerify('######'),
            'customer_name' => $faker->company(),
            'customer_tax_id' => $faker->regexify('[0-9]{10}'),
            'billing_address' => $faker->address(),
            'issue_date' => $faker->dateTimeBetween('-1 year', 'now'),
            'due_date' => $faker->dateTimeBetween('now', '+30 days'),
            'subtotal' => $faker->randomFloat(2, 100, 10000),
            'tax_rate' => $faker->randomElement([0.18, 0.08, 0.20]),
            'notes' => $faker->paragraph(),
        ];
    }
}
```

### 8. Per-Test Isolation (RefreshDatabase)

```php
use Illuminate\Foundation\Testing\RefreshDatabase;

class OrderTest extends TestCase
{
    use RefreshDatabase; // Hər testdən əvvəl DB reset

    public function test_creates_order(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/orders', [
            'items' => [['product_id' => 1, 'qty' => 2]],
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('orders', ['user_id' => $user->id]);
    }

    // Bu test yuxarıdakına təsir etmir
    public function test_lists_orders(): void
    {
        $this->assertDatabaseCount('orders', 0); // Təmiz DB
    }
}
```

## Ətraflı Qeydlər

### S1: Factory və Fixture arasında fərq nədir?
**C:** **Factory** dinamik olaraq data yaradır (parametrlə, state ilə) — məs.
`User::factory()->admin()->create()`. **Fixture** isə statik fayldır (JSON/YAML) —
əvvəlcədən təyin olunmuş məlumatdır. Factory daha çevik və test-spesifikdir, fixture
isə təkrarlanabilirdir, lakin dəyişdirilməsi çətindir.

### S2: Test-lərdə production data istifadə etmək olar?
**C:** Xeyr, bir neçə səbəbə görə:
1. **GDPR/məxfilik pozuntusu** — real PII data testdə işlənir
2. **Təkrarlanabilirlik yoxdur** — production dəyişir
3. **Ölçü** — milyonlarla satır testi yavaşladır

Əgər mütləqdirsə, **anonimizasiya edilmiş snapshot** istifadə edin.

### S3: Laravel-də factory state nədir?
**C:** **State** — factory-nin müəyyən bir variantıdır. Məs.
`User::factory()->admin()->unverified()->create()` admin və verify edilməmiş user
yaradır. State-lər factory-ləri **composable** (birləşdirilə bilən) edir.

### S4: RefreshDatabase nə edir?
**C:** Hər testdən əvvəl database-i reset edir. İki üsulla işləyir:
- **SQLite in-memory**: Hər test üçün yeni DB
- **MySQL/Postgres**: Transaction başladır, test sonunda rollback edir

Test izolasiyasını təmin edir — test pollution-un qarşısını alır.

### S5: Data Builder pattern-in üstünlüyü nədir?
**C:** Mürəkkəb obyektləri **oxunaqlı** qurmaq üçündür. Factory-lər sadə obyektlər
üçün yaxşıdır, lakin 10+ əlaqəli obyekt lazım olduqda builder fluent interface ilə
intent-i daha yaxşı ifadə edir:
```php
OrderBuilder::new()->forUser($user)->withItem('X')->paid()->build();
```

### S6: PII anonymization-da hansı texnikalardan istifadə olunur?
**C:**
- **Masking** — hissəvi gizlətmə (`john@***.com`)
- **Tokenization** — real dəyəri token ilə əvəz etmək
- **Shuffling** — sütun dəyərlərini qarışdırmaq
- **Synthetic data** — Faker ilə tam uydurma data
- **Hashing** — tək tərəfli encryption (SSN → hash)

### S7: Shared test data nə vaxt istifadə olunmalıdır?
**C:** **Read-only reference data** üçün: ölkələr, valyutalar, status-lar, kateqoriyalar.
Bunlar testlər arasında dəyişmir. **setUp()**-da bir dəfə yüklənir. **Yazılabilən
data** (user, order) isə hər test üçün factory ilə yaradılmalıdır.

### S8: Faker seed (sabit seed) nə üçündür?
**C:** Test-lərin **deterministic** olması üçün. `faker->seed(1234)` ilə Faker hər
dəfə eyni data istehsal edir. Bu flaky test-lərin qarşısını alır və debug-u asanlaşdırır.

### S9: Test data-sı çox böyükdürsə nə etməli?
**C:**
- **Minimal data** — yalnız lazım olanı yaradın
- **Lazy loading** — testdə istifadə olunmayan relation-ları yaratmayın
- **In-memory DB** (SQLite) — sürətli
- **Transactions** — RefreshDatabase əvəzinə DatabaseTransactions

### S10: Database seeder və factory arasında fərq nədir?
**C:** **Seeder** — DB-ni müəyyən vəziyyətə gətirir (adətən dev/staging üçün referans data).
**Factory** — test üçün dinamik model nümunəsi yaradır. Seeder factory-ni istifadə edə
bilər, lakin məqsədləri fərqlidir: seeder = populate, factory = generate.

## Əlaqəli Mövzular

- [Database Testing (Middle)](10-database-testing.md)
- [Test Organization (Middle)](13-test-organization.md)
- [Integration Testing (Junior)](03-integration-testing.md)
- [Test Environment Management (Lead)](40-test-environment-management.md)
- [Testing Anti-Patterns (Senior)](27-testing-anti-patterns.md)
- [Test Patterns (Senior)](26-test-patterns.md)
