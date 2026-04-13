<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * RESET PASSWORD REQUEST
 * ======================
 * Yeni şifrə təyin etmə sorğusu üçün validasiya.
 * Token + email + yeni şifrə tələb olunur.
 *
 * AXIN:
 * 1. İstifadəçi emailindəki linkə klikləyir (link-də token var)
 * 2. Frontend token-i bu endpoint-ə göndərir
 * 3. Server token-i yoxlayır, şifrəni dəyişir
 */
class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'exists:user_db.domain_users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
