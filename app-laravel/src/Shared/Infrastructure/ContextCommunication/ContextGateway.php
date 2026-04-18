<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\ContextCommunication;

/**
 * ContextGateway - Bounded Context-lər arasında əlaqə interfeysi.
 *
 * ================================================================
 * BOUNDED CONTEXT KOMMUNİKASİYASI (Məhdud Kontekst Əlaqəsi)
 * ================================================================
 *
 * DDD-də hər Bounded Context müstəqil bir "dünya"dır:
 * - Product kontekstinin öz DB cədvəlləri, öz Entity-ləri var.
 * - Order kontekstinin öz DB cədvəlləri, öz Entity-ləri var.
 * - User kontekstinin öz DB cədvəlləri, öz Entity-ləri var.
 *
 * Əsas QAYDA: Kontekstlər arasında FOREIGN KEY olmamalıdır!
 * ────────────────────────────────────────────────────────────
 *
 * Niyə foreign key olmamalıdır?
 * 1. Kontekstlər bir-birindən asılı olmamalıdır (Loose Coupling - Zəif Bağlılıq).
 * 2. Hər kontekst müstəqil deploy edilə bilməlidir.
 * 3. Gələcəkdə microservice-ə keçsək, foreign key işləməyəcək
 *    (çünki ayrı DB-lər olacaq).
 * 4. Bir kontekstin DB sxemini dəyişmək digərini pozmamalıdır.
 *
 * Bəs kontekstlər bir-birinin məlumatına necə çatır?
 * ──────────────────────────────────────────────────
 * CAVAB: Bu interfeys vasitəsilə! ContextGateway bir "daxili API" rolunu oynayır.
 *
 * Məsələn: Order kontekstinə məhsulun adı lazımdır.
 * - YANLIŞ: Order cədvəlindən Product cədvəlinə foreign key + JOIN.
 * - DOĞRU: ContextGateway::getProductById() çağırırıq.
 *
 * Bu yanaşmanın üstünlükləri:
 * - Kontekst sərhədləri qorunur.
 * - İmplementasiyanı dəyişmək asandır (DB sorğusu -> HTTP çağırışı).
 * - Test etmək asandır (mock edə bilərik).
 *
 * Monolit vs Microservice fərqi:
 * ─────────────────────────────
 * Monolitdə: Bu interfeys eyni prosesdə DB-yə birbaşa sorğu göndərir.
 * Microservice-də: Bu interfeys HTTP/gRPC vasitəsilə digər servislərə müraciət edir.
 * Kod DEYİŞMİR, yalnız implementasiya dəyişir — bu Interface Segregation-un gücüdür.
 *
 * Qaytarılan array formatı:
 * - array istifadə edirik, Entity yox — çünki digər kontekstin Entity-sini bilmirik.
 * - Hər kontekst yalnız öz Entity-lərini tanıyır.
 * - array "ümumi dil"dir, hər kontekst başa düşür.
 */
interface ContextGateway
{
    /**
     * User kontekstindən istifadəçi məlumatını əldə edir.
     *
     * Nə vaxt istifadə olunur?
     * - Order kontekstində sifarişə istifadəçi adını əlavə etmək üçün.
     * - Payment kontekstində ödəyicinin e-poçtunu tapmaq üçün.
     *
     * @param string $userId İstifadəçi ID-si (UUID formatında)
     * @return array|null ['id' => '...', 'name' => '...', 'email' => '...'] və ya null
     */
    public function getUserById(string $userId): ?array;

    /**
     * Product kontekstindən məhsul məlumatını əldə edir.
     *
     * Nə vaxt istifadə olunur?
     * - Order kontekstində sifarişə məhsul adı və qiymətini əlavə etmək üçün.
     * - Payment kontekstində ödəniş məbləğini yoxlamaq üçün.
     *
     * @param string $productId Məhsul ID-si (UUID formatında)
     * @return array|null ['id' => '...', 'name' => '...', 'price' => ...] və ya null
     */
    public function getProductById(string $productId): ?array;

    /**
     * Order kontekstindən sifariş məlumatını əldə edir.
     *
     * Nə vaxt istifadə olunur?
     * - Payment kontekstində ödənişin hansı sifarişə aid olduğunu bilmək üçün.
     * - User kontekstində istifadəçinin sifariş tarixçəsini göstərmək üçün.
     *
     * @param string $orderId Sifariş ID-si (UUID formatında)
     * @return array|null ['id' => '...', 'status' => '...', 'total' => ...] və ya null
     */
    public function getOrderById(string $orderId): ?array;
}
