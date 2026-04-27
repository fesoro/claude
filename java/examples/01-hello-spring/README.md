# Hello Spring (⭐ Junior)

Spring Boot-un ilk REST API-si. Database yoxdur — yalnız in-memory map. Spring Boot-un necə işlədiyini başa düşmək üçün başlanğıc nöqtə.

## Öyrənilən Konseptlər

- `@SpringBootApplication` — auto-configuration, component scan
- `@RestController`, `@RequestMapping` — REST endpoint-lər
- `@GetMapping`, `@PostMapping`, `@DeleteMapping`
- `@PathVariable`, `@RequestParam`, `@RequestBody`
- Java Records DTO kimi
- `ResponseEntity<T>` — HTTP status + body
- `@Valid` + `@NotBlank` validation

## İşə Salma

```bash
cd java/examples/01-hello-spring
./mvnw spring-boot:run
# → http://localhost:8080
```

## Endpoints

| Method | Path | Təsvir |
|--------|------|--------|
| GET | /api/hello | Salam mesajı |
| GET | /api/hello?name=Ali | Adlı salam |
| GET | /api/messages | Bütün mesajlar |
| POST | /api/messages | Yeni mesaj əlavə et |
| GET | /api/messages/{id} | ID ilə mesaj |
| DELETE | /api/messages/{id} | Mesajı sil |

## İstifadə Nümunəsi

```bash
# Sadə salam
curl http://localhost:8080/api/hello?name=Orkhan

# Mesaj yarat
curl -X POST http://localhost:8080/api/messages \
  -H "Content-Type: application/json" \
  -d '{"text":"Spring Boot öyrənirəm"}'

# Bütün mesajlar
curl http://localhost:8080/api/messages

# ID ilə gətir
curl http://localhost:8080/api/messages/1

# Sil
curl -X DELETE http://localhost:8080/api/messages/1
```

## PHP/Laravel ilə Müqayisə

| Laravel | Spring Boot |
|---------|-------------|
| `Route::get('/hello', ...)` | `@GetMapping("/hello")` |
| `Request $request` | `@RequestParam String name` |
| `return response()->json(...)` | `return ResponseEntity.ok(...)` |
| `app()->bind(...)` | `@Component`, `@Service` |

## İrəli Getmək Üçün

- H2 database əlavə et → `02-todo-api`
- Validation genişləndir → `@Min`, `@Email`, custom validator
- Exception handling → `@ControllerAdvice`
