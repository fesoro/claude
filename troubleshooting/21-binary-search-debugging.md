# Binary Search Debugging (Senior)

## Problem (nə görürsən)
Bug var. Harada olduğunu bilmirsən. Çox namizəd var: çox commit, çox müştəri, çox zaman pəncərəsi, çox feature flag. Hər birini bir-bir oxumaq O(n)-dir. Binary search səni O(log n)-də oraya çatdırır. Bu ən az qiymətləndirilən debug texnikalarından biridir.

Binary search-in uyğun olduğu simptomlar:
- "Dünən işləyirdi, bu gün sınıqdır" — dünəndən bəri çox commit
- "Bəzi istifadəçilər təsirlənir, hamısı yox" — çox müştəri
- "Bu həftə harada isə başladı" — çox deploy
- "Hansısa feature flag kombinasiyası altında baş verir" — çox flag state

## Sürətli triage (ilk 5 dəqiqə)

### Binary search fikir modeli

1. GOOD state təyin et (işlədiyi commit, vaxt, müştəri, config)
2. BAD state təyin et (uğursuz olduğu yer)
3. Orta nöqtəni seç
4. Test: good və ya bad?
5. Daralt: pisdirsə (köhnə-good) və (orta nöqtə) arasında yeni orta nöqtə, əks halda (orta nöqtə) və (köhnə-bad)
6. Tək səbəb tapılana qədər təkrarla

## Diaqnoz

### Git bisect — kod dəyişiklikləri üçün

Range-də olan bir commit nə isə sındırdıqda:

```bash
git bisect start
git bisect bad               # current HEAD is broken
git bisect good v2.4.0       # v2.4.0 was known good
# Git checks out a midpoint commit
# Test it manually or with a script
git bisect good    # or: git bisect bad
# Repeat until git prints the offending commit
git bisect reset
```

Test script ilə avtomatik bisect:

```bash
git bisect start HEAD v2.4.0
git bisect run ./test-is-broken.sh
# Exit code 0 = good, non-zero = bad
```

`test-is-broken.sh` nümunəsi:
```bash
#!/bin/bash
composer install --no-interaction
php artisan test --filter=OrderTest::test_broken_case
```

### Deployment bisect

Commit hash-in yoxdur, deploy ardıcıllığın var. Eyni məntiq:

- Deploy #142: məlum good
- Deploy #157: məlum bad
- Orta nöqtəni staging və ya canary-də deploy et (#150 və ya #149)
- Test
- Daralt

Əksər modern CI/CD (ArgoCD, Harness, Octopus, Laravel Envoyer) deploy tarixini saxlayır. Konkret birini yenidən deploy edə bilirsən.

### Feature flag bisect

8 feature flag aktivdir. Bir kombinasiya nə isə sındırıb. 2^8 = 256 kombinasiya test etmək əvəzinə:

1. Hər 8-ini söndür — sınıq? Xeyr → onlardan biri səbəbdir (və ya kombinasiya)
2. İlk 4-ü aktivləşdir — sınıq? Bəli → səbəb o yarıdadır
3. Həmin 4-ün ilk 2-ni aktivləşdir — sınıq? Xeyr → səbəb digər 2-dədir
4. Qalan 2-dən birini test et — tamam

LaunchDarkly və ya Unleash ilə flag-lərin toggle edilməsi saniyələrdir. Staging və ya az trafik canary environment-də et.

### Müştəri bir-bir izolyasiya

"Bəzi müştərilər sınıq, hamısı yox." 10,000 müştərin var, hər birini test edə bilməzsən.

1. Məlum-sınıq müştərini seç: unikal nə olduğunu tap
2. Məlum-işləyən müştərini seç: nə fərqləndiyini tap
3. Fərqləri siyahıla: plan (enterprise vs free), region, feature flag-lər, data forması (çox böyük? null sahə?)
4. Hipotez et: sınıq müştərilər arasında paylaşılan fərq

SQL kömək edir:

```sql
-- Customers with recent errors
SELECT customer_id, COUNT(*) as err_count
FROM error_logs
WHERE created_at > NOW() - INTERVAL 1 HOUR
GROUP BY customer_id
ORDER BY err_count DESC;

-- Find commonality
SELECT plan, region, COUNT(*)
FROM customers
WHERE id IN (...broken customer ids...)
GROUP BY plan, region;
```

Adətən pattern özünü göstərir: hamısı enterprise, və ya hamısı EU region-da, və ya hamısının > 10,000 sətir var.

### Zaman pəncərəsini daraltmaq

"Səhvlər dünən harada isə başladı." Zaman seriyasına bax:

```bash
# Error count per hour for last 24h
grep ERROR storage/logs/laravel.log \
  | awk '{print substr($2,1,2)}' \
  | sort | uniq -c
```

Səhvlərin olduğu ilk saatı tap. Sonra:

```bash
# Per minute within that hour
grep "2026-04-17 14:" storage/logs/laravel.log \
  | grep ERROR \
  | awk '{print substr($2,1,5)}' \
  | sort | uniq -c
```

Dəqiqəyə qədər get. Sonra çarpaz-referans et:
- Həmin dəqiqədə hansı deploy oldu?
- Hansı cron job işlədi?
- Hansı trafik pattern dəyişdi?
- Hansı xarici webhook işə düşdü?

## Fix (qanaxmanı dayandır)

Binary search düzəltmir — tapır. Tapılandan sonra:
- Pis commit → onu revert et və ya fix cherry-pick et
- Pis deploy → əvvəlkinə rollback
- Pis flag kombinasiyası → günahkar flag-i söndür
- Pis müştəri pattern → həmin seqment üçün feature flag söndür, data-nı düzəlt

## Əsas səbəbin analizi

Binary search özü root cause alətidir — NƏ tapmaq üçündür. NİYƏ sonradan gəlir:
- Niyə testlər tutmadı?
- Niyə CI tutmadı?
- Niyə canary tutmadı?
- Niyə feature flag kombinasiyası test olunmamışdı?

## Qarşısının alınması

- Kiçik commit-lər bisect-i sürətləndirir (N kiçik olanda N log N daha yaxşıdır)
- Təsviri commit mesajları şübhəli commit-i yoxlamağa kömək edir
- Feature flag-lər təkcə ayrıca yox, cüt-cüt test edilir
- Müştəri seqmentləri boyunca sinthetic monitoring
- Tam roll out-dan əvvəl tutulacaqları canary deploy

## PHP/Laravel üçün qeydlər

### Laravel migration bisect

Əgər migration şübhəlidirsə:

```bash
# List migrations
php artisan migrate:status

# Rollback step by step
php artisan migrate:rollback --step=1
# Test
php artisan migrate:rollback --step=1
# Test
```

Və ya hər migration-un nə işlədəcəyini görmək üçün `--pretend` istifadə et.

### Composer bisect

Dependency yeniləmə nə isə sındırıb? Lockfile-ları müqayisə et.

```bash
# Diff lockfile changes
git diff HEAD~5 -- composer.lock | grep '"version"'

# Pin suspected package
composer require vendor/package:1.2.3 --dry-run
```

### PHP version bisect

PHP 8.2 → 8.3-ə yüksəltdin və nə isə sındı:

```bash
# Run tests against both
phpbrew use 8.2 && vendor/bin/phpunit
phpbrew use 8.3 && vendor/bin/phpunit
```

Və ya Docker vasitəsilə:
```bash
docker run --rm -v $PWD:/app -w /app php:8.2-cli composer test
docker run --rm -v $PWD:/app -w /app php:8.3-cli composer test
```

## Yadda saxlanacaq komandalar

```bash
# Git bisect manual
git bisect start
git bisect bad
git bisect good v2.4.0
# test, mark good or bad
git bisect reset

# Git bisect automated
git bisect start HEAD v2.4.0
git bisect run ./check.sh

# List commits in a range
git log v2.4.0..HEAD --oneline

# Find deploy at a specific time (ArgoCD)
argocd app history my-app

# List failed jobs in time window
php artisan queue:failed | grep "2026-04-17"

# Customer count by plan for broken set
mysql -e "SELECT plan, COUNT(*) FROM customers WHERE id IN (...) GROUP BY plan"
```

## Interview sualı

"'Dünən işləyən' bug var. 50 commit edilib. Necə tapırsan?"

Güclü cavab:
- "Git bisect. Məlum-good commit ilə (dünənki tag) və bad (HEAD) ilə başlayıram."
- "Avtomatlaşdırıla bilməsi üçün repro script yazıram: good isə exit 0, bad isə non-zero."
- "`git bisect run ./check.sh` işlədirəm. 50 commit üçün ~6 addımda günahkar commit-i tapıram."
- "Əgər bug kod-lokal deyilsə — məs., feature flag-ə və ya müştəri data-sına asılıdırsa — eyni binary search məntiqini digər ölçülərə tətbiq edirəm."
- "Əsas qalib: log N N-dən çox sürətlidir. 1000 commit üçün ~10 test vs 1000-ə qədər."

Bonus: "Bisect-lə kiçik commit-lər saxlamağı və hər commit-də CI yaşıl saxlamağı öyrəndim. Əgər commit-lərimin yarısı sınıqdırsa, bisect çaşır və ya çox `git bisect skip` etməli olursan."
