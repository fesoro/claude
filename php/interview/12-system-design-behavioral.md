# System Design və Behavioral Suallar

## System Design Sualları

### 1. E-commerce sistemi necə dizayn edərdiniz?

**Əsas komponentlər:**
- **User Service** — authentication, profile
- **Product Catalog** — products, categories, search (Elasticsearch)
- **Cart Service** — session/DB-based cart
- **Order Service** — order lifecycle, status tracking
- **Payment Service** — Stripe/PayPal integration
- **Inventory Service** — stock management, reservations
- **Notification Service** — email, SMS, push

**Texniki qərarlar:**
- **Database:** MySQL/PostgreSQL (orders, users), Redis (cart, sessions, cache)
- **Search:** Elasticsearch / Meilisearch (product search, filters)
- **Queue:** Redis + Laravel Horizon (email, payment processing, inventory)
- **Cache:** Redis — product pages, category lists, user sessions
- **File Storage:** S3 — product images
- **Payment:** Stripe SDK + webhook-lar idempotent key ilə

**Race condition həlləri:**
```php
// Stok azaltma — pessimistic lock
DB::transaction(function () use ($productId, $quantity) {
    $product = Product::where('id', $productId)->lockForUpdate()->first();
    if ($product->stock < $quantity) {
        throw new InsufficientStockException();
    }
    $product->decrement('stock', $quantity);
});

// Və ya atomic operation
Product::where('id', $productId)
    ->where('stock', '>=', $quantity)
    ->decrement('stock', $quantity); // affected rows = 0 olarsa stock yoxdur
```

---

### 2. URL Shortener necə dizayn edərdiniz?

```php
class UrlShortener {
    public function shorten(string $url): string {
        $hash = $this->generateHash();

        ShortUrl::create([
            'hash' => $hash,
            'original_url' => $url,
            'expires_at' => now()->addYear(),
        ]);

        return config('app.url') . '/' . $hash;
    }

    private function generateHash(): string {
        // Base62 encoding (a-z, A-Z, 0-9)
        // 6 char = 62^6 = ~56 milyard unikal URL
        do {
            $hash = Str::random(6);
        } while (ShortUrl::where('hash', $hash)->exists());

        return $hash;
    }
}

class RedirectController {
    public function __invoke(string $hash): RedirectResponse {
        $url = Cache::remember("short_url:$hash", 86400, function () use ($hash) {
            return ShortUrl::where('hash', $hash)->firstOrFail()->original_url;
        });

        // Analytics async
        UrlVisited::dispatch($hash, request()->ip(), request()->userAgent());

        return redirect($url, 301);
    }
}
```

---

### 3. Notification sistemi necə dizayn edərdiniz?

```php
// Multi-channel notification system
interface NotificationChannel {
    public function send(Notifiable $user, NotificationPayload $payload): void;
}

class NotificationDispatcher {
    public function dispatch(
        Notifiable $user,
        NotificationPayload $payload,
        array $channels = [],
    ): void {
        $channels = $channels ?: $user->preferredChannels();

        foreach ($channels as $channel) {
            SendNotification::dispatch($user, $payload, $channel)
                ->onQueue('notifications');
        }
    }
}

// User preferences
// notifications_preferences table:
// user_id | channel | type         | enabled
// 1       | email   | order_update | true
// 1       | sms     | order_update | false
// 1       | push    | promotion    | true

// Deduplication — eyni notification-u təkrar göndərmə
class SendNotification implements ShouldQueue, ShouldBeUnique {
    public function uniqueId(): string {
        return "{$this->user->id}:{$this->payload->type}:{$this->payload->referenceId}";
    }
}
```

---

## Behavioral / Situational Suallar

### 4. Bir production bug-ı necə debug edərdiniz?

**Addım-addım:**
1. **Scope təyin et** — neçə user təsirlənir? Kritik mi?
2. **Log-ları yoxla** — Laravel log, Sentry/Bugsnag, server logs
3. **Reproduce et** — staging-də təkrarla, mümkünsə
4. **Root cause tap** — git blame, recent deployments yoxla
5. **Hotfix** — minimal dəyişikliklə düzəlt, test et, deploy et
6. **Post-mortem** — niyə baş verdi, necə qarşısını alaq

```php
// Yararlı debug alətləri
Log::channel('stderr')->info('Debug point reached', [
    'user_id' => $user->id,
    'data' => $request->all(),
]);

// Telescope — request lifecycle
// Debugbar — query analysis
// Ray — real-time debugging
ray($variable)->color('red')->label('Critical');
```

---

### 5. Legacy kodu necə refactor edərdiniz?

1. **Test yaz** — mövcud davranışı test-lərlə qoruyuq
2. **Kiçik addımlar** — hər dəyişiklik ayrı PR
3. **Strangler Fig Pattern** — yenisini köhnənin yanında yaz, tədricən keç
4. **Feature flag** — yeni kodu gizli saxla, hazır olanda aç

```php
// Strangler Fig — tədricən köhnəni əvəz et
class LegacyOrderService {
    public function process(array $data): array { /* köhnə, qarışıq kod */ }
}

class NewOrderService {
    public function process(CreateOrderDTO $dto): Order { /* təmiz, test olunmuş */ }
}

// Feature flag ilə keçid
class OrderController {
    public function store(Request $request): JsonResponse {
        if (Feature::active('new-order-service')) {
            return $this->newService->process(CreateOrderDTO::fromRequest($request));
        }
        return $this->legacyService->process($request->all());
    }
}
```

---

### 6. Komandada code review prosesini necə qurarsınız?

**PR standartları:**
- Kiçik PR-lar (< 400 sətir dəyişiklik)
- Aydın description və test plan
- Ən azı 1 approval tələb olunur
- CI/CD keçməlidir (tests, linting, static analysis)

**Review zamanı nəyə baxmaq:**
- Business logic düzgünlüyü
- Security (SQL injection, XSS, mass assignment)
- Performance (N+1, unnecessary queries)
- Test coverage
- Edge cases

**Alətlər:**
- PHPStan/Larastan — static analysis
- Laravel Pint — code style
- PHPMD — code complexity
- Infection — mutation testing

---

### 7. Böyük bir migration-u necə planlaşdırarsınız?

```
1. Analiz et — hansı cədvəllər, neçə sətir, foreign key-lər
2. Staging-də test et — real data ilə
3. Backup al
4. Maintenance mode (lazım olarsa)
5. Migration icra et
6. Smoke test — əsas funksiyaları yoxla
7. Rollback planı hazır saxla
```

```php
// Böyük migration üçün batch approach
class MigrateUserPhoneNumbers extends Command {
    public function handle(): void {
        $this->info('Starting migration...');

        User::query()
            ->whereNull('phone_normalized')
            ->chunkById(1000, function ($users) {
                foreach ($users as $user) {
                    $user->update([
                        'phone_normalized' => $this->normalize($user->phone),
                    ]);
                }
                $this->info("Processed {$users->count()} users");
            });

        $this->info('Migration completed!');
    }
}
```

---

### 8. Niyə Senior PHP/Laravel Developer olmaq istəyirsiniz? (Özünü təqdim)

**Cavab strukturu:**
1. **Background** — neçə il təcrübə, hansı layihələr
2. **Technical depth** — PHP/Laravel-də güclü tərəflər
3. **Leadership** — code review, mentoring, architecture decisions
4. **Problem solving** — çətin bug/scalability problemi həll etmə nümunəsi
5. **Continuous learning** — yeni PHP/Laravel versiyaları, best practices

**Güclü cəhətlər vurğula:**
- Clean architecture və SOLID prinsiplərinə riayət
- Performance optimization təcrübəsi
- CI/CD və DevOps bilgiləri
- Testing mədəniyyəti
- Komanda ilə işləmə və mentoring
