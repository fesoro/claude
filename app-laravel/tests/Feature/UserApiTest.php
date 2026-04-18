<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FEATURE TEST NƏDİR?
 * ====================
 * Feature Test — tətbiqin API endpoint-lərini BÜTÖV şəkildə test edir.
 * Unit Test-dən fərqli olaraq, burada REAL HTTP sorğusu göndərilir və
 * bütün pipeline işləyir: Route → Middleware → Controller → Handler → DB → Response.
 *
 * UNIT TEST vs FEATURE TEST FƏRQI:
 * ================================
 * Unit Test:
 * - Tək bir sinfi/metodu test edir
 * - Xarici asılılıq yoxdur (DB, API yoxdur)
 * - Çox sürətli işləyir
 * - PHPUnit\Framework\TestCase istifadə edir
 *
 * Feature Test:
 * - Bütün sistemi test edir (end-to-end)
 * - Real DB istifadə edir (SQLite in-memory)
 * - Nisbətən yavaş işləyir (DB əməliyyatları olduğu üçün)
 * - Tests\TestCase istifadə edir (Laravel-in TestCase-i)
 *
 * RefreshDatabase TRAIT-İ:
 * ========================
 * Hər testdən əvvəl verilənlər bazasını sıfırlayır və migration-ları yenidən icra edir.
 * Beləliklə hər test təmiz DB ilə başlayır — testlər bir-birinə təsir etmir.
 * Bu trait database transaction istifadə edir — hər testin dəyişiklikləri geri alınır.
 *
 * actingAs() METODU:
 * ==================
 * Test zamanı autentifikasiya olmuş istifadəçi kimi davranmaq üçün istifadə olunur:
 *   $this->actingAs($user)->getJson('/api/...')
 * Bu, real login prosesini keçmədən, auth tələb edən endpoint-ləri test etməyə imkan verir.
 *
 * assertJson() METODU:
 * ====================
 * API cavabının JSON strukturunu yoxlamaq üçün istifadə olunur:
 *   $response->assertJson(['success' => true, 'data' => ['user_id' => '...']])
 * Yalnız göstərilən açar-dəyər cütlərini yoxlayır — cavabda əlavə sahələr olsa da xəta vermir.
 *
 * assertStatus() METODU:
 * ======================
 * HTTP status kodunu yoxlamaq üçün:
 *   $response->assertStatus(201) — 201 Created gözləyirik
 *   $response->assertStatus(422) — 422 Validation Error gözləyirik
 *   $response->assertStatus(404) — 404 Not Found gözləyirik
 */
class UserApiTest extends TestCase
{
    use RefreshDatabase;

    // ===========================
    // POST /api/users/register TESTLƏRİ
    // ===========================

    /**
     * Düzgün data ilə qeydiyyat uğurlu olmalıdır.
     * HTTP 201 Created cavabı və user_id qaytarılmalıdır.
     */
    public function test_register_user_successfully(): void
    {
        // Arrange — qeydiyyat üçün lazım olan data
        $payload = [
            'name' => 'Test İstifadəçi',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        // Act — POST sorğusu göndəririk
        $response = $this->postJson('/api/users/register', $payload);

        // Assert — uğurlu cavab yoxlanılır
        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['user_id'],
            ]);
    }

    /**
     * Boş data ilə qeydiyyat uğursuz olmalıdır — validasiya xətası (422).
     * Laravel Form Request avtomatik 422 qaytarır.
     */
    public function test_register_user_fails_with_validation_errors(): void
    {
        // Arrange — boş payload
        $payload = [];

        // Act
        $response = $this->postJson('/api/users/register', $payload);

        // Assert — 422 Unprocessable Entity və xəta mesajları
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    /**
     * Qısa şifrə ilə qeydiyyat uğursuz olmalıdır.
     */
    public function test_register_user_fails_with_short_password(): void
    {
        // Arrange — şifrə 8 simvoldan az
        $payload = [
            'name' => 'Test',
            'email' => 'test@example.com',
            'password' => '123',
            'password_confirmation' => '123',
        ];

        // Act
        $response = $this->postJson('/api/users/register', $payload);

        // Assert — password sahəsində xəta olmalıdır
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    /**
     * Eyni email ilə ikinci qeydiyyat uğursuz olmalıdır (unique constraint).
     */
    public function test_register_user_fails_with_duplicate_email(): void
    {
        // Arrange — ilk qeydiyyat
        $payload = [
            'name' => 'İstifadəçi 1',
            'email' => 'duplicate@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];
        $this->postJson('/api/users/register', $payload);

        // Act — eyni email ilə ikinci qeydiyyat
        $payload['name'] = 'İstifadəçi 2';
        $response = $this->postJson('/api/users/register', $payload);

        // Assert — email sahəsində unique xətası olmalıdır
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    // ===========================
    // GET /api/users/{id} TESTLƏRİ
    // ===========================

    /**
     * Mövcud istifadəçini ID ilə tapmaq mümkün olmalıdır.
     */
    public function test_show_user_returns_user_data(): void
    {
        // Arrange — əvvəlcə istifadəçi yaradırıq
        $registerResponse = $this->postJson('/api/users/register', [
            'name' => 'Orxan',
            'email' => 'orxan@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $userId = $registerResponse->json('data.user_id');

        // Act — istifadəçini ID ilə sorğulayırıq
        $response = $this->getJson("/api/users/{$userId}");

        // Assert — istifadəçi datası qaytarılmalıdır
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }

    /**
     * Mövcud olmayan istifadəçi üçün 404 qaytarılmalıdır.
     */
    public function test_show_user_returns_404_for_nonexistent_user(): void
    {
        // Arrange — mövcud olmayan UUID
        $fakeId = '00000000-0000-0000-0000-000000000000';

        // Act
        $response = $this->getJson("/api/users/{$fakeId}");

        // Assert — 404 Not Found
        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
            ]);
    }
}
