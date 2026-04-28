# Claude Code Skills və Hooks: Harness Avtomatlaşdırması (Senior)

> **Oxucu kütləsi:** Senior developerlər, platform engineer-lər
> **Əhatə dairəsi:** Claude Code CLI-da **skill** (slash-command) sistemi, **hook** lifecycle, `settings.json` sxemi, `PermissionDecision` qaytarma, real avtomatlaşdırma nümunələri (auto-format, dangerous command block, long-running notify).

---

## 1. Skill vs Hook — Konseptual Fərq

| | Skill | Hook |
|-|-------|------|
| **Kim çağırır** | İstifadəçi manual (`/review`, `/init`) və ya model (prompt match) | Harness avtomatik (tool çağırışından əvvəl/sonra, session start, user prompt submit) |
| **Niyyət** | Re-usable prompt/task. LLM-ə "nə etməli" deyir | Deterministic gate/side-effect. LLM-dən əvvəl və ya sonra müdaxilə edir |
| **Saxlanma yeri** | `~/.claude/skills/<name>/SKILL.md` (user) və ya `.claude/skills/<name>/SKILL.md` (project) | `~/.claude/settings.json` və ya `.claude/settings.json` |
| **Icra** | LLM kontekstinə inject edilir, model onu yerinə yetirir | Harness shell komandaları çağırır, LLM onu görmür (və ya output-u görür) |
| **Trigger** | LLM model qərarı (prompt match, user komand) | Hadisə (event) — `PreToolUse`, `PostToolUse`, `Stop`, və s. |

Qısaca: **Skill = LLM-in biliyini/qabiliyyətini genişləndirir, Hook = harness-i (kodu) genişləndirir.**

---

## 2. Skill Strukturu

Skill bir qovluqdur:

```
~/.claude/skills/
  my-review-skill/
    SKILL.md          # frontmatter + prompt content
    template.md       # istəyə bağlı əlavə fayllar (referenced in SKILL.md)
    helper.sh         # istəyə bağlı script
```

### SKILL.md Frontmatter

```markdown
---
name: review
description: Review a pull request. Use when user asks to review a PR, check code quality, or examine recent changes.
allowed-tools:
  - Bash
  - Read
  - Grep
---

# Review Skill

You are reviewing a pull request. Follow these steps:

1. Run `gh pr view` to get PR metadata.
2. Run `gh pr diff` to get the diff.
3. Analyze the diff for:
   - Security issues
   - Performance problems
   - Missing tests
   - Code style violations
4. Post findings as a PR comment using `gh pr comment`.
```

### Slash-Command vs Auto-Triggered

Skill iki cür aktivləşə bilər:

**1. Slash-command olaraq:** istifadəçi yazır `/review <PR_URL>` → harness skill-i prompt kontekstinə əlavə edir.

**2. Model qərarı ilə:** istifadəçi normal dildə yazır "review this PR" → model `description` field-ə baxır, uyğun gələn skill-i çağırır (skill list əsas system prompt-a daxil edilib).

`description` dəqiq olmalıdır. Pis: `"Helps with code"`. Yaxşı: `"Review a pull request. Use when user asks to review a PR, check code quality, or examine recent changes."`

### Skill İerarxiyası

1. Project skill (`.claude/skills/` — git-lə birlikdə komandaya paylaşılır)
2. User skill (`~/.claude/skills/` — fərdi)
3. Built-in skills (Anthropic-dən)

Eyni adla birdən çox skill olsa, project user-dən, user built-in-dən önəmlidir.

---

## 3. Hook Lifecycle — 7 Hadisə

Claude Code harness 7 fərqli hook event-i fire edir:

| Event | Zaman | Tipik istifadə |
|-------|-------|----------------|
| `SessionStart` | Yeni sessiya başlayanda | Environment yoxlama, welcome mesajı, context inject |
| `UserPromptSubmit` | İstifadəçi Enter basandan sonra, LLM-ə getməmişdən əvvəl | Prompt re-write, audit log, security check |
| `PreToolUse` | LLM tool çağırmaq istədikdə, icra edilməmişdən əvvəl | Permission check, dangerous command block, log |
| `PostToolUse` | Tool icra olunandan sonra, nəticə LLM-ə qaytarılmamışdan əvvəl | Auto-format, linter, test-run, log |
| `Stop` | Əsas agent dayandıqda (cavab tamamlandıqda) | Notification (sound, desktop alert), metrics |
| `SubagentStop` | Subagent (Task tool) dayandıqda | Subagent metrics, log |
| `PreCompact` | Harness context compaction-a başlayanda | Backup context, metrics |

---

## 4. settings.json Hook Sxeması

```json
{
  "hooks": {
    "PreToolUse": [
      {
        "matcher": "Bash",
        "hooks": [
          {
            "type": "command",
            "command": "/usr/local/bin/block-dangerous.sh",
            "timeout": 5000
          }
        ]
      }
    ],
    "PostToolUse": [
      {
        "matcher": "Write|Edit",
        "hooks": [
          {
            "type": "command",
            "command": "~/.claude/hooks/auto-format.sh"
          }
        ]
      }
    ],
    "Stop": [
      {
        "hooks": [
          {
            "type": "command",
            "command": "notify-send 'Claude done'"
          }
        ]
      }
    ]
  }
}
```

### `matcher` Field

Yalnız `PreToolUse` və `PostToolUse` üçün mövcuddur. Regex/pipe ilə hansı tool-lara uyğun gəlir:

- `"Bash"` — yalnız Bash
- `"Write|Edit"` — Write və ya Edit
- `"*"` və ya omit — hamısı

### `command` Field

Shell komandası. stdin-dən JSON hadisə payload-u alır, stdout və exit code ilə cavab verir.

---

## 5. Hook Input/Output Format

Harness hər hook-a JSON payload stdin-dən göndərir:

```json
{
  "hook_event_name": "PreToolUse",
  "session_id": "abc-123",
  "cwd": "/home/user/project",
  "tool_name": "Bash",
  "tool_input": {
    "command": "rm -rf /",
    "description": "Delete everything"
  },
  "transcript_path": "/tmp/claude-transcript-abc.jsonl"
}
```

Hook stdout-da JSON qaytarır:

```json
{
  "hookSpecificOutput": {
    "hookEventName": "PreToolUse",
    "permissionDecision": "deny",
    "permissionDecisionReason": "Destructive rm -rf / blocked by policy"
  }
}
```

### Exit Code Semantics

- `0` — uğurlu. stdout-da JSON varsa, harness onu oxuyur.
- `1-99` — xəta, amma davam. Harness xəbərdarlıq göstərir.
- `≥100` — block. Tool çağırışı ləğv olur, exit reason LLM-ə qayıdır.

Sadə bloklama üçün JSON çıxış əvəzinə `exit 100` istifadə edin və stderr-də səbəb yazın:

```bash
echo "rm -rf / is blocked" >&2
exit 100
```

---

## 6. PermissionDecision: allow / deny / ask

`PreToolUse` hook-u tool çağırışını üç cür idarə edə bilər:

### `allow` — Gelecek prompt olmadan icazə verir

```json
{
  "hookSpecificOutput": {
    "hookEventName": "PreToolUse",
    "permissionDecision": "allow",
    "permissionDecisionReason": "Matches safe-commands allow-list"
  }
}
```

### `deny` — Icra olunmur, LLM-ə səbəb qaytarılır

```json
{
  "hookSpecificOutput": {
    "hookEventName": "PreToolUse",
    "permissionDecision": "deny",
    "permissionDecisionReason": "git push --force is blocked on main branch"
  }
}
```

### `ask` — İstifadəçidən təsdiq soruşulur

```json
{
  "hookSpecificOutput": {
    "hookEventName": "PreToolUse",
    "permissionDecision": "ask",
    "permissionDecisionReason": "Production deploy — confirm?"
  }
}
```

### Heç bir decision qaytarılmırsa

Standart `settings.json` `permissions.allow / deny / ask` bloku tətbiq olunur. Hook sadəcə log və audit üçün istifadə oluna bilər (early-return).

---

## 7. Nümunə 1: Dangerous Command Blocker

```bash
#!/usr/bin/env bash
# ~/.claude/hooks/block-dangerous.sh
# Bloklanır: rm -rf /, git push --force origin main, DROP DATABASE, sudo su

set -euo pipefail

input=$(cat)
command=$(echo "$input" | jq -r '.tool_input.command // ""')

# Dangerous patterns
patterns=(
  'rm\s+-rf\s+/($|\s)'
  'rm\s+-rf\s+~($|\s)'
  'git\s+push\s+.*--force.*\s(main|master|production)'
  'DROP\s+DATABASE'
  'DROP\s+TABLE'
  'sudo\s+su\b'
  ':\(\)\s*\{\s*:\|:\&\s*\}\s*;:'  # fork bomb
  'dd\s+.*of=/dev/(sda|nvme|xvda)'
  'mkfs\.'
  'chmod\s+-R\s+000'
)

for pattern in "${patterns[@]}"; do
  if echo "$command" | grep -qE "$pattern"; then
    cat <<JSON
{
  "hookSpecificOutput": {
    "hookEventName": "PreToolUse",
    "permissionDecision": "deny",
    "permissionDecisionReason": "Dangerous command pattern matched: $pattern"
  }
}
JSON
    exit 0
  fi
done

# No match — defer to default
exit 0
```

`settings.json`:

```json
{
  "hooks": {
    "PreToolUse": [
      {
        "matcher": "Bash",
        "hooks": [
          {
            "type": "command",
            "command": "~/.claude/hooks/block-dangerous.sh",
            "timeout": 3000
          }
        ]
      }
    ]
  }
}
```

---

## 8. Nümunə 2: Auto-Format on Write

```bash
#!/usr/bin/env bash
# ~/.claude/hooks/auto-format.sh
# Write və ya Edit sonra faylı format et

input=$(cat)
tool_name=$(echo "$input" | jq -r '.tool_name')
file_path=$(echo "$input" | jq -r '.tool_input.file_path // empty')

# file_path yoxdursa, heç nə et
if [[ -z "$file_path" ]]; then exit 0; fi

# Fayl tipinə görə formatter
case "$file_path" in
  *.php)
    command -v pint >/dev/null && pint "$file_path" >/dev/null 2>&1
    ;;
  *.ts|*.tsx|*.js|*.jsx)
    command -v prettier >/dev/null && prettier --write "$file_path" >/dev/null 2>&1
    ;;
  *.py)
    command -v ruff >/dev/null && ruff format "$file_path" >/dev/null 2>&1
    ;;
  *.go)
    gofmt -w "$file_path" 2>/dev/null
    ;;
  *.rs)
    rustfmt "$file_path" 2>/dev/null
    ;;
  *.json)
    command -v jq >/dev/null && {
      tmpfile=$(mktemp)
      jq . "$file_path" > "$tmpfile" && mv "$tmpfile" "$file_path"
    }
    ;;
esac

exit 0
```

`settings.json`:

```json
{
  "hooks": {
    "PostToolUse": [
      {
        "matcher": "Write|Edit",
        "hooks": [
          {
            "type": "command",
            "command": "~/.claude/hooks/auto-format.sh",
            "timeout": 10000
          }
        ]
      }
    ]
  }
}
```

---

## 9. Nümunə 3: Stop Hook — Long-Running Task Notification

```bash
#!/usr/bin/env bash
# ~/.claude/hooks/notify-on-stop.sh
# Sessiya dayandığında desktop notification və səs

input=$(cat)
session_id=$(echo "$input" | jq -r '.session_id')

# Sessiya uzun sürürdümü? transcript dosyasına baxaq.
transcript_path=$(echo "$input" | jq -r '.transcript_path // empty')
if [[ -n "$transcript_path" && -f "$transcript_path" ]]; then
  # İlk mesajın zaman damgası
  first_ts=$(head -1 "$transcript_path" | jq -r '.timestamp // empty')
  last_ts=$(tail -1 "$transcript_path" | jq -r '.timestamp // empty')

  if [[ -n "$first_ts" && -n "$last_ts" ]]; then
    duration=$(( $(date -d "$last_ts" +%s) - $(date -d "$first_ts" +%s) ))
    # 60 saniyədən uzun olsa bildir
    if (( duration > 60 )); then
      # Linux
      if command -v notify-send >/dev/null; then
        notify-send -u normal "Claude Code" "Task complete (${duration}s)"
      fi
      # macOS
      if command -v osascript >/dev/null; then
        osascript -e "display notification \"Task complete (${duration}s)\" with title \"Claude Code\" sound name \"Glass\""
      fi
    fi
  fi
fi

exit 0
```

---

## 10. Nümunə 4: UserPromptSubmit — Audit Log

```bash
#!/usr/bin/env bash
# ~/.claude/hooks/audit-prompts.sh
# Bütün istifadəçi promptlarını audit log-a yaz (compliance)

input=$(cat)
log_dir="$HOME/.claude/audit"
mkdir -p "$log_dir"

date_str=$(date +%F)
log_file="$log_dir/prompts-$date_str.jsonl"

# Promptu ilə birlikdə metadata-ları yaz
echo "$input" | jq -c '{
  timestamp: now,
  session_id: .session_id,
  cwd: .cwd,
  prompt: .user_prompt
}' >> "$log_file"

exit 0
```

---

## 11. Nümunə 5: SessionStart — Context Injection

```bash
#!/usr/bin/env bash
# ~/.claude/hooks/session-start.sh
# Yeni sessiya üçün dinamik context injection

input=$(cat)
cwd=$(echo "$input" | jq -r '.cwd')

# Project-spesifik məlumat yığ
context=""
if [[ -f "$cwd/package.json" ]]; then
  version=$(jq -r '.version' "$cwd/package.json")
  context+="Project version: $version\n"
fi

if [[ -d "$cwd/.git" ]]; then
  branch=$(cd "$cwd" && git branch --show-current)
  context+="Current branch: $branch\n"
fi

# Deploy statusu
if [[ -f "$cwd/.last-deploy" ]]; then
  last_deploy=$(cat "$cwd/.last-deploy")
  context+="Last deploy: $last_deploy\n"
fi

cat <<JSON
{
  "hookSpecificOutput": {
    "hookEventName": "SessionStart",
    "additionalContext": "$(echo -e "$context")"
  }
}
JSON
```

`SessionStart` hook `additionalContext` field qaytara bilər — bu, LLM-in system promptuna inject edilir.

---

## 12. Environment Variables Hook İçində

Harness hook-a aşağıdakı env var-ları passed edir:

| Env Var | Məzmunu |
|---------|---------|
| `CLAUDE_SESSION_ID` | Cari sessiya ID |
| `CLAUDE_PROJECT_DIR` | Cari cwd |
| `CLAUDE_TRANSCRIPT_PATH` | Transcript faylı (jsonl) |
| `CLAUDE_MODEL` | Cari model ID |
| `CLAUDE_HOOK_EVENT` | Bu hadisənin adı |

İstifadə nümunəsi:

```bash
#!/usr/bin/env bash
session_dir="$HOME/.claude/sessions/$CLAUDE_SESSION_ID"
mkdir -p "$session_dir"
echo "$(date -Iseconds): ${CLAUDE_HOOK_EVENT}" >> "$session_dir/events.log"
```

---

## 13. PostToolUse — Test Run After Code Change

```bash
#!/usr/bin/env bash
# ~/.claude/hooks/run-affected-tests.sh
# Yalnız Write/Edit sonra — və yalnız uyğun test-lər

input=$(cat)
file=$(echo "$input" | jq -r '.tool_input.file_path // empty')

[[ -z "$file" ]] && exit 0

# Yalnız PHP source files üçün
[[ "$file" != *.php ]] && exit 0
[[ "$file" == *"/tests/"* ]] && exit 0

# Test faylı tap
base=$(basename "$file" .php)
test_file=$(find tests -name "${base}Test.php" 2>/dev/null | head -1)

if [[ -n "$test_file" ]]; then
  # Sessizcə işləsin, yalnız failure-da stderr-ə
  if ! ./vendor/bin/pest "$test_file" >/dev/null 2>&1; then
    echo "Tests failed for $test_file. Run manually: ./vendor/bin/pest $test_file" >&2
    # exit 2 — warning, lakin block etmə
    exit 2
  fi
fi

exit 0
```

---

## 14. Hook Debugging

### Verbose mode

Hooks stderr-ə yazılsa, harness onu göstərir. Debug üçün:

```bash
echo "DEBUG: input was $input" >&2
echo "DEBUG: matched pattern $pattern" >&2
```

### Log faylı

```bash
echo "$(date -Iseconds) | $CLAUDE_HOOK_EVENT | $command" \
  >> ~/.claude/hook-debug.log
```

### Claude Code `/hooks list` komandası

Cari tətbiq olan hook-ları görmək:

```
/hooks list
```

---

## 15. Təhlükəsizlik Qeydləri

- **Hook-lar arbitrary shell komandalarıdır** — `.claude/settings.json` versiya kontroluna girirsə, pull request-də hook dəyişikliyini həmişə diqqətlə review edin.
- **`PreToolUse` hook-u sensitive tool input-u görür** — prompt və ya command arguments həssas ola bilər.
- **Timeout qoyun** — hook 30+ saniyə gözləyə bilər, halsa pis UX.
- **Shell injection risk** — hook input-undan `jq -r` ilə oxuyun və shell-ə `$(...)` kimi inline edəndə həmişə quote və ya array istifadə edin.
- **settings.local.json .gitignore-da olmalıdır** — fərdi parametrlər commit olunmasın.

---

## 16. settings.json vs settings.local.json

| Fayl | Məqsəd | Versiya kontrolu |
|------|--------|------------------|
| `~/.claude/settings.json` | User-wide (hər layihədə aktivdir) | YOX |
| `.claude/settings.json` | Project-wide, komandaya paylaşılır | BƏLI |
| `.claude/settings.local.json` | Project-layer user override | YOX (.gitignore-a əlavə et) |

Precedence: local > project > user > default.

---

## 17. Tam Real Nümunə: Production-Ready Config

```json
{
  "permissions": {
    "allow": ["Read", "Grep", "Glob", "Bash(git status:*)", "Bash(git diff:*)"],
    "ask": ["Write", "Edit", "Bash(*)"],
    "deny": ["Bash(rm -rf /)", "Bash(sudo:*)"]
  },
  "hooks": {
    "SessionStart": [
      {
        "hooks": [
          { "type": "command", "command": "~/.claude/hooks/session-start.sh" }
        ]
      }
    ],
    "UserPromptSubmit": [
      {
        "hooks": [
          { "type": "command", "command": "~/.claude/hooks/audit-prompts.sh" }
        ]
      }
    ],
    "PreToolUse": [
      {
        "matcher": "Bash",
        "hooks": [
          { "type": "command", "command": "~/.claude/hooks/block-dangerous.sh", "timeout": 3000 }
        ]
      }
    ],
    "PostToolUse": [
      {
        "matcher": "Write|Edit",
        "hooks": [
          { "type": "command", "command": "~/.claude/hooks/auto-format.sh", "timeout": 10000 },
          { "type": "command", "command": "~/.claude/hooks/run-affected-tests.sh", "timeout": 30000 }
        ]
      }
    ],
    "Stop": [
      {
        "hooks": [
          { "type": "command", "command": "~/.claude/hooks/notify-on-stop.sh" }
        ]
      }
    ]
  },
  "env": {
    "CLAUDE_CODE_MAX_PROMPT_LENGTH": "100000"
  }
}
```

---

## 18. Müsahibə Xülasəsi

- **Skill vs Hook**: skill LLM kontekstini genişləndirir (prompt injection-style re-use), hook harness lifecycle-ına müdaxilə edir (deterministic shell kod).
- **Skill strukturu**: `~/.claude/skills/<name>/SKILL.md` + frontmatter (`name`, `description`, `allowed-tools`).
- **7 hook event**: `SessionStart`, `UserPromptSubmit`, `PreToolUse`, `PostToolUse`, `Stop`, `SubagentStop`, `PreCompact`.
- **matcher** yalnız `PreToolUse` / `PostToolUse` üçün — regex tool adını match edir (`"Write|Edit"`, `"Bash"`, `"*"`).
- **Hook I/O**: stdin-də JSON payload, stdout-da JSON response və ya sadə `exit 100` ilə blok.
- **`PermissionDecision`**: `allow` / `deny` / `ask` — `PreToolUse` tool icazəsini idarə edir.
- **Real nümunələr**: dangerous command blocker (`rm -rf /`, force push main), auto-format (pint, prettier, ruff), long-task notification, audit log, SessionStart context injection.
- **Env vars**: `CLAUDE_SESSION_ID`, `CLAUDE_PROJECT_DIR`, `CLAUDE_TRANSCRIPT_PATH`, `CLAUDE_MODEL`, `CLAUDE_HOOK_EVENT`.
- **settings.json ierarxiyası**: local > project > user > default.
- **Təhlükəsizlik**: hook shell icra edir — code review məcburidir, `.claude/settings.local.json` `.gitignore`-a.
- **Debug**: stderr, log fayl, `/hooks list`.

---

## Praktik Tapşırıqlar

### Tapşırıq 1: Dangerous Command Hook

`PreToolUse` hook yaz. `tool_name === "Bash"` olduqda `input.command`-i yoxla: `rm -rf /` ya da `git push --force origin main` (production branch) kimi pattern-lər varsa `{"decision": "block", "reason": "Dangerous command"}` qaytar. `.claude/settings.json`-a əlavə et, test et.

### Tapşırıq 2: Auto-Format Hook

`PostToolUse` hook yaz. `tool_name === "Edit"` və `.php` fayl dəyişdirildikdə `./vendor/bin/pint {file_path}` icra et. `PostToolUse` hook əgər format dəyişikliyi edirsə `{"decision": "continue"}` qaytar. Laravel Pint-in formatlaşdırdığı faylları Claude-un daha sonra dəyişdirmədiyini yoxla.

### Tapşırıq 3: Session Start Context Injection

`SessionStart` hook yaz. Hook çalışdıqda: `git log --oneline -5` çalışdır (son 5 commit), `cat CLAUDE.md` oxu, nəticəni birləşdirərək `{"context": "..."}` format-da qaytar. Claude Code hər yeni session-da bu konteksti avtomatik alır. Kontekst olmadan vs olduqda Claude-un ilk cavablarının fərqini müqayisə et.

---

## Əlaqəli Mövzular

- `../03-mcp/11-mcp-for-company-laravel.md` — MCP tools ilə Skills arasındakı fərq
- `../02-claude-api/13-claude-agent-sdk.md` — Claude Code-un istifadə etdiyi SDK
- `../08-production/06-ai-testing-strategies.md` — Hook-ların test edilməsi
- `13-agent-security.md` — Hook-lardan gələn input-un sanitization-u
