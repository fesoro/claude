<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Src\Payment\Infrastructure\Models\PaymentModel;

/**
 * PAYMENT POLICY — ÖDƏNİŞ ÜZRƏ AVTORİZASİYA QAYDALARI
 * =======================================================
 *
 * Bu Policy ödəniş resursu üzrə icazələri idarə edir.
 *
 * POLICY METODLARININ TİPLƏRİ:
 * ----------------------------
 * 1. Model-li metodlar: view(User $user, PaymentModel $payment)
 *    → Konkret ödənişə baxmaq — model lazımdır
 *    → Controller-dən: $this->authorize('view', $payment)
 *
 * 2. Model-siz metodlar: process(User $user)
 *    → Ümumi hərəkət — konkret model yoxdur
 *    → Controller-dən: $this->authorize('process', PaymentModel::class)
 *    → DİQQƏT: model instance yox, CLASS adı ötürülür!
 *
 * Bu fərq vacibdir — model-siz metodlarda yalnız istifadəçi yoxlanılır,
 * konkret resurs yoxdur çünki hələ yaradılmayıb.
 */
class PaymentPolicy
{
    /**
     * view — İstifadəçi ödənişi görə bilərmi?
     *
     * QAYDA: İstifadəçi yalnız ÖZ sifarişinə aid ödənişi görə bilər.
     *
     * BURADA MARAQLI MƏQAM:
     * Payment modelinin birbaşa user_id sahəsi yoxdur.
     * Payment → Order → User zənciri ilə mülkiyyət yoxlanılır.
     * Yəni: "bu ödəniş hansı sifarişə aiddir? o sifariş səninkidirmi?"
     *
     * Bu, Eloquent relation-ların Policy-də necə istifadə olunduğunu göstərir.
     * payment->order->user_id — relation zənciri ilə user-ə çatırıq.
     */
    public function view(User $user, PaymentModel $payment): bool
    {
        // Payment → Order → user_id zənciri ilə mülkiyyət yoxlaması
        // $payment->order eager/lazy load ilə OrderModel-i gətirir
        // Sonra order-in user_id sahəsi ilə cari user müqayisə olunur
        return $user->id === $payment->order->user_id;
    }

    /**
     * process — İstifadəçi ödəniş başlada bilərmi?
     *
     * QAYDA: Hər bir autentifikasiya olunmuş istifadəçi ödəniş edə bilər.
     *
     * MODEL-SİZ METOD:
     * Bu metodda PaymentModel parametri YOXDUR çünki:
     * - Ödəniş hələ yaradılmayıb (yeni ödəniş başladılır)
     * - Yoxlamaq üçün konkret resurs yoxdur
     * - Yalnız "bu user ümumiyyətlə ödəniş edə bilərmi?" sualına cavab verir
     *
     * Controller-dən belə çağırılır (CLASS adı ilə):
     *   $this->authorize('process', PaymentModel::class);
     */
    public function process(User $user): bool
    {
        // Hər autentifikasiya olunmuş istifadəçi ödəniş edə bilər
        // Auth middleware artıq login yoxlamasını edib
        // Policy isə əlavə biznes qaydası yoxlaya bilər (məsələn ban olunmayıb?)
        return true;
    }
}
