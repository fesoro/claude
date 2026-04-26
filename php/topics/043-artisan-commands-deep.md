# Artisan Commands (Middle)

## Mündəricat
1. [Artisan nədir?](#artisan-nədir)
2. [Command yaratma](#command-yaratma)
3. [Signature & Arguments & Options](#signature--arguments--options)
4. [Input/Output (prompts)](#inputoutput-prompts)
5. [Progress bar & tables](#progress-bar--tables)
6. [Scheduler](#scheduler)
7. [Background & long-running command](#background--long-running-command)
8. [Signal handling (graceful shutdown)](#signal-handling-graceful-shutdown)
9. [Testing commands](#testing-commands)
10. [Symfony Console nüansları](#symfony-console-nüansları)
11. [İntervyu Sualları](#intervyu-sualları)

---

## Artisan nədir?

```
Artisan = Laravel-in CLI interface-i (Symfony Console üzərində qurulub).
"php artisan ..." komandaları:

  Built-in:
    make:controller, make:model, migrate, db:seed, queue:work,
    route:list, config:cache, serve, tinker, schedule:run

  Custom:
    php artisan import:users
    php artisan reports:generate

Use case:
  - Maintenance script (cleanup, backup)
  - Cron job (scheduled task)
  - One-off migration
  - Batch processing
  - Worker / daemon
  - Developer tooling (scaffolding)
```

---

## Command yaratma

```bash
php artisan make:command ImportUsers
# app/Console/Commands/ImportUsers.php yaranır
```

```php
<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;

class ImportUsers extends Command
{
    // CLI signature
    protected $signature = 'users:import
                            {file : CSV file path}
                            {--chunk=1000 : Chunk size}
                            {--dry-run : Test mode, no DB write}';
    
    protected $description = 'Import users from CSV file';
    
    public function handle(UserImporter $importer): int
    {
        $file = $this->argument('file');
        $chunk = (int) $this->option('chunk');
        $dryRun = $this->option('dry-run');
        
        if (!file_exists($file)) {
            $this->error("File not found: $file");
            return Command::FAILURE;
        }
        
        $this->info("Importing from $file...");
        
        $count = $importer->import($file, $chunk, $dryRun);
        
        $this->info("Imported $count users");
        return Command::SUCCESS;
    }
}
```

```bash
# İstifadə
php artisan users:import /tmp/users.csv
php artisan users:import /tmp/users.csv --chunk=500 --dry-run
```

---

## Signature & Arguments & Options

```php
<?php
// Signature DSL
protected $signature = 'mail:send
                        {user : Required argument}
                        {role? : Optional argument}
                        {tags?* : Optional array (multiple values)}
                        {--queue= : Option with value}
                        {--Q|queue= : Option with shortcut}
                        {--admin : Boolean flag}
                        {--D|debug : Boolean with shortcut}';

// Read
$user = $this->argument('user');     // string
$tags = $this->argument('tags');     // array (because *)

$queue = $this->option('queue');     // string ya null
$admin = $this->option('admin');     // bool

// İstifadə
// php artisan mail:send 1                                  → user=1
// php artisan mail:send 1 admin                            → user=1, role=admin
// php artisan mail:send 1 admin tag1 tag2                  → tags=[tag1, tag2]
// php artisan mail:send 1 --queue=high --admin             → queue=high, admin=true
```

---

## Input/Output (prompts)

```php
<?php
// Output methods
$this->line('Plain text');
$this->info('Green');
$this->comment('Yellow');
$this->question('Cyan');
$this->error('Red');
$this->warn('Yellow warning');

$this->newLine();
$this->newLine(3);  // 3 boş sətir

// Verbose levels
$this->line('debug', null, OutputInterface::VERBOSITY_DEBUG);
// php artisan ... -v   → normal verbose
// php artisan ... -vvv → debug verbose

// PROMPT (interactive)
$name = $this->ask('What is your name?');
$pass = $this->secret('Password (hidden):');
$bool = $this->confirm('Continue?', default: true);
$choice = $this->choice('Color?', ['red', 'green', 'blue'], default: 'red');
$multi = $this->choice('Tags?', ['a', 'b', 'c'], multiple: true);

// Anticipated values (autocomplete)
$name = $this->anticipate('Name?', ['Ali', 'Bob', 'Carol']);

// Laravel 10+ Prompts package (ən yaxşı)
use function Laravel\Prompts\{text, password, confirm, select, multiselect, search};

$name = text('What is your name?', required: true);
$pass = password('Password:');
$ok   = confirm('Continue?');
$role = select('Role?', ['admin', 'user', 'guest']);
$tags = multiselect('Tags?', ['php', 'js', 'go']);
$user = search('Find user', fn($q) => User::where('name', 'like', "%$q%")->pluck('name', 'id')->all());

// Yarımavtomatik confirmation
if (! $this->confirm('This will delete all data. Continue?')) {
    return Command::FAILURE;
}
```

---

## Progress bar & tables

```php
<?php
// Progress bar
$users = User::all();
$bar = $this->output->createProgressBar($users->count());
$bar->start();

foreach ($users as $user) {
    $this->processUser($user);
    $bar->advance();
}

$bar->finish();
$this->newLine();

// Higher-level helper
$this->withProgressBar($users, function ($user) {
    $this->processUser($user);
});

// Table
$this->table(
    ['ID', 'Name', 'Email'],
    User::take(10)->get(['id', 'name', 'email'])->toArray()
);
// ┌────┬─────────┬──────────────────┐
// │ ID │ Name    │ Email            │
// ├────┼─────────┼──────────────────┤
// │ 1  │ Ali     │ ali@example.com  │
// └────┴─────────┴──────────────────┘
```

---

## Scheduler

```php
<?php
// app/Console/Kernel.php (Laravel 10) ya da bootstrap/app.php (Laravel 11+)

protected function schedule(Schedule $schedule): void
{
    // Hər gün gecə yarısı
    $schedule->command('cache:prune-stale-tags')->daily();
    
    // Hər saat
    $schedule->command('reports:hourly')->hourly();
    
    // Hər 5 dəqiqə
    $schedule->command('queue:monitor')->everyFiveMinutes();
    
    // Custom cron
    $schedule->command('backup:run')->cron('0 3 * * *');   // 3:00 AM
    
    // Closure
    $schedule->call(function () {
        DB::table('audit_logs')->where('created_at', '<', now()->subYear())->delete();
    })->weekly();
    
    // Job
    $schedule->job(new GenerateReports)->dailyAt('02:00');
    
    // Conditional
    $schedule->command('users:welcome-email')
        ->daily()
        ->when(fn() => User::whereNull('welcomed_at')->exists());
    
    // Constraints
    $schedule->command('newsletter:send')
        ->weeklyOn(Schedule::MONDAY, '08:00')
        ->timezone('Asia/Baku')
        ->onOneServer()                  // multi-server: yalnız 1 server icra etsin
        ->withoutOverlapping(60)         // mövcud icra varsa skip (60s lock)
        ->runInBackground()              // long task, scheduler-i blocklamasin
        ->emailOutputOnFailure('alerts@example.com');
}

// Cron entry (server-də)
// * * * * * cd /var/www/app && php artisan schedule:run >> /dev/null 2>&1

// Laravel 11+: bootstrap/app.php
->withSchedule(function (Schedule $schedule) {
    $schedule->command('cache:prune-stale-tags')->daily();
})
```

---

## Background & long-running command

```php
<?php
// LONG-RUNNING WORKER (queue:work tip)
class ProcessQueueCommand extends Command
{
    protected $signature = 'queue:custom-work {--max-jobs=1000}';
    
    public function handle(): int
    {
        $jobs = 0;
        $maxJobs = (int) $this->option('max-jobs');
        
        while ($jobs < $maxJobs) {
            $job = $this->popNextJob();
            if (!$job) {
                sleep(1);
                continue;
            }
            
            try {
                $job->handle();
                $jobs++;
            } catch (\Throwable $e) {
                $this->error($e->getMessage());
            }
            
            // Memory check
            if (memory_get_usage(true) > 128 * 1024 * 1024) {
                $this->warn('Memory limit, restarting');
                break;
            }
        }
        
        $this->info("Processed $jobs jobs");
        return Command::SUCCESS;
    }
}

// SUPERVISOR config
// /etc/supervisor/conf.d/custom-worker.conf
// [program:custom-worker]
// command=php /var/www/app/artisan queue:custom-work
// autorestart=true
// numprocs=4
```

---

## Signal handling (graceful shutdown)

```php
<?php
// Laravel 9+ — signal handling
class LongRunningCommand extends Command
{
    protected $signature = 'process:large-data';
    
    private bool $shouldStop = false;
    
    public function handle(): int
    {
        // SIGTERM (Kubernetes, supervisor stop)
        $this->trap([SIGTERM, SIGINT], function () {
            $this->shouldStop = true;
            $this->warn('Graceful shutdown requested');
        });
        
        $items = Item::cursor();
        
        foreach ($items as $item) {
            if ($this->shouldStop) {
                $this->info('Shutting down gracefully');
                break;
            }
            
            $this->processItem($item);
        }
        
        return Command::SUCCESS;
    }
}

// PCNTL extension lazımdır:
// extension=pcntl
// CLI-də default ON, FPM-də NOT
```

---

## Testing commands

```php
<?php
// PHPUnit / Pest
public function test_import_command(): void
{
    Storage::fake('local');
    Storage::disk('local')->put('users.csv', "name,email\nAli,a@b.com");
    
    $this->artisan('users:import', [
            'file' => Storage::disk('local')->path('users.csv'),
            '--chunk' => 100,
        ])
        ->expectsOutput('Importing from ...')
        ->expectsOutput('Imported 1 users')
        ->assertExitCode(0);
    
    $this->assertDatabaseHas('users', ['email' => 'a@b.com']);
}

// Interactive prompt mock
public function test_interactive(): void
{
    $this->artisan('users:create')
        ->expectsQuestion('What is your name?', 'Ali')
        ->expectsQuestion('Email?', 'ali@b.com')
        ->expectsConfirmation('Continue?', 'yes')
        ->expectsOutput('User created')
        ->assertExitCode(0);
}

// Pest
it('imports users', function () {
    Storage::fake('local');
    // ...
    
    artisan('users:import', ['file' => '/path'])
        ->expectsOutput('Imported')
        ->assertSuccessful();
});
```

---

## Symfony Console nüansları

```php
<?php
// Artisan = Symfony Console + Laravel
// Saf Symfony Console-da:

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:create-user', description: 'Create a new user')]
class CreateUserCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'User email');
        $this->addOption('admin', null, null, 'Make user admin');
    }
    
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = $input->getArgument('email');
        
        $io->success("User created: $email");
        return Command::SUCCESS;
    }
}

// bin/console
// php bin/console app:create-user test@test.com --admin
```

---

## İntervyu Sualları

- `php artisan` arxa planda hansı framework istifadə edir?
- Argument vs Option fərqi?
- `withoutOverlapping` niyə vacibdir scheduler-də?
- `onOneServer` multi-server mühitdə nə edir?
- Long-running command-də signal handling niyə vacibdir?
- `pcntl` extension nə üçün lazımdır?
- Scheduler `runInBackground` nə vaxt istifadə olunur?
- Laravel Prompts ilə klassik `ask()` arasında fərq?
- Memory leak-li uzun command-də necə restart edirsiniz?
- Closure scheduling vs command scheduling — fərqlər?
- Artisan command-i unit test necə edilir?
- `php artisan ... -vvv` nə edir?
