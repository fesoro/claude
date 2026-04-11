# PII Masking & Anonymization

## Problem necə yaranır?

1. **Log-larda PII:** Developer exception log-a `$user->email` yazır, Loki/Elasticsearch-a göndərilir. İndi email-lər log sistemindədir — GDPR violation, breach riski.
2. **Test mühitindəki production data:** Prod dump test DB-yə köçürülür. Developer-lər real istifadəçi email-lərini, telefon nömrələrini görür — qanunsuz.
3. **Encrypted column-da axtarış:** Phone şifrələnir, lakin `WHERE phone = ?` işləmir.
4. **GDPR erasure — hard delete yox:** Sifariş olan user silinsə foreign key constraint bozulur, audit trail itirilir.

---

## PII Növləri və Klassifikasiya

```
Kritik (həmişə encrypt/mask):
  - SSN, pasport nömrəsi, vergi ID
  - Kredit/debit kart nömrəsi
  - Şifrə, security question

Həssas (encrypt, log-lara yazma):
  - Email, telefon, doğum tarixi
  - Ünvan, IP ünvanı
  - Tibbi məlumatlar

Az həssas (pseudonymize kifayət edir):
  - Ad, soyad
  - Ölkə/şəhər
```

---

## PII növləri və Masking

```
john.doe@gmail.com → j***@g***.com
+994501234567      → +994*****567
4532 1234 5678 9012 → 4532 **** **** 9012
192.168.1.100      → 192.168.1.0
```

---

## İmplementasiya

*Bu kod log-larda PII-nı masklayan Monolog processor-unu, şifrəli sütunlar üçün blind index axtarışını, GDPR silmə servisini və test DB anonimləşdirməsini göstərir:*

```php
// Log-larda PII masklanması — Monolog processor
// Log yazılmadan əvvəl regex ilə PII tapılır və masklanır
class PiiMaskingProcessor
{
    private PiiMasker $masker;

    private array $patterns = [
        '/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/' => fn($m) => $this->masker->maskEmail($m[0]),
        '/\+?\d[\d\s\-]{8,}\d/'                                => fn($m) => $this->masker->maskPhone($m[0]),
        '/\b\d{4}[\s\-]?\d{4}[\s\-]?\d{4}[\s\-]?\d{4}\b/'    => fn($m) => $this->masker->maskCard($m[0]),
    ];

    public function __invoke(LogRecord $record): LogRecord
    {
        $message = $record->message;
        foreach ($this->patterns as $pattern => $replacer) {
            $message = preg_replace_callback($pattern, $replacer, $message);
        }
        return $record->with(message: $message);
    }
}

// DB-də şifrələmə + blind index (axtarış üçün)
class User extends Model
{
    // Laravel encrypted cast: AES-256-CBC ilə şifrələyir
    protected $casts = [
        'phone'   => 'encrypted',
        'ssn'     => 'encrypted',
        'address' => 'encrypted:array',
    ];

    // Problem: encrypted column-da WHERE işləmir (hər dəfə fərqli ciphertext)
    // Həll: Blind index — HMAC hash axtarış üçün ayrı column
    public function setPhoneAttribute(string $phone): void
    {
        $this->attributes['phone']       = encrypt($phone);
        $this->attributes['phone_index'] = hash_hmac('sha256', $phone, config('app.key'));
    }

    public static function findByPhone(string $phone): ?self
    {
        $index = hash_hmac('sha256', $phone, config('app.key'));
        return static::where('phone_index', $index)->first();
    }
}

// GDPR Right to Erasure — hard delete yox, anonymize
class GdprErasureService
{
    public function erase(int $userId): void
    {
        DB::transaction(function () use ($userId) {
            $user = User::findOrFail($userId);

            // PII silinir, ID saxlanır (referential integrity qorunur)
            $user->update([
                'name'          => 'Deleted User',
                'email'         => "deleted_{$userId}@anon.invalid",
                'phone'         => null,
                'phone_index'   => null,
                'address'       => null,
                'anonymized_at' => now(),
            ]);

            // S3-dəki profil şəkli silinir
            if ($user->avatar_path) {
                Storage::disk('s3')->delete($user->avatar_path);
                $user->update(['avatar_path' => null]);
            }

            // Sifarişlərin shipping məlumatları silinir
            $user->orders()->update([
                'shipping_name'    => 'Deleted',
                'shipping_address' => null,
            ]);

            // Audit log: erasure baş verdi — ID saxlanır, məlumat yox
            AuditLog::create([
                'action'       => 'gdpr_erasure',
                'entity_id'    => $userId,
                'entity_type'  => 'user',
                'performed_at' => now(),
            ]);

            // Payment records saxlanır (vergi qanunu: 7 il)
            // Amount, date saxlanır — PII silinir
        });
    }
}

// Test DB üçün anonymization — prod dump-u test-ə köçürməzdən əvvəl
class DatabaseAnonymizer
{
    public function anonymize(): void
    {
        User::chunk(500, function ($users) {
            foreach ($users as $user) {
                $user->update([
                    'name'  => fake()->name(),
                    'email' => "test_{$user->id}@test.local", // Unique, real domain yox
                    'phone' => fake()->numerify('+994##########'),
                ]);
            }
        });

        // Email-lər unique olmalıdır — fake()->unique()->safeEmail() yavaş ola bilər
        // test_{id}@test.local pattern həm unique, həm sürətli
    }
}
```

---

## Structured Logging — PII-dan qaçınmaq

Log-lara PII yazmamaq ən yaxşı strategiyadır. Regex post-processing həmişə etibarlı deyil:

*Bu kod log-larda PII-nı yox, yalnız user_id-nin yazılması prinsipinin düzgün/yanlış nümunələrini göstərir:*

```php
// ❌ Anti-pattern: PII log-a yazılır
Log::info('User logged in', ['email' => $user->email]);

// ✅ Doğru: Yalnız non-PII identifiers
Log::info('User logged in', ['user_id' => $user->id]);

// ❌ Exception log-da PII
Log::error('Payment failed', ['data' => $request->all()]); // email, card ola bilər

// ✅ Seçici log
Log::error('Payment failed', [
    'user_id'    => $user->id,
    'amount'     => $request->amount,
    'error_code' => $e->getCode(),
]);
```

---

## Envelope Encryption (Çox müştərili sistem)

Tək master key bütün müştərilərin datasını şifrələyirsə key leak = total breach:

*Bu kod hər tenant üçün ayrı şifrələmə açarı (DEK) yaradan, DEK-i master key (KEK) ilə şifrələyən envelope encryption servisini göstərir:*

```php
class EncryptionService
{
    // Hər müştəri üçün ayrı data encryption key (DEK)
    // DEK özü master key (KEK) ilə şifrələnib DB-də saxlanır
    public function encryptForTenant(int $tenantId, string $data): string
    {
        $dek = $this->getOrCreateDek($tenantId); // AES-256 random key
        return encrypt($data, $dek);
    }

    private function getOrCreateDek(int $tenantId): string
    {
        $encryptedDek = TenantKey::where('tenant_id', $tenantId)->value('encrypted_key');

        if (!$encryptedDek) {
            $dek = random_bytes(32); // 256-bit random key
            TenantKey::create([
                'tenant_id'     => $tenantId,
                'encrypted_key' => encrypt($dek, config('app.master_key')), // KEK ilə şifrələ
            ]);
            return $dek;
        }

        return decrypt($encryptedDek, config('app.master_key')); // KEK ilə açıl
    }
}
```

---

## Anti-patterns

- **Plain text PII DB-də:** Breach olduqda birbaşa oxuna bilər. Sensitive field-lər encrypt edilməlidir.
- **Hard delete istifadəsi:** Foreign key constraint, audit trail — anonymize daha etibarlı.
- **Regex masklamaya tam güvənmək:** Regex miss edə bilər (yeni format, unicode). Structured logging + regex = defense in depth.
- **Test DB-də real data:** Production dump-u anonymize etmədən test-ə köçürmək — GDPR violation.

---

## İntervyu Sualları

**1. GDPR right to erasure necə tətbiq edilir?**
Hard delete foreign key constraint pozur, audit trail itirilir. Anonymize: PII silinir, record qalır. Payment records 7 il saxlanır (vergi qanunu) — yalnız PII silinir, məbləğ qalır. Audit log: kim, nə vaxt erasure tələb etdi.

**2. Encrypted column-da axtarış necə edilir?**
AES encrypted ciphertext hər dəfə fərqlidir — `WHERE phone = ?` işləmir. Blind index: `HMAC(phone, secret)` deterministic hashdir — eyni phone həmişə eyni hash. Hash ayrı column-da — axtarış mümkün. Secret key rotation: bütün index-ləri rebuild etmək lazımdır.

**3. Log-larda PII necə önlənir?**
Strukturel: log-a PII yazmamaq (user_id, yox email). Defensiv: Monolog processor ilə regex masking. İkisi birlikdə — developer səhvi edərsə processor yaxalayır.

**4. Envelope encryption nədir, niyə lazımdır?**
Tək master key ilə bütün datanı şifrələmək — key leak = total breach. Envelope encryption: hər müştəri/hər entity üçün ayrı DEK (data encryption key), DEK-ləri KEK (key encryption key = master key) ilə şifrələ. Key leak: yalnız yeni KEK ilə DEK-ləri re-encrypt et, datanı yenidən şifrələmək lazım deyil.

**5. Blind index collision riski varmı?**
HMAC-SHA256 collision probability astronomik dərəcədə aşağıdır (2^256-da 1). Lakin HMAC secret key-i bilmədən preimage attack mümkün deyil. Əgər secret key DB-yə ayrıca yerləşdirilib, application key-dən fərqlirsə əlavə qoruma var.

**6. GDPR əsasında erasure tələbi gəldikdə payment records necə idarə edilir?**
GDPR Art. 17(3)(b): qanuni öhdəlik üçün saxlama icazəlidir. Vergi qanunu 5-7 il saxlama tələb edir. Həll: payment amount, date, transaction ID saxla; `cardholder_name`, billing address, email-i anonimləşdir. Audit log: "payment PII erased on [date], financial data retained per tax law".

---

## Anti-patternlər

**1. Bütün PII-nı eyni encryption açarı ilə şifrələmək**
Bütün istifadəçilərin PII-nı tək master key ilə AES-encrypt etmək — key leak olduqda bütün müştərilərin datasına çıxış açılır. Hər müştəri üçün ayrı data encryption key (DEK) olmalı, DEKlər isə master key (KEK) ilə şifrələnərək saxlanmalıdır (envelope encryption).

**2. Anonymization-ı "PII sahələri boşaltmaq" kimi başa düşmək**
`UPDATE users SET email = '', phone = ''` etmək — boş string hələ identifikasiya üçün istifadə edilə bilər, sistemdəki digər cədvəllərdə real dəyərlər qala bilər. Düzgün anonymization: deterministic fake dəyərlərlə əvəz etmək (`anon_{id}@deleted.invalid`), bütün cədvəlləri əhatə etmək.

**3. Log-larda PII-nı filtered etmədən yazmaq**
Application log-larına `email`, `phone`, `name` dəyərlərini strukturel yoxlama olmadan yazmaq — log aggregation sistemləri (Datadog, ELK) GDPR kapsamında deyil, breach riski var. Log-larda yalnız `user_id` yazılmalı, Monolog middleware ilə PII pattern-ləri real-time mask edilməlidir.

**4. Test mühitinə production data dump-u köçürmək**
`mysqldump production | mysql staging` — developer-lər real müştəri datasına çıxış əldə edir, GDPR Article 32 pozulur. Test dataseti ya synthetic data generator ilə yaradılmalı, ya da production dump anonymize edildikdən sonra staging-ə köçürülməlidir.

**5. Blind index-i key rotation-da rebuild etməmək**
HMAC secret key-i dəyişdikdən sonra `blind_index` sütununu yenilənməmiş buraxmaq — köhnə hash artıq axtarış üçün işləmir, bütün axtarışlar uğursuz olur. Key rotation zamanı bütün mövcud record-ların blind index-i yeni key ilə yenidən hesablanmalıdır.

**6. GDPR erasure tələbini tam yerinə yetirmədən "silindi" hesab etmək**
Yalnız `users` cədvəlini anonymize edib əlaqəli cədvəlləri (comments, reviews, addresses) unutmaq — PII hələ də başqa cədvəllərdə var, GDPR tam ödənilmir. Erasure prosesi bütün PII saxlayan cədvəllərin xəritəsini (data inventory) əsas almalı, hamısı eyni anda işlənməlidir.

**7. Encryption key-i application code-da hard-code etmək**
`$key = 'my-secret-key-123'` kimi şifrələmə açarını source code-da saxlamaq — git history-də qalır, hər developer görür. Encryption key-lər environment variable-da (`APP_KEY`, dedicated KMS - AWS KMS, HashiCorp Vault) saxlanmalıdır.
