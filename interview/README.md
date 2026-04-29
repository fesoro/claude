# Senior Software Engineer Interview Hazırlığı

## Haqqında

Bu bölmə senior software engineer interview-larına hazırlıq üçün nəzərdə tutulub.
200+ mövzu, 12 kateqoriya, Junior-dan Architect-ə qədər olan səviyyələrdə.
Hər mövzu real interview sualları, güclü cavab nümunələri və praktik tapşırıqlarla dəstəklənir.

Hədəf: 5+ il təcrübəli PHP/Laravel backend developer-lər üçün Senior, Lead, Architect rollarına hazırlıq.

---

## Kateqoriyalar

### 01. Data Structures & Algorithms (30 mövzu)
📁 [01-foundations/](01-foundations/)
Səviyyə: Junior → Lead

Algorithm anlayışları, Big O analizi, əsas data structure-lar, dynamic programming, graph alqoritmləri. FAANG/top tech şirkətlər bu bölməyə güclü fokus edir. Hər mövzu LeetCode pattern-ləri ilə əlaqəlidir.

### 02. System Design (25 mövzu)
📁 [02-system-design/](02-system-design/)
Səviyyə: Senior → Architect

Distributed systems, scalability, database seçimi, caching, message queue, rate limiting. Senior/Lead rolları üçün ən vacib bölmə. Real sistemlər (URL shortener, news feed, payment system) qurmağı öyrədir.

### 03. Databases (20 mövzu)
📁 [03-databases/](03-databases/)
Səviyyə: Middle → Lead

SQL vs NoSQL, ACID, indexing, query optimization, transactions, replication, sharding. Backend developer-in gündəlik işi ilə birbaşa əlaqəli. ORM-dən WAL-ə qədər geniş əhatə.

### 04. Concurrency (15 mövzu)
📁 [04-concurrency/](04-concurrency/)
Səviyyə: Middle → Lead

Thread vs process, race condition, mutex, deadlock, thread pool, async/await, event loop, actor model, lock-free data structures, concurrent collections. Performans kritik sistemlər üçün vacib. Go/Java rolları üçün xüsusilə tələb olunur.

### 05. Networking (16 mövzu)
📁 [05-networking/](05-networking/)
Səviyyə: Middle → Lead

TCP/UDP, HTTP versiyaları, TLS/SSL, DNS, REST/GraphQL/gRPC, WebSocket, OAuth/JWT, CORS, REST API Design Principles (resource naming, status codes, RFC 7807, pagination, idempotency, HATEOAS). API dizaynı ilə yanaşı gedən vacib networking bilikləri.

### 06. Design Patterns (16 mövzu)
📁 [06-design-patterns/](06-design-patterns/)
Səviyyə: Middle → Lead

SOLID prinsipləri, creational, structural, behavioral patterns. Code quality, maintainability, refactoring müzakirələrinin əsası.

### 07. Software Architecture (16 mövzu)
📁 [07-architecture/](07-architecture/)
Səviyyə: Senior → Architect

Monolith vs microservices, DDD, Clean Architecture, event sourcing, CQRS, service mesh, saga pattern, feature flags, zero-downtime deploy, Background Job Patterns (retry, DLQ, idempotency, fan-out, cron locking). Arxitektura qərar vermə müzakirələri üçün kritik.

### 08. Security (15 mövzu)
📁 [08-security/](08-security/)
Səviyyə: Junior → Senior

OWASP Top 10, SQL injection, XSS/CSRF, authentication/authorization, JWT, OAuth2, password hashing, secrets management. Hər developer-in bilməli olduğu əsas security bilikləri.

### 09. Performance (15 mövzu)
📁 [09-performance/](09-performance/)
Səviyyə: Middle → Lead

Profiling, query optimization, caching layers, lazy loading, connection pool tuning, memory leak detection, pagination, async processing, APM tools, load testing, API Performance Optimization (N+1 fix, latency breakdown, async 202 pattern, compression, HTTP/2, ETag caching). Production sistemlərinin sağlamlığı üçün vacib.

### 10. Behavioral & Leadership (15 mövzu)
📁 [10-behavioral/](10-behavioral/)
Səviyyə: Middle → Lead

STAR method, "tell me about yourself", texniki çətinliklər, disagreement-lar, mentoring, technical debt. Soft skill müsahibələri üçün strukturlaşdırılmış hazırlıq.

### 11. Testing (10 mövzu)
📁 [11-testing/](11-testing/)
Səviyyə: Middle → Lead

Testing pyramid, unit/integration/E2E, TDD, mocking strategies, coverage metrics, performance testing, contract testing, mutation testing, CI/CD-da testing, flaky tests. Test-driven engineering mindset.

### 12. DevOps Concepts (10 mövzu)
📁 [12-devops/](12-devops/)
Səviyyə: Senior → Architect

CI/CD pipeline, Kubernetes, IaC (Terraform), observability (metrics/logs/traces), SLA/SLO/SLI, on-call, incident response, capacity planning, cost optimization, GitOps. Production ownership anlayışı.

---

## Səviyyə Sistemi

| Level | Stars | Tipik Rol |
|-------|-------|-----------|
| Junior | ⭐ | 0-2 il |
| Middle | ⭐⭐ | 2-4 il |
| Senior | ⭐⭐⭐ | 4-7 il |
| Lead | ⭐⭐⭐⭐ | 7-10 il |
| Architect | ⭐⭐⭐⭐⭐ | 10+ il |

---

## Reading Paths

### Junior → Senior: 3 Aylıq Plan

**Ay 1 — Fundamental Texniki Biliklər:**
```
01-foundations: Big O → Arrays → Hash Table → Binary Search → Sorting
03-databases: SQL vs NoSQL → ACID → Indexes → Query Optimization
05-networking: TCP/UDP → HTTP → REST/GraphQL → OAuth/JWT
```

**Ay 2 — Design və Architecture:**
```
02-system-design: Scalability → Caching → Load Balancing → Message Queue
06-design-patterns: SOLID → Factory → Observer → Strategy
07-architecture: Monolith vs Microservice → DDD → Clean Architecture
```

**Ay 3 — Production Hazırlığı:**
```
08-security: OWASP → SQL Injection → Authentication
09-performance: Profiling → Caching → APM
11-testing: Pyramid → TDD → CI/CD
12-devops: CI/CD → Kubernetes → Observability
```

---

### Senior → Lead: Sistem Dizaynına Fokus

```
02-system-design: Bütün mövzular (dərin)
07-architecture: Event Sourcing → CQRS → Service Mesh → Saga
05-sla-slo-sli (12-devops)
08-capacity-planning (12-devops)
07-contract-testing (11-testing)
08-mutation-testing (11-testing)
10-behavioral: Texniki qərar, mentoring, leadership
```

---

### Davranışsal Interview Hazırlığı

```
10-behavioral: Bütün mövzular
10-behavioral/01-star-method.md — ilk başla
10-behavioral/03-greatest-technical-challenge.md
10-behavioral/04-technical-disagreements.md
10-behavioral/05-mentoring-juniors.md
12-devops/07-incident-response.md — "incident nümunəsi" sualı üçün
12-devops/06-oncall-best-practices.md
```

---

### FAANG/Top Tech Interview Sprint (6 Həftə)

**Həftə 1-2 — Algorithms:**
```
01-foundations: 01-big-o → 02-arrays → 05-hash-table → 06-binary-search
→ 07-recursion → 08-sorting → 09-binary-tree → 12-two-pointers
→ 13-sliding-window → 14-dynamic-programming
```

**Həftə 3 — System Design Fundamentals:**
```
02-system-design: 01 → 04 → 05 → 06 → 07 → 08
```

**Həftə 4 — System Design Advanced:**
```
02-system-design: 09 → 11 → 12 → 16 → 17 → 18
07-architecture: 01 → 02 → 05 → 06
```

**Həftə 5 — Deep Dive:**
```
03-databases: Bütün mövzular
05-networking: 01-06 mövzular
```

**Həftə 6 — Behavioral + Review:**
```
10-behavioral: Bütün mövzular
Zəif mövzuları təkrar et
Mock interview practise et
```

---

## Ən Vacib 20 Mövzu (Prioritet Siyahısı)

Senior backend developer müsahibəsi üçün ən tez-tez soruşulan mövzular:

1. Big O Notation (01-foundations)
2. Hash Table (01-foundations)
3. System Design Approach (02-system-design)
4. Caching Strategies (02-system-design)
5. Database Indexing (03-databases)
6. Transaction Isolation (03-databases)
7. ACID Properties (03-databases)
8. Load Balancing (02-system-design)
9. REST vs GraphQL vs gRPC (05-networking)
10. OAuth2 / JWT (05-networking)
11. SOLID Principles (06-design-patterns)
12. Microservices vs Monolith (07-architecture)
13. CI/CD Pipeline (12-devops)
14. Testing Pyramid (11-testing)
15. SQL Injection / OWASP (08-security)
16. Performance Profiling (09-performance)
17. Dynamic Programming (01-foundations)
18. SLA/SLO/SLI (12-devops)
19. STAR Method (10-behavioral)
20. Message Queues (02-system-design)
