# WebSocket

## Nədir? (What is it?)

WebSocket client ve server arasinda full-duplex, persistent baglanti yaradan protokoldur (RFC 6455). HTTP-den ferqli olaraq, bir defe baglanti qurulduqdan sonra her iki teref istediyi vaxt mesaj gondere biler. Real-time applicationlar (chat, notifications, live data) ucun idealdir.

```
HTTP (Half-duplex):
  Client --request-->  Server
  Client <--response-- Server
  (Her defe yeni baglanti, yalniz client initiate edir)

WebSocket (Full-duplex):
  Client <============> Server
  (Daimi baglanti, her iki teref istediyi vaxt mesaj gonderir)
```

## Necə İşləyir? (How does it work?)

### WebSocket Handshake (HTTP Upgrade)

```
1. Client HTTP Upgrade request gonderir:

GET /chat HTTP/1.1
Host: example.com
Upgrade: websocket
Connection: Upgrade
Sec-WebSocket-Key: dGhlIHNhbXBsZSBub25jZQ==
Sec-WebSocket-Version: 13
Sec-WebSocket-Protocol: chat, superchat
Origin: http://example.com

2. Server 101 Switching Protocols cavabi qaytarir:

HTTP/1.1 101 Switching Protocols
Upgrade: websocket
Connection: Upgrade
Sec-WebSocket-Accept: s3pPLMBiTxaQ9kYGzzhZRbK+xOo=
Sec-WebSocket-Protocol: chat

3. Indi baglanti WebSocket protokoluna keçdi!
   Binary frames ile kommunikasiya bashlayir.
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
Niye lazimdir?
- Baglandinin hala aktiv oldugunu yoxlamaq
- NAT/proxy timeout-dan qorunmaq
- Dead connection-lari aşkarlamaq

Client                   Server
  |                        |
  |--- Ping -------------->|  (her 30 saniye)
  |<-- Pong ---------------|
  |                        |
  |--- Ping -------------->|
  |     (cavab gelmedi)    |
  |--- Close + Reconnect   |  (connection dead)
```

## Əsas Konseptlər (Key Concepts)

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
Problem: WebSocket stateful-dir, load balancer arxasinda cetindir

Tek server:
  Client A -----> Server 1 (Client A-nin connection-u burda)
  Client B -----> Server 1

Coxlu server:
  Client A -----> Server 1 (A burda qosuludur)
  Client B -----> Server 2 (B burda qosuludur)

  Server 1 A-ya mesaj gondermek istese OK.
  Amma Server 1 B-ye mesaj gondermek istese? B Server 2-dedir!

Hell yolu: Pub/Sub (Redis, Pusher, etc.)

  Server 1 --publish--> Redis <--subscribe-- Server 2
                          |
  Client A <-- Server 1   |   Server 2 --> Client B
  (Redis vasitesile butun serverler mesaji alir)
```

### Sticky Sessions

```
Load Balancer WebSocket ucun sticky session istifade etmelidir:

  Client A ----> LB ----> hemise Server 1
  Client A ----> LB ----> Server 2  ✗ (baglantiyi itirir!)

  ip_hash ve ya cookie-based sticky session
```

## PHP/Laravel ilə İstifadə

### Laravel Broadcasting + Reverb

```bash
# Laravel Reverb (Laravel-in oz WebSocket server-i, Laravel 11+)
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

### Event Broadcasting

```php
// app/Events/MessageSent.php
namespace App\Events;

use App\Models\Message;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
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

    /**
     * Public channel - her kes gore biler
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('chat.' . $this->message->room_id),
        ];
    }

    /**
     * Broadcast olunacaq data
     */
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

    /**
     * Event adi (default: class adi)
     */
    public function broadcastAs(): string
    {
        return 'message.sent';
    }
}
```

### Channel Authorization

```php
// routes/channels.php
use App\Models\ChatRoom;

// Private channel - yalniz authorized users
Broadcast::channel('chat.{roomId}', function ($user, int $roomId) {
    return ChatRoom::find($roomId)?->hasUser($user);
});

// Presence channel - kim online-dir gormek ucun
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

### Controller - Message Gondermek

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

        // Bu event WebSocket ile butun channel-deki userlere broadcast olunur
        broadcast(new MessageSent($message, $request->user()))->toOthers();

        return response()->json(new MessageResource($message), 201);
    }
}
```

### Frontend - Laravel Echo

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

// Private channel-e qosul
Echo.private(`chat.${roomId}`)
    .listen('.message.sent', (e) => {
        console.log('New message:', e.body);
        appendMessage(e);
    })
    .listenForWhisper('typing', (e) => {
        console.log(`${e.name} is typing...`);
    });

// Presence channel (kim online gormek ucun)
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

### Real-time Notifications

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
            'message' => "Sifarishiniz #{$this->order->id} gonderildi!",
        ]);
    }
}

// Notification gondermek
$user->notify(new OrderShipped($order));

// Frontend
Echo.private(`App.Models.User.${userId}`)
    .notification((notification) => {
        console.log(notification.message);
        showToast(notification.message);
    });
```

## Interview Sualları

### 1. WebSocket nedir ve HTTP-den nece ferqlenir?
**Cavab:** WebSocket full-duplex, persistent connection protokoludur. HTTP-den ferqi: baglanti daimi qalir, her iki teref istediyi vaxt mesaj gonderir, daha az overhead (header yoxdur her frame-de). HTTP request-response-dur, WebSocket bidirectional-dir.

### 2. WebSocket handshake nece isleyir?
**Cavab:** Client HTTP GET request gonderir `Upgrade: websocket` header ile. Server 101 Switching Protocols cavabi qaytarir. Bundan sonra protocol HTTP-den WebSocket-a kecir. Bu "HTTP Upgrade" mexanizmi adlanir.

### 3. WebSocket-i nece scale edirsiniz?
**Cavab:** WebSocket stateful-dir, buna gore cetin scale olunur. Redis Pub/Sub ile serverler arasi mesaj paylashmaq, sticky sessions (ip_hash) ile client-in hemise eyni servere qoshulmasi, horizontal scaling ucun Redis adapter istifade etmek.

### 4. Ping/Pong (heartbeat) niye lazimdir?
**Cavab:** Connection-un hala aktiv oldugunu yoxlamaq ucun. NAT/firewall/proxy idle timeout-dan qorunmaq ucun. Dead connection-lari detect edib temizlemek ucun. Adeten her 30-60 saniye Ping gonderilir.

### 5. Laravel Broadcasting nece isleyir?
**Cavab:** Laravel event-leri WebSocket uzerinden broadcast edir. ShouldBroadcast interface implement edin, broadcastOn() ile channel secin, broadcast() ile gonderin. Channel novleri: public (her kes), private (auth lazim), presence (kim online gormek).

### 6. WebSocket-in dezavantajlari nelerdir?
**Cavab:** Stateful olmasi scaling-i cetinlesdirir, proxy/firewall problem yarada biler, reconnection logic lazimdir, server resource (memory/connections) serfi coxdur, debugging cetindir.

### 7. wss:// ve ws:// arasinda ferq nedir?
**Cavab:** `ws://` sifresiz WebSocket (HTTP kimi), `wss://` TLS ile sifreli WebSocket (HTTPS kimi). Production-da hemise `wss://` istifade edin. Port-lar: ws=80, wss=443.

### 8. Presence channel nedir?
**Cavab:** Kimlerin online oldugunu gormek ucun istifade olunan channel novudur. `here()` - hal-hazirda online olanlari gosterir, `joining()` - yeni qosulan, `leaving()` - ayrilani. Chat applications ucun idealdir.

## Best Practices

1. **Reconnection logic** - Client avtomatik reconnect etmelidir (exponential backoff ile)
2. **Heartbeat/Ping-Pong** - Her 30 saniye connection yoxlayin
3. **Message queuing** - Disconnect zamani mesajlari queue-da saxlayin
4. **Authentication** - Handshake zamani token verify edin
5. **Rate limiting** - Message flood-dan qorunun
6. **Compression** - Per-message deflate extension istifade edin
7. **Redis Pub/Sub** - Multi-server ucun Redis adapter
8. **Connection limits** - Max concurrent connection limiti teyin edin
9. **Graceful shutdown** - Server restart zamani clientlere xeber verin
10. **Message size limiti** - Boyuk payload-lari reject edin
