# Database Migration-larını Container-lərdə İcra Etmək

> **Səviyyə (Level):** ⭐⭐⭐ Senior

## Nədir? (What is it?)

Klassik VM dünyasında `php artisan migrate` deploy-un bir addımı idi — SSH et, script işlət. Container dünyasında (Docker, Kubernetes) isə **artıq SSH yoxdur**, app bir neçə replika kimi işləyir, deploy "image-i dəyişdir" deməkdir. Bu zaman sual belə çıxır:

- Migration-ı **haçan** işlədək?
- Migration-ı **harada** işlədək?
- 3 replika eyni vaxtda `migrate` etsə nə olar?
- Migration uzun çəkərsə (1 saatlıq `ALTER TABLE`) nə olar?
- Rollback nə olacaq?
- Zero-downtime deploy-da schema dəyişikliyi necə edilir?

Bu sənəd 3 əsas pattern-ı (entrypoint / init container / pre-deploy job), race condition-ları, və zero-downtime üçün **expand-contract** pattern-ini Laravel kontekstində açıqlayır.

## Üç Pattern — Müqayisə

| Pattern | Haçan işləyir | Race riski | Mürəkkəblik |
|---------|---------------|------------|-------------|
| **Entrypoint** | Hər pod start-da | **Yüksək** (N replika) | Ən sadə |
| **Init container** | Hər pod start-da (main-dən öncə) | **Orta** (rolling deploy) | Orta |
| **Pre-deploy job** | CI/CD-də, deploy-dan öncə | **Yox** (mərkəzləşdirilmiş) | Ən mürəkkəb |

### Pattern 1: Entrypoint-də Migration

**Nədir?** Konteyner entrypoint script-ində `php artisan migrate --force` çağırılır.

```bash
#!/bin/sh
# docker/entrypoint.sh
set -e

# Wait for DB
until nc -z "$DB_HOST" "${DB_PORT:-3306}"; do
    echo "Waiting for DB..."
    sleep 1
done

if [ "$RUN_MIGRATIONS" = "true" ]; then
    php artisan migrate --force --isolated
fi

exec "$@"
```

**Üstünlüklər:**
- Sadədir — bir script
- K8s/CI/CD konfiqurasiyası lazım deyil
- Kiçik layihələr (1 pod) üçün mükəmməldir

**Mənfilər:**
- **Race condition:** 3 pod eyni anda start olsa, hər biri `migrate` çağırır
- **App boot blocked:** Migration 2 dəqiqə çəkirsə, pod 2 dəqiqə hazır olmur
- **Readiness probe keçmir:** K8s pod-u NotReady sayır
- **Rolling deploy sınır:** Yeni version pod-u migration-ı gözləyir, köhnə version-ın ehtiyacı yoxdur

**Nə vaxt uyğundur?** 
- Single pod deployments (small apps, Fly.io, single VPS + Docker)
- Kritik olmayan mühitlər (staging, dev)

### Pattern 2: Init Container (K8s)

**Nədir?** Kubernetes pod spec-ində `initContainers` — main container-dan **əvvəl** işləyir, tamamlandıqda main başlayır.

```yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: laravel-app
spec:
  replicas: 3
  selector:
    matchLabels:
      app: laravel
  template:
    metadata:
      labels:
        app: laravel
    spec:
      initContainers:
        - name: wait-for-db
          image: busybox:latest
          command:
            - sh
            - -c
            - |
              until nc -z postgres 5432; do
                echo "Waiting for DB..."
                sleep 2
              done
        - name: migrate
          image: myapp:v1.2.3
          command:
            - php
            - artisan
            - migrate
            - --force
            - --isolated
          envFrom:
            - secretRef:
                name: app-secrets
            - configMapRef:
                name: app-config
      containers:
        - name: app
          image: myapp:v1.2.3
          ports:
            - containerPort: 9000
          envFrom:
            - secretRef:
                name: app-secrets
```

**Üstünlüklər:**
- Main container migration-ı gözləmir — əvvəl init bitir
- Fail olsa, pod CrashLoopBackOff olur, görünür
- Clean separation — migration kodla bir yerdə deploy olunur

**Mənfilər:**
- **Hər pod-da işləyir** — 3 replika = 3 dəfə init. `--isolated` DB lock olmadan race olurdu.
- **Rolling deploy race:** Yeni version pod-unun init-i başlayır, köhnə version hələ də işləyir, DB schema dəyişir, köhnə pod-a query xətası gəlir.
- **Pod boot uzanır** — hər pod migration gözləyir.

**Nə vaxt uyğundur?**
- Orta ölçülü K8s deployments
- Migration qısadır (< 10 saniyə)
- Backwards-compat migration-lar (aşağıda expand-contract-a bax)

### Pattern 3: Pre-Deploy Job (TÖVSİYƏ)

**Nədir?** CI/CD pipeline-da `kubectl apply -f deployment.yaml`-dən əvvəl ayrıca K8s Job işlədilir. **Bir dəfə**, mərkəzləşdirilmiş, idempotent.

```yaml
# k8s/migrate-job.yaml
apiVersion: batch/v1
kind: Job
metadata:
  name: migrate-${VERSION}       # Hər deploy üçün unikal ad
  labels:
    app: laravel
    job-type: migration
    version: ${VERSION}
spec:
  backoffLimit: 2                # 2 dəfə retry
  activeDeadlineSeconds: 1800    # 30 dəqiqədə max
  ttlSecondsAfterFinished: 86400 # 24 saat sonra sil
  template:
    metadata:
      labels:
        job-type: migration
    spec:
      restartPolicy: OnFailure
      containers:
        - name: migrate
          image: myapp:${VERSION}
          command:
            - php
            - artisan
            - migrate
            - --force
          envFrom:
            - secretRef:
                name: app-secrets
            - configMapRef:
                name: app-config
          resources:
            requests:
              memory: 256Mi
              cpu: 100m
            limits:
              memory: 512Mi
              cpu: 500m
```

CI/CD pipeline (GitHub Actions):

```yaml
# .github/workflows/deploy.yml
name: Deploy to Production

on:
  push:
    branches: [main]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Set version
        run: echo "VERSION=${GITHUB_SHA::7}" >> $GITHUB_ENV

      - name: Build and push image
        run: |
          docker build -t myapp:${VERSION} .
          docker tag myapp:${VERSION} registry.example.com/myapp:${VERSION}
          docker push registry.example.com/myapp:${VERSION}

      - name: Configure kubectl
        uses: azure/k8s-set-context@v3
        with:
          kubeconfig: ${{ secrets.KUBECONFIG }}

      # 1. Run migration FIRST
      - name: Run database migrations
        run: |
          envsubst < k8s/migrate-job.yaml | kubectl apply -f -
          kubectl wait --for=condition=complete \
            --timeout=30m \
            job/migrate-${VERSION}

      - name: Check migration logs
        if: always()
        run: kubectl logs job/migrate-${VERSION}

      # 2. Only deploy if migration succeeded
      - name: Deploy application
        run: |
          kubectl set image deployment/laravel-app \
            app=myapp:${VERSION} \
            --record
          kubectl rollout status deployment/laravel-app --timeout=10m

      # 3. Cleanup old jobs
      - name: Cleanup old migration jobs
        run: |
          kubectl delete jobs \
            -l job-type=migration \
            --field-selector status.successful=1 \
            --ignore-not-found
```

**Üstünlüklər:**
- **Bir dəfə işləyir** — race yoxdur
- **Fail early** — migration fail olsa deploy başlamır
- **Log-lar CI-da** — debug asan
- **Rollback rahat** — yeni job yarat, köhnə image-i işlət

**Mənfilər:**
- CI-da DB credentials lazımdır (secret management)
- Pipeline mürəkkəbləşir
- Job-ları təmizləmək lazımdır (yoxsa namespace dolur)

**Nə vaxt uyğundur?**
- **Çoxluq hallar** (production, staging, multi-replica)
- Tələb: sıfırdan bir yerdə migration icrası

## Race Condition-lar

### Problem: 3 Pod, 3 `migrate`

Entrypoint pattern-ində:

```
10:00:00.100  Pod A starts → CREATE TABLE users ...
10:00:00.150  Pod B starts → CREATE TABLE users ...     ← "table already exists" XƏTASI
10:00:00.200  Pod C starts → CREATE TABLE users ...     ← "table already exists" XƏTASI
```

Nəticə: Pod A deploy olur, Pod B və C CrashLoopBackOff. Və ya daha pisi — qismən migration, yarımçıq state.

### Həll 1: Laravel `--isolated` Flag (Laravel 9+)

Laravel 9-dan bəri `--isolated` flag-i var. Postgres-də `pg_try_advisory_lock`, MySQL-də `GET_LOCK()` istifadə edir:

```bash
php artisan migrate --force --isolated
```

**Necə işləyir:**
```php
// Pseudocode (Laravel internals)
if (!$lock->acquire('artisan-migrate')) {
    $this->info('Another migration is running, skipping.');
    return 0;  // Exit 0 — fail deyil
}

try {
    $this->runMigrations();
} finally {
    $lock->release();
}
```

Birinci pod lock alır, migration edir. İkinci və üçüncü pod "Another migration is running, skipping" deyib exit 0 verir. Race həll olunur.

**Tələlər:**
- Lock TTL yoxdursa, migration çökərsə lock qalır (dead lock)
- Laravel lock TTL defaultu yoxdur — **əl ilə yoxla**
- MySQL `GET_LOCK` session-based — connection bağlanarsa lock azad olur (nisbətən təhlükəsiz)

### Həll 2: Postgres Advisory Lock (Əl ilə)

```php
// app/Console/Commands/SafeMigrate.php
use Illuminate\Support\Facades\DB;

public function handle()
{
    $lockId = crc32('migration-lock');  // 32-bit int

    $acquired = DB::selectOne(
        'SELECT pg_try_advisory_lock(?) as acquired',
        [$lockId]
    );

    if (!$acquired->acquired) {
        $this->info('Another migration is running.');
        return 0;
    }

    try {
        $this->call('migrate', ['--force' => true]);
    } finally {
        DB::selectOne('SELECT pg_advisory_unlock(?)', [$lockId]);
    }

    return 0;
}
```

**Postgres üstünlüyü:** `pg_advisory_lock` session-based, connection ölərsə lock azad olur. Process ölüsündə deadlock qalmır.

### Həll 3: Schema Migration Tool

Daha böyük proqramlar üçün dedicated migration tool-lar:

| Tool | Dil | Xüsusiyyət |
|------|-----|------------|
| **Flyway** | Java | Versioned + repeatable, checksums |
| **Sqitch** | Perl/Shell | Dependency-based, no versions |
| **golang-migrate** | Go | Sadə, rollback dəstəkli |
| **Laravel migrate** | PHP | Built-in, sadə |

Laravel-də `migrate` adətən kifayətdir — `--isolated` + proper CI pipeline.

## Zero-Downtime Deploy: Expand-Contract Pattern

### Problem

Naive deploy:

```sql
-- Miqration: rename users.name → users.full_name
ALTER TABLE users RENAME COLUMN name TO full_name;
```

Deploy zamanı:
1. Migration işləyir, column adı dəyişir
2. Köhnə version pod-lar hələ işləyir, `users.name`-ə query edir → **XƏTA**
3. Yeni version pod-lar deploy olur, `users.full_name`-ə query edir → OK
4. Rolling deploy tamamlanır

**Nəticə:** 30 saniyə downtime, 500 error-lar.

### Həll: 5 Addımlı Expand-Contract

**Prinsip:** Hər migration backwards-compatible olmalıdır — həm köhnə, həm yeni kod işləməlidir.

#### Deploy 1: Expand (Əlavə et, silmə)

Migration:
```php
// database/migrations/2026_04_25_100000_add_full_name_to_users.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('full_name')->nullable()->after('name');  // NULLABLE!
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('full_name');
        });
    }
};
```

Kod:
```php
// app/Models/User.php
class User extends Model
{
    // Dual-write: həm name, həm full_name
    public function setFullNameAttribute(string $value): void
    {
        $this->attributes['name'] = $value;       // Köhnə column
        $this->attributes['full_name'] = $value;  // Yeni column
    }

    // Read-də: full_name varsa onu, yoxsa name
    public function getFullNameAttribute(): ?string
    {
        return $this->attributes['full_name'] ?? $this->attributes['name'];
    }
}
```

**Deploy:** Migration işləyir → yeni kod deploy olur. Köhnə kod `name`-i, yeni kod `full_name`-i oxuya bilir.

#### Deploy 2: Backfill

Yeni column-u mövcud data ilə doldur:

```php
// database/migrations/2026_04_25_110000_backfill_full_name.php
return new class extends Migration {
    public function up(): void
    {
        // Batch-lə işlə — böyük table-larda row-by-row lock-lamamaq üçün
        DB::table('users')
            ->whereNull('full_name')
            ->orderBy('id')
            ->chunkById(1000, function ($users) {
                foreach ($users as $user) {
                    DB::table('users')
                        ->where('id', $user->id)
                        ->update(['full_name' => $user->name]);
                }
            });
    }

    public function down(): void
    {
        // Backfill-i geri qaytarmaq olmaz
    }
};
```

**Böyük table-lar üçün** (10M+ satır) — migration job uzun çəkə bilər. Alternativ: `Artisan::command('users:backfill-full-name')` və queue job.

#### Deploy 3: Read From New

Kodu yalnız `full_name`-dən oxuyacaq şəkildə dəyişdir:

```php
// app/Models/User.php
class User extends Model
{
    // Dual-write saxla (hələ ki)
    public function setFullNameAttribute(string $value): void
    {
        $this->attributes['name'] = $value;
        $this->attributes['full_name'] = $value;
    }

    // Read yalnız yeni column-dan
    public function getFullNameAttribute(): string
    {
        return $this->attributes['full_name'];
    }
}
```

#### Deploy 4: Write Only To New

Köhnə column-a yazmağı dayandır:

```php
class User extends Model
{
    protected $fillable = ['full_name', 'email'];

    // `name` attribute-u mənalı şəkildə dəyişdirildi
    // Yeni kod yalnız full_name yazır
}
```

Bu addımda **köhnə pod işləməməlidir** — rolling deploy bitməlidir.

#### Deploy 5: Contract (Köhnə column-u sil)

Migration:
```php
return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('name')->nullable();
        });
        DB::statement('UPDATE users SET name = full_name');
    }
};
```

**Xülasə:**
```
Deploy 1 (Expand):    ADD full_name nullable     | code: dual-write
Deploy 2 (Backfill):  UPDATE users SET full_name  | kod dəyişmir
Deploy 3 (Read new):  schema dəyişmir             | code: read full_name
Deploy 4 (Write new): schema dəyişmir             | code: write only full_name
Deploy 5 (Contract):  DROP name                   | code: təmizlə
```

5 deploy = 5 gün-1 həftə. **Amma zero downtime.**

## Long-Running Migration (Böyük Table-lar)

### Problem

```sql
ALTER TABLE orders ADD COLUMN customer_tier VARCHAR(20);
```

10M-lik `orders` table-da bu əmr MySQL-də **table-i tam lock edir** (və ya metadata lock) → 10-30 dəqiqə downtime.

Postgres-də `ADD COLUMN WITHOUT DEFAULT` və ya `ADD COLUMN DEFAULT NULL` sürətli (metadata dəyişikliyi). Amma `ADD COLUMN DEFAULT 'bronze'` — **bütün satırları yeniləyir** (Postgres 11-dən əvvəl).

### Həll 1: Online Schema Change Tools

| Tool | Necə işləyir |
|------|--------------|
| **pt-online-schema-change** (Percona) | MySQL — shadow table yaradır, trigger ilə sync, sonda rename |
| **gh-ost** (GitHub) | MySQL — binlog-dan oxuyur, trigger-siz. Daha təhlükəsiz |
| **pg-online-schema-change** | Postgres alternative |

Misal gh-ost:
```bash
gh-ost \
  --user=migrator --password=secret \
  --host=mysql-primary.example.com \
  --database=laravel \
  --table=orders \
  --alter="ADD COLUMN customer_tier VARCHAR(20) DEFAULT 'bronze'" \
  --allow-on-master \
  --execute
```

gh-ost:
1. `orders_gho` shadow table yaradır (yeni schema)
2. Binlog-u oxuyub, original table-dan shadow-a row-by-row kopyalayır
3. Live DML-ləri binlog-dan shadow-a replay edir
4. Tamamlandıqda — atomic `RENAME orders → orders_old, orders_gho → orders`

### Həll 2: Laravel Migration Timeout

Default Laravel migration PDO timeout yoxdur — amma lock çox uzun olsa Kubernetes Job `activeDeadlineSeconds`-ı keçəndə öldürür. Migration job:

```yaml
spec:
  activeDeadlineSeconds: 3600    # 1 saat
  backoffLimit: 0                # Retry ETMƏ — yarımçıq migration təhlükəlidir
```

Postgres-də statement timeout:
```php
// Migration-da
DB::statement("SET statement_timeout = '30min'");
Schema::table('orders', function ($table) {
    $table->string('customer_tier')->default('bronze');
});
```

### Həll 3: Batch-li Backfill

Migration-da `ALTER TABLE DEFAULT` əvəzinə:

```php
// 1. NULL-able column əlavə et (instant)
Schema::table('orders', function ($table) {
    $table->string('customer_tier')->nullable();
});

// 2. Batch-lə backfill (artisan command, job-da)
DB::table('orders')
    ->whereNull('customer_tier')
    ->orderBy('id')
    ->chunkById(10000, function ($orders) {
        foreach ($orders as $order) {
            DB::table('orders')
                ->where('id', $order->id)
                ->update(['customer_tier' => $this->calculateTier($order)]);
        }
    });

// 3. NOT NULL constraint əlavə et (backfill bitdikdən sonra)
DB::statement('ALTER TABLE orders ALTER COLUMN customer_tier SET NOT NULL');
```

## Rollback Strategiyası

### Prinsip: Roll Forward, Not Back

Production-da `php artisan migrate:rollback` **TƏHLÜKƏLİDİR**:
- `down()` metodu adətən test olunmayıb
- Data loss (column drop-lar data-nı götürür)
- Dependent code versiya-lər fail ola bilər

**Düzgün yanaşma — Roll Forward:**
```
Buraxılış #42 bug var.
Rollback ETMƏ — Buraxılış #43 yarat ki problem-i düzəltsin.
```

**İstisna:** Migration özü fail olursa və state-i fix etmək lazımdır — manual DB intervention (DBA supervision).

### Backwards-Compat Migration — Rollback Rahat

Expand-contract pattern-ində hər addım backwards-compat olduğundan, hər deploy geri oynadıla bilər:

```
Deploy 1 (Expand)    → Rollback: DROP COLUMN   (data loss! — etmə)
Deploy 2 (Backfill)  → Rollback: no-op         (safe)
Deploy 3 (Read new)  → Rollback: code revert   (safe)
Deploy 4 (Write new) → Rollback: code revert   (safe)
Deploy 5 (Contract)  → Rollback: schema revert (data loss)
```

Addım 2-4 təhlükəsiz roll back. Addım 1 və 5 — təhlükəli, roll forward et.

## Laravel Migration Status Commands

```bash
# Migration statusu
php artisan migrate:status

# Pending migration-ları göstər (amma işlətmə)
php artisan migrate --pretend

# Yalnız bir migration
php artisan migrate --path=/database/migrations/2026_04_25_add_full_name.php

# Batch-ə görə rollback
php artisan migrate:rollback --step=1

# Fresh (DB-ni drop et, yenidən qur) — PROD-DA İSTİFADƏ ETMƏ
php artisan migrate:fresh --seed
```

## Best Practices

1. **Pre-deploy job pattern istifadə et** production-da — race yoxdur, atomic.
2. **Backwards-compat migration yaz** — expand-contract pattern.
3. **`--isolated` flag-ı əlavə et** əgər entrypoint/init pattern istifadə edirsən.
4. **Böyük table-lar üçün gh-ost / pt-osc** — 1M+ satır `ALTER` etmə naively.
5. **Backfill-i Laravel job-la et** — migration file-da yox (timeout + progress yoxdur).
6. **`activeDeadlineSeconds` qoy K8s Job-da** — sonsuz hang olmasın.
7. **Migration log-larını sakla** — CI-da artifact olaraq, 30 gün.
8. **Staging-də test et** — prod-ya oxşar data həcmində.
9. **Roll forward, roll back yox** — rollback pattern-i rahat üçün deyil.
10. **`DB::unprepared()` DDL üçün** — `DB::statement()` bəzi DDL-lərə uyğun deyil.
11. **Migration-ları kiçik saxla** — bir commit, bir migration.
12. **`--pretend` istifadə et** — şübhəli migration-ı prod-a göndərməzdən əvvəl SQL-ə bax.

## Tələlər (Gotchas)

### 1. `--isolated` MySQL-də session-local lock

**Problem:** `migrate --isolated` işləyir, amma connection pool yeni connection açanda lock başqa session-də.

**Həll:** Laravel lock store üçün `DB_CONNECTION` persistent olsun. Və ya Postgres advisory lock istifadə et.

### 2. Init container hər pod-da işləyir

**Problem:** 10 replika = 10 dəfə init container migration cəhdi. `--isolated` ilə 9-u skip edir, amma 10 DB connection açılır.

**Həll:** Pre-deploy Job pattern-ə keç.

### 3. `php artisan migrate` exit code 0 — amma failed migration

**Problem:** Migration-da `catch` block-u səssizcə error-u basdırır, Job success olur, amma schema yarımçıqdır.

**Həll:** Migration-da **try/catch yazma** — Laravel özü rollback edir. Exception-lar exit code 1 verməlidir.

### 4. `php artisan migrate:fresh` production-da

**Problem:** Developer yanlışlıqla staging-də işlədir → **BÜTÜN DATA ITIR**.

**Həll:** 
```php
// app/Providers/AppServiceProvider.php
if (app()->isProduction()) {
    Artisan::command('migrate:fresh', fn() => abort(403, 'FORBIDDEN IN PRODUCTION'));
}
```

### 5. Timezone-dependent migration

**Problem:** `CURRENT_TIMESTAMP` default — CI container timezone ilə prod DB timezone fərqli.

**Həll:** Migration-da `NOW()` əvəzinə fix tarix istifadə et:
```php
$table->timestamp('created_at')->useCurrent();  // DB server TZ
// YOX:
$table->timestamp('created_at')->default(Carbon::now());  // PHP container TZ
```

### 6. Foreign key lock escalation

**Problem:** `ALTER TABLE orders ADD FOREIGN KEY ... REFERENCES users` — MySQL-də users table-ı da lock edir.

**Həll:** FK-nı iki addımda:
```sql
ALTER TABLE orders ADD COLUMN user_id BIGINT UNSIGNED;       -- Fast
CREATE INDEX idx_orders_user ON orders(user_id);              -- Concurrent (Postgres) / gh-ost (MySQL)
ALTER TABLE orders ADD FOREIGN KEY (user_id) REFERENCES users(id);   -- Validate constraint
```

### 7. Migration secret-lərsiz

**Problem:** CI pipeline-da DB credentials `.env`-də commit olunub.

**Həll:** K8s Secret, AWS Secrets Manager, SOPS. CI-da `envsubst` ilə inject.

### 8. Job-un logları yoxdur

**Problem:** `kubectl get jobs` — completed, amma nə olduğunu görmürsən.

**Həll:** `kubectl logs job/migrate-v1.2.3` — pod-lar hələ `ttlSecondsAfterFinished`-dan öncə olmalıdır. CI-da logs-u artifact-a çıxart.

## Müsahibə Sualları

### 1. K8s-də migration-ı hansı pattern-lə işlədirsiz?

**Cavab:** Production-da **pre-deploy K8s Job** — CI/CD pipeline-da `kubectl apply -f migrate-job.yaml && kubectl wait`. Bir dəfə işləyir, race yoxdur. Init container pattern hər pod-da işləyir (N replika = N dəfə), entrypoint pattern isə həm race, həm app boot block yaradır. Kiçik app-lar üçün Laravel `--isolated` ilə entrypoint də OK.

### 2. 3 replika eyni vaxtda migrate çağırsa nə olur?

**Cavab:** Default Laravel-də race — biri `CREATE TABLE`, digərləri `already exists` xətası. `--isolated` flag (Laravel 9+) DB advisory lock istifadə edir (`pg_try_advisory_lock` və ya MySQL `GET_LOCK`) — yalnız biri işləyir, digərləri skip. Amma bu yalnız əsas lock-dur; ideal həll Job pattern-dir.

### 3. Zero-downtime deploy-da `users.name` column-un adını necə dəyişirsiniz?

**Cavab:** **Expand-contract pattern** — 5 deploy:
1. Expand: `ADD full_name NULLABLE` — kod dual-write
2. Backfill: `UPDATE users SET full_name = name`
3. Read new: kod `full_name`-dən oxuyur
4. Write new: kod yalnız `full_name` yazır
5. Contract: `DROP name`

Hər addımda köhnə və yeni kod paralel işləyə bilir — rolling deploy təhlükəsiz.

### 4. 10M satırlıq table-da `ADD COLUMN DEFAULT 'bronze'` necə edirsiniz?

**Cavab:** Naively `ALTER TABLE` MySQL-də table-i blocklayır — 10-30 dəqiqə downtime. Həll: **gh-ost** (GitHub Online Schema) və ya **pt-online-schema-change** (Percona) — shadow table, trigger ilə sync, atomic rename. Və ya 3 addımda: nullable column əlavə et → batch-lə backfill (job) → NOT NULL constraint.

### 5. Migration fail olsa nə edirsiniz?

**Cavab:** **Roll forward, not back.** Production `migrate:rollback` təhlükəlidir — `down()` metodu tez-tez test olunmur, data loss. Əvəzinə: yeni commit ilə düzəlt et, yenidən deploy et. İstisna: backfill job fail olursa, `retry` və ya ayrıca Artisan command-la resume et.

### 6. CI-da migration-a DB credentials necə verirsiniz?

**Cavab:** K8s Secret (CI kubeconfig-lə apply edir), AWS Secrets Manager + IAM role, Vault Agent. `.env` faylı commit ETMƏ. Migration Job `envFrom: secretRef: app-secrets` ilə secret-ləri environment-ə yükləyir.

### 7. Long-running migration monitor etmək üçün nə edirsiniz?

**Cavab:** K8s Job `activeDeadlineSeconds` (timeout), `backoffLimit: 0` (retry etmə — yarımçıq migration pis), CI-da `kubectl wait --timeout=30m`. Progress üçün Laravel command-da `$this->info()` və ya custom progress bar. gh-ost/pt-osc tool-ları öz progress log-ları verir.

### 8. `migrate` command vs schema migration tool (Flyway, Sqitch) — nə vaxt hansı?

**Cavab:** Laravel `migrate` sadə app-lar üçün kifayətdir — migration-lar PHP-də, Eloquent-lə, version control-də. Flyway/Sqitch multi-app (mikroservis-lər eyni DB-ni paylaşır), multi-language, və ya checksum validation lazım olduqda. Laravel-də checksum yoxdur — migration faylını dəyişdirsən, təkrar işləməyəcək, amma DB-də dəyişiklik yoxdur.


## Əlaqəli Mövzular

- [docker-entrypoint-scripts-laravel.md](40-docker-entrypoint-scripts-laravel.md) — Entrypoint pattern
- [kubernetes-jobs-cronjobs.md](32-kubernetes-jobs-cronjobs.md) — K8s Job ilə migration
- [kubernetes-helm.md](23-kubernetes-helm.md) — Helm pre-upgrade hook
