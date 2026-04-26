# Browser Testing (Middle)
## İcmal

Browser testing (E2E testing), real brauzerdə istifadəçi davranışını simulyasiya edərək
proqramı test etmək prosesidir. Testlər link-lərə klik etmək, form doldurmaq, səhifələr
arası naviqasiya kimi real istifadəçi hərəkətlərini təkrarlayır.

Bu testlər test pyramid-ının ən üst hissəsindədir - ən yavaş və ən bahalıdır, amma
ən real test növüdür. Frontend, backend, database və bütün infrastructure birlikdə
test edilir. Laravel-də Dusk package-i ilə browser testing həyata keçirilir.

### Niyə Browser Testing Vacibdir?

1. **Real istifadəçi təcrübəsi** - Gerçək brauzerdə gerçək davranışı test edir
2. **JavaScript testing** - JS-in düzgün işlədiyini yoxlayır
3. **Visual verification** - UI elementlərinin görünməsini təmin edir
4. **Integration tam yoxlama** - Bütün stack-i birlikdə test edir
5. **Regression detection** - UI dəyişikliklərinin funksionallığı pozmadığını yoxlayır

## Niyə Vacibdir

- **JavaScript-dependent flow-ların yoxlanması** — Feature test-lər HTTP response-u yoxlayır, amma real brauzerdə JS işləməsə düymə klik olunmur, modal açılmır. Browser testing bu boşluğu bağlayır.
- **Checkout, login, form submission kimi kritik user journey-lər** — E-commerce layihələrdə cart-to-payment flow-u yalnız browser testlə tam yoxlanıla bilər; unit testlər ayrı hissələri test edir.
- **Regression detection** — Frontend dəyişikliklərindən sonra mövcud funksionallığın sınıb-sınmadığını tez aşkar edir; manual QA əvəzinə CI/CD-də avtomatik işləyir.
- **Real-time feature-ların (WebSocket, polling) doğrulanması** — Chat, live notification kimi feature-lar yalnız real brauzer simulyasiyası ilə düzgün test oluna bilər.
- **Multi-browser compatibility** — Fərqli brauzerlərdə eyni testi işlədərək CSS/JS uyğunsuzluqlarını production-a çatmadan aşkarlamaq mümkündür.

## Əsas Anlayışlar

### Browser Testing Alətləri Müqayisəsi

| Xüsusiyyət | Selenium | Cypress | Playwright | Laravel Dusk |
|------------|----------|---------|------------|-------------|
| Dil dəstəyi | Çox dil | JS/TS | JS/TS/Python | PHP |
| Brauzer dəstəyi | Hamısı | Chrome/Firefox/Edge | Hamısı | Chrome |
| Sürət | Yavaş | Orta | Sürətli | Orta |
| Setup | Mürəkkəb | Asan | Asan | Asan (Laravel) |
| Auto-wait | Yox | Bəli | Bəli | Bəli |
| Laravel integration | Zəif | Yox | Yox | Əla |

### Headless vs Headed Browsers

```
Headed Browser:
  → Görünən brauzer pəncərəsi açılır
  → Debug etmək asandır
  → Daha yavaş
  → CI/CD-də istifadəsi çətindir

Headless Browser:
  → UI olmadan işləyir
  → CI/CD üçün idealdır
  → Daha sürətli
  → Screenshot/video ilə debug edilir
```

### Selector Strategiyası

```
Prioritet sırası (yuxarıdan aşağı):

1. data-testid="submit-btn"     ← Ən etibarlı (test üçün xüsusi)
2. [role="button"]               ← Accessibility attribute-ları
3. button[type="submit"]         ← Semantic HTML
4. .submit-button                ← CSS class (dəyişə bilər)
5. #submit                       ← ID (dəyişə bilər)
6. div > form > button:nth(2)   ← DOM structure (çox kövrək)
```

## Praktik Baxış

### Best Practices

1. **data-testid istifadə edin** - CSS class və ID-lərə etibar etməyin, dəyişə bilər
2. **Explicit wait istifadə edin** - `waitFor`, `waitForText` kimi method-lar istifadə edin
3. **Page Object Pattern istifadə edin** - UI interaction-ları bir yerdə idarə edin
4. **Az sayda browser test yazın** - Pyramid qaydasına riayət edin, əsas flow-ları test edin
5. **Hər testi izole edin** - Testlər bir-birindən asılı olmamalıdır
6. **Screenshot/video çəkin** - Failure halında debug üçün vizual evidence saxlayın

### Anti-Patterns

1. **sleep/pause istifadə etmək** - Explicit wait istifadə edin
2. **Hər şeyi browser testlə test etmək** - Unit/feature testlər daha sürətlidir
3. **Mürəkkəb CSS selector-lar** - `div.parent > ul > li:nth-child(3)` kövrəkdir
4. **Test data-sını UI-dan yaratmaq** - Database-dən birbaşa yaradın
5. **Testlər arası sıra asılılığı** - Hər test müstəqil olmalıdır
6. **Production URL-lərə test yazmaq** - Həmişə test environment istifadə edin

## Nümunələr

### Cypress Nümunəsi

```javascript
// cypress/e2e/login.cy.js
describe('Login Page', () => {
    beforeEach(() => {
        cy.visit('/login');
    });

    it('shows login form', () => {
        cy.get('[data-testid="email-input"]').should('be.visible');
        cy.get('[data-testid="password-input"]').should('be.visible');
        cy.get('[data-testid="login-button"]').should('be.visible');
    });

    it('logs in with valid credentials', () => {
        cy.get('[data-testid="email-input"]').type('user@example.com');
        cy.get('[data-testid="password-input"]').type('password123');
        cy.get('[data-testid="login-button"]').click();

        cy.url().should('include', '/dashboard');
        cy.get('[data-testid="welcome-message"]').should('contain', 'Welcome');
    });

    it('shows error for invalid credentials', () => {
        cy.get('[data-testid="email-input"]').type('wrong@example.com');
        cy.get('[data-testid="password-input"]').type('wrongpassword');
        cy.get('[data-testid="login-button"]').click();

        cy.get('[data-testid="error-message"]')
            .should('be.visible')
            .and('contain', 'Invalid credentials');
    });
});
```

### Playwright Nümunəsi

```javascript
// tests/login.spec.js
const { test, expect } = require('@playwright/test');

test.describe('Login Flow', () => {
    test('successful login redirects to dashboard', async ({ page }) => {
        await page.goto('/login');

        await page.fill('[data-testid="email-input"]', 'user@example.com');
        await page.fill('[data-testid="password-input"]', 'password123');
        await page.click('[data-testid="login-button"]');

        await expect(page).toHaveURL(/.*dashboard/);
        await expect(page.locator('[data-testid="welcome-message"]'))
            .toContainText('Welcome');
    });

    test('takes screenshot on failure', async ({ page }) => {
        await page.goto('/login');
        await page.fill('[data-testid="email-input"]', 'wrong@test.com');
        await page.fill('[data-testid="password-input"]', 'wrong');
        await page.click('[data-testid="login-button"]');

        await expect(page.locator('.error')).toBeVisible();
        await page.screenshot({ path: 'screenshots/login-error.png' });
    });
});
```

## Praktik Tapşırıqlar

### Laravel Dusk Quraşdırma

```bash
composer require laravel/dusk --dev
php artisan dusk:install
```

### Əsas Dusk Test

```php
<?php

namespace Tests\Browser;

use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class LoginTest extends DuskTestCase
{
    /** @test */
    public function user_can_login(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        $this->browse(function (Browser $browser) {
            $browser->visit('/login')
                ->type('email', 'test@example.com')
                ->type('password', 'password')
                ->press('Login')
                ->assertPathIs('/dashboard')
                ->assertSee('Dashboard')
                ->assertAuthenticated();
        });
    }

    /** @test */
    public function user_sees_validation_errors(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/login')
                ->type('email', '')
                ->type('password', '')
                ->press('Login')
                ->assertPathIs('/login')
                ->assertSee('The email field is required');
        });
    }

    /** @test */
    public function user_can_logout(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/dashboard')
                ->click('@logout-button')
                ->assertPathIs('/')
                ->assertGuest();
        });
    }
}
```

### Form və Interaction Testing

```php
<?php

namespace Tests\Browser;

use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class PostCreationTest extends DuskTestCase
{
    /** @test */
    public function user_can_create_a_post(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/posts/create')
                ->type('title', 'My New Post')
                ->type('body', 'This is the content of my post.')
                ->select('category', 'technology')
                ->check('is_published')
                ->attach('thumbnail', __DIR__ . '/fixtures/test-image.jpg')
                ->press('Create Post')
                ->assertPathIs('/posts')
                ->assertSee('My New Post')
                ->assertSee('Post created successfully');
        });
    }

    /** @test */
    public function user_can_edit_a_post(): void
    {
        $user = User::factory()->create();
        $post = \App\Models\Post::factory()->create(['user_id' => $user->id]);

        $this->browse(function (Browser $browser) use ($user, $post) {
            $browser->loginAs($user)
                ->visit("/posts/{$post->id}/edit")
                ->assertInputValue('title', $post->title)
                ->clear('title')
                ->type('title', 'Updated Title')
                ->press('Update Post')
                ->assertPathIs("/posts/{$post->id}")
                ->assertSee('Updated Title');
        });
    }

    /** @test */
    public function dropdown_filters_results(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/posts')
                ->select('status', 'published')
                ->waitForText('Published Posts')
                ->assertDontSee('Draft');
        });
    }
}
```

### JavaScript və AJAX Testing

```php
<?php

namespace Tests\Browser;

use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class DynamicContentTest extends DuskTestCase
{
    /** @test */
    public function modal_opens_and_closes(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/posts')
                ->click('@delete-button')
                ->waitFor('.modal')
                ->assertSee('Are you sure?')
                ->click('.modal .cancel-btn')
                ->waitUntilMissing('.modal')
                ->assertDontSee('Are you sure?');
        });
    }

    /** @test */
    public function infinite_scroll_loads_more_posts(): void
    {
        $user = User::factory()->create();
        \App\Models\Post::factory()->count(50)->create(['user_id' => $user->id]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/posts')
                ->assertPresent('.post-card')
                ->scrollTo('.load-more-trigger')
                ->waitFor('.post-card:nth-child(21)')
                ->assertVisible('.post-card:nth-child(21)');
        });
    }

    /** @test */
    public function search_filters_results_in_realtime(): void
    {
        $user = User::factory()->create();
        \App\Models\Post::factory()->create([
            'user_id' => $user->id,
            'title' => 'Laravel Testing',
        ]);
        \App\Models\Post::factory()->create([
            'user_id' => $user->id,
            'title' => 'Vue Components',
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/posts')
                ->type('@search-input', 'Laravel')
                ->waitForText('Laravel Testing')
                ->assertSee('Laravel Testing')
                ->assertDontSee('Vue Components');
        });
    }
}
```

### Dusk Selectors (@ prefix)

```php
<?php
// Blade template-də
// <button dusk="submit-button">Submit</button>

// Testdə
$browser->click('@submit-button');    // dusk="submit-button" olan elementi tapır
$browser->assertVisible('@modal');     // dusk="modal" görünür
$browser->waitFor('@loading-spinner'); // dusk="loading-spinner" görünənə qədər gözlə
```

### Multi-Browser Testing

```php
<?php

/** @test */
public function two_users_can_chat_in_realtime(): void
{
    $userA = User::factory()->create(['name' => 'Alice']);
    $userB = User::factory()->create(['name' => 'Bob']);

    $this->browse(function (Browser $browserA, Browser $browserB) use ($userA, $userB) {
        $browserA->loginAs($userA)
            ->visit('/chat');

        $browserB->loginAs($userB)
            ->visit('/chat');

        $browserA->type('@message-input', 'Hello Bob!')
            ->press('@send-button');

        $browserB->waitForText('Hello Bob!')
            ->assertSee('Alice: Hello Bob!');
    });
}
```

### Screenshot və Console Log

```php
<?php

/** @test */
public function capture_screenshot_on_complex_page(): void
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/dashboard')
            ->screenshot('dashboard-view')
            ->resize(375, 812)      // Mobile size
            ->screenshot('dashboard-mobile')
            ->resize(1920, 1080)    // Desktop size
            ->screenshot('dashboard-desktop');
    });
}
```

## Ətraflı Qeydlər

### 1. Browser testing və feature testing arasındakı fərq nədir?
**Cavab:** Feature testing HTTP request simulyasiya edir amma real brauzer istifadə etmir, JavaScript işləmir. Browser testing real brauzerdə işləyir, JS icra olunur, CSS render edilir. Feature testing daha sürətli, browser testing daha realdır. Feature testing backend, browser testing bütün stack-i test edir.

### 2. Headless browser nədir?
**Cavab:** UI olmadan işləyən brauzerdir. Bütün brauzer funksionallığını (rendering, JS execution) yerinə yetirir amma ekranda heç nə göstərmir. CI/CD mühitlərində istifadə olunur. Chrome `--headless` flag ilə, Firefox də eyni şəkildə headless işləyə bilər.

### 3. Cypress-in Selenium-dan üstünlükləri nədir?
**Cavab:** Cypress brauzerin içindən işləyir (daha sürətli), avtomatik gözləyir (no explicit waits), time travel debugging var, network intercept asandır. Selenium isə daha çox brauzer və dil dəstəkləyir. Cypress əsasən frontend-çilər, Selenium backend/QA komandasına uyğundur.

### 4. Laravel Dusk-da `@` prefix nə üçün istifadə olunur?
**Cavab:** `@` prefix Dusk selector-dur. Blade template-də `dusk="name"` attribute ilə təyin edilir, testdə `$browser->click('@name')` ilə istifadə olunur. CSS class-lardan fərqli olaraq, dusk attribute-ları yalnız test üçün istifadə olunur və production build-dən çıxarıla bilər.

### 5. Flaky browser testlərin əsas səbəbləri nələrdir?
**Cavab:** 1) Timing problemləri - element yüklənmədən əvvəl klik etmək, 2) Test data izolyasiyasının olmaması, 3) Hardcoded sleep-lər explicit wait əvəzinə, 4) Third-party service asılılığı, 5) Brauzer cache/cookie-lər, 6) Animation-ların tamamlanmasını gözləməmək.

### 6. waitFor və pause arasındakı fərq nədir?
**Cavab:** `waitFor('.element')` element görünənə qədər intellekt ilə gözləyir, element tez görünsə tez davam edir. `pause(3000)` həmişə 3 saniyə gözləyir, element erkən görünsə belə. `waitFor` daha sürətli və etibarlıdır, `pause` anti-pattern-dir amma bəzən workaround kimi istifadə olunur.

### 7. Browser testlərini CI/CD-də necə işlədirsiniz?
**Cavab:** Headless brauzer istifadə edilir (Chrome Headless, Firefox Headless). Docker container-da Xvfb (virtual display) qurulur. Screenshot/video artifact olaraq saxlanır. Parallel execution sürəti artırır. Retry mechanism flaky testlər üçün əlavə olunur.

## Əlaqəli Mövzular

- [Feature Testing (Junior)](04-feature-testing.md)
- [API Testing (Middle)](09-api-testing.md)
- [Test Organization (Middle)](13-test-organization.md)
- [Testing Authentication & Authorization (Middle)](18-testing-authentication.md)
- [Performance Testing (Senior)](20-performance-testing.md)
- [Security Testing (Senior)](21-security-testing.md)
