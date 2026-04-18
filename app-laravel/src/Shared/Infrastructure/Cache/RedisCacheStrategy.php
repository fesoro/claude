<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Cache;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * REDİS CACHING STRATEGY (Cache Pattern-ləri)
 * =============================================
 * Redis ilə müxtəlif caching strategiyalarını implementasiya edir.
 *
 * REDİS NƏDİR?
 * Redis — in-memory data store. Datanı RAM-da saxlayır, disk-dən 100x sürətli.
 * Cache, session, queue, real-time data üçün istifadə olunur.
 *
 * ═══════════════════════════════════════════════════════════════════
 * CACHING STRATEGİYALARI (Pattern-lər):
 * ═══════════════════════════════════════════════════════════════════
 *
 * 1. CACHE-ASIDE (Lazy Loading):
 * ─────────────────────────────
 * Ən çox istifadə olunan strategiya. "Lazım olanda cache-lə."
 *
 *   OXUMA:
 *   ├─ Cache-də var? → Cache-dən qaytar (HIT)
 *   └─ Cache-də yox? → DB-dən oxu → Cache-ə yaz → Qaytar (MISS)
 *
 *   YAZMA:
 *   ├─ DB-yə yaz
 *   └─ Cache-i sil (invalidate) — növbəti oxumada yenidən cache-lənəcək
 *
 *   ÜSTÜNLÜKLƏRİ: Sadə, yalnız oxunan data cache-lənir
 *   ÇATIŞMAZLIQLARI: İlk oxuma yavaş (cold start)
 *
 * 2. WRITE-THROUGH:
 * ─────────────────
 * "Yazanda həm DB-yə, həm cache-ə yaz."
 *
 *   YAZMA:
 *   ├─ DB-yə yaz
 *   └─ Cache-ə yaz (eyni anda)
 *
 *   OXUMA:
 *   └─ Cache-dən oxu (həmişə güncel)
 *
 *   ÜSTÜNLÜKLƏRİ: Cache həmişə güncel, cold start yoxdur
 *   ÇATIŞMAZLIQLARI: Yazma yavaşlayır (iki yerdə yazır), istifadə olunmayan data cache-lənir
 *
 * 3. WRITE-BEHIND (Write-Back):
 * ─────────────────────────────
 * "Əvvəlcə cache-ə yaz, sonra async DB-yə yaz."
 *
 *   YAZMA:
 *   ├─ Cache-ə yaz (dərhal)
 *   └─ Queue/batch ilə DB-yə yaz (sonra)
 *
 *   ÜSTÜNLÜKLƏRİ: Çox sürətli yazma
 *   ÇATIŞMAZLIQLARI: Data itməsi riski (cache çöksə), mürəkkəb
 *
 * 4. READ-THROUGH:
 * ────────────────
 * Cache provider özü DB-dən oxuyur (application bilmir).
 * Cache-aside-a bənzəyir, amma cache layer daha ağıllıdır.
 *
 * ═══════════════════════════════════════════════════════════════════
 * REDİS DATA STRUCTURES (Redis Məlumat Tipləri):
 * ═══════════════════════════════════════════════════════════════════
 *
 * STRING: Sadə key-value. Cache üçün ən çox istifadə olunur.
 *   SET product:123 "{name: 'Laptop', price: 999}"
 *   GET product:123
 *
 * HASH: Obyekt saxlamaq üçün (bir key altında çox sahə).
 *   HSET user:456 name "Orkhan" email "orkhan@test.com"
 *   HGET user:456 name
 *
 * LIST: Sıralı siyahı. Queue, activity feed üçün.
 *   LPUSH notifications:user:456 "Sifarişiniz yaradıldı"
 *   LRANGE notifications:user:456 0 9  (son 10 bildiriş)
 *
 * SET: Unikal elementlər toplusu. Online users, tags üçün.
 *   SADD online_users user:456
 *   SMEMBERS online_users
 *
 * SORTED SET: Skor ilə sıralı set. Leaderboard, trending üçün.
 *   ZADD popular_products 150 "product:123"  (150 satış)
 *   ZREVRANGE popular_products 0 9  (top 10 məhsul)
 *
 * ═══════════════════════════════════════════════════════════════════
 */
class RedisCacheStrategy
{
    /**
     * CACHE-ASIDE strategiyası ilə data oxu.
     * Cache-də varsa oradan, yoxsa callback ilə al və cache-lə.
     */
    public function cacheAside(string $key, int $ttlSeconds, callable $dataProvider): mixed
    {
        // Cache HIT — data cache-dədir
        $cached = Cache::store('redis')->get($key);
        if ($cached !== null) {
            Log::debug("Cache HIT: {$key}");
            return $cached;
        }

        // Cache MISS — DB-dən oxu
        Log::debug("Cache MISS: {$key}");
        $data = $dataProvider();

        // Cache-ə yaz
        if ($data !== null) {
            Cache::store('redis')->put($key, $data, $ttlSeconds);
        }

        return $data;
    }

    /**
     * WRITE-THROUGH strategiyası ilə data yaz.
     * Həm DB-yə, həm cache-ə eyni anda yazılır.
     */
    public function writeThrough(string $key, mixed $data, int $ttlSeconds, callable $dbWriter): mixed
    {
        // 1. DB-yə yaz
        $result = $dbWriter($data);

        // 2. Cache-ə yaz
        Cache::store('redis')->put($key, $data, $ttlSeconds);

        Log::debug("Write-through: {$key}");

        return $result;
    }

    /**
     * WRITE-BEHIND strategiyası ilə data yaz.
     * Cache-ə dərhal yazılır, DB-yə yazma queue ilə async olur.
     *
     * DİQQƏT: Bu strategiya data itkisi riski daşıyır!
     * Redis çöksə, cache-dəki data itər amma DB-yə yazılmaz.
     * Yalnız itməsi kritik olmayan data üçün istifadə edin.
     */
    public function writeBehind(string $key, mixed $data, int $ttlSeconds, callable $asyncDbWriter): void
    {
        // 1. Cache-ə dərhal yaz
        Cache::store('redis')->put($key, $data, $ttlSeconds);

        // 2. DB-yə async yaz (queue vasitəsilə)
        // Real implementasiyada: dispatch(new WriteToDatabaseJob($key, $data));
        $asyncDbWriter($data);

        Log::debug("Write-behind: {$key}");
    }

    /**
     * Cache-i sil (invalidate).
     * Data dəyişdikdə köhnə cache silinir.
     */
    public function invalidate(string $key): void
    {
        Cache::store('redis')->forget($key);
        Log::debug("Cache invalidated: {$key}");
    }

    /**
     * Pattern ilə birdən çox cache sil.
     * Məsələn: invalidatePattern('product:*') → bütün məhsul cache-ini sil.
     *
     * QEYD: Laravel Cache facade pattern-based delete dəstəkləmir.
     * Bu Redis-in native SCAN + DEL əmrləri ilə edilir.
     */
    public function invalidatePattern(string $pattern): int
    {
        $redis = Cache::store('redis')->getStore()->getRedis();
        $prefix = config('cache.prefix', '') . ':';
        $count = 0;

        // SCAN ilə key-ləri tap (KEYS əvəzinə — KEYS production-da bloklamaya səbəb olur)
        $iterator = null;
        do {
            $keys = $redis->scan($iterator, ['match' => $prefix . $pattern, 'count' => 100]);
            if ($keys) {
                $redis->del(...$keys);
                $count += count($keys);
            }
        } while ($iterator > 0);

        Log::debug("Cache pattern invalidated: {$pattern} ({$count} keys)");

        return $count;
    }
}
