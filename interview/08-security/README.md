# Interview — Security

Backend developer üçün security interview hazırlığı. Junior-dan Architect-ə qədər 15 mövzu — authentication-dan threat modeling-ə qədər geniş spektr. Hər mövzu real interview sualları, güclü cavab nümunəsi, Laravel/PHP kod nümunəsi ilə tamamlanır.

---

## Mövzular — Level üzrə

### Junior ⭐

| # | Fayl | Mövzu |
|---|------|-------|
| 04 | [04-authentication-authorization.md](04-authentication-authorization.md) | Authentication vs Authorization |

### Middle ⭐⭐

| # | Fayl | Mövzu |
|---|------|-------|
| 01 | [01-owasp-top-10.md](01-owasp-top-10.md) | OWASP Top 10 |
| 02 | [02-sql-injection.md](02-sql-injection.md) | SQL Injection |
| 03 | [03-xss-csrf.md](03-xss-csrf.md) | XSS and CSRF |
| 07 | [07-password-hashing.md](07-password-hashing.md) | Password Hashing (bcrypt, Argon2) |
| 09 | [09-input-validation.md](09-input-validation.md) | Input Validation and Sanitization |

### Senior ⭐⭐⭐

| # | Fayl | Mövzu |
|---|------|-------|
| 05 | [05-jwt-deep-dive.md](05-jwt-deep-dive.md) | JWT Deep Dive |
| 06 | [06-oauth2-flows.md](06-oauth2-flows.md) | OAuth 2.0 Flows |
| 08 | [08-secrets-management.md](08-secrets-management.md) | Secrets Management |
| 10 | [10-security-headers.md](10-security-headers.md) | Security Headers |
| 11 | [11-least-privilege.md](11-least-privilege.md) | Principle of Least Privilege |
| 12 | [12-audit-logging.md](12-audit-logging.md) | Audit Logging |
| 13 | [13-data-encryption.md](13-data-encryption.md) | Data Encryption at Rest and in Transit |

### Lead ⭐⭐⭐⭐

| # | Fayl | Mövzu |
|---|------|-------|
| 14 | [14-security-in-cicd.md](14-security-in-cicd.md) | Security in CI/CD Pipeline |

### Architect ⭐⭐⭐⭐⭐

| # | Fayl | Mövzu |
|---|------|-------|
| 15 | [15-threat-modeling.md](15-threat-modeling.md) | Threat Modeling |

---

## Reading Paths

### Yeni başlayanlar üçün (Junior → Middle)
`04` → `01` → `02` → `03` → `07` → `09`

Authentication/authorization anlayışlarından başlayın, sonra ən geniş yayılmış hücum növlərini öyrənin.

### Backend developer üçün (Middle → Senior)
`09` → `02` → `03` → `07` → `05` → `06` → `08` → `10`

Input validation, injection, XSS/CSRF, password hashing — buradan JWT/OAuth2/Secrets/Headers-a keçin.

### Security-focused (Senior bütün mövzular)
`05` → `06` → `08` → `10` → `11` → `12` → `13`

JWT, OAuth2, secrets idarəsi, browser security, least privilege, audit, şifrələmə — tam Senior security stack.

### Team lead / architect hazırlığı
`11` → `12` → `13` → `14` → `15`

Least privilege prinsipindən başlayaraq audit, şifrələmə, CI/CD security, threat modeling-ə qədər komanda və sistem səviyyəsindəki mövzular.

### Tez hazırlıq (top 5 interview temaları)
`01` → `02` → `05` → `06` → `15`

OWASP, SQL injection, JWT, OAuth2, threat modeling — intervyu-larda ən çox çıxan 5 mövzu.

---

## Qısa Xülasə

| Level | Mövzu sayı | Fokus |
|-------|-----------|-------|
| Junior | 1 | Autentifikasiya/Avtorizasiya əsasları |
| Middle | 5 | Ən geniş yayılmış hücum növləri, müdafiə əsasları |
| Senior | 7 | Token-lar, protokollar, browser security, encryption |
| Lead | 1 | Komanda və pipeline səviyyəsindəki security prosesləri |
| Architect | 1 | Sistemli risk analizi və proaktiv security dizaynı |
