<?php

declare(strict_types=1);

namespace App\Observers;

use App\Events\ProductStockChangedEvent;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Src\Product\Infrastructure\Models\ProductModel;

/**
 * PRODUCT OBSERVER — Eloquent Observer
 * ======================================
 *
 * OBSERVER NƏDİR? (ƏTRAFLİ İZAH)
 * ================================
 * Observer — Eloquent Model-in həyat dövrünü (lifecycle) izləyən class-dır.
 * Model yaradılarkən, yenilənərkən, silindikdə və s. avtomatik çağırılır.
 *
 * Real həyat analogiyası:
 * Model = "Sənəd"
 * Observer = "Katibə" — sənədlə nə olursa-olsun qeyd edir
 *   → Sənəd yaradılır? Qeyd et.
 *   → Sənəd dəyişdirilir? Qeyd et.
 *   → Sənəd silinir? Qeyd et.
 *
 * BÜTÜN LİFECYCLE HOOK-LARI:
 * ===========================
 *
 * 1. retrieved  — Model DB-dən OXUNDUQDA (SELECT).
 *                 İstifadə: Audit log, cache yeniləmə.
 *                 QEYD: Çox tez-tez çağırılır — diqqətli ol!
 *
 * 2. creating   — Model DB-yə YAZILMAMIŞDAN ƏVVƏL (INSERT öncəsi).
 *                 İstifadə: UUID təyin etmək, default dəyərlər, validasiya.
 *                 return false → yaradılma ləğv olunur!
 *
 * 3. created    — Model DB-yə YAZILDIQDAN SONRA (INSERT sonrası).
 *                 İstifadə: Event dispatch, bildiriş göndərmək.
 *                 Model artıq DB-dədir, ID-si var.
 *
 * 4. updating   — Model YENİLƏNMƏMİŞDƏN ƏVVƏL (UPDATE öncəsi).
 *                 İstifadə: Dəyişiklik validasiyası, köhnə dəyərləri saxlamaq.
 *                 return false → yeniləmə ləğv olunur!
 *
 * 5. updated    — Model YENİLƏNDİKDƏN SONRA (UPDATE sonrası).
 *                 İstifadə: Stok dəyişikliyi event-i, cache invalidate.
 *
 * 6. saving     — creating VƏ YA updating-dən ƏVVƏL (hər ikisi üçün işləyir).
 *                 İstifadə: Ortaq validasiya (həm create, həm update üçün).
 *                 return false → əməliyyat ləğv olunur!
 *
 * 7. saved      — created VƏ YA updated-dən SONRA (hər ikisi üçün işləyir).
 *                 İstifadə: Ortaq post-processing.
 *
 * 8. deleting   — Model SİLİNMƏMİŞDƏN ƏVVƏL (DELETE öncəsi).
 *                 İstifadə: Əlaqəli data-nı silmək, yoxlama.
 *                 return false → silinmə ləğv olunur!
 *
 * 9. deleted    — Model SİLİNDİKDƏN SONRA (DELETE sonrası).
 *                 İstifadə: Log yazmaq, cleanup.
 *
 * 10. restoring — Soft-deleted model BƏRPA EDİLMƏMİŞDƏN ƏVVƏL.
 *                 (Yalnız SoftDeletes trait istifadə olunursa)
 *
 * 11. restored  — Soft-deleted model BƏRPA EDİLDİKDƏN SONRA.
 *
 * HOOK ARDICICILLIĞI (yaratma):  saving → creating → [DB INSERT] → created → saved
 * HOOK ARDICICILLIĞI (yeniləmə): saving → updating → [DB UPDATE] → updated → saved
 * HOOK ARDICICILLIĞI (silmə):    deleting → [DB DELETE] → deleted
 *
 * OBSERVER vs LISTENER — NƏ ZAMAN HANSİ?
 * ========================================
 * OBSERVER istifadə et:
 *   - Model-in DB əməliyyatlarına reaksiya verəndə
 *   - UUID generate, slug yaratmaq, audit log
 *   - Sadə, model-ə bağlı side effect-lər
 *
 * LISTENER istifadə et:
 *   - Biznes event-lərinə reaksiya verəndə
 *   - Email, ödəniş, API çağırışı
 *   - Mürəkkəb, çox addımlı proseslər
 *   - Queue ilə arxa planda iş görmək lazım olanda
 *
 * OBSERVER NECƏ QEYDİYYAT EDİLİR?
 * AppServiceProvider::boot() içində:
 *   ProductModel::observe(ProductObserver::class);
 */
class ProductObserver
{
    /**
     * creating() — Məhsul DB-yə yazılmamışdan ƏVVƏL çağırılır.
     *
     * UUID avtomatik təyin edilir (əgər boşdursa).
     * HasUuids trait bunu edir, amma burada əlavə yoxlama olaraq göstəririk.
     */
    public function creating(ProductModel $product): void
    {
        if (empty($product->id)) {
            $product->id = Str::uuid()->toString();
        }

        Log::info('Yeni məhsul yaradılır', [
            'name' => $product->name,
            'stock' => $product->stock,
        ]);
    }

    /**
     * updated() — Məhsul yeniləndikdən SONRA çağırılır.
     *
     * isDirty() vs wasChanged():
     * - isDirty('stock')   → saving/updating-də istifadə olunur (hələ DB-yə yazılmayıb)
     * - wasChanged('stock') → saved/updated-də istifadə olunur (artıq DB-yə yazılıb)
     *
     * getOriginal() — dəyişiklikdən ƏVVƏLKİ dəyəri qaytarır.
     * $product->stock — indiki (yeni) dəyəri qaytarır.
     */
    public function updated(ProductModel $product): void
    {
        /**
         * Stok dəyişibsə, ProductStockChangedEvent dispatch et.
         * wasChanged() — updated() hook-unda istifadə olunur
         * (saved/updated = artıq DB-yə yazılıb).
         */
        if ($product->wasChanged('stock')) {
            $oldStock = (int) $product->getOriginal('stock');
            $newStock = (int) $product->stock;

            ProductStockChangedEvent::dispatch(
                productId: $product->id,
                oldStock: $oldStock,
                newStock: $newStock,
            );

            Log::info('Məhsul stoku dəyişdi', [
                'product_id' => $product->id,
                'old_stock' => $oldStock,
                'new_stock' => $newStock,
            ]);
        }
    }

    /**
     * deleted() — Məhsul silinDİKDƏN SONRA çağırılır.
     *
     * Məhsul silmək riskli əməliyyatdır — xəbərdarlıq log-lanır.
     * Real proyektdə SoftDeletes istifadə etmək daha yaxşıdır.
     */
    public function deleted(ProductModel $product): void
    {
        Log::warning('Məhsul silindi! Bu geri qaytarılmaz bir əməliyyatdır.', [
            'product_id' => $product->id,
            'product_name' => $product->name,
        ]);
    }
}
