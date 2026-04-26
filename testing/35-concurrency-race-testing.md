# Concurrency və Race Condition Testing (Senior)
## İcmal

**Concurrency testing** — paralel işləyən kodun (thread-lər, proses-lər, async tapşırıqlar)
düzgünlüyünü yoxlamaq prosesidir.

**Race condition** — iki və ya daha çox thread/proses eyni resursa eyni vaxtda **təhlükəsiz
olmayan** şəkildə müraciət etdikdə yaranan xəta. Nəticə icra sırasından asılıdır.

**Tipik problemlər:**
- **Race condition** — təkrarlanmayan nəticələr
- **Deadlock** — thread-lər bir-birini gözləyir, heç biri irəlilə bilmir
- **Starvation** — bir thread həmişə resursdan məhrum olur
- **Lost update** — paralel yazı zamanı bir yeniləmə itir
- **Dirty read** — commit edilməmiş data oxunur

**Niyə test etmək çətindir?**
- **Non-deterministic** — hər işlətmədə fərqli nəticə
- **Reproduce çətin** — bug bəzən baş verir, bəzən yox
- **Timing-dependent** — kompüterin sürətindən asılı
- **Heisenbug** — debugger altında görünmür

## Niyə Vacibdir

- **Race condition gizliliyi**: Concurrency bug-ları lokal development-də nadirən göründür, production-da yüksək load altında pik saatlarda çıxır — test olmadan root cause tapmaq saatlar alır
- **Data corruption riski**: İki paralel request eyni resursda yazanda corrupted state yarana bilər — test olmadan tapılmaz
- **Idempotency**: Payment, order creation kimi kritik əməliyyatlar double-submit qarşısında qorunmalıdır
- **Laravel queue**: Parallel queue worker-ları eyni job-u emal edə bilər — unique job test edilməlidir

## Əsas Anlayışlar

### 1. Race Condition Nümunəsi

```
İlkin balans: 100
Thread A: balansı oxu (100), +50 əlavə et, yaz (150)
Thread B: balansı oxu (100), +30 əlavə et, yaz (130)

Gözlənilən: 180
Real: 130 və ya 150 (hansı sonuncu yazırsa)
```

### 2. Həll Mexanizmləri

| Mexanizm | Təsvir | İstifadə |
|----------|--------|----------|
| **Pessimistic lock** | `SELECT ... FOR UPDATE` | Yüksək konflikt |
| **Optimistic lock** | Version column + retry | Aşağı konflikt |
| **Atomic operations** | `increment()`, `UPDATE ... SET x=x+1` | Sadə sayğaclar |
| **Transactions** | DB transaction | ACID qaydaları |
| **Mutex/Semaphore** | Process-level lock | Kritik bölmə |
| **Queue** | Serialize işləmə | Async tapşırıqlar |

### 3. Test Strategiyaları

- **Stress testing** — yüksək yüklə race-i provoke et
- **Fuzz timing** — random sleep əlavə et
- **Thread sanitizer** — runtime race detector
- **Formal verification** — model checking
- **Chaos testing** — random latency, failure

### 4. Async Testing Növləri

- **Job/Queue testing** — background job-lar düzgün işləyir?
- **Event handling** — eventlər sıralı və ya paralel işlənir?
- **WebSocket** — concurrent connection-lar
- **HTTP concurrent requests** — eyni endpoint, eyni vaxtda

### 5. Deadlock

```
Thread A: lock(X) → lock(Y)
Thread B: lock(Y) → lock(X)
→ Hər ikisi gözləyir, heç kim ilərləmir
```

**Həll**: həmişə eyni sırada lock alın (resource ordering).

## Praktik Baxış

### Best Practices
1. **Atomic operations** — `increment()`, `UPDATE x=x+1`
2. **Transaction-da lock** — `lockForUpdate()`
3. **Idempotency key** — duplicate request qoruması
4. **Resource ordering** — deadlock qarşısı
5. **Queue worker isolation** — `ShouldBeUnique`
6. **Cache lock** — kritik bölmə üçün `Cache::lock()`
7. **Concurrent test** — pcntl_fork və ya Http::pool
8. **Stress test** — real-dünya yükü simulyasiyası
9. **Timeout hər lock-a** — sonsuz gözləmə qarşısı
10. **Retry logic** — optimistic lock xətasında

### Anti-Patterns
- **Read-modify-write** transaction-sız — klassik race
- **File-based lock** — network FS-də etibarsız
- **Sleep-based synchronization** — timing-ə bel bağlamaq
- **Global mutable state** — thread-safe deyil
- **No timeout on lock** — deadlock → sistem dayanır
- **Optimistic lock without retry** — hər konfliktdə UX pisləşir
- **Forgetting FOR UPDATE** — Laravel default SELECT lock-suzdur
- **Long transactions** — lock müddətini artırır
- **Ignoring deadlock errors** — silent data corruption
- **Testing yalnız happy path** — race yalnız yük altında görünür

### Concurrency Testing Checklist
- [ ] Kritik bölmələrdə lock (pessimistic/optimistic)
- [ ] Atomic DB operations istifadə olunur
- [ ] Idempotency key POST-larda
- [ ] Concurrent test-lər (pcntl_fork, Http::pool)
- [ ] Queue unique jobs
- [ ] Resource ordering ilə deadlock qarşısı
- [ ] Lock timeout-ları var
- [ ] Bus::fake ilə async test
- [ ] Stress test regresiyada
- [ ] Chaos testing staging-də

## Nümunələr

### Nümunə 1: Stock overselling
```
Məhsul stoku: 1
10 müştəri eyni vaxtda "Al" düyməsinə basır
Race condition olmadan: 10 sifariş qeydə alınır, amma yalnız 1 məhsul var
Həll: Pessimistic lock
```

### Nümunə 2: Double charge
```
İstifadəçi ödəniş düyməsinə 2 dəfə basır (lag-a görə)
Race: iki payment intent yaranır
Həll: Idempotency key
```

### Nümunə 3: Deadlock
```
Transfer A → B (lock A, sonra B)
Transfer B → A (lock B, sonra A) 
→ Deadlock
Həll: həmişə account id-si kiçik olanı əvvəl lock et
```

## Praktik Tapşırıqlar

### 1. Race Condition Nümunəsi (SUT)

```php
// Problemli kod
class BalanceService
{
    public function deposit(User $user, float $amount): void
    {
        $balance = $user->balance;  // Oxu
        $newBalance = $balance + $amount;  // Hesabla
        $user->update(['balance' => $newBalance]);  // Yaz
        // → Race condition: A və B eyni anda oxusa, biri itir
    }
}

// Düzgün - atomic
class BalanceService
{
    public function deposit(User $user, float $amount): void
    {
        User::where('id', $user->id)->increment('balance', $amount);
    }
}

// Və ya pessimistic lock
class BalanceService
{
    public function deposit(User $user, float $amount): void
    {
        DB::transaction(function () use ($user, $amount) {
            $locked = User::where('id', $user->id)->lockForUpdate()->first();
            $locked->balance += $amount;
            $locked->save();
        });
    }
}
```

### 2. Race Condition Testing - pcntl_fork

```php
// tests/Feature/RaceConditionTest.php
namespace Tests\Feature;

use Tests\TestCase;
use App\Models\{User, Product};

class StockRaceConditionTest extends TestCase
{
    /** @test */
    public function stock_is_not_oversold_with_concurrent_requests(): void
    {
        if (!function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl uzantısı yoxdur');
        }

        $product = Product::factory()->create(['stock' => 1]);

        $pids = [];
        $concurrentBuyers = 10;

        for ($i = 0; $i < $concurrentBuyers; $i++) {
            $pid = pcntl_fork();

            if ($pid === 0) {
                // Child process
                try {
                    app(\App\Services\OrderService::class)
                        ->placeOrder(User::factory()->create(), $product, 1);
                    exit(0); // Uğur
                } catch (\App\Exceptions\OutOfStockException $e) {
                    exit(1); // Stok yox
                }
            } else {
                $pids[] = $pid;
            }
        }

        // Valideyn gözləyir
        $successes = 0;
        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
            if (pcntl_wexitstatus($status) === 0) {
                $successes++;
            }
        }

        // Yalnız 1 nəfər uğurla almalıdır
        $this->assertEquals(1, $successes);

        $product->refresh();
        $this->assertEquals(0, $product->stock);
    }
}
```

### 3. Pessimistic Lock Test

```php
// SUT
class OrderService
{
    public function placeOrder(User $user, Product $product, int $qty): Order
    {
        return DB::transaction(function () use ($user, $product, $qty) {
            // Lock product row
            $locked = Product::where('id', $product->id)
                ->lockForUpdate()
                ->first();

            if ($locked->stock < $qty) {
                throw new OutOfStockException();
            }

            $locked->decrement('stock', $qty);

            return Order::create([
                'user_id' => $user->id,
                'product_id' => $product->id,
                'quantity' => $qty,
                'total' => $locked->price * $qty,
            ]);
        });
    }
}

// Test
class PessimisticLockTest extends TestCase
{
    /** @test */
    public function lock_for_update_prevents_overselling(): void
    {
        $product = Product::factory()->create(['stock' => 5]);

        // 10 paralel transaction
        $results = $this->runConcurrent(10, function () use ($product) {
            $user = User::factory()->create();
            try {
                app(OrderService::class)->placeOrder($user, $product, 1);
                return 'success';
            } catch (OutOfStockException $e) {
                return 'out_of_stock';
            }
        });

        $successes = array_count_values($results)['success'] ?? 0;

        $this->assertEquals(5, $successes);
        $this->assertEquals(0, $product->fresh()->stock);
    }

    private function runConcurrent(int $count, \Closure $callback): array
    {
        $results = [];
        $pids = [];
        $tmpDir = sys_get_temp_dir();

        for ($i = 0; $i < $count; $i++) {
            $pid = pcntl_fork();
            if ($pid === 0) {
                $result = $callback();
                file_put_contents("$tmpDir/result_$i", $result);
                exit(0);
            }
            $pids[] = $pid;
        }

        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }

        for ($i = 0; $i < $count; $i++) {
            $results[] = file_get_contents("$tmpDir/result_$i");
            unlink("$tmpDir/result_$i");
        }

        return $results;
    }
}
```

### 4. Optimistic Lock Test

```php
// SUT
class ArticleService
{
    public function update(Article $article, array $data): Article
    {
        $article->fill($data);

        if (!$article->save()) {
            throw new StaleDataException('Məqalə başqa istifadəçi tərəfindən dəyişdirildi');
        }

        return $article;
    }
}

// Article model-də version column
class Article extends Model
{
    public function save(array $options = []): bool
    {
        if ($this->exists) {
            $affected = static::where('id', $this->id)
                ->where('version', $this->getOriginal('version'))
                ->update(array_merge(
                    $this->getDirty(),
                    ['version' => $this->version + 1]
                ));

            return $affected > 0;
        }

        return parent::save($options);
    }
}

// Test
class OptimisticLockTest extends TestCase
{
    /** @test */
    public function concurrent_updates_second_one_fails(): void
    {
        $article = Article::factory()->create(['title' => 'Original', 'version' => 1]);

        // User A və User B eyni article-i yükləyir
        $articleA = Article::find($article->id);
        $articleB = Article::find($article->id);

        // User A save edir - uğurlu
        $articleA->title = 'Updated by A';
        $this->assertTrue($articleA->save());

        // User B save etməyə çalışır - köhnə version-la
        $articleB->title = 'Updated by B';

        $this->expectException(StaleDataException::class);
        app(ArticleService::class)->update($articleB, ['title' => 'Updated by B']);
    }
}
```

### 5. Laravel HTTP Concurrent Requests

```php
use Illuminate\Support\Facades\Http;

class ConcurrentHttpTest extends TestCase
{
    /** @test */
    public function api_handles_concurrent_requests(): void
    {
        $product = Product::factory()->create(['stock' => 3]);

        // Laravel Http::pool - async concurrent requests
        $responses = Http::pool(fn ($pool) => [
            $pool->as('req1')->post('/api/purchase', ['product_id' => $product->id]),
            $pool->as('req2')->post('/api/purchase', ['product_id' => $product->id]),
            $pool->as('req3')->post('/api/purchase', ['product_id' => $product->id]),
            $pool->as('req4')->post('/api/purchase', ['product_id' => $product->id]),
            $pool->as('req5')->post('/api/purchase', ['product_id' => $product->id]),
        ]);

        $successes = collect($responses)->filter(fn($r) => $r->successful())->count();

        $this->assertEquals(3, $successes);
        $this->assertEquals(0, $product->fresh()->stock);
    }
}
```

### 6. Bus::fake for Async Job Testing

```php
use Illuminate\Support\Facades\Bus;
use App\Jobs\ProcessPayment;

class AsyncJobTest extends TestCase
{
    /** @test */
    public function payment_job_is_dispatched_correctly(): void
    {
        Bus::fake();

        $order = Order::factory()->create();

        app(PaymentService::class)->processOrder($order);

        Bus::assertDispatched(ProcessPayment::class, function ($job) use ($order) {
            return $job->orderId === $order->id;
        });

        Bus::assertDispatchedTimes(ProcessPayment::class, 1);
    }

    /** @test */
    public function jobs_are_chained_correctly(): void
    {
        Bus::fake();

        app(CheckoutService::class)->complete($order);

        Bus::assertChained([
            ProcessPayment::class,
            SendConfirmationEmail::class,
            UpdateInventory::class,
        ]);
    }

    /** @test */
    public function jobs_are_batched(): void
    {
        Bus::fake();

        app(BulkEmailService::class)->sendTo(User::factory()->count(100)->create());

        Bus::assertBatched(function ($batch) {
            return $batch->jobs->count() === 100;
        });
    }
}
```

### 7. Deadlock Simulation Test

```php
class DeadlockTest extends TestCase
{
    /** @test */
    public function transfer_in_consistent_order_prevents_deadlock(): void
    {
        $userA = User::factory()->create(['balance' => 100]);
        $userB = User::factory()->create(['balance' => 100]);

        $results = $this->runConcurrent(2, function () use ($userA, $userB) {
            try {
                app(TransferService::class)->transfer($userA, $userB, 10);
                return 'success';
            } catch (\Exception $e) {
                return 'failed: ' . $e->getMessage();
            }
        });

        foreach ($results as $result) {
            $this->assertEquals('success', $result);
        }
    }
}

// Düzgün implementasiya - consistent ordering
class TransferService
{
    public function transfer(User $from, User $to, float $amount): void
    {
        // ID sırasına görə lock (deadlock qarşısı)
        [$first, $second] = $from->id < $to->id
            ? [$from, $to]
            : [$to, $from];

        DB::transaction(function () use ($first, $second, $from, $to, $amount) {
            User::where('id', $first->id)->lockForUpdate()->first();
            User::where('id', $second->id)->lockForUpdate()->first();

            User::where('id', $from->id)->decrement('balance', $amount);
            User::where('id', $to->id)->increment('balance', $amount);
        });
    }
}
```

### 8. Idempotency Test

```php
class IdempotencyTest extends TestCase
{
    /** @test */
    public function duplicate_payment_requests_only_charge_once(): void
    {
        $idempotencyKey = (string) Str::uuid();

        $response1 = $this->postJson('/api/payments', [
            'amount' => 100,
            'idempotency_key' => $idempotencyKey,
        ]);

        $response2 = $this->postJson('/api/payments', [
            'amount' => 100,
            'idempotency_key' => $idempotencyKey,
        ]);

        $response1->assertOk();
        $response2->assertOk();

        // Eyni payment ID qaytarılır
        $this->assertEquals(
            $response1->json('payment_id'),
            $response2->json('payment_id')
        );

        // DB-də yalnız 1 ödəniş
        $this->assertDatabaseCount('payments', 1);
    }
}
```

### 9. Cache Lock Test

```php
use Illuminate\Support\Facades\Cache;

class CacheLockTest extends TestCase
{
    /** @test */
    public function cache_lock_prevents_concurrent_execution(): void
    {
        $executed = 0;

        $results = $this->runConcurrent(5, function () use (&$executed) {
            $lock = Cache::lock('critical-section', 10);

            if ($lock->get()) {
                try {
                    sleep(1);
                    return 'executed';
                } finally {
                    $lock->release();
                }
            }

            return 'blocked';
        });

        $this->assertContains('executed', $results);
    }
}
```

### 10. Queue Worker Concurrency Test

```php
class QueueConcurrencyTest extends TestCase
{
    /** @test */
    public function unique_jobs_are_not_processed_concurrently(): void
    {
        // SUT
        // class ProcessReport extends ShouldQueue implements ShouldBeUnique {
        //     public string $uniqueId;
        //     public function uniqueId(): string { return $this->uniqueId; }
        // }

        Bus::fake();

        ProcessReport::dispatch('report-123');
        ProcessReport::dispatch('report-123'); // Eyni ID

        Bus::assertDispatchedTimes(ProcessReport::class, 1);
    }
}
```

## Ətraflı Qeydlər

### S1: Race condition nədir?
**C:** İki və ya daha çox thread/process eyni resursa eyni zamanda müraciət etdikdə
yaranan xəta. Nəticə icra sırasından asılı olur və **təkrarlanmır**. Məsələn, iki
istifadəçi eyni vaxtda hesabdan pul çəkir — balans yoxlanılır, hər ikisi "OK" alır,
amma hesabda kifayət pul yoxdur.

### S2: Race condition-u necə test edirsiniz?
**C:**
- **pcntl_fork** ilə paralel proseslər yaratmaq
- **HTTP concurrent requests** (Http::pool)
- **Queue worker-lər** paralel işlətmək
- **Stress test** ilə yüksək yük
- **Timing fuzz** — random sleep əlavə etmək

Çətinlik: race condition **non-deterministic**-dir, bir test işləmədi deyə yoxdur demək
olmaz. Əvəzinə, səbəbi həll edən kod-u test etmək lazımdır.

### S3: Optimistic və pessimistic lock fərqi?
**C:**
- **Pessimistic**: Lock götür, dəyiş, açıq qoy. Digərləri gözləyir. `SELECT FOR UPDATE`.
  Yüksək konflikt halında yaxşı.
- **Optimistic**: Version column ilə, save zamanı yoxla, version dəyişibsə xəta ver.
  Aşağı konflikt halında sürətlidir (lock overhead yoxdur).

### S4: Deadlock-u necə qarşısını alırsınız?
**C:**
- **Resource ordering** — həmişə eyni sırada lock al (məs. user_id-ə görə)
- **Timeout** — lock-un maksimum müddəti
- **Try-lock** — deadlock-dan imtina
- **Deadlock detection** — DB avtomatik aşkar edib birini ləğv edir
- **Minimal locking** — yalnız lazım olanı lock et

### S5: Idempotency niyə lazımdır?
**C:** Şəbəkə xətaları və ya istifadəçi iki dəfə klikləsə, eyni əməliyyat iki dəfə
icra edilə bilər. **Idempotency key** (UUID) ilə server eyni açarı olan sorğunu
dublicate kimi tanıyır və yalnız bir dəfə icra edir. Stripe, AWS bunu istifadə edir.

### S6: Laravel-də Bus::fake nə edir?
**C:** Queue/Job-ları **mock edir** — həqiqətdə dispatch etmir, yalnız hansıların
dispatch edildiyini izləyir:
```php
Bus::fake();
ProcessPayment::dispatch($order);
Bus::assertDispatched(ProcessPayment::class);
```
Test sürətli olur və real queue worker lazım deyil.

### S7: pcntl_fork-un məhdudiyyətləri?
**C:**
- Yalnız **CLI-da** işləyir (web serverlərdə yox)
- **Linux/Mac** — Windows-da yoxdur
- **Process isolation** — hər child proses ayrı DB connection
- **Debugging çətin** — child process-lərdə breakpoint
- **Resource overhead** — proses yaratmaq bahalıdır

### S8: ShouldBeUnique nə edir?
**C:** Laravel interface-idir — eyni `uniqueId()`-li job-un paralel işlənməməsini
təmin edir. Cache lock ilə implementasiya olunur. Məsələn, eyni report-u iki
dəfə generate etməyin qarşısını alır.

### S9: Queue worker-lər concurrent işləyirsə race olur?
**C:** Bəli, bir neçə worker eyni anda job çəkə bilər. Laravel job-ları
**atomically reserve** edir (`reserved_at` column), amma:
- **DB updates** hələ də race edə bilər → `lockForUpdate()` istifadə et
- **External API** çağırışları idempotent olmalıdır
- **Unique jobs** üçün `ShouldBeUnique` interface

### S10: Chaos engineering necə race condition tapmağa kömək edir?
**C:** Production-a və ya staging-ə **random latency, network failure, CPU spike**
salaraq sistemi stress altına qoyursunuz. Race condition-lar normal şəraitdə gizli
qala bilər, amma xaos altında ortaya çıxır. Netflix **Chaos Monkey** bunun klassik
nümunəsidir.

## Əlaqəli Mövzular

- [Database Testing (Middle)](10-database-testing.md)
- [Testing Events & Queues (Middle)](15-testing-events-queues.md)
- [Performance Testing (Senior)](20-performance-testing.md)
- [Testing Microservices (Lead)](37-testing-microservices.md)
- [Test Environment Management (Lead)](40-test-environment-management.md)
