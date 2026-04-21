## Navigation / filesystem

ls -la — gizli fayllarla detallı listele
ls -lh — human-readable ölçülər
ls -lt — modification time-a görə sırala
pwd — cari direktoriya
cd <dir> — direktoriyaya keç
cd - — əvvəlki direktoriyaya qayıt
cd ~ — home
mkdir -p <path> — iç-içə direktoriya yarat
rm -rf <dir> — direktoriya və məzmununu sil (DİQQƏT)
cp -r <src> <dst> — rekursiv kopyala
cp -a <src> <dst> — atributları saxla
mv <src> <dst> — köçür / adını dəyiş
touch <file> — boş fayl yarat / timestamp dəyiş
ln -s <target> <name> — symlink yarat
ln <target> <name> — hardlink
stat <file> — detallı fayl info (inode, mtime, ctime, atime)
readlink -f <link> — symlink-i resolve et
file <file> — fayl tipini təyin et
basename <path> / dirname <path>

## File content

cat <file> — fayl məzmununu göstər
cat -n <file> — sətir nömrələri
tac <file> — reverse print
less <file> — səhifə-səhifə göstər (q çıx, / axtar)
more <file> — sadə pager
head -n 20 <file> — ilk 20 sətir
tail -n 20 <file> — son 20 sətir
tail -f <file> — faylı canlı izlə
tail -F <file> — rotate-dan sonra reconnect
tail -n +10 <file> — 10-cu sətirdən sonuna qədər
wc -l <file> — sətir sayı
wc -w / wc -c
tr 'a-z' 'A-Z' — char translate
rev <file> — hər sətri tərsinə
nl <file> — sətir nömrəsi əlavə et
cut -d',' -f1,3 <file> — CSV sütun çıxar
cut -c 1-10 <file> — bayt range
paste f1 f2 — iki faylı sətir-sətir birləşdir
join f1 f2 — SQL-join kimi
comm f1 f2 — müqayisə
diff f1 f2 — fərq
sdiff f1 f2 — side-by-side
patch < diff.patch

## Search

grep -r "pattern" <dir> — rekursiv axtarış
grep -i "pattern" <file> — case-insensitive
grep -v "pattern" — inversiya (olmayan)
grep -n "pattern" — sətir nömrələri
grep -E (və ya egrep) — extended regex
grep -F — literal (fixed string)
grep -A 3 -B 3 — context
grep -c — yalnız count
grep -l — yalnız match olan fayl adları
grep -R --include="*.py" "foo" .
find <dir> -name "*.txt" — ad ilə
find . -type f -mtime -7 — son 7 günün fayları
find . -size +100M
find . -user alice -group staff
find . -perm 755
find . -empty
find . -name "*.log" -exec rm {} \;
find . -name "*.py" | xargs grep "foo"
locate <file> — updatedb-lə index edilmiş sürətli search
which <cmd> — PATH-dan tap
whereis <cmd> — binary + man + source
type <cmd> — builtin/alias/function/command
apropos <keyword> — man page axtarışı

## Permissions / ownership

chmod +x <file> — icra icazəsi ver
chmod 755 <file> — rwxr-xr-x
chmod -R 644 <dir>
chmod u+x, g-w, o=r
chown user:group <file>
chown -R user:group <dir>
chgrp <group> <file>
umask 022 — default mask
sudo <cmd> — root-da işlət
sudo -u alice <cmd> — başqa userlə
sudo -i — root shell
su - alice — user-ə keç
id — cari user/group
whoami
groups [user]
passwd — parol dəyiş
setfacl / getfacl — ACL
chattr +i — immutable
lsattr

## Process / job

ps aux — bütün proseslər
ps -ef — alternative format
ps aux | grep <name>
ps --forest — tree view
pstree — tree
top — interactive real-time
htop — daha gözəl top
atop — advanced (IO/disk)
kill <pid> — SIGTERM
kill -9 <pid> — SIGKILL (məcburi)
kill -HUP <pid> — reload config (nginx)
killall <name>
pkill <pattern>
pgrep <pattern>
jobs — background job-lar
fg %1, bg %1
nohup <cmd> &
disown %1
<Ctrl+Z> — prosesı suspend
<Ctrl+C> — SIGINT
<Ctrl+D> — EOF
nice -n 10 <cmd> — priority ilə işə sal
renice 10 -p <pid>
time <cmd> — icra müddəti
timeout 30 <cmd>
watch -n 2 <cmd> — hər 2s işə sal
at, cron — schedule

## System / resources

uname -a — kernel info
uptime
hostname
df -h — disk istifadəsi
df -i — inode istifadəsi
du -sh <dir> — qovluq ölçüsü
du -sh * | sort -h
ncdu — interactive du
free -h — RAM istifadəsi
vmstat 1 — virtual memory stats
iostat — IO stats
sar — historical metrics (sysstat)
uptime — load average
w — kim login edib + nə edir
dmesg — kernel ring buffer
journalctl -xe — systemd logs
journalctl -u nginx -f
journalctl --since "1 hour ago"
systemctl status/start/stop/restart/reload <service>
systemctl enable/disable <service>
systemctl list-units --type=service
systemctl daemon-reload
service <name> start (legacy)

## Network

ip addr — network interface-lər
ip route
ip link set eth0 up/down
ifconfig (legacy)
ss -tulpn — açıq portlar (modern)
netstat -tulpn (legacy)
lsof -i :8080 — porta baxan proses
lsof -p <pid>
ping <host>
traceroute <host>
mtr <host> — ping+traceroute
dig <domain>
dig +short A <domain>
nslookup <domain>
host <domain>
whois <domain>
nc -l 8080 — netcat listen
nc -zv <host> 80 — port scan
curl -v <url> — verbose
curl -X POST -H "Content-Type: application/json" -d '{"k":"v"}' <url>
curl -o file.tar <url> — save
curl -H "Authorization: Bearer ..." <url>
curl -I <url> — HEAD only
curl -L <url> — follow redirect
curl --resolve host:443:1.2.3.4 <url>
wget <url>
wget -c <url> — resume
rsync -avz --progress <src> <dst>
rsync -avz -e ssh src user@host:dst
scp <file> user@host:<path>
scp -r <dir> user@host:<path>
sftp user@host
ssh user@host
ssh -i key.pem user@host
ssh -p 2222 user@host
ssh -L 8080:localhost:80 user@host — port forward
ssh -N -D 1080 user@host — SOCKS proxy
ssh-keygen -t ed25519 -C "email"
ssh-copy-id user@host
tcpdump -i any -n port 443
nmap -p 1-1000 <host>

## Text processing

grep — axtarış (yuxarıda)
sed 's/old/new/g' <file> — mətn əvəz et
sed -i 's/old/new/g' <file> — in-place
sed -n '10,20p' <file> — konkret sətirlər
awk '{print $1}' <file> — sütun çıxar
awk -F',' '{print $2}' — custom delimiter
awk '$3 > 100 {print $1}' — condition
awk 'NR==FNR{...}' — multi-file
sort <file> — sırala
sort -n — numeric
sort -r — reverse
sort -k 2 — 2-ci sütun
sort -u — unique
uniq <file> — bitişik dublikatları sil
uniq -c — count ilə
uniq -d — yalnız duplicate-lər
xargs — stdin-dən arqument kimi istifadə et
xargs -n 1, -I {}, -P 4 (parallel)
tee <file> — stdout-u həm fayla həm terminala
echo ... | tr -d '\n'
printf "%-20s %s\n" a b
fold -w 80 — wrap
column -t — aligned table
jq '.' file.json — JSON processor
jq '.items[] | .name' file.json
yq (YAML eqv)

## Archive / compression

tar -czf archive.tar.gz <dir> — gzip arxiv yarat
tar -xzf archive.tar.gz — aç
tar -cjf archive.tar.bz2 — bzip2
tar -cJf archive.tar.xz — xz
tar -tzf archive.tar.gz — içini görmək
tar -xzf archive.tar.gz -C /dst — konkret path-a
zip -r archive.zip <dir>
unzip archive.zip
gzip <file> / gunzip
bzip2 / bunzip2
xz / unxz
7z a archive.7z <dir>

## Environment / shell

export KEY=VALUE — mühit dəyişəni təyin et
unset KEY
echo $KEY
env — bütün env-lar
set — shell variables
printenv
alias ll='ls -la'
unalias
history
!! — son əmri təkrarla
!n — n-ci əmri təkrarla
!$ — son əmrin son arqumenti
Ctrl+R — reverse search
~/.bashrc, ~/.zshrc, ~/.profile
source <file> (və ya . <file>) — execute in current shell
man <cmd> — sənədləşmə
man -k <keyword>
info <cmd>
help <builtin> — bash builtin help
tldr <cmd> — quick examples (ekstra yüklənir)

## Package managers (distro-spesifik)

apt update && apt upgrade — Debian/Ubuntu
apt install <pkg>
apt remove <pkg>
apt search <term>
apt show <pkg>
dpkg -l / dpkg -i <deb>
dnf / yum (Fedora/RHEL)
pacman (Arch)
brew (macOS)
snap / flatpak

## Package build / dev tooling

make / cmake / ninja
gcc / g++ / clang
strace -p <pid> — syscall trace
ltrace
perf stat <cmd> — performance counters
perf record / perf report
ldd <binary> — shared lib-lər
objdump -d <binary>
nm <binary>
readelf
addr2line

## Filesystem / devices

mount / umount
lsblk — block device-lər
blkid — UUID-lər
fdisk -l / parted
mkfs.ext4 / mkfs.xfs
fsck
df -T — filesystem type
fstab (/etc/fstab)
losetup (loopback)
dd if=src of=dst bs=4M status=progress
sync — flush writes
hdparm, smartctl

## Pipes / redirection

| — pipe
> file — stdout redirect (overwrite)
>> file — append
< file — stdin
2> file — stderr
2>&1 — stderr → stdout
&> file — both
/dev/null
tee file
cmd1 && cmd2 — əvvəlki uğurludursa
cmd1 || cmd2 — uğursuzdursa
cmd1 ; cmd2 — sequential
$(cmd) — command substitution
`cmd` — köhnə syntax
$((1+2)) — arithmetic

## Cron / systemd timer

crontab -e — user cron
crontab -l
/etc/crontab — system cron
* * * * * cmd  (min hour day month weekday)
systemd timer (modern alternative)
