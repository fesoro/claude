<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Src\Order\Infrastructure\ReadModel\ProjectionRebuilder;

/**
 * PROJEKSİYA YENİDƏN QURMA ARTISAN KOMANDASI
 * ==============================================
 *
 * Bu komanda proyeksiyanı (Read Model) sıfırdan yenidən qurur.
 * Event Store-dakı bütün event-ləri oxuyub Read Model-i təzələyir.
 *
 * İSTİFADƏ:
 * =========
 *   php artisan projection:rebuild
 *
 * NƏ VAXT İSTİFADƏ ETMƏLİ?
 * =========================
 * 1. Proyeksiyada bug tapılıb düzəldildikdə → rebuild ilə düzgün datanı bərpa edin.
 * 2. Read Model-ə yeni sahə əlavə edildikdə → rebuild ilə keçmiş datanı doldurun.
 * 3. Read Model DB-si xərab olduqda → rebuild ilə sıfırdan bərpa edin.
 * 4. Test mühitində → təmiz Read Model ilə testləri icra edin.
 *
 * ANALOGİYA:
 * ==========
 * Kompüter yavaşlayanda "yenidən başlat" (restart) edirsiniz.
 * Bu komanda Read Model üçün "yenidən başlat"dır.
 * Bütün keçmiş data (Event Store) sağlamdır — yalnız göstəriş təbəqəsi (Read Model) yenilənir.
 *
 * TƏHLÜKƏSİZLİK:
 * ===============
 * Bu komanda Read Model-i tamamilə SİLİR və yenidən yaradır.
 * Production mühitdə diqqətli istifadə edin!
 * Rebuild zamanı oxuma sorğuları köhnə və ya boş data qaytara bilər.
 *
 * Real layihədə əlavə tədbirlər:
 * - Rebuild zamanı maintenance mode aktiv etmək.
 * - Yeni Read Model-i ayrı cədvəldə qurmaq, sonra swap etmək (blue-green deployment).
 * - Rebuild prosesini queue job kimi icra etmək (uzun sürdükdə).
 */
class RebuildProjectionCommand extends Command
{
    /**
     * Komandanın adı və imzası.
     * Terminal-dən "php artisan projection:rebuild" yazaraq çağırılır.
     *
     * Artisan komanda konvensiyası: modul:əməliyyat
     * Nümunələr: cache:clear, queue:work, migrate:rollback
     */
    protected $signature = 'projection:rebuild';

    /**
     * Komandanın təsviri — "php artisan list" çıxışında görünür.
     * Azərbaycan dilində yazırıq ki, komandanın məqsədi aydın olsun.
     */
    protected $description = 'Order proyeksiyasını (Read Model) Event Store-dan sıfırdan yenidən qurur';

    /**
     * KOMANDANI İCRA ET
     * ==================
     *
     * Laravel Artisan komandalarında handle() metodu komanda çağırıldıqda icra olunur.
     * Dependency Injection avtomatik işləyir — Laravel Service Container
     * ProjectionRebuilder-i yaradıb buraya verir.
     *
     * İCRA AXINI:
     * 1. İstifadəçiyə "başlayır" mesajı göstər.
     * 2. Vaxtı ölç (nə qədər çəkdiyini bilmək üçün).
     * 3. ProjectionRebuilder::rebuild() çağır.
     * 4. Nəticəni göstər (neçə event emal olundu, nə qədər çəkdi).
     *
     * @param ProjectionRebuilder $rebuilder DI vasitəsilə inject olunur
     * @return int Komandanın çıxış kodu (0 = uğurlu, 1 = xəta)
     */
    public function handle(ProjectionRebuilder $rebuilder): int
    {
        /**
         * info() — mavi rəngdə məlumat mesajı göstərir.
         * Artisan-ın digər çıxış metodları:
         * - info(): mavi — məlumat
         * - warn(): sarı — xəbərdarlıq
         * - error(): qırmızı — xəta
         * - line(): ağ — adi mətn
         * - newLine(): boş sətir
         */
        $this->info('Order proyeksiyası yenidən qurulur...');
        $this->warn('DİQQƏT: Read Model tamamilə silinəcək və sıfırdan yaradılacaq!');
        $this->newLine();

        /**
         * Vaxtı ölçmək üçün başlanğıc vaxtı qeyd edirik.
         * microtime(true) millisaniyə dəqiqliyində vaxt qaytarır.
         * Monitoring və performans analizi üçün faydalıdır.
         */
        $startTime = microtime(true);

        try {
            /**
             * Əsas iş burada baş verir:
             * 1. Read Model TRUNCATE olunur (tamamilə silinir).
             * 2. Event Store-dan bütün event-lər oxunur.
             * 3. Hər event Projeksiyon-a göndərilir → Read Model yenilənir.
             * 4. Emal olunan event sayı qaytarılır.
             */
            $eventCount = $rebuilder->rebuild();

            /** Keçən vaxtı hesabla — saniyə cinsindən, 2 rəqəm dəqiqliklə */
            $elapsed = round(microtime(true) - $startTime, 2);

            $this->newLine();
            $this->info("Proyeksiya uğurla yenidən quruldu!");
            $this->table(
                ['Parametr', 'Dəyər'],
                [
                    ['Emal olunan event sayı', $eventCount],
                    ['Keçən vaxt', "{$elapsed} saniyə"],
                ]
            );

            /**
             * Çıxış kodu 0 = uğurlu icra.
             * Unix/Linux konvensiyası: 0 = success, 0-dan fərqli = error.
             * self::SUCCESS Laravel konstantıdır, dəyəri 0-dır.
             */
            return self::SUCCESS;

        } catch (\Throwable $e) {
            /**
             * İstənilən xəta baş verərsə tutulur və istifadəçiyə göstərilir.
             * \Throwable — Exception və Error-un hər ikisini tutur.
             *
             * Mümkün xətalar:
             * - DB bağlantı xətası
             * - Event Store cədvəli mövcud deyil
             * - JSON decode xətası (pozulmuş payload)
             * - Memory limit (çox sayda event)
             */
            $this->newLine();
            $this->error("Proyeksiya yenidən qurularkən xəta baş verdi!");
            $this->error("Xəta: {$e->getMessage()}");

            /**
             * -v (verbose) rejimində tam stack trace göstərilir.
             * Bu, debug üçün çox faydalıdır.
             * Normal rejimdə yalnız xəta mesajı göstərilir — daha təmiz çıxış.
             */
            if ($this->getOutput()->isVerbose()) {
                $this->newLine();
                $this->error("Stack trace:");
                $this->line($e->getTraceAsString());
            }

            /**
             * Çıxış kodu 1 = xəta ilə bitdi.
             * self::FAILURE Laravel konstantıdır, dəyəri 1-dir.
             */
            return self::FAILURE;
        }
    }
}
