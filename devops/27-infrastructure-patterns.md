# Immutable Infrastructure & PHP Deployment Tools (Senior)

## Nədir? (What is it?)

Immutable infrastructure – serverlər deploy-dan sonra **dəyişdirilmir**, əvəzlənir. Ənənəvi "mutable" yanaşmada SSH ilə serverə girib patch, update, config dəyişikliyi edir, server zamanla fərdi "snowflake" halına gəlir. Immutable yanaşmada isə hər dəyişiklik yeni image-in build edilməsi, test olunması və serverin əvəzlənməsi deməkdir. Bu pattern **configuration drift** (server-lər bir-birindən fərqlənir), **"works on my machine"** problemini aradan qaldırır. PHP deployment toolları isə (Deployer, Envoyer) symlink-based atomic deploy vasitəsilə zero-downtime release-i mümkün edir.

## Əsas Konseptlər (Key Concepts)

### Mutable vs Immutable Infrastructure

```
MUTABLE (Snowflake Server):
  SSH → apt update → composer install → config dəyiş → restart
  
  Problemlər:
  ✗ Configuration drift: hər server zamanla fərqlənir
  ✗ "Ssh-dan kim nəyi dəyişdi?" — audit yoxdur
  ✗ Rollback: "köhnə vəziyyətə qayıt" demək sıfırdan build deməkdir
  ✗ Reproducibility: "production server-i eyni qaydada yenidən qurmaq"
     çox çətin olur

IMMUTABLE:
  Dəyişiklik → yeni image build → test → deploy → köhnəni termin et
  
  Üstünlüklər:
  ✓ Reproducible: hər deployment eyni image-dən gəlir
  ✓ Audit log: Git history = infrastructure history
  ✓ Rollback instant: köhnə image-dən yeni instance yarat
  ✓ Configuration drift yoxdur: hər instance eyni baseline-dən başlayır
  ✓ Security: "clean" image, no stale packages, no forgotten configs
  
  Tələblər:
  → Stateless application (session DB-də, fayllar S3-də)
  → External config (env vars, Secrets Manager)
  → Fast image build pipeline
  → Infrastructure-as-Code (Terraform)
```

### Snowflake Server Anti-Pattern

```bash
# Klassik "snowflake" problemi:

# Server A-da bu komandalar işlənib:
sudo apt install php8.1
sudo apt install libpng-dev   # kiminsə qurduğu library
echo "memory_limit=256M" >> /etc/php/8.1/php.ini  # kiminsə əl ilə dəyişdiyi

# Server B-də:
sudo apt install php8.2       # fərqli versiya!
# libpng-dev yoxdur
# memory_limit default qalıb (128M)

# Server C 6 ay əvvəl qurulub, fərqli OpenSSL versiyası var...

# Nəticə: "production-da işləmir, staging-də işləyirdi" klassik problemi

# Həll: Configuration management (Ansible) + Immutable infrastructure (Packer)
```

### Packer ilə AMI Build

```hcl
# laravel-app.pkr.hcl
# HashiCorp Packer ilə Laravel server AMI yaratmaq

packer {
  required_plugins {
    amazon = {
      version = ">= 1.2.0"
      source  = "github.com/hashicorp/amazon"
    }
  }
}

# Mənbə: Ubuntu 22.04 base image
source "amazon-ebs" "laravel" {
  ami_name      = "laravel-php82-${formatdate("YYYY-MM-DD-hhmm", timestamp())}"
  instance_type = "t3.medium"
  region        = "eu-west-1"
  
  source_ami_filter {
    filters = {
      name                = "ubuntu/images/hvm-ssd/ubuntu-jammy-22.04-amd64-server-*"
      root-device-type    = "ebs"
      virtualization-type = "hvm"
    }
    most_recent = true
    owners      = ["099720109477"]  # Canonical
  }

  ssh_username = "ubuntu"
  
  tags = {
    Name        = "laravel-php82"
    Environment = "production"
    BuildTime   = formatdate("YYYY-MM-DD'T'hh:mm:ss'Z'", timestamp())
    GitCommit   = env("GIT_SHA")
  }
}

build {
  name    = "laravel-server"
  sources = ["source.amazon-ebs.laravel"]

  # OS-level paketlər
  provisioner "shell" {
    inline = [
      "sudo apt-get update",
      "sudo apt-get upgrade -y",
      "sudo apt-get install -y php8.2 php8.2-fpm php8.2-mysql php8.2-redis php8.2-gd php8.2-intl php8.2-mbstring php8.2-xml php8.2-zip",
      "sudo apt-get install -y nginx git unzip",
      "sudo curl -sS https://getcomposer.org/installer | sudo php -- --install-dir=/usr/local/bin --filename=composer",
      "sudo apt-get clean",
    ]
  }

  # Application kod deploy
  provisioner "file" {
    source      = "./dist/laravel-${env("APP_VERSION")}.tar.gz"
    destination = "/tmp/app.tar.gz"
  }

  # App qurulumu
  provisioner "shell" {
    inline = [
      "sudo mkdir -p /var/www/laravel",
      "sudo tar -xzf /tmp/app.tar.gz -C /var/www/laravel",
      "cd /var/www/laravel && sudo composer install --no-dev --optimize-autoloader",
      "sudo php artisan config:cache",
      "sudo php artisan route:cache",
      "sudo chown -R www-data:www-data /var/www/laravel",
    ]
  }

  # Nginx konfiqurasiyanı kopyala
  provisioner "file" {
    source      = "./nginx/laravel.conf"
    destination = "/etc/nginx/sites-available/laravel.conf"
  }

  provisioner "shell" {
    inline = [
      "sudo ln -sf /etc/nginx/sites-available/laravel.conf /etc/nginx/sites-enabled/",
      "sudo nginx -t",
      "sudo systemctl enable nginx php8.2-fpm",
    ]
  }
}
```

### Packer + Terraform Workflow

```bash
# CI/CD pipeline-da immutable deploy workflow:

# 1. Build stage: artifact hazırla
composer install --no-dev
npm run build
tar -czf dist/laravel-${GIT_SHA}.tar.gz -C . .

# 2. Packer: AMI build et
export GIT_SHA=$(git rev-parse --short HEAD)
export APP_VERSION=${GIT_SHA}
packer build laravel-app.pkr.hcl
# → AMI ID: ami-0abc123def456789

# 3. Packer output-dan AMI ID-ni al
AMI_ID=$(cat packer-manifest.json | jq -r '.builds[-1].artifact_id' | cut -d: -f2)

# 4. Terraform: launch configuration update et
terraform apply -var="ami_id=${AMI_ID}" -var="app_version=${GIT_SHA}"
# → Auto Scaling Group yeni AMI ilə rolling update başladır

# 5. Rolling update: köhnə instance-lar replace olunur
# Old: ami-old × 10 instances → New: ami-0abc123 × 10 instances
# maxUnavailable: 2 → hər anda max 2 instance yenilənir

# Terraform konfiqurasiya:
resource "aws_launch_template" "laravel" {
  name_prefix   = "laravel-"
  image_id      = var.ami_id
  instance_type = "t3.medium"
  
  tag_specifications {
    resource_type = "instance"
    tags = {
      AppVersion = var.app_version
    }
  }
}

resource "aws_autoscaling_group" "laravel" {
  launch_template {
    id      = aws_launch_template.laravel.id
    version = "$Latest"
  }
  
  min_size         = 2
  max_size         = 10
  desired_capacity = 4
  
  instance_refresh {
    strategy = "Rolling"
    preferences {
      min_healthy_percentage = 75
    }
  }
}
```

### PHP Deployment Tools: Deployer

```php
// Deployer — PHP-də yazılmış zero-downtime deployment tool
// composer require deployer/deployer --dev
// deploy.php

namespace Deployer;

require 'recipe/laravel.php';

// Layihə konfiqurasiyası
set('application', 'laravel-app');
set('repository', 'git@github.com:company/laravel-app.git');
set('git_tty', true);
set('keep_releases', 5);           // Son 5 release saxla
set('writable_mode', 'chmod');

// Shared fayllar/qovluqlar (release-lər arasında paylaşılır)
set('shared_files', ['.env']);
set('shared_dirs', ['storage']);

// Yazıla bilən qovluqlar
set('writable_dirs', [
    'bootstrap/cache',
    'storage',
    'storage/app',
    'storage/framework',
    'storage/logs',
]);

// Server konfiqurasiyası
host('production')
    ->hostname('app.example.com')
    ->user('deploy')
    ->identityFile('~/.ssh/id_rsa')
    ->set('deploy_path', '/var/www/laravel')
    ->set('branch', 'main');

host('staging')
    ->hostname('staging.example.com')
    ->user('deploy')
    ->identityFile('~/.ssh/id_rsa')
    ->set('deploy_path', '/var/www/staging')
    ->set('branch', 'develop');

// Custom task-lar
task('deploy:migrate', function () {
    run('{{bin/php}} {{release_path}}/artisan migrate --force');
});

task('restart:php-fpm', function () {
    run('sudo systemctl reload php8.2-fpm');
});

task('deploy:horizon-restart', function () {
    run('{{bin/php}} {{release_path}}/artisan horizon:terminate');
});

task('deploy:pulse-restart', function () {
    run('{{bin/php}} {{release_path}}/artisan pulse:restart');
});

// Deploy sırası
after('deploy:symlink', 'restart:php-fpm');
after('deploy:symlink', 'deploy:horizon-restart');
after('deploy:failed', 'deploy:unlock');

// Deploy əmri: dep deploy production
// Rollback: dep rollback production
```

### Atomic Deployment (Symlink-based)

```
Deployer-in direktori strukturu:

/var/www/laravel/
├── current -> releases/20240115120000/   ← symlink (aktiv release)
├── releases/
│   ├── 20240113100000/  ← v1.0.0 (saxlanılır)
│   ├── 20240114150000/  ← v1.0.1 (saxlanılır)
│   └── 20240115120000/  ← v1.0.2 (cari)
└── shared/
    ├── .env             ← bütün release-lər paylaşır
    └── storage/         ← bütün release-lər paylaşır

Deploy process:
  1. releases/timestamp/ qovluğunu yarat
  2. Git clone → bu qovluğa
  3. composer install
  4. php artisan migrate --force
  5. php artisan config:cache
  6. ln -nfs releases/new current  ← ATOMIC step!
     (Bu əməliyyat qisa müddət alır, sistem dayandırılmır)
  7. php-fpm reload
  8. Köhnə releases-ları sil (5-dən çox olanları)

Rollback:
  ln -nfs releases/old current
  php-fpm reload
  → 2 saniyə ilə geri qayıt!
```

### Envoyer (Laravel üçün hosted CI/CD)

```
Envoyer = Laravel üçün hazır zero-downtime deployment platforması
          (Deployer-in hosted/managed versiyası)

Üstünlükləri:
  ✓ GUI konfiqurasiya — deploy.php yazmaq lazım deyil
  ✓ Health check — deploy uğurludursa, köhnəni termin et
  ✓ Heartbeat monitoring — site uptime yoxlaması
  ✓ GitHub/GitLab webhook integration
  ✓ Deployment hooks (before/after migrate, etc.)
  ✓ Notification (Slack, email)
  ✓ Server management — SSH key, PHP version

Konfiqurasiya addımları:
  1. envoyer.io-da layihə yarat
  2. Server-i əlavə et (SSH key ilə)
  3. Repository bağla
  4. Environment-i konfiqurasiya et
  5. Deployment hooks əlavə et:
     - Before Activate: php artisan migrate --force
     - After Activate: php artisan queue:restart

Webhook (GitHub Actions ilə):
  - deploy: curl https://envoyer.io/deploy/xxxxx
```

### Deployment Tools Müqayisəsi

| Tool | Növ | Dil | Əsas Xüsusiyyət | PHP/Laravel |
|------|-----|-----|-----------------|-------------|
| **Deployer** | Open source CLI | PHP | Atomic symlink, rollback, recipes | ✅ Laravel recipe |
| **Envoyer** | SaaS | Web UI | GUI, health checks, monitoring | ✅ Laravel-specific |
| **Capistrano** | Open source CLI | Ruby | Ən köhnə, Ruby ecosystem | ⚠️ plugin lazım |
| **GitHub Actions** | CI/CD platform | YAML | Tam pipeline, Git integration | ✅ flexible |
| **AWS CodeDeploy** | AWS managed | AppSpec | EC2/ECS, blue-green native | ✅ flexible |
| **Ansible** | IaC/CM | YAML | Server provisioning + deploy | ✅ playbook |

## Praktiki Nümunələr (Practical Examples)

### GitHub Actions + Deployer Pipeline

```yaml
# .github/workflows/deploy.yml
name: Deploy to Production

on:
  push:
    branches: [main]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'

      - name: Install dependencies
        run: composer install --no-dev --optimize-autoloader

      - name: Build assets
        run: |
          npm ci
          npm run build

      - name: Setup SSH
        uses: webfactory/ssh-agent@v0.8.0
        with:
          ssh-private-key: ${{ secrets.DEPLOY_KEY }}

      - name: Add known host
        run: ssh-keyscan -H ${{ secrets.DEPLOY_HOST }} >> ~/.ssh/known_hosts

      - name: Deploy with Deployer
        run: |
          curl -LO https://deployer.org/deployer.phar
          php deployer.phar deploy production --revision=${{ github.sha }}
        env:
          DEPLOY_HOST: ${{ secrets.DEPLOY_HOST }}
          DEPLOY_USER: ${{ secrets.DEPLOY_USER }}

      - name: Notify Slack on failure
        if: failure()
        uses: 8398a7/action-slack@v3
        with:
          status: failure
          fields: repo,message,commit,author
        env:
          SLACK_WEBHOOK_URL: ${{ secrets.SLACK_WEBHOOK }}
```

### Health Check + Deployment Verification

```bash
#!/bin/bash
# post-deploy-verify.sh
# Deploy sonrası avtomatik yoxlama

HEALTH_URL="https://app.example.com/health"
MAX_ATTEMPTS=10
SLEEP=3

echo "Deployment verification started..."

for i in $(seq 1 $MAX_ATTEMPTS); do
    HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$HEALTH_URL")
    
    if [ "$HTTP_CODE" = "200" ]; then
        echo "✅ Health check passed (attempt $i)"
        
        # Əlavə yoxlama: key features
        CHECKS=$(curl -s "$HEALTH_URL" | jq '{
            db: .checks.database,
            cache: .checks.cache,
            queue: .checks.queue
        }')
        
        echo "Checks: $CHECKS"
        
        DB_OK=$(echo $CHECKS | jq -r '.db')
        if [ "$DB_OK" = "true" ]; then
            echo "✅ All systems operational"
            exit 0
        else
            echo "❌ Database check failed"
            exit 1
        fi
    else
        echo "⏳ Attempt $i failed (HTTP $HTTP_CODE), waiting ${SLEEP}s..."
        sleep $SLEEP
    fi
done

echo "❌ Health check failed after $MAX_ATTEMPTS attempts"
# Rollback trigger
dep rollback production
exit 1
```

## PHP/Laravel ilə İstifadə

### Laravel Artisan + Deployer Hooks

```php
// Deployer recipe-ə Laravel artisan hook-lar
namespace Deployer;

// Pre-deploy: maintenance mode
task('artisan:down', function () {
    run('{{bin/php}} {{release_path}}/artisan down --retry=60 --secret="bypass-token"');
});

// Post-deploy: cache warm-up
task('artisan:optimize', function () {
    run('{{bin/php}} {{release_path}}/artisan optimize');
    run('{{bin/php}} {{release_path}}/artisan event:cache');
});

// Zero-downtime üçün maintenance mode yoxdur,
// sadəcə symlink switch edirik

// Migrate ilə xəta olsa deploy fail edir:
task('artisan:migrate', artisan('migrate --force'));
task('artisan:migrate:rollback', artisan('migrate:rollback --force'));

// Deploy sırası (zero-downtime):
before('deploy:symlink', 'artisan:migrate');
after('deploy:symlink', 'artisan:optimize');
after('deploy:symlink', 'restart:php-fpm');
after('deploy:failed', 'artisan:migrate:rollback');  // Migration rollback
```

### Deployer ilə Multi-Server Deploy

```php
// Birdən çox server-ə paralel deploy
namespace Deployer;

host('web-1')
    ->hostname('10.0.1.10')
    ->user('deploy')
    ->set('deploy_path', '/var/www/laravel');

host('web-2')
    ->hostname('10.0.1.11')
    ->user('deploy')
    ->set('deploy_path', '/var/www/laravel');

host('web-3')
    ->hostname('10.0.1.12')
    ->user('deploy')
    ->set('deploy_path', '/var/www/laravel');

// Migration yalnız bir server-də çalışsın
host('web-1')->set('run_migrations', true);
host('web-2')->set('run_migrations', false);
host('web-3')->set('run_migrations', false);

task('conditional:migrate', function () {
    if (get('run_migrations')) {
        run('{{bin/php}} {{release_path}}/artisan migrate --force');
    }
});

// dep deploy --parallel  // Bütün server-lər paralel
// dep deploy web-1       // Yalnız web-1
```

## Interview Sualları (Q&A)

**S1: Mutable vs Immutable infrastructure fərqi nədir?**
C: Mutable-da mövcud server-ə SSH ilə girib dəyişiklik edirsən – server zamanla "snowflake" olur, fərdi konfiqurasiya drift yaranır. Immutable-da hər dəyişiklik yeni image (AMI, Docker image) yaradır, server əvəz olunur. Üstünlükləri: reproducibility, configuration drift yoxdur, rollback instant (köhnə image-dən instance yarat), audit log (Git-də).

**S2: Deployer nəyi edir, necə işləyir?**
C: Deployer PHP-nin deployment alətidir. SSH vasitəsilə serverə qoşulur, `releases/timestamp/` qovluğunda yeni deploy yaradır, composer install, artisan komandaları işlədir, sonra `current` symlink-i yeni release-ə keçirir (atomic step). Bu sayədə zero-downtime olur. Rollback üçün `current` symlink köhnə release-ə geri yönləndirilir.

**S3: Atomic symlink deployment nədir?**
C: Symlink-in `ln -nfs` əmri ilə dəyişdirilməsi prosesin atomik bir əməliyyatdır – bir nöqtədə symlink köhnəni göstərir, növbəti nöqtədə yenini. Nginx/PHP-FPM köhnə symlink üzərindən request-ləri xidmət edərkən symlink dəyişir, növbəti request yeni versiyaya gedir. Downtime sıfıra yaxındır.

**S4: Envoyer vs Deployer – nə vaxt hansını seçərdin?**
C: Deployer – open source, özün hosting, CI/CD pipeline-a tam inteqrasiya, çevik, Laravel recipe mövcuddur. Envoyer – hosted SaaS, GUI, health monitoring daxildir, monitoring built-in, kiçik/orta komandalar üçün rahat. Böyük team, özəl deploy workflow, CI/CD tamamilə öz əlinizdədir – Deployer. Sürətli qoşulma, GUI idarəetmə, Laravel-specific feature lazımdır – Envoyer.

**S5: Packer ilə AMI build workflow-u izah et.**
C: Packer konfiqurasiya faylı (HCL/JSON) base image-dən (Ubuntu AMI) başlayır, shell provisioner-lar ilə PHP, Nginx, app install edir, fayl provisioner-larla config kopyalayır. Build bitdikdə yeni AMI yaranır, Terraform bu AMI-ı Launch Template-də istifadə edir, Auto Scaling Group rolling update ilə köhnə instance-ları əvəz edir.

**S6: Configuration drift nədir? Necə qarşısı alınır?**
C: Configuration drift – bir neçə server zamanla bir-birindən fərqlənməsidir: kimisi manual patch vurub, kimisi digər package qurub. Bu "works on server-A, fails on server-B" probleminə gətirir. Həll: (1) Ansible/Chef ilə idempotent configuration management – hər server eyni state-ə gətirilir; (2) Immutable infrastructure – server-lar heç dəyişdirilmir, əvəzlənir; (3) Docker – container hər yerdə eyni şəkildə işləyir.

## Best Practices

1. **Deployment aləti seç** – Deployer (open source, CI inteqrasiya) və ya Envoyer (managed, GUI).
2. **Atomic deploy** – symlink switch; deployment zamanı downtime qısa olur.
3. **Health check** – deploy sonrası `/health` endpoint yoxla, fail-da rollback trigger et.
4. **Release saxla** – Son 5-10 release saxla, tez rollback imkanı.
5. **Shared dizinlər** – `storage/`, `.env` shared olsun, release-lər arasında paylaşılsın.
6. **Migration-ı deploy ilə sinxronlaşdır** – migration deploy əvvəl çalışsın (backward-compatible olmalı).
7. **Queue restart** – deploy sonrası `queue:restart` (workers yeni kodu götürsün).
8. **Parallel deploy** – Birdən çox server-ə `--parallel` flag ilə.
9. **CI/CD inteqrasiya** – Manual deploy əvəzinə pipeline ilə avtomatlaşdır.
10. **Packer + Terraform** – Immutable pipeline üçün; AMI bake et, Terraform ilə rolling replace.
11. **Rollback drill** – Mütəmadi rollback test et (game day-lərdə).
12. **Artisan hooks** – Deploy əsnasında `down`, migrate, `optimize`, `queue:restart` düzgün sıra ilə.

## Praktik Tapşırıqlar

1. Deployer install et (`composer require deployer/deployer --dev`), sadə `deploy.php` yaz: bir server, shared .env, storage, migration hook əlavə et
2. Mövcud deployment script-inizi Deployer recipe-ə çevirin: git pull → Deployer atomic deploy
3. `post-deploy-verify.sh` skript yaz: health check, DB status, queue check, fail-da Deployer rollback trigger
4. Envoyer-ı staging mühiti üçün qurun (pulsuz plan): hook-lar, Slack notification, heartbeat monitor
5. Packer HCL faylı yaz: Ubuntu base + PHP 8.2 + Nginx + Composer; lokal VM-də (VirtualBox) sınayın
6. GitHub Actions workflow-una Deployer inteqrasiya edin: push-to-main → deploy

## Əlaqəli Mövzular

- [Deployment Strategies](44-deployment-strategies.md) — Blue-green, canary, rolling, A/B strategiyalar
- [Zero-Downtime Deployment](41-zero-downtime-deployment.md) — DB migration koordinasiyası
- [CI/CD Deployment](39-cicd-deployment.md) — Pipeline dizaynı
- [GitHub Actions](04-github-actions.md) — CI/CD automation
- [GitOps](35-gitops.md) — Git-driven deployment
- [Terraform Advanced](24-terraform-advanced.md) — AMI + ASG ile immutable workflow
- [Ansible](25-ansible.md) — Configuration management
