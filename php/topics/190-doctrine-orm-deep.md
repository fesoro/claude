# Doctrine ORM — Deep Dive

## Mündəricat
1. [Doctrine vs Eloquent](#doctrine-vs-eloquent)
2. [Data Mapper pattern](#data-mapper-pattern)
3. [EntityManager + Unit of Work](#entitymanager--unit-of-work)
4. [Entity, mapping (attributes/XML/YAML)](#entity-mapping)
5. [Identity Map](#identity-map)
6. [Lazy loading / Proxy](#lazy-loading--proxy)
7. [DQL & Query Builder](#dql--query-builder)
8. [Lifecycle events](#lifecycle-events)
9. [Inheritance strategies](#inheritance-strategies)
10. [Performance optimization](#performance-optimization)
11. [İntervyu Sualları](#intervyu-sualları)

---

## Doctrine vs Eloquent

```
                     | Doctrine                | Eloquent
─────────────────────────────────────────────────────────────────
Pattern             | Data Mapper             | Active Record
Entity              | POPO (plain object)     | Extends Model
Persistence         | EntityManager.persist() | $model->save()
Identity tracking   | Identity Map            | None (always fresh fetch)
Mapping             | Attributes/XML/YAML     | Schema convention
Migrations          | Doctrine Migrations     | Laravel Migrations
Relationships       | Bi-directional explicit | Mostly uni-directional
Lazy loading        | Proxy class             | Magic __get
Framework           | Symfony default         | Laravel default
Learning curve      | Steep                   | Easy
Best for            | Domain models, DDD      | Rapid CRUD, simple apps
```

```php
<?php
// Doctrine — Entity is POPO
class User
{
    private int $id;
    private string $name;
    private string $email;
    
    public function __construct(string $name, string $email) { /* ... */ }
    public function getId(): int { return $this->id; }
    public function getName(): string { return $this->name; }
}

// Eloquent — Model extends framework
class User extends Model
{
    protected $fillable = ['name', 'email'];
    // Magic property access: $user->name
}

// Doctrine — explicit persist
$user = new User('Ali', 'a@b.com');
$em->persist($user);
$em->flush();

// Eloquent — fluent
User::create(['name' => 'Ali', 'email' => 'a@b.com']);
```

---

## Data Mapper pattern

```
Active Record (Eloquent):
  Model "I'm a row in DB, I can save myself"
  $user->save() — model özü DB-yə yazır
  Pros: sadə, fast development
  Cons: domain logic + persistence qarışıqdır

Data Mapper (Doctrine):
  Entity sadə obyektdir, "I don't know about DB"
  EntityManager DB-yə yazır
  Pros: pure domain logic, testable, framework-independent
  Cons: more boilerplate

DDD ilə uyğunluq:
  Doctrine — DDD aggregate + repository pattern üçün ideal
  Eloquent — domain model qarışıq olur ("Active Record anemic model")
```

---

## EntityManager + Unit of Work

```
EntityManager — Doctrine-in mərkəzi sinfi.
  - Entity-ləri persist/remove edir
  - Unit of Work daxili olaraq idarə edir
  - Transaction commit zamanı bütün dəyişiklikləri SQL-ə çevirir

Unit of Work (Martin Fowler):
  "Bir biznes əməliyyatı zamanı dəyişən bütün obyektləri yığır,
   transaction sonu bir dəfəyə DB-yə yazır"

Workflow:
  1. $user = $em->find(User::class, 1)        // fetch + UoW-a əlavə
  2. $user->setName('Yeni ad')                 // dirty (UoW dəyişikliyi izləyir)
  3. $newPost = new Post()
  4. $em->persist($newPost)                    // managed (UoW-a əlavə)
  5. $em->flush()                              // BÜTÜN dəyişikliklər DB-yə
                                                //   UPDATE users + INSERT post
                                                //   bir transaction-da

Entity states:
  NEW         — yenidir, UoW-də yox
  MANAGED     — UoW izləyir
  DETACHED    — UoW-dən çıxarılıb (clear, detach)
  REMOVED     — silinmək üçün işarələnib
```

```php
<?php
// EntityManager istifadəsi
use Doctrine\ORM\EntityManager;

$em = $entityManagerFactory->create();

// Find
$user = $em->find(User::class, 1);
$user = $em->getRepository(User::class)->find(1);

// Persist (new)
$user = new User('Ali', 'a@b.com');
$em->persist($user);
$em->flush();
echo $user->getId();   // ID set olundu (auto-increment)

// Update
$user = $em->find(User::class, 1);
$user->setName('Yeni');
$em->flush();          // UPDATE users SET name = 'Yeni' WHERE id = 1

// Delete
$em->remove($user);
$em->flush();

// Bulk operation — UoW təmizlə (memory leak qarşı)
foreach ($items as $i => $data) {
    $em->persist(new Item($data));
    if ($i % 100 === 0) {
        $em->flush();
        $em->clear();   // UoW reset, GC işləyə bilər
    }
}
$em->flush();
$em->clear();
```

---

## Entity, mapping

```php
<?php
// PHP 8 attribute mapping (modern, recommended)
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[ORM\Index(columns: ['email'], name: 'idx_email')]
class User
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;
    
    #[ORM\Column(type: 'string', length: 255)]
    private string $name;
    
    #[ORM\Column(type: 'string', length: 255, unique: true)]
    private string $email;
    
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;
    
    #[ORM\OneToMany(targetEntity: Post::class, mappedBy: 'author', cascade: ['persist'])]
    private Collection $posts;
    
    #[ORM\ManyToMany(targetEntity: Role::class)]
    #[ORM\JoinTable(name: 'user_roles')]
    private Collection $roles;
    
    public function __construct(string $name, string $email)
    {
        $this->name = $name;
        $this->email = $email;
        $this->createdAt = new \DateTimeImmutable();
        $this->posts = new ArrayCollection();
        $this->roles = new ArrayCollection();
    }
    
    // Getters/setters...
}

#[ORM\Entity]
class Post
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;
    
    #[ORM\Column(type: 'string')]
    private string $title;
    
    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'posts')]
    #[ORM\JoinColumn(nullable: false)]
    private User $author;
}
```

```yaml
# Alternative: YAML mapping
App\Entity\User:
  type: entity
  table: users
  id:
    id:
      type: integer
      generator:
        strategy: AUTO
  fields:
    name:
      type: string
      length: 255
    email:
      type: string
      length: 255
      unique: true
```

---

## Identity Map

```
Identity Map — UoW içərisində "tək instance" qaranti.
"Bir transaction-da eyni ID-li entity → eyni PHP obyekt"

$user1 = $em->find(User::class, 1);
$user2 = $em->find(User::class, 1);

$user1 === $user2    // true! eyni obyekt

Niyə vacibdir?
  ✓ Eyni entity-də dəyişiklik bir yerdə cəm olur
  ✓ Concurrent update qarşısı
  ✓ Memory efficient

Eloquent-də YOXDUR:
  $u1 = User::find(1);
  $u2 = User::find(1);
  $u1 === $u2          // false, fərqli obyektlər
```

---

## Lazy loading / Proxy

```php
<?php
// $user->getPosts() çağırılana qədər posts SQL-ə getmir
$user = $em->find(User::class, 1);          // SELECT * FROM users WHERE id=1
$posts = $user->getPosts();                  // hələ də heç nə
foreach ($posts as $post) {                  // INDI: SELECT * FROM posts WHERE author_id=1
    echo $post->getTitle();
}

// Lazy = Proxy class (Doctrine generate edir)
get_class($user->getOrders()->first());
// → Proxies\__CG__\App\Entity\Order
// Proxy class lazy-load üçün

// EAGER loading (N+1 qarşı)
$users = $em->createQueryBuilder()
    ->select('u', 'p')
    ->from(User::class, 'u')
    ->leftJoin('u.posts', 'p')
    ->getQuery()
    ->getResult();
// 1 query — JOIN ilə posts da yüklənir

// Fetch=EAGER attribute-da
#[ORM\OneToMany(targetEntity: Post::class, mappedBy: 'author', fetch: 'EAGER')]
private Collection $posts;
```

---

## DQL & Query Builder

```php
<?php
// DQL — Doctrine Query Language (SQL kimi, amma entity-yə)
$query = $em->createQuery(
    'SELECT u, COUNT(p.id) as post_count
     FROM App\Entity\User u
     LEFT JOIN u.posts p
     WHERE u.email LIKE :email
     GROUP BY u.id
     HAVING COUNT(p.id) > :min_posts
     ORDER BY post_count DESC'
);
$query->setParameter('email', '%@example.com');
$query->setParameter('min_posts', 5);
$results = $query->getResult();

// Query Builder — DSL
$qb = $em->createQueryBuilder();
$qb->select('u')
   ->from(User::class, 'u')
   ->where($qb->expr()->in('u.id', [1, 2, 3]))
   ->andWhere('u.active = :active')
   ->setParameter('active', true)
   ->orderBy('u.createdAt', 'DESC')
   ->setMaxResults(20)
   ->setFirstResult(0);

$users = $qb->getQuery()->getResult();

// Native SQL (rare)
$rsm = new \Doctrine\ORM\Query\ResultSetMapping();
$rsm->addEntityResult(User::class, 'u');
$rsm->addFieldResult('u', 'id', 'id');
$rsm->addFieldResult('u', 'name', 'name');

$query = $em->createNativeQuery(
    'SELECT u.id, u.name FROM users u WHERE u.id = ?',
    $rsm
);
$query->setParameter(1, 1);
$user = $query->getOneOrNullResult();
```

---

## Lifecycle events

```php
<?php
#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
class User
{
    // ...
    
    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }
    
    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
    
    #[ORM\PostLoad]
    public function onPostLoad(): void
    {
        $this->loadedAt = new \DateTimeImmutable();
    }
    
    // Digər event-lər:
    // PrePersist, PostPersist
    // PreUpdate, PostUpdate
    // PreRemove, PostRemove
    // PostLoad
}

// Event Subscriber (cross-entity)
class TimestampSubscriber implements EventSubscriber
{
    public function getSubscribedEvents(): array
    {
        return [Events::prePersist, Events::preUpdate];
    }
    
    public function prePersist(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();
        if (method_exists($entity, 'setCreatedAt')) {
            $entity->setCreatedAt(new \DateTimeImmutable());
        }
    }
}
```

---

## Inheritance strategies

```php
<?php
// 1. SINGLE_TABLE (default) — bir cədvəl, discriminator column
#[ORM\Entity]
#[ORM\InheritanceType('SINGLE_TABLE')]
#[ORM\DiscriminatorColumn(name: 'type', type: 'string')]
#[ORM\DiscriminatorMap(['user' => User::class, 'admin' => Admin::class])]
abstract class Person { /* ... */ }

#[ORM\Entity]
class User extends Person { }

#[ORM\Entity]
class Admin extends Person {
    #[ORM\Column]
    private bool $superAdmin;
}

// SQL:
// persons (id, type, name, super_admin) — bir cədvəl, type fərq

// 2. JOINED — hər class öz cədvəlində, JOIN
#[ORM\InheritanceType('JOINED')]
abstract class Person { }

// SQL:
// persons (id, name)
// admins  (id, super_admin) — FK persons.id
// SELECT JOIN ilə birləşdirilir

// 3. TABLE_PER_CLASS — hər concrete class öz cədvəlində, parent yox
#[ORM\InheritanceType('TABLE_PER_CLASS')]
abstract class Person { }

// users  (id, name)
// admins (id, name, super_admin) — duplicate columns
```

---

## Performance optimization

```
1. EAGER loading N+1 üçün
   ->leftJoin('u.posts', 'p')->select('u', 'p')

2. Partial objects (yalnız lazım olan field)
   $em->createQueryBuilder()
      ->select('partial u.{id, name}')
      ->from(User::class, 'u')

3. DTO projection
   ->select('NEW App\DTO\UserDTO(u.id, u.name)')
   // Hydration entity yox, DTO yaradır — sürətli

4. Read-only mode
   $em->getConfiguration()->setSecondLevelCacheEnabled(true);
   // Identity map skip — sadə oxuma üçün

5. Batch processing — clear UoW
   if ($i % 100 === 0) { $em->flush(); $em->clear(); }

6. Second-level cache (PSR-6 / APCu / Redis)
   #[ORM\Cache(usage: 'NONSTRICT_READ_WRITE')]
   class User { }

7. SQL logging dev-də (production-da OFF)
   // doctrine.yaml
   // logging: false

8. Disable proxy generation in prod
   // metadata cache + query cache
```

---

## İntervyu Sualları

- Doctrine və Eloquent arasındakı arxitekturalı fərq?
- Identity Map nədir, niyə vacibdir?
- Unit of Work pattern necə işləyir?
- `persist()` ilə `flush()` fərqi?
- Lazy loading Doctrine-də necə işləyir? Proxy class nədir?
- DQL ilə SQL fərqi? Niyə Doctrine native SQL-i tövsiyə etmir?
- N+1 problemi Doctrine-də necə həll olunur?
- Entity inheritance — SINGLE_TABLE vs JOINED — nə vaxt hansı?
- Bulk insert üçün `$em->clear()` niyə vacibdir?
- DTO projection nədir, nə vaxt istifadə olunur?
- Lifecycle event-lər (`PrePersist` vs `PreUpdate`) nə zaman?
- Doctrine ilə DDD aggregate niyə yaxşı uyğunlaşır?
