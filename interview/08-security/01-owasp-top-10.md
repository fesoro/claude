# OWASP Top 10 (Middle ⭐⭐)

## İcmal

OWASP (Open Web Application Security Project) Top 10 — veb tətbiqlərdəki ən kritik 10 təhlükəsizlik riskini sıralayan sənəddir. Hər 3-4 ildə bir yenilənir, 2021-ci il versiyası hazırda aktualdır. Interview-da bu mövzu backend developer-ın əsas security awareness-ini ölçür — hər orta-senior developer OWASP Top 10-u bilməlidir. Bu riskləri bilmək nəzəri deyil — hər biri real breach-lərdən gəlir, milyardlarla dollar ziyan verib.

## Niyə Vacibdir

OWASP Top 10 real breach-lərin statistikasına əsaslanır — bu risklər gerçəkdir. Laravel, Spring kimi framework-lər bəzi riskləri azaldır, lakin developer-ın bunları bilməsi vacibdir. Security güvənlik mühəndisi olmaq deyil — amma "mən bu faylı istifadəçi adı olaraq yükləyə bilərəm" riski görə bilmək lazımdır. 2024-cü ildə OWASP-ın tədqiqatına görə dünyada veb tətbiqlərin 94%-i ən azı bir OWASP riski daşıyır.

## Əsas Anlayışlar

- **A01:2021 — Broken Access Control**: Ən kritik risk (2017-dən 5-ci yerə düşmüş, 2021-də 1-ci oldu). İstifadəçi öz hüdudundan kənara çıxa bilir — başqasının datasını görür, admin funksiyasını çağırır. IDOR (Insecure Direct Object Reference) — `GET /orders/123` ilə başqasının sifarişini görmək. Vertical privilege escalation — adi user admin API-ni çağırır. Çox vaxt `$id` parametrini ownership yoxlaması olmadan istifadə etməkdən qaynaqlanır
- **A02:2021 — Cryptographic Failures**: Sensitive datanın düzgün qorunmaması — plain text şifrə DB-də, kredit kartı HTTP üzərindən, MD5/SHA1 ilə şifrə hash. TLS yoxdur, köhnə TLS 1.0 aktiv, zəif cipher suite-lar. GDPR/PCI DSS uyğunsuzluğu birbaşa bu kateqoriyadan qaynaqlanır
- **A03:2021 — Injection**: SQL, NoSQL, OS command, LDAP, expression language injection. User input validasiya edilmədən sistemə göndərilir. Laravel Eloquent sizi qoruyur, amma `DB::raw()` + user input = risk. OS command injection: `system("ping " . $request->ip)` → `127.0.0.1; rm -rf /`
- **A04:2021 — Insecure Design**: Arxitektura səviyyəsindəki dizayn problemləri — threat modeling edilməmişdir. Rate limit yoxdur, brute force üçün dizayn edilməmiş flow, business logic bypass. "Security as afterthought" — sonradan patch edilə bilməyən dizayn sözləri
- **A05:2021 — Security Misconfiguration**: Default parollar (admin/admin), gereksiz açıq portlar, verbose error messages production-da (`APP_DEBUG=true`), gereksiz aktiv edilmiş feature-lar, default cloud S3 bucket public access. Directory listing aktiv, backup faylları public
- **A06:2021 — Vulnerable and Outdated Components**: Köhnə library-lər, CVE-ləri düzəldilməmiş dependency-lər. Log4Shell (Log4j, 2021) — tüm Java ekosistemi. `composer audit`, `npm audit` ilə mütəmadi yoxlama lazımdır. SBOM (Software Bill of Materials) trenddir
- **A07:2021 — Identification and Authentication Failures**: Zəif parol siyasəti, brute force qoruması yoxdur, session fixation, session timeout yoxdur, "remember me" yanlış implement edilib, credential stuffing önlənməyib. MFA yoxdur, MFA bypass oluna bilər
- **A08:2021 — Software and Data Integrity Failures**: CI/CD pipeline-da güvənilməz plugin, deserialization of untrusted data, CDN-dən integrity yoxlamadan script yükləmək (`integrity` attribute yoxdur), software update mexanizmi imzasız. SolarWinds attack bu kateqoriyadan idi
- **A09:2021 — Security Logging and Monitoring Failures**: Breach-lər izlənmir, alert yoxdur, log-larda sensitive data plain text, centralized logging yoxdur, audit trail yoxdur. Orta şirkətdə breach 207 gün sonra aşkar edilir (IBM Cost of Data Breach 2023 hesabatı)
- **A10:2021 — Server-Side Request Forgery (SSRF)**: Server xarici URL-i fetch edərkən istifadəçi internal endpoint-ləri skan edə bilir. `http://169.254.169.254/latest/meta-data/` (AWS metadata) — cloud environment-da kritik. `http://localhost:6379` ilə Redis-ə, `http://internal-db:5432` ilə DB-ə daxil ola bilir. Capital One breach (2019) SSRF ilə oldu
- **CWE (Common Weakness Enumeration)**: OWASP hər riskini CWE-lərlə əlaqələndirir — daha texniki katalog. CWE-79 (XSS), CWE-89 (SQL injection), CWE-22 (Path traversal)
- **CVSS Score (Common Vulnerability Scoring System)**: Zəifliyin şiddətini 0-10 arasında qiymətləndirən skor. 9.0+ = Critical, 7.0-8.9 = High, 4.0-6.9 = Medium
- **Defense in Depth**: Çox qatlı müdafiə — bir layer bypass edilsə digəri qoruyur. Framework + validation + authorization + logging + monitoring
- **Threat Modeling**: STRIDE (Spoofing, Tampering, Repudiation, Information Disclosure, DoS, Elevation of Privilege) ilə sistemdəki risk-ləri proaktiv identify etmək

## Praktik Baxış

**Interview-da yanaşma:**
OWASP Top 10-u sıralayıb sadəcə adlarını saymaq orta cavabdır. Əla cavab hər riskin Laravel-də konkret nümunəsini verir: "Broken Access Control → Policy class olmadan direkt `$id` ilə object götürmək." Hücum vektoru + müdafiə = güclü cavab.

**Follow-up suallar (top companies-da soruşulur):**
- "Laravel sizə hansı OWASP risklərindən qoruyur, hansılardan qorumur?" → Qoruyur: CSRF token (A01), HTML encoding `{{ }}` (A03 XSS), Eloquent ORM (A03 SQLi), bcrypt (A02). Qorumur: Broken Access Control (A01 — developer policy yazmalıdır), SSRF (A10 — URL validation developer üçündür), Misconfiguration (A05 — `.env` developer idarə edir)
- "Broken Access Control-u production-da necə detect edərdiniz?" → Audit logging — hansı user hansı resursa çatdı. Rate limiting + anomaly detection — user çox resursa çatmağa çalışır. Automated security testing — OWASP ZAP, Burp Suite
- "SSRF nümunəsini izah edin" → Server `$request->url` ilə fetch edir, attacker `http://169.254.169.254` verir — AWS metadata service-ə çatır. IAM credentials alır
- "A06 (Outdated Components) üçün workflow-unuz nədir?" → `composer audit` CI/CD-da, Dependabot GitHub-da, SBOM artifact-i release-lərə əlavə etmək, security patch-ləri priority queue
- "Insecure Design-ı kod review-da necə detect edirsiniz?" → Rate limit yoxdur, business logic bypass possible (negative quantity, price manipulation), audit trail yoxdur, error message-lər sistem məlumatı verir

**Ümumi səhvlər (candidate-ların etdiyi):**
- "Framework istifadə edirəm, təhlükəsizəm" düşüncəsi — framework yardımçıdır, amma yetərli deyil
- OWASP-ı nəzəriyyə kimi bilmək, amma kodda nümunə göstərə bilməmək
- Logging/Monitoring-i security mövzusu kimi görməmək — "log-lar debug üçündür" yanlışdır
- A01 (Broken Access Control) ilə A07 (Auth Failures)-ı qarışdırmaq

**Yaxşı cavabı əla cavabdan fərqləndirən:**
OWASP-ı sadəcə sıralamamaق — "bu hücumu necə edərdilər, biz bunu necə qoruyuruq" cüt perspektivini vermək. Spesifik CVE nümunələri (Log4Shell, Capital One SSRF) çəkmək. Laravel-in həm qoruduqlarını, həm qorumadıqlarını bilmək.

## Nümunələr

### Tipik Interview Sualı

"OWASP Top 10-dan hansını bilirsəniz? Laravel-də hansı risklər ən vacibdir?"

### Güclü Cavab

"OWASP Top 10 ən kritik 10 veb təhlükəsizlik riskidir. Laravel-də ən vacib üçü:

1. **A01 Broken Access Control**: Policy class olmadan `User::find($request->id)` yazanda istifadəçi başqasının datasını ala bilər. Həll: `$this->authorize('view', $order)` — OrderPolicy.

2. **A03 Injection**: `DB::raw()` ilə user input birləşdirəndə SQL injection açılır. Həll: Eloquent ORM ya da parameterized query. SSRF: `Http::get($request->url)` — URL validation lazımdır.

3. **A05 Misconfiguration**: `APP_DEBUG=true` production-da verbose stack trace göstərir — sistem məlumatı verir. `composer audit` ilə dependency CVE-lərini yoxlamaq lazımdır.

Laravel-in avtomatik qoruması: Eloquent ORM (SQLi), `{{ }}` HTML encoding (XSS), CSRF token (CSRF), bcrypt (A02). Developer əlavə etməlidir: Authorization policy (A01), SSRF validation (A10), audit logging (A09)."

### Kod/Konfiqurasiya Nümunəsi

```php
// ============================================================
// A01: Broken Access Control
// ============================================================

// ❌ PROBLEM — istifadəçi hər order-ı görə bilər
public function show(string $id): JsonResponse
{
    $order = Order::findOrFail($id); // $id URL-dən gəlir
    return response()->json($order); // Authorization check yoxdur!
}

// ✅ HƏLL — Policy ilə authorization
public function show(string $id): JsonResponse
{
    $order = Order::findOrFail($id);
    $this->authorize('view', $order); // 403 əgər başqasının order-ıdırsa
    return response()->json($order);
}

// OrderPolicy
class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        return $user->id === $order->user_id || $user->hasRole('admin');
    }
}

// ============================================================
// A03: SQL Injection
// ============================================================

// ❌ PROBLEM
$users = DB::select("SELECT * FROM users WHERE name = '{$request->name}'");
// Hücum: name = "'; DROP TABLE users;--"

// ✅ HƏLL — Parameterized query
$users = DB::select("SELECT * FROM users WHERE name = ?", [$request->name]);
// Yaxud Eloquent (default safe):
$users = User::where('name', $request->name)->get();

// ❌ PROBLEM — Dynamic column (ORM kömək etmir)
$users = User::orderBy($request->sort_by)->get(); // injection!

// ✅ HƏLL — Whitelist
$allowedColumns = ['name', 'email', 'created_at', 'updated_at'];
$column = in_array($request->sort_by, $allowedColumns, true)
    ? $request->sort_by
    : 'created_at';
$users = User::orderBy($column)->get();

// ============================================================
// A05: Security Misconfiguration
// ============================================================

// production .env
// APP_DEBUG=false         ← Heç vaxt true olmamalıdır production-da
// APP_ENV=production
// LOG_LEVEL=error         ← Debug log-lar minimize
// DB_PASSWORD=strong_pass ← Rotation lazımdır

// S3 bucket — public access off
// AWS Console: Block All Public Access = true

// ============================================================
// A10: SSRF (Server-Side Request Forgery)
// ============================================================

// ❌ PROBLEM — istifadəçi iç şəbəkəyə/metadata-ya çata bilər
public function fetchUrl(Request $request): string
{
    $content = file_get_contents($request->url);
    // http://169.254.169.254/latest/meta-data/ → AWS credentials!
    // http://localhost:6379 → Redis
    // http://internal-db:5432 → DB
    return $content;
}

// ✅ HƏLL — URL validation + allowlist
public function fetchUrl(Request $request): string
{
    $url    = $request->validated('url');
    $parsed = parse_url($url);

    // Yalnız HTTP/HTTPS
    if (!in_array($parsed['scheme'] ?? '', ['http', 'https'], true)) {
        abort(422, 'Only HTTP/HTTPS URLs allowed');
    }

    // DNS resolve et, private IP-lərə blok
    $host = gethostbyname($parsed['host']);
    if (filter_var(
        $host,
        FILTER_VALIDATE_IP,
        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
    ) === false) {
        abort(403, 'Private or reserved IP ranges are not allowed');
    }

    // Allowlist yanaşması daha güclüdür:
    $allowedDomains = ['api.trusted-partner.com', 'feeds.news-provider.com'];
    if (!in_array($parsed['host'], $allowedDomains, true)) {
        abort(403, 'Domain not in allowlist');
    }

    return Http::timeout(5)->get($url)->body();
}
```

```php
// ============================================================
// A06: Vulnerable Components — CI/CD audit
// ============================================================

// composer.json — security audit
// $ composer audit
// Found 2 security advisories:
// - CVE-2024-XXXX in vendor/some-package 1.2.3
//   Severity: high
//   Description: Remote Code Execution

// GitHub Actions workflow
// .github/workflows/security.yml
// steps:
//   - name: Composer security audit
//     run: composer audit --format=json --no-dev
//   - name: npm audit
//     run: npm audit --audit-level=moderate

// ============================================================
// A09: Security Logging and Monitoring Failures
// ============================================================

// ✅ Authentication audit log
class LogSuccessfulLogin
{
    public function handle(Login $event): void
    {
        Log::channel('audit')->info('user.login', [
            'user_id'    => $event->user->id,
            'ip'         => request()->ip(),
            'user_agent' => request()->userAgent(),
            'occurred_at' => now()->toIso8601String(),
        ]);
    }
}

class LogFailedLogin
{
    public function handle(Failed $event): void
    {
        Log::channel('audit')->warning('user.login_failed', [
            'email'    => $event->credentials['email'] ?? 'unknown',
            'ip'       => request()->ip(),
            'attempts' => RateLimiter::attempts('login:' . request()->ip()),
        ]);
    }
}
```

### Attack/Defense Nümunəsi

```
A01 — IDOR (Insecure Direct Object Reference) Attack:

Hücum:
  Attacker: GET /api/orders/1001  → 200 OK (öz order-ı)
  Attacker: GET /api/orders/1002  → 200 OK (başqasının order-ı!)
  Attacker: GET /api/orders/1003  → 200 OK
  ... (sequential scan ilə bütün order-ları çəkir)

Müdafiə:
  1. Policy check: $this->authorize('view', $order) → 403 ForbiddenA
  2. Eloquent scope: Order::where('user_id', auth()->id())->findOrFail($id)
  3. Audit log: Bir user-ın çoxlu 403 → alert

A10 — SSRF Attack (Capital One 2019 breach):
  Hücum:
    POST /v1/image-processor
    Body: {"url": "http://169.254.169.254/latest/meta-data/iam/security-credentials/"}
    
    Response: {"AccessKeyId": "ASIA...", "SecretAccessKey": "abc...", "Token": "..."}
    
  Attacker IAM credential-larla AWS API-yə tam giriş əldə etdi
  100 milyon müştərinin datası sızdı

  Müdafiə:
    - Private IP range-lərini DNS resolve edib blok etmək
    - IMDSv2 (AWS) — token tələb edir, SSRFdən qoruyur
    - Allowlist — yalnız müəyyən domain-lərə çıxış
```

## Praktik Tapşırıqlar

1. Öz codebase-inizdə OWASP Top 10-un hər maddəsi üçün bir risk nümunəsi tapın
2. `composer audit` ilə dependency-lərin CVE-lərini yoxlayın — kritik olanlar varmı?
3. OWASP ZAP ilə öz test mühitinizdəki saytı skan edin — nə tapır?
4. A01 üçün: codebase-inizdə `findOrFail($id)` sonrasında `authorize()` olmayan yerləri axtarın
5. A10 SSRF üçün: external URL fetch edən bütün endpoint-ləri tapın, URL validation varmı?
6. "Security Misconfiguration" üçün production checklist hazırlayın: debug mode, error display, default passwords, S3 public access
7. A09 üçün: failed login, admin access, sensitive data export kimi audit event-lər log edilirmi?
8. Threat modeling: 3 əsas attack vectoru müəyyən edin, hər biri üçün STRIDE analizi

## Əlaqəli Mövzular

- `02-sql-injection.md` — A03 Injection dərinliyi, attack mechanics
- `03-xss-csrf.md` — A03 XSS injections, A01 CSRF
- `04-authentication-authorization.md` — A07 Authentication, A01 Authorization
- `10-security-headers.md` — A05 Misconfiguration, CSP, HSTS
- `11-least-privilege.md` — A01 Broken Access Control müdafiəsi
- `12-audit-logging.md` — A09 Security Logging
