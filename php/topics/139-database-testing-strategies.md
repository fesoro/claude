# Database Testing Strategies

## Mündəricat
1. [Test Strategiyaları](#test-strategiyaları)
2. [Test Database Izolyasiyası](#test-database-izolyasiyası)
3. [Fixtures və Factories](#fixtures-və-factories)
4. [PHP İmplementasiyası](#php-implementasiyası)
5. [İntervyu Sualları](#intervyu-sualları)

---

## Test Strategiyaları

```
Strategiya 1 — Real DB (Integration Tests):
  ✓ Ən etibarlı — production ilə eyni davranış
  ✓ SQL syntax, constraint, index testlənir
  ✗ Yavaş
  ✗ Paralel test çətin

Strategiya 2 — In-memory DB (SQLite):
  ✓ Sürətli
  ✗ MySQL/PostgreSQL davranışını tam simulyasiya etmir
  ✗ JSON columns, full-text search, specific functions fərqlənir
  ✗ Production bug-larını gizlədir

Strategiya 3 — DB Mock:
  ✓ Ən sürətli (unit test)
  ✗ Repository davranışı test edilmir
  ✗ SQL/ORM bug-larını gizlədir
  ✗ Real DB ilə fərqləri mask edir

Tövsiyə (senior level):
  Unit tests:       Mock repository (biznes məntiqi)
  Integration tests: Real DB (repository, query, migration)
  
  "DB mock olan testlər keçdi, production-da fail etdi" — 
  bu yüksək riskdir, real DB testlər lazımdır
```

---

## Test Database Izolyasiyası

```
Strategiya 1 — Transaction rollback:
  Hər test əvvəlində transaction başlat
  Test sonunda rollback et
  ✓ Sürətli
  ✗ Nested transactions var ise problem
  ✗ TRUNCATE/DDL rollback edilmir

Strategiya 2 — Hər test üçün yeni DB:
  Docker container hər test run-da fresh DB
  ✓ Tam izolyasiya
  ✗ Daha yavaş (container başlatmaq)

Strategiya 3 — Fixtures + Truncate:
  Test əvvəlində cədvəlləri truncate et
  Test data-sını yüklə
  ✓ Sadə
  ✗ Foreign key-lər problematik ola bilər

Strategiya 4 — Dedicated test schema:
  PostgreSQL: hər test öz schema-sını yaradır
  Test sonunda drop edir
  ✓ Paralel testlər mümkün
```

---

## Fixtures və Factories

```
Fixtures (statik data):
  YAML/JSON faylda test data
  Hər test eyni data-dan istifadə edir
  ✓ Sadə
  ✗ Şişir, sürümləşmə çətin

Object Factories (dinamik data):
  Test-də lazımi data generasiya edilir
  Yalnız lazımi sahələri dəyişirsən
  ✓ Oxunaqlı testlər
  ✓ Minimal data
  ✓ Relationship-ləri idarə edir

Factory nümunəsi:
  $order = OrderFactory::new()
    ->with(['status' => 'placed'])
    ->withItems(3)
    ->create();

Faker library:
  Realistic fake data yaratmaq
  İsim, email, adres, şirkət adı

Seeder:
  Development DB-ni məna kəsb edən data ilə doldurmaq
  Test üçün deyil, development üçün
```

---

## PHP İmplementasiyası

```php
<?php
// Transaction rollback izolyasiyası (PHPUnit)
use PHPUnit\Framework\TestCase;

abstract class DatabaseTestCase extends TestCase
{
    protected static \PDO $db;
    private string $savepoint;

    public static function setUpBeforeClass(): void
    {
        static::$db = new \PDO(
            getenv('TEST_DATABASE_URL'),
            options: [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );
    }

    protected function setUp(): void
    {
        parent::setUp();
        static::$db->beginTransaction();
        $this->savepoint = 'test_' . uniqid();
        static::$db->exec("SAVEPOINT {$this->savepoint}");
    }

    protected function tearDown(): void
    {
        static::$db->exec("ROLLBACK TO SAVEPOINT {$this->savepoint}");
        static::$db->rollBack();
        parent::tearDown();
    }
}
```

```php
<?php
// Object Factory pattern
class OrderFactory
{
    private array $attributes = [];
    private int   $itemCount  = 1;

    public static function new(): self
    {
        return new self();
    }

    public function with(array $attributes): self
    {
        $clone = clone $this;
        $clone->attributes = array_merge($clone->attributes, $attributes);
        return $clone;
    }

    public function withItems(int $count): self
    {
        $clone = clone $this;
        $clone->itemCount = $count;
        return $clone;
    }

    public function placed(): self
    {
        return $this->with(['status' => 'placed']);
    }

    public function make(): Order
    {
        $defaults = [
            'id'         => OrderId::generate(),
            'customerId' => CustomerId::generate(),
            'status'     => 'draft',
            'createdAt'  => new \DateTimeImmutable(),
        ];

        $attrs = array_merge($defaults, $this->attributes);
        $order = new Order(...$attrs);

        for ($i = 0; $i < $this->itemCount; $i++) {
            $order->addItem(
                ProductId::generate(),
                quantity: rand(1, 5),
                unitPrice: Money::of(rand(10, 200), 'USD'),
            );
        }

        return $order;
    }

    public function create(OrderRepository $repository): Order
    {
        $order = $this->make();
        $repository->save($order);
        return $order;
    }
}

// Test-də istifadə:
class OrderRepositoryTest extends DatabaseTestCase
{
    private OrderRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new DoctrineOrderRepository(static::$db);
    }

    public function test_finds_order_by_id(): void
    {
        $order = OrderFactory::new()->placed()->create($this->repository);

        $found = $this->repository->findById($order->getId());

        $this->assertNotNull($found);
        $this->assertEquals($order->getId(), $found->getId());
        $this->assertEquals('placed', $found->getStatus()->value);
    }

    public function test_finds_orders_by_customer(): void
    {
        $customerId = CustomerId::generate();

        // 3 sifariş bu müştəriyə
        OrderFactory::new()->with(['customerId' => $customerId])->create($this->repository);
        OrderFactory::new()->with(['customerId' => $customerId])->create($this->repository);
        OrderFactory::new()->with(['customerId' => $customerId])->create($this->repository);

        // 1 fərqli müştəri
        OrderFactory::new()->create($this->repository);

        $orders = $this->repository->findByCustomer($customerId);

        $this->assertCount(3, $orders);
    }
}
```

```php
<?php
// Testcontainers PHP ilə real Docker DB
// composer require testcontainers/testcontainers

use Testcontainers\Container\GenericContainer;
use Testcontainers\Wait\WaitForLog;

class TestContainerSetup
{
    private static GenericContainer $postgres;
    private static string $dsn;

    public static function start(): void
    {
        self::$postgres = GenericContainer::make('postgres:16')
            ->withEnv('POSTGRES_DB',       'testdb')
            ->withEnv('POSTGRES_USER',     'test')
            ->withEnv('POSTGRES_PASSWORD', 'test')
            ->withExposedPort(5432)
            ->withWait(new WaitForLog('database system is ready'))
            ->run();

        $port    = self::$postgres->getMappedPort(5432);
        self::$dsn = "pgsql:host=127.0.0.1;port={$port};dbname=testdb";

        // Migration-ları çalışdır
        self::runMigrations(self::$dsn);
    }

    public static function getDsn(): string
    {
        return self::$dsn;
    }

    public static function stop(): void
    {
        self::$postgres->stop();
    }

    private static function runMigrations(string $dsn): void
    {
        // Doctrine Migrations, Phinx, custom migration runner
        shell_exec("php bin/console doctrine:migrations:migrate --no-interaction --env=test");
    }
}
```

---

## İntervyu Sualları

- DB mock olan testlər niyə etibarsız ola bilər?
- Transaction rollback test izolyasiyası nə zaman işləmir?
- Object Factory, statik fixture-dən nə üstündür?
- Testcontainers nədir? Nə üçün istifadə edilir?
- Paralel DB testləri necə izolyasiya edilir?
- Migration-ların test edilməsi lazımdırmı? Necə?
