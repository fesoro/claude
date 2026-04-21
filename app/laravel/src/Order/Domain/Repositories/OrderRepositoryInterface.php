<?php

declare(strict_types=1);

namespace Src\Order\Domain\Repositories;

use Src\Order\Domain\Entities\Order;
use Src\Order\Domain\ValueObjects\OrderId;

/**
 * ORDER REPOSITORY INTERFACE (Repository Pattern)
 * =================================================
 * Sifarişləri saxlamaq və oxumaq üçün interfeys.
 *
 * REPOSITORY PATTERN NƏDİR?
 * - Domain layer-in DB-ni BİLMƏMƏSİNİ təmin edir.
 * - Domain "mənə sifarişi ver" deyir, necə alınmasını bilmir (MySQL? Redis? File?).
 * - Bu interfeys Domain layer-dadır, implementasiya Infrastructure layer-dadır.
 *
 * NƏYƏ İNTERFEYS?
 * - Dependency Inversion Principle (SOLID-in D-si):
 *   Domain layer (yuxarı) Infrastructure layer-dən (aşağı) asılı OLMAMALIDIR.
 *   Əksinə, Infrastructure Domain-in interfeysinə uyğunlaşmalıdır.
 *
 * - Test yazmaq asanlaşır: real DB əvəzinə InMemoryOrderRepository istifadə edə bilərsən.
 *
 * İMPLEMENTASİYALAR:
 * - EloquentOrderRepository: Laravel Eloquent ilə MySQL-ə yazır (production).
 * - InMemoryOrderRepository: Array-da saxlayır (test üçün).
 */
interface OrderRepositoryInterface
{
    /**
     * Sifarişi ID-sinə görə tap.
     * Tapılmazsa null qaytarır.
     *
     * @param OrderId $orderId Axtarılan sifarişin ID-si
     * @return Order|null Tapılan sifariş və ya null
     */
    public function findById(OrderId $orderId): ?Order;

    /**
     * Sifarişi saxla (yarat və ya yenilə).
     * Əgər bu ID-li sifariş varsa — yenilə, yoxdursa — yarat.
     *
     * Repository saxladıqdan sonra Domain Event-ləri dispatch etməlidir:
     * 1. $order->pullDomainEvents() ilə event-ləri al
     * 2. EventDispatcher ilə event-ləri göndər
     *
     * @param Order $order Saxlanacaq sifariş
     */
    public function save(Order $order): void;

    /**
     * İstifadəçinin bütün sifarişlərini tap.
     * Boş array qaytara bilər (heç sifariş yoxdursa).
     *
     * @param string $userId İstifadəçinin ID-si
     * @return Order[] Sifarişlər siyahısı
     */
    public function findByUserId(string $userId): array;
}
