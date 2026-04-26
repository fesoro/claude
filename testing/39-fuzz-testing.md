# Fuzz Testing (Lead)
## İcmal

**Fuzz Testing (Fuzzing)** - proqrama **random, invalid, malformed, və ya gözlənilməz input-lar** verərək crash, memory leak, güvənlik boşluqları və ya undefined behavior-ları tapmaq üçün istifadə olunan avtomatlaşdırılmış test texnikasıdır.

**Əsas məqsəd:**

- Crash-lar tapmaq (segfault, unhandled exception)
- Security vulnerabilities aşkar etmək (buffer overflow, SQL injection)
- Denial of Service (DoS) boşluqlarını tapmaq
- Input validation səhvlərini görmək

**Tarix:** 1988-ci ildə Professor Barton Miller tərəfindən Wisconsin Universitetində icad olunub. "Fuzz" adı - random noise mənasındadır.

## Niyə Vacibdir

- **Parser/validator bug-ları**: CSV parser, JSON deserializer, XML handler kimi input emal edən kod fuzz-a çox həssasdır — manual test kifayət etmir
- **Security relevance**: Heartbleed, Shellshock kimi kritik CVE-lər fuzzing ilə tapılıb — security-critical kod üçün mütləqdir
- **Unexpected input**: Production-da istifadəçilər proqramçının düşünmədiyini yazır — fuzzer bunu simulyasiya edir
- **Automation**: Bir dəfə qurulduqdan sonra fuzz testi 24/7 işləyir, daim yeni paths kəşf edir

## Əsas Anlayışlar

### 1. Fuzz Testing Növləri

**Dumb Fuzzing (Black-box):**

- Format haqqında məlumat olmadan tamamilə random data
- Sadə implementasiya, amma dayaz coverage
- Məsələn: random bytes göndərmək

**Smart Fuzzing (Grammar-aware):**

- Input format-ını bilir (JSON, XML, HTTP)
- Struktur qoruyaraq mutate edir
- Daha dərinə girə bilir

**Coverage-Guided Fuzzing:**

- Əvvəlki run-ların code coverage-ını izləyir
- Yeni path tapanda test case-ı saxlayır
- AFL, libFuzzer bu yanaşmadan istifadə edir

### 2. Mutation vs Generation

**Mutation-based:** mövcud valid input-ları götürüb random dəyişiklər edir
```
Input: {"name": "John", "age": 30}
Mutated: {"name": "Jo\x00hn", "age": 999999999999999}
```

**Generation-based:** grammar-dan yeni input-lar yaradır
```
Grammar: SELECT * FROM <table> WHERE <col> = <value>
Generated: SELECT * FROM users WHERE id = '; DROP TABLE--
```

### 3. Populyar Alətlər

**AFL (American Fuzzy Lop):**

- Coverage-guided, genetic algorithm-lı
- C/C++ üçün de-facto standart
- `afl-fuzz`, `afl-gcc` alətləri

**libFuzzer:**

- LLVM/Clang-a daxilidir
- In-process fuzzing (eyni proses daxilində)
- Daha sürətli

**PHP Fuzzing Alətləri:**

- **PHPFuzz:** PHP extension fuzzing
- **PeachPy:** protocol fuzzing
- **Burp Suite Intruder:** web app fuzzing
- **wfuzz:** URL, parametr fuzzing
- **AFL++** ilə PHP interpreter-i fuzz etmək

### 4. Security Implications

Fuzz testing security testing-in vacib hissəsidir:

- **CVE tapılması:** məşhur CVE-lərin çoxu fuzzing ilə tapılıb
- **Zero-day aşkarlama:** OpenSSL Heartbleed, ImageMagick RCE
- **OSS-Fuzz:** Google-un open-source fuzzing infrastrukturu

### 5. Corpus (Test Data Bazası)

- **Seed corpus:** başlanğıc valid input nümunələri
- **Interesting inputs:** yeni coverage açan girişlər
- **Crash corpus:** crash edən input-lar

## Praktik Baxış

### Best Practices

1. **Good seed corpus saxlayın** - valid input nümunələri fuzzer-ə başlanğıc verir
2. **Crash-ları track edin** - hər crash ayrıca saxlanıb analiz edilməli
3. **CI-da continuous fuzz** - hər commit-də qısa fuzz run
4. **Fuzzing + sanitizers** - ASan, UBSan ilə memory bugs tapmaq
5. **Domain-spesifik dictionary** - SQL, HTTP keyword-ləri fuzzer-ə vermək
6. **Resource limits** - timeout, memory limit qoymaq (DoS etməsin)

### Anti-Patterns

1. **Fuzzing-siz security** - "Code review kifayətdir" düşünmək
2. **Crash-ları görməməzlik** - "Bu real input deyil" bəhanəsi
3. **Production-da fuzz** - production DB-də fuzz etmək fəlakətdir
4. **Short run** - 10 iterasiya yetərli deyil, minlərlə-milyonlarla lazım
5. **Environment isolation yox** - fuzzer production services-ə təsir edir
6. **Coverage ignore** - coverage-guided olmasa dayaz qalır

### Təhlükəsizlik Qeydləri

- Fuzz testing **pentesting-in tamamlayıcısıdır**, əvəzi deyil
- Heartbleed, Shellshock kimi böyük CVE-lər fuzzing ilə tapılıb
- PHP kimi managed languages-də əsasən **logic bugs** və **input validation** tapılır
- Cloud fuzzing (OSS-Fuzz, ClusterFuzz) open-source üçün pulsuzdur

## Nümunələr

### Sadə PHP Fuzzer

```php
<?php

namespace Tests\Fuzz;

use PHPUnit\Framework\TestCase;

class JsonParserFuzzTest extends TestCase
{
    private const ITERATIONS = 10000;

    public function testJsonParserDoesNotCrash(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $input = $this->generateRandomInput();

            try {
                $result = json_decode($input, true);
                $this->assertTrue(true);
            } catch (\Throwable $e) {
                $this->fail(sprintf(
                    "Parser crashed on input: %s\nError: %s",
                    bin2hex($input),
                    $e->getMessage()
                ));
            }
        }
    }

    private function generateRandomInput(): string
    {
        $length = random_int(0, 1000);
        $bytes = '';
        for ($i = 0; $i < $length; $i++) {
            $bytes .= chr(random_int(0, 255));
        }
        return $bytes;
    }
}
```

### Mutation-Based Fuzzing

```php
<?php

namespace Tests\Fuzz;

use PHPUnit\Framework\TestCase;

class MutationFuzzer
{
    private array $seedInputs = [
        '{"name": "John", "age": 30}',
        '{"email": "test@example.com"}',
        '{"items": [1, 2, 3]}',
    ];

    private array $mutations = [
        'flipBit', 'removeByte', 'insertByte',
        'duplicateByte', 'replaceWithNull',
    ];

    public function mutate(string $input): string
    {
        $mutation = $this->mutations[array_rand($this->mutations)];
        return $this->$mutation($input);
    }

    private function flipBit(string $input): string
    {
        if (empty($input)) return $input;

        $pos = random_int(0, strlen($input) - 1);
        $bit = 1 << random_int(0, 7);
        $input[$pos] = chr(ord($input[$pos]) ^ $bit);
        return $input;
    }

    private function removeByte(string $input): string
    {
        if (empty($input)) return $input;
        $pos = random_int(0, strlen($input) - 1);
        return substr($input, 0, $pos) . substr($input, $pos + 1);
    }

    private function insertByte(string $input): string
    {
        $pos = random_int(0, strlen($input));
        $byte = chr(random_int(0, 255));
        return substr($input, 0, $pos) . $byte . substr($input, $pos);
    }

    private function duplicateByte(string $input): string
    {
        if (empty($input)) return $input;
        $pos = random_int(0, strlen($input) - 1);
        return substr($input, 0, $pos) . $input[$pos] . substr($input, $pos);
    }

    private function replaceWithNull(string $input): string
    {
        if (empty($input)) return $input;
        $pos = random_int(0, strlen($input) - 1);
        $input[$pos] = "\0";
        return $input;
    }

    public function getSeedInput(): string
    {
        return $this->seedInputs[array_rand($this->seedInputs)];
    }
}
```

### Laravel API Endpoint Fuzzing

```php
<?php

namespace Tests\Feature\Fuzz;

use Tests\TestCase;

class ApiFuzzTest extends TestCase
{
    public function testUserRegistrationEndpointHandlesInvalidInput(): void
    {
        $fuzzer = new MutationFuzzer();
        $crashes = [];

        for ($i = 0; $i < 1000; $i++) {
            $payload = $fuzzer->mutate($fuzzer->getSeedInput());

            try {
                $response = $this->postJson('/api/register', [
                    'raw' => $payload,
                ]);

                // Server crash olmamalı (500 error)
                $this->assertNotEquals(
                    500,
                    $response->status(),
                    "500 error on input: " . bin2hex($payload)
                );
            } catch (\Throwable $e) {
                $crashes[] = [
                    'input' => bin2hex($payload),
                    'error' => $e->getMessage(),
                ];
            }
        }

        $this->assertEmpty($crashes, 'Found crashes: ' . json_encode($crashes));
    }

    public function testSqlInjectionPayloadsRejected(): void
    {
        $sqlPayloads = [
            "' OR '1'='1",
            "'; DROP TABLE users;--",
            "admin'--",
            "1' UNION SELECT * FROM users--",
            "'; EXEC xp_cmdshell('dir')--",
        ];

        foreach ($sqlPayloads as $payload) {
            $response = $this->getJson('/api/users?search=' . urlencode($payload));

            $this->assertContains($response->status(), [200, 400, 422]);

            if ($response->status() === 200) {
                $this->assertCount(0, $response->json('data') ?? []);
            }
        }
    }
}
```

### PHP/Laravel ilə Tətbiq - Input Validation Fuzzing

```php
<?php

namespace Tests\Feature\Fuzz;

use Tests\TestCase;

class InputValidationFuzzTest extends TestCase
{
    /**
     * @dataProvider maliciousInputs
     */
    public function testEndpointHandlesMaliciousInput(string $field, string $payload): void
    {
        $response = $this->postJson('/api/comments', [
            $field => $payload,
        ]);

        // Heç bir halda crash olmamalı
        $this->assertNotEquals(500, $response->status());

        // XSS olmamalı
        if ($response->status() === 200) {
            $content = $response->content();
            $this->assertStringNotContainsString('<script>', $content);
            $this->assertStringNotContainsString('javascript:', $content);
        }
    }

    public static function maliciousInputs(): array
    {
        return [
            'XSS script tag' => ['content', '<script>alert(1)</script>'],
            'XSS img tag' => ['content', '<img src=x onerror=alert(1)>'],
            'SQL injection' => ['content', "'; DROP TABLE--"],
            'Path traversal' => ['file', '../../../etc/passwd'],
            'Null byte' => ['name', "admin\x00.jpg"],
            'Unicode overflow' => ['name', str_repeat('𝕏', 10000)],
            'Long string' => ['name', str_repeat('A', 1000000)],
            'Format string' => ['name', '%s%s%s%s%n'],
            'XXE payload' => ['xml', '<!DOCTYPE foo [<!ENTITY xxe SYSTEM "file:///etc/passwd">]>'],
            'LDAP injection' => ['query', '*)(uid=*))(|(uid=*'],
            'Command injection' => ['cmd', '; rm -rf / &'],
            'NoSQL injection' => ['filter', '{"$ne": null}'],
        ];
    }
}
```

### Coverage-Guided Fuzzing Setup

```bash
# PHP-də sadə coverage tracking
#!/bin/bash
# fuzz.sh

COVERAGE_DIR=/tmp/fuzz-coverage
SEED_DIR=./seeds
CRASH_DIR=./crashes

mkdir -p "$COVERAGE_DIR" "$CRASH_DIR"

for i in {1..10000}; do
    # Random input generate et
    head -c $((RANDOM % 1000 + 1)) /dev/urandom > /tmp/input.bin

    # Xdebug coverage ilə run et
    php -d xdebug.mode=coverage \
        -d xdebug.output_dir="$COVERAGE_DIR" \
        fuzz-target.php < /tmp/input.bin

    if [ $? -ne 0 ]; then
        # Crash tapıldı - saxla
        cp /tmp/input.bin "$CRASH_DIR/crash-$i.bin"
        echo "Crash found: $CRASH_DIR/crash-$i.bin"
    fi
done
```

### Differential Fuzzing (iki implementation müqayisəsi)

```php
public function testJsonParsersMatch(): void
{
    $fuzzer = new MutationFuzzer();

    for ($i = 0; $i < 1000; $i++) {
        $input = $fuzzer->mutate('{"key":"value"}');

        $nativeResult = @json_decode($input, true);
        $customResult = @(new CustomJsonParser())->parse($input);

        // Hər iki parser eyni nəticə verməli
        if ($nativeResult !== null && $customResult !== null) {
            $this->assertEquals(
                $nativeResult,
                $customResult,
                "Divergence on: " . bin2hex($input)
            );
        }
    }
}
```

## Ətraflı Qeydlər

### 1. Fuzz testing nədir və niyə istifadə olunur?

Fuzz testing - random/malformed input-larla crash, security vulnerability və unexpected behavior tapmaq üçün avtomatlaşdırılmış texnikadır. Xüsusilə memory safety, input parsing və security boşluqlarını tapmaqda effektivdir.

### 2. Dumb fuzzing ilə smart fuzzing arasında fərq nədir?

**Dumb:** input format-ını bilmir, tamamilə random data verir. Sürətli amma dayaz. **Smart:** grammar/format-ı bilir (JSON, HTTP), struktur qorumaqla mutate edir. Daha dərin coverage verir.

### 3. Coverage-guided fuzzing necə işləyir?

Fuzzer hər input üçün code coverage izləyir. Yeni path (branch, line) açan input-ları saxlayır və onları yenidən mutate edir. Genetic algorithm ilə interesting test case-ləri qoruyur. AFL və libFuzzer bu yanaşmadan istifadə edir.

### 4. Fuzz testing-i nə zaman istifadə etmək lazımdır?

- **Input parsing** kodları (JSON, XML, protokol parser)
- **Security-kritik** kod (authentication, authorization)
- **C/C++** kimi memory-unsafe languages
- **File format handlers** (image, PDF parser)
- **Network services** (RPC, API)

### 5. Fuzz testing-in məhdudiyyətləri nədir?

- **Slow feedback** - millions of iterations lazım ola bilər
- **Hard to reproduce** - non-deterministic nəticələr
- **False positives** - bəzi "crash"-lar əslində expected error-lardır
- **Business logic bugs tapmır** - yalnız crash/security boşluqları
- **Environment-dən asılı** - PHP kimi managed runtime-larda az effektiv

### 6. PHP-də fuzz testing necə edilir?

- Manual mutation fuzzer yazmaq
- `random_bytes()` ilə random input generasiya
- Corpus saxlamaq
- PHPUnit ilə data provider-də minlərlə input vermək
- AFL++ ilə PHP interpreter-i fuzz etmək

### 7. Crashes və bugs tapanda nə edirik?

1. Minimal reproduce case yaradın (shrinking)
2. Bug-ı regression test kimi əlavə edin
3. Root cause analysis edin
4. Fix verin və eyni input yenidən test edin
5. Security ilə bağlıdırsa CVE bildirin

### 8. OSS-Fuzz nədir?

Google-un open-source layihələri üçün continuous fuzzing infrastrukturudur. curl, OpenSSL, FFmpeg kimi layihələr burada 24/7 fuzz olunur və bugs avtomatik tapılır.

### 9. Fuzzing və property-based testing arasında fərq var?

Oxşardırlar amma fuzzing əsasən **crash/security bugs** üçün, PBT isə **invariant violations** üçündür. Fuzzing format-sız random data verir, PBT strukturlaşdırılmış data generasiya edir.

## Əlaqəli Mövzular

- [Security Testing (Senior)](21-security-testing.md)
- [Property-Based Testing (Lead)](38-property-based-testing.md)
- [API Testing (Middle)](09-api-testing.md)
- [Testing Anti-Patterns (Senior)](27-testing-anti-patterns.md)
- [Testing Microservices (Lead)](37-testing-microservices.md)
