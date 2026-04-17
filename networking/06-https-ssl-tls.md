# HTTPS, SSL/TLS

## Nədir? (What is it?)

HTTPS (HTTP Secure) HTTP-nin TLS (Transport Layer Security) uzerinden isleyen versiyasidir. Data-ni encrypt edir, server identity-ni tesdiqleyir ve data integrity-ni temin edir. SSL (Secure Sockets Layer) TLS-in kohne ve artiq istifade olunmayan selefiddir.

```
Versiya tarixcesi:
SSL 1.0  (1994) - Hec vaxt release olunmadi
SSL 2.0  (1995) - Ciddi zeifliklər, deprecated
SSL 3.0  (1996) - POODLE attack, deprecated
TLS 1.0  (1999) - SSL 3.0 upgrade, deprecated 2020
TLS 1.1  (2006) - Deprecated 2020
TLS 1.2  (2008) - Hal-hazirda genis istifade olunur
TLS 1.3  (2018) - En yeni, en suretli, en tehlukesiz
```

**3 esas meqsed:**
1. **Confidentiality (Gizlilik):** Data encrypt olunur, ucuncu teref oxuya bilmir
2. **Integrity (Butovluk):** Data transfer zamani deyisdirilmeyib
3. **Authentication (Dogrulama):** Server (ve optional client) kimliyi tesdiqlanir

## Necə İşləyir? (How does it work?)

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

### TLS 1.3 Handshake (Daha suretli)

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
Client daha evvel connect olubsa, ilk mesajla birlikde data gondermek olar.
Amma replay attack riski var, yalniz idempotent request-ler ucun istifade edin.
```

### TLS 1.3 vs 1.2 Ferqleri

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
  +--> Intermediate CA Certificate (Root CA imzalayib)
         |
         +--> Server Certificate (Intermediate CA imzalayib)
                (example.com)

Verification process:
1. Server oz certificate + intermediate certificate-i gonderir
2. Browser server cert-in intermediate CA terefinden imzalandigini yoxlayir
3. Browser intermediate cert-in Root CA terefinden imzalandigini yoxlayir
4. Root CA browser-in trust store-undadir
5. Certificate chain valid -> HTTPS lock icon gosteriir

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
  - Domain sahibliyini yoxlayir
  - Automated, ucuz/pulsuz (Let's Encrypt)
  - Adi web sitelar ucun

OV (Organization Validation):
  - Shirket melumatlari yoxlanir
  - 1-3 gun
  - Biznes sitelari

EV (Extended Validation):
  - En ciddi yoxlama
  - Shirketin hüquqi statusu yoxlanir
  - Bank, maliyye sitelari
  - Browser-da yesil bar (artiq cogu browser gostermir)

Wildcard: *.example.com (butun subdomain-ler)
Multi-domain (SAN): Bir cert-de bir nece domain
```

## Əsas Konseptlər (Key Concepts)

### Cipher Suites

```
TLS 1.2 cipher suite numunesi:
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

Key exchange TLS 1.3-de hemise ECDHE (forward secrecy mecburi)
```

### Perfect Forward Secrecy (PFS)

```
PFS olmadan (RSA key exchange):
  Server-in private key-i compromise olsa,
  BUTUN evvelki traffic decrypt oluna biler!
  (Cunki session key-ler server private key ile encrypt olunub)

PFS ile (DHE/ECDHE):
  Her session ucun yeni ephemeral key pair yaranir.
  Server private key compromise olsa bele,
  evvelki session-larin key-leri tapila bilmir.

  Session 1: Ephemeral key A -> session key 1 -> discard A
  Session 2: Ephemeral key B -> session key 2 -> discard B
  Session 3: Ephemeral key C -> session key 3 -> discard C

  Server private key leaked -> Session 1,2,3 hele de secure
```

### HSTS (HTTP Strict Transport Security)

```
Response header:
Strict-Transport-Security: max-age=31536000; includeSubDomains; preload

Bu ne edir?
1. Browser bu domain-e yalniz HTTPS ile connect edir
2. HTTP -> HTTPS redirect-i browser ozü edir (301 gozlemeden)
3. User http://example.com yazsa, browser avtomatik https://example.com acir
4. Invalid certificate olsa, user bypass ede bilmir

HSTS Preload List:
  - Browser-da built-in HTTPS-only domain siyahisi
  - hstspreload.org-da qeydiyyat
  - Chrome, Firefox, Safari, Edge istifade edir
```

### Certificate Pinning

```
Nedir?
  Application yalniz specific certificate ve ya public key-i qebul edir.
  CA compromise olsa bele, yanlis cert qebul olunmaz.

Types:
1. Pin to leaf certificate - cert deyisende app update lazim
2. Pin to intermediate CA - daha flexible
3. Pin to public key (SPKI) - cert renew olsa bele key eyni qala biler

HTTP Public Key Pinning (HPKP) - DEPRECATED (coxdiqqetlidir)
  Browser-lar artiq desteklemir
  Evezinde Certificate Transparency istifade olunur

Mobile apps-da certificate pinning hele de populyardir.
```

### OCSP ve CRL

```
Certificate revocation check:

CRL (Certificate Revocation List):
  - CA butun revoke olunmus cert-lerin siyahisini publish edir
  - Problem: siyahi boyuye biler, yavas yuklenir

OCSP (Online Certificate Status Protocol):
  - Real-time tək cert ucun status sorgusu
  - Daha suretli, amma privacy concern (CA her visit-i gorur)

OCSP Stapling:
  - Server ozü OCSP response-u alir ve TLS handshake-de gonderir
  - Client ayrica OCSP sorgusu etmir
  - Best practice: OCSP stapling aktiv edin
```

## PHP/Laravel ilə İstifadə

### Laravel HTTPS Setup

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

// .env
APP_URL=https://example.com

// HSTS Header in Nginx
# add_header Strict-Transport-Security "max-age=31536000; includeSubDomains; preload" always;

// Or via Laravel middleware
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

### Nginx SSL Configuration

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

    # Redirect HTTP to HTTPS
    # (in separate server block)
}

server {
    listen 80;
    server_name example.com;
    return 301 https://$server_name$request_uri;
}
```

### Let's Encrypt with Certbot

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

### PHP cURL with TLS

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

## Interview Sualları

### Q1: TLS handshake-i izah edin.
**A:** TLS 1.2: 2 RTT - ClientHello (versions, ciphers), ServerHello (chosen cipher + certificate), client key exchange, both sides derive session keys. TLS 1.3: 1 RTT - client ilk mesajda key share gonderir, server certificate + key share ile cavab verir, derhal encrypted communication baslar.

### Q2: Perfect Forward Secrecy nedir?
**A:** PFS her session ucun yeni ephemeral key-ler istifade edir (ECDHE). Server-in private key-i compromise olsa bele, evvelki session-larin decrypt olunmasi mumkun deyil, cunki session key-ler ayri yaranib ve silinib. TLS 1.3-de PFS mecburidir.

### Q3: Certificate chain nece isleyir?
**A:** Server cert Intermediate CA terefinden imzalanir, Intermediate Root CA terefinden. Browser Root CA-ni oz trust store-unda yoxlayir. Eger chain valid ise, connection trusted-dir. Server hemise intermediate cert-i de gondermalidir.

### Q4: HSTS nedir ve niye lazimdir?
**A:** Browser-a bu domain-e hemise HTTPS ile connect et deyen header. SSL stripping attack-larin qarsisini alir (attacker HTTP-ye downgrade edir). Preload list ile ilk visitde bele HTTPS mecbur edilir.

### Q5: TLS 1.2 ve TLS 1.3 arasinda ferqler?
**A:** TLS 1.3: 1 RTT (vs 2), 0-RTT resumption, yalniz 5 guclu cipher suite (zeif olanlar silindi), mecburi PFS (RSA key exchange yoxdur), renegotiation silindi, compression silindi.

### Q6: Self-signed certificate ile CA-signed cert arasinda ferq?
**A:** Self-signed cert ozunu oz imzalayir - browser trust etmir (warning gosterir). CA-signed cert trusted CA terefinden imzalanir - browser qebul edir. Development ucun self-signed, production ucun CA-signed (Let's Encrypt pulsuz) istifade edin.

### Q7: mTLS (Mutual TLS) nedir?
**A:** Normal TLS-de yalniz server authenticate olunur. mTLS-de client de certificate gonderir ve server client-i verify edir. Microservice-ler arasi communication, API security ucun istifade olunur. Her iki teref birbirini verify edir.

## Best Practices

1. **Minimum TLS 1.2 istifade edin:** TLS 1.0, 1.1, SSL hamisini disable edin. Ideal olaraq TLS 1.3.

2. **Strong cipher suites secin:** ECDHE key exchange (PFS ucun), AES-GCM ve ya ChaCha20-Poly1305 encryption.

3. **HSTS aktiv edin:** `max-age=31536000; includeSubDomains; preload`. Preload list-e elave edin.

4. **OCSP Stapling aktiv edin:** Performance ve privacy ucun.

5. **Certificate auto-renewal:** Let's Encrypt ile 90 gunluk cert + auto-renew. Expired cert = site down.

6. **SSL_VERIFYPEER hemise true:** PHP/cURL-da certificate verification-i sondurmeyin (`CURLOPT_SSL_VERIFYPEER = false` etmeyin). Development ucun bele self-signed cert install edin.

7. **Security headers elave edin:**
   ```
   Strict-Transport-Security: max-age=31536000; includeSubDomains
   X-Content-Type-Options: nosniff
   X-Frame-Options: DENY
   Content-Security-Policy: default-src 'self'
   ```

8. **Certificate monitoring:** Cert expire tarixini monitor edin. Alerting qurun.
