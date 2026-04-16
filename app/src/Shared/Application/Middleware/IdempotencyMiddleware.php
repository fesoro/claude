<?php

declare(strict_types=1);

namespace Src\Shared\Application\Middleware;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Src\Shared\Application\Bus\Command;

/**
 * COMMAND IDEMPOTENCY MIDDLEWARE — Command Səviyyəsində Dublikat Qoruması
 * =========================================================================
 *
 * HTTP IDEMPOTENCY vs COMMAND IDEMPOTENCY:
 * ==========================================
 * HTTP IdempotencyMiddleware (app/Http/Middleware/) artıq var:
 *   → HTTP request səviyyəsində işləyir.
 *   → X-Idempotency-Key header-i ilə dublikatı yoxlayır.
 *   → Controller-ə çatmamış dublikat sorğunu bloklayır.
 *
 * Bu Command IdempotencyMiddleware (BU FAYL):
 *   → CommandBus middleware pipeline-ında işləyir.
 *   → Command-ın özündəki idempotency key ilə dublikatı yoxlayır.
 *   → Handler-ə çatmamış dublikat command-ı bloklayır.
 *
 * NƏYƏ HƏR İKİSİ LAZIMDIR?
 * ==========================
 * HTTP middleware yalnız HTTP sorğularını qoruyur.
 * Amma command-lar HTTP-dən başqa yerlərdən də gələ bilər:
 * - Queue worker (RabbitMQ-dan gələn event → command dispatch edir)
 * - Cron job (scheduled task → command dispatch edir)
 * - Console command (artisan command → command dispatch edir)
 * - Saga/Process Manager (event → command dispatch edir)
 *
 * Bu hallarda HTTP middleware işləmir! Command middleware lazımdır.
 *
 * COMMAND-DA IDEMPOTENCY KEY:
 * ============================
 * Command class-a `idempotencyKey()` metodu əlavə olunur.
 * Bu metod command-ın unikal identifikatorunu qaytarır.
 *
 * Nümunə:
 * class ProcessPaymentCommand implements Command {
 *     public function idempotencyKey(): string {
 *         return "payment:{$this->orderId}:{$this->amount}";
 *     }
 * }
 *
 * Eyni sifariş üçün eyni məbləğdə iki ödəniş command-ı gəlsə,
 * ikincisi avtomatik bloklanır.
 *
 * PİPELINE-DAKI YERİ:
 * ====================
 * Command → [Logging] → [Idempotency] → [Validation] → [Transaction] → Handler
 *
 * Logging-dən SONRA — çünki dublikat cəhdi də log olunmalıdır.
 * Validation-dan ƏVVƏL — çünki dublikatı əvvəlcə bloklayıb, sonra validate etmək daha sürətlidir.
 */
class IdempotencyMiddleware implements Middleware
{
    /**
     * Command idempotency key-in cache müddəti (saniyə).
     * 3600 = 1 saat. Bu müddət ərzində eyni key ilə command bloklanır.
     */
    private const CACHE_TTL_SECONDS = 3600;

    /**
     * Cache prefix — digər cache key-lərlə toqquşmanın qarşısını alır.
     */
    private const CACHE_PREFIX = 'cmd_idempotency:';

    public function handle(Command $command, callable $next): mixed
    {
        /**
         * Command-ın idempotencyKey() metodu varmı yoxla.
         * Əgər yoxdursa — bu command idempotency dəstəkləmir, keç.
         * Bütün command-lara idempotency MƏCBUR deyil — yalnız kritik olanlara.
         */
        if (!method_exists($command, 'idempotencyKey')) {
            return $next($command);
        }

        $key = self::CACHE_PREFIX . $command->idempotencyKey();

        /**
         * Bu key artıq cache-dədir?
         * Bəli → bu command artıq icra olunub, dublikatdır.
         * Xeyr → ilk dəfədir, icra et.
         */
        if (Cache::has($key)) {
            $commandName = get_class($command);

            Log::info("Command dublikat bloklandi: {$commandName}", [
                'idempotency_key' => $command->idempotencyKey(),
            ]);

            /**
             * Dublikat command üçün nə qaytarmalı?
             * İki yanaşma var:
             * 1. null qaytar — "heç nə etmə" (bu yanaşma).
             * 2. Exception at — "xəta bildir".
             *
             * null qaytarırıq çünki klient üçün idempotent davranış
             * "eyni nəticə" deməkdir, xəta deyil.
             * Stripe də eyni yanaşmanı istifadə edir.
             */
            return null;
        }

        // Command-ı icra et
        $result = $next($command);

        /**
         * Uğurlu icra-dan sonra key-i cache-ə yaz.
         * Bu müddət ərzində eyni key ilə gələn command bloklanacaq.
         *
         * NƏYƏ İCRA-DAN SONRA YAZIRIQ, ƏVVƏL DEYIL?
         * Əgər əvvəl yazsaq və handler exception atsa:
         * → Key cache-dədir, amma əməliyyat olmayıb.
         * → Yenidən cəhd etmək mümkün olmaz — key bloklanıb.
         * → Bu, "phantom idempotency" problemidir.
         *
         * İcra-dan sonra yazmaqla yalnız UĞURLU əməliyyatlar qeydə alınır.
         * Uğursuz olanlar yenidən cəhd oluna bilər.
         */
        Cache::put($key, true, self::CACHE_TTL_SECONDS);

        return $result;
    }
}
