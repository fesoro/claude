# GitHub-like Platform Design (Senior)

## İcmal

GitHub-like platform - git-based kod hosting, collaboration və CI/CD xidmətidir. Developerlər kod push edir, başqaları clone edir, pull request (PR) açır, review gedir, CI pipeline işləyir, merge olunur. Platforma həm **distributed version control storage** (git), həm **application layer** (PR, issue, review, actions), həm **object storage** (release, LFS, artifact) birləşdirir.

Tanınmış nümunələr: **GitHub**, **GitLab**, **Bitbucket**, **Gitea**, **Gerrit**. Əsas çətinlik: milyardlarla git obyektini saxlamaq, minlərlə concurrent clone/push-a cavab vermək, monorepo-ları (Linux kernel, Chromium) səmərəli daşımaq və DDoS hücumundan qoruyaraq public API sərgiləmək.


## Niyə Vacibdir

Git repository storage, PR workflow, CI/CD integration, code search — developer tooling platformasının arxitekturası. Spokes replication, git pack format, diff storage — unikal texniki problemlərdir. GitHub, GitLab, Bitbucket-in arxitekturasını başa düşmək tooling şirkətlərindəki rol üçün vacibdir.

## Tələblər

### Funksional tələblər

1. **Repository hosting** - public və private git repos, organization/team hierarchy
2. **Git protocols** - HTTPS (smart HTTP), SSH
3. **Clone/fetch/push/pull** - standart git əməliyyatları
4. **Code browsing** - web-də file tree, blame, history, diff
5. **Pull requests** - branch-dan branch-a dəyişiklik, diff, review, merge
6. **Code review** - line-level comments, approve/request changes
7. **Issues** - bug tracking, labels, assignees, milestones
8. **CI/CD** - workflow-lar, runner-lər, artifact storage
9. **Webhooks** - push/pr/issue event-lərində HTTP callback
10. **Wiki + Releases + Git LFS + code search**

### Non-funksional tələblər

- **Scale** - 100M user, 300M repo, 1B+ git object
- **Availability** - 99.95% web, 99.99% git read; **Durability** - 11× nine
- **Latency** - clone start p95 < 500ms, PR diff render < 2s
- **Security** - private repo leak olmasın, token scope dar olsun
- **Fairness** - bir abuser bütün clone bandwidth yeməsin

## Capacity Estimation (Həcm Təxmini)

| Metric | Dəyər |
|--------|-------|
| Active user | 100M |
| Repository | 300M (80% private, 20% public) |
| Orta repo size | 50 MB (median 5 MB, p99 500 MB, max 50+ GB monorepo) |
| Total git storage | ~15 PB + replication ×3 = 45 PB |
| Clone QPS (peak) | 50,000 |
| Push QPS (peak) | 5,000 |
| API QPS | 500,000 (web + integration) |
| Webhook delivery/day | 5 billion |
| CI minutes/ay | 2 billion |

Clone bandwidth dominant cost-dir: 50K clone/s × 10 MB average = 500 GB/s egress. CDN və pack reuse optimallaşdırma kritikdir.

## High-Level Architecture (Ümumi Arxitektura)

```
                                  ┌─────────────┐
                                  │    CDN      │ (static, release, raw)
                                  └──────┬──────┘
                                         │
   ┌──────────┐    ┌────────────┐    ┌───▼────────┐     ┌──────────────┐
   │  git CLI │───▶│  SSH/HTTPS │───▶│  Git Proxy │────▶│ Fileserver-1 │
   └──────────┘    │    LB      │    │  (routing) │     │ (bare repos) │
                   └────────────┘    └─────┬──────┘     └──────────────┘
                                           │            ┌──────────────┐
   ┌──────────┐    ┌────────────┐          └───────────▶│ Fileserver-2 │
   │ Browser  │───▶│   Web/API  │                       └──────────────┘
   └──────────┘    │  (Rails/Go)│          ┌──────────────────────┐
                   └─────┬──────┘─────────▶│ MySQL (metadata)     │
                         │                 │ - users, repos, PRs  │
                         │                 │ - issues, comments   │
                         │                 └──────────────────────┘
                         │                 ┌──────────────────────┐
                         ├────────────────▶│ Elasticsearch/Zoekt  │
                         │                 │ (code + issue search)│
                         │                 └──────────────────────┘
                         │                 ┌──────────────────────┐
                         ├────────────────▶│ Redis (cache, queue) │
                         │                 └──────────────────────┘
                         │                 ┌──────────────────────┐
                         ├────────────────▶│ S3 (LFS, artifacts,  │
                         │                 │  releases, logs)     │
                         │                 └──────────────────────┘
                         │                 ┌──────────────────────┐
                         └────────────────▶│ Actions Runners      │
                                           │ (hosted + self-host) │
                                           └──────────────────────┘
```

### Əsas layer-lər

1. **Git storage tier** - fileserver node-ları üzərində bare repo-lar, sharded + replicated
2. **Web/API tier** - Rails (GitHub), Go services (GitLab) - UI, REST/GraphQL, permissions
3. **Database tier** - MySQL/PostgreSQL - metadata store
4. **Search tier** - Elasticsearch (issue/PR), Zoekt (GitHub code search), trigram index
5. **Object storage** - S3-compatible - LFS, CI artifact, release binary, log
6. **Job system** - Sidekiq/Resque - webhook delivery, indexing, cleanup
7. **Runner pool** - VM/container sandbox CI job icrası

## Git Storage Tier

### Bare repository layout

Server-də repo "bare" formatda saxlanır - working directory yox, yalnız `.git` məzmunu. Disk strukturu:

```
/data/repositories/
└── ab/
    └── cd/
        └── user-42/
            └── repo-1234.git/
                ├── HEAD
                ├── config
                ├── refs/
                │   ├── heads/main
                │   └── tags/v1.0
                ├── objects/
                │   ├── 34/abc123... (loose)
                │   └── pack/
                │       ├── pack-xyz.pack  (compressed multi-object)
                │       └── pack-xyz.idx
                ├── hooks/
                └── packed-refs
```

Repo-nun `id` hash-inin ilk 4 character-i directory prefix-i yaradır - tək directory-də milyon file olmasın deyə filesystem-i balanslaşdırır.

### Sharding

300M repo tək serverə tutmaz. **Shard key = repo_id**. Router: `shard_id = hash(repo_id) mod N; fileserver = shard_table[shard_id]`. İki yanaşma: **consistent hashing** (rebalance az, amma hot repo eyni shard-da qalır) və **lookup table** (DB-də `primary_fileserver_id`, manual migration mümkün, hot repo dedicated server-ə köçürülə bilər - GitHub bu yolu seçib).

### Replikasiya: GitHub Spokes

GitHub-un git replication layer-inə **Spokes** deyilir (köhnə ad: DGit). Hər repo 3 replika-da (primary + 2 secondary, fərqli DC/rack). **Sync replication** - push yalnız quorum (2/3) yazsa committed.

```
                    ┌──────────────┐
     push ─────────▶│ Primary (A)  │──sync──┬─▶ Replica B
                    └──────────────┘        └─▶ Replica C

Write: client→primary writes pack→forwards to B,C→wait 2/3 ack→
       atomic CAS ref update→reply ok
Read:  any replica (load-weighted); stale→fallback to primary
```

Primary fail olduqda health-monitor replica promote edir, lookup table yenilənir. GitLab analoqu **Gitaly Cluster (Praefect)** - Raft consensus istifadə edir.

### Git operations serving

- **Clone**: `git-upload-pack` - obyektlərdən "pack" yaradır, client-ə stream edir. **Reachability bitmap** (pack-bitmap file) - hansı commit-dən hansı obyektlərə çatılırını O(1)-də tapır.
- **Push**: `git-receive-pack` - client pack göndərir, server quick-quarantine-də saxlayır, hook işə salır, bəyənilərsə main store-a köçürür. Ref update atomic compare-and-swap-dır - race-də loser retry edir.
- **Protokollar**: HTTPS (smart HTTP, token auth), SSH (key-based, developer default).
- **Git protocol v2** - `ls-refs` filter, `ref-prefix refs/heads/feature/*` kimi - monorepo-da clone latency azaldır.

## Pull Request Workflow

```
  feature/login ──commit──▶ commit ──▶ HEAD ─push─▶ origin
                                                     │
                                                     ▼
                                  PR #42: feature/login → main
                                                     │
    ┌───────────────────────────┬────────────────────┴────┬──────────────┐
    ▼                           ▼                         ▼              ▼
  compute diff            trigger CI              notify reviewers   webhooks
  base..head              (workflow run)          (CODEOWNERS)       fire
                                     │
  Render UI: file tree, unified/split diff, inline comments
                                     │
  Reviewer: approve / request changes / comment
                                     │
  CI green + reviews met + branch protection satisfied
                                     │
  User clicks Merge:
    ├─▶ Merge commit (git merge --no-ff)
    ├─▶ Squash (combine into one, reparent on main)
    └─▶ Rebase (replay commits linearly on main)
                                     │
  main ref updated, source branch auto-delete, PR = merged
```

### Diff computation

PR açıldıqda platforma `base` (target branch tip zamanı fork olunanda) və `head` (PR branch tip) arasında **three-way merge base** tapır - `git merge-base base head`. Diff `merge_base..head`-dir, bu istifadəçinin real dəyişikliyini göstərir, target branch-ın yeni commit-lərini daxil etmir.

### Review comments və "line anchoring"

Review comment "File X, line 42" deyə sadə saxlana bilər, amma PR author rebase etsə, line number dəyişir. Həll: comment-i `(commit_sha, file_path, line)` ilə saxla, force-push zamanı yeni commit-də həmin line-ı **position recomputation** alqoritmi ilə tap (diff hunk-larda əvvəl/sonra pattern match). Uyğunluq tapılmasa, comment "outdated" kimi markalanır.

### Merge strategies

- **Merge commit** - tarixçə iki-ana saxlanır, branch topologiyası görünür
- **Squash** - bütün commit-lər bir commit-ə birləşir, linear history
- **Rebase** - commit-lər bir-bir main-ə tətbiq olunur, merge commit yox
- **Fast-forward only** - yalnız conflict-siz rebase qəbul olunur

Böyük team-lər adətən squash seçir - PR = 1 commit, bisect asan.

### Branch protection

- Required reviews (2 approval, CODEOWNERS məcburi)
- Required status checks (CI green, security scan pass)
- No force push, no delete
- Require linear history
- Require signed commits

## Data Model (Sadələşdirilmiş)

```sql
users(id, username, email, password_hash, created_at)
organizations(id, name, billing_plan)
org_members(org_id, user_id, role)  -- owner/admin/member

repositories(id, owner_id, owner_type, name, visibility, default_branch,
             size_kb, primary_fileserver_id, created_at)
repo_permissions(repo_id, user_id, role)  -- read/write/admin

branches(repo_id, name, tip_sha, protected)  -- synced from git refs
commits(repo_id, sha, author_id, committer_id, message, parents, ts)  -- indexed metadata

pull_requests(id, repo_id, number, author_id, base_branch, head_branch,
              base_sha, head_sha, state, merged_at, merged_by)
pr_reviews(id, pr_id, reviewer_id, state, submitted_at)  -- approved/changes_requested
pr_review_comments(id, pr_id, review_id, commit_sha, path, line, body)

issues(id, repo_id, number, author_id, title, body, state, milestone_id)
issue_labels(issue_id, label_id)
issue_comments(id, issue_id, author_id, body, created_at)

workflows(id, repo_id, path, name)
workflow_runs(id, workflow_id, head_sha, status, conclusion, started_at)
jobs(id, run_id, runner_id, name, status, conclusion, logs_s3_key)
```

Repo ID PR/issue number deyil - PR `number` repo daxilində scope-dir (1-dən başlayır), global `id` isə unique. Composite key: `(repo_id, number)`.

## CI/CD Subsystem

```
  push event fires ───▶ Workflow parser reads .github/workflows/*.yml
                                        │
                                        ▼
                               enqueue jobs to Job Queue
                                        │
                            ┌───────────┴────────────┐
                            ▼                        ▼
                   Hosted runner pool       Self-hosted runner
                   (Linux/Win/macOS VMs)    (customer infra)
                            │                        │
                            └─────────┬──────────────┘
                                      ▼
                            Job runs in clean VM/container
                                      │
                      ┌───────────────┼──────────────────┐
                      ▼               ▼                  ▼
                stream logs     upload artifacts     report status
                to log service  to S3                to PR checks
```

Runner lifecycle: job gəlir → ephemeral VM başladılır (1-2s startup - pre-warmed pool) → git clone + job step → artifact upload → VM destroy. Ephemeral olma cross-job leak-i bloklayır.

## Code Search

Text search `grep` across 300M repo `O(total_bytes)` - mümkün deyil. Həll: **trigram index** və **symbol index**.

- **Zoekt** (GitHub) - positional trigram inverted index, regex-aware. `foo.bar` query-si `foo`, `oo.`, `o.b`, `.ba`, `bar` trigram-larının kəsişməsini tapır, sonra false positive-ləri filter edir.
- **Sourcegraph** - similar architecture, plus code intelligence (go-to-definition)
- **Sharding** - index repo size və access frequency-yə görə shard olunur, query scatter-gather ilə aggregate olur
- **Symbol search** - ctags/tree-sitter ilə function/class/variable extract, ayrı index

Issue search - Elasticsearch istifadə olunur (text + faceted filter: state, label, assignee).

## Git LFS, Webhooks, Access Control

**Git LFS** - Böyük binary (video, image, ML model) git-də yavaşlayır. LFS həll: real blob S3-ə gedir, repo-da yalnız pointer file qalır (`version ..., oid sha256:..., size ...`). Clone zamanı pointer gəlir, checkout-da lazy blob fetch. Per-repo quota (1 GB free), bandwidth ayrı.

**Webhooks** - push/PR/issue event-lərində HTTP POST customer-ə: (1) **Async queue-based** - event enqueue, Sidekiq worker HTTP-ni icra edir. (2) **Exponential backoff retry** (1s, 2s, 4s... max 24h) 5xx və ya timeout-da. (3) **HMAC signature** - `X-Hub-Signature-256: sha256=HMAC(secret, body)`. (4) **Delivery log + manual redeliver**. (5) **Per-endpoint rate limit**.

**Access Control** - visibility (public/private/internal), repo roles (read/triage/write/maintain/admin), org/team hierarchy, fine-grained PAT (scope + expiry + IP allowlist), GitHub App (installation token 1h TTL, repo-scoped), SSH keys + deploy keys.

## Performance və Scale Challenges

**Large monorepo** (Linux kernel 1.3M commit, 5 GB): **shallow clone** (`--depth 1`), **partial clone** (`--filter=blob:none`), **sparse checkout** (path selection), reachability bitmap, commit graph file.

**Hot repo** (react, kubernetes push zamanı minlərlə CI fetch) - pack reuse, CDN (Cloudflare/Fastly) static pack-ləri cache.

**Clone bandwidth abuse** (AI scraping bot) - per-IP/token rate limit, anomaly detection (1000 clone/dəq → block).

**DDoS** - rate limit (5000 req/hour per user), Cloudflare WAF, abuse ML classifier.

**Replication lag** - trailing replica detection, pool-dan çıxar, repair sonra qayıt.

## Laravel Webhook Receiver Example

Laravel app GitHub-dan webhook qəbul edir, HMAC signature verify edir, queue-ya göndərir:

```php
// routes/web.php
Route::post('/webhooks/github', [GitHubWebhookController::class, 'handle']);

// app/Http/Controllers/GitHubWebhookController.php
class GitHubWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $signature = $request->header('X-Hub-Signature-256', '');
        $payload   = $request->getContent();
        $secret    = config('services.github.webhook_secret');

        $expected = 'sha256=' . hash_hmac('sha256', $payload, $secret);
        if (!hash_equals($expected, $signature)) {
            abort(401, 'invalid signature');
        }

        $event      = $request->header('X-GitHub-Event');
        $deliveryId = $request->header('X-GitHub-Delivery');

        // Idempotency: eyni delivery-id ikinci dəfə gəlsə skip
        if (cache()->has("gh:delivered:{$deliveryId}")) {
            return response()->json(['status' => 'duplicate']);
        }
        cache()->put("gh:delivered:{$deliveryId}", 1, now()->addDays(7));

        ProcessGitHubEvent::dispatch($event, json_decode($payload, true));
        return response()->json(['status' => 'queued'], 202);
    }
}

// app/Jobs/ProcessGitHubEvent.php
class ProcessGitHubEvent implements ShouldQueue
{
    public function __construct(public string $event, public array $payload) {}

    public function handle()
    {
        match ($this->event) {
            'push'         => $this->onPush(),
            'pull_request' => $this->onPullRequest(),
            default        => null,
        };
    }
}
```

GitHub App installation token (repo-scoped, 1h TTL):

```php
// 1. JWT signed with private key (app-level auth)
$jwt = JWT::encode([
    'iat' => time(), 'exp' => time() + 600,
    'iss' => config('services.github.app_id'),
], file_get_contents(storage_path('github-app.pem')), 'RS256');

// 2. Exchange for installation token
$token = Http::withToken($jwt, 'Bearer')
    ->post("https://api.github.com/app/installations/{$installationId}/access_tokens")
    ->json('token');

// 3. Use for repo API call (CI status)
Http::withToken($token)->post("https://api.github.com/repos/{$repo}/statuses/{$sha}", [
    'state' => 'success', 'context' => 'ci/laravel', 'description' => 'Tests passed',
]);
```

## Real-World və Failure Modes

**GitHub Spokes** - 3-way sync git replication, custom protocol. **GitLab Gitaly** - Go RPC service, Gitaly Cluster (Praefect) Raft ilə replication. **GitLab Geo** - cross-region read replica. **Google Piper** - monolithic internal repo, custom VCS.

| Failure | Recovery |
|---------|----------|
| Fileserver crash | Promote replica, update lookup table, re-replicate |
| Push race (same ref) | CAS fail; loser retries fetch+rebase+push |
| PR merge conflict | User resolves locally, force-push branch |
| Webhook receiver down | Exponential backoff retry, mark failed after 24h |
| Runner pool exhausted | Auto-scale VM pool, reject with 503 if over limit |
| LFS object missing | S3 404; prompt re-upload |

## Praktik Tapşırıqlar

**Q1: Git repo-ları server-də necə storage və shard edilir?**
Repo "bare" formatda saxlanır - working directory yox, yalnız `.git` content (refs, objects, hooks). Obyektlər loose (tək file) və pack (birləşdirilmiş compressed) olur, `git gc` periodik pack edir. Directory-də repo_id hash-inin ilk 2-4 char sub-directory kimi istifadə olunur (`/ab/cd/repo-1234.git`) - filesystem balansı üçün. 300M repo tək serverə tutmaz, repo_id hash ilə fileserver node-lara shardlanır. İki yanaşma: consistent hashing (rebalance az) və lookup table (DB-də `primary_fileserver_id`, manual migration mümkün, hot repo ayrıca köçürülə bilər). GitHub lookup table seçib - hot repo management və DR orchestration daha nəzarətli.

**Q2: GitHub necə repo-nu replicate edir və primary failure-dən necə qayıdır?**
**Spokes** - GitHub-un replication layer-i. Hər repo 3 replika (primary + 2 secondary, fərqli DC/rack). Push sync replicate: primary packfile yazır, hər iki replica-ya forward edir, quorum (2/3) ack gözləyir, sonra ref-i atomic CAS ilə yeniləyir və client-ə ok deyir. Commit "pushed" olanda artıq 2 node-da durub - tək node itsəniz data itkisi yox. Read istənilən replica-dan (load-weighted). Primary fail olsa health monitor fresh replica promote edir (replication log comparison), lookup table yenilənir. GitLab analoqu **Gitaly Cluster (Praefect)** Raft consensus istifadə edir.

**Q3: PR diff və review comment necə işləyir, rebase zamanı comment-lər necə anchor qalır?**
PR diff `merge_base(base, head)..head` arasındadır - target branch-ın sonra push olan commit-lərini qatmır. Review comment `(commit_sha, file_path, line, side)` ilə saxlanır. Author force-push edəndə commit_sha dəyişir - platforma **position recomputation** edir: köhnə commit-dəki line text-ini və ətraf context-ini yeni commit-də diff hunk-larda axtarır (fuzzy match ±3 line). Tapılsa comment move, tapılmasa "outdated" marker (silinmir, audit üçün qalır). Eyni line block təkrarlanırsa konservativ - unsure → outdated.

**Q4: Böyük monorepo (Linux kernel, 1.3M commit) necə clone optimize olunur?**
Tam clone 5+ dəqiqə və GB bandwidth yeyir. Həllər: (1) **Shallow clone** (`--depth 1`) son commit-i alır, CI üçün ideal; (2) **Partial clone** (`--filter=blob:none`) tree alır, blob lazy checkout-da fetch; (3) **Sparse checkout** - working dir-də seçilmiş path (`git sparse-checkout set frontend/`); (4) server tərəfində **reachability bitmap** commit graph-a O(1) answer verir; (5) **commit graph file** parent lookup accelerate edir; (6) Protocol v2 `ls-refs` filtering. Ən radikal: Google VFS for Git kimi FUSE filesystem - file yalnız access olanda fetch.

**Q5: PR merge strategy-ləri necə fərqlənir və hansı nə vaxt seçilir?**
**Merge commit** (`--no-ff`) - iki-ana commit, branch topology görünür. Plus: fact-preserving, feature isolation aydın. Minus: gurultulu history. **Squash** - bütün PR commit-ləri bir commit-ə birləşir, linear history, bisect asan. Minus: fərdi commit context itir. **Rebase** - commit-lər bir-bir target-ə tətbiq, linear, amma SHA dəyişir və force-push tələb edir. Seçim: OSS/community - merge commit (contributor credit); enterprise SaaS - squash (sadə history, release note); linux-kernel kimi kritik - rebase (hər commit bisect-able).

**Q6: Code search 300M repo-da necə işləyir - grep etmir?**
Naive grep 15 PB data üzərində mümkün deyil. Həll: **trigram inverted index**. Hər file 3-char window-lara parse (`function` → `fun`, `unc`, `nct`...), hər trigram → file list. Query "foobar" → `foo`, `oob`, `oba`, `bar` kəsişməsi candidate-ləri verir, real string match false positive filter edir. GitHub bunu **Zoekt** ilə edir - positional trigram (offset, regex üçün), hər shard ayrı index, scatter-gather aggregate. Issue/PR search - Elasticsearch (faceted filter: state, label, assignee). Symbol search (go-to-definition) tree-sitter AST parse ilə ayrı index.

**Q7: GitHub Actions runner necə izolyasiya edilir, cross-job leak olmur?**
Hər job **ephemeral VM/container**-də. Lifecycle: queue-dan pull → pre-warmed pool-dan VM (1-2s) → runner agent job icra edir → VM **destroyed** (never reused). Nəticə: (1) A customer-in job-u B secret-ini oxumur (hypervisor isolation); (2) Previous job-un file, cache, env qalmır; (3) Malicious dependency reverse shell açsa belə VM destroy olanda itir. Self-hosted runner-də bu garantee customer məsuliyyətidir, default reuse edir (zəif boundary). Artifact/cache S3-də, run_id scope-lanır. Secret job env-ə runtime-da inject, log-da `***` maskalanır.

**Q8: Webhook delivery necə reliable edilir, receiver down olduqda nə baş verir?**
Sync HTTP etmə - customer down olsa thread block olur. Həll: **async queue**. Event yaranır → enqueue (Sidekiq/Kafka) → worker HTTP POST. Fail olsa **exponential backoff retry** (1s, 2s, 4s... max 24h). Hər delivery `X-GitHub-Delivery: UUID` - receiver idempotency üçün istifadə edir. **HMAC signature** (`X-Hub-Signature-256: sha256=HMAC(secret, body)`) spoofing-dən qoruyur. UI-də delivery log + manual redeliver. Per-endpoint rate limit, 24h sonra fail olanlar "suspended" + admin notification.

## Praktik Baxış

1. **Git storage sharding by repo_id** - lookup table manual migration üçün (hot repo-ları ayrı shard-a köçür)
2. **Sync replication (3 replica, 2/3 quorum)** - data loss qoruması, failover fast
3. **Bare repositories + pack files + bitmap index** - clone bandwidth və CPU optimize
4. **Git protocol v2** - ref filtering ilə monorepo clone-larını sürətləndir
5. **PR diff = merge_base..head** - target branch-ın yeni commit-lərini qatma
6. **Comment position recomputation** - force-push sonrası outdated qalsın amma itməsin
7. **Branch protection** - required review, status check, no-force-push, linear-history
8. **Ephemeral CI runners** - bir job üçün VM, sonra destroy (cross-job leak qoruması)
9. **Webhook HMAC + idempotency** - signature verify, delivery-id cache ilə duplicate skip
10. **Async webhook delivery** - queue enqueue, exponential backoff retry, dead letter
11. **Rate limiting per user/IP** - abusive clone/API qoruması, token attribution
12. **CDN for static və git pack** - popular OSS repo bandwidth offload
13. **Git LFS for binary** - pointer+S3, clone sürətli qalır
14. **GitHub App > PAT** - repo-scoped, short-lived installation token (1h TTL)
15. **Code search via trigram index** - Zoekt/Sourcegraph, grep etmə
16. **Shallow/partial clone dəstəyi** - CI-da `--depth 1`, monorepo-da `--filter=blob:none`
17. **Audit log everything** - push, force-push, permission change, token creation - compliance və incident investigation


## Əlaqəli Mövzular

- [File Storage](15-file-storage.md) — git object saxlama
- [Distributed File System](65-distributed-file-system.md) — repo replication
- [Collaborative Editing](51-collaborative-editing-design.md) — PR review real-time
- [Search Systems](12-search-systems.md) — code search
- [Webhook Delivery](82-webhook-delivery-system.md) — CI/CD trigger webhook-lar
