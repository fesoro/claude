## CLI — PHPUnit

vendor/bin/phpunit                            — bütün testlər
vendor/bin/phpunit --testdox                  — readable output
vendor/bin/phpunit tests/Unit/UserTest.php    — konkret fayl
vendor/bin/phpunit --filter testCreateUser    — method ad ilə
vendor/bin/phpunit --filter "/User/i"         — regex
vendor/bin/phpunit --testsuite=Unit           — phpunit.xml suite
vendor/bin/phpunit --group=slow               — group annotation
vendor/bin/phpunit --exclude-group=slow
vendor/bin/phpunit --stop-on-failure / -fos
vendor/bin/phpunit --stop-on-error
vendor/bin/phpunit --fail-on-warning
vendor/bin/phpunit --fail-on-risky
vendor/bin/phpunit --fail-on-deprecation
vendor/bin/phpunit --order-by=random          — random order (10+)
vendor/bin/phpunit --order-by=defects         — failed first
vendor/bin/phpunit --process-isolation
vendor/bin/phpunit --debug
vendor/bin/phpunit --colors=always
vendor/bin/phpunit --do-not-cache-result
vendor/bin/phpunit --list-tests
vendor/bin/phpunit --list-suites
vendor/bin/phpunit --list-groups
vendor/bin/phpunit --version
vendor/bin/phpunit --migrate-configuration    — phpunit.xml schema upgrade
vendor/bin/phpunit --generate-configuration

## Coverage (Xdebug / PCOV)

vendor/bin/phpunit --coverage-text             — terminal
vendor/bin/phpunit --coverage-html coverage/   — HTML report
vendor/bin/phpunit --coverage-clover coverage.xml
vendor/bin/phpunit --coverage-xml coverage/
vendor/bin/phpunit --coverage-cobertura coverage.xml
vendor/bin/phpunit --coverage-php coverage.php
vendor/bin/phpunit --path-coverage             — branch coverage
vendor/bin/phpunit --coverage-filter=src/      — sadəcə src/
vendor/bin/phpunit --testdox --testdox-html report.html
XDEBUG_MODE=coverage vendor/bin/phpunit ...    — Xdebug üçün lazımdır

## phpunit.xml fragment

<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/11.5/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true"
         cacheResultFile=".phpunit.cache/test-results">
  <testsuites>
    <testsuite name="Unit">     <directory>tests/Unit</directory></testsuite>
    <testsuite name="Feature">  <directory>tests/Feature</directory></testsuite>
  </testsuites>
  <source><include><directory>src</directory></include></source>
  <coverage><report>
    <html outputDirectory="coverage"/>
    <clover outputFile="coverage.xml"/>
  </report></coverage>
  <php>
    <env name="APP_ENV" value="testing"/>
  </php>
</phpunit>

## PHPUnit attributes (10+ / 11+ — annotations deprecated)

#[Test]                              — testXxx prefix əvəzinə
#[TestDox('Creates a user')]
#[Group('slow')]                     — @group əvəzi
#[DataProvider('userProvider')]      — data-driven
#[DataProviderExternal(MyData::class, 'methodName')]
#[Depends('testCreate')]
#[CoversClass(User::class)]          — coverage scope (class-level)
#[CoversMethod(User::class, 'save')]
#[UsesClass(Helper::class)]
#[BackupGlobals(true)]
#[BackupStaticProperties(true)]
#[PreserveGlobalState(false)]
#[RunInSeparateProcess]
#[RunTestsInSeparateProcesses]
#[RequiresPhp('>= 8.2')]
#[RequiresPhpExtension('redis')]
#[RequiresOperatingSystem('Linux')]
#[Before] / #[After] / #[BeforeClass] / #[AfterClass]
#[Ticket('JIRA-123')]

## PHPUnit assertions

assertTrue($cond, 'msg')                 assertFalse
assertNull                               assertNotNull
assertEquals($expected, $actual)         — loose
assertSame                                — strict ===
assertNotEquals / assertNotSame
assertEqualsWithDelta($e, $a, 0.0001)    — float
assertEqualsCanonicalizing                — sort then compare
assertEqualsIgnoringCase

assertEmpty / assertNotEmpty
assertCount(3, $arr)
assertContains('x', $arr)
assertNotContains
assertContainsOnly('int', $arr)
assertContainsOnlyInstancesOf(User::class, $list)
assertContainsEquals($obj, $list)         — uses == on objects
assertArrayHasKey('id', $arr)
assertArrayNotHasKey

assertInstanceOf(User::class, $obj)
assertNotInstanceOf
assertObjectHasProperty('name', $obj)     — 11+
assertIsArray / assertIsString / assertIsInt / assertIsFloat / assertIsBool / assertIsCallable / assertIsObject / assertIsIterable / assertIsNumeric

assertGreaterThan / assertLessThan
assertGreaterThanOrEqual / assertLessThanOrEqual

assertStringContainsString
assertStringStartsWith / assertStringEndsWith
assertStringMatchesFormat('id=%d', $s)    — sprintf-style %d %s %a %f
assertMatchesRegularExpression('/x/', $s)

assertJsonStringEqualsJsonString
assertJsonFileEqualsJsonFile
assertXmlStringEqualsXmlString

assertFileExists / assertFileDoesNotExist
assertDirectoryExists / assertIsReadable / assertIsWritable
assertFileEqualsCanonicalizing

$this->expectException(InvalidArgumentException::class);
$this->expectExceptionMessage('not found');
$this->expectExceptionMessageMatches('/not/');
$this->expectExceptionCode(404);

$this->expectOutputString('hello');
$this->expectOutputRegex('/h/');

$this->markTestSkipped('reason');
$this->markTestIncomplete('TODO');
$this->fail('should not reach');

## Lifecycle

protected function setUp(): void { ... }       — hər test
protected function tearDown(): void { ... }
public static function setUpBeforeClass(): void
public static function tearDownAfterClass(): void
#[Before] / #[After] (multiple, ordered) — modern alternative

## Data providers

public static function userProvider(): array
{
    return [
        'admin'  => ['admin@x.com', 'admin'],
        'guest'  => ['guest@x.com', 'guest'],
    ];
}

#[Test]
#[DataProvider('userProvider')]
public function it_validates_role(string $email, string $role): void { ... }

# Generator providers (memory-friendly)
public static function bigProvider(): \Generator
{
    foreach ($rows as $row) yield $row['name'] => [$row['id'], $row['email']];
}

## Mocks / stubs (PHPUnit native)

$mock = $this->createMock(UserRepo::class);
$mock = $this->createPartialMock(UserRepo::class, ['findById']);
$mock = $this->createStub(Logger::class);                 — yalnız return values
$mock = $this->createConfiguredMock(Logger::class, ['log' => true]);

$mock->expects($this->once())
     ->method('findById')
     ->with($this->equalTo(1))
     ->willReturn($user);

$mock->method('save')->willReturnSelf();
$mock->method('list')->willReturnOnConsecutiveCalls([$a], [$b]);
$mock->method('compute')->willReturnCallback(fn($x) => $x * 2);
$mock->method('boom')->willThrowException(new \RuntimeException);

# Argument matchers
$this->equalTo($v)
$this->identicalTo($v)
$this->anything()
$this->isInstanceOf(X::class)
$this->callback(fn($arg) => $arg->id === 1)
$this->arrayHasKey('id')

# Invocation count
$this->never() / once() / atLeastOnce() / atLeast(N) / atMost(N) / exactly(N)

## Mockery (alternative — Laravel community favourite)

use Mockery as m;
$mock = m::mock(UserRepo::class);
$mock->shouldReceive('findById')->once()->with(1)->andReturn($user);
$mock->shouldReceive('save')->andReturnSelf();
$mock->shouldNotReceive('delete');

$spy = m::spy(Logger::class);
$spy->shouldHaveReceived('log')->with('hello');
$spy->shouldHaveBeenCalled();

m::close();      // tearDown(): cleanup verifications

## Pest CLI

vendor/bin/pest                                — run all
vendor/bin/pest tests/Feature/UserTest.php
vendor/bin/pest --filter "creates a user"
vendor/bin/pest --group=slow
vendor/bin/pest --parallel                     — paralel (recommended for big suites)
vendor/bin/pest -p / --processes=4
vendor/bin/pest --dirty                         — yalnız git-dəki dəyişən fayllar
vendor/bin/pest --bail                          — ilk fail-də dayan
vendor/bin/pest --retry                         — failed testləri yenidən
vendor/bin/pest --coverage --min=80
vendor/bin/pest --coverage-html=coverage/
vendor/bin/pest --type-coverage
vendor/bin/pest --mutate                        — mutation testing (Pest 3+)
vendor/bin/pest --watch                         — file watch mode (plugin)
vendor/bin/pest --profile                       — slowest tests
vendor/bin/pest --init                          — Pest setup

## Pest syntax

test('it works', function () {
    expect(1 + 1)->toBe(2);
});

it('creates a user', function () {
    $user = User::factory()->create();
    expect($user->id)->toBeInt();
});

# Higher-order
test('user has name')
    ->expect(fn () => User::factory()->create())
    ->name->toBeString();

# Hooks
beforeEach(fn () => $this->user = User::factory()->create());
afterEach(fn () => DB::rollBack());
beforeAll(fn () => Schema::create(...));
afterAll(fn () => ...);

# Datasets
it('validates email', function (string $email, bool $valid) {
    expect(EmailValidator::isValid($email))->toBe($valid);
})->with([
    ['valid@x.com', true],
    ['invalid',     false],
]);

# Named datasets
dataset('emails', [
    'valid'   => ['valid@x.com', true],
    'invalid' => ['no-at',       false],
]);
it('checks email', fn ($e, $v) => expect(...)->toBe($v))->with('emails');

# Skipping / todo / depends
it('does X')->skip('flaky');
it('does Y')->todo();
it('depends on Z')->depends('it does X');

# Exceptions
it('throws')->throws(InvalidArgumentException::class, 'message');
it('throws on bad input', fn () => doSomething(null))
    ->throws(\TypeError::class);

# Architecture testing (Pest plugin)
arch()->expect('App\Models')->toExtend(Illuminate\Database\Eloquent\Model::class);
arch()->expect('App')->not->toUse(['die','dd','dump','var_dump']);
arch()->preset()->php();
arch()->preset()->security();
arch()->preset()->laravel();

## Pest expectations (expect API)

expect($v)
  ->toBe($x)                          ===
  ->toEqual($x)                       ==
  ->toBeTrue / toBeFalse / toBeNull / toBeEmpty / toBeNumeric
  ->toBeInt / toBeString / toBeArray / toBeBool / toBeFloat / toBeObject / toBeCallable
  ->toBeInstanceOf(User::class)
  ->toBeIn([1,2,3])                   — value in
  ->toContain('x')                    — substring/element
  ->toContainOnlyInstancesOf(User::class)
  ->toHaveCount(3)
  ->toHaveKey('id') / toHaveKeys(['id','name'])
  ->toHaveProperty('name')
  ->toHaveLength(5)
  ->toMatch('/regex/') / toMatchRegex('/x/')
  ->toMatchArray(['id' => 1])         — partial
  ->toMatchObject(['id' => 1])        — like Jest
  ->toBeGreaterThan / toBeLessThan / toBeGreaterThanOrEqual / toBeLessThanOrEqual
  ->toBeBetween($min, $max)
  ->toStartWith / toEndWith
  ->toBeUuid / toBeUrl / toBeJson
  ->toBeReadableFile / toBeWritableFile
  ->not->toBe($x)                     — negation chain
  ->and($other)->toBe(...)            — chain new value
  ->each->toBeInt()                   — apply to each item
  ->sequence(fn ($v) => $v->toBe(1), fn ($v) => $v->toBe(2))   — assert per item

# Custom expectation
expect()->extend('toBeOne', fn () => $this->toBe(1));

## Laravel testing helpers (work in both PHPUnit & Pest)

use Tests\TestCase;        — base class with HTTP, DB helpers

# HTTP
$this->get('/users')->assertOk();
$this->post('/users', $payload)->assertCreated()->assertJson(['name' => 'A']);
$this->put / patch / delete / options
$this->getJson / postJson / putJson / patchJson / deleteJson
$response->assertStatus(201) / assertSuccessful / assertRedirect / assertNotFound
$response->assertSee('text') / assertDontSee
$response->assertSeeText / assertSessionHas / assertSessionHasErrors
$response->assertJson([...]) / assertJsonStructure / assertJsonCount / assertJsonFragment / assertJsonMissing / assertJsonPath('data.0.id', 5)
$response->assertHeader('X-Foo','bar') / assertCookie / assertViewIs / assertViewHas

# Auth
$this->actingAs($user) / actingAs($user, 'api')
$this->actingAsGuest() / Sanctum::actingAs($user, ['ability'])

# DB
use RefreshDatabase;            — trait, transaction rollback per test
use DatabaseTransactions;
use DatabaseMigrations;         — slowest, only if needed

$this->assertDatabaseHas('users',  ['email' => 'a@x.com'])
$this->assertDatabaseMissing('users', [...])
$this->assertDatabaseCount('users', 5)
$this->assertModelExists($user)
$this->assertModelMissing($user)
$this->assertSoftDeleted($user)
$this->assertNotSoftDeleted($user)

# Mail / Notifications / Queue / Bus / Event / HTTP / Storage / Process — Fake facades
Mail::fake();         Mail::assertSent(OrderShipped::class)
Notification::fake(); Notification::assertSentTo($user, InvoicePaid::class)
Queue::fake();        Queue::assertPushed(SendEmailJob::class)
Bus::fake();          Bus::assertDispatched(...)
Event::fake();        Event::assertDispatched(...)
Http::fake([...]);    Http::assertSent(fn ($req) => $req->url() === '...')
Storage::fake('public'); Storage::disk('public')->assertExists('a.png')
Process::fake([...]);
Cache::shouldReceive(...)
Date::setTestNow('2025-01-01');  Carbon::setTestNow(now());  $this->travelTo(now()->addDay());
$this->freezeTime();             $this->travelBack();

# Validation
$response->assertSessionHasErrors(['email']);
$response->assertJsonValidationErrors(['email']);
$response->assertValid();
$response->assertInvalid(['email']);

# Database factories
User::factory()->count(3)->create();
User::factory()->state(['admin' => true])->make();
User::factory()->has(Post::factory()->count(2))->create();
User::factory()->for(Team::factory())->create();
User::factory()->afterCreating(fn ($u) => ...)->create();

# Console
$this->artisan('migrate')->assertOk()->assertSuccessful()
$this->artisan('user:create')
    ->expectsQuestion('Name?', 'Alice')
    ->expectsConfirmation('Are you sure?', 'yes')
    ->expectsOutput('Created!')
    ->assertExitCode(0);

# Browser (Dusk — separate package)
$this->browse(fn (Browser $b) => $b->visit('/')->assertSee('Welcome'));

## Useful Pest plugins

pestphp/pest-plugin-laravel       — first-class Laravel helpers
pestphp/pest-plugin-arch           — architecture rules (built-in core 2+)
pestphp/pest-plugin-faker          — fake() helper
pestphp/pest-plugin-snapshot       — toMatchSnapshot()
pestphp/pest-plugin-watch          — --watch
pestphp/pest-plugin-stressless     — --stress (load test)
pestphp/pest-plugin-type-coverage  — --type-coverage
pestphp/pest-plugin-mutate         — --mutate (Pest 3+)
pestphp/pest-plugin-drift          — PHPUnit → Pest migration

## Mutation testing (Infection / Pest --mutate)

vendor/bin/infection --threads=4 --min-msi=70 --min-covered-msi=80
vendor/bin/pest --mutate --covered-only
# MSI = Mutation Score Indicator

## Common patterns

# Laravel: only-DB transaction (no migrate per test)
use Illuminate\Foundation\Testing\RefreshDatabase;
class FeatureTest extends TestCase { use RefreshDatabase; }

# Override config / env
config(['mail.default' => 'array']);
$this->withoutExceptionHandling();         — show real exception
$this->withoutMiddleware([VerifyCsrfToken::class]);

# Time freeze in test
$this->travelTo(Carbon::create(2025, 1, 1));
$this->travelBack();
$this->freezeTime(fn () => doX());

# Snapshot test
expect($response->json())->toMatchSnapshot();

# Group slow / DB / external tests
#[Group('slow')] / it('does X')->group('slow', 'integration')

## Laravel artisan testing commands

php artisan test                         — wrapper around phpunit/pest
php artisan test --testsuite=Unit
php artisan test --filter=UserTest
php artisan test --parallel
php artisan test --coverage / --coverage --min=80
php artisan test --profile                — slowest tests
php artisan make:test UserTest --pest
php artisan make:test UserTest --unit
php artisan make:test --pest --feature
