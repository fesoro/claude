<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Şifrə Sıfırlama</title>
</head>
<body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
    <h2>Salam, {{ $userName }}!</h2>

    <p>Şifrənizi sıfırlamaq üçün sorğu aldıq.</p>

    <p>Aşağıdakı düyməyə klikləyərək yeni şifrə təyin edə bilərsiniz:</p>

    <p style="text-align: center; margin: 30px 0;">
        <a href="{{ $resetUrl }}"
           style="background-color: #3b82f6; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold;">
            Şifrəni Sıfırla
        </a>
    </p>

    <p>Bu link 60 dəqiqə ərzində etibarlıdır.</p>

    <p style="color: #6b7280; font-size: 14px;">
        Əgər siz şifrə sıfırlama sorğusu göndərməmisinizsə, bu emaili nəzərə almayın.
        Hesabınız təhlükəsizdir.
    </p>

    <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 20px 0;">
    <p style="color: #9ca3af; font-size: 12px;">Bu avtomatik email göndərilib. Cavab yazmayın.</p>
</body>
</html>
