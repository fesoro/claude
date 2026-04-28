## Setup / config

git init — yeni repo yarat
git init --bare — bare repo (server-side)
git clone <url> — reponu kopyala
git clone --depth 1 <url> — shallow clone (history yox)
git clone --branch <branch> <url>
git config --global user.name "Your Name"
git config --global user.email "you@example.com"
git config --global init.defaultBranch main
git config --global core.editor "vim"
git config --global pull.rebase true
git config --list

## Basic workflow

git status — dəyişikliklərin vəziyyəti
git status -s — short format
git add <file> — faylı staging-ə əlavə et
git add . — bütün dəyişiklikləri stage et
git add -p — interactive (hunks seç)
git add -u — yalnız tracked faylları
git restore <file> — working dir-də dəyişikliyi geri al
git restore --staged <file> — staging-dən geri al
git commit -m "msg" — commit et
git commit -am "msg" — add + commit (tracked üçün)
git commit --amend — son commit-i redaktə et
git commit --amend --no-edit — mesaj dəyişmədən
git commit --fixup <commit> — autosquash üçün
git commit -S -m "msg" — GPG signed commit

## Branch

git branch — branch-ləri göstər
git branch -a — remote-lar ilə
git branch -vv — tracking info ilə
git branch <name> — yeni branch yarat
git branch -d <name> — branch-i sil
git branch -D <name> — force delete
git branch -m <new> — rename (cari)
git branch -m <old> <new>
git switch <branch> — branch-ə keç (modern)
git switch -c <branch> — yeni branch yarat və keç
git switch -c <branch> <base> — base-dən yarat
git checkout <branch> — köhnə API (switch ilə əvəzlənib)
git checkout -b <branch>

## Remote / sync

git remote -v — remote-ları göstər
git remote add <name> <url>
git remote set-url origin <url>
git remote rename <old> <new>
git remote remove <name>
git push — remote-a göndər
git push -u origin <branch> — upstream təyin et
git push --force-with-lease — safer force push
git push --force — təhlükəli; rewrite remote history
git push origin --delete <branch> — remote branch-i sil
git pull — remote-dan çək və merge et
git pull --rebase — rebase strategy
git fetch — remote-dan çək (merge etmədən)
git fetch --prune — köhnəlmiş remote ref-ləri təmizlə
git fetch --all

## Merge / rebase

git merge <branch> — branch-i merge et
git merge --no-ff <branch> — həmişə merge commit
git merge --squash <branch> — squash et, commit etmə
git merge --abort — merge-i ləğv et
git rebase <branch> — branch-i rebase et
git rebase --onto <new-base> <old-base> <branch>
git rebase -i HEAD~n — son n commit-i interactive
git rebase --continue — conflict həllindən sonra
git rebase --abort — rebase-i ləğv et
git rebase --autosquash — fixup/squash commit-ləri tət

## Diff / inspect

git log — commit tarixçəsi
git log --oneline — qısa format
git log --oneline --graph --all — qrafik ilə
git log --stat — fayl dəyişiklik stat-ları
git log -p — diff ilə
git log --author="name"
git log --since="2 weeks ago"
git log --grep="fix"
git log <file> — faylın tarixçəsi
git log --follow <file> — rename-lər daxil
git log <branch1>..<branch2> — arasındakı fərq
git log --cherry-pick --no-merges <branch>...<branch2>
git diff — unstaged dəyişiklikləri göstər
git diff --staged (və ya --cached) — staged dəyişikliklər
git diff HEAD — staged + unstaged
git diff <commit>..<commit>
git diff <branch>..<branch>
git diff --stat — file stats
git show <commit> — commit-in diff və metadata-sı
git show <commit>:<file> — commit-dəki fayl məzmunu
git blame <file> — hər sətrin müəllifini göstər
git blame -L 10,20 <file> — konkret sətirlər

## Stash

git stash — dəyişiklikləri müvəqqəti saxla
git stash save "message"
git stash push -m "msg" -- <file> — konkret fayl
git stash -u — untracked daxil
git stash list — stash-ləri göstər
git stash show -p stash@{0} — diff
git stash pop — son stash-i geri qaytar və sil
git stash apply — qaytar amma stash-də qalsın
git stash drop stash@{0} — konkret stash sil
git stash clear — hamısını sil

## Undo / recovery

git reset <file> — unstage
git reset --soft HEAD~1 — son commit-i geri al, staging saxla
git reset --mixed HEAD~1 — default; working dir saxla, staging təmizlə
git reset --hard HEAD~1 — son commit-i tamamilə sil (TƏHLÜKƏLİ)
git revert <commit> — commit-i geri alan yeni commit
git revert -n <commit> — commit etmədən
git cherry-pick <commit> — başqa branch-dən commit götür
git cherry-pick <c1>..<c2> — range
git cherry-pick -x <commit> — original hash-i mesaja yaz
git reflog — bütün HEAD hərəkətlərini göstər (recovery üçün kritik)
git fsck --lost-found — orphan obyektləri tap

## Tag

git tag — tag-ları listele
git tag <name> — lightweight tag
git tag -a v1.0 -m "message" — annotated tag
git tag -a v1.0 <commit> — konkret commit-ə
git push --tags — bütün tag-ları push et
git push origin v1.0 — konkret tag
git tag -d v1.0 — local sil
git push origin --delete v1.0 — remote sil

## Submodule / worktree

git submodule add <url> <path>
git submodule update --init --recursive
git submodule foreach git pull
git worktree add <path> <branch> — paralel checkout
git worktree list
git worktree remove <path>

## Bisect / debug

git bisect start
git bisect bad [commit]
git bisect good <commit>
git bisect reset
git bisect run <script>

## Clean / ignore

git clean -n — nə siləcəyini göstər (dry run)
git clean -fd — untracked fayl və dir-ləri sil
git clean -fdx — gitignore daxil
git check-ignore -v <file> — hansı rule ignore edir
git rm <file> — tracked-dən sil və working-dən də
git rm --cached <file> — yalnız tracked-dən çıxar
git mv <old> <new>

## Search

git grep "pattern" — repo-da axtarış
git grep -n "pattern" — line number ilə
git log -S "code" — code-u əlavə edən/çıxaran commit-lər
git log -G "regex" — regex match

## Advanced

git rebase -i --root — bütün tarixçəni rebase et
git filter-repo --path <dir> — history rewrite (secret silmək üçün)
git replace <old> <new> — commit əvəzləmə
git notes add -m "note" <commit>
git archive --format=zip HEAD > archive.zip
git shortlog -sn — author-a görə commit sayı
git describe — ən yaxın tag-ı təsvir et
git show-branch — branch-ləri vizual müqayisə et

## Sparse-checkout / partial clone (large repos)

git clone --filter=blob:none <url>            — partial clone (no blobs until needed)
git clone --filter=tree:0 <url>               — tree-less (super shallow)
git clone --depth 1 --filter=blob:none --no-checkout <url>
git sparse-checkout init --cone               — cone mode (faster, simpler)
git sparse-checkout set src/api docs/         — only these dirs
git sparse-checkout list
git sparse-checkout disable
git sparse-checkout reapply
# Useful for monorepos: clone ↓ size, work only on relevant subtree

## Worktree (multiple branches in parallel — no stash needed)

git worktree add ../hotfix main               — yeni worktree main branch-də
git worktree add -b hotfix ../hotfix          — yeni branch yarat
git worktree list
git worktree remove ../hotfix
git worktree prune
# Each worktree shares .git but has its own working dir + index — perfect for review/hotfix while coding

## Rerere (reuse recorded resolution)

git config --global rerere.enabled true
# Once enabled: when you resolve a conflict, git remembers and auto-applies the same resolution next time
git rerere status
git rerere diff
git rerere clear
# Big win on long-running rebase / merge cycles

## Signing commits (modern — SSH preferred)

git config --global commit.gpgsign true
git config --global gpg.format ssh                          — SSH key signing (Git 2.34+)
git config --global user.signingkey ~/.ssh/id_ed25519.pub
git config --global gpg.ssh.allowedSignersFile ~/.ssh/allowed_signers
git commit -S -m "msg"                         — sign explicitly
git tag -s v1.0 -m "release"                   — signed tag
git log --show-signature
git verify-commit <sha>
git verify-tag <tag>
# GitHub: paste public key as SSH "Signing Key" type

## Modern shortcuts (Git 2.23+ / 2.40+)

git switch <branch>                            — replaces "checkout <branch>"
git switch -c <branch>                         — replaces "checkout -b"
git switch -                                   — toggle to previous branch
git restore <file>                             — replaces "checkout -- file"
git restore --staged <file>                    — replaces "reset HEAD file"
git restore --source=HEAD~3 <file>             — file from older commit
git maintenance start                          — schedule auto-gc/fetch (2.30+)
git maintenance run --task=gc
git fsmonitor--daemon start                    — fast file change detection (2.36+)

## Common workflows

# GitHub flow / trunk-based
git switch -c feature/x
# ... commit work ...
git push -u origin feature/x
gh pr create                                   — open PR (GitHub CLI)
# After merge:
git switch main && git pull && git branch -d feature/x

# Update branch with main (rebase preferred for feature branches)
git fetch origin
git rebase origin/main
git push --force-with-lease                     — safer than --force

# Squash before merge
git rebase -i origin/main                       — pick → squash → reword
# OR merge with squash:
git switch main && git merge --squash feature/x && git commit -m "feat: ..."

# Cherry-pick a commit from another branch
git cherry-pick <sha>
git cherry-pick -x <sha>                        — record source in message
git cherry-pick A..B                            — range (A exclusive, B inclusive)
git cherry-pick A^..B                           — range (A inclusive)
git cherry-pick --no-commit <sha>               — stage without committing

# Undo a published commit safely
git revert <sha>                                — creates inverse commit (preserves history)
git revert -m 1 <merge-sha>                     — revert merge commit (mainline)

# Recover lost work
git reflog                                      — see HEAD history
git switch -c recover <sha-from-reflog>
git fsck --lost-found

# Conflict resolution
git status                                      — see "both modified"
git diff --conflict=zdiff3                      — better conflict view (2.35+)
git config --global merge.conflictstyle zdiff3
# Edit files, resolve markers, then:
git add <resolved>
git rebase --continue / git merge --continue / git cherry-pick --continue
git checkout --ours <file> / --theirs <file>    — pick a side wholesale

## Useful aliases (add to ~/.gitconfig)

[alias]
  s   = status -sb
  co  = checkout
  sw  = switch
  br  = branch
  ci  = commit
  l   = log --oneline --graph --decorate --all
  last = log -1 HEAD
  unstage = restore --staged
  amend = commit --amend --no-edit
  fixup = commit --fixup
  uncommit = reset HEAD~1 --soft
  please = push --force-with-lease

## Useful config tweaks

git config --global pull.rebase true                       — pull = fetch + rebase (cleaner history)
git config --global push.autoSetupRemote true              — first push auto-sets upstream
git config --global push.default current
git config --global rebase.autoStash true                  — stash before rebase, pop after
git config --global rebase.autoSquash true                 — autosquash fixup! commits
git config --global merge.conflictstyle zdiff3
git config --global rerere.enabled true
git config --global diff.algorithm histogram               — better diffs
git config --global core.fsmonitor true                    — fast scan
git config --global init.defaultBranch main
git config --global fetch.prune true                       — auto-remove deleted remote branches
git config --global column.ui auto                         — list output columnized

## GitHub CLI (gh) quick reference

gh auth login
gh repo clone owner/repo
gh repo view --web
gh pr create --fill                            — auto title/body from commits
gh pr list / gh pr view <n> / gh pr checkout <n>
gh pr merge --squash                           — merge current PR
gh pr review --approve / --request-changes
gh issue list / gh issue create
gh run list / gh run view <id>                 — Actions
gh release create v1.0 --notes "..."
gh api repos/owner/repo/pulls/N/comments       — raw API
