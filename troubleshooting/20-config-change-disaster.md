# Config Change Disaster (Senior)

## Problem (nəyə baxırsan)
Kimsə production-da environment variable və ya config dəyərini dəyişdi. Dəyişiklik zərərsiz görünürdü. 5 dəqiqə sonra sayt yanır. Config dəyişiklikləri real incident-lərin təəccüblü dərəcədə böyük hissəsinə səbəb olur — çox vaxt kod dəyişikliklərindən də çox.

Klassik nümunələr:
- `DB_HOST` yanlış replica endpoint-inə yönəldi
- `CACHE_DRIVER` `redis`-dən `file`-a dəyişdi, disk aşırı yükləndi
- Prod-da `LOG_LEVEL=debug`, log həcmi partladı
- `SESSION_DRIVER` dəyişdi, hər kəs logout oldu
- Timeout dəyəri 0 (limitsiz) qoyuldu, PHP-FPM worker-ləri ilişdi
- `APP_DEBUG=true`, secret-li stack trace-lər istifadəçilərə sızdı

Simptomlar:
- Config push-dan dərhal sonra qəfil xəta spike-i
- Yaxınlarda kod deploy-u yoxdur
- Log-lar connection xətaları, timeout xətaları, və ya stack trace-lərlə doludur
- Dashboard-lar görünən səbəb olmadan qırmızıya çevrilir

## Sürətli triage (ilk 5 dəqiqə)

### İlk sual: config dəyişdimi?

```bash
# Source-controlled config
git log -p configs/ --since="2 hours ago"

# Kubernetes ConfigMap history (limited; depends on GitOps)
kubectl get configmap app-config -o yaml
kubectl describe configmap app-config

# Vault / Parameter Store
# Check audit log in provider console
aws ssm get-parameter-history --name /myapp/prod/DB_HOST
```

Dəyişiklik timestamp-ini incident başlama vaxtı ilə yoxla.

### Config-i rollback et

Git-əsaslı config varsa (GitOps), commit-i revert et:
```bash
git revert <config-change-sha>
git push origin main
# CD picks up, applies
```

Kubernetes ConfigMap istifadə edirsənsə:
```bash
kubectl rollout undo deployment/api
# undoes pod redeploy, but ConfigMap rollback separate
```

Host-da env var olsa:
```bash
# Restore from backup
cp /etc/myapp/env.backup /etc/myapp/env
systemctl restart myapp
```

## Diaqnoz

### Config dəyişiklikləri niyə belə təhlükəlidir

1. **Daha az review**: config dəyişiklikləri çox vaxt kod-a tətbiq olunan code review sərtliyini keçir
2. **Daha az test**: unit test-lərlə əhatə olunmur, nadir hallarda integration test
3. **Sürətli tətbiq**: runtime reload kod üçün yavaş CI/CD-yə qarşı ani ola bilər
4. **Təhlükəsiz sayılır**: "bu sadəcə rəqəmdir" / "sadəcə boolean flip edirəm"
5. **Kaskad effekt**: timeout dəyişikliyi minlərlə request-ə kaskad ola bilər
6. **Gizli bağlantılar**: config X implicit olaraq config Y tələb edir

### Ümumi təhlükəli config pattern-ləri

**Timeout = 0**
```
GUZZLE_TIMEOUT=0     # unlimited — workers hang forever
```

**Səhv pointer-lər**
```
DB_HOST=new-db-endpoint.old-region.aws  # typo in DNS
REDIS_HOST=10.0.0.5                     # swapped primary/replica
```

**Resurs limit sürprizləri**
```
MEMORY_LIMIT=128M   # lowered, jobs OOM
MAX_CHILDREN=5      # FPM saturated
```

**Debug açıq qaldı**
```
APP_DEBUG=true
LOG_LEVEL=debug
TELESCOPE_ENABLED=true
```

Hamısı production-da secret və ya böyük log həcmi göndərir.

**Env vasitəsilə feature flag**
```
NEW_CHECKOUT=true   # suddenly rolled out 100%, instead of gradual
```

Env-var əsaslı feature flag-lər əslində flag deyil — onlar deploy-dur. İncə idarəetmə yoxdur.

## Fix (bleeding-i dayandır)

### Dərhal

1. **Config-i revert et** — git revert, ConfigMap rollback, Vault rollback, və s.
2. **Təsirlənən xidmətləri restart et** ki, revert olunmuş config-i götürsünlər
3. **Revert-in təsir etdiyini yoxla** — `php artisan config:show` və ya cari config-i göstərən health endpoint-i yoxla

### Dərhal revert edə bilmirsənsə

- Qismən yüngülləşdir: xidmətləri scale et, circuit breaker əlavə et, asılı feature-ləri söndür
- Tam olaraq nəyin dəyişdiyini sənədləşdir; səbəbi təsdiqləmək üçün staging-də təkrarla

## Əsas səbəbin analizi

Suallar:
- Bu config dəyişikliyi niyə review-dan yan keçdi?
- Kim təsdiq etdi?
- Əvvəlcə təsir staging-də test olundumu?
- Config dəyişikliklərinə deploy kimi, yoxsa data düzəlişi kimi yanaşırıq?
- Config source control-dadırmı?

## Qarşısının alınması

### Bütün config-i source control-da saxla

Opsiya deyil. Hər config dəyəri — env var, feature flag, timeout, rate limit — bir git repo-da yaşayır.

```
configs/
├── base/
│   ├── common.yaml
│   └── feature-flags.yaml
├── production/
│   ├── env.yaml
│   └── overrides.yaml
└── staging/
    └── env.yaml
```

Dəyişikliklər PR review-dan keçir. Diff görünür. Rollback `git revert`-dir.

### Mərhələli rollout

Config dəyişiklikləri kod kimi rollout olunur:
1. Əvvəl staging
2. Canary prod (trafikin 1-5%-i)
3. Qismən prod (50%)
4. Tam prod

Alətlər: mühit başına Kubernetes namespace, rollout %-ə görə ConfigMap, feature flag xidməti.

### Davranış üçün env var-lar əvəzinə feature flag-lər

```
# Bad — whole-fleet immediate toggle
NEW_CHECKOUT_ENABLED=true

# Good — feature flag service
Feature::active('new_checkout')  # LaunchDarkly, Pennant, Unleash
```

Feature flag faiz rollout, istifadəçi-seqmenti hədəfləmə, ani off düyməsi verir.

### Revert mexanizmi mövcud olmalıdır

Config dəyişikliyini push etməzdən əvvəl cavab ver: "Bunu necə geri qaytaracağam?" Əgər cavab "xətti sil və redeploy et"-dirsə, redeploy-un işlədiyini təsdiqlə. Əgər cavab "əmin deyiləm"-dirsə, push etmə.

### Laravel config cache məlumatlılığı

`.env` və ya `config/*.php` dəyişdikdən sonra:

```bash
php artisan config:clear
php artisan config:cache
```

Əgər prod-da `config:cache`-i unutsan, dəyişikliklər ümumiyyətlə tətbiq olunmaya bilər. Cache-dən əvvəl `config:clear`-i unutsan, köhnə dəyərlər dondurulur.

### Config yüklənməsində validasiya

Startup-da validasiya et:

```php
// bootstrap/app.php or a ServiceProvider
if (config('app.env') === 'production') {
    abort_if(config('app.debug') === true, 500, 'APP_DEBUG cannot be true in production');
    abort_if(config('logging.default') === 'debug', 500, 'Debug logging in production');
    abort_if((int) config('queue.timeout') === 0, 500, 'Queue timeout cannot be 0');
}
```

App təhlükəli config ilə başlamağı rədd edir.

## PHP/Laravel xüsusi qeydlər

### Laravel config cache

```bash
# Always run after .env / config changes
php artisan config:cache

# Clear when rolling back
php artisan config:clear

# Inspect current value
php artisan tinker --execute='echo config("database.connections.mysql.host");'
```

Deploy script pattern-i:
```bash
#!/bin/bash
php artisan config:clear
# (apply new .env or configs)
php artisan config:cache
php artisan route:cache
systemctl reload php-fpm
```

### Environment vs config

Laravel `.env`-i YALNIZ config cache olunmadıqda oxuyur. `config:cache` aktivləşdirildikdə:
- `.env` cache vaxtında bir dəfə oxunur
- `.env`-ə dəyişikliklərin sonrakı `config:cache`-ə qədər HEÇ BİR təsiri olmur

Tələ: `.env`-i dəyişirsən, heç nə olmur, dəyişikliyin tətbiq olunmadığını düşünürsən. Redaktədən sonra həmişə cache et (və ya cache-i təmizlə).

### İnstansiyalar arasında config drift

Çox-instansiyalı prod: hər pod/VM-nin öz `.env`-i var. Əgər biri yeniləməni qaçırsa:
- O instansiya hələ də köhnə config ilə işləyir
- Trafik boyunca uyğunsuz davranış
- Çox çətin debug olunur

ConfigMap (Kubernetes), Parameter Store (AWS), Consul, Vault istifadə et — mərkəzləşdirilmiş truth mənbəyi. Hər instansiya eyni yerdən çəkir.

### Təhlükəli Laravel config dəyərləri

```
APP_DEBUG=true          # NEVER in prod — leaks stack traces
APP_ENV=local           # enables Telescope, Debugbar by default
LOG_LEVEL=debug         # volume explodes
SESSION_DRIVER=file     # doesn't work across multi-instance
CACHE_DRIVER=array      # no persistence, everyone gets cold cache
QUEUE_CONNECTION=sync   # jobs run synchronously, kills response times
TELESCOPE_ENABLED=true  # eats disk, exposes data
```

## Yadda saxlanmalı real komandalar

```bash
# Laravel config inspection
php artisan config:show
php artisan about                    # Laravel 9+ — system overview
php artisan env                      # shows APP_ENV

# Laravel cache commands
php artisan config:cache
php artisan config:clear
php artisan route:cache
php artisan route:clear

# Git config rollback
git log -p configs/
git revert <sha>

# Kubernetes
kubectl get configmap app-config -o yaml
kubectl edit configmap app-config
kubectl rollout restart deployment/api

# AWS SSM
aws ssm get-parameter --name /myapp/prod/DB_HOST
aws ssm get-parameter-history --name /myapp/prod/DB_HOST --max-results 10

# HashiCorp Vault
vault kv get secret/myapp/prod
vault kv list secret/myapp/
```

## Müsahibə bucağı

"Config dəyişikliyi prod-u düşürdü. Prosesi izah et."

Güclü cavab:
- "Əvvəl təsdiqlə: config dəyişikliyi timeline-ını incident başlanğıcı ilə yoxla. Uyğun gəlsə, güclü şübhə."
- "Revert et. Config-lər git-dədirsə, bu `git revert + redeploy`-dur. Secret store-dadırsa, UI və ya API vasitəsilə rollback."
- "Revert olunmuş config-i götürmək üçün təsirlənən xidmətləri restart et. Laravel-də `config:cache` çox vaxt qaçırılan addımdır."
- "Struktur fix-lər: bütün config source control-da, kod kimi review olunur. Davranış dəyişiklikləri üçün feature flag. Təhlükəli dəyərlər üçün startup-da validasiya."
- "Döyüş hekayəsi: səhvən prod-a `LOG_LEVEL=debug` göndərildi. Log-lar 500MB/gündən 200GB/günə qalxdı. Bir saatda diski doldurdu. Revert 2 dəqiqə çəkdi; dərs: prod-da startup-da `LOG_LEVEL != debug` validasiyası."

Bonus: "Config dəyişikliklərinə deploy kimi yanaşıram. Eyni review, eyni staging, eyni canary. 'Bu sadəcə config dəyişikliyidir' ifadəsi incident post-mortem-lərində qırmızı bayraqdır."
