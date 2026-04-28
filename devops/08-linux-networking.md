# Linux Şəbəkə Komandaları (Middle)

## Nədir? (What is it?)

Linux networking serverlərin şəbəkə konfiqurasiyası, monitoring və troubleshooting-ini əhatə edir. DevOps mühəndisi üçün network interfacelər, routing, firewall, DNS və port management bilmək vacibdir. Laravel application deploy edərkən server networking düzgün konfiqurasiya olunmalıdır.

## Əsas Konseptlər (Key Concepts)

### Network Interface-lər

```bash
# ip əmri (müasir, ifconfig-un əvəzi)
ip addr show                    # Bütün interfacelər
ip addr show eth0               # Konkret interface
ip -4 addr show                 # Yalnız IPv4
ip link show                    # Link layer info
ip link set eth0 up             # Interface-i aktiv et
ip link set eth0 down           # Interface-i deaktiv et

# IP ünvan əlavə et / sil
sudo ip addr add 192.168.1.100/24 dev eth0
sudo ip addr del 192.168.1.100/24 dev eth0

# ifconfig (köhnə, hələ istifadə olunur)
ifconfig                        # Bütün interfacelər
ifconfig eth0                   # Konkret interface

# Routing
ip route show                   # Routing table
ip route add 10.0.0.0/8 via 192.168.1.1   # Route əlavə et
ip route del 10.0.0.0/8                     # Route sil
ip route get 8.8.8.8            # Bir IP-yə hansı route ilə gedilir

# Interface adları:
# eth0, ens3     - Ethernet
# wlan0          - WiFi
# lo             - Loopback (127.0.0.1)
# docker0        - Docker bridge
# veth*          - Virtual ethernet (containers)
```

### netstat / ss - Connections və Ports

```bash
# ss (netstat-ın müasir əvəzi)
ss -tuln                        # TCP/UDP listening ports
ss -tunp                        # Established connections + process
ss -s                           # Summary statistics
ss -t state established         # Established TCP connections
ss dst 192.168.1.100            # Konkret destination
ss sport = :80                  # Source port 80

# ss output nümunəsi:
# State   Recv-Q  Send-Q  Local Address:Port  Peer Address:Port  Process
# LISTEN  0       511     0.0.0.0:80          0.0.0.0:*          nginx
# LISTEN  0       511     0.0.0.0:443         0.0.0.0:*          nginx
# LISTEN  0       128     127.0.0.1:9000      0.0.0.0:*          php-fpm
# LISTEN  0       128     127.0.0.1:3306      0.0.0.0:*          mysqld
# ESTAB   0       0       10.0.0.5:443        203.0.113.1:52341  nginx

# netstat (köhnə)
netstat -tuln                   # Listening ports
netstat -tunp                   # Connections + process
netstat -an | grep :80          # Port 80 connections

# Hansı proses portu istifadə edir?
sudo lsof -i :80               # Port 80 istifadə edən proses
sudo fuser 80/tcp               # Port 80-i tutan PID
```

### DNS Resolution

```bash
# DNS sorğu
dig example.com                 # DNS query (ən ətraflı)
dig example.com +short          # Yalnız IP
dig @8.8.8.8 example.com       # Konkret DNS server ilə
dig example.com MX              # Mail exchange records
dig example.com NS              # Name server records
dig example.com TXT             # TXT records
dig -x 93.184.216.34            # Reverse DNS

# nslookup
nslookup example.com            # DNS query
nslookup example.com 8.8.8.8   # Konkret DNS server ilə

# host
host example.com                # Sadə DNS query
host -t MX example.com         # MX records

# /etc/hosts - Local DNS override
# 127.0.0.1    localhost
# 192.168.1.100  myapp.local
# 10.0.0.5      db.internal

# /etc/resolv.conf - DNS server config
# nameserver 8.8.8.8
# nameserver 8.8.4.4
# search example.com
```

### Firewall (iptables / nftables / ufw)

```bash
# UFW (Uncomplicated Firewall) - Ubuntu-da ən asan
sudo ufw status verbose         # Status
sudo ufw enable                 # Firewall-u aktiv et
sudo ufw disable                # Deaktiv et
sudo ufw default deny incoming  # Default: gələni blokla
sudo ufw default allow outgoing # Default: gedəni burax

# Port açmaq
sudo ufw allow 22/tcp           # SSH
sudo ufw allow 80/tcp           # HTTP
sudo ufw allow 443/tcp          # HTTPS
sudo ufw allow 3306/tcp         # MySQL (diqqətli olun!)
sudo ufw allow from 10.0.0.0/8 to any port 3306  # Yalnız internal network

# Port bağlamaq
sudo ufw deny 8080/tcp
sudo ufw delete allow 8080/tcp  # Rule sil

# iptables (daha güclü, çətin)
sudo iptables -L -n -v          # Bütün rules
sudo iptables -A INPUT -p tcp --dport 80 -j ACCEPT    # Port 80 aç
sudo iptables -A INPUT -p tcp --dport 3306 -s 10.0.0.0/8 -j ACCEPT  # MySQL internal
sudo iptables -A INPUT -p tcp --dport 3306 -j DROP     # MySQL external blokla
sudo iptables -A INPUT -m state --state ESTABLISHED,RELATED -j ACCEPT  # Established

# iptables save
sudo iptables-save > /etc/iptables/rules.v4
sudo iptables-restore < /etc/iptables/rules.v4

# nftables (iptables-in müasir əvəzi)
sudo nft list ruleset           # Bütün rules
sudo nft add table inet filter
sudo nft add chain inet filter input { type filter hook input priority 0 \; }
sudo nft add rule inet filter input tcp dport 80 accept
```

### curl / wget

```bash
# curl - HTTP client
curl https://example.com                    # GET request
curl -I https://example.com                 # Yalnız headers
curl -X POST https://api.example.com/users  # POST
curl -X POST https://api.example.com/users \
    -H "Content-Type: application/json" \
    -H "Authorization: Bearer TOKEN" \
    -d '{"name":"Orkhan","email":"test@test.com"}'

curl -o file.zip https://example.com/file.zip   # Download (adla)
curl -O https://example.com/file.zip            # Download (original ad)
curl -s -o /dev/null -w "%{http_code}" https://example.com  # Status code
curl -v https://example.com                     # Verbose (debug)
curl -k https://self-signed.example.com         # SSL verify skip
curl --connect-timeout 5 --max-time 10 https://example.com  # Timeout

# wget - File downloader
wget https://example.com/file.zip              # Download
wget -q https://example.com/file.zip           # Quiet mode
wget -c https://example.com/large.zip          # Resume download
wget --mirror https://example.com              # Mirror site
```

### Network Troubleshooting

```bash
# Connectivity test
ping -c 4 google.com            # 4 paket göndər
ping -c 4 -W 2 10.0.0.5        # 2 saniyə timeout

# Traceroute - paketlərin yolunu göstər
traceroute google.com
traceroute -n google.com        # DNS resolve etmə (sürətli)
mtr google.com                  # Real-time traceroute

# Port test
nc -zv example.com 80           # Port açıq-mı?
nc -zv example.com 22           # SSH portu test
nc -zv example.com 1-1000       # Port range scan
telnet example.com 80           # Telnet ilə port test

# tcpdump - Packet capture
sudo tcpdump -i eth0 port 80    # Port 80 traffic
sudo tcpdump -i eth0 host 10.0.0.5  # Konkret host
sudo tcpdump -i eth0 -w capture.pcap  # Fayla yaz
sudo tcpdump -i eth0 -n port 443 -c 100  # 100 paket tut

# Network performance
iperf3 -s                       # Server mode
iperf3 -c server-ip             # Client mode (bandwidth test)
```

## Praktiki Nümunələr (Practical Examples)

### Laravel Server Network Setup

```bash
#!/bin/bash
# server-network-setup.sh

# Firewall setup
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow 22/tcp comment "SSH"
sudo ufw allow 80/tcp comment "HTTP"
sudo ufw allow 443/tcp comment "HTTPS"

# MySQL yalnız internal network-dən
sudo ufw allow from 10.0.0.0/8 to any port 3306 comment "MySQL Internal"

# Redis yalnız localhost
# Redis default 127.0.0.1-da dinləyir, firewall-a ehtiyac yoxdur

# PHP-FPM yalnız localhost (socket istifadə edəndə port lazım deyil)
# TCP istifadə edəndə:
sudo ufw allow from 127.0.0.1 to any port 9000 comment "PHP-FPM"

sudo ufw --force enable
sudo ufw status verbose
```

### Health Check Script

```bash
#!/bin/bash
# healthcheck.sh

APP_URL="https://example.com"
ENDPOINTS=(
    "/api/health"
    "/"
    "/login"
)

for endpoint in "${ENDPOINTS[@]}"; do
    STATUS=$(curl -s -o /dev/null -w "%{http_code}" --max-time 10 "${APP_URL}${endpoint}")
    RESPONSE_TIME=$(curl -s -o /dev/null -w "%{time_total}" --max-time 10 "${APP_URL}${endpoint}")

    if [ "$STATUS" -ne 200 ]; then
        echo "FAIL: ${endpoint} returned ${STATUS}"
    else
        echo "OK: ${endpoint} - ${RESPONSE_TIME}s"
    fi
done

# Port checks
for port in 80 443 3306 6379; do
    if ss -tuln | grep -q ":${port} "; then
        echo "OK: Port ${port} is listening"
    else
        echo "FAIL: Port ${port} is NOT listening"
    fi
done
```

### SSL Certificate Check

```bash
#!/bin/bash
# check-ssl.sh

DOMAIN="example.com"
EXPIRY=$(echo | openssl s_client -servername "$DOMAIN" -connect "$DOMAIN:443" 2>/dev/null | openssl x509 -noout -enddate | cut -d= -f2)
EXPIRY_EPOCH=$(date -d "$EXPIRY" +%s)
NOW_EPOCH=$(date +%s)
DAYS_LEFT=$(( (EXPIRY_EPOCH - NOW_EPOCH) / 86400 ))

echo "SSL Certificate for $DOMAIN expires in $DAYS_LEFT days ($EXPIRY)"

if [ "$DAYS_LEFT" -lt 30 ]; then
    echo "WARNING: Certificate expiring soon!"
fi
```

## PHP/Laravel ilə İstifadə

### Laravel Trusted Proxies (Load Balancer arxasında)

```php
// app/Http/Middleware/TrustProxies.php
class TrustProxies extends Middleware
{
    protected $proxies = '*';  // Bütün proxy-lərə güvən (LB arxasında)
    // və ya konkret IP:
    // protected $proxies = ['10.0.0.1', '10.0.0.2'];

    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO;
}
```

### Laravel CORS konfiqurasiyası

```php
// config/cors.php
return [
    'paths' => ['api/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => ['https://frontend.example.com'],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 3600,
    'supports_credentials' => true,
];
```

### Server Connection Test (Artisan Command)

```php
// app/Console/Commands/NetworkCheck.php
class NetworkCheck extends Command
{
    protected $signature = 'network:check';

    public function handle(): int
    {
        $checks = [
            'Database' => fn() => DB::connection()->getPdo(),
            'Redis' => fn() => Redis::ping(),
            'Mail' => fn() => gethostbyname(config('mail.mailers.smtp.host')),
            'S3' => fn() => Storage::disk('s3')->exists('test'),
        ];

        foreach ($checks as $name => $check) {
            try {
                $check();
                $this->info("$name: OK");
            } catch (\Throwable $e) {
                $this->error("$name: FAIL - {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
```

## Interview Sualları

### Q1: TCP və UDP fərqi nədir?
**Cavab:** TCP connection-oriented, reliable, ordered delivery, flow/congestion control var. Three-way handshake (SYN, SYN-ACK, ACK). UDP connectionless, unreliable, sırasız, sürətlidir. TCP: HTTP, SSH, MySQL üçün. UDP: DNS, video streaming, gaming üçün.

### Q2: Bir port-un hansı proses tərəfindən istifadə edildiyini necə taparsınız?
**Cavab:** `ss -tulnp | grep :80`, `sudo lsof -i :80`, `sudo fuser 80/tcp`. ss ən sürətli yoldur, lsof daha ətraflı məlumat verir.

### Q3: DNS resolution necə işləyir?
**Cavab:** 1) Browser cache yoxlayır 2) OS cache (/etc/hosts) yoxlayır 3) Resolver DNS serverə sorğu göndərir 4) Recursive resolver root -> TLD -> authoritative server-ə sorğu göndərir 5) IP cavabı cache olunur (TTL müddətinə).

### Q4: iptables CHAIN-ləri nədir?
**Cavab:** INPUT - serverə gələn paketlər, OUTPUT - serverdən çıxan paketlər, FORWARD - serverdən keçən paketlər (router). Target-lər: ACCEPT (burax), DROP (blokt, cavab yox), REJECT (blokt, cavab göndər). Rules yuxarıdan aşağı yoxlanılır.

### Q5: Server-ə SSH ilə qoşula bilmirsinizsə nə yoxlayarsınız?
**Cavab:** 1) `ping server-ip` - network reachability 2) `nc -zv server-ip 22` - SSH port açıq-mı 3) Server-dəki firewall rules 4) SSH service status (`systemctl status sshd`) 5) `/var/log/auth.log` - authentication logs 6) Security group rules (cloud) 7) DNS resolution (`dig server-hostname`).

### Q6: Load balancer arxasında Laravel necə konfiqurasiya olunur?
**Cavab:** TrustProxies middleware-da proxy IP-ləri təyin olunur. X-Forwarded-For, X-Forwarded-Proto header-lar güvənilir. `APP_URL` https ilə qoyulur. Force HTTPS middleware əlavə olunur. Health check endpoint yaradılır.

## Best Practices

1. **Firewall first** - Yalnız lazımi portları açın, default deny
2. **Internal network** - DB, Redis yalnız internal network-dən əlçatan olsun
3. **SSH key auth** - Password authentication disable edin
4. **DNS caching** - Local DNS cache (systemd-resolved) istifadə edin
5. **Monitoring** - Port və connectivity monitoring qurun
6. **SSL everywhere** - Internal traffic da encrypt olunmalıdır
7. **Rate limiting** - DDoS qoruma üçün rate limiting tətbiq edin
8. **Network segmentation** - App, DB, cache ayrı subnet-lərdə olsun
9. **Log network events** - Connection attempts, firewall blocks log olsun
10. **Regular audits** - Açıq portları və firewall rules-ı mütəmadi yoxlayın

---

## Praktik Tapşırıqlar

1. Production server-in şəbəkə konfiqurasiyasını auditləyin: `ip a`, `ip route`, `ss -tlnp` ilə aktiv interfeys, default gateway, dinlənən portları çıxarın; gözlənilməyən port açıq olarsa araşdırın
2. UFW firewall qurun: default deny all in, allow SSH (22), HTTP (80), HTTPS (443), PostgreSQL yalnız internal IP-dən (192.168.0.0/24); `ufw status verbose` ilə yoxlayın; test üçün 80-i müvəqqəti bağlayıb Laravel-in cavab vermədiyini görün
3. `tcpdump` ilə Laravel API sorğusunu capture edin: `tcpdump -i eth0 -nn 'host <api-server> and port 443' -w api.pcap`; Wireshark-da TLS Client Hello-nu tapın; certificate details-ı oxuyun
4. DNS problem debug senariyosu: `/etc/resolv.conf` nameserver-ini 1.1.1.1-ə dəyişin, `dig @1.1.1.1 domain.com`, `dig @8.8.8.8 domain.com` ilə fərqi müqayisə edin; `nslookup` ilə MX record-ları yoxlayın
5. `curl` ilə Laravel API-ni komanda xəttindən test edin: POST request + JSON body + Bearer token + custom header; response header-larını (`-I` flag), redirect chain-i (`-L`) izləyin; response time-ı ölçün (`-w "%{time_total}"`)
6. `iptables` ilə port forwarding qurun: server-in 8080 port-unu daxili şəbəkədəki başqa host-un 80-inə yönləndir (`PREROUTING DNAT`); `iptables-save` ilə persist edin; reboot-dan sonra işlədiyini yoxlayın

## Əlaqəli Mövzular

- [Şəbəkə Əsasları](02-networking-basics.md) — OSI, TCP/IP, DNS, HTTP/HTTPS
- [Linux Əsasları](01-linux-basics.md) — fayl sistemi, əsas komandalar
- [Nginx](11-nginx.md) — reverse proxy, firewall ilə birlikdə konfiqurasiya
- [SSL/TLS](13-ssl-tls.md) — mTLS, HTTPS, certificate verification
- [AWS Əsasları](14-aws-basics.md) — Security Groups, VPC networking
