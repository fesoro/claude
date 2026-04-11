# Audit Log Sistemi + GDPR Uyğunluğu

## Problem

Enterprise tətbiqlərdə iki kritik tələb var:

1. **Audit Logging**: Kim, nə vaxt, nə etdi? Hər dəyişikliyin tam tarixi saxlanılmalıdır. Maliyyə, sağlamlıq, hüquq sahələrində bu tələb məcburidir.

2. **GDPR Uyğunluğu** (2018-ci ildən EU tələbi): İstifadəçilərin şəxsi datası qorunmalı, onların hüquqları (silmə, ixrac, razılıq) təmin edilməlidir. Pozuntu zamanı €20M və ya global dövriyyənin 4%-i cərimə.

---

## Audit Log Architecture

### Əsas Prinsiplər

**Append-Only**: Audit log-lara yalnız yeni qeyd əlavə edilə bilər, mövcud qeydlər dəyişdirilə və ya silinə bilməz.

**Tamper-Proof**: Hər log qeydinin hash-i hesablanır, chain halında bağlanır — dəyişiklik dərhal bilinir.

```
Log[1]: {data: "...", hash: SHA256(data)}
Log[2]: {data: "...", prev_hash: Log[1].hash, hash: SHA256(data + prev_hash)}
Log[3]: {data: "...", prev_hash: Log[2].hash, hash: SHA256(data + prev_hash)}
```

Bir qeyd dəyişdirilsə, onun hash-i və bütün sonrakı qeydlərin hash-ləri pozulur.

### Audit Log Cədvəl Strukturu

*Bu kod tamper-proof hash zənciri, append-only constraint ilə audit log cədvəl strukturunu göstərir:*

```sql
CREATE TABLE audit_logs (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    -- Kim etdi
    user_id         BIGINT UNSIGNED NULL,
    user_type       VARCHAR(50) NULL,       -- 'admin', 'api_client', 'system'
    ip_address      VARCHAR(45) NULL,
    user_agent      TEXT NULL,
    -- Nə etdi
    event           VARCHAR(100) NOT NULL,  -- 'user.created', 'order.deleted'
    auditable_type  VARCHAR(100) NULL,      -- Model class
    auditable_id    BIGINT UNSIGNED NULL,   -- Model ID
    -- Əvvəl/sonra dəyərlər
    old_values      JSON NULL,
    new_values      JSON NULL,
    -- Metadata
    tags            JSON NULL,              -- ['payment', 'critical']
    ip_country      VARCHAR(2) NULL,
    -- Tamper-proof chain
    chain_hash      CHAR(64) NOT NULL,      -- SHA256
    prev_hash       CHAR(64) NULL,
    -- Zaman
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Yalnız insert, heç vaxt update/delete
    INDEX idx_user (user_id, created_at),
    INDEX idx_auditable (auditable_type, auditable_id),
    INDEX idx_event (event, created_at)
) ENGINE=InnoDB;

-- Cədvəlin dəyişdirilməsinin qarşısını al (MySQL)
-- GRANT INSERT ON audit_logs TO 'app_user'@'%';
-- UPDATE, DELETE, DROP icazəsini ver MƏTMA
```

---

## Sensitive Data Masking/Encryption

Audit log-larda şifrə, kredit kartı nömrəsi, şəxsi məlumatlar açıq saxlanılmamalıdır.

*Bu kod həssas sahələri maskalamaq, qismən gizlətmək və şifrələmək üçün audit log data masker-ini göstərir:*

```php
<?php

namespace App\Services\Audit;

use Illuminate\Support\Facades\Crypt;

class SensitiveDataMasker
{
    // Həmişə masklanacaq sahələr
    private array $alwaysMask = [
        'password', 'password_confirmation', 'current_password',
        'token', 'secret', 'api_key', 'private_key',
    ];

    // Qismən masklanacaq sahələr (ilk/son hərflər görünür)
    private array $partialMask = [
        'email'       => 'email',
        'phone'       => 'phone',
        'credit_card' => 'card',
        'ssn'         => 'ssn',
        'iban'        => 'iban',
    ];

    // Şifrələnəcək sahələr (decrypt edilə bilər)
    private array $encrypt = [
        'national_id', 'passport_number', 'medical_record',
    ];

    public function mask(array $data): array
    {
        foreach ($data as $key => $value) {
            if (in_array($key, $this->alwaysMask)) {
                $data[$key] = '***REDACTED***';
                continue;
            }

            if (isset($this->partialMask[$key])) {
                $data[$key] = $this->partialMaskValue($value, $this->partialMask[$key]);
                continue;
            }

            if (in_array($key, $this->encrypt)) {
                $data[$key] = '[ENCRYPTED:' . Crypt::encryptString((string)$value) . ']';
                continue;
            }
        }

        return $data;
    }

    private function partialMaskValue(string $value, string $type): string
    {
        return match ($type) {
            'email' => $this->maskEmail($value),
            'phone' => $this->maskPhone($value),
            'card'  => $this->maskCard($value),
            default => str_repeat('*', strlen($value)),
        };
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = explode('@', $email, 2);
        $masked = substr($local, 0, 2) . str_repeat('*', max(0, strlen($local) - 2));
        return $masked . '@' . $domain;
    }

    private function maskPhone(string $phone): string
    {
        // +994501234567 → +994***4567
        return preg_replace('/(\+\d{3})\d+(\d{4})/', '$1***$2', $phone);
    }

    private function maskCard(string $card): string
    {
        $clean = preg_replace('/\D/', '', $card);
        return str_repeat('*', strlen($clean) - 4) . substr($clean, -4);
    }
}
```

---

## GDPR Konseptləri

| Termin | Açıqlama | Nümunə |
|--------|----------|--------|
| **Data Subject** | Datası işlənən şəxs | İstifadəçi |
| **Data Controller** | Datanın məqsədini müəyyən edir | Şirkət |
| **Data Processor** | Data Controller adına məlumat işləyir | AWS, Stripe |
| **DPA** | Data Processing Agreement | AWS ilə müqavilə |
| **Lawful Basis** | İşləmənin hüquqi əsası | Razılıq, müqavilə, hüquqi öhdəlik |
| **Data Minimization** | Yalnız lazımlı datanı yığ | Doğum tarixi yerinə yaş |

---

## AuditLogger Service

*Bu kod həssas datanı maskalayan, hash zəncirini yoxlayan və queue ilə yazma aparaan audit logger service-ini göstərir:*

```php
<?php

namespace App\Services\Audit;

use App\Jobs\WriteAuditLogJob;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuditLogger
{
    public function __construct(
        private readonly SensitiveDataMasker $masker
    ) {}

    /**
     * Audit qeydi yaz (queue vasitəsilə — performans üçün)
     */
    public function log(
        string $event,
        ?object $model = null,
        array $oldValues = [],
        array $newValues = [],
        array $tags = []
    ): void {
        $payload = [
            'user_id'        => Auth::id(),
            'user_type'      => Auth::user()?->type ?? 'guest',
            'ip_address'     => request()->ip(),
            'user_agent'     => request()->userAgent(),
            'event'          => $event,
            'auditable_type' => $model ? get_class($model) : null,
            'auditable_id'   => $model?->getKey(),
            'old_values'     => $this->masker->mask($oldValues),
            'new_values'     => $this->masker->mask($newValues),
            'tags'           => $tags,
            'ip_country'     => geoip(request()->ip())?->iso_code,
        ];

        // Queue-ya göndər — HTTP request-i yavaşlatmasın
        WriteAuditLogJob::dispatch($payload)->onQueue('audit');
    }

    /**
     * Zəncir hash-ini yoxla — tamper detection
     */
    public function verifyChain(int $fromId = 1): array
    {
        $invalid = [];
        $prevHash = null;

        AuditLog::where('id', '>=', $fromId)
            ->orderBy('id')
            ->chunk(1000, function ($logs) use (&$prevHash, &$invalid) {
                foreach ($logs as $log) {
                    $expectedHash = $this->computeHash($log, $prevHash);

                    if ($log->chain_hash !== $expectedHash) {
                        $invalid[] = $log->id;
                    }

                    $prevHash = $log->chain_hash;
                }
            });

        return $invalid;
    }

    private function computeHash(AuditLog $log, ?string $prevHash): string
    {
        $data = json_encode([
            'id'             => $log->id,
            'event'          => $log->event,
            'user_id'        => $log->user_id,
            'auditable_type' => $log->auditable_type,
            'auditable_id'   => $log->auditable_id,
            'old_values'     => $log->old_values,
            'new_values'     => $log->new_values,
            'created_at'     => $log->created_at->toIso8601String(),
            'prev_hash'      => $prevHash,
        ]);

        return hash('sha256', $data);
    }
}
```

### WriteAuditLogJob

*Bu kod əvvəlki qeydin hash-ini götürüb zəncirə bağlayan tamper-proof audit log yazma job-unu göstərir:*

```php
<?php

namespace App\Jobs;

use App\Models\AuditLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;

class WriteAuditLogJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    // Audit log yazma uğursuz olsa, retry et
    public int $tries = 5;
    public int $backoff = 10;

    public function __construct(private readonly array $payload) {}

    public function handle(): void
    {
        DB::transaction(function () {
            // Əvvəlki son qeydin hash-ini al
            $lastLog = AuditLog::latest('id')->lockForUpdate()->first();
            $prevHash = $lastLog?->chain_hash;

            $data = array_merge($this->payload, ['prev_hash' => $prevHash]);
            $log = new AuditLog($data);

            // Hash hesabla
            $log->chain_hash = hash('sha256', json_encode([
                'event'      => $log->event,
                'user_id'    => $log->user_id,
                'old_values' => $log->old_values,
                'new_values' => $log->new_values,
                'prev_hash'  => $prevHash,
                'timestamp'  => now()->toIso8601String(),
            ]));

            $log->save();
        });
    }
}
```

---

## Model Observer — Avtomatik Audit

*Model Observer — Avtomatik Audit üçün kod nümunəsi:*
```php
<?php

namespace App\Observers;

use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Model;

class AuditableObserver
{
    public function __construct(private readonly AuditLogger $logger) {}

    public function created(Model $model): void
    {
        $this->logger->log(
            event: class_basename($model) . '.created',
            model: $model,
            newValues: $model->toArray(),
            tags: ['model_change']
        );
    }

    public function updated(Model $model): void
    {
        $this->logger->log(
            event: class_basename($model) . '.updated',
            model: $model,
            oldValues: $model->getOriginal(),
            newValues: $model->getDirty(),
            tags: ['model_change']
        );
    }

    public function deleted(Model $model): void
    {
        $this->logger->log(
            event: class_basename($model) . '.deleted',
            model: $model,
            oldValues: $model->toArray(),
            tags: ['model_change', 'deletion']
        );
    }
}

// Model-ə əlavə et:
// use App\Observers\AuditableObserver;
// protected static function booted(): void
// {
//     static::observe(AuditableObserver::class);
// }
```

---

## Right to Be Forgotten — Anonymization Command

*Right to Be Forgotten — Anonymization Command üçün kod nümunəsi:*
```php
<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GdprAnonymizeUserCommand extends Command
{
    protected $signature = 'gdpr:anonymize {userId} {--dry-run}';
    protected $description = 'GDPR: İstifadəçinin şəxsi datasını anonymize et (Right to Erasure)';

    public function handle(): int
    {
        $userId = $this->argument('userId');
        $dryRun = $this->option('dry-run');

        $user = User::findOrFail($userId);

        $this->info("Anonymizing user #{$userId}: {$user->email}");

        if ($dryRun) {
            $this->warn('[DRY RUN] Heç bir dəyişiklik edilməyəcək');
            $this->showWhatWouldBeAnonymized($user);
            return self::SUCCESS;
        }

        DB::transaction(function () use ($user) {
            $anonymousId = 'anon_' . Str::random(16);

            // 1. İstifadəçi məlumatlarını anonymize et
            $user->update([
                'name'         => 'Deleted User',
                'email'        => $anonymousId . '@deleted.invalid',
                'phone'        => null,
                'address'      => null,
                'birth_date'   => null,
                'avatar'       => null,
                'password'     => bcrypt(Str::random(64)), // İmkansız şifrə
                'remember_token' => null,
                'anonymized_at'  => now(),
                'anonymized_id'  => $anonymousId,
            ]);

            // 2. Əlaqəli şəxsi dataları sil
            $user->addresses()->delete();
            $user->paymentMethods()->delete();

            // 3. Sifarişləri saxla amma müştəri əlaqəsini qır
            // (Maliyyə qeydləri hüquqi öhdəlik üçün saxlanılmalıdır)
            $user->orders()->update([
                'customer_name'  => 'Deleted Customer',
                'customer_email' => $anonymousId . '@deleted.invalid',
                'billing_address' => null,
            ]);

            // 4. Audit log-larda PII-ni anonymize et
            AuditLog::where('user_id', $user->id)->update([
                'ip_address' => '0.0.0.0',
                'user_agent' => null,
            ]);

            // 5. GDPR erasure qeydini audit-ə yaz
            app(\App\Services\Audit\AuditLogger::class)->log(
                event: 'gdpr.erasure_completed',
                model: $user,
                tags: ['gdpr', 'erasure', 'legal']
            );
        });

        $this->info("✓ User #{$userId} anonymize edildi.");
        return self::SUCCESS;
    }

    private function showWhatWouldBeAnonymized(User $user): void
    {
        $this->table(['Sahə', 'Mövcud dəyər', 'Yeni dəyər'], [
            ['name', $user->name, 'Deleted User'],
            ['email', $user->email, 'anon_***@deleted.invalid'],
            ['phone', $user->phone ?? 'null', 'null'],
            ['addresses', $user->addresses()->count() . ' qeyd', 'SİLİNƏCƏK'],
        ]);
    }
}
```

---

## GDPR Data Export (Right to Access / Portability)

*GDPR Data Export (Right to Access / Portability) üçün kod nümunəsi:*
```php
<?php

namespace App\Services\Gdpr;

use App\Models\User;
use Illuminate\Support\Facades\Storage;

class GdprDataExporter
{
    /**
     * İstifadəçinin bütün şəxsi datasını JSON formatında export et
     */
    public function exportAsJson(User $user): string
    {
        $data = $this->collectAllUserData($user);
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $filename = "gdpr_export_{$user->id}_" . now()->format('YmdHis') . '.json';
        Storage::disk('private')->put("gdpr_exports/{$filename}", $json);

        return $filename;
    }

    /**
     * CSV formatında export
     */
    public function exportAsCsv(User $user): string
    {
        $filename = "gdpr_export_{$user->id}_" . now()->format('YmdHis') . '.csv';
        $path = Storage::disk('private')->path("gdpr_exports/{$filename}");

        $handle = fopen($path, 'w');
        fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM

        // Əsas məlumatlar
        fputcsv($handle, ['Kateqoriya', 'Sahə', 'Dəyər']);
        foreach ($this->flattenUserData($user) as $row) {
            fputcsv($handle, $row);
        }

        fclose($handle);
        return $filename;
    }

    private function collectAllUserData(User $user): array
    {
        return [
            'export_date'    => now()->toIso8601String(),
            'user_id'        => $user->id,
            'profile'        => [
                'name'       => $user->name,
                'email'      => $user->email,
                'phone'      => $user->phone,
                'created_at' => $user->created_at->toIso8601String(),
            ],
            'orders'         => $user->orders()
                ->select(['id', 'total', 'status', 'created_at'])
                ->get()->toArray(),
            'addresses'      => $user->addresses()
                ->select(['type', 'street', 'city', 'country'])
                ->get()->toArray(),
            'login_history'  => $user->loginHistory()
                ->select(['ip_address', 'user_agent', 'created_at'])
                ->latest()
                ->limit(100)
                ->get()->toArray(),
            'consents'       => $user->consents()
                ->select(['type', 'granted', 'created_at'])
                ->get()->toArray(),
        ];
    }

    private function flattenUserData(User $user): array
    {
        $rows = [];
        $data = $this->collectAllUserData($user);

        foreach ($data['profile'] as $field => $value) {
            $rows[] = ['Profil', $field, $value ?? ''];
        }

        foreach ($data['orders'] as $order) {
            $rows[] = ['Sifariş', "#{$order['id']}", "{$order['total']} ({$order['status']})"];
        }

        return $rows;
    }
}
```

---

## Consent Model + Middleware

*Consent Model + Middleware üçün kod nümunəsi:*
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserConsent extends Model
{
    protected $fillable = [
        'user_id', 'type', 'granted', 'ip_address',
        'user_agent', 'version', 'expires_at',
    ];

    protected $casts = [
        'granted'    => 'boolean',
        'expires_at' => 'datetime',
    ];

    // Razılıq növləri
    const TYPE_MARKETING    = 'marketing';
    const TYPE_ANALYTICS    = 'analytics';
    const TYPE_THIRD_PARTY  = 'third_party';
    const TYPE_TERMS        = 'terms_of_service';

    public function isValid(): bool
    {
        return $this->granted
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    /**
     * İstifadəçinin müəyyən tip razılığı verdiyini yoxla
     */
    public static function hasConsent(int $userId, string $type): bool
    {
        return static::where('user_id', $userId)
            ->where('type', $type)
            ->where('granted', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            })
            ->exists();
    }
}
```

*->orWhere('expires_at', '>', now()); üçün kod nümunəsi:*
```php
<?php

namespace App\Http\Middleware;

use App\Models\UserConsent;
use Closure;
use Illuminate\Http\Request;

class RequireConsent
{
    /**
     * Müəyyən endpoint-lər üçün razılıq tələb et
     * Route: ->middleware('consent:marketing')
     */
    public function handle(Request $request, Closure $next, string $consentType): mixed
    {
        $user = $request->user();

        if (!$user || !UserConsent::hasConsent($user->id, $consentType)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error'         => 'consent_required',
                    'message'       => "Bu əməliyyat üçün '{$consentType}' razılığı tələb olunur",
                    'consent_url'   => route('consent.show', ['type' => $consentType]),
                ], 403);
            }

            return redirect()->route('consent.show', ['type' => $consentType]);
        }

        return $next($request);
    }
}
```

---

## Data Retention Cleanup Command

*Data Retention Cleanup Command üçün kod nümunəsi:*
```php
<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\UserConsent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DataRetentionCleanupCommand extends Command
{
    protected $signature = 'gdpr:cleanup {--dry-run}';
    protected $description = 'GDPR: Müddəti keçmiş datanı sil';

    // Data retention siyasəti (gündə)
    private array $retentionPolicy = [
        'audit_logs'       => 2555, // 7 il (hüquqi tələb)
        'user_consents'    => 1825, // 5 il
        'login_history'    => 365,  // 1 il
        'analytics_events' => 730,  // 2 il
        'temp_files'       => 30,   // 30 gün
    ];

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $totalDeleted = 0;

        foreach ($this->retentionPolicy as $table => $days) {
            $cutoffDate = now()->subDays($days);

            $query = DB::table($table)->where('created_at', '<', $cutoffDate);
            $count = $query->count();

            if ($count === 0) {
                continue;
            }

            $this->info("  {$table}: {$count} qeyd ({$days} gündən köhnə)");

            if (!$dryRun) {
                // Böyük cədvəlləri chunk-larla sil
                $deleted = 0;
                while (true) {
                    $rows = DB::table($table)
                        ->where('created_at', '<', $cutoffDate)
                        ->limit(1000)
                        ->pluck('id');

                    if ($rows->isEmpty()) {
                        break;
                    }

                    DB::table($table)->whereIn('id', $rows)->delete();
                    $deleted += $rows->count();
                    $this->output->write('.');
                }

                $totalDeleted += $deleted;
                $this->newLine();
            }
        }

        if (!$dryRun) {
            $this->info("Cəmi {$totalDeleted} qeyd silindi.");
        }

        return self::SUCCESS;
    }
}
```

---

## Personal Data Registry

*Personal Data Registry üçün kod nümunəsi:*
```php
<?php

namespace App\Services\Gdpr;

/**
 * Personal Data Registry: şirkətdə hansı personal data saxlandığını kataloqlaşdır
 * GDPR Article 30: Records of Processing Activities (RoPA)
 */
class PersonalDataRegistry
{
    /**
     * Bütün personal data kateqoriyalarını qeydiyyatdan keçir
     */
    public function getRegistry(): array
    {
        return [
            [
                'category'        => 'Identity Data',
                'fields'          => ['name', 'email', 'phone', 'profile_photo'],
                'purpose'         => 'Hesab idarəetməsi, xidmət göstərilməsi',
                'lawful_basis'    => 'Contract',
                'retention'       => '5 years after account deletion',
                'processors'      => ['AWS RDS', 'Cloudflare'],
                'third_parties'   => ['Stripe (billing)'],
            ],
            [
                'category'        => 'Transaction Data',
                'fields'          => ['order_history', 'payment_method_last4', 'billing_address'],
                'purpose'         => 'Sifariş yerinə yetirilməsi, mühasibat',
                'lawful_basis'    => 'Legal obligation',
                'retention'       => '7 years (tax law)',
                'processors'      => ['AWS RDS', 'Stripe'],
                'third_parties'   => ['Stripe', 'DHL'],
            ],
            [
                'category'        => 'Analytics Data',
                'fields'          => ['page_views', 'click_events', 'session_duration'],
                'purpose'         => 'Məhsul təkmilləşdirilməsi',
                'lawful_basis'    => 'Consent',
                'retention'       => '2 years',
                'processors'      => ['Mixpanel', 'Google Analytics 4'],
                'third_parties'   => ['Mixpanel', 'Google'],
            ],
            [
                'category'        => 'Marketing Data',
                'fields'          => ['email_open_rate', 'clicked_campaigns', 'preferences'],
                'purpose'         => 'Email marketinq',
                'lawful_basis'    => 'Consent',
                'retention'       => 'Until consent withdrawn',
                'processors'      => ['Mailchimp', 'AWS SES'],
                'third_parties'   => ['Mailchimp'],
            ],
        ];
    }
}
```

---

## İntervyu Sualları

**S: Audit log-u tamper-proof etmək üçün nə edərdiniz?**

C: Hər qeydin hash-ini hesablaraq əvvəlki hash ilə zəncirləyərəm (blockchain-ə bənzər). Əlavə olaraq: append-only DB istifadəçisi (yalnız INSERT icazəsi), ayrı audit database, həftəlik hash snapshot-larını cold storage-ə göndərmək.

**S: GDPR "right to erasure" tələbi ilə audit log məcburiyyəti necə balanslaşdırılır?**

C: Audit log-lar hüquqi öhdəlik (legal obligation) əsasında saxlanılır — bu GDPR-da legitimate basis-dir. Ancaq audit log-larda user_id əvəzinə anonymized_id saxlamaq olar. User silinəndə audit log-lardakı personal identifier-lar anonymize edilir, lakin event tarixi saxlanılır.

**S: Data breach (məlumat sızması) zamanı GDPR nə tələb edir?**

C: 72 saat ərzində DPA-ya (Data Protection Authority) bildirmək məcburidir. Əgər yüksək risk varsa, data subject-ləri də xəbərdar etmək lazımdır. Bunun üçün breach detection sistemi, incident response plan və log-ların hazır olması vacibdir.

**S: Consent management necə implement edilməlidir?**

C: Hər razılıq növü ayrıca qeydə alınmalıdır (marketing, analytics). Geri çəkilmə (withdraw) prosesi ən azı asan olmalıdır. Razılıq versiyası saxlanılmalıdır (qanun dəyişəndə yeni razılıq tələb oluna bilər). Cookie banner re-consent 13 ayda bir lazımdır.

**S: "Personal data" nədir, hansıları GDPR tərəfindən qorunur?**

C: Ad, email, telefon, IP ünvanı, cookie ID, coğrafi yer, sağlamlıq məlumatları, biometrik data, irqi/etnik mənşə, siyasi baxışlar. Şirkətin adı, iş email-i (info@company.com) personal data deyil. Ancaq işçinin iş email-i personal data sayılır.

**S: Pseudonymization ilə anonymization fərqi nədir?**
C: Anonymization: dataı şəxslə əlaqələndirmək tamamilə mümkün deyil — GDPR scope-u xaricindədir, retention tələbi yoxdur. Pseudonymization: real identifikator əvəzinə psevdonim (məs. UUID) istifadə edilir, amma "key table" saxlanılırsa reverse mümkündür — GDPR-a tabedir. Audit log-larda `user_id` saxlamaq pseudonymization sayılır: `users` cədvəli ilə join etmək mümkündür. "Right to erasure"-da audit log-dan user_id silmək olmaz (legal obligation), amma ad/email kimi direct PII pseudonymizasiya edilir — bu balans kabul ediləndir.

**S: Data breach baş verdikdə GDPR nə tələb edir, şirkət nə etməlidir?**
C: 72 saat ərzində DPA-ya (Data Protection Authority) bildirmək məcburidir — breach aşkarlandıqdan etibarən, nəticəsi tam bilinməsə belə. Yüksək risk varsa (şifrə, bank məlumatı, tibbi data sızıbsa) data subject-lər də xəbərdar edilməlidir. Hazır olmaq lazımdır: breach detection sistemi (log anomaly alerts), incident response plan (IRP), DPA contact məlumatı, hangi data-nın sızdığını bilmək üçün data map. Bildiriş gecikərsə əlavə cərimə tətbiq edilə bilər.

**S: "Lawful basis" nədir, marketinq email-ləri üçün hansı basis istifadə edilir?**
C: GDPR 6 lawful basis müəyyən edir: Consent, Contract, Legal obligation, Vital interests, Public task, Legitimate interests. Marketinq email-ləri üçün həmişə Consent lazımdır — açıq, spesifik, bilgilendirilmiş, geri alına bilən razılıq. "İstifadə şərtlərini qəbul edirəm" marketinq razılığı saymır — ayrıca opt-in checkbox lazımdır. Razılıq geri alındıqda marketinq dərhal dayanmalıdır.

---

## Anti-patternlər

**1. Audit log-a həssas data yazmaq**
`password`, `credit_card`, `ssn` kimi sahələri log-a yazmaq — GDPR pozuntusudur. `$hidden` array və ya field whitelist ilə filtrləyin, yoxsa GDPR cəriməsi.

**2. Audit log-ları silmək**
GDPR "right to erasure" dedikdə audit log-lar silinmir — PII pseudonymizasiya edilir (`user_id` saxlanılır, ad/email silinir). SOX/PCI üçün 7 il saxlama tələbi var.

**3. Audit log-ları əsas DB-də saxlamaq**
Production DB-nin 10x böyüklüyündə audit log — query performansını öldürür. Ayrı audit DB, ya da append-only storage (S3, ClickHouse).

**4. Sinxron audit yazma**
HTTP request içindən `AuditLog::create()` — hər action üçün əlavə DB write. Queue-based async logging (`dispatch(new LogAuditEvent(...))`) istifadə edin.

**5. GDPR silmə sorğusunu manual idarə etmək**
Hər table-dan əl ilə silmək — unudulan table-lar data sızması. Centralized `DataErasureService` bütün data source-ları bilir; cascade soft-delete + scheduled anonymization.

**6. Consent olmadan data toplamaq**
Analytics üçün cookie set etmək, user razı olmamışdan — GDPR Article 7 pozuntusu. Consent management platform (CMP) + server-side consent check.
