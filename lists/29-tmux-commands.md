## Sessions

tmux                                  — yeni session
tmux new -s work                      — adlandırılmış session
tmux new -s work -d                   — detached
tmux new -s work 'cmd'                — initial command ilə
tmux ls / tmux list-sessions          — sessions
tmux attach / tmux a                  — son session-a qoşul
tmux a -t work                        — konkret session-a
tmux a -d -t work                     — başqa client-i detach edib qoşul
tmux kill-session -t work
tmux kill-server                      — bütün session-ları sil
tmux rename-session -t old new
tmux switch-client -t work            — session arasında keç (tmux daxilində)
tmux has-session -t work && echo OK   — script idiom
tmux source ~/.tmux.conf              — config reload

# default prefix: Ctrl-b (yaygın olaraq Ctrl-a-ya remap olunur)

## Session keys (prefix-dən sonra)

prefix d            — detach (session arxa fonda qalır)
prefix D            — detach interactively (multiple clients)
prefix s            — session list (interactive)
prefix $            — rename session
prefix (            — previous session
prefix )            — next session
prefix L            — last session
prefix :new         — yeni session yarat (command mode)
prefix : kill-session

## Windows (tab kimi)

prefix c            — yeni window
prefix ,            — rename current window
prefix &            — kill window (təsdiq ilə)
prefix n            — next
prefix p            — previous
prefix l            — last (toggle)
prefix 0..9         — n-ci window
prefix '            — prompt for window index
prefix w            — window list (preview ilə)
prefix .            — move window (rename index)
prefix f            — find window by text
tmux new-window -n logs 'tail -f /var/log/syslog'
tmux rename-window logs
tmux move-window -t 5

## Panes (split)

prefix %            — vertical split (sol/sağ)
prefix "            — horizontal split (üst/alt)
prefix arrow        — pane-ə keç (↑↓←→)
prefix h/j/k/l      — vim-style (configure et)
prefix o            — sonrakı pane
prefix ;            — son aktiv pane
prefix q            — pane nömrələrini göstər (qısa müddət)
prefix q 1          — pane 1-ə keç
prefix x            — pane-i kill et (təsdiq ilə)
prefix z            — pane zoom toggle (full-screen)
prefix !            — pane → yeni window
prefix space        — layout cycle
prefix Alt-1..5     — preset layout (even-h, even-v, main-h, main-v, tiled)
prefix {            — pane swap left
prefix }            — pane swap right
prefix Ctrl-arrow   — pane resize 1 row/col
prefix Alt-arrow    — pane resize 5 rows/cols
prefix :resize-pane -L 10 / -R 10 / -U 5 / -D 5
prefix :swap-pane -t :1.0
prefix :join-pane -s 1 -t 2  — pane 1 → window 2
prefix :break-pane            — pane → öz window-u
prefix t            — saat göstər
prefix Ctrl-o       — bütün pane-ləri rotate et

## Copy mode (vi keys)

prefix [            — copy mode (scroll back)
q / Esc             — copy mode-dan çıx
hjkl / arrows       — naviqasiya
w / b / e           — word
0 / $               — line start/end
gg / G              — file start/end
Ctrl-u / Ctrl-d     — half page
?pattern / /pattern — search backward/forward
n / N               — next / previous match
Space               — selection start (vi mode)
v                   — character selection (vi)
V                   — line selection (vi)
Ctrl-v              — block selection (vi)
Enter / y           — copy selection (yank)
prefix ]            — paste from buffer
prefix =            — buffer list

# Set vi mode in config:
# setw -g mode-keys vi

# Copy to system clipboard (Linux + xclip):
# bind -T copy-mode-vi y send-keys -X copy-pipe-and-cancel "xclip -sel clip -i"
# macOS: pbcopy   |  WSL: clip.exe  |  Wayland: wl-copy

## Buffers / clipboard

prefix =            — buffer list
prefix ]            — paste most recent
tmux list-buffers
tmux save-buffer -b 0 file.txt
tmux load-buffer file.txt
tmux show-buffer
tmux delete-buffer -b 0

## Mouse

prefix : set -g mouse on
# Mouse on: scroll, click pane, resize border, drag-select copy

## Status bar

# config:
# set -g status-interval 5
# set -g status-left  "#[fg=green]#S "
# set -g status-right "#[fg=cyan]%Y-%m-%d %H:%M"
# set -g window-status-format "#I:#W"
# set -g window-status-current-format "#[bg=blue,fg=white]#I:#W"

prefix :setw monitor-activity on    — window-də fəaliyyət bildiriş
prefix :setw monitor-bell on
prefix : set -g visual-activity on

## Synchronize panes

prefix :setw synchronize-panes on   — bütün pane-lərə eyni input
prefix :setw synchronize-panes off

## Command mode (prefix :)

prefix :                                 — command prompt
:new-session -s work
:kill-session -t work
:swap-window -s 1 -t 5
:rename-session work2
:source ~/.tmux.conf
:set -g status-style fg=white,bg=black
:setenv KEY value
:show-environment
:show-messages                           — son mesajlar
:list-keys / :list-keys -T copy-mode-vi
:list-commands

## Custom prefix / common config (~/.tmux.conf)

# Prefix → Ctrl-a
unbind C-b
set -g prefix C-a
bind C-a send-prefix

# 1-based indexing (more natural)
set -g base-index 1
setw -g pane-base-index 1
set -g renumber-windows on

# Mouse, history, vi
set -g mouse on
set -g history-limit 50000
setw -g mode-keys vi

# Faster Esc (no delay)
set -sg escape-time 10

# True color
set -g default-terminal "tmux-256color"
set -ga terminal-overrides ",xterm-256color:Tc"

# Reload config
bind r source-file ~/.tmux.conf \; display "Reloaded!"

# Splits keep cwd
bind '"' split-window -v -c "#{pane_current_path}"
bind %   split-window -h -c "#{pane_current_path}"
bind c   new-window     -c "#{pane_current_path}"

# vim-style pane navigation
bind h select-pane -L
bind j select-pane -D
bind k select-pane -U
bind l select-pane -R

# Copy → system clipboard (Linux X11)
bind -T copy-mode-vi y send-keys -X copy-pipe-and-cancel "xclip -sel clip -i"

## Plugins (TPM)

git clone https://github.com/tmux-plugins/tpm ~/.tmux/plugins/tpm
# Add to ~/.tmux.conf:
# set -g @plugin 'tmux-plugins/tpm'
# set -g @plugin 'tmux-plugins/tmux-sensible'
# set -g @plugin 'tmux-plugins/tmux-resurrect'   — save/restore sessions
# set -g @plugin 'tmux-plugins/tmux-continuum'   — auto save every 15min
# set -g @plugin 'tmux-plugins/tmux-yank'         — easy clipboard
# set -g @plugin 'christoomey/vim-tmux-navigator' — vim+tmux pane nav
# run '~/.tmux/plugins/tpm/tpm'
prefix I            — install plugins (after adding @plugin lines)
prefix U            — update
prefix Alt-u        — uninstall removed

## Scripting / dev workflow

# Standard "dev" layout: editor + server + logs
tmux new -d -s dev -n code
tmux send-keys -t dev:code 'vim .' Enter
tmux new-window -t dev -n run
tmux send-keys -t dev:run 'php artisan serve' Enter
tmux split-window -t dev:run -h
tmux send-keys -t dev:run.1 'php artisan queue:work' Enter
tmux attach -t dev

# tmuxinator / smug — yaml/text-based session presets
# Example tmuxinator config: ~/.config/tmuxinator/laravel.yml

## Advanced

prefix ?            — bütün keybind-ları göstər
prefix #            — current pane index
prefix Ctrl-z       — suspend tmux client
prefix : show-options -g          — global options
prefix : show-window-options -g
prefix : capture-pane -p          — pane content stdout-a
prefix : capture-pane -S -3000    — son 3000 sətri capture
tmux pipe-pane -t dev:0 'cat >> /tmp/tmux.log'   — pane output stream-ə yaz
tmux clock-mode                    — tam ekran saat
tmux choose-tree                   — sessions/windows tree

## Common gotchas

- prefix-i unutma — hər command prefix-dən sonradır
- escape-time default 500ms — vim üçün set -sg escape-time 10
- tmux 3.x və əvvəli copy mode keybind syntax fərqli (`bind -t` → `bind -T`)
- $TMUX env var → "tmux daxilindəyəm" sinyalı
- tmux attach yalnız mövcud session varsa işləyir; new -A həm yaradır həm attach edir:
  tmux new -A -s work
- ssh-də ConnectionDropped → tmux session sağ qalır (lokal client ölür)
- Multiple clients eyni session-a qoşulduqda ən kiçik terminala qədər kiçilir
  (force-aggregate: prefix :setw aggressive-resize on)
