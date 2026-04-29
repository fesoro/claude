<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * ORDER API RESOURCE
 * ==================
 * Sifariş datanı API cavab formatına çevirir.
 *
 * Bu Resource-da iki mühüm konsept var:
 *
 * 1. whenLoaded() — ƏLAQƏLİ DATANI ŞƏRTLI YÜKLƏMƏ
 *    Əgər controller-də eager loading istifadə olunubsa (with('items')),
 *    onda items sahəsi qaytarılır. Əks halda, sahə JSON-dan tamamilə çıxarılır.
 *    Bu N+1 problem-inin qarşısını almağa kömək edir.
 *
 *    Nümunə:
 *    Order::with('items', 'user')->find($id)   → items VƏ user qaytarılır
 *    Order::find($id)                           → items VƏ user qaytarılMIR
 *
 * 2. İÇ İÇƏ (NESTED) RESOURCE-LAR
 *    items sahəsi üçün OrderItemResource istifadə edirik.
 *    Beləliklə hər bir item də öz Resource-u ilə formatlanır.
 *    Bu "composition" prinsipidir — kiçik Resource-ları birləşdiririk.
 */
class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'status' => $this->status,

            /**
             * FORMATLANMIŞ MƏBLƏĞ:
             * Ümumi məbləği obyekt olaraq qaytarırıq (ProductResource-dəki kimi).
             */
            'total_amount' => [
                'amount' => (float) $this->totalAmount,
                'currency' => $this->currency ?? 'USD',
            ],

            /**
             * OrderDTO-da items artıq hazır OrderItemDTO[] array-dir.
             * whenLoaded() Eloquent-ə xasdır — burada birbaşa istifadə edirik.
             */
            'items' => OrderItemResource::collection($this->items),

            /**
             * Ünvan sahələri OrderDTO-da birbaşa mövcuddur.
             */
            'address' => [
                'street' => $this->street,
                'city' => $this->city,
                'zip' => $this->zip,
                'country' => $this->country,
            ],

            'created_at' => $this->createdAt,
        ];
    }
}
