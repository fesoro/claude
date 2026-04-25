# 80 — Spring WebSocket — Geniş İzah

> **Seviyye:** Advanced ⭐⭐⭐


## Mündəricat
1. [WebSocket nədir?](#websocket-nədir)
2. [STOMP protokolu](#stomp-protokolu)
3. [WebSocket konfiqurasiyası](#websocket-konfiqurasiyası)
4. [Mesaj göndərmə](#mesaj-göndərmə)
5. [Authentication ilə WebSocket](#authentication-ilə-websocket)
6. [İntervyu Sualları](#intervyu-sualları)

---

## WebSocket nədir?

**WebSocket** — client ilə server arasında tam duplex, real-time kommunikasiya protokolu. HTTP-dən fərqli olaraq connection açıq qalır — hər iki tərəf istənilən vaxt mesaj göndərə bilər.

```
HTTP:  Client → Request → Server → Response (bağlantı bağlanır)

WebSocket:
  Client → Upgrade Request → Server (handshake)
  Client ←→ Server (bağlantı açıq qalır, iki tərəfli axın)

İstifadə halları:
  - Chat tətbiqi
  - Real-time notification
  - Live dashboard (stock prices, sports scores)
  - Multiplayer oyun
  - Collaborative editing
```

---

## STOMP protokolu

**STOMP (Simple Text Oriented Messaging Protocol)** — WebSocket üzərindəki mesajlaşma protokolu. Spring WebSocket STOMP ilə birlikdə message broker (SockJS fallback) imkanı verir.

```
SUBSCRIBE /topic/notifications  → Abunəlik
SEND /app/chat.message          → Server-ə mesaj göndər
MESSAGE /topic/public            → Server-dən mesaj al
```

---

## WebSocket konfiqurasiyası

```xml
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-websocket</artifactId>
</dependency>
```

```java
@Configuration
@EnableWebSocketMessageBroker
public class WebSocketConfig implements WebSocketMessageBrokerConfigurer {

    @Override
    public void configureMessageBroker(MessageBrokerRegistry config) {
        // Kliyentə göndərilən mesajlar prefix-i
        // /topic — broadcast (1-to-many)
        // /queue  — user-specific (1-to-1)
        config.enableSimpleBroker("/topic", "/queue");

        // Server-ə göndərilən mesajlar prefix-i
        config.setApplicationDestinationPrefixes("/app");

        // User-specific mesajlar üçün prefix
        config.setUserDestinationPrefix("/user");
    }

    @Override
    public void registerStompEndpoints(StompEndpointRegistry registry) {
        registry.addEndpoint("/ws")
            .setAllowedOriginPatterns("https://*.example.com",
                                      "http://localhost:[*]")
            .withSockJS(); // SockJS fallback — WebSocket dəstəkləməyənlər üçün
    }

    // Heartbeat, message size limit
    @Override
    public void configureWebSocketTransport(WebSocketTransportRegistration registration) {
        registration.setMessageSizeLimit(128 * 1024); // 128KB
        registration.setSendBufferSizeLimit(512 * 1024); // 512KB
        registration.setSendTimeLimit(15 * 1000); // 15 saniyə
    }
}
```

```java
// Message Controller
@Controller
public class ChatController {

    // Client → /app/chat.message → bu metod → /topic/public
    @MessageMapping("/chat.message")
    @SendTo("/topic/public") // Cavabı bu topic-ə göndər
    public ChatMessage sendMessage(ChatMessage message,
                                    Principal principal) {
        message.setSender(principal.getName());
        message.setTimestamp(LocalDateTime.now());
        return message;
    }

    // Client qoşulduqda
    @MessageMapping("/chat.join")
    @SendTo("/topic/public")
    public ChatMessage addUser(ChatMessage message, Principal principal) {
        message.setType(MessageType.JOIN);
        message.setSender(principal.getName());
        return message;
    }
}

// DTO
public class ChatMessage {
    public enum MessageType { CHAT, JOIN, LEAVE }

    private MessageType type;
    private String content;
    private String sender;
    private LocalDateTime timestamp;
}
```

---

## Mesaj göndərmə

```java
@Service
public class NotificationService {

    private final SimpMessagingTemplate messagingTemplate;

    // Broadcast — bütün subscriber-lara
    public void broadcastNotification(String message) {
        messagingTemplate.convertAndSend("/topic/notifications", message);
    }

    // User-specific — yalnız bir istifadəçiyə
    public void sendToUser(String username, Notification notification) {
        messagingTemplate.convertAndSendToUser(
            username,
            "/queue/notifications",  // /user/{username}/queue/notifications
            notification
        );
    }

    // Header ilə
    public void sendWithHeaders(String username, Object payload) {
        SimpMessageHeaderAccessor headerAccessor =
            SimpMessageHeaderAccessor.create(SimpMessageType.MESSAGE);
        headerAccessor.setSessionId("session-id");
        headerAccessor.setLeaveMutable(true);

        messagingTemplate.convertAndSendToUser(
            username,
            "/queue/updates",
            payload,
            headerAccessor.getMessageHeaders()
        );
    }
}

// Event-driven notification
@Component
public class OrderEventListener {

    private final SimpMessagingTemplate messagingTemplate;

    @EventListener
    public void handleOrderStatusChange(OrderStatusChangedEvent event) {
        String userId = event.getUserId().toString();
        OrderStatusUpdate update = new OrderStatusUpdate(
            event.getOrderId(),
            event.getNewStatus(),
            LocalDateTime.now()
        );

        // Yalnız həmin istifadəçiyə göndər
        messagingTemplate.convertAndSendToUser(
            userId,
            "/queue/order-updates",
            update
        );
    }
}
```

**Frontend (JavaScript) tərəfi:**
```javascript
const socket = new SockJS('/ws');
const stompClient = Stomp.over(socket);

stompClient.connect({'Authorization': 'Bearer ' + token}, function(frame) {
    // Public topic-ə abunə ol
    stompClient.subscribe('/topic/public', function(message) {
        const data = JSON.parse(message.body);
        displayMessage(data);
    });

    // User-specific queue-ya abunə ol
    stompClient.subscribe('/user/queue/notifications', function(message) {
        const notification = JSON.parse(message.body);
        showNotification(notification);
    });

    // Mesaj göndər
    stompClient.send('/app/chat.message', {},
        JSON.stringify({content: 'Salam!', type: 'CHAT'}));
});
```

---

## Authentication ilə WebSocket

```java
@Configuration
public class WebSocketSecurityConfig
        extends AbstractSecurityWebSocketMessageBrokerConfigurer {

    @Override
    protected void configureInbound(MessageSecurityMetadataSourceRegistry messages) {
        messages
            .nullDestMatcher().authenticated()      // Connection
            .simpSubscribeDestMatchers("/user/**").authenticated()
            .simpDestMatchers("/app/**").authenticated()
            .simpSubscribeDestMatchers("/topic/public").permitAll()
            .anyMessage().authenticated();
    }

    @Override
    protected boolean sameOriginDisabled() {
        return true; // CSRF token-i WebSocket-dən devre dışı burax
    }
}

// JWT ilə WebSocket authentication
@Component
public class JwtHandshakeInterceptor implements HandshakeInterceptor {

    private final JwtTokenProvider jwtProvider;

    @Override
    public boolean beforeHandshake(ServerHttpRequest request,
                                    ServerHttpResponse response,
                                    WebSocketHandler wsHandler,
                                    Map<String, Object> attributes) {

        // URL parametrindən token al: /ws?token=...
        if (request instanceof ServletServerHttpRequest servletRequest) {
            String token = servletRequest.getServletRequest()
                .getParameter("token");

            if (token != null && jwtProvider.validateToken(token)) {
                String username = jwtProvider.getUsernameFromToken(token);
                attributes.put("username", username);
                return true;
            }
        }
        return false; // Handshake rədd et
    }

    @Override
    public void afterHandshake(ServerHttpRequest request,
                                ServerHttpResponse response,
                                WebSocketHandler wsHandler,
                                Exception exception) { }
}

// Websocket konfiqurasiyasında
@Override
public void registerStompEndpoints(StompEndpointRegistry registry) {
    registry.addEndpoint("/ws")
        .addInterceptors(new JwtHandshakeInterceptor(jwtProvider))
        .setAllowedOriginPatterns("*")
        .withSockJS();
}
```

---

## İntervyu Sualları

### 1. WebSocket ilə HTTP SSE (Server-Sent Events) fərqi nədir?
**Cavab:** WebSocket — full-duplex, client də server də mesaj göndərə bilər. SSE — server yalnız client-ə göndərir (unidirectional), HTTP üzərindədir, auto-reconnect var. Chat üçün WebSocket; notification, live feed üçün SSE daha sadədir.

### 2. STOMP nə üçündür?
**Cavab:** WebSocket aşağı səviyyəlidir — yalnız raw data göndərir. STOMP mesaj routing, topic/queue subscription, header dəstəyi əlavə edir. Spring-in message broker inteqrasiyası STOMP üzərindən işləyir. SockJS isə WebSocket dəstəkləməyən browserlar üçün fallback (long polling, XHR streaming) verir.

### 3. /topic vs /queue fərqi nədir?
**Cavab:** `/topic` — broadcast, abunə olan bütün client-lər mesajı alır (public chat). `/queue` — point-to-point, yalnız bir consumer alır. `/user/queue/...` ilə user-specific queue — yalnız həmin istifadəçi alır (private notification).

### 4. SimpMessagingTemplate.convertAndSendToUser() necə işləyir?
**Cavab:** `/user/{username}/queue/...` topic-inə mesaj göndərir. Spring WebSocket istifadəçi adına görə STOMP session-ları izləyir. Həmin istifadəçi `/user/queue/...`-a abunə olubsa, mesaj onun session-una göndərilir. `Principal.getName()` istifadəçi adını müəyyən edir.

### 5. WebSocket-dən sonra fasilə baş versə nə olur?
**Cavab:** SockJS avtomatik reconnect cəhdi edir. STOMP subscription-lar yenidən qoşulduqda yenilənməlidir (client tərəfdə). Server offline olan müddətdəki mesajlar itirilir (WebSocket stateful-dur). Həll: Redis Pub/Sub yaxud RabbitMQ ilə persistent subscription, yaxud client-ın yenidən qoşulduqda missed mesajları sorğulaması.

*Son yenilənmə: 2026-04-10*
