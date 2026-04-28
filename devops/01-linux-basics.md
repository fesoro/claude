# Linux Əsasları (Junior)

## Nədir? (What is it?)

Linux açıq mənbəli (open-source) əməliyyat sistemidir. Serverlərin 90%+ hissəsi Linux ilə işləyir. DevOps mühəndisi üçün Linux bilmək vacibdir - serverlər, container-lər, CI/CD runner-lar hamısı Linux-dur.

Əsas distributivlər: Ubuntu/Debian (apt), CentOS/RHEL/Fedora (yum/dnf), Alpine (apk), Arch (pacman).

## Əsas Konseptlər (Key Concepts)

### File System Hierarchy (FHS)

```
/                   # Root - ən yuxarı qovluq
├── bin/            # Əsas əmrlər (ls, cp, mv, cat)
├── sbin/           # System əmrləri (fdisk, iptables, reboot)
├── etc/            # Konfiqurasiya faylları
│   ├── nginx/      # Nginx config
│   ├── php/        # PHP config
│   ├── mysql/      # MySQL config
│   ├── hosts       # Host adları
│   ├── fstab       # Disk mount-lar
│   ├── passwd      # User info
│   ├── shadow      # Encrypted passwords
│   └── crontab     # System cron
├── home/           # User home qovluqları
│   └── orkhan/     # orkhan user-in home-u
├── root/           # root user-in home-u
├── var/            # Variable data
│   ├── log/        # Log faylları
│   ├── www/        # Web server faylları
│   ├── lib/        # Application state data
│   └── tmp/        # Temporary (reboot-dan sonra silinmir)
├── tmp/            # Temporary (reboot-dan sonra silinir)
├── usr/            # User programs
│   ├── bin/        # User əmrləri
│   ├── lib/        # Libraries
│   ├── local/      # Locally installed software
│   └── share/      # Shared data
├── opt/            # Optional/third-party software
├── dev/            # Device faylları
├── proc/           # Process information (virtual)
├── sys/            # System information (virtual)
├── boot/           # Boot loader faylları
├── lib/            # Shared libraries
├── mnt/            # Temporary mount point
└── media/          # Removable media
```

### File Operations

```bash
# Fayl yaratmaq
touch file.txt                      # Boş fayl yarat
echo "hello" > file.txt             # Yazıb yarat (overwrite)
echo "world" >> file.txt            # Əlavə et (append)
cat > file.txt <<EOF                # Heredoc ilə yarat
line 1
line 2
EOF

# Fayl oxumaq
cat file.txt                        # Bütün faylı göstər
less file.txt                       # Səhifə-səhifə göstər (q - çıx)
head -n 20 file.txt                 # İlk 20 sətir
tail -n 20 file.txt                 # Son 20 sətir
tail -f /var/log/syslog             # Real-time izlə

# Fayl kopyalamaq/taşımaq/silmək
cp file.txt backup.txt              # Kopyala
cp -r folder/ backup/               # Qovluq kopyala (recursive)
mv file.txt newname.txt             # Adını dəyiş / taşı
mv file.txt /tmp/                   # Başqa yerə taşı
rm file.txt                         # Sil
rm -rf folder/                      # Qovluq sil (force, recursive)
mkdir -p path/to/dir                # Qovluq yarat (nested)
rmdir empty-folder/                 # Boş qovluq sil

# Axtarış
find /var/log -name "*.log" -mtime -7    # Son 7 gündə dəyişən log-lar
find / -type f -size +100M               # 100MB-dan böyük fayllar
find . -name "*.php" -exec grep -l "TODO" {} \;  # TODO olan PHP faylları
locate file.txt                           # Sürətli axtarış (updatedb lazım)
which php                                 # Əmrin yerini tap
whereis nginx                             # Binary, man, source yeri

# Fayl info
file document.pdf                   # Fayl tipi
stat file.txt                       # Detallı info (size, permissions, dates)
wc -l file.txt                      # Sətir sayı
du -sh folder/                      # Qovluq ölçüsü
```

### Permissions (İcazələr)

Linux-da hər faylın 3 qrup icazəsi var: Owner, Group, Others.
Hər qrup üçün 3 icazə: Read (r=4), Write (w=2), Execute (x=1).

```
-rwxr-xr-- 1 orkhan developers 4096 Jan 15 10:30 script.sh
│├─┤├─┤├─┤   │       │
│ │  │  │    owner   group
│ │  │  └── Others: r-- (read only = 4)
│ │  └───── Group:  r-x (read + execute = 5)
│ └──────── Owner:  rwx (read + write + execute = 7)
└────────── Type: - (file), d (directory), l (symlink)
```

```bash
# chmod - İcazə dəyişmək
chmod 755 script.sh                 # rwxr-xr-x
chmod 644 config.php                # rw-r--r--
chmod 600 .env                      # rw------- (yalnız owner)
chmod +x script.sh                  # Execute əlavə et
chmod -w file.txt                   # Write icazəsini sil
chmod u+x,g+r,o-rwx file.txt       # Symbolic notation

# Ümumi permission patterns:
# 755 - Executable fayllar, qovluqlar
# 644 - Normal fayllar (config, source code)
# 600 - Sensitive fayllar (.env, SSH keys)
# 700 - Private qovluqlar
# 775 - Shared qovluqlar (group write)
# 666 - Hər kəs yaza bilər (storage/logs kimi)

# chown - Owner dəyişmək
chown orkhan file.txt               # Owner dəyiş
chown orkhan:developers file.txt    # Owner və group dəyiş
chown -R www-data:www-data /var/www # Recursive (qovluq + içi)

# chgrp - Group dəyişmək
chgrp developers file.txt

# Laravel üçün tipik permissions
sudo chown -R www-data:www-data /var/www/laravel
sudo chmod -R 755 /var/www/laravel
sudo chmod -R 775 /var/www/laravel/storage
sudo chmod -R 775 /var/www/laravel/bootstrap/cache
```

### Special Permissions

```bash
# SUID (Set User ID) - 4
chmod 4755 /usr/bin/passwd          # Fayl həmişə owner kimi işləyir
# SGID (Set Group ID) - 2
chmod 2775 /var/www/shared/         # Yeni fayllar parent group-u alır
# Sticky Bit - 1
chmod 1777 /tmp                     # Yalnız owner silə bilər

# Nümunə: ls -la /tmp
# drwxrwxrwt  - "t" sticky bit deməkdir
```

### Users və Groups

```bash
# User əməliyyatları
sudo useradd -m -s /bin/bash orkhan      # User yarat
sudo useradd -r -s /usr/sbin/nologin www-data  # System user (login yox)
sudo passwd orkhan                        # Password təyin et
sudo usermod -aG sudo orkhan             # sudo qrupuna əlavə et
sudo usermod -aG www-data orkhan         # www-data qrupuna əlavə et
sudo userdel -r orkhan                   # User sil (home ilə birlikdə)
whoami                                    # Cari user
id orkhan                                 # User info (uid, gid, groups)
groups orkhan                             # User-in qrupları
su - orkhan                               # User dəyiş
sudo -u www-data php artisan migrate      # Başqa user kimi əmr işlət

# Group əməliyyatları
sudo groupadd developers                 # Group yarat
sudo groupdel developers                 # Group sil
sudo gpasswd -a orkhan developers        # User-i qrupa əlavə et
sudo gpasswd -d orkhan developers        # User-i qrupdan sil
getent group developers                  # Group üzvləri

# /etc/passwd formatı:
# username:x:uid:gid:comment:home:shell
# orkhan:x:1000:1000:Orkhan:/home/orkhan:/bin/bash

# /etc/shadow - encrypted passwords
# orkhan:$6$salt$hash:19234:0:99999:7:::
```

### Symlinks (Symbolic Links)

```bash
# Soft link (symlink) - shortcut kimi
ln -s /var/www/laravel/current /var/www/html
ln -s /etc/nginx/sites-available/app.conf /etc/nginx/sites-enabled/

# Hard link - eyni inode-u paylaşır
ln original.txt hardlink.txt

# Fərqlər:
# Symlink: original silinərsə link qırılır, fərqli partition-da ola bilər
# Hardlink: original silinərsə data qalır, eyni partition olmalıdır

# Laravel deployment symlinks
ln -nfs /var/www/releases/20240115 /var/www/current  # Atomic switch
# -n: symlink-i dereference etmə
# -f: force (mövcud olsa əvəz et)
# -s: symbolic link
```

### Pipes və Redirection

```bash
# Pipe (|) - bir əmrin output-unu digərinə ver
cat /var/log/syslog | grep "error" | wc -l
ps aux | grep php | grep -v grep
ls -la | sort -k 5 -n -r | head -10     # Ən böyük fayllar

# Redirection
command > file.txt          # stdout -> file (overwrite)
command >> file.txt         # stdout -> file (append)
command 2> error.txt        # stderr -> file
command 2>&1                # stderr -> stdout
command > output.txt 2>&1   # Hər ikisi -> file
command &> output.txt       # Hər ikisi -> file (shorthand)
command < input.txt         # File -> stdin
command <<< "string"        # String -> stdin

# /dev/null - "qara dəlik", output-u silmək üçün
command > /dev/null 2>&1    # Bütün output-u sil
ping -c 1 google.com > /dev/null && echo "Online" || echo "Offline"

# tee - həm ekrana, həm fayla yaz
command | tee output.txt           # Ekran + fayl
command | tee -a output.txt        # Ekran + fayl (append)
```

### Text Processing

```bash
# grep - Pattern axtarışı
grep "error" /var/log/syslog              # "error" olan sətirlər
grep -i "error" file.txt                  # Case insensitive
grep -r "TODO" /var/www/app/              # Recursive axtarış
grep -n "function" file.php               # Sətir nömrəsi ilə
grep -c "error" file.log                  # Sayı göstər
grep -v "debug" file.log                  # "debug" OLMAYAN sətirlər
grep -E "error|warning|critical" file.log # Extended regex (OR)
grep -A 3 -B 1 "Exception" file.log      # 3 sonra, 1 əvvəl

# sed - Stream editor
sed 's/old/new/' file.txt                 # İlk match-ı dəyiş
sed 's/old/new/g' file.txt               # Bütün match-ları dəyiş
sed -i 's/old/new/g' file.txt            # In-place dəyiş
sed '5d' file.txt                         # 5-ci sətiri sil
sed '/pattern/d' file.txt                # Pattern olan sətirləri sil
sed -n '10,20p' file.txt                 # 10-20-ci sətirləri göstər

# awk - Text processing
awk '{print $1}' file.txt                # 1-ci sütun
awk -F: '{print $1, $3}' /etc/passwd     # Delimiter ilə
awk '$3 > 1000' /etc/passwd              # 3-cü sütun > 1000
awk '{sum += $1} END {print sum}' file   # Cəm hesabla
ps aux | awk '{print $1, $11}'           # User və command

# sort, uniq, cut
sort file.txt                             # Sırala
sort -n file.txt                          # Rəqəmsal sırala
sort -r file.txt                          # Əksinə sırala
sort -u file.txt                          # Unique sırala
uniq file.txt                             # Ardışıl dublikatları sil
sort file.txt | uniq -c | sort -rn        # Frequency count
cut -d: -f1 /etc/passwd                  # 1-ci field (delimiter :)
```

## Praktiki Nümunələr (Practical Examples)

### Server İlkin Quraşdırma

```bash
#!/bin/bash
# initial-setup.sh - Yeni server üçün ilkin quraşdırma

# Update system
sudo apt update && sudo apt upgrade -y

# Create deploy user
sudo useradd -m -s /bin/bash deploy
sudo usermod -aG sudo deploy
sudo mkdir -p /home/deploy/.ssh
sudo cp ~/.ssh/authorized_keys /home/deploy/.ssh/
sudo chown -R deploy:deploy /home/deploy/.ssh
sudo chmod 700 /home/deploy/.ssh
sudo chmod 600 /home/deploy/.ssh/authorized_keys

# Set timezone
sudo timedatectl set-timezone UTC

# Install essentials
sudo apt install -y curl wget git unzip htop vim ufw

# Setup firewall
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow 22/tcp     # SSH
sudo ufw allow 80/tcp     # HTTP
sudo ufw allow 443/tcp    # HTTPS
sudo ufw --force enable

# Disable root login
sudo sed -i 's/PermitRootLogin yes/PermitRootLogin no/' /etc/ssh/sshd_config
sudo sed -i 's/#PasswordAuthentication yes/PasswordAuthentication no/' /etc/ssh/sshd_config
sudo systemctl restart sshd

echo "Initial setup complete!"
```

### Laravel Server Permissions Setup

```bash
#!/bin/bash
# fix-permissions.sh

WEB_USER="www-data"
WEB_GROUP="www-data"
LARAVEL_PATH="/var/www/laravel"

# Set ownership
sudo chown -R $WEB_USER:$WEB_GROUP $LARAVEL_PATH

# Set directory permissions
sudo find $LARAVEL_PATH -type d -exec chmod 755 {} \;

# Set file permissions
sudo find $LARAVEL_PATH -type f -exec chmod 644 {} \;

# Writable directories
sudo chmod -R 775 $LARAVEL_PATH/storage
sudo chmod -R 775 $LARAVEL_PATH/bootstrap/cache

# Artisan must be executable
sudo chmod +x $LARAVEL_PATH/artisan

echo "Permissions fixed!"
```

## PHP/Laravel ilə İstifadə

### Laravel Log Fayllarının İzlənməsi

```bash
# Laravel log izlə
tail -f /var/www/laravel/storage/logs/laravel.log

# Error-ları filtr et
tail -f /var/www/laravel/storage/logs/laravel.log | grep -i "error\|exception"

# Bugünkü log
cat /var/www/laravel/storage/logs/laravel-$(date +%Y-%m-%d).log

# Son 100 error
grep -i "error\|exception" /var/www/laravel/storage/logs/laravel.log | tail -100

# Log ölçüsü
du -sh /var/www/laravel/storage/logs/

# Köhnə log-ları sil
find /var/www/laravel/storage/logs -name "*.log" -mtime +30 -delete
```

### Environment Files

```bash
# .env faylı - sensitive, 600 permission
chmod 600 /var/www/laravel/.env
chown www-data:www-data /var/www/laravel/.env

# .env faylını oxumaq (debug)
cat /var/www/laravel/.env | grep -v "^#" | grep -v "^$"

# .env-dən bir dəyər almaq
grep "^DB_HOST" /var/www/laravel/.env | cut -d= -f2
```

## Interview Sualları

### Q1: Hard link və soft link fərqi nədir?
**Cavab:** Soft link (symlink) başqa fayla pointer-dir, original silinərsə link qırılır, fərqli partition-larda ola bilər, öz inode-u var. Hard link eyni inode-u paylaşır, original silinərsə data qalır, yalnız eyni partition-da ola bilər, directory-lər üçün istifadə oluna bilməz.

### Q2: chmod 755, 644, 600 nə deməkdir?
**Cavab:** 755 = rwxr-xr-x (owner full, group+others read+execute) - directories və executable fayllar üçün. 644 = rw-r--r-- (owner read+write, others read) - normal fayllar üçün. 600 = rw------- (yalnız owner read+write) - sensitive fayllar üçün (.env, SSH keys).

### Q3: Linux-da fayl permission-ları necə işləyir?
**Cavab:** Hər faylın owner, group və others üçün read (4), write (2), execute (1) icazələri var. chmod ilə dəyişdirilir (numeric və ya symbolic). chown ilə owner, chgrp ilə group dəyişdirilir. Directory-lər üçün execute = cd ilə daxil olmaq deməkdir.

### Q4: /proc və /sys nədir?
**Cavab:** Virtual filesystem-lərdir, disk-də yer tutmur. /proc proses və kernel məlumatları saxlayır (/proc/cpuinfo, /proc/meminfo, /proc/PID/). /sys hardware və driver məlumatları saxlayır. Runtime-da kernel tərəfindən yaradılır.

### Q5: stdin, stdout, stderr nədir?
**Cavab:** Üç standart I/O stream-dir. stdin (0) - input (klaviatura), stdout (1) - normal output, stderr (2) - error output. Redirection ilə idarə olunur: `command > file` stdout-u, `2> file` stderr-i, `2>&1` stderr-i stdout-a yönləndirir.

### Q6: Pipe (|) necə işləyir?
**Cavab:** Pipe bir əmrin stdout-unu digər əmrin stdin-inə yönləndirir. Məsələn `cat file | grep error | wc -l` - faylı oxu, "error" olan sətirləri filtr et, sayını göstər. Hər əmr ayrı prosesdə işləyir və data stream kimi axır.

## Best Practices

1. **Least privilege** - Minimum lazımi icazəni verin, 777 istifadə etməyin
2. **Use groups** - User-ləri group-larla idarə edin, fərdi permission verməyin
3. **Sensitive files** - .env, SSH keys üçün 600 istifadə edin
4. **www-data user** - Web server faylları www-data:www-data olmalıdır
5. **No root login** - SSH ilə root login disable edin
6. **SSH keys** - Password authentication disable edin, key-based istifadə edin
7. **Regular updates** - `apt update && apt upgrade` mütəmadi işlədin
8. **Firewall** - UFW/iptables ilə yalnız lazımi portları açın
9. **Log rotation** - logrotate ilə log fayllarını idarə edin
10. **Backup** - Critical faylları mütəmadi backup edin

---

## Praktik Tapşırıqlar

1. Yeni server qurduqdan sonra ilk 10 əmri yazın: sistem məlumatını öyrənin (`uname -a`, `lsb_release -a`), disk yoxlayın (`df -h`), RAM yoxlayın (`free -h`), CPU yoxlayın (`nproc`), network interface-ləri görün (`ip a`)
2. Laravel layihəsi üçün düzgün fayl icazələrini qurun: `storage/` və `bootstrap/cache/` — `775`, owner `www-data:www-data`, `.env` — `640`; sonra `stat` əmri ilə yoxlayın
3. `find` əmri ilə aşağıdakıları tapın: 7 gündən köhnə log fayllar, ölçüsü 100MB-dan böyük fayllar, `777` icazəsi olan fayllar (security risk); hər biri üçün əmr yazın
4. `journalctl` ilə PHP-FPM və Nginx loglarını real-time izləyin: son 100 sətir, `ERROR` keyword-ü filter edin, son 1 saatın log-larını çəkin
5. Yeni Linux user yaradın, `www-data` group-a əlavə edin, password-suz sudo icazəsi verin, SSH key əlavə edin, sonra bütün dəyişiklikləri yoxlayın
6. Bash script yazın: hər 5 dəqiqədə disk, RAM, CPU istifadəsini yoxlasın; hər biri 80%-dən çox olsa `/var/log/system-alert.log`-a timestamp ilə yazıb email göndərsin (mail əmri)

## Əlaqəli Mövzular

- [Linux Proses İdarəetmə](07-linux-process-management.md) — systemd, cron, PHP-FPM proses idarəsi
- [Linux Şəbəkə](08-linux-networking.md) — firewall, DNS, server setup
- [Linux Disk & Yaddaş](09-linux-disk-storage.md) — LVM, RAID, filesystem
- [Shell Scripting](10-linux-shell-scripting.md) — bash, deployment scripts
- [Performance Tuning](30-performance-tuning.md) — Linux kernel tuning, sysctl parametrləri
