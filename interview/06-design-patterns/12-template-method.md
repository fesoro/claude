# Template Method Pattern (Senior ⭐⭐⭐)

## İcmal

Template Method pattern — algoritmin skeletini base class-da müəyyən edən, bəzi addımları subclass-lara buraxan behavioral pattern-dir. "Define the skeleton of an algorithm in an operation, deferring some steps to subclasses. Template Method lets subclasses redefine certain steps of an algorithm without changing the algorithm's structure." Base class "nə sırayla" müəyyən edir, subclass-lar "necə" müəyyən edir. Laravel-in Eloquent model lifecycle (`booting()`, `creating()`, `created()` hook-ları), Artisan Command-in `handle()`, Job-un `handle()` — bunların hamısı Template Method-dur. Interview-larda inheritance vs composition suallarında, ya da "bir neçə variant prosesi necə idarə edərsiniz?" sualında çıxır.

## Niyə Vacibdir

Template Method pattern — inheritance-in ən səmərəli istifadəsidir. Böyük layihələrdə eyni prosesdə bir neçə addım dəyişirsə (məs: ödəniş prosesi, export pipeline, authentication flow) hər variantı ayrıca implement etmək code duplication yaradır. Template Method ilə: Ümumi hissə (validation, logging, error handling) base class-da bir dəfə. Fərqli hissə hər subclass-da ayrıca. Interviewer bu mövzuda yoxlayır: "Strategy vs Template Method fərqi nədir?" "Hook methods nədir?" "Nə vaxt inheritance, nə vaxt composition seçərsiniz?"

## Əsas Anlayışlar

**Template Method komponentləri:**
- **Abstract Class**: `templateMethod()` — algoritmin skeletini müəyyən edir. Abstract ya da default-implementasiyalı hook metodları saxlayır
- **Concrete Classes**: Abstract metodları implement edir, hook-ları override edir
- **Template Method**: Final ola bilər (subclass override edə bilməz) — algoritm strukturunu qorumaq üçün
- **Abstract Steps**: Mütləq override edilməlidir
- **Hook Methods**: Default implementasiya var, override optional

**Template Method vs Strategy:**
- **Template Method**: Inheritance əsaslı. Compile-time seçim. Subclass-da override
- **Strategy**: Composition əsaslı. Runtime seçim. Strategy object inject edilir
- Hər ikisi algorithm variation-ı həll edir — fərq mexanizmdədir
- Tövsiyə: "Prefer composition over inheritance" — Strategy daha flexible. Template Method daha sadə

**Hook methods:**
- Default implementasiyası olan, subclass-ın override etmək üçün isteğe bağlı olduğu metodlar
- `beforeProcess()`, `afterProcess()`, `shouldSkip()` — bunlar hook-lardır
- Laravel Eloquent: `boot()`, `booting()`, `creating()` — model lifecycle hook-ları

**Hollywood Principle:**
- "Don't call us, we'll call you" — Template Method-un tərsi yox, bu birbaşa Template Method-dur
- Base class framework role-unu oynayır: "Mən sənin metodunu çağıracağam, sən çağırmırsan"
- `handle()` metodu Laravel Job-da — framework sənin handler-ını çağırır

**Code duplication vs Template Method:**
- İki class-da identik 5 addım, yalnız 2-si fərqlidir — Template Method candidate
- Amma: 2-dən çox level inheritance çətin maintenance-dir
- Alternative: Template Method yerinə Strategy + base class ilə köməkçi metodlar

**Abstract class vs Interface:**
- Interface: Pure contract — implementasiya yoxdur
- Abstract class: Partial implementasiya + contract. Template Method-da mütləq abstract class
- PHP-də: `abstract class ReportGenerator` — `abstract protected function buildContent(): string`

**Final template method:**
- `final public function process(): void` — subclass override edə bilməz, yalnız hook-ları override edir
- Framework-lərdə çox istifadə olunur: subclass-ların algoritm sırasını pozmaması üçün

**Multiple inheritance problem (PHP):**
- PHP-də abstract class bir sinif extend edə bilər — bir Template Method hierarchy-si
- Birdən çox independent behavior lazım olduqda → Strategy + Interface + Trait kombinasiyası

## Praktik Baxış

**Interview-da yanaşma:**
Template Method-u "abstract class-da common flow, subclass-da specific steps" kimi izah edin. Laravel-in `Command::handle()` ya da Eloquent-in lifecycle-ını nümunə kimi çəkin. Sonra Strategy ilə müqayisə edin — bu differentiator-dır.

**"Nə vaxt Template Method seçərdiniz?" sualına cavab:**
- Algorithm-ın ümumi strukturu sabitdir, yalnız bəzi addımlar dəyişir
- Subclass-lar eyni prosesi paylaşır, yalnız detallar fərqlidir
- Code duplication azaltmaq üçün (DRY)
- Seçməzdiniz: Runtime-da algorithm dəyişmə lazım olduqda (Strategy daha uyğundur), kompozit davranışlar üçün (birden çox behavior)

**Anti-pattern-lər:**
- Base class-da çox abstract metod olmaq — subclass implement etmək üçün çox şey bilməlidir
- Template method-u override etməyə icazə vermək — algorithm strukturu pozula bilər (`final` istifadə edin)
- Dərin inheritance hierarchy — 3+ level: maintain etmək çətin olur
- Template Method-u Strategy-nin yerinə hər halda istifadə etmək — composition daha flexible

**Follow-up suallar:**
- "Template Method ile Decorator fərqi nədir?" → Template Method: Inheritance ilə structure. Decorator: Composition ilə davranış əlavə etmək
- "Eloquent-in `boot()` metodu nədir?" → Template Method hook-u: Model initialize olanda çağırılır, subclass-lar event listener qeydiyyatı, global scope əlavə edir
- "Artisan Command-in `handle()` metodu niyə override edilir?" → Template Method: Command class framework-in `run()` metodunu çağırır, o da `handle()`-ı çağırır — Hollywood Principle

## Nümunələr

### Tipik Interview Sualı

"You're building a data import system. You need to import from CSV, Excel, and JSON files. All formats share the same steps: read file → validate data → transform → save to database → send notification. Only the read and transform steps differ by format. How would you design this?"

### Güclü Cavab

Bu Template Method-un klassik use-case-idir. Ümumi pipeline — 5 addım, yalnız 2-si fərqlidir.

`DataImporter` abstract class-ı template method `import()` ilə: `readFile()` abstract, `validateData()` shared, `transformData()` abstract, `saveToDatabase()` shared, `notify()` hook (default: email, override edə bilər).

`CsvImporter`, `ExcelImporter`, `JsonImporter` — yalnız `readFile()` və `transformData()`-nı implement edir. Validation, save, notification logic bir dəfə yazılır.

Yeni format əlavə etmək: Sadəcə yeni class yazın, 2 metod implement edin.

### Kod Nümunəsi

```php
// Abstract Class — Template Method
abstract class DataImporter
{
    // Template Method — final: subclass override edə bilməz
    final public function import(string $filePath): ImportResult
    {
        $this->beforeImport($filePath);                    // Hook

        try {
            $rawData = $this->readFile($filePath);         // Abstract step
            $records = $this->validateData($rawData);      // Shared step
            $mapped  = $this->transformData($records);     // Abstract step
            $result  = $this->saveToDatabase($mapped);     // Shared step

            $this->afterSuccess($result);                  // Hook
            $this->notify($result);                        // Hook (default impl var)

            return $result;
        } catch (ValidationException $e) {
            $this->handleValidationError($e);              // Hook
            throw $e;
        } catch (\Throwable $e) {
            $this->handleError($e);                        // Hook
            throw $e;
        }
    }

    // Abstract steps — subclass mütləq implement etməlidir
    abstract protected function readFile(string $path): array;
    abstract protected function transformData(array $records): array;

    // Shared step — bütün format-lar üçün eyni
    protected function validateData(array $rawData): array
    {
        if (empty($rawData)) {
            throw new ImportException('File is empty');
        }

        $validated = [];
        $errors = [];

        foreach ($rawData as $lineNumber => $row) {
            try {
                $validated[] = $this->validateRow($row, $lineNumber);
            } catch (ValidationException $e) {
                $errors[] = "Line {$lineNumber}: {$e->getMessage()}";
            }
        }

        if (count($errors) > config('import.max_errors', 10)) {
            throw new TooManyValidationErrorsException($errors);
        }

        return $validated;
    }

    protected function validateRow(array $row, int $line): array
    {
        // Default validation — subclass override edə bilər
        foreach ($this->requiredFields() as $field) {
            if (empty($row[$field])) {
                throw new ValidationException("Required field '{$field}' missing");
            }
        }
        return $row;
    }

    protected function saveToDatabase(array $mapped): ImportResult
    {
        $saved = 0;
        $failed = 0;

        DB::transaction(function () use ($mapped, &$saved, &$failed) {
            foreach (array_chunk($mapped, 500) as $chunk) {
                try {
                    $model = $this->getModel();
                    $model::insert($chunk);
                    $saved += count($chunk);
                } catch (\Exception $e) {
                    $failed += count($chunk);
                    Log::warning('Batch insert failed', ['error' => $e->getMessage()]);
                }
            }
        });

        return new ImportResult($saved, $failed);
    }

    // Hook methods — default implementasiya, override optional
    protected function beforeImport(string $filePath): void
    {
        Log::info('Starting import', ['file' => $filePath, 'class' => static::class]);
    }

    protected function afterSuccess(ImportResult $result): void
    {
        Log::info('Import completed', ['saved' => $result->saved, 'failed' => $result->failed]);
    }

    protected function notify(ImportResult $result): void
    {
        // Default: log only. Subclass email, Slack notification əlavə edə bilər
        Log::info('Import notification', ['result' => $result]);
    }

    protected function handleValidationError(ValidationException $e): void
    {
        Log::warning('Validation failed during import', ['error' => $e->getMessage()]);
    }

    protected function handleError(\Throwable $e): void
    {
        Log::error('Import failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
    }

    // Helper — subclass model-i müəyyən edir
    abstract protected function getModel(): string;
    abstract protected function requiredFields(): array;
}

// Concrete Class 1: CSV Importer
class CsvProductImporter extends DataImporter
{
    protected function readFile(string $path): array
    {
        $handle = fopen($path, 'r');
        $headers = fgetcsv($handle);
        $rows = [];

        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = array_combine($headers, $row);
        }

        fclose($handle);
        return $rows;
    }

    protected function transformData(array $records): array
    {
        return array_map(function ($record) {
            return [
                'sku'        => strtoupper(trim($record['product_sku'])),
                'name'       => trim($record['product_name']),
                'price'      => (float) str_replace(',', '.', $record['price']),
                'stock'      => (int) $record['quantity'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }, $records);
    }

    protected function getModel(): string { return Product::class; }

    protected function requiredFields(): array
    {
        return ['product_sku', 'product_name', 'price'];
    }

    // Hook override — CSV import-dan sonra Slack notification
    protected function notify(ImportResult $result): void
    {
        parent::notify($result); // Parent-in log-unu da çağır
        Notification::route('slack', config('slack.import_channel'))
            ->notify(new CsvImportCompleted($result));
    }
}

// Concrete Class 2: JSON Importer
class JsonProductImporter extends DataImporter
{
    protected function readFile(string $path): array
    {
        $content = file_get_contents($path);
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ImportException('Invalid JSON: ' . json_last_error_msg());
        }

        // JSON structure fərqlənə bilər — normalize edirik
        return $data['products'] ?? $data;
    }

    protected function transformData(array $records): array
    {
        return array_map(fn($r) => [
            'sku'        => $r['sku'],
            'name'       => $r['name'],
            'price'      => $r['price_cents'] / 100,  // JSON sentdədir
            'stock'      => $r['inventory_count'],
            'created_at' => now(),
            'updated_at' => now(),
        ], $records);
    }

    protected function getModel(): string { return Product::class; }
    protected function requiredFields(): array { return ['sku', 'name', 'price_cents']; }
}
```

```php
// Laravel Eloquent — Template Method pattern tətbiqi
// Model lifecycle hook-ları — Template Method hooks
abstract class AuditableModel extends Model
{
    // Template Method hook
    protected static function booting(): void
    {
        parent::booting();

        static::creating(function (self $model) {
            $model->created_by = auth()->id();
        });

        static::updating(function (self $model) {
            $model->updated_by = auth()->id();
        });

        static::deleting(function (self $model) {
            if (method_exists($model, 'canBeDeleted') && !$model->canBeDeleted()) {
                throw new ModelCannotBeDeletedException(static::class, $model->id);
            }
        });
    }
}

class Order extends AuditableModel
{
    // Subclass yalnız spesifik davranışını əlavə edir
    protected static function booting(): void
    {
        parent::booting();  // AuditableModel-in hook-larını saxla

        static::created(function (Order $order) {
            event(new OrderCreated($order));
        });
    }

    protected function canBeDeleted(): bool
    {
        return $this->status === 'draft';
    }
}
```

## Praktik Tapşırıqlar

- `ReportGenerator` abstract class yazın: `collectData()`, `formatData()`, `output()` — CSV və PDF subclass-ları implement etsin
- Laravel-in `Notification` class-ı `via()` metoduna nəzər salın — Template Method necə tətbiq olunub
- Mövcud kod duplication-ı Template Method ilə refactor edin: İki oxşar class-da ortaq hissəni base class-a çıxarın
- Eloquent model-ə audit trail hook əlavə edin: `AuditableModel` abstract class — `creating`, `updating`, `deleting` hook-ları
- Template Method → Strategy refactoring: Template Method-la yazılmış kodu Strategy-yə refactor edin — fərqi müqayisə edin

## Əlaqəli Mövzular

- [Strategy Pattern](05-strategy-pattern.md) — Composition-based alternativ, runtime seçim
- [SOLID Principles](01-solid-principles.md) — OCP: base class-da fixed, subclass-da extension
- [Observer / Event](04-observer-event.md) — Hook metodları observer pattern ilə birlikdə
- [Factory Patterns](02-factory-patterns.md) — Hangi Importer lazımdır Factory ilə seçilir
- [Decorator Pattern](08-decorator-pattern.md) — Composition-based davranış əlavəsi alternativ
