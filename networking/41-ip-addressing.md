# IP Addressing for Backend Developers (Junior)

## İcmal

Backend developer kimi siz hər gün IP ünvanları ilə işləyirsiniz — server konfiqurasiyası, firewall qaydaları, log analizi, servis kommunikasiyası. Bu mövzu network teoriyası deyil, **gündəlik işdə lazım olan praktik bilikdir**.

## Niyə Vacibdir

- Server deploy edəndə hansı IP-yə bind etmək lazımdır?
- `0.0.0.0` vs `127.0.0.1` fərqi nədir?
- Docker container-lar arası necə kommunikasiya edir?
- Log-da görünən IP-nin sahibi kimdir?
- Firewall/Security group-da hansı CIDR bloku yazmalısan?

## Əsas Anlayışlar

### IPv4 Strukturu

```
192.168.1.100
│   │   │ │
│   │   │ └── Host hissəsi
│   │   └──── Network hissəsi
│   └──────── Octet (0-255)
└──────────── 4 octet, nöqtə ilə ayrılmış
```

32-bit ədəddir. Toplam ~4.3 milyard unikal ünvan. NAT sayəsində çatışır.

### CIDR Notation

```
192.168.1.0/24
            └── Prefix length: ilk 24 bit network hissəsidir

/24 → 256 ünvan (254 usable): 192.168.1.0 - 192.168.1.255
/16 → 65,536 ünvan: 192.168.0.0 - 192.168.255.255
/8  → 16,777,216 ünvan: 10.0.0.0 - 10.255.255.255
/32 → Tək bir ünvan (bir host)
/0  → Bütün internet
```

### Private IP Ranges (RFC 1918)

```
10.0.0.0/8        → 10.x.x.x           (büyük korporativ şəbəkələr, cloud VPCs)
172.16.0.0/12     → 172.16.x.x - 172.31.x.x  (orta ölçülü şəbəkələr)
192.168.0.0/16    → 192.168.x.x        (ev/ofis şəbəkələri)
```

Bu ünvanlar **internete route olunmur**. NAT gateway vasitəsilə çıxış edir.

### Xüsusi Ünvanlar

```
127.0.0.1         → Loopback (localhost) — özünə göndər
0.0.0.0           → "Bütün interfeyslər" — server bind üçün
255.255.255.255   → Broadcast — bütün cihazlara
169.254.x.x       → Link-local (DHCP çalışmayanda) — problem işarəsi
```

### Port Numbers

```
Well-known ports (0-1023):    root lazımdır (Linux)
  22   → SSH
  80   → HTTP
  443  → HTTPS
  3306 → MySQL
  5432 → PostgreSQL
  6379 → Redis

Registered ports (1024-49151): tətbiqlərin standart portları
  8080 → HTTP alternativi (dev)
  8443 → HTTPS alternativi (dev)
  9000 → PHP-FPM

Ephemeral ports (49152-65535): OS-in dinamik yığdığı portlar
```

### NAT (Network Address Translation)

```
Private Network              NAT Gateway           Internet
192.168.1.10:54321  ──────► 203.0.113.5:40001 ──────► google.com:443
192.168.1.20:54322  ──────► 203.0.113.5:40002 ──────► github.com:443

← Cavab NAT table-a görə doğru host-a qaytarılır
```

**NAT niyə vacibdir:** Bütün cloud server-lar private IP-dədir. Xarici dünya public IP-ni görür. AWS/GCP/DigitalOcean hamısı belə işləyir.

## Praktik Baxış

### Serverə Bind Etmək

```php
// PHP built-in server
php -S 0.0.0.0:8000   // Bütün interfeyslərə listen et (docker/remote access)
php -S 127.0.0.1:8000  // Yalnız localhost

// Laravel .env
APP_URL=http://0.0.0.0  // ❌ Yanlış — URL üçün deyil
APP_URL=http://myapp.local  // ✓ Düzgün
```

### Docker Network

```
Docker default bridge network: 172.17.0.0/16
  container1: 172.17.0.2
  container2: 172.17.0.3

docker-compose default: 172.18.0.0/16 (və ya növbəti available subnet)

Container-lar arası kommunikasiya:
  - Eyni network: service adı ilə (db, redis, app)
  - Host maşına çıxmaq: host.docker.internal (Mac/Windows)
```

### Cloud Security Groups

```
Inbound rules:
  Port 80   → 0.0.0.0/0        (bütün internet)
  Port 443  → 0.0.0.0/0        (bütün internet)
  Port 22   → 10.0.0.0/8       (yalnız internal)
  Port 3306 → 10.0.1.0/24      (yalnız app subnet)

0.0.0.0/0 = hər kəs
::/0       = IPv6 hər kəs
```

### Common Mistakes

```
❌ Database portunu (3306, 5432) 0.0.0.0/0-a aç
✓  Yalnız app server subnet-indən icazə ver

❌ APP_URL-də IP istifadə et (production)
✓  Domain adı istifadə et

❌ localhost əvəzinə 127.0.0.1 hardcode et
✓  ENV var-dan al

❌ IPv6-nı tamamilə unut
✓  Log analizi zamanı ::1 (IPv6 loopback) bilmək lazımdır
```

## Nümunələr

### Nginx Virtual Host

```nginx
server {
    listen 80;              # Bütün interfeyslər, port 80
    listen [::]:80;         # IPv6 da

    server_name myapp.com;

    location / {
        fastcgi_pass 127.0.0.1:9000;  # PHP-FPM loopback
        # Və ya docker: fastcgi_pass php-fpm:9000;
    }
}
```

### Laravel .env (Production)

```
APP_URL=https://myapp.com
DB_HOST=10.0.1.50      # Private IP — public deyil
REDIS_HOST=10.0.1.51   # Private IP
```

### Log-da IP Analizi

```php
// Request-in real IP-sini almaq (load balancer arxasında)
$ip = request()->ip();  // Laravel

// Lakin load balancer varsa:
// X-Forwarded-For: 203.0.113.10, 10.0.0.5
// İlk IP real client, sonuncular proxy-lər

// Laravel TrustProxies middleware konfiqurasiyası
// App\Http\Middleware\TrustProxies::$proxies = '*';
```

## Praktik Tapşırıqlar

1. **Subnet hesabla:** `10.0.2.0/27` — neçə host ünvanı var? Range nədir?
2. **Docker yoxla:** `docker network inspect bridge` — container-ların IP-lərini gör
3. **Port yoxla:** `ss -tlnp` və ya `netstat -tlnp` — hansı portlar dinləyir?
4. **CIDR test:** AWS Security Group-da `192.168.1.100/32` — bu nəyi ifadə edir?
5. **Log analizi:** Laravel log-da `127.0.0.1`-i görürsənsə, bu nə deməkdir?

## Əlaqəli Mövzular

- [DNS - Domain Name System](07-dns.md)
- [Load Balancing](18-load-balancing.md)
- [Reverse Proxy](19-reverse-proxy.md)
- [Network Troubleshooting](30-network-troubleshooting.md)
- [Network Security](26-network-security.md)
