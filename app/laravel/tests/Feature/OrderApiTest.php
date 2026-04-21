<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Src\Order\Infrastructure\Models\OrderModel;
use Src\User\Infrastructure\Models\UserModel;
use Tests\TestCase;

/**
 * SİFARİŞ API ENDPOINT-LƏRİNİN FEATURE TESTLƏRİ
 * ================================================
 * Bu testlər Order bounded context-inin HTTP API-sini yoxlayır.
 *
 * YOXLANILAN ENDPOINT-LƏR:
 * - POST /api/orders — yeni sifariş yaratma (auth tələb edir)
 * - GET /api/orders/{id} — sifariş detallarını alma (auth tələb edir)
 * - POST /api/orders/{id}/cancel — sifarişi ləğv etmə (auth tələb edir)
 *
 * BÜTÜN sifariş endpoint-ləri auth:sanctum middleware ilə qorunur.
 * actingAs() metodu ilə autentifikasiya olunmuş istifadəçi kimi davranırıq.
 *
 * CQRS PRİNSİPİ:
 * POST endpoint-ləri Command Bus-a, GET endpoint-ləri Query Bus-a yönləndirilir.
 * Bu testlər tam HTTP sorğu dövrünü yoxlayır — route-dan DB-yə qədər.
 */
class OrderApiTest extends TestCase
{
    use RefreshDatabase;

    // ===========================
    // POST /api/orders TESTLƏRİ
    // ===========================

    /**
     * Autentifikasiya olmadan sifariş yaratmaq mümkün olmamalıdır.
     * auth:sanctum middleware 401 və ya 403 cavabı qaytarmalıdır.
     *
     * Bu test middleware-in düzgün işlədiyini yoxlayır —
     * token olmadan heç bir sifariş endpoint-inə daxil olmaq olmaz.
     */
    public function test_unauthenticated_user_cannot_create_order(): void
    {
        // Arrange — boş payload hazırlayırıq
        $payload = [
            'user_id' => fake()->uuid(),
            'items' => [
                ['product_id' => fake()->uuid(), 'quantity' => 1, 'price' => 29.99],
            ],
            'address' => [
                'street' => 'Test küçəsi',
                'city' => 'Bakı',
                'zip' => 'AZ1000',
                'country' => 'Azərbaycan',
            ],
        ];

        // Act — auth olmadan sorğu göndəririk
        $response = $this->postJson('/api/orders', $payload);

        // Assert — 401 Unauthorized gözləyirik (Sanctum middleware rədd edir)
        $response->assertStatus(401);
    }

    /**
     * Boş payload ilə sifariş yaratmaq validasiya xətası verməlidir.
     * CreateOrderRequest-in rules() metodu bütün sahələri tələb edir.
     *
     * Gözlənilən validasiya xətaları:
     * - user_id: mütləq daxil edilməlidir
     * - items: mütləq daxil edilməlidir, minimum 1 element
     * - address sahələri: hamısı mütləqdir
     */
    public function test_create_order_fails_with_empty_payload(): void
    {
        // Arrange — auth istifadəçi yaradırıq
        $user = UserModel::factory()->create();

        // Act — boş payload ilə sorğu göndəririk
        $response = $this->actingAs($user)
            ->postJson('/api/orders', []);

        // Assert — 422 validasiya xətası gözləyirik
        // CreateOrderRequest-in rules() metoduna görə bu sahələr tələb olunur
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_id', 'items']);
    }

    /**
     * Items massivində quantity 0 və ya mənfi olarsa validasiya xətası olmalıdır.
     * items.*.quantity qaydası 'min:1' tələb edir — minimum 1 ədəd sifariş vermək lazımdır.
     */
    public function test_create_order_fails_with_invalid_item_quantity(): void
    {
        // Arrange — auth istifadəçi yaradırıq
        $user = UserModel::factory()->create();

        $payload = [
            'user_id' => $user->id,
            'items' => [
                [
                    'product_id' => fake()->uuid(),
                    'quantity' => 0, // Yanlış — minimum 1 olmalıdır
                    'price' => 29.99,
                ],
            ],
            'address' => [
                'street' => 'Test küçəsi',
                'city' => 'Bakı',
                'zip' => 'AZ1000',
                'country' => 'Azərbaycan',
            ],
        ];

        // Act — yanlış quantity ilə sorğu göndəririk
        $response = $this->actingAs($user)
            ->postJson('/api/orders', $payload);

        // Assert — items.0.quantity sahəsində validasiya xətası gözləyirik
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.quantity']);
    }

    /**
     * Ünvan sahələri olmadan sifariş yaratmaq mümkün olmamalıdır.
     * CreateOrderRequest address.street, address.city, address.zip, address.country tələb edir.
     */
    public function test_create_order_fails_without_address(): void
    {
        // Arrange — auth istifadəçi yaradırıq
        $user = UserModel::factory()->create();

        $payload = [
            'user_id' => $user->id,
            'items' => [
                ['product_id' => fake()->uuid(), 'quantity' => 1, 'price' => 29.99],
            ],
            // address sahəsi YOXdur — validasiya xətası olmalıdır
        ];

        // Act
        $response = $this->actingAs($user)
            ->postJson('/api/orders', $payload);

        // Assert — ünvan sahələrində validasiya xətası gözləyirik
        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'address.street',
                'address.city',
                'address.zip',
                'address.country',
            ]);
    }

    // ===========================
    // GET /api/orders/{id} TESTLƏRİ
    // ===========================

    /**
     * Autentifikasiya olmadan sifariş detallarını almaq mümkün olmamalıdır.
     * Order route-ları tamamilə auth:sanctum ilə qorunur.
     */
    public function test_unauthenticated_user_cannot_view_order(): void
    {
        // Arrange — fake UUID istifadə edirik
        $orderId = fake()->uuid();

        // Act — auth olmadan sorğu göndəririk
        $response = $this->getJson("/api/orders/{$orderId}");

        // Assert — 401 Unauthorized gözləyirik
        $response->assertStatus(401);
    }

    /**
     * Mövcud olmayan sifariş sorğulandıqda 404 cavabı qaytarılmalıdır.
     * OrderController::show() OrderModel::findOrFail() istifadə edir —
     * model tapılmadıqda ModelNotFoundException atılır, Laravel bunu 404-ə çevirir.
     */
    public function test_show_order_returns_404_for_nonexistent_order(): void
    {
        // Arrange — auth istifadəçi yaradırıq
        $user = UserModel::factory()->create();
        $fakeOrderId = fake()->uuid();

        // Act — mövcud olmayan sifariş sorğulayırıq
        $response = $this->actingAs($user)
            ->getJson("/api/orders/{$fakeOrderId}");

        // Assert — 404 Not Found gözləyirik
        $response->assertStatus(404);
    }

    /**
     * Sifariş sahibi öz sifarişini görə bilməlidir.
     * OrderPolicy::view() yoxlayır ki, user_id sifarişin user_id-sinə bərabərdir.
     * before() metodu is_active=true olan istifadəçiləri avtomatik buraxır.
     */
    public function test_owner_can_view_their_order(): void
    {
        // Arrange — istifadəçi və sifariş yaradırıq
        $user = UserModel::factory()->create();

        // Sifarişi birbaşa DB-yə yazırıq — CommandBus-dan keçməmək üçün
        $order = OrderModel::factory()->create([
            'user_id' => $user->id,
        ]);

        // Act — sifariş sahibi kimi sorğu göndəririk
        $response = $this->actingAs($user)
            ->getJson("/api/orders/{$order->id}");

        // Assert — uğurlu cavab gözləyirik
        // OrderPolicy::before() is_active=true üçün true qaytarır
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }

    // ====================================
    // POST /api/orders/{id}/cancel TESTLƏRİ
    // ====================================

    /**
     * Autentifikasiya olmadan sifarişi ləğv etmək mümkün olmamalıdır.
     * auth:sanctum middleware token olmadan 401 qaytarır.
     */
    public function test_unauthenticated_user_cannot_cancel_order(): void
    {
        // Arrange
        $orderId = fake()->uuid();

        // Act — auth olmadan ləğv sorğusu göndəririk
        $response = $this->postJson("/api/orders/{$orderId}/cancel");

        // Assert — 401 gözləyirik
        $response->assertStatus(401);
    }

    /**
     * Mövcud olmayan sifarişi ləğv etmək 404 qaytarmalıdır.
     * OrderController::cancel() OrderModel::findOrFail() çağırır.
     */
    public function test_cancel_nonexistent_order_returns_404(): void
    {
        // Arrange — auth istifadəçi
        $user = UserModel::factory()->create();
        $fakeOrderId = fake()->uuid();

        // Act — mövcud olmayan sifarişi ləğv etməyə çalışırıq
        $response = $this->actingAs($user)
            ->postJson("/api/orders/{$fakeOrderId}/cancel");

        // Assert — 404 Not Found gözləyirik
        $response->assertStatus(404);
    }

    /**
     * Sifariş sahibi pending statusdakı sifarişi ləğv edə bilməlidir.
     * OrderPolicy::cancel() yoxlayır:
     * 1. Sifariş istifadəçiyə məxsusdur (user_id uyğunluğu)
     * 2. Status pending və ya confirmed-dır
     *
     * before() metodu is_active=true olan istifadəçiləri avtomatik buraxır.
     */
    public function test_owner_can_cancel_pending_order(): void
    {
        // Arrange — istifadəçi və pending sifariş yaradırıq
        $user = UserModel::factory()->create();

        $order = OrderModel::factory()->pending()->create([
            'user_id' => $user->id,
        ]);

        // Act — sifariş sahibi olaraq ləğv sorğusu göndəririk
        $response = $this->actingAs($user)
            ->postJson("/api/orders/{$order->id}/cancel");

        // Assert — uğurlu cavab gözləyirik
        // Policy::before() is_active=true üçün icazə verir
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }
}
