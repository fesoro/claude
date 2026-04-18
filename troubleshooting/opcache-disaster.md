# OPcache Disaster

## Problem (nə görürsən)
OPcache yanlış getdi. Ya deploy-dan sonra köhnə kod verir, ya reset olundu və recompile edərkən CPU spike verdi, ya da `validate_timestamps=0` o mənaya gəldi ki, yeni kodun heç vaxt effekt etmədi. OPcache PHP performansı üçün vacibdir amma deploy fəlakətlərinin tez-tez səbəbidir.

Simptomlar:
- 20 dəqiqə əvvəl fix deploy etdin, bug hələ də təkrarlanır — köhnə kod verir
- OPcache reset-dən dərhal sonra CPU spike etdi, dəqiqələr içində normal oldu
- Bəzi pod-lar yeni davranış göstərir, bəziləri köhnə — uyğunsuz state
- `php -v` yeni versiya göstərir amma endpoint hələ də köhnə kod qaytarır
- Log-lar silinmiş function çağırışlarını göstərir

## Sürətli triage (ilk 5 dəqiqə)

### OPcache state-ini yoxla

Debug endpoint əlavə et (qorunmuş!):
```php
Route::get('/admin/opcache-status', function () {
    return response()->json(opcache_get_status(false));
})->middleware('admin');
```

Əsas sahələr:
- `cache_full`: true yeni kodun cache olunmayacağı deməkdir
- `memory_usage.used_memory`
- `num_cached_scripts`
- `opcache_statistics.oom_restarts`
- `opcache_statistics.hash_restarts`
- `opcache_statistics.manual_restarts`

### OPcache config-i al

```bash
php -i | grep -i opcache
```

Bu parametrlərə diqqət et:
- `opcache.enable=1` — aktiv olmalıdır
- `opcache.validate_timestamps=0` — prod-da ümumidir, deploy-lar OPcache-i reset etməlidir
- `opcache.revalidate_freq=2` — yalnız validate_timestamps=1 olanda aiddir
- `opcache.memory_consumption=256` — MB-da ölçü
- `opcache.max_accelerated_files=20000` — fayl sayı limiti
- `opcache.preload=/app/preload.php` — preload edilmiş kod

## Diaqnoz

### Niyə köhnə kod verilir?

**Hal A: validate_timestamps=0 + deploy-da reset yox**

Ən ümumi. Prod-da: performans üçün `opcache.validate_timestamps=0`. Deploy-dan sonra OPcache hələ də köhnə kompilyasiya olunmuş koda malikdir. Reset etməlisən.

**Hal B: Atomic deploy amma shared OPcache**

Deploy `/app` symlink-i `releases/v1` → `releases/v2`-yə dəyişir. Amma OPcache ESKİ release-in mütləq yolu ilə script-ləri cache-ləyib. Yeni symlink yeni fayllara işarə edir, amma OPcache köhnələri verir.

Fix: reset ilə invalidate et, və ya stabil yollarla deploy et.

**Hal C: Qismən invalidation**

`opcache_invalidate()` bəzi fayllar üçün çağırılıb amma hamısı yox. Qarışıq state.

### Niyə CPU spike etdi?

`opcache_reset()` hər şeyi təmizləyir. Növbəti 1000 request fayllarını recompile etməlidir. Orta ölçülü Laravel app üçün bu worker başına 5-30s yüksəlmiş CPU-dur.

Trafik zamanı reset etsən, qısa spike gözlə. Az trafik zamanı, görünməzdir.

### Niyə "cache_full"?

OPcache memory tükənib:
- `max_accelerated_files`-dən çox fayl
- Ümumi kompilyasiya ölçüsü > `memory_consumption`
- Deploy churn-u (hər deploy artırır, eviction olana qədər)

Dolu olanda hər request-də kompilyasiyaya fallback olur — fəlakətlidir.

## Fix (qanaxmanı dayandır)

### Variant 1: Endpoint vasitəsilə opcache_reset()

Əgər admin endpoint-in varsa:
```bash
curl -sf https://myapp.com/admin/opcache-reset -H "Authorization: ..."
```

Endpoint:
```php
Route::post('/admin/opcache-reset', function () {
    opcache_reset();
    return ['reset' => true, 'status' => opcache_get_status(false)];
})->middleware('admin');
```

Hər FPM worker-ə dəyməlidir (hərəsinin öz shared-memory seqmenti var, AMMA eyni FPM pool-da paylaşırlar — bir reset pool-u əhatə edir). Bir neçə pod üçün hər pod-a day.

### Variant 2: PHP-FPM-i reload et

```bash
# Graceful reload
systemctl reload php8.3-fpm

# Or SIGUSR2 (graceful, no dropped connections)
kill -USR2 $(pgrep -o "php-fpm: master")
```

Reload worker-ların bir-bir restart olunmasına və OPcache-i boşaltmasına səbəb olur.

### Variant 3: Pod-u restart et

Kubernetes-də:
```bash
kubectl rollout restart deployment/php-fpm -n production
```

Nüvə amma zəmanətlidir. Şübhə olanda istifadə et.

### Variant 4: OPcache-aware proseslə deploy et

Ən yaxşı təcrübə: deploy script-də yeni kod yerində olandan sonra:

```bash
# For each pod/host:
# 1. Write new code to new directory
# 2. Atomically symlink-swap OR use container rotation
# 3. Gracefully reload FPM:
kill -USR2 $(pgrep -o "php-fpm: master")
# 4. Health check before moving to next pod
```

## Əsas səbəbin analizi

Incident sonrası suallar:
- Deploy sistemi OPcache-i reset etdi?
- Staging-də end-to-end test edildi (timing də daxil)?
- Bəzi pod-lar reset etdi, bəziləri yox? Niyə?
- Bizim üçün `validate_timestamps=0` dəyəri var? (Əksər prod-da bəli.)

## Qarşısının alınması

- Deploy pipeline OPcache reset addımı DAXİL ETMƏLİDİR
- Health check gating ilə rolling deploy-lar
- OPcache hit rate, memory istifadəsi, restart sayısını monitor et
- `oom_restarts` > 0-da alert (cache dolu) — memory_consumption-u tənzimlə
- `num_cached_scripts` `max_accelerated_files`-a yaxınlaşanda alert
- Ən təmiz state üçün blue-green və ya container əsaslı deploy

## PHP/Laravel üçün qeydlər

### Laravel + OPcache check-listi

Deploy-dan sonra:
```bash
# Clear Laravel caches FIRST
php artisan config:clear
php artisan config:cache
php artisan route:clear
php artisan route:cache
php artisan view:cache
php artisan event:cache

# THEN reset OPcache
# (Or use container rotation, which resets OPcache by nature)
systemctl reload php-fpm
```

Niyə sıralama vacibdir: əgər OPcache reset-dən SONRA Laravel cache-lərini yenidən qurursansa, növbəti request-lər təzə cache-lər + təzə PHP kompilyasiya edir. Əvvəl etsən, Laravel köhnə OPcache-lənmiş class-larla cache qura bilər.

### `opcache.validate_timestamps=0`

```ini
; /etc/php/8.3/fpm/conf.d/10-opcache.ini
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0     ; PROD
opcache.save_comments=1           ; Laravel needs this for annotations
opcache.file_cache=/var/cache/opcache
opcache.file_cache_consistency_checks=0
```

Dev-də: `validate_timestamps=1`, `revalidate_freq=0`.

### Preloading (PHP 7.4+)

```ini
opcache.preload=/app/preload.php
opcache.preload_user=www-data
```

`preload.php`:
```php
opcache_compile_file('/app/vendor/autoload.php');
// Preload specific classes
foreach (glob('/app/app/Models/*.php') as $f) {
    opcache_compile_file($f);
}
```

**Tez-tez dəyişən user kodu preload ETMƏ.** Yalnız framework və nadir dəyişən class-ları preload et. Preload uğursuzluqları PHP-FPM-in başlamasının qarşısını ala bilər.

### JIT (PHP 8+) monitoringi

```ini
opcache.jit_buffer_size=100M
opcache.jit=tracing
```

`opcache_get_status()['jit']` vasitəsilə JIT effektivliyini monitor et.

JIT əsasən CPU-ağır PHP (number crunching) üçün faydalıdır. Tipik Laravel app-lər (DB/IO bound) maksimum 5-10% qazanc görür.

## Yadda saxlanacaq komandalar

```bash
# Check OPcache enabled
php -i | grep "opcache.enable =>"

# Full OPcache config
php -i | grep opcache

# Reset via CLI (per-process, doesn't affect FPM pool)
php -r "opcache_reset();"   # NOT sufficient for FPM

# FPM reset
kill -USR2 $(pgrep -o "php-fpm: master")
systemctl reload php8.3-fpm

# Kubernetes
kubectl rollout restart deployment/php-fpm

# Via HTTP (your admin endpoint)
curl -X POST https://myapp.com/admin/opcache-reset \
  -H "Authorization: Bearer $TOKEN"

# View OPcache status (requires opcache-gui or custom endpoint)
# Install https://github.com/amnuts/opcache-gui for visual
```

### Laravel deploy snippet

```bash
#!/bin/bash
# deploy.sh
set -e

composer install --no-dev --optimize-autoloader --no-interaction
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Reset OPcache (must be run on each server)
kill -USR2 $(pgrep -o "php-fpm: master") || true

# Restart Horizon
php artisan horizon:terminate
```

## Interview sualı

"Gördüyün ən böyük OPcache səhvi nədir?"

Güclü cavab:
- "Prod-da deploy OPcache-i reset etmədi. Sürət üçün `validate_timestamps=0` o deməkdir ki, yeni fayllar avtomatik aşkarlanmır. Hotfix göndərdik, tətbiq olunmadı, istifadəçilər bug-u görməyə davam etdi. 30 dəqiqə niyə bunun olduğunu düşündük, nə vaxta qədər kimsə `opcache_get_status()` işlətdi və köhnə timestamp-ları gördü."
- "Fix: deploy script-ə `systemctl reload php-fpm` əlavə etdim. OPcache-dən app versiyasını qaytaran endpoint-i vuran health check əlavə etdim, deploy sonrası yoxlamaq üçün."
- "Ümumi qayda: OPcache strategiyası deploy dizaynının hissəsidir, sonrakı fikir deyil. Ya pod başına FPM reload ilə atomic rolling deploy-lar, ya da hər yeni konteynerin təzə OPcache-i olduğu konteyner rotasiyası."
- "İkinci dərslər: `oom_restarts`-ı monitor et. OPcache memory dolarsa, cache-ləməyi dayandırır və app-in sürünür."

Bonus: "Qoşulduğum bir komanda bütün domen class-larını yükləmək üçün `opcache.preload` təyin etmişdi. O class-lardan birində pis merge-dən sonra sintaksis səhvi var idi. PHP-FPM başlamaqdan imtina etdi. Preload xəttini silənə qədər 10 dəqiqə prod-u düşürdü."
