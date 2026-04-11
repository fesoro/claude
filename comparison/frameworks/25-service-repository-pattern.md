# Service ve Repository Pattern

## Giris

Service ve Repository pattern-leri tetbiq kodunu qatlar (layers) seklinde tesbiq etmek ucun istifade olunur. Bu ayirma kodu daha oxunaqli, test edilebilir ve baximli edir. Spring-de `@Service` ve `@Repository` annotasiyalari framework-un ozul hissesidir, Laravel-de ise bu pattern-ler built-in deyil, amma teleb olunduqda manual olaraq tetbiq olunur.

## Spring-de istifadesi

### Qatli arxitektura (Layered Architecture)

```
Controller -> Service -> Repository -> Database
    ^            ^           ^
    |            |           |
  @Controller  @Service  @Repository
  HTTP        Business    Data Access
  Layer       Logic       Layer
```

### @Repository - Melumat erisi qati

```java
// Spring Data JPA ile - interface yaziriq, implementasiyanl Spring ozue yaradir
@Repository
public interface UserRepository extends JpaRepository<User, Long> {

    // Method naming convention ile sorgu yaranir
    Optional<User> findByEmail(String email);

    List<User> findByActiveTrue();

    List<User> findByNameContainingIgnoreCase(String name);

    boolean existsByEmail(String email);

    @Query("SELECT u FROM User u WHERE u.createdAt > :date")
    List<User> findRecentUsers(@Param("date") LocalDateTime date);

    @Query("SELECT u FROM User u JOIN u.roles r WHERE r.name = :roleName")
    List<User> findByRoleName(@Param("roleName") String roleName);

    @Modifying
    @Query("UPDATE User u SET u.active = false WHERE u.lastLoginAt < :date")
    int deactivateInactiveUsers(@Param("date") LocalDateTime date);
}
```

```java
// Xususi sorgu ucun custom repository
public interface UserRepositoryCustom {
    List<User> searchUsers(UserSearchCriteria criteria);
}

@Repository
public class UserRepositoryCustomImpl implements UserRepositoryCustom {

    private final EntityManager entityManager;

    public UserRepositoryCustomImpl(EntityManager entityManager) {
        this.entityManager = entityManager;
    }

    @Override
    public List<User> searchUsers(UserSearchCriteria criteria) {
        CriteriaBuilder cb = entityManager.getCriteriaBuilder();
        CriteriaQuery<User> query = cb.createQuery(User.class);
        Root<User> root = query.from(User.class);

        List<Predicate> predicates = new ArrayList<>();

        if (criteria.getName() != null) {
            predicates.add(cb.like(
                cb.lower(root.get("name")),
                "%" + criteria.getName().toLowerCase() + "%"
            ));
        }

        if (criteria.getActive() != null) {
            predicates.add(cb.equal(root.get("active"), criteria.getActive()));
        }

        query.where(predicates.toArray(new Predicate[0]));
        return entityManager.createQuery(query).getResultList();
    }
}

// Birlesdirilmis interface
@Repository
public interface UserRepository
    extends JpaRepository<User, Long>, UserRepositoryCustom {
}
```

### @Service - Business logic qati

```java
@Service
public class UserService {

    private final UserRepository userRepository;
    private final PasswordEncoder passwordEncoder;
    private final EmailService emailService;

    public UserService(UserRepository userRepository,
                       PasswordEncoder passwordEncoder,
                       EmailService emailService) {
        this.userRepository = userRepository;
        this.passwordEncoder = passwordEncoder;
        this.emailService = emailService;
    }

    @Transactional
    public User createUser(CreateUserDto dto) {
        // Business qaydasi: email unikal olmalidir
        if (userRepository.existsByEmail(dto.getEmail())) {
            throw new DuplicateEmailException(dto.getEmail());
        }

        // Entity yaratma
        User user = new User();
        user.setName(dto.getName());
        user.setEmail(dto.getEmail());
        user.setPassword(passwordEncoder.encode(dto.getPassword()));
        user.setActive(false);
        user.setActivationToken(UUID.randomUUID().toString());

        User savedUser = userRepository.save(user);

        // Yan effekt: aktivlesdirme e-poctu gonder
        emailService.sendActivationEmail(savedUser);

        return savedUser;
    }

    @Transactional(readOnly = true)
    public User getUserById(Long id) {
        return userRepository.findById(id)
            .orElseThrow(() -> new UserNotFoundException(id));
    }

    @Transactional(readOnly = true)
    public Page<User> searchUsers(UserSearchCriteria criteria, Pageable pageable) {
        return userRepository.findAll(
            UserSpecification.byCriteria(criteria), pageable);
    }

    @Transactional
    public User updateUser(Long id, UpdateUserDto dto) {
        User user = getUserById(id);

        if (dto.getName() != null) {
            user.setName(dto.getName());
        }

        if (dto.getEmail() != null && !dto.getEmail().equals(user.getEmail())) {
            if (userRepository.existsByEmail(dto.getEmail())) {
                throw new DuplicateEmailException(dto.getEmail());
            }
            user.setEmail(dto.getEmail());
        }

        return userRepository.save(user);
    }

    @Transactional
    public void deleteUser(Long id) {
        User user = getUserById(id);
        userRepository.delete(user);
    }
}
```

### Controller qati

```java
@RestController
@RequestMapping("/api/users")
public class UserController {

    private final UserService userService;

    public UserController(UserService userService) {
        this.userService = userService;
    }

    @PostMapping
    public ResponseEntity<UserResponse> createUser(
            @Valid @RequestBody CreateUserDto dto) {
        User user = userService.createUser(dto);
        return ResponseEntity.status(201).body(UserResponse.from(user));
    }

    @GetMapping("/{id}")
    public UserResponse getUser(@PathVariable Long id) {
        return UserResponse.from(userService.getUserById(id));
    }

    @PutMapping("/{id}")
    public UserResponse updateUser(@PathVariable Long id,
                                    @Valid @RequestBody UpdateUserDto dto) {
        return UserResponse.from(userService.updateUser(id, dto));
    }

    @DeleteMapping("/{id}")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    public void deleteUser(@PathVariable Long id) {
        userService.deleteUser(id);
    }
}
```

### DAO pattern (kohne yanasma)

```java
// DAO - Repository-den evvel istifade olunan pattern
// Spring Data JPA ile artiq az istifade olunur

public interface UserDao {
    User findById(Long id);
    List<User> findAll();
    User save(User user);
    void delete(Long id);
}

@Repository
public class UserDaoImpl implements UserDao {

    private final JdbcTemplate jdbcTemplate;

    public UserDaoImpl(JdbcTemplate jdbcTemplate) {
        this.jdbcTemplate = jdbcTemplate;
    }

    @Override
    public User findById(Long id) {
        return jdbcTemplate.queryForObject(
            "SELECT * FROM users WHERE id = ?",
            new UserRowMapper(), id);
    }

    @Override
    public List<User> findAll() {
        return jdbcTemplate.query(
            "SELECT * FROM users", new UserRowMapper());
    }

    @Override
    public User save(User user) {
        if (user.getId() == null) {
            jdbcTemplate.update(
                "INSERT INTO users (name, email) VALUES (?, ?)",
                user.getName(), user.getEmail());
        } else {
            jdbcTemplate.update(
                "UPDATE users SET name = ?, email = ? WHERE id = ?",
                user.getName(), user.getEmail(), user.getId());
        }
        return user;
    }

    @Override
    public void delete(Long id) {
        jdbcTemplate.update("DELETE FROM users WHERE id = ?", id);
    }
}
```

## Laravel-de istifadesi

### Sade yanasma - Service olmadan (kicik layihelerde)

Laravel-de kicik layihelerde Controller birbaşa Eloquent Model ile isleye biler:

```php
// Kicik layihelerde - Controller birbaşa Model ile isleyir
class UserController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        return response()->json($user, 201);
    }
}
```

### Service sinfi yaratmaq (orta/boyuk layihelerde)

```php
// app/Services/UserService.php
class UserService
{
    public function createUser(array $data): User
    {
        // Business qaydasi
        if (User::where('email', $data['email'])->exists()) {
            throw new DuplicateEmailException($data['email']);
        }

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'active' => false,
            'activation_token' => Str::uuid(),
        ]);

        // Yan effekt
        Mail::to($user)->send(new ActivationMail($user));

        return $user;
    }

    public function updateUser(User $user, array $data): User
    {
        if (isset($data['email']) && $data['email'] !== $user->email) {
            if (User::where('email', $data['email'])->exists()) {
                throw new DuplicateEmailException($data['email']);
            }
        }

        $user->update($data);
        return $user->fresh();
    }

    public function deleteUser(User $user): void
    {
        DB::transaction(function () use ($user) {
            $user->orders()->delete();
            $user->notifications()->delete();
            $user->delete();
        });
    }
}
```

```php
// Controller service-i istifade edir
class UserController extends Controller
{
    public function __construct(
        private UserService $userService
    ) {}

    public function store(CreateUserRequest $request)
    {
        $user = $this->userService->createUser($request->validated());
        return new UserResource($user);
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $user = $this->userService->updateUser($user, $request->validated());
        return new UserResource($user);
    }

    public function destroy(User $user)
    {
        $this->userService->deleteUser($user);
        return response()->noContent();
    }
}
```

### Repository pattern (manual implementasiya)

Laravel-de Repository pattern built-in deyil, amma manual tetbiq oluna biler:

```php
// app/Repositories/Contracts/UserRepositoryInterface.php
interface UserRepositoryInterface
{
    public function findById(int $id): ?User;
    public function findByEmail(string $email): ?User;
    public function getActive(): Collection;
    public function create(array $data): User;
    public function update(User $user, array $data): User;
    public function delete(User $user): bool;
    public function search(array $criteria): Collection;
}
```

```php
// app/Repositories/EloquentUserRepository.php
class EloquentUserRepository implements UserRepositoryInterface
{
    public function findById(int $id): ?User
    {
        return User::find($id);
    }

    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    public function getActive(): Collection
    {
        return User::where('active', true)->get();
    }

    public function create(array $data): User
    {
        return User::create($data);
    }

    public function update(User $user, array $data): User
    {
        $user->update($data);
        return $user;
    }

    public function delete(User $user): bool
    {
        return $user->delete();
    }

    public function search(array $criteria): Collection
    {
        $query = User::query();

        if (isset($criteria['name'])) {
            $query->where('name', 'like', '%' . $criteria['name'] . '%');
        }

        if (isset($criteria['active'])) {
            $query->where('active', $criteria['active']);
        }

        if (isset($criteria['role'])) {
            $query->whereHas('roles', fn ($q) =>
                $q->where('name', $criteria['role']));
        }

        return $query->get();
    }
}
```

```php
// Service provider-da bind etmek
// app/Providers/RepositoryServiceProvider.php
class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            UserRepositoryInterface::class,
            EloquentUserRepository::class
        );

        $this->app->bind(
            OrderRepositoryInterface::class,
            EloquentOrderRepository::class
        );
    }
}
```

```php
// Service repository-ni istifade edir
class UserService
{
    public function __construct(
        private UserRepositoryInterface $users
    ) {}

    public function createUser(array $data): User
    {
        if ($this->users->findByEmail($data['email'])) {
            throw new DuplicateEmailException($data['email']);
        }

        $data['password'] = Hash::make($data['password']);
        $data['activation_token'] = Str::uuid();

        return $this->users->create($data);
    }
}
```

### Ne vaxt Repository pattern istifade etmeli?

```php
// LAZIM DEYIL - kicik/orta layihelerde Eloquent ozue repository-dir
// User::find($id), User::where(...)->get() kifayet edir

// LAZIMDIR - bele hallarda:

// 1. Test zamani DB-den asili olmamaliyiq
class UserServiceTest extends TestCase
{
    public function test_create_user_checks_duplicate_email(): void
    {
        $mockRepo = $this->createMock(UserRepositoryInterface::class);
        $mockRepo->method('findByEmail')
            ->with('test@example.com')
            ->willReturn(new User()); // Movcud istifadeci

        $service = new UserService($mockRepo);

        $this->expectException(DuplicateEmailException::class);
        $service->createUser(['email' => 'test@example.com']);
    }
}

// 2. Gelecekde database deyise biler (Eloquent -> API, Eloquent -> MongoDB)
// 3. Murakkeb sorgu metiqi var ve tekarlanma var
// 4. Boyuk komandada isleyirik ve aydiin sinirllar lazimdir
```

### Action pattern (alternativ)

```php
// Tek mesuliyyetli sinif - Service-den daha ince
// app/Actions/CreateUserAction.php
class CreateUserAction
{
    public function __construct(
        private UserService $userService
    ) {}

    public function execute(CreateUserDto $dto): User
    {
        return DB::transaction(function () use ($dto) {
            $user = User::create([
                'name' => $dto->name,
                'email' => $dto->email,
                'password' => Hash::make($dto->password),
            ]);

            event(new UserCreated($user));

            return $user;
        });
    }
}

// Controller-de istifade
class UserController extends Controller
{
    public function store(CreateUserRequest $request, CreateUserAction $action)
    {
        $dto = CreateUserDto::from($request->validated());
        $user = $action->execute($dto);
        return new UserResource($user);
    }
}
```

## Esas ferqler

| Xususiyyet | Spring | Laravel |
|---|---|---|
| **Repository** | Built-in (`@Repository`, Spring Data) | Manual implementasiya |
| **Service** | `@Service` annotasiyasi | Adi PHP sinfi |
| **Qatli arxitektura** | Framework terefinden tetbiq olunur | Konvensiya ile (istege bagli) |
| **Interface yaratma** | Spring Data avtomatik impl yaradir | Manual implementasiya |
| **Sorgu yaratma** | Method naming convention | Eloquent query builder |
| **DAO pattern** | `JdbcTemplate` ile | Eloquent ozue DAO-dur |
| **DI qeydiyyati** | Avtomatik (component scan) | Service Provider-da bind |
| **Action pattern** | Yoxdur (service istifade olunur) | Populyar alternativ |

## Niye bele ferqler var?

**Spring-in yanasmasi:** Spring enterprise Java dunyasindan gelir, harada qatli arxitektura (layered architecture) standartdir. `@Controller`, `@Service`, `@Repository` annotasiyalari her qatin mesuliyyetini aciq sekilde mueyyen edir. Spring Data JPA interface-den avtomatik implementasiya yaradir ki, bu inqilabi bir xususiyyetdir - melumat erisi ucun hec bir kod yazmaq lazim deyil, sadece metod adlari kifayetdir.

**Laravel-in yanasmasi:** Laravel pragmatik yanasma tutir - "lazim olduqda istifade et". Eloquent ORM ozue Active Record pattern-dir ve hem model, hem de repository rolunu oynayir. Kicik layihelerde Controller birbaşa Model ile isleye biler, orta layihelerde Service elave olunur, boyuk layihelerde Repository pattern manual tetbiq olunur. Bu, layihenin boyukluyune gore arxitekturani secmek esnekliyini verir.

**Active Record vs Repository:** Eloquent Active Record pattern-dir - model ozue DB emeliyyatlarini bilir (`User::find()`, `$user->save()`). Spring Data JPA Repository pattern-dir - model (Entity) yalniz melumat dasiyir, emeliyyatlar Repository-dedir. Her iki yanasman artilari ve eksikleri var, amma bu felsefe ferqi framework-lerin butun dizaynina tesir edir.

## Hansi framework-de var, hansinda yoxdur?

- **Spring Data JPA** - Yalniz Spring-de. Interface-den avtomatik repository implementasiyasi yaradan sistem.
- **Method naming convention** - Yalniz Spring-de. `findByEmailAndActiveTrueOrderByNameAsc()` kimi metod adlari avtomatik SQL sorgularina cevrilir.
- **`@Repository` exception translation** - Spring `@Repository` annotasiyasi DB exceptionlarini Spring-in oz exception iyerarxiyasina cevirir.
- **Eloquent Active Record** - Yalniz Laravel-de. Model ozue sorgu qura bilir, save/delete ede bilir.
- **Action pattern** - Laravel ekosisteminde populyardir. Spring-de bu cur pattern az istifade olunur, cunki Service qati artiq movcuddur.
- **`@Query` annotasiyasi** - Spring-de JPQL/SQL-i birbaşa repository metoduna yazma imkani.
- **Eloquent Scopes** - Laravel-de tekarlanabilen sorgu sertlerini model daxilinde tanimlamaq mumkundur (`scopeActive()`, `scopeRecent()`). Spring-de oxsar funksionalliq Specification pattern ile edilir.
