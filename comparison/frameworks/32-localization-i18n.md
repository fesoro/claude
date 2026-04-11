# Lokallaşdırma və Beynəlmiləlləşdirmə (i18n): Spring vs Laravel

## Giriş

Çoxdilli tətbiqlər qurmaq müasir web development-in vacib hissəsidir. Həm Spring, həm də Laravel lokallaşdırma (localization - l10n) və beynəlmiləlləşdirmə (internationalization - i18n) üçün daxili dəstək təklif edir. Spring **MessageSource** interfeysi və `.properties` faylları ilə işləyir, Laravel isə **Lang** facade-i, `__()` funksiyası və JSON/PHP fayl formatları ilə işləyir.

## Spring-də istifadəsi

### MessageSource konfiqurasiyası

Spring-də lokallaşdırma `MessageSource` interfeysi üzərindən idarə olunur:

```java
// Konfiqurasiya sinfi
@Configuration
public class LocaleConfig {

    @Bean
    public MessageSource messageSource() {
        ResourceBundleMessageSource source = new ResourceBundleMessageSource();
        source.setBasenames("messages", "validation");
        source.setDefaultEncoding("UTF-8");
        source.setFallbackToSystemLocale(false);
        // Defolt dil tapılmadıqda boş string qaytarma
        source.setUseCodeAsDefaultMessage(true);
        return source;
    }

    @Bean
    public LocaleResolver localeResolver() {
        SessionLocaleResolver resolver = new SessionLocaleResolver();
        resolver.setDefaultLocale(Locale.forLanguageTag("az"));
        return resolver;
    }

    // Dil dəyişdirmək üçün interceptor
    @Bean
    public LocaleChangeInterceptor localeChangeInterceptor() {
        LocaleChangeInterceptor interceptor = new LocaleChangeInterceptor();
        interceptor.setParamName("lang"); // ?lang=en
        return interceptor;
    }

    @Override
    public void addInterceptors(InterceptorRegistry registry) {
        registry.addInterceptor(localeChangeInterceptor());
    }
}
```

### Messages.properties faylları

```properties
# src/main/resources/messages.properties (defolt - az)
app.name=Məhsul Mağazası
welcome.message=Xoş gəldiniz, {0}!
product.count=Cəmi {0} məhsul tapıldı
product.name=Məhsulun adı
product.price=Qiymət
product.not_found=Məhsul tapılmadı
nav.home=Ana Səhifə
nav.products=Məhsullar
nav.about=Haqqında
```

```properties
# src/main/resources/messages_en.properties
app.name=Product Store
welcome.message=Welcome, {0}!
product.count=Total {0} products found
product.name=Product Name
product.price=Price
product.not_found=Product not found
nav.home=Home
nav.products=Products
nav.about=About
```

```properties
# src/main/resources/messages_ru.properties
app.name=Магазин Продуктов
welcome.message=Добро пожаловать, {0}!
product.count=Всего найдено {0} продуктов
product.name=Название продукта
product.price=Цена
product.not_found=Продукт не найден
nav.home=Главная
nav.products=Продукты
nav.about=О нас
```

### Controller-də istifadə

```java
@Controller
public class ProductController {

    @Autowired
    private MessageSource messageSource;

    @GetMapping("/products")
    public String list(Model model, Locale locale) {
        List<Product> products = productService.findAll();
        model.addAttribute("products", products);

        // Proqramatik şəkildə mesaj almaq
        String welcome = messageSource.getMessage(
            "welcome.message",
            new Object[]{"Orxan"},  // {0} parametri
            locale
        );
        model.addAttribute("welcomeMsg", welcome);

        // Parametrli mesaj
        String count = messageSource.getMessage(
            "product.count",
            new Object[]{products.size()},
            locale
        );
        model.addAttribute("countMsg", count);

        return "products/list";
    }
}
```

### Thymeleaf şablonlarında istifadə

```html
<!-- templates/products/list.html -->
<html xmlns:th="http://www.thymeleaf.org">
<body>
    <!-- #{} ilə mesaj açarı -->
    <h1 th:text="#{app.name}">Sayt Adı</h1>

    <!-- Parametrli mesaj -->
    <p th:text="#{welcome.message('Orxan')}">Xoş gəldiniz</p>
    <p th:text="#{product.count(${products.size()})}">Məhsul sayı</p>

    <nav>
        <a th:text="#{nav.home}" th:href="@{/}">Ana Səhifə</a>
        <a th:text="#{nav.products}" th:href="@{/products}">Məhsullar</a>
    </nav>

    <!-- Dil dəyişdirmə linklər -->
    <a th:href="@{/products(lang='az')}">AZ</a>
    <a th:href="@{/products(lang='en')}">EN</a>
    <a th:href="@{/products(lang='ru')}">RU</a>

    <table>
        <tr>
            <th th:text="#{product.name}">Ad</th>
            <th th:text="#{product.price}">Qiymət</th>
        </tr>
        <tr th:each="product : ${products}">
            <td th:text="${product.name}">Nümunə</td>
            <td th:text="${product.price}">0.00</td>
        </tr>
    </table>
</body>
</html>
```

### @RequestHeader ilə locale təyini

```java
@RestController
@RequestMapping("/api/products")
public class ProductApiController {

    @Autowired
    private MessageSource messageSource;

    @GetMapping("/{id}")
    public ResponseEntity<?> getProduct(
            @PathVariable Long id,
            @RequestHeader(value = "Accept-Language", defaultValue = "az") String lang) {

        Locale locale = Locale.forLanguageTag(lang);
        Product product = productService.findById(id);

        if (product == null) {
            String msg = messageSource.getMessage("product.not_found", null, locale);
            return ResponseEntity.status(404).body(Map.of("error", msg));
        }

        return ResponseEntity.ok(product);
    }
}
```

### Müxtəlif LocaleResolver növləri

```java
// 1. Session əsaslı (ən çox istifadə olunan)
@Bean
public LocaleResolver localeResolver() {
    SessionLocaleResolver resolver = new SessionLocaleResolver();
    resolver.setDefaultLocale(new Locale("az"));
    return resolver;
}

// 2. Cookie əsaslı
@Bean
public LocaleResolver localeResolver() {
    CookieLocaleResolver resolver = new CookieLocaleResolver();
    resolver.setDefaultLocale(new Locale("az"));
    resolver.setCookieName("lang");
    resolver.setCookieMaxAge(3600 * 24 * 30); // 30 gün
    return resolver;
}

// 3. Accept-Language header əsaslı (dəyişdirilə bilməz)
@Bean
public LocaleResolver localeResolver() {
    AcceptHeaderLocaleResolver resolver = new AcceptHeaderLocaleResolver();
    resolver.setDefaultLocale(new Locale("az"));
    resolver.setSupportedLocales(List.of(
        new Locale("az"),
        new Locale("en"),
        new Locale("ru")
    ));
    return resolver;
}
```

### Validation mesajları

```properties
# src/main/resources/ValidationMessages.properties (defolt)
javax.validation.constraints.NotBlank.message=Bu sahə boş ola bilməz
javax.validation.constraints.Size.message=Uzunluq {min} ilə {max} arasında olmalıdır
javax.validation.constraints.Email.message=Düzgün e-poçt ünvanı daxil edin

# src/main/resources/ValidationMessages_en.properties
javax.validation.constraints.NotBlank.message=This field cannot be blank
javax.validation.constraints.Size.message=Size must be between {min} and {max}
javax.validation.constraints.Email.message=Please enter a valid email address
```

## Laravel-də istifadəsi

### Dil faylları

Laravel iki formatda dil faylı dəstəkləyir: PHP array və JSON.

**PHP formatı** - mürəkkəb açar strukturları üçün:

```php
// lang/az/messages.php
return [
    'welcome' => 'Xoş gəldiniz, :name!',
    'product' => [
        'count' => 'Cəmi :count məhsul tapıldı',
        'name' => 'Məhsulun adı',
        'price' => 'Qiymət',
        'not_found' => 'Məhsul tapılmadı',
    ],
    'nav' => [
        'home' => 'Ana Səhifə',
        'products' => 'Məhsullar',
        'about' => 'Haqqında',
    ],
];

// lang/en/messages.php
return [
    'welcome' => 'Welcome, :name!',
    'product' => [
        'count' => 'Total :count products found',
        'name' => 'Product Name',
        'price' => 'Price',
        'not_found' => 'Product not found',
    ],
    'nav' => [
        'home' => 'Home',
        'products' => 'Products',
        'about' => 'About',
    ],
];
```

**JSON formatı** - sadə tərcümələr üçün (mənbə dil cümlə kimi istifadə olunur):

```json
// lang/az.json
{
    "Welcome to our store": "Mağazamıza xoş gəldiniz",
    "Add to cart": "Səbətə əlavə et",
    "Your order has been placed": "Sifarişiniz qəbul edildi",
    "No products found": "Heç bir məhsul tapılmadı"
}

// lang/en.json
{
    "Welcome to our store": "Welcome to our store",
    "Add to cart": "Add to cart"
}

// lang/ru.json
{
    "Welcome to our store": "Добро пожаловать в наш магазин",
    "Add to cart": "Добавить в корзину",
    "Your order has been placed": "Ваш заказ оформлен",
    "No products found": "Продукты не найдены"
}
```

### Controller-də istifadə

```php
class ProductController extends Controller
{
    public function index()
    {
        $products = Product::all();

        return view('products.index', [
            'products' => $products,
            // trans() funksiyası
            'welcomeMsg' => trans('messages.welcome', ['name' => 'Orxan']),
            // __() helper funksiyası (eyni işi görür)
            'countMsg' => __('messages.product.count', ['count' => $products->count()]),
        ]);
    }

    public function show(Product $product)
    {
        if (!$product) {
            // JSON faylından tərcümə
            abort(404, __('No products found'));
        }

        return view('products.show', compact('product'));
    }
}
```

### Blade şablonlarında istifadə

```php
{{-- resources/views/products/index.blade.php --}}
@extends('layouts.app')

@section('content')
    <h1>{{ __('messages.welcome', ['name' => auth()->user()->name]) }}</h1>

    {{-- @lang direktivsi --}}
    <p>@lang('messages.product.count', ['count' => $products->count()])</p>

    <nav>
        <a href="{{ route('home') }}">{{ __('messages.nav.home') }}</a>
        <a href="{{ route('products.index') }}">{{ __('messages.nav.products') }}</a>
    </nav>

    {{-- JSON faylından tərcümə --}}
    <button>{{ __('Add to cart') }}</button>

    {{-- Dil dəyişdirmə --}}
    <a href="{{ route('locale.change', 'az') }}">AZ</a>
    <a href="{{ route('locale.change', 'en') }}">EN</a>
    <a href="{{ route('locale.change', 'ru') }}">RU</a>
@endsection
```

### Pluralization (Cəm formaları)

Laravel-in ən güclü lokallaşdırma xüsusiyyətlərindən biri cəm formalarını idarə etməkdir:

```php
// lang/az/messages.php
return [
    'products_found' => '{0} Heç bir məhsul tapılmadı|{1} :count məhsul tapıldı|[2,*] :count məhsul tapıldı',

    'apples' => '{0} Alma yoxdur|{1} Bir alma var|[2,4] :count alma var|[5,*] :count alma var',

    'minutes_ago' => '{1} bir dəqiqə əvvəl|[2,*] :count dəqiqə əvvəl',
];

// lang/ru/messages.php
return [
    // Rus dilində 3 cəm forması var
    'products_found' => '{0} Продуктов не найдено|{1} Найден :count продукт|[2,4] Найдено :count продукта|[5,*] Найдено :count продуктов',
];
```

```php
// İstifadəsi
echo trans_choice('messages.products_found', 0);
// "Heç bir məhsul tapılmadı"

echo trans_choice('messages.products_found', 1, ['count' => 1]);
// "1 məhsul tapıldı"

echo trans_choice('messages.products_found', 15, ['count' => 15]);
// "15 məhsul tapıldı"

echo trans_choice('messages.apples', 3, ['count' => 3]);
// "3 alma var"
```

### Locale Middleware

```php
// app/Http/Middleware/SetLocale.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        // 1. Session-dan yoxla
        if ($request->session()->has('locale')) {
            $locale = $request->session()->get('locale');
        }
        // 2. URL prefiksindən yoxla
        elseif ($request->segment(1) && in_array($request->segment(1), ['az', 'en', 'ru'])) {
            $locale = $request->segment(1);
        }
        // 3. Brauzer dilindən yoxla
        elseif ($request->hasHeader('Accept-Language')) {
            $locale = $request->getPreferredLanguage(['az', 'en', 'ru']);
        }
        // 4. Defolt
        else {
            $locale = config('app.locale');
        }

        app()->setLocale($locale);

        return $next($request);
    }
}

// Dil dəyişdirmə route
Route::get('/locale/{locale}', function (string $locale) {
    if (in_array($locale, ['az', 'en', 'ru'])) {
        session()->put('locale', $locale);
    }
    return redirect()->back();
})->name('locale.change');
```

### Carbon Localization (Tarix lokallaşdırma)

```php
use Carbon\Carbon;

// Carbon-u Azərbaycan dilinə keçirmək
Carbon::setLocale('az');

$date = Carbon::now();
echo $date->translatedFormat('l, d F Y');
// "Şənbə, 11 Aprel 2026"

echo $date->diffForHumans();
// "bir neçə saniyə əvvəl"

$pastDate = Carbon::now()->subDays(3);
echo $pastDate->diffForHumans();
// "3 gün əvvəl"

$futureDate = Carbon::now()->addMonths(2);
echo $futureDate->diffForHumans();
// "2 ay sonra"

// Müxtəlif dillər
echo $date->locale('ru')->translatedFormat('l, d F Y');
// "Суббота, 11 Апрель 2026"

echo $date->locale('en')->translatedFormat('l, d F Y');
// "Saturday, 11 April 2026"
```

### Validation mesajlarının lokallaşdırılması

```php
// lang/az/validation.php
return [
    'required' => ':attribute sahəsi mütləqdir.',
    'email' => ':attribute düzgün e-poçt ünvanı olmalıdır.',
    'min' => [
        'string' => ':attribute ən azı :min simvol olmalıdır.',
        'numeric' => ':attribute ən azı :min olmalıdır.',
    ],
    'max' => [
        'string' => ':attribute :max simvoldan çox ola bilməz.',
    ],
    'unique' => 'Bu :attribute artıq istifadə olunur.',

    // Xüsusi atribut adları
    'attributes' => [
        'email' => 'e-poçt',
        'password' => 'şifrə',
        'name' => 'ad',
        'phone' => 'telefon nömrəsi',
    ],
];
```

## Əsas fərqlər

| Xüsusiyyət | Spring | Laravel |
|---|---|---|
| Fayl formatı | `.properties` (key=value) | PHP array və JSON |
| Parametr sintaksisi | `{0}`, `{1}` (indeks əsaslı) | `:name` (ad əsaslı) |
| Açar strukturu | Düz (dot ilə manual) | İç-içə array + dot notation |
| Pluralization | Daxili yoxdur (ICU MessageFormat ilə) | Daxili (`trans_choice`, `\|` sintaksisi) |
| Tarix lokallaşdırma | `java.time` + ResourceBundle (əl ilə) | Carbon daxili dəstək |
| JSON dəstəyi | Yoxdur (defolt olaraq) | Daxili JSON fayllar |
| Defolt dil faylları | Yaratmaq lazım | Framework tərəfindən verilir (validation, pagination) |
| Template sintaksisi | `#{key}` (Thymeleaf) | `__('key')` / `@lang('key')` |
| Locale resolver | SessionLocaleResolver, CookieLocaleResolver, AcceptHeader | Middleware ilə manual |
| Helper funksiyalar | Yoxdur (MessageSource inject) | `__()`, `trans()`, `trans_choice()` |

## Niyə belə fərqlər var?

### Parametr yanaşması

Spring Java-nın `MessageFormat` sinfindən istifadə edir ki, bu da indeks əsaslı parametrlər (`{0}`, `{1}`) işlədir. Bu Java-nın ümumi yanaşmasıdır - `String.format()` kimi. Laravel isə ad əsaslı parametrlər (`:name`, `:count`) istifadə edir ki, bu da oxunaqlılığı artırır. Tərcüməçilər üçün `:name` görmək `{0}`-dan daha aydındır.

### Pluralization

Laravel PHP-nin `Symfony Translation` komponentindən istifadə edir və ICU pluralization qaydalarını sadələşdirmiş formada təqdim edir. Pipe (`|`) sintaksisi ilə fərqli cəm formaları təyin etmək çox asandır. Spring-də isə pluralization üçün ICU4J kitabxanası əlavə etmək və ya `ChoiceFormat` istifadə etmək lazımdır ki, bu da daha mürəkkəbdir.

### Fayl formatı

`.properties` faylları Java ekosisteminin standart konfiqurasiya formatıdır - sadə, lakin iç-içə strukturu dəstəkləmir. Laravel PHP array istifadə edir ki, bu da iç-içə açarları (`messages.product.name`) təbii şəkildə dəstəkləyir. JSON formatı isə SPA (Single Page Application) tətbiqlər üçün əlavə edilib - frontend JavaScript-dən birbaşa istifadə oluna bilir.

### Carbon və tarix lokallaşdırma

Laravel-in Carbon kitabxanası tarix/vaxt üçün 100+ dilin tərcüməsini daxilində gətirir. `diffForHumans()` kimi metodlar avtomatik olaraq seçilmiş dilə uyğun nəticə verir. Java-da isə `java.time` API tarix formatlaşdırmasını dəstəkləyir, lakin "3 gün əvvəl" kimi nisbi zaman ifadələri üçün əlavə iş görmək lazımdır.

## Hansı framework-də var, hansında yoxdur?

### Yalnız Laravel-də olan xüsusiyyətlər:
- **JSON formatında tərcümə faylları** - frontend ilə paylaşmaq asan
- **Daxili pluralization** (`trans_choice`) - əlavə kitabxana olmadan
- **Carbon lokallaşdırma** - tarix/vaxt üçün hazır tərcümələr
- **Mənbə cümlə açar kimi** - `__('Add to cart')` JSON fayllardan tərcümə axtarır
- **Defolt validation tərcümələri** - framework özü validation mesajlarını gətirir
- **`@lang` Blade direktivsi** - şablonlarda qısa sintaksis

### Yalnız Spring-də olan xüsusiyyətlər:
- **Müxtəlif LocaleResolver növləri** - Session, Cookie, AcceptHeader, FixedLocaleResolver
- **`LocaleChangeInterceptor`** - URL parametri ilə dil dəyişdirmə üçün hazır interceptor
- **Validation mesajları `{min}`, `{max}` interpolasiyası** - Bean Validation standartı ilə
- **`ReloadableResourceBundleMessageSource`** - fayllar dəyişdikdə avtomatik yenidən yükləmə (development üçün faydalı)
- **MessageSource hiyerarxiyası** - parent MessageSource ilə fallback zənciri
