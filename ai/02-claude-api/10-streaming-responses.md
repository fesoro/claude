# Claude API ilə Streaming Cavablar

## Streaming Nədir və Niyə Vacibdir

Claude API-ni streaming olmadan çağırdığınızda, bütün cavab Anthropic serverlərində tam generasiya edilir və sizə yalnız hamısı hazır olduqdan sonra tək bir HTTP cavabı kimi göndərilir. 2000 tokenlik cavab üçün saniyədə təxminən 60 token sürətini nəzərə alsaq, bu o deməkdir ki, istifadəçiləriniz boş ekrana baxaraq 30+ saniyə gözləyir.

Streaming çatdırılma modelini dəyişir: Claude tokenləri generasiya etdikcə sizə göndərir, siz isə onları real vaxtda istifadəçiyə ötürürsünüz. İstifadəçi ilk sözü bir saniyədən az müddətdə görür və cavabın tədricən göründüyünü izləyir — eyni Claude.ai-dakı təcrübə kimi.

Bu yalnız UX fəndindən ibarət deyil. Streaming arxitektura baxımından da mühüm əhəmiyyət daşıyır:

- **Qəbul edilən gecikmə azalır** — ilk tokenə qədər vaxt (TTFT) 30 saniyədən <1 saniyəyə düşür.
- **İstifadəçilər erkən ləğv edə bilər** — əgər ilk abzas sualı cavablandırırsa, oxumağı dayandırır və server generasiyanı dayandırır, xərc azalır.
- **Yaddaş təzyiqi azalır** — serveriniz 100 KB-lıq JSON blobu heç vaxt bufferə almır; baytlar ötürülür.
- **Xəta görünürlüyü yaxşılaşır** — məzmun siyasəti pozuntusu tam cavabı gözləmədən 5-ci tokendə aşkar edilir.

---

## HTTP Səviyyəsindəki Mexanika: Server-Sent Events

Streaming **Server-Sent Events (SSE)** istifadə edir — bu, davamlı HTTP/1.1 və ya HTTP/2 bağlantısı üzərindən birtərəfli server-istemci push üçün W3C standartıdır.

### Nəqletmə Formatı

SSE cavabının başlıqları:

```
Content-Type: text/event-stream
Cache-Control: no-cache
Connection: keep-alive
```

Nəqletmə zamanı hər event belə görünür:

```
event: content_block_delta\n
data: {"type":"content_block_delta","index":0,"delta":{"type":"text_delta","text":"Hello"}}\n
\n
```

Qaydalar:
- Hər sətir `field: value\n` formatındadır.
- Boş sətir `\n\n` bir eventi bitirir.
- `data:` sətrləri bir event daxilində təkrarlana bilər (çoxsətirli data).
- `event:` sahəsi event tipini adlandırır; istemcilər ona görə filtrlayır.
- `id:` sahəsi `Last-Event-ID` başlığı ilə yenidən qoşulmağa imkan verir.

Brauzerin `EventSource` API-si bütün bu parsingi öz-özünə idarə edir. Server tərəfində (brauzerlə relay edən Laravel tətbiqiniz) eyni formatı əl ilə yaradırsınız.

### Anthropic-in SSE-dən İstifadəsi

Anthropic-in streaming endpointi, streaming olmayan endpointlə eynidir (`POST /v1/messages`) — sadəcə sorğu gövdəsinə `"stream": true` əlavə edirsiniz. Server `Content-Type: text/event-stream` ilə cavab verir və eventləri emit etməyə başlayır.

---

## Stream Event Tipləri

Claude-un streaming protokolu dəqiq ardıcıllıqla tipli eventlər emit edir. Bunları anlamaq möhkəm idarəetmə üçün vacibdir.

### Tam Event Ardıcıllığı

```
message_start
  content_block_start   (index 0, type="text")
    content_block_delta (text_delta)
    content_block_delta (text_delta)
    ... (hər token üçün təkrarlayır)
  content_block_stop    (index 0)
  content_block_start   (index 1, type="tool_use")  ← yalnız tool istifadə olunarsa
    content_block_delta (input_json_delta)
    ...
  content_block_stop    (index 1)
message_delta           (stop_reason, usage)
message_stop
ping                    (ürək döyüşü, nəzərə almayın)
```

### Event Payload-ları

#### `message_start`
```json
{
  "type": "message_start",
  "message": {
    "id": "msg_01XFDUDYJgAACzvnptvVoYEL",
    "type": "message",
    "role": "assistant",
    "content": [],
    "model": "claude-opus-4-5",
    "stop_reason": null,
    "stop_sequence": null,
    "usage": { "input_tokens": 25, "output_tokens": 1 }
  }
}
```
Bu sizə mesaj ID-sini və ilkin token istifadəsini verir. Loglama üçün `id`-ni cache edin.

#### `content_block_start`
```json
{
  "type": "content_block_start",
  "index": 0,
  "content_block": { "type": "text", "text": "" }
}
```
`index` blokunun başladığını bildirir. Əgər `type` `"tool_use"`-dursa, Claude tool çağırır; `id` və `name`-i yadda saxlayın.

#### `content_block_delta`
```json
{
  "type": "content_block_delta",
  "index": 0,
  "delta": { "type": "text_delta", "text": "Hello, how" }
}
```
Ən çox istifadə olunan yol — hər token üçün gəlir. `delta.text` əlavə ediləcək artan mətndir. Tool çağırışları üçün `delta.type` `"input_json_delta"`-dır və `delta.partial_json` JSON input mətninin bir parçasını ehtiva edir.

#### `message_delta`
```json
{
  "type": "message_delta",
  "delta": { "stop_reason": "end_turn", "stop_sequence": null },
  "usage": { "output_tokens": 437 }
}
```
Yekun hesabat. `stop_reason` dəyərləri: `"end_turn"`, `"max_tokens"`, `"stop_sequence"`, `"tool_use"`.

#### `message_stop`
```json
{ "type": "message_stop" }
```
Stream tamamlandı. Bağlantını bağlayın.

---

## Arxitektura: Üç Təbəqəli Streaming

```
Brauzer (EventSource)
     ↑  SSE  (text/event-stream)
Laravel Controller  ← streaming cavab gövdəsi
     ↑  HTTP chunked transfer / streaming
Anthropic API       ← stream=true
```

Laravel tətbiqiniz **SSE relay** kimi çıxış edir: Anthropic-ə streaming bağlantısı açır, eventləri parse edir və onları (və ya çevrilmiş alt çoxluğu) SSE kimi brauzərə yenidən ötürür.

Alternativ olaraq, brauzer CORS-aktivləşdirilmiş API açarı ilə Anthropic-ə birbaşa müraciət edə bilər — lakin bu sizin açarınızı ifşa edir. Həmişə backend vasitəsilə proxy edin.

---

## Laravel İmplementasiyası

### 1. StreamingService — Guzzle HTTP Streaming

```php
<?php

declare(strict_types=1);

namespace App\Services\AI;

use Generator;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Anthropic Messages API-dən cavabları stream edir.
 *
 * Guzzle-nin sink / streaming body xüsusiyyətini istifadə edir ki,
 * cavab gövdəsi heç vaxt tam olaraq yaddaşa alınmasın — baytlar gəlir
 * və artan şəkildə işlənir.
 */
final class StreamingService
{
    private readonly Client $http;

    public function __construct(
        private readonly string $apiKey = '',
        private readonly string $model = 'claude-opus-4-5',
        private readonly string $baseUri = 'https://api.anthropic.com/v1/',
    ) {
        $this->http = new Client([
            'base_uri' => $this->baseUri,
            'headers' => [
                'x-api-key'         => $this->apiKey ?: config('services.anthropic.key'),
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
                'accept'            => 'text/event-stream',
            ],
            // Vacib: burada read timeout təyin etməyin; streamlər uzunömürlüdür.
            'timeout'         => 0,
            'connect_timeout' => 10,
        ]);
    }

    /**
     * Söhbəti stream edin və parse edilmiş SSE event massivlərini yield edin.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $options  Əlavə API parametrləri (max_tokens, system, tools…)
     * @return Generator<int, array<string, mixed>>
     *
     * @throws RuntimeException HTTP və ya protokol xətasında
     */
    public function stream(array $messages, array $options = []): Generator
    {
        $payload = array_merge([
            'model'      => $this->model,
            'max_tokens' => 4096,
            'stream'     => true,
            'messages'   => $messages,
        ], $options);

        try {
            $response = $this->http->post('messages', [
                'json'   => $payload,
                // stream=true Guzzle-ə gövdəni bufferə almamasını bildirir
                'stream' => true,
            ]);
        } catch (RequestException $e) {
            $body = $e->getResponse()?->getBody()->getContents() ?? '';
            Log::error('Anthropic stream sorğusu uğursuz oldu', [
                'status' => $e->getResponse()?->getStatusCode(),
                'body'   => $body,
            ]);
            throw new RuntimeException("Anthropic API xətası: {$body}", previous: $e);
        }

        $body   = $response->getBody();
        $buffer = '';

        while (! $body->eof()) {
            // Hər dəfə 1 KB oxuyun — yaddaşı sabit saxlayır
            $chunk  = $body->read(1024);
            $buffer .= $chunk;

            // SSE eventləri ikiqat yeni sətirlərlə ayrılır
            while (($pos = strpos($buffer, "\n\n")) !== false) {
                $rawEvent = substr($buffer, 0, $pos);
                $buffer   = substr($buffer, $pos + 2);

                $parsed = $this->parseEvent($rawEvent);
                if ($parsed !== null) {
                    yield $parsed;
                }
            }
        }
    }

    /**
     * Yüksək səviyyəli köməkçi: yalnız text delta-larını sadə mətn kimi yield edin.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return Generator<int, string>
     */
    public function streamText(array $messages, array $options = []): Generator
    {
        foreach ($this->stream($messages, $options) as $event) {
            if (
                $event['type'] === 'content_block_delta'
                && ($event['delta']['type'] ?? '') === 'text_delta'
            ) {
                yield $event['delta']['text'];
            }
        }
    }

    /**
     * Bir xam SSE blokunu (sətirləri \n ilə ayrılmış) massivə parse edir.
     * Şərh sətirləri və ya naməlum sahələr üçün null qaytarır.
     *
     * @return array<string, mixed>|null
     */
    private function parseEvent(string $raw): ?array
    {
        $data = null;

        foreach (explode("\n", trim($raw)) as $line) {
            if (str_starts_with($line, 'data: ')) {
                $json = substr($line, 6);

                if ($json === '[DONE]') {
                    return ['type' => 'done'];
                }

                $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
            }
        }

        return $data;
    }
}
```

### 2. Laravel Controller — Brauzerlə SSE Relay

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\AI;

use App\Services\AI\StreamingService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

final class StreamChatController
{
    public function __construct(
        private readonly StreamingService $streaming,
    ) {}

    /**
     * POST /api/chat/stream
     *
     * Qəbul edir: { "messages": [...], "system": "..." }
     * Qaytarır: text/event-stream
     */
    public function __invoke(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'messages'        => ['required', 'array', 'min:1'],
            'messages.*.role' => ['required', 'in:user,assistant'],
            'messages.*.content' => ['required', 'string', 'max:100000'],
            'system'          => ['sometimes', 'string', 'max:10000'],
        ]);

        return response()->stream(
            callback: function () use ($validated): void {
                $this->streamToClient($validated);
            },
            status: 200,
            headers: [
                'Content-Type'                     => 'text/event-stream',
                'Cache-Control'                    => 'no-cache, no-store',
                'X-Accel-Buffering'                => 'no',   // Nginx bufferini deaktiv et
                'Access-Control-Allow-Origin'      => '*',
                'Access-Control-Allow-Credentials' => 'true',
            ],
        );
    }

    private function streamToClient(array $validated): void
    {
        // PHP output bufferingi tamamilə deaktiv et
        if (ob_get_level() > 0) {
            ob_end_flush();
        }

        $options = [];
        if (isset($validated['system'])) {
            $options['system'] = $validated['system'];
        }

        try {
            foreach ($this->streaming->stream($validated['messages'], $options) as $event) {
                $this->emitSseEvent($event['type'], $event);

                // Dərhal şəbəkəyə göndər — SSE üçün kritikdir
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();

                // İstemci bağlantını kəsibsə dayandır
                if (connection_aborted()) {
                    return;
                }
            }

            // JS istemcisinə tamamlanma siqnalı göndər
            $this->emitSseEvent('done', ['type' => 'done']);
        } catch (Throwable $e) {
            $this->emitSseEvent('error', [
                'type'    => 'error',
                'message' => $e->getMessage(),
            ]);
            flush();
        }
    }

    /**
     * Stdout-a tək SSE event yaz.
     *
     * @param  array<string, mixed>  $data
     */
    private function emitSseEvent(string $eventName, array $data): void
    {
        echo "event: {$eventName}\n";
        echo 'data: ' . json_encode($data) . "\n";
        echo "\n"; // boş sətir eventi bitirir
    }
}
```

`routes/api.php` faylına route əlavə edin:

```php
Route::post('/chat/stream', App\Http\Controllers\AI\StreamChatController::class)
    ->middleware(['auth:sanctum', 'throttle:60,1']);
```

**Nginx konfiqurasiya qeydi** — `X-Accel-Buffering: no` olmadan Nginx bütün cavabı ötürməzdən əvvəl bufferə alır, streaming effektini məhv edir:

```nginx
location /api/chat/stream {
    proxy_pass         http://php-fpm;
    proxy_buffering    off;
    proxy_cache        off;
    proxy_read_timeout 300s;
}
```

### 3. Livewire Komponenti — Real Vaxtlı AI Cavabı

Bu komponent SSE endpointini istehlak etmək və mətni `$wire` vasitəsilə Livewire property-ə push etmək üçün Alpine.js-in `fetch` + `ReadableStream`-dən istifadə edir.

```php
<?php

declare(strict_types=1);

namespace App\Livewire;

use Livewire\Component;

final class AiChat extends Component
{
    public string $userInput   = '';
    public string $aiResponse  = '';
    public bool   $isStreaming = false;
    public string $error       = '';

    /** @var array<int, array{role: string, content: string}> */
    public array $messages = [];

    public function sendMessage(): void
    {
        $this->validate(['userInput' => 'required|string|max:10000']);

        $this->messages[] = ['role' => 'user', 'content' => $this->userInput];
        $this->aiResponse  = '';
        $this->error       = '';
        $this->isStreaming  = true;
        $this->userInput   = '';

        // Əsl streaming JS-də idarə olunur (Blade şablonuna bax).
        // Alpine-ın tutduğu brauzer eventi dispatch edirik.
        $this->dispatch('start-stream', messages: $this->messages);
    }

    /**
     * Delta gəldikdə Alpine tərəfindən $wire.appendToken(token) ilə çağırılır.
     */
    public function appendToken(string $token): void
    {
        $this->aiResponse .= $token;
    }

    /**
     * Tamamlandıqda Alpine tərəfindən $wire.finishStream(fullText) ilə çağırılır.
     */
    public function finishStream(string $fullText): void
    {
        $this->messages[]  = ['role' => 'assistant', 'content' => $fullText];
        $this->isStreaming  = false;
    }

    /**
     * Xəta zamanı Alpine tərəfindən çağırılır.
     */
    public function handleStreamError(string $message): void
    {
        $this->error      = $message;
        $this->isStreaming = false;
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.ai-chat');
    }
}
```

```blade
{{-- resources/views/livewire/ai-chat.blade.php --}}
<div
    x-data="aiChatStream()"
    x-on:start-stream.window="startStream($event.detail.messages)"
>
    {{-- Söhbət tarixi --}}
    <div class="space-y-4 mb-6" id="chat-history">
        @foreach ($messages as $msg)
            <div class="flex {{ $msg['role'] === 'user' ? 'justify-end' : 'justify-start' }}">
                <div class="max-w-2xl px-4 py-2 rounded-lg
                    {{ $msg['role'] === 'user'
                        ? 'bg-blue-600 text-white'
                        : 'bg-gray-100 text-gray-900' }}">
                    {{ $msg['content'] }}
                </div>
            </div>
        @endforeach

        {{-- Canlı streaming cavabı --}}
        @if ($isStreaming || $aiResponse)
            <div class="flex justify-start">
                <div class="max-w-2xl px-4 py-2 rounded-lg bg-gray-100 text-gray-900">
                    {{ $aiResponse }}
                    @if ($isStreaming)
                        <span class="inline-block w-2 h-4 bg-gray-500 animate-pulse ml-1"></span>
                    @endif
                </div>
            </div>
        @endif
    </div>

    {{-- Xəta göstəricisi --}}
    @if ($error)
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-4">
            {{ $error }}
        </div>
    @endif

    {{-- Giriş forması --}}
    <form wire:submit="sendMessage">
        <div class="flex gap-2">
            <input
                type="text"
                wire:model="userInput"
                placeholder="Claude-a nəsə soruşun…"
                :disabled="$wire.isStreaming"
                class="flex-1 border rounded-lg px-4 py-2 focus:outline-none focus:ring-2"
            />
            <button
                type="submit"
                :disabled="$wire.isStreaming"
                class="px-6 py-2 bg-blue-600 text-white rounded-lg disabled:opacity-50"
            >
                <span x-show="!$wire.isStreaming">Göndər</span>
                <span x-show="$wire.isStreaming">Streaming…</span>
            </button>
        </div>
    </form>
</div>

<script>
function aiChatStream() {
    return {
        controller: null,

        async startStream(messages) {
            // Əvvəlki streami ləğv et
            if (this.controller) {
                this.controller.abort();
            }
            this.controller = new AbortController();

            let accumulated = '';

            try {
                const response = await fetch('/api/chat/stream', {
                    method: 'POST',
                    headers: {
                        'Content-Type':  'application/json',
                        'Accept':        'text/event-stream',
                        'X-CSRF-TOKEN':  document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ messages }),
                    signal: this.controller.signal,
                });

                if (!response.ok) {
                    const err = await response.json().catch(() => ({}));
                    await $wire.handleStreamError(err.message ?? `HTTP ${response.status}`);
                    return;
                }

                const reader   = response.body.getReader();
                const decoder  = new TextDecoder();
                let   sseBuffer = '';

                while (true) {
                    const { done, value } = await reader.read();
                    if (done) break;

                    sseBuffer += decoder.decode(value, { stream: true });

                    // İkiqat yeni sətirlərlə böl — SSE event sərhədləri
                    const parts = sseBuffer.split('\n\n');
                    sseBuffer   = parts.pop(); // natamam quyruq

                    for (const part of parts) {
                        const eventMatch = part.match(/^event:\s*(.+)$/m);
                        const dataMatch  = part.match(/^data:\s*(.+)$/m);
                        if (!dataMatch) continue;

                        const eventName = eventMatch?.[1] ?? 'message';
                        const payload   = JSON.parse(dataMatch[1]);

                        if (eventName === 'content_block_delta'
                            && payload?.delta?.type === 'text_delta') {
                            const token = payload.delta.text;
                            accumulated += token;
                            await $wire.appendToken(token);
                        }

                        if (eventName === 'done' || payload?.type === 'message_stop') {
                            await $wire.finishStream(accumulated);
                            return;
                        }

                        if (eventName === 'error') {
                            await $wire.handleStreamError(payload.message ?? 'Stream xətası');
                            return;
                        }
                    }
                }

                // Açıq message_stop olmadan stream sonu
                await $wire.finishStream(accumulated);

            } catch (err) {
                if (err.name !== 'AbortError') {
                    await $wire.handleStreamError(err.message);
                }
            }
        }
    };
}
</script>
```

### 4. Xəta İdarəetməsi

Streaming xətaları iki kateqoriyaya bölünür: **stream öncəsi** (hər hansı data gəlməzdən əvvəl HTTP 4xx/5xx) və **stream ortasında** (bağlantı kəsilməsi, timeout, cavab ortasında Anthropic xəta eventi).

```php
<?php

declare(strict_types=1);

namespace App\Services\AI;

use Generator;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * StreamingService-i yenidən cəhd məntiqi və strukturlu xəta eventləri ilə bürüyür.
 */
final class ResilientStreamingService
{
    public function __construct(
        private readonly StreamingService $inner,
        private readonly int $maxRetries = 2,
    ) {}

    /**
     * Keçici şəbəkə xətalarında avtomatik yenidən cəhd ilə stream et.
     * Normal eventləri və ya sintetik xəta eventini yield edir.
     *
     * @return Generator<int, array<string, mixed>>
     */
    public function stream(array $messages, array $options = []): Generator
    {
        $attempt = 0;

        retry:
        $attempt++;

        try {
            yield from $this->inner->stream($messages, $options);
            return;
        } catch (ConnectException $e) {
            // Şəbəkə səviyyəsindəki uğursuzluq — yenidən cəhd etmək təhlükəsizdir
            if ($attempt <= $this->maxRetries) {
                Log::warning("Stream qoşulması uğursuz oldu (cəhd {$attempt}), yenidən cəhd edilir…");
                usleep(500_000 * $attempt); // 0.5 s, 1 s geri çəkilmə
                goto retry;
            }

            yield $this->errorEvent('connection_failed', $e->getMessage());
        } catch (RequestException $e) {
            $status = $e->getResponse()?->getStatusCode() ?? 0;

            // 529 = Anthropic həddindən artıq yüklənib — yenidən cəhd edilə bilər
            if ($status === 529 && $attempt <= $this->maxRetries) {
                Log::warning("Anthropic həddindən artıq yüklənib (cəhd {$attempt}), yenidən cəhd edilir…");
                sleep(2 ** $attempt);
                goto retry;
            }

            // 401 / 403 — yenidən cəhd edilə bilməz, dərhal göstər
            yield $this->errorEvent('api_error', "HTTP {$status}: " . $e->getMessage());
        } catch (\Throwable $e) {
            yield $this->errorEvent('unexpected_error', $e->getMessage());
        }
    }

    /** @return array{type: string, error_type: string, message: string} */
    private function errorEvent(string $errorType, string $message): array
    {
        Log::error("AI stream xətası: {$errorType}", ['message' => $message]);

        return [
            'type'       => 'error',
            'error_type' => $errorType,
            'message'    => $message,
        ];
    }
}
```

**Aktiv stream ortasında `overloaded_error` eventini idarə etmək** — Anthropic aktiv stream daxilinde də xəta eventi inject edə bilər:

```php
// streamToClient() içindəki event dövrənizdə:
foreach ($streaming->stream($messages) as $event) {
    if ($event['type'] === 'error') {
        // Anthropic stream ortasında xəta eventi göndərdi
        $errorType = $event['error']['type'] ?? 'unknown';

        match ($errorType) {
            'overloaded_error' => $this->emitSseEvent('error', [
                'message'   => 'Claude hal-hazırda həddindən artıq yüklənib. Zəhmət olmasa yenidən cəhd edin.',
                'retryable' => true,
            ]),
            'invalid_request_error' => $this->emitSseEvent('error', [
                'message'   => 'Yanlış sorğu: ' . ($event['error']['message'] ?? ''),
                'retryable' => false,
            ]),
            default => $this->emitSseEvent('error', [
                'message'   => $event['error']['message'] ?? 'Naməlum xəta',
                'retryable' => false,
            ]),
        };

        return;
    }

    $this->emitSseEvent($event['type'], $event);
    flush();
}
```

---

## Tool İstifadəsi üçün Token Yığımı

Claude streaming zamanı tool çağıranda, delta-lar qismən JSON kimi gəlir. Parse etməzdən əvvəl onları yığmaq lazımdır:

```php
<?php

declare(strict_types=1);

namespace App\Services\AI;

final class StreamAccumulator
{
    /** @var array<int, array{type: string, text?: string, id?: string, name?: string, input?: string}> */
    private array $blocks = [];

    private int $currentIndex = -1;

    public function processEvent(array $event): void
    {
        match ($event['type']) {
            'content_block_start' => $this->startBlock($event),
            'content_block_delta' => $this->applyDelta($event),
            'content_block_stop'  => $this->finalizeBlock($event),
            default               => null,
        };
    }

    private function startBlock(array $event): void
    {
        $this->currentIndex = $event['index'];
        $block = $event['content_block'];

        $this->blocks[$this->currentIndex] = match ($block['type']) {
            'text'     => ['type' => 'text', 'text' => ''],
            'tool_use' => ['type' => 'tool_use', 'id' => $block['id'], 'name' => $block['name'], 'input' => ''],
            default    => $block,
        };
    }

    private function applyDelta(array $event): void
    {
        $delta = $event['delta'];
        $idx   = $event['index'];

        match ($delta['type']) {
            'text_delta'       => $this->blocks[$idx]['text']  .= $delta['text'],
            'input_json_delta' => $this->blocks[$idx]['input'] .= $delta['partial_json'],
            default            => null,
        };
    }

    private function finalizeBlock(array $event): void
    {
        $idx = $event['index'];

        // Yığılmış tool input JSON-ını parse et
        if (($this->blocks[$idx]['type'] ?? '') === 'tool_use') {
            $raw = $this->blocks[$idx]['input'] ?? '{}';
            $this->blocks[$idx]['input'] = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getBlocks(): array
    {
        return array_values($this->blocks);
    }

    public function getFullText(): string
    {
        return implode('', array_map(
            fn (array $b) => $b['type'] === 'text' ? ($b['text'] ?? '') : '',
            $this->blocks,
        ));
    }
}
```

---

## Performans Məsələləri

| Problem | Tövsiyə |
|---|---|
| PHP output buffering | Streaming öncəsi `ob_end_flush()` ilə deaktiv edin |
| Nginx buffering | `proxy_buffering off` təyin edin və ya `X-Accel-Buffering: no` başlığı göndərin |
| PHP-FPM timeout | Uzun cavablar üçün `request_terminate_timeout = 300` təyin edin |
| Laravel cavab middleware | Heç bir middleware cavabı bufferə almasın (məs. `GzipMiddleware`) |
| Bağlantı havuzu | Guzzle istemci nümunəsini yenidən istifadə edin (ServiceProvider-da singleton binding) |
| Yaddaş | Guzzle streaming heç vaxt tam gövdəni yükləmir — ~1 KB buffer-də qalır |

### ServiceProvider Binding

```php
// app/Providers/AppServiceProvider.php
$this->app->singleton(StreamingService::class, fn () => new StreamingService(
    apiKey: config('services.anthropic.key'),
    model:  config('services.anthropic.model', 'claude-opus-4-5'),
));

$this->app->singleton(ResilientStreamingService::class, fn (Application $app) =>
    new ResilientStreamingService($app->make(StreamingService::class))
);
```

---

## API-yə Müraciət Etmədən Streaming Testi

```php
<?php

namespace Tests\Unit\Services\AI;

use App\Services\AI\StreamingService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use Tests\TestCase;

class StreamingServiceTest extends TestCase
{
    private function makeSseBody(): string
    {
        $events = [
            ['type' => 'message_start', 'message' => ['id' => 'msg_1', 'usage' => ['input_tokens' => 10, 'output_tokens' => 1]]],
            ['type' => 'content_block_start', 'index' => 0, 'content_block' => ['type' => 'text', 'text' => '']],
            ['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'text_delta', 'text' => 'Hello']],
            ['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'text_delta', 'text' => ' world']],
            ['type' => 'content_block_stop', 'index' => 0],
            ['type' => 'message_delta', 'delta' => ['stop_reason' => 'end_turn'], 'usage' => ['output_tokens' => 2]],
            ['type' => 'message_stop'],
        ];

        return implode('', array_map(
            fn ($e) => "event: {$e['type']}\ndata: " . json_encode($e) . "\n\n",
            $events,
        ));
    }

    public function test_stream_yields_text_deltas(): void
    {
        $mock    = new MockHandler([
            new Response(200, ['Content-Type' => 'text/event-stream'], Utils::streamFor($this->makeSseBody())),
        ]);
        $handler = HandlerStack::create($mock);

        // Mock istemciyi reflection vasitəsilə inject et (istehsalda inject edilə bilən edin)
        $service = new StreamingService();
        $ref     = new \ReflectionProperty($service, 'http');
        $ref->setAccessible(true);
        $ref->setValue($service, new Client(['handler' => $handler]));

        $tokens = iterator_to_array($service->streamText([
            ['role' => 'user', 'content' => 'Hi'],
        ]));

        $this->assertSame(['Hello', ' world'], $tokens);
    }
}
```
