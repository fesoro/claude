<?php

declare(strict_types=1);

namespace Tests\Integration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Src\Shared\Infrastructure\Bus\EventDispatcher;
use Src\User\Domain\Entities\User;
use Src\User\Domain\ValueObjects\Email;
use Src\User\Domain\ValueObjects\Password;
use Src\User\Domain\ValueObjects\UserId;
use Src\User\Infrastructure\Repositories\EloquentUserRepository;
use Tests\TestCase;

/**
 * ELOQUENT USER REPOSITORY İNTEQRASİYA TESTLƏRİ
 * ===============================================
 * Bu testlər EloquentUserRepository-nin real verilənlər bazası ilə düzgün işlədiyini yoxlayır.
 *
 * İNTEQRASİYA TESTİ vs UNIT TESTİ FƏRQİ:
 * - Unit test: mock/stub istifadə edir, heç bir xarici asılılıq yoxdur.
 * - İnteqrasiya testi: real DB, real repository — bütün qatlar birlikdə yoxlanılır.
 *
 * Bu testlər aşağıdakı əməliyyatları yoxlayır:
 * - save() — istifadəçini bazaya yazma (INSERT və UPDATE)
 * - findById() — ID ilə istifadəçi tapma
 * - findByEmail() — email ilə istifadəçi tapma
 *
 * RefreshDatabase trait hər testdən əvvəl bazanı təmizləyir —
 * beləliklə testlər bir-birinə təsir etmir (test isolation).
 *
 * QEYD: EloquentUserRepository DB::table() facade-i ilə işləyir,
 * Eloquent Model istifadə etmir — bu DDD-nin "Persistence Model ayrılığı" prinsipinə uyğundur.
 */
class EloquentUserRepositoryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test olunan repository instansı.
     * setUp() metodunda yaradılır və hər testdə istifadə olunur.
     */
    private EloquentUserRepository $repository;

    /**
     * Hər testdən əvvəl repository instansını yaradırıq.
     * EventDispatcher mock edirik — çünki inteqrasiya testində
     * event-lərin dispatch olunmasını yoxlamırıq, yalnız DB əməliyyatlarını yoxlayırıq.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // EventDispatcher-i real instans kimi yaradırıq (RabbitMQ olmadan)
        // null parametr RabbitMQ publisher-in olmadığını bildirir
        $eventDispatcher = new EventDispatcher();

        $this->repository = new EloquentUserRepository($eventDispatcher);
    }

    // ===========================
    // save() TESTLƏRİ
    // ===========================

    /**
     * Yeni istifadəçi bazaya uğurla yazılmalıdır.
     *
     * AXIN:
     * 1. User::create() ilə domain entity yaradırıq (UserRegisteredEvent qeydə alınır)
     * 2. repository->save() çağırırıq — entity bazaya yazılır
     * 3. repository->findById() ilə bazadan oxuyub yoxlayırıq
     *
     * Bu test "round-trip" yoxlamasıdır:
     * Domain Entity → DB-yə yaz → DB-dən oxu → Domain Entity (eyni data olmalıdır)
     */
    public function test_save_persists_new_user_to_database(): void
    {
        // Arrange — yeni domain entity yaradırıq
        $userId = UserId::generate();
        $user = User::create(
            userId: $userId,
            name: 'Orxan Şükürlü',
            email: Email::fromString('orxan@example.com'),
            password: Password::fromPlainText('MyP@ssw0rd123'),
        );

        // Act — bazaya yazırıq
        $this->repository->save($user);

        // Assert — bazadan oxuyub yoxlayırıq
        $found = $this->repository->findById($userId);

        // İstifadəçi tapılmalıdır
        $this->assertNotNull($found, 'Saxlanılmış istifadəçi bazadan tapılmalıdır');

        // Sahələr eyni olmalıdır
        $this->assertEquals('Orxan Şükürlü', $found->name());
        $this->assertEquals('orxan@example.com', $found->email()->value());
        $this->assertTrue($found->userId()->equals($userId));
    }

    /**
     * Mövcud istifadəçini yeniləmək (UPDATE) düzgün işləməlidir.
     * EloquentUserRepository::save() updateOrInsert istifadə edir —
     * ID mövcuddursa UPDATE, yoxdursa INSERT.
     *
     * Bu test UPDATE əməliyyatını yoxlayır:
     * 1. İstifadəçi yaradılır və saxlanılır
     * 2. Eyni ID ilə yeni entity yaradılır (fərqli ad ilə)
     * 3. Yenidən saxlanılır — UPDATE olmalıdır
     * 4. Oxunan data yenidir
     */
    public function test_save_updates_existing_user(): void
    {
        // Arrange — ilk versiyasını yaradıb saxlayırıq
        $userId = UserId::generate();
        $user = User::create(
            userId: $userId,
            name: 'Əvvəlki Ad',
            email: Email::fromString('user@example.com'),
            password: Password::fromPlainText('MyP@ssw0rd123'),
        );
        $this->repository->save($user);

        // Act — eyni ID ilə yenilənmiş versiyasını yaradıb saxlayırıq
        // reconstruct() istifadə edirik — event qeydə alınmır
        $updatedUser = User::reconstruct(
            userId: $userId,
            name: 'Yenilənmiş Ad',
            email: Email::fromString('user@example.com'),
            password: Password::fromPlainText('NewP@ssw0rd456'),
            createdAt: new \DateTimeImmutable(),
        );
        $this->repository->save($updatedUser);

        // Assert — bazadan oxuyub yoxlayırıq
        $found = $this->repository->findById($userId);
        $this->assertNotNull($found);
        $this->assertEquals('Yenilənmiş Ad', $found->name());
    }

    // ===========================
    // findById() TESTLƏRİ
    // ===========================

    /**
     * Mövcud istifadəçi ID ilə tapılmalıdır.
     * findById() bazadan sətri oxuyur və toDomain() ilə Domain Entity-yə çevirir.
     */
    public function test_find_by_id_returns_user_when_exists(): void
    {
        // Arrange — istifadəçi yaradıb saxlayırıq
        $userId = UserId::generate();
        $user = User::create(
            userId: $userId,
            name: 'Test İstifadəçi',
            email: Email::fromString('test@example.com'),
            password: Password::fromPlainText('MyP@ssw0rd123'),
        );
        $this->repository->save($user);

        // Act — ID ilə axtarırıq
        $found = $this->repository->findById($userId);

        // Assert — tapılmalı və sahələr eyni olmalıdır
        $this->assertNotNull($found);
        $this->assertInstanceOf(User::class, $found);
        $this->assertEquals('Test İstifadəçi', $found->name());
        $this->assertEquals('test@example.com', $found->email()->value());
    }

    /**
     * Mövcud olmayan ID ilə axtarış null qaytarmalıdır.
     * findById() bazada sətir tapılmadıqda null qaytarır — exception atmır.
     */
    public function test_find_by_id_returns_null_when_not_found(): void
    {
        // Arrange — mövcud olmayan UUID yaradırıq
        $nonExistentId = UserId::generate();

        // Act
        $found = $this->repository->findById($nonExistentId);

        // Assert — null qaytarılmalıdır
        $this->assertNull($found, 'Mövcud olmayan ID ilə axtarış null qaytarmalıdır');
    }

    // ===========================
    // findByEmail() TESTLƏRİ
    // ===========================

    /**
     * Mövcud email ilə istifadəçi tapılmalıdır.
     * findByEmail() email sahəsinə görə axtarış edir.
     * Login zamanı istifadə olunur — email ilə istifadəçini tapıb şifrəni yoxlayırıq.
     */
    public function test_find_by_email_returns_user_when_exists(): void
    {
        // Arrange — istifadəçi yaradıb saxlayırıq
        $email = Email::fromString('login@example.com');
        $user = User::create(
            userId: UserId::generate(),
            name: 'Email Test',
            email: $email,
            password: Password::fromPlainText('MyP@ssw0rd123'),
        );
        $this->repository->save($user);

        // Act — email ilə axtarırıq
        $found = $this->repository->findByEmail($email);

        // Assert — tapılmalı və ad eyni olmalıdır
        $this->assertNotNull($found);
        $this->assertEquals('Email Test', $found->name());
        $this->assertEquals('login@example.com', $found->email()->value());
    }

    /**
     * Mövcud olmayan email ilə axtarış null qaytarmalıdır.
     * Bazada bu email ilə istifadəçi yoxdursa, null qaytarılır.
     */
    public function test_find_by_email_returns_null_when_not_found(): void
    {
        // Arrange — bazada olmayan email yaradırıq
        $email = Email::fromString('nonexistent@example.com');

        // Act
        $found = $this->repository->findByEmail($email);

        // Assert — null qaytarılmalıdır
        $this->assertNull($found, 'Mövcud olmayan email ilə axtarış null qaytarmalıdır');
    }

    /**
     * Şifrə düzgün hash-lənib saxlanılmalıdır.
     * save() zamanı Password::hash() çağırılır — bcrypt hash bazaya yazılır.
     * findById() zamanı Password::fromHash() ilə hash geri oxunur.
     * verify() metodu açıq şifrənin hash-ə uyğun olduğunu təsdiqləyir.
     */
    public function test_password_is_correctly_hashed_and_verified(): void
    {
        // Arrange — istifadəçi yaradıb saxlayırıq
        $plainPassword = 'MySecureP@ss123';
        $user = User::create(
            userId: UserId::generate(),
            name: 'Şifrə Test',
            email: Email::fromString('password@example.com'),
            password: Password::fromPlainText($plainPassword),
        );
        $this->repository->save($user);

        // Act — bazadan oxuyuruq
        $found = $this->repository->findByEmail(Email::fromString('password@example.com'));

        // Assert — şifrə doğrulama işləməlidir
        $this->assertNotNull($found);
        $this->assertTrue(
            $found->password()->verify($plainPassword),
            'Açıq şifrə bazadan oxunan hash ilə uyğun olmalıdır'
        );

        // Yanlış şifrə ilə doğrulama uğursuz olmalıdır
        $this->assertFalse(
            $found->password()->verify('WrongPassword123'),
            'Yanlış şifrə ilə doğrulama uğursuz olmalıdır'
        );
    }
}
