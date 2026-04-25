# Senior Developer Öyrənmə Bələdçisi

Backend developer kimi bilikləri dərinləşdirmək, sistem dizaynını mənimsəmək və yeni texnologiyalara (Java, Go) keçid etmək üçün strukturlaşdırılmış bilik bazası. Hər folder müstəqil mövzudur, amma hamısı bir-birilə əlaqəlidir.

> **Toplam: ~2200+ markdown fayl, 20 folder.** Azərbaycan dilində izah + ingilis texniki terminlər.

---

## İstifadə Marşrutları

| Məqsəd | Marşrut |
|--------|---------|
| **Müsahibəyə 4 həftə qalıb** | [english/4-week-interview-prep-plan.md](english/4-week-interview-prep-plan.md) → `system-design/` ilk 20 → `java/comparison/` |
| **Laravel-dən Java/Spring-ə keçid** | `java/comparison/` (dil + framework yan-yana) → `java/core/` → `java/spring/` → `app/spring/` |
| **Laravel-dən Go-ya keçid** | `go/` (01-dən 74-ə qədər, PHP analogiyaları ilə) → `app/golang/` |
| **System design sualı** | `system-design/` (95 ssenari) → `case-studies/` (real şirkətlər) |
| **Prod-da incident** | `troubleshooting/` → `devops/` → `lists/` cheatsheet |
| **İngilis dili müsahibəsi** | `english/` (vocab, speaking, STAR method, top-50 sual) |
| **AI/LLM layihəsi** | `ai/` (Claude API, RAG, agents, production) |

---

## Folderlər

### Proqramlaşdırma dilləri

| Folder | Fayl | Nə üçün |
|--------|------|---------|
| [php/](php/) | 329 | PHP 8.3/8.4, Laravel 11, Octane, modern async. 200+ topik + 35 müsahibə ssenarisi + 60 use-case. |
| [java/](java/README.md) | 352 | Java core, Spring Boot 3.3+, Cloud, mikroservislər. Alt qovluqlar: `core/`, `spring/`, `advanced/`, `comparison/`. |
| [go/](go/README.md) | 75 | Go dilini sıfırdan professional arxitektura səviyyəsinə qədər. 74 mövzu, PHP/Laravel analogiyaları ilə. |

### Müqayisə və Öyrənmə

| Folder | Fayl | Nə üçün |
|--------|------|---------|
| [java/comparison/](java/comparison/) | 140 | **Java/Spring vs PHP/Laravel** yan-yana — 45 dil + 88 framework mövzusu. Hər mövzuda iki dilin fərqi izah edilir. |
| [app/](app/) | 254 | Eyni CRUD tətbiqi **Laravel + Spring + Golang** ilə. Docker, test, migrasiya daxil. Müqayisəli öyrənmək üçün ideal. |

### System Design və Arxitektura

| Folder | Fayl | Nə üçün |
|--------|------|---------|
| [system-design/](system-design/README.md) | 95 | 94 klassik ssenari (URL shortener → live auction, Raft, multi-region, vector DB). Diaqram + trade-off. |
| [case-studies/](case-studies/README.md) | 34 | Meta, Netflix, Uber, Stripe, Discord, Shopify — arxitektura seçimləri və **niyə** belə seçdikləri. |
| [structure/](structure/) | 20 | Clean Architecture, DDD, CQRS, Event Sourcing, Hexagonal, Event-Driven. |

### İnfrastruktur və Əməliyyat

| Folder | Fayl | Nə üçün |
|--------|------|---------|
| [docker/](docker/README.md) | 52 | Docker, Docker Compose, Kubernetes. Laravel containerization, prod Dockerfile, FPM tuning, CI/CD. |
| [devops/](devops/) | 38 | CI/CD (GitHub Actions, Jenkins, GitLab), Linux dərin (process/network/disk/shell), Nginx. |
| [networking/](networking/) | 41 | TCP/IP, DNS, TLS, HTTP/2/3, WebSocket, load balancing, proxy. |
| [troubleshooting/](troubleshooting/) | 33 | Prod incident playbook-ları — cache stampede, 5 whys, binary-search debugging, postmortem. |

### Texniki Mövzular

| Folder | Fayl | Nə üçün |
|--------|------|---------|
| [sql/](sql/) | 80 | SELECT/JOIN/DML-dən window functions, MVCC, partitioning, pgvector-ə qədər. 78 mövzu, easy→hard. |
| [git/](git/) | 33 | Rebase vs merge, cherry-pick, reflog, bisect, hooks, monorepo strategiyaları. |
| [testing/](testing/) | 42 | Unit/integration/e2e, TDD, test doubles, Pest, JUnit 5, Testcontainers, mutation testing. |
| [dsa/](dsa/) | 46 | Alqoritm və data strukturları — Big-O, array/string/graph problem pattern-ləri. |
| [lists/](lists/) | 23 | Cheatsheet-lər: docker/git/linux/nginx/kafka/k8s/postgres/bash/vim. |

### Bacarıq və Soft Skills

| Folder | Fayl | Nə üçün |
|--------|------|---------|
| [ai/](ai/) | 102 | Claude API, MCP, RAG, agents, fine-tuning, red teaming, production observability. |
| [soft-skills/](soft-skills/) | 21 | Code review, conflict resolution, design review, async communication — B1 ingiliscə şablonlar. |
| [english/](english/README.md) | 556 | A2/B1 leksika, qrammatika, speaking/writing. 196+ comparison fayl (to/for, its/your/their və s.). |

---

## Müsahibəyə Hazırlıq

### Laravel Senior → Java/Spring keçid (12 həftə)

1. `java/comparison/languages/` — Java sintaksis fərqləri (1 həftə)
2. `java/comparison/frameworks/` — Spring vs Laravel (3 həftə)
3. `java/core/` + `java/spring/` + `app/spring/` (4 həftə)
4. `system-design/` + `case-studies/` (2 həftə)
5. `english/skills/speaking/` + STAR method (2 həftə)

### Laravel Senior → Go keçid (8 həftə)

1. `go/` 01-15 — sintaksis əsasları (1 həftə)
2. `go/` 16-40 — OOP, HTTP, database (2 həftə)
3. `go/` 41-60 — concurrency, goroutines, patterns (2 həftə)
4. `go/` 61-74 — mikroservislər, gRPC, deploy (2 həftə)
5. `app/golang/` — tam işlək tətbiq (1 həftə)

### Müsahibə 1 həftə qalıb (sprint)

- **Gün 1-2**: `lists/` cheatsheet + `java/comparison/README.md`
- **Gün 3**: `system-design/` ilk 10 ssenari
- **Gün 4**: `troubleshooting/` + `devops/` Linux əsasları
- **Gün 5**: `soft-skills/` + `english/roadmap-interview.md`
- **Gün 6**: Mock — `english/skills/speaking/top-50-interview-questions.md`
- **Gün 7**: Dincəl.

### Production Mühəndisi Gündəlik Reference

- Command lazımdı → `lists/`
- Bug var → `troubleshooting/`
- Dizayn qərarı → `structure/` + `case-studies/`
- Code review yazıram → `soft-skills/code-review.md` + `english/skills/writing/`
- AI layihəsi qururam → `ai/`

---

## Repo Konvensiyaları

- **Dil**: izah azəricə, kod və texniki terminlər ingiliscə.
- **Nömrələnmə**: `01-xxx.md`, `02-xxx.md` — oxu sırası (easy → hard). Nömrəsizlər reference fayllardır.
- **Səviyyələr**: ⭐ Junior · ⭐⭐ Middle · ⭐⭐⭐ Senior · ⭐⭐⭐⭐ Lead · ⭐⭐⭐⭐⭐ Architect
- **README.md**: hər folderin öz indeksi var — mövzuları səviyyəyə görə qruplaşdırır, reading path verir.
- **`lists/`**: qısa cheatsheet. Folderin özü dərin izah.
- **`app/`**: eyni biznes məntiqinin 3 dildə (PHP/Laravel, Java/Spring, Go) implementasiyası.
