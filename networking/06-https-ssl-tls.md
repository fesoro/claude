# HTTPS, SSL/TLS (Middle)

## İcmal

HTTPS (HTTP Secure) HTTP-nin TLS (Transport Layer Security) üzərindən işləyən versiyasıdır. Data-nı encrypt edir, server identity-ni təsdiqləyir və data integrity-ni təmin edir. SSL (Secure Sockets Layer) TLS-in köhnə və artıq istifadə olunmayan sələfidir.

```
Versiya tarixçəsi:
SSL 1.0  (1994) - Heç vaxt release olunmadı
SSL 2.0  (1995) - Ciddi zəifliklər, deprecated
SSL 3.0  (1996) - POODLE attack, deprecated
TLS 1.0  (1999) - SSL 3.0 upgrade, deprecated 2020
TLS 1.1  (2006) - Deprecated 2020
TLS 1.2  (2008) - Hal-hazırda geniş istifadə olunur
TLS 1.3  (2018) - Ən yeni, ən sürətli, ən təhlükəsiz
```

3 əsas məqsəd:
1. **Confidentiality (Gizlilik):** Data encrypt olunur, üçüncü tərəf oxuya bilmir
2. **Integrity (Bütövlük):** Data transfer zamanı dəyişdirilməyib
3. **Authentication (Doğrulama):** Server (və optional client) kimliyini təsdiqlanır

## Niyə Vacibdir

Production-da HTTPS olmadan brauzerlər "Not Secure" xəbərdarlığı göstərir, Google axtarış sıralamaqasını endirir, istifadəçilər məlumatlarını risk altına atar. Backend developer olaraq TLS versiyasını düzgün konfiqurasiya etmək, sertifikat zəncirini başa düşmək, HSTS qurmaq, OCSP stapling aktiv etmək bilavasitə məsuliyyət dairənizdədir. mTLS isə microservice-lər arası autentifikasiyanın əsasıdır.

## Əsas Anlayışlar

### TLS 1.2 Handshake

```
Client                                Server
  |                                     |
  |  1. ClientHello                     |
  |  (TLS version, cipher suites,      |
  |   client random)                    |
  |----------------------------------->|
  |                                     |
  |  2. ServerHello                     |
  |  (chosen cipher, server random)    |
  |  3. Certificate (server cert)       |
  |  4. ServerKeyExchange (DH params)  |
  |  5. ServerHelloDone                |
  |<-----------------------------------|
  |                                     |
  |  6. ClientKeyExchange (DH public)  |
  |  7. ChangeCipherSpec               |
  |  8. Finished (encrypted)           |
  |----------------------------------->|
  |                                     |
  |  9. ChangeCipherSpec               |
  | 10. Finished (encrypted)           |
  |<-----------------------------------|
  |                                     |
  |  ====  Encrypted Data  ====        |
  |<=================================>|

Total: 2 RTT (Round-Trip Time)
```

### TLS 1.3 Handshake (Daha sürətli)

```
Client                                Server
  |                                     |
  |  1. ClientHello                     |
  |  (TLS 1.3, cipher suites,         |
  |   key_share with DH params,        |
  |   client random)                    |
  |----------------------------------->|
  |                                     |
  |  2. ServerHello (key_share)        |
  |  3. EncryptedExtensions            |
  |  4. Certificate                     |
  |  5. CertificateVerify              |
  |  6. Finished                        |
  |<-----------------------------------|
  |                                     |
  |  7. Finished                        |
  |----------------------------------->|
  |                                     |
  |  ====  Encrypted Data  ====        |
  |<=================================>|

Total: 1 RTT (vs TLS 1.2-nin 2 RTT)

0-RTT Resumption (PSK):
Client daha əvvəl connect olubsa, ilk məsajla birlikdə data göndərmək olar.
Amma replay attack riski var, yalnız idempotent request-lər üçün istifadə edin.
```

### TLS 1.3 vs 1.2 Fərqləri

```
+---------------------+------------------+------------------+
| Feature             | TLS 1.2          | TLS 1.3          |
+---------------------+------------------+------------------+
| Handshake RTT       | 2 RTT            | 1 RTT            |
| 0-RTT resumption    | No               | Yes              |
| RSA key exchange    | Supported        | Removed           |
| Forward secrecy     | Optional         | Mandatory         |
| Cipher suites       | Many (some weak) | 5 strong only    |
| Compression         | Optional         | Removed           |
| Renegotiation       | Supported        | Removed           |
+---------------------+------------------+------------------+
```

### Certificate Chain

```
Root CA Certificate (Self-signed, browser-da built-in)
  |
  +--> Intermediate CA Certificate (Root CA imzalayıb)
         |
         +--> Server Certificate (Intermediate CA imzalayıb)
                (example.com)

Verification process:
1. Server öz certificate + intermediate certificate-i göndərir
2. Browser server cert-in intermediate CA tərəfindən imzalandığını yoxlayır
3. Browser intermediate cert-in Root CA tərəfindən imzalandığını yoxlayır
4. Root CA browser-in trust store-undadır
5. Certificate chain valid -> HTTPS lock icon göstərir

Chain of Trust:
[Browser Trust Store] --> [Root CA] --> [Intermediate CA] --> [Server Cert]
     (built-in)         (self-signed)   (signed by Root)   (signed by Inter.)
```

### Certificate Contents

```
Certificate Fields:
  Subject:        CN=example.com, O=Example Inc, C=US
  Issuer:         CN=Let's Encrypt Authority X3
  Valid From:     Jan 1, 2026
  Valid To:       Apr 1, 2026
  Public Key:     RSA 2048-bit / EC P-256
  Serial Number:  03:A1:B2:C3:D4...
  Signature Algo: SHA256withRSA

Extensions:
  Subject Alternative Names (SAN):
    DNS: example.com
    DNS: *.example.com
    DNS: api.example.com
  Key Usage: Digital Signature, Key Encipherment
  Extended Key Usage: TLS Web Server Authentication
  OCSP Responder: http://ocsp.letsencrypt.org
  CRL Distribution: http://crl.letsencrypt.org
```

### Certificate Types

```
DV (Domain Validation):
  - Domain sahibliyini yoxlayır
  - Automated, ucuz/pulsuz (Let's Encrypt)
  - Adi web saytlar üçün

OV (Organization Validation):
  - Şirkət məlumatları yoxlanır
  - 1-3 gün
  - Biznes saytları

EV (Extended Validation):
  - Ən ciddi yoxlama
  - Şirkətin hüquqi statusu yoxlanır
  - Bank, maliyyə saytları

Wildcard: *.example.com (bütün subdomain-lər)
Multi-domain (SAN): Bir cert-də bir neçə domain
```

### Cipher Suites

```
TLS 1.2 cipher suite nümunəsi:
TLS_ECDHE_RSA_WITH_AES_256_GCM_SHA384
 |     |     |        |    |       |
 |     |     |        |    |       +-- MAC algorithm
 |     |     |        |    +---------- Block cipher mode
 |     |     |        +--------------- Encryption algorithm
 |     |     +------------------------ Authentication
 |     +------------------------------ Key exchange
 +------------------------------------ Protocol

TLS 1.3 cipher suites (only 5):
  TLS_AES_256_GCM_SHA384
  TLS_AES_128_GCM_SHA256
  TLS_CHACHA20_POLY1305_SHA256
  TLS_AES_128_CCM_SHA256
  TLS_AES_128_CCM_8_SHA256

Key exchange TLS 1.3-də həmişə ECDHE (forward secrecy məcburi)
```

### Perfect Forward Secrecy (PFS)

```
PFS olmadan (RSA key exchange):
  Server-in private key-i compromise olsa,
  BÜTÜN əvvəlki traffic decrypt oluna bilər!
  (Çünki session key-lər server private key ilə encrypt olunub)

PFS ilə (DHE/ECDHE):
  Hər session üçün yeni ephemeral key pair yaranır.
  Server private key compromise olsa belə,
  əvvəlki session-ların key-ləri tapıla bilmir.

  Session 1: Ephemeral key A -> session key 1 -> discard A
  Session 2: Ephemeral key B -> session key 2 -> discard B
  Session 3: Ephemeral key C -> session key 3 -> discard C

  Server private key leaked -> Session 1,2,3 hələ də secure
```

### HSTS (HTTP Strict Transport Security)

```
Response header:
Strict-Transport-Security: max-age=31536000; includeSubDomains; preload

Bu nə edir?
1. Browser bu domain-ə yalnız HTTPS ilə connect edir
2. HTTP -> HTTPS redirect-i browser özü edir (301 gözləmədən)
3. User http://example.com yazsa, browser avtomatik https://example.com açır
4. Invalid certificate olsa, user bypass edə bilmir

HSTS Preload List:
  - Browser-da built-in HTTPS-only domain siyahısı
  - hstspreload.org-da qeydiyyat
  - Chrome, Firefox, Safari, Edge istifadə edir
```

### Certificate Pinning

```
Nədir?
  Application yalnız specific certificate və ya public key-i qəbul edir.
  CA compromise olsa belə, yanlış cert qəbul olunmaz.

Types:
1. Pin to leaf certificate - cert dəyişəndə app update lazım
2. Pin to intermediate CA - daha flexible
3. Pin to public key (SPKI) - cert renew olsa belə key eyni qala bilər

HTTP Public Key Pinning (HPKP) - DEPRECATED
  Browser-lar artıq dəstəkləmir
  Əvəzinə Certificate Transparency istifadə olunur

Mobile apps-da certificate pinning hələ də populyardır.
```

### OCSP və CRL

```
Certificate revocation check:

CRL (Certificate Revocation List):
  - CA bütün revoke olunmuş cert-lərin siyahısını publish edir
  - Problem: siyahı böyüyə bilər, yavaş yüklənir

OCSP (Online Certificate Status Protocol):
  - Real-time tək cert üçün status sorğusu
  - Daha sürətli, amma privacy concern (CA hər visit-i görür)

OCSP Stapling:
  - Server özü OCSP response-u alır və TLS handshake-də göndərir
  - Client ayrıca OCSP sorğusu etmir
  - Best practice: OCSP stapling aktiv edin
```

## Praktik Baxış

**Real layihələrdə istifadəsi:**
- Let's Encrypt + Certbot Laravel production-da standart seçimdir — 90 günlük sertifikat + auto-renew
- Nginx-də `ssl_protocols TLSv1.2 TLSv1.3;` konfiqurasiyası TLS 1.0/1.1-i disable edir
- mTLS (mutual TLS) service-to-service autentifikasiyası üçün: hər microservice client certificate göndərir

**Trade-off-lar:**
- TLS 1.3: 1 RTT (daha sürətli) amma köhnə client-lər dəstəkləmir (iOS 10-, Android 7-)
- 0-RTT (PSK): ən sürətli, amma replay attack riski — yalnız GET/idempotent üçün
- Wildcard cert: rahat idarəetmə amma privkey leak bütün subdomain-ləri təsir edir

**Common mistakes:**
- `CURLOPT_SSL_VERIFYPEER = false` dev-də "tez həll" üçün istifadə etmək — prod-a da keçir (man-in-the-middle riskini bağışlayır)
- Expired sertifikat — monitoring olmadan xəbər olmadan site down olur
- TLS 1.0/1.1-i aktiv saxlamaq — PCI DSS compliance itirilir, brauzerlər xəbərdarlıq göstərir
- Intermediate cert-i göndərməmək — bəzi client-lər chain-i tamamlaya bilmir, TLS xətası alır

**Anti-pattern:** Self-signed cert production-da — brauzerlər blok edir, SDK-lar xəta verir; Let's Encrypt pulsuzdur.

## Nümunələr

### Ümumi Nümunə

TLS 1.3 handshake real dünyada:
1. Client: `ClientHello` göndərir (key_share daxil) — TCP SYN ilə birlikdə gedə bilər
2. Server: `ServerHello` + certificate + `Finished` (hamısı eyni uçuşda) — 1 RTT
3. Client: `Finished` — encrypted data başlayır
4. Reconnect zamanı 0-RTT: client 1-ci mesajda application data göndərir — 0 RTT

### Kod Nümunəsi

Laravel HTTPS Setup:

```php
// Force HTTPS in production
// app/Providers/AppServiceProvider.php
public function boot(): void
{
    if ($this->app->environment('production')) {
        URL::forceScheme('https');
    }
}

// Middleware: Redirect HTTP to HTTPS
// app/Http/Middleware/ForceHttps.php
class ForceHttps
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->secure() && app()->environment('production')) {
            return redirect()->secure($request->getRequestUri());
        }
        return $next($request);
    }
}
```

HSTS header middleware:

```php
class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $response->headers->set(
            'Strict-Transport-Security',
            'max-age=31536000; includeSubDomains; preload'
        );

        return $response;
    }
}
```

Nginx SSL Configuration:

```nginx
server {
    listen 443 ssl http2;
    server_name example.com;

    # Certificate files
    ssl_certificate /etc/letsencrypt/live/example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/example.com/privkey.pem;

    # TLS version
    ssl_protocols TLSv1.2 TLSv1.3;

    # Cipher suites
    ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384;
    ssl_prefer_server_ciphers off;

    # OCSP Stapling
    ssl_stapling on;
    ssl_stapling_verify on;
    ssl_trusted_certificate /etc/letsencrypt/live/example.com/chain.pem;

    # Session cache (performance)
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 1d;
    ssl_session_tickets off;

    # HSTS
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains; preload" always;
}

server {
    listen 80;
    server_name example.com;
    return 301 https://$server_name$request_uri;
}
```

Let's Encrypt with Certbot:

```bash
# Install
sudo apt install certbot python3-certbot-nginx

# Get certificate
sudo certbot --nginx -d example.com -d www.example.com

# Auto-renew (crontab)
0 0 1 * * certbot renew --quiet

# Certificate files
/etc/letsencrypt/live/example.com/fullchain.pem  # cert + intermediate
/etc/letsencrypt/live/example.com/privkey.pem     # private key
/etc/letsencrypt/live/example.com/chain.pem       # intermediate only
```

PHP cURL with TLS:

```php
$ch = curl_init('https://api.example.com/data');

// TLS options
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);     // Verify server cert
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);          // Verify hostname
curl_setopt($ch, CURLOPT_CAINFO, '/etc/ssl/certs/ca-certificates.crt');
curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);

// Client certificate (mutual TLS)
curl_setopt($ch, CURLOPT_SSLCERT, '/path/to/client.crt');
curl_setopt($ch, CURLOPT_SSLKEY, '/path/to/client.key');

$response = curl_exec($ch);
$info = curl_getinfo($ch);
echo "TLS version: " . $info['ssl_version'];  // TLSv1.3
curl_close($ch);
```

## Praktik Tapşırıqlar

**Tapşırıq 1: TLS konfiqurasiyasını yoxlayın**

```bash
# TLS versiyasını və cipher suite-ı görün
openssl s_client -connect example.com:443 -tls1_3

# SSL Labs testi (A+ reytinq hədəf)
# https://www.ssllabs.com/ssltest/

# Sertifikat expire tarixini yoxlayın
echo | openssl s_client -connect example.com:443 2>/dev/null \
  | openssl x509 -noout -dates

# HSTS header-ini yoxlayın
curl -I https://example.com | grep -i strict
```

**Tapşırıq 2: Let's Encrypt quraşdırması**

Laravel production serverda addım-addım:

```bash
# 1. Certbot quraşdır
sudo apt install certbot python3-certbot-nginx

# 2. Nginx üçün sertifikat al
sudo certbot --nginx -d yourdomain.com -d www.yourdomain.com

# 3. Auto-renew test et
sudo certbot renew --dry-run

# 4. Crontab əlavə et
(crontab -l 2>/dev/null; echo "0 3 * * * certbot renew --quiet") | crontab -
```

**Tapşırıq 3: Security headers əlavə edin**

Laravel middleware ilə aşağıdakı security header-lərini əlavə edin:

```php
class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $headers = [
            'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains; preload',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
        ];

        foreach ($headers as $key => $value) {
            $response->headers->set($key, $value);
        }

        return $response;
    }
}
```

Test: `curl -I https://yourdomain.com` ilə header-ləri yoxlayın.

## Əlaqəli Mövzular

- [HTTP Protocol](05-http-protocol.md)
- [Network Security](26-network-security.md)
- [mTLS Deep Dive](35-mtls-deep-dive.md)
- [Zero Trust](33-zero-trust.md)
- [API Security](17-api-security.md)
