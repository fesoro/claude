<?php

/**
 * BROADCAST CHANNELS (Kanal Avtorizasiyası)
 * ==========================================
 * Private və Presence channel-lar üçün auth qaydaları burada təyin olunur.
 *
 * NECƏ İŞLƏYİR?
 * 1. Frontend Echo.private('orders.123') ilə kanala qoşulmaq istəyir
 * 2. Echo avtomatik POST /broadcasting/auth sorğusu göndərir
 * 3. Laravel bu fayldakı callback-i çağırır
 * 4. Callback true qaytarsa → bağlantıya icazə verilir
 * 5. false qaytarsa → 403 Forbidden, bağlantı rədd edilir
 *
 * TƏHLÜKƏSİZLİK:
 * Bu callback olmasa, hər kəs istənilən private channel-ı dinləyə bilər!
 * Məsələn: Hacker 'orders.456' kanalını dinləyib başqasının sifarişini görə bilər.
 * Callback bunu yoxlayır: "Bu istifadəçi bu kanalı dinləyə bilərmi?"
 *
 * FRONTEND QOŞULMASI (Laravel Echo + Pusher/Reverb):
 * ═══════════════════════════════════════════════════
 *
 * import Echo from 'laravel-echo';
 * import Pusher from 'pusher-js';
 *
 * window.Echo = new Echo({
 *     broadcaster: 'reverb',  // və ya 'pusher'
 *     key: 'your-reverb-key',
 *     wsHost: 'localhost',
 *     wsPort: 8080,
 *     forceTLS: false,
 * });
 *
 * // Private channel dinlə (auth tələb olunur)
 * Echo.private(`orders.${userId}`)
 *     .listen('OrderStatusChanged', (event) => {
 *         console.log('Status:', event.new_status);
 *     });
 *
 * // Public channel dinlə (auth lazım deyil)
 * Echo.channel('admin-alerts')
 *     .listen('LowStockAlert', (event) => {
 *         console.log('Low stock:', event.product_name);
 *     });
 */

use Illuminate\Support\Facades\Broadcast;

/**
 * PRIVATE: orders.{userId}
 * Yalnız sifariş sahibi öz kanalını dinləyə bilər.
 *
 * $user → auth olunmuş istifadəçi (Sanctum/Session)
 * $userId → kanal adındakı {userId} parametri
 *
 * return true → icazə var
 * return false → 403
 */
Broadcast::channel('orders.{userId}', function ($user, string $userId) {
    return $user->id === $userId;
});

/**
 * PRIVATE: payments.{userId}
 * Yalnız ödəniş edən istifadəçi nəticəni görə bilər.
 */
Broadcast::channel('payments.{userId}', function ($user, string $userId) {
    return $user->id === $userId;
});

/**
 * PUBLIC: admin-alerts
 * Public channel-lar auth tələb etmir, burada callback lazım deyil.
 * Amma real proyektdə PrivateChannel + admin role yoxlaması olmalıdır:
 *
 * Broadcast::channel('admin-alerts', function ($user) {
 *     return $user->is_admin; // yalnız admin-lər
 * });
 */
