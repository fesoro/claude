# Real-time Chat System (Reverb) (Senior)

## Problem
- 1-on-1 və group chat
- Online/offline status
- Typing indicator
- Message delivery confirmation
- 10k concurrent user

---

## Architecture

```
Browser/Mobile ←─ WebSocket ─→ Laravel Reverb (port 8080)
                                      │
                                      ↓
                              Redis (pub/sub backplane)
                                      │
                              Multiple Reverb instances (HA)

Laravel App ────→ Event::dispatch ────→ Reverb broadcast
   │
   └─→ MySQL (message persistence)
```

---

## 1. Database schema

```php
<?php
// migrations
Schema::create('chats', function ($t) {
    $t->id();
    $t->enum('type', ['direct', 'group']);
    $t->string('name')->nullable();   // group adı
    $t->timestamps();
});

Schema::create('chat_participants', function ($t) {
    $t->id();
    $t->foreignId('chat_id')->constrained()->cascadeOnDelete();
    $t->foreignId('user_id')->constrained();
    $t->timestamp('last_read_at')->nullable();
    $t->timestamps();
    $t->unique(['chat_id', 'user_id']);
});

Schema::create('messages', function ($t) {
    $t->id();
    $t->foreignId('chat_id')->constrained();
    $t->foreignId('user_id')->constrained();
    $t->text('body');
    $t->timestamps();
    $t->index(['chat_id', 'created_at']);
});
```

---

## 2. Channel authorization

```php
<?php
// routes/channels.php
Broadcast::channel('chat.{chatId}', function ($user, $chatId) {
    return ChatParticipant::where('chat_id', $chatId)
        ->where('user_id', $user->id)
        ->exists();
});

// Presence channel — kim online
Broadcast::channel('chat.{chatId}.presence', function ($user, $chatId) {
    if (ChatParticipant::where('chat_id', $chatId)->where('user_id', $user->id)->exists()) {
        return ['id' => $user->id, 'name' => $user->name];
    }
    return null;
});
```

---

## 3. Events

```php
<?php
namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast
{
    use SerializesModels;
    
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
        return [
            'id'        => $this->message->id,
            'body'      => $this->message->body,
            'user_id'   => $this->message->user_id,
            'user_name' => $this->message->user->name,
            'sent_at'   => $this->message->created_at->toIso8601String(),
        ];
    }
    
    // Sender-ə eyni event göndərmə (optimistic UI)
    public function broadcastToEveryoneExcept(): ?string
    {
        return request()->header('X-Socket-ID');
    }
}

// Typing indicator
class UserTyping implements ShouldBroadcast
{
    public function __construct(public int $chatId, public int $userId, public string $userName) {}
    
    public function broadcastOn(): array
    {
        return [new PresenceChannel("chat.{$this->chatId}.presence")];
    }
}

// Message delivered
class MessageDelivered implements ShouldBroadcast
{
    public function __construct(public int $messageId, public int $chatId, public int $userId) {}
    
    public function broadcastOn(): array
    {
        return [new PrivateChannel("chat.{$this->chatId}")];
    }
}
```

---

## 4. Controller (HTTP send)

```php
<?php
class MessageController
{
    public function send(SendMessageRequest $req, Chat $chat): JsonResponse
    {
        // Auth gate (channel auth + business rule)
        $this->authorize('sendMessage', $chat);
        
        $message = $chat->messages()->create([
            'user_id' => auth()->id(),
            'body'    => $req->validated('body'),
        ]);
        
        // Broadcast async (queue)
        broadcast(new MessageSent($message))->toOthers();
        
        return response()->json($message, 201);
    }
    
    public function typing(Chat $chat): JsonResponse
    {
        broadcast(new UserTyping($chat->id, auth()->id(), auth()->user()->name))
            ->toOthers();
        
        return response()->noContent();
    }
}
```

---

## 5. Frontend (Vue + Echo)

```js
// resources/js/chat.js
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;
window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: 443,
    forceTLS: true,
});

// Subscribe
const channel = Echo.private(`chat.${chatId}`)
    .listen('.message.sent', (e) => {
        addMessage(e);
    })
    .listen('.message.delivered', (e) => {
        markDelivered(e.messageId);
    });

// Presence
Echo.join(`chat.${chatId}.presence`)
    .here((users) => {
        setOnlineUsers(users);
    })
    .joining((user) => addOnlineUser(user))
    .leaving((user) => removeOnlineUser(user))
    .listenForWhisper('typing', (data) => {
        showTypingIndicator(data.user);
    });

// Typing whisper (no DB hit)
input.addEventListener('input', debounce(() => {
    Echo.private(`chat.${chatId}`).whisper('typing', {
        user: currentUser.name,
    });
}, 500));

// Send (HTTP)
async function sendMessage(body) {
    const socketId = Echo.socketId();
    await axios.post(`/api/chats/${chatId}/messages`, { body }, {
        headers: { 'X-Socket-ID': socketId },   // sender-ə echo etmə
    });
    // Optimistic UI: local-də mesaj əlavə et
}
```

---

## 6. Production scaling

```yaml
# Reverb cluster (3 instance + Redis backplane)
# .env
REVERB_SCALING_ENABLED=true
REVERB_SCALING_CHANNEL=reverb-cluster
REDIS_HOST=redis-master.internal

# Nginx LB
upstream reverb {
    ip_hash;   # sticky session
    server reverb-1:8080;
    server reverb-2:8080;
    server reverb-3:8080;
}

server {
    listen 443 ssl http2;
    location / {
        proxy_pass http://reverb;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_read_timeout 3600s;
    }
}
```

```
File descriptor limit:
  ulimit -n 100000
  /etc/security/limits.conf:
    reverb soft nofile 100000
    reverb hard nofile 100000
```

---

## 7. Performance budget

```
Concurrent connections: 10k
Messages/sec:           1k
Memory per connection: ~10 KB
Total memory:          ~100 MB per server (3 server cluster)
CPU:                   ~30% per server (mostly broadcast fan-out)

Latency:
  HTTP send → DB:        20ms
  Event publish → Redis:  5ms
  Subscriber receive:    10ms
  Total client-to-client: ~35ms
```

---

## 8. Pitfalls

```
❌ Broadcast in transaction → DB rollback olarsa event göndərilib
   ✓ Use afterCommit() və ya queue dispatch transaction sonra

❌ Sync broadcast (queue olmadan) → HTTP request yavaşlayır
   ✓ ShouldBroadcast → automatic queue

❌ "All" channel — performance bomba (10k user-ə 1 message → 10k push)
   ✓ Granular channel (per-chat, per-user)

❌ Whisper-də sensitive data — server validate etmir!
   ✓ Whisper yalnız ephemeral data (typing indicator)

❌ Connection limit hit — graceful degradation yox
   ✓ Long-polling fallback (Pusher protocol dəstəkləyir)

❌ Reconnect storm — server restart-da 10k client eyni anda
   ✓ Client exponential backoff + jitter
```
