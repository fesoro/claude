# Migration Rollback

## Problem (nəyə baxırsan)
Schema migration deploy etdin, nəsə qırıldı və instinkt deyir "migration-ı rollback et". Bu adətən SƏHV instinktdir. Production-da migration rollback təhlükəlidir — data məhv edə bilər, səni yarım-vəziyyətdə qoya bilər, və ya vəziyyəti orijinal problemdən də pisləşdirə bilər.

İnsanları rollback-a sövq edən simptomlar:
- Yeni column ORM hydration-u qırdı
- Drop edilən column query-ni qırdı
- Rename edilən column-da client-lər hələ köhnə adı istinad edir
- Index əlavə olunması query plan regressiyasına səbəb oldu

## Sürətli triage (ilk 5 dəqiqə)

### Forward-only fəlsəfəsi

Əksər senior engineering təşkilatları prod-da **forward-only** migration işlədir. Bu o deməkdir ki:
- Migration-lar təhlükəsiz deploy üçün dizayn olunur
- Rollback normal əməliyyat DEYİL
- Problemlər tərsinə çevirməklə deyil, yeni migration göndərməklə həll olunur

Niyə? Schema-nı tərsinə çevirmək adətən itkili olur:
- Data yazıldıqdan sonra column drop → data itkisi
- Kod yazdıqdan sonra geri rename → hansı truth mənbəyidir?
- Sətri iki cədvələ bölmək → geri birləşdirmə qeyri-müəyyəndir

### Dayan və düşün

`php artisan migrate:rollback` işlətməzdən əvvəl:
1. Migration-dan sonra hansı data yazıldı?
2. Rollback o data-nı məhv edəcəkmi?
3. Sistemi stabilləşdirəcək forward fix varmı?

On dəfədən doqquzunda forward fix daha təhlükəsizdir.

## Diaqnoz

### Rollback NƏ VAXT OK-dir

- Hələ data yazılmayıb (migration işlədi, amma production kodu hələ yeni column-a çatmayıb)
- Təmiz DDL əlavəsi (yeni boş column, default yoxdur, data yoxdur) — təmiz geri çevrilir
- Migration-dan dərhal sonra, ilk bir neçə dəqiqədə tutulub
- Heç bir client app yeni strukturdan asılı deyil

### Rollback NƏ VAXT OK DEYİL

- Data yazılıb (itirəcəksən)
- Yeni kod yeni schema-nı oxumaq üçün istifadə edir — rollback həmin query-ləri uğursuz edir
- Qurulması saatlar çəkən index-lər — rollback-dan sonra yenidən qurma = daha çox downtime
- Rename əməliyyatları — yazıdan sonra rollback qeyri-müəyyənlik yaradır

## Fix (bleeding-i dayandır)

### Ssenari A: Yeni column app xətalarına səbəb olur

**Səhv fix**: column-u drop edən migration-ı rollback et.

**Düzgün fix**: yeni column-u düzgün idarə edən kod deploy et. Əgər kod uyğunsuzdursa, real fix-i göndərənə qədər column-u ignor edən hotfix deploy et.

```php
// Emergency: make column optional in the app
protected $casts = [
    'new_field' => 'string',  // default was strict type
];

// Or remove from $fillable until ready
protected $fillable = ['name', 'email']; // dropped new_field
```

### Ssenari B: Drop edilən column production-u qırdı

Kod hələ drop edilən column-u istinad edir. Hər yerdə app xətaları.

**Səhv fix**: column-u yenidən əlavə et və ümidlən.

**Düzgün fix**:
1. Drop edilən column-u istinad etməyən kod göndər (hotfix)
2. Təcili olsa, column-u nullable kimi yenidən əlavə et (data itkisi riski yoxdur), hotfix deploy et, sonra kod təmiz olandan sonra drop et

```sql
-- Emergency: re-add as nullable
ALTER TABLE users ADD COLUMN old_field VARCHAR(255) NULL;
```

### Ssenari C: Rename edilən column client-ləri qırdı

```sql
-- `orders.user_id` renamed to `customer_id`
-- Old code still writes `user_id` → error
```

**Səhv fix**: rename-i rollback et.

**Düzgün fix**: köhnə adı yeniyə map edən view və ya trigger yarat:

```sql
-- Temporary: add a generated column as alias
ALTER TABLE orders ADD COLUMN user_id BIGINT GENERATED ALWAYS AS (customer_id) STORED;
```

Və ya sadə trigger istifadə et. Client-ləri düzgün column adı ilə redeploy etmək üçün vaxt qazan.

### Ssenari D: Migration özü ortada ilişib

Bax: [migration-gone-wrong.md](migration-gone-wrong.md).

### Düzgün rollback (təhlükəsiz olanda)

Əgər data-nın təhlükəsiz olduğuna ƏMİNSƏNSƏ:

```bash
# Laravel
php artisan migrate:rollback --step=1
```

Default olaraq yalnız son batch-i rollback edir. N migration üçün `--step=N` istifadə et.

Yoxla:
```bash
php artisan migrate:status
```

## Əsas səbəbin analizi

- Migration production üçün təhlükəsiz dizayn olunmuşdumu? (Ruhən geri çevrilə bilən, expand-contract pattern?)
- Təmsilçi data ilə staging-də test olundumu?
- Review risk tutdumu?
- Migration checklist / gate varmı?

## Qarşısının alınması

### Expand-contract pattern-i

Column rename üçün çox-deploy təhlükəsiz migration:

**Deploy 1** — Expand:
```sql
ALTER TABLE orders ADD COLUMN customer_id BIGINT;
UPDATE orders SET customer_id = user_id;
-- Code writes to BOTH columns, reads customer_id first, falls back to user_id
```

**Deploy 2** — Reader-ləri migrate et:
```sql
-- Backfill any remaining
UPDATE orders SET customer_id = user_id WHERE customer_id IS NULL;
-- Add NOT NULL constraint
ALTER TABLE orders MODIFY customer_id BIGINT NOT NULL;
-- Code writes to BOTH, reads customer_id only
```

**Deploy 3** — Köhnəni yazmağı dayandır:
```sql
-- Code writes customer_id only
-- Verify no writes to user_id for N days
```

**Deploy 4** — Contract:
```sql
ALTER TABLE orders DROP COLUMN user_id;
```

Hər addım müstəqil olaraq təhlükəsizdir. Hər addım dayandırıla, retry edilə, və ya (point of no return-dən keçməmisənsə) rollback edilə bilər.

### Schema-dan ayrı data migration

Schema migration: sürətli, yalnız DDL.

Data migration: ayrı, idempotent, chunked, restartable.

```php
// Schema migration: add column (fast)
class AddPhoneToUsers extends Migration {
    public function up() {
        Schema::table('users', fn($t) => $t->string('phone')->nullable());
    }
}

// Data backfill: queued job, separate from migration
class BackfillUserPhones implements ShouldQueue {
    public function handle() {
        User::whereNull('phone')->chunkById(1000, function ($users) {
            foreach ($users as $user) {
                $user->update(['phone' => $this->lookup($user)]);
            }
        });
    }
}
```

Job-lar təhlükəsiz yenidən işlədilə bilər. Uzun işləyən migration yoxdur.

### Laravel-də reversible migration-lar

```php
public function up(): void {
    Schema::table('users', fn($t) => $t->string('phone')->nullable());
}

public function down(): void {
    Schema::table('users', fn($t) => $t->dropColumn('phone'));
}
```

`down()` metodu mövcuddur, amma onu YALNIZ-DEV təhlükəsizlik şəbəkəsi kimi qiymətləndir. Prod-da forward-only.

### Emergency data düzəltmə script-ləri

Test edilmiş, idempotent script-lərin qovluğunu saxla:

```
database/corrections/
├── 2026-04-17-fix-duplicate-orders.php
├── 2026-04-18-reprocess-webhooks.php
```

Nümunə:
```php
// Idempotent: safe to re-run
DB::transaction(function () {
    $duplicates = Order::select('order_number')
        ->groupBy('order_number')
        ->havingRaw('COUNT(*) > 1')
        ->pluck('order_number');
    
    foreach ($duplicates as $number) {
        // Keep oldest, mark others as cancelled
        $orders = Order::where('order_number', $number)->orderBy('id')->get();
        $orders->skip(1)->each->update(['status' => 'duplicate']);
    }
});
```

`php artisan tinker` və ya one-off command ilə işlət. Script-i commit et. Nə/nə vaxt/niyə sənədləşdir.

## PHP/Laravel xüsusi qeydlər

### Laravel migration komandalarının xülasəsi

```bash
# Run pending migrations
php artisan migrate --force   # --force required in prod

# Pretend (show SQL, don't run)
php artisan migrate --pretend

# Status
php artisan migrate:status

# Rollback last batch
php artisan migrate:rollback

# Rollback specific steps
php artisan migrate:rollback --step=3

# Reset all migrations (DEV ONLY — destroys data)
php artisan migrate:reset

# Fresh (drop all tables and re-run) — DEV ONLY
php artisan migrate:fresh
```

### Migrations cədvəli

Laravel işlədilmiş migration-ları `migrations` cədvəlində izləyir:

```sql
SELECT * FROM migrations ORDER BY batch DESC, id DESC LIMIT 10;
```

Əgər Laravel xaricində manual olaraq migration tətbiq edirsənsə, record əlavə et:

```sql
INSERT INTO migrations (migration, batch) VALUES ('2026_04_17_add_phone', 
  (SELECT COALESCE(MAX(batch), 0) + 1 FROM migrations m2));
```

### Production-da destruktiv migration etmə

Əlavə təsdiq olmadan qadağan:
- `DROP TABLE`
- Mövcud data ilə `DROP COLUMN`
- `RENAME COLUMN`
- Tipi uyğun olmayan şəkildə dəyişən `MODIFY COLUMN`
- Test edilmiş rollback planı olmayan hər hansı migration

## Yadda saxlanmalı real komandalar

```bash
# Status check
php artisan migrate:status

# Dry run
php artisan migrate --pretend

# Rollback carefully
php artisan migrate:rollback --step=1

# Force in CI/CD
php artisan migrate --force

# SQL-level check
mysql -e "SELECT * FROM migrations ORDER BY batch DESC, id DESC LIMIT 5"

# Re-add a dropped column (emergency)
mysql -e "ALTER TABLE users ADD COLUMN email VARCHAR(255) NULL"

# Generated column alias (MySQL 5.7+)
mysql -e "ALTER TABLE orders ADD COLUMN user_id BIGINT GENERATED ALWAYS AS (customer_id) STORED"
```

## Müsahibə bucağı

"Bir migration deploy etdin və prod-u qırdı. Rollback edirsən?"

Güclü cavab:
- "Demək olar ki, heç vaxt yox. Forward roll edirəm."
- "Schema rollback-ı nadir hallarda itkisizdir. Əgər data yeni column-a yazılıbsa, rollback onu məhv edir."
- "Default-um: cari schema-nı işləyən hotfix göndərmək. Drop edilən column-u nullable kimi təcili yenidən əlavə etmək. İtkin column-ları istinad etməyən kod dəyişiklikləri deploy etmək."
- "Struktur qarşısının alınması expand-contract migration-dır: bir addımda rename və ya drop etmə. Əvəzində bir neçə təhlükəsiz deploy."
- "Data migration schema migration-dan ayrılır — schema sürətlidir, data backfill queued, chunked, idempotent-dir."
- "Fövqəladə hallar üçün idempotent düzəltmə script-ləri qovluğum var. Qabaqcadan test edilmiş, commit olunmuş, sənədləşdirilmiş."

Bonus: "Bir şirkətdə prod siyasətində 'rollback' qadağan idi. Hər migration expand-contract olmalı, ya da açıq VP təsdiqi tələb etməli idi. Bu bizi əvvəldən daha təhlükəsiz migration-lar dizayn etməyə məcbur etdi. Migration-lardan incident sayı sıfıra yaxın düşdü."
