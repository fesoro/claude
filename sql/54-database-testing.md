# Database Testing (Middle)

## Niye Database Testing Vacibdir?

- Migration-ler production-da ugursuz ola biler
- Query-ler gozlenilmeyen neticeler qaytara biler
- Constraint-ler duzgun islemedikde data corruption bas verir
- Mock test-ler real database davranisini tutmur (bunu yaxsi anlamaq lazimdir!)

## Test Database Strategiyalari

### 1. SQLite In-Memory (Suretli, Limitli)

```php
// phpunit.xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
```

**Problem:** MySQL/PostgreSQL-e xas feature-ler islemiyer (JSON operators, full-text search, specific data types).

### 2. Real Database (Etibarli)

```php
// .env.testing
DB_CONNECTION=mysql
DB_DATABASE=app_testing
DB_USERNAME=root
DB_PASSWORD=secret
```

**Ustunluk:** Production ile eyni davranis, butun feature-ler islenir.

### 3. Docker ile Test Database

```yaml
# docker-compose.test.yml
services:
  mysql-test:
    image: mysql:8.0
    environment:
      MYSQL_DATABASE: testing
      MYSQL_ROOT_PASSWORD: secret
    ports:
      - "3307:3306"
    tmpfs:
      - /var/lib/mysql  # RAM-da saxla - suretli!
```

## Test Isolation (Izolyasiya)

Her test **temiz** database ile islemelidi. Bir testin datasi digerin neticelerine tesir etmemelidir.

### 1. RefreshDatabase Trait

Her test sinfi ucun migration-leri yeniden icra edir. Transaction ile wrap edir - suretlidir.

```php
use Illuminate\Foundation\Testing\RefreshDatabase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_can_be_created()
    {
        $user = User::factory()->create();

        $order = Order::create([
            'user_id' => $user->id,
            'total' => 100.00,
        ]);

        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'total' => 100.00,
        ]);
    }
}
```

### 2. DatabaseTransactions Trait

Her testi transaction-a sarir ve sonunda ROLLBACK edir. Daha suretlidir amma bezi hallarda islemir.

```php
use Illuminate\Foundation\Testing\DatabaseTransactions;

class PaymentTest extends TestCase
{
    use DatabaseTransactions;

    public function test_payment_is_processed()
    {
        $order = Order::factory()->create(['total' => 50.00]);

        $payment = PaymentService::process($order);

        $this->assertEquals('completed', $payment->status);
        // Test bitdikde her sey ROLLBACK olunur
    }
}
```

### 3. LazilyRefreshDatabase (Laravel 10+)

Migration-leri yalniz lazim olanda icra edir. Daha suretlidir.

```php
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

class ProductTest extends TestCase
{
    use LazilyRefreshDatabase;
}
```

### RefreshDatabase vs DatabaseTransactions

| Xususiyyet | RefreshDatabase | DatabaseTransactions |
|------------|----------------|---------------------|
| **Suret** | Yavas (migration icra edir) | Suretli (rollback) |
| **Izolyasiya** | Tam | Tam |
| **Transaction test** | Diger transaction-lari test ede biler | Test ozunde transaction-dadir, nested transaction lazimdir |
| **Seeder** | `$seed = true` ile isleyir | Manual seed lazimdir |

## Factory Pattern

Test datasi yaratmaq ucun factory-ler istifade olunur.

```php
// database/factories/OrderFactory.php
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => 'pending',
            'total' => $this->faker->randomFloat(2, 10, 1000),
            'notes' => $this->faker->sentence(),
            'created_at' => now(),
        ];
    }

    // Custom state-ler
    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => 'paid',
            'paid_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => 'cancelled',
        ]);
    }

    public function withItems(int $count = 3): static
    {
        return $this->has(OrderItem::factory()->count($count));
    }
}

// Istifade
$order = Order::factory()->create();                    // 1 eded
$orders = Order::factory()->count(10)->create();        // 10 eded
$paidOrder = Order::factory()->paid()->create();        // Paid status
$orderWithItems = Order::factory()->withItems(5)->create(); // 5 item ile
```

## Migration Testing

```php
class MigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_orders_table_has_required_columns()
    {
        $this->assertTrue(Schema::hasTable('orders'));
        $this->assertTrue(Schema::hasColumn('orders', 'user_id'));
        $this->assertTrue(Schema::hasColumn('orders', 'status'));
        $this->assertTrue(Schema::hasColumn('orders', 'total'));
    }

    public function test_foreign_key_constraint_works()
    {
        // Movcud olmayan user_id ile order yaratmaq mumkun olmamalidir
        $this->expectException(\Illuminate\Database\QueryException::class);

        Order::create([
            'user_id' => 99999,
            'total' => 100,
            'status' => 'pending',
        ]);
    }

    public function test_unique_constraint_works()
    {
        $user = User::factory()->create(['email' => 'test@test.com']);

        $this->expectException(\Illuminate\Database\QueryException::class);

        User::factory()->create(['email' => 'test@test.com']);
    }
}
```

## Query Testing

```php
class QueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_expensive_query_uses_index()
    {
        // EXPLAIN ile yoxla
        $explain = DB::select(
            'EXPLAIN SELECT * FROM orders WHERE user_id = ? AND status = ?',
            [1, 'pending']
        );

        // "type" filed ALL olmamalidir (full table scan)
        $this->assertNotEquals('ALL', $explain[0]->type);
    }

    public function test_query_returns_correct_results()
    {
        // Setup
        $user = User::factory()->create();
        Order::factory()->count(3)->create([
            'user_id' => $user->id,
            'status' => 'paid',
        ]);
        Order::factory()->count(2)->create([
            'user_id' => $user->id,
            'status' => 'pending',
        ]);

        // Act
        $paidOrders = Order::where('user_id', $user->id)
            ->where('status', 'paid')
            ->get();

        // Assert
        $this->assertCount(3, $paidOrders);
        $paidOrders->each(fn ($order) =>
            $this->assertEquals('paid', $order->status)
        );
    }

    public function test_soft_delete_excludes_from_queries()
    {
        $order = Order::factory()->create();
        $order->delete();

        $this->assertDatabaseHas('orders', ['id' => $order->id]);
        $this->assertNull(Order::find($order->id));
        $this->assertNotNull(Order::withTrashed()->find($order->id));
    }
}
```

## Performance Testing

```php
class PerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_listing_orders_query_count()
    {
        Order::factory()->count(50)->create();

        // N+1 problem yoxlaması
        $queryCount = 0;
        DB::listen(function () use (&$queryCount) {
            $queryCount++;
        });

        $orders = Order::with('user', 'items')->paginate(20);

        // Eager loading ile 3 query olmalidir: orders + users + items
        $this->assertLessThanOrEqual(4, $queryCount);
    }

    public function test_bulk_insert_performance()
    {
        $start = microtime(true);

        $data = [];
        for ($i = 0; $i < 1000; $i++) {
            $data[] = [
                'user_id' => 1,
                'status' => 'pending',
                'total' => rand(10, 1000),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Chunk ile bulk insert
        foreach (array_chunk($data, 100) as $chunk) {
            Order::insert($chunk);
        }

        $duration = microtime(true) - $start;
        $this->assertLessThan(2.0, $duration, 'Bulk insert 2 saniyeden cox cekdi');
    }
}
```

## Seeder ile Test Data

```php
// database/seeders/TestDataSeeder.php
class TestDataSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::factory()->count(10)->create();

        $users->each(function ($user) {
            Order::factory()
                ->count(rand(1, 5))
                ->withItems(rand(1, 3))
                ->create(['user_id' => $user->id]);
        });
    }
}

// Test-de istifade
class ReportTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;
    protected string $seeder = TestDataSeeder::class;

    public function test_sales_report_calculates_correctly()
    {
        $total = Order::where('status', 'paid')->sum('total');

        $report = (new SalesReportService())->generate();

        $this->assertEquals($total, $report->totalRevenue);
    }
}
```

## Database Assertion-lar

```php
// Movcudluq yoxlamasi
$this->assertDatabaseHas('orders', [
    'user_id' => $user->id,
    'status' => 'paid',
]);

// Yoxluq yoxlamasi
$this->assertDatabaseMissing('orders', [
    'user_id' => $user->id,
    'status' => 'cancelled',
]);

// Sayi yoxlamasi
$this->assertDatabaseCount('orders', 5);

// Soft delete yoxlamasi
$this->assertSoftDeleted('orders', ['id' => $order->id]);

// Model refresh ve yoxlama
$order->refresh();
$this->assertEquals('paid', $order->status);
```

## Interview Suallari

1. **Test-lerde real database istifade etmeliyik yoxsa mock?**
   - Real database tercih olunur. Mock-lar SQL davranisini tam simulyasiya etmir. SQLite in-memory suretlidir amma MySQL-e xas feature-ler islemiyer.

2. **RefreshDatabase ile DatabaseTransactions ferqi?**
   - RefreshDatabase: Migration-leri yeniden icra edir, tam temiz database. DatabaseTransactions: Transaction ile wrap edir, ROLLBACK ile temizleyir - daha suretli.

3. **Test izolyasiyasi nedir ve niye vacibdir?**
   - Her test bir-birinden asili olmamalidir. Bir testin yaratdigi data diger testi tesir etmemelidir. Parallel test icra ucun mutleqdir.

4. **Factory pattern-in ustunlukleri?**
   - Temiz, oxunaqli test data yaratma. State-ler ile muxtellif scenari-ler. Relation-lar avtomatik yaranir.

5. **N+1 problemi test-de nece yoxlanilir?**
   - `DB::listen()` ile query sayini hesablayiriq ve gozlenilen sayla muqayise edirik.
