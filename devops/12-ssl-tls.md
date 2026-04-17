# SSL/TLS və HTTPS

## Nədir? (What is it?)

SSL (Secure Sockets Layer) və TLS (Transport Layer Security) internet üzərindən məlumatı şifrələyərək göndərən protokollardır. TLS SSL-in təkmilləşdirilmiş versiyasıdır (SSL 3.0 -> TLS 1.0 -> 1.1 -> 1.2 -> 1.3). HTTPS = HTTP + TLS. Məqsəd: məlumatın yolda oxunmasının və dəyişdirilməsinin qarşısını almaq.

## Əsas Konseptlər (Key Concepts)

### SSL/TLS Necə İşləyir?

```
TLS Handshake (TLS 1.2):
1. Client Hello     -> Client: "Salam, bu cipher-ləri dəstəkləyirəm"
2. Server Hello     <- Server: "Bu cipher-i seçdim, budur sertifikatım"
3. Certificate      <- Server sertifikatını göndərir
4. Key Exchange     -> Client: sertifikatı yoxlayır, session key yaradır
5. Change Cipher    <-> Hər iki tərəf şifrələnmiş əlaqəyə keçir
6. Encrypted Data   <-> Şifrələnmiş data axını başlayır

TLS 1.3 (daha sürətli):
1. Client Hello + Key Share  -> 
2. Server Hello + Key Share  <-
3. Encrypted Data            <->
(1-RTT, hətta 0-RTT mümkündür)
```

### Şifrələmə Tipləri

```
Symmetric Encryption (Simmetrik):
- Eyni açar şifrələmə və deşifrələmə üçün istifadə olunur
- Sürətlidir
- AES-128, AES-256, ChaCha20
- Data transferi üçün istifadə olunur

Asymmetric Encryption (Asimmetrik):
- Public key (açıq açar) + Private key (gizli açar)
- Public key ilə şifrələ, private key ilə deşifrələ
- Yavaşdır
- RSA, ECDSA, Ed25519
- TLS handshake və sertifikatlar üçün istifadə olunur

Hash Functions:
- Bir istiqamətli (geri çevrilməz)
- SHA-256, SHA-384
- Məlumatın dəyişmədiyini yoxlamaq üçün (integrity)
```

### Sertifikatlar

```bash
# Sertifikat zənciri:
# Root CA (Certificate Authority) - Brauzerlərdə öncədən yüklü
#   └── Intermediate CA
#       └── Server Certificate (sizin sertifikat)

# Sertifikat tipləri:
# DV (Domain Validation) - Yalnız domain yoxlanır (Let's Encrypt)
# OV (Organization Validation) - Təşkilat yoxlanır
# EV (Extended Validation) - Ətraflı yoxlama, yaşıl çubuq
# Wildcard (*.example.com) - Bütün sub-domain-lar
# SAN (Subject Alternative Name) - Bir neçə domain bir sertifikatda

# Sertifikat formatları:
# PEM (.pem, .crt, .cer) - Base64 encoded, ən çox istifadə olunan
# DER (.der, .cer) - Binary format
# PFX/PKCS12 (.pfx, .p12) - Private key + certificate bir faylda
# JKS (.jks) - Java KeyStore

# Sertifikat məlumatını görmək
openssl x509 -in certificate.pem -text -noout
openssl x509 -in certificate.pem -dates -noout          # Tarixlər
openssl x509 -in certificate.pem -subject -noout         # Domain info
openssl x509 -in certificate.pem -issuer -noout          # CA info

# Remote server sertifikatını yoxlamaq
openssl s_client -connect example.com:443 -servername example.com
echo | openssl s_client -connect example.com:443 2>/dev/null | openssl x509 -dates -noout
```

### Let's Encrypt və Certbot

```bash
# Let's Encrypt - Pulsuz, avtomatik, açıq SSL sertifikat təminatçısı
# 90 gün müddətli sertifikatlar (avtomatik yenilənir)

# Certbot quraşdırma
sudo apt update
sudo apt install certbot

# Nginx plugin ilə
sudo apt install python3-certbot-nginx
sudo certbot --nginx -d example.com -d www.example.com

# Apache plugin ilə
sudo apt install python3-certbot-apache
sudo certbot --apache -d example.com -d www.example.com

# Standalone (web server olmadan)
sudo certbot certonly --standalone -d example.com

# Webroot (mövcud web server ilə)
sudo certbot certonly --webroot -w /var/www/laravel/public -d example.com

# DNS challenge (wildcard üçün)
sudo certbot certonly --manual --preferred-challenges dns -d "*.example.com"

# Sertifikat yeniləmə
sudo certbot renew                     # Bütün sertifikatları yenilə
sudo certbot renew --dry-run           # Test (real yeniləmə yox)

# Avtomatik yeniləmə (cron/systemd timer)
# /etc/cron.d/certbot - avtomatik quraşdırılır
0 0,12 * * * root certbot renew --quiet --post-hook "systemctl reload nginx"

# Sertifikat faylları
/etc/letsencrypt/live/example.com/
├── cert.pem          # Server sertifikatı
├── chain.pem         # Intermediate CA
├── fullchain.pem     # Server + Intermediate (Nginx/Apache istifadə edir)
└── privkey.pem       # Private key

# Rate limits:
# - 50 sertifikat/domain/həftə
# - 5 duplicate sertifikat/həftə
# - 300 pending authorization/3 saat
```

### Self-Signed Sertifikat

```bash
# Development mühiti üçün (production-da istifadə ETMƏYİN)

# Private key yarat
openssl genrsa -out server.key 2048

# CSR (Certificate Signing Request) yarat
openssl req -new -key server.key -out server.csr \
    -subj "/C=AZ/ST=Baku/L=Baku/O=Company/CN=localhost"

# Self-signed sertifikat yarat (365 gün)
openssl x509 -req -days 365 -in server.csr -signkey server.key -out server.crt

# Bir əmrlə (key + cert)
openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
    -keyout server.key -out server.crt \
    -subj "/C=AZ/ST=Baku/L=Baku/O=Dev/CN=localhost"

# SAN ilə (Subject Alternative Name)
openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
    -keyout server.key -out server.crt \
    -subj "/CN=localhost" \
    -addext "subjectAltName=DNS:localhost,DNS:*.localhost,IP:127.0.0.1"
```

### mTLS (Mutual TLS)

```
Normal TLS:
- Yalnız server sertifikat göstərir
- Client serveri yoxlayır, server clienti yoxlamır

mTLS (Mutual TLS):
- Həm server, həm client sertifikat göstərir
- Hər iki tərəf bir-birini yoxlayır
- Microservice-lər arası güvənli əlaqə üçün istifadə olunur
- API authentication üçün (client certificate auth)
- Service mesh-lərdə (Istio) avtomatik istifadə olunur
```

```nginx
# Nginx mTLS konfiqurasiyası
server {
    listen 443 ssl;
    server_name api.example.com;

    ssl_certificate /etc/ssl/server.crt;
    ssl_certificate_key /etc/ssl/server.key;

    # Client sertifikat tələb et
    ssl_client_certificate /etc/ssl/ca.crt;    # CA sertifikatı
    ssl_verify_client on;                       # Client verify aktiv

    location / {
        proxy_pass http://backend;
        proxy_set_header X-Client-Cert $ssl_client_s_dn;
    }
}
```

### SSL/TLS Best Practice Konfiqurasiya

```nginx
# /etc/nginx/snippets/ssl-params.conf

# Protokol versiyaları (TLS 1.2 və 1.3 yalnız)
ssl_protocols TLSv1.2 TLSv1.3;

# Cipher suites
ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384;
ssl_prefer_server_ciphers off;

# OCSP Stapling
ssl_stapling on;
ssl_stapling_verify on;
resolver 8.8.8.8 8.8.4.4 valid=300s;
resolver_timeout 5s;

# SSL session cache
ssl_session_timeout 1d;
ssl_session_cache shared:SSL:50m;
ssl_session_tickets off;

# DH parameters (2048 bit minimum)
ssl_dhparam /etc/nginx/dhparam.pem;

# HSTS (HTTP Strict Transport Security)
add_header Strict-Transport-Security "max-age=63072000; includeSubDomains; preload" always;
```

```bash
# DH parameters yaratmaq
sudo openssl dhparam -out /etc/nginx/dhparam.pem 2048

# SSL konfiqurasiyanı test etmək
# https://www.ssllabs.com/ssltest/
# A+ reytinq almaq üçün yuxarıdakı konfiqurasiya yetərlidir
```

## Praktiki Nümunələr (Practical Examples)

### SSL Sertifikat Monitoring Script

```bash
#!/bin/bash
# ssl-check.sh - Sertifikat bitmə tarixini yoxla

DOMAINS=("example.com" "api.example.com" "admin.example.com")
ALERT_DAYS=30
ALERT_EMAIL="devops@company.com"

for DOMAIN in "${DOMAINS[@]}"; do
    EXPIRY=$(echo | openssl s_client -servername "$DOMAIN" -connect "$DOMAIN:443" 2>/dev/null \
        | openssl x509 -noout -enddate 2>/dev/null | cut -d= -f2)

    if [ -z "$EXPIRY" ]; then
        echo "ERROR: Cannot check $DOMAIN"
        continue
    fi

    EXPIRY_EPOCH=$(date -d "$EXPIRY" +%s)
    NOW_EPOCH=$(date +%s)
    DAYS_LEFT=$(( (EXPIRY_EPOCH - NOW_EPOCH) / 86400 ))

    if [ $DAYS_LEFT -lt $ALERT_DAYS ]; then
        echo "WARNING: $DOMAIN expires in $DAYS_LEFT days ($EXPIRY)"
        echo "$DOMAIN SSL expires in $DAYS_LEFT days" | \
            mail -s "SSL Alert: $DOMAIN" "$ALERT_EMAIL" 2>/dev/null
    else
        echo "OK: $DOMAIN - $DAYS_LEFT days remaining"
    fi
done
```

### Let's Encrypt Avtomatik Setup

```bash
#!/bin/bash
# setup-ssl.sh - Laravel üçün SSL quraşdırma

DOMAIN=${1:?"Usage: $0 domain.com"}
EMAIL="admin@$DOMAIN"
WEBROOT="/var/www/laravel/public"

# Certbot quraşdır
sudo apt install -y certbot python3-certbot-nginx

# Sertifikat al
sudo certbot --nginx -d "$DOMAIN" -d "www.$DOMAIN" \
    --non-interactive --agree-tos --email "$EMAIL" \
    --redirect

# Yeniləmə hook
sudo tee /etc/letsencrypt/renewal-hooks/deploy/reload-nginx.sh > /dev/null <<'EOF'
#!/bin/bash
systemctl reload nginx
EOF
sudo chmod +x /etc/letsencrypt/renewal-hooks/deploy/reload-nginx.sh

# Test
sudo certbot renew --dry-run

echo "SSL configured for $DOMAIN"
echo "Test: https://www.ssllabs.com/ssltest/analyze.html?d=$DOMAIN"
```

## PHP/Laravel ilə İstifadə

### Laravel HTTPS Konfiqurasiyası

```php
<?php
// .env
APP_URL=https://example.com
FORCE_HTTPS=true

// app/Providers/AppServiceProvider.php
public function boot(): void
{
    if (config('app.env') === 'production') {
        URL::forceScheme('https');
    }
}

// bootstrap/app.php (Laravel 11)
->withMiddleware(function (Middleware $middleware) {
    // Load Balancer arxasında HTTPS detect etmək üçün
    $middleware->trustProxies(
        at: '*',
        headers: Request::HEADER_X_FORWARDED_FOR |
                 Request::HEADER_X_FORWARDED_PROTO
    );
})
```

### HTTPS Redirect Middleware

```php
<?php
// app/Http/Middleware/ForceHttps.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ForceHttps
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->secure() && app()->environment('production')) {
            return redirect()->secure($request->getRequestUri(), 301);
        }

        return $next($request);
    }
}
```

### Secure Cookie və Session

```php
<?php
// config/session.php
'secure' => env('SESSION_SECURE_COOKIE', true),  // HTTPS-only cookies
'same_site' => 'lax',                             // CSRF qoruması

// .env
SESSION_SECURE_COOKIE=true
```

## Interview Sualları

### S1: SSL və TLS arasında fərq nədir?
**C:** SSL (Secure Sockets Layer) Netscape tərəfindən yaradılıb (SSL 2.0, 3.0). TLS (Transport Layer Security) SSL-in təkmilləşdirilmiş davamçısıdır. SSL 3.0 artıq təhlükəsiz sayılmır (POODLE hücumu). Hazırda TLS 1.2 və TLS 1.3 istifadə olunur. TLS 1.3 daha sürətlidir (1-RTT handshake) və daha təhlükəsizdir. "SSL sertifikat" deyəndə əslində TLS sertifikat nəzərdə tutulur.

### S2: TLS Handshake prosesi necə işləyir?
**C:** TLS 1.2: 1) Client Hello - dəstəklənən cipher suite-ləri göndərir, 2) Server Hello - cipher seçir, sertifikat göndərir, 3) Client sertifikatı yoxlayır (CA zənciri, bitmə tarixi, domain), 4) Key exchange - pre-master secret ilə session key yaradılır, 5) Şifrələnmiş əlaqə başlayır. TLS 1.3-də bu 1 round-trip-ə endirilib. Symmetric key data transferi üçün, asymmetric key yalnız handshake üçün istifadə olunur.

### S3: mTLS nədir və nə vaxt istifadə olunur?
**C:** Mutual TLS-də həm server, həm client sertifikat göstərir və bir-birini yoxlayır. Normal TLS-dən fərqli olaraq client-in kimliyini təsdiqləyir. Microservice-lər arası əlaqədə (service mesh), B2B API-lərdə, IoT cihaz autentifikasiyasında istifadə olunur. Istio, Linkerd kimi service mesh-lər mTLS-i avtomatik idarə edir.

### S4: Let's Encrypt sertifikatları production üçün yetərlidirmi?
**C:** Bəli. Let's Encrypt DV sertifikatları şifrələmə baxımından ödənişli sertifikatlarla eynidir. 90 gün müddətlidir, certbot ilə avtomatik yenilənir. Wildcard sertifikat da dəstəkləyir (DNS challenge ilə). OV/EV lazımdırsa (bank, maliyyə) ödənişli CA lazımdır. Əksər web proyektlər və API-lər üçün Let's Encrypt kifayətdir.

### S5: HSTS nədir və niyə vacibdir?
**C:** HTTP Strict Transport Security brauzerə bu domain-ə yalnız HTTPS ilə qoşulmasını əmr edir. `Strict-Transport-Security: max-age=31536000; includeSubDomains; preload` header-i ilə aktivləşir. İlk HTTP request-i intercept etmə hücumunun qarşısını alır. preload flag ilə brauzerin daxili siyahısına əlavə etmək olar ki, heç vaxt HTTP istifadə olunmasın.

### S6: SSL sertifikat bitmə problemi necə qarşısı alınır?
**C:** 1) Certbot ilə avtomatik yeniləmə qur, 2) Monitoring script ilə bitmə tarixini yoxla (30 gün əvvəl alert), 3) certbot renew --dry-run ilə test et, 4) renewal hook ilə web server-i reload et, 5) Prometheus/Grafana ilə sertifikat bitmə metrikası izlə. Let's Encrypt sertifikatları 90 gün müddətlidir, certbot 30 gün qaldıqda avtomatik yeniləyir.

## Best Practices

1. **TLS 1.2+** istifadə edin - SSL və TLS 1.0/1.1 deaktiv edin
2. **Güclü cipher suite-lər** seçin - ECDHE key exchange, AES-GCM
3. **HSTS aktiv edin** - preload ilə birlikdə
4. **Sertifikat monitoring** qurun - Bitmə tarixini izləyin
5. **Avtomatik yeniləmə** konfiqurasiya edin - certbot renew
6. **HTTP -> HTTPS redirect** edin - 301 permanent redirect
7. **OCSP Stapling** aktiv edin - Sertifikat yoxlama sürətini artırır
8. **Private key-i qoruyun** - 600 permission, root-a məxsus
9. **SSL Labs test** keçirin - A+ reytinq hədəfləyin
10. **Certificate Transparency** izləyin - Sizin domain-ə yanlış sertifikat verilməsini izləyin
