<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * SİFARİŞ LƏĞV ETMƏ FORM REQUEST
 * ================================
 * Bu Form Request-in rules() metodu boşdur — heç bir validasiya qaydası yoxdur.
 * Bəs onda nəyə Form Request istifadə edirik?
 *
 * QAYDA OLMADAN FORM REQUEST NƏ İŞƏ YARAYIR?
 * ============================================
 *
 * 1. AVTORİZASİYA (authorize() metodu):
 *    Ən əsas səbəb budur. Sifariş ləğv etmə əməliyyatında validasiya qaydası
 *    lazım olmasa da, "bu istifadəçi bu sifarişi ləğv edə bilər?" sualını
 *    yoxlamaq lazımdır.
 *
 *    Nümunə: İstifadəçi yalnız ÖZ sifarişini ləğv edə bilər.
 *    authorize() metodu bunu yoxlayır.
 *
 * 2. TƏMİZ ARXİTEKTURA (Consistency):
 *    Bütün əməliyyatlar üçün Form Request istifadə etmək kodu ardıcıl edir.
 *    Bəzi əməliyyatlarda Request, bəzilərində FormRequest istifadə etmək
 *    kodu qarışıq edir.
 *
 * 3. GƏLƏCƏKDAKİ DƏYİŞİKLİKLƏR:
 *    İndi qayda lazım deyil, amma gələcəkdə əlavə oluna bilər.
 *    Məsələn: 'reason' => ['sometimes', 'string', 'max:500'] — ləğv səbəbi.
 *    Form Request artıq mövcuddursa, sadəcə rules()-a əlavə etmək kifayətdir.
 *
 * 4. MİDDLEWARE KİMİ DAVRANIR:
 *    Form Request controller-dən əvvəl icra olunur.
 *    Əgər authorize() false qaytarırsa, controller heç çağırılmır.
 *    Bu, "fail fast" prinsipini təmin edir.
 */
final class CancelOrderRequest extends FormRequest
{
    /**
     * Sifarişi ləğv etmə icazəsi.
     *
     * Burada əsl layihədə belə yoxlama olardı:
     *
     * public function authorize(): bool
     * {
     *     $order = Order::find($this->route('id'));
     *     return $order && $this->user()->id === $order->user_id;
     * }
     *
     * Bu, istifadəçinin yalnız öz sifarişini ləğv edə bilməsini təmin edir.
     * Hal-hazırda sadəlik üçün true qaytarırıq.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validasiya qaydaları — boşdur.
     * Sifarişi ləğv etmək üçün əlavə data lazım deyil,
     * sifariş ID-si URL-dən (route parameter) gəlir.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [];
    }
}
