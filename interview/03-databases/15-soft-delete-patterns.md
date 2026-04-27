# Soft Delete Patterns (Middle ⭐⭐)

## İcmal
Soft delete — data-nı fiziki olaraq silmək əvəzinə "silindi" kimi işarələmə yanaşmasıdır. Laravel-də `deleted_at` column ilə `SoftDeletes` trait-i bu pattern-in ən tanınmış tətbiqidir. Görünən qədər sadə deyil — index performance, unique constraints, audit, compliance, retention policy kimi ciddi trade-off-lar var.

## Niyə Vacibdir
Yanlış soft delete tətbiqi query-ləri yavaşladır, unique constraint-ləri pozur, index-ləri böyüdür, GDPR uyğunsuzluğuna gətirir. İnterviewer bu sualla sizin "sadə feature"-i dərindən düşünüb-düşünmədiyinizi yoxlayır: `WHERE deleted_at IS NULL`-u hər sorğuda unutmaq, unique constraint ilə conflict, archive strategiyası — bunları bilmək senior məsuliyyəti deməkdir.

## Əsas Anlayışlar

- **deleted_at Column:** `NULL` = aktiv row, timestamp = silinib. Nə vaxt silindiyi məlumatı var. Ən geniş yayılmış yanaşma
- **is_deleted Boolean:** Daha sadə, lakin "nə vaxt silindi" məlumatı yoxdur. Audit tələbləri olan sistemlər üçün uyğun deyil
- **Global Scope:** ORM-də avtomatik `WHERE deleted_at IS NULL` filteri. Laravel `SoftDeletes` trait-i bunu avtomatik edir. Gizli xəta: `withTrashed()` unutduqda
- **Partial Index:** `WHERE deleted_at IS NULL` şərti olan index — yalnız aktiv row-ları index-ə daxil edir. Daha kiçik, daha sürətli, deleted row-lar index-dən xaricdir
- **Unique Constraint Problem:** `email UNIQUE` + soft delete → user silindi, eyni email ilə yeni user qeydiyyat → constraint error. Çözüm: `UNIQUE(email) WHERE deleted_at IS NULL`
- **Performance Impact:** Zamanla table-da çox silindi row yığılır. `WHERE deleted_at IS NULL` hər sorğuda filtering edir; partial index olmadan bütün table taranır
- **Compliance/Audit:** GDPR, financial regulations — data-nı müəyyən müddət saxlamaq məcburidir. Soft delete bu tələbi yerinə yetirir
- **Cascade Soft Delete:** Silindi parent-ın child-larını da soft delete etmək lazımdır. Database trigger ya da ORM hook ilə
- **Hard Delete after Retention Period:** Müəyyən müddət (6 ay, 1 il) sonra soft deleted row-ları fiziki silmək — GDPR "right to erasure" + disk idarəsi
- **Status Column:** `deleted_at` əvəzinə `status = 'active'|'deleted'|'suspended'` — daha flexible, lakin unique constraint ilə problem eynidir
- **withTrashed():** Laravel-də silindi row-ları da göstər. Məs: admin panel-də
- **onlyTrashed():** Yalnız silindi row-ları göstər. Restore seçimi üçün
- **forceDelete():** Fiziki silmə — soft delete by-pass. GDPR "right to erasure" üçün
- **paranoid deletion (Sequelize):** Node.js ORM-inin analoji konsepsi — `paranoid: true`
- **Archive Table Pattern:** Köhnə silindi row-ları ayrı `*_archive` tablosuna köçürmək — əsas table kiçik qalır, arxiv ayrı idarə olunur
- **GDPR Pseudonymization:** Fiziki silmə əvəzinə personal data-nı pseudonymize etmək (e-mail, ad silmək, hash saxlamaq) — billing, analytics üçün record saxlamaq lazım olduqda

## Praktik Baxış

**Interview-da yanaşma:**
- Unique constraint problemini mütləq qeyd edin — ən çox nəzərdən qaçan tərəfdir
- Partial index-i bilmək sizi fərqləndirəcək — "bütün silindi row-lar üçün index lazım deyil"
- "Global scope olmayan raw query-lərdə `deleted_at IS NULL` unutmaq" — real bug riski

**Follow-up suallar:**
- "Soft delete ilə unique email constraint-i necə birlikdə saxlarsınız?" — Partial unique index
- "Partial index niyə lazımdır?" — Aktiv row-lar az, deleted çox olduqda query-ni dramatik sürətləndirir
- "GDPR-də soft delete yetərlidirmi?" — Personal data saxlanır, "right to erasure" üçün hard delete ya pseudonymization lazımdır
- "Uzunmüddətli saxlama üçün plan nədir?" — Retention policy + scheduled hard delete + archive table
- "Child table-ların cascade soft delete-ini necə idarə edirsiniz?" — ORM hook ya database trigger

**Ümumi səhvlər:**
- Global scope-un bütün ORM metodlarında işlədiyini güman etmək — raw query-lərdə işləmir
- Unique constraint problemini qeyd etməmək — yeni user həmin emaillə qeydiyyat cəhdi edəndə error
- Archive strategijasını düşünməmək — table şişir, query-lər yavaşlayır
- GDPR-də soft delete-in "right to erasure"-u yerinə yetirmədiyini bilməmək

**Yaxşı cavabı əla cavabdan fərqləndirən:**
- Partial index-i izah etmək
- "Email unique + soft delete" həllini bilmək
- Retention policy — "6 aydan köhnə silindi row-ları fiziki sil" demək
- GDPR pseudonymization pattern-ını bilmək

## Nümunələr

### Tipik Interview Sualı
"User-ləri soft delete ilə siliyorsunuz. Email unikal olmalıdır. Silindi user-in emaili ilə yeni user qeydiyyat etmək istəyir. Conflict yaranır. Necə həll edərdiniz?"

### Güclü Cavab
Bu klassik soft delete + unique constraint problemidir. Üç həll var:

**1. Partial unique index (ən yaxşı):** `CREATE UNIQUE INDEX ON users(email) WHERE deleted_at IS NULL` — yalnız aktiv user-lər arasında unikallıq tələb olunur. Silindi user-in emaili constraint-ə girmir. PostgreSQL-də mükəmməl işləyir.

**2. Email mutation on delete:** Silindikdə e-maili `deleted+timestamp@original.com` kimi dəyişmək. Data korruptə olur, audit-da qarışıqlıq yaranır — tövsiyə etmirəm.

**3. Archive table:** Silindikdə row-u `users_archive`-ə köçür, `users`-dan fiziki sil. Əsas tabloda unique constraint sadə işləyir, arxivdə duplicate e-mail ola bilər.

Birinci yanaşma ən temizdir — PostgreSQL-in partial index-ini aktiv istifadə edir.

### Kod Nümunəsi
```sql
-- Standart table + soft delete
CREATE TABLE users (
    id         BIGSERIAL PRIMARY KEY,
    email      VARCHAR(255) NOT NULL,
    name       VARCHAR(100) NOT NULL,
    deleted_at TIMESTAMPTZ DEFAULT NULL,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

-- PROBLEM: Bu unique index silindi user-in emailini bloklar
-- CREATE UNIQUE INDEX ON users(email);

-- HƏLL 1: Partial unique index
CREATE UNIQUE INDEX idx_users_email_active
ON users(email)
WHERE deleted_at IS NULL;
-- Artıq eyni email iki aktiv user üçün unique qalır
-- Silindi user-in emaili constraint-ə daxil deyil
-- SELECT * FROM users WHERE email = 'ali@test.com' AND deleted_at IS NULL
-- → unique, problem yox

-- HƏLL 2: Partial performance index (aktiv user-lar üçün)
CREATE INDEX idx_users_active_created
ON users(created_at DESC, name)
WHERE deleted_at IS NULL;
-- Bütün silindi row-lar index-dən xaricdir
-- EXPLAIN ANALYZE ilə yoxla: daha kiçik index, daha sürətli scan

-- Silindikdən sonra eyni email test:
INSERT INTO users (email, name) VALUES ('ali@test.com', 'Əli');
DELETE FROM users WHERE email = 'ali@test.com';  -- fiziki silmə
-- Ya da:
UPDATE users SET deleted_at = NOW() WHERE email = 'ali@test.com';  -- soft delete

INSERT INTO users (email, name) VALUES ('ali@test.com', 'Yeni Əli');
-- Partial index: uğurlu! deleted_at IS NULL deyil köhnə üçün
```

```php
// Laravel SoftDeletes tam nümunə
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Model
{
    use SoftDeletes;

    // Avtomatik: hər query-ə WHERE deleted_at IS NULL əlavə olunur
    // $table->softDeletes() migration-da deleted_at TIMESTAMPTZ column yaradır

    protected $casts = [
        'deleted_at' => 'datetime',
    ];
}

// Standart əməliyyatlar:
$user = User::find(1);
$user->delete();         // deleted_at = NOW() — soft delete
$user->restore();        // deleted_at = NULL — bərpa et
$user->forceDelete();    // Fiziki sil — GDPR üçün

// Silindi row-ları göstər
User::withTrashed()->get();                          // Hamısı
User::onlyTrashed()->get();                          // Yalnız silindi
User::withTrashed()->where('id', $id)->first();      // Bir silindi user

// Global scope by-pass edən hal (diqqət!)
DB::table('users')->where('id', 1)->first();
// Bu soft delete-i nəzərə ALMAZ! deleted_at = NULL check yoxdur
// DÜZGÜN: User::find(1)  (Global scope işləyir)
```

```php
// Validation-da soft delete aware unique check
use Illuminate\Validation\Rule;

$rules = [
    'email' => [
        'required',
        'email',
        // Yalnız aktiv (deleted_at IS NULL) user-lər arasında unique
        Rule::unique('users', 'email')->whereNull('deleted_at'),
        // Əgər bu user-in öz emailidirsə ignore et (update zamanı)
        // Rule::unique('users', 'email')
        //     ->whereNull('deleted_at')
        //     ->ignore($userId),
    ],
];

// Migration
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('email');
    $table->string('name');
    $table->softDeletes();  // deleted_at TIMESTAMPTZ column
    $table->timestamps();
    // Normal unique index QOYMA — partial index raw SQL ilə əlavə et
});

// Partial unique index migration-da
DB::statement('
    CREATE UNIQUE INDEX idx_users_email_active
    ON users(email)
    WHERE deleted_at IS NULL
');
```

```php
// Cascade soft delete — order silinəndə child-lar da silinsin
class Order extends Model
{
    use SoftDeletes;

    protected static function boot(): void
    {
        parent::boot();

        static::deleting(function (self $order) {
            // Soft delete trigger olunanda child-ları da soft delete et
            $order->items()->delete();      // OrderItem model-inin delete()
            $order->payments()->delete();   // Payment model-inin delete()
            $order->shipments()->delete();  // Shipment model-inin delete()
            // delete() burada soft delete-dir (model SoftDeletes use edirsə)
        });

        static::restoring(function (self $order) {
            // Restore ediləndə child-ları da restore et
            $order->items()->withTrashed()->restore();
            $order->payments()->withTrashed()->restore();
        });
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}
```

```php
// Retention Policy — GDPR compliance
// app/Console/Commands/PurgeOldDeletedRecords.php
class PurgeOldDeletedRecords extends Command
{
    protected $signature   = 'users:purge {--days=365}';
    protected $description = 'GDPR: 1 ildən köhnə silindi user-ləri fiziki sil';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoff = now()->subDays($days);

        $this->info("Purging users deleted before {$cutoff}...");

        // GDPR pseudonymization yanaşması (tam silmə əvəzinə)
        $count = User::onlyTrashed()
            ->where('deleted_at', '<', $cutoff)
            ->chunkById(500, function ($users) {
                foreach ($users as $user) {
                    // Personal data-nı sil, statistical data saxla
                    $user->forceFill([
                        'email'    => "deleted-{$user->id}@anonymized.invalid",
                        'name'     => 'Deleted User',
                        'phone'    => null,
                        'address'  => null,
                    ])->saveQuietly();

                    // Sonra fiziki sil (ya da saxla — analytics üçün)
                    $user->forceDelete();
                }
            });

        $this->info("Done.");
        return Command::SUCCESS;
    }
}

// Cron-a qeyd et (app/Console/Kernel.php)
protected function schedule(Schedule $schedule): void
{
    $schedule->command('users:purge --days=365')
             ->monthly()
             ->withoutOverlapping()
             ->onOneServer();
}
```

```sql
-- Archive table pattern
-- Silindikdə archive tabloya köçür, əsas tabloda fiziki sil

CREATE TABLE users_archive (
    LIKE users INCLUDING ALL,       -- Eyni struktur
    archived_at TIMESTAMPTZ DEFAULT NOW(),
    archive_reason TEXT DEFAULT 'soft_delete'
);

-- Trigger: soft delete zamanı archive-ə köçür
CREATE OR REPLACE FUNCTION fn_archive_deleted_user()
RETURNS TRIGGER AS $$
BEGIN
    IF NEW.deleted_at IS NOT NULL AND OLD.deleted_at IS NULL THEN
        INSERT INTO users_archive
        SELECT NEW.*, NOW(), 'soft_delete';
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_archive_user
AFTER UPDATE OF deleted_at ON users
FOR EACH ROW
EXECUTE FUNCTION fn_archive_deleted_user();

-- Bu yanaşmanın üstünlüyü:
-- Əsas users table-da həmişə aktiv user-lər var
-- Partial index lazım deyil — normal UNIQUE işləyir
-- Archive table ayrıca vacuum, index strategiyası
-- Dezavantaj: restore mürəkkəbdir (archive-dən geri köçürmək)
```

```sql
-- Performance müqayisəsi: partial index olmadan vs ilə
-- EXPLAIN ANALYZE ilə test:

-- Index olmadan (bütün 1M row + 800K silindi):
EXPLAIN ANALYZE
SELECT * FROM users
WHERE email = 'ali@test.com'
  AND deleted_at IS NULL;
-- Seq Scan → 1M row oxuyur, yavaş

-- Normal unique index:
CREATE UNIQUE INDEX ON users(email);
EXPLAIN ANALYZE
SELECT * FROM users
WHERE email = 'ali@test.com'
  AND deleted_at IS NULL;
-- Index Scan → 1 row tapır, lakin deleted row-lar index-dədir

-- Partial index:
CREATE UNIQUE INDEX idx_users_email_active
ON users(email)
WHERE deleted_at IS NULL;
EXPLAIN ANALYZE
SELECT * FROM users
WHERE email = 'ali@test.com'
  AND deleted_at IS NULL;
-- Partial Index Scan → yalnız 200K aktiv user index-də
-- ~4x daha kiçik index, daha sürətli
```

## Praktik Tapşırıqlar

- `WHERE deleted_at IS NULL` şərtsiz sorğu yazın, "silindi" user-lərin də gəldiyini görün; sonra `User::` modeli ilə fərqi müşahidə edin
- Partial unique index yaradın, silindi user-in emaili ilə yeni user qeydiyyat edin — uğurlu olduğunu verify edin
- `EXPLAIN ANALYZE` ilə partial index vs tam index performansını müqayisə edin (1M row, 80% deleted)
- GDPR ssenarisi: `forceDelete` əvəzinə pseudonymization — personal data silin, statistics saxlayın
- Cascade soft delete: order silindikdə items + payments + shipments-ı da soft delete edin, sonra restore edin — child-ların da restore olduğunu görün
- Retention policy command yazın, 30 gündən köhnə soft deleted user-ləri chunk-larla fiziki silin

## Əlaqəli Mövzular
- `04-index-types.md` — Partial index — yalnız aktiv row-lar üçün
- `14-uuid-vs-autoincrement.md` — ID seçimi + soft delete birlikdə düşünülməli
- `16-database-migration-strategies.md` — `deleted_at` column əlavə etmə — zero-downtime migration
- `03-normalization-denormalization.md` — Archive table = denormalization qərarı
