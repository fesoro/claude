<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\Shared\Infrastructure\Auth\TwoFactorService;

/**
 * TWO-FACTOR AUTHENTICATION CONTROLLER
 * =====================================
 * 2FA aktivləşdirmə, deaktiv etmə və doğrulama endpoint-ləri.
 *
 * 2FA AXINI:
 * ══════════
 * AKTİVLƏŞDİRMƏ:
 * 1. POST /api/auth/2fa/enable → Secret yaradılır, QR kod URL qaytarılır
 * 2. İstifadəçi QR kodu Google Authenticator ilə scan edir
 * 3. POST /api/auth/2fa/confirm → 6 rəqəmli kodla doğrulayır
 * 4. 2FA aktiv olur, backup kodlar qaytarılır
 *
 * GİRİŞ (2FA aktiv):
 * 1. POST /api/auth/login → email + şifrə → "2FA kod tələb olunur" cavabı
 * 2. POST /api/auth/2fa/verify → 6 rəqəmli kod → token qaytarılır
 *
 * BACKUP KOD İSTİFADƏSİ:
 * Telefon itirsə: POST /api/auth/2fa/verify-backup → backup kod → token
 */
class TwoFactorController extends Controller
{
    public function __construct(
        private TwoFactorService $twoFactorService,
    ) {}

    /**
     * POST /api/auth/2fa/enable
     * 2FA aktivləşdirmə prosesini başlat.
     * Secret yaradılır və QR kod URL qaytarılır.
     * Hələ aktiv deyil — confirm endpoint-i ilə doğrulanmalıdır.
     */
    public function enable(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->two_factor_enabled) {
            return ApiResponse::error('2FA artıq aktivdir', code: 400);
        }

        // Secret yarat və DB-yə yaz (hələ enabled=false)
        $secret = $this->twoFactorService->generateSecret();
        $user->update(['two_factor_secret' => $secret]);

        // QR kod URL-i yarat
        $qrUrl = $this->twoFactorService->generateQrCodeUrl($user->email, $secret);

        return ApiResponse::success(
            data: [
                'secret' => $secret,     // Manual daxil etmək üçün
                'qr_url' => $qrUrl,      // QR kod generator-a ötürmək üçün
            ],
            message: 'QR kodu Google Authenticator ilə scan edin, sonra /2fa/confirm endpoint-inə kodu göndərin'
        );
    }

    /**
     * POST /api/auth/2fa/confirm
     * 2FA-nı ilk dəfə doğrula və aktivləşdir.
     * İstifadəçi QR kodu scan edib, ilk kodu göndərir.
     * Düzgündürsə → 2FA aktiv olur + backup kodlar verilir.
     */
    public function confirm(Request $request): JsonResponse
    {
        $request->validate(['code' => 'required|string|size:6']);

        $user = $request->user();

        if (!$user->two_factor_secret) {
            return ApiResponse::error('Əvvəlcə /2fa/enable çağırın', code: 400);
        }

        // Kodu yoxla
        if (!$this->twoFactorService->verifyCode($user->two_factor_secret, $request->input('code'))) {
            return ApiResponse::error('Kod yanlışdır', code: 422);
        }

        // Backup kodlar yarat
        $backupCodes = $this->twoFactorService->generateBackupCodes();

        // 2FA aktiv et
        $user->update([
            'two_factor_enabled' => true,
            'two_factor_backup_codes' => $backupCodes,
        ]);

        return ApiResponse::success(
            data: [
                'backup_codes' => $backupCodes,
            ],
            message: '2FA aktivləşdirildi! Backup kodları təhlükəsiz yerdə saxlayın — bir daha göstərilməyəcək!'
        );
    }

    /**
     * POST /api/auth/2fa/verify
     * Login zamanı 2FA kodunu doğrula.
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|string',
            'code' => 'required|string|size:6',
        ]);

        $user = \Src\User\Infrastructure\Models\UserModel::findOrFail($request->input('user_id'));

        if (!$user->two_factor_enabled) {
            return ApiResponse::error('2FA aktiv deyil', code: 400);
        }

        if (!$this->twoFactorService->verifyCode($user->two_factor_secret, $request->input('code'))) {
            return ApiResponse::error('Kod yanlışdır', code: 422);
        }

        // Token yarat
        $token = $user->createToken('auth_token_2fa')->plainTextToken;

        return ApiResponse::success(
            data: ['token' => $token, 'token_type' => 'Bearer'],
            message: '2FA doğrulama uğurlu'
        );
    }

    /**
     * POST /api/auth/2fa/verify-backup
     * Backup kod ilə doğrula (telefon itdikdə).
     */
    public function verifyBackup(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|string',
            'backup_code' => 'required|string',
        ]);

        $user = \Src\User\Infrastructure\Models\UserModel::findOrFail($request->input('user_id'));

        $remainingCodes = $this->twoFactorService->verifyBackupCode(
            $user->two_factor_backup_codes ?? [],
            $request->input('backup_code'),
        );

        if ($remainingCodes === null) {
            return ApiResponse::error('Backup kod yanlışdır', code: 422);
        }

        // İstifadə olunmuş kodu sil
        $user->update(['two_factor_backup_codes' => $remainingCodes]);

        $token = $user->createToken('auth_token_backup')->plainTextToken;

        return ApiResponse::success(
            data: [
                'token' => $token,
                'token_type' => 'Bearer',
                'remaining_backup_codes' => count($remainingCodes),
            ],
            message: 'Backup kod ilə doğrulama uğurlu. Qalan backup kod sayı: ' . count($remainingCodes)
        );
    }

    /**
     * POST /api/auth/2fa/disable
     * 2FA deaktiv et.
     */
    public function disable(Request $request): JsonResponse
    {
        $request->validate(['code' => 'required|string|size:6']);

        $user = $request->user();

        if (!$user->two_factor_enabled) {
            return ApiResponse::error('2FA artıq deaktivdir', code: 400);
        }

        if (!$this->twoFactorService->verifyCode($user->two_factor_secret, $request->input('code'))) {
            return ApiResponse::error('Kod yanlışdır — 2FA deaktiv etmək üçün düzgün kod tələb olunur', code: 422);
        }

        $user->update([
            'two_factor_secret' => null,
            'two_factor_enabled' => false,
            'two_factor_backup_codes' => null,
        ]);

        return ApiResponse::success(message: '2FA deaktiv edildi');
    }
}
