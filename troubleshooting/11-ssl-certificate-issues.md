# SSL Certificate Issues (Middle)

## Problem (nə görürsən)

SSL sertifikatı ilə bağlı problem. Ya sertifikat expire olub, ya renewal işləməyib, ya da yanlış domain üçün sertifikat qurulub. Browser "Not Secure" yazır, curl `SSL certificate problem` qaytarır, istifadəçilərin bir qismi app-a qoşula bilmir.

Simptomlar:
- Browser: "Your connection is not private" / ERR_CERT_DATE_INVALID
- curl: `SSL certificate problem: certificate has expired`
- API client-lər `SSL handshake failed` qaytarır
- Bəzi subdomain-lər işləyir, bəziləri yox (wildcard vs spesifik sertifikat)
- Let's Encrypt renewal 90 gün doldu amma avtomatik yenilənmədi
- Monitoring alert: "Certificate expires in X days"

## Sürətli triage (ilk 5 dəqiqə)

### Sertifikatın vəziyyətini yoxla

```bash
# Expire tarixi
echo | openssl s_client -servername yourdomain.com -connect yourdomain.com:443 2>/dev/null \
  | openssl x509 -noout -dates

# Tam sertifikat məlumatı
echo | openssl s_client -servername yourdomain.com -connect yourdomain.com:443 2>/dev/null \
  | openssl x509 -noout -text | grep -A2 "Subject\|Issuer\|Not Before\|Not After"

# SAN (Subject Alternative Names) - hansı domain-lər covered?
echo | openssl s_client -servername yourdomain.com -connect yourdomain.com:443 2>/dev/null \
  | openssl x509 -noout -ext subjectAltName
```

### Let's Encrypt statusu yoxla

```bash
# Certbot
certbot certificates

# Certbot timer (systemd)
systemctl status certbot.timer
journalctl -u certbot -n 50

# Acme.sh (əgər istifadə edirsənsə)
~/.acme.sh/acme.sh --list
```

### Nginx/Apache sertifikat faylını yoxla

```bash
# Nginx config-dən sertifikat faylını tap
nginx -T | grep ssl_certificate

# Sertifikat faylı expire tarixi
openssl x509 -noout -dates -in /etc/letsencrypt/live/yourdomain.com/fullchain.pem

# Nginx-in hansı sertifikatı HAZIRDA istifadə etdiyini yoxla (live)
echo | openssl s_client -connect yourdomain.com:443 2>/dev/null | openssl x509 -noout -dates
```

## Diaqnoz

### Hal A: Sertifikat expire olub

```bash
notAfter=Oct 15 12:00:00 2025 GMT   # keçmişdə → expired
```

Fix: dərhal renewal.

### Hal B: Renewal işləməyib

Ən geniş yayılmış səbəblər:

**Port 80 bağlıdır** — Let's Encrypt HTTP-01 challenge üçün port 80-ə ehtiyac duyur:
```bash
# Challenge işləyirmi?
curl -I http://yourdomain.com/.well-known/acme-challenge/test

# 80-ci porta firewall
ufw status
iptables -L INPUT -n | grep 80
```

**DNS dəyişib** — domain artıq bu server-ə işarə etmir:
```bash
dig yourdomain.com +short
curl -I http://yourdomain.com
```

**Certbot cron/timer işləməyib:**
```bash
journalctl -u certbot --since "30 days ago" | tail -50
# "Congratulations" yoxsa → renewal uğursuz
```

**Disk dolu** — sertifikat renewal yaza bilmir:
```bash
df -h /etc/letsencrypt
```

### Hal C: Yanlış sertifikat

```bash
# Sertifikat hansı domain üçündür?
echo | openssl s_client -connect yourdomain.com:443 2>/dev/null \
  | openssl x509 -noout -subject

# Wildcard coverage
# *.example.com → api.example.com ✓, deep.api.example.com ✗
```

## Fix (qanaxmanı dayandır)

### Variant 1: Manual renewal (Let's Encrypt)

```bash
# Test əvvəl (dry-run)
certbot renew --dry-run

# Birbaşa renew
certbot renew --force-renewal

# Spesifik domain
certbot renew --cert-name yourdomain.com --force-renewal

# Nginx yenilə
nginx -t && systemctl reload nginx
```

### Variant 2: Wildcard sertifikat yenilə (DNS-01 challenge)

HTTP-01 işləmirsə (port 80 yoxdur, ya da load balancer arxasındasan):

```bash
# DNS-01 challenge ilə manual
certbot certonly --manual --preferred-challenges dns \
  -d "*.yourdomain.com" -d "yourdomain.com"

# Cloudflare plugin (avtomatik)
certbot certonly --dns-cloudflare \
  --dns-cloudflare-credentials ~/.secrets/cloudflare.ini \
  -d "*.yourdomain.com"
```

### Variant 3: Müvəqqəti self-signed (son çarə, istifadəçilər warning görür)

```bash
# Yalnız çıxış yolu yoxdursa
openssl req -x509 -nodes -days 7 -newkey rsa:2048 \
  -keyout /etc/ssl/temp.key \
  -out /etc/ssl/temp.crt \
  -subj "/CN=yourdomain.com"

# Nginx config-i yenilə
systemctl reload nginx
```

**Bu yalnız içəridə ya da müvəqqəti olaraq istifadə et. İstifadəçilərə warning çıxacaq.**

### Nginx config-i yoxla

```nginx
server {
    listen 443 ssl;
    server_name yourdomain.com;

    ssl_certificate     /etc/letsencrypt/live/yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/yourdomain.com/privkey.pem;

    # Modern TLS
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
}

# HTTP → HTTPS redirect
server {
    listen 80;
    server_name yourdomain.com;
    return 301 https://$host$request_uri;
}
```

```bash
nginx -t    # syntax check
systemctl reload nginx
```

## Əsas səbəbin analizi

- Certbot timer/cron nə vaxtdan çalışmırdı?
- 30 gün əvvəl expire xəbərdarlığı email-i alıbdınızmı? (Let's Encrypt göndərir)
- Renewal uğursuz olduqda alert var idi?
- Port 80-u bağlayan bir dəyişiklik oldumu (firewall, security group)?

## Qarşısının alınması

```bash
# Monitoring: sertifikat neçə gün qalıb?
echo | openssl s_client -servername yourdomain.com -connect yourdomain.com:443 2>/dev/null \
  | openssl x509 -noout -checkend $((30*86400)) && echo "OK" || echo "EXPIRES SOON"

# Prometheus: ssl_certificate_expiry_seconds metric (blackbox exporter)
# Alert: cert < 14 gün qalıb → PagerDuty

# Certbot dry-run hər həftə cron
0 2 * * 1 certbot renew --dry-run >> /var/log/certbot-dryrun.log 2>&1
```

Laravel-də internal API-lar üçün sertifikat yoxlama:

```php
// config/services.php
'stripe' => [
    'verify_ssl' => env('APP_ENV') !== 'local',
],

// Heç vaxt istehsalatda verify_ssl=false etmə
Http::withOptions(['verify' => true])->post('https://api.stripe.com/...');
```

## Yadda saxlanacaq komandalar

```bash
# Expire tarixi
openssl s_client -connect domain.com:443 2>/dev/null | openssl x509 -noout -dates

# Bütün sertifikatları listele
certbot certificates

# Force renewal
certbot renew --force-renewal && nginx -t && systemctl reload nginx

# Renewal dry-run test
certbot renew --dry-run

# Sertifikat hansı domain-ləri cover edir?
openssl s_client -connect domain.com:443 2>/dev/null | openssl x509 -noout -ext subjectAltName

# 30 gün içərisində expire olacaqmı?
openssl s_client -connect domain.com:443 2>/dev/null \
  | openssl x509 -noout -checkend 2592000 || echo "EXPIRES IN <30 DAYS"
```

## Interview sualı

"Production SSL sertifikatı expire olsa necə davranırsan?"

Güclü cavab:
- "Əvvəlcə `openssl s_client` ilə expire tarixini təsdiqləyim. Sonra `certbot certificates` ilə renewal vəziyyətini yoxlayım."
- "Let's Encrypt renewal uğursuz olduqda ən çox görmüş olduğum səbəb: ya port 80 firewall dəyişikliyi ilə bağlanıb, ya da DNS dəyişib. `certbot renew --dry-run` ilə diaqnozu reproduc edirəm."
- "Əgər renewal dərhal işləmirsə, müvəqqəti manual renewal eləyib site-ı düzəldirəm, sonra root cause-ı araşdırırım."
- "Post-incident: 14 gün qalmış alert qururam, həftəlik dry-run cron əlavə edirəm."
