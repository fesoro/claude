<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Webhook;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * WEBHOOK LOG MODEL
 * =================
 * Hər webhook göndəriminin tarixçəsini saxlayır.
 * Debug və monitoring üçün vacibdir.
 */
class WebhookLogModel extends Model
{
    use HasUuids;

    protected $connection = 'order_db';
    protected $table = 'webhook_logs';

    protected $fillable = [
        'id', 'webhook_id', 'event_type', 'payload',
        'response_code', 'response_body', 'attempt', 'status',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'response_code' => 'integer',
            'attempt' => 'integer',
        ];
    }
}
