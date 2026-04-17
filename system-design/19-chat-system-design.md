# Chat System Design

## Nədir? (What is it?)

Chat system real-time mesajlaşma platformasıdır - istifadəçilər arasında anlıq mesaj
mübadiləsini təmin edir. 1-1 chat, group chat, media sharing, read receipts, typing
indicators, online presence kimi funksiyaları əhatə edir.

Sadə dillə: WhatsApp/Telegram kimi mesajlaşma sistemi. Mesaj göndərirsən, qarşı tərəf
dərhal görür, oxuduğunu bilirsən, online olduğunu görürsən.

```
User A                          User B
  │                               │
  │  "Salam!"                     │
  │──────────────────────────────▶│
  │                               │
  │         "Salam, necəsən?"     │
  │◀──────────────────────────────│
  │                               │
  │  ✓✓ (read)                    │
  │                               │
  │       [User B is typing...]   │
  │◀──────────────────────────────│
```

## Əsas Konseptlər (Key Concepts)

### Message Flow

```
1. User A sends message
2. Message reaches Chat Server via WebSocket
3. Server validates and stores message in DB
4. Server checks if User B is online
   - Online: Push via WebSocket
   - Offline: Store in queue, send push notification
5. User B receives message
6. User B's client sends "delivered" acknowledgment
7. User A sees ✓✓ (delivered)
8. User B reads message → "read" receipt sent
9. User A sees blue ✓✓ (read)
```

### Message States

```
Sent (✓):      Server received and stored
Delivered (✓✓): Recipient's device received
Read (✓✓ blue): Recipient opened and read

Timeline:
  User A sends → [Server stores] → ✓ (sent)
                                 → [Push to User B] → ✓✓ (delivered)
                                 → [User B opens chat] → ✓✓ read
```

### Data Models

```sql
-- Users
CREATE TABLE users (
    id BIGINT PRIMARY KEY,
    username VARCHAR(50) UNIQUE,
    display_name VARCHAR(100),
    avatar_url TEXT,
    last_seen_at TIMESTAMP,
    status ENUM('online', 'offline', 'away')
);

-- Conversations (1-1 and group)
CREATE TABLE conversations (
    id BIGINT PRIMARY KEY,
    type ENUM('direct', 'group'),
    name VARCHAR(100) NULL,          -- group name
    avatar_url TEXT NULL,            -- group avatar
    created_by BIGINT REFERENCES users(id),
    created_at TIMESTAMP
);

-- Conversation members
CREATE TABLE conversation_members (
    conversation_id BIGINT REFERENCES conversations(id),
    user_id BIGINT REFERENCES users(id),
    role ENUM('admin', 'member') DEFAULT 'member',
    joined_at TIMESTAMP,
    last_read_message_id BIGINT NULL,
    muted_until TIMESTAMP NULL,
    PRIMARY KEY (conversation_id, user_id)
);

-- Messages
CREATE TABLE messages (
    id BIGINT PRIMARY KEY,           -- Snowflake ID (time-ordered)
    conversation_id BIGINT REFERENCES conversations(id),
    sender_id BIGINT REFERENCES users(id),
    type ENUM('text', 'image', 'video', 'file', 'system'),
    content TEXT,
    metadata JSON,                    -- {file_url, thumbnail, dimensions}
    reply_to_id BIGINT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,

    INDEX idx_conversation_created (conversation_id, created_at DESC)
);

-- Message delivery status
CREATE TABLE message_receipts (
    message_id BIGINT REFERENCES messages(id),
    user_id BIGINT REFERENCES users(id),
    delivered_at TIMESTAMP NULL,
    read_at TIMESTAMP NULL,
    PRIMARY KEY (message_id, user_id)
);
```

### Group Chat Challenges

```
Group: 500 members

Message sent by 1 member:
  - 499 deliveries needed
  - Fan-out: 499 WebSocket pushes (online) + push notifications (offline)
  - 499 delivery receipts to track
  - 499 read receipts to track

Optimization:
  - Only show "read by X people" count, not individual receipts
  - Batch delivery for offline users
  - Limit group size (WhatsApp: 1024, Telegram: 200,000)
```

### Message Ordering

```
Problem: Network delays can cause messages to arrive out of order

Solutions:
1. Server-assigned timestamp (single source of truth)
2. Snowflake IDs (time-based, globally unique, sortable)
3. Lamport timestamps (logical clocks for causality)
4. Vector clocks (for conflict detection in distributed systems)

Snowflake ID structure:
  [41 bits: timestamp] [10 bits: machine ID] [12 bits: sequence]
  = time-ordered, unique across servers
```

## Arxitektura (Architecture)

### System Architecture

```
┌──────────┐  ┌──────────┐  ┌──────────┐
│  Web     │  │  Mobile  │  │  Desktop │
│  Client  │  │  Client  │  │  Client  │
└────┬─────┘  └────┬─────┘  └────┬─────┘
     │             │             │
     └─────────────┼─────────────┘
                   │ WebSocket
          ┌────────┴────────┐
          │  Load Balancer  │
          │ (sticky session)│
          └────────┬────────┘
                   │
     ┌─────────────┼─────────────┐
     │             │             │
┌────┴────┐  ┌────┴────┐  ┌────┴────┐
│  Chat   │  │  Chat   │  │  Chat   │
│ Server 1│  │ Server 2│  │ Server 3│
└────┬────┘  └────┬────┘  └────┬────┘
     │             │             │
     └─────────────┼─────────────┘
                   │
          ┌────────┴────────┐
          │  Redis Pub/Sub  │
          │ (cross-server   │
          │  messaging)     │
          └────────┬────────┘
                   │
     ┌─────────────┼──────────────────┐
     │             │                  │
┌────┴─────┐ ┌────┴─────┐     ┌─────┴──────┐
│ Message  │ │ Presence │     │  Push      │
│ Store    │ │ Service  │     │ Notification│
│(Cassandra│ │ (Redis)  │     │  Service   │
│  /MySQL) │ └──────────┘     └────────────┘
└──────────┘
```

## PHP/Laravel ilə Tətbiq (Implementation with PHP/Laravel)

### Chat Service

```php
class ChatService
{
    public function __construct(
        private MessageRepository $messages,
        private ConversationRepository $conversations,
        private PresenceService $presence
    ) {}

    public function sendMessage(int $conversationId, int $senderId, array $data): Message
    {
        // Verify membership
        $member = $this->conversations->getMember($conversationId, $senderId);
        if (!$member) {
            throw new UnauthorizedException('Not a member of this conversation');
        }

        // Create message
        $message = $this->messages->create([
            'id' => Snowflake::generate(),
            'conversation_id' => $conversationId,
            'sender_id' => $senderId,
            'type' => $data['type'] ?? 'text',
            'content' => $data['content'],
            'reply_to_id' => $data['reply_to_id'] ?? null,
            'metadata' => $data['metadata'] ?? null,
        ]);

        // Update conversation last activity
        $this->conversations->touch($conversationId, $message->id);

        // Broadcast to all members
        $members = $this->conversations->getMembers($conversationId);
        $this->broadcastToMembers($message, $members, $senderId);

        return $message;
    }

    private function broadcastToMembers(Message $message, Collection $members, int $senderId): void
    {
        foreach ($members as $member) {
            if ($member->user_id === $senderId) continue;

            if ($this->presence->isOnline($member->user_id)) {
                // Send via WebSocket
                broadcast(new NewMessage($message, $member->user_id));
            } else {
                // Queue push notification
                SendPushNotification::dispatch($member->user_id, $message);
            }
        }
    }

    public function getConversationMessages(
        int $conversationId, int $userId, ?int $before = null, int $limit = 50
    ): Collection {
        $this->conversations->verifyMembership($conversationId, $userId);

        return $this->messages->getMessages($conversationId, $before, $limit);
    }

    public function markAsRead(int $conversationId, int $userId, int $messageId): void
    {
        DB::table('conversation_members')
            ->where('conversation_id', $conversationId)
            ->where('user_id', $userId)
            ->update(['last_read_message_id' => $messageId]);

        // Notify sender about read receipt
        $message = $this->messages->find($messageId);
        if ($message && $this->presence->isOnline($message->sender_id)) {
            broadcast(new MessageRead($conversationId, $userId, $messageId));
        }
    }
}
```

### WebSocket Event Handling

```php
// app/Events/NewMessage.php
class NewMessage implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        private Message $message,
        private int $recipientId
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("user.{$this->recipientId}");
    }

    public function broadcastAs(): string
    {
        return 'message.new';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'conversation_id' => $this->message->conversation_id,
            'sender' => [
                'id' => $this->message->sender->id,
                'name' => $this->message->sender->display_name,
                'avatar' => $this->message->sender->avatar_url,
            ],
            'type' => $this->message->type,
            'content' => $this->message->content,
            'reply_to' => $this->message->reply_to_id,
            'sent_at' => $this->message->created_at->toIso8601String(),
        ];
    }
}

// app/Events/UserTyping.php
class UserTyping implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        private int $conversationId,
        private int $userId,
        private string $userName
    ) {}

    public function broadcastOn(): PresenceChannel
    {
        return new PresenceChannel("conversation.{$this->conversationId}");
    }

    public function broadcastAs(): string
    {
        return 'user.typing';
    }
}
```

### Conversation Controller

```php
class ConversationController extends Controller
{
    public function __construct(private ChatService $chat) {}

    // List user's conversations
    public function index(): JsonResponse
    {
        $conversations = Conversation::whereHas('members', function ($q) {
            $q->where('user_id', auth()->id());
        })
        ->with(['lastMessage.sender', 'members.user'])
        ->withCount(['messages as unread_count' => function ($q) {
            $member = DB::table('conversation_members')
                ->where('user_id', auth()->id())
                ->select('last_read_message_id');
            $q->where('id', '>', $member);
        }])
        ->orderByDesc('updated_at')
        ->paginate(20);

        return response()->json(ConversationResource::collection($conversations));
    }

    // Create direct conversation
    public function createDirect(Request $request): JsonResponse
    {
        $request->validate(['user_id' => 'required|exists:users,id']);

        // Check if direct conversation already exists
        $existing = $this->findExistingDirect(auth()->id(), $request->user_id);
        if ($existing) {
            return response()->json(new ConversationResource($existing));
        }

        $conversation = DB::transaction(function () use ($request) {
            $conv = Conversation::create(['type' => 'direct']);
            $conv->members()->attach([auth()->id(), $request->user_id]);
            return $conv;
        });

        return response()->json(new ConversationResource($conversation), 201);
    }

    // Create group conversation
    public function createGroup(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'member_ids' => 'required|array|min:2|max:500',
            'member_ids.*' => 'exists:users,id',
        ]);

        $conversation = DB::transaction(function () use ($request) {
            $conv = Conversation::create([
                'type' => 'group',
                'name' => $request->name,
                'created_by' => auth()->id(),
            ]);

            $members = collect($request->member_ids)
                ->push(auth()->id())
                ->unique()
                ->mapWithKeys(fn ($id) => [$id => [
                    'role' => $id === auth()->id() ? 'admin' : 'member'
                ]]);

            $conv->members()->attach($members);
            return $conv;
        });

        return response()->json(new ConversationResource($conversation), 201);
    }

    // Get messages with cursor pagination
    public function messages(int $conversationId, Request $request): JsonResponse
    {
        $messages = $this->chat->getConversationMessages(
            conversationId: $conversationId,
            userId: auth()->id(),
            before: $request->input('before'),
            limit: min($request->input('limit', 50), 100)
        );

        return response()->json(MessageResource::collection($messages));
    }

    public function sendMessage(int $conversationId, SendMessageRequest $request): JsonResponse
    {
        $message = $this->chat->sendMessage(
            conversationId: $conversationId,
            senderId: auth()->id(),
            data: $request->validated()
        );

        return response()->json(new MessageResource($message), 201);
    }

    public function markRead(int $conversationId, Request $request): JsonResponse
    {
        $this->chat->markAsRead(
            $conversationId,
            auth()->id(),
            $request->input('message_id')
        );

        return response()->json(['status' => 'ok']);
    }
}
```

### Offline Message Queue

```php
class OfflineMessageService
{
    public function queueForOfflineUser(int $userId, Message $message): void
    {
        Redis::rpush("offline_queue:{$userId}", json_encode([
            'message_id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'sender_id' => $message->sender_id,
            'content' => $message->content,
            'sent_at' => $message->created_at->toIso8601String(),
        ]));

        // Set TTL for offline queue (7 days)
        Redis::expire("offline_queue:{$userId}", 604800);
    }

    public function deliverPendingMessages(int $userId): array
    {
        $messages = [];
        while ($item = Redis::lpop("offline_queue:{$userId}")) {
            $messages[] = json_decode($item, true);
        }
        return $messages;
    }
}
```

## Real-World Nümunələr

1. **WhatsApp** - 100B+ messages/day, end-to-end encryption, Erlang backend
2. **Telegram** - MTProto protocol, cloud-based, channels with millions
3. **Slack** - Team messaging, channels, threads, integrations
4. **Discord** - Real-time voice + text, millions of concurrent users
5. **Facebook Messenger** - Cross-platform, rich media, chatbots

## Interview Sualları

**S1: 1-1 chat vs group chat arxitektura fərqi nədir?**
C: 1-1 sadədir - mesaj bir nəfərə göndərilir. Group chat-da fan-out problemi
var - bir mesaj N nəfərə çatdırılmalıdır. Group üçün optimization: batch delivery,
read receipt-ləri aggregate edin, typing indicator-ı throttle edin.

**S2: Message ordering necə təmin olunur?**
C: Server-assigned Snowflake ID (time-based + unique). Client-side reordering
buffer. Eyni conversation-dakı mesajlar eyni server-ə route edilə bilər
(consistent hashing). Causal ordering üçün Lamport timestamps.

**S3: Online/offline presence necə idarə olunur?**
C: Heartbeat mechanism - client hər 30s ping göndərir. Timeout olsa offline
sayılır. Redis-də user status saxlanır. Presence channel ilə real-time
online/offline notification. Battery optimization üçün mobile-da daha nadir ping.

**S4: Read receipt-lər necə implement olunur?**
C: conversation_members table-da last_read_message_id saxlanır. User chat açanda
və ya scroll edəndə update olunur. Sender-ə WebSocket ilə notification göndərilir.
Group-da "read by 15 of 50" formatında göstərilir.

**S5: Media mesajları necə handle olunur?**
C: Client pre-signed URL alır, media-nı S3-ə yükləyir, sonra mesaj göndərir
(media URL ilə). Thumbnails server-side generate olunur. Client lazy loading
ilə media-nı yükləyir.

**S6: End-to-end encryption necə işləyir?**
C: Signal Protocol - hər user public/private key pair yaradır. Mesaj göndərilmədən
əvvəl recipient-in public key ilə encrypt olunur. Server mesajı oxuya bilmir.
Key exchange Diffie-Hellman ilə. Group chat-da hər member üçün ayrıca encrypt.

**S7: Chat history sync necə olur (multi-device)?**
C: Server-da saxlanan mesajlar bütün device-lara sync olunur. Hər device son
sync timestamp saxlayır, yeni device connect olduqda delta sync olur.
End-to-end encryption olduqda key sharing protocol lazımdır.

## Best Practices

1. **WebSocket** - Real-time messaging üçün əsas transport
2. **Message Queue** - Offline users üçün mesaj queue-lama
3. **Snowflake IDs** - Time-ordered, globally unique message IDs
4. **Cursor Pagination** - Offset əvəzinə message ID-based pagination
5. **Presence Heartbeat** - 30s interval, timeout ilə offline detection
6. **Read Receipt Batching** - Hər mesaj üçün deyil, debounced update
7. **Media via CDN** - Şəkil/video CDN-dən serve edin
8. **Push Notifications** - Offline users üçün FCM/APNs
9. **Message Retention** - Storage policy, archive old messages
10. **Rate Limiting** - Spam prevention, per-user message limits
