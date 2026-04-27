# TDD Approach (Senior ⭐⭐⭐)

## İcmal
Test-Driven Development (TDD) — kodu yazmadan əvvəl test yazma disiplinidir. Red-Green-Refactor siklindən ibarət bu yanaşma yalnız test metodologiyası deyil, dizayn alətidir. Kent Beck tərəfindən "Extreme Programming"-in bir hissəsi kimi popularlaşdırılmışdır. Interview-larda senior səviyyədə gəlir — TDD-ni bilmək asan, amma real layihədə tətbiq etmək fərqli bir yetkinlik tələb edir.

## Niyə Vacibdir
TDD-ni interviewer soruşanda axtardığı "TDD nədir?" sualının cavabı deyil — "TDD-ni nə vaxt tətbiq edirsiniz, nə vaxt etmirsiniz, real dünyada nə ilə üzləşdiniz?" sualının cavabıdır. TDD-ni bilən developer daha testable kod yazır, interface-first düşünür, over-engineering-dən qaçır, dizayn feedback-ini testi yazarkən alır. Bu bacarıqlar senior engineer-in əsas kompetensiyalarındandır.

## Əsas Anlayışlar

- **Red-Green-Refactor sikli**: RED — uğursuz test yaz (hələ kod yoxdur, compile belə olmaya bilər). GREEN — yalnız testi keçirəcək minimum kod yaz (mükəmməl olmaya bilər). REFACTOR — kodu təmizlə, testi yenidən keçir.

- **RED mərhələsinin məqsədi**: Tələbi executable specification kimi ifadə et. Test yazarkən API-ni dizayn edirsən — "bu metodu necə çağırmaq istəyirəm?" Bu sual interface dizaynını formalaşdırır.

- **GREEN mərhələsinin məqsədi**: YAGNI prinsipi — "You Aren't Gonna Need It". Testi keçirəcək minimum kod. Over-engineer etmə. `return 42` belə qəbul edilir, əgər test bu dəyəri gözləyirsə. Sonra REFACTOR edir.

- **REFACTOR mərhələsinin məqsədi**: Davranışı dəyişmədən strukturu yaxşılaşdır. Test sənin safety net-indir — test yaşıl qalırsa refactoring düzgündür.

- **TDD-nin real faydaları**: Design feedback — test yazmaq çətin olduqda dizayn problemi var. Living documentation — testlər sistemin nə etdiyini göstərir. Regression safety — refactoring zamanı test suite yaşıl qalır. Scope creep qarşısı — testi yazmaq tələbi kristallaşdırır.

- **TDD-nin real məhdudiyyətləri**: UI development üçün çətin (snapshot testing lazımdır). Exploratory coding zamanı zərərlidir — prototyping mərhələsini yavaşladır. Legacy codebase-ə əlavə çətin. Yavaş feedback loop bəzən. Junior developer üçün learning curve.

- **TDD vs Test-First vs Test-After**: TDD — koddan əvvəl (klassik). Test-First — tələb aydınlaşandan sonra, implementasiyadan əvvəl. Test-After — kod yazıldıqdan sonra (ən geniş yayılmış, amma TDD deyil).

- **Outside-In TDD (London School / Mockist)**: Yüksək səviyyəli testdən başla (acceptance/integration), aşağıya doğru get. Dependency-lər mock edilir. Üstünlük: interface-first, top-down design. Çatışmazlıq: çox mock → brittle testlər.

- **Inside-Out TDD (Chicago School / Classicist)**: Aşağı səviyyəli unit testdən başla, yuxarıya doğru inşa et. Mock minimuma endirilir. Üstünlük: real behavior, az mock. Çatışmazlıq: top-level design geç aydınlaşır.

- **BDD (Behavior-Driven Development)**: TDD + business language. Given/When/Then formatı. Non-technical stakeholder-larla kommunikasiyaya kömək edir. Behat (PHP), Cucumber (Java/Ruby), Gherkin syntax. TDD-nin extension-u — eyni sikl.

- **TDD — dizayn aləti**: Test yazmaq çətin olduqda bu bir dizayn siqnalıdır. Çox dependency varsa — SRP pozulub. Constructor-da çox parameter — Extract Class lazım. Setup uzundursa — class çox məsuliyyət daşıyır.

- **Testable Design prinsipləri**: Single Responsibility — test çətin olarsa class çox şey edir. Dependency Injection — constructor injection olmadan mock etmək mümkün deyil. Interface Segregation — mock etmək üçün interface lazımdır. Pure Functions — side effect-ləri azaltmaq test-ability-ni artırır.

- **TDD-nin sürəti**: Başlanğıcda TDD-siz kod yazıb sonra test yazmaq görünür sürətlidiр. Real sürət: TDD ilə yazılan kod production-a daha tez çıxır — debug vaxtı azalır, regression az olur.

- **Test-driven design anti-pattern**: Testi yazmaq üçün production kodu dizaynını qəsdən yalnız test-ə uyğun etmək — `public`-ə çevirmək, private methodları test etmək. Test implementation detail-ı yoxlamalıdır, public interface-i.

- **Kata practice**: TDD öyrənmək üçün "code kata" — fiziki idman kimi. FizzBuzz, Roman Numerals, Bowling Score — kiçik, məlum problem üzərində TDD tətbiq et. Hər dəfə yeni düşüncə tərzi inkişaf edir.

## Praktik Baxış

**Interview-da necə yanaşmaq:**
TDD haqqında iki tip cavab var: kitab cavabı ("Red-Green-Refactor") və senior cavabı ("TDD-ni hər zaman etmirəm, amma bu hallarda tətbiq edirəm, bu hallarda etmirəm, bu trade-off-lar var"). İkincisi daha güclüdür. Konkret "TDD-dən imtina etdim, çünki..." nümunəsi interviewer-ı razı salır.

**Junior-dan fərqlənən senior cavabı:**
Junior: "TDD — əvvəlcə test yazıb sonra kod yazırsan."
Senior: "TDD-ni business logic, edge case-ləri çox olan modullarda istifadə edirəm. Payment processing, discount calculation — bunlar üçün TDD çox effektiv. CRUD endpoint-lər üçün isə test-after daha praktikdir."
Lead: "Team-in TDD bacarığını inkişaf etdirmək üçün weekly coding dojo keçiririk. Pairing session-larda TDD approach göstərirəm."

**Follow-up suallar:**
- "TDD-ni hər zaman tətbiq edirsinizmi?"
- "Legacy codebaza-da TDD necə tətbiq edərdiniz?"
- "TDD ilə refactoring arasında münasibət necədir?"
- "Outside-In vs Inside-Out TDD fərqi nədir?"
- "TDD coverage-ı artırırmı? Coverage TDD-nin məqsədidirmi?"

**Ümumi səhvlər:**
- TDD-yi silver bullet kimi təqdim etmək
- "Biz hər zaman TDD edirik" demək (inandırıcı deyil)
- TDD-ni test-after ilə qarışdırmaq
- REFACTOR mərhələsini atlayıb yalnız Red-Green etmək
- Private method-ları test etmək (implementation detail)

**Yaxşı cavabı əla cavabdan fərqləndirən:**
TDD-nin "design tool" olduğunu başa düşmək. "Test yazmaq çətin olduqda bu bir dizayn problemidir" — bu cümləni söyləyən namizəd TDD-ni həqiqətən başa düşür.

## Nümunələr

### Tipik Interview Sualı
"TDD nədir və real layihədə onu tətbiq edirsinizmi? Məhdudiyyətləri nədir?"

### Güclü Cavab
"TDD Red-Green-Refactor siklindən ibarətdir — əvvəlcə uğursuz test yazırsən, sonra onu keçirəcək minimum kod, sonra kodu refactor edirsən. Amma hər zaman tətbiq etmirəm. Biznes logikası kompleks olduqda — payment processing, promo code logic, shipping calculation — TDD çox faydalıdır. Test yazarkən edge case-ləri əvvəlcə düşünürsən. UI prototip, exploratory coding ya da sıx deadline-larda test-after daha praktikdir. Son layihədə payment modulunu TDD ilə yazdım — hər edge case (uğursuz kart, network timeout, duplicate charge, currency mismatch) əvvəlcə test kimi yazıldı. Nəticədə production-da sıfır payment bug oldu. TDD-nin ən böyük faydası test deyil — dizayn feedback-idir. Test çətin yazılırsa kod yanlış dizayn edilib."

### Kod Nümunəsi (PHP/Laravel — TDD sikli — ShoppingCart)

```php
// ═══════════════════════════════════════════════
// ADDIM 1: RED — Test yaz, uğursuz olsun
// ShoppingCart class-ı hələ mövcud deyil — FAIL
// ═══════════════════════════════════════════════
class ShoppingCartTest extends TestCase
{
    public function test_empty_cart_has_zero_total(): void
    {
        $cart = new ShoppingCart();
        $this->assertEquals(0.00, $cart->total());
        // FAIL: Class ShoppingCart not found
    }
}

// ═══════════════════════════════════════════════
// ADDIM 2: GREEN — Minimum kod
// ═══════════════════════════════════════════════
class ShoppingCart
{
    public function total(): float
    {
        return 0.00;  // Hardcoded — yalnız bu testi keçirmək üçün
    }
}
// PASS ✓

// ═══════════════════════════════════════════════
// ADDIM 3: Növbəti test (RED) — item əlavə et
// ═══════════════════════════════════════════════
class ShoppingCartTest extends TestCase
{
    public function test_empty_cart_has_zero_total(): void
    {
        $cart = new ShoppingCart();
        $this->assertEquals(0.00, $cart->total());
    }

    public function test_cart_with_one_item_returns_item_price(): void
    {
        $cart = new ShoppingCart();
        $cart->add(new CartItem('Widget', price: 25.00));
        $this->assertEquals(25.00, $cart->total());
        // FAIL: Method add() not found
    }
}

// GREEN — add() implement et
class ShoppingCart
{
    private array $items = [];

    public function add(CartItem $item): void
    {
        $this->items[] = $item;
    }

    public function total(): float
    {
        return array_sum(array_map(fn($i) => $i->price, $this->items));
    }
}

class CartItem
{
    public function __construct(
        public string $name,
        public float $price,
    ) {}
}
// PASS ✓

// ═══════════════════════════════════════════════
// ADDIM 4: Tax test (RED)
// ═══════════════════════════════════════════════
public function test_total_includes_18_percent_tax(): void
{
    $cart = new ShoppingCart(taxRate: 0.18);
    $cart->add(new CartItem('Widget', price: 100.00));
    $this->assertEquals(118.00, $cart->total());
    // FAIL: Constructor doesn't accept taxRate
}

// GREEN — taxRate əlavə et
class ShoppingCart
{
    private array $items = [];

    public function __construct(private float $taxRate = 0.0) {}

    public function add(CartItem $item): void
    {
        $this->items[] = $item;
    }

    public function total(): float
    {
        return $this->subtotal() * (1 + $this->taxRate);
    }

    public function subtotal(): float
    {
        return array_sum(array_map(fn($i) => $i->price, $this->items));
    }
}
// PASS ✓

// ═══════════════════════════════════════════════
// ADDIM 5: Edge case testlər (RED)
// ═══════════════════════════════════════════════
public function test_invalid_tax_rate_throws_exception(): void
{
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Tax rate must be between 0 and 1');
    new ShoppingCart(taxRate: 1.5);  // 150% tax — mənasızdır
}

public function test_negative_price_throws_exception(): void
{
    $cart = new ShoppingCart();
    $this->expectException(\InvalidArgumentException::class);
    $cart->add(new CartItem('Widget', price: -10.00));
}

public function test_item_count_returns_correct_value(): void
{
    $cart = new ShoppingCart();
    $cart->add(new CartItem('A', 10.00));
    $cart->add(new CartItem('B', 20.00));
    $this->assertEquals(2, $cart->itemCount());
}

// ═══════════════════════════════════════════════
// ADDIM 6: REFACTOR — Bütün testlər yaşıldır
// Kod daha robust edilir
// ═══════════════════════════════════════════════
class ShoppingCart
{
    private array $items = [];

    public function __construct(private float $taxRate = 0.0)
    {
        if ($taxRate < 0 || $taxRate > 1) {
            throw new \InvalidArgumentException(
                'Tax rate must be between 0 and 1, got: ' . $taxRate
            );
        }
    }

    public function add(CartItem $item): void
    {
        if ($item->price < 0) {
            throw new \InvalidArgumentException(
                "Item price cannot be negative: {$item->price}"
            );
        }
        $this->items[] = $item;
    }

    public function subtotal(): float
    {
        return array_sum(array_map(fn(CartItem $item) => $item->price, $this->items));
    }

    public function tax(): float
    {
        return round($this->subtotal() * $this->taxRate, 2);
    }

    public function total(): float
    {
        return round($this->subtotal() + $this->tax(), 2);
    }

    public function itemCount(): int
    {
        return count($this->items);
    }

    public function isEmpty(): bool
    {
        return empty($this->items);
    }
}
// Bütün testlər hələ yaşıldır — refactoring uğurlu ✓

// ═══════════════════════════════════════════════
// BDD — Behat / Given-When-Then
// Business language ilə test
// ═══════════════════════════════════════════════
// Feature: Shopping cart discount
//   Scenario: Loyal customer receives 10% discount
//     Given I am a loyal customer with 500 points
//     And I have items worth $200 in my cart
//     When I apply my loyalty discount
//     Then my cart total should be $180

// Behat step definitions:
class CartContext implements Context
{
    private Customer $customer;
    private ShoppingCart $cart;

    /**
     * @Given I am a loyal customer with :points points
     */
    public function iAmALoyalCustomerWith(int $points): void
    {
        $this->customer = Customer::create(loyaltyPoints: $points);
    }

    /**
     * @Given I have items worth :amount in my cart
     */
    public function iHaveItemsWorth(float $amount): void
    {
        $this->cart = new ShoppingCart();
        $this->cart->add(new CartItem('Item', price: $amount));
    }

    /**
     * @When I apply my loyalty discount
     */
    public function iApplyMyLoyaltyDiscount(): void
    {
        $discountService = new LoyaltyDiscountService();
        $discount = $discountService->calculate($this->customer, $this->cart);
        $this->cart->applyDiscount($discount);
    }

    /**
     * @Then my cart total should be :expectedTotal
     */
    public function myCartTotalShouldBe(float $expectedTotal): void
    {
        Assert::assertEquals($expectedTotal, $this->cart->total());
    }
}

// ═══════════════════════════════════════════════
// Legacy Code üçün TDD — Characterization Test
// ═══════════════════════════════════════════════
// Əvvəlcə mövcud davranışı qeyd edən test yaz
// Sonra refactoring et, testlər qalır
class LegacyOrderProcessorTest extends TestCase
{
    /**
     * Characterization test: mövcud davranışı sənədləşdirir
     * Bu test nə doğru, nə yanlışdır — sadəcə "hal-hazırda belə işləyir"
     */
    public function test_legacy_order_calculates_total_including_shipping(): void
    {
        $processor = new LegacyOrderProcessor();

        // Köhnə kod müəyyən bir şəkildə işləyir — bunu qeyd edirik
        $total = $processor->calculateTotal(
            items: [['price' => 100, 'qty' => 2]],
            shippingZone: 'LOCAL'
        );

        // Bu dəyər "doğru" deyil, "hal-hazırda belə qaytarır"
        $this->assertEquals(215.00, $total);
        // 200 + 15 (LOCAL shipping fee)
    }
}
// İndi bu test sayəsində refactoring zamanı davranış dəyişsə xəbər tutarıq
```

### Müqayisə Cədvəli — TDD Yanaşmaları

| Yanaşma | Test yazılma vaxtı | Mock istifadəsi | Üstünlük | Çatışmazlıq |
|---------|-------------------|-----------------|----------|-------------|
| TDD (klassik) | Koddan əvvəl | Az (classicist) | Design quality | Öyrənmə süreci |
| Outside-In TDD | Yüksək səviyyədən | Çox | Top-down clarity | Brittle mocks |
| Test-First | Tələbdən sonra | Orta | Balanced | TDD tam deyil |
| Test-After | Koddan sonra | Hər hansı | Fast initially | Regression risk |
| BDD | Tələbdən əvvəl | Az | Business alignment | Gherkin overhead |

## Praktik Tapşırıqlar

1. Bir həftə ərzində yazdığın hər yeni feature üçün TDD tətbiq et. Hər gün RED→GREEN→REFACTOR sikli.
2. Mövcud legacy class-ı TDD ilə refactor et: əvvəlcə characterization test yaz, sonra refactor et.
3. Outside-In TDD ilə bir feature yaz: acceptance test → service test → unit test ardıcıllığı ilə.
4. "TDD etmək çox çətin idi" olan bir halı tap — dizayn problemini aşkar et. Constructor parametrləri çox mu?
5. Bowling Score Kata-sını TDD ilə həll et — klassik TDD alıştırması.
6. BDD: Behat install edib bir feature üçün Given/When/Then scenario yaz.
7. Test yazmaq niyə çətin oldu? Dependency injection yoxdursa — refactor et, sonra test yaz.
8. Legacy class-ın private method-unu test etmək lazımdırsa — niyə public interface üzərindən test etmək daha doğrudur?

## Əlaqəli Mövzular

- [01-testing-pyramid.md](01-testing-pyramid.md) — TDD pyramid-ın hansı qatına toxunur
- [04-mocking-strategies.md](04-mocking-strategies.md) — TDD-də mock istifadəsi
- [02-unit-integration-e2e.md](02-unit-integration-e2e.md) — TDD ilə hansı test növü yazılır
- [05-test-coverage-metrics.md](05-test-coverage-metrics.md) — TDD coverage-ı artırırmı?
