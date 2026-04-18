<?php

declare(strict_types=1);

namespace Src\Notification\Application\Services;

use Src\Notification\Infrastructure\Preferences\NotificationPreferenceModel;

/**
 * NOTIFICATION PREFERENCE SERVICE
 * ================================
 * İstifadəçinin bildiriş seçimlərini idarə edir.
 *
 * İSTİFADƏ AXINI:
 * 1. Notification göndərmədən əvvəl bu service-ə müraciət et
 * 2. İstifadəçi bu event üçün hansı kanalları istəyir?
 * 3. Yalnız aktiv kanallar vasitəsilə göndər
 *
 * DEFAULT DAVRANIŞ:
 * Əgər istifadəçinin heç bir seçimi yoxdursa (yeni user),
 * default olaraq email aktiv, SMS deaktiv, push aktiv qaytarılır.
 *
 * EVENT TİPLƏRİ:
 * - order.created: Sifariş yaradıldı
 * - order.shipped: Sifariş göndərildi
 * - payment.completed: Ödəniş tamamlandı
 * - payment.failed: Ödəniş uğursuz oldu
 * - product.low_stock: Stok azalıb (admin üçün)
 * - marketing: Kampaniya/endirim (opt-in tələb olunur!)
 */
class NotificationPreferenceService
{
    /**
     * İstifadəçinin verilən event üçün aktiv kanallarını qaytar.
     *
     * @return array ['email' => bool, 'sms' => bool, 'push' => bool]
     */
    public function getChannels(string $userId, string $eventType): array
    {
        $preference = NotificationPreferenceModel::where('user_id', $userId)
            ->where('event_type', $eventType)
            ->first();

        // Default dəyərlər — preference tapılmazsa
        if (!$preference) {
            return $this->defaultChannels($eventType);
        }

        return [
            'email' => $preference->email_enabled,
            'sms' => $preference->sms_enabled,
            'push' => $preference->push_enabled,
        ];
    }

    /**
     * İstifadəçinin bütün seçimlərini qaytar.
     */
    public function getAllPreferences(string $userId): array
    {
        $preferences = NotificationPreferenceModel::where('user_id', $userId)->get();

        $result = [];
        foreach ($preferences as $pref) {
            $result[$pref->event_type] = [
                'email' => $pref->email_enabled,
                'sms' => $pref->sms_enabled,
                'push' => $pref->push_enabled,
            ];
        }

        // Olmayan event tipləri üçün default əlavə et
        foreach ($this->availableEvents() as $event) {
            if (!isset($result[$event])) {
                $result[$event] = $this->defaultChannels($event);
            }
        }

        return $result;
    }

    /**
     * Seçimi yenilə və ya yarat (upsert).
     */
    public function updatePreference(
        string $userId,
        string $eventType,
        bool $emailEnabled,
        bool $smsEnabled,
        bool $pushEnabled,
    ): void {
        NotificationPreferenceModel::updateOrCreate(
            ['user_id' => $userId, 'event_type' => $eventType],
            [
                'email_enabled' => $emailEnabled,
                'sms_enabled' => $smsEnabled,
                'push_enabled' => $pushEnabled,
            ],
        );
    }

    /**
     * Mövcud event tipləri.
     */
    public function availableEvents(): array
    {
        return [
            'order.created',
            'order.shipped',
            'payment.completed',
            'payment.failed',
            'product.low_stock',
            'marketing',
        ];
    }

    /**
     * Default kanal seçimləri.
     * marketing üçün default olaraq hamısı deaktivdir (opt-in model).
     */
    private function defaultChannels(string $eventType): array
    {
        if ($eventType === 'marketing') {
            return ['email' => false, 'sms' => false, 'push' => false];
        }

        return ['email' => true, 'sms' => false, 'push' => true];
    }
}
