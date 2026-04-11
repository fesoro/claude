# Testing

## 1. Testing növləri və Laravel-də necə yazılır?

### Unit Test — tək bir sinif/metodu test edir
```php
class OrderCalculatorTest extends TestCase {
    public function test_calculates_subtotal(): void {
        $calculator = new OrderCalculator();

        $items = [
            new CartItem(price: 10.00, quantity: 2),
            new CartItem(price: 25.50, quantity: 1),
        ];

        $this->assertEquals(45.50, $calculator->subtotal($items));
    }

    public function test_applies_discount(): void {
        $calculator = new OrderCalculator();

        $result = $calculator->applyDiscount(100.00, percentage: 15);

        $this->assertEquals(85.00, $result);
    }
}
```

### Feature Test — bir neçə komponentin birlikdə işləməsini test edir
```php
class OrderWorkflowTest extends TestCase {
    use RefreshDatabase;

    public function test_user_can_place_order(): void {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 29.99, 'stock' => 5]);

        $response = $this->actingAs($user)
            ->postJson('/api/orders', [
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 2],
                ],
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'total' => 59.98,
        ]);
        $this->assertEquals(3, $product->fresh()->stock);
    }
}
```

---

## 2. Mocking və Faking

```php
class PaymentServiceTest extends TestCase {
    // Mock — sinifin davranışını simulyasiya et
    public function test_processes_payment(): void {
        $gateway = $this->createMock(PaymentGateway::class);
        $gateway->expects($this->once())
            ->method('charge')
            ->with(99.99)
            ->willReturn(new PaymentResult(success: true, transactionId: 'txn_123'));

        $service = new PaymentService($gateway);
        $result = $service->processPayment(99.99);

        $this->assertTrue($result->success);
    }

    // Laravel Fakes
    public function test_sends_notification_on_order(): void {
        Notification::fake();
        Mail::fake();
        Event::fake([OrderPlaced::class]);
        Queue::fake();

        $user = User::factory()->create();
        $order = Order::factory()->for($user)->create();

        // Act
        app(OrderService::class)->complete($order);

        // Assert
        Notification::assertSentTo($user, OrderCompletedNotification::class);
        Mail::assertSent(OrderConfirmationMail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });
        Event::assertDispatched(OrderPlaced::class);
        Queue::assertPushed(ProcessPayment::class);
    }

    // HTTP fake — xarici API-ləri simulyasiya et
    public function test_fetches_exchange_rate(): void {
        Http::fake([
            'api.exchangerate.com/*' => Http::response([
                'rates' => ['USD' => 1.7],
            ], 200),
        ]);

        $rate = app(ExchangeRateService::class)->getRate('USD');

        $this->assertEquals(1.7, $rate);
        Http::assertSent(fn ($request) =>
            str_contains($request->url(), 'api.exchangerate.com')
        );
    }

    // Storage fake
    public function test_uploads_avatar(): void {
        Storage::fake('avatars');

        $file = UploadedFile::fake()->image('avatar.jpg', 200, 200);

        $this->actingAs(User::factory()->create())
            ->postJson('/api/avatar', ['avatar' => $file])
            ->assertOk();

        Storage::disk('avatars')->assertExists('avatars/' . $file->hashName());
    }
}
```

---

## 3. Database Testing

```php
class UserRepositoryTest extends TestCase {
    use RefreshDatabase; // Hər test üçün DB sıfırlanır

    // Və ya LazilyRefreshDatabase — yalnız DB istifadə olunanda migrate edir

    public function test_finds_active_users(): void {
        User::factory()->count(3)->create(['active' => true]);
        User::factory()->count(2)->create(['active' => false]);

        $activeUsers = app(UserRepository::class)->findActive();

        $this->assertCount(3, $activeUsers);
    }

    // Database assertions
    public function test_creates_user(): void {
        $service = app(UserService::class);

        $service->register('Orxan', 'orxan@example.com', 'password');

        $this->assertDatabaseHas('users', [
            'name' => 'Orxan',
            'email' => 'orxan@example.com',
        ]);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_deletes_user(): void {
        $user = User::factory()->create();

        app(UserService::class)->delete($user);

        $this->assertSoftDeleted('users', ['id' => $user->id]);
        // və ya
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }
}
```

---

## 4. Test Data Builders / Arrange-Act-Assert

```php
class OrderTest extends TestCase {
    use RefreshDatabase;

    public function test_premium_user_gets_free_shipping(): void {
        // Arrange
        $user = User::factory()->premium()->create();
        $order = Order::factory()
            ->for($user)
            ->has(OrderItem::factory()->count(3))
            ->create(['subtotal' => 150.00]);

        // Act
        $shipping = app(ShippingCalculator::class)->calculate($order);

        // Assert
        $this->assertEquals(0.00, $shipping);
    }

    // Data provider — eyni testi müxtəlif datalarla
    #[DataProvider('discountProvider')]
    public function test_discount_calculation(float $total, int $discount, float $expected): void {
        $calculator = new OrderCalculator();
        $this->assertEquals($expected, $calculator->applyDiscount($total, $discount));
    }

    public static function discountProvider(): array {
        return [
            'no discount' => [100.00, 0, 100.00],
            '10% discount' => [100.00, 10, 90.00],
            '50% discount' => [200.00, 50, 100.00],
            '100% discount' => [50.00, 100, 0.00],
        ];
    }
}
```

---

## 5. Test Coverage və Best Practices

**Nəyi test etməli?**
- Business logic (services, calculations)
- API endpoints (request/response)
- Validation rules
- Authorization/policies
- Edge cases və error scenarios
- Database queries (scopes, relationships)

**Nəyi test etməməli?**
- Framework-un öz kodu (Eloquent, Router)
- Getter/setter-lər
- Constructor-lar (əgər sadə assign edirlərsə)
- Third-party package-ların daxili məntiqləri

**Best Practices:**
```php
// 1. Hər test bir şeyi yoxlasın
// Pis
public function test_user_crud(): void {
    // create, read, update, delete hamısı bir testdə
}
// Yaxşı
public function test_can_create_user(): void { /* ... */ }
public function test_can_read_user(): void { /* ... */ }

// 2. Test adları aydın olsun
public function test_guest_cannot_access_admin_panel(): void {}
public function test_returns_404_for_nonexistent_product(): void {}

// 3. Hardcoded dəyərlər istifadə et (dynamic deyil)
// Pis
$this->assertEquals($user->name, $response->json('data.name'));
// Yaxşı
$user = User::factory()->create(['name' => 'Orxan']);
$this->assertEquals('Orxan', $response->json('data.name'));
```
