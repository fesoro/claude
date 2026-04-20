# Bug Report Writing — Səhv Hesabatı Yazmaq

## Səviyyə
B1 (iş + open source)

---

## Niyə Vacibdir?

Yaxşı bug report:
- Developer vaxtını qorur
- Tez fix edilir
- Peşəkar imza

**Pis bug report = "don't reproduce" → closed**

---

## Bug Report Strukturu (Standard)

```markdown
## Title
[Qısa, spesifik başlıq]

## Description
[Nə baş verir]

## Steps to Reproduce
1. ...
2. ...
3. ...

## Expected Behavior
[Nə olmalı idi]

## Actual Behavior
[Nə baş verdi]

## Environment
- OS: ...
- Browser: ...
- Version: ...

## Screenshots / Logs
[Ekran şəkli və ya log]

## Additional Context
[Əlavə məlumat]
```

---

## 1. Title — Qısa, Spesifik

Başlıq çox vacibdir — triage-çilər bu baxıb açır.

### ✗ Pis başlıqlar

- "Bug"
- "Doesn't work"
- "Error"
- "Help!"

### ✓ Yaxşı başlıqlar

- "Login button unclickable on Safari 15"
- "500 error when submitting empty form"
- "Search returns duplicates for multi-word queries"
- "PR #123 breaks build on Windows"

### Qayda

- 5-10 söz
- Spesifik nə xəta, harada
- Environment / context qeyd et

---

## 2. Description — Nə Olur

1-3 cümlədə problem izah.

### ✓ Yaxşı

```
When I click the "Submit" button on the contact form,
nothing happens. No error message, no network request.
Console shows a TypeError.
```

### ✗ Pis

```
Form doesn't work.
```

---

## 3. Steps to Reproduce — Təkrarlama

Başqa developer bu addımları izləyərək səhvi yenidən görə bilməlidir.

### Format

```
1. Navigate to /login
2. Enter username "test@example.com"
3. Enter password "test123"
4. Click "Sign In" button
5. Observe: Error 500 is displayed
```

### ✓ Yaxşı

- Numbered steps
- Actions concrete (click, type, navigate)
- Spesifik input values (test data)
- Hər adım bir yeni nəticə

### ✗ Pis

- "I tried to log in."
- "Then some stuff happened."

### Expected format example

```
## Steps to Reproduce
1. Open the app (https://app.example.com)
2. Log in with admin credentials
3. Go to Settings > Users
4. Click "Add User"
5. Leave the email field empty
6. Click "Save"

## Expected: Validation error "Email required"
## Actual: 500 Internal Server Error
```

---

## 4. Expected vs Actual

### Expected Behavior

Nə **olmalı** idi.

- "The form should display a success message."
- "The page should load within 2 seconds."
- "An email should be sent to the user."

### Actual Behavior

Nə **baş verdi**.

- "The page shows a blank screen."
- "An error 500 is displayed."
- "No email is sent."

### Fərq niyə vacibdir?

Developer bilir:
- **Expected** — doğru davranış nədir
- **Actual** — hansı anomalya var

---

## 5. Environment

Texniki detallar.

### Web

```
## Environment
- OS: macOS 14.2
- Browser: Chrome 121
- Screen size: 1920x1080
- Logged in as: Admin role
- App version: 2.3.1
```

### Mobile

```
## Environment
- Device: iPhone 15 Pro
- OS: iOS 17.2
- App version: 3.1.0 (build 456)
- Network: WiFi
```

### Backend / API

```
## Environment
- API version: v2
- Endpoint: /api/users
- Method: POST
- Authentication: Bearer token
```

---

## 6. Screenshots / Logs

Görünüş vacibdir.

### Screenshots

- Annotate screenshot (arrows, circles)
- Before / after state
- Error message tam görünsün

### Logs

```
## Error Log
[2024-04-18 10:23:15] ERROR: Database connection timeout
Stack trace:
  at UserService.getUser (user.service.ts:45)
  at UserController.show (user.controller.ts:23)
```

### Network requests

Browser DevTools:
- Status code (500)
- Request headers
- Response body

---

## 7. Additional Context

Ekstra məlumat.

### Frequency

- "Happens every time."
- "Happens intermittently (~30% of attempts)."
- "Only after refreshing the page."

### Workaround

- "Clearing cache fixes it temporarily."
- "Works in incognito mode."

### Impact

- "Blocks all users from logging in."
- "Affects only admin accounts."
- "Minor — UI glitch, not blocking."

---

## Severity / Priority

### Severity

- **Critical (SEV-1)**: production down
- **High (SEV-2)**: major feature broken
- **Medium (SEV-3)**: partial issue
- **Low (SEV-4)**: cosmetic / minor

### Priority

- **P0**: fix immediately
- **P1**: fix this sprint
- **P2**: fix eventually
- **P3**: nice to have

---

## Tech Vocabulary

### Action verbs

- **reproduce** = təkrarla
- **trigger** = başlat
- **throw** = error ver (throw an error)
- **observe** = müşahidə et
- **occur** = baş verməsi
- **fail** = uğursuz
- **crash** = dağılmaq
- **hang** = donmuş qalmaq

### Adjectives

- **intermittent** = fasiləli
- **consistent** = hər dəfə
- **reproducible** = təkrarlana bilən
- **flaky** = sabit deyil
- **inconsistent** = ardıcıl deyil

### Nouns

- **regression** = əvvəl işləyirdi
- **workaround** = müvəqqəti həll
- **edge case** = nadir hal
- **race condition** = paralellik səhvi

---

## Example Full Bug Report

```markdown
## Title
Login button unresponsive on Safari 15 after password manager autofill

## Description
When Safari's password manager autofills credentials, the "Sign In"
button becomes unresponsive. Clicking it does nothing — no network
request is made, no error appears in the console.

## Steps to Reproduce
1. Open https://app.example.com/login in Safari 15
2. Let Safari's password manager autofill email and password
3. Click the "Sign In" button
4. Observe: Nothing happens

## Expected Behavior
The user should be logged in and redirected to /dashboard.

## Actual Behavior
Button click has no effect. No network request in DevTools.
No error in console.

## Environment
- OS: macOS 14.2 (Sonoma)
- Browser: Safari 15.6.1
- App version: 2.3.1
- User role: All (affects multiple accounts)

## Screenshots
[Attached: login-page.png showing autofilled fields]

## Additional Context
- Works correctly in Chrome and Firefox
- Works in Safari if user manually types credentials
- Workaround: Manually clear and retype password after autofill
- Likely related to Safari's autofill event handling

## Impact
Medium — affects all Safari users who use autofill (~15% of users).
```

---

## Common Phrases

### Describing the issue

- "The application crashes when..."
- "An error is thrown when..."
- "The page fails to load..."
- "Users are unable to..."

### Specifying conditions

- "This only happens when..."
- "Consistently reproducible by..."
- "Intermittently occurs..."
- "Affects users with..."

### Error messages

- "The following error appears:"
- "Error log shows:"
- "Console output:"

---

## Pis Bug Report Nümunəsi

```
Title: Something broken

Description: Page not working.

Steps: Go to the site.

Expected: Work.
Actual: Doesn't work.
```

**Hər sahə boşdur!** Developer nə edəcək?

---

## Yaxşı Report — Tips

### 1. One bug per report

İki bug tapsan, iki report yaz.

### 2. Search for duplicates

Eyni bug varsa, yeni yazma — əlavə məlumat comment et.

### 3. Assume reader knows nothing

Context ver. Developer sənin mühitini bilmir.

### 4. Update if new info

Əlavə məlumat tapırsan, comment əlavə et.

---

## Response Templates

### Acknowledging bug

- "Thanks for the report. I'll investigate."
- "Looking into this. Can you provide logs?"
- "Reproduced. Working on a fix."

### Asking for info

- "Can you share the browser version?"
- "Do you have a screenshot?"
- "Can you reproduce on a clean profile?"

### Closing

- "Fixed in PR #123."
- "Can't reproduce. Closing. Reopen if it recurs."
- "Duplicate of #456."

---

## Interview Kontekstində

### "Tell me about a bug you found"

Use the structure:
1. What was the bug (brief description)
2. How you reproduced
3. Root cause
4. How you fixed
5. What you learned

### Example

"I found a **race condition** in the checkout flow. I **reproduced** it by simulating concurrent requests. The **root cause** was a missing lock on the payment transaction. I added **pessimistic locking** and wrote integration tests. Learning: always consider concurrency in payment flows."

---

## Azərbaycanlı Səhvləri

- ✗ "It not working." (grammar)
- ✓ "It **doesn't work**."

- ✗ "Give error." (struktur)
- ✓ "It **throws an error**." / "It **shows an error**."

- ✗ Steps boş
- ✓ **Numbered steps** ver

---

## Xatırlatma

**Yaxşı Bug Report:**
1. ✓ Specific title
2. ✓ Clear description
3. ✓ Numbered steps
4. ✓ Expected vs Actual
5. ✓ Environment details
6. ✓ Screenshots / logs
7. ✓ Frequency + impact

**Qızıl qayda:** Yazdığın report-u 1 həftə sonra özün oxu. Anlayırsansa — yaxşıdır.

→ Related: [git-commit-messages.md](git-commit-messages.md), [pr-descriptions.md](pr-descriptions.md), [incident-postmortem-writing.md](incident-postmortem-writing.md)
