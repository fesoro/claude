# WebRTC

## Nədir? (What is it?)

WebRTC (Web Real-Time Communication) browser və mobile app-lər arasında **peer-to-peer** audio, video və data communication üçün açıq standartdır. Google tərəfindən 2011-də açıq mənbə kimi buraxılıb, W3C və IETF tərəfindən standardlaşdırılıb.

Əsas istifadə halları:
- Video konfrans (Google Meet, Zoom web client, Discord)
- Voice calls (WhatsApp Web, Telegram)
- Screen sharing
- File transfer (P2P)
- Online gaming (low-latency data)
- Live streaming (broadcast)

```
Traditional client-server:           WebRTC P2P:
  Browser A ---> Server <--- Browser B    Browser A <------> Browser B
  (all media via server)                  (direct media, server only for signaling)
```

## Necə İşləyir? (How does it work?)

### 1. Signaling (not part of WebRTC)

```
WebRTC spec DOES NOT define signaling protocol.
Developer chooses: WebSocket, HTTP, SIP, MQTT, anything.

Purpose of signaling:
  - Exchange SDP (Session Description Protocol) offers/answers
  - Exchange ICE candidates
  - Notify about peer joining/leaving

Flow:
  Peer A                Signal Server              Peer B
    |--- offer (SDP) ----->|                          |
    |                      |----- offer (SDP) ------->|
    |                      |<---- answer (SDP) -------|
    |<--- answer (SDP) ----|                          |
    |                      |                          |
    |-- ICE candidate ---->|------ ICE candidate ---->|
    |<- ICE candidate -----|<------ ICE candidate ----|
```

### 2. NAT Traversal: STUN

```
Problem: Most devices are behind NAT (Network Address Translation).
  Private IP: 192.168.1.10
  Public IP:  203.0.113.5 (assigned by router)
Peer cannot connect to private IP directly.

STUN (Session Traversal Utilities for NAT):
  Peer asks STUN server: "What is my public IP?"
  STUN server replies: "Your public IP is 203.0.113.5:54321"
  Peer shares this address in ICE candidates

Peer A                      STUN Server
  |--- Binding Request ------->|
  |<-- Binding Response -------|  (reveals public IP:port)
  |
  |--- Share with Peer B via signaling ---
```

### 3. NAT Traversal: TURN (fallback)

```
Problem: Symmetric NAT or strict firewall blocks direct P2P.

TURN (Traversal Using Relays around NAT):
  Relay server forwards all media between peers.
  Bandwidth-heavy (server pays for traffic).
  Used as fallback when STUN fails.

Peer A                    TURN Server                    Peer B
  |---- media --------------->|                             |
  |                           |---- media ----------------->|
  |<---- media ---------------|<---- media -----------------|

~20% of WebRTC connections need TURN in practice.
```

### 4. ICE (Interactive Connectivity Establishment)

```
ICE gathers and tries multiple candidate paths:

Candidate types:
  - host:     local IP (LAN)
  - srflx:    server-reflexive (via STUN, public IP)
  - prflx:    peer-reflexive (discovered during check)
  - relay:    via TURN server

Peer gathers all candidates, sends to remote peer.
Both peers try candidate pairs in priority order:
  1. host <-> host      (same LAN, fastest)
  2. srflx <-> srflx    (direct via public IP)
  3. relay <-> relay    (via TURN, slowest)

First working pair wins.
```

### 5. Full Connection Flow

```
1. Peer A: getUserMedia() -> gets camera/mic
2. Peer A: createPeerConnection(iceServers)
3. Peer A: createOffer() -> SDP offer
4. Peer A -> Signaling -> Peer B: offer
5. Peer B: createPeerConnection, setRemoteDescription(offer)
6. Peer B: createAnswer() -> SDP answer
7. Peer B -> Signaling -> Peer A: answer
8. Both peers: ICE candidate gathering (async)
9. Both peers: exchange ICE candidates via signaling
10. ICE connectivity check -> best path found
11. DTLS handshake -> encryption keys
12. SRTP media flows P2P
```

## Əsas Konseptlər (Key Concepts)

### PeerConnection API

```javascript
const pc = new RTCPeerConnection({
    iceServers: [
        { urls: 'stun:stun.l.google.com:19302' },
        {
            urls: 'turn:turn.example.com:3478',
            username: 'user',
            credential: 'password'
        }
    ]
});
```

### Media Streams

```javascript
// Audio + video
const stream = await navigator.mediaDevices.getUserMedia({
    audio: true,
    video: { width: 1280, height: 720 }
});

stream.getTracks().forEach(track => pc.addTrack(track, stream));

// Screen sharing
const screen = await navigator.mediaDevices.getDisplayMedia({ video: true });
```

### DataChannel (P2P Data Transfer)

```javascript
const channel = pc.createDataChannel('chat', {
    ordered: true,       // guaranteed order (default)
    maxRetransmits: 3    // reliability control
});

channel.onopen = () => channel.send('Hello!');
channel.onmessage = (e) => console.log('Received:', e.data);

// Use cases: chat, file transfer, multiplayer gaming, custom protocols
```

### SFU vs MCU (Multi-party architectures)

```
Mesh (pure P2P):
  3 peers = each peer has 2 connections
  4 peers = 3 connections each, O(N^2) total
  Doesn't scale > 4-5 peers (upload bandwidth)

SFU (Selective Forwarding Unit):
  Server receives stream from each peer, forwards to others
  Does NOT re-encode (low CPU on server)
  Peer A -> SFU -> Peer B, C, D
  Scales to 50-100+ peers
  Examples: Janus, mediasoup, LiveKit, Jitsi Videobridge

MCU (Multipoint Control Unit):
  Server decodes all streams, composites into one, sends to peers
  High CPU (transcoding), low client bandwidth
  Best for low-bandwidth clients, legacy SIP gateways
  Examples: Jitsi Meet (can act as MCU), FreeSWITCH
```

### Codec Negotiation

```
Audio: Opus (default), G.711, G.722
Video: VP8, VP9, H.264, AV1 (newer)

SDP includes codec preferences.
Both peers must support at least one common codec.
```

### Security

```
Mandatory encryption:
  - SRTP (Secure Real-time Transport Protocol) for media
  - DTLS (Datagram TLS) for key exchange and DataChannel
  - No unencrypted WebRTC exists

Browser permissions:
  - HTTPS required (except localhost)
  - User must grant camera/mic access
  - Visual indicator (red dot) when active
```

## PHP/Laravel ilə İstifadə

PHP WebRTC media handle etmir - bu browser-to-browser protokoldur. PHP/Laravel **signaling server** və **TURN credential provisioning** üçün istifadə olunur.

### Signaling Server (Laravel Reverb / WebSocket)

```php
// routes/channels.php
Broadcast::channel('webrtc.{room}', function ($user, $room) {
    return ['id' => $user->id, 'name' => $user->name];
});
```

```php
// app/Events/WebRtcSignal.php
namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class WebRtcSignal implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public string $room,
        public string $type,   // 'offer', 'answer', 'ice-candidate'
        public array $payload,
        public int $fromUserId,
        public ?int $toUserId = null
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
        'type' => 'required|in:offer,answer,ice-candidate',
        'payload' => 'required|array',
        'to' => 'nullable|integer',
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

### TURN Credentials API (time-limited)

```php
// app/Http/Controllers/TurnCredentialsController.php
namespace App\Http\Controllers;

use Illuminate\Http\Request;

class TurnCredentialsController extends Controller
{
    public function show(Request $request)
    {
        $ttl      = 3600; // 1 hour
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
                    'username' => $username,
                    'credential' => $credential,
                ],
            ],
            'ttl' => $ttl,
        ];
    }
}
```

### Frontend (Browser) Code

```html
<script>
async function startCall(roomId) {
    // 1. Get TURN credentials from Laravel
    const iceConfig = await fetch('/api/turn-credentials').then(r => r.json());

    const pc = new RTCPeerConnection(iceConfig);

    // 2. Get local media
    const stream = await navigator.mediaDevices.getUserMedia({
        audio: true, video: true
    });
    stream.getTracks().forEach(track => pc.addTrack(track, stream));

    // 3. Handle ICE candidates
    pc.onicecandidate = (e) => {
        if (e.candidate) {
            Echo.join(`webrtc.${roomId}`).whisper('signal', {
                type: 'ice-candidate',
                payload: e.candidate
            });
        }
    };

    // 4. Handle remote stream
    pc.ontrack = (e) => {
        document.getElementById('remoteVideo').srcObject = e.streams[0];
    };

    // 5. Create and send offer
    const offer = await pc.createOffer();
    await pc.setLocalDescription(offer);

    Echo.join(`webrtc.${roomId}`).whisper('signal', {
        type: 'offer',
        payload: offer
    });

    // 6. Listen for answer
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

### Recording with SFU (server-side)

For recording, use SFU like **mediasoup** or **LiveKit** (Node/Go), called from Laravel via API:

```php
Http::withToken(config('services.livekit.token'))
    ->post('https://livekit.example.com/twirp/livekit.Egress/StartRoomCompositeEgress', [
        'room_name' => $roomId,
        'output' => ['filepath' => "recordings/{$roomId}.mp4"],
    ]);
```

## Interview Sualları (Q&A)

### 1. WebRTC nədir və niyə istifadə olunur?

**Cavab:** WebRTC browser-lər arasında P2P real-time audio/video/data mübadiləsi üçün standartdır. Plugin-siz işləyir (native browser API), aşağı latency təmin edir (server keçmədən birbaşa), end-to-end şifrəli. Video conference, voice call, screen share, file transfer və online oyunlar üçün istifadə olunur.

### 2. Signaling nədir və niyə WebRTC-nin hissəsi deyil?

**Cavab:** Signaling peer-lər arasında SDP offer/answer və ICE candidate-ləri mübadilə prosesidir. WebRTC spec-i developer-a transport seçimi sərbəstliyi verir - WebSocket, HTTP, SIP, MQTT olsun. Bu, WebRTC-ni hər contextə uyğunlaşdırır (korporativ SIP ilə, web app ilə və s.).

### 3. STUN və TURN arasında fərq nədir?

**Cavab:**
- **STUN** - peer-ə öz public IP-sini öyrədir, NAT arxasından direct P2P imkan verir. Server yalnız discovery üçündür, media keçmir. Ucuz.
- **TURN** - media relay kimi işləyir. Symmetric NAT və ya strict firewall olanda yeganə yoldur. Bandwidth ağırdır, server pul xərcləyir. Təxminən 20% bağlantılar TURN tələb edir.

### 4. ICE necə işləyir?

**Cavab:** ICE (Interactive Connectivity Establishment) candidate-lər toplayır: host (LAN), srflx (STUN-dan gələn public), relay (TURN). Hər iki peer candidate-ləri exchange edir, priority-yə görə cütləri yoxlayır (LAN > public > TURN), ilk işləyəni seçir. Nəticə: ən optimal yol tapılır.

### 5. SFU və MCU arasında fərq nədir?

**Cavab:**
- **SFU** - stream-ləri re-encode etmədən forward edir. Server CPU aşağı, klient bandwidth yüksək. 50-100 peer-ə scale edir. Misal: mediasoup, Janus, LiveKit.
- **MCU** - bütün stream-ləri decode edib composite video yaradır, tək stream göndərir. Server CPU yüksək, klient bandwidth aşağı. Köhnə SIP gateway və zəif klient üçün yaxşıdır.
- **Mesh** - saf P2P, yalnız 4-5 peer-ə qədər işləyir (N^2 bandwidth).

### 6. DataChannel nəyə görə istifadə olunur?

**Cavab:** P2P data transfer üçün - chat, file transfer, multiplayer oyun üçün. WebSocket-dən fərqli olaraq server keçmir (aşağı latency). Reliable (TCP-like) və unreliable (UDP-like) rejim dəstəkləyir. `ordered` və `maxRetransmits` option-ları ilə performance tune olunur.

### 7. WebRTC niyə P2P olsa da çoxlu müştəri üçün server istifadə olunur?

**Cavab:** Saf P2P mesh yalnız 4-5 peer-ə qədər işləyir - hər peer hər digərinə upload etməlidir (N^2 bandwidth). 10 nəfərlik toplantıda hər peer 9 stream upload etməlidir - mümkünsüzdür. SFU server tək upload qəbul edib digərlərinə forward edir, klient bandwidth-i azalır.

### 8. WebRTC media necə şifrələnir?

**Cavab:** Media üçün **SRTP** (Secure RTP) məcburidir. Key exchange DTLS (Datagram TLS) ilə olur. DataChannel də DTLS üzərində işləyir. Plain-text WebRTC yoxdur - spec tələb edir. End-to-end şifrələmə SFU ilə kompleksdir (insertable streams API ilə həll olunur).

### 9. WebRTC-də audio/video keyfiyyəti necə tune olunur?

**Cavab:**
- Bitrate limit (`setParameters()` with encodings)
- Adaptive bitrate (congestion control avtomatik)
- Codec seçimi (Opus audio, VP9/AV1 video)
- Simulcast - multiple resolution stream-ləri göndər, SFU uyğununu seçir
- getStats() API ilə metric-ləri izlə: packet loss, jitter, RTT

## Best Practices

1. **STUN + TURN kombinasiyası istifadə et** - yalnız STUN 80% halları həll edir, qalan 20% üçün TURN lazımdır.
2. **TURN credentials time-limited et** - HMAC-based qısa TTL (1 saat), sui-istifadənin qarşısını alır.
3. **Signaling-i scalable et** - Laravel Reverb, Socket.io, Centrifugo ilə horizontal scaling.
4. **SFU istifadə et 4+ peer üçün** - mesh topology-dan uzaq dur.
5. **getStats() ilə monitoring** - packet loss, jitter, RTT real-time izlə, pis şəraitdə bitrate azalt.
6. **Simulcast aktivləşdir** - zəif klient aşağı rezolyusiya, güclü klient yüksək rezolyusiya alır.
7. **ICE restart logic əlavə et** - network dəyişəndə connection yenidən establish olsun.
8. **HTTPS məcburidir** - getUserMedia yalnız secure context-də işləyir (localhost istisna).
9. **Bandwidth estimation respect et** - serverdən və ya client-dən congestion siqnalına reaksiya ver.
10. **Cross-browser test et** - Safari bəzi codec-ləri dəstəkləmir (VP9), fallback planla.
