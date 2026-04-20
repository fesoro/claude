# Refactoring & Advanced Patterns — Interview Suallar

## Mündəricat
1. Code smells
2. Refactoring techniques
3. Advanced patterns
4. Legacy migration
5. Sual-cavab seti

---

## 1. Code smells

**S: "Long method"-u necə aşkarlayırsınız?**
C: 30+ sətir, 3+ səviyyəli nesting, çoxlu lokal dəyişən. Hər yeni "section" extract method üçün namizəddir.

**S: "Primitive obsession" nədir? Nümunə?**
C: String/int hər yerdə. Misal: `$user->email = 'a@b.com'` — Email value object yaradın (validation, format).

**S: "Feature envy" hansı bug-a aparır?**
C: Method başqa class-ın property-lərini çox istifadə edir → method o class-a köçürülməlidir.

**S: Law of Demeter pozuntusu?**
C: `$order->getCustomer()->getAddress()->getCity()->getName()` — train wreck. Dəyişiklik bütün zənciri qırır. Tell-don't-ask.

**S: "Shotgun surgery" anti-pattern?**
C: Bir feature dəyişikliyi 10+ fayla toxunur. Cohesion zəif, coupling yüksək. Aggregate / module-də toplamaq lazım.

**S: "God class" — neçə xət/method sayılır?**
C: 500+ sətir, 20+ method, 10+ dependency = "god class" alarm. SRP pozulur.

---

## 2. Refactoring techniques

**S: Extract method nə vaxt edilir?**
C: Comment yazmaq istəyəndə. Comment əvəzinə → method-un adı izah etsin.

**S: Extract class nə vaxt?**
C: Class-da 2 fərqli responsibility görünəndə (məs: User-də address logic). Address ayrı class olsun.

**S: Replace conditional with polymorphism?**
C: Switch/match-də `type` field varsa — strategy pattern, hər tip ayrı class.

**S: Introduce parameter object?**
C: 4+ parameter olan method. Onları DTO-ya çevir.

**S: Replace temp with query?**
C: Lokal dəyişən bir hesablama saxlayır → method çağırışı ilə əvəz et (DRY).

**S: Decompose conditional?**
C: Mürəkkəb if-də şərt extract method-a:
```php
// Pis
if ($date < $summer || $date > $autumn || $isHoliday) { ... }

// Yaxşı
if ($this->isOffSeason($date) || $this->isHoliday($date)) { ... }
```

---

## 3. Advanced patterns

**S: Specification pattern nə üçündür?**
C: Mürəkkəb biznes qaydalarını composable, reusable obyektlərə çevirmək. AND/OR/NOT operator.

**S: Saga pattern microservice-də?**
C: Distributed transaction əvəzinə. Hər addım local transaction + compensating action (rollback üçün).

**S: Outbox pattern hansı problemi həll edir?**
C: Dual-write: DB commit + event publish atomic deyil. Outbox table → DB-də event saxla → CDC ilə publish.

**S: Circuit breaker hansı dövlətlərdə olur?**
C: 
- CLOSED — normal işləyir
- OPEN — fail threshold keçilib, request rədd
- HALF-OPEN — sınama mərhələsi (1-2 request keçir)

**S: Bulkhead pattern?**
C: Resource isolation. Bir feature-in xəta verməsi digərlərinə təsir etməsin (separate thread pool, connection pool).

**S: CQRS nə vaxt həqiqətən faydalıdır?**
C: Read və write yükü çox fərqlidirsə (1000:1 read-heavy), fərqli model lazımdır. Sadə CRUD-da overkill.

**S: Event Sourcing trade-off?**
C: 
- ✓ Audit trail, time travel, projection rebuild
- ✗ Mürəkkəb, query çətin, schema evolution sancılı

**S: Repository pattern niyə tənqid olunur Eloquent ilə?**
C: Eloquent özü Repository implement edir. Əlavə layer (UserRepository) — abstraction over abstraction. Sadə CRUD-da lazımsız.

---

## 4. Legacy migration

**S: Strangler Fig pattern?**
C: Yeni kod köhnənin ətrafında böyüyür. Hissə-hissə replace. Köhnə nəhayət "strangled" (silinir). Big-bang rewrite riskini azaldır.

**S: Characterization test (Michael Feathers)?**
C: Legacy kodun mövcud davranışını təsbit edən test. "Doğrudur" deyil, "necə işlədiyini" sənədləşdirir. Refactor üçün safety net.

**S: Sprout method?**
C: Yeni feature-i ayrı method-da yaz, köhnə kodu minimal dəyiş. Test etmək asan.

**S: Seam nədir?**
C: "Behaviour-ı dəyişmədən kodu modify edə biləcəyin nöqtə". Subclass override, DI inject — seam-lərdir.

**S: Boy Scout rule?**
C: "Kodu daha təmiz halda burax, gəldiyindən". Hər PR-də kiçik təmizlik.

**S: Legacy DB-də N+1 necə tapılır?**
C: Telescope/Clockwork query log + slow query log + APM (per-request query count). Threshold: 1 request > 10 query → şübhəli.

---

## 5. Sual-cavab seti (Refactoring fokus)

**S: PHPStan level "max"-a niyə çatdırmaq lazımdır?**
C: Hər səviyyə daha çox bug aşkarlayır. Max — generic type, exhaustive match, dead code, redundant cast. Refactor zamanı safety net.

**S: Rector ilə hansı refactoring-ləri avtomatlaşdırmaq olar?**
C: PHP version upgrade (7→8), framework migration (Symfony 5→6, Laravel 9→10), code quality (extract method, early return, dead code).

**S: Cyclomatic complexity neçə yaxşıdır?**
C: <10 ideal, 10-20 acceptable, 20+ refactor. PHPMD-də threshold qoymaq olar.

**S: Coupling və cohesion balansı?**
C: Cohesion yüksək (related code together). Coupling aşağı (modules independent). "High cohesion, low coupling" — modular dizayn.

**S: SOLID hansı ən çox pozulur?**
C: SRP (Single Responsibility) — fat controller, god class. ISP (Interface Segregation) — fat interface (10+ method).

**S: Dependency Inversion praktikada?**
C: Concrete class əvəzinə interface inject. `MailerInterface` (abstraction) → `SmtpMailer` (concrete). Test mock asan.

**S: "Don't Repeat Yourself" həddən artıq nə vaxt zərərlidir?**
C: Premature abstraction. 2 oxşar yer — kopya OK. 3 oxşar yer — extract düşün. Wrong abstraction çoxlu duplikasiyadan pisdir.

**S: Pure function hər zaman yaxşıdır?**
C: Test asan, predictable, parallel-safe. AMMA hər şey pure deyil (DB, side effect). Pure və impure ayır (functional core, imperative shell).

**S: Anemic Domain Model nədir?**
C: Entity yalnız property + getter/setter, behavior YOXdur. Service-lərdə bütün logic. DDD-yə görə anti-pattern.

**S: Rich Domain Model qurmağa nə mane olur Eloquent-də?**
C: Active Record — model özünü save edir, framework coupling güclü. Pure domain logic ayırmaq çətin. Doctrine bunda daha yaxşıdır.

**S: Refactoring niyə test qabağı olmalıdır?**
C: Safety net. Test green olmasa refactor-ın doğruluğunu təsdiq edə bilmirsən. Red-Green-Refactor TDD cycle.

**S: "Extract till you drop" prinsipi?**
C: Method-ları DAİM extract et. 5-10 sətirlik kiçik method-lar. Naming improve edir, kompozisiya asanlaşır.

**S: Microservice extraction strategiyası?**
C: 
1. Bounded context identify et
2. Strangler Fig — hissə-hissə
3. Database split (öz DB)
4. API contract
5. Event-driven communication

**S: "Code review checklist"-də əsas?**
C: 
- Naming aydındır?
- Test əhatəsi?
- Edge case-lər?
- Performance regress?
- Security (input validation)?
- SOLID prinsipləri?
- Documentation?

**S: PHPMD niyə cyclomatic complexity yoxlayır?**
C: Mürəkkəb method bug-prone, test çətin. >10 cyclomatic — refactor signal.

**S: PSR-12 niyə vacibdir?**
C: Cross-team consistency. Code review-də style müzakirəsi minimal. Pint/PHP-CS-Fixer auto-format.

**S: "Blue-green refactoring"?**
C: İki implementation paralel saxla. Trafiyi tədricən switch. Ya da feature flag ilə.

**S: Legacy kodla ilk addım?**
C: Test əhatəsi (characterization). Sonra kiçik refactor. Big-bang refactor planlama YOX.

**S: "Ship of Theseus" rewrite?**
C: Hər hissəni yenisi ilə əvəz et — son nöqtədə tam yeni kod. Strangler Fig-in metaforası.

**S: Refactoring vs new feature?**
C: 80/20 qaydası. 20% refactor + 80% feature. Boy Scout rule ilə daimi kiçik təmizlik.
