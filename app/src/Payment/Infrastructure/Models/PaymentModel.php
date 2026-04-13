<?php

declare(strict_types=1);

namespace Src\Payment\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * PAYMENT ELOQUENT MODEL
 * ======================
 * Ödəniş cədvəlinin Eloquent təmsili.
 *
 * ƏLAVƏ CASTS:
 * 'amount' => 'decimal:2' — DB-dən gələn string "29.99" avtomatik float olur.
 */
class PaymentModel extends Model
{
    use HasUuids;

    /**
     * Bu model Payment bounded context-inin ayrı verilənlər bazasına qoşulur.
     * Ödəniş datası həssas maliyyə məlumatıdır — ayrı DB-də saxlamaq
     * təhlükəsizlik, audit və compliance baxımından vacibdir.
     * Digər context-lər ödəniş məlumatlarına yalnız Payment API vasitəsilə çatır.
     */
    protected $connection = 'payment_db';

    protected $table = 'payments';

    protected $fillable = [
        'id',
        'order_id',
        'amount',
        'currency',
        'method',
        'status',
        'transaction_id',
        'failure_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    /*
     * CROSS-CONTEXT RELATION SİLİNDİ: order()
     *
     * Order fərqli bounded context-dədir və fərqli DB-dədir (order_db).
     * Eloquent relation fərqli verilənlər bazaları arasında işləmir.
     * Sifariş məlumatını öyrənmək üçün Order context-inin API-si
     * istifadə olunmalıdır. order_id burada yalnız referans ID kimi saxlanılır.
     */

    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeForOrder($query, string $orderId)
    {
        return $query->where('order_id', $orderId);
    }

    public function isSuccessful(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }
}
