# Linux Disk və Yaddaş (Middle)

## Nədir? (What is it?)

Disk və storage idarəetməsi serverlərdə data-nın necə saxlanmasını, disklərin necə bölünməsini və filesystem-lərin necə qurulmasını əhatə edir. DevOps mühəndisi üçün disk dolması, performans problemləri və data itkisi kimi situasiyalarda bilmək vacibdir.

Laravel proyektlərində log faylları, upload-lar, database faylları - bunların hamısı disk ilə bağlıdır.

## Əsas Konseptlər (Key Concepts)

### Disk Məlumatları - df (Disk Free)

```bash
# Bütün mount edilmiş filesystem-ləri göstər
df -h                        # Human-readable format (GB, MB)
df -hT                       # Filesystem type ilə birlikdə
df -i                        # Inode istifadəsi göstər
df -h /var/www               # Konkret qovluğun filesystem-i
df --total                   # Ümumi cəmi göstər

# Nümunə output:
# Filesystem      Size  Used Avail Use% Mounted on
# /dev/sda1        50G   35G   13G  73% /
# /dev/sdb1       100G   60G   36G  63% /var/www
# tmpfs            2G   100M  1.9G   6% /tmp
```

### Qovluq Ölçüləri - du (Disk Usage)

```bash
# Qovluğun ölçüsü
du -sh /var/www/laravel              # Cəmi ölçü
du -sh /var/www/laravel/*            # Hər alt-qovluq ayrıca
du -sh /var/www/laravel/storage/*    # Storage qovluğu analizi
du -h --max-depth=1 /var/www         # Bir səviyyə dərinlik
du -h --max-depth=2 /home            # İki səviyyə dərinlik

# Ən böyük qovluqları tap
du -h /var/www | sort -rh | head -20

# Ən böyük faylları tap
find /var/log -type f -size +100M -exec ls -lh {} \;
find /var/www -name "*.log" -size +50M

# ncdu - interaktiv disk analizi
sudo apt install ncdu
ncdu /var/www                        # İnteraktiv olaraq gəz
```

### Disk Bölmələri - fdisk

```bash
# Diskləri göstər
lsblk                        # Block device-ları ağac şəklində göstər
lsblk -f                     # Filesystem info ilə
fdisk -l                     # Bütün diskləri göstər (root lazım)
blkid                        # Block device UUID-lərini göstər

# Yeni disk əlavə etmək (məsələn /dev/sdb)
sudo fdisk /dev/sdb
# n - new partition
# p - primary
# 1 - partition number
# Enter - default first sector
# Enter - default last sector (bütün disk)
# w - write and exit

# Partition yaratdıqdan sonra
sudo mkfs.ext4 /dev/sdb1     # ext4 filesystem yarat
sudo mkfs.xfs /dev/sdb1      # XFS filesystem yarat

# parted - GPT disklar üçün (2TB+ disklar)
sudo parted /dev/sdc
# mklabel gpt
# mkpart primary ext4 0% 100%
# quit
```

### Mount Əməliyyatları

```bash
# Disk mount etmək
sudo mkdir -p /mnt/data
sudo mount /dev/sdb1 /mnt/data
mount | grep sdb1             # Mount olunub?

# Davamlı mount - /etc/fstab
sudo nano /etc/fstab
# Əlavə et:
# /dev/sdb1  /mnt/data  ext4  defaults  0  2
# UUID=xxxx  /mnt/data  ext4  defaults  0  2   (UUID ilə daha yaxşı)

# UUID-ni tap
sudo blkid /dev/sdb1

# fstab-ı test et (reboot etmədən)
sudo mount -a

# Unmount
sudo umount /mnt/data
sudo umount -l /mnt/data      # Lazy unmount (istifadə olunursa)

# Mount options
sudo mount -o ro /dev/sdb1 /mnt/data          # Read-only
sudo mount -o noexec /dev/sdb1 /mnt/data       # Execute icazəsi yox
sudo mount -o nosuid /dev/sdb1 /mnt/data       # SUID bit-i ignore et
sudo mount -o remount,rw /mnt/data             # Remount read-write
```

### Filesystem Tiplər

```bash
# ext4 - Linux default, journaling, 1EB max size
# Ən çox istifadə olunan, stable, yaxşı performans
mkfs.ext4 /dev/sdb1

# XFS - Yüksək performans, böyük fayllar üçün
# Shrink etmək olmur, yalnız grow
mkfs.xfs /dev/sdb1

# Btrfs - Copy-on-write, snapshots, compression
# Modern Linux filesystem, subvolume support
mkfs.btrfs /dev/sdb1

# tmpfs - RAM-based filesystem
# Reboot-da silinir, çox sürətli
mount -t tmpfs -o size=512m tmpfs /mnt/ramdisk

# swap - Virtual memory
mkswap /dev/sdc1
swapon /dev/sdc1
```

### Inode

```bash
# Inode = faylın metadata-sı (permissions, owner, timestamps, data block pointers)
# Hər fayl bir inode-a sahibdir
# Inode number faylın unikal identifikatoru-dur

# Inode istifadəsini göstər
df -i
# Filesystem      Inodes  IUsed   IFree IUse% Mounted on
# /dev/sda1      3276800 250000 3026800    8% /

# Faylın inode-unu göstər
ls -i file.txt
stat file.txt

# Inode dolması problemi
# Disk boş ola bilər amma inode dolub - yeni fayl yarada bilməzsən!
# Səbəb: minlərcə kiçik fayl (session files, cache files)

# Həlli: lazımsız kiçik faylları sil
find /tmp -type f -mtime +7 -delete
find /var/www/laravel/storage/framework/sessions -type f -mtime +1 -delete
```

### Swap (Virtual Memory)

```bash
# Swap nədir?
# RAM dolduqda diskdə əlavə yaddaş kimi istifadə olunur
# RAM-dan yavaşdır, amma OOM (Out of Memory) killer-dən qoruyur

# Mövcud swap-ı göstər
swapon --show
free -h
cat /proc/swaps

# Swap file yaratmaq
sudo fallocate -l 2G /swapfile
sudo chmod 600 /swapfile
sudo mkswap /swapfile
sudo swapon /swapfile

# Davamlı etmək
echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab

# Swappiness - nə qədər tez swap istifadə olunacaq
cat /proc/sys/vm/swappiness            # Default 60
sudo sysctl vm.swappiness=10           # Daha az swap istifadə et

# Davamlı swappiness
echo 'vm.swappiness=10' | sudo tee -a /etc/sysctl.conf

# Server üçün tövsiyə:
# - Database server: swappiness=1 (demək olar ki swap istifadə etmə)
# - Web server: swappiness=10-20
# - Desktop: swappiness=60 (default)
```

### LVM (Logical Volume Manager)

```bash
# LVM nədir?
# Fiziki diskləri qruplaşdırıb, elastik olaraq bölmək imkanı verir
# Disk ölçüsünü runtime-da dəyişmək mümkün olur

# Terminologiya:
# PV (Physical Volume) - fiziki disk/partition
# VG (Volume Group) - PV-lərin qrupu
# LV (Logical Volume) - VG-dən ayrılan bölmə

# LVM Quraşdırma
sudo apt install lvm2

# 1) Physical Volume yaratmaq
sudo pvcreate /dev/sdb /dev/sdc
sudo pvdisplay                   # PV-ləri göstər
sudo pvs                        # Qısa format

# 2) Volume Group yaratmaq
sudo vgcreate data_vg /dev/sdb /dev/sdc
sudo vgdisplay                   # VG-ləri göstər
sudo vgs                        # Qısa format

# 3) Logical Volume yaratmaq
sudo lvcreate -L 50G -n www_lv data_vg       # 50GB www üçün
sudo lvcreate -L 30G -n db_lv data_vg        # 30GB database üçün
sudo lvcreate -l 100%FREE -n backup_lv data_vg  # Qalan yer backup üçün
sudo lvdisplay                   # LV-ləri göstər
sudo lvs                        # Qısa format

# 4) Filesystem yaratmaq və mount etmək
sudo mkfs.ext4 /dev/data_vg/www_lv
sudo mkfs.ext4 /dev/data_vg/db_lv
sudo mkdir -p /var/www /var/lib/mysql
sudo mount /dev/data_vg/www_lv /var/www
sudo mount /dev/data_vg/db_lv /var/lib/mysql

# LVM ölçü dəyişmək (əsas üstünlük!)
sudo lvextend -L +20G /dev/data_vg/www_lv    # 20GB əlavə et
sudo resize2fs /dev/data_vg/www_lv           # ext4 filesystem-i genişləndir
# və ya
sudo lvextend -L +20G -r /dev/data_vg/www_lv # -r avtomatik resize edir

# LVM Snapshot
sudo lvcreate -L 5G -s -n www_snap /dev/data_vg/www_lv
# Snapshot-dan restore
sudo lvconvert --merge /dev/data_vg/www_snap
```

### RAID Səviyyələri

```bash
# RAID = Redundant Array of Independent Disks
# Diskləri birləşdirərək performans və/və ya etibarlılıq artırır

# RAID 0 - Striping
# - Minimum 2 disk
# - Performans: 2x oxuma/yazma
# - Etibarlılıq: YOX! Bir disk çökdü = bütün data itirildi
# - Kapasite: Bütün disklərin cəmi (100+100 = 200GB)
# - İstifadə: Müvəqqəti data, build server cache

# RAID 1 - Mirroring
# - Minimum 2 disk
# - Performans: 2x oxuma, 1x yazma
# - Etibarlılıq: 1 disk çökə bilər
# - Kapasite: Ən kiçik diskin ölçüsü (100+100 = 100GB)
# - İstifadə: OS diski, kiçik database

# RAID 5 - Striping + Parity
# - Minimum 3 disk
# - Performans: Yaxşı oxuma, orta yazma
# - Etibarlılıq: 1 disk çökə bilər
# - Kapasite: (N-1) * disk_size (3x100GB = 200GB)
# - İstifadə: Ümumi təyinatlı serverlər

# RAID 6 - Striping + Double Parity
# - Minimum 4 disk
# - Performans: Yaxşı oxuma, yavaş yazma
# - Etibarlılıq: 2 disk çökə bilər
# - Kapasite: (N-2) * disk_size (4x100GB = 200GB)
# - İstifadə: Kritik data storage

# RAID 10 - Mirroring + Striping
# - Minimum 4 disk
# - Performans: Əla oxuma/yazma
# - Etibarlılıq: Hər mirror cütündən 1 disk çökə bilər
# - Kapasite: N/2 * disk_size (4x100GB = 200GB)
# - İstifadə: Database serverlər, yüksək performans

# mdadm ilə Software RAID yaratmaq
sudo apt install mdadm

# RAID 1 yaratmaq
sudo mdadm --create /dev/md0 --level=1 --raid-devices=2 /dev/sdb /dev/sdc
sudo mkfs.ext4 /dev/md0
sudo mount /dev/md0 /mnt/raid

# RAID statusu
cat /proc/mdstat
sudo mdadm --detail /dev/md0

# RAID 5 yaratmaq
sudo mdadm --create /dev/md1 --level=5 --raid-devices=3 /dev/sdd /dev/sde /dev/sdf

# RAID konfiqurasiyasını saxlamaq
sudo mdadm --detail --scan | sudo tee -a /etc/mdadm/mdadm.conf
```

## Praktiki Nümunələr (Practical Examples)

### Disk Dolması Problemini Həll Etmək

```bash
#!/bin/bash
# disk-cleanup.sh - Laravel server disk təmizliyi

# 1) Disk istifadəsini yoxla
echo "=== Disk Usage ==="
df -h /

# 2) Böyük qovluqları tap
echo "=== Top 10 Largest Directories ==="
du -h --max-depth=2 /var/www/laravel | sort -rh | head -10

# 3) Laravel təmizliyi
cd /var/www/laravel

# Köhnə log faylları
find storage/logs -name "*.log" -mtime +7 -delete
echo "Old logs deleted"

# Session faylları
find storage/framework/sessions -type f -mtime +1 -delete
echo "Old sessions deleted"

# Cache təmizliyi
php artisan cache:clear
php artisan view:clear
php artisan config:clear
echo "Laravel cache cleared"

# 4) System təmizliyi
sudo apt autoremove -y
sudo apt clean
sudo journalctl --vacuum-time=7d         # systemd journal-ı təmizlə
echo "System cleaned"

# 5) Nəticəni göstər
echo "=== Disk Usage After Cleanup ==="
df -h /
```

### Yeni Disk əlavə etmək (AWS EBS nümunəsi)

```bash
#!/bin/bash
# attach-disk.sh - AWS EBS volume mount etmək

DEVICE="/dev/xvdf"
MOUNT_POINT="/var/www/uploads"

# Disk var mı yoxla
if ! lsblk | grep -q "xvdf"; then
    echo "ERROR: Device $DEVICE not found"
    exit 1
fi

# Filesystem var mı yoxla
if ! blkid $DEVICE; then
    echo "Creating filesystem on $DEVICE..."
    sudo mkfs.ext4 $DEVICE
fi

# Mount point yarat
sudo mkdir -p $MOUNT_POINT

# Mount et
sudo mount $DEVICE $MOUNT_POINT

# fstab-a əlavə et
UUID=$(sudo blkid -s UUID -o value $DEVICE)
if ! grep -q "$UUID" /etc/fstab; then
    echo "UUID=$UUID $MOUNT_POINT ext4 defaults,nofail 0 2" | sudo tee -a /etc/fstab
fi

# Laravel üçün permission
sudo chown -R www-data:www-data $MOUNT_POINT
sudo chmod -R 775 $MOUNT_POINT

echo "Disk mounted at $MOUNT_POINT"
df -h $MOUNT_POINT
```

## PHP/Laravel ilə İstifadə

### Laravel Storage Disk Konfiqurasiyası

```php
// config/filesystems.php
'disks' => [
    'local' => [
        'driver' => 'local',
        'root' => storage_path('app'),    // /var/www/laravel/storage/app
    ],
    'public' => [
        'driver' => 'local',
        'root' => storage_path('app/public'),
        'url' => env('APP_URL').'/storage',
        'visibility' => 'public',
    ],
    'uploads' => [
        'driver' => 'local',
        'root' => '/mnt/data/uploads',    // Ayrı disk
    ],
    's3' => [
        'driver' => 's3',
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION'),
        'bucket' => env('AWS_BUCKET'),
    ],
],
```

### Disk Monitoring Artisan Command

```php
<?php
// app/Console/Commands/DiskHealthCheck.php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class DiskHealthCheck extends Command
{
    protected $signature = 'monitor:disk {--threshold=80}';
    protected $description = 'Check disk usage and alert if threshold exceeded';

    public function handle()
    {
        $threshold = $this->option('threshold');
        $output = shell_exec("df -h --output=source,pcent,target | grep -v tmpfs | tail -n +2");
        $lines = explode("\n", trim($output));

        foreach ($lines as $line) {
            preg_match('/(\S+)\s+(\d+)%\s+(\S+)/', $line, $matches);
            if (empty($matches)) continue;

            $device = $matches[1];
            $usage = (int) $matches[2];
            $mount = $matches[3];

            if ($usage >= $threshold) {
                $this->error("WARNING: {$mount} is {$usage}% full ({$device})");
                // Alert göndər
                logger()->critical("Disk usage alert", [
                    'device' => $device,
                    'usage' => $usage,
                    'mount' => $mount,
                ]);
            } else {
                $this->info("OK: {$mount} is {$usage}% ({$device})");
            }
        }
    }
}

// app/Console/Kernel.php - Hər saat yoxla
$schedule->command('monitor:disk --threshold=80')->hourly();
```

## Interview Sualları

### S1: df və du arasında fərq nədir?
**C:** `df` (disk free) filesystem səviyyəsində boş yeri göstərir - bütün mount olunmuş disklərin ümumi/istifadə olunan/boş yerini göstərir. `du` (disk usage) fayl və qovluq səviyyəsində istifadəni göstərir - konkret qovluğun nə qədər yer tutduğunu ölçür. df daha sürətli işləyir çünki filesystem metadata-sını oxuyur, du isə faylları sayır. Bəzən df və du fərqli nəticə göstərə bilər - bu silinmiş amma hələ açıq olan fayllar səbəbindən olur.

### S2: Inode nədir və niyə inode dolması problemi olur?
**C:** Inode faylın metadata-sını saxlayan data strukturudur - permissions, owner, timestamps, data block pointers. Hər fayl bir inode istifadə edir. Filesystem yaradılarkən sabit sayda inode ayrılır. Çox sayda kiçik fayl (session files, cache, tmp) inode-ları tükədə bilər - disk boş olsa da yeni fayl yaradıla bilmir. `df -i` ilə yoxlanır. Həlli: lazımsız kiçik faylları silmək, və ya filesystem-i daha çox inode ilə yenidən yaratmaq.

### S3: RAID 5 və RAID 10 arasında fərq nədir? Database üçün hansı daha yaxşıdır?
**C:** RAID 5 minimum 3 disk istifadə edir, 1 disk çökə bilər, kapasite (N-1)*disk. RAID 10 minimum 4 disk, hər mirror cütündən 1 disk çökə bilər, kapasite N/2*disk. Database üçün RAID 10 daha yaxşıdır çünki: 1) yazma performansı daha yaxşıdır (parity hesablaması yoxdur), 2) rebuild sürəti daha tezdir, 3) random I/O daha yaxşıdır. RAID 5 oxuma-ağırlıqlı və kapasite vacib olan hallarda uyğundur.

### S4: LVM-in üstünlükləri nədir?
**C:** LVM diskləri elastik idarə etmək imkanı verir: 1) Runtime-da volume ölçüsünü dəyişmək (extend/reduce), 2) Snapshot almaq - backup üçün faydalı, 3) Bir neçə fiziki diski birləşdirmək, 4) Thin provisioning - virtual olaraq böyük disk vermək amma faktiki yeri lazım olduqca ayırmaq, 5) Disk migrasyonu - məlumatı bir fiziki diskdən digərinə köçürmək, 6) Striping - performans artırmaq. Production serverlərində LVM istifadə etmək tövsiyə olunur.

### S5: Swap nə vaxt istifadə olunmalıdır? Nə qədər swap ayırmalıyıq?
**C:** Swap RAM dolduqda diskdə virtual memory kimi istifadə olunur. Tövsiyə: RAM 2GB-dən az isə 2x RAM, RAM 2-8GB isə 1x RAM, RAM 8GB+ isə 4-8GB sabit. Database serverlərində swappiness=1 olmalıdır çünki swap performansı çox azaldır. Hibernation lazımdırsa swap RAM-dan böyük olmalıdır. Container/Kubernetes mühitlərində swap əksər hallarda deaktiv edilir çünki OOM killer pod-ları restart etməli olur.

### S6: Serverdə disk dolur, necə araşdırıb həll edərsiniz?
**C:** Addım-addım: 1) `df -h` ilə hansı filesystem dolub bax, 2) `du -h --max-depth=2 /` ilə böyük qovluqları tap, 3) `find / -type f -size +100M` ilə böyük faylları tap, 4) `lsof | grep deleted` ilə silinmiş amma açıq faylları yoxla, 5) Log rotation konfiqurasiyasını yoxla, 6) `df -i` ilə inode istifadəsini yoxla. Təcili həll: köhnə logları sil, tmp təmizlə, apt cache təmizlə. Uzunmüddətli: log rotation qur, monitoring əlavə et, disk genişləndir.

### S7: ext4 və XFS arasında fərq nədir?
**C:** ext4 Linux-un default filesystem-dir, 1EB max, journaling var, shrink/grow mümkün. XFS yüksək performanslı filesystem-dir, paralel I/O-da güclüdür, yalnız grow mümkün (shrink yox), böyük fayllarla daha yaxşıdır. Database serverlər və böyük fayl storage üçün XFS daha yaxşıdır. Ümumi təyinatlı serverlər üçün ext4 yetərlidir. AWS EBS default olaraq ext4 istifadə edir.

## Best Practices

1. **Monitorinq qurun** - Disk istifadəsi 80%-ə çatanda alert göndərin
2. **Ayrı partitionlar istifadə edin** - /var/log, /var/www, /tmp ayrı olsun ki bir-birini təsir etməsin
3. **LVM istifadə edin** - Production serverlərində elastiklik üçün
4. **Log rotation konfiqurasiya edin** - logrotate ilə köhnə logları sıxışdırın/silin
5. **fstab-da UUID istifadə edin** - Device adları dəyişə bilər, UUID sabitdir
6. **nofail option əlavə edin** - fstab-da əlavə disklar üçün, disk olmasa da boot olsun
7. **noatime mount option** - Fayl oxuma vaxtını yazmağı söndür, performans artır
8. **Database üçün RAID 10** - Ən yaxşı performans və etibarlılıq
9. **Swap-ı düzgün konfiqurasiya edin** - Server tipinə görə swappiness ayarlayın
10. **Müntəzəm disk audit** - Ayda bir böyük faylları və lazımsız data-nı yoxlayın
