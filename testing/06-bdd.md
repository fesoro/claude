# Behavior-Driven Development - BDD (Middle)
## İcmal

BDD (Behavior-Driven Development) proqram təminatının davranışını business language ilə
təsvir edən development metodologiyasıdır. TDD-nin genişləndirilmiş versiyasıdır, amma
texniki dildən çox business dilinə fokuslanır.

BDD-nin əsas ideyası: developer-lər, testçilər və business stakeholder-lər eyni dildə
danışmalıdır. Bu ortaq dil "ubiquitous language" adlanır.

Dan North tərəfindən yaradılıb, TDD-dəki "test" sözünün developer-ləri çaşdırması
probleminə cavab olaraq.

## Niyə Vacibdir

- **Stakeholder kommunikasiyası**: Business tərəf testləri oxuya bilir, tələblər test-ə çevrilir — developer ilə product owner eyni dili danışır
- **Canlı sənəd**: BDD ssenariləri daima aktual spesifikasiya kimi çalışır, ayrı doc-a ehtiyac olmur
- **Regression clarity**: Failing scenario tam olaraq hansı biznes qaydanın pozulduğunu göstərir
- **Onboarding**: Yeni developer Gherkin ssenariləri oxuyaraq sistem davranışını anlayır

## Əsas Anlayışlar

### Gherkin Syntax

BDD ssenariləri Gherkin dilində yazılır:

```gherkin
Feature: User Registration
  As a visitor
  I want to register an account
  So that I can access the application

  Scenario: Successful registration
    Given I am on the registration page
    When I fill in "Name" with "John Doe"
    And I fill in "Email" with "john@example.com"
    And I fill in "Password" with "secret123"
    And I press "Register"
    Then I should see "Welcome, John Doe"
    And I should be logged in

  Scenario: Registration with existing email
    Given a user exists with email "john@example.com"
    And I am on the registration page
    When I fill in "Email" with "john@example.com"
    And I fill in "Password" with "secret123"
    And I press "Register"
    Then I should see "Email already taken"
```

### Given-When-Then

- **Given**: Başlanğıc vəziyyət (precondition)
- **When**: Hadisə/əməliyyat (action)
- **Then**: Gözlənilən nəticə (assertion)
- **And/But**: Əlavə addımlar

### Feature, Scenario, Scenario Outline

```gherkin
Feature: Shopping Cart
  Background:
    Given I am logged in as a customer

  Scenario: Add item to cart
    Given the product "Laptop" costs $999
    When I add "Laptop" to cart
    Then my cart total should be $999

  Scenario Outline: Apply discount codes
    Given my cart total is <total>
    When I apply discount code "<code>"
    Then my cart total should be <discounted>

    Examples:
      | total | code    | discounted |
      | 100   | SAVE10  | 90         |
      | 200   | SAVE20  | 160        |
      | 50    | SAVE10  | 45         |
```

### BDD vs TDD

| Xüsusiyyət | TDD | BDD |
|-------------|-----|-----|
| Dil | Texniki | Business |
| Focus | Code design | Behavior |
| Audience | Developers | Everyone |
| Tool | PHPUnit | Behat/Cucumber |
| Granularity | Method level | Feature level |

## Praktik Baxış

### Best Practices
- Feature file-ları business language-da yazın
- Scenario-ları qısa saxlayın (5-8 addım)
- Background istifadə edərək dublikatı azaldın
- Step definition-ları reusable yazın
- Tag-lar ilə ssenariləri qruplaşdırın (@smoke, @regression)
- Scenario Outline ilə data variation-ları test edin

### Anti-Patterns
- **Technical Gherkin**: "Given I INSERT INTO users..." - business dili olmalıdır
- **Too many steps**: 20 addımlıq ssenari oxunmaz olur
- **UI-coupled steps**: "I click button#submit" - davranışı təsvir edin
- **Missing examples**: Yalnız happy path test etmək
- **Untested steps**: Step definition-da assert olmadan
- **Monolithic contexts**: Bir context class-da hər şey

## Nümunələr

### E-Commerce BDD Ssenariləri

```gherkin
Feature: Order Placement
  As a registered customer
  I want to place orders
  So that I can purchase products

  Background:
    Given the following products exist:
      | name   | price | stock |
      | Laptop | 999   | 10    |
      | Mouse  | 29    | 50    |
      | Book   | 15    | 100   |

  Scenario: Place a simple order
    Given I am logged in as "customer@test.com"
    And my cart contains:
      | product | quantity |
      | Laptop  | 1        |
      | Mouse   | 2        |
    When I proceed to checkout
    And I select "Credit Card" payment
    And I confirm the order
    Then an order should be created with total $1057
    And my cart should be empty
    And I should receive an order confirmation email

  Scenario: Cannot order out-of-stock items
    Given I am logged in as "customer@test.com"
    And the product "Laptop" has 0 stock
    When I try to add "Laptop" to cart
    Then I should see "Laptop is out of stock"
    And my cart should be empty

  Scenario: Apply coupon to order
    Given I am logged in as "customer@test.com"
    And a coupon "SUMMER20" exists with 20% discount
    And my cart contains:
      | product | quantity |
      | Book    | 2        |
    When I apply coupon "SUMMER20"
    Then my cart total should be $24
```

### Authentication BDD

```gherkin
Feature: User Authentication
  Scenario: Successful login
    Given I am on the login page
    When I fill in "Email" with "user@test.com"
    And I fill in "Password" with "correctpassword"
    And I press "Login"
    Then I should be redirected to "/dashboard"
    And I should see "Welcome back"

  Scenario: Failed login with wrong password
    Given a user exists with email "user@test.com"
    And I am on the login page
    When I fill in "Email" with "user@test.com"
    And I fill in "Password" with "wrongpassword"
    And I press "Login"
    Then I should see "Invalid credentials"
    And I should still be on the login page

  Scenario: Account lockout after failed attempts
    Given a user exists with email "user@test.com"
    And I am on the login page
    When I attempt to login 5 times with wrong password
    Then I should see "Account locked. Try again in 15 minutes"
    And the user "user@test.com" should be locked
```

## Praktik Tapşırıqlar

### Behat Quraşdırma

```bash
composer require --dev behat/behat
composer require --dev behat/mink-extension
composer require --dev laracasts/behat-laravel-extension
```

### behat.yml Konfiqurasiya

```yaml
default:
  extensions:
    Laracasts\Behat\ServiceContainer\BehatExtension: ~
    Behat\MinkExtension:
      default_session: laravel
      laravel: ~
  suites:
    default:
      contexts:
        - FeatureContext
        - AuthenticationContext
        - CartContext
      paths:
        - features
```

### Feature File

```gherkin
# features/authentication.feature
Feature: Authentication
  Scenario: User can login
    Given a user exists with email "test@example.com" and password "secret"
    When I login with email "test@example.com" and password "secret"
    Then I should be authenticated
```

### Context Class (Step Definitions)

```php
// features/bootstrap/AuthenticationContext.php
use Behat\Behat\Context\Context;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AuthenticationContext extends TestCase implements Context
{
    use DatabaseTransactions;

    private $response;
    private $user;

    /** @Given a user exists with email :email and password :password */
    public function aUserExistsWithEmailAndPassword(string $email, string $password): void
    {
        $this->user = User::factory()->create([
            'email' => $email,
            'password' => bcrypt($password),
        ]);
    }

    /** @When I login with email :email and password :password */
    public function iLoginWithEmailAndPassword(string $email, string $password): void
    {
        $this->response = $this->post('/login', [
            'email' => $email,
            'password' => $password,
        ]);
    }

    /** @Then I should be authenticated */
    public function iShouldBeAuthenticated(): void
    {
        $this->assertAuthenticated();
    }

    /** @Then I should not be authenticated */
    public function iShouldNotBeAuthenticated(): void
    {
        $this->assertGuest();
    }

    /** @Given I am on the login page */
    public function iAmOnTheLoginPage(): void
    {
        $this->response = $this->get('/login');
        $this->response->assertStatus(200);
    }

    /** @Then I should see :text */
    public function iShouldSee(string $text): void
    {
        $this->response->assertSee($text);
    }

    /** @Then I should be redirected to :url */
    public function iShouldBeRedirectedTo(string $url): void
    {
        $this->response->assertRedirect($url);
    }
}
```

### Shopping Cart Context

```php
class CartContext extends TestCase implements Context
{
    use DatabaseTransactions;

    private $response;
    private $user;
    private $cart;

    /** @Given the following products exist: */
    public function theFollowingProductsExist(TableNode $table): void
    {
        foreach ($table->getHash() as $row) {
            Product::factory()->create([
                'name' => $row['name'],
                'price' => $row['price'],
                'stock' => $row['stock'],
            ]);
        }
    }

    /** @Given I am logged in as :email */
    public function iAmLoggedInAs(string $email): void
    {
        $this->user = User::factory()->create(['email' => $email]);
        $this->actingAs($this->user);
    }

    /** @Given my cart contains: */
    public function myCartContains(TableNode $table): void
    {
        foreach ($table->getHash() as $row) {
            $product = Product::where('name', $row['product'])->first();
            $this->post('/cart/add', [
                'product_id' => $product->id,
                'quantity' => $row['quantity'],
            ]);
        }
    }

    /** @When I apply coupon :code */
    public function iApplyCoupon(string $code): void
    {
        $this->response = $this->post('/cart/coupon', ['code' => $code]);
    }

    /** @Then my cart total should be :amount */
    public function myCartTotalShouldBe(string $amount): void
    {
        $total = (float) str_replace('$', '', $amount);
        $cart = Cart::where('user_id', $this->user->id)->first();
        assertEquals($total, $cart->total);
    }
}
```

### Behat Çalışdırma

```bash
# Bütün feature-ları çalışdır
vendor/bin/behat

# Bir feature
vendor/bin/behat features/authentication.feature

# Tag ilə filter
vendor/bin/behat --tags=@cart

# Format
vendor/bin/behat --format=pretty
vendor/bin/behat --format=progress
```

### Laravel Feature Test ilə BDD Style

Behat istifadə etmədən, Laravel testlərini BDD style-da yazmaq:

```php
class OrderFeatureTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function a_customer_can_place_an_order(): void
    {
        // Given
        $customer = User::factory()->create();
        $product = Product::factory()->create(['price' => 50, 'stock' => 10]);

        // When
        $response = $this->actingAs($customer)->postJson('/api/orders', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ]);

        // Then
        $response->assertCreated();
        $this->assertDatabaseHas('orders', [
            'user_id' => $customer->id,
            'total' => 100,
        ]);
        $this->assertEquals(8, $product->fresh()->stock);
    }

    /** @test */
    public function an_order_cannot_be_placed_for_out_of_stock_items(): void
    {
        // Given
        $customer = User::factory()->create();
        $product = Product::factory()->create(['stock' => 0]);

        // When
        $response = $this->actingAs($customer)->postJson('/api/orders', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        // Then
        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['items.0.product_id']);
    }
}
```

## Ətraflı Qeydlər

**S: BDD ilə TDD arasındakı fərq nədir?**
C: TDD texniki perspektivdən yazılır (unit test), BDD isə business perspektivdən
(behavior). BDD-də testlər Gherkin dilində yazılır ki, non-technical stakeholder-lər
də oxuya bilsin. TDD code design-ə fokuslanır, BDD isə feature behavior-a.

**S: Gherkin syntax-ın əsas elementləri nədir?**
C: Feature (xüsusiyyət təsviri), Scenario (konkret ssenari), Given (precondition),
When (action), Then (expected result), And/But (əlavə addımlar), Background (ümumi
precondition), Scenario Outline (parametrized scenario), Examples (data table).

**S: BDD-nin üstünlükləri nədir?**
C: Stakeholder-lərlə ümumi dil, living documentation, requirement-lərin aydınlaşması,
development ilə business arasında bridge, testlərin readable olması.

**S: Behat nədir?**
C: PHP üçün BDD framework-üdür. Gherkin feature file-larını PHP step definition-larına
bağlayır. Cucumber-in PHP versiyasıdır.

**S: Scenario Outline nə üçün istifadə olunur?**
C: Eyni ssenarini fərqli data ilə çalışdırmaq üçün. Examples table-da müxtəlif
input/output kombinasiyaları verilir. Data-driven testing imkanı yaradır.

## Əlaqəli Mövzular

- [Testing Fundamentals (Junior)](01-testing-fundamentals.md)
- [Test-Driven Development - TDD (Middle)](05-tdd.md)
- [Feature Testing (Junior)](04-feature-testing.md)
- [Test Organization (Middle)](13-test-organization.md)
- [Continuous Testing (Senior)](23-continuous-testing.md)
