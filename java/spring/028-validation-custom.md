# 028 — Spring Custom Validation — Geniş İzah
**Səviyyə:** İrəli


## Mündəricat
1. [Custom Constraint nədir?](#custom-constraint-nədir)
2. [Sadə custom annotasiya](#sadə-custom-annotasiya)
3. [Cross-field validation](#cross-field-validation)
4. [Service inject edilmiş validator](#service-inject-edilmiş-validator)
5. [Kompozit constraint](#kompozit-constraint)
6. [İntervyu Sualları](#intervyu-sualları)

---

## Custom Constraint nədir?

Standart annotasiyalar (`@NotBlank`, `@Email` və s.) kifayət etmədikdə öz validasiya annotasiyamızı yaradırıq. Custom constraint iki hissədən ibarətdir:
1. **Annotasiya** — `@interface`
2. **Validator** — `ConstraintValidator<A, T>` implement edir

---

## Sadə custom annotasiya

```java
// 1. Annotasiya yaratmaq
@Documented
@Constraint(validatedBy = AzerbaijaniPhoneValidator.class)
@Target({ElementType.FIELD, ElementType.PARAMETER})
@Retention(RetentionPolicy.RUNTIME)
public @interface AzPhone {

    String message() default "Azərbaycan telefon nömrəsi formatı yanlışdır (+994XXXXXXXXX)";

    Class<?>[] groups() default {};

    Class<? extends Payload>[] payload() default {};
}

// 2. Validator sinfi
public class AzerbaijaniPhoneValidator
        implements ConstraintValidator<AzPhone, String> {

    private static final Pattern AZ_PHONE_PATTERN =
        Pattern.compile("^\\+994(50|51|55|60|70|77|99)[0-9]{7}$");

    @Override
    public void initialize(AzPhone constraintAnnotation) {
        // Annotasiya parametrlərini oxumaq üçün (optional)
    }

    @Override
    public boolean isValid(String value,
                           ConstraintValidatorContext context) {
        // null dəyər — @NotNull ilə idarə edin
        if (value == null) return true;

        boolean valid = AZ_PHONE_PATTERN.matcher(value).matches();

        if (!valid) {
            // Default mesajı dəyişdirmək (optional)
            context.disableDefaultConstraintViolation();
            context.buildConstraintViolationWithTemplate(
                "Telefon +994 50/51/55/60/70/77/99 ilə başlamalıdır")
                .addConstraintViolation();
        }

        return valid;
    }
}

// 3. İstifadəsi
public class ContactRequest {

    @NotBlank
    @AzPhone
    private String phone;
}
```

---

## Cross-field validation

Bir neçə field-i birlikdə yoxlamaq (sinif səviyyəsində):

```java
// Annotasiya — sinifə tətbiq olunur
@Documented
@Constraint(validatedBy = PasswordMatchValidator.class)
@Target(ElementType.TYPE)
@Retention(RetentionPolicy.RUNTIME)
public @interface PasswordMatch {

    String message() default "Şifrələr uyğun gəlmir";

    Class<?>[] groups() default {};

    Class<? extends Payload>[] payload() default {};

    String password() default "password";
    String confirmPassword() default "confirmPassword";
}

// Validator
public class PasswordMatchValidator
        implements ConstraintValidator<PasswordMatch, Object> {

    private String passwordField;
    private String confirmPasswordField;

    @Override
    public void initialize(PasswordMatch annotation) {
        this.passwordField = annotation.password();
        this.confirmPasswordField = annotation.confirmPassword();
    }

    @Override
    public boolean isValid(Object obj, ConstraintValidatorContext context) {
        try {
            Object password = BeanWrapperImpl(obj)
                .getPropertyValue(passwordField);
            Object confirmPassword = new BeanWrapperImpl(obj)
                .getPropertyValue(confirmPasswordField);

            boolean valid = Objects.equals(password, confirmPassword);

            if (!valid) {
                // Xətanı confirmPassword field-ə əlavə et
                context.disableDefaultConstraintViolation();
                context.buildConstraintViolationWithTemplate(
                    "Şifrələr uyğun gəlmir")
                    .addPropertyNode(confirmPasswordField)
                    .addConstraintViolation();
            }

            return valid;

        } catch (Exception e) {
            return false;
        }
    }
}

// DTO-da istifadəsi
@PasswordMatch(
    password = "password",
    confirmPassword = "confirmPassword",
    message = "Şifrə və təkrar şifrə eyni olmalıdır"
)
public class RegisterRequest {

    @NotBlank
    @Size(min = 8)
    private String password;

    @NotBlank
    private String confirmPassword;

    @NotBlank
    @Email
    private String email;
}
```

**Tarix aralığı yoxlama nümunəsi:**
```java
@DateRange(message = "Başlama tarixi bitmə tarixindən əvvəl olmalıdır")
public class BookingRequest {

    @NotNull
    @Future
    private LocalDate startDate;

    @NotNull
    @Future
    private LocalDate endDate;
}

public class DateRangeValidator
        implements ConstraintValidator<DateRange, BookingRequest> {

    @Override
    public boolean isValid(BookingRequest request,
                           ConstraintValidatorContext context) {
        if (request.getStartDate() == null || request.getEndDate() == null) {
            return true; // @NotNull ilə idarə olunur
        }
        return request.getStartDate().isBefore(request.getEndDate());
    }
}
```

---

## Service inject edilmiş validator

Verilənlər bazası yoxlaması (unique email):

```java
@Documented
@Constraint(validatedBy = UniqueEmailValidator.class)
@Target(ElementType.FIELD)
@Retention(RetentionPolicy.RUNTIME)
public @interface UniqueEmail {
    String message() default "Bu email artıq istifadə olunur";
    Class<?>[] groups() default {};
    Class<? extends Payload>[] payload() default {};
}

// Spring bean-larını inject edə bilərik!
@Component
public class UniqueEmailValidator
        implements ConstraintValidator<UniqueEmail, String> {

    // Spring avtomatik inject edir
    private final UserRepository userRepository;

    public UniqueEmailValidator(UserRepository userRepository) {
        this.userRepository = userRepository;
    }

    @Override
    public boolean isValid(String email, ConstraintValidatorContext context) {
        if (email == null) return true;
        return !userRepository.existsByEmail(email);
    }
}

// İstifadəsi
public class CreateUserRequest {

    @NotBlank
    @Email
    @UniqueEmail
    private String email;
}
```

---

## Kompozit constraint

Bir neçə annotasiyanı birləşdirmək:

```java
// Kompozit annotasiya — @NotBlank + @Size + @Pattern
@NotBlank(message = "İstifadəçi adı boş ola bilməz")
@Size(min = 3, max = 20,
      message = "İstifadəçi adı 3-20 simvol arasında olmalıdır")
@Pattern(regexp = "^[a-zA-Z0-9_]+$",
         message = "İstifadəçi adı yalnız hərf, rəqəm, alt xətt içərə bilər")
@Documented
@Constraint(validatedBy = {}) // Validatedby boş — digər annotasiyalar işləyir
@Target(ElementType.FIELD)
@Retention(RetentionPolicy.RUNTIME)
@ReportAsSingleViolation // Yalnız bir xəta mesajı göstər
public @interface ValidUsername {
    String message() default "İstifadəçi adı yanlışdır";
    Class<?>[] groups() default {};
    Class<? extends Payload>[] payload() default {};
}

// İstifadəsi
public class UserRequest {

    @ValidUsername
    private String username;
}
```

---

## İntervyu Sualları

### 1. Custom constraint yaratmaq üçün nə lazımdır?
**Cavab:** İki şey: (1) `@Constraint(validatedBy=...)` ilə işarələnmiş annotasiya — `message()`, `groups()`, `payload()` metodları olmalıdır. (2) `ConstraintValidator<Annotation, Type>` implement edən validator sinfi — `isValid()` metodunda yoxlama məntiqi yazılır.

### 2. Cross-field validation necə həyata keçirilir?
**Cavab:** Annotasiya `@Target(ElementType.TYPE)` ilə sinif səviyyəsində tətbiq edilir. Validator `ConstraintValidator<A, SinifTipi>` olur. `isValid()` metodunda sinifin bütün field-lərinə çıxış var. Xəta spesifik field-ə əlavə edilə bilər.

### 3. Validator-da Spring bean inject etmək olurmu?
**Cavab:** Bəli. Validator `@Component` ilə işarələnməlidir. Spring Boot-da Hibernate Validator avtomatik Spring bean factory-dən yararlanır, buna görə `@Autowired` və ya konstruktor injection işləyir. Bu sayədə repository-dən DB yoxlaması etmək mümkündür.

### 4. @ReportAsSingleViolation nə üçündür?
**Cavab:** Kompozit constraint-də (bir neçə annotasiya birləşdirildikdə) istifadə edilir. Bu olmadan hər annotation üçün ayrı xəta mesajı göstərilir. `@ReportAsSingleViolation` ilə yalnız kompozit annotasiyanın öz mesajı göstərilir.

### 5. isValid() metodunda null dəyəri necə idarə etmək lazımdır?
**Cavab:** Adətən `if (value == null) return true` yazılır. Null yoxlaması ayrıca `@NotNull` annotasiyasının məsuliyyətidir. Bu Separation of Concerns prinsipinə uyğundur və validator-ları kompoz edə bilmək imkanı verir.

*Son yenilənmə: 2026-04-10*
