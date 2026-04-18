<?php

declare(strict_types=1);

namespace Src\Shared\Application\Middleware;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Src\Shared\Application\Bus\Command;

/**
 * TRANSACTION MIDDLEWARE (Unit of Work Pattern) + LOGGİNG
 * =======================================================
 * Bütün Command əməliyyatını bir database transaction daxilində icra edir
 * və hər transaction-ın başlanğıc, commit, rollback hadisələrini log edir.
 *
 * UNIT OF WORK NƏDİR?
 * - Bir neçə repository əməliyyatını bir transaction-da birləşdirir.
 * - Ya hamısı uğurlu olur, ya da hamısı geri qaytarılır (rollback).
 *
 * NÜMUNƏ:
 * CreateOrderHandler daxilində:
 *   1. Order yaradılır (OrderRepository->save)
 *   2. Stok azaldılır (ProductRepository->updateStock)
 *   3. Outbox event yazılır (OutboxRepository->save)
 *   → Əgər 3-cü addımda xəta olarsa, 1 və 2 də geri qaytarılır!
 *
 * TRANSACTION LOGGİNG NƏYƏ LAZIMDIR?
 * ===================================
 * 1. DEBUGGİNG: Hansı transaction uğursuz oldu? Nəyə görə?
 * 2. PERFORMANS: Hansı transaction yavaşdır? (>500ms uyarısı)
 * 3. MONİTORİNG: Neçə transaction/saniyə? Rollback nisbəti nədir?
 * 4. AUDİT: Kim nə vaxt hansı əməliyyatı icra etdi?
 *
 * LOG FORMATI:
 * [INFO]  Transaction başladı: CreateOrderCommand (txn_id: abc123)
 * [INFO]  Transaction commit: CreateOrderCommand (txn_id: abc123, 112ms)
 * [ERROR] Transaction rollback: CreateOrderCommand (txn_id: abc123, 45ms) — InsufficientStockException
 * [WARN]  Yavaş transaction: CreateOrderCommand (txn_id: abc123, 523ms) — threshold: 500ms
 *
 * Pipeline-da yeri:
 * Command → [Logging] → [Validation] → [TRANSACTION + LOG] → Handler
 */
class TransactionMiddleware implements Middleware
{
    /**
     * Yavaş transaction threshold (millisaniyə).
     * Bu dəyəri aşan transaction-lar WARNING log yaradır.
     * Production-da bu dəyəri monitoring dashboard-da izləyə bilərsən.
     */
    private const SLOW_THRESHOLD_MS = 500;

    public function handle(Command $command, callable $next): mixed
    {
        $commandName = class_basename($command);
        $transactionId = Str::random(8);
        $startTime = microtime(true);

        // Transaction başlanğıcını log et
        Log::info("Transaction başladı: {$commandName}", [
            'txn_id' => $transactionId,
            'command' => $commandName,
            'timestamp' => now()->toISOString(),
        ]);

        try {
            $result = DB::transaction(function () use ($command, $next) {
                return $next($command);
            });

            $durationMs = round((microtime(true) - $startTime) * 1000, 2);

            // Uğurlu commit logu
            Log::info("Transaction commit: {$commandName}", [
                'txn_id' => $transactionId,
                'duration_ms' => $durationMs,
            ]);

            // Yavaş transaction uyarısı
            if ($durationMs > self::SLOW_THRESHOLD_MS) {
                Log::warning("Yavaş transaction: {$commandName}", [
                    'txn_id' => $transactionId,
                    'duration_ms' => $durationMs,
                    'threshold_ms' => self::SLOW_THRESHOLD_MS,
                ]);
            }

            return $result;
        } catch (\Throwable $e) {
            $durationMs = round((microtime(true) - $startTime) * 1000, 2);

            // Rollback logu — xəta detalları ilə
            Log::error("Transaction rollback: {$commandName}", [
                'txn_id' => $transactionId,
                'duration_ms' => $durationMs,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
