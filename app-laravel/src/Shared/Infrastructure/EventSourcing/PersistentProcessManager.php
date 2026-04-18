<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\EventSourcing;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * PERSISTENT PROCESS MANAGER — DB-yə Persist Olunan Saga State
 * ===============================================================
 *
 * PROBLEMİ ANLAYAQ:
 * =================
 * OrderFulfillmentProcessManager artıq var, amma state-i in-memory-dir.
 * Əgər server restart olsa və ya worker çöksə — prosesin harada olduğu itir!
 *
 * Nümunə:
 * 1. Sifariş yaradıldı → state = PAYMENT_PENDING (in-memory)
 * 2. Server restart oldu → state İTDİ
 * 3. PaymentCompleted event gəlir → Process Manager bilmir ki, proses var idi
 * 4. Ödəniş oldu amma sifariş statusu yenilənmədi → DATA UYĞUNSUZLUĞU!
 *
 * HƏLLİ — PERSİSTENT STATE:
 * ==========================
 * State-i DB-yə yazırıq. Server restart olsa belə, proses davam edə bilər.
 * Bu, "durable saga" və ya "persistent process manager" adlanır.
 *
 * ANALOGİYA:
 * ===========
 * In-memory saga = Kağıza yazmadan başdan danışmaq. Telefon sönsə — unuduldu.
 * Persistent saga = Hər addımı dəftərə yazmaq. Telefon sönsə — dəftərdən davam et.
 *
 * WORKFLOW ENGİNE-LƏR:
 * =====================
 * Real production-da bu pattern-i Temporal, Camunda, AWS Step Functions kimi
 * workflow engine-lər həll edir. Onlar:
 * - State-i avtomatik persist edir.
 * - Timeout-ları idarə edir.
 * - Retry/compensation-ı built-in edir.
 * - Visual workflow designer verir.
 *
 * Bu class həmin engine-lərin ƏSAS mexanizmini göstərir — DB persist + state machine.
 */
class PersistentProcessManager
{
    private const TABLE = 'process_manager_states';

    public function __construct(
        private readonly string $connection = 'sqlite',
    ) {}

    /**
     * PROSES YARAT VƏ YA MÖVCUD PROSESİ YÜKLƏ
     * ===========================================
     * Əgər bu aggregate üçün proses varsa — DB-dən yükləyir.
     * Yoxdursa — yeni proses yaradır.
     *
     * @param string $aggregateId Sifariş ID-si
     * @param string $processType Process Manager class adı
     * @param string $initialState Başlanğıc vəziyyəti
     *
     * @return array Proses state datası
     */
    public function loadOrCreate(string $aggregateId, string $processType, string $initialState = 'initiated'): array
    {
        $existing = DB::connection($this->connection)
            ->table(self::TABLE)
            ->where('aggregate_id', $aggregateId)
            ->where('process_type', $processType)
            ->first();

        if ($existing !== null) {
            Log::info('Process Manager: mövcud proses yükləndi', [
                'aggregate_id' => $aggregateId,
                'process_type' => $processType,
                'state' => $existing->state,
            ]);

            return [
                'id'              => $existing->id,
                'aggregate_id'    => $existing->aggregate_id,
                'process_type'    => $existing->process_type,
                'state'           => $existing->state,
                'process_data'    => json_decode($existing->process_data ?? '{}', true),
                'completed_steps' => json_decode($existing->completed_steps ?? '[]', true),
                'last_event_at'   => $existing->last_event_at,
            ];
        }

        // Yeni proses yarat
        $id = uuid_create();

        DB::connection($this->connection)->table(self::TABLE)->insert([
            'id'              => $id,
            'aggregate_id'    => $aggregateId,
            'process_type'    => $processType,
            'state'           => $initialState,
            'process_data'    => json_encode([]),
            'completed_steps' => json_encode([]),
            'last_event_at'   => now(),
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        Log::info('Process Manager: yeni proses yaradıldı', [
            'id' => $id,
            'aggregate_id' => $aggregateId,
            'state' => $initialState,
        ]);

        return [
            'id'              => $id,
            'aggregate_id'    => $aggregateId,
            'process_type'    => $processType,
            'state'           => $initialState,
            'process_data'    => [],
            'completed_steps' => [],
            'last_event_at'   => now()->toDateTimeString(),
        ];
    }

    /**
     * STATE-İ YENİLƏ (Transition)
     * =============================
     * Prosesin vəziyyətini dəyişir və DB-yə yazır.
     *
     * @param string $aggregateId    Aggregate ID
     * @param string $processType    Process tipi
     * @param string $newState       Yeni vəziyyət
     * @param array  $processData    Yenilənmiş data
     * @param array  $completedSteps Tamamlanmış addımlar
     */
    public function transition(
        string $aggregateId,
        string $processType,
        string $newState,
        array $processData = [],
        array $completedSteps = [],
    ): void {
        $updated = DB::connection($this->connection)
            ->table(self::TABLE)
            ->where('aggregate_id', $aggregateId)
            ->where('process_type', $processType)
            ->update([
                'state'           => $newState,
                'process_data'    => json_encode($processData),
                'completed_steps' => json_encode($completedSteps),
                'last_event_at'   => now(),
                'updated_at'      => now(),
            ]);

        if ($updated === 0) {
            Log::warning('Process Manager: transition uğursuz — proses tapılmadı', [
                'aggregate_id' => $aggregateId,
                'process_type' => $processType,
                'new_state' => $newState,
            ]);
            return;
        }

        Log::info('Process Manager: state keçidi persist olundu', [
            'aggregate_id' => $aggregateId,
            'new_state' => $newState,
        ]);
    }

    /**
     * TIMEOUT OLMUŞ PROSESLƏRİ TAP
     * ===============================
     * Müəyyən müddət ərzində event almamış prosesləri tapır.
     * Bunlar "asılıb qalmış" proseslərdir — admin müdaxiləsi lazımdır.
     *
     * Nümunə: Ödəniş 30 dəqiqədir gəlmir → timeout.
     * Admin-ə alert göndərilir, proses manual olaraq həll olunur.
     *
     * @param int $timeoutMinutes Neçə dəqiqədən sonra timeout sayılsın
     */
    public function findTimedOut(int $timeoutMinutes = 30): array
    {
        $cutoff = now()->subMinutes($timeoutMinutes);

        return DB::connection($this->connection)
            ->table(self::TABLE)
            ->whereNotIn('state', ['completed', 'failed'])
            ->where('last_event_at', '<', $cutoff)
            ->get()
            ->toArray();
    }

    /**
     * TAMAMLANMIŞ PROSESLƏRİ TƏMİZLƏ (Cleanup)
     * Köhnə completed/failed prosesləri silir.
     *
     * @param int $retentionDays Neçə gün saxlanılsın
     */
    public function cleanup(int $retentionDays = 30): int
    {
        $cutoff = now()->subDays($retentionDays);

        return DB::connection($this->connection)
            ->table(self::TABLE)
            ->whereIn('state', ['completed', 'failed'])
            ->where('updated_at', '<', $cutoff)
            ->delete();
    }
}
