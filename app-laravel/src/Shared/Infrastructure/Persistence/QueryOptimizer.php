<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * QUERY OPTIMIZER — Verilənlər Bazası Sorğu Optimizasiyası
 * ==========================================================
 * Yavaş sorğuları aşkarlayan, N+1 problemini həll edən və
 * performans monitorinqi təmin edən xidmət.
 *
 * ═══════════════════════════════════════════════════════════════
 * N+1 PROBLEM — ƏN ÇOX RAST GƏLİNƏN PERFORMANS PROBLEMİ
 * ═══════════════════════════════════════════════════════════════
 *
 * PROBLEM:
 * 100 sifarişi siyahılamaq istəyirik. Hər sifarişin müştərisi var.
 *
 * YANLIŞ YANAŞMA (N+1):
 *   $orders = Order::all();                    // 1 sorğu: SELECT * FROM orders
 *   foreach ($orders as $order) {
 *       echo $order->customer->name;           // 100 sorğu: SELECT * FROM customers WHERE id = ?
 *   }
 *   // Cəmi: 1 + 100 = 101 sorğu! 🐢
 *
 * DÜZGÜN YANAŞMA (Eager Loading):
 *   $orders = Order::with('customer')->get();  // 2 sorğu:
 *                                               // SELECT * FROM orders
 *                                               // SELECT * FROM customers WHERE id IN (1,2,3,...100)
 *   foreach ($orders as $order) {
 *       echo $order->customer->name;           // Sorğu YOX — artıq yüklənib!
 *   }
 *   // Cəmi: 2 sorğu! 🚀
 *
 * NİYƏ BU QƏDƏR FƏRQ VAR?
 * - 101 sorğu: hər sorğu üçün database-ə ayrı müraciət (network latency)
 * - 2 sorğu: yalnız 2 müraciət, bütün data bir dəfəyə gəlir
 * - 1000 sifariş olsa → 1001 vs 2 sorğu (500x fərq!)
 *
 * ═══════════════════════════════════════════════════════════════
 * EAGER LOADING NÖVLƏRİ
 * ═══════════════════════════════════════════════════════════════
 *
 * 1. with() — Əvvəlcədən yüklə (ən çox istifadə olunan)
 *    Order::with('customer', 'items.product')->get();
 *    // 3 sorğu: orders + customers + (items → products)
 *
 * 2. withCount() — Yalnız sayı yüklə (əlaqəli modelin özünü yox)
 *    Product::withCount('reviews')->get();
 *    // Hər product-a reviews_count əlavə edir
 *    // SELECT products.*, (SELECT COUNT(*) FROM reviews ...) as reviews_count
 *
 * 3. load() — Artıq yüklənmiş model üçün sonradan yüklə
 *    $order = Order::find(1);
 *    $order->load('items');  // Sonradan eager load
 *
 * 4. withWhereHas() — Filtr + eager load eyni anda
 *    Order::withWhereHas('items', fn($q) => $q->where('quantity', '>', 5))->get();
 *
 * ═══════════════════════════════════════════════════════════════
 * DATABASE İNDEKSLƏMƏ STRATEGİYALARI
 * ═══════════════════════════════════════════════════════════════
 *
 * İNDEKS NƏDİR?
 * Kitabın arxasındakı əlifba sırası ilə düzülmüş mövzu siyahısı kimidir.
 * "PHP" sözünü tapmaq üçün hər səhifəni oxumaq əvəzinə, indeksə baxırsan → "PHP: səh. 234"
 *
 * İNDEKS NÖVLƏRİ:
 *
 * 1. PRIMARY KEY — Unikal identifikator
 *    $table->id();
 *    // Hər cədvəlin əsas açarı. Avtomatik indekslənir.
 *
 * 2. UNIQUE INDEX — Unikal dəyərlər
 *    $table->unique('email');
 *    // email sütununda təkrar dəyər ola bilməz.
 *    // Login zamanı email-ə görə axtarışı sürətləndirir.
 *
 * 3. REGULAR INDEX — Adi indeks
 *    $table->index('status');
 *    // WHERE status = 'active' sorğularını sürətləndirir.
 *
 * 4. COMPOSITE INDEX — Birləşdirilmiş indeks
 *    $table->index(['user_id', 'created_at']);
 *    // WHERE user_id = 5 AND created_at > '2024-01-01' sorğularını sürətləndirir.
 *    // SIRA VACİBDİR: sol-dan sağ-a (leftmost prefix rule)
 *    // Bu indeks user_id üzrə axtarışı da sürətləndirir,
 *    // amma TƏK created_at üzrə axtarışı sürətləndirMİR!
 *
 * 5. FULLTEXT INDEX — Tam mətn axtarışı
 *    $table->fullText(['name', 'description']);
 *    // LIKE '%laptop%' əvəzinə MATCH(name) AGAINST('laptop')
 *    // Çox daha sürətli (xüsusilə böyük cədvəllərdə)
 *
 * HARADA İNDEKS LAZIMDIR?
 * ✅ WHERE şərtlərində tez-tez istifadə olunan sütunlar
 * ✅ JOIN şərtlərində istifadə olunan foreign key-lər
 * ✅ ORDER BY sütunları
 * ✅ GROUP BY sütunları
 *
 * HARADA İNDEKS LAZIM DEYİL?
 * ❌ Nadir axtarılan sütunlar (indeks disk sahəsi tutur)
 * ❌ Çox az unikal dəyəri olan sütunlar (məs: gender — M/F)
 * ❌ Çox tez-tez UPDATE olunan sütunlar (hər UPDATE indeksi yeniləyir)
 *
 * ═══════════════════════════════════════════════════════════════
 * EXPLAIN — SORĞU ANALİZİ
 * ═══════════════════════════════════════════════════════════════
 *
 * EXPLAIN SELECT * FROM orders WHERE status = 'pending';
 *
 * NƏTİCƏNİ NECƏ OXUMAQ:
 * - type: ALL → tam cədvəl skan (PİS! İndeks yoxdur)
 * - type: index → indeks skan (yaxşı)
 * - type: ref → indeks ilə referans axtarış (çox yaxşı)
 * - type: const → primary key ilə (ən yaxşı)
 * - rows: neçə sətir yoxlandı (az olmalıdır)
 * - Extra: "Using filesort" → ORDER BY üçün indeks yoxdur (yavaş)
 * - Extra: "Using temporary" → müvəqqəti cədvəl yaradıldı (yavaş)
 *
 * ═══════════════════════════════════════════════════════════════
 * QUERY OPTIMIZATION TEXNİKALARI
 * ═══════════════════════════════════════════════════════════════
 *
 * 1. SELECT yalnız lazım olan sütunları:
 *    ❌ SELECT * FROM orders
 *    ✅ SELECT id, status, total_amount FROM orders
 *
 * 2. Cursor pagination (böyük dataset-lər üçün):
 *    ❌ OFFSET 10000, LIMIT 20 → 10020 sətir oxunur, 10000 atılır
 *    ✅ WHERE id > 10000 LIMIT 20 → yalnız 20 sətir oxunur
 *
 * 3. Chunk processing (böyük toplu əməliyyatlar):
 *    ❌ User::all()->each(fn($u) => ...) → bütün cədvəl RAM-a yüklənir
 *    ✅ User::chunk(1000, fn($users) => ...) → 1000-lik hissələrlə işlə
 *
 * 4. Lazy collection (memory-efficient):
 *    User::lazy()->each(fn($u) => ...) → bir-bir oxuyur, RAM yükləmir
 */
class QueryOptimizer
{
    /**
     * Yavaş sorğuları aşkarla və log-la.
     *
     * Laravel-in DB::listen() metodu hər sorğunu dinləyir.
     * Müəyyən müddətdən uzun çəkən sorğuları "yavaş" hesab edib log-layırıq.
     *
     * Production-da bu log-lar Grafana/Datadog-a göndərilir və alert yaradılır.
     *
     * @param float $thresholdMs Yavaş sorğu həddi (millisaniyə). Default: 100ms.
     *   - 100ms: çox sorğu üçün ağlabatan hədd
     *   - Sadə SELECT: 1-10ms
     *   - JOIN ilə sorğu: 10-50ms
     *   - 100ms-dən çox: problem var, yəqin ki indeks yoxdur
     */
    public static function enableSlowQueryLogging(float $thresholdMs = 100.0): void
    {
        DB::listen(function ($query) use ($thresholdMs) {
            if ($query->time >= $thresholdMs) {
                Log::warning('Yavaş sorğu aşkarlandı', [
                    'sql' => $query->sql,
                    'bindings' => $query->bindings,
                    'time_ms' => $query->time,
                    'threshold_ms' => $thresholdMs,
                    // Connection adı hansı DB-dən gəldiyini göstərir (multi-DB halında faydalı)
                    'connection' => $query->connectionName,
                ]);
            }
        });
    }

    /**
     * EXPLAIN vasitəsilə sorğu planını analiz et.
     *
     * Bu metod sorğunun necə icra olunacağını göstərir:
     * - Hansı indekslərdən istifadə edir?
     * - Neçə sətir yoxlayır?
     * - Tam cədvəl skan edirmi?
     *
     * @param string $sql SQL sorğusu
     * @param array $bindings Parametrlər
     * @param string $connection Database bağlantı adı
     * @return array EXPLAIN nəticəsi
     */
    public static function explain(string $sql, array $bindings = [], string $connection = 'default'): array
    {
        $results = DB::connection($connection)->select("EXPLAIN {$sql}", $bindings);

        return array_map(fn($row) => (array) $row, $results);
    }

    /**
     * Cursor-based pagination — böyük dataset-lər üçün effektiv səhifələmə.
     *
     * PROBLEM: OFFSET/LIMIT pagination
     * ────────────────────────────────
     * Səhifə 500: OFFSET 10000, LIMIT 20
     * Database 10020 sətir oxuyur, 10000-ni atır, 20-ni qaytarır.
     * Səhifə nömrəsi artdıqca performans düşür!
     *
     * HƏLL: Cursor pagination
     * ───────────────────────
     * WHERE id > 10000 ORDER BY id LIMIT 20
     * Database birbaşa id > 10000 olan yerə atlayır (indeks istifadə edir).
     * 1-ci səhifə ilə 500-cü səhifə eyni sürətdədir!
     *
     * MƏHDUDIYYƏT:
     * - Yalnız "növbəti səhifə" / "əvvəlki səhifə" dəstəkləyir
     * - "5-ci səhifəyə keç" mümkün deyil (bunun üçün OFFSET lazımdır)
     * - API-larda (infinite scroll) ideal, admin paneldə az uyğun
     *
     * @param Builder $query Eloquent sorğusu
     * @param int $limit Səhifədəki element sayı
     * @param string|int|null $cursor Sonuncu elementin ID-si (null = ilk səhifə)
     * @param string $cursorColumn Cursor sütunu (default: id)
     * @return array{data: array, next_cursor: string|null, has_more: bool}
     */
    public static function cursorPaginate(
        Builder $query,
        int $limit = 20,
        string|int|null $cursor = null,
        string $cursorColumn = 'id',
    ): array {
        if ($cursor !== null) {
            $query->where($cursorColumn, '>', $cursor);
        }

        // limit + 1 — əlavə bir element oxuyuruq ki, "daha var?" sualına cavab verək
        $items = $query->orderBy($cursorColumn)->limit($limit + 1)->get();

        $hasMore = $items->count() > $limit;

        if ($hasMore) {
            $items = $items->take($limit); // Əlavə elementi sil
        }

        $nextCursor = $hasMore ? $items->last()?->{$cursorColumn} : null;

        return [
            'data' => $items->toArray(),
            'next_cursor' => $nextCursor,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Böyük dataset-lər üzərində chunk əməliyyatı — memory-efficient emal.
     *
     * PROBLEM: Model::all()
     * ─────────────────────
     * User::all() → 1 milyon istifadəçini RAM-a yükləyir → Memory OVERFLOW!
     *
     * HƏLL: chunk()
     * ─────────────
     * 1000-lik hissələrlə oxuyur. Hər hissə emal edildikdən sonra RAM-dan silinir.
     *
     * @param Builder $query Eloquent sorğusu
     * @param int $chunkSize Hər hissədəki element sayı
     * @param callable $callback Hər hissə üçün icra olunan funksiya
     */
    public static function processInChunks(Builder $query, int $chunkSize, callable $callback): void
    {
        $query->chunkById($chunkSize, function ($records) use ($callback) {
            foreach ($records as $record) {
                $callback($record);
            }
        });
    }
}
