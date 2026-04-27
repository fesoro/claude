# OpenAPI / Swagger Sənədləşməsi (Senior)

## İcmal

OpenAPI Specification (OAS) — RESTful API-lərin maşın oxunaqlı formatda təsvir edilməsi üçün standartdır (YAML/JSON). Swagger UI — bu spesifikasiyadan interaktiv sənəd yaradır.

Go-da iki əsas yanaşma var:
1. **swaggo/swag** — kod comment-lərindən swagger.json generasiya edir
2. **kin-openapi / oapi-codegen** — OpenAPI spec-dən Go kodu generasiya edir (spec-first)

Ən geniş yayılmış: **swaggo/swag** (comment-first).

## Niyə Vacibdir

- API sənədləşməsi olmadan frontend, mobil, üçüncü tərəf inteqrasiyası çətindir
- Swagger UI — developer-lərin API-ni birbaşa brauzerdə sınaqdan keçirməsinə imkan verir
- OpenAPI spec — client SDK generasiyası üçün istifadə edilir (TypeScript, Python, Java)
- Contract testing — spec + real API arasındakı uyğunluğu yoxlamaq

## Əsas Anlayışlar

### swag annotation formatı

```go
// @Summary      Qısa başlıq
// @Description  Ətraflı izah
// @Tags         users
// @Accept       json
// @Produce      json
// @Param        id   path      int           true  "User ID"
// @Param        body body      CreateUserReq true  "Request body"
// @Success      200  {object}  UserResponse
// @Failure      400  {object}  ErrorResponse
// @Failure      404  {object}  ErrorResponse
// @Router       /users/{id} [get]
func (h *UserHandler) GetUser(w http.ResponseWriter, r *http.Request) {
```

### main.go — global annotation

```go
// @title          My API
// @version        1.0
// @description    Backend API sənədləşməsi
// @host           localhost:8080
// @BasePath       /api/v1
// @securityDefinitions.apikey  BearerAuth
// @in                          header
// @name                        Authorization
// @description                 Format: "Bearer <token>"
func main() {
```

### Model annotation

```go
type CreateUserReq struct {
    Name     string `json:"name"     example:"Aydın"      validate:"required,min=2"`
    Email    string `json:"email"    example:"user@ex.com" validate:"required,email"`
    Password string `json:"password" example:"secret123"   validate:"required,min=8"`
} // @name CreateUserReq

type UserResponse struct {
    ID        int       `json:"id"         example:"42"`
    Name      string    `json:"name"       example:"Aydın"`
    Email     string    `json:"email"      example:"user@ex.com"`
    CreatedAt time.Time `json:"created_at" example:"2024-01-15T10:00:00Z"`
} // @name UserResponse

type ErrorResponse struct {
    Error   string `json:"error"   example:"record not found"`
    Code    int    `json:"code"    example:"404"`
} // @name ErrorResponse
```

## Praktik Baxış

### Quraşdırma

```bash
# swag CLI qur
go install github.com/swaggo/swag/cmd/swag@latest

# Swagger handler (gin üçün):
go get github.com/swaggo/gin-swagger
go get github.com/swaggo/files

# Swagger handler (standart net/http üçün):
go get github.com/swaggo/http-swagger
```

### Swagger generasiya

```bash
# docs/ folderini yaradır (docs.go, swagger.json, swagger.yaml)
swag init -g cmd/api/main.go -o docs/

# ya da:
swag init --parseDependency --parseInternal
```

### net/http ilə inteqrasiya

```go
import (
    _ "myapp/docs"  // generated docs — init() çağırılır
    httpSwagger "github.com/swaggo/http-swagger"
)

mux := http.NewServeMux()

// Swagger UI: http://localhost:8080/swagger/
mux.Handle("/swagger/", httpSwagger.WrapHandler)

// API routes
mux.HandleFunc("GET /api/v1/users/{id}", h.GetUser)
```

### Gin ilə inteqrasiya

```go
import (
    _ "myapp/docs"
    ginSwagger "github.com/swaggo/gin-swagger"
    swaggerFiles "github.com/swaggo/files"
)

r := gin.Default()
r.GET("/swagger/*any", ginSwagger.WrapHandler(swaggerFiles.Handler))
```

### Security annotation

```go
// @Security BearerAuth

// Handler-də security tələb etmək:
// @Summary  Profili al
// @Security BearerAuth
// @Success  200 {object} UserResponse
// @Failure  401 {object} ErrorResponse
// @Router   /profile [get]
func (h *UserHandler) GetProfile(w http.ResponseWriter, r *http.Request) {
```

### Pagination response

```go
type PaginatedResponse[T any] struct {
    Data       []T   `json:"data"`
    Total      int64 `json:"total"       example:"150"`
    Page       int   `json:"page"        example:"1"`
    PerPage    int   `json:"per_page"    example:"20"`
    TotalPages int   `json:"total_pages" example:"8"`
}

// Generics swag tərəfindən tam dəstəklənmir,
// hər model üçün concrete type yaz:
type UserListResponse = PaginatedResponse[UserResponse]
```

### Query parameter annotation

```go
// @Param page     query int    false "Səhifə nömrəsi"      default(1)    minimum(1)
// @Param per_page query int    false "Səhifə ölçüsü"       default(20)   maximum(100)
// @Param search   query string false "Axtarış sözü"
// @Param sort     query string false "Sıralama sahəsi"     Enums(name, email, created_at)
// @Param order    query string false "Sıralama istiqaməti" Enums(asc, desc)
```

### Enum / oneof

```go
type OrderStatus string

const (
    OrderPending   OrderStatus = "pending"
    OrderShipped   OrderStatus = "shipped"
    OrderDelivered OrderStatus = "delivered"
)

// swag enum annotation:
// @Param status query string false "Status" Enums(pending, shipped, delivered)
```

### File upload

```go
// @Accept       multipart/form-data
// @Param        file formData file true "Yüklənəcək fayl"
// @Param        type formData string true "Fayl tipi" Enums(avatar, document)
```

### CI/CD inteqrasiyası

```yaml
# .github/workflows/docs.yml
- name: Check swagger docs are up-to-date
  run: |
    swag init -g cmd/api/main.go -o docs/
    git diff --exit-code docs/
    # Fərq varsa → PR-da docs yenilənməyib → pipeline fail
```

### oapi-codegen (spec-first alternativ)

```bash
# OpenAPI YAML-dan Go server interface + types generasiya et
go install github.com/oapi-codegen/oapi-codegen/v2/cmd/oapi-codegen@latest
oapi-codegen -generate types,server -package api openapi.yaml > api/api.gen.go
```

```
Spec-first üstünlükləri:
✓ API dizayn kodu yazmadan əvvəl razılaşılır
✓ Frontend komandası spec-dən client generasiya edə bilər
✓ Contract testing asan

Code-first (swag) üstünlükləri:
✓ Mövcud kod dəyişdirilmədən annotation əlavə olunur
✓ Öyrənmə əyrisi azdır
✓ Spec-i əl ilə yazıb saxlamaq lazım deyil
```

## Trade-off-lar

| | swag (code-first) | oapi-codegen (spec-first) |
|--|-------------------|---------------------------|
| Başlanğıc | Asan | Orta |
| API dizayn prosesi | Kod yazmaqla | Spec-dən |
| Generics | Məhdud | Tam |
| Type safety | Comment-based | Compile-time |
| Böyük komanda | Spec drift riski | Spec canonical mənbədir |

## Praktik Tapşırıqlar

1. **CRUD API:** User CRUD üçün swag annotation-lar yaz, Swagger UI-da test et
2. **Auth:** BearerAuth security scheme əlavə et, bütün protected route-lara `@Security` yaz
3. **CI yoxlama:** Swagger docs-un commit edilib-edilmədiyini CI-da yoxla
4. **Client gen:** Swagger JSON-dan TypeScript client generasiya et (`openapi-typescript-codegen`)
5. **Versioning:** `/api/v1` və `/api/v2` üçün ayrı swagger file generasiya et

## PHP ilə Müqayisə

```
Laravel                          Go
────────────────────────────────────────────────────────────
l5-swagger (darkaonline)    →   swaggo/swag
Form Request validation     →   validator annotation + comment
php artisan l5-swagger:generate → swag init
API Resource                →   Response struct + swag model
```

**Fərqlər:**
- Laravel-də OpenAPI annotation FormRequest + Resource-da da yazılır, Go-da yalnız handler comment-ləri
- Go compile-time tip yoxlaması var, swag annotation-lar isə runtime — tip uyğunsuzluğu CI-da tutulmalıdır
- oapi-codegen spec-first yanaşma laravel API spec workflow-una daha çox bənzəyir (API blueprint → code)

## Əlaqəli Mövzular

- [33-http-server.md](33-http-server.md) — net/http server əsasları
- [35-middleware-and-routing.md](35-middleware-and-routing.md) — route qurma
- [82-api-versioning.md](82-api-versioning.md) — API versiyalaşdırma
- [76-input-validation.md](76-input-validation.md) — validator ilə input yoxlama
- [94-pagination.md](94-pagination.md) — pagination response strukturu
