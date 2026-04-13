<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\ValidMoneyRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * MƏHSUL YARATMA FORM REQUEST
 * ============================
 * Yeni məhsul yaratmaq üçün HTTP sorğusunun validasiyası.
 *
 * Bu Form Request CreateProductCommand-a data göndərilməzdən əvvəl
 * bütün sahələrin düzgünlüyünü yoxlayır.
 *
 * AXIN:
 * HTTP Request → CreateProductRequest (validasiya) → Controller → CreateProductCommand → Handler
 *
 * Əgər validasiya uğursuzdursa, sorğu controller-ə heç çatmır —
 * Laravel avtomatik 422 Unprocessable Entity cavabı qaytarır.
 */
final class CreateProductRequest extends FormRequest
{
    /**
     * İcazə yoxlaması.
     * Hal-hazırda hər kəs məhsul yarada bilər.
     * Gələcəkdə: return $this->user()->isAdmin(); — yalnız admin yarada bilər.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validasiya qaydaları.
     *
     * 'price' sahəsində həm built-in ('numeric') həm də Custom Rule (ValidMoneyRule) var.
     * Built-in rule tipi yoxlayır, Custom Rule biznes qaydasını yoxlayır (müsbət olmalıdır).
     *
     * 'currency' üçün 'in:USD,EUR,AZN' istifadə edirik — bu built-in rule
     * dəyərin verilən siyahıda olub-olmadığını yoxlayır.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3'],
            'price' => ['required', 'numeric', new ValidMoneyRule()],
            'currency' => ['required', 'string', 'in:USD,EUR,AZN'],
            'stock' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Məhsul adı mütləq doldurulmalıdır.',
            'name.min' => 'Məhsul adı minimum :min simvol olmalıdır.',
            'price.required' => 'Qiymət mütləq daxil edilməlidir.',
            'price.numeric' => 'Qiymət ədəd olmalıdır.',
            'currency.required' => 'Valyuta mütləq seçilməlidir.',
            'currency.in' => 'Valyuta yalnız USD, EUR və ya AZN ola bilər.',
            'stock.required' => 'Stok miqdarı mütləq daxil edilməlidir.',
            'stock.integer' => 'Stok miqdarı tam ədəd olmalıdır.',
            'stock.min' => 'Stok miqdarı mənfi ola bilməz.',
        ];
    }
}
