# Authentication ve Authorization

## Giris

Authentication (kimlik dogrulama) istifadecinin kim oldugunu yoxlayir. Authorization (icaze) ise hemin istifadecinin ne ede bileceyini mueyyen edir. Spring Security cox guclu ve cevik bir tehlukesizlik frameworkudur. Laravel ise daha sade, amma effektiv auth sistemi teklif edir. Her ikisi JWT, OAuth2, session-based authentication destekleyir.

## Spring-de istifadesi

### Spring Security konfiqurasiyasi

Spring Security `SecurityFilterChain` ile konfiqurasiya olunur:

```java
import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;
import org.springframework.security.config.annotation.method.configuration.EnableMethodSecurity;
import org.springframework.security.config.annotation.web.builders.HttpSecurity;
import org.springframework.security.config.annotation.web.configuration.EnableWebSecurity;
import org.springframework.security.config.http.SessionCreationPolicy;
import org.springframework.security.crypto.bcrypt.BCryptPasswordEncoder;
import org.springframework.security.crypto.password.PasswordEncoder;
import org.springframework.security.web.SecurityFilterChain;
import org.springframework.security.web.authentication.UsernamePasswordAuthenticationFilter;

@Configuration
@EnableWebSecurity
@EnableMethodSecurity
public class SecurityConfig {

    private final JwtAuthenticationFilter jwtAuthFilter;

    public SecurityConfig(JwtAuthenticationFilter jwtAuthFilter) {
        this.jwtAuthFilter = jwtAuthFilter;
    }

    @Bean
    public SecurityFilterChain securityFilterChain(HttpSecurity http) throws Exception {
        http
            .csrf(csrf -> csrf.disable())
            .sessionManagement(session ->
                session.sessionCreationPolicy(SessionCreationPolicy.STATELESS))
            .authorizeHttpRequests(auth -> auth
                .requestMatchers("/api/auth/**").permitAll()
                .requestMatchers("/api/public/**").permitAll()
                .requestMatchers("/api/admin/**").hasRole("ADMIN")
                .requestMatchers("/api/posts/**").hasAnyRole("USER", "ADMIN")
                .anyRequest().authenticated()
            )
            .addFilterBefore(jwtAuthFilter, UsernamePasswordAuthenticationFilter.class);

        return http.build();
    }

    @Bean
    public PasswordEncoder passwordEncoder() {
        return new BCryptPasswordEncoder();
    }
}
```

### UserDetailsService implementasiyasi

Spring Security istifadeci melumatlarini `UserDetailsService` vasitesile alir:

```java
import org.springframework.security.core.userdetails.UserDetails;
import org.springframework.security.core.userdetails.UserDetailsService;
import org.springframework.security.core.userdetails.UsernameNotFoundException;
import org.springframework.security.core.authority.SimpleGrantedAuthority;
import org.springframework.stereotype.Service;

@Service
public class CustomUserDetailsService implements UserDetailsService {

    private final UserRepository userRepository;

    public CustomUserDetailsService(UserRepository userRepository) {
        this.userRepository = userRepository;
    }

    @Override
    public UserDetails loadUserByUsername(String email) throws UsernameNotFoundException {
        User user = userRepository.findByEmail(email)
            .orElseThrow(() -> new UsernameNotFoundException(
                "Istifadeci tapilmadi: " + email));

        List<SimpleGrantedAuthority> authorities = user.getRoles().stream()
            .map(role -> new SimpleGrantedAuthority("ROLE_" + role.getName()))
            .collect(Collectors.toList());

        return new org.springframework.security.core.userdetails.User(
            user.getEmail(),
            user.getPassword(),
            user.isActive(),    // enabled
            true,               // accountNonExpired
            true,               // credentialsNonExpired
            true,               // accountNonLocked
            authorities
        );
    }
}
```

### JWT Filter

```java
import jakarta.servlet.FilterChain;
import jakarta.servlet.ServletException;
import jakarta.servlet.http.HttpServletRequest;
import jakarta.servlet.http.HttpServletResponse;
import org.springframework.security.authentication.UsernamePasswordAuthenticationToken;
import org.springframework.security.core.context.SecurityContextHolder;
import org.springframework.security.core.userdetails.UserDetails;
import org.springframework.stereotype.Component;
import org.springframework.web.filter.OncePerRequestFilter;
import java.io.IOException;

@Component
public class JwtAuthenticationFilter extends OncePerRequestFilter {

    private final JwtService jwtService;
    private final CustomUserDetailsService userDetailsService;

    public JwtAuthenticationFilter(JwtService jwtService,
                                    CustomUserDetailsService userDetailsService) {
        this.jwtService = jwtService;
        this.userDetailsService = userDetailsService;
    }

    @Override
    protected void doFilterInternal(HttpServletRequest request,
                                     HttpServletResponse response,
                                     FilterChain filterChain)
            throws ServletException, IOException {

        String authHeader = request.getHeader("Authorization");

        if (authHeader == null || !authHeader.startsWith("Bearer ")) {
            filterChain.doFilter(request, response);
            return;
        }

        String jwt = authHeader.substring(7);
        String userEmail = jwtService.extractUsername(jwt);

        if (userEmail != null &&
            SecurityContextHolder.getContext().getAuthentication() == null) {

            UserDetails userDetails = userDetailsService.loadUserByUsername(userEmail);

            if (jwtService.isTokenValid(jwt, userDetails)) {
                UsernamePasswordAuthenticationToken authToken =
                    new UsernamePasswordAuthenticationToken(
                        userDetails, null, userDetails.getAuthorities());

                SecurityContextHolder.getContext().setAuthentication(authToken);
            }
        }

        filterChain.doFilter(request, response);
    }
}
```

### JWT Service

```java
import io.jsonwebtoken.Jwts;
import io.jsonwebtoken.SignatureAlgorithm;
import io.jsonwebtoken.security.Keys;
import org.springframework.beans.factory.annotation.Value;
import org.springframework.security.core.userdetails.UserDetails;
import org.springframework.stereotype.Service;
import java.security.Key;
import java.util.Date;

@Service
public class JwtService {

    @Value("${jwt.secret}")
    private String secretKey;

    @Value("${jwt.expiration}")
    private long jwtExpiration;

    public String generateToken(UserDetails userDetails) {
        return Jwts.builder()
            .setSubject(userDetails.getUsername())
            .setIssuedAt(new Date())
            .setExpiration(new Date(System.currentTimeMillis() + jwtExpiration))
            .signWith(getSigningKey(), SignatureAlgorithm.HS256)
            .compact();
    }

    public String extractUsername(String token) {
        return Jwts.parserBuilder()
            .setSigningKey(getSigningKey())
            .build()
            .parseClaimsJws(token)
            .getBody()
            .getSubject();
    }

    public boolean isTokenValid(String token, UserDetails userDetails) {
        String username = extractUsername(token);
        return username.equals(userDetails.getUsername()) && !isTokenExpired(token);
    }

    private boolean isTokenExpired(String token) {
        Date expiration = Jwts.parserBuilder()
            .setSigningKey(getSigningKey())
            .build()
            .parseClaimsJws(token)
            .getBody()
            .getExpiration();
        return expiration.before(new Date());
    }

    private Key getSigningKey() {
        return Keys.hmacShaKeyFor(secretKey.getBytes());
    }
}
```

### Auth Controller

```java
@RestController
@RequestMapping("/api/auth")
public class AuthController {

    private final AuthenticationManager authenticationManager;
    private final JwtService jwtService;
    private final CustomUserDetailsService userDetailsService;

    // Constructor injection

    @PostMapping("/login")
    public ResponseEntity<AuthResponse> login(@RequestBody LoginRequest request) {
        authenticationManager.authenticate(
            new UsernamePasswordAuthenticationToken(
                request.getEmail(), request.getPassword()));

        UserDetails userDetails = userDetailsService.loadUserByUsername(request.getEmail());
        String token = jwtService.generateToken(userDetails);

        return ResponseEntity.ok(new AuthResponse(token));
    }
}
```

### Method-level authorization ile `@PreAuthorize`

```java
@RestController
@RequestMapping("/api/posts")
public class PostController {

    @GetMapping
    public List<PostDto> getAllPosts() {
        // Butun authenticated istifadeciler gore biler
        return postService.getAllPosts();
    }

    @PostMapping
    @PreAuthorize("hasRole('USER') or hasRole('ADMIN')")
    public PostDto createPost(@RequestBody CreatePostRequest request) {
        return postService.createPost(request);
    }

    @DeleteMapping("/{id}")
    @PreAuthorize("hasRole('ADMIN') or @postService.isOwner(#id, authentication.name)")
    public void deletePost(@PathVariable Long id) {
        postService.deletePost(id);
    }

    @GetMapping("/admin/stats")
    @PreAuthorize("hasAuthority('ROLE_ADMIN')")
    public StatsDto getStats() {
        return postService.getStats();
    }
}
```

## Laravel-de istifadesi

### Daxili Authentication

Laravel `php artisan make:auth` ve ya Laravel Breeze/Jetstream ile hazir auth sistemi teklif edir. Amma manual da qura bilerik:

```php
// routes/web.php
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth');
```

```php
<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();
            return redirect()->intended('dashboard');
        }

        return back()->withErrors([
            'email' => 'Melumatlar sehvdir.',
        ]);
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|confirmed|min:8',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        Auth::login($user);

        return redirect('/dashboard');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
```

### Sanctum ile API Authentication (JWT alternativi)

```php
// config/sanctum.php avtomatik konfiqurasiya olunur

// API login
class ApiAuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Melumatlar sehvdir.'
            ], 401);
        }

        // Token yaratmaq
        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user,
        ]);
    }

    public function logout(Request $request)
    {
        // Cari tokeni sil
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Cixis edildi']);
    }
}
```

```php
// routes/api.php
Route::post('/login', [ApiAuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn (Request $request) => $request->user());
    Route::post('/logout', [ApiAuthController::class, 'logout']);
    Route::apiResource('/posts', PostController::class);
});
```

### Guards

Guard istifadecinin nece authenticate olundugunu mueyyen edir:

```php
// config/auth.php
'guards' => [
    'web' => [
        'driver' => 'session',
        'provider' => 'users',
    ],
    'api' => [
        'driver' => 'sanctum',
        'provider' => 'users',
    ],
    'admin' => [
        'driver' => 'session',
        'provider' => 'admins',
    ],
],

'providers' => [
    'users' => [
        'driver' => 'eloquent',
        'model' => App\Models\User::class,
    ],
    'admins' => [
        'driver' => 'eloquent',
        'model' => App\Models\Admin::class,
    ],
],
```

```php
// Ferqli guard istifade etmek
Auth::guard('admin')->attempt($credentials);
Auth::guard('admin')->user();
```

### Gates ve Policies (Authorization)

**Gate** - sade icaze yoxlamalari ucun closure-lar:

```php
// app/Providers/AppServiceProvider.php (ve ya AuthServiceProvider)
use Illuminate\Support\Facades\Gate;

public function boot(): void
{
    Gate::define('update-post', function (User $user, Post $post) {
        return $user->id === $post->user_id;
    });

    Gate::define('admin-access', function (User $user) {
        return $user->role === 'admin';
    });
}
```

```php
// Controller-de istifade
public function update(Request $request, Post $post)
{
    Gate::authorize('update-post', $post);

    // ve ya
    if (Gate::allows('update-post', $post)) {
        // icaze var
    }

    if (Gate::denies('update-post', $post)) {
        abort(403);
    }
}
```

**Policy** - bir model ucun butun icaze qaydalari bir yerde:

```php
<?php
// app/Policies/PostPolicy.php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;

class PostPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // Butun authenticated istifadeciler gore biler
    }

    public function view(User $user, Post $post): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->is_active;
    }

    public function update(User $user, Post $post): bool
    {
        return $user->id === $post->user_id;
    }

    public function delete(User $user, Post $post): bool
    {
        return $user->id === $post->user_id || $user->role === 'admin';
    }
}
```

```php
// Controller-de istifade
class PostController extends Controller
{
    public function update(Request $request, Post $post)
    {
        $this->authorize('update', $post);

        $post->update($request->validated());

        return redirect()->route('posts.show', $post);
    }

    public function destroy(Post $post)
    {
        $this->authorize('delete', $post);

        $post->delete();

        return redirect()->route('posts.index');
    }
}

// Blade template-de
@can('update', $post)
    <a href="{{ route('posts.edit', $post) }}">Redakte et</a>
@endcan
```

### Middleware ile qoruma

```php
// Oz middleware yaratmaq
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user()?->role !== 'admin') {
            abort(403, 'Icaze yoxdur');
        }

        return $next($request);
    }
}

// bootstrap/app.php-de qeydiyyat
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'admin' => EnsureUserIsAdmin::class,
    ]);
})
```

```php
// Route-da istifade
Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/admin/dashboard', [AdminController::class, 'dashboard']);
});
```

## Esas ferqler

| Xususiyyet | Spring Security | Laravel Auth |
|---|---|---|
| **Arxitektura** | Filter-based (Servlet Filter chain) | Middleware-based |
| **Konfiqurasiya** | Java sinifi ile (`SecurityFilterChain`) | config/auth.php + middleware |
| **Istifadeci yuklemek** | `UserDetailsService` interface | Eloquent model (`User`) |
| **Parol hashing** | `PasswordEncoder` bean | `Hash::make()` facade |
| **JWT** | Manual implementasiya (jjwt kutupxanesi) | Sanctum (token-based, sade) |
| **OAuth2** | Spring Security OAuth2 (daxili destek) | Passport paketi (ayrica qurasdirilir) |
| **Role/Permission** | `GrantedAuthority`, `hasRole()` | Gate, Policy |
| **Method-level** | `@PreAuthorize`, `@Secured` | `$this->authorize()`, `@can` |
| **CSRF qorumasi** | Default olaraq aktivdir | Default olaraq aktivdir |
| **Session idare** | `SessionCreationPolicy` ile | Session driver konfiqurasiyasi ile |
| **Hazir UI** | Yoxdur (manual yaradilir) | Breeze, Jetstream ile hazir UI |

## Niye bele ferqler var?

### Spring Security-nin murakkebliyi

Spring Security enterprise muhitler ucun yaradilib. Bank sistemleri, dovlet qurumlarinin tehlukesizlik teleblerine cavab vermek ucun cox detalli ve konfiqurasiya edile bilen bir sistem qurulub. `SecurityFilterChain` ile her HTTP sorgusu bir nece filtrden kecir - her filtr oz isini gorur (CORS, CSRF, authentication, authorization). Bu murakkeb gorunse de, her addimi tam kontrol etmeye imkan verir.

### Laravel-in sadeliviliyi

Laravel "cogu web application ucun eyni auth lazimdir" felsefesi ile yaradilib. `Auth::attempt()` bir satir ile login edir, `auth` middleware bir soz ile route-u qoruyur. Bu yanasmada 90% proyektin ehtiyaci 10% kodla hell olunur. Eger daha murakkeb sey lazim olsa, Passport ve ya custom guard yazilir.

### Role sistemi ferqi

Spring-de role ve authority ferqli konseptlerdir. `ROLE_ADMIN` bir role-dur, `READ_POSTS` bir authority-dir. Bu ince ferq boyuk sistemlerde vacibdir.

Laravel-de ise default olaraq role/permission sistemi yoxdur. `Gate` ve `Policy` ile oz mentiginizi yazirsiniz, ve ya `spatie/laravel-permission` kimi paketler istifade olunur. Bu daha sadedir, amma strukturlasdirilmamis ola biler.

## Hansi framework-de var, hansinda yoxdur?

### Yalniz Spring-de (ve ya daha asandir):
- **Filter chain** - HTTP sorgusunun bir nece tehlukesizlik filtrinden kecmesi
- **Method-level security annotasiyalari** - `@PreAuthorize("hasRole('ADMIN') and #id > 0")`
- **SpEL (Spring Expression Language)** icaze ifadelerinde - cox guclu ifade dili
- **Daxili OAuth2 Resource Server desteki** - annotasiya ile konfiqurasiya
- **CORS konfiqurasiyasi** SecurityFilterChain daxilinde
- **Remember-me authentication** daxili destek

### Yalniz Laravel-de (ve ya daha asandir):
- **Hazir auth scaffolding** - `php artisan make:auth`, Breeze, Jetstream
- **Policy sinfleri** - bir model ucun butun icazeleri bir yerde toplamaq
- **`@can` Blade direktivi** - template-de birbaşa icaze yoxlamasi
- **Sanctum** - SPA ve mobile app ucun sade token sistemi
- **Guard sistemi** - ferqli istifadeci novleri ucun ferqli auth mexanizmleri
- **`Auth::routes()`** - bir satirla butun auth route-larini yaratmaq
