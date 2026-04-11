# OWASP Top 10 (PHP Kontekstdə)

## Mündəricat
1. [A01 — Broken Access Control](#a01--broken-access-control)
2. [A02 — Cryptographic Failures](#a02--cryptographic-failures)
3. [A03 — Injection](#a03--injection)
4. [A04 — Insecure Design](#a04--insecure-design)
5. [A07 — Auth Failures](#a07--auth-failures)
6. [A08 — Integrity Failures (Deserialization)](#a08--integrity-failures-deserialization)
7. [Digər vacib zəifliklər](#digər-vacib-zəifliklər)
8. [PHP İmplementasiyası](#php-implementasiyası)
9. [İntervyu Sualları](#intervyu-sualları)

---

## A01 — Broken Access Control

```
Ən geniş yayılmış zəiflik.
İstifadəçi etməməli olduğu əməliyyatları edə bilir.

Nümunələr:
  GET /admin/users          → auth yoxlaması yoxdur
  GET /orders/999           → başqasının sifarişi
  ?debug=true               → admin panel görsənir
  IDOR (Insecure Direct Object Reference):
    GET /users/42/profile   → user 43-ün profilini görə bilir

PHP nümunəsi (vulnerable):
  $id = $_GET['id'];
  $user = User::find($id); // Hər user-ın məlumatını qaytarır!

Düzgün:
  $id = $_GET['id'];
  $user = User::find($id);
  if ($user->id !== auth()->id()) {
      abort(403);
  }

Qaydalar:
  - Default DENY: icazə veriləni açıq müəyyən et
  - Hər endpoint-də authorization yoxla (middleware deyil, policy)
  - Rate limiting admin endpointlərə
  - Object-level authorization (policy sənədlər)
```

---

## A02 — Cryptographic Failures

```
Həssas data düzgün qorunmur.

Zəif şifrələmə:
  MD5, SHA1 — password hashing üçün YASAQDIR
  ECB mode  — şəkil içindəki pattern-ləri saxlayır
  Zəif random (mt_rand) — token generasiyası üçün

PHP nümunəsi (vulnerable):
  $hash = md5($password);         // ❌ brute-force mümkün
  $hash = sha1($password);        // ❌
  $token = mt_rand(1000, 9999);   // ❌ predictable

Düzgün:
  $hash  = password_hash($password, PASSWORD_ARGON2ID);
  $token = bin2hex(random_bytes(32));  // CSPRNG

Digər qaydalar:
  HTTPS everywhere (HSTS header)
  Sensitive data DB-də encrypt (AES-256-GCM)
  SSL certificate doğruluğunu yoxla (peer verification)
  Cookie: Secure, HttpOnly flagları
  Sensitive data log-a yazmaq olmaz (password, token, CC)
```

---

## A03 — Injection

```
SQL Injection:
  Vulnerable:
    $sql = "SELECT * FROM users WHERE name = '$name'";
    // name = "' OR '1'='1" → bütün userlar!
    // name = "'; DROP TABLE users; --"

  Düzgün (Prepared Statements):
    $stmt = $pdo->prepare("SELECT * FROM users WHERE name = ?");
    $stmt->execute([$name]);

Command Injection:
  Vulnerable:
    shell_exec("ping " . $_GET['host']);
    // host = "8.8.8.8; rm -rf /"

  Düzgün:
    $host = escapeshellarg($_GET['host']);
    shell_exec("ping " . $host);
    // Daha yaxşı: whitelist validation əvvəl

LDAP Injection, XPath Injection — eyni prinsip.

Serialization Injection:
  unserialize($userInput) → Object injection!
  Düzgün: JSON istifadə et, serialize/unserialize-dan qaç.
```

---

## A04 — Insecure Design

```
Dizayn səviyyəsindəki problemlər — kod yazılmamışdan əvvəl.

Nümunələr:
  "Şifrəni unutdum" → email göndərmək əvəzinə şifrəni göstərir
  Unlimited retry → brute-force üçün açıqdır
  Rate limiting yoxdur → DDOS/scraping
  Debug mode production-da

PHP/Laravel nümunəsi:
  APP_DEBUG=true production-da → stack trace, DB credentials görünür!

Dizayn qaydaları:
  Threat modeling əvvəldən
  Security requirements feature-la birlikdə
  Least privilege principle
  Fail securely (xəta zamanı default DENY)
```

---

## A07 — Auth Failures

```
Authentication zəiflikləri:

Brute-force:
  Limitsiz login cəhdi → şifrəni tap
  Həll: Rate limiting + account lockout

Credential stuffing:
  Başqa service-dən oğurlanmış user/pass listəsi
  Həll: Multi-factor auth, breach detection

Zəif şifrə siyasəti:
  "123456" qəbul edilir
  Həll: NIST guidelines — min uzunluq, breach check

Session fixation:
  Login-dən sonra session ID dəyişdirilmir
  Həll: session_regenerate_id(true) login-dən sonra

PHP nümunəsi:
  // ❌ Yanlış
  session_start();
  $_SESSION['user_id'] = $user->id;

  // ✅ Düzgün
  session_start();
  session_regenerate_id(true); // Yeni session ID
  $_SESSION['user_id'] = $user->id;

Cookie təhlükəsizliyi:
  session.cookie_httponly = 1
  session.cookie_secure   = 1
  session.cookie_samesite = Strict
```

---

## A08 — Integrity Failures (Deserialization)

```
PHP unserialize() — Remote Code Execution riski!

Vulnerable:
  $data = unserialize($_COOKIE['cart']);
  // Cookie tamper edilib, malicious class inject edilib
  // __wakeup() və __destruct() metodu çağırılır!

PHP Object Injection nümunəsi:
  class Logger {
    public $logFile;
    public function __destruct() {
      file_put_contents($this->logFile, "deleted"); // arbitrary write!
    }
  }
  // Hücumçu Logger obyekti serialize edib cookie-yə qoyur
  // unserialize → __destruct → fayl silir/yazır

Həll:
  unserialize() HEÇ VAXT user input-undan istifadə etmə!
  JSON istifadə et: json_decode($input)
  Əgər mütləq lazımdırsa: allowed_classes option
    unserialize($data, ['allowed_classes' => [SafeClass::class]]);
```

---

## Digər vacib zəifliklər

```
XSS (Cross-Site Scripting):
  Vulnerable:
    echo $_GET['name']; // <script>alert(1)</script>
  Düzgün:
    echo htmlspecialchars($_GET['name'], ENT_QUOTES, 'UTF-8');

CSRF (Cross-Site Request Forgery):
  Forged request başqa site-dan göndərilir.
  Həll: CSRF token (Laravel: @csrf), SameSite cookie

Path Traversal:
  Vulnerable:
    $file = $_GET['file'];
    readfile('/uploads/' . $file);
    // file = "../../etc/passwd"
  Düzgün:
    $file = basename($_GET['file']); // yalnız fayl adı
    $path = realpath('/uploads/' . $file);
    if (!str_starts_with($path, '/uploads/')) abort(403);

Open Redirect:
  Vulnerable:
    header('Location: ' . $_GET['redirect']);
    // redirect = https://evil.com
  Düzgün:
    Whitelist of allowed redirect URLs
```

---

## PHP İmplementasiyası

```php
<?php
// Secure password hashing
class UserRepository
{
    public function create(string $email, string $plainPassword): User
    {
        $hash = password_hash($plainPassword, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536, // 64 MB
            'time_cost'   => 4,
            'threads'     => 2,
        ]);

        return User::create(['email' => $email, 'password' => $hash]);
    }

    public function verify(User $user, string $plainPassword): bool
    {
        if (!password_verify($plainPassword, $user->password)) {
            return false;
        }

        // Password rehash lazımdırmı? (alqoritm dəyişibsə)
        if (password_needs_rehash($user->password, PASSWORD_ARGON2ID)) {
            $newHash = password_hash($plainPassword, PASSWORD_ARGON2ID);
            $user->update(['password' => $newHash]);
        }

        return true;
    }
}
```

```php
<?php
// Secure random token generation
class TokenGenerator
{
    public static function generate(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes)); // CSPRNG
    }

    public static function generateUrlSafe(int $bytes = 32): string
    {
        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
    }
}

// Input sanitization - SQL injection prevention
class UserRepository
{
    public function findByEmail(string $email): ?User
    {
        // ✅ Prepared statement — SQL injection mümkün deyil
        $stmt = $this->pdo->prepare(
            'SELECT * FROM users WHERE email = :email AND active = 1'
        );
        $stmt->execute(['email' => $email]);
        return $stmt->fetchObject(User::class) ?: null;
    }
}

// CSRF middleware (manual)
class CsrfMiddleware
{
    public function handle(Request $request, callable $next): Response
    {
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            $token = $request->input('_token') ?? $request->header('X-CSRF-TOKEN');

            if (!hash_equals(session('csrf_token'), $token ?? '')) {
                abort(419, 'CSRF token mismatch');
            }
        }

        return $next($request);
    }
}
```

---

## İntervyu Sualları

- IDOR nədir? Konkret PHP nümunəsi verə bilərsinizmi?
- PHP-də `unserialize()` niyə RCE riski daşıyır?
- `htmlspecialchars` nə vaxt yetərli deyildir?
- SQL injection-u prepared statement olmadan necə önləmək olar?
- Brute-force hücumuna qarşı hansı tədbirlər görərdiniz?
- Session fixation attack-ı nədir? PHP-də necə önlənir?
- `password_hash()` vs `hash('sha256', $password)` — fərqi nədir?
