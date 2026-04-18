<?php

declare(strict_types=1);

namespace Src\User\Domain\Entities;

use Src\Shared\Domain\AggregateRoot;
use Src\User\Domain\ValueObjects\UserId;
use Src\User\Domain\ValueObjects\Email;
use Src\User\Domain\ValueObjects\Password;
use Src\User\Domain\Events\UserRegisteredEvent;

/**
 * USER AGGREGATE ROOT
 * ===================
 * User — istifadəçi domeninin Aggregate Root-udur.
 *
 * NƏYƏ AGGREGATE ROOT?
 * - User bütün istifadəçi məlumatlarını (email, password, name) bir yerdə idarə edir.
 * - Bütün dəyişikliklər User vasitəsilə edilir.
 * - Domain Event-ləri burada yaranır (məsələn: UserRegisteredEvent).
 *
 * NƏYƏ ENTITY DEYİL, AGGREGATE ROOT?
 * - Entity yalnız ID ilə müəyyən olunan obyektdir.
 * - Aggregate Root isə Entity-dir + Domain Event-ləri qeyd edir + consistency qaydalarını tətbiq edir.
 * - User bizim sistemdə müstəqil yaşaya bilir (başqa Aggregate-ə bağlı deyil),
 *   buna görə Aggregate Root-dur.
 *
 * FACTORY METHOD PATTERN:
 * - User yaratmaq üçün new User() əvəzinə User::create() istifadə olunur.
 * - Bu bizə imkan verir ki, yaradılma zamanı Domain Event qeyd edək.
 * - Constructor yalnız dəyərləri set edir, əlavə logika yoxdur.
 *
 * ENCAPSULATION (Kapsullaşdırma):
 * - Bütün property-lər private-dir — xaricdən birbaşa dəyişdirilə bilməz.
 * - Dəyişiklik yalnız metodlar vasitəsilə olur ki, biznes qaydaları tətbiq olunsun.
 */
final class User extends AggregateRoot
{
    /**
     * Constructor private-dir — yalnız static factory metodlar vasitəsilə yaradılır.
     * Bu pattern "Named Constructor" və ya "Factory Method" adlanır.
     */
    private function __construct(
        private UserId $userId,
        private string $name,
        private Email $email,
        private Password $password,
        private \DateTimeImmutable $createdAt,
    ) {
        /**
         * Parent Entity class-ının $id sahəsini set edirik.
         * Bu Entity::id() metodunun düzgün işləməsi üçün lazımdır.
         */
        $this->id = $userId->value();
    }

    /**
     * YENİ İSTİFADƏÇİ YARAT — Factory Method.
     *
     * Bu metod iki şey edir:
     * 1. User obyektini yaradır.
     * 2. UserRegisteredEvent qeyd edir (recordEvent).
     *
     * NƏYƏ CONSTRUCTOR-DA EVENT QEYD ETMİRİK?
     * - Constructor yalnız obyekti inisializasiya etməlidir.
     * - Event qeyd etmək biznes logikasıdır — factory method-da olmalıdır.
     * - Bazadan oxuyanda (reconstruct) event təkrar qeyd olunmamalıdır.
     *
     * NÜMUNƏ İSTİFADƏ:
     * $user = User::create(
     *     userId: UserId::generate(),
     *     name: 'Orxan',
     *     email: Email::fromString('orxan@example.com'),
     *     password: Password::fromPlainText('MyP@ssw0rd')
     * );
     */
    public static function create(
        UserId $userId,
        string $name,
        Email $email,
        Password $password,
    ): self {
        $user = new self(
            userId: $userId,
            name: $name,
            email: $email,
            password: $password,
            createdAt: new \DateTimeImmutable(),
        );

        /**
         * Domain Event qeyd et — "İstifadəçi qeydiyyatdan keçdi!"
         * Bu event Aggregate daxilində toplanır və persist-dən sonra dispatch olunur.
         *
         * AXIN:
         * 1. User::create() → event qeyd olunur (amma hələ göndərilmir)
         * 2. Repository::save() → user bazaya yazılır
         * 3. pullDomainEvents() → event-lər alınır və dispatch olunur
         * 4. Event Listener-lər işə düşür (məs: xoş gəldin emaili göndər)
         */
        $user->recordEvent(new UserRegisteredEvent(
            userId: $userId->value(),
            email: $email->value(),
            name: $name,
        ));

        return $user;
    }

    /**
     * Bazadan oxunan datanı User obyektinə çevirmək üçün istifadə olunur.
     * Bu metod Event QEYD ETMİR — çünki user artıq əvvəl yaradılıb.
     *
     * Bu metod yalnız Infrastructure layer-dən (Repository) çağırılmalıdır.
     */
    public static function reconstruct(
        UserId $userId,
        string $name,
        Email $email,
        Password $password,
        \DateTimeImmutable $createdAt,
    ): self {
        return new self(
            userId: $userId,
            name: $name,
            email: $email,
            password: $password,
            createdAt: $createdAt,
        );
    }

    // ========================
    // GETTER METODLARI
    // Domain obyektlərini xaricə veririk (read-only).
    // ========================

    public function userId(): UserId
    {
        return $this->userId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function email(): Email
    {
        return $this->email;
    }

    public function password(): Password
    {
        return $this->password;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
