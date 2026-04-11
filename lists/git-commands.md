git init — yeni repo yarat
git clone <url> — reponu kopyala
git status — dəyişikliklərin vəziyyəti
git add <file> — faylı staging-ə əlavə et
git add . — bütün dəyişiklikləri stage et
git commit -m "msg" — commit et
git push — remote-a göndər
git pull — remote-dan çək və merge et
git fetch — remote-dan çək (merge etmədən)
git merge <branch> — branch-i merge et
git rebase <branch> — branch-i rebase et
git rebase -i HEAD~n — son n commit-i interaktiv redaktə et
git branch — branch-ləri göstər
git branch <name> — yeni branch yarat
git branch -d <name> — branch-i sil
git switch <branch> — branch-ə keç
git switch -c <branch> — yeni branch yarat və keç
git stash — dəyişiklikləri müvəqqəti saxla
git stash pop — son stash-i geri qaytar
git stash list — stash-ləri göstər
git log --oneline --graph — sadə qrafik tarixçə
git diff — unstaged dəyişiklikləri göstər
git diff --staged — staged dəyişiklikləri göstər
git reset --soft HEAD~1 — son commit-i geri al, dəyişikliklər qalsın
git reset --hard HEAD~1 — son commit-i tamamilə sil
git revert <commit> — commit-i geri alan yeni commit yarat
git cherry-pick <commit> — başqa branch-dən commit götür
git tag <name> — tag yarat
git reflog — bütün HEAD hərəkətlərini göstər
git blame <file> — hər sətrin müəllifini göstər
