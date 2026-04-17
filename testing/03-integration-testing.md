# Integration Testing

## Nədir? (What is it?)

Integration testing iki və ya daha çox komponentin birlikdə düzgün işlədiyini yoxlayan
test növüdür. Unit testlərdən fərqli olaraq, real asılılıqlar (database, file system,
cache) istifadə olunur.

Məsələn, bir `UserService` sinifi `UserRepository` və `EmailService` ilə işləyirsə,
integration test bu üç komponentin birlikdə düzgün işlədiyini yoxlayır.

Integration testlər unit testlərdən yavaşdır amma real sistemdəki problemləri tapır.
Unit testlər hər komponentin ayrı-ayrılıqda düzgün işlədiyini göstərir, amma
komponentlər birləşdirildikdə yaranan problemləri (serialization xətaları, database
constraint-ləri, race condition-lar) tapa bilmir.

## Əsas Konseptlər (Key Concepts)

### Integration Test Növləri

1. **Component Integration**: İki sinif/modul arasında
2. **Database Integration**: Kod və database arasında
3. **API Integration**: Servislər arasında
4. **Third-party Integration**: Xarici API-larla

### Database Testing Strategiyaları

**Transaction Rollback**: Hər test transaction-da işləyir, sonra rollback edilir.
- Üstünlük: Sürətli, database təmiz qalır
- Mənfi: Transaction içindəki bəzi davranışlar (deadlock) test edilə bilmir

**Migrate Fresh**: Hər test suite-dən əvvəl database sıfırlanır.
- Üstünlük: Təmiz mühit
- Mənfi: Yavaş

**Seeding**: Əvvəlcədən data doldurulur.
- Üstünlük: Mürəkkəb ssenariləri test etmək asandır
- Mənfi: Seed data maintain etmək çətindir

### Test Database

Production database-dən ayrı test database istifadə edin:

```env
# .env.testing
DB_CONNECTION=sqlite
DB_DATABASE=:memory:

# və ya ayrı MySQL database
DB_CONNECTION=mysql
DB_DATABASE=app_testing
```

## Praktiki Nümunələr (Practical Examples)

### Repository Pattern Integration Test

```php
// app/Repositories/UserRepository.php
class UserRepository
{
    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    public function createWithProfile(array $userData, array $profileData): User
    {
        return DB::transaction(function () use ($userData, $profileData) {
            $user = User::create($userData);
            $user->profile()->create($profileData);
            return $user->load('profile');
        });
    }

    public function getActiveUsers(): Collection
    {
        return User::where('is_active', true)
            ->where('last_login_at', '>=', now()->subDays(30))
            ->get();
    }
}

// tests/Integration/Repositories/UserRepositoryTest.php
class UserRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private UserRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new UserRepository();
    }

    public function test_find_by_email_returns_user(): void
    {
        $user = User::factory()->create(['email' => 'test@example.com']);

        $found = $this->repository->findByEmail('test@example.com');

        $this->assertNotNull($found);
        $this->assertEquals($user->id, $found->id);
    }

    public function test_find_by_email_returns_null_when_not_found(): void
    {
        $found = $this->repository->findByEmail('nonexistent@example.com');
        $this->assertNull($found);
    }

    public function test_create_with_profile_creates_both_records(): void
    {
        $user = $this->repository->createWithProfile(
            ['name' => 'John', 'email' => 'john@test.com', 'password' => 'secret'],
            ['bio' => 'Developer', 'avatar' => 'default.jpg']
        );

        $this->assertDatabaseHas('users', ['email' => 'john@test.com']);
        $this->assertDatabaseHas('profiles', [
            'user_id' => $user->id,
            'bio' => 'Developer',
        ]);
        $this->assertNotNull($user->profile);
    }

    public function test_create_with_profile_rolls_back_on_failure(): void
    {
        try {
            $this->repository->createWithProfile(
                ['name' => 'John', 'email' => 'john@test.com', 'password' => 'secret'],
                ['bio' => null] // Required field null - fail edəcək
            );
        } catch (\Exception $e) {
            // Transaction rollback etməli idi
            $this->assertDatabaseMissing('users', ['email' => 'john@test.com']);
        }
    }

    public function test_get_active_users(): void
    {
        // Active user
        User::factory()->create([
            'is_active' => true,
            'last_login_at' => now()->subDays(5),
        ]);

        // Inactive user
        User::factory()->create(['is_active' => false]);

        // Active but old login
        User::factory()->create([
            'is_active' => true,
            'last_login_at' => now()->subDays(60),
        ]);

        $activeUsers = $this->repository->getActiveUsers();

        $this->assertCount(1, $activeUsers);
    }
}
```

### Service Layer Integration Test

```php
// app/Services/OrderService.php
class OrderService
{
    public function __construct(
        private OrderRepository $orderRepository,
        private InventoryService $inventoryService,
        private PaymentService $paymentService,
    ) {}

    public function placeOrder(User $user, array $items): Order
    {
        // Check inventory
        foreach ($items as $item) {
            if (!$this->inventoryService->isAvailable($item['product_id'], $item['quantity'])) {
                throw new OutOfStockException("Product {$item['product_id']} is out of stock");
            }
        }

        // Create order
        $order = $this->orderRepository->create($user, $items);

        // Reserve inventory
        foreach ($items as $item) {
            $this->inventoryService->reserve($item['product_id'], $item['quantity']);
        }

        return $order;
    }
}

// tests/Integration/Services/OrderServiceTest.php
class OrderServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_place_order_creates_order_and_reserves_inventory(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['stock' => 10]);

        $service = app(OrderService::class);

        $order = $service->placeOrder($user, [
            ['product_id' => $product->id, 'quantity' => 2],
        ]);

        // Order yaradılıb
        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'status' => 'pending',
        ]);

        // Order items yaradılıb
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        // Inventory azalıb
        $this->assertEquals(8, $product->fresh()->stock);
    }

    public function test_place_order_fails_when_out_of_stock(): void
    {
        $this->expectException(OutOfStockException::class);

        $user = User::factory()->create();
        $product = Product::factory()->create(['stock' => 0]);

        $service = app(OrderService::class);

        $service->placeOrder($user, [
            ['product_id' => $product->id, 'quantity' => 1],
        ]);
    }
}
```

## PHP/Laravel ilə Tətbiq (Implementation with PHP/Laravel)

### RefreshDatabase vs DatabaseTransactions

```php
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

// RefreshDatabase - Hər test class-dan əvvəl migrate:fresh çalışdırır
// Sonra hər test transaction-da işləyir
class UserTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true; // Seed-ləri çalışdır

    public function test_example(): void
    {
        // Database təmizdir, yalnız seed datası var
    }
}

// DatabaseTransactions - Migration etmir, yalnız transaction istifadə edir
// Database əvvəlcədən migrate olunmuş olmalıdır
class OrderTest extends TestCase
{
    use DatabaseTransactions;

    public function test_example(): void
    {
        // Hər test sonunda rollback olur
    }
}
```

### API Integration Test

```php
class ProductApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_products_returns_paginated_results(): void
    {
        Product::factory()->count(25)->create();

        $response = $this->getJson('/api/products');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'price', 'created_at'],
                ],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ])
            ->assertJsonCount(15, 'data'); // default pagination
    }

    public function test_create_product_stores_in_database(): void
    {
        $user = User::factory()->admin()->create();

        $response = $this->actingAs($user)->postJson('/api/products', [
            'name' => 'Test Product',
            'price' => 29.99,
            'description' => 'A test product',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'name' => 'Test Product',
                    'price' => 29.99,
                ],
            ]);

        $this->assertDatabaseHas('products', [
            'name' => 'Test Product',
            'price' => 29.99,
        ]);
    }

    public function test_create_product_requires_authentication(): void
    {
        $response = $this->postJson('/api/products', [
            'name' => 'Test',
            'price' => 10,
        ]);

        $response->assertStatus(401);
    }
}
```

### Cache Integration Test

```php
class CachedProductServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_products_are_cached(): void
    {
        Cache::flush();

        Product::factory()->count(5)->create();
        $service = app(CachedProductService::class);

        // İlk çağırış - database-dən oxuyur
        $products = $service->getAll();
        $this->assertCount(5, $products);

        // Yeni product əlavə et
        Product::factory()->create();

        // İkinci çağırış - cache-dən oxuyur, hələ 5 olmalıdır
        $products = $service->getAll();
        $this->assertCount(5, $products);

        // Cache təmizlə
        $service->clearCache();

        // İndi 6 olmalıdır
        $products = $service->getAll();
        $this->assertCount(6, $products);
    }
}
```

### Queue Integration Test

```php
class OrderProcessingTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_completion_dispatches_notifications(): void
    {
        Queue::fake();

        $order = Order::factory()->create(['status' => 'processing']);

        $service = app(OrderService::class);
        $service->complete($order);

        $this->assertEquals('completed', $order->fresh()->status);

        Queue::assertPushed(SendOrderConfirmationEmail::class);
        Queue::assertPushed(UpdateInventory::class);
    }
}
```

## Interview Sualları

**S: Integration test nə vaxt yazmalıyıq?**
C: Komponentlər arası əlaqəni yoxlamaq lazım olduqda: database sorğuları, API
endpoint-ləri, cache davranışı, queue işləmsi, file system əməliyyatları. Unit
testlər ilə əhatə olunmayan "aradakı boşluqları" integration testlər bağlayır.

**S: RefreshDatabase ilə DatabaseTransactions arasındakı fərq?**
C: RefreshDatabase hər test sinifindən əvvəl `migrate:fresh` çalışdırır, sonra
transaction istifadə edir. DatabaseTransactions isə yalnız transaction ilə işləyir,
migration etmir. RefreshDatabase daha təmiz amma yavaşdır.

**S: Test database üçün SQLite istifadə etməyin üstünlük və mənfilikləri?**
C: Üstünlüklər: Çox sürətli (in-memory), quraşdırma tələb etmir. Mənfiliklər:
MySQL/PostgreSQL-dən fərqli davranışlar (foreign key, JSON, full-text search),
production mühitini tam əks etdirmir.

**S: Integration testlər niyə unit testlərdən yavaşdır?**
C: Real I/O əməliyyatları (database read/write, network, file system) CPU əməliyyatlarından
min dəfələrlə yavaşdır. Hər test üçün database setup/teardown vaxt alır.

**S: Test isolation-u integration testlərdə necə təmin edirsiniz?**
C: Transaction rollback, database refresh, cache flush, queue fake istifadə edərək
hər testin təmiz mühitdə başlamasını təmin edirik.

## Best Practices / Anti-Patterns

### Best Practices
- Test database üçün ayrı `.env.testing` faylı istifadə edin
- Factory-lər ilə test data yaradın, manual insert etməyin
- Hər testin öz datasını yaratmasını təmin edin (shared state olmasın)
- Transaction rollback istifadə edin (sürət üçün)
- CI/CD-də production ilə eyni database engine istifadə edin

### Anti-Patterns
- **Shared test data**: Testlər arasında paylaşılan data
- **Order dependency**: Testlər müəyyən sırada işləməli olur
- **Production database**: Test üçün production DB istifadə etmək
- **No cleanup**: Test sonunda datanı təmizləməmək
- **Slow tests**: Hər testdə migrate:fresh çalışdırmaq
- **Too broad**: Bir integration testdə çoxlu şey yoxlamaq
