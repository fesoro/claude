<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * FORGOT PASSWORD REQUEST
 * =======================
 * Şifrə sıfırlama sorğusu üçün validasiya.
 * Yalnız email tələb olunur — bu emailə reset link göndəriləcək.
 */
class ForgotPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Hər kəs şifrə sıfırlama istəyə bilər
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'exists:user_db.domain_users,email'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.exists' => 'Bu email ünvanı ilə istifadəçi tapılmadı.',
        ];
    }
}
