# Spring Validation — Geniş İzah

## Mündəricat
1. [Bean Validation nədir?](#bean-validation-nədir)
2. [Əsas annotasiyalar](#əsas-annotasiyalar)
3. [@Valid vs @Validated](#valid-vs-validated)
4. [BindingResult](#bindingresult)
5. [Global exception handler](#global-exception-handler)
6. [Validation Groups](#validation-groups)
7. [İntervyu Sualları](#intervyu-sualları)

---

## Bean Validation nədir?

**Bean Validation (JSR-380 / Jakarta Validation)** — Java obyektlərinin field-lərini constraint annotasiyaları ilə yoxlayan standart mexanizmdir. Spring MVC `@Valid` ilə bunu avtomatik işlədir.

```xml
<!-- Spring Boot Starter ilə avtomatik gəlir -->
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-validation</artifactId>
</dependency>
```

---

## Əsas annotasiyalar

```java
public class CreateUserRequest {

    @NotNull(message = "Ad boş ola bilməz")
    @NotBlank(message = "Ad yalnız boşluqdan ibarət ola bilməz")
    @Size(min = 2, max = 50, message = "Ad 2-50 simvol arasında olmalıdır")
    private String name;

    @NotBlank(message = "Email boş ola bilməz")
    @Email(message = "Email formatı yanlışdır")
    private String email;

    @NotNull(message = "Yaş boş ola bilməz")
    @Min(value = 18, message = "Minimum yaş 18-dir")
    @Max(value = 120, message = "Maksimum yaş 120-dir")
    private Integer age;

    @Positive(message = "Maaş müsbət olmalıdır")
    private BigDecimal salary;

    @PositiveOrZero(message = "Balans sıfır və ya müsbət olmalıdır")
    private BigDecimal balance;

    @Negative(message = "Borc mənfi olmalıdır")
    private BigDecimal debt;

    @Pattern(regexp = "^\\+994[0-9]{9}$",
             message = "Telefon formatı: +994XXXXXXXXX")
    private String phone;

    @NotEmpty(message = "Rollar boş ola bilməz")
    private List<String> roles;

    @Future(message = "Tarix gələcəkdə olmalıdır")
    private LocalDate startDate;

    @Past(message = "Doğum tarixi keçmişdə olmalıdır")
    private LocalDate birthDate;

    @FutureOrPresent(message = "Tarix bu gün və ya gələcəkdə olmalıdır")
    private LocalDateTime eventTime;
}
```

**Fərqlər:**
- `@NotNull` — null ola bilməz (boş string keçir)
- `@NotEmpty` — null və ya boş ola bilməz (boşluqlar keçir)
- `@NotBlank` — null, boş, yalnız boşluq ola bilməz

---

## @Valid vs @Validated

```java
@RestController
@RequestMapping("/api/users")
public class UserController {

    // @Valid — Jakarta Validation
    // Controller parametrində istifadə
    @PostMapping
    public ResponseEntity<User> createUser(
            @Valid @RequestBody CreateUserRequest request) {
        return ResponseEntity.ok(userService.create(request));
    }

    // @Validated — Spring-ə xasdır
    // Həm controller-də, həm service-də istifadə edilə bilər
    // Validation Group dəstəkləyir
    @PutMapping("/{id}")
    public ResponseEntity<User> updateUser(
            @PathVariable Long id,
            @Validated(UpdateGroup.class) @RequestBody UpdateUserRequest request) {
        return ResponseEntity.ok(userService.update(id, request));
    }

    // Path variable-ı validate etmək üçün
    // Sinfə @Validated lazımdır!
    @GetMapping("/{id}")
    public ResponseEntity<User> getUser(
            @PathVariable @Positive(message = "ID müsbət olmalıdır") Long id) {
        return ResponseEntity.ok(userService.findById(id));
    }
}

// Path variable validation üçün sinfi @Validated ilə işarə et
@RestController
@RequestMapping("/api/users")
@Validated // ← Bunu əlavə et
public class UserController {
    // ...
}
```

```java
// Service-də validation (@Validated + @Valid)
@Service
@Validated
public class UserService {

    public User createUser(@Valid CreateUserRequest request) {
        // @Valid — parametr validation
        return userRepository.save(mapper.toEntity(request));
    }

    public List<User> getUsersByAge(
            @Min(18) @Max(100) int age) {
        // Sadə tip validation
        return userRepository.findByAge(age);
    }
}
```

---

## BindingResult

```java
@RestController
public class FormController {

    // BindingResult — xətaları manual idarə etmək üçün
    // Exception atılmır — xətaları siz idarə edirsiniz
    @PostMapping("/users")
    public ResponseEntity<?> createUser(
            @Valid @RequestBody CreateUserRequest request,
            BindingResult bindingResult) {

        // Xəta varmı?
        if (bindingResult.hasErrors()) {
            Map<String, String> errors = new HashMap<>();

            // Field xətaları
            bindingResult.getFieldErrors().forEach(error ->
                errors.put(error.getField(), error.getDefaultMessage())
            );

            // Global xətalar (class-level constraint)
            bindingResult.getGlobalErrors().forEach(error ->
                errors.put(error.getObjectName(), error.getDefaultMessage())
            );

            return ResponseEntity.badRequest().body(errors);
        }

        return ResponseEntity.ok(userService.create(request));
    }
}
```

**Qeyd:** `BindingResult` `@Valid` parametrindən **dərhal sonra** gəlməlidir.

---

## Global exception handler

```java
@RestControllerAdvice
public class ValidationExceptionHandler {

    // @Valid xətası (controller method-level)
    @ExceptionHandler(MethodArgumentNotValidException.class)
    public ResponseEntity<Map<String, Object>> handleValidationErrors(
            MethodArgumentNotValidException ex) {

        Map<String, String> fieldErrors = new LinkedHashMap<>();

        ex.getBindingResult().getFieldErrors().forEach(error ->
            fieldErrors.put(error.getField(), error.getDefaultMessage())
        );

        Map<String, Object> response = new LinkedHashMap<>();
        response.put("status", HttpStatus.BAD_REQUEST.value());
        response.put("message", "Validasiya xətası");
        response.put("errors", fieldErrors);
        response.put("timestamp", LocalDateTime.now());

        return ResponseEntity.badRequest().body(response);
    }

    // @Validated (path variable, request param) xətası
    @ExceptionHandler(ConstraintViolationException.class)
    public ResponseEntity<Map<String, Object>> handleConstraintViolation(
            ConstraintViolationException ex) {

        Map<String, String> errors = new LinkedHashMap<>();

        ex.getConstraintViolations().forEach(violation -> {
            String path = violation.getPropertyPath().toString();
            String message = violation.getMessage();
            errors.put(path, message);
        });

        Map<String, Object> response = new LinkedHashMap<>();
        response.put("status", HttpStatus.BAD_REQUEST.value());
        response.put("message", "Parametr validasiya xətası");
        response.put("errors", errors);

        return ResponseEntity.badRequest().body(response);
    }
}
```

**Cavab nümunəsi:**
```json
{
  "status": 400,
  "message": "Validasiya xətası",
  "errors": {
    "name": "Ad 2-50 simvol arasında olmalıdır",
    "email": "Email formatı yanlışdır",
    "age": "Minimum yaş 18-dir"
  },
  "timestamp": "2026-04-10T10:30:00"
}
```

---

## Validation Groups

Eyni DTO-nu müxtəlif əməliyyatlar üçün fərqli validasiya ilə istifadə etmək:

```java
// Group interface-ləri
public interface CreateGroup {}
public interface UpdateGroup {}

// DTO-da qruplar
public class UserRequest {

    // Yalnız update zamanı tələb olunur
    @NotNull(groups = UpdateGroup.class, message = "ID boş ola bilməz")
    private Long id;

    // Hər iki halda tələb olunur
    @NotBlank(groups = {CreateGroup.class, UpdateGroup.class},
              message = "Ad boş ola bilməz")
    @Size(min = 2, max = 50,
          groups = {CreateGroup.class, UpdateGroup.class})
    private String name;

    // Yalnız create zamanı tələb olunur
    @NotBlank(groups = CreateGroup.class,
              message = "Şifrə boş ola bilməz")
    @Size(min = 8, groups = CreateGroup.class,
          message = "Şifrə minimum 8 simvol olmalıdır")
    private String password;

    @Email(groups = {CreateGroup.class, UpdateGroup.class})
    private String email;
}

// Controller-də
@RestController
public class UserController {

    @PostMapping("/users")
    public ResponseEntity<User> create(
            @Validated(CreateGroup.class) @RequestBody UserRequest request) {
        return ResponseEntity.ok(userService.create(request));
    }

    @PutMapping("/users/{id}")
    public ResponseEntity<User> update(
            @PathVariable Long id,
            @Validated(UpdateGroup.class) @RequestBody UserRequest request) {
        return ResponseEntity.ok(userService.update(id, request));
    }
}
```

**@GroupSequence — ardıcıl yoxlama:**
```java
@GroupSequence({CreateGroup.class, UpdateGroup.class, Default.class})
public interface ValidationOrder {}
```

---

## İntervyu Sualları

### 1. @NotNull, @NotEmpty, @NotBlank fərqi nədir?
**Cavab:** `@NotNull` — yalnız null yoxlayır, boş string keçər. `@NotEmpty` — null və boş string yoxlayır (boşluqlar keçər). `@NotBlank` — null, boş və yalnız boşluqdan ibarət stringi yoxlayır. String üçün ən sərt yoxlama `@NotBlank`-dır.

### 2. @Valid vs @Validated fərqi nədir?
**Cavab:** `@Valid` — Jakarta EE standartıdır, Validation Group dəstəkləmir. `@Validated` — Spring-ə xasdır, Validation Group dəstəkləyir, həm controller-də həm service-də istifadə edilə bilər. Path variable/request param validation üçün sinfin özünə `@Validated` əlavə edilməlidir.

### 3. BindingResult nə üçün lazımdır?
**Cavab:** Normalda `@Valid` xəta olduqda `MethodArgumentNotValidException` atır. `BindingResult` istifadə edildikdə exception atılmır — xətaları özünüz idarə edirsiniz. `BindingResult` həmişə `@Valid` parametrindən birbaşa sonra gəlməlidir.

### 4. Validation Groups nə zaman istifadə edilir?
**Cavab:** Eyni DTO create/update əməliyyatları üçün istifadə edildikdə. Məsələn, create zamanı şifrə məcburidir, update zamanı isə məcburi deyil. `@Validated(CreateGroup.class)` ilə müəyyən qrup üçün validasiya işlədilir.

### 5. ConstraintViolationException nə zaman atılır?
**Cavab:** `@Validated` ilə annotasiya edilmiş sinifdə (service/controller) path variable, request param, və ya metod parametrlərinin validasiyası uğursuz olduqda. `MethodArgumentNotValidException` isə `@RequestBody` ilə `@Valid` istifadə edildikdə atılır.

*Son yenilənmə: 2026-04-10*
