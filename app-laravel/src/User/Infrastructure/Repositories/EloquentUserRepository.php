<?php

declare(strict_types=1);

namespace Src\User\Infrastructure\Repositories;

use Illuminate\Support\Facades\DB;
use Src\Shared\Infrastructure\Bus\EventDispatcher;
use Src\User\Domain\Entities\User;
use Src\User\Domain\Repositories\UserRepositoryInterface;
use Src\User\Domain\ValueObjects\Email;
use Src\User\Domain\ValueObjects\Password;
use Src\User\Domain\ValueObjects\UserId;

/**
 * ELOQUENT USER REPOSITORY (Infrastructure Layer)
 * =================================================
 * Bu class UserRepositoryInterface-in KONKRET implementasiyasıdır.
 *
 * NƏYƏ INFRASTRUCTURE LAYER-DƏ?
 * - Domain layer "User-i bazaya yaz" deyir (NƏ etmək lazım).
 * - Infrastructure layer "Eloquent ilə MySQL-ə yaz" deyir (NECƏ etmək lazım).
 * - Bu ayrılıq bizi texnologiyadan müstəqil edir.
 *
 * REPOSITORY PATTERN İMPLEMENTASİYASI:
 * - Interface Domain layer-dədir: UserRepositoryInterface
 * - Implementation Infrastructure layer-dədir: EloquentUserRepository (bu class)
 * - Laravel ServiceProvider ikisini birləşdirir (bind edir).
 *
 * DOMAIN MODEL vs PERSISTENCE MODEL:
 * - Bu nümunədə biz Eloquent Model istifadə etmirik — birbaşa DB facade ilə işləyirik.
 * - Səbəb: Domain Entity (User) və Eloquent Model fərqli məqsədlərə xidmət edir.
 * - Domain Entity biznes qaydalarını ehtiva edir.
 * - Eloquent Model bazanın strukturunu əks etdirir.
 * - Bunları ayırmaq daha təmiz arxitektura verir.
 *
 * MAPPING (Xəritələmə):
 * - toDomain(): Baza sətirini (stdClass) → Domain Entity-yə çevirir.
 * - save(): Domain Entity-ni → baza sətirlərinə çevirir.
 */
final class EloquentUserRepository implements UserRepositoryInterface
{
    /**
     * Baza cədvəlinin adı.
     */
    private const string TABLE = 'users';

    public function __construct(
        /**
         * EventDispatcher — Domain Event-ləri dispatch etmək üçün.
         * User bazaya yazıldıqdan SONRA event-lər göndərilir.
         */
        private readonly EventDispatcher $eventDispatcher,
    ) {
    }

    /**
     * İstifadəçini ID ilə tap.
     *
     * AXIN:
     * 1. Bazadan sətir oxu (DB::table()->where()->first())
     * 2. Əgər tapılmadısa → null qaytar
     * 3. Tapıldısa → Domain Entity-yə çevir (toDomain)
     */
    public function findById(UserId $userId): ?User
    {
        $row = DB::table(self::TABLE)
            ->where('id', $userId->value())
            ->first();

        if ($row === null) {
            return null;
        }

        return $this->toDomain($row);
    }

    /**
     * İstifadəçini email ilə tap.
     */
    public function findByEmail(Email $email): ?User
    {
        $row = DB::table(self::TABLE)
            ->where('email', $email->value())
            ->first();

        if ($row === null) {
            return null;
        }

        return $this->toDomain($row);
    }

    /**
     * İstifadəçini bazaya yaz.
     *
     * UPSERT STRATEGİYASI:
     * - updateOrInsert: Əgər ID varsa UPDATE, yoxdursa INSERT.
     * - Bu pattern Repository-lərdə çox yayılmışdır.
     *
     * EVENT DISPATCH:
     * - User bazaya yazıldıqdan SONRA Domain Event-lər dispatch olunur.
     * - NƏYƏ SONRA? Çünki əgər baza xətası olarsa, event göndərilməməlidir.
     * - pullDomainEvents() event-ləri alır VƏ siyahını təmizləyir.
     */
    public function save(User $user): void
    {
        DB::table(self::TABLE)->updateOrInsert(
            /**
             * Birinci array: WHERE şərti — hansı sətri yeniləmək lazım.
             */
            ['id' => $user->userId()->value()],
            /**
             * İkinci array: Yazılacaq/yenilənəcək dəyərlər.
             */
            [
                'id' => $user->userId()->value(),
                'name' => $user->name(),
                'email' => $user->email()->value(),
                'password' => $user->password()->hash(),
                'created_at' => $user->createdAt()->format('Y-m-d H:i:s'),
                'updated_at' => now()->format('Y-m-d H:i:s'),
            ],
        );

        /**
         * Domain Event-ləri dispatch et.
         * pullDomainEvents() event-ləri qaytarır və Aggregate-in daxili siyahısını təmizləyir.
         * Beləliklə eyni event iki dəfə göndərilmir.
         */
        $events = $user->pullDomainEvents();
        $this->eventDispatcher->dispatch($events);
    }

    /**
     * Baza sətirini (stdClass) Domain Entity-yə çevir.
     *
     * MAPPİNG PRİNSİPİ:
     * - Baza primitiv tiplər saxlayır (string, int).
     * - Domain Value Object-lər istifadə edir (Email, Password, UserId).
     * - Bu metod primitiv → Value Object çevrilməsini edir.
     *
     * reconstruct() istifadə edirik (create() deyil!) — çünki:
     * - Bu istifadəçi artıq əvvəl yaradılıb — yenidən event qeyd edilməməlidir.
     * - Password::fromHash() istifadə edirik — artıq hash-lənmiş şifrəni qəbul edir.
     */
    private function toDomain(\stdClass $row): User
    {
        return User::reconstruct(
            userId: UserId::fromString($row->id),
            name: $row->name,
            email: Email::fromString($row->email),
            password: Password::fromHash($row->password),
            createdAt: new \DateTimeImmutable($row->created_at),
        );
    }
}
