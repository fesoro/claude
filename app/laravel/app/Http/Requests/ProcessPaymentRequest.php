<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\ValidMoneyRule;
use App\Rules\ValidUuidRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * ÖDƏNİŞ EMAL ETMƏ FORM REQUEST
 * ================================
 * Ödəniş sorğusunun validasiyası.
 *
 * Burada bir neçə Custom Rule birlikdə istifadə olunur:
 * - ValidUuidRule: order_id-nin UUID formatında olmasını yoxlayır
 * - ValidMoneyRule: məbləğin müsbət olmasını yoxlayır
 *
 * Custom Rule-ları built-in rule-larla birlikdə istifadə etmək olar.
 * Laravel onları sıra ilə icra edir: əvvəl built-in, sonra custom.
 */
final class ProcessPaymentRequest extends FormRequest
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
            'order_id' => ['required', new ValidUuidRule(), 'exists:orders,id'],
            'amount' => ['required', 'numeric', new ValidMoneyRule()],
            'currency' => ['required', 'string', 'in:USD,EUR,AZN'],
            'payment_method' => ['required', 'string', 'in:credit_card,paypal,bank_transfer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'order_id.required' => 'Sifariş ID-si mütləq daxil edilməlidir.',
            'order_id.exists' => 'Bu sifariş mövcud deyil.',
            'amount.required' => 'Məbləğ mütləq daxil edilməlidir.',
            'amount.numeric' => 'Məbləğ ədəd olmalıdır.',
            'currency.required' => 'Valyuta mütləq seçilməlidir.',
            'currency.in' => 'Valyuta yalnız USD, EUR və ya AZN ola bilər.',
            'payment_method.required' => 'Ödəniş üsulu mütləq seçilməlidir.',
            'payment_method.in' => 'Ödəniş üsulu yalnız credit_card, paypal və ya bank_transfer ola bilər.',
        ];
    }
}
