# Video Conferencing Design (Lead)

Bu sənəd **video conferencing platform** (Zoom, Google Meet, Microsoft Teams) dizaynını izah edir. Real-time video/audio, WebRTC, SFU topology və Laravel signaling ilə interview prep üçün.

---


## Niyə Vacibdir

Zoom/Meet kimi sistemlər WebRTC, SFU/MCU topology, simulcast, adaptive bitrate — real-time media üzrə ən mürəkkəb texniki problemləri əhatə edir. P2P vs server-mediated seçimi scale ilə bağlıdır. Bu arxitekturanı başa düşmək real-time sistem mühəndisliyi üçün ən yüksək dərəcəli hazırlıqdır.

## Tələblər

### Funksional (Functional)
- Multi-party video və audio (2-dən 1000-ə qədər iştirakçı)
- Screen sharing (ekran paylaşımı)
- In-meeting chat və reactions
- Meeting recording (yazma) və cloud storage
- Waiting room, host controls (mute, remove)
- Transcription (subtitle) — optional

### Qeyri-funksional (Non-Functional)
- **Low latency**: <200ms glass-to-glass (kamera → ekran)
- **Availability**: 99.99% uptime
- **Bad network tolerance**: 5-10% packet loss-u idarə et
- **Scale**: milyonlarla concurrent meeting
- **Security**: encrypted media (DTLS + SRTP), E2EE opsiyası

---

## 2. WebRTC Fundamentals

**WebRTC** (Web Real-Time Communication) browser-native real-time media protokoludur.

### PeerConnection
- Iki peer arasında **encrypted media** göndərən əsas obyekt
- Transport: UDP (reliable TCP yavaş olar video üçün)
- Security: **DTLS** (handshake) + **SRTP** (media encryption)
- Audio, video, və data channel-ları daşıyır

### ICE (Interactive Connectivity Establishment)
- İki peer arasında **ən yaxşı path**-ı tapır
- **Candidate gathering**: host (local IP), server-reflexive (public IP via STUN), relay (TURN)
- Candidate-lər exchange olunur, connectivity check edilir

### STUN Server
- **Session Traversal Utilities for NAT**
- Client-in **public IP:port**-unu öyrənməyə kömək edir (NAT arxasında)
- Yüngül, stateless, ucuz (Google-ın public STUN server-i var: `stun.l.google.com:19302`)

### TURN Server
- **Traversal Using Relays around NAT**
- Direct P2P mümkün olmayanda (məsələn **symmetric NAT**) media relay edir
- **Bandwidth-costly**: bütün media server-dən keçir
- Enterprise-də şirkət öz TURN-unu deploy edir (coturn populyar open-source)

### SDP (Session Description Protocol)
- **Offer/Answer exchange**: "Mən VP9 dəstəkləyirəm, Opus audio, bu codec-lərlə..."
- Text-based format: media types, codec-lər, IP:port
- Signaling server üzərindən ötürülür (WebRTC signaling-i özü define etmir)

---

## 3. Topologies

### Mesh (P2P Full-Connect)
```
    A <--> B
    ^  \/  ^
    |  /\  |
    v /  \ v
    C <--> D
```
- Hər peer digər peer-ə birbaşa bağlanır
- **N*(N-1)/2** connection, hər client N-1 upload stream göndərir
- **Yalnız 2-3 iştirakçı** üçün uyğundur (4+ üçün upload bandwidth partlayır)
- Server lazım deyil (signaling-dən başqa)

### MCU (Multipoint Control Unit)
- Server **bütün stream-ləri decode edir, mix edir, re-encode edir**
- Hər client-ə **bir stream** göndərir (mixed grid)
- **CPU/GPU heavy** server-də — bahalı
- Uniform client: zəif device-lər də işləyir (yalnız 1 decode)
- Legacy video conferencing (Polycom, Cisco) istifadə edirdi

### SFU (Selective Forwarding Unit) — ƏSAS PATTERN
```
            +----------+
   A  --->  |          |  ---> A (B, C, D stream)
   B  --->  |   SFU    |  ---> B (A, C, D stream)
   C  --->  |  server  |  ---> C (A, B, D stream)
   D  --->  |          |  ---> D (A, B, C stream)
            +----------+
         (no transcoding,
          just forwarding)
```
- Server **stream-ləri qəbul edir, lazım olanları forward edir** (decode yox!)
- **Most scalable** — server CPU az istifadə edir
- Hər client **N-1 stream download edir** (daha çox client bandwidth)
- Zoom, Google Meet, Teams — hamısı SFU əsaslıdır

---

## 4. SFU Advanced Features

### Simulcast
- Client **3 layer göndərir**: high (720p), medium (360p), low (180p)
- SFU viewer-in network-nə görə hansı layer-i forward edəcəyinə qərar verir
- Zəif network olan viewer low layer alır, güclü olan high

### SVC (Scalable Video Coding)
- **Tək stream-də** multiple layer encode edilir (temporal + spatial)
- SFU lazımsız frame-ləri drop edir (re-encode yoxdur)
- VP9 SVC, AV1 SVC — daha yeni codec-lərdə
- Simulcast-dan **daha efficient** (1 encode vs 3 encode)

---

## 5. Codecs

| Codec | Istifadə | Quality | CPU | Notes |
|-------|----------|---------|-----|-------|
| **VP8** | Legacy | Yaxşı | Az | WebRTC default uzun müddət |
| **VP9** | Müasir | Əla | Orta | Google Meet default |
| **AV1** | Yeni | Ən yaxşı | **Çox heavy** | Bandwidth 30% az, amma CPU |
| **H.264** | Ubiquity | Yaxşı | Az (HW accel) | Mobile/Safari üçün |
| **Opus** | Audio | Əla | Az | 6-510 kbps, WebRTC standard |

---

## 6. Signaling Server

- Media-dan **ayrıdır** (WebRTC signaling-i define etmir)
- Funksiyalar:
  - Room state (kim həmin room-dadır)
  - User presence (online/offline, mute status)
  - Offer/Answer exchange (SDP ötürülməsi)
  - ICE candidate exchange
- Protocol: **WebSocket** (low-latency, bidirectional)
- Laravel-də **Reverb** istifadə edilə bilər

---

## 7. Yüksək Səviyyəli Arxitektura (High-Level Architecture)

```
 +------------+     +------------+      +---------------+
 | Web Client |     | iOS Client |      | Android Client|
 +------+-----+     +------+-----+      +-------+-------+
        |                  |                    |
        |  WebSocket       |                    |
        |  (signaling)     |                    |
        v                  v                    v
   +-----------------------------------------------+
   |         Signaling Server (Laravel Reverb)     |
   |   - Room mgmt, presence, SDP/ICE exchange     |
   +--------------------+--------------------------+
                        |
                        v
   +-----------------------------------------------+
   |              SFU Cluster (Regional)           |
   |  +-------+   +-------+   +-------+            |
   |  | SFU-1 |   | SFU-2 |   | SFU-3 |  cascade   |
   |  +---+---+   +---+---+   +---+---+            |
   +------|-----------|-----------|----------------+
          |           |           |
          v           v           v
   +-------------+  +------------+  +--------------+
   | TURN Pool   |  | Recording  |  | Transcription|
   | (global)    |  | Service    |  | (optional)   |
   +-------------+  +-----+------+  +--------------+
                          |
                          v
                   +-------------+
                   | S3 / GCS    |
                   | (recordings)|
                   +-------------+
```

---

## 8. Room Scaling

- Tək SFU ~50-100 iştirakçı idarə edir (CPU/network limit)
- **1000+ iştirakçı** üçün:
  - **SFU Cascade**: SFU-1 → SFU-2 → SFU-3 (stream-lər forward olunur)
  - **Hierarchical SFU**: tree topology, root SFU media aggregate edir
- **Active speaker detection** — yalnız aktiv speaker-in video-su full quality, digərləri thumbnail

---

## 9. Bandwidth Adaptation

- **REMB / TWCC** — Receiver Estimated Max Bitrate, Transport-Wide Congestion Control
- Client network metrics (packet loss, RTT) göndərir
- SFU bitrate-i dinamik azaldır, simulcast layer switch edir
- Viewer zəif network-də olanda low-res alır, güclü olanda high-res

---

## 10. Packet Loss və Jitter Handling

- **Jitter buffer** — packet-lər network-də qeyri-bərabər gəlir; buffer smooth playback təmin edir
- **PLC** (Packet Loss Concealment) — itmiş audio frame-i interpolate et
- **FEC** (Forward Error Correction) — redundant data göndər, itki bərpa et
- **NACK** (Negative Acknowledgment) — "bu packet itdi, yenidən göndər"
- **RTX** (Retransmission) — NACK-ə cavab olaraq

---

## 11. Audio DSP (Client-side)

- **AEC** (Acoustic Echo Cancellation) — speaker-dən gələn səs mikrofona qayıtmasın
- **NS** (Noise Suppression) — fon küyü (ventilator, klavye) filter et
- **AGC** (Automatic Gain Control) — uzaq/yaxın microphone-da səviyyə eyni
- WebRTC bunları **browser-də built-in** verir

---

## 12. Geo-Distribution

- User **ən yaxın SFU**-ya qoşulur (anycast DNS, GeoDNS)
- Bütün user-lər eyni region-dadırsa: tək SFU, minimal latency
- Mixed region (ABD + Avropa + Asia):
  - Hər region-un SFU-su var
  - SFU-lar arasında **cascade** (inter-region forwarding)
  - Latency artır, amma intra-region optimal qalır

---

## 13. Recording

### Options
1. **Client-side recording** — hər iştirakçı öz stream-ini yazır, sonra merge
   - Pro: server yük yoxdur, yüksək quality
   - Con: file sync çətin, iştirakçı connection itirərsə hissə itir
2. **Server-side compositing** — SFU-ya yaxın recording bot, MCU-like mix
   - Pro: tək final file, reliable
   - Con: server CPU heavy (decode + encode)
3. **Hybrid**: raw stream-ləri + compressed mix save et, sonra re-composite mümkün

### Storage
- Raw: S3/GCS object storage
- Metadata: PostgreSQL (user, duration, timestamps)
- CDN ilə playback

---

## 14. Chat və Reactions

- Separate **WebSocket channel** (signaling-dən ayrı) VƏ YA WebRTC **DataChannel**
- **Redis Pub/Sub** — signaling server-lər arasında chat message broadcast
- Reactions (thumbs up, clap) — lightweight event, high frequency
- Chat history — recording-lə birlikdə save

---

## 15. Screen Sharing

- **Əlavə PeerConnection stream** (video track)
- Parametrlər fərqli: **higher resolution** (1080p+), **lower fps** (5-15)
- Codec: VP9 daha yaxşı (text clarity)
- Bəzi sistemlərdə audio da share (tab audio)

---

## 16. Security

### Default Encryption
- **DTLS-SRTP**: client ↔ SFU arasında encrypted
- SFU **keys-ləri bilir** — media-ya access edə bilər (compliance üçün lazımdır)

### E2EE (End-to-End Encryption)
- Zoom: **opt-in** (E2EE mode) — recording disable olur
- Google Meet: default DTLS-SRTP, E2EE opt-in
- **Group E2EE çətindir** — Signal-style **MLS** (Messaging Layer Security, RFC 9420) protocol
- Key rotation user join/leave olanda

### Digər
- Room password, waiting room, host approval
- Identity: SSO (SAML, OIDC) enterprise üçün
- Rate limiting — invitation spam qarşısı

---

## 17. Observability

- **MOS** (Mean Opinion Score) — 1-5 user-perceived quality
- **Jitter, packet loss, RTT** — per-connection metrics
- **Frame rate, resolution** — video quality
- **Viewer reports** — "video freezed" button
- Prometheus + Grafana, ELK log-lar üçün

---

## 18. Open Source SFU-lar

| SFU | Dil | Notes |
|-----|-----|-------|
| **LiveKit** | Go | Modern, cloud-native, Kubernetes-friendly |
| **Jitsi Videobridge** | Java | Blueprint açıq, Jitsi Meet ilə |
| **Janus** | C | Lightweight, extensible |
| **mediasoup** | C++/Node | Library-first, framework yox |
| **Pion** | Go | Pure Go WebRTC stack, low-level |

---

## 19. Laravel Signaling Example (Reverb WebSocket)

Laravel **SFU** deyil (media handling Go/Node/C++ job-udur), amma **signaling** edə bilər.

```php
// routes/api.php
Route::post('/rooms', [RoomController::class, 'create']);
Route::post('/rooms/{room}/join', [RoomController::class, 'join']);
```

```php
// app/Http/Controllers/RoomController.php
public function create(Request $request)
{
    $room = Room::create([
        'id' => Str::uuid(),
        'host_id' => $request->user()->id,
        'sfu_endpoint' => $this->sfuRouter->selectNearest($request->ip()),
    ]);
    return response()->json($room);
}

public function join(Request $request, Room $room)
{
    $token = $this->sfuRouter->generateJoinToken($room, $request->user());
    broadcast(new UserJoinedRoom($room, $request->user()))->toOthers();
    return response()->json(['sfu' => $room->sfu_endpoint, 'token' => $token]);
}
```

```php
// app/Events/SignalingMessage.php
class SignalingMessage implements ShouldBroadcast
{
    public function __construct(
        public string $roomId,
        public string $fromUser,
        public string $toUser,
        public array $payload  // SDP offer/answer or ICE candidate
    ) {}

    public function broadcastOn(): Channel
    {
        return new PresenceChannel("room.{$this->roomId}");
    }
}
```

```javascript
// Client side (browser)
Echo.join(`room.${roomId}`)
  .here(users => console.log('Participants:', users))
  .joining(user => createPeerConnection(user))
  .listen('SignalingMessage', async (e) => {
    if (e.payload.type === 'offer') {
      await pc.setRemoteDescription(e.payload);
      const answer = await pc.createAnswer();
      await pc.setLocalDescription(answer);
      axios.post('/signal', { to: e.fromUser, payload: answer });
    }
  });
```

---

## 20. Real-World Nümunələr

### Zoom
- **Custom protocol over UDP** (WebRTC deyil originally)
- Indi hybrid: web client üçün WebRTC, native app-da custom
- Proprietary codec, aggressive optimization
- MMR (Multimedia Router) — SFU variant

### Google Meet
- **WebRTC + custom SFU** (closed-source)
- VP9 default, AV1 enterprise tier-də
- Tight Chrome integration

### Microsoft Teams
- Skype legacy-dən migrate
- Azure Communication Services üzərində
- WebRTC hybrid

### Discord
- Voice-focused sonra video əlavə
- Custom Rust-based media server
- Opus audio, H.264 video

---

## 21. Interview Q&A

**Q1: Niyə SFU MCU-dan daha scalable-dir?**
A: MCU server-də **decode + mix + re-encode** edir — hər room üçün bahalı CPU/GPU. SFU yalnız packet-ləri forward edir — CPU az istifadə edir. Tradeoff: SFU-da client **N-1 stream download edir** (daha çox client bandwidth və decode).

**Q2: STUN ilə TURN arasında fərq nədir?**
A: **STUN** client-in public IP:port-unu öyrədir (NAT discovery) — yüngül. **TURN** media-nı özü relay edir — P2P mümkün olmayanda (symmetric NAT). TURN bandwidth-costly çünki bütün trafik oradan keçir.

**Q3: Simulcast nədir və niyə lazımdır?**
A: Client **3 quality layer göndərir** (high/med/low). SFU hər viewer-ə network-nə görə uyğun layer forward edir. Zəif network-u olan user low layer alır freeze olmadan. Alternativ SVC-dir (tək stream-də layer-lər encode).

**Q4: 1000 iştirakçılı meeting-i necə scale edərsən?**
A: Tək SFU limit-dir (~100). **Cascade SFU** istifadə et — SFU-lar bir-birinə connect olur, stream-lər forward olunur. Ya da **hierarchical**: root SFU + leaf SFU-lar. **Active speaker detection** — yalnız N (məsələn 9) ən aktiv speaker-i göstər, qalanları audio-only.

**Q5: WebRTC-də packet loss-u necə idarə edirsən?**
A: **Jitter buffer** (smooth playback), **PLC** (loss concealment, audio interpolation), **FEC** (redundant data), **NACK** (retransmission request), **REMB/TWCC** bandwidth adaptation. Kombinasiya — 5-10% loss-u user hiss etməz.

**Q6: E2EE group video-da niyə çətindir?**
A: 1-1 E2EE asandır (Diffie-Hellman). Group-da **key management** çətinləşir: user join/leave olanda **key rotation** lazımdır (forward secrecy). Həll — **MLS protocol** (RFC 9420), Signal-style tree-based group keys. SFU media-nı görmür, yalnız encrypted forward edir — recording, moderation çətin olur.

**Q7: Geo-distributed meeting-də latency-ni necə azaldırsan?**
A: **GeoDNS / anycast** ilə user ən yaxın SFU-ya qoşulur. Mixed region olsa: hər region-un öz SFU-su, **inter-region cascade** edir (region-lar arası tək link). Intra-region latency <50ms, inter-region əlavə ~100-150ms.

**Q8: Laravel-in bu sistemdə rolu nədir?**
A: Laravel **media handling üçün uyğun deyil** (CPU-intensive, Go/C++/Rust işi). Amma Laravel ideal-dır:
- **Signaling server** (Reverb WebSocket) — room mgmt, presence, SDP/ICE exchange
- **REST API** — room create, user invite, recording metadata
- **Business logic** — billing, quotas, permissions
- SFU ayrı service-dir (LiveKit, mediasoup) — Laravel ilə HTTP/gRPC ilə inteqrasiya olur.

---

## 22. Best Practices

1. **SFU seç** — mesh/MCU-nu yalnız spesifik case-lərdə istifadə et (2-person call, legacy interop)
2. **Simulcast məcburi** — heterogeneous network-lərdə vacib
3. **TURN pool regional deploy et** — global coverage, fallback
4. **Opus audio, VP9/AV1 video** — müasir codec-lər seç
5. **Client-side DSP always on** — AEC, NS, AGC UX üçün kritik
6. **Active speaker detection** — böyük meeting-lərdə bandwidth save
7. **Recording-i async process et** — real-time mixing yerinə post-meeting composite
8. **Monitor MOS, jitter, loss** — objective quality metrics
9. **Separate signaling from media** — scale ayrı, fail ayrı
10. **Open-source SFU istifadə et** (LiveKit, mediasoup) — özünü yazma, 5+ il development
11. **E2EE opt-in olsun** — recording/transcription default vacibdir enterprise üçün
12. **Graceful degradation** — network pisləşəndə video off, audio keep (audio ən vacib)

---

## 23. Xülasə (Summary)

Video conferencing sisteminin əsas hissələri:
- **WebRTC** (PeerConnection, ICE, STUN/TURN, SDP) — media transport
- **SFU topology** — scalable server, simulcast/SVC layer routing
- **Signaling server** (Laravel Reverb) — room state, offer/answer exchange
- **Codec choice** — Opus audio, VP9/AV1 video
- **Resilience** — jitter buffer, FEC, NACK, bandwidth adaptation
- **Geo-distribution** — nearest SFU, cascade for mixed regions
- **Recording** — server compositing və ya client-side merge
- **Security** — DTLS-SRTP default, E2EE (MLS) opt-in
- **Laravel-in rolu** — signaling + business logic, SFU ayrı service

Interview-da **SFU vs MCU tradeoff-unu**, **simulcast-ın əhəmiyyətini**, və **signaling/media separation**-u izah et — bu 3 fikir əsas-dır.


## Əlaqəli Mövzular

- [Real-Time Systems](17-real-time-systems.md) — WebSocket/SSE əsasları
- [Live Streaming](58-live-streaming-design.md) — one-to-many video delivery
- [Pub/Sub](81-pubsub-system-design.md) — signaling event delivery
- [CDN](04-cdn.md) — TURN server distribution
- [Video Streaming](23-video-streaming-design.md) — VOD müqayisəsi
