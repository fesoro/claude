# Validasiya: Spring vs Laravel

## Giris

Validasiya istifadeciden gelen datalarin durzgunluyunu yoxlamaq prosesidir. Adi mecburidirmi? Email formati duzgundurmu? Yas 0-dan boyukdurmu? Bu suallara cavab vermek ucun her iki framework guclu validasiya sistemleri teklif edir.

Spring Java-nin Bean Validation spesifikasiyasina (JSR 380) esaslanir ve annotasiyalar ile isleyir. Laravel ise oz validasiya sistemini teklif edir - string esasli qaydalar, Form Request-ler ve Rule obyektleri ile.

## Spring-de Istifadesi

### Esas Annotasiyalar

Spring, Hibernate Validator (Bean Validation referans implementasiyasi) istifade edir:

```java
// DTO sinifinde validasiya annotasiyalari
public class CreateUserRequest {

    @NotBlank(message = "Ad bos ola bilmez")
    @Size(min = 2, max = 100, message = "Ad 2-100 simvol arasinda olmalidir")
    private String name;

    @NotBlank(message = "Email mecburidir")
    @Email(message = "Duzgun email formati daxil edin")
    private String email;

    @NotBlank(message = "Sifreni daxil edin")
    @Size(min = 8, max = 64, message = "Sifre 8-64 simvol olmalidir")
    @Pattern(
        regexp = "^(?=.*[a-z])(?=.*[A-Z])(?=.*\\d).*$",
        message = "Sifre en az 1 boyuk herf, 1 kicik herf ve 1 reqem olmalidir"
    )
    private String password;

    @NotNull(message = "Yas mecburidir")
    @Min(value = 18, message = "Yas en az 18 olmalidir")
    @Max(value = 120, message = "Yas en cox 120 ola biler")
    private Integer age;

    @Past(message = "Dogum tarixi kecmisde olmalidir")
    private LocalDate birthDate;

    @FutureOrPresent(message = "Baslama tarixi kecmisde ola bilmez")
    private LocalDate startDate;

    @Positive(message = "Maas musbat olmalidir")
    @DecimalMax(value = "999999.99", message = "Maas coxdur")
    private BigDecimal salary;

    @Size(max = 5, message = "En cox 5 etiket ola biler")
    private List<@NotBlank(message = "Etiket bos ola bilmez") String> tags;

    // getter/setter-ler
}
```

### Controller-de @Valid istifadesi

```java
@RestController
@RequestMapping("/api/users")
public class UserController {

    @PostMapping
    public ResponseEntity<?> store(@Valid @RequestBody CreateUserRequest request) {
        // Bura catibsa, validasiya kecib
        User user = userService.create(request);
        return ResponseEntity.status(201).body(user);
    }
}
```

Eger validasiya ugursuz olarsa, Spring avtomatik olaraq `MethodArgumentNotValidException` atir.

### BindingResult ile Manual Xeta Idare Etme

```java
@RestController
@RequestMapping("/api/users")
public class UserController {

    @PostMapping
    public ResponseEntity<?> store(
            @Valid @RequestBody CreateUserRequest request,
            BindingResult bindingResult) {

        // Xetalari ozumuz idare edirik
        if (bindingResult.hasErrors()) {
            Map<String, String> errors = new HashMap<>();

            bindingResult.getFieldErrors().forEach(error ->
                errors.put(error.getField(), error.getDefaultMessage())
            );

            return ResponseEntity.badRequest().body(Map.of(
                "message", "Validasiya xetasi",
                "errors", errors
            ));
        }

        User user = userService.create(request);
        return ResponseEntity.status(201).body(user);
    }
}
```

### Qlobal Exception Handler ile

Daha yaxsi yanasmda: xetalari merkezi bir yerde tutmaq:

```java
@RestControllerAdvice
public class ValidationExceptionHandler {

    @ExceptionHandler(MethodArgumentNotValidException.class)
    public ResponseEntity<Map<String, Object>> handleValidationErrors(
            MethodArgumentNotValidException ex) {

        Map<String, String> fieldErrors = new HashMap<>();
        ex.getBindingResult().getFieldErrors().forEach(error ->
            fieldErrors.put(error.getField(), error.getDefaultMessage())
        );

        Map<String, Object> body = new LinkedHashMap<>();
        body.put("message", "Validasiya xetasi");
        body.put("errors", fieldErrors);
        body.put("timestamp", LocalDateTime.now());

        return ResponseEntity.status(422).body(body);
    }

    @ExceptionHandler(ConstraintViolationException.class)
    public ResponseEntity<Map<String, Object>> handleConstraintViolation(
            ConstraintViolationException ex) {

        Map<String, String> errors = new HashMap<>();
        ex.getConstraintViolations().forEach(violation -> {
            String field = violation.getPropertyPath().toString();
            errors.put(field, violation.getMessage());
        });

        return ResponseEntity.status(422).body(Map.of(
            "message", "Validasiya xetasi",
            "errors", errors
        ));
    }
}
```

### Path Variable ve Request Param validasiyasi

```java
@RestController
@Validated  // Sinif seviyyesinde elave olunmalidir
@RequestMapping("/api/products")
public class ProductController {

    @GetMapping("/{id}")
    public Product show(
            @PathVariable @Positive(message = "ID musbat olmalidir") Long id) {
        return productService.findById(id);
    }

    @GetMapping
    public Page<Product> search(
            @RequestParam @Min(0) int page,
            @RequestParam @Min(1) @Max(100) int size,
            @RequestParam(required = false) @Size(min = 2) String query) {
        return productService.search(query, PageRequest.of(page, size));
    }
}
```

### Xususi Validator Yaratmaq

```java
// 1. Annotasiya tanimla
@Target({ElementType.FIELD, ElementType.PARAMETER})
@Retention(RetentionPolicy.RUNTIME)
@Constraint(validatedBy = UniqueEmailValidator.class)
@Documented
public @interface UniqueEmail {
    String message() default "Bu email artiq istifade olunur";
    Class<?>[] groups() default {};
    Class<? extends Payload>[] payload() default {};
}

// 2. Validator sinfi yaz
@Component
public class UniqueEmailValidator implements ConstraintValidator<UniqueEmail, String> {

    private final UserRepository userRepository;

    public UniqueEmailValidator(UserRepository userRepository) {
        this.userRepository = userRepository;
    }

    @Override
    public boolean isValid(String email, ConstraintValidatorContext context) {
        if (email == null) return true;  // @NotNull ayri yoxlayir
        return !userRepository.existsByEmail(email);
    }
}

// 3. Istifade
public class CreateUserRequest {
    @NotBlank
    @Email
    @UniqueEmail  // Bizim xususi annotasiya
    private String email;
}
```

### Murekkeb Xususi Validator

```java
// Sifre teyidini yoxlayan sinif seviyyeli validator
@Target(ElementType.TYPE)
@Retention(RetentionPolicy.RUNTIME)
@Constraint(validatedBy = PasswordMatchValidator.class)
public @interface PasswordMatch {
    String message() default "Sifreler uygun gelmir";
    Class<?>[] groups() default {};
    Class<? extends Payload>[] payload() default {};
}

public class PasswordMatchValidator implements ConstraintValidator<PasswordMatch, Object> {

    @Override
    public boolean isValid(Object obj, ConstraintValidatorContext context) {
        if (obj instanceof RegisterRequest request) {
            boolean valid = request.getPassword().equals(request.getPasswordConfirmation());
            if (!valid) {
                context.disableDefaultConstraintViolation();
                context.buildConstraintViolationWithTemplate("Sifreler uygun gelmir")
                       .addPropertyNode("passwordConfirmation")
                       .addConstraintViolation();
            }
            return valid;
        }
        return true;
    }
}

@PasswordMatch
public class RegisterRequest {
    @NotBlank
    @Email
    private String email;

    @NotBlank
    @Size(min = 8)
    private String password;

    @NotBlank
    private String passwordConfirmation;
}
```

### Validation Groups

Ferqli emeliyyatlar ucun ferqli validasiya qaydalari:

```java
// Qrup interface-leri
public interface OnCreate {}
public interface OnUpdate {}

// DTO-da qruplarla annotasiya
public class ProductRequest {

    @Null(groups = OnCreate.class, message = "Yaradarkен ID olmamalidir")
    @NotNull(groups = OnUpdate.class, message = "Yenileyerken ID mecburidir")
    private Long id;

    @NotBlank(groups = {OnCreate.class, OnUpdate.class})
    private String name;

    @NotNull(groups = OnCreate.class, message = "Qiymet mecburidir")
    @Positive(groups = {OnCreate.class, OnUpdate.class})
    private BigDecimal price;

    @NotBlank(groups = OnCreate.class)
    private String sku;  // Yalniz yaradarkken mecburi
}

// Controller-de istifade
@RestController
@RequestMapping("/api/products")
public class ProductController {

    @PostMapping
    public ResponseEntity<Product> store(
            @Validated(OnCreate.class) @RequestBody ProductRequest request) {
        return ResponseEntity.status(201).body(productService.create(request));
    }

    @PutMapping("/{id}")
    public ResponseEntity<Product> update(
            @PathVariable Long id,
            @Validated(OnUpdate.class) @RequestBody ProductRequest request) {
        return ResponseEntity.ok(productService.update(id, request));
    }
}
```

### Java Record ile Validasiya

```java
// Java 16+ record ile sade DTO
public record CreateOrderRequest(
    @NotNull(message = "Mehsul ID mecburidir")
    Long productId,

    @Positive(message = "Miqdar musbat olmalidir")
    int quantity,

    @NotBlank(message = "Catdirilma unvani mecburidir")
    @Size(max = 500)
    String shippingAddress,

    @Size(max = 1000)
    String notes
) {}
```

## Laravel-de Istifadesi

### Controller-de inline validasiya

```php
class UserController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        // validate() ugursuz olarsa, avtomatik 422 cavab qaytarir
        $validated = $request->validate([
            'name' => 'required|string|min:2|max:100',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',  // password_confirmation sahesi de lazim
            'age' => 'required|integer|min:18|max:120',
            'birth_date' => 'required|date|before:today',
            'salary' => 'nullable|numeric|min:0|max:999999.99',
            'tags' => 'sometimes|array|max:5',
            'tags.*' => 'string|max:50',  // Array elementleri
            'avatar' => 'nullable|image|mimes:jpg,png|max:2048',
        ]);

        $user = User::create($validated);

        return response()->json($user, 201);
    }
}
```

### Array sintaksisi ile qaydalar

```php
use Illuminate\Validation\Rule;

$validated = $request->validate([
    'name' => ['required', 'string', 'min:2', 'max:100'],
    'email' => [
        'required',
        'email',
        Rule::unique('users', 'email')->ignore($user->id),  // Yenileyerken ozunu istisna et
    ],
    'role' => ['required', Rule::in(['admin', 'editor', 'viewer'])],
    'status' => ['required', Rule::notIn(['banned', 'suspended'])],
    'country' => ['required', Rule::exists('countries', 'code')],
]);
```

### Form Request sinfleri

Validasiya mentiqini controller-den ayirmaq ucun:

```bash
php artisan make:request StoreOrderRequest
```

```php
// app/Http/Requests/StoreOrderRequest.php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
{
    /**
     * Avtorizasiya yoxlamasi
     */
    public function authorize(): bool
    {
        return $this->user()->can('create-orders');
    }

    /**
     * Validasiya qaydalari
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'shipping_address' => ['required', 'string', 'max:500'],
            'billing_address' => ['sometimes', 'string', 'max:500'],
            'coupon_code' => ['nullable', 'string', 'exists:coupons,code'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'payment_method' => ['required', Rule::in(['card', 'bank_transfer', 'cash'])],
        ];
    }

    /**
     * Xeta mesajlarini ozellesdir
     */
    public function messages(): array
    {
        return [
            'items.required' => 'En az bir mehsul secilmelidir.',
            'items.*.product_id.exists' => 'Secilen mehsul movcud deyil.',
            'items.*.quantity.min' => 'Mehsul miqdari en az 1 olmalidir.',
            'shipping_address.required' => 'Catdirilma unvani mecburidir.',
            'payment_method.in' => 'Etibarsiz odenis usulu.',
        ];
    }

    /**
     * Sahə adlarini insan oxunaqli etmek
     */
    public function attributes(): array
    {
        return [
            'items.*.product_id' => 'mehsul',
            'items.*.quantity' => 'miqdar',
            'shipping_address' => 'catdirilma unvani',
            'coupon_code' => 'kupon kodu',
        ];
    }

    /**
     * Validasiyadan evvel datani hazirlama
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'coupon_code' => $this->coupon_code
                ? strtoupper(trim($this->coupon_code))
                : null,
        ]);
    }

    /**
     * Validasiyadan sonra elave yoxlamalar
     */
    public function after(): array
    {
        return [
            function ($validator) {
                if ($this->hasDuplicateProducts()) {
                    $validator->errors()->add(
                        'items',
                        'Eyni mehsul bir nece defe elave oluna bilmez.'
                    );
                }
            }
        ];
    }

    private function hasDuplicateProducts(): bool
    {
        $productIds = collect($this->items)->pluck('product_id');
        return $productIds->count() !== $productIds->unique()->count();
    }
}

// Controller-de istifade - cok temiz
class OrderController extends Controller
{
    public function store(StoreOrderRequest $request): JsonResponse
    {
        // Bura catibsa: avtorizasiya kecib + validasiya kecib
        $order = $this->orderService->create($request->validated());
        return response()->json($order, 201);
    }
}
```

### Xususi Rule Obyektleri

```bash
php artisan make:rule StrongPassword
```

```php
// app/Rules/StrongPassword.php
namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class StrongPassword implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (strlen($value) < 8) {
            $fail('Sifre en az 8 simvol olmalidir.');
        }

        if (!preg_match('/[A-Z]/', $value)) {
            $fail('Sifrede en az 1 boyuk herf olmalidir.');
        }

        if (!preg_match('/[a-z]/', $value)) {
            $fail('Sifrede en az 1 kicik herf olmalidir.');
        }

        if (!preg_match('/[0-9]/', $value)) {
            $fail('Sifrede en az 1 reqem olmalidir.');
        }

        if (!preg_match('/[!@#$%^&*]/', $value)) {
            $fail('Sifrede en az 1 xususi simvol olmalidir.');
        }
    }
}

// Istifade
$request->validate([
    'password' => ['required', new StrongPassword],
]);
```

### Verilener bazasi ile isleyen xususi Rule

```php
namespace App\Rules;

use App\Models\Product;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ProductInStock implements ValidationRule
{
    public function __construct(
        private readonly int $requestedQuantity
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $product = Product::find($value);

        if (!$product) {
            $fail('Mehsul tapilmadi.');
            return;
        }

        if (!$product->is_active) {
            $fail("'{$product->name}' hal-hazirda satisda deyil.");
            return;
        }

        if ($product->stock < $this->requestedQuantity) {
            $fail("'{$product->name}' ucun kifayet qeder stok yoxdur. Movcud: {$product->stock}");
        }
    }
}

// Istifade
$request->validate([
    'items.*.product_id' => [
        'required',
        new ProductInStock($request->input('items.*.quantity')),
    ],
]);
```

### Serti Validasiya

```php
use Illuminate\Validation\Rule;

$request->validate([
    'payment_method' => 'required|in:card,bank_transfer,cash',

    // Yalniz payment_method "card" olanda mecburi
    'card_number' => 'required_if:payment_method,card|nullable|digits:16',
    'card_expiry' => 'required_if:payment_method,card|nullable|date_format:m/y',
    'card_cvv' => 'required_if:payment_method,card|nullable|digits:3',

    // Yalniz payment_method "bank_transfer" olanda
    'bank_name' => 'required_if:payment_method,bank_transfer|nullable|string',
    'iban' => 'required_if:payment_method,bank_transfer|nullable|string|size:28',

    // Baska bir sahe varsa mecburi
    'billing_address' => 'required_with:billing_name',

    // Her hansi biri varsa hamisi mecburi
    'street' => 'required_with_all:city,zip_code',

    // Sahe null/bos deyilse, onda yoxla
    'website' => 'nullable|url',

    // Sometimes - sahe movcuddursa yoxla, yoxdursa ignore et
    'nickname' => 'sometimes|string|max:50',
]);

// Closure ile serti qaydalar
use Illuminate\Validation\Validator;

$request->validate([
    'type' => 'required|in:individual,company',
    'company_name' => [
        Rule::requiredIf(fn () => $request->type === 'company'),
        'nullable',
        'string',
        'max:255',
    ],
    'tax_number' => [
        Rule::requiredIf(fn () => $request->type === 'company'),
        'nullable',
        'string',
        'size:10',
    ],
]);
```

### Nested Data Validasiyasi

```php
$request->validate([
    // Obyekt icinde
    'address.street' => 'required|string',
    'address.city' => 'required|string',
    'address.zip' => 'required|string|regex:/^\d{5}$/',

    // Array icinde obyektler
    'contacts' => 'required|array|min:1|max:5',
    'contacts.*.name' => 'required|string|max:100',
    'contacts.*.phone' => 'required|string|regex:/^\+994\d{9}$/',
    'contacts.*.type' => 'required|in:mobile,home,work',
    'contacts.*.is_primary' => 'sometimes|boolean',

    // Derin nested
    'departments.*.employees.*.name' => 'required|string',
]);
```

### Manual Validator

```php
use Illuminate\Support\Facades\Validator;

$validator = Validator::make($request->all(), [
    'name' => 'required|string',
    'email' => 'required|email',
]);

// Manual yoxlama
if ($validator->fails()) {
    return response()->json([
        'message' => 'Validasiya xetasi',
        'errors' => $validator->errors(),
    ], 422);
}

// Validasiyadan kecmis datani al
$validated = $validator->validated();

// Elave xeta elave etmek
$validator->after(function ($validator) use ($request) {
    if ($this->somethingElseIsInvalid($request)) {
        $validator->errors()->add('field', 'Bu sahe ile problem var.');
    }
});
```

## Esas Ferqler

| Xususiyyet | Spring | Laravel |
|---|---|---|
| **Validasiya yeri** | DTO annotasiyalari | Controller/FormRequest/inline |
| **Qaydalar formati** | Java annotasiyalar (`@NotBlank`, `@Size`) | String (`'required\|string\|max:255'`) |
| **Tetikleme** | `@Valid` / `@Validated` | `$request->validate()` / FormRequest type-hint |
| **Xeta cavabi** | Exception atilir, handler tutur | Avtomatik 422 JSON cavab |
| **Xususi qaydalar** | `ConstraintValidator` interface | `ValidationRule` interface / Closure |
| **Serti qaydalar** | Validation groups | `required_if`, `required_with`, `Rule::requiredIf()` |
| **Nested validasiya** | `@Valid` nested obyektde | Dot notasiya: `address.city` |
| **Xeta mesajlari** | Annotasiya `message` parametri | `messages()` metodu / `lang/` fayllari |
| **Compile zamani yoxlama** | Beli (annotasiya tipi yoxlanir) | Yox (runtime-da) |
| **DB ile validasiya** | Xususi validator lazim | Daxili: `exists:`, `unique:` |
| **Fayl validasiyasi** | Xususi validator lazim | Daxili: `image`, `mimes:`, `max:` |
| **Sifre teyidi** | Xususi validator lazim | Daxili: `confirmed` |

## Niye Bele Ferqler Var?

### Spring: Annotasiya esasli, tip tehlukesiz

1. **JSR 380 standarti**: Spring oz validasiya sistemini yaratmir, Java EE-nin Bean Validation standartini istifade edir. Bu, framework-dan asili olmayan standartdir - eyni annotasiyalar Spring-siz de isleyir.

2. **DTO uzerinde validasiya**: Qaydalar data sinifinin uzerinde tanimlanir, controller-de yox. Bu menteqi asililikdir - "bu data nece olmalidir" sualinin cavabi data sinifindedir. Amma bu o demekdir ki, ferqli emeliyyatlar ucun ferqli DTO-lar ve ya validation groups lazim olur.

3. **Compile zamani destey**: Annotasiya tipleri compile zamani yoxlanir - `@Size` bir `Integer` saheye tetbiq ede bilmezsiniz. Bu, bir sinif xetalari azaldir.

4. **Xususi validator-ler cok kodludur**: `@interface` + `ConstraintValidator` - iki fayl lazimdir. Bu, Java-nin felsefesine uygunudur (aciq, tipli), amma sade qaydalar ucun coxlu boilerplate yaradir.

### Laravel: Serti, ifadeli, praktik

1. **String esasli qaydalar**: `'required|email|unique:users'` yazmaq asandir ve oxunaqlidir. Bu, Laravel-in "developer experience" felsefesidir. Amma compile zamani yoxlama yoxdur - sehv qayda adi yazsaniz, runtime-da xeta cixir.

2. **Daxili qaydalar zengindlir**: `unique:users`, `exists:products,id`, `confirmed`, `image`, `mimes:jpg,png` kimi qaydalar framework ile gelir. Spring-de bunlarin her birini elle yazmaq lazimdir.

3. **Form Request ayriligi**: Validasiya + avtorizasiya mentiqini controller-den ayirmaq. Bu, Single Responsibility prinsipine uygunudur. Spring-de validasiya DTO-da, avtorizasiya ise Spring Security-dedir - ferqli yerlerde.

4. **Serti validasiya**: `required_if`, `required_with`, `required_without` kimi qaydalar ortaq web ehtiyaclarini qarisiq kod olmadan hell edir. Spring-de bunu hell etmek ucun validation groups ve ya xususi validator lazimdir.

5. **Dot notasiya**: `contacts.*.phone` ile array icindeki butun elementleri yoxlamaq. Spring-de nested obyektlerde `@Valid` istifade olunur, amma bu qeder cevik deyil.

## Hansi Framework-de Var, Hansinda Yoxdur?

### Yalniz Spring-de olan xususiyyetler

- **Validation Groups**: Eyni DTO-nu ferqli emeliyyatlar ucun ferqli qaydalarla yoxlamaq (`OnCreate`, `OnUpdate`). Laravel-de bunun ucun ayri FormRequest sinfleri yazilir.

- **Compile zamani annotasiya yoxlamasi**: `@Size` annotasiyasini `int` tipli saheye tetbiq ede bilmezsiniz - compiler xeta verir.

- **`@Validated` sinif seviyyesinde**: Controller sinifine `@Validated` elave edib, metod parametrlerini birbase validasiya etmek (`@PathVariable @Positive Long id`).

- **Cross-parameter validation**: Metod parametrleri arasinda validasiya (meselen, start date < end date).

### Yalniz Laravel-de olan xususiyyetler

- **Daxili DB validasiya qaydalari**: `unique:users,email`, `exists:categories,id` - verilener bazasi ile birbase isleyen qaydalar. Spring-de xususi validator yazmaq lazimdir.

- **`confirmed` qaydasi**: `password` sahesi ucun avtomatik olaraq `password_confirmation` sahesinini uygunlugunu yoxlayir.

- **Fayl validasiyasi**: `image`, `mimes:jpg,png,webp`, `max:2048` (KB), `dimensions:min_width=100` - hamisi daxili.

- **`required_if` / `required_with` ailesii**: Serti mecburilik qaydalari. Spring-de bunu validation groups ve ya xususi validator ile hell etmek lazimdir.

- **`prepareForValidation()`**: Validasiyadan evvel datani temizlemek/hazirlama (trim, uppercase, vs.).

- **`after()` hook**: Validasiyadan sonra elave biznes qaydalarini yoxlamaq.

- **`bail` qaydasi**: Ilk xetada dayanmaq - sonraki qaydalari yoxlamamaq. `'email' => 'bail|required|email|unique:users'`.

- **Dot notasiya ile array validasiyasi**: `contacts.*.phone` - array icindeki her elementin xususi sahesini yoxlamaq. Spring-de bu mümkündür amma daha çox kod tələb edir.

### Her ikisinde olan, amma ferqli isleyen

- **Xususi qaydalar**: Spring-de `@interface` + `ConstraintValidator` (2 fayl, coxlu boilerplate). Laravel-de `Rule` sinfi (1 fayl, az kod) ve ya sade closure.
- **Xeta mesajlari**: Spring-de annotasiya `message` parametri. Laravel-de `messages()` metodu, `lang/az/validation.php` fayli ile lokalizasiya.
- **Nested validasiya**: Spring-de `@Valid` nested obyektde. Laravel-de dot notasiya `address.city`.
- **Qrup/scenario**: Spring-de validation groups. Laravel-de ayri FormRequest sinfleri.
