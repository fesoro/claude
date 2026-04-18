{{--
    AZ STOK XƏBƏRDARLIQ ŞABLONU (Low Stock Alert Email Template)
    ==============================================================
    Admin-ə göndərilən xəbərdarlıq emaili.

    Mailable-dan gələn dəyişənlər:
    - $productName  → Məhsulun adı
    - $currentStock → Hazırkı stok miqdarı

    Bu email ShouldQueue interface ilə göndərilir — yəni queue vasitəsilə
    arxa planda göndərilir, admin panelinin sürətini yavaşlatmır.
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

                    {{-- Başlıq — narıncı rəng xəbərdarlıq bildirir --}}
                    <tr>
                        <td style="background-color: #f59e0b; color: #ffffff; padding: 30px; text-align: center;">
                            <h1 style="margin: 0; font-size: 24px;">Stok Xeberdarligi</h1>
                            <p style="margin: 10px 0 0; font-size: 16px;">Mehsulun stoku azdir!</p>
                        </td>
                    </tr>

                    {{-- Məzmun --}}
                    <tr>
                        <td style="padding: 30px;">

                            {{-- Xəbərdarlıq bloku --}}
                            <div style="padding: 20px; background-color: #fffbeb; border-radius: 6px; border: 1px solid #fde68a; margin-bottom: 20px;">
                                <table width="100%" style="font-size: 14px; color: #555555;">
                                    <tr>
                                        <td style="padding: 8px 0;"><strong>Mehsul:</strong></td>
                                        <td style="padding: 8px 0; text-align: right; font-weight: bold; color: #333333;">{{ $productName }}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 0;"><strong>Hazirki stok:</strong></td>
                                        <td style="padding: 8px 0; text-align: right;">
                                            {{--
                                                @if / @elseif / @else / @endif — şərt bloku.
                                                Stok miqdarına görə fərqli rəng və mesaj göstəririk:
                                                - 0 = qırmızı (tükənib)
                                                - 1-5 = narıncı (çox az)
                                                - 6+ = sarı (az)
                                            --}}
                                            @if ($currentStock === 0)
                                                <span style="color: #dc2626; font-weight: bold; font-size: 18px;">TUKENIB (0)</span>
                                            @elseif ($currentStock <= 5)
                                                <span style="color: #ea580c; font-weight: bold; font-size: 18px;">{{ $currentStock }} eded</span>
                                            @else
                                                <span style="color: #d97706; font-weight: bold; font-size: 18px;">{{ $currentStock }} eded</span>
                                            @endif
                                        </td>
                                    </tr>
                                </table>
                            </div>

                            <p style="color: #555555; font-size: 14px; line-height: 1.6;">
                                Bu mehsulun stoku minimum hedden asagiya dusdugu ucun
                                bu xeberdarliq avtomatik gonderildi. Zehmet olmasa,
                                admin panelinden stoku yenileyib tedarukcu ile elaqe saxlayin.
                            </p>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="background-color: #f8f8f8; padding: 20px; text-align: center; font-size: 12px; color: #999999;">
                            <p style="margin: 0;">Bu avtomatik sistem xeberdarliqidir (ShouldQueue ile queue vasitesile gonderildi).</p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>

</body>
</html>
