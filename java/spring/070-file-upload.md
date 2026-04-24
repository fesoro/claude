# 070 — Spring File Upload — Geniş İzah
**Səviyyə:** Orta


## Mündəricat
1. [MultipartFile nədir?](#multipartfile-nədir)
2. [Fayl yükləmə konfiqurasiyası](#fayl-yükləmə-konfiqurasiyası)
3. [Fayl validation](#fayl-validation)
4. [Fayl saxlama](#fayl-saxlama)
5. [S3/MinIO ilə cloud storage](#s3minio-ilə-cloud-storage)
6. [Fayl endirme (download)](#fayl-endirme-download)
7. [İntervyu Sualları](#intervyu-sualları)

---

## MultipartFile nədir?

**MultipartFile** — HTTP multipart/form-data sorğusu ilə yüklənən fayl üçün Spring interfeysi. `getBytes()`, `getInputStream()`, `getOriginalFilename()`, `getSize()`, `getContentType()` metodlarına malikdir.

---

## Fayl yükləmə konfiqurasiyası

```yaml
# application.yml
spring:
  servlet:
    multipart:
      enabled: true
      max-file-size: 10MB          # Tək fayl maksimum ölçüsü
      max-request-size: 50MB       # Bütün request maksimum ölçüsü
      file-size-threshold: 2KB     # Bu ölçüdən böyük fayllar diskə yazılır
      location: /tmp/uploads       # Müvəqqəti yer
```

---

## Fayl validation

```java
@Service
public class FileValidationService {

    private static final Set<String> ALLOWED_IMAGE_TYPES = Set.of(
        "image/jpeg", "image/png", "image/gif", "image/webp"
    );

    private static final Set<String> ALLOWED_DOCUMENT_TYPES = Set.of(
        "application/pdf",
        "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
        "application/msword"
    );

    private static final long MAX_IMAGE_SIZE = 5 * 1024 * 1024;  // 5MB
    private static final long MAX_DOCUMENT_SIZE = 20 * 1024 * 1024; // 20MB

    public void validateImage(MultipartFile file) {
        if (file.isEmpty()) {
            throw new IllegalArgumentException("Fayl boşdur");
        }

        if (file.getSize() > MAX_IMAGE_SIZE) {
            throw new FileSizeLimitExceededException(
                "Şəkil 5MB-dan böyük ola bilməz", file.getSize(), MAX_IMAGE_SIZE);
        }

        String contentType = file.getContentType();
        if (contentType == null || !ALLOWED_IMAGE_TYPES.contains(contentType)) {
            throw new IllegalArgumentException(
                "Yalnız JPEG, PNG, GIF, WebP formatları qəbul edilir");
        }

        // Content Type spoofing-ə qarşı — real fayl məzmununu yoxla
        validateMagicBytes(file);
    }

    private void validateMagicBytes(MultipartFile file) {
        try {
            byte[] bytes = file.getBytes();
            // JPEG: FF D8 FF
            if (isJpeg(bytes) || isPng(bytes) || isGif(bytes) || isWebP(bytes)) {
                return;
            }
            throw new IllegalArgumentException("Fayl formatı etibarsızdır");
        } catch (IOException e) {
            throw new RuntimeException("Fayl yoxlanıla bilmədi", e);
        }
    }

    private boolean isJpeg(byte[] bytes) {
        return bytes.length > 2 &&
               (bytes[0] & 0xFF) == 0xFF &&
               (bytes[1] & 0xFF) == 0xD8 &&
               (bytes[2] & 0xFF) == 0xFF;
    }

    private boolean isPng(byte[] bytes) {
        return bytes.length > 3 &&
               (bytes[0] & 0xFF) == 0x89 &&
               bytes[1] == 'P' &&
               bytes[2] == 'N' &&
               bytes[3] == 'G';
    }

    private boolean isGif(byte[] bytes) {
        return bytes.length > 2 &&
               bytes[0] == 'G' && bytes[1] == 'I' && bytes[2] == 'F';
    }

    private boolean isWebP(byte[] bytes) {
        return bytes.length > 11 &&
               bytes[0] == 'R' && bytes[1] == 'I' && bytes[2] == 'F' && bytes[3] == 'F' &&
               bytes[8] == 'W' && bytes[9] == 'E' && bytes[10] == 'B' && bytes[11] == 'P';
    }

    // Fayl adını sanitize et
    public String sanitizeFilename(String originalFilename) {
        if (originalFilename == null || originalFilename.isBlank()) {
            return UUID.randomUUID().toString();
        }
        // Path traversal hücumunu qarşısını al
        String sanitized = originalFilename
            .replaceAll("[^a-zA-Z0-9._-]", "_")
            .replaceAll("\\.{2,}", ".")
            .replaceAll("^[./]+", "");

        return sanitized.isBlank() ? UUID.randomUUID().toString() : sanitized;
    }
}
```

---

## Fayl saxlama

```java
@RestController
@RequestMapping("/api/files")
public class FileUploadController {

    private final FileStorageService storageService;
    private final FileValidationService validationService;

    // Tək fayl yükləmə
    @PostMapping("/upload")
    public ResponseEntity<FileUploadResponse> uploadFile(
            @RequestParam("file") MultipartFile file) {

        validationService.validateImage(file);
        String fileUrl = storageService.store(file);

        return ResponseEntity.ok(new FileUploadResponse(fileUrl, file.getOriginalFilename()));
    }

    // Çoxlu fayl yükləmə
    @PostMapping("/upload/multiple")
    public ResponseEntity<List<FileUploadResponse>> uploadMultiple(
            @RequestParam("files") List<MultipartFile> files) {

        if (files.size() > 10) {
            return ResponseEntity.badRequest().build();
        }

        List<FileUploadResponse> responses = files.stream()
            .map(file -> {
                validationService.validateImage(file);
                String url = storageService.store(file);
                return new FileUploadResponse(url, file.getOriginalFilename());
            })
            .collect(Collectors.toList());

        return ResponseEntity.ok(responses);
    }

    // Fayl + JSON birlikdə
    @PostMapping("/upload-with-metadata")
    public ResponseEntity<Product> uploadWithMetadata(
            @RequestPart("file") MultipartFile file,
            @RequestPart("product") @Valid ProductDto productDto) {

        validationService.validateImage(file);
        String imageUrl = storageService.store(file);
        productDto.setImageUrl(imageUrl);

        return ResponseEntity.ok(productService.create(productDto));
    }
}

// Local disk saxlama
@Service
public class LocalFileStorageService {

    private final Path storageLocation;

    public LocalFileStorageService(@Value("${app.file.storage-path}") String storagePath) {
        this.storageLocation = Paths.get(storagePath).toAbsolutePath().normalize();

        try {
            Files.createDirectories(this.storageLocation);
        } catch (Exception e) {
            throw new RuntimeException("Upload directory yaradıla bilmədi", e);
        }
    }

    public String store(MultipartFile file) {
        String originalFilename = file.getOriginalFilename();
        String extension = getExtension(originalFilename);
        String uniqueFilename = UUID.randomUUID() + "." + extension;

        // Subdirectory ilə organize et (date-based)
        String subDir = LocalDate.now().format(DateTimeFormatter.ofPattern("yyyy/MM/dd"));
        Path targetDir = storageLocation.resolve(subDir);

        try {
            Files.createDirectories(targetDir);
            Path targetPath = targetDir.resolve(uniqueFilename);

            // Əvvəlcə müvəqqəti yerə yaz, sonra köçür (atomik əməliyyat)
            try (InputStream inputStream = file.getInputStream()) {
                Files.copy(inputStream, targetPath, StandardCopyOption.REPLACE_EXISTING);
            }

            return "/files/" + subDir + "/" + uniqueFilename;
        } catch (IOException e) {
            throw new RuntimeException("Fayl saxlanıla bilmədi", e);
        }
    }

    private String getExtension(String filename) {
        if (filename == null) return "bin";
        int dotIndex = filename.lastIndexOf('.');
        return dotIndex > 0 ? filename.substring(dotIndex + 1).toLowerCase() : "bin";
    }
}
```

---

## S3/MinIO ilə cloud storage

```java
@Service
public class S3FileStorageService {

    private final S3Client s3Client;

    @Value("${aws.s3.bucket}")
    private String bucketName;

    @Value("${aws.s3.region}")
    private String region;

    public String store(MultipartFile file) {
        String key = generateKey(file.getOriginalFilename());

        try {
            PutObjectRequest request = PutObjectRequest.builder()
                .bucket(bucketName)
                .key(key)
                .contentType(file.getContentType())
                .contentLength(file.getSize())
                .build();

            s3Client.putObject(request,
                RequestBody.fromInputStream(file.getInputStream(), file.getSize()));

            return "https://" + bucketName + ".s3." + region + ".amazonaws.com/" + key;
        } catch (IOException e) {
            throw new RuntimeException("S3-ə yükləmə uğursuz oldu", e);
        }
    }

    public String generatePresignedUrl(String key, Duration duration) {
        GetObjectPresignRequest presignRequest = GetObjectPresignRequest.builder()
            .signatureDuration(duration)
            .getObjectRequest(req -> req.bucket(bucketName).key(key))
            .build();

        return s3Presigner.presignGetObject(presignRequest).url().toString();
    }

    private String generateKey(String originalFilename) {
        String extension = getExtension(originalFilename);
        String date = LocalDate.now().format(DateTimeFormatter.ofPattern("yyyy/MM/dd"));
        return date + "/" + UUID.randomUUID() + "." + extension;
    }
}
```

---

## Fayl endirme (download)

```java
@GetMapping("/download/{filename}")
public ResponseEntity<Resource> downloadFile(@PathVariable String filename) {
    Path filePath = storageLocation.resolve(filename).normalize();

    // Path traversal yoxlaması
    if (!filePath.startsWith(storageLocation)) {
        return ResponseEntity.badRequest().build();
    }

    Resource resource = new UrlResource(filePath.toUri());

    if (!resource.exists() || !resource.isReadable()) {
        return ResponseEntity.notFound().build();
    }

    String contentType = determineContentType(filename);

    return ResponseEntity.ok()
        .contentType(MediaType.parseMediaType(contentType))
        .header(HttpHeaders.CONTENT_DISPOSITION,
                "attachment; filename=\"" + filename + "\"")
        .body(resource);
}

// Inline görüntüləmə (attachment əvəzinə)
@GetMapping("/view/{filename}")
public ResponseEntity<Resource> viewFile(@PathVariable String filename) {
    // ... resource yüklə ...

    return ResponseEntity.ok()
        .contentType(MediaType.IMAGE_JPEG)
        .header(HttpHeaders.CONTENT_DISPOSITION, "inline; filename=\"" + filename + "\"")
        .body(resource);
}

private String determineContentType(String filename) {
    try {
        String contentType = Files.probeContentType(Path.of(filename));
        return contentType != null ? contentType : "application/octet-stream";
    } catch (IOException e) {
        return "application/octet-stream";
    }
}
```

---

## İntervyu Sualları

### 1. Path traversal hücumu nədir?
**Cavab:** `../../etc/passwd` kimi fayl adları ilə serverdəki başqa faylara çıxış cəhdi. Həll: `filename.normalize()` + `startsWith(storageLocation)` yoxlaması. Fayl adını UUID ilə əvəz etmək ən güvənli yanaşmadır.

### 2. Content-Type spoofing nədir?
**Cavab:** Zərərli fayl (exe, php) şəkil extension-ı ilə yüklənməsi. `file.getContentType()` yalnız HTTP header-ə baxır, aldadıla bilər. Həll: faylın ilk byte-larını (magic bytes) yoxlamaq — hər format özünəməxsus signature-a malikdir (JPEG: FF D8 FF, PNG: 89 50 4E 47).

### 3. Böyük faylları necə idarə etmək olar?
**Cavab:** (1) `file-size-threshold` ilə müvəqqəti disə yaz (RAM-da saxlama). (2) `MultipartFile.getInputStream()` ilə streaming — bütün faylı RAM-a yükləmə. (3) S3 Multipart Upload — həddən böyük faylları part-lara bölüb yüklə. (4) Presigned URL ilə client birbaşa S3-ə yükləsin.

### 4. Faylı UUID ilə niyə saxlamaq lazımdır?
**Cavab:** (1) Fayl adı collision-ından qorunmaq. (2) Path traversal hücumunu qarşısını almaq. (3) Orijinal fayl adı XSS/injection cəhdi ola bilər. UUID ilə saxlayıb orijinal adı metadata-da (DB) saxlamaq düzgün yanaşmadır.

### 5. ResponseEntity<Resource> ilə fayl download-un fərqi attachment vs inline?
**Cavab:** `Content-Disposition: attachment` — browser-i faylı download etməyə məcbur edir. `Content-Disposition: inline` — browser faylı öz daxilində açmağa çalışır (PDF, şəkil). Güvənlik üçün istifadəçi tərəfindən yüklənmiş HTML fayllar həmişə `attachment` ilə göndərilməlidir.

*Son yenilənmə: 2026-04-10*
