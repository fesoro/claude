# Third-Party Service Failure (Senior)

## Problem (nə görürsən)

Tətbiqin istifadə etdiyi xarici servis (Stripe, AWS S3, SendGrid, Twilio, Mailgun, Google Maps, Auth0 və s.) düşüb ya da cavab vermir. Bu sənin kodun deyil amma istifadəçilər sənin app-ı qınadır. Bağımlılıq sənin öhdəliyindir.

Simptomlar:
- Ödəniş axını dayandı: `STRIPE_API_ERROR: The Stripe API is currently unavailable`
- Email göndərilmir: `SendGrid: 503 Service Unavailable`
- File upload uğursuz: `S3: RequestTimeout / ConnectionError`
- Auth axını qırılıb: OAuth provider (Google/GitHub) cavab vermir
- Third-party widget-lər (chat, analytics) bloklanır — bəzən səhifəni freeze edir
- Log-larda `cURL error 28: Operation timed out` seli

## Sürətli triage (ilk 5 dəqiqə)

### Problem kimin tərəfindədir?

```bash
# Sən vs onlar — eyni sorğunu manual yoxla
curl -I https://api.stripe.com/v1
curl -I https://api.sendgrid.com

# Status koduna bax: 5xx onların problemidir, timeout da onlardır
```

```bash
# Status page-ləri yoxla:
# https://status.stripe.com
# https://status.sendgrid.com
# https://health.aws.amazon.com
# https://www.cloudflarestatus.com

# DNS-dən başqa problem varmı?
dig api.stripe.com
curl -v --max-time 5 https://api.stripe.com/v1 2>&1 | head -30
```

### Laravel log-larını yoxla

```bash
tail -f storage/logs/laravel.log | grep -i "stripe\|sendgrid\|s3\|exception"

# Son 30 dəqiqədə neçə uğursuz cəhd?
grep "cURL error\|Connection refused\|timed out" storage/logs/laravel.log \
  | grep "$(date '+%Y-%m-%d')" | wc -l
```

## Diaqnoz

### Hal A: Servis tamamilə düşüb (onların problemidir)

Status page `Incident` göstərir. Sən heç nə edə bilməzsən — amma tətbiqin necə uğursuz olduğunu idarə edə bilərsən.

**Yanlış davranış:** Retry fırtınası
```
Stripe düşdü → hər request 30 saniyə timeout gözləyir → worker-lar bloklanır
→ queue dolur → yeni request-lər işlənmir → app "donur"
```

**Düzgün davranış:** Tez uğursuz ol, queue-a at, circuit open.

### Hal B: Qismən problem (timeout ya da qismən 5xx)

```bash
# Response time artıb?
time curl -sf https://api.stripe.com/v1 -u $STRIPE_KEY:
# Normal: <300ms. Problem: >2s

# Latency artımı → circuit breaker açılmalıdır
```

### Hal C: Sənin konfiqurasiyan səhvdir

```bash
# API key expire olub?
# Rate limit aşılıb?
grep "401\|403\|429" storage/logs/laravel.log | tail -20

# Env var dəyişib?
php artisan tinker --execute="echo config('services.stripe.secret')[0..4];"
```

## Fix (qanaxmanı dayandır)

### Anlıq: İstifadəçiyə aydın mesaj ver

```php
// AppServiceProvider ya da Middleware
try {
    $charge = $stripe->charges->create([...]);
} catch (\Stripe\Exception\ApiConnectionException $e) {
    // Stripe düşüb — istifadəçini saxla, sil
    return response()->json([
        'error' => 'Ödəniş sistemi müvəqqəti əlçatmazdır. Zəhmət olmasa bir az sonra yenidən cəhd edin.',
        'retry_after' => 300,
    ], 503);
}
```

### Queue-a al (ödəniş deyilsə)

```php
// Email göndərmə, webhook, notification — queue-a al
SendEmailJob::dispatch($mailable)->delay(now()->addMinutes(5));

// Məsələn Stripe düşdüsə ödənişi queue-a almaq olmaz (idem­potency key lazım)
// Amma invoice email, receipt → queue-a al
```

### Circuit Breaker pattern

```php
// Redis ilə sadə circuit breaker
class StripeCircuitBreaker
{
    private const FAILURE_THRESHOLD = 5;
    private const RESET_TIMEOUT = 60; // seconds

    public static function isOpen(): bool
    {
        $failures = Cache::get('stripe_failures', 0);
        return $failures >= self::FAILURE_THRESHOLD;
    }

    public static function recordFailure(): void
    {
        Cache::increment('stripe_failures');
        Cache::put('stripe_open_until', now()->addSeconds(self::RESET_TIMEOUT));
    }

    public static function recordSuccess(): void
    {
        Cache::forget('stripe_failures');
    }
}

// İstifadəsi
if (StripeCircuitBreaker::isOpen()) {
    throw new ServiceUnavailableException('Payment service temporarily unavailable');
}

try {
    $result = $stripe->charges->create([...]);
    StripeCircuitBreaker::recordSuccess();
} catch (\Stripe\Exception\ApiConnectionException $e) {
    StripeCircuitBreaker::recordFailure();
    throw $e;
}
```

**Hazır kitabxanalar:** `spatie/laravel-activitylog` deyil, `guzzlehttp/guzzle` retry middleware, ya da `resilience4php/resilience4php`.

### S3 düşübsə: fallback storage

```php
// config/filesystems.php
'disks' => [
    's3' => [...],
    'local_fallback' => [
        'driver' => 'local',
        'root' => storage_path('app/s3-fallback'),
    ],
],

// Usage
try {
    Storage::disk('s3')->put($path, $content);
} catch (\Exception $e) {
    Log::error('S3 unavailable, falling back to local', ['error' => $e->getMessage()]);
    Storage::disk('local_fallback')->put($path, $content);
    // Sonra S3 düzələndə sync etmək üçün job qoy
    SyncToS3Job::dispatch($path)->delay(now()->addMinutes(10));
}
```

### Feature flag ilə deactivate et

```php
// Problematik feature-ı tamamilə söndür
if (! Feature::active('stripe_payments')) {
    return response()->json(['error' => 'Ödənişlər müvəqqəti dayandırılıb'], 503);
}
```

## Kommunikasiya

**Status page yeniləməsi (hər 15 dəqiqə):**
> "Stripe API ilə bağlı problem aşkarlandı. Ödəniş prosesləri təsirlənib. Stripe tərəfindən iş aparılır. Növbəti yeniləmə 15 dəqiqə içərisində."

**Slack/Teams:**
```
🔴 [INCIDENT] Stripe API degraded
Başlama: 14:32 UTC
Təsir: Ödəniş endpoint-ləri — yeni charge-lar fail edir
Status: https://status.stripe.com — "Degraded Performance"
Mitigation: Ödəniş forması 503 qaytarır, istifadəçi mesajlanır
Növbəti update: 14:47
```

## Əsas səbəbin analizi

- Timeout-lar nə vaxtdan başladı? Stripe status page incident-i nə zaman elan etdi?
- Neçə request uğursuz oldu? Neçə istifadəçi təsirləndi?
- Circuit breaker var idi? İşlədimi?
- Timeout threshold nə idi? Worker-lar bloklandımı?
- Stripe SLA: [99.99% uptime](https://stripe.com/docs/api) — bu incident SLA-nı pozdu?

## Qarşısının alınması

```php
// Guzzle HTTP timeout — heç vaxt default (sonsuz) saxlama
Http::timeout(5)->retry(2, 500)->post('https://api.stripe.com/...');

// Laravel HTTP client default timeout
// config/app.php or ServiceProvider
Http::globalOptions(['timeout' => 10]);
```

```bash
# Monitoring: third-party endpoint health
# Blackbox exporter (Prometheus) ilə xarici URL-lərə probe
# Alert: response_time > 2s ya da status != 200
```

**Defensive coding checklist:**
- [ ] Hər third-party çağırış timeout ilə
- [ ] Retry exponential backoff ilə (Guzzle retry middleware)
- [ ] Circuit breaker (Redis sayacı yetər ilk MVP üçün)
- [ ] Uğursuzluq case-ləri üçün user-friendly error mesajları
- [ ] Kritik olmayan feature-lər üçün graceful degradation
- [ ] Status page URL-lərini runbook-da saxla

## Yadda saxlanacaq komandalar

```bash
# Status page-ləri
open https://status.stripe.com
open https://status.sendgrid.com
open https://health.aws.amazon.com
open https://www.cloudflarestatus.com

# API əlçatanlığını yoxla
curl -I --max-time 5 https://api.stripe.com/v1
time curl -sf https://api.sendgrid.com

# Son saatda neçə third-party xətası?
grep "cURL error\|timed out\|ConnectionError" storage/logs/laravel.log \
  | grep "$(date '+%Y-%m-%d %H')" | wc -l

# Circuit breaker state (Redis)
redis-cli GET stripe_failures
redis-cli GET stripe_open_until
```

## Interview sualı

"Tətbiqiniz Stripe-dan asılıdır. Stripe 2 saatlıq outage keçirdi — nə oldu?"

Güclü cavab:
- "Əgər circuit breaker yox idisə: hər ödəniş sorğusu 30 saniyə timeout gözlədi → worker-lar bloklandı → bütün queue dayandı. Bunun qarşısını almaq üçün Guzzle timeout (5s) + circuit breaker (5 uğursuzluqdan sonra 60s açıq) qurmuşdum."
- "Anlıq fix: ödəniş endpoint-i `503 + retry_after header` qaytardı. Status page-i `investigating` etdim."
- "Root cause: xarici servis dependency — nəzarətimizdən kənardır. Amma tətbiqin necə uğursuz olduğu nəzarətimizdədir."
- "Post-incident: bütün third-party call-lar üçün timeout + circuit breaker pattern-i standart etdik."
