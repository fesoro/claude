# Fayl Storage (File Storage)

## Giris

Mueyyenlesmis bir tetbiqde fayllarla islemek - yuklemek, saxlamaq, oxumaq ve silmek - en vacib emeliyyatlardan biridir. Istifadeciler profil sekilleri yuklemelidir, senedler paylasmalari, ve s. Spring ve Laravel bu meseleye ferqli yanasmalar teklif edir: Spring asagi seviyyeli `MultipartFile` ve `Resource` abstraksiyalari uezerine qurulanda, Laravel yukek seviyyeli `Storage` facade ve "disk" konsepsiyasi ile isleyir.

## Spring-de istifadesi

### MultipartFile ile fayl yukleme

Spring-de fayl yukleme ucun `MultipartFile` interfeysi istifade olunur:

```java
@RestController
@RequestMapping("/api/files")
public class FileUploadController {

    private final String uploadDir = "/var/uploads/";

    @PostMapping("/upload")
    public ResponseEntity<Map<String, String>> uploadFile(
            @RequestParam("file") MultipartFile file) {

        if (file.isEmpty()) {
            return ResponseEntity.badRequest()
                .body(Map.of("error", "Fayl bos ola bilmez"));
        }

        try {
            // Unikal fayl adi yaradiriq
            String originalName = file.getOriginalFilename();
            String extension = originalName.substring(originalName.lastIndexOf("."));
            String newFileName = UUID.randomUUID() + extension;

            // Faylin saxlanma yolunu mueyyen edirik
            Path targetPath = Path.of(uploadDir, newFileName);
            Files.createDirectories(targetPath.getParent());

            // Faylin mezmununu diskde saxlayiriq
            Files.copy(file.getInputStream(), targetPath,
                StandardCopyOption.REPLACE_EXISTING);

            return ResponseEntity.ok(Map.of(
                "fileName", newFileName,
                "size", String.valueOf(file.getSize()),
                "contentType", file.getContentType()
            ));
        } catch (IOException e) {
            return ResponseEntity.status(500)
                .body(Map.of("error", "Fayl yukleme ugursuz oldu"));
        }
    }
}
```

### Birden cox fayl yukleme

```java
@PostMapping("/upload-multiple")
public ResponseEntity<List<String>> uploadMultipleFiles(
        @RequestParam("files") MultipartFile[] files) {

    List<String> uploadedFiles = new ArrayList<>();

    for (MultipartFile file : files) {
        if (!file.isEmpty()) {
            try {
                String newFileName = UUID.randomUUID() + "_" + file.getOriginalFilename();
                Path targetPath = Path.of(uploadDir, newFileName);
                Files.copy(file.getInputStream(), targetPath);
                uploadedFiles.add(newFileName);
            } catch (IOException e) {
                // Loqlayiriq ve davam edirik
            }
        }
    }

    return ResponseEntity.ok(uploadedFiles);
}
```

### Fayl endirme (download)

```java
@GetMapping("/download/{fileName}")
public ResponseEntity<Resource> downloadFile(@PathVariable String fileName) {
    try {
        Path filePath = Path.of(uploadDir, fileName);
        Resource resource = new UrlResource(filePath.toUri());

        if (!resource.exists()) {
            return ResponseEntity.notFound().build();
        }

        // Content-Type mueyyen edirik
        String contentType = Files.probeContentType(filePath);
        if (contentType == null) {
            contentType = "application/octet-stream";
        }

        return ResponseEntity.ok()
            .contentType(MediaType.parseMediaType(contentType))
            .header(HttpHeaders.CONTENT_DISPOSITION,
                "attachment; filename=\"" + resource.getFilename() + "\"")
            .body(resource);

    } catch (IOException e) {
        return ResponseEntity.status(500).build();
    }
}
```

### Resource Handling ve Static Resources

```java
// application.properties ile statik resurs konfiqurasiyasi
// spring.web.resources.static-locations=classpath:/static/,file:/var/uploads/

@Configuration
public class WebConfig implements WebMvcConfigurer {

    @Override
    public void addResourceHandlers(ResourceHandlerRegistry registry) {
        // /uploads/** URL-lerini fiziki qovluga yonlendiririk
        registry.addResourceHandler("/uploads/**")
                .addResourceLocations("file:/var/uploads/")
                .setCachePeriod(3600);

        // Classpath-den statik resurslar
        registry.addResourceHandler("/static/**")
                .addResourceLocations("classpath:/static/");
    }
}
```

### Fayl olcusu ve tipi validasiyasi

```java
@Configuration
public class FileUploadConfig {

    @Bean
    public MultipartConfigElement multipartConfigElement() {
        MultipartConfigFactory factory = new MultipartConfigFactory();
        factory.setMaxFileSize(DataSize.ofMegabytes(10));      // Maks fayl olcusu
        factory.setMaxRequestSize(DataSize.ofMegabytes(50));   // Maks request olcusu
        return factory.createMultipartConfig();
    }
}

// application.properties alternativi:
// spring.servlet.multipart.max-file-size=10MB
// spring.servlet.multipart.max-request-size=50MB
```

```java
@Service
public class FileValidationService {

    private static final Set<String> ALLOWED_TYPES = Set.of(
        "image/jpeg", "image/png", "image/gif", "application/pdf"
    );

    public void validate(MultipartFile file) {
        if (file.isEmpty()) {
            throw new IllegalArgumentException("Fayl bos ola bilmez");
        }

        if (!ALLOWED_TYPES.contains(file.getContentType())) {
            throw new IllegalArgumentException(
                "Icaze verilmeyen fayl tipi: " + file.getContentType());
        }

        if (file.getSize() > 10 * 1024 * 1024) {  // 10MB
            throw new IllegalArgumentException("Fayl cox boyukdur");
        }
    }
}
```

### S3 ile islemek (AWS SDK)

Spring-de S3 inteqrasiyasi ucun AWS SDK istifade olunur:

```java
@Service
public class S3StorageService {

    private final S3Client s3Client;
    private final String bucketName;

    public S3StorageService(
            @Value("${aws.s3.bucket}") String bucketName) {
        this.s3Client = S3Client.builder()
            .region(Region.EU_WEST_1)
            .build();
        this.bucketName = bucketName;
    }

    public String upload(MultipartFile file) throws IOException {
        String key = "uploads/" + UUID.randomUUID() + "_" + file.getOriginalFilename();

        s3Client.putObject(
            PutObjectRequest.builder()
                .bucket(bucketName)
                .key(key)
                .contentType(file.getContentType())
                .build(),
            RequestBody.fromInputStream(file.getInputStream(), file.getSize())
        );

        return key;
    }

    public byte[] download(String key) {
        GetObjectRequest request = GetObjectRequest.builder()
            .bucket(bucketName)
            .key(key)
            .build();

        try (ResponseInputStream<GetObjectResponse> response =
                s3Client.getObject(request)) {
            return response.readAllBytes();
        } catch (IOException e) {
            throw new RuntimeException("S3-den fayl oxuna bilmedi", e);
        }
    }

    public void delete(String key) {
        s3Client.deleteObject(DeleteObjectRequest.builder()
            .bucket(bucketName)
            .key(key)
            .build());
    }
}
```

## Laravel-de istifadesi

### Storage Facade ve Disk sistemi

Laravel-de fayl emeliyyatlari `Storage` facade vasitesile aparilir ve "disk" konsepsiyasi uezerine quruludur:

```php
// config/filesystems.php
return [
    'default' => env('FILESYSTEM_DISK', 'local'),

    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL') . '/storage',
            'visibility' => 'public',
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
        ],
    ],
];
```

### Fayl yukleme

```php
class FileUploadController extends Controller
{
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:jpg,png,gif,pdf|max:10240', // 10MB
        ]);

        // Faylin saxlanmasi - cemi bir setir!
        $path = $request->file('file')->store('uploads', 'public');

        return response()->json([
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
            'size' => $request->file('file')->getSize(),
        ]);
    }

    // Xususi ad ile saxlama
    public function uploadWithCustomName(Request $request)
    {
        $file = $request->file('avatar');

        $fileName = auth()->id() . '_' . time() . '.' . $file->getClientOriginalExtension();

        $path = $file->storeAs('avatars', $fileName, 'public');

        return response()->json(['path' => $path]);
    }
}
```

### Birden cox fayl yukleme

```php
public function uploadMultiple(Request $request)
{
    $request->validate([
        'files' => 'required|array',
        'files.*' => 'file|mimes:jpg,png,pdf|max:5120',
    ]);

    $paths = [];
    foreach ($request->file('files') as $file) {
        $paths[] = $file->store('uploads', 'public');
    }

    return response()->json(['paths' => $paths]);
}
```

### Fayl endirme ve oxuma

```php
class FileController extends Controller
{
    public function download(string $fileName)
    {
        $path = 'uploads/' . $fileName;

        if (!Storage::disk('public')->exists($path)) {
            abort(404, 'Fayl tapilmadi');
        }

        return Storage::disk('public')->download($path);
    }

    // Faylin mezmununu oxumaq
    public function read(string $fileName)
    {
        $content = Storage::disk('local')->get('data/' . $fileName);
        return response($content);
    }

    // Faylin URL-ini almaq
    public function getUrl(string $fileName)
    {
        // Public disk ucun
        $url = Storage::disk('public')->url('uploads/' . $fileName);

        // S3 ucun muveqqeti URL (15 deq)
        $tempUrl = Storage::disk('s3')->temporaryUrl(
            'uploads/' . $fileName,
            now()->addMinutes(15)
        );

        return response()->json([
            'url' => $url,
            'temp_url' => $tempUrl,
        ]);
    }
}
```

### S3 inteqrasiyasi

Laravel-de S3-e kecid son derece sadedir - yalniz disk adini deyisirsiniz:

```php
class DocumentService
{
    // Lokal diskden S3-e kecid ucun yalniz disk adini deyismek kifayetdir
    public function store(UploadedFile $file): string
    {
        // Lokal saxlama:
        // return $file->store('documents', 'local');

        // S3 saxlama - eyni API!
        return $file->store('documents', 's3');
    }

    public function getUrl(string $path): string
    {
        // Ictimai fayl
        return Storage::disk('s3')->url($path);
    }

    public function getSecureUrl(string $path): string
    {
        // Muveqqeti imzalanmis URL - tehlukesiz paylasim
        return Storage::disk('s3')->temporaryUrl(
            $path,
            now()->addHours(1)
        );
    }

    public function delete(string $path): bool
    {
        return Storage::disk('s3')->delete($path);
    }

    // Faylin visibility-sini deyismek
    public function makePublic(string $path): void
    {
        Storage::disk('s3')->setVisibility($path, 'public');
    }

    public function makePrivate(string $path): void
    {
        Storage::disk('s3')->setVisibility($path, 'private');
    }
}
```

### Public ve Private fayllar

```php
// Public fayllar - brauzerde birbaşa gorsenir
// storage/app/public -> public/storage (symlink)
// php artisan storage:link emri ile symlink yaradilir

// Public diske yukleme
$path = $file->store('photos', 'public');
$url = Storage::disk('public')->url($path);
// Netice: https://example.com/storage/photos/abc123.jpg

// Private fayllar - yalniz kod vasitesile erisile bilir
$path = $file->store('secret-docs', 'local');
// Bu fayla birbaşa URL ile erisib olmaz

// Private faylin endirme ucun route yaradirig
Route::get('/documents/{document}/download', function (Document $document) {
    // Icaze yoxlamasi
    $this->authorize('download', $document);

    return Storage::disk('local')->download($document->file_path);
});
```

### Fayl emeliyyatlari

```php
// Movcudluq yoxlamasi
$exists = Storage::disk('local')->exists('file.txt');

// Faylin olcusu
$size = Storage::disk('local')->size('file.txt');

// Son deyisiklik vaxti
$time = Storage::disk('local')->lastModified('file.txt');

// Qovluqdaki fayllarin siyahisi
$files = Storage::disk('local')->files('uploads');
$allFiles = Storage::disk('local')->allFiles('uploads'); // Rekursiv

// Qovluqlarin siyahisi
$directories = Storage::disk('local')->directories('uploads');

// Faylin kopiyalanmasi ve kocurulmesi
Storage::disk('local')->copy('old.txt', 'new.txt');
Storage::disk('local')->move('old.txt', 'new-location/old.txt');

// Faylin silinmesi
Storage::disk('local')->delete('file.txt');
Storage::disk('local')->delete(['file1.txt', 'file2.txt']); // Toplu silme

// Qovlug yaratma ve silme
Storage::disk('local')->makeDirectory('new-folder');
Storage::disk('local')->deleteDirectory('old-folder');
```

## Esas ferqler

| Xususiyyet | Spring | Laravel |
|---|---|---|
| **Fayl qebulu** | `MultipartFile` interfeysi | `$request->file()` metodu |
| **Saxlama abstraksiyasi** | Yoxdur (manual ve ya Spring Content) | `Storage` facade + disk sistemi |
| **S3 inteqrasiyasi** | AWS SDK ile manual | Konfiqurasiyada disk deyismekle |
| **Public/Private** | Manual konfiqurasiya | Built-in visibility sistemi |
| **Validasiya** | Manual ve ya `@Valid` ile | `validate()` ile bir setirde |
| **URL yaratma** | Manual | `Storage::url()`, `temporaryUrl()` |
| **Fayl endirme** | `Resource` + `ResponseEntity` | `Storage::download()` |
| **Disk kecidi** | Boyuk refaktorinq teleb edir | Yalniz disk adini deyismek |
| **Statik resurslar** | `ResourceHandler` konfiqurasiyasi | `storage:link` artisan emri |
| **Fayl siyahisi** | `Files.list()` Java API | `Storage::files()` |

## Niye bele ferqler var?

**Spring-in yanasmasi:** Spring asagi seviyyeli Java API-leri uezerine ince bir qat elave edir. `MultipartFile` servlet spesifikasiyasinin bir hissesidir ve Spring bunu birbaşa istifade edir. S3 ve ya basqa xidmetlerle islemek ucun SDK-lar ayrica elave olunur. Bu yanasma coxlu esneklik verir, amma daha cox kod yazmaq teleb edir.

**Laravel-in yanasmasi:** Laravel "convention over configuration" felsefesine uygun olaraq, fayl emeliyyatlarini maksimum dereced sadelesdirir. `Storage` facade arxasinda Flysystem kutubxanesi istifade olunur ki, bu da ferqli saxlama sistemleri ucun eyni API-ni teklif edir. Lokal diskden S3-e kecid yalniz konfiqurasiya deyisikliyi ile mumkundur - kod deyismir.

**Dizayn felsefesi:** Spring "sene butun aletleri verirem, ozun qur" deyir. Laravel ise "en cox istifade olunan ssenarilar ucun hazir hell var" deyir. Spring-de sifirdan qurmaq lazimdir, amma her sey tam nezaret altindadir. Laravel-de ise ekser hallar ucun bir-iki setir kodla isler gorulur.

## Hansi framework-de var, hansinda yoxdur?

- **Disk abstraksiyasi** - Yalniz Laravel-de var. Spring-de oxsar funksionalliq ucun Spring Content ve ya manual hell yazilmalidir.
- **temporaryUrl()** - Laravel-de S3 ucun imzalanmis muveqqeti URL bir metod cagirisla alinir. Spring-de AWS SDK ile manual yaradilir.
- **Visibility sistemi** (public/private) - Laravel-de built-in. Spring-de manual heyata kecirilir.
- **storage:link** artisan emri - Laravel-de `public` diskini veb-den erisilebilir etmek ucun bir emr kifayetdir.
- **Spring Resource abstraction** - Spring-de `Resource` interfeysi vasitesile classpath, URL, file system resurslarini eyni formada oxumaq mumkundur. Laravel-de bu cur abstraksiya yoxdur.
- **Streaming upload/download** - Her ikisinde var, amma Spring-de `StreamingResponseBody` ile daha ince nezaret mumkundur.
