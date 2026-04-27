# Hexagonal Architecture (Ports & Adapters) (Senior ⭐⭐⭐)

## İcmal
Hexagonal Architecture (Ports & Adapters) Alistair Cockburn tərəfindən 2005-ci ildə təqdim edilmişdir. Əsas fikir: tətbiqin "ürəyi" (biznes məntiqini) xarici dünyadan izole etmək — istər HTTP request, istər CLI, istər test olsun, tətbiq eyni şəkildə davranmalıdır. Clean Architecture ilə çox oxşardır, lakin fərqli metafora istifadə edir.

## Niyə Vacibdir
Hexagonal Architecture testability-nin fundamentidir. Real database, HTTP server olmadan bütün biznes məntiqini test edə bilmək böyük komandada development sürətini artırır. Framework migration zamanı (Laravel → Symfony) yalnız adapter-ləri dəyişmək lazım gəlir, core logic toxunulmaz qalır. Bu mövzu interviewer-lərə dependency management haqqında düşüncənizi göstərir.

## Əsas Anlayışlar

- **Port**: Interface — tətbiqin xarici dünya ilə danışdığı müqavilə. İki növ: Driving (inbound) və Driven (outbound)
- **Driving Port (Inbound)**: Xarici aləm tətbiqi idarə edir — HTTP Controller, CLI command, test bu port-u çağırır
- **Driven Port (Outbound)**: Tətbiq xarici aləmi idarə edir — Database, email, external API bu port vasitəsilə çağrılır
- **Adapter**: Port-un konkret implementasiyası. `EloquentUserRepository` — `UserRepository` port-unun adapteri
- **Application Core (Hexagon)**: Port-larla əhatə olunmuş biznes məntiqinin məskəni — framework bilmir
- **Primary Adapter**: Driving side — Laravel Controller, Artisan Command, PHPUnit Test
- **Secondary Adapter**: Driven side — EloquentRepository, SmtpMailer, StripePaymentGateway
- **Testability**: Core-u test edərkən real adapter əvəzinə fake/in-memory adapter istifadə etmək
- **Port adlandırması**: Port interface-lər ümumlikdə `UserRepositoryPort`, `PaymentGatewayPort` kimi adlandırılır
- **Clean Architecture fərqi**: Hexagonal daha az layer-lə eyni fikri ifadə edir — inward/outward, daxili/xarici
- **Dependency Injection**: Container port-a müraciət edildikdə düzgün adapter-i inject edir
- **Multiple Adapters**: Eyni port üçün bir neçə adapter ola bilər — `RedisCache` vs `InMemoryCache` eyni port üçün
- **Port granularity**: Çox xırda portlar (hər metod üçün ayrı) lazy abstraction yaradır — balance lazımdır
- **Symmetric architecture**: Sol tərəf (inbound), sağ tərəf (outbound) simmetrikdir — hexagonun iki yarısı

## Praktik Baxış

**Interview-da necə yanaşmaq:**
Clean Architecture ilə Hexagonal Architecture-ın fərqini soruşanda "eyni ideya, fərqli metafora" demək düzgün amma az cavabdır. Daha dərin: Hexagonal binary ayrım edir (inside/outside), Clean Architecture layer-based. Hər ikisi DIP-ə əsaslanır.

**Follow-up suallar:**
- "Driving və Driven port-lar arasındakı fərqi izah edin"
- "Fake adapter ilə real adapter arasındakı fərq nədir?"
- "Hexagonal Architecture-ı ne vaxt istifadə etməmək lazımdır?"
- "Service Locator pattern Hexagonal ilə uyğun gəlirmi?"

**Ümumi səhvlər:**
- Port = Repository kimi düşünmək — Port daha geniş konseptdir (Email, Payment, Storage da port ola bilər)
- Adapter-i test etmək əvəzinə integration test əvəzləndirir
- Core-dan framework-ə birbaşa müraciət etmək (port olmadan)
- Port interface-i olmadan repository yazmaq — bu Hexagonal deyil

**Yaxşı cavabı əla cavabdan fərqləndirən:**
Fake adapter yazmağı göstərə bilmək, core-un framework-dən tam müstəqil test edilə bilməsini nümayiş etdirmək əla cavabın əlamətidir.

## Nümunələr

### Tipik Interview Sualı
"Hexagonal Architecture-ı izah edin. Clean Architecture-dan nə ilə fərqlənir?"

### Güclü Cavab
"Hər ikisi eyni problemi həll edir: biznes məntiqini xarici asılılıqlardan izole etmək. Hexagonal-da tətbiqin 'ürəyi' portlarla əhatə olunub. Sol tərəfdə (driving) HTTP, CLI, test tətbiqi idarə edir. Sağ tərəfdə (driven) tətbiq database, email, third-party API ilə danışır — lakin birbaşa yox, port interface-ləri vasitəsilə. Beləliklə core test edərkən real database əvəzinə in-memory adapter istifadə etmək olur. Clean Architecture layer-lər ilə eyni fikri ifadə edir — layer count fərqlidir, amma əsas DIP ideyası eynidir."

### Kod / Konfiqurasiya Nümunəsi

```php
// ============================================================
// APPLICATION CORE (Hexagon) — Framework tamamilə yoxdur
// ============================================================

// DRIVEN PORT (Outbound) — tətbiq bu interface-ə yazır
interface UserRepositoryPort
{
    public function findByEmail(string $email): ?User;
    public function save(User $user): void;
}

interface PasswordHasherPort
{
    public function hash(string $plain): string;
    public function verify(string $plain, string $hashed): bool;
}

interface EventPublisherPort
{
    public function publish(object $event): void;
}

// Domain Entity
class User
{
    private function __construct(
        public readonly UserId $id,
        private string $email,
        private string $hashedPassword
    ) {}

    public static function register(
        string $email,
        string $hashedPassword
    ): self {
        return new self(UserId::generate(), $email, $hashedPassword);
    }

    public function verifyPassword(string $plain, PasswordHasherPort $hasher): bool
    {
        return $hasher->verify($plain, $this->hashedPassword);
    }
}

// DRIVING PORT (Inbound) — xarici aləm bu interface-i çağırır
interface RegisterUserPort
{
    public function register(RegisterUserCommand $command): UserId;
}

interface AuthenticateUserPort
{
    public function authenticate(string $email, string $password): string; // token
}

// Application Service — core məntiq
class UserService implements RegisterUserPort, AuthenticateUserPort
{
    public function __construct(
        private UserRepositoryPort $users,
        private PasswordHasherPort $hasher,
        private EventPublisherPort $events
    ) {}

    public function register(RegisterUserCommand $command): UserId
    {
        if ($this->users->findByEmail($command->email) !== null) {
            throw new \DomainException('Email already registered');
        }

        $user = User::register(
            $command->email,
            $this->hasher->hash($command->password)
        );

        $this->users->save($user);
        $this->events->publish(new UserRegistered($user->id, $command->email));

        return $user->id;
    }

    public function authenticate(string $email, string $password): string
    {
        $user = $this->users->findByEmail($email)
            ?? throw new \DomainException('Invalid credentials');

        if (!$user->verifyPassword($password, $this->hasher)) {
            throw new \DomainException('Invalid credentials');
        }

        return base64_encode($user->id . ':' . time()); // sadələşdirilmiş token
    }
}

// ============================================================
// PRIMARY ADAPTERS (Driving Side) — sol tərəf
// ============================================================

// HTTP Adapter — Laravel Controller
class RegisterController extends Controller
{
    public function __construct(private RegisterUserPort $registerUser) {}

    public function __invoke(RegisterRequest $request): JsonResponse
    {
        $userId = $this->registerUser->register(
            new RegisterUserCommand(
                email: $request->validated('email'),
                password: $request->validated('password')
            )
        );

        return response()->json(['user_id' => (string) $userId], 201);
    }
}

// CLI Adapter — Artisan Command
class RegisterUserCommand extends Command
{
    protected $signature = 'users:register {email} {password}';

    public function __construct(private RegisterUserPort $registerUser) {
        parent::__construct();
    }

    public function handle(): int
    {
        $userId = $this->registerUser->register(
            new \App\Core\Commands\RegisterUserCommand(
                email: $this->argument('email'),
                password: $this->argument('password')
            )
        );

        $this->info("User registered: {$userId}");
        return self::SUCCESS;
    }
}

// ============================================================
// SECONDARY ADAPTERS (Driven Side) — sağ tərəf
// ============================================================

// Real Database Adapter
class EloquentUserRepository implements UserRepositoryPort
{
    public function findByEmail(string $email): ?User
    {
        $model = UserModel::where('email', $email)->first();
        return $model ? $this->toDomain($model) : null;
    }

    public function save(User $user): void
    {
        UserModel::updateOrCreate(
            ['id' => (string) $user->id],
            ['email' => $user->email, 'password' => $user->hashedPassword]
        );
    }
}

// FAKE Adapter — test üçün (framework lazım deyil)
class InMemoryUserRepository implements UserRepositoryPort
{
    private array $users = [];

    public function findByEmail(string $email): ?User
    {
        return collect($this->users)->firstWhere('email', $email);
    }

    public function save(User $user): void
    {
        $this->users[(string) $user->id] = $user;
    }
}

class NullEventPublisher implements EventPublisherPort
{
    public function publish(object $event): void {} // heç nə etmir
}

// UNIT TEST — framework yoxdur, real DB yoxdur
class UserServiceTest extends TestCase
{
    private UserService $service;
    private InMemoryUserRepository $users;

    protected function setUp(): void
    {
        $this->users = new InMemoryUserRepository();
        $this->service = new UserService(
            users: $this->users,
            hasher: new BcryptPasswordHasher(),
            events: new NullEventPublisher()
        );
    }

    public function test_registers_user(): void
    {
        $userId = $this->service->register(
            new RegisterUserCommand('test@example.com', 'secret123')
        );

        $this->assertNotNull($userId);
        $this->assertNotNull($this->users->findByEmail('test@example.com'));
    }

    public function test_rejects_duplicate_email(): void
    {
        $this->service->register(new RegisterUserCommand('test@example.com', 'pass1'));

        $this->expectException(\DomainException::class);
        $this->service->register(new RegisterUserCommand('test@example.com', 'pass2'));
    }
}
```

## Praktik Tapşırıqlar

- Bir service götürün, bütün xarici asılılıqları port-a çevirin
- Fake adapter yazın, service-i Laravel olmadan test edin
- "Port olmayan dependency" nümunəsi tapın — düzəldin
- Driving port ilə Driven port arasındakı fərqi diaqramla izah edin
- Eyni port üçün iki fərqli adapter yazın (Redis vs InMemory cache)

## Əlaqəli Mövzular

- `03-clean-architecture.md` — Eyni ideyanın Clean Architecture versiyası
- `02-domain-driven-design.md` — Domain + Hexagonal birlikdə
- `01-monolith-vs-microservices.md` — Arxitektura konteksti
- `06-cqrs-architecture.md` — CQRS + Ports & Adapters
