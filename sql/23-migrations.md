# Migrations & Schema Management (Junior)

## Migration nedir?

Database schema deyisikliklerini version control eden sistemdir. Her deyisiklik ayri migration fayli ile izlenir.

---

## Laravel Migrations

### Yaratma

```bash
php artisan make:migration create_orders_table
php artisan make:migration add_status_to_orders_table --table=orders
```

### Strukturu

```php
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();                                    // BIGINT UNSIGNED AUTO_INCREMENT PK
            $table->foreignId('user_id')->constrained();     // FK + index
            $table->string('status', 50)->default('pending');
            $table->decimal('total_amount', 12, 2);
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();                            // created_at, updated_at
            $table->softDeletes();                           // deleted_at
            
            $table->index(['status', 'created_at']);
        });
    }
    
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
```

### Icra

```bash
php artisan migrate                  # Butun pending migration-lari icra et
php artisan migrate --step           # Her migration-i ayri batch-de
php artisan migrate:rollback         # Son batch-i geri al
php artisan migrate:rollback --step=2  # Son 2 migration-i geri al
php artisan migrate:reset            # Hamisi geri al
php artisan migrate:fresh            # DROP ALL + migrate (development ucun!)
php artisan migrate:status           # Hansilari icra olunub?
```

---

## Zero-Downtime Migrations

Production-da downtime olmadan schema deyisiklikleri etmek.

### Tehlikeli emeliyyatlar

```php
// TEHLIKELI: Table lock yaradır (boyuk table-larda deqiqeler sure biler)

// 1. Column rename
$table->renameColumn('name', 'full_name'); // MySQL-de table lock!

// 2. Column type deyisiklyi
$table->string('status', 100)->change(); // Table rebuild!

// 3. NOT NULL elave etmek (default olmadan)
$table->string('email')->nullable(false)->change(); // Movcud NULL row-lar?

// 4. Index elave etmek (boyuk table-da)
$table->index('email'); // Yavas ola biler
```

### Safe Migration Pattern-leri

#### Column elave etmek (safe)

```php
// Yeni nullable column elave etmek SAFE-dir
$table->string('phone')->nullable()->after('email');
// MySQL: ALGORITHM=INSTANT (8.0+), aninda!
```

#### Column rename etmek (safe usul)

```php
// Addim 1: Yeni column yarat
public function up()
{
    Schema::table('users', function (Blueprint $table) {
        $table->string('full_name')->nullable()->after('name');
    });
}

// Addim 2: Data-ni kopyala (background job ile)
// UPDATE users SET full_name = name WHERE full_name IS NULL;

// Addim 3: Application-u yeni column istifade etmeye deyis (deploy)

// Addim 4: Kohne column-u sil (ayri migration)
public function up()
{
    Schema::table('users', function (Blueprint $table) {
        $table->dropColumn('name');
    });
}
```

#### NOT NULL elave etmek (safe usul)

```php
// Addim 1: Default deyer ile nullable column
$table->string('status')->nullable()->default('pending');

// Addim 2: Movcud NULL row-lari yenile (batch ile)
DB::table('orders')->whereNull('status')->update(['status' => 'pending']);

// Addim 3: NOT NULL constraint elave et
$table->string('status')->default('pending')->nullable(false)->change();
```

#### Boyuk table-da index (safe usul)

```sql
-- MySQL: ALGORITHM=INPLACE, LOCK=NONE (online DDL)
ALTER TABLE orders ADD INDEX idx_status (status), ALGORITHM=INPLACE, LOCK=NONE;

-- PostgreSQL: CONCURRENTLY (table lock etmir)
CREATE INDEX CONCURRENTLY idx_orders_status ON orders (status);
```

```php
// Laravel migration-da raw SQL istifade et
public function up()
{
    DB::statement('CREATE INDEX CONCURRENTLY idx_orders_status ON orders (status)');
}
```

---

## Batch Data Migration

```php
// YANLIS: Butun table-i bir defe yenile (memory + lock problemi)
DB::table('orders')->update(['currency' => 'USD']);

// DOGRU: Batch ile
DB::table('orders')
    ->whereNull('currency')
    ->orderBy('id')
    ->chunk(1000, function ($orders) {
        $ids = $orders->pluck('id');
        DB::table('orders')
            ->whereIn('id', $ids)
            ->update(['currency' => 'USD']);
    });

// Daha yaxsi: LazyById (memory-efficient)
DB::table('orders')
    ->whereNull('currency')
    ->lazyById(1000)
    ->each(function ($order) {
        DB::table('orders')
            ->where('id', $order->id)
            ->update(['currency' => 'USD']);
    });
```

---

## Migration Best Practices

### 1. Her migration geri alinabilmeli (reversible)

```php
public function down(): void
{
    Schema::table('orders', function (Blueprint $table) {
        $table->dropColumn('phone');
    });
}
```

### 2. Migration-da Eloquent model istifade etme

```php
// YANLIS: Model deyiserse migration qirilir
public function up()
{
    User::all()->each(function ($user) {
        $user->update(['status' => 'active']);
    });
}

// DOGRU: Raw query istifade et
public function up()
{
    DB::table('users')->update(['status' => 'active']);
}
```

### 3. Foreign Key conventions

```php
// Laravel conventions
$table->foreignId('user_id')->constrained();
// = BIGINT UNSIGNED + FOREIGN KEY REFERENCES users(id)

// Custom
$table->foreignId('author_id')->constrained('users')->onDelete('cascade');

// Index olmadan FK (performance problemi!)
// foreignId() avtomatik index yaradir, amma manual FK-da unutma:
$table->unsignedBigInteger('user_id');
$table->foreign('user_id')->references('id')->on('users');
$table->index('user_id'); // UNUTMA!
```

### 4. Squashing (Migration-lari birlesdirmek)

```bash
# Coxlu kohne migration-i tek SQL file-a cevirme
php artisan schema:dump
php artisan schema:dump --prune  # Kohne migration file-larini sil
# database/schema/mysql-schema.sql yaradilir
```

---

## Interview suallari

**Q: Production-da zero-downtime migration nece edersin?**
A: 1) Yalniz additive (elave edici) deyisiklikler et (yeni column, yeni table). 2) Destructive deyisiklikleri merhele-merhele et (yeni column yarat -> data kopyala -> app deyis -> kohne column sil). 3) Boyuk table-larda online DDL (ALGORITHM=INPLACE) ve ya pt-online-schema-change istifade et. 4) Index-leri CONCURRENTLY yarat.

**Q: Migration-da nese sehv olsa ne edersin?**
A: 1) `migrate:rollback` ile geri al (eger down() yazilibsa). 2) Down() yoxdursa ve ya islemir, manual fix et. 3) Her zaman production-dan evvel staging-de test et. 4) Boyuk deyisiklikleri bir nece kicik migration-a bol.

**Q: Foreign key-ler performance-a nece tesir edir?**
A: INSERT/UPDATE/DELETE zamani FK constraint yoxlanilir (parent table-da lookup). Boyuk batch INSERT-lerde yavas ola biler. Bezi hallarda FK-ni temporary disable etmek olar: `SET FOREIGN_KEY_CHECKS = 0;` (yalniz migration/import zamani!).
