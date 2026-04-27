# TLS/SSL Handshake (Senior ⭐⭐⭐)

## İcmal
TLS (Transport Layer Security) — internet üzərindəki kommunikasiyaların şifrələnməsini və autentifikasiyasını təmin edən protokoldur. SSL artıq deprecated-dir, lakin "SSL certificate" ifadəsi gündəlik həyatda hələ işlədilir. Backend developer kimi TLS handshake-in necə işlədiyini bilmək HTTPS debug etmək, certificate idarəetməsi, mTLS, performance optimizasiya üçün vacibdir.

## Niyə Vacibdir
Senior developer kimi TLS-i "şifrələmə var" səviyyəsindən artıq bilmək lazımdır. Production-da TLS certificate expire olması, handshake failure, self-signed certificate problemi, mTLS konfiqurasiyası kimi real problem-lər yaşanır. Bunları debug etmək üçün handshake prosesini anlamaq şərtdir.

## Əsas Anlayışlar

- **SSL vs TLS fərqi:** SSL 3.0 1996-da deprecated edildi. TLS 1.0 (2018), TLS 1.1 (2020) da deprecated. Cari standart: TLS 1.2 (geniş dəstək) və TLS 1.3 (müasir, sürətli, daha güvənli)
- **TLS-in 2 məqsədi:** 1) Şifrələmə (confidentiality) — üçüncü tərəf oxuya bilməsin; 2) Autentifikasiya (authentication) — server-in kimliyi doğrulanır. Bu fərqi bilmək vacibdir
- **Asymmetric Encryption (handshake üçün):** RSA ya da ECDH public/private key cütü. Key exchange üçün istifadə olunur. Yavaşdır (böyük hesablamalar)
- **Symmetric Encryption (data üçün):** AES-256-GCM kimi — eyni key hər iki tərəfdə. Çox sürətlidir. Handshake-dən sonra data bu key ilə şifrələnir
- **TLS 1.2 Handshake (2-RTT):** ClientHello → ServerHello + Certificate + ServerHelloDone → ClientKeyExchange + ChangeCipherSpec + Finished → ChangeCipherSpec + Finished. 2 round-trip gecikmə
- **TLS 1.3 Handshake (1-RTT):** ClientHello (key_share daxil) → ServerHello (key_share) + Certificate + CertificateVerify + Finished → Finished. Yalnız 1 round-trip. Zəif cipher suite-lər silindi
- **0-RTT Session Resumption:** TLS 1.3-də əvvəlki session-dan PSK (Pre-Shared Key) var isə client data-nı handshake olmadan göndərə bilər. Risk: replay attack — `0-RTT` data idempotent olmalıdır
- **Forward Secrecy (PFS):** ECDHE (Ephemeral Diffie-Hellman) ilə hər session üçün ayrı key. Uzun müddəti saxlanan private key gələcəkdə ifşa olsa belə köhnə session-lar decipher edilə bilmir
- **Certificate Chain:** Server certificate → Intermediate CA → Root CA. Browser trust store-unda yalnız Root CA-lar var. Server Intermediate CA-nı da göndərməlidir
- **Certificate Validation:** 1) Domain match (CN ya da SAN); 2) Expiry date check; 3) Revocation check (CRL ya da OCSP); 4) CA chain trust
- **SNI (Server Name Indication):** TLS ClientHello-da client hansı domain-ə qoşulduğunu bildirir. Bu olmadan bir IP-dən çox TLS domain host etmək mümkün deyil
- **mTLS (Mutual TLS):** Client-in də certificate göndərməsi. Serverdən clienta autentifikasiya (normal TLS). Client-dən serverə də autentifikasiya (mTLS). Microservices arası güvənli kommunikasiya üçün
- **OCSP Stapling:** Server revocation status-u CA-dan əvvəlcədən alıb handshake-ə əlavə edir. Client ayrıca CA-ya sorğu göndərmir — daha sürətli, privacy-friendly
- **HSTS (HTTP Strict Transport Security):** `Strict-Transport-Security: max-age=31536000` — browser bu domain-ə daima HTTPS ilə qoşulur. HSTS Preload siyahısına daxil olmaq daha güvənlidir
- **Certificate Pinning:** Spesifik certificate ya da public key-ə trust etmək. Mobile app-larda MITM attack-a qarşı. Risk: certificate rotasyonu zamanı app update lazım olur, yanlış konfiqurasiya = app işləmir
- **TLS 1.3 Improvements:** Daha az cipher suite (yalnız AEAD), RSA key exchange silindi (PFS məcburi), handshake 1-RTT, 0-RTT support, daha güvənli HKDF key derivation
- **Cipher Suite:** Key exchange + authentication + encryption + MAC kombinasiyası. Nümunə: `TLS_ECDHE_RSA_WITH_AES_256_GCM_SHA384` — ECDHE key exchange, RSA auth, AES-256-GCM şifrə, SHA-384 hash

## Praktik Baxış

**Interview-da yanaşma:**
TLS handshake-i addım-addım izah edərkən diagram çəkmək çox effektivdir. TLS 1.2 vs 1.3 fərqini RTT sayı üzərindən izah etmək (2-RTT → 1-RTT) konkret müqayisədir. "Şifrələmə + autentifikasiya — ikisi birlikdə" vurğulamaq.

**Follow-up suallar:**
- "mTLS nədir, nə zaman istifadə olunur?" — Service mesh (Istio), microservices arası autentifikasiya, zero-trust network
- "Certificate expire olduqda nə baş verir?" — Browser: warning/block; API client-lər: SSL error → connection refused
- "Forward secrecy niyə vacibdir?" — Gələcəkdə private key ifşa olsa keçmiş session-lar decrypt edilə bilmir
- "TLS 1.3-ün 0-RTT replay attack riski nədir?" — Non-idempotent əməliyyat (POST payment) 0-RTT ilə göndərilsə iki dəfə icra edilə bilər
- "HSTS nədir? preload nə verir?" — Browser-i daima HTTPS-ə məcbur edir; preload: browser-in öncədən bildiyi domain siyahısı

**Ümumi səhvlər:**
- SSL ilə TLS-i eyni hesab etmək — SSL deprecated-dir, texniki TLS istifadə olunur
- Handshake-in yalnız şifrələmə olduğunu düşünmək — autentifikasiya da var
- TLS 1.3-ün 0-RTT-nin replay attack riskini bilməmək
- Certificate pinning-in üstünlüklərini söyləmək, riskini (rotasiya çətinliyi) qeyd etməmək
- "Self-signed certificate production-da istifadə etmək olar" demək

**Yaxşı cavabı əla cavabdan fərqləndirən:**
- Forward secrecy-nin kritik olduğunu, ECDHE-nin RSA key exchange-dən fərqini izah etmək
- TLS 1.3-ün 0-RTT replay attack riskini bilmək
- mTLS-i microservices service mesh context-ində izah etmək
- Certificate chain-in tam olması tələbini bilmək (intermediate CA)

## Nümunələr

### Tipik Interview Sualı
"Explain what happens when a browser connects to https://example.com for the first time. Walk me through each step from DNS lookup to encrypted data."

### Güclü Cavab
Addım-addım:

1. **DNS resolution:** `example.com` → IP ünvanı (93.184.216.34). Browser cache, OS cache, recursive resolver.

2. **TCP 3-way handshake:** SYN → SYN-ACK → ACK. TCP connection qurulur.

3. **TLS 1.3 Handshake (1-RTT):**
   - **ClientHello:** Browser dəstəklədiyini TLS version-ları, cipher suite-ləri, key_share (ECDH public key), SNI (`example.com`) göndərir
   - **ServerHello:** Server cipher suite seçir, öz ECDH key_share-ini göndərir. Bu anda hər iki tərəf Diffie-Hellman ilə eyni shared secret-i hesablaya bilir → session key-lər derive edilir
   - **Server → Certificate:** Server-in sertifikatı (Intermediate CA + leaf certificate)
   - **Server → CertificateVerify:** Server private key-in olduğunu sübut edir (signature)
   - **Server → Finished:** Encrypted — handshake tamamlandı
   - **Client → Finished:** Encrypted — client da razılaşdı

4. **Encrypted HTTP/2 data:** `GET / HTTP/2` artıq AES-256-GCM ilə şifrəli göndərilir

Browser certificate-i yoxlayır: CA chain etibarlımı? `example.com` SAN-da varmı? Expire olmayıb?

### Kod Nümunəsi
```bash
# TLS handshake debug etmək
openssl s_client -connect example.com:443 -tls1_3

# Certificate məlumatlarını görmək
echo | openssl s_client -connect example.com:443 2>/dev/null \
  | openssl x509 -noout -text \
  | grep -E "(Subject|DNS:|Not After)"
# Subject: CN=example.com
# DNS:example.com, DNS:www.example.com  ← SAN
# Not After : Jan 15 23:59:59 2026 GMT

# Certificate expire tarixini yoxlamaq
echo | openssl s_client -connect example.com:443 2>/dev/null \
  | openssl x509 -noout -dates
# notBefore=Jan 15 00:00:00 2024 GMT
# notAfter=Jan 15 23:59:59 2026 GMT

# Cipher suite yoxla
openssl s_client -connect example.com:443 2>/dev/null \
  | grep -i "cipher"
# Cipher is TLS_AES_256_GCM_SHA384

# TLS versiyasını məcbur et
openssl s_client -connect example.com:443 -tls1_2   # TLS 1.2
openssl s_client -connect example.com:443 -tls1_3   # TLS 1.3 only

# Certificate chain yoxla
openssl s_client -connect example.com:443 -showcerts 2>/dev/null \
  | grep -E "s:|i:"
# s:/CN=example.com          ← Server certificate
# i:/CN=DigiCert Intermediate ← İssuer (Intermediate CA)
# s:/CN=DigiCert Intermediate
# i:/CN=DigiCert Root         ← Root CA

# SSL Labs skor (online tool)
# https://www.ssllabs.com/ssltest/analyze.html?d=example.com
# A+ = ideal, A = yaxşı, B = zəif cipher/TLS version problem
```

```python
# Python ilə mTLS connection (microservice-to-microservice)
import ssl
import httpx

# mTLS — client certificate ilə
ssl_context = ssl.create_default_context(ssl.Purpose.SERVER_AUTH)

# Serverə trust edəcəyimiz CA
ssl_context.load_verify_locations('/etc/ssl/certs/company-ca.crt')

# Client-in öz certificate + private key-i
ssl_context.load_cert_chain(
    certfile='/etc/ssl/client/client.crt',
    keyfile ='/etc/ssl/client/client.key'
)

# mTLS ilə HTTP request
with httpx.Client(verify=ssl_context) as client:
    response = client.get('https://payment-service.internal/api/process')

# Certificate auto-generation (development üçün)
# openssl req -x509 -newkey rsa:4096 -keyout key.pem \
#   -out cert.pem -sha256 -days 365 -nodes \
#   -subj "/CN=payment-service.internal"
```

```php
// PHP/Laravel — TLS konfiqurasiyası
// Http client ilə custom certificate
$response = Http::withOptions([
    'verify'  => '/etc/ssl/certs/company-ca-bundle.pem',  // CA trust
    'cert'    => ['/etc/ssl/client/client.pem', 'passphrase'],  // mTLS
    'ssl_key' => '/etc/ssl/client/client-key.pem',
    'curl'    => [
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_SSLVERSION     => CURL_SSLVERSION_TLSv1_3,
    ],
])->get('https://internal-service.company.com/api/data');

// Certificate expire monitoring
function checkCertificateExpiry(string $host, int $port = 443): array
{
    $context = stream_context_create([
        'ssl' => [
            'capture_peer_cert' => true,
            'verify_peer'       => true,
            'verify_peer_name'  => true,
        ]
    ]);

    $socket = stream_socket_client(
        "ssl://{$host}:{$port}",
        $errno, $errstr, 30,
        STREAM_CLIENT_CONNECT,
        $context
    );

    if (!$socket) {
        return ['error' => $errstr];
    }

    $params = stream_context_get_params($socket);
    $cert   = openssl_x509_parse(
        $params['options']['ssl']['peer_certificate']
    );

    $expiryTimestamp = $cert['validTo_time_t'];
    $daysLeft        = (int)(($expiryTimestamp - time()) / 86400);

    fclose($socket);

    return [
        'host'       => $host,
        'subject'    => $cert['subject']['CN'] ?? '',
        'expiry'     => date('Y-m-d', $expiryTimestamp),
        'days_left'  => $daysLeft,
        'status'     => $daysLeft > 30 ? 'OK' : ($daysLeft > 0 ? 'WARNING' : 'EXPIRED'),
    ];
}

// Cron job: hər gün certificate-ləri yoxla
$domains = ['api.example.com', 'payment.example.com'];
foreach ($domains as $domain) {
    $info = checkCertificateExpiry($domain);
    if ($info['days_left'] < 30) {
        // Alert göndər — Slack, email
        Log::warning("Certificate expiring: {$domain} in {$info['days_left']} days");
    }
}
```

```
TLS 1.2 Handshake (2-RTT):
Client                              Server
  |---ClientHello (ciphers, random)->|
  |<--ServerHello (cipher, random)---|  ← RTT 1
  |<--Certificate -------------------|
  |<--ServerHelloDone----------------|
  |---ClientKeyExchange (RSA/DH)---->|  ← RTT 2
  |---ChangeCipherSpec-------------->|
  |---Finished (encrypted)---------->|
  |<--ChangeCipherSpec----------------|
  |<--Finished (encrypted)------------|
  |===Encrypted Application Data======|

TLS 1.3 Handshake (1-RTT):
Client                              Server
  |---ClientHello                    |
  |    + key_share (ECDH pub key)--->|
  |<--ServerHello + key_share--------|  ← RTT 1 (server key gönderir)
  |<--{Certificate}-encrypted--------|  ← Session key hər iki tərəfdə hesablandı
  |<--{CertificateVerify}-enc--------|
  |<--{Finished}-encrypted-----------|
  |---{Finished}-encrypted---------->|
  |===Encrypted Application Data======|

TLS 1.3 0-RTT (Session Resumption):
Client (PSK var)                    Server
  |---ClientHello + PSK + EarlyData->|  ← Data handshakesiz!
  |<--ServerHello + FinishedMessages--|
  |===Normal Encrypted Data==========|
  Risk: Replay attack mümkündür
```

```yaml
# Let's Encrypt certificate — certbot ilə
# Nginx üçün
certbot --nginx -d example.com -d www.example.com

# Auto-renewal
# /etc/cron.d/certbot
# 0 12 * * * root certbot renew --quiet

# Manual renewal test
certbot renew --dry-run

# Nginx TLS optimal konfigurasiyası
# /etc/nginx/snippets/ssl-params.conf
ssl_protocols TLSv1.2 TLSv1.3;
ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:
            ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:
            TLS_AES_128_GCM_SHA256:TLS_AES_256_GCM_SHA384;
ssl_prefer_server_ciphers off;  # Client cipher tercihini hörmət et

ssl_session_timeout   1d;
ssl_session_cache     shared:SSL:50m;
ssl_session_tickets   off;  # Forward secrecy üçün

ssl_stapling          on;   # OCSP Stapling
ssl_stapling_verify   on;
resolver              8.8.8.8 8.8.4.4 valid=300s;

# HSTS
add_header Strict-Transport-Security "max-age=63072000; includeSubDomains; preload" always;
```

### İkinci Nümunə — mTLS Service Mesh

```
mTLS — Zero Trust Network Architecture:

Problem: Microservices arası şəbəkə trafikini şifrələmək + autentifikasiya

Traditional TLS:
  Client → [TLS] → Server
  Server autentifikasiya edilir (certificate)
  Client autentifikasiya edilmir!

mTLS:
  Client → [TLS (both sides)] → Server
  Hər iki tərəf certificate göndərir
  Server client-i tanıyır, client server-i tanıyır

Istio Service Mesh (Kubernetes):
  Hər pod-un yanında Envoy sidecar proxy var
  Sidecar-lar arası mTLS avtomatik
  Application kod dəyişmədən şifrəli kommunikasiya

Sertifikat management:
  Certificate Authority (CA) = Istio (ya da cert-manager)
  Hər servis üçün avtomatik certificate issue
  Certificate rotation: avtomatik, 24 saatda bir
  SPIFFE ID: spiffe://cluster.local/ns/default/sa/payment-service

Nəticə:
  payment-service → order-service: mTLS, identity verified
  Xarici traffic payment-service-ə birbaşa çata bilmir
  Compromised pod → digər servislər onu tanımır (certificate yoxdur)
```

## Praktik Tapşırıqlar

- `openssl s_client -connect google.com:443 -tls1_3` ilə TLS handshake output-unu oxuyun: cipher, certificate chain, OCSP stapling statusunu anlayın
- Certificate expire monitoring skripti yazın: 5 domeni yoxlayan, 30 gündən az qaldıqda Slack alert göndərən PHP CLI skripti
- Let's Encrypt ilə manual certificate alma prosesini (`certbot`) tam başa düşün — ACME protokolu necə işləyir
- mTLS qurun: iki PHP Laravel service arasında client certificate ilə autentifikasiya — bir servis digərini tanımasın
- TLS 1.3 vs TLS 1.2 latency fərqini ölçün: `openssl s_time -connect api.example.com:443 -new -time 10`
- SSL Labs testi: bir saytın TLS konfiqurasiyasını qiymətləndirin, A+ almaq üçün nə lazımdır?

## Əlaqəli Mövzular
- [HTTP Versions](02-http-versions.md) — HTTP/2 TLS tələb edir; HTTP/3 QUIC built-in TLS 1.3
- [TCP vs UDP](01-tcp-vs-udp.md) — TLS TCP üzərindədir; QUIC/TLS 1.3 UDP üzərindədir
- [WebSockets](06-websockets.md) — wss:// = TLS üzərindən WebSocket
- [DNS Resolution](04-dns-resolution.md) — DNS resolution TLS handshake-dən əvvəl baş verir
