# Server-Sent Events (SSE) (Senior)

## İcmal

Server-Sent Events (SSE) — server-dən client-ə birtərəfli (one-directional) real-time data streaming texnologiyasıdır. Client bir dəfə HTTP connection açır, server isə bu connection üzərindən hadisələri (event-ləri) push edir. Connection browser tərəfindən avtomatik saxlanılır.

SSE, `text/event-stream` media type-ı istifadə edir və HTTP/1.1 üzərində işləyir — xüsusi protokol tələb etmir.

---

## Niyə Vacibdir

Real-time data tələb edən əksər use-case-lər üçün WebSocket-dən daha sadə həlldir. Browser-in built-in `EventSource` API-si avtomatik reconnect edir, server-də isə minimal infrastruktur tələb olunur.

**Tipik use-case-lər:**
- Sifariş (order) status yeniləmələri
- Dashboard metric-ləri (CPU, RAM, traffic)
- Live notification-lar
- Fond (stock) qiymətləri
- CI/CD pipeline progress

---

## Əsas Anlayışlar

### SSE vs WebSocket

| Xüsusiyyət | SSE | WebSocket |
|---|---|---|
| İstiqamət | Server → Client (unidirectional) | Bidirectional |
| Protokol | HTTP/1.1 | ws:// (ayrı protokol) |
| Reconnect | Avtomatik (browser) | Manual |
| Proxy dəstəyi | Tam (HTTP) | Bəzən problem |
| Komplekslik | Sadə | Mürəkkəb |
| Use-case | Push-only updates | Chat, real-time game |

### SSE Event Format

```
id: 123
event: order-update
data: {"orderId": 42, "status": "SHIPPED"}

```

Hər event boş sətrlə bitir. `id` field-i reconnect zamanı `Last-Event-ID` header-ə çevrilir — browser kəsilmiş yerindən davam edir.

### Spring-də iki yanaşma

1. **Spring MVC** — `SseEmitter` (thread-based, blocking)
2. **Spring WebFlux** — `Flux<ServerSentEvent<T>>` (reactive, non-blocking)

---

## Praktik Baxış

**Ne vaxt SSE istifadə et:**
- Yalnız server → client data lazımdır
- Browser client-dir
- Sadəlik əsas prioritetdir

**Ne vaxt SSE istifadə etmə:**
- Client-dən server-ə data göndərmək lazımdır → WebSocket
- Binary data (video, audio) → WebSocket və ya HTTP streaming
- Long-polling ilə kifayətlənirsən (daha az connection)

**Multi-instance problem:**
- `SseEmitter` in-memory saxlanılır — request hansı instance-a düşürsə, emitter onda olur
- Load balancer sticky session olmasa, client başqa instance-a düşəndə emitter-i tapa bilmir
- Həll: Redis Pub/Sub — hər instance mesajı Redis-dən alır, öz emitter-lərini notifly edir

---

## Nümunələr

### Ümumi Nümunə

Order tracking sistemi: müştəri `/api/orders/42/status/stream` endpoint-inə subscribe olur. OrderService sifarişi update edəndə status dəyişikliyini SSE vasitəsilə client-ə göndərir.

### Kod Nümunəsi

#### 1. Spring MVC — SseEmitter yanaşması

**SseEmitter Registry (thread-safe):**

```java
@Component
public class SseEmitterRegistry {

    // orderId → emitter-lər siyahısı (eyni order üçün birdən çox tab ola bilər)
    private final Map<Long, List<SseEmitter>> emitters = new ConcurrentHashMap<>();

    public SseEmitter register(Long orderId) {
        // 5 dəqiqə timeout — client heartbeat ilə diri saxlayır
        SseEmitter emitter = new SseEmitter(5 * 60 * 1000L);

        emitters.computeIfAbsent(orderId, k -> new CopyOnWriteArrayList<>()).add(emitter);

        // Lifecycle callbacks
        Runnable cleanup = () -> removeEmitter(orderId, emitter);
        emitter.onCompletion(cleanup);
        emitter.onTimeout(cleanup);
        emitter.onError(e -> cleanup.run());

        return emitter;
    }

    public void send(Long orderId, Object data) {
        List<SseEmitter> orderEmitters = emitters.getOrDefault(orderId, List.of());
        List<SseEmitter> dead = new ArrayList<>();

        for (SseEmitter emitter : orderEmitters) {
            try {
                emitter.send(
                    SseEmitter.event()
                        .id(String.valueOf(System.currentTimeMillis()))
                        .name("order-update")
                        .data(data, MediaType.APPLICATION_JSON)
                );
            } catch (IOException e) {
                // Emitter artıq bağlıdır — siyahıdan çıxar
                dead.add(emitter);
            }
        }

        dead.forEach(e -> removeEmitter(orderId, e));
    }

    private void removeEmitter(Long orderId, SseEmitter emitter) {
        List<SseEmitter> list = emitters.get(orderId);
        if (list != null) {
            list.remove(emitter);
            if (list.isEmpty()) {
                emitters.remove(orderId);
            }
        }
    }
}
```

**SSE Controller:**

```java
@RestController
@RequestMapping("/api/orders")
public class OrderSseController {

    private final SseEmitterRegistry registry;

    public OrderSseController(SseEmitterRegistry registry) {
        this.registry = registry;
    }

    @GetMapping(value = "/{orderId}/status/stream",
                produces = MediaType.TEXT_EVENT_STREAM_VALUE)
    public SseEmitter streamOrderStatus(@PathVariable Long orderId) {
        SseEmitter emitter = registry.register(orderId);

        // İlk event — connection qurulduğunu təsdiq et
        try {
            emitter.send(
                SseEmitter.event()
                    .name("connected")
                    .data("Subscribed to order " + orderId)
            );
        } catch (IOException e) {
            emitter.completeWithError(e);
        }

        return emitter;
    }
}
```

**OrderService — status dəyişdirəndə SSE göndər:**

```java
@Service
public class OrderService {

    private final OrderRepository orderRepository;
    private final SseEmitterRegistry sseRegistry;

    @Transactional
    public void updateOrderStatus(Long orderId, OrderStatus newStatus) {
        Order order = orderRepository.findById(orderId)
            .orElseThrow(() -> new EntityNotFoundException("Order not found: " + orderId));

        order.setStatus(newStatus);
        order.setUpdatedAt(Instant.now());
        orderRepository.save(order);

        // SSE vasitəsilə client-ə bildir (async)
        sseRegistry.send(orderId, new OrderStatusEvent(orderId, newStatus, Instant.now()));
    }
}
```

**Heartbeat — connection-u diri saxla:**

```java
@Component
public class SseHeartbeatScheduler {

    private final SseEmitterRegistry registry;

    public SseHeartbeatScheduler(SseEmitterRegistry registry) {
        this.registry = registry;
    }

    // Hər 30 saniyədə bir heartbeat göndər
    @Scheduled(fixedDelay = 30_000)
    public void sendHeartbeat() {
        registry.sendHeartbeatToAll();
    }
}
```

Registry-yə `sendHeartbeatToAll()` əlavə et:

```java
public void sendHeartbeatToAll() {
    emitters.forEach((orderId, list) -> {
        List<SseEmitter> dead = new ArrayList<>();
        for (SseEmitter emitter : list) {
            try {
                emitter.send(SseEmitter.event().comment("heartbeat"));
            } catch (IOException e) {
                dead.add(emitter);
            }
        }
        dead.forEach(e -> removeEmitter(orderId, e));
    });
}
```

---

#### 2. Spring WebFlux — Reactive yanaşma

```java
@RestController
@RequestMapping("/api/orders")
public class OrderSseWebFluxController {

    private final OrderStatusService statusService;

    @GetMapping(value = "/{orderId}/status/stream",
                produces = MediaType.TEXT_EVENT_STREAM_VALUE)
    public Flux<ServerSentEvent<OrderStatusEvent>> streamStatus(
            @PathVariable Long orderId) {

        return statusService.getStatusStream(orderId)
            .map(status -> ServerSentEvent.<OrderStatusEvent>builder()
                .id(String.valueOf(status.timestamp().toEpochMilli()))
                .event("order-update")
                .data(status)
                .build())
            // Hər 20 saniyədə heartbeat
            .mergeWith(
                Flux.interval(Duration.ofSeconds(20))
                    .map(tick -> ServerSentEvent.<OrderStatusEvent>builder()
                        .comment("heartbeat")
                        .build())
            );
    }
}
```

**Sinks istifadəsi ilə reactive event bus:**

```java
@Service
public class OrderStatusService {

    // orderId → Sink (event publisher)
    private final Map<Long, Sinks.Many<OrderStatusEvent>> sinks = new ConcurrentHashMap<>();

    public Flux<OrderStatusEvent> getStatusStream(Long orderId) {
        Sinks.Many<OrderStatusEvent> sink = sinks.computeIfAbsent(orderId,
            k -> Sinks.many().multicast().onBackpressureBuffer());

        return sink.asFlux()
            .doFinally(signal -> {
                // Heç subscriber qalmayanda sink-i sil
                Sinks.Many<OrderStatusEvent> s = sinks.get(orderId);
                if (s != null && s.currentSubscriberCount() == 0) {
                    sinks.remove(orderId);
                }
            });
    }

    public void publishStatusChange(Long orderId, OrderStatus newStatus) {
        Sinks.Many<OrderStatusEvent> sink = sinks.get(orderId);
        if (sink != null) {
            sink.tryEmitNext(new OrderStatusEvent(orderId, newStatus, Instant.now()));
        }
    }
}
```

---

#### 3. Client-side JavaScript (EventSource API)

```javascript
const orderId = 42;
const eventSource = new EventSource(`/api/orders/${orderId}/status/stream`);

// Xüsusi event-lər dinlə
eventSource.addEventListener('order-update', (event) => {
    const data = JSON.parse(event.data);
    console.log('Order status:', data.status);
    updateUI(data);
});

eventSource.addEventListener('connected', (event) => {
    console.log('SSE connected:', event.data);
});

// Xəta halında browser avtomatik reconnect edir
eventSource.onerror = (error) => {
    console.error('SSE error, reconnecting...', error);
};

// Bağla (istifadəçi səhifəni tərk edəndə)
window.addEventListener('beforeunload', () => {
    eventSource.close();
});
```

---

#### 4. Multi-instance üçün Redis Pub/Sub həlli

```java
@Service
public class RedisBackedSseService {

    private final SseEmitterRegistry localRegistry;
    private final RedisTemplate<String, String> redisTemplate;
    private final ObjectMapper objectMapper;

    private static final String CHANNEL_PREFIX = "order-status:";

    // Pub: OrderService bu metodu çağırır
    public void publishStatusChange(Long orderId, OrderStatusEvent event) {
        try {
            String json = objectMapper.writeValueAsString(event);
            // Bütün instance-lar bu channel-ı dinləyir
            redisTemplate.convertAndSend(CHANNEL_PREFIX + orderId, json);
        } catch (JsonProcessingException e) {
            throw new RuntimeException("Failed to serialize event", e);
        }
    }

    // Sub: hər instance öz local emitter-lərini notifly edir
    @Bean
    public MessageListenerAdapter orderStatusListener() {
        return new MessageListenerAdapter(this, "handleRedisMessage");
    }

    public void handleRedisMessage(String message, String channel) {
        Long orderId = Long.parseLong(channel.replace(CHANNEL_PREFIX, ""));
        try {
            OrderStatusEvent event = objectMapper.readValue(message, OrderStatusEvent.class);
            // Yalnız bu instance-dakı emitter-lərə göndər
            localRegistry.send(orderId, event);
        } catch (JsonProcessingException e) {
            // log and ignore
        }
    }
}
```

---

## Praktik Tapşırıqlar

1. **Sadə notification sistemi:** `/api/notifications/stream` endpoint yarat. Admin panel-dən göndərilən mesajları bütün bağlı client-lərə SSE ilə çatdır.

2. **Dashboard metrics:** Hər 5 saniyədə server stats (CPU load, active users, request count) SSE ilə göndər. Client-də real-time qrafik göstər.

3. **Multi-instance test:** İki Spring Boot instance başlat, load balancer önünə qoy. SSE subscribe et, digər instance-dan event publish et. Redis Pub/Sub olmadan işləmir — Redis əlavə edib test et.

4. **Reconnect test:** SSE subscribe olandan sonra server-i restart et. Browser neçə saniyədən sonra reconnect edir? `Last-Event-ID` header-i serverə düzgün çatır?

---

## Əlaqəli Mövzular

- `101-restclient.md` — HTTP client (webhook sender tərəfi)
- `102-httpexchange.md` — Declarative HTTP interface-lər
- `105-webhook-delivery.md` — Server-dən event push (alternativ pattern)
- `java/advanced/20-grpc.md` — Bidirectional streaming alternativ
