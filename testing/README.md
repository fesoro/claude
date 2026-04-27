# Software Testing

PHP/Laravel developer üçün software testing learning path. Junior-dan Lead səviyyəsinə qədər, praktiki nümunələr və real layihə tapşırıqları ilə.

---

## ⭐ Junior

| # | Fayl | Mövzu |
|---|------|-------|
| 01 | [Testing Fundamentals](01-testing-fundamentals.md) | Testing pyramid, cost of bugs, terminology |
| 02 | [Unit Testing](02-unit-testing.md) | PHPUnit, AAA pattern, test isolation |
| 03 | [Integration Testing](03-integration-testing.md) | Database testing, API testing, RefreshDatabase |
| 04 | [Feature Testing](04-feature-testing.md) | HTTP tests, Laravel feature tests |

---

## ⭐⭐ Middle

| # | Fayl | Mövzu |
|---|------|-------|
| 05 | [TDD](05-tdd.md) | Red-Green-Refactor, TDD in Laravel |
| 06 | [BDD](06-bdd.md) | Gherkin, Given-When-Then, Behat |
| 07 | [Mocking](07-mocking.md) | Mockery, Facades, Http::fake, Queue::fake |
| 08 | [Test Doubles](08-test-doubles.md) | Dummy, stub, spy, mock, fake |
| 09 | [API Testing](09-api-testing.md) | REST API tests, contract testing, Sanctum |
| 10 | [Database Testing](10-database-testing.md) | Factories, seeders, assertDatabaseHas |
| 11 | [Browser Testing](11-browser-testing.md) | Dusk, Cypress, Playwright, Selenium |
| 12 | [Code Coverage](12-code-coverage.md) | Line, branch, path coverage, Xdebug |
| 13 | [Test Organization](13-test-organization.md) | phpunit.xml, test suites, groups |
| 14 | [Pest PHP](14-pest-php.md) | Laravel 11+ default framework, expect() API, datasets |
| 15 | [Testing Events & Queues](15-testing-events-queues.md) | Event::fake, Queue::fake, Bus::fake |
| 16 | [Testing Email & Notifications](16-testing-email-notifications.md) | Mail::fake, Notification::fake |
| 17 | [Testing File Uploads](17-testing-file-uploads.md) | Storage::fake, UploadedFile::fake |
| 18 | [Testing Authentication](18-testing-authentication.md) | actingAs, roles, permissions |
| 19 | [Testing Artisan Commands](19-testing-commands.md) | Command testing, assertExitCode |

---

## ⭐⭐⭐ Senior

| # | Fayl | Mövzu |
|---|------|-------|
| 20 | [Performance Testing](20-performance-testing.md) | Load, stress, spike testing, JMeter, k6 |
| 21 | [Security Testing](21-security-testing.md) | OWASP, SQL injection, XSS, CSRF testing |
| 22 | [Mutation Testing](22-mutation-testing.md) | Infection PHP, mutation score |
| 23 | [Continuous Testing](23-continuous-testing.md) | CI/CD, GitHub Actions, flaky tests |
| 24 | [Contract Testing](24-contract-testing.md) | Pact, consumer-driven contracts |
| 25 | [Snapshot Testing](25-snapshot-testing.md) | Spatie snapshot assertions |
| 26 | [Test Patterns](26-test-patterns.md) | Builder, Object Mother, factory patterns |
| 27 | [Testing Anti-Patterns](27-testing-anti-patterns.md) | Flaky tests, over-mocking, ice cream cone |
| 28 | [Testing Third-Party](28-testing-third-party.md) | Http::fake, Http::preventStrayRequests |
| 29 | [Testing WebSockets](29-testing-websockets.md) | Broadcasting, real-time testing |
| 30 | [Testing Best Practices](30-testing-best-practices.md) | FIRST principles, maintainability |
| 31 | [Characterization Tests](31-characterization-tests.md) | Legacy code, Michael Feathers |
| 32 | [Approval Testing](32-approval-testing.md) | Golden master, output comparison |
| 33 | [Test Data Management](33-test-data-management.md) | Factories, fixtures, anonymization, Faker |
| 34 | [Regression, Smoke & Sanity](34-regression-smoke-sanity.md) | Differences and pipeline stages |
| 35 | [Concurrency & Race Testing](35-concurrency-race-testing.md) | Race conditions, deadlocks, locks, async jobs |
| 36 | [GraphQL Testing](36-graphql-testing.md) | Lighthouse, schema, N+1, field permissions |
| 42 | [Integration & Contract Testing](42-integration-contract-testing.md) | Pact, provider/consumer, schema validation, API contracts |

---

## ⭐⭐⭐⭐ Lead

| # | Fayl | Mövzu |
|---|------|-------|
| 37 | [Testing Microservices](37-testing-microservices.md) | Service virtualization, chaos testing |
| 38 | [Property-Based Testing](38-property-based-testing.md) | Eris, random inputs, invariants |
| 39 | [Fuzz Testing](39-fuzz-testing.md) | Random/malformed inputs, security fuzzing |
| 40 | [Test Environment Management](40-test-environment-management.md) | Parity, ephemeral envs, Testcontainers |

---

## Reading Paths

### Backend developer üçün əsas path (Junior → Senior)
`01` → `02` → `03` → `04` → `07` → `08` → `09` → `10` → `14` → `15` → `16` → `17` → `18` → `19` → `23` → `30`

### Laravel developer üçün praktik path
`02` → `04` → `07` → `09` → `10` → `14` → `15` → `16` → `17` → `18` → `19` → `25` → `26` → `33`

### TDD/BDD ilə işləyənlər üçün
`01` → `02` → `05` → `06` → `08` → `12` → `13` → `22` → `26` → `27` → `30`

### CI/CD pipeline qurmaq üçün
`02` → `03` → `12` → `13` → `20` → `23` → `34` → `40`

### Legacy code ilə işləyənlər üçün
`01` → `07` → `08` → `31` → `32` → `27` → `30` → `33`

### Interview hazırlığı üçün (2-3 gün)
`01` → `02` → `03` → `05` → `07` → `08` → `12` → `27` → `30`

---

## Əsas Texnologiyalar

- **PHP 8.x** / **Laravel 11+**
- **Pest PHP** / **PHPUnit 10+**
- **Mockery**
- **Laravel Dusk**
- **Behat**
- **Infection PHP**
- **Lighthouse (GraphQL)**
- **Testcontainers**
- **Faker**
- **Eris (property-based)**
