# Pipeline (Senior ⭐⭐⭐)

## İcmal
Pipeline pattern, data və ya request-i ardıcıl stage-lərdən keçirir; hər stage bir transformasiya və ya validation əməliyyatı icra edir. Laravel-in özündə `Illuminate\Pipeline\Pipeline` class-ı mövcuddur — middleware sistemi də bu pattern üzərində qurulmuşdur.

## Niyə Vacibdir
Real Laravel layihələrində mürəkkəb business process-lər (user registration, order processing, data import) bir neçə ardıcıl addımdan ibarət olur. Bu addımları ayrı-ayrı if/else bloklarında yazmaq yerinə Pipeline ilə hər addımı izolə etmək, test etmək və sıralamanı dəyişmək asanlaşır. Laravel Middleware pipeline-ın özü bu pattern-in ən böyük real-world nümunəsidir.

## Əsas Anlayışlar
- **Stage / Pipe**: bir transformasiya və ya validation mərhələsi; `handle($payload, Closure $next)` signature-u ilə middleware-ə bənzər interface
- **Payload**: pipeline boyunca ötürülən data (DTO, array, Eloquent model, custom object)
- **Pipeline**: bütün stage-ləri ardıcıl icra edən koordinator; `send()->through()->thenReturn()`
- **Pass-through**: `$next($payload)` çağırmaq — növbəti stage-ə keçiş
- **Short-circuit**: `$next` çağırmamaq — pipeline-ı dayandırmaq (validation failure zamanı)

## Praktik Baxış
- **Real istifadə**: user registration flow, CSV import processing, HTTP request/response lifecycle, payment processing steps, data transformation chains
- **Trade-off-lar**: stage-ləri ayrı-ayrı test etmək asandır; lakin stage-lər arasındakı error handling mürəkkəbdir — exception catch etmək üçün ya global try/catch, ya da hər stage-də logging lazımdır
- **İstifadə etməmək**: sadə, tək-addımlı əməliyyatlar üçün; stage sayı 2-3-dən azdırsa pipeline overkill-dir
- **Common mistakes**: pipe-da side effects (email göndərmək, external API call) etmək — idempotency pozulur; pipeline-ı retry etdikdə side effect-lər təkrarlana bilər; side effects-i ən sona bıraxın

## Nümunələr

### Ümumi Nümunə
Bir zavod assembly line düşünün: hər stansiya öncəki stansiyadan məhsul alır, öz əməliyyatını icra edir, sonrakı stansiyaya ötürür. Hər stansiya müstəqildir — birini dəyişdikdə digərləri təsirlənmir. Pipeline pattern-i də eyni məntiqə əsaslanır.

### PHP/Laravel Nümunəsi

```php
<?php

namespace App\Pipelines\Registration;

use App\DTOs\RegistrationData;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\Hash;

// Payload DTO
class RegistrationData
{
    public ?User $user = null;

    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $password,
        public readonly string $role = 'user',
    ) {}
}

// Stage 1: Validate
class ValidateRegistrationData
{
    public function handle(RegistrationData $data, Closure $next): mixed
    {
        if (User::where('email', $data->email)->exists()) {
            throw new \DomainException("Email already registered: {$data->email}");
        }

        return $next($data);
    }
}

// Stage 2: Hash password
class HashPassword
{
    public function handle(RegistrationData $data, Closure $next): mixed
    {
        // RegistrationData immutable olduğu üçün yeni DTO yaratmaq lazım
        // Əgər mutable DTO istifadə edirsinizsə: $data->hashedPassword = Hash::make(...)
        return $next($data);
    }
}

// Stage 3: Persist
class CreateUser
{
    public function handle(RegistrationData $data, Closure $next): mixed
    {
        $data->user = User::create([
            'name'     => $data->name,
            'email'    => $data->email,
            'password' => Hash::make($data->password),
        ]);

        return $next($data);
    }
}

// Stage 4: Assign role
class AssignDefaultRole
{
    public function handle(RegistrationData $data, Closure $next): mixed
    {
        $data->user->assignRole($data->role);

        return $next($data);
    }
}

// Stage 5: Send welcome email (side effect — ən sonda)
class SendWelcomeEmail
{
    public function handle(RegistrationData $data, Closure $next): mixed
    {
        // Job dispatch etmək daha güvənlidir — pipeline uğursuz olsa email getmir
        \App\Jobs\SendWelcomeEmailJob::dispatch($data->user);

        return $next($data);
    }
}

// Usage — Service class-da
use Illuminate\Pipeline\Pipeline;

class UserRegistrationService
{
    public function __construct(private Pipeline $pipeline) {}

    public function register(array $input): User
    {
        $data = new RegistrationData(
            name: $input['name'],
            email: $input['email'],
            password: $input['password'],
        );

        $result = $this->pipeline
            ->send($data)
            ->through([
                ValidateRegistrationData::class,
                CreateUser::class,
                AssignDefaultRole::class,
                SendWelcomeEmail::class,
            ])
            ->thenReturn();

        return $result->user;
    }
}
```

**CSV Import Pipeline nümunəsi:**

```php
<?php

// Daha mürəkkəb: hər row-u pipeline-dan keçiririk
class CsvImportPipeline
{
    public function __construct(private Pipeline $pipeline) {}

    public function import(array $rows): ImportResult
    {
        $result = new ImportResult();

        foreach ($rows as $row) {
            try {
                $this->pipeline
                    ->send(new ImportRow($row))
                    ->through([
                        TrimWhitespace::class,
                        ValidateRequiredFields::class,
                        TransformDataTypes::class,
                        CheckDuplicates::class,
                        PersistRow::class,
                    ])
                    ->thenReturn();

                $result->incrementSuccess();
            } catch (\Exception $e) {
                $result->addError($row, $e->getMessage());
            }
        }

        return $result;
    }
}
```

## Praktik Tapşırıqlar
1. Laravel `Illuminate\Pipeline\Pipeline` class-ını source code-da oxuyun (`vendor/laravel/framework/src/Illuminate/Pipeline`) — `thenReturn()` vs `then(Closure)` fərqini anlayın
2. Mövcud bir Laravel controller-da ardıcıl validation + business logic addımları tapın, onları Pipeline stage-lərinə çevirin
3. CSV import üçün Pipeline yazın: whitespace trim → required field validation → type cast → duplicate check → DB persist; hər stage-i ayrı unit test ilə əhatə edin
4. `LoggingPipe` yaradın — hər stage-in əvvəl və sonrasını log edir; istənilən pipeline-a əlavə olunabilir

## Əlaqəli Mövzular
- [Chain of Responsibility](08-chain-of-responsibility.md) — pipeline hər stage-dən keçir + data transform edir; CoR birini tapıb dayandırır
- [Decorator](10-decorator.md) — pipeline-a bənzər ardıcıl wrapping, lakin recursion əsaslı
- [Command](11-command.md) — hər pipe-ı command kimi modelləmək mümkündür
- [Middleware Pattern](../php/topics/) — Laravel HTTP pipeline eyni konsept
