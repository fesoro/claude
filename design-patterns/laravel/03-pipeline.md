# Pipeline (Senior ⭐⭐⭐)

## İcmal

Pipeline pattern, data və ya request-i ardıcıl stage-lərdən keçirir; hər stage bir transformasiya və ya validation əməliyyatı icra edir. Laravel-in özündə `Illuminate\Pipeline\Pipeline` class-ı mövcuddur — middleware sistemi də bu pattern üzərindədir.

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

- **Common mistakes**:
  1. Pipe-da side effects (email göndərmək, external API call) etmək — idempotency pozulur; pipeline-ı retry etdikdə side effect-lər təkrarlana bilər; side effects-i ən sona bıraxın
  2. Pipe-lar arası implicit state — bir pipe-in dəyişdirdiyi şeyi başqası gizli şəkildə oxuyursa, sıralama mühüm olur və test çətinləşir

### Anti-Pattern Nə Zaman Olur?

**Pipeline for simple steps** — sadəcə ardıcıl metodlar üçün:
```php
// BAD — 2 addım üçün pipeline overkill
class ProcessPaymentService
{
    public function process(PaymentData $data): void
    {
        // Bu sadəcə iki metod çağırışıdır — pipeline lazım deyil
        app(Pipeline::class)
            ->send($data)
            ->through([ValidateCard::class, ChargeCard::class])
            ->thenReturn();
    }
}

// GOOD — sadə ardıcıl metodlar
class ProcessPaymentService
{
    public function process(PaymentData $data): void
    {
        $this->validate($data);
        $this->charge($data);
    }
}
```

**Side effect-ləri ortada etmək:**
```php
// BAD — email göndərmək ortada — pipeline fail olsa email artıq getmişdir
class SendWelcomeEmail
{
    public function handle(RegistrationData $data, Closure $next): mixed
    {
        Mail::to($data->email)->send(new WelcomeMail()); // Side effect ORTADA!
        return $next($data); // Növbəti pipe fail olsa email geri alınmaz
    }
}

// GOOD — side effect-lər ən sonda, ya da job ilə
class SendWelcomeEmail
{
    public function handle(RegistrationData $data, Closure $next): mixed
    {
        $result = $next($data); // Əvvəl növbətiləri çalışdır
        // Yalnız hər şey uğurlu olduqda email göndər
        SendWelcomeEmailJob::dispatch($data->user);
        return $result;
    }
}
```

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

// Payload DTO — pipeline boyunca bir object gedir
class RegistrationData
{
    public ?User $user = null; // Mutable field — pipe-lar doldurur

    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $password,
        public readonly string $role = 'user',
    ) {}
}

// Stage 1: Validate — guard
class ValidateRegistrationData
{
    public function handle(RegistrationData $data, Closure $next): mixed
    {
        // Şərt yerinə yetirilmədikdə exception — pipeline dayandırır
        if (User::where('email', $data->email)->exists()) {
            throw new \DomainException("Email already registered: {$data->email}");
        }

        return $next($data); // Keçid — növbəti stage-ə
    }
}

// Stage 2: Create user — persist
class CreateUser
{
    public function handle(RegistrationData $data, Closure $next): mixed
    {
        // $data mutable — user-i əlavə edirik, sonrakı stage-lər istifadə edə bilər
        $data->user = User::create([
            'name'     => $data->name,
            'email'    => $data->email,
            'password' => Hash::make($data->password),
        ]);

        return $next($data);
    }
}

// Stage 3: Assign role — enrichment
class AssignDefaultRole
{
    public function handle(RegistrationData $data, Closure $next): mixed
    {
        // User artıq yaradılmışdır (əvvəlki stage), role əlavə edirik
        $data->user->assignRole($data->role);

        return $next($data);
    }
}

// Stage 4: Send welcome email — side effect ən SONDA
class SendWelcomeEmail
{
    public function handle(RegistrationData $data, Closure $next): mixed
    {
        // Əvvəlcə növbəti stage-ləri çalışdır
        $result = $next($data);

        // Yalnız hər şey uğurlu olduqdan sonra email — job ilə, sync deyil
        \App\Jobs\SendWelcomeEmailJob::dispatch($data->user);

        return $result;
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

        // Sıralama vacibdir: validate → persist → enrich → side effects
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

// Hər row-u pipeline-dan keçiririk — error-ı izolə edirik
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
                        TrimWhitespace::class,         // normalize
                        ValidateRequiredFields::class,  // guard
                        TransformDataTypes::class,      // transform
                        CheckDuplicates::class,         // business rule
                        PersistRow::class,              // persist
                    ])
                    ->thenReturn();

                $result->incrementSuccess();
            } catch (\Exception $e) {
                // Bir row fail olur, digərləri davam edir
                $result->addError($row, $e->getMessage());
            }
        }

        return $result;
    }
}
```

**Stage-i unit test etmək:**

```php
class ValidateRegistrationDataTest extends TestCase
{
    public function test_throws_if_email_taken(): void
    {
        User::factory()->create(['email' => 'test@example.com']);

        $stage = new ValidateRegistrationData();
        $data  = new RegistrationData('Name', 'test@example.com', 'pass');

        $this->expectException(\DomainException::class);

        // Stage-i birbaşa test etmək mümkündür — pipeline lazım deyil
        $stage->handle($data, fn($d) => $d);
    }

    public function test_passes_when_email_free(): void
    {
        $stage = new ValidateRegistrationData();
        $data  = new RegistrationData('Name', 'new@example.com', 'pass');

        $called = false;
        $stage->handle($data, function ($d) use (&$called) {
            $called = true;
            return $d;
        });

        $this->assertTrue($called); // $next çağırıldı
    }
}
```

## Praktik Tapşırıqlar

1. Laravel `Illuminate\Pipeline\Pipeline` class-ını source code-da oxuyun (`vendor/laravel/framework/src/Illuminate/Pipeline`) — `thenReturn()` vs `then(Closure)` fərqini anlayın
2. Mövcud bir Laravel controller-da ardıcıl validation + business logic addımları tapın, onları Pipeline stage-lərinə çevirin
3. CSV import üçün Pipeline yazın: whitespace trim → required field validation → type cast → duplicate check → DB persist; hər stage-i ayrı unit test ilə əhatə edin
4. `LoggingPipe` yaradın — hər stage-in əvvəl və sonrasını log edir; istənilən pipeline-a əlavə olunabilir

## Əlaqəli Mövzular

- [02-service-layer.md](02-service-layer.md) — Pipeline service layer-in daxilindən istifadə olunur
- [08-command-query-bus.md](08-command-query-bus.md) — Bus middleware pipeline ilə eyni prinsip
- [../behavioral/06-chain-of-responsibility.md](../behavioral/06-chain-of-responsibility.md) — Pipeline hər stage-dən keçir; CoR birini tapıb dayandırır
- [../structural/03-decorator.md](../structural/03-decorator.md) — Pipeline-a bənzər ardıcıl wrapping
- [../behavioral/03-command.md](../behavioral/03-command.md) — Hər pipe-ı command kimi modelləmək mümkündür
- [../general/02-code-smells-refactoring.md](../general/02-code-smells-refactoring.md) — Uzun if/else chain-ləri pipeline ilə refactor etmək
