<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * PAYMENT API RESOURCE
 * ====================
 * Ödəniş datanı API cavab formatına çevirir.
 *
 * Bu Resource-da $this->when() metodunun əsl gücünü görəcəyik:
 * bəzi sahələr yalnız müəyyən statusda göstərilir.
 *
 * Məsələn:
 * - transaction_id → yalnız ödəniş tamamlandıqda (completed) göstərilir
 * - failure_reason → yalnız ödəniş uğursuz olduqda (failed) göstərilir
 *
 * Bu niyə vacibdir?
 * - API cavab təmiz olur — lazımsız null sahələr olmur
 * - Frontend bilir ki, əgər sahə varsa, məna kəsb edir
 * - Təhlükəsizlik — həssas məlumatlar lazımsız yerə göstərilmir
 */
class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,

            /**
             * Məbləğ və valyuta ayrı-ayrı sahə olaraq saxlanılır.
             */
            'amount' => (float) $this->amount,
            'currency' => $this->currency ?? 'USD',

            /**
             * Ödəniş metodu: credit_card, paypal, bank_transfer və s.
             */
            'method' => $this->method,

            /**
             * Ödəniş statusu: pending, processing, completed, failed, refunded
             */
            'status' => $this->status,

            /**
             * ═══════════════════════════════════════════════════════
             * ŞƏRTLI SAHƏLƏR — $this->when() detallı izah
             * ═══════════════════════════════════════════════════════
             *
             * when(ŞƏRT, DƏYƏR) — əgər ŞƏRT true-dursa, sahə JSON-a daxil olur.
             * Əgər ŞƏRT false-dursa, sahə JSON-dan TAMAMILƏ SİLİNİR (null qaytarılmır!).
             *
             * Bu null-dan fərqlidir:
             * - when(false, ...) → sahə JSON-da görünmür: {"id": 1, "status": "pending"}
             * - null versək      → sahə null olaraq görünür: {"id": 1, "transaction_id": null}
             *
             * when() daha təmizdir — frontend bilir ki, sahə varsa dəyəri mənalıdır.
             */

            /**
             * TRANSACTION_ID — yalnız ödəniş tamamlandıqda göstərilir.
             *
             * Niyə? Çünki pending/processing statusda transaction_id hələ yoxdur.
             * Failed statusda isə transaction_id ola bilər, amma mənasızdır.
             * Yalnız "completed" olduqda bu sahənin dəyəri vacibdir.
             */
            'transaction_id' => $this->when(
                $this->status === 'completed',
                $this->transaction_id
            ),

            /**
             * FAILURE_REASON — yalnız ödəniş uğursuz olduqda göstərilir.
             *
             * Məsələn: "Insufficient funds", "Card expired", "Gateway timeout"
             *
             * Uğurlu ödənişdə bu sahəni göstərməyin mənası yoxdur.
             * when() sayəsində yalnız failed statusda JSON-a daxil olur.
             */
            'failure_reason' => $this->when(
                $this->status === 'failed',
                $this->failure_reason
            ),

            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
