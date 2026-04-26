# Approval Testing (Senior)
## İcmal

**Approval Testing** (həmçinin **Golden Master Testing** adlanır) - **funksiyanın/sistemin çıxışını əvvəlcədən yoxlanılıb "onaylanmış" (approved) fayllarla müqayisə edən** test metodudur. Test zamanı actual output `received` faylına yazılır və `approved` faylla müqayisə edilir.

**İdeya:** çıxışı yazmaq əvəzinə, bir dəfə təsdiq edirsən və sonra dəyişikliklər avtomatik aşkarlanır.

**Nümunə workflow:**

```
1. Test ilk run → "MyTest.received.txt" yaranır
2. Developer fayla baxır, məntiqini yoxlayır
3. Razıdırsa: "MyTest.received.txt" → "MyTest.approved.txt"
4. Sonrakı run-larda received vs approved müqayisəsi
5. Fərq varsa test fail olur, diff göstərilir
```

**Niyə lazımdır?**

- Kompleks çıxışı assert etmək çətindir (JSON, HTML, PDF, Report)
- Expected output-u hand-code etmək əziyyətlidir
- Refactor zamanı regression-ları avtomatik tapmaq
- Human review ilə qərar verilə bilər

## Niyə Vacibdir

- **Kompleks output-ların test edilməsini sadələşdirir:** Email template-lər, PDF invoice-lər, böyük JSON response-lar üçün hər assertion-u əl ilə yazmaq çox vaxt aparır. Approval testing ilə bütün çıxışı bir anda "snapshot" kimi qeyd etmək mümkündür.
- **API schema regression-larını erkən aşkarlayır:** API response strukturu istemeden dəyişdikdə approval test dərhal fail olur. Bu, müştəri (client) tərəfində integration pozulmadan əvvəl problemi tutmağa imkan verir.
- **Kod review prosesini gücləndirir:** Approved faylları Git-ə commit etmək, PR-də output dəyişikliklərini görünən edir. Reviewer yalnız kod yox, faktiki çıxışı da yoxlaya bilər — bu insan gözetimi üçün dəyərlidir.
- **Generated kod keyfiyyətini təmin edir:** Migration generator, scaffolding, ya da report builder yazıldıqda, çıxışı approved fayl kimi saxlamaq əl ilə yoxlamadan qorunma sağlayır. Generator refactor ediləndə regression dərhal görünür.
- **Çətin test edilən side effect-ləri asan yoxlamaq:** Email body-si, rendered HTML view, ya da export fayl kimi vizual çıxışları ənənəvi assertion-larla test etmək çətin olur. Approval testing bu problemin ən praktik həllidir.

## Əsas Anlayışlar

### 1. Approved vs Received Files

**Approved file:** əvvəl yoxlanılıb təsdiq edilmiş etalon çıxış. Git-ə commit olunur.

**Received file:** son test run-da yaradılan actual çıxış. Gitignore olunur.

**Test flow:**

```
test run → received.txt
         ↓
received == approved?  →  PASS
         ↓ fərqli
FAIL + diff tool göstərilir
```

### 2. Golden Master

Golden master = yaxşı bilinən, stable, əvvəl-təsdiq-edilmiş output. Xüsusilə:

- **Complex report**-ların çıxışı
- **Generated code** (scaffolding, migrations)
- **HTML/Email template**-ləri
- **Serialization** çıxışı

### 3. Reviewer Tools

Approval tool-ları diff viewer inteqrasiya edir:

- `meld` (Linux)
- `kdiff3` (cross-platform)
- `Beyond Compare`
- `WinMerge` (Windows)
- VS Code, PhpStorm diff view

### 4. Scrubbers (Təmizləyicilər)

Dinamik data-nı normalize edir:

- Timestamp → `[TIMESTAMP]`
- UUID → `[UUID]`
- IP address → `[IP]`
- Random ID → `[ID]`

### 5. Populyar Kitabxanalar

**PHP:**
- **approvals-php** (Spatie)
- **phpunit-approvals**
- Custom implementation

**Digər:**
- **ApprovalTests.Java/C#/JS/Python**
- **verify** (.NET, C#)
- **jest snapshots** (JS - approval-ın bir növü)

## Praktik Baxış

### Best Practices

1. **Scrubber aggressive qur** - bütün dinamik data-nı normalize et
2. **Approved file-ları gözəl format-la** - diff oxunan olsun
3. **Kiçik approved faylları** - 50 sətirdən az, böyükdürsə böl
4. **Review ciddi alın** - approved file = test expectation
5. **Diff tool inteqrasiya et** - IDE-də avto review
6. **Test adı descriptive** - `InvoiceRendering_UsdCurrency_NoDiscount`

### Anti-Patterns

1. **Auto-approve in CI** - regression-ları keçirər
2. **Scrub etməmək** - flaky test yaranır
3. **Binary approval** - görünməz diff
4. **Giant approval file** - review çətin olur
5. **No human review** - ilk approve göz gəzdirmədən
6. **Frequently changing data** - hər run re-approve zəhmət
7. **`--update` flag CI-da** - dəhşətli anti-pattern

### Faydalı Texnikalar

- **Combination approval** - bir neçə input/output birləşmiş faylda
- **Reporter chain** - PhpStorm > Console > File
- **Named approvals** - bir test bir neçə approve edə bilər
- **Inline approvals** - kiçik output üçün kod içində

### Kitablar/Resurslar

- Llewellyn Falco - Approval Tests yaradıcısı
- **ApprovalTests.com** - multiple language dokumentasiyası
- Emily Bache - "Approval Testing" videoları
- Spatie snapshot package docs

## Nümunələr

### Basic Approval Test

```php
<?php

namespace Tests\Approval;

use PHPUnit\Framework\TestCase;

class InvoiceApprovalTest extends TestCase
{
    private const APPROVAL_DIR = __DIR__ . '/approvals';

    public function testInvoiceRendering(): void
    {
        $invoice = new Invoice([
            'number' => 'INV-001',
            'customer' => 'John Doe',
            'items' => [
                ['name' => 'Laptop', 'price' => 1200, 'qty' => 1],
                ['name' => 'Mouse', 'price' => 25, 'qty' => 2],
            ],
        ]);

        $received = $invoice->render();
        $this->assertApproved('InvoiceApprovalTest.testInvoiceRendering', $received);
    }

    protected function assertApproved(string $name, string $received): void
    {
        if (!is_dir(self::APPROVAL_DIR)) {
            mkdir(self::APPROVAL_DIR, 0755, true);
        }

        $approvedFile = self::APPROVAL_DIR . "/{$name}.approved.txt";
        $receivedFile = self::APPROVAL_DIR . "/{$name}.received.txt";

        file_put_contents($receivedFile, $received);

        if (!file_exists($approvedFile)) {
            $this->fail(sprintf(
                "No approved file found. Review '%s' and rename to '%s' if correct.",
                $receivedFile,
                $approvedFile
            ));
        }

        $approved = file_get_contents($approvedFile);

        if ($approved !== $received) {
            $this->fail(sprintf(
                "Received differs from approved.\nRun: diff %s %s",
                $approvedFile,
                $receivedFile
            ));
        }

        unlink($receivedFile);
    }
}
```

### Spatie Approvals

**Quraşdırma:**

```bash
composer require --dev spatie/phpunit-snapshot-assertions
```

```php
<?php

namespace Tests\Feature;

use Spatie\Snapshots\MatchesSnapshots;
use Tests\TestCase;

class OrderReportTest extends TestCase
{
    use MatchesSnapshots;

    public function testOrderSummaryReport(): void
    {
        $report = (new OrderReportGenerator())->generate([
            'year' => 2024,
            'month' => 6,
        ]);

        $this->assertMatchesSnapshot($report);
    }

    public function testOrderReportAsJson(): void
    {
        $data = OrderReport::forMonth(2024, 6)->toArray();

        $this->assertMatchesJsonSnapshot($data);
    }

    public function testEmailTemplate(): void
    {
        $email = (new WelcomeEmail($user))->render();

        $this->assertMatchesHtmlSnapshot($email);
    }
}
```

## Praktik Tapşırıqlar

### Custom Approval Trait

```php
<?php

namespace Tests\Support;

trait ApprovalTestTrait
{
    protected function approve(string $received, ?string $name = null): void
    {
        $name ??= $this->generateName();
        $approvalDir = dirname((new \ReflectionClass($this))->getFileName()) . '/approvals';

        if (!is_dir($approvalDir)) {
            mkdir($approvalDir, 0755, true);
        }

        $approvedPath = "{$approvalDir}/{$name}.approved.txt";
        $receivedPath = "{$approvalDir}/{$name}.received.txt";

        $scrubbed = $this->scrub($received);

        file_put_contents($receivedPath, $scrubbed);

        if (!file_exists($approvedPath)) {
            $this->fail(
                "\nApproval file does not exist.\n" .
                "Review: {$receivedPath}\n" .
                "If correct, run: mv {$receivedPath} {$approvedPath}"
            );
        }

        $approved = file_get_contents($approvedPath);

        if ($scrubbed === $approved) {
            @unlink($receivedPath);
            $this->assertTrue(true);
            return;
        }

        $this->fail(
            "\nApproval mismatch.\n" .
            "Approved: {$approvedPath}\n" .
            "Received: {$receivedPath}\n" .
            "Review diff and update approved file if change is intentional.\n" .
            "Diff:\n" . $this->makeDiff($approved, $scrubbed)
        );
    }

    protected function scrub(string $content): string
    {
        $patterns = [
            '/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?Z?/' => '[TIMESTAMP]',
            '/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/' => '[DATETIME]',
            '/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i' => '[UUID]',
            '/\b(?:\d{1,3}\.){3}\d{1,3}\b/' => '[IP]',
            '/Bearer [a-zA-Z0-9._\-]+/' => 'Bearer [TOKEN]',
        ];

        foreach ($patterns as $pattern => $replacement) {
            $content = preg_replace($pattern, $replacement, $content);
        }

        return $content;
    }

    protected function generateName(): string
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        foreach ($backtrace as $frame) {
            if (str_starts_with($frame['function'] ?? '', 'test')) {
                $className = basename(str_replace('\\', '/', $frame['class']));
                return "{$className}.{$frame['function']}";
            }
        }
        return 'approval_' . uniqid();
    }

    protected function makeDiff(string $approved, string $received): string
    {
        $approvedLines = explode("\n", $approved);
        $receivedLines = explode("\n", $received);
        $diff = [];

        $max = max(count($approvedLines), count($receivedLines));
        for ($i = 0; $i < $max; $i++) {
            $a = $approvedLines[$i] ?? null;
            $r = $receivedLines[$i] ?? null;
            if ($a !== $r) {
                $diff[] = "- {$a}";
                $diff[] = "+ {$r}";
            }
        }

        return implode("\n", array_slice($diff, 0, 40));
    }
}
```

### Laravel JSON API Approval Test

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ApprovalTestTrait;
use Tests\TestCase;

class OrderApiApprovalTest extends TestCase
{
    use RefreshDatabase, ApprovalTestTrait;

    public function testOrderListEndpointResponse(): void
    {
        $this->travelTo('2024-06-15 12:00:00');

        $user = User::factory()->create(['id' => 1, 'name' => 'John']);
        Order::factory()->count(3)->sequence(
            ['id' => 100, 'user_id' => 1, 'total' => 99.99, 'status' => 'pending'],
            ['id' => 101, 'user_id' => 1, 'total' => 199.99, 'status' => 'completed'],
            ['id' => 102, 'user_id' => 1, 'total' => 49.99, 'status' => 'cancelled'],
        )->create();

        $response = $this->actingAs($user)->getJson('/api/orders');

        $formatted = json_encode(
            $response->json(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
        );

        $this->approve($formatted);
    }
}
```

**approvals/OrderApiApprovalTest.testOrderListEndpointResponse.approved.txt:**

```json
{
    "data": [
        {
            "id": 100,
            "user_id": 1,
            "total": "99.99",
            "status": "pending",
            "created_at": "[TIMESTAMP]",
            "updated_at": "[TIMESTAMP]"
        },
        {
            "id": 101,
            "user_id": 1,
            "total": "199.99",
            "status": "completed",
            "created_at": "[TIMESTAMP]",
            "updated_at": "[TIMESTAMP]"
        },
        {
            "id": 102,
            "user_id": 1,
            "total": "49.99",
            "status": "cancelled",
            "created_at": "[TIMESTAMP]",
            "updated_at": "[TIMESTAMP]"
        }
    ],
    "meta": {
        "total": 3,
        "per_page": 15
    }
}
```

### Email Template Approval

```php
<?php

namespace Tests\Feature;

use App\Mail\InvoiceMail;
use App\Models\Invoice;
use Tests\Support\ApprovalTestTrait;
use Tests\TestCase;

class InvoiceEmailApprovalTest extends TestCase
{
    use ApprovalTestTrait;

    public function testInvoiceEmailRendersCorrectly(): void
    {
        $invoice = Invoice::factory()->make([
            'number' => 'INV-2024-001',
            'customer_name' => 'Ayşə Məmmədova',
            'total' => 1500.00,
            'items' => [
                ['name' => 'Service A', 'amount' => 1000],
                ['name' => 'Service B', 'amount' => 500],
            ],
        ]);

        $email = new InvoiceMail($invoice);
        $rendered = $email->render();

        $normalized = $this->normalizeHtml($rendered);
        $this->approve($normalized);
    }

    private function normalizeHtml(string $html): string
    {
        $html = preg_replace('/\s+/', ' ', $html);
        $html = preg_replace('/>\s+</', ">\n<", $html);
        return trim($html);
    }
}
```

### Generated Code Approval

```php
<?php

namespace Tests\Feature;

class MigrationGeneratorApprovalTest extends TestCase
{
    use ApprovalTestTrait;

    public function testGeneratesUsersMigration(): void
    {
        $generator = new MigrationGenerator();

        $code = $generator->generate([
            'table' => 'users',
            'columns' => [
                'id' => 'bigIncrements',
                'name' => ['string', 255],
                'email' => ['string', 255, 'unique' => true],
                'created_at' => 'timestamp',
            ],
        ]);

        $this->approve($code);
    }
}
```

Approved fayl:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 255);
            $table->string('email', 255)->unique();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
```

### CLI Approval Workflow

```bash
#!/bin/bash
# scripts/approve.sh
# Update approved files from received

for received in $(find tests/ -name "*.received.txt"); do
    approved="${received%.received.txt}.approved.txt"

    echo "Reviewing: $received"
    diff -u "$approved" "$received" || true

    read -p "Approve? (y/n) " answer
    if [[ "$answer" == "y" ]]; then
        mv "$received" "$approved"
        echo "Approved: $approved"
    fi
done
```

### Gitignore Setup

```gitignore
# Approval tests - received files are never committed
**/*.received.txt
**/*.received.json
**/*.received.html
```

## Ətraflı Qeydlər

### 1. Approval testing nədir və necə işləyir?

Test run zamanı çıxış `received` faylına yazılır və `approved` faylla müqayisə edilir. Fərq varsa test fail olur. Developer received fayla baxır, düzgündürsə approved fayla çevirir. Bu minimum əl ilə assertion tələb edir.

### 2. Snapshot testing ilə approval testing fərqi var?

Çox az. Snapshot testing əsasən **JS/Jest** ekosistemindən gəlir və avtomatik update-ə meyillidir. Approval testing **human review**-ni vurğulayır - ilk approve manual edilməlidir. Praktiki olaraq eyni texnikadır.

### 3. Approval testing hansı hallarda idealdır?

- **Kompleks output:** HTML, JSON, PDF, email templates
- **Generated code:** migrations, scaffolding, API clients
- **Reports:** PDF invoices, analytics reports
- **Legacy characterization:** mövcud sistem davranışı
- **Regression testing:** UI output, API response schema

### 4. Scrubber (təmizləyici) nə üçün lazımdır?

Dinamik data-nı sabitləyir. Timestamp, UUID, random ID hər run-da dəyişir - bunlar test-i flaky edər. Scrubber `2024-06-15 10:30:45` → `[DATETIME]` çevirir, müqayisə dayanıqlı olur.

### 5. Approved faylları git-ə commit etmək lazımdır?

Bəli! **Approved files** version control-un hissəsidir - test data kimidir. **Received files** git-ignore olunmalıdır. Bu sayədə hər developer eyni approved versiyalarla işləyir.

### 6. Yanlış approved fayl commit edilərsə nə olur?

- Refactor zamanı bug-ı test pass olaraq keçə bilər
- **Həll:** approved faylları code review-da diqqətlə yoxlamaq
- PR-də approved file dəyişikliyi böyük məsuliyyətlə baxılır
- **"This test file changed"** - warning verən CI rule əlavə etmək

### 7. Ne zaman approval testing istifadə etməmək lazımdır?

- **Simple assertion** - `assertEquals(5, add(2,3))` approval lazım deyil
- **Non-deterministic output** - random, threading, network
- **Binary output** (image, video) - diff görünməz olur
- **Frequently changing output** - hər dəyişiklikdə re-approve əziyyətli

### 8. Diff review prosesi necə olmalıdır?

- **Small diffs** - 1-10 sətir asan review
- **Large diffs** - component-ləri ayrı approval-a böl
- **Automated diff tool** - IDE-də inteqrasiya
- **PR comment** - approved file dəyişikliyinə açıqlama yaz

### 9. Faylları kim approve etməlidir?

- **Developer** - həmin dəyişikliyi edən
- **Code reviewer** - PR-də diff-i yoxlayan
- **Product owner** - business-critical output üçün
- **Domain expert** - kompleks report-lar üçün

### 10. CI-da approval testing necə qurulur?

- Received file yaranarsa test fail
- CI-da received faylları artifact kimi yüklə (Jenkins, GitHub Actions)
- Developer local-da review edib commit edir
- Auto-approve **CI-da qətiyyən etməyin**

## Əlaqəli Mövzular

- [Characterization Tests (Senior)](31-characterization-tests.md)
- [Snapshot Testing (Senior)](25-snapshot-testing.md)
- [Regression, Smoke və Sanity Testing (Senior)](34-regression-smoke-sanity.md)
- [Testing Third-Party Services (Senior)](28-testing-third-party.md)
- [Test Data Management (Senior)](33-test-data-management.md)
- [Contract Testing (Senior)](24-contract-testing.md)
