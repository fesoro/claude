# .gitignore

## Nədir? (What is it?)

`.gitignore` faylı, Git-ə hansı faylları və qovluqları izləməməsini (track etməməsini) bildirir. Build artefaktları, dependency-lər, əməliyyat sistemi faylları, IDE konfiqurasiyaları və həssas məlumatlar (API key-lər, parollar) kimi fayllar `.gitignore`-a əlavə edilir.

```
.gitignore olmadan:
  git status
  # Untracked files:
  #   .env                   ← Parollar!
  #   vendor/                ← 50MB dependency
  #   node_modules/          ← 200MB dependency
  #   .idea/                 ← IDE faylları
  #   storage/logs/          ← Log faylları
  #   .DS_Store              ← macOS sistem faylı

.gitignore ilə:
  git status
  # nothing to commit, working tree clean ✓
```

### .gitignore Yerləri

```
┌──────────────────────────────────────────────────┐
│ 1. Repo root: .gitignore (ən çox istifadə olunan)│
│    Repo ilə paylaşılır                            │
├──────────────────────────────────────────────────┤
│ 2. Alt qovluqlarda: subdir/.gitignore            │
│    Həmin qovluğa tətbiq olunur                    │
├──────────────────────────────────────────────────┤
│ 3. Global: ~/.gitignore_global                    │
│    Bütün repo-lara tətbiq olunur (şəxsi)          │
├──────────────────────────────────────────────────┤
│ 4. Repo-specific: .git/info/exclude               │
│    Yalnız lokal, paylaşılmır                      │
└──────────────────────────────────────────────────┘
```

## Əsas Əmrlər (Key Commands)

### Pattern Syntax

```bash
# Sadə fayl/qovluq adı
.env
node_modules/

# Wildcard (*) - istənilən simvol(lar)
*.log
*.tmp
*.cache

# Sual işarəsi (?) - tək simvol
file?.txt    # file1.txt, fileA.txt (file10.txt yox)

# Qovluq slash (/)
/vendor/     # Yalnız root-dakı vendor/
vendor/      # İstənilən yerdəki vendor/
build/       # İstənilən yerdəki build/

# Double asterisk (**) - iç-içə qovluqlar
**/logs      # İstənilən yerdəki logs qovluğu
logs/**      # logs/ daxilindəki hər şey
**/logs/**   # İstənilən yerdəki logs/ və içindəki hər şey

# Negate (istisna)
*.log        # Bütün .log fayllarını ignore et
!important.log  # Amma important.log-u track et

# Comment
# Bu comment-dir

# Boşluqlu fayl adları
my\ file.txt
# və ya
"my file.txt"

# Range
[abc]        # a, b, və ya c
[0-9]        # Rəqəmlər
```

### Global .gitignore

```bash
# Global gitignore yaradın
touch ~/.gitignore_global

# Git-ə bildirin
git config --global core.excludesfile ~/.gitignore_global

# ~/.gitignore_global
cat << 'EOF' > ~/.gitignore_global
# macOS
.DS_Store
.AppleDouble
.LSOverride
._*

# Linux
*~
.fuse_hidden*
.Trash-*
.nfs*

# Windows
Thumbs.db
ehthumbs.db
Desktop.ini

# IDE
.idea/
.vscode/
*.swp
*.swo
*~

# Tags
tags
TAGS
EOF
```

### Track Olunmuş Faylı Ignore Etmə

```bash
# Problem: .env artıq track olunur, .gitignore işləmir

# Həll: Faylı Git cache-dən silin
git rm --cached .env
echo ".env" >> .gitignore
git commit -m "chore: remove .env from tracking"

# Qovluq üçün
git rm -r --cached vendor/
git commit -m "chore: remove vendor from tracking"

# Fayl disk-dən SİLİNMİR, yalnız Git-dən çıxarılır
```

### Faydalı Əmrlər

```bash
# Ignored faylları göstər
git status --ignored

# Fayl niyə ignore olunur? (hansı rule?)
git check-ignore -v file.txt
# .gitignore:3:*.txt    file.txt

# Bütün ignored faylları siyahıla
git ls-files --ignored --exclude-standard

# Ignored faylları sil (təmizlə)
git clean -fdX   # Yalnız ignored faylları sil
git clean -fdx   # Ignored + untracked faylları sil (DİQQƏT!)
```

## Praktiki Nümunələr (Practical Examples)

### Nümunə 1: Laravel .gitignore (Tam)

```bash
# .gitignore (Laravel layihəsi)

# Laravel
/vendor/
/node_modules/
/public/hot
/public/storage
/public/build
/storage/*.key
/storage/app/*
/storage/framework/cache/*
/storage/framework/sessions/*
/storage/framework/views/*
/storage/logs/*
!storage/app/.gitkeep
!storage/framework/cache/.gitkeep
!storage/framework/sessions/.gitkeep
!storage/framework/views/.gitkeep
!storage/logs/.gitkeep
bootstrap/cache/*
!bootstrap/cache/.gitkeep

# Environment
.env
.env.backup
.env.production
.env.*.local

# IDE
.idea/
.vscode/
*.swp
*.swo

# OS
.DS_Store
Thumbs.db

# Composer
/vendor/

# NPM
/node_modules/
package-lock.json

# Build
/public/build/
/public/mix-manifest.json

# Testing
.phpunit.result.cache
/coverage/
.phpunit.cache/

# Debug
/storage/debugbar/
/storage/clockwork/

# Docker
/docker/volumes/

# Misc
*.log
npm-debug.log*
yarn-debug.log*
yarn-error.log*
Homestead.json
Homestead.yaml
auth.json
```

### Nümunə 2: .gitkeep ilə Boş Qovluq Saxlama

```bash
# Git boş qovluğu track etmir
# .gitkeep (və ya .gitignore) ilə qovluğu saxlayın

# storage qovluqlarını saxlayın
touch storage/app/.gitkeep
touch storage/framework/cache/.gitkeep
touch storage/framework/sessions/.gitkeep
touch storage/framework/views/.gitkeep
touch storage/logs/.gitkeep

# .gitignore-da:
storage/app/*
!storage/app/.gitkeep

storage/logs/*
!storage/logs/.gitkeep
```

### Nümunə 3: Negate (İstisna) Pattern-ləri

```bash
# Bütün .log fayllarını ignore et, amma...
*.log
!important.log       # Bu faylı track et

# build/ qovluğunu ignore et, amma...
build/
!build/index.html    # ⚠️ İŞLƏMİR! (parent ignore edilsə child negate olmur)

# Düzgün yol:
build/*              # build/ daxilindəki hər şeyi ignore et
!build/index.html    # Amma index.html-i track et

# İç-içə negate:
logs/
!logs/               # Əvvəlcə qovluğu geri al
logs/*               # Sonra içindəkiləri ignore et
!logs/error.log      # Amma error.log-u saxla
```

### Nümunə 4: Track Olunmuş Faylları Təmizləmə

```bash
# Ssenari: vendor/ yanlışlıqla commit edilib

# 1. .gitignore-a əlavə edin (əgər yoxdursa)
echo "/vendor/" >> .gitignore

# 2. Cache-dən silin
git rm -r --cached vendor/

# 3. Commit edin
git commit -m "chore: remove vendor/ from tracking, add to .gitignore"

# DİQQƏT: Bu, vendor/ qovluğunu disk-dən silmir
# Amma başqaları pull etdikdə onların vendor/ silinəcək
# Onlar composer install işlətməlidir
```

### Nümunə 5: Şərti İgnore (Əlavə Qovluqlarda)

```
project/
├── .gitignore           # Root ignore
├── app/
│   └── .gitignore       # App-specific ignore
├── docs/
│   └── .gitignore       # Docs-specific ignore
└── tests/
    └── .gitignore       # Tests-specific ignore
```

```bash
# tests/.gitignore
# Test coverage report-larını ignore et
/coverage/
/.phpunit.result.cache
*.html  # Coverage HTML report-ları
```

## Vizual İzah (Visual Explanation)

### Pattern Matching

```
Pattern: *.log

  ✓ error.log
  ✓ debug.log
  ✓ storage/logs/laravel.log
  ✗ error.txt
  ✗ logfile

Pattern: /vendor/

  ✓ vendor/              (root-da)
  ✗ packages/vendor/     (alt qovluqda)

Pattern: vendor/

  ✓ vendor/              (root-da)
  ✓ packages/vendor/     (alt qovluqda da!)

Pattern: **/logs

  ✓ logs/
  ✓ storage/logs/
  ✓ app/storage/logs/

Pattern: logs/**

  ✓ logs/error.log
  ✓ logs/2026/04/error.log
  ✗ app/logs/error.log
```

### .gitignore Prosessing Sırası

```
Git fayl status yoxlayarkən:

1. .git/info/exclude            (repo-specific, paylaşılmır)
       │ ignore?
       ▼
2. Alt qovluq .gitignore         (subdir/.gitignore)
       │ ignore?
       ▼
3. Root .gitignore               (.gitignore)
       │ ignore?
       ▼
4. Global gitignore              (~/.gitignore_global)
       │ ignore?
       ▼
5. Nəticə: tracked / ignored

Qeyd: Əgər fayl artıq TRACKED-dirsə, .gitignore İŞLƏMİR!
      Əvvəlcə git rm --cached lazımdır.
```

### Track vs Ignore Decision Tree

```
┌─────────────────────────────┐
│ Fayl nədir?                 │
└──────────┬──────────────────┘
           │
     ┌─────┴──────────────────────┐
     │                            │
  Mənbə kod,              Generated/temp,
  konfiqurasiya,           dependency, secret,
  asset                    IDE, OS faylları
     │                            │
     ▼                            ▼
  TRACK ET                   IGNORE ET
  (.gitignore-a             (.gitignore-a
   əlavə etmə)              əlavə et)

Nümunələr:
  TRACK: app/, config/, routes/, tests/, composer.json, package.json
  IGNORE: vendor/, node_modules/, .env, .idea/, .DS_Store, *.log
```

## PHP/Laravel Layihələrdə İstifadə

### Laravel Default .gitignore Faylları

```bash
# Root .gitignore (yuxarıdakı tam nümunəyə baxın)

# storage/app/.gitignore
*
!public/
!.gitignore

# storage/app/public/.gitignore
*
!.gitignore

# storage/framework/cache/.gitignore
*
!data/
!.gitignore

# storage/framework/cache/data/.gitignore
*
!.gitignore

# storage/framework/sessions/.gitignore
*
!.gitignore

# storage/framework/testing/.gitignore
*
!.gitignore

# storage/framework/views/.gitignore
*
!.gitignore

# storage/logs/.gitignore
*
!.gitignore

# bootstrap/cache/.gitignore
*
!.gitignore
```

### .env.example Strategiyası

```bash
# .env HEÇVAXT commit edilmir
# .env.example commit edilir (template kimi)

# .env.example
APP_NAME=MyApp
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=myapp
DB_USERNAME=root
DB_PASSWORD=

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025

# Yeni developer: cp .env.example .env && php artisan key:generate
```

### Composer auth.json

```bash
# Private package-lər üçün credentials
# auth.json HEÇVAXT commit edilmir!

# .gitignore
auth.json

# auth.json nümunəsi (lokal):
{
    "http-basic": {
        "satis.company.com": {
            "username": "token",
            "password": "secret-token-here"
        }
    }
}
```

### Docker-specific .gitignore

```bash
# Docker volumeları
docker/volumes/mysql/*
docker/volumes/redis/*
!docker/volumes/mysql/.gitkeep
!docker/volumes/redis/.gitkeep

# Docker override
docker-compose.override.yml
```

## Interview Sualları

### S1: .gitignore nədir və niyə lazımdır?

**Cavab**: `.gitignore`, Git-ə hansı faylları track etməməsini bildirən konfiqurasiya faylıdır. Lazımdır çünki:
- **Security**: `.env`, API key-lər, parollar commit edilməsin
- **Performance**: `vendor/`, `node_modules/` kimi böyük qovluqlar repo-nu şişirtməsin
- **Təmizlik**: IDE faylları, OS faylları, log-lar repo-nu çirkləndirməsin

### S2: Artıq track olunmuş faylı necə ignore edirsiniz?

**Cavab**: `.gitignore` yalnız untracked fayllara tətbiq olunur. Artıq track olunmuş faylı ignore etmək üçün:
```bash
git rm --cached filename    # Git-dən sil, diskdə saxla
echo "filename" >> .gitignore
git commit -m "chore: stop tracking filename"
```

### S3: `/vendor/` ilə `vendor/` arasındakı fərq nədir?

**Cavab**:
- `/vendor/`: Yalnız root qovluğundakı vendor/ ignore olunur
- `vendor/`: İstənilən dərinlikdəki vendor/ ignore olunur (root, subdir, subsubdir)

Slash (/) əvvəldə olduqda pattern root-a nisbət edilir.

### S4: Negate (`!`) pattern necə işləyir?

**Cavab**: `!` əvvəlki ignore rule-unu ləğv edir. Lakin vacib məhdudiyyət var: parent qovluq ignore edilibsə, daxilindəki faylı negate edə bilməzsiniz.
```bash
# İŞLƏYİR:
*.log
!important.log

# İŞLƏMİR:
build/
!build/index.html   # Parent ignore edilib

# Düzgün:
build/*
!build/index.html
```

### S5: Global .gitignore nə üçündür?

**Cavab**: Bütün repo-lara tətbiq olunan şəxsi ignore rule-larıdır. IDE faylları (`.idea/`, `.vscode/`) və OS faylları (`.DS_Store`, `Thumbs.db`) üçün idealdır. Repo-nun `.gitignore`-unu hər developer-in IDE seçimi ilə çirkləndirmək əvəzinə, hər kəs öz global gitignore-unu idarə edir.

### S6: `git check-ignore -v` nə edir?

**Cavab**: Faylın hansı `.gitignore` rule-u tərəfindən ignore edildiyini göstərir:
```bash
git check-ignore -v storage/logs/laravel.log
# .gitignore:15:storage/logs/*    storage/logs/laravel.log
```
Debug etmək üçün çox faydalıdır - niyə fayl track olunmur/olunur sualına cavab verir.

### S7: `.gitkeep` nədir?

**Cavab**: Git boş qovluqları track etmir. `.gitkeep` (Git tərəfindən xüsusi tanınmır, sadəcə konvensiya) boş qovluğu track etmək üçün yaradılan boş fayldır. Laravel-də `storage/logs/.gitkeep` kimi istifadə olunur ki, qovluq strukturu saxlansın.

### S8: `.gitignore` ilə `.git/info/exclude` arasındakı fərq nədir?

**Cavab**:
- `.gitignore`: Repo ilə paylaşılır (commit edilir). Komanda üçündür.
- `.git/info/exclude`: Yalnız lokaldır, paylaşılmır. Şəxsi ignore-lar üçündür.

Nümunə: Yalnız siz istifadə etdiyiniz debug script-i exclude-a əlavə edərsiniz.

## Best Practices

### 1. .gitignore-u Layihə Başlanğıcında Yaradın

```bash
# İlk commit-dən əvvəl!
# gitignore.io istifadə edin:
curl -sL https://www.toptal.com/developers/gitignore/api/laravel,phpstorm,visualstudiocode > .gitignore
```

### 2. .env-i Heç Vaxt Commit Etməyin

```bash
# .gitignore-da olmalıdır:
.env
.env.*
!.env.example
```

### 3. Global Gitignore-da IDE Faylları

```bash
# Repo .gitignore-u IDE-agnostic olmalıdır
# IDE fayllarını global gitignore-a qoyun
~/.gitignore_global:
  .idea/
  .vscode/
  *.swp
```

### 4. vendor/node_modules Heç Vaxt Commit Etməyin

```bash
# .gitignore:
/vendor/
/node_modules/

# Əvəzinə:
# composer.json + composer.lock commit edin
# package.json + package-lock.json commit edin
```

### 5. Həssas Faylları Yoxlayın

```bash
# Pre-commit hook-da yoxlayın:
STAGED=$(git diff --cached --name-only)
for PATTERN in ".env" "credentials" "secret" "password" "*.pem" "*.key"; do
    if echo "$STAGED" | grep -qi "$PATTERN"; then
        echo "WARNING: Sensitive file detected: $PATTERN"
        exit 1
    fi
done
```

### 6. `.gitignore` Template İstifadə Edin

```
GitHub-da hazır template-lər var:
  github.com/github/gitignore

Laravel: github.com/github/gitignore/blob/main/Laravel.gitignore
PHP:     github.com/github/gitignore/blob/main/PHP.gitignore
Node:    github.com/github/gitignore/blob/main/Node.gitignore
```
