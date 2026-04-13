<?php

declare(strict_types=1);

namespace Src\Order\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * ORDER ELOQUENT MODEL
 * ====================
 * Sifariş cədvəlinin Eloquent təmsili.
 *
 * ELOQUENT RELATIONS (Əlaqələr):
 * Bu model-də 3 tip relation göstərilir:
 *
 * 1. belongsTo — "Bu sifariş BİR user-ə məxsusdur" (N:1)
 *    $order->user → sifarişin sahibi
 *
 * 2. hasMany — "Bu sifarişin ÇOX item-i var" (1:N)
 *    $order->items → sifariş sətirləri
 *
 * 3. hasOne — "Bu sifarişin BİR ödənişi var" (1:1)
 *    $order->payment → sifarişin ödənişi
 *
 * EAGER LOADING vs LAZY LOADING:
 * - Lazy: $order->items (hər dəfə ayrı SQL sorğusu — N+1 problemi)
 * - Eager: Order::with('items')->get() (bir SQL-də hamısını gətirir)
 * $with property ilə default eager loading təyin edilir.
 */
class OrderModel extends Model
{
    use HasUuids;

    /**
     * Bu model Order bounded context-inin ayrı verilənlər bazasına qoşulur.
     * Sifariş, sifariş sətirləri (OrderItem) və Outbox mesajları hamısı
     * eyni order_db-dədir — çünki onlar eyni Aggregate-ə aiddir və
     * atomik transaction tələb edir.
     */
    protected $connection = 'order_db';

    protected $table = 'orders';

    protected $fillable = [
        'id',
        'user_id',
        'status',
        'total_amount',
        'currency',
        'address_street',
        'address_city',
        'address_zip',
        'address_country',
    ];

    /**
     * $with — bu relation-lar həmişə avtomatik yüklənir (eager loading).
     * Order sorğulandıqda items də gəlir — N+1 problemi olmaz.
     */
    protected $with = ['items'];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
        ];
    }

    /*
     * CROSS-CONTEXT RELATION-LAR SİLİNDİ: user(), payment()
     *
     * DDD-də bounded context-lər arası Eloquent relation QADAĞANDIR.
     * - user() silindi: User fərqli DB-dədir (user_db). İstifadəçi məlumatını
     *   öyrənmək üçün User context-inin API-si istifadə olunmalıdır.
     * - payment() silindi: Payment fərqli DB-dədir (payment_db). Ödəniş
     *   statusunu öyrənmək üçün Payment context-inin API-si istifadə olunmalıdır.
     *
     * Yalnız eyni context daxilindəki relation-lar saxlanılır (items).
     */

    /**
     * hasMany — Order-in ÇOX Item-i var.
     * Foreign key: order_items.order_id → orders.id
     * Bu relation icazəlidir çünki OrderItem eyni bounded context-dədir (Order)
     * və eyni verilənlər bazasındadır (order_db).
     */
    public function items()
    {
        return $this->hasMany(OrderItemModel::class, 'order_id');
    }

    /**
     * Scope — status üzrə filter.
     * OrderModel::status('pending')->get()
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeForUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }
}
