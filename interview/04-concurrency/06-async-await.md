# Async/Await Patterns (Middle ⭐⭐)

## İcmal
Async/await — asynchronous kodu synchronous kimi yazan sintaktik şəkərdir. Callback hell-dən Promise-ə, Promise-dən async/await-ə keçid — bu əsas abstraction-ların hər birini başa düşmək lazımdır. Bu mövzu interview-da I/O-bound task-ları effektiv idarə etmə bacarığınızı yoxlayır.

## Niyə Vacibdir
Hər modern backend sistemi I/O gözləmələri ilə doludur: database query, HTTP call, file read. Bunları blocking şəkildə icra etmək thread-i bağlayır, throughput-u aşağı salır. İnterviewer bu sualla sizin event loop, non-blocking I/O, error handling, cancellation, və async composition pattern-ları bildiyinizi yoxlayır.

---

## Əsas Anlayışlar

- **Synchronous:** Task bitənə qədər thread bloklanır — thread "gözləyir"; sadə, amma I/O gözləmədə CPU boşdur
- **Asynchronous:** Task başladılır, thread başqa işlə məşğul olur; task bitəndə callback/continuation çağırılır
- **Callback:** Task bitdikdə çağırılan funksiya — "callback hell" deeply nested callbacks; error handling çətin
- **Promise / Future:** Async operasiyonun gələcəkdəki nəticəsini təmsil edən object — `then()`/`catch()` chain; composable
- **async/await:** Promise-in synchronous kimi yazılması — syntactic sugar; error handling `try/catch` ilə
- **Coroutine:** Execution-u müvəqqəti dayandırıb sonra davam etdirə bilən funksiya — Kotlin, Python asyncio, Go goroutine (cooperative)
- **Event Loop:** Single-threaded async execution mexanizmi — Node.js, Python asyncio; I/O notification-ları monitor edir
- **Non-blocking I/O:** Kernel-in I/O tamamlandıqda notification göndərməsi — epoll (Linux), kqueue (macOS), IOCP (Windows)
- **Cooperative Multitasking:** Task-lar özləri "yield" edirlər — Node.js JS execution; `await` = yield point
- **Preemptive Multitasking:** OS thread-ləri istənilən anda kəsə bilər — Java, Go runtime
- **Promise.all:** Hamısı başlayır, hamısı tamamlananda cavab; bir fail → hamısı fail; "fail fast"
- **Promise.allSettled:** Hamısı başlayır, hamısı tamamlananda hər birinin status-unu qaytarır — bir fail digərlərini etkiləmir
- **Promise.race:** İlk tamamlanan qalib — timeout pattern üçün istifadə olunur
- **Promise.any:** İlk uğurlu tamamlanan qalib — bütünü fail olsaydı AggregateError
- **Structured Concurrency (Java 21):** Async task-ların lifecycle-ı parent-ə bağlıdır — child task-lar parent bitdikdə cancel olunur
- **Task Cancellation:** Async task-ı ləğv etmə — JavaScript `AbortController`, Go `context.WithCancel`, Java `Future.cancel()`
- **Async Generator:** Async məlumatı lazy stream kimi qaytarır — memory-efficient large data; Node.js `async function*`
- **Python asyncio:** `async def`, `await`, coroutine-lər, `asyncio.gather()`, `asyncio.create_task()`
- **PHP Swoole/ReactPHP:** PHP-də event loop — coroutine-lər, non-blocking I/O; Octane/Swoole ilə

---

## Praktik Baxış

**Interview-da yanaşma:**
- "Async = non-blocking I/O" — thread-i boşaldır, başqa task icra olunur
- Promise chain vs async/await oxunaqlıq fərqini izah edin
- `Promise.all` vs `Promise.allSettled` fərqini konkret nümunə ilə göstərin

**Follow-up suallar:**
1. "Async/await thread yaradırmı?" — Xeyr; mövcud thread-i I/O gözlərkən başqa task üçün boşaldır
2. "Promise.all vs Promise.allSettled fərqi?" — `.all` bir fail-da hamısı fail; `.allSettled` hamısını tamamlayır, hər birinin statusu var
3. "Async kodu sync kodu ilə qarışdırmaq niyə problem yaradır?" — Blocking call event loop-u tutur; bütün digər request-lər gözləyir
4. "CPU-bound task-ları async etmək niyə kömək etmir?" — CPU zamanı lazımdır, I/O gözləmə yoxdur; worker thread lazımdır
5. "Cancellation token nə vaxt lazımdır?" — Client disconnect olduqda, timeout-da — hələ qaytarılmamış task-ların işini dayandırmaq üçün
6. "Structured concurrency-nin faydası nədir?" — Child task-lar parent bitdikdə leak etmir; cancel olunur; error propagation düzgündür

**Code review red flags:**
- `async function` içərisindən `await` olmadan `Promise` qaytarmaq — unhandled rejection riski
- `for` loop içərisindən ardıcıl `await` — paralel edilə bilən task-ları sequential etmək
- Async context-də sync blocking call: Node.js-də `fs.readFileSync`, Python-da `time.sleep`
- `Promise.all` ilə failure isolation lazım olan case-də — `allSettled` istifadə edilməlidir

**Production debugging ssenariləri:**
- Node.js latency artır: event loop-da bir async callback CPU-intensive iş görür
- "Unhandled Promise rejection" log-ları: `.catch()` ya `try/catch` unutulub
- Timeout olmadan `Promise.all`: bir API yavaşlayanda bütün response bloklanır; `Promise.race` + timeout əlavə et
- Memory leak: Async task-lar cancel edilmədən birikir — `AbortController` tətbiq et

---

## Nümunələr

### Tipik Interview Sualı
"Üç API call-ı paralel icra etmək istəyirsiniz. Biri fail olsa, digərləri davam etsin. Nəticəni necə toplarsınız?"

### Güclü Cavab
`Promise.all` fail olan kimi bütün Promise-ləri reject edir — bu case-ə uyğun deyil. `Promise.allSettled` istifadə edərdim — bütün Promise-lər tamamlanana qədər gözləyir, hər birinin statusunu qaytarır: `fulfilled` ya `rejected`. Sonra nəticələri filter edib uğurluları götürürəm, failed olanları log edirəm.

Java-da `CompletableFuture.allOf()` + `exceptionally()` birləşdirməsi eyni effekti verir. Go-da goroutine-lər + `sync.WaitGroup` + error channel. Əlavə olaraq, production-da bu call-ların hər birinin timeout-u olmalıdır — `Promise.race` + `setTimeout` ilə ya da Java-da `orTimeout()` ilə.

### Kod Nümunəsi

```javascript
// ── Callback hell → Promise → async/await evolyusiyası ───────────

// 1) Callback hell — dərin iç-içə, error handling çətin
function getUserOrdersCallback(userId, callback) {
    getUser(userId, (err, user) => {
        if (err) return callback(err);
        getOrders(user.id, (err, orders) => {
            if (err) return callback(err);
            getProductsForOrders(orders, (err, products) => {
                if (err) return callback(err);
                callback(null, { user, orders, products });
            });
        });
    });
}

// 2) Promise chain — yaxşılaşdı, amma uzun chain-lər çətin oxunur
function getUserOrdersPromise(userId) {
    return getUser(userId)
        .then(user => getOrders(user.id).then(orders => ({ user, orders })))
        .then(({ user, orders }) =>
            getProductsForOrders(orders).then(products => ({ user, orders, products }))
        )
        .catch(err => { throw new Error(`Failed: ${err.message}`); });
}

// 3) async/await — ən oxunaqlı
async function getUserOrders(userId) {
    try {
        const user     = await getUser(userId);
        const orders   = await getOrders(user.id);
        const products = await getProductsForOrders(orders);
        return { user, orders, products };
    } catch (err) {
        throw new Error(`Failed to fetch user orders: ${err.message}`);
    }
}

// ── Paralel icra ─────────────────────────────────────────────────

async function fetchUserDashboard(userId) {
    // YANLIŞ: Sequential — hər biri digərini gözləyir (toplam: 300ms)
    const user    = await fetchUser(userId);    // 100ms
    const orders  = await fetchOrders(userId);  // 100ms
    const notifs  = await fetchNotifs(userId);  // 100ms

    // DÜZGÜN: Paralel — hamısı eyni anda başlayır (toplam: ~100ms)
    const [user2, orders2, notifs2] = await Promise.all([
        fetchUser(userId),
        fetchOrders(userId),
        fetchNotifs(userId),
    ]);

    // Biri fail olsa davam et (fail-tolerant)
    const results = await Promise.allSettled([
        fetchUser(userId),
        fetchOrders(userId),
        fetchNotifs(userId),
    ]);

    const succeeded = results
        .filter(r => r.status === 'fulfilled')
        .map(r => r.value);

    const failed = results
        .filter(r => r.status === 'rejected')
        .map(r => r.reason.message);

    if (failed.length > 0) {
        console.warn('Some requests failed:', failed);
    }

    return succeeded;
}

// ── Timeout ilə Promise ──────────────────────────────────────────
function withTimeout(promise, ms) {
    const timeout = new Promise((_, reject) =>
        setTimeout(() => reject(new Error(`Timeout after ${ms}ms`)), ms)
    );
    return Promise.race([promise, timeout]);
}

async function safeExternalCall(url) {
    try {
        const result = await withTimeout(fetch(url), 5000); // 5s timeout
        return result.json();
    } catch (err) {
        if (err.message.includes('Timeout')) {
            return null; // Fallback
        }
        throw err;
    }
}

// ── Cancellation: AbortController ───────────────────────────────
async function fetchWithCancellation(url) {
    const controller = new AbortController();

    // 10 saniyə sonra ləğv et
    const timeoutId = setTimeout(() => controller.abort(), 10_000);

    try {
        const response = await fetch(url, { signal: controller.signal });
        return await response.json();
    } catch (err) {
        if (err.name === 'AbortError') {
            console.log('Request cancelled');
            return null;
        }
        throw err;
    } finally {
        clearTimeout(timeoutId);
    }
}
```

```java
// ── Java CompletableFuture ────────────────────────────────────────
import java.util.concurrent.*;

public class AsyncPatterns {
    private final ExecutorService executor = Executors.newFixedThreadPool(10);

    // Parallel fetch + combine
    public CompletableFuture<UserDashboard> getDashboard(int userId) {
        CompletableFuture<User>        userFuture  = CompletableFuture
            .supplyAsync(() -> userService.findById(userId), executor);

        CompletableFuture<List<Order>> orderFuture = CompletableFuture
            .supplyAsync(() -> orderService.findByUser(userId), executor);

        CompletableFuture<List<Notif>> notifFuture = CompletableFuture
            .supplyAsync(() -> notifService.findByUser(userId), executor);

        // Hamısı bitdikdə birləşdir
        return CompletableFuture.allOf(userFuture, orderFuture, notifFuture)
            .thenApply(v -> new UserDashboard(
                userFuture.join(),
                orderFuture.join(),
                notifFuture.join()
            ));
    }

    // Chaining: fetch → transform → save
    public CompletableFuture<Void> processUser(int userId) {
        return CompletableFuture
            .supplyAsync(() -> userService.findById(userId), executor) // fetch
            .thenApply(user -> enrichUser(user))                        // transform
            .thenApply(user -> validateUser(user))                      // validate
            .thenAccept(user -> userService.save(user))                 // save
            .exceptionally(ex -> {
                log.error("Processing failed for user {}", userId, ex);
                return null;
            });
    }

    // Timeout
    public CompletableFuture<String> withTimeout(String url) {
        return CompletableFuture
            .supplyAsync(() -> httpClient.get(url), executor)
            .orTimeout(5, TimeUnit.SECONDS)       // Java 9+: timeout
            .exceptionally(ex -> {
                if (ex.getCause() instanceof TimeoutException) {
                    return "default-value"; // Fallback
                }
                throw new CompletionException(ex);
            });
    }

    // Java 21: Structured Concurrency
    public UserDashboard getDashboardStructured(int userId) throws Exception {
        try (var scope = new StructuredTaskScope.ShutdownOnFailure()) {
            StructuredTaskScope.Subtask<User>        userTask  = scope.fork(() -> userService.findById(userId));
            StructuredTaskScope.Subtask<List<Order>> orderTask = scope.fork(() -> orderService.findByUser(userId));

            scope.join();           // Hər ikisi bitənə qədər gözlə
            scope.throwIfFailed();  // Biri fail oldusa exception at

            return new UserDashboard(userTask.get(), orderTask.get());
        }
        // try block bitdikdə scope-da qalan task-lar cancel olunur — leak yoxdur
    }
}
```

```python
# ── Python asyncio ────────────────────────────────────────────────
import asyncio
import aiohttp
from typing import Any

async def fetch(session: aiohttp.ClientSession, url: str) -> dict:
    async with session.get(url, timeout=aiohttp.ClientTimeout(total=5)) as resp:
        resp.raise_for_status()
        return await resp.json()

async def get_dashboard(user_id: int) -> dict:
    async with aiohttp.ClientSession() as session:
        # Paralel başlat
        tasks = [
            asyncio.create_task(fetch(session, f"/users/{user_id}")),
            asyncio.create_task(fetch(session, f"/orders?user={user_id}")),
            asyncio.create_task(fetch(session, f"/notifs?user={user_id}")),
        ]

        # Biri fail olsa davam et
        results = await asyncio.gather(*tasks, return_exceptions=True)

        succeeded = {}
        keys = ['user', 'orders', 'notifs']
        for key, result in zip(keys, results):
            if isinstance(result, Exception):
                print(f"Warning: {key} fetch failed: {result}")
            else:
                succeeded[key] = result

        return succeeded

# ── Async generator — böyük data streaming ───────────────────────
async def stream_users(page_size: int = 100):
    page = 0
    while True:
        users = await fetch_users_page(page=page, limit=page_size)
        if not users:
            break
        for user in users:
            yield user  # Bir-bir yield — bütün data-nı yükləmir
        page += 1

async def process_all_users():
    async for user in stream_users():
        await process_user(user)  # Memory-efficient

# CPU-bound task-ı event loop-dan çıxar
import concurrent.futures

async def cpu_intensive_async(data: Any) -> Any:
    loop = asyncio.get_running_loop()
    with concurrent.futures.ProcessPoolExecutor() as pool:
        # Process pool-da icra et — event loop bloklanmır
        result = await loop.run_in_executor(pool, heavy_cpu_computation, data)
    return result

asyncio.run(get_dashboard(42))
```

```php
// ── PHP: Laravel async patterns ──────────────────────────────────
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Bus;
use Illuminate\Bus\Batch;

// Laravel HTTP pool — concurrent requests
function getDashboard(int $userId): array
{
    // Sequential — yavaş (3 × latency)
    // $user   = Http::get("/users/{$userId}")->json();
    // $orders = Http::get("/orders?user={$userId}")->json();

    // Concurrent — paralel (~1 × latency)
    [$user, $orders, $notifs] = Http::pool(fn ($pool) => [
        $pool->as('user')->get("/users/{$userId}"),
        $pool->as('orders')->get("/orders?user={$userId}"),
        $pool->as('notifs')->get("/notifs?user={$userId}"),
    ]);

    return [
        'user'   => $user->json(),
        'orders' => $orders->json(),
        'notifs' => $notifs->json(),
    ];
}

// Laravel Bus::batch — parallel job execution + monitoring
function processBulkOrders(array $orderIds): Batch
{
    $jobs = collect($orderIds)
        ->map(fn ($id) => new ProcessOrderJob($id))
        ->all();

    return Bus::batch($jobs)
        ->then(function (Batch $batch) {
            Log::info("All {$batch->totalJobs} orders processed");
        })
        ->catch(function (Batch $batch, \Throwable $e) {
            Log::error("Batch failed: {$e->getMessage()}");
        })
        ->finally(function (Batch $batch) {
            // Başarılı ya da xeyir — həmişə çalışır
            NotifyAdminJob::dispatch($batch->id);
        })
        ->allowFailures() // Bir job fail olsa digərləri davam edir
        ->dispatch();
}
```

---

## Praktik Tapşırıqlar

- Sequential vs `Promise.all` ilə 3 API call-ı benchmark edin, vaxt fərqini ölçün
- `Promise.allSettled` ilə biri fail olan 3-lü request-i handle edin, retry məntiqi əlavə edin
- Python asyncio ilə 100 URL-ə concurrent HTTP request göndərin (`aiohttp`), `asyncio.gather` vs sequential vaxtı ölçün
- Java `CompletableFuture` chain-i yazın: fetch → validate → transform → save; timeout + fallback əlavə edin
- PHP Laravel `Http::pool` ilə microservice çağırışlarını paralel edin, circuit breaker pattern əlavə edin

## Əlaqəli Mövzular
- `07-event-loop.md` — Async-in əsasındakı mexanizm
- `05-thread-pools.md` — Thread-based async alternativ
- `01-threads-vs-processes.md` — Thread model konteksti
- `02-race-conditions.md` — Shared state async context-də
