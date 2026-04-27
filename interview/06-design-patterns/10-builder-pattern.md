# Builder Pattern (Middle ⭐⭐)

## İcmal

Builder pattern — mürəkkəb object-lərin addım-addım yaradılmasını idarə edən creational pattern-dir. "Separate the construction of a complex object from its representation so that the same construction process can create different representations." Çoxlu optional parametri olan object-lərdə constructor-un uzanıb çirkinləşməsinin qarşısını alır. Laravel-in Query Builder (`DB::table()->where()->orderBy()->get()`), Eloquent fluent interface, test data factories (`UserFactory::new()->unverified()->withRole('admin')->create()`) — bunların hamısı Builder pattern-dir. Interview-larda creational pattern suallarında, ya da "constructor bolluğunu necə idarə edərsiniz?" sualında çıxır.

## Niyə Vacibdir

Builder pattern-in əsas dəyəri — mürəkkəb object yaradılmasını oxunaqlı, step-by-step şəkildə ifadə etməkdir. Telescoping constructor anti-pattern: 8 parametrli constructor, yarısı optional — çağırarkən hansı parametrin nə olduğu bəlli olmur. Builder ilə: `->withEmail('...')->withRole('admin')->withStatus('active')` — hər dəyər aydın. Test data hazırlamaq üçün Builder xüsusən dəyərlidir — Laravel Factory sistemi bunun üzərindədir. Interviewer bu mövzuda yoxlayır: "Immutable object necə yaratmaq olar?" "Test data necə hazırlayırsınız?" "Fluent interface nədir?"

## Əsas Anlayışlar

**Builder pattern komponentləri:**
- **Builder interface**: `setX()`, `setY()`, `build()` metodları
- **Concrete Builder**: Daxilində object-i addım-addım qurur
- **Director** (optional): Builder-i istifadə edərək konkret quruluş alqoritmi tətbiq edir
- **Product**: Nəticədə yaranan mürəkkəb object

**Telescoping Constructor anti-pattern:**
- `new User(email, password, null, null, 'active', null, true, false)` — hansı parameter nədir?
- Builder ilə: `UserBuilder::new()->email('...')->password('...')->active()->build()`

**Fluent Interface (Method Chaining):**
- Hər setter metodu `$this`-i (ya da yeni instance-i) qaytarır — chain yaratmaq üçün
- `$builder->setA(1)->setB(2)->setC(3)->build()`
- Oxunaqlılığı artırır, IDE auto-complete ilə rahat

**Immutable Builder:**
- Setter metodlar `$this`-i deyil, yeni Builder instance-i qaytarır
- Thread-safe, predictable — shared builder race condition yaratmır
- PHP-də: `return new static(...array_merge((array)$this, ['email' => $email]))`

**Director (isteğe bağlı):**
- Director müəyyən "preset" quruluşları encapsulate edir
- `UserDirector::createAdmin(UserBuilder $b): User` — admin üçün lazım olan bütün parametrləri tətbiq edir
- Laravel Factory-nin `->state()` metodu Director ideologiyasıdır

**Builder vs Factory:**
- Factory: Bir addımda tam object yaradır. Hansı concrete type yaradılacağı gizlənir
- Builder: Addım-addım yaradır. Hansı parametrlər seçilir — izlənir
- Hər ikisini birlikdə istifadə etmək mümkündür: Factory Builder qaytarır

**Query Builder analogy:**
- `DB::table('users')->where('active', 1)->orderBy('name')->limit(10)->get()` — Builder-in textbook nümunəsi
- Hər method builder-i qaytarır, `.get()` son mərhələdir — `build()`-un qarşılığı
- Intermediate state-i lazım olduqda saxlamaq mümkündür: `$base = DB::table('users')->where('active', 1);` sonra `$base->where('role', 'admin')->get()`

**Validation Builder-da:**
- `build()` çağrılarkən məcburi sahələri yoxlamaq — incomplete object yaratmamaq
- Məcburi sahə yoxdursa exception atılır

## Praktik Baxış

**Interview-da yanaşma:**
Builder-i telescoping constructor problemi ilə başlayın: "6 parametrli constructor-un 4-ü optional olduqda nə edir?" Sonra Builder həllinə keçin. Laravel Eloquent Factory-sini real dünya nümunəsi kimi istifadə edin.

**"Nə vaxt Builder seçərdiniz?" sualına cavab:**
- Object-in 4+ optional parametri olduqda
- Yaradılış məntiqi mürəkkəb, addımlara bölünə biləndə
- Bir neçə fərqli "variant" quruluş tez-tez lazım olduqda (test, production, staging)
- API response DTO-larını hazırlarken
- Test fixture-larında flexible data hazırlamaq lazım olduqda

**Anti-pattern-lər:**
- Builder-i sadə 2-3 parametrli class üçün istifadə etmək — overkill
- `build()` çağrılmadan incomplete object istifadə etmək — validation atlamaq
- Mutable Builder-i parallel thread-lərdə paylaşmaq — race condition
- Director-ı lüzumsuz əlavə etmək — sadə Builder kifayətdir

**Follow-up suallar:**
- "Builder vs Named Constructor fərqi nədir?" → Named constructor: `User::fromRegistration(email, password)` — single-step, spesifik use-case. Builder: multi-step, flexible combination
- "Laravel Factory-si Builder pattern-dirmi?" → Bəli — `UserFactory::new()->unverified()->admin()->create()` tam Builder + Director-dur
- "Immutable Builder necə test olunur?" → Hər method yeni instance qaytarır — original dəyişmir, snapshot test etmək asandır

## Nümunələr

### Tipik Interview Sualı

"You're building an email notification system. An email has to/cc/bcc recipients, subject, body, attachments, priority, tracking options — but not all fields are required for every email. How would you design the Email object construction?"

### Güclü Cavab

Bu klassik Builder use-case-idir. `Email` object-i immutable olmalıdır — göndərildikdən sonra dəyişməməlidir.

`EmailBuilder` class-ı hər sahə üçün setter metod təqdim edir — hamısı optional, biri məcburi (`to` və `subject`). Hər setter yeni `EmailBuilder` instance-i qaytarır — immutable chain. `build()` lazım olan sahələri yoxlayır, `Email` value object yaradır.

Director kimi: `NotificationEmail::forPasswordReset(User $user): Email` — spesifik email template-ini Builder ilə qurur.

Laravel-in `Mailable` class-ı özü `from()`, `to()`, `cc()`, `attach()` metodları ilə Builder kimi işləyir.

### Kod Nümunəsi

```php
// Immutable Email Value Object
final class Email
{
    private function __construct(
        public readonly array   $to,
        public readonly string  $subject,
        public readonly string  $htmlBody,
        public readonly ?string $textBody,
        public readonly array   $cc,
        public readonly array   $bcc,
        public readonly array   $attachments,
        public readonly string  $priority,
        public readonly bool    $trackOpens,
    ) {}

    // Builder-dan başqa yaratmaq qeyri-mümkün
    public static function builder(): EmailBuilder
    {
        return new EmailBuilder();
    }
}

// Fluent Builder — immutable (hər step yeni instance)
final class EmailBuilder
{
    private array   $to          = [];
    private string  $subject     = '';
    private string  $htmlBody    = '';
    private ?string $textBody    = null;
    private array   $cc          = [];
    private array   $bcc         = [];
    private array   $attachments = [];
    private string  $priority    = 'normal';
    private bool    $trackOpens  = false;

    public function to(string ...$addresses): self
    {
        $clone     = clone $this;
        $clone->to = array_merge($this->to, $addresses);
        return $clone;
    }

    public function subject(string $subject): self
    {
        $clone          = clone $this;
        $clone->subject = $subject;
        return $clone;
    }

    public function htmlBody(string $html): self
    {
        $clone           = clone $this;
        $clone->htmlBody = $html;
        return $clone;
    }

    public function textBody(string $text): self
    {
        $clone           = clone $this;
        $clone->textBody = $text;
        return $clone;
    }

    public function cc(string ...$addresses): self
    {
        $clone     = clone $this;
        $clone->cc = array_merge($this->cc, $addresses);
        return $clone;
    }

    public function bcc(string ...$addresses): self
    {
        $clone      = clone $this;
        $clone->bcc = array_merge($this->bcc, $addresses);
        return $clone;
    }

    public function attach(Attachment $attachment): self
    {
        $clone              = clone $this;
        $clone->attachments = [...$this->attachments, $attachment];
        return $clone;
    }

    public function highPriority(): self
    {
        $clone           = clone $this;
        $clone->priority = 'high';
        return $clone;
    }

    public function withOpenTracking(): self
    {
        $clone             = clone $this;
        $clone->trackOpens = true;
        return $clone;
    }

    public function build(): Email
    {
        // Validation — məcburi sahələr
        if (empty($this->to)) {
            throw new InvalidEmailException('At least one recipient required');
        }
        if (empty($this->subject)) {
            throw new InvalidEmailException('Subject is required');
        }
        if (empty($this->htmlBody)) {
            throw new InvalidEmailException('Email body is required');
        }

        return new Email(
            to:          $this->to,
            subject:     $this->subject,
            htmlBody:    $this->htmlBody,
            textBody:    $this->textBody,
            cc:          $this->cc,
            bcc:         $this->bcc,
            attachments: $this->attachments,
            priority:    $this->priority,
            trackOpens:  $this->trackOpens,
        );
    }
}

// İstifadə — fluent, oxunaqlı
$email = Email::builder()
    ->to('user@example.com', 'user2@example.com')
    ->cc('manager@example.com')
    ->subject('Your order has shipped')
    ->htmlBody('<h1>Order #1234 shipped!</h1><p>Expected delivery: Friday.</p>')
    ->textBody('Order #1234 shipped! Expected delivery: Friday.')
    ->attach(Attachment::fromFile('/tmp/invoice-1234.pdf', 'invoice.pdf'))
    ->highPriority()
    ->withOpenTracking()
    ->build();

// Director — spesifik email növləri üçün preset
class TransactionalEmailDirector
{
    public static function orderShipped(Order $order, User $user): Email
    {
        return Email::builder()
            ->to($user->email)
            ->subject("Order #{$order->number} has shipped")
            ->htmlBody(view('emails.order-shipped', compact('order', 'user'))->render())
            ->textBody("Your order #{$order->number} has shipped.")
            ->highPriority()
            ->withOpenTracking()
            ->build();
    }

    public static function passwordReset(User $user, string $resetUrl): Email
    {
        return Email::builder()
            ->to($user->email)
            ->subject('Reset your password')
            ->htmlBody(view('emails.password-reset', compact('user', 'resetUrl'))->render())
            ->highPriority()
            ->build();
    }
}
```

```php
// Laravel Factory — Builder + Director kombinasiyası
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'              => $this->faker->name(),
            'email'             => $this->faker->unique()->safeEmail(),
            'password'          => bcrypt('password'),
            'status'            => 'active',
            'email_verified_at' => now(),
        ];
    }

    // Director metodları — state-lər
    public function unverified(): static
    {
        return $this->state(['email_verified_at' => null]);
    }

    public function admin(): static
    {
        return $this->state(['role' => 'admin']);
    }

    public function suspended(): static
    {
        return $this->state([
            'status'       => 'suspended',
            'suspended_at' => now(),
        ]);
    }
}

// Test-də istifadə:
$user = User::factory()->unverified()->create();               // Doğrulanmamış
$admin = User::factory()->admin()->create();                   // Admin
$batch = User::factory()->count(10)->suspended()->make();     // 10 bloklanmış user
```

## Praktik Tapşırıqlar

- `QueryBuilder` yazın: `select()`, `where()`, `orderBy()`, `limit()`, `toSql()` — SQL string qurulsun
- `HttpRequestBuilder` yazın: URL, headers, query params, body, auth — immutable chain ilə
- Laravel Factory-yə custom state əlavə edin: `->withSubscription('pro')`, `->fromCountry('AZ')`
- `ReportConfigBuilder` yazın — report parametrlərini (date range, filters, columns, format) fluent interface ilə qurun
- Telescoping constructor-u olan class-ı Builder ilə refactor edin — parametr sayını azaldın, oxunaqlılığı artırın

## Əlaqəli Mövzular

- [Factory Patterns](02-factory-patterns.md) — Builder tez-tez Factory ilə birlikdə istifadə olunur
- [Strategy Pattern](05-strategy-pattern.md) — Builder içindəki konkret quruluş alqoritmi strategy ola bilər
- [SOLID Principles](01-solid-principles.md) — SRP: quruluş məntiqi Builder-də ayrılır
- [Decorator Pattern](08-decorator-pattern.md) — Decorator stack-i Builder ilə qurulur
