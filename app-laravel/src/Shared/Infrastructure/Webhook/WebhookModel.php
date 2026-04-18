<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Webhook;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * WEBHOOK MODEL
 * =============
 * Webhook abunəliklərini təmsil edir.
 *
 * Hər webhook-un:
 * - url: Hadisə baş verəndə POST göndəriləcək URL
 * - events: Dinlənilən hadisə tipləri (JSON array)
 * - secret_key: HMAC imzalama üçün gizli açar
 * - is_active: Aktiv/deaktiv
 */
class WebhookModel extends Model
{
    use HasUuids;

    protected $connection = 'order_db';
    protected $table = 'webhooks';

    protected $fillable = [
        'id', 'user_id', 'url', 'events', 'secret_key', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'events' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function logs()
    {
        return $this->hasMany(WebhookLogModel::class, 'webhook_id');
    }

    /**
     * Bu webhook verilən event-i dinləyirmi?
     */
    public function listensTo(string $eventType): bool
    {
        return in_array($eventType, $this->events ?? []);
    }
}
