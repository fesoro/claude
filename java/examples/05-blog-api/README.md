# Blog API (⭐⭐⭐ Senior)

Spring Security + JPA + Spring Cache + Pagination ilə tam blog backend. Post CRUD, comment sistemi, JWT auth, `@Cacheable` ilə post cache-ləmə.

## Öyrənilən Konseptlər

- `@OneToMany` / `@ManyToOne` — Post → Comment, User → Post
- `@Cacheable`, `@CacheEvict` — Spring Cache (Caffeine)
- `Pageable` + DTO projection
- `@PreAuthorize` — yalnız öz postunu silə bilər
- `@CreatedDate`, `@LastModifiedDate` — JPA Auditing
- `@JsonIgnoreProperties` — serialization idarəsi
- Custom `@Query` — published post-ları gətirmək

## İşə Salma

```bash
cd java/examples/05-blog-api
./mvnw spring-boot:run
# → http://localhost:8080
```

## Endpoints

| Method | Path | Auth | Təsvir |
|--------|------|------|--------|
| POST | /api/auth/register | — | Qeydiyyat |
| POST | /api/auth/login | — | Login → JWT |
| GET | /api/posts | — | Post siyahısı (pagination) |
| GET | /api/posts/{id} | — | Post detalları (cached) |
| POST | /api/posts | Bearer | Yeni post |
| PUT | /api/posts/{id} | Bearer (öz postu) | Post yenilə |
| DELETE | /api/posts/{id} | Bearer (öz postu) | Post sil |
| GET | /api/posts/{id}/comments | — | Şərhlər |
| POST | /api/posts/{id}/comments | Bearer | Şərh əlavə et |
| DELETE | /api/comments/{id} | Bearer (öz şərhi) | Şərh sil |

## İstifadə Nümunəsi

```bash
# Register & login
curl -X POST http://localhost:8080/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{"email":"ali@blog.com","password":"pass123","name":"Ali"}'

TOKEN=$(curl -s -X POST http://localhost:8080/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"ali@blog.com","password":"pass123"}' | jq -r '.token')

# Post yarat
curl -X POST http://localhost:8080/api/posts \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"title":"Spring Boot öyrənirəm","content":"Çox maraqlıdır...","published":true}'

# Post siyahısı (pagination)
curl "http://localhost:8080/api/posts?page=0&size=5"

# Şərh əlavə et
curl -X POST http://localhost:8080/api/posts/1/comments \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"content":"Əla yazı!"}'
```

## Cache İzahı

- `GET /api/posts/{id}` — birinci müraciətdə DB-dən gəlir, sonrakılar cache-dən (1 dəqiqə)
- Post update/delete olduqda cache avtomatik silinir (`@CacheEvict`)

## İrəli Getmək Üçün

- Tag sistemi (`@ManyToMany`)
- Post like/dislike sayğacı
- File upload (şəkil)
- → `06-order-events` ilə Spring Events öyrən
