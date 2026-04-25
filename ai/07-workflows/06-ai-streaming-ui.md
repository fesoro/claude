# AI Streaming UI: SSE, Laravel, Livewire, Next.js RSC (Senior)

> **Oxucu kütləsi:** Senior developerlər, full-stack engineer-lər
> **Əhatə dairəsi:** Anthropic SDK streaming, SSE (Server-Sent Events), Laravel streaming response, Livewire və Inertia integrasiyası, Next.js App Router + AI SDK, UI pattern-ləri (typing indicator, stop, retry), backpressure, error recovery, reconnect.

---

## 1. Niyə Streaming Lazımdır

LLM-in bir cavabı 10-30 saniyə çəkə bilər. İstifadəçi ekranda nəticə görməsə, "buza düşdü" təəssüratı yaranır.

Streaming iki problemi həll edir:

1. **UX** — TTFT (Time To First Token) 300-800 ms olur, total 20 saniyə olsa da istifadəçi oxumağa dərhal başlayır.
2. **Memory** — uzun cavabı buffer etməkdənsə, chunk-by-chunk işləyirsən.

Streamingin xərci: **kod mürəkkəbliyi** — connection management, error mid-stream, reconnect, client-side parser.

---

## 2. Anthropic SDK Streaming — PHP və Node

### PHP (anthropic-sdk-php)

```php
<?php
// Sadə CLI-də stream
use Anthropic\Anthropic;

$client = Anthropic::factory()->withApiKey($apiKey)->make();

$stream = $client->messages()->createStream([
    'model' => 'claude-sonnet-4-6',
    'max_tokens' => 1024,
    'messages' => [['role' => 'user', 'content' => 'Azərbaycan haqqında 3 faktı yaz.']],
]);

foreach ($stream as $event) {
    match ($event->type) {
        'message_start' => print("\n[Start]\n"),
        'content_block_delta' => print($event->delta->text ?? ''),
        'message_delta' => null,
        'message_stop' => print("\n[Done]\n"),
        default => null,
    };
}
```

### Node (@anthropic-ai/sdk)

```typescript
import Anthropic from "@anthropic-ai/sdk";

const client = new Anthropic();

const stream = await client.messages.stream({
  model: "claude-sonnet-4-6",
  max_tokens: 1024,
  messages: [{ role: "user", content: "3 facts about Azerbaijan." }],
});

for await (const event of stream) {
  if (event.type === "content_block_delta" && event.delta.type === "text_delta") {
    process.stdout.write(event.delta.text);
  }
}

const finalMessage = await stream.finalMessage();
console.log("\nUsage:", finalMessage.usage);
```

### Event Tipləri

| Event | Məzmunu |
|-------|---------|
| `message_start` | Başlama, usage placeholder |
| `content_block_start` | Yeni content block (text və ya tool_use) |
| `content_block_delta` | Faktiki token — `text_delta.text` və ya `input_json_delta.partial_json` |
| `content_block_stop` | Block bitir |
| `message_delta` | Stop reason, final usage |
| `message_stop` | Stream bitir |
| `ping` | Keep-alive (ignoring olur) |
| `error` | API xətası mid-stream |

---

## 3. Server-Sent Events (SSE) — Protokol Əsası

SSE HTTP-nin uzunmüddətli cavab mexanizmidir. Browser `EventSource` API ilə oxuyur. WebSocket-dən sadə:

- **Yalnız server → client** (AI use case-də bu yetərlidir)
- **HTTP-dir** — proxy, load balancer, CDN hamısı dəstəkləyir
- **Auto-reconnect** browser tərəfindən
- **Chrome 6+, Firefox 6+, Safari 5+** — universal

### SSE Format

```
event: message
data: {"text": "Hello"}
id: 1

event: message
data: {"text": " world"}
id: 2

event: done
data: {}
```

Boş sətir (double `\n`) event separator-dur. `event:`, `data:`, `id:`, `retry:` sahələri.

### Content-Type

```
Content-Type: text/event-stream
Cache-Control: no-cache
Connection: keep-alive
X-Accel-Buffering: no
```

`X-Accel-Buffering: no` — **nginx üçün kritik**. Yoxsa nginx bufferlə saxlayacaq və SSE işləməyəcək.

---

## 4. Laravel — StreamedResponse ilə SSE Endpoint

```php
<?php
// routes/web.php
Route::post('/chat/stream', [ChatController::class, 'stream']);

// app/Http/Controllers/ChatController.php
namespace App\Http\Controllers;

use Anthropic\Anthropic;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatController extends Controller
{
    public function __construct(private Anthropic $client) {}

    public function stream(Request $request): StreamedResponse
    {
        $data = $request->validate([
            'messages' => 'required|array',
            'messages.*.role' => 'required|in:user,assistant',
            'messages.*.content' => 'required|string',
        ]);

        return new StreamedResponse(function () use ($data) {
            // Nginx buffer bypass
            header('X-Accel-Buffering: no');

            $stream = $this->client->messages()->createStream([
                'model' => 'claude-sonnet-4-6',
                'max_tokens' => 2048,
                'messages' => $data['messages'],
            ]);

            try {
                foreach ($stream as $event) {
                    if ($event->type === 'content_block_delta' && isset($event->delta->text)) {
                        $this->sse('token', ['text' => $event->delta->text]);
                    } elseif ($event->type === 'message_delta') {
                        $this->sse('usage', [
                            'input_tokens' => $event->usage?->inputTokens ?? 0,
                            'output_tokens' => $event->usage?->outputTokens ?? 0,
                        ]);
                    }

                    // Client disconnect yoxlaması
                    if (connection_aborted()) {
                        break;
                    }
                }

                $this->sse('done', ['ok' => true]);
            } catch (\Throwable $e) {
                $this->sse('error', [
                    'message' => config('app.debug') ? $e->getMessage() : 'Stream error',
                    'code' => $e->getCode(),
                ]);
                report($e);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function sse(string $event, array $data): void
    {
        echo "event: {$event}\n";
        echo "data: " . json_encode($data) . "\n\n";
        ob_flush();
        flush();
    }
}
```

### Vacib Laravel Konfiqurasiyası

```php
// config/octane.php - Octane istifadə edirsinizsə
'warm' => [
    // Stream endpoint-ləri burada olmamalıdır — hər request fresh
],

// Octane-də flush listener
'flush' => [
    // ...
],
```

**PHP-FPM konfiqurasiyası:**

```ini
; php.ini
output_buffering = Off
zlib.output_compression = Off
```

```nginx
# nginx.conf — SSE endpoint üçün
location /chat/stream {
    proxy_pass http://php-fpm;
    proxy_http_version 1.1;
    proxy_buffering off;
    proxy_cache off;
    proxy_set_header Connection '';
    chunked_transfer_encoding off;
    proxy_read_timeout 600s;  # stream uzun ola bilər
}
```

---

## 5. Client-Side — Vanilla JS EventSource

```javascript
// resources/js/chat.js
async function streamChat(messages, onToken, onDone, onError) {
  const resp = await fetch('/chat/stream', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
    },
    body: JSON.stringify({ messages }),
  });

  if (!resp.ok) {
    onError(new Error(`HTTP ${resp.status}`));
    return;
  }

  const reader = resp.body.getReader();
  const decoder = new TextDecoder();
  let buffer = '';

  while (true) {
    const { done, value } = await reader.read();
    if (done) break;

    buffer += decoder.decode(value, { stream: true });

    // SSE event parsing — double-newline separator
    const events = buffer.split('\n\n');
    buffer = events.pop(); // yarımçıq olanı saxla

    for (const raw of events) {
      const event = parseSSE(raw);
      if (!event) continue;

      switch (event.name) {
        case 'token':
          onToken(event.data.text);
          break;
        case 'usage':
          // track cost
          break;
        case 'done':
          onDone();
          return;
        case 'error':
          onError(new Error(event.data.message));
          return;
      }
    }
  }
}

function parseSSE(block) {
  const lines = block.split('\n');
  let name = 'message';
  let data = '';
  for (const line of lines) {
    if (line.startsWith('event:')) name = line.slice(6).trim();
    else if (line.startsWith('data:')) data += line.slice(5).trim();
  }
  try {
    return { name, data: JSON.parse(data) };
  } catch {
    return null;
  }
}
```

**Niyə `EventSource` yox?** `EventSource` yalnız GET dəstəkləyir. POST body ilə chat mesajları göndərmək üçün `fetch` + manual parse.

---

## 6. Livewire 3 + Streaming

Livewire v3 `wire:stream` atributu streaming-i native dəstəkləyir.

```php
<?php
// app/Livewire/ChatComponent.php

namespace App\Livewire;

use Anthropic\Anthropic;
use Livewire\Component;

class ChatComponent extends Component
{
    public array $messages = [];
    public string $input = '';
    public string $currentResponse = '';
    public bool $isStreaming = false;

    public function send(): void
    {
        $this->messages[] = ['role' => 'user', 'content' => $this->input];
        $userMsg = $this->input;
        $this->input = '';
        $this->isStreaming = true;
        $this->currentResponse = '';

        // Livewire v3 async method
        $this->streamResponse($userMsg);
    }

    public function streamResponse(string $userMsg): void
    {
        $client = app(Anthropic::class);
        $stream = $client->messages()->createStream([
            'model' => 'claude-sonnet-4-6',
            'max_tokens' => 1024,
            'messages' => $this->messages,
        ]);

        foreach ($stream as $event) {
            if ($event->type === 'content_block_delta' && isset($event->delta->text)) {
                $this->stream(
                    to: 'response',
                    content: $event->delta->text,
                    replace: false,
                );
                $this->currentResponse .= $event->delta->text;
            }
        }

        $this->messages[] = ['role' => 'assistant', 'content' => $this->currentResponse];
        $this->isStreaming = false;
    }

    public function render()
    {
        return view('livewire.chat');
    }
}
```

```blade
{{-- resources/views/livewire/chat.blade.php --}}
<div>
    @foreach ($messages as $msg)
        <div class="msg msg-{{ $msg['role'] }}">
            {{ $msg['content'] }}
        </div>
    @endforeach

    @if ($isStreaming)
        <div class="msg msg-assistant streaming">
            <span wire:stream="response">{{ $currentResponse }}</span>
            <span class="cursor">▊</span>
        </div>
    @endif

    <form wire:submit="send">
        <input type="text" wire:model="input" placeholder="Ask..." :disabled="$isStreaming">
        <button type="submit" :disabled="$isStreaming">Send</button>
        @if ($isStreaming)
            <button type="button" wire:click="stop">Stop</button>
        @endif
    </form>
</div>
```

---

## 7. Inertia.js + Vue + Streaming

Inertia özü SSE vermir, amma Vue client-də `fetch` streaming-i normal işləyir.

```vue
<!-- resources/js/Pages/Chat.vue -->
<script setup>
import { ref } from 'vue';

const messages = ref([]);
const input = ref('');
const currentResponse = ref('');
const isStreaming = ref(false);
const abortController = ref(null);

async function send() {
  if (isStreaming.value) return;

  const userMsg = input.value;
  messages.value.push({ role: 'user', content: userMsg });
  input.value = '';
  isStreaming.value = true;
  currentResponse.value = '';

  abortController.value = new AbortController();

  try {
    const resp = await fetch('/chat/stream', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
      },
      body: JSON.stringify({ messages: messages.value }),
      signal: abortController.value.signal,
    });

    const reader = resp.body.getReader();
    const decoder = new TextDecoder();
    let buffer = '';

    while (true) {
      const { done, value } = await reader.read();
      if (done) break;
      buffer += decoder.decode(value, { stream: true });

      const events = buffer.split('\n\n');
      buffer = events.pop();

      for (const raw of events) {
        const parsed = parseSSE(raw);
        if (parsed?.name === 'token') {
          currentResponse.value += parsed.data.text;
        } else if (parsed?.name === 'done') {
          messages.value.push({ role: 'assistant', content: currentResponse.value });
          currentResponse.value = '';
        }
      }
    }
  } catch (e) {
    if (e.name !== 'AbortError') {
      messages.value.push({ role: 'error', content: `Stream failed: ${e.message}` });
    }
  } finally {
    isStreaming.value = false;
    abortController.value = null;
  }
}

function stop() {
  abortController.value?.abort();
}

function parseSSE(block) {
  const lines = block.split('\n');
  let name = 'message', data = '';
  for (const l of lines) {
    if (l.startsWith('event:')) name = l.slice(6).trim();
    else if (l.startsWith('data:')) data += l.slice(5).trim();
  }
  try { return { name, data: JSON.parse(data) }; } catch { return null; }
}
</script>

<template>
  <div class="chat">
    <div v-for="(m, i) in messages" :key="i" :class="['msg', `msg-${m.role}`]">
      {{ m.content }}
    </div>
    <div v-if="isStreaming" class="msg msg-assistant streaming">
      {{ currentResponse }}<span class="cursor">▊</span>
    </div>
    <form @submit.prevent="send">
      <input v-model="input" :disabled="isStreaming" placeholder="Ask..." />
      <button type="submit" :disabled="isStreaming || !input">Send</button>
      <button v-if="isStreaming" type="button" @click="stop">Stop</button>
    </form>
  </div>
</template>
```

---

## 8. Next.js App Router + AI SDK Streaming

Vercel-in AI SDK (v4+) React Server Components ilə yanaşı işləyir.

```typescript
// app/api/chat/route.ts
import Anthropic from "@anthropic-ai/sdk";
import { AnthropicStream, StreamingTextResponse } from "ai";

const client = new Anthropic();

export async function POST(req: Request) {
  const { messages } = await req.json();

  const response = await client.messages.create({
    model: "claude-sonnet-4-6",
    max_tokens: 1024,
    stream: true,
    messages,
  });

  const stream = AnthropicStream(response);
  return new StreamingTextResponse(stream);
}
```

```tsx
// app/chat/page.tsx
"use client";

import { useChat } from "ai/react";

export default function ChatPage() {
  const { messages, input, handleInputChange, handleSubmit, isLoading, stop } =
    useChat({ api: "/api/chat" });

  return (
    <div className="chat">
      {messages.map((m) => (
        <div key={m.id} className={`msg msg-${m.role}`}>
          {m.content}
        </div>
      ))}

      <form onSubmit={handleSubmit}>
        <input
          value={input}
          onChange={handleInputChange}
          disabled={isLoading}
          placeholder="Ask..."
        />
        <button type="submit" disabled={isLoading || !input}>
          Send
        </button>
        {isLoading && (
          <button type="button" onClick={stop}>
            Stop
          </button>
        )}
      </form>
    </div>
  );
}
```

### RSC (React Server Component) + `streamUI`

```typescript
// app/actions.tsx
"use server";

import { streamUI } from "ai/rsc";
import { anthropic } from "@ai-sdk/anthropic";

export async function continueConversation(message: string) {
  const result = await streamUI({
    model: anthropic("claude-sonnet-4-6"),
    messages: [{ role: "user", content: message }],
    text: ({ content, done }) => (done ? <div>{content}</div> : <div className="streaming">{content}▊</div>),
  });

  return result.value;
}
```

RSC ilə server stream-ini birbaşa React element-lərə çevirmək olur — client JavaScript minimal.

---

## 9. Progress UI Pattern-ləri

### 9.1 Typing Indicator (TTFT-dən əvvəl)

İlk token gələnə qədər `...` və ya dot pulsation:

```html
<div class="typing-indicator">
  <span></span><span></span><span></span>
</div>
```

```css
.typing-indicator span {
  display: inline-block;
  width: 8px; height: 8px;
  border-radius: 50%;
  background: #888;
  animation: pulse 1.4s infinite;
}
.typing-indicator span:nth-child(2) { animation-delay: 0.2s; }
.typing-indicator span:nth-child(3) { animation-delay: 0.4s; }
@keyframes pulse {
  0%, 60%, 100% { opacity: 0.3; }
  30% { opacity: 1; }
}
```

### 9.2 Streaming Cursor (Block Cursor)

Stream zamanı son sözdən sonra yanıb-sönən blok:

```css
.cursor {
  display: inline-block;
  width: 8px;
  height: 1em;
  background: currentColor;
  animation: blink 1s infinite;
  vertical-align: text-bottom;
  margin-left: 2px;
}
@keyframes blink {
  50% { opacity: 0; }
}
```

### 9.3 Stop Button

İstifadəçi generasyani kəsə bilsin:

```javascript
const abortController = new AbortController();
fetch('/chat/stream', { signal: abortController.signal, ... });

// User klik etsə:
abortController.abort();
```

Server tərəfdə `connection_aborted()` və ya `res.on('close', ...)` ilə stream dayandır.

### 9.4 Retry Button

Stream mid-failure-da:

```javascript
if (error) {
  return (
    <div className="stream-error">
      <p>Stream failed: {error.message}</p>
      <button onClick={retry}>Retry</button>
    </div>
  );
}
```

### 9.5 Token Count Indicator

Stream davam edərkən tokens gəldikcə:

```vue
<span class="token-count">{{ tokenCount }} tokens · {{ elapsedMs }}ms</span>
```

### 9.6 Markdown Rendering Mid-Stream

Problem: stream zamanı markdown yarımçıq olur (`**bold` — closing `**` hələ gəlməyib). Həll:

```javascript
import { marked } from 'marked';
// Permissive: smartypants, breaks, gfm
marked.setOptions({ gfm: true, breaks: true });

// Render edəndə unclosed patterns-i məkkəyyən bağla (approximation)
function softRender(text) {
  const stars = (text.match(/\*\*/g) || []).length;
  if (stars % 2 === 1) text += '**';
  return marked.parse(text);
}
```

Və ya daha yaxşı: **streaming markdown parser** kitabxanası (`react-markdown` yanaşması — tamamlanmamış nodu silir).

---

## 10. Backpressure

Client yavaş oxuyur, server sürətlə yazır — TCP buffer dolur. Node.js-də bunu `.write()` qaytarma dəyəri ilə yoxlayın:

```typescript
// Express + Node stream
function writeEvent(res: Response, event: string, data: object): Promise<void> {
  const chunk = `event: ${event}\ndata: ${JSON.stringify(data)}\n\n`;
  return new Promise((resolve) => {
    if (!res.write(chunk)) {
      res.once('drain', resolve);
    } else {
      resolve();
    }
  });
}

for await (const ev of stream) {
  if (ev.type === 'content_block_delta') {
    await writeEvent(res, 'token', { text: ev.delta.text });
  }
}
```

PHP-də `flush()` sadəcə OS buffer-ə göndərir — backpressure OS səviyyəsində avtomatik işləyir (`fwrite` bloklanır).

---

## 11. Error Recovery Mid-Stream

### Server Tərəfdə

Stream mid-failure (Anthropic API timeout, context window overflow, content policy):

```php
try {
    foreach ($stream as $event) {
        // ...
    }
} catch (\Anthropic\Errors\APIError $e) {
    // Partial content artıq göndərilib — user bilməlidir
    $this->sse('error', [
        'message' => $this->humanize($e),
        'recoverable' => $this->isRetryable($e),
        'partial' => true,
    ]);
}

private function humanize(\Throwable $e): string
{
    return match (true) {
        str_contains($e->getMessage(), 'rate_limit') => 'Too many requests, please wait.',
        str_contains($e->getMessage(), 'context') => 'Conversation too long. Start a new one.',
        str_contains($e->getMessage(), 'overloaded') => 'Model is busy, try again.',
        default => 'Stream error.',
    };
}
```

### Client Tərəfdə Retry

Partial cavab + error → istifadəçiyə göstər + retry təklif et:

```javascript
if (streamError) {
  messages.push({
    role: 'assistant',
    content: currentResponse + ' [stream interrupted]',
    error: true,
    canRetry: streamError.recoverable,
  });
}
```

Retry zamanı mövcud partial-ı silib, original user mesajını yenidən göndər.

---

## 12. Reconnect Logic

Network drop / page navigate-dən sonra ongoing generation-ı bərpa etmək.

Bu nontrivial-dır — SSE auto-reconnect standard olsa da, Anthropic stream stateless-dir (server stream-i hafizəda saxlamır). İki seçim:

### Seçim A: Ignore Reconnect — Sadə

Disconnect olarsa, yenidən göndər. UX: "connection lost, please try again." Əksər chat app-lər bunu edir.

### Seçim B: Persist Partial Response

```php
// Stream zamanı hər N token-da DB-ə save et
if ($tokenCount % 50 === 0) {
    StreamSession::updateOrCreate(
        ['session_id' => $sessionId],
        ['partial' => $accumulated, 'status' => 'streaming']
    );
}
```

Client reconnect edəndə:

```php
Route::get('/chat/stream/{session}/resume', function ($sessionId) {
    $s = StreamSession::find($sessionId);
    return response()->json(['partial' => $s->partial, 'status' => $s->status]);
});
```

Amma generasyon özünü davam etdirə bilmirsiniz — Anthropic API-də mid-stream resume yoxdur. Yalnız artıq generate olunmuş hissəni UI-da bərpa edə bilərsiniz. Yeni generasyon yenidən başlamalıdır.

---

## 13. Token-by-Token vs Chunk vs Event-Based

| Pattern | Server work | Client UX | Use case |
|---------|-------------|-----------|----------|
| **Token-by-token** | Hər tokeni flush et | Ən smooth, real-time | Chat, writing assistant |
| **Chunk (word-level)** | Space-də buffer | 100-200 ms latency əlavə | Sadə UI |
| **Event-based** | Semantic unit (sentence, section) | Yavaş, struktur | Multi-step reasoning, tool use display |

Anthropic SDK `content_block_delta` event-ləri subword (token) səviyyəsindədir. Əgər istəyirsinizsə chunk-lamaq:

```php
$buffer = '';
foreach ($stream as $event) {
    if ($event->type === 'content_block_delta') {
        $buffer .= $event->delta->text;
        // Sözün sonunda flush et
        if (preg_match('/\s$/u', $buffer)) {
            $this->sse('chunk', ['text' => $buffer]);
            $buffer = '';
        }
    }
}
if ($buffer) $this->sse('chunk', ['text' => $buffer]);
```

---

## 14. Tool Use Mid-Stream

Agent zamanı tool çağırışı stream ilə qarışıqdır. `content_block_delta` tipi `input_json_delta`-dır (tokens), `tool_use` block-u tam formalaşandan sonra icra olunur.

```php
$toolUseBuffer = [];
foreach ($stream as $event) {
    match ($event->type) {
        'content_block_start' => $this->handleBlockStart($event),
        'content_block_delta' => match ($event->delta->type ?? '') {
            'text_delta' => $this->sse('token', ['text' => $event->delta->text]),
            'input_json_delta' => $this->sse('tool_progress', [
                'index' => $event->index,
                'partial' => $event->delta->partial_json,
            ]),
            default => null,
        },
        'content_block_stop' => $this->handleBlockStop($event),
        default => null,
    };
}
```

UI-də iki fərqli zona göstərilir: text stream + tool use panel (spinner + tool adı).

---

## 15. Müsahibə Xülasəsi

- **SSE** LLM streaming üçün defacto standartdır: HTTP-based, proxy-friendly, browser auto-reconnect. WebSocket overkill.
- **Content-Type `text/event-stream`** + `X-Accel-Buffering: no` (nginx), PHP-də `output_buffering = Off`.
- **Anthropic event tipləri**: `content_block_delta` (tokens), `message_delta` (final usage), `message_stop`.
- **Laravel**: `StreamedResponse` + `echo; ob_flush(); flush();`. Octane-də uzun connection-lar üçün `read_timeout` artır.
- **Client parsing**: `EventSource` yalnız GET; POST body üçün `fetch` + manual SSE parse.
- **Livewire 3** `wire:stream` native dəstək; Inertia/Vue fetch stream; Next.js AI SDK `useChat` hook + `streamUI` RSC.
- **UI pattern-ləri**: typing indicator (TTFT-dən əvvəl), streaming cursor, stop button (AbortController), retry.
- **Markdown mid-stream**: unclosed patterns-i yumşaq close et və ya streaming-aware parser (`react-markdown`).
- **Backpressure**: Node-da `.write()` qaytarma + `drain` event; PHP-də OS buffer avtomatik.
- **Error mid-stream**: partial cavabı göstər, recoverable flag, retry option.
- **Reconnect**: Anthropic API stateless — partial-ı DB-də saxlamaq olur, amma generation resume deyil, sadəcə UI bərpası.
- **Token vs chunk vs event** granularity use-case-dən asılıdır — chat üçün token, multi-step üçün event.
- **Tool use stream**: `input_json_delta` tool args-ı token-by-token streamlə; UI tool panel-də progress göstər.
