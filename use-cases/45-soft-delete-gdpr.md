# Soft Delete & GDPR Compliance (Middle)

## Problem necə yaranır?

**Soft delete tək başına GDPR-ı ödəmir:** Soft delete `deleted_at` set edir, PII (email, phone, address) hələ DB-dədir. GDPR "right to erasure" tələbi var — PII həqiqətən silinməlidir.

**Hard delete problemi:** `DELETE FROM users WHERE id = 1` — foreign key cascade, orphan orders, audit trail itirilir, referential integrity pozulur.

**Unique constraint çatışmazlığı:** Email unique constraint var. User soft delete edilir. Başqa user eyni email ilə qeydiyyatdan keçmək istəyir — bloklanır. `deleted_at` NULL deyil, amma constraint keçmir.

---

## Həll: Soft Delete + Scheduled Anonymization

```
User silinir → soft delete (deleted_at set)
30 gün sonra → scheduled job PII-nı anonymize edir
ID saxlanır, PII silinir → GDPR compliant + FK integrity
```

---

## İmplementasiya

*Bu kod soft delete lifecycle-ını, unique constraint probleminin həllini, 30 günlük retention siyasəti ilə anonimləşdirməni və cascade soft delete-i göstərir:*

```php
// Soft delete + anonymization lifecycle
class User extends Model
{
    use SoftDeletes;

    // Soft delete zamanı unique constraint problemini həll et:
    // email "deleted_{id}_original@..." olur → başqası eyni email-lə qeydiyyat keçə bilər
    protected static function booted(): void
    {
        static::deleting(function (User $user) {
            if (!$user->isForceDeleting()) {
                $user->email = "deleted_{$user->id}_{$user->email}";
                $user->save();
            }
        });
    }
}

// Validation-da soft deleted user-ları nəzərə al
class RegisterRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => [
                'required',
                'email',
                // Yalnız aktiv (not deleted) user-lar arasında unique yoxla
                Rule::unique('users', 'email')->whereNull('deleted_at'),
            ],
        ];
    }
}

// Retention Policy — 30 gün sonra PII anonymize et
class RetentionPolicyService
{
    public function applyUserRetention(): void
    {
        User::onlyTrashed()
            ->where('deleted_at', '<', now()->subDays(30))
            ->whereNull('anonymized_at')
            ->chunk(100, function ($users) {
                foreach ($users as $user) {
                    $this->anonymizeUser($user);
                }
            });
    }

    private function anonymizeUser(User $user): void
    {
        DB::transaction(function () use ($user) {
            $user->update([
                'name'          => 'Deleted User',
                'email'         => "anon_{$user->id}@deleted.invalid",
                'phone'         => null,
                'address'       => null,
                'anonymized_at' => now(),
            ]);

            // S3-dəki şəkil silinir
            if ($user->avatar) {
                Storage::delete($user->avatar);
                $user->update(['avatar' => null]);
            }

            // Audit log — məcburi saxlanır
            AuditLog::create([
                'action'       => 'user_anonymized',
                'entity_id'    => $user->id,
                'performed_at' => now(),
            ]);
        });
    }
}

// Cascade soft delete — Laravel FK cascade soft delete-i dəstəkləmir
class OrderService
{
    public function cancelAndSoftDelete(int $orderId): void
    {
        DB::transaction(function () use ($orderId) {
            $order = Order::with('items')->findOrFail($orderId);
            $order->delete(); // soft delete

            // Manual cascade: order items-ı da soft delete et
            $order->items()->delete();

            // Stok bərpa et
            foreach ($order->items as $item) {
                $item->product->increment('stock', $item->quantity);
            }
        });
    }
}

// Anonymized user-ın postlarını filter et
class Post extends Model
{
    use SoftDeletes;

    // Anonymized user-ın postlarını gizlət
    public function scopeVisible(Builder $query): Builder
    {
        return $query
            ->whereNull('posts.deleted_at')
            ->whereHas('user', fn($q) => $q->whereNull('anonymized_at'));
    }
}
```

---

## MySQL Partial Index (Unique Constraint)

`deleted_at IS NULL` şərtli partial index daha performanslı həll:

*Bu kod yalnız aktiv (silinməmiş) email-lər üçün MySQL 8+ partial unique index yaradılmasını göstərir:*

```sql
-- Yalnız aktiv (silinməmiş) email-lər üçün unique constraint
-- MySQL 8.0+: functional index ilə partial unique constraint
CREATE UNIQUE INDEX idx_users_email_active
ON users (email)
WHERE deleted_at IS NULL;

-- Bu index soft deleted user-ları ignore edir
-- Eyni email-lə yeni qeydiyyat mümkün olur
```

Laravel migration-da:

*Bu kod Laravel migration-da partial index yaradılması seçimlərini göstərir:*

```php
// MySQL 8+ partial index
$table->rawIndex('(email) WHERE deleted_at IS NULL', 'users_email_active_unique');

// Ya da application-level: Rule::unique()->whereNull('deleted_at')
```

---

## Data Retention Siyasəti

| Data növü | Müddət | Səbəb |
|-----------|--------|-------|
| User account (PII) | 30 gün sonra anonymize | GDPR |
| Payment records | 7 il saxla | Vergi qanunu |
| Audit logs | 2 il | Compliance |
| Session data | 30 gün | Security |
| App logs (PII-sız) | 90 gün | Debugging |
| Marketing consent | Consent revoke → dərhal sil | GDPR |

---

## Anti-patterns

- **Soft delete = GDPR compliance saymaq:** Soft delete PII-nı saxlayır. 30 gün retention + anonymize məcburidir.
- **Hard delete istifadəsi:** Orphan records, FK constraint violation, audit trail itirilir.
- **`deleted_at` NULL unique index:** MySQL-də NULL = NULL false deməkdir — unique constraint NULL-ları bloklamır. `whereNull('deleted_at')` validation-da şərtli yoxlama lazımdır.
- **Anonymize edərkən ID-ni silmək:** ID foreign key-dir — silinməməlidir. Yalnız PII sahələr anonymize edilir.

---

## İntervyu Sualları

**1. Soft delete tək başına GDPR-ı ödəyirmi?**
Xeyr. Soft delete yalnız `deleted_at` set edir, PII (email, phone) hələ DB-dədir. GDPR erasure: PII anonymize edilməlidir. Həll: soft delete + 30 gün retention + scheduled anonymization job.

**2. Soft delete-də unique constraint problemi necə həll edilir?**
`email UNIQUE` constraint. Soft deleted user-ın email-i başqasını bloklayır. Həll 1: `Rule::unique()->whereNull('deleted_at')` — yalnız aktiv user-lar arasında yoxla. Həll 2: Soft delete event-ında email-i `deleted_{id}_{email}` et — constraint bloklamaz. Həll 3: MySQL 8+ partial index `WHERE deleted_at IS NULL`.

**3. Laravel-də cascade soft delete?**
Laravel FK `onDelete('cascade')` yalnız hard delete-də işləyir. Soft cascade manual: model `deleting` event-ında child record-ları `.delete()` et. Observer pattern ilə daha təmiz.

**4. Anonymization-da payment records necə idarə edilir?**
Vergi qanunu ödəniş məlumatlarını 7 il saxlamağı tələb edir. Həll: `amount`, `currency`, `transaction_id`, `date` saxla; `cardholder_name`, `billing_address`, `email` sil/anonymize et. DB-də ayrı `payment_transactions` cədvəlində PII olmayan finansal data qalır.

**5. Soft deleted record-u bərpa etmək (restore) lazım olsa?**
`User::withTrashed()->find($id)->restore()` — `deleted_at` null edilir, aktif olur. Lakin email artıq rename edilibsə: restore event-ında orijinal email-i geri qay tar. Bu üçün `original_email` sütununda saxlamaq lazım ola bilər. Restore mümkünlüyü anonymize-dan əvvəl (30 gün window) var.

**6. `withTrashed()` query-ləri harada istifadə edilir?**
Admin panel-də silinmiş record-ları göstərmək üçün. `onlyTrashed()` — yalnız silinmiş. `withTrashed()` — hamısı. Authorization vacibdir: yalnız admin rol bu query-ləri çağıra bilər. API endpoint-ləri default olaraq soft deleted record-ları gizlətməlidir.

---

## Anti-patternlər

**1. Soft delete-i GDPR "silmə hüququ" ilə eyniləşdirmək**
`deleted_at = NOW()` set etməyi GDPR Article 17 tələbini ödəmək kimi qəbul etmək — `deleted_at` olan record-da email, phone, ad hələ var. Soft delete yalnız "aktiv deyil" deməkdir; GDPR üçün PII sahələr anonymize edilməlidir.

**2. `deleted_at`-li cədvəldə global unique index saxlamaq**
`email` sütununa `UNIQUE` constraint qoyub soft delete-i nəzərə almamaq — silnmiş istifadəçinin email-i başqasının qeydiyyatını bloklayır. Unique index `WHERE deleted_at IS NULL` partial index kimi yaradılmalıdır (ya da email soft delete zamanı rename edilməlidir).

**3. Soft delete-i FK constraint ilə birlikdə düzgün idarə etməmək**
Parent record soft delete edildikdə child record-larını (orders, comments) silməmək — child record-lar DB-də "yetim" qalır, sorğularda görünür. Soft delete event-ında `deleting` hook ilə child record-lar da cascade soft delete edilməlidir.

**4. Retention period olmadan soft deleted record-ları sonsuza saxlamaq**
`deleted_at` dolu record-ları heç vaxt anonymize etməmək — DB sonsuz böyüyür, köhnə silnmiş müştərilərin PII-nı lazımsız saxlamaq GDPR ihlalıdır. Scheduled job 30 gün sonra soft deleted record-ları anonymize etməlidir.

**5. `withTrashed()` sorğularını authorization-sız istifadə etmək**
Admin panel-də `User::withTrashed()->find($id)` çağırışını rol yoxlaması olmadan etmək — hər istifadəçi silnmiş profillərə çıxış əldə edə bilər. Soft deleted record-lara çıxış yalnız müəyyən rol/permission ilə məhdudlaşdırılmalıdır.

**6. Anonymize edilmiş record-u "silindi" kimi audit log-a yazmamaq**
PII anonymize edildikdə audit trail-ə qeyd etməmək — GDPR Article 5(2) "accountability" tələbi: kim, nə vaxt, hansı əsasla PII silindi sualı cavabsız qalır. Hər anonymization hadisəsi `gdpr_erasure_log` cədvəlində timestamp, user_id, operator_id ilə qeyd edilməlidir.

**7. Soft deleted record-ları DB-dən tamamilə silmək üçün scheduled job yazmamaq**
Anonymize edildikdən illər sonra soft deleted record-ların hələ də DB-də qalmasına icazə vermək — lüzumsuz storage, GDPR "data minimization" prinsipinə zidd. Anonymize edildikdən 1 il sonra (ya da müəyyən müddət) record-lar hard delete edilməlidir; FK-lar anonymized placeholder ID-yə point edəcək.
