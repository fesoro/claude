# Migration Gone Wrong (Senior)

## Problem (nəyə baxırsan)
Kimsə production-da database migration işlətdi. İndi nəsə qırılıb — ya migration hələ də işləyir və kritik cədvəli lock edir, ya bitib amma səssizcə app-i qırıb, ya da qismən tətbiq olunub və schema uyğunsuzdur.

Simptomlar:
- `php artisan migrate` production-da 20+ dəqiqə asılı qalır
- Cədvələ bütün write-lər timeout olur
- App `Unknown column` və ya `Table doesn't exist` ilə 500 qaytarır
- Column rename köhnə adı gözləyən client app-ləri qırdı
- ALTER TABLE metadata lock tutub, hər şeyi bloklayır

## Sürətli triage (ilk 5 dəqiqə)

### Migration hələ də işləyir?

MySQL:
```sql
SHOW FULL PROCESSLIST;
-- look for ALTER TABLE, CREATE INDEX, etc.
```

Postgres:
```sql
SELECT pid, now() - query_start AS duration, state, query
FROM pg_stat_activity
WHERE query LIKE '%ALTER%' OR query LIKE '%CREATE INDEX%';
```

### Lock edir?

MySQL:
```sql
-- MySQL 8+
SELECT * FROM performance_schema.metadata_locks;
SELECT * FROM performance_schema.data_locks;
```

Postgres:
```sql
SELECT * FROM pg_locks WHERE NOT granted;
```

### Kill etmək?

Yalnız təhlükəsiz kill edilə biləndirsə. Bəzi migration-lar (xüsusilə `ALGORITHM=INPLACE` olmayan MySQL ALTER) artıq cədvəlin çox hissəsini yenidən yazmış ola bilər — ortada kill etmək qarışıqdır, amma adətən OK-dır. Qiymətləndir:

- Əməliyyatın təmiz checkpoint-i varmı? (Postgres: əksər halda bəli; MySQL 5.7-: şübhəli)
- Cədvəl indi kritikdirmi?
- Rollback + retry downtime-ına tab gətirə bilərsən?

MySQL:
```sql
KILL 12345;
```

Postgres:
```sql
SELECT pg_terminate_backend(12345);
```

## Diaqnoz

### MySQL ALTER niyə lock edir

**MySQL 5.6 və daha əvvəlki** — ALTER TABLE cədvəli yenidən qurur. Table-level lock, bütün müddətdə read/write-lər bloklanır.

**MySQL 5.6+** — bir çox ALTER-lər `ALGORITHM=INPLACE, LOCK=NONE` (online DDL) istifadə edə bilər. Amma:
- Bəzi əməliyyatlar hələ də kopiya tələb edir (column type dəyişmə, fulltext index əlavə etmə)
- Başlanğıcda və sonda metadata lock (qısa müddətlə bloklayır).
- Rebuild üçün disk yeri lazımdır (cədvəl ölçüsündən 1-2x).

**MySQL 8** — əlavə təkmilləşdirmələr, instant ADD COLUMN (yalnız metadata).

### Postgres niyə daha yaxşıdır amma hələ də risklidir

Postgres DDL transactional-dır. Əksər ALTER-lər sürətli, yalnız metadata dəyişiklikləridir. AMMA:
- Böyük cədvəldə `ALTER TABLE ... ADD COLUMN ... DEFAULT x NOT NULL` yenidən yazma apara bilər (Postgres 11+ DEFAULT hiyləsini yaxşı idarə edir, amma yoxla)
- `CREATE INDEX` write-ləri lock edir — `CREATE INDEX CONCURRENTLY` istifadə et
- `ALTER TABLE ... SET DATA TYPE` cədvəli yenidən yazır
- İstənilən ALTER qısa müddətlə `ACCESS EXCLUSIVE` əldə edir — əgər uzun işləyən query daha zəif lock tutursa, ALTER gözləyir, və ALTER-dən sonra hər yeni query də gözləyir → yığılma

### MySQL online schema change alətləri

**pt-online-schema-change (Percona Toolkit)**
```bash
pt-online-schema-change \
  --alter "ADD COLUMN phone VARCHAR(20)" \
  D=mydb,t=users \
  --execute
```

Necə işləyir: shadow cədvəl yaradır, trigger-lərlə sinxron saxlayaraq data-nı chunk-lar halında köçürür, sonra atomik şəkildə dəyişdirir. Production write-lərinə sıfır lock təsiri.

**gh-ost (GitHub)**
```bash
gh-ost \
  --host=primary.db \
  --user=migrator --password=... \
  --database=mydb --table=users \
  --alter="ADD COLUMN phone VARCHAR(20)" \
  --execute
```

Trigger yoxdur, dəyişiklikləri izləmək üçün binlog oxuyur. Çox yüksək-trafik cədvəllər üçün daha təhlükəsiz.

Production-da 10M-dən çox sətirli cədvəldə hər hansı schema dəyişikliyi üçün bunları istifadə et.

## Fix (bleeding-i dayandır)

### Migration asılı qalıbsa

1. Həqiqətən ilişibmi yoxsa sadəcə yavaş olduğunu yoxla. Sətir sayı irəliləməsinə bax (bəzi alətlər report edir).
2. İlişibsə və prod-u bloklayırsa: kill et.
3. Schema vəziyyətini yoxla:
   ```sql
   SHOW CREATE TABLE users;
   ```
4. Qismən olsa, ya manual bitir, ya da rollback et.

### Column rename client-ləri qırdı

Klassik: `user_id`-i `customer_id`-ə dəyişdin. App hələ deploy olunur, `user_id` yazır. 500 xətaları.

**Expand-contract pattern** (düzgün yol):

1. **Expand** — yeni column əlavə et, dual-write
   ```sql
   ALTER TABLE orders ADD COLUMN customer_id BIGINT;
   UPDATE orders SET customer_id = user_id;
   -- App writes to both user_id AND customer_id
   ```

2. **Migrate readers** — customer_id-dən oxumaq üçün kod yenilə
   ```sql
   -- App reads customer_id only
   ```

3. **Contract** — user_id yazmağı dayandır, drop et
   ```sql
   -- App writes customer_id only
   ALTER TABLE orders DROP COLUMN user_id;
   ```

Əgər expand-contract etmədinsə və client-lər qırıldısa:
- Emergency: köhnə column adını sinxron saxlayan view və ya trigger əlavə et
- Yaxud: tez yeni column yazan/oxuyan kod deploy et

### Qismən migration vəziyyəti

```sql
-- Laravel
php artisan migrate:status
```

Hansı migration-ların tətbiq olunmuş kimi qeyd edildiyini göstərir. Əgər cədvəl uyğunsuz vəziyyət göstərir, amma Laravel migration işləyib hesab edirsə, manual düzəlt:

```sql
-- Record migration as applied (Laravel 8+)
INSERT INTO migrations (migration, batch) VALUES ('2026_04_17_add_phone', 5);

-- Or remove and rerun
DELETE FROM migrations WHERE migration = '2026_04_17_add_phone';
```

## Əsas səbəbin analizi

- Migration əvvəl staging-də işlədimi?
- Staging-də təmsilçi data həcmi var idimi?
- Migration review olundumu? Review riski tutdumu?
- Riskli migration-lar üçün runbook varmı?

## Qarşısının alınması

- 1M-dən çox sətirli cədvəllər üçün **heç vaxt** prod-da xam `php artisan migrate` işlətmə — pt-osc / gh-ost istifadə et
- 100k-dan çox sətirli cədvəllər üçün migration ikinci mühəndis tərəfindən review olunsun
- Staging DB-də təmsilçi data həcmi olsun (yalnız 100 sətir deyil)
- Staging-də migration-ları dry-run et, vaxtlarını ölç
- Migration planı daxil edir: gözlənən müddət, bloklayıcı davranış, rollback proseduru
- Bütün column rename-lər üçün expand-contract
- Data backfill job-larını schema migration-lardan ayır

## PHP/Laravel xüsusi qeydlər

### Təhlükəsiz Laravel migration pattern-ləri

```php
// Add column — safe, fast
Schema::table('users', function (Blueprint $table) {
    $table->string('phone')->nullable();
});

// Add column with default — potentially slow (older MySQL)
Schema::table('users', function (Blueprint $table) {
    $table->string('plan')->default('free');  // PROBABLY SLOW on large table
});

// Backfill separately from DDL
// Step 1: add nullable column
Schema::table('users', function (Blueprint $table) {
    $table->string('phone')->nullable();
});
// Step 2: backfill via queue job, chunked
User::whereNull('phone')->chunkById(1000, function ($users) {
    $users->each->update(['phone' => '...']);
});
// Step 3: maybe later, add NOT NULL constraint
```

### Column-u təhlükəsiz rename et

Belə etmə:
```php
$table->renameColumn('user_id', 'customer_id');
```

Belə et: bir neçə deploy boyunca expand-contract.

### Prod-da migration rollback bir mifdir

```php
public function down(): void
{
    // In theory this works, in practice: almost never safe in prod
    Schema::table('users', function ($table) {
        $table->dropColumn('phone');
    });
}
```

Problem: əgər data `phone`-a yazılıbsa, rollback onu drop edir. Data itirirsən.

Forward-only migration istifadə et (lazım gələrsə tərs migration yeni migration kimi yazılır).

### Laravel migration komandaları

```bash
# Check status
php artisan migrate:status

# Run
php artisan migrate --force

# Rollback last batch
php artisan migrate:rollback

# Rollback N migrations
php artisan migrate:rollback --step=3

# Pretend (show SQL without running)
php artisan migrate --pretend
```

## Yadda saxlanmalı real komandalar

```bash
# pt-online-schema-change
pt-online-schema-change --alter "ADD COLUMN phone VARCHAR(20)" D=mydb,t=users --execute

# gh-ost
gh-ost --host=primary --user=migrator --password=... \
  --database=mydb --table=users \
  --alter="ADD COLUMN phone VARCHAR(20)" --execute

# Laravel migration dry-run
php artisan migrate --pretend

# Check migration state
php artisan migrate:status

# Postgres concurrent index
psql -c "CREATE INDEX CONCURRENTLY idx_users_email ON users(email);"
```

```sql
-- MySQL online DDL syntax
ALTER TABLE users 
  ADD COLUMN phone VARCHAR(20), 
  ALGORITHM=INPLACE, LOCK=NONE;

-- Postgres lock-free index
CREATE INDEX CONCURRENTLY idx_x ON t(col);
```

## Müsahibə bucağı

"Prod-da bir migration ilişib. Nə edirsən?"

Güclü cavab:
- "Əvvəl: ilişib yoxsa sadəcə yavaş olduğunu təsdiqlə. Process list-ə bax, keçən vaxtı gör."
- "Nəyi bloklayırsa yoxla. Metadata lock o cədvələ hər yeni query-nin yığılması deməkdir."
- "Qərar: kill edib rollback, yoxsa gözləmək? İstifadəçi təsiri və migration növündən asılıdır."
- "Kill edirsənsə: sonra schema vəziyyətini `SHOW CREATE TABLE` ilə yoxla. Lazım gələrsə `migrations` cədvəlini təmizlə."
- "Qarşısının alınması: prod-da böyük ALTER-ləri birbaşa işlətmirəm. MySQL üçün `pt-online-schema-change` və ya `gh-ost`, Postgres üçün `CONCURRENTLY` index istifadə edirəm."
- "Column rename-i heç vaxt birbaşa etmirəm — həmişə bir neçə deploy boyunca expand-contract."

Bonus döyüş hekayəsi: "Həmkar 500M-sətirli MySQL 5.6 cədvəlində `ALTER TABLE orders ADD COLUMN ... DEFAULT 'x'` işlətdi. Write-ləri 40 dəqiqə lock etdi. Kill etdik, parçaladıq: (1) nullable column, (2) arxa fonda chunked UPDATE, (3) NOT NULL set. Ümumi downtime: 5 dəqiqə (kill + rollback üçün). pt-osc tələbini migration checklist-imizə əlavə etdik."
