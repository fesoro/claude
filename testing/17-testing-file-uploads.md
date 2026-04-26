# Testing File Uploads (Middle)
## İcmal

File upload testing, istifadəçinin upload etdiyi faylların (image, PDF, CSV, video) doğru
validate olunduğunu, saxlandığını və processing-dən keçdiyini yoxlamaq prosesidir. Real
fayl sistemi ilə test etmək yavaş və yan təsirlidir (cleanup lazımdır, disk doldurur);
Laravel bunun üçün `Storage::fake()` və `UploadedFile::fake()` helper-lərini təqdim edir.

### Niyə File Upload Testing Vacibdir?

1. **Security** - Malicious file (PHP shell, XSS HTML) upload edilə bilər
2. **Validation** - Size, mime type, dimensions yoxlanmalıdır
3. **Storage correctness** - Fayl doğru disk və path-da saxlanır?
4. **Processing** - Image resize, PDF parse, virus scan düzgün işləyir?
5. **Cleanup** - Cancelled upload-lar silinir?

## Niyə Vacibdir

- **Security zəifliklərinin erkən aşkarlanması** — PHP shell, XSS payload olan faylların upload edilməsi real hücum vektordur; `mimetypes` yoxlaması, re-encode testi olmadan bu boşluq production-da qalır.
- **Real storage-siz sürətli test** — S3-ə real request göndərərək test yazmaq həm yavaş, həm bahalı, həm flaky-dir; `Storage::fake('s3')` eyni API-ni saxlayaraq bütün bunları aradan qaldırır.
- **Validation qaydalarının eksiksiz yoxlanması** — Size, mime type, dimensions validation-ların hər biri ayrıca test edilmədikdə birinin boşluğu production-da görünür; hər rule üçün test əsas qaydadır.
- **Cleanup logic-inin doğrulanması** — Köhnə avatar silinmədikdə orphan fayllar disk-i doldurur; yeni upload zamanı köhnə faylın silinməsini test etmək storage leak-in qarşısını alır.
- **CSV/Excel import kimi biznes kritik flow-lar** — Import əməliyyatları çox zaman fayl upload + queue dispatch birləşimidir; hər addımın test edilməsi məlumat itkisinin qarşısını alır.

## Əsas Anlayışlar

### Storage::fake()

```php
Storage::fake('public');        // "public" disk-i fake
Storage::fake('s3');            // S3-ü fake (heç bir AWS call getmir)
Storage::fake();                // Default disk-i fake

// Assertion
Storage::disk('public')->assertExists('avatars/user1.jpg');
Storage::disk('public')->assertMissing('avatars/user2.jpg');
Storage::disk('public')->assertDirectoryEmpty('temp');
```

### UploadedFile::fake()

```php
// Generic file
UploadedFile::fake()->create('report.pdf', 500); // 500 KB

// Image (real PNG/JPG generated)
UploadedFile::fake()->image('avatar.jpg', 400, 300); // width x height

// Custom mime
UploadedFile::fake()->createWithContent('data.csv', "name,age\nAli,30");
```

### Validation Rules Matrix

| Rule | Məqsəd |
|------|--------|
| `file` | Upload mövcuddur |
| `image` | jpg, jpeg, png, bmp, gif, svg, webp |
| `mimes:pdf,docx` | Extension-lar |
| `mimetypes:image/jpeg` | MIME (daha sərt) |
| `max:2048` | KB-də ölçü |
| `dimensions:min_width=100` | Image ölçüləri |

## Praktik Baxış

### Best Practices

- **Hər test-də `Storage::fake()`** - Real fayl sistemi problemə yol açır
- **Hər validation rule üçün ayrı test** - size, type, dimensions
- **Negative path-ları test edin** - Invalid file, too big, wrong mime
- **Cleanup logic-ini test edin** - Köhnə avatar silinir?
- **Real MIME check** - `mimetypes` `mimes`-dən güclüdür
- **Re-encode images** - Security üçün orijinal upload-u birbaşa serve etməyin

### Anti-Patterns

- **Real S3-ə test zamanı bağlanmaq** - Slow, expensive, flaky
- **Yalnız happy path** - Security test-ləri unutmaq
- **Hardcoded path-lar** - Production path test-də istifadə olunursa datanı silə bilər
- **File tearDown etməmək** - Local test-lər disk doldurur
- **Yalnız extension yoxlamaq** - `shell.jpg` adlı PHP fayl keçə bilər
- **User input-u path-da istifadə** - Path traversal (`../../etc/passwd`)

## Nümunələr

### Basic Upload Test

```php
public function test_user_can_upload_avatar(): void
{
    Storage::fake('public');

    $file = UploadedFile::fake()->image('avatar.jpg', 200, 200);

    $this->actingAs($user = User::factory()->create())
        ->postJson('/api/profile/avatar', ['avatar' => $file])
        ->assertOk();

    Storage::disk('public')->assertExists("avatars/{$user->id}.jpg");
}
```

### Validation Test

```php
public function test_large_file_is_rejected(): void
{
    Storage::fake('public');

    $file = UploadedFile::fake()->create('huge.zip', 5000); // 5 MB (max 2 MB)

    $this->actingAs(User::factory()->create())
        ->postJson('/api/upload', ['file' => $file])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('file');

    Storage::disk('public')->assertDirectoryEmpty('/');
}
```

## Praktik Tapşırıqlar

### 1. Controller

```php
// app/Http/Controllers/AvatarController.php
class AvatarController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'avatar' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png',
                'max:2048', // 2 MB
                'dimensions:min_width=100,min_height=100,max_width=2000',
            ],
        ]);

        $user = $request->user();

        // Delete old avatar if exists
        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $path = $request->file('avatar')->storeAs(
            'avatars',
            "{$user->id}.{$request->file('avatar')->extension()}",
            'public'
        );

        $user->update(['avatar_path' => $path]);

        return response()->json([
            'url' => Storage::disk('public')->url($path),
        ]);
    }

    public function destroy(Request $request)
    {
        $user = $request->user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
            $user->update(['avatar_path' => null]);
        }

        return response()->noContent();
    }
}
```

### 2. Comprehensive Feature Test

```php
namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AvatarUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_upload_valid_image(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $file = UploadedFile::fake()->image('avatar.jpg', 500, 500);

        $response = $this->actingAs($user)
            ->postJson('/api/profile/avatar', ['avatar' => $file]);

        $response->assertOk()
            ->assertJsonStructure(['url']);

        Storage::disk('public')->assertExists("avatars/{$user->id}.jpg");

        $this->assertDatabaseHas('users', [
            'id'          => $user->id,
            'avatar_path' => "avatars/{$user->id}.jpg",
        ]);
    }

    public function test_guest_cannot_upload(): void
    {
        Storage::fake('public');

        $this->postJson('/api/profile/avatar', [
            'avatar' => UploadedFile::fake()->image('x.jpg'),
        ])->assertUnauthorized();

        Storage::disk('public')->assertDirectoryEmpty('avatars');
    }

    public function test_file_larger_than_2mb_rejected(): void
    {
        Storage::fake('public');
        $file = UploadedFile::fake()->image('big.jpg')->size(3000); // 3 MB

        $this->actingAs(User::factory()->create())
            ->postJson('/api/profile/avatar', ['avatar' => $file])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('avatar');
    }

    public function test_non_image_file_rejected(): void
    {
        Storage::fake('public');
        $file = UploadedFile::fake()->create('document.pdf', 500);

        $this->actingAs(User::factory()->create())
            ->postJson('/api/profile/avatar', ['avatar' => $file])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('avatar');
    }

    public function test_image_too_small_rejected(): void
    {
        Storage::fake('public');
        $file = UploadedFile::fake()->image('tiny.jpg', 50, 50); // min 100

        $this->actingAs(User::factory()->create())
            ->postJson('/api/profile/avatar', ['avatar' => $file])
            ->assertUnprocessable();
    }

    public function test_uploading_new_avatar_deletes_old_one(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['avatar_path' => 'avatars/old.jpg']);
        Storage::disk('public')->put('avatars/old.jpg', 'old content');

        $this->actingAs($user)->postJson('/api/profile/avatar', [
            'avatar' => UploadedFile::fake()->image('new.jpg', 500, 500),
        ])->assertOk();

        Storage::disk('public')->assertMissing('avatars/old.jpg');
        Storage::disk('public')->assertExists("avatars/{$user->id}.jpg");
    }

    public function test_user_can_delete_avatar(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['avatar_path' => 'avatars/x.jpg']);
        Storage::disk('public')->put('avatars/x.jpg', 'content');

        $this->actingAs($user)
            ->deleteJson('/api/profile/avatar')
            ->assertNoContent();

        Storage::disk('public')->assertMissing('avatars/x.jpg');
        $this->assertNull($user->fresh()->avatar_path);
    }
}
```

### 3. Multiple File Upload Test

```php
public function test_user_can_upload_multiple_images_for_gallery(): void
{
    Storage::fake('public');

    $files = [
        UploadedFile::fake()->image('1.jpg', 800, 600),
        UploadedFile::fake()->image('2.jpg', 800, 600),
        UploadedFile::fake()->image('3.jpg', 800, 600),
    ];

    $this->actingAs(User::factory()->create())
        ->postJson('/api/gallery', ['images' => $files])
        ->assertCreated();

    $this->assertSame(3, Storage::disk('public')->allFiles('gallery') |> count());
}
```

### 4. CSV Import Test

```php
// app/Http/Controllers/ImportController.php
public function users(Request $request)
{
    $request->validate([
        'file' => 'required|file|mimes:csv,txt|max:10240',
    ]);

    $path = $request->file('file')->store('imports', 'local');

    ImportUsersJob::dispatch($path);

    return response()->json(['message' => 'Import queued']);
}

// tests/Feature/ImportControllerTest.php
public function test_csv_upload_queues_import_job(): void
{
    Storage::fake('local');
    Queue::fake();

    $csv = UploadedFile::fake()->createWithContent(
        'users.csv',
        "name,email\nAli,ali@example.com\nVeli,veli@example.com"
    );

    $this->actingAs(User::factory()->admin()->create())
        ->postJson('/api/import/users', ['file' => $csv])
        ->assertOk();

    Queue::assertPushed(ImportUsersJob::class);
    Storage::disk('local')->assertExists('imports/' . $csv->hashName());
}
```

### 5. S3 Upload Test

```php
public function test_file_uploads_to_s3(): void
{
    Storage::fake('s3');

    $file = UploadedFile::fake()->create('report.pdf', 200);

    $this->actingAs(User::factory()->create())
        ->postJson('/api/reports', ['file' => $file])
        ->assertCreated();

    Storage::disk('s3')->assertExists('reports/' . $file->hashName());

    // Note: URL is constructed, not actually calling S3
    $url = Storage::disk('s3')->url('reports/' . $file->hashName());
    $this->assertStringStartsWith('http', $url);
}
```

### 6. Image Processing Test

```php
// Controller uses Intervention Image to resize
public function store(Request $request)
{
    $image = Image::make($request->file('photo'))
        ->resize(800, 600)
        ->encode('jpg', 80);

    Storage::disk('public')->put(
        $path = 'photos/' . Str::uuid() . '.jpg',
        (string) $image
    );

    return response()->json(['path' => $path]);
}

// Test
public function test_uploaded_image_is_resized(): void
{
    Storage::fake('public');

    $file = UploadedFile::fake()->image('huge.jpg', 3000, 2000);

    $this->actingAs(User::factory()->create())
        ->postJson('/api/photos', ['photo' => $file])
        ->assertCreated();

    $files = Storage::disk('public')->allFiles('photos');
    $this->assertCount(1, $files);

    // Assert dimension after resize
    $content = Storage::disk('public')->get($files[0]);
    $tempPath = tempnam(sys_get_temp_dir(), 'img');
    file_put_contents($tempPath, $content);
    [$width, $height] = getimagesize($tempPath);

    $this->assertSame(800, $width);
    $this->assertSame(600, $height);
}
```

### 7. Download Test

```php
public function test_user_can_download_their_invoice(): void
{
    Storage::fake('local');
    $user    = User::factory()->create();
    $invoice = Invoice::factory()->for($user)->create(['path' => 'invoices/1.pdf']);
    Storage::disk('local')->put('invoices/1.pdf', '%PDF-1.4 content');

    $response = $this->actingAs($user)
        ->get("/invoices/{$invoice->id}/download");

    $response->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertHeader('content-disposition', 'attachment; filename=invoice-1.pdf');
}
```

### 8. Malicious File Test (Security)

```php
public function test_php_file_cannot_be_uploaded_as_image(): void
{
    Storage::fake('public');

    // Fake image whose underlying content contains PHP
    $malicious = UploadedFile::fake()->createWithContent(
        'shell.jpg',
        '<?php system($_GET["c"]); ?>'
    );

    $this->actingAs(User::factory()->create())
        ->postJson('/api/profile/avatar', ['avatar' => $malicious])
        ->assertUnprocessable();

    Storage::disk('public')->assertDirectoryEmpty('avatars');
}
```

## Ətraflı Qeydlər

**Q1: `Storage::fake()` nə edir?**
A: Disk-i in-memory fake ilə əvəz edir. Fayllar real disk-ə yazılmır; test bitəndə
avtomatik təmizlənir.

**Q2: `UploadedFile::fake()->image()` real image generate edir?**
A: Bəli. GD extension istifadə edərək kiçik real PNG/JPG yaradır. Bu ölçü və mime
yoxlamaları işə salır.

**Q3: `create()` və `image()` fərqi?**
A: `create('file.pdf', 500)` — generic fayl, mime extension-dan götürülür.
`image('a.jpg', 800, 600)` — real image with dimensions.

**Q4: `mimes` və `mimetypes` validation-ının fərqi?**
A: `mimes:pdf` — extension-a baxır (zəif). `mimetypes:application/pdf` — real MIME-a
baxır (güclü, faylın daxilinə əsasən).

**Q5: File size necə validate olunur?**
A: `max:2048` KB-dəki maksimum ölçünü göstərir (2 MB).

**Q6: S3 upload-ı necə test edirik, həqiqətən S3-ə bağlanırıq?**
A: Xeyr. `Storage::fake('s3')` istifadə olunur — heç bir real AWS request getmir,
amma API eyni qalır.

**Q7: `storeAs` və `store` fərqi?**
A: `store('dir')` — random hash-lı ad generate edir. `storeAs('dir', 'name.jpg')` —
konkret ad istifadə edir.

**Q8: Malicious upload-a qarşı hansı müdafiələr?**
A: MIME type check, filename sanitize, size limit, virus scan (ClamAV),
re-encode images (GD/Imagick), public disk-də PHP execution-u qadağa.

**Q9: Test-dən sonra real dosya qalırmı?**
A: `Storage::fake()` istifadə olunursa — xeyr. Əks halda `tearDown`-da silmək lazımdır.

**Q10: Böyük video upload-u (chunked) necə test olunur?**
A: Chunks ayrı upload edilir; backend-də re-assemble. Hər chunk ayrı test
olunur, sonra final file assembly test edilir.

## Əlaqəli Mövzular

- [Integration Testing (Junior)](03-integration-testing.md)
- [Feature Testing (Junior)](04-feature-testing.md)
- [Mocking (Middle)](07-mocking.md)
- [Testing Authentication & Authorization (Middle)](18-testing-authentication.md)
- [Security Testing (Senior)](21-security-testing.md)
