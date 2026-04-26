# CQRS Read Model & Projection (Lead)

## Read Model nədir?

CQRS-in read tərəfi: write DB-dəki normalized data əvəzinə sorğuya tam uyğun, denormalized cədvəl.

**Problem:** Dashboard-da hər dəfə:
***Problem:** Dashboard-da hər dəfə üçün kod nümunəsi:*
```sql
-- Bu kod normalized cədvəllərdən dashboard məlumatı üçün mürəkkəb JOIN sorğusunu göstərir
SELECT o.*, u.name, u.email, COUNT(oi.id) as item_count, SUM(oi.price) as total
FROM orders o
JOIN users u ON o.user_id = u.id
JOIN order_items oi ON oi.order_id = o.id
WHERE o.user_id = 123
GROUP BY o.id
```
Bu sorğu hər request-də çalışır — JOIN, aggregation, index miss mümkündür.

**Həll (read model):**
```sql
-- Bu kod denormalized read model cədvəlindən JOIN olmadan sürətli sorğunu göstərir
SELECT * FROM order_summary_view WHERE user_id = 123
```
Cədvəl artıq `user_name`, `item_count`, `total` saxlayır. JOIN yoxdur, tam indexed.

---

## Projection — Read Model Necə Yaranır?

Projection: domain event-lərini dinləyib read model-i yaradan/yeniləyən handler.

```
// Bu kod event-lərin projector vasitəsilə read model-i necə yenilədiyi axınını göstərir
OrderPlaced event → Projector → order_summary_view-a INSERT
OrderShipped event → Projector → status = 'shipped' UPDATE
UserNameChanged event → Projector → bütün o user-in order-larında user_name UPDATE
```

**Denormalization tradeoff:** User adı dəyişdikdə bütün order-larda update lazımdır. Bu normal — read model-in qiyməti. Write-da bir dəfə, read-da minlərlə sürətli oxuma.

---

## Projection növləri

**Inline (Synchronous):**
Write əməliyyatı ilə eyni DB transaction-da read model yenilənir. Strongly consistent — write commit olduqda read model artıq yenilənib. Lakin write yavaşlayır (2 DB write eyni TX-də).

**Async:**
Event publish edilir, projector ayrı worker-da işləyir. Write sürətlidir. Qısa lag ola bilər (eventual consistency). Dashboard stats, listing kimi non-critical read-lar üçün idealdır.

---

## Niyə problem yaranır?

**Stale read:** User sifariş verir, dərhal sifariş listini yeniləyir — async projection hələ işlənməyib, köhnə data görür. Həll: kritik oxumalar write DB-dən (`orders` cədvəlindən), non-critical read model-dən.

**Fan-out problemi:** 1 user 1 milyon user-ə mesaj göndərir → 1 milyon write model yeniləməsi lazımdır. Twitter fan-out problemi. Həll: push (pre-compute) vs pull (query time) tradeoff — çox follower-lı celebrity-lər üçün pull daha uyğundur.

**Read model out of sync:** Projector bug-ı, event atlanması, ya da manual DB dəyişikliyi nəticəsində read model write model-dən fərqlənir. Həll: rebuild command — event store-dan sıfırdan replay.

---

## PHP İmplementasiyası

*PHP İmplementasiyası üçün kod nümunəsi:*
```php
// Bu kod async projector, query service, inline projection və rebuild command-ı göstərir
// Async projector — event bus-a subscribe edir
class OrderSummaryProjector
{
    // Yeni sifariş gəldikdə read model-ə insert edir
    public function onOrderPlaced(OrderPlaced $event): void
    {
        $user = User::find($event->customerId);

        DB::table('order_summary_view')->insert([
            'order_id'   => $event->orderId,
            'user_id'    => $event->customerId,
            'user_name'  => $user->name,   // Denormalized
            'user_email' => $user->email,
            'total'      => $event->totalAmount,
            'status'     => 'pending',
            'item_count' => count($event->items),
            'created_at' => $event->occurredAt,
        ]);
    }

    // Status dəyişdikdə yalnız status yeniləyir
    public function onOrderStatusChanged(OrderStatusChanged $event): void
    {
        DB::table('order_summary_view')
            ->where('order_id', $event->orderId)
            ->update(['status' => $event->newStatus]);
    }

    // User adı dəyişdikdə bütün order-larında user_name güncəllənir
    public function onUserProfileUpdated(UserProfileUpdated $event): void
    {
        DB::table('order_summary_view')
            ->where('user_id', $event->userId)
            ->update(['user_name' => $event->newName, 'user_email' => $event->newEmail]);
    }
}

// Query side — read model-dən oxuyur, JOIN yoxdur
class OrderQueryService
{
    public function getUserOrders(int $userId, ?string $status = null): array
    {
        return DB::table('order_summary_view')
            ->where('user_id', $userId)
            ->when($status, fn($q) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->toArray();
    }

    public function getDashboardStats(): array
    {
        // 60 saniyə cache — bu data eventual consistent ola bilər
        return Cache::remember('dashboard:stats', 60, fn() =>
            DB::table('order_summary_view')
                ->selectRaw('COUNT(*) as total_orders, SUM(total) as revenue,
                             COUNT(CASE WHEN status="pending" THEN 1 END) as pending')
                ->first()
        );
    }
}

// Inline projection — write ilə eyni TX-də (strongly consistent)
class OrderRepository
{
    public function save(Order $order): void
    {
        DB::transaction(function () use ($order) {
            DB::table('orders')->upsert($order->toArray(), ['id'], ['status', 'total']);

            // Eyni TX-də read model-i yenilə — consistency zəmanəti
            foreach ($order->pullDomainEvents() as $event) {
                app(OrderSummaryProjector::class)->handle($event);
            }
        });
    }
}

// Rebuild command — projection bug-ından sonra sıfırdan rebuild
class RebuildOrderSummaryCommand extends Command
{
    protected $signature = 'projections:rebuild:order-summary';

    public function handle(OrderSummaryProjector $projector): void
    {
        DB::table('order_summary_view')->truncate();

        DB::table('event_store')
            ->orderBy('occurred_at')
            ->chunk(1000, function ($events) use ($projector) {
                foreach ($events as $row) {
                    $projector->handle($this->deserialize($row));
                }
            });

        $this->info('Rebuild complete.');
    }
}
```

---

## Anti-patterns

- **Read model-i birbaşa yeniləmək (domain events olmadan):** Event-siz projection rebuild mümkün deyil, sync itir.
- **Read model-dən write əməliyyatı:** Read side oxumaq üçündür. Write əməliyyatı write side-a gedib event yaratmalıdır.
- **Çox sayda projection-a eyni event-i göndərmək (fan-out):** Hər projection-u ayrı queue-da async işlə, eyni anda hamısını sync etmə.
- **Async projection-u payment confirmation-da istifadə etmək:** Lag ola bilər, istifadəçi köhnə status görür. Kritik oxumalar write DB-dən.

---

## İntervyu Sualları

**1. CQRS read model nədir, niyə lazımdır?**
Write DB normalized (3NF), read-ə optimal deyil. Read model: denormalized, JOIN yoxdur, sorğuya uyğun shape. Event-lərdən yaranır (projection). Write performansına təsir etmədən read scale edilə bilər.

**2. Inline vs async projection tradeoff?**
Inline: strongly consistent, write yavaşlayır. Async: write sürətli, qısa lag (eventual consistent). Kritik oxumalar üçün inline və ya write DB-dən oxu. Stats, listing kimi non-critical üçün async.

**3. Projection rebuild nə zaman lazımdır?**
Projector-da bug düzəldildikdə, yeni read model field əlavə edildikdə, schema dəyişdikdə. Event Store varsa: truncate + event-ləri sıfırdan replay. Normal DB varsa: event log olmadığından rebuild çətindir — bu Event Sourcing-in əlavə faydalarından biridir.

**4. Stale read problemi necə həll edilir?**
Yazıdan dərhal sonra oxuma: write DB-dən oxu (bypass read model). Non-critical data: read model-dən, UI-da "data gecikmə ilə yenilənə bilər" bildirişi. Optimistic UI: client öz local state-ini göstərir, arxa planda refresh edir.

**5. Bir neçə read model eyni event-dən yaradıla bilərmi?**
Bəli — bu CQRS-in güclü tərəfidir. `OrderPlaced` event-i: `order_list_view` (admin panel üçün), `customer_order_view` (müştərinin özü üçün), `analytics_view` (hesabatlar üçün) — hər biri fərqli shape-də, fərqli sütunlarla. Hər use-case öz read model-inə sahibdir.

**6. Read model-i ayrı DB-yə köçürmək nə zaman mənalıdır?**
Write: PostgreSQL (ACID, normalized). Read model: Elasticsearch (full-text search), Redis (real-time leaderboard), ClickHouse (analytics). Write-dan ayrı scale edilir. Amma sync latency artır — CDC ya da async projection ilə sync saxlanılır.

---

## Anti-patternlər

**1. Read model-dən write əməliyyatı etmək**
`ReadModelController`-dən birbaşa DB-yə `UPDATE` göndərmək — CQRS prinsipi pozulur, read side-ın consistency zəmanəti itirilir, write event yaradılmır, projection rebuild-də bu dəyişiklik əks olunmur. Write əməliyyatları həmişə Command → Write Model → Domain Event axınından keçsin; read model yalnız oxumaq üçündur.

**2. Projection-ı rebuild etmək imkanı olmadan qurmaq**
Event log saxlanılmır, projection-lar birbaşa DB-yə yazılır — projection-da bug düzəldildikdə ya da yeni field lazım olduqda mövcud data yenidən hesablana bilmir. Event Store saxlayın; projection-ı həmişə event-lərdən sıfırdan rebuild etmək imkanı olsun.

**3. Kritik sorğular üçün async projection-dan istifadə etmək**
Ödəniş tamamlandıqdan sonra balance sorğusu async projection-dan gəlir — projection lag-ı olduğu üçün köhnə balans göstərilir, istifadəçi ödənişi görə bilmir. Kritik, real-time data (ödəniş statusu, balans) həmişə write DB-dən (ya da inline projection ilə) oxusun; async projection yalnız non-critical display üçün.

**4. Projection rebuild zamanı sistemi dayandırmaq**
`TRUNCATE read_model; REPLAY events...` əməliyyatı production-da işlənir — rebuild bitənə qədər read model boşdur, istifadəçilər data görmür. Shadow rebuild tətbiq edin: yeni projection cədvəli qurun, arxa planda doldurun, tamamlandıqda atomik olaraq əvvəlki ilə əvəzin.

**5. Çox sayda projection-a eyni event-i sync ilə göndərmək**
`OrderConfirmed` event-i 10 fərqli projection-ı sinxron yeniləyir — write latency 10x artır, bir projection xətası bütün write-ı bloklar. Projection-ları async queue-larla yeniləyin: event publish olunur, hər projection öz queue-sunu oxuyur; write trafiki projection-ların işlənmə sürətindən asılı olmur.

**6. Read model schema-sını versiyalanmadan dəyişmək**
`order_read_model` cədvəlinə yeni sütun əlavə edilir, amma köhnə projection event-ləri bu sütunu doldurmur — köhnə sıralar null, yeni sıralar dolu, inconsistent data yaranır. Projection schema dəyişikliyi ilə birlikdə full rebuild planı hazırlayın; ya da default dəyər ilə backward compatible dəyişiklik edin.
