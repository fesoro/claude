# WebSocket ve Real-time Kommunikasiya

> **Seviyye:** Intermediate ⭐⭐

## Giris

Real-time (canli) melumat mubadilesie - chat tetbiqleri, canli bildirisler, canli oyunlar, maliyye melumatlarinin axini ve s. ucun vacibdir. HTTP-nin "sorgu-cavab" modeli bunun ucun uygun deyil, cunki server ozue mesaj gondermek isteyende bunu ede bilmir. WebSocket protokolu bu meseleyo hell edir - server ve klient arasinda daimi, iki terefli elaqe qanalini acir. Spring STOMP ve SockJS istifade edir, Laravel ise Broadcasting sistemi, Pusher/Reverb ve Laravel Echo ile isleyir.

## Spring-de istifadesi

### WebSocket konfiqurasiyasi

```java
@Configuration
@EnableWebSocketMessageBroker
public class WebSocketConfig implements WebSocketMessageBrokerConfigurer {

    @Override
    public void configureMessageBroker(MessageBrokerRegistry config) {
        // Server-den kliente mesaj gondermek ucun prefix-ler
        config.enableSimpleBroker("/topic", "/queue");

        // Klient-den servere mesaj gondermek ucun prefix
        config.setApplicationDestinationPrefixes("/app");

        // Xususi istifadeciye mesaj ucun prefix
        config.setUserDestinationPrefix("/user");
    }

    @Override
    public void registerStompEndpoints(StompEndpointRegistry registry) {
        // WebSocket baglanti noqtesi
        registry.addEndpoint("/ws")
                .setAllowedOrigins("http://localhost:3000")
                .withSockJS(); // SockJS fallback - WebSocket desteklemeyen brauzerler ucun
    }
}
```

### @MessageMapping ile mesaj islemek

```java
@Controller
public class ChatController {

    private final SimpMessagingTemplate messagingTemplate;

    public ChatController(SimpMessagingTemplate messagingTemplate) {
        this.messagingTemplate = messagingTemplate;
    }

    // Klient "/app/chat.send" adresine mesaj gonderir
    // Cavab "/topic/chat" kanalina yonlendirilir (butun abonelere)
    @MessageMapping("/chat.send")
    @SendTo("/topic/chat")
    public ChatMessage sendMessage(ChatMessage message) {
        message.setTimestamp(LocalDateTime.now());
        return message;
    }

    // Istifadecinin qosulmasi
    @MessageMapping("/chat.join")
    @SendTo("/topic/chat")
    public ChatMessage join(ChatMessage message,
                            SimpMessageHeaderAccessor headerAccessor) {
        // Session-a istifadeci adini elave edirik
        headerAccessor.getSessionAttributes()
            .put("username", message.getSender());

        message.setType(MessageType.JOIN);
        message.setContent(message.getSender() + " qosuldu");
        return message;
    }

    // Xususi istifadeciye mesaj gonderme
    @MessageMapping("/chat.private")
    public void sendPrivateMessage(PrivateMessage message) {
        messagingTemplate.convertAndSendToUser(
            message.getRecipient(),
            "/queue/private",
            message
        );
    }
}
```

### Model sinifleri

```java
public class ChatMessage {
    private String sender;
    private String content;
    private MessageType type;
    private LocalDateTime timestamp;

    // Getters, setters, constructor
}

public enum MessageType {
    CHAT, JOIN, LEAVE
}

public class PrivateMessage {
    private String sender;
    private String recipient;
    private String content;
    // ...
}
```

### Hadise dinleyicileri

```java
@Component
public class WebSocketEventListener {

    private static final Logger log = LoggerFactory.getLogger(WebSocketEventListener.class);
    private final SimpMessagingTemplate messagingTemplate;

    public WebSocketEventListener(SimpMessagingTemplate messagingTemplate) {
        this.messagingTemplate = messagingTemplate;
    }

    @EventListener
    public void handleWebSocketConnectListener(SessionConnectedEvent event) {
        log.info("Yeni WebSocket baglantisi");
    }

    @EventListener
    public void handleWebSocketDisconnectListener(SessionDisconnectEvent event) {
        StompHeaderAccessor headerAccessor =
            StompHeaderAccessor.wrap(event.getMessage());

        String username = (String) headerAccessor.getSessionAttributes()
            .get("username");

        if (username != null) {
            ChatMessage message = new ChatMessage();
            message.setType(MessageType.LEAVE);
            message.setSender(username);
            message.setContent(username + " ayrildi");

            messagingTemplate.convertAndSend("/topic/chat", message);
        }
    }
}
```

### Serverdisn mesaj gonderme (Backend-den push)

```java
@Service
public class NotificationService {

    private final SimpMessagingTemplate messagingTemplate;

    public NotificationService(SimpMessagingTemplate messagingTemplate) {
        this.messagingTemplate = messagingTemplate;
    }

    // Butun abonelere
    public void broadcastNotification(String message) {
        messagingTemplate.convertAndSend("/topic/notifications",
            Map.of("message", message, "timestamp", Instant.now()));
    }

    // Xususi istifadeciye
    public void notifyUser(String userId, Notification notification) {
        messagingTemplate.convertAndSendToUser(
            userId, "/queue/notifications", notification);
    }

    // Sifaris statusu deyisende
    public void orderStatusChanged(Order order) {
        messagingTemplate.convertAndSendToUser(
            order.getUserId().toString(),
            "/queue/orders",
            Map.of(
                "orderId", order.getId(),
                "status", order.getStatus(),
                "message", "Sifarisizin statusu deyisdi: " + order.getStatus()
            )
        );
    }
}
```

### JavaScript klient (SockJS + STOMP)

```javascript
const socket = new SockJS('/ws');
const stompClient = Stomp.over(socket);

stompClient.connect({}, function (frame) {
    console.log('Baglandi: ' + frame);

    // Umumie kanala abune
    stompClient.subscribe('/topic/chat', function (message) {
        const chatMessage = JSON.parse(message.body);
        displayMessage(chatMessage);
    });

    // Xususi mesajlara abune
    stompClient.subscribe('/user/queue/private', function (message) {
        const privateMsg = JSON.parse(message.body);
        displayPrivateMessage(privateMsg);
    });

    // Qosulma mesaji gonder
    stompClient.send('/app/chat.join', {},
        JSON.stringify({ sender: 'Orxan', type: 'JOIN' }));
});

// Mesaj gonderme
function sendMessage(content) {
    stompClient.send('/app/chat.send', {},
        JSON.stringify({ sender: 'Orxan', content: content, type: 'CHAT' }));
}
```

## Laravel-de istifadesi

### Broadcasting konfiqurasiyasi

```php
// config/broadcasting.php
return [
    'default' => env('BROADCAST_CONNECTION', 'reverb'),

    'connections' => [
        'reverb' => [
            'driver' => 'reverb',
            'key' => env('REVERB_APP_KEY'),
            'secret' => env('REVERB_APP_SECRET'),
            'app_id' => env('REVERB_APP_ID'),
            'options' => [
                'host' => env('REVERB_HOST'),
                'port' => env('REVERB_PORT', 443),
                'scheme' => env('REVERB_SCHEME', 'https'),
            ],
        ],

        'pusher' => [
            'driver' => 'pusher',
            'key' => env('PUSHER_APP_KEY'),
            'secret' => env('PUSHER_APP_SECRET'),
            'app_id' => env('PUSHER_APP_ID'),
            'options' => [
                'cluster' => env('PUSHER_APP_CLUSTER'),
                'useTLS' => true,
            ],
        ],
    ],
];
```

### Event yaratmaq

```bash
php artisan make:event MessageSent
```

```php
// app/Events/MessageSent.php
class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public User $user,
        public Message $message
    ) {}

    // Hansi kanala yayimlamaq
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('chat.' . $this->message->conversation_id),
        ];
    }

    // Yayimlanan melumatin formati
    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ],
            'content' => $this->message->content,
            'sent_at' => $this->message->created_at->toISOString(),
        ];
    }

    // Hadisenin adi (opsional)
    public function broadcastAs(): string
    {
        return 'message.sent';
    }
}
```

### Kanal avtorizasiyasi

```php
// routes/channels.php

// Private kanal - yalniz sohbetin istirakcilarini buraxir
Broadcast::channel('chat.{conversationId}', function (User $user, int $conversationId) {
    return $user->conversations()->where('id', $conversationId)->exists();
});

// Presence kanal - kim online oldugunu bildirir
Broadcast::channel('room.{roomId}', function (User $user, int $roomId) {
    if ($user->rooms()->where('id', $roomId)->exists()) {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'avatar' => $user->avatar_url,
        ];
    }
});

// Ictimai kanal - avtorizasiya lazim deyil
// broadcastOn()-da new Channel('public-updates') istifade edin
```

### Controller-den event yayimlamaq

```php
class ChatController extends Controller
{
    public function sendMessage(Request $request, Conversation $conversation)
    {
        $validated = $request->validate([
            'content' => 'required|string|max:1000',
        ]);

        $message = $conversation->messages()->create([
            'user_id' => auth()->id(),
            'content' => $validated['content'],
        ]);

        // Event yayimlamaq - butun kanal abonelerine catidir
        broadcast(new MessageSent(auth()->user(), $message))->toOthers();

        return response()->json($message);
    }
}
```

### Bildiris sistemi ile inteqrasiya

```php
// app/Events/OrderStatusChanged.php
class OrderStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Order $order
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('orders.' . $this->order->user_id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->order->id,
            'status' => $this->order->status,
            'message' => "Sifarisizin statusu: {$this->order->status}",
        ];
    }
}

// Service-de istifade
class OrderService
{
    public function updateStatus(Order $order, string $status): void
    {
        $order->update(['status' => $status]);

        event(new OrderStatusChanged($order));
    }
}
```

### Laravel Echo (JavaScript klient)

```javascript
// resources/js/bootstrap.js
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT,
    wssPort: import.meta.env.VITE_REVERB_PORT,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
});
```

```javascript
// Ictimai kanala abune
Echo.channel('public-updates')
    .listen('NewsPublished', (e) => {
        console.log('Yeni xeber:', e.title);
    });

// Private kanala abune
Echo.private('chat.1')
    .listen('.message.sent', (e) => {
        console.log(`${e.user.name}: ${e.content}`);
    });

// Presence kanal - kim onlinedir?
Echo.join('room.1')
    .here((users) => {
        console.log('Online istifadeciler:', users);
    })
    .joining((user) => {
        console.log(`${user.name} qosuldu`);
    })
    .leaving((user) => {
        console.log(`${user.name} ayrildi`);
    })
    .listen('.message.sent', (e) => {
        console.log('Mesaj:', e.content);
    });
```

### Laravel Reverb

Reverb Laravel-in ozune mexsus WebSocket serveridir:

```bash
# Qurulum
php artisan install:broadcasting

# Reverb serverini baslatmaq
php artisan reverb:start

# Production ucun
php artisan reverb:start --host=0.0.0.0 --port=8080
```

```env
# .env
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=my-app
REVERB_APP_KEY=my-key
REVERB_APP_SECRET=my-secret
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http
```

## Esas ferqler

| Xususiyyet | Spring | Laravel |
|---|---|---|
| **Protokol** | STOMP over WebSocket | WebSocket (Reverb), Pusher protokolu |
| **Server** | Tetbiq daxilinde (embedded) | Ayri server (Reverb) ve ya ucuncu teref (Pusher) |
| **Fallback** | SockJS (long-polling) | Laravel Echo avtomatik |
| **Kanal novleri** | topic, queue, user | public, private, presence |
| **Mesaj routing** | `@MessageMapping` | Event + `broadcastOn()` |
| **Klient** | STOMP.js + SockJS | Laravel Echo |
| **Avtorizasiya** | Spring Security ile | `routes/channels.php` |
| **Presence** | Manual implementasiya | Built-in presence channels |
| **Queue inteqrasiyasi** | Manual | `ShouldBroadcastNow` / `ShouldBroadcast` |

## Niye bele ferqler var?

**Spring-in yanasmasi:** Spring daimi isleyen JVM prosesidir, buna gore WebSocket baglantilarini birbaşa ozue idarae ede bilir. STOMP protokolu mesaj yonlendirmesi ucun standart bir protokoldur ve Spring bunu tam destekleyir. `@MessageMapping` REST controller-lere benzer formada isleyir. Bu yanasma tam nezaret verir, amma daha cox konfiqurasiya teleb edir.

**Laravel-in yanasmasi:** PHP sorgu-cavab modeli ile isleyir ve daimi baglantilari ozue idarae ede bilmir. Buna gore Laravel xarici WebSocket serverine etibar edir - evvelce Pusher, indi ise Laravel-in oz Reverb serveri. Broadcasting sistemi Event-Driven Architecture uezerine quruludur: hadise bas verir -> event yayimlanir -> klient dinleyir. Bu ayirma (decoupling) tez ve temiz kod yazmaga imkan verir.

**Presence channels:** Laravel-in en guclu xususiyyetlerinden biri presence kanallaridir - hansi istifadecilerin online oldugunu izlemek ucun built-in hell. Spring-de bu funksionalligi sifirdan qurmaq lazimdir.

## Hansi framework-de var, hansinda yoxdur?

- **Presence channels** - Laravel-de built-in. Spring-de manual implementasiya lazimdir (online istifadecilerin siyahisi, qosulma/ayrilma hadiseleri).
- **`toOthers()`** - Laravel-de mesaji gondericiye geri gondermeemek ucun bir metod kifayetdir. Spring-de bu metiqi manual yazmaq lazimdir.
- **STOMP protokolu** - Yalniz Spring-de. Laravel WebSocket uezerine oz protokolunu istifade edir.
- **SockJS fallback** - Yalniz Spring-de. WebSocket desteklemeyen brauzerler ucun avtomatik long-polling-e kecid.
- **`@SendTo`** - Spring-de mesajin hansi kanala yonlendirileceyini annotasiya ile bildirmek mumkundur.
- **Reverb** - Yalniz Laravel-de. Oz WebSocket serveri, evvelce Pusher kimi ucuncu teref xidmetlerine ehtiyac var idi.
- **`broadcastAs()` / `broadcastWith()`** - Laravel-de hadisenin adi ve formatini deyismek asandir.
- **`SimpMessagingTemplate`** - Spring-de istenilyn yerden (service, scheduled task) mesaj gondermek mumkundur.
