# Pull Request Descriptions — İngiliscə Yazmaq

## Səviyyə
B1 (tech interview + open source)

---

## Niyə Vacibdir?

PR description:
- Reviewer vaxtını saxlayır
- Kontekst verir (niyə?)
- Interview portfolio-sında görünür
- Open source töhfəsidir
- Peşəkar imza

**Yaxşı PR description = yaxşı reviewer təcrübəsi**

---

## Əsas Struktur

```markdown
## Summary
(What does this PR do? 1-3 sentences)

## Why
(Why is this change needed?)

## Changes
(Bullet list of key changes)

## Testing
(How was this tested?)

## Screenshots (if UI)
(Visual proof)

## Related
(Links to issues / tickets)
```

---

## Yaxşı PR Template

### Minimal (kiçik PR)

```markdown
## Summary
Fix null pointer exception in user profile endpoint.

## Changes
- Add null check before accessing user.profile
- Return 404 if user is deleted

## Testing
- Added unit test for deleted user case
- Manually tested with curl

Closes #123
```

### Detailed (böyük PR)

```markdown
## Summary
Add JWT-based authentication to the API.

## Why
Currently, the API uses session-based auth which doesn't
scale well across multiple servers. JWT tokens enable
stateless authentication and horizontal scaling.

## Changes
- Add `/api/login` endpoint with JWT response
- Add `authMiddleware` for protected routes
- Migrate `/user/*` routes to use JWT
- Remove session-based code

## Testing
- Added 15 unit tests (covers 90% of new code)
- Integration tests for login/logout flow
- Load tested with 1000 concurrent users
- Tested token expiration and refresh

## Breaking Changes
Clients must now send `Authorization: Bearer <token>` header
instead of relying on session cookies.

## Related
- Closes #123
- Depends on #456
- Follows design in [ADR-007](link)
```

---

## Summary Yazmaq

### ✓ Yaxşı

- "Add user authentication with JWT." (1 cümlə)
- "Fix null pointer crash in user profile." (aydın)
- "Migrate database from MySQL to Postgres."

### ✗ Pis

- "Updates" (nə update?)
- "Changes" (nə dəyişdi?)
- "Fix bug" (hansı bug?)

---

## "Why" Yazmaq (VACİB!)

**Reviewer kod görür. Kontekstləri görmür. Sən izah etməlisən.**

### ✓ Yaxşı Why

```
## Why
Users report slow load times on the dashboard.
Profiling showed the N+1 query problem in orders.
This PR adds eager loading to reduce DB calls.
```

### ✗ Pis Why

```
## Why
Needed to improve performance.
```

(Niyə lazım idi? Niyə indi? Başqa yollar?)

---

## Changes Siyahısı

### ✓ Yaxşı bullet list

```
## Changes
- Add `eager_load` to orders relationship
- Remove redundant `count()` call in view
- Add database index on `user_id` column
- Update tests for new query pattern
```

### ✗ Pis

```
## Changes
- Made changes
- Fixed stuff
- Updated things
```

---

## Testing Bölməsi

Reviewer bilmək istəyir: "Test olunub?"

### ✓ Yaxşı

```
## Testing
- Added 8 unit tests (covers new logic)
- Ran full test suite: all passing
- Manually tested on staging
- Load test: 500 req/s sustained
```

### ✗ Pis

```
## Testing
- Tested it
```

---

## Screenshots (UI PR)

UI dəyişikliklər üçün mütləq şəkil əlavə et.

### Markdown

```markdown
## Screenshots

### Before
![Before](url)

### After
![After](url)
```

Və ya:

```markdown
| Before | After |
|--------|-------|
| ![before](url) | ![after](url) |
```

---

## Breaking Changes

API dəyişikliyi varsa — mütləq qeyd et.

```markdown
## Breaking Changes
- `/api/user` response now returns `id` as UUID (was int)
- Clients must update parsing logic
- Migration guide: [link](...)
```

---

## Related Links

Issue və dependencies qeyd et.

```markdown
## Related
- Closes #123
- Part of epic #456
- Follows [design doc](link)
- Depends on #789 (merge first)
```

### GitHub keywords (auto-close)

- `Closes #123`
- `Fixes #456`
- `Resolves #789`

---

## Kiçik PR vs Böyük PR

### Kiçik PR (< 200 lines)

Minimum format OK:

```markdown
## Summary
Fix typo in error message.

Closes #321
```

### Böyük PR (1000+ lines)

Full template mütləq:
- Summary
- Why (context)
- Changes
- Testing
- Screenshots
- Breaking changes

**Qayda:** PR-ı kiçik saxla! Böyük PR → review vaxtı çox.

---

## Checklist Əlavə Etmək

Bəzi şirkətlər template-ə checklist əlavə edir:

```markdown
## Checklist
- [x] Tests added
- [x] Docs updated
- [x] No secrets in code
- [ ] Translations updated (if UI)
- [x] Migration included (if DB)
```

---

## PR Title

PR title də vacibdir!

### ✓ Yaxşı

- `feat: add user authentication`
- `fix: handle null user in profile`
- `refactor: extract auth logic`

### ✗ Pis

- `Update`
- `Fix it`
- `My changes`

**Qayda:** PR title = commit message stili (conventional commits).

→ [git-commit-messages.md](git-commit-messages.md)

---

## Common Phrases

### Summary / Introduction

- "This PR..."
- "This change..."
- "In this PR, we..."

### Explaining why

- "The reason for this is..."
- "We need this because..."
- "This addresses..."

### Listing changes

- "Changes include..."
- "This PR introduces..."
- "The following changes were made..."

### Testing

- "I tested this by..."
- "Added tests covering..."
- "Verified in staging..."

### Requesting review

- "Please review..."
- "Feedback welcome on..."
- "Can you double-check..."

---

## Reviewer-ə Polite Mesaj

PR comment-lərdə polite ol:

### ✓ Good tone

- "Thanks for the review!"
- "Good catch!"
- "Let me update that."
- "Could you clarify..."

### ✗ Avoid

- "Wrong." (təhqiramiz)
- "That's stupid."
- "I disagree." (izah yox)

---

## WIP / Draft PR

Work-in-progress PR üçün:

### GitHub Draft

- Açılış zamanı "Draft" seç
- Review istəmə, sadəcə fikir paylaş

### Title prefix

- `[WIP] feat: add auth` ✓
- `Draft: feat: add auth` ✓

### Description

```markdown
## Status
🚧 Work in progress — not ready for review

## TODO
- [ ] Add tests
- [ ] Update docs
- [ ] Handle edge cases
```

---

## Open Source PR

Open source-da ekstra vacib:

### İlk PR

```markdown
## Summary
Fix typo in README.md.

This is my first contribution to the project — thanks
for maintaining it! Please let me know if I need
to adjust anything.

Closes #42
```

### Saygılı ol

- Maintainer vaxtını dəyər
- Style guide-ı oxu
- CONTRIBUTING.md-ı oxu

---

## Tech Interview Kontekstində

Interviewer GitHub-ına baxacaq:

### ✓ Yaxşı görünmək üçün

- Yaxşı PR descriptions
- Aydın commit messages
- İngiliscə
- Peşəkar interaction

### Portfolio təmizlə

- Köhnə "test" PR-ları sil
- Yaxşı nümunələri pin-lə
- README-lərini yenilə

---

## Azərbaycanlı Səhvləri

- ✗ Description boş buraxma!
- ✓ Ən azından "## Summary" yaz.

- ✗ Azərbaycanca yazma. (international team!)
- ✓ **İngiliscə** yaz.

- ✗ "i fixed the bug" (kiçik hərf, formal deyil)
- ✓ "## Summary\nFix null pointer bug."

---

## Example (Real PR)

```markdown
## Summary
Add rate limiting to login endpoint to prevent brute force attacks.

## Why
Security audit (see #789) identified our login endpoint as
vulnerable to brute force attacks. We need to limit login
attempts per IP address.

## Changes
- Add Redis-based rate limiter middleware
- Limit: 5 attempts per 15 minutes per IP
- Return `429 Too Many Requests` when exceeded
- Log blocked attempts for monitoring

## Testing
- Unit tests for rate limiter logic
- Integration test for login flow
- Manual test: exceeded limit, got 429 ✓
- Performance test: no impact on happy path

## Performance
- Added ~2ms to login requests
- Redis memory: ~100 bytes per tracked IP

## Security
- IP obfuscation (hash before storing)
- No PII logged

## Related
- Closes #789
- Follows [security ADR-005](link)
- Depends on #800 (Redis setup) — merge first
```

---

## Xatırlatma

**Yaxşı PR Description:**
1. ✓ Aydın summary (1-3 cümlə)
2. ✓ WHY — niyə lazımdır
3. ✓ Changes — nələr
4. ✓ Testing — necə test olundu
5. ✓ Related issues
6. ✓ Screenshots (UI)
7. ✓ Breaking changes qeyd

**Qayda:** Reviewer-in 30 saniyəni qoruyursan — yaxşı PR yazırsan.

→ Related: [git-commit-messages.md](git-commit-messages.md), [technical-writing.md](technical-writing.md), [design-doc-writing.md](design-doc-writing.md)
