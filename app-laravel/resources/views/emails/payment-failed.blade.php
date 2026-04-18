{{--
    ÖDƏMƏ UĞURSUZ EMAİL ŞABLONU (Payment Failed Email Template)
    ==============================================================
    Mailable-dan gələn dəyişənlər:
    - $orderId       → Sifariş nömrəsi
    - $amount        → Ödənilməyə çalışılan məbləğ
    - $failureReason → Uğursuzluğun səbəbi
--}}

<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; background-color: #f4f4f4; font-family: Arial, sans-serif;">

    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f4f4; padding: 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden;">

                    {{-- Başlıq — qırmızı rəng xəbərdarlıq bildirir --}}
                    <tr>
                        <td style="background-color: #d93025; color: #ffffff; padding: 30px; text-align: center;">
                            <h1 style="margin: 0; font-size: 24px;">Odeme Ugursuz Oldu</h1>
                            <p style="margin: 10px 0 0; font-size: 16px;">Sifaris #{{ $orderId }}</p>
                        </td>
                    </tr>

                    {{-- Məzmun --}}
                    <tr>
                        <td style="padding: 30px;">
                            <p style="color: #333333; font-size: 16px; margin: 0 0 20px;">
                                Salam! Teassuflə bildiririk ki, sifarişiniz ucun odeme ugursuz oldu.
                            </p>

                            {{-- Uğursuzluq detalları --}}
                            <table width="100%" style="font-size: 14px; color: #555555; background-color: #fef2f2; border-radius: 6px; border: 1px solid #fecaca;">
                                <tr>
                                    <td style="padding: 12px 15px; border-bottom: 1px solid #fecaca;"><strong>Sifaris nomresi:</strong></td>
                                    <td style="padding: 12px 15px; border-bottom: 1px solid #fecaca; text-align: right;">#{{ $orderId }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 12px 15px; border-bottom: 1px solid #fecaca;"><strong>Mebleg:</strong></td>
                                    <td style="padding: 12px 15px; border-bottom: 1px solid #fecaca; text-align: right;">{{ number_format($amount, 2) }} AZN</td>
                                </tr>
                                <tr>
                                    <td style="padding: 12px 15px;"><strong>Sebebi:</strong></td>
                                    <td style="padding: 12px 15px; text-align: right; color: #d93025; font-weight: bold;">{{ $failureReason }}</td>
                                </tr>
                            </table>

                            {{-- Yenidən cəhd etmə təklifi --}}
                            <div style="margin-top: 25px; padding: 20px; background-color: #f0f9ff; border-radius: 6px; border: 1px solid #bae6fd;">
                                <h3 style="margin: 0 0 10px; color: #0369a1; font-size: 16px;">Ne ede bilersiniz?</h3>
                                <ul style="margin: 0; padding: 0 0 0 20px; color: #555555; font-size: 14px; line-height: 1.8;">
                                    <li>Kart melumatlarinizi yoxlayin ve yeniden cehd edin</li>
                                    <li>Basqa odeme usulu secin (bank kocurmesi, qapida odeme)</li>
                                    <li>Bankinizla elaqe saxlayin</li>
                                    <li>Destek komandamiza yazin: support@eshop.az</li>
                                </ul>
                            </div>

                            {{--
                                @isset — dəyişənin mövcud olub-olmadığını yoxlayır.
                                Bu direktiv isset() PHP funksiyasının Blade versiyasıdır.
                                Əgər $retryUrl dəyişəni ötürülübsə, "Yenidən cəhd et" düyməsi göstərilir.
                            --}}
                            @isset($retryUrl)
                                <div style="text-align: center; margin-top: 25px;">
                                    <a href="{{ $retryUrl }}"
                                       style="display: inline-block; background-color: #1a73e8; color: #ffffff; padding: 12px 30px; text-decoration: none; border-radius: 6px; font-size: 16px; font-weight: bold;">
                                        Yeniden cehd et
                                    </a>
                                </div>
                            @endisset
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="background-color: #f8f8f8; padding: 20px; text-align: center; font-size: 12px; color: #999999;">
                            <p style="margin: 0 0 5px;">Komek lazimdir? support@eshop.az</p>
                            <p style="margin: 0;">Bu email avtomatik olaraq gonderildi.</p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>

</body>
</html>
