<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Src\User\Infrastructure\Models\UserModel;
use Tests\TestCase;

/**
 * Product API endpoint-lərinin Feature Testləri.
 *
 * Bu testlər məhsul CRUD əməliyyatlarını yoxlayır:
 * - GET /api/products — bütün məhsulları siyahılama
 * - POST /api/products — yeni məhsul yaratma (auth tələb edir)
 * - PATCH /api/products/{id}/stock — stok yeniləmə
 *
 * Bəzi endpoint-lər (POST, PATCH) avtorizasiya tələb edir.
 * actingAs() ilə autentifikasiya olmuş istifadəçi kimi davranırıq.
 */
class ProductApiTest extends TestCase
{
    use RefreshDatabase;

    // ===========================
    // GET /api/products TESTLƏRİ
    // ===========================

    /**
     * Məhsul siyahısı endpoint-i düzgün işləməlidir.
     */
    public function test_list_products_returns_success(): void
    {
        // Act — bütün məhsulları sorğulayırıq
        $response = $this->getJson('/api/products');

        // Assert — uğurlu cavab qaytarılmalıdır
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }

    // ===========================
    // POST /api/products TESTLƏRİ
    // ===========================

    /**
     * Autentifikasiya olmuş istifadəçi məhsul yarada bilməlidir.
     * actingAs() ilə auth user kimi davranırıq.
     */
    public function test_authenticated_user_can_create_product(): void
    {
        // Arrange — auth istifadəçi yaradırıq
        $user = UserModel::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'password123',
            'is_active' => true,
        ]);

        $payload = [
            'name' => 'Test Məhsul',
            'price' => 29.99,
            'currency' => 'USD',
            'stock' => 100,
        ];

        // Act — actingAs() ilə auth user kimi sorğu göndəririk
        $response = $this->actingAs($user)
            ->postJson('/api/products', $payload);

        // Assert — 201 Created cavabı gözləyirik
        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['product_id'],
            ]);
    }

    /**
     * Autentifikasiya olmadan məhsul yaratmaq mümkün olmamalıdır.
     * 401 Unauthorized və ya 403 Forbidden cavabı gözləyirik.
     */
    public function test_unauthenticated_user_cannot_create_product(): void
    {
        // Arrange
        $payload = [
            'name' => 'Test Məhsul',
            'price' => 29.99,
            'currency' => 'USD',
            'stock' => 100,
        ];

        // Act — auth olmadan sorğu göndəririk
        $response = $this->postJson('/api/products', $payload);

        // Assert — 401 və ya 403 cavabı gözləyirik
        $response->assertStatus(403);
    }

    /**
     * Yanlış data ilə məhsul yaratmaq mümkün olmamalıdır — validasiya xətası.
     */
    public function test_create_product_fails_with_invalid_data(): void
    {
        // Arrange — auth istifadəçi
        $user = UserModel::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'password123',
            'is_active' => true,
        ]);

        // Boş payload — bütün sahələr tələb olunur
        $payload = [];

        // Act
        $response = $this->actingAs($user)
            ->postJson('/api/products', $payload);

        // Assert — 422 validasiya xətası
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'price', 'currency', 'stock']);
    }

    // =======================================
    // PATCH /api/products/{id}/stock TESTLƏRİ
    // =======================================

    /**
     * Autentifikasiya olmuş istifadəçi stoku yeniləyə bilməlidir.
     */
    public function test_authenticated_user_can_update_stock(): void
    {
        // Arrange — istifadəçi və məhsul yaradırıq
        $user = UserModel::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'password123',
            'is_active' => true,
        ]);

        // Əvvəlcə məhsul yaradırıq
        $createResponse = $this->actingAs($user)
            ->postJson('/api/products', [
                'name' => 'Test Məhsul',
                'price' => 29.99,
                'currency' => 'USD',
                'stock' => 100,
            ]);

        $productId = $createResponse->json('data.product_id');

        // Act — stoku artırırıq
        $response = $this->actingAs($user)
            ->patchJson("/api/products/{$productId}/stock", [
                'quantity' => 10,
                'operation' => 'increase',
            ]);

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }

    /**
     * Yanlış operation dəyəri ilə stok yeniləmə uğursuz olmalıdır.
     */
    public function test_update_stock_fails_with_invalid_operation(): void
    {
        // Arrange
        $user = UserModel::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'password123',
            'is_active' => true,
        ]);

        // Act — yanlış operation dəyəri
        $response = $this->actingAs($user)
            ->patchJson('/api/products/some-id/stock', [
                'quantity' => 10,
                'operation' => 'invalid_op',
            ]);

        // Assert — validasiya xətası
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['operation']);
    }
}
