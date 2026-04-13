<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\ValidOrderStatusRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * SİFARİŞ STATUSU YENİLƏMƏ FORM REQUEST
 * ========================================
 * Sifariş statusunu dəyişdirmək üçün validasiya.
 * Custom Rule (ValidOrderStatusRule) ilə statusun düzgünlüyü yoxlanılır.
 */
final class UpdateOrderStatusRequest extends FormRequest
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
            'status' => ['required', 'string', new ValidOrderStatusRule()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.required' => 'Sifariş statusu mütləq daxil edilməlidir.',
        ];
    }
}
