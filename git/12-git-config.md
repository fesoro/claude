# Git Config (Middle)

## İcmal

Git config, Git-in davranışını idarə edən konfiqurasiya sistemidir. Üç səviyyəsi var: system (bütün istifadəçilər), global (cari istifadəçi), local (cari repo). Daha spesifik səviyyə daha ümumi səviyyəni override edir.

```
Konfiqurasiya Səviyyələri (prioritet sırası):

┌─────────────────────────────────┐
│ Local  (.git/config)            │ ← Ən yüksək prioritet
│ Yalnız bu repo üçün             │
├─────────────────────────────────┤
│ Global (~/.gitconfig)           │ ← Orta prioritet
│ Bu istifadəçi üçün              │
├─────────────────────────────────┤
│ System (/etc/gitconfig)         │ ← Ən aşağı prioritet
│ Bütün istifadəçilər üçün        │
└─────────────────────────────────┘

Local > Global > System (override sırası)
```

## Niyə Vacibdir

Komanda üzvləri arasında standart editor, diff tool, EOL, merge strategy konfiqurasiyası olmadan hər developer fərqli davranışla işləyir. Alias-lar gündəlik produktivliyi artırır; global settings yeni maşına keçiddə vaxt qənaət edir. İş və şəxsi layihələr üçün ayrı identity saxlamaq isə professional mühitdə mütləq tələbdir.

## Əsas Əmrlər (Key Commands)

### Config Oxuma/Yazma

```bash
# Config dəyərini oxu
git config user.name
git config user.email

# Bütün config-ləri göstər
git config --list

# Hansı fayl-dan gəldiyini göstər
git config --list --show-origin

# Spesifik səviyyədən oxu
git config --global user.name
git config --local user.name
git config --system core.editor

# Global config yaz
git config --global user.name "Orkhan Shukurlu"
git config --global user.email "claude-orkhan@zipmend.com"

# Local config yaz (yalnız bu repo)
git config --local user.email "orkhan@company.com"

# Config sil
git config --global --unset alias.lg
```

### Əsas Konfiqurasiyalar

```bash
# İstifadəçi məlumatları
git config --global user.name "Orkhan Shukurlu"
git config --global user.email "claude-orkhan@zipmend.com"

# Default branch adı
git config --global init.defaultBranch main

# Editor
git config --global core.editor "vim"
# VS Code:
git config --global core.editor "code --wait"
# Nano:
git config --global core.editor "nano"

# Line ending
# Windows:
git config --global core.autocrlf true
# macOS/Linux:
git config --global core.autocrlf input

# Rəngli output
git config --global color.ui auto

# Push davranışı
git config --global push.default current
git config --global push.autoSetupRemote true
git config --global push.followTags true

# Pull davranışı
git config --global pull.rebase true

# Merge tool
git config --global merge.tool vscode
git config --global mergetool.vscode.cmd 'code --wait --merge $REMOTE $LOCAL $BASE $MERGED'

# Diff tool
git config --global diff.tool vscode
git config --global difftool.vscode.cmd 'code --wait --diff $LOCAL $REMOTE'
```

## Nümunələr

### Nümunə 1: Tam .gitconfig Nümunəsi

```ini
# ~/.gitconfig

[user]
    name = Orkhan Shukurlu
    email = claude-orkhan@zipmend.com

[init]
    defaultBranch = main

[core]
    editor = vim
    autocrlf = input
    whitespace = fix
    pager = delta  # git-delta istifadə edirsinizsə

[color]
    ui = auto

[push]
    default = current
    autoSetupRemote = true
    followTags = true

[pull]
    rebase = true

[fetch]
    prune = true

[merge]
    conflictstyle = diff3
    tool = vscode

[diff]
    algorithm = histogram
    colorMoved = default
    tool = vscode

[rebase]
    autoSquash = true
    autoStash = true

[rerere]
    enabled = true

[alias]
    # Log
    lg = log --oneline --graph --all --decorate
    last = log --oneline -10
    today = log --oneline --since='00:00:00' --no-merges

    # Status
    st = status -sb
    
    # Branch
    br = branch
    bra = branch -a
    brd = branch -d
    
    # Checkout
    co = checkout
    cob = checkout -b
    
    # Commit
    ci = commit
    cm = commit -m
    ca = commit --amend --no-edit
    
    # Diff
    df = diff
    dfs = diff --staged
    
    # Stash
    sl = stash list
    sp = stash pop
    ss = stash push -m
    
    # Reset
    unstage = reset HEAD --
    undo = reset --soft HEAD~1
    
    # Clean
    cleanup = "!git branch --merged main | grep -v main | xargs -n 1 git branch -d"
    
    # Who
    who = shortlog -s -n --all
    blame-line = blame -w -C

[url "git@github.com:"]
    insteadOf = https://github.com/

[credential]
    helper = store
    # macOS: helper = osxkeychain
    # Linux: helper = /usr/lib/git-core/git-credential-libsecret
```

### Nümunə 2: Fərqli Layihələr üçün Fərqli Config

```bash
# Şəxsi layihələr üçün global config istifadə olunur
# ~/.gitconfig
[user]
    name = Orkhan Shukurlu
    email = personal@email.com

# İş layihəsi üçün local config
cd ~/work/company-project
git config --local user.email "orkhan@company.com"

# Conditional includes (Git 2.13+)
# ~/.gitconfig
[includeIf "gitdir:~/work/"]
    path = ~/.gitconfig-work

[includeIf "gitdir:~/personal/"]
    path = ~/.gitconfig-personal
```

```ini
# ~/.gitconfig-work
[user]
    email = orkhan@company.com
    signingkey = COMPANY_GPG_KEY

[core]
    sshCommand = ssh -i ~/.ssh/company_key
```

```ini
# ~/.gitconfig-personal
[user]
    email = personal@email.com
    signingkey = PERSONAL_GPG_KEY
```

### Nümunə 3: Faydalı Alias-lar

```bash
# Merge olunmuş branch-ləri sil
git config --global alias.cleanup '!git branch --merged main | grep -v main | xargs -n 1 git branch -d'

# Son commit-i undo et (dəyişikliklər staged qalır)
git config --global alias.undo 'reset --soft HEAD~1'

# Bütün dəyişiklikləri unstage et
git config --global alias.unstage 'reset HEAD --'

# WIP commit (sürətli saxlama)
git config --global alias.wip '!git add -A && git commit -m "WIP: work in progress"'

# WIP-i undo et
git config --global alias.unwip '!git log -1 --format="%s" | grep -q "^WIP" && git reset HEAD~1'

# İnteraktiv rebase (son N commit)
git config --global alias.ri '!f() { git rebase -i HEAD~$1; }; f'
# İstifadə: git ri 5

# Branch-ı remote ilə sync et
git config --global alias.sync '!git fetch --prune && git pull --rebase'
```

### Nümunə 4: GPG İmzalama

```bash
# GPG key setup
gpg --gen-key
gpg --list-secret-keys --keyid-format=long
# sec   rsa4096/ABC1234DEF5678 2026-04-16

git config --global user.signingkey ABC1234DEF5678
git config --global commit.gpgsign true
git config --global tag.gpgsign true

# İmzalı commit
git commit -S -m "feat: add signed feature"

# İmzanı yoxla
git verify-commit HEAD
git log --show-signature
```

## Vizual İzah (Visual Explanation)

### Config Override Mexanizmi

```
Sual: user.email nədir?

Git yoxlayır (aşağıdan yuxarı):

1. .git/config (local)
   user.email = orkhan@company.com  ← TAPILDI! Bu istifadə olunur.

2. ~/.gitconfig (global)
   user.email = personal@email.com  ← Override edildi

3. /etc/gitconfig (system)
   (ayarlanmayıb)

Nəticə: orkhan@company.com
```

### Config Fayllarının Yeri

```
Linux/macOS:
  System:  /etc/gitconfig
  Global:  ~/.gitconfig (və ya ~/.config/git/config)
  Local:   .git/config

Windows:
  System:  C:\Program Files\Git\etc\gitconfig
  Global:  C:\Users\<name>\.gitconfig
  Local:   .git/config
```

### Conditional Include

```
~/                                  ~/.gitconfig
├── work/                          [includeIf "gitdir:~/work/"]
│   ├── project-1/.git/  ──────>     path = ~/.gitconfig-work
│   └── project-2/.git/  ──────>     (orkhan@company.com)
│
├── personal/                      [includeIf "gitdir:~/personal/"]
│   ├── blog/.git/  ────────────>     path = ~/.gitconfig-personal
│   └── oss/.git/   ────────────>     (personal@email.com)
```

## Praktik Baxış

### Laravel Layihə Config (.git/config)

```ini
# .git/config

[core]
    hooksPath = hooks

[diff "composer-lock"]
    textconv = "php -r 'echo json_encode(json_decode(file_get_contents($argv[1]), true)[\"packages\"], JSON_PRETTY_PRINT);'"
    # composer.lock diff-ini oxunaqlı edir
```

### .gitattributes (Config ilə əlaqəli)

```
# .gitattributes
* text=auto eol=lf

# PHP faylları
*.php text diff=php

# Composer lock
composer.lock -diff
# və ya oxunaqlı diff:
# composer.lock diff=composer-lock

# Binary fayllar
*.png binary
*.jpg binary
*.gif binary
*.woff2 binary

# Export ignore (git archive zamanı daxil etmə)
/.github export-ignore
/tests export-ignore
/phpunit.xml export-ignore
/.php-cs-fixer.dist.php export-ignore
/phpstan.neon export-ignore
```

### Komanda üçün Standart Config

```bash
# Yeni developer layihəyə qoşulduqda:

# 1. Clone
git clone git@github.com:company/project.git
cd project

# 2. Hooks setup (core.hooksPath sayəsində avtomatik)
# hooks/ qovluğu repo-dadır

# 3. Composer install (post-install hook-lar işləyir)
composer install

# 4. Local config (əgər fərqli email lazımdırsa)
git config --local user.email "name@company.com"
```

### Git Delta (Gözəl Diff)

```bash
# Delta quraşdırma
# macOS: brew install git-delta
# Ubuntu: apt install git-delta

# ~/.gitconfig
[core]
    pager = delta

[interactive]
    diffFilter = delta --color-only

[delta]
    navigate = true
    light = false
    side-by-side = true
    line-numbers = true
    syntax-theme = Dracula
```

## Praktik Tapşırıqlar

1. **Əsas konfiqurasiya**
   ```bash
   git config --global user.name "Orkhan"
   git config --global user.email "dev@company.com"
   git config --global core.editor "code --wait"
   git config --global pull.rebase true
   git config --global init.defaultBranch main
   ```

2. **Faydalı alias-lar qur**
   ```bash
   git config --global alias.st "status -s"
   git config --global alias.co "checkout"
   git config --global alias.lg "log --oneline --graph --all"
   git config --global alias.undo "reset --soft HEAD~1"
   git config --global alias.amend "commit --amend --no-edit"
   ```

3. **Local repo üçün ayrı identity**
   ```bash
   cd ~/work/client-project
   git config user.email "me@client.com"
   git config user.name "Orkhan (Client)"
   git config --list --local  # yoxla
   ```

4. **Diff tool qur**
   ```bash
   git config --global diff.tool vscode
   git config --global difftool.vscode.cmd 'code --wait --diff $LOCAL $REMOTE'
   git difftool HEAD~1 HEAD
   ```

## Interview Sualları

### S1: Git config-in üç səviyyəsi nədir?

**Cavab**:
1. **System** (`/etc/gitconfig`): Bütün istifadəçilər üçün
2. **Global** (`~/.gitconfig`): Cari istifadəçi üçün
3. **Local** (`.git/config`): Yalnız cari repo üçün

Prioritet: Local > Global > System. Daha spesifik səviyyə ümumini override edir.

### S2: Fərqli layihələrdə fərqli email istifadə etmək üçün nə edərdiniz?

**Cavab**: İki yol var:
1. **Local config**: Hər repo-da `git config --local user.email "work@company.com"`
2. **Conditional includes**: `~/.gitconfig`-da `[includeIf "gitdir:~/work/"]` istifadə edərək iş qovluğundakı bütün repo-lara avtomatik iş emaili tətbiq etmək.

### S3: `push.default current` nə deməkdir?

**Cavab**: `push.default` push zamanı default davranışı təyin edir:
- **current**: Cari branch-i eyni adlı remote branch-ə push edir
- **simple** (default): current kimi, amma upstream set olunmalıdır
- **matching**: Eyni adlı bütün branch-ləri push edir (təhlükəli)
- **nothing**: Heç nə push etmir, açıq göstərmək lazım

### S4: `pull.rebase true` nə edir?

**Cavab**: `git pull` zamanı default olaraq merge əvəzinə rebase istifadə edir. Yəni `git pull` = `git pull --rebase` olur. Bu, təmiz xətti tarixçə yaradır və gereksiz merge commit-lərin qarşısını alır.

### S5: Git alias nə üçün faydalıdır?

**Cavab**: Tez-tez istifadə olunan uzun əmrləri qısaltmaq üçün. Nümunə:
```bash
git config --global alias.lg "log --oneline --graph --all"
# Artıq git lg yetərlidir
```
Shell alias-dan fərqli olaraq Git alias Git kontekstindədir və komanda ilə paylaşıla bilər.

### S6: `fetch.prune true` nə edir?

**Cavab**: `git fetch` zamanı remote-da silinmiş branch-lərin lokal tracking referanslarını avtomatik silir. Fetch.prune olmadan, remote-da silinmiş branch-in lokal tracking-i qalır və `git branch -r` çıxışını çirkləndirir.

### S7: `rerere` nədir?

**Cavab**: "REuse REcorded REsolution" - əvvəl həll etdiyiniz merge conflict-ləri yadda saxlayır və eyni conflict təkrar baş verdikdə avtomatik həll edir. `git config --global rerere.enabled true` ilə aktivləşdirilir.

### S8: `.gitattributes` nə üçündür?

**Cavab**: Fayl atributlarını təyin edir: line ending (`text=auto eol=lf`), diff strategiyası (`*.php diff=php`), merge strategiyası, binary fayllar (`*.png binary`), export ignore və s. Repo ilə paylaşılır (`.git/config`-dən fərqli).

## Best Practices

### 1. Global Config-i Erkən Qurun

```bash
# İlk işlər
git config --global user.name "Adınız"
git config --global user.email "email@example.com"
git config --global init.defaultBranch main
git config --global pull.rebase true
git config --global push.autoSetupRemote true
git config --global fetch.prune true
git config --global rerere.enabled true
```

### 2. Conditional Includes İstifadə Edin

```ini
# İş/şəxsi email-i avtomatik ayırmaq üçün
[includeIf "gitdir:~/work/"]
    path = ~/.gitconfig-work
```

### 3. Alias-ları Sənədləşdirin

```bash
# Alias-ları görmək üçün öz alias-ınızı yaradın
git config --global alias.aliases "config --get-regexp ^alias\."
# İstifadə: git aliases
```

### 4. `.gitattributes`-u Repo-ya Əlavə Edin

```
# Hər layihəyə .gitattributes əlavə edin
* text=auto eol=lf
*.php text diff=php
```

### 5. Credential Helper İstifadə Edin

```bash
# Password-u hər dəfə yazmamaq üçün
# Linux:
git config --global credential.helper store
# macOS:
git config --global credential.helper osxkeychain
# SSH key istifadəsi ən yaxşısıdır
```

### 6. Config-i Backup Edin

```bash
# ~/.gitconfig-i dotfiles repo-suna əlavə edin
cp ~/.gitconfig ~/dotfiles/gitconfig
# Symlink:
ln -sf ~/dotfiles/gitconfig ~/.gitconfig
```

## Əlaqəli Mövzular

- [04-gitignore.md](04-gitignore.md) — global gitignore konfiqurasiyası
- [18-git-hooks.md](18-git-hooks.md) — hook-ları konfiqurasiya etmək
- [24-signed-commits.md](24-signed-commits.md) — GPG signing konfiqurasiyası
