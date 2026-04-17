# Software Testing - Interview Hazırlığı

Bu qovluq software testing mövzularını əhatə edir. Hər fayl müsahibə hazırlığı üçün
ətraflı izahat, praktiki nümunələr və PHP/Laravel kod misalları ehtiva edir.

## Mündəricat (Table of Contents)

| # | Fayl | Mövzu |
|---|------|-------|
| 01 | [Testing Fundamentals](01-testing-fundamentals.md) | Testing pyramid, cost of bugs, terminology |
| 02 | [Unit Testing](02-unit-testing.md) | PHPUnit, AAA pattern, test isolation |
| 03 | [Integration Testing](03-integration-testing.md) | Database testing, API testing, RefreshDatabase |
| 04 | [Feature Testing](04-feature-testing.md) | HTTP tests, browser testing, Laravel feature tests |
| 05 | [TDD](05-tdd.md) | Red-Green-Refactor, TDD in Laravel |
| 06 | [BDD](06-bdd.md) | Gherkin, Given-When-Then, Behat |
| 07 | [Mocking](07-mocking.md) | Mockery, Facades, Http::fake, Queue::fake |
| 08 | [Test Doubles](08-test-doubles.md) | Dummy, stub, spy, mock, fake |
| 09 | [API Testing](09-api-testing.md) | REST API tests, contract testing, Sanctum |
| 10 | [Database Testing](10-database-testing.md) | Factories, seeders, assertDatabaseHas |
| 11 | [Browser Testing](11-browser-testing.md) | Dusk, Cypress, Playwright, Selenium |
| 12 | [Performance Testing](12-performance-testing.md) | Load, stress, spike testing, JMeter, k6 |
| 13 | [Security Testing](13-security-testing.md) | OWASP, SQL injection, XSS, CSRF testing |
| 14 | [Mutation Testing](14-mutation-testing.md) | Infection PHP, mutation score |
| 15 | [Code Coverage](15-code-coverage.md) | Line, branch, path coverage, Xdebug |
| 16 | [Test Organization](16-test-organization.md) | phpunit.xml, test suites, groups |
| 17 | [Continuous Testing](17-continuous-testing.md) | CI/CD, GitHub Actions, flaky tests |
| 18 | [Contract Testing](18-contract-testing.md) | Pact, consumer-driven contracts |
| 19 | [Snapshot Testing](19-snapshot-testing.md) | Spatie snapshot assertions |
| 20 | [Test Patterns](20-test-patterns.md) | Builder, Object Mother, factory patterns |
| 21 | [Testing Anti-Patterns](21-testing-anti-patterns.md) | Flaky tests, over-mocking, ice cream cone |
| 22 | [Testing Microservices](22-testing-microservices.md) | Service virtualization, chaos testing |
| 23 | [Testing Events & Queues](23-testing-events-queues.md) | Event::fake, Queue::fake, Bus::fake |
| 24 | [Testing Email & Notifications](24-testing-email-notifications.md) | Mail::fake, Notification::fake |
| 25 | [Testing File Uploads](25-testing-file-uploads.md) | Storage::fake, UploadedFile::fake |
| 26 | [Testing Authentication](26-testing-authentication.md) | actingAs, roles, permissions |
| 27 | [Testing Third-Party](27-testing-third-party.md) | Http::fake, Http::preventStrayRequests |
| 28 | [Testing Commands](28-testing-commands.md) | Artisan command testing |
| 29 | [Testing WebSockets](29-testing-websockets.md) | Broadcasting, real-time testing |
| 30 | [Testing Best Practices](30-testing-best-practices.md) | FIRST principles, maintainability |
| 31 | [Property-Based Testing](31-property-based-testing.md) | Eris, random inputs, invariants |
| 32 | [Fuzz Testing](32-fuzz-testing.md) | Random/malformed inputs, security fuzzing |
| 33 | [Visual Regression Testing](33-visual-regression-testing.md) | Screenshot diff, Percy, BackstopJS |
| 34 | [Accessibility Testing](34-accessibility-testing.md) | WCAG, axe-core, screen reader |
| 35 | [Characterization Tests](35-characterization-tests.md) | Legacy code, Michael Feathers |
| 36 | [Approval Testing](36-approval-testing.md) | Golden master, output comparison |
| 37 | [Test Data Management](37-test-data-management.md) | Factories, fixtures, anonymization, Faker |
| 38 | [Test Environment Management](38-test-environment-management.md) | Parity, ephemeral envs, Testcontainers, smoke on deploy |
| 39 | [Regression, Smoke, Sanity](39-regression-smoke-sanity.md) | Differences and pipeline stages |
| 40 | [Concurrency & Race Testing](40-concurrency-race-testing.md) | Race conditions, deadlocks, locks, async jobs |
| 41 | [GraphQL Testing](41-graphql-testing.md) | Lighthouse, schema, N+1, field permissions |

## Necə İstifadə Etməli

1. Hər faylı ardıcıl oxuyun
2. Kod nümunələrini öz proyektinizdə tətbiq edin
3. Interview suallarını cavablandırmağa çalışın
4. Anti-pattern-lərdən qaçının

## Əsas Texnologiyalar

- **PHP 8.x** / **Laravel 10+**
- **PHPUnit 10+**
- **Mockery**
- **Laravel Dusk**
- **Behat**
- **Infection PHP**
- **Lighthouse (GraphQL)**
- **Testcontainers**
- **Faker**
- **Eris (property-based)**
