<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\Notification\Application\Services\NotificationPreferenceService;

/**
 * NOTIFICATION PREFERENCE CONTROLLER
 * ====================================
 * İstifadəçinin bildiriş seçimlərini idarə etmək üçün API.
 *
 * GET  /api/notifications/preferences         → Bütün seçimləri gör
 * PUT  /api/notifications/preferences/{event} → Bir event üçün seçimi dəyiş
 *
 * NÜMUNƏ REQUEST:
 * PUT /api/notifications/preferences/order.created
 * { "email": true, "sms": false, "push": true }
 */
class NotificationPreferenceController extends Controller
{
    public function __construct(
        private NotificationPreferenceService $preferenceService,
    ) {}

    /**
     * GET /api/notifications/preferences
     * İstifadəçinin bütün bildiriş seçimləri.
     */
    public function index(Request $request): JsonResponse
    {
        $preferences = $this->preferenceService->getAllPreferences(
            $request->user()->id,
        );

        return ApiResponse::success(
            data: [
                'preferences' => $preferences,
                'available_events' => $this->preferenceService->availableEvents(),
            ],
        );
    }

    /**
     * PUT /api/notifications/preferences/{eventType}
     * Bir event tipi üçün kanal seçimlərini yenilə.
     */
    public function update(Request $request, string $eventType): JsonResponse
    {
        // Event tipini yoxla
        if (!in_array($eventType, $this->preferenceService->availableEvents())) {
            return ApiResponse::error("Naməlum event tipi: {$eventType}", code: 422);
        }

        $validated = $request->validate([
            'email' => 'required|boolean',
            'sms' => 'required|boolean',
            'push' => 'required|boolean',
        ]);

        $this->preferenceService->updatePreference(
            userId: $request->user()->id,
            eventType: $eventType,
            emailEnabled: $validated['email'],
            smsEnabled: $validated['sms'],
            pushEnabled: $validated['push'],
        );

        return ApiResponse::success(message: 'Bildiriş seçimi yeniləndi');
    }
}
