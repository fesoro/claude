# Zero-Downtime Database Migration (Senior)

## Ssenari

Production-da 50 milyonluq `orders` cədvəlinə yeni sütun əlavə etmək və mövcud sütunu rename etmək lazımdır. Deploy zamanı aplikasiya dayanmamalıdır.

---

## Problem

```
Adi yanaşma (DANGEROUS!):
  ALTER TABLE orders ADD COLUMN discount_amount INT;
  ALTER TABLE orders RENAME COLUMN amount TO total_amount;
  Deploy new code

Nə baş verir:
  ALTER TABLE → bütün cədvəli lock edir
  50M row → saatlarla lock!
  Rename sonrası köhnə kod: "amount column not found" ❌
  
Downtime: 2-4 saat!
```

---

## Expand/Contract Pattern

```
Expand (Genişlət):
  1. Yeni sütun əlavə et (nullable, default var)
  2. Köhnə kod işləməyə davam edir
  3. Yeni kod həm köhnəni, həm yenini yazır

Contract (Daralt):
  4. Köhnə kodu sil
  5. Köhnə sütunu sil (artıq istifadə edilmir)

Mərhələlər:
  ┌──────────────────────────────────────────────────────────────┐
  │ DB    │ amount (old) │ discount_amount │ total_amount (new) │
  ├──────────────────────────────────────────────────────────────┤
  │ Ph 1  │ dolu         │ yoxdur          │ yoxdur              │
  │ Ph 2  │ dolu         │ əlavə edildi    │ əlavə edildi (null) │
  │ Ph 3  │ dolu         │ dolu            │ backfill olundu     │
  │ Ph 4  │ köhnə, istf. │ dolu            │ dolu                │
  │ Ph 5  │ silinib      │ dolu            │ dolu                │
  └──────────────────────────────────────────────────────────────┘
```

---

## Addım-addım İmplementasiya

### Mərhələ 1: Yeni sütunları əlavə et (non-blocking)

*Bu kod köhnə məlumatları qoruyaraq yeni nullable sütunları cədvələ əlavə edən ilk migration mərhələsini göstərir:*

```php
// Migration 1: Schema dəyişikliyi
class AddDiscountAndTotalAmountToOrders extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // nullable — köhnə data üçün
            $table->integer('discount_amount')->nullable()->after('amount');
            $table->integer('total_amount')->nullable()->after('discount_amount');
        });
        // ALTER TABLE ADD COLUMN nullable — fast! (metadata only change in MySQL 8+)
    }
}
```

### Mərhələ 2: Yeni kod həm köhnəni, həm yenini yazır

*Bu kod həm köhnə, həm də yeni sütunlara eyni anda yazan və oxuyarkən yeni sütuna üstünlük verən dual-write model-i göstərir:*

```php
// Deploy: Dual-write code
class Order extends Model
{
    // Write: həm köhnəyə, həm yeniyə yaz
    public function setAmountAttribute(int $value): void
    {
        $this->attributes['amount']       = $value;          // köhnə
        $this->attributes['total_amount'] = $value;          // yeni
    }
    
    // Write discount
    public function setDiscountAmountAttribute(int $value): void
    {
        $this->attributes['discount_amount'] = $value;
    }
    
    // Read: yenisi varsa onu istifadə et, yoxsa köhnəni
    public function getTotalAmountAttribute(): int
    {
        return $this->attributes['total_amount'] 
            ?? $this->attributes['amount'] 
            ?? 0;
    }
}
```

### Mərhələ 3: Backfill (köhnə data-nı yeniyə kopyala)

*Bu kod köhnə sütundakı məlumatları chunk-larla yeni sütuna kopyalayan, DB-yə nəfəs verən backfill job-unu göstərir:*

```php
class BackfillOrderTotalAmountJob implements ShouldQueue
{
    public int $tries    = 3;
    public int $timeout  = 3600;  // 1 saat
    
    public function handle(): void
    {
        // Chunk ilə — lock-suz batch update
        $lastId = 0;
        $batchSize = 1000;
        
        do {
            $rows = DB::table('orders')
                ->where('id', '>', $lastId)
                ->whereNull('total_amount')
                ->orderBy('id')
                ->limit($batchSize)
                ->get(['id', 'amount']);
            
            if ($rows->isEmpty()) break;
            
            $updates = $rows->map(fn($row) => [
                'id'           => $row->id,
                'total_amount' => $row->amount,
                'discount_amount' => 0,
            ])->all();
            
            // Batch upsert
            DB::table('orders')->upsert(
                $updates,
                ['id'],
                ['total_amount', 'discount_amount']
            );
            
            $lastId = $rows->last()->id;
            
            Log::info("Backfill progress", [
                'last_id'    => $lastId,
                'batch_size' => $rows->count(),
            ]);
            
            // DB-yə nəfəs ver
            usleep(100 * 1000);  // 100ms
            
        } while ($rows->count() === $batchSize);
        
        Log::info("Backfill completed");
    }
}
```

### Mərhələ 4: Köhnə sütunu oxumadan çıxar

*Bu kod artıq yalnız yeni sütundan oxuyan, amma hərəkətsizlik üçün köhnə sütuna da yazmağa davam edən model versiyasını göstərir:*

```php
// Deploy: Read only from new column
class Order extends Model
{
    // Artıq yalnız yeni sütundan oxu
    public function getTotalAmountAttribute(): int
    {
        return $this->attributes['total_amount'] ?? 0;
    }
    
    // Dual-write hələ davam edir (safety net)
    public function setAmountAttribute(int $value): void
    {
        $this->attributes['amount']       = $value;   // hələ yaz
        $this->attributes['total_amount'] = $value;
    }
}
```

### Mərhələ 5: Köhnə sütunu sil

*Bu kod heç kimin artıq oxumadığı köhnə sütunu silən son contract migration-ını göstərir:*

```php
// Migration 2: Köhnə sütunu sil (artıq heç kim oxumur)
class RemoveAmountColumnFromOrders extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('amount');
        });
    }
}
```

---

## Online Schema Change Alətləri

*Bu kod gh-ost və pt-online-schema-change alətlərindən istifadə edərək böyük cədvəllərdə online schema dəyişikliyi aparmağı göstərir:*

```bash
# gh-ost (GitHub Online Schema Change)
# Production-da yüklü cədvəllərdə DDL
gh-ost \
  --host=db-master \
  --database=myapp \
  --table=orders \
  --alter="ADD COLUMN total_amount INT NULL, ADD INDEX idx_total (total_amount)" \
  --execute \
  --max-load=Threads_running=25 \
  --critical-load=Threads_running=1000 \
  --chunk-size=1000 \
  --throttle-control-replicas=replica1,replica2

# pt-online-schema-change (Percona Toolkit)
pt-online-schema-change \
  --alter "ADD COLUMN total_amount INT NULL" \
  D=myapp,t=orders \
  --execute \
  --max-lag=1s \
  --chunk-size=1000

# Necə işləyir:
# 1. Shadow table yaradır (_orders_ghos)
# 2. Triggers: INSERT/UPDATE/DELETE → shadow table-a da tətbiq edir
# 3. Köhnə data-nı chunk-larla kopyalayır
# 4. Kopyalama bitincə tables swap edir (atomic RENAME)
# 5. Köhnə cədvəli sil
```

---

## Rename Sütun Strategiyası

```
Rename = ən çətin ssenari

Expand/Contract ilə:
  1. Yeni sütun əlavə et (total_amount)
  2. Dual-write: həm köhnəyə (amount), həm yeniyə yaz
  3. Backfill: köhnəni yeniyə kopyala
  4. Read: köhnəni oxumağı dayandır
  5. Write: yalnız yeniyə yaz
  6. Drop: köhnə sütunu sil

Timeline (typical):
  Migration 1 deploy → 1 gün → Backfill → 1 gün → Migration 2 deploy
  
  Niyə 1 gün gözlə?
  → Trafikdə dual-write doğru işlədiyini yoxla
  → Rollback window-u açıq saxla
```

---

## Monitoring

*Bu kod backfill əməliyyatının neçə faiz tamamlandığını izləyən monitoring sinfini göstərir:*

```php
// Backfill progress monitoring
class BackfillMonitor
{
    public function getProgress(): array
    {
        $total = DB::table('orders')->count();
        $filled = DB::table('orders')->whereNotNull('total_amount')->count();
        
        return [
            'total'   => $total,
            'filled'  => $filled,
            'percent' => round($filled / $total * 100, 2),
            'remaining' => $total - $filled,
        ];
    }
    
    public function isComplete(): bool
    {
        return DB::table('orders')
            ->whereNull('total_amount')
            ->doesntExist();
    }
}
```

---

## İntervyu Sualları

**S: Niyə böyük cədvəldə ALTER TABLE dangerous-dır?**
C: MySQL-də `ALTER TABLE` adətən metadata lock tələb edir, bütün cədvəli yenidən qurur. 50M row-lu cədvəldə saatlarla sürmə bilər. Bu müddətdə `INSERT`/`UPDATE`/`SELECT` operasiyaları block olunur. Downtime qaçılmaz. MySQL 8.0+ bəzi ALTER-ları `ALGORITHM=INSTANT` ilə metadata-only dəyişiklik kimi edir (nullable sütun əlavəsi, default dəyər dəyişikliyi) — amma sütun adı dəyişdirmə, tip dəyişdirmə hələ də table rebuild tələb edir.

**S: Expand/Contract pattern nədir, neçə deploy lazımdır?**
C: 3 mərhələli yanaşma: Expand (yeni sütun əlavə et, köhnə işlər davam edir), Migrate (dual-write + backfill — həm köhnəyə, həm yeniyə yaz, köhnə data-nı kopyala), Contract (köhnə sütunu sil). Hər mərhələ ayrı deploy. Zero downtime, amma 2-3x daha uzun proses. Rollback window-u açıq saxlamaq üçün mərhələlər arasında ən az 1 gün gözlə.

**S: gh-ost nədir, necə işləyir?**
C: GitHub Online Schema Change. Shadow cədvəl (`_tablename_ghos`) yaradır. Trigger əvəzinə binlog replication istifadə edir — bu yanaşma trigger-in gətirdiyi yazma yükünü aradan qaldırır. Data-nı chunk-larla kopyalayır (configurable chunk-size). Kopyalama bitdikdə atomic `RENAME TABLE` edir. Lock minimal (millisaniyə), replication lag izlənilir, throttle edilir. `--max-load` flag-i ilə DB yükü yüksəkdirsə avtomatik dayanır.

**S: gh-ost vs pt-online-schema-change fərqi nədir?**
C: pt-osc trigger əsaslıdır — hər INSERT/UPDATE/DELETE üçün trigger yazır, bu yazma yükünü 2x artırır. gh-ost binlog streaming istifadə edir — trigger yoxdur, yazma yükü minimal. pt-osc replication topology-ni bilməlidir; gh-ost master-ı birbaşa oxuyur. Yüksək yazma yükü olan production sistemlərdə gh-ost daha etibarlıdır.

**S: Backfill zamanı nəyi nəzərə almaq lazımdır?**
C: Chunk-larla işlə (OFFSET əvəzinə cursor-based: `WHERE id > lastId`). Hər chunk arasında `usleep(100ms)` — DB-yə nəfəs ver. Progress-i Redis/DB-yə yaz. Replication lag izlə — lag artırsa backfill-i yavaşlat. Idempotent olsun — `NULL` yoxlaması ilə artıq doldurulmuş sətirləri keç. Uzun job üçün queue + multiple workers (fərqli ID range-ləri ilə paralel işlət).

**S: Sütun tipini dəyişdirmək lazım olduqda (məs. INT → BIGINT) necə zero-downtime etmək olar?**
C: Expand/Contract: yeni tip ilə yeni sütun əlavə et (`total_amount_v2 BIGINT`). Dual-write: həm köhnəyə (`total_amount INT`), həm yeniyə yaz. Backfill: köhnə data-nı yeniyə kopyala. Read switching: yalnız yeni sütundan oxu. Drop: köhnəni sil. Bu yanaşma ilə heç bir downtime olmur, amma 3 deploy lazımdır.

**S: Blue-Green deployment ilə zero-downtime migration arasındakı fərq nədir?**
C: Blue-Green-də iki identik mühit var (Blue=aktiv, Green=yeni). Migration: Blue hələ işləyərkən Green-də schema dəyişikliyi tamamla, sonra traffic-i Green-ə yönləndir. Bu yalnız backward-compatible dəyişikliklər üçün işləyir (sütun əlavəsi). Sütun silmə kimi breaking dəyişikliklər üçün hər iki mühitin kodu uyumlu olmalıdır (Expand/Contract + Blue-Green kombinasiyası lazımdır).

**S: Feature flag ilə DB migration-ı necə əlaqələndirmək olar?**
C: Dual-write mərhələsində feature flag əlavə et: flag aktiv olanda yeni sütundan oxu, deaktiv olanda köhnədən oxu. Bu backfill tamamlanmadan read switching üçün güvənli mexanizm verir. Backfill 100% bitdikdə, flag-i bütün istifadəçilər üçün aktiv et. Sonra Contract mərhələsini başlat.

---

## Anti-patternlər

**1. Birbaşa ALTER TABLE istifadəsi**
Böyük cədvəldə `ALTER TABLE` ilə sütun əlavə etmək və ya dəyişdirmək — MySQL bütün cədvəli lock edir, milyonlarla sətirdə saatlarla downtime yaradır. Bunun əvəzinə gh-ost və ya pt-online-schema-change kimi online migration alətlərindən istifadə et.

**2. Bütün backfill-i tək sorğuda etmək**
`UPDATE users SET new_col = old_col` kimi bir sorğu ilə bütün cədvəli backfill etmək — uzun lock, replication lag, DB-nin çökməsi riski. Bunun əvəzinə chunk-larla (məsələn, 1000 sətir) yenilə, hər chunk arasında qısa pauza et.

**3. Expand/Contract mərhələlərini bir deploy-da birləşdirmək**
Yeni sütun əlavə etmək, kodu dəyişmək və köhnə sütunu silmək əməliyyatlarını eyni deploy-da etmək — rollback imkanı aradan çıxır, xəta baş verəndə tərəddüd yaranır. Hər mərhələni (Expand → Migrate → Contract) ayrı deploy kimi planlaşdır.

**4. Köhnə sütunu tez silmək**
Yeni sütun hazır olduqdan dərhal sonra köhnə sütunu silmək — köhnə kod versiyaları (hələ deploy edilməmiş) köhnə sütuna istinad edə bilər. Yeni kod tam deploy edilib sabitləşənə qədər köhnə sütunu saxla.

**5. Migration zamanı replication lag-ı nəzərə almamaq**
Sürətli migration çalışdırarkən binlog tıxanır, replica-lar geridə qalır, oxuma replica-larından köhnə data gəlir. gh-ost kimi alətlər lag threshold-u aşanda avtomatik throttle edir — bu mexanizmi mütləq aktiv et.

**6. Idempotent olmayan migration skriptləri yazmaq**
Migration skripti bir dəfədən çox çalışdırıldıqda xəta verən yanaşma — restart zamanı problam yaranır. `IF NOT EXISTS`, `IF EXISTS` yoxlamalarını istifadə et; migration-lar hər zaman yenidən çalışdırıla bilən olsun.
