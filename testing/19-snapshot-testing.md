# Snapshot Testing

## Nədir? (What is it?)

Snapshot testing, test-in nəticəsini ilk dəfə "snapshot" (anlıq şəkil) olaraq saxlayıb,
sonrakı test run-larında nəticəni bu snapshot ilə müqayisə edən test yanaşmasıdır.
Əgər nəticə snapshot ilə eyni deyilsə, test fail olur - ya bug tapılıb, ya da snapshot
yenilənməlidir.

Bu yanaşma əsasən mürəkkəb output-u olan kodlar üçün faydalıdır: HTML render, JSON response,
email template, PDF content, konfiqurasiya faylları. Hər dəfə bütün output-u manual assert
etmək əvəzinə, snapshot bunu avtomatik edir.

### Niyə Snapshot Testing Vacibdir?

1. **Mürəkkəb output-u asan test etmək** - Böyük JSON/HTML-i manual assert lazım deyil
2. **Regression detection** - Output-dakı hər dəyişiklik tutulur
3. **Sürətli test yazmaq** - İlk run snapshot yaradır, manual assert lazım deyil
4. **Living documentation** - Snapshot faylları output-un nə olduğunu göstərir
5. **Refactoring güvəni** - Output dəyişmədiyini avtomatik yoxlayır

## Əsas Konseptlər (Key Concepts)

### Snapshot Testing Workflow

```
İlk run:
  1. Test icra olunur
  2. Nəticə snapshot faylına yazılır
  3. Test PASS olur
  4. Snapshot git-ə commit edilir

Sonrakı run-lar:
  1. Test icra olunur
  2. Nəticə mövcud snapshot ilə müqayisə olunur
  3. Eynidir → PASS
  4. Fərqlidir → FAIL (intentional dəyişiklik? Bug?)

Update:
  1. Dəyişiklik gözlənilən idi
  2. --update-snapshots flag ilə yenilə
  3. Yeni snapshot-u review et
  4. Git-ə commit et
```

### Nə Zaman İstifadə Etməli

```
YAXŞI istifadə halları:
  ✓ API response JSON strukturu
  ✓ HTML/email template render
  ✓ PDF/report content
  ✓ Configuration output
  ✓ Serialized data structures
  ✓ CLI command output

PIS istifadə halları:
  ✗ Timestamp ehtiva edən output (hər dəfə dəyişir)
  ✗ Random data olan nəticələr
  ✗ Çox böyük output (MB-larla)
  ✗ Binary files
  ✗ Sadə equality check-lər (assertEquals daha yaxşı)
```

### Snapshot vs Traditional Assert

```php
// Traditional - hər field manual
$response->assertJson([
    'id' => 1,
    'name' => 'John',
    'email' => 'john@test.com',
    'roles' => ['admin', 'editor'],
    'settings' => [
        'theme' => 'dark',
        'notifications' => true,
        'language' => 'en',
    ],
    // ... 50+ field
]);

// Snapshot - bütün output avtomatik
$this->assertMatchesJsonSnapshot($response->json());
// İlk dəfə snapshot yaranır, sonra avtomatik müqayisə
```

## Praktiki Nümunələr (Practical Examples)

### spatie/phpunit-snapshot-assertions Quraşdırma

```bash
composer require --dev spatie/phpunit-snapshot-assertions
```

### Əsas İstifadə

```php
<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Spatie\Snapshots\MatchesSnapshots;

class UserSerializerTest extends TestCase
{
    use MatchesSnapshots;

    /** @test */
    public function it_serializes_user_to_json(): void
    {
        $user = new UserData(
            id: 1,
            name: 'John Doe',
            email: 'john@example.com',
            roles: ['admin', 'editor'],
            settings: new UserSettings(
                theme: 'dark',
                language: 'en',
                notifications: true,
            ),
        );

        $json = $user->toJson();

        $this->assertMatchesJsonSnapshot($json);
    }

    /** @test */
    public function it_renders_user_profile_html(): void
    {
        $html = view('profile', [
            'user' => new UserData(
                id: 1,
                name: 'John Doe',
                email: 'john@example.com',
                roles: ['admin'],
                settings: new UserSettings('dark', 'en', true),
            ),
        ])->render();

        $this->assertMatchesHtmlSnapshot($html);
    }

    /** @test */
    public function it_generates_correct_xml_export(): void
    {
        $exporter = new UserXmlExporter();
        $xml = $exporter->export([
            ['id' => 1, 'name' => 'John'],
            ['id' => 2, 'name' => 'Jane'],
        ]);

        $this->assertMatchesXmlSnapshot($xml);
    }

    /** @test */
    public function it_outputs_correct_text(): void
    {
        $report = new ReportGenerator();
        $output = $report->generateTextReport(2024, 1);

        $this->assertMatchesTextSnapshot($output);
    }
}
```

### Snapshot Fayl Nümunəsi

```
tests/Unit/__snapshots__/
├── UserSerializerTest__it_serializes_user_to_json__1.json
├── UserSerializerTest__it_renders_user_profile_html__1.html
├── UserSerializerTest__it_generates_correct_xml_export__1.xml
└── UserSerializerTest__it_outputs_correct_text__1.txt
```

```json
// UserSerializerTest__it_serializes_user_to_json__1.json
{
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "roles": [
        "admin",
        "editor"
    ],
    "settings": {
        "theme": "dark",
        "language": "en",
        "notifications": true
    }
}
```

## PHP/Laravel ilə Tətbiq (Implementation with PHP/Laravel)

### API Response Snapshot Testing

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Snapshots\MatchesSnapshots;
use Tests\TestCase;

class ApiResponseSnapshotTest extends TestCase
{
    use RefreshDatabase, MatchesSnapshots;

    /** @test */
    public function post_index_response_matches_snapshot(): void
    {
        $user = User::factory()->create([
            'id' => 1,
            'name' => 'Test User',
        ]);

        Post::factory()->create([
            'id' => 1,
            'user_id' => $user->id,
            'title' => 'First Post',
            'body' => 'Content of first post.',
            'status' => 'published',
            'created_at' => '2024-01-15 10:00:00',
            'updated_at' => '2024-01-15 10:00:00',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/posts');

        // Bütün response structure snapshot ilə yoxlanır
        $this->assertMatchesJsonSnapshot($response->getContent());
    }

    /** @test */
    public function validation_error_response_matches_snapshot(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/posts', [
                'title' => '', // boş
                // body yoxdur
            ]);

        $this->assertMatchesJsonSnapshot($response->getContent());
    }
}
```

### Dynamic Data ilə Snapshot

```php
<?php

namespace Tests\Feature;

use Spatie\Snapshots\MatchesSnapshots;
use Tests\TestCase;

class DynamicDataSnapshotTest extends TestCase
{
    use MatchesSnapshots;

    /** @test */
    public function response_structure_matches_with_dynamic_data(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/posts/{$post->id}");

        // Dynamic data-nı normalize et
        $data = $response->json();
        $data['data']['id'] = 'NORMALIZED_ID';
        $data['data']['user_id'] = 'NORMALIZED_ID';
        $data['data']['created_at'] = 'NORMALIZED_TIMESTAMP';
        $data['data']['updated_at'] = 'NORMALIZED_TIMESTAMP';

        $this->assertMatchesJsonSnapshot(json_encode($data, JSON_PRETTY_PRINT));
    }

    /**
     * Custom snapshot driver ilə dynamic data-nı handle etmək
     */
    /** @test */
    public function using_custom_snapshot_with_wildcards(): void
    {
        $response = $this->getJson('/api/status');

        $data = $response->json();

        // Yalnız structure-ı yoxla, dəyərləri yox
        $structure = $this->extractStructure($data);
        $this->assertMatchesJsonSnapshot(json_encode($structure));
    }

    private function extractStructure(array $data): array
    {
        $structure = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $structure[$key] = $this->extractStructure($value);
            } else {
                $structure[$key] = gettype($value);
            }
        }
        return $structure;
    }
}
```

### Email Template Snapshot

```php
<?php

namespace Tests\Feature;

use App\Mail\WelcomeEmail;
use App\Models\User;
use Spatie\Snapshots\MatchesSnapshots;
use Tests\TestCase;

class EmailSnapshotTest extends TestCase
{
    use MatchesSnapshots;

    /** @test */
    public function welcome_email_html_matches_snapshot(): void
    {
        $user = new User([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $mailable = new WelcomeEmail($user);
        $html = $mailable->render();

        $this->assertMatchesHtmlSnapshot($html);
    }

    /** @test */
    public function invoice_email_matches_snapshot(): void
    {
        $order = new OrderData(
            id: 'ORD-001',
            items: [
                new OrderItem('Product A', 2, 29.99),
                new OrderItem('Product B', 1, 49.99),
            ],
            total: 109.97,
        );

        $mailable = new InvoiceEmail($order);
        $html = $mailable->render();

        $this->assertMatchesHtmlSnapshot($html);
    }
}
```

### CLI Command Output Snapshot

```php
<?php

namespace Tests\Feature;

use Spatie\Snapshots\MatchesSnapshots;
use Tests\TestCase;

class CommandOutputSnapshotTest extends TestCase
{
    use MatchesSnapshots;

    /** @test */
    public function status_command_output_matches_snapshot(): void
    {
        $this->artisan('app:status')
            ->assertSuccessful();

        // Artisan output capture
        $output = $this->artisan('app:status')->getOutput();

        // Normalize dynamic parts
        $normalized = preg_replace(
            '/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/',
            'YYYY-MM-DD HH:MM:SS',
            $output
        );

        $this->assertMatchesTextSnapshot($normalized);
    }
}
```

### Snapshot Update Workflow

```bash
# Bütün snapshot-ları yenilə
vendor/bin/phpunit --update-snapshots

# Yalnız müəyyən test-in snapshot-ını yenilə
vendor/bin/phpunit --filter=test_name --update-snapshots

# Snapshot fayllarını review et
git diff tests/__snapshots__/

# Yenilənmiş snapshot-ları commit et
git add tests/__snapshots__/
git commit -m "Update test snapshots after API response change"
```

## Interview Sualları

### 1. Snapshot testing nədir?
**Cavab:** Test nəticəsini ilk dəfə fayla saxlayıb (snapshot), sonrakı run-larda bu fayla qarşı müqayisə edən test yanaşmasıdır. Fərq varsa test fail olur. Mürəkkəb output-u (JSON, HTML, email) asan test etmək üçün istifadə olunur. İlk run snapshot yaradır, sonrakılar yoxlayır.

### 2. Snapshot testing nə zaman istifadə olunmalıdır?
**Cavab:** Mürəkkəb output-u olan kodlarda: API response, HTML template, email content, report generation, serialized data. Sadə dəyər müqayisəsi üçün assertEquals daha yaxşıdır. Dynamic data (timestamp, random) olan output-da normalization lazımdır.

### 3. Snapshot test fail olanda nə etmək lazımdır?
**Cavab:** İki sual soruşulmalıdır: 1) Dəyişiklik gözlənilən idi? Əgər bəli - snapshot-u yeniləyin: `--update-snapshots`, review edin, commit edin. 2) Dəyişiklik gözlənilməz idi? Bu bug-dur - kodu fix edin. Heç vaxt review etmədən snapshot yeniləməyin.

### 4. Dynamic data (timestamp, ID) snapshot testlərdə necə idarə edilir?
**Cavab:** Normalization - dynamic dəyərləri sabit placeholder ilə əvəz edin (timestamp → NORMALIZED). Fixed factory data istifadə edin (create(['id' => 1])). Carbon::setTestNow() ilə vaxtı fix edin. Structure-only snapshot - yalnız key-ləri/type-ları yoxlayın, dəyərləri yox.

### 5. Snapshot faylları git-ə commit edilməlidirmi?
**Cavab:** Bəli, mütləq commit edilməlidir. Snapshot faylları test-in gözlənilən nəticəsini saxlayır. Commit edilməsə, hər developer-in maşınında fərqli snapshot yaranacaq. PR review-da snapshot dəyişikliklərini yoxlamaq vacibdir.

### 6. Snapshot testing-in çatışmazlıqları nələrdir?
**Cavab:** 1) False positives - kiçik dəyişiklik bütün snapshot-u pozur, 2) Lazy testing - nəticəni anlamadan snapshot yaratmaq, 3) Böyük snapshot faylları - review çətinləşir, 4) Dynamic data problemi - normalization lazımdır, 5) Snapshot blindness - developer dəyişikliyi review etmədən yeniləyir.

## Best Practices / Anti-Patterns

### Best Practices

1. **Snapshot-ları review edin** - Update etmədən əvvəl dəyişikliyi oxuyun
2. **Dynamic data normalize edin** - Timestamp, ID, random dəyərləri fix edin
3. **Kiçik snapshot-lar saxlayın** - Böyük snapshot review çətindir
4. **Git-ə commit edin** - Snapshot faylları kod-un hissəsidir
5. **Traditional assert ilə birləşdirin** - Kritik field-lər üçün explicit assert
6. **Adlandırma convention** - Snapshot fayl adları test-i identifikasiya etsin

### Anti-Patterns

1. **Blind update** - Nəticəni oxumadan `--update-snapshots` işlətmək
2. **Hər şeyi snapshot ilə test etmək** - Sadə check-lər üçün assertEquals
3. **Dynamic data normalize etməmək** - Hər run-da fail olan snapshot
4. **Çox böyük snapshot** - MB-larla snapshot fayl
5. **Snapshot-ları .gitignore-a əlavə etmək** - Commit edilməlidir
6. **Yalnız snapshot-a güvənmək** - Behavior test-ləri də lazımdır
