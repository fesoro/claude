# Infrastructure Patterns (Deployment Patterns)

## Nədir? (What is it?)

Infrastructure və deployment pattern-ləri – blue-green, canary, rolling update, feature flags, A/B testing, immutable infrastructure – production tətbiqlərinin dayanıqlı, təhlükəsiz və az risk ilə deploy edilməsini təmin edir. Modern DevOps praktikasında zero-downtime deployment, tez rollback və risk azaltma əsas tələblərdir. Laravel üçün Envoyer, Forge və Deployer populyar həllərdir.

## Əsas Konseptlər (Key Concepts)

### Blue-Green Deployment

```bash
# Blue-Green = iki identical environment
# Blue  - Hazırkı production
# Green - Yeni versiya (deploy olunur, test olunur)
# Switchover: Router/LB trafiği Blue-dən Green-ə yönəldir

# Üstünlükləri:
# - Zero downtime
# - Fast rollback (LB-ni Blue-ə qaytar)
# - Production-da tam test imkanı

# Mənfiləri:
# - İki dəfə resurs lazımdır (2x cost)
# - Database migration mürəkkəb
# - Stateful uygulamalarda çətin

# AWS-də Blue-Green (ALB Target Groups)
aws elbv2 create-target-group --name blue-tg ...
aws elbv2 create-target-group --name green-tg ...

# Green-ə yeni versiya deploy et
# Test et
# Weight 0-100 dəyiş
aws elbv2 modify-listener --listener-arn $LISTENER_ARN \
  --default-actions Type=forward,ForwardConfig='{
    "TargetGroups":[
      {"TargetGroupArn":"blue-tg-arn","Weight":0},
      {"TargetGroupArn":"green-tg-arn","Weight":100}
    ]
  }'

# Problem olsa, tez geri:
aws elbv2 modify-listener --listener-arn $LISTENER_ARN \
  --default-actions Type=forward,ForwardConfig='{
    "TargetGroups":[
      {"TargetGroupArn":"blue-tg-arn","Weight":100},
      {"TargetGroupArn":"green-tg-arn","Weight":0}
    ]
  }'
```

### Canary Deployment

```bash
# Canary = Trafiğin kiçik hissəsini yeni versiyaya yönəltmək
# 5% → 25% → 50% → 100% tədricən artırmaq
# Problem olanda tez geri qayıtmaq

# Canary adı "canary in a coal mine" ifadəsindən gəlir

# Üstünlükləri:
# - Real trafikdə test
# - Tədrici riski azaldır
# - Performance metric-lərini müqayisə et
# - Azaltılmış resurs (Blue-Green-dən)

# Mənfiləri:
# - Monitoring vacibdir
# - Database schema uyğunluğu lazımdır
# - Slow rollout

# Kubernetes-də canary (Flagger/Argo Rollouts ilə)
apiVersion: flagger.app/v1beta1
kind: Canary
metadata:
  name: laravel-app
spec:
  targetRef:
    apiVersion: apps/v1
    kind: Deployment
    name: laravel-app
  service:
    port: 80
  analysis:
    interval: 1m
    threshold: 5
    stepWeight: 10
    maxWeight: 50
    metrics:
      - name: request-success-rate
        thresholdRange:
          min: 99
        interval: 1m
      - name: request-duration
        thresholdRange:
          max: 500
        interval: 1m
```

### Rolling Update

```bash
# Rolling Update = eski instance-ları tədricən yeniləmək
# N instance var, birincisini öldür → yeni yarat → növbəti...
# maxSurge, maxUnavailable parametrləri ilə idarə olunur

# Kubernetes Rolling Update (default)
apiVersion: apps/v1
kind: Deployment
metadata:
  name: laravel-app
spec:
  replicas: 10
  strategy:
    type: RollingUpdate
    rollingUpdate:
      maxSurge: 25%         # Əlavə yaradıla bilən pod %
      maxUnavailable: 25%   # Unavailable ola bilən pod %
  selector:
    matchLabels:
      app: laravel
  template:
    metadata:
      labels:
        app: laravel
    spec:
      containers:
      - name: laravel
        image: laravel:2.0
        readinessProbe:
          httpGet:
            path: /health
            port: 80
          initialDelaySeconds: 10
          periodSeconds: 5

# Üstünlükləri:
# - Zero downtime
# - Tədrici rollout
# - Kubernetes native

# Mənfiləri:
# - Bir müddət iki versiya birlikdə işləyir
# - Schema backward compatible olmalıdır
```

### Feature Flags (Feature Toggles)

```php
// Feature flag = kodda if-else, deployment-dan ayrı
// Dark launch: feature deploy olur amma deaktivdir
// A/B testing, gradual rollout, kill switch

// Nümunə: Laravel Feature Flag (Laravel Pennant)
composer require laravel/pennant
php artisan pennant:install

// Flag təyini
use Laravel\Pennant\Feature;

Feature::define('new-checkout', fn (User $user) => 
    $user->isSubscribed() || Lottery::odds(1, 100)
);

// İstifadə
if (Feature::active('new-checkout')) {
    // Yeni checkout flow
} else {
    // Köhnə checkout
}

// Blade-də
@feature('new-checkout')
    <new-checkout-flow />
@else
    <old-checkout-flow />
@endfeature

// User-ə flag yazmaq
Feature::for($user)->activate('new-checkout');
Feature::for($user)->deactivate('new-checkout');
```

### A/B Testing

```php
// A/B Testing = iki versiyanı müqayisə et, metrikdən daha yaxşını seç

use Laravel\Pennant\Feature;

Feature::define('checkout-button-color', fn (User $user) => 
    Arr::random(['red', 'blue'])
);

// Controller-də
$variant = Feature::value('checkout-button-color');
return view('checkout', ['buttonColor' => $variant]);

// Metrika topla
if ($user->completedPurchase()) {
    analytics()->track('purchase_completed', [
        'variant' => $variant,
        'revenue' => $order->total,
    ]);
}

// Statistik əhəmiyyət üçün:
// Minimum sample size: 1000+ per variant
// Duration: Ən azı 1 həftə
// p-value < 0.05 significant
```

### Immutable Infrastructure

```bash
# Immutable Infrastructure = server-lər dəyişdirilmir, əvəzlənir
# Server-də patch, update yoxdur - yeni image yaradılır və köhnə əvəz olunur

# Ənənəvi (mutable):
# 1. SSH ilə serverə gir
# 2. apt update, composer install
# 3. config dəyişdir
# 4. restart

# Immutable:
# 1. Yeni AMI/image build et
# 2. Yeni instance yarat
# 3. LB-yə qoş
# 4. Köhnəni termin et

# Üstünlükləri:
# - Reproducible
# - Configuration drift yoxdur
# - Easy rollback
# - Infrastructure as Code uyğun

# Alətlər: Packer (AMI build), Terraform, AWS AutoScaling

# Packer nümunəsi
cat > laravel.pkr.hcl <<EOF
source "amazon-ebs" "laravel" {
  ami_name      = "laravel-{{timestamp}}"
  instance_type = "t3.medium"
  region        = "us-east-1"
  source_ami    = "ami-0c55b159cbfafe1f0"
  ssh_username  = "ubuntu"
}

build {
  sources = ["source.amazon-ebs.laravel"]

  provisioner "shell" {
    inline = [
      "sudo apt-get update",
      "sudo apt-get install -y php8.2 php8.2-fpm nginx",
      "sudo systemctl enable nginx php8.2-fpm",
    ]
  }

  provisioner "file" {
    source      = "./laravel/"
    destination = "/var/www/laravel"
  }
}
EOF

packer build laravel.pkr.hcl
```

### Deployment Strategies müqayisə

| Strategy | Downtime | Cost | Complexity | Rollback | Use Case |
|----------|----------|------|------------|----------|----------|
| Recreate | Var | 1x | Aşağı | Yavaş | Dev/staging |
| Rolling | Yoxdur | 1x | Orta | Orta | Ümumi |
| Blue-Green | Yoxdur | 2x | Yüksək | İnstant | Vacib release |
| Canary | Yoxdur | 1.1x | Yüksək | Tez | Yüksək risk |
| A/B | Yoxdur | 1.1x | Çox Yüksək | İnstant | Feature test |

## Praktiki Nümunələr (Practical Examples)

### Laravel zero-downtime deployment (Deployer)

```php
// deploy.php
namespace Deployer;

require 'recipe/laravel.php';

set('repository', 'git@github.com:company/laravel-app.git');
set('keep_releases', 5);
set('shared_files', ['.env']);
set('shared_dirs', ['storage']);
set('writable_dirs', ['bootstrap/cache', 'storage']);

host('production')
    ->hostname('app.example.com')
    ->user('deploy')
    ->identityFile('~/.ssh/id_rsa')
    ->set('deploy_path', '/var/www/laravel')
    ->set('branch', 'main');

task('restart:php-fpm', function () {
    run('sudo systemctl reload php8.2-fpm');
});

task('restart:queue', function () {
    run('{{bin/php}} {{release_path}}/artisan queue:restart');
});

after('deploy:symlink', 'restart:php-fpm');
after('deploy:symlink', 'restart:queue');
after('deploy:failed', 'deploy:unlock');

// Rollback task
task('rollback:custom', function () {
    invoke('deploy:rollback');
    invoke('restart:php-fpm');
});
```

```bash
# Deploy
dep deploy production
# Rollback
dep deploy:rollback production
```

### GitHub Actions Blue-Green workflow

```yaml
name: Blue-Green Deploy

on:
  push:
    branches: [main]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      
      - name: Determine inactive environment
        id: env
        run: |
          ACTIVE=$(aws elbv2 describe-listeners --listener-arns $LISTENER_ARN \
            --query 'Listeners[0].DefaultActions[0].TargetGroupArn' --output text)
          if [[ "$ACTIVE" == *"blue"* ]]; then
            echo "target=green" >> $GITHUB_OUTPUT
          else
            echo "target=blue" >> $GITHUB_OUTPUT
          fi
      
      - name: Deploy to inactive
        run: |
          aws ecs update-service --cluster laravel \
            --service laravel-${{ steps.env.outputs.target }} \
            --force-new-deployment
          
          aws ecs wait services-stable --cluster laravel \
            --services laravel-${{ steps.env.outputs.target }}
      
      - name: Smoke test
        run: |
          curl -f https://${{ steps.env.outputs.target }}.example.com/health
      
      - name: Switch traffic
        run: |
          aws elbv2 modify-listener --listener-arn $LISTENER_ARN \
            --default-actions Type=forward,TargetGroupArn=${{ steps.env.outputs.target }}-tg-arn
```

## PHP/Laravel ilə İstifadə

### Laravel Envoyer

```bash
# Envoyer = Laravel üçün zero-downtime deployment servisi
# Features:
# - Git-based deploy
# - Atomic deployment (symlink switch)
# - Rollback (previous release-ə qayıtmaq)
# - Chat notifications
# - Scheduled deployments
# - Heartbeats (scheduled task monitoring)

# Envoyer structure:
# /home/deploy/site.com/
#   ├── current -> releases/20240415120000 (symlink)
#   ├── releases/
#   │   ├── 20240415120000/
#   │   ├── 20240414150000/
#   │   └── ...
#   └── storage/ (shared)

# Deployment prosesi:
# 1. Git clone → /releases/TIMESTAMP
# 2. composer install
# 3. npm ci && npm run build
# 4. php artisan optimize
# 5. Symlink shared: .env, storage
# 6. Atomic switch: current → new release
# 7. PHP-FPM reload
# 8. Old releases cleanup (keep last 5)
```

### Laravel Forge

```bash
# Forge = Laravel-in server management platforması
# Features:
# - Server provisioning (AWS, DigitalOcean, Linode)
# - Nginx, PHP, MySQL, Redis auto setup
# - SSL (Let's Encrypt)
# - Scheduled jobs
# - Queue workers
# - Deploy hooks

# Deploy script (Forge-də konfiqurasiya)
cd /home/forge/app.example.com
git pull origin main
composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
( flock -w 10 9 || exit 1
    echo 'Restarting FPM...'; sudo -S service php8.2-fpm reload ) 9>/tmp/fpmlock
```

### Blue-Green Laravel with database migrations

```php
// Backward-compatible migration (expand-contract pattern)

// Phase 1: Expand (deploy A)
// Köhnə və yeni kod eyni zamanda işləyə bilər
class AddEmailVerifiedToUsers extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            // Yeni sütun əlavə et (nullable, default yaxşı)
            $table->timestamp('email_verified_at')->nullable();
        });
    }
}

// Phase 2: Migrate data (deploy B)
// Data backfill
User::whereNotNull('email_verified')
    ->update(['email_verified_at' => DB::raw('email_verified')]);

// Phase 3: Contract (deploy C)
// Köhnə sütunu sil (yeni kod yalnız yeni sütunu istifadə edir)
Schema::table('users', function (Blueprint $table) {
    $table->dropColumn('email_verified');
});
```

### Laravel Feature Flag Service

```php
// app/Services/FeatureFlag.php
namespace App\Services;

use Illuminate\Support\Facades\Cache;

class FeatureFlag
{
    public function isEnabled(string $feature, ?User $user = null): bool
    {
        $config = Cache::remember("feature.{$feature}", 60, function () use ($feature) {
            return DB::table('feature_flags')->where('name', $feature)->first();
        });
        
        if (!$config || !$config->enabled) {
            return false;
        }
        
        // Percentage rollout
        if ($config->percentage < 100 && $user) {
            $hash = crc32($user->id . ':' . $feature) % 100;
            return $hash < $config->percentage;
        }
        
        // User allowlist
        if ($user && $config->allowed_users) {
            return in_array($user->id, json_decode($config->allowed_users, true));
        }
        
        return true;
    }
}

// İstifadə
if (app(FeatureFlag::class)->isEnabled('new-api', auth()->user())) {
    return $this->newApiResponse();
}
return $this->oldApiResponse();
```

## Interview Sualları (5-10 Q&A)

**S1: Blue-Green və Canary deployment fərqi nədir?**
C: Blue-Green – iki tam environment, trafik bir anda tam switch olur (0% → 100%). İnstant rollback, amma 2x resurs tələb edir. Canary – trafiğin bir hissəsi (5%) yeni versiyaya, tədricən artırılır (5% → 25% → 100%). Daha az resurs, amma uzun müddət iki versiya işləyir, monitoring vacibdir.

**S2: Rolling update zamanı database schema necə idarə edilir?**
C: Expand-Contract pattern istifadə olunur: (1) Expand – yeni sütun əlavə, köhnə və yeni kod uyğun; (2) Migrate – data köçürülür; (3) Contract – köhnə sütun silinir. Backward/forward compatible migration-lar lazımdır. Drop column kimi destructive dəyişikliklər yalnız bütün kod yeni versiyaya keçəndən sonra edilir.

**S3: Feature flag-ın üstünlükləri nədir?**
C: Dark launch (kod deploy amma deaktiv), gradual rollout (5% → 100% istifadəçi), kill switch (problem çıxsa tez deaktiv), A/B testing, deploy və release ayrılır. Mənfiləri: kod mürəkkəbliyi artır, texniki borc toplanır, cleanup lazımdır. Vacib: istifadə olunmayan flag-ları silmək.

**S4: Zero-downtime deployment necə əldə olunur?**
C: Zero-downtime üçün lazım: (1) Load balancer arxasında birdən çox instance; (2) Health check ilə traffic switching; (3) Graceful shutdown (instance termine ediləndə mövcud request-lər tamamlanır); (4) Database backward compatible migration-lar; (5) Cache invalidation düzgün; (6) Session storage external (Redis). Laravel-də `php artisan down` istifadə edilməməlidir.

**S5: Immutable infrastructure nədir və niyə yaxşıdır?**
C: Server-lər dəyişdirilmir, əvəzlənir. Configuration drift yoxdur (müxtəlif server-lərdə fərqli config problem yaratmaz). Reproducible – Packer/Terraform ilə eyni image. Easy rollback – əvvəlki AMI-yə qayıt. Mənfiləri: daha çox build vaxtı, storage xərci. Alətlər: Packer, AWS AMI, Docker image-lər.

**S6: Canary deployment-da hansı metriklər izlənir?**
C: Əsas SLI-lər: error rate (4xx, 5xx), latency (p50, p95, p99), throughput (RPS), CPU/memory, business metric-lər (conversion rate). Yeni versiyanın metrikləri mövcud ilə müqayisə olunur. Prometheus + Grafana, Datadog, CloudWatch istifadə olunur. Threshold aşıldıqda avtomatik rollback (Flagger, Argo Rollouts).

**S7: Laravel zero-downtime deployment-də nə kimi problemlər var?**
C: (1) Config cache – yeni config əvvəl `php artisan config:clear` lazımdır; (2) Queue workers – yeni kodu almaq üçün `php artisan queue:restart`; (3) Database migration – backward compatible olmalıdır; (4) Opcache – PHP-FPM reload lazımdır; (5) Scheduled tasks – symlink switch zamanı həmin anda başlaya bilər; (6) Horizon/Octane – graceful restart.

**S8: A/B test nə zaman statistik olaraq əhəmiyyətli sayılır?**
C: p-value < 0.05 (95% güvən), minimum sample size (adətən 1000+ per variant), test müddəti ən azı 1 tam biznes tsikli (həftə). Confounding variable-ləri nəzərdən keçirin (həftəsonu effekti). Statistik testlər: t-test, chi-square. Tools: Optimizely, LaunchDarkly, custom Laravel Pennant.

**S9: Deployment-da rollback strategy necə olmalıdır?**
C: Rollback tez, avtomatik və təhlükəsiz olmalıdır. Blue-Green: LB trafiğini dərhal köhnə environment-ə qaytar. Rolling: `kubectl rollout undo`. Database: Backup + schema backward compatible. CI/CD pipeline-da rollback button olmalıdır. Feature flag ilə code-level rollback mümkündür. Test edin – "runbook"-da rollback prosedurunu yazın.

**S10: Envoyer və Deployer arasında fərq nədir?**
C: Envoyer – Laravel komandasının managed servisi, GUI, $10/ay, auto-setup, chat integration, heartbeats. Deployer – open-source, self-hosted, CLI, customize edilə bilən PHP file (deploy.php). Envoyer – kiçik komanda üçün tez setup, Deployer – tam kontrol, ödənişsiz, CI/CD inteqrasiya. Hər ikisi atomic deployment (symlink switch).

## Best Practices

1. **Zero-downtime default**: Hər deployment zero-downtime olmalıdır (load balancer, health check).
2. **Health checks**: `/health` endpoint-i yaradın, DB və cache yoxlasın, LB onu istifadə etsin.
3. **Graceful shutdown**: SIGTERM alanda mövcud request-ləri tamamlayın (PHP-FPM timeout).
4. **Database migrations**: Backward compatible yazın, expand-contract pattern istifadə edin.
5. **Feature flags**: Risk-li feature-ləri flag arxasında saxlayın, kill switch olsun.
6. **Automated rollback**: Error rate artsa avtomatik rollback (Flagger, CloudWatch alarm).
7. **Canary monitoring**: SLI-lər Prometheus-a gedib Grafana-da izlənsin.
8. **Smoke tests**: Hər deploy-dan sonra kritik endpoint-lər yoxlanılsın.
9. **Deployment frequency**: Kiçik, tez-tez deploy edin (böyük release-lərdən qaçın).
10. **Atomic deployment**: Symlink switching (Envoyer, Deployer) yarımçıq state-dən qoruyur.
11. **Shared resources**: .env və storage qovluğunu shared folder olaraq saxlayın.
12. **Keep releases**: Son 5 release-i saxlayın, rollback üçün.
13. **Deployment notifications**: Slack/Teams-ə deploy status göndərin.
14. **Post-deployment verification**: Automated test + manual smoke test.
15. **Runbook**: Incident response və rollback prosedurlarını dokumentləyin.
