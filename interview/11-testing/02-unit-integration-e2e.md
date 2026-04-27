# Unit vs Integration vs E2E Testing (Middle ⭐⭐)

## İcmal
Unit, Integration və E2E testlər — hər biri fərqli məqsəd üçün mövcud olan, bir-birini əvəz etməyən test növləridir. Bu üç növü dəqiq fərqləndirmək müsahibədə fundamental kompetensiya sayılır. Interviewer adətən "bu ssenarini necə test edərdiniz?" sualı ilə real seçim etməyi tələb edir. Hər növün nə vaxt istifadə ediləcəyini bilmək, test suite-i effektiv qurmanın əsasıdır.

## Niyə Vacibdir
Test növlərini səhv seçmək development prosesinin bir çox yerini poza bilir: yavaş CI pipeline, fragilə test suite, ya da heç aşkarlanmayan integration bug-lar. Senior developer kimi hər test növünün hansı problemə çözüm olduğunu bilmək lazımdır. Bu bilgi həm code review zamanı ("bu unit test deyil, integration test"), həm də team-ə test strategiyası qurarkən kritikdir.

## Əsas Anlayışlar

### Unit Testing

- **Tərif**: Tək bir unit-i (funksiya, method, class) isolate şəkildə test edir. Bütün external dependency-lər (database, API, filesystem) mock/stub/fake ilə əvəz olunur.

- **Sürəti**: ~1-5ms. 1000 unit test = 1-5 saniyə.

- **Xüsusiyyətlər**: External I/O yoxdur. Deterministik — hər dəfə eyni nəticə. Refactoring zamanı çox sınır (implementation-a bağlı olduqda).

- **Nə vaxt yazılmalıdır**: Business logic, calculation, transformation. Pure funksiyalar. Edge case-lər (null, empty, boundary). Error handling yolları. Utility/helper funksiyalar.

- **Nə vaxt yazılmamalıdır**: Sadə getter/setter (trivial). Framework-ün özünün məsuliyyəti. Configuration-only class-lar. Database CRUD-un özü (integration test-in işidir).

- **FIRST prinsipləri**: Fast (sürətli), Independent (digər testlərdən asılı deyil), Repeatable (hər mühitdə eyni), Self-validating (pass/fail aydın), Timely (kodla birlikdə yazılır).

### Integration Testing

- **Tərif**: Bir neçə komponentin real dependency-lərlə birlikdə işləməsini test edir. Database, cache, message queue kimi infrastruktur real ya da test versiyası ilə iştirak edir.

- **Sürəti**: ~50ms–2s. Saniyələrlə işləyir.

- **Nə vaxt yazılmalıdır**: Database sorğuları (real schema ilə). Service → Repository əlaqəsi. External API wrapping. Message broker publish/consume. Cache layer (Redis). Authorization middleware.

- **Test database strategiyaları**:
  - SQLite (in-memory): Sürətli, amma real DB-dən fərqlənir — PostgreSQL-specific feature-lar işləmir
  - Dedicated test DB: Real behavior, setup lazımdır
  - Testcontainers: Docker-da real DB instance — ideal
  - Transaction rollback: Hər test sonunda rollback — sürətli, clean state

- **Laravel-də integration test**: `RefreshDatabase` trait bütün database-i təmizləyir. `DatabaseTransactions` trait transaction rollback edir (daha sürətli, amma Observers trigger olmur).

### E2E Testing

- **Tərif**: Sistemin bütün qatlarını — UI/API-dən başlayaraq database-ə qədər — real user journey kimi test edir. Heç bir şey mock edilmir.

- **Sürəti**: ~5s–60s+. Ən bahalı yazmaq və saxlamaq.

- **Nə vaxt yazılmalıdır**: Kritik user journey-lər (signup, checkout, payment). Multi-service axınlar. Regression test üçün prioritet path-lər. Compliance/audit tələb edən flow-lar.

- **E2E alətləri**: Laravel Dusk (browser-based PHP). Playwright (modern, fast). Cypress (frontend-focused). Postman Collection runner (API E2E).

### Test Double növləri

- **Mock**: Gözlənilən çağırışları verify edir. `$mock->expects($this->once())->method('send')`. Test uğursuz olur əgər method çağırılmayıbsa.

- **Stub**: Sabit dəyər qaytarır, verify etmir. `$stub->method('find')->willReturn($user)`. Test davranışını idarə etmək üçün.

- **Fake**: İşləyən, sadə implementasiya. `InMemoryUserRepository` — real DB əvəzinə array-da saxlayır. Tam functional, sadəcə real olmayan.

- **Spy**: Real obyekti wrap edir, çağırışları izləyir. Test edildikdən sonra nə çağırıldığını soruşmaq mümkündür.

- **Dummy**: Heç istifadə edilmir, yalnız parameter doldurur. `$this->createMock(Logger::class)` — logger test üçün vacib deyil.

### Test Scope Qərar Framework-u

```
"Bu test nəyi yoxlayır?"
├── Tək bir funksiyanın loqikasını → Unit Test
│   └── External I/O var? Mock et
├── İki+ komponentin əlaqəsini → Integration Test
│   └── Real DB/Cache istifadə et, transaction rollback
└── User-in gördüyü axını → E2E Test
    └── Yalnız kritik path-lər — sayı az saxla
```

- **"Unit test mi, integration test mi?" meyarı**: External I/O varsa (DB, HTTP, file) → integration test. Yalnız business logic varsa → unit test. Bu qayda əksər hallarda doğru seçim edir.

- **Over-mocking anti-pattern**: Hər dependency-ni mock etmək bəzən real bug-ları gizləyir. "Don't mock what you don't own" — sadəcə öz kodunuzun boundary-lərini mock edin.

- **Test isolation əhəmiyyəti**: Testlər bir-birinə asılı olmamalıdır. Global state, shared singleton, static properties — bunlar test pollution yaradır.

- **Parametrized tests**: Eyni test logic-ni müxtəlif data ilə çalışdırmaq. PHPUnit `@dataProvider`, Pest `dataset()` — kod tekrarı azalır, coverage artır.

## Praktik Baxış

**Interview-da necə yanaşmaq:**
Real ssenario veriləndə — "bu feature-ı necə test edərdiniz?" — üç test növünü sistemli şəkildə nəzərdən keçir. "Unit test ilə business rule-u yoxlaram, integration test ilə DB persistence-i yoxlaram, E2E test ilə critical user path-i yoxlaram" — bu struktur cavab güclü impression yaradır.

**Junior-dan fərqlənən senior cavabı:**
Junior: "Unit test yazıram, bəzən integration test."
Senior: "UserRegistrationService üçün: unit test ilə business rules (duplicate email, password policy), integration test ilə DB persistence və transaction rollback (Stripe failure = user not saved), bir E2E test ilə full registration flow."
Lead: "Test boundary-ləri haqqında team-ə guidelines yazıram. Integration test üçün Testcontainers istifadə edirik — real PostgreSQL, hər CI run-da fresh container."

**Follow-up suallar:**
- "Unit test ilə integration test arasındakı sərhədi necə müəyyənləşdirirsən?"
- "Mock-dan nə vaxt imtina edib real dependency istifadə edirsin?"
- "E2E testlər çox yavaş olduqda nə edirsən?"
- "Database test-ləri üçün SQLite vs real PostgreSQL — hansını seçirsiniz?"
- "Test Double-lar arasındakı fərq nədir? Nə vaxt Mock, nə vaxt Stub?"

**Ümumi səhvlər:**
- Database ilə işləyən testi "unit test" adlandırmaq
- Hər şeyi mock edib real integration bug-larını qaçırmaq
- E2E testlər uğursuz olanda debuggability yoxdur
- Test-ləri implementation detallarına bağlamaq (refactoring-i çətinləşdirir)
- Hər test növü üçün ayrı test class yazmamaq — qarışıq test suite idarəsi çətin

**Yaxşı cavabı əla cavabdan fərqləndirən:**
"Hansi test nə vaxt" sualına konkret threshold vermək. "External I/O varsa integration test yazıram. E2E yalnız business-critical path üçün, ən çox 10-15 test, CI 5 dəqiqə limiti var."

## Nümunələr

### Tipik Interview Sualı
"Laravel-də yeni bir `UserRegistrationService` yazdınız. Bu service email göndərir, database-ə yazır, Stripe-da customer yaradır. Bunu necə test edərdiniz?"

### Güclü Cavab
"Üç səviyyədə test yazardım. Unit test: email validation loqikasını, password policy-ni, duplicate email check-i ayrıca test edərdim — burada email service, DB, Stripe hamısı mock olardı. Integration test: real test database ilə service-in düzgün user yaratdığını, transaction rollback-ini (Stripe failure = DB rollback), duplicate user prevention-ı yoxlardım. E2E: yalnız bir test — form submit-dən email confirmation-a qədər. Stripe sandbox API istifadə edərdim."

### Kod Nümunəsi (PHP/Laravel)

```php
// ═══════════════════════════════════════════════════
// 1. UNIT TEST — Email validation loqikası
//    External dependency-lər yoxdur (hamısı mock)
// ═══════════════════════════════════════════════════
class UserRegistrationServiceUnitTest extends TestCase
{
    private UserRepository $userRepo;
    private Mailer $mailer;
    private StripeClient $stripe;
    private UserRegistrationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userRepo = $this->createMock(UserRepository::class);
        $this->mailer   = $this->createMock(Mailer::class);
        $this->stripe   = $this->createMock(StripeClient::class);
        $this->service  = new UserRegistrationService(
            $this->userRepo, $this->mailer, $this->stripe
        );
    }

    public function test_throws_exception_for_duplicate_email(): void
    {
        // Stub: bu email mövcuddur
        $this->userRepo->method('existsByEmail')->willReturn(true);

        $this->expectException(DuplicateEmailException::class);
        $this->expectExceptionMessage('Email already registered');
        $this->service->register('existing@example.com', 'Password1!');
    }

    public function test_throws_exception_for_weak_password(): void
    {
        $this->userRepo->method('existsByEmail')->willReturn(false);

        $this->expectException(WeakPasswordException::class);
        $this->service->register('new@example.com', '123');  // çox qısa
    }

    public function test_email_sent_on_successful_registration(): void
    {
        $this->userRepo->method('existsByEmail')->willReturn(false);
        $this->userRepo->method('save')->willReturn(new User(id: 1));
        $this->stripe->method('createCustomer')->willReturn(['id' => 'cus_test']);

        // Mock: email bir dəfə göndərilməlidir
        $this->mailer
            ->expects($this->once())
            ->method('send')
            ->with($this->callback(fn($mail) => $mail instanceof WelcomeEmail));

        $this->service->register('new@example.com', 'StrongPass1!');
    }

    public function test_password_is_hashed_before_saving(): void
    {
        $this->userRepo->method('existsByEmail')->willReturn(false);
        $this->stripe->method('createCustomer')->willReturn(['id' => 'cus_test']);

        // Spy: save-ə nə göndərildiyini yoxla
        $this->userRepo
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (User $user) {
                // Plain password saxlanmamalıdır
                return $user->password !== 'StrongPass1!'
                    && password_verify('StrongPass1!', $user->password);
            }));

        $this->service->register('new@example.com', 'StrongPass1!');
    }
}

// ═══════════════════════════════════════════════════
// 2. INTEGRATION TEST — Real database, transaction rollback
// ═══════════════════════════════════════════════════
class UserRegistrationIntegrationTest extends TestCase
{
    use RefreshDatabase;  // Hər test DB-ni sıfırlayır

    protected function setUp(): void
    {
        parent::setUp();
        // Yalnız Stripe mock-lanır — external paid API, test üçün real çağırış olmaz
        $this->stripeMock = $this->createMock(StripeClient::class);
        $this->app->instance(StripeClient::class, $this->stripeMock);
    }

    public function test_user_saved_to_database_on_success(): void
    {
        $this->stripeMock->method('createCustomer')
            ->willReturn(['id' => 'cus_test123']);

        $service = app(UserRegistrationService::class);
        $user    = $service->register('new@example.com', 'StrongPass1!');

        $this->assertDatabaseHas('users', [
            'email'              => 'new@example.com',
            'stripe_customer_id' => 'cus_test123',
        ]);
        $this->assertNotNull($user->id);
    }

    public function test_user_not_saved_when_stripe_fails(): void
    {
        // Stripe exception atır
        $this->stripeMock->method('createCustomer')
            ->willThrowException(new \Stripe\Exception\ApiException('stripe down'));

        $service = app(UserRegistrationService::class);

        try {
            $service->register('new@example.com', 'StrongPass1!');
            $this->fail('Exception expected');
        } catch (\Stripe\Exception\ApiException $e) {
            // Transaction rollback işlədi — DB-də user yoxdur
            $this->assertDatabaseMissing('users', ['email' => 'new@example.com']);
        }
    }

    public function test_duplicate_email_returns_conflict_error(): void
    {
        User::factory()->create(['email' => 'existing@example.com']);

        $this->expectException(DuplicateEmailException::class);
        app(UserRegistrationService::class)->register('existing@example.com', 'StrongPass1!');
    }

    public function test_welcome_email_queued_on_success(): void
    {
        Queue::fake();  // Mail queue-a mock
        $this->stripeMock->method('createCustomer')->willReturn(['id' => 'cus_x']);

        app(UserRegistrationService::class)->register('new@example.com', 'StrongPass1!');

        Queue::assertPushed(WelcomeEmail::class, function ($mail) {
            return $mail->to === 'new@example.com';
        });
    }
}

// ═══════════════════════════════════════════════════
// 3. E2E TEST — Laravel Dusk (full browser flow)
// ═══════════════════════════════════════════════════
class UserRegistrationE2ETest extends DuskTestCase
{
    public function test_user_can_register_and_receive_confirmation(): void
    {
        // Stripe sandbox — real API call (test mode)
        $this->browse(function (Browser $browser) {
            $browser
                ->visit('/register')
                ->type('email', 'dusktest+' . time() . '@example.com')
                ->type('password', 'StrongPass1!')
                ->type('password_confirmation', 'StrongPass1!')
                ->press('Create Account')
                ->assertSee('Please check your email')
                ->assertUrlContains('/register/pending');
        });
    }
}

// ═══════════════════════════════════════════════════
// PARAMETRIZED TEST — eyni logic, müxtəlif data
// ═══════════════════════════════════════════════════
class PasswordValidationTest extends TestCase
{
    /**
     * @dataProvider invalidPasswordProvider
     */
    public function test_invalid_passwords_are_rejected(
        string $password,
        string $expectedError
    ): void {
        $validator = new PasswordValidator();
        $result    = $validator->validate($password);

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString($expectedError, $result->errors()[0]);
    }

    public static function invalidPasswordProvider(): array
    {
        return [
            'too short'           => ['Ab1!', 'at least 8 characters'],
            'no uppercase'        => ['password1!', 'uppercase letter'],
            'no number'           => ['Password!', 'number'],
            'no special char'     => ['Password1', 'special character'],
            'common password'     => ['Password1!', 'too common'],
            'spaces'              => ['Pass word1!', 'spaces not allowed'],
        ];
    }
}

// ═══════════════════════════════════════════════════
// FAKE (in-memory implementasiya) — unit test üçün
// ═══════════════════════════════════════════════════
class InMemoryUserRepository implements UserRepository
{
    private array $users = [];
    private int $nextId  = 1;

    public function save(User $user): User
    {
        $user->id        = $this->nextId++;
        $this->users[$user->id] = $user;
        return $user;
    }

    public function existsByEmail(string $email): bool
    {
        return count(array_filter(
            $this->users,
            fn(User $u) => $u->email === $email
        )) > 0;
    }

    public function findById(int $id): ?User
    {
        return $this->users[$id] ?? null;
    }
}

// Fake istifadəsi — mock-dan daha çox realist behavior
class UserRegistrationWithFakeRepoTest extends TestCase
{
    public function test_second_registration_with_same_email_fails(): void
    {
        $repo    = new InMemoryUserRepository();
        $service = new UserRegistrationService(
            $repo,
            $this->createMock(Mailer::class),
            $this->createMock(StripeClient::class)
        );

        $service->register('test@example.com', 'StrongPass1!');

        $this->expectException(DuplicateEmailException::class);
        $service->register('test@example.com', 'AnotherPass1!');
    }
}
```

### Müqayisə Cədvəli — Test Double növləri

| Test Double | Nə edir | Nə vaxt istifadə olunur |
|-------------|---------|------------------------|
| Mock | Çağırışları verify edir | "email göndərilib mi?" yoxlamaq |
| Stub | Sabit cavab qaytarır | "user mövcuddur" vəziyyəti qurmaq |
| Fake | İşləyən sadə impl. | InMemoryRepository — sürətli test |
| Spy | Çağırışları izləyir | Nə parametrlə çağırıldığını yoxlamaq |
| Dummy | Placeholder | Test üçün lazım olmayan dependency |

## Praktik Tapşırıqlar

1. Mövcud bir service üçün unit, integration, E2E testlərini yazın. Hər test nəyi yoxlayır — müqayisə edin.
2. Mock istifadə edilən bir testi götürün, real dependency ilə yenidən yazın — hansı bug-lar ortaya çıxdı?
3. Team-in test-lərini nəzərdən keçirin: hansıları "unit test" adlanıb, amma əslində integration test?
4. Testcontainers ilə Docker-da real PostgreSQL istifadə edən integration test yazın.
5. `InMemoryUserRepository` fake-i yaradın — unit test-lərdə istifadə edin. DB mock-dan sürətli və realmi?
6. Parametrized test yazın: `DiscountCalculator` üçün 10 müxtəlif ssenari.
7. E2E test-lərin sayını azaldın — ən kritik 5 flow-u seçin, qalanını integration testlərə dönüşdürün.
8. PHPUnit Groups ilə test-ləri kateqoriyalara ayırın (`@group unit`, `@group integration`, `@group e2e`). CI-da ayrı icra edin.

## Əlaqəli Mövzular

- [01-testing-pyramid.md](01-testing-pyramid.md) — Test növlərinin strateji balansı
- [03-tdd-approach.md](03-tdd-approach.md) — Test növlərini TDD ilə birləşdirmək
- [04-mocking-strategies.md](04-mocking-strategies.md) — Mock, Stub, Fake dərinləşdirilmiş
- [07-contract-testing.md](07-contract-testing.md) — E2E alternatiви microservice-lər üçün
