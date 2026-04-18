<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * UPLOAD PRODUCT IMAGE REQUEST
 * ============================
 * Şəkil yükləmə sorğusu üçün validasiya.
 *
 * FAYL VALİDASİYA QAYDALARI:
 * ═══════════════════════════
 *
 * 'image' rule: Fayl şəkil olmalıdır (jpeg, png, bmp, gif, svg, webp).
 *   Bu MIME type yoxlayır, extension-a deyil — təhlükəsiz.
 *   Hacker "virus.exe" faylının adını "virus.jpg" dəyişsə belə,
 *   'image' rule MIME type-a baxdığı üçün rədd edəcək.
 *
 * 'mimes:jpeg,png,webp' → Yalnız bu formatları qəbul et.
 *   gif, svg istəmiriksə burda siyahıya almırıq.
 *
 * 'max:5120' → Maksimum 5 MB (5120 KB).
 *   Böyük fayllar serveri yavaşladır, disk-i doldurur.
 *   Production-da adətən 2-10 MB arası limit qoyulur.
 *
 * 'dimensions:min_width=200,min_height=200' → Minimum ölçü.
 *   Çox kiçik şəkillər keyfiyyətsiz görünür.
 *
 * 'is_primary' → Boolean, əsas şəkildirmi.
 */
class UploadProductImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Policy controller-də yoxlanılır
    }

    public function rules(): array
    {
        return [
            /**
             * 'image' — fayl şəkil tipidir?
             * 'mimes:jpeg,png,webp' — yalnız bu formatlar qəbul edilir
             * 'max:5120' — maksimum 5 MB
             * 'dimensions' — minimum 200x200 piksel
             */
            'image' => [
                'required',
                'image',
                'mimes:jpeg,png,webp',
                'max:5120',
                'dimensions:min_width=200,min_height=200,max_width=4096,max_height=4096',
            ],
            'is_primary' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'image.required' => 'Şəkil seçilməyib.',
            'image.image' => 'Yüklənən fayl şəkil formatında olmalıdır.',
            'image.mimes' => 'Yalnız JPEG, PNG və WebP formatları qəbul edilir.',
            'image.max' => 'Şəkil ölçüsü 5 MB-dan böyük ola bilməz.',
            'image.dimensions' => 'Şəkil ən azı 200x200, ən çox 4096x4096 piksel olmalıdır.',
        ];
    }
}
