# Input Validation (Senior)

## İcmal

Go-da daxili validation yoxdur — `go-playground/validator` paketi sənaye standartıdır. Struct tag-ları ilə deklarativ validation, custom validator dəstəyi, HTTP middleware ilə inteqrasiya. PHP/Laravel-in Form Request sinifinin Go ekvivalenti.

## Niyə Vacibdir

- Hər API endpoint-i sərhəd nöqtəsidir — xarici inputa heç vaxt etibar olunmaz
- Business logic-ə çatmadan validation tamamlanmalıdır
- Struct tag-ları ilə validation qaydaları kodun yanında görünür — ayrı documentation lazım deyil
- Error mesajları strukturlu olduqda client tərəf daha asan işləyir

## Əsas Anlayışlar

**Əsas tag-lar:**
```
required     — boş ola bilməz
email        — email formatı
min=3        — minimum uzunluq (string) / dəyər (rəqəm)
max=100      — maksimum uzunluq / dəyər
oneof=a b c  — siyahıdan biri olmalıdır
url          — valid URL
uuid         — UUID formatı
numeric      — yalnız rəqəm
alphanum     — hərflər + rəqəmlər
gt=0         — sıfırdan böyük
gte=18       — 18 və ya böyük
lt=100       — 100-dən kiçik
dive         — slice/map elementlərini validate et
```

**`validate:"required,email"` — AND məntiqi (hamısı keçməlidir)**
**`validate:"email|url"` — OR məntiqi deyil, `|` yazmayın — ayrı struct field istifadə edin**

## Praktik Baxış

**Nə vaxt istifadə et:**
- HTTP request body decode olunduqdan sonra
- Service layer-ə çatmadan
- Domain object yaratmadan əvvəl

**Nə vaxt istifadə etmə:**
- Domain validation üçün (email formatı business rule-dur → domain-də `NewEmail(email)` konstruktoru)
- Validator struct-ı global state ilə initialization edir — `sync.Once` ilə bir dəfə yaradın

**Trade-off-lar:**
- Struct tag-ları ilə sıx coupling — struct dəyişsə validation da dəyişir (bu çox vaxt istənilir)
- Xəta mesajları ingilis dilindədir — i18n üçün custom error handling lazımdır
- Nested struct validation üçün `dive` tag lazımdır

## Nümunələr

### Nümunə 1: Əsas istifadə

```go
package main

import (
    "fmt"

    "github.com/go-playground/validator/v10"
)

// go get github.com/go-playground/validator/v10

var validate = validator.New()

type RegisterRequest struct {
    Name     string `json:"name"     validate:"required,min=2,max=100"`
    Email    string `json:"email"    validate:"required,email"`
    Password string `json:"password" validate:"required,min=8"`
    Age      int    `json:"age"      validate:"gte=18,lte=120"`
    Role     string `json:"role"     validate:"required,oneof=admin user guest"`
    Website  string `json:"website"  validate:"omitempty,url"` // optional, amma varsa valid URL
}

func main() {
    req := RegisterRequest{
        Name:     "O",          // min=2 xətası
        Email:    "yanlış",     // email xətası
        Password: "123",        // min=8 xətası
        Age:      15,           // gte=18 xətası
        Role:     "superadmin", // oneof xətası
    }

    if err := validate.Struct(req); err != nil {
        // ValidationErrors — bütün xətaların siyahısı
        for _, e := range err.(validator.ValidationErrors) {
            fmt.Printf("Sahə: %s, Qayda: %s, Dəyər: %v\n",
                e.Field(), e.Tag(), e.Value())
        }
    }
}
```

### Nümunə 2: Strukturlu xəta cavabı — HTTP middleware

```go
package main

import (
    "encoding/json"
    "fmt"
    "net/http"
    "reflect"
    "strings"

    "github.com/go-playground/validator/v10"
)

type ValidationError struct {
    Field   string `json:"field"`
    Message string `json:"message"`
}

type ErrorResponse struct {
    Errors []ValidationError `json:"errors"`
}

// Singleton — bir dəfə yarat
var validate = validator.New()

func init() {
    // JSON tag adlarını field adı kimi istifadə et
    // Beləliklə error.Field() "Name" deyil "name" qaytarır
    validate.RegisterTagNameFunc(func(fld reflect.StructField) string {
        name := strings.SplitN(fld.Tag.Get("json"), ",", 2)[0]
        if name == "-" {
            return ""
        }
        return name
    })
}

func validateRequest(r *http.Request, dst interface{}) ([]ValidationError, error) {
    if err := json.NewDecoder(r.Body).Decode(dst); err != nil {
        return nil, err
    }

    if err := validate.Struct(dst); err != nil {
        var errs []ValidationError

        for _, e := range err.(validator.ValidationErrors) {
            errs = append(errs, ValidationError{
                Field:   e.Field(),
                Message: fieldErrorMessage(e),
            })
        }
        return errs, nil
    }
    return nil, nil
}

func fieldErrorMessage(e validator.FieldError) string {
    switch e.Tag() {
    case "required":
        return "Bu sahə tələb olunur"
    case "email":
        return "Düzgün email daxil edin"
    case "min":
        return fmt.Sprintf("Minimum %s simvol olmalıdır", e.Param())
    case "max":
        return fmt.Sprintf("Maksimum %s simvol ola bilər", e.Param())
    case "gte":
        return fmt.Sprintf("Dəyər %s və ya böyük olmalıdır", e.Param())
    case "oneof":
        return fmt.Sprintf("Dəyər bunlardan biri olmalıdır: %s", e.Param())
    default:
        return "Yanlış dəyər"
    }
}

type CreateProductRequest struct {
    Name     string  `json:"name"     validate:"required,min=2,max=200"`
    Price    float64 `json:"price"    validate:"required,gt=0"`
    Stock    int     `json:"stock"    validate:"gte=0"`
    Category string  `json:"category" validate:"required,oneof=electronics clothing food"`
}

func CreateProductHandler(w http.ResponseWriter, r *http.Request) {
    var req CreateProductRequest

    validationErrs, err := validateRequest(r, &req)
    if err != nil {
        http.Error(w, "Yanlış format", http.StatusBadRequest)
        return
    }

    if validationErrs != nil {
        w.Header().Set("Content-Type", "application/json")
        w.WriteHeader(http.StatusUnprocessableEntity)
        json.NewEncoder(w).Encode(ErrorResponse{Errors: validationErrs})
        return
    }

    // req artıq validate olunub — business logic-ə keç
    w.WriteHeader(http.StatusCreated)
}
```

### Nümunə 3: Custom validator

```go
package main

import (
    "regexp"
    "unicode"

    "github.com/go-playground/validator/v10"
)

// Azərbaycan telefon nömrəsi: +994XXXXXXXXX
var azPhoneRegex = regexp.MustCompile(`^\+994[0-9]{9}$`)

// Güclü parol: böyük hərf + kiçik hərf + rəqəm + xüsusi simvol
func strongPassword(fl validator.FieldLevel) bool {
    password := fl.Field().String()
    var hasUpper, hasLower, hasDigit, hasSpecial bool

    for _, c := range password {
        switch {
        case unicode.IsUpper(c):
            hasUpper = true
        case unicode.IsLower(c):
            hasLower = true
        case unicode.IsDigit(c):
            hasDigit = true
        case unicode.IsPunct(c) || unicode.IsSymbol(c):
            hasSpecial = true
        }
    }
    return hasUpper && hasLower && hasDigit && hasSpecial
}

func azPhone(fl validator.FieldLevel) bool {
    return azPhoneRegex.MatchString(fl.Field().String())
}

func setupValidator() *validator.Validate {
    v := validator.New()
    v.RegisterValidation("strong_password", strongPassword)
    v.RegisterValidation("az_phone", azPhone)
    return v
}

type UserRequest struct {
    Password string `json:"password" validate:"required,min=8,strong_password"`
    Phone    string `json:"phone"    validate:"required,az_phone"`
}
```

### Nümunə 4: Nested struct və slice validation

```go
package main

type Address struct {
    Street string `json:"street" validate:"required,min=5"`
    City   string `json:"city"   validate:"required"`
    Zip    string `json:"zip"    validate:"required,len=6,numeric"`
}

type OrderItem struct {
    ProductID int64   `json:"product_id" validate:"required,gt=0"`
    Quantity  int     `json:"quantity"   validate:"required,gt=0,lte=100"`
    Price     float64 `json:"price"      validate:"required,gt=0"`
}

type CreateOrderRequest struct {
    CustomerEmail string      `json:"customer_email" validate:"required,email"`
    ShipTo        Address     `json:"ship_to"        validate:"required"`
    Items         []OrderItem `json:"items"          validate:"required,min=1,dive"` // dive — hər elementi validate et
    Notes         string      `json:"notes"          validate:"max=500"`
}

// "dive" — slice içindəki hər OrderItem üçün validation işləyir
// min=1 — ən az bir element olmalıdır
```

### Nümunə 5: Cross-field validation

```go
package main

import "github.com/go-playground/validator/v10"

type DateRange struct {
    StartDate string `json:"start_date" validate:"required,datetime=2006-01-02"`
    EndDate   string `json:"end_date"   validate:"required,datetime=2006-01-02,gtfield=StartDate"`
}

// gtfield=StartDate — EndDate, StartDate-dən böyük olmalıdır

type PasswordChange struct {
    NewPassword     string `json:"new_password"     validate:"required,min=8"`
    ConfirmPassword string `json:"confirm_password" validate:"required,eqfield=NewPassword"`
}

// eqfield=NewPassword — ConfirmPassword, NewPassword-ə bərabər olmalıdır

func validateCrossField() {
    v := validator.New()

    req := PasswordChange{
        NewPassword:     "secret123",
        ConfirmPassword: "secret456", // uyğun deyil
    }

    if err := v.Struct(req); err != nil {
        // Field: ConfirmPassword, Tag: eqfield
        _ = err
    }
}
```

## Praktik Tapşırıqlar

**Tapşırıq 1:**
Qeydiyyat formu üçün validation yaz: ad (min 2, max 50), email, parol (min 8, güclü), yaş (18-65), şəhər (oneof ilə siyahı).

**Tapşırıq 2:**
HTTP handler yazın: validation xətaları 422 status ilə strukturlu JSON qaytarsın. Xəta mesajları Azərbaycan dilində olsun.

**Tapşırıq 3:**
Custom validator: IBAN formatı (AZ00AAAA000000000000000000) yoxlayan validator yaz.

## Əlaqəli Mövzular

- [18-error-handling.md](18-error-handling.md) — Xəta idarəetməsi
- [33-http-server.md](33-http-server.md) — HTTP server
- [35-middleware-and-routing.md](35-middleware-and-routing.md) — Middleware
- [20-json-encoding.md](20-json-encoding.md) — JSON decode
