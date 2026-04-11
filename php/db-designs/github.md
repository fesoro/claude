# GitHub — DB Design & Technology Stack

## Actual DB Stack

```
┌─────────────────────────────────────────────────────────────────┐
│                     GitHub Database Stack                        │
├──────────────────────┬──────────────────────────────────────────┤
│ MySQL (Vitess)       │ Core: repos, users, PRs, issues, stars   │
│ Git object storage   │ Git data (objects, refs) — custom        │
│ Redis                │ Session, cache, job queues               │
│ Elasticsearch        │ Code search, issue search                │
│ Amazon S3            │ Release assets, LFS (large file storage) │
│ Kafka                │ Event streaming, webhooks pipeline       │
│ ClickHouse           │ Analytics (stars, views, clones)         │
│ Spokes               │ Custom Git replication system            │
└──────────────────────┴──────────────────────────────────────────┘
```

---

## Git Storage Arxitekturası

```
Git-in özü bir database-dir!

Git object store:
  4 tip: blob, tree, commit, tag
  Content-addressed: SHA-1/SHA-256 hash
  Immutable: bir dəfə yazılır, dəyişmir
  
  blob:   fayl məzmunu
  tree:   directory (fayl + alt-directory listesi)
  commit: tree + parent commit + metadata
  tag:    commit reference with name

Nümunə:
  echo "Hello" | git hash-object --stdin
  → e965047ad7c57865823c7d992b1d046ea66edf78
  
  Bu hash = blob object key
  Fayl: .git/objects/e9/65047ad7c57865823c7d992b1d046ea66edf78

GitHub-un challenge-i:
  100M+ repositories
  Hər repo: ayrı Git object store
  Distributed storage + replication lazım

GitHub Spokes (custom):
  Git repository replication system
  Multiple data centers
  Consistency model: strong (git push = all replicas updated)
  
DGit → Spokes evolution:
  2013: DGit (first custom git storage)
  2016: Spokes (redesign)
  Active-active: any DC can serve reads
  Push: quorum-based commit
```

---

## MySQL Schema (via Vitess)

```sql
-- ==================== USERS / ORGANIZATIONS ====================
CREATE TABLE accounts (
    id          BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    login       VARCHAR(39) UNIQUE NOT NULL,  -- max 39 chars
    type        ENUM('user', 'org', 'bot') NOT NULL DEFAULT 'user',
    email       VARCHAR(255),
    
    -- Profile
    name        VARCHAR(255),
    bio         VARCHAR(160),
    company     VARCHAR(255),
    location    VARCHAR(255),
    blog        VARCHAR(255),
    twitter_username VARCHAR(15),
    
    avatar_url  TEXT,
    
    -- Stats (denormalized)
    public_repos INT DEFAULT 0,
    followers    INT DEFAULT 0,
    following    INT DEFAULT 0,
    
    -- Plan
    plan        ENUM('free', 'pro', 'team', 'enterprise') DEFAULT 'free',
    
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ==================== REPOSITORIES ====================
CREATE TABLE repositories (
    id              BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    owner_id        BIGINT UNSIGNED NOT NULL,
    name            VARCHAR(100) NOT NULL,
    full_name       VARCHAR(140) GENERATED ALWAYS AS
                    (CONCAT(owner_login, '/', name)) STORED,
    
    description     VARCHAR(350),
    homepage        VARCHAR(255),
    
    -- Visibility
    is_private      BOOLEAN DEFAULT FALSE,
    is_fork         BOOLEAN DEFAULT FALSE,
    forked_from_id  BIGINT UNSIGNED REFERENCES repositories(id),
    
    -- Defaults
    default_branch  VARCHAR(255) DEFAULT 'main',
    
    -- Language (dominant)
    language        VARCHAR(100),
    
    -- Features (enabled/disabled)
    has_issues      BOOLEAN DEFAULT TRUE,
    has_wiki        BOOLEAN DEFAULT TRUE,
    has_projects    BOOLEAN DEFAULT TRUE,
    has_discussions BOOLEAN DEFAULT FALSE,
    
    -- Stats (denormalized — batch updated)
    stargazer_count INT DEFAULT 0,
    fork_count      INT DEFAULT 0,
    watcher_count   INT DEFAULT 0,
    open_issues_count INT DEFAULT 0,
    size_kb         INT DEFAULT 0,
    
    -- Topics
    topics          JSON,   -- ['php', 'laravel', 'open-source']
    
    -- License
    license_spdx    VARCHAR(50),
    
    archived_at     DATETIME,
    pushed_at       DATETIME,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE INDEX idx_owner_name (owner_id, name),
    INDEX idx_full_name (full_name)
) ENGINE=InnoDB;

-- ==================== STARS ====================
CREATE TABLE stars (
    user_id     BIGINT UNSIGNED NOT NULL,
    repo_id     BIGINT UNSIGNED NOT NULL,
    starred_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, repo_id),
    INDEX idx_repo (repo_id, starred_at DESC)
) ENGINE=InnoDB;

-- ==================== ISSUES ====================
CREATE TABLE issues (
    id          BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    repo_id     BIGINT UNSIGNED NOT NULL,
    number      INT UNSIGNED NOT NULL,       -- repo-scoped: #42
    title       VARCHAR(255) NOT NULL,
    body        MEDIUMTEXT,
    
    author_id   BIGINT UNSIGNED NOT NULL,
    assignee_ids JSON,          -- [user_id, ...]
    
    state       ENUM('open', 'closed') DEFAULT 'open',
    state_reason ENUM('completed', 'not_planned', 'reopened'),
    
    is_pull_request BOOLEAN DEFAULT FALSE,
    
    -- Labels
    label_ids   JSON,           -- [label_id, ...]
    
    -- Milestone
    milestone_id BIGINT UNSIGNED,
    
    closed_at   DATETIME,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE INDEX idx_repo_number (repo_id, number),
    INDEX idx_author (author_id),
    INDEX idx_state  (repo_id, state, created_at DESC)
) ENGINE=InnoDB;

-- ==================== PULL REQUESTS ====================
CREATE TABLE pull_requests (
    id          BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    issue_id    BIGINT UNSIGNED NOT NULL REFERENCES issues(id),
    repo_id     BIGINT UNSIGNED NOT NULL,
    
    -- Branches
    head_repo_id    BIGINT UNSIGNED,
    head_branch     VARCHAR(255) NOT NULL,
    head_sha        CHAR(40) NOT NULL,
    
    base_repo_id    BIGINT UNSIGNED NOT NULL,
    base_branch     VARCHAR(255) NOT NULL,
    base_sha        CHAR(40) NOT NULL,
    
    -- Merge
    state           ENUM('open', 'closed', 'merged') DEFAULT 'open',
    merged_at       DATETIME,
    merged_by_id    BIGINT UNSIGNED,
    merge_commit_sha CHAR(40),
    
    -- Review
    review_state    ENUM('pending', 'approved', 'changes_requested',
                         'dismissed') DEFAULT 'pending',
    
    -- Stats
    changed_files   SMALLINT DEFAULT 0,
    additions       INT DEFAULT 0,
    deletions       INT DEFAULT 0,
    
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
```

---

## Code Search: Elasticsearch + Custom

```
GitHub code search: ən çətin problemlərdən biri

2021: GitHub-un "Technology Preview" yeni axtarış
  Custom Elasticsearch setup
  Symbol extraction (function names, classes)
  
Index per repository:
  Hər repo: ayrı Elasticsearch index
  100M+ repos → 100M+ indexes (too many!)
  
Çözüm: Blackbird (2022, GitHub's custom search)
  Custom low-level index over Elasticsearch
  Code-specific optimizations
  Symbol indexing (not just text)
  
Code search features:
  Regular expressions
  Language filter
  Path filter
  Symbol search (function definitions)
  
Scale:
  200TB+ of code indexed
  45M+ public repos
  Sub-second query latency
```

---

## GitHub Actions: Pipeline Storage

```
GitHub Actions: CI/CD platform

Workflow definition:
  .github/workflows/ci.yml → Git repository (stored in Spokes)

Workflow runs:
  runs → MySQL (metadata)
  Logs → Azure Blob Storage (not S3 — GitHub = Microsoft now)
  Artifacts → Azure Blob Storage

Queuing:
  Redis: job queue
  Workers: pull jobs, execute in containers

State machine:
  queued → in_progress → success/failure/cancelled

Artifacts:
  After job: upload to Azure Blob
  Retention: 90 days default
  Max size: 500MB per artifact
```

---

## Webhook Delivery System

```
GitHub webhooks: "notify external system on events"
  Push, PR, Issue, Star, Release...

Pipeline:
  Git push → Event generated → Kafka
  Kafka consumer → Webhook delivery service
  → HTTP POST to subscriber URL
  
Delivery guarantees:
  At-least-once delivery
  Retry: exponential backoff (up to 24 hours)
  DLQ: failed deliveries → review

MySQL storage:
  webhook_deliveries:
    id, hook_id, event, payload (JSON), response_code,
    status (pending/delivered/failed), created_at
    
  Retention: 7 days (viewing in GitHub UI)

Rate limiting:
  Per webhook: max N deliveries/sec
  Circuit breaker: if endpoint fails → slow down
```

---

## Scale Faktları

```
Numbers (2023):
  100M+ developer accounts
  420M+ repositories
  3.5B+ contributions/year
  ~100M pull requests/year
  
  Git storage (Spokes):
  Multiple exabytes of git data
  Multi-datacenter replication
  
  Code search:
  200TB+ code indexed
  45M+ public repos
  
  Actions:
  Billions of workflow runs/year
  
  MySQL via Vitess:
  Petabytes of metadata
  Hundreds of MySQL shards
```

---

## GitHub-dan Öyrəniləcəklər

```
1. Git = distributed database:
   Content-addressed storage
   Immutable objects
   Branching = cheap copies
   "Git is brilliant DB design"

2. Spokes = custom replication:
   Standard MySQL replication didn't fit
   Custom quorum-based git replication
   Active-active multi-DC

3. Vitess for MySQL:
   Same as YouTube (common owner: Google/Microsoft)
   Transparent sharding

4. At-least-once webhooks:
   Don't promise exactly-once delivery
   Design recipients for idempotency

5. Code search is hard:
   Text search ≠ code search
   Symbols, syntax, cross-file references
   Custom indexes needed
```
