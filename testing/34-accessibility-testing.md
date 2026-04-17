# Accessibility Testing

## Nədir? (What is it?)

**Accessibility Testing (A11y Testing)** - veb tətbiqlərin **əlilliyi olan insanlar** (görmə, eşitmə, motor, kognitiv maneələri olan) tərəfindən istifadə edilə bilən olmasını təmin edən test prosesidir.

**"A11y"** - "accessibility" sözündəki 11 hərf üçün numeronim-dir (a + 11 hərf + y).

**Niyə vacibdir?**

- **Qanunvericilik:** ADA (ABŞ), EN 301 549 (AB), AODA (Kanada)
- **Əhali:** dünyada 1 milyarddan çox əlilliyi olan insan
- **Biznes:** daha geniş auditoriya, SEO-ya müsbət təsir
- **Etik məsuliyyət:** inclusive design

**Nümunə problemlər:**

- Şəkildə `alt` atributu yoxdur - screen reader oxuya bilmir
- Button yerinə `<div onclick>` - klaviatura ilə işləmir
- Zəif contrast - görmə zəifliyi olan istifadəçi oxuya bilmir
- Form input-unda `label` yoxdur - screen reader sahənin nə üçün olduğunu deyə bilmir

## Əsas Konseptlər (Key Concepts)

### 1. WCAG 2.1 Guidelines

**Web Content Accessibility Guidelines** - W3C tərəfindən yaradılmış standart.

**4 əsas prinsip (POUR):**

1. **Perceivable** - məlumat qavranılan olmalıdır
2. **Operable** - interfeys idarə edilən olmalıdır
3. **Understandable** - məzmun başa düşülən olmalıdır
4. **Robust** - müxtəlif texnologiyalarla uyğun olmalıdır

**Uyğunluq səviyyələri:**

- **Level A** - minimum (zəruri, əks halda istifadə edilə bilməz)
- **Level AA** - orta (hüquqi standart, çoxu burada dayanır)
- **Level AAA** - yüksək (xüsusi ehtiyaclar üçün)

**Vacib AA tələblər:**

- Contrast ratio: normal mətn 4.5:1, böyük mətn 3:1
- Alt text bütün image-lər üçün
- Keyboard navigation tam işləməli
- Form labels mövcud olmalı
- Heading hierarchy düzgün (h1 > h2 > h3)

### 2. ARIA Atributları

**Accessible Rich Internet Applications** - HTML-in semantik olmayan hissələri üçün accessibility məlumat əlavə edir.

**Əsas atributlar:**

```html
<!-- Role -->
<div role="button" tabindex="0">Click me</div>
<div role="navigation" aria-label="Main menu">...</div>
<div role="alert">Error occurred!</div>

<!-- State -->
<button aria-pressed="true">Toggle</button>
<div aria-expanded="false">Collapsed panel</div>
<input aria-invalid="true" aria-describedby="error-msg">

<!-- Properties -->
<button aria-label="Close dialog">X</button>
<div aria-labelledby="heading-id">...</div>
<input aria-required="true">
<div aria-live="polite">Status updates here</div>
```

**ARIA qaydası #1:** mümkünsə semantic HTML istifadə edin, ARIA əvəzinə. `<button>` > `<div role="button">`.

### 3. Avtomat Alətlər

**axe-core:**

- Deque Systems tərəfindən
- JS kitabxanası, hər frameworkdə işləyir
- 57%-ə qədər a11y issue-ləri avtomatik tapır

**Pa11y:**

- CLI aləti, CI-friendly
- Node.js-də qurulub
- Sitemap scan edə bilir

**Lighthouse:**

- Google Chrome-a daxilidir
- Performance + A11y + SEO score verir
- CI-də lighthouse-ci ilə avtomatlaşdırılır

**WAVE:**

- Browser extension
- Visual feedback verir

### 4. Manual Testing

Avtomat alətlər 30-50% issue-ləri tapır, qalanı manual yoxlanmalıdır:

**Screen Reader-lər:**

- **NVDA** (Windows, pulsuz)
- **JAWS** (Windows, kommersiya)
- **VoiceOver** (macOS/iOS, built-in)
- **TalkBack** (Android)

**Manual yoxlamalar:**

- Keyboard-only navigation (Tab, Enter, Escape)
- Zoom 200% - layout sınmamalı
- Color contrast yoxlaması
- Video-larda caption
- Form error mesajları screen reader-də oxunur?

## Praktiki Nümunələr (Practical Examples)

### axe-core ilə Automated Testing

```javascript
// tests/a11y/homepage.spec.js
const { test, expect } = require('@playwright/test');
const AxeBuilder = require('@axe-core/playwright').default;

test.describe('Accessibility tests', () => {
    test('homepage should not have accessibility violations', async ({ page }) => {
        await page.goto('http://localhost:8000');

        const results = await new AxeBuilder({ page })
            .withTags(['wcag2a', 'wcag2aa', 'wcag21aa'])
            .analyze();

        expect(results.violations).toEqual([]);
    });

    test('login form is accessible', async ({ page }) => {
        await page.goto('/login');

        const results = await new AxeBuilder({ page })
            .include('form.login')
            .disableRules(['color-contrast'])
            .analyze();

        expect(results.violations).toEqual([]);
    });

    test('keyboard navigation works', async ({ page }) => {
        await page.goto('/');

        await page.keyboard.press('Tab');
        let focused = await page.evaluate(() => document.activeElement.textContent);
        expect(focused).toContain('Skip to content');

        await page.keyboard.press('Tab');
        focused = await page.evaluate(() => document.activeElement.tagName);
        expect(focused).toBe('A');
    });
});
```

### Pa11y CI Integration

```json
{
  "defaults": {
    "standard": "WCAG2AA",
    "runners": ["axe", "htmlcs"],
    "timeout": 30000,
    "viewport": {
      "width": 1280,
      "height": 800
    }
  },
  "urls": [
    "http://localhost:8000/",
    "http://localhost:8000/login",
    "http://localhost:8000/register",
    {
      "url": "http://localhost:8000/dashboard",
      "actions": [
        "set field #email to user@example.com",
        "set field #password to password",
        "click element button[type=submit]",
        "wait for path to be /dashboard"
      ]
    }
  ]
}
```

```bash
npm install --save-dev pa11y-ci
npx pa11y-ci --config .pa11yci
```

## PHP/Laravel ilə Tətbiq

### Laravel Dusk + axe-core

```php
<?php

namespace Tests\Browser\Accessibility;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class AccessibilityTest extends DuskTestCase
{
    private const AXE_CDN = 'https://cdnjs.cloudflare.com/ajax/libs/axe-core/4.8.3/axe.min.js';

    public function testHomepageAccessibility(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/')
                ->waitFor('body');

            $violations = $this->runAxe($browser);

            $this->assertEmpty(
                $violations,
                $this->formatViolations($violations)
            );
        });
    }

    public function testFormAccessibility(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/contact')
                ->waitFor('form');

            $violations = $this->runAxe($browser, [
                'runOnly' => ['wcag2a', 'wcag2aa'],
                'rules' => [
                    'label' => ['enabled' => true],
                    'aria-required-attr' => ['enabled' => true],
                ],
            ]);

            $this->assertEmpty($violations, $this->formatViolations($violations));
        });
    }

    public function testKeyboardNavigation(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/')
                ->keys('body', '{tab}')
                ->assertFocused('a.skip-link')
                ->keys('body', '{tab}')
                ->assertFocused('header nav a:first-child');
        });
    }

    public function testColorContrast(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/');

            $violations = $this->runAxe($browser, [
                'runOnly' => ['color-contrast'],
            ]);

            $this->assertEmpty($violations);
        });
    }

    private function runAxe(Browser $browser, array $options = []): array
    {
        $browser->script([
            sprintf(
                "var script = document.createElement('script');
                 script.src = '%s';
                 document.head.appendChild(script);",
                self::AXE_CDN
            ),
        ]);

        $browser->pause(1000);

        $optionsJson = json_encode($options ?: (object)[]);

        $results = $browser->driver->executeAsyncScript(
            "var callback = arguments[arguments.length - 1];
             axe.run(document, {$optionsJson}, function(err, results) {
                 callback(results.violations);
             });"
        );

        return $results ?? [];
    }

    private function formatViolations(array $violations): string
    {
        if (empty($violations)) {
            return 'No violations found';
        }

        $output = "Accessibility violations found:\n";
        foreach ($violations as $violation) {
            $output .= sprintf(
                "- [%s] %s: %s\n  Nodes: %d\n",
                $violation['impact'] ?? 'unknown',
                $violation['id'],
                $violation['description'],
                count($violation['nodes'])
            );
        }
        return $output;
    }
}
```

### Blade Template - Accessible Form

```blade
<form method="POST" action="{{ route('contact.store') }}" aria-labelledby="contact-heading">
    @csrf

    <h1 id="contact-heading">Contact Us</h1>

    <div role="alert" aria-live="polite">
        @if ($errors->any())
            <div class="alert alert-error">
                <h2>Please fix the following errors:</h2>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>

    <div class="form-group">
        <label for="name">
            Name <span aria-hidden="true">*</span>
            <span class="sr-only">required</span>
        </label>
        <input
            type="text"
            id="name"
            name="name"
            value="{{ old('name') }}"
            aria-required="true"
            aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}"
            aria-describedby="name-hint @error('name') name-error @enderror"
        >
        <small id="name-hint">Enter your full name</small>
        @error('name')
            <span id="name-error" class="error" role="alert">{{ $message }}</span>
        @enderror
    </div>

    <div class="form-group">
        <label for="email">
            Email <span aria-hidden="true">*</span>
            <span class="sr-only">required</span>
        </label>
        <input
            type="email"
            id="email"
            name="email"
            value="{{ old('email') }}"
            aria-required="true"
            autocomplete="email"
        >
    </div>

    <fieldset>
        <legend>Preferred contact method</legend>
        <label>
            <input type="radio" name="contact_method" value="email" checked>
            Email
        </label>
        <label>
            <input type="radio" name="contact_method" value="phone">
            Phone
        </label>
    </fieldset>

    <button type="submit">
        Send Message
    </button>
</form>

<style>
    .sr-only {
        position: absolute;
        width: 1px;
        height: 1px;
        padding: 0;
        margin: -1px;
        overflow: hidden;
        clip: rect(0, 0, 0, 0);
        border: 0;
    }

    *:focus-visible {
        outline: 3px solid #0066cc;
        outline-offset: 2px;
    }
</style>
```

### Livewire Accessible Modal

```php
<?php

namespace App\Http\Livewire;

use Livewire\Component;

class AccessibleModal extends Component
{
    public bool $isOpen = false;
    public string $title = '';

    public function open(): void
    {
        $this->isOpen = true;
        $this->dispatch('modal-opened');
    }

    public function close(): void
    {
        $this->isOpen = false;
        $this->dispatch('modal-closed');
    }

    public function render()
    {
        return view('livewire.accessible-modal');
    }
}
```

```blade
@if ($isOpen)
    <div
        role="dialog"
        aria-modal="true"
        aria-labelledby="modal-title"
        aria-describedby="modal-desc"
        x-data="{}"
        x-init="$nextTick(() => $refs.closeBtn.focus())"
        @keydown.escape="$wire.close()"
    >
        <div class="modal-overlay" aria-hidden="true" wire:click="close"></div>

        <div class="modal-content" role="document">
            <h2 id="modal-title">{{ $title }}</h2>
            <p id="modal-desc">{{ $slot }}</p>

            <button
                type="button"
                wire:click="close"
                x-ref="closeBtn"
                aria-label="Close modal"
            >
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    </div>
@endif
```

### PHP A11y Test Helper

```php
<?php

namespace Tests\Support;

class AccessibilityAssertions
{
    public static function assertHasAltText(string $html): void
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML($html);

        $images = $dom->getElementsByTagName('img');

        foreach ($images as $img) {
            if (!$img->hasAttribute('alt')) {
                throw new \AssertionError(
                    "Image missing alt attribute: " . $dom->saveHTML($img)
                );
            }
        }
    }

    public static function assertFormLabelsExist(string $html): void
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);

        $inputs = $xpath->query("//input[@type!='hidden' and @type!='submit']");

        foreach ($inputs as $input) {
            $id = $input->getAttribute('id');
            $ariaLabel = $input->getAttribute('aria-label');
            $ariaLabelledby = $input->getAttribute('aria-labelledby');

            if (!$id && !$ariaLabel && !$ariaLabelledby) {
                throw new \AssertionError('Input without label found');
            }

            if ($id) {
                $labels = $xpath->query("//label[@for='{$id}']");
                if ($labels->length === 0 && !$ariaLabel && !$ariaLabelledby) {
                    throw new \AssertionError("No label for input #{$id}");
                }
            }
        }
    }

    public static function assertHeadingHierarchy(string $html): void
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);

        $headings = $xpath->query('//h1 | //h2 | //h3 | //h4 | //h5 | //h6');
        $previousLevel = 0;

        foreach ($headings as $heading) {
            $level = (int)substr($heading->tagName, 1);

            if ($previousLevel > 0 && $level > $previousLevel + 1) {
                throw new \AssertionError(
                    "Heading hierarchy broken: h{$previousLevel} -> h{$level}"
                );
            }

            $previousLevel = $level;
        }
    }
}
```

## Interview Sualları (Q&A)

### 1. WCAG nədir və hansı səviyyələri var?

WCAG (Web Content Accessibility Guidelines) - W3C tərəfindən a11y standartıdır. 3 səviyyə var: **A** (minimum), **AA** (hüquqi standart), **AAA** (yüksək). AA əksər qanunların tələbidir.

### 2. POUR prinsipləri nədir?

- **Perceivable** - məlumat qavranılan (alt text, captions)
- **Operable** - idarə edilən (keyboard, zaman limiti yox)
- **Understandable** - başa düşülən (readable, predictable)
- **Robust** - uyğun (assistive technologies ilə işləyən)

### 3. ARIA nə zaman istifadə edilməlidir?

**ARIA qaydası #1:** mümkünsə istifadə etməyin, semantic HTML daha yaxşıdır. `<button>` həmişə `<div role="button">` -dən üstündür. ARIA native HTML-in çatmadığı yerlərdə (custom widgets, dynamic updates) lazımdır.

### 4. axe-core, Pa11y, Lighthouse arasında fərq?

- **axe-core:** JS library, hər framework, detallı rules
- **Pa11y:** CLI tool, CI üçün ideal, sitemap scan
- **Lighthouse:** Google-un aləti, performance+a11y+SEO combined

### 5. Avtomat a11y testlər kifayətdir?

Xeyr. Avtomat alətlər issue-lərin **30-50%-ni** tapır. Manual yoxlama (screen reader, keyboard navigation, real user testing) vacibdir. Xüsusilə kognitiv məsələlər avtomat tapıla bilməz.

### 6. Color contrast ratio necə yoxlanır?

WCAG AA: normal mətn üçün **4.5:1**, böyük mətn (18pt+) üçün **3:1**. Alətlər: WebAIM Contrast Checker, axe-core, Lighthouse. Design zamanı Figma plugin-ləri də var.

### 7. Screen reader-də formu necə test edərsiz?

1. NVDA/VoiceOver aç
2. Tab ilə naviqasiya et
3. Hər field üçün label oxunur?
4. Required field-lər bildirilir?
5. Error mesajları real-time oxunur (`aria-live`)
6. Success mesajı dinlənilir?

### 8. Keyboard-only user üçün nə önəmlidir?

- Bütün interactive elementlər `Tab` ilə reachable
- Focus indicator görünən (outline)
- Logical tab order (tabindex=0, -1 istifadə qaydası)
- Escape ilə modal bağlanır
- Skip links (əsas məzmuna keçid)

### 9. Accessibility-ni Laravel proyektinə necə inteqrasiya edərsiz?

- Blade components-də semantic HTML
- Form requests-dən gələn error-lar `aria-describedby` ilə
- Dusk + axe-core ilə avtomat test
- Pre-commit hook-da lint
- CI-da Pa11y run et
- Storybook ilə component-level a11y test

### 10. ADA lawsuit-lərdən necə qorunaq?

- WCAG 2.1 AA uyğunluğu
- Accessibility Statement publish et
- Regular audits (avtomat + manual)
- VPAT (Voluntary Product Accessibility Template) doldur
- User feedback channel accessibility üçün

## Best Practices / Anti-Patterns

### Best Practices

1. **Semantic HTML əvvəl** - `<button>`, `<nav>`, `<main>`, `<header>` istifadə edin
2. **Alt text hər image-ə** - dekorativ image üçün `alt=""`
3. **Label hər form input-a** - `<label for="...">`
4. **Keyboard testing mütəmadi** - Tab ilə bütün səhifəni gəzin
5. **Focus visible saxla** - `outline:none` etməyin
6. **ARIA live regions** - dynamic content üçün `aria-live="polite"`
7. **Contrast check design mərhələsində** - sonra düzəltmək çətindir
8. **Real user testing** - əlilliyi olan user-lərlə

### Anti-Patterns

1. **Div-soup** - `<div onclick>` yerinə `<button>`
2. **Placeholder-i label kimi** - screen reader görmür, fokus zamanı itir
3. **Color-only info** - "Qırmızı xəta" deyil, ikon + mətn
4. **Auto-play video/audio** - a11y və UX üçün pisdir
5. **`outline: none`** - fokus göstəricisi silmək
6. **Non-descriptive links** - "Click here" yerinə "Download report"
7. **tabindex="5"** - tabindex > 0 anti-pattern, təbii sıranı poz
8. **Title attribute-a asılılıq** - tooltip screen reader-də işləmir

### Audit Workflow

1. Lighthouse automated scan
2. axe DevTools manual scan
3. Keyboard navigation test
4. Screen reader test (NVDA/VoiceOver)
5. Zoom 200% layout check
6. Color contrast review
7. Manual accessibility tree inspection
