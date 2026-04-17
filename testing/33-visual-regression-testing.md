# Visual Regression Testing

## Nədir? (What is it?)

**Visual Regression Testing (VRT)** - UI-nin screenshot-larını götürüb **baseline (etalon)** şəkillərlə piksel səviyyəsində müqayisə edən test texnikasıdır. Məqsəd - CSS, JS və ya layout dəyişikliklərinin UI-da gözlənilməz vizual regression-lara səbəb olub-olmadığını avtomatik aşkarlamaqdır.

**Niyə lazımdır?**

- Functional test-lər UI-nin **necə göründüyünü** yoxlamır
- CSS dəyişikliyi bir komponenti düzəltsə də digərini poza bilər
- Cross-browser, cross-device fərqlərini aşkarlamaq
- Design system consistency təmin etmək

**Nümunə problem:**

```css
/* Developer bu dəyişikliyi edir */
.button { padding: 12px; } /* əvvəl 8px idi */

/* Nəticə: "Submit" button-u hər səhifədə 4px böyük oldu */
/* Functional test-lər pass olur, amma UI sındı */
```

## Əsas Konseptlər (Key Concepts)

### 1. Baseline Images (Etalon Şəkillər)

- İlk run-da screenshot götürülür və **baseline** kimi saxlanır
- Sonrakı run-larda yeni screenshot baseline ilə müqayisə olunur
- Baseline-lar version control-a (git) commit olunur
- UI intentionally dəyişdiksə baseline update edilir

### 2. Diff Detection

- **Pixel-by-pixel** müqayisə
- **Threshold** təyin edilə bilər (məs: 0.1% fərq ignore)
- **Highlighted diff image** yaradılır - fərqlər qırmızı ilə göstərilir
- Anti-aliasing, font rendering fərqləri tolerance edilir

### 3. Dynamic Content Handling

Vizual dəyişən elementləri stabilləşdirmək:

- **Timestamps:** mocklanmış tarix
- **Animations:** disable edilir
- **Random data:** seed-li generator
- **Loading spinners:** wait for idle
- **Third-party widgets:** hide və ya mock

### 4. Responsive Testing

- Müxtəlif viewport-larda test: mobile (375px), tablet (768px), desktop (1280px)
- Hər breakpoint üçün ayrı baseline
- Orientation dəyişiklikləri (portrait/landscape)

### 5. Populyar Alətlər

**Cloud-based:**

- **Percy** (BrowserStack) - Git integration, PR-da diff göstərir
- **Chromatic** - Storybook-la ideal, component-level testing
- **Applitools Eyes** - AI-powered, smart diff
- **LambdaTest** - cross-browser VRT

**Self-hosted:**

- **BackstopJS** - Node.js, JSON config
- **Playwright** - built-in `toHaveScreenshot()`
- **Cypress** - `cypress-image-snapshot` plugin
- **Puppeteer** - manual screenshot + pixel diff
- **Resemble.js** - JS image comparison library

## Praktiki Nümunələr (Practical Examples)

### Playwright Visual Testing

```javascript
// tests/visual/homepage.spec.js
const { test, expect } = require('@playwright/test');

test.describe('Homepage visual tests', () => {
    test('homepage looks correct', async ({ page }) => {
        await page.goto('http://localhost:8000');
        await page.waitForLoadState('networkidle');

        // Animasiyaları dayandır
        await page.addStyleTag({
            content: '*, *::before, *::after { animation-duration: 0s !important; }'
        });

        await expect(page).toHaveScreenshot('homepage.png', {
            fullPage: true,
            maxDiffPixels: 100,
        });
    });

    test('responsive mobile view', async ({ page }) => {
        await page.setViewportSize({ width: 375, height: 667 });
        await page.goto('/');
        await expect(page).toHaveScreenshot('homepage-mobile.png');
    });

    test('button hover state', async ({ page }) => {
        await page.goto('/');
        await page.hover('button.primary');
        await expect(page.locator('button.primary')).toHaveScreenshot('button-hover.png');
    });
});
```

### BackstopJS Config

```json
{
  "id": "myapp_visual_tests",
  "viewports": [
    { "label": "phone", "width": 375, "height": 667 },
    { "label": "tablet", "width": 768, "height": 1024 },
    { "label": "desktop", "width": 1920, "height": 1080 }
  ],
  "scenarios": [
    {
      "label": "Homepage",
      "url": "http://localhost:8000",
      "delay": 1000,
      "misMatchThreshold": 0.1,
      "requireSameDimensions": true
    },
    {
      "label": "Login Page",
      "url": "http://localhost:8000/login",
      "hideSelectors": [".timestamp", ".ad-banner"],
      "selectors": ["form.login"]
    },
    {
      "label": "Dashboard",
      "url": "http://localhost:8000/dashboard",
      "cookiePath": "backstop_data/engine_scripts/cookies.json",
      "hoverSelector": ".menu-item"
    }
  ],
  "paths": {
    "bitmaps_reference": "backstop_data/reference",
    "bitmaps_test": "backstop_data/test",
    "html_report": "backstop_data/html_report"
  },
  "engine": "puppeteer",
  "engineOptions": { "args": ["--no-sandbox"] }
}
```

Komanda:

```bash
# Initial baseline yarat
backstop reference

# Test run
backstop test

# Baseline-ları yenilə (approve changes)
backstop approve
```

## PHP/Laravel ilə Tətbiq

### Laravel Dusk + Visual Diff

```php
<?php

namespace Tests\Browser\Visual;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class VisualRegressionTest extends DuskTestCase
{
    private const BASELINE_DIR = __DIR__ . '/../screenshots/baseline';
    private const ACTUAL_DIR = __DIR__ . '/../screenshots/actual';
    private const DIFF_DIR = __DIR__ . '/../screenshots/diff';

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([self::BASELINE_DIR, self::ACTUAL_DIR, self::DIFF_DIR] as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }

    public function testHomepageVisual(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/')
                ->waitFor('body')
                ->pause(500)
                ->script([
                    '*, *::before, *::after { animation: none !important; transition: none !important; }',
                ])
                ->screenshot('actual/homepage');

            $this->compareWithBaseline('homepage');
        });
    }

    public function testLoginFormVisual(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/login')
                ->waitFor('form')
                ->screenshot('actual/login-form');

            $this->compareWithBaseline('login-form', 0.5);
        });
    }

    public function testResponsiveLayouts(): void
    {
        $viewports = [
            'mobile' => [375, 667],
            'tablet' => [768, 1024],
            'desktop' => [1920, 1080],
        ];

        foreach ($viewports as $name => [$width, $height]) {
            $this->browse(function (Browser $browser) use ($name, $width, $height) {
                $browser->resize($width, $height)
                    ->visit('/')
                    ->screenshot("actual/homepage-{$name}");

                $this->compareWithBaseline("homepage-{$name}");
            });
        }
    }

    private function compareWithBaseline(string $name, float $threshold = 0.1): void
    {
        $baseline = self::BASELINE_DIR . "/{$name}.png";
        $actual = self::ACTUAL_DIR . "/{$name}.png";
        $diff = self::DIFF_DIR . "/{$name}.png";

        if (!file_exists($baseline)) {
            copy($actual, $baseline);
            $this->markTestSkipped("Baseline created: {$baseline}");
            return;
        }

        $diffPercent = $this->calculateImageDiff($baseline, $actual, $diff);

        $this->assertLessThanOrEqual(
            $threshold,
            $diffPercent,
            "Visual regression detected ({$diffPercent}%). See: {$diff}"
        );
    }

    private function calculateImageDiff(string $baseline, string $actual, string $output): float
    {
        $img1 = imagecreatefrompng($baseline);
        $img2 = imagecreatefrompng($actual);

        $width = imagesx($img1);
        $height = imagesy($img1);

        if ($width !== imagesx($img2) || $height !== imagesy($img2)) {
            throw new \RuntimeException('Image dimensions differ');
        }

        $diffImage = imagecreatetruecolor($width, $height);
        $red = imagecolorallocate($diffImage, 255, 0, 0);

        $diffPixels = 0;
        $totalPixels = $width * $height;

        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                $c1 = imagecolorat($img1, $x, $y);
                $c2 = imagecolorat($img2, $x, $y);

                if ($c1 !== $c2) {
                    imagesetpixel($diffImage, $x, $y, $red);
                    $diffPixels++;
                } else {
                    imagesetpixel($diffImage, $x, $y, $c1);
                }
            }
        }

        imagepng($diffImage, $output);
        imagedestroy($img1);
        imagedestroy($img2);
        imagedestroy($diffImage);

        return ($diffPixels / $totalPixels) * 100;
    }
}
```

### Percy Integration with Laravel Dusk

```php
<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class PercyVisualTest extends DuskTestCase
{
    public function testHomepageWithPercy(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/')
                ->waitFor('main');

            $this->percySnapshot($browser, 'Homepage');
        });
    }

    public function testDashboardWithPercy(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/dashboard')
                ->waitFor('.dashboard-widgets');

            $this->percySnapshot($browser, 'Dashboard', [
                'widths' => [375, 768, 1280],
            ]);
        });
    }

    private function percySnapshot(Browser $browser, string $name, array $options = []): void
    {
        $html = $browser->driver->getPageSource();
        $url = $browser->driver->getCurrentURL();

        $data = array_merge([
            'name' => $name,
            'url' => $url,
            'dom_snapshot' => $html,
        ], $options);

        $client = new \GuzzleHttp\Client();
        $client->post('https://percy.io/api/v1/snapshots', [
            'headers' => [
                'Authorization' => 'Token ' . env('PERCY_TOKEN'),
            ],
            'json' => $data,
        ]);
    }
}
```

### GitHub Actions CI Integration

```yaml
name: Visual Regression Tests

on: [pull_request]

jobs:
  visual-test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'

      - name: Install dependencies
        run: composer install

      - name: Run Laravel server
        run: php artisan serve &

      - name: Run visual tests with Percy
        run: npx percy exec -- php artisan dusk
        env:
          PERCY_TOKEN: ${{ secrets.PERCY_TOKEN }}
```

### Dynamic Content Handling

```php
public function testArticlePageWithStableTimestamp(): void
{
    $this->browse(function (Browser $browser) {
        $browser->script("
            Date.now = function() { return 1704067200000; };
            Date.prototype.getTime = function() { return 1704067200000; };
        ");

        $browser->visit('/articles/1')
            ->waitFor('.article-content')
            ->screenshot('actual/article');

        $this->compareWithBaseline('article');
    });
}

public function testWithHiddenDynamicElements(): void
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/dashboard')
            ->script("
                document.querySelectorAll('.timestamp, .ad-banner, .chat-widget')
                    .forEach(el => el.style.visibility = 'hidden');
            ")
            ->screenshot('actual/dashboard');
    });
}
```

## Interview Sualları (Q&A)

### 1. Visual regression testing nədir və niyə vacibdir?

VRT - UI-nin screenshot-larını baseline ilə müqayisə edən test növüdür. Functional test-lər UI-nin görünüşünü yoxlamır - CSS, layout, font dəyişikliklərini ancaq VRT tapır. Design consistency və brand identity üçün vacibdir.

### 2. Baseline images haradan gəlir və necə manage olunur?

İlk test run-da screenshot-lar götürülüb baseline kimi saxlanır. Version control-a (git) commit olunur. UI intentionally dəyişəndə `approve` komandası ilə yenilənir. PR-da diff göstərilir.

### 3. Dynamic content (tarix, animasiya, reklam) necə idarə olunur?

- **Mock Date.now()** - sabit timestamp
- **Disable animations** - CSS ilə `animation: none`
- **Hide selectors** - dinamik elementləri gizlət
- **Wait for stable state** - network idle, animation end
- **Seed data** - deterministik test data

### 4. Percy, Chromatic və BackstopJS arasında fərq nədir?

- **Percy:** cloud-based, Git integration, hər platformla işləyir
- **Chromatic:** Storybook-a xüsusi, component-level, UI review workflow
- **BackstopJS:** self-hosted, open-source, Node.js-də quraşdırılır

### 5. Flaky VRT testlərinin səbəbi nədir?

- Font rendering fərqləri (OS-dən asılı)
- Anti-aliasing dəyişmələri
- Timing issues (animation, loading)
- Third-party embed-lər
- Browser version fərqləri

**Həll:** threshold qoymaq, eyni OS-də test, headless mode, font-ları embed etmək.

### 6. VRT-ni hansı səviyyədə etmək lazımdır?

- **Component-level** (Storybook + Chromatic) - ən ideal
- **Page-level** - Playwright, Dusk ilə
- **Critical flows** - checkout, onboarding, sign-up
- Hər səhifə lazım deyil - yüksək dəyərli səhifələr seçin

### 7. Cross-browser VRT necə qurulur?

- BrowserStack, Sauce Labs kimi cloud services
- Playwright-ın `chromium`, `firefox`, `webkit` project-ləri
- Hər browser üçün ayrı baseline saxlamaq
- CI-da parallel run etmək

### 8. VRT vs manual QA arasında fərq nədir?

VRT **avtomatdır, dəqiqdir** (piksel-mükəmməl), hər PR-da run olur. Manual QA subjective, yavaşdır amma **UX və aesthetics** yoxlaya bilir. Hər ikisi birlikdə lazımdır.

### 9. Responsive VRT necə aparılır?

Hər breakpoint üçün (mobile, tablet, desktop) ayrı screenshot götürülür, hər biri üçün ayrı baseline saxlanır. Playwright-də `devices` API, Dusk-da `resize()` istifadə olunur.

## Best Practices / Anti-Patterns

### Best Practices

1. **Animasiyaları dayandır** - bütün testlərdə CSS inject edin
2. **Deterministik data** - factory-lərdə sabit data, tarix mock
3. **Threshold agrresiv olmasın** - 0.1-0.5% tolerance
4. **Selective screenshots** - full page əvəzinə komponent
5. **PR-da review** - diff-ləri manual review edilsin
6. **Baseline-ı git-də saxla** - LFS istifadə edin böyükdürsə

### Anti-Patterns

1. **Hər səhifəni screenshot etmək** - test suite şişir
2. **Threshold-u 0% qoymaq** - flaky testlər yaranır
3. **Baseline-ları auto-approve** - regression-lar qeyri-müəyyən qalır
4. **Dynamic content-i ignorlamaq** - hər run-da diff çıxır
5. **Production data ilə test** - PII risk, non-deterministik
6. **OS/browser mismatch** - developer Mac-də, CI Linux-da = fərqli render

### Əlavə Tövsiyələr

- **Visual review workflow:** Percy/Chromatic-da manager approve etsin
- **Component Storybook** ilə integration - ən təmiz yanaşma
- **Accessibility testing ilə birləşdir** - axe-core + VRT
- **Test data freeze** - seed-li factory, frozen time
