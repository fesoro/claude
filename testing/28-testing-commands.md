# Testing Artisan Commands

## Nədir? (What is it?)

Artisan command testing, Laravel CLI komandalarının (`php artisan ...`) input, output,
exit code və yan təsirlərini (database dəyişiklikləri, job dispatch) yoxlamaq prosesidir.
CLI komandaları çox zaman kritikdir — daily reports, data migrations, cleanup, import —
buna görə də onların behaviors-u test olunmalıdır.

Laravel test framework-unda `artisan()` helper və xüsusi assertion-lar (`expectsQuestion`,
`expectsOutput`, `assertExitCode`) mövcuddur.

### Niyə Command Testing Lazımdır?

1. **Scheduled task-lar** - Daily/hourly komandalar production-da avtomatik işləyir
2. **Manual ops** - DevOps komandaları (import, cleanup) təhlükəsiz olmalıdır
3. **Interactive prompts** - User input CLI-də hazır tutulur
4. **Exit codes** - CI/CD pipeline-da exit code vacibdir
5. **Idempotency** - Eyni komanda 2 dəfə işlədilsə problem olmasın

## Əsas Konseptlər (Key Concepts)

### artisan() Helper

```php
$this->artisan('email:send user@example.com')
    ->expectsQuestion('Confirm?', 'yes')
    ->expectsOutput('Sending...')
    ->expectsOutput('Done!')
    ->doesntExpectOutput('Failed')
    ->assertExitCode(0);
```

### Assertion Methods

| Method | Məqsəd |
|--------|--------|
| `expectsQuestion($q, $answer)` | Interactive prompt |
| `expectsConfirmation($q, 'yes')` | Y/N prompt |
| `expectsChoice($q, $answer, $options)` | Choice prompt |
| `expectsOutput($text)` | Output string match |
| `expectsOutputToContain($text)` | Substring match |
| `doesntExpectOutput($text)` | Output yoxdur |
| `expectsTable($headers, $rows)` | Table output |
| `assertExitCode(0)` | Success exit |
| `assertFailed()` | Non-zero exit |
| `assertSuccessful()` | Zero exit |

### Exit Code Standard

```
0 - Success
1 - General error
2 - Misuse of shell command
126 - Cannot execute
127 - Command not found
128+n - Fatal signal n
```

## Praktiki Nümunələr (Practical Examples)

### Basic Command Test

```php
public function test_cache_clear_command_runs(): void
{
    $this->artisan('cache:clear')
        ->expectsOutput('Application cache cleared successfully.')
        ->assertExitCode(0);
}
```

### Interactive Command

```php
public function test_create_user_command_with_prompts(): void
{
    $this->artisan('user:create')
        ->expectsQuestion('Name?', 'Orkhan')
        ->expectsQuestion('Email?', 'orkhan@example.com')
        ->expectsQuestion('Password?', 'secret')
        ->expectsOutput('User created!')
        ->assertExitCode(0);

    $this->assertDatabaseHas('users', ['email' => 'orkhan@example.com']);
}
```

## PHP/Laravel ilə Tətbiq (Implementation with PHP/Laravel)

### 1. Simple Cleanup Command

```php
// app/Console/Commands/CleanupExpiredTokens.php
class CleanupExpiredTokens extends Command
{
    protected $signature = 'tokens:cleanup {--dry-run : Only show what would be deleted}';
    protected $description = 'Delete expired API tokens';

    public function handle(): int
    {
        $expired = PersonalAccessToken::where('expires_at', '<', now())->get();

        if ($expired->isEmpty()) {
            $this->info('No expired tokens.');
            return self::SUCCESS;
        }

        $this->info("Found {$expired->count()} expired tokens.");

        if ($this->option('dry-run')) {
            $this->comment('Dry run — not deleted.');
            return self::SUCCESS;
        }

        $expired->each->delete();
        $this->info('Deleted.');

        return self::SUCCESS;
    }
}

// tests/Feature/Commands/CleanupExpiredTokensTest.php
namespace Tests\Feature\Commands;

use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CleanupExpiredTokensTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_outputs_message_when_no_expired_tokens(): void
    {
        $this->artisan('tokens:cleanup')
            ->expectsOutput('No expired tokens.')
            ->assertExitCode(0);
    }

    public function test_command_deletes_expired_tokens(): void
    {
        PersonalAccessToken::factory()->create(['expires_at' => now()->subDay()]);
        PersonalAccessToken::factory()->create(['expires_at' => now()->subHour()]);
        $valid = PersonalAccessToken::factory()->create(['expires_at' => now()->addDay()]);

        $this->artisan('tokens:cleanup')
            ->expectsOutput('Found 2 expired tokens.')
            ->expectsOutput('Deleted.')
            ->assertExitCode(0);

        $this->assertSame(1, PersonalAccessToken::count());
        $this->assertTrue($valid->fresh()->exists);
    }

    public function test_dry_run_does_not_delete(): void
    {
        PersonalAccessToken::factory()->count(3)->create(['expires_at' => now()->subDay()]);

        $this->artisan('tokens:cleanup --dry-run')
            ->expectsOutput('Found 3 expired tokens.')
            ->expectsOutput('Dry run — not deleted.')
            ->assertExitCode(0);

        $this->assertSame(3, PersonalAccessToken::count());
    }
}
```

### 2. Interactive User Create Command

```php
// app/Console/Commands/CreateUserCommand.php
class CreateUserCommand extends Command
{
    protected $signature = 'user:create';
    protected $description = 'Interactively create a new user';

    public function handle(): int
    {
        $name     = $this->ask('Name?');
        $email    = $this->ask('Email?');
        $password = $this->secret('Password?');
        $role     = $this->choice('Role?', ['admin', 'editor', 'user'], 2);

        if (User::where('email', $email)->exists()) {
            $this->error("User with {$email} already exists.");
            return self::FAILURE;
        }

        if (! $this->confirm("Create {$email} as {$role}?", true)) {
            $this->comment('Aborted.');
            return self::FAILURE;
        }

        $user = User::create([
            'name'     => $name,
            'email'    => $email,
            'password' => bcrypt($password),
        ]);
        $user->assignRole($role);

        $this->info("User created with ID: {$user->id}");

        return self::SUCCESS;
    }
}

// Test
class CreateUserCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_user_with_provided_input(): void
    {
        $this->artisan('user:create')
            ->expectsQuestion('Name?', 'Orkhan')
            ->expectsQuestion('Email?', 'orkhan@example.com')
            ->expectsQuestion('Password?', 'secret123')
            ->expectsChoice('Role?', 'admin', ['admin', 'editor', 'user'])
            ->expectsConfirmation('Create orkhan@example.com as admin?', 'yes')
            ->expectsOutputToContain('User created with ID:')
            ->assertExitCode(0);

        $this->assertDatabaseHas('users', [
            'name'  => 'Orkhan',
            'email' => 'orkhan@example.com',
        ]);
    }

    public function test_aborts_if_user_cancels_confirmation(): void
    {
        $this->artisan('user:create')
            ->expectsQuestion('Name?', 'Orkhan')
            ->expectsQuestion('Email?', 'o@example.com')
            ->expectsQuestion('Password?', 'secret')
            ->expectsChoice('Role?', 'editor', ['admin', 'editor', 'user'])
            ->expectsConfirmation('Create o@example.com as editor?', 'no')
            ->expectsOutput('Aborted.')
            ->assertExitCode(1);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_fails_if_email_already_exists(): void
    {
        User::factory()->create(['email' => 'exist@example.com']);

        $this->artisan('user:create')
            ->expectsQuestion('Name?', 'X')
            ->expectsQuestion('Email?', 'exist@example.com')
            ->expectsQuestion('Password?', 'x')
            ->expectsChoice('Role?', 'user', ['admin', 'editor', 'user'])
            ->expectsOutput('User with exist@example.com already exists.')
            ->assertExitCode(1);
    }
}
```

### 3. Command with Output Table

```php
// app/Console/Commands/ListUsersCommand.php
class ListUsersCommand extends Command
{
    protected $signature = 'users:list {--role=}';

    public function handle(): int
    {
        $query = User::query();
        if ($role = $this->option('role')) {
            $query->role($role);
        }

        $users = $query->get(['id', 'name', 'email']);

        if ($users->isEmpty()) {
            $this->warn('No users found.');
            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Name', 'Email'],
            $users->map(fn ($u) => [$u->id, $u->name, $u->email])->toArray()
        );

        return self::SUCCESS;
    }
}

// Test
public function test_list_users_renders_table(): void
{
    User::factory()->create(['id' => 1, 'name' => 'Ali',  'email' => 'a@x.com']);
    User::factory()->create(['id' => 2, 'name' => 'Veli', 'email' => 'v@x.com']);

    $this->artisan('users:list')
        ->expectsTable(
            ['ID', 'Name', 'Email'],
            [
                [1, 'Ali',  'a@x.com'],
                [2, 'Veli', 'v@x.com'],
            ]
        )
        ->assertExitCode(0);
}
```

### 4. Command That Dispatches Jobs

```php
// app/Console/Commands/ImportProductsCommand.php
class ImportProductsCommand extends Command
{
    protected $signature = 'products:import {file}';

    public function handle(): int
    {
        $path = $this->argument('file');

        if (! Storage::exists($path)) {
            $this->error("File {$path} not found.");
            return self::FAILURE;
        }

        ImportProductsJob::dispatch($path);
        $this->info('Import queued.');

        return self::SUCCESS;
    }
}

// Test
public function test_import_command_dispatches_job(): void
{
    Queue::fake();
    Storage::fake('local');
    Storage::put('imports/products.csv', 'name,price');

    $this->artisan('products:import imports/products.csv')
        ->expectsOutput('Import queued.')
        ->assertExitCode(0);

    Queue::assertPushed(ImportProductsJob::class, function ($job) {
        return $job->path === 'imports/products.csv';
    });
}

public function test_missing_file_returns_failure(): void
{
    Storage::fake('local');

    $this->artisan('products:import missing.csv')
        ->expectsOutput('File missing.csv not found.')
        ->assertExitCode(1);
}
```

### 5. Progress Bar Test

```php
// Command
public function handle(): int
{
    $users = User::all();
    $bar = $this->output->createProgressBar($users->count());
    $bar->start();

    foreach ($users as $user) {
        // do something
        $bar->advance();
    }
    $bar->finish();
    $this->newLine();
    $this->info("Processed {$users->count()} users.");

    return self::SUCCESS;
}

// Test
public function test_processes_all_users(): void
{
    User::factory()->count(5)->create();

    $this->artisan('users:process')
        ->expectsOutputToContain('Processed 5 users.')
        ->assertExitCode(0);
}
```

### 6. Scheduled Command Test

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule): void
{
    $schedule->command('tokens:cleanup')->daily();
    $schedule->command('reports:daily')->dailyAt('02:00');
}

// Test
public function test_tokens_cleanup_is_scheduled_daily(): void
{
    $schedule = app(Schedule::class);

    $event = collect($schedule->events())->first(
        fn ($e) => str_contains($e->command, 'tokens:cleanup')
    );

    $this->assertNotNull($event);
    $this->assertSame('0 0 * * *', $event->expression); // daily midnight
}

public function test_daily_reports_scheduled_at_2am(): void
{
    $schedule = app(Schedule::class);

    $event = collect($schedule->events())->first(
        fn ($e) => str_contains($e->command, 'reports:daily')
    );

    $this->assertSame('0 2 * * *', $event->expression);
}
```

### 7. Command with Input/Output Mocking

```php
// Using Command directly in unit test
public function test_command_handle_method_directly(): void
{
    $command = new CleanupExpiredTokens;
    $command->setLaravel($this->app);

    $input  = new ArrayInput(['--dry-run' => true]);
    $output = new BufferedOutput;

    $exitCode = $command->run($input, $output);

    $this->assertSame(0, $exitCode);
    $this->assertStringContainsString('Dry run', $output->fetch());
}
```

### 8. Environment-Aware Command Test

```php
// Command restricted to production-only or dev-only
public function handle(): int
{
    if (! app()->environment('production')) {
        $this->error('This command only runs in production.');
        return self::FAILURE;
    }
    // ...
}

// Test
public function test_command_fails_in_test_environment(): void
{
    $this->artisan('dangerous:action')
        ->expectsOutput('This command only runs in production.')
        ->assertExitCode(1);
}

public function test_command_runs_in_production(): void
{
    app()->detectEnvironment(fn () => 'production');

    $this->artisan('dangerous:action')->assertExitCode(0);
}
```

## Interview Sualları

**Q1: Artisan command-ı niyə test etmək vacibdir?**
A: Scheduled task-lar production-da insansız işləyir. CLI komandaları çox zaman data və
ya state dəyişir — xətalı olsa prod-u çökdürə bilər.

**Q2: `expectsQuestion` necə işləyir?**
A: Command prompt göstərəndə test sistem avtomatik cavab verir. Sıra vacibdir — hər
`expectsQuestion` real prompt-a uyğun gəlməlidir.

**Q3: `assertExitCode(0)` vs `assertSuccessful` fərqi?**
A: Eynidir. `assertSuccessful` sintaktik şəkər-dir. `assertFailed` non-zero exit yoxlayır.

**Q4: Command-ın output-unu regex ilə necə yoxlayırıq?**
A: `expectsOutputToContain('partial')` substring match edir. Tam regex üçün output-u
buffer-ə götürüb manual assertion lazımdır.

**Q5: Scheduled task-ın registration-ı necə test olunur?**
A: `app(Schedule::class)->events()` ilə bütün registered event-lər alınır, command
adı və cron expression yoxlanılır.

**Q6: Command-ın `handle()` metodu exception atarsa nə olur?**
A: Exit code 1 olur, exception message output-a yazılır. Test-də
`expectException(X::class)` ilə yoxlamaq olar.

**Q7: `$this->info`, `$this->error`, `$this->warn` niyə fərqlidir?**
A: Konsol-da rəngli output və log level. `error` STDERR-ə, qalanları STDOUT-a yazılır.

**Q8: Interactive komanda CI-də non-interactive necə işlədilir?**
A: `--no-interaction` flag-i və ya bütün input-lar option kimi ötürülür. Test-də
`expectsQuestion` istifadə olunur.

**Q9: `signature` və `description` dəyişəndə test necə update olunmalıdır?**
A: Komanda adı dəyişsə test-lərdə `artisan('old:name')` yox olacaq. Constant-da
saxlamaq və ya `$command::class` istifadəsi daha yaxşıdır.

**Q10: Command-ın idempotency-ni necə test edirik?**
A: Eyni komanda-nı 2 dəfə çağırıb, eyni nəticə gözləyirik. Dəyişən data (timestamp)
mock edilməlidir.

## Best Practices / Anti-Patterns

### Best Practices

- **Exit code-u həmişə yoxlayın** - CI/CD pipeline exit code-dan asılıdır
- **Hər option/flag üçün test** - `--dry-run`, `--force`, `--env`
- **Dry-run mode test** - Data dəyişdirməyən mode çox vacibdir
- **Edge case input** - Boş, invalid, çox böyük input
- **Scheduled command test** - Registration və logic ayrıca
- **Use constants for signatures** - `CleanupCommand::SIGNATURE`
- **Faker-dən istifadə** - `@example.com` əvəzinə `faker->email()`

### Anti-Patterns

- **Real file system / API ilə test** - Fake istifadə edin
- **Yalnız happy path** - Error path-ları test olunmur
- **`->run()` vs `->artisan()`** - Production behavior-dan fərqlənə bilər
- **Exit code yoxlamamaq** - `assertExitCode(0)` olmadan silent failure
- **Long-running command-ı sync test** - Timeout riski; kiçik dataset istifadə edin
- **Output format-ına strict bağlanmaq** - Kiçik text dəyişiklik test-i qırır
- **Global state dəyişən komandalar** - Test isolation pozulur (RefreshDatabase lazımdır)
