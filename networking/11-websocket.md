# WebSocket (Middle)

## İcmal

WebSocket client və server arasında full-duplex, persistent bağlantı yaradan protokoldur (RFC 6455). HTTP-dən fərqli olaraq, bir dəfə bağlantı qurulduqdan sonra hər iki tərəf istədiyi vaxt mesaj göndərə bilər. Real-time tətbiqlər (chat, notifications, live data) üçün idealdır.

```
HTTP (Half-duplex):
  Client --request-->  Server
  Client <--response-- Server
  (Hər dəfə yeni bağlantı, yalnız client initiate edir)

WebSocket (Full-duplex):
  Client <============> Server
  (Daimi bağlantı, hər iki tərəf istədiyi vaxt mesaj göndərir)
```

## Niyə Vacibdir

Real-time tətbiqlərdə (chat, online oyunlar, canlı dashboard-lar, trading platformaları) HTTP polling həddən artıq əlavə yük yaradır. WebSocket daimi açıq bağlantı saxlayır — hər mesaj üçün yeni HTTP connection qurulmur, header overhead yoxdur. Bu latency-ni əhəmiyyətli dərəcədə azaldır. Laravel Reverb vasitəsilə PHP ekosistemindən birbaşa istifadə olunur.

## Əsas Anlayışlar

### WebSocket Handshake (HTTP Upgrade)

```
1. Client HTTP Upgrade request göndərir:

GET /chat HTTP/1.1
Host: example.com
Upgrade: websocket
Connection: Upgrade
Sec-WebSocket-Key: dGhlIHNhbXBsZSBub25jZQ==
Sec-WebSocket-Version: 13
Sec-WebSocket-Protocol: chat, superchat
Origin: http://example.com

2. Server 101 Switching Protocols cavabı qaytarır:

HTTP/1.1 101 Switching Protocols
Upgrade: websocket
Connection: Upgrade
Sec-WebSocket-Accept: s3pPLMBiTxaQ9kYGzzhZRbK+xOo=
Sec-WebSocket-Protocol: chat

3. İndi bağlantı WebSocket protokoluna keçdi!
   Binary frames ilə kommunikasiya başlayır.
```

### WebSocket Frame Structure

```
 0                   1                   2                   3
 0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1
+-+-+-+-+-------+-+-------------+-------------------------------+
|F|R|R|R| opcode|M| Payload len |    Extended payload length    |
|I|S|S|S|  (4)  |A|     (7)     |             (16/64)           |
|N|V|V|V|       |S|             |   (if payload len==126/127)   |
| |1|2|3|       |K|             |                               |
+-+-+-+-+-------+-+-------------+-------------------------------+
|                     Payload Data                              |
+---------------------------------------------------------------+

Opcodes:
  0x0 = Continuation frame
  0x1 = Text frame (UTF-8)
  0x2 = Binary frame
  0x8 = Close
  0x9 = Ping
  0xA = Pong
```

### Connection Lifecycle

```
Client                                  Server
  |                                       |
  |--- HTTP Upgrade Request ------------->|
  |<-- 101 Switching Protocols -----------|
  |                                       |
  |=== WebSocket Connection Open =========|
  |                                       |
  |--- Text Frame: "Hello" -------------->|
  |<-- Text Frame: "Hi there!" ----------|
  |                                       |
  |<-- Text Frame: "New notification" ---|  (Server push)
  |                                       |
  |--- Ping ------------------------------>|
  |<-- Pong -------------------------------|
  |                                       |
  |--- Close Frame ---------------------->|
  |<-- Close Frame -----------------------|
  |                                       |
  |=== Connection Closed =================|
```

### Heartbeat (Ping/Pong)

```
Niyə lazımdır?
- Bağlantının hala aktiv olduğunu yoxlamaq
- NAT/proxy timeout-dan qorunmaq
- Dead connection-ları aşkarlamaq

Client                   Server
  |                        |
  |--- Ping -------------->|  (hər 30 saniyə)
  |<-- Pong ---------------|
  |                        |
  |--- Ping -------------->|
  |     (cavab gəlmədi)    |
  |--- Close + Reconnect   |  (connection dead)
```

### WebSocket vs HTTP vs SSE

```
+------------------+----------+----------+----------+
| Feature          | HTTP     | SSE      | WebSocket|
+------------------+----------+----------+----------+
| Direction        | Client→S | Server→C | Both ↔   |
| Protocol         | HTTP     | HTTP     | WS       |
| Connection       | Short    | Long     | Long     |
| Data format      | Any      | Text     | Any      |
| Auto reconnect   | No       | Yes      | No       |
| Binary support   | Yes      | No       | Yes      |
| Firewall issues  | No       | No       | Sometimes|
| Max connections  | ~6/domain| ~6/domain| Unlimited|
+------------------+----------+----------+----------+
```

### Scaling WebSocket

```
Problem: WebSocket stateful-dir, load balancer arxasında çətindir

Tək server:
  Client A -----> Server 1 (Client A-nın connection-u burda)
  Client B -----> Server 1

Çoxlu server:
  Client A -----> Server 1 (A burda qoşuludur)
  Client B -----> Server 2 (B burda qoşuludur)

  Server 1 A-ya mesaj göndərmək istəsə OK.
  Amma Server 1 B-yə mesaj göndərmək istəsə? B Server 2-dədir!

Həll yolu: Pub/Sub (Redis, Pusher, etc.)

  Server 1 --publish--> Redis <--subscribe-- Server 2
                          |
  Client A <-- Server 1   |   Server 2 --> Client B
  (Redis vasitəsilə bütün serverlər mesajı alır)
```

### Sticky Sessions

```
Load Balancer WebSocket üçün sticky session istifadə etməlidir:

  Client A ----> LB ----> həmişə Server 1
  Client A ----> LB ----> Server 2  ✗ (bağlantını itirir!)

  ip_hash və ya cookie-based sticky session
```

## Praktik Baxış

**Nə vaxt WebSocket istifadə etmək lazımdır:**
- Bidirectional real-time kommunikasiya (chat, oyunlar)
- Binary data axını lazım olanda
- Çox sürətli, hər iki yönlü mesajlaşma

**Nə vaxt SSE seçmək lazımdır:**
- Yalnız server-dən client-ə data lazımdır (notifications, live feed)
- Daha sadə implementation lazım olanda

**Trade-off-lar:**
- Stateful olması scaling-i çətinləşdirir — Redis Pub/Sub mütləqdir
- Proxy/firewall bəzən WebSocket bağlantılarını kəsir
- Reconnection logic manual yazılmalıdır
- Server resource (memory/connections) sərfi çoxdur

**Anti-pattern-lər:**
- Yalnız server-dən client-ə data lazım olanda WebSocket seçmək (SSE daha uyğundur)
- Redis Pub/Sub olmadan multi-server deploy etmək
- Reconnect logic-siz istifadə etmək
- Sticky session olmadan session-based auth istifadə etmək

## Nümunələr

### Ümumi Nümunə

```
Client qoşulur --> WebSocket server --> Redis channel-a subscribe olur
Başqa server mesaj publish edir --> Redis --> Bütün subscriber-lər alır --> Öz client-lərinə göndərirlər
```

### Kod Nümunəsi

**Laravel Broadcasting + Reverb setup:**

```bash
# Laravel Reverb (Laravel-in öz WebSocket server-i, Laravel 11+)
composer require laravel/reverb
php artisan reverb:install

# .env
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=my-app
REVERB_APP_KEY=my-key
REVERB_APP_SECRET=my-secret
REVERB_HOST=localhost
REVERB_PORT=8080
```

**Event Broadcasting:**

```php
// app/Events/MessageSent.php
namespace App\Events;

use App\Models\Message;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Message $message,
        public User $user
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('chat.' . $this->message->room_id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'body' => $this->message->body,
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ],
            'created_at' => $this->message->created_at->toISOString(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }
}
```

**Channel Authorization:**

```php
// routes/channels.php
use App\Models\ChatRoom;

// Private channel - yalnız authorized users
Broadcast::channel('chat.{roomId}', function ($user, int $roomId) {
    return ChatRoom::find($roomId)?->hasUser($user);
});

// Presence channel - kim online-dir görmək üçün
Broadcast::channel('chat.{roomId}', function ($user, int $roomId) {
    if (ChatRoom::find($roomId)?->hasUser($user)) {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'avatar' => $user->avatar_url,
        ];
    }
    return null;
});
```

**Controller - Mesaj Göndərmək:**

```php
namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Models\ChatRoom;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function sendMessage(Request $request, ChatRoom $room): JsonResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:1000'],
        ]);

        $message = $room->messages()->create([
            'user_id' => $request->user()->id,
            'body' => $validated['body'],
        ]);

        // Bu event WebSocket ilə bütün channel-dəki userlərə broadcast olunur
        broadcast(new MessageSent($message, $request->user()))->toOthers();

        return response()->json(new MessageResource($message), 201);
    }
}
```

**Frontend - Laravel Echo:**

```javascript
// resources/js/bootstrap.js
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT,
    forceTLS: false,
    enabledTransports: ['ws', 'wss'],
});

// Private channel-ə qoşul
Echo.private(`chat.${roomId}`)
    .listen('.message.sent', (e) => {
        console.log('New message:', e.body);
        appendMessage(e);
    })
    .listenForWhisper('typing', (e) => {
        console.log(`${e.name} is typing...`);
    });

// Presence channel (kim online görmək üçün)
Echo.join(`chat.${roomId}`)
    .here((users) => {
        console.log('Online users:', users);
    })
    .joining((user) => {
        console.log(`${user.name} joined`);
    })
    .leaving((user) => {
        console.log(`${user.name} left`);
    })
    .listen('.message.sent', (e) => {
        appendMessage(e);
    });

// Client event (typing indicator)
Echo.private(`chat.${roomId}`)
    .whisper('typing', { name: 'Orkhan' });
```

**Real-time Notifications:**

```php
// app/Notifications/OrderShipped.php
namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\BroadcastMessage;

class OrderShipped extends Notification
{
    use Queueable;

    public function __construct(
        public Order $order
    ) {}

    public function via($notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toBroadcast($notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'order_id' => $this->order->id,
            'status' => 'shipped',
            'message' => "Sifarişiniz #{$this->order->id} göndərildi!",
        ]);
    }
}

// Notification göndərmək
$user->notify(new OrderShipped($order));

// Frontend
Echo.private(`App.Models.User.${userId}`)
    .notification((notification) => {
        console.log(notification.message);
        showToast(notification.message);
    });
```

## Praktik Tapşırıqlar

1. **Laravel Reverb qaldırın:** `composer require laravel/reverb` ilə install edin, `.env`-i konfiqurasiya edin, `php artisan reverb:start` ilə serveri başladın.

2. **Chat sistemi qurun:** `ChatRoom` modeli, `Message` modeli, `MessageSent` event-i yaradın. Private channel authorization implement edin.

3. **Typing indicator:** `whisper` API-sını istifadə edərək "X is typing..." göstəricisini implement edin. 2 saniyə cavab gəlməsə indicator-u gizlədin.

4. **Presence channel:** Kim online-dır? Siyahısını real-time göstərən panel qurun. Join/leave event-lərini handle edin.

5. **Redis scale test:** 2 ayrı `php artisan reverb:start` process-i əvəzinə Redis Pub/Sub adapterini konfiqurasiya edin. Hər iki process arasında mesajın çatdırılmasını yoxlayın.

6. **Notification sistemi:** `OrderShipped` notification-ını həm `database` həm `broadcast` channel-ı ilə göndərin. Frontend-də toast göstərin, unread count-u yeniləyin.

## Əlaqəli Mövzular

- [SSE - Server-Sent Events](12-sse.md)
- [Long Polling](13-long-polling.md)
- [HTTP Protocol](05-http-protocol.md)
- [Load Balancing](18-load-balancing.md)
- [API Gateway](21-api-gateway.md)
