# Template Engine-lər: Thymeleaf vs Blade

> **Seviyye:** Intermediate ⭐⭐

## Giriş

Hər iki framework server-side rendering üçün öz template engine-lərinə malikdir. Spring ekosistemində **Thymeleaf** defolt template engine kimi istifadə olunur, Laravel-də isə **Blade** daxili (built-in) template engine-dir. Hər ikisi HTML səhifələr yaratmaq, layout-lar qurmaq və dinamik məzmun göstərmək üçün nəzərdə tutulub, lakin yanaşmaları kökündən fərqlidir.

## Spring-də istifadəsi

### Thymeleaf-in əsas fəlsəfəsi: Natural Templates

Thymeleaf-in ən vacib xüsusiyyəti **natural templates** konseptdir. Bu o deməkdir ki, Thymeleaf şablonları server olmadan da brauzerdə düzgün HTML kimi açıla bilir. Bütün Thymeleaf direktivləri HTML atributları kimi yazılır:

```html
<!-- src/main/resources/templates/products.html -->
<!DOCTYPE html>
<html xmlns:th="http://www.thymeleaf.org">
<head>
    <title>Məhsullar</title>
</head>
<body>
    <h1 th:text="${pageTitle}">Defolt Başlıq</h1>

    <!-- th:each - siyahı üzərində dövr -->
    <div th:each="product : ${products}">
        <h3 th:text="${product.name}">Nümunə Məhsul</h3>
        <p th:text="${product.description}">Nümunə açıqlama</p>
        <span th:text="${#numbers.formatDecimal(product.price, 1, 2)} + ' AZN'">
            10.00 AZN
        </span>

        <!-- th:if - şərti göstərmə -->
        <span th:if="${product.inStock}" class="badge badge-success">Stokda var</span>
        <span th:unless="${product.inStock}" class="badge badge-danger">Stokda yoxdur</span>

        <!-- th:switch -->
        <div th:switch="${product.category}">
            <p th:case="'electronics'">Elektronika</p>
            <p th:case="'clothing'">Geyim</p>
            <p th:case="*">Digər</p>
        </div>
    </div>

    <!-- th:each ilə status dəyişəni -->
    <table>
        <tr th:each="product, iterStat : ${products}"
            th:class="${iterStat.odd} ? 'odd-row' : 'even-row'">
            <td th:text="${iterStat.index}">0</td>
            <td th:text="${product.name}">Ad</td>
            <td th:text="${iterStat.first} ? 'İlk element' : ''"></td>
        </tr>
    </table>
</body>
</html>
```

`iterStat` dəyişəni `index`, `count`, `size`, `current`, `even`, `odd`, `first`, `last` kimi xüsusiyyətlər verir.

### View Resolution (Görünüş Həlli)

Spring MVC-də controller-dən qaytarılan string avtomatik olaraq template faylına yönləndirilir:

```java
// Controller
@Controller
public class ProductController {

    @GetMapping("/products")
    public String listProducts(Model model) {
        List<Product> products = productService.findAll();
        model.addAttribute("products", products);
        model.addAttribute("pageTitle", "Bütün Məhsullar");
        // "products" -> src/main/resources/templates/products.html
        return "products";
    }

    @GetMapping("/products/{id}")
    public String showProduct(@PathVariable Long id, Model model) {
        Product product = productService.findById(id);
        model.addAttribute("product", product);
        // "product/detail" -> src/main/resources/templates/product/detail.html
        return "product/detail";
    }
}
```

View resolver konfiqurasiyası `application.properties`-də:

```properties
spring.thymeleaf.prefix=classpath:/templates/
spring.thymeleaf.suffix=.html
spring.thymeleaf.cache=false  # development üçün
spring.thymeleaf.mode=HTML
```

### Layout Dialect ilə Layout Sistemi

Thymeleaf-də layout sistemi üçün əlavə **thymeleaf-layout-dialect** kitabxanası lazımdır:

```xml
<!-- pom.xml -->
<dependency>
    <groupId>nz.net.ultraq.thymeleaf</groupId>
    <artifactId>thymeleaf-layout-dialect</artifactId>
</dependency>
```

```html
<!-- templates/layouts/main.html - Əsas layout -->
<!DOCTYPE html>
<html xmlns:th="http://www.thymeleaf.org"
      xmlns:layout="http://www.ultraq.net.nz/thymeleaf/layout">
<head>
    <meta charset="UTF-8">
    <title layout:title-pattern="$LAYOUT_TITLE - $CONTENT_TITLE">Sayt Adı</title>
    <link rel="stylesheet" th:href="@{/css/main.css}">
    <!-- Əlavə CSS üçün blok -->
    <th:block layout:fragment="extra-css"></th:block>
</head>
<body>
    <nav th:replace="~{fragments/navbar :: navbar}"></nav>

    <div class="container">
        <main layout:fragment="content">
            <p>Defolt məzmun</p>
        </main>
    </div>

    <footer th:replace="~{fragments/footer :: footer}"></footer>

    <script th:src="@{/js/app.js}"></script>
    <th:block layout:fragment="extra-js"></th:block>
</body>
</html>
```

```html
<!-- templates/products.html - Layout-u istifadə edən səhifə -->
<!DOCTYPE html>
<html xmlns:th="http://www.thymeleaf.org"
      xmlns:layout="http://www.ultraq.net.nz/thymeleaf/layout"
      layout:decorate="~{layouts/main}">
<head>
    <title>Məhsullar</title>
</head>
<body>
    <main layout:fragment="content">
        <h1>Məhsullar Siyahısı</h1>
        <div th:each="product : ${products}">
            <p th:text="${product.name}">Məhsul adı</p>
        </div>
    </main>

    <th:block layout:fragment="extra-js">
        <script th:src="@{/js/products.js}"></script>
    </th:block>
</body>
</html>
```

### Fragments (Parçalar)

Thymeleaf-də təkrar istifadə edilən HTML hissələri fragment kimi təyin olunur:

```html
<!-- templates/fragments/navbar.html -->
<nav th:fragment="navbar" class="navbar">
    <a th:href="@{/}">Ana səhifə</a>
    <a th:href="@{/products}">Məhsullar</a>

    <!-- Parametrli fragment -->
    <div th:fragment="userInfo(user)">
        <span th:text="${user.name}">İstifadəçi</span>
        <img th:src="${user.avatar}" alt="avatar">
    </div>
</nav>

<!-- templates/fragments/components.html -->
<!-- Alert komponenti -->
<div th:fragment="alert(type, message)"
     th:class="'alert alert-' + ${type}"
     th:text="${message}">
    Xəbərdarlıq mesajı
</div>

<!-- Kart komponenti -->
<div th:fragment="card(title, content)" class="card">
    <div class="card-header">
        <h3 th:text="${title}">Kart başlığı</h3>
    </div>
    <div class="card-body" th:utext="${content}">
        Kart məzmunu
    </div>
</div>
```

Fragment-ləri istifadə etmək üçün üç üsul var:

```html
<!-- th:insert - fragmenti elementin içinə əlavə edir -->
<div th:insert="~{fragments/navbar :: navbar}"></div>

<!-- th:replace - elementi fragment ilə əvəz edir -->
<div th:replace="~{fragments/navbar :: navbar}"></div>

<!-- th:include (köhnəlmiş) - fragmentin məzmununu əlavə edir -->
<div th:include="~{fragments/navbar :: navbar}"></div>

<!-- Parametrli fragment çağırışı -->
<div th:replace="~{fragments/components :: alert('warning', 'Diqqət!')}"></div>
<div th:replace="~{fragments/components :: card('Başlıq', '<p>Məzmun</p>')}"></div>
```

### Thymeleaf-də form işləmə

```html
<form th:action="@{/products}" th:object="${productForm}" method="post">
    <!-- CSRF token avtomatik əlavə olunur -->

    <div>
        <label for="name">Ad:</label>
        <input type="text" th:field="*{name}">
        <span th:if="${#fields.hasErrors('name')}"
              th:errors="*{name}" class="error">Ad xətası</span>
    </div>

    <div>
        <label>Kateqoriya:</label>
        <select th:field="*{category}">
            <option value="">Seçin</option>
            <option th:each="cat : ${categories}"
                    th:value="${cat.id}"
                    th:text="${cat.name}">Kateqoriya</option>
        </select>
    </div>

    <button type="submit">Göndər</button>
</form>
```

### URL ifadələri

```html
<!-- Kontekst-nisbi URL -->
<a th:href="@{/products}">Məhsullar</a>

<!-- Parametrli URL -->
<a th:href="@{/products/{id}(id=${product.id})}">Detallara bax</a>

<!-- Query parametrləri -->
<a th:href="@{/products(page=${currentPage}, size=10)}">Səhifə</a>

<!-- Protokol-nisbi URL -->
<script th:src="@{//cdn.example.com/lib.js}"></script>
```

## Laravel-də istifadəsi

### Blade-in əsas fəlsəfəsi: Sadəlik və ifadəlilik

Blade PHP-yə yaxın, lakin daha təmiz sintaksis təklif edir. Blade direktiv-əsaslı yanaşma istifadə edir (Thymeleaf-dən fərqli olaraq atribut-əsaslı deyil):

```php
{{-- resources/views/products/index.blade.php --}}

@extends('layouts.app')

@section('title', 'Məhsullar')

@section('content')
    <h1>{{ $pageTitle }}</h1>

    {{-- @foreach - siyahı üzərində dövr --}}
    @foreach($products as $product)
        <div class="product-card">
            <h3>{{ $product->name }}</h3>
            <p>{{ $product->description }}</p>
            <span>{{ number_format($product->price, 2) }} AZN</span>

            {{-- @if - şərti göstərmə --}}
            @if($product->in_stock)
                <span class="badge badge-success">Stokda var</span>
            @else
                <span class="badge badge-danger">Stokda yoxdur</span>
            @endif

            {{-- @switch --}}
            @switch($product->category)
                @case('electronics')
                    <p>Elektronika</p>
                    @break
                @case('clothing')
                    <p>Geyim</p>
                    @break
                @default
                    <p>Digər</p>
            @endswitch
        </div>
    @endforeach

    {{-- @forelse - boş siyahı üçün --}}
    @forelse($products as $product)
        <div>{{ $product->name }}</div>
    @empty
        <p>Heç bir məhsul tapılmadı.</p>
    @endforelse

    {{-- $loop dəyişəni --}}
    @foreach($products as $product)
        <div class="{{ $loop->odd ? 'odd-row' : 'even-row' }}">
            <span>{{ $loop->iteration }}.</span>
            <span>{{ $product->name }}</span>
            @if($loop->first) <span>(İlk element)</span> @endif
            @if($loop->last) <span>(Son element)</span> @endif
        </div>
    @endforeach
@endsection

@section('extra-js')
    <script src="{{ asset('js/products.js') }}"></script>
@endsection
```

Blade-in `$loop` dəyişəni: `index`, `iteration`, `remaining`, `count`, `first`, `last`, `even`, `odd`, `depth`, `parent`.

### Layout sistemi: @extends, @section, @yield

Blade-də layout sistemi daxili (built-in) xüsusiyyətdir, əlavə paket lazım deyil:

```php
{{-- resources/views/layouts/app.blade.php - Əsas layout --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'Sayt Adı') - Sayt Adı</title>
    <link rel="stylesheet" href="{{ asset('css/main.css') }}">
    @stack('styles')
</head>
<body>
    @include('partials.navbar')

    <div class="container">
        @yield('content')
    </div>

    @include('partials.footer')

    <script src="{{ asset('js/app.js') }}"></script>
    @stack('scripts')
</body>
</html>
```

```php
{{-- resources/views/products/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Məhsullar')

@section('content')
    <h1>Məhsullar Siyahısı</h1>
    @foreach($products as $product)
        <p>{{ $product->name }}</p>
    @endforeach
@endsection

@push('scripts')
    <script src="{{ asset('js/products.js') }}"></script>
@endpush

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/products.css') }}">
@endpush
```

`@yield` layout-da yer ayırır, `@section` həmin yeri doldurur. `@stack` / `@push` isə yığma (stacking) mexanizmi təqdim edir - bir neçə yerdən eyni stack-ə əlavə etmək mümkündür.

### Blade Components (Komponentlər)

Laravel-in ən güclü xüsusiyyətlərindən biri class-əsaslı komponentlərdir:

```php
// app/View/Components/Alert.php
namespace App\View\Components;

use Illuminate\View\Component;

class Alert extends Component
{
    public string $type;
    public string $message;

    public function __construct(string $type = 'info', string $message = '')
    {
        $this->type = $type;
        $this->message = $message;
    }

    public function alertClass(): string
    {
        return "alert alert-{$this->type}";
    }

    public function render()
    {
        return view('components.alert');
    }
}
```

```php
{{-- resources/views/components/alert.blade.php --}}
<div class="{{ $alertClass() }}" {{ $attributes }}>
    {{ $message }}
    {{ $slot }}
</div>
```

```php
{{-- İstifadəsi --}}
<x-alert type="warning" message="Diqqət edin!" />

<x-alert type="success">
    <strong>Uğurlu!</strong> Məhsul əlavə edildi.
</x-alert>

{{-- Atributları ötürmək --}}
<x-alert type="danger" class="mt-4" id="main-alert">
    Xəta baş verdi.
</x-alert>
```

### Anonymous Components (Anonim Komponentlər)

PHP sinfi olmadan, yalnız Blade faylı ilə komponent yaratmaq mümkündür:

```php
{{-- resources/views/components/card.blade.php --}}
@props([
    'title' => 'Defolt başlıq',
    'footer' => null
])

<div {{ $attributes->merge(['class' => 'card']) }}>
    <div class="card-header">
        <h3>{{ $title }}</h3>
    </div>
    <div class="card-body">
        {{ $slot }}
    </div>
    @if($footer)
        <div class="card-footer">
            {{ $footer }}
        </div>
    @endif
</div>
```

```php
{{-- İstifadəsi --}}
<x-card title="Məhsul Məlumatları" class="shadow-lg">
    <p>Bu kart məzmunudur.</p>

    <x-slot:footer>
        <button>Yadda saxla</button>
    </x-slot:footer>
</x-card>
```

### @include ilə Parçalar

```php
{{-- resources/views/partials/navbar.blade.php --}}
<nav class="navbar">
    <a href="{{ route('home') }}">Ana səhifə</a>
    <a href="{{ route('products.index') }}">Məhsullar</a>

    @auth
        <span>{{ auth()->user()->name }}</span>
    @endauth

    @guest
        <a href="{{ route('login') }}">Daxil ol</a>
    @endguest
</nav>
```

```php
{{-- İstifadəsi --}}
@include('partials.navbar')

{{-- Dəyişən ötürmə ilə --}}
@include('partials.product-card', ['product' => $featuredProduct])

{{-- Şərti include --}}
@includeWhen($user->isAdmin(), 'partials.admin-panel')
@includeUnless($user->isBanned(), 'partials.comment-form')

{{-- Mövcudluq yoxlaması ilə --}}
@includeIf('partials.optional-section')

{{-- Kolleksiya üçün --}}
@each('partials.product-card', $products, 'product', 'partials.no-products')
```

### Inline Blade Templates

Controller-dən birbaşa Blade şablonu render etmək:

```php
// Controller-də
use Illuminate\Support\Facades\Blade;

public function preview()
{
    $html = Blade::render('
        <h1>{{ $title }}</h1>
        @foreach($items as $item)
            <p>{{ $item }}</p>
        @endforeach
    ', [
        'title' => 'Önizləmə',
        'items' => ['Element 1', 'Element 2']
    ]);

    return $html;
}
```

### Blade-in əlavə direktivləri

```php
{{-- XSS-dən qorunmayan çıxış --}}
{!! $htmlContent !!}

{{-- XSS-dən qorunan çıxış (defolt) --}}
{{ $userInput }}

{{-- @auth / @guest --}}
@auth
    <p>Xoş gəldiniz, {{ auth()->user()->name }}</p>
@endauth

{{-- @env --}}
@env('local')
    <p>Bu development mühitidir</p>
@endenv

{{-- @production --}}
@production
    <script src="{{ asset('js/analytics.js') }}"></script>
@endproduction

{{-- Custom Blade direktivləri --}}
// AppServiceProvider-da:
Blade::directive('money', function ($expression) {
    return "<?php echo number_format($expression, 2) . ' AZN'; ?>";
});

// Şablonda:
@money($product->price)

{{-- @once - yalnız bir dəfə render --}}
@once
    <script src="{{ asset('js/chart.js') }}"></script>
@endonce
```

## Əsas fərqlər

| Xüsusiyyət | Spring Thymeleaf | Laravel Blade |
|---|---|---|
| Sintaksis növü | HTML atributları (`th:text`, `th:if`) | Direktiv əsaslı (`@if`, `@foreach`) |
| Natural templates | Bəli - brauzerdə düz HTML kimi açılır | Xeyr - server lazımdır |
| Layout sistemi | Əlavə dialect lazım (layout-dialect) | Daxili (`@extends`, `@yield`) |
| Komponentlər | Fragment-lər (məhdud) | Tam komponent sistemi (class + anonim) |
| Slot dəstəyi | Yoxdur (fragment parametrləri ilə) | Tam dəstək (`$slot`, named slots) |
| Şərti include | Yoxdur (th:if ilə əl ilə) | `@includeWhen`, `@includeUnless`, `@includeIf` |
| Stack mexanizmi | Yoxdur | `@push` / `@stack` |
| Custom direktivlər | Dialect yazmaq lazım (mürəkkəb) | `Blade::directive()` (sadə) |
| Çıxış escaping | `th:text` (escaped), `th:utext` (unescaped) | `{{ }}` (escaped), `{!! !!}` (unescaped) |
| Loop dəyişəni | `iterStat` (məhdud) | `$loop` (zəngin: depth, parent) |
| `@forelse` ekvivalenti | Yoxdur - `th:if` + `th:each` birlikdə | Daxili `@forelse` / `@empty` |
| İnline render | Mürəkkəb konfiqurasiya | `Blade::render()` sadə |
| Kompilyasiya | Hər request-də (cache ilə) | PHP fayllarına kompilyasiya olunur |

## Niyə belə fərqlər var?

### Thymeleaf: Natural Templates fəlsəfəsi

Thymeleaf "natural templates" konsepti ilə yaranıb. Əsas ideya budur ki, HTML şablonları backend developer olmadan da, frontend developer tərəfindən brauzerdə açılıb baxıla bilsin. Buna görə bütün Thymeleaf ifadələri HTML atributları kimi yazılır - brauzer tanımadığı atributları sadəcə görməzdən gəlir. Bu, Java dünyasının **JSP-nin çirkin `<% %>` tag-lərindən** uzaqlaşmaq istəyindən qaynaqlanır.

### Blade: PHP-nin gücünü sadələşdirmək

Blade isə PHP-nin artıq bir template dili olmasından yararlanır. PHP özü HTML içində işləyə bilir (`<?php echo $x; ?>`), lakin bu sintaksis çox uzundur. Blade bunu `{{ $x }}` kimi qısaldır və `@if`, `@foreach` kimi direktivlərlə PHP-nin `<?php if(): ?>` sintaksisini əvəz edir. Blade faylları əslində PHP-yə kompilyasiya olunur və cache-lənir, bu da performansı artırır.

### Komponent sistemi fərqi

Laravel-in komponent sistemi React/Vue kimi frontend framework-lərdən ilham alıb. `<x-alert>` sintaksisi HTML custom element-lərinə bənzəyir. Thymeleaf-də isə fragment-lər var, lakin bunlar tam mənada komponent deyil - onların öz state-i, slot-ları və atribut birləşdirməsi (attribute merging) yoxdur.

### Layout yanaşması

Thymeleaf-in layout sistemi üçüncü tərəf dialect tələb edir, çünki Thymeleaf modular arxitektura ilə dizayn edilib - əsas motor minimaldır, funksionallıq dialect-lar vasitəsilə əlavə olunur. Blade-də isə layout sistemi framework-ün əsas hissəsidir, çünki Laravel "batteries included" (hər şey daxildir) fəlsəfəsinə əsaslanır.

## Hansı framework-də var, hansında yoxdur?

### Yalnız Blade-də olan xüsusiyyətlər:
- **`@forelse` / `@empty`** - boş kolleksiya üçün xüsusi blok
- **`@push` / `@stack`** - CSS/JS yığma mexanizmi
- **`@auth` / `@guest`** - autentifikasiya yoxlaması birbaşa şablonda
- **`@env` / `@production`** - mühit yoxlaması
- **`@once`** - yalnız bir dəfə render etmə
- **Named slots** - komponentlərdə adlandırılmış slot-lar
- **Anonymous components** - PHP sinfi olmadan komponent
- **`$loop->parent`** - iç-içə dövrlərdə valideyn loop-a müraciət
- **`@each`** - kolleksiya üçün partial render
- **`Blade::directive()`** - sadə custom direktiv yaratma

### Yalnız Thymeleaf-də olan xüsusiyyətlər:
- **Natural templates** - server olmadan brauzerdə baxmaq imkanı
- **`th:object` + `*{field}`** - form binding (Spring MVC ilə inteqrasiya)
- **`@{/url}` ifadələri** - kontekst-nisbi URL yaratma (deployment path-ını avtomatik əlavə edir)
- **`#dates`, `#numbers`, `#strings`** - utility obyektləri birbaşa şablonda
- **Spring Security dialect** - `sec:authorize` ilə rol yoxlaması
- **Dialect genişlənmə sistemi** - tamamilə yeni atribut prosessorları yazmaq imkanı
