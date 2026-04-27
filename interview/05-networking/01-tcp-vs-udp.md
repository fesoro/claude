# TCP vs UDP (Middle ⭐⭐)

## İcmal
TCP (Transmission Control Protocol) və UDP (User Datagram Protocol) transport layer-də iki fundamental protokoldur. Hər backend developer bu iki protokol arasındakı fərqi, hansı halda hansını seçməyi bilməlidir. Interview-larda bu sual əksər hallarda sistem dizaynı suallarının giriş hissəsi kimi verilir.

## Niyə Vacibdir
Interviewer bu sualı verərkən əslində sizi test edir: hansı trade-off-ları bildiyinizi, real layihələrdə doğru protokol seçimi edə bildiyinizi görməyə çalışır. "TCP daha etibarlıdır, UDP isə sürətlidir" səviyyəsindəki cavab Middle developer üçün kifayət etmir. Güclü cavab o zaman olur ki, siz konkret use-case-lər üzərindən izah edirsiniz. Bu bilgi həmçinin WebSockets, gRPC, QUIC/HTTP/3 kimi mövzuları anlamaq üçün fundamentaldır.

## Əsas Anlayışlar

- **TCP (Transmission Control Protocol):** Connection-oriented protokol. Hər kommunikasiya 3-way handshake ilə başlayır: SYN → SYN-ACK → ACK. Bağlantı qurana qədər data göndərilmir
- **UDP (User Datagram Protocol):** Connectionless protokol. Packet birbaşa göndərilir, qarşı tərəfin hazır olması gözlənilmir, çatıb-çatmadığı yoxlanmır
- **Reliability (Etibarlılıq):** TCP hər packet-ın çatdığını, sırasını və bütövlüyünü zəmanət verir. Packet itərsə yenidən göndərilir. UDP bu zəmanəti vermir — application özü handle etməlidir
- **Ordering:** TCP sequence number-lar vasitəsilə packet-ları gəliş sırasından asılı olmayaraq doğru sırada çatdırır. UDP-də sıra zəmanəti yoxdur
- **Flow Control:** TCP sliding window mexanizmi ilə sender-in göndərmə sürətini receiver-in emal edə biləcəyi sürətə uyğunlaşdırır. UDP yoxdur
- **Congestion Control:** TCP network yüklənəndə (packet loss aşkarlandıqda) sürəti avtomatik azaldır. Əsas alqoritmlər: TCP Reno, TCP CUBIC, BBR. UDP network-ü nəzərə almır
- **Error Checking:** Hər ikisinin checksum-u var. TCP xəta aşkarladıqda packet-ı yenidən istəyir. UDP xətalı packet-ı atar (application-ın qərarı)
- **Header Size:** TCP header minimum 20 byte (options ilə 60 byte-a qədər). UDP header cəmi 8 byte (Source Port + Dest Port + Length + Checksum). Kiçik message-lar üçün UDP daha effektiv
- **Connection State:** TCP hər connection üçün server-də state saxlayır (memory istifadəsi artır). UDP tamamilə stateless-dir — server-da hər client üçün heç bir state yoxdur
- **Latency:** TCP: 3-way handshake + ACK gözləmə = əlavə RTT. UDP: birbaşa göndər — handshake yoxdur. Latency-sensitive sistemlər üçün UDP üstündür
- **Head-of-line Blocking (TCP):** TCP stream-dir — bir packet itərsə sonrakı bütün packet-lar çatmış olsa belə gözləyir. HTTP/2 application layer-dəki blocking-i həll etdi, lakin TCP-nin blocking-i qaldı
- **SYN Flood Attack:** TCP-nin handshake mexanizmi istismar edilir — server çox yarımçıq connection (SYN_RECV state) saxlayır, memory tükənir. SYN cookies ilə qorunmaq mümkündür
- **TIME_WAIT State:** TCP connection bağlandıqdan sonra server tərəf 2×MSL (Maximum Segment Lifetime ≈ 60-120 saniyə) gözləyir — delayed packet-lara görə. High-traffic server-lərdə port exhaustion yarana bilər
- **QUIC/HTTP/3:** Google-un UDP üzərindən qurduğu protokol. TCP-nin head-of-line blocking-ini həll edir, 0-RTT connection resumption dəstəkləyir, TLS 1.3 built-in. UDP üzərindən "daha yaxşı TCP" kimi düşünüla bilər
- **Multicast/Broadcast:** UDP multicast və broadcast dəstəkləyir (bir göndərən, çox alıcı). TCP yalnız unicast (point-to-point) dəstəkləyir

## Praktik Baxış

**Interview-da yanaşma:**
Sualı eşidən kimi birbaşa fərqləri sadalamayın. Əvvəlcə məntiqi framework qurun: "Bu iki protokol reliability vs performance arasında fundamental trade-off-u təmsil edir." Sonra 2-3 konkret nümunə verin.

**Follow-up suallar:**
- "UDP unreliable olduğu üçün niyə istifadə olunsun ki?" — Application özü reliability implement edə bilər, daha çeviklik verir. Video frame-lər kimi bəzən bir packet itməsi problem deyil
- "HTTP/3 niyə UDP üzərindən işləyir?" — QUIC protokolu UDP üzərindən özünün reliability mexanizmini qurub, TCP-nin head-of-line blocking problemini aradan qaldırır
- "TCP-nin zəif tərəfləri nələrdir?" — Head-of-line blocking, 3-way handshake latency, connection state overhead, SYN flood
- "Load balancer TCP-ni necə handle edir?" — Layer 4 (TCP level, fast): IP+port-a görə. Layer 7 (HTTP level): content-ə görə, daha smart
- "TCP socket-ini necə optimize edərdiniz?" — `SO_REUSEADDR`, `TCP_NODELAY`, `tcp_tw_reuse`, connection pool

**Ümumi səhvlər:**
- UDP-ni "etibarsız" kimi mənfi məna ilə təqdim etmək — UDP-nin unreliability-si bəzən dizayn seçimidir, zəiflik deyil
- TCP-nin bütün problemlərini unutmaq: head-of-line blocking, connection overhead, SYN flood, TIME_WAIT
- Real nümunə verməmək — "TCP daha yaxşıdır" demək yetərli deyil

**Yaxşı cavabı əla cavabdan fərqləndirən:**
- QUIC/HTTP/3-ün niyə UDP üzərindən qurulduğunu izah etmək
- TIME_WAIT problemini bilmək
- SYN cookies kimi TCP optimizasiyalarını bilmək
- "Hibrid yanaşma: game state UDP, critical events TCP" demək

## Nümunələr

### Tipik Interview Sualı
"You're designing a multiplayer real-time game. Would you use TCP or UDP for game state updates? Why? What are the trade-offs?"

### Güclü Cavab
Real-time oyun üçün UDP daha uyğundur, lakin seçim use-case-ə görədir.

**Niyə UDP:**
Oyun state update-ləri (oyunçunun mövqeyi, atəş, hərəkət) çox tez-tez göndərilir — 30-60 frame per second. TCP-nin head-of-line blocking-i: bir packet itdikdə sonrakı bütün packet-lar blok olunur → oyunda "freeze" hissi. Lakin oyunda köhnəlmiş frame lazım deyil — son vəziyyət kifayət edir. UDP ilə bir packet itərsə problem yoxdur, növbəti frame onsuz da gəlir.

**Application-level handling:**
UDP-nin üstünə sequence number əlavə edərək köhnə packet-ları discard edirik. Kritik state-lər üçün application-level acknowledgment mexanizmi yazırıq.

**TCP lazım olan hallar:**
Authentication, in-game purchase, leaderboard save — bunlar güvenilir çatdırılma tələb edir → TCP. Ya da QUIC: UDP üzərindən qurulan, TCP-nin reliability-si + UDP-nin speed-i.

**Hibrid yanaşma (real game-lərdə istifadə olunur):**
- Game state (player position, health): UDP
- Critical events (kill, level up, purchase): TCP/QUIC
- Chat: TCP

### Kod Nümunəsi
```python
# UDP ilə game state göndərmə (Python)
import socket
import struct
import time

class GameClient:
    def __init__(self, server_host: str, server_port: int):
        self.sock     = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
        self.server   = (server_host, server_port)
        self.sequence = 0

    def send_player_state(self, x: float, y: float, direction: float):
        self.sequence += 1
        # Packet format: [seq:4 bytes][timestamp:8 bytes][x:4][y:4][dir:4]
        packet = struct.pack('>IQfff',
            self.sequence,
            int(time.time() * 1000),  # milliseconds timestamp
            x, y, direction
        )
        self.sock.sendto(packet, self.server)

    def recv_game_state(self) -> dict:
        data, addr = self.sock.recvfrom(1024)
        seq, ts, x, y, direction = struct.unpack('>IQfff', data)
        return {'seq': seq, 'ts': ts, 'x': x, 'y': y, 'dir': direction}


class GameServer:
    def __init__(self, port: int):
        self.sock       = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
        self.sock.bind(('0.0.0.0', port))
        self.last_seq   = {}   # player_addr → last_sequence
        self.game_state = {}   # player_addr → {x, y, dir}

    def process_packets(self):
        while True:
            data, addr = self.sock.recvfrom(1024)
            seq, ts, x, y, direction = struct.unpack('>IQfff', data)

            last = self.last_seq.get(addr, 0)
            if seq <= last:
                print(f"Discarding old packet: seq={seq}, last={last}")
                continue  # Köhnə ya da dublikat packet → ignore

            self.last_seq[addr]   = seq
            self.game_state[addr] = {'x': x, 'y': y, 'dir': direction}
            self.broadcast_state(addr)

    def broadcast_state(self, from_addr):
        """Bütün digər oyunçulara yeni state-i göndər"""
        for addr, state in self.game_state.items():
            if addr != from_addr:
                packet = struct.pack('>fff',
                    state['x'], state['y'], state['dir']
                )
                self.sock.sendto(packet, addr)
```

```python
# TCP 3-way handshake vs UDP — socket səviyyəsində fərq
import socket

# TCP — connection-oriented
tcp_server = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
tcp_server.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
tcp_server.bind(('0.0.0.0', 8080))
tcp_server.listen(100)  # Backlog: yarımçıq connection queue ölçüsü

conn, addr = tcp_server.accept()  # 3-way handshake tamamlanır
data = conn.recv(1024)            # Stream — sıralı data
conn.send(b"HTTP/1.1 200 OK\r\n\r\nHello!")
conn.close()  # FIN→FIN-ACK→ACK — bağlantı bağlanır

# UDP — connectionless
udp_server = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
udp_server.bind(('0.0.0.0', 9090))

data, addr = udp_server.recvfrom(4096)  # Datagram — bir packet
udp_server.sendto(b"Hello!", addr)       # Birbaşa göndər — ACK yox
```

```
TCP 3-way handshake:
Client                    Server
  |---SYN (seq=100)------->|   Client bağlantı açmaq istəyir
  |<--SYN-ACK (seq=200,    |   Server razıdır, öz seq-ini göndərir
  |    ack=101)------------|
  |---ACK (ack=201)------->|   Client təsdiqləyir
  |                        |   (bağlantı quruldu)
  |===DATA flow============|
  |---FIN----------------->|   Bağlantı bağlama
  |<--FIN-ACK--------------|
  |---ACK----------------->|
  (Server TIME_WAIT → 60-120s)

UDP — birbaşa:
Client                    Server
  |---DATAGRAM------------>|   Packet göndərildi, ACK yox
  |---DATAGRAM------------>|   Başqa packet (sıra zəmanəti yox)
  |<--DATAGRAM-------------|   Server istəsə cavab verir
```

```
TCP Head-of-line Blocking nümunəsi:

HTTP/1.1 (TCP üzərindən):
Packet 1: ████ (çatdı)
Packet 2: ████ (çatdı)
Packet 3: ---- (itdi!) → Bütün növbəti packet-lar blok olunur
Packet 4: ████ (gəldi amma gözləyir)
Packet 5: ████ (gəldi amma gözləyir)
[Packet 3 yenidən göndərilir → 4 və 5 release edilir]

HTTP/3 (QUIC/UDP üzərindən):
Stream A, Packet 1: ████ (çatdı)
Stream A, Packet 2: ---- (itdi!) → Yalnız Stream A blok oldu
Stream B, Packet 1: ████ (normal davam edir)
Stream B, Packet 2: ████ (normal davam edir)
[Paralel stream-lər bir-birini bloklamır]
```

```bash
# Sistem-level TCP diagnostics
# Aktiv connection-ları gör
netstat -an | grep ESTABLISHED | wc -l

# TIME_WAIT connection-ları say
netstat -an | grep TIME_WAIT | wc -l

# TCP statistikaları
ss -s
# Total: 1024, TCP: 512 estab 128 syn-recv 64 time-wait ...

# Port-lara görə connection-lar
ss -tnp | grep :8080

# TCP SYN flood protection
sysctl net.ipv4.tcp_syncookies    # 1 = enabled (default olaraq aktiv olmalı)
sysctl net.ipv4.tcp_max_syn_backlog  # SYN queue ölçüsü

# TIME_WAIT optimizasiyası (high-traffic server)
sysctl net.ipv4.tcp_tw_reuse      # 1 = TIME_WAIT port-ları yenidən istifadə et
sysctl net.ipv4.tcp_fin_timeout   # FIN-WAIT-2 timeout (default 60s)

# UDP packet loss monitoring
cat /proc/net/snmp | grep Udp
# UdpInDatagrams, UdpNoPorts, UdpInErrors, UdpOutDatagrams
```

### İkinci Nümunə — VoIP/Streaming

```
VoIP (Voice over IP) — UDP seçiminin əsaslandırması:

Ssenario: Real-time audio call (Zoom, Teams kimi)

NIYƏ UDP:
- Audio packet-ları 20ms intervalda göndərilir → 50 packet/saniyə
- Bir packet gecikmə = audible glitch
- Bir packet itməsi = 20ms sükut → tolerable
- TCP-nin replay mexanizmi: itirilmiş 20ms audio gəlib çatanda
  artıq 100ms geridədir → çox daha pis user experience

NIYƏ TCP İŞLƏMƏZ:
- Head-of-line blocking: bir audio packet itərsə
  növbəti 10 packet blok olunur → 200ms freeze → call drops

APPLICATION-LEVEL RELIABILITY:
- Sequence number: köhnə packet-ı discard et
- Jitter buffer: kiçik delay ilə hamar playback
- PLC (Packet Loss Concealment): itirilmiş packet-ı interpolate et
- RTCP (RTP Control Protocol): statistics, feedback

PROTOKOL STACK:
Application: WebRTC, SIP
Transport:   RTP (Real-time Transport Protocol) over UDP
Session:     SRTP (Secure RTP — şifrəli)
Network:     IP
Physical:    Ethernet/WiFi
```

## Praktik Tapşırıqlar

- TCP-nin "head-of-line blocking" problemini öz sözlərinizlə izah edin. HTTP/2-də mövcuddurmu? HTTP/3-də?
- UDP üzərindən sadə reliable delivery mexanizmi dizayn edin: sequence number, acknowledgment, retransmit timeout — hansı xüsusiyyətlər lazımdır?
- `netstat -an | grep TIME_WAIT` ilə öz sisteminizdə TIME_WAIT connection-ları sayın; niyə bu qədər ola bilər?
- QUIC protokolunun niyə UDP üzərindən qurulduğunu araşdırın — TCP-dən nə fayda var, nə əlavə edir?
- VoIP sistemi dizayn edərkən TCP vs UDP seçimini əsaslandırın; jitter buffer-ın rolunu izah edin
- `SO_REUSEADDR` vs `SO_REUSEPORT` socket option-larının fərqini araşdırın

## Əlaqəli Mövzular
- [HTTP Versions](02-http-versions.md) — HTTP/3 QUIC/UDP üzərindən işləyir
- [WebSockets](06-websockets.md) — TCP üzərindən qurulan persistent connection
- [TLS/SSL Handshake](03-tls-ssl-handshake.md) — TLS TCP üzərindən əlavə RTT əlavə edir
- [Long Polling vs SSE vs WebSocket](07-polling-sse-websocket.md) — Real-time communication pattern-ları
