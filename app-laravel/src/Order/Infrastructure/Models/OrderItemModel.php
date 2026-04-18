<?php

declare(strict_types=1);

namespace Src\Order\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * ORDER ITEM MODEL
 * ================
 * Sifariş sətri — Order Aggregate-in daxili Entity-si.
 *
 * DDD-DƏ:
 * OrderItem birbaşa yaradılmır — Order.addItem() vasitəsilə əlavə olunur.
 * Amma Eloquent səviyyəsində ayrı model lazımdır çünki ayrı cədvəldədir.
 *
 * belongsTo RELATION:
 * - $item->order → bu item hansı sifarişə aiddir
 * - $item->product → bu item hansı məhsuldur
 */
class OrderItemModel extends Model
{
    use HasUuids;

    /**
     * Bu model Order bounded context-inin ayrı verilənlər bazasına qoşulur.
     * OrderItem Order Aggregate-inin daxili Entity-sidir, ona görə Order ilə
     * eyni DB-dədir — atomik transaction təmin olunur.
     */
    protected $connection = 'order_db';

    protected $table = 'order_items';

    protected $fillable = [
        'id',
        'order_id',
        'product_id',
        'quantity',
        'price',
        'currency',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'price' => 'decimal:2',
        ];
    }

    public function order()
    {
        return $this->belongsTo(OrderModel::class, 'order_id');
    }

    /*
     * CROSS-CONTEXT RELATION SİLİNDİ: product()
     *
     * Product fərqli bounded context-dədir və fərqli DB-dədir (product_db).
     * Eloquent relation fərqli DB-lər arasında işləmir.
     * Məhsul məlumatını əldə etmək üçün Product context-inin API-si
     * və ya Query Service istifadə olunmalıdır.
     * product_id burada yalnız referans ID kimi saxlanılır.
     */

    /**
     * Sətrin ümumi məbləği: qiymət × say
     */
    public function totalAmount(): float
    {
        return (float) $this->price * $this->quantity;
    }
}
