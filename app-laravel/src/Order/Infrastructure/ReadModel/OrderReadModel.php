<?php

declare(strict_types=1);

namespace Src\Order\Infrastructure\ReadModel;

use Illuminate\Database\Eloquent\Model;

/**
 * ORDER READ MODEL — Sifariş Oxuma Modeli (Eloquent)
 * ===================================================
 *
 * Bu Eloquent model, order_read_model cədvəlini təmsil edir.
 * CQRS arxitekturasında bu "Query" tərəfinin modeli-dir.
 *
 * ADİ ELOQUENT MODEL-DƏN FƏRQİ:
 * ==============================
 * Adi model (Order): həm yazma, həm oxuma üçün istifadə olunur.
 * Read Model: YALNIZ oxuma üçündür!
 *
 * Bu modelə birbaşa yazma əməliyyatı edilir, amma YALNIZ Projection tərəfindən.
 * Controller və ya servis birbaşa bu modelə yazmaz — yalnız oxuyar.
 *
 * NƏYƏ ELOQUENT?
 * ===============
 * Read Model üçün Eloquent lazım deyildi — DB fasadı ilə də olardı.
 * Amma Eloquent istifadə edirik çünki:
 * 1. Laravel ekosistemi ilə uyğunluq — Resource, Pagination, Scopes.
 * 2. Relationship-lər əlavə etmək asandır (gələcəkdə lazım ola bilər).
 * 3. Developer-lər üçün tanış interfeys — öyrənmə əziyyəti yoxdur.
 *
 * ANALOGİYA:
 * Read Model — mağazadakı vitrin kimidir.
 * Vitrin məhsulları göstərir (oxuma), amma müştəri vitrindən birbaşa məhsul almır.
 * Məhsul anbarda idarə olunur (Event Store), vitrin anbardan yenilənir (Projection).
 *
 * İSTİFADƏ NÜMUNƏSİ:
 * ===================
 * // Bir sifarişi oxu
 * $order = OrderReadModel::find('uuid-here');
 *
 * // İstifadəçinin bütün sifarişləri
 * $orders = OrderReadModel::where('user_id', $userId)->get();
 *
 * // Ödənilmiş sifarişlər (filtrlənmiş)
 * $paidOrders = OrderReadModel::where('status', 'paid')->paginate(20);
 *
 * // Cəmi məbləğə görə sıralama
 * $topOrders = OrderReadModel::orderByDesc('total_amount')->limit(10)->get();
 */
class OrderReadModel extends Model
{
    /**
     * Hansı DB connection istifadə olunacaq.
     * Event Store ilə eyni connection-dur — order_db.
     *
     * Real layihədə Read Model AYRI connection-da ola bilər:
     * - Event Store: PostgreSQL (güclü yazma)
     * - Read Model: MySQL və ya Elasticsearch (güclü oxuma)
     */
    protected $connection = 'order_db';

    /**
     * Cədvəl adı.
     * Laravel konvensiyası "order_read_models" olardı (cəm halı),
     * amma biz açıq şəkildə təyin edirik.
     */
    protected $table = 'order_read_model';

    /**
     * Primary key sahəsi — UUID istifadə edirik.
     * Laravel default olaraq 'id' gözləyir, amma bizdə 'order_id'-dir.
     */
    protected $primaryKey = 'order_id';

    /**
     * Primary key auto-increment DEYİL — UUID istifadə edirik.
     */
    public $incrementing = false;

    /**
     * Primary key-in tipi string-dir (UUID).
     */
    protected $keyType = 'string';

    /**
     * Laravel-in avtomatik timestamp-lərini söndürürük.
     * Bizdə created_at/updated_at yoxdur — əvəzinə last_updated_at var.
     * Timestamp-ı Projection özü idarə edir.
     */
    public $timestamps = false;

    /**
     * Kütləvi təyin edilə bilən sahələr (mass assignment).
     * Projection bu sahələri updateOrCreate() ilə yeniləyəcək.
     *
     * XƏBƏRDARLIQ: Real layihədə $guarded = [] istifadə etmək təhlükəsizlik riski ola bilər.
     * Amma Read Model-ə yalnız Projection yazır, istifadəçi input-u gəlmir.
     * Buna görə burada $fillable ilə açıq şəkildə siyahılayırıq.
     */
    protected $fillable = [
        'order_id',
        'user_id',
        'user_name',
        'status',
        'total_amount',
        'currency',
        'item_count',
        'last_updated_at',
    ];

    /**
     * Sahə tipləri — Laravel avtomatik cast edəcək.
     * Məsələn: total_amount DB-dən string gələ bilər, amma integer-ə çevriləcək.
     */
    protected $casts = [
        'total_amount'    => 'integer',
        'item_count'      => 'integer',
        'last_updated_at' => 'datetime',
    ];
}
