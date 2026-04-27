# Security

Backend developer üçün **praktik security** materialları. Hər fayl real layihələrdə tətbiq olunan müdafiə mexanizmlərini, authentication/authorization pattern-lərini və secrets idarəetməsini əhatə edir.

**Toplam: 5 mövzu**

---

## Səviyyə Göstəriciləri

| Göstərici | Səviyyə |
|-----------|---------|
| ⭐⭐ Middle | Hər backend developer bilməlidir |
| ⭐⭐⭐ Senior | Arxitektura qərarı, trade-off analizi tələb edir |

---

## ⭐⭐ Middle — Əsas Security Bilikləri

| Fayl | Mövzu |
|------|-------|
| [01-security-best-practices.md](01-security-best-practices.md) | OWASP Top 10, injection, XSS, CSRF, file permissions, dependency audit |
| [02-oauth2-jwt-oidc.md](02-oauth2-jwt-oidc.md) | OAuth2 flows, JWT imzalama/doğrulama, OIDC, PKCE, token refresh |

---

## ⭐⭐⭐ Senior — Arxitektura Səviyyəsində Security

| Fayl | Mövzu |
|------|-------|
| [03-secrets-management.md](03-secrets-management.md) | Vault, AWS Secrets Manager, rotation, env vs secret store |
| [04-api-security-patterns.md](04-api-security-patterns.md) | API key management, rate limiting, request signing, mTLS |
| [05-authorization-rbac-abac.md](05-authorization-rbac-abac.md) | RBAC, ABAC, ReBAC, OPA/Casbin, policy engine seçimi |

---

## Tövsiyə Olunan Oxuma Ardıcıllığı

### Backend Developer üçün (Middle → Senior)

```
1. security-best-practices   — OWASP, temel müdafiə texnikaları
2. oauth2-jwt-oidc           — authentication protocol-ları
3. api-security-patterns     — API-lərə xas hücum vektorları
4. authorization-rbac-abac   — kimə nə icazə verilir — dizayn et
5. secrets-management        — production-da credentials necə saxlanır
```

### Sürətli Axtarış

```
Injection / XSS / CSRF?          → 01-security-best-practices
JWT doğrulama / OAuth2 flow?     → 02-oauth2-jwt-oidc
API key, rate limit, mTLS?       → 04-api-security-patterns
Role-based / permission sistem?  → 05-authorization-rbac-abac
Vault, secret rotation?          → 03-secrets-management
```

---

## Əhatə Olunan Mövzular

- **OWASP Top 10** — injection, broken auth, IDOR, security misconfiguration
- **Authentication** — OAuth2 (Authorization Code + PKCE, Client Credentials), JWT (HS256/RS256), OIDC, refresh token rotation
- **Authorization** — RBAC, ABAC, ReBAC; OPA (Open Policy Agent), Casbin
- **API Security** — API key lifecycle, request signing (HMAC), mTLS, input validation
- **Secrets Management** — HashiCorp Vault, AWS Secrets Manager, secret rotation, 12-factor app principles
- **Dependency Audit** — `composer audit`, `npm audit`, CVE tracking
- **Security Headers** — CSP, HSTS, X-Frame-Options, CORS konfigurasiyas
