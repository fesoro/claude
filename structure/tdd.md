# Test-Driven Development (TDD)

TDD development metodologiyasıdır — test-lər implementation kodundan əvvəl yazılır.
Folder strukturu test-first yanaşmanı əks etdirir — test-lər source strukturunu mirror edir.

**Dövr: Red -> Green -> Refactor**
1. **Red** — Uğursuz (failing) bir test yaz
2. **Green** — Test-i keçmək üçün minimal kod yaz
3. **Refactor** — Test-ləri pozmadan kodu təmizlə

**Test növləri:**
- **Unit Tests** — Tək class/function-ı təcrid olunmuş şəkildə test edir
- **Integration Tests** — Bir neçə komponenti birlikdə test edir
- **Functional/E2E Tests** — Bütün sistemi kənardan test edir
- **Contract Tests** — Servislər arası API contract-ları test edir

---

## Laravel

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── UserController.php
│   │   └── OrderController.php
│   ├── Requests/
│   │   ├── CreateUserRequest.php
│   │   └── PlaceOrderRequest.php
│   └── Resources/
│       ├── UserResource.php
│       └── OrderResource.php
│
├── Services/
│   ├── UserService.php
│   ├── OrderService.php
│   └── PaymentService.php
│
├── Repositories/
│   ├── Contracts/
│   │   ├── UserRepositoryInterface.php
│   │   └── OrderRepositoryInterface.php
│   └── Eloquent/
│       ├── UserRepository.php
│       └── OrderRepository.php
│
├── Models/
│   ├── User.php
│   ├── Order.php
│   └── Payment.php
│
├── ValueObjects/
│   ├── Email.php
│   └── Money.php
│
└── Exceptions/
    ├── UserNotFoundException.php
    └── InsufficientBalanceException.php

tests/
├── Unit/                                   # Unit tests (isolated, fast)
│   ├── ValueObjects/
│   │   ├── EmailTest.php
│   │   └── MoneyTest.php
│   ├── Models/
│   │   ├── UserTest.php
│   │   └── OrderTest.php
│   ├── Services/
│   │   ├── UserServiceTest.php
│   │   ├── OrderServiceTest.php
│   │   └── PaymentServiceTest.php
│   └── Repositories/
│       └── UserRepositoryTest.php
│
├── Integration/                            # Integration tests (DB, APIs)
│   ├── Repositories/
│   │   ├── EloquentUserRepositoryTest.php
│   │   └── EloquentOrderRepositoryTest.php
│   ├── Services/
│   │   └── PaymentServiceIntegrationTest.php
│   └── Database/
│       └── MigrationTest.php
│
├── Feature/                                # Feature/E2E tests (HTTP)
│   ├── Http/
│   │   ├── UserControllerTest.php
│   │   ├── OrderControllerTest.php
│   │   └── AuthenticationTest.php
│   ├── Api/
│   │   ├── CreateUserApiTest.php
│   │   ├── PlaceOrderApiTest.php
│   │   └── GetUserApiTest.php
│   └── Workflows/
│       ├── UserRegistrationWorkflowTest.php
│       └── OrderPlacementWorkflowTest.php
│
├── Contract/                               # Contract tests
│   └── Api/
│       ├── UserApiContractTest.php
│       └── OrderApiContractTest.php
│
├── Fixtures/
│   ├── UserFixture.php
│   └── OrderFixture.php
│
├── Stubs/
│   ├── PaymentGatewayStub.php
│   └── EmailServiceStub.php
│
├── Factories/
│   ├── UserFactory.php
│   └── OrderFactory.php
│
├── TestCase.php
├── CreatesApplication.php
└── phpunit.xml
```

---

## Symfony

```
src/
├── Controller/
│   ├── UserController.php
│   └── OrderController.php
├── Service/
│   ├── UserService.php
│   ├── OrderService.php
│   └── PaymentService.php
├── Repository/
│   ├── UserRepository.php
│   └── OrderRepository.php
├── Entity/
│   ├── User.php
│   ├── Order.php
│   └── Payment.php
├── ValueObject/
│   ├── Email.php
│   └── Money.php
└── Exception/
    ├── UserNotFoundException.php
    └── InsufficientBalanceException.php

tests/
├── Unit/
│   ├── ValueObject/
│   │   ├── EmailTest.php
│   │   └── MoneyTest.php
│   ├── Entity/
│   │   ├── UserTest.php
│   │   └── OrderTest.php
│   ├── Service/
│   │   ├── UserServiceTest.php
│   │   ├── OrderServiceTest.php
│   │   └── PaymentServiceTest.php
│   └── Repository/
│
├── Integration/
│   ├── Repository/
│   │   ├── UserRepositoryTest.php
│   │   └── OrderRepositoryTest.php
│   ├── Service/
│   │   └── PaymentServiceIntegrationTest.php
│   └── Doctrine/
│       └── MappingTest.php
│
├── Functional/
│   ├── Controller/
│   │   ├── UserControllerTest.php
│   │   ├── OrderControllerTest.php
│   │   └── AuthenticationTest.php
│   └── Workflow/
│       ├── UserRegistrationTest.php
│       └── OrderPlacementTest.php
│
├── Contract/
│   └── Api/
│       ├── UserApiContractTest.php
│       └── OrderApiContractTest.php
│
├── DataFixtures/
│   ├── UserFixtures.php
│   └── OrderFixtures.php
│
├── Doubles/                                # Test doubles
│   ├── Stub/
│   │   ├── PaymentGatewayStub.php
│   │   └── MailerStub.php
│   ├── Mock/
│   │   └── EventDispatcherMock.php
│   └── Fake/
│       └── InMemoryUserRepository.php
│
├── bootstrap.php
└── phpunit.xml.dist
```

---

## Spring Boot (Java)

```
src/main/java/com/example/app/
├── controller/
│   ├── UserController.java
│   └── OrderController.java
├── service/
│   ├── UserService.java
│   ├── OrderService.java
│   └── PaymentService.java
├── repository/
│   ├── UserRepository.java
│   └── OrderRepository.java
├── entity/
│   ├── User.java
│   ├── Order.java
│   └── Payment.java
├── valueobject/
│   ├── Email.java
│   └── Money.java
├── dto/
│   ├── request/
│   └── response/
└── exception/
    ├── UserNotFoundException.java
    └── InsufficientBalanceException.java

src/test/java/com/example/app/
├── unit/
│   ├── valueobject/
│   │   ├── EmailTest.java
│   │   └── MoneyTest.java
│   ├── entity/
│   │   ├── UserTest.java
│   │   └── OrderTest.java
│   ├── service/
│   │   ├── UserServiceTest.java
│   │   ├── OrderServiceTest.java
│   │   └── PaymentServiceTest.java
│   └── repository/
│
├── integration/
│   ├── repository/
│   │   ├── UserRepositoryIntegrationTest.java
│   │   └── OrderRepositoryIntegrationTest.java
│   ├── service/
│   │   └── PaymentServiceIntegrationTest.java
│   └── database/
│       └── MigrationTest.java
│
├── functional/
│   ├── controller/
│   │   ├── UserControllerTest.java
│   │   ├── OrderControllerTest.java
│   │   └── AuthenticationTest.java
│   └── workflow/
│       ├── UserRegistrationWorkflowTest.java
│       └── OrderPlacementWorkflowTest.java
│
├── contract/
│   └── api/
│       ├── UserApiContractTest.java
│       └── OrderApiContractTest.java
│
├── fixture/
│   ├── UserTestFixture.java
│   └── OrderTestFixture.java
│
├── stub/
│   ├── PaymentGatewayStub.java
│   └── MailerStub.java
│
├── config/
│   └── TestConfig.java
│
src/test/resources/
├── application-test.yml
└── data/
    └── test-data.sql
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
│   ├── handler/
│   │   ├── user_handler.go
│   │   ├── user_handler_test.go           # Unit test next to source
│   │   ├── order_handler.go
│   │   └── order_handler_test.go
│   │
│   ├── service/
│   │   ├── user_service.go
│   │   ├── user_service_test.go
│   │   ├── order_service.go
│   │   ├── order_service_test.go
│   │   ├── payment_service.go
│   │   └── payment_service_test.go
│   │
│   ├── repository/
│   │   ├── user_repository.go             # Interface
│   │   ├── order_repository.go
│   │   └── postgres/
│   │       ├── user_repo.go
│   │       ├── user_repo_test.go          # Integration test
│   │       ├── order_repo.go
│   │       └── order_repo_test.go
│   │
│   ├── model/
│   │   ├── user.go
│   │   ├── user_test.go
│   │   ├── order.go
│   │   ├── order_test.go
│   │   ├── email.go
│   │   ├── email_test.go
│   │   ├── money.go
│   │   └── money_test.go
│   │
│   ├── router/
│   │   └── router.go
│   │
│   └── config/
│       └── config.go
│
├── test/                                   # E2E and integration tests
│   ├── e2e/
│   │   ├── user_api_test.go
│   │   ├── order_api_test.go
│   │   └── workflow_test.go
│   ├── integration/
│   │   ├── database_test.go
│   │   └── payment_integration_test.go
│   ├── contract/
│   │   ├── user_api_contract_test.go
│   │   └── order_api_contract_test.go
│   ├── fixture/
│   │   ├── user_fixture.go
│   │   └── order_fixture.go
│   ├── mock/                              # Generated or hand-written mocks
│   │   ├── user_repository_mock.go
│   │   ├── order_repository_mock.go
│   │   └── payment_gateway_mock.go
│   ├── testutil/
│   │   ├── db.go                          # Test DB helpers
│   │   └── http.go                        # Test HTTP helpers
│   └── testdata/
│       ├── create_user.json
│       └── place_order.json
│
├── Makefile                                # test, test-unit, test-integration, etc.
├── go.mod
└── go.sum
```
