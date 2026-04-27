# Geo-routing & Localization (Senior)

## Problem
- Global app: EU, US, Asia user-lər
- Lokal latency az olsun (CDN edge)
- Lokal regulation: GDPR (EU), CCPA (CA), SOX (US)
- Lokal dil: en, az, ru, tr
- Lokal currency, time format
- Data residency — EU user data EU-da qalmalıdır

---

## Həll: Multi-region + GeoIP routing

```
                  ┌─ Cloudflare Edge (auto geo-routed)
                  │  Auto: nearest PoP
                  │
EU user ──────────┤
                  └─→ EU region (Frankfurt)
                       ↓
                       MySQL (EU primary)
                       Redis (EU cache)

US user ──────────→ US region (Virginia)
                       ↓
                       MySQL (US primary, EU async replica for analytics)
```

---

## 1. Cloudflare geo-routing

```
DNS / Anycast:
  example.com → CF anycast IP
  EU user → Frankfurt PoP → eu-app.internal
  US user → Virginia PoP → us-app.internal

Cloudflare Workers (edge):
  request.cf.country → header əlavə et
  Header: X-User-Country = "AZ"
  Header: X-User-Region = "EU"
```

```js
// Cloudflare Worker
export default {
    async fetch(request, env) {
        const country = request.cf?.country || 'US';
        const region = mapToRegion(country);
        
        // Origin selection
        const origin = region === 'EU' 
            ? 'https://eu-app.internal' 
            : 'https://us-app.internal';
        
        const url = new URL(request.url);
        const newUrl = origin + url.pathname + url.search;
        
        const response = await fetch(newUrl, {
            method: request.method,
            headers: {
                ...request.headers,
                'X-User-Country': country,
                'X-User-Region': region,
            },
            body: request.body,
        });
        
        return response;
    }
};

function mapToRegion(country) {
    const eu = ['DE', 'FR', 'IT', 'ES', 'NL', 'AZ', /* ... */];
    return eu.includes(country) ? 'EU' : 'US';
}
```

---

## 2. PHP middleware (geo + locale detect)

```php
<?php
namespace App\Http\Middleware;

class GeoLocalizationMiddleware
{
    public function handle($request, Closure $next)
    {
        $country = $request->header('X-User-Country', 'US');
        $region = $request->header('X-User-Region', 'US');
        
        // Locale rules
        $locale = $this->detectLocale($request, $country);
        app()->setLocale($locale);
        
        // Currency
        $currency = $this->detectCurrency($country);
        session(['currency' => $currency]);
        
        // Timezone
        $tz = $this->detectTimezone($country);
        config(['app.timezone' => $tz]);
        date_default_timezone_set($tz);
        
        // Compliance flag
        $request->merge([
            'geo' => [
                'country' => $country,
                'region'  => $region,
                'gdpr'    => $this->isGdprRegion($country),
                'ccpa'    => $country === 'US' && $request->header('X-State') === 'CA',
            ],
        ]);
        
        return $next($request);
    }
    
    private function detectLocale($request, string $country): string
    {
        // Priority:
        //  1. Explicit user setting (cookie)
        //  2. Accept-Language header
        //  3. Country mapping
        
        if ($cookie = $request->cookie('locale')) {
            return in_array($cookie, ['en', 'az', 'ru', 'tr']) ? $cookie : 'en';
        }
        
        $accept = $request->header('Accept-Language', '');
        if (preg_match('/^([a-z]{2})/', $accept, $m)) {
            $lang = $m[1];
            if (in_array($lang, ['en', 'az', 'ru', 'tr'])) return $lang;
        }
        
        return match($country) {
            'AZ'             => 'az',
            'TR'             => 'tr',
            'RU', 'BY', 'KZ' => 'ru',
            default          => 'en',
        };
    }
    
    private function detectCurrency(string $country): string
    {
        return match($country) {
            'AZ'                          => 'AZN',
            'RU'                          => 'RUB',
            'TR'                          => 'TRY',
            'GB'                          => 'GBP',
            'JP'                          => 'JPY',
            'DE','FR','IT','ES','NL','AT' => 'EUR',
            default                       => 'USD',
        };
    }
    
    private function detectTimezone(string $country): string
    {
        return match($country) {
            'AZ' => 'Asia/Baku',
            'TR' => 'Europe/Istanbul',
            'RU' => 'Europe/Moscow',
            'JP' => 'Asia/Tokyo',
            'DE' => 'Europe/Berlin',
            default => 'UTC',
        };
    }
    
    private function isGdprRegion(string $country): bool
    {
        $eu = ['AT','BE','BG','HR','CY','CZ','DK','EE','FI','FR','DE',
               'GR','HU','IE','IT','LV','LT','LU','MT','NL','PL','PT',
               'RO','SK','SI','ES','SE'];
        return in_array($country, $eu) || in_array($country, ['NO','IS','LI','GB']);
    }
}
```

---

## 3. Translation system (Laravel Lang)

```php
<?php
// resources/lang/az/messages.php
return [
    'welcome'   => 'Xoş gəldiniz!',
    'cart'      => [
        'empty' => 'Səbətiniz boşdur',
        'total' => 'Cəmi: :amount',
    ],
];

// resources/lang/en/messages.php
return [
    'welcome'   => 'Welcome!',
    'cart'      => [
        'empty' => 'Your cart is empty',
        'total' => 'Total: :amount',
    ],
];

// İstifadə
__('messages.welcome');                   // current locale
__('messages.cart.total', ['amount' => '$50.00']);
trans_choice('messages.items', 5);         // pluralization
```

---

## 4. Data residency (GDPR)

```php
<?php
// EU user data EU DB-də qalmalıdır
class GeoAwareDatabaseManager
{
    public function userConnection(User $user): string
    {
        return $user->region === 'EU' ? 'mysql_eu' : 'mysql_us';
    }
}

// Model
class User extends Model
{
    protected $connection = null;   // dynamic
    
    public function getConnectionName()
    {
        // Region-a görə connection
        return $this->region === 'EU' ? 'mysql_eu' : 'mysql_us';
    }
}

// config/database.php
'connections' => [
    'mysql_us' => ['host' => env('DB_US_HOST'), /* ... */],
    'mysql_eu' => ['host' => env('DB_EU_HOST'), /* ... */],
],

// Cross-region read (analytics) — read-only replica
'mysql_eu_replica' => [
    'host' => env('DB_EU_REPLICA_HOST'),  // US-də EU read replica
    'read_only' => true,
],
```

---

## 5. GDPR consent banner

```php
<?php
class CookieConsentMiddleware
{
    public function handle($request, Closure $next)
    {
        $isGdpr = $request->geo['gdpr'] ?? false;
        
        if ($isGdpr && !$request->cookie('cookie_consent')) {
            // Analytics cookie-ləri qoyma!
            view()->share('show_cookie_banner', true);
            view()->share('disable_analytics', true);
        }
        
        return $next($request);
    }
}

// JavaScript: consent vermədən analytics yükləmə
@if(!$disable_analytics ?? false)
    <script src="/analytics.js"></script>
@endif

@if($show_cookie_banner ?? false)
    <x-cookie-banner />
@endif
```

---

## 6. Right to be forgotten (GDPR Article 17)

```php
<?php
class GdprService
{
    public function forgetUser(int $userId): void
    {
        DB::transaction(function () use ($userId) {
            $user = User::findOrFail($userId);
            
            // 1. PII anonymize (audit trail saxlamaq lazımdır)
            $user->update([
                'name'     => 'Deleted User',
                'email'    => "deleted-{$user->id}@deleted.invalid",
                'phone'    => null,
                'address'  => null,
                'avatar'   => null,
                'deleted_at' => now(),
                'deletion_reason' => 'GDPR_RTBF',
            ]);
            
            // 2. Sessions revoke
            DB::table('sessions')->where('user_id', $userId)->delete();
            DB::table('personal_access_tokens')
                ->where('tokenable_id', $userId)
                ->delete();
            
            // 3. Cache invalidate
            Cache::forget("user:$userId");
            
            // 4. Search index remove
            Elasticsearch::delete(['index' => 'users', 'id' => $userId]);
            
            // 5. Backup-larda... (separate process — backup retention 30 gün)
            
            // 6. Audit log
            AuditLog::create([
                'event'   => 'gdpr.user_forgotten',
                'user_id' => $userId,
                'actor_id'=> auth()->id(),
            ]);
        });
        
        // 7. CDC events — Kafka topic-də tombstone
        // Debezium DELETE event göndərir → consumer-lər invalidate edir
    }
}
```

---

## 7. Pitfalls

```
❌ User location ≠ user citizenship
   AZ-də olan UK vətəndaşı GDPR scope-undadır
   ✓ User-də əlavə "citizenship" və "data_residency" field

❌ VPN ilə geo-spoofing
   ✓ İlk login zamanı country lock + verify (SMS, email)

❌ Caching geo-aware deyil
   /products cached → US user EU price görür
   ✓ Vary: X-User-Country (CDN cache key-də country)

❌ Translation missing key fallback yox
   __('messages.unknown') → "messages.unknown" göstərir
   ✓ Fallback locale (en), missing key alert

❌ Cross-region join çox yavaş
   EU MySQL-dən US MySQL-yə JOIN — network 100ms+ latency
   ✓ Read replica + denormalization

❌ Time zone DB-də saxlamamaq
   user.created_at "2026-04-19 10:00" — hansı TZ?
   ✓ DB-də UTC saxla, display-də user TZ ilə convert
```

---

## 8. CDN strategy

```
Static (HTML/JS/CSS/images): edge cache, no origin hit
API public (catalog): edge cache 5 min
API private (user-specific): no cache, always origin

Geo-routed cache:
  /products?lang=az → cache key: products:lang=az
  /products?lang=en → ayrı entry

Origin shielding: yalnız 1 region origin-a çatır
  EU edge → EU origin (low latency)
  US edge → US origin
```
