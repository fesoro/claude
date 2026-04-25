# 23 — Spring MVC — Controllers

> **Seviyye:** Intermediate ⭐⭐


## Mündəricat
1. [@Controller vs @RestController](#controller-vs-restcontroller)
2. [@RequestMapping](#requestmapping)
3. [HTTP Metod Annotasiyaları](#http-metod-annotasiyalari)
4. [@PathVariable](#pathvariable)
5. [@RequestParam](#requestparam)
6. [@RequestHeader](#requestheader)
7. [@CookieValue](#cookievalue)
8. [Tam Nümunə — REST Controller](#tam-numune)
9. [İntervyu Sualları](#intervyu-sualları)

---

## @Controller vs @RestController

`@Controller` — Spring MVC-nin klassik annotasiyasıdır. View adı qaytarılır, Thymeleaf/JSP ilə işləyir.

`@RestController` — `@Controller` + `@ResponseBody` birləşməsidir. Metoddan qaytarılan dəyər birbaşa HTTP response body-sinə yazılır (JSON/XML).

```java
// YANLIŞ: REST API üçün @Controller istifadə etmək
@Controller
public class WrongApiController {

    @GetMapping("/api/users")
    public List<User> getUsers() {
        // Bu List<User> qaytarır, amma Spring bunu view adı kimi
        // interpret etməyə çalışacaq → ViewResolver xəta verəcək!
        return userService.findAll();
    }
}

// DOĞRU: REST API üçün @RestController istifadə et
@RestController
public class UserApiController {

    @GetMapping("/api/users")
    public List<User> getUsers() {
        // Jackson bu siyahını JSON-a çevirir → HTTP response body
        return userService.findAll();
    }
}
```

```java
// @RestController = @Controller + @ResponseBody
// Bunu əl ilə belə yazmaq olar (amma lazım deyil):
@Controller
public class ManualRestController {

    @GetMapping("/api/products")
    @ResponseBody // ← bu hər metoda ayrıca əlavə edilməlidir
    public List<Product> getProducts() {
        return productService.findAll();
    }
}

// Ekvivalent, daha qısa:
@RestController
public class CleanRestController {

    @GetMapping("/api/products")
    public List<Product> getProducts() {
        return productService.findAll();
    }
}
```

**View qaytarmaq üçün @Controller:**

```java
@Controller
@RequestMapping("/web")
public class WebController {

    private final UserService userService;

    public WebController(UserService userService) {
        this.userService = userService;
    }

    // "users" → templates/users.html faylını render edir
    @GetMapping("/users")
    public String usersPage(Model model) {
        model.addAttribute("users", userService.findAll());
        return "users"; // ViewResolver bu adı tapır
    }

    // Redirect
    @PostMapping("/users")
    public String createUser(@ModelAttribute UserForm form) {
        userService.create(form);
        return "redirect:/web/users"; // Yönləndirmə
    }
}
```

---

## @RequestMapping

`@RequestMapping` — HTTP sorğularını Controller metodlarına uyğunlaşdırır.

**Əsas atributlar:**

```java
@RestController
public class RequestMappingDemoController {

    // Method: HTTP metodunu göstər (GET, POST, PUT, DELETE, PATCH)
    @RequestMapping(value = "/api/items", method = RequestMethod.GET)
    public List<Item> getItems() {
        return itemService.findAll();
    }

    // Path: URL yolu (value ilə eynidir)
    @RequestMapping(path = "/api/items/{id}", method = RequestMethod.GET)
    public Item getItem(@PathVariable Long id) {
        return itemService.findById(id);
    }

    // Produces: Bu endpoint hansı format qaytarır
    @RequestMapping(
        value = "/api/items",
        method = RequestMethod.GET,
        produces = {MediaType.APPLICATION_JSON_VALUE, MediaType.APPLICATION_XML_VALUE}
    )
    public List<Item> getItemsMultiFormat() {
        return itemService.findAll();
    }

    // Consumes: Bu endpoint hansı format qəbul edir
    @RequestMapping(
        value = "/api/items",
        method = RequestMethod.POST,
        consumes = MediaType.APPLICATION_JSON_VALUE,
        produces = MediaType.APPLICATION_JSON_VALUE
    )
    public Item createItem(@RequestBody Item item) {
        return itemService.save(item);
    }
}
```

**Sinif səviyyəsində @RequestMapping:**

```java
// Sinif səviyyəsindəki path bütün metodlara tətbiq olunur
@RestController
@RequestMapping("/api/v1/products") // Bütün metodlara /api/v1/products prefix-i əlavə olunur
public class ProductController {

    // GET /api/v1/products
    @GetMapping
    public List<Product> getAll() { ... }

    // GET /api/v1/products/123
    @GetMapping("/{id}")
    public Product getById(@PathVariable Long id) { ... }

    // POST /api/v1/products
    @PostMapping
    public Product create(@RequestBody Product product) { ... }
}
```

---

## HTTP Metod Annotasiyaları

Spring `@RequestMapping(method=...)` üçün rahat qısaltmalar təqdim edir:

```java
@RestController
@RequestMapping("/api/orders")
public class OrderController {

    // @GetMapping — məlumat almaq üçün
    @GetMapping
    public List<Order> getAllOrders() {
        return orderService.findAll();
    }

    // @GetMapping — tək məlumat almaq
    @GetMapping("/{id}")
    public Order getOrderById(@PathVariable Long id) {
        return orderService.findById(id)
            .orElseThrow(() -> new OrderNotFoundException(id));
    }

    // @PostMapping — yeni məlumat yaratmaq üçün
    @PostMapping
    @ResponseStatus(HttpStatus.CREATED) // 201 qaytarır
    public Order createOrder(@RequestBody @Valid CreateOrderRequest request) {
        return orderService.create(request);
    }

    // @PutMapping — tam yeniləmə (bütün sahələri göndər)
    @PutMapping("/{id}")
    public Order updateOrder(
            @PathVariable Long id,
            @RequestBody @Valid UpdateOrderRequest request) {
        return orderService.update(id, request);
    }

    // @PatchMapping — qismən yeniləmə (yalnız dəyişən sahələri göndər)
    @PatchMapping("/{id}/status")
    public Order updateOrderStatus(
            @PathVariable Long id,
            @RequestBody @Valid UpdateStatusRequest request) {
        return orderService.updateStatus(id, request.getStatus());
    }

    // @DeleteMapping — silmə
    @DeleteMapping("/{id}")
    @ResponseStatus(HttpStatus.NO_CONTENT) // 204 qaytarır
    public void deleteOrder(@PathVariable Long id) {
        orderService.delete(id);
    }
}
```

**PUT vs PATCH fərqi:**

```java
// PUT — İdempotent, bütün resursu dəyişdirir
// YANLIŞ istifadə: Yalnız bir sahəni PUT ilə dəyiş
@PutMapping("/{id}")
public User updateUserName(@PathVariable Long id, @RequestBody Map<String, String> body) {
    // Yalnız adı dəyişsək, digər sahələr null olacaq → məlumat itirilir!
    user.setName(body.get("name"));
    return userService.save(user);
}

// DOĞRU: PUT tam obyekt gözləyir
@PutMapping("/{id}")
public User updateUser(@PathVariable Long id, @RequestBody @Valid UserUpdateRequest request) {
    // Bütün sahələr var — tam yeniləmə
    return userService.update(id, request);
}

// PATCH — qismən yeniləmə üçün düzgündür
@PatchMapping("/{id}")
public User patchUser(@PathVariable Long id, @RequestBody Map<String, Object> updates) {
    return userService.partialUpdate(id, updates);
}
```

---

## @PathVariable

`@PathVariable` — URL-dəki dəyişkən hissəni metod parametrinə bağlayır.

```java
@RestController
@RequestMapping("/api")
public class PathVariableController {

    // Sadə path variable
    @GetMapping("/users/{id}")
    public User getUser(@PathVariable Long id) {
        return userService.findById(id);
    }

    // Dəyişkən adını açıq göstər (parametr adı fərqlidirsə)
    @GetMapping("/users/{userId}/orders/{orderId}")
    public Order getUserOrder(
            @PathVariable("userId") Long userId,
            @PathVariable("orderId") Long orderId) {
        return orderService.findByUserAndId(userId, orderId);
    }

    // Optional path variable
    @GetMapping({"/categories", "/categories/{id}"})
    public Object getCategory(
            @PathVariable(required = false) Long id) {
        if (id == null) {
            return categoryService.findAll();
        }
        return categoryService.findById(id);
    }

    // String path variable
    @GetMapping("/products/{slug}")
    public Product getProductBySlug(@PathVariable String slug) {
        return productService.findBySlug(slug);
    }

    // Bütün qalan path-i tut (/** pattern)
    @GetMapping("/files/{filePath:**}")
    public Resource getFile(@PathVariable String filePath) {
        return fileService.load(filePath);
    }
}
```

**YANLIŞ vs DOĞRU:**

```java
// YANLIŞ: Path variable tipini yanlış istifadə et
@GetMapping("/users/{id}")
public User getUser(@PathVariable String id) {
    // String-i əl ilə Long-a çevirmək lazımdır
    Long userId = Long.parseLong(id); // NumberFormatException riski!
    return userService.findById(userId);
}

// DOĞRU: Spring avtomatik tip çevirmə edir
@GetMapping("/users/{id}")
public User getUser(@PathVariable Long id) {
    // Spring "123" → 123L avtomatik çevirir
    // Yanlış format gəlsə → 400 Bad Request
    return userService.findById(id);
}
```

---

## @RequestParam

`@RequestParam` — URL query string parametrlərini metod parametrlərinə bağlayır.

```
URL: /api/users?page=0&size=10&sort=name&active=true
                 ^^^^   ^^^^   ^^^^^^^^^  ^^^^^^^^^^^
              @RequestParam-larla tutulur
```

```java
@RestController
@RequestMapping("/api/users")
public class UserSearchController {

    // Sadə required param (default olaraq required=true)
    @GetMapping("/search")
    public List<User> search(@RequestParam String name) {
        // URL-də ?name=... olmasa → 400 Bad Request
        return userService.searchByName(name);
    }

    // Optional param (required=false)
    @GetMapping
    public List<User> getUsers(
            @RequestParam(required = false) String role) {
        if (role == null) {
            return userService.findAll();
        }
        return userService.findByRole(role);
    }

    // Default dəyər ilə
    @GetMapping("/page")
    public Page<User> getUsersPaged(
            @RequestParam(defaultValue = "0") int page,
            @RequestParam(defaultValue = "10") int size,
            @RequestParam(defaultValue = "id") String sortBy) {
        return userService.findAll(PageRequest.of(page, size, Sort.by(sortBy)));
    }

    // Dəyişkən adını açıq göstər
    @GetMapping("/filter")
    public List<User> filterUsers(
            @RequestParam("active") boolean isActive,
            @RequestParam("min_age") int minAge,
            @RequestParam("max_age") int maxAge) {
        return userService.filter(isActive, minAge, maxAge);
    }

    // Siyahı parametr
    @GetMapping("/bulk")
    public List<User> getUsersByIds(
            @RequestParam List<Long> ids) {
        // URL: /api/users/bulk?ids=1&ids=2&ids=3
        // və ya: /api/users/bulk?ids=1,2,3
        return userService.findAllById(ids);
    }

    // MultiValueMap ilə bütün parametrləri al
    @GetMapping("/all-params")
    public Map<String, String> getAllParams(
            @RequestParam Map<String, String> params) {
        return params; // Bütün query parametrlərini qaytarır
    }
}
```

**Tam axtarış nümunəsi:**

```java
// Axtarış üçün DTO istifadə etmək daha yaxşıdır (çox parametr olduqda)
public record UserSearchRequest(
    String name,
    String email,
    String role,
    Boolean active,
    int page,
    int size
) {}

@GetMapping("/search")
public Page<User> searchUsers(UserSearchRequest searchRequest) {
    // Spring avtomatik olaraq query parametrlərini DTO-ya bağlayır
    // URL: /api/users/search?name=Əli&role=ADMIN&page=0&size=10
    return userService.search(searchRequest);
}
```

---

## @RequestHeader

`@RequestHeader` — HTTP başlıq dəyərlərinə daxil olmağı təmin edir.

```java
@RestController
@RequestMapping("/api")
public class HeaderController {

    // Tək başlıq
    @GetMapping("/content")
    public String processContent(
            @RequestHeader("Content-Type") String contentType) {
        return "Content-Type: " + contentType;
    }

    // Authorization başlığı (autentifikasiya üçün)
    @GetMapping("/protected")
    public String getProtected(
            @RequestHeader("Authorization") String authHeader) {
        // "Bearer eyJhbGciOiJIUzI1..." formatında gəlir
        String token = authHeader.replace("Bearer ", "");
        // Token yoxla...
        return "Protected resource";
    }

    // Optional başlıq
    @GetMapping("/optional-header")
    public String withOptionalHeader(
            @RequestHeader(value = "X-Custom-Header", required = false)
            String customHeader) {
        if (customHeader == null) {
            return "Başlıq yoxdur";
        }
        return "Başlıq: " + customHeader;
    }

    // Default dəyər ilə
    @GetMapping("/language")
    public String getLanguage(
            @RequestHeader(
                value = "Accept-Language",
                defaultValue = "az-AZ"
            ) String language) {
        return "Dil: " + language;
    }

    // Bütün başlıqları al
    @GetMapping("/all-headers")
    public Map<String, String> getAllHeaders(
            @RequestHeader Map<String, String> headers) {
        return headers;
    }

    // HttpHeaders obyekti ilə
    @GetMapping("/http-headers")
    public String withHttpHeaders(
            @RequestHeader HttpHeaders headers) {
        String userAgent = headers.getFirst("User-Agent");
        String accept = headers.getFirst("Accept");
        return String.format("User-Agent: %s, Accept: %s", userAgent, accept);
    }
}
```

---

## @CookieValue

`@CookieValue` — HTTP cookie dəyərlərinə daxil olmağı təmin edir.

```java
@RestController
@RequestMapping("/api")
public class CookieController {

    // Session cookie oxu
    @GetMapping("/session")
    public String getSession(
            @CookieValue("JSESSIONID") String sessionId) {
        return "Session ID: " + sessionId;
    }

    // Optional cookie
    @GetMapping("/preferences")
    public String getPreferences(
            @CookieValue(value = "user_prefs", required = false)
            String userPrefs) {
        if (userPrefs == null) {
            return "Default tənzimləmələr";
        }
        return "İstifadəçi tənzimləmələri: " + userPrefs;
    }

    // Default dəyər ilə cookie
    @GetMapping("/theme")
    public String getTheme(
            @CookieValue(value = "theme", defaultValue = "light")
            String theme) {
        return "Tema: " + theme;
    }

    // Cookie obyekti kimi
    @GetMapping("/full-cookie")
    public String getFullCookie(
            @CookieValue("tracking") Cookie trackingCookie) {
        return String.format(
            "Cookie adı: %s, dəyəri: %s, domain: %s",
            trackingCookie.getName(),
            trackingCookie.getValue(),
            trackingCookie.getDomain()
        );
    }

    // Cookie yaratmaq (ResponseEntity ilə)
    @PostMapping("/login")
    public ResponseEntity<String> login(@RequestBody LoginRequest request) {
        String token = authService.login(request);

        // Cookie yarat
        ResponseCookie cookie = ResponseCookie.from("auth_token", token)
            .httpOnly(true)    // JavaScript-dən gizlə (XSS qoruma)
            .secure(true)      // Yalnız HTTPS ilə göndər
            .path("/")
            .maxAge(Duration.ofDays(7))
            .sameSite("Strict") // CSRF qoruma
            .build();

        return ResponseEntity.ok()
            .header(HttpHeaders.SET_COOKIE, cookie.toString())
            .body("Giriş uğurlu oldu");
    }
}
```

---

## Tam Nümunə — REST Controller

```java
@RestController
@RequestMapping("/api/v1/books")
@RequiredArgsConstructor
public class BookController {

    private final BookService bookService;

    // GET /api/v1/books?page=0&size=10&genre=fiction
    @GetMapping
    public Page<BookDto> getBooks(
            @RequestParam(defaultValue = "0") int page,
            @RequestParam(defaultValue = "10") int size,
            @RequestParam(required = false) String genre,
            @RequestHeader(
                value = "Accept-Language",
                defaultValue = "az"
            ) String language) {

        return bookService.findAll(page, size, genre, language);
    }

    // GET /api/v1/books/978-3-16-148410-0
    @GetMapping("/{isbn}")
    public BookDto getBook(
            @PathVariable String isbn,
            @RequestHeader(
                value = "X-Currency",
                defaultValue = "AZN"
            ) String currency) {

        return bookService.findByIsbn(isbn, currency);
    }

    // POST /api/v1/books
    @PostMapping
    @ResponseStatus(HttpStatus.CREATED)
    public BookDto createBook(
            @RequestBody @Valid CreateBookRequest request,
            @RequestHeader("Authorization") String authHeader) {

        validateAdminToken(authHeader);
        return bookService.create(request);
    }

    // PUT /api/v1/books/123
    @PutMapping("/{id}")
    public BookDto updateBook(
            @PathVariable Long id,
            @RequestBody @Valid UpdateBookRequest request) {

        return bookService.update(id, request);
    }

    // PATCH /api/v1/books/123/price
    @PatchMapping("/{id}/price")
    public BookDto updatePrice(
            @PathVariable Long id,
            @RequestParam BigDecimal price,
            @CookieValue(value = "admin_session", required = false)
            String adminSession) {

        validateSession(adminSession);
        return bookService.updatePrice(id, price);
    }

    // DELETE /api/v1/books/123
    @DeleteMapping("/{id}")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    public void deleteBook(@PathVariable Long id) {
        bookService.delete(id);
    }

    private void validateAdminToken(String authHeader) {
        // Token yoxlama məntiq
    }

    private void validateSession(String session) {
        // Session yoxlama məntiq
    }
}
```

---

## İntervyu Sualları

**S: @Controller ilə @RestController arasındakı fərq nədir?**

C: `@Controller` metod nəticəsini view adı kimi qəbul edir — `ViewResolver` həmin adı HTML şablonuna çevirir. `@RestController` isə `@Controller` + `@ResponseBody` birləşməsidir; metoddan qaytarılan dəyər birbaşa `HttpMessageConverter` vasitəsilə (adətən Jackson ilə JSON-a) HTTP response body-sinə yazılır.

---

**S: @RequestParam ilə @PathVariable fərqi nədir?**

C: `@PathVariable` URL-in özündəki parametrləri tutur: `/users/{id}` → `id`. `@RequestParam` isə URL-dəki query string-i tutur: `/users?page=0&size=10` → `page`, `size`. `@PathVariable` resursun identifikasiyası üçün, `@RequestParam` filtr/axtarış üçün istifadə edilir.

---

**S: PUT vs PATCH fərqi nədir?**

C: `PUT` — idempotentdir, resursu tam əvəz edir; bütün sahələr göndərilməlidir, əks halda göndərilməyən sahələr null/default olacaq. `PATCH` — qismən yeniləmə üçündür; yalnız dəyişən sahələr göndərilir. REST standartlarına görə tam yeniləmə üçün PUT, qismən üçün PATCH istifadə olunur.

---

**S: @RequestHeader nə üçün istifadə olunur?**

C: HTTP başlıq dəyərlərinə Controller metodunda daxil olmaq üçün. Ən çox `Authorization` başlığından token oxumaq, `Content-Type`/`Accept` başlıqlarını yoxlamaq, xüsusi başlıqlar (`X-Request-ID`, `X-Correlation-ID`) oxumaq üçün istifadə edilir. `required=false` ilə optional da ola bilər.

---

**S: @RequestParam-da `required=false` vs `defaultValue` fərqi nədir?**

C: `required=false` ilə parametr gəlmədikdə `null` alınır — null yoxlama məntiq lazımdır. `defaultValue` istifadə edildikdə parametr gəlmədikdə göstərilən default dəyər tətbiq olunur — `required` avtomatik `false` olur. `int`, `boolean` kimi primitive tiplər üçün `defaultValue` şərtdir, çünki primitive-lər `null` ola bilməz.
