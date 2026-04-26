# Real Use Cases (Senior)

## 1. Shopping Cart implementasiyası (Session + DB hybrid)

**Problem:** İstifadəçi login olmadan cart-a məhsul əlavə edir, sonra login olur — cart itməməlidir.

```php
// CartService — session və DB hybrid
class CartService {
    public function __construct(
        private CartRepositoryInterface $repository,
        private SessionManager $session,
    ) {}

    public function add(int $productId, int $quantity = 1, array $options = []): Cart {
        $cart = $this->getOrCreateCart();

        // Stok yoxla
        $product = Product::findOrFail($productId);
        $variant = $options['variant_id'] ?? null;
        $availableStock = $this->getAvailableStock($product, $variant);

        $existingItem = $cart->items->where('product_id', $productId)
            ->where('variant_id', $variant)
            ->first();

        $totalQuantity = ($existingItem?->quantity ?? 0) + $quantity;

        if ($totalQuantity > $availableStock) {
            throw new InsufficientStockException(
                product: $product,
                requested: $totalQuantity,
                available: $availableStock,
            );
        }

        if ($existingItem) {
            $existingItem->update(['quantity' => $totalQuantity]);
        } else {
            $cart->items()->create([
                'product_id' => $productId,
                'variant_id' => $variant,
                'quantity' => $quantity,
                'unit_price' => $product->getCurrentPrice($variant),
            ]);
        }

        $this->recalculate($cart);

        return $cart->fresh('items.product');
    }

    public function remove(int $itemId): Cart {
        $cart = $this->getOrCreateCart();
        $cart->items()->where('id', $itemId)->delete();
        $this->recalculate($cart);
        return $cart->fresh('items.product');
    }

    public function updateQuantity(int $itemId, int $quantity): Cart {
        $cart = $this->getOrCreateCart();
        $item = $cart->items()->findOrFail($itemId);

        if ($quantity <= 0) {
            $item->delete();
        } else {
            $availableStock = $this->getAvailableStock($item->product, $item->variant_id);
            if ($quantity > $availableStock) {
                throw new InsufficientStockException(
                    product: $item->product,
                    requested: $quantity,
                    available: $availableStock,
                );
            }
            $item->update(['quantity' => $quantity]);
        }

        $this->recalculate($cart);
        return $cart->fresh('items.product');
    }

    public function applyCoupon(string $code): Cart {
        $cart = $this->getOrCreateCart();
        $coupon = Coupon::where('code', strtoupper($code))->firstOrFail();

        // Validasiyalar
        if ($coupon->isExpired()) {
            throw new CouponExpiredException($coupon);
        }
        if ($coupon->isUsageLimitReached()) {
            throw new CouponUsageLimitException($coupon);
        }
        if ($coupon->minimum_amount && $cart->subtotal < $coupon->minimum_amount) {
            throw new CouponMinimumAmountException($coupon, $cart->subtotal);
        }

        $cart->update(['coupon_id' => $coupon->id]);
        $this->recalculate($cart);

        return $cart->fresh('items.product');
    }

    // Guest → authenticated user: cart merge
    public function mergeGuestCart(User $user): void {
        $sessionId = $this->session->getId();
        $guestCart = Cart::where('session_id', $sessionId)->whereNull('user_id')->first();
        $userCart = Cart::where('user_id', $user->id)->first();

        if (!$guestCart) return;

        if (!$userCart) {
            // Guest cart-ı user-ə bağla
            $guestCart->update(['user_id' => $user->id, 'session_id' => null]);
            return;
        }

        // Merge — guest cart items-ını user cart-a əlavə et
        foreach ($guestCart->items as $guestItem) {
            $existingItem = $userCart->items
                ->where('product_id', $guestItem->product_id)
                ->where('variant_id', $guestItem->variant_id)
                ->first();

            if ($existingItem) {
                $existingItem->update([
                    'quantity' => $existingItem->quantity + $guestItem->quantity,
                ]);
            } else {
                $guestItem->update(['cart_id' => $userCart->id]);
            }
        }

        $guestCart->delete();
        $this->recalculate($userCart);
    }

    private function getOrCreateCart(): Cart {
        $user = auth()->user();

        if ($user) {
            return Cart::firstOrCreate(['user_id' => $user->id]);
        }

        $sessionId = $this->session->getId();
        return Cart::firstOrCreate(['session_id' => $sessionId]);
    }

    private function recalculate(Cart $cart): void {
        $cart->load('items.product', 'coupon');

        $subtotal = $cart->items->sum(fn ($item) => $item->unit_price * $item->quantity);

        $discount = 0;
        if ($cart->coupon) {
            $discount = $cart->coupon->type === 'percentage'
                ? $subtotal * ($cart->coupon->value / 100)
                : $cart->coupon->value;
            $discount = min($discount, $subtotal); // Discount subtotal-dan böyük ola bilməz
        }

        $taxable = $subtotal - $discount;
        $tax = $taxable * 0.18; // 18% ƏDV

        $cart->update([
            'subtotal' => $subtotal,
            'discount' => $discount,
            'tax' => $tax,
            'total' => $taxable + $tax,
        ]);
    }

    private function getAvailableStock(Product $product, ?int $variantId): int {
        if ($variantId) {
            return $product->variants()->where('id', $variantId)->value('stock') ?? 0;
        }
        return $product->stock;
    }
}

// Login event listener — cart merge
class MergeCartOnLogin {
    public function handle(Login $event): void {
        app(CartService::class)->mergeGuestCart($event->user);
    }
}
```

---

## 2. Order Processing Pipeline — bütün lifecycle

```php
// Order Status Flow:
// pending → confirmed → processing → shipped → delivered
//    ↓         ↓           ↓
// cancelled  cancelled   returned → refunded

enum OrderStatus: string {
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Processing = 'processing';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
    case Returned = 'returned';
    case Refunded = 'refunded';

    public function canTransitionTo(self $next): bool {
        return match($this) {
            self::Pending => in_array($next, [self::Confirmed, self::Cancelled]),
            self::Confirmed => in_array($next, [self::Processing, self::Cancelled]),
            self::Processing => in_array($next, [self::Shipped, self::Cancelled]),
            self::Shipped => in_array($next, [self::Delivered, self::Returned]),
            self::Delivered => in_array($next, [self::Returned]),
            self::Returned => in_array($next, [self::Refunded]),
            default => false,
        };
    }
}

class PlaceOrderAction {
    public function __construct(
        private InventoryService $inventory,
        private PaymentService $payment,
        private OrderNumberGenerator $orderNumbers,
    ) {}

    public function execute(Cart $cart, PlaceOrderDTO $dto): Order {
        // 1. Cart boş olmamalı
        if ($cart->items->isEmpty()) {
            throw new EmptyCartException();
        }

        // 2. Stok son dəfə yoxla + qiymət dəyişikliyini yoxla
        foreach ($cart->items as $item) {
            $currentPrice = $item->product->getCurrentPrice($item->variant_id);
            if (abs($item->unit_price - $currentPrice) > 0.01) {
                throw new PriceChangedException($item->product, $item->unit_price, $currentPrice);
            }
        }

        return DB::transaction(function () use ($cart, $dto) {
            // 3. Stoku rezerv et (pessimistic lock)
            $this->inventory->reserveForOrder($cart->items);

            // 4. Order yarat
            $order = Order::create([
                'order_number' => $this->orderNumbers->generate(),
                'user_id' => $cart->user_id,
                'status' => OrderStatus::Pending,
                'subtotal' => $cart->subtotal,
                'discount' => $cart->discount,
                'tax' => $cart->tax,
                'shipping_cost' => $dto->shippingCost,
                'total' => $cart->total + $dto->shippingCost,
                'shipping_address' => $dto->shippingAddress->toArray(),
                'billing_address' => $dto->billingAddress->toArray(),
                'coupon_id' => $cart->coupon_id,
                'notes' => $dto->notes,
            ]);

            // 5. Order items
            foreach ($cart->items as $cartItem) {
                $order->items()->create([
                    'product_id' => $cartItem->product_id,
                    'variant_id' => $cartItem->variant_id,
                    'product_name' => $cartItem->product->name, // snapshot
                    'unit_price' => $cartItem->unit_price,
                    'quantity' => $cartItem->quantity,
                    'total' => $cartItem->unit_price * $cartItem->quantity,
                ]);
            }

            // 6. Payment intent yarat
            $paymentIntent = $this->payment->createIntent($order, $dto->paymentMethod);
            $order->update(['payment_intent_id' => $paymentIntent->id]);

            // 7. Cart təmizlə
            $cart->items()->delete();
            $cart->update(['coupon_id' => null, 'subtotal' => 0, 'total' => 0]);

            // 8. Coupon istifadə sayını artır
            if ($order->coupon_id) {
                Coupon::where('id', $order->coupon_id)->increment('used_count');
            }

            // 9. Event dispatch
            OrderPlaced::dispatch($order);

            return $order;
        });
    }
}

// Inventory reservation — race condition-suz
class InventoryService {
    public function reserveForOrder(Collection $items): void {
        foreach ($items as $item) {
            $affected = Product::where('id', $item->product_id)
                ->where('stock', '>=', $item->quantity)
                ->decrement('stock', $item->quantity);

            if ($affected === 0) {
                throw new InsufficientStockException(
                    product: $item->product,
                    requested: $item->quantity,
                    available: $item->product->fresh()->stock,
                );
            }
        }
    }

    public function releaseReservation(Order $order): void {
        foreach ($order->items as $item) {
            Product::where('id', $item->product_id)
                ->increment('stock', $item->quantity);
        }
    }
}

// Order number generator — unique, readable
class OrderNumberGenerator {
    public function generate(): string {
        // Format: ORD-20260411-XXXXX
        $date = now()->format('Ymd');
        $sequence = Cache::lock('order-number', 5)->block(3, function () use ($date) {
            $key = "order_seq:{$date}";
            $seq = Cache::increment($key);
            if ($seq === 1) {
                Cache::put($key, 1, now()->endOfDay());
            }
            return $seq;
        });

        return sprintf('ORD-%s-%05d', $date, $sequence);
    }
}
```

---

## 3. Stripe Payment Integration (webhooks ilə tam flow)

```php
class PaymentService {
    public function __construct(private StripeClient $stripe) {}

    public function createIntent(Order $order, string $paymentMethodId): PaymentIntent {
        return $this->stripe->paymentIntents->create([
            'amount' => (int) ($order->total * 100), // cents
            'currency' => 'azn',
            'payment_method' => $paymentMethodId,
            'confirmation_method' => 'manual',
            'confirm' => true,
            'metadata' => [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ],
            'idempotency_key' => 'order_' . $order->id,
        ]);
    }

    public function refund(Order $order, ?float $amount = null): Refund {
        $payment = $order->payment;
        return $this->stripe->refunds->create([
            'payment_intent' => $payment->stripe_payment_intent_id,
            'amount' => $amount ? (int) ($amount * 100) : null, // null = full refund
            'metadata' => ['order_id' => $order->id],
        ]);
    }
}

// Stripe Webhook Handler
class StripeWebhookController extends Controller {
    public function handle(Request $request): Response {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');

        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $signature,
                config('services.stripe.webhook_secret')
            );
        } catch (\Exception $e) {
            Log::error('Stripe webhook signature failed', ['error' => $e->getMessage()]);
            return response('Invalid signature', 400);
        }

        // Idempotency — duplicate webhook-ları ignore et
        if (WebhookEvent::where('stripe_event_id', $event->id)->exists()) {
            return response('Already processed', 200);
        }

        WebhookEvent::create([
            'stripe_event_id' => $event->id,
            'type' => $event->type,
            'payload' => $event->toArray(),
        ]);

        // Async emal et
        match($event->type) {
            'payment_intent.succeeded' => ProcessSuccessfulPayment::dispatch($event->data->object),
            'payment_intent.payment_failed' => ProcessFailedPayment::dispatch($event->data->object),
            'charge.refunded' => ProcessRefund::dispatch($event->data->object),
            'charge.dispute.created' => HandleDispute::dispatch($event->data->object),
            default => Log::info("Unhandled webhook: {$event->type}"),
        };

        return response('OK', 200);
    }
}

class ProcessSuccessfulPayment implements ShouldQueue {
    public int $tries = 5;
    public array $backoff = [10, 30, 60, 300, 900];

    public function handle(): void {
        $paymentIntent = $this->paymentIntentData;
        $orderId = $paymentIntent['metadata']['order_id'];

        $order = Order::findOrFail($orderId);

        if ($order->status !== OrderStatus::Pending) {
            return; // Artıq emal olunub
        }

        DB::transaction(function () use ($order, $paymentIntent) {
            // Payment record
            Payment::create([
                'order_id' => $order->id,
                'stripe_payment_intent_id' => $paymentIntent['id'],
                'amount' => $paymentIntent['amount'] / 100,
                'currency' => $paymentIntent['currency'],
                'status' => 'completed',
            ]);

            $order->update(['status' => OrderStatus::Confirmed]);
        });

        // Async notifications
        Bus::chain([
            new SendOrderConfirmationEmail($order),
            new SendOrderConfirmationSms($order),
            new NotifyAdminNewOrder($order),
        ])->dispatch();
    }
}
```

---

## 4. Product Search & Filtering (E-commerce üçün)

```php
// Controller
class ProductController extends Controller {
    public function index(ProductFilterRequest $request): JsonResponse {
        $products = app(Pipeline::class)
            ->send(Product::query()->with(['category', 'images', 'variants']))
            ->through([
                Filters\FilterByCategory::class,
                Filters\FilterByPriceRange::class,
                Filters\FilterByBrand::class,
                Filters\FilterByAttributes::class,
                Filters\FilterByRating::class,
                Filters\FilterByAvailability::class,
                Filters\SearchByKeyword::class,
                Filters\SortProducts::class,
            ])
            ->thenReturn()
            ->paginate($request->input('per_page', 20));

        return ProductResource::collection($products)->response();
    }
}

// Filter nümunələri
class FilterByPriceRange {
    public function handle(Builder $query, Closure $next): mixed {
        if (request()->filled('min_price')) {
            $query->where('price', '>=', request('min_price'));
        }
        if (request()->filled('max_price')) {
            $query->where('price', '<=', request('max_price'));
        }
        return $next($query);
    }
}

class FilterByAttributes {
    public function handle(Builder $query, Closure $next): mixed {
        if (request()->filled('attributes')) {
            // ?attributes[color]=red&attributes[size]=xl
            foreach (request('attributes') as $key => $value) {
                $query->whereHas('attributeValues', function ($q) use ($key, $value) {
                    $q->whereHas('attribute', fn ($q) => $q->where('slug', $key))
                      ->where('value', $value);
                });
            }
        }
        return $next($query);
    }
}

class SearchByKeyword {
    public function handle(Builder $query, Closure $next): mixed {
        if (request()->filled('q')) {
            $keyword = request('q');
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'LIKE', "%{$keyword}%")
                  ->orWhere('description', 'LIKE', "%{$keyword}%")
                  ->orWhere('sku', 'LIKE', "%{$keyword}%")
                  ->orWhereHas('tags', fn ($q) => $q->where('name', 'LIKE', "%{$keyword}%"));
            });
        }
        return $next($query);
    }
}

class SortProducts {
    public function handle(Builder $query, Closure $next): mixed {
        $sortField = request('sort', 'created_at');
        $sortDir = request('direction', 'desc');

        $allowed = ['price', 'name', 'created_at', 'rating', 'popularity'];
        if (!in_array($sortField, $allowed)) {
            $sortField = 'created_at';
        }

        if ($sortField === 'popularity') {
            $query->withCount('orders')->orderBy('orders_count', $sortDir);
        } elseif ($sortField === 'rating') {
            $query->withAvg('reviews', 'rating')->orderBy('reviews_avg_rating', $sortDir);
        } else {
            $query->orderBy($sortField, $sortDir);
        }

        return $next($query);
    }
}
```

---

## 5. Coupon / Discount sistemi

```php
// Coupon types
enum CouponType: string {
    case Percentage = 'percentage';      // 15% endirim
    case FixedAmount = 'fixed_amount';   // 10 AZN endirim
    case FreeShipping = 'free_shipping'; // Pulsuz çatdırılma
    case BuyXGetY = 'buy_x_get_y';      // 2 al 1 ödə
}

class Coupon extends Model {
    protected function casts(): array {
        return [
            'type' => CouponType::class,
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'rules' => 'array',
        ];
    }

    public function isValid(Cart $cart): bool {
        if ($this->isExpired()) return false;
        if ($this->isUsageLimitReached()) return false;
        if ($this->hasUserUsed(auth()->id())) return false;
        if (!$this->meetsMinimumAmount($cart)) return false;
        if (!$this->isApplicableToProducts($cart)) return false;
        return true;
    }

    public function calculateDiscount(Cart $cart): float {
        return match($this->type) {
            CouponType::Percentage => min(
                $cart->subtotal * ($this->value / 100),
                $this->max_discount ?? PHP_FLOAT_MAX
            ),
            CouponType::FixedAmount => min($this->value, $cart->subtotal),
            CouponType::FreeShipping => $cart->shipping_cost,
            CouponType::BuyXGetY => $this->calculateBuyXGetY($cart),
        };
    }

    private function calculateBuyXGetY(Cart $cart): float {
        // rules: {"buy": 2, "get": 1, "product_ids": [1,2,3]}
        $rules = $this->rules;
        $eligibleItems = $cart->items->whereIn('product_id', $rules['product_ids']);

        $totalQuantity = $eligibleItems->sum('quantity');
        $setsCount = intdiv($totalQuantity, $rules['buy'] + $rules['get']);
        $freeCount = $setsCount * $rules['get'];

        // Ən ucuz məhsullar pulsuz olur
        $prices = $eligibleItems->flatMap(function ($item) {
            return array_fill(0, $item->quantity, $item->unit_price);
        })->sort()->take($freeCount);

        return $prices->sum();
    }

    public function isExpired(): bool {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isUsageLimitReached(): bool {
        return $this->usage_limit && $this->used_count >= $this->usage_limit;
    }

    public function hasUserUsed(int $userId): bool {
        if (!$this->per_user_limit) return false;

        $userUsage = Order::where('user_id', $userId)
            ->where('coupon_id', $this->id)
            ->whereNotIn('status', ['cancelled', 'refunded'])
            ->count();

        return $userUsage >= $this->per_user_limit;
    }
}
```

---

## 6. Review & Rating sistemi

```php
class ReviewService {
    public function submit(CreateReviewDTO $dto): Review {
        // Yalnız sifarişi tamamlanmış user review yaza bilər
        $hasPurchased = Order::where('user_id', $dto->userId)
            ->where('status', OrderStatus::Delivered)
            ->whereHas('items', fn ($q) => $q->where('product_id', $dto->productId))
            ->exists();

        if (!$hasPurchased) {
            throw new CannotReviewException('Bu məhsulu almamısınız.');
        }

        // Bir məhsul üçün yalnız bir review
        $existingReview = Review::where('user_id', $dto->userId)
            ->where('product_id', $dto->productId)
            ->first();

        if ($existingReview) {
            throw new DuplicateReviewException();
        }

        $review = DB::transaction(function () use ($dto) {
            $review = Review::create([
                'user_id' => $dto->userId,
                'product_id' => $dto->productId,
                'rating' => $dto->rating,
                'title' => $dto->title,
                'body' => $dto->body,
                'is_verified_purchase' => true,
                'status' => 'pending', // Moderasiya
            ]);

            // Product rating yenilə (denormalized)
            $this->updateProductRating($dto->productId);

            return $review;
        });

        ReviewSubmitted::dispatch($review);

        return $review;
    }

    private function updateProductRating(int $productId): void {
        $stats = Review::where('product_id', $productId)
            ->where('status', 'approved')
            ->selectRaw('AVG(rating) as avg_rating, COUNT(*) as review_count')
            ->first();

        Product::where('id', $productId)->update([
            'avg_rating' => round($stats->avg_rating, 1),
            'review_count' => $stats->review_count,
        ]);
    }
}
```
