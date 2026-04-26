# Laravel Model Factories & Seeders (Middle)

## İcmal
Model Factory-lər test üçün fake məlumat generasiyasının əsas mexanizmidir. Seeder-lər isə database-i başlanğıc məlumatlarla doldurmaq üçündür. İkisi ayrı məqsəd üçündür: factory testdə, seeder development/production başlanğıc datası üçün.

## Niyə Vacibdir
Test yazmaq istəyirsən amma əl ilə `User::create([...])` yazmaq yorucu. Factory-lər bir sətirdə realistic, valid data yaratmağa imkan verir. Relationship-ləri, edge case-ləri, state variation-larını birbaşa factory-də modelləmək testləri oxunaqlı və dözümlü edir.

## Əsas Anlayışlar

### Factory Anatomy
```php
// database/factories/UserFactory.php
class UserFactory extends Factory
{
    protected $model = User::class;
    
    public function definition(): array
    {
        return [
            'name'              => fake()->name(),
            'email'             => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password'          => Hash::make('password'),  // sabit — testdə bilmək lazımdır
            'remember_token'    => Str::random(10),
            'role'              => 'user',
            'locale'            => 'az',
        ];
    }
}
```

### Model-də HasFactory
```php
class User extends Authenticatable
{
    use HasFactory, Notifiable;
}
```

### Əsas İstifadə
```php
// Bir obyekt yarat (DB-yə yaz)
$user = User::factory()->create();

// Bir obyekt yarat (DB-yə yazmadan — in-memory)
$user = User::factory()->make();

// Çoxlu
$users = User::factory()->count(10)->create();

// Atributları override et
$admin = User::factory()->create(['role' => 'admin', 'email' => 'admin@test.com']);
```

## State-lər

Factory state-ləri fərqli vəziyyətdəki model variantlarıdır:
```php
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'              => fake()->name(),
            'email'             => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'role'              => 'user',
            'suspended_at'      => null,
            'premium_until'     => null,
        ];
    }
    
    // State: email doğrulanmamış
    public function unverified(): static
    {
        return $this->state(['email_verified_at' => null]);
    }
    
    // State: premium
    public function premium(): static
    {
        return $this->state(['premium_until' => now()->addYear()]);
    }
    
    // State: admin
    public function admin(): static
    {
        return $this->state(['role' => 'admin']);
    }
    
    // State: suspended
    public function suspended(): static
    {
        return $this->state(['suspended_at' => now()->subDay()]);
    }
    
    // State + closure
    public function withExpiredPremium(): static
    {
        return $this->state(function (array $attributes) {
            return ['premium_until' => now()->subMonth()];
        });
    }
}

// İstifadə
$user = User::factory()->premium()->create();
$user = User::factory()->admin()->unverified()->create();
$users = User::factory()->count(5)->suspended()->create();
```

## Relationship-lər

### BelongsTo (hasOwner)
```php
// Order factory
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'    => User::factory(),   // avtomatik User yaradır
            'total'      => fake()->numberBetween(100, 50000),
            'status'     => 'pending',
            'created_at' => fake()->dateTimeBetween('-6 months'),
        ];
    }
}

// Mövcud user ilə
$order = Order::factory()->for($user)->create();
// Eyni: Order::factory()->create(['user_id' => $user->id])
```

### HasMany
```php
// User + 3 Order
$user = User::factory()
    ->has(Order::factory()->count(3))
    ->create();

// Alias
$user = User::factory()
    ->hasOrders(3)
    ->create();

// State ilə
$user = User::factory()
    ->has(Order::factory()->count(2)->state(['status' => 'paid']))
    ->create();
```

### BelongsToMany (attach)
```php
class ArticleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title'   => fake()->sentence(),
            'content' => fake()->paragraphs(3, true),
        ];
    }
}

// Article + 3 Tag
$article = Article::factory()
    ->hasAttached(Tag::factory()->count(3), ['created_by' => 1])
    ->create();
```

## Sequence

Ardıcıl variasiyalar üçün:
```php
// Dönüşümlü status-lar
$orders = Order::factory()
    ->count(6)
    ->sequence(
        ['status' => 'pending'],
        ['status' => 'paid'],
        ['status' => 'cancelled'],
    )
    ->create();
// pending, paid, cancelled, pending, paid, cancelled

// Index əsaslı sequence
$products = Product::factory()
    ->count(5)
    ->sequence(fn(Sequence $seq) => ['sort_order' => $seq->index + 1])
    ->create();
// sort_order: 1, 2, 3, 4, 5
```

## Callbacks

```php
class OrderFactory extends Factory
{
    public function configure(): static
    {
        return $this->afterMaking(function (Order $order) {
            // Make-dən sonra (DB-yə yazılmadan)
        })->afterCreating(function (Order $order) {
            // Create-dən sonra (DB-yə yazıldıqdan sonra)
            OrderAuditLog::create([
                'order_id' => $order->id,
                'event'    => 'created',
            ]);
        });
    }
}
```

## Seeder-lər

### Database Seeder
```php
// database/seeders/DatabaseSeeder.php
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            CategorySeeder::class,
            ProductSeeder::class,
        ]);
    }
}
```

### Xüsusi Seeder
```php
// database/seeders/UserSeeder.php
class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Sabit admin (idempotent — mövcuddursa yarat, yoxdursa skip)
        User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name'     => 'Admin',
                'password' => Hash::make('secret'),
                'role'     => 'admin',
            ]
        );
        
        // Test istifadəçiləri (sadəcə development)
        if (app()->isLocal()) {
            User::factory()->count(50)->create();
            User::factory()->premium()->count(10)->create();
        }
    }
}
```

### Environment-based Seeding
```php
class ProductSeeder extends Seeder
{
    public function run(): void
    {
        // Production — real məlumatlar
        if (app()->isProduction()) {
            $this->seedFromCsv();
            return;
        }
        
        // Development — fake məlumatlar
        Category::factory()->count(10)
            ->has(Product::factory()->count(20))
            ->create();
    }
    
    private function seedFromCsv(): void
    {
        foreach (array_slice(file('/path/to/products.csv'), 1) as $line) {
            [$name, $price, $category] = str_getcsv($line);
            Product::firstOrCreate(['name' => trim($name)], [
                'price'    => (int) $price,
                'category' => trim($category),
            ]);
        }
    }
}
```

```bash
# Çalışdırmaq
php artisan db:seed
php artisan db:seed --class=UserSeeder
php artisan migrate:fresh --seed   # sil + migrate + seed
```

## Testdə İstifadə

### RefreshDatabase vs DatabaseTransactions
```php
// RefreshDatabase — hər test üçün migrate:fresh (yavaş)
use RefreshDatabase;

// DatabaseTransactions — hər test transaction içindədir, rollback olur (sürətli)
use DatabaseTransactions;
```

### Pest ilə
```php
uses(RefreshDatabase::class);

it('returns only active products', function () {
    Product::factory()->count(3)->create(['active' => true]);
    Product::factory()->count(2)->create(['active' => false]);
    
    $response = $this->getJson('/api/products');
    
    $response->assertJsonCount(3, 'data');
});

it('calculates premium discount', function () {
    $user  = User::factory()->premium()->create();
    $order = Order::factory()->for($user)->create(['total' => 10000]);
    
    $discount = app(PricingService::class)->getDiscount($order);
    
    expect($discount)->toBe(2000); // 20%
});
```

## Praktik Baxış

### Trade-off-lar
- **Factory vs manual create**: Factory-lər default-ları saxlayır, yalnız lazımlı fərqi override et. Manual create-dən daha oxunaqlı.
- **State vs override**: State semantik birlik verir (`->premium()`), override dəqiq control. Tez-tez istifadə olunan kombinasiyalar state olsun.
- **RefreshDatabase vs DatabaseTransactions**: RefreshDatabase daha izolasiyalı amma yavaş (migration hər test); DatabaseTransactions transaction rollback edir, daha sürətli.

### Common Mistakes
- `fake()->unique()->safeEmail()` olmadan parallel testlərdə unique constraint xətası
- Seeder-i production-a idempotent etməmək → hər run-da dublikat məlumat
- Factory-də hardcoded `id` yazılmaq → konflikt
- `afterCreating` callback-ında başqa factory çağırmaq sonsuz loop riski
- Test-ə həddən çox data yarat → test yavaşlayır

## Praktik Tapşırıqlar

1. `Order` factory yaz: `pending`, `paid`, `cancelled`, `refunded` state-ləri
2. User + Order + OrderItem relationship factory chain: `User::factory()->hasOrders(3, ['status' => 'paid'])->hasOrderItems(5)->create()`
3. `Sequence` ilə 10 product yarat: sort_order 1-10, alternating active/inactive
4. `UserSeeder` idempotent et: `firstOrCreate` ilə admin user, local mühitdə factory ilə 100 test user
5. `RefreshDatabase` vs `DatabaseTransactions` benchmark: 50 testlə fərqi ölç

## Əlaqəli Mövzular
- [Database Testing Strategies](052-database-testing-strategies.md)
- [Pest Framework](021-pest-framework.md)
- [TDD](086-tdd.md)
- [Testing Doubles Patterns](204-testing-doubles-patterns.md)
- [ORM Deep Dive](051-orm-deep-dive.md)
