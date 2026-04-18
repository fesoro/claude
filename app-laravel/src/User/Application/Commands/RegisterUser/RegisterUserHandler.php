<?php

declare(strict_types=1);

namespace Src\User\Application\Commands\RegisterUser;

use Src\Shared\Application\Bus\Command;
use Src\Shared\Application\Bus\CommandHandler;
use Src\Shared\Domain\Exceptions\DomainException;
use Src\User\Domain\Entities\User;
use Src\User\Domain\Repositories\UserRepositoryInterface;
use Src\User\Domain\ValueObjects\Email;
use Src\User\Domain\ValueObjects\Password;
use Src\User\Domain\ValueObjects\UserId;

/**
 * REGISTER USER HANDLER (CQRS Pattern)
 * =====================================
 * Bu Handler RegisterUserCommand-ı qəbul edib qeydiyyat əməliyyatını icra edir.
 *
 * HANDLER-İN ROLU:
 * - Handler "orkestrator" (idarəedici) rolunu oynayır.
 * - Özü biznes qaydalarını ehtiva ETMİR — Domain layer-dəki obyektləri çağırır.
 * - Handler-in işi:
 *   1. Command-dan datanı çıxart
 *   2. Domain obyektlərini yarat (Value Object, Entity)
 *   3. Biznes qaydalarını yoxla (email unikallığı)
 *   4. Repository vasitəsilə bazaya yaz
 *
 * APPLICATION LAYER-İN ƏHƏMİYYƏTİ:
 * - Application layer Domain və Infrastructure arasında "körpü" rolunu oynayır.
 * - Domain layer biznes qaydalarını bilir (Email formatı, Password uzunluğu).
 * - Infrastructure layer texniki detalları bilir (MySQL, Eloquent).
 * - Application layer hər ikisini koordinasiya edir.
 *
 * DEPENDENCY INJECTION:
 * - Handler constructor-da UserRepositoryInterface qəbul edir (interface!).
 * - Konkret implementasiya (EloquentUserRepository) Laravel ServiceProvider
 *   tərəfindən inject olunur.
 * - Bu Dependency Inversion Principle-dir (SOLID-in D hərfi).
 */
final class RegisterUserHandler implements CommandHandler
{
    /**
     * Constructor Injection — asılılıqlar constructor vasitəsilə verilir.
     *
     * NƏYƏ CONSTRUCTOR INJECTION?
     * - Handler yaradılan anda bütün asılılıqları bəlli olur.
     * - Test zamanı mock repository inject edə bilərsən.
     * - Handler-in nəyə ehtiyacı olduğu aydın görünür.
     */
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
    ) {
    }

    /**
     * Qeydiyyat əməliyyatını icra et.
     *
     * ADDIMLAR:
     * 1. DTO-dan Value Object-ləri yarat (validasiya avtomatik baş verir)
     * 2. Email unikallığını yoxla (biznes qaydası)
     * 3. User Aggregate yaradır (factory method ilə)
     * 4. Repository vasitəsilə bazaya yaz
     * 5. Yaradılan User-in ID-sini qaytar
     *
     * @throws DomainException Email artıq mövcuddursa və ya validasiya uğursuz olarsa
     */
    public function handle(Command $command): string
    {
        /** @var RegisterUserCommand $command */
        $dto = $command->dto;

        /**
         * ADDIM 1: Value Object-ləri yarat.
         * Value Object-lər yaradılan anda öz qaydalarını yoxlayır (self-validating).
         * Əgər email formatı yanlışdırsa → Email::fromString() DomainException atacaq.
         * Əgər şifrə qısadırsa → Password::fromPlainText() DomainException atacaq.
         */
        $email = Email::fromString($dto->email);
        $password = Password::fromPlainText($dto->password);

        /**
         * ADDIM 2: Email unikallığını yoxla.
         * Bu biznes qaydadır: "Bir email ilə yalnız bir hesab ola bilər."
         * Bu qayda Application layer-dədir çünki Repository-yə müraciət lazımdır.
         *
         * Alternativ: Domain Service və ya Specification pattern istifadə oluna bilər.
         */
        $existingUser = $this->userRepository->findByEmail($email);

        if ($existingUser !== null) {
            throw new DomainException(
                "Bu email artıq qeydiyyatdan keçib: {$email->value()}"
            );
        }

        /**
         * ADDIM 3: User Aggregate yarat.
         * User::create() factory method istifadə edirik:
         * - User obyekti yaranır
         * - UserRegisteredEvent qeyd olunur (amma hələ dispatch olunmur)
         */
        $userId = UserId::generate();

        $user = User::create(
            userId: $userId,
            name: $dto->name,
            email: $email,
            password: $password,
        );

        /**
         * ADDIM 4: Bazaya yaz.
         * Repository save() metodu:
         * - User-i bazaya yazır
         * - Domain Event-ləri dispatch edir (pullDomainEvents)
         */
        $this->userRepository->save($user);

        /**
         * ADDIM 5: Yaradılan User-in ID-sini qaytar.
         * CQRS prinsipinə görə Command heç nə qaytarmamalıdır,
         * amma praktikada yaradılan resursun ID-sini qaytarmaq adi haldır.
         */
        return $userId->value();
    }
}
