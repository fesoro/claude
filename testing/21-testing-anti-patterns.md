# Testing Anti-Patterns

## Nədir? (What is it?)

Testing anti-patterns, test yazmaqda tez-tez rast gəlinən səhv yanaşma və praktikalardır.
Bu pattern-lər testləri yavaş, kövrək, çətin bakım edilən və ya dəyərsiz edir. Onları
tanımaq və qaçmaq professional test suite-i yaratmaq üçün vacibdir.

Anti-pattern-lər zamanla yığılır - bir test yavaşdır, fərq etməz. 500 yavaş test isə
CI/CD pipeline-ı 30 dəqiqəyə uzadır, developer-lərin testlərə güvənini sarsıdır.

### Niyə Anti-Pattern-ləri Bilmək Vacibdir?

1. **Vaxt qənaəti** - Yavaş testlər developer vaxtını israf edir
2. **Güvən** - Flaky testlər bütün test suite-ə güvəni sarsıdır
3. **Bakım xərci** - Kövrək testlər daim fix tələb edir
4. **False security** - Zəif testlər yanlış güvən verir
5. **Team moralı** - Pis testlər test yazmaq istəyini azaldır

## Əsas Konseptlər (Key Concepts)

### Test Anti-Pattern Kateqoriyaları

```
1. Structural Anti-Patterns
   ├── Ice Cream Cone (ters piramida)
   ├── God Test Class
   ├── Test Dependency Chain
   └── Mystery Guest

2. Behavioral Anti-Patterns
   ├── Flaky Tests
   ├── Slow Tests
   ├── Non-deterministic Tests
   └── Sleeping Tests

3. Design Anti-Patterns
   ├── Testing Implementation Details
   ├── Excessive Mocking
   ├── Inappropriate Intimacy
   └── Logic in Tests

4. Maintenance Anti-Patterns
   ├── Copy-Paste Tests
   ├── Dead Tests
   ├── Commented-Out Tests
   └── Ignored/Skipped Tests
```

### Ice Cream Cone Anti-Pattern

```
Düzgün Test Piramidası:         Ice Cream Cone (YANLIŞ):

        /  E2E  \               /  Unit  \
       /----------\            /----------\
      / Integration \         / Integration \
     /----------------\      /----------------\
    /    Unit Tests     \   /    E2E / Manual    \
   /____________________\  /________________________\

Piramida: çox unit, az E2E      Cone: çox E2E, az unit
Sürətli feedback                 Yavaş feedback
Ucuz bakım                      Bahalı bakım
Stabil                          Flaky
```

## Praktiki Nümunələr (Practical Examples)

### Anti-Pattern 1: Testing Implementation Details

```php
<?php

// ❌ PIS - Implementation detail test edir
class OrderServiceTest extends TestCase
{
    /** @test */
    public function it_calls_repository_save_method(): void
    {
        $repo = $this->createMock(OrderRepository::class);
        $repo->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(Order::class));

        $service = new OrderService($repo);
        $service->createOrder(['item' => 'book', 'qty' => 1]);
    }

    /** @test */
    public function it_calls_event_dispatcher_twice(): void
    {
        $dispatcher = $this->createMock(EventDispatcher::class);
        $dispatcher->expects($this->exactly(2))
            ->method('dispatch');

        // Implementation dəyişsə (3 event olsa), test qırılır
    }
}

// ✅ YAXŞI - Davranışı test edir
class OrderServiceTest extends TestCase
{
    /** @test */
    public function it_creates_an_order_and_persists_it(): void
    {
        $service = app(OrderService::class);

        $order = $service->createOrder(['item' => 'book', 'qty' => 1]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'pending',
        ]);
    }

    /** @test */
    public function it_notifies_customer_after_order_creation(): void
    {
        Notification::fake();
        $customer = User::factory()->create();

        $service = app(OrderService::class);
        $service->createOrder([
            'customer_id' => $customer->id,
            'item' => 'book',
        ]);

        Notification::assertSentTo($customer, OrderCreatedNotification::class);
    }
}
```

### Anti-Pattern 2: Excessive Mocking

```php
<?php

// ❌ PIS - Hər şey mock-lanıb, heç nə real test edilmir
class UserServiceTest extends TestCase
{
    /** @test */
    public function it_creates_user(): void
    {
        $repo = $this->createMock(UserRepository::class);
        $hasher = $this->createMock(Hasher::class);
        $validator = $this->createMock(Validator::class);
        $logger = $this->createMock(Logger::class);
        $events = $this->createMock(EventDispatcher::class);

        $validator->method('validate')->willReturn(true);
        $hasher->method('hash')->willReturn('hashed');
        $repo->method('save')->willReturn(new User());
        $logger->method('info')->willReturn(null);

        $service = new UserService($repo, $hasher, $validator, $logger, $events);
        $result = $service->create(['name' => 'John', 'password' => '123']);

        $this->assertInstanceOf(User::class, $result);
        // Bu test nəyi yoxlayır? Mock-ların qaytardığını!
    }
}

// ✅ YAXŞI - Real dependency-lər, yalnız xarici service mock-lanır
class UserServiceTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_creates_user_with_hashed_password(): void
    {
        $service = app(UserService::class);

        $user = $service->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'secret123',
        ]);

        $this->assertDatabaseHas('users', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);
        $this->assertTrue(Hash::check('secret123', $user->password));
    }
}
```

### Anti-Pattern 3: Flaky Tests

```php
<?php

// ❌ PIS - Flaky: zamanla dəyişən nəticə
class ReportTest extends TestCase
{
    /** @test */
    public function it_generates_today_report(): void
    {
        $report = $this->reportService->generateDailyReport();

        // Gün dəyişsə, test qırılır!
        $this->assertEquals('2024-01-15', $report->date);
    }

    /** @test */
    public function it_marks_recent_users(): void
    {
        $user = User::factory()->create(['created_at' => now()->subMinutes(5)]);

        // Gecikmə olsa, 5 dəqiqə keçə bilər
        $this->assertTrue($user->isRecent());
    }
}

// ✅ YAXŞI - Deterministic
class ReportTest extends TestCase
{
    /** @test */
    public function it_generates_report_for_current_date(): void
    {
        $this->travelTo(Carbon::create(2024, 1, 15));

        $report = $this->reportService->generateDailyReport();

        $this->assertEquals('2024-01-15', $report->date);
    }

    /** @test */
    public function it_marks_users_created_within_last_hour_as_recent(): void
    {
        $this->travelTo(now());

        $recentUser = User::factory()->create(['created_at' => now()->subMinutes(30)]);
        $oldUser = User::factory()->create(['created_at' => now()->subHours(2)]);

        $this->assertTrue($recentUser->isRecent());
        $this->assertFalse($oldUser->isRecent());
    }
}
```

### Anti-Pattern 4: Slow Tests

```php
<?php

// ❌ PIS - Lazımsız yavaş
class PostTest extends TestCase
{
    /** @test */
    public function it_shows_post_title(): void
    {
        // 1000 user lazım deyil, 1 kifayət edir
        User::factory()->count(1000)->create();
        $user = User::first();

        // 500 post lazım deyil
        Post::factory()->count(500)->create(['user_id' => $user->id]);
        $post = Post::first();

        $response = $this->actingAs($user)->get("/posts/{$post->id}");
        $response->assertSee($post->title);
    }

    /** @test */
    public function it_sends_notification(): void
    {
        sleep(2); // NİYƏ GÖZLƏYİRİK?
        $user = User::factory()->create();
        // ...
    }
}

// ✅ YAXŞI - Sürətli
class PostTest extends TestCase
{
    /** @test */
    public function it_shows_post_title(): void
    {
        $post = Post::factory()->create();

        $response = $this->actingAs($post->user)->get("/posts/{$post->id}");
        $response->assertSee($post->title);
    }
}
```

### Anti-Pattern 5: Mystery Guest

```php
<?php

// ❌ PIS - Data haradan gəlir bəlli deyil
class OrderTest extends TestCase
{
    /** @test */
    public function it_calculates_discount(): void
    {
        // "test-data.json" nə ehtiva edir? Oxucu bilmir
        $this->seed(TestOrderSeeder::class);

        $order = Order::find(1);
        $discount = $this->service->calculateDiscount($order);

        $this->assertEquals(20.00, $discount);
        // Niyə 20? Seeder-ə baxmadan anlamaq mümkün deyil
    }
}

// ✅ YAXŞI - Bütün data testdə görünür
class OrderTest extends TestCase
{
    /** @test */
    public function vip_customer_gets_20_percent_discount_on_100_dollar_order(): void
    {
        $customer = User::factory()->create(['type' => 'vip']);
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'total' => 100.00,
        ]);

        $discount = $this->service->calculateDiscount($order);

        $this->assertEquals(20.00, $discount); // 100 * 0.20 = 20, aydındır
    }
}
```

### Anti-Pattern 6: Logic in Tests

```php
<?php

// ❌ PIS - Testdə məntiqi hesablama
class PricingTest extends TestCase
{
    /** @test */
    public function it_calculates_total_correctly(): void
    {
        $items = [
            ['price' => 10, 'qty' => 2],
            ['price' => 25, 'qty' => 1],
        ];

        $order = Order::factory()->create();
        foreach ($items as $item) {
            OrderItem::factory()->create([
                'order_id' => $order->id,
                'price' => $item['price'],
                'quantity' => $item['qty'],
            ]);
        }

        // Testdə hesablama - production kodu ilə eyni logic!
        $expectedTotal = 0;
        foreach ($items as $item) {
            $expectedTotal += $item['price'] * $item['qty'];
        }

        $this->assertEquals($expectedTotal, $order->fresh()->calculateTotal());
        // Əgər eyni bug hər ikisində varsa, test keçəcək!
    }
}

// ✅ YAXŞI - Hardcoded gözlənilən dəyər
class PricingTest extends TestCase
{
    /** @test */
    public function it_calculates_total_as_sum_of_item_prices(): void
    {
        $order = Order::factory()->create();
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'price' => 10.00,
            'quantity' => 2, // 20.00
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'price' => 25.00,
            'quantity' => 1, // 25.00
        ]);

        $this->assertEquals(45.00, $order->fresh()->calculateTotal());
    }
}
```

## PHP/Laravel ilə Tətbiq (Implementation with PHP/Laravel)

### Anti-Pattern 7: Test Dependency Chain

```php
<?php

// ❌ PIS - Testlər bir-birindən asılıdır
class UserFlowTest extends TestCase
{
    private static ?int $userId = null;

    /** @test */
    public function step1_register_user(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'John',
            'email' => 'john@test.com',
            'password' => 'password',
        ]);
        self::$userId = $response->json('data.id');
        $response->assertStatus(201);
    }

    /**
     * @depends step1_register_user
     */
    /** @test */
    public function step2_login_user(): void
    {
        // step1 fail olsa, bu da fail olur
        $this->assertNotNull(self::$userId);
        // ...
    }
}

// ✅ YAXŞI - Hər test müstəqil
class UserRegistrationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function user_can_register(): void
    {
        $response = $this->postJson('/api/register', [...]);
        $response->assertStatus(201);
    }
}

class UserLoginTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function user_can_login(): void
    {
        $user = User::factory()->create(['email' => 'john@test.com']);
        $response = $this->postJson('/api/login', [...]);
        $response->assertStatus(200);
    }
}
```

### Anti-Pattern 8: Copy-Paste Tests

```php
<?php

// ❌ PIS - Eyni kod təkrarlanır
class PostApiTest extends TestCase
{
    /** @test */
    public function admin_can_create_post(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin, 'sanctum');

        $response = $this->postJson('/api/posts', [
            'title' => 'Test',
            'body' => 'Content',
        ]);

        $response->assertStatus(201);
    }

    /** @test */
    public function admin_can_update_post(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin, 'sanctum');

        $post = Post::factory()->create();
        $response = $this->putJson("/api/posts/{$post->id}", [
            'title' => 'Updated',
        ]);

        $response->assertStatus(200);
    }

    // Hər testdə admin yaratmaq təkrarlanır...
}

// ✅ YAXŞI - setUp və helper istifadəsi
class PostApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
        $this->actingAs($this->admin, 'sanctum');
    }

    /** @test */
    public function admin_can_create_post(): void
    {
        $response = $this->postJson('/api/posts', [
            'title' => 'Test',
            'body' => 'Content',
        ]);

        $response->assertStatus(201);
    }

    /** @test */
    public function admin_can_update_post(): void
    {
        $post = Post::factory()->create();

        $response = $this->putJson("/api/posts/{$post->id}", [
            'title' => 'Updated',
        ]);

        $response->assertStatus(200);
    }
}
```

### Anti-Pattern Checklist

```
Test Review Checklist:

□ Testlər arası asılılıq var?
□ sleep/pause istifadə olunur?
□ Random/time-dependent data var?
□ Excessive mocking (3+ mock) var?
□ Implementation detail test edilir?
□ Magic numbers/strings var?
□ Test-də məntiqi hesablama var?
□ Copy-paste kod təkrarı var?
□ Uyğunsuz test adlandırma var?
□ Commented-out/skipped testlər var?
□ Lazımsız böyük data set yaradılır?
□ setUp-da çox iş görülür?
```

## Interview Sualları

### 1. Testing-də ən çox rast gəlinən anti-pattern-lər hansılardır?
**Cavab:** 1) Flaky tests - bəzən pass, bəzən fail, 2) Testing implementation details - davranış əvəzinə kod strukturunu test etmək, 3) Excessive mocking - hər şeyi mock-lamaq, 4) Ice cream cone - çox E2E az unit test, 5) Slow tests - lazımsız data, sleep-lər, 6) Test dependency - testlər arası sıra asılılığı.

### 2. Implementation detail testing niyə pisdir?
**Cavab:** Refactoring-i çətinləşdirir - kod dəyişsə amma davranış eyni qalsa, test qırılır. Yanlış güvən verir - implementation düzgün test edilsə belə, davranış yanlış ola bilər. Testlər koda çox bağlanır (tight coupling). Əvəzinə output/davranış test edilməlidir.

### 3. Flaky testlərin zərərləri nələrdir?
**Cavab:** CI/CD pipeline-a güvəni sarsıdır - "yenə flaky, ignore edək". Real bug-lar gizlənir - flaky sayıldığı üçün əsl failure ignore edilir. Developer vaxtı israf olur - retry, investigate. Team moralını aşağı salır. Həll: root cause tapın, mock/freeze edin, son çarə quarantine.

### 4. Ice cream cone anti-pattern nədir?
**Cavab:** Test piramidanın tərsidir - çox E2E/manual test, az unit test. E2E testlər yavaş, bahalı və flaky-dir. Unit testlər sürətli, ucuz və stabildir. Ice cream cone yavaş feedback loop, yüksək bakım xərci və flaky test suite deməkdir. Piramida strategiyasına keçmək lazımdır.

### 5. Excessive mocking nə zaman baş verir?
**Cavab:** 3+ dependency mock-landıqda, mock-ların davranışı konfiguraiysya edildikdə, test yalnız mock interaction-ı yoxladıqda. Bu test-in real davranışı test etmədiyini göstərir. Həll: integration test yazın, yalnız xarici service-ləri mock-layın, in-memory implementation istifadə edin.

### 6. Mystery Guest anti-pattern nədir?
**Cavab:** Test-in asılı olduğu data-nın testdə görünməməsidir - seeder, fixture file, shared state-dən gəlir. Test-i oxuyan developer nəticənin niyə belə olduğunu anlamır. Həll: bütün relevant data test method-da yaradılmalıdır, inline factory əvəzinə seeder, explicit əvəzinə implicit.

## Best Practices / Anti-Patterns

### Anti-Pattern-lərdən Qaçmaq üçün

1. **Davranışı test edin** - Implementation deyil
2. **Minimum data yaradın** - Yalnız test üçün lazımi qədər
3. **Deterministic olun** - Time, random-dan qaçının, freeze edin
4. **sleep istifadə etməyin** - waitFor, Carbon::setTestNow istifadə edin
5. **Testləri müstəqil saxlayın** - Shared state yoxdur
6. **Mock-u minimuma endirin** - Yalnız xarici service-lər
7. **Test-ləri review edin** - Code review-da testlərə də baxın
8. **Piramida qaydasına əməl edin** - Çox unit, az E2E
9. **Dead testləri silin** - Skip/commented test saxlamayın
10. **Test adını aydın yazın** - Nəyi test etdiyini izah etsin
