<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Src\Order\Infrastructure\Models\OrderModel;
use Src\User\Infrastructure\Models\UserModel;
use Tests\TestCase;

/**
 * ÖDƏNİŞ API ENDPOINT-LƏRİNİN FEATURE TESTLƏRİ
 * ================================================
 * Bu testlər Payment bounded context-inin HTTP API-sini yoxlayır.
 *
 * YOXLANILAN ENDPOINT-LƏR:
 * - POST /api/payments/process — ödənişi emal etmə (auth tələb edir)
 *
 * Ödəniş endpoint-ləri auth:sanctum middleware ilə qorunur.
 * Anonim ödəniş mümkün deyil — həmişə autentifikasiya lazımdır.
 *
 * STRATEGY PATTERN:
 * payment_method sahəsinə görə müvafiq gateway seçilir:
 * - credit_card → CreditCardGateway
 * - paypal → PayPalGateway
 * - bank_transfer → BankTransferGateway
 *
 * ProcessPaymentRequest validasiya qaydaları:
 * - order_id: UUID formatında, orders cədvəlində mövcud olmalı
 * - amount: ədəd, müsbət olmalı (ValidMoneyRule)
 * - currency: USD, EUR və ya AZN
 * - payment_method: credit_card, paypal və ya bank_transfer
 */
class PaymentApiTest extends TestCase
{
    use RefreshDatabase;

    // ===================================
    // POST /api/payments/process TESTLƏRİ
    // ===================================

    /**
     * Autentifikasiya olmadan ödəniş emal etmək mümkün olmamalıdır.
     * auth:sanctum middleware token olmadan 401 Unauthorized qaytarır.
     *
     * Bu test təhlükəsizlik baxımından vacibdir —
     * anonim istifadəçi heç vaxt ödəniş başlata bilməməlidir.
     */
    public function test_unauthenticated_user_cannot_process_payment(): void
    {
        // Arrange — ödəniş datası hazırlayırıq
        $payload = [
            'order_id' => fake()->uuid(),
            'amount' => 59.98,
            'currency' => 'USD',
            'payment_method' => 'credit_card',
        ];

        // Act — auth olmadan sorğu göndəririk
        $response = $this->postJson('/api/payments/process', $payload);

        // Assert — 401 Unauthorized gözləyirik
        $response->assertStatus(401);
    }

    /**
     * Boş payload ilə ödəniş emal etmək validasiya xətası verməlidir.
     * ProcessPaymentRequest-in rules() metodu bütün sahələri tələb edir:
     * - order_id, amount, currency, payment_method
     */
    public function test_process_payment_fails_with_empty_payload(): void
    {
        // Arrange — auth istifadəçi yaradırıq
        $user = UserModel::factory()->create();

        // Act — boş payload ilə sorğu göndəririk
        $response = $this->actingAs($user)
            ->postJson('/api/payments/process', []);

        // Assert — 422 validasiya xətası gözləyirik
        // Bütün tələb olunan sahələrdə xəta olmalıdır
        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'order_id',
                'amount',
                'payment_method',
            ]);
    }

    /**
     * Yanlış currency dəyəri ilə ödəniş emal etmək mümkün olmamalıdır.
     * ProcessPaymentRequest-də currency qaydası: 'in:USD,EUR,AZN'
     * Yalnız bu üç valyuta qəbul edilir — digərləri rədd olunur.
     */
    public function test_process_payment_fails_with_invalid_currency(): void
    {
        // Arrange — auth istifadəçi və sifariş yaradırıq
        $user = UserModel::factory()->create();

        // Sifariş yaradırıq ki, order_id exists validasiyasından keçsin
        $order = OrderModel::factory()->create([
            'user_id' => $user->id,
        ]);

        $payload = [
            'order_id' => $order->id,
            'amount' => 59.98,
            'currency' => 'GBP', // Yanlış — yalnız USD, EUR, AZN qəbul edilir
            'payment_method' => 'credit_card',
        ];

        // Act
        $response = $this->actingAs($user)
            ->postJson('/api/payments/process', $payload);

        // Assert — currency sahəsində validasiya xətası gözləyirik
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['currency']);
    }

    /**
     * Yanlış payment_method ilə ödəniş emal etmək mümkün olmamalıdır.
     * ProcessPaymentRequest-də payment_method qaydası: 'in:credit_card,paypal,bank_transfer'
     * Bu üç üsuldan başqa heç biri qəbul edilmir.
     */
    public function test_process_payment_fails_with_invalid_payment_method(): void
    {
        // Arrange
        $user = UserModel::factory()->create();

        $order = OrderModel::factory()->create([
            'user_id' => $user->id,
        ]);

        $payload = [
            'order_id' => $order->id,
            'amount' => 59.98,
            'currency' => 'USD',
            'payment_method' => 'bitcoin', // Yanlış — yalnız credit_card, paypal, bank_transfer
        ];

        // Act
        $response = $this->actingAs($user)
            ->postJson('/api/payments/process', $payload);

        // Assert — payment_method sahəsində validasiya xətası gözləyirik
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['payment_method']);
    }

    /**
     * Mövcud olmayan order_id ilə ödəniş emal etmək mümkün olmamalıdır.
     * ProcessPaymentRequest-də order_id qaydası: 'exists:orders,id'
     * Orders cədvəlində olmayan ID rədd edilir.
     */
    public function test_process_payment_fails_with_nonexistent_order_id(): void
    {
        // Arrange
        $user = UserModel::factory()->create();

        $payload = [
            'order_id' => fake()->uuid(), // Mövcud olmayan sifariş ID-si
            'amount' => 59.98,
            'currency' => 'USD',
            'payment_method' => 'credit_card',
        ];

        // Act
        $response = $this->actingAs($user)
            ->postJson('/api/payments/process', $payload);

        // Assert — order_id sahəsində validasiya xətası gözləyirik
        // 'exists:orders,id' qaydası mövcud olmayan ID-ni rədd edir
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['order_id']);
    }

    /**
     * Mənfi məbləğ ilə ödəniş emal etmək mümkün olmamalıdır.
     * ValidMoneyRule custom qaydası məbləğin müsbət olmasını tələb edir.
     */
    public function test_process_payment_fails_with_negative_amount(): void
    {
        // Arrange
        $user = UserModel::factory()->create();

        $order = OrderModel::factory()->create([
            'user_id' => $user->id,
        ]);

        $payload = [
            'order_id' => $order->id,
            'amount' => -10.00, // Mənfi məbləğ — ValidMoneyRule rədd edir
            'currency' => 'USD',
            'payment_method' => 'credit_card',
        ];

        // Act
        $response = $this->actingAs($user)
            ->postJson('/api/payments/process', $payload);

        // Assert — amount sahəsində validasiya xətası gözləyirik
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    // ===================================
    // GET /api/payments/{id} TESTLƏRİ
    // ===================================

    /**
     * Autentifikasiya olmadan ödəniş detallarını almaq mümkün olmamalıdır.
     * auth:sanctum middleware token olmadan rədd edir.
     */
    public function test_unauthenticated_user_cannot_view_payment(): void
    {
        // Arrange
        $paymentId = fake()->uuid();

        // Act — auth olmadan sorğu göndəririk
        $response = $this->getJson("/api/payments/{$paymentId}");

        // Assert — 401 Unauthorized gözləyirik
        $response->assertStatus(401);
    }

    /**
     * GET /api/payments/{id} endpoint-i hələ implement edilməyib.
     * PaymentController::show() 501 Not Implemented cavabı qaytarır.
     * Bu, gələcəkdə GetPaymentQuery implement edildikdə dəyişəcək.
     */
    public function test_show_payment_returns_501_not_implemented(): void
    {
        // Arrange — auth istifadəçi
        $user = UserModel::factory()->create();
        $paymentId = fake()->uuid();

        // Act
        $response = $this->actingAs($user)
            ->getJson("/api/payments/{$paymentId}");

        // Assert — 501 Not Implemented gözləyirik
        // PaymentController::show() placeholder cavab qaytarır
        $response->assertStatus(501)
            ->assertJson([
                'success' => false,
            ]);
    }
}
