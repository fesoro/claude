{{--
    SİFARİŞ TƏSDİQ EMAİL ŞABLONU (Order Confirmation Email Template)
    ==================================================================

    BLADE ŞABLON SİSTEMİ NƏDİR?
    ==============================
    Blade — Laravel-in şablon mühərrikidir (template engine).
    HTML içində PHP kodunu rahat yazmağa imkan verir.

    Əsas Blade sintaksisi:
    - {{ $variable }}     → Dəyişəni ekrana çıxarır (XSS-dən qorunmuş, htmlspecialchars).
    - {!! $html !!}       → HTML-i olduğu kimi çıxarır (XSS riski var, ehtiyatlı olun!).
    - @if / @elseif / @else / @endif   → Şərt bloku.
    - @foreach / @endforeach           → Dövrə (loop).
    - @forelse / @empty / @endforelse  → Dövrə + boş halda mesaj.
    - @isset / @endisset               → Dəyişən mövcuddursa.
    - @php / @endphp                   → Xam PHP kodu (nadir hallarda istifadə edin).
    - {{-- komentar --}}               → Blade komentarı (HTML-ə düşmür).

    MAİLABLE-DAN GƏLƏN DƏYİŞƏNLƏR:
    ================================
    Mailable class-ın public property-ləri avtomatik bu şablona ötürülür:
    - $orderId     → Sifariş nömrəsi (int)
    - $userEmail   → Müştəri emaili (string)
    - $totalAmount → Ümumi məbləğ (float)
    - $items       → Məhsullar siyahısı (array)
    - $orderDate   → Sifariş tarixi (with: [...] vasitəsilə ötürülüb)
--}}

<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    {{-- Email klientlərinin əksəriyyəti <style> dəstəkləmir, ona görə inline style istifadə edirik --}}
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; background-color: #f4f4f4; font-family: Arial, sans-serif;">

    {{-- Əsas konteyner — email klientlərində table layout daha etibarlıdır --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f4f4; padding: 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden;">

                    {{-- Başlıq hissəsi (header) --}}
                    <tr>
                        <td style="background-color: #2d7d46; color: #ffffff; padding: 30px; text-align: center;">
                            <h1 style="margin: 0; font-size: 24px;">Sifarişiniz Təsdiq Edildi!</h1>
                            {{-- number_format — rəqəmi formatlayır: 1500.5 → 1,500.50 --}}
                            <p style="margin: 10px 0 0; font-size: 16px;">Sifariş #{{ $orderId }}</p>
                        </td>
                    </tr>

                    {{-- Sifariş məlumatları --}}
                    <tr>
                        <td style="padding: 30px;">
                            <p style="color: #333333; font-size: 16px; margin: 0 0 20px;">
                                Salam! Sifarişiniz uğurla qəbul edildi.
                            </p>

                            {{-- Sifariş detalları --}}
                            <table width="100%" style="margin-bottom: 20px; font-size: 14px; color: #555555;">
                                <tr>
                                    <td style="padding: 5px 0;"><strong>Sifariş nömrəsi:</strong></td>
                                    <td style="padding: 5px 0; text-align: right;">#{{ $orderId }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 5px 0;"><strong>Tarix:</strong></td>
                                    {{-- $orderDate — content() metodundakı with: [...] vasitəsilə ötürülüb --}}
                                    <td style="padding: 5px 0; text-align: right;">{{ $orderDate }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 5px 0;"><strong>Email:</strong></td>
                                    <td style="padding: 5px 0; text-align: right;">{{ $userEmail }}</td>
                                </tr>
                            </table>

                            {{-- Məhsullar cədvəli --}}
                            <h2 style="font-size: 18px; color: #333333; margin: 0 0 15px; border-bottom: 2px solid #eeeeee; padding-bottom: 10px;">
                                Sifariş edilən məhsullar
                            </h2>

                            <table width="100%" cellpadding="0" cellspacing="0" style="font-size: 14px;">
                                {{-- Cədvəl başlığı --}}
                                <tr style="background-color: #f8f8f8;">
                                    <td style="padding: 10px; font-weight: bold; color: #333333;">Məhsul</td>
                                    <td style="padding: 10px; font-weight: bold; color: #333333; text-align: center;">Miqdar</td>
                                    <td style="padding: 10px; font-weight: bold; color: #333333; text-align: right;">Qiymət</td>
                                </tr>

                                {{--
                                    @foreach — dövrə (loop) direktividir.
                                    $items array-ın hər elementini $item dəyişəninə təyin edir.
                                    Hər $item: ['name' => '...', 'quantity' => ..., 'price' => ...]
                                --}}
                                @foreach ($items as $item)
                                    <tr style="border-bottom: 1px solid #eeeeee;">
                                        {{--
                                            {{ $item['name'] }} — XSS-dən qorunmuş çıxış.
                                            Əgər $item['name'] = '<script>alert("hack")</script>' olsa,
                                            Blade onu avtomatik escape edəcək (təhlükəsiz).
                                        --}}
                                        <td style="padding: 10px; color: #555555;">{{ $item['name'] }}</td>
                                        <td style="padding: 10px; color: #555555; text-align: center;">{{ $item['quantity'] }}</td>
                                        <td style="padding: 10px; color: #555555; text-align: right;">
                                            {{-- number_format — rəqəmi 2 onluqla formatlayır --}}
                                            {{ number_format($item['price'], 2) }} AZN
                                        </td>
                                    </tr>
                                @endforeach

                                {{-- Ümumi məbləğ sətri --}}
                                <tr style="background-color: #f8f8f8;">
                                    <td colspan="2" style="padding: 12px; font-weight: bold; font-size: 16px; color: #333333;">
                                        Ümumi məbləğ:
                                    </td>
                                    <td style="padding: 12px; font-weight: bold; font-size: 16px; color: #2d7d46; text-align: right;">
                                        {{ number_format($totalAmount, 2) }} AZN
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Alt hissə (footer) --}}
                    <tr>
                        <td style="background-color: #f8f8f8; padding: 20px; text-align: center; font-size: 12px; color: #999999;">
                            <p style="margin: 0 0 5px;">Sualınız varsa, bizə yazın: support@eshop.az</p>
                            <p style="margin: 0;">Bu email avtomatik olaraq göndərilib.</p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>

</body>
</html>
