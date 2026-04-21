## Modes

Normal — default, naviqasiya + komanda (Esc / Ctrl-[)
Insert — mətn daxil et (i, a, o)
Visual — seçim (v, V, Ctrl-v)
Command-line — : komandaları, / search
Replace — mətni əvəz et (R)
Terminal — :terminal (nvim / vim 8+)

## Enter insert mode

i — kursordan əvvəl
I — xəttin əvvəlində (first non-blank)
a — kursordan sonra
A — xəttin sonunda
o — aşağıda yeni xətt
O — yuxarıda yeni xətt
s — simvol sil + insert
S / cc — xətti sil + insert
C — kursordan xətt sonuna qədər dəyiş
gi — son insert yerinə qayıt

## Basic movement (normal mode)

h / j / k / l — sol / aşağı / yuxarı / sağ
w — next word start
W — next WORD (whitespace-separated)
b — previous word start
B — previous WORD
e — next word end
ge — previous word end
0 — xəttin başı
^ — first non-blank
$ — xəttin sonu
g_ — son non-blank
gg — faylın başı
G — faylın sonu
25G / :25 — 25-ci xəttə
H / M / L — screen top / middle / bottom
Ctrl-d / Ctrl-u — half page down/up
Ctrl-f / Ctrl-b — full page
Ctrl-e / Ctrl-y — scroll line (cursor stays)
zz / zt / zb — cursor center/top/bottom of screen
zh / zl — scroll horizontal
% — matching bracket ( { [
( / ) — sentence back/forward
{ / } — paragraph back/forward
[[ / ]] — section

## Character search in line

f<char> — sağdan tap (on char)
F<char> — soldan tap
t<char> — before char (till)
T<char> — backward till
; — son f/t təkrarla
, — əks istiqamətdə
* — cursor altındakı sözü sonrakıya tap
# — əvvəlkinə tap
g* / g# — partial match

## Text objects (used with operator: d, c, y, v)

iw / aw — inner word / around word (with space)
iW / aW — inner/around WORD
is / as — sentence
ip / ap — paragraph
i" / a" / i' / a' / i` / a` — quoted string
i( / a( / i) / a) / ib / ab — parentheses
i[ / a] / i{ / a} / iB / aB — brackets
i< / a> / it / at — HTML/XML tag
i* / a* — user-defined (plugins)
Visual example: vi" — select inside quotes; ca( — change around parens
Targets.vim — in next/last (in", an)
vim-surround: cs"' — change " to '; ds" — delete surrounding "; ysiw" — surround word with "

## Operators

d — delete (cut)
c — change (delete + insert)
y — yank (copy)
p / P — paste after / before
>> / << — indent / dedent
= — auto-indent
~ — toggle case
gu / gU — lowercase / uppercase
g~ — toggle case
gq — format (wrap text)
. — son dəyişikliyi təkrarla

## Common edits

x — simvol sil (cursor altında)
X — əvvəlki simvol sil
dd — xətt sil
D / d$ — xətt sonuna qədər
d0 — xətt başına qədər
dw / db / de — söz
d2w — 2 söz
dG — faylın sonuna qədər
dgg — faylın əvvəlinə qədər
di" / da( / dit — text object
yy — xətt yank
Y — xətt yank (sync ilə)
3yy — 3 xətt
yw — söz yank
p — paste aşağı/sağ
P — paste yuxarı/sol
]p / [p — indent-matched paste
u — undo
Ctrl-r — redo
U — xətti restore
r<ch> — tək simvolu dəyiş
R — replace mode
cw — sözü dəyiş
C — xətt sonuna qədər dəyiş
J — aşağı xətti qoş
gJ — join without space
xp — swap iki simvol
ddp — swap iki xətt
Ctrl-a / Ctrl-x — rəqəmi artır / azalt
g Ctrl-a — visual sequence

## Counts + motions

[count][operator][motion]
3dw — 3 söz sil
5j — 5 xətt aşağı
10dd — 10 xətt sil
d3e — 3 word-end-ə qədər sil
y$ — xətt sonuna qədər yank
c3w / 3cw — eyni

## Search / replace

/pattern — irəli axtar
?pattern — geri axtar
n — sonrakı
N — əvvəlki
* / # — kursor sözünü ir/ger
/\v magic — very magic (Perl regex)
/\c — ignore case (bu axtarışa)
/\C — case sensitive
:set hlsearch / nohlsearch / incsearch
:noh — highlight söndür
:%s/old/new/g — hamısını əvəz et (faylda)
:%s/old/new/gc — təsdiq ilə
:%s/old/new/gi — ignore case
:s/old/new/ — cari xəttdə (ilk)
:s/old/new/g — cari xəttdə hamısı
:10,20s/old/new/g — 10-20 xəttdə
:'<,'>s/... — visual seçimdə
:g/pattern/d — pattern-i olan xətlərin hamısını sil
:g/pattern/cmd — pattern olan xətlərdə cmd
:v/pattern/d — olmayan xətləri sil
\1 \2 — capture group (in replacement)
& — bütün match
~ — son replacement
:&& — son substitute təkrarla (same flags)
:s//new/ — son axtarışa substitute

## Visual mode

v — character-wise
V — line-wise
Ctrl-v — block-wise
gv — son seçimi bərpa
o — seçimin digər ucuna keç
Seçimdə: d (sil), c (dəyiş), y (yank), > (indent), = (auto-indent), ~ (toggle case), u/U (lower/upper)
Visual block insert — Ctrl-v → seç → I → yaz → Esc (bütün xətlərə tətbiq olur)
Visual block append — Ctrl-v → seç → A → yaz → Esc

## Macros

q<letter> — qeyd başlat
q — dayandır
@<letter> — oynat
@@ — son makronu təkrarla
5@a — 5 dəfə
qA — makroya əlavə et (append)
:let @a = 'text' — dəyişənə yaz
"ap — makro içindəkini paste
:reg — bütün registers
Multi-line macro — j aşağı hərəkət ilə ilk xəttdə qeyd et

## Registers

"ayy — "a registerə yank
"ap — "a registerdən paste
"* / "+ — system clipboard (X11 primary / system)
"0 — son yank
"1-"9 — son dəletion ring
"_ — black hole (nothing)
"= — expression register (=1+2 insert olaraq)
"/ — son axtarış
": — son command
". — son inserted text
"% — file name
"# — alternate file
:reg / :reg a — baxış

## Windows (splits)

:sp[lit] file — horizontal split
:vs[plit] file / :vsp file — vertical
Ctrl-w s / Ctrl-w v — split current
Ctrl-w h/j/k/l — sol/aşağı/yuxarı/sağ pəncərəyə keç
Ctrl-w w — next window
Ctrl-w W — previous
Ctrl-w p — previous window
Ctrl-w q / :q — close current
Ctrl-w c — close
Ctrl-w o — bütün digərlərini bağla
Ctrl-w H/J/K/L — pəncərəni sola/aşağı/yuxarı/sağa sür
Ctrl-w r / R — rotate
Ctrl-w = — bərabər ölçü
Ctrl-w + / - — yüksəklik artır/azalt
Ctrl-w > / < — en
Ctrl-w _ — maksimum hündürlük
Ctrl-w | — maksimum en
Ctrl-w T — tab-ə aç
:resize 20 / :vertical resize 50

## Tabs

:tabnew / :tabe file — yeni tab
gt / :tabn — sonrakı
gT / :tabp — əvvəlki
{n}gt — n-ci tab
:tabc — bağla
:tabo — digərləri bağla
:tabs — list
:tabm [N] — hərəkət etdir
Ctrl-w T — window → yeni tab

## Buffers

:e file — aç (cari window-da)
:bn / :bp — next / previous
:bN — N-ci buffer
:b name — name prefix ilə
:ls / :buffers — list
:bd — delete current
:bd N — N-ci buffer delete
:bufdo cmd — hər buffer-də cmd
Ctrl-^ / Ctrl-6 — alternate buffer
:e! — re-read disk
:enew — boş buffer

## Marks

m<letter> — işarə qoy (a-z local, A-Z global)
`a — konkret pozisiya (line + col)
'a — xətt başı
`` — son pozisiya
'' — son xətt
`. — son dəyişiklik
`^ — son insert
`" — faylı son açanda olduğun yer
:marks — list
:delmarks a / :delmarks! — sil

## Jumps / changes

Ctrl-o — əvvəlki pozisiya
Ctrl-i — sonrakı pozisiya
:jumps — list
g; — əvvəlki dəyişiklik
g, — sonrakı
:changes — list
`` — son jump-dan əvvəlki yerə qayıt

## Folds

zf{motion} — fold yarat (zfap → paragraph)
zo / zc — aç / bağla
za — toggle
zO / zC / zA — recursive
zr / zm — bütün fold-ları aç/bağla bir level
zR / zM — hamısı
zd — fold sil
:set foldmethod=indent/syntax/marker/manual/expr
zj / zk — sonrakı/əvvəlki fold

## File / save commands

:w — save
:w file — save as
:wa — bütün
:wq / :x / ZZ — save + quit
:q — quit
:q! / ZQ — force quit (no save)
:wq! — force save + quit
:qa / :qa! — bütün
:e! — re-read
:r file — faylın məzmununu insert et
:r !cmd — komandanın çıxışını insert
:!cmd — shell komanda
:w !cmd — buffer-i komandaya pipe
:.!cmd — xətti komanda ilə əvəz
:%!cmd — faylı əvəz
:saveas file
:f newname — file name dəyiş

## Command-line / Ex

: — command-line
:help cmd — kömək
:help 'option' — option üçün kömək
:set option / :set nooption / :set option!
:set option? — cari dəyər
:let var = 'x'
:echo var
:source file — vimscript exec
:runtime file
:source %  — cari faylı exec
@: — son : command təkrarla
q: — command history pəncərəsi (editable)
q/ — search history
Ctrl-r" / Ctrl-r a — register-i command-line-a paste

## Useful :commands

:version — Vim versiyası + features
:scriptnames — yüklənmiş faylları
:mes[sages] — son mesajlar
:set list / nolist — whitespace göstər
:set number / nu / nonu
:set relativenumber / rnu
:set wrap / nowrap
:set expandtab / noexpandtab
:set tabstop=4 shiftwidth=4 softtabstop=4
:set autoindent smartindent
:set ignorecase smartcase
:set clipboard=unnamedplus — sistem clipboard
:set mouse=a
:syntax on / off
:colorscheme name
:filetype detect / filetype plugin indent on
:retab — tab → space
:sort / :sort u / :sort! / :sort n
:earlier 5m / :later 5m — undo tree time travel
:undolist / :earlier 10 (10 change back)
:diffthis / :diffoff / :diffupdate
:vimdiff file1 file2 — external
]c / [c — next/prev diff
do / dp — diff obtain / put
:cd dir / :pwd
:TOhtml — HTML export
:terminal / :term — embedded terminal (vim 8+ / nvim)

## QuickFix / location list

:make / :cexpr system('cmd')
:copen / :cclose / :cwindow
:cn / :cp — next/prev
:cnf / :cpf — next file
:cfirst / :clast
:grep pattern files / :grepadd
:vimgrep /pattern/ **/*.ext
:lopen — location list (per window)
:lnext / :lprev
:cdo cmd / :ldo cmd — hər nəticədə cmd
:cfdo cmd — hər faylda

## Netrw / file browser

:Ex[plore] — file browser aç
:Sex / :Vex — split ilə
:Lex — left explorer
- — parent dir (netrw)
<Enter> — aç
% — yeni fayl yarat
d — yeni qovluq
D — sil
R — adını dəyişdir

## Useful remaps / tips

:imap jk <Esc> — jk = escape
:nnoremap <leader>w :w<CR>
<leader> — usually \ or remapped to space
:nnoremap ; :
:let mapleader = ' '
:set nocompatible — Vi uyğunluq söndür (Vim default)

## Config

~/.vimrc (Vim) / ~/.config/nvim/init.vim | init.lua (Neovim)
:scriptnames — hansı fayl yüklənib
Plugin managers — vim-plug, Packer (Lua), lazy.nvim, paq-nvim, Vundle (legacy)
LSP — nvim built-in (0.5+), vim-lsp, coc.nvim
Treesitter — nvim-treesitter (syntax highlight/parse)
fzf / fzf-lua / telescope.nvim — fuzzy finder
NERDTree / nvim-tree / neo-tree — file tree
vim-surround / vim-commentary / vim-fugitive — popular
which-key.nvim — keybinding hint
