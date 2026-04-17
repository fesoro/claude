# VPN & Tunneling

## Nədir? (What is it?)

VPN (Virtual Private Network) public network (internet) üstündə encrypted, authenticated tunnel yaradıb private network komunikasiyası təmin edir. Tunneling daha geniş konseptdir - bir protocol-u başqa bir protocol içində encapsulate etmək.

İstifadə halları:
- **Remote access**: İşdən evdən internal network-ə çıxış
- **Site-to-site**: İki ofis arasında secure link
- **Privacy**: ISP tracking-dən, public WiFi-dan müdafiə
- **Geo-unblocking**: Content region restrictions
- **Security**: Sensitive data encryption in transit

```
Without VPN:
  Laptop --> Public WiFi --> Internet --> Server
             (can sniff)

With VPN:
  Laptop --> VPN tunnel (encrypted) --> VPN Server --> Internet --> Destination
             (ISP can't see content, sees only encrypted tunnel)
```

## Necə İşləyir? (How does it work?)

### 1. VPN Types

```
1. Remote Access VPN
   Fərdi user home/cafe-den company network-e connect:
     [User] --- VPN tunnel ---> [VPN Gateway] --> [Internal network]
   Protocols: OpenVPN, WireGuard, IKEv2/IPSec

2. Site-to-Site VPN
   Iki network tam baglanir, user-lere sheffaf:
     [Office A Network] <--- VPN tunnel ---> [Office B Network]
   Protocols: IPSec, GRE+IPSec

3. Client-to-Site (same as remote access)

4. SSL/TLS VPN (clientless)
   Browser-based, VPN client lazim deyil:
     [Browser HTTPS] --> [SSL VPN Gateway] --> [Internal apps]
   Products: OpenVPN Access Server, Pulse Secure

5. P2P VPN (mesh)
   Hər node hər node ilə direct encrypted:
     WireGuard mesh, Tailscale
```

### 2. IPSec (Internet Protocol Security)

```
IPSec: Layer 3-də IP packet encryption/authentication.

İki mode:

Transport Mode (host-to-host):
  [Original IP Header][ESP Header][Encrypted Payload][ESP Trailer][ESP Auth]
                                   <- original TCP/UDP + data ->

Tunnel Mode (VPN, network-to-network):
  [New IP Header][ESP Header][Encrypted (Original IP + Payload)][ESP Trailer]
                              <- original IP packet fully encrypted ->

Protocols:
  - AH (Authentication Header): Integrity + auth (no encryption)
  - ESP (Encapsulating Security Payload): Encryption + integrity + auth
  - IKE (Internet Key Exchange): SA negotiation, key management

IKE Phases:

Phase 1 (IKE SA setup):
  - Main Mode or Aggressive Mode
  - Mutual authentication (PSK, cert, EAP)
  - DH key exchange (shared secret)
  - Establishes IKE SA

Phase 2 (IPSec SA):
  - Negotiate encryption params (AES-256-GCM, SHA-2)
  - Create actual tunnel SAs
  - Fast mode

Example config (strongSwan):
  conn site-to-site
    left=1.2.3.4
    leftsubnet=10.0.1.0/24
    right=5.6.7.8
    rightsubnet=10.0.2.0/24
    ike=aes256-sha256-modp2048
    esp=aes256-sha256
    auto=start
```

### 3. WireGuard

```
WireGuard: Modern, simple, fast VPN.
Linux kernel-də native (5.6+).

Concepts:
  - Peer-to-peer (mesh-capable)
  - Public/private key authentication (like SSH)
  - Cryptographically opinionated (ChaCha20, Curve25519, Poly1305)
  - Minimal code (~4000 lines vs IPSec 400K+)

Config (client):
  [Interface]
  PrivateKey = ABC...
  Address = 10.0.0.2/32
  DNS = 1.1.1.1

  [Peer]
  PublicKey = XYZ...
  Endpoint = vpn.example.com:51820
  AllowedIPs = 0.0.0.0/0
  PersistentKeepalive = 25

Config (server):
  [Interface]
  PrivateKey = DEF...
  Address = 10.0.0.1/24
  ListenPort = 51820

  [Peer]
  PublicKey = ABC... (client's public key)
  AllowedIPs = 10.0.0.2/32

Commands:
  wg genkey | tee private.key | wg pubkey > public.key
  wg-quick up wg0
  wg-quick down wg0
  wg show

Advantages:
  - Very fast (near line speed)
  - Simple config (~10 lines)
  - Modern crypto
  - Mobile-friendly (roaming, sleep)
```

### 4. OpenVPN

```
OpenVPN: Mature, feature-rich VPN.

Uses TLS/SSL for key exchange:
  Port: 1194 (UDP or TCP)
  TCP more reliable but slower (TCP-over-TCP issues)
  UDP recommended

Authentication:
  - Certificates (X.509 PKI)
  - Username/password (PAM, LDAP)
  - Pre-shared key (point-to-point)
  - 2FA support

Config (client):
  client
  dev tun
  proto udp
  remote vpn.example.com 1194
  resolv-retry infinite
  nobind
  persist-key
  persist-tun
  ca ca.crt
  cert client.crt
  key client.key
  cipher AES-256-GCM
  auth SHA256
  remote-cert-tls server
  tls-version-min 1.2

Config (server):
  port 1194
  proto udp
  dev tun
  ca ca.crt
  cert server.crt
  key server.key
  dh dh.pem
  server 10.8.0.0 255.255.255.0
  ifconfig-pool-persist ipp.txt
  push "redirect-gateway def1 bypass-dhcp"
  push "dhcp-option DNS 1.1.1.1"
  keepalive 10 120
  cipher AES-256-GCM
  user nobody
  group nogroup
  persist-key
  persist-tun
  status openvpn-status.log
  verb 3
```

### 5. SSH Tunneling

```
SSH tunnel: SSH connection üstündə başqa traffic forward etmək.

Three types:

1. Local Port Forwarding (-L)
   Local machine port -> Remote service

   ssh -L 8080:internal-server:80 user@jump-server

   Effect:
     localhost:8080 traffic -> (via SSH) -> jump-server -> internal-server:80

   Use case: Accessing internal DB through bastion

2. Remote Port Forwarding (-R)
   Remote port -> Local service

   ssh -R 9000:localhost:3000 user@remote-server

   Effect:
     remote-server:9000 traffic -> (via SSH) -> your-laptop:3000

   Use case: Expose local dev server to public

3. Dynamic Port Forwarding (-D) - SOCKS Proxy
   ssh -D 1080 user@server

   Configure browser SOCKS proxy: localhost:1080
   All browser traffic goes through SSH server

   Use case: Secure browsing from public WiFi

Advanced:
   ssh -L 5432:db.internal:5432 \
       -L 6379:redis.internal:6379 \
       -N -f user@jump.example.com
   (-N = no remote command, -f = background)
```

### 6. Port Forwarding

```
NAT-də router-lər internal service-i internet-ə expose etmək üçün:

Router:
  Public IP: 1.2.3.4
  Internal network: 192.168.1.0/24

Port forwarding rule:
  External 1.2.3.4:80 -> Internal 192.168.1.100:80

Types:
  Static (DNAT): Permanent mapping
  UPnP: Automatic (security risk)
  PAT (Port Address Translation): Many-to-one
```

### 7. Bastion Host (Jump Server)

```
Problem: Internal server-lər public internet-ə açıq olmasın.
Həll: Bir "jump" server-dən keç.

Architecture:

Internet
   |
[Bastion Host]  <- only this has public IP
   |
[Private Network]
   |
   +-- [DB Server]
   +-- [App Server]
   +-- [Cache Server]

Security:
  - SSH key only (no password)
  - 2FA
  - Audit logging
  - IP whitelist
  - Session recording

Access flow:
  1. User SSH to bastion (with their key)
  2. From bastion, SSH to internal server
  3. All traffic audited

SSH ProxyJump (simpler):
  ssh -J user@bastion user@internal-server
  # or in ~/.ssh/config:
  Host internal
    HostName internal.example.com
    ProxyJump bastion.example.com
```

### 8. Split Tunneling

```
Full tunnel: All traffic through VPN
  Laptop --> VPN --> All internet

Split tunnel: Selected traffic through VPN
  Laptop --> VPN (for 10.0.0.0/8)
         --> Regular internet (for other)

Benefits:
  - Less bandwidth usage
  - Better local network performance
  - Work + personal traffic separated

Risks:
  - Work device on untrusted network
  - Data leakage
  - Compliance violations
```

## Əsas Konseptlər (Key Concepts)

### Encryption Algorithms

```
Symmetric (bulk data):
  AES-256-GCM   - Most common
  ChaCha20-Poly1305 - Mobile (no AES hardware)

Asymmetric (key exchange):
  RSA-2048/4096
  ECDSA P-256/P-384
  X25519 (Curve25519) - modern

Hashing (integrity):
  SHA-256, SHA-384
  BLAKE2 (WireGuard)

Key exchange:
  Diffie-Hellman (DH)
  Elliptic Curve DH (ECDH)
```

### Perfect Forward Secrecy (PFS)

```
PFS: Session key compromise keçmiş trafic-i açmır.

Without PFS:
  Server long-term key compromise -> all historical traffic decrypted

With PFS:
  Each session has ephemeral key (DH/ECDH)
  Long-term key compromise -> only future sessions at risk
  Old sessions remain secure

Modern VPN-lər (WireGuard, IKEv2) PFS istifade edir.
```

### VPN Killswitch

```
Problem: VPN disconnect olanda trafik clear-text internet-ə gedir.

Killswitch: VPN down olanda butun network disabled.

Implementation:
  - Firewall rule: DROP all, allow only via tun0
  - iptables:
      iptables -A OUTPUT ! -o tun0 -j DROP
      iptables -A OUTPUT -d 1.2.3.4 -j ACCEPT  # allow VPN server
```

### MTU and Fragmentation

```
MTU (Maximum Transmission Unit): Max packet size per hop.
  Ethernet default: 1500 bytes

VPN encapsulation adds overhead:
  IPSec: ~50-60 bytes
  OpenVPN: ~40-60 bytes
  WireGuard: ~60 bytes

If total > MTU: Fragmentation or drop.

Solution:
  Set lower MTU on VPN interface: 1420, 1380
  Or: MSS clamping (TCP only)
      iptables -t mangle -A FORWARD -p tcp --tcp-flags SYN,RST SYN \
               -j TCPMSS --clamp-mss-to-pmtu
```

### DNS Leak

```
Problem: VPN active, amma DNS request ISP DNS-a gedir (untunneled).
  Result: ISP user-in hansi sayt-a getdiyini bilir.

Solution:
  - VPN-də DNS push et (OpenVPN: push "dhcp-option DNS X")
  - System DNS-i VPN DNS-a yönəlt
  - DoH/DoT istifadə et (DNS over HTTPS/TLS)
  - Test: dnsleaktest.com
```

### IPv4 vs IPv6 Leak

```
VPN IPv4 only qura bilər, IPv6 bypass edib ISP-dan keçə bilər.

Prevention:
  - IPv6 disable (radical)
  - IPv6 dəstəkləyən VPN protocol
  - Firewall DROP IPv6 outbound
```

## PHP/Laravel ilə İstifadə

VPN əsasən infrastructure-level olur, app-level PHP kodu VPN idarə etmir. Amma VPN/tunnel context-də app-də nələr edilir:

### Detecting VPN Users

```php
// IP reputation check (commercial: ipqualityscore, ipinfo)
class VpnDetector
{
    public function isVpn(string $ip): bool
    {
        $apiKey = config('services.ipqualityscore.key');
        $response = Http::get("https://ipqualityscore.com/api/json/ip/{$apiKey}/{$ip}");

        $data = $response->json();
        return $data['vpn'] || $data['proxy'] || $data['tor'];
    }
}

// Middleware
class BlockVpn
{
    public function handle($request, Closure $next)
    {
        if (app(VpnDetector::class)->isVpn($request->ip())) {
            abort(403, 'VPN access not allowed');
        }
        return $next($request);
    }
}
```

### DB Connection via SSH Tunnel (Development)

```php
// Development only - SSH tunnel-dan keçib production DB-yə
// Terminal:
// ssh -L 5433:production-db:5432 bastion.example.com -N -f

// .env
DB_HOST=127.0.0.1
DB_PORT=5433
DB_DATABASE=app
DB_USERNAME=developer
DB_PASSWORD=xxx

// Laravel connects to local 5433, which tunnels to prod DB
```

### Laravel SSH Task Runner (over bastion)

```php
use Illuminate\Support\Facades\Process;

// With ProxyJump
$result = Process::run([
    'ssh',
    '-J', 'bastion@bastion.example.com',
    'deploy@internal-server.example.com',
    'php /var/www/app/artisan migrate --force'
]);

if (!$result->successful()) {
    throw new Exception($result->errorOutput());
}
```

### Exposing Local Dev Server (Ngrok alternative)

```php
// cloudflared tunnel
// $ cloudflared tunnel create my-app
// $ cloudflared tunnel --url http://localhost:8000

// Or with SSH reverse tunnel:
// $ ssh -R 80:localhost:8000 serveo.net
// Public URL: https://your-app.serveo.net

// Laravel config for correct URL generation:
// .env
APP_URL=https://your-app.serveo.net

// TrustProxies middleware
protected $proxies = '*'; // trust all (dev only!)
```

### Geo-blocking with Country Detection

```php
// MaxMind GeoIP2
// composer require geoip2/geoip2

use GeoIp2\Database\Reader;

class GeoBlock
{
    public function handle($request, Closure $next)
    {
        $reader = new Reader(storage_path('GeoLite2-Country.mmdb'));

        try {
            $record = $reader->country($request->ip());
            $country = $record->country->isoCode;

            $blockedCountries = config('app.blocked_countries', []);
            if (in_array($country, $blockedCountries)) {
                abort(403, 'Not available in your region');
            }
        } catch (\Exception $e) {
            // IP not in DB
        }

        return $next($request);
    }
}
```

### API Access Only from VPN/Internal Network

```php
// app/Http/Middleware/InternalOnly.php
class InternalOnly
{
    protected array $allowedNetworks = [
        '10.0.0.0/8',      // Private
        '172.16.0.0/12',   // Private
        '192.168.0.0/16',  // Private
        '100.64.0.0/10',   // VPN CGNAT
    ];

    public function handle($request, Closure $next)
    {
        $ip = $request->ip();

        foreach ($this->allowedNetworks as $network) {
            if ($this->ipInRange($ip, $network)) {
                return $next($request);
            }
        }

        abort(403, 'Internal access only');
    }

    private function ipInRange(string $ip, string $range): bool
    {
        [$subnet, $bits] = explode('/', $range);
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        $mask = -1 << (32 - $bits);
        return ($ipLong & $mask) === ($subnetLong & $mask);
    }
}

// Usage
Route::middleware(InternalOnly::class)->group(function () {
    Route::get('/admin/metrics', [AdminController::class, 'metrics']);
});
```

## Interview Sualları

**Q1: VPN-in əsas məqsədi nədir?**

**Məqsədlər**:
1. **Confidentiality**: Encrypted tunnel - man-in-middle attack-dan müdafiə
2. **Authentication**: Tunnel-a giriş üçün credentials/cert
3. **Remote access**: Internal resources-a uzaqdan çatan
4. **IP masking**: Public IP-n VPN server-in IP-sinə dəyişir
5. **Geo-unblocking**: Region-based restrictions-ı keç

VPN privacy tam təmin etmir (VPN provider data görə bilər) - bu bir trust transfer-dir.

**Q2: IPSec Transport vs Tunnel mode?**

**Transport mode**: Yalnız payload (TCP/UDP + data) encrypt olunur. Original IP header görünür. Host-to-host komunikasiyası üçün.

**Tunnel mode**: Butun original IP packet encrypt olunur, yeni outer IP header əlavə olunur. Site-to-site VPN üçün ideal (internal IP-lər gizli qalır).

```
Transport: [IP][ESP][encrypted TCP+Data][ESP trailer]
Tunnel:    [New IP][ESP][encrypted [IP][TCP][Data]][ESP trailer]
```

**Q3: OpenVPN vs WireGuard fərqi?**

**OpenVPN**:
- Mature (2001), mükəmməl enterprise features
- TLS-based, cert authentication
- TCP/UDP
- Config mürəkkəb, 100+ options
- Daha yavaş (user-space)
- 70K+ lines of code

**WireGuard**:
- Modern (2016), simple
- Public key authentication (SSH kimi)
- UDP only
- Config ~10 sətir
- Çox sürətli (kernel-space)
- 4K lines of code
- Mobile-friendly (roaming)

WireGuard gələcək, amma OpenVPN bəzi feature-ə görə (multi-factor, LDAP, dynamic routing) enterprise-da hələ də populyar.

**Q4: SSH tunneling necə işləyir?**

SSH connection üstündə başqa network trafic-ini forward edir.

**Local forward** (`-L`): Local port-a gələn traffic SSH-la remote-a ötürülür.
```
ssh -L 8080:db.internal:5432 bastion.example.com
# localhost:8080 -> SSH tunnel -> db.internal:5432
```

**Remote forward** (`-R`): Remote port-a gələn traffic local-a ötürülür.
```
ssh -R 9000:localhost:3000 remote.example.com
# remote:9000 -> SSH tunnel -> local:3000
```

**Dynamic** (`-D`): SOCKS proxy.
```
ssh -D 1080 server
# Browser SOCKS proxy -> tunnel -> server -> internet
```

**Q5: Bastion host nə üçündür?**

Bastion (jump server): Internal network-ə tek giriş point. Internal server-lər public internet-ə açıq deyil.

**Architecture**:
```
Internet --> [Bastion (public IP)] --> [Internal servers (private)]
```

**Faydalar**:
- Attack surface azaldılır (1 public server vs N)
- Centralized logging/audit
- MFA enforcement
- Easy to harden (minimal services)

**SSH ProxyJump**:
```
ssh -J bastion@bastion.com user@internal.com
```

**Q6: Split tunnel vs full tunnel?**

**Full tunnel**: Bütün trafik VPN-dən keçir. Daha təhlükəsiz (privacy), amma slow (bandwidth), local network resources əlçatmaz.

**Split tunnel**: Yalnız seçilmiş trafik (10.0.0.0/8 kimi) VPN-dən keçir. Qalanı birbaşa internet-ə. Daha sürətli, lakin security risk (work device untrusted traffic-də).

Enterprise: full tunnel tövsiyə (compliance). Home: split (performance).

**Q7: PFS (Perfect Forward Secrecy) niyə vacibdir?**

PFS ilə hər session unique ephemeral key istifadə edir (DH/ECDH). Server-in long-term key-i compromise olsa:
- **Without PFS**: Keçmiş trafic decrypt oluna bilər (saved encrypted traffic)
- **With PFS**: Yalnız gələcək session-lar risk altında, keçmiş trafik təhlükəsiz

Modern protokollar (TLS 1.3, WireGuard, IKEv2) PFS tələb edir.

**Q8: DNS leak nədir və necə qarşısını almaq olar?**

**Problem**: VPN aktiv olsa da, DNS query-lər ISP DNS server-inə gedir. ISP user-in hansi sayt-a baxdığını görür.

**Prevention**:
1. VPN server öz DNS-ini push etsin (OpenVPN: `push "dhcp-option DNS 10.8.0.1"`)
2. System DNS-i VPN DNS-a force et
3. DoH/DoT istifade et (Cloudflare 1.1.1.1)
4. DNS test: dnsleaktest.com

**Q9: VPN killswitch necə işləyir?**

VPN bağlantı itilərsə, trafic regular internet-ə açılmasın.

**Implementation** (iptables):
```bash
iptables -A OUTPUT ! -o tun0 -j DROP
iptables -A OUTPUT -d VPN_SERVER_IP -j ACCEPT
```

Yalniz tun0 (VPN interface) üzərindən trafic allowed, qalan hamısı DROP. VPN server-ə connect etmək üçün istisna.

VPN client-lər (NordVPN, ExpressVPN) bunu UI-də toggle edir.

**Q10: MTU problemi VPN-də necə həll olunur?**

VPN encapsulation overhead əlavə edir (40-60 byte). Original MTU 1500-dirsə, payload 1440-1460-a düşür.

**Problem**: Path MTU discovery failed case-lərdə black hole - packets drop olunur.

**Solutions**:
1. **VPN interface MTU-nu aşağı qoy**: 1420 (WireGuard default)
2. **MSS clamping** (TCP): SYN-də MSS azalt
   ```
   iptables -t mangle -A FORWARD -p tcp --tcp-flags SYN,RST SYN \
            -j TCPMSS --clamp-mss-to-pmtu
   ```
3. **ICMP icazəli olsun**: Path MTU discovery üçün
4. **Jumbo frames** (LAN only): MTU 9000

## Best Practices

1. **Modern protokol seç**: WireGuard > IKEv2/IPSec > OpenVPN > L2TP/PPTP.

2. **PFS tələb et**: Long-term key compromise-i future-only risk et.

3. **Strong crypto**: AES-256-GCM, ChaCha20-Poly1305, SHA-384+.

4. **Certificate authentication** preferred over PSK.

5. **MFA əlavə et**: Certificate + password/TOTP kombinasiyası.

6. **Regular key rotation**: Long-term key-ləri yenilə.

7. **Split vs full tunnel** decision: Security (full) vs usability (split).

8. **DNS leak prevention**: VPN DNS push, DoH backup.

9. **Killswitch enabled**: VPN drop olanda trafic sızmasın.

10. **Audit logging**: Kim, nə vaxt, nə qədər bağlandı.

11. **Bastion hosts for SSH**: Internal network-ə direct access olmasın.

12. **SSH key only**: Password authentication disable.

13. **Session timeout**: Inactive connection-lar drop.

14. **Network segmentation**: VPN-dən girən yalniz lazım resources-a çıxsın.

15. **VPN client update policy**: Outdated client-ləri bloklamaq.

16. **Monitoring**: Unusual traffic, geographic anomalies.

17. **Backup VPN endpoint**: Primary down olsa fallback.

18. **Split horizon DNS**: Internal domains VPN-dən resolvable.

19. **Tunnel health check**: Auto-reconnect, dead peer detection (DPD).

20. **Documentation**: VPN architecture, credentials rotation, emergency procedures.
