<?php

declare(strict_types=1);

namespace Src\Notification\Infrastructure\Preferences;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * NOTIFICATION PREFERENCE MODEL
 * ==============================
 * İstifadəçinin bildiriş seçimlərini saxlayır.
 *
 * Hər sətir bir istifadəçi + bir event tipi üçün kanal seçimlərini təmsil edir.
 * Məsələn:
 * user_id: abc, event_type: order.created, email: true, sms: false, push: true
 * → Bu user sifariş yaradılanda email və push istəyir, SMS istəmir.
 */
class NotificationPreferenceModel extends Model
{
    use HasUuids;

    protected $connection = 'user_db';
    protected $table = 'notification_preferences';

    protected $fillable = [
        'id', 'user_id', 'event_type',
        'email_enabled', 'sms_enabled', 'push_enabled',
    ];

    protected function casts(): array
    {
        return [
            'email_enabled' => 'boolean',
            'sms_enabled' => 'boolean',
            'push_enabled' => 'boolean',
        ];
    }
}
