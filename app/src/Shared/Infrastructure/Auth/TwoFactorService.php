<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Auth;

/**
 * TWO-FACTOR AUTHENTICATION (2FA) SERVİS
 * ========================================
 * TOTP (Time-based One-Time Password) əsaslı iki addımlı doğrulama.
 *
 * 2FA NƏDİR?
 * Normal giriş: email + şifrə (1 faktor — "nə bilirsən")
 * 2FA giriş: email + şifrə + 6 rəqəmli kod (2 faktor — "nə bilirsən" + "nəyə sahibsən")
 *
 * TOTP NECƏ İŞLƏYİR?
 * 1. Server secret key yaradır (base32)
 * 2. İstifadəçi Google Authenticator-a QR kod ilə əlavə edir
 * 3. App hər 30 saniyə yeni 6 rəqəmli kod yaradır: HMAC-SHA1(secret, time/30)
 * 4. Login zamanı istifadəçi bu kodu daxil edir
 * 5. Server eyni formulla hesablayır və müqayisə edir
 *
 * NƏYƏ TƏHLÜKƏSİZ?
 * - Hacker şifrəni bilsə belə, 2FA kodu olmadan daxil ola bilməz
 * - Kod hər 30 saniyə dəyişir — köhnə kod işləmir
 * - Secret key yalnız server və istifadəçinin telefonundadır
 *
 * BACKUP KODLARI:
 * Telefon itirsə/pozulsa, backup kod ilə daxil olmaq mümkündür.
 * Hər backup kod BİR DƏFƏ istifadə oluna bilər.
 * Adətən 8-10 kod yaradılır, təhlükəsiz yerdə saxlanmalıdır.
 *
 * QEYD: Real implementasiyada pragmarx/google2fa paketi istifadə olunur.
 * Bu sadələşdirilmiş versiyadır — prinsipi göstərmək üçün.
 */
class TwoFactorService
{
    /**
     * Yeni TOTP secret key yarat (base32 encoded).
     * Bu key Google Authenticator-a QR kod ilə ötürülür.
     */
    public function generateSecret(): string
    {
        // Real: Google2FA::generateSecretKey()
        // Sadələşdirilmiş: random 16 simvolluq base32 string
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < 16; $i++) {
            $secret .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $secret;
    }

    /**
     * QR kod URL-i yarat — Google Authenticator üçün.
     * İstifadəçi bu QR kodu scan edərək app-a əlavə edir.
     *
     * otpauth://totp/AppName:user@email.com?secret=ABCDEF&issuer=AppName
     */
    public function generateQrCodeUrl(string $email, string $secret): string
    {
        $appName = config('app.name', 'DDD-App');
        $encodedEmail = urlencode($email);
        $encodedApp = urlencode($appName);

        return "otpauth://totp/{$encodedApp}:{$encodedEmail}?secret={$secret}&issuer={$encodedApp}";
    }

    /**
     * TOTP kodunu yoxla.
     * Server əsaslı hesablama ilə istifadəçinin daxil etdiyi kodu müqayisə edir.
     *
     * ±1 interval tolerans: Saat fərqi üçün əvvəlki və sonrakı 30 saniyə qəbul edilir.
     */
    public function verifyCode(string $secret, string $code): bool
    {
        // Real: Google2FA::verifyKey($secret, $code)
        // Sadələşdirilmiş: TOTP əsas alqoritmi
        $timeSlice = floor(time() / 30);

        for ($i = -1; $i <= 1; $i++) {
            $calculatedCode = $this->calculateTotp($secret, $timeSlice + $i);
            if (hash_equals($calculatedCode, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Backup kodları yarat (8 ədəd, hər biri 8 simvol).
     */
    public function generateBackupCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(bin2hex(random_bytes(4))); // 8 hex simvol
        }
        return $codes;
    }

    /**
     * Backup kodu yoxla və istifadə olunmuş kimi işarələ.
     *
     * @return array|null Qalan kodlar (uğurlu) və ya null (uğursuz)
     */
    public function verifyBackupCode(array $storedCodes, string $inputCode): ?array
    {
        $inputCode = strtoupper(trim($inputCode));

        $index = array_search($inputCode, $storedCodes);
        if ($index === false) {
            return null; // Kod tapılmadı
        }

        // Kodu sil (bir dəfəlik istifadə)
        unset($storedCodes[$index]);
        return array_values($storedCodes);
    }

    /**
     * TOTP hesablama (sadələşdirilmiş).
     * Real implementasiyada HMAC-SHA1 istifadə olunur.
     */
    private function calculateTotp(string $secret, int $timeSlice): string
    {
        // Sadələşdirilmiş — real TOTP RFC 6238-ə uyğun olmalıdır
        $hash = hash_hmac('sha1', pack('N*', 0, $timeSlice), $secret, true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $code = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        ) % 1000000;

        return str_pad((string) $code, 6, '0', STR_PAD_LEFT);
    }
}
