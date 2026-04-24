# REST API

> **Seviyye:** Beginner ⭐

## Giris

REST API muasir web aplikasiyalarin ayrilmaz hissesidir. Frontend (React, Vue) ve mobile app-lar backend ile REST API vasitesile elaqe qurur. Spring `@RestController` ve `ResponseEntity` ile guclu API yaratma imkani verir. Laravel ise API Resources, `response()->json()` ve rahat routing ile API development-i asanlasdirir.

## Spring-de istifadesi

### @RestController ile API yaratmaq

```java
import org.springframework.http.HttpStatus;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.*;
import jakarta.validation.Valid;
import java.net.URI;
import java.util.List;

@RestController
@RequestMapping("/api/v1/posts")
public class PostController {

    private final PostService postService;

    public PostController(PostService postService) {
        this.postService = postService;
    }

    @GetMapping
    public ResponseEntity<List<PostDto>> getAllPosts(
            @RequestParam(defaultValue = "0") int page,
            @RequestParam(defaultValue = "10") int size,
            @RequestParam(defaultValue = "createdAt") String sortBy) {

        Page<PostDto> posts = postService.getAllPosts(page, size, sortBy);

        return ResponseEntity.ok()
            .header("X-Total-Count", String.valueOf(posts.getTotalElements()))
            .body(posts.getContent());
    }

    @GetMapping("/{id}")
    public ResponseEntity<PostDto> getPost(@PathVariable Long id) {
        PostDto post = postService.getPostById(id);
        return ResponseEntity.ok(post);
    }

    @PostMapping
    public ResponseEntity<PostDto> createPost(@Valid @RequestBody CreatePostRequest request) {
        PostDto created = postService.createPost(request);
        URI location = URI.create("/api/v1/posts/" + created.getId());
        return ResponseEntity.created(location).body(created);
    }

    @PutMapping("/{id}")
    public ResponseEntity<PostDto> updatePost(
            @PathVariable Long id,
            @Valid @RequestBody UpdatePostRequest request) {
        PostDto updated = postService.updatePost(id, request);
        return ResponseEntity.ok(updated);
    }

    @DeleteMapping("/{id}")
    public ResponseEntity<Void> deletePost(@PathVariable Long id) {
        postService.deletePost(id);
        return ResponseEntity.noContent().build();
    }
}
```

### DTO ve Jackson serialization

Spring Jackson kutupxanesini istifade ederek Java obyektlerini avtomatik JSON-a cevirir:

```java
import com.fasterxml.jackson.annotation.JsonFormat;
import com.fasterxml.jackson.annotation.JsonInclude;
import com.fasterxml.jackson.annotation.JsonProperty;
import java.time.LocalDateTime;
import java.util.List;

@JsonInclude(JsonInclude.Include.NON_NULL)
public class PostDto {

    private Long id;
    private String title;
    private String content;

    @JsonProperty("author_name")
    private String authorName;

    @JsonFormat(pattern = "yyyy-MM-dd HH:mm:ss")
    private LocalDateTime createdAt;

    @JsonProperty("tags")
    private List<String> tagNames;

    // Getters, Setters, Constructor
    public PostDto() {}

    public PostDto(Long id, String title, String content,
                   String authorName, LocalDateTime createdAt) {
        this.id = id;
        this.title = title;
        this.content = content;
        this.authorName = authorName;
        this.createdAt = createdAt;
    }

    // Getters ve Setters...
}
```

```java
// Request DTO
import jakarta.validation.constraints.*;

public class CreatePostRequest {

    @NotBlank(message = "Bashliq bosh ola bilmez")
    @Size(max = 255, message = "Bashliq 255 simvoldan cox ola bilmez")
    private String title;

    @NotBlank(message = "Mezmun bosh ola bilmez")
    private String content;

    @NotNull(message = "Kateqoriya secilmelidir")
    private Long categoryId;

    private List<Long> tagIds;

    // Getters ve Setters
}
```

### ResponseEntity ile cavab nezareti

```java
@RestController
@RequestMapping("/api/v1/users")
public class UserController {

    @GetMapping("/{id}")
    public ResponseEntity<UserDto> getUser(@PathVariable Long id) {
        return userService.findById(id)
            .map(ResponseEntity::ok)
            .orElse(ResponseEntity.notFound().build());
    }

    @PostMapping
    public ResponseEntity<?> createUser(@Valid @RequestBody CreateUserRequest request) {
        if (userService.existsByEmail(request.getEmail())) {
            return ResponseEntity
                .status(HttpStatus.CONFLICT)
                .body(new ErrorResponse("Bu email artiq istifade olunur"));
        }

        UserDto user = userService.createUser(request);
        return ResponseEntity
            .status(HttpStatus.CREATED)
            .body(user);
    }
}
```

### Global Exception Handler

```java
import org.springframework.http.HttpStatus;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.MethodArgumentNotValidException;
import org.springframework.web.bind.annotation.ExceptionHandler;
import org.springframework.web.bind.annotation.RestControllerAdvice;
import java.time.LocalDateTime;
import java.util.Map;
import java.util.HashMap;

@RestControllerAdvice
public class GlobalExceptionHandler {

    @ExceptionHandler(ResourceNotFoundException.class)
    public ResponseEntity<ErrorResponse> handleNotFound(ResourceNotFoundException ex) {
        ErrorResponse error = new ErrorResponse(
            HttpStatus.NOT_FOUND.value(),
            ex.getMessage(),
            LocalDateTime.now()
        );
        return ResponseEntity.status(HttpStatus.NOT_FOUND).body(error);
    }

    @ExceptionHandler(MethodArgumentNotValidException.class)
    public ResponseEntity<Map<String, Object>> handleValidation(
            MethodArgumentNotValidException ex) {
        Map<String, String> errors = new HashMap<>();
        ex.getBindingResult().getFieldErrors().forEach(error ->
            errors.put(error.getField(), error.getDefaultMessage()));

        Map<String, Object> response = new HashMap<>();
        response.put("status", 422);
        response.put("errors", errors);
        response.put("message", "Validasiya xetasi");

        return ResponseEntity.unprocessableEntity().body(response);
    }
}
```

### API Versioning

```java
// URL ile versioning (en populyar)
@RestController
@RequestMapping("/api/v1/posts")
public class PostControllerV1 { }

@RestController
@RequestMapping("/api/v2/posts")
public class PostControllerV2 { }

// Header ile versioning
@GetMapping(value = "/posts", headers = "X-API-VERSION=1")
public List<PostDtoV1> getPostsV1() { }

@GetMapping(value = "/posts", headers = "X-API-VERSION=2")
public List<PostDtoV2> getPostsV2() { }

// Content-Type ile versioning
@GetMapping(value = "/posts", produces = "application/vnd.myapp.v1+json")
public List<PostDtoV1> getPostsV1() { }
```

### HATEOAS

Spring HATEOAS kutupxanesi ile API cavablarinda linkler elave etmek mumkundur:

```java
import org.springframework.hateoas.EntityModel;
import org.springframework.hateoas.CollectionModel;
import static org.springframework.hateoas.server.mvc.WebMvcLinkBuilder.*;

@RestController
@RequestMapping("/api/v1/posts")
public class PostHateoasController {

    @GetMapping("/{id}")
    public EntityModel<PostDto> getPost(@PathVariable Long id) {
        PostDto post = postService.getPostById(id);

        return EntityModel.of(post,
            linkTo(methodOn(PostHateoasController.class).getPost(id)).withSelfRel(),
            linkTo(methodOn(PostHateoasController.class).getAllPosts()).withRel("posts"),
            linkTo(methodOn(CommentController.class).getComments(id)).withRel("comments")
        );
    }
}
```

Cavab numunesi:
```json
{
    "id": 1,
    "title": "Spring Boot Giris",
    "content": "...",
    "_links": {
        "self": { "href": "/api/v1/posts/1" },
        "posts": { "href": "/api/v1/posts" },
        "comments": { "href": "/api/v1/posts/1/comments" }
    }
}
```

## Laravel-de istifadesi

### API Routing

```php
// routes/api.php
// Butun route-lar avtomatik /api prefiksi alir

use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\UserController;

Route::prefix('v1')->group(function () {
    // Public endpointler
    Route::get('/posts', [PostController::class, 'index']);
    Route::get('/posts/{post}', [PostController::class, 'show']);

    // Qorunan endpointler
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/posts', [PostController::class, 'store']);
        Route::put('/posts/{post}', [PostController::class, 'update']);
        Route::delete('/posts/{post}', [PostController::class, 'destroy']);

        Route::apiResource('/users', UserController::class);
    });
});
```

### API Controller

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePostRequest;
use App\Http\Requests\UpdatePostRequest;
use App\Http\Resources\PostResource;
use App\Http\Resources\PostCollection;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PostController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $posts = Post::with(['user', 'tags'])
            ->when($request->search, function ($query, $search) {
                $query->where('title', 'like', "%{$search}%");
            })
            ->when($request->category_id, function ($query, $categoryId) {
                $query->where('category_id', $categoryId);
            })
            ->orderBy($request->sort_by ?? 'created_at', $request->order ?? 'desc')
            ->paginate($request->per_page ?? 15);

        return PostResource::collection($posts);
    }

    public function show(Post $post): PostResource
    {
        $post->load(['user', 'comments.user', 'tags']);
        return new PostResource($post);
    }

    public function store(StorePostRequest $request): JsonResponse
    {
        $post = $request->user()->posts()->create($request->validated());

        if ($request->has('tag_ids')) {
            $post->tags()->attach($request->tag_ids);
        }

        return (new PostResource($post->load('user', 'tags')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdatePostRequest $request, Post $post): PostResource
    {
        $post->update($request->validated());

        if ($request->has('tag_ids')) {
            $post->tags()->sync($request->tag_ids);
        }

        return new PostResource($post->load('user', 'tags'));
    }

    public function destroy(Post $post): JsonResponse
    {
        $this->authorize('delete', $post);
        $post->delete();

        return response()->json(null, 204);
    }
}
```

### API Resources (JsonResource)

API Resources modeli JSON formatina cevirmek ucun istifade olunur:

```php
<?php
// app/Http/Resources/PostResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'content' => $this->content,
            'excerpt' => str($this->content)->limit(200),
            'author' => new UserResource($this->whenLoaded('user')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'comments_count' => $this->whenCounted('comments'),
            'is_published' => $this->published_at !== null,
            'published_at' => $this->published_at?->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),

            // Sertli saheler
            'edit_url' => $this->when(
                $request->user()?->id === $this->user_id,
                route('posts.edit', $this->id)
            ),
        ];
    }

    public function with(Request $request): array
    {
        return [
            'meta' => [
                'api_version' => 'v1',
            ],
        ];
    }
}
```

```php
<?php
// app/Http/Resources/UserResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->when($request->user()?->id === $this->id, $this->email),
            'posts_count' => $this->whenCounted('posts'),
            'avatar_url' => $this->avatar_url,
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
```

### Form Request ile Validation

```php
<?php
// app/Http/Requests/StorePostRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'category_id' => 'required|exists:categories,id',
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'exists:tags,id',
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Bashliq bosh ola bilmez',
            'content.required' => 'Mezmun bosh ola bilmez',
            'category_id.exists' => 'Secilen kateqoriya movcud deyil',
        ];
    }
}
```

### Error handling

```php
// app/Exceptions/Handler.php ve ya bootstrap/app.php (Laravel 11+)
use Illuminate\Foundation\Configuration\Exceptions;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

->withExceptions(function (Exceptions $exceptions) {
    $exceptions->render(function (NotFoundHttpException $e) {
        return response()->json([
            'status' => 404,
            'message' => 'Resurs tapilmadi',
        ], 404);
    });
})
```

### response() helperleri

```php
// Sade JSON cavab
return response()->json(['message' => 'Ugurlu'], 200);

// Custom headerler
return response()->json($data)
    ->header('X-Custom-Header', 'value')
    ->header('X-Request-Id', uniqid());

// Download
return response()->download($filePath, 'filename.pdf');

// Stream
return response()->stream(function () {
    // melumat gondermek
}, 200, ['Content-Type' => 'text/event-stream']);
```

## Esas ferqler

| Xususiyyet | Spring | Laravel |
|---|---|---|
| **Controller annotasiyasi** | `@RestController` | Normal controller, `response()->json()` ve ya Resource |
| **Routing** | `@GetMapping`, `@PostMapping` annotasiyalari | `Route::get()`, `Route::post()` metodlari |
| **Request body** | `@RequestBody` + DTO sinifi | `Request` sinifi, `$request->input()` |
| **Response** | `ResponseEntity<T>` ile tip-tehlukesiz | `response()->json()`, `JsonResource` |
| **Serialization** | Jackson (avtomatik) | API Resources (manual mapping) |
| **Validation** | `@Valid` + DTO annotasiyalari | Form Request sinifi |
| **API Resource** | Manuel DTO yaratmaq lazim | `JsonResource` sinfi ile hazir pattern |
| **Pagination** | `Page<T>` + `Pageable` | `->paginate()` + avtomatik JSON formati |
| **HATEOAS** | Spring HATEOAS kutupxanesi | Yoxdur (manual yaratmaq lazim) |
| **API versioning** | URL, header, content-type | URL (route group ile) |
| **Exception handling** | `@RestControllerAdvice` | `withExceptions()` ve ya Handler sinifi |

## Niye bele ferqler var?

### Tip tehlukesizliyi meselesi

Spring-de `ResponseEntity<PostDto>` yazdiqda, compile-time-da hansi tipin qaytarilacagi melumdur. Bu IDE destek, refactoring ve sehv tutma baximindan boyuk ustunlukdur. DTO sinfleri yaratmaq elave is olsa da, boyuk proyektlerde nizam-intizam yaradir.

Laravel-de ise API Resource daha cevikdir. `$this->when()`, `$this->whenLoaded()` kimi metodlarla sertli saheleri asanliqla idare etmek olur. PHP-nin dinamik tebietine uygun olaraq, daha az kod ile daha cox is gorulur.

### Jackson vs API Resources

Jackson avtomatik isleyir - Java obyektini JSON-a cevirir, hecsne yazmag lazim deyil. Amma hansi sahelerin gorunduyunu nezaret etmek ucun `@JsonIgnore`, `@JsonProperty` kimi annotasiyalar lazimdir.

Laravel API Resources ise her seyi manual yazirsan. Bu daha cox is demel olsa da, cavab strukturunu tam nezaret altinda saxlayir. Bir model ferqli endpointlerde ferqli formatlarda gostermek asandir.

### HATEOAS felsefesi

HATEOAS (Hypermedia as the Engine of Application State) REST-in yetkinlik seviyyelerinden biridir. Spring bunu daxili kutupxane ile destekleyir. Laravel ekosisteminde ise HATEOAS nadir istifade olunur - cogu Laravel API sadece JSON qaytarir. Bu Java ekosisteminin enterprise standartlara daha cox oncelik vermesinden ireli gelir.

## Hansi framework-de var, hansinda yoxdur?

### Yalniz Spring-de (ve ya daha asandir):
- **HATEOAS** - Daxili kutupxane ile cavablarda linkler
- **Content negotiation** - Eyni endpoint JSON, XML ve s. formatlarda cavab vere biler
- **`ResponseEntity`** - HTTP status, header ve body-ni bir yerde idare etmek
- **Jackson annotasiyalari** - `@JsonIgnore`, `@JsonProperty`, `@JsonFormat` ile ince nezaret
- **Header-based API versioning** - Annotasiya ile asanliqla

### Yalniz Laravel-de (ve ya daha asandir):
- **API Resources** - `JsonResource` ile hazir transformation pattern
- **`whenLoaded()`** - Yalniz eager load olunmus elaqeleri gostermek
- **`apiResource` routing** - Bir satirla butun CRUD route-larini yaratmaq
- **Pagination avtomatik formati** - `paginate()` ederken JSON-da `data`, `links`, `meta` strukturu hazir gelir
- **Route model binding** - URL-deki ID-ye gore modeli avtomatik tapmaq (`Post $post`)
- **`response()->download()`**, **`response()->stream()`** - Hazir helper-ler
