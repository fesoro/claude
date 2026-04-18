# Software Engineering — Deep Vocabulary (B1/B2)

Xarici şirkətlərdə müsahibələrdə, meeting-lərdə və sənədlərdə qarşılaşacağınız texniki ifadələr. `tech-meetings.md` faylını bitirdikdən sonra buna keçin.

---

## 1. APIs & Web Services

| İfadə | Məna (Az) | Misal |
|-------|-----------|-------|
| endpoint | API ünvanı | "The `/users` endpoint returns a list of users." |
| request / response | sorğu / cavab | "The request returned a 500 response." |
| payload | göndərilən/gələn məlumat | "The payload is too large — we should paginate." |
| rate limit | sorğu limiti | "Their API has a rate limit of 100 requests per minute." |
| throttle | məhdudlaşdırmaq | "We throttle requests per user to prevent abuse." |
| webhook | avtomatik bildiriş | "Stripe sends a webhook when a payment succeeds." |
| idempotent | təkrar olunduqda nəticəni dəyişməyən | "The `PUT` endpoint is idempotent — safe to retry." |
| stateless | vəziyyətsiz | "REST APIs are stateless by design." |
| versioning | versiyalama | "We version our API using `/v1/`, `/v2/` prefixes." |
| backwards compatible | geriyə uyğun | "This change is backwards compatible — no client updates needed." |
| breaking change | pozucu dəyişiklik | "We announce breaking changes a month in advance." |
| polling | dövri sorğu | "The client polls the server every 30 seconds." |
| long polling | uzun sorğu | "Long polling keeps the connection open until data is ready." |

---

## 2. Databases

| İfadə | Məna (Az) | Misal |
|-------|-----------|-------|
| schema | verilənlər strukturu | "We need to update the schema to add a new column." |
| migration | sxem dəyişikliyi | "I ran the migration on staging first." |
| index | indeks | "Adding an index on `user_id` sped up the query tenfold." |
| query | sorğu | "This query is slow — it scans the entire table." |
| join | birləşdirmə | "We join the `users` and `orders` tables." |
| foreign key | xarici açar | "`user_id` is a foreign key to the `users` table." |
| transaction | tranzaksiya | "Wrap those updates in a transaction to keep them atomic." |
| rollback | geri qaytarma | "If anything fails, the transaction is rolled back." |
| replication | surətlənmə | "We use read replicas to reduce load on the primary." |
| sharding | parçalama | "At that scale, you probably need to shard by user ID." |
| consistency | tutarlılıq | "Eventual consistency is acceptable for analytics data." |
| ACID | atomiclik, tutarlılıq, izolyasiya, davamlılıq | "PostgreSQL guarantees ACID transactions." |
| denormalize | normallaşdırmadan çıxarmaq | "We denormalized the data for faster reads." |
| ORM | Object-Relational Mapper | "We use Prisma as our ORM." |
| N+1 problem | çox sorğu problemi | "Watch out for the N+1 problem when loading related data." |

### Common phrases
- "The database is a **bottleneck** for this feature."
- "This query **scans the entire table** — we need an index."
- "We **hit the connection pool limit** during peak hours."

---

## 3. Cloud & Infrastructure

| İfadə | Məna (Az) | Misal |
|-------|-----------|-------|
| deploy | yayımlamaq | "We deploy to production every Tuesday." |
| deployment | yayım | "The deployment failed because of a config error." |
| rollout | mərhələli yayım | "We do gradual rollouts starting with 5% of users." |
| infrastructure | infrastruktur | "Our infrastructure runs on AWS." |
| provision | hazırlamaq, yaratmaq | "We provision new servers using Terraform." |
| scale up / scale out | şaquli / horizontal miqyaslama | "We scaled out by adding more instances." |
| auto-scaling | avtomatik miqyaslama | "Auto-scaling kicks in when CPU hits 70%." |
| load balancer | yük balansı | "The load balancer distributes traffic across five servers." |
| container | konteyner | "Each service runs in its own container." |
| orchestration | idarəetmə | "Kubernetes handles container orchestration for us." |
| instance | nümunə, maşın | "We run on three EC2 instances in production." |
| region / zone | region / zona | "Our primary region is `us-east-1`." |
| failover | sıradan çıxanda keçid | "Failover to the secondary region is automatic." |
| downtime | iş dayanması | "We had 15 minutes of downtime last Friday." |
| uptime | iş müddəti | "We aim for 99.9% uptime." |

### Common phrases
- "The service is **up and running** again."
- "We're **scaling horizontally** to handle the load."
- "The deployment **went smoothly** — no issues."

---

## 4. DevOps & CI/CD

| İfadə | Məna (Az) | Misal |
|-------|-----------|-------|
| CI (Continuous Integration) | davamlı inteqrasiya | "CI runs tests on every commit." |
| CD (Continuous Deployment) | davamlı yayım | "Our CD pipeline deploys automatically after tests pass." |
| pipeline | boru kəməri (proses ardıcıllığı) | "The pipeline has five stages." |
| build | yığmaq | "The build failed — there's a syntax error." |
| artifact | nəticə fayl | "The build produces a Docker image as an artifact." |
| staging | test mühiti | "Always test in staging before production." |
| production | real mühit | "The bug only appears in production." |
| canary deployment | sınaq yayımı | "We tested the change with a canary deployment." |
| blue-green deployment | iki mühit arası keçid | "Blue-green lets us roll back instantly." |
| feature flag | funksiya açarı | "We hid the feature behind a feature flag." |
| monitoring | izləmə | "We set up monitoring with Datadog." |
| alerting | xəbərdarlıq | "I got paged by an alert at 3 AM." |
| observability | müşahidə imkanı | "Good observability means less time debugging." |
| logs | qeydlər | "I checked the logs — there's a stack trace." |
| metrics | ölçülər | "Response time is one of our key metrics." |
| tracing | izləmə (sorğu izləmə) | "Distributed tracing helps us follow a request across services." |

---

## 5. Software Architecture

| İfadə | Məna (Az) | Misal |
|-------|-----------|-------|
| monolith | tək blok tətbiq | "We started as a monolith and split it later." |
| microservices | mikroservislər | "Each microservice owns its own data." |
| service | xidmət | "The payment service is down." |
| coupling | bağlılıq | "Loose coupling makes the system easier to change." |
| cohesion | daxili bağlılıq | "High cohesion means a module does one thing well." |
| abstraction | abstraksiya | "This abstraction hides the database details." |
| dependency | asılılıq | "We have too many dependencies between services." |
| interface | interfeys | "The interface defines what the service can do." |
| contract | razılaşma | "Changing this breaks the API contract." |
| event-driven | hadisə yönümlü | "We moved to an event-driven architecture." |
| message queue | mesaj növbəsi | "We use Kafka as our message queue." |
| pub/sub | nəşr/abunə | "Services communicate via pub/sub." |
| sync / async | sinxron / asinxron | "Async calls don't block the caller." |
| bottleneck | darboğaz | "The database is the current bottleneck." |
| single point of failure | tək nöqtəli risk | "The load balancer is a single point of failure." |

---

## 6. Code Quality & Testing

| İfadə | Məna (Az) | Misal |
|-------|-----------|-------|
| unit test | vahid test | "I wrote unit tests for every function." |
| integration test | inteqrasiya testi | "Integration tests check how services work together." |
| end-to-end (E2E) test | tam-dövrlü test | "E2E tests cover the full user flow." |
| test coverage | test əhatəsi | "Our test coverage is around 80%." |
| flaky test | qeyri-sabit test | "This test is flaky — it fails randomly." |
| mock | əvəzləyici | "We mock the payment gateway in tests." |
| stub | bərkidilmiş cavab | "The stub always returns the same response." |
| fixture | hazır test datası | "The fixture sets up a test user." |
| linter | kod analizatoru | "The linter caught the unused import." |
| formatter | formatlayıcı | "We use Prettier as our formatter." |
| technical debt / tech debt | texniki borc | "We've accumulated tech debt in the auth module." |
| code smell | şübhəli kod | "This nested if-else is a code smell." |
| refactor | yenidən yazmaq | "We refactored the module without changing behavior." |
| regression | yenidən səhv | "This change caused a regression in search." |

### Common phrases
- "Let me **write a test to reproduce** the bug."
- "This code is **hard to test** — we should refactor it."
- "We need to **clean up this tech debt** before adding features."

---

## 7. Performance & Scaling

| İfadə | Məna (Az) | Misal |
|-------|-----------|-------|
| latency | gecikmə | "P99 latency is 200ms." |
| throughput | keçiricilik | "The service handles 10k requests per second." |
| concurrency | eyni anda iş | "We handle high concurrency with a worker pool." |
| bottleneck | darboğaz | "Database writes are the bottleneck." |
| cache hit / miss | keşə tuş gəlmək / gəlməmək | "Our cache hit rate is 95%." |
| cache invalidation | keşin ləğvi | "Cache invalidation is one of the hardest problems." |
| warm up / cold start | isinmə / soyuq başlanğıc | "Lambda has cold start issues." |
| memory leak | yaddaş sızması | "We found a memory leak in the background worker." |
| garbage collection | zibil yığma | "Long GC pauses caused the timeout." |
| profiling | performans analizi | "I profiled the code and found the slow function." |
| benchmark | müqayisəli ölçmə | "The benchmark shows version 2 is 3x faster." |
| saturate | tam yükləmək | "The CPU saturates during peak hours." |

---

## 8. Security

| İfadə | Məna (Az) | Misal |
|-------|-----------|-------|
| authentication | identifikasiya (kim) | "We use JWT for authentication." |
| authorization | icazə (nə etmək olar) | "Authorization is handled per-role." |
| encryption | şifrələmə | "All data is encrypted at rest and in transit." |
| hashing | hash-ləmə | "Passwords are hashed with bcrypt." |
| vulnerability | zəiflik | "We patched the vulnerability last week." |
| exploit | istifadə, suiistifadə | "An attacker exploited the SQL injection bug." |
| sanitize | təmizləmək | "Always sanitize user input." |
| injection | kod yeridilməsi | "Parameterized queries prevent SQL injection." |
| CSRF / XSS | cross-site attacks | "We protect against CSRF with tokens." |
| audit log | fəaliyyət qeydi | "Every admin action is in the audit log." |
| least privilege | minimum hüquq | "Give each service the least privilege it needs." |

---

## 9. Data & Analytics

| İfadə | Məna (Az) | Misal |
|-------|-----------|-------|
| data pipeline | data boru kəməri | "The pipeline processes 1TB per day." |
| ETL (Extract, Transform, Load) | çıxar-dəyiş-yüklə | "We have nightly ETL jobs." |
| data warehouse | data anbarı | "We load the data into BigQuery as our warehouse." |
| data lake | data gölü | "Raw data goes into S3 as our data lake." |
| batch processing | toplu emal | "Batch jobs run every hour." |
| streaming | axın emalı | "Kafka Streams handles real-time events." |
| aggregation | aqreqasiya | "The dashboard shows aggregated metrics." |
| dashboard | panel | "I built a dashboard for these KPIs." |
| KPI (Key Performance Indicator) | əsas performans göstəricisi | "Signup conversion is a key KPI." |

---

## 10. Problem Solving Expressions

Texniki söhbətlərdə problem və həllləri ifadə etmək üçün:

### Symptomu təsvir etmək
- "We're seeing **elevated error rates** on the payment service."
- "Users are reporting **slow response times**."
- "The service is **returning 500s intermittently**."
- "There's a **spike in latency** around noon."

### Araşdırma
- "Let me **dig into** the logs."
- "I want to **rule out** a database issue first."
- "I'll **reproduce it locally** and investigate."
- "It **looks like** a race condition."

### Root cause
- "The **root cause** was a misconfigured timeout."
- "We **traced the issue back to** a recent deployment."
- "It turned out to be a **classic N+1 query**."
- "The issue was that **we weren't handling** empty arrays."

### Həll
- "We **rolled back** the change and the issue went away."
- "The fix was a **one-line change** in the config."
- "We put in a **workaround** for now and a permanent fix next sprint."
- "We **added monitoring** so we catch this sooner next time."

---

## Məşq 1 — Sinonim seçmə

Hər cümlədə mötərizədə verilmiş variantdan uyğun olanı seçin:

1. "The API has a (rate limit / throughput) of 1000 requests per minute."
2. "Passwords should be (encrypted / hashed) before storing."
3. "We use read (replicas / instances) to handle heavy read traffic."
4. "This change is a (breaking change / regression) — clients need to update."
5. "The test is (flaky / stubbed) — it fails every third run."
6. "The cache (hit / miss) rate is 95%, which is great."
7. "We (provision / deploy) servers using Terraform."
8. "The (bottleneck / pipeline) has five stages: build, test, lint, deploy, verify."

### Cavablar
1. rate limit, 2. hashed, 3. replicas, 4. breaking change, 5. flaky, 6. hit, 7. provision, 8. pipeline

---

## Məşq 2 — Boşluq doldurma

Aşağıdakı sözlərdən birini seçin: *latency, downtime, feature flag, technical debt, migration, rollback, endpoint, monitoring*

1. "We had 10 minutes of __________ during the deployment."
2. "The new `/users/search` __________ supports filtering."
3. "I'll hide the new button behind a __________ for now."
4. "We set up __________ with alerts for high error rates."
5. "After the bug appeared, we did a __________ to the previous version."
6. "Our P99 __________ went up after the last release."
7. "We've accumulated a lot of __________ in the auth module."
8. "The database __________ renamed the `email` column to `email_address`."

### Cavablar
1. downtime, 2. endpoint, 3. feature flag, 4. monitoring, 5. rollback, 6. latency, 7. technical debt, 8. migration

---

## Məşq 3 — Tərcümə

Aşağıdakı cümlələri Azərbaycancaya tərcümə edin, sonra öz cavabınızı dəyərləndirin:

1. "We're going to scale out horizontally by adding more instances."
2. "The root cause was a misconfigured timeout in the load balancer."
3. "Async processing keeps the API responsive under load."
4. "Eventual consistency is fine for analytics but not for payments."
5. "We need to refactor this module before it becomes unmaintainable."
