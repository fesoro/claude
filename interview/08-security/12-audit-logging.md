# Audit Logging (Senior ⭐⭐⭐)

## İcmal
Audit logging — sistemdə kimin, nə vaxt, hansı resursa, hansı əməliyyatı etdiyini qeyd edən mexanizmdir. Müntəzəm application log-larından fərqli olaraq audit log-lar iş (business) dəyişikliklərini izləyir: data dəyişiklikləri, icazə qərarları, istifadəçi hərəkətləri. Interview-da bu mövzu Senior developer-ın compliance (SOC 2, GDPR, HIPAA) tələblərini, incident response prosesini nə dərəcədə başa düşdüyünü yoxlayır.

## Niyə Vacibdir
Audit log-lar breach sonrası forensic analiz üçün əvəzolunmazdır — "nə baş verdi, hansı data təsirləndi, kim etdi" suallarına cavab verir. Compliance standartları (GDPR, HIPAA, PCI DSS, SOC 2) audit log-u tələb edir. PCI DSS minimum 1 il saxlama tələb edir. GDPR data access-i qeyd etmək tələb edir. İnterviewerlər bu mövzunu yoxlayanda developer-ın yalnız log yazmağı deyil, log-ların tamamsallığını (integrity), saxlanma müddətini, axtarışını, alerting-i düşündüyünü görmək istəyir.

## Əsas Anlayışlar

- **Audit log vs Application log**: Application log — texniki event-lər (error, debug, warning). Audit log — iş əməliyyatları ("user X record Y-i dəyişdirdi, köhnə dəyər: A, yeni dəyər: B"). Hər ikisi lazımdır, qarışdırmaq olmaz.
- **Audit log minimum atributları**: `who` (actor_id, actor_type), `when` (timestamp, timezone), `what` (action), `where` (resource_type, resource_id), `result` (success/failure), `context` (IP, user agent, correlation_id).
- **Immutability**: Audit log-lar dəyişdirilə bilməz olmalıdır — attacker öz izlərini silə bilməsin. Write-once storage, append-only log, `UPDATE/DELETE` icazəsi yoxdur.
- **Centralized logging**: Log-lar application server-ından ayrı məkanda saxlanmalıdır — server pozulsa log da itməsin. ELK Stack, Datadog, AWS CloudWatch Logs, Grafana Loki.
- **Tamper detection**: Log-ların dəyişdirilmədiyini təsdiqləmək — hash chain, digital signature, AWS CloudTrail log file validation.
- **Log retention policy**: GDPR: minimum lazım olan müddət, right to erasure ilə konflikt var. PCI DSS: 1 il (3 ay online, 9 ay archive). SOC 2: müştəri müqaviləsinə görə. HIPAA: 6 il.
- **Sensitive data masking**: Audit log-da plain text şifrə, kredit kartı, SSN, token olmamalı. Mask (`***`) ya hash edilməli. GDPR üçün PII-nin loqlanması da problem yarada bilər.
- **Structured logging**: JSON format — axtarış, analiz, alert, SIEM üçün çox rahat. Plain text log-lar analiz etmək çox çətindir.
- **SIEM integration**: Security Information and Event Management — audit log-lar SIEM-ə (Splunk, Elastic SIEM, Sentinel) göndərilir, pattern analizi, anomaly detection, alert.
- **Failed access logging**: Uğursuz giriş cəhdləri ən vacib audit event-lərindəndir — brute force, unauthorized access cəhdlərini detect etmək üçün.
- **Privilege use logging**: Admin əməliyyatları, role dəyişiklikləri, icazə verme/alma — bütün privilege escalation cəhdləri.
- **Data access logging**: Sensitive data-ya (PII, payment data, health data) hər access audit edilməlidir — GDPR DSAR üçün lazımdır.
- **Correlation ID**: Bir request-in bütün sistem komponentlərindəki audit trail-ini birləşdirmək üçün unikal ID. Microservices-də xüsusilə vacibdir.
- **Alerting on critical events**: Admin hesabına giriş, toplu data export, çoxlu uğursuz giriş — real-time alert lazımdır.
- **GDPR DSAR audit tələbi**: Data Subject Access Request — "kim mənim datamı nə vaxt oxudu?" — bunu cavablamaq üçün data access log-ları lazımdır.
- **Non-repudiation**: İstifadəçi "mən etmədim" deyə bilməsin — audit log bunu sübut edir. Digital imza əlavə güc verir.
- **Log rotation vs retention**: Application log-lar rotate edilir, audit log-lar retention policy-yə uyğun saxlanır — fərqli strategiya.

## Praktik Baxış

**Interview-da necə yanaşmaq:**
Audit logging-i sadəcə "log yazırıq" kimi qısaltmayın. Güclü cavab immutability-ni, sensitive data masking-i, retention policy-ni, SIEM/alerting aspektlərini əhatə edir. Laravel-də konkret implementation nümunəsi (Observer, Event Listener, Middleware) göstərmək xalı artırır.

**Hansı konkret nümunələr gətirmək:**
- "Biz GDPR uyğunluğu üçün personal data-ya hər access-i audit log-a yazırıq — kim oxudu, nə vaxt, hansı məqsədlə"
- "Payment əməliyyatları üçün ayrı audit log channel qurduq — 7 il saxlanır, PCI DSS tələbinə görə"
- "Audit log-lar application server-ından fərqli S3 bucket-a yazılır, versioning aktiv, delete icazəsi yoxdur"
- "SIEM-ə 5 dəqiqə ərzində 10-dan çox uğursuz login cəhdini alert edir"

**Follow-up suallar interviewerlər soruşur:**
- "Audit log özü compromised olsa nə edərdiniz?"
- "Log-larda PII datanı necə idarə edirsiniz — GDPR right to erasure ilə log retention necə balanslaşır?"
- "Microservices mühitindəki distributed audit trail-i necə birləşdirirsiniz?"
- "Audit log-un performance-a təsiri? Synchronous vs asynchronous yazma?"
- "Audit log-da `old_values` saxlamaq nə qədər disk yer tutur, strategiyanız nədir?"

**Red flags — pis cavab əlamətləri:**
- Application log ilə audit log-u eyniləşdirmək
- Log-larda plain text şifrə ya kredit kartı
- "Log-lar eyni database-dədir" — attacker hamısını silə bilər
- Retention policy-nin compliance tələblərinə uyğun olub-olmadığını düşünməmək
- Synchronous yazma ilə request latency artacağını düşünməmək

## Nümunələr

### Tipik Interview Sualı
"Laravel tətbiqinizdə istifadəçilərin hansı əməliyyatlarını audit etmək lazımdır? Bu məlumatları necə saxlayıb monitor edərdiniz?"

### Güclü Cavab
"İlk növbədə audit scope-u müəyyən edərdim — hər event-i audit etmək çox geniş, lazımsız noise yaradır. Prioritet event-ləri belə müəyyən edərəm:

Authentication event-ləri — login (success/fail), logout, password reset, MFA əlavə/silmə, token revocation.

Authorization event-ləri — permission yoxlanması uğursuzluqları (access denied case-lər), role dəyişiklikləri.

Sensitive data operations — PII oxunması, export, deletion, bulk operations.

Admin əməliyyatları — istifadəçi yaratma/silmə, konfiqurasiya dəyişiklikləri, system settings.

Hər audit record-da: `actor_id`, `actor_type`, `actor_ip`, `actor_agent`, `action`, `resource_type`, `resource_id`, `old_values`, `new_values`, `timestamp`, `correlation_id`.

Storage: audit log-lar application database-indən ayrıdır. Mən append-only PostgreSQL cədvəli istifadə edirəm, `UPDATE`/`DELETE` icazəsi yoxdur. Uzunmüddətli saxlama üçün S3-ə göndəririk, Object Lock aktiv, delete icazəsi heç kimə yoxdur.

Performance: audit yazma asynchronous — Queue ilə. Request latency-sinə təsiri minimaldır. Lakin critical event-lər (authentication fail) synchronous yazılır — itməsin deyə.

GDPR: PII-nin adını deyil, hash-ini log-layırıq. Right to erasure gəldikdə audit log-un legal holdunu qorumaq üçün hüquq şöbəsi ilə balans qururuq."

### Kod Nümunəsi — AuditLog Model (Immutable)

```php
// app/Models/AuditLog.php
class AuditLog extends Model
{
    protected $fillable = [
        'actor_id', 'actor_type', 'actor_ip', 'actor_agent',
        'action', 'resource_type', 'resource_id',
        'old_values', 'new_values',
        'metadata', 'correlation_id', 'occurred_at',
    ];

    protected $casts = [
        'old_values'  => 'array',
        'new_values'  => 'array',
        'metadata'    => 'array',
        'occurred_at' => 'datetime',
    ];

    // Timestamps yoxdur — sadəcə occurred_at
    public $timestamps = false;

    public static function boot(): void
    {
        parent::boot();

        // Audit log-lar immutable — update və delete qadağandır
        static::updating(function () {
            throw new \LogicException('Audit logs are immutable and cannot be updated.');
        });
        static::deleting(function () {
            throw new \LogicException('Audit logs cannot be deleted.');
        });
    }
}
```

### Kod Nümunəsi — AuditService (Mərkəzi Log Yazma)

```php
// app/Services/AuditService.php
class AuditService
{
    private const SENSITIVE_FIELDS = [
        'password', 'password_confirmation',
        'credit_card', 'card_number', 'cvv',
        'ssn', 'social_security',
        'token', 'secret', 'api_key', 'private_key',
    ];

    public function log(
        string $action,
        string $resourceType,
        int|string|null $resourceId = null,
        array $oldValues = [],
        array $newValues = [],
        array $metadata = []
    ): void {
        $actor = auth()->user();

        // Asynchronous yazma — request latency-ni artırma
        dispatch(new WriteAuditLogJob([
            'actor_id'       => $actor?->id,
            'actor_type'     => $actor ? $actor::class : 'system',
            'actor_ip'       => request()?->ip(),
            'actor_agent'    => request()?->userAgent(),
            'action'         => $action,
            'resource_type'  => $resourceType,
            'resource_id'    => (string) $resourceId,
            'old_values'     => $this->maskSensitive($oldValues),
            'new_values'     => $this->maskSensitive($newValues),
            'metadata'       => $metadata,
            'correlation_id' => request()?->header('X-Correlation-ID') ?? (string) Str::uuid(),
            'occurred_at'    => now()->toIso8601String(),
        ]));
    }

    // Critical event-lər — synchronous (itməsin)
    public function logCritical(string $action, array $data = []): void
    {
        AuditLog::create(array_merge($data, [
            'action'      => $action,
            'occurred_at' => now(),
        ]));
    }

    private function maskSensitive(array $data): array
    {
        foreach (self::SENSITIVE_FIELDS as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = '***REDACTED***';
            }
        }
        return $data;
    }
}
```

### Kod Nümunəsi — Model Observer (Avtomatik Audit)

```php
// app/Observers/UserObserver.php
class UserObserver
{
    public function __construct(private AuditService $audit) {}

    public function updated(User $user): void
    {
        $dirty = $user->getDirty();

        // Əhəmiyyətsiz field dəyişiklikləri — log-lama
        $ignoredFields = ['updated_at', 'last_seen_at', 'remember_token'];
        $dirty = array_diff_key($dirty, array_flip($ignoredFields));

        if (empty($dirty)) return;

        $this->audit->log(
            action: 'user.updated',
            resourceType: 'User',
            resourceId: $user->id,
            oldValues: array_intersect_key($user->getOriginal(), $dirty),
            newValues: $dirty,
        );
    }

    public function deleted(User $user): void
    {
        // Critical — synchronous, itməsin
        $this->audit->logCritical('user.deleted', [
            'actor_id'      => auth()->id(),
            'actor_ip'      => request()->ip(),
            'resource_type' => 'User',
            'resource_id'   => (string) $user->id,
            'old_values'    => $this->audit->maskSensitive($user->toArray()),
            'occurred_at'   => now(),
        ]);
    }

    public function restored(User $user): void
    {
        $this->audit->log(
            action: 'user.restored',
            resourceType: 'User',
            resourceId: $user->id,
        );
    }
}
```

### Kod Nümunəsi — Failed Login Listener

```php
// app/Listeners/LogFailedLogin.php
use Illuminate\Auth\Events\Failed;

class LogFailedLogin
{
    public function __construct(private AuditService $audit) {}

    public function handle(Failed $event): void
    {
        $ip      = request()->ip();
        $email   = $event->credentials['email'] ?? 'unknown';
        $attempts = RateLimiter::attempts('login:' . $ip);

        // Synchronous — authentication event kritikdir
        $this->audit->logCritical('auth.login_failed', [
            'actor_id'       => null,
            'actor_ip'       => $ip,
            'actor_agent'    => request()->userAgent(),
            'action'         => 'auth.login_failed',
            'resource_type'  => 'User',
            'resource_id'    => null,
            'metadata'       => [
                'email'          => hash('sha256', $email), // PII — hash
                'attempts'       => $attempts,
                'lockout_at'     => $attempts >= 5 ? now()->addMinutes(15)->toIso8601String() : null,
            ],
            'occurred_at'    => now(),
        ]);

        // Alert: 5+ cəhd — suspicious activity
        if ($attempts >= 5) {
            event(new SuspiciousLoginAttemptDetected($ip, $email));
        }
    }
}
```

### Kod Nümunəsi — Database Migration

```php
// database/migrations/xxxx_create_audit_logs_table.php
Schema::create('audit_logs', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('actor_id')->nullable()->index();
    $table->string('actor_type', 50)->default('user');
    $table->string('actor_ip', 45)->nullable();
    $table->text('actor_agent')->nullable();
    $table->string('action', 100)->index();
    $table->string('resource_type', 100)->index();
    $table->string('resource_id', 100)->nullable()->index();
    $table->json('old_values')->nullable();
    $table->json('new_values')->nullable();
    $table->json('metadata')->nullable();
    $table->uuid('correlation_id')->index();
    $table->timestamp('occurred_at')->useCurrent()->index();

    // Composite indexes — axtarış üçün
    $table->index(['resource_type', 'resource_id']);
    $table->index(['actor_id', 'occurred_at']);
    $table->index(['action', 'occurred_at']);

    // created_at/updated_at yoxdur — immutable record
});

// Database-level immutability (PostgreSQL)
// Audit user-inin UPDATE/DELETE icazəsi yoxdur
// CREATE USER audit_writer WITH PASSWORD '...';
// GRANT INSERT, SELECT ON audit_logs TO audit_writer;
// -- UPDATE, DELETE icazəsi verilmir
```

### Attack Nümunəsi — Silinən Audit Log

```
Ssenari: Attacker sistem daxilinə girib, izlərini silmək istəyir

Audit log eyni database-dədirsə:
1. Attacker: DELETE FROM audit_logs WHERE actor_ip = '185.x.x.x';
2. Forensic analiz: log boşdur, heç nə tapılmır
3. İncident report: "attack haqda məlumat yoxdur"

Düzgün qoruma:
1. Audit log-lar ayrı database-ə yazılır
   - Application user sadece INSERT icazəsi var
   - DELETE/UPDATE icazəsi yoxdur
2. Real-time S3-ə stream edilir (Kinesis Firehose)
   - S3 Object Lock: WORM (Write Once Read Many)
   - Compliance mode: 7 il ərzində silinmir
3. CloudTrail: S3-dəki hər access da log-lanır
4. SIEM-ə real-time push
   - SIEM-də audit log artıq ayrı sistemdədir
   - Application server-indən müstəqil

Nəticə: Attacker application server-ini tam silsə belə
        audit trail başqa sistemdə mövcud qalır
```

## Praktik Tapşırıqlar

- Mövcud tətbiqinizdə hansı əməliyyatlar audit olunur? Hansılar çatışmır? (authentication fail, data export, admin action?)
- Audit log silinə bilər? Kim silə bilər? `UPDATE/DELETE` icazəsini application user-dən götürün
- GDPR "right to erasure" tələbi gəldikdə audit log-daki PII-ni necə idarə edərdiniz? Hash + separate PII store strategiyası?
- `pg_stat_activity` ilə audit yazma əməliyyatının query time-ını ölçün — synchronous vs asynchronous fərqini görün
- Admin bir dəfəyə 1000+ record export etdi — bu event-i necə detect edib alert edərdiniz?
- Microservices mühitindəki 5 fərqli service-dən keçən bir əməliyyatın correlation_id ilə tam trail-ini birləşdirin
- S3-də Object Lock konfiqurasiya edin: audit log bucket-a DELETE icazəsini deaktiv edin, compliance mode aktivləşdirin

## Əlaqəli Mövzular
- `04-authentication-authorization.md` — Authentication event-ləri ən kritik audit mövzularındandır
- `11-least-privilege.md` — PoLP ilə birlikdə audit kim nəyə çatdığını izləyir
- `08-secrets-management.md` — Secret access-i audit etmək
- `13-data-encryption.md` — Audit log-da sensitive data masking
- `15-threat-modeling.md` — Threat model-də audit log forensic evidence kimi rol oynayır
