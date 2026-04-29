# File Upload (Senior)

## İcmal

Go-da fayl yükləmə `multipart/form-data` ilə işləyir — `net/http` paketi tam dəstək verir. Kiçik fayllar RAM-da, böyük fayllar diskə stream edilir. S3 və digər object storage-ə yükləmə də bu əsasın üzərindədir.

## Niyə Vacibdir

- Hər veb tətbiqin fayl yükləmə funksiyası var: avatar, sənəd, şəkil
- Yanlış implementasiya: RAM dolur, disk dolur, güvənlik açıqları
- Streaming upload — böyük faylları RAM-a yükləmədən işləmək
- Production-da S3/GCS/MinIO istifadəsi — lokal disk istifadə etmə

## Əsas Anlayışlar

**`r.ParseMultipartForm(maxMemory)`** — form-u parse et, `maxMemory` baytdan böyük fayllar diskə yazılır

**`r.FormFile("field_name")`** — faylı al, `multipart.File` + `multipart.FileHeader` qaytarır

**Streaming:** `io.Copy` ilə faylı bir yerdən digərinə RAM-a yükləmədən kopyala

**Güvənlik:**
- Content-Type validation — `http.DetectContentType` ilə real tipi yoxla (extension-a güvənmə)
- Fayl adını sanitize et — path traversal: `../../etc/passwd`
- Maksimum fayl ölçüsü — `http.MaxBytesReader`

## Praktik Baxış

**Nə vaxt lokal disk:**
- Development mühiti
- Tək server deployment

**Production-da həmişə object storage (S3, GCS, MinIO):**
- Multi-server deployment — fayllar serverə bağlı qalır
- Backup, CDN, avtomatik scaling

**Common mistakes:**
- `r.ParseMultipartForm` çağırmadan `r.FormFile` çağırmaq
- Content-Type yoxlanmadan faylı saxlamaq (SVG → XSS)
- Orijinal fayl adını birbaşa saxlamaq (path traversal)
- Çox böyük `maxMemory` — RAM problemi

## Nümunələr

### Nümunə 1: Əsas fayl yükləmə — lokal disk

```go
package main

import (
    "fmt"
    "io"
    "net/http"
    "os"
    "path/filepath"
    "strings"
    "time"

    "github.com/google/uuid"
)

const (
    maxUploadSize = 10 << 20 // 10 MB
    uploadDir     = "./uploads"
)

var allowedTypes = map[string]string{
    "image/jpeg": ".jpg",
    "image/png":  ".png",
    "image/webp": ".webp",
    "application/pdf": ".pdf",
}

func UploadHandler(w http.ResponseWriter, r *http.Request) {
    if r.Method != http.MethodPost {
        http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
        return
    }

    // Maksimum ölçü məhdudiyyəti — parse etmədən əvvəl
    r.Body = http.MaxBytesReader(w, r.Body, maxUploadSize)

    // 32 MB-dan böyük hissə diskə yazılır (RAM-da saxlanmaz)
    if err := r.ParseMultipartForm(32 << 20); err != nil {
        if strings.Contains(err.Error(), "too large") {
            http.Error(w, "Fayl çox böyükdür (max 10MB)", http.StatusRequestEntityTooLarge)
            return
        }
        http.Error(w, "Form parse xətası", http.StatusBadRequest)
        return
    }

    file, header, err := r.FormFile("file")
    if err != nil {
        http.Error(w, "Fayl sahəsi tapılmadı", http.StatusBadRequest)
        return
    }
    defer file.Close()

    // Content-Type yoxla — extension-a yox, məzmuna bax
    buf := make([]byte, 512)
    n, err := file.Read(buf)
    if err != nil {
        http.Error(w, "Fayl oxuma xətası", http.StatusInternalServerError)
        return
    }

    contentType := http.DetectContentType(buf[:n])
    ext, ok := allowedTypes[contentType]
    if !ok {
        http.Error(w, fmt.Sprintf("İcazəsiz fayl tipi: %s", contentType), http.StatusBadRequest)
        return
    }

    // Təhlükəsiz fayl adı — UUID ilə, orijinal adı istifadə etmə
    fileName := fmt.Sprintf("%d-%s%s", time.Now().Unix(), uuid.New().String(), ext)
    savePath := filepath.Join(uploadDir, fileName)

    dst, err := os.Create(savePath)
    if err != nil {
        http.Error(w, "Fayl yaratma xətası", http.StatusInternalServerError)
        return
    }
    defer dst.Close()

    // Başından oxu (ilk 512 baytı geri qaytar)
    if _, err := file.Seek(0, io.SeekStart); err != nil {
        http.Error(w, "Fayl xətası", http.StatusInternalServerError)
        return
    }

    // Stream kopyalama — RAM-a yükləmir
    written, err := io.Copy(dst, file)
    if err != nil {
        http.Error(w, "Fayl saxlama xətası", http.StatusInternalServerError)
        return
    }

    w.Header().Set("Content-Type", "application/json")
    fmt.Fprintf(w, `{"file_name":"%s","size":%d,"original":"%s"}`,
        fileName, written, header.Filename)
}
```

### Nümunə 2: Çoxlu fayl yükləmə

```go
package main

import (
    "fmt"
    "io"
    "net/http"
    "os"
    "path/filepath"
)

func MultiUploadHandler(w http.ResponseWriter, r *http.Request) {
    r.Body = http.MaxBytesReader(w, r.Body, 50<<20) // 50MB cəmi
    if err := r.ParseMultipartForm(32 << 20); err != nil {
        http.Error(w, err.Error(), http.StatusBadRequest)
        return
    }

    // Bütün "files" sahəsindəki fayllar
    files := r.MultipartForm.File["files"]

    var uploaded []string
    for _, fileHeader := range files {
        if fileHeader.Size > 5<<20 { // hər fayl max 5MB
            continue
        }

        file, err := fileHeader.Open()
        if err != nil {
            continue
        }

        name := filepath.Base(fileHeader.Filename) // path traversal önlə
        dst, err := os.Create(filepath.Join("./uploads", fmt.Sprintf("%d_%s", 
            time.Now().UnixNano(), name)))
        if err != nil {
            file.Close()
            continue
        }

        io.Copy(dst, file)
        file.Close()
        dst.Close()

        uploaded = append(uploaded, name)
    }

    fmt.Fprintf(w, "Yükləndi: %v", uploaded)
}
```

### Nümunə 3: S3-ə yükləmə (AWS SDK v2)

```go
package main

import (
    "context"
    "fmt"
    "io"
    "mime/multipart"
    "net/http"
    "path/filepath"
    "time"

    "github.com/aws/aws-sdk-go-v2/aws"
    "github.com/aws/aws-sdk-go-v2/config"
    "github.com/aws/aws-sdk-go-v2/service/s3"
    "github.com/google/uuid"
)

// go get github.com/aws/aws-sdk-go-v2/...

type S3Uploader struct {
    client     *s3.Client
    bucketName string
    baseURL    string
}

func NewS3Uploader(ctx context.Context, bucket, region, baseURL string) (*S3Uploader, error) {
    cfg, err := config.LoadDefaultConfig(ctx, config.WithRegion(region))
    if err != nil {
        return nil, err
    }

    return &S3Uploader{
        client:     s3.NewFromConfig(cfg),
        bucketName: bucket,
        baseURL:    baseURL,
    }, nil
}

func (u *S3Uploader) Upload(ctx context.Context, file multipart.File, header *multipart.FileHeader) (string, error) {
    // Content-Type təyin et
    buf := make([]byte, 512)
    n, _ := file.Read(buf)
    contentType := http.DetectContentType(buf[:n])
    file.Seek(0, io.SeekStart)

    // Unikal key
    ext := filepath.Ext(header.Filename)
    key := fmt.Sprintf("uploads/%d/%s%s", time.Now().Year(), uuid.New().String(), ext)

    _, err := u.client.PutObject(ctx, &s3.PutObjectInput{
        Bucket:        aws.String(u.bucketName),
        Key:           aws.String(key),
        Body:          file,
        ContentType:   aws.String(contentType),
        ContentLength: aws.Int64(header.Size),
        // ACL: "public-read" — public bucket üçün
    })
    if err != nil {
        return "", fmt.Errorf("s3 upload: %w", err)
    }

    return fmt.Sprintf("%s/%s", u.baseURL, key), nil
}

// HTTP Handler
type UploadHandler struct {
    uploader *S3Uploader
}

func (h *UploadHandler) ServeHTTP(w http.ResponseWriter, r *http.Request) {
    r.Body = http.MaxBytesReader(w, r.Body, 10<<20)
    r.ParseMultipartForm(32 << 20)

    file, header, err := r.FormFile("file")
    if err != nil {
        http.Error(w, err.Error(), http.StatusBadRequest)
        return
    }
    defer file.Close()

    url, err := h.uploader.Upload(r.Context(), file, header)
    if err != nil {
        http.Error(w, "Yükləmə uğursuz", http.StatusInternalServerError)
        return
    }

    fmt.Fprintf(w, `{"url":"%s"}`, url)
}
```

### Nümunə 4: MinIO (local S3-compatible) — development üçün

```go
package main

import (
    "context"
    "io"
    "mime/multipart"
    "net/http"
    "time"

    "github.com/minio/minio-go/v7"
    "github.com/minio/minio-go/v7/pkg/credentials"
)

// go get github.com/minio/minio-go/v7

type MinIOUploader struct {
    client     *minio.Client
    bucketName string
}

func NewMinIOUploader(endpoint, accessKey, secretKey, bucket string) (*MinIOUploader, error) {
    client, err := minio.New(endpoint, &minio.Options{
        Creds:  credentials.NewStaticV4(accessKey, secretKey, ""),
        Secure: false, // development üçün HTTP
    })
    if err != nil {
        return nil, err
    }

    // Bucket mövcud deyilsə yarat
    ctx := context.Background()
    exists, err := client.BucketExists(ctx, bucket)
    if err != nil {
        return nil, err
    }
    if !exists {
        client.MakeBucket(ctx, bucket, minio.MakeBucketOptions{})
    }

    return &MinIOUploader{client: client, bucketName: bucket}, nil
}

func (u *MinIOUploader) Upload(ctx context.Context, file multipart.File, header *multipart.FileHeader) (string, error) {
    objectName := fmt.Sprintf("%d_%s", time.Now().UnixNano(), header.Filename)

    buf := make([]byte, 512)
    n, _ := file.Read(buf)
    contentType := http.DetectContentType(buf[:n])
    file.Seek(0, io.SeekStart)

    _, err := u.client.PutObject(ctx, u.bucketName, objectName, file,
        header.Size, minio.PutObjectOptions{ContentType: contentType})
    if err != nil {
        return "", err
    }

    return fmt.Sprintf("http://localhost:9000/%s/%s", u.bucketName, objectName), nil
}
```

## Praktik Tapşırıqlar

**Tapşırıq 1:**
Avatar yükləmə endpoint-i yaz: yalnız JPEG/PNG/WebP, max 2MB. Fayl adını UUID ilə generasiya et, `./uploads/avatars/` qovluğuna saxla.

**Tapşırıq 2:**
MinIO Docker ilə lokal yüklə: `docker run -p 9000:9000 minio/minio server /data`. Go kodundan şəkil yüklə, URL qaytar.

**Tapşırıq 3:**
Çoxlu fayl endpoint-i: maksimum 5 fayl, hər biri max 5MB. Yüklənmiş faylların URL-lərini JSON array kimi qaytar.

## PHP ilə Müqayisə

Laravel `$request->file()` + `Storage::put()` fayl yükləməni abstrakt edir. Go-da eyni işi `net/http` + `io.Copy` birliyi görür — daha az abstraktsiya, daha çox control.

```php
// Laravel
$path = $request->file('avatar')->store('avatars', 's3');
$url = Storage::disk('s3')->url($path);

// Content-type yoxlama — manual
$mimeType = $request->file('avatar')->getMimeType();
if (!in_array($mimeType, ['image/jpeg', 'image/png'])) {
    return response()->json(['error' => 'Invalid type'], 422);
}
```

```go
// Go
file, header, _ := r.FormFile("avatar")
buf := make([]byte, 512)
file.Read(buf)
contentType := http.DetectContentType(buf) // real content-type yoxla
file.Seek(0, io.SeekStart)
// S3-ə yüklə...
```

**Əsas fərqlər:**
- Laravel `Storage` facade S3/GCS/local abstraction verir; Go-da hər driver üçün SDK
- Laravel `$request->file()->validate()` — Go-da manual content-type yoxlama
- Go streaming default olaraq işləyir; Laravel büyük faylları da RAM-a yükləyir

## Əlaqəli Mövzular

- [30-io-reader-writer.md](../core/30-io-reader-writer.md) — io.Reader/Writer streaming
- [49-files-advanced.md](13-files-advanced.md) — Fayl əməliyyatları
- [62-security.md](../advanced/07-security.md) — Fayl yükləmə güvənliyi
- [01-http-server.md](01-http-server.md) — HTTP handler
