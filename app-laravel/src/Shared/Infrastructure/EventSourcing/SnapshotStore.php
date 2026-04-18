<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\EventSourcing;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * SNAPSHOT STORE — Snapshot-ları DB-də saxlayan repository
 * =========================================================
 *
 * ═══════════════════════════════════════════════════════════════
 * SNAPSHOT İLƏ AGGREGATE YÜKLƏMƏ ALGORİTMİ
 * ═══════════════════════════════════════════════════════════════
 *
 * ƏVVƏL (snapshot olmadan):
 * ─────────────────────────
 * 1. Event Store-dan BÜTÜN event-ləri oxu: SELECT * FROM event_store WHERE aggregate_id = ?
 * 2. Boş aggregate yarat
 * 3. BÜTÜN event-ləri replay et: foreach ($events as $event) { $aggregate->apply($event); }
 * 4. Aggregate hazırdır
 *
 * İNDİ (snapshot ilə):
 * ────────────────────
 * 1. SnapshotStore-dan sonuncu snapshot-u oxu
 * 2. Əgər snapshot varsa:
 *    a. Snapshot-dan aggregate-i bərpa et (deserialize)
 *    b. Event Store-dan YALNIZ snapshot-dan sonrakı event-ləri oxu:
 *       SELECT * FROM event_store WHERE aggregate_id = ? AND version > snapshot_version
 *    c. Bu event-ləri replay et
 * 3. Əgər snapshot yoxdursa:
 *    a. Köhnə üsulla bütün event-ləri replay et
 * 4. Aggregate hazırdır
 *
 * PERFORMANS MÜQAYİSƏSİ:
 * ════════════════════════
 *
 * 1000 event-li aggregate:
 * - Snapshot yox:   1000 event oxu + 1000 apply = ~100ms
 * - Snapshot var:    1 snapshot oxu + 100 apply  = ~15ms  (6.5x sürətli!)
 *
 * 10,000 event-li aggregate:
 * - Snapshot yox:   10,000 event oxu + 10,000 apply = ~1000ms (1 saniyə!)
 * - Snapshot var:    1 snapshot oxu + 100 apply     = ~15ms   (66x sürətli!)
 *
 * ═══════════════════════════════════════════════════════════════
 * CƏDVƏLİN STRUKTURU (snapshots cədvəli)
 * ═══════════════════════════════════════════════════════════════
 *
 * | Sütun          | Tip       | İzah                          |
 * |----------------|-----------|-------------------------------|
 * | id             | UUID      | Snapshot-un unikal ID-si       |
 * | aggregate_id   | VARCHAR   | Aggregate-in ID-si             |
 * | aggregate_type | VARCHAR   | Aggregate class adı            |
 * | version        | INT       | Bu snapshot-dakı event versiyası|
 * | state          | JSON      | Aggregate-in serialized state-i|
 * | created_at     | TIMESTAMP | Yaradılma vaxtı                |
 *
 * İNDEKS: (aggregate_id, version DESC) — sonuncu snapshot-u sürətli tapmaq üçün
 */
class SnapshotStore
{
    private const TABLE = 'snapshots';

    /** Hər neçə event-dən sonra avtomatik snapshot çəkilsin */
    private const SNAPSHOT_THRESHOLD = 100;

    /**
     * Aggregate üçün sonuncu snapshot-u yüklə.
     *
     * @param string $aggregateId Aggregate ID
     * @return Snapshot|null Snapshot tapıldısa qaytarılır, yoxdursa null
     */
    public function load(string $aggregateId): ?Snapshot
    {
        $row = DB::table(self::TABLE)
            ->where('aggregate_id', $aggregateId)
            ->orderByDesc('version')
            ->first();

        if ($row === null) {
            return null;
        }

        return new Snapshot(
            aggregateId: $row->aggregate_id,
            aggregateType: $row->aggregate_type,
            version: (int) $row->version,
            state: json_decode($row->state, true),
            createdAt: new \DateTimeImmutable($row->created_at),
        );
    }

    /**
     * Yeni snapshot saxla.
     *
     * @param Snapshot $snapshot Saxlanılacaq snapshot
     */
    public function save(Snapshot $snapshot): void
    {
        DB::table(self::TABLE)->insert([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'aggregate_id' => $snapshot->aggregateId,
            'aggregate_type' => $snapshot->aggregateType,
            'version' => $snapshot->version,
            'state' => json_encode($snapshot->state),
            'created_at' => $snapshot->createdAt->format('Y-m-d H:i:s'),
        ]);

        Log::info('Snapshot yaradıldı', [
            'aggregate_id' => $snapshot->aggregateId,
            'aggregate_type' => $snapshot->aggregateType,
            'version' => $snapshot->version,
        ]);
    }

    /**
     * Snapshot çəkmək lazımdırmı yoxla.
     *
     * Qayda: Sonuncu snapshot-dan sonra SNAPSHOT_THRESHOLD (100) yeni event əlavə olubsa → bəli.
     *
     * @param string $aggregateId Aggregate ID
     * @param int $currentVersion Aggregate-in cari versiyası
     * @return bool Snapshot lazımdırmı?
     */
    public function shouldTakeSnapshot(string $aggregateId, int $currentVersion): bool
    {
        $lastSnapshot = $this->load($aggregateId);

        $lastSnapshotVersion = $lastSnapshot?->version ?? 0;

        // Sonuncu snapshot-dan sonra 100+ event əlavə olubsa → snapshot çək
        return ($currentVersion - $lastSnapshotVersion) >= self::SNAPSHOT_THRESHOLD;
    }

    /**
     * Köhnə snapshot-ları sil — disk sahəsi idarəsi.
     *
     * Hər aggregate üçün yalnız sonuncu N snapshot saxlanılır.
     * Köhnə snapshot-lar artıq lazım deyil — yenisi var.
     *
     * @param string $aggregateId Aggregate ID
     * @param int $keepCount Neçə snapshot saxlanılsın (default: 3)
     */
    public function pruneOldSnapshots(string $aggregateId, int $keepCount = 3): void
    {
        // Sonuncu N snapshot-un versiyalarını tap
        $keepVersions = DB::table(self::TABLE)
            ->where('aggregate_id', $aggregateId)
            ->orderByDesc('version')
            ->limit($keepCount)
            ->pluck('version');

        if ($keepVersions->isEmpty()) {
            return;
        }

        // Qalan köhnə snapshot-ları sil
        $deleted = DB::table(self::TABLE)
            ->where('aggregate_id', $aggregateId)
            ->whereNotIn('version', $keepVersions)
            ->delete();

        if ($deleted > 0) {
            Log::info("Köhnə snapshot-lar silindi", [
                'aggregate_id' => $aggregateId,
                'deleted_count' => $deleted,
            ]);
        }
    }
}
