# System Design: Chat Application (Lead)

## Mündəricat
1. [Tələblər](#tələblər)
2. [Real-Time Protokol Seçimi](#real-time-protokol-seçimi)
3. [Yüksək Səviyyəli Dizayn](#yüksək-səviyyəli-dizayn)
4. [PHP İmplementasiyası](#php-implementasiyası)
5. [İntervyu Sualları](#intervyu-sualları)

---

## Tələblər

```
Funksional:
  1-1 mesajlaşma
  Qrup söhbəti (max 500 üzv)
  Online status
  Mesaj statusları: sent, delivered, read
  Media göndərmək (şəkil, fayl)
  Push bildirişi (offline üçün)

Qeyri-funksional:
  Aşağı gecikmə: < 100ms mesaj çatdırma
  Yüksək mövcudluq: 99.99%
  500M aktiv istifadəçi
  Peak: 10M eş-zamanlı bağlantı
  Mesajlar kalıcı saxlanır

Hesablamalar:
  10M eş-zamanlı bağlantı
  Ortalama 10 mesaj/gün/user = 5B mesaj/gün
  Ortalama mesaj 100 bytes = 500GB/gün
```

---

## Real-Time Protokol Seçimi

```
WebSocket:
  Bidirectional, persistent connection
  Low overhead (no HTTP header per message)
  Browser dəstəyi: universal
  Chat üçün ən uyğun

Long Polling:
  HTTP request açıq qalır, server cavab gələnə qədər
  WebSocket olmayan mühitlər üçün fallback
  Overhead: hər sorğuda HTTP header

Server-Sent Events (SSE):
  Unidirectional (server → client)
  Chat üçün uyğun deyil (client məlumat göndərə bilmir WebSocket olmadan)

XMPP / MQTT:
  IoT-da MQTT populyar
  Chat üçün XMPP (WhatsApp istifadə etdi, sonra custom)

Seçim: WebSocket (birincil) + Long Polling (fallback)
```

---

## Yüksək Səviyyəli Dizayn

```
                    ┌──────────────────────────────┐
                    │        Load Balancer          │
                    │  (sticky session / IP hash)   │
                    └─────────┬──────────┬──────────┘
                              │          │
                    ┌─────────▼──┐  ┌────▼─────────┐
                    │Chat Server1│  │Chat Server2  │
                    │(WebSocket) │  │(WebSocket)   │
                    └─────────┬──┘  └────┬─────────┘
                              │          │
                    ┌─────────▼──────────▼─────────┐
                    │      Message Broker           │
                    │      (Redis Pub/Sub)           │
                    └──────────────┬───────────────┘
                                   │
                    ┌──────────────▼───────────────┐
                    │      Message Storage          │
                    │      (Cassandra / MySQL)      │
                    └──────────────────────────────┘

Mesaj göndərmə axını:
  User A (Server1) → mesaj göndər → Server1
  Server1 → DB-ə yaz
  Server1 → Redis Pub/Sub: channel "user:B" → publish
  Server2 (User B-nin bağlantısı) → subscribe → User B-yə göndər

Niyə Redis Pub/Sub?
  User A Server1-ə, User B Server2-yə qoşulmuş ola bilər
  Redis hər server-ı xəbərdar edir
```

---

## PHP İmplementasiyası

```php
<?php
// Swoole ilə WebSocket Chat Server (topik 119)
use Swoole\WebSocket\Server;
use Swoole\WebSocket\Frame;

class ChatServer
{
    private Server $server;
    private \Redis $redis;
    /** @var array<int, string> $connections fd → userId */
    private array $connections = [];
    /** @var array<string, int[]> $userConnections userId → [fd] */
    private array $userConnections = [];

    public function __construct(private MessageRepository $messages)
    {
        $this->server = new Server('0.0.0.0', 9501);
        $this->redis  = new \Redis();
        $this->redis->connect('redis', 6379);

        $this->server->on('open',    [$this, 'onOpen']);
        $this->server->on('message', [$this, 'onMessage']);
        $this->server->on('close',   [$this, 'onClose']);
    }

    public function onOpen(Server $server, \Swoole\Http\Request $request): void
    {
        $userId = $this->authenticateUser($request);
        if (!$userId) {
            $server->close($request->fd);
            return;
        }

        $this->connections[$request->fd] = $userId;
        $this->userConnections[$userId][] = $request->fd;

        // Online status
        $this->redis->setex("online:{$userId}", 120, '1');

        // Pending mesajları göndər
        $this->deliverPendingMessages($request->fd, $userId);
    }

    public function onMessage(Server $server, Frame $frame): void
    {
        $data = json_decode($frame->data, true);

        match ($data['type'] ?? '') {
            'send_message' => $this->handleSendMessage($frame->fd, $data),
            'typing'       => $this->handleTyping($frame->fd, $data),
            'read_receipt' => $this->handleReadReceipt($frame->fd, $data),
            default        => null,
        };
    }

    private function handleSendMessage(int $fd, array $data): void
    {
        $senderId   = $this->connections[$fd];
        $receiverId = $data['to'];
        $content    = $data['content'];

        // DB-ə yaz
        $messageId = $this->messages->save([
            'from'       => $senderId,
            'to'         => $receiverId,
            'content'    => $content,
            'status'     => 'sent',
            'created_at' => microtime(true),
        ]);

        $payload = json_encode([
            'type'       => 'new_message',
            'id'         => $messageId,
            'from'       => $senderId,
            'content'    => $content,
            'created_at' => time(),
        ]);

        // Receiver bu server-dədir?
        $receiverFds = $this->userConnections[$receiverId] ?? [];

        if (!empty($receiverFds)) {
            // Birbaşa göndər
            foreach ($receiverFds as $receiverFd) {
                $this->server->push($receiverFd, $payload);
            }
            $this->messages->updateStatus($messageId, 'delivered');
        } else {
            // Başqa server-də ola bilər → Redis Pub/Sub
            $this->redis->publish("chat:user:{$receiverId}", $payload);

            // Offline → Push notification
            if (!$this->redis->exists("online:{$receiverId}")) {
                $this->pushNotificationQueue->publish(
                    new PushNotificationJob($receiverId, "Yeni mesaj", $content)
                );
            }
        }

        // Sender-ə ACK göndər
        $this->server->push($fd, json_encode([
            'type'       => 'message_ack',
            'message_id' => $messageId,
            'status'     => 'sent',
        ]));
    }

    public function onClose(Server $server, int $fd): void
    {
        $userId = $this->connections[$fd] ?? null;
        if ($userId) {
            unset($this->connections[$fd]);
            $this->userConnections[$userId] = array_filter(
                $this->userConnections[$userId] ?? [],
                fn($f) => $f !== $fd
            );

            if (empty($this->userConnections[$userId])) {
                $this->redis->del("online:{$userId}");
            }
        }
    }

    public function start(): void
    {
        // Redis Pub/Sub listener (başqa coroutine-də)
        go(function () {
            $sub = new \Redis();
            $sub->connect('redis', 6379);

            $sub->psubscribe(['chat:user:*'], function ($redis, $pattern, $channel, $message) {
                $userId = str_replace('chat:user:', '', $channel);
                $fds    = $this->userConnections[$userId] ?? [];

                foreach ($fds as $fd) {
                    $this->server->push($fd, $message);
                }
            });
        });

        $this->server->start();
    }
}
```

```php
<?php
// Message Storage — Cassandra schema (PHP cassandra driver)
// Cassandra time-series üçün ideal

// CREATE TABLE messages (
//   conversation_id UUID,
//   message_id      TIMEUUID,         -- built-in timestamp ordering
//   sender_id       UUID,
//   content         TEXT,
//   status          TEXT,
//   created_at      TIMESTAMP,
//   PRIMARY KEY (conversation_id, message_id)
// ) WITH CLUSTERING ORDER BY (message_id DESC)
//   AND default_time_to_live = 31536000; -- 1 il TTL

class CassandraMessageRepository
{
    public function findConversation(
        string $conversationId,
        int    $limit    = 50,
        ?string $beforeId = null,
    ): array {
        $query = "SELECT * FROM messages WHERE conversation_id = ?";
        $params = [$conversationId];

        if ($beforeId !== null) {
            $query   .= " AND message_id < ?";
            $params[] = $beforeId;
        }

        $query .= " LIMIT {$limit}";

        return $this->session->execute($query, $params)->all();
    }
}
```

---

## İntervyu Sualları

- WebSocket niyə chat üçün HTTP-dən daha uyğundur?
- User A ilə User B fərqli chat server-ə qoşulubsa necə mesajlaşır?
- Redis Pub/Sub ilə Kafka arasında chat üçün hansını seçərdiniz?
- Mesaj saxlamaq üçün Cassandra niyə MySQL-dən daha uyğundur?
- Offline istifadəçiyə mesajı necə çatdırırsınız?
- 10M eş-zamanlı bağlantı üçün horizontal scale necə edilir?
