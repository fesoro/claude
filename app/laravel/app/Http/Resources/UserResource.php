<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * USER API RESOURCE
 * =================
 * Bu sinif User model/DTO-nu API cavabına çevirir (transform edir).
 *
 * ═══════════════════════════════════════════════════════════════════
 * API RESOURCE NƏDİR?
 * ═══════════════════════════════════════════════════════════════════
 *
 * API Resource — Model (və ya DTO) ilə JSON cavab arasında duran
 * "tərcüməçi" (transformation layer) təbəqəsidir.
 *
 * Sadə dildə: Bazadan gələn datanı API-nin qaytaracağı formata çevirir.
 *
 * Nümunə:
 *   User modeldə 20 sahə var (password, remember_token, və s.)
 *   Amma API-dən yalnız 5 sahə qaytarmaq istəyirik.
 *   Resource tam olaraq bunu edir — lazımi sahələri seçib formatlaşdırır.
 *
 * ═══════════════════════════════════════════════════════════════════
 * NƏYƏ GÖRƏ MODEL/DTO-NU BİRBAŞA QAYTARMIAQ?
 * ═══════════════════════════════════════════════════════════════════
 *
 * 1. TƏHLÜKƏSİZLİK: Model-i birbaşa qaytarsaq, password, token kimi
 *    gizli sahələr də JSON-a düşə bilər. Resource yalnız icazə verilən
 *    sahələri qaytarır.
 *
 * 2. FORMATLAMA: API-dən tarix "2024-01-15T10:30:00" formatında,
 *    qiymət {amount: 29.99, currency: "USD"} kimi gəlməlidir.
 *    Model bunu bilmir, Resource formatlaşdırır.
 *
 * 3. VERSİYALAMA: API v1-də "full_name", v2-də "name" qaytarmaq istəsək,
 *    Model-i dəyişmədən fərqli Resource-lar yarada bilərik.
 *
 * 4. İÇ İÇƏ RESURSLAR (Nested Resources): Order-un daxilində items,
 *    user, address kimi əlaqəli datanı da formatlaya bilərik.
 *
 * 5. DTO İLƏ MÜQAYİSƏ:
 *    - DTO sadəcə data daşıyıcıdır, format bilmir
 *    - Resource isə HTTP cavab üçün xüsusi formatlama edir
 *    - DTO: Application Layer daxilində data ötürmək üçün
 *    - Resource: Presentation Layer-də API cavab formatlamaq üçün
 *    - Hər ikisi birlikdə istifadə oluna bilər:
 *      Handler → DTO qaytarır → Controller → Resource(DTO) → JSON
 *
 * ═══════════════════════════════════════════════════════════════════
 * toArray() METODU NECƏ İŞLƏYİR?
 * ═══════════════════════════════════════════════════════════════════
 *
 * Laravel Resource yaradanda, $this kontekstində model/DTO-ya birbaşa
 * müraciət edə bilərik. Yəni $this->name yazsaq, model-in name sahəsini
 * alırıq. Bu "sehrli" davranış JsonResource-un __get() magic metodu
 * sayəsində işləyir — $this-dəki hər bir sahə əslində $this->resource-a
 * yönləndirilir.
 *
 * Nümunə:
 *   new UserResource($user)  →  $this->name  ===  $user->name
 *
 * ═══════════════════════════════════════════════════════════════════
 * PAGİNASİYA İLƏ İŞLƏMƏ
 * ═══════════════════════════════════════════════════════════════════
 *
 * Laravel Resource paginasiya ilə avtomatik işləyir:
 *
 *   UserResource::collection(User::paginate(15))
 *
 * Bu avtomatik olaraq belə cavab qaytarır:
 *   {
 *     "data": [ {id: 1, name: "..."}, {id: 2, name: "..."} ],
 *     "links": { "first": "...", "last": "...", "prev": null, "next": "..." },
 *     "meta": { "current_page": 1, "last_page": 5, "per_page": 15, "total": 75 }
 *   }
 *
 * Yəni əlavə heç nə yazmadan pagination metadata əlavə olunur!
 *
 * ═══════════════════════════════════════════════════════════════════
 * ŞƏRTLI SAHƏLƏR — when() METODU
 * ═══════════════════════════════════════════════════════════════════
 *
 * Bəzi sahələri yalnız müəyyən şərtdə qaytarmaq istəyə bilərik:
 *
 *   'is_admin' => $this->when(auth()->user()->isAdmin(), $this->is_admin)
 *   // Yalnız admin istifadəçi sorğu göndərirsə, is_admin sahəsi görünür.
 *
 *   'secret' => $this->when($request->user()->isOwner($this->id), $this->secret)
 *   // Yalnız öz profilini baxırsa, secret sahəsi görünür.
 *
 *   'orders_count' => $this->when($this->orders_count !== null, $this->orders_count)
 *   // Yalnız eager load edilibsə, orders_count göstərilir.
 *
 * ═══════════════════════════════════════════════════════════════════
 * İÇ İÇƏ RESURSLAR (Nested Resources)
 * ═══════════════════════════════════════════════════════════════════
 *
 * Bir Resource daxilində başqa Resource istifadə edə bilərik:
 *
 *   'orders' => OrderResource::collection($this->whenLoaded('orders'))
 *   // User-in sifarişlərini OrderResource ilə formatla
 *   // whenLoaded() — yalnız əlaqə eager load edilibsə daxil et
 *
 *   'latest_order' => new OrderResource($this->whenLoaded('latestOrder'))
 *   // Tək əlaqəni wrap et
 */
class UserResource extends JsonResource
{
    /**
     * Resource-u array (sonra JSON) formatına çevir.
     *
     * Bu metod hər dəfə Resource JSON-a serializasiya olunanda çağırılır.
     * $request parametri cari HTTP sorğusudur — ona görə şərtli
     * sahələr üçün istifadə edə bilərik (məsələn, admin yoxlaması).
     *
     * @param Request $request — Cari HTTP sorğusu
     * @return array<string, mixed> — JSON-a çevriləcək massiv
     */
    public function toArray(Request $request): array
    {
        return [
            /**
             * Sadə sahə — birbaşa model/DTO-dan götürülür.
             * $this->id əslində $this->resource->id deməkdir.
             */
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,

            /**
             * Tarix — UserDTO-da createdAt (camelCase) kimi saxlanır.
             */
            'created_at' => $this->createdAt,
        ];
    }
}
