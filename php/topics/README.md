# PHP / Laravel Topics

PHP/Laravel backend developer üçün vacib mövzular — dil əsaslarından runtime arxitekturasına qədər.

**Toplam: 57 mövzu** — Junior-dan Architect-ə, sadədən mürəkkəbə.

---

## Səviyyə Göstəriciləri

| Göstərici | Kimə uyğundur | Fayl aralığı |
|-----------|--------------|-------------|
| ⭐ **Junior** | PHP ilə yeni tanış, 0-2 il | 01–08 |
| ⭐⭐ **Middle** | Laravel development, 2-4 il | 09–28 |
| ⭐⭐⭐ **Senior** | Advanced features, external integrations | 29–43 |
| ⭐⭐⭐⭐ **Lead** | PHP internals, performance, code quality | 44–54 |
| ⭐⭐⭐⭐⭐ **Architect** | Runtime arxitekturası, async execution | 55–57 |

---

## ⭐ Junior — PHP Fundamentals (01–08)

### PHP Dil Əsasları
1. [OOP — 4 Prinsip, Abstract, Interface](01-oop.md)
2. [PHP Types & Strict Types](02-php-types-and-strict-types.md)
3. [Error Handling & Exceptions](03-error-handling-and-exceptions.md)
4. [PHP Generators — yield, memory-efficient iteration](04-php-generators.md)

### Tooling & Standards
5. [Composer & Package Management](05-composer-and-package-management.md)
6. [Pest — modern PHP testing framework](06-pest-framework.md)
7. [Xdebug — debugging & profiling](07-xdebug-deep-dive.md)
8. [PSR Standards — PSR-1/2/3/4/7/11/12/15](08-psr-standards.md)

---

## ⭐⭐ Middle — Laravel & PHP Internals (09–28)

### PHP Advanced Language Features
9. [Magic Methods — __get, __set, __call, __invoke](09-magic-methods-deep-dive.md)
10. [Type Juggling & Gotchas — == vs ===](10-type-juggling-gotchas.md)
11. [Traits — composition vs inheritance](11-traits-deep-dive.md)
12. [PHP Enums (8.1+)](12-php-enums-deep-dive.md)
13. [PHP Attributes (8.0+)](13-php-attributes.md)
14. [PHP SPL — data structures, iterators](14-php-spl.md)
15. [PHP Reflection API](15-reflection.md)

### PSR & Standards Deep Dive
16. [PSR-14 Event Dispatcher](16-psr-14-event-dispatcher.md)
17. [PSR-7/15 Middleware Deep Dive](17-psr-middleware-deep-dive.md)
18. [Autoloading Internals — PSR-4, classmap](18-autoloading-internals.md)
19. [Composer Advanced — plugins, scripts, private packages](19-composer-advanced.md)

### Laravel Ecosystem
20. [Service Provider & Service Container](20-service-provider.md)
21. [Laravel Internals — lifecycle, bindings](21-laravel-internals.md)
22. [Symfony Service Container](22-symfony-service-container.md)
23. [DI Container Comparison — Laravel vs Symfony vs PHP-DI](23-di-container-comparison.md)
24. [Laravel Telescope & Clockwork](24-laravel-telescope-clockwork.md)
25. [Laravel Auth — Sanctum, Passport, JWT](25-laravel-auth-sanctum-passport-jwt.md)
26. [Livewire & Inertia.js](26-livewire-inertia.md)
27. [Artisan Commands — custom commands, scheduling](27-artisan-commands-deep.md)
28. [Package Development](28-package-development.md)

---

## ⭐⭐⭐ Senior — Advanced Features & Integrations (29–43)

### PHP 8.x & Advanced Language
29. [PHP 8.3 / 8.4 New Features](29-php-83-84-features.md)
30. [PHP Streams — wrappers, filters, custom streams](30-php-streams.md)
31. [PHP CLI Application](31-php-cli-application.md)
32. [PHP Profiling Tools — Blackfire, Tideways, SPX](32-php-profiling-tools.md)

### External Integrations
33. [Elasticsearch with PHP — deep dive](33-elasticsearch-php-deep.md)
34. [MongoDB with PHP/Laravel](34-mongodb-with-php.md)
35. [Doctrine ORM — deep dive](35-doctrine-orm-deep.md)
36. [Symfony Console — deep dive](36-symfony-console-deep.md)

### Laravel Advanced Features
37. [Laravel Horizon — queue monitoring & management](37-laravel-horizon-queue.md)
38. [GraphQL with PHP/Laravel](38-graphql-php.md)
39. [Saloon — API SDK builder](39-saloon-api-sdk.md)
40. [OWASP Top 10 for PHP](40-owasp-top10-php.md)
41. [Laravel Notifications & Channels — mail, SMS, Slack](41-laravel-notifications-channels.md)
42. [Laravel Task Scheduler — cron, overlap, multi-server](42-laravel-task-scheduler.md)
43. [Laravel Model Factories & Seeders](43-laravel-model-factories-seeders.md)

---

## ⭐⭐⭐⭐ Lead — PHP Internals & Performance (44–54)

### PHP Runtime Internals
44. [PHP JIT Compiler — opcache.jit, tracing vs function](44-php-jit-compiler.md)
45. [PHP Process Model — FPM, request lifecycle](45-php-process-model.md)
46. [OPcache & Bytecode — internals, tuning](46-php-internals-opcache.md)

### Performance Engineering
47. [PHP Performance Profiling — flame graphs, bottlenecks](47-php-performance-profiling.md)
48. [PHP Memory & Heap — GC, memory leaks](48-php-memory-heap.md)
49. [PHP-FPM Configuration & Tuning](49-php-fpm-configuration.md)
50. [PHP Fibers & Async — cooperative multitasking](50-php-fibers-async.md)
51. [Long-Running PHP Processes](51-long-running-php-processes.md)

### Code Quality & Analysis
52. [Clean Code in PHP — principles, refactoring](52-clean-code-php.md)
53. [PHP Static Analysis — PHPStan, Larastan, Psalm](53-php-static-analysis.md)
54. [Mutation Testing — Infection PHP, MSI](54-mutation-testing-infection.md)

---

## ⭐⭐⭐⭐⭐ Architect — Runtime Architecture (55–57)

55. [Laravel Octane, RoadRunner & FrankenPHP](55-laravel-octane-roadrunner-frankenphp.md)
56. [Swoole & ReactPHP — event loop, coroutines](56-swoole-reactphp.md)
57. [PHP Execution Models — FPM vs CLI vs async runtimes](57-php-execution-models.md)

---

## Reading Paths

### PHP Fundamentals Path (Junior → Middle)
`01` → `02` → `03` → `04` → `05` → `08` → `09` → `10` → `11` → `12` → `13`

### Laravel Developer Path (Middle)
`20` → `21` → `16` → `17` → `18` → `19` → `24` → `25` → `27` → `28`

### PHP/Laravel Interview Hazırlığı (3-4 həftə)
`01–08` (Junior) → `09–19` (PHP advanced) → `20–28` (Laravel) → `29–32` (Senior features) → `44–51` (Internals + performance) → `interview/` folder

### Advanced Integration Path (Senior)
`29` → `30` → `33` → `34` → `35` → `37` → `38` → `39` → `40` → `41` → `42`

### Performance & Internals Path (Lead → Architect)
`44` → `45` → `46` → `47` → `48` → `49` → `50` → `51` → `55` → `56` → `57`

### Testing Mastery Path
`06` (Pest) → `07` (Xdebug) → `32` (Profiling) → `43` (Factories) → `52` (Clean Code) → `53` (Static analysis) → `54` (Mutation testing)
