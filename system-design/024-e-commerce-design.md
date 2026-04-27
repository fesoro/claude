# E-Commerce System Design (Middle)

## İcmal

E-commerce system online alış-veriş platformasıdır. Product catalog, shopping cart,
inventory management, order processing, payment və shipping əhatə edir. Amazon,
Shopify, eBay kimi platformalar nümunədir. High availability, data consistency
və scalability kritik tələblərdir.

Sadə dillə: online mağaza - məhsulu seçirsən, səbətə atırsan, ödəyirsən, çatdırılır.

```
Browse → Add to Cart → Checkout → Payment → Order → Shipping → Delivery
```


## Niyə Vacibdir

E-commerce sisteminin hər hissəsi — inventar, ödəniş, sifariş, çatdırılma — ayrı domain-dir. Yüksək trafik (Black Friday) zamanı inventory concurrency kritik problemdir; idempotent ödəniş pulun itməsinin qarşısını alır. Shopify, Amazon arxitekturası bu prinsiplər üzərindədir.

## Əsas Anlayışlar

### Core Domains

```
┌──────────────────────────────────────────────────────┐
│                  E-Commerce Platform                  │
│                                                       │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐          │
│  │ Product  │  │   Cart   │  │  Order   │          │
│  │ Catalog  │  │ Service  │  │ Service  │          │
│  └──────────┘  └──────────┘  └──────────┘          │
│                                                       │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐          │
│  │Inventory │  │ Payment  │  │ Shipping │          │
│  │ Service  │  │ Service  │  │ Service  │          │
│  └──────────┘  └──────────┘  └──────────┘          │
│                                                       │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐          │
│  │  Search  │  │  User    │  │ Review   │          │
│  │ Service  │  │ Service  │  │ Service  │          │
│  └──────────┘  └──────────┘  └──────────┘          │
└──────────────────────────────────────────────────────┘
```

### Inventory Management

```
Challenge: 100 people trying to buy the last item

Race condition:
  Thread 1: Read stock=1 → Check: 1>0 ✓ → Update stock=0 ✓
  Thread 2: Read stock=1 → Check: 1>0 ✓ → Update stock=0 ✗ OVERSOLD!

Solutions:
  1. Pessimistic Lock: SELECT ... FOR UPDATE
  2. Optimistic Lock: UPDATE WHERE stock >= quantity
  3. Redis atomic decrement: DECRBY stock 1 (check if >= 0)
```

### Cart Design

```
Approach 1: Session/Cookie based (guest users)
  - Stored in browser, no backend needed
  - Lost when session expires

Approach 2: Database based (logged-in users)
  - Persistent across devices
  - Cart abandonment tracking possible

Approach 3: Redis (hybrid)
  - Fast read/write
  - TTL for auto-expiry
  - Sync to DB periodically

Approach 4: Hybrid
  - Guest: Cookie/localStorage
  - Logged-in: Redis + DB backup
  - Merge on login
```

### Order State Machine

```
┌─────────┐   ┌──────────┐   ┌───────────┐   ┌──────────┐
│ Pending │──▶│ Confirmed│──▶│ Processing│──▶│ Shipped  │
└────┬────┘   └──────────┘   └───────────┘   └────┬─────┘
     │                                              │
     │                                         ┌────┴─────┐
     ▼                                         │Delivered │
┌─────────┐                                   └────┬─────┘
│Cancelled│                                         │
└─────────┘                                    ┌────┴─────┐
                                               │ Returned │
                                               └──────────┘
```

## Arxitektura

### E-Commerce Architecture

```
┌───────────┐  ┌───────────┐
│   Web     │  │  Mobile   │
│   App     │  │   App     │
└─────┬─────┘  └─────┬─────┘
      │               │
      └───────┬───────┘
              │
       ┌──────┴──────┐
       │ API Gateway │
       │ + CDN       │
       └──────┬──────┘
              │
  ┌───────────┼───────────┬───────────┐
  │           │           │           │
┌─┴──┐   ┌───┴──┐   ┌───┴──┐   ┌───┴──┐
│Prod│   │ Cart │   │Order │   │ User │
│Svc │   │ Svc  │   │ Svc  │   │ Svc  │
└─┬──┘   └───┬──┘   └───┬──┘   └───┬──┘
  │           │          │          │
┌─┴──┐   ┌───┴──┐   ┌───┴──┐  ┌───┴──┐
│ ES │   │Redis │   │MySQL │  │MySQL │
│Prod│   │Cart  │   │Orders│  │Users │
│DB  │   │Cache │   │DB    │  │DB    │
└────┘   └──────┘   └──────┘  └──────┘
              │
       ┌──────┴──────┐
       │ Message Bus │
       │  (Kafka)    │
       └──────┬──────┘
              │
  ┌───────────┼───────────┐
  │           │           │
┌─┴────┐ ┌───┴───┐ ┌────┴───┐
│Invent│ │Payment│ │Shipping│
│ory   │ │  Svc  │ │  Svc   │
└──────┘ └───────┘ └────────┘
```

## Nümunələr

### Product Catalog

```php
// app/Models/Product.php
class Product extends Model
{
    use Searchable, SoftDeletes;

    protected $casts = [
        'price' => 'decimal:2',
        'attributes' => 'json',
        'is_active' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('stock', '>', 0);
    }

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'category' => $this->category->name,
            'price' => $this->price,
            'rating' => $this->reviews()->avg('rating'),
            'brand' => $this->brand,
        ];
    }
}
```

### Shopping Cart

```php
class CartService
{
    private const CART_TTL = 604800; // 7 days

    public function __construct(private \Redis $redis) {}

    public function addItem(int $userId, int $productId, int $quantity = 1, ?array $variant = null): array
    {
        $product = Product::active()->findOrFail($productId);
        $cartKey = "cart:{$userId}";

        $item = [
            'product_id' => $product->id,
            'name' => $product->name,
            'price' => $product->price,
            'quantity' => $quantity,
            'variant' => $variant,
            'image' => $product->images->first()?->url,
        ];

        // Check stock
        if ($product->stock < $quantity) {
            throw new InsufficientStockException("Only {$product->stock} items available");
        }

        $this->redis->hset($cartKey, $productId, json_encode($item));
        $this->redis->expire($cartKey, self::CART_TTL);

        return $this->getCart($userId);
    }

    public function updateQuantity(int $userId, int $productId, int $quantity): array
    {
        $cartKey = "cart:{$userId}";
        $item = json_decode($this->redis->hget($cartKey, $productId), true);

        if (!$item) throw new CartItemNotFoundException();

        if ($quantity <= 0) {
            $this->redis->hdel($cartKey, $productId);
        } else {
            $item['quantity'] = $quantity;
            $this->redis->hset($cartKey, $productId, json_encode($item));
        }

        return $this->getCart($userId);
    }

    public function getCart(int $userId): array
    {
        $cartKey = "cart:{$userId}";
        $items = $this->redis->hgetall($cartKey);

        $cartItems = [];
        $total = 0;

        foreach ($items as $productId => $itemJson) {
            $item = json_decode($itemJson, true);
            $item['subtotal'] = $item['price'] * $item['quantity'];
            $total += $item['subtotal'];
            $cartItems[] = $item;
        }

        return [
            'items' => $cartItems,
            'item_count' => count($cartItems),
            'total' => round($total, 2),
        ];
    }

    public function clear(int $userId): void
    {
        $this->redis->del("cart:{$userId}");
    }

    // Merge guest cart into user cart on login
    public function merge(string $sessionCartKey, int $userId): void
    {
        $guestItems = $this->redis->hgetall($sessionCartKey);
        $userCartKey = "cart:{$userId}";

        foreach ($guestItems as $productId => $itemJson) {
            if (!$this->redis->hexists($userCartKey, $productId)) {
                $this->redis->hset($userCartKey, $productId, $itemJson);
            }
        }

        $this->redis->del($sessionCartKey);
    }
}
```

### Checkout & Order Service

```php
class CheckoutService
{
    public function __construct(
        private CartService $cart,
        private InventoryService $inventory,
        private PaymentService $payment,
        private OrderRepository $orders
    ) {}

    public function checkout(int $userId, CheckoutRequest $request): Order
    {
        $cart = $this->cart->getCart($userId);

        if (empty($cart['items'])) {
            throw new EmptyCartException();
        }

        return DB::transaction(function () use ($userId, $cart, $request) {
            // Step 1: Reserve inventory (with lock)
            $this->reserveInventory($cart['items']);

            // Step 2: Create order
            $order = $this->orders->create([
                'user_id' => $userId,
                'status' => 'pending',
                'subtotal' => $cart['total'],
                'shipping_cost' => $this->calculateShipping($request->shipping_address),
                'tax' => $this->calculateTax($cart['total'], $request->shipping_address),
                'total' => 0, // calculated below
                'shipping_address' => $request->shipping_address,
                'billing_address' => $request->billing_address,
            ]);

            $order->total = $order->subtotal + $order->shipping_cost + $order->tax;
            $order->save();

            // Step 3: Create order items
            foreach ($cart['items'] as $item) {
                $order->items()->create([
                    'product_id' => $item['product_id'],
                    'product_name' => $item['name'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['price'],
                    'subtotal' => $item['subtotal'],
                    'variant' => $item['variant'],
                ]);
            }

            // Step 4: Process payment
            $payment = $this->payment->processPayment(new ProcessPaymentRequest(
                orderId: $order->id,
                amount: $order->total,
                paymentMethodId: $request->payment_method_id,
                idempotencyKey: "order-{$order->id}",
            ));

            $order->update([
                'payment_id' => $payment->id,
                'status' => 'confirmed',
            ]);

            // Step 5: Clear cart
            $this->cart->clear($order->user_id);

            // Step 6: Emit event
            event(new OrderPlaced($order));

            return $order;
        });
    }

    private function reserveInventory(array $items): void
    {
        foreach ($items as $item) {
            $updated = DB::table('products')
                ->where('id', $item['product_id'])
                ->where('stock', '>=', $item['quantity'])
                ->decrement('stock', $item['quantity']);

            if (!$updated) {
                throw new InsufficientStockException(
                    "Product '{$item['name']}' is out of stock"
                );
            }
        }
    }

    private function calculateShipping(array $address): float
    {
        // Simplified shipping calculation
        return match ($address['country']) {
            'AZ' => 5.00,
            'TR', 'GE', 'RU' => 15.00,
            default => 25.00,
        };
    }

    private function calculateTax(float $subtotal, array $address): float
    {
        $taxRate = match ($address['country']) {
            'AZ' => 0.18,
            'DE' => 0.19,
            'US' => 0.08,
            default => 0.00,
        };

        return round($subtotal * $taxRate, 2);
    }
}
```

### Inventory Service with Locking

```php
class InventoryService
{
    // Optimistic locking approach
    public function decrementStock(int $productId, int $quantity): bool
    {
        $affected = DB::table('products')
            ->where('id', $productId)
            ->where('stock', '>=', $quantity)
            ->decrement('stock', $quantity);

        return $affected > 0;
    }

    // Redis-based for high-concurrency scenarios
    public function reserveWithRedis(int $productId, int $quantity): bool
    {
        $key = "inventory:{$productId}";

        // Atomic check and decrement
        $script = <<<'LUA'
        local current = tonumber(redis.call('GET', KEYS[1]) or 0)
        if current >= tonumber(ARGV[1]) then
            redis.call('DECRBY', KEYS[1], ARGV[1])
            return 1
        end
        return 0
        LUA;

        return (bool) Redis::eval($script, 1, $key, $quantity);
    }

    // Release reserved inventory (on order cancel)
    public function releaseStock(int $productId, int $quantity): void
    {
        DB::table('products')
            ->where('id', $productId)
            ->increment('stock', $quantity);
    }

    // Sync Redis inventory with DB (periodic job)
    public function syncInventory(): void
    {
        Product::where('is_active', true)->chunk(100, function ($products) {
            foreach ($products as $product) {
                Redis::set("inventory:{$product->id}", $product->stock);
            }
        });
    }
}
```

### Order Management

```php
class OrderService
{
    public function cancelOrder(int $orderId, int $userId): Order
    {
        $order = Order::where('id', $orderId)
            ->where('user_id', $userId)
            ->firstOrFail();

        if (!in_array($order->status, ['pending', 'confirmed'])) {
            throw new OrderCannotBeCancelledException();
        }

        DB::transaction(function () use ($order) {
            // Release inventory
            foreach ($order->items as $item) {
                DB::table('products')
                    ->where('id', $item->product_id)
                    ->increment('stock', $item->quantity);
            }

            // Refund payment if charged
            if ($order->payment && $order->payment->status === 'completed') {
                app(PaymentService::class)->refund($order->payment->id);
            }

            $order->update(['status' => 'cancelled']);
        });

        event(new OrderCancelled($order));

        return $order->fresh();
    }

    public function getOrderHistory(int $userId, int $perPage = 20): LengthAwarePaginator
    {
        return Order::where('user_id', $userId)
            ->with(['items.product', 'payment'])
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }
}
```

## Real-World Nümunələr

1. **Amazon** - World's largest e-commerce, microservices pioneer
2. **Shopify** - E-commerce platform, powers 4M+ stores
3. **Alibaba** - Handles Singles Day (11.11) - 583K orders/second peak
4. **eBay** - Auction + fixed-price, 1.7B listings
5. **Etsy** - Handmade marketplace, 90M+ active buyers

## Praktik Tapşırıqlar

**S1: Flash sale zamanı inventory race condition necə həll olunur?**
C: Redis atomic operations (DECRBY + check >= 0), database optimistic lock
(UPDATE WHERE stock >= quantity), distributed lock. Pre-reserve with TTL
(10 dəqiqə ərzində ödənilməsə release). Queue-based ordering for fairness.

**S2: Cart persistence strategiyası necə olmalıdır?**
C: Guest: localStorage/cookie (client-side). Logged-in: Redis (fast, TTL) +
periodic DB backup. Login zamanı guest cart-ı user cart-a merge edin.
Cart abandonment email üçün DB-dəki cart data istifadə edin.

**S3: Distributed transaction-lar e-commerce-da necə idarə olunur?**
C: Saga pattern ilə. Order → Payment → Inventory → Shipping hər biri
local transaction. Uğursuz addımda compensating transactions: refund,
release stock. Eventual consistency qəbul edilir.

**S4: Product pricing consistency necə təmin olunur?**
C: Add to cart zamanı qiyməti lock edin (cart item-da saxlayın). Checkout
zamanı cari qiymətlə müqayisə edin. Fərq varsa user-ə xəbər verin.
Coupon/discount validation checkout zamanı server-side olmalıdır.

**S5: Search və recommendation necə implement olunur?**
C: Search: Elasticsearch full-text + faceted filtering. Recommendations:
collaborative filtering (users who bought X also bought Y), content-based
(similar products by attributes). A/B test ilə optimize edin.

**S6: Order status tracking necə implement olunur?**
C: State machine pattern ilə valid transitions enforce edin. Hər status
dəyişikliyi event emit edir. WebSocket/push notification ilə real-time
update. Third-party shipping API-dan webhook ilə status sync.

## Praktik Baxış

1. **Inventory Lock** - Atomic operations ilə overselling-in qarşısını alın
2. **Idempotent Checkout** - Duplicate order prevention
3. **Cart TTL** - Abandoned cart-ları expire edin
4. **Price Lock** - Cart-a əlavə edəndə qiyməti saxlayın
5. **Async Processing** - Email, analytics, inventory sync async edin
6. **Search Optimization** - Elasticsearch ilə sürətli product search
7. **CDN for Images** - Product images CDN-dən serve edin
8. **Rate Limiting** - Checkout endpoint-ə rate limit
9. **Audit Trail** - Hər order dəyişikliyini log edin
10. **Graceful Degradation** - Payment down olsa "retry later" göstərin


## Əlaqəli Mövzular

- [Payment System](20-payment-system-design.md) — checkout ödəniş axını
- [Booking System](39-booking-system.md) — inventory lock problemi
- [Idempotency](28-idempotency.md) — sifariş təkrarının qarşısı
- [Distributed Transactions](45-distributed-transactions-saga.md) — order saga
- [Caching](03-caching-strategies.md) — məhsul kataloqu cache
