<?php

declare(strict_types=1);

namespace Src\Shared\Application\Bus;

/**
 * QUERY (CQRS Pattern)
 * ====================
 * Query — sistemdən data oxumaq üçün göndərilən sorğudur.
 *
 * ƏSAS QAYDALAR:
 * 1. Query heç vaxt datanı dəyişmir (side-effect free).
 * 2. Query həmişə data qaytarır.
 * 3. Query adı sual formasındadır: GetOrder, ListProducts, FindUserByEmail
 *
 * Command vs Query:
 * - CreateOrderCommand → datanı DƏYİŞİR, heç nə qaytarmır
 * - GetOrderQuery → datanı OXUYUR, Order qaytarır
 */
interface Query
{
}
