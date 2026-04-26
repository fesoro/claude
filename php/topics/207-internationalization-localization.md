# Internationalization & Localization — i18n / L10n (Middle)

## İcmal
Internationalization (i18n) tətbiqi çoxdilli dəstəyə hazırlamaq, Localization (L10n) isə həmin dil üçün aktual tərcümə və formatlaşdırma deməkdir. Laravel-in built-in dil dəstəyi, Spatie-nin translatable paketi, Carbon-la tarix formatlaşdırması — bunlar real SaaS layihələrinin əsas komponentləridir.

## Niyə Vacibdir
Beynəlxalq bazar, ya da sadəcə multi-language müştəri tələbi olan hər layihədə i18n lazım olur. Sonradan əlavə etmək — bütün string-ləri tapıb çıxarmaq — çox bahalıdır. Əvvəlcədən düzgün arxitektura qurmaq investisiyanı azaldır.

## Əsas Anlayışlar

### Laravel Dil Faylları
```
lang/
├── az/
│   ├── auth.php
│   ├── pagination.php
│   ├── validation.php
│   └── messages.php
├── en/
│   └── messages.php
└── az.json   # JSON format (key = actual string)
```

```php
// lang/az/messages.php
return [
    'welcome'        => 'Xoş gəlmisiniz, :name!',
    'order_created'  => 'Sifariş uğurla yaradıldı.',
    'items_in_cart'  => ':count məhsul səbətdədir',
];
```

### `__()` və `trans()` Helpers
```php
// Sadə
__('messages.welcome');                      // "Xoş gəlmisiniz, :name!"
__('messages.welcome', ['name' => 'Orxan']); // "Xoş gəlmisiniz, Orxan!"

// trans() eyni şeydir
trans('messages.welcome', ['name' => 'Orxan']);

// Mövcud olmayan key
__('messages.missing_key');  // "messages.missing_key" — key-i qaytarır

// Blade-də
{{ __('messages.welcome', ['name' => $user->name]) }}
@lang('messages.welcome', ['name' => $user->name])
```

### JSON Formatı (Daha Rahat)
```json
// lang/az.json
{
    "Welcome": "Xoş gəlmisiniz",
    "Log in": "Daxil ol",
    "Hello, :name!": "Salam, :name!",
    "Your order has been placed.": "Sifarişiniz qəbul edildi."
}
```
```php
__('Hello, :name!', ['name' => $user->name]); // İngilis key, AZ dəyər
```
JSON formatı `.php` fayllarından daha rahatdır çünki key özü default ingilis mətn olur.

### Locale İdarəsi
```php
// Cari locale
App::getLocale();           // 'en'

// Dəyiş
App::setLocale('az');

// Fallback (key tapılmasa)
App::getFallbackLocale();   // config/app.php-dən

// Müvəqqəti locale
App::usingLocale('fr', function () {
    return __('messages.welcome'); // Fransızca
});
```

### Middleware — İstifadəçi Locale-si
```php
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user()?->locale         // DB-dən
            ?? $request->getPreferredLanguage(['az', 'en', 'ru']) // Browser
            ?? config('app.locale');                // Default
        
        App::setLocale($locale);
        Carbon::setLocale($locale);
        
        return $next($request);
    }
}
```

## Çoxalma (Pluralization)

### `trans_choice()` / `@choice`
```php
// lang/az/messages.php
return [
    'items_count' => '{0} Məhsul yoxdur|{1} :count məhsul|[2,*] :count məhsul',
    'days_left'   => 'Bir gün qaldı|:count gün qaldı',
];

trans_choice('messages.items_count', 0);   // "Məhsul yoxdur"
trans_choice('messages.items_count', 1);   // "1 məhsul"
trans_choice('messages.items_count', 5);   // "5 məhsul"

// Blade
@choice('messages.items_count', $count)
```

## Database Translations — Spatie Translatable

Model məzmununu (məqalə başlığı, kateqoriya adı) çox dildə saxlamaq üçün:
```bash
composer require spatie/laravel-translatable
```

```php
// Migration — JSON sütun
Schema::create('products', function (Blueprint $table) {
    $table->id();
    $table->json('name');         // {"az": "Masa", "en": "Table"}
    $table->json('description');
});

// Model
class Product extends Model
{
    use HasTranslations;

    public array $translatable = ['name', 'description'];
}
```

```php
// Yaratmaq
Product::create([
    'name' => [
        'az' => 'Kitab',
        'en' => 'Book',
        'ru' => 'Книга',
    ],
]);

// Oxumaq — cari locale
$product->name;                  // App locale-na görə

// Xüsusi locale
$product->getTranslation('name', 'en');   // "Book"
$product->setTranslation('name', 'fr', 'Livre');
$product->save();

// Bütün tərcümələr
$product->getTranslations('name');  // ['az' => 'Kitab', 'en' => 'Book']
```

## Tarix, Vaxt, Rəqəm Formatlaşdırma

### Carbon Locale
```php
Carbon::setLocale('az');

$date = Carbon::now()->subDays(3);
$date->diffForHumans();   // "3 gün əvvəl"

$date->translatedFormat('d F Y');  // "15 aprel 2026"
$date->isoFormat('D MMMM YYYY'); // Locale-a görə
```

### PHP NumberFormatter (intl extension)
```php
// Rəqəm formatlaşdırma
$formatter = new NumberFormatter('az_AZ', NumberFormatter::DECIMAL);
echo $formatter->format(1234567.89);  // "1.234.567,89"

// Valyuta
$formatter = new NumberFormatter('az_AZ', NumberFormatter::CURRENCY);
echo $formatter->formatCurrency(50.5, 'AZN');  // "50,50 ₼"
echo $formatter->formatCurrency(50.5, 'USD');  // "50,50 $"

// Faiz
$formatter = new NumberFormatter('az_AZ', NumberFormatter::PERCENT);
echo $formatter->format(0.15);  // "15%"
```

### Blade Helper (Custom)
```php
// Custom helper
function money(int $amountInCents, string $currency = 'AZN', string $locale = null): string
{
    $locale    = $locale ?? App::getLocale();
    $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);
    return $formatter->formatCurrency($amountInCents / 100, $currency);
}

// Blade-də: {{ money($order->total, 'AZN') }}
```

## Validation Mesajları
```php
// lang/az/validation.php
return [
    'required' => ':attribute sahəsi mütləqdir.',
    'email'    => ':attribute düzgün email formatında olmalıdır.',
    'min'      => [
        'string' => ':attribute ən az :min simvol olmalıdır.',
    ],
    
    'attributes' => [
        'email'    => 'Email ünvanı',
        'password' => 'Şifrə',
        'name'     => 'Ad',
    ],
];
```

## RTL Dəstəyi

```html
<!-- Blade layout -->
<html lang="{{ App::getLocale() }}" dir="{{ in_array(App::getLocale(), ['ar', 'fa', 'he']) ? 'rtl' : 'ltr' }}">
```

```css
/* Tailwind CSS RTL */
@import 'tailwindcss';
/* rtl: */ notasyonu ilə direction-specific style-lar */
```

## Translation Management Tools

Böyük layihələrdə `.php`/`.json` fayllarını manual idarə etmək çətindir:
- **[Tolgee](https://tolgee.io/)** — Self-hosted, GitHub Actions inteqrasiyası
- **[Phrase](https://phrase.com/)** — Enterprise, Laravel adapteri var
- **[Crowdin](https://crowdin.com/)** — Open source layihələr üçün pulsuz

```bash
# Spatie Laravel Translation Loader (DB-dən yüklə)
composer require spatie/laravel-translation-loader

# Translation-ları DB-də saxla, admin paneldən edit et
```

## Praktik Baxış

### Trade-off-lar
- **`.php` vs `.json`**: JSON daha sadə, `.php` nested key-lar üçün daha yaxşı. Hybrid: `.php` sistem mesajları, `.json` UI string-ləri.
- **DB translations (Spatie)**: Model content (məqalə, kateqoriya) — DB-dən; UI string-lər — fayl-dan. Onları qarışdırma.
- **Eager vs lazy locale**: Bütün tərcümə faylını yükləmə, yalnız lazım olanı — böyük file olduqda performans fərqi yaranır.

### Common Mistakes
- `App::setLocale()` çağırmağı unutmaq → hər yerdə default locale
- Carbon locale-ni ayrı set etməmək → tarix ingilis qalır
- Hardcoded string-lər kod-da buraxmaq (`"Salam"` yerinə `__('messages.hello')`)
- Spatie Translatable-da `$translatable` array-ini unutmaq → JSON-u string kimi qaytarır
- Validation attribute adları localize etməmək → "The email is required" mesajı çıxır

## Praktik Tapşırıqlar

1. Middleware yaz: `Accept-Language` header-ə görə locale seç, DB-dəki user preference ilə birləşdir
2. `Product` modelinə Spatie Translatable əlavə et: `name`, `description` JSON; admin paneldən AZ/EN/RU tərcümə et
3. Validation mesajlarını Azərbaycanca tərcümə et, `attributes` array-ini doldurun
4. `money()` helper funksiyası yaz: `NumberFormatter` ilə valyuta formatlaşdırması
5. Locale middleware-i test et: `'az'` locale ilə validation mesajları AZ dilindədir

## Əlaqəli Mövzular
- [Laravel Auth & Sanctum](041-laravel-auth-sanctum-passport-jwt.md)
- [Multi-Tenancy](130-multi-tenancy.md)
- [Database Testing Strategies](052-database-testing-strategies.md)
