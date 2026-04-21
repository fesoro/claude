# Senior Developer Interview & Öyrənmə Bələdçisi

Bu repo senior backend developer müsahibələrinə (PHP/Laravel, Java/Spring, Go) və gündəlik mühəndislik işinə tam hazırlıq üçün strukturlaşdırılmış bilik bazasıdır. Hər folder müstəqil mövzudur — amma hamısı bir-biri ilə əlaqəlidir (məs. `comparison` → `app` → `case-studies` → `system-design`).

> Toplam: **2000+ markdown fayl, ~20 böyük mövzu folderi**. Əksəriyyəti Azerbaycanca izahla + İngilis texniki terminlərlə yazılıb.

## İstifadə marşrutları

- **Müsahibəyə 4 həftə qalıb** → [english/4-week-interview-prep-plan.md](english/4-week-interview-prep-plan.md) + [java/PROGRESS-NEW.md](java/PROGRESS-NEW.md) + `system-design/` ilk 20 fayl.
- **Laravel-dən Spring-ə keçid** → `comparison/` (dil + framework yan-yana), sonra `app/laravel` vs `app/spring` (eyni CRUD, hər iki dildə).
- **System design sualı gəldi** → `system-design/` (94 ssenari) + `case-studies/` (kimin necə etdiyinə bax).
- **Prod-da incident** → `troubleshooting/` (playbook) + `devops/` (Linux/CI/CD) + `lists/` (command cheatsheet).
- **İngilis dili müsahibəsi** → `english/` (A2/B1 vocab, speaking, interview Q&A, STAR method).
- **AI/LLM layihəsi** → `ai/` (Claude API, RAG, agents, observability).

## Əsas folderlər

### Öyrənmə və müqayisə

| Folder | Fayl sayı | Nə üçün |
|--------|-----------|---------|
| [comparison/](comparison/README.md) | 114 | **Java/Spring vs PHP/Laravel** yan-yana — dil səviyyəsi (35 mövzu) + framework səviyyəsi (78 mövzu). Laravel developer üçün Spring-ə körpü. |
| [java/](java/README.md) | 182 | Java core + Spring Boot 3.3+ dərin mövzular. `PROGRESS-NEW.md` ilə roadmap. |
| [php/](php/) | 329 | 200+ topik + 35 müsahibə ssenarisi + 60 use-case + DB dizaynları. PHP 8.3/8.4, Laravel 11, Octane, modern async. |
| [app/](app/) | 254 | Eyni CRUD tətbiqi **Laravel + Spring + Golang** ilə. Hər biri Docker, test, migrasiya ilə işlək. |

### System design & arxitektura

| Folder | Fayl sayı | Nə üçün |
|--------|-----------|---------|
| [system-design/](system-design/README.md) | 95 | 94 klassik ssenari (URL shortener → live auction, Raft, multi-region, vector DB). Hər faylda diaqram + trade-off. |
| [case-studies/](case-studies/README.md) | 34 | Meta, Netflix, Uber, Stripe, Discord, Shopify və s. şirkətlərin arxitektura seçimləri və **NİYƏ** belə seçdikləri. |
| [structure/](structure/) | 20 | Clean Architecture, DDD, CQRS, Event Sourcing, Hexagonal, Event-Driven. |
| [comparison/frameworks/](comparison/frameworks/) | 78 | Spring vs Laravel: Security, JPA, WebFlux, Kafka, gRPC, Modulith, AOT Native və s. |

### İnfrastruktur və əməliyyat

| Folder | Fayl sayı | Nə üçün |
|--------|-----------|---------|
| [docker/](docker/README.md) | 35 | Docker + Kubernetes (RBAC, HPA/VPA, CNI, service mesh, jobs, observability). |
| [devops/](devops/) | 43 | CI/CD (GitHub Actions, Jenkins, GitLab), Linux dərin (process/network/disk/shell), Nginx. |
| [networking/](networking/) | 41 | TCP/IP, DNS, TLS, HTTP/2/3, WebSocket, load balancing, proxy. |
| [troubleshooting/](troubleshooting/) | 33 | Prod incident playbook-ları — cache stampede, 5 whys, binary-search debugging, postmortem. |

### Əsas texnologiyalar

| Folder | Fayl sayı | Nə üçün |
|--------|-----------|---------|
| [sql/](sql/) | 36 | Joinlər, indekslər, N+1, explain plan, window funksiyaları, transaction izolyasiya. |
| [git/](git/) | 33 | Rebase vs merge, cherry-pick, reflog, bisect, hooks, monorepo strategiyaları. |
| [testing/](testing/) | 42 | Unit/integration/e2e, TDD, test doubles, Pest, JUnit 5, Testcontainers, mutation testing. |
| [dsa/](dsa/) | 46 | Alqoritm və data strukturları — Big-O, array/string/graph problem pattern-ləri. |
| [lists/](lists/) | 23 | Cheatsheet-lər: docker/git/linux/nginx/kafka/k8s/postgres/bash/vim + dil topik siyahıları. |

### Ixtisas və soft skills

| Folder | Fayl sayı | Nə üçün |
|--------|-----------|---------|
| [ai/](ai/) | 56 | Claude API, MCP, RAG, agents, fine-tuning, production observability, prompt injection müdafiəsi. |
| [soft-skills/](soft-skills/) | 21 | Code review, conflict resolution, design review, async communication — B1 ingiliscə cümlə şablonları ilə. |
| [english/](english/README.md) | 556 | A2/B1 leksika, qrammatika, speaking/writing/listening/reading. 196+ comparison (to/for, its/your/their, əsasən azəri dilində qrup izahı). |

## Müsahibəyə hazırlıq kombinasiyaları

### Laravel Senior → Spring junior-mid keçid (12 həftə)
1. `comparison/languages/` (35 fayl, 1 həftə)
2. `comparison/frameworks/` (78 fayl, 3 həftə)
3. `java/topics/` + `app/spring/` işlə (4 həftə)
4. `system-design/` + `case-studies/netflix.md` (2 həftə)
5. `english/skills/speaking/` + STAR method (2 həftə)

### Müsahibə 1 həftəsi qalıb (sprint)
- Gün 1-2: `lists/` cheatsheet-ləri + `comparison/README.md`
- Gün 3: `system-design/` ilk 10 ssenari
- Gün 4: `troubleshooting/` + `devops/05-09` Linux
- Gün 5: `soft-skills/` + `english/roadmap-interview.md`
- Gün 6: Mock interview — `english/skills/speaking/top-50-interview-questions.md`
- Gün 7: Dincəl. Həddindən artıq oxuma gecə.

### Production mühəndisi gündəlik reference
- Command lazımdı → `lists/`
- Bug var → `troubleshooting/`
- Dizayn qərarı → `structure/` + `case-studies/`
- Code review yazıram → `soft-skills/code-review.md` + `english/skills/writing/`

## Repo konvensiyaları

- **Dil**: izah azəricə, kod və texniki terminlər ingiliscə. Kod nümunələri işlək və copy-paste hazır.
- **Nömrələnmə**: `01-xxx.md`, `02-xxx.md` — oxu sırası. Nömrə olmayanlar reference fayllardır.
- **README.md folder başına**: hər folderin öz oxşar indeksi var (case-studies, comparison, docker, english, java, system-design).
- **`lists/` vs folder**: `lists/` cheatsheet (qısa), folderin özü dərin izah.
- **`app/`**: eyni biznes məntiqinin 3 dildə implementasiyası — müqayisəli öyrənmək üçün.

## Qeyd

Bu repo interview prep + real iş üçün birgə bilik bazasıdır, nəşr olunacaq bir kitab yox. Fayllar vaxtaşırı yenilənir, bəzi yerlər hələ draft ola bilər. Xəta görsən yoxla və düzəlt — commit history kontekst verir.
