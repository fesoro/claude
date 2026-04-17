# Git Interview Preparation Guide

Bu repozitoriya Git mövzularını əhatə edən ətraflı bələdçi toplusudur.
Hər fayl müsahibəyə hazırlıq üçün nəzərdə tutulub və praktiki nümunələr, ASCII diaqramlar və PHP/Laravel konteksti daxildir.

## Mündəricat (Table of Contents)

### Əsas Mövzular (Core Topics)

| #  | Fayl | Mövzu |
|----|------|-------|
| 01 | [01-git-basics.md](01-git-basics.md) | Git Basics - VCS, repository, staging area, init, clone, status, add, commit, log |
| 02 | [02-git-branching.md](02-git-branching.md) | Branching - creating/deleting branches, HEAD, detached HEAD, local vs remote |
| 03 | [03-git-merging.md](03-git-merging.md) | Merging - fast-forward, 3-way merge, conflicts, merge strategies |
| 04 | [04-git-rebasing.md](04-git-rebasing.md) | Rebasing - rebase vs merge, interactive rebase, squashing, golden rule |
| 05 | [05-git-remote.md](05-git-remote.md) | Remote Repositories - origin, fetch, pull, push, tracking branches, forks |
| 06 | [06-git-stashing.md](06-git-stashing.md) | Stashing - stash, apply, pop, drop, branch, partial stash |
| 07 | [07-git-reset-revert.md](07-git-reset-revert.md) | Reset & Revert - soft/mixed/hard reset, revert, restore, reflog |
| 08 | [08-git-cherry-pick.md](08-git-cherry-pick.md) | Cherry-pick - single/multiple commits, conflicts, use cases |
| 09 | [09-git-bisect.md](09-git-bisect.md) | Bisect - binary search for bugs, automated bisect |
| 10 | [10-gitflow.md](10-gitflow.md) | GitFlow - branching model, feature/release/hotfix branches |
| 11 | [11-trunk-based-development.md](11-trunk-based-development.md) | Trunk-Based Development - feature flags, CI, comparison with GitFlow |
| 12 | [12-github-flow.md](12-github-flow.md) | GitHub Flow - simple branching, pull requests, deploy from main |
| 13 | [13-git-hooks.md](13-git-hooks.md) | Git Hooks - pre-commit, commit-msg, husky, PHPStan, PHP-CS-Fixer |
| 14 | [14-git-tags.md](14-git-tags.md) | Tags - lightweight vs annotated, semantic versioning, release management |
| 15 | [15-git-submodules.md](15-git-submodules.md) | Submodules - adding, updating, removing, alternatives |
| 16 | [16-git-worktrees.md](16-git-worktrees.md) | Worktrees - multiple working trees, use cases |
| 17 | [17-git-log-advanced.md](17-git-log-advanced.md) | Advanced Log - formatting, graph, blame, shortlog, reflog |
| 18 | [18-git-config.md](18-git-config.md) | Git Config - system/global/local, aliases, tools, .gitconfig |
| 19 | [19-gitignore.md](19-gitignore.md) | .gitignore - patterns, global gitignore, Laravel .gitignore |
| 20 | [20-git-internals.md](20-git-internals.md) | Git Internals - objects, SHA-1, packfiles, refs, plumbing commands |
| 21 | [21-monorepo-vs-polyrepo.md](21-monorepo-vs-polyrepo.md) | Monorepo vs Polyrepo - pros/cons, tools, CI/CD |
| 22 | [22-pull-request-best-practices.md](22-pull-request-best-practices.md) | Pull Request Best Practices - size, templates, review checklist |
| 23 | [23-git-workflow-team.md](23-git-workflow-team.md) | Team Workflow - naming conventions, Conventional Commits, code review |
| 24 | [24-git-troubleshooting.md](24-git-troubleshooting.md) | Troubleshooting - common problems, recovering, Git LFS |
| 25 | [25-git-advanced-commands.md](25-git-advanced-commands.md) | Advanced Commands - filter-repo, rerere, bundle, sparse checkout |
| 26 | [26-signed-commits.md](26-signed-commits.md) | Signed Commits - GPG/SSH/S-MIME imzalar, verification, CI-də tətbiq |
| 27 | [27-git-lfs.md](27-git-lfs.md) | Git LFS - böyük binary fayllar, track, migrate, storage optimallaşdırma |
| 28 | [28-conventional-commits-semantic-release.md](28-conventional-commits-semantic-release.md) | Conventional Commits & Semantic Release - avtomatik versiyalama və CHANGELOG |
| 29 | [29-dependabot-renovate.md](29-dependabot-renovate.md) | Dependabot & Renovate - dependency update avtomatlaşdırılması, composer/npm |
| 30 | [30-git-performance-large-repos.md](30-git-performance-large-repos.md) | Performance in Large Repos - partial clone, sparse checkout, fsmonitor |
| 31 | [31-git-maintenance.md](31-git-maintenance.md) | Git Maintenance - gc, repack, prune, fsck, reflog expiration, commit-graph |
| 32 | [32-codeowners-branch-protection.md](32-codeowners-branch-protection.md) | CODEOWNERS & Branch Protection - ownership qaydaları, məcburi review, status checks |

## Necə İstifadə Etməli (How to Use)

1. Hər mövzunu sıra ilə oxuyun
2. Əmrləri terminalda praktika edin
3. Interview suallarını cavablandırmağa çalışın
4. ASCII diaqramları başa düşdüyünüzə əmin olun

## Qeydlər

- Nümunələr PHP/Laravel layihələri kontekstində verilmişdir
- Hər faylda Azərbaycan dilində bölmə başlıqları var
- Interview sualları real müsahibə təcrübəsinə əsaslanır
