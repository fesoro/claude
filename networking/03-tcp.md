# TCP (Transmission Control Protocol)

## Nədir? (What is it?)

TCP (Transmission Control Protocol) Transport Layer-de isleyen connection-oriented, reliable protokoldur. Data-nin tam, sirali ve seyhsiz catdirilmasini temin edir. Internet-deki trafikin boyuk hissesi (HTTP, HTTPS, FTP, SMTP, SSH) TCP uzerinde isleyir.

**Esas xususiyyetler:**
- Connection-oriented (elaqe qurulmalidir)
- Reliable delivery (temin olunmus catdirilma)
- Ordered (sirali catdirilma)
- Flow control (axin kontrolu)
- Congestion control (sixisma kontrolu)
- Full-duplex (iki istiqametli eyni anda)

## Necə İşləyir? (How does it work?)

### TCP Header (20-60 bytes)

```
 0                   1                   2                   3
 0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1
+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
|          Source Port          |       Destination Port        |
+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
|                        Sequence Number                        |
+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
|                    Acknowledgment Number                      |
+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
|  Data |Reserv|C|E|U|A|P|R|S|F|                               |
| Offset| ed   |W|C|R|C|S|S|Y|I|          Window Size           |
|       |      |R|E|G|K|H|T|N|N|                               |
+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
|           Checksum            |         Urgent Pointer        |
+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
|                    Options (if any)                           |
+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
```

**Key flags:**
- **SYN:** Connection baslatma
- **ACK:** Acknowledgment
- **FIN:** Connection bitirme
- **RST:** Connection reset
- **PSH:** Data-ni derhall application-a otur
- **URG:** Urgent data var

### Three-Way Handshake (Connection Establishment)

```
    Client                    Server
      |                         |
      |    SYN (seq=100)        |
      |------------------------>|   1. Client SYN gonderir
      |                         |
      |  SYN-ACK (seq=300,      |
      |          ack=101)       |
      |<------------------------|   2. Server SYN+ACK gonderir
      |                         |
      |    ACK (seq=101,        |
      |        ack=301)         |
      |------------------------>|   3. Client ACK gonderir
      |                         |
      |  CONNECTION ESTABLISHED |
      |<========================>|
```

**Addimlar:**
1. **SYN:** Client random sequence number (ISN=100) ile SYN gonderir
2. **SYN-ACK:** Server oz ISN (300) + client-in seq+1 (ack=101) ile cavab verir
3. **ACK:** Client server-in seq+1 (ack=301) ile tesdiqleyir

**Niye 3-way? 2-way olmaz?** Cunki her iki teref oz sequence number-ini gondermeli ve terefin onu qebul etdiyini tesdiqlemalidir. 2-way-de server-in SYN-inin qebul edildiyi tesdiqlenmir.

### Connection Termination (Four-Way Handshake)

```
    Client                    Server
      |                         |
      |    FIN (seq=500)        |
      |------------------------>|   1. Client FIN gonderir
      |                         |
      |    ACK (ack=501)        |
      |<------------------------|   2. Server ACK gonderir
      |                         |
      |                         |   (Server remaining data gondere biler)
      |                         |
      |    FIN (seq=700)        |
      |<------------------------|   3. Server FIN gonderir
      |                         |
      |    ACK (ack=701)        |
      |------------------------>|   4. Client ACK gonderir
      |                         |
      |   (TIME_WAIT 2*MSL)     |
      |                         |
```

**Niye 4-way?** Cunki connection half-close ola biler. Server ACK gonderdikden sonra hele data gondermesi ola biler, sonra oz FIN-ini gonderir.

### TCP States

```
                              +------------+
                              |   CLOSED   |
                              +-----+------+
                   active open/     |     passive open
                   SYN sent         |     (listen)
                              +-----v------+
                     +------->|   LISTEN   |
                     |        +-----+------+
                     |              | receive SYN
                     |              | send SYN+ACK
                     |        +-----v------+
              +------+-----+  |  SYN_RCVD  |
              |  SYN_SENT  |  +-----+------+
              +------+-----+        | receive ACK
                     |              |
                     | rcv SYN+ACK  |
                     | send ACK     |
                     |        +-----v------+
                     +------->| ESTABLISHED|<----+
                              +-----+------+     |
                                    |            |
                    close/FIN sent  |            |
                              +-----v------+     |
                              |  FIN_WAIT_1|     |
                              +-----+------+     |
                                    | rcv ACK    |
                              +-----v------+     |
                              |  FIN_WAIT_2|     |
                              +-----+------+     |
                                    | rcv FIN    |
                              +-----v------+     |
                              |  TIME_WAIT |     |
                              +-----+------+     |
                                    | 2MSL timer |
                              +-----v------+     |
                              |   CLOSED   |     |
                              +------------+     |
                                                 |
               Server side:                      |
               CLOSE_WAIT --> LAST_ACK ----------+
```

**Muhum state-ler:**
- **ESTABLISHED:** Data transfer aktiv
- **TIME_WAIT:** Client FIN gonderdikden sonra 2*MSL (Maximum Segment Lifetime, typically 60s) gozleyir. Bu, gec gelen paketlerin duzgun islenilmesini temin edir.
- **CLOSE_WAIT:** Server FIN qebul edib, amma hele oz FIN-ini gondermeyi. CLOSE_WAIT-in cox olmasi application-da bug gosterir (socket duzgun close olunmur).

### Reliable Delivery

TCP reliable delivery ucun bir nece mexanizm istifade edir:

**1. Sequence Numbers ve Acknowledgments:**
```
Client sends: [Seq=1, 100 bytes data]
Server ACKs:  [Ack=101]  (men 101-e qeder aldim, novbetini gonder)

Client sends: [Seq=101, 200 bytes data]
Server ACKs:  [Ack=301]
```

**2. Retransmission:**
```
Client: [Seq=1, data] ----> (lost!)
Client: (timeout)
Client: [Seq=1, data] ----> Server (retransmit)
Server: [Ack=101] -------> Client
```

**3. Duplicate Detection:** Sequence number ile duplicate paketler askar olunur ve atilir.

### Flow Control (Sliding Window)

Receiver oz buffer capacity-sini Window Size field ile bildirir:

```
Receiver buffer: 4KB

Client                              Server
  |  [Seq=1, 1KB] ------>          | Window=4KB
  |  [Seq=1025, 1KB] --->          | Window=3KB
  |  [Seq=2049, 1KB] --->          | Window=2KB
  |  <---- [Ack=3073, Win=2KB]     | (processed 2KB)
  |  [Seq=3073, 1KB] --->          | Window=3KB
  |  [Seq=4097, 1KB] --->          | Window=2KB
  
Window=0 olsa, sender dayanir (Zero Window)
Server buffer bosaldiqda Window Update gonderir
```

**Sliding Window mexanizmi:**
```
Bytes: 1  2  3  4  5  6  7  8  9  10 11 12
       [sent+acked][sent,waiting][can send][cannot send]
       ^^^^^^^^^^^^             ^^^^^^^^^^^^
       |                        |
       Window slides right      Window size
       as ACKs arrive           determined by receiver
```

### Congestion Control

Network-de sixisma olmamasi ucun:

**1. Slow Start:**
```
cwnd (congestion window) 1 MSS-den baslar, her ACK-da 2x artir:
Round 1: cwnd = 1 MSS  (1 segment)
Round 2: cwnd = 2 MSS  (2 segments)
Round 3: cwnd = 4 MSS  (4 segments)
Round 4: cwnd = 8 MSS  (8 segments)
...ssthresh-e catanda Congestion Avoidance-a kecir
```

**2. Congestion Avoidance:**
```
cwnd her RTT-de 1 MSS artir (linear growth)
```

**3. Fast Retransmit:**
3 duplicate ACK alinanda timeout gozlemeden retransmit:
```
Client sends: Seq 1, 2, 3, 4, 5
Server receives: 1, 2, (3 lost), 4, 5
Server sends: Ack 3, Ack 3, Ack 3 (3 duplicate ACKs)
Client: Fast retransmit Seq 3
```

**4. Fast Recovery:**
Packet loss oldugda cwnd yarimcaya endirilir (slow start-a qayitmaq evezine).

```
Tahoe (kohne):  Loss -> cwnd=1, slow start
Reno:           Loss -> cwnd=cwnd/2, congestion avoidance
CUBIC (modern): Loss -> cwnd * beta, cubic function ile artirir
```

## Əsas Konseptlər (Key Concepts)

### MSS vs MTU

```
MTU (Maximum Transmission Unit): 1500 bytes (Ethernet)
MSS (Maximum Segment Size): MTU - IP header(20) - TCP header(20) = 1460 bytes

Packet structure:
[Ethernet Header 14B][IP Header 20B][TCP Header 20B][Data <=1460B][FCS 4B]
```

### TCP Keep-Alive

Idle connection-larin canli olub-olmadigini yoxlamaq ucun:
```
Default settings (Linux):
  tcp_keepalive_time = 7200s  (2 saat sonra basla)
  tcp_keepalive_intvl = 75s   (her 75 saniye)
  tcp_keepalive_probes = 9    (9 cehd)
```

### Nagle's Algorithm

Kicik paketlerin gondermesini optimallasdirir:
```
Nagle: Kicik data-ni buffer et, boyuk segment yarananda ve ya ACK geldikde gonder.
Interaktiv application-lar ucun problem yarada biler (latency artir).
Disable: TCP_NODELAY socket option
```

### TCP vs Performance

```
Factors affecting TCP performance:
1. RTT (Round-Trip Time) - 3-way handshake zamani 1.5 RTT cost
2. Bandwidth-delay product = bandwidth * RTT
3. Window size - receiver buffer size limits throughput
4. Congestion - slow start causes initial slowness
5. Head-of-line blocking - bir packet iterse ardinca gelenler gozleyir
```

## PHP/Laravel ilə İstifadə

### PHP Socket Programming

```php
// TCP Server
$server = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
socket_set_option($server, SOL_SOCKET, SO_REUSEADDR, 1);
socket_bind($server, '0.0.0.0', 8080);
socket_listen($server, 5); // backlog = 5

echo "Server listening on port 8080\n";

while (true) {
    $client = socket_accept($server); // Blocking - 3-way handshake here
    
    $data = socket_read($client, 2048);
    echo "Received: $data\n";
    
    socket_write($client, "Hello from server!\n");
    socket_close($client); // 4-way handshake here
}

socket_close($server);
```

```php
// TCP Client
$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

// 3-way handshake happens here
$result = socket_connect($socket, '127.0.0.1', 8080);

socket_write($socket, "Hello from client!");
$response = socket_read($socket, 2048);
echo "Server says: $response\n";

// 4-way handshake happens here
socket_close($socket);
```

### Stream-based TCP in PHP

```php
// Simpler stream-based approach
$server = stream_socket_server('tcp://0.0.0.0:8080', $errno, $errstr);

if (!$server) {
    die("Error: $errstr ($errno)");
}

while ($client = stream_socket_accept($server, -1)) {
    $data = fread($client, 2048);
    fwrite($client, "HTTP/1.1 200 OK\r\nContent-Length: 5\r\n\r\nHello");
    fclose($client);
}

fclose($server);
```

### Laravel TCP Connection Configuration

```php
// Database connection uses TCP
// config/database.php
'mysql' => [
    'driver' => 'mysql',
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => env('DB_PORT', '3306'),
    'options' => [
        PDO::ATTR_PERSISTENT => false,       // Connection pooling
        PDO::ATTR_TIMEOUT => 5,              // TCP connect timeout
        PDO::MYSQL_ATTR_READ_TIMEOUT => 30,  // TCP read timeout
    ],
],

// Redis connection
'redis' => [
    'default' => [
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'port' => env('REDIS_PORT', 6379),
        'timeout' => 2.5,        // TCP connect timeout
        'read_timeout' => 60.0,  // TCP read timeout
        'persistent' => true,    // Keep TCP connection alive
    ],
],
```

### PHP Socket Options

```php
$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

// TCP_NODELAY - Nagle algorithm-i sondur (low latency ucun)
socket_set_option($socket, SOL_TCP, TCP_NODELAY, 1);

// SO_KEEPALIVE - TCP keep-alive aktiv et
socket_set_option($socket, SOL_SOCKET, SO_KEEPALIVE, 1);

// SO_RCVBUF - Receive buffer size (flow control ile elaqeli)
socket_set_option($socket, SOL_SOCKET, SO_RCVBUF, 65536);

// SO_SNDBUF - Send buffer size
socket_set_option($socket, SOL_SOCKET, SO_SNDBUF, 65536);

// SO_REUSEADDR - TIME_WAIT state-deki port-u yeniden istifade et
socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);

// Timeout settings
socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, [
    'sec' => 5,
    'usec' => 0,
]);
```

## Interview Sualları

### Q1: TCP three-way handshake-i izah edin.
**A:** 1) Client SYN (seq=x) gonderir. 2) Server SYN+ACK (seq=y, ack=x+1) gonderir. 3) Client ACK (ack=y+1) gonderir. Her iki teref oz ISN-ini gonderir ve qarsi terefin ISN-ini tesdiqleyir.

### Q2: Niye 3-way handshake lazimdir, 2-way olmaz?
**A:** Her iki teref oz sequence number-ini qarsiya bildirmeli ve onun qebul edildiyini tesdiq almalidir. 2-way-de server-in SYN-i tesdiqlenmir. Hemcinin kohne duplicate SYN-lerin yanlis connection yaratmasinin qarsisini alir.

### Q3: TIME_WAIT state nedir ve niye lazimdir?
**A:** Connection close olandan sonra 2*MSL (adeten 60 saniye) gozlenilir. Sebebleri: 1) Son ACK iterse server FIN-i tekrar gonderir, biz cavab vere bilerik. 2) Kohne connection-dan qalan gec paketlerin yeni connection-a tesir etmesinin qarsisini alir.

### Q4: TCP flow control nece isleyir?
**A:** Receiver Window Size field ile oz buffer capacity-sini bildirir. Sender bu window-dan cox data gondermez. Window 0 olsa, sender dayanir (zero window probe gonderir). Sliding window mexanizmi istifade olunur.

### Q5: TCP congestion control-u izah edin.
**A:** 4 esas mexanizm: 1) Slow Start - cwnd 1-den baslar, exponensial artir (ssthresh-e qeder). 2) Congestion Avoidance - linear artirim. 3) Fast Retransmit - 3 dup ACK-da derhal retransmit. 4) Fast Recovery - loss-da cwnd/2, slow start-a qayitmaq evezine.

### Q6: Head-of-line blocking nedir?
**A:** TCP-de bir paket itse, ardinca gelen paketler application-a verilmir (receiver buffer-de gozleyir) hele ki itirilen paket retransmit olunub catana qeder. Bu HTTP/2-de performance problem yaradir ve HTTP/3 bunu QUIC (UDP-based) ile hell edir.

### Q7: CLOSE_WAIT state-in cox olmasi ne demekdir?
**A:** Application socket-i duzgun close etmir. Remote teref FIN gonderib, biz ACK gondermisik amma oz FIN-imizi gondermirik. Bu resource leak-dir - her CLOSE_WAIT bir file descriptor tutur. Fix: application-da socket.close() duzgun cagirilmalidir.

### Q8: TCP ve UDP-ni ne zaman secmeliyik?
**A:** TCP: reliability lazim olanda (web, file transfer, email, database). UDP: speed ve low latency lazim olanda, bezi packet loss qebul edilende (video streaming, gaming, DNS, VoIP).

## Best Practices

1. **Connection pooling istifade edin:** Her request ucun yeni TCP connection acmaq bahadir (3-way handshake). Database, Redis, HTTP client ucun persistent connections saxlayin.

2. **Timeout-lari duzgun set edin:**
   - Connect timeout: 3-5 saniye
   - Read timeout: application-dan asili (API: 30s, long process: 300s)
   - Idle timeout: connection pool ucun 60-300s

3. **TCP_NODELAY interaktiv app-lar ucun:** Real-time communication lazim olanda Nagle algorithm-i sondur.

4. **SO_REUSEADDR istifade edin:** Server restart zamani TIME_WAIT state-deki port-u yeniden istifade etmek ucun.

5. **Backlog size-i duzgun secin:** `listen(socket, backlog)` - high traffic ucun backlog-u artirun (meselen 1024). Linux: `net.core.somaxconn` sysctl parametri.

6. **Keep-alive production-da:** Long-lived connection-lar ucun TCP keep-alive aktiv edin. Load balancer-ler ve firewall-lar idle connection-lari kese biler.

7. **MTU/MSS optimizasiyasi:** Path MTU Discovery aktiv olsun. Fragmentation performance-i azaldir.
