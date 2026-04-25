# Prototype (Middle ⭐⭐)

## İcmal
Prototype pattern mövcud bir obyekti kopyalayaraq (clone edərək) yeni obyekt yaratmağa imkan verir. Yeni obyekti sıfırdan qurmaq əvəzinə, mövcud "prototip" kopyalanır — lazım olan yerlər dəyişdirilir, qalanı eyni qalır.

## Niyə Vacibdir
Bəzi obyektlər yaratmaq bahalıdır: verilənlər bazasından oxunur, hesablanır, API-dən gəlir. Bu obyektləri hər dəfə sıfırdan qurmaq əvəzinə clone etmək performansı artırır. Laravel-in `$model->replicate()` metodu bu pattern-in real nümunəsidir — Eloquent model-i database-ə vurmadan kopyalayır.

## Əsas Anlayışlar
- **Shallow copy**: PHP `clone` keyword-ü — primitiv sahələr kopyalanır, obyekt sahələri isə hər iki surətdə eyni referansa işarə edir
- **Deep copy**: `__clone()` magic metodu ilə nested obyektləri də ayrıca kopyalamaq
- **`clone` keyword**: PHP-nin daxili kopyalama mexanizmi — `$copy = clone $original`
- **`__clone()` magic method**: `clone` çağırıldıqda avtomatik işləyir — dərin kopyalama üçün burada nested obyektlər əl ilə clone edilir
- **`$model->replicate()`**: Laravel Eloquent-in built-in shallow clone metodu — yeni, unsaved model qaytarır

## Praktik Baxış
- **Real istifadə**: Invoice template-dən yeni invoice, konfiqurasiya presetlər, test fixture-ları, mürəkkəb sorğu builder-in kopyası, report şablonu
- **Trade-off-lar**: Shallow copy referans problemi yaradır — bir surətdəki dəyişiklik digərini təsir edir; circular reference olan obyektlərdə `clone` sonsuz dövrəyə girə bilər; clone sonrası state idarəsi çətin ola bilər
- **İstifadə etməmək**: Sadə, ucuz yaradılan obyektlər üçün (`new` daha aydındır); immutable obyektlər üçün (clone-un mənası yoxdur); circular reference içərən ağır obyekt qrafları üçün
- **Common mistakes**: Shallow copy ilə deep copy fərqini bilməmək — nested obyektin bir surətdə dəyişdirilməsi digər surəti də dəyişir; `__clone()` yazmağı unutmaq

## Nümunələr

### Ümumi Nümunə
E-ticarət sistemini düşün: ayda bir dəfə recurring invoice göndərilirsə, hər dəfə yeni invoice sıfırdan doldurmaq əvəzinə əvvəlki ayın invoice-ini prototip kimi alıb müştəriyə, tarixə görə dəyişdirirsən. Template invoice-i birbaşa dəyişdirmirsən — clone edirsən.

### PHP/Laravel Nümunəsi

```php
// ===== Shallow copy problemi =====
class Address
{
    public function __construct(
        public string $street,
        public string $city
    ) {}
}

class Invoice
{
    public function __construct(
        public string $number,
        public Address $billingAddress,   // obyekt — referans məsələsi!
        public array $lineItems,          // massiv — kopyalanır (PHP-də array value type)
        public \DateTimeImmutable $date
    ) {}
}

$template = new Invoice(
    number:         'INV-2024-001',
    billingAddress: new Address('Nizami 12', 'Bakı'),
    lineItems:      [['desc' => 'Web xidmət', 'amount' => 500]],
    date:           new \DateTimeImmutable('2024-01-01')
);

// Shallow copy
$copy = clone $template;
$copy->number = 'INV-2024-002';
$copy->billingAddress->city = 'Gəncə'; // PROBLEM: template-in city-si də dəyişdi!

echo $template->billingAddress->city; // "Gəncə" — istenilmeyen dəyişiklik


// ===== __clone() ilə Deep copy =====
class InvoiceLineItem
{
    public function __construct(
        public string $description,
        public int $quantity,
        public int $unitPriceInCents
    ) {}
}

class InvoiceTemplate
{
    public string $number;
    public \DateTimeImmutable $issuedAt;
    public Address $billingAddress;

    /** @var InvoiceLineItem[] */
    public array $lineItems = [];

    public string $currency = 'AZN';
    public int $taxPercent = 18;

    public function __construct(string $number, Address $billingAddress)
    {
        $this->number         = $number;
        $this->billingAddress = $billingAddress;
        $this->issuedAt       = new \DateTimeImmutable();
    }

    public function addItem(InvoiceLineItem $item): void
    {
        $this->lineItems[] = $item;
    }

    // Deep clone — bütün nested obyektlər ayrıca kopyalanır
    public function __clone()
    {
        // Address ayrıca kopyalanır
        $this->billingAddress = clone $this->billingAddress;

        // DateTimeImmutable immutable olduğuna görə clone lazım deyil,
        // amma adı fərqli olaraq göstəririk ki aydın olsun
        $this->issuedAt = new \DateTimeImmutable('now');

        // Hər line item ayrıca kopyalanır
        $this->lineItems = array_map(
            fn(InvoiceLineItem $item) => clone $item,
            $this->lineItems
        );

        // Yeni invoice üçün number sıfırlanır — caller tənzimləyəcək
        $this->number = '';
    }
}

// İstifadə
$masterTemplate = new InvoiceTemplate('TEMPLATE', new Address('Nizami 12', 'Bakı'));
$masterTemplate->addItem(new InvoiceLineItem('Web xidmət', 1, 50000));
$masterTemplate->addItem(new InvoiceLineItem('Hosting', 12, 10000));

// Yeni invoice üçün clone et
$januaryInvoice = clone $masterTemplate;
$januaryInvoice->number    = 'INV-2024-001';
$januaryInvoice->issuedAt  = new \DateTimeImmutable('2024-01-31');

$februaryInvoice = clone $masterTemplate;
$februaryInvoice->number   = 'INV-2024-002';
$februaryInvoice->billingAddress->city = 'Gəncə'; // yalnız bu invoice-ə təsir edir

echo $masterTemplate->billingAddress->city; // "Bakı" — dəyişmədi ✓
echo $januaryInvoice->billingAddress->city;  // "Bakı" — dəyişmədi ✓
echo $februaryInvoice->billingAddress->city; // "Gəncə" ✓


// ===== Laravel Eloquent replicate() =====
class ProductController extends Controller
{
    public function duplicate(Product $product): JsonResponse
    {
        // replicate() — yeni, unsaved model qaytarır (id yoxdur)
        $copy = $product->replicate();
        $copy->name       = "{$product->name} (Kopya)";
        $copy->slug       = $product->slug . '-copy';
        $copy->created_at = now();
        $copy->save();

        // replicate() ilə seçilmədən buraxılan sahələr:
        // $copy = $product->replicate(except: ['views_count', 'featured_at']);

        return response()->json(['id' => $copy->id], 201);
    }
}


// ===== Prototype Registry (pool of prototypes) =====
class InvoiceTemplateRegistry
{
    private array $templates = [];

    public function register(string $name, InvoiceTemplate $template): void
    {
        $this->templates[$name] = $template;
    }

    public function make(string $name): InvoiceTemplate
    {
        if (!isset($this->templates[$name])) {
            throw new \InvalidArgumentException("Template '{$name}' not found.");
        }
        return clone $this->templates[$name];
    }
}

// ServiceProvider-də
$registry = new InvoiceTemplateRegistry();
$registry->register('standard', $standardTemplate);
$registry->register('vat-exempt', $vatExemptTemplate);

app()->instance(InvoiceTemplateRegistry::class, $registry);

// İstifadə
$invoice = app(InvoiceTemplateRegistry::class)->make('standard');
$invoice->number = 'INV-2024-050';
```

## Praktik Tapşırıqlar
1. Shallow copy problemini özün sübut et: `Address` sahəsi olan bir class yaz, `clone` et, kopyada `Address`-i dəyiş — originalın da dəyişdiyini görəndən sonra `__clone()` ilə düzəlt
2. `QueryBuilder` wrapper yaz: mürəkkəb filter-li bir query template saxla, `clone` edərək fərqli pagination nömrələri ilə eyni sorğunu çox dəfə işlət — hər çağırışda query-i sıfırdan qurma
3. Laravel-də `Product::factory()->make()` mənbə koduna bax — factory state (state pattern) ilə Prototype ideyasını necə birləşdirir?

## Əlaqəli Mövzular
- [05-builder.md](05-builder.md) — Builder sıfırdan qurur, Prototype mövcudu kopyalayır
- [03-factory-method.md](03-factory-method.md) — Factory Method yeni obyekt yaradır, Prototype mövcudu klonlayır
- [20-state.md](20-state.md) — Prototype Registry-də template state-lərini idarə etmək üçün
