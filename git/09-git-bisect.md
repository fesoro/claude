# Git Bisect

## Nədir? (What is it?)

Git bisect, **binary search** (ikili axtarış) alqoritmi istifadə edərək bug-ı təqdim edən commit-i tapmağa kömək edən əmrdir. Yüzlərlə commit arasından problemi yaradan dəqiq commit-i tapması üçün yalnız `log2(n)` addım lazımdır.

```
100 commit arasından bug-ı tapmaq:
  Manual yoxlama:  100 commit yoxlamaq lazım ola bilər
  Binary search:   log2(100) ≈ 7 addım kifayətdir!

1000 commit:
  Manual:  1000 yoxlama
  Bisect:  log2(1000) ≈ 10 addım!
```

### Binary Search Prinsipi

```
Commit tarixçəsi (? = yoxlanılmamış):

A ? ? ? ? ? ? ? ? ? ? ? ? ? ? B
↑                               ↑
good (bug yoxdur)              bad (bug var)

Addım 1: Ortanı yoxla
A ? ? ? ? ? ? M ? ? ? ? ? ? ? B
                ↑
              good → bug sağ tərəfdədir

Addım 2: Sağ tərəfin ortasını yoxla
              M ? ? ? N ? ? ? B
                        ↑
                      bad → bug sol tərəfdədir

Addım 3: Daraldılmış aralıq
              M ? P ? N
                  ↑
                bad → P bu commit-dir!

Nəticə: 15 commit-dən 3 addımda tapdıq!
```

## Əsas Əmrlər (Key Commands)

### Manual Bisect

```bash
# 1. Bisect-i başlat
git bisect start

# 2. Cari commit-i "bad" kimi qeyd et (bug var)
git bisect bad

# 3. İşləyən commit-i "good" kimi qeyd et
git bisect good abc1234
# və ya
git bisect good v1.0  # Tag istifadə edə bilərsiniz

# 4. Git ortadakı commit-ə checkout edir
# Test edin, sonra nəticəni bildirin:
git bisect good   # Bu commit-də bug yoxdur
# və ya
git bisect bad    # Bu commit-də bug var

# 5. Git növbəti commit-ə keçir, təkrarlayın...

# 6. Bug tapıldıqda Git commit-i göstərir
# Bisect-i bitirin
git bisect reset
```

### Avtomatik Bisect (Script ilə)

```bash
# Test script-i ilə avtomatik bisect
git bisect start
git bisect bad HEAD
git bisect good v1.0

# Script: exit 0 = good, exit 1 = bad
git bisect run ./test-script.sh

# PHP/Laravel nümunəsi:
git bisect run php artisan test --filter=PaymentTest

# Bitirdikdən sonra
git bisect reset
```

### Əlavə Əmrlər

```bash
# Bisect logunu göstər
git bisect log

# Bisect vizualizasiyası
git bisect visualize
# və ya
git bisect view

# Commit-i atla (test edə bilmirsinizsə)
git bisect skip

# Bir neçə commit-i atla
git bisect skip abc1234 def5678

# Log-dan bisect-i yenidən oyna
git bisect replay bisect-log.txt

# Bisect log-u fayla yaz
git bisect log > bisect-log.txt

# Yeni terminlər (good/bad əvəzinə)
git bisect start --term-old=fast --term-new=slow
git bisect slow   # Bu commit-də yavaşdır
git bisect fast   # Bu commit-də sürətlidir
```

## Praktiki Nümunələr (Practical Examples)

### Nümunə 1: Manual Bisect ilə Bug Tapmaq

```bash
# Ssenari: Login səhifəsi 500 error qaytarır
# Son işləyən versiya: v2.3 tag-ı
# Bug olan versiya: HEAD (cari)

$ git bisect start
$ git bisect bad HEAD
$ git bisect good v2.3
# Bisecting: 25 revisions left to test after this (roughly 5 steps)
# [abc1234] feat: add social login

# Login səhifəsini test edin
$ php artisan serve &
$ curl -s -o /dev/null -w "%{http_code}" http://localhost:8000/login
# 200 (işləyir)

$ git bisect good
# Bisecting: 12 revisions left to test after this (roughly 4 steps)
# [def5678] refactor: update middleware

$ curl -s -o /dev/null -w "%{http_code}" http://localhost:8000/login
# 500 (bug var!)

$ git bisect bad
# Bisecting: 6 revisions left to test after this (roughly 3 steps)
# ... davam edir ...

# Nəticə:
# abc1234def5678 is the first bad commit
# Author: Developer X
# Date: Mon Apr 10 14:30:00 2026
# refactor: change auth middleware logic

$ git bisect reset
# HEAD əvvəlki branch-ə qayıdır
```

### Nümunə 2: Avtomatik Bisect (PHPUnit Test ilə)

```bash
# Test ilə avtomatik bug axtarışı
git bisect start
git bisect bad HEAD
git bisect good v2.0

# PHPUnit test-i ilə avtomatik yoxla
git bisect run php artisan test --filter=LoginTest

# Nəticə avtomatik tapılır:
# abc1234 is the first bad commit
# ...
# bisect run success

git bisect reset
```

### Nümunə 3: Custom Script ilə Bisect

```bash
# test-bug.sh script-i yaradın:
cat > /tmp/test-bug.sh << 'EOF'
#!/bin/bash

# Dependencies yüklə
composer install --quiet 2>/dev/null

# Migration işlət
php artisan migrate:fresh --seed --quiet 2>/dev/null

# Spesifik testi işlət
php artisan test --filter=PaymentGatewayTest 2>/dev/null
exit $?
EOF

chmod +x /tmp/test-bug.sh

# Avtomatik bisect
git bisect start HEAD v1.5
git bisect run /tmp/test-bug.sh
git bisect reset
```

### Nümunə 4: Performance Regression Tapmaq

```bash
# Ssenari: API cavab müddəti artıb (əvvəl <200ms, indi >500ms)

cat > /tmp/perf-test.sh << 'EOF'
#!/bin/bash

composer install --quiet 2>/dev/null
php artisan migrate:fresh --seed --quiet 2>/dev/null
php artisan serve --port=8099 &
SERVER_PID=$!
sleep 2

# Cavab müddətini ölç
RESPONSE_TIME=$(curl -s -o /dev/null -w "%{time_total}" http://localhost:8099/api/products)

kill $SERVER_PID 2>/dev/null

# 0.3 saniyədən çox olarsa = bad
if (( $(echo "$RESPONSE_TIME > 0.3" | bc -l) )); then
    exit 1  # bad
else
    exit 0  # good
fi
EOF

chmod +x /tmp/perf-test.sh

# Custom terminlərlə bisect
git bisect start --term-old=fast --term-new=slow
git bisect slow HEAD
git bisect fast v2.0
git bisect run /tmp/perf-test.sh
git bisect reset
```

## Vizual İzah (Visual Explanation)

### Bisect Addımları

```
Commit tarixçəsi (32 commit):

v2.0                                                HEAD
 ↓                                                    ↓
 G───G───G───G───G───G───G───G───?───?───?───?───?───B
 1   2   3   4   5   6   7   8   9  10  11  12  13  14

G = good (bilinir), B = bad (bilinir), ? = bilinmir

Addım 1: git bisect start; git bisect bad; git bisect good v2.0
Git orta commit-ə (8) keçir:
 G───G───G───G───G───G───G───[8]──?───?───?───?───?───B
                              ↑
                           Test et!
Nəticə: good

Addım 2:
 ────────────────────────────G───?───?───[11]──?───?───B
                                          ↑
                                       Test et!
Nəticə: bad

Addım 3:
 ──────────────────────────────G───?───[10]──B
                                        ↑
                                     Test et!
Nəticə: bad

Addım 4:
 ───────────────────────────────G──[9]──B
                                    ↑
                                 Test et!
Nəticə: good → Commit 10 ilk bad commit-dir!
```

### Bisect İş Axını

```
┌────────────────────┐
│ git bisect start   │
└────────┬───────────┘
         ▼
┌────────────────────┐
│ git bisect bad     │─── Cari bug-lı commit
└────────┬───────────┘
         ▼
┌────────────────────┐
│ git bisect good    │─── Son işləyən commit/tag
└────────┬───────────┘
         ▼
   ┌─────────────┐
   │ Git ortadakı│
   │ commit-ə    │◄──────────────────┐
   │ checkout edir│                   │
   └──────┬──────┘                   │
          ▼                          │
   ┌─────────────┐                   │
   │ Test et     │                   │
   └──────┬──────┘                   │
          ▼                          │
   ┌─────────────┐     ┌─────────┐  │
   │ Bug var?    │─Bəli─│ bisect  │──┘
   │             │      │ bad     │
   └──────┬──────┘     └─────────┘
          │ Xeyr        ┌─────────┐
          └─────────────│ bisect  │──┘
                        │ good    │
                        └─────────┘

   ┌─────────────────────────────┐
   │ Aralıq 1 commit qaldıqda:  │
   │ "abc1234 is the first bad   │
   │  commit"                    │
   └──────────┬──────────────────┘
              ▼
   ┌─────────────────┐
   │ git bisect reset│
   └─────────────────┘
```

## PHP/Laravel Layihələrdə İstifadə

### Ssenari 1: Broken Migration Tapmaq

```bash
# Migration error başlayıb, hansı commit-də olduğunu tapmaq lazımdır

cat > /tmp/test-migration.sh << 'EOF'
#!/bin/bash
composer install --quiet 2>/dev/null
php artisan migrate:fresh --force 2>&1
exit $?
EOF

chmod +x /tmp/test-migration.sh

git bisect start
git bisect bad HEAD
git bisect good v3.0
git bisect run /tmp/test-migration.sh
git bisect reset
```

### Ssenari 2: N+1 Query Problemini Tapmaq

```bash
cat > /tmp/test-queries.sh << 'EOF'
#!/bin/bash
composer install --quiet 2>/dev/null
php artisan migrate:fresh --seed --quiet 2>/dev/null

# Query sayını yoxla
QUERY_COUNT=$(php artisan tinker --execute="
    DB::enableQueryLog();
    App\Models\User::with([])->get()->each->posts;
    echo count(DB::getQueryLog());
" 2>/dev/null)

# 10-dan çox query varsa = bad (N+1 problem)
if [ "$QUERY_COUNT" -gt 10 ]; then
    exit 1
fi
exit 0
EOF

chmod +x /tmp/test-queries.sh
git bisect start HEAD v2.5
git bisect run /tmp/test-queries.sh
```

### Ssenari 3: Blade Template Error

```bash
cat > /tmp/test-view.sh << 'EOF'
#!/bin/bash
composer install --quiet 2>/dev/null

# View compile test
php artisan view:clear 2>/dev/null
php artisan view:cache 2>&1

if [ $? -ne 0 ]; then
    exit 1  # bad - view compile error
fi
exit 0
EOF

chmod +x /tmp/test-view.sh
git bisect start HEAD v2.0
git bisect run /tmp/test-view.sh
```

### Ssenari 4: API Response Format Dəyişikliyi

```bash
cat > /tmp/test-api.sh << 'EOF'
#!/bin/bash
composer install --quiet 2>/dev/null
php artisan migrate:fresh --seed --quiet 2>/dev/null
php artisan serve --port=8099 &
PID=$!
sleep 3

# API cavabında "data" key-inin olduğunu yoxla
RESPONSE=$(curl -s http://localhost:8099/api/users)
echo "$RESPONSE" | php -r '
    $json = json_decode(file_get_contents("php://stdin"), true);
    exit(isset($json["data"]) ? 0 : 1);
'
RESULT=$?

kill $PID 2>/dev/null
exit $RESULT
EOF

chmod +x /tmp/test-api.sh
git bisect start HEAD v1.0
git bisect run /tmp/test-api.sh
```

## Interview Sualları

### S1: Git bisect nədir və necə işləyir?

**Cavab**: Git bisect, binary search alqoritmi istifadə edərək bug-ı yaradan ilk commit-i tapan əmrdir. İşləmə prinsipi:
1. "good" (işləyən) və "bad" (bug olan) commit-ləri təyin edirsiniz
2. Git ortadakı commit-ə checkout edir
3. Siz test edib "good" və ya "bad" deyirsiniz
4. Git aralığı yarıya endirir
5. Bu proses bug-ı yaradan commit tapılana qədər davam edir

`n` commit üçün `log2(n)` addım lazımdır. 1000 commit-dən 10 addımda bug tapıla bilər.

### S2: Avtomatik bisect necə işləyir?

**Cavab**: `git bisect run <script>` əmri ilə test script-i verilir. Script exit code 0 qaytarsa "good", 1-125 arasında qaytarsa "bad", 125 qaytarsa "skip" kimi qəbul edilir. Bu, manual yoxlama etmədən bisect prosesini tam avtomatlaşdırır.

```bash
git bisect start HEAD v1.0
git bisect run php artisan test --filter=SpecificTest
```

### S3: Bisect zamanı bir commit-i test edə bilməsəniz nə edərsiniz?

**Cavab**: `git bisect skip` istifadə edirəm. Bu, cari commit-i atlayıb başqa bir commit-ə keçir. Məsələn, compile olunmayan və ya migration error verən commit-lər üçün faydalıdır. Lakin çox skip etsəniz, Git dəqiq nəticə verə bilməyə bilər.

### S4: Bisect-dən sonra nə edərsiniz?

**Cavab**:
1. `git bisect reset` ilə əvvəlki branch-ə qayıdıram
2. Tapılmış commit-i `git show <hash>` ilə analiz edirəm
3. Bug-ın səbəbini başa düşürəm
4. Fix yazıram və test əlavə edirəm ki, gələcəkdə təkrarlanmasın
5. `git bisect log` nəticəsini saxlayıram (lazım olarsa)

### S5: Bisect-i performance regression tapmaq üçün istifadə edə bilərsinizmi?

**Cavab**: Bəli. Custom terminlər istifadə edə bilərsiniz:
```bash
git bisect start --term-old=fast --term-new=slow
```
Və ya script ilə response time ölçüb exit code qaytara bilərsiniz. Threshold-dan yavaş = exit 1 (bad), sürətli = exit 0 (good).

### S6: `git bisect log` və `git bisect replay` nə üçün istifadə olunur?

**Cavab**: `git bisect log` bisect sessiyasının addımlarını çap edir. Bu log-u fayla yazıb `git bisect replay` ilə eyni sessiyanı yenidən oynada bilərsiniz. Komanda üzvləri ilə bisect nəticələrini paylaşmaq və ya bisect-i təkrar etmək üçün faydalıdır.

### S7: Bisect ilə merge commit-ləri necə idarə olunur?

**Cavab**: Git bisect merge commit-ləri də daxil olmaqla bütün commit-ləri yoxlayır. Lakin mürəkkəb merge tarixçəsində bəzən `git bisect skip` istifadə etmək lazım ola bilər. `--first-parent` ilə yalnız main branch commit-lərini yoxlamaq da mümkündür.

## Best Practices

### 1. Reproduksiya Script-i Yazın

```bash
# Əvvəlcə bug-ı stabil şəkildə reproduce edən script yazın
# Sonra bisect run ilə istifadə edin

cat > /tmp/reproduce-bug.sh << 'EOF'
#!/bin/bash
set -e
composer install --quiet
php artisan migrate:fresh --seed --quiet
php artisan test --filter=BrokenTest
EOF
```

### 2. Tag-ləri İstifadə Edin

```bash
# Release tag-ləri bisect üçün əla "good" nöqtəsidir
git bisect good v2.3.1
# Tag yoxdursa, tarix ilə
git bisect good $(git log --before="2026-03-01" --format="%H" -1)
```

### 3. Bisect Log-unu Saxlayın

```bash
# Nəticəni sənədləşdirin
git bisect log > bisect-result-2026-04-16.txt
# PR-a və ya issue-ya əlavə edin
```

### 4. Skip-i Minimuma Endirin

```
Çox skip = qeyri-dəqiq nəticə

✅ Yaxşı: Test script-ində dependency-ləri handle edin
✅ Yaxşı: composer install || exit 125  (125 = skip)
❌ Pis: Manual olaraq hər compile olunmayan commit-i skip etmək
```

### 5. Dar Aralıq Seçin

```bash
# Əgər təxminən haradan başladığını bilirsinizsə, dar aralıq verin
# Bu, addım sayını azaldır

# Pis: 2000 commit aralığı
git bisect good v1.0  # 6 ay əvvəl

# Yaxşı: 50 commit aralığı
git bisect good abc1234  # Keçən həftə işləyirdi
```

### 6. Testləri Bisect-dən Sonra Əlavə Edin

```bash
# Bug commit-ini tapdıqdan sonra:
# 1. Bug-ı reproduce edən test yazın
# 2. Fix-i tətbiq edin
# 3. Test-in keçdiyinə əmin olun

# Bu, regression-ın təkrarlanmasının qarşısını alır
```
