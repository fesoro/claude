# Advanced Laravel

## 1. Laravel Pipeline nədir?

Bir datanı ardıcıl mərhələlərdən keçirmək. Middleware sistemi bunun üzərində qurulub.

```php
use Illuminate\Pipeline\Pipeline;

class OrderFilterPipeline {
    public function handle(Request $request): LengthAwarePaginator {
        return app(Pipeline::class)
            ->send(Order::query())
            ->through([
                FilterByStatus::class,
                FilterByDateRange::class,
                FilterByUser::class,
                SortOrders::class,
            ])
            ->thenReturn()
            ->paginate();
    }
}

// Hər filter bir "pipe"
class FilterByStatus {
    public function handle(Builder $query, Closure $next): mixed {
        if (request()->has('status')) {
            $query->where('status', request('status'));
        }
        return $next($query);
    }
}

class FilterByDateRange {
    public function handle(Builder $query, Closure $next): mixed {
        if (request()->has(['from', 'to'])) {
            $query->whereBetween('created_at', [request('from'), request('to')]);
        }
        return $next($query);
    }
}
```

---

## 2. Laravel Notifications

```php
class OrderShippedNotification extends Notification implements ShouldQueue {
    use Queueable;

    public function __construct(private Order $order) {}

    // Hansı kanallarla göndərmək
    public function via(object $notifiable): array {
        return $notifiable->prefers_sms
            ? ['mail', 'vonage']
            : ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage {
        return (new MailMessage)
            ->subject('Sifarişiniz göndərildi!')
            ->greeting("Salam, {$notifiable->name}!")
            ->line("#{$this->order->id} nömrəli sifarişiniz yola salındı.")
            ->action('Sifarişi izlə', url("/orders/{$this->order->id}"))
            ->line('Təşəkkür edirik!');
    }

    public function toDatabase(object $notifiable): array {
        return [
            'order_id' => $this->order->id,
            'message' => "Sifarişiniz #{$this->order->id} göndərildi",
            'tracking_url' => $this->order->tracking_url,
        ];
    }

    public function toArray(object $notifiable): array {
        return [
            'order_id' => $this->order->id,
        ];
    }
}

// Göndərmə
$user->notify(new OrderShippedNotification($order));
Notification::send($users, new OrderShippedNotification($order));

// Database notifications-u oxumaq
$user->unreadNotifications;
$user->notifications()->latest()->paginate();
$notification->markAsRead();
```

---

## 3. Laravel Broadcasting (WebSockets)

```php
// Event
class MessageSent implements ShouldBroadcast {
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Message $message,
    ) {}

    public function broadcastOn(): array {
        return [
            new PrivateChannel('chat.' . $this->message->conversation_id),
        ];
    }

    public function broadcastWith(): array {
        return [
            'id' => $this->message->id,
            'body' => $this->message->body,
            'user' => new UserResource($this->message->user),
            'created_at' => $this->message->created_at->toISOString(),
        ];
    }
}

// Channel authorization (routes/channels.php)
Broadcast::channel('chat.{conversationId}', function (User $user, int $conversationId) {
    return $user->conversations()->where('id', $conversationId)->exists();
});

// Frontend (Echo)
Echo.private(`chat.${conversationId}`)
    .listen('MessageSent', (e) => {
        messages.push(e);
    });
```

---

## 4. Laravel Actions Pattern

```php
// Bir business əməliyyat = bir Action class
class CreateOrderAction {
    public function __construct(
        private OrderRepositoryInterface $orders,
        private ApplyDiscountAction $applyDiscount,
        private CalculateShippingAction $calculateShipping,
    ) {}

    public function execute(CreateOrderDTO $dto): Order {
        DB::transaction(function () use ($dto, &$order) {
            $order = $this->orders->create($dto);

            $discount = $this->applyDiscount->execute($order, $dto->couponCode);
            $shipping = $this->calculateShipping->execute($order);

            $order->update([
                'discount' => $discount,
                'shipping' => $shipping,
                'total' => $order->subtotal - $discount + $shipping,
            ]);
        });

        OrderPlaced::dispatch($order);

        return $order->fresh();
    }
}

// Controller sadə qalır
class OrderController {
    public function store(StoreOrderRequest $request, CreateOrderAction $action): JsonResponse {
        $order = $action->execute(CreateOrderDTO::fromRequest($request));
        return new OrderResource($order);
    }
}
```

---

## 5. Laravel Collections - Advanced

```php
$orders = Order::with('items.product')->get();

// Ümumi gəlir
$revenue = $orders->sum('total');

// Status-a görə qruplaşdır
$grouped = $orders->groupBy('status');
// ['pending' => [...], 'shipped' => [...]]

// Transform
$summary = $orders->map(fn ($order) => [
    'id' => $order->id,
    'total' => $order->total,
    'items_count' => $order->items->count(),
]);

// Filter + sort + unique
$topProducts = $orders
    ->flatMap->items                          // bütün items düz siyahıda
    ->groupBy('product_id')
    ->map(fn ($items) => [
        'product' => $items->first()->product->name,
        'quantity' => $items->sum('quantity'),
        'revenue' => $items->sum(fn ($i) => $i->price * $i->quantity),
    ])
    ->sortByDesc('revenue')
    ->take(10);

// Reduce
$cart = collect($items)->reduce(function ($carry, $item) {
    $carry['total'] += $item['price'] * $item['quantity'];
    $carry['count'] += $item['quantity'];
    return $carry;
}, ['total' => 0, 'count' => 0]);

// Lazy collection — yaddaş effektiv
LazyCollection::make(function () {
    $handle = fopen('huge-file.csv', 'r');
    while ($line = fgetcsv($handle)) {
        yield $line;
    }
})->chunk(1000)->each(function ($chunk) {
    DB::table('imports')->insert($chunk->toArray());
});
```

---

## 6. Multi-tenancy yanaşmaları

```php
// 1. Database per tenant
// Hər tenant-ın ayrı DB-si var
config(['database.connections.tenant.database' => $tenant->database]);

// 2. Schema per tenant (PostgreSQL)
DB::statement("SET search_path TO {$tenant->schema}");

// 3. Shared database with tenant_id (ən sadə)
// Global scope ilə
class TenantScope implements Scope {
    public function apply(Builder $builder, Model $model): void {
        $builder->where('tenant_id', auth()->user()->tenant_id);
    }
}

// Trait
trait BelongsToTenant {
    protected static function booted(): void {
        static::addGlobalScope(new TenantScope());
        static::creating(function ($model) {
            $model->tenant_id = auth()->user()->tenant_id;
        });
    }
}

class Order extends Model {
    use BelongsToTenant;
}

// Artıq bütün sorğular avtomatik tenant_id filter edir
Order::all(); // WHERE tenant_id = 5
```

---

## 7. File Storage və Upload

```php
class FileUploadController {
    public function store(Request $request): JsonResponse {
        $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,png'],
        ]);

        $path = $request->file('file')->store('uploads', 's3');

        $media = Media::create([
            'path' => $path,
            'original_name' => $request->file('file')->getClientOriginalName(),
            'mime_type' => $request->file('file')->getMimeType(),
            'size' => $request->file('file')->getSize(),
            'disk' => 's3',
        ]);

        return response()->json([
            'url' => Storage::disk('s3')->temporaryUrl($path, now()->addHour()),
        ]);
    }
}

// Temporary URL (pre-signed)
$url = Storage::disk('s3')->temporaryUrl($path, now()->addMinutes(30));
```
