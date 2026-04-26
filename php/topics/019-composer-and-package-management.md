# Composer və Package Management (Junior)

## Mündəricat
1. [Composer nədir?](#composer-nədir)
2. [Dependency Resolution Algorithm](#dependency-resolution-algorithm)
3. [composer.json vs composer.lock](#composerjson-vs-composerlock)
4. [Semantic Versioning](#semantic-versioning)
5. [require vs require-dev](#require-vs-require-dev)
6. [Autoloading](#autoloading)
7. [Scripts](#scripts)
8. [Repositories](#repositories)
9. [Private Packages](#private-packages)
10. [Əsas Komandalar](#əsas-komandalar)
11. [Platform Requirements](#platform-requirements)
12. [Conflict, Replace, Provide](#conflict-replace-provide)
13. [Custom Laravel Package Yaratma](#custom-laravel-package-yaratma)
14. [Monorepo vs Separate Packages](#monorepo-vs-separate-packages)
15. [Dependency Hell](#dependency-hell)
16. [İntervyu Sualları](#intervyu-sualları)

---

## Composer nədir?

Composer PHP üçün dependency management alətidir. Node.js-dəki npm və ya Python-dakı pip-ə bənzər şəkildə işləyir. Layihənizin asılı olduğu kitabxanaları (dependencies) avtomatik olaraq yükləyir, versiyalarını idarə edir və autoloading konfiqurasiyasını təmin edir.

Composer **project-level** dependency manager-dir — sistem səviyyəsində deyil, hər layihə öz `vendor/` qovluğuna malik olur.

*Composer **project-level** dependency manager-dir — sistem səviyyəsind üçün kod nümunəsi:*
```bash
# Composer quraşdırma (global)
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer

# Versiyaya baxmaq
composer --version
```

---

## Dependency Resolution Algorithm

Composer **SAT solver** (Boolean Satisfiability Problem) əsaslı alqoritm istifadə edir. Bu alqoritm bütün paketlərin versiya məhdudiyyətlərini eyni anda nəzərə alaraq uyğun kombinasiyanı tapır.

### Necə işləyir:

1. `composer.json` faylındakı bütün tələblər oxunur
2. Packagist-dən hər paketin mövcud versiyaları çəkilir
3. SAT solver bütün məhdudiyyətləri eyni anda həll etməyə çalışır
4. Əgər konflikt varsa — xəta verir
5. Konflikt yoxdursa — `composer.lock` yaradır

```
layihə → package-a:^2.0
layihə → package-b:^1.5
package-a:2.3 → package-c:^1.0
package-b:1.6 → package-c:^1.2

Həll: package-c:1.2+ (hər ikisini ödəyir)
```

### Niyə yavaş ola bilər?

Çox sayda paketi olan böyük layihələrdə SAT solver kombinatorial partlama yaşaya bilər. Buna görə `composer update` bəzən uzun çəkir.

---

## composer.json vs composer.lock

### composer.json

İnsan tərəfindən yazılan, versiya **məhdudiyyətlərini** (constraints) saxlayan fayl.

*İnsan tərəfindən yazılan, versiya **məhdudiyyətlərini** (constraints)  üçün kod nümunəsi:*
```json
{
    "name": "myapp/myapp",
    "description": "My Laravel Application",
    "type": "project",
    "require": {
        "php": "^8.2",
        "laravel/framework": "^11.0",
        "guzzlehttp/guzzle": "^7.2"
    },
    "require-dev": {
        "phpunit/phpunit": "^11.0",
        "fakerphp/faker": "^1.9",
        "laravel/pint": "^1.0"
    },
    "autoload": {
        "psr-4": {
            "App\\": "app/",
            "Database\\Factories\\": "database/factories/",
            "Database\\Seeders\\": "database/seeders/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Tests\\": "tests/"
        }
    },
    "minimum-stability": "stable",
    "prefer-stable": true
}
```

### composer.lock

Composer tərəfindən **avtomatik** yaranan, hər paketin **dəqiq** versiyasını, hash-ini və tam dependency ağacını saxlayan fayl.

*Composer tərəfindən **avtomatik** yaranan, hər paketin **dəqiq** versi üçün kod nümunəsi:*
```json
{
    "packages": [
        {
            "name": "laravel/framework",
            "version": "v11.5.0",
            "source": {
                "type": "git",
                "url": "https://github.com/laravel/framework.git",
                "reference": "abc123def456..."
            },
            "dist": {
                "type": "zip",
                "url": "https://api.github.com/repos/laravel/framework/zipball/abc123...",
                "shasum": "sha256hashvalue..."
            },
            "require": {
                "php": "^8.2",
                "ext-json": "*"
            }
        }
    ],
    "content-hash": "md5hashofcomposerjson"
}
```

### Fərqlər cədvəli

| Xüsusiyyət | composer.json | composer.lock |
|---|---|---|
| Yazan | Developer | Composer özü |
| Məzmun | Versiya məhdudiyyətləri | Dəqiq versiyalar |
| Git-ə commit | Həmişə | Həmişə (library-larda bəzən .gitignore) |
| `composer install` | Oxuyur | Yazır / oxuyur |
| `composer update` | Oxuyur | Yenidən yazır |

### Mühüm qayda

- **`composer install`** — `composer.lock` varsa, ondan oxuyur (production-da istifadə et)
- **`composer update`** — `composer.json`-a baxaraq yeni versiyalar axtarır (development-də istifadə et)

*- **`composer update`** — `composer.json`-a baxaraq yeni versiyalar ax üçün kod nümunəsi:*
```bash
# Production deployment
composer install --no-dev --optimize-autoloader

# Development yenilənmə
composer update laravel/framework
```

---

## Semantic Versioning

Semantic Versioning (SemVer) formatı: `MAJOR.MINOR.PATCH`

- **MAJOR** — breaking changes (köhnə API ilə uyğunsuz)
- **MINOR** — yeni funksionallıq (geriyə uyğun)
- **PATCH** — bug fix-lər (geriyə uyğun)

### Operator-lar

#### `^` (Caret) — Ən çox istifadə edilən

MAJOR versiyasını sabit saxlayır, MINOR və PATCH-i artıra bilər.

```
^1.2.3  → >=1.2.3 <2.0.0
^1.2    → >=1.2.0 <2.0.0
^1      → >=1.0.0 <2.0.0
^0.3.0  → >=0.3.0 <0.4.0  (0.x.x üçün MINOR sabit)
^0.0.3  → >=0.0.3 <0.0.4  (0.0.x üçün PATCH sabit)
```

#### `~` (Tilde) — Daha məhdud

```
~1.2.3  → >=1.2.3 <1.3.0   (yalnız PATCH-i artıra bilər)
~1.2    → >=1.2.0 <2.0.0   (MINOR-u da artıra bilər)
~1      → >=1.0.0 <2.0.0
```

#### `*` (Wildcard)

```
1.2.*   → >=1.2.0 <1.3.0
1.*     → >=1.0.0 <2.0.0
*       → hər versiya (tövsiyyə edilmir)
```

#### Müqayisə operatorları

```
>1.0    → 1.0-dan böyük hər versiya
>=1.0   → 1.0 və ya daha böyük
<2.0    → 2.0-dan kiçik
<=2.0   → 2.0 və ya daha kiçik
!=1.5.0 → 1.5.0 istisna
```

#### Range (Aralıq)

```
>=1.0 <2.0       → 1.x.x
>=1.0 <1.5 || >=2.0  → 1.0-1.5 arası ya da 2.0+
1.0 - 2.0        → >=1.0.0 <=2.0.0
```

### Praktiki nümunə

*Praktiki nümunə üçün kod nümunəsi:*
```json
{
    "require": {
        "php": "^8.2",
        "laravel/framework": "^11.0",
        "doctrine/dbal": "^3.6|^4.0",
        "league/flysystem": "~3.0",
        "ext-pdo": "*",
        "ext-json": "*"
    }
}
```

---

## require vs require-dev

### require

Production-da lazım olan paketlər. `composer install --no-dev` zamanı yüklənir.

*Production-da lazım olan paketlər. `composer install --no-dev` zamanı  üçün kod nümunəsi:*
```json
{
    "require": {
        "laravel/framework": "^11.0",
        "predis/predis": "^2.0",
        "spatie/laravel-permission": "^6.0",
        "league/flysystem-aws-s3-v3": "^3.0"
    }
}
```

### require-dev

Yalnız development və testing zamanı lazım olan paketlər.

*Yalnız development və testing zamanı lazım olan paketlər üçün kod nümunəsi:*
```json
{
    "require-dev": {
        "phpunit/phpunit": "^11.0",
        "fakerphp/faker": "^1.23",
        "laravel/pint": "^1.0",
        "laravel/telescope": "^5.0",
        "mockery/mockery": "^1.6",
        "nunomaduro/collision": "^8.1"
    }
}
```

### Deployment qaydası

*Deployment qaydası üçün kod nümunəsi:*
```bash
# CI/CD pipeline və production
composer install --no-dev --optimize-autoloader --no-interaction

# Local development
composer install
```

---

## Autoloading

Autoloading PHP-nin siniflərini fayllara baxmadan avtomatik yükləməsidir. Composer `vendor/autoload.php` faylını yaradır.

### PSR-4 (Ən çox istifadə edilən)

Namespace ilə fayl sistemi arasında birbaşa əlaqə. Standart və tövsiyyə edilən üsul.

*Namespace ilə fayl sistemi arasında birbaşa əlaqə. Standart və tövsiyy üçün kod nümunəsi:*
```json
{
    "autoload": {
        "psr-4": {
            "App\\": "app/",
            "MyPackage\\": "src/"
        }
    }
}
```

```
App\Http\Controllers\UserController → app/Http/Controllers/UserController.php
MyPackage\Services\PaymentService  → src/Services/PaymentService.php
```

### PSR-0 (Köhnəlmiş)

PSR-4-dən əvvəlki standart. Namespace-dəki `_` simvolu qovluq ayırıcısı kimi işləyirdi.

*PSR-4-dən əvvəlki standart. Namespace-dəki `_` simvolu qovluq ayırıcıs üçün kod nümunəsi:*
```json
{
    "autoload": {
        "psr-0": {
            "Vendor_Package_": "src/"
        }
    }
}
```

```
Vendor_Package_ClassName → src/Vendor/Package/ClassName.php
```

PSR-0 artıq **deprecated**-dir. PSR-4 istifadə edin.

### classmap

Konkret qovluq və ya faylları skan edib bütün sinifləri map-ə əlavə edir. PSR-4/PSR-0 standartına uymayan köhnə kodlar üçün istifadə edilir.

*Konkret qovluq və ya faylları skan edib bütün sinifləri map-ə əlavə ed üçün kod nümunəsi:*
```json
{
    "autoload": {
        "classmap": [
            "database/seeds",
            "database/factories",
            "legacy/classes/"
        ]
    }
}
```

*"legacy/classes/" üçün kod nümunəsi:*
```bash
# classmap yeniləmək
composer dump-autoload
```

### files

Hər request-də avtomatik yüklənəcək fayllar. Funksiyalar, constants, helper-lər üçün istifadə edilir (sinif olmayan PHP kodu).

*Hər request-də avtomatik yüklənəcək fayllar. Funksiyalar, constants, h üçün kod nümunəsi:*
```json
{
    "autoload": {
        "files": [
            "src/helpers.php",
            "src/constants.php"
        ]
    }
}
```

*"src/constants.php" üçün kod nümunəsi:*
```php
// src/helpers.php
if (!function_exists('format_money')) {
    function format_money(float $amount, string $currency = 'AZN'): string
    {
        return number_format($amount, 2) . ' ' . $currency;
    }
}
```

### Fərqlər cədvəli

| Tip | İstifadə | Performance |
|---|---|---|
| PSR-4 | Namespace-based siniflər | Yaxşı |
| PSR-0 | Köhnə kod (deprecated) | Zəif |
| classmap | Namespace-siz köhnə siniflər | Ən yaxşı (precompiled) |
| files | Funksiyalar, constants | Hər request yüklənir |

### Optimizasiya

*Optimizasiya üçün kod nümunəsi:*
```bash
# Development
composer dump-autoload

# Production (classmap yaradaraq daha sürətli)
composer dump-autoload --optimize
# və ya
composer dump-autoload -o

# install zamanı
composer install --optimize-autoloader
```

---

## Scripts

Composer scripts müəyyən hadisələr zamanı avtomatik işləyən komandalar/PHP callables-dır.

### Standart events

*Standart events üçün kod nümunəsi:*
```json
{
    "scripts": {
        "pre-install-cmd": [
            "echo 'Install başlayır...'"
        ],
        "post-install-cmd": [
            "@php artisan key:generate --ansi",
            "@php artisan storage:link",
            "@php artisan migrate --force"
        ],
        "pre-update-cmd": [
            "echo 'Update başlayır...'"
        ],
        "post-update-cmd": [
            "Illuminate\\Foundation\\ComposerScripts::postUpdate",
            "@php artisan optimize"
        ],
        "post-autoload-dump": [
            "Illuminate\\Foundation\\ComposerScripts::postAutoloadDump",
            "@php artisan package:discover --ansi"
        ],
        "post-root-package-install": [
            "@php -r \"file_exists('.env') || copy('.env.example', '.env');\""
        ],
        "post-create-project-cmd": [
            "@php artisan key:generate --ansi",
            "@php artisan migrate --graceful --ansi"
        ]
    }
}
```

### Custom scripts

*Custom scripts üçün kod nümunəsi:*
```json
{
    "scripts": {
        "test": [
            "@php artisan test"
        ],
        "test-coverage": [
            "@php artisan test --coverage"
        ],
        "lint": [
            "./vendor/bin/pint"
        ],
        "analyse": [
            "./vendor/bin/phpstan analyse"
        ],
        "ci": [
            "@lint",
            "@analyse",
            "@test"
        ],
        "deploy": [
            "composer install --no-dev -o",
            "@php artisan migrate --force",
            "@php artisan optimize",
            "@php artisan queue:restart"
        ]
    }
}
```

*"@php artisan queue:restart" üçün kod nümunəsi:*
```bash
composer run test
composer run ci
composer run deploy
```

### PHP callable scripts

*PHP callable scripts üçün kod nümunəsi:*
```json
{
    "scripts": {
        "post-install-cmd": [
            "MyApp\\Composer\\Scripts::postInstall"
        ]
    }
}
```

*"MyApp\\Composer\\Scripts::postInstall" üçün kod nümunəsi:*
```php
// src/Composer/Scripts.php
namespace MyApp\Composer;

use Composer\Script\Event;

class Scripts
{
    public static function postInstall(Event $event): void
    {
        $io = $event->getIO();
        $io->write('Custom post-install script işləyir...');

        // Environment yoxlama
        if (!file_exists('.env')) {
            copy('.env.example', '.env');
            $io->write('.env faylı yaradıldı');
        }
    }
}
```

---

## Repositories

### Packagist (Default)

Composer default olaraq [packagist.org](https://packagist.org)-u istifadə edir.

*Composer default olaraq [packagist.org](https://packagist.org)-u istif üçün kod nümunəsi:*
```json
{
    "repositories": []
}
```

### VCS Repository

Git, SVN, Mercurial repository-dən birbaşa paket quraşdırmaq.

*Git, SVN, Mercurial repository-dən birbaşa paket quraşdırmaq üçün kod nümunəsi:*
```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/mycompany/private-package.git"
        },
        {
            "type": "vcs",
            "url": "git@gitlab.com:mycompany/another-package.git"
        }
    ],
    "require": {
        "mycompany/private-package": "dev-main"
    }
}
```

### Path Repository

Local qovluqdan paket quraşdırmaq. Monorepo üçün ideal.

*Local qovluqdan paket quraşdırmaq. Monorepo üçün ideal üçün kod nümunəsi:*
```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../packages/my-local-package",
            "options": {
                "symlink": true
            }
        }
    ],
    "require": {
        "mycompany/my-local-package": "@dev"
    }
}
```

`symlink: true` ilə local dəyişikliklər dərhal əks olunur (vendor/ altında symlink yaranır).

### Artifact Repository

Zip arxiv fayllarından paket quraşdırmaq (offline mühit üçün).

*Zip arxiv fayllarından paket quraşdırmaq (offline mühit üçün) üçün kod nümunəsi:*
```json
{
    "repositories": [
        {
            "type": "artifact",
            "url": "/path/to/artifacts/"
        }
    ]
}
```

### Packagist-i deaktiv etmək

*Packagist-i deaktiv etmək üçün kod nümunəsi:*
```json
{
    "repositories": [
        {
            "type": "composer",
            "url": "https://my-satis.company.com"
        },
        {
            "packagist.org": false
        }
    ]
}
```

---

## Private Packages

### Satis

Composer-in open-source, self-hosted mirror-i. Öz şirkət paketlərinizi internal Packagist kimi host edə bilərsiniz.

*Composer-in open-source, self-hosted mirror-i. Öz şirkət paketlərinizi üçün kod nümunəsi:*
```bash
# Satis quraşdırma
composer create-project composer/satis --stability=dev --keep-vcs
```

*composer create-project composer/satis --stability=dev --keep-vcs üçün kod nümunəsi:*
```json
// satis.json
{
    "name": "My Company Repository",
    "homepage": "https://packages.mycompany.com",
    "repositories": [
        {
            "type": "vcs",
            "url": "git@gitlab.com:mycompany/package1.git"
        },
        {
            "type": "vcs",
            "url": "git@gitlab.com:mycompany/package2.git"
        }
    ],
    "require-all": true,
    "archive": {
        "directory": "dist",
        "format": "zip",
        "skip-dev": true
    }
}
```

*"skip-dev": true üçün kod nümunəsi:*
```bash
# Satis build etmək
php bin/satis build satis.json web/

# Cron ilə avtomatik rebuild
0 * * * * cd /path/to/satis && php bin/satis build satis.json web/
```

Layihədə istifadə:
*Layihədə istifadə üçün kod nümunəsi:*
```json
{
    "repositories": [
        {
            "type": "composer",
            "url": "https://packages.mycompany.com"
        }
    ]
}
```

### Private Packagist

[Private Packagist](https://packagist.com) — commercial, managed private package hosting.

*[Private Packagist](https://packagist.com) — commercial, managed priva üçün kod nümunəsi:*
```json
{
    "repositories": [
        {
            "type": "composer",
            "url": "https://repo.packagist.com/mycompany/"
        }
    ],
    "config": {
        "bearer": {
            "repo.packagist.com": "your-api-token-here"
        }
    }
}
```

### GitHub Packages

*GitHub Packages üçün kod nümunəsi:*
```json
{
    "repositories": [
        {
            "type": "composer",
            "url": "https://maven.pkg.github.com/OWNER/REPOSITORY"
        }
    ],
    "config": {
        "http-basic": {
            "maven.pkg.github.com": {
                "username": "github-username",
                "password": "ghp_personalAccessToken"
            }
        }
    }
}
```

### Authentication (auth.json)

Credentials-i `auth.json`-da saxlamaq (`.gitignore`-a əlavə edin!):

*Credentials-i `auth.json`-da saxlamaq (`.gitignore`-a əlavə edin!) üçün kod nümunəsi:*
```json
// auth.json (git-ə commit etməyin!)
{
    "http-basic": {
        "repo.packagist.com": {
            "username": "token",
            "password": "your-secret-token"
        }
    },
    "github-oauth": {
        "github.com": "ghp_yourGitHubToken"
    },
    "gitlab-token": {
        "gitlab.com": "your-gitlab-token"
    }
}
```

---

## Əsas Komandalar

### composer install

`composer.lock` varsa ondan, yoxdursa `composer.json`-dan paketləri yükləyir.

*`composer.lock` varsa ondan, yoxdursa `composer.json`-dan paketləri yü üçün kod nümunəsi:*
```bash
# Əsas install
composer install

# Production üçün (dev paketlər olmadan, optimize edilmiş)
composer install --no-dev --optimize-autoloader --no-interaction --no-progress

# Verbose output
composer install -v
```

### composer update

Yeni versiyaları yoxlayır, `composer.lock`-u yeniləyir.

*Yeni versiyaları yoxlayır, `composer.lock`-u yeniləyir üçün kod nümunəsi:*
```bash
# Bütün paketləri yenilə
composer update

# Yalnız bir paketi yenilə
composer update laravel/framework

# Wildcard ilə
composer update "spatie/*"

# --with-dependencies ilə (transitive dependencies də yenilənir)
composer update laravel/framework --with-dependencies

# Yalnız lock faylını yenilə (versiyanı dəyişmə)
composer update --lock
```

### composer require

Yeni paket əlavə et.

*Yeni paket əlavə et üçün kod nümunəsi:*
```bash
# Packagist-dən paket əlavə et
composer require spatie/laravel-permission

# Spesifik versiya
composer require "guzzlehttp/guzzle:^7.0"

# Dev dependency olaraq
composer require --dev phpunit/phpunit

# Bir neçə paket eyni anda
composer require predis/predis league/flysystem
```

### composer remove

Paketi sil.

*composer remove üçün kod nümunəsi:*
```bash
composer remove spatie/laravel-permission
composer remove --dev phpunit/phpunit
```

### composer dump-autoload

Autoload fayllarını regenerate et.

*Autoload fayllarını regenerate et üçün kod nümunəsi:*
```bash
composer dump-autoload
composer dump-autoload --optimize   # Production üçün
composer dump-autoload -o           # Qısaldılmış variant
composer dump-autoload --classmap-authoritative  # Yalnız classmap
```

### composer show

Paket məlumatlarını göstər.

*Paket məlumatlarını göstər üçün kod nümunəsi:*
```bash
# Bütün quraşdırılmış paketlər
composer show

# Konkret paket haqqında
composer show laravel/framework

# Quraşdırılmış versiyaları JSON formatda
composer show --format=json

# Köhnəlmiş paketlər
composer show --outdated
# və ya
composer outdated
```

### composer outdated

Yeniləyə biləcəyiniz paketləri göstərir.

*Yeniləyə biləcəyiniz paketləri göstərir üçün kod nümunəsi:*
```bash
composer outdated

# Yalnız birbaşa asılılıqlar (transitive deyil)
composer outdated --direct

# Minor/patch yeniləmələri istisna et, yalnız major göstər
composer outdated --major-only
```

### composer audit

Təhlükəsizlik zəifliklərini yoxla.

*Təhlükəsizlik zəifliklərini yoxla üçün kod nümunəsi:*
```bash
# Bütün quraşdırılmış paketlər üçün vulnerability scan
composer audit

# JSON output (CI/CD üçün)
composer audit --format=json

# Yalnız abandoned paketlər
composer audit --abandoned=report
```

### composer why

Paketin niyə quraşdırıldığını göstər.

*Paketin niyə quraşdırıldığını göstər üçün kod nümunəsi:*
```bash
# Hansı paket bu paketi tələb edir?
composer why symfony/console

# composer why-not — niyə quraşdırıla bilmir
composer why-not php 8.4
composer why-not laravel/framework 12.0
```

### Digər faydalı komandalar

*Digər faydalı komandalar üçün kod nümunəsi:*
```bash
# Yeni Laravel layihəsi yarat
composer create-project laravel/laravel myapp

# Global paket quraşdır
composer global require laravel/installer

# Cache təmizlə
composer clear-cache
composer cc  # qısaldılmış

# Validate composer.json
composer validate

# Diagnose (problemləri tap)
composer diagnose

# Composer özünü güncəllə
composer self-update
```

---

## Platform Requirements

Platform requirements PHP versiyasını və PHP extension-larını tələb etmək üçündür.

*Platform requirements PHP versiyasını və PHP extension-larını tələb et üçün kod nümunəsi:*
```json
{
    "require": {
        "php": "^8.2",
        "php-64bit": "*",
        "ext-pdo": "*",
        "ext-json": "*",
        "ext-mbstring": "*",
        "ext-openssl": "*",
        "ext-curl": "*",
        "ext-redis": "^5.0|^6.0",
        "lib-pcre": "^8.0"
    }
}
```

### Platform config

Test mühitinizin fərqli PHP versiyasını simulyasiya etmək üçün:

*Test mühitinizin fərqli PHP versiyasını simulyasiya etmək üçün üçün kod nümunəsi:*
```json
{
    "config": {
        "platform": {
            "php": "8.2.0",
            "ext-pdo": "8.2.0"
        }
    }
}
```

*"ext-pdo": "8.2.0" üçün kod nümunəsi:*
```bash
# Platform requirements yoxla
composer check-platform-reqs
```

---

## Conflict, Replace, Provide

### conflict

Bu paketin digər paketlərlə uyğunsuzluğunu bildirir.

*Bu paketin digər paketlərlə uyğunsuzluğunu bildirir üçün kod nümunəsi:*
```json
{
    "conflict": {
        "some/incompatible-package": "*",
        "old/abandoned-library": "<2.0"
    }
}
```

### replace

Bu paketin başqa bir paketi əvəz etdiyini bildirir. Fork-larda ya da bir paketin başqasını daxil etdiyi hallarda istifadə edilir.

*Bu paketin başqa bir paketi əvəz etdiyini bildirir. Fork-larda ya da b üçün kod nümunəsi:*
```json
{
    "replace": {
        "symfony/polyfill-php80": "1.0",
        "laminas/laminas-zendframework-bridge": "*"
    }
}
```

Laravel framework `illuminate/*` paketlərinin hamısını `replace` edir:

*Laravel framework `illuminate/*` paketlərinin hamısını `replace` edir üçün kod nümunəsi:*
```json
// laravel/framework composer.json
{
    "replace": {
        "illuminate/auth": "self.version",
        "illuminate/bus": "self.version",
        "illuminate/cache": "self.version",
        "illuminate/config": "self.version"
    }
}
```

### provide

Bu paketin virtual bir interfeys/API-ni implement etdiyini bildirir.

*Bu paketin virtual bir interfeys/API-ni implement etdiyini bildirir üçün kod nümunəsi:*
```json
{
    "provide": {
        "psr/log-implementation": "1.0|2.0",
        "psr/cache-implementation": "1.0|2.0"
    }
}
```

---

## Custom Laravel Package Yaratma

Tam bir Laravel paketi yaratmağın addım-addım prosesi.

### 1. Folder Structure

```
packages/
└── mycompany/
    └── laravel-notifications/
        ├── composer.json
        ├── README.md
        ├── src/
        │   ├── NotificationsServiceProvider.php
        │   ├── Facades/
        │   │   └── Notifications.php
        │   ├── Services/
        │   │   └── NotificationService.php
        │   ├── Models/
        │   │   └── Notification.php
        │   ├── Http/
        │   │   ├── Controllers/
        │   │   │   └── NotificationController.php
        │   │   └── Requests/
        │   │       └── SendNotificationRequest.php
        │   └── Contracts/
        │       └── NotificationInterface.php
        ├── config/
        │   └── notifications.php
        ├── database/
        │   └── migrations/
        │       └── 2024_01_01_000000_create_notifications_table.php
        ├── resources/
        │   └── views/
        │       └── notification.blade.php
        ├── routes/
        │   └── web.php
        └── tests/
            ├── Feature/
            │   └── NotificationTest.php
            └── Unit/
                └── NotificationServiceTest.php
```

### 2. composer.json (Package)

*2. composer.json (Package) üçün kod nümunəsi:*
```json
{
    "name": "mycompany/laravel-notifications",
    "description": "Laravel Notification Management Package",
    "type": "library",
    "license": "MIT",
    "authors": [
        {
            "name": "Orkhan",
            "email": "orkhan@mycompany.com"
        }
    ],
    "require": {
        "php": "^8.2",
        "illuminate/support": "^10.0|^11.0",
        "illuminate/database": "^10.0|^11.0",
        "illuminate/http": "^10.0|^11.0"
    },
    "require-dev": {
        "phpunit/phpunit": "^10.0|^11.0",
        "orchestra/testbench": "^8.0|^9.0",
        "mockery/mockery": "^1.6"
    },
    "autoload": {
        "psr-4": {
            "MyCompany\\Notifications\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "MyCompany\\Notifications\\Tests\\": "tests/"
        }
    },
    "extra": {
        "laravel": {
            "providers": [
                "MyCompany\\Notifications\\NotificationsServiceProvider"
            ],
            "aliases": {
                "Notifications": "MyCompany\\Notifications\\Facades\\Notifications"
            }
        }
    },
    "minimum-stability": "dev",
    "prefer-stable": true
}
```

### 3. ServiceProvider

*3. ServiceProvider üçün kod nümunəsi:*
```php
// src/NotificationsServiceProvider.php
<?php

namespace MyCompany\Notifications;

use Illuminate\Support\ServiceProvider;

class NotificationsServiceProvider extends ServiceProvider
{
    /**
     * register: Container binding-lər burada edilir.
     * Boot-dan əvvəl işləyir, digər service-lərə access yoxdur.
     */
    public function register(): void
    {
        // Config merge et (user config üstün gəlir)
        $this->mergeConfigFrom(
            __DIR__ . '/../config/notifications.php',
            'notifications'
        );

        // Service-i container-a bind et
        $this->app->singleton(
            Contracts\NotificationInterface::class,
            Services\NotificationService::class
        );

        // Facade alias üçün bind
        $this->app->bind('notifications', function ($app) {
            return $app->make(Services\NotificationService::class);
        });
    }

    /**
     * boot: Bütün service provider-lar register olduqdan sonra çağırılır.
     * Route, view, migration publish burada edilir.
     */
    public function boot(): void
    {
        // Routes yüklə
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');

        // Views yüklə (namespace ilə)
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'notifications');

        // Migrations yüklə
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Translations yüklə
        $this->loadTranslationsFrom(__DIR__ . '/../lang', 'notifications');

        // Yalnız artisan-dan çalışanda publish et
        if ($this->app->runningInConsole()) {
            $this->publishConfig();
            $this->publishMigrations();
            $this->publishViews();
            $this->publishAssets();
        }
    }

    protected function publishConfig(): void
    {
        $this->publishes([
            __DIR__ . '/../config/notifications.php' => config_path('notifications.php'),
        ], 'notifications-config');
    }

    protected function publishMigrations(): void
    {
        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'notifications-migrations');
    }

    protected function publishViews(): void
    {
        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/notifications'),
        ], 'notifications-views');
    }

    protected function publishAssets(): void
    {
        $this->publishes([
            __DIR__ . '/../resources/assets' => public_path('vendor/notifications'),
        ], 'notifications-assets');
    }

    /**
     * Package-in təqdim etdiyi service-ləri siyahıla (optional, introspection üçün).
     */
    public function provides(): array
    {
        return [
            Contracts\NotificationInterface::class,
            'notifications',
        ];
    }
}
```

### 4. Config Faylı

*4. Config Faylı üçün kod nümunəsi:*
```php
// config/notifications.php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Notification Channel
    |--------------------------------------------------------------------------
    */
    'default_channel' => env('NOTIFICATION_CHANNEL', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Channels
    |--------------------------------------------------------------------------
    */
    'channels' => [
        'database' => [
            'table' => 'notifications',
        ],
        'slack' => [
            'webhook_url' => env('SLACK_WEBHOOK_URL'),
            'channel' => env('SLACK_CHANNEL', '#general'),
        ],
        'email' => [
            'from' => env('MAIL_FROM_ADDRESS', 'noreply@example.com'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cleanup Settings
    |--------------------------------------------------------------------------
    */
    'cleanup' => [
        'enabled' => env('NOTIFICATION_CLEANUP_ENABLED', true),
        'days' => env('NOTIFICATION_CLEANUP_DAYS', 30),
    ],
];
```

### 5. Facade Yaratma

*5. Facade Yaratma üçün kod nümunəsi:*
```php
// src/Facades/Notifications.php
<?php

namespace MyCompany\Notifications\Facades;

use Illuminate\Support\Facades\Facade;
use MyCompany\Notifications\Services\NotificationService;

/**
 * @method static void send(string $userId, string $message, array $data = [])
 * @method static array getUnread(string $userId)
 * @method static int markAsRead(string $userId)
 * @method static int getUnreadCount(string $userId)
 *
 * @see NotificationService
 */
class Notifications extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'notifications';
    }
}
```

### 6. Service Class

*6. Service Class üçün kod nümunəsi:*
```php
// src/Services/NotificationService.php
<?php

namespace MyCompany\Notifications\Services;

use MyCompany\Notifications\Contracts\NotificationInterface;
use Illuminate\Support\Facades\DB;

class NotificationService implements NotificationInterface
{
    public function __construct(
        private readonly array $config
    ) {}

    public function send(string $userId, string $message, array $data = []): void
    {
        DB::table($this->config['channels']['database']['table'])->insert([
            'user_id'    => $userId,
            'message'    => $message,
            'data'       => json_encode($data),
            'read_at'    => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function getUnread(string $userId): array
    {
        return DB::table($this->config['channels']['database']['table'])
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->orderByDesc('created_at')
            ->get()
            ->toArray();
    }

    public function markAsRead(string $userId): int
    {
        return DB::table($this->config['channels']['database']['table'])
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function getUnreadCount(string $userId): int
    {
        return DB::table($this->config['channels']['database']['table'])
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->count();
    }
}
```

### 7. Testing (Orchestra Testbench)

*7. Testing (Orchestra Testbench) üçün kod nümunəsi:*
```php
// tests/Feature/NotificationTest.php
<?php

namespace MyCompany\Notifications\Tests\Feature;

use Orchestra\Testbench\TestCase;
use MyCompany\Notifications\NotificationsServiceProvider;
use MyCompany\Notifications\Facades\Notifications;

class NotificationTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [NotificationsServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'Notifications' => \MyCompany\Notifications\Facades\Notifications::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
    }

    public function test_can_send_notification(): void
    {
        Notifications::send('user-123', 'Test notification', ['type' => 'info']);

        $this->assertDatabaseHas('notifications', [
            'user_id' => 'user-123',
            'message' => 'Test notification',
        ]);
    }

    public function test_can_get_unread_count(): void
    {
        Notifications::send('user-123', 'First');
        Notifications::send('user-123', 'Second');

        $count = Notifications::getUnreadCount('user-123');

        $this->assertEquals(2, $count);
    }

    public function test_can_mark_as_read(): void
    {
        Notifications::send('user-123', 'Test');

        $marked = Notifications::markAsRead('user-123');

        $this->assertEquals(1, $marked);
        $this->assertEquals(0, Notifications::getUnreadCount('user-123'));
    }
}
```

### 8. Local Development (Path Repository)

Ana layihənin `composer.json`-unda:

*Ana layihənin `composer.json`-unda üçün kod nümunəsi:*
```json
{
    "repositories": [
        {
            "type": "path",
            "url": "./packages/mycompany/laravel-notifications",
            "options": {
                "symlink": true
            }
        }
    ],
    "require": {
        "mycompany/laravel-notifications": "@dev"
    }
}
```

### 9. Packagist-ə Publish

1. GitHub-da public repository yarat
2. `composer.json` faylının düzgün olduğundan əmin ol
3. [packagist.org](https://packagist.org)-da "Submit" düyməsinə bas
4. GitHub repository URL-ini daxil et
5. GitHub Webhook qur (avtomatik yeniləmələr üçün):
   - Settings → Webhooks → Add webhook
   - Payload URL: `https://packagist.org/api/github?username=YOUR_USERNAME`
   - Content type: `application/json`
   - Secret: Packagist API token-iniz

*- Secret: Packagist API token-iniz üçün kod nümunəsi:*
```bash
# Tag yaradıb push et
git tag v1.0.0
git push origin v1.0.0

# Packagist avtomatik yeni versiyanu görür
```

---

## Monorepo vs Separate Packages

### Monorepo

Bütün paketlər bir repository-də saxlanılır.

```
company-monorepo/
├── packages/
│   ├── core/
│   │   └── composer.json
│   ├── auth/
│   │   └── composer.json
│   └── billing/
│       └── composer.json
├── apps/
│   ├── api/
│   │   └── composer.json
│   └── admin/
│       └── composer.json
└── composer.json (root)
```

Root `composer.json`:
*Root `composer.json` üçün kod nümunəsi:*
```json
{
    "repositories": [
        {"type": "path", "url": "packages/core"},
        {"type": "path", "url": "packages/auth"},
        {"type": "path", "url": "packages/billing"}
    ],
    "require": {
        "company/core": "@dev",
        "company/auth": "@dev",
        "company/billing": "@dev"
    }
}
```

**Üstünlüklər**: Atomic commits, asan refactoring, paylaşılan CI/CD
**Çatışmazlıqlar**: Böyük repo, access control çətin

### Separate Packages

Hər paket öz repository-sindədir.

**Üstünlüklər**: İzolasiya, müstəqil versioning, granular access control
**Çatışmazlıqlar**: Cross-repo dəyişikliklər çətin, versiya koordinasiyası lazımdır

---

## Dependency Hell

Dependency hell müxtəlif paketlərin bir-biri ilə konflikt yaradan versiyaları tələb etməsi halıdır.

### Ümumi ssenari

```
package-a tələb edir: library-x: ^1.0
package-b tələb edir: library-x: ^2.0
# library-x v1 və v2 eyni anda quraşdırıla bilməz!
```

### Həll yolları

#### 1. Versiyaları manual uyğunlaşdırmaq

*1. Versiyaları manual uyğunlaşdırmaq üçün kod nümunəsi:*
```bash
composer why library-x
composer why-not library-x 2.0

# Conflict-i araşdır
composer update package-a --with-dependencies
```

#### 2. composer.json-da version constraint-ləri dəqiqləşdirmək

*2. composer.json-da version constraint-ləri dəqiqləşdirmək üçün kod nümunəsi:*
```json
{
    "require": {
        "package-a": "^2.0",
        "package-b": "^1.5",
        "library-x": "^2.0"
    }
}
```

#### 3. Paketi fork edib patch tətbiq etmək

*3. Paketi fork edib patch tətbiq etmək üçün kod nümunəsi:*
```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/mycompany/forked-package-a"
        }
    ],
    "require": {
        "original/package-a": "dev-fix-dependency as 2.0.0"
    }
}
```

#### 4. inline alias istifadə etmək

*4. inline alias istifadə etmək üçün kod nümunəsi:*
```json
{
    "require": {
        "library-x": "2.0.x-dev as 1.9.9"
    }
}
```

#### 5. replace istifadə etmək

Əgər paketdən yalnız bir hissəsi lazımdırsa:

*Əgər paketdən yalnız bir hissəsi lazımdırsa üçün kod nümunəsi:*
```json
{
    "replace": {
        "conflicting/dependency": "*"
    }
}
```

### Praktiki debug komandaları

*Praktiki debug komandaları üçün kod nümunəsi:*
```bash
# Niyə bu versiya quraşdırıla bilmir?
composer why-not package/name 3.0

# Kimdir bu paketi tələb edən?
composer why package/name

# Bütün dependency ağacını göstər
composer show --tree

# Konkret paketin ağacı
composer show --tree package/name

# Verbose mode ilə install (conflict-i görmək üçün)
composer install -vvv 2>&1 | grep -i conflict
```

---

## İntervyu Sualları

### 1. `composer install` ilə `composer update` arasındakı fərq nədir?

**Cavab**: `composer install` `composer.lock` faylı varsa ondan oxuyur və dəqiq versiyaları quraşdırır — bu production deployment üçün idealdır, çünki hər deployment eyni versiyaları quraşdırır. `composer update` isə `composer.json`-dakı constraint-lərə uyğun ən yeni versiyaları axtarır, `composer.lock`-u yeniləyir. Development-də istifadə edilir.

### 2. `composer.lock` faylını git-ə commit etmək lazımdırmı?

**Cavab**: **Proqramlar (applications) üçün — bəli**, commit edilməlidir. Bu, bütün komanda üzvlərinin və deployment mühitinin eyni versiyaları istifadə etməsini təmin edir. **Kitabxanalar (libraries) üçün — tövsiyyə edilmir**, çünki kitabxananı istifadə edən layihənin öz lock faylı var. Bununla belə, kitabxanalar üçün də test məqsədilə `.gitignore`-a əlavə etmədən saxlamaq olar.

### 3. Semantic Versioning-də `^1.2.3` nəyi bildirir?

**Cavab**: `>=1.2.3 <2.0.0` deməkdir. MAJOR versiyanı sabit saxlayır, MINOR və PATCH-i artıra bilər. `^0.3.0` isə `>=0.3.0 <0.4.0` deməkdir, çünki 0.x.x versiyalarda MINOR sabit saxlanılır (breaking change ehtimalı var).

### 4. PSR-4 autoloading necə işləyir?

**Cavab**: PSR-4 namespace prefix-ini fayl sistemi path-i ilə map edir. Məsələn, `"App\\": "app/"` konfiqurasiyası ilə `App\Http\Controllers\UserController` sinifi `app/Http/Controllers/UserController.php` faylında axtarılır. Namespace-dəki hər `\` bir qovluq səviyyəsinə uyğun gəlir.

### 5. `require` ilə `require-dev` arasındakı fərq nədir?

**Cavab**: `require` production-da da lazım olan paketlər üçündür (framework, database driver-lar). `require-dev` yalnız development/testing zamanı lazım olan paketlər üçündür (test framework-lar, code style tool-lar, debugger-lər). `composer install --no-dev` ilə production deployment-da `require-dev` paketlər quraşdırılmır, bu da vendor/ qovluğunu kiçildir.

### 6. ServiceProvider-də `register()` ilə `boot()` arasındakı fərq nədir?

**Cavab**: `register()` metodunda yalnız service container binding-ləri edilməlidir. Bu metod bütün provider-ların `register()`-indən əvvəl çağırılır, ona görə digər service-lərə güvənmək olmaz. `boot()` isə bütün provider-lar register olduqdan sonra çağırılır — burada route-lar, view-lar, event listener-lər, migration publish əməliyyatları edilir.

### 7. Satis nədir və nə zaman istifadə edilir?

**Cavab**: Satis Composer-in self-hosted, open-source mirror-idir. Private paketlərinizi şirkət daxilindəki Packagist kimi host etmək üçün istifadə edilir. Packagist.org-a yükləmək istəmədiyiniz internal/proprietary paketlər üçün idealdır. Satis static HTML+JSON faylları yaradır — veb server tələb edir, lakin real-time server tərəfi emal etmir.

### 8. Dependency hell problemi yarandıqda necə həll edərdiniz?

**Cavab**: Əvvəlcə `composer why` və `composer why-not` ilə konflikt səbəbini tapardım. Sonra `composer show --tree` ilə tam dependency ağacına baxardım. Həll yolları: (1) conflict edən paketlərin versiyalarını uyğunlaşdırmaq, (2) paketi fork edib patch tətbiq etmək, (3) inline alias istifadə etmək, (4) `replace` direktivindən istifadə etmək.

### 9. `composer audit` nə edir?

**Cavab**: `composer audit` quraşdırılmış paketləri Packagist Security Advisory verilənlər bazası ilə müqayisə edərək bilinen security vulnerability-ləri yoxlayır. CI/CD pipeline-a daxil edilməsi tövsiyyə olunur. JSON output (`--format=json`) ilə avtomatlaşdırılmış security reportlar yaratmaq mümkündür.

### 10. `files` autoloading nə zaman istifadə edilir?

**Cavab**: `files` autoloading sinif olmayan, lakin avtomatik yüklənməsi lazım olan PHP fayllar üçün istifadə edilir — helper funksiyaları, constants, procedural kod. `files` ilə göstərilən fayllar hər request-də avtomatik `require` edilir. Məsələn, Laravel-in `Illuminate/Support/helpers.php` faylı bu üsulla yüklənir.

### 11. Monorepo-da Composer path repositories necə istifadə edilir?

**Cavab**: Root `composer.json`-da hər lokal paket üçün `"type": "path"` repository əlavə edilir. `"options": {"symlink": true}` ilə vendor/ altında real kopyalanmaq əvəzinə symlink yaranır — bu sayədə paketdəki dəyişikliklər dərhal əks olunur, hər dəfə `composer update` çalışdırmağa ehtiyac qalmır. Versiya olaraq `@dev` istifadə edilir.

### 12. `post-autoload-dump` scripti nə vaxt istləyir?

**Cavab**: `composer install`, `composer update` və `composer dump-autoload` komandalarının sonu işldir. Laravel bu scripti `package:discover` artisan komandası üçün istifadə edir — bu komanda bütün quraşdırılmış paketlərin `composer.json`-dakı `extra.laravel.providers` massivindən ServiceProvider-ları avtomatik kəşf edib `bootstrap/cache/packages.php` faylına yazır.

---

## Anti-patternlər

**1. `composer.lock` faylını versiya kontroluna əlavə etməmək**
`.gitignore`-a `composer.lock` yazmaq — komanda üzvləri fərqli paket versiyaları quraşdırır, production fərqli, development fərqli davranır. `composer.lock`-u mütləq commit et, `composer install` (update yox) istifadə et.

**2. Production-da `composer install` yerinə `composer update` çalışdırmaq**
Hər deploy-da `composer update` etmək — paketlər gözlənilmədən yüksəlir, breaking change-lər production-u poza bilər. Production-da həmişə `composer install --no-dev --optimize-autoloader` işlət.

**3. `dev` paketlərini production-a daxil etmək**
`require` bölməsinə PHPUnit, Faker, Laravel Telescope əlavə etmək — production container-i lazımsız olaraq böyüyür, attack surface artır. `require-dev`-ə əlavə et, deploy-da `--no-dev` flag-i işlət.

**4. Paket versiyasını `"*"` və ya çox geniş constraint ilə göstərmək**
`"guzzlehttp/guzzle": "*"` — istənilən versiya quraşdırıla bilər, breaking change-lər avtomatik daxil olur, bütün dependency tree qeyri-sabitləşir. Semantic versioning constraint-ləri (`^6.5`, `~2.0`) düzgün istifadə et.

**5. `composer audit`-i CI pipeline-a daxil etməmək**
Security vulnerability yoxlamasını manual etmək — bilinen CVE-lər olan paketlər production-da qalır, yalnız breach olduqda aşkar olunur. `composer audit` addımını CI-a əlavə et, kritik vulnerability-lər pipeline-ı dayandırsın.

**6. Autoload `classmap` optimizasiyasını production-da atlamaq**
`composer install`-ı `--optimize-autoloader` olmadan çalışdırmaq — hər class yükündə file system-də axtarış aparılır, yüksək trafikdə əhəmiyyətli performans itkisi baş verir. Production deploy scriptinə `--optimize-autoloader` ya da `--classmap-authoritative` flag-i əlavə et.
