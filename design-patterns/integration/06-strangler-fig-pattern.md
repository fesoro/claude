# Strangler Fig Pattern (Senior ⭐⭐⭐)

## İcmal

Strangler Fig Pattern — legacy sistemi tam söküb yenidən yazmaq əvəzinə, tədricən yeni sistemlə sarıyaraq əvəz edən miqrasiya yanaşması. Martin Fowler 2004-cü ildə tropik strangler fig ağacına bənzədərək adlandırdı: yeni sistem legacy-nin ətrafında böyüyür, zamanla onu tamamilə sarıyır. Proxy bütün trafikin keçdiyi qapı olur; hər köçürülmüş modul yeni sistemə yönləndirilir.

## Niyə Vacibdir

"Big Bang Rewrite" — legacy-ni birdən sıfırdan yazmaq — çox vaxt uğursuz olur: aylar/illər sürür, business tələblər dəyişir, test coverage çatmır, paralel sistemlər divergent olur. Strangler Fig: hər sprint bir modul köçürülür, deploy edilir, test edilir. Risk minimal, rollback asandır, business davam edir.

## Əsas Anlayışlar

- **Proxy/Facade**: bütün traffic bu qatdan keçir; köçürülmüş modullar yeni sistemə, qalanlar legacy-ə yönləndirilir
- **Branch by Abstraction**: legacy-ni birbaşa dəyişdirməmək — əvvəlcə interface yarad, sonra yeni implementasiya yaz, sonra feature flag ilə keçid et
- **Shadow Mode**: yeni sistemə paralel sorğu göndər, nəticəni log-la, amma real cavab legacy-dən gəlir; divergence-ı detect et
- **Feature Flag**: trafiki faiz-faiz yeni sistemə keçir; rollback = flag-ı kapat
- **Miqrasiya ardıcıllığı**: az dependency olan modullardan başla; core business (payment, order) sona saxla

## Praktik Baxış

- **Real istifadə**: monolit → mikroservis; köhnə framework → Laravel; SOAP API → REST; PHP 5 → PHP 8
- **Trade-off-lar**: inkremental, az risk, business davam edir; lakin miqrasiya uzun sürərsə iki sistemi paralel maintain etmək xərci artır; data sync mürəkkəbdir; proxy qatı əlavə latency əlavə edir
- **İstifadə etməmək**: sistem həqiqətən çox kiçik və tam yenidən yazmaq daha sürətlidirsə; legacy-nin heç bir business dəyəri qalmamışdırsa
- **Common mistakes**: miqrasiyanı ən kritik moduldan başlamaq; data miqrasiyasını sonraya saxlamaq; shadow mode-u atlamaq; feature flag olmadan birdən açmaq

## Anti-Pattern Nə Zaman Olur?

**Miqrasiyanı heç bitirməmək:**
"Geçici" deyə qoyulan proxy 2 il sonra hələ işləyir. Legacy modul heç köçürülmür, iki sistem paralel maintain edilir. Hər sprint miqrasiya commitment-i olmalı, modul-by-modul completion tracking edilməlidir. "Definition of Done" — legacy modul söndürülməsidir.

**İki sistemdə data sync problemi:**
Keçid dönəmündə legacy DB-si ilə yeni DB-si eyni data-nı saxlayır. Biri dəyişdikdə digəri avtomatik güncəllənmirsə inkonsistentlik baş verir. Dual-write + reconciliation job lazımdır; schema dəyişiklikləri hər iki sistemdə planlaşdırılmalıdır (expand-contract pattern).

**Proxy olmadan paralel sistem:**
Trafiki idarə edən proxy qatı olmadan iki sistemin paralel işləməsi mümkün deyil — istifadəçilər ya köhnə, ya yeni sistemə düşür, nəzarətsiz. Facade/proxy qatı qurun ki, routing mərkəzləşsin.

**Shadow mode-u atlamaq:**
Yeni sistemi birbaşa production trafikinə vermək — gizli edge case-lər real istifadəçiləri əsir edir. Shadow mode mütləq: cavablar müqayisə edilsin, divergence log-lansın, yalnız sonra real trafik keçirilsin.

## Nümunələr

### Ümumi Nümunə

```
Mərhələ 1: Proxy əlavə edilir, hər şey legacy-ə gedir
  Client → Proxy → Legacy System

Mərhələ 2: Auth modulu köçürüldü
  Client → Proxy → Auth: New System
                 → Orders, Payments: Legacy System

Mərhələ 3: Hamısı köçürüldü, legacy söndürülür
  Client → Proxy → New System
  (Legacy söndürülür, arxivlənir)
```

### PHP/Laravel Nümunəsi

```php
<?php

// Branch by Abstraction — interface + feature flag ilə keçid
interface OrderRepositoryInterface
{
    public function findById(int $id): ?Order;
    public function save(Order $order): void;
}

// Legacy implementasiya — köhnə, spaghetti kod
class LegacyOrderRepository implements OrderRepositoryInterface
{
    public function findById(int $id): ?Order
    {
        // Köhnə mysql_query, global connection, çirkin kod
        $row = \DB::select("SELECT * FROM tbl_orders WHERE id = ?", [$id]);
        return $row ? $this->mapLegacyRow($row[0]) : null;
    }

    public function save(Order $order): void
    {
        \DB::statement("UPDATE tbl_orders SET ... WHERE id = {$order->id}");
    }

    private function mapLegacyRow(object $row): Order
    {
        return new Order(
            id:         $row->ORD_ID,
            customerId: $row->CUST_NO,
            total:      (int) ($row->AMT_TOT_NET * 100),
            status:     $this->mapStatus($row->ORD_STS_CD),
        );
    }
}

// Yeni implementasiya — Eloquent, clean kod
class EloquentOrderRepository implements OrderRepositoryInterface
{
    public function findById(int $id): ?Order
    {
        $model = \App\Models\Order::find($id);
        return $model ? Order::fromModel($model) : null;
    }

    public function save(Order $order): void
    {
        \App\Models\Order::updateOrCreate(
            ['id' => $order->id],
            $order->toArray()
        );
    }
}

// Feature flag ilə keçid — AppServiceProvider-də bind
class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(OrderRepositoryInterface::class, function () {
            // Config-dən feature flag oxu
            return config('features.use_new_order_repo')
                ? app(EloquentOrderRepository::class)
                : app(LegacyOrderRepository::class);
        });
    }
}
```

```php
<?php

// Proxy / HTTP-level routing — Laravel middleware
class StranglerProxy
{
    // Köçürülmüş modullar → yeni sistem
    private array $newSystemRoutes = [
        '/api/users',
        '/api/auth',
        '/api/catalog',
    ];

    public function handle(Request $request, \Closure $next): Response
    {
        $path = $request->getPathInfo();

        foreach ($this->newSystemRoutes as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return $this->forwardToNewSystem($request);
            }
        }

        // Qalanlar legacy-ə
        return $next($request);
    }

    private function forwardToNewSystem(Request $request): Response
    {
        $response = \Http::withHeaders($request->headers->all())
            ->send($request->method(), config('strangler.new_system_url') . $request->getRequestUri(), [
                'body' => $request->getContent(),
            ]);

        return response($response->body(), $response->status(), $response->headers());
    }
}
```

```php
<?php

// Shadow Mode — divergence detect etmək üçün
class ShadowOrderService
{
    public function place(PlaceOrderData $data): Order
    {
        // Legacy-dən real cavab al
        $legacyResult = $this->legacyService->place($data);

        // Arxa planda yeni sistemi paralel çağır (cavab istifadəçiyə getmir)
        dispatch(function () use ($data, $legacyResult) {
            try {
                $newResult = $this->newService->place($data);

                // Nəticələri müqayisə et
                if ($this->differs($legacyResult, $newResult)) {
                    \Log::warning('Shadow mode divergence', [
                        'legacy' => $legacyResult->toArray(),
                        'new'    => $newResult->toArray(),
                        'data'   => $data->toArray(),
                    ]);
                }
            } catch (\Exception $e) {
                \Log::error('Shadow mode error', ['error' => $e->getMessage()]);
            }
        })->afterResponse();

        return $legacyResult;  // İstifadəçiyə legacy nəticəsi qaytarılır
    }
}
```

## Praktik Tapşırıqlar

1. Mövcud monolitdən bir modul seçin (məs: `UserController`); interface yaradın; legacy implementasiyası ilə bağlayın; feature flag əlavə edin; yeni implementasiyanı yazın; flag-ı açıq/qapalı edin — hər ikisi eyni cevabı verməlidir
2. Shadow mode yazın: `OrderService.place()` legacy-dən cavab alır, paralel yeni servisi çağırır; divergence log-lanır; test: 100 sifarişdə divergence sıfır olmalıdır
3. Nginx-də routing qurun: `/api/users` yeni sistemə, `/api/orders` legacy-ə; feature flag olmadan routing-i dəyişin; deploy olmadan routing switch edin
4. Miqrasiya checklist yaradın: hansı modullar köçürüldü, hansı qaldı; hər modula "data sync" planı; rollback addımları; legacy söndürülmə kriteriləri

## Əlaqəli Mövzular

- [Anti-Corruption Layer](08-anti-corruption-layer.md) — köçürmə zamanı legacy model-dən qorunmaq
- [Outbox Pattern](04-outbox-pattern.md) — dual-write dönəmindəki event reliable sync
- [Hexagonal Architecture](../architecture/05-hexagonal-architecture.md) — yeni sistemin port/adapter strukturu
- [Modular Monolith](../architecture/08-modular-monolith.md) — monolitin ilk strangler addımı
- [DDD Bounded Context](../ddd/06-ddd-bounded-context.md) — miqrasiya modullarını bounded context-lərə görə planla
