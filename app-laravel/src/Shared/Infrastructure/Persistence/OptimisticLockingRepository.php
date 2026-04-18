<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Persistence;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Src\Shared\Domain\ConcurrencyException;
use Src\Shared\Domain\VersionedAggregateRoot;

/**
 * OPTİMİSTİK KİLİDLƏMƏ REPOSITORY — Version-Based Save
 * =========================================================
 *
 * Bu abstract class, VersionedAggregateRoot-ları saxlayarkən
 * optimistic locking yoxlamasını avtomatik edir.
 *
 * NECƏ İŞLƏYİR?
 * ==============
 * 1. Repository aggregate-i DB-dən oxuyur: SELECT * FROM orders WHERE id = ? → version: 5
 * 2. Application aggregate-i dəyişir: $order->confirm() → version 6 olur
 * 3. Repository save edir: UPDATE orders SET ..., version = 6 WHERE id = ? AND version = 5
 * 4. Əgər UPDATE 0 row qaytarırsa → başqa biri artıq dəyişdirib → ConcurrencyException!
 *
 * "WHERE version = 5" — bu şərt DB səviyyəsində atomikdir.
 * İki UPDATE eyni anda gəlsə belə, yalnız biri keçəcək.
 *
 * RETRY STRATEGİYASI:
 * ====================
 * ConcurrencyException tutulduqda:
 *
 * 1. SADƏSİ: Aggregate-i yenidən oxu → əməliyyatı yenidən cəhd et.
 *    ```php
 *    try {
 *        $order = $repo->findById($orderId);
 *        $order->confirm();
 *        $repo->save($order);
 *    } catch (ConcurrencyException $e) {
 *        // Yenidən oxu və cəhd et
 *        $order = $repo->findById($orderId);
 *        $order->confirm();
 *        $repo->save($order);
 *    }
 *    ```
 *
 * 2. MÜRƏKKƏBİ: Retry middleware ilə avtomatik:
 *    CommandBus → RetryMiddleware → Handler → Repository
 *    ConcurrencyException → RetryMiddleware → Handler yenidən çağırılır (max 3 dəfə)
 *
 * EVENT SOURCİNG-DƏ OPTİMİSTİK KİLİDLƏMƏ:
 * ==========================================
 * Event Store-da version = stream-in son event sıra nömrəsidir.
 * Yeni event əlavə edəndə: INSERT INTO events WHERE stream_version = expected_version.
 * Əgər version uyğun gəlmirsə → conflict.
 *
 * Bu implementation state-based (cədvəl) aggregate-lər üçündür.
 * Event Sourced aggregate-lər üçün EventStore.append() öz yoxlamasını edir.
 */
abstract class OptimisticLockingRepository
{
    abstract protected function connection(): string;
    abstract protected function tableName(): string;

    /**
     * VERSİYALI SAVE — Optimistic Locking ilə
     * ==========================================
     *
     * @param VersionedAggregateRoot $aggregate Saxlanılacaq aggregate
     * @param array $data DB-yə yazılacaq data (version və id xaricində)
     *
     * @throws ConcurrencyException Version uyğunsuzluğu varsa
     */
    protected function saveWithVersionCheck(VersionedAggregateRoot $aggregate, array $data): void
    {
        $data['version'] = $aggregate->version();

        $isNew = $aggregate->originalVersion() === 0;

        if ($isNew) {
            // Yeni aggregate — INSERT
            $data['id'] = $aggregate->id();
            DB::connection($this->connection())->table($this->tableName())->insert($data);
        } else {
            /**
             * Mövcud aggregate — UPDATE + VERSION CHECK
             *
             * WHERE id = ? AND version = ?
             * ↑ Bu şərt DB-nin atomik lock-udur.
             * Əgər başqa proses artıq version-u artırıbsa, WHERE 0 row match edəcək.
             */
            $affected = DB::connection($this->connection())->table($this->tableName())
                ->where('id', $aggregate->id())
                ->where('version', $aggregate->originalVersion())
                ->update($data);

            if ($affected === 0) {
                /**
                 * 0 ROW AFFECTED — İki mümkün səbəb:
                 * 1. Aggregate silinib (nadir hal).
                 * 2. Version dəyişib — başqa biri artıq yeniləyib (əsas hal).
                 *
                 * Hər iki halda: aggregate "stale" (köhnəlib).
                 */
                $currentVersion = DB::connection($this->connection())->table($this->tableName())
                    ->where('id', $aggregate->id())
                    ->value('version');

                Log::warning("Optimistic locking conflict", [
                    'aggregate_id' => $aggregate->id(),
                    'expected_version' => $aggregate->originalVersion(),
                    'current_version' => $currentVersion,
                    'table' => $this->tableName(),
                ]);

                throw new ConcurrencyException(
                    aggregateId: $aggregate->id(),
                    expectedVersion: $aggregate->originalVersion(),
                    actualVersion: (int) ($currentVersion ?? -1),
                );
            }
        }
    }
}
