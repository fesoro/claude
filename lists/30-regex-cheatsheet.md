## Anchors / boundaries

^         line start (multiline mode-da hər line)
$         line end
\A        absolute string start (multiline-də də)
\z        absolute string end (PCRE)
\Z        end of string (or before final newline)
\b        word boundary (between \w and \W)
\B        non-word boundary
\<  \>    word start / word end (POSIX, vim, grep)

## Character classes

.         any char (default — newline yox; /s flag ilə dahil)
[abc]     a or b or c
[^abc]    NOT a/b/c
[a-z]     range
[a-zA-Z0-9_]
\d  \D    digit / non-digit
\w  \W    word char (alnum + _) / non-word
\s  \S    whitespace / non-whitespace
\h  \H    horizontal whitespace (PCRE) — space, tab
\v  \V    vertical whitespace — \n \r \f
\n \r \t \f \v \0     newline / cr / tab / formfeed / vtab / null
\xFF      hex char
é    unicode codepoint (PCRE2)
\p{L}     unicode letter (PCRE)
\p{N}     unicode number
\p{Lu}    uppercase letter
\p{Greek}, \p{Cyrillic}    script
\P{...}   negation

# POSIX classes (inside [ ])
[[:alpha:]]    letters
[[:alnum:]]    letters + digits
[[:digit:]]    \d equivalent
[[:space:]]    whitespace
[[:upper:]] [[:lower:]]
[[:punct:]]    punctuation
[[:xdigit:]]   hex digit
[[:cntrl:]]    control chars
[[:print:]] [[:graph:]] [[:blank:]]

## Quantifiers

*         0 or more (greedy)
+         1 or more (greedy)
?         0 or 1
{n}       exactly n
{n,}      n or more
{n,m}     n to m

# Lazy (non-greedy) — match as few as possible
*?  +?  ??  {n,m}?

# Possessive (PCRE/Java) — no backtracking
*+  ++  ?+  {n,m}+

## Groups / alternation

(abc)         capture group
(?:abc)       non-capturing group
(?P<name>abc) named group (Python / PCRE)
(?<name>abc)  named group (PCRE / .NET / JS)
(?'name'abc)  alt named syntax (PCRE)
\1 \2         backreference (numbered)
\k<name>      named backreference
$1 $2         in replacement string (most flavors)
${name}       named in replacement
a|b           alternation
(a|b)c        — "ac" or "bc"

## Lookaround (zero-width)

(?=...)       lookahead positive  (next chars match)
(?!...)       lookahead negative  (next chars DON'T match)
(?<=...)      lookbehind positive (prev chars match)
(?<!...)      lookbehind negative

# Examples
\d+(?=px)         — digits followed by "px" (px not consumed)
(?<=\$)\d+        — digits preceded by "$"
foo(?!bar)        — foo NOT followed by bar
(?<!un)happy      — happy NOT preceded by un

## Flags / modifiers

i    case-insensitive
m    multiline (^ $ match line bounds)
s    singleline / dotall (. matches \n)
x    extended (whitespace + # comments ignored)
u    unicode (JS, PCRE)
g    global (replace-all in JS/replacement context)
A    anchored (PCRE)
U    ungreedy default (swap greedy/lazy)

# Inline flags (PCRE / Python / JS): (?i), (?im), (?-i), (?i:...)
(?i)hello       — case-insensitive from here
(?i:hello)world — only "hello" case-insensitive
(?x)            — verbose mode

## Common patterns

# Email (pragmatic, not RFC-strict)
^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$

# URL (basic)
^https?://[^\s/$.?#].[^\s]*$

# IPv4
^((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)\.){3}(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)$

# IPv6 (full, simplified)
^([0-9a-fA-F]{1,4}:){7}[0-9a-fA-F]{1,4}$

# UUID v4
^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$

# ISO 8601 date
^\d{4}-\d{2}-\d{2}(T\d{2}:\d{2}:\d{2}(\.\d+)?(Z|[+-]\d{2}:?\d{2})?)?$

# Hex color
^#?([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$

# Phone (loose international)
^\+?[1-9]\d{6,14}$

# Slug (kebab-case)
^[a-z0-9]+(-[a-z0-9]+)*$

# Whitespace trim
^\s+|\s+$

# Markdown link
\[([^\]]+)\]\(([^)]+)\)

# JWT (3 segments base64url)
^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$

# Strong password (≥8, upper, lower, digit, symbol)
^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$

# Semver (basic)
^\d+\.\d+\.\d+(-[0-9A-Za-z.-]+)?(\+[0-9A-Za-z.-]+)?$

# Filepath split (POSIX)
^(.*)/([^/]+)$        — group 1 = dir, group 2 = filename

## Replacement syntax

$1 $2 ...       numbered groups (most flavors)
\1 \2 ...       sed, awk, Vim
${name}         named groups
$&  /  \0       full match
$`              before match (Perl/JS)
$'              after match
\u  \l          uppercase/lowercase next char (Vim, sed)
\U  \L          start uppercase/lowercase region (Vim/Perl)
\E              end \U / \L

## Tool-specific quirks

### grep
grep "pattern" file       — BRE (basic — () {} need \, \( \))
grep -E "pattern" file    — ERE (() {} as-is)
grep -P "pattern" file    — PCRE (lookaround, \d, \b)
grep -F "literal" file    — fixed string (no regex)
grep -r --include="*.php" "pattern"
grep -o "pattern"         — only matches
grep -v "pattern"         — invert
ripgrep (rg)              — modern, default-PCRE-ish, fast
ack / ag (silver searcher) — older alternatives

### sed
sed -E 's/pattern/repl/g' file    — ERE
sed 's/pattern/repl/' file        — BRE (escape () {} +)
sed -i 's/old/new/g' file         — in-place (GNU)
sed -i '' 's/old/new/g' file      — macOS
sed -n '/pattern/p'                — print matches only
sed '/pattern/d'                   — delete matching lines
sed '10,20s/x/y/g'                 — line range
& in replacement = full match (sed/Vim)

### awk
awk '/pattern/ {print $1}' file
awk '$1 ~ /^[0-9]+$/'
awk -v RS='\n' -v FS=','

### Vim
/pattern              search forward (BRE-ish, magic mode)
/\v(group)            very magic (Perl-like — preferred)
/\V                   very nomagic (literal)
:%s/old/new/g         replace all
:%s/\v(\w+) (\w+)/\2 \1/g     swap words

### PHP (PCRE)
preg_match('/pattern/i', $s, $m)
preg_match_all('/p/', $s, $m, PREG_SET_ORDER)
preg_replace('/p/', 'r', $s)
preg_replace_callback('/p/', fn($m) => strtoupper($m[1]), $s)
preg_split('/[,;]/', $s)
# Delimiter can be / # ~ {  — pick one not in pattern
# Modifiers after delimiter: /pattern/imsu

### Python (re)
import re
re.match(r'^p', s)              — anchored at start
re.search(r'p', s)              — anywhere
re.findall(r'p', s)
re.finditer(r'p', s)            — iterator (better for large)
re.sub(r'p', 'r', s)
re.sub(r'p', lambda m: m.group(1).upper(), s)
re.compile(r'p', re.I | re.M)
# Use raw strings r'...' to avoid \\\\ hell

### JavaScript
const re = /pattern/gi
str.match(re) / str.matchAll(re)
str.replace(re, '$1')
str.replace(re, (m, g1) => g1.toUpperCase())
str.search(re)
re.test(str)
new RegExp("p", "gi")           — dynamic
# Modifiers: i g m s u y d
# String.prototype.replaceAll for fixed strings (no regex needed)

### Java
Pattern p = Pattern.compile("pattern", Pattern.CASE_INSENSITIVE);
Matcher m = p.matcher(s);
while (m.find()) m.group(1);
"text".matches("^p$");          — full match (anchored implicitly)
s.replaceAll("p", "r");
s.replaceFirst("p", "r");
# Backslash: "\\d" in Java string for \d

### Go
re := regexp.MustCompile(`pattern`)         — backtick raw string
re.MatchString(s)
re.FindString(s) / FindAllString(s, -1)
re.FindStringSubmatch(s)
re.ReplaceAllString(s, "$1")
# RE2 syntax — no lookaround, no backrefs (linear-time guarantee)

## Engine performance / pitfalls

- Catastrophic backtracking — nested quantifiers on same chars: (a+)+, (a|a)+
  Use possessive (a++) or atomic groups (?>a+)
- Greedy by default — .* matches as much as possible; lazy .*? for HTML/JSON-like
- Don't parse HTML with regex (well-known meme); use a parser
- Anchor when possible (^ $ \A \z) — full-match faster than substring
- Compile once, reuse (Python re.compile, Java Pattern.compile, Go regexp.MustCompile)
- RE2 (Go, Rust regex crate) — linear time, no backrefs/lookaround
- PCRE / .NET / Java — backtracking engines, support advanced features but can blow up
- Test with samples — regex101.com, rubular.com, regexr.com
- Whitespace + comments: /x flag (PCRE/Python) or /\v (Vim)
- Always validate input length before complex regex on user input (DoS prevention)

## Quick lookup table

\d        digit                   [0-9]
\w        word char               [A-Za-z0-9_]
\s        whitespace              [ \t\r\n\f\v]
.         any (no \n)
^ $       line/string anchors
\b        word boundary
*  +  ?   0+, 1+, 0/1
{n} {n,m} repetition
( )       capture
(?: )     non-capture
(?= )     lookahead +
(?! )     lookahead -
(?<= )    lookbehind +
(?<! )    lookbehind -
|         alternation
\1 \2     backref
$1 ${name} replacement reference
