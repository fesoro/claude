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
            'user_id' => $this->user_id,
            'status' => $this->status,

            /**
             * FORMATLANMIŞ MƏBLƏĞ:
             * Ümumi məbləği obyekt olaraq qaytarırıq (ProductResource-dəki kimi).
             */
            'total_amount' => [
                'amount' => (float) $this->total_amount,
                'currency' => $this->currency ?? 'USD',
            ],

            /**
             * whenLoaded() İSTİFADƏSİ:
             * ────────────────────────
             * $this->whenLoaded('items') yoxlayır ki, 'items' əlaqəsi
             * eager load edilib ya yox.
             *
             * Əgər edilib → OrderItemResource::collection() ilə formatlanır
             * Əgər edilməyib → bu sahə JSON cavabdan tamamilə silinir (null yox, yoxdur!)
             *
             * Bu niyə vacibdir?
             * - Siyahı endpoint-ində (GET /orders) items lazım deyil → yükləmirik
             * - Detal endpoint-ində (GET /orders/1) items lazımdır → with('items') ilə yükləyirik
             * - Eyni Resource hər iki halda işləyir!
             */
            'items' => OrderItemResource::collection($this->whenLoaded('items')),

            /**
             * İÇ İÇƏ OBYEKTLƏRİN ŞƏRTLI GÖSTƏRİLMƏSİ:
             * Ünvan (address) əlaqəsi yüklənibsə, iç içə obyekt olaraq qaytarırıq.
             * Burada ayrı Resource yox, birbaşa array istifadə edirik — çünki
             * address sadə bir obyektdir, mürəkkəb formatlama lazım deyil.
             */
            'address' => $this->when($this->relationLoaded('address'), function () {
                return [
                    'street' => $this->address->street ?? null,
                    'city' => $this->address->city ?? null,
                    'zip' => $this->address->zip ?? null,
                    'country' => $this->address->country ?? null,
                ];
            }),

            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
