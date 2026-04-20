# Refactor / Rewrite / Redesign / Restructure — Kod Dəyişdirmə

## Səviyyə
B1-B2 (tech interview vacib)

---

## Əsas Cədvəl

| Söz | Davranış dəyişir? | Miqyas |
|-----|-------------------|--------|
| **refactor** | yox (sadəcə təmizlik) | kod / funksiya |
| **rewrite** | ola bilər | böyük hissə / modul |
| **redesign** | bəli (interfeys / struktur) | arxitektura / UI |
| **restructure** | daxili struktur | qovluq / təşkilat |

> **Qısa qayda:**
> - **refactor** = eyni davranış, daha təmiz kod
> - **rewrite** = sıfırdan yenidən yaz
> - **redesign** = interfeysi / arxitekturanı dəyiş
> - **restructure** = yenidən təşkil

---

## 1. Refactor — Kodu Təmizləmək (Davranış Dəyişmir!)

Ən vacib termin. **Davranış eyni qalır**, kod daha yaxşı olur.

### Nümunələr

- I **refactored** the auth module.
- Time to **refactor** this legacy code.
- **Refactoring** for readability.
- Small **refactor** PR.
- Red-Green-**Refactor** (TDD cycle).

### Nə edir?

- Çirkli kodu təmizləyir
- Duplicate silir (DRY)
- Method çıxarır (extract method)
- Variable adlandırır (rename)
- Class bölür (split)

### Nə etmir?

- Yeni feature əlavə etmir
- Bug fix etmir
- API interface dəyişdirmir

### Golden Rule

> Refactor = davranış eyni, kod fərqli.

Test suite dəyişməsə və hər şey yaşıl qalsa, düzgün refactor etmisən.

---

## 2. Rewrite — Sıfırdan Yenidən Yazmaq

Böyük miqyas. Adətən bütöv modul / sistem.

### Nümunələr

- We're **rewriting** the backend in Go.
- Full **rewrite** of the frontend.
- It's easier to **rewrite** than fix.
- Big **rewrite** project.
- **Rewrite** from scratch.

### Refactor vs Rewrite

- **refactor** = kiçik addımlar, davranış qorunur
- **rewrite** = sıfırdan, hər şey yenidir

- "Let's **refactor** this function." (kiçik)
- "Let's **rewrite** the entire auth service." (böyük)

### Joel Spolsky qaydası

> Never rewrite code from scratch.

Adətən rewrite-dən qaçmaq tövsiyə olunur (bug-lar itir, yeni bug-lar gəlir).

---

## 3. Redesign — Dizaynı Dəyişmək

Arxitektura və ya UI səviyyəsində. İnterfeys dəyişir.

### Nümunələr

- We're **redesigning** the homepage.
- Architecture **redesign**.
- Complete UI **redesign**.
- **Redesign** the database schema.
- Time for a major **redesign**.

### Kontekstlər

- UI/UX redesign → dizayn dəyişir
- Architecture redesign → sistem strukturu dəyişir
- Database redesign → schema dəyişir
- API redesign → endpoint-lər dəyişir

### Redesign vs Rewrite

- **redesign** = plan / dizayn dəyişir
- **rewrite** = kod sıfırdan yazılır

Redesign-dan sonra adətən rewrite gəlir.

---

## 4. Restructure — Yenidən Təşkil Etmək

Daxili təşkilat. Qovluq strukturu, modullar, team.

### Nümunələr

- I **restructured** the project folders.
- **Restructure** the codebase.
- Team **restructure**.
- Module **restructuring**.

### Refactor vs Restructure

- **refactor** = kod keyfiyyəti
- **restructure** = təşkilat (qovluq, fayl ardıcıllığı)

- "I **refactored** the function." (kod içi)
- "I **restructured** the folders." (kənar təşkilat)

---

## 5. Bonus Sözlər

### Migrate

Bir sistemdən digərinə köç.

- **Migrate** from Java to Kotlin.
- Database **migration**.

### Modernize

Köhnə texnologiyanı yeniləmək.

- **Modernize** the legacy stack.

### Rearchitect

Tam arxitektura dəyişikliyi.

- **Rearchitect** the system for scale.

### Overhaul

Bütöv sistemin yenidən işlənməsi.

- Complete **overhaul** of the CI pipeline.

---

## Test

Hansı söz daha uyğun?

1. I cleaned up the code — tests still pass. (davranış eyni) → ______
2. We scrapped everything and wrote it from scratch in Rust. → ______
3. The UI team changed the look of the login page. → ______
4. I moved the files into new folders. → ______

**Cavablar:** 1. refactored, 2. rewrote, 3. redesigned, 4. restructured

---

## İnterview Nümunələri

- "Last quarter, I **refactored** a 2000-line class into 5 smaller ones."
- "We had to **rewrite** the payment service because it couldn't scale."
- "After user research, we **redesigned** the checkout flow."
- "I **restructured** the monorepo to separate concerns."

---

## Red-Green-Refactor Cycle (TDD)

1. **Red** → Test yaz, fail olsun.
2. **Green** → Minimum kod yaz ki test keçsin.
3. **Refactor** → Kodu təmizlə, test keçməyə davam etsin.

---

## Azərbaycanlı Səhvləri

- ✗ I rewrote this function (sadəcə təmizlədim).
- ✓ I **refactored** this function.

- ✗ We're refactoring the whole system. (əslində rewrite)
- ✓ We're **rewriting** the whole system.

- ✗ We're refactoring the UI. (yenidən dizayn)
- ✓ We're **redesigning** the UI.

---

## Xatırlatma

| Söz | Bir sözdə |
|-----|-----------|
| **refactor** | təmizlə (eyni davranış) |
| **rewrite** | sıfırdan yaz |
| **redesign** | planı dəyiş |
| **restructure** | yenidən təşkil et |
