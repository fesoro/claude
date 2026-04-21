## Built-in slash commands

/help — mövcud komandaları göstər
/clear — söhbəti təmizlə (bütün history silinir)
/compact — konteksti xülasə edərək yer boşalt
/cost — cari session-un token və dollar xərcini göstər
/status — cari status (model, plan, version)
/doctor — konfiqurasiyasını yoxla, environment diagnostics
/init — CLAUDE.md faylı yarat (layihə kontekstini öyrənib yazır)
/memory — memory fayllarını idarə et (yarat / redaktə / sil)
/model — modeli göstər / dəyiş (opus, sonnet, haiku, opusplan)
/fast — Fast mode aç/bağla (yalnız Opus 4.6-da mövcud)
/vim — vim editing mode aç/bağla
/review — branch / PR / dəyişiklikləri review et
/security-review — security review et (vulnerability axtarışı)
/login — hesaba daxil ol (Anthropic API key və ya Claude ilə)
/logout — hesabdan çıx
/logout --force — bütün credential-ları sil
/mcp — MCP server statusu və connect/disconnect
/permissions — fayl icazə ayarları (hansı tool icazəsi var)
/add-dir <path> — əlavə working directory əlavə et
/config — settings.json-u aç
/bug — bug report göndər
/release-notes — son update-ın dəyişikliklərini göstər
/agents — subagent-ləri idarə et (yarat / sil / redaktə)
/hooks — hook konfiqurasiyası (PreToolUse, PostToolUse, UserPromptSubmit)
/export — cari conversation-u fayla yaz
/resume — əvvəlki conversation-u bərpa et
/ide — IDE inteqrasiyası (VS Code, JetBrains)

## Prefix short-cuts

! <command> — shell əmrini birbaşa icra et (məs: ! git status)
@ <filename> — fayla istinad et (məs: @src/Main.java; avto-complete işləyir)
# <instruction> — system səviyyəsində təlimat ver (CLAUDE.md-ə əlavə)
$ <var> — env variable referansı

## Keyboard shortcuts

Ctrl+C — cari işi dayandır (interrupt)
Ctrl+D — exit (iki dəfə basanda)
Ctrl+L — ekranı təmizlə (UI, history saxlanılır)
Ctrl+R — reverse search (əvvəlki prompt-ları tap)
Shift+Tab — planning mode aç (ExitPlanMode əvəzlənir)
Tab — auto-complete (fayllar, komandalar)
Up/Down — əvvəlki prompt-ları seç
Esc — cari model cavabını dayandır (generate)
Esc Esc — rewind; cari prompt-u redaktə et
Ctrl+J — newline (prompt-u submit etmədən)

## CLI flag-ları (terminal-dan)

claude — interaktiv mode başlat
claude -p "prompt" — bir-shot mode (cavab verib çıxar)
claude --model opus — model seç
claude --resume <session-id> — əvvəlki session-u bərpa et
claude --continue — ən son session-u davam et
claude --append-system-prompt "..." — system prompt-a əlavə et
claude --add-dir <path> — əlavə directory əlavə et
claude --dangerously-skip-permissions — bütün tool-lara icazə ver (təhlükəli!)
claude --verbose — debug output
claude --output-format json — JSON output (scripting üçün)
claude --mcp-config mcp.json — MCP server config yüklə
claude doctor — diagnostics
claude config — config redaktə
claude migrate-installer — npm → native installer-ə keç
claude update — latest versiona yenilə
claude --version — versiyanı göstər

## MCP (Model Context Protocol)

claude mcp list — qoşulmuş MCP server-ləri göstər
claude mcp add <name> <command> — MCP server əlavə et
claude mcp remove <name> — MCP server sil
claude mcp add-from-claude-desktop — Claude Desktop-dan import et

## Hooks (settings.json)

PreToolUse — tool çağırılmadan əvvəl
PostToolUse — tool bitdikdən sonra
UserPromptSubmit — user mesajı göndərdikdə
Stop — Claude cavabı bitirdikdə
SubagentStop — subagent bitirdikdə
PreCompact — compaction-dan əvvəl
SessionStart — yeni session başladıqda

## Agents (.claude/agents/*.md)

Custom subagent — öz specialized agent-ini yarat
Frontmatter: name, description, model, tools
Invoke: Task tool ilə subagent_type parametri
Built-in: general-purpose, Explore, Plan, statusline-setup

## Config files

~/.claude/settings.json — user-level config
.claude/settings.json — project-level (team-shared)
.claude/settings.local.json — project-level (gitignored)
CLAUDE.md — project context (auto-loaded)
~/.claude/CLAUDE.md — global context
.claude/commands/ — custom slash commands (markdown)
.claude/agents/ — custom subagents
.claude/hooks/ — hook scripts
