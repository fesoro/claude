# Git Commit Messages — İngiliscə Yazmaq

## Səviyyə
B1 (tech interview + iş)

---

## Niyə Vacibdir?

Git commit message-lar:
- Beynəlxalq iş yerində standart İngiliscədir
- Code review-da görünür
- Interview-da sənə baxan adam oxuyur (GitHub / portfolio)
- Şirkət tarixidir

**Yaxşı commit message = peşəkar imza**

---

## Qızıl Qaydalar

1. **İmperative mood** istifadə et (əmr forması)
2. **50 hərf** subject-də
3. **Present tense** (keçmiş yox!)
4. **Nöqtə qoyma** subject-də
5. **Kapital hərf** ilə başla
6. **Nə etdi** — yaz (niyə — body-də)

---

## Commit Struktur

```
<type>: <subject line — 50 chars max>

<body — optional, explains WHY>

<footer — optional, refs issues>
```

### Type-lər (Conventional Commits)

- **feat**: yeni feature
- **fix**: bug fix
- **docs**: dokumentasiya
- **style**: format (kod deyil)
- **refactor**: kod təmizlik (davranış eyni)
- **test**: test əlavə
- **chore**: build / tooling
- **perf**: performans
- **ci**: CI/CD dəyişikliyi

---

## Imperative Mood (VACİB!)

Commit message "What will this commit do?" sualına cavab verir.

### ✓ Düzgün (Imperative)

- **Add** user authentication
- **Fix** login bug
- **Update** README
- **Remove** deprecated API
- **Refactor** auth module

### ✗ Səhv (Past tense)

- ~~Added user authentication~~
- ~~Fixed login bug~~
- ~~Updated README~~

### ✗ Səhv (Present continuous)

- ~~Adding user authentication~~
- ~~Fixing bug~~

**Qayda:** "If applied, this commit will __" formuluna uyğun yaz.

- "If applied, this commit will **add** user authentication." ✓

---

## Uzunluq

- **Subject line**: 50 hərf (max 72)
- **Body**: 72 hərf / sətir

### Test

- "feat: add user auth with JWT tokens" → 36 hərf ✓
- "feat: implement a new user authentication module using JWT" → 58 hərf ✗ (çox!)

---

## Yaxşı Commit Nümunələri

### Feature

```
feat: add user login endpoint

Implement POST /api/login endpoint with JWT token response.
Includes rate limiting and password hashing.

Closes #123
```

### Bug fix

```
fix: prevent null pointer in user profile

Check if user exists before accessing profile data.
Previous code crashed for deleted users.

Fixes #456
```

### Refactor

```
refactor: extract auth logic into separate module

Move authentication functions from UserService
to AuthService for better separation of concerns.
No behavior change.
```

### Docs

```
docs: update README with setup instructions

Add Docker setup steps and environment variable
documentation for new contributors.
```

### Chore

```
chore: bump dependencies to latest versions

Update all npm packages to latest stable releases.
Fixes security vulnerabilities in lodash 4.17.x.
```

---

## Pis Commit Nümunələri

### ✗ Çox qısa / məlumatsız

- ~~"fix"~~
- ~~"update"~~
- ~~"changes"~~
- ~~"wip"~~

### ✗ Çox uzun subject

- ~~"feat: add a new user authentication endpoint that handles JWT tokens and rate limiting for login attempts"~~

### ✗ Past tense

- ~~"Added new feature for login"~~

### ✗ Azərbaycanca / qarışıq

- ~~"fix: login problemi həll etdim"~~

---

## Body Nə Vaxt Lazımdır?

### Body LAZIMDIR:

- Mürəkkəb dəyişikliklər
- Niyə yaz (WHY, not WHAT)
- Trade-off izahı
- Breaking changes

### Body LAZIM DEYİL:

- Sadə fix
- Typo
- One-liner dəyişiklik

---

## Niyə "Niyə?" Vacibdir

**Subject** = nə etdi (kod baxdıqda görünür)
**Body** = niyə etdi (kod baxdıqda görünmür!)

### Pis body

```
Refactor the code to make it cleaner.
```
(Bəs niyə? Kim üçün? Nə pis idi?)

### Yaxşı body

```
Refactor the code to improve readability.

The previous structure had 3 levels of nesting,
making it hard to follow. This change flattens
the logic into separate functions, each handling
one responsibility.
```

---

## Issue Referansı

### Bağlamaq (auto-close)

- `Closes #123`
- `Fixes #456`
- `Resolves #789`

GitHub issue-nu avtomatik bağlayır.

### Sadəcə qeyd

- `Refs #123`
- `See #456`
- `Related to #789`

---

## Breaking Changes

Əgər API / interface dəyişirsə:

```
feat!: change user ID from int to UUID

BREAKING CHANGE: User IDs are now UUID v4 strings
instead of integers. Update client code accordingly.
```

**"!"** və **"BREAKING CHANGE:"** göstərir.

---

## Commit Sentences — Vocabulary

### Feel free to copy these!

**Add**
- Add user login endpoint
- Add logging for failed requests
- Add unit tests for auth module

**Fix**
- Fix null pointer in user service
- Fix race condition in cache
- Fix typo in error message

**Update**
- Update dependencies to latest
- Update README with new examples
- Update error messages for clarity

**Remove**
- Remove deprecated API endpoint
- Remove unused imports
- Remove console.log statements

**Refactor**
- Refactor auth module for testability
- Refactor user service to use async/await
- Refactor tests to reduce duplication

**Improve / Enhance**
- Improve error handling in API
- Enhance search performance with indexes
- Optimize database queries

**Rename**
- Rename handleUser to processUser
- Rename old API endpoints

**Move / Extract**
- Move utility functions to utils/
- Extract validation logic into separate module

**Bump / Upgrade**
- Bump Node.js version to 20
- Upgrade React to v18

---

## Müxtəlif Stillər

### Conventional Commits

```
feat: add login
fix: handle null user
docs: update setup
```

### Jira Style

```
PROJ-123: Add user login
PROJ-456: Fix null pointer
```

### Simple Imperative

```
Add login endpoint
Fix typo in docs
Update dependencies
```

Şirkət qaydalarına əməl et!

---

## İnterview-da Commits

Interviewer portfolioya baxır:

### ✓ Yaxşı

- Aydın, peşəkar commit message-lər
- Conventional commits
- İngiliscə
- Imperative mood

### ✗ Pis

- "fix", "update" (məlumatsız)
- "wip", "asdf", "test" (junk)
- Qarışıq dillər
- Too many commits like "fixed it"

---

## Practical Tips

### 1. Commit Often, Push Logically

Lokal çox commit et, amma push-dan əvvəl məntiqlə birləşdir (squash).

### 2. Use `--amend` for Small Fixes

```bash
git commit --amend --no-edit
```

### 3. Interactive Rebase

```bash
git rebase -i HEAD~3
```

Son 3 commit-i təmizlə.

### 4. Atomic Commits

Bir commit = bir məntiqi dəyişiklik.

---

## Example Good Commit History

```
feat: add user authentication
feat: add password reset endpoint
test: add tests for auth module
docs: update API documentation
fix: handle expired tokens
refactor: extract token validation
chore: update dependencies
```

---

## Example Bad Commit History

```
asdf
fix
more fixes
wip
fix bug
it works now
final
really final
```

---

## Azərbaycanlı Səhvləri

- ✗ "Fixed the bug" (past → imperative)
- ✓ "**Fix** the bug"

- ✗ "Adding new feature" (V-ing → V)
- ✓ "**Add** new feature"

- ✗ "fix: bug düzəldildi" (AZ → English!)
- ✓ "fix: handle null user"

- ✗ Type yazılmır (conventional commits)
- ✓ "feat: ...", "fix: ..."

---

## Commit Message Checklist

Push-dan əvvəl yoxla:

- [ ] İmperative mood? (Add, Fix, Update)
- [ ] 50 hərfdən az?
- [ ] Kapital başlıq?
- [ ] Nöqtə yoxdur sonda?
- [ ] İngiliscədir?
- [ ] Type var? (feat/fix/docs)
- [ ] Body gərəkirsə, niyə izah edilir?

---

## Resources

- Conventional Commits: conventionalcommits.org
- Chris Beams guide: chris.beams.io/posts/git-commit/

---

## Xatırlatma

**Qızıl Qaydalar:**
1. Imperative ("Add", "Fix")
2. 50 hərf limit
3. İngiliscə
4. Aydın nə + niyə (body)
5. Conventional prefix (feat/fix/docs)

**Test:** "If applied, this commit will **[verb]**..." — bu forma OK-dırsa, commit düzgündür.

→ Related: [pr-descriptions.md](pr-descriptions.md), [technical-writing.md](technical-writing.md)
