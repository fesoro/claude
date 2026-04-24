# Spring WebSocket/STOMP — Dərin Müqayisə

> **Seviyye:** Expert ⭐⭐⭐⭐

## Giriş

Real-time ünsiyyət — chat, bildiriş, dashboard, oyun — HTTP-in tək-istiqamətli modelindən kənar qalır. WebSocket RFC 6455 iki-istiqamətli, uzun-ömürlü bağlantı verir. Üstündə **STOMP** (Simple Text Oriented Messaging Protocol) sub-protokolu messaging semantikası əlavə edir: `SEND`, `SUBSCRIBE`, `MESSAGE`, `ACK`, `CONNECT`, `DISCONNECT` freymləri.

**Spring** iki səviyyəli dəstək verir:
- Low-level: `@EnableWebSocket` + `WebSocketHandler` — frame-lə işlə.
- High-level: `@EnableWebSocketMessageBroker` — STOMP, destination, broker relay, `@MessageMapping`.

**Laravel**-də STOMP yoxdur. Əvəzinə **Pusher protokol** (Reverb, Soketi, Ably, Pusher Cloud) — event broadcasting üzərindən. Client-də **Laravel Echo** istifadə olunur. Bu daha sadədir, amma STOMP qədər standart deyil.

Bu fayl 22-websocket.md-nin genişləndirilmiş variantıdır — STOMP-a, broker relay-ə, cluster-ə, presence və channel security-yə dərin baxır.

---

## Spring-də istifadəsi

### 1) `@EnableWebSocket` — low-level handler

```java
@Configuration
@EnableWebSocket
public class RawWebSocketConfig implements WebSocketConfigurer {

    @Override
    public void registerWebSocketHandlers(WebSocketHandlerRegistry registry) {
        registry.addHandler(new EchoHandler(), "/ws/echo")
                .setAllowedOrigins("https://app.example.com");
    }
}

public class EchoHandler extends TextWebSocketHandler {
    @Override
    public void handleTextMessage(WebSocketSession session, TextMessage msg) throws Exception {
        session.sendMessage(new TextMessage("Echo: " + msg.getPayload()));
    }
}
```

Bu səviyyə — sadə, amma messaging semantikası yoxdur. Chat üçün STOMP-a keçmək məsləhətdir.

### 2) `@EnableWebSocketMessageBroker` — STOMP konfiqurasiya

```java
@Configuration
@EnableWebSocketMessageBroker
public class StompConfig implements WebSocketMessageBrokerConfigurer {

    @Override
    public void registerStompEndpoints(StompEndpointRegistry registry) {
        registry.addEndpoint("/ws")
                .setAllowedOriginPatterns("https://*.example.com")
                .withSockJS();                    // SockJS fallback
    }

    @Override
    public void configureMessageBroker(MessageBrokerRegistry config) {
        config.enableSimpleBroker("/topic", "/queue")   // Simple in-memory broker
              .setHeartbeatValue(new long[] {10000, 10000})
              .setTaskScheduler(heartbeatScheduler());

        config.setApplicationDestinationPrefixes("/app"); // Controller-ə gedən
        config.setUserDestinationPrefix("/user");         // User-specific
    }

    @Bean
    public TaskScheduler heartbeatScheduler() {
        ThreadPoolTaskScheduler sch = new ThreadPoolTaskScheduler();
        sch.setPoolSize(1);
        sch.setThreadNamePrefix("ws-heartbeat-");
        return sch;
    }
}
```

### 3) Destination anatomiyası

- **`/app/...`** — application destinations. Client buraya `SEND` edir, Spring `@MessageMapping`-a ötürür.
- **`/topic/...`** — broadcast destinations. Bir-çox abunə alır (pub-sub).
- **`/queue/...`** — user-specific queue (point-to-point). Hər abunəçi ayrı alır.
- **`/user/...`** — user-specific prefix. `/user/queue/notifications` → yalnız həmin user-in session-ları alır.

### 4) `@MessageMapping` və `@SendTo`

```java
@Controller
public class ChatController {

    @MessageMapping("/chat.send")     // client SEND → /app/chat.send
    @SendTo("/topic/chat")             // nəticə → /topic/chat (hamı alır)
    public ChatMessage send(ChatMessage msg, Principal principal) {
        msg.setFrom(principal.getName());
        msg.setTimestamp(Instant.now());
        return msg;
    }

    @MessageMapping("/chat.private")
    @SendToUser("/queue/messages")     // göndərənin öz session-ına
    public ChatMessage echo(ChatMessage msg, Principal principal) {
        return msg;
    }

    @SubscribeMapping("/topic/chat")   // SUBSCRIBE hadisəsinə cavab
    public List<ChatMessage> getHistory() {
        return chatHistory.lastN(50);  // son 50 mesaj
    }
}
```

`@SendToUser("/queue/messages")` → daxildə Spring `/user/{username}/queue/messages`-ə publish edir. Client `/user/queue/messages`-a abunə olur, Spring user həllini özü edir.

### 5) `SimpMessagingTemplate` — programmatic send

```java
@Service
public class NotificationService {
    private final SimpMessagingTemplate messaging;

    public NotificationService(SimpMessagingTemplate messaging) {
        this.messaging = messaging;
    }

    public void broadcast(String event) {
        messaging.convertAndSend("/topic/announcements", event);
    }

    public void notifyUser(String username, Notification n) {
        messaging.convertAndSendToUser(username, "/queue/notifications", n);
    }

    public void sendWithHeaders(String username, Object payload) {
        SimpMessageHeaderAccessor h = SimpMessageHeaderAccessor.create(SimpMessageType.MESSAGE);
        h.setSessionId(null);
        h.setLeaveMutable(true);
        h.setHeader("priority", "high");
        messaging.convertAndSendToUser(username, "/queue/priority", payload, h.getMessageHeaders());
    }
}
```

### 6) External broker (RabbitMQ/ActiveMQ) — cluster

Simple in-memory broker tək JVM-də işləyir. Horizontal scale üçün xarici broker lazımdır:

```java
@Override
public void configureMessageBroker(MessageBrokerRegistry config) {
    config.enableStompBrokerRelay("/topic", "/queue")
          .setRelayHost("rabbitmq.internal")
          .setRelayPort(61613)                  // RabbitMQ STOMP port
          .setClientLogin("app")
          .setClientPasscode("secret")
          .setSystemLogin("system")
          .setSystemPasscode("secret")
          .setUserDestinationBroadcast("/topic/unresolved-user")
          .setUserRegistryBroadcast("/topic/user-registry");

    config.setApplicationDestinationPrefixes("/app");
    config.setUserDestinationPrefix("/user");
}
```

RabbitMQ-nun STOMP plugin-i aktiv olmalıdır:

```bash
rabbitmq-plugins enable rabbitmq_stomp
```

Artıq broker state RabbitMQ-dadır — Spring instansiyası stateless olur. User registry broadcast sayəsində hər node-un hansı user session-u saxladığını bilir.

### 7) WebSocket Security (Spring Security 6)

Köhnə `AbstractSecurityWebSocketMessageBrokerConfigurer` deprecated-dir. Yeni yol — `AuthorizationManager`:

```java
@Configuration
@EnableWebSocketSecurity
public class WsSecurityConfig {

    @Bean
    public AuthorizationManager<Message<?>> messageAuthorizationManager(
            MessageMatcherDelegatingAuthorizationManager.Builder messages) {
        messages
            .nullDestMatcher().authenticated()
            .simpSubscribeDestMatchers("/topic/admin/**").hasRole("ADMIN")
            .simpSubscribeDestMatchers("/user/queue/**").authenticated()
            .simpDestMatchers("/app/chat.send").authenticated()
            .simpDestMatchers("/app/admin/**").hasRole("ADMIN")
            .anyMessage().denyAll();
        return messages.build();
    }
}
```

HTTP handshake-də authentication (`HandshakeInterceptor` ilə JWT oxumaq):

```java
public class JwtHandshakeInterceptor implements HandshakeInterceptor {
    private final JwtDecoder decoder;

    @Override
    public boolean beforeHandshake(ServerHttpRequest req, ServerHttpResponse resp,
                                   WebSocketHandler wsHandler, Map<String, Object> attrs) {
        String auth = req.getHeaders().getFirst("Authorization");
        if (auth == null || !auth.startsWith("Bearer ")) return false;
        Jwt jwt = decoder.decode(auth.substring(7));
        attrs.put("user", jwt.getSubject());
        return true;
    }

    @Override
    public void afterHandshake(ServerHttpRequest req, ServerHttpResponse resp,
                               WebSocketHandler wsHandler, Exception ex) {}
}
```

### 8) `ChannelInterceptor` — inbound/outbound

```java
@Component
public class StompChannelInterceptor implements ChannelInterceptor {

    @Override
    public Message<?> preSend(Message<?> message, MessageChannel channel) {
        StompHeaderAccessor acc = MessageHeaderAccessor.getAccessor(message, StompHeaderAccessor.class);
        if (acc != null && StompCommand.CONNECT.equals(acc.getCommand())) {
            String token = acc.getFirstNativeHeader("Authorization");
            Authentication authentication = tokenAuthenticator.authenticate(token);
            acc.setUser(authentication);
        }
        return message;
    }
}

@Override
public void configureClientInboundChannel(ChannelRegistration registration) {
    registration.interceptors(stompChannelInterceptor);
    registration.taskExecutor().corePoolSize(8).maxPoolSize(32);
}
```

### 9) Session events

```java
@Component
public class PresenceTracker {
    private final Set<String> online = ConcurrentHashMap.newKeySet();
    private final SimpMessagingTemplate tmpl;

    @EventListener
    public void onConnect(SessionConnectedEvent e) {
        String user = e.getUser() != null ? e.getUser().getName() : null;
        if (user != null) {
            online.add(user);
            tmpl.convertAndSend("/topic/presence", Map.of("user", user, "state", "online"));
        }
    }

    @EventListener
    public void onDisconnect(SessionDisconnectEvent e) {
        String user = e.getUser() != null ? e.getUser().getName() : null;
        if (user != null) {
            online.remove(user);
            tmpl.convertAndSend("/topic/presence", Map.of("user", user, "state", "offline"));
        }
    }

    @EventListener
    public void onSubscribe(SessionSubscribeEvent e) {
        // log subscription for audit
    }
}
```

### 10) Client — stomp.js + SockJS

```javascript
import { Client } from '@stomp/stompjs';
import SockJS from 'sockjs-client';

const client = new Client({
    webSocketFactory: () => new SockJS('/ws'),
    connectHeaders: { Authorization: 'Bearer ' + token },
    reconnectDelay: 5000,
    heartbeatIncoming: 10000,
    heartbeatOutgoing: 10000,
});

client.onConnect = () => {
    client.subscribe('/topic/chat', msg => {
        const chat = JSON.parse(msg.body);
        render(chat);
    });
    client.subscribe('/user/queue/notifications', msg => {
        showToast(JSON.parse(msg.body));
    });
    client.publish({ destination: '/app/chat.send', body: JSON.stringify({ text: 'Hi' }) });
};

client.activate();
```

### 11) Full chat example

```java
public record ChatMessage(String from, String text, Instant timestamp, String type) {}

@Controller
public class ChatController {

    private final SimpMessagingTemplate tmpl;
    private final PresenceTracker presence;
    private final ChatHistoryRepo history;

    @MessageMapping("/chat.send")
    public void send(ChatMessage msg, Principal principal) {
        ChatMessage saved = history.save(new ChatMessage(
            principal.getName(), msg.text(), Instant.now(), "CHAT"));
        tmpl.convertAndSend("/topic/chat", saved);
    }

    @MessageMapping("/chat.typing")
    public void typing(Principal principal) {
        tmpl.convertAndSend("/topic/typing",
            Map.of("user", principal.getName(), "at", Instant.now()));
    }

    @SubscribeMapping("/topic/chat.history")
    public List<ChatMessage> history() {
        return history.last(50);
    }

    @SubscribeMapping("/topic/presence.list")
    public Set<String> presence() {
        return presence.online();
    }
}
```

---

## Laravel-də istifadəsi

### 1) Laravel Reverb — rəsmi WebSocket server (Laravel 11+)

Reverb Laravel 11-də rəsmi gəldi. Pusher protokolu ilə uyğundur (Laravel Echo işləyir).

```bash
composer require laravel/reverb
php artisan reverb:install
php artisan reverb:start                # WebSocket server işə sal
```

`.env`:

```env
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=local
REVERB_APP_KEY=local-key
REVERB_APP_SECRET=local-secret
REVERB_HOST="0.0.0.0"
REVERB_PORT=8080
REVERB_SCHEME=http
```

### 2) Broadcasting event

```php
// app/Events/MessageSent.php
namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast
{
    use SerializesModels, InteractsWithSockets;

    public function __construct(public Message $message) {}

    public function broadcastOn(): array
    {
        return [new PresenceChannel('room.' . $this->message->room_id)];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    public function broadcastWith(): array
    {
        return [
            'id'        => $this->message->id,
            'text'      => $this->message->text,
            'user'      => $this->message->user->only(['id', 'name', 'avatar']),
            'timestamp' => $this->message->created_at->toIso8601String(),
        ];
    }
}
```

Kontroller:

```php
Route::post('/rooms/{room}/messages', function (Room $room, Request $req) {
    $msg = $room->messages()->create([
        'user_id' => auth()->id(),
        'text'    => $req->validate(['text' => 'required|string|max:500'])['text'],
    ]);
    broadcast(new MessageSent($msg))->toOthers();
    return $msg;
});
```

`->toOthers()` — göndərənin öz tabına getmir (client optimistic UI üçün).

### 3) Channel authorization — `routes/channels.php`

```php
use App\Models\Room;

Broadcast::channel('room.{roomId}', function ($user, $roomId) {
    $room = Room::find($roomId);
    if (! $room || ! $room->members->contains($user->id)) {
        return false;
    }
    return [
        'id'     => $user->id,
        'name'   => $user->name,
        'avatar' => $user->avatar_url,
    ];
});

Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});
```

Presence channel üçün closure array qaytarsa, həmin metadata bütün abunəçilərə göndərilir.

### 4) Client — Laravel Echo

```javascript
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT,
    forceTLS: false,
    enabledTransports: ['ws', 'wss'],
    authEndpoint: '/broadcasting/auth',
});

// Presence channel subscribe
Echo.join(`room.${roomId}`)
    .here(users => setOnlineUsers(users))
    .joining(user => addUser(user))
    .leaving(user => removeUser(user))
    .listen('.message.sent', e => addMessage(e))
    .listenForWhisper('typing', e => showTyping(e.user));

// Typing indicator — whisper (server-ə getmir, peer-to-peer)
input.addEventListener('keyup', () => {
    Echo.join(`room.${roomId}`).whisper('typing', { user: currentUser.name });
});
```

### 5) Private channels — user notifications

```php
// server
Broadcast::channel('user.{id}', fn ($user, $id) => $user->id === (int) $id);

// event
class InvoicePaid implements ShouldBroadcast {
    use SerializesModels;
    public function __construct(public Invoice $invoice) {}
    public function broadcastOn(): array {
        return [new PrivateChannel('user.' . $this->invoice->user_id)];
    }
}
```

```javascript
Echo.private(`user.${currentUserId}`)
    .listen('InvoicePaid', e => showToast(`Invoice #${e.invoice.id} paid`));
```

### 6) Alternativlər — Soketi, Pusher Cloud, Ably

```env
# Soketi (self-hosted, Pusher API-uyğun)
BROADCAST_CONNECTION=pusher
PUSHER_HOST=soketi.internal
PUSHER_PORT=6001

# Pusher Cloud
BROADCAST_CONNECTION=pusher
PUSHER_APP_KEY=xxx
PUSHER_APP_SECRET=xxx
PUSHER_APP_ID=xxx
PUSHER_APP_CLUSTER=eu
```

Ably — `ably/laravel-broadcaster` paketi.

### 7) Horizontal scale

Reverb single-process-dir. Cluster üçün Reverb-in scaling mode-u (Redis-lə pub/sub) lazımdır:

```env
REVERB_SCALING_ENABLED=true
REVERB_SCALING_CHANNEL=reverb
```

Redis pub/sub üzərindən çoxlu Reverb instansiyası mesajları sinxronlaşdırır.

### 8) Full chat + typing + presence

```php
// app/Events/UserTyping.php
class UserTyping implements ShouldBroadcast {
    public function __construct(public int $roomId, public int $userId, public string $name) {}
    public function broadcastOn(): array { return [new PresenceChannel('room.' . $this->roomId)]; }
    public function broadcastAs(): string { return 'user.typing'; }
    public function broadcastWith(): array { return ['userId' => $this->userId, 'name' => $this->name]; }
}

Route::post('/rooms/{room}/typing', function (Room $room) {
    broadcast(new UserTyping($room->id, auth()->id(), auth()->user()->name))->toOthers();
    return response()->noContent();
});

Route::get('/rooms/{room}/messages', function (Room $room) {
    return $room->messages()->with('user')->latest()->limit(50)->get();
});
```

Whisper istifadə etsən, server tamamilə kənarda qalır — typing eventləri yalnız client-to-client:

```javascript
let typingTimer;
Echo.join(`room.${roomId}`)
    .listenForWhisper('typing', e => {
        showTyping(e.user);
        clearTimeout(typingTimer);
        typingTimer = setTimeout(() => hideTyping(e.user), 2000);
    });
```

---

## Əsas fərqlər

| Xüsusiyyət | Spring (STOMP) | Laravel (Reverb/Pusher) |
|---|---|---|
| Sub-protokol | STOMP (IETF standart) | Pusher protocol (proprietary) |
| Server | Spring + embedded Tomcat/Netty | Reverb (standalone PHP server) |
| Broker | In-memory və ya RabbitMQ/ActiveMQ | Redis (scaling) |
| Destinations | `/topic`, `/queue`, `/user`, `/app` | public/private/presence channels |
| Client library | `stomp.js` + SockJS | Laravel Echo (Pusher-uyğun) |
| Authorization | `AuthorizationManager` (Security 6) | `routes/channels.php` closure |
| Presence | Session events + custom | PresenceChannel built-in |
| Typing indicator | Custom event/controller | `.whisper()` (client-only) |
| SockJS fallback | Var — `.withSockJS()` | Yoxdur — yalnız WebSocket |
| Heartbeat | STOMP frame | Pusher ping/pong |
| Scaling | STOMP broker relay (RabbitMQ) | Redis scaling mode |
| Mesaj publish (server-side) | `SimpMessagingTemplate` | `broadcast(new Event)` |
| Security inteqrasiya | Spring Security (session-əsaslı) | Sanctum/JWT + auth closure |
| Interceptor | `ChannelInterceptor` | Middleware yoxdur, sadəcə channel auth |
| Binary mesaj | Dəstəklənir | Yalnız JSON |

---

## Niyə belə fərqlər var?

**Spring-in enterprise mühiti.** STOMP — IETF-dən qeyri-rəsmi amma geniş standartdır. Java Spring AMQP/JMS ilə inteqrasiya asandır — RabbitMQ-a STOMP ilə qoşul, artıq orada hər broker üstünlüyü (durable queue, DLQ, ack) var. Spring buna görə STOMP seçib.

**Laravel-in frontend-first yanaşması.** Laravel Echo Pusher protokolu ilə başladı — çünki Pusher popular idi və client SDK-sı hazır idi. Reverb də eyni protokolu saxlayır ki, mövcud Echo və Pusher client-ləri dəyişmədən işləsin. STOMP-a keçmək Laravel community-ni qıracaq.

**Stateful server problemi.** PHP request-per-process modelində WebSocket saxlamaq olmur — hər sorğu bitir. Bu Laravel üçün ayrıca server (Reverb, Soketi) tələb edir. Spring-də JVM uzun-ömürlüdür — WebSocket session-ları birbaşa proses daxilində saxlanır.

**Broker relay vs Redis pub/sub.** Spring-də çoxlu node-da WebSocket olanda RabbitMQ broker relay ilə hər node broker-dan mesaj alır. Laravel-də Redis pub/sub ilə Reverb instansiyaları bir-birinə xəbər verir. Nəzəri cəhətdən eyni — amma STOMP broker həm də durable queue imkanı verir (bağlantı qırılsa, mesaj saxlanır). Reverb-də yoxdur — client yenidən qoşulduqda keçmiş mesajları app-dan istəməlidir.

**Whisper vs server round-trip.** Laravel Echo-da `whisper()` — peer-to-peer via Pusher. Server heç bilmir. Typing indicator üçün mükəmməldir (qeyd etməyə dəyməz). Spring-də hər mesaj server-dən keçir — bu "always-through-server" daha predictable, amma typing üçün artıq yükdür.

**Channel authorization strukturu.** Spring Security WebSocket security-ni normal HTTP security kimi idarə edir (`AuthorizationManager`, `hasRole()` və s.) — hər şey bir yerdədir. Laravel `routes/channels.php`-də ayrı closure-lar ilə — hər channel üçün ayrıca authorization. Laravel-in yanaşması daha sadə, amma bir yerdə bütün kuralları görmək olmur.

**SockJS.** WebSocket-siz köhnə proxy və firewall-larda işləməyə imkan verir (long-polling fallback). Spring tarixi səbəbdən dəstəkləyir — enterprise şəbəkələrdə proxy problemi tez-tez olur. Pusher protokolu artıq SockJS-siz işləyir (client modern brauzerdir) — Reverb də fallback təklif etmir.

---

## Hansı framework-də var, hansında yoxdur?

**Yalnız Spring-də:**
- STOMP protokol birinci-dərəcəli dəstək
- `@MessageMapping`, `@SendTo`, `@SendToUser`, `@SubscribeMapping` annotation-ları
- `SimpMessagingTemplate` — istənilən yerdən publish
- RabbitMQ/ActiveMQ STOMP broker relay
- SockJS fallback (polling)
- `ChannelInterceptor` — inbound/outbound filtr
- Subscribe mapping — client abunə olduqda server-ə hook
- Per-destination authorization `simpDestMatchers("/topic/admin/**")`
- Session events (`SessionConnectedEvent`, `SessionDisconnectEvent`)
- Binary WebSocket mesajlar

**Yalnız Laravel-də:**
- PresenceChannel — user list + join/leave notifications out-of-box
- `.whisper()` və `listenForWhisper()` — peer-to-peer client-to-client
- `ShouldBroadcast` interface — event-i broadcast etmək üçün
- `broadcastWith()` — mesaj payload-ını transformasiya
- `broadcastAs()` — custom event name
- `->toOthers()` — göndərəni istisna etmək
- Pusher Cloud ilə birbaşa inteqrasiya
- Reverb scaling mode (Redis pub/sub)
- Laravel Echo client SDK (abstraksiya Pusher üzərində)

**Hər ikisində var:**
- Private channel
- Channel authorization
- Server-dən client-ə push
- Reconnection
- User-specific messaging
- Horizontal scale (broker ilə)

---

## Best Practices

**Spring-də xarici broker istifadə et (production).** Simple in-memory broker yalnız dev üçündür. Production-da RabbitMQ STOMP relay seç — durable queue, DLQ, cluster.

**Sticky sessions — in-memory broker olsa.** LoadBalancer cookies-based affinity etməlidir ki, client eyni node-a qayıtsın. Xarici broker-lə sticky lazım deyil.

**Authorization WebSocket handshake-də tam yerinə yetir.** JWT yoxlamağı HandshakeInterceptor-da et, sonra STOMP message səviyyəsində yenə `AuthorizationManager`-la `denyAll()` default qoy.

**Spring-də `convertAndSendToUser` çağır.** User-specific mesaj üçün bu metodu istifadə et — Spring user-in session-larını tapıb göndərir.

**Laravel-də presence channel-də yalnız ictimai mə'lumat qaytar.** `routes/channels.php` closure dönən array bütün abunəçilərə görünür. Email, telefon kimi gizli sahələri çıxart.

**Whisper ilə server yükünü azalt.** Typing, cursor position kimi tez-tez tez-tez gələn event-lər üçün `.whisper()` istifadə et. Chat mesajı üçün isə həmişə server-dən keç.

**Reverb-i standalone proses kimi supervisor altında işlət.** `php artisan reverb:start` uzun-ömürlü prosesdir — systemd/supervisor ilə idarə et, memory limit qoy.

**Heartbeat aktiv et.** STOMP-da `setHeartbeatValue([10000, 10000])` — load balancer timeout-undan aşağı saxla (AWS ALB default 60s, heartbeat 30s olsun).

**Reverb-də TLS istifadə et (production).** `REVERB_SCHEME=https` + proxy (nginx/traefik) WSS üçün. Pusher key public-dir, amma broadcast auth olmasa channel əlçatmaz qalır.

**Chat history RDBMS-də, live delivery WebSocket-də.** WebSocket yalnız "push delivery"-dir. Keçmişi DB-dən oxu, yeni mesajı broadcast et. Reconnection zamanı client keçmişi yenidən fetch edir.

**Spring-də `SimpMessagingTemplate` @Scheduled-də istifadə etmə.** Uzun saxlanan event üçün `@EventListener` + domain event-lərdən istifadə et. Məsələn `OrderPlacedEvent` → listener `messaging.convertAndSendToUser` çağırır.

**Laravel-də Broadcasting üçün queue driver istifadə et.** `ShouldBroadcast` avtomatik queue-ya gedir — HTTP sorğu gözləmir. `BROADCAST_CONNECTION` və `QUEUE_CONNECTION` düzgün qur.

**WebSocket vs SSE vs polling.** Yalnız server→client lazımdırsa — SSE (HTTP/1.1, cache-friendly, reconnection daxili). İki-istiqamətli — WebSocket. Saniyədə bir-iki dəfə — polling də kifayətdir.

---

## Yekun

**Spring WebSocket + STOMP** — enterprise mühiti üçün güclü: IETF standart, RabbitMQ/ActiveMQ ilə birbaşa inteqrasiya, Spring Security-ə qoşulma, SockJS fallback, per-destination authorization. Cluster üçün broker relay istifadə et — stateless node-lar alırsan. `@MessageMapping`, `@SendTo`, `SimpMessagingTemplate` ilə kod təmiz və test olunandır.

**Laravel Reverb/Echo** — frontend-first, Pusher protokolunda. Laravel Echo SDK-sı brauzerdə istifadəsi çox asan, presence channel out-of-box, whisper ilə peer-to-peer event. Reverb Laravel 11-də rəsmi oldu — `php artisan reverb:start` ilə qaldırırsan. Scaling üçün Redis mode. STOMP-un hər gücü yoxdur (durable broker queue yoxdur), amma chat və bildiriş üçün kifayətdir.

Seçim kontekstə bağlıdır: Java-Spring ekosistemin varsa və RabbitMQ artıq istifadə edirsənsə — STOMP təbii seçimdir. Laravel ilə başlayırsan, frontend React/Vue ilə — Reverb + Echo ən sürətli yoldur. Chat tətbiqi hər iki framework-də bir neçə gündə yazılır; əsl fərq scaling və broker-də üzə çıxır.
