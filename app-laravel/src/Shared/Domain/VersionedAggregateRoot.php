<?php

declare(strict_types=1);

namespace Src\Shared\Domain;

use Src\Shared\Domain\Exceptions\DomainException;

/**
 * VERSIONED AGGREGATE ROOT — Optimistic Locking ilə Aggregate
 * ==============================================================
 *
 * PROBLEMİ ANLAYAQ:
 * =================
 * İki istifadəçi eyni anda eyni sifarişi dəyişdirmək istəyir:
 *
 * İstifadəçi A: Order oxuyur (version: 1) → Statusu dəyişir → Saxlayır ✓
 * İstifadəçi B: Order oxuyur (version: 1) → Ünvanı dəyişir → Saxlayır ???
 *
 * PROBLEM: İstifadəçi B-nin dəyişikliyi A-nın dəyişikliyini əzər!
 * Bu "Lost Update" problemidir — ən çox rast gəlinən concurrency bug-ıdır.
 *
 * 2 HƏLLİ VAR:
 * =============
 *
 * 1. PESSİMİSTİK KİLİDLƏMƏ (Pessimistic Locking):
 *    - Oxuyanda DB-də kilid qoyursan (SELECT FOR UPDATE).
 *    - Digər istifadəçi gözləyir, ta ki, kilid açılsın.
 *    - Problem: Deadlock riski, performans aşağı düşür.
 *    - Nümunə: Bank köçürmələri — pul var/yox yoxlamaq vacibdir.
 *
 * 2. OPTİMİSTİK KİLİDLƏMƏ (Optimistic Locking): ← BU YANAŞMA
 *    - Heç bir kilid qoyulmur — hamı oxuya bilər.
 *    - Yazanda version nömrəsini yoxlayırsan.
 *    - Əgər version dəyişibsə → "başqa biri artıq dəyişdirdi" xətası verir.
 *    - İstifadəçi yenidən oxuyub təkrar cəhd edir.
 *    - Problem: Konflikt çox olanda effektiv deyil.
 *    - Nümunə: E-commerce sifariş dəyişiklikləri — konflikt nadir hallarda olur.
 *
 * ANALOGİYA:
 * ==========
 * Google Docs: Sən sənədi açırsan (version 5). Yazırsan. Save edirsən.
 * Əgər o vaxt ərzində başqa biri dəyişdiribsə (version 6 olub) — "Conflict" xəbərdarlığı alırsan.
 * Sən yeni version-u oxuyub dəyişikliyini birləşdirməlisən.
 *
 * Git: İki nəfər eyni branch-ə push edir. İkinci push rədd olunur — "pull first".
 * Bu da optimistic locking-dir: "oxuduğun zaman dəyişməyibsə, yaz".
 *
 * NECƏ İŞLƏYİR?
 * ==============
 * 1. Aggregate DB-dən oxunur: { id: 123, version: 5, ... }
 * 2. Aggregate dəyişdirilir: $order->confirm()
 * 3. Version artırılır: version: 5 → 6
 * 4. DB-yə yazılır: UPDATE orders SET ... WHERE id = 123 AND version = 5
 * 5. Əgər WHERE şərti 0 row qaytarırsa → başqa biri artıq dəyişdirib → XƏTA!
 *
 * NƏYƏ "WHERE version = ?" VACİBDİR?
 * Bu şərt DB-nin atomik əməliyyatıdır. İki UPDATE eyni anda gəlsə belə,
 * yalnız biri WHERE şərtini keçəcək. Digəri 0 row update edəcək.
 * Bu, DB səviyyəsində race condition-un qarşısını alır.
 *
 * EVENT SOURCİNG İLƏ ƏLAQƏ:
 * ==========================
 * Event Sourcing-də version = event stream-in son event nömrəsidir.
 * Yeni event əlavə edəndə: "event 6-dan sonra əlavə et" — əgər artıq event 6 varsa, rədd et.
 * Bu, event store-da da optimistic locking təmin edir.
 *
 * BU CLASSIN AGGREGATE ROOT-DAN FƏRQI:
 * =====================================
 * AggregateRoot: Event record edir, version-u yoxdur.
 * VersionedAggregateRoot: Event record edir + version izləyir + concurrency müdafiəsi.
 * Əgər aggregate-ə concurrent access ola bilərsə, bu class-dan extend et.
 */
abstract class VersionedAggregateRoot extends AggregateRoot
{
    /**
     * Aggregate-in cari versiyası.
     * Hər dəyişiklikdə 1 artır. DB-dən oxuyanda əvvəlki dəyəri saxlanılır.
     *
     * İlk yaradılanda version 0-dır.
     * İlk save-dən sonra version 1 olur.
     * Hər update-dən sonra version artır.
     */
    private int $version = 0;

    /**
     * Aggregate oxunanda DB-dəki version dəyəri.
     * Save edəndə bu dəyər WHERE şərtində istifadə olunacaq.
     *
     * NƏYƏ AYRI SAXLANıLıR?
     * $version: aggregate-in indiki versiyasıdır (artırılmış).
     * $originalVersion: DB-dən oxunduğu andakı versiyasıdır (WHERE üçün).
     *
     * Əgər originalVersion != DB-dəki version → ConcurrencyException!
     */
    private int $originalVersion = 0;

    /**
     * VERSİYANI ARTIR — hər biznes əməliyyatında çağırılmalıdır.
     *
     * Bu metod aggregate-in dəyişdiyini bildirir.
     * Repository save edəndə bu artmış version-u DB-yə yazacaq.
     *
     * İSTİFADƏ:
     * ```php
     * public function confirm(): void {
     *     $this->status = OrderStatus::confirmed();
     *     $this->incrementVersion(); // <-- HƏR DƏYİŞİKLİKDƏ ÇAĞIR
     *     $this->recordEvent(new OrderConfirmedEvent(...));
     * }
     * ```
     */
    protected function incrementVersion(): void
    {
        $this->version++;
    }

    /**
     * DB-dən oxuyanda version-u bərpa et.
     * Repository reconstitute edəndə çağırır.
     */
    public function setVersion(int $version): void
    {
        $this->version = $version;
        $this->originalVersion = $version;
    }

    public function version(): int
    {
        return $this->version;
    }

    /**
     * WHERE şərtində istifadə olunacaq version.
     * Bu, aggregate oxunduğu andakı version-dur.
     */
    public function originalVersion(): int
    {
        return $this->originalVersion;
    }

    /**
     * Version dəyişibmi yoxla.
     * True qaytarırsa — aggregate dəyişdirilib və save olunmalıdır.
     */
    public function isDirty(): bool
    {
        return $this->version !== $this->originalVersion;
    }

    /**
     * OPTİMİSTİK KİLİD YOXLAMASI
     * ============================
     * Repository-nin save metodunda istifadə olunur.
     * DB-dən oxunan version ilə cari original version-u müqayisə edir.
     *
     * @param int $dbVersion DB-dən SELECT ilə oxunan cari version
     * @throws ConcurrencyException Version uyğunsuzluğu varsa
     */
    public function ensureNotStale(int $dbVersion): void
    {
        if ($this->originalVersion !== $dbVersion) {
            throw new ConcurrencyException(
                aggregateId: $this->id(),
                expectedVersion: $this->originalVersion,
                actualVersion: $dbVersion,
            );
        }
    }
}

/**
 * CONCURRENCY EXCEPTION — Eyni Vaxtlılıq Xətası
 * =================================================
 * Bu xəta "Lost Update" probleminin baş verdiyini bildirir.
 *
 * HANDLER-DƏ NƏ ETMƏLİ?
 * 1. Aggregate-i DB-dən yenidən oxu (təzə version ilə).
 * 2. Əməliyyatı yenidən cəhd et (retry).
 * 3. Əgər conflict həll olunmursa → istifadəçiyə bildir.
 *
 * HTTP CAVABI:
 * 409 Conflict — "Sifariş başqa biri tərəfindən dəyişdirilib. Zəhmət olmasa yeniləyin."
 * Bu, REST API-da standart cavabdır.
 */
final class ConcurrencyException extends DomainException
{
    public function __construct(
        public readonly string $aggregateId,
        public readonly int $expectedVersion,
        public readonly int $actualVersion,
    ) {
        parent::__construct(
            "Aggregate '{$aggregateId}' artıq dəyişdirilib. " .
            "Gözlənilən version: {$expectedVersion}, DB-dəki version: {$actualVersion}. " .
            "Zəhmət olmasa yenidən oxuyub təkrar cəhd edin."
        );
    }
}
