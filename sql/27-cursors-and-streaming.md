# Cursor Operations & Streaming Large Datasets

> **Seviyye:** Intermediate ⭐⭐

## Problem: Boyuk Data Setleri

```php
// YANLIS: 10 milyon row-u RAM-a yukle
$orders = Order::all(); // OutOfMemoryError!

// YANLIS: Hele de boyuk resultset
$orders = DB::select('SELECT * FROM orders'); // Butun netice RAM-da
```

10M row * ~1KB = ~10GB RAM lazimdir. Bu mumkun deyil!

## Database Cursor

Cursor - neticeni **bir-bir** (ve ya batch ile) oxuyan pointer-dir. Butun neticeni RAM-a yuklemir.

### SQL Cursor

```sql
-- MySQL Cursor (stored procedure icerisinde)
DELIMITER //
CREATE PROCEDURE process_old_orders()
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_order_id BIGINT;
    DECLARE v_total DECIMAL(12,2);

    -- Cursor elan et
    DECLARE order_cursor CURSOR FOR
        SELECT id, total FROM orders WHERE created_at < '2023-01-01';

    -- Not found handler
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

    OPEN order_cursor;

    read_loop: LOOP
        FETCH order_cursor INTO v_order_id, v_total;
        IF done THEN
            LEAVE read_loop;
        END IF;

        -- Her row ucun emeliyyat
        INSERT INTO archived_orders (order_id, total, archived_at)
        VALUES (v_order_id, v_total, NOW());

        DELETE FROM orders WHERE id = v_order_id;
    END LOOP;

    CLOSE order_cursor;
END //
DELIMITER ;

CALL process_old_orders();
```

```sql
-- PostgreSQL Cursor
BEGIN;

DECLARE order_cursor CURSOR FOR
    SELECT id, total FROM orders WHERE created_at < '2023-01-01';

-- 100 row al
FETCH 100 FROM order_cursor;

-- Novbeti 100
FETCH 100 FROM order_cursor;

CLOSE order_cursor;
COMMIT;
```

## PHP/Laravel ile Boyuk Data

### 1. chunk() - Batch Emali

```php
// 1000 row-luq batch-lerle isle
Order::where('status', 'pending')
    ->chunk(1000, function ($orders) {
        foreach ($orders as $order) {
            $order->update(['status' => 'processing']);
        }
    });
// Her batch ayri query ile getirilir: SELECT ... LIMIT 1000 OFFSET N
```

**Problem:** `chunk()` `OFFSET` istifade edir - boyuk offset-lerde yavasdir.

### 2. chunkById() - ID-ye gore Batch (Tovsiye olunan)

```php
// OFFSET yerine WHERE id > last_id istifade edir
Order::where('status', 'pending')
    ->chunkById(1000, function ($orders) {
        foreach ($orders as $order) {
            $order->update(['status' => 'processing']);
        }
    });
// Query: SELECT ... WHERE id > 1000 ORDER BY id LIMIT 1000
// OFFSET-den qat-qat suretlidir!
```

### 3. lazy() ve lazyById() - Cursor (RAM Qenayeti)

```php
// lazy() - PHP Generator istifade edir
Order::where('status', 'pending')
    ->lazy(1000)
    ->each(function ($order) {
        // Her 1000 row-dan sonra novbeti batch yuklenilir
        ProcessOrder::dispatch($order);
    });

// lazyById() - chunk ile eyni amma generator kimi
Order::where('created_at', '<', now()->subYear())
    ->lazyById(1000)
    ->each(function ($order) {
        $order->archive();
    });
```

### 4. cursor() - Real Database Cursor

```php
// PDO cursor - bir dene row RAM-da saxlayir
foreach (Order::where('status', 'pending')->cursor() as $order) {
    ProcessOrder::dispatch($order);
}
// DİQQET: Butun result set server-de bufferlenir, connection uzun muddete aciq qalir
```

### Muqayise

| Metod | RAM | Speed | Nece isleyir |
|-------|-----|-------|-------------|
| `all()` / `get()` | Butun data | Suretli (kicik data ucun) | Tek query |
| `chunk()` | Batch olcusu | OFFSET ile yavas | `LIMIT X OFFSET Y` |
| `chunkById()` | Batch olcusu | Suretli | `WHERE id > X LIMIT Y` |
| `lazy()` | Batch olcusu | OFFSET ile yavas | Generator + chunk |
| `lazyById()` | Batch olcusu | Suretli | Generator + chunkById |
| `cursor()` | 1 row | Database cursor | PDO cursor |

> **Tovsiye:** Demek olar ki her zaman `chunkById()` ve ya `lazyById()` istifade edin.

## Batch Operations

### Bulk Insert

```php
// YANLIS: Dongu ile tek-tek insert
foreach ($items as $item) {
    Order::create($item);  // 10000 query!
}

// DOGRU: Bulk insert
$chunks = array_chunk($items, 1000);
foreach ($chunks as $chunk) {
    Order::insert($chunk);  // 10 query (10000/1000)
}

// Upsert (INSERT ... ON DUPLICATE KEY UPDATE)
Order::upsert(
    $data,
    ['order_number'],          // Unique key
    ['status', 'updated_at']   // Update olunacaq column-lar
);
```

### Bulk Update

```php
// YANLIS: Tek-tek update
foreach ($orderIds as $id) {
    Order::where('id', $id)->update(['status' => 'shipped']);
}

// DOGRU: Bulk update
Order::whereIn('id', $orderIds)->update(['status' => 'shipped']);

// Conditional bulk update
DB::statement("
    UPDATE orders
    SET status = CASE
        WHEN paid_at IS NOT NULL AND shipped_at IS NOT NULL THEN 'delivered'
        WHEN paid_at IS NOT NULL THEN 'paid'
        ELSE 'pending'
    END
    WHERE id IN (?)
", [implode(',', $orderIds)]);
```

### Bulk Delete

```php
// Boyuk table-dan silmek - kicik batch-lerle
do {
    $deleted = Order::where('created_at', '<', '2022-01-01')
        ->limit(1000)
        ->delete();
} while ($deleted > 0);
// Niye batch? Boyuk DELETE lock tutur ve replication lag yaradir
```

## Streaming Export

```php
// CSV export - streaming ile (RAM-a yuklemeden)
class OrderExportController
{
    public function export()
    {
        return response()->stream(function () {
            $handle = fopen('php://output', 'w');

            // Header
            fputcsv($handle, ['ID', 'User', 'Total', 'Status', 'Date']);

            // Data - batch ile
            Order::with('user')
                ->lazyById(1000)
                ->each(function ($order) use ($handle) {
                    fputcsv($handle, [
                        $order->id,
                        $order->user->name,
                        $order->total,
                        $order->status,
                        $order->created_at->format('Y-m-d'),
                    ]);
                });

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="orders.csv"',
        ]);
    }
}
```

## Interview Suallari

1. **chunk() ile chunkById() ferqi?**
   - `chunk()` OFFSET istifade edir - boyuk data-da yavasdir. `chunkById()` `WHERE id > X` istifade edir - index ile suretlidir.

2. **10 milyon row-u nece export edersiniz?**
   - `lazyById()` ile streaming response. RAM-a yuklemeden, batch-batch oxuyub write edirik.

3. **cursor() ne vaxt istifade olunur?**
   - Tek-tek row emali lazim olanda ve RAM minimal olmalidir. Amma connection uzun aciq qalir, connection pool-da problem yarada biler.

4. **Boyuk DELETE niye batch ile edilmelidir?**
   - Tek boyuk DELETE: uzun lock, replication lag, transaction log sismesi. Batch ile: kicik lock-lar, replication izleye bilir.

5. **Bulk insert-de niye chunk edirsiniz?**
   - MySQL-in `max_allowed_packet` limiti var. Tek query-de 100K row gondermek ugursuz ola biler. 1000-lik batch optimal.
