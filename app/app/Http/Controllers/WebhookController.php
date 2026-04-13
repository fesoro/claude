<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Src\Shared\Infrastructure\Webhook\WebhookModel;

/**
 * WEBHOOK CONTROLLER
 * ==================
 * İstifadəçilərə webhook yaratmaq, silmək, siyahılamaq imkanı verir.
 *
 * WEBHOOK CRUD:
 * POST   /api/webhooks          → Yeni webhook yarat
 * GET    /api/webhooks          → Webhook siyahısı
 * DELETE /api/webhooks/{id}     → Webhook sil
 * PATCH  /api/webhooks/{id}     → Webhook aktivləşdir/deaktiv et
 */
class WebhookController extends Controller
{
    /**
     * POST /api/webhooks
     * Yeni webhook yarat.
     *
     * Request body:
     * {
     *   "url": "https://my-system.com/webhooks/callback",
     *   "events": ["order.created", "payment.completed", "payment.failed"]
     * }
     *
     * secret_key avtomatik yaradılır — istifadəçi bunu bir dəfə görür.
     * Bu key ilə HMAC imzanı yoxlaya bilər.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'url' => ['required', 'url', 'max:500'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['string', 'in:order.created,order.cancelled,order.paid,payment.completed,payment.failed,product.low_stock'],
        ]);

        $secretKey = Str::random(40);

        $webhook = WebhookModel::create([
            'user_id' => $request->user()->id,
            'url' => $validated['url'],
            'events' => $validated['events'],
            'secret_key' => $secretKey,
            'is_active' => true,
        ]);

        return ApiResponse::success(
            data: [
                'webhook_id' => $webhook->id,
                'secret_key' => $secretKey, // Yalnız BİR DƏFƏ göstərilir!
            ],
            message: 'Webhook yaradıldı. Secret key-i qeyd edin — bir daha göstərilməyəcək!',
            code: 201,
        );
    }

    /**
     * GET /api/webhooks
     * İstifadəçinin webhook-ları.
     */
    public function index(Request $request): JsonResponse
    {
        $webhooks = WebhookModel::where('user_id', $request->user()->id)
            ->select(['id', 'url', 'events', 'is_active', 'created_at'])
            ->get();

        return ApiResponse::success(data: $webhooks);
    }

    /**
     * DELETE /api/webhooks/{id}
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $webhook = WebhookModel::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $webhook->delete();

        return ApiResponse::success(message: 'Webhook silindi');
    }

    /**
     * PATCH /api/webhooks/{id}
     * Aktivləşdir/deaktiv et.
     */
    public function toggle(Request $request, string $id): JsonResponse
    {
        $webhook = WebhookModel::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $webhook->update(['is_active' => !$webhook->is_active]);

        return ApiResponse::success(
            data: ['is_active' => $webhook->is_active],
            message: $webhook->is_active ? 'Webhook aktivləşdirildi' : 'Webhook deaktiv edildi',
        );
    }
}
