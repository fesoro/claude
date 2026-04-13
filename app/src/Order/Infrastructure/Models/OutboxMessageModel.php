<?php

declare(strict_types=1);

namespace Src\Order\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * OUTBOX MESSAGE MODEL
 * ====================
 * Outbox Pattern cədvəlinin Eloquent təmsili.
 *
 * Bu model sayəsində OutboxRepository-də DB::table() əvəzinə
 * Eloquent istifadə edə bilərik — daha təmiz və oxunaqlı kod.
 *
 * SCOPE-LAR:
 * - unpublished() → hələ RabbitMQ-ya göndərilməmiş mesajlar
 * - published() → göndərilmiş mesajlar
 */
class OutboxMessageModel extends Model
{
    use HasUuids;

    /**
     * Bu model Order bounded context-inin verilənlər bazasına qoşulur.
     * Outbox mesajları Order ilə eyni DB-dədir çünki Order yaradılarkən
     * outbox mesajı eyni transaction-da yazılmalıdır (atomiklik).
     * Əgər ayrı DB-də olsaydı, distributed transaction lazım olardı
     * ki, bu da mürəkkəblik və performans problemi yaradardı.
     */
    protected $connection = 'order_db';

    protected $table = 'outbox_messages';

    public $timestamps = false; // created_at manual idarə olunur

    protected $fillable = [
        'id',
        'event_type',
        'routing_key',
        'payload',
        'created_at',
        'published_at',
        'retry_count',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',       // JSON ↔ PHP array avtomatik çevrilmə
            'created_at' => 'datetime',
            'published_at' => 'datetime',
            'retry_count' => 'integer',
        ];
    }

    /**
     * Hələ göndərilməmiş mesajlar.
     * OutboxPublisher bu scope-u istifadə edir.
     */
    public function scopeUnpublished($query)
    {
        return $query->whereNull('published_at');
    }

    public function scopePublished($query)
    {
        return $query->whereNotNull('published_at');
    }

    /**
     * Mesajı göndərilmiş kimi işarələ.
     */
    public function markAsPublished(): void
    {
        $this->update(['published_at' => now()]);
    }
}
