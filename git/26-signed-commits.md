# Signed Commits (GPG və SSH Signing)

## Nədir? (What is it?)

**Signed commit** – kommitin həqiqətən də iddia edilən müəllif tərəfindən edildiyini kriptoqrafik olaraq təsdiqləyən imzalı kommitdir. Git default olaraq `user.name` və `user.email` dəyərlərini yoxlamır; istənilən şəxs başqa birinin adı ilə kommit edə bilər. Signed commit bu problemi həll edir.

**Niyə vacibdir?**
- **Authenticity (Həqiqilik):** Kommiti yalnız gizli açara sahib şəxs imzalaya bilər.
- **Supply chain security:** npm, Composer, Docker kimi paketlərin mənbə kodunun doğruluğunu təmin edir.
- **Compliance:** SOC2, ISO 27001 kimi auditlərdə tələb olunur.
- **Verified badge:** GitHub/GitLab profilində "Verified" nişanı görünür.

**İki əsas metod:**
1. **GPG signing** – klassik yanaşma, GnuPG istifadə edir.
2. **SSH signing** (Git 2.34+) – SSH açarları ilə daha sadə imzalama.
3. **S/MIME (X.509)** – korporativ mühitlərdə istifadə olunur.

---

## Əsas Əmrlər (Key Commands)

### GPG açar idarəetməsi
```bash
# GPG açar yaratmaq
gpg --full-generate-key

# GPG açarları siyahısı
gpg --list-secret-keys --keyid-format=long

# Public açarı ixrac etmək (GitHub üçün)
gpg --armor --export <KEY_ID>

# Açarı silmək
gpg --delete-secret-keys <KEY_ID>
gpg --delete-keys <KEY_ID>
```

### Git konfiqurasiyası (GPG)
```bash
# İmzalama açarını təyin etmək
git config --global user.signingkey <KEY_ID>

# Bütün kommitləri avtomatik imzalamaq
git config --global commit.gpgsign true

# Bütün taqları avtomatik imzalamaq
git config --global tag.gpgSign true

# GPG proqramının yolunu təyin etmək (macOS üçün)
git config --global gpg.program $(which gpg)
```

### SSH signing (Git 2.34+)
```bash
# Format olaraq SSH seçmək
git config --global gpg.format ssh

# SSH açarını imzalama açarı kimi göstərmək
git config --global user.signingkey ~/.ssh/id_ed25519.pub

# Avtomatik imzalamaq
git config --global commit.gpgsign true

# Allowed signers faylı (yerli doğrulama üçün)
git config --global gpg.ssh.allowedSignersFile ~/.ssh/allowed_signers
```

### İmzalama və doğrulama
```bash
# Tək kommiti imzalamaq
git commit -S -m "feat: add user authentication"

# Taqı imzalamaq
git tag -s v1.0.0 -m "Release v1.0.0"

# İmzanı yoxlamaq
git log --show-signature
git verify-commit HEAD
git verify-tag v1.0.0
```

---

## Praktiki Nümunələr (Practical Examples)

### Nümunə 1: GPG açarının yaradılması (addım-addım)

```bash
$ gpg --full-generate-key

Please select what kind of key you want:
   (1) RSA and RSA (default)
   (9) ECC and ECC
Your selection? 9

Please select which elliptic curve you want:
   (1) Curve 25519
Your selection? 1

Key is valid for? (0) 2y   # 2 il
Real name: Orkhan Shukurlu
Email address: orkhan@example.com
Comment: Work signing key
```

Sonra:
```bash
$ gpg --list-secret-keys --keyid-format=long
sec   ed25519/ABCD1234EF567890 2026-04-17 [SC] [expires: 2028-04-17]
      1234567890ABCDEF1234567890ABCDEF12345678
uid   Orkhan Shukurlu <orkhan@example.com>
```

`ABCD1234EF567890` – bizim KEY_ID-dir.

### Nümunə 2: GitHub-a GPG açarı əlavə etmək

```bash
# Public açarı klipborda kopyalamaq
gpg --armor --export ABCD1234EF567890 | xclip -selection clipboard

# Nəticə şəklində:
# -----BEGIN PGP PUBLIC KEY BLOCK-----
# mQENBGA...
# -----END PGP PUBLIC KEY BLOCK-----
```

Sonra GitHub → Settings → SSH and GPG keys → New GPG key.

### Nümunə 3: SSH signing setup (daha sadə variant)

```bash
# Mövcud SSH açarını istifadə etmək
git config --global gpg.format ssh
git config --global user.signingkey ~/.ssh/id_ed25519.pub
git config --global commit.gpgsign true

# Test kommit
cd ~/projects/my-laravel-app
git commit --allow-empty -m "test: verify SSH signing"
git log --show-signature -1
```

Nəticə:
```
commit 7a8b9c... (HEAD -> main)
Good "git" signature for orkhan@example.com with ED25519 key SHA256:...
Author: Orkhan Shukurlu <orkhan@example.com>
    test: verify SSH signing
```

### Nümunə 4: Allowed signers faylı

```bash
# ~/.ssh/allowed_signers
orkhan@example.com ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAI...
teammate@example.com ssh-ed25519 AAAAC3NzaC1lZDI1NTE5BBBB...
```

Bu fayl yerli olaraq kommitlərin imzasını yoxlamaq üçün istifadə olunur.

### Nümunə 5: Artıq edilmiş kommiti imzalamaq

```bash
# Son kommiti yenidən imzalamaq
git commit --amend --no-edit -S

# Son 5 kommiti imzalamaq (interactive rebase)
git rebase -i HEAD~5
# Hər kommiti "edit" kimi qeyd edin, sonra:
git commit --amend --no-edit -S
git rebase --continue
```

---

## Vizual İzah (Visual Explanation)

### GPG signing iş prinsipi

```
  Developer                    Git Repository               GitHub/GitLab
 ┌──────────┐                ┌──────────────┐             ┌──────────────┐
 │ Private  │                │              │             │ Public GPG   │
 │ GPG Key  │──sign(commit)─>│ Signed       │──push──────>│ Key Registry │
 └──────────┘                │ Commit       │             └──────────────┘
                             │ + signature  │                    │
                             └──────────────┘                    │
                                                                 v
                                                          ┌──────────────┐
                                                          │ Verify with  │
                                                          │ public key   │
                                                          └──────────────┘
                                                                 │
                                                                 v
                                                          [Verified] badge
```

### Signed vs Unsigned commit

```
Unsigned commit:
┌─────────────────────────────────────┐
│ commit 7a8b9c                       │
│ Author: Anyone Can Fake <f@k.e>     │  <-- doğrulanmamış
│ Date: ...                           │
│                                     │
│     feat: malicious code injection  │
└─────────────────────────────────────┘

Signed commit:
┌─────────────────────────────────────┐
│ commit 7a8b9c                       │
│ gpg: Good signature from "Orkhan"   │  <-- kriptoqrafik təsdiq
│ Author: Orkhan <orkhan@example.com> │
│ Date: ...                           │
│                                     │
│     feat: add authentication        │
└─────────────────────────────────────┘
```

### Signing workflow

```
  git commit -S
       │
       v
  ┌─────────────┐    ┌──────────────┐    ┌──────────────┐
  │ Commit      │───>│ Hash (SHA-1) │───>│ Sign with    │
  │ content     │    │ of content   │    │ private key  │
  └─────────────┘    └──────────────┘    └──────────────┘
                                                │
                                                v
                                        ┌──────────────┐
                                        │ Store        │
                                        │ signature in │
                                        │ commit object│
                                        └──────────────┘
```

---

## PHP/Laravel Layihələrdə İstifadə

### Team policy: bütün kommitlər imzalanmalıdır

`CONTRIBUTING.md` faylında:
```markdown
## Commit Signing Policy

Bütün kommitlər GPG və ya SSH ilə imzalanmalıdır:

1. GPG/SSH açar yaradın
2. GitHub-a əlavə edin
3. `git config --global commit.gpgsign true` təyin edin
4. PR-lərdə "Verified" badge görünməlidir
```

### CI/CD-də imza yoxlaması

`.github/workflows/verify-signatures.yml`:
```yaml
name: Verify Commit Signatures

on: [pull_request]

jobs:
  verify:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
        with:
          fetch-depth: 0

      - name: Check all commits signed
        run: |
          UNSIGNED=$(git log origin/main..HEAD --pretty=format:"%H %G?" | grep -v " G$" || true)
          if [ -n "$UNSIGNED" ]; then
            echo "Unsigned commits found:"
            echo "$UNSIGNED"
            exit 1
          fi
```

`%G?` dəyərləri:
- `G` – good signature
- `B` – bad signature
- `U` – good but untrusted
- `N` – no signature

### Laravel release taglarını imzalamaq

```bash
# Production release
cd ~/projects/laravel-shop
git tag -s v2.1.0 -m "Release v2.1.0

Features:
- Payment gateway integration
- Admin dashboard

Bug fixes:
- Cart calculation issue
"

git push origin v2.1.0

# Composer paketiniz varsa
git verify-tag v2.1.0
```

### Pre-push hook – imzasız kommitləri bloklamaq

`.git/hooks/pre-push`:
```bash
#!/usr/bin/env bash
while read local_ref local_sha remote_ref remote_sha; do
    unsigned=$(git log --pretty="%H %G?" "$remote_sha..$local_sha" | grep -v " G$" || true)
    if [ -n "$unsigned" ]; then
        echo "Error: unsigned commits detected. Sign with 'git commit --amend -S'"
        exit 1
    fi
done
```

---

## Interview Sualları (Q&A)

### Q1: Niyə signed commit lazımdır? Git artıq author məlumatlarını saxlayır axı.

**Cavab:** Git-də `user.email` və `user.name` sadəcə mətn sahələridir və istənilən şəxs tərəfindən dəyişdirilə bilər. Məsələn:
```bash
git -c user.name="Linus Torvalds" -c user.email="linus@kernel.org" commit -m "..."
```
Bu, Linus-un adı ilə kommit yaradır. Signed commit isə kriptoqrafik açarla təsdiqlənir – yalnız gizli açara sahib şəxs belə kommit yarada bilər. Supply chain attack-lardan (məsələn, SolarWinds) qorunmaq üçün kritikdir.

### Q2: GPG və SSH signing arasında fərq nədir?

**Cavab:**
| GPG | SSH (Git 2.34+) |
|-----|-----------------|
| GnuPG proqramı lazımdır | SSH açarı kifayətdir |
| Açar web of trust ilə verify olunur | Allowed signers faylı lazımdır |
| Tarixən standart | Daha sadə setup |
| Daha geniş tool dəstəyi | Git-dən xaricdə məhdud |

SSH signing daha sadədir, çünki çoxları artıq SSH açarına sahibdir. Ancaq CI/CD-də GPG hələ də daha çox dəstəklənir.

### Q3: "Verified" badge GitHub-da necə görünür?

**Cavab:** GitHub imzanı yoxlayır:
1. Commit-dəki GPG/SSH imzasını alır.
2. İstifadəçinin GitHub profilindəki public açarlarla müqayisə edir.
3. Commit author email-i açarın identity-si ilə uyğun gəlirsə → **Verified**.
4. Əks halda → **Unverified** və ya göstərilmir.

### Q4: GPG açarımı itirdim. Nə etməliyəm?

**Cavab:**
1. **Revocation certificate** varsa – onu GitHub-dan silin və açarı ləğv edin.
2. Yeni açar yaradın və GitHub-a əlavə edin.
3. Əvvəlki kommitlər "Unverified" olacaq, amma onlar tarixdə qalır.
4. Gələcək üçün: açar yaradarkən həmişə revocation certificate saxlayın:
```bash
gpg --output revoke.asc --gen-revoke <KEY_ID>
```

### Q5: CI-də imzaları necə yoxlayırsan?

**Cavab:** `git log --pretty="%G?"` formatı istifadə edirəm:
```bash
git log origin/main..HEAD --pretty="%H %G?" | grep -v " G$"
```
`G` – good signature deməkdir. Başqa hərf varsa PR bloklanır. GitHub Actions-da branch protection rules ilə "Require signed commits" seçimi də aktivləşdirilə bilər.

### Q6: Commit imzalandıqdan sonra dəyişdirilə bilərmi?

**Cavab:** Xeyr. Commit-in SHA-1 hash-i onun məzmununa (tree, parent, author, message) əsasən hesablanır. İmza bu hash-i imzalayır. Hər hansı dəyişiklik hash-i dəyişəcək və imza etibarsız olacaq. Ancaq `git commit --amend` yeni kommit yaradır – köhnə kommit tarixdə qalır (reflog-da).

### Q7: Eyni layihədə imzalanmış və imzalanmamış kommitlər ola bilərmi?

**Cavab:** Bəli, Git bunu qadağan etmir. Amma branch protection rules ilə "Require signed commits" aktivləşdirilə bilər – bu halda imzasız kommit main-ə push edilə bilməz. Legacy layihələr üçün çox vaxt müəyyən tarixdən sonra bu qayda tətbiq edilir.

### Q8: GPG açarı expire oldu. Əvvəlki kommitlər nə olur?

**Cavab:** Əvvəlki kommitlər etibarlı qalır, çünki imza o vaxtkı etibarlı açarla edilib. GitHub/GitLab ümumiyyətlə imzanın edildiyi vaxtı yoxlayır, `expire_date`-dən əvvəldirsə etibarlıdır. Ancaq yeni kommitlər üçün açarı yeniləmək lazımdır:
```bash
gpg --edit-key <KEY_ID>
> expire
> 2y
> save
```

### Q9: Subkeys nədir və niyə istifadə olunur?

**Cavab:** GPG primary key-dən başqa subkeys yarada bilər:
- **[S]** – signing
- **[E]** – encryption
- **[A]** – authentication

Master key yalnız yeni subkey imzalamaq üçün istifadə olunur və offline saxlanılır (məsələn, YubiKey və ya şifrlənmiş USB). Subkey gündəlik istifadə üçündür. Subkey kompromat olarsa, yalnız onu revoke edirsən, master key toxunulmaz qalır.

### Q10: Commit imzalamağın performance təsiri varmı?

**Cavab:** Minimal. Hər imzalama ~50-100ms əlavə edir. Böyük layihələrdə (məsələn, 1000 kommit cherry-pick) bu hiss oluna bilər. Həll: batch əməliyyatlar üçün müvəqqəti `commit.gpgsign false` edib, final mergedən əvvəl imzalamaq.

---

## Best Practices

1. **Həmişə ED25519 istifadə edin** (RSA-dan daha sürətli və güvənli):
   ```bash
   gpg --full-generate-key  # sonra "ECC and ECC" → "Curve 25519"
   ```

2. **Açar expiry təyin edin** (məsələn, 2 il). Bu açar sızarsa təsiri məhdudlaşdırır.

3. **Revocation certificate saxlayın**:
   ```bash
   gpg --output ~/secure/revoke.asc --gen-revoke <KEY_ID>
   ```

4. **Hardware security key istifadə edin** (YubiKey, Nitrokey). Private key heç vaxt diskdə saxlanmır.

5. **Git konfiqurasiyasında avtomatik imzalamaq**:
   ```bash
   git config --global commit.gpgsign true
   git config --global tag.gpgSign true
   ```

6. **Branch protection rule aktivləşdirin** (GitHub/GitLab): "Require signed commits".

7. **Emailləri uyğunlaşdırın**: GPG açarındakı email Git config-dəki email ilə və GitHub-dakı verified email ilə eyni olmalıdır.

8. **Çoxlu mühit üçün ayrı açarlar**: İş üçün bir, şəxsi layihələr üçün başqa açar. Bu, rol bölgüsünü asanlaşdırır.

9. **Key backup strategiyası**: Master key-i encrypted USB-də və fiziki seyfdə saxlayın. Subkeys-i laptopda.

10. **Team-də onboarding sənədi olmalıdır**: Yeni developer qoşulan kimi açar yaratmalı və setup-u tamamlamalıdır. Bunu CONTRIBUTING.md-də sənədləşdirin.

11. **SSH signing-i yeni layihələrdə tövsiyə edin**: GPG-dən sadədir və artıq SSH key-i olan hər kəs üçün dərhal istifadəyə hazırdır.

12. **CI/CD-də bot kommitlərini istisna edin**: Dependabot, Renovate kimi botlar fərqli qaydalarla işləyir – onları allow list-ə əlavə edin.
