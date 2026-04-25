# Stored Procedures, Triggers & Views (Middle)

## Stored Procedures

Database server-de saxlanilan ve icra olunan SQL proqramlaridir.

### Yaratma (MySQL)

```sql
DELIMITER //

CREATE PROCEDURE transfer_money(
    IN from_account INT,
    IN to_account INT,
    IN amount DECIMAL(10,2),
    OUT success BOOLEAN
)
BEGIN
    DECLARE from_balance DECIMAL(10,2);
    
    -- Error handler
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET success = FALSE;
    END;
    
    START TRANSACTION;
    
    -- Balance yoxla
    SELECT balance INTO from_balance 
    FROM accounts WHERE id = from_account FOR UPDATE;
    
    IF from_balance < amount THEN
        SET success = FALSE;
        ROLLBACK;
    ELSE
        UPDATE accounts SET balance = balance - amount WHERE id = from_account;
        UPDATE accounts SET balance = balance + amount WHERE id = to_account;
        
        INSERT INTO transactions (from_id, to_id, amount, created_at)
        VALUES (from_account, to_account, amount, NOW());
        
        COMMIT;
        SET success = TRUE;
    END IF;
END //

DELIMITER ;

-- Cagirma
CALL transfer_money(1, 2, 500.00, @result);
SELECT @result;
```

### PHP-den cagirma

```php
// PDO
$stmt = $pdo->prepare("CALL transfer_money(?, ?, ?, @result)");
$stmt->execute([1, 2, 500.00]);
$result = $pdo->query("SELECT @result AS success")->fetch();

// Laravel
DB::statement('CALL transfer_money(?, ?, ?, @result)', [1, 2, 500.00]);
$result = DB::select('SELECT @result AS success');
```

### Ustunlukler

- **Performance:** Compile olunmus plan cache-lenir, network round-trip azalir
- **Security:** User-lere table-a birbaşa erisim yerine yalniz procedure icaze vermek olar
- **Reusability:** Muxtelif application-lar eyni logic-i istifade ede biler

### Menfi terefler

- **Debugging cetindir** - PHP-deki kimi step-by-step debug yoxdur
- **Version control cetindir** - migration ile idare etmek lazimdir
- **Business logic bolunur** - yarimsi PHP-de, yarimsi DB-de
- **Portability** - MySQL procedure PostgreSQL-de islemir
- **Testing cetindir** - unit test yazmaq catindir

### Ne vaxt istifade etmeli?

- **Heh:** Complex data transformation, batch operations, security-critical logic
- **Yox:** Sade CRUD, business logic, validation

---

## Triggers

Mueyyen hadise (INSERT, UPDATE, DELETE) bas verdikde avtomatik icra olunan SQL kodudur.

### Yaratma

```sql
-- Misal 1: Audit log - her update-de kohne deyeri saxla
CREATE TRIGGER orders_audit_update
BEFORE UPDATE ON orders
FOR EACH ROW
BEGIN
    INSERT INTO orders_audit (
        order_id, 
        old_status, 
        new_status, 
        changed_at
    ) VALUES (
        OLD.id, 
        OLD.status, 
        NEW.status, 
        NOW()
    );
END;

-- Misal 2: Stock azaltma - order yarananda
CREATE TRIGGER decrease_stock_after_order
AFTER INSERT ON order_items
FOR EACH ROW
BEGIN
    UPDATE products 
    SET stock = stock - NEW.quantity 
    WHERE id = NEW.product_id;
    
    -- Low stock warning
    IF (SELECT stock FROM products WHERE id = NEW.product_id) < 10 THEN
        INSERT INTO notifications (message, created_at)
        VALUES (CONCAT('Low stock: product ', NEW.product_id), NOW());
    END IF;
END;

-- Misal 3: Soft delete yerine cascade
CREATE TRIGGER cascade_soft_delete
AFTER UPDATE ON users
FOR EACH ROW
BEGIN
    IF NEW.deleted_at IS NOT NULL AND OLD.deleted_at IS NULL THEN
        UPDATE orders SET deleted_at = NOW() WHERE user_id = NEW.id AND deleted_at IS NULL;
        UPDATE addresses SET deleted_at = NOW() WHERE user_id = NEW.id AND deleted_at IS NULL;
    END IF;
END;
```

### Trigger zamanlari

| Zaman | Hadise | Istifade |
|-------|--------|----------|
| BEFORE INSERT | Row insert olunmazdan evvel | Validation, default deyer |
| AFTER INSERT | Row insert olunandan sonra | Audit log, notification |
| BEFORE UPDATE | Row update olunmazdan evvel | Deyisikliyi yoxla/deyis |
| AFTER UPDATE | Row update olunandan sonra | Audit log, cascade |
| BEFORE DELETE | Row silinmezden evvel | Cascade yoxlamasi |
| AFTER DELETE | Row silindikden sonra | Cleanup, log |

### Laravel Migration ile Trigger

```php
public function up()
{
    DB::unprepared('
        CREATE TRIGGER orders_audit_update
        BEFORE UPDATE ON orders
        FOR EACH ROW
        BEGIN
            INSERT INTO orders_audit (order_id, old_status, new_status, changed_at)
            VALUES (OLD.id, OLD.status, NEW.status, NOW());
        END
    ');
}

public function down()
{
    DB::unprepared('DROP TRIGGER IF EXISTS orders_audit_update');
}
```

### Trigger vs Application Logic (Laravel Observer)

```php
// Laravel Observer (application seviyyesinde)
class OrderObserver
{
    public function updating(Order $order)
    {
        if ($order->isDirty('status')) {
            OrderAudit::create([
                'order_id' => $order->id,
                'old_status' => $order->getOriginal('status'),
                'new_status' => $order->status,
            ]);
        }
    }
}

// Ferq:
// Observer: Yalniz Eloquent uzerinden deyisiklik olduqda isleyir
//           DB::table('orders')->update(...) zamani ISLEMIR!
// Trigger:  Butun deyisikliklerde isleyir (Eloquent, raw SQL, diger app-lar)
```

### Trigger problemleri

- **Gizli logic:** Bir INSERT 10 trigger tetikleye biler, debug cetindir
- **Performance:** Her write emeliyyatina overhead elave edir
- **Cascade:** Trigger baska trigger-i tetikleye biler (trigger chain)
- **Testing:** Unit test-de trigger-i mock etmek olmur

---

## Views

Virtual table - saxlanilmis SELECT query-dir. Data saxlamır, her sorguda yeniden icra olunur.

### Yaratma

```sql
-- Sade view
CREATE VIEW active_orders AS
SELECT 
    o.id,
    o.created_at,
    o.total_amount,
    u.name AS customer_name,
    u.email AS customer_email
FROM orders o
JOIN users u ON u.id = o.user_id
WHERE o.status != 'cancelled';

-- Istifade (adi table kimi)
SELECT * FROM active_orders WHERE total_amount > 100;
```

### Complex View

```sql
CREATE VIEW user_order_summary AS
SELECT 
    u.id AS user_id,
    u.name,
    u.email,
    COUNT(o.id) AS total_orders,
    COALESCE(SUM(o.total_amount), 0) AS total_spent,
    COALESCE(AVG(o.total_amount), 0) AS avg_order_value,
    MAX(o.created_at) AS last_order_date
FROM users u
LEFT JOIN orders o ON o.user_id = u.id
GROUP BY u.id, u.name, u.email;

-- Istifade
SELECT * FROM user_order_summary WHERE total_orders > 10 ORDER BY total_spent DESC;
```

### Updatable Views

Bezi sade view-lara INSERT/UPDATE/DELETE etmek olar:

```sql
CREATE VIEW pending_orders AS
SELECT id, user_id, total_amount, created_at
FROM orders
WHERE status = 'pending';

-- Bu isleyir (sade view, tek table, aggregate yoxdur)
UPDATE pending_orders SET total_amount = 150 WHERE id = 1;
```

### WITH CHECK OPTION

View-un WHERE sertini pozan deyisikliklerin qarsisini alir:

```sql
CREATE VIEW pending_orders AS
SELECT * FROM orders WHERE status = 'pending'
WITH CHECK OPTION;

-- Bu FAIL edir (status artiq 'pending' olmayacaq)
UPDATE pending_orders SET status = 'shipped' WHERE id = 1;
-- ERROR: CHECK OPTION failed
```

### Laravel-de View

```php
// Migration ile yaratma
public function up()
{
    DB::statement("
        CREATE VIEW active_orders AS
        SELECT o.*, u.name AS customer_name
        FROM orders o
        JOIN users u ON u.id = o.user_id
        WHERE o.status != 'cancelled'
    ");
}

// Model yaradib istifade etmek (read-only)
class ActiveOrder extends Model
{
    protected $table = 'active_orders';
    
    // View-da insert/update islemir
    public $timestamps = false;
    protected $guarded = [];
}

// Istifade
$orders = ActiveOrder::where('total_amount', '>', 100)->get();
```

### Materialized View (PostgreSQL)

Neticeni fiziki olaraq saxlayir. Suretli oxuma, amma kohne data ola biler.

```sql
-- PostgreSQL
CREATE MATERIALIZED VIEW monthly_revenue AS
SELECT 
    DATE_TRUNC('month', created_at) AS month,
    SUM(total_amount) AS revenue,
    COUNT(*) AS order_count
FROM orders
GROUP BY DATE_TRUNC('month', created_at)
ORDER BY month;

-- Refresh (data yenile)
REFRESH MATERIALIZED VIEW monthly_revenue;
REFRESH MATERIALIZED VIEW CONCURRENTLY monthly_revenue; -- Read-lari bloklamadan
```

---

## Interview suallari

**Q: Stored procedure vs application code - hansi daha yaxsidir?**
A: Cogu hallda application code daha yaxsidir: test, debug, version control, deploy asandir. Stored procedure yalniz: 1) network round-trip kritikdirsa (batch processing), 2) muxtelif application-lar eyni logic-i istifade etmelidirsə, 3) security constraint varsa (table-a birbaşa erisim olmamali) istifade olunmalidir.

**Q: Trigger ne vaxt istifade etmeli?**
A: Audit log, data integrity (application bypassedile bilmeyecek rule-lar), cross-application consistency. Amma mumkun qeder application seviyyesinde hell et (Observer, Event), cunki debug ve test daha asandir.

**Q: View vs Materialized View?**
A: View her sorquda yeniden icra olunur (hemishe fresh data). Materialized View neticeni cache-leyir (suretli oxuma, amma stale ola biler - REFRESH lazimdir). Reporting/dashboard ucun materialized view idealdır.
