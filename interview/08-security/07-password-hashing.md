# Password Hashing (bcrypt, Argon2) (Middle ⭐⭐)

## İcmal

Password hashing — istifadəçi parolunu birbaşa saxlamaq əvəzinə, geri qaytarılmaz şəkildə hash-ə çevirmək prosesidir. MD5, SHA1 kimi ümumi kriptografik hash funksiyaları parol üçün istifadəyə uyğun deyil — burada bcrypt, Argon2id kimi yavaş, cost-parametrli alqoritmlər vacibdir. Interview-da bu mövzu security əsaslarınızı ölçür. Düzgün password hashing = database breach-inin real dünyadakı zərərini minimuma endirmək.

## Niyə Vacibdir

2012-ci ildə LinkedIn breach-indən 117 milyon SHA1-unsalted hash dump olundu, günlər içindəqırıldı. bcrypt olsaydı illər alardı. 2019-cu ildə Collection #1 breach-i 773 milyon credential paylaşdı — əksəriyyəti zəif hashlarla. Parolları düzgün hash etmək — istifadəçi məlumatlarını qorumağın ən fundamental addımıdır. Database breach baş versə belə, hash-lər düzgün seçilmişsə parollar güvəndədir.

## Əsas Anlayışlar

- **Hash vs Encryption**: Hash geri döndürülməzdir — orijinal parolu hash-dən almaq mümkün deyil. Encryption reversible-dir (key ilə). Parol həmişə hash edilməlidir, heç vaxt encrypt edilməməlidir
- **Salt**: Hash-ə əlavə edilən random string — eyni parol fərqli salt ilə fərqli hash-lər verir. Salt hash-in bir hissəsi kimi saxlanır (gizli olması lazım deyil). bcrypt salt-ı avtomatik generate edir
- **Rainbow Table Attack**: Əvvəlcədən hesablanmış hash-parol cütlüklərindən ibarət cədvəl — "password123" → hash-i bilinir. Salt bu hücumu məhv edir: eyni parol, fərqli salt → fərqli hash → cədvəldə yoxdur
- **Brute Force Attack**: Bütün mümkün kombinasiyaları sınamaq. Hash per second əhəmiyyətlidir: MD5 = 10 billion/s (GPU ilə), bcrypt(12) = 4/s. Fərq = attack 2.5 milyard× bahalılaşır
- **Dictionary Attack**: Sözlük sözlərini + kombinasiyaları sınamaq. "password", "password123", "P@ssw0rd". Breach check (HIBP API) bu sözlükdən istifadə edilmiş parolları detect edir
- **Cost factor / Work factor**: bcrypt-in iterasiya sayı (rounds). 2^cost iterasiya. Cost artdıqca hash yavaşlayır — brute force bahalılaşır. Cost 12 = ~100ms; cost 14 = ~400ms; cost 16 = ~1600ms. Hardware sürətləndikcə cost-u artırmaq lazımdır
- **bcrypt (1999)**: Blowfish cipher-a əsaslanan, Niels Provos + David Mazières tərəfindən. Cost factor 4-31. Output: `$2y$12$saltSALTsaltSALT...hashHASHhash`. PHP-də `PASSWORD_BCRYPT`. 72 byte limit — 72+ char parolu truncate edir
- **Argon2 (2015)**: PHC (Password Hashing Competition) qalibi. 3 variant: Argon2d (GPU resistance), Argon2i (side-channel resistance), Argon2id (hər ikisi — recommended). Parametrler: `memory_cost` (KB RAM), `time_cost` (iterations), `parallelism` (threads)
- **Memory-hard**: Argon2 çox RAM tələb edir — GPU/ASIC ilə parallel brute force çətinləşir. bcrypt yalnız CPU-bound. Argon2id modern hardware-ə qarşı daha güclü
- **`PASSWORD_DEFAULT`**: PHP-nin default hash alqoritmi — hazırda `PASSWORD_BCRYPT`. Zamanla Argon2id-ə keçəcək. `password_hash($pass, PASSWORD_DEFAULT)` — alqoritm upgrade-i avtomatik
- **`password_needs_rehash()`**: Alqoritm ya da cost dəyişibsə true qaytarır. Login zamanı yoxla, lazımsa yenidən hash et — seamless migration
- **Pepper**: Bütün hash-lərə database xaricindən əlavə edilən gizli string — environment variable-da saxlanır. DB breach olsa belə pepper olmadan hash-lər crack edilmir. Versioned pepper: `PEPPER_V1`, `PEPPER_V2` — rotation dəstəyi
- **Timing attack**: String comparison `===` — biri tez, biri gec qurtarır — timing fərqdən parol öyrənilə bilər. `hash_equals()` constant-time comparison. Laravel-in `Hash::check()` daxilən `hash_equals()` istifadə edir
- **NIST SP 800-63B (2017)**: Parol siyasəti tövsiyəsi. Min 8 char (tövsiyə: 12+). Complexity rules (uppercase, symbol) NIST-ə görə optimal deyil — parol uzunluğu daha vacib. Breach check (HIBP API). Max length limit (72 bcrypt limit xaricində) olmamalı. Periodic rotation zorunlu deyil (tövsiyə edilmir artıq)
- **bcrypt 72 byte limit**: bcrypt yalnız ilk 72 byte-ı işlər. Həll: `bcrypt(sha256(password))` — önce sha256 ilə hash et, sonra bcrypt. Uzun parollar truncate edilmir
- **HIBP (Have I Been Pwned) API**: k-Anonymity model — pароlun SHA1-in ilk 5 char-ı göndərilir (`GET /range/5BAA6`), server həmin prefix ilə başlayan bütün breach hash-lərini qaytarır. Tam parol/hash göndərilmir — privacy qorunur

## Praktik Baxış

**Interview-da yanaşma:**
"bcrypt istifadə edirəm" yetərlidir, amma əla cavab niyə MD5/SHA1-in yetərsiz olduğunu, cost factor-un performans/security trade-off-unu, Argon2id-in bcrypt-dən nə üçün daha müasir olduğunu izah edir. Pepper-in rolunu bilmək sizi fərqləndirir.

**Follow-up suallar (top companies-da soruşulur):**
- "MD5 ilə parol hash etmək niyə yanlışdır?" → Çox sürətlidir — GPU ilə 10 billion hash/saniyə. Salt olmadığı hallarda rainbow table. Kriptografik collision zəiflikləri (hash collision, preimage). MD5 ümumi data integrity üçün deyil, parol üçün nəzərdə tutulmayıb
- "Cost factor-u artırmağın limiti nədir?" → ~300ms server latency. Hər login 300ms — real user üçün qəbul ediləbilən. Cost 14: ~400ms. 1000 concurrent login = ~400ms/each. Login rate limiting ilə birlikdə cost artırmaq mümkündür
- "Argon2id niyə bcrypt-dən daha yaxşıdır?" → Memory-hard: GPU/ASIC ilə paralel brute force çox bahalı. bcrypt yalnız CPU-bound — GPU acceleration effektiv. Argon2id daha modern, PHC qalib, NIST SP 800-63B tövsiyə edir
- "Pepper nədir, nə üçün lazımdır?" → DB breach-inə əlavə qoruma. DB alınan attacker hash-ləri crack etmək istəyir — pepper olmadan crack edilə bilər. Pepper env variable-da (DB xaricindədir) — DB leak yetərli deyil. `HMAC(hash, pepper)`
- "bcrypt 72 byte limit-i bildiyinizdən söz açın" → bcrypt yalnız ilk 72 byte-ı işlər — 73+ char parolu truncate edir. Attack: "password" vs "password+73+more+chars" eyni hash. Həll: `bcrypt(base64(sha256(password)))` — önce 32-byte SHA256 hash, sonra bcrypt. Hmm! Laravel `Hash::make()` bunu etmir by default — Laravel documentation-da bu limitdən söz edilir

**Ümumi səhvlər (candidate-ların etdiyi):**
- `md5($password)`, `sha256($password)` — heç vaxt!
- Salt-siz hash — rainbow table hücumuna açıqdır
- Cost factor-u çox aşağı saxlamaq (4-6) — modern GPU ilə sürətli brute force
- `hash_equals()` əvəzinə `===` comparison — timing attack riski

**Yaxşı cavabı əla cavabdan fərqləndirən:**
72 byte bcrypt limit-ini bilmək, timing attack-ı izah etmək, pepper rotation strategiyasını bilmək, HIBP k-anonymity model-ini izah etmək, `password_needs_rehash()` ilə seamless migration.

## Nümunələr

### Tipik Interview Sualı

"Parolları veritabanında necə saxlamalısınız? bcrypt niyə SHA256-dan daha yaxşıdır?"

### Güclü Cavab

"Parolu heç vaxt plain text saxlamaq olmaz. SHA256 ümumi kriptografik hash-dir — çox sürətlidir, GPU ilə milyardlarla hash/saniyə hesablamaq olar, brute force ucuzlaşır. bcrypt məqsədli olaraq yavaşdır — cost factor 12 ilə bir hash ~100ms çəkir. Real user üçün əhəmiyyətsiz, amma attacker üçün milyardlarla cəhd = illər.

Salt avtomatik əlavə olunur — eyni parol fərqli hash verir, rainbow table-lar işləmir.

Laravel-də `Hash::make()` — bcrypt default, `PASSWORD_ARGON2ID` ilə Argon2id seçmək olar. Argon2id daha müasir — memory-hard, GPU resistance daha güclü.

Əlavə: pepper env-də saxla (DB breach-ə qarşı). Login zamanı `password_needs_rehash()` yoxla — seamless cost upgrade."

### Kod/Konfiqurasiya Nümunəsi

```php
// ============================================================
// BAD vs GOOD — Password Hashing
// ============================================================

// ❌ Heç vaxt belə etmə
$hash = md5($password);               // 10 billion/s GPU-da
$hash = sha1($password);              // Sürətli, SHA1 deprecated
$hash = sha256($password);            // SHA256 performans mükəmməl — parol üçün deyil
$hash = base64_encode($password);     // Bu hash deyil!
$hash = openssl_encrypt($password);   // Encryption — decrypt edilə bilər, parol üçün YANLIŞ

// ✅ Laravel — bcrypt (default cost: 12)
$hash = Hash::make($password);
// Output: $2y$12$[22 char salt][31 char hash]
// Misal: $2y$12$abcdefghijklmnopqrstuuABCDEFGHIJKLMNOPQRSTUVWXYZ01234

// ✅ Doğrulama — timing-safe
if (Hash::check($password, $storedHash)) {
    // Login success
    
    // Seamless cost upgrade
    if (Hash::needsRehash($storedHash)) {
        $user->update(['password' => Hash::make($password)]);
    }
}

// ❌ Timing attack riski
if ($inputHash === $storedHash) { ... }  // Variable-time comparison

// ✅ Constant-time (hash_equals daxilən)
// Hash::check() artıq constant-time istifadə edir — safe
```

```php
// ============================================================
// Cost Factor Benchmark — Serverdə optimal cost tapın
// ============================================================

class BcryptBenchmark
{
    /**
     * Target: ~300ms per hash
     * Run this once on production server to find optimal cost
     */
    public static function findOptimalCost(int $targetMs = 300): int
    {
        for ($cost = 8; $cost <= 20; $cost++) {
            $start = microtime(true);
            password_hash('benchmark_password', PASSWORD_BCRYPT, ['cost' => $cost]);
            $ms = (microtime(true) - $start) * 1000;

            echo "Cost {$cost}: {$ms}ms\n";

            if ($ms >= $targetMs) {
                echo "→ Optimal cost: {$cost}\n";
                return $cost;
            }
        }

        return 14; // Default
    }
}

// config/hashing.php
return [
    'driver' => 'bcrypt',
    'bcrypt' => [
        'rounds' => env('BCRYPT_ROUNDS', 12),
        // Benchmark ilə tapılan cost-u .env-ə yazın
        // Hər yeni server nəsli üçün yenidən benchmark
    ],
    'argon' => [
        'memory'  => 65536,  // 64 MB RAM — GPU resistance
        'threads' => 1,
        'time'    => 4,      // 4 iterations
    ],
];
```

```php
// ============================================================
// Argon2id — Modern seçim
// ============================================================

// ✅ Argon2id ilə hash
$hash = Hash::driver('argon2id')->make($password);
// Output: $argon2id$v=19$m=65536,t=4,p=1$[salt]$[hash]

// ✅ Doğrulama — driver avtomatik detect edilir
Hash::check($password, $hash); // hash format-ına görə driver seçir

// ✅ PHP built-in Argon2id
$hash = password_hash($password, PASSWORD_ARGON2ID, [
    'memory_cost' => 65536, // 64 MB
    'time_cost'   => 4,
    'threads'     => 1,
]);
```

```php
// ============================================================
// Pepper — Database breach-ə əlavə qoruma
// ============================================================

class PasswordService
{
    /**
     * Pepper rotation üçün versioned approach
     * PEPPER_CURRENT=v2, PEPPER_V1=old_pepper, PEPPER_V2=new_pepper
     */
    public function hash(string $password): string
    {
        $pepperVersion = config('security.pepper_current', 'v1');
        $pepper        = config("security.peppers.{$pepperVersion}");

        // Pepper + parol birlikdə hash edilir
        return Hash::make($this->pepperize($password, $pepper))
            . ':' . $pepperVersion; // Version-u hash-ə əlavə et (rotation üçün)
    }

    public function verify(string $password, string $storedHash): bool
    {
        // Version çıxar
        [$hash, $version] = explode(':', $storedHash, 2) + [$storedHash, 'v1'];
        $pepper = config("security.peppers.{$version}");

        return Hash::check($this->pepperize($password, $pepper), $hash);
    }

    public function needsRehash(string $storedHash): bool
    {
        [, $version] = explode(':', $storedHash, 2) + ['', config('security.pepper_current')];
        return $version !== config('security.pepper_current') || Hash::needsRehash(explode(':', $storedHash)[0]);
    }

    private function pepperize(string $password, string $pepper): string
    {
        // HMAC ilə pepper — sadə concatenation-dan daha güclü
        return hash_hmac('sha256', $password, $pepper);
    }
}

// config/security.php
return [
    'pepper_current' => env('PEPPER_CURRENT', 'v1'),
    'peppers' => [
        'v1' => env('PEPPER_V1', ''),  // Production-da güclü random string
        'v2' => env('PEPPER_V2', ''),  // Rotation edəndə yeni version
    ],
];
```

```php
// ============================================================
// bcrypt 72 byte limit — həll
// ============================================================

class PasswordHasherWithLongSupport
{
    /**
     * 72 byte-dan uzun parolları düzgün handle et
     * Həll: SHA256 ilə önce compress, sonra bcrypt
     */
    public function hash(string $password): string
    {
        // SHA256 → 32 byte (256 bit) → base64 → 44 char — bcrypt üçün safe
        $normalized = base64_encode(hash('sha256', $password, true));
        return Hash::make($normalized);
    }

    public function verify(string $password, string $hash): bool
    {
        $normalized = base64_encode(hash('sha256', $password, true));
        return Hash::check($normalized, $hash);
    }
}

// ⚠️ Qeyd: Əksər tətbiqlərdə 72 char parol real world-da nadir
// Laravel-in password max: 8192 char — laravel validation qatında limit
// bcrypt 72 byte truncation: real risk deyil əgər validation-da max:72 varsa
// Amma long password support istəyirsənsə sha256+bcrypt yanaşması
```

```php
// ============================================================
// NIST-compliant Password Validator + HIBP Check
// ============================================================

class PasswordValidator
{
    public function validate(string $password): ValidationResult
    {
        $errors = [];

        // NIST SP 800-63B — minimum uzunluq
        if (mb_strlen($password) < 12) {
            $errors[] = 'Minimum 12 characters required';
        }

        // Maksimum uzunluq (ağlabatan limit)
        if (mb_strlen($password) > 128) {
            $errors[] = 'Maximum 128 characters allowed';
        }

        // NIST: Complexity rules tələb etmə — uzunluq daha vacib
        // (uppercase, digit, symbol tələb etmək — user-ı predictable pattern-ə sürür)

        // Breach check — k-Anonymity model
        if ($this->isPwnedPassword($password)) {
            $errors[] = 'This password appeared in known data breaches. Please choose another';
        }

        // Asan tapılan parollar
        $commonPasswords = ['password', '123456789', 'qwerty123', 'letmein'];
        if (in_array(strtolower($password), $commonPasswords, true)) {
            $errors[] = 'This password is too common';
        }

        return new ValidationResult(empty($errors), $errors);
    }

    private function isPwnedPassword(string $password): bool
    {
        $sha1    = strtoupper(sha1($password));
        $prefix  = substr($sha1, 0, 5);   // Yalnız 5 char göndər
        $suffix  = substr($sha1, 5);

        try {
            $response = Http::timeout(3)
                ->withHeaders(['Add-Padding: true']) // k-Anonymity daha güclü
                ->get("https://api.pwnedpasswords.com/range/{$prefix}");

            if (!$response->ok()) {
                return false; // API əlçatan deyilsə, proceed et (fail open)
            }

            // Cavabda: SUFFIX:COUNT formatında sıra var
            $lines = explode("\r\n", $response->body());
            foreach ($lines as $line) {
                [$hashSuffix] = explode(':', $line);
                if (strtoupper($hashSuffix) === $suffix) {
                    return true; // Breach-də tapıldı
                }
            }
        } catch (\Exception $e) {
            Log::warning('HIBP API check failed', ['error' => $e->getMessage()]);
        }

        return false;
    }
}
```

### Attack/Defense Nümunəsi

```
RAINBOW TABLE ATTACK (salt olmadan):

Precomputed table:
  "password"   → 5f4dcc3b5aa765d61d8327deb882cf99 (MD5)
  "admin"      → 21232f297a57a5a743894a0e4a801fc3 (MD5)
  "123456"     → e10adc3949ba59abbe56e057f20f883e (MD5)

DB leaked: user hash = 5f4dcc3b5aa765d61d8327deb882cf99
Lookup → "password" — instant crack!

DEFENSE — Salt ilə:
  salt = "g4f8h2k9" (random, DB-də saxlanır)
  hash = MD5("g4f8h2k9" + "password") = completely different hash
  Rainbow table-da bu kombinasiya yoxdur → crack çox çətinləşir

DEFENSE — bcrypt ilə (salt built-in):
  hash = bcrypt("password", cost=12, salt=random)
  GPU ilə: 4 hash/saniyə
  10^9 possible 8-char passwords: 250 milyon saniyə = 8 il → impractical

BRUTE FORCE TIME COMPARISON:
  Algorithm      | Speed (GPU)    | 8-char crack time
  ───────────────|────────────────|──────────────────
  MD5            | 10B hash/s     | ~1 second
  SHA-256        | 8B hash/s      | ~10 seconds
  bcrypt (cost=12)| 4 hash/s      | ~8 years
  Argon2id       | 0.5 hash/s     | ~63 years
```

## Praktik Tapşırıqlar

1. Öz serverinizdə cost 10, 12, 14, 16 üçün bcrypt benchmark edin — `microtime()` ilə, 300ms verən cost-u tapın
2. Argon2id parametrlərini tənzimləyin — 64MB RAM, 4 iterasiya ilə: kaç milliseconds?
3. `Hash::needsRehash()` implement edin — login zamanı köhnə bcrypt(10) hash-ləri bcrypt(14)-ə upgrade edin
4. HIBP API ilə breach check implement edin — k-anonymity model-ini verify edin: `SHA1[0:5]` göndərilir
5. bcrypt-in 72 byte limitini test edin: 72 char + 73 char parol — eyni hashmi qaytarır?
6. Pepper implement edin + versioned rotation: PEPPER_V1-dən V2-yə keçid zamanı köhnə hash-lər yenilənir
7. Timing attack-ı simulate edin: `===` vs `hash_equals()` — microtime fərqi ölçün

## Əlaqəli Mövzular

- `04-authentication-authorization.md` — Password auth konteksti, login flow
- `13-data-encryption.md` — Hash vs Encryption fundamental fərqi
- `08-secrets-management.md` — Pepper-i haraya saxlamaq, rotation
- `09-input-validation.md` — Password strength validation, NIST rules
