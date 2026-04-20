# Fix / Patch / Hotfix / Workaround — Həll Tipləri

## Səviyyə
B1-B2 (tech interview)

---

## Əsas Cədvəl

| Söz | Nə deməkdir? | Nə vaxt? |
|-----|--------------|----------|
| **fix** | ümumi həll | bug düzəldilir |
| **patch** | kiçik yamaq | version incrementi |
| **hotfix** | təcili production fix | urgent, critical |
| **workaround** | müvəqqəti həll | əsl fix hazır olana qədər |
| **fixup** | git-də commit təmizləmə | git context |

> **Qısa qayda:**
> - **fix** = həll (ümumi)
> - **patch** = kod yamaqı / versiya
> - **hotfix** = təcili fix
> - **workaround** = müvəqqəti bypass

---

## 1. Fix — Ümumi Həll

Hər cür kod düzəlişinə deyilir.

### Fel və isim kimi

- Fel: I **fixed** the bug.
- İsim: The **fix** works.

### Nümunələr

- Can you **fix** this? (fel)
- The **fix** is in the PR. (isim)
- We need a quick **fix**.
- Bug **fix** deployment.
- **Fix** version 2.1.3.

### Kombinasiyalar

- **fix** a bug
- **fix** an issue
- **fix** the build
- **bug fix** = səhv düzəldilməsi

---

## 2. Patch — Yamaq

Kiçik, lokal düzəliş. Adətən versiyalama kontekstində.

### Nümunələr

- Security **patch** released.
- Apply the **patch** to production.
- **Patch** version 2.1.1 → 2.1.2.
- Monthly **patches** for Linux.
- Emergency **patch**.

### Semantic versioning

- **MAJOR.MINOR.PATCH**
  - 3.0.0 → major (breaking)
  - 3.1.0 → minor (feature)
  - 3.1.1 → **patch** (fix)

### Patch vs Fix

- **fix** = prosess (düzəltmək)
- **patch** = nəticə (kiçik versiya)

- "Apply the **patch**." (artıq yazılmış kod yamaqı)
- "Let's **fix** the bug." (prosess)

---

## 3. Hotfix — Təcili Production Fix

Production-da kritik problem → təcili fix. Normal cycle gözləmədən.

### Nümunələr

- We deployed a **hotfix** last night.
- **Hotfix** branch from main.
- Emergency **hotfix** needed.
- **Hotfix** released within 30 minutes.

### Git workflow

- **hotfix branch** → main-dən şaxalanır, main-ə qayıdır
- normal feature branch → develop-dan

### Hotfix vs Patch

- **patch** = kiçik, planlı versiya
- **hotfix** = təcili, plansız fix

---

## 4. Workaround — Müvəqqəti Həll

"Əsl həll" olmur, problemi yan yoldan keçir. Əsl fix planlaşdırılır.

### Nümunələr

- Here's a **workaround** until we fix it.
- Temporary **workaround**.
- Users found a **workaround**.
- We need a **workaround** for this bug.
- The **workaround** is to restart the service.

### Fix vs Workaround

- **fix** = problemi həll edir
- **workaround** = problemi yan keçir

- "The bug isn't fixed, but the **workaround** works." ✓
- "This is only a **workaround**; we'll fix properly next sprint." ✓

---

## 5. Bonus: Fixup (Git)

Git-də `--fixup` — commit-i sonradan əvvəlki commit ilə birləşdirmək.

- `git commit --fixup <hash>`
- `git rebase -i --autosquash`

Bu "fix" sözünün tam başqa istifadəsidir.

---

## Tez Test

Hansı söz daha uyğun?

1. A critical bug in prod — we need a ______ now. (təcili)
2. The bug isn't fixed yet, but here's a ______ . (müvəqqəti)
3. Security ______ version 3.1.2 released.
4. Can you ______ the failing test?
5. Monthly ______ update for iOS.

**Cavablar:** 1. hotfix, 2. workaround, 3. patch, 4. fix, 5. patch

---

## İnterview Kontekstində

- "I deployed a **hotfix** to resolve a production incident."
- "We released a security **patch** last week."
- "Users had a **workaround**, but we prioritized a proper **fix**."
- "Every sprint, we **fix** bugs and ship **patches**."

---

## Related Expressions

- **quick fix** = tez həll (bəzən "ideal deyil")
- **proper fix** = əsl həll
- **temporary fix** = müvəqqəti həll
- **permanent fix** = daimi həll
- **bug-fix release** = yalnız bug fix-lər üçün versiya

---

## Azərbaycanlı Səhvi

- ✗ This is a temporary fix. (doğru, amma daha dəqiq söz var)
- ✓ This is a **workaround**. (spesifik)

- ✗ Production bug — let's patch it.
- ✓ Production bug — let's **hotfix** it.

---

## Xatırlatma

| Söz | Bir sözdə |
|-----|-----------|
| **fix** | düzəlt |
| **patch** | kiçik yamaq |
| **hotfix** | təcili fix |
| **workaround** | bypass |

→ Related: [bug-defect-issue-incident.md](bug-defect-issue-incident.md), [deploy-ship-release-rollout.md](deploy-ship-release-rollout.md)
