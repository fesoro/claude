# Template Method (Middle ⭐⭐)

## İcmal
Template Method pattern abstract class-da bir algoritmin skeleton-ını müəyyən edir; addımların bəzilərini subclass-lara implement etməyə buraxır. Algoritmin strukturu sabit qalır, ancaq bəzi addımlar dəyişdirilə bilir. "Nə edəcəyini" abstract class, "necə edəcəyini" subclass müəyyən edir.

## Niyə Vacibdir
Laravel-in özündə Mailable, Notification, Console\Command, Job siniflərinin hamısı bu pattern üzərindədir. "Bunu extend et, bu metodu override et" deyən hər Laravel sinfini düzgün istifadə etmək üçün Template Method-u anlamaq lazımdır.

## Əsas Anlayışlar
- **Template method**: abstract class-da `final` qeyd olunan ana metod — algoritmin gedişatını idarə edir
- **Abstract methods**: subclass-ın mütləq implement etməli olduğu addımlar
- **Hook methods**: subclass-ın istəyə bağlı override edə bildiyi boş (default) metodlar
- **Invariant behaviour**: template method-da olan, dəyişdirilməsi mümkün olmayan hissə
- **Variant behaviour**: subclass-lara verilən, override edilən hissə

## Praktik Baxış
- **Real istifadə**: report generation (PDF/CSV/Excel), data import pipeline (validate → transform → save), notification channels (email/SMS/push), ETL processes
- **Trade-off-lar**: inheritance hierarchy mürəkkəbləşə bilər; base class-a edilən dəyişiklik bütün subclass-ları təsir edir (Liskov Substitution Principle pozula bilər)
- **İstifadə etməmək**: algoritmik fərq çox böyükdürsə (Strategy daha uyğundur); "is-a" əlaqəsi yoxdursa inheritance məcburi etmək düzgün deyil
- **Common mistakes**: template method-u `final` etməmək — subclass-lar onu override edib bütün loqikanı pozur; çox sayda abstract method — subclass implement etmək çox çətin olur

## Nümunələr

### Ümumi Nümunə
Bir çay və qəhvə hazırlama prosesi düşünün. İkisi üçün də: qaynar su qaynat → içki hazırla → stəkana tök → əlavələr qoy. "Qaynar su qaynatmaq" və "stəkana tökmək" eynidir — base class-da sabit qalır. "İçki hazırlamaq" (çay yarpağı vs. qəhvə dənəsi) və "əlavələr" (limon vs. süd/şəkər) hər biri üçün fərqlidir — subclass-lar override edir.

### PHP/Laravel Nümunəsi

```php
<?php

// Abstract class — algoritmin skeleton-ı
abstract class ReportGenerator
{
    // Template method — final: subclass override edə bilməz
    final public function generateReport(): string
    {
        $data      = $this->fetchData();           // abstract
        $processed = $this->processData($data);    // abstract
        $this->beforeFormat($processed);           // hook (optional)
        $output    = $this->formatOutput($processed); // abstract
        $this->afterFormat($output);               // hook (optional)

        return $output;
    }

    abstract protected function fetchData(): array;
    abstract protected function processData(array $data): array;
    abstract protected function formatOutput(array $data): string;

    // Hook metodlar — default boş, istəyə bağlı override edilir
    protected function beforeFormat(array $data): void {}
    protected function afterFormat(string $output): void {}
}

// ConcreteClass 1
class PdfReportGenerator extends ReportGenerator
{
    protected function fetchData(): array
    {
        return DB::table('orders')->whereBetween('created_at', [...])->get()->toArray();
    }

    protected function processData(array $data): array
    {
        return collect($data)->groupBy('user_id')->map(fn($rows) => [
            'total' => $rows->sum('amount'),
            'count' => $rows->count(),
        ])->toArray();
    }

    protected function formatOutput(array $data): string
    {
        return app(PdfRenderer::class)->render('reports.sales', $data);
    }

    // Hook override: PDF-i S3-ə yüklə
    protected function afterFormat(string $output): void
    {
        Storage::put('reports/sales-' . now()->format('Y-m-d') . '.pdf', $output);
    }
}

// ConcreteClass 2
class CsvReportGenerator extends ReportGenerator
{
    protected function fetchData(): array
    {
        return DB::table('orders')->get()->toArray();
    }

    protected function processData(array $data): array
    {
        return $data; // CSV üçün raw data kifayətdir
    }

    protected function formatOutput(array $data): string
    {
        $csv = implode(',', ['id', 'user_id', 'amount', 'created_at']) . "\n";

        foreach ($data as $row) {
            $csv .= implode(',', [(array) $row]) . "\n";
        }

        return $csv;
    }
}

// İstifadəsi — polimorfizm sayəsində eyni interface
$generators = [
    new PdfReportGenerator(),
    new CsvReportGenerator(),
];

foreach ($generators as $generator) {
    $output = $generator->generateReport(); // hər biri öz formatında
}
```

**Laravel Mailable = Template Method:**

```php
// Laravel-in abstract Mailable class-ı template method-dur
// build() override etmək məcburidir
class OrderConfirmationMail extends Mailable
{
    public function __construct(private readonly Order $order) {}

    // Abstract addım: subclass-ın implement etməsi lazımdır
    public function build(): self
    {
        return $this
            ->subject("Order #{$this->order->id} Confirmed")
            ->view('emails.order-confirmation')
            ->with(['order' => $this->order]);
    }
}

// Notification class-ı da eyni pattern
class OrderShippedNotification extends Notification
{
    public function __construct(private readonly Order $order) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    // Hook: mail channel üçün
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Your order has shipped!")
            ->line("Order #{$this->order->id} is on its way.");
    }

    // Hook: database channel üçün
    public function toDatabase(object $notifiable): array
    {
        return ['order_id' => $this->order->id, 'message' => 'Order shipped'];
    }
}
```

**Data Import Pipeline:**

```php
abstract class DataImporter
{
    final public function import(string $filePath): ImportResult
    {
        $raw      = $this->readFile($filePath);      // abstract
        $validated = $this->validate($raw);           // abstract
        $this->onValidationComplete($validated);      // hook
        $transformed = $this->transform($validated);  // abstract
        $result   = $this->persist($transformed);     // concrete — eyni logic
        $this->onComplete($result);                   // hook

        return $result;
    }

    abstract protected function readFile(string $path): array;
    abstract protected function validate(array $data): array;
    abstract protected function transform(array $data): array;

    // Concrete shared method — bütün subclass-lar üçün eyni
    protected function persist(array $data): ImportResult
    {
        return DB::transaction(function () use ($data) {
            $inserted = 0;
            foreach (array_chunk($data, 500) as $chunk) {
                DB::table($this->tableName())->insert($chunk);
                $inserted += count($chunk);
            }
            return new ImportResult(inserted: $inserted);
        });
    }

    abstract protected function tableName(): string;
    protected function onValidationComplete(array $data): void {}
    protected function onComplete(ImportResult $result): void {}
}

class CsvProductImporter extends DataImporter
{
    protected function readFile(string $path): array
    {
        return array_map('str_getcsv', file($path));
    }

    protected function validate(array $data): array
    {
        return collect($data)->filter(fn($row) => count($row) === 5)->values()->toArray();
    }

    protected function transform(array $data): array
    {
        return collect($data)->map(fn($row) => [
            'sku'        => $row[0],
            'name'       => $row[1],
            'price'      => (float) $row[2],
            'stock'      => (int) $row[3],
            'created_at' => now(),
        ])->toArray();
    }

    protected function tableName(): string
    {
        return 'products';
    }
}
```

**Template Method vs Strategy müqayisəsi:**

```php
// Template Method: inheritance — "is-a" əlaqəsi
// Base class algoritmi idarə edir, subclass addımları fill edir
abstract class Sorter
{
    final public function sort(array $data): array
    {
        $data = $this->prepare($data);       // hook
        $data = $this->doSort($data);        // abstract
        return $this->finalize($data);       // hook
    }
    abstract protected function doSort(array $data): array;
    protected function prepare(array $data): array { return $data; }
    protected function finalize(array $data): array { return $data; }
}

// Strategy: composition — "has-a" əlaqəsi
// Context algoritmi idarə edir, strategy bir addımı edir
class Sorter
{
    public function __construct(private SortStrategy $strategy) {}

    public function sort(array $data): array
    {
        return $this->strategy->sort($data); // tam delegasiya
    }
}
// Strategy run-time-da dəyişdirilə bilir; Template Method compile-time-da sabitdir
```

## Praktik Tapşırıqlar
1. `abstract NotificationSender` class yazın — `final send()` template method-u `buildSubject()`, `buildBody()`, `getRecipients()` abstract metodlarını çağırsın; `EmailNotificationSender` və `SmsNotificationSender` implement edin
2. Laravel-dəki bir `Mailable` subclass-ı seçin, `Mailable` base class-ının hansı metodlarını template method kimi istifadə etdiyini araşdırın
3. `CsvProductImporter`-ı `ExcelProductImporter` kimi extend edin, yalnız `readFile()` metodunu dəyişin

## Əlaqəli Mövzular
- [09-strategy.md](09-strategy.md) — Template Method vs Strategy seçimi
- [03-abstract-factory.md](03-abstract-factory.md) — Abstract class istifadəsi baxımından oxşarlıq
- [15-service-layer.md](15-service-layer.md) — Service class-larında pipeline qurmaq
