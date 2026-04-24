# Spring Shell vs Laravel Artisan — CLI Framework Müqayisəsi

> **Seviyye:** Advanced ⭐⭐⭐

## Giriş

CLI (Command Line Interface) tətbiqləri hər ekosistemin ayrılmaz hissəsidir — admin konsolları, DevOps alətləri, miqrasiya skriptləri, seed data, repl-tipli debug alətləri. Hər iki framework CLI-ı birinci sinif dəstəkləyir, amma fəlsəfələri fərqlidir.

**Spring Shell** — Spring Boot üzərində interaktiv CLI framework-dür. `@ShellComponent`, `@ShellMethod` annotasiyaları ilə komanda yazılır, JLine kitabxanası arxasında interaktiv REPL (prompt) təmin edilir. Tab-completion, command history, styling hamısı hazırdır. DevOps alətləri, operator konsolları üçün istifadə olunur.

**Laravel Artisan** — default olaraq hər Laravel layihəsinə daxildir. Symfony Console üzərində qurulub — `php artisan make:command` ilə yeni komanda yaradılır. `handle()` metodu əsas məntiqdir, `$signature` / `$description` komandanın imzasını müəyyən edir. Laravel 11+ **Laravel Prompts** paketi ilə müasir interaktiv input API-si təqdim edir (`text()`, `select()`, `multiselect()`, `search()`).

Bu sənəd hər iki framework-ün CLI imkanlarını — komanda müəyyənləşdirmə, input/output, formatting, auto-completion, testing, scheduling, security — müqayisə edir.

---

## Spring-də istifadəsi

### 1) Dependency və minimal setup

```xml
<dependency>
    <groupId>org.springframework.shell</groupId>
    <artifactId>spring-shell-starter</artifactId>
    <version>3.3.0</version>
</dependency>
```

```java
@SpringBootApplication
public class ShellApp {
    public static void main(String[] args) {
        SpringApplication.run(ShellApp.class, args);
    }
}
```

Tətbiqi `./mvnw spring-boot:run` ilə işə salsan, interaktiv prompt açılır:

```
shell:>help
shell:>my-command
shell:>exit
```

### 2) `@ShellComponent` və `@ShellMethod`

```java
@ShellComponent
public class UserCommands {

    private final UserService userService;

    public UserCommands(UserService userService) {
        this.userService = userService;
    }

    @ShellMethod(key = "user-create", value = "Yeni user yaradır")
    public String createUser(
        @ShellOption(value = "--email", help = "İstifadəçi e-poçtu") String email,
        @ShellOption(value = "--name", defaultValue = "Unknown") String name,
        @ShellOption(value = "--admin", defaultValue = "false") boolean isAdmin
    ) {
        User u = userService.create(email, name, isAdmin);
        return "User yaradıldı: id=" + u.getId();
    }

    @ShellMethod(key = { "user-list", "ul" }, value = "User siyahısı")
    public void listUsers(
        @ShellOption(defaultValue = "10") int limit
    ) {
        userService.findAll(limit)
            .forEach(u -> System.out.printf("%d\t%s\t%s%n", u.getId(), u.getEmail(), u.getName()));
    }

    @ShellMethod(key = "user-delete", value = "User-i sil")
    public String deleteUser(Long id) {
        userService.delete(id);
        return "Silindi: " + id;
    }
}
```

İstifadə:

```
shell:>user-create --email a@b.com --name Ali --admin
User yaradıldı: id=42

shell:>ul 5
1  a@b.com  Ali
2  ...

shell:>user-delete 42
Silindi: 42
```

### 3) `@ShellMethodAvailability` — şərtli komandalar

```java
@ShellComponent
public class AdminCommands {

    private boolean loggedIn = false;

    @ShellMethod("Login")
    public String login(String password) {
        if ("secret".equals(password)) {
            loggedIn = true;
            return "Xoş gəldin!";
        }
        return "Yanlış password";
    }

    @ShellMethod("Bütün cache-i təmizlə")
    public String flushAll() {
        // ...
        return "Təmizləndi";
    }

    @ShellMethodAvailability("flushAll")
    public Availability flushAllAvailability() {
        return loggedIn
            ? Availability.available()
            : Availability.unavailable("əvvəlcə login ol");
    }
}
```

`flushAll` yalnız login olandan sonra görünür. `help` komandası unavailable-ı göstərir və səbəbini çap edir.

### 4) Command groups, aliases

```java
@ShellComponent
@ShellCommandGroup("User idarəetmə")
public class UserCommands {

    @ShellMethod(key = { "user-create", "uc" }, value = "Yarat")
    public String create(...) { ... }

    @ShellMethod(key = { "user-list", "ul", "users" }, value = "Siyahı")
    public void list() { ... }
}
```

`help` komandası grouplanmış şəkildə göstərir:

```
User idarəetmə
    user-create, uc: Yarat
    user-list, ul, users: Siyahı
```

### 5) Interaktiv prompt — JLine

```java
@ShellComponent
public class SetupCommands {

    private final LineReader reader;

    public SetupCommands(@Lazy LineReader reader) {
        this.reader = reader;
    }

    @ShellMethod("Yeni DB konfiqurasiyası")
    public String setupDb() {
        String host = reader.readLine("DB host [localhost]: ");
        String port = reader.readLine("DB port [5432]: ");
        String pwd = reader.readLine("Parol: ", '*');   // Gizli
        return "Yadda saxlandı: " + host + ":" + port;
    }
}
```

### 6) Styling və progress bar

```java
@ShellComponent
public class MigrationCommands {

    private final Terminal terminal;

    public MigrationCommands(Terminal terminal) {
        this.terminal = terminal;
    }

    @ShellMethod("Migrate")
    public void migrate() {
        PrintWriter w = terminal.writer();
        AttributedString msg = new AttributedStringBuilder()
            .style(AttributedStyle.DEFAULT.foreground(AttributedStyle.GREEN).bold())
            .append("Migrasiya başladı...")
            .toAttributedString();
        w.println(msg.toAnsi(terminal));

        int total = 100;
        for (int i = 1; i <= total; i++) {
            w.printf("\rProgress: %d/%d [%s%s]", i, total,
                "=".repeat(i / 2), " ".repeat(50 - i / 2));
            w.flush();
            try { Thread.sleep(30); } catch (Exception e) {}
        }
        w.println("\nTamamlandı");
    }
}
```

### 7) Tablo çıxarış

```java
import org.springframework.shell.table.*;

@ShellMethod("User cədvəli")
public Table usersTable() {
    List<User> users = userService.findAll(50);
    String[][] data = new String[users.size() + 1][3];
    data[0] = new String[]{ "ID", "Email", "Name" };
    for (int i = 0; i < users.size(); i++) {
        User u = users.get(i);
        data[i + 1] = new String[]{ u.getId().toString(), u.getEmail(), u.getName() };
    }
    TableModel model = new ArrayTableModel(data);
    return new TableBuilder(model)
        .addFullBorder(BorderStyle.fancy_light)
        .build();
}
```

Terminal-da belə görünür:

```
┌────┬──────────┬───────┐
│ ID │ Email    │ Name  │
├────┼──────────┼───────┤
│ 1  │ a@b.com  │ Ali   │
│ 2  │ c@d.com  │ Mehdi │
└────┴──────────┴───────┘
```

### 8) Interactive vs non-interactive mode

```bash
# İnteraktiv — prompt açılır
java -jar admin.jar

# Non-interactive — komanda verilsin və çıxsın (skript üçün)
java -jar admin.jar user-create --email a@b.com --name Ali

# Fayldan komandalar
java -jar admin.jar @commands.txt
```

`application.yml`:

```yaml
spring:
  shell:
    interactive:
      enabled: true
    noninteractive:
      enabled: true
    history:
      name: admin-history.log
    script:
      enabled: true
```

### 9) Spring Security inteqrasiyası

```java
@ShellComponent
public class ProtectedCommands {

    @ShellMethod("Yalnız admin")
    @PreAuthorize("hasRole('ADMIN')")
    public String secret() {
        return "top secret";
    }
}
```

Auth context-i interaktiv rejimdə qurmaq üçün custom login komandası lazımdır (Spring Security + in-memory user detail service).

### 10) Testing

```java
@SpringBootTest
@AutoConfigureShell
class UserCommandsTest {

    @Autowired Shell shell;

    @Test
    void shouldCreateUser() {
        Object result = shell.evaluate(() -> "user-create --email a@b.com --name Ali");
        assertThat(result.toString()).contains("User yaradıldı");
    }

    @Test
    void unavailableWhenNotLoggedIn() {
        CommandNotCurrentlyAvailable r = (CommandNotCurrentlyAvailable) shell.evaluate(() -> "flushAll");
        assertThat(r.getReason()).contains("əvvəlcə login ol");
    }
}
```

---

## Laravel-də istifadəsi

### 1) Komanda yaratmaq

```bash
php artisan make:command UserCreate
```

`app/Console/Commands/UserCreate.php`:

```php
namespace App\Console\Commands;

use Illuminate\Console\Command;

class UserCreate extends Command
{
    protected $signature = 'user:create
                            {email : İstifadəçi e-poçtu}
                            {--name=Unknown : Ad}
                            {--admin : Admin yarat}';

    protected $description = 'Yeni user yaradır';

    public function handle(\App\Services\UserService $users): int
    {
        $email = $this->argument('email');
        $name = $this->option('name');
        $isAdmin = $this->option('admin');

        $user = $users->create($email, $name, $isAdmin);
        $this->info("User yaradıldı: id={$user->id}");
        return self::SUCCESS;
    }
}
```

İstifadə:

```bash
php artisan user:create a@b.com --name=Ali --admin
```

Laravel 11+ `app/Console/Kernel.php` yoxdur — komandalar avtomatik `app/Console/Commands/` altından yüklənir. Əl ilə qeydiyyat `bootstrap/app.php`-də:

```php
->withCommands([
    __DIR__.'/../app/Console/Commands',
])
```

### 2) `$signature` DSL — argumentlər və seçimlər

```php
protected $signature = 'report:generate
                        {type : Hesabat tipi (daily|weekly|monthly)}
                        {--since= : Başlanğıc tarix}
                        {--until= : Son tarix}
                        {--email=* : Alıcılar (çoxlu)}
                        {--pdf : PDF kimi export et}
                        {--force : Təsdiq istəmə}';
```

- `{arg}` — məcburi argument
- `{arg?}` — optional
- `{arg=default}` — default dəyər
- `{arg*}` — array (bir neçə dəyər)
- `{--opt}` — bool flag
- `{--opt=}` — dəyər qəbul edən option
- `{--opt=default}` — default ilə
- `{--opt=*}` — array option

### 3) Input — argument, option, ask, confirm, choice

```php
public function handle(): int
{
    // Args / options
    $name = $this->argument('name');
    $email = $this->option('email');

    // Interaktiv suallar
    $age = $this->ask('Yaşın?', 25);
    $ok = $this->confirm('Əminsən?', true);
    $color = $this->choice('Rəng seç?', ['qırmızı', 'mavi', 'yaşıl'], 'mavi');
    $password = $this->secret('Parol?');   // Gizli input

    // Çoxsətirli input (editor aç)
    $desc = $this->ask('Description?');

    return self::SUCCESS;
}
```

### 4) Output — info, warn, error, line, newLine

```php
$this->info('Uğurlu əməliyyat');      // Yaşıl
$this->warn('Diqqət: cache boşdur');  // Sarı
$this->error('Xəta!');                // Qırmızı
$this->line('Neytral sətir');
$this->comment('Boz comment');
$this->newLine();                      // Boş sətir
$this->newLine(3);                     // 3 boş sətir
```

### 5) Tablo və progress bar

```php
$users = User::limit(10)->get();

$this->table(
    ['ID', 'Email', 'Name'],
    $users->map(fn($u) => [$u->id, $u->email, $u->name])->toArray()
);
```

Çıxış:

```
+----+---------+-------+
| ID | Email   | Name  |
+----+---------+-------+
| 1  | a@b.com | Ali   |
+----+---------+-------+
```

Progress bar:

```php
$users = User::lazy();
$bar = $this->output->createProgressBar($users->count());
$bar->start();
foreach ($users as $u) {
    $this->processUser($u);
    $bar->advance();
}
$bar->finish();
$this->newLine();
```

Və ya qısa:

```php
User::lazy()->each(function ($u) {
    $this->processUser($u);
});

// withProgressBar helper
$this->withProgressBar(User::all(), function ($u) {
    $this->processUser($u);
});
```

### 6) Laravel Prompts — müasir interaktiv UI (11+)

```bash
composer require laravel/prompts
```

```php
use function Laravel\Prompts\{text, password, select, multiselect, confirm, search, spin, note, info};

public function handle(): int
{
    $name = text(
        label: 'Adın nədir?',
        placeholder: 'Ali',
        required: true,
        validate: fn($v) => strlen($v) < 2 ? 'Çox qısa' : null,
    );

    $password = password(label: 'Parol?', validate: fn($v) => strlen($v) < 8 ? 'min 8 simvol' : null);

    $role = select(
        label: 'Rol seç',
        options: ['admin' => 'Administrator', 'user' => 'İstifadəçi'],
        default: 'user',
    );

    $permissions = multiselect(
        label: 'İcazələr',
        options: ['read', 'write', 'delete'],
        default: ['read'],
    );

    $ok = confirm(label: "$name-i yaradaq?", default: true);

    $userId = search(
        label: 'Mövcud user axtar',
        options: fn($q) => User::where('email', 'like', "%$q%")->limit(5)->pluck('email', 'id')->all(),
    );

    // Spinner
    $result = spin(
        message: 'Yüklənir...',
        callback: fn() => Http::get('https://api.example.com/slow'),
    );

    note('Qeyd: verilənlər saxlanıldı');
    info('Hamısı hazır');

    return self::SUCCESS;
}
```

Laravel Prompts JLine-a bənzər terminal UI verir — keyboard navigation, highlight, validation mesajı.

### 7) Nunomaduro Termwind — rich HTML-style terminal

```bash
composer require nunomaduro/termwind
```

```php
use function Termwind\{render};

render(<<<'HTML'
<div class="p-1 bg-green-500 text-white">
    <span class="font-bold">OK</span>
    Sorğu uğurludur
</div>
<ul class="mt-1">
    <li>User yaradıldı</li>
    <li>Email göndərildi</li>
</ul>
HTML);
```

Tailwind-bənzər siniflər terminal-da render olunur — rənglər, padding, margin.

### 8) Testing

```php
class UserCreateCommandTest extends TestCase
{
    public function test_creates_user(): void
    {
        $this->artisan('user:create', ['email' => 'a@b.com', '--name' => 'Ali'])
            ->expectsOutput('User yaradıldı: id=1')
            ->assertExitCode(0);
    }

    public function test_interactive(): void
    {
        $this->artisan('setup')
            ->expectsQuestion('DB host', 'localhost')
            ->expectsQuestion('DB port', '5432')
            ->expectsConfirmation('Əminsən?', 'yes')
            ->expectsOutput('Yadda saxlandı')
            ->assertExitCode(0);
    }

    public function test_choice(): void
    {
        $this->artisan('pick')
            ->expectsChoice('Rəng seç', 'mavi', ['qırmızı', 'mavi', 'yaşıl'])
            ->assertExitCode(0);
    }
}
```

### 9) Scheduling — komanda planlaşdırma

```php
// routes/console.php (Laravel 11+)
use Illuminate\Support\Facades\Schedule;

Schedule::command('report:generate daily')->dailyAt('02:00');
Schedule::command('cache:prune')->everyFifteenMinutes();
Schedule::command('users:cleanup')
    ->daily()
    ->onOneServer()
    ->withoutOverlapping()
    ->runInBackground()
    ->emailOutputTo('devops@co.com');
```

Production-da cron:

```cron
* * * * * cd /var/www && php artisan schedule:run >> /dev/null 2>&1
```

Və ya Laravel 11+-da uzunmüddətli daemon:

```bash
php artisan schedule:work
```

### 10) Laravel Zero — standalone CLI

Əgər web interfeysiz, yalnız CLI tətbiq yaradırsansa:

```bash
composer create-project laravel-zero/laravel-zero my-cli
cd my-cli
php application make:command Greet
./application greet
./application app:build my-cli   # PHAR binar
```

Laravel Zero bütün Artisan, queue, scheduler, logging, DB imkanlarını saxlayır — amma web layer-i yoxdur. DevOps alətləri üçün ideal.

### 11) Symfony Console — Artisan-ın əsası

Laravel Artisan komandaları altda Symfony Console-dan törəyir. Lazım olanda Symfony API-sinə birbaşa qoşulmaq olar:

```php
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

public function handle(InputInterface $input, OutputInterface $output): int
{
    $output->writeln('<fg=green>Hello from Symfony Console</>');
    return 0;
}
```

Laravel `$this->info()` əslində `$this->output->writeln('<info>...</info>')` üçün qısa yoldur.

---

## Əsas fərqlər

| Xüsusiyyət | Spring Shell | Laravel Artisan |
|---|---|---|
| Başlanğıc | Ayrıca starter + `@ShellComponent` | Built-in, hər layihədə var |
| Komanda defn | `@ShellMethod(key="...")` + parametr | `$signature` DSL string |
| Argument parsing | `@ShellOption` annotasiya | `{arg}` / `{--opt}` DSL |
| Interaktiv prompt | JLine `LineReader.readLine()` | `$this->ask()` / Laravel Prompts |
| Rich UI | AttributedString, Table API | Laravel Prompts, Termwind |
| Progress bar | Manual `\r` + print | `withProgressBar()` hazır |
| Availability | `@ShellMethodAvailability` | Manual `if (!$cond) return` |
| Auto-completion | Hazır (JLine) | Hazır (Symfony) + custom |
| Interaktiv REPL | Default interaktivdir | `php artisan tinker` ayrıca |
| Non-interactive | Hazır | Default rejimi |
| Scheduling | Spring Scheduler ayrıca | `routes/console.php` built-in |
| Testing | `shell.evaluate()` | `$this->artisan()->expects...` |
| Package support | Spring Boot app daxilində | Laravel Zero (standalone) |
| Security | `@PreAuthorize` işləyir | Middleware yoxdur, manual check |
| History/config | `application.yml` | config yoxdur, auto |

---

## Niyə belə fərqlər var?

**Spring Shell niş alətdir.** Spring layihələrinin əksəriyyəti HTTP API və ya batch job-dur — CLI lazım olduqda Spring Shell əlavə edilir. Bu səbəbdən Spring Shell ayrıca starter-dir, default gəlmir. Əvəzinə Spring CLI (`spring run`) ilə prototipləmə və Spring Batch ilə job-run mövcuddur.

**Artisan Laravel-in ürəyidir.** `php artisan make:*`, `php artisan migrate`, `php artisan tinker`, `php artisan serve` — developer hər gün işlədir. Buna görə Artisan framework-ə sıx inteqrasiya olunub və hər Laravel paket öz komandasını əlavə edə bilir (`composer require ...` edəndə yeni `artisan` komandaları peyda olur).

**JLine vs Symfony Console.** Spring Shell JLine istifadə edir — REPL, tab-completion, history birinci sinif. Laravel Symfony Console üzərindədir — command-oriented, interaktiv rejim ikinci dərəcəlidir. Laravel Prompts (11+) bu fərqi azaltdı — indi Artisan-da da Laravel Prompts ilə müasir UX mümkündür.

**Scheduling yeri.** Spring-də `@Scheduled` annotasiyası istənilən bean-da ola bilər — Spring Shell-lə əlaqəsi yoxdur. Laravel-də scheduling Artisan komandalarına sıx bağlıdır (`Schedule::command('x:y')`). Bu Laravel-in "batteries included" fəlsəfəsi ilə uyğundur.

**Laravel Zero faktoru.** PHP-də tək fayl PHAR kimi paketləmə asandır — buna görə Laravel Zero standalone CLI tətbiqlər üçün praktiki seçimə çevrildi. Spring-də `native-image` (GraalVM) var, lakin build mürəkkəbdir; əvəzinə `fat JAR` paketləmə ümumi yoldur.

**Security modeli.** Spring Shell `@PreAuthorize` ilə Spring Security context-inə qoşulur — JAAS, authentication provider ilə tam işləyir. Laravel-də `Auth::loginUsingId()` mümkündür, amma Artisan komandaları çox vaxt CLI-də root icra olunur — guard yoxdur. "Security by environment" fəlsəfəsi — artisan yalnız prod server-ə SSH ilə çatan admin işlədir.

---

## Hansı framework-də var, hansında yoxdur?

**Yalnız Spring Shell-də:**
- JLine-based REPL — rich interactive terminal əvvəldən
- `@ShellMethodAvailability` — şərtli komanda görünmə
- `Table API` — `TableBuilder` + border style
- `AttributedString` styling — hazır ANSI helper
- `@ShellCommandGroup` — help-də qruplaşdırma
- Script mode — komanda faylı icra etmək (`@file.txt`)
- Spring Security inteqrasiyası — `@PreAuthorize` işləyir
- `application.yml` ilə history/prompt konfiqurasiya

**Yalnız Laravel Artisan-də:**
- `$signature` DSL — argument + option kompakt ifadə
- Laravel Prompts — `text()`, `select()`, `multiselect()`, `search()`, `spin()`
- Termwind — HTML/Tailwind-bənzər terminal render
- `withProgressBar()` — bir sətirlə progress
- `$this->table(...)` — built-in
- Scheduling birinci sinif — `Schedule::command('x')` hazır
- `$this->artisan()` test helper — `expectsOutput/Question/Confirmation/Choice`
- Laravel Zero — tam standalone PHAR CLI
- Hər paket öz Artisan komandalarını avto-register edir
- `tinker` REPL (Laravel-specific, Spring Shell ondan fərqlidir)

---

## Best Practices

1. **Komanda adlarını kateqoriyalaşdır.** Spring-də `@ShellCommandGroup("User")`, Laravel-də `user:create`, `user:delete` — prefix ilə group qur.
2. **Help mətni yaz.** `@ShellMethod(value="...")` və `$description` boş qoyma. `help` komandası ilə istifadəçiyə alətin məqsədini izah edir.
3. **Exit code qaytar.** Laravel: `return self::SUCCESS` (0) / `self::FAILURE` (1). Spring: `System.exit(code)` və ya `ExitCodeGenerator`. CI pipeline üçün vacibdir.
4. **Input-u validate et.** Laravel-də `$this->validate(['arg' => 'required|email'])`. Spring-də `@ShellOption` + manual check.
5. **Destructive komandaya `--force` əlavə et.** `if (!$this->option('force') && !$this->confirm('Əminsən?')) return;`
6. **Uzun iş üçün progress göstər.** Bar, spinner — user buna öyrəşib.
7. **Scheduling-də `withoutOverlapping()` + `onOneServer()`** istifadə et — cluster-də dublikat işləmə.
8. **CLI-də log et.** `Log::info(...)` + `$this->info(...)` paralel yaz — audit üçün fayl log-u lazımdır.
9. **Secret input `$this->secret()` / JLine `readLine('*')`** işlət — parol ekranda görünməsin.
10. **Testing:** hər komanda üçün `artisan()` / `shell.evaluate()` test yaz — output, exit code, interactive fluxunu yoxla.
11. **Container-də non-interactive rejimi seç** — Docker log-u için `-n` / `--no-interaction` flag-ini dəstəklə.
12. **Production-da `ini_set('memory_limit', '512M')`** Laravel-də uzun komandalar üçün qoy; Spring-də JVM `-Xmx` ayarla.
13. **Restart safe:** uzun iş tranzaksiya içində olmasın — chunk-lara böl, hər chunk öz tranzaksiyası.
14. **Laravel Prompts fallback aktiv saxla** — CI / SSH olmayan terminal üçün `PROMPT_FALLBACK=true`.

---

## Yekun

Spring Shell Java ekosistemində peşəkar interaktiv CLI üçün seçimdir — JLine backend ilə rich REPL, `@ShellMethod` annotasiya DSL-i, `@ShellMethodAvailability` şərtli komandalar, Spring Security inteqrasiyası. Lakin ayrıca starter-dir, hər Spring layihəsində default yoxdur.

Laravel Artisan isə hər Laravel tətbiqinin ayrılmaz hissəsidir — `php artisan` komandası hər developer-in gündəlik aləti. `$signature` DSL-i kompaktdır, Laravel Prompts (11+) ilə müasir UX hazırdır, Termwind HTML-style terminal render verir. Scheduling Artisan-a sıx bağlıdır, Laravel Zero ilə standalone CLI qurmaq asandır.

Müsahibədə "Admin konsolu necə qurardın?" sualı gəlsə — Spring Shell-də `@ShellComponent` + `@PreAuthorize`, Laravel-də Artisan command + middleware/guard + Laravel Prompts istifadə et. "Scheduling?" — Spring Scheduler / `@Scheduled` (Spring Shell-dən müstəqil), Laravel `Schedule::command(...)` built-in. Testing yanaşmasını göstər (`shell.evaluate` vs `$this->artisan()`) — bu CLI layer-ə ciddi yanaşdığını göstərir.
