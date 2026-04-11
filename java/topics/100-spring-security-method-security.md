# Spring Security Method Security — Geniş İzah

## Mündəricat
1. [Method Security nədir?](#method-security-nədir)
2. [@PreAuthorize](#preauthorize)
3. [@PostAuthorize](#postauthorize)
4. [@PreFilter və @PostFilter](#prefilter-və-postfilter)
5. [@Secured](#secured)
6. [Custom Permission Evaluator](#custom-permission-evaluator)
7. [İntervyu Sualları](#intervyu-sualları)

---

## Method Security nədir?

**Method Security** — endpoint səviyyəsindəki `requestMatchers` əvəzinə, metod səviyyəsində authorization tətbiq etmək imkanı verir. SpEL (Spring Expression Language) istifadə edir.

```java
@Configuration
@EnableWebSecurity
@EnableMethodSecurity( // ← Bunu əlavə et
    prePostEnabled = true,  // @PreAuthorize, @PostAuthorize (default true)
    securedEnabled = true,  // @Secured annotasiyası
    jsr250Enabled = true    // @RolesAllowed annotasiyası
)
public class SecurityConfig {
    // ...
}
```

---

## @PreAuthorize

Metod çağırılmadan **əvvəl** yoxlanır:

```java
@RestController
@RequestMapping("/api/users")
public class UserController {

    // Rol yoxlaması
    @GetMapping
    @PreAuthorize("hasRole('ADMIN')")
    public List<User> getAllUsers() {
        return userService.findAll();
    }

    // Bir neçə rol
    @GetMapping("/reports")
    @PreAuthorize("hasAnyRole('ADMIN', 'MANAGER')")
    public List<Report> getReports() {
        return reportService.findAll();
    }

    // Authority yoxlaması
    @DeleteMapping("/{id}")
    @PreAuthorize("hasAuthority('user:delete')")
    public void deleteUser(@PathVariable Long id) {
        userService.delete(id);
    }

    // Cari istifadəçi özünü silə bilər, ya da ADMIN
    @DeleteMapping("/{id}")
    @PreAuthorize("hasRole('ADMIN') or #id == authentication.principal.id")
    public void deleteUserOrSelf(@PathVariable Long id) {
        userService.delete(id);
    }

    // Method parametri ilə mürəkkəb yoxlama
    @PutMapping("/{id}")
    @PreAuthorize("hasRole('ADMIN') or (hasRole('USER') and #request.ownerId == authentication.principal.id)")
    public User updateUser(@PathVariable Long id,
                           @RequestBody UpdateUserRequest request) {
        return userService.update(id, request);
    }

    // Authenticated yoxlaması
    @GetMapping("/me")
    @PreAuthorize("isAuthenticated()")
    public User getMyProfile(Authentication auth) {
        return userService.findByUsername(auth.getName());
    }

    // Anonymous icazə
    @GetMapping("/public")
    @PreAuthorize("permitAll()")
    public List<User> getPublicUsers() {
        return userService.findPublic();
    }
}
```

**Service səviyyəsində:**
```java
@Service
public class DocumentService {

    // SpEL ilə mürəkkəb şərt
    @PreAuthorize("hasRole('ADMIN') or " +
                  "@documentPermissionService.canRead(authentication, #documentId)")
    public Document getDocument(Long documentId) {
        return documentRepository.findById(documentId).orElseThrow();
    }

    // Method parametri (DTO field-i)
    @PreAuthorize("authentication.principal.username == #request.username")
    public void updateProfile(UpdateProfileRequest request) {
        // ...
    }
}
```

---

## @PostAuthorize

Metod qaytardıqdan **sonra** nəticə üzərində yoxlanır:

```java
@Service
public class DocumentService {

    // Nəticə üzərində yoxlama — qaytarılan entity-nin owner-i cari istifadəçidirmi?
    @PostAuthorize("returnObject.owner.username == authentication.principal.username " +
                   "or hasRole('ADMIN')")
    public Document findById(Long id) {
        return documentRepository.findById(id).orElseThrow();
    }

    // Nəticə null ola bilər
    @PostAuthorize("returnObject == null or " +
                   "returnObject.status == 'PUBLIC' or " +
                   "hasRole('ADMIN')")
    public Order findOrder(Long id) {
        return orderRepository.findById(id).orElse(null);
    }
}
```

---

## @PreFilter və @PostFilter

Collection parametrlərini/nəticələrini filter etmək:

```java
@Service
public class DocumentService {

    // Gələn collection-dan yalnız cari istifadəçinin yaratdıqlarını saxla
    @PreFilter("filterObject.owner.username == authentication.principal.username")
    public List<Document> bulkUpdate(List<Document> documents) {
        return documentRepository.saveAll(documents);
    }

    // Qaytarılan collection-dan yalnız icazə verilənləri göstər
    @PostFilter("filterObject.status == 'PUBLIC' or " +
                "filterObject.owner.username == authentication.principal.username or " +
                "hasRole('ADMIN')")
    public List<Document> findAll() {
        return documentRepository.findAll();
    }
}
```

---

## @Secured

Sadə rol yoxlaması (SpEL dəstəkləmir):

```java
@Service
public class AdminService {

    // Yalnız bir rol
    @Secured("ROLE_ADMIN")
    public void deleteAllUsers() {
        userRepository.deleteAll();
    }

    // Bir neçə rol (OR məntiqi)
    @Secured({"ROLE_ADMIN", "ROLE_SUPER_ADMIN"})
    public void systemMaintenance() {
        // ...
    }
}

// @RolesAllowed (JSR-250) — @Secured-ə bənzər
@RolesAllowed("ADMIN")
public void adminAction() { }

@RolesAllowed({"ADMIN", "MANAGER"})
public void managerAction() { }
```

---

## Custom Permission Evaluator

Mürəkkəb permission məntiqi üçün:

```java
// 1. PermissionEvaluator implement et
@Component
public class CustomPermissionEvaluator implements PermissionEvaluator {

    private final DocumentRepository documentRepository;
    private final UserService userService;

    // hasPermission(authentication, targetObject, permission)
    @Override
    public boolean hasPermission(Authentication authentication,
                                  Object targetDomainObject,
                                  Object permission) {
        if (authentication == null || !authentication.isAuthenticated()) {
            return false;
        }

        if (targetDomainObject instanceof Document document) {
            return hasDocumentPermission(authentication, document, permission.toString());
        }

        return false;
    }

    // hasPermission(authentication, targetId, targetType, permission)
    @Override
    public boolean hasPermission(Authentication authentication,
                                  Serializable targetId,
                                  String targetType,
                                  Object permission) {
        if (authentication == null || !authentication.isAuthenticated()) {
            return false;
        }

        if ("Document".equals(targetType)) {
            Document document = documentRepository.findById((Long) targetId).orElse(null);
            if (document == null) return false;
            return hasDocumentPermission(authentication, document, permission.toString());
        }

        return false;
    }

    private boolean hasDocumentPermission(Authentication auth,
                                           Document document,
                                           String permission) {
        String username = auth.getName();

        return switch (permission) {
            case "READ" -> document.isPublic() ||
                           document.getOwner().getUsername().equals(username) ||
                           isAdmin(auth);
            case "WRITE" -> document.getOwner().getUsername().equals(username) ||
                            isAdmin(auth);
            case "DELETE" -> isAdmin(auth);
            default -> false;
        };
    }

    private boolean isAdmin(Authentication auth) {
        return auth.getAuthorities().stream()
            .anyMatch(a -> a.getAuthority().equals("ROLE_ADMIN"));
    }
}

// 2. Konfiqurasyona əlavə et
@Configuration
@EnableWebSecurity
@EnableMethodSecurity
public class MethodSecurityConfig {

    @Bean
    public MethodSecurityExpressionHandler methodSecurityExpressionHandler(
            CustomPermissionEvaluator permissionEvaluator) {
        DefaultMethodSecurityExpressionHandler handler =
            new DefaultMethodSecurityExpressionHandler();
        handler.setPermissionEvaluator(permissionEvaluator);
        return handler;
    }
}

// 3. İstifadəsi
@Service
public class DocumentService {

    // hasPermission ilə custom evaluator çağırılır
    @PreAuthorize("hasPermission(#documentId, 'Document', 'READ')")
    public Document getDocument(Long documentId) {
        return documentRepository.findById(documentId).orElseThrow();
    }

    @PreAuthorize("hasPermission(#document, 'WRITE')")
    public Document updateDocument(Document document) {
        return documentRepository.save(document);
    }

    @PreAuthorize("hasPermission(#documentId, 'Document', 'DELETE')")
    public void deleteDocument(Long documentId) {
        documentRepository.deleteById(documentId);
    }
}
```

---

## İntervyu Sualları

### 1. @PreAuthorize vs @Secured fərqi nədir?
**Cavab:** `@Secured` — yalnız rol adı ilə sadə yoxlama, SpEL dəstəkləmir. `@PreAuthorize` — SpEL istifadə edir, mürəkkəb şərtlər yazılır, method parametrlərinə çıxış var, custom bean-ları çağıra bilir. `@PreAuthorize` daha güclü və çevik, `@Secured` sadə hal üçün kifayətdir.

### 2. @PreAuthorize vs @PostAuthorize fərqi?
**Cavab:** `@PreAuthorize` — metod çağırılmadan əvvəl yoxlanır. Yoxlama keçməsə metod işləmir. `@PostAuthorize` — metod işlədikdən sonra qaytarılan nəticəyə əsasən yoxlanır. Nəticəni `returnObject` ilə alır. DB-dən çəkilmiş entity-nin sahibini yoxlamaq üçün istifadə edilir (ID ilə əvvəlcədən yoxlamaq mümkün deyil).

### 3. PermissionEvaluator nə üçündür?
**Cavab:** `hasPermission()` SpEL ifadəsinin arxasındakı iş məntiqini saxlamaq üçün. Mürəkkəb, domain-specific permission qaydalarını SpEL-dən ayırır. Məsələn, document-ə çıxışı owner, shared users, rollar əsasında yoxlamaq. İki metod: object-lə yaxud targetId+targetType ilə.

### 4. @EnableMethodSecurity olmadan nə baş verir?
**Cavab:** `@PreAuthorize`, `@PostAuthorize`, `@Secured` annotasiyaları işləmir — nəzərə alınmır, metod heç bir yoxlama olmadan çağırılır. `@EnableMethodSecurity` Spring-in bu annotasiyalar üçün AOP proxy yaratmasını aktivləşdirir.

### 5. authentication.principal nədir?
**Cavab:** SecurityContext-dəki `Authentication` obyektinin principal-ı. Adətən `UserDetails` obyekti (username, authorities) yaxud JWT token-in `sub` claim-i. Custom `UserDetails` extend edildikdə əlavə field-lərə (id, email) erişim mümkündür: `authentication.principal.id`.

*Son yenilənmə: 2026-04-10*
