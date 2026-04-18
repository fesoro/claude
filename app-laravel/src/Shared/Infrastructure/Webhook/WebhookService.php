<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Webhook;

use App\Jobs\SendWebhookJob;

/**
 * WEBHOOK SERVICE
 * ===============
 * Event baş verəndə müvafiq webhook-ları tapıb göndərmə job-unu dispatch edir.
 *
 * WEBHOOK NƏDİR?
 * Webhook — "tərsinə API çağırışı"dır. Normal API-da SƏN server-ə sorğu göndərirsən.
 * Webhook-da SERVER SƏNƏ sorğu göndərir — hadisə baş verəndə.
 *
 * NÜMUNƏ:
 * 1. Müştəri webhook qeydiyyat edir: url=https://erp.com/callback, events=["order.created"]
 * 2. Sifariş yaradılır → WebhookService::dispatch("order.created", $data)
 * 3. Service webhook-u tapır → SendWebhookJob dispatch edir (async)
 * 4. Job POST sorğusu göndərir → https://erp.com/callback
 * 5. Payload HMAC ilə imzalanır → müştəri imzanı yoxlaya bilər (təhlükəsizlik)
 *
 * HMAC İMZA NƏDİR?
 * HMAC (Hash-based Message Authentication Code) — mesajın bütövlüyünü və mənbəyini
 * yoxlamaq üçün istifadə olunan kriptoqrafik imzadır.
 *
 * AXIN:
 * 1. Server: payload + secret_key → HMAC-SHA256 → imza yaradır
 * 2. Server: imzanı X-Webhook-Signature header-ində göndərir
 * 3. Müştəri: eyni payload + öz secret_key ilə HMAC hesablayır
 * 4. Müştəri: öz hesabladığı ilə header-dəki imzanı müqayisə edir
 * 5. Eynidirsə → mesaj etibarlıdır, dəyişdirilməyib
 *
 * BU NƏYƏ LAZIMDIR?
 * Əgər birisi saxta POST göndərsə, secret_key bilmədən düzgün imza yarada bilməz.
 */
class WebhookService
{
    /**
     * Verilən event tipinə abunə olan bütün webhook-lara göndərmə job-u dispatch et.
     */
    public function dispatch(string $eventType, array $payload): void
    {
        $webhooks = WebhookModel::where('is_active', true)->get();

        foreach ($webhooks as $webhook) {
            if ($webhook->listensTo($eventType)) {
                SendWebhookJob::dispatch(
                    webhookId: $webhook->id,
                    eventType: $eventType,
                    payload: $payload,
                );
            }
        }
    }

    /**
     * HMAC-SHA256 imza yarat.
     */
    public static function generateSignature(string $payload, string $secret): string
    {
        return hash_hmac('sha256', $payload, $secret);
    }
}
