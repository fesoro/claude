{{--
    ÖDƏMƏ QƏBZ EMAİL ŞABLONU (Payment Receipt Email Template)
    ============================================================
    Mailable-dan gələn dəyişənlər:
    - $orderId          → Sifariş nömrəsi
    - $amount           → Ödənilən məbləğ
    - $currency         → Valyuta (AZN, USD, EUR)
    - $transactionId    → Əməliyyat ID-si
    - $paymentMethod    → Ödəmə üsulu (card, bank_transfer, cash_on_delivery)
    - $paymentDate      → Ödəmə tarixi (with vasitəsilə ötürülüb)
    - $paymentMethodLabel → Ödəmə üsulunun Azərbaycan dilində adı (with vasitəsilə)
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

                    {{-- Başlıq --}}
                    <tr>
                        <td style="background-color: #1a73e8; color: #ffffff; padding: 30px; text-align: center;">
                            <h1 style="margin: 0; font-size: 24px;">Ödəmə Qəbzi</h1>
                            <p style="margin: 10px 0 0; font-size: 16px;">Ödəməniz uğurla qəbul edildi</p>
                        </td>
                    </tr>

                    {{-- Ödəmə detalları --}}
                    <tr>
                        <td style="padding: 30px;">
                            <p style="color: #333333; font-size: 16px; margin: 0 0 20px;">
                                Salam! Sifarişiniz üçün ödəmə uğurla həyata keçirildi.
                            </p>

                            {{-- Ödəmə məlumatları cədvəli --}}
                            <table width="100%" style="font-size: 14px; color: #555555; background-color: #f8f8f8; border-radius: 6px;">
                                <tr>
                                    <td style="padding: 12px 15px; border-bottom: 1px solid #eeeeee;"><strong>Sifariş nömrəsi:</strong></td>
                                    <td style="padding: 12px 15px; border-bottom: 1px solid #eeeeee; text-align: right;">#{{ $orderId }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 12px 15px; border-bottom: 1px solid #eeeeee;"><strong>Əməliyyat ID:</strong></td>
                                    <td style="padding: 12px 15px; border-bottom: 1px solid #eeeeee; text-align: right;">{{ $transactionId }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 12px 15px; border-bottom: 1px solid #eeeeee;"><strong>Ödəmə üsulu:</strong></td>
                                    <td style="padding: 12px 15px; border-bottom: 1px solid #eeeeee; text-align: right;">{{ $paymentMethodLabel }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 12px 15px; border-bottom: 1px solid #eeeeee;"><strong>Tarix:</strong></td>
                                    <td style="padding: 12px 15px; border-bottom: 1px solid #eeeeee; text-align: right;">{{ $paymentDate }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 12px 15px;"><strong>Ödənilən məbləğ:</strong></td>
                                    <td style="padding: 12px 15px; text-align: right; font-size: 18px; font-weight: bold; color: #1a73e8;">
                                        {{ number_format($amount, 2) }} {{ $currency }}
                                    </td>
                                </tr>
                            </table>

                            <p style="color: #999999; font-size: 13px; margin: 20px 0 0; text-align: center;">
                                Bu qəbzi saxlayın. Sualınız olsa, bizə yazın.
                            </p>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="background-color: #f8f8f8; padding: 20px; text-align: center; font-size: 12px; color: #999999;">
                            <p style="margin: 0 0 5px;">Sualınız varsa: support@eshop.az</p>
                            <p style="margin: 0;">Bu email avtomatik olaraq göndərilib.</p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>

</body>
</html>
