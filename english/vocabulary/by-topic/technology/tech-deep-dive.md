# Tech Vocabulary Deep Dive — Debug, Review, Architecture

## Bu Fayl Haqqında

Adi "technology.md" ümumi texnologiya sözləri verir. Bu fayl **gündəlik software engineer işi** üçün vacib söz və ifadələri verir — bug fix, code review, architecture diskussiyası, git, deployment, monitoring.

Müsahibədə və iş mühitində bu sözlər "developer kimi danışmağın" ən vacib hissəsidir.

---

## 1. Debugging Vocabulary

### Problem təsviri
| İfadə | Məna |
|-------|------|
| **bug** | sistem səhvi |
| **issue** | məsələ, problem |
| **error** | xəta |
| **failure** | uğursuzluq |
| **glitch** | kiçik texniki problem |
| **flaky test** | bəzən keçən, bəzən keçməyən test |
| **race condition** | eyni zamanda iki proses eyni resursa giriş |
| **deadlock** | iki proses biri digərini gözləyir |
| **memory leak** | yaddaş sızması |
| **null pointer** | null referansı |
| **off-by-one error** | bir sayı səhv (sıx-sıx bug) |
| **infinite loop** | sonsuz dövrə |
| **segfault** | memory access səhvi |
| **corrupted data** | zədələnmiş məlumat |
| **edge case** | qeyri-adi hal |
| **corner case** | nadir kombinasiya |
| **regression** | yenidən ortaya çıxan köhnə bug |

### Problemin yerini tapmaq
| Verb | Mənası | Nümunə |
|------|--------|--------|
| **reproduce** | təkrar yaratmaq | "Can you **reproduce** the bug?" |
| **track down** | izləyib tapmaq | "I **tracked down** the root cause." |
| **narrow down** | daraltmaq | "Let me **narrow down** where it fails." |
| **pinpoint** | dəqiq yerləşdirmək | "I **pinpointed** the issue to line 42." |
| **isolate** | təcrid etmək | "We need to **isolate** the problem." |
| **trace** | izləmək | "I **traced** the request through the system." |
| **diagnose** | tanıtmaq | "**Diagnosing** slow queries." |
| **investigate** | araşdırmaq | "I'm **investigating** the timeout issue." |

### Debug prosesi
| İfadə | Nümunə |
|-------|--------|
| add logging | "Let me **add logging** to understand the flow." |
| attach a debugger | "I'll **attach a debugger** to the running process." |
| set a breakpoint | "I **set a breakpoint** on line 100." |
| step through the code | "I need to **step through** this function." |
| dump stack trace | "Can you **dump the stack trace**?" |
| check the logs | "Did you **check the logs**?" |
| print statements | "Use **print statements** to verify state." |
| repro steps | "Here are the **repro steps**:" |

### Fix vocabulary
| İfadə | Mənası |
|-------|--------|
| **patch** | kiçik düzəliş |
| **hotfix** | təcili, dərhal tətbiq edilən fix |
| **workaround** | müvəqqəti həll (root cause həll edilmir) |
| **band-aid fix** | keyfiyyətsiz, müvəqqəti həll |
| **root cause** | əsl səbəb |
| **regression test** | yenidən baş verməsin deyə yazılan test |
| **post-mortem** | hadisədən sonra analiz |
| **incident report** | hadisə haqqında rəsmi sənəd |
| **RCA (root cause analysis)** | səbəb analizi |

### Nümunə dialog:
> A: "We're getting 500 errors in production."  
> B: "Can you **reproduce** it?"  
> A: "Yeah, I have **repro steps**. It only happens when the user has more than 100 orders."  
> B: "That sounds like an **edge case**. Let me **add logging** and **track down** where it fails."  
> A: "Might be worth checking for a **race condition**."  
> B: "Good thought. If we can't fix it today, let's at least push a **workaround** — and do **root cause analysis** after."

---

## 2. Code Review Vocabulary

### Rəyin tonu
| Neutral | Güclü (emergency) |
|---------|---------------------|
| nit: (kiçik irad) | blocking: (bağlayıcı) |
| suggestion: | required: |
| question: | must fix: |
| thought: | concern: |

### Review ifadələri

**Razılaşma:**
- "LGTM" (Looks Good To Me) — hər şey yaxşıdır
- "Approved" — təsdiqləndi
- "Ship it!" — deployla
- "Nice work." / "Great job."
- "Clean and readable, well done."

**Təkliflər (blocking deyil):**
- "Just a nit — could rename this variable for clarity."
- "Suggestion: consider extracting this into a helper."
- "Have we considered ___?"
- "Would it be simpler to ___?"
- "What do you think about ___?"

**Sual soruşmaq:**
- "Could you help me understand what this does?"
- "Why did you choose X over Y?"
- "Is this intended?"
- "Could you add a comment here explaining the edge case?"

**Blocking irad:**
- "This will break existing tests — we need to fix that before merging."
- "This introduces a breaking change that affects downstream services."
- "I think there's a potential **security issue** here."
- "This doesn't handle the error case."
- "Can we add tests for this?"

### Kodun keyfiyyəti ilə bağlı sözlər
| İfadə | Mənası |
|-------|--------|
| **readable** | aydın oxunan |
| **maintainable** | dəstəkləmə asanlığı |
| **clean** | səliqəli |
| **DRY** (Don't Repeat Yourself) | təkrar olmayan |
| **WET** (Write Everything Twice) | təkrarlı (pis) |
| **tight coupling** | sıx bağlılıq (pis) |
| **loose coupling** | gevşək bağlılıq (yaxşı) |
| **spaghetti code** | qarışıq kod |
| **technical debt** | texniki borc |
| **boilerplate** | standart, çox yazılan şablon kod |
| **hardcoded** | kod içində sabitləşdirilmiş dəyər |
| **magic number** | izahsız sabit rəqəm |
| **code smell** | şüphəli kod nişanı |
| **refactor** | yenidən təşkil etmək |
| **legacy code** | köhnə kod |

### Dialogue nümunələri:
> Reviewer: "Nice PR overall. Just a few **nits**. The naming in `processData` is a bit vague — could we call it something more specific? Also, there's a **magic number** (500) on line 42 — let's extract it as a constant."  
>   
> Author: "Good points. I'll rename to `validateAndSaveUserOrders` and add a `const MAX_BATCH_SIZE = 500`. Pushing now."

---

## 3. Architecture & System Design Vocabulary

### Komponentlər
| İfadə | Mənası |
|-------|--------|
| **microservice** | mikrosxidmət |
| **monolith** | monolitik sistem |
| **service** | xidmət |
| **module** | modul |
| **component** | komponent |
| **layer** | təbəqə |
| **tier** | yarus |
| **frontend / backend / full-stack** | ön / arxa / tam tərəf |
| **API gateway** | API şlüzü |
| **load balancer** | yük paylayıcı |
| **reverse proxy** | tərs proksi |
| **message queue** | mesaj növbəsi |
| **event bus** | hadisə şinası |
| **worker** | arxa planda işləyən proses |
| **cron job** | planlaşdırılmış iş |

### Məlumat bazası
| İfadə | Mənası |
|-------|--------|
| **schema** | sxem |
| **migration** | miqrasiya |
| **index** | indeks |
| **query** | sorğu |
| **join** | birləşdirmək |
| **transaction** | əməliyyat |
| **rollback** | geri qaytarmaq |
| **commit** | təsdiqləmək |
| **replica** | replika, kopya |
| **master-slave** | əsas-tabe |
| **sharding** | bölmə |
| **partitioning** | partisyalaşdırma |
| **denormalization** | denormalizasiya |
| **foreign key** | xarici açar |
| **primary key** | əsas açar |

### Şəbəkə və Protokol
| İfadə | Mənası |
|-------|--------|
| **REST API** | REST tipli API |
| **GraphQL** | GraphQL |
| **RPC** | uzaq prosedur çağırışı |
| **webhook** | web çağırış |
| **endpoint** | nöqtə |
| **request / response** | sorğu / cavab |
| **payload** | məzmun |
| **status code** | status kodu |
| **HTTPS / TLS** | təhlükəsiz bağlantı |
| **authentication** | kimlik təsdiq |
| **authorization** | icazə yoxlama |
| **rate limiting** | sürət məhdudiyyəti |
| **throttling** | qarşısını alma |

### Performans və Ölçülənmə
| İfadə | Mənası |
|-------|--------|
| **latency** | gecikmə |
| **throughput** | ötürücülük |
| **response time** | cavab vaxtı |
| **bandwidth** | bant genişliyi |
| **QPS (queries per second)** | saniyədə sorğu |
| **requests per second (RPS)** | saniyədə sorğu |
| **scalability** | miqyaslanma |
| **horizontal / vertical scaling** | üfüqi / şaquli miqyaslama |
| **elastic** | elastik |
| **high availability** | yüksək əlçatanlıq |
| **uptime** | işləmə müddəti |
| **downtime** | sıradan çıxma |
| **SLA (Service Level Agreement)** | xidmət səviyyəsi razılaşması |
| **SLO / SLI** | məqsəd / göstərici |
| **p95 / p99 latency** | 95/99-cu persentil gecikmə |
| **fan-out** | çox istiqamətə yayılma |

### Dayanıqlılıq və Təhlükəsizlik
| İfadə | Mənası |
|-------|--------|
| **resilient** | davamlı |
| **fault-tolerant** | xətaya dözümlü |
| **graceful degradation** | zəif vəziyyətdə də işləmək |
| **circuit breaker** | ara vermə dövrəsi |
| **retry with backoff** | gecikməli təkrar cəhd |
| **idempotent** | təkrar çağırışda eyni nəticə |
| **disaster recovery** | fəlakət bərpası |
| **backup** | ehtiyat surət |
| **failover** | alternativ sistem keçməsi |
| **data consistency** | məlumat ardıcıllığı |
| **eventual consistency** | zaman içində ardıcıllıq |
| **ACID** | atomic, consistent, isolated, durable |

### Architecture Dialogue Nümunəsi:
> Architect: "We need to design a system that can handle **100K requests per second**. Our current **monolith** struggles at **10K QPS**."  
>   
> Engineer: "Let's split into **microservices**. We can have separate services for auth, orders, and payments. Between them, we'll use a **message queue** for async work."  
>   
> Architect: "Good. What about the database?"  
>   
> Engineer: "For the user service, we might need **sharding** by user ID. For orders, a **read replica** should handle most of the read load."  
>   
> Architect: "Consider **caching** the hot data with Redis. And let's think about **failure modes** — what happens if the message queue goes down?"  
>   
> Engineer: "We can add a **circuit breaker** and **retry with backoff**. For critical paths, we'll use **idempotent** requests."

---

## 4. Git & Version Control Vocabulary

### Əsas əməliyyatlar
| Əmr | Mənası | İngilis ifadəsi |
|-----|--------|------------------|
| commit | təsdiqləmək | "I'll **commit** this change." |
| push | uzaq serverə göndərmək | "**Push** your branch." |
| pull | uzaq serverdən almaq | "**Pull** the latest changes." |
| fetch | əldə etmək (merge etməmək) | "**Fetch** before you push." |
| merge | birləşdirmək | "**Merge** master into your branch." |
| rebase | tarixini yenidən qurmaq | "**Rebase** onto master." |
| cherry-pick | xüsusi commit götürmək | "**Cherry-pick** that fix." |
| squash | commit-ləri birləşdirmək | "**Squash** these commits before merging." |
| stash | müvəqqəti saxlamaq | "Let me **stash** my changes." |
| revert | geri qaytarmaq | "We need to **revert** that commit." |
| reset | resetləmək | "**Reset** to the last good commit." |

### Konseptlər
| İfadə | Mənası |
|-------|--------|
| **branch** | qol |
| **master / main** | əsas qol |
| **feature branch** | xüsusiyyət qolu |
| **hotfix branch** | təcili düzəliş qolu |
| **pull request (PR) / merge request** | merge sorğusu |
| **diff** | fərq |
| **merge conflict** | birləşmə münaqişəsi |
| **rebase conflict** | rebase münaqişəsi |
| **force push** | güclü push (təhlükəli) |
| **fast-forward** | xətti merge |
| **detached HEAD** | HEAD-in qolsuz vəziyyəti |
| **upstream** | yuxarı axın |
| **origin** | mənbə |
| **fork** | budaqlandırmaq |
| **clone** | surətini çıxarmaq |

### Müzakirə ifadələri:
- "Could you **rebase** onto main before I review?"
- "This PR has a **merge conflict**; can you resolve it?"
- "I'll **cherry-pick** your fix into the release branch."
- "Don't **force push** to shared branches."
- "Let's **squash** these 10 commits into 1 before merging."
- "I'm **checking out** the feature branch."
- "My branch is out of date — I need to **pull**."

---

## 5. Deployment & DevOps Vocabulary

### Deploy prosesi
| İfadə | Mənası |
|-------|--------|
| **deploy** | yerləşdirmə |
| **release** | buraxılış |
| **rollout** | yayım |
| **rollback** | geri qayıtma |
| **hotfix deploy** | təcili yayım |
| **canary release** | kiçik qrup ilə test |
| **blue-green deployment** | iki mühit arasında keçid |
| **feature flag** | xüsusiyyət açarı |
| **staging** | sınaq mühiti |
| **production / prod** | istehsal mühiti |
| **dev / development** | inkişaf |
| **CI/CD pipeline** | davamlı inteqrasiya / təhvil |
| **artifact** | son məhsul |
| **container** | konteyner |
| **orchestration** | orkestrasiya |
| **infrastructure as code** | kod halında infrastruktur |

### Cloud
| İfadə | Mənası |
|-------|--------|
| **provision** | təchiz etmək |
| **scale up / down** | böyütmək / kiçiltmək |
| **auto-scaling** | avtomatik miqyaslama |
| **spin up / tear down** | qurmaq / sökmək |
| **region / zone** | region / zona |
| **VM (virtual machine)** | virtual maşın |
| **cluster** | klaster |
| **load** | yük |

### Hadisələr
| İfadə | Mənası |
|-------|--------|
| **incident** | hadisə |
| **outage** | sıradan çıxma |
| **degradation** | keyfiyyət itkisi |
| **on-call** | növbətçi |
| **page** | çağırmaq (alarm) |
| **alert** | xəbərdarlıq |
| **escalate** | yuxarı qaldırmaq |
| **severity (SEV1, SEV2)** | ciddilik səviyyəsi |
| **runbook** | addım-addım təlimat |

### Dialogue:
> On-call engineer: "We're seeing elevated **latency** on the checkout service. **p99 is up 5x**."  
>   
> Manager: "**Page** the team. What's the **blast radius**?"  
>   
> Engineer: "Only affects payments. Should we **roll back** the last **deploy**?"  
>   
> Manager: "Yes. **Revert** it. Then we'll do a **post-mortem** tomorrow."

---

## 6. Monitoring & Observability Vocabulary

| İfadə | Mənası |
|-------|--------|
| **metrics** | metriklər |
| **logs** | qeydlər |
| **traces** | izlər |
| **dashboard** | panel |
| **alert** | xəbərdarlıq |
| **threshold** | həd |
| **noise** | səs-küy (yalan alarmlar) |
| **signal** | siqnal (əsl problem) |
| **drill down** | dərinliyinə getmək |
| **aggregate** | cəmləmək |
| **percentile** | persentil |
| **anomaly** | anomaliya |
| **spike** | ani artım |
| **baseline** | əsas səviyyə |
| **RCA** | root cause analysis |
| **SLI / SLO / SLA** | göstərici / məqsəd / razılaşma |
| **error budget** | xəta büdcəsi |

---

## 7. Testing Vocabulary

### Test növləri
| İfadə | Mənası |
|-------|--------|
| **unit test** | vahid test |
| **integration test** | inteqrasiya testi |
| **end-to-end (E2E) test** | sondan sona test |
| **smoke test** | qısa yoxlama |
| **regression test** | köhnə bug üçün test |
| **load test** | yük testi |
| **stress test** | stress testi |
| **A/B test** | A/B testi |
| **chaos testing** | xaos testi |

### Test prosesi
| İfadə | Mənası |
|-------|--------|
| **test coverage** | test əhatəsi |
| **test case** | test halı |
| **test suite** | test paketi |
| **fixture** | sabit test məlumatı |
| **mock / stub** | saxta obyekt / əvəzedici |
| **assertion** | iddia |
| **flaky** | dəyişkən (sabit olmayan) test |
| **green / red tests** | keçir / keçmir |
| **TDD (Test-Driven Development)** | test əsaslı inkişaf |
| **BDD (Behavior-Driven Development)** | davranış əsaslı inkişaf |

---

## 8. Agile & Project Management Vocabulary

### Ağır terminlər
| İfadə | Mənası |
|-------|--------|
| **sprint** | sprint |
| **standup / daily** | gündəlik görüş |
| **retro / retrospective** | retrospektiv |
| **planning / refinement** | planlama |
| **backlog** | siyahı (iş növbəsi) |
| **grooming** | backlog təmizləmə |
| **story / user story** | hekayə |
| **ticket** | bilet (iş vahidi) |
| **epic** | epos (böyük iş) |
| **story points** | hekayə xalları |
| **velocity** | sürət |
| **definition of done** | bitmənin meyarı |
| **acceptance criteria** | qəbul meyarları |
| **blocked / blocker** | bloklanmış / bloklayan şey |
| **burndown chart** | bitmə qrafiki |
| **stakeholder** | maraqlı tərəf |

### Dialoqda:
- "This is **blocked** waiting on the design team."
- "Let me **refine** this ticket — the **acceptance criteria** aren't clear."
- "We don't have enough **velocity** to finish this sprint."
- "Please **groom** the top 10 tickets before planning."
- "What's the **definition of done** for this story?"

---

## 9. Performance Optimization Vocabulary

| İfadə | Mənası |
|-------|--------|
| **bottleneck** | darboğaz |
| **profile** | proseslərinin analizini etmək |
| **benchmark** | müqayisə testi |
| **optimize** | optimallaşdırmaq |
| **tune** | incə tənzimləmək |
| **cache hit / miss** | cache uğur / uğursuzluq |
| **cold / warm start** | soyuq / isti başlama |
| **lazy load** | tələbə görə yükləmə |
| **eager load** | öncədən yükləmə |
| **memoization** | əvvəlki nəticəni yadda saxlamaq |
| **batch processing** | paketlə emal |
| **parallel processing** | paralel emal |
| **async / sync** | asinxron / sinxron |
| **non-blocking** | bloklanmayan |
| **throttle** | sürəti məhdudlaşdırmaq |
| **debounce** | təkrar çağırışları birləşdirmək |

---

## 10. Security Vocabulary

| İfadə | Mənası |
|-------|--------|
| **vulnerability** | zəiflik |
| **exploit** | istismar |
| **patch** | yamaq |
| **CVE** | Common Vulnerabilities and Exposures |
| **injection attack** | inyeksiya hücumu |
| **SQL injection** | SQL inyeksiyası |
| **XSS** | Cross-Site Scripting |
| **CSRF** | Cross-Site Request Forgery |
| **encryption** | şifrələmə |
| **hash** | həsh |
| **salt** | duz (həsh üçün) |
| **token** | nişan |
| **JWT** | JSON Web Token |
| **OAuth** | səlahiyyət vermə protokolu |
| **SSO (Single Sign-On)** | tək giriş |
| **2FA / MFA** | iki / çoxfaktorlu təsdiq |
| **pentesting** | penetrasiya testi |
| **audit** | audit |
| **compliance** | uyğunluq |
| **GDPR** | Avropa məlumat qanunu |

---

## 11. Collaboration Vocabulary

### Komanda
- "**Sync up**" = birlikdə görüşmək, statusu aydınlaşdırmaq
- "**Loop in**" = birini söhbətə daxil etmək ("Let's **loop in** Sara")
- "**Hand off**" = təhvil vermək
- "**Pair program**" = cütlük şəklində proqramlaşdırma
- "**Mob programming**" = bütün komanda birlikdə
- "**Retro action items**" = retrospektiv addımlar
- "**Knowledge transfer (KT)**" = bilik ötürmə

### Qərarlar
- "**Alignment**" = razılaşdırma
- "**Buy-in**" = dəstək / razılıq
- "**Pushback**" = əks-təklif, müqavimət
- "**Compromise**" = güzəşt
- "**Trade-off**" = güzəşt (nəyə qarşı nə)
- "**Green light**" = icazə
- "**Put on hold**" = dayandırmaq
- "**Back-burner**" = təxirə salmaq
- "**Out of scope**" = miqyasdan kənar

### Nümunələr:
- "Let me **loop in** the DevOps team on this."
- "We need **alignment** with product before we start."
- "There was some **pushback** on the new approach."
- "Let's **put this on hold** until Q2."
- "That's a **trade-off** between speed and correctness."
- "This is **out of scope** for this sprint."

---

## 12. Əlavə Tech Expressions — Müsahibələrdə

### Problemi qiymətləndirmək
- "There are several ways to approach this..."
- "The naive approach would be..."
- "A more efficient solution would be..."
- "Let's think about the edge cases."
- "What are the constraints?"
- "What's the expected scale?"

### Qərar izah etmək
- "I chose X over Y because..."
- "The trade-off is..."
- "In this case, I prioritized readability over performance."
- "If scale were higher, I'd use..."
- "I'd start simple and iterate."

### Bilmirsənsə
- "I'm not familiar with that specific technology, but based on general principles, I'd..."
- "I haven't used this in production, but I understand the concept."
- "Can I think out loud for a moment?"
- "Let me take a different angle."

### Kod yazarkən
- "Let me start with the happy path."
- "I'll handle edge cases after getting the basic logic right."
- "Let me write the test cases first."
- "I'm going to assume X for now; we can revisit that."

---

## 13. Tez-tez İşlədilən Abreviyaturlar

| Abreviyatura | Açılışı | Mənası |
|--------------|---------|--------|
| **API** | Application Programming Interface | Proqramlaşdırma interfeysi |
| **SDK** | Software Development Kit | İnkişaf paketi |
| **CI/CD** | Continuous Integration / Continuous Deployment | Davamlı inteqrasiya / təhvil |
| **CRUD** | Create, Read, Update, Delete | Yaradılma, oxunma, yenilənmə, silinmə |
| **DRY** | Don't Repeat Yourself | Özünü təkrarlama |
| **KISS** | Keep It Simple, Stupid | Sadə saxla |
| **YAGNI** | You Aren't Gonna Need It | Lazım olmayacaq |
| **MVC** | Model-View-Controller | Model-Görünüş-Nəzarətçi |
| **MVP** | Minimum Viable Product | Minimum işləyən məhsul |
| **POC** | Proof of Concept | Konsept sübutu |
| **QA** | Quality Assurance | Keyfiyyət təminatı |
| **SLA** | Service Level Agreement | Xidmət razılaşması |
| **SLO** | Service Level Objective | Xidmət məqsədi |
| **SLI** | Service Level Indicator | Xidmət göstəricisi |
| **RCA** | Root Cause Analysis | Kökündə olan səbəb analizi |
| **TDD** | Test-Driven Development | Test əsaslı inkişaf |
| **OOP** | Object-Oriented Programming | Obyekt əsaslı proqramlaşdırma |
| **PR** | Pull Request | Pull sorğusu |
| **RFC** | Request for Comments | Rəy sorğusu |
| **YTD** | Year to Date | Bu ilə qədər |
| **EOD** | End of Day | Günün sonu |
| **EOW** | End of Week | Həftənin sonu |
| **ETA** | Estimated Time of Arrival | Təxmini bitmə vaxtı |
| **FYI** | For Your Information | Məlumat üçün |
| **TBD** | To Be Determined | Sonra müəyyən ediləcək |
| **TL;DR** | Too Long; Didn't Read | Çox uzundur; oxumadım (xülasə) |
| **ASAP** | As Soon As Possible | Mümkün qədər tez |

---

## 14. Praktik Hazırlıq — 10 Cümlə

Bu 10 cümləni hazır bil — müsahibə və gündəlik işdə lazımdır:

1. "Let me **trace through the code** to find the issue."
2. "We need to **add monitoring** before we ship this."
3. "This PR has a **merge conflict** — could you **rebase**?"
4. "Consider adding a **feature flag** so we can **roll it back** if needed."
5. "The **p99 latency** spiked after the last deploy."
6. "Let's **sync up** on the architecture decision before Friday."
7. "We should write a **post-mortem** for yesterday's incident."
8. "I'm **blocked** on the DB migration — waiting on infra."
9. "What's the **blast radius** if this fails?"
10. "Let me **loop in** the security team on this change."

---

## Əlaqəli Fayllar

- [Technology Vocabulary (basic)](technology.md)
- [Software Engineering Deep](../../topics/software-engineering-deep.md)
- [Tech Meetings](../../topics/tech-meetings.md)
- [Technical Discussion Phrases](../../../skills/speaking/technical-discussion-phrases.md)
- [System Design Discussion](../../../skills/speaking/system-design-discussion.md)
- [Tech Terms Pronunciation](../../../skills/pronunciation/tech-terms.md)
- [Meeting / Standup English](../../../skills/speaking/meeting-standup-english.md)
