# Todo API (⭐⭐ Middle)

Spring Boot + JPA + H2 ilə tam CRUD API. Validation, global exception handling, service layer pattern. Laravel-dən Java-ya keçən developer-lər üçün əsas referans layihə.

## Öyrənilən Konseptlər

- `@Entity`, `@Id`, `@GeneratedValue` — JPA entity
- `JpaRepository` — CRUD repository
- Service layer — business logic ayrılığı
- `@Valid` + Bean Validation — request validation
- `@ControllerAdvice` — global exception handler
- H2 in-memory database + console
- `@Enumerated` — enum entity field-i

## İşə Salma

```bash
cd java/examples/02-todo-api
./mvnw spring-boot:run
# → http://localhost:8080
# → H2 Console: http://localhost:8080/h2-console
#   JDBC URL: jdbc:h2:mem:tododb
```

## Endpoints

| Method | Path | Təsvir |
|--------|------|--------|
| GET | /api/todos | Bütün todo-lar (status filter-i ilə) |
| POST | /api/todos | Yeni todo yarat |
| GET | /api/todos/{id} | ID ilə gətir |
| PUT | /api/todos/{id} | Yenilə |
| PATCH | /api/todos/{id}/complete | Tamamlandı olaraq işarələ |
| DELETE | /api/todos/{id} | Sil |

## İstifadə Nümunəsi

```bash
# Yeni todo
curl -X POST http://localhost:8080/api/todos \
  -H "Content-Type: application/json" \
  -d '{"title":"Spring Boot öyrən","priority":"HIGH"}'

# Bütün todo-lar
curl http://localhost:8080/api/todos

# Status ilə filtr
curl "http://localhost:8080/api/todos?status=PENDING"

# Tamamla
curl -X PATCH http://localhost:8080/api/todos/1/complete

# Yenilə
curl -X PUT http://localhost:8080/api/todos/1 \
  -H "Content-Type: application/json" \
  -d '{"title":"Yenilənmiş başlıq","priority":"LOW"}'

# Sil
curl -X DELETE http://localhost:8080/api/todos/1
```

## PHP/Laravel ilə Müqayisə

| Laravel | Spring Boot |
|---------|-------------|
| `protected $fillable` | `@Column` field-lər |
| `Model::find($id)` | `repository.findById(id)` |
| `$model->save()` | `repository.save(entity)` |
| `App\Exceptions\Handler` | `@ControllerAdvice` |
| `php artisan migrate` | `ddl-auto: create-drop` (dev-də) |

## İrəli Getmək Üçün

- PostgreSQL-ə keç: `datasource.url` dəyiş
- Flyway migration əlavə et → `03-book-store`-a bax
- Pagination əlavə et: `Pageable` istifadə et
