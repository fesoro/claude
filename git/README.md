# Git

PHP/Laravel developer üçün Git — əsaslardan komanda workflow-larına, böyük repo optimallaşdırmasına qədər.

---

## Mövzular

### ⭐ Junior

| # | Fayl | Mövzu |
|---|------|-------|
| 01 | [01-git-basics.md](01-git-basics.md) | Git Basics — init, add, commit, log, status |
| 02 | [02-git-branching.md](02-git-branching.md) | Git Branching — branch yaratmaq, HEAD, detached HEAD |
| 03 | [03-git-remote.md](03-git-remote.md) | Git Remote — origin, fetch, pull, push, tracking branch |
| 04 | [04-gitignore.md](04-gitignore.md) | .gitignore — pattern-lər, global ignore, Laravel setup |

### ⭐⭐ Middle

| # | Fayl | Mövzu |
|---|------|-------|
| 05 | [05-git-merging.md](05-git-merging.md) | Git Merging — fast-forward, 3-way merge, conflict resolution |
| 06 | [06-git-rebasing.md](06-git-rebasing.md) | Git Rebasing — rebase vs merge, interactive rebase, squash |
| 07 | [07-git-stashing.md](07-git-stashing.md) | Git Stashing — stash, apply, pop, partial stash |
| 08 | [08-git-reset-revert.md](08-git-reset-revert.md) | Git Reset & Revert — soft/mixed/hard reset, revert, reflog |
| 09 | [09-git-cherry-pick.md](09-git-cherry-pick.md) | Git Cherry-Pick — tək/çox commit seçmək, conflict |
| 10 | [10-git-bisect.md](10-git-bisect.md) | Git Bisect — binary search ilə bug tapmaq |
| 11 | [11-git-tags.md](11-git-tags.md) | Git Tags — lightweight vs annotated, semantic versioning |
| 12 | [12-git-config.md](12-git-config.md) | Git Config — system/global/local, alias-lər, tooling |
| 13 | [13-gitflow.md](13-gitflow.md) | GitFlow — branching model, feature/release/hotfix |
| 14 | [14-github-flow.md](14-github-flow.md) | GitHub Flow — sadə branching, pull request, main-dən deploy |
| 15 | [15-pull-request-best-practices.md](15-pull-request-best-practices.md) | Pull Request Best Practices — ölçü, template, review checklist |
| 16 | [16-git-troubleshooting.md](16-git-troubleshooting.md) | Git Troubleshooting — ümumi problemlər, recovery |

### ⭐⭐⭐ Senior

| # | Fayl | Mövzu |
|---|------|-------|
| 17 | [17-trunk-based-development.md](17-trunk-based-development.md) | Trunk-Based Development — feature flags, CI, GitFlow müqayisəsi |
| 18 | [18-git-hooks.md](18-git-hooks.md) | Git Hooks — pre-commit, commit-msg, husky, PHPStan, CS-Fixer |
| 19 | [19-git-submodules.md](19-git-submodules.md) | Git Submodules — əlavə etmək, yeniləmək, alternativlər |
| 20 | [20-git-worktrees.md](20-git-worktrees.md) | Git Worktrees — paralel working tree-lər, use case-lər |
| 21 | [21-git-log-advanced.md](21-git-log-advanced.md) | Git Log Advanced — format, graph, blame, shortlog, reflog |
| 22 | [22-git-workflow-team.md](22-git-workflow-team.md) | Team Git Workflow — naming convention, Conventional Commits, review |
| 23 | [23-git-advanced-commands.md](23-git-advanced-commands.md) | Git Advanced Commands — filter-repo, rerere, bundle, sparse checkout |
| 24 | [24-signed-commits.md](24-signed-commits.md) | Signed Commits — GPG/SSH imzalar, verification, CI-də tətbiq |
| 25 | [25-git-lfs.md](25-git-lfs.md) | Git LFS — böyük binary fayllar, track, migrate |
| 26 | [26-conventional-commits-semantic-release.md](26-conventional-commits-semantic-release.md) | Conventional Commits & Semantic Release — avtomatik versiyalama, CHANGELOG |
| 27 | [27-dependabot-renovate.md](27-dependabot-renovate.md) | Dependabot & Renovate — dependency update avtomatlaşdırılması |

### ⭐⭐⭐⭐ Lead

| # | Fayl | Mövzu |
|---|------|-------|
| 28 | [28-monorepo-vs-polyrepo.md](28-monorepo-vs-polyrepo.md) | Monorepo vs Polyrepo — pros/cons, tool-lar, CI/CD |
| 29 | [29-codeowners-branch-protection.md](29-codeowners-branch-protection.md) | CODEOWNERS & Branch Protection — ownership qaydaları, məcburi review |
| 30 | [30-git-performance-large-repos.md](30-git-performance-large-repos.md) | Git Performance in Large Repos — partial clone, sparse checkout, fsmonitor |
| 31 | [31-git-maintenance.md](31-git-maintenance.md) | Git Maintenance — gc, repack, prune, fsck, commit-graph |

### ⭐⭐⭐⭐⭐ Architect

| # | Fayl | Mövzu |
|---|------|-------|
| 32 | [32-git-internals.md](32-git-internals.md) | Git Internals — objects, SHA-1, packfiles, refs, plumbing commands |

---

## Reading Paths

### Sıfırdan başlamaq (Junior → Middle)
01 → 02 → 03 → 04 → 05 → 06 → 07 → 08 → 11 → 12 → 13 → 14

### Gündəlik iş (Middle core)
05 → 06 → 07 → 08 → 09 → 15 → 16

### Komanda workflow-u (Senior)
13 → 14 → 17 → 18 → 22 → 26

### Böyük layihə idarəetməsi (Lead)
19 → 28 → 29 → 30 → 31

### Git dərinliyi (Architect)
21 → 23 → 32
