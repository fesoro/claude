# Reflection — reflect paketi, struct tag parsing, dinamik invocation (Lead)

## İcmal

`reflect` paketi Go proqramına öz tip sistemi haqqında runtime-da məlumat almağa imkan verir: tipin adı, sahələri, metodları, tag-ları, dəyəri dinamik olaraq dəyişdirmək. JSON marshal/unmarshal, ORM, validation framework-lər, test utility-lər reflection-dan geniş istifadə edir.

**Amma:** reflection yavaşdır, tip təhlükəsizliyini azaldır, kodun oxunaqlılığını pisləşdirir. Bu mövzunun əsas məqsədi reflection-ı NƏ VAXT işlətmək, nə vaxt işlətməmək olduğunu öyrətməkdir.

## Niyə Vacibdir

- JSON/YAML/XML serialization-ı necə işləyir başa düşmək
- ORM-lərin (GORM, sqlx) necə struct-lardan SQL yaratdığını anlamaq
- Custom validation framework yazmaq
- Generic utility funksiyalar (deepCopy, diff, merge)
- `fmt.Sprintf`, `log.Printf` kimi variadic funksiyaların daxili işi

## Əsas Anlayışlar

### Üç əsas tip

```go
reflect.TypeOf(x)   // reflect.Type  — tipin metadata-sı
reflect.ValueOf(x)  // reflect.Value — dəyərin container-i
reflect.Kind        // tipin kateqoriyası: Struct, Slice, Map, Int, ...
```

### Type vs Kind

```go
type MyInt int

var x MyInt = 5
reflect.TypeOf(x).Name()  // "MyInt"  (tip adı)
reflect.TypeOf(x).Kind()  // reflect.Int (kateqoriya)
```

Kind — primitiv kateqoriya (Int, String, Slice, Map, Struct, Ptr, ...); tip adından asılı deyil.

### Struct tag-lar

```go
type User struct {
    Name string `json:"name" db:"user_name" validate:"required,min=2"`
}
```

Tag format: `key:"value"` cütləri. `reflect.StructField.Tag.Get("json")` — konkret key üçün dəyəri qaytarır.

### CanSet() — dəyişdirilə bilərmi?

```go
x := 42
v := reflect.ValueOf(x)
v.CanSet() // false — dəyər kopyasıdır

v2 := reflect.ValueOf(&x).Elem()
v2.CanSet() // true — pointer vasitəsilə
```

Yalnız **export olunmuş** (böyük hərflə başlayan) struct sahələri `Set` edilə bilər.

## Praktik Baxış

### Nə vaxt reflection, nə vaxt alternativ?

| Vəziyyət | Tövsiyə |
|----------|---------|
| Generic funksiya | Generics (Go 1.18+) |
| Tip yoxlaması | Type switch / interface |
| Struct tag parse | Reflection (məqbul) |
| JSON marshal | `encoding/json` (özü reflection işlədir) |
| Runtime tip yaratma | Reflection (zəruri) |
| Validation framework | Reflection (məqbul) |
| Sadə funksiya çağırışı | Birbaşa çağırış |

### Performance

```
Birbaşa çağırış:     1 ns/op
Interface çağırış:   2-3 ns/op
Reflection çağırış:  50-200 ns/op
```

Hot path-də (hər request işlənən kod) reflection işlətməyin. Əvəzinə nəticəni cache edin.

### Anti-pattern-lər

```go
// YANLIŞ: hər sorğuda struct tag parse etmək
func GetColumnName(field reflect.StructField) string {
    return field.Tag.Get("db") // parse hər dəfə işlənir
}

// DOĞRU: init zamanı bir dəfə cache et
var columnCache = sync.Map{}

func GetColumnNameCached(t reflect.Type, fieldName string) string {
    key := t.Name() + "." + fieldName
    if v, ok := columnCache.Load(key); ok {
        return v.(string)
    }
    f, _ := t.FieldByName(fieldName)
    name := f.Tag.Get("db")
    columnCache.Store(key, name)
    return name
}
```

```go
// YANLIŞ: reflect.ValueOf olmadan Set cəhdi
v := reflect.ValueOf(42)
v.SetInt(100) // panic: reflect.Value.SetInt using value obtained using unexported field

// DOĞRU: pointer və Elem()
x := 42
v := reflect.ValueOf(&x).Elem()
v.SetInt(100)
```

## Nümunələr

### Nümunə 1: Custom struct tag validator

```go
package main

import (
    "fmt"
    "reflect"
    "strconv"
    "strings"
)

// Validation xətası
type ValidationError struct {
    Field   string
    Message string
}

func (e ValidationError) Error() string {
    return fmt.Sprintf("%s: %s", e.Field, e.Message)
}

// Validate — struct-u `validate` tag-larına görə yoxlayır
func Validate(v interface{}) []ValidationError {
    rv := reflect.ValueOf(v)
    rt := reflect.TypeOf(v)

    // Pointer isə — dereference
    if rv.Kind() == reflect.Ptr {
        rv = rv.Elem()
        rt = rt.Elem()
    }

    if rv.Kind() != reflect.Struct {
        return []ValidationError{{Field: "_", Message: "struct gözlənilir"}}
    }

    var errs []ValidationError

    for i := 0; i < rt.NumField(); i++ {
        field := rt.Field(i)
        value := rv.Field(i)
        tag := field.Tag.Get("validate")

        if tag == "" || tag == "-" {
            continue
        }

        rules := strings.Split(tag, ",")
        fieldName := field.Tag.Get("json")
        if fieldName == "" {
            fieldName = field.Name
        }
        // json:"name,omitempty" → "name" al
        fieldName = strings.Split(fieldName, ",")[0]

        for _, rule := range rules {
            parts := strings.SplitN(rule, "=", 2)
            ruleName := parts[0]
            var ruleVal string
            if len(parts) == 2 {
                ruleVal = parts[1]
            }

            switch ruleName {
            case "required":
                if value.IsZero() {
                    errs = append(errs, ValidationError{Field: fieldName, Message: "tələb olunur"})
                }

            case "min":
                n, _ := strconv.Atoi(ruleVal)
                switch value.Kind() {
                case reflect.String:
                    if value.Len() < n {
                        errs = append(errs, ValidationError{
                            Field:   fieldName,
                            Message: fmt.Sprintf("minimum uzunluq %d", n),
                        })
                    }
                case reflect.Int, reflect.Int64:
                    if value.Int() < int64(n) {
                        errs = append(errs, ValidationError{
                            Field:   fieldName,
                            Message: fmt.Sprintf("minimum dəyər %d", n),
                        })
                    }
                }

            case "max":
                n, _ := strconv.Atoi(ruleVal)
                switch value.Kind() {
                case reflect.String:
                    if value.Len() > n {
                        errs = append(errs, ValidationError{
                            Field:   fieldName,
                            Message: fmt.Sprintf("maksimum uzunluq %d", n),
                        })
                    }
                case reflect.Int, reflect.Int64:
                    if value.Int() > int64(n) {
                        errs = append(errs, ValidationError{
                            Field:   fieldName,
                            Message: fmt.Sprintf("maksimum dəyər %d", n),
                        })
                    }
                }

            case "email":
                s := value.String()
                if !strings.Contains(s, "@") || !strings.Contains(s, ".") {
                    errs = append(errs, ValidationError{Field: fieldName, Message: "düzgün email deyil"})
                }
            }
        }
    }

    return errs
}

type RegisterRequest struct {
    Name     string `json:"name"     validate:"required,min=2,max=50"`
    Email    string `json:"email"    validate:"required,email"`
    Password string `json:"password" validate:"required,min=8"`
    Age      int    `json:"age"      validate:"min=18,max=120"`
}

func main() {
    req := &RegisterRequest{
        Name:     "A",          // çox qısa
        Email:    "yanlish",    // düzgün deyil
        Password: "123",        // çox qısa
        Age:      15,           // az
    }

    errs := Validate(req)
    if len(errs) > 0 {
        fmt.Println("Doğrulama xətaları:")
        for _, e := range errs {
            fmt.Printf("  - %s\n", e)
        }
    }

    valid := &RegisterRequest{
        Name:     "Orkhan",
        Email:    "orkhan@example.com",
        Password: "securepass",
        Age:      25,
    }
    if errs := Validate(valid); len(errs) == 0 {
        fmt.Println("\nDoğrulama keçdi!")
    }
}
```

### Nümunə 2: Struct tag-larından query builder

```go
package main

import (
    "fmt"
    "reflect"
    "strings"
)

type dbTag struct {
    Column string
    Skip   bool
}

func parseDBTag(tag string) dbTag {
    if tag == "" || tag == "-" {
        return dbTag{Skip: tag == "-"}
    }
    parts := strings.Split(tag, ",")
    return dbTag{Column: parts[0]}
}

// BuildInsertQuery — struct-dan INSERT query + args yaradır
func BuildInsertQuery(tableName string, v interface{}) (string, []interface{}) {
    rv := reflect.ValueOf(v)
    rt := reflect.TypeOf(v)

    if rv.Kind() == reflect.Ptr {
        rv = rv.Elem()
        rt = rt.Elem()
    }

    var columns []string
    var placeholders []string
    var args []interface{}
    argIdx := 1

    for i := 0; i < rt.NumField(); i++ {
        field := rt.Field(i)
        value := rv.Field(i)
        tag := parseDBTag(field.Tag.Get("db"))

        if tag.Skip || !field.IsExported() {
            continue
        }

        col := tag.Column
        if col == "" {
            col = strings.ToLower(field.Name)
        }

        columns = append(columns, col)
        placeholders = append(placeholders, fmt.Sprintf("$%d", argIdx))
        args = append(args, value.Interface())
        argIdx++
    }

    query := fmt.Sprintf(
        "INSERT INTO %s (%s) VALUES (%s)",
        tableName,
        strings.Join(columns, ", "),
        strings.Join(placeholders, ", "),
    )

    return query, args
}

type Order struct {
    ID        int     `db:"id"`
    UserID    int     `db:"user_id"`
    Total     float64 `db:"total_amount"`
    Status    string  `db:"status"`
    InternalNote string `db:"-"` // skip
}

func main() {
    order := Order{
        ID:           101,
        UserID:       42,
        Total:        299.99,
        Status:       "pending",
        InternalNote: "bu saxlanmır",
    }

    query, args := BuildInsertQuery("orders", order)
    fmt.Println("Query:", query)
    fmt.Println("Args:", args)
}
```

### Nümunə 3: Deep copy utility

```go
package main

import (
    "fmt"
    "reflect"
)

// DeepCopy — istənilən tipi dərin surətini çıxarır
func DeepCopy[T any](v T) T {
    original := reflect.ValueOf(v)
    copy := deepCopyValue(original)
    return copy.Interface().(T)
}

func deepCopyValue(v reflect.Value) reflect.Value {
    switch v.Kind() {
    case reflect.Ptr:
        if v.IsNil() {
            return reflect.Zero(v.Type())
        }
        newPtr := reflect.New(v.Type().Elem())
        newPtr.Elem().Set(deepCopyValue(v.Elem()))
        return newPtr

    case reflect.Struct:
        newStruct := reflect.New(v.Type()).Elem()
        for i := 0; i < v.NumField(); i++ {
            if v.Type().Field(i).IsExported() {
                newStruct.Field(i).Set(deepCopyValue(v.Field(i)))
            }
        }
        return newStruct

    case reflect.Slice:
        if v.IsNil() {
            return reflect.Zero(v.Type())
        }
        newSlice := reflect.MakeSlice(v.Type(), v.Len(), v.Cap())
        for i := 0; i < v.Len(); i++ {
            newSlice.Index(i).Set(deepCopyValue(v.Index(i)))
        }
        return newSlice

    case reflect.Map:
        if v.IsNil() {
            return reflect.Zero(v.Type())
        }
        newMap := reflect.MakeMap(v.Type())
        for _, key := range v.MapKeys() {
            newMap.SetMapIndex(deepCopyValue(key), deepCopyValue(v.MapIndex(key)))
        }
        return newMap

    default:
        // Primitiv tip-lər (int, string, bool, ...) — birbaşa kopyala
        newVal := reflect.New(v.Type()).Elem()
        newVal.Set(v)
        return newVal
    }
}

type Address struct {
    City    string
    Country string
}

type Person struct {
    Name    string
    Age     int
    Address *Address
    Tags    []string
}

func main() {
    original := Person{
        Name:    "Orkhan",
        Age:     25,
        Address: &Address{City: "Bakı", Country: "AZ"},
        Tags:    []string{"go", "php"},
    }

    copied := DeepCopy(original)

    // Orijinalı dəyişdirmək kopyaya təsir etmir
    copied.Name = "Əli"
    copied.Address.City = "Sumqayıt"
    copied.Tags[0] = "python"

    fmt.Println("Original:", original.Name, original.Address.City, original.Tags)
    fmt.Println("Copy:", copied.Name, copied.Address.City, copied.Tags)
}
```

### Nümunə 4: Dinamik metod çağırışı ilə plugin sistemi

```go
package main

import (
    "fmt"
    "reflect"
)

type Plugin interface{}

// InvokeMethod — struct metodunu adı ilə çağırır
func InvokeMethod(obj interface{}, methodName string, args ...interface{}) ([]interface{}, error) {
    v := reflect.ValueOf(obj)
    method := v.MethodByName(methodName)

    if !method.IsValid() {
        return nil, fmt.Errorf("metod tapılmadı: %s", methodName)
    }

    in := make([]reflect.Value, len(args))
    for i, arg := range args {
        in[i] = reflect.ValueOf(arg)
    }

    results := method.Call(in)
    out := make([]interface{}, len(results))
    for i, r := range results {
        out[i] = r.Interface()
    }
    return out, nil
}

type Calculator struct{}

func (c *Calculator) Add(a, b int) int       { return a + b }
func (c *Calculator) Multiply(a, b int) int  { return a * b }
func (c *Calculator) Greet(name string) string { return "Salam, " + name }

func main() {
    calc := &Calculator{}

    // Metod adını runtime-da müəyyənləşdir
    methods := []struct {
        name string
        args []interface{}
    }{
        {"Add", []interface{}{3, 4}},
        {"Multiply", []interface{}{5, 6}},
        {"Greet", []interface{}{"Dünya"}},
    }

    for _, m := range methods {
        results, err := InvokeMethod(calc, m.name, m.args...)
        if err != nil {
            fmt.Println("Xəta:", err)
            continue
        }
        fmt.Printf("%s(%v) = %v\n", m.name, m.args, results)
    }

    // Mövcud olmayan metod
    _, err := InvokeMethod(calc, "Divide", 10, 2)
    fmt.Println("Gözlənilən xəta:", err)
}
```

## Praktik Tapşırıqlar

**Tapşırıq 1 — Struct diffing:**
İki struct-ı müqayisə edən `Diff(a, b interface{}) []string` funksiyası yazın. Dəyişmiş sahələrin adı və əvvəlki/yeni dəyərini qaytarsın.

**Tapşırıq 2 — Form parser:**
`url.Values` (HTTP form məlumatı) ilə struct-a doldurma: `form:"name"` tag-larını oxuyub, tip çevirməsini özü etsin. `string` → `int`, `bool` çevirmələri.

**Tapşırıq 3 — JSON tag extractor:**
Struct-dan bütün `json` tag-larını oxuyub, `map[string]string{fieldName: jsonTag}` qaytaran funksiya yazın. Embedded struct-ları da işləsin.

**Tapşırıq 4 — Benchmark:**
Reflection ilə struct field oxuma vs birbaşa field access arasında benchmark yazın. Nə qədər yavaşdır? Cache etmək nə qədər kömək edir?

## PHP ilə Müqayisə

PHP-dəki `ReflectionClass`, `ReflectionMethod` ilə müqayisə edilə bilər — hər ikisi runtime-da tip məlumatını oxuyur, struct/class sahələrini dinamik idarə edir. Lakin Go-da compile-time tip sistemi var, buna görə reflection istifadəsi daha məhdud olmalıdır. PHP-dəki `ReflectionProperty::setValue()` Go-da `reflect.Value.Set()` ilə uyğundur, amma Go pointer vasitəsilə işləyir. Ən əsas fərq: Go-da generics (1.18+) reflection-ın bir çox istifadə halını replace edir — PHP-də bu imkan yoxdur.

## Əlaqəli Mövzular

- [10-structs](10-structs.md) — struct əsasları
- [17-interfaces](17-interfaces.md) — interface sistemi
- [20-json-encoding](20-json-encoding.md) — JSON marshal/unmarshal (reflection işlədir)
- [29-generics](29-generics.md) — reflection alternativ kimi generics
- [59-design-patterns](59-design-patterns.md) — framework-lərdə reflection istifadəsi
