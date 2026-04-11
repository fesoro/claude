# Git Advanced — Tagging, Branching Strategies, Dərin Alətlər

## Mündəricat
1. [Tagging](#tagging)
2. [Branching Strategies](#branching-strategies)
3. [Rebase vs Merge](#rebase-vs-merge)
4. [Cherry-pick](#cherry-pick)
5. [Git Bisect](#git-bisect)
6. [Reflog](#reflog)
7. [Submodules](#submodules)
8. [Hooks](#hooks)
9. [İntervyu Sualları](#intervyu-sualları)

---

## Tagging

*Tagging üçün kod nümunəsi:*
```bash
# Bu kod Git lightweight və annotated tag yaratma, push etmə əmrlərini göstərir
# Lightweight tag (sadə pointer)
git tag v1.0.0
git tag v1.0.0 abc1234  # Spesifik commit-ə

# Annotated tag (metadata ilə — tövsiyə edilir)
git tag -a v1.0.0 -m "Release version 1.0.0"
git tag -a v1.0.0 abc1234 -m "Hotfix release"

# Tag-ları listə al
git tag
git tag -l "v1.*"   # Pattern

# Tag məlumatı
git show v1.0.0

# Remote-a push
git push origin v1.0.0       # Tək tag
git push origin --tags        # Bütün tag-lar

# Tag-ı sil
git tag -d v1.0.0             # Local
git push origin --delete v1.0.0  # Remote

# Semantic Versioning:
# MAJOR.MINOR.PATCH
# v1.0.0 → v1.0.1 (patch: bugfix)
# v1.0.0 → v1.1.0 (minor: new feature, backward compatible)
# v1.0.0 → v2.0.0 (major: breaking change)

# Pre-release:
# v1.0.0-alpha.1
# v1.0.0-beta.2
# v1.0.0-rc.1
```

---

## Branching Strategies

### GitFlow

```
// Bu kod GitFlow branching strategiyasını main, develop, feature, release, hotfix branch-ləri ilə göstərir
main ────●────────────────────────────●──── (production)
         │                            │
develop ─●──●──●──●──●──●──●──●──●──●──── (integration)
              │           │
feature/x ────●──●──●─────┘
                   │
feature/y ─────────●──●──●─────────────────
              
release/1.0 ──────────────●──●──●───────────
                                      │
hotfix/critical ──────────────────────●──●──

Branch-lər:
  main: production-ready
  develop: inteqrasiya
  feature/*: yeni feature (develop-dan, develop-a merge)
  release/*: release preparation (develop-dan, main+develop-a merge)
  hotfix/*: urgent fix (main-dən, main+develop-a merge)

✅ Structured, clear
✅ Parallel development
❌ Mürəkkəb, çox branch
❌ Long-lived branches → merge conflict
```

### Trunk-Based Development (TBD)

```
// Bu kod Trunk-Based Development strategiyasında qısa ömürlü branch-lər və feature flag-ların istifadəsini göstərir
main ────●──●──●──●──●──●──●──●──●── (hər commit deployable)
         │        │
short ───●──●──●──┘  (max 2 gün yaşayan feature branch)
feature
         
Feature Flags ilə:
  Incomplete feature → flag arxasında gizlə
  Flag off → production-da görünmür
  Flag on → enable et

✅ CI/CD dostu
✅ Az merge conflict
✅ Fast delivery
❌ Feature flags tələb edir
❌ Disiplin lazımdır
```

### GitHub Flow (sadələşdirilmiş)

```
// Bu kod GitHub Flow-un sadə branch-PR-merge iş axınını göstərir
main ───●────────────────●──────────── (always deployable)
         │               │
feature ─●──●──●──PR──review──merge
         
Addımlar:
  1. main-dən branch (feature/fix/...)
  2. Commit, push
  3. Pull Request aç
  4. Review + CI
  5. main-ə merge
  6. Deploy

✅ Sadə
✅ CI/CD uyğun
✅ Kiçik team-lər üçün ideal
❌ Release management yoxdur
```

---

## Rebase vs Merge

```
// Bu kod merge və rebase əməliyyatlarının commit history-yə təsirini müqayisəli göstərir
Merge:
  feature  ──●──●──●
            /        \
  main ────●───────────●  (merge commit)
  
  git merge feature
  
  ✅ History qorunur
  ✅ Parallel iş görünür
  ❌ Merge commit-lər history-ni çirkləndirir

Rebase:
  feature  ──●──●──●
            /
  main ────●──●──●
  
  git rebase main  # feature commit-lərini main üzərinə köçür
  
  main ────●──●──●──●'──●'──●'  (yeni commit-lər, yeni hash)
  
  ✅ Təmiz, linear history
  ✅ Merge commit yoxdur
  ❌ Commit hash-ləri dəyişir
  ❌ Shared branch-lərdə QADAĞAN (başqaları push etmişsə)

Qayda: "Golden Rule of Rebasing"
  Public/shared branch-ləri (main, develop) rebase etmə!
  Öz local feature branch-ini rebase et.

Interactive Rebase (commit-ləri düzəlt):
  git rebase -i HEAD~3  # Son 3 commit-i interaktiv edit et
  
  pick abc1234 Add user registration
  squash def5678 Fix typo          ← öncəkiylə birləşdir
  reword ghi9012 Update README     ← commit mesajını dəyiş
  drop jkl3456 Debug log           ← bu commit-i sil
```

---

## Cherry-pick

*Cherry-pick üçün kod nümunəsi:*
```bash
# Bu kod cherry-pick ilə spesifik commit-ləri başqa branch-ə köçürməyi göstərir
# Spesifik commit-i başqa branch-ə köçür
git cherry-pick abc1234

# Bir neçə commit
git cherry-pick abc1234 def5678

# Range
git cherry-pick abc1234..ghi9012

# Conflict olduqda
git cherry-pick --continue  # Conflict həll etdikdən sonra
git cherry-pick --abort     # Ləğv et

# Nümunə: hotfix-i develop-a da köçür
git checkout develop
git cherry-pick hotfix-commit-hash

# -n flag: commit etmədən yalnız changes al
git cherry-pick -n abc1234
git commit -m "Cherry-picked fix from main"
```

---

## Git Bisect

```
// Bu kod git bisect ilə binary search metodunu istifadə edərək buggy commit-i tapır
Binary search ilə bug-ı tapan commit-i tap:

git bisect start
git bisect bad                    # Hazırkı commit bug var
git bisect good v1.0.0            # Bu version-da bug yox idi

Git avtomatik olaraq aradakı commit-i checkout edir:
  Bisecting: 15 revisions left to test

Test et → bug varsa:
  git bisect bad

Test et → bug yoxdursa:
  git bisect good

Git binary search edir → 15 commit → 4 addımda tapır!
O(log n) axtarış!

Avtomatik bisect:
  git bisect run php artisan test  # Test pass = good, fail = bad

Bitdikdən sonra:
  git bisect reset  # HEAD-ə qayıt
```

---

## Reflog

*Reflog üçün kod nümunəsi:*
```bash
# Bu kod git reflog ilə silinmiş commit-lər və branch-lərin bərpa edilməsini göstərir
# Bütün HEAD hərəkətlərini göstər (local-da saxlanır, 90 gün)
git reflog

# Output:
# abc1234 HEAD@{0}: commit: Add user auth
# def5678 HEAD@{1}: checkout: moving from feature to main
# ghi9012 HEAD@{2}: reset: moving to HEAD~1
# jkl3456 HEAD@{3}: commit: Initial setup

# "Silinmiş" commit-i geri qaytар
git reset --hard HEAD@{2}  # 2 addım əvvəlki state-ə

# Accidental reset --hard-dan recover
git reflog              # commit hash-i tap
git checkout -b recover abc1234  # Yeni branch-ə köçür

# Silinmiş branch-i geri qaytар
git reflog | grep feature-branch
git checkout -b feature-branch abc1234

# Reflog-u göstər
git reflog show HEAD
git reflog show main  # Specific branch
```

---

## Submodules

*Submodules üçün kod nümunəsi:*
```bash
# Bu kod git submodule əlavə etmə, yeniləmə və silmə əmrlərini göstərir
# Başqa repo-nu projekt-ə əlavə et
git submodule add https://github.com/org/library.git libs/library

# Clone zamanı submodule-larla
git clone --recurse-submodules https://github.com/...

# Mövcud clone-a submodule-ları əlavə et
git submodule init
git submodule update

# Submodule-u update et
cd libs/library
git pull origin main
cd ../..
git add libs/library
git commit -m "Update library submodule"

# Bütün submodule-ları update et
git submodule update --remote --merge

# Submodule-u sil
git submodule deinit libs/library
git rm libs/library
rm -rf .git/modules/libs/library
```

---

## Hooks

*Hooks üçün kod nümunəsi:*
```bash
# Bu kod pre-commit, commit-msg və pre-push Git hook-larının PHP layihəsi üçün istifadəsini göstərir
# .git/hooks/ direktoriyası
# Executable script-lər

# pre-commit: commit-dən əvvəl
#!/bin/bash
# .git/hooks/pre-commit

# PHP syntax check
php -l $(git diff --cached --name-only | grep "\.php$") || exit 1

# CS Fixer
./vendor/bin/php-cs-fixer fix --dry-run --diff $(git diff --cached --name-only | grep "\.php$")
[ $? -ne 0 ] && echo "Code style xəta!" && exit 1

exit 0

# commit-msg: commit mesajını yoxla
#!/bin/bash
# .git/hooks/commit-msg

MSG=$(cat "$1")
PATTERN="^(feat|fix|docs|style|refactor|test|chore)(\(.+\))?: .{1,72}$"

if ! echo "$MSG" | grep -qE "$PATTERN"; then
    echo "Commit mesajı formatı yanlışdır!"
    echo "Format: type(scope): description"
    echo "Nümunə: feat(auth): add JWT refresh token"
    exit 1
fi

# pre-push: push-dan əvvəl test
#!/bin/bash
# .git/hooks/pre-push

php artisan test --stop-on-failure || exit 1

# Husky (Node.js ilə hook management)
# package.json:
{
  "husky": {
    "hooks": {
      "pre-commit": "lint-staged",
      "commit-msg": "commitlint --edit $1"
    }
  }
}

# CaptainHook (PHP)
# composer require captainhook/captainhook --dev
```

---

## İntervyu Sualları

**1. Annotated vs lightweight tag fərqi nədir?**
Lightweight: sadəcə commit-ə pointer. Annotated: tagger adı, email, tarixi, mesajı olan ayrı Git obyekti. Release üçün annotated tövsiyə edilir (`git tag -a v1.0.0 -m "..."`). `git show` annotated-da metadata göstərir.

**2. GitFlow vs Trunk-Based Development fərqi nədir?**
GitFlow: uzun ömürlü branch-lər (main, develop, feature, release, hotfix). Structured, amma mürəkkəb, merge conflict riski. TBD: hər şey main-ə, qısa (max 2 gün) feature branch-lər, feature flags. CI/CD dostu, sürətli delivery.

**3. Rebase-in "Golden Rule"-u nədir?**
Public/shared branch-ləri (main, develop, başqası push etmişsə) rebase etmə. Commit hash-lər dəyişir → push etmiş hamının history-si pozulur. Yalnız öz local feature branch-ini rebase et.

**4. Git bisect necə işləyir?**
Binary search ilə buggy commit-i tapır. `git bisect bad` (hazırkı commit) + `git bisect good` (köhnə, bug yox idi) verilir. Git ortadakı commit-i checkout edir. Test et → bad/good de → O(log n) addımda tapır. `git bisect run script` ilə avtomatikləşdirilə bilər.

**5. Reflog nədir, nə zaman lazımdır?**
HEAD-in bütün hərəkətlərinin local log-u. `reset --hard`, silinmiş branch, yanlış commit → reflog ilə geri qaytarmaq olar. 90 gün saxlanılır. Remote-da yoxdur — yalnız local. `git reflog` + `git checkout -b recover <hash>`.

**6. `git merge --squash` vs `git merge` vs `git rebase` nə vaxt seçilir?**
`git merge`: history qorunur, merge commit yaranır — long-lived branch-lər üçün. `git rebase`: linear history, commit-lər üzərindən yenidən yazılır — feature branch-ləri main üzərinə tapmaq üçün. `git merge --squash`: feature branch-dəki bütün commit-ləri bir commit-ə sıxışdırır — "noisy" feature history-ni gizlətmək üçün, amma original commit-lər itirilir.

**7. Monorepo vs multi-repo strategiyası nədir?**
Monorepo: bütün servislər bir repo-da. Paylaşılan kod asandır, atomic commit mümkündür, amma repo ölçüsü böyüyür. Nx, Turborepo kimi alətlər lazımdır. Multi-repo: hər servis ayrı repo — yalnız o servisin CI/CD-si işləyir, amma cross-service dəyişiklik çətin olur. Şirkətin ölçüsünə görə seçilir.

**8. `git stash` ilə `git worktree` fərqi nədir?**
`git stash`: mövcud dəyişiklikləri müvəqqəti saxla, başqa branch-ə keç, geri qayıt. `git worktree`: eyni repo-dan bir neçə working directory — eyni anda iki branch-də paralel işləmək üçün. Böyük layihələrdə context switch zamanı stash alternative-idir.

---

## Anti-patternlər

**1. Shared branch-ləri rebase etmək**
`develop` ya da `main` branch-ini başqaları push etdikdən sonra rebase etmək — commit hash-lər dəyişir, komanda üzvlərinin local history-si pozulur, force push lazım olur. Yalnız öz local feature branch-ini rebase edin; shared branch-lər üçün həmişə `git merge` istifadə edin.

**2. "WIP" ya da "fix" kimi mənasız commit mesajları yazmaq**
`git commit -m "fix"`, `git commit -m "changes"` — 3 ay sonra hansı problemi həll etdiyini anlamaq mümkün olmur, `git blame` faydasız olur. Conventional Commits formatını istifadə edin: `fix(auth): token expiry edge case-i düzəldildi`; nə etdiyinizi deyil, niyə etdiyinizi yazın.

**3. `git push --force` istifadə etmək (shared branch-də)**
Öz branch-ini tənizləmək üçün `git push --force` işlətmək, lakin başqası da o branch-ə push edibsə — onların commit-ləri silinir, iş itirilir. `git push --force-with-lease` istifadə edin: yerli branch remote ilə sinxron deyilsə push rədd edilir.

**4. `.gitignore`-u sonradan əlavə etmək**
Proyektə əvvəlcə `.gitignore` olmadan başlamaq, `.env`, `vendor/`, `storage/logs/` track edilir — sensitive data remote-a push olunur, repo ölçüsü şişir. Hər proyektin başında `.gitignore` qurun; artıq track edilmiş faylları `git rm --cached` ilə siyahıdan çıxarın.

**5. Uzun ömürlü feature branch-lər saxlamaq**
Feature branch-i həftələrlə main-dən kəsilmiş saxlamaq — merge conflict-lər toplanır, inteqrasiya çətinləşir, review üçün böyük diff yaranır. Branch-ləri qısa (max 1-2 gün) tutun; uzun feature-lar üçün feature flag arxasında kiçik commit-lərlə tədricən main-ə merge edin.

**6. Git history-ni anlamadan `reset --hard` istifadə etmək**
`git reset --hard HEAD~3` — 3 commit geri qayıdır, həmin commit-lərdəki bütün dəyişikliklər itirilir. `git reset --soft` ya da `git revert` istifadə edin: `--soft` dəyişiklikləri staged saxlayır, `revert` yeni commit ilə əvvəlki dəyişikliyi geri alır, history pozulmur.
