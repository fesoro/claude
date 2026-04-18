<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * PASSWORD RESET MAİL
 * ===================
 * Şifrə sıfırlama linki olan email.
 *
 * TOKEN NECƏ İŞLƏYİR?
 * 1. Server random token yaradır və hash-ləyib password_reset_tokens cədvəlinə yazır
 * 2. Düz mətn token emaildəki linkə qoyulur
 * 3. İstifadəçi linkə klikləyir → token server-ə göndərilir
 * 4. Server token-i hash-ləyib DB-dəki ilə müqayisə edir
 * 5. Uyğundursa → şifrə dəyişdirilir, token silinir
 *
 * Token-in ömrü config/auth.php-də passwords.users.expire ilə təyin olunur (default 60 dəqiqə).
 */
class PasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $resetUrl,
        public readonly string $userName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Şifrə Sıfırlama Sorğusu',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.password-reset',
        );
    }
}
