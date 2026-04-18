<?php

declare(strict_types=1);

namespace Database\Factories;

use Src\User\Infrastructure\Models\UserModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * İSTİFADƏÇİ FACTORY
 * ====================
 *
 * LARAVEL FACTORY PATTERN NƏDİR?
 * ================================
 * Factory — test və seed zamanı model instansları yaratmaq üçün istifadə olunan şablondur.
 * Əl ilə hər dəfə uzun create() yazmaq əvəzinə, Factory bütün sahələri avtomatik doldurur.
 *
 * Factory OLMADAN:
 *   UserModel::create([
 *       'name' => 'Test',
 *       'email' => 'test@test.com',
 *       'password' => Hash::make('password'),
 *       'is_active' => true,
 *   ]);
 *
 * Factory İLƏ:
 *   UserModel::factory()->create();  // Bütün sahələr avtomatik Faker ilə doldurulur!
 *
 * ƏSAS METODLAR:
 *
 * 1. definition() — Default sahə dəyərlərini təyin edir.
 *    Faker istifadə edərək realistik test datası yaradır.
 *    fake()->name() → 'John Doe', fake()->safeEmail() → 'john@example.com'
 *
 * 2. configure() — Factory yaradıldıqdan sonra əlavə əməliyyatlar təyin edir.
 *    afterMaking() — model instansı yaradıldıqdan SONRA (DB-yə yazılmadan əvvəl)
 *    afterCreating() — model DB-yə yazıldıqdan SONRA (relation yaratmaq üçün ideal)
 *
 * 3. States (Vəziyyətlər) — definition()-i override edən variantlar:
 *    UserModel::factory()->admin()->create()     → is_active=true olan admin
 *    UserModel::factory()->inactive()->create()  → is_active=false olan user
 *    State-lər zəncir şəklində birləşdirilə bilər:
 *    UserModel::factory()->admin()->inactive()->create()
 *
 * 4. Sequences (Ardıcıllıqlar) — hər instans üçün fərqli dəyərlər:
 *    UserModel::factory()->count(3)->sequence(
 *        ['name' => 'Birinci'],
 *        ['name' => 'İkinci'],
 *        ['name' => 'Üçüncü'],
 *    )->create();
 *
 * 5. afterCreating Hook — model DB-yə yazıldıqdan sonra işləyir:
 *    $factory->afterCreating(function (UserModel $user) {
 *        // Burada əlaqəli model-lər yaratmaq olar
 *        // Məsələn: istifadəçiyə profil yaratmaq
 *    });
 *
 * FACTORY İSTİFADƏ NÜMUNƏLƏRİ:
 *   UserModel::factory()->create();              // 1 user yarat və DB-yə yaz
 *   UserModel::factory()->make();                // 1 user yarat amma DB-yə yazma
 *   UserModel::factory()->count(10)->create();   // 10 user yarat
 *   UserModel::factory()->create(['name' => 'Orxan']); // Adı override et
 *
 * @extends Factory<UserModel>
 */
class UserFactory extends Factory
{
    /**
     * Bu factory-nin hansı model üçün olduğunu təyin edir.
     * DDD strukturunda model src/ qovluğundadır, ona görə tam namespace yazılır.
     */
    protected $model = UserModel::class;

    /**
     * Şifrənin cache-lənmiş versiyası.
     * Hash::make() yavaş əməliyyatdır (bcrypt), hər factory çağırışında
     * yenidən hash etmək əvəzinə bir dəfə hash edib saxlayırıq.
     * static keyword ilə bütün instanslar eyni hash-i istifadə edir.
     */
    protected static ?string $password = null;

    /**
     * DEFAULT SAHƏ DƏYƏRLƏRİ
     *
     * Bu metod hər factory()->create() çağırışında istifadə olunur.
     * Faker (fake() helper) realistik test datası yaradır:
     * - fake()->name() → 'John Doe', 'Jane Smith' və s.
     * - fake()->unique()->safeEmail() → unique + @example.com domeni
     * - unique() — eyni email-in təkrarlanmamasını təmin edir
     *
     * @return array<string, mixed> - sahə adı => dəyər cütləri
     */
    public function definition(): array
    {
        return [
            'name'      => fake()->name(),
            'email'     => fake()->unique()->safeEmail(),
            'password'  => static::$password ??= Hash::make('password'),
            'is_active' => true,
        ];
    }

    /**
     * Admin state — admin istifadəçi yaratmaq üçün.
     *
     * STATE NƏDİR?
     * State — definition()-dəki default dəyərləri override edən metoddur.
     * $this->state() çağıraraq fərqli variantlar yaradırıq.
     *
     * İstifadəsi: UserModel::factory()->admin()->create()
     *
     * @return static - factory instansını qaytarır (method chaining üçün)
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'name'  => 'Admin İstifadəçi',
            'email' => 'admin@example.com',
        ]);
    }

    /**
     * Deaktiv istifadəçi state-i.
     * is_active = false olan istifadəçi — sistemə daxil ola bilməz.
     *
     * İstifadəsi: UserModel::factory()->inactive()->create()
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
