<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\ValidEmailRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * FORM REQUEST NƏDİR?
 * ====================
 * Form Request — Laravel-də HTTP sorğusunun validasiyasını və avtorizasiyasını
 * controller-dən ayırmaq üçün istifadə olunan xüsusi sinifdir.
 *
 * ƏVVƏL (Form Request olmadan):
 * -------------------------------------------------------------------
 * public function register(Request $request) {
 *     $validated = $request->validate([
 *         'name' => 'required|min:2|max:255',
 *         'email' => 'required|email|unique:domain_users',
 *     ]);
 *     // ...biznes logikası...
 * }
 * -------------------------------------------------------------------
 * Problem: Controller həm validasiya, həm də biznes logikası ilə məşğuldur.
 * Controller "şişir" — Single Responsibility Principle (SRP) pozulur.
 *
 * SONRA (Form Request ilə):
 * -------------------------------------------------------------------
 * public function register(RegisterUserRequest $request) {
 *     $command = new RegisterUserCommand(...$request->validated());
 *     // Controller yalnız koordinasiya edir — YAXŞI!
 * }
 * -------------------------------------------------------------------
 * Validasiya ayrı sinifdədir, controller təmizdir.
 *
 * FORM REQUEST NECƏ İŞLƏYİR?
 * ============================
 * 1. Controller metodu type-hint ilə Form Request qəbul edir:
 *    public function register(RegisterUserRequest $request)
 *
 * 2. Laravel avtomatik olaraq:
 *    a) authorize() metodunu çağırır — icazə yoxlaması
 *    b) rules() metodunu çağırır — validasiya qaydalarını alır
 *    c) Sorğu datanı qaydalara uyğun yoxlayır
 *
 * 3. Əgər authorize() false qaytarırsa → 403 Forbidden cavabı
 * 4. Əgər validasiya uğursuzdursa → 422 Unprocessable Entity + xəta mesajları
 * 5. Hər şey yaxşıdırsa → Controller metodu icra olunur
 *
 * authorize() METODU:
 * ===================
 * Bu metod "bu istifadəçinin bu sorğunu göndərməyə icazəsi var?" sualına cavab verir.
 * - true qaytarırsa → icazə var, davam et
 * - false qaytarırsa → 403 Forbidden
 *
 * Nümunələr:
 * - Qeydiyyat üçün: həmişə true (hər kəs qeydiyyatdan keçə bilər)
 * - Profil yeniləmə üçün: $this->user()->id === $this->route('id')
 * - Admin əməliyyatı üçün: $this->user()->isAdmin()
 *
 * messages() METODU:
 * ==================
 * Bu metod xəta mesajlarını xüsusiləşdirmək üçündür.
 * Laravel-in standart mesajları ingilis dilindədir.
 * messages() ilə Azərbaycan dilinə çevirə bilərik:
 *
 * 'name.required' → 'Ad sahəsi mütləq doldurulmalıdır'
 * 'email.unique'  → 'Bu email artıq qeydiyyatdadır'
 *
 * Format: 'sahə.qayda' => 'Xüsusi mesaj'
 */
final class RegisterUserRequest extends FormRequest
{
    /**
     * İCAZƏ YOXLAMASI (Authorization)
     *
     * Qeydiyyat sorğusu üçün heç bir icazə tələb olunmur —
     * hər kəs qeydiyyatdan keçə bilər, ona görə true qaytarırıq.
     *
     * Əgər false qaytarsaydıq, Laravel avtomatik 403 Forbidden cavabı göndərərdi.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * VALİDASİYA QAYDALARI
     *
     * Hər sahə üçün qaydalar massiv şəklində yazılır.
     * Qaydalar soldan sağa sıra ilə yoxlanılır.
     *
     * 'name' => ['required', 'string', 'min:2', 'max:255']:
     *   - required: mütləq doldurulmalıdır (null, boş string qəbul edilmir)
     *   - string: yalnız string tipində olmalıdır
     *   - min:2: minimum 2 simvol
     *   - max:255: maksimum 255 simvol (DB varchar limiti)
     *
     * 'email' => ['required', 'email', 'unique:domain_users,email', new ValidEmailRule()]:
     *   - email: Laravel-in daxili email validasiyası
     *   - unique:domain_users,email: domain_users cədvəlindəki email sütununda unikal olmalıdır
     *   - new ValidEmailRule(): bizim Custom Rule — Domain Value Object ilə eyni logika
     *
     * 'password' => ['required', 'string', 'min:8', 'confirmed']:
     *   - confirmed: request-də "password_confirmation" sahəsi olmalı və eyni olmalıdır
     *     Bu, istifadəçinin parolu düzgün yazdığını təsdiq etmək üçündür.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['required', 'email', 'unique:domain_users,email', new ValidEmailRule()],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    /**
     * XÜSUSİ XƏTA MESAJLARI
     *
     * Laravel-in standart mesajları ingilis dilindədir.
     * Bu metod ilə hər qayda üçün Azərbaycan dilində mesaj yazırıq.
     *
     * Format: 'sahə_adı.qayda_adı' => 'Xüsusi mesaj'
     *
     * :attribute — Laravel bunu avtomatik sahə adı ilə əvəz edir.
     * :min, :max — Laravel bunu qayda parametri ilə əvəz edir.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Ad sahəsi mütləq doldurulmalıdır.',
            'name.min' => 'Ad minimum :min simvol olmalıdır.',
            'name.max' => 'Ad maksimum :max simvol ola bilər.',
            'email.required' => 'Email sahəsi mütləq doldurulmalıdır.',
            'email.email' => 'Düzgün email formatı daxil edin.',
            'email.unique' => 'Bu email artıq qeydiyyatdadır.',
            'password.required' => 'Şifrə sahəsi mütləq doldurulmalıdır.',
            'password.min' => 'Şifrə minimum :min simvol olmalıdır.',
            'password.confirmed' => 'Şifrə təsdiqi uyğun gəlmir.',
        ];
    }
}
