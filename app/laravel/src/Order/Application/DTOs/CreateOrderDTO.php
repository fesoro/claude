<?php

declare(strict_types=1);

namespace Src\Order\Application\DTOs;

/**
 * CREATE ORDER DTO (Input DTO)
 * =============================
 * Yeni sifariş yaratmaq üçün lazım olan datanı daşıyır.
 *
 * INPUT DTO vs OUTPUT DTO:
 * - INPUT DTO (CreateOrderDTO): Controller-dən Application layer-ə data gətirir.
 *   Müştərinin göndərdiyi datadır (request body).
 *
 * - OUTPUT DTO (OrderDTO): Application layer-dən Controller-ə data qaytarır.
 *   API cavabında göstəriləcək datadır (response body).
 *
 * AXIN:
 * HTTP Request → Controller → CreateOrderDTO → CreateOrderCommand → Handler
 *   Handler → OrderFactory → Order entity → Repository (DB-yə yaz)
 *   Handler → OrderDTO → Controller → HTTP Response
 *
 * @property CreateOrderItemDTO[] $items Sifarişdəki məhsullar
 */
readonly class CreateOrderDTO
{
    /**
     * @param string $userId  Sifarişi verən istifadəçinin ID-si
     * @param string $street  Çatdırılma ünvanı — küçə
     * @param string $city    Çatdırılma ünvanı — şəhər
     * @param string $zip     Çatdırılma ünvanı — poçt kodu
     * @param string $country Çatdırılma ünvanı — ölkə
     * @param CreateOrderItemDTO[] $items Sifarişdəki məhsullar
     */
    public function __construct(
        public string $userId,
        public string $street,
        public string $city,
        public string $zip,
        public string $country,
        public array $items,
    ) {}
}

