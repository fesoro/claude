# Temporal Data & Slowly Changing Dimensions (Middle)

## Temporal Data Nedir?

Data-nin **zamana gore versiyalarini** saxlama. Yeni "indi nedir?" deyil, "nezaman ne idi?" sualina cavab vermek.

**Misal:** Mehsulun qiymeti deyisir. Kecmis sifarislerde hansi qiymete satildigi bilinmelidir.

## Temporal Table Noverileri

### 1. System-Versioned Temporal Tables

Database avtomatik olaraq row-larin versiyalarini saxlayir.

```sql
-- MariaDB / SQL Server (native destekliyir)
CREATE TABLE products (
    id BIGINT PRIMARY KEY,
    name VARCHAR(255),
    price DECIMAL(10,2),
    valid_from DATETIME(6) GENERATED ALWAYS AS ROW START,
    valid_to DATETIME(6) GENERATED ALWAYS AS ROW END,
    PERIOD FOR SYSTEM_TIME (valid_from, valid_to)
) WITH SYSTEM VERSIONING;

-- Kecmis versiyalari sorgula
SELECT * FROM products FOR SYSTEM_TIME AS OF '2024-01-01 00:00:00';
SELECT * FROM products FOR SYSTEM_TIME BETWEEN '2024-01-01' AND '2024-06-01';
```

### 2. Application-Managed Temporal (MySQL/PostgreSQL)

Ozumuz idare edirik.

```sql
-- Price history table
CREATE TABLE product_prices (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    product_id BIGINT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    effective_from DATETIME NOT NULL,
    effective_to DATETIME DEFAULT '9999-12-31 23:59:59',
    created_by BIGINT,
    FOREIGN KEY (product_id) REFERENCES products(id),
    INDEX idx_product_effective (product_id, effective_from, effective_to)
);

-- Hazirki qiymeti tap
SELECT price FROM product_prices
WHERE product_id = 1
  AND NOW() BETWEEN effective_from AND effective_to;

-- Belli bir tarixde qiymeti tap
SELECT price FROM product_prices
WHERE product_id = 1
  AND '2024-03-15' BETWEEN effective_from AND effective_to;

-- Yeni qiymeti elave et (kohnesini bagla)
START TRANSACTION;
UPDATE product_prices
SET effective_to = NOW()
WHERE product_id = 1 AND effective_to = '9999-12-31 23:59:59';

INSERT INTO product_prices (product_id, price, effective_from)
VALUES (1, 29.99, NOW());
COMMIT;
```

### Laravel ile Temporal Data

```php
// Migration
Schema::create('product_prices', function (Blueprint $table) {
    $table->id();
    $table->foreignId('product_id')->constrained();
    $table->decimal('price', 10, 2);
    $table->dateTime('effective_from');
    $table->dateTime('effective_to')->default('9999-12-31 23:59:59');
    $table->foreignId('created_by')->nullable();
    $table->timestamps();

    $table->index(['product_id', 'effective_from', 'effective_to']);
});

// Model
class ProductPrice extends Model
{
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('effective_from', '<=', now())
                     ->where('effective_to', '>=', now());
    }

    public function scopeAsOf(Builder $query, string $date): Builder
    {
        return $query->where('effective_from', '<=', $date)
                     ->where('effective_to', '>=', $date);
    }
}

// Istifade
$currentPrice = ProductPrice::where('product_id', 1)->current()->first();
$oldPrice = ProductPrice::where('product_id', 1)->asOf('2024-01-15')->first();
```

## Slowly Changing Dimensions (SCD)

Data warehouse / analytics-de dimension table-larinin zamana gore deyisme noverileri.

### SCD Type 1 - Overwrite (Uzerin Yaz)

Kecmis saxlanmaz, yeni deyerle evez olunur.

```sql
-- Sadece UPDATE edirik
UPDATE customers
SET address = 'Yeni adres',
    city = 'Baku'
WHERE id = 1;
```

**Ne vaxt:** Kecmis melumat vacib deyilse (meselen: telefon nomresi duzeltmesi).

### SCD Type 2 - Full History (Tam Tarixce)

Her deyisiklik ucun yeni row yaranir. En cox istifade olunan novdur.

```sql
CREATE TABLE customer_dim (
    surrogate_key BIGINT AUTO_INCREMENT PRIMARY KEY,
    customer_id BIGINT NOT NULL,
    name VARCHAR(255),
    address VARCHAR(500),
    city VARCHAR(100),
    effective_from DATE NOT NULL,
    effective_to DATE DEFAULT '9999-12-31',
    is_current BOOLEAN DEFAULT TRUE,
    version INT DEFAULT 1
);

-- Yeni versiya elave et
START TRANSACTION;

-- Kohne row-u bagla
UPDATE customer_dim
SET effective_to = CURDATE(),
    is_current = FALSE
WHERE customer_id = 42 AND is_current = TRUE;

-- Yeni row yarat
INSERT INTO customer_dim (customer_id, name, address, city, effective_from, version)
SELECT customer_id, name, '221B Baker St', 'London', CURDATE(),
       version + 1
FROM customer_dim
WHERE customer_id = 42
ORDER BY version DESC LIMIT 1;

COMMIT;

-- Hazirki melumat
SELECT * FROM customer_dim WHERE customer_id = 42 AND is_current = TRUE;

-- Kecmis melumat (2024-01-15 tarixinde)
SELECT * FROM customer_dim
WHERE customer_id = 42
  AND '2024-01-15' BETWEEN effective_from AND effective_to;
```

### SCD Type 3 - Previous Value (Evvelki Deyer)

Yalniz bir evvelki deyeri saxlayir.

```sql
CREATE TABLE customers (
    id BIGINT PRIMARY KEY,
    name VARCHAR(255),
    current_city VARCHAR(100),
    previous_city VARCHAR(100),
    city_changed_at DATE
);

-- Deyisiklik
UPDATE customers
SET previous_city = current_city,
    current_city = 'Istanbul',
    city_changed_at = CURDATE()
WHERE id = 42;
```

**Ne vaxt:** Yalniz son deyisiklik vacibdirse.

### SCD Muqayise

| Xususiyyet | Type 1 | Type 2 | Type 3 |
|------------|--------|--------|--------|
| **Tarixce** | Yoxdur | Tam | Yalniz evvelki |
| **Disk istifadesi** | Az | Cox | Orta |
| **Complexity** | Asagi | Yuksek | Orta |
| **Query performance** | Yaxsi | is_current lazim | Yaxsi |
| **Use case** | Duzeltmeler | Audit, analitika | Muqayise |

## Audit Trail (Iz Surmek)

Her deyisikliyi kim, ne vaxt, ne deyisib kimi qeyd etmek.

```sql
CREATE TABLE audit_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    auditable_type VARCHAR(100) NOT NULL,
    auditable_id BIGINT NOT NULL,
    user_id BIGINT,
    action ENUM('created', 'updated', 'deleted') NOT NULL,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_auditable (auditable_type, auditable_id),
    INDEX idx_user (user_id),
    INDEX idx_created (created_at)
);
```

```php
// Laravel Observer ile avtomatik audit
class AuditableObserver
{
    public function created(Model $model): void
    {
        $this->log($model, 'created', [], $model->getAttributes());
    }

    public function updated(Model $model): void
    {
        $dirty = $model->getDirty();
        $original = collect($dirty)->mapWithKeys(
            fn ($v, $k) => [$k => $model->getOriginal($k)]
        )->toArray();

        $this->log($model, 'updated', $original, $dirty);
    }

    public function deleted(Model $model): void
    {
        $this->log($model, 'deleted', $model->getAttributes(), []);
    }

    private function log(Model $model, string $action, array $old, array $new): void
    {
        DB::table('audit_log')->insert([
            'auditable_type' => get_class($model),
            'auditable_id' => $model->getKey(),
            'user_id' => auth()->id(),
            'action' => $action,
            'old_values' => json_encode($old),
            'new_values' => json_encode($new),
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);
    }
}

// AppServiceProvider-da register et
Order::observe(AuditableObserver::class);
```

## Snapshot Pattern

Mueyyyen anlarda butov veziyyeti saxlama.

```sql
-- Ayliq balans snapshotu
CREATE TABLE account_snapshots (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    account_id BIGINT NOT NULL,
    balance DECIMAL(15,2) NOT NULL,
    snapshot_date DATE NOT NULL,
    metadata JSON,
    UNIQUE KEY uq_account_date (account_id, snapshot_date)
);

-- Her ayin sonunda snapshot al
INSERT INTO account_snapshots (account_id, balance, snapshot_date)
SELECT id, balance, LAST_DAY(CURDATE())
FROM accounts;

-- Belli bir tarixde balans?
SELECT balance FROM account_snapshots
WHERE account_id = 1 AND snapshot_date = '2024-06-30';
```

## Interview Suallari

1. **Temporal data nedir ve niye lazimdir?**
   - Data-nin zamana gore versiyalarini saxlama. "Bu mehsulun 3 ay evvelki qiymeti ne idi?" sualina cavab verir. Audit, compliance, ve analitika ucun vacibdir.

2. **SCD Type 2 nece isleyir?**
   - Her deyisiklikde kohne row baglenir (effective_to, is_current=false), yeni row yaranir. Tam tarixce saxlanilir.

3. **Audit trail ile temporal table ferqi?**
   - Audit trail: Kim, ne vaxt, ne deyisdi - log meqsedli. Temporal table: Data-nin her hansı bir andaki veziyyetini sorgulamaq ucun - query meqsedli.

4. **effective_to ucun '9999-12-31' niye istifade olunur?**
   - NULL yerine sentinel deyer. BETWEEN query-lerinde NULL-i handle etmek cetindir, '9999-12-31' isə range query-leri sadeleşdirir ve index-i effektiv istifade edir.

5. **Temporal data performance problemleri?**
   - Coxalan row-lar, boyuk table-lar. Helli: partitioning (tarixe gore), arxivleme, covering index-ler.
