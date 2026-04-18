<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * STOK YENİLƏMƏ FORM REQUEST
 * ============================
 * Məhsul stokunu artırmaq və ya azaltmaq üçün validasiya.
 *
 * 'operation' sahəsi 'in:increase,decrease' ilə yoxlanılır —
 * bu, Strategy Pattern ilə uyğundur: Handler-da operation dəyərinə görə
 * müvafiq stok əməliyyatı seçilir.
 */
final class UpdateStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'quantity' => ['required', 'integer', 'min:1'],
            'operation' => ['required', 'string', 'in:increase,decrease'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'quantity.required' => 'Miqdar mütləq daxil edilməlidir.',
            'quantity.integer' => 'Miqdar tam ədəd olmalıdır.',
            'quantity.min' => 'Miqdar minimum :min olmalıdır.',
            'operation.required' => 'Əməliyyat növü mütləq seçilməlidir.',
            'operation.in' => 'Əməliyyat yalnız "increase" (artır) və ya "decrease" (azalt) ola bilər.',
        ];
    }
}
