# 41 — Spring Data Pagination və Sorting — Geniş İzah

> **Seviyye:** Middle ⭐⭐


## Mündəricat
1. [Pageable nədir?](#pageable-nədir)
2. [Repository-də pagination](#repository-də-pagination)
3. [Controller-də pagination](#controller-də-pagination)
4. [Custom sort](#custom-sort)
5. [Slice vs Page](#slice-vs-page)
6. [İntervyu Sualları](#intervyu-sualları)

---

## Pageable nədir?

**Pageable** — Spring Data-nın səhifələmə abstraksiyas ıdır. Səhifə nömrəsi, ölçüsü və sıralama məlumatını daşıyır.

```java
// Pageable yaratmaq
Pageable pageable = PageRequest.of(
    0,          // Səhifə nömrəsi (0-dan başlayır)
    20,         // Hər səhifədəki element sayı
    Sort.by("createdAt").descending() // Sıralama
);

// Çoxlu sıralama
Pageable pageable = PageRequest.of(0, 20,
    Sort.by(
        Sort.Order.desc("createdAt"),
        Sort.Order.asc("name")
    )
);
```

---

## Repository-də pagination

```java
public interface UserRepository extends JpaRepository<User, Long> {

    // Spring Data avtomatik Pageable qəbul edir
    Page<User> findAll(Pageable pageable);

    // Filtr ilə
    Page<User> findByStatus(UserStatus status, Pageable pageable);

    // JPQL ilə
    @Query("SELECT u FROM User u WHERE u.age >= :minAge")
    Page<User> findByMinAge(@Param("minAge") int minAge, Pageable pageable);

    // countQuery — COUNT sorğusunu ayrıca optimizasiya et
    @Query(value = "SELECT u FROM User u WHERE u.department.name = :dept",
           countQuery = "SELECT COUNT(u) FROM User u WHERE u.department.name = :dept")
    Page<User> findByDepartment(@Param("dept") String department,
                                Pageable pageable);

    // Projection ilə
    @Query("SELECT new com.example.dto.UserSummary(u.id, u.name, u.email) " +
           "FROM User u WHERE u.active = true")
    Page<UserSummary> findActiveSummaries(Pageable pageable);
}
```

---

## Controller-də pagination

```java
@RestController
@RequestMapping("/api/users")
public class UserController {

    private final UserRepository userRepository;

    // URL: GET /api/users?page=0&size=20&sort=name,asc
    @GetMapping
    public ResponseEntity<Page<UserDto>> getUsers(
            @PageableDefault(size = 20, sort = "createdAt",
                            direction = Sort.Direction.DESC)
            Pageable pageable) {

        Page<User> users = userRepository.findAll(pageable);
        Page<UserDto> dtos = users.map(userMapper::toDto);
        return ResponseEntity.ok(dtos);
    }

    // Filtr ilə
    @GetMapping("/search")
    public ResponseEntity<Page<UserDto>> searchUsers(
            @RequestParam(required = false) String status,
            @PageableDefault(size = 10) Pageable pageable) {

        Page<User> users = status != null
            ? userRepository.findByStatus(UserStatus.valueOf(status), pageable)
            : userRepository.findAll(pageable);

        return ResponseEntity.ok(users.map(userMapper::toDto));
    }
}
```

**URL nümunələri:**
```
GET /api/users?page=0&size=10
GET /api/users?page=1&size=20&sort=name,asc
GET /api/users?page=0&size=10&sort=createdAt,desc&sort=name,asc
```

**Page cavabı:**
```json
{
  "content": [...],
  "pageable": {
    "pageNumber": 0,
    "pageSize": 20,
    "sort": { "sorted": true, "orders": [{"property": "createdAt", "direction": "DESC"}] }
  },
  "totalElements": 150,
  "totalPages": 8,
  "last": false,
  "first": true,
  "size": 20,
  "number": 0
}
```

---

## Custom sort

```java
// Sort parametrlərini validasiya etmək
@Configuration
public class WebConfig implements WebMvcConfigurer {

    @Override
    public void addArgumentResolvers(List<HandlerMethodArgumentResolver> resolvers) {
        // Sort üçün icazə verilən field-lər
        SortHandlerMethodArgumentResolver sortResolver =
            new SortHandlerMethodArgumentResolver();
        sortResolver.setPropertyDelimiter(";");

        PageableHandlerMethodArgumentResolver pageResolver =
            new PageableHandlerMethodArgumentResolver(sortResolver);
        pageResolver.setMaxPageSize(100); // Maksimum 100 element
        pageResolver.setFallbackPageable(PageRequest.of(0, 20));
        resolvers.add(pageResolver);
    }
}

// Sort güvənliyi — yalnız icazə verilən field-lərlə sort
@GetMapping
public Page<UserDto> getUsers(Pageable pageable) {

    // YANLIŞ — SQL injection riskli
    // Sort.by(pageable.getSort()) birbaşa istifadə etmə

    // DOĞRU — icazə verilən field-ləri yoxla
    Set<String> allowedSortFields = Set.of("name", "email", "createdAt");

    pageable.getSort().forEach(order -> {
        if (!allowedSortFields.contains(order.getProperty())) {
            throw new IllegalArgumentException(
                "Sort field icazəsizdir: " + order.getProperty());
        }
    });

    return userRepository.findAll(pageable).map(userMapper::toDto);
}
```

---

## Slice vs Page

```java
// Page<T> — total count da əldə edir (COUNT query əlavə edir)
Page<User> findAll(Pageable pageable);

// Slice<T> — total count YOX, növbəti səhifə varmı? (daha sürətli)
Slice<User> findByActive(boolean active, Pageable pageable);
```

```java
// Slice istifadəsi — "Load More" funksionallığı üçün ideal
@GetMapping("/feed")
public Map<String, Object> getFeed(
        @PageableDefault(size = 10) Pageable pageable) {

    Slice<Post> slice = postRepository.findByActive(true, pageable);

    Map<String, Object> response = new HashMap<>();
    response.put("content", slice.getContent());
    response.put("hasNext", slice.hasNext());
    response.put("pageNumber", slice.getNumber());
    return response;
}
```

| Xüsusiyyət | Page | Slice |
|------------|------|-------|
| Total count | ✓ (COUNT query) | ✗ |
| Performans | Aşağı (2 query) | Yüksək (1 query) |
| İstifadə | Pagination UI | Infinite scroll |
| hasNext() | ✓ | ✓ |
| getTotalElements() | ✓ | ✗ |

---

## İntervyu Sualları

### 1. Page vs Slice fərqi nədir?
**Cavab:** `Page` həm məzmunu, həm də ümumi element sayını qaytarır (2 query: data + COUNT). `Slice` yalnız məzmunu və növbəti səhifənin olub-olmadığını bilir (1 query). `Slice` "Load More" kimi ssenarilərdə daha sürətlidir.

### 2. @PageableDefault nə üçündür?
**Cavab:** Controller metodunda Pageable parametri üçün default dəyərləri müəyyən edir. URL-də `page`, `size`, `sort` göndərilmədikdə bu dəyərlər istifadə olunur. Məsələn: `@PageableDefault(size = 20, sort = "createdAt", direction = DESC)`.

### 3. Pagination-da N+1 problemi necə həll edilir?
**Cavab:** `@EntityGraph` və ya `JOIN FETCH` ilə. `Page<User>` qaytararkən hər user üçün ayrıca relasiya sorğusu atılmaması üçün JPQL-də `JOIN FETCH` istifadə edilir. `countQuery` ayrıca yazılır çünki JOIN FETCH COUNT ilə uyğun gəlmir.

### 4. Maksimum page size necə məhdudlaşdırılır?
**Cavab:** `PageableHandlerMethodArgumentResolver.setMaxPageSize()` ilə. Bu olmadan client `size=10000` göndərə bilər, bu isə performance problemə yol açar.

### 5. Sort parametrlərinin SQL injection riski varmı?
**Cavab:** Spring Data öz başına parametrləri query-yə birbaşa yerləşdirmir, JPA metamodel istifadə edir. Lakin dinamik sort field adları validation olmadan istifadə edildikdə gizli risklər ola bilər. Ən yaxşı praktika: icazə verilən field adlarını `allowedSortFields` Set-də saxlamaq.

*Son yenilənmə: 2026-04-10*
