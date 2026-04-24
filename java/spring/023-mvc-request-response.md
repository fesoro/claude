# 023 — Spring MVC — Request və Response
**Səviyyə:** Orta


## Mündəricat
1. [@RequestBody — JSON Deserializasiya](#requestbody)
2. [@ResponseBody](#responsebody)
3. [ResponseEntity](#responseentity)
4. [HttpEntity](#httpentity)
5. [HttpStatus](#httpstatus)
6. [@ResponseStatus](#responsestatus)
7. [Müxtəlif Qaytarma Növləri](#qaytarma-novleri)
8. [Content Type Başlıqları](#content-type)
9. [İntervyu Sualları](#intervyu-sualları)

---

## @RequestBody — JSON Deserializasiya

`@RequestBody` — HTTP request body-sini Java obyektinə çevirir. Jackson `MappingJackson2HttpMessageConverter` vasitəsilə JSON-u deserializasiya edir.

```java
// Sadə @RequestBody istifadəsi
@RestController
@RequestMapping("/api/users")
public class UserController {

    // HTTP POST body-sindən User obyektini oxu
    @PostMapping
    public User createUser(@RequestBody User user) {
        // Jackson JSON → User obyekti çevirmişdir
        return userService.save(user);
    }
}
```

**JSON → Java çevrilmə prosesi:**

```
HTTP Request Body:
{
  "name": "Əli Həsənov",
  "email": "ali@example.com",
  "age": 30
}
        |
        v
MappingJackson2HttpMessageConverter
        |
        v
User { name="Əli Həsənov", email="ali@example.com", age=30 }
```

**DTO ilə @RequestBody:**

```java
// DTO record-ları ilə istifadə (Java 16+)
public record CreateUserRequest(
    @NotBlank String name,
    @Email String email,
    @Min(18) int age,
    String role
) {}

public record UpdateUserRequest(
    String name,
    String email
) {}

@RestController
@RequestMapping("/api/users")
public class UserController {

    // @Valid ilə birlikdə — validasiya aktiv olur
    @PostMapping
    @ResponseStatus(HttpStatus.CREATED)
    public UserResponse createUser(@RequestBody @Valid CreateUserRequest request) {
        return userService.create(request);
    }

    @PutMapping("/{id}")
    public UserResponse updateUser(
            @PathVariable Long id,
            @RequestBody @Valid UpdateUserRequest request) {
        return userService.update(id, request);
    }
}
```

**Nested obyektlər:**

```java
public record OrderRequest(
    String customerName,
    String address,
    List<OrderItemRequest> items,  // Siyahı
    PaymentInfo payment            // İç-içə obyekt
) {}

public record OrderItemRequest(
    Long productId,
    int quantity,
    BigDecimal unitPrice
) {}

public record PaymentInfo(
    String method,      // "CARD", "CASH"
    String cardNumber
) {}

// JSON:
// {
//   "customerName": "Əli",
//   "address": "Bakı, Nərimanov",
//   "items": [
//     {"productId": 1, "quantity": 2, "unitPrice": 15.99}
//   ],
//   "payment": {"method": "CARD", "cardNumber": "4111..."}
// }
@PostMapping("/orders")
public OrderResponse createOrder(@RequestBody @Valid OrderRequest request) {
    return orderService.create(request);
}
```

**YANLIŞ vs DOĞRU:**

```java
// YANLIŞ: @RequestBody olmadan — parametr null gəlir
@PostMapping("/wrong")
public User createUserWrong(User user) {
    // user obyekti null olacaq — Spring onu query param kimi axtarır
    return userService.save(user);
}

// DOĞRU: @RequestBody ilə — JSON body oxunur
@PostMapping("/correct")
public User createUserCorrect(@RequestBody User user) {
    return userService.save(user);
}

// YANLIŞ: Required olmayan sahə üçün null check yoxdur
@PostMapping("/users")
public User create(@RequestBody User user) {
    // user.getMiddleName() null ola bilər!
    String fullName = user.getFirstName() + " " + user.getMiddleName().trim();
    // NullPointerException!
    return userService.save(user);
}

// DOĞRU: Optional sahəni yoxla
@PostMapping("/users")
public User create(@RequestBody User user) {
    String fullName = user.getFirstName() +
        (user.getMiddleName() != null ? " " + user.getMiddleName().trim() : "");
    return userService.save(user);
}
```

---

## @ResponseBody

`@ResponseBody` — metodun qaytardığı dəyəri birbaşa HTTP response body-sinə yazır. `@RestController` istifadə edildikdə hər metoda avtomatik tətbiq olunur.

```java
@Controller // @RestController deyil
public class MixedController {

    // View qaytarır (ViewResolver işləyir)
    @GetMapping("/page")
    public String showPage(Model model) {
        model.addAttribute("data", "test");
        return "page"; // templates/page.html
    }

    // JSON qaytarır (@ResponseBody əlavə edilib)
    @GetMapping("/api/data")
    @ResponseBody
    public Map<String, Object> getData() {
        return Map.of("status", "OK", "count", 42);
    }

    // @ResponseBody ilə birlikdə ResponseEntity
    @GetMapping("/api/item/{id}")
    @ResponseBody
    public ResponseEntity<Item> getItem(@PathVariable Long id) {
        return itemService.findById(id)
            .map(ResponseEntity::ok)
            .orElse(ResponseEntity.notFound().build());
    }
}
```

---

## ResponseEntity

`ResponseEntity` — HTTP status kodu, başlıqlar və body-ni tam idarə etməyə imkan verir.

```java
@RestController
@RequestMapping("/api/products")
public class ProductController {

    // Sadə 200 OK
    @GetMapping("/{id}")
    public ResponseEntity<Product> getProduct(@PathVariable Long id) {
        return productService.findById(id)
            .map(product -> ResponseEntity.ok(product))  // 200 + body
            .orElse(ResponseEntity.notFound().build());  // 404, body yoxdur
    }

    // 201 Created — Location başlığı ilə
    @PostMapping
    public ResponseEntity<Product> createProduct(@RequestBody @Valid CreateProductRequest req) {
        Product created = productService.create(req);

        // REST standartına görə: yaradılan resursa link qaytarılmalıdır
        URI location = URI.create("/api/products/" + created.getId());

        return ResponseEntity
            .created(location)         // 201 Created + Location: /api/products/123
            .body(created);
    }

    // Xüsusi başlıqlarla
    @GetMapping
    public ResponseEntity<List<Product>> getAllProducts() {
        List<Product> products = productService.findAll();

        return ResponseEntity.ok()
            .header("X-Total-Count", String.valueOf(products.size()))
            .header("Cache-Control", "max-age=300")  // 5 dəqiqə cache
            .body(products);
    }

    // Şərti cavab (Conditional response — ETag ilə)
    @GetMapping("/{id}/conditional")
    public ResponseEntity<Product> getProductConditional(
            @PathVariable Long id,
            @RequestHeader(value = "If-None-Match", required = false) String etag) {

        Product product = productService.findById(id)
            .orElseThrow(() -> new ProductNotFoundException(id));

        String currentEtag = "\"" + product.getVersion() + "\"";

        if (currentEtag.equals(etag)) {
            return ResponseEntity.status(HttpStatus.NOT_MODIFIED).build(); // 304
        }

        return ResponseEntity.ok()
            .eTag(currentEtag)
            .body(product);
    }

    // No Content — silmə
    @DeleteMapping("/{id}")
    public ResponseEntity<Void> deleteProduct(@PathVariable Long id) {
        productService.delete(id);
        return ResponseEntity.noContent().build(); // 204 No Content
    }

    // Accepted — asinxron əməliyyat başladı
    @PostMapping("/{id}/export")
    public ResponseEntity<ExportJobResponse> startExport(@PathVariable Long id) {
        ExportJob job = exportService.startExport(id);

        return ResponseEntity
            .accepted()  // 202 Accepted
            .body(new ExportJobResponse(job.getId(), "Processing..."));
    }
}
```

**ResponseEntity Builder metodları:**

```java
// Bütün builder metodlarının nümunəsi
ResponseEntity.ok(body)                        // 200
ResponseEntity.created(uri)                    // 201
ResponseEntity.accepted()                      // 202
ResponseEntity.noContent()                     // 204
ResponseEntity.badRequest()                    // 400
ResponseEntity.notFound()                      // 404
ResponseEntity.status(HttpStatus.CONFLICT)     // 409
ResponseEntity.status(422)                     // İstənilən status kodu

// Zəncir style:
ResponseEntity.ok()
    .contentType(MediaType.APPLICATION_JSON)
    .header("X-Custom", "value")
    .body(data);
```

---

## HttpEntity

`HttpEntity` — başlıqlar + body-ni birləşdirir. `ResponseEntity`-nin super class-ıdır.

```java
@RestController
public class HttpEntityController {

    // Request body VƏ başlıqlarına eyni anda çıxış
    @PostMapping("/api/data")
    public ResponseEntity<String> processData(
            HttpEntity<String> requestEntity) {

        HttpHeaders headers = requestEntity.getHeaders();
        String body = requestEntity.getBody();

        String contentType = headers.getContentType().toString();
        String requestId = headers.getFirst("X-Request-ID");

        return ResponseEntity.ok("Alındı: " + body);
    }

    // RequestEntity (HttpEntity-nin genişləndirilmiş versiyası)
    @PostMapping("/api/typed")
    public ResponseEntity<UserResponse> processUser(
            RequestEntity<CreateUserRequest> requestEntity) {

        CreateUserRequest user = requestEntity.getBody();
        URI requestUri = requestEntity.getUrl();
        HttpMethod method = requestEntity.getMethod();

        UserResponse response = userService.create(user);
        return ResponseEntity.ok(response);
    }
}
```

---

## HttpStatus

`HttpStatus` enum-u bütün standart HTTP status kodlarını ehtiva edir:

```java
// Ən çox istifadə olunan status kodları:

// 2xx — Uğurlu cavablar
HttpStatus.OK                    // 200 — Standart uğurlu cavab
HttpStatus.CREATED               // 201 — Yeni resurs yaradıldı
HttpStatus.ACCEPTED              // 202 — Sorğu qəbul edildi, hələ işlənir
HttpStatus.NO_CONTENT            // 204 — Uğurlu, amma body yoxdur

// 3xx — Yönləndirmə
HttpStatus.MOVED_PERMANENTLY     // 301 — Daimi yönləndirmə
HttpStatus.FOUND                 // 302 — Müvəqqəti yönləndirmə
HttpStatus.NOT_MODIFIED          // 304 — Cache etibarlıdır, dəyişiklik yoxdur

// 4xx — Client xətası
HttpStatus.BAD_REQUEST           // 400 — Yanlış sorğu (validasiya xətası)
HttpStatus.UNAUTHORIZED          // 401 — Autentifikasiya lazımdır
HttpStatus.FORBIDDEN             // 403 — İcazə yoxdur
HttpStatus.NOT_FOUND             // 404 — Resurs tapılmadı
HttpStatus.METHOD_NOT_ALLOWED    // 405 — HTTP metod icazəsi yoxdur
HttpStatus.CONFLICT              // 409 — Resurs artıq mövcuddur
HttpStatus.GONE                  // 410 — Resurs silinib (geri dönməz)
HttpStatus.UNPROCESSABLE_ENTITY  // 422 — Validasiya xətası (semantik)
HttpStatus.TOO_MANY_REQUESTS     // 429 — Rate limit aşıldı

// 5xx — Server xətası
HttpStatus.INTERNAL_SERVER_ERROR // 500 — Ümumi server xətası
HttpStatus.NOT_IMPLEMENTED       // 501 — Hələ implementasiya edilməyib
HttpStatus.BAD_GATEWAY           // 502 — Upstream server xətası
HttpStatus.SERVICE_UNAVAILABLE   // 503 — Servis müvəqqəti əlçatmazdır

// Kod ilə yaratmaq
HttpStatus status = HttpStatus.valueOf(200); // OK
int code = HttpStatus.CREATED.value();       // 201
String reason = HttpStatus.NOT_FOUND.getReasonPhrase(); // "Not Found"
```

---

## @ResponseStatus

`@ResponseStatus` — metod və ya istisna sinfindəki default HTTP status kodunu müəyyən edir.

```java
@RestController
@RequestMapping("/api/tasks")
public class TaskController {

    // Method üzərindəki @ResponseStatus
    @PostMapping
    @ResponseStatus(HttpStatus.CREATED) // 200 yerinə 201 qaytarır
    public Task createTask(@RequestBody @Valid CreateTaskRequest request) {
        return taskService.create(request);
        // ResponseEntity qaytarmırıq, amma status 201 olacaq
    }

    @DeleteMapping("/{id}")
    @ResponseStatus(HttpStatus.NO_CONTENT) // 204 qaytarır
    public void deleteTask(@PathVariable Long id) {
        taskService.delete(id); // void metod, amma status 204
    }

    // İstisna sinifi üzərindəki @ResponseStatus
    // Bu istisna atıldıqda avtomatik 404 qaytarılır
}

@ResponseStatus(
    value = HttpStatus.NOT_FOUND,
    reason = "Task tapılmadı" // WWW-Authenticate başlığında görünür
)
public class TaskNotFoundException extends RuntimeException {
    public TaskNotFoundException(Long id) {
        super("Task tapılmadı: " + id);
    }
}

// İstifadəsi:
// Bu istisna atıldıqda Spring avtomatik 404 + reason qaytarır
throw new TaskNotFoundException(123L);
```

**@ResponseStatus vs ResponseEntity:**

```java
// @ResponseStatus — sadə, amma çevik deyil (dynamic status mümkün deyil)
@PostMapping("/simple")
@ResponseStatus(HttpStatus.CREATED)
public Task createSimple(@RequestBody Task task) {
    return taskService.save(task);
}

// ResponseEntity — tam idarəetmə (dynamic status, başlıqlar, body)
@PostMapping("/flexible")
public ResponseEntity<Task> createFlexible(@RequestBody Task task) {
    Task saved = taskService.save(task);
    boolean isNew = saved.getCreatedAt().equals(saved.getUpdatedAt());

    // İcra zamanı qərar ver: 201 ya 200
    return isNew
        ? ResponseEntity.status(HttpStatus.CREATED).body(saved)
        : ResponseEntity.ok(saved);
}
```

---

## Müxtəlif Qaytarma Növləri

```java
@RestController
@RequestMapping("/api/demo")
public class ReturnTypeDemoController {

    // 1. Sadə obyekt qaytarma → 200 OK + JSON
    @GetMapping("/object")
    public User getUser() {
        return new User("Əli", "ali@example.com");
        // Jackson avtomatik JSON-a çevirir
    }

    // 2. String qaytarma → StringHttpMessageConverter işləyir
    @GetMapping(value = "/text", produces = MediaType.TEXT_PLAIN_VALUE)
    public String getText() {
        return "Bu sadə mətndir";
        // Content-Type: text/plain
    }

    // 3. void qaytarma → 200 OK, body yoxdur
    @PostMapping("/notify")
    public void sendNotification(@RequestBody NotificationRequest req) {
        notificationService.send(req);
        // Body yoxdur, status 200
    }

    // 4. ResponseEntity qaytarma → tam idarəetmə
    @GetMapping("/entity/{id}")
    public ResponseEntity<User> getUserEntity(@PathVariable Long id) {
        return userService.findById(id)
            .map(ResponseEntity::ok)
            .orElse(ResponseEntity.notFound().build());
    }

    // 5. Optional qaytarma — @ResponseStatus ilə
    @GetMapping("/optional/{id}")
    public User getOptional(@PathVariable Long id) {
        return userService.findById(id)
            .orElseThrow(() -> new UserNotFoundException(id));
        // UserNotFoundException → @ResponseStatus(NOT_FOUND)
    }

    // 6. Siyahı qaytarma → JSON array
    @GetMapping("/list")
    public List<User> getList() {
        return userService.findAll();
        // [{"name":"Əli",...}, {"name":"Vəli",...}]
    }

    // 7. Map qaytarma → JSON object
    @GetMapping("/map")
    public Map<String, Object> getMap() {
        return Map.of(
            "status", "OK",
            "count", 42,
            "timestamp", LocalDateTime.now()
        );
    }

    // 8. byte[] qaytarma → binary data (fayl yükləmə)
    @GetMapping("/download/{filename}")
    public ResponseEntity<byte[]> downloadFile(@PathVariable String filename) {
        byte[] content = fileService.read(filename);

        return ResponseEntity.ok()
            .contentType(MediaType.APPLICATION_OCTET_STREAM)
            .header(HttpHeaders.CONTENT_DISPOSITION,
                "attachment; filename=\"" + filename + "\"")
            .body(content);
    }

    // 9. Resource qaytarma → fayl stream
    @GetMapping("/stream/{filename}")
    public ResponseEntity<Resource> streamFile(@PathVariable String filename) {
        Resource resource = fileService.loadAsResource(filename);

        return ResponseEntity.ok()
            .contentType(MediaType.APPLICATION_PDF)
            .body(resource);
    }
}
```

---

## Content Type Başlıqları

```java
@RestController
@RequestMapping("/api/content")
public class ContentTypeController {

    // JSON qaytarır (default @RestController-da)
    @GetMapping(produces = MediaType.APPLICATION_JSON_VALUE)
    public DataDto getJson() {
        return new DataDto("test");
    }

    // XML qaytarır
    @GetMapping(produces = MediaType.APPLICATION_XML_VALUE)
    public DataDto getXml() {
        return new DataDto("test");
    }

    // Həm JSON, həm XML — Accept başlığına görə seçilir
    @GetMapping(
        value = "/flexible",
        produces = {
            MediaType.APPLICATION_JSON_VALUE,
            MediaType.APPLICATION_XML_VALUE
        }
    )
    public DataDto getFlexible() {
        // Client "Accept: application/json" göndərsə → JSON
        // Client "Accept: application/xml" göndərsə → XML
        return new DataDto("test");
    }

    // JSON qəbul edir
    @PostMapping(
        consumes = MediaType.APPLICATION_JSON_VALUE,
        produces = MediaType.APPLICATION_JSON_VALUE
    )
    public DataDto postJson(@RequestBody DataDto dto) {
        return dto;
    }

    // Form data qəbul edir
    @PostMapping(consumes = MediaType.APPLICATION_FORM_URLENCODED_VALUE)
    public String handleForm(
            @RequestParam String name,
            @RequestParam String email) {
        return "Alındı: " + name + " <" + email + ">";
    }

    // Multipart (fayl + form data)
    @PostMapping(consumes = MediaType.MULTIPART_FORM_DATA_VALUE)
    public String uploadFile(
            @RequestParam("file") MultipartFile file,
            @RequestParam("description") String description) {
        fileService.save(file, description);
        return "Fayl yükləndi: " + file.getOriginalFilename();
    }
}
```

**Content Negotiation nümunəsi:**

```java
// Accept başlığına görə fərqli format qaytar
@GetMapping(
    value = "/report",
    produces = {
        MediaType.APPLICATION_JSON_VALUE,
        "text/csv",
        MediaType.APPLICATION_PDF_VALUE
    }
)
public ResponseEntity<?> getReport(
        @RequestHeader("Accept") String acceptHeader) {

    if (acceptHeader.contains("text/csv")) {
        byte[] csvData = reportService.generateCsv();
        return ResponseEntity.ok()
            .contentType(MediaType.parseMediaType("text/csv"))
            .header(HttpHeaders.CONTENT_DISPOSITION, "attachment; filename=report.csv")
            .body(csvData);
    } else if (acceptHeader.contains("application/pdf")) {
        byte[] pdfData = reportService.generatePdf();
        return ResponseEntity.ok()
            .contentType(MediaType.APPLICATION_PDF)
            .body(pdfData);
    } else {
        return ResponseEntity.ok(reportService.generateJson());
    }
}
```

---

## İntervyu Sualları

**S: @RequestBody ilə form data arasındakı fərq nədir?**

C: `@RequestBody` HTTP request body-sindəki JSON/XML-i Java obyektinə çevirir — `Content-Type: application/json` başlığı lazımdır. Form data (`application/x-www-form-urlencoded`) isə `@RequestParam` ilə oxunur. Multipart form data (`multipart/form-data`) üçün `@RequestParam MultipartFile` istifadə olunur.

---

**S: ResponseEntity nə zaman istifadə etməlisiniz?**

C: HTTP cavabı üzərində tam idarəetmə lazım olduqda: xüsusi status kodları (201, 204, 409), xüsusi başlıqlar (Location, ETag, Cache-Control), şərti cavablar (304 Not Modified), ya da runtime-da qərar verilən status kodu. Sadə hallarda `@ResponseStatus` annotasiyası kifayətdir.

---

**S: @ResponseStatus vs ResponseEntity — hansını nə zaman istifadə etməli?**

C: `@ResponseStatus` — statik, compile-time status kodu üçün sadə hallarda. `ResponseEntity` — dinamik, runtime-da qərar verilən status, başlıqlar, ya da mürəkkəb cavab strukturu üçün. `@ResponseStatus` İstisna siniflərinə də tətbiq olunur ki, həmin istisna atıldıqda avtomatik müvafiq HTTP status kodu qaytarılsın.

---

**S: HttpEntity ilə ResponseEntity arasındakı fərq nədir?**

C: `ResponseEntity`, `HttpEntity`-nin alt sinfidir. `HttpEntity` başlıqlar + body ehtiva edir. `ResponseEntity` bunlara əlavə olaraq HTTP status kodunu da ehtiva edir. `HttpEntity` adətən request parametri kimi istifadə olunur (həm body, həm başlıqlara çıxış üçün), `ResponseEntity` isə cavab qaytarmaq üçün.

---

**S: Null qaytarıldıqda nə baş verir?**

C: `@RestController`-da `null` qaytarıldıqda Spring `204 No Content` cavabı göndərir (body yoxdur). `ResponseEntity<T>` də `null` body ilə qaytarıla bilər — status kod saxlanılır. Bu davranış istənilmədirsə, boş optional üçün `Optional` qaytarıb `@ExceptionHandler` ilə 404 qaytarmaq daha yaxşıdır.
