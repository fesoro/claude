# Visitor (Lead ⭐⭐⭐⭐)

## İcmal
Visitor pattern, mövcud class-ları dəyişmədən yeni əməliyyatlar əlavə etməyə imkan verir. Əməliyyat ayrı Visitor object-inə köçürülür; element-lər sadəcə `accept(visitor)` çağırır — bu "double dispatch" mexanizmi visitor-a hansı konkret element ilə işlədiyini bildirir.

## Niyə Vacibdir
PHP layihələrindəki real problem: eyni structure-a (AST, document elements, order items) müxtəlif əməliyyatlar tətbiq etmək lazımdır — export to PDF, export to CSV, discount hesablama, validation. Hər əməliyyat üçün element class-larına yeni metod əlavə etmək Open/Closed Principle-i pozur. Visitor ilə yeni əməliyyat üçün yalnız yeni Visitor class yazılır; element class-ları dəyişmir.

## Əsas Anlayışlar
- **Visitor interface**: hər element növü üçün ayrı `visit` metodu — `visitSalesSection(SalesSection)`, `visitChart(ChartElement)`
- **ConcreteVisitor**: bir spesifik əməliyyatı bilir; bütün element növlərini handle edir
- **Element interface**: `accept(Visitor)` metodu — visitoru qəbul edir
- **ConcreteElement**: `accept()` içindən `visitor.visitThisType(this)` çağırır — bu double dispatch-in əsasıdır
- **Double Dispatch**: runtime-da həm element növü, həm visitor növü seçilir; PHP-nin virtual dispatch-i yalnız bir tərəfi seçir, visitor ikinci tərəfi əlavə edir
- **Object Structure**: element-lərin məcmusu (array, tree, composite); visitor bu structure-u traverse edir

## Praktik Baxış
- **Real istifadə**: report sistemi (eyni data-yı PDF/CSV/Excel-ə export), AST traversal (PHP parser, SQL builder), discount/tax engine (müxtəlif item növlərinə müxtəlif qaydalar), document rendering, code generation
- **Trade-off-lar**: yeni əməliyyat əlavə etmək asandır (yeni visitor); lakin yeni element növü əlavə etmək bütün mövcud visitor-ları dəyişdirir; element hierarchy stabil olmalıdır
- **İstifadə etməmək**: element hierarchy tez-tez dəyişirsə (yeni element hər visitor-u pozacaq); element sayı azsa (sadə method overloading bəsdir); element-lərə az sayda visitor lazımdırsa
- **Common mistakes**: element-lərdə business logic saxlamaq (visitor-ın işi olmalı); visitor-a state qoymaq (stateless olmalı, ya shared state açıq saxlanmalı); double dispatch-i anlamadan sadə method call hesab etmək
- **Anti-Pattern Nə Zaman Olur?**: Stabil structure-da tək bir əməliyyat üçün Visitor qurmaq — `Report`-un yalnız PDF export-u varsa, `PdfExportVisitor` ilə `accept()/visitX()` mexanizmi qurmaq overkill-dir; birbaşa `$report->toPdf()` metodu daha sadədir. Visitor pattern-in mənası çoxlu fərqli əməliyyatların (export, validate, transform, serialize) eyni structure-a tətbiq edilməsidir. Bir əməliyyat — bir metod; beş əməliyyat — Visitor düşünün. Əlavə olaraq: element hierarchy-ə tez-tez yeni növ əlavə olunursa, hər yeni element bütün mövcud Visitor-ları dəyişdirir — bu vəziyyətdə Strategy + Polymorphism daha uyğundur.

## Nümunələr

### Ümumi Nümunə
Bir mühasib şirkətin müxtəlif department-larına gedir — Sales, HR, Tech. Hər departmanda eyni sorğu edir (audit); lakin hər departmentdə audit fərqli formada aparılır (fərqli sənədlər, fərqli metrikalar). Mühasib (visitor) departmentdən (element) asılı olaraq düzgün audit metodunu tətbiq edir. Yeni bir audit növü üçün yeni mühasib göndərilir; departmentlər dəyişmir.

### PHP/Laravel Nümunəsi

```php
<?php

namespace App\Reports;

// ─────────────────────────────────────────────
// VISITOR INTERFACE — hər element növü üçün metod
// ─────────────────────────────────────────────

interface ReportVisitor
{
    public function visitSalesSection(SalesSection $section): void;
    public function visitChartElement(ChartElement $element): void;
    public function visitTableElement(TableElement $element): void;
    public function visitSummaryBlock(SummaryBlock $block): void;
}

// ─────────────────────────────────────────────
// ELEMENT INTERFACE
// ─────────────────────────────────────────────

interface ReportElement
{
    public function accept(ReportVisitor $visitor): void;
}

// ─────────────────────────────────────────────
// CONCRETE ELEMENTS
// ─────────────────────────────────────────────

class SalesSection implements ReportElement
{
    public function __construct(
        public readonly string $title,
        public readonly array  $salesData,  // ['date' => ..., 'amount' => ...]
        public readonly float  $totalRevenue,
    ) {}

    public function accept(ReportVisitor $visitor): void
    {
        // Double dispatch: visitor hansı element olduğunu bilir
        $visitor->visitSalesSection($this);
    }
}

class ChartElement implements ReportElement
{
    public function __construct(
        public readonly string $chartType,  // 'bar', 'line', 'pie'
        public readonly array  $labels,
        public readonly array  $datasets,
    ) {}

    public function accept(ReportVisitor $visitor): void
    {
        $visitor->visitChartElement($this);
    }
}

class TableElement implements ReportElement
{
    public function __construct(
        public readonly array $headers,
        public readonly array $rows,
        public readonly bool  $hasTotalsRow = false,
    ) {}

    public function accept(ReportVisitor $visitor): void
    {
        $visitor->visitTableElement($this);
    }
}

class SummaryBlock implements ReportElement
{
    public function __construct(
        public readonly string $title,
        public readonly array  $metrics,  // ['label' => 'value']
    ) {}

    public function accept(ReportVisitor $visitor): void
    {
        $visitor->visitSummaryBlock($this);
    }
}

// ─────────────────────────────────────────────
// CONCRETE VISITORS — hər biri ayrı əməliyyat
// ─────────────────────────────────────────────

class PdfExportVisitor implements ReportVisitor
{
    private string $output = '';

    public function visitSalesSection(SalesSection $section): void
    {
        $this->output .= "<h1 style='color:darkblue'>{$section->title}</h1>\n";
        $this->output .= "<p>Total Revenue: $" . number_format($section->totalRevenue, 2) . "</p>\n";
        foreach ($section->salesData as $row) {
            $this->output .= "<p>{$row['date']}: \${$row['amount']}</p>\n";
        }
    }

    public function visitChartElement(ChartElement $element): void
    {
        // PDF-də chart image olaraq render olunur
        $this->output .= "<img src='chart-{$element->chartType}.png' />\n";
        $this->output .= "<p>[Chart: " . implode(', ', $element->labels) . "]</p>\n";
    }

    public function visitTableElement(TableElement $element): void
    {
        $this->output .= "<table border='1'>\n";
        $this->output .= "<tr>" . implode('', array_map(fn($h) => "<th>{$h}</th>", $element->headers)) . "</tr>\n";
        foreach ($element->rows as $row) {
            $this->output .= "<tr>" . implode('', array_map(fn($c) => "<td>{$c}</td>", $row)) . "</tr>\n";
        }
        $this->output .= "</table>\n";
    }

    public function visitSummaryBlock(SummaryBlock $block): void
    {
        $this->output .= "<div class='summary'><h3>{$block->title}</h3>\n";
        foreach ($block->metrics as $label => $value) {
            $this->output .= "<p><strong>{$label}:</strong> {$value}</p>\n";
        }
        $this->output .= "</div>\n";
    }

    public function getOutput(): string { return $this->output; }
}

class CsvExportVisitor implements ReportVisitor
{
    private array $rows = [];

    public function visitSalesSection(SalesSection $section): void
    {
        $this->rows[] = ["SECTION: {$section->title}", '', ''];
        $this->rows[] = ["Date", "Amount", ""];
        foreach ($section->salesData as $row) {
            $this->rows[] = [$row['date'], $row['amount'], ''];
        }
        $this->rows[] = ["TOTAL", $section->totalRevenue, ""];
        $this->rows[] = [];
    }

    public function visitChartElement(ChartElement $element): void
    {
        // CSV-də chart export edilmir — skip
        $this->rows[] = ["[Chart: {$element->chartType} - not exportable to CSV]"];
    }

    public function visitTableElement(TableElement $element): void
    {
        $this->rows[] = $element->headers;
        foreach ($element->rows as $row) {
            $this->rows[] = $row;
        }
        $this->rows[] = [];
    }

    public function visitSummaryBlock(SummaryBlock $block): void
    {
        $this->rows[] = ["SUMMARY: {$block->title}"];
        foreach ($block->metrics as $label => $value) {
            $this->rows[] = [$label, $value];
        }
        $this->rows[] = [];
    }

    public function getCsv(): string
    {
        $output = '';
        foreach ($this->rows as $row) {
            $output .= implode(',', array_map(fn($cell) => '"' . str_replace('"', '""', $cell) . '"', $row)) . "\n";
        }
        return $output;
    }
}

// Yeni visitor — element-lər dəyişmir
class ValidationVisitor implements ReportVisitor
{
    private array $errors = [];

    public function visitSalesSection(SalesSection $section): void
    {
        if (empty($section->title)) {
            $this->errors[] = "SalesSection title cannot be empty";
        }
        if ($section->totalRevenue < 0) {
            $this->errors[] = "Revenue cannot be negative";
        }
    }

    public function visitChartElement(ChartElement $element): void
    {
        if (empty($element->datasets)) {
            $this->errors[] = "Chart has no datasets";
        }
    }

    public function visitTableElement(TableElement $element): void
    {
        foreach ($element->rows as $i => $row) {
            if (count($row) !== count($element->headers)) {
                $this->errors[] = "Row {$i} column count mismatch";
            }
        }
    }

    public function visitSummaryBlock(SummaryBlock $block): void
    {
        if (empty($block->metrics)) {
            $this->errors[] = "Summary block '{$block->title}' has no metrics";
        }
    }

    public function isValid(): bool { return empty($this->errors); }
    public function getErrors(): array { return $this->errors; }
}

// ─────────────────────────────────────────────
// REPORT — Object Structure, visitor-ı qəbul edir
// ─────────────────────────────────────────────

class Report
{
    /** @var ReportElement[] */
    private array $elements = [];

    public function add(ReportElement $element): self
    {
        $this->elements[] = $element;
        return $this;
    }

    public function accept(ReportVisitor $visitor): void
    {
        foreach ($this->elements as $element) {
            $element->accept($visitor);
        }
    }
}
```

**Usage — Controller:**

```php
<?php

class ReportController extends Controller
{
    public function export(string $reportId, string $format): \Symfony\Component\HttpFoundation\Response
    {
        $report = $this->buildReport($reportId);

        return match ($format) {
            'pdf' => $this->exportPdf($report),
            'csv' => $this->exportCsv($report),
            default => abort(400, "Unsupported format: {$format}"),
        };
    }

    private function exportPdf(Report $report): \Illuminate\Http\Response
    {
        $visitor = new PdfExportVisitor();
        $report->accept($visitor);

        return response($visitor->getOutput())
            ->header('Content-Type', 'application/pdf');
    }

    private function exportCsv(Report $report): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $visitor = new CsvExportVisitor();
        $report->accept($visitor);

        return response()->streamDownload(
            fn() => print($visitor->getCsv()),
            'report.csv',
            ['Content-Type' => 'text/csv']
        );
    }

    private function buildReport(string $reportId): Report
    {
        // Factory ilə report qur
        return (new Report())
            ->add(new SummaryBlock("Q1 Overview", ['Revenue' => '$124,000', 'Orders' => '1,240']))
            ->add(new SalesSection("Monthly Sales", [/* data */], 124000.0))
            ->add(new ChartElement('bar', ['Jan', 'Feb', 'Mar'], [/* datasets */]))
            ->add(new TableElement(['Product', 'Units', 'Revenue'], [/* rows */]));
    }
}
```

## Praktik Tapşırıqlar
1. E-commerce order items üçün Visitor qurun: `PhysicalItem`, `DigitalItem`, `SubscriptionItem` — element-lər; `ShippingCostVisitor`, `TaxCalculationVisitor`, `DiscountVisitor` — visitor-lar; hər item növünə müxtəlif qayda tətbiq olunur
2. `JsonExportVisitor` əlavə edin mövcud report sisteminə — heç bir element class-ı dəyişmədən; bu Visitor-ın Open/Closed Principle üstünlüyünü demonstrasiya edir
3. PHP code AST-i Visitor ilə traverse edin (nikic/php-parser istifadə edirsə): `MethodCallCollector` visitor-u bütün method call-ları toplasın; `DeprecatedFunctionDetector` visitor-u köhnə function-ları tapır
4. `StatisticsVisitor` yaradın: report-u traverse edərkən element sayını, top metrics-i toplar; `getStats()` ilə report haqqında ümumi məlumat qaytarır

## Əlaqəli Mövzular
- [02-strategy.md](02-strategy.md) — fərq: Strategy bir algorithm-u bir context üçün dəyişdirir; Visitor structured hierarchy-yə algorithm əlavə edir
- [../structural/05-composite.md](../structural/05-composite.md) — Visitor tez-tez Composite structure-u traverse etmək üçün istifadə olunur
- [05-iterator.md](05-iterator.md) — structure traverse etmək üçün alternativ; Visitor-da element öz traversal-ını idarə edir
- [03-command.md](03-command.md) — Visitor-un hər visit metodu command kimi modellenə bilər
- [../architecture/02-solid-principles.md](../architecture/02-solid-principles.md) — Open/Closed Principle: Visitor-un əsas üstünlüyü element-ləri dəyişmədən yeni əməliyyat əlavə etmək
