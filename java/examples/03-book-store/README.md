# Book Store API (⭐⭐ Middle)

Entity relationship-ləri, pagination, search/filter və Flyway migration ilə Spring Boot API. `@ManyToOne` / `@OneToMany` əlaqəliyini real ssenaridə göstərir.

## Öyrənilən Konseptlər

- `@ManyToOne`, `@OneToMany` — entity əlaqələri
- `Pageable`, `Page<T>` — pagination
- `@Query` JPQL — custom sorğular
- `@JsonIgnoreProperties` — serialization idarəsi
- Flyway — versioned DB migration
- DTO projection — entity-ni birbaşa return etmə əvəzinə
- `@RestControllerAdvice` — global exception handler

## İşə Salma

```bash
cd java/examples/03-book-store
./mvnw spring-boot:run
# → http://localhost:8080
# → H2 Console: http://localhost:8080/h2-console
```

## Endpoints

| Method | Path | Təsvir |
|--------|------|--------|
| GET | /api/authors | Bütün müəlliflər |
| POST | /api/authors | Yeni müəllif |
| GET | /api/books | Kitablar (pagination + search) |
| POST | /api/books | Yeni kitab |
| GET | /api/books/{id} | Kitab detalları |
| GET | /api/books/author/{authorId} | Müəllifin kitabları |
| DELETE | /api/books/{id} | Kitabı sil |

## İstifadə Nümunəsi

```bash
# Müəllif yarat
curl -X POST http://localhost:8080/api/authors \
  -H "Content-Type: application/json" \
  -d '{"name":"Robert C. Martin","bio":"Software engineer and author"}'

# Kitab yarat (authorId daxil et)
curl -X POST http://localhost:8080/api/books \
  -H "Content-Type: application/json" \
  -d '{"title":"Clean Code","isbn":"978-0132350884","year":2008,"authorId":1}'

# Pagination ilə kitablar
curl "http://localhost:8080/api/books?page=0&size=5&sort=title"

# Başlıq üzrə axtarış
curl "http://localhost:8080/api/books?search=clean"

# Müəllifin kitabları
curl http://localhost:8080/api/books/author/1
```

## Flyway Migration

`src/main/resources/db/migration/V1__init.sql` faylı application start olduqda avtomatik işləyir.

## İrəli Getmək Üçün

- `@ManyToMany` — kitab + kateqoriya
- Specification API ilə çox parametrli filter
- Spring Cache ilə author list-ini cache et
