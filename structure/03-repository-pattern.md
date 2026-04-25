# Repository Pattern (Middle)

Repository Pattern domain ilə data mapping layer-ləri arasında vasitəçilik edir.
Domain obyektlərinə müraciət üçün collection kimi interface verir və data mənbəyini abstract edir.

**Əsas anlayışlar:**
- **Repository Interface** — Kontrakt təyin edir (domain layer-də)
- **Repository Implementation** — Konkret data access (infrastructure layer-də)
- **Specification** — Query kriteriyalarını kapsullaşdırır
- **Unit of Work** — Bir neçə repository üzrə dəyişiklikləri izləyir
- **Generic Repository** — Ümumi CRUD əməliyyatları olan base

---

## Laravel

```
app/
├── Repositories/
│   ├── Contracts/                          # Repository interfaces
│   │   ├── RepositoryInterface.php         # Base interface
│   │   ├── UserRepositoryInterface.php
│   │   ├── OrderRepositoryInterface.php
│   │   ├── ProductRepositoryInterface.php
│   │   └── CriteriaInterface.php
│   │
│   ├── Eloquent/                           # Eloquent implementations
│   │   ├── BaseRepository.php             # Generic CRUD
│   │   ├── UserRepository.php
│   │   ├── OrderRepository.php
│   │   └── ProductRepository.php
│   │
│   ├── Cache/                              # Caching decorators
│   │   ├── CachedUserRepository.php
│   │   └── CachedProductRepository.php
│   │
│   ├── Criteria/                           # Query specifications
│   │   ├── User/
│   │   │   ├── ActiveUserCriteria.php
│   │   │   ├── UserByEmailCriteria.php
│   │   │   └── UserByRoleCriteria.php
│   │   ├── Order/
│   │   │   ├── OrderByStatusCriteria.php
│   │   │   ├── OrderByDateRangeCriteria.php
│   │   │   └── OrderByUserCriteria.php
│   │   └── Product/
│   │       ├── ProductByCategoryCriteria.php
│   │       └── ProductByPriceRangeCriteria.php
│   │
│   └── UnitOfWork/
│       ├── UnitOfWorkInterface.php
│       └── EloquentUnitOfWork.php
│
├── Models/
│   ├── User.php
│   ├── Order.php
│   └── Product.php
│
├── Services/
│   ├── UserService.php                     # Uses UserRepositoryInterface
│   └── OrderService.php
│
├── Http/
│   └── Controllers/
│       ├── UserController.php
│       └── OrderController.php
│
└── Providers/
    └── RepositoryServiceProvider.php       # Binds interfaces to implementations

tests/
├── Unit/
│   └── Repositories/
│       ├── UserRepositoryTest.php
│       └── OrderRepositoryTest.php
└── Integration/
    └── Repositories/
        ├── EloquentUserRepositoryTest.php
        └── EloquentOrderRepositoryTest.php
```

---

## Symfony

```
src/
├── Repository/
│   ├── Contract/                           # Interfaces
│   │   ├── RepositoryInterface.php
│   │   ├── UserRepositoryInterface.php
│   │   ├── OrderRepositoryInterface.php
│   │   └── ProductRepositoryInterface.php
│   │
│   ├── Doctrine/                           # Doctrine implementations
│   │   ├── AbstractDoctrineRepository.php
│   │   ├── DoctrineUserRepository.php
│   │   ├── DoctrineOrderRepository.php
│   │   └── DoctrineProductRepository.php
│   │
│   ├── InMemory/                           # In-memory (for testing)
│   │   ├── InMemoryUserRepository.php
│   │   └── InMemoryOrderRepository.php
│   │
│   ├── Cache/
│   │   ├── CachedUserRepository.php
│   │   └── CachedProductRepository.php
│   │
│   ├── Specification/                      # Query specifications
│   │   ├── User/
│   │   │   ├── ActiveUserSpecification.php
│   │   │   ├── UserByEmailSpecification.php
│   │   │   └── UserByRoleSpecification.php
│   │   ├── Order/
│   │   │   ├── OrderByStatusSpecification.php
│   │   │   └── OrderByDateRangeSpecification.php
│   │   ├── SpecificationInterface.php
│   │   ├── AndSpecification.php
│   │   └── OrSpecification.php
│   │
│   └── UnitOfWork/
│       ├── UnitOfWorkInterface.php
│       └── DoctrineUnitOfWork.php
│
├── Entity/
│   ├── User.php
│   ├── Order.php
│   └── Product.php
│
├── Service/
│   ├── UserService.php
│   └── OrderService.php
│
├── Controller/
│   ├── UserController.php
│   └── OrderController.php
│
config/
└── services.yaml                           # Interface -> implementation bindings

tests/
├── Unit/
│   └── Repository/
└── Integration/
    └── Repository/
```

---

## Spring Boot (Java)

```
src/main/java/com/example/app/
├── repository/
│   ├── contract/                           # Interfaces
│   │   ├── Repository.java                # Generic base
│   │   ├── UserRepository.java
│   │   ├── OrderRepository.java
│   │   └── ProductRepository.java
│   │
│   ├── jpa/                                # JPA implementations
│   │   ├── AbstractJpaRepository.java
│   │   ├── JpaUserRepository.java
│   │   ├── JpaOrderRepository.java
│   │   └── JpaProductRepository.java
│   │
│   ├── inmemory/                           # In-memory (for testing)
│   │   ├── InMemoryUserRepository.java
│   │   └── InMemoryOrderRepository.java
│   │
│   ├── cache/
│   │   └── CachedUserRepository.java
│   │
│   ├── specification/                      # Query specifications
│   │   ├── user/
│   │   │   ├── ActiveUserSpecification.java
│   │   │   ├── UserByEmailSpecification.java
│   │   │   └── UserByRoleSpecification.java
│   │   ├── order/
│   │   │   ├── OrderByStatusSpecification.java
│   │   │   └── OrderByDateRangeSpecification.java
│   │   ├── Specification.java
│   │   ├── AndSpecification.java
│   │   └── OrSpecification.java
│   │
│   └── unitofwork/
│       ├── UnitOfWork.java
│       └── JpaUnitOfWork.java
│
├── entity/
│   ├── User.java
│   ├── Order.java
│   └── Product.java
│
├── service/
│   ├── UserService.java
│   └── OrderService.java
│
├── controller/
│   ├── UserController.java
│   └── OrderController.java
│
└── config/
    └── RepositoryConfig.java
```

---

## Golang

```
project/
├── cmd/
│   └── api/
│       └── main.go
│
├── internal/
│   ├── repository/
│   │   ├── repository.go                  # Generic Repository interface
│   │   ├── user_repository.go             # UserRepository interface
│   │   ├── order_repository.go            # OrderRepository interface
│   │   ├── product_repository.go
│   │   │
│   │   ├── postgres/                       # PostgreSQL implementations
│   │   │   ├── base_repository.go
│   │   │   ├── user_repo.go
│   │   │   ├── order_repo.go
│   │   │   ├── product_repo.go
│   │   │   └── connection.go
│   │   │
│   │   ├── inmemory/                       # In-memory (for testing)
│   │   │   ├── user_repo.go
│   │   │   └── order_repo.go
│   │   │
│   │   ├── cache/
│   │   │   └── cached_user_repo.go
│   │   │
│   │   ├── specification/                  # Query specifications
│   │   │   ├── specification.go           # Interface
│   │   │   ├── user/
│   │   │   │   ├── active_user.go
│   │   │   │   ├── by_email.go
│   │   │   │   └── by_role.go
│   │   │   ├── order/
│   │   │   │   ├── by_status.go
│   │   │   │   └── by_date_range.go
│   │   │   ├── and_spec.go
│   │   │   └── or_spec.go
│   │   │
│   │   └── unitofwork/
│   │       ├── unit_of_work.go            # Interface
│   │       └── postgres_uow.go
│   │
│   ├── model/
│   │   ├── user.go
│   │   ├── order.go
│   │   └── product.go
│   │
│   ├── service/
│   │   ├── user_service.go
│   │   └── order_service.go
│   │
│   ├── handler/
│   │   ├── user_handler.go
│   │   └── order_handler.go
│   │
│   └── config/
│       └── config.go
│
├── pkg/
│   └── repository/
│       └── generic.go                      # Shared generic repository
├── go.mod
└── Makefile
```
