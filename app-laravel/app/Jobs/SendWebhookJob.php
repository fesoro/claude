<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Src\Shared\Infrastructure\Webhook\WebhookLogModel;
use Src\Shared\Infrastructure\Webhook\WebhookModel;
use Src\Shared\Infrastructure\Webhook\WebhookService;

/**
 * SEND WEBHOOK JOB
 * ================
 * Webhook-u async (queue vasitəsilə) göndərir.
 *
 * NƏYƏ ASYNC?
 * - Webhook göndərmə yavaş ola bilər (xarici server cavab verməyə bilər)
 * - İstifadəçi gözləməməlidir
 * - Uğursuz olsa retry mexanizmi var
 *
 * RETRY STRATEGİYASI:
 * $tries = 3, $backoff = [30, 120, 300]
 * 1-ci cəhd uğursuzdursa → 30 saniyə gözlə → 2-ci cəhd
 * 2-ci cəhd uğursuzdursa → 120 saniyə gözlə → 3-cü cəhd
 * 3-cü cəhd uğursuzdursa → failed() çağırılır → log-lanır
 */
class SendWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [30, 120, 300];
    public int $timeout = 30;

    public function __construct(
        private string $webhookId,
        private string $eventType,
        private array $payload,
    ) {}

    public function handle(): void
    {
        $webhook = WebhookModel::find($this->webhookId);

        if (!$webhook || !$webhook->is_active) {
            return;
        }

        $jsonPayload = json_encode([
            'event' => $this->eventType,
            'data' => $this->payload,
            'timestamp' => now()->toIso8601String(),
        ]);

        // HMAC imza yarat
        $signature = WebhookService::generateSignature($jsonPayload, $webhook->secret_key);

        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Webhook-Signature' => $signature,
                    'X-Webhook-Event' => $this->eventType,
                ])
                ->withBody($jsonPayload, 'application/json')
                ->post($webhook->url);

            // Log yarat
            WebhookLogModel::create([
                'webhook_id' => $webhook->id,
                'event_type' => $this->eventType,
                'payload' => $this->payload,
                'response_code' => $response->status(),
                'response_body' => substr($response->body(), 0, 1000),
                'attempt' => $this->attempts(),
                'status' => $response->successful() ? 'success' : 'failed',
            ]);

            if (!$response->successful()) {
                throw new \RuntimeException("Webhook cavab kodu: {$response->status()}");
            }

        } catch (\Throwable $e) {
            Log::error("Webhook göndərmə xətası", [
                'webhook_id' => $webhook->id,
                'url' => $webhook->url,
                'event' => $this->eventType,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
            ]);

            throw $e; // Retry mexanizmi işləsin
        }
    }

    /**
     * Bütün retry cəhdləri bitdikdən sonra çağırılır.
     *
     * EXPONENTIAL BACKOFF NƏDİR?
     * ===========================
     * Hər uğursuz cəhddən sonra gözləmə müddəti artır:
     *   Cəhd 1 → 30 san gözlə  (xarici server bərpa olsun)
     *   Cəhd 2 → 120 san gözlə (daha çox vaxt ver)
     *   Cəhd 3 → 300 san gözlə (son şans)
     *
     * NƏYƏ LİNEAR DEYİL?
     * Linear: 30, 30, 30 — server hələ bərpa olmayıbsa, faydasız sorğu göndərirsən.
     * Exponential: 30, 120, 300 — hər dəfə daha çox vaxt verirsən, serveri "narahat etmirsən".
     *
     * CONSECUTIVE FAILURE TRACKING:
     * Webhook ardıcıl neçə dəfə uğursuz olduğunu izləyirik.
     * Əgər ardıcıl 10 dəfə uğursuz olubsa → webhook-u avtomatik deaktiv edirik.
     * Bu, "dead endpoint"-lərə davamlı sorğu göndərməyin qarşısını alır.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Webhook tamamilə uğursuz oldu (bütün cəhdlər bitdi)", [
            'webhook_id' => $this->webhookId,
            'event' => $this->eventType,
            'error' => $exception->getMessage(),
        ]);

        // Ardıcıl uğursuzluq sayını artır
        $webhook = WebhookModel::find($this->webhookId);

        if (!$webhook) {
            return;
        }

        $consecutiveFailures = ($webhook->consecutive_failures ?? 0) + 1;
        $webhook->update(['consecutive_failures' => $consecutiveFailures]);

        // 10 ardıcıl uğursuzluqdan sonra webhook-u deaktiv et
        // Bu, "dead endpoint" problemi həll edir:
        // - Server bağlanıb, URL dəyişib, və ya müştəri artıq webhook istifadə etmir
        // - Hər event-də faydasız HTTP sorğusu göndərməyin mənası yoxdur
        // - Müştəri webhook-u yenidən aktivləşdirə bilər (admin paneldən)
        if ($consecutiveFailures >= 10) {
            $webhook->update(['is_active' => false]);

            Log::warning("Webhook avtomatik deaktiv edildi — ardıcıl {$consecutiveFailures} uğursuzluq", [
                'webhook_id' => $webhook->id,
                'url' => $webhook->url,
            ]);
        }
    }
}
