# DDoS Protection Strategies (Lead ⭐⭐⭐⭐)

## İcmal

DDoS (Distributed Denial of Service) hücumu — çoxlu mənbədən gələn traffic ilə sistemi əlçatmaz etmə cəhdidir. Müasir attack-lar milyonlarla kompromis edilmiş cihaz (botnet) istifadə edir. Backend Lead kimi DDoS protection strategiyalarını, defense-in-depth yanaşmasını, cloud-based mitigation-ı bilmək tələb olunur. Interview-larda bu mövzu high availability, security, infrastructure rezilience, incident response sualları ilə gəlir.

## Niyə Vacibdir

DDoS attack-ları artıq yalnız böyük şirkətlərə deyil, hər kəsə yönəlir. E-commerce, fintech, SaaS, gaming — hər məhsul bu riskə məruz qalır. Lead Developer mövcud arxitekturada DDoS riskini identify etmək, mitigation layerları dizayn etmək, incident zamanı triage edib cavab vermək bacarıqlarına sahib olmalıdır. 2023-cü ildə DDoS hücumlarının həcmi 71% artıb — AWS, Cloudflare, Akamai hesabatlarına görə.

## Əsas Anlayışlar

**DDoS attack növləri — 3 əsas kateqoriya:**

**Volumetric attacks (Layer 3/4):**
- UDP flood, ICMP flood, DNS amplification — bandwidth bitmənə məcbur etmək
- Ölçü: Gbps (gigabit per second). ISP/CDN-in ümumi bandwidth-i keçilir
- DNS amplification: 1 byte sorğu ilə 100+ byte cavab — 1:100 amplification factor. Attacker source IP-ni victim IP ilə spoof edir
- NTP amplification (monlist command): 1:500 factor. Memcached UDP: 1:50000 factor — ən güclü amplification
- Qorunma: ISP-level filtering, CDN anycast, BCP38 (source IP spoofing prevention)

**Protocol attacks (Layer 4):**
- SYN flood: Çoxlu SYN göndər, SYN-ACK aldıqdan sonra ACK göndərmə — server SYN_RECV state-dəki yarımçıq connection-larla dolur, mem/CPU tükənir
- ACK flood, RST flood — TCP state machine exhaustion
- Ölçü: Packets per second (PPS), connections per second
- Qorunma: SYN cookies, connection rate limiting, stateless packet processing

**Application attacks (Layer 7):**
- HTTP GET/POST flood — legitimate görünən sorğularla server resource-larını tükətmək. Ən çətin mübarizə olunandır
- Slowloris: Çox yavaş HTTP request göndərir (header-ları yavaş göndərir), server connection-ları uzun müddət saxlayır — connection pool tükənir
- Slow POST: Yavaş body göndərir
- RUDY (R-U-Dead-Yet): Yavaş POST — form field-ları çox yavaş göndərir
- Application-specific: Login brute force, search overload, price scraping
- Qorunma: Rate limiting, CAPTCHA, JavaScript challenge, behavioral analysis

**Defense-in-depth — çox layerlı qorunma:**

**Layer 1 — ISP / Upstream mitigation:**
- BGP blackholing: Victim IP-yə gələn traffic null route-a yönləndirilir — DDoS dəf edilir, lakin servis də əlçatmaz olur (son çarə, "take the hit")
- ISP-level scrubbing center-ləri: Legitimate traffic filter edilib servisə göndərilir
- RTBH (Remote Triggered Black Hole): Specific prefix-i yönləndir

**Layer 2 — CDN / Cloud mitigation (ən effektiv):**
- Cloudflare, AWS Shield, Akamai Prolexic, Fastly — anycast architecture ilə traffic absorbsiyası
- Anycast: Eyni IP ünvanı bütün PoP-larda (Point of Presence) var. DNS client ən yaxın PoP-a yönlənir. Attack traffic da yüzlərlə PoP-a paylaşdırılır — bir yerdə concentration yoxdur
- Cloudflare: 200+ PoP, 100+ Tbps capacity — ən böyük botnet-i belə absorb edir
- Scrubbing: Legitimate traffic-i attack traffic-dən filter etmək — DPI (Deep Packet Inspection), behavioral heuristics
- Origin IP gizlətmək: Cloudflare/CDN-in IP-si görünür, origin server-in IP-si gizlidir. DNS leak, email header, SSL cert SAN-da origin IP sıza bilər — diqqət

**Layer 3 — Perimeter (Firewall / Load Balancer):**
- Rate limiting: IP başına saniyəlik sorğu limiti
- SYN cookies: RFC 4987 — server SYN_RECV state-da connection saxlamır, cookie-dən connection rebuild edir
- Connection rate limiting: Yeni connection-ların sürəti limitlənir
- Geo-blocking: Hücum müəyyən ölkədən gəlirsə müvəqqəti blok — legitimate user-ləri də təsirləyə bilər
- IP reputation database: Bilinen botnet IP-lərini, Tor exit node-ları blok etmək

**Layer 4 — Application layer:**
- CAPTCHA / JavaScript challenge: Bot traffic-i human traffic-dən ayırmaq. Cloudflare "Under Attack Mode" — bütün visitor-lara JS challenge
- Bot management: Cloudflare Bot Management, AWS WAF bot control, PerimeterX
- Request fingerprinting: Browser fingerprint (canvas, fonts, WebGL), TLS fingerprint (JA3 hash) ilə bot detect
- Behavioral analysis: Normal istifadəçi behavior profile-ından kənar aktivlik — mouse movement, click pattern, request timing
- Honeypot endpoint-lər: `/wp-admin`, `/phpmyadmin` — bura gələn IP-ləri avtomatik blok

**Rate limiting alqoritmləri — müqayisə:**
- **Fixed window**: 1 dəqiqəlik window-da max N sorğu. Edge case: window dəyişimindən əvvəl N, dəyişimdən sonra N → 2N burst
- **Sliding window**: Hər sorğuda son N saniyədəki sayı hesabla — Redis sorted set ilə. Fixed window-un edge case-ini həll edir. Daha dəqiq
- **Token bucket**: Bucket-ə sabitdə token toplanır (refill rate), hər sorğu bir token istifadə edir. Burst-ə icazə verilir (bucket dolduqca), sustained rate limitlənir. API gateway-lər üçün ideal
- **Leaky bucket**: Sabit sürətlə queue-ya əlavə, sabit sürətlə process. Smooth output, burst absorb olunur. Network traffic shaping üçün

**Auto-scaling vs DDoS — trade-off:**
- Auto-scaling DDoS-a cavab kimi istifadə olunur, lakin expensive-dir — "bill shock"
- Economic DDoS: Attacker auto-scaling-i trigger edərək cloud xərclərini artırmağı məqsəd edə bilər
- Scaling + rate limiting + budget alert birlikdə: Əvvəlcə legitimate traffic üçün genişlən, attack traffic-i filter et, max spend limit qoy

**DDoS incident response — 6 addım:**
1. **Detect**: Traffic anomaly alert — baseline-dən 5-10× sapma, error rate artımı, CPU/memory spike
2. **Triage**: Attack növü nədir? Layer 3/4 (volumetric) vs Layer 7 (application)? Hansı endpoint-lər hədəf?
3. **Contain**: Rate limit sıxlaşdırılır, şübhəli IP range-lər blok olunur, CDN challenge aktiv
4. **Mitigate**: Origin IP gizlənir (CDN-in arxasına keçid), BGP blackhole lazım olursa aktivləşdirilir
5. **Recover**: Normal traffic-ə qayıdış, false positive-ləri düzelt, performance monitoring
6. **Post-mortem**: Root cause analiz, incident timeline, nə yaxşı işlədi, nə təkmilləşdirilməlidir, playbook update

## Praktik Baxış

**Interview-da yanaşma:**
DDoS protection-ı "bir layer-lı həll" kimi deyil, defense-in-depth çərçivəsində izah edin. Hər layer nəyi tutur, nəyi atlayır, nə qədər trafik handle edə bilər. Cost-benefit analizi əlavə edin: CDN vs on-premise scrubbing.

**Follow-up suallar (top companies-da soruşulur):**
- "Rate limiting Redis ilə necə implement edilir?" → Token bucket ya da sliding window. Redis atomic `INCR`/`EXPIRE` ya da Lua script (atomic read-modify-write). Lua script ZADD/ZREMRANGEBYSCORE/ZCARD ilə sliding window
- "Cloudflare-in origin IP-ni gizlətməyin niyə vacibdir?" → Əgər origin IP məlum olursa, attacker CDN-i bypass edib birbaşa origin-ə hücum edir. DNS leak, email header, SSL certificate-dəki SAN field-ı IP-ni ifşa edə bilər
- "Application-level DDoS-u volumetric-dən necə fərqləndirir?" → Volumetric: bandwidth/PPS yüksəlir, paket-level görünür. App-level: normal-görünən HTTP request-lər, CPU/DB yük artır, bandwidth normal. Traffic pattern: bütün sorğular eyni endpoint-ə, eyni timing
- "AWS Shield Standard vs Advanced fərqi?" → Standard: Layer 3/4 pulsuz, avtomatik. Advanced: Layer 7, 24/7 SRT (Shield Response Team) dəstəyi, DDoS cost protection (scale-up xərcləri ödənilmir), $3000/ay
- "SYN flood-a qarşı SYN cookies necə işləyir?" → Server SYN_RECV state saxlamır. Sequence number-ı `hash(src_ip, src_port, dst_ip, dst_port, secret, timestamp)` ilə hesablayır. ACK gəldikdə bu hash-i verify edir — əgər keçərlirsə connection qurulur. Heç bir state yoxdur
- "Cost-based DDoS haqqında nə bilirsiniz?" → Attacker auto-scaling-i trigger edərək victim-in cloud xərclərini artırmağı məqsəd edir. Müdafiə: max spend alert, budget cap, scaling limit, aggressive rate limiting

**Ümumi səhvlər (candidate-ların etdiyi):**
- DDoS protection-ı yalnız firewall rule kimi düşünmək — "IP blok etdik, qurtardı"
- Origin IP-nin CDN arxasında həmişə gizli olduğunu fərz etmək — SSL cert, email header, DNS history-dən sızır
- Rate limiting olmadan auto-scaling etmək — cost-based DDoS mümkün olur
- Yalnız IP-ə görə rate limit — botnet IP rotation ilə bypass olunur. User agent, fingerprint, behavioral əlamətlər kombinasiyası lazımdır
- "DDoS-u tam önləmək mümkündür" düşüncəsi — yalnız impact-i azaltmaq mümkündür

**Yaxşı cavabı əla cavabdan fərqləndirən:**
DNS amplification-ın 1:50000 amplification factor-unu izah edə bilmək, behavioral analysis ilə sophisticated bot-ların necə detect ediləcəyini, cost-based DDoS strategiyasına qarşı tədbirləri, anycast-ın niyə volumetric DDoS-u effektiv şəkildə absorb etdiyini izah edə bilmək.

## Nümunələr

### Tipik Interview Sualı

"Your e-commerce site is being hit by a DDoS attack during Black Friday. Traffic is 50x normal and growing. Walk me through your incident response and mitigation strategy."

### Güclü Cavab

Belə bir scenarioda ilk dəqiqələr kritikdir.

**Immediate (0-5 dəqiqə):**
Cloudflare dashboard-da "Under Attack Mode" aktiv et — bütün visitor-lara JavaScript challenge göndərilir. Botlar JS execute edə bilmir, filterlənir. Legitimate user-lər 5 saniyə gözləyib keçir. Bu ən sürətli ilk addımdır.

**Triage (5-15 dəqiqə):**
Traffic profile-ına bax: Layer 7-dəmi (eyni endpoint, müxtəlif IP), volumetric-dəmi (bandwidth saturation)? Hansı endpoint-lər hədəf: `/checkout`? `/search`? Origin log-larına bax: normal pattern-dənmi kənar? Bot signature varmı (eyni User-Agent, eyni request interval)?

**Contain (15-60 dəqiqə):**
Rate limiting sıxlaşdır: Black Friday üçün yüksəldilmiş limitləri normal səviyyəyə qaytar. Geo-restriction: attack spesifik country-dən gəlirsə müvəqqəti blok. Cloudflare WAF custom rule: tanınan bot signature-ları blok et. CAPTCHA `/checkout` üçün aktiv et.

**Capacity:**
Auto-scaling aktiv olsun — amma rate limiting aktiv olduqda genişlənmə yalnız legitimate traffic-ə xidmət edir. Budget alert qur: $X-dən yuxarı scale-up xərcinə alert.

**Communication:**
Status page-i yenilə: "We are experiencing higher than normal traffic." Müştərilərə şəffaf bildiriş.

**Post-attack:**
Rate limiting parametrlərini review et. Yeni bot signature-lar üçün WAF rule-ları əlavə et. Incident timeline sənədləşdir. Cloudflare Bot Management upgrade-i dəyərləndir.

### Kod Nümunəsi

```php
// Laravel — Redis sliding window rate limiter
class SlidingWindowRateLimiter
{
    public function __construct(private readonly Redis $redis) {}

    /**
     * @return bool true = allowed, false = rate limited
     */
    public function attempt(string $key, int $maxAttempts, int $windowSeconds): bool
    {
        $now         = microtime(true);
        $windowStart = $now - $windowSeconds;
        $redisKey    = "rl:{$key}";

        // Atomic Lua script — race condition yoxdur
        $result = $this->redis->eval(
            <<<'LUA'
                local key          = KEYS[1]
                local now          = tonumber(ARGV[1])
                local window_start = tonumber(ARGV[2])
                local max_attempts = tonumber(ARGV[3])
                local window_secs  = tonumber(ARGV[4])

                -- Köhnə sorğuları sil
                redis.call('ZREMRANGEBYSCORE', key, 0, window_start)

                -- Mövcud sayı
                local count = redis.call('ZCARD', key)

                if count < max_attempts then
                    -- Yeni sorğu əlavə et (score = timestamp, member = timestamp_random)
                    redis.call('ZADD', key, now, now .. math.random(1000))
                    redis.call('EXPIRE', key, window_secs)
                    return 1
                end

                return 0
            LUA,
            1,
            $redisKey,
            $now,
            $windowStart,
            $maxAttempts,
            $windowSeconds
        );

        return $result === 1;
    }

    public function remaining(string $key, int $maxAttempts, int $windowSeconds): int
    {
        $windowStart = microtime(true) - $windowSeconds;
        $redisKey    = "rl:{$key}";

        $this->redis->zRemRangeByScore($redisKey, 0, $windowStart);
        $current = (int) $this->redis->zCard($redisKey);

        return max(0, $maxAttempts - $current);
    }
}
```

```php
// DDoS-aware API Rate Limit Middleware
class DdosAwareRateLimitMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // DDoS mode aktiv olduqda daha sərt limit
        $isDdosMode  = Cache::get('ddos_mode_active', false);
        $identifier  = $this->getIdentifier($request);
        $limiter     = app(SlidingWindowRateLimiter::class);

        // Normal vs DDoS mode limits
        $limits = $isDdosMode
            ? ['requests' => 10, 'window' => 60]   // DDoS mode: 10 req/min
            : ['requests' => 100, 'window' => 60];  // Normal: 100 req/min

        if (!$limiter->attempt($identifier, $limits['requests'], $limits['window'])) {
            $remaining = $limiter->remaining($identifier, $limits['requests'], $limits['window']);

            return response()->json([
                'error'       => 'Too Many Requests',
                'retry_after' => $limits['window'],
            ], 429)->withHeaders([
                'Retry-After'            => $limits['window'],
                'X-RateLimit-Limit'      => $limits['requests'],
                'X-RateLimit-Remaining'  => $remaining,
                'X-RateLimit-Reset'      => now()->addSeconds($limits['window'])->timestamp,
            ]);
        }

        return $next($request);
    }

    private function getIdentifier(Request $request): string
    {
        // Authenticated user-lər üçün user ID — IP rotation bypass edilmir
        if ($user = $request->user()) {
            return "user:{$user->id}";
        }

        // Unauthenticated: IP + User-Agent kombinasiyası
        // Yalnız IP = botnet IP rotation ilə bypass olunur
        $ip        = $request->ip();
        $userAgent = substr($request->userAgent() ?? '', 0, 50);
        return "anon:" . md5("{$ip}:{$userAgent}");
    }
}
```

```php
// DDoS mode toggle — anomaly detection ilə avtomatik
class DdosDetectionService
{
    private const BASELINE_WINDOW = 3600;  // 1 saat baseline
    private const SPIKE_MULTIPLIER = 5;    // 5x spike → DDoS mode

    public function checkAndActivate(): void
    {
        $currentRpm  = $this->getCurrentRpm();
        $baselineRpm = $this->getBaselineRpm();

        if ($baselineRpm > 0 && $currentRpm > $baselineRpm * self::SPIKE_MULTIPLIER) {
            // Traffic spike detected — DDoS mode aktiv
            Cache::put('ddos_mode_active', true, now()->addMinutes(30));

            Log::critical('DDoS mode activated', [
                'current_rpm'  => $currentRpm,
                'baseline_rpm' => $baselineRpm,
                'multiplier'   => round($currentRpm / $baselineRpm, 1),
            ]);

            // Ops team-ə alert (PagerDuty, Slack, email)
            AlertService::critical('DDoS Protection Mode Activated', [
                'traffic_spike' => "{$currentRpm} rpm vs {$baselineRpm} rpm baseline",
            ]);
        } elseif (Cache::get('ddos_mode_active') && $currentRpm < $baselineRpm * 2) {
            // Traffic normala qayıtdı
            Cache::forget('ddos_mode_active');
            Log::info('DDoS mode deactivated — traffic normalized');
        }
    }

    private function getCurrentRpm(): int
    {
        return (int) Cache::get('current_rpm', 0);
    }

    private function getBaselineRpm(): int
    {
        // Son 1 saatın orta dəyəri
        return (int) Cache::get('baseline_rpm', 100);
    }
}
```

```nginx
# Nginx — Layer 7 DDoS mitigation konfiqurasiyası
http {
    # Rate limiting zones
    limit_req_zone  $binary_remote_addr zone=general:20m rate=60r/m;
    limit_req_zone  $binary_remote_addr zone=api:20m     rate=100r/m;
    limit_req_zone  $binary_remote_addr zone=login:10m   rate=5r/m;
    limit_req_zone  $binary_remote_addr zone=checkout:10m rate=10r/m;
    limit_conn_zone $binary_remote_addr zone=connections:10m;

    # Geoip blocking (opsiyonal — nginx-module-geoip2 lazımdır)
    # geoip2 /etc/nginx/GeoLite2-Country.mmdb {
    #     $geoip2_data_country_code country iso_code;
    # }

    server {
        # Connection limiting: IP başına max 100 concurrent
        limit_conn connections 100;

        # Default rate limit
        limit_req zone=general burst=20 nodelay;
        limit_req_status 429;

        location /api/ {
            limit_req zone=api burst=30 nodelay;

            # Geo-blocking opsiyonal
            # if ($geoip2_data_country_code ~* "^(RU|CN|KP)$") {
            #     return 444;
            # }

            try_files $uri /index.php?$query_string;
        }

        location /auth/login {
            limit_req zone=login burst=3;
            limit_req_status 429;
            try_files $uri /index.php?$query_string;
        }

        location /checkout {
            limit_req zone=checkout burst=5 nodelay;
            try_files $uri /index.php?$query_string;
        }

        # Honeypot — botların yoxladığı "low-hanging fruit" paths
        location ~ ^/(wp-admin|phpmyadmin|\.env|\.git|admin\.php|shell\.php) {
            # 444 = connection drop, heç bir cavab yoxdur
            return 444;
            # Bu IP-ləri fail2ban ilə avtomatik blok etmək:
            # fail2ban-regex /var/log/nginx/access.log 'GET /(wp-admin|phpmyadmin)'
        }

        # Slow loris qorunması
        client_header_timeout  5s;
        client_body_timeout    10s;
        keepalive_timeout      65s;
        send_timeout           10s;

        # Large request limiting
        client_max_body_size   10m;
    }
}
```

```
Defense-in-Depth Architecture:

Internet (Attacker + Legitimate Users)
           │
           ▼
┌──────────────────────────────┐
│  CDN / Anycast Layer         │ ← Volumetric DDoS absorption
│  (Cloudflare / AWS Shield)   │ ← JavaScript Challenge (Layer 7)
│  100+ Tbps capacity          │ ← Bot fingerprinting
└─────────────┬────────────────┘
              │ Scrubbed traffic
              ▼
┌──────────────────────────────┐
│  Load Balancer (HAProxy)     │ ← SYN cookies, connection limiting
│                              │ ← Layer 4 rate limiting
└─────────────┬────────────────┘
              │
              ▼
┌──────────────────────────────┐
│  Nginx (Reverse Proxy)       │ ← HTTP rate limiting per IP/endpoint
│                              │ ← Honeypot detection
│                              │ ← Geo-blocking (opsiyonal)
└─────────────┬────────────────┘
              │
              ▼
┌──────────────────────────────┐
│  Application (Laravel)       │ ← Redis sliding window rate limit
│                              │ ← DDoS mode auto-activation
│                              │ ← Behavioral analysis
└─────────────┬────────────────┘
              │
              ▼
┌──────────────────────────────┐
│  Database / Cache            │ ← Connection pool limits
│  (MySQL + Redis)             │ ← Query timeout limits
└──────────────────────────────┘
```

## Praktik Tapşırıqlar

1. Cloudflare free tier ilə test domain qurun: "Under Attack Mode"-u aktiv edib browser-dən girişi test edin
2. Redis-də sliding window rate limiter implement edin, Apache Benchmark (ab) ilə test edin: `ab -n 200 -c 10 http://localhost/api/test`
3. Nginx-in `limit_req` + `limit_conn` konfiqurasiyasını qurun, limit aşıldıqda `429` response-u confirm edin
4. Honeypot endpoint qurun: `/wp-admin`, `/phpmyadmin` — bura gələn IP-ləri log edin, fail2ban ilə avtomatik blok edin
5. DDoS mode toggle implement edin: traffic anomaly detect edildikdə rate limit-i avtomatik sıxlaşdır
6. DNS amplification attack-ı araşdırın: `dig +short test.openresolver.com TXT @8.8.8.8` — amplification factor-u hesablayın
7. DDoS incident response playbook yazın: detect → triage → contain → mitigate → recover — hər addım üçün konkret command-lar

## Əlaqəli Mövzular

- [Proxy vs Reverse Proxy](13-proxy-reverse-proxy.md) — Rate limiting at reverse proxy, connection limiting
- [DNS Resolution](04-dns-resolution.md) — DNS amplification attack, authoritative DNS protection
- [HTTP Caching](09-http-caching.md) — CDN cache DDoS yükünü azaldır — origin-ə sorğu getmir
- [TLS/SSL Handshake](03-tls-ssl-handshake.md) — TLS handshake resource consumption — flood attack surface
- [Network Latency](14-network-latency.md) — DDoS-un latency üzərindəki təsiri, Little's Law feedback loop
