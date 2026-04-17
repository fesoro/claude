# Git Log Advanced

## Nədir? (What is it?)

Git log, commit tarixçəsini göstərən güclü əmrdir. Sadə `git log`-dan əlavə, formatlaşdırma, filtrasiya, vizualizasiya və analiz üçün çoxlu seçimlər mövcuddur. `git blame`, `git shortlog` və `git reflog` da tarixçə araşdırma alətləridir.

```
git log çıxışı (default):

commit abc1234def5678 (HEAD -> main, origin/main)
Author: Developer <dev@example.com>
Date:   Wed Apr 16 10:30:00 2026 +0400

    feat: add user authentication

commit def5678ghi9012
Author: Developer <dev@example.com>
Date:   Tue Apr 15 14:20:00 2026 +0400

    fix: correct validation rules
```

## Əsas Əmrlər (Key Commands)

### Formatlaşdırma

```bash
# Tək sətir format
git log --oneline
# abc1234 feat: add user authentication
# def5678 fix: correct validation rules

# Graph (branch vizualizasiyası)
git log --oneline --graph
# * abc1234 (HEAD -> main) Merge feature/auth
# |\
# | * def5678 feat: add login
# | * ghi9012 feat: add register
# |/
# * jkl3456 initial commit

# Graph + bütün branch-lər
git log --oneline --graph --all

# Graph + dekorasiya
git log --oneline --graph --all --decorate

# Custom format
git log --pretty=format:"%h - %an, %ar : %s"
# abc1234 - Developer, 2 hours ago : feat: add auth

# Detallı custom format
git log --pretty=format:"%C(yellow)%h%C(reset) %C(blue)%an%C(reset) %C(green)(%ar)%C(reset) %s %C(red)%d%C(reset)"
```

### Format Placeholder-ları

```
%H  - Tam commit hash
%h  - Qısa commit hash
%T  - Tree hash
%t  - Qısa tree hash
%P  - Parent hash
%p  - Qısa parent hash
%an - Author adı
%ae - Author email
%ad - Author tarixi
%ar - Author tarixi (nisbi: "2 days ago")
%cn - Committer adı
%ce - Committer email
%cd - Committer tarixi
%cr - Committer tarixi (nisbi)
%s  - Commit mesajının ilk sətri (subject)
%b  - Commit mesajının body-si
%d  - Ref adları (branch, tag)
%D  - Ref adları (dekorasiya olmadan)

Rənglər:
%C(red)    %C(green)    %C(blue)
%C(yellow) %C(cyan)     %C(reset)
%C(bold)   %C(dim)
```

### Filtrasiya

```bash
# Author ilə
git log --author="Orkhan"
git log --author="orkhan\|john"  # Çoxlu author

# Tarixə görə
git log --since="2026-04-01"
git log --until="2026-04-15"
git log --since="2 weeks ago"
git log --after="2026-03-01" --before="2026-04-01"

# Commit mesajında axtarış
git log --grep="payment"
git log --grep="fix:" --grep="bug" --all-match  # AND
git log --grep="fix:\|feat:"                      # OR

# Fayl üzrə
git log -- app/Models/User.php
git log -- "*.php"

# Faylda dəyişiklik edən commit-lər
git log -p -- app/Models/User.php

# Kodu axtarış (pickaxe)
git log -S "function processPayment"  # Bu string əlavə/silinən commit
git log -G "process.*Payment"          # Regex ilə

# Merge commit-ləri göstər/gizlət
git log --merges
git log --no-merges

# İlk parent (merge-lərin yalnız əsas xətti)
git log --first-parent

# Son N commit
git log -5
git log -10 --oneline
```

### Statistika

```bash
# Dəyişən fayllar
git log --stat
# abc1234 feat: add user auth
#  app/Models/User.php    | 15 +++++++
#  routes/web.php         |  5 +++
#  2 files changed, 20 insertions(+)

# Qısa stat
git log --shortstat

# Yalnız fayl adları
git log --name-only

# Fayl adları + status (A=added, M=modified, D=deleted)
git log --name-status

# Diff ilə
git log -p
git log -p -1  # Son commit-in diff-i

# Sətir dəyişiklik sayı (numstat)
git log --numstat
```

## Git Blame

```bash
# Faylın hər sətirini kim, nə zaman dəyişdirdiyini göstərir
git blame app/Models/User.php
# abc1234 (Orkhan  2026-04-10 14:30) class User extends Authenticatable
# def5678 (John    2026-04-12 09:15) {
# ghi9012 (Orkhan  2026-04-13 11:00)     use HasFactory, Notifiable;

# Sətir aralığı
git blame -L 10,20 app/Models/User.php

# Rənglərlə (commit-ə görə)
git blame --color-lines app/Models/User.php

# Email göstər
git blame -e app/Models/User.php

# Whitespace dəyişikliklərini ignore et
git blame -w app/Models/User.php

# Köçürülmüş/kopyalanmış sətirləri detect et
git blame -M app/Models/User.php        # Fayl daxili
git blame -C app/Models/User.php        # Fayllar arası
git blame -CCC app/Models/User.php      # Daha aggressiv
```

## Git Shortlog

```bash
# Author-a görə commit sayı
git shortlog -s -n
#  42  Orkhan
#  31  John
#  15  Alice

# Tarix aralığı ilə
git shortlog -s -n --since="2026-01-01"

# Email ilə
git shortlog -s -n -e

# Commit mesajları ilə
git shortlog
# Orkhan (42):
#   feat: add user authentication
#   fix: correct validation rules
#   ...
```

## Git Reflog

```bash
# Bütün HEAD hərəkətləri (branch dəyişmə, reset, rebase...)
git reflog
# abc1234 HEAD@{0}: commit: feat: add auth
# def5678 HEAD@{1}: checkout: moving from feature to main
# ghi9012 HEAD@{2}: commit: WIP: working on feature
# jkl3456 HEAD@{3}: reset: moving to HEAD~1

# Branch üçün reflog
git reflog show feature/auth

# Tarixlə
git reflog --date=iso

# Silinmiş commit-i bərpa etmək
git reflog
# ghi9012 HEAD@{2}: commit: important work  ← Bu commit
git checkout ghi9012
# və ya
git cherry-pick ghi9012
```

## Praktiki Nümunələr (Practical Examples)

### Nümunə 1: Release Notes Yaratmaq

```bash
# Son iki tag arasındakı dəyişikliklər
git log v1.1.0..v1.2.0 --pretty=format:"- %s (%h)" --no-merges

# Kategoriyalara bölmək
echo "### Features"
git log v1.1.0..v1.2.0 --pretty=format:"- %s (%h)" --no-merges --grep="^feat"

echo "### Bug Fixes"
git log v1.1.0..v1.2.0 --pretty=format:"- %s (%h)" --no-merges --grep="^fix"

echo "### Other"
git log v1.1.0..v1.2.0 --pretty=format:"- %s (%h)" --no-merges --grep="^chore\|^docs\|^refactor"
```

### Nümunə 2: Kimin Nə Qədər İşlədiyini Görmək

```bash
# Bu ay kimin neçə commit etdiyini
git shortlog -s -n --since="2026-04-01"

# Hər developer-in dəyişdirdiyi sətir sayı
git log --author="Orkhan" --numstat --since="2026-04-01" | \
  awk 'NF==3 {plus+=$1; minus+=$2} END {printf("+%d, -%d\n", plus, minus)}'
```

### Nümunə 3: Problemli Fayl Tapmaq

```bash
# Ən çox dəyişən fayllar (son 3 ayda)
git log --since="3 months ago" --name-only --pretty=format: | \
  sort | uniq -c | sort -rn | head -20

# Ən çox bug fix olan fayllar
git log --grep="^fix" --name-only --pretty=format: | \
  sort | uniq -c | sort -rn | head -10
```

### Nümunə 4: Spesifik Funksiyanın Tarixçəsi

```bash
# Funksiya nə zaman əlavə edildi?
git log -S "function processPayment" --oneline
# abc1234 feat: add payment processing

# Funksiyanın bütün dəyişiklikləri
git log -p -S "processPayment" -- app/Services/PaymentService.php

# Sətir aralığının tarixçəsi (funksiyanın evolution-u)
git log -L :processPayment:app/Services/PaymentService.php
```

## Vizual İzah (Visual Explanation)

### Git Log --graph Çıxışı

```
$ git log --oneline --graph --all

* 8a5c2d1 (HEAD -> main) Merge feature/payment
|\
| * 3f4e5d6 feat: add payment processing
| * 1a2b3c4 feat: add payment model
|/
* 9e8f7a6 Merge feature/auth
|\
| * 5d6e7f8 feat: add login page
| * 2c3d4e5 feat: add User model
|/
* 7b8c9d0 initial commit

Simvollar:
  *     = commit
  |     = branch xətti
  \  /  = merge/diverge
  |/    = merge (sağdan sola)
  |\    = branch (soldan sağa)
```

### Blame Çıxışı

```
$ git blame app/Http/Controllers/PaymentController.php

abc1234 (Orkhan 2026-04-01 10:00)  <?php
abc1234 (Orkhan 2026-04-01 10:00)  
abc1234 (Orkhan 2026-04-01 10:00)  namespace App\Http\Controllers;
abc1234 (Orkhan 2026-04-01 10:00)  
def5678 (John   2026-04-05 14:30)  use App\Services\PaymentService;
abc1234 (Orkhan 2026-04-01 10:00)  
ghi9012 (Alice  2026-04-10 09:00)  class PaymentController extends Controller
abc1234 (Orkhan 2026-04-01 10:00)  {
ghi9012 (Alice  2026-04-10 09:00)      public function __construct(
ghi9012 (Alice  2026-04-10 09:00)          private PaymentService $paymentService
ghi9012 (Alice  2026-04-10 09:00)      ) {}
```

### Reflog Timeline

```
Reflog (HEAD hərəkətlər tarixçəsi):

HEAD@{0}: commit: feat: add payment       ← İndi
HEAD@{1}: checkout: develop → feature     ← 1 saat əvvəl
HEAD@{2}: commit: fix: typo              ← 2 saat əvvəl
HEAD@{3}: reset: moving to HEAD~2        ← 3 saat əvvəl (2 commit silindi!)
HEAD@{4}: commit: important work          ← Silinmiş amma reflog-da!
HEAD@{5}: commit: another important work  ← Silinmiş amma reflog-da!

Bərpa: git cherry-pick HEAD@{4}
```

## PHP/Laravel Layihələrdə İstifadə

### Faydalı Git Alias-lar

```bash
# ~/.gitconfig
[alias]
    # Gözəl log
    lg = log --oneline --graph --decorate --all
    
    # Son 10 commit
    last = log --oneline -10
    
    # Bugünkü commit-lər
    today = log --oneline --since='00:00:00' --all --no-merges
    
    # Mənim commit-lərim (bu həftə)
    mine = log --oneline --author='Orkhan' --since='1 week ago' --no-merges
    
    # Fayl tarixçəsi
    filelog = log --oneline --follow
    
    # Blame qısa format
    who = blame -w -C
    
    # Commit statistikası
    stats = shortlog -s -n --all
    
    # Dəyişən fayllar (son commit)
    changed = diff-tree --no-commit-id --name-only -r HEAD
```

### Laravel Migration Tarixçəsi

```bash
# Migration fayllarının tarixçəsi
git log --oneline -- database/migrations/

# Spesifik migration-ın tarixçəsi
git log -p -- database/migrations/*create_users_table*

# Kim hansı migration-ı yaradıb?
git log --pretty=format:"%an: %s" -- database/migrations/ | sort
```

### Controller Dəyişikliklərini İzləmə

```bash
# PaymentController-in bütün dəyişiklikləri
git log --oneline -p -- app/Http/Controllers/PaymentController.php

# Bu controller-ə ən çox kim toxunub?
git shortlog -s -n -- app/Http/Controllers/PaymentController.php

# Controller-də "processPayment" metodunun tarixçəsi
git log -L :processPayment:app/Http/Controllers/PaymentController.php
```

### Debug: Hansı Commit Nəyi Sındırdı?

```bash
# "payment" ilə bağlı son dəyişikliklər
git log --oneline --all -20 --grep="payment"

# PaymentService-də son dəyişikliklər
git log --oneline -10 -p -- app/Services/PaymentService.php

# Bu sətiri kim dəyişdirib?
git blame -L 45,50 app/Services/PaymentService.php
```

## Interview Sualları

### S1: `git log --oneline --graph --all` nə göstərir?

**Cavab**: Bu əmr bütün branch-lərdəki commit-ləri qısa hash və mesaj ilə (--oneline), ASCII branch diaqramı ilə (--graph), və bütün branch-ləri daxil edərək (--all) göstərir. Branch-lərin necə ayrıldığını və merge olunduğunu vizual olaraq görmək üçün ən faydalı əmrlərdəndir.

### S2: `git blame` nə üçün istifadə olunur?

**Cavab**: `git blame` faylın hər sətirini hansı commit-in, kimin, nə zaman dəyişdirdiyini göstərir. Bug tapdıqda "bu sətiri kim və niyə dəyişdirib?" sualına cavab verir. `-w` ilə whitespace ignore, `-C` ilə kopyalanmış sətirləri detect edə bilir.

### S3: `git reflog` ilə `git log` arasındakı fərq nədir?

**Cavab**:
- **git log**: Commit tarixçəsini göstərir (DAG boyunca)
- **git reflog**: HEAD-in bütün hərəkətlərini göstərir (checkout, reset, rebase daxil)

Reflog yalnız lokaldır, remote ilə paylaşılmır. `git reset --hard` ilə "silinmiş" commit-ləri reflog vasitəsilə bərpa edə bilərsiniz.

### S4: `-S` və `-G` flag-ları arasındakı fərq nədir?

**Cavab**:
- `-S "text"` (pickaxe): Bu mətni əlavə edən və ya silən commit-ləri tapır
- `-G "regex"`: Bu regex-ə uyğun dəyişiklik olan commit-ləri tapır

`-S` say dəyişikliyinə baxır (əlavə/silmə), `-G` isə diff-in özünə baxır.

### S5: Ən çox dəyişən faylları necə taparsınız?

**Cavab**:
```bash
git log --name-only --pretty=format: --since="3 months ago" | \
  sort | uniq -c | sort -rn | head -10
```
Bu, son 3 ayda ən çox dəyişdirilən faylları göstərir. Çox dəyişən fayllar refactoring namizədidir.

### S6: `git log -L` nə edir?

**Cavab**: `-L` flag-ı faylda spesifik sətir aralığının və ya funksiyanın tarixçəsini göstərir:
```bash
git log -L 10,20:file.php       # 10-20 sətir aralığı
git log -L :functionName:file.php  # Funksiya tarixçəsi
```

### S7: Silinmiş branch-dəki commit-i necə taparsınız?

**Cavab**: `git reflog` istifadə edirəm. Reflog bütün HEAD hərəkətlərini saxlayır, branch silinmiş olsa belə. Commit hash-ini reflog-dan tapıb `git cherry-pick` və ya `git checkout -b` ilə bərpa edə bilərəm. Reflog default olaraq 90 gün saxlanır.

## Best Practices

### 1. Faydalı Alias-lar Qurun

```bash
git config --global alias.lg "log --oneline --graph --all --decorate"
git config --global alias.last "log --oneline -10"
git config --global alias.today "log --oneline --since='00:00:00' --no-merges"
```

### 2. `--follow` İstifadə Edin

```bash
# Fayl adı dəyişibsə, əvvəlki adla olan tarixçəni də göstərir
git log --follow -- app/Services/NewPaymentService.php
```

### 3. Reflog-u Bilin

```
Reflog sizin "undo" tarixçənizdir.
Reset, rebase, branch silmə - hamısı reflog-da qalır.
Default 90 gün saxlanır.
Yalnız lokal (remote-da yoxdur).
```

### 4. Blame ilə Kontekst Anlayın

```bash
# Sadəcə "kim yazdı?" yox, "niyə yazdı?" sualını verin
git blame file.php  # Sətri tapın
git show abc1234    # Commit-in tam kontekstini oxuyun
```

### 5. Log Format-ı Standartlaşdırın

```bash
# Komanda üçün standart log format
git config --global format.pretty "format:%C(yellow)%h%C(reset) %C(blue)%an%C(reset) (%ar) %s%C(red)%d%C(reset)"
```

### 6. Commit Mesajlarında Axtarış Asanlığı

```
Conventional Commits istifadə edin ki,
git log --grep="feat:" bütün feature-ları tapsın:

feat: add payment processing
fix: correct timeout issue
chore: update dependencies
```
