<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * ================================================================
 * LARAVEL SCHEDULER (Planlaşdırıcı) — ƏTRAFLI İZAH
 * ================================================================
 *
 * Laravel Scheduler nədir?
 * ─────────────────────────
 * Əvvəllər hər cron tapşırığı üçün serverdə ayrıca crontab yazırdıq:
 *   * * * * * php artisan outbox:publish
 *   */5 * * * * php artisan circuit:check
 *   0 0 * * 0 php artisan outbox:prune
 *
 * Bu, bir neçə problemə səbəb olurdu:
 *   1. Crontab serverdədir, kodla birlikdə version control-da deyil
 *   2. Yeni developer serverin crontab-ını bilmir
 *   3. Hər server üçün ayrıca konfiqurasiya lazımdır
 *
 * Laravel Scheduler həll:
 *   - Bütün planlaşdırılmış tapşırıqlar BU FAYLDA yazılır (kod ilə birlikdə)
 *   - Serverdə yalnız BİR crontab lazımdır:
 *     * * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
 *   - Hər dəqiqə Laravel özü yoxlayır: "İndi icra olunmalı tapşırıq varmı?"
 *
 * Scheduler-in iş prinsipi:
 * ─────────────────────────
 *   Cron (hər dəqiqə) → schedule:run → Bu faylı oxuyur → Vaxtı gəlib? → İcra et
 *
 * Əsas metodlar:
 * ─────────────────────────
 * - everyMinute()       → Hər dəqiqə
 * - everyFiveMinutes()  → Hər 5 dəqiqə
 * - hourly()            → Hər saat
 * - daily()             → Hər gün gecə yarısı
 * - weekly()            → Hər həftə bazar günü
 * - monthly()           → Hər ayın 1-i
 *
 * Əlavə seçimlər:
 * - withoutOverlapping() → Əvvəlki hələ bitməyibsə, yenisini başlatma
 * - onOneServer()        → Bir neçə server varsa, yalnız birində icra et
 * - runInBackground()    → Arxa planda icra et (digər tapşırıqları gözlətmə)
 */

/**
 * ─────────────────────────────────────────────
 * 1. OUTBOX MESAJLARINI DƏRC ET — Hər dəqiqə
 * ─────────────────────────────────────────────
 *
 * Transactional Outbox pattern-i ilə işləyir:
 * - Göndərilməmiş mesajları outbox_messages cədvəlindən oxuyur
 * - Message broker-ə (RabbitMQ, Redis) göndərir
 * - Göndərilmiş mesajları "published" kimi işarələyir
 *
 * withoutOverlapping() NİYƏ VACİBDİR?
 * ────────────────────────────────────
 * Əgər Job 1 dəqiqədən çox çəksə, növbəti dəqiqə yeni Job başlayar.
 * İki Job eyni mesajları oxuyub iki dəfə göndərər (dublikat!).
 * withoutOverlapping() buna icazə vermir — əvvəlki bitənə qədər gözləyir.
 *
 * Texniki olaraq: Bu, Redis/file lock istifadə edir.
 * Lock açarı: schedule-{command_fingerprint}
 */
Schedule::job(new \App\Jobs\PublishOutboxMessagesJob())
    ->everyMinute()
    ->withoutOverlapping()
    ->description('Outbox mesajlarını dərc et (Transactional Outbox pattern)');

/**
 * ─────────────────────────────────────────────
 * 2. CIRCUIT BREAKER YOXLAMASI — Hər 5 dəqiqə
 * ─────────────────────────────────────────────
 *
 * Circuit Breaker pattern-i ilə işləyir:
 * - Xarici xidmətlərin (ödəniş, göndərmə) sağlamlığını yoxlayır
 * - "Open" vəziyyətdəki circuit-ları "half-open" etməyə cəhd edir
 * - Xidmət bərpa olubsa, circuit-u "closed" edir (normal vəziyyət)
 *
 * Niyə hər 5 dəqiqə?
 * ──────────────────
 * - Hər dəqiqə: çox tez-tez, xarici xidmətə lazımsız yük
 * - Hər 30 dəqiqə: çox gec, xidmət bərpa olsa belə uzun müddət istifadə edə bilmərik
 * - 5 dəqiqə: optimal balans — problem 5 dəqiqə ərzində aşkar olunur
 */
Schedule::job(new \App\Jobs\CheckCircuitBreakerJob())
    ->everyFiveMinutes()
    ->description('Circuit Breaker vəziyyətlərini yoxla');

/**
 * ─────────────────────────────────────────────
 * 3. KÖHNƏ OUTBOX MESAJLARINI SİL — Həftəlik
 * ─────────────────────────────────────────────
 *
 * "Pruning" (budama) — köhnə, artıq lazım olmayan məlumatları silmə prosesi.
 *
 * Niyə silirik?
 * ─────────────
 * - outbox_messages cədvəli zamanla böyüyür (hər əməliyyat yeni mesaj yaradır)
 * - 7 gündən köhnə, artıq dərc edilmiş mesajlar lazımsızdır
 * - Cədvəl böyüdükcə sorğular yavaşlayır (indeks boyutu artır)
 * - Disk sahəsi boşaldılır
 *
 * Niyə həftəlik?
 * ─────────────
 * - Gündəlik: çox tez-tez, lazımsız yük
 * - Aylıq: cədvəl çox böyüyə bilər
 * - Həftəlik: optimal — cədvəl idarə olunan ölçüdə qalır
 *
 * 7 gün niyə?
 * ──────────
 * - Problem yaransa, son 7 günün mesajlarını araşdıra bilərik
 * - Audit/debugging üçün kifayət qədər tarixçə qalır
 * - Çox köhnə mesajları saxlamağın faydası yoxdur
 *
 * Artisan command istifadə edirik çünki bu, sadə DB əməliyyatıdır:
 * DELETE FROM outbox_messages WHERE published_at < NOW() - INTERVAL 7 DAY
 */
Schedule::command('outbox:prune --days=7')
    ->weekly()
    ->description('7 gündən köhnə outbox mesajlarını sil (pruning)');
