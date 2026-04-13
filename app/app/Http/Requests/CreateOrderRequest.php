<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\ValidUuidRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * SİFARİŞ YARATMA FORM REQUEST
 * ==============================
 * Bu Form Request mürəkkəb (nested/iç-içə) data strukturunu validasiya edir.
 *
 * İÇ-İÇƏ MASİV VALİDASİYASI (Nested Array Validation):
 * ======================================================
 * Bəzən sorğu datası düz (flat) deyil, iç-içə (nested) olur:
 *
 * {
 *   "user_id": "uuid",
 *   "items": [
 *     { "product_id": "uuid", "quantity": 2, "price": 29.99 },
 *     { "product_id": "uuid", "quantity": 1, "price": 49.99 }
 *   ],
 *   "address": { "street": "...", "city": "...", "zip": "...", "country": "..." }
 * }
 *
 * Laravel-də iç-içə sahələri "nöqtə notasiyası" (dot notation) ilə yoxlayırıq:
 *
 * 1. MASİV İÇİNDƏKİ HƏLƏR (items.*):
 *    'items' => ['required', 'array', 'min:1']
 *      → items massiv olmalıdır və minimum 1 element olmalıdır
 *
 *    'items.*.product_id' => ['required', 'exists:products,id']
 *      → items massivinin HƏR BİR elementinin product_id sahəsi olmalıdır
 *      → * (ulduz) simvolu "hər bir element" deməkdir
 *      → Əgər items-da 5 element varsa, hər birinin product_id-si yoxlanılır
 *
 *    'items.*.quantity' => ['required', 'integer', 'min:1']
 *      → hər elementin quantity-si tam ədəd və minimum 1 olmalıdır
 *
 * 2. OBYEKTİN SAHƏLƏRİ (address.street):
 *    'address.street' => ['required', 'string']
 *      → address obyektinin street sahəsi mütləq olmalıdır
 *
 *    Bu, JS-dəki address.street ilə eyni məntiqdir — obyektin daxili sahəsinə istinad.
 *
 * NÖQTƏ NOTASİYASI QƏLİBLƏRİ:
 * - 'items'          → items massivinin özü
 * - 'items.*'        → items-ın hər bir elementi
 * - 'items.*.field'  → hər elementin field sahəsi
 * - 'address.city'   → address obyektinin city sahəsi
 * - 'items.0.price'  → yalnız ilk elementin price-ı (nadir istifadə)
 */
final class CreateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Sifariş yaratma qaydaları.
     *
     * 'exists:products,id' — verilənlər bazasında products cədvəlinin id sütununda
     * bu dəyərin mövcud olub-olmadığını yoxlayır. Yəni, mövcud olmayan məhsula
     * sifariş vermək mümkün deyil.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // İstifadəçi ID-si — UUID formatında olmalı, domain_users cədvəlində olmalıdır
            'user_id' => ['required', new ValidUuidRule(), 'exists:domain_users,id'],

            // Sifariş elementləri — minimum 1 element olmalıdır
            'items' => ['required', 'array', 'min:1'],

            // Hər elementin product_id-si — products cədvəlində mövcud olmalıdır
            'items.*.product_id' => ['required', 'exists:products,id'],

            // Hər elementin miqdarı — tam ədəd, minimum 1
            'items.*.quantity' => ['required', 'integer', 'min:1'],

            // Hər elementin qiyməti — ədəd, minimum 0.01 (pulsuz məhsul ola bilməz)
            'items.*.price' => ['required', 'numeric', 'min:0.01'],

            // Ünvan sahələri — hamısı mütləqdir
            'address.street' => ['required', 'string'],
            'address.city' => ['required', 'string'],
            'address.zip' => ['required', 'string'],
            'address.country' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_id.required' => 'İstifadəçi ID-si mütləq daxil edilməlidir.',
            'user_id.exists' => 'Bu istifadəçi mövcud deyil.',
            'items.required' => 'Sifariş elementləri mütləq daxil edilməlidir.',
            'items.min' => 'Sifarişdə minimum :min element olmalıdır.',
            'items.*.product_id.required' => 'Hər elementin məhsul ID-si olmalıdır.',
            'items.*.product_id.exists' => 'Bu məhsul mövcud deyil.',
            'items.*.quantity.required' => 'Hər elementin miqdarı daxil edilməlidir.',
            'items.*.quantity.min' => 'Miqdar minimum :min olmalıdır.',
            'items.*.price.required' => 'Hər elementin qiyməti daxil edilməlidir.',
            'items.*.price.min' => 'Qiymət minimum :min olmalıdır.',
            'address.street.required' => 'Küçə ünvanı mütləq daxil edilməlidir.',
            'address.city.required' => 'Şəhər mütləq daxil edilməlidir.',
            'address.zip.required' => 'Poçt indeksi mütləq daxil edilməlidir.',
            'address.country.required' => 'Ölkə mütləq daxil edilməlidir.',
        ];
    }
}
