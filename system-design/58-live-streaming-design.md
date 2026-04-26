# Live Streaming Design (Senior)

## İcmal

Live streaming sistemi bir streamer-in video/audio yayımını milyonlarla izləyiciyə
minimum gecikmə ilə çatdıran arxitekturadır. VOD-dan fərqli olaraq content real vaxtda
yaradılır — ingest server-ə gəlir, transcode olunur, CDN üzərindən fan-out edilir və
viewer-in player-inə çatır. Twitch, YouTube Live, Kick, TikTok Live bu modelə nümunədir.

Sadə dillə: canlı TV yayımı kimi — amma internetlə, minlərlə streamer eyni anda yayım
edir, milyonlarla insan izləyir, hər kəs real vaxtda chat-də yazır. Hər saniyə
maksimum 2-5 saniyə gecikmə ilə çatmalıdır (VOD-da "rewind" yoxdur).

Bu fayl LIVE streaming-ə fokuslanır (ingest, transcode, low-latency delivery, chat).
VOD/HLS packaging barədə ətraflı `23-video-streaming-design.md` faylına bax.

```
Streamer (OBS)                                      Viewer (Browser/Mobile)
     │                                                      ▲
     │ RTMP/SRT/WebRTC                                      │ HLS/LL-HLS/WebRTC
     ▼                                                      │
┌──────────┐   ┌───────────┐   ┌────────┐   ┌──────┐   ┌────────┐
│  Ingest  │──▶│ Transcoder│──▶│ Origin │──▶│ CDN  │──▶│  Edge  │
│  Server  │   │  (GPU)    │   │ Server │   │ POPs │   │ Cache  │
└──────────┘   └───────────┘   └────────┘   └──────┘   └────────┘
      │               │              │
      ▼               ▼              ▼
  Stream Key     1080p/720p      Segment   ──▶ VOD Pipeline (S3)
  Validation     480p/360p       Storage
                 (ABR)
```


## Niyə Vacibdir

Live stream real-time ingest, transcoding, CDN distribution, low latency delivery kimi bir neçə çətin mövzunu birləşdirir. Twitch/YouTube Live-ın arxitekturası miqyaslı real-time sistem dizaynının əla nümunəsidir. LL-HLS, WebRTC, RTMP — protokol seçimi latency vs compatibility trade-off-unu müəyyən edir.

## Tələblər

### Functional

- Streamer RTMP/SRT/WebRTC ilə yayım başlada bilsin
- Milyonlarla viewer eyni anda izləyə bilsin, latency < 5s (LL-HLS < 2s)
- Adaptive bitrate: 1080p/720p/480p/360p avtomatik
- Real-time chat (hər channel üçün)
- Stream discovery (trending, category, search)
- Yayım bitdikdə avtomatik VOD yaradılması
- Stream key ilə authentication, moderation tools

### Non-Functional

- End-to-end latency: 2-5s (normal), < 1s (WebRTC mode)
- Availability: 99.95%, Concurrent viewers: 10M peak
- Concurrent streams: 100k aktiv streamer
- Chat: 1M messages/sec peak, multi-region CDN

### Capacity Estimation

```
Concurrent viewers: 10M × 3 Mbps = 30 Tbps peak egress → CDN məcburidir
Concurrent streamers: 100k × 6 Mbps = 600 Gbps ingest → multi-PoP lazımdır

Transcoding:
  100k stream × 4 rendition = 400k transcode job
  1 GPU ~ 20 stream (NVENC) → 5000 GPU lazımdır

Storage (VOD):
  Orta yayım: 4 saat × 6 Mbps = ~10 GB/stream
  Günlük: 100k × 10 GB = 1 PB/day
  30 gün: 30 PB
```

## Arxitektura

```
┌─────────────────────────────────────────────────────────────┐
│                      INGEST LAYER                           │
│   OBS ──RTMP──▶ ┌──────────┐                                │
│   FFmpeg ─SRT─▶ │ Ingest   │──▶ Auth (stream_key validate)  │
│   Browser WebRTC│ Server   │──▶ Metrics (bitrate, fps)      │
│                 └──────────┘                                │
│                       ▼                                     │
│              ┌──────────────────┐                           │
│              │ Transcoder Pool  │  GPU NVENC / CPU x264     │
│              │ One → Many ABR   │                           │
│              └──────────────────┘                           │
│                       ▼                                     │
│              ┌──────────────────┐                           │
│              │ Packager         │  HLS + LL-HLS + DASH      │
│              │ CMAF chunked     │                           │
│              └──────────────────┘                           │
└─────────────────────────────────────────────────────────────┘
                        ▼
┌─────────────────────────────────────────────────────────────┐
│                    DELIVERY LAYER                           │
│   Origin ──▶ CDN (Akamai/Cloudflare/Fastly) ──▶ Edge ──▶ Viewer │
│        │                                                    │
│        └──▶ VOD Pipeline (S3 + offline transcode)           │
└─────────────────────────────────────────────────────────────┘
                        ▼
┌─────────────────────────────────────────────────────────────┐
│                    CONTROL PLANE                            │
│   Stream Metadata (MySQL), Chat (Redis Pub/Sub + WebSocket) │
│   Viewer Count (Redis), Discovery (ES), Auth (Laravel API)  │
└─────────────────────────────────────────────────────────────┘
```

## Əsas Anlayışlar

### Ingest Protokolları

```
RTMP  — TCP, 2-5s latency, OBS default, pis şəbəkədə yavaş (HoL blocking)
SRT   — UDP + ARQ reliable, 1-2s, packet loss-a tolerant, encrypted
WebRTC — UDP+DTLS+SRTP, < 500ms, browser-native; SFU lazım (mediasoup/Janus/LiveKit)
RIST  — professional broadcast, SRT-ə bənzər açıq standart
```

Seçim: delivery üçün HLS/LL-HLS (scale) + WebRTC (premium low-latency). Ingest default
RTMP, advanced streamer-lər SRT.

### Transcoding (ABR Ladder)

```
Streamer 1080p @ 6 Mbps göndərir → Transcoder:
  ├── 1080p @ 6 Mbps (source, re-packaged)
  ├── 720p  @ 3 Mbps
  ├── 480p  @ 1.5 Mbps
  ├── 360p  @ 800 Kbps
  └── audio-only @ 128 Kbps

Hardware (NVIDIA NVENC):  1 GPU ~ 15-20 stream, bahalı amma throughput yüksək
Software (x264):          keyfiyyət yaxşı, 1 server ~ 2-4 stream, ucuz

K8s GPU pod:
  resources: { limits: { nvidia.com/gpu: 1 } }
  Autoscale by queue depth
```

### Low-Latency Delivery

```
Normal HLS:
  Segment 6s, buffer 3 × 6 = 18s, total latency ~20-30s

LL-HLS (Apple):
  Partial segment 200-500ms (CMAF chunk), HTTP/2 push, latency 2-5s

LL-DASH:
  Chunked CMAF + HTTP chunked transfer, latency 2-4s

WebRTC:
  Sub-second < 500ms, scale limitli (SFU mesh 10k-100k viewer)

Hybrid:
  Casual viewer → LL-HLS (milyon, ucuz CDN)
  Interactive (auction, sport betting) → WebRTC
```

### Chat Sistemi

```
Channel 100k viewer, 1000 msg/sec

WebSocket Gateway (sharded by channel_id)
         ▼
    Redis Pub/Sub (channel:<id>)
         ├──▶ Rate limiter (per-user, per-channel)
         ├──▶ Spam filter (ML + keyword)
         ├──▶ Moderator queue
         └──▶ Persistence (Kafka → ClickHouse)

Fan-out: User A → Gateway → Redis PUBLISH → bütün subscribed gateway-lər → WebSocket
Sharding: channel_id % N; hot stream (10M viewer) dedicated cluster
```

### CDN və Edge Caching

```
Cache key: /live/<channel_id>/<rendition>/<segment_number>.ts
Segment TTL: 30s, Playlist TTL: 1-2s (tez dəyişir)

Hot stream fan-out:
  100k viewer eyni segment → edge-də 1 origin request, digərləri cache hit
  Shield tier (mid-tier cache) origin yükünü azaldır

Multi-CDN: Akamai + Cloudflare + Fastly, failover + cost arbitrage
```

### DRM (paid content)

```
Widevine (Android/Chrome), FairPlay (iOS/Safari), PlayReady (Microsoft/TV)
Segmentlər AES-128/SAMPLE-AES ilə encrypt. Key server auth user-ə key verir.
CDN encrypted segment cache edir, amma oxuya bilmir.
```

## Nümunələr

### 1. Stream Key Validation (Laravel + Nginx-RTMP)

Nginx-RTMP ingest zamanı `on_publish` callback göndərir, Laravel endpoint-i key-i
validate edir.

```php
// routes/api.php
Route::post('/ingest/auth', [IngestController::class, 'authenticate']);
Route::post('/ingest/done', [IngestController::class, 'onPublishDone']);

// app/Http/Controllers/IngestController.php
class IngestController extends Controller
{
    public function authenticate(Request $request)
    {
        $streamKey = $request->input('name'); // Nginx-RTMP field

        $stream = Stream::where('stream_key', $streamKey)
            ->where('status', 'enabled')
            ->with('user')
            ->first();

        if (!$stream || $stream->user->is_banned) {
            Log::warning('Invalid stream key', ['key' => $streamKey]);
            return response('Unauthorized', 403);
        }

        $stream->update([
            'status' => 'live',
            'started_at' => now(),
            'ingest_server' => $request->input('addr'),
        ]);

        event(new StreamStarted($stream));
        Redis::sadd('live:streams', $stream->id);
        Redis::setex("stream:{$stream->id}:heartbeat", 30, time());

        return response('OK', 200);
    }

    public function onPublishDone(Request $request)
    {
        $stream = Stream::where('stream_key', $request->input('name'))->first();
        if ($stream) {
            $stream->update(['status' => 'ended', 'ended_at' => now()]);
            Redis::srem('live:streams', $stream->id);
            ProcessVODJob::dispatch($stream); // offline VOD transcode
        }
        return response('OK', 200);
    }
}
```

### 2. Chat Backend (Laravel Reverb + Redis)

```php
// app/Events/ChatMessageSent.php
class ChatMessageSent implements ShouldBroadcast
{
    public function __construct(
        public int $channelId,
        public int $userId,
        public string $username,
        public string $message,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel("stream.{$this->channelId}");
    }

    public function broadcastAs(): string { return 'message'; }
}

// app/Http/Controllers/ChatController.php
class ChatController extends Controller
{
    public function send(Request $request, int $channelId)
    {
        $user = $request->user();
        $message = $request->input('message');

        // Rate limit: 1 msg/sec per user per channel
        $key = "chat:rate:{$user->id}:{$channelId}";
        if (Redis::incr($key) > 1) {
            return response()->json(['error' => 'slow_down'], 429);
        }
        Redis::expire($key, 1);

        if ($this->isSpam($message)) {
            return response()->json(['error' => 'spam'], 400);
        }

        event(new ChatMessageSent($channelId, $user->id, $user->name, $message));
        dispatch(new PersistChatMessageJob($channelId, $user->id, $message))
            ->onQueue('chat-persist');

        return response()->json(['ok' => true]);
    }

    private function isSpam(string $message): bool
    {
        if (strlen($message) > 500) return true;
        if (preg_match('/(.)\1{10,}/', $message)) return true; // aaaaaa...
        return false;
    }
}
```

### 3. Data Model

```sql
CREATE TABLE streams (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    title VARCHAR(255),
    category_id INT,
    stream_key VARCHAR(64) UNIQUE NOT NULL,
    status ENUM('enabled','live','ended','banned') DEFAULT 'enabled',
    started_at TIMESTAMP NULL,
    ended_at TIMESTAMP NULL,
    peak_viewers INT DEFAULT 0,
    INDEX idx_status_category (status, category_id)
);

CREATE TABLE stream_segments (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    stream_id BIGINT NOT NULL,
    segment_number INT NOT NULL,
    rendition VARCHAR(16),    -- '1080p', '720p', ...
    s3_key VARCHAR(255),
    duration_ms INT,
    UNIQUE KEY u_seg (stream_id, rendition, segment_number)
);

CREATE TABLE chat_messages (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    channel_id BIGINT NOT NULL,
    user_id BIGINT NOT NULL,
    message VARCHAR(500),
    created_at TIMESTAMP,
    INDEX idx_channel_time (channel_id, created_at)
);
-- Production: chat_messages ClickHouse və ya Cassandra-da
```

### 4. Monitoring

```
Ingest:   received bitrate, fps (frame drop), keyframe interval, queue depth
Delivery: origin egress, CDN cache hit ratio, 2xx/4xx/5xx rate
Player:   rebuffer ratio, startup time, ABR switches
Latency:  SEI timestamp (streamer → player delta), target P50=2s P99=6s
```

## Praktik Tapşırıqlar

**1. Niyə HLS latency 20+ saniyədir və LL-HLS necə həll edir?**

Normal HLS 6s segment + 3 segment buffer = 18s. LL-HLS CMAF partial segment (200-500ms)
+ HTTP/2 push ilə işləyir, buffer bir neçə chunk olur, nəticədə 2-5s latency alınır.

**2. RTMP-dən nə vaxt SRT və WebRTC-ə keçməli?**

RTMP TCP-dir, packet loss-da yavaşlayır. Pis şəbəkədə SRT (UDP+ARQ) daha yaxşıdır.
Sub-second lazımdırsa (auction, sport betting) WebRTC, amma scale çətinləşir.
Hybrid: RTMP ingest + LL-HLS delivery default.

**3. 10M viewer eyni yayımı izləyir — origin-i necə qoruyursan?**

Multi-tier CDN: edge + shield (mid-tier). Hot stream üçün origin-ə 1 request çıxır,
digərləri edge-də cache hit. Cache key: `channel + rendition + segment_num`. Playlist
short TTL (1s), segment uzun TTL (30s).

**4. Chat-də 1M msg/sec necə fan-out edirsən?**

Redis Pub/Sub sharded by channel_id. WebSocket gateway-lər subscribe olur. User yazır
→ gateway → Redis PUBLISH → bütün gateway-lər push → WebSocket client-lərə. Hot channel
(10M viewer) dedicated Redis cluster.

**5. Transcoding GPU yoxsa CPU?**

NVENC (GPU): 1 GPU ~ 15-20 stream, keyfiyyət yaxşı, bahalı. x264 (CPU): keyfiyyət bir az
daha yaxşı, 1 server ~ 2-4 stream. Tier-based: popular streamer NVENC, niche streamer CPU.

**6. Streamer qəfil disconnect olsa, viewer nə görəcək?**

Ingest server 10-15s reconnect window saxlayır. Streamer qayıtsa, segmentlər davam edir.
Qayıtmasa stream `ended` olur, player "stream ended" göstərir. Partial VOD yenə də
S3-ə yazılır və VOD pipeline-a gedir.

**7. Ən böyük cost driver nədir?**

Egress bandwidth (CDN) #1 — milyard dollar ola bilər. Optimization: multi-CDN (arbitrage),
per-title encoding (AV1/HEVC bandwidth-i 30% azaldır), P2P delivery (WebTorrent), aggressive
ABR (default 720p). Transcode ikinci xərcdir — GPU utilization monitoring kritikdir.

**8. Stream key leak olsa nə baş verir?**

İki nəfər eyni key ilə ingest → ingest server conflict detect edir (ilk connection qalır).
Orijinal streamer disconnect olsa, hacker yayım açar. Həlli: key rotation UI, IP whitelist
(enterprise), short-lived JWT token, HMAC-signed ingest URL.

## Praktik Baxış

- Ingest həmişə regional PoP-a yönəldilsin (GeoDNS) — streamer-ə ən yaxın datacenter
- Stream key URL path-da olmasın, yalnız `/live` path + private key
- HMAC signed CDN URL-ləri short TTL ilə istifadə et (hotlink qorunması)
- ABR ladder ən az 4 rendition: 360p/480p/720p/1080p + audio-only (zəif şəbəkə)
- CDN-də playlist TTL 1-2s, segment TTL 30s
- LL-HLS və CMAF chunked encoding istifadə et — latency 3x azalır
- Transcode worker-lər K8s-də autoscale olsun (queue depth əsasında)
- Chat-i stream metadata-dan ayrı cluster-də saxla (fault isolation)
- Chat rate limit layered: per-user, per-channel, global emergency switch
- VOD pipeline offline işləsin — live ingest-i bloklamasın
- End-to-end latency SEI data ilə ölç (streamer timestamp → viewer player)
- Multi-CDN strategy: Akamai + Cloudflare + Fastly, failover + cost arbitrage
- GPU encoder utilization monitor et — 70% altı waste, 90% üstü queue building
- DRM yalnız paid content üçün (latency əlavə edir)
- WebRTC sub-second mode-u opt-in et, default LL-HLS (cost optimization)
- Streamer dashboard-da bitrate/fps/dropped frames real-time göstər
- Ban sistemi ingest səviyyəsində olsun (banned user açıla bilməsin)
- Recording S3-yə direct yazılsın, offline transcode sonra olsun
- Cost dashboard: egress GB/day, transcode GPU-hour, storage PB real-time


## Əlaqəli Mövzular

- [Video Streaming](23-video-streaming-design.md) — VOD arxitekturası
- [CDN](04-cdn.md) — live chunk distribution
- [Stream Processing](54-stream-processing.md) — live video analitika
- [Video Conferencing](80-video-conferencing-design.md) — iki tərəfli video
- [Real-Time Systems](17-real-time-systems.md) — live chat fan-out
