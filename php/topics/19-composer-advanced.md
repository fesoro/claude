# Composer Advanced (Middle)

## Mündəricat
1. [Dependency Resolution](#dependency-resolution)
2. [Autoloader Optimizasiya](#autoloader-optimizasiya)
3. [Private Packages](#private-packages)
4. [PHP İmplementasiyası](#php-implementasiyası)
5. [İntervyu Sualları](#intervyu-sualları)

---

## Dependency Resolution

```
Composer SAT solver istifadə edir:
  version constraints → uyğun versiya tapır

Constraint sintaksisi:
  ^1.2.3  → >=1.2.3 <2.0.0  (semver compatible)
  ~1.2.3  → >=1.2.3 <1.3.0  (patch updates only)
  1.2.*   → >=1.2.0 <1.3.0
  >=1.0   → minimum versiyan
  1.0|2.0 → ya 1.0 ya 2.0

composer.lock:
  Tam versiyaları, hash-ləri saxlayır
  Reproduceble builds üçün vacib
  Git-ə commit edilməlidir (app-lar üçün)
  Library-lər üçün commit edilmir (sadəcə composer.json)

update vs install:
  composer install:  composer.lock-dan yüklə
  composer update:   constraint-lara uyğun ən yeni versiya

Conflict resolution:
  İki paket eyni paketi fərqli versiyada tələb edir:
  package-a: "guzzle": "^6.0"
  package-b: "guzzle": "^7.0"
  → Conflict! Composer xəta verir
  Həll: require-dev-də bir tərəfi pin et
```

---

## Autoloader Optimizasiya

```
Autoloader növləri:
  PSR-4: namespace → directory mapping (ən çox)
  PSR-0: köhnə, deprecated
  classmap: tam class → file map
  files: hər zaman yüklənən fayllar (helpers)

Optimizasiya səviyyələri:

Level 1 (--optimize-autoloader / -o):
  PSR-4 → classmap-ə çevrilir
  Filesystem scan yoxdur — daha sürətli
  
Level 2 (--classmap-authoritative / -a):
  Yalnız classmap istifadə edilir
  Mövcud olmayan class-lar üçün fallback yoxdur
  Production üçün ən sürətli

Level 2b (--apcu-autoloader):
  APCu cache-ə classmap saxlanılır
  Multi-request-da paylaşılır

Production əmri:
  composer install --no-dev --optimize-autoloader --classmap-authoritative

Benchmark:
  Standard PSR-4:    ~2ms per class load
  --optimize:        ~0.5ms per class load
  --apcu:            ~0.1ms per class load
```

---

## Private Packages

```
Private Composer Repository seçimləri:

Satis (open source):
  Statik Composer repository
  Öz serverinizdə
  Manuel build lazımdır

Private Packagist:
  Packagist-in ödənişli versiyası
  Tam Composer API
  Webhook ilə auto-update

Nexus Repository / Artifactory:
  Enterprise artifact management
  Composer proxy + private packages

VCS (Version Control Source):
  composer.json-da birbaşa Git URL
  {
    "repositories": [{
      "type": "vcs",
      "url":  "git@github.com:company/private-repo.git"
    }]
  }
  SSH key ilə auth

Path repository (monorepo):
  {
    "repositories": [{
      "type": "path",
      "url":  "../shared-kernel"
    }]
  }
  Local package-lar üçün
```

---

## PHP İmplementasiyası

```php
<?php
// 1. Custom Composer Plugin
namespace MyCompany\ComposerPlugin;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Script\ScriptEvents;

class SecurityCheckPlugin implements PluginInterface, EventSubscriberInterface
{
    private Composer    $composer;
    private IOInterface $io;

    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->composer = $composer;
        $this->io       = $io;
    }

    public function deactivate(Composer $composer, IOInterface $io): void {}
    public function uninstall(Composer $composer, IOInterface $io): void {}

    public static function getSubscribedEvents(): array
    {
        return [
            ScriptEvents::POST_INSTALL_CMD => 'checkVulnerabilities',
            ScriptEvents::POST_UPDATE_CMD  => 'checkVulnerabilities',
        ];
    }

    public function checkVulnerabilities(): void
    {
        $this->io->write('<info>Vulnerability check running...</info>');
        // Symfony Security Checker API-ya sor
        // Vulnerabilities varsa warning/error
    }
}
```

```json
// composer.json — production optimizasiya
{
  "name": "company/app",
  "type": "project",
  "require": {
    "php": "^8.3",
    "symfony/framework-bundle": "^7.0"
  },
  "require-dev": {
    "phpunit/phpunit": "^11.0"
  },
  "autoload": {
    "psr-4": {
      "App\\": "src/"
    },
    "files": [
      "src/helpers.php"
    ]
  },
  "autoload-dev": {
    "psr-4": {
      "Tests\\": "tests/"
    }
  },
  "scripts": {
    "post-install-cmd": [
      "@php artisan key:generate --ansi"
    ],
    "post-update-cmd": [
      "@php artisan vendor:publish --force --tag=laravel-assets"
    ],
    "test": "phpunit --testdox",
    "analyse": "phpstan analyse src --level=8"
  },
  "config": {
    "optimize-autoloader": true,
    "preferred-install": "dist",
    "sort-packages": true
  },
  "extra": {
    "laravel": {
      "providers": [
        "App\\Providers\\AppServiceProvider"
      ]
    }
  }
}
```

```bash
# Production deployment
composer install \
  --no-dev \
  --no-interaction \
  --optimize-autoloader \
  --classmap-authoritative \
  --prefer-dist

# Monorepo local packages
# packages/shared-kernel/composer.json:
# { "name": "company/shared-kernel", ... }

# Root composer.json:
# {
#   "repositories": [
#     { "type": "path", "url": "./packages/*" }
#   ],
#   "require": {
#     "company/shared-kernel": "@dev"
#   }
# }
```

```php
<?php
// 2. Composer scripts — development automation
// "scripts": {
//   "setup": [
//     "@composer install",
//     "@php bin/console doctrine:migrations:migrate --no-interaction",
//     "@php bin/console cache:warmup"
//   ],
//   "check": [
//     "@phpstan",
//     "@test",
//     "@cs-check"
//   ],
//   "phpstan": "vendor/bin/phpstan analyse src tests --level=8",
//   "test":    "vendor/bin/phpunit",
//   "cs-fix":  "vendor/bin/php-cs-fixer fix src tests"
// }

// CLI:
// composer setup
// composer check
```

---

## İntervyu Sualları

- `^` ilə `~` constraint fərqi nədir?
- `composer.lock` niyə git-ə commit edilir?
- `composer install` ilə `composer update` fərqi nədir?
- `--optimize-autoloader` nə edir? Production-da fərqi nədir?
- Private Composer package necə host edilir?
- `--classmap-authoritative` nə zaman problematik ola bilər?
