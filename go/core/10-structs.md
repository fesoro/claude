# Struct-lar (Junior)

## İcmal

Go-dakı `struct` — müxtəlif tipli məlumatları bir araya gətirən tip sistemidir. Struct-lar Go-da OOP-nin əsasını təşkil edir: methodlar struct-lara bağlanır, embedding ilə komponent-bazalı kod yazmaq mümkündür. Go-nun fəlsəfəsi: composition over inheritance.

## Niyə Vacibdir

Verilənlər bazası model-ləri, API request/response tipləri, domain entity-lər — hamısı struct ilə modelləşdirilir. JSON serialization üçün struct tag-ları (`json:"..."`) API-lər yaratmaq üçün vacibdir. Constructor function pattern-i təmiz struct initialization təmin edir.

## Əsas Anlayışlar

- **Struct elanı** — `type MyStruct struct { Field Tip }` — həmişə paket səviyyəsindədir
- **Sahə adları** — Böyük hərflə başlayan public (export olunur), kiçik hərflə private
- **Metodlar** — `func (s MyStruct) Method()` — value receiver; kopya üzərindədir
- **Pointer receiver** — `func (s *MyStruct) Method()` — orijinalı dəyişdirir; hər zaman bu tövsiyə olunur
- **Constructor function** — `func NewUser(...) User` — struct-u yaratmaq üçün standart pattern
- **Embedded struct** — `type Employee struct { Person; Role string }` — composition
- **Anonim field** — `type Employee struct { Person }` — Person-un metodları birbaşa Employee-dən çağırılır
- **Struct tags** — `` `json:"name"` `` — JSON, YAML, DB etiketləri üçün; reflect ilə oxunur
- **`new(T)`** — `*T` pointer qaytarır; `&T{}` daha çox istifadə olunur

## Praktik Baxış

**Real layihədə istifadə:**
- DB model — `type User struct { ID int64; Name string; Email string }`
- API DTO — `type CreateUserRequest struct { Name string `json:"name" validate:"required"` }`
- Service layer — `type UserService struct { db *sql.DB; cache Cache }`
- Constructor pattern — `func NewUserService(db *sql.DB) *UserService`

**Trade-off-lar:**
- Value receiver vs pointer receiver: böyük struct-lar üçün həmişə pointer receiver istifadə edin
- Embedding — güclüdür, amma interface collision ola bilər; sahə adları üst-üstə düşsə, açıq yol lazımdır
- Struct tag-lar runtime reflection istifadə edir — performance overhead var, amma normal şərtlərdə əhəmiyyətsizdir

**Common mistakes:**
- Value receiver ilə struct-u dəyişdirməyə cəhd — kopya üzərindədir, orijinal dəyişmir
- Export edilməmiş sahəni JSON-a serializasiya etməyə cəhd — kiçik hərflə başlayan sahələr `json:"-"` kimi davranır
- Nil pointer struct metodunu çağırmaq — `var u *User; u.Name` → panic

## Nümunələr

### Nümunə 1: Struct elanı, yaratma, metodlar

```go
package main

import "fmt"

type User struct {
    ID    int64
    Name  string
    Email string
    aktiv bool // kiçik hərfli — paket xaricindən görünmür
}

// Constructor — struct-u düzgün initialize etmək üçün
func NewUser(id int64, name, email string) *User {
    return &User{
        ID:    id,
        Name:  name,
        Email: email,
        aktiv: true,
    }
}

// Value receiver — struct-u dəyişdirmir
func (u User) FullInfo() string {
    return fmt.Sprintf("ID:%d Name:%s Email:%s", u.ID, u.Name, u.Email)
}

// Pointer receiver — struct-u dəyişdirir; bu tövsiyə olunur
func (u *User) Deactivate() {
    u.aktiv = false
}

func (u *User) IsActive() bool {
    return u.aktiv
}

func main() {
    // Sahə adları ilə yaratma (tövsiyə olunan)
    u1 := User{
        ID:    1,
        Name:  "Orkhan",
        Email: "orkhan@example.com",
    }
    fmt.Println("User:", u1.FullInfo())

    // Constructor ilə
    u2 := NewUser(2, "Eli", "eli@example.com")
    fmt.Println("Active:", u2.IsActive())
    u2.Deactivate()
    fmt.Println("Active:", u2.IsActive())

    // Sahə dəyişikliyi
    u1.Email = "yeni@example.com"
    fmt.Println("Yeni email:", u1.Email)

    // Struct müqayisəsi (bütün sahələr müqayisəli olmalı)
    a := User{ID: 1, Name: "Test"}
    b := User{ID: 1, Name: "Test"}
    fmt.Println("Bərabərdir:", a == b) // true
}
```

### Nümunə 2: Embedding — composition

```go
package main

import "fmt"

type Person struct {
    Name string
    Age  int
}

func (p Person) Greet() string {
    return fmt.Sprintf("Salam, mənim adım %s, %d yaşım var", p.Name, p.Age)
}

// Employee-ə Person embed edir
type Employee struct {
    Person         // anonim embedding — Person-un sahəlləri birbaşa çıxır
    Department string
    Salary    float64
}

type Address struct {
    City   string
    Street string
}

// İstifadəçi daha mürəkkəb komponent
type FullUser struct {
    Person
    Address Address // adlı embedding — Address ilə açıq giriş
    Role    string
}

func main() {
    emp := Employee{
        Person:     Person{Name: "Veli", Age: 30},
        Department: "Backend",
        Salary:     5000,
    }

    // Person-un sahəllərinə birbaşa giriş
    fmt.Println("Ad:", emp.Name)       // emp.Person.Name ilə eyni
    fmt.Println("Yaş:", emp.Age)
    fmt.Println("Departman:", emp.Department)

    // Person-un metodlarına da birbaşa giriş
    fmt.Println(emp.Greet())

    // Adlı struct üçün açıq yol lazımdır
    u := FullUser{
        Person:  Person{Name: "Günel", Age: 25},
        Address: Address{City: "Bakı", Street: "Nizami 10"},
        Role:    "Editor",
    }
    fmt.Println(u.Name)          // Person.Name — birbaşa
    fmt.Println(u.Address.City)  // Address.City — açıq yol
}
```

### Nümunə 3: Struct tags — JSON serialization

```go
package main

import (
    "encoding/json"
    "fmt"
)

type Product struct {
    ID       int64   `json:"id"`
    Name     string  `json:"name"`
    Price    float64 `json:"price"`
    Stock    int     `json:"stock,omitempty"` // 0-sa JSON-a yazılmaz
    Internal string  `json:"-"`              // heç vaxt JSON-a yazılmaz
}

// Anonim struct — birdəfəlik istifadə
type Config struct {
    Host string
    Port int
}

func main() {
    p := Product{
        ID:       1,
        Name:     "Laptop",
        Price:    1299.99,
        Stock:    5,
        Internal: "internal_data",
    }

    // Struct → JSON
    data, _ := json.Marshal(p)
    fmt.Println(string(data))
    // {"id":1,"name":"Laptop","price":1299.99,"stock":5}

    // JSON → Struct
    jsonStr := `{"id":2,"name":"Telefon","price":699.99}`
    var p2 Product
    json.Unmarshal([]byte(jsonStr), &p2)
    fmt.Printf("ID: %d, Name: %s, Price: %.2f\n", p2.ID, p2.Name, p2.Price)

    // Anonim struct
    cfg := struct {
        Host string
        Port int
    }{
        Host: "localhost",
        Port: 8080,
    }
    fmt.Printf("Config: %s:%d\n", cfg.Host, cfg.Port)
}
```

## Praktik Tapşırıqlar

1. **E-ticarət model**: `Product` struct yarat: `ID`, `Name`, `Price`, `Category`, `Stock`. Constructor, `IsAvailable() bool` (stok > 0), `ApplyDiscount(percent float64)` pointer receiver metodları yaz. JSON tag-larını əlavə et.

2. **Embedded service**: `BaseService` struct yarat: `logger *Logger`, `db *DB`. `UserService` və `ProductService`-ə embed et. Hər iki service-in `Init()` metodunu çağırınca `BaseService`-in metodları işləsin.

3. **Builder pattern**: `QueryBuilder` struct yarat: `table`, `conditions []string`, `limit int`. `Where(cond string) *QueryBuilder`, `Limit(n int) *QueryBuilder`, `Build() string` metodları yaz. Method chaining-i dəstəkləsin.

4. **PHP-dən Go-ya çevir**:
   ```php
   class User {
       public function __construct(
           public readonly int $id,
           public string $name,
           public string $email
       ) {}
       public function getFullName(): string { return $this->name; }
   }
   ```

## PHP ilə Müqayisə

- PHP `class User { public string $name; public function __construct(...) }` → Go `type User struct { Name string }` + `func NewUser(...) User`
- PHP inheritance (`extends`) yoxdur; Go-da embedding (composition) var — "composition over inheritance"
- PHP `$user->name` → Go `user.Name` (böyük hərflə, çünkü public)
- PHP magic methods yoxdur; Go-da `String()` metodu `fmt.Stringer` interface-ini implement edir
- Go struct-lar müqayisə edilə bilər (`==`), əgər bütün sahələri müqayisəli tipdirsə
- PHP `__construct` → Go constructor function (`func NewUser(...)`) — built-in deyil, konvensiya
- PHP-də bütün object-lər reference kimi ötürülür; Go-da struct default value-dir, pointer receiver lazımdır

## Əlaqəli Mövzular

- [11-pointers.md](11-pointers.md) — pointer receiver üçün pointer-lər
- [07-functions.md](07-functions.md) — funksiyalar
- [17-interfaces.md](17-interfaces.md) — interfeyslər
- [20-json-encoding.md](20-json-encoding.md) — JSON işləmə
- [35-struct-advanced.md](35-struct-advanced.md) — struct dərinləşmə
