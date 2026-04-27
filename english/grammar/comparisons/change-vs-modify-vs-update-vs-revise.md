# Change / Modify / Update / Revise — Dəyişdirmək Fellər

## Səviyyə
B1-B2

---

## Əsas Cədvəl

| Söz | Nədir? | Tam/Hissəvi? |
|-----|--------|--------------|
| **change** | ümumi dəyişiklik | tam və ya hissəvi |
| **modify** | hissəsini dəyiş | hissəvi (targeted) |
| **update** | yeni versiyaya gətir | yenilik əlavə et |
| **revise** | nəzərdən keçirib dəyiş | sənəd / plan |

> **Qısa qayda:**
> - **change** = dəyiş (ümumi, tam və ya hissəvi)
> - **modify** = bir hissəsini düzəlt (orijinal qalır)
> - **update** = cari versiyaya gətir (köhnəni yenilə)
> - **revise** = oxuyub nəzərdən keçir, sonra dəyiş

---

## 1. Change — Ümumi Dəyişiklik

Ən geniş söz. Kiçik düzəliş də, tam transformasiya da ola bilər.

### Nümunələr

- **Change** your password.
- The client wants to **change** the entire UI.
- Things **change** over time.
- **Change** the design from scratch.
- We need to **change** our approach.

### Kontekst

- Tam transformasiya: "**change** the architecture"
- Kiçik şey: "**change** a variable name"
- Azərbaycanca: "dəyişmək" — ən yaxın

### Commit mesajlarında

- "**change** default timeout to 30s" ✓ (broad, neytral)

---

## 2. Modify — Hissəsini Dəyiş (Targeted)

Orijinal saxlanılır, yalnız bir hissəsi dəyişdirilir. Məqsədli, cərrahi dəyişiklik.

### Nümunələr

- **Modify** the function signature.
- **Modify** the config file.
- **Modify** the request before sending.
- I'll **modify** the query to add pagination.
- We **modified** the middleware behavior.

### Əsas fərq: Change vs Modify

- **change** = tam da ola bilər, hissəvi də
- **modify** = mütləq hissəvi, orijinal qorunur

- "**Change** the function." (tam yenidən yaza bilərsən)
- "**Modify** the function." (mövcudu düzəldirsən)

### Tech kontekst — çox istifadə olunur

- **Modify** behavior via config.
- **Modified** files in git status.
- **Modifier** key (Shift, Ctrl).
- HTTP **method** ≠ modify (fərq!)

### Commit mesajlarında

- "**modify** auth middleware to support JWT" ✓

---

## 3. Update — Yeni Versiyaya Gətir

Köhnəni, artıq aktual olmayanı cari hala gətirmək. Yenilik əlavə etmək.

### Nümunələr

- **Update** the npm packages.
- **Update** the documentation.
- **Update** the user record in the database.
- **Update** the library to v3.2.
- Please **update** your profile.

### Əsas fikir: "Köhnəlmişdi → İndi caridir"

- **update** = "artıq yeni version var, gəl onu işlət"
- **update** = "məlumat dəyişdi, bazada da dəyiş"

### Tech kontekst

- `UPDATE` SQL command.
- **Update** a dependency.
- **Update** the cache.
- Software **update** / OS **update**.
- CRUD-da: Create, Read, **Update**, Delete.

### Modify vs Update

- **modify** = hissəsini dəyiş (orijinala nəzərən)
- **update** = cariləşdir (köhnəyə nəzərən)

- "**Modify** the function logic." (kod dəyişikliyi)
- "**Update** the API version." (köhnə → yeni)

### Commit mesajlarında

- "**update** readme with new env vars" ✓
- "**update** Laravel to 11.x" ✓

---

## 4. Revise — Nəzərdən Keçirib Dəyiş

Oxu → düşün → düzəlt. Sənəd, plan, qiymətləndirmə üçün.

### Nümunələr

- **Revise** the deployment plan.
- **Revise** the estimates — they were too optimistic.
- A **revised** proposal has been sent.
- Let me **revise** my approach.
- The document needs to be **revised**.

### Əsas fərq: Revise digərlərindən

- **change** / **modify** / **update** = sadəcə dəyiş
- **revise** = əvvəlcə oxu/nəzərdən keçir, sonra dəyiş

- "**Revise** the RFC." (oxuyub, düşünüb, sonra dəyiş)
- "**Update** the RFC." (məlumatı yenilə)

### Kontekst: Nə vaxt revise?

- Sənədlər: "**revise** the technical spec"
- Planlar: "**revise** the roadmap"
- Rəylər: "**revise** your opinion"
- Commit-lərdə: nadir — daha çox PR/doc kontekstdə

### "Revised" — çox işlənən forma

- **Revised** proposal. ✓
- **Revised** estimate. ✓
- **Revised** timeline. ✓

---

## Müqayisə Cədvəli

| | Change | Modify | Update | Revise |
|-|--------|--------|--------|--------|
| Tam/hissəvi | hər ikisi | hissəvi | hissəvi/tam | hissəvi |
| Köhnəlik hissi | yox | yox | bəli | bəli |
| Review + dəyiş | yox | yox | yox | bəli |
| Tech kontekst | geniş | çox | çox | az |
| Sənəd kontekst | bəli | az | bəli | çox |
| Rəsmilik | neytral | neytral | neytral | rəsmi |

---

## Commit Mesajı Bələdçisi

| Hal | Düzgün söz | Nümunə |
|-----|-----------|--------|
| Tam yeni yanaşma | change | `change auth to token-based` |
| Mövcud kodu düzəlt | modify | `modify retry logic in HTTP client` |
| Dependency / data | update | `update dependencies`, `update user email` |
| Sənəd / plan | revise | `revise deployment checklist` |

---

## Tez-tez Yanlış İşlənənlər

| ❌ Yanlış | ✅ Düzgün |
|-----------|-----------|
| Please revise the variable name. | Please **change** / **rename** the variable name. |
| I'll change the config slightly. | I'll **modify** the config. |
| Modify the docs with the new API info. | **Update** the docs with the new API info. |
| We changed the plan after review. | We **revised** the plan after review. |
| Update the function signature. | **Modify** the function signature. |
| I revised the password. | I **changed** the password. |
| We need to change the library version. | We need to **update** the library version. |
| Revise this line of code. | **Change** / **modify** this line of code. |

---

## Test

Hansı söz uyğundur?

1. The client wants to ______ the entire design. (tam transformasiya)
2. I'll ______ just the timeout value in the config. (hissəvi, targeted)
3. Please ______ the npm packages to the latest version. (köhnə → yeni)
4. We need to ______ our estimates — they were wrong. (oxuyub düzəlt)
5. ______ your password every 3 months. (sadə dəyişiklik)
6. The tech spec has been ______. (nəzərdən keçirildi)
7. `UPDATE users SET email = ?` — which word does SQL use? ______
8. I'll ______ the middleware to add rate limiting. (hissəvi kod dəyişikliyi)

**Cavablar:** 1. change, 2. modify, 3. update, 4. revise, 5. Change, 6. revised, 7. UPDATE (update), 8. modify

---

## Cümləni tamamlayın

1. The plan was too ambitious. We had to ______ it before presenting to the board.
2. Don't rewrite the whole function — just ______ the part that handles errors.
3. Our CI pipeline automatically ______ the cache after each build.
4. The architecture needs a complete ______. We're starting over.
5. Please ______ the documentation — the API endpoints have changed.
6. I'll ______ the Dockerfile to reduce the image size.

**Cavablar:** 1. revise, 2. modify, 3. updates, 4. change, 5. update, 6. modify

---

## Tech / İş Kontekstində

### Change

- "The client wants to **change** the entire payment flow." ✓
- "**Change** the approach — current one doesn't scale." ✓

### Modify

- "**Modify** the request headers in the middleware." ✓
- "I **modified** the base class behavior." ✓
- "**Modified** 3 files." (git output) ✓

### Update

- "`UPDATE` query to change user status." ✓
- "**Update** the dependencies weekly." ✓
- "**Update** the README after merging." ✓

### Revise

- "Let's **revise** the architecture proposal." ✓
- "**Revised** timeline: Q3 instead of Q2." ✓

---

## Azərbaycanlı Səhvləri

- ✗ Revise the password. (revise sənəd/plan üçündür)
- ✓ **Change** the password.

- ✗ I changed the config slightly. (tam dəyişiklik kimi səslənir)
- ✓ I **modified** the config.

- ✗ Modify the docs. (məlumat yeniliyi üçün update)
- ✓ **Update** the docs.

---

## Xatırlatma

| Söz | Bir sözdə |
|-----|-----------|
| **change** | dəyiş (ümumi) |
| **modify** | hissəsini düzəlt |
| **update** | cariləşdir |
| **revise** | oxuyub düzəlt |

**Git commit qaydası:** `modify` kod üçün, `update` dependency/doc üçün, `revise` plan/spec üçün.

→ Related: [add-vs-insert-vs-append.md](add-vs-insert-vs-append.md), [fix-vs-solve-vs-resolve-vs-debug.md](fix-vs-solve-vs-resolve-vs-debug.md)
