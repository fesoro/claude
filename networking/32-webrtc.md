# WebRTC (Senior)

## İcmal

WebRTC (Web Real-Time Communication) browser və mobile app-lər arasında **peer-to-peer** audio, video və data communication üçün açıq standartdır. Google tərəfindən 2011-də açıq mənbə kimi buraxılıb, W3C və IETF tərəfindən standardlaşdırılıb.

```
Traditional client-server:           WebRTC P2P:
  Browser A ---> Server <--- Browser B    Browser A <------> Browser B
  (bütün media server üzərindən)          (birbaşa media, server yalnız signaling)
```

Əsas istifadə halları: video konfrans (Google Meet, Zoom web client, Discord), voice calls (WhatsApp Web), screen sharing, file transfer (P2P), online gaming, live streaming.

## Niyə Vacibdir

Laravel backend-i WebRTC sistemlərinin kritik hissəsini idarə edir: signaling server, TURN credentials API, room management. Plugin-siz, aşağı latency, end-to-end şifrəli real-time kommunikasiya qurmaq üçün WebRTC-nin necə işlədiyini başa düşmək şərtdir.

## Əsas Anlayışlar

### Signaling (WebRTC-nin hissəsi deyil)

```
WebRTC spec signaling protokolunu təyin etmir.
Developer seçir: WebSocket, HTTP, SIP, MQTT, hər şey.

Signaling-in məqsədi:
  - SDP (Session Description Protocol) offer/answer mübadilə et
  - ICE candidate-ləri mübadilə et
  - Peer joining/leaving bildiriş

Flow:
  Peer A              Signal Server              Peer B
    |--- offer (SDP) ----->|                          |
    |                      |----- offer (SDP) ------->|
    |                      |<---- answer (SDP) -------|
    |<--- answer (SDP) ----|                          |
    |-- ICE candidate ---->|------ ICE candidate ---->|
    |<- ICE candidate -----|<------ ICE candidate ----|
```

### NAT Traversal: STUN

```
Problem: Əksər cihazlar NAT arxasındadır.
  Private IP: 192.168.1.10
  Public IP:  203.0.113.5 (router tərəfindən verilir)
Peer birbaşa private IP-yə qoşula bilmir.

STUN (Session Traversal Utilities for NAT):
  Peer STUN server-ə soruşur: "Mənim public IP-m nədir?"
  STUN cavab verir: "203.0.113.5:54321"
  Peer bu ünvanı ICE candidate kimi paylaşır

Peer A                      STUN Server
  |--- Binding Request ------->|
  |<-- Binding Response -------|  (public IP:port açıqlanır)
  |
  |--- signaling vasitəsilə Peer B-yə paylaşılır
```

### NAT Traversal: TURN (fallback)

```
Problem: Symmetric NAT və ya strict firewall birbaşa P2P-ni bloklayır.

TURN (Traversal Using Relays around NAT):
  Relay server bütün media-nı peer-lər arasında forward edir.
  Bandwidth ağırdır (server trafik üçün pul xərcləyir).
  Yalnız STUN uğursuz olduqda istifadə olunur.

Peer A                    TURN Server                    Peer B
  |---- media --------------->|                             |
  |                           |---- media ----------------->|
  |<---- media ---------------|<---- media -----------------|

Real-world: ~20% WebRTC bağlantısı TURN tələb edir.
```

### ICE (Interactive Connectivity Establishment)

```
ICE bir neçə candidate yolunu toplayır və sınayır:

Candidate növləri:
  - host:   local IP (LAN)
  - srflx:  server-reflexive (STUN vasitəsilə, public IP)
  - prflx:  peer-reflexive (check zamanı kəşf edilir)
  - relay:  TURN server vasitəsilə

Hər iki peer candidate-ləri toplayır, bir-birinə göndərir.
Candidate cütlərini priority sırasıyla sınayır:
  1. host <-> host      (eyni LAN, ən sürətli)
  2. srflx <-> srflx    (public IP ilə birbaşa)
  3. relay <-> relay    (TURN vasitəsilə, ən yavaş)

İlk işləyən cüt qalır.
```

### Tam Connection Flow

```
1. Peer A: getUserMedia() → camera/mic alır
2. Peer A: createPeerConnection(iceServers)
3. Peer A: createOffer() → SDP offer
4. Peer A → Signaling → Peer B: offer
5. Peer B: createPeerConnection, setRemoteDescription(offer)
6. Peer B: createAnswer() → SDP answer
7. Peer B → Signaling → Peer A: answer
8. Hər iki peer: ICE candidate toplama (async)
9. Hər iki peer: ICE candidate-ləri signaling vasitəsilə paylaşır
10. ICE connectivity check → ən yaxşı yol tapılır
11. DTLS handshake → şifrələmə açarları
12. SRTP media P2P axır
```

### SFU vs MCU (Multi-party arxitekturalar)

```
Mesh (saf P2P):
  3 peer = hər peer 2 connection
  4 peer = hər peer 3 connection, O(N^2) cəmi
  4-5 peer-dən çox mümkün deyil (upload bandwidth)

SFU (Selective Forwarding Unit):
  Server hər peer-dən stream qəbul edir, başqalarına forward edir
  Re-encode etmir (server CPU aşağı)
  50-100+ peer-ə scale edir
  Misal: Janus, mediasoup, LiveKit, Jitsi Videobridge

MCU (Multipoint Control Unit):
  Server bütün stream-ləri decode edib bir-ə kompozit edir
  Yüksək CPU (transcoding), aşağı client bandwidth
  Köhnə SIP gateway, zəif client üçün uyğundur
  Misal: FreeSWITCH
```

### Codec Negotiation

```
Audio: Opus (default), G.711, G.722
Video: VP8, VP9, H.264, AV1 (yeni)

SDP codec preference-ləri ehtiva edir.
Hər iki peer ən azı bir ümumi codec dəstəkləməlidir.
```

### Təhlükəsizlik

```
Məcburi şifrələmə:
  - SRTP (Secure Real-time Transport Protocol) — media üçün
  - DTLS (Datagram TLS) — key exchange və DataChannel üçün
  - Şifirəsiz WebRTC mövcud deyil

Browser icazələri:
  - HTTPS tələb olunur (localhost istisna)
  - İstifadəçi camera/mic girişini icazə verməlidir
  - Aktiv olanda vizual göstərici (qırmızı nöqtə)
```

## Praktik Baxış

- **PHP WebRTC media handle etmir:** Bu browser-to-browser protokoldur. PHP/Laravel **signaling server** və **TURN credential provisioning** üçün istifadə olunur.
- **4+ peer üçün SFU məcburidir:** Mesh N^2 bandwidth tələb edir, praktikada 4-5 nəfərdən çox işləmir.
- **TURN xərci nəzərə alın:** ~20% bağlantı TURN relay-dən keçir, bu bandwidth və server xərci deməkdir. Limitli TTL ilə credential ver.
- **HTTPS məcburidir:** `getUserMedia()` yalnız secure context-də işləyir.
- **Cross-browser test:** Safari bəzi codec-ləri dəstəkləmir (VP9), fallback plan lazımdır.

### Anti-patterns

- Yalnız STUN server qurmaq — 20% istifadəçi (symmetric NAT) bağlana bilməyəcək
- TURN credentials-ı static/permanent etmək — sui-istifadə riski; HMAC-based qısa TTL istifadə et
- 4+ peer üçün saf mesh topology — N^2 bandwidth, mümkün deyil
- SFU olmadan recording cəhd etmək — browser-side recording etibarsız

## Nümunələr

### Ümumi Nümunə

```
TURN credentials HMAC mexanizmi:

username = (unix_timestamp + ttl) + ":" + user_id
credential = base64(HMAC-SHA1(secret, username))

Server hər request üçün yeni credential yaradır.
TTL bitəndə (1 saat) credential işləmir.
TURN server secret ilə eyni HMAC-i hesablayıb yoxlayır.
```

### Kod Nümunəsi

**Signaling Server (Laravel Reverb/WebSocket):**
```php
// app/Events/WebRtcSignal.php
namespace App\Events;

use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;

class WebRtcSignal implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public string $room,
        public string $type,   // 'offer', 'answer', 'ice-candidate'
        public array  $payload,
        public int    $fromUserId,
        public ?int   $toUserId = null,
    ) {}

    public function broadcastOn(): PresenceChannel
    {
        return new PresenceChannel("webrtc.{$this->room}");
    }

    public function broadcastAs(): string
    {
        return 'signal';
    }
}
```

```php
// app/Http/Controllers/SignalingController.php
public function signal(Request $request, string $room)
{
    $validated = $request->validate([
        'type'    => 'required|in:offer,answer,ice-candidate',
        'payload' => 'required|array',
        'to'      => 'nullable|integer',
    ]);

    broadcast(new WebRtcSignal(
        room: $room,
        type: $validated['type'],
        payload: $validated['payload'],
        fromUserId: $request->user()->id,
        toUserId: $validated['to'] ?? null,
    ))->toOthers();

    return response()->json(['ok' => true]);
}
```

**TURN Credentials API (time-limited):**
```php
// app/Http/Controllers/TurnCredentialsController.php
namespace App\Http\Controllers;

use Illuminate\Http\Request;

class TurnCredentialsController extends Controller
{
    public function show(Request $request)
    {
        $ttl      = 3600; // 1 saat
        $username = (time() + $ttl) . ':' . $request->user()->id;
        $secret   = config('services.turn.secret');

        // HMAC-SHA1 base64 (coturn standard)
        $credential = base64_encode(hash_hmac('sha1', $username, $secret, true));

        return [
            'iceServers' => [
                ['urls' => 'stun:stun.example.com:3478'],
                [
                    'urls' => [
                        'turn:turn.example.com:3478?transport=udp',
                        'turn:turn.example.com:3478?transport=tcp',
                    ],
                    'username'   => $username,
                    'credential' => $credential,
                ],
            ],
            'ttl' => $ttl,
        ];
    }
}
```

**Frontend (Browser) kodu:**
```html
<script>
async function startCall(roomId) {
    // 1. Laravel-dən TURN credentials al
    const iceConfig = await fetch('/api/turn-credentials').then(r => r.json());

    const pc = new RTCPeerConnection(iceConfig);

    // 2. Local media al
    const stream = await navigator.mediaDevices.getUserMedia({
        audio: true, video: true
    });
    stream.getTracks().forEach(track => pc.addTrack(track, stream));

    // 3. ICE candidate-ləri idarə et
    pc.onicecandidate = (e) => {
        if (e.candidate) {
            Echo.join(`webrtc.${roomId}`).whisper('signal', {
                type: 'ice-candidate',
                payload: e.candidate
            });
        }
    };

    // 4. Remote stream qəbul et
    pc.ontrack = (e) => {
        document.getElementById('remoteVideo').srcObject = e.streams[0];
    };

    // 5. Offer yarat və göndər
    const offer = await pc.createOffer();
    await pc.setLocalDescription(offer);
    Echo.join(`webrtc.${roomId}`).whisper('signal', { type: 'offer', payload: offer });

    // 6. Answer-i dinlə
    Echo.join(`webrtc.${roomId}`).listenForWhisper('signal', async (data) => {
        if (data.type === 'answer') {
            await pc.setRemoteDescription(data.payload);
        } else if (data.type === 'ice-candidate') {
            await pc.addIceCandidate(data.payload);
        }
    });
}
</script>
```

**SFU ilə Recording (server-side):**
```php
// LiveKit API vasitəsilə recording başlat
Http::withToken(config('services.livekit.token'))
    ->post('https://livekit.example.com/twirp/livekit.Egress/StartRoomCompositeEgress', [
        'room_name' => $roomId,
        'output'    => ['filepath' => "recordings/{$roomId}.mp4"],
    ]);
```

## Praktik Tapşırıqlar

1. **TURN server qur:** `coturn` quraşdır, HMAC-based time-limited credential API-ni Laravel-də yaz.

2. **Signaling server tamamla:** Laravel Reverb ilə `webrtc.{room}` presence channel-ini qur, offer/answer/ice-candidate message-lərini route et.

3. **Browser-dən browser-ə video call:** Yuxarıdakı frontend kodu əsasında lokal iki browser tab arasında video call test et.

4. **TURN-u sına:** Network şəraitini `tc` tool-u ilə məhdudlaşdır (symmetric NAT simulyasiyası), TURN fallback-in işlədiyini yoxla.

5. **SFU araşdır:** LiveKit və ya mediasoup-u lokal quraşdır, 3+ istifadəçi ilə mesh-in yavaşladığını, SFU-nun necə kömək etdiyini müşahidə et.

6. **getStats() izlə:** `pc.getStats()` API-dən `packet-loss`, `jitter`, `rtt` metric-lərini hər 5 saniyədə oxu, zəif şəbəkə siqnalı olaraq istifadə et.

## Əlaqəli Mövzular

- [WebSocket](11-websocket.md)
- [SSE (Server-Sent Events)](12-sse.md)
- [HTTPS & SSL/TLS](06-https-ssl-tls.md)
- [mTLS Deep Dive](35-mtls-deep-dive.md)
- [UDP](04-udp.md)
