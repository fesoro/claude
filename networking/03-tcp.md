# TCP - Transmission Control Protocol (Junior)

## İcmal

TCP (Transmission Control Protocol) Transport Layer-də işləyən connection-oriented, reliable protokoldur. Data-nın tam, sıralı və xətasız çatdırılmasını təmin edir. İnternetdəki trafikin böyük hissəsi (HTTP, HTTPS, FTP, SMTP, SSH) TCP üzərində işləyir.

Əsas xüsusiyyətlər:
- Connection-oriented (əlaqə qurulmalıdır)
- Reliable delivery (təmin olunmuş çatdırılma)
- Ordered (sıralı çatdırılma)
- Flow control (axın kontrolu)
- Congestion control (sıxışma kontrolu)
- Full-duplex (iki istiqamətli eyni anda)

## Niyə Vacibdir

Hər database connection, Redis sorğusu, HTTP API çağırışı TCP üzərindən gedir. Connection pooling-in niyə lazım olduğunu, timeout-ların nə üçün konfiqurasiya edildiyini, "connection reset by peer" kimi xətaların niyə baş verdiyini başa düşmək üçün TCP-nin necə işlədiyini bilmək lazımdır. High-load sistemlərdə TIME_WAIT state, CLOSE_WAIT yığılması, ya da backlog overflow kimi production problemləri TCP anlayışı olmadan həll edilmir.

## Əsas Anlayışlar

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

Key flags:
- **SYN:** Connection başlatma
- **ACK:** Acknowledgment
- **FIN:** Connection bitirmə
- **RST:** Connection reset
- **PSH:** Data-nı dərhal application-a ötür
- **URG:** Urgent data var

### Three-Way Handshake (Connection Establishment)

```
    Client                    Server
      |                         |
      |    SYN (seq=100)        |
      |------------------------>|   1. Client SYN göndərir
      |                         |
      |  SYN-ACK (seq=300,      |
      |          ack=101)       |
      |<------------------------|   2. Server SYN+ACK göndərir
      |                         |
      |    ACK (seq=101,        |
      |        ack=301)         |
      |------------------------>|   3. Client ACK göndərir
      |                         |
      |  CONNECTION ESTABLISHED |
      |<========================>|
```

Addımlar:
1. **SYN:** Client random sequence number (ISN=100) ilə SYN göndərir
2. **SYN-ACK:** Server öz ISN (300) + client-in seq+1 (ack=101) ilə cavab verir
3. **ACK:** Client server-in seq+1 (ack=301) ilə təsdiqləyir

Niyə 3-way, 2-way olmaz? Çünki hər iki tərəf öz sequence number-ini göndərməli və tərəfin onu qəbul etdiyini təsdiqləməlidir. 2-way-də server-in SYN-inin qəbul edildiyi təsdiqlənmir.

### Connection Termination (Four-Way Handshake)

```
    Client                    Server
      |                         |
      |    FIN (seq=500)        |
      |------------------------>|   1. Client FIN göndərir
      |                         |
      |    ACK (ack=501)        |
      |<------------------------|   2. Server ACK göndərir
      |                         |
      |                         |   (Server remaining data göndərə bilər)
      |                         |
      |    FIN (seq=700)        |
      |<------------------------|   3. Server FIN göndərir
      |                         |
      |    ACK (ack=701)        |
      |------------------------>|   4. Client ACK göndərir
      |                         |
      |   (TIME_WAIT 2*MSL)     |
      |                         |
```

Niyə 4-way? Çünki connection half-close ola bilər. Server ACK göndərdikdən sonra hələ data göndərməsi ola bilər, sonra öz FIN-ini göndərir.

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

Mühüm state-lər:
- **ESTABLISHED:** Data transfer aktiv
- **TIME_WAIT:** Client FIN göndərdikdən sonra 2*MSL (adətən 60s) gözləyir. Bu, gec gələn paketlərin düzgün işlənilməsini təmin edir.
- **CLOSE_WAIT:** Server FIN qəbul edib, amma hələ öz FIN-ini göndərməyib. CLOSE_WAIT-in çox olması application-da bug göstərir (socket düzgün close olunmur).

### Reliable Delivery

TCP reliable delivery üçün bir neçə mexanizm istifadə edir:

**1. Sequence Numbers və Acknowledgments:**
```
Client sends: [Seq=1, 100 bytes data]
Server ACKs:  [Ack=101]  (101-ə qədər aldım, növbətini göndər)

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

**3. Duplicate Detection:** Sequence number ilə duplicate paketlər aşkar olunur və atılır.

### Flow Control (Sliding Window)

Receiver öz buffer capacity-sini Window Size field ilə bildirir:

```
Receiver buffer: 4KB

Client                              Server
  |  [Seq=1, 1KB] ------>          | Window=4KB
  |  [Seq=1025, 1KB] --->          | Window=3KB
  |  [Seq=2049, 1KB] --->          | Window=2KB
  |  <---- [Ack=3073, Win=2KB]     | (processed 2KB)
  |  [Seq=3073, 1KB] --->          | Window=3KB
  |  [Seq=4097, 1KB] --->          | Window=2KB
  
Window=0 olsa, sender dayanır (Zero Window)
Server buffer boşaldıqda Window Update göndərir
```

### Congestion Control

Network-də sıxışma olmaması üçün:

**1. Slow Start:**
```
cwnd (congestion window) 1 MSS-dən başlar, hər ACK-da 2x artır:
Round 1: cwnd = 1 MSS  (1 segment)
Round 2: cwnd = 2 MSS  (2 segments)
Round 3: cwnd = 4 MSS  (4 segments)
Round 4: cwnd = 8 MSS  (8 segments)
...ssthresh-ə çatanda Congestion Avoidance-a keçir
```

**2. Congestion Avoidance:**
```
cwnd hər RTT-də 1 MSS artır (linear growth)
```

**3. Fast Retransmit:**
3 duplicate ACK alınanda timeout gözləmədən retransmit:
```
Client sends: Seq 1, 2, 3, 4, 5
Server receives: 1, 2, (3 lost), 4, 5
Server sends: Ack 3, Ack 3, Ack 3 (3 duplicate ACKs)
Client: Fast retransmit Seq 3
```

**4. Fast Recovery:**
Packet loss olduqda cwnd yarıya endirilir (slow start-a qayıtmaq əvəzinə).

```
Tahoe (köhnə):  Loss -> cwnd=1, slow start
Reno:           Loss -> cwnd=cwnd/2, congestion avoidance
CUBIC (modern): Loss -> cwnd * beta, cubic function ilə artırır
```

### MSS vs MTU

```
MTU (Maximum Transmission Unit): 1500 bytes (Ethernet)
MSS (Maximum Segment Size): MTU - IP header(20) - TCP header(20) = 1460 bytes

Packet structure:
[Ethernet Header 14B][IP Header 20B][TCP Header 20B][Data <=1460B][FCS 4B]
```

### TCP Keep-Alive

Idle connection-ların canlı olub-olmadığını yoxlamaq üçün:
```
Default settings (Linux):
  tcp_keepalive_time = 7200s  (2 saat sonra başla)
  tcp_keepalive_intvl = 75s   (hər 75 saniyə)
  tcp_keepalive_probes = 9    (9 cəhd)
```

### Nagle's Algorithm

Kiçik paketlərin göndərməsini optimallaşdırır:
```
Nagle: Kiçik data-nı buffer et, böyük segment yarananda və ya ACK gəldikdə göndər.
İnteraktiv application-lar üçün problem yarada bilər (latency artır).
Disable: TCP_NODELAY socket option
```

## Praktik Baxış

**Real layihələrdə istifadəsi:**
- Database connection pool-u əslində hazır TCP connection-ların pool-udur; yeni connection açmaq 3-way handshake dəyərinə başa gəlir
- Load balancer-lər TCP connection-ı terminate edib yeni bir connection açır (L4 LB) ya da HTTP-ni parse edir (L7 LB)
- Nginx `keepalive` direktivi upstream TCP connection-larını yenidən istifadə edir

**Trade-off-lar:**
- TCP reliable-dır amma latency ödəyir — real-time apps (gaming, video) UDP seçir
- 3-way handshake hər yeni connection-a 1.5 RTT əlavə edir — buna görə connection pooling vacibdir
- Head-of-line blocking: bir paket itərsə ardınca gələn paketlər gözləyir (HTTP/2 bunu TCP level-də həll edə bilmir, HTTP/3 QUIC ilə həll edir)

**Common mistakes:**
- `SO_REUSEADDR` olmadan server restart edəndə "Address already in use" xətası — TIME_WAIT state port-u tutur
- CLOSE_WAIT-in çox olması — application socket-i close etmir, file descriptor leak
- Çox uzun timeout — yavaş client bütün worker-ləri blok edir
- Connection pool minimum size-ı sıfır — hər spike-da handshake delay

**Anti-pattern:** `PDO::ATTR_PERSISTENT => true` lazım olmayan yerlərdə — persistent connection-lar köhnə state saxlaya bilər, transaction-lar açıq qala bilər.

## Nümunələr

### Ümumi Nümunə

Database connection pool-da 10 TCP connection hazır gözləyir. `$user = User::find(1)` çağırıldıqda pool-dan mövcud TCP connection götürülür, SQL query göndərilir, cavab gəlir, connection pool-a qaytarılır. Yeni handshake yoxdur.

### Kod Nümunəsi

PHP Socket ilə TCP Server:

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

Laravel TCP Connection Configuration:

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

PHP Socket Options:

```php
$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

// TCP_NODELAY - Nagle algorithm-i söndür (low latency üçün)
socket_set_option($socket, SOL_TCP, TCP_NODELAY, 1);

// SO_KEEPALIVE - TCP keep-alive aktiv et
socket_set_option($socket, SOL_SOCKET, SO_KEEPALIVE, 1);

// SO_RCVBUF - Receive buffer size (flow control ilə əlaqəli)
socket_set_option($socket, SOL_SOCKET, SO_RCVBUF, 65536);

// SO_SNDBUF - Send buffer size
socket_set_option($socket, SOL_SOCKET, SO_SNDBUF, 65536);

// SO_REUSEADDR - TIME_WAIT state-dəki portu yenidən istifadə et
socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);

// Timeout settings
socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, [
    'sec' => 5,
    'usec' => 0,
]);
```

## Praktik Tapşırıqlar

**Tapşırıq 1: TCP state-lərini müşahidə edin**

```bash
# ESTABLISHED, TIME_WAIT, CLOSE_WAIT vəziyyətlərini görün
ss -tan | grep -E 'ESTABLISHED|TIME_WAIT|CLOSE_WAIT'

# PHP-FPM process-lərinin TCP connection-larını sayın
ss -tan state established | grep ':9000'

# Neçə TIME_WAIT var?
ss -tan | grep TIME_WAIT | wc -l
```

**Tapşırıq 2: Wireshark ilə handshake analizi**

1. `tcpdump -i any -n port 80` işlədin
2. `curl http://example.com` göndərin
3. SYN, SYN-ACK, ACK paketlərini müşahidə edin
4. 4-way FIN/ACK-ı da görəcəksiniz

**Tapşırıq 3: Connection pool konfiqurasiyası**

Laravel-in database pool ölçüsünü tənzimləyin:

```php
// config/database.php
'mysql' => [
    'pool' => [
        'min' => 5,   // Minimum hazır connection
        'max' => 20,  // Maximum concurrent connection
    ],
    'options' => [
        PDO::ATTR_TIMEOUT => 3,             // Connect timeout: 3s
        PDO::MYSQL_ATTR_READ_TIMEOUT => 30, // Read timeout: 30s
    ],
],
```

Sonra `ab -n 1000 -c 50 http://localhost/api/users` ilə load test edin, `ss -tan` ilə connection state-lərini izləyin.

## Əlaqəli Mövzular

- [OSI Model](01-osi-model.md)
- [UDP](04-udp.md)
- [HTTP Protocol](05-http-protocol.md)
- [HTTPS, SSL/TLS](06-https-ssl-tls.md)
- [Network Timeouts](42-network-timeouts.md)
- [Network Troubleshooting](30-network-troubleshooting.md)
