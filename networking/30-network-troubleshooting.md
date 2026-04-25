# Network Troubleshooting (Senior)

## İcmal

Network troubleshooting şəbəkə problemlərini identificasiya, diaqnoz və həll etmə prosesidir. OSI modelinə görə sistematik yanaşma əsasında — problem hansı layer-dədir: physical, data link, network, transport, application? — düzgün tool seçilir və həll edilir.

```
Troubleshooting flow:

1. Define problem — Nə işləmir? Nə vaxtdan?
2. Gather info    — Error messages, logs, affected users
3. Establish theory — Hipotez yarat
4. Test theory    — Tools ilə yoxla (ping, traceroute, dig)
5. Implement fix  — Əsas səbəbi həll et
6. Verify         — Problem həll olundu?
7. Document       — Gələcək üçün yaz
```

## Niyə Vacibdir

Production outage-ı düzgün tool seçmədən həll etmək mümkün deyil. Backend developer network problemlərini özü diaqnoz edə bilməlidir — infrastructure komanda gəlməyi gözləmək olmaz. Latency, packet loss, DNS xətaları, TLS problemləri — bunların hamısını tez müəyyən etmək üçün sistematik yanaşma şərtdir.

## Əsas Anlayışlar

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

curl -w breakdown:
  time_namelookup   = DNS
  time_connect      = DNS + TCP
  time_appconnect   = DNS + TCP + TLS
  time_pretransfer  = above + request sent
  time_starttransfer = above + first byte received
  time_total        = entire request
```

### Error Code Taxonomy

```
HTTP:
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

MTR ilə hop analizi:
  Loss at intermediate hops without loss at destination = router filtering ICMP (OK)
  Loss at destination + hops = real problem
```

## Praktik Baxış

### Tool-lar

**ping (ICMP Echo):**
```bash
ping -c 5 google.com           # 5 paket göndər
ping -M do -s 1472 example.com # MTU discovery
ping -c 100 example.com | tail -2  # Packet loss analizi
# Qeyd: Bəzi hostlar ICMP bloklar, "no response" ≠ "host down"
```

**traceroute / MTR:**
```bash
traceroute -n google.com       # DNS olmadan (sürətli)
traceroute -T -p 443 example.com  # TCP SYN (ICMP bloklandıqda)
mtr google.com                 # Real-time traceroute + statistika
mtr -T -P 443 -c 100 google.com  # TCP mode, 100 probe
```

**nslookup / dig:**
```bash
dig google.com                 # A record
dig google.com MX              # MX records
dig @8.8.8.8 google.com        # Spesifik DNS server
dig +trace google.com          # Root-dan trace
dig -x 8.8.8.8                 # Reverse DNS
```

**netcat (nc):**
```bash
nc -zv example.com 80          # TCP port yoxla
nc -zvu example.com 53         # UDP port 53
nc -z example.com 20-30        # Port range scan
```

**tcpdump:**
```bash
tcpdump -i eth0 port 80        # Bütün HTTP
tcpdump 'tcp and host example.com and not port 22'
tcpdump -i any -n host 1.2.3.4 and port 5432 -w db.pcap
```

**ss / ip:**
```bash
ss -tuln          # Listening TCP/UDP
ss -tp            # TCP + process adları
ip addr show      # İnterface-lər
ip route          # Routing table
```

**openssl:**
```bash
openssl s_client -connect example.com:443
openssl x509 -in cert.pem -text -noout
```

### Common Issues Diagnosis

```
Issue 1: "Cannot connect to server"
  1. ping server              — network reachable?
  2. nc -zv server port       — port open?
  3. traceroute server        — route OK?
  4. dig server               — DNS correct?
  5. server firewall yoxla
  6. server service running?

Issue 2: "Slow website"
  1. curl -w timing           — harada yavaşdır?
  2. mtr target               — packet loss var?
  3. dig target               — DNS yavaşdır?
  4. tcpdump retransmissions  — network problemi?

Issue 3: "SSL/TLS errors"
  1. openssl s_client -connect host:443
     Cert expiry, chain, CN match yoxla
  2. System time yoxla (cert validation)
  3. curl -v ilə handshake izlə

Issue 4: "DNS not resolving"
  1. cat /etc/resolv.conf     — DNS servers
  2. dig @8.8.8.8 domain      — fərqli DNS ilə sına
  3. dig +trace domain        — full chain
```

### Trade-offs

- `tcpdump` production-da sudo tələb edir, performance impact var — filtrlə istifadə et, broad capture etmə
- `mtr` uzun müddət çalışdıqda çox trafik yaradır — `-c` ilə limit qoy
- Wireshark GUI-də offline analiz daha məhsuldardır: `tcpdump -w capture.pcap` → Wireshark-da aç

### Anti-patterns

- Packet capture etmədən "network OK" demək
- Application log oxumadan birbaşa tcpdump istifadə etmək — application layer first
- Production-da geniş tcpdump filter — `host X and port Y` kimi spesifik et
- Yalnız ping ilə "host down" qərarı vermək — ICMP bloklanmış ola bilər

## Nümunələr

### Ümumi Nümunə

```
MTR output oxumaq:

 Host                    Loss%   Avg
 1. router.local          0.0%   1.3ms
 2. isp-gw               10.0%  10.3ms
 3. ???                 100.0%   0.0ms
 4. 142.250.80.46          0.0%  20.3ms

Hop 2-də 10% loss, amma hop 4-də 0% — hop 2 sadəcə ICMP cavab
vermir (firewall). Real problem yoxdur.
Hop 3-də 100% — bu router ICMP filtrlir. Yenə normal.
```

### Kod Nümunəsi

**Laravel Connection Test Command:**
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
            'DB'    => fn() => $this->checkDb(),
            'Redis' => fn() => $this->checkRedis(),
            'S3'    => fn() => $this->checkHttp('https://s3.amazonaws.com'),
            'DNS'   => fn() => $this->checkDns('google.com'),
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
```

**HTTP Request Timing:**
```php
use Illuminate\Support\Facades\Http;

$response = Http::withOptions([
    'on_stats' => function (\GuzzleHttp\TransferStats $stats) {
        $handler = $stats->getHandlerStats();
        logger()->info('HTTP Timing', [
            'url'           => $stats->getEffectiveUri(),
            'total'         => $stats->getTransferTime(),
            'dns'           => $handler['namelookup_time'] ?? null,
            'connect'       => $handler['connect_time'] ?? null,
            'tls'           => $handler['appconnect_time'] ?? null,
            'starttransfer' => $handler['starttransfer_time'] ?? null,
        ]);
    }
])->get('https://api.example.com/users');
```

**Port Checker:**
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

if (!isPortOpen('db.example.com', 5432)) {
    logger()->alert('Database unreachable');
}
```

**Health Check Endpoint:**
```php
// app/Http/Controllers/HealthController.php
class HealthController extends Controller
{
    public function check()
    {
        $status    = ['app' => 'ok', 'timestamp' => now()->toIso8601String(), 'checks' => []];
        $overallOk = true;

        try {
            DB::connection()->getPdo();
            $status['checks']['database'] = 'ok';
        } catch (\Exception $e) {
            $status['checks']['database'] = 'fail: ' . $e->getMessage();
            $overallOk = false;
        }

        try {
            Redis::ping();
            $status['checks']['redis'] = 'ok';
        } catch (\Exception $e) {
            $status['checks']['redis'] = 'fail: ' . $e->getMessage();
            $overallOk = false;
        }

        $free    = disk_free_space('/');
        $total   = disk_total_space('/');
        $percent = round((1 - $free / $total) * 100, 2);
        $status['checks']['disk'] = "{$percent}% used";
        if ($percent > 90) $overallOk = false;

        return response()->json($status, $overallOk ? 200 : 503);
    }
}
```

## Praktik Tapşırıqlar

1. **MTU problemi simulyasiyası:** `ping -M do -s 1472 target` ilə başla, packet size-ı azaltaraq hansı ölçüdə pass etdiyini tap. MTU-nu hesabla (header = 28 byte).

2. **DNS propagation yoxla:** `dig @8.8.8.8 domain`, `dig @1.1.1.1 domain`, `dig @208.67.222.222 domain` — eyni cavabı verirlərmi? Fərq varsa, propagation hələ tamamlanmayıb.

3. **tcpdump ilə HTTP debug:** `tcpdump -A -s 0 'tcp port 80 and host api.example.com'` — sadə HTTP request-in header-lərini oxu.

4. **Production health endpoint yaz:** yuxarıdakı `HealthController`-i öz layihəndə qur, DB, Redis, disk yoxlamalarını əlavə et, `/health` route-una qoş.

5. **`curl -w` timing:** aşağıdakı format faylı yarat, real endpoint-ə qarşı çalışdır, ən çox vaxt harada itirilir müəyyən et:
   ```bash
   curl -w "dns:%{time_namelookup} connect:%{time_connect} tls:%{time_appconnect} total:%{time_total}\n" \
        -o /dev/null -s https://api.example.com/health
   ```

6. **Network problem runbook:** komandanız üçün "connection refused", "timeout", "DNS failure" hallarına step-by-step runbook yaz.

## Əlaqəli Mövzular

- [HTTP Protocol](05-http-protocol.md)
- [HTTPS & SSL/TLS](06-https-ssl-tls.md)
- [DNS](07-dns.md)
- [Network Security](26-network-security.md)
- [Network Timeouts](42-network-timeouts.md)
