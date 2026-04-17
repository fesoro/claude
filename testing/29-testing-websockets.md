# Testing WebSockets & Real-Time Features

## Nədir? (What is it?)

WebSocket və real-time testing, broadcasting (Pusher, Soketi, Laravel Reverb), presence
channels, private channels və real-time notification-lar üçün istifadə olunan
mexanizmlərin düzgün işlədiyini yoxlamaq prosesidir. Real WebSocket connection açmaq
test üçün çətindir; buna görə də Laravel `Event::fake()` və broadcasting assertion-lar
təqdim edir.

### Niyə Real-Time Testing Mürəkkəbdir?

1. **Asynchronous nature** - Event emit edilir, client async alır
2. **Connection state** - Presence channel join/leave state-li
3. **Authorization** - Private channel-da auth callback yoxlanılmalıdır
4. **Multiple clients** - Bir neçə subscriber-in görməsi lazımdır
5. **Infrastructure** - Pusher/Soketi server test-də qaldırılmalıdır?

## Əsas Konseptlər (Key Concepts)

### Broadcasting Channel Types

```
public        → Kiməsə bağlı deyil, hamı dinləyə bilər (chat-rooms.1)
private       → Auth tələb olunur (user.{id})
presence      → private + kim online olduğunu göstərir (team.{teamId})
```

### Broadcasting Events

```php
// app/Events/MessageSent.php
class MessageSent implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(public Message $message) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("chat.{$this->message->chat_id}")];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    public function broadcastWith(): array
    {
        return ['id' => $this->message->id, 'text' => $this->message->text];
    }
}
```

### Testing Assertions

```php
Event::fake();
// or
Broadcast::fake(); // yeni Laravel versiyalarında

// Əsas assertion-lar
Event::assertDispatched(MessageSent::class);
Event::assertDispatched(MessageSent::class, fn ($e) => $e->message->text === 'Hi');
```

## Praktiki Nümunələr (Practical Examples)

### Broadcast Event Test

```php
public function test_sending_message_broadcasts_event(): void
{
    Event::fake();

    $user = User::factory()->create();
    $chat = Chat::factory()->create();

    $this->actingAs($user)
        ->postJson("/api/chats/{$chat->id}/messages", ['text' => 'Hello'])
        ->assertCreated();

    Event::assertDispatched(MessageSent::class,
        fn ($e) => $e->message->text === 'Hello'
    );
}
```

### Channel Authorization Test

```php
public function test_user_can_join_private_channel_for_their_chat(): void
{
    $user = User::factory()->create();
    $chat = Chat::factory()->hasAttached($user)->create();

    $this->actingAs($user)
        ->postJson('/broadcasting/auth', [
            'socket_id'     => '1234.1234',
            'channel_name'  => "private-chat.{$chat->id}",
        ])
        ->assertOk();
}
```

## PHP/Laravel ilə Tətbiq (Implementation with PHP/Laravel)

### 1. Broadcast Event Implementation & Test

```php
// app/Events/OrderStatusChanged.php
class OrderStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Order $order,
        public string $oldStatus,
        public string $newStatus,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("orders.{$this->order->user_id}")];
    }

    public function broadcastAs(): string
    {
        return 'order.status.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'order_id'   => $this->order->id,
            'old_status' => $this->oldStatus,
            'new_status' => $this->newStatus,
            'changed_at' => now()->toIso8601String(),
        ];
    }
}

// tests/Unit/Events/OrderStatusChangedTest.php
class OrderStatusChangedTest extends TestCase
{
    use RefreshDatabase;

    public function test_broadcasts_on_correct_channel(): void
    {
        $order = Order::factory()->create(['user_id' => 42]);
        $event = new OrderStatusChanged($order, 'pending', 'shipped');

        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertSame('private-orders.42', $channels[0]->name);
    }

    public function test_broadcast_payload(): void
    {
        $order = Order::factory()->create();
        $event = new OrderStatusChanged($order, 'pending', 'shipped');

        $payload = $event->broadcastWith();

        $this->assertSame($order->id, $payload['order_id']);
        $this->assertSame('pending', $payload['old_status']);
        $this->assertSame('shipped', $payload['new_status']);
    }

    public function test_broadcast_event_name(): void
    {
        $event = new OrderStatusChanged(Order::factory()->create(), 'a', 'b');

        $this->assertSame('order.status.changed', $event->broadcastAs());
    }
}

// tests/Feature/OrderStatusUpdateTest.php
class OrderStatusUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_changing_order_status_broadcasts_event(): void
    {
        Event::fake([OrderStatusChanged::class]);

        $admin = User::factory()->admin()->create();
        $order = Order::factory()->create(['status' => 'pending']);

        $this->actingAs($admin)
            ->patchJson("/api/orders/{$order->id}", ['status' => 'shipped'])
            ->assertOk();

        Event::assertDispatched(OrderStatusChanged::class,
            function ($event) use ($order) {
                return $event->order->is($order)
                    && $event->oldStatus === 'pending'
                    && $event->newStatus === 'shipped';
            }
        );
    }

    public function test_no_broadcast_when_status_unchanged(): void
    {
        Event::fake([OrderStatusChanged::class]);

        $order = Order::factory()->create(['status' => 'pending']);

        $this->actingAs(User::factory()->admin()->create())
            ->patchJson("/api/orders/{$order->id}", ['status' => 'pending'])
            ->assertOk();

        Event::assertNotDispatched(OrderStatusChanged::class);
    }
}
```

### 2. Channel Authorization Test

```php
// routes/channels.php
Broadcast::channel('orders.{userId}', function (User $user, int $userId) {
    return $user->id === $userId;
});

Broadcast::channel('chat.{chatId}', function (User $user, int $chatId) {
    return $user->chats()->where('chats.id', $chatId)->exists();
});

Broadcast::channel('team.{teamId}', function (User $user, int $teamId) {
    if ($user->teams()->where('teams.id', $teamId)->exists()) {
        return ['id' => $user->id, 'name' => $user->name];
    }
    return false;
});

// tests/Feature/Broadcasting/ChannelAuthorizationTest.php
class ChannelAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_authorize_own_orders_channel(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/broadcasting/auth', [
                'socket_id'    => '1234.5678',
                'channel_name' => "private-orders.{$user->id}",
            ])
            ->assertOk();
    }

    public function test_user_cannot_authorize_others_orders_channel(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/broadcasting/auth', [
                'socket_id'    => '1234.5678',
                'channel_name' => "private-orders.{$other->id}",
            ])
            ->assertForbidden();
    }

    public function test_guest_cannot_authorize_private_channel(): void
    {
        $this->postJson('/broadcasting/auth', [
            'socket_id'    => '1234.5678',
            'channel_name' => 'private-orders.1',
        ])->assertForbidden();
    }
}
```

### 3. Presence Channel Test

```php
// Channel returns user info for presence
public function test_team_member_joins_presence_channel(): void
{
    $team = Team::factory()->create();
    $user = User::factory()->hasAttached($team)->create([
        'name' => 'Orkhan',
    ]);

    $this->actingAs($user)
        ->postJson('/broadcasting/auth', [
            'socket_id'    => '1234.5678',
            'channel_name' => "presence-team.{$team->id}",
        ])
        ->assertOk()
        ->assertJsonStructure([
            'auth',
            'channel_data',
        ])
        ->assertJsonPath('channel_data.user_info.name', 'Orkhan');
}

public function test_non_member_cannot_join_team_presence(): void
{
    $team = Team::factory()->create();
    $user = User::factory()->create(); // not in team

    $this->actingAs($user)
        ->postJson('/broadcasting/auth', [
            'socket_id'    => '1234.5678',
            'channel_name' => "presence-team.{$team->id}",
        ])
        ->assertForbidden();
}
```

### 4. Broadcast toOthers() Test

```php
// Event uses toOthers to exclude the sender
class MessageSent implements ShouldBroadcast
{
    use InteractsWithSockets; // gives dontBroadcastToCurrentUser

    // controller: broadcast(new MessageSent($msg))->toOthers();
}

// Test
public function test_message_broadcast_excludes_sender_socket(): void
{
    Event::fake([MessageSent::class]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->withHeader('X-Socket-ID', 'socket-123')
        ->postJson('/api/messages', ['text' => 'Hi']);

    Event::assertDispatched(MessageSent::class, function ($event) {
        return $event->socket === 'socket-123';
    });
}
```

### 5. Notification via Broadcast Channel

```php
// app/Notifications/NewOrderNotification.php
class NewOrderNotification extends Notification implements ShouldBroadcast
{
    public function __construct(public Order $order) {}

    public function via(object $notifiable): array
    {
        return ['broadcast', 'database'];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'order_id' => $this->order->id,
            'total'    => $this->order->total,
        ]);
    }

    public function toArray(object $notifiable): array
    {
        return ['order_id' => $this->order->id];
    }
}

// Test
public function test_new_order_broadcasts_notification_to_admin(): void
{
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $user  = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/orders', ['product_id' => 1, 'quantity' => 1])
        ->assertCreated();

    Notification::assertSentTo($admin, NewOrderNotification::class,
        function ($notification, array $channels) {
            return in_array('broadcast', $channels);
        }
    );
}
```

### 6. Testing Broadcast Connection (Integration)

```php
// For Reverb/Soketi - real WebSocket integration test
// Usually uses Ratchet/ReactPHP client

/**
 * @group websocket
 * @group slow
 */
public function test_real_websocket_receives_broadcast(): void
{
    $this->markTestSkipped('Requires Reverb server');

    // Start: php artisan reverb:start --port=9998
    $loop      = React\EventLoop\Loop::get();
    $connector = new Ratchet\Client\Connector($loop);

    $receivedMessage = null;
    $connector('ws://localhost:9998/app/key')
        ->then(function (WebSocket $conn) use (&$receivedMessage, $loop) {
            $conn->on('message', function ($msg) use (&$receivedMessage, $loop) {
                $receivedMessage = (string) $msg;
                $loop->stop();
            });

            $conn->send(json_encode([
                'event' => 'pusher:subscribe',
                'data'  => ['channel' => 'orders'],
            ]));

            // Trigger broadcast
            OrderPlaced::dispatch(Order::factory()->create());
        });

    $loop->addTimer(5, fn () => $loop->stop()); // timeout
    $loop->run();

    $this->assertNotNull($receivedMessage);
    $this->assertStringContainsString('OrderPlaced', $receivedMessage);
}
```

### 7. Queue-able Broadcast

```php
// ShouldBroadcastNow vs ShouldBroadcast
class UrgentAlert implements ShouldBroadcastNow // queue bypass
{
    // sync broadcasting
}

// Test
public function test_urgent_alert_broadcasts_immediately(): void
{
    Queue::fake();
    Event::fake();

    UrgentAlert::dispatch('Server down');

    Event::assertDispatched(UrgentAlert::class);
    Queue::assertNothingPushed(); // immediate, not queued
}
```

### 8. Broadcasting Conditions

```php
class PrivateMessage implements ShouldBroadcast
{
    public function __construct(public Message $message) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("user.{$this->message->to_id}")];
    }

    public function broadcastWhen(): bool
    {
        return $this->message->to_user->prefers_realtime;
    }
}

// Test
public function test_broadcast_skipped_when_user_disabled_realtime(): void
{
    Event::fake();

    $sender    = User::factory()->create();
    $recipient = User::factory()->create(['prefers_realtime' => false]);

    $this->actingAs($sender)->postJson('/api/messages', [
        'to_id' => $recipient->id,
        'text'  => 'Hi',
    ]);

    Event::assertNotDispatched(PrivateMessage::class);
    // Or check broadcastWhen returned false separately
}
```

## Interview Sualları

**Q1: `ShouldBroadcast` və `ShouldBroadcastNow` fərqi?**
A: `ShouldBroadcast` - queue üzərindən göndərilir (async). `ShouldBroadcastNow` -
sync, immediate.

**Q2: Private channel authorization necə test olunur?**
A: `/broadcasting/auth` endpoint-ə POST göndərilir; channel name və socket ID ilə.
Assertion: 200 OK (icazə var) / 403 (yox).

**Q3: Presence channel vs Private channel fərqi?**
A: Presence = Private + kim online olduğunu tracking. Callback `false` əvəzinə user info
array qaytarır.

**Q4: `Event::fake()` broadcast-ları da dayandırır?**
A: Bəli. `ShouldBroadcast` event də Event facade üzərindən gedir; fake-lənir.

**Q5: `broadcastWith` nə üçündür?**
A: Client-ə göndəriləcək payload-ı customize edir. Default olaraq event-in public
property-ləri serialize olunur.

**Q6: `toOthers()` nə edir və necə test olunur?**
A: Sender-in öz socket-inə broadcast getmir. X-Socket-ID header yoxlanılır — event-in
`socket` property-si set olur.

**Q7: Real WebSocket integration test niyə çətindir?**
A: Async connection, timing, WebSocket server-in qaldırılması. Adətən E2E suite-də,
Docker ilə Reverb/Soketi qaldırılır.

**Q8: `broadcastWhen` nədir?**
A: Bool qaytaran metod — `false` olarsa broadcast atlanılır. Conditional broadcasting
üçün (user preference, time, flag).

**Q9: Channel callback-ini unit test etmək olar?**
A: Bəli, `broadcasting_channels.php`-dən callback-i çağırıb argumentlərlə yoxlamaq olar.
Amma `/broadcasting/auth` feature test daha real scenario verir.

**Q10: Broadcasting driver-lər (pusher, reverb, log) test-də nə fərq edir?**
A: Test environment-də `BROADCAST_DRIVER=log` və ya `null` istifadə olunur — real
network call getmir. `Event::fake()` ilə tam dayandırılır.

## Best Practices / Anti-Patterns

### Best Practices

- **`Event::fake()` istifadə edin** - Real broadcast server lazım deyil
- **Channel authorization ayrı test** - Kritik security boundary
- **Payload-ı `broadcastWith` test edin** - Client tərəfi asılıdır
- **Channel adı constant** - `"orders.{$id}"` hər yerdə yazılmır
- **`broadcastWhen` logic-i unit test** - Conditional broadcast səhv olarsa silent
- **Integration test-i ayrı suite** - `@group websocket` ilə CI-də ayrı step

### Anti-Patterns

- **Real WebSocket hər unit test-də** - Slow, flaky
- **Channel authorization-u bypass** - Private channel-ı public kimi test etmək
- **Payload struktur test etməmək** - Frontend pozulur
- **Yalnız happy path** - Unauthorized access test olunmur
- **Broadcast driver prod-da null** - Config səhv deploy olarsa real-time işləmir
- **`broadcastOn` return-unu hardcode** - Dinamik ID-lər channel-da dəyişir
