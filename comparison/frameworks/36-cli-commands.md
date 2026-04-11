# CLI Əmrləri (Command-Line Interface)

## Giriş

Hər iki framework terminal vasitəsilə işləyən əmrlər yaratmağa imkan verir. Laravel-də bu, framework-ün əsas hissəsidir — Artisan adlı güclü CLI sistemi var. Spring-də isə CLI əmrləri bir neçə fərqli yanaşma ilə həyata keçirilir: `CommandLineRunner`, `ApplicationRunner` və ayrıca Spring Shell layihəsi ilə.

---

## Spring-də istifadəsi

### CommandLineRunner — Ən sadə yanaşma

`CommandLineRunner` Spring Boot tətbiqi başlayanda bir dəfə işləyən kod yazmaq üçün istifadə olunur:

```java
@Component
public class StartupTask implements CommandLineRunner {

    @Override
    public void run(String... args) throws Exception {
        System.out.println("Tətbiq başladı!");
        System.out.println("Arqumentlər: " + Arrays.toString(args));
        
        // Məsələn, cache-i isidin, ilkin data yükləyin və s.
        for (String arg : args) {
            System.out.println("Arqument: " + arg);
        }
    }
}
```

Terminal əmri:

```bash
java -jar app.jar --server.port=8080 hello world
# Çıxış: Arqumentlər: [--server.port=8080, hello, world]
```

### ApplicationRunner — Daha strukturlu yanaşma

`ApplicationRunner` arqumentləri daha rahat parse etməyə imkan verir:

```java
@Component
public class DataImportRunner implements ApplicationRunner {

    @Override
    public void run(ApplicationArguments args) throws Exception {
        // --file=data.csv kimi option arqumentləri
        if (args.containsOption("file")) {
            List<String> files = args.getOptionValues("file");
            files.forEach(file -> System.out.println("Fayl import olunur: " + file));
        }

        // Option olmayan arqumentlər
        List<String> nonOptionArgs = args.getNonOptionArgs();
        System.out.println("Digər arqumentlər: " + nonOptionArgs);

        // Bütün source arqumentlər
        String[] sourceArgs = args.getSourceArgs();
        System.out.println("Bütün arqumentlər: " + Arrays.toString(sourceArgs));
    }
}
```

```bash
java -jar app.jar --file=users.csv --file=products.csv import-all
# Çıxış:
# Fayl import olunur: users.csv
# Fayl import olunur: products.csv
# Digər arqumentlər: [import-all]
```

### Çoxlu Runner-lərin sıralanması

```java
@Component
@Order(1)
public class DatabaseCheckRunner implements CommandLineRunner {
    @Override
    public void run(String... args) {
        System.out.println("1. Verilənlər bazası yoxlanılır...");
    }
}

@Component
@Order(2)
public class CacheWarmupRunner implements CommandLineRunner {
    @Override
    public void run(String... args) {
        System.out.println("2. Cache isidilir...");
    }
}

@Component
@Order(3)
public class NotificationRunner implements CommandLineRunner {
    @Override
    public void run(String... args) {
        System.out.println("3. Admin-ə bildiriş göndərilir...");
    }
}
```

### Spring Shell — Tam CLI tətbiqi

Spring Shell ilə interaktiv terminal tətbiqləri yaratmaq mümkündür. Bu, ayrıca dependency tələb edir:

```xml
<dependency>
    <groupId>org.springframework.shell</groupId>
    <artifactId>spring-shell-starter</artifactId>
</dependency>
```

```java
@ShellComponent
public class UserCommands {

    private final UserService userService;

    public UserCommands(UserService userService) {
        this.userService = userService;
    }

    @ShellMethod(value = "Bütün istifadəçiləri göstər", key = "user-list")
    public String listUsers() {
        List<User> users = userService.findAll();
        StringBuilder sb = new StringBuilder();
        sb.append(String.format("%-5s %-20s %-30s%n", "ID", "Ad", "Email"));
        sb.append("-".repeat(55)).append("\n");
        for (User user : users) {
            sb.append(String.format("%-5d %-20s %-30s%n",
                user.getId(), user.getName(), user.getEmail()));
        }
        return sb.toString();
    }

    @ShellMethod(value = "Yeni istifadəçi yarat", key = "user-create")
    public String createUser(
            @ShellOption(value = "--name", help = "İstifadəçi adı") String name,
            @ShellOption(value = "--email", help = "Email ünvanı") String email,
            @ShellOption(value = "--role", defaultValue = "USER", help = "Rol") String role) {
        
        User user = new User(name, email, role);
        userService.save(user);
        return "İstifadəçi yaradıldı: " + user.getName() + " (ID: " + user.getId() + ")";
    }

    @ShellMethod(value = "İstifadəçi sil", key = "user-delete")
    public String deleteUser(@ShellOption(value = "--id") Long id) {
        userService.deleteById(id);
        return "İstifadəçi silindi: ID " + id;
    }
}
```

Spring Shell-də interaktiv terminal:

```
shell:> user-list
ID    Ad                   Email
-------------------------------------------------------
1     Orxan                orxan@test.com
2     Aynur                aynur@test.com

shell:> user-create --name "Elvin" --email "elvin@test.com" --role ADMIN
İstifadəçi yaradıldı: Elvin (ID: 3)

shell:> user-delete --id 3
İstifadəçi silindi: ID 3
```

### Spring Shell-də əmr mövcudluğu (Availability)

```java
@ShellComponent
public class AdminCommands {

    private boolean authenticated = false;

    @ShellMethod("Admin girişi")
    public String login(@ShellOption String password) {
        if ("secret123".equals(password)) {
            authenticated = true;
            return "Giriş uğurlu!";
        }
        return "Yanlış şifrə!";
    }

    @ShellMethod("Verilənlər bazasını sıfırla")
    public String resetDatabase() {
        return "Verilənlər bazası sıfırlandı!";
    }

    // resetDatabase əmri yalnız giriş edildikdə mövcuddur
    public Availability resetDatabaseAvailability() {
        return authenticated
            ? Availability.available()
            : Availability.unavailable("Əvvəlcə giriş edin (login əmri)");
    }
}
```

---

## Laravel-də istifadəsi

### Artisan — Laravel-in CLI sistemi

Laravel-in daxili CLI sistemi Artisan adlanır. Yeni əmr yaratmaq:

```bash
php artisan make:command ImportUsers
```

Bu, `app/Console/Commands/ImportUsers.php` faylı yaradır:

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Services\CsvImportService;

class ImportUsers extends Command
{
    // Əmrin adı və parametrləri
    protected $signature = 'users:import 
                            {file : CSV faylının yolu}
                            {--chunk=100 : Hər dəfə neçə sətir oxunsun}
                            {--skip-header : Başlıq sətirini keç}
                            {--dry-run : Əslində import etmə, yalnız göstər}';

    protected $description = 'CSV faylından istifadəçiləri import et';

    public function __construct(
        private CsvImportService $importService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $file = $this->argument('file');
        $chunkSize = (int) $this->option('chunk');
        $skipHeader = $this->option('skip-header');
        $dryRun = $this->option('dry-run');

        if (!file_exists($file)) {
            $this->error("Fayl tapılmadı: {$file}");
            return Command::FAILURE;
        }

        if ($dryRun) {
            $this->warn('DRY RUN rejimi — dəyişiklik edilməyəcək');
        }

        $this->info("Import başlayır: {$file}");
        
        // Progress bar
        $lines = count(file($file));
        $bar = $this->output->createProgressBar($lines);
        $bar->start();

        $imported = 0;
        $skipped = 0;

        $this->importService->import($file, $chunkSize, $skipHeader, 
            function ($row) use (&$imported, &$skipped, $dryRun, $bar) {
                if (!$dryRun) {
                    User::create([
                        'name' => $row['name'],
                        'email' => $row['email'],
                    ]);
                    $imported++;
                }
                $bar->advance();
            }
        );

        $bar->finish();
        $this->newLine(2);

        // Nəticə cədvəli
        $this->table(
            ['Metrik', 'Dəyər'],
            [
                ['Import edilən', $imported],
                ['Keçilən', $skipped],
                ['Cəmi', $imported + $skipped],
            ]
        );

        $this->info('Import tamamlandı!');
        return Command::SUCCESS;
    }
}
```

İstifadəsi:

```bash
php artisan users:import storage/users.csv --chunk=50 --skip-header
php artisan users:import storage/users.csv --dry-run
```

### Artisan-da İnteraktiv Giriş

```php
class SetupApplication extends Command
{
    protected $signature = 'app:setup';
    protected $description = 'Tətbiqi ilkin quraşdır';

    public function handle(): int
    {
        // Sadə sual
        $name = $this->ask('Tətbiqin adı nədir?');

        // Default dəyərlə sual
        $locale = $this->ask('Dil seçin', 'az');

        // Gizli giriş (şifrə)
        $dbPassword = $this->secret('Verilənlər bazası şifrəsi?');

        // Bəli/Xeyr
        if ($this->confirm('Demo data əlavə edilsin?', true)) {
            $this->call('db:seed', ['--class' => 'DemoSeeder']);
        }

        // Seçim
        $env = $this->choice('Mühit seçin', [
            'local',
            'staging', 
            'production'
        ], 0);

        // Çoxlu seçim
        $features = $this->choice(
            'Hansı xüsusiyyətlər aktiv olsun?',
            ['api', 'admin-panel', 'notifications', 'queue'],
            null,
            null,
            true // multiple
        );

        $this->info("Quraşdırma tamamlandı!");
        $this->info("Ad: {$name}, Mühit: {$env}");
        $this->info("Xüsusiyyətlər: " . implode(', ', $features));

        return Command::SUCCESS;
    }
}
```

### Əmrləri Proqramatik Çağırmaq

```php
// Controller-dən və ya başqa yerdən əmr çağırmaq
use Illuminate\Support\Facades\Artisan;

// Sadə çağırış
Artisan::call('users:import', [
    'file' => 'storage/users.csv',
    '--chunk' => 50,
    '--skip-header' => true,
]);

// Çıxışı almaq
$output = Artisan::output();

// Bir əmrdən digərini çağırmaq
class DeployCommand extends Command
{
    protected $signature = 'app:deploy';

    public function handle(): int
    {
        $this->info('Deploy başlayır...');

        // Digər əmrləri ardıcıl çağır
        $this->call('migrate', ['--force' => true]);
        $this->call('cache:clear');
        $this->call('config:cache');
        $this->call('route:cache');
        $this->call('view:cache');

        // Çıxışı gizlədərək çağırmaq
        $this->callSilently('queue:restart');

        $this->info('Deploy tamamlandı!');
        return Command::SUCCESS;
    }
}
```

### Əmrlərin Planlanması (Scheduling)

```php
// app/Console/Kernel.php
class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // Hər gün gecə 2-də
        $schedule->command('users:cleanup --days=30')
                 ->dailyAt('02:00')
                 ->onOneServer()
                 ->emailOutputOnFailure('admin@example.com');

        // Hər saatda
        $schedule->command('reports:generate')
                 ->hourly()
                 ->withoutOverlapping()
                 ->appendOutputTo(storage_path('logs/reports.log'));

        // Hər 5 dəqiqədə
        $schedule->command('queue:monitor redis:default --max=100')
                 ->everyFiveMinutes();

        // Bazar ertəsi hər həftə
        $schedule->command('analytics:weekly-report')
                 ->weeklyOn(1, '09:00')
                 ->timezone('Asia/Baku');

        // Closure da planlamaq olar
        $schedule->call(function () {
            DB::table('sessions')
                ->where('last_activity', '<', now()->subHours(24))
                ->delete();
        })->daily();
    }
}
```

Serverdə cron:

```
* * * * * cd /var/www/app && php artisan schedule:run >> /dev/null 2>&1
```

### Artisan Tinker — İnteraktiv REPL

Tinker, Laravel tətbiqinin daxilində interaktiv PHP sessiyası açmağa imkan verir:

```bash
php artisan tinker
```

```php
>>> User::count()
=> 150

>>> $user = User::factory()->create(['name' => 'Test User'])
=> App\Models\User {#1234
     name: "Test User",
     email: "test@example.com",
     ...
   }

>>> $user->posts()->count()
=> 0

>>> User::where('role', 'admin')->pluck('email')
=> Illuminate\Support\Collection {#5678
     all: [
       "admin1@example.com",
       "admin2@example.com",
     ],
   }

>>> Cache::put('key', 'value', 3600)
=> true

>>> event(new App\Events\UserRegistered($user))
=> null
```

### Əmrlərin Siyahısı və Kömək

```bash
# Bütün əmrlər
php artisan list

# Əmr haqqında kömək
php artisan help users:import

# Filtrləmə
php artisan list --raw | grep user
```

---

## Əsas fərqlər

| Xüsusiyyət | Spring | Laravel |
|---|---|---|
| CLI sistemi | CommandLineRunner, ApplicationRunner, Spring Shell (ayrıca) | Artisan (daxili, vahid sistem) |
| Əmr yaratma | Java sinfi yazırsınız, əl ilə | `make:command` ilə avtomatik scaffold |
| Arqument parsing | Əl ilə və ya Spring Shell-də annotasiyalarla | `$signature` ilə deklarativ |
| İnteraktiv giriş | Spring Shell-də mövcud | `ask()`, `confirm()`, `choice()` daxili |
| Progress bar | Xüsusi kitabxana lazım | Daxili `createProgressBar()` |
| Əmr planlaması | Spring-in `@Scheduled` annotasiyası (cron deyil) | Artisan Scheduler (eloquent cron) |
| REPL | JShell (JDK-da, Spring-ə bağlı deyil) | Artisan Tinker (tam Laravel konteksti) |
| Proqramatik çağırış | Runner sadəcə başlanğıcda işləyir | `Artisan::call()` ilə hər yerdən |
| Scaffold əmrləri | Yoxdur (IDE plugin-ləri var) | 50+ daxili `make:*` əmri |

---

## Niyə belə fərqlər var?

**Laravel CLI-yə əsaslanan framework-dür.** PHP əsasən request-response dövrü ilə işləyir — hər HTTP sorğusu ayrıca proses kimi başlayır və bitir. Bu səbəbdən, verilənlər bazası miqrasiyaları, cache təmizlənməsi, kod generasiyası kimi əməliyyatlar üçün CLI vasitəsi vacibdir. Artisan, Laravel-in "ikinci yarısıdır" — development zamanı demək olar ki, hər şey terminal vasitəsilə edilir.

**Spring isə uzunömürlü tətbiq kimi işləyir.** JVM başlayır, tətbiq yaddaşa yüklənir və saatlarla, günlərlə işləyir. Buna görə "başlanğıcda bir dəfə işləyən kod" üçün `CommandLineRunner` kifayətdir. İnteraktiv CLI lazımdırsa, Spring Shell ayrıca layihə olaraq mövcuddur, amma əksər Spring tətbiqləri web server kimi işləyir, interaktiv terminal kimi yox.

**Artisan Scheduler vs @Scheduled:** Laravel-də hər HTTP sorğusu ayrı proses olduğu üçün, planlanmış tapşırıqlar üçün OS-un cron sistemi istifadə olunur — Laravel bunu öz scheduler-i ilə sarmalayır. Spring-də isə tətbiq artıq daim işlədiyi üçün `@Scheduled` annotasiyası ilə tətbiqin daxilində planlaşdırma mümkündür.

---

## Hansı framework-də var, hansında yoxdur?

**Yalnız Laravel-də:**
- `make:*` əmrləri ilə kod generasiyası (model, controller, migration və s.)
- Artisan Tinker — framework kontekstində REPL
- Daxili progress bar və cədvəl formatlaması
- `$signature` ilə deklarativ arqument/option təyini
- `Artisan::call()` ilə istənilən yerdən əmr çağırma
- Əmrlərin planlaşdırılması üçün fluent API

**Yalnız Spring-də:**
- Spring Shell ilə tam interaktiv shell tətbiqləri (tab completion, kömək sistemi)
- `@ShellMethod` ilə avtomatik kömək generasiyası
- Əmr mövcudluğu (Availability) sistemi — şərtlərə görə əmrin aktiv/deaktiv olması
- `@Scheduled` ilə tətbiq daxili planlaşdırma (ayrıca cron lazım deyil)
- Picocli inteqrasiyası ilə mürəkkəb CLI tətbiqləri
