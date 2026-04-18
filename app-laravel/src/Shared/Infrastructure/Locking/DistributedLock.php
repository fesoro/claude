<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Locking;

use Illuminate\Support\Facades\Cache;
use Closure;

/**
 * DISTRIBUTED LOCK (Paylanmış Kilid)
 * ====================================
 * Eyni vaxtda yalnız BİR prosesin müəyyən əməliyyatı icra etməsini təmin edir.
 *
 * RACE CONDITION PROBLEMİ:
 * ========================
 * İki müştəri eyni anda sonuncu məhsulu almaq istəyir:
 *
 *   Müştəri A                    Müştəri B
 *   ─────────                    ─────────
 *   Stock yoxla → 1 var           Stock yoxla → 1 var     (eyni anda oxudular!)
 *   Stock azalt → 0               Stock azalt → -1        (PROBLEM! -1 ola bilməz)
 *   Sifariş yarat ✓               Sifariş yarat ✓        (iki sifariş, bir məhsul!)
 *
 * Bu "race condition" adlanır — iki proses eyni resursa eyni anda müraciət edir.
 *
 * HƏLL: DISTRIBUTED LOCK
 * =======================
 *   Müştəri A                    Müştəri B
 *   ─────────                    ─────────
 *   Lock al ✓ (kilid əldə edildi)  Lock al ✗ (gözlə...)
 *   Stock yoxla → 1 var            ... gözləyir ...
 *   Stock azalt → 0                ... gözləyir ...
 *   Sifariş yarat ✓                ... gözləyir ...
 *   Lock burax ✓                  Lock al ✓ (indi növbə sənindi)
 *                                  Stock yoxla → 0 var
 *                                  "Stock yoxdur" xətası ✗
 *
 * NİYƏ "DISTRIBUTED"?
 * ====================
 * Tək server olsa, PHP-nin flock() və ya mutex istifadə edə bilərik.
 * Amma microservice və ya çox server arxitekturasında:
 *   - Server A-da lock alsan, Server B bilmir
 *   - Redis BÜTÜN serverlər üçün ortaq "kilid mağazası"dır
 *
 * Redis-in bu iş üçün seçilməsinin səbəbləri:
 * 1. In-memory — çox sürətli (mikrosaniyələr)
 * 2. Atomic əməliyyatlar — SET NX (set if not exists) dəstəkləyir
 * 3. TTL — kilid avtomatik silinir (deadlock qarşısını alır)
 * 4. Paylaşılan — bütün serverlər eyni Redis-ə qoşulur
 *
 * DEADLOCK PROBLEMİ VƏ TTL:
 * ==========================
 * Lock alan proses çökdü → Lock buraxılmadı → Heç kim lock ala bilmir!
 * TTL (Time To Live) bu problemi həll edir:
 *   - Lock 10 saniyəliyə alınır
 *   - Proses çöksə belə, 10 saniyə sonra lock avtomatik silinir
 *   - Digər proseslər davam edə bilər
 *
 * OWNER TOKEN NƏDİR?
 * ===================
 * Hər lock-un sahibini tanıdan unikal token.
 * Niyə lazımdır? Səhvən başqasının lock-unu buraxmamaq üçün:
 *
 *   Proses A: Lock aldı (token: abc123)
 *   Proses A: İşi bitdi
 *   ... TTL bitdi, lock silindi ...
 *   Proses B: Lock aldı (token: xyz789)
 *   Proses A: Lock burax (token: abc123) → RƏDD! Token uyğun gəlmir.
 *
 * Bu mexanizm olmasa, Proses A Proses B-nin lock-unu buraxar — təhlükəli!
 *
 * İSTİFADƏ NÜMUNƏLƏRİ:
 * =====================
 *
 * 1. Sadə lock:
 *   $lock = new DistributedLock();
 *   $lock->execute('order:create:user:123', function () {
 *       // Yalnız bir proses bura daxil ola bilər
 *       $this->createOrder($userId);
 *   });
 *
 * 2. Manual lock:
 *   $lock = new DistributedLock();
 *   if ($lock->acquire('payment:process:456')) {
 *       try {
 *           $this->processPayment($orderId);
 *       } finally {
 *           $lock->release('payment:process:456');
 *       }
 *   }
 *
 * 3. Middleware ilə (HTTP request səviyyəsində):
 *   Route::post('/orders', [OrderController::class, 'store'])
 *       ->middleware('distributed-lock:order_create');
 */
class DistributedLock
{
    /** Lock açar prefiksi — digər cache açarları ilə toqquşmanın qarşısını alır */
    private const KEY_PREFIX = 'distributed_lock:';

    /** Cari proses tərəfindən alınmış lock-ların owner token-ləri */
    private array $ownedLocks = [];

    /**
     * @param int $defaultTtl Lock-un default ömrü (saniyə).
     *   Bu müddət bitdikdə lock avtomatik silinir — deadlock qarşısını alır.
     *   Default 10 saniyə — çox əməliyyat üçün kifayətdir.
     *   Uzun əməliyyatlar üçün daha böyük TTL istifadə edin.
     *
     * @param int $retryIntervalMs Lock əldə etmək üçün retry intervalı (millisaniyə).
     *   Lock başqası tərəfindən tutulubsa, bu qədər gözlədikdən sonra yenidən cəhd edir.
     *   Default 100ms — çox tez retry serveri yükləyir, çox gec istifadəçini gözlədir.
     *
     * @param int $maxRetries Maksimum retry sayı.
     *   Bu qədər cəhddən sonra lock əldə edilə bilmirsə, false qaytarılır.
     *   Default 50 (100ms * 50 = 5 saniyə gözləmə).
     */
    public function __construct(
        private readonly int $defaultTtl = 10,
        private readonly int $retryIntervalMs = 100,
        private readonly int $maxRetries = 50,
    ) {
    }

    /**
     * Lock al — eyni anda yalnız bir proses bu lock-u saxlaya bilər.
     *
     * Redis-in SET NX (Set if Not eXists) əmrini istifadə edir:
     * - Açar yoxdursa → yaradılır və true qaytarılır (lock alındı)
     * - Açar varsa → heç nə edilmir və false qaytarılır (lock tutulub)
     *
     * @param string $key Lock açarı (məs: "order:create:user:123")
     * @param int|null $ttl Lock ömrü (null olsa defaultTtl istifadə olunur)
     * @return bool Lock əldə edildimi?
     */
    public function acquire(string $key, ?int $ttl = null): bool
    {
        $ttl = $ttl ?? $this->defaultTtl;
        $lockKey = self::KEY_PREFIX . $key;

        // Unikal owner token — bu prosesin lock-un sahibi olduğunu təsdiqləyir
        $ownerToken = bin2hex(random_bytes(16));

        // Retry loop — lock başqası tərəfindən tutulubsa gözlə və yenidən cəhd et
        for ($i = 0; $i < $this->maxRetries; $i++) {
            // Cache::add() = SET NX + TTL (atomic əməliyyat)
            // Yalnız açar mövcud DEYİLSƏ yaradır — bu, lock-un atomikliyini təmin edir.
            // İki proses eyni anda add() çağırsa, yalnız biri uğurlu olacaq.
            $acquired = Cache::add($lockKey, $ownerToken, $ttl);

            if ($acquired) {
                $this->ownedLocks[$key] = $ownerToken;
                return true;
            }

            // Lock tutulub — qısa müddət gözlə və yenidən cəhd et
            usleep($this->retryIntervalMs * 1000);
        }

        // Bütün cəhdlər bitdi — lock əldə edilə bilmədi
        return false;
    }

    /**
     * Lock burax — yalnız lock-un sahibi buraxa bilər.
     *
     * Owner token yoxlaması olmasa belə problem yarana bilər:
     *   Proses A lock aldı → TTL bitdi → lock silindi
     *   Proses B lock aldı → Proses A release çağırdı → Proses B-nin lock-u silindi!
     *
     * Owner token bu problemi həll edir — yalnız öz lock-unu buraxa bilərsən.
     *
     * @param string $key Lock açarı
     * @return bool Lock uğurla buraxıldımı?
     */
    public function release(string $key): bool
    {
        $lockKey = self::KEY_PREFIX . $key;

        // Owner token yoxla — bu bizim lock-umuzdurmu?
        if (!isset($this->ownedLocks[$key])) {
            return false;
        }

        $currentValue = Cache::get($lockKey);

        // Token uyğun gəlmirsə, bu artıq bizim lock-umuz deyil
        // (TTL bitib və başqası yeni lock alıb)
        if ($currentValue !== $this->ownedLocks[$key]) {
            unset($this->ownedLocks[$key]);
            return false;
        }

        // Lock-u sil
        Cache::forget($lockKey);
        unset($this->ownedLocks[$key]);

        return true;
    }

    /**
     * Lock daxilində əməliyyat icra et — try/finally pattern avtomatik tətbiq olunur.
     *
     * Bu metod ən təhlükəsiz yanaşmadır çünki:
     * 1. Lock avtomatik alınır
     * 2. Exception olsa belə, lock avtomatik buraxılır (finally bloku)
     * 3. Developer lock.release() unutma riski yoxdur
     *
     * Real həyat analogiyası:
     * Tualetə girirsən → qapını bağlayırsan (lock) → işini görürsən
     * → çıxırsan → qapı avtomatik açılır (finally)
     * Exception olsa belə (məsələn, telefon düşdü) qapı açıq qalmır.
     *
     * @template T
     * @param string $key Lock açarı
     * @param Closure(): T $callback İcra olunacaq əməliyyat
     * @param int|null $ttl Lock ömrü
     * @return T|null Əməliyyatın nəticəsi, lock alına bilmədisə null
     *
     * @throws LockNotAcquiredException Lock əldə edilə bilmədikdə
     */
    public function execute(string $key, Closure $callback, ?int $ttl = null): mixed
    {
        if (!$this->acquire($key, $ttl)) {
            throw new LockNotAcquiredException(
                "Lock əldə edilə bilmədi: '{$key}'. "
                . "Başqa proses bu əməliyyatı icra edir. "
                . "{$this->maxRetries} cəhddən sonra timeout oldu."
            );
        }

        try {
            return $callback();
        } finally {
            // Exception olsa belə lock MÜTLƏQ buraxılır.
            // finally bloku həmişə icra olunur — return, throw, exit fərq etməz.
            $this->release($key);
        }
    }

    /**
     * Lock-un hazırda tutulub-tutulmadığını yoxla.
     *
     * @param string $key Lock açarı
     * @return bool Lock tutulubmu?
     */
    public function isLocked(string $key): bool
    {
        return Cache::has(self::KEY_PREFIX . $key);
    }
}
