# Claim / Argue / State / Suggest — İddia, Mübahisə, Bəyan, Təklif

## Səviyyə
B1-B2

---

## Əsas Cədvəl

| Söz | Məna | Şübhə | Rəsmilik | Kontekst |
|-----|------|-------|----------|---------|
| **claim** | iddia etmək | bəli — sübut yoxdur | neytral | şübhəli iddia |
| **argue** | dəlil gətirmək | xeyr — əsaslandırılmış | formal | analitik yazı |
| **state** | rəsmən bəyan etmək | xeyr — faktual | çox formal | sənədlər, faktlar |
| **suggest** | təklif etmək / işarə etmək | kontekstə görə | neytral | təklif, məlumat |

> **Qısa qayda:**
> - **claim** = "məncə belədir" (amma sübutu yoxdur)
> - **argue** = "dəlillərim var, belədir" (analitik)
> - **state** = "sənəddə yazır" (faktual, rəsmi)
> - **suggest** = "təklif edirəm" **yaxud** "məlumat göstərir ki..."

---

## 1. Claim — İddia Etmək (Şübhəli)

Bir şeyi həqiqət kimi söyləmək — amma dinləyən şübhə edir ya da sübut azdır.

**Diqqət:** "claim" işlətdikdə, müəllif faktın **doğru olub-olmadığından əmin deyil**.

### Struktur

- **claim** (that) + clause → "He claims (that) the API is fast."
- **claim** + to + V → "She claims to have tested it."

### Nümunələr

| İfadə | Nümunə | Azərbaycanca |
|-------|--------|-------------|
| claim that | "The client claims that our API is slow." | müştəri iddia edir ki |
| claim to have | "He claims to have fixed the bug." | düzəltdiyini iddia edir |
| docs claim | "The documentation claims Redis is optional." | sənəd iddia edir (amma biz inanmırıq) |
| claims vs reality | "They claim 99.99% uptime — but the logs say otherwise." | iddiaya qarşı həqiqət |

### Nə zaman istifadə etmə

- ✗ Öz nəzərindən yazdığın texniki sənəddə: "I claim this approach is better." (rəsmi deyil, zəif görünür)
- ✓ Başqasının iddiasını şübhə ilə aktardıqda: "The vendor claims zero downtime."

### Tech kontekst

- "The provider **claims** the service has 99.9% uptime." (siz şübhə edirsiniz)
- "The PR author **claims** the refactor doesn't break anything." (amma testlər yoxdur)
- "They **claim** to support PostgreSQL 15." (amma sənəd köhnədir)

---

## 2. Argue — Dəlil Gətirmək (Analitik)

Sübutlara əsaslanaraq mövqe bildirmək. Mübahisə etmək deyil — **analitik əsaslandırma**.

### Struktur

- **argue** (that) + clause → "I argue that microservices are overkill here."
- **argue** for/against + N → "She argued for a monolith approach."

### Nümunələr

| İfadə | Nümunə | Azərbaycanca |
|-------|--------|-------------|
| argue that | "I argued that PostgreSQL was the better choice." | mövqeyimi əsaslandırdım |
| argue for | "We argued for a simpler architecture." | dəstəklədik |
| argue against | "She argued against adding more microservices." | əleyhdarı oldu |
| one could argue | "One could argue that caching solves this." | deyilə bilər ki |

### Nə zaman istifadə et

- Design docs, Architecture Decision Records (ADR), code review, post-mortem
- "I argue that..." = professional, mövqeyini əsaslandırırsın
- ✓ PR description: "I argue that this approach reduces coupling." (güclü)

### Tech kontekst

- "In the design doc, I **argued** that vertical scaling is sufficient for now."
- "She **argued** that the current ORM is causing N+1 issues."
- "We **argued** for a queue-based solution rather than direct API calls."
- "One could **argue** that we're over-engineering this."

---

## 3. State — Rəsmən Bəyan Etmək (Faktual)

Faktı açıq-aydın, şübhəsiz şəkildə bildirmək. Sənəddə, müqaviləddə, texniki spesifikasiyada.

### Struktur

- N + **state** (that) + clause → "The contract states that..."
- **state** + N → "Please state your requirements." (formal)
- **as stated in** → "As stated in the README..."

### Nümunələr

| İfadə | Nümunə | Azərbaycanca |
|-------|--------|-------------|
| the docs state | "The docs state that Redis 6+ is required." | sənəddə yazır |
| the contract states | "The SLA states 99.9% uptime." | müqavilə bəyan edir |
| as stated | "As stated in the RFC, the endpoint is deprecated." | göstərildiyi kimi |
| it is stated that | "It is stated that the API key must be rotated monthly." | qeyd olunur ki |

### Nə zaman istifadə et

- Rəsmi faktı aktaranda: "The RFC **states** that..." ✓
- ✗ Şəxsi fikirlərdə: "I state that this is better." (yanlış — state şəxsi fikir üçün işlənmir)

### Tech kontekst

- "The README **states** that PHP 8.2+ is required."
- "The policy **states** that all data must be encrypted at rest."
- "As **stated** in the ADR, we chose PostgreSQL over MySQL."
- "The API contract **states** that the response will always include a `status` field."

---

## 4. Suggest — Təklif Etmək / İşarə Etmək

**İki fərqli məna — kontekstə görə:**
1. **Təklif** → "I suggest we refactor this." (mən tövsiyə edirəm)
2. **İşarə etmək / göstərmək** → "The data suggests a memory leak." (məlumat göstərir)

### Struktur

- **suggest** + V-ing → "I suggest **refactoring** the controller."
- **suggest** (that) + clause → "I suggest (that) we **use** Redis."
- N + **suggests** + clause → "The logs suggest (that) there's a timeout."

### ⚠ Yanlış struktur

- ✗ "I suggest to refactor this." (suggest + to V yanlışdır!)
- ✓ "I suggest **refactoring** this." (V-ing lazımdır)
- ✓ "I suggest **that we refactor** this." (clause ilə)

### Nümunələr

| İfadə | Nümunə | Azərbaycanca |
|-------|--------|-------------|
| suggest V-ing | "I suggest adding an index on this column." | tövsiyə edirəm |
| suggest that | "I suggest that we postpone the migration." | tövsiyə edirəm ki |
| data suggests | "The metrics suggest a memory leak." | məlumatlar göstərir |
| logs suggest | "The logs suggest a connection timeout." | loglar işarə edir |
| suggest an alternative | "Can you suggest a better approach?" | alternativ tövsiyə et |

### Tech kontekst

- "I **suggest** adding rate limiting before the public launch."
- "The profiler **suggests** that the bottleneck is in the DB layer."
- "The error pattern **suggests** a race condition."
- "I **suggest** we write integration tests first."

---

## Müqayisə Cədvəli

| | Claim | Argue | State | Suggest |
|-|-------|-------|-------|---------|
| Şübhə var? | bəli | xeyr | xeyr | kontekstə görə |
| Sübutla? | xeyr | bəli | — (fakt) | bəzən |
| Şəxsi fikir? | bəli | bəli | xeyr | bəli (tövsiyə) |
| Rəsmilik | neytral | formal | çox formal | neytral |
| PR/Doc-da? | nadir | ✓ çox | ✓ çox | ✓ çox |

---

## Tez-tez Yanlış İşlənənlər

| ❌ Yanlış | ✅ Düzgün |
|-----------|-----------|
| "I claim this is the best approach." | "I **argue** this is the best approach." |
| "The logs claim a timeout." | "The logs **suggest** a timeout." |
| "I suggest to use Redis." | "I suggest **using** Redis." |
| "He stated his opinion wrongly." | "He **claimed** / **expressed** his opinion." |
| "The vendor argued 99.9% uptime." | "The vendor **claimed** 99.9% uptime." |
| "The docs argued Redis is optional." | "The docs **state** Redis is optional." |
| "I argued we should use Redis." | "I **suggested** / **argued** we **use** Redis." (suggest yaxud argue işlənir) |
| "I suggest that we to refactor." | "I suggest **that we refactor**." (to yox!) |

---

## Məşq Tapşırıqları

### 1. Doğru sözu seçin (claim / argue / state / suggest)

1. "The metrics ______ there's a memory leak." (məlumat işarə edir)
2. "The README ______ that PHP 8.2 is required." (faktual, sənəddə)
3. "The vendor ______ their API has zero downtime." (şübhəli iddia)
4. "In the ADR, I ______ that a monolith is sufficient." (dəlillə mövqe)
5. "Can you ______ a better name for this variable?" (tövsiyə et)
6. "The client ______ the bug was introduced in our last release." (sübut yoxdur)
7. "The SLA ______ a maximum response time of 200ms." (rəsmi sənəd)
8. "I ______ we switch to async processing." (tövsiyə)
9. "She ______ that the current architecture won't scale." (analitik mövqe)
10. "The error pattern ______ a race condition." (məlumat göstərir)

**Cavablar:** 1. suggest, 2. states, 3. claims, 4. argued, 5. suggest, 6. claims, 7. states, 8. suggest, 9. argued, 10. suggests

### 2. Cümləni tamamlayın (düzgün struktur seçin)

1. "I ______ (refactor / to refactor / that we refactor) the payment module." (suggest)
2. "The documentation ______ Redis 6 is required." (state — düzgün forma)
3. "The new engineer ______ that the existing tests are sufficient." (claim — şübhəli)
4. "In my design doc, I ______ for event-driven architecture." (argue)
5. "As ______ in the RFC, this endpoint will be deprecated." (state — passive)
6. "The profiler ______ the bottleneck is in the N+1 queries." (suggest)

**Cavablar:** 1. suggest refactoring / suggest that we refactor, 2. states (that), 3. claims, 4. argued, 5. stated, 6. suggests

---

## Yazılı Texniki Kontekstdə Hansı Seçim?

| Ssenari | İşlət |
|---------|-------|
| PR description: öz mövqeyini əsaslandırırsan | **argue** |
| PR comment: tövsiyə edirsən | **suggest** |
| README: texniki tələbi yazırsan | **state** |
| Post-mortem: data göstərir ki | **suggest** |
| Başqa şirkətin iddiasını aktarırsan | **claim** |
| ADR: dizayn qərarını əsaslandırırsan | **argue** |
| API doc: davranışı bəyan edirsən | **state** |

---

## Əlaqəli Mövzular

- `respond-vs-reply-vs-answer-vs-react.md` — cavab vermə felləri
- `actually-currently-eventually-finally.md` — zaman bağlayıcıları
- `because-due-to.md` — səbəb bildirmə
