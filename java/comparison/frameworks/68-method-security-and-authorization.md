# Method Security və Authorization — Spring vs Laravel

> **Seviyye:** Advanced ⭐⭐⭐

## Giriş

Authorization — "user bu əməliyyatı edə bilər?" sualına cavab verməkdir. İki təbəqədə həll olunur: **route-level** (endpoint-ə çatmamış yoxla) və **method-level** (controller metodu və ya servis daxilində yoxla).

Spring-də bu iş `@PreAuthorize`, `@PostAuthorize`, `@PreFilter`, `@PostFilter` annotation-ları ilə SpEL (Spring Expression Language) vasitəsilə aparılır. Spring Security 6-da yeni `AuthorizationManager` abstraction-u gəldi — bu, köhnə `AccessDecisionManager`-i əvəz edir və daha çevikdir.

Laravel-də iki mexanizm var: **Gate** (sadə callback-əsaslı yoxlamalar) və **Policy** (bir model üçün bütün icazələri bir class-da toplamaq). `@can` Blade directive, `$user->can()`, controller-də `authorize()` — hamısı eyni sistemin fərqli üzləridir. DB-də rol və icazə saxlamaq üçün `spatie/laravel-permission` paketi de-fakto standartdır.

Bu dərsdə RBAC (rol-əsaslı), ABAC (atribut-əsaslı), ReBAC (münasibət-əsaslı) — hər üçünü hər iki framework-də qururuq.

---

## Spring-də istifadəsi

### 1) Method security aktiv et

```java
@Configuration
@EnableWebSecurity
@EnableMethodSecurity(
    prePostEnabled = true,    // @PreAuthorize, @PostAuthorize
    securedEnabled = true,    // @Secured
    jsr250Enabled = true      // @RolesAllowed
)
public class SecurityConfig {

    @Bean
    public SecurityFilterChain filterChain(HttpSecurity http) throws Exception {
        http
            .authorizeHttpRequests(auth -> auth
                .requestMatchers("/public/**").permitAll()
                .requestMatchers("/admin/**").hasRole("ADMIN")
                .anyRequest().authenticated()
            )
            .oauth2ResourceServer(rs -> rs.jwt(Customizer.withDefaults()))
            .sessionManagement(s -> s.sessionCreationPolicy(SessionCreationPolicy.STATELESS))
            .csrf(AbstractHttpConfigurer::disable);
        return http.build();
    }
}
```

### 2) `@PreAuthorize` — əsas istifadə

`@PreAuthorize` metod çağırılmadan əvvəl yoxlanır. SpEL ifadəsi `true` qaytarmalıdır:

```java
@RestController
@RequestMapping("/api/posts")
public class PostController {

    private final PostService service;

    @GetMapping
    @PreAuthorize("isAuthenticated()")
    public List<PostDto> list() {
        return service.listAll();
    }

    @GetMapping("/{id}")
    @PreAuthorize("hasAuthority('SCOPE_posts:read')")
    public PostDto getOne(@PathVariable Long id) {
        return service.get(id);
    }

    @PostMapping
    @PreAuthorize("hasRole('EDITOR') or hasRole('ADMIN')")
    public PostDto create(@RequestBody @Valid CreatePostDto dto) {
        return service.create(dto);
    }

    // Expression-də method parameter-lərə çat
    @PutMapping("/{id}")
    @PreAuthorize("@postSecurity.canEdit(#id, authentication)")
    public PostDto update(@PathVariable Long id, @RequestBody UpdatePostDto dto) {
        return service.update(id, dto);
    }

    @DeleteMapping("/{id}")
    @PreAuthorize("hasRole('ADMIN') or @postSecurity.isOwner(#id, authentication.name)")
    public void delete(@PathVariable Long id) {
        service.delete(id);
    }

    // Current user authentication hasPermission-a ötürülür
    @PostMapping("/{id}/publish")
    @PreAuthorize("hasPermission(#id, 'Post', 'publish')")
    public void publish(@PathVariable Long id) {
        service.publish(id);
    }
}
```

### 3) SpEL built-in funksiyalar

| İfadə | Mənası |
|---|---|
| `hasRole('ADMIN')` | `ROLE_ADMIN` authority var |
| `hasAnyRole('ADMIN', 'EDITOR')` | biri varsa |
| `hasAuthority('SCOPE_posts:read')` | dəqiq authority |
| `hasAnyAuthority('a', 'b')` | biri varsa |
| `isAuthenticated()` | login olub (anonymous deyil) |
| `isAnonymous()` | anonymous user |
| `isRememberMe()` | remember-me token ilə |
| `isFullyAuthenticated()` | remember-me deyil, tam login |
| `permitAll()`, `denyAll()` | hər kəs / heç kim |
| `hasPermission(target, permission)` | PermissionEvaluator çağırır |
| `hasPermission(id, type, permission)` | eyni, amma id + type ilə |
| `principal` | authentication.principal |
| `authentication` | cari Authentication obyekti |

### 4) `@PostAuthorize` — nəticəni qaytardıqdan sonra yoxla

```java
@GetMapping("/posts/{id}")
@PostAuthorize("returnObject.ownerId == authentication.principal.id or hasRole('ADMIN')")
public PostDto getPrivate(@PathVariable Long id) {
    return service.get(id);
    // Metod icra olunur, amma returnObject-in sahibi deyilsə 403 qaytarılır
}

@GetMapping("/posts/{id}")
@PostAuthorize("hasPermission(returnObject, 'read')")
public PostDto getWithPermission(@PathVariable Long id) {
    return service.get(id);
}
```

**Diqqət:** `@PostAuthorize` metod icra olunandan sonra yoxlayır. Database-ə sorğu gedir, side effect ola bilər — buna görə əksər hallarda `@PreAuthorize` + `@postSecurity.canView(#id, authentication)` daha təhlükəsizdir.

### 5) `@PreFilter` və `@PostFilter` — koleksiyaları filtrələ

```java
@PostMapping("/posts/bulk-delete")
@PreFilter("filterObject.ownerId == authentication.principal.id")
public void bulkDelete(@RequestBody List<PostDto> posts) {
    // Yalnız user-in öz post-ları qalır, digərləri filter olunur
    service.deleteAll(posts);
}

@GetMapping("/posts")
@PostFilter("filterObject.public or filterObject.ownerId == authentication.principal.id")
public List<PostDto> visiblePosts() {
    return service.listAll();
    // Qaytarılan list filter olunur — yalnız public və ya user-in post-ları qalır
}
```

**Performans xəbərdarlığı:** `@PostFilter` bütün nəticəni yaddaşa yükləyib sonra filter edir. Böyük list-lərdə SQL-də filter etmək daha yaxşıdır.

### 6) Custom PermissionEvaluator

`hasPermission(...)` ifadəsi `PermissionEvaluator` bean-ı çağırır:

```java
@Component
public class CustomPermissionEvaluator implements PermissionEvaluator {

    private final PostRepository postRepo;

    @Override
    public boolean hasPermission(Authentication auth, Object target, Object permission) {
        if (target instanceof PostDto post) {
            return hasPermission(auth, post.id(), "Post", permission);
        }
        return false;
    }

    @Override
    public boolean hasPermission(Authentication auth, Serializable id, String type, Object permission) {
        return switch (type) {
            case "Post" -> checkPost((Long) id, auth, permission.toString());
            case "Comment" -> checkComment((Long) id, auth, permission.toString());
            default -> false;
        };
    }

    private boolean checkPost(Long id, Authentication auth, String permission) {
        Post post = postRepo.findById(id).orElse(null);
        if (post == null) return false;

        String username = auth.getName();
        boolean isAdmin = auth.getAuthorities().stream()
            .anyMatch(a -> a.getAuthority().equals("ROLE_ADMIN"));

        return switch (permission) {
            case "read" -> post.isPublic() || post.getOwnerId().equals(username) || isAdmin;
            case "edit" -> post.getOwnerId().equals(username) || isAdmin;
            case "delete" -> post.getOwnerId().equals(username) || isAdmin;
            case "publish" -> isAdmin || auth.getAuthorities().stream()
                .anyMatch(a -> a.getAuthority().equals("ROLE_EDITOR"));
            default -> false;
        };
    }
}

@Configuration
public class MethodSecurityConfig {

    @Bean
    static MethodSecurityExpressionHandler expressionHandler(PermissionEvaluator evaluator) {
        DefaultMethodSecurityExpressionHandler handler = new DefaultMethodSecurityExpressionHandler();
        handler.setPermissionEvaluator(evaluator);
        return handler;
    }
}
```

### 7) Role hierarchy

"ADMIN EDITOR-dan mirasla-yır, EDITOR USER-dən miras alır":

```java
@Configuration
public class RoleHierarchyConfig {

    @Bean
    static RoleHierarchy roleHierarchy() {
        return RoleHierarchyImpl.withDefaultRolePrefix()
            .role("ADMIN").implies("EDITOR")
            .role("EDITOR").implies("USER")
            .role("USER").implies("GUEST")
            .build();
    }

    @Bean
    static MethodSecurityExpressionHandler expressionHandler(RoleHierarchy hierarchy) {
        DefaultMethodSecurityExpressionHandler handler = new DefaultMethodSecurityExpressionHandler();
        handler.setRoleHierarchy(hierarchy);
        return handler;
    }
}
```

İndi `@PreAuthorize("hasRole('USER')")` — ADMIN user-i də keçir.

### 8) `AuthorizationManager` — Spring Security 6 yeni extensibility point

Köhnə `AccessDecisionManager` deprecate olundu. Yeni `AuthorizationManager<T>` funksional interfeysdir və daha asan test olunur:

```java
@Component
public class TenantAuthorizationManager implements AuthorizationManager<MethodInvocation> {

    private final TenantService tenantService;

    @Override
    public AuthorizationDecision check(Supplier<Authentication> auth, MethodInvocation mi) {
        Authentication authentication = auth.get();
        Long resourceId = (Long) mi.getArguments()[0];

        String userTenant = ((Jwt) authentication.getPrincipal()).getClaimAsString("tenant_id");
        String resourceTenant = tenantService.getTenantOfResource(resourceId);

        boolean granted = userTenant != null && userTenant.equals(resourceTenant);
        return new AuthorizationDecision(granted);
    }
}

// HttpSecurity-də
http.authorizeHttpRequests(auth -> auth
    .requestMatchers("/api/tenant-resource/**").access(tenantAuthorizationManager)
);
```

### 9) Domain object security — Spring ACL

Hər obyekt üçün ayrıca icazə matrisi saxlayır (`acl_class`, `acl_sid`, `acl_object_identity`, `acl_entry` cədvəlləri):

```xml
<dependency>
    <groupId>org.springframework.security</groupId>
    <artifactId>spring-security-acl</artifactId>
</dependency>
```

```java
@PostAuthorize("hasPermission(returnObject, read)")
public Post load(Long id) { ... }

@PreAuthorize("hasPermission(#post, 'write')")
public void save(Post post) { ... }

// ACL manual idarə
@Transactional
public void grantEdit(Post post, String username) {
    ObjectIdentity oid = new ObjectIdentityImpl(Post.class, post.getId());
    MutableAcl acl = (MutableAcl) aclService.readAclById(oid);
    acl.insertAce(acl.getEntries().size(), BasePermission.WRITE,
                  new PrincipalSid(username), true);
    aclService.updateAcl(acl);
}
```

ACL güclüdür, amma ağırdır — əksər layihələr öz "permissions" cədvəli ilə sadə RBAC/ABAC qurur.

### 10) Reactive (`WebFlux`) ilə method security

```java
@EnableReactiveMethodSecurity
@Configuration
public class ReactiveSecurityConfig {
    @Bean
    public SecurityWebFilterChain filter(ServerHttpSecurity http) {
        return http
            .authorizeExchange(e -> e.anyExchange().authenticated())
            .oauth2ResourceServer(rs -> rs.jwt(Customizer.withDefaults()))
            .build();
    }
}

@Service
public class ReactivePostService {

    @PreAuthorize("hasRole('USER')")
    public Mono<Post> getPost(Long id) {
        return postRepo.findById(id);
    }

    @PreAuthorize("hasRole('ADMIN')")
    public Flux<Post> listAll() {
        return postRepo.findAll();
    }
}
```

### 11) `@AuthenticationPrincipal` — principal-ı inject et

```java
@GetMapping("/me")
public Mono<UserDto> me(@AuthenticationPrincipal Jwt jwt) {
    return Mono.just(new UserDto(
        jwt.getSubject(),
        jwt.getClaimAsString("email"),
        jwt.getClaimAsStringList("permissions")
    ));
}

@GetMapping("/me/posts")
public List<PostDto> myPosts(@CurrentSecurityContext(expression = "authentication.principal")
                            Jwt principal) {
    return service.listByOwner(principal.getSubject());
}
```

### 12) Kompleks avtorizasiya — "author OR moderator OR admin"

```java
@Component("postSecurity")
public class PostSecurity {
    private final PostRepository posts;
    private final SectionMemberRepository members;

    public boolean canEdit(Long postId, Authentication auth) {
        Post post = posts.findById(postId).orElse(null);
        if (post == null) return false;

        String username = auth.getName();
        boolean isAdmin = hasAuthority(auth, "ROLE_ADMIN");
        boolean isAuthor = post.getAuthor().equals(username);
        boolean isModerator = members.isModeratorOf(post.getSectionId(), username);

        return isAuthor || isModerator || isAdmin;
    }

    private boolean hasAuthority(Authentication auth, String authority) {
        return auth.getAuthorities().stream()
            .anyMatch(a -> a.getAuthority().equals(authority));
    }
}

// Controller-də
@PutMapping("/posts/{id}")
@PreAuthorize("@postSecurity.canEdit(#id, authentication)")
public PostDto update(@PathVariable Long id, @RequestBody UpdatePostDto dto) {
    return service.update(id, dto);
}
```

---

## Laravel-də istifadəsi

### 1) Gates — callback-əsaslı sadə yoxlamalar

```php
// app/Providers/AuthServiceProvider.php
use Illuminate\Support\Facades\Gate;

public function boot(): void
{
    Gate::define('view-admin-panel', function (User $user) {
        return $user->is_admin;
    });

    Gate::define('update-post', function (User $user, Post $post) {
        return $user->id === $post->user_id;
    });

    // Multiple abilities
    Gate::define('publish-post', function (User $user, Post $post) {
        return $user->hasRole('editor') || $post->user_id === $user->id;
    });

    // Before hook — admin hər şeyə icazəli
    Gate::before(function (User $user, string $ability) {
        if ($user->is_super_admin) {
            return true;
        }
    });

    // After hook — loglama üçün
    Gate::after(function (User $user, string $ability, ?bool $result, mixed $arguments) {
        Log::info('Authorization check', [
            'user' => $user->id,
            'ability' => $ability,
            'result' => $result,
        ]);
    });
}
```

İstifadəsi:

```php
// Controller-də
public function update(Request $request, Post $post)
{
    if (! Gate::allows('update-post', $post)) {
        abort(403);
    }
    // və ya qısa forma:
    Gate::authorize('update-post', $post);   // avtomatik 403 atır

    $post->update($request->validated());
    return $post;
}

// Blade-də
@can('update-post', $post)
    <a href="{{ route('posts.edit', $post) }}">Edit</a>
@endcan

@cannot('delete-post', $post)
    <span class="text-muted">Delete disabled</span>
@endcannot

// User model-də
if ($user->can('update-post', $post)) { ... }
if ($user->cannot('delete-post', $post)) { ... }
```

### 2) Policies — model-əsaslı

```bash
php artisan make:policy PostPolicy --model=Post
```

```php
// app/Policies/PostPolicy.php
class PostPolicy
{
    use HandlesAuthorization;

    // Before hook — policy class səviyyəsində
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }
        return null;   // null = başqa metoda keç
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Post $post): bool
    {
        return $post->is_public
            || $post->user_id === $user->id
            || $user->isMemberOf($post->section);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['editor', 'author']);
    }

    public function update(User $user, Post $post): Response
    {
        if ($post->user_id === $user->id) {
            return Response::allow();
        }
        if ($user->isModeratorOf($post->section_id)) {
            return Response::allow();
        }
        return Response::deny('You are not the author or moderator of this section.');
    }

    public function delete(User $user, Post $post): bool
    {
        return $post->user_id === $user->id && ! $post->is_published;
    }

    public function restore(User $user, Post $post): bool
    {
        return $user->hasRole('admin');
    }

    public function forceDelete(User $user, Post $post): bool
    {
        return $user->hasRole('super-admin');
    }

    public function publish(User $user, Post $post): bool
    {
        return $user->hasRole('editor') || $post->user_id === $user->id;
    }
}
```

Laravel 11-də Policy-lər avtomatik discover olunur — `App\Models\Post` üçün `App\Policies\PostPolicy` axtarılır. Manual register:

```php
// app/Providers/AuthServiceProvider.php
protected $policies = [
    Post::class => PostPolicy::class,
    Comment::class => CommentPolicy::class,
];
```

### 3) `authorize()` — resource controller

```php
class PostController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Post::class, 'post');
        // Avtomatik: index→viewAny, show→view, create→create,
        //          store→create, edit→update, update→update, destroy→delete
    }

    public function show(Post $post)
    {
        // $this->authorize('view', $post); — authorizeResource edib
        return view('posts.show', ['post' => $post]);
    }

    public function update(UpdatePostRequest $request, Post $post)
    {
        // $this->authorize('update', $post); — authorizeResource edib
        $post->update($request->validated());
        return redirect()->route('posts.show', $post);
    }

    public function publish(Post $post)
    {
        $this->authorize('publish', $post);
        $post->publish();
        return back();
    }
}
```

### 4) Form Request-də avtorizasiya

```php
class UpdatePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('post'));
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'body' => 'required|string',
        ];
    }
}

// Controller-də validate edəndə authorize() yoxlanır
public function update(UpdatePostRequest $request, Post $post)
{
    // $request->authorize() false qaytarsa avtomatik 403
    $post->update($request->validated());
}
```

### 5) Route middleware ilə policy

```php
// routes/web.php
Route::middleware(['auth'])->group(function () {
    Route::get('/posts/{post}', [PostController::class, 'show'])
        ->middleware('can:view,post');

    Route::put('/posts/{post}', [PostController::class, 'update'])
        ->middleware('can:update,post');

    Route::delete('/posts/{post}', [PostController::class, 'destroy'])
        ->middleware('can:delete,post');

    // Parametrsiz gate
    Route::get('/admin', fn () => view('admin.dashboard'))
        ->middleware('can:view-admin-panel');
});
```

### 6) Spatie laravel-permission — role + permission cədvəllərdə

```bash
composer require spatie/laravel-permission
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan migrate
```

```php
// app/Models/User.php
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles;
}
```

Seed:

```php
// database/seeders/RolePermissionSeeder.php
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

$permissions = [
    'posts.view', 'posts.create', 'posts.update', 'posts.delete',
    'posts.publish', 'users.manage', 'admin.panel',
];

foreach ($permissions as $p) {
    Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
}

$admin = Role::firstOrCreate(['name' => 'admin']);
$admin->givePermissionTo(Permission::all());

$editor = Role::firstOrCreate(['name' => 'editor']);
$editor->givePermissionTo(['posts.view', 'posts.create', 'posts.update', 'posts.publish']);

$author = Role::firstOrCreate(['name' => 'author']);
$author->givePermissionTo(['posts.view', 'posts.create', 'posts.update']);

$viewer = Role::firstOrCreate(['name' => 'viewer']);
$viewer->givePermissionTo(['posts.view']);

$user = User::find(1);
$user->assignRole('editor');
$user->givePermissionTo('users.manage');   // birbaşa permission
```

İstifadəsi:

```php
// Middleware
Route::middleware(['role:admin'])->group(function () { ... });
Route::middleware(['permission:posts.publish'])->group(function () { ... });
Route::middleware(['role_or_permission:editor|posts.publish'])->group(function () { ... });

// Controller / Model
$user->hasRole('editor');
$user->hasAnyRole(['editor', 'author']);
$user->hasAllRoles(['editor', 'author']);
$user->hasPermissionTo('posts.publish');
$user->can('posts.publish');   // Gate və Spatie ikisi də yoxlanır

// Blade
@role('admin') ... @endrole
@hasrole('editor|author') ... @endhasrole
@can('posts.publish') ... @endcan
```

### 7) Teams / Multi-tenancy — Spatie

```php
// config/permission.php
'teams' => true,
'team_foreign_key' => 'team_id',
```

```php
// İstifadəçiyə team kontekstində rol ver
setPermissionsTeamId($teamId);
$user->assignRole('editor');    // yalnız bu team-də

setPermissionsTeamId($anotherTeamId);
$user->hasRole('editor');       // bu team-də deyil → false
```

### 8) Kompleks avtorizasiya — "author OR moderator OR admin"

```php
// app/Policies/PostPolicy.php
public function update(User $user, Post $post): bool
{
    // 1. Admin hər şey edə bilər
    if ($user->hasRole('admin')) {
        return true;
    }

    // 2. Author-dur
    if ($post->user_id === $user->id) {
        return true;
    }

    // 3. Bu section-un moderator-udur
    $isModerator = DB::table('section_moderators')
        ->where('section_id', $post->section_id)
        ->where('user_id', $user->id)
        ->exists();

    return $isModerator;
}
```

N+1 problemini önləmək üçün (policy-nin çoxlu post üçün çağırılması):

```php
public function update(User $user, Post $post): bool
{
    if ($user->hasRole('admin')) return true;
    if ($post->user_id === $user->id) return true;

    // User-in moderator olduğu bütün section-ları bir dəfə yüklə (cache et)
    $moderatedSections = Cache::remember(
        "user:{$user->id}:moderated_sections",
        now()->addMinutes(5),
        fn () => DB::table('section_moderators')
            ->where('user_id', $user->id)
            ->pluck('section_id')
            ->toArray()
    );

    return in_array($post->section_id, $moderatedSections);
}
```

### 9) Response::deny() ilə aydın mesaj

```php
public function delete(User $user, Post $post): Response
{
    if ($post->is_published) {
        return Response::deny('Published posts cannot be deleted. Unpublish first.', 409);
    }
    if ($post->user_id !== $user->id) {
        return Response::deny('Only the author can delete.', 403);
    }
    return Response::allow();
}

// Controller-də
$response = Gate::inspect('delete', $post);
if (! $response->allowed()) {
    return response()->json([
        'error' => $response->message(),
        'code' => $response->code(),
    ], $response->code() ?? 403);
}
```

### 10) Test

```php
use Spatie\Permission\Models\Role;

class PostPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_author_can_update_own_post(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($user->can('update', $post));
    }

    public function test_other_user_cannot_update(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $owner->id]);

        $this->assertFalse($other->can('update', $post));
    }

    public function test_admin_can_update_any_post(): void
    {
        Role::firstOrCreate(['name' => 'admin']);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $post = Post::factory()->create();

        $this->assertTrue($admin->can('update', $post));
    }

    public function test_unauthorized_returns_403(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($other)
            ->putJson("/api/posts/{$post->id}", ['title' => 'new'])
            ->assertStatus(403);
    }
}
```

---

## Əsas fərqlər

| Xüsusiyyət | Spring | Laravel |
|---|---|---|
| Method-level annotation | `@PreAuthorize("SpEL")` | `authorize('ability', $model)` |
| Parametr access | SpEL `#id`, `#user`, `authentication` | Callback argument-ləri |
| Collection filter | `@PreFilter`, `@PostFilter` | `$posts->filter(fn ($p) => Gate::allows('view', $p))` |
| Rol hierarchy | `RoleHierarchyImpl` built-in | Spatie `permission-role-hierarchy` 3rd party |
| Domain object ACL | Spring Security ACL modulu | Manual və ya Spatie + `belongsToMany` |
| Policy-model auto | `@Component("postSecurity")` bean | `PostPolicy` auto-discover |
| Blade/template directive | Yox (server-side render az) | `@can`, `@cannot`, `@role` |
| Before/after hook | `AuthorizationManager` composition | `Gate::before`, `Policy::before` |
| Reactive dəstək | `@EnableReactiveMethodSecurity` | Yox (Laravel sync) |
| Rol + DB-də permission | Spring Security + öz cədvəl | Spatie laravel-permission standart |
| Multi-tenant | Manual (TenantContext + SpEL) | Spatie teams flag |
| Deny mesajı | Exception message | `Response::deny('...', $code)` |
| Formats | 403 Forbidden / 401 Unauthorized | eyni |
| Test helper | `.with(user().roles("ADMIN"))` | `$this->actingAs($user)` |

---

## Niyə belə fərqlər var?

**Spring compile-time typed ekosistem.** `@PreAuthorize` annotation-ı + SpEL kombinasiyası güclüdür, amma SpEL ifadələri string-dir — runtime-da xətalar üzə çıxır. Buna görə böyük layihələrdə tez-tez `@PreAuthorize("@postSecurity.canEdit(#id, authentication)")` kimi komponent-çağırış istifadə olunur — bu yolla logic Java kodunda qalır, test olunur.

**Laravel ekspressiv, conventions-əsaslı.** Policy class-ları model ilə naming convention əsasında bağlanır (`Post` → `PostPolicy`), metod adları resource controller actions ilə uyğundur (`view`, `update`, `delete`). Bu sadəlik kiçik-orta layihələrdə üstünlükdür, amma kompleks biznes qaydaları olduqda method-ların arxasında çoxlu logic gizlənir.

**Spring ACL ağır, Laravel Spatie yüngül.** Spring Security ACL DB-də hər obyekt üçün `acl_entry` sətirlərini saxlayır — yüksək granularity, amma 1000+ obyekt olanda cədvəl çox böyüyür. Spatie laravel-permission isə "rol → icazə" modelini saxlayır, obyekt-level kontrolu `Policy` içində SQL-lə edir. Laravel yanaşması əksər real hallarda daha sürətlidir.

**Blade directive-ləri.** Laravel server-side render edilən UI-lərdə `@can` çox işə yarayır — button-u göstər/gizlət məntiqi. Spring tərəfdə Thymeleaf-də `sec:authorize="hasRole('ADMIN')"` var, amma ümumiyyətlə Java backend-lərində server-side UI az yazılır — REST API + JS client üstünlük təşkil edir.

**RoleHierarchy vs flat permissions.** Spring `RoleHierarchyImpl` rolları iç-içə yerləşdirir — ADMIN avtomatik EDITOR kimi davranır. Laravel Spatie-də hierarchy birinci-sinif dəstəklənmir — əvəzinə "admin rolu bütün permission-ları alır" seed pattern-i istifadə olunur, və ya 3rd party paket əlavə edilir.

**Reactive support.** Spring WebFlux ilə `@EnableReactiveMethodSecurity` tam işləyir — `Mono<Post>` qaytaran metodlar həm də avtorizasiya olunur. Laravel sync-dir, buna görə bu problem yoxdur (lazım deyil).

**`@PostAuthorize` vs Laravel Policy-də `before`.** `@PostAuthorize` obyekt yükləndikdən sonra yoxlamağa imkan verir — amma side effect ola bilər. Laravel-də eyni iş üçün `Policy::view()` metodu obyekti parameter alır — bu daha təmiz pattern-dir, çünki obyekti yükləyəndən sonra yoxlayırıq, amma hələ cavab qaytarmamışıq.

---

## Hansı framework-də var, hansında yoxdur?

**Yalnız Spring-də:**
- SpEL expression dili — annotation-da kompleks ifadələr
- `@PreFilter`, `@PostFilter` koleksiya filter annotation-ları
- `RoleHierarchyImpl` — built-in role mirasxorluğu
- `PermissionEvaluator` interface + custom ilə `hasPermission(target, perm)`
- Spring Security ACL — DB-də per-object ACL
- `@AuthenticationPrincipal`, `@CurrentSecurityContext` parametrlərə injection
- `AuthorizationManager` — yeni functional interface, composable
- Reactive method security (`@EnableReactiveMethodSecurity`)
- `@Secured`, `@RolesAllowed` (JSR-250) alternativ annotation-lar

**Yalnız Laravel-də:**
- `Gate::define()` — callback-əsaslı sadə yoxlama
- `Policy` auto-discovery by naming convention
- `$this->authorizeResource()` — resource controller üçün automasiya
- `@can`, `@cannot`, `@role`, `@permission` Blade directive-ləri
- `Response::deny('message', $code)` — deny səbəbini oxutmaq
- `Gate::inspect()` — detallı cavab alma
- `FormRequest::authorize()` — request level avtorizasiya
- Spatie `laravel-permission` — DB-də rol+permission+team hazır paket
- `$user->hasRole()`, `$user->hasPermissionTo()` — model trait
- `setPermissionsTeamId()` — multi-tenant context switch

**Ortaq, amma fərqli adlanan:**
- `@PreAuthorize` ≈ `authorize()` / `@can`
- `@PostAuthorize` ≈ Policy metodunun içindəki yoxlama (obyekt parameter kimi)
- Role hierarchy (Spring native, Laravel seed pattern)
- Permission evaluator (Spring bean, Laravel Policy metod)
- Before hook (Spring `AuthorizationManager.check`, Laravel `Gate::before`)

---

## RBAC vs ABAC vs ReBAC

**RBAC (Role-Based Access Control).** User-ə rol verilir, rol icazələrin siyahısıdır. Sadə və ən geniş istifadə olunan. "Editor rolu olan user post yaza bilər."

```java
// Spring
@PreAuthorize("hasRole('EDITOR')")
public Post create(...) { }
```

```php
// Laravel
Route::post('/posts')->middleware('role:editor');
```

**ABAC (Attribute-Based Access Control).** Qərar user-in atributları + resource-un atributları + context əsasında verilir. "User yalnız öz department-ının file-larını, yalnız iş saatında görə bilər."

```java
// Spring
@PreAuthorize("authentication.principal.claims['department'] == #file.department " +
              "and T(java.time.LocalTime).now().isBefore(T(java.time.LocalTime).of(18, 0))")
public FileDto getFile(FileDto file) { }
```

```php
// Laravel
Gate::define('view-file', function (User $user, File $file) {
    $isOfficeHours = now()->between(now()->setTime(9, 0), now()->setTime(18, 0));
    return $user->department_id === $file->department_id && $isOfficeHours;
});
```

**ReBAC (Relationship-Based Access Control).** Qərar user ilə resource arasındakı münasibət əsasında verilir (Google Zanzibar modelindən). "User bu post-un author-udur" və ya "User bu section-un moderator-udur" və ya "User bu team-in üzvüdür".

```java
// Spring
@PreAuthorize("@postSecurity.isAuthor(#id, authentication.name) " +
              "or @sectionSecurity.isModerator(#post.sectionId, authentication.name) " +
              "or hasRole('ADMIN')")
public void edit(@PathVariable Long id, Post post) { }
```

```php
// Laravel Policy
public function update(User $user, Post $post): bool
{
    return $user->hasRole('admin')
        || $post->user_id === $user->id
        || $user->moderatedSections()->where('sections.id', $post->section_id)->exists();
}
```

Real sistemlərdə adətən RBAC + ABAC/ReBAC qarışıq istifadə olunur. Böyük sistemlər üçün **OpenFGA** (Ory-nin Zanzibar-like servisi), **Oso**, **Casbin** kimi xarici authorization engine-lər var — hər iki framework-dən çağırmaq mümkündür.

---

## Best Practices

1. **İki səviyyəli qoruma.** Route-level (middleware, `@PreAuthorize` on controller) + service-level (policy, `@PreAuthorize` on service metoda). Bu, yoxlamanı keçib servisi birbaşa çağıranları da tutur.

2. **Policy/SpEL expression-larını qısa saxla.** 50 sətirlik annotation oxunmur. Mürəkkəb qaydaları bean-a (`@postSecurity.canEdit(...)`) və ya Policy metoduna köçür.

3. **401 və 403 fərqini qoru.** Autentifikasiya yoxdur/yanlışdır → 401. Autentifikasiya var, amma icazə yoxdur → 403. Client-lər bunu fərqli idarə edir.

4. **N+1 qarşı cache.** Policy-lər list səhifədə hər element üçün çağırılır — user-in rollarını və permission-larını session/request cache-də saxla (Spatie avtomatik edir, Laravel Gate-lərdə manual).

5. **`@PostFilter` / `$collection->filter()` böyük list-lərdə istifadə etmə.** Filter-i SQL-də et — WHERE clause ilə.

6. **Rol silmək ağır iş.** Rol silinəndə bütün istifadəçilərin icazələri dəyişir. Production-da rol-a bağlı workflow və ya prod-freeze planla.

7. **Admin-i `before` hook-da yox, rol hierarchy-də ver.** `Gate::before(fn ($user) => $user->is_admin)` gözlənilməz açıqlıqlar yaradır — hansısa Policy metod bu user üçün hətta iş prinsipcə mümkün olmayan şeyi açır.

8. **Kompleks qaydalar üçün authorization engine.** 100+ resource tipi, 20+ rol varsa, OpenFGA və ya Oso kimi xarici servis düşün. Hər iki framework-dən çağırmaq olur.

9. **Audit log.** Avtorizasiya uğursuzluqlarını log et — brute force və ya insider attack tapmaq üçün. Spring-də `AuthorizationDeniedEvent`, Laravel-də `Gate::after`.

10. **Test hər policy metodunda.** Unit test-də user rolu və model sahibini manipulyasiya et, allow/deny hər iki sənariyi yoxla.

11. **Documentation.** Hər controller/service metodu üçün "bu əməliyyatı kim edə bilər?" sualına cavab README-də və ya annotation-da olsun. Bu, security review zamanı həyat qurtarır.

---

## Yekun

Spring-də method security `@PreAuthorize` + SpEL + `AuthorizationManager` abstraction-ı üzərində qurulub. SpEL ifadələri güclüdür — parametrlərə, authentication obyektinə, JWT claim-lərinə çatmağa imkan verir. `PermissionEvaluator`, `RoleHierarchy`, ACL, reactive support kimi enterprise özəllikləri hazır.

Laravel Gate + Policy + Spatie `laravel-permission` üçlüyü ilə convention-əsaslı və sadə avtorizasiya verir. Policy-lər model ilə name-based auto-bind olur, `@can` Blade directive view-larda sadə şəkildə UI açıb-bağlayır, Spatie isə DB-də role+permission+team modelini hazır verir. Kiçik-orta layihələrdə Laravel-in sadəliyi sərfəlidir; kompleks ACL və multi-tenant senarilerində Spring daha çevikdir.

RBAC hər iki framework-də standart. ABAC və ReBAC üçün custom Policy/SpEL kifayətdir — amma yüksək miqyasda **OpenFGA**, **Oso**, **Casbin** kimi xarici authorization engine-lər düşünülməlidir. Əsas prinsip hər yerdə eynidir: qərarı birbaşa controller-də yazma, abstraction-un (Policy class və ya `@Component("security")` bean) arxasına sal, test et, audit log saxla.
