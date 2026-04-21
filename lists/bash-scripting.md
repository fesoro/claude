## Shebang / safety

#!/usr/bin/env bash — portable shebang
#!/bin/bash — absolute path
#!/bin/sh — POSIX (bashism-lər işləməz)
set -e — ilk errorda dayan
set -u — undefined variable error
set -o pipefail — pipe-də istənilən fail → overall fail
set -euo pipefail — canonical strict mode
set -x / set +x — komandaları echo et (trace)
set -n — syntax check (execute etmə)
set -E — ERR trap funksiyalara ötürülür
set -f — globbing söndür
shopt -s nullglob — no-match → boş list
shopt -s globstar — ** recursive
shopt -s extglob — extended pattern (?(x), !(x))
shopt -s inherit_errexit — subshell-ə errexit ötür
IFS=$'\n\t' — word splitting təhlükəsiz

## Variables

name=value — təyin (= ətrafında boşluq YOX!)
readonly CONST=1 — immutable
declare -i num=5 — integer
declare -a arr — indexed array
declare -A map — associative array
declare -r — readonly
declare -x — export
declare -l — lowercase
declare -u — uppercase
local var=x — funksiya daxilində
unset var — sil
export VAR=val — child process-ə ötür
"$var" / "${var}" — quote-la! (word splitting)
${var:-default} — boş/undef-sə default
${var:=default} — təyin et əgər boşdursa
${var:?error msg} — boşdursa error
${var:+other} — varsa other, yoxdursa boş
${var:offset:length} — substring
${#var} — string uzunluğu
${var/pat/repl} — ilk əvəzləmə
${var//pat/repl} — bütün
${var#prefix} / ${var##prefix} — başdan sil (qısa/uzun)
${var%suffix} / ${var%%suffix} — sondan sil
${var^} / ${var^^} — upper (ilk/bütün)
${var,} / ${var,,} — lower
${!prefix*} / ${!prefix@} — prefix ilə dəyişənlər
${!arr[@]} — array key-lər
${arr[@]} vs ${arr[*]} — elementləri ayrı / bir

## Arithmetic

$((1 + 2)) — arithmetic expansion
(( x = 1 + 2 )) — arithmetic command
(( x++ )) / (( x-- ))
(( x += 5 )) / (( x *= 2 ))
$(( RANDOM % 100 ))
let "x = 5 + 3" — köhnə
expr 5 + 3 — köhnə, fork edir
bc — float üçün: echo "scale=2; 1/3" | bc
awk 'BEGIN{print 1/3}' — float alternativ

## String manipulation

"$a$b" — concatenate
"$a $b" — space ilə
${#str} — uzunluq
${str:2:5} — substring
${str,,} / ${str^^} — case
${str/old/new} — replace
tr 'a-z' 'A-Z' <<< "$str"
echo "$str" | cut -d: -f2
echo "$str" | awk -F: '{print $2}'
printf -v var "%s=%d" "key" 42 — format into variable
printf "%-20s %5d\n" "name" 42 — aligned
read -r var — whitespace-safe input
IFS=, read -ra arr <<< "a,b,c" — split to array

## Arrays (indexed)

arr=(a b c) — literal
arr[0]="x"
arr+=("new") — append
echo "${arr[0]}" / "${arr[-1]}"
echo "${arr[@]}" — bütün elementlər
echo "${#arr[@]}" — uzunluq
echo "${!arr[@]}" — index-lər
unset 'arr[1]' — element sil
for x in "${arr[@]}"; do echo "$x"; done
arr=("${arr[@]:1}") — ilk elementi sil (shift)
arr=("${arr[@]:0:2}") — slice
mapfile -t lines < file.txt — fayldan array
readarray -t lines < file.txt — eyni

## Associative arrays (bash 4+)

declare -A map
map[key1]=value1
map[key2]="another"
echo "${map[key1]}"
echo "${!map[@]}" — açarlar
echo "${map[@]}" — dəyərlər
for k in "${!map[@]}"; do echo "$k=${map[$k]}"; done
unset 'map[key1]'

## Conditionals

if [[ cond ]]; then ...; elif [[ ... ]]; then ...; else ...; fi
[[ ... ]] — bash-built-in (safer)
[ ... ] / test — POSIX (quote-unutma)
case "$x" in
  a|A) echo "A";;
  b*) echo "starts with b";;
  *)  echo "other";;
esac
case ... ;;& — davam et (test sonrakılara keç)
[[ -z "$s" ]] — boş string
[[ -n "$s" ]] — boş deyil
[[ "$a" == "$b" ]] — bərabər (string)
[[ "$a" != "$b" ]]
[[ "$a" == prefix* ]] — glob match
[[ "$a" =~ ^[0-9]+$ ]] — regex match
[[ "$a" < "$b" ]] — leksikoqrafik
(( x == 5 )) — arithmetic
(( x > 5 && y < 10 ))
&& / || — conditional execution (cmd1 && cmd2 || cmd3)

## Test operators

-e path — exists
-f path — regular file
-d path — directory
-L path — symlink
-r / -w / -x path — readable/writable/executable
-s path — exists and non-empty
-N path — modified since last read
-O / -G path — owned by user/group
path1 -nt path2 — newer than
path1 -ot path2 — older than
path1 -ef path2 — same inode
-z str — empty
-n str — non-empty
str1 = str2 / str1 != str2
-eq / -ne / -lt / -le / -gt / -ge — integer (-eq NOT == in [ ])
! expr — negation
expr1 -a expr2 — AND (deprecated, use &&)
expr1 -o expr2 — OR (deprecated, use ||)

## Loops

for i in 1 2 3; do echo "$i"; done
for i in {1..10}; do echo "$i"; done — brace expansion
for i in {1..10..2}; do ...; done — step
for ((i=0; i<10; i++)); do ...; done — C-style
for f in *.txt; do ...; done — globbing
for f in "$dir"/*; do ...; done — directory içində
while read -r line; do echo "$line"; done < file.txt
while IFS=, read -r a b c; do ...; done < csv
until [[ cond ]]; do ...; done
break / break N — N level break
continue / continue N
select opt in a b c; do ... break; done — menu

## Functions

function name() { ... }
name() { ... }  # preferred
name() {
  local arg1="$1" arg2="$2"
  local -n ref="$3"  # nameref (bash 4.3+)
  echo "$@"          # all args
  echo "$#"          # arg count
  echo "$0"          # script name
  echo "$FUNCNAME"   # current function name
  return 0           # exit code
}
name arg1 arg2 — çağırış
$? — son exit code
Return via echo + $(name) — output capture
Shift — shift 2

## Argument parsing — getopts

while getopts "hvf:o:" opt; do
  case "$opt" in
    h) usage; exit 0 ;;
    v) verbose=1 ;;
    f) file="$OPTARG" ;;
    o) output="$OPTARG" ;;
    *) usage; exit 1 ;;
  esac
done
shift $((OPTIND-1))
# Long options → manual while [[ $# -gt 0 ]]; case "$1" in --help) ...; shift;; esac

## Redirection / pipes

cmd > file — stdout redirect (overwrite)
cmd >> file — append
cmd < file — stdin from file
cmd 2> err.log — stderr redirect
cmd 2>&1 — stderr → stdout
cmd &> file / cmd >file 2>&1 — hər ikisi
cmd 2>/dev/null — stderr at
cmd >/dev/null 2>&1 — hər ikisini at
cmd1 | cmd2 — pipe
cmd1 |& cmd2 — stderr+stdout pipe
cmd < <(other) — process substitution (stdin)
cmd >(other) — process substitution (stdout)
diff <(cmd1) <(cmd2)
exec > log 2>&1 — script daxili bütün output
exec 3<> file — FD 3 oxu+yaz
exec 3>&- — FD 3 bağla
tee file — stdout + fayl
tee -a file — append
mktemp / mktemp -d — təhlükəsiz temp

## Heredoc / herestring

cat <<EOF
line with $var interpolated
EOF

cat <<'EOF'
no interpolation
EOF

cat <<-EOF
  leading tabs stripped
EOF

cmd <<< "string"      # herestring
read -r var <<< "$x"

## Trap / signals

trap 'echo "interrupt"; exit 1' INT TERM
trap 'cleanup' EXIT — script bitəndə həmişə
trap '' INT — ignore
trap - INT — reset default
trap 'echo "line: $LINENO"; exit 1' ERR — set -e ilə
Signals — INT (2), TERM (15), HUP (1), QUIT (3), KILL (9), USR1/USR2
trap -p — list

## Subshells / grouping

(cmd1; cmd2) — subshell (cwd, variables isolated)
{ cmd1; cmd2; } — current shell group (; or newline + space)
$(cmd) — command substitution (preferred)
`cmd` — backticks (legacy)
(( ... )) — arithmetic
[[ ... ]] — test
coproc name { cmd; } — async coprocess

## Background / jobs

cmd & — background
jobs — list
fg %1 / bg %1 — foreground/background
wait — wait all children
wait $PID — wait specific
wait -n — wait any (bash 4.3+)
disown %1 — unlink from shell
nohup cmd & — HUP ignore
$! — last background PID
$$ — current shell PID
$PPID — parent PID
kill %1 / kill -TERM $PID
trap handler EXIT — cleanup on exit

## Common commands used in scripts

basename / dirname / realpath / readlink -f
date / date -u / date +%Y-%m-%d / date -d "yesterday"
stat -c '%s %n' file
find . -type f -name "*.log" -mtime +7 -delete
find . -type f -exec cmd {} \; / {} +
xargs -I {} cmd {} / xargs -n 1 / xargs -P 4
sort / sort -n / -u / -k 2 / -t,
uniq / uniq -c / -d / -u
head -n 10 / tail -n 10 / tail -f
grep / grep -E / -F / -i / -v / -r / -l / -c / -o / -P
sed -i 's/a/b/g' file / sed -n '1,5p' / sed '/pattern/d'
awk '{print $1}' / awk -F: '$3 > 1000' / awk 'NR==1'
cut -d: -f1 / cut -c1-5
tr 'a-z' 'A-Z' / tr -d '\r' / tr -s ' '
paste / join -t, file1 file2
tee / split / shuf / rev
md5sum / sha256sum / cksum
envsubst — env var substitution in template
jq '.key' / jq -r '.[]' — JSON
yq — YAML equivalent
curl -fsSL -o out url — fail+silent+show-error+follow
wget -qO- url
getopt — enhanced, long options (POSIX util)

## Debug / tracing

bash -n script.sh — syntax check
bash -x script.sh — trace
set -x / set +x — around a block
PS4='+ ${BASH_SOURCE}:${LINENO}:${FUNCNAME[0]:-}: ' — custom trace prefix
shellcheck script.sh — static analysis (must-have)
bashdb — debugger
trap 'echo "err on line $LINENO"' ERR

## Script template

#!/usr/bin/env bash
set -euo pipefail
IFS=$'\n\t'

readonly SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
readonly SCRIPT_NAME="$(basename "$0")"

usage() {
  cat <<EOF
Usage: $SCRIPT_NAME [-h] [-v] <arg>
EOF
}

log() { printf '[%s] %s\n' "$(date +%H:%M:%S)" "$*" >&2; }
die() { log "ERROR: $*"; exit 1; }

cleanup() { rm -rf "$TMPDIR"; }
trap cleanup EXIT

TMPDIR="$(mktemp -d)"

main() {
  [[ $# -lt 1 ]] && { usage; exit 1; }
  log "starting"
  # ...
}

main "$@"

## Useful gotchas

"$@" vs $* — hər arg ayrı vs bir string (həmişə "$@" istifadə et)
[[ ]] > [ ] — lexing fərqli, quote ehtiyacı az
foo | while read — subshell! variables itir → use process substitution: while read; do ...; done < <(foo)
$(cmd) → yeni satır trim olunur (son)
read without -r — backslash pozur, həmişə -r istifadə et
find -exec {} \; (per-file fork) vs {} + (batched)
Empty globs — shopt -s nullglob
Spaces in filenames — "$f" quote, find -print0 + xargs -0
eval → təhlükəli, alternativ: array, printf -v, declare
Exit codes — 0 = success, 1-255 = error, 126 = not exec, 127 = not found, 130 = Ctrl+C
PIPESTATUS — array of exit codes in pipe
