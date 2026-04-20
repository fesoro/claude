# Code Review Vocabulary — PR Review Lüğəti

## Səviyyə
B1-B2 (iş və open source-da vacib!)

---

## Niyə Vacibdir?

Code review:
- Daily peşəkar söhbət
- İnternational tim iştirakı
- Open source töhfə
- Interview portfolio

**Yaxşı review = peşəkar imza**

---

## PR Review Strukturu

### Reviewer rolları

1. **Code quality** yoxla
2. **Correctness** yoxla (bug var?)
3. **Performance** yoxla
4. **Security** yoxla
5. **Tests** yoxla
6. **Docs** yoxla

---

## 1. Approving Phrases (Təsdiq)

### Sadə onay

- **LGTM** = Looks good to me
- **👍 LGTM**
- "Looks good, ship it!"
- "Approved!"

### Tərifli

- "Nice work!"
- "Clean implementation."
- "Well done!"
- "Great solution."
- "Love this approach."

### Onay + kiçik iradlar

- "LGTM with minor nits."
- "Looks great, minor suggestions below."
- "Approved — feel free to address or ignore nits."

---

## 2. Requesting Changes (Dəyişiklik İstək)

### Polite dəyişiklik istəyi

- "Could you...?"
- "Would you mind...?"
- "Can we...?"
- "I'd suggest..."
- "Consider..."

### Nümunələr

- "**Could you** add a null check here?"
- "**Would you mind** adding a test for this case?"
- "**Can we** rename this to be clearer?"
- "**I'd suggest** extracting this into a helper."
- "**Consider** using a constant instead of magic number."

---

## 3. Asking Questions

Suallar — kobud səslənmir.

### Yumşaq suallar

- "What's the reason for this approach?"
- "Have you considered X?"
- "What happens if Y?"
- "Why not use X instead?"
- "Is this handling Z case?"

### Nümunələr

- "**Have you considered** using a map instead of a list for O(1) lookup?"
- "**What happens if** the input is empty?"
- "**Why not** use async/await here?"

---

## 4. Suggestions

### Səviyyələr

#### Nit (xırda)

- **nit**: naming convention.
- **nit**: extra newline here.
- **nit (optional)**: use map instead of filter.

#### Suggestion (təklif)

- **suggestion**: extract into a helper function.
- **suggestion**: add a type annotation.

#### Question

- **question**: why this order?
- **question**: is this the best approach?

#### Blocking (bloklayıcı)

- **blocking**: must add tests.
- **blocking**: SQL injection risk here.

### Conventional comments

Format: `<label>: <comment>`

- `nit: ...`
- `suggestion: ...`
- `question: ...`
- `blocking: ...`
- `praise: ...` (tərif!)

---

## 5. Issues / Concerns

### Bug report

- "I think there's a bug here — when X, Y happens."
- "This looks like a potential null pointer."
- "Edge case: what if the array is empty?"
- "Race condition possibility."

### Performance

- "This might be slow for large inputs."
- "N+1 query concern here."
- "Consider pagination for large datasets."
- "Memory usage could spike."

### Security

- "Potential SQL injection."
- "User input should be sanitized."
- "Missing authentication check."
- "Logging sensitive data."

### Readability

- "This function is doing too much."
- "Could we break this down?"
- "Variable name is unclear."
- "Magic number — extract to constant."

---

## 6. Code Smells Vocabulary

### Common issues

- **Long function**: > 50 lines usually
- **Deep nesting**: > 3 levels
- **Magic numbers**: unexplained constants
- **Duplicated code**: DRY violation
- **God class**: does too much
- **Dead code**: never executed
- **Tight coupling**: hard to test
- **Feature envy**: class uses another's data too much

### Using these in review

- "This function suffers from **deep nesting** — let's refactor."
- "**Magic number** 42 here — what does it represent?"
- "I see **duplicated code** between X and Y."
- "This looks like **dead code** — can we remove?"

---

## 7. Testing Vocabulary

### Types of tests

- **Unit test**: single function
- **Integration test**: multiple components
- **E2E test**: full user flow
- **Smoke test**: basic functionality
- **Regression test**: prevent old bugs
- **Performance test**: speed / load

### Common review comments

- "Missing unit test for this function."
- "Could you add an integration test?"
- "Test covers the happy path but not error cases."
- "What about edge cases?"
- "Mock the external API."
- "Test is flaky — add retries or fix the race."

---

## 8. Refactoring Suggestions

### Common suggestions

- **Extract method**: uzun function-ı böl
- **Rename**: daha aydın ad
- **Inline**: kiçik method-u silib yerinə qoy
- **Move**: başqa class-a köçür
- **Simplify**: sadələşdir
- **DRY**: duplicate sil

### Phrases

- "**Extract this** into a helper function."
- "**Rename to** something more descriptive."
- "**Move this** to the User service."
- "**Simplify** with `.filter().map()`."
- "**DRY this up** — we have similar code in X."

---

## 9. Positive Feedback

Only criticism is bad culture. Also give praise!

### Praise phrases

- "Great catch!"
- "Nice refactor."
- "Love this abstraction."
- "Clean code — easy to follow."
- "Good separation of concerns."
- "Tests are thorough!"
- "Docs are great."

### Using `praise:` label

- **praise**: Really nice use of the Strategy pattern here.
- **praise**: Love the comprehensive test coverage!

---

## 10. Responding to Reviews

### Accepting

- "Good catch, fixed!"
- "Makes sense, updated."
- "Thanks, will update."
- "You're right — let me fix that."

### Disagreeing

- "I see your point, but..."
- "I considered that, however..."
- "I'd argue this is fine because..."
- "Let's discuss — I have different view."

### Asking for clarification

- "Could you clarify?"
- "Not sure I understand — could you show an example?"
- "Can you explain the concern in more detail?"

### Deferring

- "Good idea, but outside this PR's scope."
- "Agreed — I'll open a follow-up issue."
- "Let's address in a future PR."

---

## 11. Common Review Sentences

### Starting a review

- "Thanks for the PR! A few comments below."
- "Overall looking good — some suggestions."
- "Nice work. Let's iterate on a few things."

### Requesting changes block

- "Needs a few changes before approval."
- "Please address the blocking comments."

### Ending

- "Approved once these are fixed!"
- "Ready after addressing comments."
- "Great work overall!"

---

## 12. Abbreviations

### Common

- **LGTM**: Looks Good To Me
- **PR**: Pull Request
- **MR**: Merge Request (GitLab)
- **WIP**: Work In Progress
- **TBD**: To Be Determined
- **TBH**: To Be Honest (informal)
- **TL;DR**: Too Long; Didn't Read
- **FYI**: For Your Information
- **IIRC**: If I Recall Correctly
- **AFAIK**: As Far As I Know
- **IMO / IMHO**: In My (Humble) Opinion

### Using these

- "**IMO** this approach is cleaner."
- "**AFAIK** we don't need this check."
- "**IIRC** we discussed this pattern before."
- "**TL;DR** — great PR, minor nits."

---

## 13. Tone — Be Kind!

### ✓ Kind feedback

- "What if we tried X?"
- "Have you considered Y?"
- "I'd suggest..."
- "Just a thought..."

### ✗ Rude feedback (AVOID!)

- "This is wrong."
- "Don't do this."
- "Why did you do this?" (açıq aqqressiv)
- "This is bad code."

### Reframe

- ✗ "This is wrong." → ✓ "Could we check if X handles Y?"
- ✗ "Don't do this." → ✓ "Consider using X instead."

---

## 14. Review Approval Levels

### Approve

Kod hazırdır, merge et.

### Approve with comments

Kod hazırdır, amma kiçik iradlar.

### Request changes

Dəyişiklik tələb edir, bloklayır.

### Comment only

Fikir bildirir, approve / block yox.

---

## 15. GitHub-specific Terms

### Merge strategies

- **Merge**: normal merge commit
- **Squash**: all commits → single
- **Rebase**: linear history

### PR states

- **Draft**: hazır deyil
- **Open**: review gözləyir
- **Closed**: bağlanıb (merged / rejected)
- **Merged**: merge edilib

### Labels

- `good first issue`
- `help wanted`
- `bug`, `feature`, `documentation`
- `breaking change`

---

## Nümunə Code Review

### PR comments nümunə

```
Reviewer: @reviewer

## Overall
Nice work on the auth module! A few comments below.

## Comments

### file.py line 23
suggestion: Extract this into a helper function.
It's getting complex.

### file.py line 45
nit: rename `x` to `userId` for clarity.

### file.py line 78
question: What if the token is expired? How is that
handled?

### file.py line 102
praise: Great use of the Strategy pattern here!

### tests/test_file.py
blocking: Missing test for error cases. Please add
tests for invalid tokens and expired tokens.

## Approval
Will approve once error case tests are added!
```

---

## 16. Open Source Review

### İlk PR-ına hazırlıq

- CONTRIBUTING.md oxu
- Style guide izlə
- Linter / formatter işlət
- Tests əlavə et

### Maintainer olma

- Səbirli ol
- İlk dəfə contribute edənlərə mentor ol
- "praise" etməyi unutma

---

## Azərbaycanlı Səhvləri

- ✗ "This code is bad."
- ✓ "**Could we** refactor this? It's hard to follow."

- ✗ "You must add tests."
- ✓ "**Could you** add tests for X case?"

- ✗ "Wrong!"
- ✓ "I think there's a bug — when X, Y happens."

---

## Xatırlatma

**Yaxşı Reviewer:**
1. ✓ Kind tone
2. ✓ Specific feedback
3. ✓ Suggests, not demands
4. ✓ Asks questions
5. ✓ Gives praise
6. ✓ Blocking vs nit aydın

**Qızıl qayda:** "Could we...?" / "What if...?" istifadə et.

→ Related: [pr-descriptions.md](../../skills/writing/pr-descriptions.md), [git-commit-messages.md](../../skills/writing/git-commit-messages.md), [tech-idioms.md](../idioms/tech-idioms.md)
