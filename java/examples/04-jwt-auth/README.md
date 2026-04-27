# JWT Auth Service (⭐⭐⭐ Senior)

Spring Security 6 + JWT ilə tam authentication sistemi. Register, login, token refresh, qorunmuş endpoint-lər. Laravel Sanctum/Passport-un Java qarşılığı.

## Öyrənilən Konseptlər

- Spring Security 6 filter chain konfiqurasiyası
- JWT yaratma, imzalama, yoxlama (`io.jsonwebtoken`)
- `UserDetailsService` — DB-dən user yükləmə
- `JwtAuthFilter` — hər requestdə token yoxlama
- `BCryptPasswordEncoder` — şifrə hashing
- Stateless session (JWT-nin əsas məntiqı)
- `SecurityContext` — cari user-i almaq

## İşə Salma

```bash
cd java/examples/04-jwt-auth
./mvnw spring-boot:run
# → http://localhost:8080
```

## Endpoints

| Method | Path | Auth | Təsvir |
|--------|------|------|--------|
| POST | /api/auth/register | — | Yeni hesab aç |
| POST | /api/auth/login | — | Token al |
| GET | /api/users/me | Bearer token | Cari user |
| GET | /api/users | Bearer token (ADMIN) | Bütün userlər |

## İstifadə Nümunəsi

```bash
# Register
curl -X POST http://localhost:8080/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{"email":"ali@example.com","password":"secret123","name":"Ali"}'

# Login → token al
TOKEN=$(curl -s -X POST http://localhost:8080/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"ali@example.com","password":"secret123"}' | jq -r '.token')

# Qorunmuş endpoint-ə müraciət
curl http://localhost:8080/api/users/me \
  -H "Authorization: Bearer $TOKEN"

# Tokensiz müraciət → 401
curl http://localhost:8080/api/users/me
```

## JWT Necə İşləyir

```
1. Login: email + password → server yoxlayır → JWT imzalayır → client-ə göndərir
2. Request: client hər requestdə "Authorization: Bearer <token>" header əlavə edir
3. Filter: JwtAuthFilter hər requestdə tokeni yoxlayır → SecurityContext-ə user yükləyir
4. Controller: @AuthenticationPrincipal ilə cari user-ə çatır
```

## PHP/Laravel ilə Müqayisə

| Laravel | Spring Security |
|---------|-----------------|
| `auth()->user()` | `SecurityContextHolder.getContext().getAuthentication()` |
| `Auth::guard('sanctum')` | `SecurityFilterChain` konfigurasyonu |
| `$request->user()` | `@AuthenticationPrincipal UserDetails user` |
| `auth()->check()` | `authentication.isAuthenticated()` |

## İrəli Getmək Üçün

- Refresh token əlavə et
- Role-based access (`@PreAuthorize("hasRole('ADMIN')")`)
- Token blacklist (logout üçün Redis)
- → `05-blog-api`-yə bax (bu pattern əsas götürülür)
